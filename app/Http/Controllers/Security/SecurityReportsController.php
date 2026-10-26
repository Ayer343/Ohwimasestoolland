<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\SecurityReport;
use App\Models\SecurityPost;
use App\Models\SecuritySchedule;
use App\Models\User;
use App\Services\MultiChannelInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SecurityReportsController extends Controller
{
    public function __construct(
        protected MultiChannelInvitationService $multiChannelService
    ) {}

    // ============================================================
    // AUTHORIZATION + RESPONSE HELPERS
    // ============================================================

    private function canManageReports(): bool
    {
        $user = auth()->user();
        return $user && Gate::forUser($user)->allows('manage-security-schedules');
    }

    private function canVerifyOrClose(): bool
    {
        $user = auth()->user();
        return $user && (
            $user->isSuperAdmin()
            || $user->isAdmin()
            || (method_exists($user, 'isSecuritySupervisor') && $user->isSecuritySupervisor())
        );
    }

    private function authorizeManage(): void
    {
        if (!$this->canManageReports()) {
            abort(403, 'You do not have permission to manage security reports.');
        }
    }

    private function success(Request $request, string $message, array $data = [], ?string $redirect = null)
    {
        if ($request->expectsJson()) {
            return response()->json(array_merge(['success' => true, 'message' => $message], $data));
        }
        $r = $redirect ? redirect($redirect) : back();
        return $r->with('success', $message);
    }

    private function failure(Request $request, string $message, int $status = 422, $errors = null)
    {
        if ($request->expectsJson()) {
            return response()->json(
                array_merge(['success' => false, 'message' => $message], $errors ? ['errors' => $errors] : []),
                $status
            );
        }
        $r = back();
        if ($errors) {
            $r->withErrors($errors);
        }
        return $r->with('error', $message)->withInput();
    }

    // ============================================================
    // INDEX
    // ============================================================

    public function index(Request $request)
    {
        $this->authorizeManage();

        $currentUser = auth()->user();

        $query = SecurityReport::with(['securityPost', 'reporter', 'assignee', 'verifier'])
            ->orderByDesc('created_at');

        // ---- Filters ----
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%")
                  ->orWhere('report_number', 'like', "%{$s}%")
                  ->orWhere('location', 'like', "%{$s}%")
                  ->orWhere('digital_address', 'like', "%{$s}%");
            });
        }

        foreach ([
            'report_type' => 'report_type',
            'category'    => 'category',
            'priority'    => 'priority',
            'status'      => 'status',
        ] as $param => $column) {
            if ($request->filled($param) && $request->$param !== 'all') {
                $query->where($column, $request->$param);
            }
        }

        if ($request->filled('security_post_id') && $request->security_post_id !== 'all') {
            $query->where('security_post_id', $request->security_post_id);
        }
        if ($request->filled('reported_by') && $request->reported_by !== 'all') {
            $query->where('reported_by', $request->reported_by);
        }
        if ($request->filled('assigned_to') && $request->assigned_to !== 'all') {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->filled('date_range') && $request->date_range !== 'all') {
            $range = explode(' to ', $request->date_range);
            if (count($range) === 2) {
                $query->whereDate('incident_date', '>=', $range[0])
                      ->whereDate('incident_date', '<=', $range[1]);
            } elseif (count($range) === 1) {
                $query->whereDate('incident_date', $range[0]);
            }
        }

        if ($request->filled('requires_followup') && $request->requires_followup === '1') {
            $query->where('requires_followup', true)
                  ->where(fn($q) => $q->whereNull('followup_date')
                                       ->orWhere('followup_date', '<=', now()->addDays(3)));
        }

        if ($request->filled('is_confidential') && $request->is_confidential === '1') {
            $query->where('is_confidential', true);
        }

        // Confidentiality gate — supervisors see only their own / assigned / involved
        if (!$this->canVerifyOrClose()) {
            $query->where(function ($q) use ($currentUser) {
                $q->where('is_confidential', false)
                  ->orWhere('reported_by', $currentUser->id)
                  ->orWhere('assigned_to', $currentUser->id)
                  ->orWhereJsonContains('personnel_involved', $currentUser->id);
            });
        }

        $perPage = (int) $request->get('per_page', 20);
        $reports = $query->paginate($perPage)->withQueryString();

        // ---- Sidebar data ----
        $stats = $this->dashboardStatisticsFor($request);

        $reportTypes = SecurityReport::getReportTypes();
        $categories  = SecurityReport::getCategories();
        $priorities  = SecurityReport::getPriorities();
        $statuses    = SecurityReport::getStatuses();

        $securityPosts = SecurityPost::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);

        $criticalReports = SecurityReport::whereIn('priority', ['high', 'critical'])
            ->whereIn('status', ['pending', 'under_investigation'])
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        $overdueReports = SecurityReport::where('status', 'pending')
            ->where('created_at', '<', now()->subDays(1))
            ->orderBy('created_at')
            ->take(5)
            ->get();

        return view('security.security-reports.index', compact(
            'reports',
            'stats',
            'reportTypes',
            'categories',
            'priorities',
            'statuses',
            'securityPosts',
            'securityPersonnel',
            'criticalReports',
            'overdueReports'
        ));
    }

    // ============================================================
    // CREATE / STORE
    // ============================================================

    public function create()
    {
        $this->authorizeManage();

        $currentUser = auth()->user();

        $securityPosts = SecurityPost::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'location']);

        $securitySchedules = SecuritySchedule::where('status', 'scheduled')
            ->with(['securityPost', 'securityPersonnel'])
            ->orderBy('start_time')
            ->get();

        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);

        return view('security.security-reports.create', [
            'securityPosts'      => $securityPosts,
            'securitySchedules'  => $securitySchedules,
            'securityPersonnel'  => $securityPersonnel,
            'reportTypes'        => SecurityReport::getReportTypes(),
            'categories'         => SecurityReport::getCategories(),
            'priorities'         => SecurityReport::getPriorities(),
            'defaultReporter'    => $currentUser->id,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManage();

        $currentUser = auth()->user();

        $validator = Validator::make($request->all(), [
            'title'               => 'required|string|max:255',
            'description'         => 'required|string|min:10',
            'report_type'         => 'required|string|in:' . implode(',', array_keys(SecurityReport::getReportTypes())),
            'category'            => 'required|string|in:' . implode(',', array_keys(SecurityReport::getCategories())),
            'priority'            => 'required|string|in:' . implode(',', array_keys(SecurityReport::getPriorities())),

            'security_post_id'     => 'nullable|exists:security_posts,id',
            'security_schedule_id' => 'nullable|exists:security_schedules,id',
            'reported_by'          => 'required|exists:users,id',
            'assigned_to'          => 'nullable|exists:users,id',

            'incident_date'   => 'nullable|date',
            'location'        => 'required|string|max:255',
            'digital_address' => 'nullable|string|max:255',

            'personnel_involved'   => 'nullable|array',
            'personnel_involved.*' => 'exists:users,id',
            'witnesses'            => 'nullable|array',
            'witnesses.*.name'     => 'nullable|string|max:255',
            'witnesses.*.contact'  => 'nullable|string|max:255',

            'impact_assessment' => 'nullable|string|max:1000',
            'immediate_actions' => 'nullable|string|max:1000',
            'recommendations'   => 'nullable|string|max:1000',

            'evidence_photos'    => 'nullable|array',
            'evidence_photos.*'  => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'evidence_documents' => 'nullable|array',
            'evidence_documents.*' => 'file|mimes:pdf,doc,docx,txt|max:10240',

            'requires_followup' => 'sometimes|boolean',
            'followup_date'     => 'nullable|date|after:today',

            'is_confidential'     => 'sometimes|boolean',
            'notify_stakeholders' => 'sometimes|boolean',

            'severity_level'      => 'nullable|integer|min:1|max:10',
            'estimated_damage'    => 'nullable|numeric|min:0',
            'personnel_injured'   => 'nullable|integer|min:0',
            'personnel_deceased'  => 'nullable|integer|min:0',
            'civilians_affected'  => 'nullable|integer|min:0',
        ]);

        $validator->after(function ($v) use ($request) {
            if (in_array($request->priority, ['high', 'critical'], true) && empty($request->immediate_actions)) {
                $v->errors()->add('immediate_actions', 'Please describe immediate actions for high/critical priority reports.');
            }
            if ($request->category === 'theft' && empty($request->estimated_damage)) {
                $v->errors()->add('estimated_damage', 'Please provide estimated damage for theft reports.');
            }
            if (in_array($request->category, ['medical', 'fire'], true) && $request->personnel_injured === null) {
                $v->errors()->add('personnel_injured', 'Please specify the number of personnel injured (0 if none).');
            }
        });

        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $report = SecurityReport::create([
                'title'                => $request->title,
                'description'          => $request->description,
                'report_type'          => $request->report_type,
                'category'             => $request->category,
                'priority'             => $request->priority,
                'status'               => SecurityReport::STATUS_PENDING,

                'security_post_id'     => $request->security_post_id,
                'security_schedule_id' => $request->security_schedule_id,
                'reported_by'          => $request->reported_by,
                'assigned_to'          => $request->assigned_to,

                'incident_date'    => $request->incident_date ? Carbon::parse($request->incident_date) : now(),
                'report_date'      => now(),
                'location'         => $request->location,
                'digital_address'  => $request->digital_address,

                'personnel_involved' => $request->personnel_involved,
                'witnesses'          => $request->witnesses,

                'impact_assessment' => $request->impact_assessment,
                'immediate_actions' => $request->immediate_actions,
                'recommendations'   => $request->recommendations,

                'requires_followup' => $request->boolean('requires_followup'),
                'followup_date'     => $request->followup_date ? Carbon::parse($request->followup_date) : null,

                'is_confidential'     => $request->boolean('is_confidential'),
                'notify_stakeholders' => $request->boolean('notify_stakeholders'),

                'severity_level'     => $request->severity_level,
                'estimated_damage'   => $request->estimated_damage,
                'personnel_injured'  => $request->personnel_injured ?? 0,
                'personnel_deceased' => $request->personnel_deceased ?? 0,
                'civilians_affected' => $request->civilians_affected ?? 0,

                'evidence_photos'    => $this->uploadFiles($request, 'evidence_photos', 'security-reports/photos'),
                'evidence_documents' => $this->uploadFiles($request, 'evidence_documents', 'security-reports/documents'),

                'metadata' => [
                    'created_by'      => $currentUser->id,
                    'created_by_role' => $currentUser->isSuperAdmin() ? 'super_admin' : ($currentUser->isAdmin() ? 'admin' : 'supervisor'),
                    'ip_address'      => $request->ip(),
                    'user_agent'      => $request->userAgent(),
                ],
            ]);

            if ($report->assigned_to) {
                $report->update(['status' => SecurityReport::STATUS_UNDER_INVESTIGATION]);
            }

            if ($request->boolean('notify_stakeholders')) {
                $this->sendReportNotifications($report, $currentUser);
            }

            DB::commit();

            Log::info('Security report created (supervisor)', [
                'report_id' => $report->id,
                'report_number' => $report->report_number,
                'priority' => $report->priority,
                'created_by' => $currentUser->id,
            ]);

            return $this->success(
                $request,
                'Security report created successfully!',
                ['report_id' => $report->id, 'report_number' => $report->report_number],
                route('security.security-reports.show', $report->id)
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create security report (supervisor): ' . $e->getMessage());
            return $this->failure($request, 'Failed to create security report: ' . $e->getMessage(), 500);
        }
    }

    // ============================================================
    // SHOW / EDIT / UPDATE
    // ============================================================

    public function show($id)
    {
        $this->authorizeManage();
        $currentUser = auth()->user();

        $report = SecurityReport::with([
            'securityPost', 'securitySchedule', 'reporter',
            'assignee', 'verifier', 'closer', 'involvedPersonnel',
        ])->findOrFail($id);

        if (!$report->canView($currentUser)) {
            abort(403, 'You are not authorized to view this confidential report.');
        }

        $relatedReports = SecurityReport::where('id', '!=', $report->id)
            ->where(function ($q) use ($report) {
                $q->where('security_post_id', $report->security_post_id)
                  ->orWhere('location', 'like', '%' . $report->location . '%')
                  ->orWhere('category', $report->category);
            })
            ->when($report->incident_date, fn($q) => $q->whereDate('incident_date', $report->incident_date->toDateString()))
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        $activities = $this->getReportActivities($report);
        $contextStats = $this->getContextStatistics($report);

        $requiresFollowupAlert = $report->requires_followup
            && (!$report->followup_date || $report->followup_date <= now()->addDay());

        return view('security.security-reports.show', compact(
            'report', 'relatedReports', 'activities', 'contextStats', 'requiresFollowupAlert'
        ));
    }

    public function edit($id)
    {
        $this->authorizeManage();
        $currentUser = auth()->user();

        $report = SecurityReport::findOrFail($id);

        if (!$report->canView($currentUser)) {
            abort(403, 'You are not authorized to edit this report.');
        }

        if (!in_array($report->status, [SecurityReport::STATUS_PENDING, SecurityReport::STATUS_UNDER_INVESTIGATION], true)) {
            return back()->with('warning', 'Cannot edit reports that are already resolved or closed.');
        }

        return view('security.security-reports.edit', [
            'report'             => $report,
            'securityPosts'      => SecurityPost::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'location']),
            'securitySchedules'  => SecuritySchedule::where('status', 'scheduled')
                                        ->with(['securityPost', 'securityPersonnel'])
                                        ->orderBy('start_time')->get(),
            'securityPersonnel'  => User::where('type', User::TYPE_SECURITY_PERSONNEL)
                                        ->where('status', User::STATUS_ACTIVE)
                                        ->orderBy('name')->get(['id', 'name', 'email', 'phone']),
            'reportTypes'        => SecurityReport::getReportTypes(),
            'categories'         => SecurityReport::getCategories(),
            'priorities'         => SecurityReport::getPriorities(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeManage();
        $currentUser = auth()->user();

        $report = SecurityReport::findOrFail($id);

        if (!$report->canView($currentUser)) {
            abort(403, 'You are not authorized to update this report.');
        }

        if (!in_array($report->status, [SecurityReport::STATUS_PENDING, SecurityReport::STATUS_UNDER_INVESTIGATION], true)) {
            return back()->with('warning', 'Cannot update reports that are already resolved or closed.');
        }

        $validator = Validator::make($request->all(), [
            'title'        => 'required|string|max:255',
            'description'  => 'required|string|min:10',
            'report_type'  => 'required|string|in:' . implode(',', array_keys(SecurityReport::getReportTypes())),
            'category'     => 'required|string|in:' . implode(',', array_keys(SecurityReport::getCategories())),
            'priority'     => 'required|string|in:' . implode(',', array_keys(SecurityReport::getPriorities())),

            'security_post_id'     => 'nullable|exists:security_posts,id',
            'security_schedule_id' => 'nullable|exists:security_schedules,id',
            'assigned_to'          => 'nullable|exists:users,id',

            'incident_date'   => 'nullable|date',
            'location'        => 'required|string|max:255',
            'digital_address' => 'nullable|string|max:255',

            'personnel_involved'   => 'nullable|array',
            'personnel_involved.*' => 'exists:users,id',
            'witnesses'            => 'nullable|array',
            'witnesses.*.name'     => 'nullable|string|max:255',
            'witnesses.*.contact'  => 'nullable|string|max:255',

            'impact_assessment' => 'nullable|string|max:1000',
            'immediate_actions' => 'nullable|string|max:1000',
            'recommendations'   => 'nullable|string|max:1000',

            'new_evidence_photos'    => 'nullable|array',
            'new_evidence_photos.*'  => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'new_evidence_documents' => 'nullable|array',
            'new_evidence_documents.*' => 'file|mimes:pdf,doc,docx,txt|max:10240',

            'requires_followup' => 'sometimes|boolean',
            'followup_date'     => 'nullable|date|after:today',

            'is_confidential'     => 'sometimes|boolean',
            'notify_stakeholders' => 'sometimes|boolean',

            'severity_level'      => 'nullable|integer|min:1|max:10',
            'estimated_damage'    => 'nullable|numeric|min:0',
            'personnel_injured'   => 'nullable|integer|min:0',
            'personnel_deceased'  => 'nullable|integer|min:0',
            'civilians_affected'  => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $updateData = [
                'title'       => $request->title,
                'description' => $request->description,
                'report_type' => $request->report_type,
                'category'    => $request->category,
                'priority'    => $request->priority,

                'security_post_id'     => $request->security_post_id,
                'security_schedule_id' => $request->security_schedule_id,
                'assigned_to'          => $request->assigned_to,

                'incident_date'   => $request->incident_date ? Carbon::parse($request->incident_date) : $report->incident_date,
                'location'        => $request->location,
                'digital_address' => $request->digital_address,

                'personnel_involved' => $request->personnel_involved,
                'witnesses'          => $request->witnesses,

                'impact_assessment' => $request->impact_assessment,
                'immediate_actions' => $request->immediate_actions,
                'recommendations'   => $request->recommendations,

                'requires_followup' => $request->boolean('requires_followup'),
                'followup_date'     => $request->followup_date ? Carbon::parse($request->followup_date) : null,

                'is_confidential' => $request->boolean('is_confidential'),

                'severity_level'     => $request->severity_level,
                'estimated_damage'   => $request->estimated_damage,
                'personnel_injured'  => $request->personnel_injured ?? $report->personnel_injured,
                'personnel_deceased' => $request->personnel_deceased ?? $report->personnel_deceased,
                'civilians_affected' => $request->civilians_affected ?? $report->civilians_affected,
            ];

            if ($request->assigned_to && !$report->assigned_to) {
                $updateData['status'] = SecurityReport::STATUS_UNDER_INVESTIGATION;
            }

            // Append new evidence (never replace)
            $existingPhotos = $report->evidence_photos ?? [];
            $newPhotos = $this->uploadFiles($request, 'new_evidence_photos', 'security-reports/photos');
            if (!empty($newPhotos)) {
                $updateData['evidence_photos'] = array_merge($existingPhotos, $newPhotos);
            }

            $existingDocs = $report->evidence_documents ?? [];
            $newDocs = $this->uploadFiles($request, 'new_evidence_documents', 'security-reports/documents');
            if (!empty($newDocs)) {
                $updateData['evidence_documents'] = array_merge($existingDocs, $newDocs);
            }

            $metadata = $report->metadata ?? [];
            $metadata['updated_by'] = $currentUser->id;
            $metadata['updated_at'] = now()->toDateTimeString();
            $updateData['metadata'] = $metadata;

            $report->update($updateData);

            if ($request->boolean('notify_stakeholders')) {
                $this->sendReportUpdateNotifications($report, $currentUser);
            }

            DB::commit();

            Log::info('Security report updated (supervisor)', [
                'report_id' => $report->id,
                'updated_by' => $currentUser->id,
                'fields' => array_keys($updateData),
            ]);

            return $this->success(
                $request,
                'Security report updated successfully!',
                ['report_id' => $report->id],
                route('security.security-reports.show', $report->id)
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update security report (supervisor): ' . $e->getMessage());
            return $this->failure($request, 'Failed to update security report: ' . $e->getMessage(), 500);
        }
    }

    // ============================================================
    // DESTROY
    // ============================================================

    public function destroy(Request $request, $id)
    {
        $this->authorizeManage();

        if (!$this->canVerifyOrClose()) {
            return $this->failure($request, 'Unauthorized action.', 403);
        }

        $report = SecurityReport::findOrFail($id);

        if ($report->status !== SecurityReport::STATUS_PENDING) {
            return $this->failure($request, 'Only pending reports can be deleted.', 422);
        }

        DB::beginTransaction();
        try {
            $this->deleteReportFiles($report);
            $report->delete();

            DB::commit();

            Log::warning('Security report deleted (supervisor)', [
                'report_id' => $id,
                'deleted_by' => auth()->id(),
            ]);

            return $this->success(
                $request,
                'Security report deleted successfully!',
                [],
                route('security.security-reports.index')
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete security report: ' . $e->getMessage());
            return $this->failure($request, 'Failed to delete security report: ' . $e->getMessage(), 500);
        }
    }

    // ============================================================
    // WORKFLOW ACTIONS
    // ============================================================

    public function assign(Request $request, $id)
    {
        $this->authorizeManage();
        if (!$this->canVerifyOrClose()) {
            return $this->failure($request, 'Unauthorized action.', 403);
        }

        $validator = Validator::make($request->all(), [
            'assigned_to'     => 'required|exists:users,id',
            'assignment_notes' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, $validator->errors());
        }

        $report = SecurityReport::findOrFail($id);
        $currentUser = auth()->user();

        DB::beginTransaction();
        try {
            $oldAssignee = $report->assignee;
            $report->assignTo($request->assigned_to);

            $meta = $report->metadata ?? [];
            $meta['assignment_history'][] = [
                'from'        => $oldAssignee?->id,
                'to'          => $request->assigned_to,
                'assigned_by' => $currentUser->id,
                'assigned_at' => now()->toDateTimeString(),
                'notes'       => $request->assignment_notes,
            ];
            $report->update(['metadata' => $meta]);

            $this->sendAssignmentNotification($report, $currentUser);

            DB::commit();

            return $this->success($request, 'Report assigned successfully!', [
                'report_id' => $report->id,
                'assignee_name' => $report->fresh('assignee')->assignee?->name,
                'status_label'  => $report->fresh()->status_label,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to assign report: ' . $e->getMessage());
            return $this->failure($request, 'Failed to assign report: ' . $e->getMessage(), 500);
        }
    }

    public function verify(Request $request, $id)
    {
        $this->authorizeManage();
        if (!$this->canVerifyOrClose()) {
            return $this->failure($request, 'Unauthorized action.', 403);
        }

        $validator = Validator::make($request->all(), [
            'verification_notes' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, $validator->errors());
        }

        $report = SecurityReport::findOrFail($id);
        $currentUser = auth()->user();

        if (in_array($report->status, [SecurityReport::STATUS_CLOSED, SecurityReport::STATUS_CANCELLED], true)) {
            return $this->failure($request, 'Cannot verify a closed or cancelled report.', 422);
        }

        DB::beginTransaction();
        try {
            $report->markAsVerified($currentUser->id, $request->verification_notes);
            DB::commit();

            return $this->success($request, 'Report verified successfully!', [
                'report_id'     => $report->id,
                'verified_at'   => $report->fresh()->verified_at?->format('M j, Y g:i A'),
                'verifier_name' => $report->fresh('verifier')->verifier?->name,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to verify report: ' . $e->getMessage());
            return $this->failure($request, 'Failed to verify report: ' . $e->getMessage(), 500);
        }
    }

    public function resolve(Request $request, $id)
    {
        $this->authorizeManage();
        $report = SecurityReport::findOrFail($id);
        $currentUser = auth()->user();

        if (!$report->canView($currentUser) && $report->assigned_to !== $currentUser->id) {
            return $this->failure($request, 'Unauthorized action.', 403);
        }

        $validator = Validator::make($request->all(), [
            'resolution_details' => 'required|string|min:10|max:2000',
            'mark_as_closed'     => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, $validator->errors());
        }

        if (in_array($report->status, [SecurityReport::STATUS_CLOSED, SecurityReport::STATUS_CANCELLED], true)) {
            return $this->failure($request, 'Cannot resolve a closed or cancelled report.', 422);
        }

        DB::beginTransaction();
        try {
            $report->markAsResolved($request->resolution_details);

            if ($request->boolean('mark_as_closed') && $this->canVerifyOrClose()) {
                $report->markAsClosed($currentUser->id, 'Automatically closed after resolution');
            }

            DB::commit();

            return $this->success($request, 'Report resolved successfully!', [
                'report_id'      => $report->id,
                'status_label'   => $report->fresh()->status_label,
                'resolved_at'    => $report->fresh()->resolved_at?->format('M j, Y g:i A'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to resolve report: ' . $e->getMessage());
            return $this->failure($request, 'Failed to resolve report: ' . $e->getMessage(), 500);
        }
    }

    public function close(Request $request, $id)
    {
        $this->authorizeManage();
        if (!$this->canVerifyOrClose()) {
            return $this->failure($request, 'Unauthorized action.', 403);
        }

        $validator = Validator::make($request->all(), [
            'closure_notes' => 'required|string|min:10|max:1000',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, $validator->errors());
        }

        $report = SecurityReport::findOrFail($id);
        $currentUser = auth()->user();

        if ($report->status === SecurityReport::STATUS_CLOSED) {
            return $this->failure($request, 'Report is already closed.', 422);
        }
        if ($report->status === SecurityReport::STATUS_CANCELLED) {
            return $this->failure($request, 'Cannot close a cancelled report.', 422);
        }

        DB::beginTransaction();
        try {
            $report->markAsClosed($currentUser->id, $request->closure_notes);
            DB::commit();

            return $this->success($request, 'Report closed successfully!', [
                'report_id'    => $report->id,
                'status_label' => $report->fresh()->status_label,
                'closed_at'    => $report->fresh()->closed_at?->format('M j, Y g:i A'),
                'closer_name'  => $report->fresh('closer')->closer?->name,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to close report: ' . $e->getMessage());
            return $this->failure($request, 'Failed to close report: ' . $e->getMessage(), 500);
        }
    }

    public function cancel(Request $request, $id)
    {
        $this->authorizeManage();
        $currentUser = auth()->user();

        // Only super admin can cancel — matches admin behavior
        if (!$currentUser->isSuperAdmin()) {
            return $this->failure($request, 'Unauthorized action. Only super admins can cancel reports.', 403);
        }

        $validator = Validator::make($request->all(), [
            'cancellation_reason' => 'required|string|min:10|max:1000',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, $validator->errors());
        }

        $report = SecurityReport::findOrFail($id);

        if ($report->status === SecurityReport::STATUS_CLOSED) {
            return $this->failure($request, 'Cannot cancel a closed report.', 422);
        }
        if ($report->status === SecurityReport::STATUS_CANCELLED) {
            return $this->failure($request, 'Report is already cancelled.', 422);
        }

        DB::beginTransaction();
        try {
            $report->update([
                'status' => SecurityReport::STATUS_CANCELLED,
                'metadata' => array_merge($report->metadata ?? [], [
                    'cancelled_by'        => $currentUser->id,
                    'cancelled_at'        => now()->toDateTimeString(),
                    'cancellation_reason' => $request->cancellation_reason,
                ]),
            ]);
            DB::commit();

            return $this->success($request, 'Report cancelled successfully!', [
                'report_id'    => $report->id,
                'status_label' => $report->fresh()->status_label,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to cancel report: ' . $e->getMessage());
            return $this->failure($request, 'Failed to cancel report: ' . $e->getMessage(), 500);
        }
    }

    // ============================================================
    // EXPORT & STATS
    // ============================================================

    public function export(Request $request)
    {
        $this->authorizeManage();
        if (!$this->canVerifyOrClose()) {
            abort(403, 'Unauthorized action.');
        }

        $validator = Validator::make($request->all(), [
            'format' => 'required|in:csv,excel,pdf',
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $query = SecurityReport::query();

        if ($request->filled('date_range')) {
            $range = explode(' to ', $request->date_range);
            if (count($range) === 2) {
                $query->whereDate('incident_date', '>=', $range[0])
                      ->whereDate('incident_date', '<=', $range[1]);
            }
        }
        foreach (['report_type', 'status', 'priority'] as $field) {
            if ($request->filled($field) && $request->$field !== 'all') {
                $query->where($field, $request->$field);
            }
        }

        if (!auth()->user()->isSuperAdmin()) {
            $query->where('is_confidential', false);
        }

        $reports = $query->with(['securityPost', 'reporter', 'assignee'])
            ->orderByDesc('incident_date')
            ->get();

        $filename = 'security-reports-' . now()->format('Y-m-d-His') . '.' . $request->format;

        return match ($request->format) {
            'csv'   => $this->exportToCsv($reports, $filename, $request->boolean('include_details')),
            default => $this->exportToCsv($reports, str_replace(['.xlsx', '.pdf'], '.csv', $filename), $request->boolean('include_details')),
        };
    }

    public function dashboardStatistics(Request $request)
    {
        $this->authorizeManage();
        if (!$this->canVerifyOrClose()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        return response()->json([
            'success' => true,
            'statistics' => $this->dashboardStatisticsFor($request),
        ]);
    }

    public function bulkAction(Request $request)
    {
        $this->authorizeManage();
        if (!$this->canVerifyOrClose()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'action'              => 'required|in:assign,verify,resolve,close,delete,export',
            'report_ids'          => 'required|array|min:1',
            'report_ids.*'        => 'exists:security_reports,id',
            'assigned_to'         => 'nullable|required_if:action,assign|exists:users,id',
            'resolution_details'  => 'nullable|required_if:action,resolve|string|min:10',
            'closure_notes'       => 'nullable|required_if:action,close|string|min:10',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $results = ['success' => 0, 'failed' => 0, 'details' => []];
        $currentUser = auth()->user();

        foreach ($request->report_ids as $reportId) {
            try {
                $report = SecurityReport::find($reportId);
                if (!$report) {
                    $results['failed']++;
                    $results['details'][] = ['id' => $reportId, 'success' => false, 'message' => 'Report not found'];
                    continue;
                }

                $ok = false;
                $msg = '';

                switch ($request->action) {
                    case 'assign':
                        $ok = (bool) $report->assignTo($request->assigned_to);
                        $msg = $ok ? 'Assigned' : 'Assign failed';
                        break;
                    case 'verify':
                        if (!in_array($report->status, [SecurityReport::STATUS_CLOSED, SecurityReport::STATUS_CANCELLED], true)) {
                            $report->markAsVerified($currentUser->id);
                            $ok = true; $msg = 'Verified';
                        } else { $msg = 'Cannot verify closed/cancelled'; }
                        break;
                    case 'resolve':
                        if (!in_array($report->status, [SecurityReport::STATUS_CLOSED, SecurityReport::STATUS_CANCELLED], true)) {
                            $report->markAsResolved($request->resolution_details);
                            $ok = true; $msg = 'Resolved';
                        } else { $msg = 'Cannot resolve closed/cancelled'; }
                        break;
                    case 'close':
                        if (!in_array($report->status, [SecurityReport::STATUS_CLOSED, SecurityReport::STATUS_CANCELLED], true)) {
                            $report->markAsClosed($currentUser->id, $request->closure_notes);
                            $ok = true; $msg = 'Closed';
                        } else { $msg = 'Cannot close closed/cancelled'; }
                        break;
                    case 'delete':
                        if ($report->status === SecurityReport::STATUS_PENDING) {
                            $this->deleteReportFiles($report);
                            $report->delete();
                            $ok = true; $msg = 'Deleted';
                        } else { $msg = 'Only pending reports can be deleted'; }
                        break;
                }

                if ($ok) {
                    $results['success']++;
                    $results['details'][] = ['id' => $reportId, 'success' => true, 'message' => $msg];
                } else {
                    $results['failed']++;
                    $results['details'][] = ['id' => $reportId, 'success' => false, 'message' => $msg];
                }
            } catch (\Throwable $e) {
                $results['failed']++;
                $results['details'][] = ['id' => $reportId, 'success' => false, 'message' => 'Error: ' . $e->getMessage()];
                Log::error("Bulk report action failed for #{$reportId}: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => $results['success'] > 0,
            'results' => $results,
            'message' => "Bulk action completed: {$results['success']} succeeded, {$results['failed']} failed",
        ]);
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function dashboardStatisticsFor(Request $request): array
    {
        $startDate = match ($request->get('date_range', 'month')) {
            'today'   => now()->startOfDay(),
            'week'    => now()->subWeek()->startOfDay(),
            'month'   => now()->subMonth()->startOfDay(),
            'quarter' => now()->subMonths(3)->startOfDay(),
            'year'    => now()->subYear()->startOfDay(),
            default   => now()->subMonth()->startOfDay(),
        };

        $stats = SecurityReport::generateStatistics($startDate, now());
        $stats['today']                = SecurityReport::whereDate('created_at', today())->count();
        $stats['yesterday']            = SecurityReport::whereDate('created_at', today()->subDay())->count();
        $stats['pending_high_priority'] = SecurityReport::whereIn('priority', ['high', 'critical'])->where('status', 'pending')->count();
        $stats['overdue']              = SecurityReport::where('status', 'pending')->where('created_at', '<', now()->subDay())->count();
        $stats['avg_resolution_days']  = ($stats['avg_resolution_hours'] ?? 0) / 24;
        $stats['date_range'] = [
            'start' => $startDate->format('M j, Y'),
            'end'   => now()->format('M j, Y'),
            'label' => ucfirst($request->get('date_range', 'month')),
        ];

        return $stats;
    }

    private function uploadFiles(Request $request, string $key, string $directory): array
    {
        if (!$request->hasFile($key)) {
            return [];
        }

        $paths = [];
        foreach ($request->file($key) as $file) {
            if (!$file->isValid()) continue;
            $ext  = strtolower($file->getClientOriginalExtension());
            $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                  . '_' . time() . '_' . Str::random(8) . '.' . $ext;
            $paths[] = $file->storeAs($directory, $name, 'public');
        }
        return $paths;
    }

    private function deleteReportFiles(SecurityReport $report): void
    {
        foreach (['evidence_photos', 'evidence_documents'] as $field) {
            foreach ((array) ($report->$field ?? []) as $path) {
                if ($path && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        }
    }

    private function sendReportNotifications(SecurityReport $report, $sender): void
    {
        $recipients = collect();
        if ($report->assignee) $recipients->push($report->assignee);
        if ($report->reporter && $report->reporter->id !== $sender->id) $recipients->push($report->reporter);

        User::whereIn('type', [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN])
            ->where('status', User::STATUS_ACTIVE)
            ->get()
            ->each(function ($admin) use ($recipients) {
                if (!$recipients->contains('id', $admin->id)) {
                    $recipients->push($admin);
                }
            });

        foreach ($recipients as $recipient) {
            $this->sendIndividualNotification($report, $recipient, $sender);
        }
    }

    private function sendIndividualNotification(SecurityReport $report, $recipient, $sender): void
    {
        try {
            $message = "🔔 *New Security Report*\n\n"
                . "• #: {$report->report_number}\n"
                . "• Title: {$report->title}\n"
                . "• Priority: {$report->priority_label}\n"
                . "• Location: {$report->location}\n"
                . "• Reported by: {$report->reporter?->name}\n\n"
                . "🔗 " . route('security.security-reports.show', $report->id);

            $this->multiChannelService->sendMessage(
                $recipient,
                $message,
                'security_report_notification',
                [
                    'report_id'     => $report->id,
                    'report_number' => $report->report_number,
                    'priority'      => $report->priority,
                    'sender_id'     => $sender->id,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Report notification failed: ' . $e->getMessage());
        }
    }

    private function sendReportUpdateNotifications(SecurityReport $report, $sender): void
    {
        $recipients = collect();
        if ($report->assignee) $recipients->push($report->assignee);
        if ($report->reporter && $report->reporter->id !== $sender->id) $recipients->push($report->reporter);

        if (!empty($report->personnel_involved)) {
            User::whereIn('id', $report->personnel_involved)->get()->each(function ($p) use ($recipients) {
                if (!$recipients->contains('id', $p->id)) $recipients->push($p);
            });
        }

        foreach ($recipients as $recipient) {
            try {
                $this->multiChannelService->sendMessage(
                    $recipient,
                    "🔔 *Security Report Updated*\n• #: {$report->report_number}\n• Status: {$report->status_label}\n🔗 " . route('security.security-reports.show', $report->id),
                    'security_report_update',
                    ['report_id' => $report->id, 'updated_by' => $sender->id]
                );
            } catch (\Throwable $e) {
                Log::error('Update notification failed: ' . $e->getMessage());
            }
        }
    }

    private function sendAssignmentNotification(SecurityReport $report, $sender): void
    {
        if (!$report->assignee) return;

        try {
            $this->multiChannelService->sendMessage(
                $report->assignee,
                "👤 *Assignment: Investigate Security Report*\n• #: {$report->report_number}\n• Title: {$report->title}\n• Priority: {$report->priority_label}\n🔗 " . route('security.security-reports.show', $report->id),
                'security_report_assignment',
                ['report_id' => $report->id, 'assigned_by' => $sender->id]
            );
        } catch (\Throwable $e) {
            Log::error('Assignment notification failed: ' . $e->getMessage());
        }
    }

    private function getReportActivities(SecurityReport $report): array
    {
        $activities = [[
            'type' => 'created',
            'description' => 'Report created',
            'user' => $report->reporter,
            'timestamp' => $report->created_at,
            'details' => 'Initial report submission',
        ]];

        if ($report->assigned_to && $report->assignee) {
            $activities[] = [
                'type' => 'assigned',
                'description' => 'Assigned to investigator',
                'user' => $report->assignee,
                'timestamp' => $report->updated_at,
                'details' => 'Assigned for investigation',
            ];
        }
        if ($report->verified_at && $report->verifier) {
            $activities[] = [
                'type' => 'verified',
                'description' => 'Report verified',
                'user' => $report->verifier,
                'timestamp' => $report->verified_at,
                'details' => $report->verification_notes ?? 'Verified',
            ];
        }
        if ($report->resolved_at) {
            $activities[] = [
                'type' => 'resolved',
                'description' => 'Report resolved',
                'user' => null,
                'timestamp' => $report->resolved_at,
                'details' => $report->resolution_details ?? 'Resolved',
            ];
        }
        if ($report->closed_at && $report->closer) {
            $activities[] = [
                'type' => 'closed',
                'description' => 'Report closed',
                'user' => $report->closer,
                'timestamp' => $report->closed_at,
                'details' => $report->closure_notes ?? 'Closed',
            ];
        }

        usort($activities, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        return $activities;
    }

    private function getContextStatistics(SecurityReport $report): array
    {
        $similar = SecurityReport::where('id', '!=', $report->id)
            ->where(function ($q) use ($report) {
                $q->where('category', $report->category)
                  ->orWhere('security_post_id', $report->security_post_id)
                  ->orWhere('location', 'like', '%' . $report->location . '%');
            })->get();

        $total = $similar->count();
        $resolved = $similar->where('status', SecurityReport::STATUS_RESOLVED)->count();
        $avg = $similar->whereNotNull('resolved_at')
            ->avg(fn($r) => $r->incident_date?->diffInHours($r->resolved_at) ?? 0);

        return [
            'total_similar'                => $total,
            'resolved_similar'             => $resolved,
            'resolution_rate_similar'      => $total > 0 ? round(($resolved / $total) * 100, 2) : 0,
            'avg_resolution_hours_similar' => round($avg ?? 0, 2),
        ];
    }

    private function exportToCsv($reports, string $filename, bool $includeDetails = false)
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($reports, $includeDetails) {
            $fh = fopen('php://output', 'w');
            fwrite($fh, "\xEF\xBB\xBF");

            $cols = [
                'Report #', 'Title', 'Type', 'Category', 'Priority', 'Status',
                'Incident Date', 'Location', 'Digital Address', 'Reporter', 'Assignee',
                'Security Post', 'Severity', 'Est. Damage', 'Injured', 'Deceased',
                'Civilians Affected', 'Created At', 'Resolved At', 'Closed At',
            ];
            if ($includeDetails) {
                $cols = array_merge($cols, [
                    'Description', 'Impact', 'Immediate Actions',
                    'Recommendations', 'Resolution', 'Verification Notes', 'Closure Notes',
                ]);
            }
            fputcsv($fh, $cols);

            foreach ($reports as $r) {
                $row = [
                    $r->report_number, $r->title, $r->report_type_label, $r->category_label,
                    $r->priority_label, $r->status_label,
                    $r->incident_date?->format('Y-m-d H:i:s'),
                    $r->location, $r->digital_address,
                    $r->reporter?->name, $r->assignee?->name, $r->securityPost?->name,
                    $r->severity_level, $r->estimated_damage,
                    $r->personnel_injured, $r->personnel_deceased, $r->civilians_affected,
                    $r->created_at?->format('Y-m-d H:i:s'),
                    $r->resolved_at?->format('Y-m-d H:i:s'),
                    $r->closed_at?->format('Y-m-d H:i:s'),
                ];
                if ($includeDetails) {
                    $row = array_merge($row, [
                        $r->description, $r->impact_assessment, $r->immediate_actions,
                        $r->recommendations, $r->resolution_details,
                        $r->verification_notes, $r->closure_notes,
                    ]);
                }
                fputcsv($fh, $row);
            }
            fclose($fh);
        };

        return response()->stream($callback, 200, $headers);
    }
}