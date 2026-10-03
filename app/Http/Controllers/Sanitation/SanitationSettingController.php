<?php
// app/Http/Controllers/Sanitation/SanitationSettingController.php

namespace App\Http\Controllers\Sanitation;

use App\Http\Controllers\Controller;
use App\Models\SanitationSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SanitationSettingController extends Controller
{
    /**
     * Allowed weekday names — single source of truth.
     */
    private const WEEKDAYS = [
        'Monday', 'Tuesday', 'Wednesday', 'Thursday',
        'Friday', 'Saturday', 'Sunday',
    ];

    /**
     * Allowed collection frequencies — single source of truth.
     */
    private const COLLECTION_FREQUENCIES = [
        'daily', 'weekly', 'biweekly', 'monthly',
    ];

    /**
     * Canonical "H:i" format for operational times.
     */
    private const OPERATIONAL_TIME_FORMAT = 'H:i';

    /**
     * ✅ Whitelist of `.env` keys this controller is allowed to write.
     *
     * Never write arbitrary user input to `.env` — always gate on an
     * explicit allowlist. Add new keys here as new settings are added.
     */
    private const WRITABLE_ENV_KEYS = [
        'GOOGLE_MAPS_API_KEY',
        'GOOGLE_MAPS_BROWSER_KEY',
        'GOOGLE_MAPS_SERVER_KEY',
        'GOOGLE_MAPS_DEFAULT_LAT',
        'GOOGLE_MAPS_DEFAULT_LNG',
        'GOOGLE_MAPS_DEFAULT_ZOOM',
        'GOOGLE_MAPS_DEFAULT_COUNTRY',
        'GOOGLE_MAPS_DEFAULT_REGION',
        'GHANA_POST_GPS_ENABLED',
        'GHANA_POST_GPS_API_KEY',
        'GHANA_POST_GPS_URL',
    ];

    // ================================================================ //
    // 🔒 AUTHORIZATION                                                //
    // ================================================================ //

    /**
     * Ensure the current user is allowed to manage sanitation settings.
     */
    private function authorizeSettingsAccess(): void
    {
        $user = auth()->user();

        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        // Root supervisor ONLY — no admin bypass
        $personnel = $user->sanitationPersonnel;

        if ($personnel
            && $personnel->isSupervisor()
            && is_null($personnel->supervisor_id)) {
            return;
        }

        Log::warning('Unauthorized sanitation settings access attempt', [
            'user_id'        => $user->id,
            'user_type'      => $user->type,
            'personnel_id'   => $personnel?->id,
            'personnel_role' => $personnel?->role,
            'supervisor_id'  => $personnel?->supervisor_id,
            'route'          => request()->route()?->getName(),
            'ip'             => request()->ip(),
        ]);

        abort(403, 'Only the root sanitation supervisor can access sanitation settings.');
    }

    // ================================================================ //
    // 📊 INDEX / EDIT                                                 //
    // ================================================================ //

    /**
     * Display sanitation settings.
     */
    public function index()
    {
        $this->authorizeSettingsAccess();

        $settings       = SanitationSetting::getSettings();
        $activeSettings = SanitationSetting::getActiveSettings();

        // ✅ Provide the canonical option lists to the view so the
        //    edit blade can render checkboxes/selects consistently.
        $weekdays              = self::WEEKDAYS;
        $collectionFrequencies = self::COLLECTION_FREQUENCIES;

        // ✅ NEW: Google Maps + Ghana Post GPS status (no secrets leaked)
        $googleMapsStatus = $this->getGoogleMapsStatus();

        return view('sanitation.settings.index', compact(
            'settings',
            'activeSettings',
            'weekdays',
            'collectionFrequencies',
            'googleMapsStatus'
        ));
    }

    /**
     * Show form to create/update settings.
     */
    public function edit()
    {
        $this->authorizeSettingsAccess();

        $settings = SanitationSetting::getSettings();
        $isNew    = !$settings->exists;

        $weekdays              = self::WEEKDAYS;
        $collectionFrequencies = self::COLLECTION_FREQUENCIES;

        // ✅ NEW: Google Maps + Ghana Post GPS status (no secrets leaked)
        $googleMapsStatus = $this->getGoogleMapsStatus();

        return view('sanitation.settings.edit', compact(
            'settings',
            'isNew',
            'weekdays',
            'collectionFrequencies',
            'googleMapsStatus'
        ));
    }

    // ================================================================ //
    // 💾 STORE / UPDATE                                               //
    // ================================================================ //

    /**
     * Store or update settings.
     */
    public function store(Request $request)
    {
        $this->authorizeSettingsAccess();

        $validator = $this->validateSettings($request);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            DB::beginTransaction();

            $settings = SanitationSetting::firstOrNew();

            // Prepare data
            $data = $request->all();

            // Handle logo upload
            if ($request->hasFile('company_logo')) {
                $logoPath = $this->handleLogoUpload($request->file('company_logo'));
                if ($logoPath) {
                    // Delete old logo
                    if ($settings->company_logo && Storage::disk('public')->exists($settings->company_logo)) {
                        Storage::disk('public')->delete($settings->company_logo);
                    }
                    $data['company_logo'] = $logoPath;
                }
            }

            // Handle JSON fields
            $jsonFields = [
                'operational_days',
                'service_areas',
                'default_collection_frequencies',
                'default_collection_days',
                'default_waste_types',
                'vehicle_types',
                'notification_preferences',
                'reminder_settings',
                'emergency_contacts',
                'frequency_pricing',
                'metadata',
            ];

            foreach ($jsonFields as $field) {
                if ($request->has($field)) {
                    $data[$field] = $this->processJsonField($request->input($field));
                }
            }

            // Normalize schedule fields
            if (array_key_exists('operational_days', $data)) {
                $data['operational_days'] = $this->normalizeWeekdays($data['operational_days']);
            }
            if (array_key_exists('default_collection_days', $data)) {
                $data['default_collection_days'] = $this->normalizeWeekdays($data['default_collection_days']);
            }
            if (array_key_exists('default_collection_frequencies', $data)) {
                $data['default_collection_frequencies'] = $this->normalizeFrequencies($data['default_collection_frequencies']);
            }

            // Normalize operational times to "H:i"
            if (array_key_exists('operational_start_time', $data)) {
                $data['operational_start_time'] = $this->normalizeOperationalTime($data['operational_start_time']);
            }
            if (array_key_exists('operational_end_time', $data)) {
                $data['operational_end_time'] = $this->normalizeOperationalTime($data['operational_end_time']);
            }

            // Set timestamps
            if (!$settings->exists) {
                $data['created_by'] = auth()->id();
            }
            $data['updated_by'] = auth()->id();

            // Save settings
            $settings->fill($data);
            $settings->save();

            DB::commit();

            Log::info('Sanitation settings updated', [
                'settings_id'                    => $settings->id,
                'updated_by'                     => auth()->id(),
                'company_name'                   => $settings->company_name,
                'operational_start_time'         => $settings->operational_start_time,
                'operational_end_time'           => $settings->operational_end_time,
                'operational_days'               => $settings->operational_days,
                'default_collection_days'        => $settings->default_collection_days,
                'default_collection_frequencies' => $settings->default_collection_frequencies,
            ]);

            return redirect()->route('sanitation.settings.index')
                ->with('success', 'Sanitation settings updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update sanitation settings: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update settings: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Update settings (alias).
     */
    public function update(Request $request)
    {
        $this->authorizeSettingsAccess();

        return $this->store($request);
    }

    // ================================================================ //
    // ✅ NEW: UPDATE ENVIRONMENT (.env)                               //
    // ================================================================ //

    /**
     * Write Google Maps + Ghana Post GPS credentials to `.env` and
     * reload the config cache.
     *
     * Only the root sanitation supervisor may call this.
     */
    public function updateEnvironment(Request $request)
    {
        $this->authorizeSettingsAccess();

        $validator = Validator::make($request->all(), [
            'GOOGLE_MAPS_API_KEY'         => 'nullable|string|max:255',
            'GOOGLE_MAPS_BROWSER_KEY'     => 'nullable|string|max:255',
            'GOOGLE_MAPS_SERVER_KEY'      => 'nullable|string|max:255',
            'GOOGLE_MAPS_DEFAULT_LAT'     => 'nullable|numeric|between:-90,90',
            'GOOGLE_MAPS_DEFAULT_LNG'     => 'nullable|numeric|between:-180,180',
            'GOOGLE_MAPS_DEFAULT_ZOOM'    => 'nullable|integer|between:1,22',
            'GOOGLE_MAPS_DEFAULT_COUNTRY' => 'nullable|string|size:2',
            'GOOGLE_MAPS_DEFAULT_REGION'  => 'nullable|string|size:2',
            'GHANA_POST_GPS_ENABLED'      => 'nullable|boolean',
            'GHANA_POST_GPS_API_KEY'      => 'nullable|string|max:255',
            'GHANA_POST_GPS_URL'          => 'nullable|url|max:255',
        ], [
            'GOOGLE_MAPS_DEFAULT_LAT.between' => 'Latitude must be between -90 and 90.',
            'GOOGLE_MAPS_DEFAULT_LNG.between' => 'Longitude must be between -180 and 180.',
            'GOOGLE_MAPS_DEFAULT_ZOOM.between' => 'Zoom level must be between 1 and 22.',
            'GOOGLE_MAPS_DEFAULT_COUNTRY.size' => 'Country code must be exactly 2 letters (e.g., GH).',
            'GOOGLE_MAPS_DEFAULT_REGION.size'  => 'Region code must be exactly 2 letters (e.g., GH).',
            'GHANA_POST_GPS_URL.url'           => 'Ghana Post GPS URL must be a valid URL.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $validated = $validator->validated();

            // Build the set of updates keyed by the whitelisted env name
            $updates = [];
            foreach (self::WRITABLE_ENV_KEYS as $key) {
                if (array_key_exists($key, $validated)) {
                    $value = $validated[$key];

                    // Normalize booleans to "true"/"false" strings
                    if (is_bool($value)) {
                        $value = $value ? 'true' : 'false';
                    }

                    // Skip null/empty so we don't blank out existing keys
                    // unless the user explicitly submitted an empty string
                    // (in that case, they intend to clear it).
                    if ($value === null) {
                        continue;
                    }

                    $updates[$key] = (string) $value;
                }
            }

            if (empty($updates)) {
                return redirect()->back()
                    ->with('info', 'Nothing to update — no environment values were submitted.');
            }

            // ---- Apply to .env ----
            $this->applyEnvUpdates($updates);

            // ---- Reload config cache so new values take effect ----
            $this->clearConfigCache();

            Log::info('Sanitation .env updated from settings UI', [
                'updated_by' => auth()->id(),
                'keys'       => array_keys($updates),
                // Never log values — log only length fingerprints
                'fingerprints' => array_map(
                    fn ($v) => substr(hash('sha256', (string) $v), 0, 8),
                    $updates
                ),
            ]);

            return redirect()->route('sanitation.settings.index')
                ->with('success', 'Environment settings saved successfully. Config cache cleared.');

        } catch (\Throwable $e) {
            Log::error('Failed to update .env from sanitation settings: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update environment file: ' . $e->getMessage())
                ->withInput();
        }
    }

    // ================================================================ //
    // 🔄 TOGGLE / REMOVE LOGO                                         //
    // ================================================================ //

    public function toggleActive(Request $request)
    {
        $this->authorizeSettingsAccess();

        try {
            $settings = SanitationSetting::first();

            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Settings not found',
                ], 404);
            }

            $settings->is_active  = !$settings->is_active;
            $settings->updated_by = auth()->id();
            $settings->save();

            Log::info('Sanitation settings toggled', [
                'settings_id' => $settings->id,
                'is_active'   => $settings->is_active,
                'updated_by'  => auth()->id(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success'   => true,
                    'is_active' => $settings->is_active,
                    'message'   => $settings->is_active ? 'Settings activated' : 'Settings deactivated',
                ]);
            }

            return redirect()->back()
                ->with('success', $settings->is_active ? 'Settings activated' : 'Settings deactivated');

        } catch (\Exception $e) {
            Log::error('Failed to toggle settings: ' . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to toggle settings: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to toggle settings: ' . $e->getMessage());
        }
    }

    public function removeLogo(Request $request)
    {
        $this->authorizeSettingsAccess();

        try {
            $settings = SanitationSetting::first();

            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Settings not found',
                ], 404);
            }

            if ($settings->company_logo && Storage::disk('public')->exists($settings->company_logo)) {
                Storage::disk('public')->delete($settings->company_logo);
            }

            $settings->company_logo = null;
            $settings->updated_by   = auth()->id();
            $settings->save();

            Log::info('Sanitation logo removed', [
                'settings_id' => $settings->id,
                'removed_by'  => auth()->id(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Logo removed successfully',
                ]);
            }

            return redirect()->back()
                ->with('success', 'Logo removed successfully');

        } catch (\Exception $e) {
            Log::error('Failed to remove logo: ' . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to remove logo: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to remove logo: ' . $e->getMessage());
        }
    }

    // ================================================================ //
    // 📤 EXPORT / IMPORT                                              //
    // ================================================================ //

    public function export()
    {
        $this->authorizeSettingsAccess();

        $settings = SanitationSetting::getSettings();

        return response()->json($settings, 200, [
            'Content-Disposition' => 'attachment; filename="sanitation-settings-' . date('Y-m-d') . '.json"',
        ]);
    }

    public function import(Request $request)
    {
        $this->authorizeSettingsAccess();

        $validator = Validator::make($request->all(), [
            'json_file' => 'required|file|mimes:json|max:5120',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $file    = $request->file('json_file');
            $content = file_get_contents($file->getPathname());
            $data    = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON format: ' . json_last_error_msg());
            }

            $data = array_intersect_key(
                $data,
                array_flip((new SanitationSetting())->getFillable())
            );

            DB::beginTransaction();

            if (array_key_exists('operational_days', $data)) {
                $data['operational_days'] = $this->normalizeWeekdays($data['operational_days']);
            }
            if (array_key_exists('default_collection_days', $data)) {
                $data['default_collection_days'] = $this->normalizeWeekdays($data['default_collection_days']);
            }
            if (array_key_exists('default_collection_frequencies', $data)) {
                $data['default_collection_frequencies'] = $this->normalizeFrequencies($data['default_collection_frequencies']);
            }

            if (array_key_exists('operational_start_time', $data)) {
                $data['operational_start_time'] = $this->normalizeOperationalTime($data['operational_start_time']);
            }
            if (array_key_exists('operational_end_time', $data)) {
                $data['operational_end_time'] = $this->normalizeOperationalTime($data['operational_end_time']);
            }

            $settings = SanitationSetting::firstOrNew();
            $settings->fill($data);
            $settings->updated_by = auth()->id();

            if (!$settings->exists) {
                $settings->created_by = auth()->id();
            }

            $settings->save();

            DB::commit();

            Log::info('Sanitation settings imported', [
                'settings_id' => $settings->id,
                'imported_by' => auth()->id(),
            ]);

            return redirect()->route('sanitation.settings.index')
                ->with('success', 'Settings imported successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to import settings: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to import settings: ' . $e->getMessage());
        }
    }

    // ================================================================ //
    // 🔌 API                                                          //
    // ================================================================ //

    public function apiSettings()
    {
        $this->authorizeSettingsAccess();

        $settings = SanitationSetting::getSettings();

        return response()->json([
            'success'            => true,
            'data'               => $settings,
            'operational_status' => $settings->getOperationalStatusPayload(),
            // ✅ NEW: expose maps readiness without secrets
            'google_maps_status' => $this->getGoogleMapsStatus(),
        ]);
    }

    // ================================================================ //
    // 🔧 PRIVATE HELPERS                                              //
    // ================================================================ //

    /**
     * Validate settings.
     */
    private function validateSettings(Request $request)
    {
        // Custom validation rule for hex color
        Validator::extend('hex_color', function ($attribute, $value, $parameters, $validator) {
            if (empty($value)) {
                return true;
            }
            return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value) === 1;
        }, 'The :attribute must be a valid hex color code (e.g., #10B981 or #1a2b3c).');

        $rules = [
            // Company Details
            'company_name'       => 'required|string|max:255',
            'company_short_name' => 'nullable|string|max:50',
            'company_email'      => 'nullable|email|max:255',
            'company_phone'      => 'nullable|string|max:20',
            'company_address'    => 'nullable|string|max:500',
            'company_logo'       => 'nullable|image|max:2048|mimes:jpeg,png,jpg,gif,svg',

            // Registration Details
            'registration_number' => 'nullable|string|max:100',
            'tax_id'              => 'nullable|string|max:100',
            'license_number'      => 'nullable|string|max:100',

            // Contact Persons
            'contact_person_name'  => 'nullable|string|max:255',
            'contact_person_phone' => 'nullable|string|max:20',
            'contact_person_email' => 'nullable|email|max:255',

            // ✅ Operations Schedule
            'operational_start_time' => 'nullable|date_format:H:i',
            'operational_end_time'   => 'nullable|date_format:H:i|after:operational_start_time',
            'operational_days'       => 'nullable|array',
            'operational_days.*'     => ['string', Rule::in(self::WEEKDAYS)],

            // ✅ Collection Schedule Defaults
            'default_collection_frequencies'   => 'nullable|array',
            'default_collection_frequencies.*' => ['string', Rule::in(self::COLLECTION_FREQUENCIES)],

            'default_collection_days'   => 'nullable|array',
            'default_collection_days.*' => ['string', Rule::in(self::WEEKDAYS)],

            'default_waste_types'   => 'nullable|array',
            'default_waste_types.*' => ['string', Rule::in(['general', 'recyclable', 'organic', 'hazardous', 'bulk'])],

            'frequency_pricing'   => 'nullable|array',
            'frequency_pricing.*' => 'nullable|array',

            // Pricing & Fees
            'default_collection_fee'   => 'nullable|numeric|min:0',
            'emergency_collection_fee' => 'nullable|numeric|min:0',
            'late_fee_percentage'      => 'nullable|numeric|min:0|max:100',

            // Vehicle Fleet
            'default_worker_count_per_vehicle' => 'nullable|integer|min:1|max:10',

            // Reporting
            'default_report_timezone' => 'nullable|string|max:50',
            'date_format'             => 'nullable|string|max:20',
            'time_format'             => 'nullable|string|max:20',

            // Branding
            'primary_color'       => 'nullable|string|hex_color',
            'secondary_color'     => 'nullable|string|hex_color',
            'company_description' => 'nullable|string|max:1000',
            'website_url'         => 'nullable|url|max:255',

            // Social Media
            'facebook_url'  => 'nullable|url|max:255',
            'twitter_url'   => 'nullable|url|max:255',
            'instagram_url' => 'nullable|url|max:255',
            'linkedin_url'  => 'nullable|url|max:255',
        ];

        $messages = [
            'company_name.required'      => 'Company name is required.',
            'company_email.email'        => 'Please enter a valid email address.',
            'company_logo.image'         => 'Logo must be an image file.',
            'company_logo.max'           => 'Logo must not exceed 2MB.',
            'company_logo.mimes'         => 'Logo must be JPEG, PNG, JPG, GIF, or SVG format.',
            'operational_end_time.after' => 'End time must be after start time.',

            'operational_days.array' => 'Operational days must be submitted as a list.',
            'operational_days.*.in'  => 'Each operational day must be a valid weekday name (Monday–Sunday).',

            'default_collection_frequencies.array' => 'Collection frequencies must be submitted as a list.',
            'default_collection_frequencies.*.in'  => 'Each collection frequency must be one of: daily, weekly, biweekly, monthly.',
            'default_collection_days.array'        => 'Collection days must be submitted as a list.',
            'default_collection_days.*.in'         => 'Each collection day must be a valid weekday name (Monday–Sunday).',
            'default_waste_types.*.in'             => 'Each waste type must be one of: general, recyclable, organic, hazardous, bulk.',

            'default_collection_fee.numeric' => 'Collection fee must be a number.',
            'primary_color.hex_color'        => 'Please enter a valid hex color code (e.g., #10B981 or #1a2b3c).',
            'secondary_color.hex_color'      => 'Please enter a valid hex color code (e.g., #059669 or #2d3748).',
            'website_url.url'                => 'Please enter a valid website URL.',
            'facebook_url.url'               => 'Please enter a valid Facebook URL.',
            'twitter_url.url'                => 'Please enter a valid Twitter URL.',
            'instagram_url.url'              => 'Please enter a valid Instagram URL.',
            'linkedin_url.url'               => 'Please enter a valid LinkedIn URL.',
        ];

        return Validator::make($request->all(), $rules, $messages);
    }

    private function handleLogoUpload($file): ?string
    {
        try {
            $filename = 'sanitation_logo_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $path     = $file->storeAs('sanitation/logos', $filename, 'public');
            return $path;
        } catch (\Exception $e) {
            Log::error('Failed to upload logo: ' . $e->getMessage());
            return null;
        }
    }

    private function processJsonField($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            if (strpos($value, ',') !== false) {
                return array_map('trim', explode(',', $value));
            }
            return $value ? [$value] : [];
        }
        return $value ?: [];
    }

    private function normalizeOperationalTime($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)
                ->format(self::OPERATIONAL_TIME_FORMAT);
        } catch (\Throwable $e) {
            Log::warning('Failed to normalize operational time in controller', [
                'value' => $value,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function normalizeWeekdays($value): array
    {
        $input = $this->processJsonField($value);

        if (!is_array($input)) {
            return [];
        }

        $canonical = array_flip(self::WEEKDAYS);

        $valid = [];
        foreach ($input as $day) {
            if (!is_string($day)) {
                continue;
            }
            foreach ($canonical as $weekday => $idx) {
                if (strcasecmp($day, $weekday) === 0) {
                    $valid[$weekday] = true;
                    break;
                }
            }
        }

        return array_keys(array_intersect_key($canonical, $valid));
    }

    private function normalizeFrequencies($value): array
    {
        $input = $this->processJsonField($value);

        if (!is_array($input)) {
            return [];
        }

        $canonical = array_flip(self::COLLECTION_FREQUENCIES);

        $valid = [];
        foreach ($input as $freq) {
            if (!is_string($freq)) {
                continue;
            }
            $lower = strtolower(trim($freq));
            if (isset($canonical[$lower])) {
                $valid[$lower] = true;
            }
        }

        return array_keys(array_intersect_key($canonical, $valid));
    }

    // ================================================================ //
    // ✅ NEW: ENV-FILE WRITING HELPERS                                //
    // ================================================================ //

    /**
     * Safely rewrite `.env` with the given key/value updates.
     *
     * - Existing keys are replaced in place.
     * - Missing keys are appended under a "written by controller" block.
     * - Atomic write via temp file + rename to avoid partial corruption.
     *
     * Never call this with untrusted keys — always gate via
     * `self::WRITABLE_ENV_KEYS`.
     */
    private function applyEnvUpdates(array $updates): void
    {
        if (empty($updates)) {
            return;
        }

        // Reject any keys outside the whitelist
        $allowed = array_flip(self::WRITABLE_ENV_KEYS);
        $updates = array_intersect_key($updates, $allowed);

        if (empty($updates)) {
            return;
        }

        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            throw new \RuntimeException('.env file not found at: ' . $envPath);
        }

        if (!is_writable($envPath)) {
            throw new \RuntimeException(
                '.env file is not writable. Please check file permissions. ' .
                'Run: chmod 664 .env  (or icacls on Windows)'
            );
        }

        $contents = file_get_contents($envPath);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read .env file.');
        }

        $lines = preg_split("/\r\n|\n|\r/", $contents);

        $pendingKeys = array_keys($updates);
        $handledKeys = [];

        // ---- Pass 1: replace existing keys in place ----
        foreach ($lines as $index => $line) {
            $trimmed = ltrim($line);

            // Skip blank lines and comments
            if ($trimmed === '' || strpos($trimmed, '#') === 0) {
                continue;
            }

            foreach ($pendingKeys as $key) {
                if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/', $line)) {
                    $lines[$index] = $key . '=' . $this->formatEnvValue($updates[$key]);
                    $handledKeys[] = $key;
                    break;
                }
            }
        }

        // ---- Pass 2: append missing keys at the end ----
        $missingKeys = array_diff($pendingKeys, $handledKeys);

        if (!empty($missingKeys)) {
            // Ensure there's at least one trailing newline before appending
            $lastLine = end($lines);
            if ($lastLine !== '') {
                $lines[] = '';
            }

            $lines[] = '# ----------------------------------------------------------------';
            $lines[] = '# Auto-added by SanitationSettingController::updateEnvironment';
            $lines[] = '# Date: ' . now()->toDateTimeString();
            $lines[] = '# ----------------------------------------------------------------';

            foreach ($missingKeys as $key) {
                $lines[] = $key . '=' . $this->formatEnvValue($updates[$key]);
            }
        }

        // ---- Atomic write: temp file + rename ----
        $newContents = implode(PHP_EOL, $lines);

        // Preserve a trailing newline
        if (substr($newContents, -1) !== PHP_EOL) {
            $newContents .= PHP_EOL;
        }

        $tmpPath = $envPath . '.tmp.' . Str::random(8);

        if (file_put_contents($tmpPath, $newContents) === false) {
            throw new \RuntimeException('Unable to write temporary .env file.');
        }

        // Try to preserve permissions
        @chmod($tmpPath, fileperms($envPath) ?: 0664);

        if (!@rename($tmpPath, $envPath)) {
            // Windows sometimes refuses to overwrite — fall back to copy + unlink
            if (!@copy($tmpPath, $envPath)) {
                @unlink($tmpPath);
                throw new \RuntimeException('Unable to replace .env file.');
            }
            @unlink($tmpPath);
        }
    }

    /**
     * Format a value for `.env` — quote when necessary, escape inner quotes.
     */
    private function formatEnvValue($value): string
    {
        $value = (string) $value;

        // Booleans and numerics pass through unquoted
        if (in_array(strtolower($value), ['true', 'false', 'null'], true)) {
            return strtolower($value);
        }

        if (is_numeric($value)) {
            return $value;
        }

        // Empty value → empty string (still `KEY=`)
        if ($value === '') {
            return '';
        }

        // Quote when the value contains spaces, `#`, quotes, or special chars
        if ($this->shouldQuoteEnvValue($value)) {
            $escaped = str_replace('"', '\\"', $value);
            return '"' . $escaped . '"';
        }

        return $value;
    }

    private function shouldQuoteEnvValue(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        // Quote if contains whitespace, #, ", ', =, or shell-special chars
        return preg_match('/[\s#"\'=>|&;$`\\\\()]/', $value) === 1;
    }

    /**
     * Clear Laravel config + option caches after writing `.env`.
     *
     * Safe to call when the app is running in any environment.
     */
    private function clearConfigCache(): void
    {
        try {
            Artisan::call('config:clear');
            Artisan::call('cache:clear');

            // If you use route caching in production, drop it too.
            // (Optional — uncomment if you rely on cached routes.)
            // Artisan::call('route:clear');
        } catch (\Throwable $e) {
            // Non-fatal — the user can always run it manually
            Log::warning('Failed to clear config cache after .env update: ' . $e->getMessage());
        }
    }

    /**
     * Return a *masked* status of the Google Maps + Ghana Post GPS
     * configuration, safe to render in the settings index/edit blades.
     *
     * Never returns actual key contents.
     */
    private function getGoogleMapsStatus(): array
    {
        $masked = function (?string $key): ?string {
            if (empty($key)) {
                return null;
            }
            $len = strlen($key);
            if ($len <= 8) {
                return str_repeat('•', $len);
            }
            return substr($key, 0, 4) . str_repeat('•', max(4, $len - 8)) . substr($key, -4);
        };

        return [
            'api_key_set'         => !empty(config('services.google_maps.key')),
            'browser_key_set'     => !empty(config('services.google_maps.browser_key')),
            'server_key_set'      => !empty(config('services.google_maps.server_key')),

            'api_key_preview'     => $masked(config('services.google_maps.key')),
            'browser_key_preview' => $masked(config('services.google_maps.browser_key')),
            'server_key_preview'  => $masked(config('services.google_maps.server_key')),

            'default_center' => [
                'lat' => (float) config('services.google_maps.maps.default_center.lat', 5.6037),
                'lng' => (float) config('services.google_maps.maps.default_center.lng', -0.1870),
            ],
            'default_zoom'    => (int) config('services.google_maps.maps.default_zoom', 14),
            'default_country' => config('services.google_maps.maps.default_country', 'GH'),
            'default_region'  => config('services.google_maps.maps.default_region', 'GH'),

            'ghana_post' => [
                'enabled'     => (bool) config('services.google_maps.geocoding.ghana_post_enabled', false),
                'url'         => config('services.google_maps.geocoding.ghana_post_url'),
                'api_key_set' => !empty(config('services.google_maps.geocoding.ghana_post_api_key')),
                'api_key_preview' => $masked(config('services.google_maps.geocoding.ghana_post_api_key')),
            ],

            'env_writable' => file_exists(base_path('.env')) && is_writable(base_path('.env')),
        ];
    }
}