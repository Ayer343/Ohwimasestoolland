<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\SecurityPost;
use App\Models\SecuritySchedule;
use App\Models\SecuritySupervisorAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SecurityPostController extends Controller
{
    // ✅ USER TYPE CONSTANTS (match AuthServiceProvider)
    const USER_TYPE_SUPER_ADMIN = 0;
    const USER_TYPE_ADMIN = 1;
    const USER_TYPE_LANDLORD = 2;
    const USER_TYPE_TENANT = 3;
    const USER_TYPE_FIELD_AGENT = 4;
    const USER_TYPE_DEVELOPER = 5;
    const USER_TYPE_SECURITY_PERSONNEL = 6;

    // ==================== VIEW METHODS (All Security Personnel) ====================

    /**
     * Display a listing of security posts for ALL security personnel
     * All security personnel can VIEW all active posts
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get all active posts (security personnel can view all active posts)
        $query = SecurityPost::where('is_active', true)
            ->whereNull('deleted_at')
            ->withCount(['currentSchedules as current_personnel'])
            ->orderBy('name');

        // Filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // Filter by staffing status
        if ($request->filled('staffing_status')) {
            switch ($request->staffing_status) {
                case 'fully_staffed':
                    $query->whereRaw('(SELECT COUNT(*) FROM security_schedules WHERE security_post_id = security_posts.id AND DATE(assignment_date) = CURDATE() AND status IN ("scheduled", "active")) >= max_personnel');
                    break;
                case 'understaffed':
                    $query->whereRaw('(SELECT COUNT(*) FROM security_schedules WHERE security_post_id = security_posts.id AND DATE(assignment_date) = CURDATE() AND status IN ("scheduled", "active")) < max_personnel');
                    break;
                case 'unstaffed':
                    $query->whereRaw('(SELECT COUNT(*) FROM security_schedules WHERE security_post_id = security_posts.id AND DATE(assignment_date) = CURDATE() AND status IN ("scheduled", "active")) = 0');
                    break;
            }
        }

        $posts = $query->paginate(20);

        // Get statistics
        $stats = [
            'total_posts' => SecurityPost::where('is_active', true)->whereNull('deleted_at')->count(),
            'fully_staffed' => 0,
            'understaffed' => 0,
            'unstaffed' => 0,
        ];

        foreach ($posts as $post) {
            $current = $post->current_personnel ?? 0;
            if ($current >= $post->max_personnel) {
                $stats['fully_staffed']++;
            } elseif ($current > 0) {
                $stats['understaffed']++;
            } else {
                $stats['unstaffed']++;
            }
        }

        // ✅ Check if user is an Area Supervisor
        $isAreaSupervisor = $this->isAreaSupervisor(Auth::user());

        // ✅ DEBUG: Log the supervisor status
        Log::info('SecurityPostController@index - Supervisor check', [
            'user_id' => Auth::id(),
            'isAreaSupervisor' => $isAreaSupervisor,
        ]);

        // ✅ Get edit permissions for each post (if user is supervisor)
        $editPermissions = [];
        $trashedCount = SecurityPost::onlyTrashed()->count();

        if ($isAreaSupervisor) {
            // Get all supervised posts with edit permission
            $supervisedPosts = SecuritySupervisorAssignment::where('user_id', Auth::id())
                ->where('is_active', true)
                ->where(function($q) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', now());
                })
                ->get();
            
            // ✅ DEBUG: Log supervised posts
            Log::info('SecurityPostController@index - Supervised posts', [
                'user_id' => Auth::id(),
                'supervised_posts_count' => $supervisedPosts->count(),
                'supervised_posts' => $supervisedPosts->pluck('security_post_id')->toArray(),
            ]);
            
            foreach ($supervisedPosts as $assignment) {
                // ✅ FIX: Handle NULL security_post_id
                if (is_null($assignment->security_post_id)) {
                    // If security_post_id is NULL, user can edit ALL posts
                    // Set all posts to editable
                    foreach ($posts as $post) {
                        $editPermissions[$post->id] = (bool) $assignment->can_edit_schedules;
                    }
                    Log::info('SecurityPostController@index - NULL post ID detected, granting edit permission to ALL posts', [
                        'user_id' => Auth::id(),
                        'can_edit_schedules' => $assignment->can_edit_schedules,
                    ]);
                } else {
                    // Specific post assignment
                    $editPermissions[$assignment->security_post_id] = (bool) $assignment->can_edit_schedules;
                }
            }

            // ✅ DEBUG: Log edit permissions
            Log::info('SecurityPostController@index - Edit permissions', [
                'user_id' => Auth::id(),
                'editPermissions' => $editPermissions,
            ]);
        }

        // Get working hours info for each post
        $posts->each(function($post) {
            $post->working_hours_info = $this->getWorkingHoursInfo($post);
        });

        // ✅ Pass trashedCount to view
        return view('security.posts.index', compact(
            'posts', 
            'stats', 
            'request', 
            'isAreaSupervisor', 
            'editPermissions',
            'trashedCount'
        ));
    }

    /**
     * Display trashed security posts (Area Supervisor only)
     */
    public function trash(Request $request)
    {
        // ✅ Only Area Supervisors can view trash
        if (!$this->isAreaSupervisor(Auth::user())) {
            abort(403, 'Only Area Supervisors can view deleted posts.');
        }

        $query = SecurityPost::onlyTrashed()
            ->withCount(['schedules as total_assignments'])
            ->orderBy('deleted_at', 'desc');

        // Filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $trashedPosts = $query->paginate(20);

        // Get statistics for trashed posts
        $trashStats = [
            'total_trashed' => SecurityPost::onlyTrashed()->count(),
            'recently_deleted' => SecurityPost::onlyTrashed()
                ->where('deleted_at', '>=', now()->subDays(7))
                ->count(),
            'by_type' => SecurityPost::onlyTrashed()
                ->select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
        ];

        return view('security.posts.trash', compact('trashedPosts', 'trashStats', 'request'));
    }

    // ==================== CREATE METHODS (Area Supervisor Only) ====================

    /**
     * Show the form for creating a new security post (Area Supervisor only)
     */
    public function create()
    {
        // ✅ Only Area Supervisors can create
        if (!$this->isAreaSupervisor(Auth::user())) {
            abort(403, 'Only Area Supervisors can create security posts.');
        }

        $postTypes = [
            'main_gate' => 'Main Gate',
            'internal_gate' => 'Internal Gate',
            'checkpoint' => 'Checkpoint',
            'patrol_route' => 'Patrol Route',
            'observation_post' => 'Observation Post',
            'control_room' => 'Control Room',
            'access_point' => 'Access Point',
        ];

        $defaultEquipment = [
            'Communication Radio',
            'Flashlight',
            'First Aid Kit',
            'Visitor Logbook',
            'Security Barrier',
            'CCTV Monitor',
        ];

        return view('security.posts.create', compact('postTypes', 'defaultEquipment'));
    }

    /**
     * Store a newly created security post (Area Supervisor only)
     */
    public function store(Request $request)
    {
        // ✅ Only Area Supervisors can create
        if (!$this->isAreaSupervisor(Auth::user())) {
            abort(403, 'Only Area Supervisors can create security posts.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:security_posts,name',
            'code' => 'required|string|max:50|unique:security_posts,code',
            'type' => 'required|string|in:main_gate,internal_gate,checkpoint,patrol_route,observation_post,control_room,access_point',
            'description' => 'nullable|string|max:1000',
            'location' => 'required|string|max:500',
            'digital_address' => 'nullable|string|max:255',
            'equipment' => 'nullable|array',
            'equipment.*' => 'string|max:100',
            'max_personnel' => 'required|integer|min:1|max:10',
            'is_active' => 'boolean',
            'requires_checkin' => 'boolean',
            'working_hours_start' => 'nullable|date_format:H:i',
            'working_hours_end' => 'nullable|date_format:H:i',
            'restrictions' => 'nullable|array',
            'restrictions.*' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Custom validation for working hours
        if ($request->filled('working_hours_start') && $request->filled('working_hours_end')) {
            $start = Carbon::parse($request->working_hours_start);
            $end = Carbon::parse($request->working_hours_end);
            
            if ($start->format('H:i') === $end->format('H:i')) {
                return redirect()->back()
                    ->withErrors(['working_hours_end' => 'Start and end times cannot be the same.'])
                    ->withInput();
            }
        }

        DB::beginTransaction();

        try {
            $postData = $request->only([
                'name', 'code', 'type', 'description', 
                'location', 'digital_address', 'max_personnel'
            ]);

            $postData['is_active'] = $request->boolean('is_active', true);
            $postData['requires_checkin'] = $request->boolean('requires_checkin', true);
            $postData['equipment'] = $request->equipment ?? [];

            // Handle working hours with overnight support
            if ($request->filled('working_hours_start') && $request->filled('working_hours_end')) {
                $start = Carbon::parse($request->working_hours_start);
                $end = Carbon::parse($request->working_hours_end);
                
                $isOvernight = $end->lessThan($start) || $end->equalTo($start);
                
                $postData['working_hours'] = [
                    'start' => $request->working_hours_start,
                    'end' => $request->working_hours_end,
                    'is_overnight' => $isOvernight,
                    'duration_hours' => $this->calculateDurationHours($start, $end),
                    'display' => $this->formatWorkingHoursDisplay($request->working_hours_start, $request->working_hours_end, $isOvernight),
                ];
            }

            $postData['restrictions'] = $request->restrictions ?? [];

            $post = SecurityPost::create($postData);

            Log::info('Security post created by Area Supervisor', [
                'post_id' => $post->id,
                'name' => $post->name,
                'code' => $post->code,
                'created_by' => Auth::id(),
                'created_by_name' => Auth::user()->name,
            ]);

            DB::commit();

            return redirect()->route('security.posts.show', $post)
                ->with('success', 'Security post created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create security post: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to create security post. Please try again.')
                ->withInput();
        }
    }

    // ==================== READ METHODS (All Security Personnel) ====================

    /**
     * Display the specified security post (read-only for all security)
     */
    public function show(SecurityPost $securityPost)
    {
        $user = Auth::user();
        
        // Check if post is active and not deleted
        if (!$securityPost->is_active || $securityPost->trashed()) {
            abort(404, 'Post not available.');
        }

        // Check if user has access to view this post
        if (!$this->canViewPost($user, $securityPost->id)) {
            abort(403, 'You do not have access to this post.');
        }

        // Load relationships
        $securityPost->load([
            'schedules' => function($query) {
                $query->whereDate('assignment_date', '>=', now()->subDays(7))
                      ->orderBy('assignment_date', 'desc')
                      ->limit(10);
            },
            'schedules.securityUser',
            'schedules.shift',
        ]);

        // Get today's schedules
        $todaySchedules = $securityPost->currentSchedules()
            ->with(['securityUser', 'shift'])
            ->get();

        // Get upcoming schedules (next 7 days)
        $upcomingSchedules = $securityPost->schedules()
            ->whereDate('assignment_date', '>', now()->toDateString())
            ->whereDate('assignment_date', '<=', now()->addDays(7)->toDateString())
            ->with(['securityUser', 'shift'])
            ->orderBy('assignment_date')
            ->get();

        // Get statistics
        $stats = [
            'total_assignments' => $securityPost->schedules()->count(),
            'active_today' => $todaySchedules->where('status', 'active')->count(),
            'staffing_rate' => $securityPost->max_personnel > 0 
                ? round(($todaySchedules->count() / $securityPost->max_personnel) * 100, 1)
                : 0,
        ];

        // ✅ Check if user is an Area Supervisor for this post
        $isAreaSupervisor = $this->isAreaSupervisor(Auth::user());
        $isSupervisorForPost = $this->isSupervisorForPost(Auth::user(), $securityPost->id);

        // Check if user can edit this post (supervisor with permission)
        $canEdit = $isSupervisorForPost && $this->hasEditPermission(Auth::user(), $securityPost->id);

        // Check if user can delete this post (supervisor with permission)
        $canDelete = $isSupervisorForPost && $this->hasDeletePermission(Auth::user(), $securityPost->id);

        // Get working hours info
        $workingHours = $this->getWorkingHoursInfo($securityPost);

        return view('security.posts.show', compact(
            'securityPost',
            'todaySchedules',
            'upcomingSchedules',
            'stats',
            'isAreaSupervisor',
            'isSupervisorForPost',
            'canEdit',
            'canDelete',
            'workingHours'
        ));
    }

    // ==================== UPDATE METHODS (Area Supervisor Only) ====================

    /**
     * Show the form for editing the specified security post (Area Supervisor only)
     */
    public function edit(SecurityPost $securityPost)
    {
        $user = Auth::user();
        
        // ✅ Only Area Supervisors can edit
        if (!$this->isAreaSupervisor($user)) {
            abort(403, 'Only Area Supervisors can edit security posts.');
        }

        // ✅ Check if user is a supervisor for this post OR has NULL post assignment
        if (!$this->isSupervisorForPost($user, $securityPost->id)) {
            // Check for NULL assignment (can edit ALL posts)
            $hasNullAssignment = SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->whereNull('security_post_id')
                ->where(function($q) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', now());
                })
                ->exists();
            
            if (!$hasNullAssignment) {
                abort(403, 'You are not assigned as a supervisor for this post.');
            }
        }

        // ✅ Check if supervisor has edit permission
        if (!$this->hasEditPermission($user, $securityPost->id)) {
            abort(403, 'You do not have permission to edit this post.');
        }

        // Check if post is trashed
        if ($securityPost->trashed()) {
            return redirect()->route('security.posts.index')
                ->with('warning', 'Cannot edit a deleted security post.');
        }

        $postTypes = [
            'main_gate' => 'Main Gate',
            'internal_gate' => 'Internal Gate',
            'checkpoint' => 'Checkpoint',
            'patrol_route' => 'Patrol Route',
            'observation_post' => 'Observation Post',
            'control_room' => 'Control Room',
            'access_point' => 'Access Point',
        ];

        $equipment = is_array($securityPost->equipment) ? $securityPost->equipment : [];
        $restrictions = is_array($securityPost->restrictions) ? $securityPost->restrictions : [];

        $workingHoursStart = '';
        $workingHoursEnd = '';
        $isOvernight = false;
        if (is_array($securityPost->working_hours)) {
            $workingHoursStart = $securityPost->working_hours['start'] ?? '';
            $workingHoursEnd = $securityPost->working_hours['end'] ?? '';
            $isOvernight = $securityPost->working_hours['is_overnight'] ?? false;
        }

        return view('security.posts.edit', compact(
            'securityPost',
            'postTypes',
            'equipment',
            'restrictions',
            'workingHoursStart',
            'workingHoursEnd',
            'isOvernight'
        ));
    }

    /**
     * Update the specified security post (Area Supervisor only)
     */
    public function update(Request $request, SecurityPost $securityPost)
    {
        $user = Auth::user();
        
        // ✅ Only Area Supervisors can update
        if (!$this->isAreaSupervisor($user)) {
            abort(403, 'Only Area Supervisors can update security posts.');
        }

        // ✅ Check if user is a supervisor for this post OR has NULL post assignment
        if (!$this->isSupervisorForPost($user, $securityPost->id)) {
            $hasNullAssignment = SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->whereNull('security_post_id')
                ->where(function($q) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', now());
                })
                ->exists();
            
            if (!$hasNullAssignment) {
                abort(403, 'You are not assigned as a supervisor for this post.');
            }
        }

        // ✅ Check if supervisor has edit permission
        if (!$this->hasEditPermission($user, $securityPost->id)) {
            abort(403, 'You do not have permission to edit this post.');
        }

        // Check if post is trashed
        if ($securityPost->trashed()) {
            return redirect()->route('security.posts.index')
                ->with('warning', 'Cannot update a deleted security post.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:security_posts,name,' . $securityPost->id,
            'code' => 'required|string|max:50|unique:security_posts,code,' . $securityPost->id,
            'type' => 'required|string|in:main_gate,internal_gate,checkpoint,patrol_route,observation_post,control_room,access_point',
            'description' => 'nullable|string|max:1000',
            'location' => 'required|string|max:500',
            'digital_address' => 'nullable|string|max:255',
            'equipment' => 'nullable|array',
            'equipment.*' => 'string|max:100',
            'max_personnel' => 'required|integer|min:1|max:10',
            'is_active' => 'boolean',
            'requires_checkin' => 'boolean',
            'working_hours_start' => 'nullable|date_format:H:i',
            'working_hours_end' => 'nullable|date_format:H:i',
            'restrictions' => 'nullable|array',
            'restrictions.*' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Custom validation for working hours
        if ($request->filled('working_hours_start') && $request->filled('working_hours_end')) {
            $start = Carbon::parse($request->working_hours_start);
            $end = Carbon::parse($request->working_hours_end);
            
            if ($start->format('H:i') === $end->format('H:i')) {
                return redirect()->back()
                    ->withErrors(['working_hours_end' => 'Start and end times cannot be the same.'])
                    ->withInput();
            }
        }

        DB::beginTransaction();

        try {
            $updateData = $request->only([
                'name', 'code', 'type', 'description', 
                'location', 'digital_address', 'max_personnel'
            ]);

            $updateData['is_active'] = $request->boolean('is_active');
            $updateData['requires_checkin'] = $request->boolean('requires_checkin');
            $updateData['equipment'] = $request->equipment ?? [];

            if ($request->filled('working_hours_start') && $request->filled('working_hours_end')) {
                $start = Carbon::parse($request->working_hours_start);
                $end = Carbon::parse($request->working_hours_end);
                
                $isOvernight = $end->lessThan($start) || $end->equalTo($start);
                
                $updateData['working_hours'] = [
                    'start' => $request->working_hours_start,
                    'end' => $request->working_hours_end,
                    'is_overnight' => $isOvernight,
                    'duration_hours' => $this->calculateDurationHours($start, $end),
                    'display' => $this->formatWorkingHoursDisplay($request->working_hours_start, $request->working_hours_end, $isOvernight),
                ];
            } else {
                $updateData['working_hours'] = null;
            }

            $updateData['restrictions'] = $request->restrictions ?? [];

            $securityPost->update($updateData);

            Log::info('Security post updated by Area Supervisor', [
                'post_id' => $securityPost->id,
                'name' => $securityPost->name,
                'updated_by' => Auth::id(),
                'updated_by_name' => Auth::user()->name,
            ]);

            DB::commit();

            return redirect()->route('security.posts.show', $securityPost)
                ->with('success', 'Security post updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update security post: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to update security post. Please try again.')
                ->withInput();
        }
    }

    // ==================== DELETE METHODS (Area Supervisor Only) ====================

  /**
 * Soft delete the specified security post (Area Supervisor only)
 */
public function destroy(SecurityPost $securityPost)
{
    try {
        $user = Auth::user();
        
        // ✅ Check if user is logged in
        if (!$user) {
            if (request()->expectsJson() || request()->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'You must be logged in to perform this action.'
                ], 401);
            }
            abort(401, 'Unauthenticated.');
        }

        // ✅ Only Area Supervisors can delete
        if (!$this->isAreaSupervisor($user)) {
            if (request()->expectsJson() || request()->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Only Area Supervisors can delete security posts.'
                ], 403);
            }
            abort(403, 'Only Area Supervisors can delete security posts.');
        }

        // ✅ Check if user is a supervisor for this post OR has NULL post assignment
        $isSupervisor = $this->isSupervisorForPost($user, $securityPost->id);
        $hasNullAssignment = false;
        
        if (!$isSupervisor) {
            $hasNullAssignment = SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->whereNull('security_post_id')
                ->where(function($q) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', now());
                })
                ->exists();
            
            if (!$hasNullAssignment) {
                if (request()->expectsJson() || request()->ajax()) {
                    return $this->cleanJsonResponse([
                        'success' => false,
                        'message' => 'You are not assigned as a supervisor for this post.'
                    ], 403);
                }
                abort(403, 'You are not assigned as a supervisor for this post.');
            }
        }

        // Check if supervisor has delete permission
        if (!$this->hasDeletePermission($user, $securityPost->id)) {
            if (request()->expectsJson() || request()->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'You do not have permission to delete this post.'
                ], 403);
            }
            abort(403, 'You do not have permission to delete this post.');
        }

        // Check if post is already trashed
        if ($securityPost->trashed()) {
            if (request()->expectsJson() || request()->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Security post is already deleted.'
                ], 422);
            }
            return redirect()->route('security.posts.trash')
                ->with('warning', 'Security post is already deleted.');
        }

        // Check if post has active schedules
        $hasActiveSchedules = $securityPost->schedules()
            ->whereDate('assignment_date', '>=', now()->toDateString())
            ->whereIn('status', ['scheduled', 'active'])
            ->exists();

        if ($hasActiveSchedules) {
            if (request()->expectsJson() || request()->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Cannot delete security post with active or upcoming schedules.'
                ], 422);
            }
            return redirect()->back()
                ->with('error', 'Cannot delete security post with active or upcoming schedules.');
        }

        DB::beginTransaction();

        try {
            $postName = $securityPost->name;
            $postId = $securityPost->id;
            $securityPost->delete();

            Log::info('Security post soft deleted by Area Supervisor', [
                'post_id' => $postId,
                'name' => $postName,
                'deleted_by' => Auth::id(),
                'deleted_by_name' => Auth::user()->name,
            ]);

            DB::commit();

            if (request()->expectsJson() || request()->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => true,
                    'message' => 'Security post moved to trash successfully.',
                    'post_id' => $postId,
                    'post_name' => $postName
                ]);
            }

            return redirect()->route('security.posts.index')
                ->with('success', 'Security post moved to trash successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete security post: ' . $e->getMessage());

            if (request()->expectsJson() || request()->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Failed to delete security post: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to delete security post. Please try again.');
        }
        
    } catch (\Exception $e) {
        Log::error('Delete exception: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        
        if (request()->expectsJson() || request()->ajax()) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
        
        return redirect()->back()
            ->with('error', 'Server error: ' . $e->getMessage());
    }
}

   /**
 * Force delete a security post from trash (Area Supervisor only)
 */
public function forceDelete($id)
{
    try {
        $user = Auth::user();
        
        // ✅ Check if user is logged in
        if (!$user) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'You must be logged in to perform this action.'
            ], 401);
        }

        // ✅ Only Area Supervisors can force delete
        if (!$this->isAreaSupervisor($user)) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Only Area Supervisors can permanently delete security posts.'
            ], 403);
        }

        $post = SecurityPost::withTrashed()->findOrFail($id);

        if (!$post->trashed()) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Post is not in trash.'
            ], 422);
        }

        // Check if user is a supervisor for this post OR has NULL post assignment
        $isSupervisor = $this->isSupervisorForPost($user, $post->id);
        $hasNullAssignment = false;
        
        if (!$isSupervisor) {
            $hasNullAssignment = SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->whereNull('security_post_id')
                ->where(function($q) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', now());
                })
                ->exists();
            
            if (!$hasNullAssignment) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'You are not assigned as a supervisor for this post.'
                ], 403);
            }
        }

        DB::beginTransaction();

        try {
            $hasSchedules = $post->schedules()->exists();
            
            if ($hasSchedules) {
                $post->schedules()->delete();
            }

            $postName = $post->name;
            $postId = $post->id;
            $post->forceDelete();

            Log::warning('Security post permanently deleted by Area Supervisor', [
                'post_id' => $id,
                'name' => $postName,
                'deleted_by' => Auth::id(),
                'deleted_by_name' => Auth::user()->name,
            ]);

            DB::commit();

            return $this->cleanJsonResponse([
                'success' => true,
                'message' => 'Security post permanently deleted.',
                'post_id' => $postId,
                'post_name' => $postName
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to force delete security post: ' . $e->getMessage());

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to permanently delete security post: ' . $e->getMessage()
            ], 500);
        }
        
    } catch (\Exception $e) {
        Log::error('Force delete exception: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        
        return $this->cleanJsonResponse([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}

   /**
 * Restore a soft-deleted post from trash (Area Supervisor only)
 */
public function restore($id)
{
    try {
        $user = Auth::user();
        
        if (!$user) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'You must be logged in to perform this action.'
            ], 401);
        }

        if (!$this->isAreaSupervisor($user)) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Only Area Supervisors can restore security posts.'
            ], 403);
        }

        $post = SecurityPost::withTrashed()->findOrFail($id);

        if (!$post->trashed()) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Post is not deleted.'
            ], 422);
        }

        $isSupervisor = $this->isSupervisorForPost($user, $post->id);
        $hasNullAssignment = false;
        
        if (!$isSupervisor) {
            $hasNullAssignment = SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->whereNull('security_post_id')
                ->where(function($q) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', now());
                })
                ->exists();
            
            if (!$hasNullAssignment) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'You are not assigned as a supervisor for this post.'
                ], 403);
            }
        }

        DB::beginTransaction();

        try {
            $nameExists = SecurityPost::where('name', $post->name)
                ->where('id', '!=', $post->id)
                ->exists();
                
            $codeExists = SecurityPost::where('code', $post->code)
                ->where('id', '!=', $post->id)
                ->exists();

            if ($nameExists || $codeExists) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Cannot restore post. Name or code already exists for another active post.'
                ], 422);
            }

            $post->restore();
            $post->update(['is_active' => true]);

            Log::info('Security post restored from trash by Area Supervisor', [
                'post_id' => $post->id,
                'name' => $post->name,
                'restored_by' => Auth::id(),
                'restored_by_name' => Auth::user()->name,
            ]);

            DB::commit();

            return $this->cleanJsonResponse([
                'success' => true,
                'message' => 'Security post restored and activated successfully.',
                'post_id' => $post->id,
                'post_name' => $post->name,
                'redirect_url' => route('security.posts.index')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore security post: ' . $e->getMessage());

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to restore security post: ' . $e->getMessage()
            ], 500);
        }
        
    } catch (\Exception $e) {
        Log::error('Restore exception: ' . $e->getMessage());
        
        return $this->cleanJsonResponse([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Empty trash (permanently delete all trashed posts) - Area Supervisor only
     */
    public function emptyTrash()
    {
        // ✅ Only Area Supervisors can empty trash
        if (!$this->isAreaSupervisor(Auth::user())) {
            abort(403, 'Only Area Supervisors can empty trash.');
        }

        $trashedCount = SecurityPost::onlyTrashed()->count();

        if ($trashedCount === 0) {
            return redirect()->route('security.posts.trash')
                ->with('info', 'Trash is already empty.');
        }

        DB::beginTransaction();

        try {
            $trashedPosts = SecurityPost::onlyTrashed()->get();
            
            foreach ($trashedPosts as $post) {
                $post->schedules()->delete();
            }

            $deletedCount = SecurityPost::onlyTrashed()->forceDelete();

            Log::warning('Trash emptied - All security posts permanently deleted by Area Supervisor', [
                'count' => $deletedCount,
                'deleted_by' => Auth::id(),
                'deleted_by_name' => Auth::user()->name,
            ]);

            DB::commit();

            return redirect()->route('security.posts.trash')
                ->with('success', "{$deletedCount} security posts permanently deleted from trash.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to empty trash: ' . $e->getMessage());

            return redirect()->route('security.posts.trash')
                ->with('error', 'Failed to empty trash.');
        }
    }

   /**
 * Bulk restore multiple posts from trash (Area Supervisor only)
 */
public function bulkRestore(Request $request)
{
    try {
        $user = Auth::user();
        
        // ✅ Only Area Supervisors can bulk restore
        if (!$this->isAreaSupervisor($user)) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Only Area Supervisors can restore posts.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'post_ids' => 'required|array',
            'post_ids.*' => 'exists:security_posts,id',
        ]);

        if ($validator->fails()) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $restoredCount = 0;
            $failedPosts = [];

            foreach ($request->post_ids as $postId) {
                $post = SecurityPost::withTrashed()->find($postId);
                
                if (!$post || !$post->trashed()) {
                    $failedPosts[] = [
                        'id' => $postId,
                        'reason' => 'Post not found or not in trash'
                    ];
                    continue;
                }

                // Check if user is supervisor for this post OR has NULL assignment
                $isSupervisor = $this->isSupervisorForPost($user, $post->id);
                
                if (!$isSupervisor) {
                    $hasNullAssignment = SecuritySupervisorAssignment::where('user_id', $user->id)
                        ->where('is_active', true)
                        ->whereNull('security_post_id')
                        ->where(function($q) {
                            $q->whereNull('end_date')
                              ->orWhere('end_date', '>=', now());
                        })
                        ->exists();
                    
                    if (!$hasNullAssignment) {
                        $failedPosts[] = [
                            'id' => $postId,
                            'reason' => 'You are not assigned as supervisor for this post'
                        ];
                        continue;
                    }
                }

                // Check for conflicts
                $nameExists = SecurityPost::where('name', $post->name)
                    ->where('id', '!=', $post->id)
                    ->exists();
                    
                $codeExists = SecurityPost::where('code', $post->code)
                    ->where('id', '!=', $post->id)
                    ->exists();

                if ($nameExists || $codeExists) {
                    $failedPosts[] = [
                        'id' => $postId,
                        'reason' => $nameExists ? 'Name conflict' : 'Code conflict'
                    ];
                    continue;
                }

                $post->restore();
                $post->update(['is_active' => true]);
                $restoredCount++;
            }

            DB::commit();

            return $this->cleanJsonResponse([
                'success' => true,
                'message' => "Successfully restored and activated {$restoredCount} post(s).",
                'restored_count' => $restoredCount,
                'failed_posts' => $failedPosts,
                'redirect_url' => route('security.posts.index')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk restore posts: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'post_ids' => $request->post_ids,
                'error_trace' => $e->getTraceAsString()
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
        
    } catch (\Exception $e) {
        Log::error('Bulk restore exception: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        
        return $this->cleanJsonResponse([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}

   /**
 * Bulk permanently delete multiple posts from trash (Area Supervisor only)
 */
public function bulkPermanentDelete(Request $request)
{
    try {
        $user = Auth::user();
        
        // ✅ Only Area Supervisors can bulk delete
        if (!$this->isAreaSupervisor($user)) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Only Area Supervisors can permanently delete posts.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'post_ids' => 'required|array',
            'post_ids.*' => 'exists:security_posts,id',
        ]);

        if ($validator->fails()) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $deletedCount = 0;
            $failedPosts = [];

            foreach ($request->post_ids as $postId) {
                $post = SecurityPost::withTrashed()->find($postId);
                
                if (!$post || !$post->trashed()) {
                    $failedPosts[] = [
                        'id' => $postId,
                        'reason' => 'Post not found or not in trash'
                    ];
                    continue;
                }

                // Check if user is supervisor for this post OR has NULL assignment
                $isSupervisor = $this->isSupervisorForPost($user, $post->id);
                
                if (!$isSupervisor) {
                    $hasNullAssignment = SecuritySupervisorAssignment::where('user_id', $user->id)
                        ->where('is_active', true)
                        ->whereNull('security_post_id')
                        ->where(function($q) {
                            $q->whereNull('end_date')
                              ->orWhere('end_date', '>=', now());
                        })
                        ->exists();
                    
                    if (!$hasNullAssignment) {
                        $failedPosts[] = [
                            'id' => $postId,
                            'reason' => 'You are not assigned as supervisor for this post'
                        ];
                        continue;
                    }
                }

                // Delete schedules first
                $post->schedules()->delete();
                $post->forceDelete();
                $deletedCount++;
            }

            DB::commit();

            return $this->cleanJsonResponse([
                'success' => true,
                'message' => "Successfully permanently deleted {$deletedCount} post(s).",
                'deleted_count' => $deletedCount,
                'failed_posts' => $failedPosts
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk delete posts: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'post_ids' => $request->post_ids,
                'error_trace' => $e->getTraceAsString()
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
        
    } catch (\Exception $e) {
        Log::error('Bulk delete exception: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        
        return $this->cleanJsonResponse([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}

    // ==================== API / AJAX METHODS ====================

    /**
     * Get post schedule for security personnel
     */
    public function getSchedule(Request $request, SecurityPost $securityPost)
    {
        $user = Auth::user();
        
        if (!$this->canViewPost($user, $securityPost->id)) {
            abort(403, 'You do not have access to this post.');
        }

        $date = $request->get('date', now()->toDateString());
        
        $schedules = SecuritySchedule::where('security_post_id', $securityPost->id)
            ->whereDate('assignment_date', $date)
            ->with(['securityUser', 'shift'])
            ->orderBy('security_shift_id')
            ->get();

        $isAreaSupervisor = $this->isAreaSupervisor(Auth::user());
        $isSupervisorForPost = $this->isSupervisorForPost(Auth::user(), $securityPost->id);

        $workingHours = $this->getWorkingHoursInfo($securityPost);

        return view('security.posts.schedule', compact(
            'securityPost', 
            'schedules', 
            'date', 
            'isAreaSupervisor',
            'isSupervisorForPost',
            'workingHours'
        ));
    }

    /**
     * Get my assigned post (for regular security personnel)
     */
    public function myPost(Request $request)
    {
        $user = Auth::user();
        $today = now()->toDateString();
        
        $schedule = SecuritySchedule::whereHas('securityUser', function($query) use ($user) {
            $query->where('id', $user->id);
        })->whereDate('assignment_date', $today)
          ->with(['post', 'shift'])
          ->first();
        
        if (!$schedule) {
            $possibleColumns = ['security_user_id', 'personnel_id', 'user_id', 'employee_id'];
            foreach ($possibleColumns as $column) {
                if (\Schema::hasColumn('security_schedules', $column)) {
                    $schedule = SecuritySchedule::where($column, $user->id)
                        ->whereDate('assignment_date', $today)
                        ->with(['post', 'shift'])
                        ->first();
                    
                    if ($schedule) {
                        break;
                    }
                }
            }
        }

        if (!$schedule) {
            return redirect()->route('security.posts.index')
                ->with('info', 'You are not scheduled for any post today.');
        }

        $post = $schedule->post;
        
        if (!$post || !$post->is_active || $post->trashed()) {
            return redirect()->route('security.posts.index')
                ->with('warning', 'Your assigned post is currently unavailable.');
        }

        $personnel = SecuritySchedule::where('security_post_id', $post->id)
            ->whereDate('assignment_date', $today)
            ->with(['securityUser', 'shift'])
            ->get();

        $workingHours = $this->getWorkingHoursInfo($post);
        $isAreaSupervisor = $this->isAreaSupervisor(Auth::user());

        return view('security.posts.my-post', compact('post', 'schedule', 'personnel', 'workingHours', 'isAreaSupervisor'));
    }

    /**
     * Get post staffing status (API)
     */
    public function getStaffingStatus(SecurityPost $securityPost)
    {
        $user = Auth::user();
        
        if (!$this->canViewPost($user, $securityPost->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this post.'
            ], 403);
        }

        $currentPersonnel = $securityPost->currentSchedules()
            ->whereIn('status', ['scheduled', 'active'])
            ->count();

        $status = $currentPersonnel >= $securityPost->max_personnel 
            ? 'fully_staffed' 
            : ($currentPersonnel > 0 ? 'understaffed' : 'unstaffed');

        $workingHours = $this->getWorkingHoursInfo($securityPost);

        return response()->json([
            'success' => true,
            'data' => [
                'post_id' => $securityPost->id,
                'post_name' => $securityPost->name,
                'max_personnel' => $securityPost->max_personnel,
                'current_personnel' => $currentPersonnel,
                'status' => $status,
                'status_label' => ucfirst(str_replace('_', ' ', $status)),
                'staffing_rate' => $securityPost->max_personnel > 0 
                    ? round(($currentPersonnel / $securityPost->max_personnel) * 100, 1)
                    : 0,
                'working_hours' => $workingHours,
            ]
        ]);
    }

    /**
     * Get all posts with staffing status (for supervisor dashboard)
     */
    public function getPostsWithStaffing()
    {
        $user = Auth::user();
        
        $posts = SecurityPost::where('is_active', true)
            ->whereNull('deleted_at')
            ->withCount(['currentSchedules as current_personnel'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $posts->map(function($post) {
                $workingHours = $this->getWorkingHoursInfo($post);
                
                return [
                    'id' => $post->id,
                    'name' => $post->name,
                    'code' => $post->code,
                    'type' => $post->type,
                    'max_personnel' => $post->max_personnel,
                    'current_personnel' => $post->current_personnel,
                    'staffing_status' => $post->current_personnel >= $post->max_personnel 
                        ? 'fully_staffed' 
                        : ($post->current_personnel > 0 ? 'understaffed' : 'unstaffed'),
                    'staffing_rate' => $post->max_personnel > 0 
                        ? round(($post->current_personnel / $post->max_personnel) * 100, 1)
                        : 0,
                    'working_hours' => $workingHours,
                ];
            }),
            'total' => $posts->count(),
            'fully_staffed' => $posts->filter(function($post) {
                return $post->current_personnel >= $post->max_personnel;
            })->count(),
            'understaffed' => $posts->filter(function($post) {
                return $post->current_personnel > 0 && $post->current_personnel < $post->max_personnel;
            })->count(),
            'unstaffed' => $posts->filter(function($post) {
                return $post->current_personnel === 0;
            })->count(),
        ]);
    }

    /**
     * Get post details for QR code scan
     */
    public function getPostForScan($code)
    {
        $post = SecurityPost::where('code', $code)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid QR code or post not found.'
            ], 404);
        }

        $user = Auth::user();
        
        if (!$this->canViewPost($user, $post->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this post.'
            ], 403);
        }

        $workingHours = $this->getWorkingHoursInfo($post);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $post->id,
                'name' => $post->name,
                'code' => $post->code,
                'type' => $post->type,
                'location' => $post->location,
                'working_hours' => $workingHours,
                'current_personnel' => $post->currentSchedules()
                    ->whereIn('status', ['scheduled', 'active'])
                    ->count(),
                'max_personnel' => $post->max_personnel,
                'equipment' => is_array($post->equipment) ? $post->equipment : [],
                'restrictions' => is_array($post->restrictions) ? $post->restrictions : [],
            ]
        ]);
    }

    /**
     * Get posts by type (API)
     */
    public function getPostsByType(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:main_gate,internal_gate,checkpoint,patrol_route,observation_post,control_room,access_point',
        ]);

        $posts = SecurityPost::where('type', $request->type)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->select('id', 'name', 'code', 'location', 'max_personnel')
            ->get()
            ->map(function($post) {
                $workingHours = $this->getWorkingHoursInfo($post);
                return array_merge($post->toArray(), ['working_hours' => $workingHours]);
            });

        return response()->json([
            'success' => true,
            'data' => $posts,
            'count' => $posts->count(),
        ]);
    }

    /**
     * Get post availability for scheduling (API)
     */
    public function getAvailability(Request $request, SecurityPost $securityPost)
    {
        $user = Auth::user();
        
        if (!$this->canViewPost($user, $securityPost->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this post.'
            ], 403);
        }

        $date = $request->get('date', now()->toDateString());
        
        $schedules = SecuritySchedule::where('security_post_id', $securityPost->id)
            ->whereDate('assignment_date', $date)
            ->whereIn('status', ['scheduled', 'active'])
            ->count();

        $isFullyStaffed = $schedules >= $securityPost->max_personnel;
        $availableSlots = $securityPost->max_personnel - $schedules;

        return response()->json([
            'success' => true,
            'data' => [
                'post_id' => $securityPost->id,
                'post_name' => $securityPost->name,
                'date' => $date,
                'scheduled_personnel' => $schedules,
                'max_personnel' => $securityPost->max_personnel,
                'available_slots' => max(0, $availableSlots),
                'is_fully_staffed' => $isFullyStaffed,
                'staffing_rate' => $securityPost->max_personnel > 0 
                    ? round(($schedules / $securityPost->max_personnel) * 100, 1)
                    : 0,
            ]
        ]);
    }

    /**
     * Get shift coverage report for supervisor
     */
    public function getCoverageReport(Request $request)
    {
        $user = Auth::user();
        
        if (!$this->isAreaSupervisor($user)) {
            abort(403, 'Only Area Supervisors can view coverage reports.');
        }

        $date = $request->get('date', now()->toDateString());
        
        $supervisedPostIds = SecuritySupervisorAssignment::where('user_id', Auth::id())
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->pluck('security_post_id')
            ->toArray();

        $posts = SecurityPost::whereIn('id', $supervisedPostIds)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->withCount(['schedules as scheduled_count' => function($query) use ($date) {
                $query->whereDate('assignment_date', $date)
                      ->whereIn('status', ['scheduled', 'active']);
            }])
            ->get();

        $report = $posts->map(function($post) {
            $workingHours = $this->getWorkingHoursInfo($post);
            $isFullyStaffed = $post->scheduled_count >= $post->max_personnel;
            
            return [
                'post' => [
                    'id' => $post->id,
                    'name' => $post->name,
                    'code' => $post->code,
                ],
                'working_hours' => $workingHours,
                'scheduled' => $post->scheduled_count,
                'max_personnel' => $post->max_personnel,
                'status' => $isFullyStaffed ? 'fully_staffed' : 'understaffed',
                'status_label' => $isFullyStaffed ? 'Fully Staffed' : 'Understaffed',
                'shortage' => max(0, $post->max_personnel - $post->scheduled_count),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $report,
            'summary' => [
                'total_posts' => $report->count(),
                'fully_staffed' => $report->filter(fn($r) => $r['status'] === 'fully_staffed')->count(),
                'understaffed' => $report->filter(fn($r) => $r['status'] === 'understaffed')->count(),
                'total_shortage' => $report->sum('shortage'),
            ]
        ]);
    }

    // ==================== PERMISSION HELPER METHODS ====================

    /**
     * Check if a user is an Area Supervisor
     * Area Supervisors are users with active supervisor assignments
     */
    private function isAreaSupervisor($user): bool
    {
        if (!$user) {
            return false;
        }

        // Admin and Super Admin are always considered supervisors
        if ($user->type === self::USER_TYPE_SUPER_ADMIN || $user->type === self::USER_TYPE_ADMIN) {
            return true;
        }

        // Check if user has active supervisor assignment
        $hasAssignment = SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->exists();

        if ($hasAssignment) {
            return true;
        }

        // Check if user has supervisor role/permission flags
        if (($user->supervisor_level ?? 0) >= 2 && ($user->can_be_supervisor ?? false)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user is a supervisor for a specific post
     * Also checks for NULL assignment (can manage ALL posts)
     */
    private function isSupervisorForPost($user, $postId): bool
    {
        if (!$user) {
            return false;
        }

        // Admin and Super Admin can manage all posts
        if ($user->type === self::USER_TYPE_SUPER_ADMIN || $user->type === self::USER_TYPE_ADMIN) {
            return true;
        }

        // Check for specific post assignment
        $hasSpecificAssignment = SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('security_post_id', $postId)
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->exists();

        if ($hasSpecificAssignment) {
            return true;
        }

        // Check for NULL assignment (can manage ALL posts)
        $hasNullAssignment = SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNull('security_post_id')
            ->where(function($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->exists();

        return $hasNullAssignment;
    }

    /**
     * Check if user has edit permission for a post
     * Handles both specific and NULL assignments
     */
    private function hasEditPermission($user, $postId): bool
    {
        if (!$user) {
            return false;
        }

        // Admin and Super Admin have full permissions
        if ($user->type === self::USER_TYPE_SUPER_ADMIN || $user->type === self::USER_TYPE_ADMIN) {
            return true;
        }

        // Check for assignment (specific or NULL)
        $assignment = SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where(function($q) use ($postId) {
                $q->where('security_post_id', $postId)
                  ->orWhereNull('security_post_id'); // ✅ NULL means ALL posts
            })
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->first();

        if (!$assignment) {
            return false;
        }

        // Check explicit edit permission
        if ($assignment->can_edit_schedules) {
            return true;
        }

        // Check if metadata contains edit permission
        $metadata = is_array($assignment->metadata) ? $assignment->metadata : [];
        $permissions = $metadata['permissions'] ?? [];
        
        return in_array('edit_schedules', $permissions) || in_array('full_access', $permissions);
    }

    /**
 * Check if user has delete permission for a post
 * Handles both specific and NULL assignments
 */
private function hasDeletePermission($user, $postId): bool
{
    if (!$user) {
        return false;
    }

    // Admin and Super Admin have full permissions
    if ($user->type === self::USER_TYPE_SUPER_ADMIN || $user->type === self::USER_TYPE_ADMIN) {
        return true;
    }

    // Check for assignment (specific or NULL)
    $assignment = SecuritySupervisorAssignment::where('user_id', $user->id)
        ->where(function($q) use ($postId) {
            $q->where('security_post_id', $postId)
              ->orWhereNull('security_post_id'); // ✅ NULL means ALL posts
        })
        ->where('is_active', true)
        ->where(function($q) {
            $q->whereNull('end_date')
              ->orWhere('end_date', '>=', now());
        })
        ->first();

    if (!$assignment) {
        return false;
    }

    // Check explicit delete permission in metadata
    $metadata = is_array($assignment->metadata) ? $assignment->metadata : [];
    $permissions = $metadata['permissions'] ?? [];
    
    // Check if has delete permission or full access
    if (in_array('delete_posts', $permissions) || in_array('full_access', $permissions)) {
        return true;
    }
    
    // If can_edit_schedules is true, grant delete permission as well
    if ($assignment->can_edit_schedules) {
        return true;
    }
    
    return false;
}

    /**
     * Check if user can view a post
     * ALL security personnel can view ALL active posts
     */
    private function canViewPost($user, $postId): bool
    {
        if (!$user) {
            return false;
        }

        // Admins can view all
        if ($user->type === self::USER_TYPE_SUPER_ADMIN || $user->type === self::USER_TYPE_ADMIN) {
            return true;
        }

        // All security personnel can view all active posts
        if ($user->type === self::USER_TYPE_SECURITY_PERSONNEL) {
            return true;
        }

        // Other user types have limited access
        return false;
    }

    // ==================== HELPER METHODS ====================

    /**
     * Calculate duration in hours between two times (handles overnight)
     */
    private function calculateDurationHours($start, $end)
    {
        if ($start->format('H:i') === $end->format('H:i')) {
            return 0;
        }
        
        if ($end->lessThan($start)) {
            $end->addDay();
        }
        
        return $start->diffInHours($end);
    }

    /**
     * Format working hours for display
     */
    private function formatWorkingHoursDisplay($start, $end, $isOvernight)
    {
        $startFormatted = Carbon::parse($start)->format('g:i A');
        $endFormatted = Carbon::parse($end)->format('g:i A');
        
        if ($isOvernight) {
            return "{$startFormatted} - {$endFormatted} (Overnight)";
        }
        
        return "{$startFormatted} - {$endFormatted}";
    }

    /**
     * Get working hours information for a post
     */
    private function getWorkingHoursInfo($post)
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
 * Helper method to return clean JSON response without BOM
 */
private function cleanJsonResponse($data, $status = 200)
{
    // Clean any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set proper headers
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Encode JSON without BOM
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    
    // Remove any BOM if present
    if (substr($json, 0, 3) === "\xEF\xBB\xBF") {
        $json = substr($json, 3);
    }
    
    return response($json, $status)
        ->header('Content-Type', 'application/json; charset=utf-8')
        ->header('X-Content-Type-Options', 'nosniff');
}

}