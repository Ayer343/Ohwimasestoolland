<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\StoreSecurityPersonnelRequest;
use App\Models\User;
use App\Models\SecurityPost;
use App\Models\SecuritySupervisorAssignment;
use App\Models\SecuritySchedule;
use App\Models\UserInvitation;
use App\Repositories\UserRepository;
use App\Services\Security\SecurityPersonnelService;
use App\Services\UserInvitationService;
use App\Services\MultiChannelInvitationService;
use App\Events\UserCreated;
use App\Events\UserDeleted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class SecurityPersonnelAssignmentController extends Controller
{
    protected UserRepository $userRepository;
    protected SecurityPersonnelService $personnelService;
    protected UserInvitationService $invitationService;
    protected MultiChannelInvitationService $multiChannelService;

    // Configurable pagination settings
    protected const DEFAULT_PER_PAGE = 15;
    protected const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    public function __construct(
        UserRepository $userRepository,
        SecurityPersonnelService $personnelService,
        UserInvitationService $invitationService,
        MultiChannelInvitationService $multiChannelService
    ) {
        $this->userRepository = $userRepository;
        $this->personnelService = $personnelService;
        $this->invitationService = $invitationService;
        $this->multiChannelService = $multiChannelService;
    }

    // ==================== MAIN CRUD METHODS ====================

    /**
     * Display eligible security personnel for supervisor assignment
     */
    public function index(Request $request)
    {
        $currentUser = Auth::user();
        
        if (!$this->canManageSecurityPersonnel($currentUser)) {
            return redirect()->route('security.dashboard')
                ->with('error', 'You do not have permission to view security personnel.');
        }

        $tab = $request->get('tab', 'assigned');
        $personnel = $this->getScopedSecurityPersonnel($currentUser, $request, $tab);
        $posts = $this->getAccessiblePosts($currentUser);
        $allPosts = SecurityPost::active()->orderBy('name')->get();
        $stats = $this->getPersonnelStatistics($currentUser);
        
        $unassignedCount = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('id', '!=', $currentUser->id)
            ->whereDoesntHave('supervisorAssignments', function($q) {
                $q->where('is_active', true);
            })
            ->count();

        $invitationStats = $this->getInvitationStatistics($currentUser);

        $trashStats = [
            'total_trashed' => User::onlyTrashed()->where('type', User::TYPE_SECURITY_PERSONNEL)->count(),
            'trashed_this_week' => User::onlyTrashed()
                ->where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('deleted_at', '>=', now()->subWeek())
                ->count(),
            'trashed_this_month' => User::onlyTrashed()
                ->where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('deleted_at', '>=', now()->subMonth())
                ->count(),
            'old_deleted_count' => User::onlyTrashed()
                ->where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('deleted_at', '<', now()->subDays(30))
                ->count(),
        ];

        return view('security.personnel.index', compact(
            'personnel', 'posts', 'stats', 'tab', 'unassignedCount',
            'allPosts', 'invitationStats', 'trashStats'
        ));
    }

    /**
     * Show form to create new security personnel
     */
    public function create()
    {
        $currentUser = Auth::user();
        
        if (!$this->canManageSecurityPersonnel($currentUser)) {
            return redirect()->route('security.dashboard')
                ->with('error', 'You do not have permission to add security personnel.');
        }

        $posts = $this->getAccessiblePosts($currentUser);
        $statuses = User::getUserStatuses();
        
        // ✅ UPDATED: Only eligibility toggle, no levels
        $assignableLevels = [
            1 => 'Team Lead (Level 1)',
            2 => 'Section Lead (Level 2)',
        ];

        $communicationStatus = $this->getCommunicationServiceStatus();
        $smsStatus = $communicationStatus['sms'] ?? [];
        $whatsappStatus = $communicationStatus['whatsapp'] ?? [];
        $emailStatus = $communicationStatus['email'] ?? [];
        $multiChannelStatus = $communicationStatus['multi_channel'] ?? [];
        
        $smsStatus['system_ready'] = $smsStatus['system_ready'] ?? ($smsStatus['enabled'] ?? false);
        $whatsappStatus['system_ready'] = $whatsappStatus['system_ready'] ?? ($whatsappStatus['enabled'] ?? false);
        $emailStatus['system_ready'] = $emailStatus['system_ready'] ?? ($emailStatus['enabled'] ?? false);

        $invitationTemplates = $this->getInvitationTemplates();

        return view('security.personnel.create', compact(
            'posts', 'statuses', 'assignableLevels',
            'smsStatus', 'whatsappStatus', 'emailStatus', 'multiChannelStatus',
            'invitationTemplates'
        ));
    }

    /**
     * Store new security personnel
     */
    public function store(StoreSecurityPersonnelRequest $request)
    {
        Log::info('🔍 ========== STORE METHOD - START ==========');
        
        try {
            $currentUser = Auth::user();
            
            Log::info('🔍 Store method - current user', [
                'user_id' => $currentUser->id ?? 'null',
                'user_name' => $currentUser->name ?? 'null',
                'user_type' => $currentUser->type ?? 'null',
            ]);
            
            Log::info('🔍 Store method - request data', $request->all());

            if (!$this->canManageSecurityPersonnel($currentUser)) {
                Log::warning('❌ Permission denied in store method');
                return redirect()->route('security.dashboard')
                    ->with('error', 'You do not have permission to add security personnel.');
            }
            Log::info('✅ Permission check passed');

            // ✅ Post accessibility check
            $postId = null;
            if ($request->security_post_id) {
                Log::info('🔍 CHECK 2: Post accessibility check', [
                    'security_post_id' => $request->security_post_id,
                ]);
                
                $accessiblePosts = $this->getAccessiblePosts($currentUser)->pluck('id')->toArray();
                
                if (!in_array((int)$request->security_post_id, $accessiblePosts)) {
                    Log::warning('❌ Post not accessible');
                    return redirect()->back()
                        ->with('error', 'You do not have access to the selected post.')
                        ->withInput();
                }
                Log::info('✅ Post accessibility check passed');
                $postId = (int)$request->security_post_id;
            }

            DB::beginTransaction();

            try {
                $userData = $request->validated();
                $userData['type'] = User::TYPE_SECURITY_PERSONNEL;
                $userData['created_by'] = $currentUser->id;
                $userData['status'] = $request->status ?? User::STATUS_PENDING;
                
                // ✅ UPDATED: Only set can_be_supervisor flag
                $userData['can_be_supervisor'] = $request->boolean('can_be_supervisor', false);
                // ❌ REMOVED: supervisor_level, supervisor_score, supervisor_certifications
                
                $user = $this->userRepository->createUser($userData, $currentUser);
                
                Log::info('✅ User created', [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'can_be_supervisor' => $user->can_be_supervisor,
                ]);

                // ✅ Separate POST assignment from SUPERVISOR ROLE assignment
            
                // 1. Assign to post (if requested) - WITHOUT auto-creating schedule
                if ($postId) {
                    $this->assignPersonnelToPost($user, $postId, $currentUser);
                    Log::info('✅ Personnel assigned to post (NO schedule auto-created)', [
                        'user_id' => $user->id,
                        'post_id' => $postId,
                    ]);
                }

                // 2. Assign supervisor role (if requested) - WITHOUT auto-post assignment
                $supervisorAssignment = null;
                if ($request->boolean('assign_supervisor') && $request->supervisor_level) {
                    // ✅ IMPORTANT: Pass null for security_post_id to prevent auto-post assignment
                    // The supervisor role is assigned independently of any post
                    $supervisorAssignment = $this->assignSupervisorRole(
                        $user, 
                        $currentUser, 
                        (int)$request->supervisor_level,
                        null,  // ← NO post assignment! Role only.
                        $request->start_date,
                        $request->end_date,
                        $request->boolean('is_primary_supervisor', false)
                    );
                    
                    Log::info('✅ Supervisor role assigned (NO post)', [
                        'user_id' => $user->id,
                        'level' => $request->supervisor_level,
                        'assignment_id' => $supervisorAssignment->id ?? null,
                    ]);
                }

                // Send invitation
                $invitationResult = null;
                if ($request->boolean('send_invitation')) {
                    $invitationData = [
                        'invitation_channels' => $request->input('invitation_channels', ['email']),
                        'invitation_type' => $request->input('invitation_type', 'security_welcome'),
                        'custom_message' => $request->input('custom_message'),
                        'expires_in_days' => $request->input('expires_in_days', 7),
                        'template' => $request->input('template', 'security_personnel_welcome'),
                    ];
                    
                    $invitationResult = $this->invitationService->sendInvitation($user, $invitationData);
                    
                    if ($invitationResult['success']) {
                        $user->update(['invitation_sent_at' => now()]);
                        Log::info('✅ Security personnel invitation sent', [
                            'user_id' => $user->id,
                            'channels' => $invitationResult['channels_successful'] ?? [],
                        ]);
                    } else {
                        Log::warning('⚠️ Security personnel invitation failed', [
                            'user_id' => $user->id,
                            'error' => $invitationResult['message'] ?? 'Unknown error',
                        ]);
                    }
                }

                DB::commit();

                event(new UserCreated($user, $currentUser, $request->validated()));

                $message = 'Security personnel created successfully!';
                
                if ($supervisorAssignment) {
                    $levelNames = ['', 'Team Lead', 'Section Lead', 'Post Commander'];
                    $message .= " User assigned as {$levelNames[(int)$request->supervisor_level]}.";
                }
                
                if ($postId) {
                    $post = SecurityPost::find($postId);
                    $postName = $post ? $post->name : 'Unknown Post';
                    $message .= " Assigned to post: {$postName}.";
                }
                
                if ($invitationResult && $invitationResult['success']) {
                    $channels = implode(', ', $invitationResult['channels_successful'] ?? []);
                    $message .= " Invitation sent via {$channels}.";
                }

                // ✅ Add note about no auto-schedule
                if ($postId) {
                    $message .= " (No schedule automatically created - please create schedules separately.)";
                }

                return redirect()->route('security.personnel.index')
                    ->with('success', $message)
                    ->with('created_user_id', $user->id)
                    ->with('invitation_result', $invitationResult)
                    ->with('supervisor_assignment', $supervisorAssignment);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('❌ Database error in store method: ' . $e->getMessage(), [
                    'user_data' => $request->validated(),
                    'error_trace' => $e->getTraceAsString(),
                    'user_id' => $currentUser->id
                ]);

                return redirect()->back()
                    ->with('error', 'Failed to create security personnel: ' . $e->getMessage())
                    ->withInput();
            }
            
        } catch (\Exception $e) {
            Log::error('❌ Fatal error in store method: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->back()
                ->with('error', 'An unexpected error occurred: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show form to edit security personnel
     */
    public function edit($userId)
    {
        $currentUser = Auth::user();
        
        if (!$this->canManageSecurityPersonnel($currentUser)) {
            return redirect()->route('security.dashboard')
                ->with('error', 'You do not have permission to edit security personnel.');
        }

        $personnel = User::findOrFail($userId);
        $posts = $this->getAccessiblePosts($currentUser);
        $statuses = User::getUserStatuses();
        
        // ✅ UPDATED: Only eligibility toggle, no levels
        $assignableLevels = [
            0 => 'Not a Supervisor',
            1 => 'Team Lead (Level 1)',
            2 => 'Section Lead (Level 2)',
        ];

        $communicationStatus = $this->getCommunicationServiceStatus();
        $smsStatus = $communicationStatus['sms'] ?? [];
        $whatsappStatus = $communicationStatus['whatsapp'] ?? [];
        $emailStatus = $communicationStatus['email'] ?? [];
        $multiChannelStatus = $communicationStatus['multi_channel'] ?? [];
        
        $smsStatus['system_ready'] = $smsStatus['system_ready'] ?? ($smsStatus['enabled'] ?? false);
        $whatsappStatus['system_ready'] = $whatsappStatus['system_ready'] ?? ($whatsappStatus['enabled'] ?? false);
        $emailStatus['system_ready'] = $emailStatus['system_ready'] ?? ($emailStatus['enabled'] ?? false);

        return view('security.personnel.edit', compact(
            'personnel', 'posts', 'statuses', 'assignableLevels',
            'smsStatus', 'whatsappStatus', 'emailStatus', 'multiChannelStatus'
        ));
    }

    /**
     * Update security personnel
     */
    public function update(Request $request, $userId)
    {
        try {
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            $currentUser = Auth::user();
            
            Log::info('🔍 UPDATE METHOD - START', [
                'user_id' => $userId,
                'request_data' => $request->all(),
            ]);
            
            if (!$this->canManageSecurityPersonnel($currentUser)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to update security personnel.'
                ], 403);
            }

            $personnel = User::findOrFail($userId);
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $userId,
                'phone' => 'nullable|string|max:20',
                'username' => 'nullable|string|max:50|unique:users,username,' . $userId,
                'status' => 'required|in:active,inactive,pending,suspended',
                'security_post_id' => 'nullable|exists:security_posts,id',
                'can_be_supervisor' => 'sometimes|boolean',
                // ✅ REMOVED: supervisor_level, supervisor_score
            ]);

            if ($validator->fails()) {
                Log::warning('❌ UPDATE: Validation failed', [
                    'errors' => $validator->errors()->toArray(),
                ]);
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
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'username' => $request->username,
                    'status' => $request->status,
                ];
                
                // ✅ UPDATED: Only set can_be_supervisor flag
                $canBeSupervisor = $request->has('can_be_supervisor') ? $request->boolean('can_be_supervisor') : false;
                $updateData['can_be_supervisor'] = $canBeSupervisor;
                
                // ❌ REMOVED: supervisor_level and supervisor_score updates

                $updated = $personnel->update($updateData);

                if ($request->has('security_post_id')) {
                    $this->updatePersonnelPost($personnel, $request->security_post_id ? (int)$request->security_post_id : null, $currentUser);
                }

                DB::commit();
                
                Log::info('✅ Personnel updated successfully', [
                    'user_id' => $personnel->id,
                    'updated_by' => $currentUser->id,
                    'can_be_supervisor' => $personnel->can_be_supervisor,
                ]);

                $response = [
                    'success' => true,
                    'message' => 'Security personnel updated successfully!',
                    'user' => $personnel->fresh()->toArray(),
                ];

                $json = json_encode($response);
                if (substr($json, 0, 3) === "\xEF\xBB\xBF") {
                    $json = substr($json, 3);
                }
                
                return response($json, 200)
                    ->header('Content-Type', 'application/json')
                    ->header('X-Content-Type-Options', 'nosniff');

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('❌ Database error in update: ' . $e->getMessage(), [
                    'user_id' => $userId,
                    'error_trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Database error: ' . $e->getMessage()
                ], 500);
            }
            
        } catch (\Exception $e) {
            Log::error('❌ Fatal error in update method: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get personnel data for editing (AJAX)
     */
    public function getEditData($userId)
    {
        try {
            Log::info('🔍 getEditData called', [
                'user_id' => $userId,
                'current_user' => Auth::id(),
                'timestamp' => now()->toDateTimeString(),
            ]);
            
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            $currentUser = Auth::user();
            
            if (!$this->canManageSecurityPersonnel($currentUser)) {
                Log::warning('❌ getEditData: Unauthorized', [
                    'user_id' => $userId,
                    'current_user' => $currentUser->id ?? 'null',
                ]);
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $personnel = User::with(['roles', 'supervisorAssignments' => function($q) {
                $q->where('is_active', true);
            }, 'schedules' => function($q) {
                $q->where('status', 'active')->with('securityPost');
            }])->findOrFail($userId);
            
            $securityPostId = null;
            if ($personnel->schedules && $personnel->schedules->isNotEmpty()) {
                $schedule = $personnel->schedules->first();
                if ($schedule->securityPost) {
                    $securityPostId = $schedule->security_post_id;
                }
            } elseif ($personnel->supervisorAssignments && $personnel->supervisorAssignments->isNotEmpty()) {
                $assignment = $personnel->supervisorAssignments->first();
                if ($assignment->securityPost) {
                    $securityPostId = $assignment->security_post_id;
                }
            }

            $roleNames = $personnel->roles->pluck('display_name')->implode(', ');
            $roleSlugs = $personnel->roles->pluck('slug')->toArray();

            $responseData = [
                'id' => $personnel->id,
                'name' => $personnel->name,
                'email' => $personnel->email,
                'phone' => $personnel->phone,
                'username' => $personnel->username,
                'status' => $personnel->status,
                'security_post_id' => $securityPostId,
                'can_be_supervisor' => (bool) $personnel->can_be_supervisor,
                // ✅ REMOVED: supervisor_level, supervisor_score
                'type' => $personnel->type,
                'created_at' => $personnel->created_at ? $personnel->created_at->format('Y-m-d H:i:s') : null,
                'created_by' => $personnel->created_by,
                'roles' => $roleSlugs,
                'role_names' => $roleNames,
                'is_active_supervisor' => $personnel->supervisorAssignments()->where('is_active', true)->exists(),
                'assigned_posts' => $personnel->schedules->map(function($schedule) {
                    return [
                        'id' => $schedule->security_post_id,
                        'name' => $schedule->securityPost->name ?? null,
                    ];
                }),
                'invitation_sent_at' => $personnel->invitation_sent_at,
            ];

            $json = json_encode($responseData);
            
            if (substr($json, 0, 3) === "\xEF\xBB\xBF") {
                $json = substr($json, 3);
            }
            
            return response($json, 200)
                ->header('Content-Type', 'application/json')
                ->header('X-Content-Type-Options', 'nosniff');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('❌ getEditData: Personnel not found', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Personnel not found'], 404);
            
        } catch (\Exception $e) {
            Log::error('❌ getEditData: Error fetching edit data', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Failed to load data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get security personnel details for AJAX
     */
    public function show($userId)
    {
        try {
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            $currentUser = Auth::user();
            
            if (!$this->canManageSecurityPersonnel($currentUser)) {
                Log::warning('❌ Unauthorized: User cannot manage security personnel', [
                    'user_id' => $currentUser->id ?? 'null',
                ]);
                return response()->json(['error' => 'Unauthorized - You do not have permission to view personnel details.'], 403);
            }

            $personnel = User::with(['supervisorAssignments' => function($q) {
                $q->where('is_active', true);
            }, 'schedules' => function($q) {
                $q->where('status', 'active')->with('securityPost');
            }])->findOrFail($userId);

            $postIds = $personnel->supervisorAssignments->pluck('security_post_id')->filter()->unique()->toArray();
            $posts = SecurityPost::whereIn('id', $postIds)->get();

            $schedulePosts = collect();
            if ($personnel->schedules) {
                $schedulePosts = $personnel->schedules->pluck('securityPost')->filter();
            }

            $allPosts = $posts->merge($schedulePosts)->unique('id')->values();

            $responseData = [
                'id' => $personnel->id,
                'name' => $personnel->name,
                'email' => $personnel->email,
                'phone' => $personnel->phone,
                'username' => $personnel->username,
                'status' => $personnel->status,
                'type' => $personnel->type,
                // ✅ REMOVED: supervisor_level
                'can_be_supervisor' => $personnel->can_be_supervisor,
                // ✅ REMOVED: supervisor_score
                'created_at' => $personnel->created_at ? $personnel->created_at->format('Y-m-d H:i:s') : null,
                'created_by' => $personnel->created_by,
                'invitation_sent_at' => $personnel->invitation_sent_at,
                'posts' => $allPosts->map(function($post) {
                    return [
                        'id' => $post->id,
                        'name' => $post->name,
                    ];
                }),
                'supervisor_assignments' => $personnel->supervisorAssignments->map(function($assignment) {
                    return [
                        'id' => $assignment->id,
                        'supervisor_type' => $assignment->supervisor_type,
                        'is_active' => $assignment->is_active,
                        'start_date' => $assignment->start_date ? $assignment->start_date->format('Y-m-d') : null,
                        'end_date' => $assignment->end_date ? $assignment->end_date->format('Y-m-d') : null,
                        'assigned_by' => $assignment->assigned_by,
                        'security_post_id' => $assignment->security_post_id,
                    ];
                }),
                'schedule_count' => $personnel->supervisorAssignments->count(),
            ];

            $json = json_encode($responseData);
            
            if (substr($json, 0, 3) === "\xEF\xBB\xBF") {
                $json = substr($json, 3);
            }
            
            return response($json, 200)
                ->header('Content-Type', 'application/json')
                ->header('X-Content-Type-Options', 'nosniff');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('❌ Personnel not found', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Personnel not found'], 404);
            
        } catch (\Exception $e) {
            Log::error('❌ Error fetching personnel details', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Failed to load personnel details: ' . $e->getMessage()], 500);
        }
    }

    // ==================== DESTROY / DELETE METHODS ====================

    /**
     * Soft delete security personnel
     */
    public function destroy(Request $request, $id)
    {
        $currentUser = Auth::user();
        
        if (!$this->canManageSecurityPersonnel($currentUser)) {
            return $this->deletionResponse($request, false, 'You do not have permission to delete security personnel.');
        }
        
        $user = User::findOrFail($id);
        
        if ($user->type !== User::TYPE_SECURITY_PERSONNEL) {
            return $this->deletionResponse($request, false, 'User is not security personnel.');
        }
        
        if ($user->id === auth()->id()) {
            return $this->deletionResponse($request, false, 'You cannot delete your own account.');
        }
        
        $criticalRelations = $this->checkCriticalRelations($user);
        
        if (!empty($criticalRelations)) {
            $message = "Cannot delete personnel. Has: " . implode(', ', $criticalRelations);
            return $this->deletionResponse($request, false, $message);
        }
        
        DB::beginTransaction();
        
        try {
            $user->invitations()
                ->whereNull('accepted_at')
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'User account deleted by ' . $currentUser->name,
                    'metadata' => DB::raw("JSON_SET(COALESCE(metadata, '{}'), '$.cancelled_by', " . $currentUser->id . ", '$.cancelled_by_name', '" . addslashes($currentUser->name) . "', '$.cancelled_at', '" . now()->toISOString() . "')")
                ]);
            
            SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
            
            if (class_exists('App\Models\SecuritySchedule')) {
                SecuritySchedule::where('security_user_id', $user->id)
                    ->whereIn('status', ['scheduled', 'active'])
                    ->update(['status' => 'cancelled']);
            }
            
            $deletionReason = $request->input('deletion_reason', 'No reason provided');
            
            $currentMetadata = [];
            if ($user->metadata) {
                if (is_string($user->metadata)) {
                    $currentMetadata = json_decode($user->metadata, true) ?? [];
                } elseif (is_array($user->metadata)) {
                    $currentMetadata = $user->metadata;
                }
            }
            
            $currentMetadata['deletion_info'] = [
                'deleted_at' => now()->toISOString(),
                'deleted_by' => $currentUser->id,
                'deleted_by_name' => $currentUser->name,
                'deletion_reason' => $deletionReason,
                'deletion_type' => 'soft_delete',
            ];
            
            $user->deleted_by = $currentUser->id;
            $user->deleted_at = now();
            $user->status = 'deleted';
            $user->metadata = $currentMetadata;
            $user->save();
            
            DB::commit();
            
            event(new UserDeleted($user, $currentUser, $deletionReason));
            
            Log::info('Security personnel soft deleted', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'deleted_by' => $currentUser->id,
                'deletion_reason' => $deletionReason,
                'invitations_cancelled' => true
            ]);
            
            return $this->deletionResponse($request, true, 'Security personnel deleted successfully!', route('security.personnel.index'));
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete security personnel: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'error_trace' => $e->getTraceAsString(),
                'deleted_by' => $currentUser->id
            ]);
            
            return $this->deletionResponse($request, false, 'Failed to delete security personnel: ' . $e->getMessage());
        }
    }

    /**
     * Check critical relations for deletion
     */
    private function checkCriticalRelations(User $user): array
    {
        $criticalRelations = [];
        
        $activeAssignments = SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('is_active', true)
            ->count();
        
        if ($activeAssignments > 0) {
            $criticalRelations[] = "{$activeAssignments} active supervisor assignments";
        }
        
        if (class_exists('App\Models\SecuritySchedule')) {
            $activeSchedules = SecuritySchedule::where('security_user_id', $user->id)
                ->whereIn('status', ['scheduled', 'active'])
                ->count();
            
            if ($activeSchedules > 0) {
                $criticalRelations[] = "{$activeSchedules} active schedules";
            }
        }
        
        $supervisedUsers = SecuritySupervisorAssignment::where('assigned_by', $user->id)
            ->where('is_active', true)
            ->count();
        
        if ($supervisedUsers > 0) {
            $criticalRelations[] = "{$supervisedUsers} supervised personnel";
        }
        
        return $criticalRelations;
    }

    /**
     * Check if user has critical relations (for deletion validation - AJAX)
     */
    public function checkRelations($id)
    {
        $currentUser = Auth::user();
        
        if (!$this->canManageSecurityPersonnel($currentUser)) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $user = User::findOrFail($id);
        
        if ($user->type !== User::TYPE_SECURITY_PERSONNEL) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'User is not security personnel.'
            ], 422);
        }
        
        $criticalRelations = $this->checkCriticalRelations($user);
        
        return $this->cleanJsonResponse([
            'success' => true,
            'has_critical_relations' => !empty($criticalRelations),
            'critical_relations' => $criticalRelations,
            'user_id' => $user->id,
            'user_name' => $user->name
        ]);
    }

    /**
     * Restore a soft-deleted security personnel
     */
    public function restore($id)
    {
        $currentUser = Auth::user();
        
        if (!$this->canManageSecurityPersonnel($currentUser)) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'You do not have permission to restore personnel.'
            ], 403);
        }
        
        try {
            $user = User::onlyTrashed()->findOrFail($id);
            
            if ($user->type !== User::TYPE_SECURITY_PERSONNEL) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'User is not security personnel.'
                ], 422);
            }
            
            DB::beginTransaction();
            
            $currentMetadata = [];
            if ($user->metadata) {
                if (is_string($user->metadata)) {
                    $currentMetadata = json_decode($user->metadata, true) ?? [];
                } elseif (is_array($user->metadata)) {
                    $currentMetadata = $user->metadata;
                }
            }
            
            unset($currentMetadata['deletion_info']);
            
            $currentMetadata['restored_info'] = [
                'restored_at' => now()->toISOString(),
                'restored_by' => $currentUser->id,
                'restored_by_name' => $currentUser->name,
            ];
            
            $user->restore();
            
            $user->update([
                'status' => User::STATUS_PENDING,
                'deleted_at' => null,
                'deleted_by' => null,
                'metadata' => $currentMetadata,
            ]);
            
            DB::commit();
            
            Log::info('Security personnel restored', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'restored_by' => $currentUser->id
            ]);
            
            return $this->cleanJsonResponse([
                'success' => true,
                'message' => 'Security personnel restored successfully!',
                'redirect_url' => route('security.personnel.show', $user->id)
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore security personnel: ' . $e->getMessage(), [
                'user_id' => $id,
                'error_trace' => $e->getTraceAsString()
            ]);
            
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to restore personnel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Permanently delete a security personnel (force delete from trash)
     */
    public function forceDelete(Request $request, $id)
    {
        $currentUser = Auth::user();
        
        if (!$this->canManageSecurityPersonnel($currentUser)) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'You do not have permission to permanently delete personnel.'
            ], 403);
        }
        
        try {
            $user = User::onlyTrashed()->findOrFail($id);
            
            if ($user->type !== User::TYPE_SECURITY_PERSONNEL) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'User is not security personnel.'
                ], 422);
            }
            
            DB::beginTransaction();
            
            $user->roles()->detach();
            SecuritySupervisorAssignment::where('user_id', $user->id)->delete();
            
            if (class_exists('App\Models\SecuritySchedule')) {
                SecuritySchedule::where('security_user_id', $user->id)->delete();
            }
            
            if ($user->photo && Storage::disk('public')->exists('users/photos/' . $user->photo)) {
                Storage::disk('public')->delete('users/photos/' . $user->photo);
            }
            
            $user->forceDelete();
            
            DB::commit();
            
            Log::info('Security personnel permanently deleted', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'deleted_by' => $currentUser->id,
                'deleted_by_name' => $currentUser->name,
                'deletion_reason' => $request->input('deletion_reason')
            ]);
            
            return $this->cleanJsonResponse([
                'success' => true,
                'message' => 'Security personnel permanently deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to permanently delete security personnel: ' . $e->getMessage(), [
                'user_id' => $id,
                'error_trace' => $e->getTraceAsString()
            ]);
            
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to permanently delete personnel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Safely get metadata as array
     */
    private function getMetadataAsArray($user): array
    {
        if (empty($user->metadata)) {
            return [];
        }
        
        if (is_string($user->metadata)) {
            return json_decode($user->metadata, true) ?? [];
        }
        
        if (is_array($user->metadata)) {
            return $user->metadata;
        }
        
        return [];
    }

    /**
     * Safely set metadata from array
     */
    private function setMetadata(User $user, array $metadata): void
    {
        if (method_exists($user, 'getCasts') && isset($user->getCasts()['metadata'])) {
            $user->metadata = $metadata;
        } else {
            $user->metadata = json_encode($metadata);
        }
    }

    /**
     * Alias for forceDelete - permanently delete a soft-deleted user
     */
    public function forceDestroy(Request $request, $id)
    {
        return $this->forceDelete($request, $id);
    }

    /**
     * Display trashed (deleted) security personnel
     */
    public function trash(Request $request)
    {
        $currentUser = Auth::user();
        
        if (!$this->canManageSecurityPersonnel($currentUser)) {
            abort(403, 'Unauthorized to view trashed personnel.');
        }
        
        $perPage = $this->getPerPage($request->get('per_page'));
        
        $personnel = User::onlyTrashed()
            ->where('type', User::TYPE_SECURITY_PERSONNEL)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('deleted_by'), function ($query) use ($request) {
                $query->where('deleted_by', $request->deleted_by);
            })
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage);
        
        $personnel->getCollection()->transform(function ($user) {
            $metadata = [];
            if ($user->metadata) {
                if (is_string($user->metadata)) {
                    $metadata = json_decode($user->metadata, true) ?? [];
                } elseif (is_array($user->metadata)) {
                    $metadata = $user->metadata;
                }
            }
            
            $user->deleted_at_formatted = $user->deleted_at?->format('Y-m-d H:i:s');
            $user->deleted_by_name = optional($user->deleter)->name ?? 'System';
            $user->deletion_reason = $metadata['deletion_info']['deletion_reason'] ?? 'Not specified';
            
            return $user;
        });
        
        $stats = [
            'total_trashed' => User::onlyTrashed()->where('type', User::TYPE_SECURITY_PERSONNEL)->count(),
            'trashed_this_week' => User::onlyTrashed()
                ->where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('deleted_at', '>=', now()->subWeek())
                ->count(),
            'trashed_this_month' => User::onlyTrashed()
                ->where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('deleted_at', '>=', now()->subMonth())
                ->count(),
        ];
        
        $perPageOptions = self::PER_PAGE_OPTIONS;
        $posts = $this->getAccessiblePosts($currentUser);
        
        return view('security.personnel.trash', compact(
            'personnel', 'stats', 'posts', 'perPage', 'perPageOptions'
        ));
    }

    /**
     * Bulk restore multiple trashed personnel
     */
    public function bulkRestore(Request $request)
    {
        $currentUser = Auth::user();
        
        if (!$this->canManageSecurityPersonnel($currentUser)) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }
        
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);
        
        $results = ['success' => 0, 'failed' => 0, 'restored_count' => 0, 'details' => []];
        
        DB::beginTransaction();
        
        try {
            foreach ($validated['user_ids'] as $userId) {
                try {
                    $user = User::onlyTrashed()->find($userId);
                    
                    if (!$user) {
                        $results['failed']++;
                        $results['details'][] = [
                            'id' => $userId,
                            'success' => false,
                            'message' => 'User not found'
                        ];
                        continue;
                    }
                    
                    if ($user->type !== User::TYPE_SECURITY_PERSONNEL) {
                        $results['failed']++;
                        $results['details'][] = [
                            'id' => $userId,
                            'success' => false,
                            'message' => 'User is not security personnel'
                        ];
                        continue;
                    }
                    
                    $user->restore();
                    $user->update([
                        'status' => User::STATUS_PENDING,
                        'deleted_at' => null,
                        'deleted_by' => null
                    ]);
                    
                    $results['success']++;
                    $results['restored_count']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => true,
                        'message' => 'User restored'
                    ];
                    
                    Log::info('Security personnel restored via bulk action', [
                        'user_id' => $userId,
                        'restored_by' => $currentUser->id
                    ]);
                    
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                    Log::error("Bulk restore failed for user {$userId}: " . $e->getMessage());
                }
            }
            
            DB::commit();
            
            $message = "Bulk restore completed: {$results['success']} successful, {$results['failed']} failed";
            
            return $this->cleanJsonResponse([
                'success' => $results['success'] > 0,
                'restored_count' => $results['restored_count'],
                'failed_count' => $results['failed'],
                'message' => $message,
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk restore failed: ' . $e->getMessage(), [
                'user_ids' => $validated['user_ids']
            ]);
            
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Bulk restore failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk permanently delete multiple trashed personnel
     */
    public function bulkPermanentDelete(Request $request)
    {
        $currentUser = Auth::user();
        
        if (!$this->canManageSecurityPersonnel($currentUser)) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }
        
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'deletion_reason' => 'nullable|string|max:500'
        ]);
        
        if (in_array($currentUser->id, $validated['user_ids'])) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'You cannot permanently delete your own account.'
            ], 422);
        }
        
        $results = ['success' => 0, 'failed' => 0, 'deleted_count' => 0, 'details' => []];
        
        DB::beginTransaction();
        
        try {
            foreach ($validated['user_ids'] as $userId) {
                try {
                    $user = User::onlyTrashed()->find($userId);
                    
                    if (!$user) {
                        $results['failed']++;
                        $results['details'][] = [
                            'id' => $userId,
                            'success' => false,
                            'message' => 'User not found or not trashed'
                        ];
                        continue;
                    }
                    
                    if ($user->type !== User::TYPE_SECURITY_PERSONNEL) {
                        $results['failed']++;
                        $results['details'][] = [
                            'id' => $userId,
                            'success' => false,
                            'message' => 'User is not security personnel'
                        ];
                        continue;
                    }
                    
                    $user->roles()->detach();
                    SecuritySupervisorAssignment::where('user_id', $user->id)->delete();
                    
                    if ($user->photo && Storage::disk('public')->exists('users/photos/' . $user->photo)) {
                        Storage::disk('public')->delete('users/photos/' . $user->photo);
                    }
                    
                    $user->forceDelete();
                    
                    $results['success']++;
                    $results['deleted_count']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => true,
                        'message' => 'User permanently deleted'
                    ];
                    
                    Log::info('Security personnel permanently deleted via bulk action', [
                        'user_id' => $userId,
                        'deleted_by' => $currentUser->id,
                        'deletion_reason' => $validated['deletion_reason'] ?? null
                    ]);
                    
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                    Log::error("Bulk permanent delete failed for user {$userId}: " . $e->getMessage());
                }
            }
            
            DB::commit();
            
            $message = "Bulk permanent delete completed: {$results['success']} successful, {$results['failed']} failed";
            
            return $this->cleanJsonResponse([
                'success' => $results['success'] > 0,
                'deleted_count' => $results['deleted_count'],
                'failed_count' => $results['failed'],
                'message' => $message,
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk permanent delete failed: ' . $e->getMessage(), [
                'user_ids' => $validated['user_ids']
            ]);
            
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Bulk permanent delete failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Empty entire trash (permanently delete all soft-deleted security personnel)
     */
    public function emptyTrash(Request $request)
    {
        $currentUser = Auth::user();
        
        if (!$this->canManageSecurityPersonnel($currentUser)) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Unauthorized. Only authorized personnel can empty trash.'
            ], 403);
        }
        
        $validated = $request->validate([
            'confirmation' => 'required|string|in:empty_all_trash'
        ]);
        
        DB::beginTransaction();
        
        try {
            $trashedUsers = User::onlyTrashed()
                ->where('type', User::TYPE_SECURITY_PERSONNEL)
                ->get();
            
            $count = $trashedUsers->count();
            $deletedCount = 0;
            $failedUsers = [];
            
            foreach ($trashedUsers as $user) {
                try {
                    if ($user->id === $currentUser->id) {
                        $failedUsers[] = "Cannot delete your own account";
                        continue;
                    }
                    
                    $user->roles()->detach();
                    SecuritySupervisorAssignment::where('user_id', $user->id)->delete();
                    
                    if ($user->photo && Storage::disk('public')->exists('users/photos/' . $user->photo)) {
                        Storage::disk('public')->delete('users/photos/' . $user->photo);
                    }
                    
                    $user->forceDelete();
                    $deletedCount++;
                    
                } catch (\Exception $e) {
                    $failedUsers[] = "{$user->name} (ID: {$user->id}): " . $e->getMessage();
                    Log::error("Failed to permanently delete user {$user->id} during empty trash: " . $e->getMessage());
                }
            }
            
            DB::commit();
            
            Log::info('Security personnel trash emptied', [
                'total_users' => $count,
                'deleted_count' => $deletedCount,
                'failed_count' => count($failedUsers),
                'deleted_by' => $currentUser->id
            ]);
            
            $message = "Trash emptied successfully! {$deletedCount} out of {$count} users were permanently deleted.";
            
            if (!empty($failedUsers)) {
                $message .= " Failed to delete: " . implode(', ', array_slice($failedUsers, 0, 3));
                if (count($failedUsers) > 3) {
                    $message .= " and " . (count($failedUsers) - 3) . " more";
                }
            }
            
            return $this->cleanJsonResponse([
                'success' => true,
                'message' => $message,
                'deleted_count' => $deletedCount,
                'total_count' => $count,
                'failed_users' => $failedUsers
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to empty trash: ' . $e->getMessage(), [
                'error_trace' => $e->getTraceAsString()
            ]);
            
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to empty trash: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== INVITATION METHODS ====================

    /**
     * Send invitation to security personnel
     */
    public function sendInvitation(Request $request, $userId)
    {
        try {
            $currentUser = Auth::user();
            
            if (!$this->canManageSecurityPersonnel($currentUser)) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'You do not have permission to send invitations.'
                ], 403);
            }

            $user = User::findOrFail($userId);
            
            if ($user->type !== User::TYPE_SECURITY_PERSONNEL) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'User is not security personnel.'
                ], 422);
            }

            $validated = $request->validate([
                'invitation_channels' => 'required|array',
                'invitation_channels.*' => 'in:sms,email,whatsapp',
                'invitation_type' => 'required|in:security_welcome,security_orientation,security_training,password_setup,welcome,registration,account_setup',
                'custom_message' => 'nullable|string|max:1000',
                'expires_in_days' => 'nullable|integer|min:1|max:30',
                'template' => 'nullable|string|max:100',
            ]);

            if (empty($validated['expires_in_days'])) {
                $validated['expires_in_days'] = config('invitation.security_expiry_days', 7);
            }

            $result = $this->sendInvitationDirect($user, $validated, $currentUser);

            if ($request->expectsJson() || $request->ajax()) {
                if ($result['success']) {
                    $user->update(['invitation_sent_at' => now()]);
                    return $this->cleanJsonResponse([
                        'success' => true,
                        'message' => 'Invitation sent successfully!',
                        'invitation_url' => $result['invitation_url'] ?? null,
                        'channels_successful' => $result['channels_successful'] ?? [],
                        'failed_channels' => $result['failed_channels'] ?? []
                    ]);
                }
                
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to send invitation'
                ], 422);
            }

            if ($result['success']) {
                $channels = implode(', ', $result['channels_successful']);
                return back()->with('success', "Invitation sent successfully via {$channels}");
            }

            return back()->with('error', 'Failed to send invitation: ' . ($result['message'] ?? 'Unknown error'));

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            throw $e;

        } catch (\Exception $e) {
            Log::error('Failed to send security invitation: ' . $e->getMessage(), [
                'user_id' => $userId,
                'error_trace' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Failed to send invitation: ' . $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Failed to send invitation: ' . $e->getMessage());
        }
    }

    /**
     * Resend invitation to security personnel
     */
    public function resendInvitation(Request $request, $userId)
    {
        try {
            $currentUser = Auth::user();
            
            if (!$this->canManageSecurityPersonnel($currentUser)) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'You do not have permission to resend invitations.'
                ], 403);
            }

            $user = User::withTrashed()->find($userId);
            
            if (!$user) {
                Log::error('User not found for resend invitation', [
                    'user_id' => $userId,
                    'current_user' => $currentUser->id ?? 'null'
                ]);
                
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'User not found. The personnel may have been deleted.'
                ], 404);
            }
            
            if ($user->trashed()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Cannot send invitation to a deleted user. Please restore the user first.'
                ], 422);
            }
            
            if ($user->type !== User::TYPE_SECURITY_PERSONNEL) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'User is not security personnel.'
                ], 422);
            }

            $validated = $request->validate([
                'channels' => 'sometimes|array',
                'channels.*' => 'in:sms,email,whatsapp',
                'custom_message' => 'nullable|string|max:1000',
                'expires_in_days' => 'nullable|integer|min:1|max:30',
                'resend_type' => 'nullable|in:same_channels,available_channels,selected_channels',
                'invitation_type' => 'nullable|in:welcome,registration,account_setup,password_setup,security_orientation,security_training'
            ]);

            if (empty($validated['channels'])) {
                $validated['channels'] = $this->getAvailableChannelsForUser($user);
            }

            if (empty($validated['channels'])) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'No available communication channels for this user.'
                ], 422);
            }

            $result = $this->sendInvitationDirect($user, [
                'invitation_channels' => $validated['channels'],
                'invitation_type' => $validated['invitation_type'] ?? 'welcome',
                'custom_message' => $validated['custom_message'] ?? null,
                'expires_in_days' => $validated['expires_in_days'] ?? 7,
            ], $currentUser);

            if ($request->expectsJson() || $request->ajax()) {
                if ($result['success']) {
                    return $this->cleanJsonResponse([
                        'success' => true,
                        'message' => 'Invitation resent successfully!',
                        'invitation_url' => $result['invitation_url'] ?? null,
                        'channels_successful' => $result['channels_successful'] ?? [],
                        'failed_channels' => $result['failed_channels'] ?? []
                    ]);
                }
                
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to resend invitation'
                ], 422);
            }

            if ($result['success']) {
                $channels = implode(', ', $result['channels_successful']);
                return back()->with('success', "Invitation resent successfully via {$channels}");
            }

            return back()->with('error', 'Failed to resend invitation: ' . ($result['message'] ?? 'Unknown error'));

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            throw $e;

        } catch (\Exception $e) {
            Log::error('Failed to resend invitation: ' . $e->getMessage(), [
                'user_id' => $userId,
                'error_trace' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Failed to resend invitation: ' . $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Failed to resend invitation: ' . $e->getMessage());
        }
    }

    /**
     * Get invitation information for a security personnel
     */
    public function getInvitationInfo($userId)
    {
        try {
            $user = User::with(['invitations' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(1);
            }])->findOrFail($userId);

            if ($user->type !== User::TYPE_SECURITY_PERSONNEL) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'User is not security personnel.'
                ], 422);
            }

            $lastInvitation = $user->invitations->first();

            if ($lastInvitation) {
                $availableChannels = $this->invitationService->getAvailableChannels($user);
                
                return $this->cleanJsonResponse([
                    'success' => true,
                    'invitation' => [
                        'sent_at' => $lastInvitation->created_at,
                        'expires_at' => $lastInvitation->expires_at,
                        'channels' => json_decode($lastInvitation->channels ?? '[]', true),
                        'type' => $lastInvitation->type ?? 'security_welcome',
                        'status' => $lastInvitation->status ?? 'sent',
                    ],
                    'available_channels' => $availableChannels,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'has_phone' => !empty($user->phone),
                        'has_email' => !empty($user->email),
                    ]
                ]);
            }

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'No previous invitation found'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get invitation info: ' . $e->getMessage(), [
                'user_id' => $userId,
                'error_trace' => $e->getTraceAsString()
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to retrieve invitation information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available invitation channels for a user
     */
    public function getUserChannels($userId)
    {
        try {
            $user = User::findOrFail($userId);
            
            if ($user->type !== User::TYPE_SECURITY_PERSONNEL) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'User is not security personnel.'
                ], 422);
            }

            $availableChannels = $this->invitationService->getAvailableChannels($user);

            return $this->cleanJsonResponse([
                'success' => true,
                'available_channels' => $availableChannels,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'has_phone' => !empty($user->phone),
                    'has_email' => !empty($user->email),
                    'phone' => $user->phone,
                    'email' => $user->email,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get user channels: ' . $e->getMessage(), [
                'user_id' => $userId,
                'error_trace' => $e->getTraceAsString()
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to get available channels: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk send invitations to multiple security personnel
     */
    public function bulkSendInvitation(Request $request)
    {
        try {
            $currentUser = Auth::user();
            
            if (!$this->canManageSecurityPersonnel($currentUser)) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'You do not have permission to send bulk invitations.'
                ], 403);
            }

            $validated = $request->validate([
                'user_ids' => 'required|array',
                'user_ids.*' => 'exists:users,id',
                'invitation_channels' => 'required|array',
                'invitation_channels.*' => 'in:sms,email,whatsapp',
                'invitation_type' => 'required|in:security_welcome,security_orientation,security_training,password_setup',
                'custom_message' => 'nullable|string|max:1000',
                'expires_in_days' => 'nullable|integer|min:1|max:30',
            ]);

            $results = ['success' => 0, 'failed' => 0, 'details' => []];

            foreach ($validated['user_ids'] as $userId) {
                try {
                    $user = User::find($userId);
                    
                    if (!$user || $user->type !== User::TYPE_SECURITY_PERSONNEL) {
                        $results['failed']++;
                        $results['details'][] = [
                            'id' => $userId,
                            'success' => false,
                            'message' => 'User not found or not security personnel'
                        ];
                        continue;
                    }

                    $invitationData = [
                        'invitation_channels' => $validated['invitation_channels'],
                        'invitation_type' => $validated['invitation_type'],
                        'custom_message' => $validated['custom_message'] ?? null,
                        'expires_in_days' => $validated['expires_in_days'] ?? 7,
                    ];

                    $result = $this->invitationService->sendInvitation($user, $invitationData);

                    if ($result['success']) {
                        $user->update(['invitation_sent_at' => now()]);
                        $results['success']++;
                        $results['details'][] = [
                            'id' => $userId,
                            'success' => true,
                            'message' => 'Invitation sent',
                            'channels' => $result['channels_successful'] ?? []
                        ];
                    } else {
                        $results['failed']++;
                        $results['details'][] = [
                            'id' => $userId,
                            'success' => false,
                            'message' => $result['message'] ?? 'Unknown error'
                        ];
                    }

                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                    Log::error("Bulk invitation failed for user {$userId}: " . $e->getMessage());
                }
            }

            $message = "Bulk invitations completed: {$results['success']} successful, {$results['failed']} failed";

            return $this->cleanJsonResponse([
                'success' => $results['success'] > 0,
                'results' => $results,
                'message' => $message
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Bulk invitation failed: ' . $e->getMessage(), [
                'error_trace' => $e->getTraceAsString()
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Bulk invitation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== SUPERVISOR MANAGEMENT METHODS ====================

    /**
     * Show form to assign supervisor role to security personnel
     */
    public function assignSupervisorForm($userId)
    {
        $currentUser = Auth::user();
        
        if (!$this->canManageSecurityPersonnel($currentUser)) {
            return redirect()->route('security.dashboard')
                ->with('error', 'You do not have permission to assign supervisor roles.');
        }

        $personnel = User::with(['supervisorAssignments' => function($q) {
            $q->where('is_active', true);
        }])->findOrFail($userId);
        
        if (!$this->isInScope($currentUser, $personnel)) {
            return redirect()->route('security.personnel.index')
                ->with('error', 'This personnel is not within your scope.');
        }

        // ✅ UPDATED: Only Team Lead and Section Lead are assignable by Area Supervisors
        $assignableLevels = [
            1 => 'Team Lead (Level 1)',
            2 => 'Section Lead (Level 2)',
        ];

        // ✅ ADDED: Level names for display
        $levelNames = [
            0 => 'Not a Supervisor',
            1 => 'Team Lead (Level 1)',
            2 => 'Section Lead (Level 2)',
            3 => 'Post Commander (Level 3)',
        ];

        // ✅ ADDED: Get accessible posts with capacity info
        $posts = $this->getAccessiblePosts($currentUser);
        
        // ✅ ADDED: Add post capacity info to each post
        $posts->each(function($post) {
            $post->current_staff_count = SecuritySupervisorAssignment::where('security_post_id', $post->id)
                ->where('is_active', true)
                ->count();
            $post->available_slots = $post->max_personnel - $post->current_staff_count;
            $post->is_full = $post->available_slots <= 0;
        });

        // ✅ ADDED: Check if personnel already has active supervisor assignment
        $hasActiveAssignment = $personnel->supervisorAssignments()
            ->where('is_active', true)
            ->exists();

        // ✅ ADDED: Get current supervisor assignment if exists
        $currentAssignment = null;
        if ($hasActiveAssignment) {
            $currentAssignment = $personnel->supervisorAssignments()
                ->where('is_active', true)
                ->first();
        }

        // ✅ UPDATED: Check if personnel is eligible for supervisor (only can_be_supervisor)
        $isEligible = $personnel->can_be_supervisor;

        // ✅ UPDATED: Get communication status for UI
        $communicationStatus = $this->getCommunicationServiceStatus();
        $smsStatus = $communicationStatus['sms'] ?? [];
        $whatsappStatus = $communicationStatus['whatsapp'] ?? [];
        $emailStatus = $communicationStatus['email'] ?? [];
        $multiChannelStatus = $communicationStatus['multi_channel'] ?? [];
        
        $smsStatus['system_ready'] = $smsStatus['system_ready'] ?? ($smsStatus['enabled'] ?? false);
        $whatsappStatus['system_ready'] = $whatsappStatus['system_ready'] ?? ($whatsappStatus['enabled'] ?? false);
        $emailStatus['system_ready'] = $emailStatus['system_ready'] ?? ($emailStatus['enabled'] ?? false);

        return view('security.personnel.assign-supervisor', compact(
            'personnel', 
            'assignableLevels', 
            'posts',
            'levelNames',
            'hasActiveAssignment',
            'currentAssignment',
            'isEligible',
            'smsStatus',
            'whatsappStatus',
            'emailStatus',
            'multiChannelStatus'
        ));
    }

    /**
     * Assign supervisor role to security personnel
     */
    public function assignSupervisor(Request $request, $userId)
    {
        $currentUser = Auth::user();
        
        if (!$this->canManageSecurityPersonnel($currentUser)) {
            return redirect()->route('security.dashboard')
                ->with('error', 'You do not have permission to assign supervisor roles.');
        }

        $validator = Validator::make($request->all(), [
            'supervisor_level' => 'required|in:1,2',
            'security_post_id' => 'nullable|exists:security_posts,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_primary_supervisor' => 'sometimes|boolean',
            // ✅ REMOVED: supervisor_score
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $personnel = User::findOrFail($userId);

        if (!$this->canManageSecurityPersonnel($currentUser)) {
            return redirect()->route('security.personnel.index')
                ->with('error', 'This personnel is not within your scope.');
        }

        // ✅ UPDATED: Check if personnel is eligible (can_be_supervisor)
        if (!$personnel->can_be_supervisor) {
            return redirect()->back()
                ->with('error', 'This personnel is not eligible to be a supervisor. Please mark them as eligible first.')
                ->withInput();
        }

        if ($request->security_post_id) {
            $accessiblePosts = $this->getAccessiblePosts($currentUser)->pluck('id')->toArray();
            if (!in_array((int)$request->security_post_id, $accessiblePosts)) {
                return redirect()->back()
                    ->with('error', 'You do not have access to the selected post.')
                    ->withInput();
            }
        }

        DB::beginTransaction();

        try {
            $assignment = $this->assignSupervisorRole(
                $personnel,
                $currentUser,
                (int)$request->supervisor_level,
                $request->security_post_id ? (int)$request->security_post_id : null,
                $request->start_date,
                $request->end_date,
                $request->boolean('is_primary_supervisor', false)
                // ✅ REMOVED: supervisor_score parameter
            );

            DB::commit();

            $levelNames = ['', 'Team Lead', 'Section Lead', 'Post Commander'];
            return redirect()->route('security.personnel.index')
                ->with('success', "Supervisor role ({$levelNames[(int)$request->supervisor_level]}) assigned successfully!");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign supervisor: ' . $e->getMessage(), [
                'user_id' => $userId,
                'assigned_by' => $currentUser->id,
                'error_trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to assign supervisor role: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove supervisor role from security personnel
     */
    public function removeSupervisor($userId)
    {
        $currentUser = Auth::user();
        
        if (!$this->canManageSecurityPersonnel($currentUser)) {
            return redirect()->route('security.dashboard')
                ->with('error', 'You do not have permission to remove supervisor roles.');
        }

        $personnel = User::findOrFail($userId);

        // ✅ UPDATED: Check if assignment can be removed
        $assignment = SecuritySupervisorAssignment::where('user_id', $personnel->id)
            ->where('is_active', true)
            ->first();

        if ($assignment && $assignment->assigned_by !== $currentUser->id && !$currentUser->isAdmin() && !$currentUser->isSuperAdmin()) {
            return redirect()->route('security.personnel.index')
                ->with('error', 'You can only remove supervisor roles you have assigned.');
        }

        DB::beginTransaction();

        try {
            SecuritySupervisorAssignment::where('user_id', $personnel->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // ✅ UPDATED: Only update can_be_supervisor flag
            $personnel->update([
                'can_be_supervisor' => false,
                // ❌ REMOVED: supervisor_level = 0
            ]);

            DB::commit();

            return redirect()->route('security.personnel.index')
                ->with('success', 'Supervisor role removed successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove supervisor: ' . $e->getMessage(), [
                'user_id' => $userId,
                'removed_by' => $currentUser->id,
                'error_trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to remove supervisor role: ' . $e->getMessage());
        }
    }

    // ==================== QUICK ACTIONS (AJAX) ====================

    /**
     * Quick assign personnel to a post via AJAX
     */
    public function quickAssignPost(Request $request, $userId)
    {
        try {
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            $currentUser = Auth::user();
            
            Log::info('🔍 Quick Assign - START', [
                'user_id' => $currentUser->id ?? 'null',
                'personnel_id' => $userId,
                'request_data' => $request->all(),
            ]);
            
            if (!$this->canManageSecurityPersonnel($currentUser)) {
                Log::warning('❌ Quick Assign - Unauthorized', [
                    'user_id' => $currentUser->id ?? 'null',
                ]);
                return response()->json([
                    'success' => false, 
                    'message' => 'You do not have permission to assign personnel to posts.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'security_post_id' => 'required|exists:security_posts,id',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Quick Assign - Validation failed', [
                    'errors' => $validator->errors()->toArray(),
                ]);
                return response()->json([
                    'success' => false, 
                    'message' => 'Invalid post selected',
                    'errors' => $validator->errors()
                ], 422);
            }

            $personnel = User::findOrFail($userId);
            $postId = (int)$request->security_post_id;
            $post = SecurityPost::find($postId);
            
            Log::info('✅ Personnel and Post found', [
                'personnel_id' => $personnel->id,
                'personnel_name' => $personnel->name,
                'created_by' => $personnel->created_by,
                'post_id' => $postId,
                'post_name' => $post->name ?? 'Unknown',
                'max_personnel' => $post->max_personnel ?? 1,
            ]);

            $accessiblePosts = $this->getAccessiblePosts($currentUser)->pluck('id')->toArray();
            
            if (!in_array($postId, $accessiblePosts) && !$currentUser->isAdmin() && !$currentUser->isSuperAdmin()) {
                Log::warning('❌ Quick Assign - Post not accessible', [
                    'post_id' => $postId,
                    'accessible_posts' => $accessiblePosts,
                ]);
                return response()->json([
                    'success' => false, 
                    'message' => 'You do not have access to the selected post.'
                ], 403);
            }

            if (!$post->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => "Post '{$post->name}' is currently inactive. Cannot assign personnel."
                ], 422);
            }

            $currentStaffCount = SecuritySupervisorAssignment::where('security_post_id', $postId)
                ->where('is_active', true)
                ->count();

            Log::info('🔍 Capacity check', [
                'post_id' => $postId,
                'current_staff' => $currentStaffCount,
                'max_personnel' => $post->max_personnel,
            ]);

            if ($currentStaffCount >= $post->max_personnel) {
                return response()->json([
                    'success' => false,
                    'message' => "Post '{$post->name}' is fully staffed (max: {$post->max_personnel}). Please try another post.",
                    'current_staff' => $currentStaffCount,
                    'max_personnel' => $post->max_personnel,
                    'available_slots' => 0,
                    'is_fully_staffed' => true,
                ], 422);
            }

            $existingActive = SecuritySupervisorAssignment::where('user_id', $personnel->id)
                ->where('security_post_id', $postId)
                ->where('is_active', true)
                ->first();

            if ($existingActive) {
                Log::info('ℹ️ Personnel already has an ACTIVE assignment', [
                    'personnel_id' => $personnel->id,
                    'post_id' => $postId,
                    'assignment_id' => $existingActive->id,
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => "Personnel is already assigned to '{$post->name}'.",
                    'already_assigned' => true,
                    'post_name' => $post->name,
                    'current_staff' => $currentStaffCount,
                    'max_personnel' => $post->max_personnel,
                ], 200);
            }

            $existingInactive = SecuritySupervisorAssignment::where('user_id', $personnel->id)
                ->where('security_post_id', $postId)
                ->where('is_active', false)
                ->first();

            if ($existingInactive) {
                Log::info('🔄 Found inactive assignment, reactivating', [
                    'personnel_id' => $personnel->id,
                    'post_id' => $postId,
                    'assignment_id' => $existingInactive->id,
                ]);

                DB::beginTransaction();
                try {
                    $existingInactive->update([
                        'is_active' => true,
                        'assigned_by' => $currentUser->id,
                        'start_date' => now(),
                        'updated_at' => now(),
                        'metadata' => array_merge($existingInactive->metadata ?? [], [
                            'reactivated_by' => $currentUser->id,
                            'reactivated_by_name' => $currentUser->name,
                            'reactivated_at' => now()->toISOString(),
                        ]),
                    ]);

                    $personnel->update([
                        'security_post_id' => $postId,
                    ]);

                    // ✅ No auto-schedule creation here - removed

                    DB::commit();

                    $newStaffCount = $currentStaffCount + 1;
                    $availableSlots = $post->max_personnel - $newStaffCount;

                    return response()->json([
                        'success' => true,
                        'message' => "Personnel reassigned to '{$post->name}' successfully! ({$newStaffCount}/{$post->max_personnel} slots filled)",
                        'post_name' => $post->name,
                        'current_staff' => $newStaffCount,
                        'max_personnel' => $post->max_personnel,
                        'available_slots' => $availableSlots,
                        'is_fully_staffed' => $availableSlots <= 0,
                        'reactivated' => true,
                    ]);

                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('❌ Failed to reactivate assignment: ' . $e->getMessage(), [
                        'personnel_id' => $personnel->id,
                        'post_id' => $postId,
                        'error_trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            }

            $deactivatedCount = SecuritySupervisorAssignment::where('user_id', $personnel->id)
                ->where('is_active', true)
                ->where('security_post_id', '!=', $postId)
                ->update(['is_active' => false]);

            if ($deactivatedCount > 0) {
                Log::info('🔍 Deactivated other active assignments', [
                    'personnel_id' => $personnel->id,
                    'deactivated_count' => $deactivatedCount,
                ]);
            }

            DB::beginTransaction();

            try {
                $this->assignPersonnelToPost($personnel, $postId, $currentUser);
                DB::commit();
                
                $newStaffCount = $currentStaffCount + 1;
                $availableSlots = $post->max_personnel - $newStaffCount;

                Log::info('✅ Quick Assign - Success', [
                    'personnel_id' => $personnel->id,
                    'post_id' => $postId,
                    'post_name' => $post->name,
                    'new_staff_count' => $newStaffCount,
                    'max_personnel' => $post->max_personnel,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Personnel assigned to '{$post->name}' successfully! ({$newStaffCount}/{$post->max_personnel} slots filled)",
                    'post_name' => $post->name,
                    'current_staff' => $newStaffCount,
                    'max_personnel' => $post->max_personnel,
                    'available_slots' => $availableSlots,
                    'is_fully_staffed' => $availableSlots <= 0,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('❌ Quick Assign - Error: ' . $e->getMessage(), [
                    'personnel_id' => $userId,
                    'post_id' => $request->security_post_id,
                    'error_trace' => $e->getTraceAsString(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to assign: ' . $e->getMessage()
                ], 500);
            }
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('❌ Quick Assign - Personnel not found', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Personnel not found.'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('❌ Quick Assign - Fatal error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * Get pagination per page value
     */
    private function getPerPage($requestedPerPage): int
    {
        $perPage = (int) $requestedPerPage;
        
        if (!in_array($perPage, self::PER_PAGE_OPTIONS)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }
        
        return $perPage;
    }

    /**
     * Handle deletion response
     */
    private function deletionResponse(Request $request, bool $success, string $message, ?string $redirectUrl = null)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return $this->cleanJsonResponse([
                'success' => $success,
                'message' => $message,
                'redirect_url' => $redirectUrl,
            ], $success ? 200 : 422);
        }
        
        if ($success && $redirectUrl) {
            return redirect($redirectUrl)->with('success', $message);
        }
        
        return back()->with('error', $message);
    }

    /**
     * Helper method to return clean JSON response
     */
    protected function cleanJsonResponse($data, $status = 200)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-cache, must-revalidate');
        
        $jsonString = json_encode($data);
        if (strpos($jsonString, "\xEF\xBB\xBF") !== false) {
            $jsonString = str_replace("\xEF\xBB\xBF", '', $jsonString);
            $data = json_decode($jsonString, true);
        }
        
        return response()->json($data, $status);
    }

    /**
     * Get invitation templates for security personnel
     */
    private function getInvitationTemplates(): array
    {
        return [
            'security_welcome' => [
                'name' => 'Security Welcome',
                'description' => 'Welcome email for new security personnel',
                'channels' => ['email', 'sms'],
                'default' => true,
            ],
            'security_orientation' => [
                'name' => 'Security Orientation',
                'description' => 'Orientation details for new security staff',
                'channels' => ['email'],
                'default' => false,
            ],
            'security_training' => [
                'name' => 'Security Training',
                'description' => 'Training session invitation',
                'channels' => ['email', 'sms', 'whatsapp'],
                'default' => false,
            ],
            'password_setup' => [
                'name' => 'Password Setup',
                'description' => 'Setup password for new account',
                'channels' => ['email'],
                'default' => false,
            ],
        ];
    }

    /**
     * Get invitation statistics
     */
    private function getInvitationStatistics(User $areaSupervisor): array
    {
        $postIds = $this->getAccessiblePosts($areaSupervisor)->pluck('id')->toArray();

        $baseQuery = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('id', '!=', $areaSupervisor->id);

        if (!empty($postIds)) {
            $baseQuery->where(function($q) use ($postIds, $areaSupervisor) {
                $q->whereHas('supervisorAssignments', function($sq) use ($postIds) {
                    $sq->whereIn('security_post_id', $postIds)
                       ->where('is_active', true);
                });
                
                if ($this->hasScheduleRelationship()) {
                    $q->orWhereHas('schedules', function($sq) use ($postIds) {
                        $sq->whereIn('security_post_id', $postIds)
                           ->where('status', 'active');
                    });
                }
                
                $q->orWhere('created_by', $areaSupervisor->id);
            });
        }

        $personnel = $baseQuery->get();
        
        $pending = 0;
        $sent = 0;
        $accepted = 0;
        $failed = 0;
        $expired = 0;
        
        foreach ($personnel as $user) {
            $lastInvitation = $user->invitations()->latest()->first();
            if ($lastInvitation) {
                switch ($lastInvitation->status) {
                    case 'pending':
                        $pending++;
                        break;
                    case 'sent':
                        $sent++;
                        break;
                    case 'accepted':
                        $accepted++;
                        break;
                    case 'failed':
                        $failed++;
                        break;
                    case 'expired':
                        $expired++;
                        break;
                }
            }
        }
        
        return [
            'pending' => $pending,
            'sent' => $sent,
            'accepted' => $accepted,
            'failed' => $failed,
            'expired' => $expired,
            'total' => $personnel->count(),
            'invited_percentage' => $personnel->count() > 0 
                ? round(($sent + $pending + $accepted) / $personnel->count() * 100, 1) 
                : 0,
        ];
    }

    /**
     * Send invitation directly
     */
    private function sendInvitationDirect(User $user, array $data, User $currentUser): array
    {
        try {
            Log::info('Sending direct invitation for security personnel', [
                'user_id' => $user->id,
                'channels' => $data['invitation_channels'] ?? [],
                'invitation_type' => $data['invitation_type'] ?? 'welcome',
                'sent_by' => $currentUser->id
            ]);

            $channels = $data['invitation_channels'] ?? ['email'];
            $invitationType = $data['invitation_type'] ?? 'welcome';
            $expiresInDays = $data['expires_in_days'] ?? 7;
            $customMessage = $data['custom_message'] ?? null;

            $availableChannels = $this->getAvailableChannelsForUser($user);
            
            $validChannels = array_filter($channels, function($channel) use ($availableChannels) {
                return in_array($channel, $availableChannels);
            });

            if (empty($validChannels)) {
                return [
                    'success' => false,
                    'message' => 'No available communication channels for this user.'
                ];
            }

            $invitation = UserInvitation::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'phone' => $user->phone,
                'token' => \Illuminate\Support\Str::random(64),
                'expires_at' => now()->addDays($expiresInDays),
                'type' => $invitationType,
                'channels' => json_encode($validChannels),
                'status' => 'pending',
                'created_by' => $currentUser->id,
                'metadata' => json_encode([
                    'sent_by' => $currentUser->id,
                    'sent_by_name' => $currentUser->name,
                    'sent_at' => now()->toISOString(),
                    'custom_message' => $customMessage,
                    'user_type' => $user->type,
                    'security_personnel' => true,
                    'invitation_type' => $invitationType,
                ])
            ]);

            $sendResult = $this->sendInvitationViaChannels($user, $invitation, $validChannels, $customMessage);

            if ($sendResult['success']) {
                $invitation->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } else {
                $invitation->update([
                    'status' => 'failed',
                    'metadata' => json_encode(array_merge(
                        json_decode($invitation->metadata ?? '{}', true) ?? [],
                        ['error' => $sendResult['message'] ?? 'Unknown error']
                    ))
                ]);
            }

            return [
                'success' => $sendResult['success'],
                'message' => $sendResult['message'] ?? 'Invitation processed',
                'invitation_url' => $sendResult['invitation_url'] ?? null,
                'channels_successful' => $sendResult['channels_successful'] ?? [],
                'failed_channels' => $sendResult['failed_channels'] ?? [],
                'invitation_id' => $invitation->id,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send direct invitation: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'error_trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Send invitation via multiple channels
     */
    private function sendInvitationViaChannels(User $user, $invitation, array $channels, ?string $customMessage = null): array
    {
        $results = [
            'success' => false,
            'channels_successful' => [],
            'failed_channels' => [],
            'message' => '',
            'invitation_url' => null,
        ];

        $invitationUrl = route('invitation.accept', ['token' => $invitation->token]);

        foreach ($channels as $channel) {
            try {
                $sent = false;
                
                switch ($channel) {
                    case 'email':
                        $sent = $this->sendInvitationEmail($user, $invitation, $invitationUrl, $customMessage);
                        break;
                    case 'sms':
                        $sent = $this->sendInvitationSms($user, $invitation, $invitationUrl, $customMessage);
                        break;
                    case 'whatsapp':
                        $sent = $this->sendInvitationWhatsApp($user, $invitation, $invitationUrl, $customMessage);
                        break;
                    default:
                        continue 2;
                }

                if ($sent) {
                    $results['channels_successful'][] = $channel;
                    Log::info("Invitation sent via {$channel}", [
                        'user_id' => $user->id,
                        'invitation_id' => $invitation->id
                    ]);
                } else {
                    $results['failed_channels'][] = $channel;
                    Log::warning("Invitation failed via {$channel}", [
                        'user_id' => $user->id,
                        'invitation_id' => $invitation->id
                    ]);
                }

            } catch (\Exception $e) {
                $results['failed_channels'][] = $channel;
                Log::error("Invitation error via {$channel}: " . $e->getMessage(), [
                    'user_id' => $user->id,
                    'invitation_id' => $invitation->id
                ]);
            }
        }

        $results['invitation_url'] = $invitationUrl;
        $results['success'] = count($results['channels_successful']) > 0;
        $results['message'] = $results['success'] 
            ? 'Invitation sent via ' . implode(', ', $results['channels_successful'])
            : 'Failed to send invitation via any channel';

        return $results;
    }

    /**
     * Get available channels for a user
     */
    private function getAvailableChannelsForUser(User $user): array
    {
        $channels = [];
        
        if (!empty($user->email)) {
            $channels[] = 'email';
        }
        
        if (!empty($user->phone)) {
            $channels[] = 'sms';
            $channels[] = 'whatsapp';
        }
        
        return $channels;
    }

    /**
     * Send invitation via email
     */
    private function sendInvitationEmail(User $user, $invitation, string $invitationUrl, ?string $customMessage = null): bool
    {
        try {
            Log::info('Email invitation would be sent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'invitation_url' => $invitationUrl,
                'token' => $invitation->token
            ]);
            
            \Mail::to($user->email)->send(new \App\Mail\SecurityInvitationMail($user, $invitation, $invitationUrl, $customMessage));
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send invitation email: ' . $e->getMessage(), [
                'user_id' => $user->id
            ]);
            return false;
        }
    }

    /**
     * Send invitation via SMS
     */
    private function sendInvitationSms(User $user, $invitation, string $invitationUrl, ?string $customMessage = null): bool
    {
        try {
            Log::info('SMS invitation would be sent', [
                'user_id' => $user->id,
                'phone' => $user->phone,
                'invitation_url' => $invitationUrl,
                'token' => $invitation->token
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send invitation SMS: ' . $e->getMessage(), [
                'user_id' => $user->id
            ]);
            return false;
        }
    }

    /**
     * Send invitation via WhatsApp
     */
    private function sendInvitationWhatsApp(User $user, $invitation, string $invitationUrl, ?string $customMessage = null): bool
    {
        try {
            Log::info('WhatsApp invitation would be sent', [
                'user_id' => $user->id,
                'phone' => $user->phone,
                'invitation_url' => $invitationUrl,
                'token' => $invitation->token
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send invitation WhatsApp: ' . $e->getMessage(), [
                'user_id' => $user->id
            ]);
            return false;
        }
    }

    // ==================== CORE HELPER METHODS ====================

    /**
     * Check if a user is an Area Supervisor
     */
    private function isAreaSupervisor(User $user): bool
    {
        Log::info('Checking area supervisor status', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'can_be_supervisor' => $user->can_be_supervisor,
            'roles' => $user->roles->pluck('slug')->toArray(),
        ]);

        if ($user->hasRole('area_supervisor') || $user->hasRole('post_commander')) {
            Log::info('User is area supervisor by role', ['user_id' => $user->id]);
            return true;
        }

        // ✅ UPDATED: Check by supervisor assignment, not level
        $hasSupervisorAssignment = SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereIn('supervisor_type', [
                    'area_supervisor', 
                    'post_commander', 
                    'section_lead'
                ])
                ->orWhereJsonContains('metadata->permissions', 'manage_personnel');
            })
            ->exists();

        if ($hasSupervisorAssignment) {
            Log::info('User is area supervisor by assignment', ['user_id' => $user->id]);
            return true;
        }

        // ✅ UPDATED: Check by can_be_supervisor flag with role
        if ($user->can_be_supervisor && $user->hasRole('area_supervisor')) {
            Log::info('User is area supervisor by capability and role', [
                'user_id' => $user->id,
                'can_be_supervisor' => $user->can_be_supervisor
            ]);
            return true;
        }

        Log::info('User is NOT an area supervisor', ['user_id' => $user->id]);
        return false;
    }

    /**
     * Check if a user can add/manage security personnel
     */
    private function canManageSecurityPersonnel(User $user): bool
    {
        Log::info('🔍 ========== START canManageSecurityPersonnel ==========', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_type' => $user->type,
            'can_be_supervisor' => $user->can_be_supervisor ?? 'NULL',
            'roles' => $user->roles->pluck('slug')->toArray(),
        ]);

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            Log::info('✅ User can manage personnel (Super Admin/Admin)', ['user_id' => $user->id]);
            return true;
        }

        $isAreaSupervisor = $this->isAreaSupervisor($user);
        
        if ($isAreaSupervisor) {
            Log::info('✅ User can manage personnel (Area Supervisor)', ['user_id' => $user->id]);
            return true;
        }

        $hasPermission = SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereJsonContains('metadata->permissions', 'manage_personnel')
                  ->orWhereJsonContains('metadata->permissions', 'add_personnel')
                  ->orWhereJsonContains('metadata->permissions', 'manage_security_team');
            })
            ->exists();

        if ($hasPermission) {
            Log::info('✅ User can manage personnel (has permission in assignment)', ['user_id' => $user->id]);
            return true;
        }

        // ✅ UPDATED: Check by can_be_supervisor and role
        if ($user->can_be_supervisor && $user->hasRole('section_lead')) {
            Log::info('✅ User can manage personnel (Section Lead with eligibility)', [
                'user_id' => $user->id,
                'can_be_supervisor' => $user->can_be_supervisor
            ]);
            return true;
        }

        Log::info('❌ User cannot manage personnel (no permission)', ['user_id' => $user->id]);
        return false;
    }

    /**
     * Get security personnel based on tab selection
     */
    private function getScopedSecurityPersonnel(User $areaSupervisor, ?Request $request = null, string $tab = 'assigned')
    {
        $postIds = $this->getAccessiblePosts($areaSupervisor)->pluck('id')->toArray();

        $query = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('id', '!=', $areaSupervisor->id);

        if ($tab === 'assigned') {
            $query->where(function($q) use ($postIds, $areaSupervisor) {
                if (!empty($postIds)) {
                    $q->whereHas('supervisorAssignments', function($sq) use ($postIds) {
                        $sq->whereIn('security_post_id', $postIds)
                           ->where('is_active', true);
                    });
                    
                    if ($this->hasScheduleRelationship()) {
                        $q->orWhereHas('schedules', function($sq) use ($postIds) {
                            $sq->whereIn('security_post_id', $postIds)
                               ->where('status', 'active');
                        });
                    }
                }
                
                $q->orWhere('created_by', $areaSupervisor->id);
            });
        } elseif ($tab === 'unassigned') {
            $query->whereDoesntHave('supervisorAssignments', function($q) {
                $q->where('is_active', true);
            });
            
            if ($this->hasScheduleRelationship()) {
                $query->whereDoesntHave('schedules', function($q) {
                    $q->where('status', 'active');
                });
            }
        }

        $query->with(['roles', 'supervisorAssignments' => function($q) {
            $q->where('is_active', true);
        }]);

        if ($request && $request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // ✅ UPDATED: Filter by can_be_supervisor instead of supervisor_level
        if ($request && $request->filled('can_be_supervisor')) {
            if ($request->can_be_supervisor === 'yes') {
                $query->where('can_be_supervisor', true);
            } elseif ($request->can_be_supervisor === 'no') {
                $query->where('can_be_supervisor', false);
            }
        }

        if ($request && $request->filled('security_post_id') && !empty($postIds)) {
            $postId = (int) $request->security_post_id;
            if (in_array($postId, $postIds) || $areaSupervisor->hasRole('super-admin')) {
                $query->whereHas('supervisorAssignments', function($q) use ($postId) {
                    $q->where('security_post_id', $postId)
                      ->where('is_active', true);
                });
            }
        }

        if ($request && $request->filled('status')) {
            $query->where('status', $request->status);
        }

        $query->orderBy('name');

        $perPage = $request ? (int) $request->get('per_page', 20) : 20;
        $perPage = in_array($perPage, [10, 15, 20, 25, 50, 100]) ? $perPage : 20;

        return $query->paginate($perPage);
    }

    /**
     * Check if the Schedule relationship exists
     */
    private function hasScheduleRelationship(): bool
    {
        return class_exists('App\Models\Schedule') && 
               method_exists('App\Models\User', 'schedules');
    }

    /**
     * Get posts accessible by the area supervisor
     */
    private function getAccessiblePosts(User $areaSupervisor)
    {
        $assignments = SecuritySupervisorAssignment::where('user_id', $areaSupervisor->id)
            ->where(function($q) {
                $q->where('supervisor_type', 'area_supervisor')
                  ->orWhere('supervisor_type', 'post_commander')
                  ->orWhereJsonContains('metadata->permissions', 'manage_personnel');
            })
            ->where('is_active', true)
            ->get();

        if ($assignments->isEmpty()) {
            if ($areaSupervisor->hasRole('area_supervisor') || $areaSupervisor->hasRole('post_commander')) {
                return SecurityPost::active()->orderBy('name')->get();
            }
            return collect([]);
        }

        $hasAllPosts = $assignments->contains(function($assignment) {
            return is_null($assignment->security_post_id);
        });

        if ($hasAllPosts) {
            return SecurityPost::active()->orderBy('name')->get();
        }

        $postIds = $assignments->pluck('security_post_id')->filter()->unique()->toArray();
        
        if (empty($postIds)) {
            return collect([]);
        }
        
        return SecurityPost::active()
            ->whereIn('id', $postIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * Check if personnel is within the area supervisor's scope
     */
    private function isInScope(User $areaSupervisor, User $personnel): bool
    {
        if ($personnel->id === $areaSupervisor->id) {
            return false;
        }

        if ($this->canManageSecurityPersonnel($areaSupervisor)) {
            Log::info('✅ Area Supervisor can see all personnel', [
                'supervisor_id' => $areaSupervisor->id,
                'personnel_id' => $personnel->id,
            ]);
            return true;
        }

        if ($personnel->created_by == $areaSupervisor->id) {
            return true;
        }

        $postIds = $this->getAccessiblePosts($areaSupervisor)->pluck('id')->toArray();

        if (empty($postIds)) {
            return false;
        }

        $hasAssignment = SecuritySupervisorAssignment::where('user_id', $personnel->id)
            ->whereIn('security_post_id', $postIds)
            ->where('is_active', true)
            ->exists();

        if ($hasAssignment) {
            return true;
        }

        if ($this->hasScheduleRelationship()) {
            $hasSchedule = $personnel->schedules()
                ->whereIn('security_post_id', $postIds)
                ->where('status', 'active')
                ->exists();

            if ($hasSchedule) {
                return true;
            }
        }

        return false;
    }

    /**
     * Assign supervisor role to a user - NO AUTO-POST ASSIGNMENT
     * 
     * This method ONLY assigns the supervisor role (can_be_supervisor).
     * Post assignments must be created separately using assignPersonnelToPost().
     */
    private function assignSupervisorRole(
        User $personnel,
        User $assignedBy,
        int $level,
        ?int $securityPostId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        bool $isPrimarySupervisor = false
        // ✅ REMOVED: supervisorScore parameter
    ): SecuritySupervisorAssignment
    {
        if (!in_array($level, [1, 2])) {
            throw new \Exception('Invalid supervisor level. Area Supervisors can only assign Team Lead (1) and Section Lead (2).');
        }

        DB::beginTransaction();

        try {
            $this->cleanupOrphanedSupervisorAssignments($personnel);

            // ✅ Check if user already has a supervisor role assignment (without post)
            $existingAssignment = SecuritySupervisorAssignment::where('user_id', $personnel->id)
                ->whereNull('security_post_id')
                ->where('is_active', true)
                ->first();

            if ($existingAssignment) {
                $existingAssignment->update([
                    'assigned_by' => $assignedBy->id,
                    'start_date' => $startDate ?? now(),
                    'end_date' => $endDate,
                    'supervisor_type' => $this->getSupervisorTypeFromLevel($level),
                    'is_active' => true,
                    'is_primary_supervisor' => $isPrimarySupervisor,
                    'can_override_checkins' => true,
                    'can_approve_swaps' => true,
                    'can_approve_overtime' => true,
                    'can_review_incidents' => true,
                    'can_verify_checkins' => true,
                    'can_request_backup' => true,
                    'can_approve_breaks' => true,
                    'can_escalate_issues' => true,
                    'can_view_all_schedules' => true,
                    'can_edit_schedules' => false,
                    'metadata' => [
                        'assigned_by_supervisor_id' => $assignedBy->id,
                        'assigned_by_supervisor_name' => $assignedBy->name,
                        'assigned_level' => $level,
                        'assigned_level_name' => $this->getLevelName($level),
                        'permissions' => $this->getSupervisorPermissions($level),
                        'updated_at' => now()->toISOString(),
                        'assignment_type' => 'role_only',
                        'post_assigned' => false,
                    ],
                    'updated_at' => now(),
                ]);

                $personnel->update([
                    'can_be_supervisor' => true,
                    // ✅ REMOVED: supervisor_level, supervisor_score
                ]);

                DB::commit();
                return $existingAssignment;
            }

            // ✅ Create role assignment WITHOUT post
            $assignment = SecuritySupervisorAssignment::create([
                'user_id' => $personnel->id,
                'security_post_id' => null,
                'assigned_by' => $assignedBy->id,
                'start_date' => $startDate ?? now(),
                'end_date' => $endDate,
                'supervisor_type' => $this->getSupervisorTypeFromLevel($level),
                'is_active' => true,
                'is_primary_supervisor' => $isPrimarySupervisor,
                'can_override_checkins' => true,
                'can_approve_swaps' => true,
                'can_approve_overtime' => true,
                'can_review_incidents' => true,
                'can_verify_checkins' => true,
                'can_request_backup' => true,
                'can_approve_breaks' => true,
                'can_escalate_issues' => true,
                'can_view_all_schedules' => true,
                'can_edit_schedules' => false,
                'metadata' => [
                    'assigned_by_supervisor_id' => $assignedBy->id,
                    'assigned_by_supervisor_name' => $assignedBy->name,
                    'assigned_level' => $level,
                    'assigned_level_name' => $this->getLevelName($level),
                    'permissions' => $this->getSupervisorPermissions($level),
                    'assignment_type' => 'role_only',
                    'post_assigned' => false,
                    'created_via' => 'role_assignment',
                ],
            ]);

            $personnel->update([
                'can_be_supervisor' => true,
                // ✅ REMOVED: supervisor_level, supervisor_score
            ]);

            Log::info('Supervisor role assigned (NO POST)', [
                'personnel_id' => $personnel->id,
                'personnel_name' => $personnel->name,
                'level' => $level,
                'level_name' => $this->getLevelName($level),
                'assigned_by' => $assignedBy->id,
                'assignment_id' => $assignment->id,
            ]);

            DB::commit();
            return $assignment;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign supervisor role: ' . $e->getMessage(), [
                'personnel_id' => $personnel->id,
                'level' => $level,
                'error_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get supervisor permissions based on level
     */
    private function getSupervisorPermissions(int $level): array
    {
        $basePermissions = [
            'view_schedules',
            'view_attendance',
            'view_team_members',
        ];

        $levelPermissions = match($level) {
            1 => array_merge($basePermissions, [
                'manage_team_schedule',
                'approve_swap_requests',
                'report_incidents',
            ]),
            2 => array_merge($basePermissions, [
                'manage_team_schedule',
                'approve_swap_requests',
                'report_incidents',
                'manage_personnel',
                'add_personnel',
                'assign_tasks',
                'view_performance',
            ]),
            3 => array_merge($basePermissions, [
                'manage_team_schedule',
                'approve_swap_requests',
                'report_incidents',
                'manage_personnel',
                'add_personnel',
                'assign_tasks',
                'view_performance',
                'manage_security_team',
                'full_access',
            ]),
            default => $basePermissions,
        };

        return $levelPermissions;
    }

    /**
     * Assign personnel to a security post - NO AUTO-SCHEDULE CREATION
     * 
     * This method ONLY creates the SecuritySupervisorAssignment.
     * Schedules must be created separately using createPersonnelSchedule().
     */
    private function assignPersonnelToPost(User $personnel, int $postId, User $assignedBy): void
    {
        $post = SecurityPost::find($postId);
        if (!$post) {
            throw new \Exception('Security post not found.');
        }

        Log::info('🔍 assignPersonnelToPost called (NO AUTO-SCHEDULE)', [
            'personnel_id' => $personnel->id,
            'personnel_name' => $personnel->name,
            'post_id' => $postId,
            'post_name' => $post->name,
            'max_personnel' => $post->max_personnel ?? 1,
            'assigned_by' => $assignedBy->id,
        ]);

        if (!$post->is_active) {
            throw new \Exception('Cannot assign personnel to an inactive post.');
        }

        $currentStaffCount = SecuritySupervisorAssignment::where('security_post_id', $postId)
            ->where('is_active', true)
            ->count();

        Log::info('🔍 Capacity check in assignPersonnelToPost', [
            'post_id' => $postId,
            'current_staff' => $currentStaffCount,
            'max_personnel' => $post->max_personnel,
        ]);

        if ($currentStaffCount >= $post->max_personnel) {
            throw new \Exception("Post '{$post->name}' is fully staffed (max: {$post->max_personnel}). Cannot assign more personnel.");
        }

        DB::beginTransaction();

        try {
            $this->cleanupOrphanedSchedules($personnel);

            $existingAssignment = SecuritySupervisorAssignment::where('user_id', $personnel->id)
                ->where('security_post_id', $postId)
                ->where('is_active', true)
                ->first();

            if ($existingAssignment) {
                Log::info('ℹ️ Personnel already has active assignment, updating', [
                    'personnel_id' => $personnel->id,
                    'post_id' => $postId,
                    'assignment_id' => $existingAssignment->id,
                ]);

                $existingAssignment->update([
                    'assigned_by' => $assignedBy->id,
                    'start_date' => now(),
                    'updated_at' => now(),
                    'metadata' => array_merge($existingAssignment->metadata ?? [], [
                        'last_assigned_by' => $assignedBy->id,
                        'last_assigned_by_name' => $assignedBy->name,
                        'last_assigned_at' => now()->toISOString(),
                        'assignment_updated' => true,
                        'schedule_auto_created' => false,
                    ]),
                ]);

                DB::commit();
                return;
            }

            $existingInactive = SecuritySupervisorAssignment::where('user_id', $personnel->id)
                ->where('security_post_id', $postId)
                ->where('is_active', false)
                ->first();

            if ($existingInactive) {
                Log::info('🔄 Found inactive assignment, reactivating', [
                    'personnel_id' => $personnel->id,
                    'post_id' => $postId,
                    'assignment_id' => $existingInactive->id,
                ]);

                $existingInactive->update([
                    'is_active' => true,
                    'assigned_by' => $assignedBy->id,
                    'start_date' => now(),
                    'updated_at' => now(),
                    'metadata' => array_merge($existingInactive->metadata ?? [], [
                        'reactivated_by' => $assignedBy->id,
                        'reactivated_by_name' => $assignedBy->name,
                        'reactivated_at' => now()->toISOString(),
                        'schedule_auto_created' => false,
                    ]),
                ]);

                DB::commit();
                return;
            }

            $deactivatedCount = SecuritySupervisorAssignment::where('user_id', $personnel->id)
                ->where('is_active', true)
                ->where('security_post_id', '!=', $postId)
                ->update(['is_active' => false]);

            if ($deactivatedCount > 0) {
                Log::info('🔍 Deactivated other active assignments', [
                    'personnel_id' => $personnel->id,
                    'deactivated_count' => $deactivatedCount,
                ]);
            }

            Log::info('✅ Creating new assignment (NO AUTO-SCHEDULE)', [
                'personnel_id' => $personnel->id,
                'post_id' => $postId,
                'slot' => ($currentStaffCount + 1) . '/' . $post->max_personnel,
            ]);

            $newAssignment = SecuritySupervisorAssignment::create([
                'user_id' => $personnel->id,
                'security_post_id' => $postId,
                'assigned_by' => $assignedBy->id,
                'start_date' => now(),
                'supervisor_type' => 'post_supervisor',
                'is_active' => true,
                'is_primary_supervisor' => false,
                'can_override_checkins' => true,
                'can_approve_swaps' => true,
                'can_approve_overtime' => true,
                'can_review_incidents' => true,
                'can_verify_checkins' => true,
                'can_request_backup' => true,
                'can_approve_breaks' => true,
                'can_escalate_issues' => true,
                'can_view_all_schedules' => true,
                'can_edit_schedules' => false,
                'metadata' => [
                    'assigned_by_supervisor_id' => $assignedBy->id,
                    'assigned_by_supervisor_name' => $assignedBy->name,
                    'assigned_at' => now()->toISOString(),
                    'permissions' => ['view_schedules', 'view_attendance'],
                    'created_via' => 'post_assignment',
                    'slot_number' => $currentStaffCount + 1,
                    'total_slots' => $post->max_personnel,
                    'schedule_auto_created' => false,
                ],
            ]);

            Log::info('✅ New assignment created successfully (NO AUTO-SCHEDULE)', [
                'personnel_id' => $personnel->id,
                'post_id' => $postId,
                'assignment_id' => $newAssignment->id,
                'slot' => ($currentStaffCount + 1) . '/' . $post->max_personnel,
            ]);

            DB::commit();

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();

            if ($e->errorInfo[1] == 1062) {
                Log::warning('⚠️ Duplicate entry detected, attempting recovery', [
                    'personnel_id' => $personnel->id,
                    'post_id' => $postId,
                ]);

                $existing = SecuritySupervisorAssignment::where('user_id', $personnel->id)
                    ->where('security_post_id', $postId)
                    ->first();

                if ($existing) {
                    Log::info('🔄 Found existing record, updating instead of creating duplicate', [
                        'assignment_id' => $existing->id,
                        'was_active' => $existing->is_active,
                    ]);

                    $existing->update([
                        'is_active' => true,
                        'assigned_by' => $assignedBy->id,
                        'start_date' => now(),
                        'updated_at' => now(),
                        'metadata' => array_merge($existing->metadata ?? [], [
                            'recovered_by' => $assignedBy->id,
                            'recovered_at' => now()->toISOString(),
                            'schedule_auto_created' => false,
                        ]),
                    ]);

                    DB::commit();
                    return;
                }
            }

            Log::error('❌ Failed to assign personnel to post: ' . $e->getMessage(), [
                'personnel_id' => $personnel->id,
                'post_id' => $postId,
                'error_trace' => $e->getTraceAsString(),
            ]);
            throw $e;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Failed to assign personnel to post: ' . $e->getMessage(), [
                'personnel_id' => $personnel->id,
                'post_id' => $postId,
                'error_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Clean up orphaned schedule entries for a user
     */
    private function cleanupOrphanedSchedules(User $personnel): void
    {
        if (!class_exists('App\Models\SecuritySchedule')) {
            return;
        }

        try {
            $hasAssignmentRelation = method_exists('App\Models\SecuritySchedule', 'assignment');
            
            if (!$hasAssignmentRelation) {
                return;
            }

            $orphanedSchedules = SecuritySchedule::where('security_user_id', $personnel->id)
                ->whereIn('status', ['scheduled', 'active'])
                ->whereDoesntHave('assignment', function($q) {
                    $q->where('is_active', true);
                })
                ->get();

            if ($orphanedSchedules->isNotEmpty()) {
                foreach ($orphanedSchedules as $schedule) {
                    if ($schedule->created_at->diffInDays(now()) > 7) {
                        $schedule->update([
                            'status' => 'abandoned',
                            'notes' => 'Orphaned schedule cleaned up - no active assignment found',
                        ]);
                    } else {
                        $schedule->update([
                            'status' => 'pending',
                            'notes' => 'Schedule waiting for assignment - cleaned up',
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to cleanup orphaned schedules: ' . $e->getMessage(), [
                'personnel_id' => $personnel->id,
            ]);
        }
    }

    /**
     * Create a schedule for personnel (separate from assignment)
     * This should be called explicitly when needed
     */
    public function createPersonnelSchedule(Request $request, $userId)
    {
        $currentUser = Auth::user();
        
        if (!$this->canManageSecurityPersonnel($currentUser)) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'security_post_id' => 'required|exists:security_posts,id',
            'assignment_date' => 'required|date',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s|after:start_time',
            'shift_id' => 'nullable|exists:security_shifts,id',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->cleanJsonResponse([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $personnel = User::findOrFail($userId);

        try {
            $schedule = SecuritySchedule::create([
                'security_user_id' => $personnel->id,
                'security_post_id' => $request->security_post_id,
                'assignment_date' => $request->assignment_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'shift_id' => $request->shift_id,
                'status' => 'scheduled',
                'assigned_by' => $currentUser->id,
                'notes' => $request->notes ?? 'Manually created schedule',
                'include_breaks' => $request->boolean('include_breaks', true),
                'handover_completed' => 0,
                'is_approved' => 0,
                'late_minutes' => 0,
                'overtime_minutes' => 0,
                'break_duration' => 0,
                'rotation_swap_count' => 0,
                'is_rotated' => 0,
                'rotation_preference_score' => 5,
                'rotation_sequence_number' => 0,
            ]);

            Log::info('Schedule created for personnel', [
                'personnel_id' => $personnel->id,
                'schedule_id' => $schedule->id,
                'created_by' => $currentUser->id,
            ]);

            return $this->cleanJsonResponse([
                'success' => true,
                'message' => 'Schedule created successfully!',
                'schedule' => $schedule
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create schedule: ' . $e->getMessage());
            
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to create schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get working hours information for a post
     */
    private function getWorkingHoursInfo(SecurityPost $post): array
    {
        $workingHours = is_array($post->working_hours) ? $post->working_hours : [];
        
        return [
            'start' => $workingHours['start'] ?? null,
            'end' => $workingHours['end'] ?? null,
            'is_overnight' => $workingHours['is_overnight'] ?? false,
            'duration_hours' => $workingHours['duration_hours'] ?? 0,
            'display' => $workingHours['display'] ?? 'N/A',
            'formatted_start' => $workingHours['start'] ? Carbon::parse($workingHours['start'])->format('g:i A') : null,
            'formatted_end' => $workingHours['end'] ? Carbon::parse($workingHours['end'])->format('g:i A') : null,
        ];
    }

    /**
     * Update personnel's post assignment
     */
    private function updatePersonnelPost(User $personnel, ?int $postId, User $assignedBy): void
    {
        if (class_exists('App\Models\Schedule')) {
            \App\Models\Schedule::where('user_id', $personnel->id)
                ->where('status', 'active')
                ->update(['status' => 'inactive']);
        }

        SecuritySupervisorAssignment::where('user_id', $personnel->id)
            ->where('is_active', true)
            ->whereNotNull('security_post_id')
            ->update(['is_active' => false]);

        if ($postId) {
            $this->assignPersonnelToPost($personnel, $postId, $assignedBy);
        }
    }

    /**
     * Get supervisor type from level
     */
    private function getSupervisorTypeFromLevel(int $level): string
    {
        return match($level) {
            1 => 'team_lead',
            2 => 'section_lead',
            3 => 'post_commander',
            default => 'post_supervisor',
        };
    }

    /**
     * Get level name
     */
    private function getLevelName(int $level): string
    {
        return match($level) {
            1 => 'Team Lead',
            2 => 'Section Lead',
            3 => 'Post Commander',
            default => 'Not a Supervisor',
        };
    }

    /**
     * Get personnel statistics for dashboard
     */
    private function getPersonnelStatistics(User $areaSupervisor): array
    {
        $postIds = $this->getAccessiblePosts($areaSupervisor)->pluck('id')->toArray();

        $baseQuery = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('id', '!=', $areaSupervisor->id);

        if (!empty($postIds)) {
            $baseQuery->where(function($q) use ($postIds, $areaSupervisor) {
                $q->whereHas('supervisorAssignments', function($sq) use ($postIds) {
                    $sq->whereIn('security_post_id', $postIds)
                       ->where('is_active', true);
                });
                
                if ($this->hasScheduleRelationship()) {
                    $q->orWhereHas('schedules', function($sq) use ($postIds) {
                        $sq->whereIn('security_post_id', $postIds)
                           ->where('status', 'active');
                    });
                }
                
                $q->orWhere('created_by', $areaSupervisor->id);
                
                $q->orWhereDoesntHave('supervisorAssignments', function($sq) {
                    $sq->where('is_active', true);
                });
                
                if ($this->hasScheduleRelationship()) {
                    $q->orWhereDoesntHave('schedules', function($sq) {
                        $sq->where('status', 'active');
                    });
                }
            });
        }

        return [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('status', User::STATUS_ACTIVE)->count(),
            'pending' => (clone $baseQuery)->where('status', User::STATUS_PENDING)->count(),
            'suspended' => (clone $baseQuery)->where('status', User::STATUS_SUSPENDED)->count(),
            // ✅ UPDATED: Use can_be_supervisor instead of supervisor_level
            'supervisors' => (clone $baseQuery)->where('can_be_supervisor', true)->count(),
            'team_leads' => (clone $baseQuery)->whereHas('supervisorAssignments', function($q) {
                $q->where('supervisor_type', 'team_lead')
                  ->where('is_active', true);
            })->count(),
            'section_leads' => (clone $baseQuery)->whereHas('supervisorAssignments', function($q) {
                $q->where('supervisor_type', 'section_lead')
                  ->where('is_active', true);
            })->count(),
            'unassigned' => User::where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('id', '!=', $areaSupervisor->id)
                ->whereDoesntHave('supervisorAssignments', function($q) {
                    $q->where('is_active', true);
                })
                ->count(),
        ];
    }

    /**
     * Get communication service status with caching
     */
    private function getCommunicationServiceStatus(): array
    {
        try {
            return cache()->remember('security_communication_service_status', 300, function () {
                $status = [];
                
                try {
                    if (class_exists(\App\Services\SmsService::class)) {
                        $smsService = app(\App\Services\SmsService::class);
                        $status['sms'] = $smsService->getSystemStatus() ?? [];
                    } else {
                        $status['sms'] = [
                            'enabled' => false,
                            'system_ready' => false,
                            'status' => 'unavailable',
                            'message' => 'SMS service not configured',
                            'can_send' => false,
                            'health' => 'unavailable',
                        ];
                    }
                } catch (\Exception $e) {
                    $status['sms'] = [
                        'enabled' => false,
                        'system_ready' => false,
                        'status' => 'error',
                        'message' => 'SMS service unavailable: ' . $e->getMessage(),
                        'can_send' => false,
                        'health' => 'unhealthy',
                    ];
                }
                
                try {
                    if (class_exists(\App\Services\WhatsAppService::class)) {
                        $whatsappService = app(\App\Services\WhatsAppService::class);
                        $status['whatsapp'] = $whatsappService->getSystemStatus() ?? [];
                    } else {
                        $status['whatsapp'] = [
                            'enabled' => false,
                            'system_ready' => false,
                            'status' => 'unavailable',
                            'message' => 'WhatsApp service not configured',
                            'can_send' => false,
                            'health' => 'unavailable',
                        ];
                    }
                } catch (\Exception $e) {
                    $status['whatsapp'] = [
                        'enabled' => false,
                        'system_ready' => false,
                        'status' => 'error',
                        'message' => 'WhatsApp service unavailable: ' . $e->getMessage(),
                        'can_send' => false,
                        'health' => 'unhealthy',
                    ];
                }
                
                try {
                    if (class_exists(\App\Services\EmailService::class)) {
                        $emailService = app(\App\Services\EmailService::class);
                        $status['email'] = $emailService->getSystemStatus() ?? [];
                    } else {
                        $status['email'] = [
                            'enabled' => true,
                            'system_ready' => true,
                            'status' => 'active',
                            'message' => 'Email service available (default)',
                            'can_send' => true,
                            'health' => 'healthy',
                            'provider' => 'System Default',
                            'statistics' => ['success_rate' => 100],
                            'limits' => ['daily_limit' => ['max_emails_per_day' => 1000, 'used_today' => 0]],
                        ];
                    }
                } catch (\Exception $e) {
                    $status['email'] = [
                        'enabled' => true,
                        'system_ready' => true,
                        'status' => 'degraded',
                        'message' => 'Email service degraded: ' . $e->getMessage(),
                        'can_send' => true,
                        'health' => 'degraded',
                        'provider' => 'Fallback',
                        'statistics' => ['success_rate' => 80],
                        'limits' => ['daily_limit' => ['max_emails_per_day' => 500, 'used_today' => 0]],
                    ];
                }
                
                $status['multi_channel'] = [
                    'enabled' => true,
                    'system_ready' => true,
                    'status' => 'active',
                    'message' => 'Multi-channel invitation system ready',
                    'can_send' => true,
                ];
                
                return $status;
            });
        } catch (\Exception $e) {
            return [
                'sms' => [
                    'enabled' => false,
                    'system_ready' => false,
                    'status' => 'error',
                    'message' => 'Service unavailable',
                    'can_send' => false,
                    'health' => 'unhealthy',
                ],
                'email' => [
                    'enabled' => true,
                    'system_ready' => true,
                    'status' => 'active',
                    'message' => 'Email service available',
                    'can_send' => true,
                    'health' => 'healthy',
                    'provider' => 'System Default',
                    'statistics' => ['success_rate' => 100],
                    'limits' => ['daily_limit' => ['max_emails_per_day' => 1000, 'used_today' => 0]],
                ],
                'whatsapp' => [
                    'enabled' => false,
                    'system_ready' => false,
                    'status' => 'error',
                    'message' => 'Service unavailable',
                    'can_send' => false,
                    'health' => 'unhealthy',
                ],
                'multi_channel' => [
                    'enabled' => true,
                    'system_ready' => true,
                    'status' => 'active',
                    'message' => 'Multi-channel system ready',
                    'can_send' => true,
                ],
            ];
        }
    }

    /**
     * Generate a unique username
     */
    private function generateUsername(string $name): string
    {
        $base = strtolower(str_replace(' ', '.', $name));
        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base . '.' . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Clean up orphaned supervisor assignments
     */
    private function cleanupOrphanedSupervisorAssignments(User $personnel): void
    {
        $activeAssignments = SecuritySupervisorAssignment::where('user_id', $personnel->id)
            ->where('is_active', true)
            ->get();

        foreach ($activeAssignments as $assignment) {
            // ✅ UPDATED: Check can_be_supervisor flag only
            if (!$personnel->can_be_supervisor) {
                Log::info('🧹 Cleaning up orphaned supervisor assignment', [
                    'user_id' => $personnel->id,
                    'user_name' => $personnel->name,
                    'assignment_id' => $assignment->id,
                    'post_id' => $assignment->security_post_id,
                    'reason' => 'User is no longer eligible for supervisor',
                ]);
                $assignment->update(['is_active' => false]);
            }
        }
    }

    /**
     * Unassign personnel from a post
     */
    public function unassignPost(Request $request, $userId)
    {
        try {
            $currentUser = Auth::user();
            
            if (!$this->canManageSecurityPersonnel($currentUser)) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'You do not have permission to unassign personnel.'
                ], 403);
            }

            $personnel = User::findOrFail($userId);
            
            if ($personnel->type !== User::TYPE_SECURITY_PERSONNEL) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'User is not security personnel.'
                ], 422);
            }

            DB::beginTransaction();

            try {
                $postName = 'Unknown Post';
                $currentAssignment = SecuritySupervisorAssignment::where('user_id', $personnel->id)
                    ->where('is_active', true)
                    ->first();
                
                if ($currentAssignment && $currentAssignment->security_post_id) {
                    $post = SecurityPost::find($currentAssignment->security_post_id);
                    if ($post) {
                        $postName = $post->name;
                    }
                }

                $deactivatedCount = SecuritySupervisorAssignment::where('user_id', $personnel->id)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'end_date' => now(),
                        'updated_at' => now(),
                        'metadata' => DB::raw("JSON_SET(COALESCE(metadata, '{}'), '$.unassigned_by', " . $currentUser->id . ", '$.unassigned_by_name', '" . addslashes($currentUser->name) . "', '$.unassigned_at', '" . now()->toISOString() . "', '$.unassigned_reason', '" . addslashes($request->input('reason', 'No reason provided')) . "')")
                    ]);

                if (class_exists('App\Models\SecuritySchedule')) {
                    SecuritySchedule::where('security_user_id', $personnel->id)
                        ->whereIn('status', ['scheduled', 'active'])
                        ->update([
                            'status' => 'cancelled',
                            'notes' => DB::raw("CONCAT(COALESCE(notes, ''), ' - Unassigned from post on " . now()->toDateTimeString() . " by " . $currentUser->name . "')")
                        ]);
                }

                $personnel->update([
                    'security_post_id' => null
                ]);

                DB::commit();

                Log::info('Security personnel unassigned from post', [
                    'user_id' => $personnel->id,
                    'user_name' => $personnel->name,
                    'post_name' => $postName,
                    'unassigned_by' => $currentUser->id,
                    'unassigned_by_name' => $currentUser->name,
                    'reason' => $request->input('reason', 'No reason provided'),
                    'deactivated_assignments' => $deactivatedCount
                ]);

                return $this->cleanJsonResponse([
                    'success' => true,
                    'message' => "Personnel unassigned from '{$postName}' successfully!",
                    'deactivated_assignments' => $deactivatedCount,
                    'post_name' => $postName
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to unassign personnel: ' . $e->getMessage(), [
                    'user_id' => $userId,
                    'error_trace' => $e->getTraceAsString()
                ]);

                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Failed to unassign personnel: ' . $e->getMessage()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Fatal error in unassignPost: ' . $e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}