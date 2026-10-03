<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityReport;
use App\Models\SecurityPost;
use App\Models\SecuritySchedule;
use App\Models\User;
use App\Services\MultiChannelInvitationService;
use App\Services\SmsService;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SecurityReportsController extends Controller
{
    protected $multiChannelService;
    protected $smsService;
    protected $emailService;

    public function __construct(
        MultiChannelInvitationService $multiChannelService,
        SmsService $smsService,
        EmailService $emailService
    ) {
        $this->multiChannelService = $multiChannelService;
        $this->smsService = $smsService;
        $this->emailService = $emailService;
    }

    /**
     * Display a listing of security reports with filters
     */
    public function index(Request $request)
    {
        $currentUser = auth()->user();
        
        $query = SecurityReport::with([
            'securityPost',
            'reporter',
            'assignee',
            'verifier'
        ])->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('report_number', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('digital_address', 'like', "%{$search}%");
            });
        }

        if ($request->has('report_type') && $request->report_type != 'all') {
            $query->where('report_type', $request->report_type);
        }

        if ($request->has('category') && $request->category != 'all') {
            $query->where('category', $request->category);
        }

        if ($request->has('priority') && $request->priority != 'all') {
            $query->where('priority', $request->priority);
        }

        if ($request->has('status') && $request->status != 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('security_post_id') && $request->security_post_id != 'all') {
            $query->where('security_post_id', $request->security_post_id);
        }

        if ($request->has('reported_by') && $request->reported_by != 'all') {
            $query->where('reported_by', $request->reported_by);
        }

        if ($request->has('assigned_to') && $request->assigned_to != 'all') {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->has('date_range') && $request->date_range != 'all') {
            $dateRange = explode(' to ', $request->date_range);
            if (count($dateRange) == 2) {
                $query->whereDate('incident_date', '>=', $dateRange[0])
                      ->whereDate('incident_date', '<=', $dateRange[1]);
            } elseif (count($dateRange) == 1) {
                $query->whereDate('incident_date', $dateRange[0]);
            }
        }

        if ($request->has('requires_followup') && $request->requires_followup == '1') {
            $query->where('requires_followup', true)
                  ->where(function($q) {
                      $q->whereNull('followup_date')
                        ->orWhere('followup_date', '<=', now()->addDays(3));
                  });
        }

        if ($request->has('is_confidential') && $request->is_confidential == '1') {
            $query->where('is_confidential', true);
        }

        // Filter out confidential reports that user cannot view
        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            $query->where(function($q) use ($currentUser) {
                $q->where('is_confidential', false)
                  ->orWhere('reported_by', $currentUser->id)
                  ->orWhere('assigned_to', $currentUser->id)
                  ->orWhereJsonContains('personnel_involved', $currentUser->id);
            });
        }

        // Paginate results
        $perPage = $request->get('per_page', 20);
        $reports = $query->paginate($perPage);

        // Get statistics for dashboard
        $stats = $this->getDashboardStatistics($request);

        // Get filter options
        $reportTypes = SecurityReport::getReportTypes();
        $categories = SecurityReport::getCategories();
        $priorities = SecurityReport::getPriorities();
        $statuses = SecurityReport::getStatuses();
        
        $securityPosts = SecurityPost::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
            
        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);

        // Get recent critical reports for alerts
        $criticalReports = SecurityReport::whereIn('priority', ['high', 'critical'])
            ->whereIn('status', ['pending', 'under_investigation'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Get overdue reports
        $overdueReports = SecurityReport::where('status', 'pending')
            ->where('created_at', '<', now()->subDays(1))
            ->orderBy('created_at', 'asc')
            ->take(5)
            ->get();

        return view('admin.security-reports.index', compact(
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

    /**
     * Show the form for creating a new security report
     */
    public function create()
    {
        $currentUser = auth()->user();
        
        // Get active security posts
        $securityPosts = SecurityPost::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'location']);
            
        // Get active security schedules
        $securitySchedules = SecuritySchedule::where('status', 'scheduled')
            ->with(['securityPost', 'securityPersonnel'])
            ->orderBy('start_time')
            ->get();
            
        // Get security personnel for assignment
        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);
        
        // Get report types, categories, priorities
        $reportTypes = SecurityReport::getReportTypes();
        $categories = SecurityReport::getCategories();
        $priorities = SecurityReport::getPriorities();
        
        // Pre-select current user as reporter
        $defaultReporter = $currentUser->id;

        return view('admin.security-reports.create', compact(
            'securityPosts',
            'securitySchedules',
            'securityPersonnel',
            'reportTypes',
            'categories',
            'priorities',
            'defaultReporter'
        ));
    }

    /**
     * Store a newly created security report
     */
    public function store(Request $request)
    {
        $currentUser = auth()->user();
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string|min:10',
            'report_type' => 'required|string|in:' . implode(',', array_keys(SecurityReport::getReportTypes())),
            'category' => 'required|string|in:' . implode(',', array_keys(SecurityReport::getCategories())),
            'priority' => 'required|string|in:' . implode(',', array_keys(SecurityReport::getPriorities())),
            
            // Relationships
            'security_post_id' => 'nullable|exists:security_posts,id',
            'security_schedule_id' => 'nullable|exists:security_schedules,id',
            'reported_by' => 'required|exists:users,id',
            'assigned_to' => 'nullable|exists:users,id',
            
            // Incident details
            'incident_date' => 'nullable|date',
            'location' => 'required|string|max:255',
            'digital_address' => 'nullable|string|max:255',
            
            // Personnel involved
            'personnel_involved' => 'nullable|array',
            'personnel_involved.*' => 'exists:users,id',
            'witnesses' => 'nullable|array',
            'witnesses.*.name' => 'nullable|string|max:255',
            'witnesses.*.contact' => 'nullable|string|max:255',
            
            // Impact & response
            'impact_assessment' => 'nullable|string|max:1000',
            'immediate_actions' => 'nullable|string|max:1000',
            'recommendations' => 'nullable|string|max:1000',
            
            // Evidence
            'evidence_photos' => 'nullable|array',
            'evidence_photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'evidence_documents' => 'nullable|array',
            'evidence_documents.*' => 'file|mimes:pdf,doc,docx,txt|max:10240',
            
            // Followup
            'requires_followup' => 'sometimes|boolean',
            'followup_date' => 'nullable|date|after:today',
            
            // Confidentiality
            'is_confidential' => 'sometimes|boolean',
            'notify_stakeholders' => 'sometimes|boolean',
            
            // Statistics
            'severity_level' => 'nullable|integer|min:1|max:10',
            'estimated_damage' => 'nullable|numeric|min:0',
            'personnel_injured' => 'nullable|integer|min:0',
            'personnel_deceased' => 'nullable|integer|min:0',
            'civilians_affected' => 'nullable|integer|min:0',
        ], [
            'title.required' => 'Please provide a title for the report',
            'description.required' => 'Please provide a detailed description of the incident',
            'location.required' => 'Please specify the location of the incident',
            'incident_date.date' => 'Please provide a valid date for the incident',
            'evidence_photos.*.image' => 'Only image files are allowed for photos',
            'evidence_documents.*.file' => 'Only PDF, DOC, DOCX, or TXT files are allowed',
        ]);

        // Custom validation for high priority reports
        $validator->after(function ($validator) use ($request) {
            if (in_array($request->priority, ['high', 'critical']) && empty($request->immediate_actions)) {
                $validator->errors()->add('immediate_actions', 
                    'Please describe immediate actions taken for high/critical priority reports');
            }
            
            if ($request->category === 'theft' && empty($request->estimated_damage)) {
                $validator->errors()->add('estimated_damage', 
                    'Please provide estimated damage value for theft reports');
            }
            
            if (in_array($request->category, ['medical', 'fire']) && empty($request->personnel_injured)) {
                $validator->errors()->add('personnel_injured', 
                    'Please specify number of personnel injured for medical/fire incidents');
            }
        });

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please fix the errors below');
        }

        DB::beginTransaction();

        try {
            // Prepare report data
            $reportData = [
                'title' => $request->title,
                'description' => $request->description,
                'report_type' => $request->report_type,
                'category' => $request->category,
                'priority' => $request->priority,
                'status' => SecurityReport::STATUS_PENDING,
                
                // Relationships
                'security_post_id' => $request->security_post_id,
                'security_schedule_id' => $request->security_schedule_id,
                'reported_by' => $request->reported_by,
                'assigned_to' => $request->assigned_to,
                
                // Incident details
                'incident_date' => $request->incident_date ? Carbon::parse($request->incident_date) : now(),
                'report_date' => now(),
                'location' => $request->location,
                'digital_address' => $request->digital_address,
                
                // Personnel involved
                'personnel_involved' => $request->personnel_involved,
                'witnesses' => $request->witnesses,
                
                // Impact & response
                'impact_assessment' => $request->impact_assessment,
                'immediate_actions' => $request->immediate_actions,
                'recommendations' => $request->recommendations,
                
                // Followup
                'requires_followup' => $request->boolean('requires_followup'),
                'followup_date' => $request->followup_date ? Carbon::parse($request->followup_date) : null,
                
                // Confidentiality
                'is_confidential' => $request->boolean('is_confidential'),
                'notify_stakeholders' => $request->boolean('notify_stakeholders'),
                
                // Statistics
                'severity_level' => $request->severity_level,
                'estimated_damage' => $request->estimated_damage,
                'personnel_injured' => $request->personnel_injured ?? 0,
                'personnel_deceased' => $request->personnel_deceased ?? 0,
                'civilians_affected' => $request->civilians_affected ?? 0,
                
                // Metadata
                'metadata' => [
                    'created_by' => $currentUser->id,
                    'created_by_role' => $currentUser->isSuperAdmin() ? 'super_admin' : 'admin',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            ];

            // Handle evidence file uploads
            $evidencePhotos = [];
            $evidenceDocuments = [];

            // Upload photos
            if ($request->hasFile('evidence_photos')) {
                foreach ($request->file('evidence_photos') as $photo) {
                    if ($photo->isValid()) {
                        $path = $this->handleFileUpload($photo, 'security-reports/photos');
                        $evidencePhotos[] = $path;
                    }
                }
                $reportData['evidence_photos'] = $evidencePhotos;
            }

            // Upload documents
            if ($request->hasFile('evidence_documents')) {
                foreach ($request->file('evidence_documents') as $document) {
                    if ($document->isValid()) {
                        $path = $this->handleFileUpload($document, 'security-reports/documents');
                        $evidenceDocuments[] = $path;
                    }
                }
                $reportData['evidence_documents'] = $evidenceDocuments;
            }

            // Create the report
            $report = SecurityReport::create($reportData);

            // If assigned, update status to under investigation
            if ($report->assigned_to) {
                $report->update(['status' => SecurityReport::STATUS_UNDER_INVESTIGATION]);
            }

            // Send notifications if requested
            if ($request->boolean('notify_stakeholders')) {
                $this->sendReportNotifications($report, $currentUser);
            }

            // Log the creation
            Log::info('Security report created', [
                'report_id' => $report->id,
                'report_number' => $report->report_number,
                'title' => $report->title,
                'priority' => $report->priority,
                'created_by' => $currentUser->id,
                'created_by_name' => $currentUser->name,
            ]);

            DB::commit();

            return redirect()->route('admin.security-reports.show', $report->id)
                ->with('success', 'Security report created successfully!')
                ->with('report_number', $report->report_number);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create security report: ' . $e->getMessage(), [
                'request_data' => $request->except(['evidence_photos', 'evidence_documents']),
                'user_id' => $currentUser->id,
                'error_trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'Failed to create security report: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified security report
     */
    public function show($id)
    {
        $currentUser = auth()->user();
        
        $report = SecurityReport::with([
            'securityPost',
            'securitySchedule',
            'reporter',
            'assignee',
            'verifier',
            'closer',
            'involvedPersonnel'
        ])->findOrFail($id);

        // Check if user can view confidential report
        if (!$report->canView($currentUser)) {
            abort(403, 'You are not authorized to view this confidential report.');
        }

        // Get related reports (same location, same date, etc.)
        $relatedReports = SecurityReport::where('id', '!=', $report->id)
            ->where(function($q) use ($report) {
                $q->where('security_post_id', $report->security_post_id)
                  ->orWhere('location', 'like', '%' . $report->location . '%')
                  ->orWhere('category', $report->category);
            })
            ->whereDate('incident_date', $report->incident_date?->toDateString())
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Get activity log (you can implement this with a separate activity log model)
        $activities = $this->getReportActivities($report);

        // Get statistics for this report's context
        $contextStats = $this->getContextStatistics($report);

        // Check if report requires followup
        $requiresFollowupAlert = $report->requires_followup && 
                               (!$report->followup_date || $report->followup_date <= now()->addDays(1));

        return view('admin.security-reports.show', compact(
            'report',
            'relatedReports',
            'activities',
            'contextStats',
            'requiresFollowupAlert'
        ));
    }

    /**
     * Show the form for editing the specified security report
     */
    public function edit($id)
    {
        $currentUser = auth()->user();
        
        $report = SecurityReport::findOrFail($id);

        // Authorization check
        if (!$report->canView($currentUser)) {
            abort(403, 'You are not authorized to edit this report.');
        }

        // Only allow editing of pending or under investigation reports
        if (!in_array($report->status, [SecurityReport::STATUS_PENDING, SecurityReport::STATUS_UNDER_INVESTIGATION])) {
            return back()->with('warning', 'Cannot edit reports that are already resolved or closed.');
        }

        // Get active security posts
        $securityPosts = SecurityPost::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'location']);
            
        // Get active security schedules
        $securitySchedules = SecuritySchedule::where('status', 'scheduled')
            ->with(['securityPost', 'securityPersonnel'])
            ->orderBy('start_time')
            ->get();
            
        // Get security personnel for assignment
        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);
        
        // Get report types, categories, priorities
        $reportTypes = SecurityReport::getReportTypes();
        $categories = SecurityReport::getCategories();
        $priorities = SecurityReport::getPriorities();

        return view('admin.security-reports.edit', compact(
            'report',
            'securityPosts',
            'securitySchedules',
            'securityPersonnel',
            'reportTypes',
            'categories',
            'priorities'
        ));
    }

    /**
     * Update the specified security report
     */
    public function update(Request $request, $id)
    {
        $currentUser = auth()->user();
        
        $report = SecurityReport::findOrFail($id);

        // Authorization check
        if (!$report->canView($currentUser)) {
            abort(403, 'You are not authorized to update this report.');
        }

        // Only allow updating of pending or under investigation reports
        if (!in_array($report->status, [SecurityReport::STATUS_PENDING, SecurityReport::STATUS_UNDER_INVESTIGATION])) {
            return back()->with('warning', 'Cannot update reports that are already resolved or closed.');
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string|min:10',
            'report_type' => 'required|string|in:' . implode(',', array_keys(SecurityReport::getReportTypes())),
            'category' => 'required|string|in:' . implode(',', array_keys(SecurityReport::getCategories())),
            'priority' => 'required|string|in:' . implode(',', array_keys(SecurityReport::getPriorities())),
            
            // Relationships
            'security_post_id' => 'nullable|exists:security_posts,id',
            'security_schedule_id' => 'nullable|exists:security_schedules,id',
            'assigned_to' => 'nullable|exists:users,id',
            
            // Incident details
            'incident_date' => 'nullable|date',
            'location' => 'required|string|max:255',
            'digital_address' => 'nullable|string|max:255',
            
            // Personnel involved
            'personnel_involved' => 'nullable|array',
            'personnel_involved.*' => 'exists:users,id',
            'witnesses' => 'nullable|array',
            'witnesses.*.name' => 'nullable|string|max:255',
            'witnesses.*.contact' => 'nullable|string|max:255',
            
            // Impact & response
            'impact_assessment' => 'nullable|string|max:1000',
            'immediate_actions' => 'nullable|string|max:1000',
            'recommendations' => 'nullable|string|max:1000',
            
            // Evidence (new files only)
            'new_evidence_photos' => 'nullable|array',
            'new_evidence_photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'new_evidence_documents' => 'nullable|array',
            'new_evidence_documents.*' => 'file|mimes:pdf,doc,docx,txt|max:10240',
            
            // Followup
            'requires_followup' => 'sometimes|boolean',
            'followup_date' => 'nullable|date|after:today',
            
            // Confidentiality
            'is_confidential' => 'sometimes|boolean',
            'notify_stakeholders' => 'sometimes|boolean',
            
            // Statistics
            'severity_level' => 'nullable|integer|min:1|max:10',
            'estimated_damage' => 'nullable|numeric|min:0',
            'personnel_injured' => 'nullable|integer|min:0',
            'personnel_deceased' => 'nullable|integer|min:0',
            'civilians_affected' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please fix the errors below');
        }

        DB::beginTransaction();

        try {
            // Prepare update data
            $updateData = [
                'title' => $request->title,
                'description' => $request->description,
                'report_type' => $request->report_type,
                'category' => $request->category,
                'priority' => $request->priority,
                
                // Relationships
                'security_post_id' => $request->security_post_id,
                'security_schedule_id' => $request->security_schedule_id,
                'assigned_to' => $request->assigned_to,
                
                // Incident details
                'incident_date' => $request->incident_date ? Carbon::parse($request->incident_date) : $report->incident_date,
                'location' => $request->location,
                'digital_address' => $request->digital_address,
                
                // Personnel involved
                'personnel_involved' => $request->personnel_involved,
                'witnesses' => $request->witnesses,
                
                // Impact & response
                'impact_assessment' => $request->impact_assessment,
                'immediate_actions' => $request->immediate_actions,
                'recommendations' => $request->recommendations,
                
                // Followup
                'requires_followup' => $request->boolean('requires_followup'),
                'followup_date' => $request->followup_date ? Carbon::parse($request->followup_date) : null,
                
                // Confidentiality
                'is_confidential' => $request->boolean('is_confidential'),
                
                // Statistics
                'severity_level' => $request->severity_level,
                'estimated_damage' => $request->estimated_damage,
                'personnel_injured' => $request->personnel_injured ?? $report->personnel_injured,
                'personnel_deceased' => $request->personnel_deceased ?? $report->personnel_deceased,
                'civilians_affected' => $request->civilians_affected ?? $report->civilians_affected,
            ];

            // Handle assignment change - update status
            if ($request->assigned_to && !$report->assigned_to) {
                $updateData['status'] = SecurityReport::STATUS_UNDER_INVESTIGATION;
            }

            // Handle new evidence file uploads
            $existingPhotos = $report->evidence_photos ?? [];
            $existingDocuments = $report->evidence_documents ?? [];

            // Upload new photos
            if ($request->hasFile('new_evidence_photos')) {
                foreach ($request->file('new_evidence_photos') as $photo) {
                    if ($photo->isValid()) {
                        $path = $this->handleFileUpload($photo, 'security-reports/photos');
                        $existingPhotos[] = $path;
                    }
                }
                $updateData['evidence_photos'] = $existingPhotos;
            }

            // Upload new documents
            if ($request->hasFile('new_evidence_documents')) {
                foreach ($request->file('new_evidence_documents') as $document) {
                    if ($document->isValid()) {
                        $path = $this->handleFileUpload($document, 'security-reports/documents');
                        $existingDocuments[] = $path;
                    }
                }
                $updateData['evidence_documents'] = $existingDocuments;
            }

            // Update metadata
            $metadata = $report->metadata ?? [];
            $metadata['updated_by'] = $currentUser->id;
            $metadata['updated_at'] = now()->toDateTimeString();
            $metadata['update_reason'] = 'Manual update by admin';
            $updateData['metadata'] = $metadata;

            // Update the report
            $report->update($updateData);

            // Send notifications if requested
            if ($request->boolean('notify_stakeholders')) {
                $this->sendReportUpdateNotifications($report, $currentUser);
            }

            // Log the update
            Log::info('Security report updated', [
                'report_id' => $report->id,
                'report_number' => $report->report_number,
                'updated_by' => $currentUser->id,
                'updated_by_name' => $currentUser->name,
                'changes' => array_keys($updateData),
            ]);

            DB::commit();

            return redirect()->route('admin.security-reports.show', $report->id)
                ->with('success', 'Security report updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update security report: ' . $e->getMessage(), [
                'report_id' => $id,
                'user_id' => $currentUser->id,
                'error_trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'Failed to update security report: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified security report
     */
    public function destroy($id)
    {
        $currentUser = auth()->user();
        
        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $report = SecurityReport::findOrFail($id);

        // Only allow deletion of pending reports
        if ($report->status !== SecurityReport::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending reports can be deleted.'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Delete evidence files
            $this->deleteReportFiles($report);
            
            // Soft delete the report
            $report->delete();

            // Log the deletion
            Log::warning('Security report deleted', [
                'report_id' => $id,
                'report_number' => $report->report_number,
                'title' => $report->title,
                'deleted_by' => $currentUser->id,
                'deleted_by_name' => $currentUser->name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Security report deleted successfully!',
                'redirect_url' => route('admin.security-reports.index')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete security report: ' . $e->getMessage(), [
                'report_id' => $id,
                'user_id' => $currentUser->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete security report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign report to investigator
     */
    public function assign(Request $request, $id)
    {
        $currentUser = auth()->user();
        
        $report = SecurityReport::findOrFail($id);

        // Authorization check
        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'assigned_to' => 'required|exists:users,id',
            'assignment_notes' => 'nullable|string|max:1000',
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
            $oldAssignee = $report->assignee;
            
            // Update assignment
            $report->assignTo($request->assigned_to);
            
            // Update metadata
            $metadata = $report->metadata ?? [];
            $metadata['assignment_history'][] = [
                'from' => $oldAssignee ? $oldAssignee->id : null,
                'to' => $request->assigned_to,
                'assigned_by' => $currentUser->id,
                'assigned_at' => now()->toDateTimeString(),
                'notes' => $request->assignment_notes,
            ];
            $report->update(['metadata' => $metadata]);

            // Send notification to new assignee
            $this->sendAssignmentNotification($report, $currentUser);

            // Log the assignment
            Log::info('Security report assigned', [
                'report_id' => $report->id,
                'report_number' => $report->report_number,
                'old_assignee' => $oldAssignee ? $oldAssignee->id : null,
                'new_assignee' => $request->assigned_to,
                'assigned_by' => $currentUser->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Report assigned successfully!',
                'report' => $report->fresh(['assignee']),
                'status_label' => $report->status_label,
                'assignee_name' => $report->assignee?->name,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign security report: ' . $e->getMessage(), [
                'report_id' => $id,
                'user_id' => $currentUser->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify the security report
     */
    public function verify(Request $request, $id)
    {
        $currentUser = auth()->user();
        
        $report = SecurityReport::findOrFail($id);

        // Authorization check - only admins and super admins can verify
        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'verification_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if report can be verified
        if ($report->status === SecurityReport::STATUS_CLOSED || $report->status === SecurityReport::STATUS_CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot verify a closed or cancelled report.'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Mark as verified
            $report->markAsVerified($currentUser->id, $request->verification_notes);

            // Log the verification
            Log::info('Security report verified', [
                'report_id' => $report->id,
                'report_number' => $report->report_number,
                'verified_by' => $currentUser->id,
                'verified_by_name' => $currentUser->name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Report verified successfully!',
                'report' => $report->fresh(['verifier']),
                'verified_at' => $report->verified_at->format('M j, Y g:i A'),
                'verifier_name' => $report->verifier?->name,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to verify security report: ' . e($e->getMessage()), [
                'report_id' => $id,
                'user_id' => $currentUser->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resolve the security report
     */
    public function resolve(Request $request, $id)
    {
        $currentUser = auth()->user();
        
        $report = SecurityReport::findOrFail($id);

        // Authorization check - only assignee, admins, or super admins can resolve
        if (!$report->canView($currentUser) && $report->assigned_to !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'resolution_details' => 'required|string|min:10|max:2000',
            'mark_as_closed' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if report can be resolved
        if ($report->status === SecurityReport::STATUS_CLOSED || $report->status === SecurityReport::STATUS_CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot resolve a closed or cancelled report.'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Mark as resolved
            $report->markAsResolved($request->resolution_details);

            // If mark_as_closed is true, also close the report
            if ($request->boolean('mark_as_closed')) {
                $report->markAsClosed($currentUser->id, 'Automatically closed after resolution');
            }

            // Log the resolution
            Log::info('Security report resolved', [
                'report_id' => $report->id,
                'report_number' => $report->report_number,
                'resolved_by' => $currentUser->id,
                'resolved_by_name' => $currentUser->name,
                'also_closed' => $request->boolean('mark_as_closed'),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Report resolved successfully!' . ($request->boolean('mark_as_closed') ? ' Report also closed.' : ''),
                'report' => $report->fresh(),
                'status_label' => $report->status_label,
                'resolved_at' => $report->resolved_at->format('M j, Y g:i A'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to resolve security report: ' . $e->getMessage(), [
                'report_id' => $id,
                'user_id' => $currentUser->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Close the security report
     */
    public function close(Request $request, $id)
    {
        $currentUser = auth()->user();
        
        $report = SecurityReport::findOrFail($id);

        // Authorization check - only admins and super admins can close
        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'closure_notes' => 'required|string|min:10|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if report can be closed
        if ($report->status === SecurityReport::STATUS_CLOSED) {
            return response()->json([
                'success' => false,
                'message' => 'Report is already closed.'
            ], 422);
        }

        if ($report->status === SecurityReport::STATUS_CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot close a cancelled report.'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Mark as closed
            $report->markAsClosed($currentUser->id, $request->closure_notes);

            // Log the closure
            Log::info('Security report closed', [
                'report_id' => $report->id,
                'report_number' => $report->report_number,
                'closed_by' => $currentUser->id,
                'closed_by_name' => $currentUser->name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Report closed successfully!',
                'report' => $report->fresh(['closer']),
                'status_label' => $report->status_label,
                'closed_at' => $report->closed_at->format('M j, Y g:i A'),
                'closer_name' => $report->closer?->name,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to close security report: ' . $e->getMessage(), [
                'report_id' => $id,
                'user_id' => $currentUser->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to close report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel the security report
     */
    public function cancel(Request $request, $id)
    {
        $currentUser = auth()->user();
        
        $report = SecurityReport::findOrFail($id);

        // Authorization check - only super admins can cancel
        if (!$currentUser->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action. Only super admins can cancel reports.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'cancellation_reason' => 'required|string|min:10|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if report can be cancelled
        if ($report->status === SecurityReport::STATUS_CLOSED) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel a closed report.'
            ], 422);
        }

        if ($report->status === SecurityReport::STATUS_CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'Report is already cancelled.'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Update to cancelled status
            $report->update([
                'status' => SecurityReport::STATUS_CANCELLED,
                'metadata' => array_merge($report->metadata ?? [], [
                    'cancelled_by' => $currentUser->id,
                    'cancelled_at' => now()->toDateTimeString(),
                    'cancellation_reason' => $request->cancellation_reason,
                ]),
            ]);

            // Log the cancellation
            Log::warning('Security report cancelled', [
                'report_id' => $report->id,
                'report_number' => $report->report_number,
                'cancelled_by' => $currentUser->id,
                'cancelled_by_name' => $currentUser->name,
                'cancellation_reason' => $request->cancellation_reason,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Report cancelled successfully!',
                'report' => $report->fresh(),
                'status_label' => $report->status_label,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel security report: ' . $e->getMessage(), [
                'report_id' => $id,
                'user_id' => $currentUser->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export security reports
     */
    public function export(Request $request)
    {
        $currentUser = auth()->user();
        
        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validator = Validator::make($request->all(), [
            'format' => 'required|in:csv,excel,pdf',
            'date_range' => 'nullable|string',
            'report_type' => 'nullable|string',
            'status' => 'nullable|string',
            'priority' => 'nullable|string',
            'include_details' => 'sometimes|boolean',
            'include_evidence' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Build query based on filters
        $query = SecurityReport::query();

        if ($request->has('date_range') && $request->date_range) {
            $dateRange = explode(' to ', $request->date_range);
            if (count($dateRange) == 2) {
                $query->whereDate('incident_date', '>=', $dateRange[0])
                      ->whereDate('incident_date', '<=', $dateRange[1]);
            }
        }

        if ($request->has('report_type') && $request->report_type != 'all') {
            $query->where('report_type', $request->report_type);
        }

        if ($request->has('status') && $request->status != 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('priority') && $request->priority != 'all') {
            $query->where('priority', $request->priority);
        }

        // Filter out confidential reports if not admin
        if (!$currentUser->isSuperAdmin()) {
            $query->where('is_confidential', false);
        }

        $reports = $query->with(['securityPost', 'reporter', 'assignee'])
            ->orderBy('incident_date', 'desc')
            ->get();

        // Generate filename
        $filename = 'security-reports-' . date('Y-m-d-H-i-s') . '.' . $request->format;

        // Handle different export formats
        switch ($request->format) {
            case 'csv':
                return $this->exportToCsv($reports, $filename, $request->boolean('include_details'));
            case 'excel':
                return $this->exportToExcel($reports, $filename, $request->boolean('include_details'));
            case 'pdf':
                return $this->exportToPdf($reports, $filename, $request->boolean('include_details'));
            default:
                return back()->with('error', 'Unsupported export format.');
        }
    }

    /**
     * Generate dashboard statistics
     */
    public function dashboardStatistics(Request $request)
    {
        $currentUser = auth()->user();
        
        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $dateRange = $request->get('date_range', 'month');
        $startDate = null;
        $endDate = now();

        switch ($dateRange) {
            case 'today':
                $startDate = now()->startOfDay();
                break;
            case 'week':
                $startDate = now()->subWeek()->startOfDay();
                break;
            case 'month':
                $startDate = now()->subMonth()->startOfDay();
                break;
            case 'quarter':
                $startDate = now()->subMonths(3)->startOfDay();
                break;
            case 'year':
                $startDate = now()->subYear()->startOfDay();
                break;
            default:
                $startDate = now()->subMonth()->startOfDay();
        }

        $stats = SecurityReport::generateStatistics($startDate, $endDate);

        // Add additional stats
        $stats['date_range'] = [
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d'),
            'label' => ucfirst($dateRange),
        ];

        // Get top categories
        $topCategories = SecurityReport::whereBetween('incident_date', [$startDate, $endDate])
            ->select('category', DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->take(5)
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->category => $item->count];
            });

        $stats['top_categories'] = $topCategories;

        // Get reports by day for chart
        $reportsByDay = SecurityReport::whereBetween('incident_date', [$startDate, $endDate])
            ->select(DB::raw('DATE(incident_date) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $stats['reports_by_day'] = $reportsByDay;

        return response()->json([
            'success' => true,
            'statistics' => $stats,
        ]);
    }

    /**
     * Bulk actions for security reports
     */
    public function bulkAction(Request $request)
    {
        $currentUser = auth()->user();
        
        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:assign,verify,resolve,close,delete,export',
            'report_ids' => 'required|array',
            'report_ids.*' => 'exists:security_reports,id',
            'assigned_to' => 'nullable|required_if:action,assign|exists:users,id',
            'resolution_details' => 'nullable|required_if:action,resolve|string|min:10',
            'closure_notes' => 'nullable|required_if:action,close|string|min:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $results = [
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($request->report_ids as $reportId) {
            try {
                $report = SecurityReport::find($reportId);
                
                if (!$report) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $reportId,
                        'success' => false,
                        'message' => 'Report not found'
                    ];
                    continue;
                }

                switch ($request->action) {
                    case 'assign':
                        if ($report->assignTo($request->assigned_to)) {
                            $results['success']++;
                            $results['details'][] = [
                                'id' => $reportId,
                                'success' => true,
                                'message' => 'Assigned successfully'
                            ];
                        } else {
                            $results['failed']++;
                        }
                        break;

                    case 'verify':
                        if ($report->status !== SecurityReport::STATUS_CLOSED && 
                            $report->status !== SecurityReport::STATUS_CANCELLED) {
                            $report->markAsVerified($currentUser->id);
                            $results['success']++;
                            $results['details'][] = [
                                'id' => $reportId,
                                'success' => true,
                                'message' => 'Verified successfully'
                            ];
                        } else {
                            $results['failed']++;
                            $results['details'][] = [
                                'id' => $reportId,
                                'success' => false,
                                'message' => 'Cannot verify closed/cancelled report'
                            ];
                        }
                        break;

                    case 'resolve':
                        if ($report->status !== SecurityReport::STATUS_CLOSED && 
                            $report->status !== SecurityReport::STATUS_CANCELLED) {
                            $report->markAsResolved($request->resolution_details);
                            $results['success']++;
                            $results['details'][] = [
                                'id' => $reportId,
                                'success' => true,
                                'message' => 'Resolved successfully'
                            ];
                        } else {
                            $results['failed']++;
                            $results['details'][] = [
                                'id' => $reportId,
                                'success' => false,
                                'message' => 'Cannot resolve closed/cancelled report'
                            ];
                        }
                        break;

                    case 'close':
                        if ($report->status !== SecurityReport::STATUS_CLOSED && 
                            $report->status !== SecurityReport::STATUS_CANCELLED) {
                            $report->markAsClosed($currentUser->id, $request->closure_notes);
                            $results['success']++;
                            $results['details'][] = [
                                'id' => $reportId,
                                'success' => true,
                                'message' => 'Closed successfully'
                            ];
                        } else {
                            $results['failed']++;
                            $results['details'][] = [
                                'id' => $reportId,
                                'success' => false,
                                'message' => 'Cannot close already closed/cancelled report'
                            ];
                        }
                        break;

                    case 'delete':
                        if ($report->status === SecurityReport::STATUS_PENDING) {
                            $this->deleteReportFiles($report);
                            $report->delete();
                            $results['success']++;
                            $results['details'][] = [
                                'id' => $reportId,
                                'success' => true,
                                'message' => 'Deleted successfully'
                            ];
                        } else {
                            $results['failed']++;
                            $results['details'][] = [
                                'id' => $reportId,
                                'success' => false,
                                'message' => 'Only pending reports can be deleted'
                            ];
                        }
                        break;
                }

            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'id' => $reportId,
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ];
                Log::error("Error in bulk action for report {$reportId}: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => $results['success'] > 0,
            'results' => $results,
            'message' => "Bulk action completed: {$results['success']} successful, {$results['failed']} failed"
        ]);
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * Get dashboard statistics
     */
    private function getDashboardStatistics(Request $request)
    {
        $dateRange = $request->get('date_range', 'month');
        $startDate = null;

        switch ($dateRange) {
            case 'today':
                $startDate = now()->startOfDay();
                break;
            case 'week':
                $startDate = now()->subWeek()->startOfDay();
                break;
            case 'month':
                $startDate = now()->subMonth()->startOfDay();
                break;
            case 'quarter':
                $startDate = now()->subMonths(3)->startOfDay();
                break;
            case 'year':
                $startDate = now()->subYear()->startOfDay();
                break;
            default:
                $startDate = now()->subMonth()->startOfDay();
        }

        $stats = SecurityReport::generateStatistics($startDate, now());

        // Add today's count
        $stats['today'] = SecurityReport::whereDate('created_at', today())->count();
        
        // Add yesterday's count
        $stats['yesterday'] = SecurityReport::whereDate('created_at', today()->subDay())->count();
        
        // Add pending high priority
        $stats['pending_high_priority'] = SecurityReport::whereIn('priority', ['high', 'critical'])
            ->where('status', 'pending')
            ->count();
            
        // Add overdue reports
        $stats['overdue'] = SecurityReport::where('status', 'pending')
            ->where('created_at', '<', now()->subDays(1))
            ->count();
            
        // Add average resolution time
        $stats['avg_resolution_days'] = $stats['avg_resolution_hours'] / 24;
        
        // Add date range
        $stats['date_range'] = [
            'start' => $startDate->format('M j, Y'),
            'end' => now()->format('M j, Y'),
            'label' => ucfirst($dateRange),
        ];

        return $stats;
    }

    /**
     * Handle file upload
     */
    private function handleFileUpload($file, $directory)
    {
        try {
            // Generate unique filename
            $extension = strtolower($file->getClientOriginalExtension());
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $filename = Str::slug($originalName) . '_' . time() . '_' . Str::random(8) . '.' . $extension;
            
            // Store the file
            $path = $file->storeAs($directory, $filename, 'public');
            
            return $path;

        } catch (\Exception $e) {
            Log::error('Failed to upload file: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete report files
     */
    private function deleteReportFiles(SecurityReport $report)
    {
        try {
            // Delete evidence photos
            if ($report->evidence_photos) {
                foreach ($report->evidence_photos as $photo) {
                    if (Storage::disk('public')->exists($photo)) {
                        Storage::disk('public')->delete($photo);
                    }
                }
            }

            // Delete evidence documents
            if ($report->evidence_documents) {
                foreach ($report->evidence_documents as $document) {
                    if (Storage::disk('public')->exists($document)) {
                        Storage::disk('public')->delete($document);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to delete report files: ' . $e->getMessage());
            // Don't throw exception - continue with deletion
        }
    }

    /**
     * Send report notifications
     */
    private function sendReportNotifications(SecurityReport $report, $sender)
    {
        try {
            $recipients = [];
            
            // Add assignee if exists
            if ($report->assignee) {
                $recipients[] = $report->assignee;
            }
            
            // Add reporter if different from sender
            if ($report->reporter && $report->reporter->id !== $sender->id) {
                $recipients[] = $report->reporter;
            }
            
            // Add security admins
            $admins = User::whereIn('type', [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN])
                ->where('status', User::STATUS_ACTIVE)
                ->get();
            
            foreach ($admins as $admin) {
                if (!collect($recipients)->contains('id', $admin->id)) {
                    $recipients[] = $admin;
                }
            }
            
            // Send notifications
            foreach ($recipients as $recipient) {
                $this->sendIndividualNotification($report, $recipient, $sender);
            }
            
            Log::info('Report notifications sent', [
                'report_id' => $report->id,
                'recipient_count' => count($recipients),
                'sent_by' => $sender->id,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send report notifications: ' . $e->getMessage());
        }
    }

    /**
     * Send individual notification
     */
    private function sendIndividualNotification(SecurityReport $report, $recipient, $sender)
    {
        try {
            $subject = "New Security Report: {$report->title}";
            $message = "🔔 *New Security Report Notification*\n\n";
            $message .= "A new security report has been created.\n\n";
            $message .= "📋 *Report Details:*\n";
            $message .= "• Report #: {$report->report_number}\n";
            $message .= "• Title: {$report->title}\n";
            $message .= "• Type: {$report->report_type_label}\n";
            $message .= "• Priority: {$report->priority_label}\n";
            $message .= "• Location: {$report->location}\n";
            $message .= "• Reported by: {$report->reporter->name}\n";
            $message .= "• Incident Date: {$report->incident_date_formatted}\n\n";
            
            if ($report->assignee && $report->assignee->id === $recipient->id) {
                $message .= "👤 *You have been assigned to investigate this report.*\n\n";
            }
            
            $message .= "📊 *Quick Actions:*\n";
            $message .= "• View Report: " . route('admin.security-reports.show', $report->id) . "\n";
            $message .= "• Update Status: " . route('admin.security-reports.edit', $report->id) . "\n\n";
            
            $message .= "⚠️ *Priority Level: " . strtoupper($report->priority) . "*\n";
            if (in_array($report->priority, ['high', 'critical'])) {
                $message .= "This report requires immediate attention!\n\n";
            }
            
            $message .= "Thank you,\nSecurity Management System";

            // Use multi-channel service
            $this->multiChannelService->sendMessage(
                $recipient,
                $message,
                'security_report_notification',
                [
                    'report_id' => $report->id,
                    'report_number' => $report->report_number,
                    'priority' => $report->priority,
                    'sender_id' => $sender->id,
                    'sender_name' => $sender->name,
                    'is_assignment' => $report->assignee && $report->assignee->id === $recipient->id,
                ]
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to send individual notification: ' . $e->getMessage(), [
                'recipient_id' => $recipient->id,
                'report_id' => $report->id,
            ]);
        }
    }

    /**
     * Send report update notifications
     */
    private function sendReportUpdateNotifications(SecurityReport $report, $sender)
    {
        try {
            $recipients = [];
            
            // Add assignee if exists
            if ($report->assignee) {
                $recipients[] = $report->assignee;
            }
            
            // Add reporter if different from sender
            if ($report->reporter && $report->reporter->id !== $sender->id) {
                $recipients[] = $report->reporter;
            }
            
            // Add involved personnel
            if ($report->personnel_involved) {
                $involvedPersonnel = User::whereIn('id', $report->personnel_involved)->get();
                foreach ($involvedPersonnel as $person) {
                    if (!collect($recipients)->contains('id', $person->id)) {
                        $recipients[] = $person;
                    }
                }
            }
            
            // Send update notifications
            foreach ($recipients as $recipient) {
                $this->sendUpdateNotification($report, $recipient, $sender);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to send update notifications: ' . $e->getMessage());
        }
    }

    /**
     * Send update notification
     */
    private function sendUpdateNotification(SecurityReport $report, $recipient, $sender)
    {
        try {
            $subject = "Security Report Updated: {$report->title}";
            $message = "🔔 *Security Report Update*\n\n";
            $message .= "A security report has been updated.\n\n";
            $message .= "📋 *Report Details:*\n";
            $message .= "• Report #: {$report->report_number}\n";
            $message .= "• Title: {$report->title}\n";
            $message .= "• Status: {$report->status_label}\n";
            $message .= "• Priority: {$report->priority_label}\n";
            $message .= "• Updated by: {$sender->name}\n";
            $message .= "• Update Time: " . now()->format('M j, Y g:i A') . "\n\n";
            
            $message .= "🔗 *View Report:* " . route('admin.security-reports.show', $report->id) . "\n\n";
            
            if ($report->requires_followup && $report->followup_date) {
                $message .= "📅 *Follow-up Required:* " . $report->followup_date->format('M j, Y') . "\n\n";
            }
            
            $message .= "Thank you,\nSecurity Management System";

            // Use multi-channel service
            $this->multiChannelService->sendMessage(
                $recipient,
                $message,
                'security_report_update',
                [
                    'report_id' => $report->id,
                    'report_number' => $report->report_number,
                    'updated_by' => $sender->id,
                    'update_type' => 'manual_update',
                ]
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to send update notification: ' . $e->getMessage());
        }
    }

    /**
     * Send assignment notification
     */
    private function sendAssignmentNotification(SecurityReport $report, $sender)
    {
        try {
            if (!$report->assignee) {
                return;
            }
            
            $subject = "You've been assigned to investigate a security report";
            $message = "👤 *Assignment Notification*\n\n";
            $message .= "You have been assigned to investigate a security report.\n\n";
            $message .= "📋 *Report Details:*\n";
            $message .= "• Report #: {$report->report_number}\n";
            $message .= "• Title: {$report->title}\n";
            $message .= "• Priority: {$report->priority_label}\n";
            $message .= "• Location: {$report->location}\n";
            $message .= "• Incident Date: {$report->incident_date_formatted}\n";
            $message .= "• Assigned by: {$sender->name}\n\n";
            
            $message .= "🔍 *Investigation Required:*\n";
            $message .= "Please review the report details and begin your investigation.\n\n";
            
            $message .= "🔗 *View Report:* " . route('admin.security-reports.show', $report->id) . "\n";
            $message .= "✏️ *Update Report:* " . route('admin.security-reports.edit', $report->id) . "\n\n";
            
            if (in_array($report->priority, ['high', 'critical'])) {
                $message .= "⚠️ *URGENT:* This is a high priority report requiring immediate attention!\n\n";
            }
            
            $message .= "📅 *Expected Timeline:*\n";
            $message .= "• Initial review: Within 24 hours\n";
            $message .= "• Investigation completion: 3-5 business days\n";
            $message .= "• Report submission: Upon investigation completion\n\n";
            
            $message .= "For any questions or assistance, contact the security department.\n\n";
            $message .= "Thank you,\nSecurity Management System";

            // Use multi-channel service
            $this->multiChannelService->sendMessage(
                $report->assignee,
                $message,
                'security_report_assignment',
                [
                    'report_id' => $report->id,
                    'report_number' => $report->report_number,
                    'priority' => $report->priority,
                    'assigned_by' => $sender->id,
                    'is_high_priority' => in_array($report->priority, ['high', 'critical']),
                ]
            );
            
            Log::info('Assignment notification sent', [
                'report_id' => $report->id,
                'assignee_id' => $report->assignee->id,
                'assigned_by' => $sender->id,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send assignment notification: ' . $e->getMessage());
        }
    }

    /**
     * Get report activities
     */
    private function getReportActivities(SecurityReport $report)
    {
        // This would typically come from an activity log model/table
        // For now, return a simulated activity log
        
        $activities = [];
        
        // Report creation
        $activities[] = [
            'type' => 'created',
            'description' => 'Report created',
            'user' => $report->reporter,
            'timestamp' => $report->created_at,
            'details' => 'Initial report submission',
        ];
        
        // Status changes
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
                'details' => $report->verification_notes ?? 'Verified by administrator',
            ];
        }
        
        if ($report->resolved_at) {
            $activities[] = [
                'type' => 'resolved',
                'description' => 'Report resolved',
                'user' => null, // Would be the resolver
                'timestamp' => $report->resolved_at,
                'details' => $report->resolution_details ?? 'Incident resolved',
            ];
        }
        
        if ($report->closed_at && $report->closer) {
            $activities[] = [
                'type' => 'closed',
                'description' => 'Report closed',
                'user' => $report->closer,
                'timestamp' => $report->closed_at,
                'details' => $report->closure_notes ?? 'Case closed',
            ];
        }
        
        // Sort by timestamp
        usort($activities, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });
        
        return $activities;
    }

    /**
     * Get context statistics
     */
    private function getContextStatistics(SecurityReport $report)
    {
        // Get statistics for similar reports
        $similarReports = SecurityReport::where('category', $report->category)
            ->orWhere('security_post_id', $report->security_post_id)
            ->orWhere('location', 'like', '%' . $report->location . '%')
            ->where('id', '!=', $report->id)
            ->get();
        
        $totalSimilar = $similarReports->count();
        $resolvedSimilar = $similarReports->where('status', SecurityReport::STATUS_RESOLVED)->count();
        $avgResolutionSimilar = $similarReports->whereNotNull('resolved_at')
            ->avg(function($r) {
                return $r->incident_date->diffInHours($r->resolved_at);
            });
        
        return [
            'total_similar' => $totalSimilar,
            'resolved_similar' => $resolvedSimilar,
            'resolution_rate_similar' => $totalSimilar > 0 ? round(($resolvedSimilar / $totalSimilar) * 100, 2) : 0,
            'avg_resolution_hours_similar' => round($avgResolutionSimilar ?? 0, 2),
        ];
    }

    /**
     * Export to CSV
     */
    private function exportToCsv($reports, $filename, $includeDetails = false)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($reports, $includeDetails) {
            $file = fopen('php://output', 'w');
            
            // Headers
            $headers = [
                'Report Number',
                'Title',
                'Type',
                'Category',
                'Priority',
                'Status',
                'Incident Date',
                'Location',
                'Digital Address',
                'Reporter',
                'Assignee',
                'Security Post',
                'Severity Level',
                'Estimated Damage',
                'Personnel Injured',
                'Personnel Deceased',
                'Civilians Affected',
                'Created At',
                'Resolved At',
                'Closed At',
            ];
            
            if ($includeDetails) {
                $headers = array_merge($headers, [
                    'Description',
                    'Impact Assessment',
                    'Immediate Actions',
                    'Recommendations',
                    'Resolution Details',
                    'Verification Notes',
                    'Closure Notes',
                ]);
            }
            
            fputcsv($file, $headers);
            
            // Data
            foreach ($reports as $report) {
                $row = [
                    $report->report_number,
                    $report->title,
                    $report->report_type_label,
                    $report->category_label,
                    $report->priority_label,
                    $report->status_label,
                    $report->incident_date?->format('Y-m-d H:i:s'),
                    $report->location,
                    $report->digital_address,
                    $report->reporter?->name,
                    $report->assignee?->name,
                    $report->securityPost?->name,
                    $report->severity_level,
                    $report->estimated_damage,
                    $report->personnel_injured,
                    $report->personnel_deceased,
                    $report->civilians_affected,
                    $report->created_at->format('Y-m-d H:i:s'),
                    $report->resolved_at?->format('Y-m-d H:i:s'),
                    $report->closed_at?->format('Y-m-d H:i:s'),
                ];
                
                if ($includeDetails) {
                    $row = array_merge($row, [
                        $report->description,
                        $report->impact_assessment,
                        $report->immediate_actions,
                        $report->recommendations,
                        $report->resolution_details,
                        $report->verification_notes,
                        $report->closure_notes,
                    ]);
                }
                
                fputcsv($file, $row);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export to Excel
     */
    private function exportToExcel($reports, $filename, $includeDetails = false)
    {
        // This would use a package like Maatwebsite/Laravel-Excel
        // For now, return CSV as fallback
        return $this->exportToCsv($reports, str_replace('.xlsx', '.csv', $filename), $includeDetails);
    }

    /**
     * Export to PDF
     */
    private function exportToPdf($reports, $filename, $includeDetails = false)
    {
        // This would use a package like barryvdh/laravel-dompdf
        // For now, return CSV as fallback
        return $this->exportToCsv($reports, str_replace('.pdf', '.csv', $filename), $includeDetails);
    }
}