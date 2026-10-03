<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SmsService;
use App\Traits\ProfileCompletionTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ProfileController extends Controller
{
    use ProfileCompletionTrait;

    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Show the form for editing the current user's profile
     */
    public function edit()
    {
        $user = Auth::user();
        
        // Get comprehensive user statistics
        $profileCompletion = $this->calculateProfileCompletion($user);
        $profileStats = $this->getDetailedProfileStats($user);
        
        // Initialize variables that might be used in different views
        $vacantUnits = 0;
        $totalUnits = 0;
        
        // Check if user is landlord and calculate property stats
        if ($user->isLandlord()) {
            // Calculate vacant units using an efficient query
            $vacantUnits = $user->properties()
                ->withCount(['units as vacant_count' => function($query) {
                    $query->where('status', 'vacant');
                }])
                ->get()
                ->sum('vacant_count');
            
            // Calculate total units
            $totalUnits = $user->properties()
                ->withCount('units')
                ->get()
                ->sum('units_count');
        }
        
        // Field agent specific statistics
        $activeAssignments = 0;
        $completionRate = 0;
        if ($user->type == User::TYPE_FIELD_AGENT) {
            $activeAssignments = $user->assignedPlans()->where('status', 'active')->count();
            $totalAssignments = $user->assignedPlans()->count();
            $completedAssignments = $user->assignedPlans()->where('status', 'completed')->count();
            $completionRate = $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100) : 0;
            
            $profileStats['active_assignments'] = $activeAssignments;
            $profileStats['completion_rate'] = $completionRate;
        }
        
        // Security personnel specific statistics
        $totalIncidents = 0;
        $totalPatrols = 0;
        if ($user->type == User::TYPE_SECURITY_PERSONNEL) {
            $metadata = $user->metadata ?? [];
            $totalIncidents = $metadata['incidents_reported'] ?? 0;
            $totalPatrols = $metadata['patrols_completed'] ?? 0;
            
            $profileStats['total_incidents'] = $totalIncidents;
            $profileStats['total_patrols'] = $totalPatrols;
        }

        // ============================================ //
        // ✅ SANITATION PERSONNEL SPECIFIC STATISTICS   //
        // ============================================ //
        $personnelStats = [];
        $totalAssignedRequests = 0;
        $completedRequests = 0;
        $pendingRequests = 0;
        $inProgressRequests = 0;
        $totalWasteCollected = 0;
        $completionRateSanitation = 0;
        $assignedPropertiesCount = 0;
        
        if ($user->type == User::TYPE_SANITATION_PERSONNEL) {
            // Get sanitation personnel record
            $personnel = $user->sanitationPersonnel;
            
            if ($personnel) {
                // Get request statistics
                $totalAssignedRequests = $personnel->assignedRequests()->count();
                $completedRequests = $personnel->assignedRequests()->where('status', 'completed')->count();
                $pendingRequests = $personnel->assignedRequests()->where('status', 'pending')->count();
                $inProgressRequests = $personnel->assignedRequests()
                    ->whereIn('status', ['assigned', 'en_route', 'arrived', 'in_progress'])
                    ->count();
                
                // Get total waste collected
                $totalWasteCollected = $personnel->assignedRequests()
                    ->where('status', 'completed')
                    ->sum('waste_weight_kg') ?? 0;
                
                // Calculate completion rate
                $completionRateSanitation = $totalAssignedRequests > 0 
                    ? round(($completedRequests / $totalAssignedRequests) * 100, 2) 
                    : 0;
                
                // Get assigned properties count
                $assignedPropertiesCount = $personnel->assignedRequests()
                    ->distinct('property_id')
                    ->count();
                
                // Store in profile stats
                $personnelStats = [
                    'employee_id' => $personnel->employee_id,
                    'role' => $personnel->role,
                    'vehicle_number' => $personnel->vehicle_number,
                    'vehicle_type' => $personnel->vehicle_type,
                    'is_available' => $personnel->is_available,
                    'hire_date' => $personnel->hire_date?->format('M d, Y'),
                    'status' => $personnel->status,
                ];
                
                $profileStats['sanitation_personnel'] = $personnelStats;
                $profileStats['total_assigned_requests'] = $totalAssignedRequests;
                $profileStats['completed_requests'] = $completedRequests;
                $profileStats['pending_requests'] = $pendingRequests;
                $profileStats['in_progress_requests'] = $inProgressRequests;
                $profileStats['total_waste_collected'] = $totalWasteCollected;
                $profileStats['completion_rate_sanitation'] = $completionRateSanitation;
                $profileStats['assigned_properties_count'] = $assignedPropertiesCount;
            }
        }
        
        // ============================================ //
        // CONTRACTOR SPECIFIC STATISTICS               //
        // ============================================ //
        $totalContracts = 0;
        $activeContracts = 0;
        $completedContracts = 0;
        $pendingContracts = 0;
        $overdueContracts = 0;
        $totalContractValue = 0;
        $avgContractValue = 0;
        $totalMilestones = 0;
        $completedMilestones = 0;
        $pendingMilestones = 0;
        $overdueMilestones = 0;
        
        if ($user->type == User::TYPE_CONTRACTOR) {
            // Contract statistics
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
            
            // Milestone statistics
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
            
            $profileStats['total_contracts'] = $totalContracts;
            $profileStats['active_contracts'] = $activeContracts;
            $profileStats['completed_contracts'] = $completedContracts;
            $profileStats['pending_contracts'] = $pendingContracts;
            $profileStats['overdue_contracts'] = $overdueContracts;
            $profileStats['total_contract_value'] = $totalContractValue;
            $profileStats['avg_contract_value'] = $avgContractValue;
            $profileStats['total_milestones'] = $totalMilestones;
            $profileStats['completed_milestones'] = $completedMilestones;
            $profileStats['pending_milestones'] = $pendingMilestones;
            $profileStats['overdue_milestones'] = $overdueMilestones;
            $profileStats['milestone_completion_rate'] = $totalMilestones > 0 ? round(($completedMilestones / $totalMilestones) * 100) : 0;
        }
        
        // Get active sessions
        $activeSessions = $this->getActiveSessions($user);
        
        // Get SMS provider status for phone verification
        $smsStatus = $this->smsService->getSystemStatus();
        
        // Determine which view to show based on user type
        $view = $this->getProfileViewByType($user->type);
        
        // Pass all variables to the view
        return view($view, compact(
            'user', 
            'profileCompletion', 
            'profileStats', 
            'smsStatus',
            'vacantUnits',
            'totalUnits',
            'activeAssignments',
            'completionRate',
            'totalIncidents',
            'totalPatrols',
            'activeSessions',
            // Sanitation personnel variables
            'personnelStats',
            'totalAssignedRequests',
            'completedRequests',
            'pendingRequests',
            'inProgressRequests',
            'totalWasteCollected',
            'completionRateSanitation',
            'assignedPropertiesCount',
            // Contractor-specific variables
            'totalContracts',
            'activeContracts',
            'completedContracts',
            'pendingContracts',
            'overdueContracts',
            'totalContractValue',
            'avgContractValue',
            'totalMilestones',
            'completedMilestones',
            'pendingMilestones',
            'overdueMilestones'
        ));
    }

    /**
     * ✅ ADDED: General personal info update method that routes to type-specific methods
     */
    public function updatePersonalInfo(Request $request)
    {
        $user = Auth::user();
        
        // Route to the appropriate method based on user type
        switch ($user->type) {
            case User::TYPE_SUPER_ADMIN:
            case User::TYPE_ADMIN:
                return $this->updateAdminPersonal($request);
                
            case User::TYPE_FIELD_AGENT:
                return $this->updatePersonal($request);
                
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
     * ✅ ADDED: Update personal information for sanitation personnel
     */
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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Update user data
            $updateData = [
                'name' => $request->name,
                'username' => $request->username ?: null,
                'gender' => $request->gender,
                'dob' => $request->dob ? Carbon::parse($request->dob) : null,
                'region' => $request->region,
                'digital_address' => $request->digital_address,
                'location' => $request->location,
            ];

            $user->update($updateData);

            // Update metadata for sanitation personnel
            $metadata = $user->metadata ?? [];
            
            if ($request->filled('emergency_contact')) {
                $metadata['emergency_contact'] = $request->emergency_contact;
            }

            // Update sanitation personnel record if exists
            $personnel = $user->sanitationPersonnel;
            if ($personnel) {
                $personnelData = [];
                
                // Update personnel specific fields
                if ($request->has('role')) {
                    $personnelData['role'] = $request->role;
                }
                if ($request->has('vehicle_number')) {
                    $personnelData['vehicle_number'] = $request->vehicle_number;
                }
                if ($request->has('vehicle_type')) {
                    $personnelData['vehicle_type'] = $request->vehicle_type;
                }
                
                if (!empty($personnelData)) {
                    $personnel->update($personnelData);
                }
            }

            $user->update(['metadata' => $metadata]);

            // Update profile completion
            $this->updateProfileCompletion($user);
            
            $profileCompletion = $this->calculateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Personal information updated successfully',
                'profile_completion' => $profileCompletion,
                'user' => [
                    'username' => $user->username,
                    'name' => $user->name,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update sanitation personnel personal info: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update personal information. Please try again.'
            ], 500);
        }
    }

    /**
     * ✅ ADDED: Update contact information for sanitation personnel
     */
    public function updateSanitationContact(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|unique:users,phone,' . $user->id,
            'emergency_contact_phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $updateData = [];
            $metadata = $user->metadata ?? [];
            $requiresVerification = [];

            // Handle email change
            if ($request->email !== $user->email) {
                $updateData['email'] = $request->email;
                $updateData['email_verified_at'] = null;
                $requiresVerification[] = 'email';
            }

            // Handle phone change
            if ($request->phone !== $user->phone) {
                $updateData['phone'] = $request->phone;
                $updateData['phone_verified_at'] = null;
                $requiresVerification[] = 'phone';
                
                // Send verification code for new phone number
                $this->sendPhoneVerificationCode($user, $request->phone);
            }

            // Update emergency contact in metadata
            if ($request->filled('emergency_contact_phone')) {
                $metadata['emergency_contact_phone'] = $request->emergency_contact_phone;
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            $user->update(['metadata' => $metadata]);

            // Update profile completion
            $this->updateProfileCompletion($user);
            
            $profileCompletion = $this->calculateProfileCompletion($user);

            DB::commit();

            $successMessage = 'Contact information updated successfully!';
            
            if (!empty($requiresVerification)) {
                $successMessage .= ' Please verify your ' . implode(' and ', $requiresVerification) . '.';
            }

            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'profile_completion' => $profileCompletion,
                'requires_verification' => $requiresVerification
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update sanitation contact info: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update contact information. Please try again.'
            ], 500);
        }
    }

    /**
     * ✅ ADDED: Update sanitation personnel specific settings
     */
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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $personnel = $user->sanitationPersonnel;
            
            if (!$personnel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sanitation personnel record not found.'
                ], 404);
            }

            $updateData = [];

            if ($request->has('role')) {
                $updateData['role'] = $request->role;
            }
            if ($request->has('vehicle_number')) {
                $updateData['vehicle_number'] = $request->vehicle_number;
            }
            if ($request->has('vehicle_type')) {
                $updateData['vehicle_type'] = $request->vehicle_type;
            }
            if ($request->has('shift_preference')) {
                $updateData['shift_preference'] = $request->shift_preference;
            }
            if ($request->has('is_available')) {
                $updateData['is_available'] = $request->boolean('is_available');
            }
            if ($request->has('special_skills')) {
                $updateData['special_skills'] = $request->special_skills;
            }
            if ($request->has('notes')) {
                $updateData['notes'] = $request->notes;
            }
            if ($request->has('certifications')) {
                $updateData['certifications'] = $request->certifications;
            }

            if (!empty($updateData)) {
                $personnel->update($updateData);

                // Log the update
                Log::info('Sanitation personnel settings updated', [
                    'user_id' => $user->id,
                    'updates' => $updateData
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sanitation settings updated successfully!',
                'personnel' => $personnel->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update sanitation settings: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update sanitation settings. Please try again.'
            ], 500);
        }
    }

    /**
     * ✅ ADDED: Update availability status for sanitation personnel
     */
    public function updateAvailability(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'is_available' => 'required|boolean',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $personnel = $user->sanitationPersonnel;
            
            if (!$personnel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sanitation personnel record not found.'
                ], 404);
            }

            $isAvailable = $request->boolean('is_available');
            $reason = $request->input('reason');

            // Update availability
            $personnel->update([
                'is_available' => $isAvailable,
            ]);

            // Log status change
            Log::info('Sanitation personnel availability updated', [
                'user_id' => $user->id,
                'is_available' => $isAvailable,
                'reason' => $reason,
                'updated_by' => auth()->id()
            ]);

            DB::commit();

            $statusMessage = $isAvailable ? 'You are now available for assignments.' : 'You are now unavailable for assignments.';

            return response()->json([
                'success' => true,
                'message' => $statusMessage,
                'is_available' => $isAvailable
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update availability: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update availability. Please try again.'
            ], 500);
        }
    }

    /**
     * ✅ ADDED: Update contractor personal information
     */
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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $updateData = [
                'name' => $request->name,
                'username' => $request->username ?: null,
                'gender' => $request->gender,
                'dob' => $request->dob ? Carbon::parse($request->dob) : null,
                'region' => $request->region,
                'digital_address' => $request->digital_address,
                'location' => $request->location,
            ];

            $user->update($updateData);

            // Update metadata for contractor
            $metadata = $user->metadata ?? [];
            
            if ($request->filled('company_name')) {
                $metadata['company_name'] = $request->company_name;
            }
            if ($request->filled('contractor_license')) {
                $metadata['contractor_license'] = $request->contractor_license;
            }
            if ($request->filled('specialization')) {
                $metadata['specialization'] = $request->specialization;
            }

            $user->update(['metadata' => $metadata]);

            // Update profile completion
            $this->updateProfileCompletion($user);
            
            $profileCompletion = $this->calculateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Personal information updated successfully',
                'profile_completion' => $profileCompletion,
                'user' => [
                    'username' => $user->username,
                    'name' => $user->name,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update contractor personal info: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update personal information. Please try again.'
            ], 500);
        }
    }

    /**
     * ✅ ADDED: Update contact information for contractor
     */
    public function updateContractorContact(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|unique:users,phone,' . $user->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $updateData = [];
            $requiresVerification = [];

            // Handle email change
            if ($request->email !== $user->email) {
                $updateData['email'] = $request->email;
                $updateData['email_verified_at'] = null;
                $requiresVerification[] = 'email';
            }

            // Handle phone change
            if ($request->phone !== $user->phone) {
                $updateData['phone'] = $request->phone;
                $updateData['phone_verified_at'] = null;
                $requiresVerification[] = 'phone';
                
                // Send verification code for new phone number
                $this->sendPhoneVerificationCode($user, $request->phone);
            }

            if (!empty($updateData)) {
                $user->update($updateData);

                // Update profile completion
                $this->updateProfileCompletion($user);
                
                $profileCompletion = $this->calculateProfileCompletion($user);

                DB::commit();

                $successMessage = 'Contact information updated successfully!';
                
                if (!empty($requiresVerification)) {
                    $successMessage .= ' Please verify your ' . implode(' and ', $requiresVerification) . '.';
                }

                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'profile_completion' => $profileCompletion,
                    'requires_verification' => $requiresVerification
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'No changes detected.',
                'profile_completion' => $this->calculateProfileCompletion($user)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update contractor contact info: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update contact information. Please try again.'
            ], 500);
        }
    }

    // ==================== GET PROFILE VIEW BY TYPE ====================

    /**
     * Get appropriate profile view based on user type
     * ✅ UPDATED: Added sanitation personnel
     */
    private function getProfileViewByType($userType)
    {
        $viewMap = [
            User::TYPE_SUPER_ADMIN => 'admin.profile.edit',
            User::TYPE_ADMIN => 'admin.profile.edit',
            User::TYPE_LANDLORD => 'landlord.profile.edit',
            User::TYPE_TENANT => 'tenant.profile.edit',
            User::TYPE_FIELD_AGENT => 'agent.profile.edit',
            User::TYPE_DEVELOPER => 'developer.profile.edit',
            User::TYPE_SECURITY_PERSONNEL => 'security.profile.edit',
            User::TYPE_CONTRACTOR => 'contractor.profile.edit',
            User::TYPE_SANITATION_PERSONNEL => 'sanitation.profile.edit', // ✅ ADDED
        ];

        return $viewMap[$userType] ?? 'profile.edit';
    }

    // ==================== GET DETAILED PROFILE STATS ====================

    /**
     * Get detailed profile statistics
     * ✅ UPDATED: Added sanitation personnel statistics
     */
    private function getDetailedProfileStats(User $user): array
    {
        $completion = $this->calculateProfileCompletion($user);
        $completionDetails = $this->calculateProfileCompletionDetails($user);

        $stats = [
            'profile_completion' => $completion,
            'completion_details' => $completionDetails,
            'member_since' => $user->created_at->diffForHumans(),
            'last_login' => $user->last_login_at?->diffForHumans() ?? 'Never',
            'last_activity' => $user->last_activity_at?->diffForHumans() ?? 'Never',
            'phone_verified' => !is_null($user->phone_verified_at),
            'email_verified' => !is_null($user->email_verified_at),
            'has_photo' => !is_null($user->photo),
            'account_age_days' => $user->created_at->diffInDays(),
        ];

        // Add type-specific statistics
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
                $stats['api_key_exists'] = !empty($user->metadata['api_key'] ?? null);
                $stats['debug_mode'] = $user->metadata['debug_mode'] ?? false;
                $stats['api_access_level'] = $user->metadata['api_access_level'] ?? 'read';
                break;
                
            case User::TYPE_SECURITY_PERSONNEL:
                $stats['security_id'] = $user->metadata['security_id'] ?? null;
                $stats['security_location'] = $user->metadata['security_location'] ?? null;
                $stats['shift'] = $user->metadata['shift'] ?? null;
                break;

            case User::TYPE_CONTRACTOR:
                $stats['company_name'] = $user->metadata['company_name'] ?? null;
                $stats['contractor_license'] = $user->metadata['contractor_license'] ?? null;
                $stats['specialization'] = $user->metadata['specialization'] ?? null;
                break;

            case User::TYPE_SANITATION_PERSONNEL: // ✅ ADDED
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
                    $stats['in_progress_requests'] = $personnel->assignedRequests()
                        ->whereIn('status', ['assigned', 'en_route', 'arrived', 'in_progress'])
                        ->count();
                    $stats['total_waste_collected'] = $personnel->assignedRequests()
                        ->where('status', 'completed')
                        ->sum('waste_weight_kg') ?? 0;
                    $stats['completion_rate'] = $this->calculateSanitationCompletionRate($personnel);
                    $stats['assigned_properties'] = $personnel->assignedRequests()->distinct('property_id')->count();
                }
                break;
        }

        return $stats;
    }

    /**
     * ✅ ADDED: Calculate sanitation completion rate
     */
    private function calculateSanitationCompletionRate($personnel): float
    {
        $total = $personnel->assignedRequests()->count();
        $completed = $personnel->assignedRequests()->where('status', 'completed')->count();
        
        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }

    // ==================== ACTIVE SESSIONS ====================

    /**
     * Get active sessions for the user
     */
    private function getActiveSessions(User $user): array
    {
        // Mock implementation - replace with actual session tracking
        $sessions = [];
        
        // Current session
        $sessions[] = [
            'id' => session()->getId(),
            'device' => $this->getDeviceType(request()->userAgent()),
            'location' => 'Current Location',
            'last_active' => 'Just now',
            'current' => true,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent()
        ];
        
        return $sessions;
    }

    /**
     * Get device type from user agent
     */
    private function getDeviceType($userAgent): string
    {
        if (strpos($userAgent, 'Mobile') !== false) {
            return 'Mobile Device';
        }
        if (strpos($userAgent, 'Tablet') !== false) {
            return 'Tablet';
        }
        return 'Desktop Computer';
    }


    /**
     * ✅ ADDED: Landlord/Tenant personal info update method
     */
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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $updateData = [
                'name' => $request->name,
                'username' => $request->username ?: null,
                'gender' => $request->gender,
                'dob' => $request->dob ? Carbon::parse($request->dob) : null,
                'region' => $request->region,
                'digital_address' => $request->digital_address,
                'location' => $request->location,
            ];

            $user->update($updateData);

            // Update profile completion
            $this->updateProfileCompletion($user);
            
            $profileCompletion = $this->calculateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Personal information updated successfully',
                'profile_completion' => $profileCompletion,
                'user' => [
                    'username' => $user->username,
                    'name' => $user->name,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update landlord personal info: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update personal information. Please try again.'
            ], 500);
        }
    }

    // ==================== ADMIN/SUPER ADMIN METHODS ====================

    /**
     * Update personal information for admin/super admin
     */
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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $updateData = [
                'name' => $request->name,
                'username' => $request->username ?: null,
                'gender' => $request->gender,
                'dob' => $request->dob ? Carbon::parse($request->dob) : null,
                'region' => $request->region,
                'digital_address' => $request->digital_address,
                'location' => $request->location,
            ];

            $user->update($updateData);

            // Update profile completion
            $this->updateProfileCompletion($user);
            
            $profileCompletion = $this->calculateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Personal information updated successfully',
                'profile_completion' => $profileCompletion,
                'user' => [
                    'username' => $user->username,
                    'name' => $user->name,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update admin personal info: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update personal information. Please try again.'
            ], 500);
        }
    }

    /**
     * Update contact information for admin/super admin
     */
    public function updateAdminContact(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|unique:users,phone,' . $user->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $updateData = [];
            $requiresVerification = [];

            // Handle email change
            if ($request->email !== $user->email) {
                $updateData['email'] = $request->email;
                $updateData['email_verified_at'] = null;
                $requiresVerification[] = 'email';
            }

            // Handle phone change
            if ($request->phone !== $user->phone) {
                $updateData['phone'] = $request->phone;
                $updateData['phone_verified_at'] = null;
                $requiresVerification[] = 'phone';
                
                // Send verification code for new phone number
                $this->sendPhoneVerificationCode($user, $request->phone);
            }

            if (!empty($updateData)) {
                $user->update($updateData);

                // Update profile completion
                $this->updateProfileCompletion($user);
                
                $profileCompletion = $this->calculateProfileCompletion($user);

                DB::commit();

                $successMessage = 'Contact information updated successfully!';
                
                if (!empty($requiresVerification)) {
                    $successMessage .= ' Please verify your ' . implode(' and ', $requiresVerification) . '.';
                }

                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'profile_completion' => $profileCompletion
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'No changes detected.',
                'profile_completion' => $this->calculateProfileCompletion($user)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update admin contact info: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update contact information. Please try again.'
            ], 500);
        }
    }

    /**
     * Update password for admin/super admin
     */
    public function updateAdminPassword(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'different:current_password',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
            ],
        ], [
            'current_password.required' => 'Current password is required',
            'password.required' => 'New password is required',
            'password.min' => 'Password must be at least 8 characters',
            'password.confirmed' => 'Password confirmation does not match',
            'password.different' => 'New password must be different from current password',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number and one special character',
        ]);

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            $validator->errors()->add('current_password', 'Current password is incorrect');
        }

        // Check password history (prevent reuse of recent passwords)
        if ($this->isPasswordInHistory($user, $request->password)) {
            $validator->errors()->add('password', 'You have recently used this password. Please choose a different one.');
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Store old password hash for history
            $this->addPasswordToHistory($user, $user->password);

            $user->update([
                'password' => Hash::make($request->password),
                'password_changed_at' => now(),
            ]);

            // Update metadata
            $metadata = $user->metadata ?? [];
            $metadata['password_strength'] = $this->calculatePasswordStrength($request->password);
            $metadata['last_password_change'] = now()->toDateTimeString();
            $user->update(['metadata' => $metadata]);

            // Send notification about password change
            $this->sendPasswordChangeNotificationToUser($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully!',
                'reload' => true
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update admin password: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update password. Please try again.'
            ], 500);
        }
    }

    /**
     * Get admin profile statistics
     */
    public function getAdminProfileStats()
    {
        $user = Auth::user();
        
        $stats = $this->getDetailedProfileStats($user);

        // Add admin-specific stats
        $stats['admin'] = [
            'user_type' => $user->type_name,
            'is_super_admin' => $user->isSuperAdmin(),
            'last_login' => $user->last_login_at?->toDateTimeString(),
            'last_activity' => $user->last_activity_at?->toDateTimeString(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'last_updated' => now()->toISOString()
        ]);
    }

    /**
     * Download admin data
     */
    public function downloadAdminData()
    {
        $user = Auth::user();

        try {
            $adminData = $this->compileAdminData($user);

            $jsonData = json_encode($adminData, JSON_PRETTY_PRINT);
            
            $fileName = 'admin_data_' . $user->id . '_' . date('Y-m-d_H-i-s') . '.json';

            return response($jsonData, 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to compile admin data: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to compile admin data. Please try again.'
            ], 500);
        }
    }

    /**
     * Compile admin-specific data for download
     */
    private function compileAdminData(User $user): array
    {
        $personalData = $this->compilePersonalData($user);
        
        $metadata = $user->metadata ?? [];
        
        // Add admin-specific data
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

    /**
     * Get admin privileges
     */
    private function getAdminPrivileges(User $user): array
    {
        $privileges = [];
        
        if ($user->isSuperAdmin()) {
            $privileges = [
                'full_system_access',
                'user_management',
                'system_settings',
                'data_export',
                'audit_logs',
                'api_management',
            ];
        } else {
            $privileges = [
                'user_management',
                'data_export',
                'audit_logs',
            ];
        }
        
        return $privileges;
    }

    // ==================== FIELD AGENT METHODS ====================

    /**
     * Update personal information for field agent
     */
    public function updatePersonal(Request $request)
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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $updateData = [
                'name' => $request->name,
                'username' => $request->username,
                'gender' => $request->gender,
                'dob' => $request->dob ? Carbon::parse($request->dob) : null,
            ];

            // Update metadata
            $metadata = $user->metadata ?? [];
            
            if ($request->filled('national_id')) {
                $metadata['national_id'] = $request->national_id;
            }
            
            if ($request->filled('emergency_contact')) {
                $metadata['emergency_contact'] = $request->emergency_contact;
            }

            // Update user data
            $user->update($updateData);
            
            // Update metadata
            if (!empty($metadata)) {
                $user->update(['metadata' => $metadata]);
            }

            // Update profile completion
            $this->updateProfileCompletion($user);
            
            $profileCompletion = $this->calculateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Personal information updated successfully',
                'profile_completion' => $profileCompletion,
                'user' => [
                    'username' => $user->username,
                    'name' => $user->name,
                ],
                'metadata' => $metadata
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update field agent personal info: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update personal information. Please try again.'
            ], 500);
        }
    }

    /**
     * Update contact information for field agent
     */
    public function updateContact(Request $request)
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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $updateData = [];
            $metadata = $user->metadata ?? [];
            $requiresVerification = [];

            // Handle email change
            if ($request->email !== $user->email) {
                $updateData['email'] = $request->email;
                $updateData['email_verified_at'] = null;
                $requiresVerification[] = 'email';
            }

            // Handle phone change
            if ($request->phone !== $user->phone) {
                $updateData['phone'] = $request->phone;
                $updateData['phone_verified_at'] = null;
                $requiresVerification[] = 'phone';
                
                // Send verification code for new phone number
                $this->sendPhoneVerificationCode($user, $request->phone);
            }

            // Update standard fields
            if ($request->filled('region')) {
                $updateData['region'] = $request->region;
            }
            
            if ($request->filled('digital_address')) {
                $updateData['digital_address'] = $request->digital_address;
            }
            
            if ($request->filled('location')) {
                $updateData['location'] = $request->location;
            }

            // Update metadata
            if ($request->filled('alt_phone')) {
                $metadata['alt_phone'] = $request->alt_phone;
            }

            // Update user data
            if (!empty($updateData)) {
                $user->update($updateData);
            }

            // Update metadata
            $user->update(['metadata' => $metadata]);

            // Update profile completion
            $this->updateProfileCompletion($user);
            
            $profileCompletion = $this->calculateProfileCompletion($user);

            DB::commit();

            $successMessage = 'Contact information updated successfully!';
            
            if (!empty($requiresVerification)) {
                $successMessage .= ' Please verify your ' . implode(' and ', $requiresVerification) . '.';
            }

            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'profile_completion' => $profileCompletion,
                'metadata' => $metadata,
                'reload' => !empty($requiresVerification) // Trigger reload if verification needed
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update field agent contact info: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update contact information. Please try again.'
            ], 500);
        }
    }

    /**
     * Update agent information for field agent
     */
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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $metadata = $user->metadata ?? [];
            
            // Update specializations
            if ($request->has('specializations')) {
                $metadata['specializations'] = json_encode($request->specializations);
            }
            
            // Update preferences
            if ($request->has('preferences')) {
                $preferences = $metadata['preferences'] ?? [];
                foreach ($request->preferences as $preference) {
                    $preferences[$preference] = true;
                }
                $metadata['preferences'] = $preferences;
            }
            
            // Update equipment
            if ($request->filled('equipment')) {
                $metadata['equipment'] = $request->equipment;
            }
            
            // Update agent notes
            if ($request->filled('agent_notes')) {
                $metadata['agent_notes'] = $request->agent_notes;
            }
            
            // Update user metadata
            $user->update(['metadata' => $metadata]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Agent information updated successfully',
                'metadata' => $metadata
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update field agent info: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update agent information. Please try again.'
            ], 500);
        }
    }

    /**
     * ✅ NEW: Update notifications settings for field agent
     */
    public function updateNotifications(Request $request)
    {
        $user = Auth::user();
        
        // Validate the request
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
            // Get current metadata
            $metadata = $user->metadata ?? [];
            
            // Update notification settings
            foreach ($validated as $key => $value) {
                $metadata[$key] = $value === null ? false : $value;
            }
            
            // Save to database
            $user->metadata = $metadata;
            $user->save();
            
            // Update profile completion
            $completion = $this->calculateProfileCompletion($user);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Notification settings updated successfully!',
                'profile_completion' => $completion,
                'metadata' => $metadata
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update notification settings: ' . $user->id . ': ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification settings. Please try again.'
            ], 500);
        }
    }

    /**
     * Update security settings for field agent
     */
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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $metadata = $user->metadata ?? [];
            
            // Update security settings
            if ($request->has('two_factor_enabled')) {
                $metadata['two_factor_enabled'] = $request->boolean('two_factor_enabled');
            }
            
            if ($request->has('login_alerts')) {
                $metadata['login_alerts'] = $request->boolean('login_alerts');
            }
            
            if ($request->has('data_sharing')) {
                $metadata['data_sharing'] = $request->boolean('data_sharing');
            }
            
            if ($request->has('marketing_emails')) {
                $metadata['marketing_emails'] = $request->boolean('marketing_emails');
            }
            
            if ($request->has('suspicious_activity_alerts')) {
                $metadata['suspicious_activity_alerts'] = $request->boolean('suspicious_activity_alerts');
            }
            
            if ($request->has('password_change_alerts')) {
                $metadata['password_change_alerts'] = $request->boolean('password_change_alerts');
            }
            
            if ($request->has('new_device_alerts')) {
                $metadata['new_device_alerts'] = $request->boolean('new_device_alerts');
            }

            // Update user metadata
            $user->update(['metadata' => $metadata]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Security settings updated successfully',
                'metadata' => $metadata
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update security settings: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update security settings. Please try again.'
            ], 500);
        }
    }

    /**
     * Update password for field agent
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'different:current_password',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
            ],
        ], [
            'current_password.required' => 'Current password is required',
            'password.required' => 'New password is required',
            'password.min' => 'Password must be at least 8 characters',
            'password.confirmed' => 'Password confirmation does not match',
            'password.different' => 'New password must be different from current password',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number and one special character',
        ]);

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            $validator->errors()->add('current_password', 'Current password is incorrect');
        }

        // Check password history (prevent reuse of recent passwords)
        if ($this->isPasswordInHistory($user, $request->password)) {
            $validator->errors()->add('password', 'You have recently used this password. Please choose a different one.');
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Store old password hash for history
            $this->addPasswordToHistory($user, $user->password);

            $user->update([
                'password' => Hash::make($request->password),
                'password_changed_at' => now(),
            ]);

            // Update metadata
            $metadata = $user->metadata ?? [];
            $metadata['password_strength'] = $this->calculatePasswordStrength($request->password);
            $metadata['last_password_change'] = now()->toDateTimeString();
            $user->update(['metadata' => $metadata]);

            // Send notification about password change
            $this->sendPasswordChangeNotificationToUser($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully!',
                'reload' => true // Trigger page reload
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update password for field agent ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update password. Please try again.'
            ], 500);
        }
    }

    /**
     * Upload ID document for field agent
     */
    public function uploadIdDocument(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'id_type' => 'required|string|in:ghana_card,passport,driver_license,voter_id',
            'id_number' => 'required|string|max:50',
            'id_document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $metadata = $user->metadata ?? [];
            
            // Handle file upload
            if ($request->hasFile('id_document')) {
                $file = $request->file('id_document');
                $filename = 'id_document_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('id_documents', $filename, 'public');
                
                // Update metadata
                $metadata['id_document'] = [
                    'type' => $request->id_type,
                    'number' => $request->id_number,
                    'filename' => $filename,
                    'path' => $path,
                    'uploaded_at' => now()->toDateTimeString(),
                    'status' => 'pending_verification'
                ];
                
                // Update user metadata
                $user->update(['metadata' => $metadata]);

                // Update profile completion
                $this->updateProfileCompletion($user);
                
                $profileCompletion = $this->calculateProfileCompletion($user);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'ID document uploaded successfully! Verification pending.',
                    'profile_completion' => $profileCompletion,
                    'metadata' => $metadata,
                    'reload' => true
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No document file provided.'
            ], 400);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to upload ID document for field agent: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload ID document. Please try again.'
            ], 500);
        }
    }

    /**
     * Revoke session for field agent
     */
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
            // In a real application, you would:
            // 1. Validate session belongs to user
            // 2. Delete session from database or cache
            // 3. Log the session revocation
            
            Log::info('Session revoked by user', [
                'user_id' => $user->id,
                'session_id' => $request->session_id,
                'ip' => request()->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Session revoked successfully!'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to revoke session: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke session. Please try again.'
            ], 500);
        }
    }

    

   /**
 * Send email verification to user
 */
public function sendEmailVerification(Request $request)
{
    $user = Auth::user();
    
    // Validate email if provided in request
    if ($request->has('email')) {
        $request->validate([
            'email' => 'required|email|unique:users,email,' . $user->id
        ]);
        
        // If email changed, update it first
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
        // Generate verification token
        $verificationToken = Str::random(64);
        
        // Store token in user metadata or verification table
        $metadata = $user->metadata ?? [];
        $metadata['email_verification_token'] = $verificationToken;
        $metadata['email_verification_sent_at'] = now()->toISOString();
        $user->metadata = $metadata;
        $user->save();
        
        // Generate verification URL
        $verificationUrl = route('developer.profile.email.verify', ['token' => $verificationToken]);
        
        // Send email using Laravel's Mail
        Mail::send('emails.verification', [
            'user' => $user,
            'verificationUrl' => $verificationUrl,
            'expiryMinutes' => 60
        ], function ($message) use ($user) {
            $message->to($user->email)
                    ->subject('Verify Your Email Address - ' . config('app.name'));
        });
        
        Log::info('Email verification sent', [
            'user_id' => $user->id,
            'email' => $user->email,
            'token_sent' => true
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Verification email sent successfully! Please check your inbox.'
        ]);
        
    } catch (\Exception $e) {
        Log::error('Failed to send email verification: ' . $e->getMessage(), [
            'user_id' => $user->id,
            'email' => $user->email
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to send verification email. Please try again later.'
        ], 500);
    }
}

/**
 * Verify email with token
 */
public function verifyEmail(Request $request, $token = null)
{
    // Get token from request if not in route parameter
    $token = $token ?? $request->input('token');
    
    if (!$token) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification token.'
            ], 400);
        }
        return redirect()->route('developer.profile.edit')
            ->with('error', 'Invalid verification token.');
    }
    
    // Find user by token
    $user = User::where('metadata->email_verification_token', $token)->first();
    
    if (!$user) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification token.'
            ], 400);
        }
        return redirect()->route('developer.profile.edit')
            ->with('error', 'Invalid or expired verification token.');
    }
    
    // Check if token is expired (60 minutes)
    $sentAt = $user->metadata['email_verification_sent_at'] ?? null;
    if ($sentAt && Carbon::parse($sentAt)->addMinutes(60)->isPast()) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Verification token has expired. Please request a new one.'
            ], 400);
        }
        return redirect()->route('developer.profile.edit')
            ->with('error', 'Verification token has expired. Please request a new one.');
    }
    
    // Verify the email
    $user->email_verified_at = now();
    
    // Clear verification token
    $metadata = $user->metadata ?? [];
    unset($metadata['email_verification_token']);
    unset($metadata['email_verification_sent_at']);
    $user->metadata = $metadata;
    $user->save();
    
    Log::info('Email verified successfully', [
        'user_id' => $user->id,
        'email' => $user->email
    ]);
    
    if ($request->expectsJson()) {
        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully!',
            'reload' => true
        ]);
    }
    
    return redirect()->route('developer.profile.edit')
        ->with('success', 'Email verified successfully!');
}

    // ==================== SECURITY PERSONNEL METHODS ====================

    /**
     * Update personal information for security personnel
     */
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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $updateData = [
                'name' => $request->name,
                'username' => $request->username ?: null,
                'gender' => $request->gender,
                'dob' => $request->dob ? Carbon::parse($request->dob) : null,
                'region' => $request->region,
                'digital_address' => $request->digital_address,
                'location' => $request->location,
            ];

            $user->update($updateData);

            // Update profile completion
            $this->updateProfileCompletion($user);
            
            $profileCompletion = $this->calculateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Personal information updated successfully',
                'profile_completion' => $profileCompletion,
                'user' => [
                    'username' => $user->username,
                    'name' => $user->name,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update personal info for security personnel: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update personal information. Please try again.'
            ], 500);
        }
    }

    /**
     * Update security information for security personnel
     */
    public function updateSecurityInfo(Request $request)
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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $updateData = [];
            $metadata = $user->metadata ?? [];
            $requiresVerification = [];

            // Handle email change
            if ($request->email !== $user->email) {
                $updateData['email'] = $request->email;
                $updateData['email_verified_at'] = null;
                $requiresVerification[] = 'email';
            }

            // Handle phone change
            if ($request->phone !== $user->phone) {
                $updateData['phone'] = $request->phone;
                $updateData['phone_verified_at'] = null;
                $requiresVerification[] = 'phone';
                
                // Send verification code for new phone number
                $this->sendPhoneVerificationCode($user, $request->phone);
            }

            // Update metadata for security-specific fields
            if ($request->filled('security_id')) {
                $metadata['security_id'] = $request->security_id;
            }
            
            if ($request->filled('security_location')) {
                $metadata['security_location'] = $request->security_location;
            }
            
            if ($request->filled('shift')) {
                $metadata['shift'] = $request->shift;
            }

            // Update user data
            if (!empty($updateData)) {
                $user->update($updateData);
            }

            // Update metadata
            $user->update(['metadata' => $metadata]);

            // Update profile completion
            $this->updateProfileCompletion($user);
            
            $profileCompletion = $this->calculateProfileCompletion($user);

            DB::commit();

            $successMessage = 'Security information updated successfully!';
            
            if (!empty($requiresVerification)) {
                $successMessage .= ' Please verify your ' . implode(' and ', $requiresVerification) . '.';
            }

            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'profile_completion' => $profileCompletion,
                'metadata' => $metadata
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update security info: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update security information. Please try again.'
            ], 500);
        }
    }

    /**
     * Send phone verification for security personnel
     */
    public function sendSecurityPhoneVerification(Request $request)
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
            
            // Format the phone number properly
            $formattedPhone = $this->formatPhoneNumberForSms($user->phone);
            
            $message = "Your security personnel verification code is: {$verificationCode}. Valid for 10 minutes.";
            
            // ✅ FIXED: Correct parameter order - provider, phone, message, options
            $smsResult = $this->smsService->sendWithDefaultProvider(
                $formattedPhone,
                $message,
                [
                    'is_test' => false,
                    'type' => 'security_phone_verification',
                    'user_id' => $user->id
                ]
            );
            
            if ($smsResult['success'] ?? false) {
                return response()->json([
                    'success' => true,
                    'message' => 'Verification code sent successfully!'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send verification code. Please try again.'
                ], 500);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to send security verification code: ' . $user->id . ': ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code. Please try again.'
            ], 500);
        }
    }

    /**
     * Verify phone for security personnel
     */
    public function verifySecurityPhone(Request $request)
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
            // Check if code matches and is not expired (10 minutes)
            if ($user->phone_verification_code === $request->verification_code && 
                $user->phone_verification_sent_at && 
                $user->phone_verification_sent_at->diffInMinutes(now()) < 10) {
                
                $user->update([
                    'phone_verified_at' => now(),
                    'phone_verification_code' => null,
                    'phone_verification_sent_at' => null
                ]);
                
                // Update profile completion
                $this->updateProfileCompletion($user);
                
                Log::info("Security personnel phone number verified successfully", [
                    'user_id' => $user->id,
                    'phone' => $this->maskPhone($user->phone)
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Phone number verified successfully!',
                    'profile_completion' => $this->calculateProfileCompletion($user)
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification code. Please try again.'
                ], 400);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to verify security phone: ' . $user->id . ': ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify phone number. Please try again.'
            ], 500);
        }
    }

    /**
     * Get security personnel profile statistics
     */
    public function getSecurityProfileStats()
    {
        $user = Auth::user();
        
        $stats = $this->getDetailedProfileStats($user);

        // Add security-specific stats
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
            'stats' => $stats,
            'last_updated' => now()->toISOString()
        ]);
    }

    /**
     * Download security personnel data
     */
    public function downloadSecurityData()
    {
        $user = Auth::user();

        try {
            $securityData = $this->compileSecurityData($user);

            $jsonData = json_encode($securityData, JSON_PRETTY_PRINT);
            
            $fileName = 'security_personnel_data_' . $user->id . '_' . date('Y-m-d_H-i-s') . '.json';

            return response($jsonData, 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to compile security personnel data: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to compile security data. Please try again.'
            ], 500);
        }
    }

    /**
     * Compile security personnel-specific data for download
     */
    private function compileSecurityData(User $user): array
    {
        $personalData = $this->compilePersonalData($user);
        
        $metadata = $user->metadata ?? [];
        
        // Add security-specific data
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

    // ==================== DEVELOPER PROFILE METHODS ====================

    /**
     * Update personal information for developer profile
     */
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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $updateData = [
                'name' => $request->name,
                'username' => $request->username ?: null,
                'gender' => $request->gender,
                'dob' => $request->dob ? \Carbon\Carbon::parse($request->dob) : null,
                'region' => $request->region,
                'digital_address' => $request->digital_address,
                'location' => $request->location,
            ];

            $user->update($updateData);

            // Update profile completion
            $this->updateProfileCompletion($user);
            
            $profileCompletion = $this->calculateProfileCompletion($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Personal information updated successfully',
                'profile_completion' => $profileCompletion,
                'user' => [
                    'username' => $user->username,
                    'name' => $user->name,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update developer personal info: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update personal information. Please try again.'
            ], 500);
        }
    }

    /**
     * Update contact information for developer profile
     */
    public function updateDeveloperContact(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|unique:users,phone,' . $user->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $updateData = [];
            $requiresVerification = [];

            // Handle email change
            if ($request->email !== $user->email) {
                $updateData['email'] = $request->email;
                $updateData['email_verified_at'] = null;
                $requiresVerification[] = 'email';
            }

            // Handle phone change
            if ($request->phone !== $user->phone) {
                $updateData['phone'] = $request->phone;
                $updateData['phone_verified_at'] = null;
                $requiresVerification[] = 'phone';
                
                // Send verification code for new phone number
                $this->sendPhoneVerificationCode($user, $request->phone);
            }

            if (!empty($updateData)) {
                $user->update($updateData);

                // Update profile completion
                $this->updateProfileCompletion($user);
                
                $profileCompletion = $this->calculateProfileCompletion($user);

                DB::commit();

                $successMessage = 'Contact information updated successfully!';
                
                if (!empty($requiresVerification)) {
                    $successMessage .= ' Please verify your ' . implode(' and ', $requiresVerification) . '.';
                }

                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'profile_completion' => $profileCompletion
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'No changes detected.',
                'profile_completion' => $this->calculateProfileCompletion($user)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update developer contact info: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update contact information. Please try again.'
            ], 500);
        }
    }

    /**
     * Update developer settings
     */
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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
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
                'metadata' => $metadata
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update developer settings: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update developer settings. Please try again.'
            ], 500);
        }
    }

    /**
     * Generate API key for developer
     */
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
                'api_key' => $apiKey,
                'generated_at' => $metadata['api_key_generated_at']
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to generate API key: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate API key. Please try again.'
            ], 500);
        }
    }

    /**
     * Revoke API key for developer
     */
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

            Log::info('API key revoked for developer', [
                'user_id' => $user->id,
                'old_api_key' => substr($oldApiKey, 0, 10) . '...' // Log only first 10 chars for security
            ]);

            return response()->json([
                'success' => true,
                'message' => 'API key revoked successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to revoke API key: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke API key. Please try again.'
            ], 500);
        }
    }

    /**
     * Developer dashboard
     */
    public function developerDashboard()
    {
        $user = Auth::user();
        
        // Get developer-specific stats
        $stats = [
            'api_usage' => $this->getApiUsageStats($user),
            'system_status' => $this->getSystemStatus(),
            'recent_activity' => $this->getRecentDeveloperActivity($user),
        ];

        return view('developer.dashboard', compact('user', 'stats'));
    }

    /**
     * Developer tools page
     */
    public function developerTools()
    {
        $user = Auth::user();
        
        $tools = [
            'api_playground' => [
                'name' => 'API Playground',
                'description' => 'Test API endpoints interactively',
                'enabled' => true
            ],
            'webhook_tester' => [
                'name' => 'Webhook Tester',
                'description' => 'Test and debug webhooks',
                'enabled' => true
            ],
            'log_viewer' => [
                'name' => 'Log Viewer',
                'description' => 'View application logs in real-time',
                'enabled' => $user->hasRole('admin') // Only for admins
            ],
            'database_explorer' => [
                'name' => 'Database Explorer',
                'description' => 'Browse database schema and data',
                'enabled' => $user->hasRole('admin') // Only for admins
            ]
        ];

        return view('developer.tools', compact('user', 'tools'));
    }

    /**
     * Get detailed developer stats
     */
    public function getDeveloperStats()
    {
        $user = Auth::user();
        
        $stats = $this->getDetailedDeveloperStats($user);

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'last_updated' => now()->toISOString()
        ]);
    }

    /**
     * Download developer data
     */
    public function downloadDeveloperData()
    {
        $user = Auth::user();

        try {
            $developerData = $this->compileDeveloperData($user);

            $jsonData = json_encode($developerData, JSON_PRETTY_PRINT);
            
            $fileName = 'developer_data_' . $user->id . '_' . date('Y-m-d_H-i-s') . '.json';

            return response($jsonData, 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to compile developer data: ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to compile developer data. Please try again.'
            ], 500);
        }
    }

    // ==================== SHARED METHODS (ALL USER TYPES) ====================

    /**
     * Update the current user's profile (legacy method)
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        
        $validator = $this->validateProfileUpdate($request, $user);
        
        if ($validator->fails()) {
            return $this->handleValidationResponse($validator, $request);
        }

        DB::beginTransaction();

        try {
            $updateData = $this->prepareProfileUpdateData($request, $user);
            $changes = [];
            
            // Track changes for notification
            foreach ($updateData as $field => $value) {
                if ($user->$field != $value) {
                    $changes[$field] = [
                        'old' => $user->$field,
                        'new' => $value
                    ];
                }
            }

            // Handle phone number change (requires verification)
            $phoneChanged = false;
            if ($request->filled('phone') && $request->phone !== $user->phone) {
                $updateData['phone'] = $request->phone;
                $updateData['phone_verified_at'] = null;
                $phoneChanged = true;
                $changes['phone'] = [
                    'old' => $this->maskPhone($user->phone),
                    'new' => $this->maskPhone($request->phone)
                ];
                
                // Send verification code for new phone number
                $verificationSent = $this->sendPhoneVerificationCode($user, $request->phone);
                
                Log::info("Phone number changed, verification required", [
                    'user_id' => $user->id,
                    'old_phone' => $this->maskPhone($user->phone),
                    'new_phone' => $this->maskPhone($request->phone),
                    'verification_sent' => $verificationSent
                ]);
            }

            // Handle email change (requires verification)
            $emailChanged = false;
            if ($request->filled('email') && $request->email !== $user->email) {
                $updateData['email'] = $request->email;
                $updateData['email_verified_at'] = null;
                $emailChanged = true;
                $changes['email'] = [
                    'old' => $user->email,
                    'new' => $request->email
                ];
                
                // Send email verification notification
                $this->sendEmailVerificationNotification($user, $request->email);
            }

            // Only update if there are changes
            if (!empty($updateData)) {
                $user->update($updateData);
                
                Log::info("User profile updated successfully", [
                    'user_id' => $user->id,
                    'changes' => $changes
                ]);
            }

            // Update profile completion
            $this->updateProfileCompletion($user);

            // Log profile changes
            if (!empty($changes)) {
                $this->logProfileChanges($user, $changes);
            }

            DB::commit();

            $successMessage = 'Profile updated successfully!';
            
            if ($phoneChanged) {
                $successMessage .= ' Please verify your new phone number.';
            }
            
            if ($emailChanged) {
                $successMessage .= ' Please verify your new email address.';
            }

            return $this->handleSuccessResponse($request, $successMessage, 'profile');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update profile for user ' . $user->id . ': ' . $e->getMessage(), [
                'error_trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['photo', '_token', '_method'])
            ]);

            return $this->handleErrorResponse($request, 'Failed to update profile. Please try again.');
        }
    }

    /**
     * ✅ FIXED: Update current user's profile photo with proper preview support
     */
    public function updateProfilePhoto(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ], [
            'photo.required' => 'Please select a photo to upload',
            'photo.image' => 'The file must be an image',
            'photo.mimes' => 'The image must be a JPEG, PNG, JPG, GIF, or WEBP file',
            'photo.max' => 'The image size must not exceed 5MB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $oldPhoto = $user->photo;
            
            // Delete old photo if exists
            if ($oldPhoto) {
                $this->deletePhoto($oldPhoto);
            }

            // Handle photo upload using trait method
            $photoPath = $this->handlePhotoUpload($request->file('photo'));
            
            // Update user with new photo path
            $user->update([
                'photo' => $photoPath
            ]);

            // Generate proper URLs for response
            $photoUrl = Storage::disk('public')->url('users/photos/' . $photoPath);
            
            // Update profile completion using trait method
            $this->updateProfileCompletion($user);
            
            // Get updated completion percentage
            $profileCompletion = $this->calculateProfileCompletion($user);

            DB::commit();

            // Return comprehensive response data
            return response()->json([
                'success' => true,
                'message' => 'Profile photo updated successfully!',
                'photo' => $photoPath,
                'photo_url' => $photoUrl,
                'avatar_url' => $photoUrl,
                'profile_completion' => $profileCompletion,
                'user' => [
                    'photo' => $photoPath,
                    'photo_url' => $photoUrl,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update profile photo for user ' . $user->id . ': ' . $e->getMessage(), [
                'error_trace' => $e->getTraceAsString(),
                'file_name' => $request->file('photo')?->getClientOriginalName(),
                'file_size' => $request->file('photo')?->getSize()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile photo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove current user's profile photo
     * Handles both POST and DELETE methods
     */
    public function removeProfilePhoto(Request $request)
    {
        $user = Auth::user();

        // Log the request method for debugging
        Log::info('Remove profile photo request', [
            'user_id' => $user->id,
            'method' => $request->method(),
            'has_photo' => !is_null($user->photo)
        ]);

        if (!$user->photo) {
            $message = 'No profile photo to remove.';
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 400);
            }
            
            return back()->with('info', $message);
        }

        DB::beginTransaction();

        try {
            $oldPhoto = $user->photo;
            $this->deletePhoto($oldPhoto);
            
            // Update user record
            $user->photo = null;
            $user->save();

            // Update profile completion stats
            $this->updateProfileCompletion($user);

            DB::commit();

            // Refresh user to get updated URLs
            $user->refresh();

            // Get updated profile completion using trait method
            $profileCompletion = $this->calculateProfileCompletion($user);

            // Log successful removal
            Log::info("Profile photo removed successfully", [
                'user_id' => $user->id,
                'removed_photo' => $oldPhoto,
                'profile_completion' => $profileCompletion
            ]);

            $defaultAvatar = asset('images/default-avatar.png');

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Profile photo removed successfully!',
                    'photo' => null,
                    'photo_url' => $defaultAvatar,
                    'avatar_url' => $defaultAvatar,
                    'profile_completion' => $profileCompletion,
                    'user' => [
                        'photo' => null,
                        'photo_url' => $defaultAvatar,
                        'avatar_url' => $defaultAvatar,
                    ]
                ]);
            }

            return back()->with('success', 'Profile photo removed successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove profile photo for user ' . $user->id . ': ' . $e->getMessage());

            $errorMessage = 'Failed to remove profile photo. Please try again.';
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 500);
            }

            return back()->with('error', $errorMessage);
        }
    }

    /**
 * Send phone verification code
 */
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
        
        // Format the phone number properly (NOW INCLUDES +)
        $formattedPhone = $this->formatPhoneNumberForSms($user->phone);
        
        // Remove + for logging (privacy)
        $logPhone = '+' . substr(preg_replace('/[^0-9]/', '', $formattedPhone), -9);
        
        $message = "Your phone verification code for " . config('app.name') . " is: {$verificationCode}. Valid for 10 minutes.";
        
        Log::info('Attempting to send verification SMS', [
            'user_id' => $user->id,
            'original_phone' => $user->phone,
            'formatted_phone' => $logPhone,
            'code' => $verificationCode
        ]);
        
        // Send with default provider
        $smsResult = $this->smsService->sendWithDefaultProvider(
            $formattedPhone,  // Now has + prefix
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
        } else {
            Log::error('SMS sending failed', [
                'user_id' => $user->id,
                'error' => $smsResult['message'] ?? 'Unknown error',
                'provider_used' => $smsResult['provider'] ?? 'unknown'
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code: ' . ($smsResult['message'] ?? 'Unknown error')
            ], 500);
        }
        
    } catch (\Exception $e) {
        Log::error('Failed to send verification code: ' . $user->id . ': ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to send verification code. Please try again.'
        ], 500);
    }
}

    /**
 * Format phone number for SMS sending
 */
private function formatPhoneNumberForSms($phone): string
{
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // If it's 9 digits (local Ghana number without prefix)
    if (strlen($phone) === 9) {
        return '+233' . $phone;  // Add + prefix
    }
    
    // If it's 10 digits starting with 0 (e.g., 0595652410)
    if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
        return '+233' . substr($phone, 1);  // Add + prefix
    }
    
    // If it's already 12 digits starting with 233
    if (strlen($phone) === 12 && substr($phone, 0, 3) === '233') {
        return '+' . $phone;  // Add + prefix
    }
    
    // If it's 13 digits with + (e.g., +233595652410)
    if (strlen($phone) === 13 && substr($phone, 0, 1) === '+') {
        return $phone;  // Keep as is
    }
    
    // Default: ensure + prefix for international format
    return '+' . $phone;
}

    /**
     * Verify phone number with code
     */
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
            // Check if code matches and is not expired (10 minutes)
            if ($user->phone_verification_code === $request->verification_code && 
                $user->phone_verification_sent_at && 
                $user->phone_verification_sent_at->diffInMinutes(now()) < 10) {
                
                $user->update([
                    'phone_verified_at' => now(),
                    'phone_verification_code' => null,
                    'phone_verification_sent_at' => null
                ]);
                
                // Update profile completion
                $this->updateProfileCompletion($user);
                
                Log::info("Phone number verified successfully", [
                    'user_id' => $user->id,
                    'phone' => $this->maskPhone($user->phone)
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Phone number verified successfully!',
                    'profile_completion' => $this->calculateProfileCompletion($user),
                    'reload' => true
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification code. Please try again.'
                ], 400);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to verify phone: ' . $user->id . ': ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify phone number. Please try again.'
            ], 500);
        }
    }

    /**
     * Get current user's comprehensive profile statistics
     */
    public function getProfileStats()
    {
        $user = Auth::user();
        
        $stats = $this->getDetailedProfileStats($user);

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'last_updated' => now()->toISOString()
        ]);
    }

    /**
     * Download user's personal data (GDPR compliance)
     */
    public function downloadPersonalData()
    {
        $user = Auth::user();

        try {
            $personalData = $this->compilePersonalData($user);

            $jsonData = json_encode($personalData, JSON_PRETTY_PRINT);
            
            $fileName = 'personal_data_' . $user->id . '_' . date('Y-m-d_H-i-s') . '.json';

            return response($jsonData, 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to compile personal data for user ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to compile personal data. Please try again.'
            ], 500);
        }
    }

    // ==================== PUBLIC/PROTECTED HELPER METHODS ====================

    /**
     * Update profile completion in metadata (now using trait)
     * This method is kept for backward compatibility but now uses the trait
     */
    public function updateProfileCompletionInMetadata(User $user): void
    {
        $this->updateProfileCompletion($user);
    }


    /**
     * Calculate field agent completion rate
     */
    private function calculateFieldAgentCompletionRate(User $user): float
    {
        $totalAssignments = $user->assignedPlans()->count();
        $completedAssignments = $user->assignedPlans()->where('status', 'completed')->count();
        
        return $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100, 2) : 0;
    }

    /**
     * Get field label for display
     */
    private function getFieldLabelForDisplay(string $field): string
    {
        $labels = [
            'name' => 'Full Name',
            'email' => 'Email Address',
            'phone' => 'Phone Number',
            'digital_address' => 'Digital Address',
            'region' => 'Region',
            'location' => 'Location',
            'gender' => 'Gender',
            'dob' => 'Date of Birth',
            'photo' => 'Profile Photo',
            'username' => 'Username',
            'security_id' => 'Security ID',
            'security_location' => 'Security Location',
            'notifications' => 'Notification Settings',
            'specializations' => 'Specializations',
        ];

        return $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Check if password is in history
     */
    private function isPasswordInHistory(User $user, string $newPassword): bool
    {
        $passwordHistory = $user->metadata['password_history'] ?? [];
        $historyLimit = 5; // Keep last 5 passwords

        foreach (array_slice($passwordHistory, -$historyLimit) as $oldPasswordHash) {
            if (Hash::check($newPassword, $oldPasswordHash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add password to history
     */
    private function addPasswordToHistory(User $user, string $oldPasswordHash): void
    {
        $metadata = $user->metadata ?? [];
        $passwordHistory = $metadata['password_history'] ?? [];
        
        // Add to history and keep only last 5
        $passwordHistory[] = $oldPasswordHash;
        $metadata['password_history'] = array_slice($passwordHistory, -5);
        
        $user->update(['metadata' => $metadata]);
    }

    /**
     * Calculate password strength
     */
    private function calculatePasswordStrength(string $password): string
    {
        $score = 0;
        
        // Length check
        if (strlen($password) >= 8) $score++;
        if (strlen($password) >= 12) $score++;
        
        // Complexity checks
        if (preg_match('/[a-z]/', $password)) $score++;
        if (preg_match('/[A-Z]/', $password)) $score++;
        if (preg_match('/[0-9]/', $password)) $score++;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $score++;
        
        if ($score >= 6) return 'strong';
        if ($score >= 4) return 'medium';
        return 'weak';
    }

    /**
     * Log profile changes
     */
    private function logProfileChanges(User $user, array $changes, string $type = 'profile'): void
    {
        Log::info("User {$type} updated", [
            'user_id' => $user->id,
            'changes' => $changes,
            'updated_at' => now()->toDateTimeString()
        ]);

        // Store in user metadata for audit trail
        $metadata = $user->metadata ?? [];
        $changeLog = $metadata['change_log'] ?? [];
        
        $changeLog[] = [
            'type' => $type,
            'changes' => $changes,
            'timestamp' => now()->toDateTimeString(),
            'ip' => request()->ip()
        ];
        
        // Keep only last 50 changes
        $metadata['change_log'] = array_slice($changeLog, -50);
        $user->update(['metadata' => $metadata]);
    }

    /**
     * Compile personal data for download
     */
    private function compilePersonalData(User $user): array
    {
        return [
            'personal_information' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'username' => $user->username,
                'gender' => $user->gender,
                'date_of_birth' => $user->dob,
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

    

    /**
     * Get system status for developer dashboard
     */
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

    /**
     * Get recent developer activity
     */
    private function getRecentDeveloperActivity(User $user): array
    {
        $metadata = $user->metadata ?? [];
        $activityLog = $metadata['activity_log'] ?? [];
        
        return array_slice($activityLog, -10); // Last 10 activities
    }

    /**
     * Get detailed developer statistics
     */
    private function getDetailedDeveloperStats(User $user): array
    {
        $stats = $this->getDetailedProfileStats($user);
        
        $metadata = $user->metadata ?? [];
        
        // Add developer-specific stats
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
        ];
        
        return $stats;
    }

    /**
     * Compile developer-specific data for download
     */
    private function compileDeveloperData(User $user): array
    {
        $personalData = $this->compilePersonalData($user);
        
        $metadata = $user->metadata ?? [];
        
        // Add developer-specific data
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

    // ==================== SYSTEM HELPER METHODS ====================

    /**
     * Check database connection
     */
    private function checkDatabaseConnection(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'connected', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'disconnected', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check cache connection
     */
    private function checkCacheConnection(): array
    {
        try {
            cache()->put('test_connection', 'test', 1);
            return ['status' => 'connected', 'message' => 'Cache connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'disconnected', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check storage connection
     */
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

    /**
     * Get system uptime (mock implementation)
     */
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

    // ==================== EXISTING HELPER METHODS ====================

    /**
     * Send email verification notification
     */
    private function sendEmailVerificationNotification(User $user, string $newEmail): void
    {
        Log::info("Email verification required for new address", [
            'user_id' => $user->id,
            'old_email' => $user->email,
            'new_email' => $newEmail
        ]);
    }

    /**
     * Validate profile update request
     */
    private function validateProfileUpdate(Request $request, User $user)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|max:20|unique:users,phone,' . $user->id,
            'gender' => 'nullable|string|in:male,female,other',
            'dob' => 'nullable|date|before:today|after:1900-01-01',
            'digital_address' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'remove_photo' => 'sometimes|boolean',
        ];

        // Type-specific validations
        switch ($user->type) {
            case User::TYPE_FIELD_AGENT:
                $rules['username'] = 'required|string|max:100|unique:users,username,' . $user->id;
                break;
                
            case User::TYPE_SECURITY_PERSONNEL:
                $rules['username'] = 'nullable|string|max:255|unique:users,username,' . $user->id;
                break;
        }

        return Validator::make($request->all(), $rules, [
            'phone.unique' => 'This phone number is already registered with another account.',
            'email.unique' => 'This email address is already registered with another account.',
            'dob.before' => 'Date of birth must be in the past.',
            'dob.after' => 'Date of birth must be after 1900.',
        ]);
    }

    /**
     * Prepare profile update data
     */
    private function prepareProfileUpdateData(Request $request, User $user)
    {
        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'gender' => $request->gender,
            'dob' => $request->dob,
            'digital_address' => $request->digital_address,
            'region' => $request->region,
            'location' => $request->location,
        ];

        // Type-specific fields
        switch ($user->type) {
            case User::TYPE_FIELD_AGENT:
                $data['username'] = $request->username;
                break;
                
            case User::TYPE_SECURITY_PERSONNEL:
                $data['username'] = $request->username;
                break;
        }

        return $data;
    }


    /**
     * Send password change notification to user
     */
    private function sendPasswordChangeNotificationToUser(User $user)
    {
        try {
            $message = "Hello {$user->name}! Your password has been changed successfully. " .
                      "If you did not make this change, please contact support immediately.";

            if ($user->phone) {
                $formattedPhone = $this->formatPhoneNumberForSms($user->phone);
                
                // ✅ FIXED: Correct parameter order
                $this->smsService->sendWithDefaultProvider(
                    $formattedPhone,
                    $message,
                    [
                        'is_test' => false,
                        'type' => 'password_change_notification',
                        'user_id' => $user->id
                    ]
                );
            }

            Log::info("Password change notification sent to user", [
                'user_id' => $user->id
            ]);

        } catch (\Exception $e) {
            Log::warning("Failed to send password change notification to user: " . $e->getMessage(), [
                'user_id' => $user->id
            ]);
        }
    }

    /**
     * Mask phone number for display
     */
    private function maskPhone($phone): string
    {
        if (empty($phone) || strlen($phone) <= 6) {
            return $phone ?? '';
        }
        
        return substr($phone, 0, 3) . '****' . substr($phone, -3);
    }

    /**
     * Handle success response consistently
     */
    private function handleSuccessResponse(Request $request, string $message, string $type = 'general', array $additionalData = [])
    {
        $user = Auth::user();
        $responseData = [
            'success' => true,
            'message' => $message,
            'type' => $type
        ];

        // Add profile completion for profile-related updates
        if (in_array($type, ['profile', 'personal_info', 'contact_info', 'password', 'photo'])) {
            $responseData['profile_completion'] = $this->calculateProfileCompletion($user);
        }

        // Merge additional data
        $responseData = array_merge($responseData, $additionalData);

        if ($request->expectsJson()) {
            return response()->json($responseData);
        }

        return back()->with('success', $message);
    }

    /**
     * Handle error response consistently
     */
    private function handleErrorResponse(Request $request, string $message, array $errors = [])
    {
        $responseData = [
            'success' => false,
            'message' => $message
        ];

        if (!empty($errors)) {
            $responseData['errors'] = $errors;
        }

        if ($request->expectsJson()) {
            return response()->json($responseData, 500);
        }

        return back()->with('error', $message)->withInput();
    }

    /**
     * Handle validation response consistently
     */
    private function handleValidationResponse($validator, Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        return back()
            ->withErrors($validator)
            ->withInput()
            ->with('error', 'Please fix the validation errors below.');
    }

    /**
     * Send phone verification code for new phone number
     */
    private function sendPhoneVerificationCode(User $user, $newPhone): bool
    {
        try {
            $verificationCode = $this->generatePhoneVerificationCode($user);

            $message = "Your phone verification code for " . config('app.name') . " is: {$verificationCode}. Valid for 10 minutes.";
            
            $formattedPhone = $this->formatPhoneNumberForSms($newPhone);
            
            // ✅ FIXED: Correct parameter order
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
                Log::info("Phone verification code sent for number change", [
                    'user_id' => $user->id,
                    'old_phone' => $this->maskPhone($user->phone),
                    'new_phone' => $this->maskPhone($newPhone),
                    'provider' => $smsResult['provider'] ?? 'unknown'
                ]);
                return true;
            } else {
                Log::error("Failed to send verification code for number change", [
                    'user_id' => $user->id,
                    'error' => $smsResult['message'] ?? 'Unknown error'
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error("Failed to send verification code for number change: " . $e->getMessage(), [
                'user_id' => $user->id,
                'new_phone' => $this->maskPhone($newPhone)
            ]);
            return false;
        }
    }

    /**
     * Generate phone verification code
     */
    private function generatePhoneVerificationCode(User $user): string
    {
        $code = sprintf('%06d', random_int(1, 999999));
        
        $user->update([
            'phone_verification_code' => $code,
            'phone_verification_sent_at' => now(),
        ]);

        return $code;
    }

    /**
     * Photo upload handler
     */
    private function handlePhotoUpload($photoFile): string
    {
        try {
            // Validate file exists and is valid
            if (!$photoFile || !$photoFile->isValid()) {
                throw new \Exception('Invalid photo file provided.');
            }

            $disk = 'public';
            $directory = 'users/photos';

            // Create directory if it doesn't exist with proper permissions
            if (!Storage::disk($disk)->exists($directory)) {
                Storage::disk($disk)->makeDirectory($directory, 0755, true);
            }

            // Generate unique filename with proper extension
            $extension = strtolower($photoFile->getClientOriginalExtension());
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                throw new \Exception('Invalid image format.');
            }

            $filename = 'user_' . time() . '_' . \Illuminate\Support\Str::random(10) . '_' . Auth::id() . '.' . $extension;
            $fullPath = $directory . '/' . $filename;

            // Store the file
            Storage::disk($disk)->putFileAs($directory, $photoFile, $filename);

            // Verify the file was actually stored
            if (!Storage::disk($disk)->exists($fullPath)) {
                throw new \Exception('Failed to store photo file after upload.');
            }

            Log::info("Photo uploaded successfully", [
                'user_id' => Auth::id(),
                'filename' => $filename,
                'path' => $fullPath,
            ]);

            return $filename;

        } catch (\Exception $e) {
            Log::error('Photo upload failed: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'original_name' => $photoFile->getClientOriginalName(),
                'file_size' => $photoFile->getSize(),
            ]);
            throw new \Exception('Failed to upload photo: ' . $e->getMessage());
        }
    }

    /**
     * Enhanced photo deletion with better error handling
     */
    private function deletePhoto($photoPath): bool
    {
        if (!$photoPath) {
            return true;
        }

        try {
            $disk = 'public';
            $directory = 'users/photos';
            $fullPath = $directory . '/' . $photoPath;
            
            // Check if file exists before attempting deletion
            if (Storage::disk($disk)->exists($fullPath)) {
                $deleted = Storage::disk($disk)->delete($fullPath);
                
                if ($deleted) {
                    Log::info("Photo deleted successfully from storage", [
                        'photo_path' => $photoPath,
                        'full_path' => $fullPath
                    ]);
                    return true;
                } else {
                    Log::warning("Photo file exists but could not be deleted", [
                        'photo_path' => $photoPath
                    ]);
                    return false;
                }
            } else {
                Log::info("Photo file not found in storage (may have been already deleted)", [
                    'photo_path' => $photoPath
                ]);
                return true; // Consider success if file doesn't exist
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to delete photo from storage: ' . $e->getMessage(), [
                'photo_path' => $photoPath,
            ]);
            return false;
        }
    }

    /**
 * Update contact information for landlord
 */
public function updateLandlordContact(Request $request)
{
    $user = Auth::user();
    
    $validator = Validator::make($request->all(), [
        'email' => 'required|email|unique:users,email,' . $user->id,
        'phone' => 'required|string|max:20|unique:users,phone,' . $user->id,
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    DB::beginTransaction();

    try {
        $updateData = [];
        $requiresVerification = [];

        // Handle email change
        if ($request->email !== $user->email) {
            $updateData['email'] = $request->email;
            $updateData['email_verified_at'] = null;
            $requiresVerification[] = 'email';
        }

        // Handle phone change
        if ($request->phone !== $user->phone) {
            $updateData['phone'] = $request->phone;
            $updateData['phone_verified_at'] = null;
            $requiresVerification[] = 'phone';
            
            // Send verification code for new phone number
            $this->sendPhoneVerificationCode($user, $request->phone);
        }

        if (!empty($updateData)) {
            $user->update($updateData);

            // Update profile completion
            $this->updateProfileCompletion($user);
            
            $profileCompletion = $this->calculateProfileCompletion($user);

            DB::commit();

            $successMessage = 'Contact information updated successfully!';
            
            if (!empty($requiresVerification)) {
                $successMessage .= ' Please verify your ' . implode(' and ', $requiresVerification) . '.';
            }

            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'profile_completion' => $profileCompletion,
                'requires_verification' => $requiresVerification
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'No changes detected.',
            'profile_completion' => $this->calculateProfileCompletion($user)
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to update landlord contact info: ' . $user->id . ': ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to update contact information. Please try again.'
        ], 500);
    }
}
    
}