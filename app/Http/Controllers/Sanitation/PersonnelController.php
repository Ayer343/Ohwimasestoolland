<?php
// app/Http/Controllers/Sanitation/PersonnelController.php

namespace App\Http\Controllers\Sanitation;

use App\Http\Controllers\Controller;
use App\Models\SanitationPersonnel;
use App\Models\SanitationWorker;
use App\Models\User;
use App\Models\WasteCollectionRequest;
use App\Models\Property;
use App\Services\SanitationService;
use App\Services\GoogleMapsService;
use App\Services\UserInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class PersonnelController extends Controller
{
    protected $sanitationService;
    protected $googleMapsService;
    protected UserInvitationService $invitationService;

    public function __construct(
        SanitationService $sanitationService,
        GoogleMapsService $googleMapsService,
        UserInvitationService $invitationService
    ) {
        $this->sanitationService = $sanitationService;
        $this->googleMapsService = $googleMapsService;
        $this->invitationService = $invitationService;
    }

    // ================================================================ //
    // 📋 INDEX                                                        //
    // ================================================================ //

    /**
     * Display a listing of sanitation personnel.
     */
    public function index(Request $request)
    {
        $query = SanitationPersonnel::with(['user', 'workers', 'supervisor']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $sortField = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        $personnel = $query->paginate($request->get('per_page', 20));

        $stats = [
            'total'       => SanitationPersonnel::count(),
            'active'      => SanitationPersonnel::where('status', 'active')->count(),
            'on_leave'    => SanitationPersonnel::where('status', 'on_leave')->count(),
            'supervisors' => SanitationPersonnel::whereIn('role', SanitationPersonnel::SUPERVISOR_ROLES)->count(),
            'workers'     => SanitationPersonnel::where('role', 'worker')->count(),
            'drivers'     => SanitationPersonnel::where('role', 'driver')->count(),
        ];

        $statuses = ['active', 'inactive', 'on_leave', 'suspended'];
        $roles    = ['supervisor', 'worker', 'driver'];

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data'    => $personnel,
                'stats'   => $stats,
                'filters' => [
                    'statuses' => $statuses,
                    'roles'    => $roles,
                ],
            ]);
        }

        return view('sanitation.personnel.index', compact('personnel', 'stats', 'statuses', 'roles'));
    }

    // ================================================================ //
    // ➕ CREATE                                                        //
    // ================================================================ //

    /**
     * Show the form for creating a new sanitation personnel.
     * ✅ Passes supervisor context AND email availability for the invitation UI.
     */
    public function create()
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$this->isSanitationSupervisor($user)) {
            abort(403, 'Only administrators and sanitation supervisors can create sanitation personnel.');
        }

        $statuses = ['active', 'inactive', 'on_leave', 'suspended'];
        $roles    = ['supervisor', 'driver'];

        // ✅ Supervisor context for the blade
        $creatorPersonnel    = $user->sanitationPersonnel;
        $allowedSupervisors  = $this->getAllowedSupervisorsFor($user);
        $defaultSupervisorId = old('supervisor_id', $creatorPersonnel?->id);

        // Existing users eligible to be linked (optional)
        $existingUsers = User::where('type', User::TYPE_SANITATION_PERSONNEL)
            ->whereDoesntHave('sanitationPersonnel')
            ->get();

        // 📧 Email service status for the invitation section
        $emailStatus = $this->getEmailServiceStatus();

        return view('sanitation.personnel.create', compact(
            'statuses',
            'roles',
            'existingUsers',
            'creatorPersonnel',
            'allowedSupervisors',
            'defaultSupervisorId',
            'emailStatus'
        ));
    }

    // ================================================================ //
    // 💾 STORE                                                         //
    // ================================================================ //

    /**
     * Store a newly created sanitation personnel.
     * ✅ Persists supervisor_id; validates supervisor scope.
     * ✅ NEW: Handles email-only invitation flow.
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$this->isSanitationSupervisor($user)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators and sanitation supervisors can create personnel.',
                ], 403);
            }
            abort(403, 'Only administrators and sanitation supervisors can create sanitation personnel.');
        }

        $validator = $this->validatePersonnel($request);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // ✅ Validate supervisor assignment scope (non-admins)
        $supervisorError = $this->validateSupervisorAssignment($request, $user);
        if ($supervisorError) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $supervisorError,
                ], 403);
            }
            return redirect()->back()->with('error', $supervisorError)->withInput();
        }

        $sendInvitation = $request->boolean('send_invitation');

        try {
            DB::beginTransaction();

            // ✅ Pass invitation flag so user status/verification is set correctly
            $createdUser = $this->handleUserCreation($request, $sendInvitation);

            $employeeId = $this->generateEmployeeId();

            $photoPath = null;
            if ($request->hasFile('profile_photo')) {
                $photoPath = $this->handlePhotoUpload($request->file('profile_photo'), $createdUser->id);
            }

            $supervisorId = $request->filled('supervisor_id')
                ? (int) $request->supervisor_id
                : null;

            $canBeSupervisor = in_array(
                $request->role,
                SanitationPersonnel::SUPERVISOR_ROLES,
                true
            );

            $personnel = SanitationPersonnel::create([
                'user_id'           => $createdUser->id,
                'supervisor_id'     => $supervisorId,
                'can_be_supervisor' => $canBeSupervisor,
                'employee_id'       => $employeeId,
                'first_name'        => $request->first_name,
                'last_name'         => $request->last_name,
                'phone'             => $request->phone,
                'email'             => $request->email ?? $createdUser->email,
                'address'           => $request->address,
                'profile_photo'     => $photoPath,
                'status'            => $request->status,
                'role'              => $request->role,
                'vehicle_number'    => $request->vehicle_number,
                'vehicle_type'      => $request->vehicle_type,
                'emergency_contact' => $request->emergency_contact,
                'hire_date'         => $request->hire_date ?? now(),
                'certifications'    => $request->certifications
                    ? array_map('trim', explode(',', $request->certifications))
                    : null,
                'shift_preference'  => $request->shift_preference,
                'availability_schedule' => $request->availability_schedule
                    ? json_decode($request->availability_schedule, true)
                    : null,
                'assigned_zone'     => $request->assigned_zone
                    ? json_decode($request->assigned_zone, true)
                    : null,
                'metadata' => [
                    'created_by'          => auth()->id(),
                    'created_by_name'     => auth()->user()->name,
                    'created_at'          => now()->toISOString(),
                    'assigned_supervisor' => $supervisorId,
                ],
            ]);

            if ($request->role === 'supervisor' && $request->has('assigned_workers')) {
                $this->assignWorkersToSupervisor($personnel, $request->assigned_workers);
            }

            Log::info('Sanitation personnel created', [
                'personnel_id'  => $personnel->id,
                'employee_id'   => $employeeId,
                'role'          => $request->role,
                'supervisor_id' => $supervisorId,
                'created_by'    => auth()->id(),
            ]);

            // =========================================================
            // 📧 EMAIL-ONLY INVITATION
            // =========================================================
            $invitationResult = null;

            if ($sendInvitation) {
                $invitationResult = $this->sendInvitationFor($createdUser, $request);

                if (!empty($invitationResult['success'])) {
                    $createdUser->update(['invitation_sent_at' => now()]);

                    Log::info('Invitation sent for sanitation personnel', [
                        'personnel_id'       => $personnel->id,
                        'user_id'            => $createdUser->id,
                        'email'              => $createdUser->email,
                        'channels_successful'=> $invitationResult['channels_successful'] ?? [],
                        'failed_channels'    => $invitationResult['failed_channels'] ?? [],
                        'sent_by'            => auth()->id(),
                    ]);
                } else {
                    Log::warning('Invitation failed for sanitation personnel', [
                        'personnel_id' => $personnel->id,
                        'user_id'      => $createdUser->id,
                        'message'      => $invitationResult['message'] ?? 'unknown',
                    ]);
                }
            }

            DB::commit();

            $message = $this->buildStoreSuccessMessage($personnel, $invitationResult, $sendInvitation);

            if ($request->expectsJson()) {
                return response()->json([
                    'success'           => true,
                    'message'           => $message,
                    'data'              => $personnel->load(['user', 'supervisor']),
                    'invitation_result' => $invitationResult,
                ], 201);
            }

            return redirect()->route('sanitation.personnel.show', $personnel)
                ->with('success', $message)
                ->with('invitation_result', $invitationResult);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create sanitation personnel: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create sanitation personnel: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to create personnel: ' . $e->getMessage())
                ->withInput();
        }
    }

   /**
 * Display the specified sanitation personnel.
 *
 * ✅ Authorization gate:
 *    - Admins / Super Admins → can view any record
 *    - Sanitation personnel   → can view themselves, their ancestry chain,
 *                               and their entire subtree (all descendants)
 *    - Everyone else          → 403
 */
public function show(SanitationPersonnel $personnel, Request $request)
{
    $user = auth()->user();

    // ================================================================
    // ✅ Authorization
    // ================================================================
    if (!$user->isSuperAdmin() && !$user->isAdmin()) {
        $currentPersonnel = $user->sanitationPersonnel;

        // Non-sanitation users can never view personnel records
        if (!$currentPersonnel) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view sanitation personnel records.',
                ], 403);
            }
            abort(403, 'You are not authorized to view sanitation personnel records.');
        }

        // Can always view self
        $canView = (int) $currentPersonnel->id === (int) $personnel->id;

        // Supervisors can view their chain + their subtree
        if (!$canView && $currentPersonnel->isSupervisor()) {
            // Direct supervisor (fast path)
            if ((int) $currentPersonnel->supervisor_id === (int) $personnel->id) {
                $canView = true;
            }

            // Anyone above them in the chain
            if (!$canView) {
                $canView = $currentPersonnel->ancestors()
                    ->contains('id', $personnel->id);
            }

            // Anyone below them (recursive)
            if (!$canView) {
                $canView = $this->isDescendantOf($currentPersonnel, (int) $personnel->id);
            }
        }

        if (!$canView) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view this personnel record.',
                ], 403);
            }
            abort(403, 'You are not authorized to view this personnel record.');
        }
    }

    // ================================================================
    // Load relationships
    // ================================================================
    $personnel->load(['user', 'workers', 'supervisor', 'subordinates']);

    // ================================================================
    // Requests (paginated) + active requests
    // ================================================================
    $requests = $personnel->assignedRequests()
        ->with(['property', 'requestedBy', 'worker'])
        ->orderBy('created_at', 'desc')
        ->paginate($request->get('per_page', 10));

    $activeRequests = $personnel->activeRequests()
        ->with(['property', 'requestedBy'])
        ->get();

    // ================================================================
    // Stats
    // ================================================================
    $stats = [
        'total_requests'      => $personnel->assignedRequests()->count(),
        'completed'           => $personnel->assignedRequests()->where('status', 'completed')->count(),
        'active'              => $personnel->activeRequests()->count(),
        'cancelled'           => $personnel->assignedRequests()->where('status', 'cancelled')->count(),
        'completion_rate'     => $this->calculateCompletionRate($personnel),
        'total_weight'        => $personnel->assignedRequests()
            ->where('status', 'completed')
            ->sum('waste_weight_kg'),
        'avg_completion_time' => $personnel->assignedRequests()
            ->where('status', 'completed')
            ->avg('completion_time'),
        'daily_stats'         => $personnel->getDailyStats(),
        'weekly_stats'        => $personnel->getWeeklyStats(),
    ];

    // ================================================================
    // Performance metrics
    // ================================================================
    $performance = $this->calculatePerformanceMetrics($personnel);

    // ================================================================
    // JSON response
    // ================================================================
    if ($request->expectsJson()) {
        return response()->json([
            'success' => true,
            'data'    => [
                'personnel'       => $personnel,
                'requests'        => $requests,
                'active_requests' => $activeRequests,
                'stats'           => $stats,
                'performance'     => $performance,
            ],
        ]);
    }

    // ================================================================
    // Available workers (for supervisors)
    // ================================================================
    $availableWorkers = [];
    if ($personnel->isSupervisor()) {
        $availableWorkers = SanitationWorker::whereNull('supervisor_id')
            ->where('status', 'active')
            ->get();
    }

    return view('sanitation.personnel.show', compact(
        'personnel',
        'requests',
        'activeRequests',
        'stats',
        'performance',
        'availableWorkers'
    ));
}

/**
 * ✅ Recursively check whether $targetId exists anywhere in the subtree
 *    rooted at $root (i.e. $root's descendants at any depth).
 *
 * Uses an iterative BFS with a visited set to guard against accidental cycles.
 */
private function isDescendantOf(SanitationPersonnel $root, int $targetId): bool
{
    $queue   = $root->subordinates()->pluck('id')->all();
    $visited = [];

    while (!empty($queue)) {
        $currentId = (int) array_shift($queue);

        // Cycle guard
        if (isset($visited[$currentId])) {
            continue;
        }
        $visited[$currentId] = true;

        // Found it
        if ($currentId === $targetId) {
            return true;
        }

        // Enqueue children
        $children = SanitationPersonnel::where('supervisor_id', $currentId)
            ->pluck('id')
            ->all();

        foreach ($children as $childId) {
            $queue[] = (int) $childId;
        }
    }

    return false;
}

    // ================================================================ //
    // ✏️ EDIT / UPDATE                                                //
    // ================================================================ //

    /**
 * Show the form for editing the specified personnel.
 *
 * ✅ Non-admins cannot edit their own personnel record.
 * ✅ Also passes $emailStatus so an edit blade can reuse the invitation block if desired.
 */
public function edit(SanitationPersonnel $personnel)
{
    $user = auth()->user();

    if (!$user->isSuperAdmin() && !$user->isAdmin() && !$this->isSanitationSupervisor($user)) {
        abort(403, 'Only administrators and sanitation supervisors can edit sanitation personnel.');
    }

    // ✅ Prevent non-admins from editing their own personnel record
    if (!$user->isSuperAdmin() && !$user->isAdmin()) {
        $currentPersonnelId = $user->sanitationPersonnel?->id;

        if ($currentPersonnelId && $personnel->id === $currentPersonnelId) {
            abort(403, 'You cannot edit your own personnel record here. Please use your profile settings.');
        }
    }

    $statuses = ['active', 'inactive', 'on_leave', 'suspended'];
    $roles    = ['supervisor', 'worker', 'driver'];

    $creatorPersonnel   = $user->sanitationPersonnel;
    $allowedSupervisors = $this->getAllowedSupervisorsFor($user, $personnel);

    $defaultSupervisorId = old('supervisor_id', $personnel->supervisor_id);

    // 📧 For parity with create
    $emailStatus = $this->getEmailServiceStatus();

    return view('sanitation.personnel.edit', compact(
        'personnel',
        'statuses',
        'roles',
        'creatorPersonnel',
        'allowedSupervisors',
        'defaultSupervisorId',
        'emailStatus'
    ));
}

    /**
     * Update the specified sanitation personnel.
     */
    public function update(Request $request, SanitationPersonnel $personnel)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$this->isSanitationSupervisor($user)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 403);
            }
            abort(403, 'Only administrators and sanitation supervisors can update sanitation personnel.');
        }

        $validator = $this->validatePersonnel($request, $personnel->id);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $supervisorError = $this->validateSupervisorAssignment($request, $user, $personnel);
        if ($supervisorError) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $supervisorError,
                ], 403);
            }
            return redirect()->back()->with('error', $supervisorError)->withInput();
        }

        try {
            DB::beginTransaction();

            $photoPath = $personnel->profile_photo;
            if ($request->hasFile('profile_photo')) {
                if ($personnel->profile_photo) {
                    Storage::disk('public')->delete($personnel->profile_photo);
                }
                $photoPath = $this->handlePhotoUpload($request->file('profile_photo'), $personnel->user_id);
            }

            $supervisorId = $request->filled('supervisor_id')
                ? (int) $request->supervisor_id
                : null;

            $canBeSupervisor = in_array(
                $request->role,
                SanitationPersonnel::SUPERVISOR_ROLES,
                true
            );

            $personnel->update([
                'supervisor_id'     => $supervisorId,
                'can_be_supervisor' => $canBeSupervisor,
                'first_name'        => $request->first_name,
                'last_name'         => $request->last_name,
                'phone'             => $request->phone,
                'email'             => $request->email,
                'address'           => $request->address,
                'profile_photo'     => $photoPath,
                'status'            => $request->status,
                'role'              => $request->role,
                'vehicle_number'    => $request->vehicle_number,
                'vehicle_type'      => $request->vehicle_type,
                'emergency_contact' => $request->emergency_contact,
                'shift_preference'  => $request->shift_preference,
                'certifications'    => $request->certifications
                    ? array_map('trim', explode(',', $request->certifications))
                    : null,
                'availability_schedule' => $request->availability_schedule
                    ? json_decode($request->availability_schedule, true)
                    : null,
                'assigned_zone'     => $request->assigned_zone
                    ? json_decode($request->assigned_zone, true)
                    : null,
                'metadata' => array_merge($personnel->metadata ?? [], [
                    'updated_by'          => auth()->id(),
                    'updated_by_name'     => auth()->user()->name,
                    'updated_at'          => now()->toISOString(),
                    'assigned_supervisor' => $supervisorId,
                ]),
            ]);

            $parentUser = $personnel->user;
            if ($parentUser) {
                $parentUser->update([
                    'name'  => $request->first_name . ' ' . $request->last_name,
                    'phone' => $request->phone,
                    'email' => $request->email ?? $parentUser->email,
                ]);
            }

            Log::info('Sanitation personnel updated', [
                'personnel_id' => $personnel->id,
                'updated_by'   => auth()->id(),
                'changes'      => $personnel->getChanges(),
            ]);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Personnel updated successfully!',
                    'data'    => $personnel->fresh(['user', 'supervisor']),
                ]);
            }

            return redirect()->route('sanitation.personnel.show', $personnel)
                ->with('success', 'Personnel updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update personnel: ' . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update personnel: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to update personnel: ' . $e->getMessage())
                ->withInput();
        }
    }

 /**
 * Delete the specified sanitation personnel.
 *
 * ✅ Prevent non-admins from deleting their own personnel record.
 * ✅ Prevent deletion when active requests, subordinates, or workers exist.
 *
 * ✅ Cascade to the linked User is handled by
 *    SanitationPersonnel::booted() → deleting event, which:
 *        - writes deleted_by + deletion_reason onto the User
 *        - soft-deletes the User (unless it's protected)
 *
 *    This means the trash metadata is written regardless of which
 *    controller / job / tinker session triggers the delete.
 */
public function destroy(SanitationPersonnel $personnel, Request $request)
{
    $user = auth()->user();

    // -----------------------------------------------------------------
    // 1. Authorization — who can delete personnel at all?
    // -----------------------------------------------------------------
    if (!$user->isSuperAdmin() && !$user->isAdmin() && !$this->isSanitationSupervisor($user)) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }
        abort(403, 'Only administrators and sanitation supervisors can delete sanitation personnel.');
    }

    // -----------------------------------------------------------------
    // 2. Prevent non-admins from deleting their own record
    // -----------------------------------------------------------------
    if (!$user->isSuperAdmin() && !$user->isAdmin()) {
        $currentPersonnelId = $user->sanitationPersonnel?->id;

        if ($currentPersonnelId && (int) $personnel->id === (int) $currentPersonnelId) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own personnel record.',
                ], 403);
            }
            abort(403, 'You cannot delete your own personnel record.');
        }
    }

    try {
        DB::beginTransaction();

        // -------------------------------------------------------------
        // 3. Guard: active requests
        // -------------------------------------------------------------
        $activeRequests = $personnel->activeRequests()->count();
        if ($activeRequests > 0) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete personnel with {$activeRequests} active request(s).",
                ], 422);
            }
            return redirect()->back()
                ->with('error', "Cannot delete personnel with {$activeRequests} active request(s).");
        }

        // -------------------------------------------------------------
        // 4. Guard: subordinates
        // -------------------------------------------------------------
        $subordinateCount = $personnel->subordinates()->count();
        if ($subordinateCount > 0) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete personnel with {$subordinateCount} subordinate(s). Reassign them first.",
                ], 422);
            }
            return redirect()->back()
                ->with('error', "Cannot delete personnel with {$subordinateCount} subordinate(s). Reassign them first.");
        }

        // -------------------------------------------------------------
        // 5. Guard: legacy workers
        // -------------------------------------------------------------
        if ($personnel->isSupervisor() && $personnel->workers()->count() > 0) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete supervisor with assigned workers.',
                ], 422);
            }
            return redirect()->back()
                ->with('error', 'Cannot delete supervisor with assigned workers.');
        }

        // -------------------------------------------------------------
        // 6. Capture data before deletion for logging
        // -------------------------------------------------------------
        $personnelId    = $personnel->id;
        $personnelName  = $personnel->full_name;
        $linkedUserId   = $personnel->user_id;

        // -------------------------------------------------------------
        // 7. Delete profile photo file (if any)
        // -------------------------------------------------------------
        if ($personnel->profile_photo && Storage::disk('public')->exists($personnel->profile_photo)) {
            Storage::disk('public')->delete($personnel->profile_photo);
        }

        // -------------------------------------------------------------
        // 8. Delete the SanitationPersonnel row.
        //
        //    The SanitationPersonnel model's `deleting` event listener
        //    automatically:
        //      - writes deleted_by + deletion_reason onto the linked User
        //      - soft-deletes the linked User (with safety checks in place)
        //
        //    No user cleanup logic belongs here — it's the model's job.
        // -------------------------------------------------------------
        $personnel->delete();

        // -------------------------------------------------------------
        // 9. Log
        // -------------------------------------------------------------
        Log::info('Sanitation personnel deleted (User cascade handled by model event)', [
            'personnel_id'    => $personnelId,
            'personnel_name'  => $personnelName,
            'linked_user_id'  => $linkedUserId,
            'deleted_by'      => auth()->id(),
            'deleted_by_name' => auth()->user()->name,
        ]);

        DB::commit();

        // -------------------------------------------------------------
        // 10. Response
        // -------------------------------------------------------------
        $message = 'Personnel deleted successfully!';

        if ($request->expectsJson()) {
            return response()->json([
                'success'         => true,
                'message'         => $message,
                'linked_user_id'  => $linkedUserId,
            ]);
        }

        return redirect()->route('sanitation.personnel.index')
            ->with('success', $message);

    } catch (\Exception $e) {
        DB::rollBack();

        Log::error('Failed to delete personnel: ' . $e->getMessage(), [
            'personnel_id' => $personnel->id,
            'trace'        => $e->getTraceAsString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete personnel: ' . $e->getMessage(),
            ], 500);
        }

        return redirect()->back()
            ->with('error', 'Failed to delete personnel: ' . $e->getMessage());
    }
}

    // ================================================================ //
    // 📍 LOCATION & MAPS                                              //
    // ================================================================ //

    public function updateLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy'  => 'nullable|numeric',
            'speed'     => 'nullable|numeric',
            'altitude'  => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $user = auth()->user();
            $personnel = $user->sanitationPersonnel;

            if (!$personnel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Personnel record not found.',
                ], 404);
            }

            $personnel->updateLocation($request->latitude, $request->longitude);

            $metadata = $personnel->metadata ?? [];
            $locationHistory = $metadata['location_history'] ?? [];

            $locationHistory[] = [
                'latitude'  => $request->latitude,
                'longitude' => $request->longitude,
                'accuracy'  => $request->accuracy,
                'speed'     => $request->speed,
                'altitude'  => $request->altitude,
                'timestamp' => now()->toISOString(),
            ];

            if (count($locationHistory) > 100) {
                $locationHistory = array_slice($locationHistory, -100);
            }

            $metadata['location_history'] = $locationHistory;
            $metadata['last_location_update'] = now()->toISOString();

            $personnel->update([
                'latitude'             => $request->latitude,
                'longitude'            => $request->longitude,
                'last_location_update' => now(),
                'metadata'             => $metadata,
            ]);

            $nearbyRequests = $this->getNearbyRequests($personnel);
            if ($nearbyRequests->isNotEmpty()) {
                $this->notifyNearbyRequests($personnel, $nearbyRequests);
            }

            return response()->json([
                'success'         => true,
                'message'         => 'Location updated successfully!',
                'location'        => $personnel->current_location,
                'nearby_requests' => $nearbyRequests->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update personnel location: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update location.',
            ], 500);
        }
    }

    private function getNearbyRequests($personnel, $radius = 5)
    {
        if (!$personnel->latitude || !$personnel->longitude) {
            return collect();
        }

        return WasteCollectionRequest::whereIn('status', ['pending', 'assigned'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw(
                "*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                [$personnel->latitude, $personnel->longitude, $personnel->latitude]
            )
            ->having('distance', '<', $radius)
            ->orderBy('distance')
            ->with(['property'])
            ->get();
    }

    private function notifyNearbyRequests($personnel, $requests)
    {
        Log::info('Nearby requests found for personnel', [
            'personnel_id'  => $personnel->id,
            'request_count' => $requests->count(),
        ]);
    }

    public function getAvailable(Request $request)
    {
        $latitude  = $request->get('latitude');
        $longitude = $request->get('longitude');
        $radius    = $request->get('radius', 10);
        $limit     = $request->get('limit', 10);

        $query = SanitationPersonnel::available()->with('user');

        if ($latitude && $longitude) {
            $query->nearLocation($latitude, $longitude, $radius);
        }

        $personnel = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data'    => $personnel,
        ]);
    }

    // ================================================================ //
    // 👷 WORKER ASSIGNMENT (Legacy)                                   //
    // ================================================================ //

    public function assignWorker(Request $request, SanitationPersonnel $personnel)
    {
        if (!$personnel->isSupervisor()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only supervisors can manage workers.',
                ], 422);
            }
            return redirect()->back()->with('error', 'Only supervisors can manage workers.');
        }

        $validator = Validator::make($request->all(), [
            'worker_id' => 'required|exists:sanitation_workers,id',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator);
        }

        try {
            DB::beginTransaction();

            $worker = SanitationWorker::findOrFail($request->worker_id);

            if ($worker->supervisor_id) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Worker is already assigned to a supervisor.',
                    ], 422);
                }
                return redirect()->back()->with('error', 'Worker is already assigned to a supervisor.');
            }

            $worker->update(['supervisor_id' => $personnel->id]);

            Log::info('Worker assigned to supervisor', [
                'worker_id'     => $worker->id,
                'supervisor_id' => $personnel->id,
                'assigned_by'   => auth()->id(),
            ]);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Worker assigned successfully!',
                    'data'    => $worker,
                ]);
            }

            return redirect()->back()->with('success', 'Worker assigned successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign worker: ' . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to assign worker: ' . $e->getMessage(),
                ], 500);
            }
            return redirect()->back()->with('error', 'Failed to assign worker: ' . $e->getMessage());
        }
    }

    public function unassignWorker(Request $request, SanitationPersonnel $personnel, SanitationWorker $worker)
    {
        if (!$personnel->isSupervisor()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only supervisors can manage workers.',
                ], 422);
            }
            return redirect()->back()->with('error', 'Only supervisors can manage workers.');
        }

        try {
            DB::beginTransaction();

            if ($worker->supervisor_id !== $personnel->id) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Worker is not assigned to this supervisor.',
                    ], 422);
                }
                return redirect()->back()->with('error', 'Worker is not assigned to this supervisor.');
            }

            if ($worker->assignedRequests()
                ->whereIn('status', ['assigned', 'en_route', 'arrived', 'in_progress'])
                ->exists()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot unassign worker with active assignments.',
                    ], 422);
                }
                return redirect()->back()->with('error', 'Cannot unassign worker with active assignments.');
            }

            $worker->update(['supervisor_id' => null]);

            Log::info('Worker unassigned from supervisor', [
                'worker_id'     => $worker->id,
                'supervisor_id' => $personnel->id,
                'unassigned_by' => auth()->id(),
            ]);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Worker unassigned successfully!',
                ]);
            }

            return redirect()->back()->with('success', 'Worker unassigned successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to unassign worker: ' . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to unassign worker: ' . $e->getMessage(),
                ], 500);
            }
            return redirect()->back()->with('error', 'Failed to unassign worker: ' . $e->getMessage());
        }
    }

    public function getWorkers(SanitationPersonnel $personnel, Request $request)
    {
        if (!$personnel->isSupervisor()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only supervisors have workers.',
                ], 422);
            }
            return redirect()->back()->with('error', 'Only supervisors have workers.');
        }

        $workers = $personnel->workers()
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->orderBy('first_name')
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data'    => $workers,
            ]);
        }

        return view('sanitation.personnel.workers', compact('personnel', 'workers'));
    }

    // ================================================================ //
    // 🔧 HELPERS                                                      //
    // ================================================================ //

    /**
     * ✅ Supervisors allowed to be assigned by the current user.
     */
    private function getAllowedSupervisorsFor(User $user, ?SanitationPersonnel $target = null): \Illuminate\Support\Collection
    {
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return SanitationPersonnel::query()
                ->where(function ($q) {
                    $q->whereIn('role', SanitationPersonnel::SUPERVISOR_ROLES)
                      ->orWhere('can_be_supervisor', true);
                })
                ->where('status', 'active')
                ->when($target, fn ($q) => $q->where('id', '!=', $target->id))
                ->orderBy('first_name')
                ->get();
        }

        $creatorPersonnel = $user->sanitationPersonnel;
        if (!$creatorPersonnel || !$creatorPersonnel->isSupervisor()) {
            return collect();
        }

        $list = collect([$creatorPersonnel]);

        if ($creatorPersonnel->supervisor && $creatorPersonnel->supervisor->status === 'active') {
            $list->push($creatorPersonnel->supervisor);
        }

        if ($target) {
            $list = $list->reject(fn ($p) => $p->id === $target->id)->values();
        }

        return $list;
    }

    /**
     * ✅ Validate that the requested supervisor_id is within the creator's scope.
     */
    private function validateSupervisorAssignment(Request $request, User $user, ?SanitationPersonnel $target = null): ?string
    {
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            if ($request->filled('supervisor_id')) {
                $exists = SanitationPersonnel::where('id', $request->supervisor_id)->exists();
                if (!$exists) {
                    return 'The selected supervisor does not exist.';
                }

                if ($target && (int) $request->supervisor_id === $target->id) {
                    return 'A personnel cannot be their own supervisor.';
                }
            }
            return null;
        }

        $creatorPersonnel = $user->sanitationPersonnel;
        if (!$creatorPersonnel || !$creatorPersonnel->isSupervisor()) {
            return 'You are not authorized to assign supervisors.';
        }

        if ($request->filled('supervisor_id')) {
            $allowedIds = collect([$creatorPersonnel->id]);

            if ($creatorPersonnel->supervisor) {
                $allowedIds->push($creatorPersonnel->supervisor->id);
            }

            if (!$allowedIds->contains((int) $request->supervisor_id)) {
                return 'You can only assign personnel under yourself or your own supervisor.';
            }

            if ($target && (int) $request->supervisor_id === $target->id) {
                return 'A personnel cannot be their own supervisor.';
            }
        }

        return null;
    }

    /**
     * Validate personnel data.
     * ✅ Includes email-only invitation fields.
     */
    private function validatePersonnel(Request $request, $personnelId = null)
    {
        $rules = [
            'first_name'        => 'required|string|max:100',
            'last_name'         => 'required|string|max:100',
            'phone'             => 'required|string|max:20|unique:sanitation_personnels,phone' . ($personnelId ? ',' . $personnelId : ''),
            // ✅ Email is required only when an invitation is being sent
            'email'             => 'nullable|email|max:255|unique:sanitation_personnels,email' . ($personnelId ? ',' . $personnelId : '') . '|required_if:send_invitation,1',
            'address'           => 'nullable|string|max:255',
            'profile_photo'     => 'nullable|image|max:5120|mimes:jpeg,png,jpg,gif',
            'status'            => 'required|in:active,inactive,on_leave,suspended',
            'role'              => 'required|in:supervisor,worker,driver,collector,team-lead,manager',
            'supervisor_id'     => 'nullable|integer|exists:sanitation_personnels,id',
            'vehicle_number'    => 'nullable|string|max:50',
            'vehicle_type'      => 'nullable|string|max:100',
            'emergency_contact' => 'nullable|string|max:255',
            'hire_date'         => 'nullable|date',
            'shift_preference'  => 'nullable|string|max:50',
            'certifications'    => 'nullable|string',
            'availability_schedule' => 'nullable|string',
            'assigned_zone'     => 'nullable|string',

            // 📧 Email-only invitation fields
            'send_invitation'    => 'sometimes|boolean',
            'invitation_type'    => 'nullable|in:welcome,registration,account_setup,password_setup',
            'expires_in_days'    => 'nullable|integer|min:1|max:30',
            'invitation_message' => 'nullable|string|max:1000',
        ];

        $messages = [
            'first_name.required'      => 'First name is required.',
            'last_name.required'       => 'Last name is required.',
            'phone.required'           => 'Phone number is required.',
            'phone.unique'             => 'This phone number is already registered.',
            'email.email'              => 'Please enter a valid email address.',
            'email.unique'             => 'This email is already registered.',
            'email.required_if'        => 'An email address is required to send an invitation.',
            'profile_photo.image'      => 'Profile photo must be an image.',
            'profile_photo.max'        => 'Profile photo must not exceed 5MB.',
            'role.required'            => 'Please select a role.',
            'status.required'          => 'Please select a status.',
            'supervisor_id.exists'     => 'The selected supervisor does not exist.',
            'invitation_type.in'       => 'Invalid invitation type selected.',
            'expires_in_days.min'      => 'Invitation expiry must be at least 1 day.',
            'expires_in_days.max'      => 'Invitation expiry cannot exceed 30 days.',
        ];

        return Validator::make($request->all(), $rules, $messages);
    }

    /**
     * Handle user creation or retrieval.
     * ✅ Now aware of invitation flow: sets status=pending, skips email verification,
     *    and doesn't stash a temporary password when the user will set their own.
     */
    private function handleUserCreation(Request $request, bool $viaInvitation = false)
    {
        $user = User::where('phone', $request->phone)
            ->orWhere(function ($q) use ($request) {
                if (!empty($request->email)) {
                    $q->where('email', $request->email);
                }
            })
            ->first();

        if ($user) {
            $updates = [];

            if ($user->type !== User::TYPE_SANITATION_PERSONNEL) {
                $updates['type'] = User::TYPE_SANITATION_PERSONNEL;
            }

            // If inviting, keep them pending until they accept
            $updates['status'] = $viaInvitation ? User::STATUS_PENDING : User::STATUS_ACTIVE;

            // Keep name/email/phone in sync with the form
            $updates['name']  = $request->first_name . ' ' . $request->last_name;
            $updates['phone'] = $request->phone;
            if (!empty($request->email)) {
                $updates['email'] = $request->email;
            }

            $user->update($updates);
            return $user;
        }

        // New user
        $password = Str::random(10);
        $status   = $viaInvitation ? User::STATUS_PENDING : User::STATUS_ACTIVE;

        $metadata = [
            'created_via'     => 'sanitation_personnel_creation',
            'created_by'      => auth()->id(),
            'created_by_name' => auth()->user()->name,
        ];

        // Only stash a temporary password when NOT inviting
        if (!$viaInvitation) {
            $metadata['temporary_password'] = $password;
        } else {
            $metadata['invited_at'] = now()->toISOString();
        }

        $user = User::create([
            'name'              => $request->first_name . ' ' . $request->last_name,
            'email'             => $request->email ?? $request->phone . '@sanitation.local',
            'phone'             => $request->phone,
            'password'          => Hash::make($password),
            'type'              => User::TYPE_SANITATION_PERSONNEL,
            'status'            => $status,
            'created_by'        => auth()->id(),
            // Only mark as verified when NOT inviting
            'email_verified_at' => $viaInvitation ? null : now(),
            'phone_verified_at' => $viaInvitation ? null : now(),
            'metadata'          => $metadata,
        ]);

        return $user;
    }

    /**
     * ✅ NEW: Send an email-only invitation for a sanitation personnel user.
     * Keeps the controller clean and centralises the invitation payload shape.
     */
    private function sendInvitationFor(User $user, Request $request): array
    {
        try {
            $payload = [
                // ⚠️ EMAIL ONLY — no channel picker in the sanitation form
                'invitation_channels' => ['email'],
                'invitation_type'     => $request->input('invitation_type', 'welcome'),
                'custom_message'      => $request->input('invitation_message'),
                'expires_in_days'     => $request->input('expires_in_days', 7),
            ];

            $result = $this->invitationService->sendInvitation($user, $payload);

            // Normalise shape so callers can rely on `success`
            if (!is_array($result)) {
                return [
                    'success'  => false,
                    'message'  => 'Invitation service returned an unexpected response.',
                ];
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Sanitation invitation send failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Invitation could not be sent: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * ✅ NEW: Email service status for the create/edit blades.
     * Mirrors the shape used in UserManagementController so the invitation UI works identically.
     */
    private function getEmailServiceStatus(): array
    {
        try {
            return cache()->remember('email_service_status', 300, function () {
                return app(\App\Services\EmailService::class)->getSystemStatus() ?? [];
            });
        } catch (\Exception $e) {
            Log::warning('Failed to get email service status: ' . $e->getMessage());

            return [
                'enabled'      => false,
                'system_ready' => false,
                'status'       => 'error',
                'message'      => 'Service unavailable',
                'can_send'     => false,
                'health'       => 'unknown',
            ];
        }
    }

    /**
     * ✅ NEW: Success message builder that reflects the invitation outcome.
     */
    private function buildStoreSuccessMessage(
        SanitationPersonnel $personnel,
        ?array $invitationResult,
        bool $sendInvitation
    ): string {
        $message = 'Sanitation personnel created successfully!';

        if ($sendInvitation) {
            if (!empty($invitationResult['success'])) {
                $channels = !empty($invitationResult['channels_successful'])
                    ? implode(', ', $invitationResult['channels_successful'])
                    : 'email';

                $message .= " Invitation sent via {$channels}.";

                if (!empty($invitationResult['failed_channels'])) {
                    $message .= ' Failed channels: ' . implode(', ', $invitationResult['failed_channels']) . '.';
                }
            } else {
                $message .= ' The invitation could not be sent: '
                    . ($invitationResult['message'] ?? 'unknown error')
                    . ' You can resend it from the personnel details page.';
            }
        }

        return $message;
    }

    private function assignWorkersToSupervisor(SanitationPersonnel $supervisor, $workerIds)
    {
        if (!is_array($workerIds)) {
            $workerIds = explode(',', $workerIds);
        }

        foreach ($workerIds as $workerId) {
            $worker = SanitationWorker::find($workerId);
            if ($worker && !$worker->supervisor_id) {
                $worker->update(['supervisor_id' => $supervisor->id]);
            }
        }
    }

    private function generateEmployeeId()
    {
        $prefix = 'SAN';
        $year   = date('Y');
        $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $employeeId = $prefix . $year . $random;

        while (SanitationPersonnel::where('employee_id', $employeeId)->exists()) {
            $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $employeeId = $prefix . $year . $random;
        }

        return $employeeId;
    }

    private function handlePhotoUpload($file, $userId)
    {
        $filename = 'personnel_' . $userId . '_' . time() . '.' . $file->getClientOriginalExtension();
        return $file->storeAs('sanitation/personnel', $filename, 'public');
    }

    private function calculateCompletionRate($personnel)
    {
        $total = $personnel->assignedRequests()->count();
        $completed = $personnel->assignedRequests()->where('status', 'completed')->count();

        return $total === 0 ? 0 : round(($completed / $total) * 100, 2);
    }

    private function calculatePerformanceMetrics($personnel)
    {
        $requests = $personnel->assignedRequests()
            ->where('status', 'completed')
            ->get();

        if ($requests->isEmpty()) {
            return [
                'avg_completion_time'    => 0,
                'total_weight'           => 0,
                'total_requests'         => 0,
                'avg_weight_per_request' => 0,
                'efficiency_score'       => 0,
            ];
        }

        $avgCompletionTime    = $requests->avg('completion_time') ?? 0;
        $totalWeight          = $requests->sum('waste_weight_kg') ?? 0;
        $totalRequests        = $requests->count();
        $avgWeightPerRequest  = $totalRequests > 0 ? $totalWeight / $totalRequests : 0;

        $efficiencyScore = 0;
        if ($avgCompletionTime > 0 && $avgWeightPerRequest > 0) {
            $efficiencyScore = round(($avgWeightPerRequest / ($avgCompletionTime / 60)) * 10, 2);
            $efficiencyScore = min($efficiencyScore, 100);
        }

        return [
            'avg_completion_time'    => round($avgCompletionTime, 2),
            'total_weight'           => round($totalWeight, 2),
            'total_requests'         => $totalRequests,
            'avg_weight_per_request' => round($avgWeightPerRequest, 2),
            'efficiency_score'       => $efficiencyScore,
        ];
    }

    private function sendWelcomeEmail($personnel)
    {
        Log::info('Welcome email sent to personnel', [
            'personnel_id' => $personnel->id,
            'email'        => $personnel->email,
        ]);
    }

    public function export(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$this->isSanitationSupervisor($user)) {
            abort(403, 'Unauthorized.');
        }

        $personnel = SanitationPersonnel::with(['user', 'workers', 'supervisor'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->get();

        $filename = 'sanitation_personnel_' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($personnel) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, [
                'Employee ID',
                'Name',
                'Phone',
                'Email',
                'Role',
                'Status',
                'Supervisor',
                'Vehicle Number',
                'Vehicle Type',
                'Active Jobs',
                'Completed Jobs',
                'Completion Rate',
                'Hire Date',
                'Created At',
            ]);

            foreach ($personnel as $p) {
                fputcsv($file, [
                    $p->employee_id,
                    $p->full_name,
                    $p->phone,
                    $p->email,
                    ucfirst($p->role),
                    ucfirst($p->status),
                    $p->supervisor?->full_name ?? 'None',
                    $p->vehicle_number,
                    $p->vehicle_type,
                    $p->active_jobs_count,
                    $p->assignedRequests()->where('status', 'completed')->count(),
                    $this->calculateCompletionRate($p) . '%',
                    $p->hire_date?->format('Y-m-d'),
                    $p->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * ✅ Check if a user is a sanitation supervisor.
     */
    private function isSanitationSupervisor(User $user): bool
    {
        if ($user->type !== User::TYPE_SANITATION_PERSONNEL) {
            return false;
        }

        return $user->sanitationPersonnel
            && $user->sanitationPersonnel->isSupervisor();
    }

    // ================================================================ //
// 🗑️ TRASH                                                         //
// ================================================================ //

/**
 * Display soft-deleted sanitation personnel (trash bin).
 */
public function trash(Request $request)
{
    $user = auth()->user();

    if (!$user->isSuperAdmin() && !$user->isAdmin() && !$this->isSanitationSupervisor($user)) {
        abort(403, 'Only administrators and sanitation supervisors can view the personnel trash.');
    }

    $query = SanitationPersonnel::onlyTrashed()
        ->with(['user' => fn ($q) => $q->withTrashed(), 'supervisor' => fn ($q) => $q->withTrashed()]);

    // Search
    if ($request->filled('search')) {
        $search = trim($request->search);
        $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%")
              ->orWhere('employee_id', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    // Role filter
    if ($request->filled('role') && $request->role !== 'all') {
        $query->where('role', $request->role);
    }

    // Sort
    $query->orderBy('deleted_at', 'desc');

    $perPage = (int) $request->get('per_page', 20);
    if ($perPage < 5 || $perPage > 100) {
        $perPage = 20;
    }
    $personnel = $query->paginate($perPage);

    // Stats — scoped to only trashed records
    $stats = [
        'total_trashed'      => SanitationPersonnel::onlyTrashed()->count(),
        'trashed_today'      => SanitationPersonnel::onlyTrashed()->whereDate('deleted_at', today())->count(),
        'trashed_this_week'  => SanitationPersonnel::onlyTrashed()->where('deleted_at', '>=', now()->subWeek())->count(),
        'trashed_this_month' => SanitationPersonnel::onlyTrashed()->where('deleted_at', '>=', now()->subMonth())->count(),
        'oldest_deletion'    => SanitationPersonnel::onlyTrashed()->orderBy('deleted_at')->value('deleted_at'),
    ];

    $roles = ['supervisor', 'worker', 'driver'];

    return view('sanitation.personnel.trash', compact('personnel', 'stats', 'roles', 'perPage'));
}

/**
 * Restore a soft-deleted personnel.
 */
public function restore($id, Request $request)
{
    $user = auth()->user();

    if (!$user->isSuperAdmin() && !$user->isAdmin() && !$this->isSanitationSupervisor($user)) {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }
        abort(403, 'Only administrators and sanitation supervisors can restore personnel.');
    }

    $personnel = SanitationPersonnel::onlyTrashed()->findOrFail($id);

    try {
        DB::beginTransaction();

        // Restore the personnel row
        $personnel->restore();

        // Also restore the linked User if it was soft-deleted
        $linkedUser = \App\Models\User::withTrashed()->find($personnel->user_id);
        if ($linkedUser && $linkedUser->trashed()) {
            $linkedUser->restore();
            $linkedUser->update([
                'status'          => \App\Models\User::STATUS_ACTIVE,
                'deleted_by'      => null,
                'deletion_reason' => null,
            ]);
        }

        Log::info('Sanitation personnel restored', [
            'personnel_id' => $personnel->id,
            'user_id'      => $personnel->user_id,
            'restored_by'  => auth()->id(),
        ]);

        DB::commit();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Personnel restored successfully!',
            ]);
        }

        return redirect()->route('sanitation.personnel.trash')
            ->with('success', 'Personnel restored successfully!');

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to restore personnel: ' . $e->getMessage(), [
            'personnel_id' => $id,
            'trace'        => $e->getTraceAsString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore personnel: ' . $e->getMessage(),
            ], 500);
        }

        return redirect()->back()->with('error', 'Failed to restore personnel: ' . $e->getMessage());
    }
}

/**
 * Permanently delete a soft-deleted personnel.
 */
public function forceDelete($id, Request $request)
{
    $user = auth()->user();

    if (!$user->isSuperAdmin() && !$user->isAdmin() && !$this->isSanitationSupervisor($user)) {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }
        abort(403, 'Only administrators and sanitation supervisors can permanently delete personnel.');
    }

    $personnel = SanitationPersonnel::onlyTrashed()->findOrFail($id);

    try {
        DB::beginTransaction();

        // Delete profile photo file (if any)
        if ($personnel->profile_photo && Storage::disk('public')->exists($personnel->profile_photo)) {
            Storage::disk('public')->delete($personnel->profile_photo);
        }

        // Cascade permanently delete the linked User if it's still soft-deleted
        $linkedUser = \App\Models\User::withTrashed()->find($personnel->user_id);
        if ($linkedUser && $linkedUser->trashed()) {
            // Safety: never force-delete admins/super-admins
            $protectedTypes = [
                \App\Models\User::TYPE_ADMIN,
                \App\Models\User::TYPE_SUPER_ADMIN,
                \App\Models\User::TYPE_DEVELOPER,
            ];

            if (!in_array($linkedUser->type, $protectedTypes, true)) {
                $linkedUser->forceDelete();
            }
        }

        $personnel->forceDelete();

        Log::warning('Sanitation personnel permanently deleted', [
            'personnel_id' => $id,
            'user_id'      => $personnel->user_id,
            'deleted_by'   => auth()->id(),
        ]);

        DB::commit();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Personnel permanently deleted!',
            ]);
        }

        return redirect()->route('sanitation.personnel.trash')
            ->with('success', 'Personnel permanently deleted!');

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to permanently delete personnel: ' . $e->getMessage(), [
            'personnel_id' => $id,
            'trace'        => $e->getTraceAsString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete personnel: ' . $e->getMessage(),
            ], 500);
        }

        return redirect()->back()->with('error', 'Failed to permanently delete personnel: ' . $e->getMessage());
    }
}

/**
 * Bulk restore from trash.
 */
public function bulkRestore(Request $request)
{
    $user = auth()->user();

    if (!$user->isSuperAdmin() && !$user->isAdmin() && !$this->isSanitationSupervisor($user)) {
        return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
    }

    $validated = $request->validate([
        'ids'   => 'required|array',
        'ids.*' => 'integer',
    ]);

    $restored = 0;
    $failed   = 0;

    DB::beginTransaction();

    try {
        foreach ($validated['ids'] as $id) {
            $personnel = SanitationPersonnel::onlyTrashed()->find($id);

            if (!$personnel) {
                $failed++;
                continue;
            }

            $personnel->restore();

            $linkedUser = \App\Models\User::withTrashed()->find($personnel->user_id);
            if ($linkedUser && $linkedUser->trashed()) {
                $linkedUser->restore();
                $linkedUser->update([
                    'status'          => \App\Models\User::STATUS_ACTIVE,
                    'deleted_by'      => null,
                    'deletion_reason' => null,
                ]);
            }

            $restored++;
        }

        DB::commit();

        return response()->json([
            'success'  => $restored > 0,
            'message'  => "Restored {$restored} personnel record(s)." . ($failed ? " {$failed} failed." : ''),
            'restored' => $restored,
            'failed'   => $failed,
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Bulk restore failed: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Bulk restore failed: ' . $e->getMessage(),
        ], 500);
    }
}

/**
 * Bulk permanent delete from trash.
 */
public function bulkForceDelete(Request $request)
{
    $user = auth()->user();

    if (!$user->isSuperAdmin() && !$user->isAdmin() && !$this->isSanitationSupervisor($user)) {
        return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
    }

    $validated = $request->validate([
        'ids'   => 'required|array',
        'ids.*' => 'integer',
    ]);

    $deleted = 0;
    $failed  = 0;

    DB::beginTransaction();

    try {
        foreach ($validated['ids'] as $id) {
            $personnel = SanitationPersonnel::onlyTrashed()->find($id);

            if (!$personnel) {
                $failed++;
                continue;
            }

            if ($personnel->profile_photo && Storage::disk('public')->exists($personnel->profile_photo)) {
                Storage::disk('public')->delete($personnel->profile_photo);
            }

            $linkedUser = \App\Models\User::withTrashed()->find($personnel->user_id);
            if ($linkedUser && $linkedUser->trashed()) {
                $protectedTypes = [
                    \App\Models\User::TYPE_ADMIN,
                    \App\Models\User::TYPE_SUPER_ADMIN,
                    \App\Models\User::TYPE_DEVELOPER,
                ];
                if (!in_array($linkedUser->type, $protectedTypes, true)) {
                    $linkedUser->forceDelete();
                }
            }

            $personnel->forceDelete();
            $deleted++;
        }

        DB::commit();

        return response()->json([
            'success' => $deleted > 0,
            'message' => "Permanently deleted {$deleted} personnel record(s)." . ($failed ? " {$failed} failed." : ''),
            'deleted' => $deleted,
            'failed'  => $failed,
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Bulk permanent delete failed: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Bulk permanent delete failed: ' . $e->getMessage(),
        ], 500);
    }
}

}