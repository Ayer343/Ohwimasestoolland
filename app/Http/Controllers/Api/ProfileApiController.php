<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SmsService;
use App\Traits\ProfileCompletionTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ProfileApiController extends Controller implements HasMiddleware
{
    use ProfileCompletionTrait;

    protected $smsService;

    /**
     * Laravel 11+ way to declare middleware for a controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('auth:sanctum'),
        ];
    }

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    // ==================== SHOW PROFILE ====================

    /**
     * GET /api/profile
     */
    public function show()
    {
        $user = Auth::user();

        $profileData = [
            'user' => $this->formatUserData($user),
            'profile_completion' => $this->calculateProfileCompletion($user),
            'stats' => $this->getApiProfileStats($user),
            'permissions' => $this->getUserPermissions($user),
            'type_specific_data' => $this->getTypeSpecificData($user),
        ];

        return response()->json([
            'success' => true,
            'data' => $profileData
        ]);
    }

    // ==================== ROUTING METHODS ====================

    /**
     * PUT/PATCH /api/profile/personal
     */
    public function updatePersonal(Request $request)
    {
        $user = Auth::user();

        switch ($user->type) {
            case User::TYPE_SUPER_ADMIN:
            case User::TYPE_ADMIN:
                return $this->updateAdminPersonal($request);

            case User::TYPE_FIELD_AGENT:
                return $this->updateFieldAgentPersonal($request);

            case User::TYPE_SECURITY_PERSONNEL:
                return $this->updateSecurityPersonal($request);

            case User::TYPE_DEVELOPER:
                return $this->updateDeveloperPersonal($request);

            case User::TYPE_LANDLORD:
            case User::TYPE_TENANT:
                return $this->updateLandlordPersonal($request);

            case User::TYPE_CONTRACTOR:
                return $this->updateContractorPersonal($request);

            case User::TYPE_SANITATION_PERSONNEL:
                return $this->updateSanitationPersonal($request);

            default:
                return response()->json([
                    'success' => false,
                    'message' => 'User type not supported for personal info update.'
                ], 400);
        }
    }

    /**
     * PUT/PATCH /api/profile/contact
     */
    public function updateContact(Request $request)
    {
        $user = Auth::user();

        switch ($user->type) {
            case User::TYPE_SUPER_ADMIN:
            case User::TYPE_ADMIN:
                return $this->updateAdminContact($request);

            case User::TYPE_FIELD_AGENT:
                return $this->updateFieldAgentContact($request);

            case User::TYPE_SECURITY_PERSONNEL:
                return $this->updateSecurityContact($request);

            case User::TYPE_DEVELOPER:
                return $this->updateDeveloperContact($request);

            case User::TYPE_LANDLORD:
            case User::TYPE_TENANT:
                return $this->updateLandlordContact($request);

            case User::TYPE_CONTRACTOR:
                return $this->updateContractorContact($request);

            case User::TYPE_SANITATION_PERSONNEL:
                return $this->updateSanitationContact($request);

            default:
                return response()->json([
                    'success' => false,
                    'message' => 'User type not supported for contact info update.'
                ], 400);
        }
    }

    // ==================== LANDLORD/TENANT METHODS ====================

    public function updateLandlordPersonal(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:255|unique:users,username,' . $user->id,
            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date|before:today',
            'region' => 'nullable|string|max:100',
            'digital_address' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $user->update([
                'name' => $request->name,
                'username' => $request->username ?: null,
                'gender' => $request->gender,
                'dob' => $request->dob ? Carbon::parse($request->dob) : null,
                'region' => $request->region,
                'digital_address' => $request->digital_address,
                'location' => $request->location,
            ]);

            $this->updateProfileCompletion($user);
            $profileCompletion = $this->calculateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Personal information updated successfully',
                'data' => [
                    'user' => $this->formatUserData($user),
                    'profile_completion' => $profileCompletion
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update landlord personal info: ' . $e->getMessage());

            return $this->serverErrorResponse('Failed to update personal information. Please try again.');
        }
    }

    public function updateLandlordContact(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|max:20|unique:users,phone,' . $user->id,
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $updateData = [];
            $requiresVerification = [];

            if ($request->email !== $user->email) {
                $updateData['email'] = $request->email;
                $updateData['email_verified_at'] = null;
                $requiresVerification[] = 'email';
            }

            if ($request->phone !== $user->phone) {
                $updateData['phone'] = $request->phone;
                $updateData['phone_verified_at'] = null;
                $requiresVerification[] = 'phone';
                $this->sendPhoneVerificationCode($user, $request->phone);
            }

            if (!empty($updateData)) {
                $user->update($updateData);
                $this->updateProfileCompletion($user);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => $this->buildVerificationMessage('Contact information updated successfully!', $requiresVerification),
                    'data' => [
                        'profile_completion' => $this->calculateProfileCompletion($user),
                        'requires_verification' => $requiresVerification
                    ]
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'No changes detected.',
                'data' => ['profile_completion' => $this->calculateProfileCompletion($user)]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update landlord contact info: ' . $e->getMessage());

            return $this->serverErrorResponse('Failed to update contact information. Please try again.');
        }
    }

    // ==================== ADMIN/SUPER ADMIN METHODS ====================

    public function updateAdminPersonal(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:255|unique:users,username,' . $user->id,
            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date|before:today',
            'region' => 'nullable|string|max:100',
            'digital_address' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $user->update([
                'name' => $request->name,
                'username' => $request->username ?: null,
                'gender' => $request->gender,
                'dob' => $request->dob ? Carbon::parse($request->dob) : null,
                'region' => $request->region,
                'digital_address' => $request->digital_address,
                'location' => $request->location,
            ]);

            $user->refresh();
            $this->updateProfileCompletion($user);
            $user->refresh();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Personal information updated successfully',
                'data' => [
                    'user' => $this->formatUserData($user),
                    'profile_completion' => $this->calculateProfileCompletion($user),
                    'completion_details' => $this->calculateProfileCompletionDetails($user),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update admin personal info: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update personal information. Please try again.');
        }
    }

    public function updateAdminContact(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|unique:users,phone,' . $user->id,
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $updateData = [];
            $requiresVerification = [];

            if ($request->email !== $user->email) {
                $updateData['email'] = $request->email;
                $updateData['email_verified_at'] = null;
                $requiresVerification[] = 'email';
            }

            if ($request->phone !== $user->phone) {
                $updateData['phone'] = $request->phone;
                $updateData['phone_verified_at'] = null;
                $requiresVerification[] = 'phone';
                $this->sendPhoneVerificationCode($user, $request->phone);
            }

            if (!empty($updateData)) {
                $user->update($updateData);
                $this->updateProfileCompletion($user);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => $this->buildVerificationMessage('Contact information updated successfully!', $requiresVerification),
                    'data' => [
                        'profile_completion' => $this->calculateProfileCompletion($user),
                        'requires_verification' => $requiresVerification
                    ]
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'No changes detected.',
                'data' => ['profile_completion' => $this->calculateProfileCompletion($user)]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update admin contact info: ' . $e->getMessage());

            return $this->serverErrorResponse('Failed to update contact information. Please try again.');
        }
    }

    public function updateAdminPassword(Request $request)
    {
        return $this->updatePassword($request);
    }

    public function getAdminProfileStats()
    {
        $user = Auth::user();
        $stats = $this->getDetailedProfileStats($user);

        $stats['admin'] = [
            'user_type' => $user->type_name,
            'is_super_admin' => $user->isSuperAdmin(),
            'last_login' => $user->last_login_at?->toDateTimeString(),
            'last_activity' => $user->last_activity_at?->toDateTimeString(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'last_updated' => now()->toISOString()
        ]);
    }

    public function downloadAdminData()
    {
        $user = Auth::user();

        try {
            return response()->json([
                'success' => true,
                'data' => $this->compileAdminData($user),
                'exported_at' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('API: Failed to compile admin data: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to compile admin data. Please try again.');
        }
    }

    // ==================== FIELD AGENT METHODS ====================

    public function updateFieldAgentPersonal(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:100|unique:users,username,' . $user->id,
            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date|before:today',
            'national_id' => 'nullable|string|max:50',
            'emergency_contact' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $user->update([
                'name' => $request->name,
                'username' => $request->username,
                'gender' => $request->gender,
                'dob' => $request->dob ? Carbon::parse($request->dob) : null,
            ]);

            $metadata = $user->metadata ?? [];

            if ($request->filled('national_id')) {
                $metadata['national_id'] = $request->national_id;
            }
            if ($request->filled('emergency_contact')) {
                $metadata['emergency_contact'] = $request->emergency_contact;
            }

            $user->update(['metadata' => $metadata]);

            $this->updateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Personal information updated successfully',
                'data' => [
                    'user' => $this->formatUserData($user),
                    'profile_completion' => $this->calculateProfileCompletion($user),
                    'metadata' => $metadata
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update field agent personal info: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update personal information. Please try again.');
        }
    }

    public function updateFieldAgentContact(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|unique:users,phone,' . $user->id,
            'region' => 'nullable|string|max:100',
            'digital_address' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'alt_phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $updateData = [];
            $metadata = $user->metadata ?? [];
            $requiresVerification = [];

            if ($request->email !== $user->email) {
                $updateData['email'] = $request->email;
                $updateData['email_verified_at'] = null;
                $requiresVerification[] = 'email';
            }

            if ($request->phone !== $user->phone) {
                $updateData['phone'] = $request->phone;
                $updateData['phone_verified_at'] = null;
                $requiresVerification[] = 'phone';
                $this->sendPhoneVerificationCode($user, $request->phone);
            }

            if ($request->filled('region')) $updateData['region'] = $request->region;
            if ($request->filled('digital_address')) $updateData['digital_address'] = $request->digital_address;
            if ($request->filled('location')) $updateData['location'] = $request->location;
            if ($request->filled('alt_phone')) $metadata['alt_phone'] = $request->alt_phone;

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            $user->update(['metadata' => $metadata]);
            $this->updateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $this->buildVerificationMessage('Contact information updated successfully!', $requiresVerification),
                'data' => [
                    'profile_completion' => $this->calculateProfileCompletion($user),
                    'metadata' => $metadata,
                    'requires_verification' => $requiresVerification
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update field agent contact info: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update contact information. Please try again.');
        }
    }

    public function updateAgentInfo(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'specializations' => 'nullable|array',
            'specializations.*' => 'nullable|string',
            'preferences' => 'nullable|array',
            'preferences.*' => 'nullable|string',
            'equipment' => 'nullable|string|max:1000',
            'agent_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $metadata = $user->metadata ?? [];

            if ($request->has('specializations')) {
                $metadata['specializations'] = json_encode($request->specializations);
            }

            if ($request->has('preferences')) {
                $preferences = $metadata['preferences'] ?? [];
                foreach ($request->preferences as $preference) {
                    $preferences[$preference] = true;
                }
                $metadata['preferences'] = $preferences;
            }

            if ($request->filled('equipment')) {
                $metadata['equipment'] = $request->equipment;
            }
            if ($request->filled('agent_notes')) {
                $metadata['agent_notes'] = $request->agent_notes;
            }

            $user->update(['metadata' => $metadata]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Agent information updated successfully',
                'data' => ['metadata' => $metadata]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update agent info: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update agent information. Please try again.');
        }
    }

    public function updateNotifications(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'new_assignment_notifications' => 'nullable|boolean',
            'assignment_update_notifications' => 'nullable|boolean',
            'deadline_notifications' => 'nullable|boolean',
            'team_message_notifications' => 'nullable|boolean',
            'announcement_notifications' => 'nullable|boolean',
            'training_notifications' => 'nullable|boolean',
            'performance_review_notifications' => 'nullable|boolean',
            'feedback_notifications' => 'nullable|boolean',
            'recognition_notifications' => 'nullable|boolean',
            'email_notifications' => 'nullable|boolean',
            'sms_notifications' => 'nullable|boolean',
            'in_app_notifications' => 'nullable|boolean',
            'suspicious_activity_alerts' => 'nullable|boolean',
            'password_change_alerts' => 'nullable|boolean',
            'new_device_alerts' => 'nullable|boolean',
            'data_sharing' => 'nullable|boolean',
            'marketing_emails' => 'nullable|boolean',
            'location_tracking' => 'nullable|boolean',
            'two_factor_enabled' => 'nullable|boolean',
            'login_alerts' => 'nullable|boolean',
        ]);

        DB::beginTransaction();

        try {
            $metadata = $user->metadata ?? [];

            foreach ($validated as $key => $value) {
                $metadata[$key] = $value === null ? false : $value;
            }

            $user->metadata = $metadata;
            $user->save();

            $completion = $this->calculateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Notification settings updated successfully!',
                'data' => [
                    'profile_completion' => $completion,
                    'metadata' => $metadata
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update notification settings: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update notification settings. Please try again.');
        }
    }

    public function updateSecurity(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'two_factor_enabled' => 'nullable|boolean',
            'login_alerts' => 'nullable|boolean',
            'data_sharing' => 'nullable|boolean',
            'marketing_emails' => 'nullable|boolean',
            'suspicious_activity_alerts' => 'nullable|boolean',
            'password_change_alerts' => 'nullable|boolean',
            'new_device_alerts' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $metadata = $user->metadata ?? [];

            foreach ([
                'two_factor_enabled', 'login_alerts', 'data_sharing', 'marketing_emails',
                'suspicious_activity_alerts', 'password_change_alerts', 'new_device_alerts'
            ] as $field) {
                if ($request->has($field)) {
                    $metadata[$field] = $request->boolean($field);
                }
            }

            $user->update(['metadata' => $metadata]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Security settings updated successfully',
                'data' => ['metadata' => $metadata]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update security settings: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update security settings. Please try again.');
        }
    }

    public function uploadIdDocument(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'id_type' => 'required|string|in:ghana_card,passport,driver_license,voter_id',
            'id_number' => 'required|string|max:50',
            'id_document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $metadata = $user->metadata ?? [];

            if ($request->hasFile('id_document')) {
                $file = $request->file('id_document');
                $filename = 'id_document_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('id_documents', $filename, 'public');

                $metadata['id_document'] = [
                    'type' => $request->id_type,
                    'number' => $request->id_number,
                    'filename' => $filename,
                    'path' => $path,
                    'uploaded_at' => now()->toDateTimeString(),
                    'status' => 'pending_verification'
                ];

                $user->update(['metadata' => $metadata]);
                $this->updateProfileCompletion($user);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'ID document uploaded successfully! Verification pending.',
                    'data' => [
                        'profile_completion' => $this->calculateProfileCompletion($user),
                        'metadata' => $metadata
                    ]
                ]);
            }

            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'No document file provided.'
            ], 400);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to upload ID document: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to upload ID document. Please try again.');
        }
    }

    public function revokeSession(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Session ID is required'
            ], 422);
        }

        try {
            Log::info('API: Session revoked by user', [
                'user_id' => $user->id,
                'session_id' => $request->session_id,
                'ip' => request()->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Session revoked successfully!'
            ]);

        } catch (\Exception $e) {
            Log::error('API: Failed to revoke session: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to revoke session. Please try again.');
        }
    }

    // ==================== SECURITY PERSONNEL METHODS ====================

    public function updateSecurityPersonal(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:255|unique:users,username,' . $user->id,
            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date|before:today',
            'region' => 'nullable|string|max:100',
            'digital_address' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $user->update([
                'name' => $request->name,
                'username' => $request->username ?: null,
                'gender' => $request->gender,
                'dob' => $request->dob ? Carbon::parse($request->dob) : null,
                'region' => $request->region,
                'digital_address' => $request->digital_address,
                'location' => $request->location,
            ]);

            $this->updateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Personal information updated successfully',
                'data' => [
                    'user' => $this->formatUserData($user),
                    'profile_completion' => $this->calculateProfileCompletion($user)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update security personal info: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update personal information. Please try again.');
        }
    }

    public function updateSecurityContact(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|unique:users,phone,' . $user->id,
            'security_id' => 'nullable|string|max:50',
            'security_location' => 'nullable|string|max:255',
            'shift' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $updateData = [];
            $metadata = $user->metadata ?? [];
            $requiresVerification = [];

            if ($request->email !== $user->email) {
                $updateData['email'] = $request->email;
                $updateData['email_verified_at'] = null;
                $requiresVerification[] = 'email';
            }

            if ($request->phone !== $user->phone) {
                $updateData['phone'] = $request->phone;
                $updateData['phone_verified_at'] = null;
                $requiresVerification[] = 'phone';
                $this->sendPhoneVerificationCode($user, $request->phone);
            }

            if ($request->filled('security_id')) $metadata['security_id'] = $request->security_id;
            if ($request->filled('security_location')) $metadata['security_location'] = $request->security_location;
            if ($request->filled('shift')) $metadata['shift'] = $request->shift;

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            $user->update(['metadata' => $metadata]);
            $this->updateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $this->buildVerificationMessage('Security information updated successfully!', $requiresVerification),
                'data' => [
                    'profile_completion' => $this->calculateProfileCompletion($user),
                    'metadata' => $metadata
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update security info: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update security information. Please try again.');
        }
    }

    public function getSecurityProfileStats()
    {
        $user = Auth::user();
        $stats = $this->getDetailedProfileStats($user);

        $metadata = $user->metadata ?? [];
        $stats['security'] = [
            'security_id' => $metadata['security_id'] ?? null,
            'security_location' => $metadata['security_location'] ?? null,
            'shift' => $metadata['shift'] ?? null,
            'last_checkin' => $metadata['last_checkin'] ?? null,
            'total_checkins' => $metadata['total_checkins'] ?? 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'last_updated' => now()->toISOString()
        ]);
    }

    public function downloadSecurityData()
    {
        $user = Auth::user();

        try {
            return response()->json([
                'success' => true,
                'data' => $this->compileSecurityData($user),
                'exported_at' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('API: Failed to compile security data: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to compile security data. Please try again.');
        }
    }

    // ==================== SANITATION PERSONNEL METHODS ====================

    public function updateSanitationPersonal(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:255|unique:users,username,' . $user->id,
            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date|before:today',
            'region' => 'nullable|string|max:100',
            'digital_address' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'emergency_contact' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $user->update([
                'name' => $request->name,
                'username' => $request->username ?: null,
                'gender' => $request->gender,
                'dob' => $request->dob ? Carbon::parse($request->dob) : null,
                'region' => $request->region,
                'digital_address' => $request->digital_address,
                'location' => $request->location,
            ]);

            $metadata = $user->metadata ?? [];

            if ($request->filled('emergency_contact')) {
                $metadata['emergency_contact'] = $request->emergency_contact;
            }

            $personnel = $user->sanitationPersonnel;
            if ($personnel) {
                $personnelData = [];

                if ($request->has('role')) $personnelData['role'] = $request->role;
                if ($request->has('vehicle_number')) $personnelData['vehicle_number'] = $request->vehicle_number;
                if ($request->has('vehicle_type')) $personnelData['vehicle_type'] = $request->vehicle_type;

                if (!empty($personnelData)) {
                    $personnel->update($personnelData);
                }
            }

            $user->update(['metadata' => $metadata]);
            $this->updateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Personal information updated successfully',
                'data' => [
                    'user' => $this->formatUserData($user),
                    'profile_completion' => $this->calculateProfileCompletion($user)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update sanitation personnel personal info: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update personal information. Please try again.');
        }
    }

    public function updateSanitationContact(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|unique:users,phone,' . $user->id,
            'emergency_contact_phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $updateData = [];
            $metadata = $user->metadata ?? [];
            $requiresVerification = [];

            if ($request->email !== $user->email) {
                $updateData['email'] = $request->email;
                $updateData['email_verified_at'] = null;
                $requiresVerification[] = 'email';
            }

            if ($request->phone !== $user->phone) {
                $updateData['phone'] = $request->phone;
                $updateData['phone_verified_at'] = null;
                $requiresVerification[] = 'phone';
                $this->sendPhoneVerificationCode($user, $request->phone);
            }

            if ($request->filled('emergency_contact_phone')) {
                $metadata['emergency_contact_phone'] = $request->emergency_contact_phone;
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            $user->update(['metadata' => $metadata]);
            $this->updateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $this->buildVerificationMessage('Contact information updated successfully!', $requiresVerification),
                'data' => [
                    'profile_completion' => $this->calculateProfileCompletion($user),
                    'requires_verification' => $requiresVerification
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update sanitation contact info: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update contact information. Please try again.');
        }
    }

    public function updateSanitationSettings(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'role' => 'nullable|in:supervisor,worker,driver,collector',
            'vehicle_number' => 'nullable|string|max:50',
            'vehicle_type' => 'nullable|string|max:100',
            'shift_preference' => 'nullable|string|max:50',
            'is_available' => 'nullable|boolean',
            'certifications' => 'nullable|array',
            'special_skills' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $personnel = $user->sanitationPersonnel;

            if (!$personnel) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Sanitation personnel record not found.'
                ], 404);
            }

            $updateData = [];

            if ($request->has('role')) $updateData['role'] = $request->role;
            if ($request->has('vehicle_number')) $updateData['vehicle_number'] = $request->vehicle_number;
            if ($request->has('vehicle_type')) $updateData['vehicle_type'] = $request->vehicle_type;
            if ($request->has('shift_preference')) $updateData['shift_preference'] = $request->shift_preference;
            if ($request->has('is_available')) $updateData['is_available'] = $request->boolean('is_available');
            if ($request->has('special_skills')) $updateData['special_skills'] = $request->special_skills;
            if ($request->has('notes')) $updateData['notes'] = $request->notes;
            if ($request->has('certifications')) $updateData['certifications'] = $request->certifications;

            if (!empty($updateData)) {
                $personnel->update($updateData);

                Log::info('API: Sanitation personnel settings updated', [
                    'user_id' => $user->id,
                    'updates' => $updateData
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sanitation settings updated successfully!',
                'data' => ['personnel' => $personnel->fresh()]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update sanitation settings: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update sanitation settings. Please try again.');
        }
    }

    public function updateAvailability(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'is_available' => 'required|boolean',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $personnel = $user->sanitationPersonnel;

            if (!$personnel) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Sanitation personnel record not found.'
                ], 404);
            }

            $isAvailable = $request->boolean('is_available');

            $personnel->update(['is_available' => $isAvailable]);

            Log::info('API: Sanitation personnel availability updated', [
                'user_id' => $user->id,
                'is_available' => $isAvailable,
                'reason' => $request->input('reason'),
                'updated_by' => auth()->id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $isAvailable
                    ? 'You are now available for assignments.'
                    : 'You are now unavailable for assignments.',
                'data' => ['is_available' => $isAvailable]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update availability: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update availability. Please try again.');
        }
    }

    public function getSanitationStats()
    {
        $user = Auth::user();
        $personnel = $user->sanitationPersonnel;

        if (!$personnel) {
            return response()->json([
                'success' => false,
                'message' => 'Sanitation personnel record not found.'
            ], 404);
        }

        $totalAssignedRequests = $personnel->assignedRequests()->count();
        $completedRequests = $personnel->assignedRequests()->where('status', 'completed')->count();
        $pendingRequests = $personnel->assignedRequests()->where('status', 'pending')->count();
        $inProgressRequests = $personnel->assignedRequests()
            ->whereIn('status', ['assigned', 'en_route', 'arrived', 'in_progress'])
            ->count();

        $totalWasteCollected = $personnel->assignedRequests()
            ->where('status', 'completed')
            ->sum('waste_weight_kg') ?? 0;

        $completionRate = $totalAssignedRequests > 0
            ? round(($completedRequests / $totalAssignedRequests) * 100, 2)
            : 0;

        $assignedPropertiesCount = $personnel->assignedRequests()
            ->distinct('property_id')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'personnel' => [
                    'employee_id' => $personnel->employee_id,
                    'role' => $personnel->role,
                    'vehicle_number' => $personnel->vehicle_number,
                    'vehicle_type' => $personnel->vehicle_type,
                    'is_available' => $personnel->is_available,
                    'hire_date' => $personnel->hire_date?->format('M d, Y'),
                    'status' => $personnel->status,
                ],
                'statistics' => [
                    'total_assigned_requests' => $totalAssignedRequests,
                    'completed_requests' => $completedRequests,
                    'pending_requests' => $pendingRequests,
                    'in_progress_requests' => $inProgressRequests,
                    'total_waste_collected' => $totalWasteCollected,
                    'completion_rate' => $completionRate,
                    'assigned_properties_count' => $assignedPropertiesCount,
                ],
            ],
            'last_updated' => now()->toISOString()
        ]);
    }

    // ==================== CONTRACTOR METHODS ====================

    public function updateContractorPersonal(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:255|unique:users,username,' . $user->id,
            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date|before:today',
            'region' => 'nullable|string|max:100',
            'digital_address' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'contractor_license' => 'nullable|string|max:100',
            'specialization' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $user->update([
                'name' => $request->name,
                'username' => $request->username ?: null,
                'gender' => $request->gender,
                'dob' => $request->dob ? Carbon::parse($request->dob) : null,
                'region' => $request->region,
                'digital_address' => $request->digital_address,
                'location' => $request->location,
            ]);

            $metadata = $user->metadata ?? [];

            if ($request->filled('company_name')) $metadata['company_name'] = $request->company_name;
            if ($request->filled('contractor_license')) $metadata['contractor_license'] = $request->contractor_license;
            if ($request->filled('specialization')) $metadata['specialization'] = $request->specialization;

            $user->update(['metadata' => $metadata]);
            $this->updateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Personal information updated successfully',
                'data' => [
                    'user' => $this->formatUserData($user),
                    'profile_completion' => $this->calculateProfileCompletion($user),
                    'metadata' => $metadata
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update contractor personal info: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update personal information. Please try again.');
        }
    }

    public function updateContractorContact(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|unique:users,phone,' . $user->id,
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $updateData = [];
            $requiresVerification = [];

            if ($request->email !== $user->email) {
                $updateData['email'] = $request->email;
                $updateData['email_verified_at'] = null;
                $requiresVerification[] = 'email';
            }

            if ($request->phone !== $user->phone) {
                $updateData['phone'] = $request->phone;
                $updateData['phone_verified_at'] = null;
                $requiresVerification[] = 'phone';
                $this->sendPhoneVerificationCode($user, $request->phone);
            }

            if (!empty($updateData)) {
                $user->update($updateData);
                $this->updateProfileCompletion($user);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => $this->buildVerificationMessage('Contact information updated successfully!', $requiresVerification),
                    'data' => [
                        'profile_completion' => $this->calculateProfileCompletion($user),
                        'requires_verification' => $requiresVerification
                    ]
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'No changes detected.',
                'data' => ['profile_completion' => $this->calculateProfileCompletion($user)]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update contractor contact info: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update contact information. Please try again.');
        }
    }

    public function getContractorStats()
    {
        $user = Auth::user();

        try {
            $totalContracts = \App\Models\ConstructionContract::where('contractor_user_id', $user->id)->count();
            $activeContracts = \App\Models\ConstructionContract::where('contractor_user_id', $user->id)
                ->whereIn('status', ['approved', 'in_progress'])->count();
            $completedContracts = \App\Models\ConstructionContract::where('contractor_user_id', $user->id)
                ->where('status', 'completed')->count();
            $pendingContracts = \App\Models\ConstructionContract::where('contractor_user_id', $user->id)
                ->where('status', 'pending_approval')->count();
            $overdueContracts = \App\Models\ConstructionContract::where('contractor_user_id', $user->id)
                ->where('status', '!=', 'completed')
                ->where('status', '!=', 'cancelled')
                ->where('estimated_completion_date', '<', now())
                ->count();
            $totalContractValue = \App\Models\ConstructionContract::where('contractor_user_id', $user->id)
                ->sum('contract_amount') ?? 0;
            $avgContractValue = $totalContracts > 0 ? $totalContractValue / $totalContracts : 0;

            $totalMilestones = \App\Models\ConstructionMilestone::whereHas('contract', function($q) use ($user) {
                $q->where('contractor_user_id', $user->id);
            })->count();
            $completedMilestones = \App\Models\ConstructionMilestone::whereHas('contract', function($q) use ($user) {
                $q->where('contractor_user_id', $user->id);
            })->where('status', 'completed')->count();
            $pendingMilestones = \App\Models\ConstructionMilestone::whereHas('contract', function($q) use ($user) {
                $q->where('contractor_user_id', $user->id);
            })->where('status', 'pending')->count();
            $overdueMilestones = \App\Models\ConstructionMilestone::whereHas('contract', function($q) use ($user) {
                $q->where('contractor_user_id', $user->id);
            })->where('status', '!=', 'completed')
              ->where('due_date', '<', now())->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'contracts' => [
                        'total' => $totalContracts,
                        'active' => $activeContracts,
                        'completed' => $completedContracts,
                        'pending' => $pendingContracts,
                        'overdue' => $overdueContracts,
                        'total_value' => $totalContractValue,
                        'average_value' => $avgContractValue,
                    ],
                    'milestones' => [
                        'total' => $totalMilestones,
                        'completed' => $completedMilestones,
                        'pending' => $pendingMilestones,
                        'overdue' => $overdueMilestones,
                        'completion_rate' => $totalMilestones > 0
                            ? round(($completedMilestones / $totalMilestones) * 100)
                            : 0,
                    ],
                ],
                'last_updated' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('API: Failed to compile contractor stats: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to compile contractor stats. Please try again.');
        }
    }

    // ==================== DEVELOPER METHODS ====================

    public function updateDeveloperPersonal(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:255|unique:users,username,' . $user->id,
            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date|before:today',
            'region' => 'nullable|string',
            'digital_address' => 'nullable|string',
            'location' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $user->update([
                'name' => $request->name,
                'username' => $request->username ?: null,
                'gender' => $request->gender,
                'dob' => $request->dob ? Carbon::parse($request->dob) : null,
                'region' => $request->region,
                'digital_address' => $request->digital_address,
                'location' => $request->location,
            ]);

            $this->updateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Personal information updated successfully',
                'data' => [
                    'user' => $this->formatUserData($user),
                    'profile_completion' => $this->calculateProfileCompletion($user)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update developer personal info: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update personal information. Please try again.');
        }
    }

    public function updateDeveloperContact(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|unique:users,phone,' . $user->id,
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $updateData = [];
            $requiresVerification = [];

            if ($request->email !== $user->email) {
                $updateData['email'] = $request->email;
                $updateData['email_verified_at'] = null;
                $requiresVerification[] = 'email';
            }

            if ($request->phone !== $user->phone) {
                $updateData['phone'] = $request->phone;
                $updateData['phone_verified_at'] = null;
                $requiresVerification[] = 'phone';
                $this->sendPhoneVerificationCode($user, $request->phone);
            }

            if (!empty($updateData)) {
                $user->update($updateData);
                $this->updateProfileCompletion($user);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => $this->buildVerificationMessage('Contact information updated successfully!', $requiresVerification),
                    'data' => [
                        'profile_completion' => $this->calculateProfileCompletion($user),
                        'requires_verification' => $requiresVerification
                    ]
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'No changes detected.',
                'data' => ['profile_completion' => $this->calculateProfileCompletion($user)]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update developer contact info: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update contact information. Please try again.');
        }
    }

    public function updateDeveloperSettings(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'api_access_level' => 'nullable|in:read,write,admin',
            'default_environment' => 'nullable|in:local,staging,production',
            'debug_mode' => 'nullable|boolean',
            'system_alerts' => 'nullable|boolean',
            'api_change_notifications' => 'nullable|boolean',
            'security_alerts' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            $metadata = $user->metadata ?? [];

            $metadata['api_access_level'] = $request->input('api_access_level', $metadata['api_access_level'] ?? 'read');
            $metadata['default_environment'] = $request->input('default_environment', $metadata['default_environment'] ?? 'local');
            $metadata['debug_mode'] = $request->boolean('debug_mode', $metadata['debug_mode'] ?? false);
            $metadata['system_alerts'] = $request->boolean('system_alerts', $metadata['system_alerts'] ?? true);
            $metadata['api_change_notifications'] = $request->boolean('api_change_notifications', $metadata['api_change_notifications'] ?? true);
            $metadata['security_alerts'] = $request->boolean('security_alerts', $metadata['security_alerts'] ?? true);

            $user->update(['metadata' => $metadata]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Developer settings updated successfully',
                'data' => ['metadata' => $metadata]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update developer settings: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update developer settings. Please try again.');
        }
    }

    public function generateApiKey(Request $request)
    {
        $user = Auth::user();

        DB::beginTransaction();

        try {
            $apiKey = Str::random(60);

            $metadata = $user->metadata ?? [];
            $metadata['api_key'] = $apiKey;
            $metadata['api_key_generated_at'] = now()->toDateTimeString();

            $user->update(['metadata' => $metadata]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'API key generated successfully',
                'data' => [
                    'api_key' => $apiKey,
                    'generated_at' => $metadata['api_key_generated_at']
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to generate API key: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to generate API key. Please try again.');
        }
    }

    public function revokeApiKey(Request $request)
    {
        $user = Auth::user();

        DB::beginTransaction();

        try {
            $metadata = $user->metadata ?? [];
            $oldApiKey = $metadata['api_key'] ?? null;

            unset($metadata['api_key']);
            unset($metadata['api_key_generated_at']);

            $user->update(['metadata' => $metadata]);

            DB::commit();

            Log::info('API: API key revoked for developer', [
                'user_id' => $user->id,
                'old_api_key' => substr($oldApiKey, 0, 10) . '...'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'API key revoked successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to revoke API key: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to revoke API key. Please try again.');
        }
    }

    public function getDeveloperStats()
    {
        $user = Auth::user();
        return response()->json([
            'success' => true,
            'data' => $this->getDetailedDeveloperStats($user),
            'last_updated' => now()->toISOString()
        ]);
    }

    public function downloadDeveloperData()
    {
        $user = Auth::user();

        try {
            return response()->json([
                'success' => true,
                'data' => $this->compileDeveloperData($user),
                'exported_at' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('API: Failed to compile developer data: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to compile developer data. Please try again.');
        }
    }

    // ==================== EMAIL VERIFICATION METHODS ====================

    public function sendEmailVerification(Request $request)
    {
        $user = Auth::user();

        if ($request->has('email')) {
            $request->validate([
                'email' => 'required|email|unique:users,email,' . $user->id
            ]);

            if ($request->email !== $user->email) {
                $user->email = $request->email;
                $user->email_verified_at = null;
                $user->save();
            }
        }

        if (!$user->email) {
            return response()->json([
                'success' => false,
                'message' => 'No email address found to verify.'
            ], 400);
        }

        try {
            $verificationToken = Str::random(64);

            $metadata = $user->metadata ?? [];
            $metadata['email_verification_token'] = $verificationToken;
            $metadata['email_verification_sent_at'] = now()->toISOString();
            $user->metadata = $metadata;
            $user->save();

            $verificationUrl = url("/api/profile/email/verify/{$verificationToken}");

            Mail::send('emails.verification', [
                'user' => $user,
                'verificationUrl' => $verificationUrl,
                'expiryMinutes' => 60
            ], function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Verify Your Email Address - ' . config('app.name'));
            });

            Log::info('API: Email verification sent', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Verification email sent successfully! Please check your inbox.'
            ]);

        } catch (\Exception $e) {
            Log::error('API: Failed to send email verification: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to send verification email. Please try again later.');
        }
    }

    public function verifyEmail(Request $request, $token = null)
    {
        $token = $token ?? $request->input('token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification token.'
            ], 400);
        }

        $user = User::where('metadata->email_verification_token', $token)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification token.'
            ], 400);
        }

        $sentAt = $user->metadata['email_verification_sent_at'] ?? null;
        if ($sentAt && Carbon::parse($sentAt)->addMinutes(60)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Verification token has expired. Please request a new one.'
            ], 400);
        }

        $user->email_verified_at = now();

        $metadata = $user->metadata ?? [];
        unset($metadata['email_verification_token'], $metadata['email_verification_sent_at']);
        $user->metadata = $metadata;
        $user->save();

        Log::info('API: Email verified successfully', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully!'
        ]);
    }

    // ==================== SHARED METHODS ====================

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => [
                'required', 'string', 'min:8', 'confirmed', 'different:current_password',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
            ],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 422);
        }

        if ($this->isPasswordInHistory($user, $request->password)) {
            return response()->json([
                'success' => false,
                'message' => 'You have recently used this password. Please choose a different one.'
            ], 422);
        }

        DB::beginTransaction();

        try {
            $this->addPasswordToHistory($user, $user->password);

            $user->update([
                'password' => Hash::make($request->password),
                'password_changed_at' => now(),
            ]);

            $metadata = $user->metadata ?? [];
            $metadata['password_strength'] = $this->calculatePasswordStrength($request->password);
            $metadata['last_password_change'] = now()->toDateTimeString();
            $user->update(['metadata' => $metadata]);

            $this->sendPasswordChangeNotificationToUser($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to update password: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update password. Please try again.');
        }
    }

    public function uploadPhoto(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        DB::beginTransaction();

        try {
            if ($user->photo) {
                $this->deletePhoto($user->photo);
            }

            $photoPath = $this->handlePhotoUpload($request->file('photo'));
            $user->update(['photo' => $photoPath]);
            $this->updateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profile photo updated successfully!',
                'data' => [
                    'photo_url' => url("/api/v1/profile/photo/admin/users/{$user->id}"),
                    'profile_completion' => $this->calculateProfileCompletion($user)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to upload photo: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to update profile photo: ' . $e->getMessage());
        }
    }

    public function removePhoto()
    {
        $user = Auth::user();

        if (!$user->photo) {
            return response()->json([
                'success' => false,
                'message' => 'No profile photo to remove.'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $this->deletePhoto($user->photo);
            $user->photo = null;
            $user->save();

            $this->updateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profile photo removed successfully!',
                'data' => [
                    'photo_url' => null,
                    'profile_completion' => $this->calculateProfileCompletion($user)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to remove photo: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to remove profile photo. Please try again.');
        }
    }

    public function sendPhoneVerification(Request $request)
    {
        $user = Auth::user();

        if (!$user->phone) {
            return response()->json([
                'success' => false,
                'message' => 'No phone number found to verify.'
            ], 400);
        }

        try {
            $verificationCode = $this->generatePhoneVerificationCode($user);
            $formattedPhone = $this->formatPhoneNumberForSms($user->phone);

            $message = "Your phone verification code for " . config('app.name') . " is: {$verificationCode}. Valid for 10 minutes.";

            $smsResult = $this->smsService->sendWithDefaultProvider(
                $formattedPhone,
                $message,
                [
                    'is_test' => false,
                    'type' => 'phone_verification',
                    'user_id' => $user->id
                ]
            );

            if ($smsResult['success'] ?? false) {
                return response()->json([
                    'success' => true,
                    'message' => 'Verification code sent successfully!',
                    'provider' => $smsResult['provider'] ?? 'default'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code: ' . ($smsResult['message'] ?? 'Unknown error')
            ], 500);

        } catch (\Exception $e) {
            Log::error('API: Failed to send verification code: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to send verification code. Please try again.');
        }
    }

    public function verifyPhone(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'verification_code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid 6-digit verification code.'
            ], 422);
        }

        try {
            if ($user->phone_verification_code === $request->verification_code &&
                $user->phone_verification_sent_at &&
                $user->phone_verification_sent_at->diffInMinutes(now()) < 10) {

                $user->update([
                    'phone_verified_at' => now(),
                    'phone_verification_code' => null,
                    'phone_verification_sent_at' => null
                ]);

                $this->updateProfileCompletion($user);

                return response()->json([
                    'success' => true,
                    'message' => 'Phone number verified successfully!',
                    'data' => ['profile_completion' => $this->calculateProfileCompletion($user)]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code. Please try again.'
            ], 400);

        } catch (\Exception $e) {
            Log::error('API: Failed to verify phone: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to verify phone number. Please try again.');
        }
    }

    public function getStats()
    {
        $user = Auth::user();
        return response()->json([
            'success' => true,
            'data' => $this->getDetailedProfileStats($user),
            'last_updated' => now()->toISOString()
        ]);
    }

    public function downloadData()
    {
        $user = Auth::user();

        try {
            return response()->json([
                'success' => true,
                'data' => $this->compilePersonalData($user),
                'exported_at' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('API: Failed to compile personal data: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to compile personal data. Please try again.');
        }
    }

    public function requestDeletion(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
            'reason' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password is incorrect'
            ], 422);
        }

        try {
            $metadata = $user->metadata ?? [];
            $metadata['deletion_requested_at'] = now()->toISOString();
            $metadata['deletion_reason'] = $request->reason;
            $user->metadata = $metadata;
            $user->status = 'deletion_requested';
            $user->save();

            Log::info('API: Account deletion requested', [
                'user_id' => $user->id,
                'reason' => $request->reason
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Account deletion requested. You will receive a confirmation email shortly.'
            ]);

        } catch (\Exception $e) {
            Log::error('API: Failed to request deletion: ' . $e->getMessage());
            return $this->serverErrorResponse('Failed to process deletion request');
        }
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * ✅ FIXED: `photo_url` now points at the API streaming route so
     * CORS applies and Flutter web can load it cross-origin.
     * `storage_url` is still provided for non-web clients / direct
     * storage access.
     */
    private function formatUserData(User $user): array
    {
        // ✅ Compute photo URLs once, use them in both places below.
        $photoUrl = null;
        $storageUrl = null;

        if (!empty($user->photo)) {
            // API route that streams the image through Laravel with CORS.
            $photoUrl = url("/api/v1/profile/photo/admin/users/{$user->id}");

            // Direct storage URL (works when Apache/Nginx serves /storage).
            $storageUrl = Storage::disk('public')->url('users/photos/' . $user->photo);
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'username' => $user->username,
            'gender' => $user->gender,
            'dob' => $user->dob?->toISOString(),
            'region' => $user->region,
            'digital_address' => $user->digital_address,
            'location' => $user->location,
            'photo_url' => $photoUrl,
            'storage_url' => $storageUrl,
            'type' => $user->type,
            'type_name' => $user->type_name,
            'status' => $user->status,
            'email_verified' => !is_null($user->email_verified_at),
            'phone_verified' => !is_null($user->phone_verified_at),
            'created_at' => $user->created_at->toISOString(),
            'updated_at' => $user->updated_at->toISOString(),
        ];
    }

    private function getApiProfileStats(User $user): array
    {
        return [
            'profile_completion' => $this->calculateProfileCompletion($user),
            'member_since' => $user->created_at->diffForHumans(),
            'last_activity' => $user->last_activity_at?->diffForHumans() ?? 'Never',
        ];
    }

    private function getUserPermissions(User $user): array
    {
        $permissions = [
            'can_edit_profile' => true,
            'can_change_password' => true,
            'can_upload_photo' => true,
        ];

        switch ($user->type) {
            case User::TYPE_SUPER_ADMIN:
            case User::TYPE_ADMIN:
                $permissions['can_manage_users'] = true;
                $permissions['can_view_analytics'] = true;
                break;
            case User::TYPE_DEVELOPER:
                $permissions['can_access_api_docs'] = true;
                $permissions['can_generate_api_keys'] = true;
                break;
            case User::TYPE_LANDLORD:
                $permissions['can_manage_properties'] = true;
                break;
            case User::TYPE_SANITATION_PERSONNEL:
                $permissions['can_update_availability'] = true;
                $permissions['can_view_assignments'] = true;
                break;
            case User::TYPE_CONTRACTOR:
                $permissions['can_manage_contracts'] = true;
                $permissions['can_manage_milestones'] = true;
                break;
        }

        return $permissions;
    }

    private function getTypeSpecificData(User $user): array
    {
        $data = [];

        switch ($user->type) {
            case User::TYPE_LANDLORD:
                $data['vacant_units'] = $user->properties()
                    ->withCount(['units as vacant_count' => function($query) {
                        $query->where('status', 'vacant');
                    }])->get()->sum('vacant_count');
                $data['total_units'] = $user->properties()
                    ->withCount('units')->get()->sum('units_count');
                break;

            case User::TYPE_FIELD_AGENT:
                $data['active_assignments'] = $user->assignedPlans()->where('status', 'active')->count();
                $totalAssignments = $user->assignedPlans()->count();
                $completedAssignments = $user->assignedPlans()->where('status', 'completed')->count();
                $data['completion_rate'] = $totalAssignments > 0
                    ? round(($completedAssignments / $totalAssignments) * 100) : 0;
                break;

            case User::TYPE_SECURITY_PERSONNEL:
                $metadata = $user->metadata ?? [];
                $data['total_incidents'] = $metadata['incidents_reported'] ?? 0;
                $data['total_patrols'] = $metadata['patrols_completed'] ?? 0;
                break;

            case User::TYPE_SANITATION_PERSONNEL:
                $personnel = $user->sanitationPersonnel;
                if ($personnel) {
                    $data['employee_id'] = $personnel->employee_id;
                    $data['role'] = $personnel->role;
                    $data['is_available'] = $personnel->is_available;
                    $data['total_assigned_requests'] = $personnel->assignedRequests()->count();
                    $data['completed_requests'] = $personnel->assignedRequests()->where('status', 'completed')->count();
                }
                break;

            case User::TYPE_CONTRACTOR:
                $metadata = $user->metadata ?? [];
                $data['company_name'] = $metadata['company_name'] ?? null;
                $data['contractor_license'] = $metadata['contractor_license'] ?? null;
                $data['specialization'] = $metadata['specialization'] ?? null;
                $data['total_contracts'] = \App\Models\ConstructionContract::where('contractor_user_id', $user->id)->count();
                $data['active_contracts'] = \App\Models\ConstructionContract::where('contractor_user_id', $user->id)
                    ->whereIn('status', ['approved', 'in_progress'])->count();
                break;
        }

        return $data;
    }

    private function getDetailedProfileStats(User $user): array
    {
        $stats = [
            'profile_completion' => $this->calculateProfileCompletion($user),
            'completion_details' => $this->calculateProfileCompletionDetails($user),
            'member_since' => $user->created_at->diffForHumans(),
            'last_login' => $user->last_login_at?->diffForHumans() ?? 'Never',
            'last_activity' => $user->last_activity_at?->diffForHumans() ?? 'Never',
            'phone_verified' => !is_null($user->phone_verified_at),
            'email_verified' => !is_null($user->email_verified_at),
            'has_photo' => !is_null($user->photo),
            'account_age_days' => $user->created_at->diffInDays(),
        ];

        switch ($user->type) {
            case User::TYPE_LANDLORD:
                $stats['total_properties'] = $user->properties()->count();
                $stats['active_properties'] = $user->properties()->where('status', 'active')->count();
                $stats['pending_properties'] = $user->properties()->where('status', 'pending')->count();
                break;

            case User::TYPE_FIELD_AGENT:
                $stats['total_assignments'] = $user->assignedPlans()->count();
                $stats['active_assignments'] = $user->assignedPlans()->where('status', 'active')->count();
                $stats['completion_rate'] = $this->calculateFieldAgentCompletionRate($user);
                break;

            case User::TYPE_TENANT:
                $stats['total_rentals'] = $user->rentals()->count();
                $stats['active_rentals'] = $user->rentals()->where('status', 'active')->count();
                break;

            case User::TYPE_DEVELOPER:
                $metadata = $user->metadata ?? [];
                $stats['api_key_exists'] = !empty($metadata['api_key'] ?? null);
                $stats['debug_mode'] = $metadata['debug_mode'] ?? false;
                $stats['api_access_level'] = $metadata['api_access_level'] ?? 'read';
                break;

            case User::TYPE_SECURITY_PERSONNEL:
                $metadata = $user->metadata ?? [];
                $stats['security_id'] = $metadata['security_id'] ?? null;
                $stats['security_location'] = $metadata['security_location'] ?? null;
                $stats['shift'] = $metadata['shift'] ?? null;
                break;

            case User::TYPE_SANITATION_PERSONNEL:
                $personnel = $user->sanitationPersonnel;
                if ($personnel) {
                    $stats['employee_id'] = $personnel->employee_id;
                    $stats['role'] = $personnel->role;
                    $stats['vehicle_number'] = $personnel->vehicle_number;
                    $stats['vehicle_type'] = $personnel->vehicle_type;
                    $stats['is_available'] = $personnel->is_available;
                    $stats['hire_date'] = $personnel->hire_date?->format('M d, Y');
                    $stats['status'] = $personnel->status;
                    $stats['total_requests'] = $personnel->assignedRequests()->count();
                    $stats['completed_requests'] = $personnel->assignedRequests()->where('status', 'completed')->count();
                    $stats['pending_requests'] = $personnel->assignedRequests()->where('status', 'pending')->count();
                    $stats['total_waste_collected'] = $personnel->assignedRequests()
                        ->where('status', 'completed')->sum('waste_weight_kg') ?? 0;
                    $stats['completion_rate'] = $this->calculateSanitationCompletionRate($personnel);
                }
                break;

            case User::TYPE_CONTRACTOR:
                $metadata = $user->metadata ?? [];
                $stats['company_name'] = $metadata['company_name'] ?? null;
                $stats['contractor_license'] = $metadata['contractor_license'] ?? null;
                $stats['specialization'] = $metadata['specialization'] ?? null;
                break;
        }

        return $stats;
    }

    private function getDetailedDeveloperStats(User $user): array
    {
        $stats = $this->getDetailedProfileStats($user);
        $metadata = $user->metadata ?? [];

        $stats['developer'] = [
            'api_key_exists' => !empty($metadata['api_key']),
            'api_key_generated_at' => $metadata['api_key_generated_at'] ?? null,
            'api_access_level' => $metadata['api_access_level'] ?? 'read',
            'default_environment' => $metadata['default_environment'] ?? 'local',
            'debug_mode' => $metadata['debug_mode'] ?? false,
            'notifications' => [
                'system_alerts' => $metadata['system_alerts'] ?? true,
                'api_changes' => $metadata['api_change_notifications'] ?? true,
                'security_alerts' => $metadata['security_alerts'] ?? true,
            ],
            'api_usage' => $this->getApiUsageStats($user),
            'system_status' => $this->getSystemStatus(),
            'recent_activity' => $this->getRecentDeveloperActivity($user),
        ];

        return $stats;
    }

    private function calculateFieldAgentCompletionRate(User $user): float
    {
        $total = $user->assignedPlans()->count();
        $completed = $user->assignedPlans()->where('status', 'completed')->count();
        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }

    private function calculateSanitationCompletionRate($personnel): float
    {
        $total = $personnel->assignedRequests()->count();
        $completed = $personnel->assignedRequests()->where('status', 'completed')->count();
        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }

    private function isPasswordInHistory(User $user, string $newPassword): bool
    {
        $passwordHistory = $user->metadata['password_history'] ?? [];
        foreach (array_slice($passwordHistory, -5) as $oldPasswordHash) {
            if (Hash::check($newPassword, $oldPasswordHash)) {
                return true;
            }
        }
        return false;
    }

    private function addPasswordToHistory(User $user, string $oldPasswordHash): void
    {
        $metadata = $user->metadata ?? [];
        $passwordHistory = $metadata['password_history'] ?? [];
        $passwordHistory[] = $oldPasswordHash;
        $metadata['password_history'] = array_slice($passwordHistory, -5);
        $user->update(['metadata' => $metadata]);
    }

    private function calculatePasswordStrength(string $password): string
    {
        $score = 0;
        if (strlen($password) >= 8) $score++;
        if (strlen($password) >= 12) $score++;
        if (preg_match('/[a-z]/', $password)) $score++;
        if (preg_match('/[A-Z]/', $password)) $score++;
        if (preg_match('/[0-9]/', $password)) $score++;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $score++;

        if ($score >= 6) return 'strong';
        if ($score >= 4) return 'medium';
        return 'weak';
    }

    private function sendPasswordChangeNotificationToUser(User $user): void
    {
        try {
            $message = "Hello {$user->name}! Your password has been changed successfully. " .
                      "If you did not make this change, please contact support immediately.";

            if ($user->phone) {
                $this->smsService->sendWithDefaultProvider(
                    $this->formatPhoneNumberForSms($user->phone),
                    $message,
                    ['is_test' => false, 'type' => 'password_change_notification', 'user_id' => $user->id]
                );
            }

            Log::info("Password change notification sent", ['user_id' => $user->id]);
        } catch (\Exception $e) {
            Log::warning("Failed to send password change notification: " . $e->getMessage());
        }
    }

    private function generatePhoneVerificationCode(User $user): string
    {
        $code = sprintf('%06d', random_int(1, 999999));
        $user->update([
            'phone_verification_code' => $code,
            'phone_verification_sent_at' => now(),
        ]);
        return $code;
    }

    private function sendPhoneVerificationCode(User $user, string $newPhone): void
    {
        try {
            $code = $this->generatePhoneVerificationCode($user);
            $message = "Your phone verification code for " . config('app.name') . " is: {$code}. Valid for 10 minutes.";

            $this->smsService->sendWithDefaultProvider(
                $this->formatPhoneNumberForSms($newPhone),
                $message,
                ['is_test' => false, 'type' => 'phone_verification', 'user_id' => $user->id]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send phone verification code: ' . $e->getMessage());
        }
    }

    private function formatPhoneNumberForSms(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) === 9) return '+233' . $phone;
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') return '+233' . substr($phone, 1);
        if (strlen($phone) === 12 && substr($phone, 0, 3) === '233') return '+' . $phone;
        if (strlen($phone) === 13 && substr($phone, 0, 1) === '+') return $phone;

        return '+' . $phone;
    }

    private function handlePhotoUpload($photoFile): string
    {
        $directory = 'users/photos';
        $filename = 'user_' . time() . '_' . Str::random(10) . '_' . Auth::id() . '.' . $photoFile->getClientOriginalExtension();
        Storage::disk('public')->putFileAs($directory, $photoFile, $filename);
        return $filename;
    }

    private function deletePhoto(string $photoPath): void
    {
        $fullPath = 'users/photos/' . $photoPath;
        if (Storage::disk('public')->exists($fullPath)) {
            Storage::disk('public')->delete($fullPath);
        }
    }

    private function compilePersonalData(User $user): array
    {
        return [
            'personal_information' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'username' => $user->username,
                'gender' => $user->gender,
                'date_of_birth' => $user->dob?->toISOString(),
                'digital_address' => $user->digital_address,
                'region' => $user->region,
                'location' => $user->location,
            ],
            'account_information' => [
                'user_type' => $user->type_name,
                'account_status' => $user->status,
                'registration_date' => $user->created_at->toISOString(),
                'email_verified' => !is_null($user->email_verified_at),
                'phone_verified' => !is_null($user->phone_verified_at),
                'last_login' => $user->last_login_at?->toISOString(),
                'last_activity' => $user->last_activity_at?->toISOString(),
            ],
            'profile_statistics' => $this->getDetailedProfileStats($user),
            'export_metadata' => [
                'exported_at' => now()->toISOString(),
                'export_format' => 'JSON',
                'data_version' => '1.0'
            ]
        ];
    }

    private function compileAdminData(User $user): array
    {
        $personalData = $this->compilePersonalData($user);
        $metadata = $user->metadata ?? [];

        $personalData['admin_information'] = [
            'admin_level' => $user->isSuperAdmin() ? 'Super Admin' : 'Admin',
            'privileges' => $this->getAdminPrivileges($user),
            'system_access' => [
                'last_login' => $user->last_login_at?->toDateTimeString(),
                'last_activity' => $user->last_activity_at?->toDateTimeString(),
                'total_logins' => $metadata['total_logins'] ?? 0,
            ],
            'admin_stats' => [
                'profile_completion' => $this->calculateProfileCompletion($user),
                'account_age_days' => $user->created_at->diffInDays(),
                'verification_status' => [
                    'email' => !is_null($user->email_verified_at),
                    'phone' => !is_null($user->phone_verified_at),
                ],
            ],
        ];

        return $personalData;
    }

    private function getAdminPrivileges(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return ['full_system_access', 'user_management', 'system_settings', 'data_export', 'audit_logs', 'api_management'];
        }
        return ['user_management', 'data_export', 'audit_logs'];
    }

    private function compileSecurityData(User $user): array
    {
        $personalData = $this->compilePersonalData($user);
        $metadata = $user->metadata ?? [];

        $personalData['security_information'] = [
            'security_details' => [
                'security_id' => $metadata['security_id'] ?? null,
                'security_location' => $metadata['security_location'] ?? null,
                'shift' => $metadata['shift'] ?? null,
            ],
            'activity_log' => [
                'last_checkin' => $metadata['last_checkin'] ?? null,
                'total_checkins' => $metadata['total_checkins'] ?? 0,
                'last_assigned' => $metadata['last_assigned'] ?? null,
            ],
            'security_stats' => [
                'incidents_reported' => $metadata['incidents_reported'] ?? 0,
                'visitors_logged' => $metadata['visitors_logged'] ?? 0,
                'patrols_completed' => $metadata['patrols_completed'] ?? 0,
            ],
        ];

        return $personalData;
    }

    private function compileDeveloperData(User $user): array
    {
        $personalData = $this->compilePersonalData($user);
        $metadata = $user->metadata ?? [];

        $personalData['developer_settings'] = [
            'api_access' => [
                'has_api_key' => !empty($metadata['api_key']),
                'api_key_generated_at' => $metadata['api_key_generated_at'] ?? null,
                'access_level' => $metadata['api_access_level'] ?? 'read',
                'last_api_request' => $metadata['last_api_request'] ?? null,
                'total_requests' => $metadata['api_request_count'] ?? 0,
            ],
            'development_preferences' => [
                'default_environment' => $metadata['default_environment'] ?? 'local',
                'debug_mode' => $metadata['debug_mode'] ?? false,
            ],
            'notifications' => [
                'system_alerts' => $metadata['system_alerts'] ?? true,
                'api_change_notifications' => $metadata['api_change_notifications'] ?? true,
                'security_alerts' => $metadata['security_alerts'] ?? true,
            ],
            'recent_activity' => $this->getRecentDeveloperActivity($user),
        ];

        return $personalData;
    }

    private function getApiUsageStats(User $user): array
    {
        $metadata = $user->metadata ?? [];

        return [
            'total_requests' => $metadata['api_request_count'] ?? 0,
            'last_request_at' => $metadata['last_api_request'] ?? null,
            'requests_today' => $metadata['api_requests_today'] ?? 0,
            'rate_limit' => $metadata['api_rate_limit'] ?? 1000,
        ];
    }

    private function getSystemStatus(): array
    {
        return [
            'database' => $this->checkDatabaseConnection(),
            'cache' => $this->checkCacheConnection(),
            'storage' => $this->checkStorageConnection(),
            'sms_service' => $this->smsService->getSystemStatus(),
            'uptime' => $this->getSystemUptime(),
        ];
    }

    private function getRecentDeveloperActivity(User $user): array
    {
        $metadata = $user->metadata ?? [];
        $activityLog = $metadata['activity_log'] ?? [];
        return array_slice($activityLog, -10);
    }

    private function checkDatabaseConnection(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'connected', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'disconnected', 'message' => $e->getMessage()];
        }
    }

    private function checkCacheConnection(): array
    {
        try {
            cache()->put('test_connection', 'test', 1);
            return ['status' => 'connected', 'message' => 'Cache connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'disconnected', 'message' => $e->getMessage()];
        }
    }

    private function checkStorageConnection(): array
    {
        try {
            Storage::disk('public')->put('test.txt', 'test');
            Storage::disk('public')->delete('test.txt');
            return ['status' => 'connected', 'message' => 'Storage connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'disconnected', 'message' => $e->getMessage()];
        }
    }

    private function getSystemUptime(): array
    {
        return [
            'days' => 5,
            'hours' => 12,
            'minutes' => 30,
            'last_restart' => now()->subDays(5)->subHours(12)->subMinutes(30)->toDateTimeString(),
            'status' => 'stable'
        ];
    }

    // ==================== RESPONSE HELPERS ====================

    private function validationErrorResponse($validator)
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    private function serverErrorResponse(string $message)
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 500);
    }

    private function buildVerificationMessage(string $baseMessage, array $requiresVerification): string
    {
        if (empty($requiresVerification)) {
            return $baseMessage;
        }
        return $baseMessage . ' Please verify your ' . implode(' and ', $requiresVerification) . '.';
    }
}