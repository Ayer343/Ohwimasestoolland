<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\SecurityPost;
use App\Models\SecuritySchedule;
use App\Models\SecuritySupervisorAssignment;
use App\Models\PostQrCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SecurityPostController extends Controller
{
    // ============================================================
    // USER TYPE CONSTANTS
    // ============================================================
    const USER_TYPE_SUPER_ADMIN        = 0;
    const USER_TYPE_ADMIN              = 1;
    const USER_TYPE_LANDLORD           = 2;
    const USER_TYPE_TENANT             = 3;
    const USER_TYPE_FIELD_AGENT        = 4;
    const USER_TYPE_DEVELOPER          = 5;
    const USER_TYPE_SECURITY_PERSONNEL = 6;

    // ============================================================
    // LIST
    // ============================================================

    /**
     * Two modes:
     *  - Head of Security (has active SecuritySupervisorAssignment):
     *    full admin-parity payload — stats grid, staffing alerts,
     *    QR stats, trashed count, per-row QR counts, show_trashed
     *    and status filters.
     *  - Regular security personnel: lightweight read-only list.
     */
    public function index(Request $request)
    {
        $user             = Auth::user();
        $isAreaSupervisor = $this->isAreaSupervisor($user);

        if ($isAreaSupervisor) {
            return $this->indexForSupervisor($request);
        }

        // ------------------ Regular personnel ------------------
        $query = SecurityPost::where('is_active', true)
            ->whereNull('deleted_at')
            ->withCount(['currentSchedules as current_personnel'])
            ->orderBy('name');

        $this->applyFilters($query, $request);

        $posts = $query->paginate(20)->withQueryString();

        $stats = [
            'total_posts'   => SecurityPost::where('is_active', true)->whereNull('deleted_at')->count(),
            'fully_staffed' => 0,
            'understaffed'  => 0,
            'unstaffed'     => 0,
        ];

        foreach ($posts as $post) {
            $current = $post->current_personnel ?? 0;
            if ($current >= $post->max_personnel)  $stats['fully_staffed']++;
            elseif ($current > 0)                  $stats['understaffed']++;
            else                                   $stats['unstaffed']++;
        }

        $posts->each(fn($p) => $p->working_hours_info = $this->getWorkingHoursInfo($p));

        $canManage    = $this->canManagePosts();
        $trashedCount = $canManage ? SecurityPost::onlyTrashed()->count() : 0;

        return view('security.posts.index', compact(
            'posts', 'stats', 'request', 'canManage', 'trashedCount', 'isAreaSupervisor'
        ));
    }

    /**
     * Supervisor listing: same shape as the admin controller returns.
     */
    protected function indexForSupervisor(Request $request)
    {
        $query = SecurityPost::withCount(['schedules as total_assignments'])
            ->withCount(['currentSchedules as current_personnel'])
            ->withCount(['qrCodes as qr_codes_count'])
            ->withCount(['qrCodes as active_qr_codes_count' => fn($q) =>
                $q->where('is_active', true)
                  ->where(fn($qq) => $qq->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ])
            ->orderBy('is_active', 'desc')
            ->orderBy('name');

        if (!$request->boolean('show_trashed')) {
            $query->whereNull('deleted_at');
        } else {
            $query->withTrashed();
        }

        $this->applyFilters($query, $request);

        $posts = $query->paginate(20)->withQueryString();

        $stats = [
            'total_posts'    => SecurityPost::whereNull('deleted_at')->count(),
            'active_posts'   => SecurityPost::whereNull('deleted_at')->where('is_active', true)->count(),
            'main_gates'     => SecurityPost::whereNull('deleted_at')->where('type', 'main_gate')->count(),
            'internal_gates' => SecurityPost::whereNull('deleted_at')->where('type', 'internal_gate')->count(),
            'fully_staffed'  => $this->getFullyStaffedCount(),
            'understaffed'   => $this->getUnderstaffedCount(),
            'trashed_posts'  => SecurityPost::onlyTrashed()->count(),
        ];

        $staffingAlerts = SecurityPost::withCount(['currentSchedules as current_personnel'])
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get()
            ->filter(fn($p) => $p->current_personnel < $p->max_personnel)
            ->sortByDesc(fn($p) => $p->max_personnel - $p->current_personnel);

        $qrCodeStats = $this->globalQrStats();

        $posts->each(fn($p) => $p->working_hours_info = $this->getWorkingHoursInfo($p));

        $trashedCount = SecurityPost::onlyTrashed()->count();

        return view('security.posts.index', compact(
            'posts', 'stats', 'staffingAlerts', 'qrCodeStats',
            'request', 'trashedCount'
        ) + ['isAreaSupervisor' => true]);
    }

    public function trash(Request $request)
    {
        $this->authorizeManage();

        $query = SecurityPost::onlyTrashed()
            ->withCount(['schedules as total_assignments'])
            ->orderBy('deleted_at', 'desc');

        if ($request->filled('type'))   $query->where('type', $request->type);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%{$s}%")
                                      ->orWhere('code', 'like', "%{$s}%")
                                      ->orWhere('location', 'like', "%{$s}%"));
        }

        $trashedPosts = $query->paginate(20);
        $trashStats   = $this->trashStatsArray();

        return view('security.posts.trash', compact('trashedPosts', 'trashStats', 'request'));
    }

    // ============================================================
    // CREATE / STORE
    // ============================================================

    public function create()
    {
        $this->authorizeManage();

        return view('security.posts.create', [
            'postTypes'        => $this->postTypeOptions(),
            'defaultEquipment' => $this->defaultEquipment(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManage();

        $validator = $this->validatePostPayload($request);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        if ($this->workingHoursAreIdentical($request)) {
            return back()->withErrors(['working_hours_end' => 'Start and end times cannot be the same.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $post = SecurityPost::create($this->buildPostPayload($request, true));

            Log::info('Security post created', [
                'post_id'    => $post->id,
                'name'       => $post->name,
                'code'       => $post->code,
                'created_by' => Auth::id(),
                'created_by_name' => Auth::user()?->name,
            ]);

            DB::commit();
            return redirect()->route('security.posts.show', $post)
                ->with('success', 'Security post created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create security post: ' . $e->getMessage());
            return back()->with('error', 'Failed to create security post. Please try again.')->withInput();
        }
    }

    // ============================================================
    // SHOW / EDIT / UPDATE
    // ============================================================

    public function show(SecurityPost $securityPost)
    {
        if (!$securityPost->is_active || $securityPost->trashed()) {
            abort(404, 'Post not available.');
        }
        if (!$this->canViewPost(Auth::user(), $securityPost->id)) {
            abort(403, 'You do not have access to this post.');
        }

        $securityPost->load([
            'schedules' => fn($q) => $q->whereDate('assignment_date', '>=', now()->subDays(7))
                                       ->orderBy('assignment_date', 'desc')
                                       ->limit(10),
            'schedules.securityUser',
            'schedules.shift',
        ]);

        $todaySchedules = $securityPost->currentSchedules()
            ->with(['securityUser', 'shift'])->get();

        $upcomingSchedules = $securityPost->schedules()
            ->whereDate('assignment_date', '>', now()->toDateString())
            ->whereDate('assignment_date', '<=', now()->addDays(7)->toDateString())
            ->with(['securityUser', 'shift'])
            ->orderBy('assignment_date')
            ->get();

        $stats = [
            'total_assignments' => $securityPost->schedules()->count(),
            'completed_shifts'  => $securityPost->schedules()->where('status', 'completed')->count(),
            'active_today'      => $todaySchedules->where('status', 'active')->count(),
            'staffing_rate'     => $securityPost->max_personnel > 0
                ? round(($todaySchedules->count() / $securityPost->max_personnel) * 100, 1)
                : 0,
        ];

        $workingHours = $this->getWorkingHoursInfo($securityPost);
        $canManage    = $this->canManagePosts();
        $isSupervisor = $this->isAreaSupervisor(Auth::user());

        return view('security.posts.show', compact(
            'securityPost', 'todaySchedules', 'upcomingSchedules',
            'stats', 'workingHours', 'canManage', 'isSupervisor'
        ));
    }

    public function edit(SecurityPost $securityPost)
    {
        $this->authorizeManage();

        if ($securityPost->trashed()) {
            return redirect()->route('security.posts.index')
                ->with('warning', 'Cannot edit a deleted security post.');
        }

        $postTypes    = $this->postTypeOptions();
        $equipment    = is_array($securityPost->equipment) ? $securityPost->equipment : [];
        $restrictions = is_array($securityPost->restrictions) ? $securityPost->restrictions : [];

        $workingHoursStart = $securityPost->working_hours['start'] ?? '';
        $workingHoursEnd   = $securityPost->working_hours['end']   ?? '';
        $isOvernight       = $securityPost->working_hours['is_overnight'] ?? false;

        return view('security.posts.edit', compact(
            'securityPost', 'postTypes', 'equipment', 'restrictions',
            'workingHoursStart', 'workingHoursEnd', 'isOvernight'
        ));
    }

    public function update(Request $request, SecurityPost $securityPost)
    {
        $this->authorizeManage();

        if ($securityPost->trashed()) {
            return redirect()->route('security.posts.index')
                ->with('warning', 'Cannot update a deleted security post.');
        }

        $validator = $this->validatePostPayload($request, $securityPost->id);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        if ($this->workingHoursAreIdentical($request)) {
            return back()->withErrors(['working_hours_end' => 'Start and end times cannot be the same.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $securityPost->update($this->buildPostPayload($request, false));

            Log::info('Security post updated', [
                'post_id'    => $securityPost->id,
                'name'       => $securityPost->name,
                'updated_by' => Auth::id(),
                'updated_by_name' => Auth::user()?->name,
            ]);

            DB::commit();
            return redirect()->route('security.posts.show', $securityPost)
                ->with('success', 'Security post updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update security post: ' . $e->getMessage());
            return back()->with('error', 'Failed to update security post. Please try again.')->withInput();
        }
    }

    // ============================================================
    // DELETE / RESTORE / TRASH
    // ============================================================

    public function destroy(SecurityPost $securityPost)
    {
        $this->authorizeManage();

        if ($securityPost->trashed()) {
            return $this->failure(request(), 'Security post is already deleted.', 422, route('security.posts.trash'));
        }
        if ($this->hasActiveSchedules($securityPost)) {
            return $this->failure(request(), 'Cannot delete security post with active or upcoming schedules.', 422);
        }

        DB::beginTransaction();
        try {
            $postName = $securityPost->name;
            $postId   = $securityPost->id;
            $securityPost->delete();

            Log::info('Security post soft deleted', [
                'post_id' => $postId, 'name' => $postName, 'deleted_by' => Auth::id(),
            ]);

            DB::commit();
            return $this->success(request(),
                'Security post moved to trash successfully.',
                ['post_id' => $postId, 'post_name' => $postName],
                route('security.posts.index'));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete security post: ' . $e->getMessage());
            return $this->failure(request(), 'Failed to delete security post. Please try again.', 500);
        }
    }

    public function restore($id)
    {
        $this->authorizeManage();

        $post = SecurityPost::withTrashed()->findOrFail($id);
        if (!$post->trashed()) {
            return $this->failure(request(), 'Post is not deleted.', 422);
        }

        DB::beginTransaction();
        try {
            if ($this->hasNameOrCodeConflict($post)) {
                DB::rollBack();
                return $this->failure(request(),
                    'Cannot restore post. Name or code already exists for another active post.', 422);
            }

            $post->restore();
            $post->update(['is_active' => true]);

            Log::info('Security post restored', ['post_id' => $post->id, 'restored_by' => Auth::id()]);
            DB::commit();

            return $this->success(request(),
                'Security post restored and activated successfully.',
                ['post_id' => $post->id, 'post_name' => $post->name],
                route('security.posts.index'));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore security post: ' . $e->getMessage());
            return $this->failure(request(), 'Failed to restore security post: ' . $e->getMessage(), 500);
        }
    }

    public function forceDelete($id)
    {
        $this->authorizeManage();

        $post = SecurityPost::withTrashed()->findOrFail($id);
        if (!$post->trashed()) {
            return $this->failure(request(), 'Post is not in trash.', 422);
        }

        DB::beginTransaction();
        try {
            if ($post->schedules()->exists()) {
                $post->schedules()->delete();
            }
            $postName = $post->name;
            $postId   = $post->id;
            $post->forceDelete();

            Log::warning('Security post permanently deleted', [
                'post_id' => $id, 'name' => $postName, 'deleted_by' => Auth::id(),
            ]);

            DB::commit();
            return $this->success(request(),
                'Security post permanently deleted.',
                ['post_id' => $postId, 'post_name' => $postName]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to force delete security post: ' . $e->getMessage());
            return $this->failure(request(), 'Failed to permanently delete security post: ' . $e->getMessage(), 500);
        }
    }

    public function emptyTrash()
    {
        $this->authorizeManage();

        $count = SecurityPost::onlyTrashed()->count();
        if ($count === 0) {
            return redirect()->route('security.posts.trash')->with('info', 'Trash is already empty.');
        }

        DB::beginTransaction();
        try {
            foreach (SecurityPost::onlyTrashed()->get() as $post) {
                $post->schedules()->delete();
            }
            $deletedCount = SecurityPost::onlyTrashed()->forceDelete();

            Log::warning('Trash emptied', ['count' => $deletedCount, 'deleted_by' => Auth::id()]);
            DB::commit();
            return redirect()->route('security.posts.trash')
                ->with('success', "{$deletedCount} security posts permanently deleted from trash.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to empty trash: ' . $e->getMessage());
            return redirect()->route('security.posts.trash')->with('error', 'Failed to empty trash.');
        }
    }

    public function bulkRestore(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'post_ids'   => 'required|array',
            'post_ids.*' => 'exists:security_posts,id',
        ]);
        if ($validator->fails()) {
            return $this->failure(request(), 'Validation failed.', 422, null, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $restored = 0;
            $failed   = [];

            foreach ($request->post_ids as $postId) {
                $post = SecurityPost::withTrashed()->find($postId);
                if (!$post || !$post->trashed()) {
                    $failed[] = ['id' => $postId, 'reason' => 'Not found or not in trash'];
                    continue;
                }
                if ($this->hasNameOrCodeConflict($post)) {
                    $failed[] = ['id' => $postId, 'reason' => 'Name/Code conflict'];
                    continue;
                }
                $post->restore();
                $post->update(['is_active' => true]);
                $restored++;
            }

            DB::commit();
            return $this->success(request(),
                "Restored {$restored} post(s).",
                ['restored_count' => $restored, 'failed_posts' => $failed],
                route('security.posts.index'));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk restore failed: ' . $e->getMessage());
            return $this->failure(request(), 'Server error: ' . $e->getMessage(), 500);
        }
    }

    public function bulkPermanentDelete(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'post_ids'   => 'required|array',
            'post_ids.*' => 'exists:security_posts,id',
        ]);
        if ($validator->fails()) {
            return $this->failure(request(), 'Validation failed.', 422, null, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $deleted = 0;
            $failed  = [];

            foreach ($request->post_ids as $postId) {
                $post = SecurityPost::withTrashed()->find($postId);
                if (!$post || !$post->trashed()) {
                    $failed[] = ['id' => $postId, 'reason' => 'Not found or not in trash'];
                    continue;
                }
                $post->schedules()->delete();
                $post->forceDelete();
                $deleted++;
            }

            DB::commit();
            return $this->success(request(),
                "Permanently deleted {$deleted} post(s).",
                ['deleted_count' => $deleted, 'failed_posts' => $failed]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk permanent delete failed: ' . $e->getMessage());
            return $this->failure(request(), 'Server error: ' . $e->getMessage(), 500);
        }
    }

    // ============================================================
    // BULK UPDATE
    // ============================================================

    public function bulkUpdate(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'post_ids'   => 'required|array',
            'post_ids.*' => 'exists:security_posts,id',
            'action'     => 'required|in:activate,deactivate,update_type,trash,restore,force_delete',
            'data'       => 'nullable|array',
            'data.type'  => 'nullable|in:main_gate,internal_gate,checkpoint,patrol_route,observation_post,control_room,access_point',
        ]);
        if ($validator->fails()) {
            return $this->failure(request(), 'Validation failed.', 422, null, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $posts   = SecurityPost::withTrashed()->whereIn('id', $request->post_ids)->get();
            $updated = 0;
            $failed  = [];

            foreach ($posts as $post) {
                try {
                    switch ($request->action) {
                        case 'activate':
                            if (!$post->trashed()) { $post->update(['is_active' => true]); $updated++; }
                            break;
                        case 'deactivate':
                            if (!$post->trashed() && !$this->hasActiveSchedules($post)) {
                                $post->update(['is_active' => false]); $updated++;
                            } elseif (!$post->trashed()) {
                                $failed[] = ['id' => $post->id, 'name' => $post->name, 'reason' => 'Has active schedules'];
                            }
                            break;
                        case 'update_type':
                            if (!$post->trashed() && !empty($request->data['type'])) {
                                $post->update(['type' => $request->data['type']]); $updated++;
                            }
                            break;
                        case 'trash':
                            if (!$post->trashed() && !$this->hasActiveSchedules($post)) {
                                $post->delete(); $updated++;
                            } elseif (!$post->trashed()) {
                                $failed[] = ['id' => $post->id, 'name' => $post->name, 'reason' => 'Has active schedules'];
                            }
                            break;
                        case 'restore':
                            if ($post->trashed() && !$this->hasNameOrCodeConflict($post)) {
                                $post->restore();
                                $post->update(['is_active' => false]);
                                $updated++;
                            } elseif ($post->trashed()) {
                                $failed[] = ['id' => $post->id, 'name' => $post->name, 'reason' => 'Name/Code conflict'];
                            }
                            break;
                        case 'force_delete':
                            if ($post->trashed()) {
                                $post->schedules()->delete();
                                $post->forceDelete();
                                $updated++;
                            }
                            break;
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $post->id, 'name' => $post->name, 'reason' => $e->getMessage()];
                }
            }

            DB::commit();
            return $this->success(request(),
                "Processed {$updated} post(s).",
                ['updated_count' => $updated, 'failed_posts' => $failed]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk update failed: ' . $e->getMessage());
            return $this->failure(request(), 'Failed to process posts.', 500);
        }
    }

    // ============================================================
    // TOGGLE ACTIVATION
    // ============================================================

    public function toggleActivation(SecurityPost $securityPost)
    {
        $this->authorizeManage();

        if ($securityPost->trashed()) {
            return $this->failure(request(), 'Cannot toggle a deleted post.', 400);
        }

        DB::beginTransaction();
        try {
            $securityPost->update(['is_active' => !$securityPost->is_active]);

            Log::info('Security post activation toggled', [
                'post_id'    => $securityPost->id,
                'new_status' => $securityPost->is_active ? 'active' : 'inactive',
                'updated_by' => Auth::id(),
            ]);

            DB::commit();
            return $this->success(request(),
                $securityPost->is_active ? 'Post activated.' : 'Post deactivated.',
                ['is_active' => $securityPost->is_active]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Toggle activation failed: ' . $e->getMessage());
            return $this->failure(request(), 'Failed to update activation.', 500);
        }
    }

    // ============================================================
    // AJAX / API
    // ============================================================

    public function getSchedule(Request $request, SecurityPost $securityPost)
    {
        if (!$this->canViewPost(Auth::user(), $securityPost->id)) {
            abort(403, 'You do not have access to this post.');
        }

        $date = $request->get('date', now()->toDateString());
        $schedules = SecuritySchedule::where('security_post_id', $securityPost->id)
            ->whereDate('assignment_date', $date)
            ->with(['securityUser', 'shift'])
            ->orderBy('security_shift_id')
            ->get();

        $workingHours = $this->getWorkingHoursInfo($securityPost);
        $canManage    = $this->canManagePosts();
        $isSupervisor = $this->isAreaSupervisor(Auth::user());

        return view('security.posts.schedule', compact(
            'securityPost', 'schedules', 'date', 'workingHours', 'canManage', 'isSupervisor'
        ));
    }

    public function myPost(Request $request)
    {
        $user  = Auth::user();
        $today = now()->toDateString();

        $schedule = SecuritySchedule::whereHas('securityUser', fn($q) => $q->where('id', $user->id))
            ->whereDate('assignment_date', $today)
            ->with(['post', 'shift'])
            ->first();

        if (!$schedule) {
            foreach (['security_user_id', 'personnel_id', 'user_id', 'employee_id'] as $column) {
                if (\Schema::hasColumn('security_schedules', $column)) {
                    $schedule = SecuritySchedule::where($column, $user->id)
                        ->whereDate('assignment_date', $today)
                        ->with(['post', 'shift'])
                        ->first();
                    if ($schedule) break;
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
        $isSupervisor = $this->isAreaSupervisor($user);

        return view('security.posts.my-post', compact(
            'post', 'schedule', 'personnel', 'workingHours', 'isSupervisor'
        ));
    }

    public function getStaffingStatus(SecurityPost $securityPost)
    {
        if (!$this->canViewPost(Auth::user(), $securityPost->id)) {
            return $this->failure(request(), 'You do not have access to this post.', 403);
        }

        $current = $securityPost->currentSchedules()->whereIn('status', ['scheduled', 'active'])->count();
        $status  = $this->staffingStatus($current, $securityPost->max_personnel);

        return $this->success(request(), 'OK', [
            'post_id'           => $securityPost->id,
            'post_name'         => $securityPost->name,
            'max_personnel'     => $securityPost->max_personnel,
            'current_personnel' => $current,
            'status'            => $status,
            'status_label'      => ucfirst(str_replace('_', ' ', $status)),
            'staffing_rate'     => $securityPost->max_personnel > 0
                ? round(($current / $securityPost->max_personnel) * 100, 1) : 0,
            'working_hours'     => $this->getWorkingHoursInfo($securityPost),
        ]);
    }

    public function getPostsWithStaffing()
    {
        $posts = SecurityPost::where('is_active', true)
            ->whereNull('deleted_at')
            ->withCount(['currentSchedules as current_personnel'])
            ->get();

        return $this->success(request(), 'OK', [
            'data' => $posts->map(fn($p) => [
                'id'                => $p->id,
                'name'              => $p->name,
                'code'              => $p->code,
                'type'              => $p->type,
                'max_personnel'     => $p->max_personnel,
                'current_personnel' => $p->current_personnel,
                'staffing_status'   => $this->staffingStatus($p->current_personnel, $p->max_personnel),
                'staffing_rate'     => $p->max_personnel > 0
                    ? round(($p->current_personnel / $p->max_personnel) * 100, 1) : 0,
                'working_hours'     => $this->getWorkingHoursInfo($p),
            ]),
            'total'         => $posts->count(),
            'fully_staffed' => $posts->filter(fn($p) => $p->current_personnel >= $p->max_personnel)->count(),
            'understaffed'  => $posts->filter(fn($p) => $p->current_personnel > 0 && $p->current_personnel < $p->max_personnel)->count(),
            'unstaffed'     => $posts->filter(fn($p) => $p->current_personnel === 0)->count(),
        ]);
    }

    public function getPostForScan($code)
    {
        $post = SecurityPost::where('code', $code)
            ->where('is_active', true)->whereNull('deleted_at')->first();

        if (!$post) {
            return $this->failure(request(), 'Invalid QR code or post not found.', 404);
        }
        if (!$this->canViewPost(Auth::user(), $post->id)) {
            return $this->failure(request(), 'You do not have access to this post.', 403);
        }

        return $this->success(request(), 'OK', [
            'id'                => $post->id,
            'name'              => $post->name,
            'code'              => $post->code,
            'type'              => $post->type,
            'location'          => $post->location,
            'working_hours'     => $this->getWorkingHoursInfo($post),
            'current_personnel' => $post->currentSchedules()
                ->whereIn('status', ['scheduled', 'active'])->count(),
            'max_personnel'     => $post->max_personnel,
            'equipment'         => is_array($post->equipment) ? $post->equipment : [],
            'restrictions'      => is_array($post->restrictions) ? $post->restrictions : [],
        ]);
    }

    public function getPostsByType(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:main_gate,internal_gate,checkpoint,patrol_route,observation_post,control_room,access_point',
        ]);

        $posts = SecurityPost::where('type', $request->type)
            ->where('is_active', true)->whereNull('deleted_at')
            ->select('id', 'name', 'code', 'location', 'max_personnel')
            ->get()
            ->map(fn($p) => array_merge($p->toArray(), ['working_hours' => $this->getWorkingHoursInfo($p)]));

        return $this->success(request(), 'OK', ['data' => $posts, 'count' => $posts->count()]);
    }

    public function getAvailability(Request $request, SecurityPost $securityPost)
    {
        if (!$this->canViewPost(Auth::user(), $securityPost->id)) {
            return $this->failure(request(), 'You do not have access to this post.', 403);
        }

        $date = $request->get('date', now()->toDateString());
        $scheduled = SecuritySchedule::where('security_post_id', $securityPost->id)
            ->whereDate('assignment_date', $date)
            ->whereIn('status', ['scheduled', 'active'])
            ->count();

        return $this->success(request(), 'OK', [
            'post_id'             => $securityPost->id,
            'post_name'           => $securityPost->name,
            'date'                => $date,
            'scheduled_personnel' => $scheduled,
            'max_personnel'       => $securityPost->max_personnel,
            'available_slots'     => max(0, $securityPost->max_personnel - $scheduled),
            'is_fully_staffed'    => $scheduled >= $securityPost->max_personnel,
            'staffing_rate'       => $securityPost->max_personnel > 0
                ? round(($scheduled / $securityPost->max_personnel) * 100, 1) : 0,
        ]);
    }

    public function getCoverageReport(Request $request)
    {
        $this->authorizeManage();

        $date = $request->get('date', now()->toDateString());

        $posts = SecurityPost::where('is_active', true)
            ->whereNull('deleted_at')
            ->withCount(['schedules as scheduled_count' => function ($q) use ($date) {
                $q->whereDate('assignment_date', $date)
                  ->whereIn('status', ['scheduled', 'active']);
            }])
            ->get();

        $report = $posts->map(function ($p) {
            $fully = $p->scheduled_count >= $p->max_personnel;
            return [
                'post'          => ['id' => $p->id, 'name' => $p->name, 'code' => $p->code],
                'working_hours' => $this->getWorkingHoursInfo($p),
                'scheduled'     => $p->scheduled_count,
                'max_personnel' => $p->max_personnel,
                'status'        => $fully ? 'fully_staffed' : 'understaffed',
                'status_label'  => $fully ? 'Fully Staffed' : 'Understaffed',
                'shortage'      => max(0, $p->max_personnel - $p->scheduled_count),
            ];
        });

        return $this->success(request(), 'OK', [
            'data'    => $report,
            'summary' => [
                'total_posts'    => $report->count(),
                'fully_staffed'  => $report->where('status', 'fully_staffed')->count(),
                'understaffed'   => $report->where('status', 'understaffed')->count(),
                'total_shortage' => $report->sum('shortage'),
            ],
        ]);
    }

    public function getPosts(Request $request)
    {
        $query = SecurityPost::where('is_active', true)->whereNull('deleted_at')->orderBy('name');

        if ($request->filled('type'))   $query->where('type', $request->type);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%{$s}%")
                                      ->orWhere('code', 'like', "%{$s}%"));
        }

        $posts = $query->limit(50)->get();

        return $this->success(request(), 'OK', [
            'posts' => $posts->map(fn($p) => [
                'id'                => $p->id,
                'name'              => $p->name,
                'code'              => $p->code,
                'type'              => $p->type,
                'max_personnel'     => $p->max_personnel,
                'current_personnel' => $p->getCurrentPersonnelCount(),
                'is_fully_staffed'  => $p->isFullyStaffed(),
                'working_hours'     => $this->getWorkingHoursInfo($p),
            ]),
        ]);
    }

    public function getPostDetails(SecurityPost $securityPost)
    {
        if (!$this->canViewPost(Auth::user(), $securityPost->id)) {
            return $this->failure(request(), 'You do not have access to this post.', 403);
        }

        $post = $securityPost->load(['currentSchedules.securityUser']);

        $upcoming = $post->schedules()
            ->whereDate('assignment_date', '>', now()->toDateString())
            ->whereDate('assignment_date', '<=', now()->addDays(7)->toDateString())
            ->with(['securityUser', 'shift'])
            ->orderBy('assignment_date')
            ->get()
            ->groupBy('assignment_date');

        return $this->success(request(), 'OK', [
            'post' => [
                'id'                => $post->id,
                'name'              => $post->name,
                'code'              => $post->code,
                'type'              => $post->type,
                'location'          => $post->location,
                'digital_address'   => $post->digital_address,
                'max_personnel'     => $post->max_personnel,
                'current_personnel' => $post->current_personnel,
                'working_hours'     => $this->getWorkingHoursInfo($post),
                'equipment'         => is_array($post->equipment) ? $post->equipment : [],
                'restrictions'      => is_array($post->restrictions) ? $post->restrictions : [],
                'requires_checkin'  => $post->requires_checkin,
                'is_active'         => $post->is_active,
                'is_trashed'        => $post->trashed(),
                'staffing_status'   => $this->staffingStatus($post->current_personnel, $post->max_personnel),
            ],
            'current_schedules' => $post->currentSchedules->map(fn($s) => [
                'id'            => $s->id,
                'security_user' => $s->securityUser ? [
                    'id'    => $s->securityUser->id,
                    'name'  => $s->securityUser->name,
                    'phone' => $s->securityUser->phone,
                ] : null,
                'shift'         => $s->shift?->name,
                'time_range'    => $s->shift?->getTimeRange(),
                'checkin_time'  => $s->checkin_time,
                'status'        => $s->status,
            ]),
            'upcoming_schedules' => $upcoming,
        ]);
    }

    public function getStaffingStatistics()
    {
        $posts = SecurityPost::where('is_active', true)->whereNull('deleted_at')
            ->withCount(['currentSchedules as current_personnel'])->get();

        $fully = $posts->filter(fn($p) => $p->current_personnel >= $p->max_personnel)->count();
        $under = $posts->filter(fn($p) => $p->current_personnel > 0 && $p->current_personnel < $p->max_personnel)->count();
        $un    = $posts->filter(fn($p) => $p->current_personnel === 0)->count();

        return $this->success(request(), 'OK', [
            'statistics' => [
                'total_posts'    => $posts->count(),
                'fully_staffed'  => $fully,
                'understaffed'   => $under,
                'unstaffed'      => $un,
                'staffing_rate'  => $posts->count() > 0 ? round(($fully / $posts->count()) * 100, 1) : 0,
            ],
            'posts' => $posts->map(fn($p) => [
                'id'                => $p->id,
                'name'              => $p->name,
                'code'              => $p->code,
                'type'              => $p->type,
                'current_personnel' => $p->current_personnel,
                'max_personnel'     => $p->max_personnel,
                'staffing_status'   => $this->staffingStatus($p->current_personnel, $p->max_personnel),
            ]),
        ]);
    }

    public function getUtilizationStatistics(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $start = Carbon::parse($request->start_date)->startOfDay();
        $end   = Carbon::parse($request->end_date)->startOfDay();
        $days  = $start->diffInDays($end) + 1;

        $utilization = [];
        foreach (SecurityPost::where('is_active', true)->whereNull('deleted_at')->get() as $post) {
            $totalSlots  = $post->max_personnel * $days;
            $filledSlots = $post->schedules()
                ->whereBetween('assignment_date', [$start->toDateString(), $end->toDateString()])
                ->whereIn('status', ['scheduled', 'active', 'completed'])
                ->count();

            $rate = $totalSlots > 0 ? ($filledSlots / $totalSlots) * 100 : 0;

            $utilization[] = [
                'post'             => ['id' => $post->id, 'name' => $post->name, 'code' => $post->code, 'type' => $post->type],
                'filled_slots'     => $filledSlots,
                'total_slots'      => $totalSlots,
                'utilization_rate' => round($rate, 2),
                'status'           => $rate >= 80 ? 'high' : ($rate >= 50 ? 'medium' : 'low'),
            ];
        }

        return $this->success(request(), 'OK', [
            'utilization' => $utilization,
            'period'      => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'days' => $days],
        ]);
    }

    public function getTrashStatistics()
    {
        $this->authorizeManage();
        return $this->success(request(), 'OK', ['statistics' => $this->trashStatsArray()]);
    }

    public function getQrCodeStatistics()
    {
        try {
            if (!class_exists(PostQrCode::class)) {
                return $this->failure(request(), 'QR Code functionality not available', 404);
            }

            $stats = $this->globalQrStats();

            return $this->success(request(), 'OK', ['stats' => $stats]);
        } catch (\Exception $e) {
            Log::error('Failed to get QR code statistics: ' . $e->getMessage());
            return $this->success(request(), 'Degraded response', [
                'stats' => [
                    'total' => 0, 'active' => 0, 'by_type' => [], 'by_post' => [],
                    'recent_activity' => 0, 'expiring_soon' => 0, 'expired' => 0, 'one_time_used' => 0,
                ],
            ]);
        }
    }

    public function getEligiblePersonnel(Request $request)
    {
        try {
            $search = trim($request->get('q', ''));
            $personnel = User::eligibleSecuritySupervisors()
                ->when($search, fn($q) => $q->where(fn($qq) =>
                    $qq->where('name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%")
                ))
                ->orderBy('name')->limit(20)->get(['id', 'name', 'email']);

            return $this->success(request(), 'OK', [
                'data' => $personnel->map(fn($u) => [
                    'id'    => $u->id,
                    'text'  => $u->name . ($u->email ? " ({$u->email})" : ''),
                    'name'  => $u->name,
                    'email' => $u->email,
                ]),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load eligible personnel: ' . $e->getMessage());
            return $this->failure(request(), 'Failed to load personnel.', 500);
        }
    }

    // ============================================================
    // EXPORT
    // ============================================================

    public function exportPosts(Request $request)
    {
        $this->authorizeManage();

        $includeSchedules  = $request->boolean('include_schedules');
        $includeEquipment  = $request->boolean('include_equipment', true);
        $includeStatistics = $request->boolean('include_statistics');
        $includeQrCodes    = $request->boolean('include_qr_codes');

        $query = SecurityPost::withCount(['schedules as total_assignments'])
            ->withCount(['currentSchedules as current_personnel']);

        if ($includeQrCodes) {
            $query->withCount(['qrCodes as qr_codes_count'])
                  ->withCount(['qrCodes as active_qr_codes_count' => fn($q) =>
                      $q->where('is_active', true)
                        ->where(fn($qq) => $qq->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                  ]);
        }

        if ($request->filled('type'))   $query->where('type', $request->type);
        if ($request->filled('status')) $query->where('is_active', $request->status === 'active');
        if ($request->boolean('include_trashed')) $query->withTrashed();

        $posts    = $query->orderBy('name')->get();
        $fileName = 'security_posts_' . date('Y-m-d_H-i-s') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function () use (
            $posts, $includeSchedules, $includeEquipment, $includeStatistics, $includeQrCodes
        ) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");

            $headers = [
                'ID','Name','Code','Type','Location','Digital Address','Max Personnel',
                'Current Personnel','Status','Deleted Status','Working Hours','Is Overnight',
                'Duration (Hours)','Requires Check-in',
            ];
            if ($includeEquipment) $headers[] = 'Equipment';
            $headers[] = 'Restrictions';
            $headers[] = 'Total Assignments';
            if ($includeSchedules) $headers[] = 'Current Schedules';
            if ($includeQrCodes)   { $headers[] = 'QR Codes'; $headers[] = 'Active QR Codes'; }
            if ($includeStatistics) $headers[] = 'Staffing Rate (%)';
            $headers = array_merge($headers, ['Created At','Updated At','Deleted At']);

            fputcsv($file, $headers);

            foreach ($posts as $post) {
                $wh = $this->getWorkingHoursInfo($post);

                $row = [
                    $post->id, $post->name, $post->code,
                    ucfirst(str_replace('_', ' ', $post->type)),
                    $post->location, $post->digital_address ?? 'N/A',
                    $post->max_personnel, $post->current_personnel,
                    $post->is_active ? 'Active' : 'Inactive',
                    $post->trashed() ? 'Deleted' : 'Active',
                    $wh['display'] ?? 'N/A',
                    $wh['is_overnight'] ? 'Yes' : 'No',
                    $wh['duration_hours'] ?? 0,
                    $post->requires_checkin ? 'Yes' : 'No',
                ];
                if ($includeEquipment) {
                    $row[] = is_array($post->equipment) ? implode('; ', $post->equipment) : 'N/A';
                }
                $row[] = is_array($post->restrictions) ? implode('; ', $post->restrictions) : 'N/A';
                $row[] = $post->total_assignments;
                if ($includeSchedules) {
                    $row[] = $post->current_personnel;
                }
                if ($includeQrCodes) {
                    $row[] = $post->qr_codes_count ?? 0;
                    $row[] = $post->active_qr_codes_count ?? 0;
                }
                if ($includeStatistics) {
                    $row[] = $post->max_personnel > 0
                        ? round(($post->current_personnel / $post->max_personnel) * 100, 1) : 0;
                }
                $row[] = optional($post->created_at)->format('Y-m-d H:i:s');
                $row[] = optional($post->updated_at)->format('Y-m-d H:i:s');
                $row[] = $post->deleted_at ? $post->deleted_at->format('Y-m-d H:i:s') : 'N/A';

                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ============================================================
    // ACCESS CONTROL
    // ============================================================

    private function canViewPost($user, $postId): bool
    {
        if (!$user) return false;
        return in_array($user->type, [
            self::USER_TYPE_SUPER_ADMIN,
            self::USER_TYPE_ADMIN,
            self::USER_TYPE_SECURITY_PERSONNEL,
        ]);
    }

    /**
     * Who may mutate posts on the security side:
     *  - Admins / Super Admins (defensive — routes are typically `:6`)
     *  - Security Personnel with an ACTIVE SecuritySupervisorAssignment
     *    (a.k.a. "Head of Security"). If your data model instead uses a
     *    `can_be_supervisor` flag, swap the predicate accordingly.
     */
    private function canManagePosts(): bool
    {
        $user = Auth::user();
        if (!$user) return false;

        if (in_array($user->type, [self::USER_TYPE_SUPER_ADMIN, self::USER_TYPE_ADMIN])) {
            return true;
        }

        if ($user->type === self::USER_TYPE_SECURITY_PERSONNEL) {
            return $this->isAreaSupervisor($user);
        }

        return false;
    }

    private function authorizeManage(): void
    {
        if (!$this->canManagePosts()) {
            abort(403, 'Only the Head of Security can manage posts.');
        }
    }

    /**
     * True if the caller has an active SecuritySupervisorAssignment or is
     * an admin/super-admin. Adapt the predicate if your eligibility flag
     * lives elsewhere (e.g. `$user->can_be_supervisor`).
     */
    private function isAreaSupervisor($user): bool
    {
        if (!$user) return false;
        if (in_array($user->type, [self::USER_TYPE_SUPER_ADMIN, self::USER_TYPE_ADMIN])) {
            return true;
        }
        if ($user->type !== self::USER_TYPE_SECURITY_PERSONNEL) {
            return false;
        }

        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
            ->exists();
    }

    // ============================================================
    // SHARED HELPERS
    // ============================================================

    private function getFullyStaffedCount(): int
    {
        return SecurityPost::where('is_active', true)->whereNull('deleted_at')
            ->withCount(['currentSchedules as current_personnel'])
            ->get()
            ->filter(fn($p) => $p->current_personnel >= $p->max_personnel)
            ->count();
    }

    private function getUnderstaffedCount(): int
    {
        return SecurityPost::where('is_active', true)->whereNull('deleted_at')
            ->withCount(['currentSchedules as current_personnel'])
            ->get()
            ->filter(fn($p) => $p->current_personnel > 0 && $p->current_personnel < $p->max_personnel)
            ->count();
    }

    private function globalQrStats(): array
    {
        $total  = PostQrCode::count();
        $active = PostQrCode::where('is_active', true)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();
        $byType = PostQrCode::select('code_type', DB::raw('count(*) as count'))
            ->groupBy('code_type')->pluck('count', 'code_type')->toArray();
        $byPost = PostQrCode::select('post_id', DB::raw('count(*) as total'))
            ->selectRaw('SUM(CASE WHEN is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 ELSE 0 END) as active')
            ->groupBy('post_id')->get()->keyBy('post_id')->toArray();
        $recent   = PostQrCode::where('created_at', '>=', now()->subDays(7))->count();
        $expiring = PostQrCode::where('is_active', true)->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(7)])->count();
        $expired  = PostQrCode::where('is_active', true)->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())->count();
        $oneTimeUsed = PostQrCode::where('code_type', 'one_time')->where('uses_count', '>', 0)->count();

        return [
            'total'           => $total,
            'active'          => $active,
            'by_type'         => $byType,
            'by_post'         => $byPost,
            'recent_activity' => $recent,
            'expiring_soon'   => $expiring,
            'expired'         => $expired,
            'one_time_used'   => $oneTimeUsed,
        ];
    }

    private function postTypeOptions(): array
    {
        return [
            'main_gate'        => 'Main Gate',
            'internal_gate'    => 'Internal Gate',
            'checkpoint'       => 'Checkpoint',
            'patrol_route'     => 'Patrol Route',
            'observation_post' => 'Observation Post',
            'control_room'     => 'Control Room',
            'access_point'     => 'Access Point',
        ];
    }

    private function defaultEquipment(): array
    {
        return ['Communication Radio','Flashlight','First Aid Kit','Visitor Logbook','Security Barrier','CCTV Monitor'];
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('type'))   $query->where('type', $request->type);
        if ($request->filled('status')) $query->where('is_active', $request->status === 'active');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('code', 'like', "%{$s}%")
                  ->orWhere('location', 'like', "%{$s}%");
            });
        }

        if ($request->filled('staffing_status')) {
            $sub = '(SELECT COUNT(*) FROM security_schedules WHERE security_post_id = security_posts.id
                     AND DATE(assignment_date) = CURDATE() AND status IN ("scheduled", "active"))';
            switch ($request->staffing_status) {
                case 'fully_staffed': $query->whereRaw("{$sub} >= max_personnel"); break;
                case 'understaffed':  $query->whereRaw("{$sub} < max_personnel AND {$sub} > 0"); break;
                case 'unstaffed':     $query->whereRaw("{$sub} = 0"); break;
            }
        }
    }

    private function validatePostPayload(Request $request, ?int $ignoreId = null)
    {
        $uniqueName = 'unique:security_posts,name' . ($ignoreId ? ",{$ignoreId}" : '');
        $uniqueCode = 'unique:security_posts,code' . ($ignoreId ? ",{$ignoreId}" : '');

        return Validator::make($request->all(), [
            'name'               => "required|string|max:255|{$uniqueName}",
            'code'               => "required|string|max:50|{$uniqueCode}",
            'type'               => 'required|string|in:main_gate,internal_gate,checkpoint,patrol_route,observation_post,control_room,access_point',
            'description'        => 'nullable|string|max:1000',
            'location'           => 'required|string|max:500',
            'digital_address'    => 'nullable|string|max:255',
            'equipment'          => 'nullable|array',
            'equipment.*'        => 'string|max:100',
            'max_personnel'      => 'required|integer|min:1|max:10',
            'is_active'          => 'boolean',
            'requires_checkin'   => 'boolean',
            'working_hours_start'=> 'nullable|date_format:H:i',
            'working_hours_end'  => 'nullable|date_format:H:i',
            'restrictions'       => 'nullable|array',
            'restrictions.*'     => 'string|max:255',
        ]);
    }

    private function workingHoursAreIdentical(Request $request): bool
    {
        if (!$request->filled('working_hours_start') || !$request->filled('working_hours_end')) {
            return false;
        }
        return Carbon::parse($request->working_hours_start)->format('H:i')
            === Carbon::parse($request->working_hours_end)->format('H:i');
    }

    private function buildPostPayload(Request $request, bool $isCreate): array
    {
        $data = $request->only([
            'name','code','type','description','location','digital_address','max_personnel',
        ]);

        $data['is_active']        = $isCreate ? $request->boolean('is_active', true) : $request->boolean('is_active');
        $data['requires_checkin'] = $isCreate ? $request->boolean('requires_checkin', true) : $request->boolean('requires_checkin');
        $data['equipment']        = $request->equipment ?? [];

        if ($request->filled('working_hours_start') && $request->filled('working_hours_end')) {
            $start = Carbon::parse($request->working_hours_start);
            $end   = Carbon::parse($request->working_hours_end);
            $isOvernight = $end->lessThan($start) || $end->equalTo($start);

            $data['working_hours'] = [
                'start'          => $request->working_hours_start,
                'end'            => $request->working_hours_end,
                'is_overnight'   => $isOvernight,
                'duration_hours' => $this->calculateDurationHours($start, $end),
                'display'        => $this->formatWorkingHoursDisplay(
                    $request->working_hours_start, $request->working_hours_end, $isOvernight
                ),
            ];
        } elseif (!$isCreate) {
            $data['working_hours'] = null;
        }

        $data['restrictions'] = $request->restrictions ?? [];
        return $data;
    }

    private function hasActiveSchedules(SecurityPost $post): bool
    {
        return $post->schedules()
            ->whereDate('assignment_date', '>=', now()->toDateString())
            ->whereIn('status', ['scheduled', 'active'])
            ->exists();
    }

    private function hasNameOrCodeConflict(SecurityPost $post): bool
    {
        return SecurityPost::where('name', $post->name)->where('id', '!=', $post->id)->exists()
            || SecurityPost::where('code', $post->code)->where('id', '!=', $post->id)->exists();
    }

    private function trashStatsArray(): array
    {
        return [
            'total_trashed'    => SecurityPost::onlyTrashed()->count(),
            'recently_deleted' => SecurityPost::onlyTrashed()->where('deleted_at', '>=', now()->subDays(7))->count(),
            'by_type'          => SecurityPost::onlyTrashed()
                ->select('type', DB::raw('count(*) as count'))
                ->groupBy('type')->pluck('count', 'type')->toArray(),
        ];
    }

    private function staffingStatus(int $current, int $max): string
    {
        if ($current >= $max) return 'fully_staffed';
        return $current > 0 ? 'understaffed' : 'unstaffed';
    }

    private function calculateDurationHours($start, $end)
    {
        if ($start->format('H:i') === $end->format('H:i')) return 0;
        if ($end->lessThan($start)) $end->addDay();
        return $start->diffInHours($end);
    }

    private function formatWorkingHoursDisplay($start, $end, $isOvernight): string
    {
        $s = Carbon::parse($start)->format('g:i A');
        $e = Carbon::parse($end)->format('g:i A');
        return $isOvernight ? "{$s} - {$e} (Overnight)" : "{$s} - {$e}";
    }

    private function getWorkingHoursInfo($post): array
    {
        $wh = is_array($post->working_hours) ? $post->working_hours : [];
        return [
            'start'           => $wh['start'] ?? null,
            'end'             => $wh['end'] ?? null,
            'is_overnight'    => $wh['is_overnight'] ?? false,
            'duration_hours'  => $wh['duration_hours'] ?? 0,
            'display'         => $wh['display'] ?? 'N/A',
            'formatted_start' => $wh['start'] ? Carbon::parse($wh['start'])->format('g:i A') : null,
            'formatted_end'   => $wh['end']   ? Carbon::parse($wh['end'])->format('g:i A')   : null,
        ];
    }

    // ============================================================
    // RESPONSE HELPERS
    // ============================================================

    private function success(Request $request, string $message, array $data = [], ?string $redirect = null)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return $this->cleanJsonResponse(array_merge(
                ['success' => true, 'message' => $message],
                $data,
                $redirect ? ['redirect_url' => $redirect] : []
            ));
        }
        return $redirect ? redirect($redirect)->with('success', $message)
                         : back()->with('success', $message);
    }

    private function failure(Request $request, string $message, int $status = 422,
                             ?string $redirect = null, $errors = null)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return $this->cleanJsonResponse(array_filter([
                'success' => false,
                'message' => $message,
                'errors'  => $errors,
            ]), $status);
        }
        return $redirect ? redirect($redirect)->with('error', $message)
                         : back()->with('error', $message);
    }

    private function cleanJsonResponse($data, $status = 200)
    {
        while (ob_get_level()) { ob_end_clean(); }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (substr($json, 0, 3) === "\xEF\xBB\xBF") { $json = substr($json, 3); }

        return response($json, $status)
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'no-cache, must-revalidate');
    }
}