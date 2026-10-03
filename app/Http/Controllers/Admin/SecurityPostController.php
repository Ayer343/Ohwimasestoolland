<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityPost;
use Illuminate\Http\Request;
use App\Models\PostQrCode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SecurityPostController extends Controller
{
    /**
     * Display a listing of security posts
     */
    public function index(Request $request)
    {
        $query = SecurityPost::withCount(['schedules as total_assignments'])
            ->withCount(['currentSchedules as current_personnel'])
            ->orderBy('is_active', 'desc')
            ->orderBy('name');

        // Filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('digital_address', 'like', "%{$search}%");
            });
        }

        // Status filtering
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

        // Exclude trashed posts
        if (!$request->boolean('show_trashed')) {
            $query->whereNull('deleted_at');
        }

        $posts = $query->paginate(20);

        // Get statistics (exclude trashed for main stats)
        $stats = [
            'total_posts' => SecurityPost::whereNull('deleted_at')->count(),
            'active_posts' => SecurityPost::whereNull('deleted_at')->where('is_active', true)->count(),
            'main_gates' => SecurityPost::whereNull('deleted_at')->where('type', 'main_gate')->count(),
            'internal_gates' => SecurityPost::whereNull('deleted_at')->where('type', 'internal_gate')->count(),
            'fully_staffed' => $this->getFullyStaffedCount(),
            'understaffed' => $this->getUnderstaffedCount(),
            'trashed_posts' => SecurityPost::onlyTrashed()->count(),
        ];

        // Get posts with staffing issues for alert (exclude trashed)
        $staffingAlerts = SecurityPost::withCount(['currentSchedules as current_personnel'])
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get()
            ->filter(function($post) {
                return $post->current_personnel < $post->max_personnel;
            })
            ->sortByDesc(function($post) {
                return $post->max_personnel - $post->current_personnel;
            });

        return view('admin.security-posts.index', compact(
            'posts',
            'stats',
            'staffingAlerts',
            'request'
        ));
    }

    /**
     * Display trashed security posts
     */
    public function trash(Request $request)
    {
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
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('digital_address', 'like', "%{$search}%");
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

        return view('admin.security-posts.trash', compact(
            'trashedPosts',
            'trashStats',
            'request'
        ));
    }

    /**
     * Show the form for creating a new security post
     */
    public function create()
    {
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

        return view('admin.security-posts.create', compact('postTypes', 'defaultEquipment'));
    }

    /**
     * Store a newly created security post
     */
    public function store(Request $request)
    {
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
            
            // Check if start and end are the same
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
                
                // Determine if this is an overnight shift
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

            // Log activity
            Log::info('Security post created', [
                'post_id' => $post->id,
                'name' => $post->name,
                'code' => $post->code,
                'working_hours' => $post->working_hours ?? null,
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()->route('admin.security-posts.show', $post)
                ->with('success', 'Security post created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create security post: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to create security post. Please try again.')
                ->withInput();
        }
    }

    /**
     * Display the specified security post
     */
    public function show(SecurityPost $securityPost)
    {
        // Check if post is trashed
        if ($securityPost->trashed()) {
            return redirect()->route('admin.security-posts.trash')
                ->with('warning', 'This security post has been deleted.');
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
            'completed_shifts' => $securityPost->schedules()->where('status', 'completed')->count(),
            'active_today' => $todaySchedules->where('status', 'active')->count(),
            'staffing_rate' => $securityPost->max_personnel > 0 
                ? round(($todaySchedules->count() / $securityPost->max_personnel) * 100, 1)
                : 0,
        ];

        // Get equipment list
        $equipment = is_array($securityPost->equipment) 
            ? $securityPost->equipment 
            : [];

        // Get restrictions
        $restrictions = is_array($securityPost->restrictions) 
            ? $securityPost->restrictions 
            : [];

        // Get working hours info
        $workingHours = $this->getWorkingHoursInfo($securityPost);

        return view('admin.security-posts.show', compact(
            'securityPost',
            'todaySchedules',
            'upcomingSchedules',
            'stats',
            'equipment',
            'restrictions',
            'workingHours'
        ));
    }

    /**
     * Show the form for editing the specified security post
     */
    public function edit(SecurityPost $securityPost)
    {
        // Check if post is trashed
        if ($securityPost->trashed()) {
            return redirect()->route('admin.security-posts.trash')
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

        // Prepare equipment array
        $equipment = is_array($securityPost->equipment) 
            ? $securityPost->equipment 
            : [];

        // Prepare restrictions array
        $restrictions = is_array($securityPost->restrictions) 
            ? $securityPost->restrictions 
            : [];

        // Prepare working hours
        $workingHoursStart = '';
        $workingHoursEnd = '';
        $isOvernight = false;
        if (is_array($securityPost->working_hours)) {
            $workingHoursStart = $securityPost->working_hours['start'] ?? '';
            $workingHoursEnd = $securityPost->working_hours['end'] ?? '';
            $isOvernight = $securityPost->working_hours['is_overnight'] ?? false;
        }

        return view('admin.security-posts.edit', compact(
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
     * Update the specified security post
     */
    public function update(Request $request, SecurityPost $securityPost)
    {
        // Check if post is trashed
        if ($securityPost->trashed()) {
            return redirect()->route('admin.security-posts.trash')
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
            
            // Check if start and end are the same
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

            // Handle working hours with overnight support
            if ($request->filled('working_hours_start') && $request->filled('working_hours_end')) {
                $start = Carbon::parse($request->working_hours_start);
                $end = Carbon::parse($request->working_hours_end);
                
                // Determine if this is an overnight shift
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

            // Log activity
            Log::info('Security post updated', [
                'post_id' => $securityPost->id,
                'name' => $securityPost->name,
                'working_hours' => $securityPost->working_hours ?? null,
                'updated_by' => Auth::id(),
                'changes' => $updateData,
            ]);

            DB::commit();

            return redirect()->route('admin.security-posts.show', $securityPost)
                ->with('success', 'Security post updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update security post: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to update security post. Please try again.')
                ->withInput();
        }
    }

    /**
     * Remove the specified security post (soft delete)
     */
    public function destroy(SecurityPost $securityPost)
    {
        // Check if post is already trashed
        if ($securityPost->trashed()) {
            return redirect()->route('admin.security-posts.trash.index')
                ->with('warning', 'Security post is already deleted.');
        }

        // Check if post has active schedules
        $hasActiveSchedules = $securityPost->schedules()
            ->whereDate('assignment_date', '>=', now()->toDateString())
            ->whereIn('status', ['scheduled', 'active'])
            ->exists();

        if ($hasActiveSchedules) {
            return redirect()->back()
                ->with('error', 'Cannot delete security post with active or upcoming schedules.');
        }

        DB::beginTransaction();

        try {
            // Soft delete the post
            $postName = $securityPost->name;
            $securityPost->delete();

            // Log activity
            Log::info('Security post soft deleted', [
                'post_id' => $securityPost->id,
                'name' => $postName,
                'deleted_by' => Auth::id(),
                'deleted_at' => now(),
            ]);

            DB::commit();

            return redirect()->route('admin.security-posts.index')
                ->with('success', 'Security post moved to trash successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete security post: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to delete security post. Please try again.');
        }
    }

    /**
     * Force delete a security post from trash
     */
    public function forceDelete($id)
    {
        $post = SecurityPost::withTrashed()->findOrFail($id);

        if (!$post->trashed()) {
            return redirect()->route('admin.security-posts.index')
                ->with('error', 'Post is not in trash.');
        }

        DB::beginTransaction();

        try {
            // Check for any related records that might prevent deletion
            $hasSchedules = $post->schedules()->exists();
            
            if ($hasSchedules) {
                // Cancel all schedules
                $post->schedules()->delete();
            }

            $postName = $post->name;
            
            // Permanently delete
            $post->forceDelete();

            // Log activity
            Log::warning('Security post permanently deleted', [
                'post_id' => $id,
                'name' => $postName,
                'deleted_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()->route('admin.security-posts.trash.index')
                ->with('success', 'Security post permanently deleted.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to force delete security post: ' . $e->getMessage());

            return redirect()->route('admin.security-posts.trash.index')
                ->with('error', 'Failed to permanently delete security post.');
        }
    }

    /**
     * Restore a soft-deleted post from trash
     */
    public function restore($id)
    {
        $post = SecurityPost::withTrashed()->findOrFail($id);

        if (!$post->trashed()) {
            return redirect()->route('admin.security-posts.index')
                ->with('warning', 'Post is not deleted.');
        }

        DB::beginTransaction();

        try {
            // Check for name/code conflicts before restoring
            $nameExists = SecurityPost::where('name', $post->name)
                ->where('id', '!=', $post->id)
                ->exists();
                
            $codeExists = SecurityPost::where('code', $post->code)
                ->where('id', '!=', $post->id)
                ->exists();

            if ($nameExists || $codeExists) {
                return redirect()->route('admin.security-posts.trash.index')
                    ->with('error', 'Cannot restore post. Name or code already exists for another active post.');
            }

            $post->restore();

            // Deactivate by default when restoring
            $post->update(['is_active' => false]);

            // Log activity
            Log::info('Security post restored from trash', [
                'post_id' => $post->id,
                'name' => $post->name,
                'restored_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()->route('admin.security-posts.show', $post)
                ->with('success', 'Security post restored successfully. It has been deactivated by default.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore security post: ' . $e->getMessage());

            return redirect()->route('admin.security-posts.trash.index')
                ->with('error', 'Failed to restore security post.');
        }
    }

    /**
     * Restore multiple posts from trash
     */
    public function bulkRestore(Request $request)
{
    // Set JSON response headers early
    header('Content-Type: application/json; charset=utf-8');
    
    $validator = Validator::make($request->all(), [
        'post_ids' => 'required|array',
        'post_ids.*' => 'exists:security_posts,id',
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
        $posts = SecurityPost::withTrashed()
            ->whereIn('id', $request->post_ids)
            ->whereNotNull('deleted_at')
            ->get();

        $restoredCount = 0;
        $failedPosts = [];

        foreach ($posts as $post) {
            // Check for conflicts
            $nameExists = SecurityPost::where('name', $post->name)
                ->where('id', '!=', $post->id)
                ->exists();
                
            $codeExists = SecurityPost::where('code', $post->code)
                ->where('id', '!=', $post->id)
                ->exists();

            if ($nameExists || $codeExists) {
                $failedPosts[] = [
                    'id' => $post->id,
                    'name' => $post->name,
                    'reason' => $nameExists ? 'Name conflict' : 'Code conflict'
                ];
                continue;
            }

            $post->restore();
            $post->update(['is_active' => false]);
            $restoredCount++;

            Log::info('Security post restored from trash (bulk)', [
                'post_id' => $post->id,
                'name' => $post->name,
                'restored_by' => Auth::id(),
            ]);
        }

        DB::commit();

        $message = "Restored {$restoredCount} out of {$posts->count()} posts.";
        if (count($failedPosts) > 0) {
            $message .= " Failed to restore " . count($failedPosts) . " posts due to conflicts.";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'restored_count' => $restoredCount,
            'failed_posts' => $failedPosts,
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to bulk restore posts: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to restore posts.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Empty trash (permanently delete all trashed posts)
     */
    public function emptyTrash()
    {
        $trashedCount = SecurityPost::onlyTrashed()->count();

        if ($trashedCount === 0) {
            return redirect()->route('admin.security-posts.trash.index')
                ->with('info', 'Trash is already empty.');
        }

        DB::beginTransaction();

        try {
            // Get all trashed posts
            $trashedPosts = SecurityPost::onlyTrashed()->get();
            
            // Delete schedules for each post first
            foreach ($trashedPosts as $post) {
                $post->schedules()->delete();
            }

            // Permanently delete all trashed posts
            $deletedCount = SecurityPost::onlyTrashed()->forceDelete();

            // Log activity
            Log::warning('Trash emptied - All security posts permanently deleted', [
                'count' => $deletedCount,
                'deleted_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()->route('admin.security-posts.trash.index')
                ->with('success', "{$deletedCount} security posts permanently deleted from trash.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to empty trash: ' . $e->getMessage());

            return redirect()->route('admin.security-posts.trash.index')
                ->with('error', 'Failed to empty trash.');
        }
    }

    /**
     * Toggle post activation status
     */
    public function toggleActivation(SecurityPost $securityPost)
    {
        // Check if post is trashed
        if ($securityPost->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot activate/deactivate a deleted post.',
            ], 400);
        }

        DB::beginTransaction();

        try {
            $securityPost->update([
                'is_active' => !$securityPost->is_active
            ]);

            // Log activity
            Log::info('Security post activation toggled', [
                'post_id' => $securityPost->id,
                'name' => $securityPost->name,
                'new_status' => $securityPost->is_active ? 'active' : 'inactive',
                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $securityPost->is_active 
                    ? 'Security post activated successfully.' 
                    : 'Security post deactivated successfully.',
                'is_active' => $securityPost->is_active,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to toggle security post activation: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update activation status.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get posts for dropdown/autocomplete
     */
    public function getPosts(Request $request)
    {
        $query = SecurityPost::active()->orderBy('name');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%");
            });
        }

        $posts = $query->limit(50)->get();

        return response()->json([
            'success' => true,
            'posts' => $posts->map(function($post) {
                return [
                    'id' => $post->id,
                    'name' => $post->name,
                    'code' => $post->code,
                    'type' => $post->type,
                    'max_personnel' => $post->max_personnel,
                    'current_personnel' => $post->getCurrentPersonnelCount(),
                    'is_fully_staffed' => $post->isFullyStaffed(),
                    'working_hours' => $this->getWorkingHoursInfo($post),
                ];
            }),
        ]);
    }

    /**
     * Get staffing statistics for dashboard
     */
    public function getStaffingStatistics()
    {
        $posts = SecurityPost::active()
            ->withCount(['currentSchedules as current_personnel'])
            ->get();

        $fullyStaffed = $posts->filter(function($post) {
            return $post->current_personnel >= $post->max_personnel;
        })->count();

        $understaffed = $posts->filter(function($post) {
            return $post->current_personnel > 0 && $post->current_personnel < $post->max_personnel;
        })->count();

        $unstaffed = $posts->filter(function($post) {
            return $post->current_personnel === 0;
        })->count();

        return response()->json([
            'success' => true,
            'statistics' => [
                'total_posts' => $posts->count(),
                'fully_staffed' => $fullyStaffed,
                'understaffed' => $understaffed,
                'unstaffed' => $unstaffed,
                'staffing_rate' => $posts->count() > 0 
                    ? round(($fullyStaffed / $posts->count()) * 100, 1)
                    : 0,
            ],
            'posts' => $posts->map(function($post) {
                return [
                    'id' => $post->id,
                    'name' => $post->name,
                    'code' => $post->code,
                    'type' => $post->type,
                    'current_personnel' => $post->current_personnel,
                    'max_personnel' => $post->max_personnel,
                    'staffing_status' => $post->current_personnel >= $post->max_personnel 
                        ? 'fully_staffed' 
                        : ($post->current_personnel > 0 ? 'understaffed' : 'unstaffed'),
                ];
            }),
        ]);
    }

    /**
     * Export posts to CSV
     */
    public function exportPosts(Request $request)
    {
        $query = SecurityPost::withCount(['schedules as total_assignments'])
            ->withCount(['currentSchedules as current_personnel']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Include trashed posts if requested
        if ($request->boolean('include_trashed')) {
            $query->withTrashed();
        }

        $posts = $query->orderBy('name')->get();

        $fileName = 'security_posts_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function() use ($posts) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF"); // UTF-8 BOM
            
            // Headers
            fputcsv($file, [
                'ID',
                'Name',
                'Code',
                'Type',
                'Location',
                'Digital Address',
                'Max Personnel',
                'Current Personnel',
                'Status',
                'Deleted Status',
                'Working Hours',
                'Is Overnight',
                'Duration (Hours)',
                'Requires Check-in',
                'Equipment',
                'Restrictions',
                'Total Assignments',
                'Created At',
                'Updated At',
                'Deleted At',
            ]);

            // Data
            foreach ($posts as $post) {
                $workingHours = $this->getWorkingHoursInfo($post);
                
                fputcsv($file, [
                    $post->id,
                    $post->name,
                    $post->code,
                    ucfirst(str_replace('_', ' ', $post->type)),
                    $post->location,
                    $post->digital_address ?? 'N/A',
                    $post->max_personnel,
                    $post->current_personnel,
                    $post->is_active ? 'Active' : 'Inactive',
                    $post->trashed() ? 'Deleted' : 'Active',
                    $workingHours['display'] ?? 'N/A',
                    $workingHours['is_overnight'] ? 'Yes' : 'No',
                    $workingHours['duration_hours'] ?? 0,
                    $post->requires_checkin ? 'Yes' : 'No',
                    is_array($post->equipment) ? implode('; ', $post->equipment) : 'N/A',
                    is_array($post->restrictions) ? implode('; ', $post->restrictions) : 'N/A',
                    $post->total_assignments,
                    $post->created_at->format('Y-m-d H:i:s'),
                    $post->updated_at->format('Y-m-d H:i:s'),
                    $post->deleted_at ? $post->deleted_at->format('Y-m-d H:i:s') : 'N/A',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get post details with availability
     */
    public function getPostDetails(SecurityPost $securityPost)
    {
        $post = $securityPost->load(['currentSchedules.securityUser']);

        // Get upcoming schedules
        $upcomingSchedules = $post->schedules()
            ->whereDate('assignment_date', '>', now()->toDateString())
            ->whereDate('assignment_date', '<=', now()->addDays(7)->toDateString())
            ->with(['securityUser', 'shift'])
            ->orderBy('assignment_date')
            ->get()
            ->groupBy('assignment_date');

        return response()->json([
            'success' => true,
            'post' => [
                'id' => $post->id,
                'name' => $post->name,
                'code' => $post->code,
                'type' => $post->type,
                'location' => $post->location,
                'digital_address' => $post->digital_address,
                'max_personnel' => $post->max_personnel,
                'current_personnel' => $post->current_personnel,
                'working_hours' => $this->getWorkingHoursInfo($post),
                'equipment' => is_array($post->equipment) ? $post->equipment : [],
                'restrictions' => is_array($post->restrictions) ? $post->restrictions : [],
                'requires_checkin' => $post->requires_checkin,
                'is_active' => $post->is_active,
                'is_trashed' => $post->trashed(),
                'staffing_status' => $post->isFullyStaffed() ? 'fully_staffed' : 
                                    ($post->current_personnel > 0 ? 'understaffed' : 'unstaffed'),
            ],
            'current_schedules' => $post->currentSchedules->map(function($schedule) {
                return [
                    'id' => $schedule->id,
                    'security_user' => [
                        'id' => $schedule->securityUser->id,
                        'name' => $schedule->securityUser->name,
                        'phone' => $schedule->securityUser->phone,
                    ],
                    'shift' => $schedule->shift->name,
                    'time_range' => $schedule->shift->getTimeRange(),
                    'checkin_time' => $schedule->checkin_time,
                    'status' => $schedule->status,
                ];
            }),
            'upcoming_schedules' => $upcomingSchedules,
        ]);
    }

    /**
     * Bulk update posts
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'post_ids' => 'required|array',
            'post_ids.*' => 'exists:security_posts,id',
            'action' => 'required|in:activate,deactivate,update_type,trash,restore,force_delete',
            'data' => 'nullable|array',
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
            $posts = SecurityPost::withTrashed()->whereIn('id', $request->post_ids)->get();
            $updatedCount = 0;
            $failedPosts = [];

            foreach ($posts as $post) {
                try {
                    switch ($request->action) {
                        case 'activate':
                            if (!$post->trashed()) {
                                $post->update(['is_active' => true]);
                                $updatedCount++;
                            }
                            break;
                        
                        case 'deactivate':
                            if (!$post->trashed()) {
                                // Check for active schedules before deactivating
                                $hasActiveSchedules = $post->currentSchedules()
                                    ->whereIn('status', ['scheduled', 'active'])
                                    ->exists();
                                
                                if (!$hasActiveSchedules) {
                                    $post->update(['is_active' => false]);
                                    $updatedCount++;
                                } else {
                                    $failedPosts[] = [
                                        'id' => $post->id,
                                        'name' => $post->name,
                                        'reason' => 'Has active schedules'
                                    ];
                                }
                            }
                            break;
                        
                        case 'update_type':
                            if (!$post->trashed() && isset($request->data['type'])) {
                                $post->update(['type' => $request->data['type']]);
                                $updatedCount++;
                            }
                            break;
                        
                        case 'trash':
                            if (!$post->trashed()) {
                                // Check for active schedules
                                $hasActiveSchedules = $post->currentSchedules()
                                    ->whereIn('status', ['scheduled', 'active'])
                                    ->exists();
                                
                                if (!$hasActiveSchedules) {
                                    $post->delete();
                                    $updatedCount++;
                                } else {
                                    $failedPosts[] = [
                                        'id' => $post->id,
                                        'name' => $post->name,
                                        'reason' => 'Has active schedules'
                                    ];
                                }
                            }
                            break;
                        
                        case 'restore':
                            if ($post->trashed()) {
                                // Check for conflicts
                                $nameExists = SecurityPost::where('name', $post->name)
                                    ->where('id', '!=', $post->id)
                                    ->exists();
                                    
                                $codeExists = SecurityPost::where('code', $post->code)
                                    ->where('id', '!=', $post->id)
                                    ->exists();

                                if (!$nameExists && !$codeExists) {
                                    $post->restore();
                                    $post->update(['is_active' => false]);
                                    $updatedCount++;
                                } else {
                                    $failedPosts[] = [
                                        'id' => $post->id,
                                        'name' => $post->name,
                                        'reason' => $nameExists ? 'Name conflict' : 'Code conflict'
                                    ];
                                }
                            }
                            break;
                        
                        case 'force_delete':
                            if ($post->trashed()) {
                                // Delete related schedules first
                                $post->schedules()->delete();
                                $post->forceDelete();
                                $updatedCount++;
                            }
                            break;
                    }
                } catch (\Exception $e) {
                    $failedPosts[] = [
                        'id' => $post->id,
                        'name' => $post->name,
                        'reason' => 'Error: ' . $e->getMessage()
                    ];
                }
            }

            DB::commit();

            $message = "Processed {$updatedCount} out of {$posts->count()} posts.";
            if (count($failedPosts) > 0) {
                $message .= " Failed to process " . count($failedPosts) . " posts.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'updated_count' => $updatedCount,
                'failed_posts' => $failedPosts,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk update posts: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process posts.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get post utilization statistics
     */
    public function getUtilizationStatistics(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $posts = SecurityPost::active()->whereNull('deleted_at')->get();
        $utilization = [];

        foreach ($posts as $post) {
            $totalSlots = $post->max_personnel * (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24);
            $filledSlots = $post->schedules()
                ->whereBetween('assignment_date', [$startDate, $endDate])
                ->count();
            
            $utilizationRate = $totalSlots > 0 ? ($filledSlots / $totalSlots) * 100 : 0;

            $utilization[] = [
                'post' => [
                    'id' => $post->id,
                    'name' => $post->name,
                    'code' => $post->code,
                    'type' => $post->type,
                ],
                'filled_slots' => $filledSlots,
                'total_slots' => $totalSlots,
                'utilization_rate' => round($utilizationRate, 2),
                'status' => $utilizationRate >= 80 ? 'high' : 
                           ($utilizationRate >= 50 ? 'medium' : 'low'),
            ];
        }

        return response()->json([
            'success' => true,
            'utilization' => $utilization,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ]);
    }

    /**
     * Get fully staffed posts count
     */
    private function getFullyStaffedCount()
    {
        return SecurityPost::active()
            ->whereNull('deleted_at')
            ->withCount(['currentSchedules as current_personnel'])
            ->get()
            ->filter(function($post) {
                return $post->current_personnel >= $post->max_personnel;
            })
            ->count();
    }

    /**
     * Get understaffed posts count
     */
    private function getUnderstaffedCount()
    {
        return SecurityPost::active()
            ->whereNull('deleted_at')
            ->withCount(['currentSchedules as current_personnel'])
            ->get()
            ->filter(function($post) {
                return $post->current_personnel > 0 && $post->current_personnel < $post->max_personnel;
            })
            ->count();
    }

    /**
     * Get trash statistics
     */
    public function getTrashStatistics()
    {
        $trashStats = [
            'total' => SecurityPost::onlyTrashed()->count(),
            'recent' => SecurityPost::onlyTrashed()
                ->where('deleted_at', '>=', now()->subDays(7))
                ->count(),
            'old' => SecurityPost::onlyTrashed()
                ->where('deleted_at', '<', now()->subDays(30))
                ->count(),
            'by_type' => SecurityPost::onlyTrashed()
                ->select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
        ];

        return response()->json([
            'success' => true,
            'statistics' => $trashStats,
        ]);
    }

    /**
     * Get global QR code statistics across all posts
     */
    public function getQrCodeStatistics()
    {
        try {
            // Check if PostQrCode model exists
            if (!class_exists('App\Models\PostQrCode')) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR Code functionality not available'
                ], 404);
            }
            
            // Get total QR codes
            $totalQrCodes = PostQrCode::count();
            
            // Get active QR codes (not expired and active)
            $activeQrCodes = PostQrCode::where('is_active', true)
                ->where(function($query) {
                    $query->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                })
                ->count();
            
            // Get QR codes by type
            $byType = PostQrCode::select('code_type', DB::raw('count(*) as count'))
                ->groupBy('code_type')
                ->pluck('count', 'code_type')
                ->toArray();
            
            // Get QR codes by post
            $byPost = PostQrCode::select('post_id', DB::raw('count(*) as total'))
                ->selectRaw('SUM(CASE WHEN is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 ELSE 0 END) as active')
                ->groupBy('post_id')
                ->get()
                ->keyBy('post_id')
                ->toArray();
            
            // Get recent QR code activity (last 7 days)
            $recentActivity = PostQrCode::where('created_at', '>=', now()->subDays(7))
                ->count();
            
            // Get expiring soon QR codes (next 7 days)
            $expiringSoon = PostQrCode::where('is_active', true)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays(7))
                ->where('expires_at', '>', now())
                ->count();
            
            // Get expired QR codes
            $expired = PostQrCode::where('is_active', true)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->count();
            
            // Get one-time use QR codes that have been used
            $oneTimeUsed = PostQrCode::where('code_type', 'one_time')
                ->where('uses_count', '>', 0)
                ->count();
            
            return response()->json([
                'success' => true,
                'stats' => [
                    'total' => $totalQrCodes,
                    'active' => $activeQrCodes,
                    'by_type' => $byType,
                    'by_post' => $byPost,
                    'recent_activity' => $recentActivity,
                    'expiring_soon' => $expiringSoon,
                    'expired' => $expired,
                    'one_time_used' => $oneTimeUsed,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get QR code statistics: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load QR code statistics',
                'stats' => [
                    'total' => 0,
                    'active' => 0,
                    'by_type' => [],
                    'by_post' => [],
                    'recent_activity' => 0,
                    'expiring_soon' => 0,
                    'expired' => 0,
                    'one_time_used' => 0,
                ]
            ], 500);
        }
    }

    /**
     * Calculate duration in hours between two times (handles overnight)
     */
    private function calculateDurationHours($start, $end)
    {
        if ($start->format('H:i') === $end->format('H:i')) {
            return 0;
        }
        
        if ($end->lessThan($start)) {
            // Overnight - add 24 hours
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
}