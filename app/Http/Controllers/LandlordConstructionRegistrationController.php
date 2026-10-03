<?php

namespace App\Http\Controllers;

use App\Models\LandlordConstructionRegistration;
use App\Models\User;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\RegistrationPlan;
use App\Models\RegistrationTenant;
use App\Models\TenantInvitation;
use App\Models\SystemSetting;
use App\Services\MultiChannelInvitationService;
use App\Http\Controllers\PropertyLandlordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Notifications\ConstructionRegistrationSubmitted;
use App\Notifications\PropertyRegistrationSubmitted;
use App\Notifications\RegistrationUpdated;
use App\Notifications\RegistrationCancelled;

class LandlordConstructionRegistrationController extends Controller
{
    protected $multiChannelInvitationService;
    protected $propertyLandlordController;

    // Registration types
    const TYPE_CONSTRUCTION = 'construction';
    const TYPE_PROPERTY_CAPTURE = 'property_capture';
    
    // Registration purposes
    const PURPOSE_CONSTRUCTION = 'construction';
    const PURPOSE_PERMANENT_REGISTRATION = 'permanent_registration';
    const PURPOSE_BOTH = 'both';

    public function __construct(
        MultiChannelInvitationService $multiChannelInvitationService,
        PropertyLandlordController $propertyLandlordController
    ) {
        $this->multiChannelInvitationService = $multiChannelInvitationService;
        $this->propertyLandlordController = $propertyLandlordController;
    }

    /**
     * ✅ UPDATED: Show the registration form with registration control check
     */
    public function create(Request $request)
    {
        // Check if registration is allowed
        $settings = SystemSetting::getSettings();
        
        if (!$settings->isRegistrationAllowed()) {
            $message = $settings->getRegistrationDisabledMessage();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 403);
            }
            
            return redirect()->route('home')
                ->with('error', $message);
        }
        
        $type = $request->get('type', self::TYPE_CONSTRUCTION);
        $plotNumber = $request->get('plot_number');
        $propertyName = $request->get('property_name');
        
        return view('landlord.construction.register', compact('type', 'plotNumber', 'propertyName'));
    }

    /**
 * ✅ ENHANCED: Store a new registration with duplicate prevention
 * ✅ FIXED: Properly handles include_construction flag for vacant land with construction details
 */
public function store(Request $request)
{
    // Check if registration is allowed
    $settings = SystemSetting::getSettings();
    
    if (!$settings->isRegistrationAllowed()) {
        $message = $settings->getRegistrationDisabledMessage();
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message
            ], 403);
        }
        
        return redirect()->back()
            ->with('error', $message)
            ->withInput();
    }
    
    // ============================================
    // ⭐ FIXED: Determine registration type and purpose
    // ============================================
    $registrationType = $request->registration_type ?? self::TYPE_CONSTRUCTION;
    $purpose = $request->purpose ?? self::PURPOSE_CONSTRUCTION;
    
    // ⭐ FIXED: Check if construction details are included (for vacant land)
    $includeConstruction = $request->has('include_construction') ? filter_var($request->include_construction, FILTER_VALIDATE_BOOLEAN) : false;
    
    // ⭐ FIXED: If purpose is 'both' but include_construction is not set, set it
    if ($purpose === self::PURPOSE_BOTH && !$includeConstruction) {
        $includeConstruction = true;
        Log::info('Setting include_construction to true because purpose is both', [
            'purpose' => $purpose
        ]);
    }
    
    // ⭐ FIXED: If include_construction is true but purpose is not 'both', update purpose
    if ($includeConstruction && $purpose !== self::PURPOSE_BOTH) {
        $purpose = self::PURPOSE_BOTH;
        Log::info('Updating purpose to both because include_construction is true', [
            'include_construction' => $includeConstruction
        ]);
    }
    
    // Log the determined values for debugging
    Log::info('Registration determination', [
        'registration_type' => $registrationType,
        'purpose' => $purpose,
        'include_construction' => $includeConstruction,
        'has_construction_checkbox' => $request->has('provide_construction_details'),
        'construction_checkbox_value' => $request->input('provide_construction_details', 'not_set'),
    ]);
    
    // Validate based on type and construction inclusion
    $validator = $this->validateRegistration($request, $registrationType, $purpose, $includeConstruction);
    
    if ($validator->fails()) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        return redirect()->back()
            ->withErrors($validator)
            ->withInput();
    }

    // ========== DUPLICATE PREVENTION CHECKS ==========
    
    // Check for session-based duplicate prevention
    $sessionCheck = $this->checkSessionDuplicate($request);
    if ($sessionCheck['blocked']) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $sessionCheck['message'],
                'wait_time' => $sessionCheck['wait_time']
            ], 429);
        }
        
        return redirect()->back()
            ->with('error', $sessionCheck['message'])
            ->withInput();
    }
    
    // Check for duplicate submission
    $duplicateCheck = $this->checkForDuplicate($request);
    
    if ($duplicateCheck['is_duplicate']) {
        $existingRegistration = $duplicateCheck['registration'];
        $matchMethod = $duplicateCheck['match_method'];
        
        // Handle based on registration status
        $response = $this->handleDuplicateRegistration($existingRegistration, $request);
        
        if ($response) {
            return $response;
        }
    }

    DB::beginTransaction();

    try {
        // Generate unique access token for status checking
        $accessToken = Str::random(32);
        
        // Generate submission hash for duplicate detection
        $submissionHash = $this->generateSubmissionHash($request);
        
        // Process phone numbers
        $allPhones = $this->processPhoneNumbers($request);
        
        // ========== PROCESS TENANT DATA ==========
        $tenantData = [];
        $hasTenants = $request->has_tenants === 'yes';
        $tenantCount = 0;
        
        // Check for JSON tenant data (from the blade's tenant_data_json field)
        if ($request->has('tenant_data_json') && !empty($request->tenant_data_json)) {
            $tenantData = json_decode($request->tenant_data_json, true) ?? [];
            $tenantCount = count($tenantData);
            Log::info('Tenant data from JSON:', ['count' => $tenantCount, 'data' => $tenantData]);
        }
        // Check for individual tenant fields (backward compatibility)
        else if ($request->has('tenant_count') && $request->tenant_count > 0) {
            $tenantCount = (int) $request->tenant_count;
            for ($i = 0; $i < $tenantCount; $i++) {
                if ($request->has("tenant_name_$i") && $request->has("tenant_phone_$i")) {
                    $tenantData[] = [
                        'name' => $request->input("tenant_name_$i"),
                        'phone' => $request->input("tenant_phone_$i"),
                        'email' => $request->input("tenant_email_$i"),
                        'notes' => $request->input("tenant_notes_$i"),
                    ];
                }
            }
            Log::info('Tenant data from individual fields:', ['count' => count($tenantData)]);
        }
        
        // Prepare registration data
        $registrationData = [
            // Registration Type & Purpose
            'registration_type' => $registrationType,
            'purpose' => $purpose,
            
            // Landlord Information
            'name' => $request->name,
            'email' => $request->email,
            'primary_phone' => $allPhones[0] ?? $request->primary_phone,
            'additional_phones' => count($allPhones) > 1 ? array_slice($allPhones, 1) : null,
            
            // Land/Plot Information
            'property_name' => $request->property_name,
            'plot_number' => $request->plot_number,
            'street_name' => $request->street_name,
            'digital_address' => $request->digital_address,
            'land_description' => $request->land_description,
            'land_ownership_document' => $this->handleFileUpload($request, 'land_ownership_document'),
            
            // Zone and Section - Will be filled by admin during approval
            'zone' => null,
            'section' => null,
            
            // Tenant Information - Summary fields
            'has_tenants' => $hasTenants,
            'tenant_count' => $hasTenants ? $tenantCount : 0,
            'tenant_data' => $hasTenants ? $tenantData : null,
            
            // ⭐ FIXED: Track if construction details were included
            'has_construction_details' => $includeConstruction,
            
            // Status & Token
            'status' => LandlordConstructionRegistration::STATUS_PENDING,
            'access_token' => $accessToken,
            'submitted_at' => now(),
            
            // Duplicate prevention fields
            'submission_hash' => $submissionHash,
            'duplicate_check_at' => now(),
        ];

        // ============================================
        // ⭐ FIXED: Add construction-specific fields
        // ============================================
        // Check if we should add construction details:
        // 1. Registration type is construction AND include_construction is true
        // 2. OR purpose is 'both' (which implies construction details are included)
        // 3. OR purpose is 'construction' and include_construction is true
        $shouldAddConstruction = ($registrationType === self::TYPE_CONSTRUCTION || $purpose === self::PURPOSE_CONSTRUCTION) && $includeConstruction;
        
        // Also add construction if purpose is 'both' regardless of include_construction flag
        if ($purpose === self::PURPOSE_BOTH) {
            $shouldAddConstruction = true;
        }
        
        Log::info('Construction details decision', [
            'shouldAddConstruction' => $shouldAddConstruction,
            'registrationType' => $registrationType,
            'purpose' => $purpose,
            'includeConstruction' => $includeConstruction,
        ]);

        if ($shouldAddConstruction) {
            $registrationData = array_merge($registrationData, [
                'property_type' => $request->property_type,
                'custom_property_type' => $request->property_type === 'other' ? $request->custom_property_type : null,
                'property_status' => $request->property_status ?? 'under_construction',
                'estimated_bedrooms' => $request->estimated_bedrooms,
                'has_plans' => $request->has_plans === 'yes',
                'estimated_completion' => $request->estimated_completion,
                'construction_documents' => $this->handleMultipleFileUpload($request, 'construction_documents'),
            ]);
            
            Log::info('Added construction details to registration', [
                'property_type' => $request->property_type,
                'property_status' => $request->property_status ?? 'under_construction',
                'has_plans' => $request->has_plans === 'yes',
            ]);
        } else {
            // If construction details not provided, set default values or null
            $registrationData = array_merge($registrationData, [
                'property_type' => null,
                'custom_property_type' => null,
                'property_status' => 'vacant',
                'estimated_bedrooms' => null,
                'has_plans' => false,
                'estimated_completion' => null,
                'construction_documents' => null,
            ]);
            
            Log::info('No construction details added - setting to vacant', [
                'registration_id' => $registrationData['property_name'] ?? 'unknown',
            ]);
        }

        // Add property capture-specific fields if applicable
        if ($registrationType === self::TYPE_PROPERTY_CAPTURE || $purpose === self::PURPOSE_PERMANENT_REGISTRATION) {
            $registrationData = array_merge($registrationData, [
                'existing_property_type' => $request->existing_property_type,
                'existing_custom_property_type' => $request->existing_property_type === 'other' ? $request->existing_custom_property_type : null,
                'existing_property_status' => $request->existing_property_status,
                'existing_bedrooms' => $request->existing_bedrooms,
                'existing_bathrooms' => $request->existing_bathrooms,
                'year_built' => $request->year_built,
                'property_photos' => $this->handleMultipleFileUpload($request, 'property_photos'),
                'property_documents' => $this->handleMultipleFileUpload($request, 'property_documents'),
            ]);
        }

        // Create the registration record
        $registration = LandlordConstructionRegistration::create($registrationData);

        // ========== SAVE INDIVIDUAL TENANT RECORDS ==========
        if ($hasTenants && !empty($tenantData)) {
            foreach ($tenantData as $tenant) {
                $registration->tenants()->create([
                    'name' => $tenant['name'],
                    'phone' => $tenant['phone'],
                    'email' => $tenant['email'] ?? null,
                    'notes' => $tenant['notes'] ?? null,
                    'status' => 'pending', // All tenants start as pending
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            Log::info('Tenant records created', [
                'registration_id' => $registration->id,
                'tenant_count' => count($tenantData)
            ]);
        }

        // Try to find or create landlord user
        try {
            $landlordId = $this->propertyLandlordController->findOrCreateLandlordByPhone(
                $allPhones[0] ?? $request->primary_phone,
                $request->name,
                $request->email,
                $allPhones
            );
            
            if ($landlordId) {
                $registration->landlord_id = $landlordId;
                $registration->save();
            }
        } catch (\Exception $e) {
            Log::warning('Could not create landlord user during registration: ' . $e->getMessage());
        }

        DB::commit();

        // Update session with last submission time
        session(['last_registration_submission' => now()]);
        session(['last_submission_hash' => $submissionHash]);

        // ✅ FIXED: Manually log activity AFTER commit to prevent foreign key constraint violation
        try {
            $registration->logActivity('created', 'Registration created');
        } catch (\Exception $e) {
            // Just log the error but don't fail the request
            Log::warning('Could not log creation activity: ' . $e->getMessage(), [
                'registration_id' => $registration->id
            ]);
        }

        // Notify admins
        $this->notifyAdmins($registration);

        Log::info('New registration submitted', [
            'registration_id' => $registration->id,
            'type' => $registrationType,
            'purpose' => $purpose,
            'include_construction' => $includeConstruction,
            'has_construction_details' => $registration->has_construction_details,
            'has_tenants' => $hasTenants,
            'tenant_count' => $tenantCount,
            'name' => $request->name,
            'submission_hash' => $submissionHash
        ]);

        // Return success response
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $this->getSuccessMessage($registrationType, $purpose, $includeConstruction),
                'registration_id' => $registration->id,
                'access_token' => $accessToken,
                'tenant_count' => $tenantCount,
                'has_construction_details' => $registration->has_construction_details,
            ], 201);
        }

        return redirect()->route($this->getRedirectRoute($registrationType))
            ->with('success', $this->getSuccessMessage($registrationType, $purpose, $includeConstruction))
            ->with('registration_success', true)
            ->with('access_token', $accessToken);

    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Failed to submit registration: ' . $e->getMessage(), [
            'request' => $request->except(['password', 'password_confirmation']),
            'trace' => $e->getTraceAsString()
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit registration. Please try again or contact support.'
            ], 500);
        }

        return redirect()->back()
            ->with('error', 'Failed to submit registration. Please try again or contact support.')
            ->withInput();
    }
}

    /**
     * Check for duplicate registration
     */
    private function checkForDuplicate(Request $request): array
    {
        $primaryPhone = $request->primary_phone;
        $plotNumber = $request->plot_number;
        $propertyName = $request->property_name;
        $registrationType = $request->registration_type ?? self::TYPE_CONSTRUCTION;
        
        // Generate submission hash
        $submissionHash = $this->generateSubmissionHash($request);
        
        // Check by submission hash first (most accurate)
        $existingByHash = LandlordConstructionRegistration::where('submission_hash', $submissionHash)
            ->whereIn('status', [
                LandlordConstructionRegistration::STATUS_PENDING,
                LandlordConstructionRegistration::STATUS_APPROVED,
                LandlordConstructionRegistration::STATUS_IN_REVIEW,
                LandlordConstructionRegistration::STATUS_NEEDS_INFO
            ])
            ->first();
        
        if ($existingByHash) {
            return [
                'is_duplicate' => true,
                'registration' => $existingByHash,
                'match_method' => 'submission_hash'
            ];
        }
        
        // Check by key fields (phone + plot + property name)
        $existingByFields = LandlordConstructionRegistration::where('primary_phone', $primaryPhone)
            ->where('plot_number', $plotNumber)
            ->where('property_name', $propertyName)
            ->where('registration_type', $registrationType)
            ->whereIn('status', [
                LandlordConstructionRegistration::STATUS_PENDING,
                LandlordConstructionRegistration::STATUS_APPROVED,
                LandlordConstructionRegistration::STATUS_IN_REVIEW,
                LandlordConstructionRegistration::STATUS_NEEDS_INFO
            ])
            ->first();
        
        if ($existingByFields) {
            return [
                'is_duplicate' => true,
                'registration' => $existingByFields,
                'match_method' => 'key_fields'
            ];
        }
        
        // Check for rejected/cancelled within the last 24 hours
        $recentRejected = LandlordConstructionRegistration::where('primary_phone', $primaryPhone)
            ->where('plot_number', $plotNumber)
            ->where('property_name', $propertyName)
            ->whereIn('status', [
                LandlordConstructionRegistration::STATUS_REJECTED,
                LandlordConstructionRegistration::STATUS_CANCELLED
            ])
            ->where('updated_at', '>=', now()->subHours(24))
            ->first();
        
        if ($recentRejected) {
            return [
                'is_duplicate' => true,
                'registration' => $recentRejected,
                'match_method' => 'recent_rejected',
                'time_since' => $recentRejected->updated_at->diffInHours(now())
            ];
        }
        
        // Similar content check (using text similarity for description fields)
        $similarContent = $this->checkSimilarContent($request);
        
        if ($similarContent) {
            return [
                'is_duplicate' => true,
                'registration' => $similarContent,
                'match_method' => 'similar_content'
            ];
        }
        
        return [
            'is_duplicate' => false,
            'registration' => null,
            'match_method' => null
        ];
    }

    /**
     * Handle duplicate registration based on status
     */
    private function handleDuplicateRegistration($existingRegistration, Request $request)
    {
        $status = $existingRegistration->status;
        
        // If registration is pending or needs info, prevent resubmission
        if (in_array($status, [
            LandlordConstructionRegistration::STATUS_PENDING,
            LandlordConstructionRegistration::STATUS_NEEDS_INFO
        ])) {
            $message = 'You already have a pending registration for this property. Please check your status or contact support.';
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'is_duplicate' => true,
                    'existing_registration_id' => $existingRegistration->id,
                    'status' => $status,
                    'can_resubmit' => false
                ], 409);
            }
            
            return redirect()->back()
                ->with('error', $message)
                ->with('duplicate_registration_id', $existingRegistration->id)
                ->withInput();
        }
        
        // If registration was approved, prevent resubmission
        if ($status === LandlordConstructionRegistration::STATUS_APPROVED) {
            $message = 'This property has already been approved. Please contact support if you need to make changes.';
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'is_duplicate' => true,
                    'existing_registration_id' => $existingRegistration->id,
                    'status' => $status
                ], 409);
            }
            
            return redirect()->back()
                ->with('error', $message)
                ->withInput();
        }
        
        // If registration is in review, prevent resubmission
        if ($status === LandlordConstructionRegistration::STATUS_IN_REVIEW) {
            $message = 'Your registration is currently under review. Please wait for the review to complete.';
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'is_duplicate' => true,
                    'existing_registration_id' => $existingRegistration->id,
                    'status' => $status
                ], 409);
            }
            
            return redirect()->back()
                ->with('error', $message)
                ->withInput();
        }
        
        // For rejected or cancelled registrations, allow re-submission with warning
        if (in_array($status, [
            LandlordConstructionRegistration::STATUS_REJECTED,
            LandlordConstructionRegistration::STATUS_CANCELLED
        ])) {
            // Check if enough time has passed since rejection/cancellation
            $timeSince = $existingRegistration->updated_at->diffInHours(now());
            
            if ($timeSince < 24) {
                $message = "You had a {$status} registration for this property within the last 24 hours. Please wait before resubmitting.";
                
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                        'is_duplicate' => true,
                        'existing_registration_id' => $existingRegistration->id,
                        'status' => $status,
                        'hours_since' => $timeSince,
                        'can_resubmit_after' => 24 - $timeSince
                    ], 409);
                }
                
                return redirect()->back()
                    ->with('error', $message)
                    ->withInput();
            }
            
            // Allow resubmission with a warning
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'warning' => true,
                    'message' => "You had a previous {$status} registration. You can resubmit, but please ensure you've addressed the issues.",
                    'existing_registration_id' => $existingRegistration->id,
                    'status' => $status
                ]);
            }
            
            // Flash warning message but allow submission
            session()->flash('warning', "You had a previous {$status} registration. Please ensure you've addressed the issues before resubmitting.");
            return null; // Allow submission to proceed
        }
        
        return null; // Allow submission for other statuses
    }

    /**
     * Check session-based duplicate prevention
     */
    private function checkSessionDuplicate(Request $request): array
    {
        $lastSubmission = session('last_registration_submission', null);
        $lastHash = session('last_submission_hash', null);
        $currentHash = $this->generateSubmissionHash($request);
        
        // Check if same user submitted the exact same data recently
        if ($lastSubmission && $lastHash === $currentHash) {
            $secondsSince = now()->diffInSeconds($lastSubmission);
            
            if ($secondsSince < 60) {
                $waitTime = 60 - $secondsSince;
                return [
                    'blocked' => true,
                    'message' => "You have already submitted this registration. Please wait {$waitTime} seconds before trying again.",
                    'wait_time' => $waitTime
                ];
            }
        }
        
        return [
            'blocked' => false,
            'message' => null,
            'wait_time' => null
        ];
    }

    /**
     * Generate a unique hash for the submission
     */
    private function generateSubmissionHash(Request $request): string
    {
        // Normalize phone number for consistent hashing
        $primaryPhone = $this->normalizePhoneNumber($request->primary_phone);
        
        // Remove extra whitespace and normalize other fields
        $plotNumber = trim(strtoupper($request->plot_number));
        $propertyName = trim($request->property_name);
        $streetName = trim($request->street_name);
        $email = trim(strtolower($request->email ?? ''));
        $registrationType = $request->registration_type ?? self::TYPE_CONSTRUCTION;
        
        // Create a normalized string for hashing
        $hashString = implode('|', [
            $primaryPhone,
            $plotNumber,
            $propertyName,
            $streetName,
            $email,
            $registrationType
        ]);
        
        // Generate SHA-256 hash
        return hash('sha256', $hashString);
    }

    /**
     * Normalize phone number for consistent comparison
     */
    private function normalizePhoneNumber($phone): string
    {
        if (empty($phone)) {
            return '';
        }
        
        // Remove all non-numeric characters except +
        $normalized = preg_replace('/[^0-9+]/', '', trim($phone));
        
        // Remove leading zeros and ensure consistent format
        if (strpos($normalized, '0') === 0) {
            $normalized = '233' . substr($normalized, 1);
        } elseif (strpos($normalized, '+') === 0) {
            $normalized = substr($normalized, 1);
        }
        
        return $normalized;
    }

    /**
     * Check for similar content (description fields)
     */
    private function checkSimilarContent(Request $request): ?LandlordConstructionRegistration
    {
        // Only check if land description is provided
        if (empty($request->land_description)) {
            return null;
        }
        
        $description = trim($request->land_description);
        $descriptionWords = explode(' ', $description);
        $wordCount = count($descriptionWords);
        
        // Only check descriptions with at least 5 words
        if ($wordCount < 5) {
            return null;
        }
        
        // Get all registrations with similar plot and property name
        $potentialDuplicates = LandlordConstructionRegistration::where('plot_number', $request->plot_number)
            ->where('property_name', $request->property_name)
            ->whereIn('status', [
                LandlordConstructionRegistration::STATUS_PENDING,
                LandlordConstructionRegistration::STATUS_APPROVED,
                LandlordConstructionRegistration::STATUS_IN_REVIEW,
                LandlordConstructionRegistration::STATUS_NEEDS_INFO
            ])
            ->get();
        
        foreach ($potentialDuplicates as $existing) {
            if (empty($existing->land_description)) {
                continue;
            }
            
            $existingDescription = trim($existing->land_description);
            $existingWords = explode(' ', $existingDescription);
            
            // Calculate similarity using common words
            $commonWords = array_intersect(
                array_map('strtolower', $descriptionWords),
                array_map('strtolower', $existingWords)
            );
            
            $commonCount = count($commonWords);
            $totalWords = max($wordCount, count($existingWords));
            
            // If more than 70% of words match, consider it a duplicate
            if ($totalWords > 0 && ($commonCount / $totalWords) >= 0.7) {
                return $existing;
            }
            
            // Also check using Levenshtein distance for shorter descriptions
            if ($wordCount < 20 && $this->levenshteinSimilarity($description, $existingDescription) >= 0.8) {
                return $existing;
            }
        }
        
        return null;
    }

    /**
     * Calculate Levenshtein similarity between two strings
     */
    private function levenshteinSimilarity($str1, $str2): float
    {
        if (empty($str1) || empty($str2)) {
            return 0;
        }
        
        $distance = levenshtein($str1, $str2);
        $maxLength = max(strlen($str1), strlen($str2));
        
        if ($maxLength === 0) {
            return 0;
        }
        
        return 1 - ($distance / $maxLength);
    }

    /**
     * Check for duplicate using existing registration ID
     */
    public function checkDuplicateById($registrationId, Request $request)
    {
        try {
            $registration = LandlordConstructionRegistration::findOrFail($registrationId);
            
            // Check if we have a new submission with similar details
            $duplicateCheck = $this->checkForDuplicate($request);
            
            return response()->json([
                'success' => true,
                'is_duplicate' => $duplicateCheck['is_duplicate'],
                'existing_registration' => $duplicateCheck['registration'] ? [
                    'id' => $duplicateCheck['registration']->id,
                    'status' => $duplicateCheck['registration']->status,
                    'submitted_at' => $duplicateCheck['registration']->submitted_at?->format('Y-m-d H:i:s'),
                    'property_name' => $duplicateCheck['registration']->property_name,
                    'plot_number' => $duplicateCheck['registration']->plot_number,
                ] : null,
                'match_method' => $duplicateCheck['match_method']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to check duplicate: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check for duplicates'
            ], 500);
        }
    }

    /**
     * API endpoint to check for duplicates before submission
     */
    public function checkDuplicate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'primary_phone' => 'required|string',
                'plot_number' => 'required|string',
                'property_name' => 'required|string',
                'registration_type' => 'nullable|string',
                'land_description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $duplicateCheck = $this->checkForDuplicate($request);
            
            $response = [
                'success' => true,
                'is_duplicate' => $duplicateCheck['is_duplicate'],
                'match_method' => $duplicateCheck['match_method']
            ];

            if ($duplicateCheck['is_duplicate']) {
                $registration = $duplicateCheck['registration'];
                $response['existing_registration'] = [
                    'id' => $registration->id,
                    'status' => $registration->status,
                    'status_label' => $registration->getStatusLabel(),
                    'submitted_at' => $registration->submitted_at?->format('Y-m-d H:i:s'),
                    'property_name' => $registration->property_name,
                    'plot_number' => $registration->plot_number,
                    'can_resubmit' => in_array($registration->status, [
                        LandlordConstructionRegistration::STATUS_REJECTED,
                        LandlordConstructionRegistration::STATUS_CANCELLED
                    ])
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Failed to check duplicate: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check for duplicates'
            ], 500);
        }
    }

    /**
     * Admin: View potential duplicates
     */
    public function viewDuplicates()
    {
        $registrations = LandlordConstructionRegistration::whereIn('status', [
            LandlordConstructionRegistration::STATUS_PENDING,
            LandlordConstructionRegistration::STATUS_IN_REVIEW
        ])
        ->orderBy('created_at', 'desc')
        ->get();
        
        $duplicates = [];
        
        foreach ($registrations as $registration) {
            // Check for duplicates
            $potentialDuplicates = LandlordConstructionRegistration::where('id', '!=', $registration->id)
                ->where('plot_number', $registration->plot_number)
                ->where('property_name', $registration->property_name)
                ->whereIn('status', [
                    LandlordConstructionRegistration::STATUS_PENDING,
                    LandlordConstructionRegistration::STATUS_APPROVED,
                    LandlordConstructionRegistration::STATUS_IN_REVIEW
                ])
                ->get();
            
            if ($potentialDuplicates->isNotEmpty()) {
                $duplicates[$registration->id] = [
                    'registration' => $registration,
                    'duplicates' => $potentialDuplicates
                ];
            }
        }
        
        return view('admin.registrations.duplicates', compact('duplicates'));
    }

    /**
     * Admin: Merge duplicate registrations
     */
    public function mergeDuplicates(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'primary_id' => 'required|exists:landlord_construction_registrations,id',
            'duplicate_ids' => 'required|array',
            'duplicate_ids.*' => 'exists:landlord_construction_registrations,id',
            'keep_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator);
        }

        DB::beginTransaction();

        try {
            $primary = LandlordConstructionRegistration::findOrFail($request->primary_id);
            $duplicateIds = $request->duplicate_ids;
            $keepData = $request->keep_data ?? [];

            foreach ($duplicateIds as $duplicateId) {
                $duplicate = LandlordConstructionRegistration::find($duplicateId);
                
                if (!$duplicate || $duplicate->id == $primary->id) {
                    continue;
                }

                // Merge tenant data
                if ($duplicate->tenant_data && $primary->tenant_data) {
                    $mergedTenants = array_merge($primary->tenant_data, $duplicate->tenant_data);
                    $primary->tenant_data = $mergedTenants;
                    $primary->tenant_count = count($mergedTenants);
                } elseif ($duplicate->tenant_data && !$primary->tenant_data) {
                    $primary->tenant_data = $duplicate->tenant_data;
                    $primary->tenant_count = $duplicate->tenant_count;
                }

                // Merge other fields if specified
                if (in_array('documents', $keepData)) {
                    // Merge construction documents
                    if ($duplicate->construction_documents && $primary->construction_documents) {
                        $primary->construction_documents = array_merge(
                            $primary->construction_documents,
                            $duplicate->construction_documents
                        );
                    } elseif ($duplicate->construction_documents) {
                        $primary->construction_documents = $duplicate->construction_documents;
                    }
                    
                    // Merge property photos
                    if ($duplicate->property_photos && $primary->property_photos) {
                        $primary->property_photos = array_merge(
                            $primary->property_photos,
                            $duplicate->property_photos
                        );
                    } elseif ($duplicate->property_photos) {
                        $primary->property_photos = $duplicate->property_photos;
                    }
                    
                    // Merge property documents
                    if ($duplicate->property_documents && $primary->property_documents) {
                        $primary->property_documents = array_merge(
                            $primary->property_documents,
                            $duplicate->property_documents
                        );
                    } elseif ($duplicate->property_documents) {
                        $primary->property_documents = $duplicate->property_documents;
                    }
                }

                // Merge landlord info
                if (in_array('landlord_info', $keepData)) {
                    if ($duplicate->additional_phones && $primary->additional_phones) {
                        $primary->additional_phones = array_merge(
                            $primary->additional_phones,
                            $duplicate->additional_phones
                        );
                    } elseif ($duplicate->additional_phones) {
                        $primary->additional_phones = $duplicate->additional_phones;
                    }
                    
                    if ($duplicate->email && !$primary->email) {
                        $primary->email = $duplicate->email;
                    }
                }

                // Merge property details
                if (in_array('property_details', $keepData)) {
                    if ($duplicate->land_description && !$primary->land_description) {
                        $primary->land_description = $duplicate->land_description;
                    }
                    if ($duplicate->digital_address && !$primary->digital_address) {
                        $primary->digital_address = $duplicate->digital_address;
                    }
                }

                // Save the primary registration
                $primary->save();

                // Move tenants to primary
                $duplicate->tenants()->update(['registration_id' => $primary->id]);

                // Delete the duplicate
                $duplicate->delete();

                Log::info('Registrations merged', [
                    'primary_id' => $primary->id,
                    'duplicate_id' => $duplicateId,
                    'merged_by' => auth()->id()
                ]);
            }

            DB::commit();

            $message = "Successfully merged " . count($duplicateIds) . " duplicate registrations.";

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'primary_registration' => $primary->id
                ]);
            }

            return redirect()->route('admin.registrations.show', $primary)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to merge duplicates: ' . $e->getMessage(), [
                'primary_id' => $request->primary_id,
                'duplicate_ids' => $request->duplicate_ids
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to merge duplicates: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to merge duplicates: ' . $e->getMessage());
        }
    }

    /**
     * ✅ NEW: Check if registration is allowed (helper method for API)
     */
    public function checkRegistrationStatus()
    {
        try {
            $settings = SystemSetting::getSettings();
            
            return response()->json([
                'success' => true,
                'registration_allowed' => $settings->isRegistrationAllowed(),
                'message' => $settings->getRegistrationDisabledMessage(),
                'button_text' => $settings->isRegistrationAllowed() ? 'Register Your Land/Property' : 'Registration Currently Disabled',
                'button_class' => $settings->isRegistrationAllowed() ? 'btn-success' : 'btn-secondary',
                'button_disabled' => !$settings->isRegistrationAllowed()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to check registration status: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check registration status'
            ], 500);
        }
    }

    /**
     * Send invitations to approved tenants
     */
    public function sendTenantInvitations(LandlordConstructionRegistration $registration, Request $request)
    {
        $this->authorize('update', $registration);
        
        if ($registration->status !== LandlordConstructionRegistration::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant invitations can only be sent for approved registrations.'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $property = $registration->approvedProperty;
            $landlord = $registration->landlord;
            $tenants = $registration->tenants()->where('status', 'approved')->get();
            
            if ($tenants->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No approved tenants found to send invitations to.'
                ], 400);
            }

            $invitationResults = [];
            $successCount = 0;

            foreach ($tenants as $tenant) {
                // Create invitation for tenant
                $invitation = TenantInvitation::create([
                    'tenant_id' => $tenant->id,
                    'property_id' => $property->id,
                    'invited_by' => auth()->id(),
                    'channels' => ['email', 'sms'], // Default channels, can be customized
                    'expires_at' => now()->addDays(7)
                ]);

                // Send the invitation
                $result = $this->sendTenantInvitation($invitation, $tenant, $property, $landlord);
                
                $invitationResults[] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'success' => $result['success'],
                    'message' => $result['message']
                ];

                if ($result['success']) {
                    $successCount++;
                    
                    Log::info('Tenant invitation sent', [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'invitation_id' => $invitation->id,
                        'token' => $invitation->token,
                        'property_id' => $property->id
                    ]);
                }
            }

            DB::commit();

            $message = "Sent {$successCount} out of " . $tenants->count() . " tenant invitations successfully.";

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => $successCount > 0,
                    'message' => $message,
                    'results' => $invitationResults
                ]);
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to send tenant invitations: ' . $e->getMessage(), [
                'registration_id' => $registration->id
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send tenant invitations. Please try again.'
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to send tenant invitations.');
        }
    }

    /**
     * Send individual tenant invitation
     */
    private function sendTenantInvitation(TenantInvitation $invitation, RegistrationTenant $tenant, Property $property, User $landlord): array
    {
        try {
            // Generate invitation message
            $message = $this->generateTenantInvitationMessage($tenant, $property, $landlord, $invitation);
            
            // Send via configured channels
            $results = [];
            $successCount = 0;

            foreach ($invitation->channels as $channel) {
                switch ($channel) {
                    case 'email':
                        if ($tenant->email) {
                            // Send email notification
                            Notification::route('mail', $tenant->email)
                                ->notify(new TenantInvitationNotification($invitation, $tenant, $property, $landlord));
                            $results[$channel] = true;
                            $successCount++;
                        }
                        break;
                        
                    case 'sms':
                        if ($tenant->phone) {
                            // Use multi-channel service for SMS
                            $smsResult = $this->multiChannelInvitationService->sendSms(
                                $tenant->phone,
                                $message
                            );
                            $results[$channel] = $smsResult['success'] ?? false;
                            if ($results[$channel]) $successCount++;
                        }
                        break;
                }
            }

            if ($successCount > 0) {
                $invitation->update([
                    'status' => TenantInvitation::STATUS_SENT,
                    'sent_at' => now()
                ]);

                return [
                    'success' => true,
                    'message' => "Invitation sent via {$successCount} channel(s)",
                    'results' => $results
                ];
            } else {
                $invitation->update([
                    'status' => TenantInvitation::STATUS_FAILED,
                    'last_error' => 'No channels were successfully delivered'
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to send invitation via any channel',
                    'results' => $results
                ];
            }

        } catch (\Exception $e) {
            Log::error('Failed to send tenant invitation: ' . $e->getMessage(), [
                'tenant_id' => $tenant->id,
                'invitation_id' => $invitation->id
            ]);

            $invitation->update([
                'status' => TenantInvitation::STATUS_FAILED,
                'last_error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send invitation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate tenant invitation message
     */
    private function generateTenantInvitationMessage(RegistrationTenant $tenant, Property $property, User $landlord, TenantInvitation $invitation): string
    {
        $message = "Hello {$tenant->name},\n\n";
        $message .= "You have been registered as a tenant at {$property->property_name} by your landlord, {$landlord->name}.\n\n";
        $message .= "Property Details:\n";
        $message .= "📍 Address: {$property->street_name}\n";
        if ($property->zone) $message .= "📍 Zone: {$property->zone}\n";
        if ($property->section) $message .= "📍 Section: {$property->section}\n\n";
        
        $message .= "To access your tenant portal and manage your tenancy, please click the link below:\n";
        $message .= "{$invitation->getInvitationUrl()}\n\n";
        
        $message .= "This link will expire on {$invitation->expires_at->format('F j, Y')}.\n\n";
        $message .= "If you have any questions, please contact your landlord or the estate management office.\n\n";
        $message .= "Thank you,\n";
        $message .= "Property Management Team";

        return $message;
    }

    /**
     * Resend tenant invitation
     */
    public function resendTenantInvitation(TenantInvitation $invitation)
    {
        DB::beginTransaction();

        try {
            $tenant = $invitation->tenant;
            $property = $invitation->property;
            $landlord = $property->landlord;

            // Reset invitation
            $invitation->update([
                'status' => TenantInvitation::STATUS_PENDING,
                'sent_at' => null,
                'attempts' => $invitation->attempts + 1,
                'last_error' => null
            ]);

            // Regenerate token if needed
            if ($invitation->isExpired()) {
                $invitation->token = TenantInvitation::generateToken();
                $invitation->expires_at = now()->addDays(7);
                $invitation->save();
            }

            // Send the invitation
            $result = $this->sendTenantInvitation($invitation, $tenant, $property, $landlord);

            DB::commit();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Invitation resent successfully' : 'Failed to resend invitation',
                'invitation_url' => $invitation->getInvitationUrl(),
                'token' => $invitation->token,
                'result' => $result
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to resend tenant invitation: ' . $e->getMessage(), [
                'invitation_id' => $invitation->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend invitation. Please try again.'
            ], 500);
        }
    }

    /**
     * Get tenant invitation status
     */
    public function getTenantInvitationStatus(RegistrationTenant $tenant)
    {
        try {
            $invitation = $tenant->invitations()->latest()->first();

            if (!$invitation) {
                return response()->json([
                    'success' => true,
                    'has_invitation' => false,
                    'message' => 'No invitation found for this tenant'
                ]);
            }

            return response()->json([
                'success' => true,
                'has_invitation' => true,
                'invitation' => [
                    'id' => $invitation->id,
                    'status' => $invitation->status,
                    'status_label' => $invitation->status_label,
                    'status_badge_class' => $invitation->status_badge_class,
                    'sent_at' => $invitation->sent_at?->format('Y-m-d H:i:s'),
                    'expires_at' => $invitation->expires_at->format('Y-m-d H:i:s'),
                    'accepted_at' => $invitation->accepted_at?->format('Y-m-d H:i:s'),
                    'invitation_url' => $invitation->getInvitationUrl(),
                    'is_expired' => $invitation->isExpired(),
                    'is_active' => $invitation->isActive()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get tenant invitation status: ' . $e->getMessage(), [
                'tenant_id' => $tenant->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invitation status'
            ], 500);
        }
    }

    /**
     * Get all tenant invitations for a registration
     */
    public function getTenantInvitations(LandlordConstructionRegistration $registration)
    {
        try {
            $invitations = [];
            
            foreach ($registration->tenants as $tenant) {
                $latestInvitation = $tenant->invitations()->latest()->first();
                
                $invitations[] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'tenant_phone' => $tenant->phone,
                    'tenant_email' => $tenant->email,
                    'tenant_status' => $tenant->status,
                    'has_invitation' => $latestInvitation ? true : false,
                    'invitation' => $latestInvitation ? [
                        'id' => $latestInvitation->id,
                        'status' => $latestInvitation->status,
                        'status_label' => $latestInvitation->status_label,
                        'status_badge_class' => $latestInvitation->status_badge_class,
                        'sent_at' => $latestInvitation->sent_at?->format('Y-m-d H:i:s'),
                        'expires_at' => $latestInvitation->expires_at->format('Y-m-d H:i:s'),
                        'accepted_at' => $latestInvitation->accepted_at?->format('Y-m-d H:i:s'),
                        'invitation_url' => $latestInvitation->getInvitationUrl(),
                        'is_expired' => $latestInvitation->isExpired(),
                        'is_active' => $latestInvitation->isActive()
                    ] : null
                ];
            }

            return response()->json([
                'success' => true,
                'registration_id' => $registration->id,
                'tenant_count' => $registration->tenants->count(),
                'invitations' => $invitations
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get tenant invitations: ' . $e->getMessage(), [
                'registration_id' => $registration->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tenant invitations'
            ], 500);
        }
    }

    /**
     * Check registration status using token
     */
    public function checkStatus(Request $request, $token = null)
    {
        if ($token) {
            $registration = LandlordConstructionRegistration::where('access_token', $token)
                ->with(['reviewer', 'approvedProperty'])
                ->first();
            
            if (!$registration) {
                return view('landlord.construction.status-check', [
                    'error' => 'Invalid or expired token.'
                ]);
            }
            
            return view('landlord.construction.status-check', [
                'registration' => $registration
            ]);
        }
        
        return view('landlord.construction.status-check');
    }

    /**
     * Check status via phone/email
     */
    public function checkStatusByContact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contact' => 'required|string',
            'contact_type' => 'required|in:phone,email'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $query = LandlordConstructionRegistration::query();
        
        if ($request->contact_type === 'phone') {
            $tempUser = new User();
            
            $query->where(function($q) use ($request, $tempUser) {
                $q->where('primary_phone', 'LIKE', '%' . $request->contact)
                  ->orWhere('primary_phone', $request->contact);
                
                try {
                    $standardized = $tempUser->standardizePhoneNumber($request->contact);
                    if ($standardized !== $request->contact) {
                        $q->orWhere('primary_phone', $standardized);
                    }
                } catch (\Exception $e) {
                    // Ignore standardization errors
                }
            });
        } else {
            $query->where('email', $request->contact);
        }

        $registrations = $query->with(['reviewer', 'approvedProperty'])
            ->latest()
            ->get();

        return view('landlord.construction.status-results', [
            'registrations' => $registrations,
            'contact' => $request->contact,
            'contact_type' => $request->contact_type
        ]);
    }

    /**
     * Get authenticated landlord's registrations
     */
    public function myRegistrations(Request $request)
    {
        $user = auth()->user();
        
        $registrations = LandlordConstructionRegistration::where('landlord_id', $user->id)
            ->with(['reviewer', 'approvedProperty'])
            ->latest()
            ->paginate(15);

        // Separate counts by type and purpose
        $counts = [
            'total' => LandlordConstructionRegistration::where('landlord_id', $user->id)->count(),
            
            'construction' => LandlordConstructionRegistration::where('landlord_id', $user->id)
                ->where('registration_type', self::TYPE_CONSTRUCTION)->count(),
            
            'property_capture' => LandlordConstructionRegistration::where('landlord_id', $user->id)
                ->where('registration_type', self::TYPE_PROPERTY_CAPTURE)->count(),
            
            'both_purposes' => LandlordConstructionRegistration::where('landlord_id', $user->id)
                ->where('purpose', self::PURPOSE_BOTH)->count(),
            
            'pending' => LandlordConstructionRegistration::where('landlord_id', $user->id)
                ->where('status', LandlordConstructionRegistration::STATUS_PENDING)->count(),
            
            'approved' => LandlordConstructionRegistration::where('landlord_id', $user->id)
                ->where('status', LandlordConstructionRegistration::STATUS_APPROVED)->count(),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'registrations' => $registrations,
                'counts' => $counts
            ]);
        }

        return view('landlord.construction.my-registrations', compact('registrations', 'counts'));
    }

    /**
     * View specific registration for authenticated landlord
     */
    public function showMyRegistration(LandlordConstructionRegistration $registration)
    {
        $this->authorize('view', $registration);
        
        $registration->load(['reviewer', 'approvedProperty']);
        
        return view('landlord.construction.show-my-registration', compact('registration'));
    }

    /**
     * Edit registration (if still pending or needs info)
     */
    public function editMyRegistration(LandlordConstructionRegistration $registration)
    {
        $this->authorize('update', $registration);
        
        if (!in_array($registration->status, [
            LandlordConstructionRegistration::STATUS_PENDING,
            LandlordConstructionRegistration::STATUS_NEEDS_INFO
        ])) {
            return redirect()->route('landlord.construction.my-registration', $registration)
                ->with('error', 'You can only edit pending registrations or those needing additional information.');
        }
        
        return view('landlord.construction.edit-my-registration', compact('registration'));
    }

    /**
     * Update registration
     */
    public function updateMyRegistration(Request $request, LandlordConstructionRegistration $registration)
    {
        $this->authorize('update', $registration);
        
        if (!in_array($registration->status, [
            LandlordConstructionRegistration::STATUS_PENDING,
            LandlordConstructionRegistration::STATUS_NEEDS_INFO
        ])) {
            return redirect()->route('landlord.construction.my-registration', $registration)
                ->with('error', 'You can only update pending registrations or those needing additional information.');
        }

        $includeConstruction = $request->has('include_construction') ? filter_var($request->include_construction, FILTER_VALIDATE_BOOLEAN) : false;
        $validator = $this->validateRegistration($request, $registration->registration_type, $registration->purpose, $includeConstruction, true);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            $allPhones = $this->processPhoneNumbers($request);
            
            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'primary_phone' => $allPhones[0] ?? $request->primary_phone,
                'additional_phones' => count($allPhones) > 1 ? array_slice($allPhones, 1) : null,
                'property_name' => $request->property_name,
                'plot_number' => $request->plot_number,
                'street_name' => $request->street_name,
                'digital_address' => $request->digital_address,
                'land_description' => $request->land_description,
            ];

            // Handle file uploads
            if ($request->hasFile('land_ownership_document')) {
                $updateData['land_ownership_document'] = $this->handleFileUpload($request, 'land_ownership_document');
            }

            // Update construction-specific fields if applicable
            if (($registration->registration_type === self::TYPE_CONSTRUCTION || $registration->purpose === self::PURPOSE_CONSTRUCTION) && $includeConstruction) {
                $updateData = array_merge($updateData, [
                    'property_type' => $request->property_type,
                    'custom_property_type' => $request->property_type === 'other' ? $request->custom_property_type : null,
                    'property_status' => $request->property_status,
                    'estimated_bedrooms' => $request->estimated_bedrooms,
                    'has_plans' => $request->has_plans === 'yes',
                    'estimated_completion' => $request->estimated_completion,
                ]);

                if ($request->hasFile('construction_documents')) {
                    $updateData['construction_documents'] = $this->handleMultipleFileUpload($request, 'construction_documents');
                }
            } else {
                // If construction details not provided, set to null
                $updateData = array_merge($updateData, [
                    'property_type' => null,
                    'custom_property_type' => null,
                    'property_status' => 'vacant',
                    'estimated_bedrooms' => null,
                    'has_plans' => false,
                    'estimated_completion' => null,
                ]);
            }

            // Update property capture-specific fields
            if ($registration->registration_type === self::TYPE_PROPERTY_CAPTURE || $registration->purpose === self::PURPOSE_PERMANENT_REGISTRATION) {
                $updateData = array_merge($updateData, [
                    'existing_property_type' => $request->existing_property_type,
                    'existing_custom_property_type' => $request->existing_property_type === 'other' ? $request->existing_custom_property_type : null,
                    'existing_property_status' => $request->existing_property_status,
                    'existing_bedrooms' => $request->existing_bedrooms,
                    'existing_bathrooms' => $request->existing_bathrooms,
                    'year_built' => $request->year_built,
                    'has_tenants' => $request->has_tenants === 'yes',
                    'tenant_count' => $request->tenant_count,
                ]);

                if ($request->hasFile('property_photos')) {
                    $updateData['property_photos'] = $this->handleMultipleFileUpload($request, 'property_photos');
                }

                if ($request->hasFile('property_documents')) {
                    $updateData['property_documents'] = $this->handleMultipleFileUpload($request, 'property_documents');
                }
            }

            $registration->update($updateData);

            // If status was "needs_info", change back to "pending"
            if ($registration->status === LandlordConstructionRegistration::STATUS_NEEDS_INFO) {
                $registration->status = LandlordConstructionRegistration::STATUS_PENDING;
                $registration->save();
            }

            DB::commit();

            $this->notifyAdminsAboutUpdate($registration);

            return redirect()->route('landlord.construction.my-registration', $registration)
                ->with('success', 'Your registration has been updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update registration: ' . $e->getMessage(), [
                'registration_id' => $registration->id
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update registration. Please try again.')
                ->withInput();
        }
    }

    /**
     * Cancel registration
     */
    public function cancelMyRegistration(LandlordConstructionRegistration $registration)
    {
        $this->authorize('delete', $registration);
        
        if (in_array($registration->status, [
            LandlordConstructionRegistration::STATUS_APPROVED,
            LandlordConstructionRegistration::STATUS_REJECTED
        ])) {
            return redirect()->route('landlord.construction.my-registration', $registration)
                ->with('error', 'Cannot cancel an already processed registration.');
        }

        DB::beginTransaction();

        try {
            $registration->status = LandlordConstructionRegistration::STATUS_CANCELLED;
            $registration->cancelled_at = now();
            $registration->save();

            DB::commit();

            $this->notifyAdminsAboutCancellation($registration);

            return redirect()->route('landlord.construction.my-registrations')
                ->with('success', 'Your registration has been cancelled successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to cancel registration: ' . $e->getMessage(), [
                'registration_id' => $registration->id
            ]);

            return redirect()->back()
                ->with('error', 'Failed to cancel registration. Please try again.');
        }
    }

    /**
     * Export statistics report (PDF/CSV)
     */
    public function exportStats(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|in:summary,detailed,performance,trends',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'registration_type' => 'required|in:all,construction,property_capture',
            'format' => 'required|in:csv,pdf',
            'include_charts' => 'sometimes|boolean',
            'include_trends' => 'sometimes|boolean',
            'include_admin_stats' => 'sometimes|boolean',
            'include_zone_stats' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // Gather statistics data based on filters
            $stats = $this->gatherStatistics($request);
            
            // Add report metadata
            $stats['report'] = [
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'generated_by' => auth()->user() ? auth()->user()->name : 'System',
                'date_range' => Carbon::parse($request->date_from)->format('M d, Y') . ' - ' . 
                               Carbon::parse($request->date_to)->format('M d, Y'),
                'report_type' => ucfirst($request->report_type),
                'registration_type' => ucfirst(str_replace('_', ' ', $request->registration_type)),
                'filters' => $request->only(['include_charts', 'include_trends', 'include_admin_stats', 'include_zone_stats'])
            ];

            // Generate report based on format
            if ($request->format === 'csv') {
                return $this->generateCsvReport($stats, $request);
            } else {
                return $this->generatePdfReport($stats, $request);
            }

        } catch (\Exception $e) {
            Log::error('Failed to export statistics report: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate report. Please try again.'
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to generate report. Please try again.');
        }
    }

    /**
     * Gather statistics based on request filters
     */
    private function gatherStatistics(Request $request): array
    {
        $dateFrom = Carbon::parse($request->date_from)->startOfDay();
        $dateTo = Carbon::parse($request->date_to)->endOfDay();
        
        // Base query with date filtering
        $query = LandlordConstructionRegistration::whereBetween('created_at', [$dateFrom, $dateTo]);
        
        // Filter by registration type
        if ($request->registration_type !== 'all') {
            $query->where('registration_type', $request->registration_type);
        }

        // Get all registrations for the period
        $allRegistrations = $query->get();
        
        // Get monthly stats for trends
        $monthlyStats = LandlordConstructionRegistration::select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected'),
                DB::raw('SUM(CASE WHEN status = "in_review" THEN 1 ELSE 0 END) as in_review')
            )
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when($request->registration_type !== 'all', function($q) use ($request) {
                $q->where('registration_type', $request->registration_type);
            })
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();

        // Calculate statistics
        $stats = [
            'total_registrations' => $allRegistrations->count(),
            'monthly_totals' => [
                'this_month' => $allRegistrations->whereBetween('created_at', [now()->startOfMonth(), now()])->count(),
            ],
            
            'by_type' => [
                'construction' => $allRegistrations->where('registration_type', self::TYPE_CONSTRUCTION)->count(),
                'property_capture' => $allRegistrations->where('registration_type', self::TYPE_PROPERTY_CAPTURE)->count(),
            ],
            
            'by_purpose' => [
                'construction' => $allRegistrations->where('purpose', self::PURPOSE_CONSTRUCTION)->count(),
                'permanent_registration' => $allRegistrations->where('purpose', self::PURPOSE_PERMANENT_REGISTRATION)->count(),
                'both' => $allRegistrations->where('purpose', self::PURPOSE_BOTH)->count(),
            ],
            
            'by_status' => [
                'pending' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_PENDING)->count(),
                'in_review' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_IN_REVIEW)->count(),
                'approved' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_APPROVED)->count(),
                'rejected' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_REJECTED)->count(),
                'needs_info' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_NEEDS_INFO)->count(),
                'cancelled' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_CANCELLED)->count(),
            ],
            
            'monthly_stats' => $monthlyStats,
            
            'with_tenants' => $allRegistrations->where('has_tenants', true)->count(),
            'total_tenants' => $allRegistrations->sum('tenant_count'),
            
            'avg_processing_time' => $this->calculateAverageProcessingTime($dateFrom, $dateTo, $request->registration_type),
            
            'property_type_stats' => $this->getPropertyTypeStats($dateFrom, $dateTo, $request->registration_type),
            'existing_property_type_stats' => $this->getExistingPropertyTypeStats($dateFrom, $dateTo, $request->registration_type),
            
            'zone_stats' => $this->getZoneStats($dateFrom, $dateTo, $request->registration_type),
            'section_stats' => $this->getSectionStats($dateFrom, $dateTo, $request->registration_type),
            
            'admin_performance' => $this->getAdminPerformanceStats($dateFrom, $dateTo, $request->registration_type),
            
            'recent_activity' => $this->getRecentActivity($dateFrom, $dateTo, $request->registration_type),
        ];

        // Calculate percentages
        $total = $stats['total_registrations'] > 0 ? $stats['total_registrations'] : 1;
        
        $stats['by_type']['construction_percentage'] = round(($stats['by_type']['construction'] / $total) * 100, 1);
        $stats['by_type']['property_percentage'] = round(($stats['by_type']['property_capture'] / $total) * 100, 1);
        
        $stats['by_purpose']['construction_percentage'] = round(($stats['by_purpose']['construction'] / $total) * 100, 1);
        $stats['by_purpose']['permanent_percentage'] = round(($stats['by_purpose']['permanent_registration'] / $total) * 100, 1);
        $stats['by_purpose']['both_percentage'] = round(($stats['by_purpose']['both'] / $total) * 100, 1);

        return $stats;
    }

    /**
     * Calculate average processing time
     */
    private function calculateAverageProcessingTime($dateFrom, $dateTo, $registrationType): array
    {
        $query = LandlordConstructionRegistration::whereNotNull('reviewed_at')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);
        
        if ($registrationType !== 'all') {
            $query->where('registration_type', $registrationType);
        }

        $processed = $query->get();
        
        if ($processed->isEmpty()) {
            return ['hours' => 0, 'days' => 0];
        }

        $totalHours = $processed->sum(function($item) {
            return $item->created_at->diffInHours($item->reviewed_at);
        });

        $avgHours = $totalHours / $processed->count();
        
        return [
            'hours' => round($avgHours, 1),
            'days' => round($avgHours / 24, 1)
        ];
    }

    /**
     * Get property type statistics (for construction registrations)
     */
    private function getPropertyTypeStats($dateFrom, $dateTo, $registrationType): array
    {
        $query = LandlordConstructionRegistration::whereNotNull('property_type')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);
        
        if ($registrationType !== 'all') {
            $query->where('registration_type', $registrationType);
        }

        return $query->get()
            ->groupBy('property_type')
            ->map(function($items, $type) {
                // Handle custom property types
                if ($type === 'other') {
                    $customTypes = $items->groupBy('custom_property_type')
                        ->mapWithKeys(function($customItems, $customType) {
                            $key = $customType ?: 'other_specified';
                            return [$key => $customItems->count()];
                        });
                    return $customTypes->toArray();
                }
                return $items->count();
            })
            ->flatMap(function($value, $key) {
                if (is_array($value)) {
                    return $value;
                }
                return [$key => $value];
            })
            ->toArray();
    }

    /**
     * Get existing property type statistics (for property capture)
     */
    private function getExistingPropertyTypeStats($dateFrom, $dateTo, $registrationType): array
    {
        $query = LandlordConstructionRegistration::whereNotNull('existing_property_type')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);
        
        if ($registrationType !== 'all') {
            $query->where('registration_type', $registrationType);
        }

        return $query->get()
            ->groupBy('existing_property_type')
            ->map(function($items, $type) {
                if ($type === 'other') {
                    $customTypes = $items->groupBy('existing_custom_property_type')
                        ->mapWithKeys(function($customItems, $customType) {
                            $key = $customType ?: 'other_specified';
                            return [$key => $customItems->count()];
                        });
                    return $customTypes->toArray();
                }
                return $items->count();
            })
            ->flatMap(function($value, $key) {
                if (is_array($value)) {
                    return $value;
                }
                return [$key => $value];
            })
            ->toArray();
    }

    /**
     * Get zone statistics
     */
    private function getZoneStats($dateFrom, $dateTo, $registrationType): array
    {
        $query = LandlordConstructionRegistration::whereNotNull('zone')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);
        
        if ($registrationType !== 'all') {
            $query->where('registration_type', $registrationType);
        }

        return $query->get()
            ->groupBy('zone')
            ->map->count()
            ->toArray();
    }

    /**
     * Get section statistics
     */
    private function getSectionStats($dateFrom, $dateTo, $registrationType): array
    {
        $query = LandlordConstructionRegistration::whereNotNull('section')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);
        
        if ($registrationType !== 'all') {
            $query->where('registration_type', $registrationType);
        }

        return $query->get()
            ->groupBy('section')
            ->map->count()
            ->toArray();
    }

    /**
     * Get admin performance statistics
     */
    private function getAdminPerformanceStats($dateFrom, $dateTo, $registrationType): array
    {
        $admins = User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])
            ->where('status', User::STATUS_ACTIVE)
            ->get();

        $stats = [];

        foreach ($admins as $admin) {
            $query = LandlordConstructionRegistration::where('reviewed_by', $admin->id)
                ->whereBetween('reviewed_at', [$dateFrom, $dateTo]);
            
            if ($registrationType !== 'all') {
                $query->where('registration_type', $registrationType);
            }

            $reviewed = $query->get();
            
            $approved = $reviewed->where('status', LandlordConstructionRegistration::STATUS_APPROVED)->count();
            $rejected = $reviewed->where('status', LandlordConstructionRegistration::STATUS_REJECTED)->count();
            
            // Calculate average processing time
            $totalHours = $reviewed->sum(function($item) {
                return $item->created_at->diffInHours($item->reviewed_at);
            });
            
            $avgTime = $reviewed->count() > 0 ? $totalHours / $reviewed->count() : 0;

            // Get currently assigned count
            $assignedCount = LandlordConstructionRegistration::where('assigned_to', $admin->id)
                ->whereIn('status', [LandlordConstructionRegistration::STATUS_PENDING, LandlordConstructionRegistration::STATUS_IN_REVIEW])
                ->count();

            $stats[] = [
                'name' => $admin->name,
                'email' => $admin->email,
                'approved' => $approved,
                'rejected' => $rejected,
                'avg_processing_time' => round($avgTime, 1),
                'assigned_count' => $assignedCount,
            ];
        }

        // Sort by total reviewed (approved + rejected)
        usort($stats, function($a, $b) {
            return ($b['approved'] + $b['rejected']) <=> ($a['approved'] + $a['rejected']);
        });

        return $stats;
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity($dateFrom, $dateTo, $registrationType): array
    {
        $query = LandlordConstructionRegistration::whereNotNull('reviewed_at')
            ->whereBetween('reviewed_at', [$dateFrom, $dateTo])
            ->with(['reviewer']);
        
        if ($registrationType !== 'all') {
            $query->where('registration_type', $registrationType);
        }

        return $query->latest('reviewed_at')
            ->limit(20)
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->name,
                    'status' => $item->status,
                    'registration_type' => $item->registration_type,
                    'plot_number' => $item->plot_number,
                    'reviewed_at' => $item->reviewed_at,
                    'reviewer_name' => $item->reviewer ? $item->reviewer->name : null,
                ];
            })
            ->toArray();
    }

    /**
     * Generate CSV report
     */
    private function generateCsvReport(array $stats, Request $request)
    {
        $filename = 'registration_stats_' . now()->format('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function() use ($stats, $request) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Report Header
            fputcsv($file, ['REGISTRATION STATISTICS REPORT']);
            fputcsv($file, ['Generated:', $stats['report']['generated_at']]);
            fputcsv($file, ['Generated By:', $stats['report']['generated_by']]);
            fputcsv($file, ['Date Range:', $stats['report']['date_range']]);
            fputcsv($file, ['Report Type:', $stats['report']['report_type']]);
            fputcsv($file, ['Registration Type:', $stats['report']['registration_type']]);
            fputcsv($file, []);
            
            // Summary Section
            fputcsv($file, ['SUMMARY STATISTICS']);
            fputcsv($file, ['Total Registrations', $stats['total_registrations']]);
            fputcsv($file, ['']);
            
            // By Type
            fputcsv($file, ['BY REGISTRATION TYPE']);
            fputcsv($file, ['Type', 'Count', 'Percentage']);
            fputcsv($file, ['Construction', $stats['by_type']['construction'], $stats['by_type']['construction_percentage'] . '%']);
            fputcsv($file, ['Property Capture', $stats['by_type']['property_capture'], $stats['by_type']['property_percentage'] . '%']);
            fputcsv($file, ['']);
            
            // By Purpose
            fputcsv($file, ['BY PURPOSE']);
            fputcsv($file, ['Purpose', 'Count', 'Percentage']);
            fputcsv($file, ['Construction Only', $stats['by_purpose']['construction'], $stats['by_purpose']['construction_percentage'] . '%']);
            fputcsv($file, ['Permanent Registration', $stats['by_purpose']['permanent_registration'], $stats['by_purpose']['permanent_percentage'] . '%']);
            fputcsv($file, ['Both Purposes', $stats['by_purpose']['both'], $stats['by_purpose']['both_percentage'] . '%']);
            fputcsv($file, ['']);
            
            // By Status
            fputcsv($file, ['BY STATUS']);
            fputcsv($file, ['Status', 'Count']);
            fputcsv($file, ['Pending', $stats['by_status']['pending']]);
            fputcsv($file, ['In Review', $stats['by_status']['in_review']]);
            fputcsv($file, ['Approved', $stats['by_status']['approved']]);
            fputcsv($file, ['Rejected', $stats['by_status']['rejected']]);
            fputcsv($file, ['Needs Info', $stats['by_status']['needs_info']]);
            fputcsv($file, ['Cancelled', $stats['by_status']['cancelled']]);
            fputcsv($file, ['']);
            
            // Monthly Trends
            if ($request->include_trends) {
                fputcsv($file, ['MONTHLY TRENDS']);
                fputcsv($file, ['Month', 'Total', 'Approved', 'Pending', 'Rejected', 'In Review']);
                foreach ($stats['monthly_stats'] as $month) {
                    $date = Carbon::create($month->year, $month->month, 1);
                    fputcsv($file, [
                        $date->format('M Y'),
                        $month->total,
                        $month->approved,
                        $month->pending,
                        $month->rejected,
                        $month->in_review
                    ]);
                }
                fputcsv($file, []);
            }
            
            // Admin Performance
            if ($request->include_admin_stats && !empty($stats['admin_performance'])) {
                fputcsv($file, ['ADMIN PERFORMANCE']);
                fputcsv($file, ['Admin', 'Email', 'Approved', 'Rejected', 'Total Reviewed', 'Approval Rate', 'Avg Time (hrs)', 'Assigned']);
                foreach ($stats['admin_performance'] as $admin) {
                    $totalReviewed = ($admin['approved'] ?? 0) + ($admin['rejected'] ?? 0);
                    $approvalRate = $totalReviewed > 0 ? round(($admin['approved'] / $totalReviewed) * 100, 1) : 0;
                    fputcsv($file, [
                        $admin['name'],
                        $admin['email'],
                        $admin['approved'],
                        $admin['rejected'],
                        $totalReviewed,
                        $approvalRate . '%',
                        $admin['avg_processing_time'],
                        $admin['assigned_count']
                    ]);
                }
                fputcsv($file, []);
            }
            
            // Zone Stats
            if ($request->include_zone_stats && !empty($stats['zone_stats'])) {
                fputcsv($file, ['ZONE DISTRIBUTION']);
                fputcsv($file, ['Zone', 'Count']);
                foreach ($stats['zone_stats'] as $zone => $count) {
                    fputcsv($file, [$zone, $count]);
                }
                fputcsv($file, []);
            }
            
            // Section Stats
            if ($request->include_zone_stats && !empty($stats['section_stats'])) {
                fputcsv($file, ['SECTION DISTRIBUTION']);
                fputcsv($file, ['Section', 'Count']);
                foreach ($stats['section_stats'] as $section => $count) {
                    fputcsv($file, [$section, $count]);
                }
                fputcsv($file, []);
            }
            
            // Property Type Stats
            if (!empty($stats['property_type_stats'])) {
                fputcsv($file, ['PLANNED PROPERTY TYPES (CONSTRUCTION)']);
                fputcsv($file, ['Property Type', 'Count']);
                foreach ($stats['property_type_stats'] as $type => $count) {
                    fputcsv($file, [ucfirst(str_replace('_', ' ', $type)), $count]);
                }
                fputcsv($file, []);
            }
            
            // Existing Property Type Stats
            if (!empty($stats['existing_property_type_stats'])) {
                fputcsv($file, ['EXISTING PROPERTY TYPES']);
                fputcsv($file, ['Property Type', 'Count']);
                foreach ($stats['existing_property_type_stats'] as $type => $count) {
                    fputcsv($file, [ucfirst(str_replace('_', ' ', $type)), $count]);
                }
                fputcsv($file, []);
            }
            
            // Recent Activity
            fputcsv($file, ['RECENT ACTIVITY']);
            fputcsv($file, ['Name', 'Type', 'Plot Number', 'Status', 'Reviewed By', 'Reviewed At']);
            foreach ($stats['recent_activity'] as $activity) {
                fputcsv($file, [
                    $activity['name'],
                    $activity['registration_type'],
                    $activity['plot_number'] ?? 'N/A',
                    $activity['status'],
                    $activity['reviewer_name'] ?? 'System',
                    Carbon::parse($activity['reviewed_at'])->format('Y-m-d H:i:s')
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Generate PDF report
     */
    private function generatePdfReport(array $stats, Request $request)
    {
        $filename = 'registration_stats_' . now()->format('Y-m-d_His') . '.pdf';
        
        // Generate chart images if requested
        $charts = [];
        if ($request->include_charts) {
            $charts = $this->generateChartImages($stats);
        }
        
        $pdf = Pdf::loadView('reports.registration-stats', [
            'stats' => $stats,
            'charts' => $charts,
            'includeCharts' => $request->include_charts,
            'includeTrends' => $request->include_trends,
            'includeAdminStats' => $request->include_admin_stats,
            'includeZoneStats' => $request->include_zone_stats
        ]);
        
        $pdf->setPaper('A4', 'portrait');
        
        return $pdf->download($filename);
    }

    /**
     * Generate chart images for PDF (simplified)
     */
    private function generateChartImages(array $stats): array
    {
        $charts = [];
        
        // This is a placeholder. In production, you might want to use:
        // - QuickChart.io API
        // - Chart.js server-side rendering
        // - GD library to generate simple charts
        // For now, we'll return empty array and rely on textual representation in PDF
        
        return $charts;
    }

    /**
     * ✅ UPDATED: Validate registration request based on type and purpose
     */
    private function validateRegistration(Request $request, $registrationType, $purpose, $includeConstruction = false, $isUpdate = false)
    {
        $rules = [
            // Common fields for all registrations
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'primary_phone' => [
                'required',
                'string',
                'max:30',
                function ($attribute, $value, $fail) {
                    if (!$this->isValidPhoneNumber($value)) {
                        $fail('Please enter a valid phone number (e.g., 0595652410 or +233595652410)');
                    }
                },
            ],
            'additional_phones' => 'nullable|array',
            'additional_phones.*' => [
                'nullable',
                'string',
                'max:30',
                function ($attribute, $value, $fail) {
                    if (!empty($value) && !$this->isValidPhoneNumber($value)) {
                        $fail('Please enter a valid phone number for ' . $attribute);
                    }
                },
            ],
            
            // Land/Plot Information
            'property_name' => 'required|string|max:255',
            'plot_number' => 'required|string|max:50',
            'street_name' => 'required|string|max:255',
            'digital_address' => 'nullable|string|max:255',
            'land_description' => 'nullable|string|max:1000',
            'land_ownership_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            
            // Zone and Section - Not required for submission, will be set by admin
            'zone' => 'nullable|string|max:100',
            'section' => 'nullable|string|max:100',
            
            // ========== TENANT VALIDATION RULES ==========
            'has_tenants' => 'sometimes|in:yes,no',
            'tenant_count' => 'nullable|integer|min:1|max:100',
            'tenant_data_json' => 'nullable|json',
        ];

        // Construction-specific rules - only apply if construction details are included
        if (($registrationType === self::TYPE_CONSTRUCTION || $purpose === self::PURPOSE_CONSTRUCTION) && $includeConstruction) {
            $rules = array_merge($rules, [
                'property_type' => 'required|in:residential,apartment,commercial,mixed,other',
                'custom_property_type' => 'required_if:property_type,other|nullable|string|max:100',
                'property_status' => 'required|in:under_construction,active,vacant,inactive',
                'estimated_bedrooms' => 'nullable|integer|min:1|max:20',
                'has_plans' => 'sometimes|in:yes,no',
                'estimated_completion' => 'nullable|date|after:today',
                'construction_documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            ]);
        }

        // Property capture-specific rules
        if ($registrationType === self::TYPE_PROPERTY_CAPTURE || $purpose === self::PURPOSE_PERMANENT_REGISTRATION) {
            $rules = array_merge($rules, [
                'existing_property_type' => 'required|in:residential,apartment,commercial,mixed,other',
                'existing_custom_property_type' => 'required_if:existing_property_type,other|nullable|string|max:100',
                'existing_property_status' => 'required|in:active,inactive,vacant,under_maintenance',
                'existing_bedrooms' => 'nullable|integer|min:1|max:20',
                'existing_bathrooms' => 'nullable|integer|min:1|max:20',
                'year_built' => 'nullable|integer|min:1900|max:' . date('Y'),
                'property_photos.*' => 'nullable|image|mimes:jpg,jpeg,png|max:10240',
                'property_documents.*' => 'nullable|file|mimes:pdf|max:10240',
            ]);
        }

        // Declaration required for new registrations
        if (!$isUpdate) {
            $rules['declaration'] = 'required|accepted';
            
            // Purpose is always required
            $rules['purpose'] = 'required|in:construction,permanent_registration,both';
            
            // Add include_construction field validation
            $rules['include_construction'] = 'sometimes|boolean';
        }

        $messages = [
            // Property type messages
            'custom_property_type.required_if' => 'Please specify the property type.',
            'existing_custom_property_type.required_if' => 'Please specify the property type.',
            'property_status.required' => 'The construction status field is required when providing construction details.',
            'property_type.required' => 'The planned property type is required when providing construction details.',
            
            // Declaration message
            'declaration.accepted' => 'You must accept the declaration to proceed.',
            
            // Date validation
            'estimated_completion.after' => 'Estimated completion date must be in the future.',
            
            // File upload messages
            'property_photos.*.image' => 'Property photos must be images.',
            'property_documents.*.mimes' => 'Property documents must be PDF files.',
            'land_ownership_document.max' => 'Land ownership document must not exceed 5MB.',
            'construction_documents.*.max' => 'Construction documents must not exceed 10MB each.',
            'property_photos.*.max' => 'Property photos must not exceed 10MB each.',
            'property_documents.*.max' => 'Property documents must not exceed 10MB each.',
            
            // ========== TENANT VALIDATION MESSAGES ==========
            'has_tenants.in' => 'Please select whether the property has tenants.',
            'tenant_count.required_if' => 'Please provide the number of tenants.',
            'tenant_count.integer' => 'Tenant count must be a valid number.',
            'tenant_count.min' => 'Tenant count must be at least 1.',
            'tenant_count.max' => 'Tenant count cannot exceed 100.',
            'tenant_data_json.json' => 'Tenant data must be in valid JSON format.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        // ========== CUSTOM VALIDATION FOR TENANT DATA ==========
        $validator->after(function ($validator) use ($request) {
            // Only validate tenant data if has_tenants is 'yes'
            if ($request->has('has_tenants') && $request->has_tenants === 'yes') {
                
                // Check if we have tenant data
                $hasTenantData = false;
                $tenantData = [];
                
                // Check for JSON tenant data
                if ($request->has('tenant_data_json') && !empty($request->tenant_data_json)) {
                    $tenantData = json_decode($request->tenant_data_json, true);
                    if (is_array($tenantData) && count($tenantData) > 0) {
                        $hasTenantData = true;
                        
                        // Validate each tenant in the JSON array
                        foreach ($tenantData as $index => $tenant) {
                            if (empty($tenant['name'])) {
                                $validator->errors()->add(
                                    "tenant_data_json.{$index}.name", 
                                    "Tenant " . ($index + 1) . " name is required."
                                );
                            }
                            if (empty($tenant['phone'])) {
                                $validator->errors()->add(
                                    "tenant_data_json.{$index}.phone", 
                                    "Tenant " . ($index + 1) . " phone number is required."
                                );
                            }
                        }
                    }
                }
                
                // Check for individual tenant fields (backup)
                if (!$hasTenantData && $request->has('tenant_count')) {
                    $count = (int) $request->tenant_count;
                    for ($i = 0; $i < $count; $i++) {
                        if ($request->has("tenant_name_$i") || $request->has("tenant_phone_$i")) {
                            $hasTenantData = true;
                            
                            if (empty($request->input("tenant_name_$i"))) {
                                $validator->errors()->add(
                                    "tenant_name_$i", 
                                    "Tenant " . ($i + 1) . " name is required."
                                );
                            }
                            if (empty($request->input("tenant_phone_$i"))) {
                                $validator->errors()->add(
                                    "tenant_phone_$i", 
                                    "Tenant " . ($i + 1) . " phone number is required."
                                );
                            }
                        }
                    }
                }
                
                // If no tenant data found at all, add error
                if (!$hasTenantData) {
                    $validator->errors()->add(
                        'has_tenants', 
                        'You indicated the property has tenants, but no tenant information was provided. Please add tenant details.'
                    );
                }
                
                // Validate that tenant_count matches actual number of tenants
                if ($request->has('tenant_count') && $request->tenant_count > 0) {
                    $actualCount = 0;
                    
                    // Count from JSON
                    if (!empty($tenantData)) {
                        $actualCount = count($tenantData);
                    }
                    
                    // Count from individual fields (if JSON not used)
                    if ($actualCount === 0) {
                        for ($i = 0; $i < $request->tenant_count; $i++) {
                            if ($request->has("tenant_name_$i") || $request->has("tenant_phone_$i")) {
                                $actualCount++;
                            }
                        }
                    }
                    
                    if ($actualCount > 0 && $actualCount != $request->tenant_count) {
                        $validator->errors()->add(
                            'tenant_count',
                            "The number of tenants provided ($actualCount) does not match the tenant count specified ({$request->tenant_count})."
                        );
                    }
                }
            }
        });

        return $validator;
    }

    /**
     * Validate phone number in various formats
     */
    private function isValidPhoneNumber($phone): bool
    {
        if (empty($phone)) {
            return false;
        }

        $clean = preg_replace('/[\s\-\(\)]/', '', $phone);
        
        return preg_match('/^(\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}$/', $phone) ||
               preg_match('/^(\+?233|0)\d{9}$/', $clean);
    }

    /**
     * Process phone numbers from request
     */
    private function processPhoneNumbers(Request $request): array
    {
        $phones = [];
        
        if ($request->primary_phone) {
            $phones[] = trim($request->primary_phone);
        }
        
        if ($request->has('additional_phones') && is_array($request->additional_phones)) {
            foreach ($request->additional_phones as $phone) {
                if (!empty(trim($phone))) {
                    $phones[] = trim($phone);
                }
            }
        }
        
        return $phones;
    }

    /**
     * Handle single file upload
     */
    private function handleFileUpload(Request $request, $fieldName)
    {
        if ($request->hasFile($fieldName) && $request->file($fieldName)->isValid()) {
            $path = $request->file($fieldName)->store('registrations/documents', 'public');
            return $path;
        }
        
        return null;
    }

    /**
     * Handle multiple file upload
     */
    private function handleMultipleFileUpload(Request $request, $fieldName)
    {
        if ($request->hasFile($fieldName)) {
            $paths = [];
            foreach ($request->file($fieldName) as $file) {
                if ($file->isValid()) {
                    $paths[] = $file->store('registrations/' . $fieldName, 'public');
                }
            }
            return $paths;
        }
        
        return null;
    }

    /**
     * Get success message based on registration type
     */
    private function getSuccessMessage($registrationType, $purpose, $includeConstruction = false): string
    {
        if ($purpose === self::PURPOSE_BOTH) {
            return 'Your registration has been submitted successfully! An administrator will review your construction plans and property details, then contact you soon.';
        }
        
        if ($registrationType === self::TYPE_CONSTRUCTION) {
            if ($includeConstruction) {
                return 'Your construction registration has been submitted successfully! An estate administrator will review your construction plans and contact you soon.';
            }
            return 'Your vacant land has been registered successfully! You can provide construction details later when you\'re ready to build.';
        }
        
        if ($registrationType === self::TYPE_PROPERTY_CAPTURE) {
            return 'Your property registration has been submitted successfully! An administrator will verify your property details and complete the permanent registration.';
        }
        
        return 'Your registration has been submitted successfully! An administrator will review your application and contact you soon.';
    }

    /**
     * Get redirect route based on registration type
     */
    private function getRedirectRoute($registrationType): string
    {
        return $registrationType === self::TYPE_CONSTRUCTION ? 'home' : 'home';
    }

    /**
     * Notify admins about new registration
     * Using the same pattern as LandlordTransferController
     */
    private function notifyAdmins(LandlordConstructionRegistration $registration)
    {
        // Get all active admins and super admins
        $admins = User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])
            ->where('status', User::STATUS_ACTIVE)
            ->get();

        if ($admins->isEmpty()) {
            Log::warning('No active admins found to notify about registration', [
                'registration_id' => $registration->id
            ]);
            return;
        }

        // Send appropriate notification based on registration type
        foreach ($admins as $admin) {
            try {
                if ($registration->registration_type === self::TYPE_PROPERTY_CAPTURE) {
                    $admin->notify(new \App\Notifications\PropertyRegistrationSubmitted($registration));
                } else {
                    $admin->notify(new \App\Notifications\ConstructionRegistrationSubmitted($registration));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send notification to admin: ' . $e->getMessage(), [
                    'admin_id' => $admin->id,
                    'registration_id' => $registration->id
                ]);
            }
        }

        Log::info('New registration notification sent to admins', [
            'registration_id' => $registration->id,
            'type' => $registration->registration_type,
            'admin_count' => $admins->count()
        ]);
    }

    /**
     * Notify admins about registration update
     */
    private function notifyAdminsAboutUpdate(LandlordConstructionRegistration $registration)
    {
        $admins = User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])
            ->where('status', User::STATUS_ACTIVE)
            ->get();

        foreach ($admins as $admin) {
            try {
                $admin->notify(new \App\Notifications\RegistrationUpdated($registration));
            } catch (\Exception $e) {
                Log::warning('Failed to notify admin about update: ' . $e->getMessage());
            }
        }
    }

    /**
     * Notify admins about registration cancellation
     */
    private function notifyAdminsAboutCancellation(LandlordConstructionRegistration $registration)
    {
        $admins = User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])
            ->where('status', User::STATUS_ACTIVE)
            ->get();

        foreach ($admins as $admin) {
            try {
                $admin->notify(new \App\Notifications\RegistrationCancelled($registration));
            } catch (\Exception $e) {
                Log::warning('Failed to notify admin about cancellation: ' . $e->getMessage());
            }
        }
    }

    /**
     * Webhook handler for external services
     */
    public function webhook(Request $request)
    {
        if (!$this->verifyWebhookSignature($request)) {
            Log::warning('Invalid webhook signature', $request->all());
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        try {
            switch ($event) {
                case 'registration.created':
                    $this->handleExternalRegistrationCreated($data);
                    break;
                    
                case 'registration.updated':
                    $this->handleExternalRegistrationUpdated($data);
                    break;
                    
                case 'payment.confirmed':
                    $this->handlePaymentConfirmed($data);
                    break;
                    
                default:
                    Log::info('Unhandled webhook event', ['event' => $event]);
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Webhook processing failed: ' . $e->getMessage(), [
                'event' => $event,
                'data' => $data
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Verify webhook signature
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Webhook-Signature');
        $payload = $request->getContent();
        $secret = config('services.webhook.secret');
        
        if (!$signature || !$secret) {
            return false;
        }
        
        $computedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($computedSignature, $signature);
    }

    /**
     * Handle external registration created webhook
     */
    private function handleExternalRegistrationCreated(array $data)
    {
        Log::info('External registration created', $data);
    }

    /**
     * Handle external registration updated webhook
     */
    private function handleExternalRegistrationUpdated(array $data)
    {
        Log::info('External registration updated', $data);
    }

    /**
     * Handle payment confirmed webhook
     */
    private function handlePaymentConfirmed(array $data)
    {
        if (isset($data['registration_id'])) {
            $registration = LandlordConstructionRegistration::find($data['registration_id']);
            
            if ($registration) {
                $registration->payment_status = 'paid';
                $registration->payment_reference = $data['reference'] ?? null;
                $registration->paid_at = now();
                $registration->save();
                
                Log::info('Payment confirmed for registration', [
                    'registration_id' => $registration->id
                ]);
            }
        }
    }

    /**
     * Handle bulk actions on trashed registrations
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:restore,force_delete',
            'registration_ids' => 'required|json',
        ]);

        $action = $request->action;
        $registrationIds = json_decode($request->registration_ids, true);

        if (empty($registrationIds)) {
            return redirect()->back()->with('error', 'No registrations selected.');
        }

        DB::beginTransaction();

        try {
            $count = count($registrationIds);
            $successCount = 0;

            foreach ($registrationIds as $id) {
                $registration = LandlordConstructionRegistration::withTrashed()->find($id);
                
                if (!$registration) {
                    continue;
                }

                if ($action === 'restore') {
                    // Check if the registration is trashed
                    if ($registration->trashed()) {
                        $registration->restore();
                        
                        // If there's an associated property, restore it too
                        if ($registration->approvedProperty && $registration->approvedProperty->trashed()) {
                            $registration->approvedProperty->restore();
                        }
                        
                        Log::info('Registration restored from trash', [
                            'registration_id' => $registration->id,
                            'restored_by' => auth()->id()
                        ]);
                        
                        $successCount++;
                    }
                } elseif ($action === 'force_delete') {
                    // Permanently delete the registration and associated data
                    if ($registration->trashed()) {
                        // Delete associated documents/files if they exist
                        if ($registration->land_ownership_document) {
                            Storage::disk('public')->delete($registration->land_ownership_document);
                        }
                        
                        if ($registration->construction_documents) {
                            foreach ($registration->construction_documents as $doc) {
                                Storage::disk('public')->delete($doc);
                            }
                        }
                        
                        if ($registration->property_photos) {
                            foreach ($registration->property_photos as $photo) {
                                Storage::disk('public')->delete($photo);
                            }
                        }
                        
                        if ($registration->property_documents) {
                            foreach ($registration->property_documents as $doc) {
                                Storage::disk('public')->delete($doc);
                            }
                        }
                        
                        // Force delete the registration
                        $registration->forceDelete();
                        
                        Log::info('Registration permanently deleted', [
                            'registration_id' => $registration->id,
                            'deleted_by' => auth()->id()
                        ]);
                        
                        $successCount++;
                    }
                }
            }

            DB::commit();

            $message = $action === 'restore' 
                ? "Successfully restored {$successCount} out of {$count} registration(s)."
                : "Successfully deleted {$successCount} out of {$count} registration(s).";

            return redirect()->route('admin.construction-registrations.trash')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Bulk action failed: ' . $e->getMessage(), [
                'action' => $action,
                'registration_ids' => $registrationIds,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to perform bulk action. Please try again.');
        }
    }

    /**
     * Archive registrations for a specific year
     */
    public function archiveRegistrations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:' . date('Y'),
            'dry_run' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator);
        }

        $year = $request->year;
        $dryRun = $request->boolean('dry_run', false);
        
        $startDate = Carbon::create($year, 1, 1, 0, 0, 0);
        $endDate = Carbon::create($year, 12, 31, 23, 59, 59);
        
        // Get registrations to archive
        $query = LandlordConstructionRegistration::whereBetween('created_at', [$startDate, $endDate])
            ->where('is_archived', false);
        
        $count = $query->count();
        $registrations = $dryRun ? $query->get() : null;
        
        if ($count === 0) {
            $message = "No registrations found to archive for year {$year}";
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'count' => 0,
                    'year' => $year,
                    'dry_run' => $dryRun
                ]);
            }
            return redirect()->back()->with('info', $message);
        }
        
        if ($dryRun) {
            // Just return the list for preview
            $preview = $registrations->map(function($reg) {
                return [
                    'id' => $reg->id,
                    'name' => $reg->name,
                    'property_name' => $reg->property_name,
                    'status' => $reg->status,
                    'created_at' => $reg->created_at->format('Y-m-d'),
                    'type' => $reg->registration_type,
                ];
            });
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Dry run: Found {$count} registrations to archive",
                    'count' => $count,
                    'year' => $year,
                    'dry_run' => true,
                    'preview' => $preview
                ]);
            }
            
            return view('admin.registrations.archive-preview', compact('preview', 'count', 'year'));
        }
        
        // Perform the archival
        DB::beginTransaction();
        
        try {
            $updated = $query->update([
                'is_archived' => true,
                'archived_at' => now(),
                'archive_year' => $year,
                'archive_reason' => 'Manual archival by admin',
                'archived_by' => auth()->id(),
            ]);
            
            DB::commit();
            
            Log::info("Registrations archived manually", [
                'year' => $year,
                'count' => $updated,
                'archived_by' => auth()->id(),
                'archived_by_name' => auth()->user()->name
            ]);
            
            $message = "Successfully archived {$updated} registrations for year {$year}";
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'count' => $updated,
                    'year' => $year,
                    'dry_run' => false
                ]);
            }
            
            return redirect()->route('admin.construction-registrations.index')
                ->with('success', $message);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Failed to archive registrations", [
                'year' => $year,
                'error' => $e->getMessage(),
                'archived_by' => auth()->id()
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Failed to archive registrations: " . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()
                ->with('error', "Failed to archive registrations: " . $e->getMessage());
        }
    }
}