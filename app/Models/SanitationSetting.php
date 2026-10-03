<?php
// app/Models/SanitationSetting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SanitationSetting extends Model
{
    /**
     * Allowed weekday names — single source of truth.
     */
    public const WEEKDAYS = [
        'Monday', 'Tuesday', 'Wednesday', 'Thursday',
        'Friday', 'Saturday', 'Sunday',
    ];

    /**
     * Allowed collection frequencies.
     */
    public const COLLECTION_FREQUENCIES = [
        'daily', 'weekly', 'biweekly', 'monthly',
    ];

    /**
     * Allowed waste types.
     */
    public const WASTE_TYPES = [
        'general', 'recyclable', 'organic', 'hazardous', 'bulk',
    ];

    /**
     * Canonical human labels for frequencies — used by blades and exports.
     */
    public const FREQUENCY_LABELS = [
        'daily'    => 'Daily',
        'weekly'   => 'Weekly',
        'biweekly' => 'Bi-weekly',
        'monthly'  => 'Monthly',
    ];

    /**
     * Fallback pricing matrix — used when a settings row has no
     * `frequency_pricing` configured. Guarantees the landlord-side
     * agreement modal always has something to display.
     *
     * ⚠️ IMPORTANT: This matrix is a *display-only* safety net. The
     * `emergency` column here is NOT proof that emergency pickup has
     * been enabled — use `hasEmergencyConfigured()` for that question.
     * Use `getConfiguredFrequencyPricing()` if you need the raw values
     * the admin actually typed.
     */
    public const FALLBACK_FREQUENCY_PRICING = [
        'daily'    => ['per_visit' => 30.0, 'per_month' => 650.0, 'emergency' => 90.0],
        'weekly'   => ['per_visit' => 50.0, 'per_month' => 200.0, 'emergency' => 90.0],
        'biweekly' => ['per_visit' => 55.0, 'per_month' => 110.0, 'emergency' => 90.0],
        'monthly'  => ['per_visit' => 60.0, 'per_month' =>  65.0, 'emergency' => 90.0],
    ];

    /**
     * Accepted time formats for operational_start_time / operational_end_time.
     * Anything Carbon::parse() accepts, we normalize to "H:i" internally.
     */
    private const OPERATIONAL_TIME_FORMAT = 'H:i';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sanitation_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        // Company Details
        'company_name',
        'company_short_name',
        'company_email',
        'company_phone',
        'company_address',
        'company_logo',

        // Registration Details
        'registration_number',
        'tax_id',
        'license_number',

        // Contact Persons
        'contact_person_name',
        'contact_person_phone',
        'contact_person_email',

        // Operations
        'operational_start_time',
        'operational_end_time',
        'operational_days',
        'service_areas',

        // Pricing & Fees
        'default_collection_fee',
        'emergency_collection_fee',
        'late_fee_percentage',
        'default_collection_frequencies',
        'default_waste_types',
        'default_collection_days',
        'frequency_pricing',

        // Vehicle Fleet
        'vehicle_types',
        'default_worker_count_per_vehicle',

        // Reporting
        'default_report_timezone',
        'date_format',
        'time_format',

        // Branding
        'primary_color',
        'secondary_color',
        'company_description',
        'website_url',

        // Social Media
        'facebook_url',
        'twitter_url',
        'instagram_url',
        'linkedin_url',

        // Settings
        'notification_preferences',
        'reminder_settings',
        'emergency_contacts',
        'is_active',
        'metadata',
        'operational_status',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * NOTE: `operational_start_time` / `operational_end_time` are NOT
     * cast to `datetime`. They are plain "HH:MM" strings. Casting them
     * to datetime and then re-saving would corrupt the value (Laravel
     * would persist the full date in some drivers). We keep them as
     * strings and parse on demand in getOperationalStatusAttribute().
     *
     * @var array
     */
    protected $casts = [
        // Array/JSON casts
        'operational_days'               => 'array',
        'service_areas'                  => 'array',
        'default_collection_frequencies' => 'array',
        'default_waste_types'            => 'array',
        'default_collection_days'        => 'array',
        'vehicle_types'                  => 'array',
        'notification_preferences'       => 'array',
        'reminder_settings'              => 'array',
        'emergency_contacts'             => 'array',
        'metadata'                       => 'array',
        'frequency_pricing'              => 'array',

        // Scalar casts
        'is_active'                        => 'boolean',
        'default_collection_fee'           => 'float',
        'emergency_collection_fee'         => 'float',
        'late_fee_percentage'              => 'float',
        'default_worker_count_per_vehicle' => 'integer',

        // Timestamps
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ================================================================ //
    // 🔗 RELATIONSHIPS                                                //
    // ================================================================ //

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ================================================================ //
    // 🔍 SCOPES                                                       //
    // ================================================================ //

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOperational($query)
    {
        return $query->active()
            ->whereNotNull('operational_start_time')
            ->whereNotNull('operational_end_time');
    }

    // ================================================================ //
    // 🏭 SINGLETON ACCESSORS                                          //
    // ================================================================ //

    /**
     * Get settings (singleton pattern).
     * Returns a non-persisted instance if none exists.
     */
    public static function getSettings(): self
    {
        return self::first() ?? new self();
    }

    /**
     * Get active settings, or null if none are active.
     */
    public static function getActiveSettings(): ?self
    {
        return self::where('is_active', true)->first();
    }

    /**
     * Get active settings, or the singleton if none are active.
     * Never returns null — used by controllers that just need *something*.
     */
    public static function getResolvedSettings(): self
    {
        return self::getActiveSettings() ?? self::getSettings();
    }

    // ================================================================ //
    // 🖼️ LOGO                                                         //
    // ================================================================ //

    /**
     * Public URL of the uploaded logo, or null if none.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (empty($this->company_logo)) {
            return null;
        }

        try {
            if (Storage::disk('public')->exists($this->company_logo)) {
                return Storage::disk('public')->url($this->company_logo);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get logo URL: ' . $e->getMessage(), [
                'settings_id' => $this->id,
            ]);
        }

        return null;
    }

    // ================================================================ //
    // ⏰ OPERATIONAL STATUS                                           //
    // ================================================================ //

    /**
     * Is the setting row active?
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * ✅ Normalize an operational time string to a plain "H:i" value.
     *
     * Accepts anything Carbon::parse() understands — "08:00", "8:00",
     * "08:00:00", "08:00:00.000000", "8:00 AM", "2025-01-15 08:00:00"
     * — and strips any date / timezone component so the result is always
     * a pure time-of-day.
     *
     * Returns null when the value is empty or unparseable.
     */
    public function normalizeOperationalTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)
                ->format(self::OPERATIONAL_TIME_FORMAT);
        } catch (\Throwable $e) {
            Log::warning('Failed to normalize operational time', [
                'settings_id' => $this->id,
                'value'       => $value,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Derived operational status: 'inactive' | 'open' | 'closed' | 'unknown'.
     * Also considers operational_days if populated.
     *
     * ✅ FIX: Time strings are normalized to "H:i" via normalizeOperationalTime()
     *    BEFORE being applied to $now. This eliminates any date-component or
     *    timezone drift that could make the comparison fail and return 'unknown'
     *    — which was the root cause of the badge flipping to "Unknown" on the
     *    settings index page after the AJAX refresh.
     */
    public function getOperationalStatusAttribute(): string
    {
        // Manual override takes precedence
        if (!empty($this->attributes['operational_status'] ?? null)) {
            return (string) $this->attributes['operational_status'];
        }

        if (!$this->is_active) {
            return 'inactive';
        }

        if (empty($this->operational_start_time) || empty($this->operational_end_time)) {
            return 'unknown';
        }

        $now = now();

        // Respect operational_days if set
        $days = $this->operational_days ?? [];
        if (is_array($days) && !empty($days)) {
            $today = $now->format('l');
            if (!in_array($today, $days, true)) {
                return 'closed';
            }
        }

        try {
            // ✅ Normalize both times to "H:i" first — this is the fix.
            //    We no longer rely on ->hour / ->minute of a raw Carbon
            //    parse, which could carry a date/timezone offset.
            $startNormalized = $this->normalizeOperationalTime($this->operational_start_time);
            $endNormalized   = $this->normalizeOperationalTime($this->operational_end_time);

            if ($startNormalized === null || $endNormalized === null) {
                Log::warning('Unparseable operational time(s)', [
                    'settings_id' => $this->id,
                    'start'       => $this->operational_start_time,
                    'end'         => $this->operational_end_time,
                ]);
                return 'unknown';
            }

            [$startHour, $startMinute] = array_map('intval', explode(':', $startNormalized));
            [$endHour,   $endMinute]   = array_map('intval', explode(':', $endNormalized));

            // ✅ Apply the parsed time-of-day onto TODAY in the app
            //    timezone so the comparison uses the same clock as $now.
            $todayStart = $now->copy()->setTime($startHour, $startMinute, 0);
            $todayEnd   = $now->copy()->setTime($endHour,   $endMinute,   0);

            // Overnight operation (e.g., 22:00 → 06:00)
            if ($todayEnd->lessThanOrEqualTo($todayStart)) {
                return ($now->greaterThanOrEqualTo($todayStart) || $now->lessThanOrEqualTo($todayEnd))
                    ? 'open'
                    : 'closed';
            }

            return $now->between($todayStart, $todayEnd) ? 'open' : 'closed';

        } catch (\Throwable $e) {
            Log::warning('Invalid operational time format', [
                'settings_id' => $this->id,
                'start'       => $this->operational_start_time,
                'end'         => $this->operational_end_time,
                'error'       => $e->getMessage(),
            ]);
            return 'unknown';
        }
    }

    /**
     * Human-readable label for the operational status.
     */
    public function getOperationalStatusLabelAttribute(): string
    {
        return match ($this->operational_status) {
            'open'     => 'Open',
            'closed'   => 'Closed',
            'inactive' => 'Inactive',
            default    => 'Unknown',
        };
    }

    /**
     * Is sanitation currently open?
     */
    public function isOperational(): bool
    {
        return $this->operational_status === 'open';
    }

    /**
     * ✅ NEW — Structured payload for the AJAX status endpoint.
     *
     * Gives the front-end everything it needs to render the badge
     * without having to guess at key names or re-derive the label.
     *
     * Shape:
     *   [
     *     'status'      => 'open'|'closed'|'inactive'|'unknown',
     *     'status_text' => 'Open'|'Closed'|'Inactive'|'Unknown',
     *     'is_open'     => bool,
     *     'is_active'   => bool,
     *     'checked_at'  => ISO-8601 string,
     *   ]
     */
    public function getOperationalStatusPayload(): array
    {
        $status = $this->operational_status;

        return [
            'status'      => $status,
            'status_text' => $this->operational_status_label,
            'is_open'     => $status === 'open',
            'is_active'   => (bool) $this->is_active,
            'checked_at'  => now()->toIso8601String(),
        ];
    }

    // ================================================================ //
    // 🗓️ SCHEDULE HELPERS                                             //
    // ================================================================ //

    /**
     * Effective collection frequencies — falls back to a sensible default.
     */
    public function getEffectiveCollectionFrequencies(): array
    {
        $freqs = $this->default_collection_frequencies ?? [];

        if (!is_array($freqs)) {
            $freqs = [];
        }

        $valid = array_values(array_intersect(
            array_map('strtolower', $freqs),
            self::COLLECTION_FREQUENCIES
        ));

        return !empty($valid) ? $valid : ['weekly'];
    }

    /**
     * Effective default collection days — falls back to operational days.
     */
    public function getEffectiveCollectionDays(): array
    {
        $days = $this->default_collection_days ?? [];

        if (empty($days)) {
            $days = $this->operational_days ?? [];
        }

        if (empty($days)) {
            return self::WEEKDAYS;
        }

        // Preserve canonical Monday→Sunday order
        return array_values(array_filter(
            self::WEEKDAYS,
            fn ($day) => in_array($day, $days, true)
        ));
    }

    /**
     * Is a given weekday an operational day?
     */
    public function operatesOn(string $weekday): bool
    {
        return in_array($weekday, $this->operational_days ?? [], true);
    }

    /**
     * Is a given weekday a default collection day?
     */
    public function collectsOn(string $weekday): bool
    {
        return in_array($weekday, $this->getEffectiveCollectionDays(), true);
    }

    // ================================================================ //
    // 💰 PRICING HELPERS                                              //
    // ================================================================ //

    /**
     * ✅ Is emergency pickup explicitly configured?
     *
     * Returns true ONLY when the admin has actually turned emergency
     * pickup on, via ONE of:
     *   1. `emergency_collection_fee` > 0, OR
     *   2. an explicit `frequency_pricing[$freq]['emergency']` > 0
     *
     * The hard-coded `FALLBACK_FREQUENCY_PRICING` matrix is NOT
     * considered a real configuration — so setting
     * `emergency_collection_fee = 0` and leaving `frequency_pricing`
     * empty correctly turns emergency pickup OFF everywhere.
     */
    public function hasEmergencyConfigured(): bool
    {
        // Tier 1: top-level scalar fee
        if (is_numeric($this->emergency_collection_fee)
            && (float) $this->emergency_collection_fee > 0) {
            return true;
        }

        // Tier 2: any explicit per-frequency emergency value
        $raw = $this->frequency_pricing ?? [];

        if (!is_array($raw)) {
            return false;
        }

        foreach ($raw as $row) {
            if (is_array($row)
                && isset($row['emergency'])
                && is_numeric($row['emergency'])
                && (float) $row['emergency'] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * ✅ The resolved scalar emergency fee (0.0 when unset).
     * Use this for display, not for the "is it configured?" question.
     */
    public function getRawEmergencyFee(): float
    {
        if (is_numeric($this->emergency_collection_fee)
            && (float) $this->emergency_collection_fee > 0) {
            return (float) $this->emergency_collection_fee;
        }

        return 0.0;
    }

    /**
     * ✅ The *raw* per-frequency pricing the admin actually typed,
     * with NO fallback applied. Missing rows are simply omitted.
     *
     * Shape (only includes frequencies that have any config):
     *   [
     *     'weekly' => ['per_visit' => 50.0, 'per_month' => 200.0, 'emergency' => 0.0],
     *     ...
     *   ]
     *
     * Useful for views/JS that need to distinguish "configured as 0"
     * from "not configured at all".
     */
    public function getConfiguredFrequencyPricing(): array
    {
        $raw = $this->frequency_pricing ?? [];

        if (!is_array($raw)) {
            return [];
        }

        $out = [];

        foreach (self::COLLECTION_FREQUENCIES as $frequency) {
            $row = $raw[$frequency] ?? null;

            if (!is_array($row)) {
                continue;
            }

            $out[$frequency] = [
                'per_visit' => isset($row['per_visit']) ? (float) $row['per_visit'] : 0.0,
                'per_month' => isset($row['per_month']) ? (float) $row['per_month'] : 0.0,
                'emergency' => isset($row['emergency']) ? (float) $row['emergency'] : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Full per-frequency pricing matrix with three-tier fallback:
     *
     *   1. `frequency_pricing` if set for that frequency
     *   2. `default_collection_fee` / `emergency_collection_fee`
     *   3. `FALLBACK_FREQUENCY_PRICING` constant
     *
     * ✅ EMERGENCY OPT-OUT: When the admin has NOT configured emergency
     * pickup (see `hasEmergencyConfigured()`), the returned matrix will
     * have `emergency => 0.0` for every frequency, regardless of the
     * hard-coded fallback. This prevents the landlord-side agreement
     * modal from accidentally displaying the fallback `90.0` when the
     * admin has turned emergency pickup off.
     *
     * Pass `$respectEmergencyOptOut = false` if you truly need the
     * raw three-tier fallback (e.g. internal cost-modelling tooling).
     *
     * Always returns a complete matrix keyed by every valid frequency.
     *
     * Shape:
     *   [
     *     'daily'    => ['per_visit' => 30.0, 'per_month' => 650.0, 'emergency' => 90.0],
     *     'weekly'   => ['per_visit' => 50.0, 'per_month' => 200.0, 'emergency' => 90.0],
     *     'biweekly' => ['per_visit' => 55.0, 'per_month' => 110.0, 'emergency' => 90.0],
     *     'monthly'  => ['per_visit' => 60.0, 'per_month' =>  65.0, 'emergency' => 90.0],
     *   ]
     */
    public function getResolvedFrequencyPricing(bool $respectEmergencyOptOut = true): array
    {
        $configured = $this->frequency_pricing ?? [];
        $fallback   = self::FALLBACK_FREQUENCY_PRICING;

        $defaultFee   = (float) ($this->default_collection_fee   ?? 0);
        $emergencyFee = (float) ($this->emergency_collection_fee ?? 0);

        // ✅ Decide once whether emergency is on at all
        $emergencyEnabled = $respectEmergencyOptOut
            ? $this->hasEmergencyConfigured()
            : true;

        $matrix = [];

        foreach (self::COLLECTION_FREQUENCIES as $frequency) {
            $row = is_array($configured[$frequency] ?? null) ? $configured[$frequency] : [];

            $perMonth = (float) ($row['per_month'] ?? 0);
            $perVisit = (float) ($row['per_visit'] ?? 0);
            $emerg    = (float) ($row['emergency'] ?? 0);

            if ($perMonth <= 0) {
                $perMonth = $defaultFee > 0
                    ? $defaultFee
                    : ($fallback[$frequency]['per_month'] ?? 0);
            }
            if ($perVisit <= 0) {
                $perVisit = $fallback[$frequency]['per_visit'] ?? $perMonth;
            }

            // ✅ Emergency resolution — gated by the opt-out flag
            if (!$emergencyEnabled) {
                $emerg = 0.0;
            } elseif ($emerg <= 0) {
                $emerg = $emergencyFee > 0
                    ? $emergencyFee
                    : ($fallback[$frequency]['emergency'] ?? 0);
            }

            $matrix[$frequency] = [
                'per_visit' => round($perVisit, 2),
                'per_month' => round($perMonth, 2),
                'emergency' => round($emerg, 2),
            ];
        }

        return $matrix;
    }

    /**
     * Pricing row for one frequency, or null if the frequency is invalid.
     * Uses the same three-tier fallback as getResolvedFrequencyPricing().
     */
    public function getFrequencyPricing(string $frequency): ?array
    {
        if (!in_array($frequency, self::COLLECTION_FREQUENCIES, true)) {
            return null;
        }

        return $this->getResolvedFrequencyPricing()[$frequency] ?? null;
    }

    /**
     * Convenience: monthly fee for one frequency (falls back).
     */
    public function getMonthlyFeeFor(string $frequency): ?float
    {
        return $this->getFrequencyPricing($frequency)['per_month'] ?? null;
    }

    /**
     * Convenience: emergency fee for one frequency.
     * Returns 0.0 when emergency is not configured.
     */
    public function getEmergencyFeeFor(string $frequency): ?float
    {
        return $this->getFrequencyPricing($frequency)['emergency'] ?? null;
    }

    /**
     * Convenience: human label for a frequency (e.g. "Weekly").
     */
    public static function getFrequencyLabel(string $frequency): string
    {
        return self::FREQUENCY_LABELS[$frequency] ?? ucfirst($frequency);
    }

    /**
     * Is frequency pricing explicitly configured for a given frequency?
     * (Useful for UI: "custom price" vs "uses default".)
     */
    public function hasConfiguredPriceFor(string $frequency): bool
    {
        $row = $this->frequency_pricing[$frequency] ?? null;

        return is_array($row) && !empty($row['per_month']);
    }

    // ================================================================ //
    // 🔧 BOOT                                                         //
    // ================================================================ //

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
            if (empty($model->updated_by)) {
                $model->updated_by = auth()->id();
            }
            if (!isset($model->is_active)) {
                $model->is_active = true;
            }
        });

        static::updating(function ($model) {
            if (empty($model->updated_by)) {
                $model->updated_by = auth()->id();
            }
        });
    }
}