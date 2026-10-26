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
    // ============================================================
    // LIST
    // ============================================================

    public function index(Request $request)
    {
        $query = SecurityPost::withCount(['schedules as total_assignments'])
            ->withCount(['currentSchedules as current_personnel'])
            ->orderBy('is_active', 'desc')
            ->orderBy('name');

        $this->applyIndexFilters($query, $request);

        if (!$request->boolean('show_trashed')) {
            $query->whereNull('deleted_at');
        }

        $posts = $query->paginate(20);

        $stats = [
            'total_posts'     => SecurityPost::whereNull('deleted_at')->count(),
            'active_posts'    => SecurityPost::whereNull('deleted_at')->where('is_active', true)->count(),
            'main_gates'      => SecurityPost::whereNull('deleted_at')->where('type', 'main_gate')->count(),
            'internal_gates'  => SecurityPost::whereNull('deleted_at')->where('type', 'internal_gate')->count(),
            'fully_staffed'   => $this->getFullyStaffedCount(),
            'understaffed'    => $this->getUnderstaffedCount(),
            'trashed_posts'   => SecurityPost::onlyTrashed()->count(),
        ];

        $staffingAlerts = SecurityPost::withCount(['currentSchedules as current_personnel'])
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get()
            ->filter(fn($post) => $post->current_personnel < $post->max_personnel)
            ->sortByDesc(fn($post) => $post->max_personnel - $post->current_personnel);

        return view('admin.security-posts.index', compact(
            'posts', 'stats', 'staffingAlerts', 'request'
        ));
    }

    public function trash(Request $request)
    {
        $query = SecurityPost::onlyTrashed()
            ->withCount(['schedules as total_assignments'])
            ->orderBy('deleted_at', 'desc');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('code', 'like', "%{$s}%")
                  ->orWhere('location', 'like', "%{$s}%")
                  ->orWhere('digital_address', 'like', "%{$s}%");
            });
        }

        $trashedPosts = $query->paginate(20);
        $trashStats   = $this->trashStatsArray();

        return view('admin.security-posts.trash', compact('trashedPosts', 'trashStats', 'request'));
    }

    // ============================================================
    // CREATE / STORE
    // ============================================================

    public function create()
    {
        return view('admin.security-posts.create', [
            'postTypes'        => $this->postTypeOptions(),
            'defaultEquipment' => $this->defaultEquipment(),
        ]);
    }

    public function store(Request $request)
    {
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
                'post_id'       => $post->id,
                'name'          => $post->name,
                'code'          => $post->code,
                'working_hours' => $post->working_hours ?? null,
                'created_by'    => Auth::id(),
            ]);

            DB::commit();
            return redirect()->route('admin.security-posts.show', $post)
                ->with('success', 'Security post created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create security post: ' . $e->getMessage());
            return back()->with('error', 'Failed to create security post. Please try again.')->withInput();
        }
    }

    // ============================================================
    // SHOW
    // ============================================================

    public function show(SecurityPost $securityPost)
    {
        if ($securityPost->trashed()) {
            return redirect()->route('admin.security-posts.trash')
                ->with('warning', 'This security post has been deleted.');
        }

        $securityPost->load([
            'schedules' => fn($q) => $q->whereDate('assignment_date', '>=', now()->subDays(7))
                                       ->orderBy('assignment_date', 'desc')
                                       ->limit(10),
            'schedules.securityUser',
            'schedules.shift',
        ]);

        $todaySchedules = $securityPost->currentSchedules()
            ->with(['securityUser', 'shift'])
            ->get();

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

        $equipment    = is_array($securityPost->equipment) ? $securityPost->equipment : [];
        $restrictions = is_array($securityPost->restrictions) ? $securityPost->restrictions : [];
        $workingHours = $this->getWorkingHoursInfo($securityPost);

        return view('admin.security-posts.show', compact(
            'securityPost', 'todaySchedules', 'upcomingSchedules',
            'stats', 'equipment', 'restrictions', 'workingHours'
        ));
    }

    // ============================================================
    // EDIT / UPDATE
    // ============================================================

    public function edit(SecurityPost $securityPost)
    {
        if ($securityPost->trashed()) {
            return redirect()->route('admin.security-posts.trash')
                ->with('warning', 'Cannot edit a deleted security post.');
        }

        $postTypes    = $this->postTypeOptions();
        $equipment    = is_array($securityPost->equipment) ? $securityPost->equipment : [];
        $restrictions = is_array($securityPost->restrictions) ? $securityPost->restrictions : [];

        $workingHoursStart = '';
        $workingHoursEnd   = '';
        $isOvernight       = false;
        if (is_array($securityPost->working_hours)) {
            $workingHoursStart = $securityPost->working_hours['start'] ?? '';
            $workingHoursEnd   = $securityPost->working_hours['end'] ?? '';
            $isOvernight       = $securityPost->working_hours['is_overnight'] ?? false;
        }

        return view('admin.security-posts.edit', compact(
            'securityPost', 'postTypes', 'equipment', 'restrictions',
            'workingHoursStart', 'workingHoursEnd', 'isOvernight'
        ));
    }

    public function update(Request $request, SecurityPost $securityPost)
    {
        if ($securityPost->trashed()) {
            return redirect()->route('admin.security-posts.trash')
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
            $updateData = $this->buildPostPayload($request, false);
            $securityPost->update($updateData);

            Log::info('Security post updated', [
                'post_id'       => $securityPost->id,
                'name'          => $securityPost->name,
                'working_hours' => $securityPost->working_hours ?? null,
                'updated_by'    => Auth::id(),
                'changes'       => $updateData,
            ]);

            DB::commit();
            return redirect()->route('admin.security-posts.show', $securityPost)
                ->with('success', 'Security post updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update security post: ' . $e->getMessage());
            return back()->with('error', 'Failed to update security post. Please try again.')->withInput();
        }
    }

    // ============================================================
    // DELETE / TRASH
    // ============================================================

    public function destroy(SecurityPost $securityPost)
    {
        // ✅ FIXED: route name (was `admin.security-posts.trash.index`, which is undefined)
        if ($securityPost->trashed()) {
            return redirect()->route('admin.security-posts.trash')
                ->with('warning', 'Security post is already deleted.');
        }

        if ($this->hasActiveSchedules($securityPost)) {
            return back()->with('error', 'Cannot delete security post with active or upcoming schedules.');
        }

        DB::beginTransaction();
        try {
            $postName = $securityPost->name;
            $securityPost->delete();

            Log::info('Security post soft deleted', [
                'post_id'    => $securityPost->id,
                'name'       => $postName,
                'deleted_by' => Auth::id(),
                'deleted_at' => now(),
            ]);

            DB::commit();
            return redirect()->route('admin.security-posts.index')
                ->with('success', 'Security post moved to trash successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete security post: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete security post. Please try again.');
        }
    }

    public function forceDelete($id)
    {
        $post = SecurityPost::withTrashed()->findOrFail($id);

        if (!$post->trashed()) {
            return redirect()->route('admin.security-posts.index')
                ->with('error', 'Post is not in trash.');
        }

        DB::beginTransaction();
        try {
            if ($post->schedules()->exists()) {
                $post->schedules()->delete();
            }

            $postName = $post->name;
            $post->forceDelete();

            Log::warning('Security post permanently deleted', [
                'post_id'    => $id,
                'name'       => $postName,
                'deleted_by' => Auth::id(),
            ]);

            DB::commit();
            return redirect()->route('admin.security-posts.trash')
                ->with('success', 'Security post permanently deleted.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to force delete security post: ' . $e->getMessage());
            return redirect()->route('admin.security-posts.trash')
                ->with('error', 'Failed to permanently delete security post.');
        }
    }

    public function restore($id)
    {
        $post = SecurityPost::withTrashed()->findOrFail($id);

        if (!$post->trashed()) {
            return redirect()->route('admin.security-posts.index')
                ->with('warning', 'Post is not deleted.');
        }

        DB::beginTransaction();
        try {
            if ($this->hasNameOrCodeConflict($post)) {
                DB::rollBack();
                return redirect()->route('admin.security-posts.trash')
                    ->with('error', 'Cannot restore post. Name or code already exists for another active post.');
            }

            $post->restore();
            $post->update(['is_active' => false]);

            Log::info('Security post restored from trash', [
                'post_id'     => $post->id,
                'name'        => $post->name,
                'restored_by' => Auth::id(),
            ]);

            DB::commit();
            return redirect()->route('admin.security-posts.show', $post)
                ->with('success', 'Security post restored successfully. It has been deactivated by default.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore security post: ' . $e->getMessage());
            return redirect()->route('admin.security-posts.trash')
                ->with('error', 'Failed to restore security post.');
        }
    }

    /**
     * ✅ FIXED: removed manual `header('Content-Type: …')`; JSON is
     * already correct from `response()->json()`.
     */
    public function bulkRestore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'post_ids'   => 'required|array',
            'post_ids.*' => 'exists:security_posts,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $posts = SecurityPost::withTrashed()
                ->whereIn('id', $request->post_ids)
                ->whereNotNull('deleted_at')
                ->get();

            $restored = 0;
            $failed   = [];

            foreach ($posts as $post) {
                if ($this->hasNameOrCodeConflict($post)) {
                    $failed[] = [
                        'id'     => $post->id,
                        'name'   => $post->name,
                        'reason' => 'Name/Code conflict',
                    ];
                    continue;
                }
                $post->restore();
                $post->update(['is_active' => false]);
                $restored++;

                Log::info('Security post restored from trash (bulk)', [
                    'post_id'     => $post->id,
                    'name'        => $post->name,
                    'restored_by' => Auth::id(),
                ]);
            }

            DB::commit();

            $message = "Restored {$restored} out of {$posts->count()} posts.";
            if (!empty($failed)) {
                $message .= " Failed to restore " . count($failed) . " posts due to conflicts.";
            }

            return response()->json([
                'success'        => true,
                'message'        => $message,
                'restored_count' => $restored,
                'failed_posts'   => $failed,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk restore posts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore posts.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function emptyTrash()
    {
        $trashedCount = SecurityPost::onlyTrashed()->count();
        if ($trashedCount === 0) {
            return redirect()->route('admin.security-posts.trash')
                ->with('info', 'Trash is already empty.');
        }

        DB::beginTransaction();
        try {
            foreach (SecurityPost::onlyTrashed()->get() as $post) {
                $post->schedules()->delete();
            }
            $deletedCount = SecurityPost::onlyTrashed()->forceDelete();

            Log::warning('Trash emptied - All security posts permanently deleted', [
                'count'      => $deletedCount,
                'deleted_by' => Auth::id(),
            ]);

            DB::commit();
            return redirect()->route('admin.security-posts.trash')
                ->with('success', "{$deletedCount} security posts permanently deleted from trash.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to empty trash: ' . $e->getMessage());
            return redirect()->route('admin.security-posts.trash')
                ->with('error', 'Failed to empty trash.');
        }
    }

    // ============================================================
    // TOGGLE ACTIVATION
    // ============================================================

    public function toggleActivation(SecurityPost $securityPost)
    {
        if ($securityPost->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot activate/deactivate a deleted post.',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $securityPost->update(['is_active' => !$securityPost->is_active]);

            Log::info('Security post activation toggled', [
                'post_id'    => $securityPost->id,
                'name'       => $securityPost->name,
                'new_status' => $securityPost->is_active ? 'active' : 'inactive',
                'updated_by' => Auth::id(),
            ]);

            DB::commit();
            return response()->json([
                'success'   => true,
                'message'   => $securityPost->is_active
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
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // API / AJAX
    // ============================================================

    public function getPosts(Request $request)
    {
        $query = SecurityPost::active()->orderBy('name');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%{$s}%")
                                      ->orWhere('code', 'like', "%{$s}%"));
        }

        $posts = $query->limit(50)->get();

        return response()->json([
            'success' => true,
            'posts'   => $posts->map(fn($p) => [
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

    public function getStaffingStatistics()
    {
        $posts = SecurityPost::active()
            ->withCount(['currentSchedules as current_personnel'])
            ->get();

        $fully = $posts->filter(fn($p) => $p->current_personnel >= $p->max_personnel)->count();
        $under = $posts->filter(fn($p) => $p->current_personnel > 0 && $p->current_personnel < $p->max_personnel)->count();
        $un    = $posts->filter(fn($p) => $p->current_personnel === 0)->count();

        return response()->json([
            'success'    => true,
            'statistics' => [
                'total_posts'   => $posts->count(),
                'fully_staffed' => $fully,
                'understaffed'  => $under,
                'unstaffed'     => $un,
                'staffing_rate' => $posts->count() > 0 ? round(($fully / $posts->count()) * 100, 1) : 0,
            ],
            'posts' => $posts->map(fn($p) => [
                'id'                => $p->id,
                'name'              => $p->name,
                'code'              => $p->code,
                'type'              => $p->type,
                'current_personnel' => $p->current_personnel,
                'max_personnel'     => $p->max_personnel,
                'staffing_status'   => $p->current_personnel >= $p->max_personnel
                    ? 'fully_staffed'
                    : ($p->current_personnel > 0 ? 'understaffed' : 'unstaffed'),
            ]),
        ]);
    }

    public function exportPosts(Request $request)
    {
        $query = SecurityPost::withCount(['schedules as total_assignments'])
            ->withCount(['currentSchedules as current_personnel']);

        if ($request->filled('type'))   $query->where('type', $request->type);
        if ($request->filled('status')) $query->where('is_active', $request->status === 'active');
        if ($request->boolean('include_trashed')) $query->withTrashed();

        $posts    = $query->orderBy('name')->get();
        $fileName = 'security_posts_' . date('Y-m-d_H-i-s') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function () use ($posts) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'ID','Name','Code','Type','Location','Digital Address','Max Personnel',
                'Current Personnel','Status','Deleted Status','Working Hours','Is Overnight',
                'Duration (Hours)','Requires Check-in','Equipment','Restrictions',
                'Total Assignments','Created At','Updated At','Deleted At',
            ]);

            foreach ($posts as $post) {
                $wh = $this->getWorkingHoursInfo($post);
                fputcsv($file, [
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
                    is_array($post->equipment) ? implode('; ', $post->equipment) : 'N/A',
                    is_array($post->restrictions) ? implode('; ', $post->restrictions) : 'N/A',
                    $post->total_assignments,
                    optional($post->created_at)->format('Y-m-d H:i:s'),
                    optional($post->updated_at)->format('Y-m-d H:i:s'),
                    $post->deleted_at ? $post->deleted_at->format('Y-m-d H:i:s') : 'N/A',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * ✅ FIXED: null-safe securityUser / shift dereferences.
     */
    public function getPostDetails(SecurityPost $securityPost)
    {
        $post = $securityPost->load(['currentSchedules.securityUser']);

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
                'staffing_status'   => $post->isFullyStaffed()
                    ? 'fully_staffed'
                    : ($post->current_personnel > 0 ? 'understaffed' : 'unstaffed'),
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
            'upcoming_schedules' => $upcomingSchedules,
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'post_ids'   => 'required|array',
            'post_ids.*' => 'exists:security_posts,id',
            'action'     => 'required|in:activate,deactivate,update_type,trash,restore,force_delete',
            'data'       => 'nullable|array',
            'data.type'  => 'nullable|in:main_gate,internal_gate,checkpoint,patrol_route,observation_post,control_room,access_point',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
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
                    $failed[] = ['id' => $post->id, 'name' => $post->name, 'reason' => 'Error: ' . $e->getMessage()];
                }
            }

            DB::commit();

            $message = "Processed {$updated} out of {$posts->count()} posts.";
            if (!empty($failed)) {
                $message .= ' Failed to process ' . count($failed) . ' posts.';
            }

            return response()->json([
                'success'       => true,
                'message'       => $message,
                'updated_count' => $updated,
                'failed_posts'  => $failed,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk update posts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process posts.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ FIXED: inclusive day count + status filter on filled slots.
     * (Original divided `(end - start)` by 86400, giving 0 for one-day
     *  ranges, and counted cancelled schedules.)
     */
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
        foreach (SecurityPost::active()->whereNull('deleted_at')->get() as $post) {
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

        return response()->json([
            'success'     => true,
            'utilization' => $utilization,
            'period'      => [
                'start' => $start->toDateString(),
                'end'   => $end->toDateString(),
                'days'  => $days,
            ],
        ]);
    }

    // ============================================================
    // TRASH / QR STATS
    // ============================================================

    private function getFullyStaffedCount()
    {
        return SecurityPost::active()->whereNull('deleted_at')
            ->withCount(['currentSchedules as current_personnel'])
            ->get()
            ->filter(fn($p) => $p->current_personnel >= $p->max_personnel)
            ->count();
    }

    private function getUnderstaffedCount()
    {
        return SecurityPost::active()->whereNull('deleted_at')
            ->withCount(['currentSchedules as current_personnel'])
            ->get()
            ->filter(fn($p) => $p->current_personnel > 0 && $p->current_personnel < $p->max_personnel)
            ->count();
    }

    public function getTrashStatistics()
    {
        $trashStats = [
            'total'   => SecurityPost::onlyTrashed()->count(),
            'recent'  => SecurityPost::onlyTrashed()->where('deleted_at', '>=', now()->subDays(7))->count(),
            'old'     => SecurityPost::onlyTrashed()->where('deleted_at', '<', now()->subDays(30))->count(),
            'by_type' => SecurityPost::onlyTrashed()
                ->select('type', DB::raw('count(*) as count'))
                ->groupBy('type')->pluck('count', 'type')->toArray(),
        ];

        return response()->json([
            'success'    => true,
            'statistics' => $trashStats,
        ]);
    }

    public function getQrCodeStatistics()
    {
        try {
            if (!class_exists('App\Models\PostQrCode')) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR Code functionality not available',
                ], 404);
            }

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

            return response()->json([
                'success' => true,
                'stats'   => [
                    'total'           => $total,
                    'active'          => $active,
                    'by_type'         => $byType,
                    'by_post'         => $byPost,
                    'recent_activity' => $recent,
                    'expiring_soon'   => $expiring,
                    'expired'         => $expired,
                    'one_time_used'   => $oneTimeUsed,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get QR code statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load QR code statistics',
                'stats'   => [
                    'total' => 0, 'active' => 0, 'by_type' => [], 'by_post' => [],
                    'recent_activity' => 0, 'expiring_soon' => 0, 'expired' => 0, 'one_time_used' => 0,
                ],
            ], 500);
        }
    }

    // ============================================================
    // SHARED HELPERS
    // ============================================================

    private function applyIndexFilters($query, Request $request): void
    {
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('code', 'like', "%{$s}%")
                  ->orWhere('location', 'like', "%{$s}%")
                  ->orWhere('digital_address', 'like', "%{$s}%");
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

        // Preserved asymmetry: create defaults is_active to true; update is strict.
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
}