<?php

namespace App\Http\Controllers\Contractor;

use App\Http\Controllers\Controller;
use App\Models\ConstructionContract;
use App\Models\ConstructionWorker;
use App\Models\WorkerBadge;
use App\Models\User;
use App\Services\BadgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ConstructionWorkerController extends Controller
{
    protected $badgeService;

    public function __construct(BadgeService $badgeService)
    {
        $this->badgeService = $badgeService;
    }

    /**
     * Display a listing of workers for a contract
     */
    public function index(Request $request, ConstructionContract $contract)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            abort(403, 'Unauthorized to view workers for this contract.');
        }

        $query = ConstructionWorker::where('contract_id', $contract->id)
            ->with(['addedBy', 'badge']);

        // Apply filters
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('trade') && $request->trade) {
            $query->where('trade', $request->trade);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                  ->orWhere('job_title', 'LIKE', "%{$search}%")
                  ->orWhere('trade', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'full_name');
        $sortDirection = $request->get('direction', 'asc');
        $allowedSorts = ['full_name', 'job_title', 'trade', 'status', 'daily_rate', 'start_date', 'end_date'];
        
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        }

        $workers = $query->paginate(15)->withQueryString();

        // Statistics
        $stats = [
            'total' => ConstructionWorker::where('contract_id', $contract->id)->count(),
            'active' => ConstructionWorker::where('contract_id', $contract->id)
                ->where('status', ConstructionWorker::STATUS_ACTIVE)->count(),
            'inactive' => ConstructionWorker::where('contract_id', $contract->id)
                ->where('status', ConstructionWorker::STATUS_INACTIVE)->count(),
            'completed' => ConstructionWorker::where('contract_id', $contract->id)
                ->where('status', ConstructionWorker::STATUS_COMPLETED)->count(),
            'terminated' => ConstructionWorker::where('contract_id', $contract->id)
                ->where('status', ConstructionWorker::STATUS_TERMINATED)->count(),
            'badge_sent' => WorkerBadge::whereHas('worker', function($q) use ($contract) {
                $q->where('contract_id', $contract->id);
            })->whereNotNull('email_sent_at')->count(),
        ];

        // Get unique trades for filter dropdown
        $trades = ConstructionWorker::where('contract_id', $contract->id)
            ->select('trade')
            ->distinct()
            ->whereNotNull('trade')
            ->pluck('trade')
            ->toArray();

        return view('contractor.workers.grobal', compact('contract', 'workers', 'stats', 'trades'));
    }

    /**
     * Show the form for creating a new worker
     */
    public function create(ConstructionContract $contract)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            abort(403, 'Unauthorized to add workers to this contract.');
        }

        // Only allow adding workers to active contracts
        if (!in_array($contract->status, [
            ConstructionContract::STATUS_APPROVED,
            ConstructionContract::STATUS_IN_PROGRESS
        ])) {
            return redirect()->route('contractor.contracts.show', $contract)
                ->with('error', 'Workers can only be added to active or approved contracts.');
        }

        return view('contractor.workers.create', compact('contract'));
    }

    /**
     * Store a newly created worker in storage
     */
    public function store(Request $request, ConstructionContract $contract)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            abort(403, 'Unauthorized to add workers to this contract.');
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'id_number' => 'nullable|string|max:50',
            'job_title' => 'nullable|string|max:255',
            'trade' => 'nullable|string|max:100',
            'specialization' => 'nullable|string|max:255',
            'skills' => 'nullable|array',
            'skills.*' => 'string|max:100',
            'service_description' => 'nullable|string|max:1000',
            'daily_rate' => 'nullable|numeric|min:0',
            'contract_rate' => 'nullable|numeric|min:0',
            'documents' => 'nullable|array',
            'status' => ['required', Rule::in([
                ConstructionWorker::STATUS_ACTIVE,
                ConstructionWorker::STATUS_INACTIVE
            ])],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'notes' => 'nullable|string|max:1000',
            'send_badge' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            // 🔧 FIX: Only use fields that exist in the database
            $data = $request->only([
                'full_name',
                'phone',
                'email',
                'address',
                'id_number',
                'job_title',
                'trade',
                'specialization',
                'skills',
                'service_description',
                'daily_rate',
                'contract_rate',
                'status',
                'start_date',
                'end_date',
                'notes'
            ]);
            
            $data['contract_id'] = $contract->id;
            $data['added_by'] = auth()->id();

            // Handle skills as JSON
            if (isset($data['skills']) && is_array($data['skills'])) {
                $data['skills'] = array_filter($data['skills']);
            }

            // Set default start date if not provided
            if (empty($data['start_date']) && $data['status'] === ConstructionWorker::STATUS_ACTIVE) {
                $data['start_date'] = now();
            }

            // Create worker
            $worker = ConstructionWorker::create($data);

            // ✅ Generate and send badge if worker has email and is active
            $badgeSent = false;
            if ($worker->email && $worker->status === ConstructionWorker::STATUS_ACTIVE) {
                try {
                    $badge = $this->badgeService->generateBadge($worker);
                    $this->badgeService->sendBadgeEmail($badge);
                    $badgeSent = true;
                    
                    Log::info('Badge sent to new worker', [
                        'worker_id' => $worker->id,
                        'email' => $worker->email,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to send badge for new worker: ' . $e->getMessage(), [
                        'worker_id' => $worker->id,
                    ]);
                }
            }

            // Log activity
            try {
                if (method_exists($contract, 'logActivity')) {
                    $contract->logActivity('worker_added', "Worker '{$worker->full_name}' added to contract" . 
                        ($badgeSent ? ' with badge sent' : ''));
                }
            } catch (\Exception $e) {
                Log::warning('Failed to log worker activity: ' . $e->getMessage());
            }

            DB::commit();

            $message = "Worker '{$worker->full_name}' added successfully to contract '{$contract->contract_number}'!";
            if ($badgeSent) {
                $message .= " Badge has been sent to {$worker->email}.";
            } elseif ($worker->email && $worker->status === ConstructionWorker::STATUS_ACTIVE) {
                $message .= " Failed to send badge. You can resend it from the worker list.";
            }

            return redirect()->route('contractor.workers.global')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create worker: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'contractor_id' => auth()->id(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to create worker. Please try again.')
                ->withInput();
        }
    }

    /**
     * Display the specified worker
     * ✅ UPDATED: Returns JSON for AJAX requests
     */
    public function show(ConstructionContract $contract, ConstructionWorker $worker)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            abort(403, 'Unauthorized to view this worker.');
        }

        if ($worker->contract_id !== $contract->id) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'Worker not found'], 404);
            }
            abort(404, 'Worker not found on this contract.');
        }

        $worker->load(['addedBy', 'contract', 'badge']);

        // ✅ Check if request expects JSON (AJAX request from modal)
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'worker' => [
                    'id' => $worker->id,
                    'full_name' => $worker->full_name,
                    'phone' => $worker->phone,
                    'email' => $worker->email,
                    'address' => $worker->address,
                    'id_number' => $worker->id_number,
                    'job_title' => $worker->job_title,
                    'trade' => $worker->trade,
                    'specialization' => $worker->specialization,
                    'skills' => $worker->skills ?? [],
                    'service_description' => $worker->service_description,
                    'daily_rate' => $worker->daily_rate,
                    'contract_rate' => $worker->contract_rate,
                    'status' => $worker->status,
                    'start_date' => $worker->start_date ? $worker->start_date->toISOString() : null,
                    'end_date' => $worker->end_date ? $worker->end_date->toISOString() : null,
                    'created_at' => $worker->created_at ? $worker->created_at->toISOString() : null,
                    'contract_id' => $worker->contract_id,
                    'is_assigned_to_site' => (bool) $worker->is_assigned_to_site,
                    'assigned_to_site_at' => $worker->assigned_to_site_at ? $worker->assigned_to_site_at->toISOString() : null,
                    'contract' => $worker->contract ? [
                        'id' => $worker->contract->id,
                        'contract_number' => $worker->contract->contract_number,
                        'title' => $worker->contract->title,
                        'location' => $worker->contract->location ?? 'Not specified',
                    ] : null,
                    'added_by' => $worker->addedBy ? [
                        'id' => $worker->addedBy->id,
                        'name' => $worker->addedBy->name,
                    ] : null,
                    'badge' => $worker->badge ? [
                        'badge_number' => $worker->badge->badge_number,
                        'status' => $worker->badge->status,
                        'valid_from' => $worker->badge->valid_from ? $worker->badge->valid_from->toISOString() : null,
                        'valid_until' => $worker->badge->valid_until ? $worker->badge->valid_until->toISOString() : null,
                        'is_expired' => $worker->badge->is_expired,
                        'days_until_expiry' => $worker->badge->days_until_expiry,
                        'verification_count' => $worker->badge->verification_count,
                        'last_verified_at' => $worker->badge->last_verified_at ? $worker->badge->last_verified_at->toISOString() : null,
                        'qr_code' => $worker->badge->qr_code,
                        'verification_url' => $worker->badge->getVerificationUrl(),
                    ] : null,
                ]
            ]);
        }

        // ✅ Return view for normal browser requests
        return view('contractor.workers.show', compact('contract', 'worker'));
    }

    /**
     * Update the specified worker in storage
     */
    public function update(Request $request, ConstructionContract $contract, ConstructionWorker $worker)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            abort(403, 'Unauthorized to update this worker.');
        }

        if ($worker->contract_id !== $contract->id) {
            abort(404, 'Worker not found on this contract.');
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'id_number' => 'nullable|string|max:50',
            'job_title' => 'nullable|string|max:255',
            'trade' => 'nullable|string|max:100',
            'specialization' => 'nullable|string|max:255',
            'skills' => 'nullable|array',
            'skills.*' => 'string|max:100',
            'service_description' => 'nullable|string|max:1000',
            'daily_rate' => 'nullable|numeric|min:0',
            'contract_rate' => 'nullable|numeric|min:0',
            'documents' => 'nullable|array',
            'status' => ['required', Rule::in([
                ConstructionWorker::STATUS_ACTIVE,
                ConstructionWorker::STATUS_INACTIVE,
                ConstructionWorker::STATUS_COMPLETED,
                ConstructionWorker::STATUS_TERMINATED
            ])],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'notes' => 'nullable|string|max:1000',
            'send_badge' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            // 🔧 FIX: Only use fields that exist in the database
            $data = $request->only([
                'full_name',
                'phone',
                'email',
                'address',
                'id_number',
                'job_title',
                'trade',
                'specialization',
                'skills',
                'service_description',
                'daily_rate',
                'contract_rate',
                'status',
                'start_date',
                'end_date',
                'notes'
            ]);

            // Handle skills as JSON
            if (isset($data['skills']) && is_array($data['skills'])) {
                $data['skills'] = array_filter($data['skills']);
            }

            // Store old status for badge handling
            $oldStatus = $worker->status;
            $oldEmail = $worker->email;

            // Update worker
            $worker->update($data);

            // ✅ Handle badge generation/update
            $badgeSent = false;
            if ($worker->email && $worker->status === ConstructionWorker::STATUS_ACTIVE) {
                try {
                    // Generate or update badge
                    $badge = $this->badgeService->generateBadge($worker);
                    
                    // Send badge if:
                    // 1. Worker was just activated (status changed from inactive to active)
                    // 2. Email was changed
                    // 3. Explicitly requested via send_badge flag
                    if (($oldStatus !== ConstructionWorker::STATUS_ACTIVE && $worker->status === ConstructionWorker::STATUS_ACTIVE) ||
                        ($oldEmail !== $worker->email && $worker->email) ||
                        $request->boolean('send_badge', false)) {
                        
                        $this->badgeService->sendBadgeEmail($badge);
                        $badgeSent = true;
                        
                        Log::info('Badge sent to updated worker', [
                            'worker_id' => $worker->id,
                            'email' => $worker->email,
                            'reason' => $oldStatus !== $worker->status ? 'status_changed' : 
                                       ($oldEmail !== $worker->email ? 'email_changed' : 'manual'),
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to send badge for updated worker: ' . $e->getMessage(), [
                        'worker_id' => $worker->id,
                    ]);
                }
            } elseif ($worker->status !== ConstructionWorker::STATUS_ACTIVE && $worker->badge) {
                // If worker is no longer active, deactivate badge
                $worker->badge->update(['is_active' => false]);
                Log::info('Badge deactivated for inactive worker', [
                    'worker_id' => $worker->id,
                    'status' => $worker->status,
                ]);
            }

            // Log activity
            try {
                if (method_exists($contract, 'logActivity')) {
                    $contract->logActivity('worker_updated', "Worker '{$worker->full_name}' updated" . 
                        ($badgeSent ? ' with badge sent' : ''));
                }
            } catch (\Exception $e) {
                Log::warning('Failed to log worker activity: ' . $e->getMessage());
            }

            DB::commit();

            $message = "Worker '{$worker->full_name}' updated successfully!";
            if ($badgeSent) {
                $message .= " Badge has been sent to {$worker->email}.";
            }

            return redirect()->route('contractor.workers.global')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update worker: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'worker_id' => $worker->id,
                'contractor_id' => auth()->id(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update worker. Please try again.')
                ->withInput();
        }
    }

    /**
     * Remove the specified worker from storage
     */
    public function destroy(ConstructionContract $contract, ConstructionWorker $worker)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            abort(403, 'Unauthorized to delete this worker.');
        }

        if ($worker->contract_id !== $contract->id) {
            abort(404, 'Worker not found on this contract.');
        }

        // Prevent deletion of active workers
        if ($worker->status === ConstructionWorker::STATUS_ACTIVE) {
            return redirect()->back()
                ->with('error', 'Cannot delete an active worker. Please terminate or complete their work first.');
        }

        DB::beginTransaction();

        try {
            $workerName = $worker->full_name;
            
            // ✅ Delete associated badge if exists
            if ($worker->badge) {
                $worker->badge->delete();
                Log::info('Badge deleted with worker', [
                    'worker_id' => $worker->id,
                    'badge_number' => $worker->badge->badge_number,
                ]);
            }
            
            $worker->delete();

            // Log activity
            try {
                if (method_exists($contract, 'logActivity')) {
                    $contract->logActivity('worker_deleted', "Worker '{$workerName}' removed from contract");
                }
            } catch (\Exception $e) {
                Log::warning('Failed to log worker activity: ' . $e->getMessage());
            }

            DB::commit();

            return redirect()->route('contractor.workers.global')
                ->with('success', "Worker '{$workerName}' deleted successfully!");

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to delete worker: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'worker_id' => $worker->id,
                'contractor_id' => auth()->id(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to delete worker. Please try again.');
        }
    }

    /**
     * Activate a worker
     */
    public function activate(ConstructionContract $contract, ConstructionWorker $worker)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            abort(403, 'Unauthorized to activate this worker.');
        }

        if ($worker->contract_id !== $contract->id) {
            abort(404, 'Worker not found on this contract.');
        }

        if (!$worker->canBeActive()) {
            return redirect()->back()
                ->with('error', 'This worker cannot be activated.');
        }

        DB::beginTransaction();

        try {
            $worker->activate();
            $worker->start_date = now();
            $worker->save();

            // ✅ Generate and send badge on activation
            $badgeSent = false;
            if ($worker->email) {
                try {
                    $badge = $this->badgeService->generateBadge($worker);
                    $this->badgeService->sendBadgeEmail($badge);
                    $badgeSent = true;
                    
                    Log::info('Badge sent on worker activation', [
                        'worker_id' => $worker->id,
                        'email' => $worker->email,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to send badge on activation: ' . $e->getMessage(), [
                        'worker_id' => $worker->id,
                    ]);
                }
            }

            // Log activity
            try {
                if (method_exists($contract, 'logActivity')) {
                    $contract->logActivity('worker_activated', "Worker '{$worker->full_name}' activated" . 
                        ($badgeSent ? ' with badge sent' : ''));
                }
            } catch (\Exception $e) {
                Log::warning('Failed to log worker activity: ' . $e->getMessage());
            }

            DB::commit();

            $message = "Worker '{$worker->full_name}' activated successfully!";
            if ($badgeSent) {
                $message .= " Badge has been sent to {$worker->email}.";
            } elseif ($worker->email) {
                $message .= " Failed to send badge. You can resend it from the worker list.";
            }

            return redirect()->route('contractor.workers.global')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to activate worker: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'worker_id' => $worker->id,
                'contractor_id' => auth()->id(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to activate worker. Please try again.');
        }
    }

    /**
     * Terminate a worker
     */
    public function terminate(Request $request, ConstructionContract $contract, ConstructionWorker $worker)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            abort(403, 'Unauthorized to terminate this worker.');
        }

        if ($worker->contract_id !== $contract->id) {
            abort(404, 'Worker not found on this contract.');
        }

        if (!$worker->canBeTerminated()) {
            return redirect()->back()
                ->with('error', 'This worker cannot be terminated.');
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator);
        }

        DB::beginTransaction();

        try {
            $worker->terminate($request->reason);

            // ✅ Deactivate badge on termination
            if ($worker->badge) {
                $worker->badge->update([
                    'is_active' => false,
                    'notes' => 'Terminated: ' . ($request->reason ?? 'No reason provided'),
                ]);
                Log::info('Badge deactivated on worker termination', [
                    'worker_id' => $worker->id,
                    'badge_number' => $worker->badge->badge_number,
                ]);
            }

            // Log activity
            try {
                if (method_exists($contract, 'logActivity')) {
                    $contract->logActivity('worker_terminated', "Worker '{$worker->full_name}' terminated. Reason: " . ($request->reason ?? 'No reason provided') . " - Badge deactivated");
                }
            } catch (\Exception $e) {
                Log::warning('Failed to log worker activity: ' . $e->getMessage());
            }

            DB::commit();

            return redirect()->route('contractor.workers.global')
                ->with('success', "Worker '{$worker->full_name}' terminated successfully! Badge has been deactivated.");

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to terminate worker: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'worker_id' => $worker->id,
                'contractor_id' => auth()->id(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to terminate worker. Please try again.');
        }
    }

    /**
     * Complete a worker's work
     */
    public function complete(ConstructionContract $contract, ConstructionWorker $worker)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            abort(403, 'Unauthorized to complete this worker.');
        }

        if ($worker->contract_id !== $contract->id) {
            abort(404, 'Worker not found on this contract.');
        }

        if ($worker->status !== ConstructionWorker::STATUS_ACTIVE) {
            return redirect()->back()
                ->with('error', 'Only active workers can be marked as completed.');
        }

        DB::beginTransaction();

        try {
            $worker->completeWork();

            // ✅ Deactivate badge on completion
            if ($worker->badge) {
                $worker->badge->update([
                    'is_active' => false,
                    'notes' => 'Work completed',
                ]);
                Log::info('Badge deactivated on worker completion', [
                    'worker_id' => $worker->id,
                    'badge_number' => $worker->badge->badge_number,
                ]);
            }

            // Log activity
            try {
                if (method_exists($contract, 'logActivity')) {
                    $contract->logActivity('worker_completed', "Worker '{$worker->full_name}' completed work - Badge deactivated");
                }
            } catch (\Exception $e) {
                Log::warning('Failed to log worker activity: ' . $e->getMessage());
            }

            DB::commit();

            return redirect()->route('contractor.workers.global')
                ->with('success', "Worker '{$worker->full_name}' marked as completed! Badge has been deactivated.");

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to complete worker: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'worker_id' => $worker->id,
                'contractor_id' => auth()->id(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to complete worker. Please try again.');
        }
    }

    /**
     * Send badge to a specific worker
     * ✅ FIXED: Returns JSON for AJAX requests
     */
    public function sendBadge(ConstructionContract $contract, ConstructionWorker $worker)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to send badge to this worker.'
                ], 403);
            }
            abort(403, 'Unauthorized to send badge to this worker.');
        }

        if ($worker->contract_id !== $contract->id) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Worker not found on this contract.'
                ], 404);
            }
            abort(404, 'Worker not found on this contract.');
        }

        if (!$worker->email) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Worker does not have an email address.'
                ], 400);
            }
            return redirect()->back()
                ->with('error', 'Worker does not have an email address.');
        }

        if ($worker->status !== ConstructionWorker::STATUS_ACTIVE) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Badge can only be sent to active workers.'
                ], 400);
            }
            return redirect()->back()
                ->with('error', 'Badge can only be sent to active workers.');
        }

        try {
            // Generate badge
            $badge = $this->badgeService->generateBadge($worker);
            
            // Send email with badge
            $this->badgeService->sendBadgeEmail($badge);

            // Log activity
            try {
                if (method_exists($contract, 'logActivity')) {
                    $contract->logActivity('badge_sent', "Badge sent to worker '{$worker->full_name}'");
                }
            } catch (\Exception $e) {
                Log::warning('Failed to log worker activity: ' . $e->getMessage());
            }

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Badge sent successfully to {$worker->full_name} at {$worker->email}!"
                ]);
            }

            return redirect()->back()
                ->with('success', "Badge sent successfully to {$worker->full_name} at {$worker->email}!");

        } catch (\Exception $e) {
            Log::error('Failed to send badge: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'worker_id' => $worker->id,
                'contractor_id' => auth()->id(),
                'error_trace' => $e->getTraceAsString()
            ]);

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send badge: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to send badge. Please try again.');
        }
    }

    /**
     * Resend badge to a worker
     * ✅ FIXED: Returns JSON for AJAX requests
     */
    public function resendBadge(ConstructionContract $contract, ConstructionWorker $worker)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to resend badge to this worker.'
                ], 403);
            }
            abort(403, 'Unauthorized to resend badge to this worker.');
        }

        if ($worker->contract_id !== $contract->id) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Worker not found on this contract.'
                ], 404);
            }
            abort(404, 'Worker not found on this contract.');
        }

        if (!$worker->email) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Worker does not have an email address.'
                ], 400);
            }
            return redirect()->back()
                ->with('error', 'Worker does not have an email address.');
        }

        if ($worker->status !== ConstructionWorker::STATUS_ACTIVE) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Badge can only be sent to active workers.'
                ], 400);
            }
            return redirect()->back()
                ->with('error', 'Badge can only be sent to active workers.');
        }

        try {
            // Get existing badge or create new
            $badge = WorkerBadge::where('construction_worker_id', $worker->id)->first();
            
            if (!$badge) {
                $badge = $this->badgeService->generateBadge($worker);
            } else {
                // Regenerate QR code for security
                $badge->generateQRCode();
                $badge->save();
            }
            
            // Update email if changed
            if ($badge->email_sent_to !== $worker->email) {
                $badge->email_sent_to = $worker->email;
                $badge->save();
            }
            
            // Send email with badge
            $this->badgeService->sendBadgeEmail($badge);

            // Log activity
            try {
                if (method_exists($contract, 'logActivity')) {
                    $contract->logActivity('badge_resent', "Badge resent to worker '{$worker->full_name}'");
                }
            } catch (\Exception $e) {
                Log::warning('Failed to log worker activity: ' . $e->getMessage());
            }

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Badge resent successfully to {$worker->full_name} at {$worker->email}!"
                ]);
            }

            return redirect()->back()
                ->with('success', "Badge resent successfully to {$worker->full_name} at {$worker->email}!");

        } catch (\Exception $e) {
            Log::error('Failed to resend badge: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'worker_id' => $worker->id,
                'contractor_id' => auth()->id(),
                'error_trace' => $e->getTraceAsString()
            ]);

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to resend badge: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to resend badge. Please try again.');
        }
    }

    /**
     * Get badge status for a worker
     */
    public function getBadgeStatus(ConstructionContract $contract, ConstructionWorker $worker)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($worker->contract_id !== $contract->id) {
            return response()->json(['error' => 'Worker not found'], 404);
        }

        $badge = WorkerBadge::where('construction_worker_id', $worker->id)->first();

        if (!$badge) {
            return response()->json([
                'success' => false,
                'message' => 'No badge found for this worker'
            ]);
        }

        return response()->json([
            'success' => true,
            'badge' => $this->badgeService->getBadgeStatus($badge)
        ]);
    }

    /**
     * Bulk send badges to multiple workers
     */
    public function bulkSendBadges(Request $request, ConstructionContract $contract)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            abort(403, 'Unauthorized to send badges.');
        }

        $validator = Validator::make($request->all(), [
            'worker_ids' => 'required|array',
            'worker_ids.*' => 'exists:construction_workers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $workers = ConstructionWorker::whereIn('id', $request->worker_ids)
            ->where('contract_id', $contract->id)
            ->whereNotNull('email')
            ->where('status', ConstructionWorker::STATUS_ACTIVE)
            ->get();

        $sent = 0;
        $failed = [];

        foreach ($workers as $worker) {
            try {
                $badge = $this->badgeService->generateBadge($worker);
                $this->badgeService->sendBadgeEmail($badge);
                $sent++;
                
                Log::info('Bulk badge sent', [
                    'worker_id' => $worker->id,
                    'email' => $worker->email,
                ]);
            } catch (\Exception $e) {
                $failed[] = [
                    'worker' => $worker->full_name,
                    'id' => $worker->id,
                    'error' => $e->getMessage()
                ];
                Log::warning('Bulk badge failed', [
                    'worker_id' => $worker->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Log activity
        try {
            if (method_exists($contract, 'logActivity')) {
                $contract->logActivity('badges_bulk_sent', "{$sent} badges sent to workers");
            }
        } catch (\Exception $e) {
            Log::warning('Failed to log worker activity: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'sent' => $sent,
            'failed' => $failed,
            'message' => "Sent {$sent} badges successfully!" . (count($failed) > 0 ? " Failed: " . count($failed) . " workers." : "")
        ]);
    }

    /**
     * Get worker statistics for AJAX dashboard widgets
     */
    public function getStats(ConstructionContract $contract)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stats = [
            'total' => ConstructionWorker::where('contract_id', $contract->id)->count(),
            'active' => ConstructionWorker::where('contract_id', $contract->id)
                ->where('status', ConstructionWorker::STATUS_ACTIVE)->count(),
            'inactive' => ConstructionWorker::where('contract_id', $contract->id)
                ->where('status', ConstructionWorker::STATUS_INACTIVE)->count(),
            'completed' => ConstructionWorker::where('contract_id', $contract->id)
                ->where('status', ConstructionWorker::STATUS_COMPLETED)->count(),
            'terminated' => ConstructionWorker::where('contract_id', $contract->id)
                ->where('status', ConstructionWorker::STATUS_TERMINATED)->count(),
            'badge_sent' => WorkerBadge::whereHas('worker', function($q) use ($contract) {
                $q->where('contract_id', $contract->id);
            })->whereNotNull('email_sent_at')->count(),
            'by_trade' => ConstructionWorker::where('contract_id', $contract->id)
                ->where('status', ConstructionWorker::STATUS_ACTIVE)
                ->select('trade', DB::raw('COUNT(*) as count'))
                ->groupBy('trade')
                ->get()
                ->pluck('count', 'trade')
                ->toArray(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Get workers by trade
     */
    public function getByTrade(Request $request, ConstructionContract $contract, string $trade)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $workers = ConstructionWorker::where('contract_id', $contract->id)
            ->where('trade', $trade)
            ->where('status', ConstructionWorker::STATUS_ACTIVE)
            ->select('id', 'full_name', 'job_title', 'daily_rate')
            ->with('badge')
            ->get()
            ->map(function($worker) {
                return [
                    'id' => $worker->id,
                    'full_name' => $worker->full_name,
                    'job_title' => $worker->job_title,
                    'daily_rate' => $worker->daily_rate,
                    'has_badge' => $worker->badge ? true : false,
                    'badge_status' => $worker->badge ? $worker->badge->status : null,
                ];
            });

        return response()->json([
            'success' => true,
            'workers' => $workers,
        ]);
    }

    /**
     * Get available workers for assignment (not on any contract)
     */
    public function getAvailable()
    {
        // Get workers not assigned to any active contract
        $workers = ConstructionWorker::whereDoesntHave('contract', function($query) {
            $query->whereIn('status', [
                ConstructionContract::STATUS_APPROVED,
                ConstructionContract::STATUS_IN_PROGRESS
            ]);
        })
        ->where('status', ConstructionWorker::STATUS_ACTIVE)
        ->select('id', 'full_name', 'job_title', 'trade', 'daily_rate')
        ->with('badge')
        ->limit(50)
        ->get()
        ->map(function($worker) {
            return [
                'id' => $worker->id,
                'full_name' => $worker->full_name,
                'job_title' => $worker->job_title,
                'trade' => $worker->trade,
                'daily_rate' => $worker->daily_rate,
                'has_badge' => $worker->badge ? true : false,
            ];
        });

        return response()->json([
            'success' => true,
            'workers' => $workers,
        ]);
    }

    /**
     * Bulk assign workers to a contract
     */
    public function bulkAssign(Request $request, ConstructionContract $contract)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            abort(403, 'Unauthorized to assign workers to this contract.');
        }

        $validator = Validator::make($request->all(), [
            'worker_ids' => 'required|array',
            'worker_ids.*' => 'exists:construction_workers,id',
            'send_badges' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $count = 0;
            $badgeSent = 0;
            
            foreach ($request->worker_ids as $workerId) {
                $worker = ConstructionWorker::find($workerId);
                if ($worker && $worker->status === ConstructionWorker::STATUS_ACTIVE) {
                    $worker->contract_id = $contract->id;
                    $worker->save();
                    $count++;
                    
                    // Send badge if requested and worker has email
                    if ($request->boolean('send_badges', true) && $worker->email) {
                        try {
                            $badge = $this->badgeService->generateBadge($worker);
                            $this->badgeService->sendBadgeEmail($badge);
                            $badgeSent++;
                        } catch (\Exception $e) {
                            Log::warning('Failed to send badge during bulk assign: ' . $e->getMessage(), [
                                'worker_id' => $worker->id,
                            ]);
                        }
                    }
                }
            }

            // Log activity
            try {
                if (method_exists($contract, 'logActivity')) {
                    $contract->logActivity('workers_bulk_assigned', "{$count} workers assigned to contract. {$badgeSent} badges sent.");
                }
            } catch (\Exception $e) {
                Log::warning('Failed to log worker activity: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'count' => $count,
                'badges_sent' => $badgeSent,
                'message' => "{$count} worker(s) assigned successfully! {$badgeSent} badge(s) sent.",
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to bulk assign workers: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'contractor_id' => auth()->id(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to assign workers. Please try again.',
            ], 500);
        }
    }

    /**
     * Display a global listing of workers (across all contracts)
     */
    public function globalIndex(Request $request)
    {
        $userId = auth()->id();
        
        // Get all contracts for this contractor
        $contractIds = ConstructionContract::where('contractor_user_id', $userId)->pluck('id');
        
        $query = ConstructionWorker::whereIn('contract_id', $contractIds)
            ->with(['contract', 'addedBy', 'badge']);

        // Apply filters
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('trade') && $request->trade) {
            $query->where('trade', $request->trade);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                  ->orWhere('job_title', 'LIKE', "%{$search}%")
                  ->orWhere('trade', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // ✅ Filter by badge status
        if ($request->has('badge_status') && $request->badge_status) {
            switch ($request->badge_status) {
                case 'has_badge':
                    $query->whereHas('badge');
                    break;
                case 'no_badge':
                    $query->whereDoesntHave('badge');
                    break;
                case 'badge_sent':
                    $query->whereHas('badge', function($q) {
                        $q->whereNotNull('email_sent_at');
                    });
                    break;
                case 'badge_expired':
                    $query->whereHas('badge', function($q) {
                        $q->where('valid_until', '<=', now());
                    });
                    break;
                case 'badge_active':
                    $query->whereHas('badge', function($q) {
                        $q->where('is_active', true)
                          ->where('valid_until', '>', now());
                    });
                    break;
            }
        }

        // ✅ Filter by site assignment
        if ($request->has('site_assignment') && $request->site_assignment) {
            if ($request->site_assignment === 'assigned') {
                $query->where('is_assigned_to_site', true);
            } elseif ($request->site_assignment === 'unassigned') {
                $query->where(function($q) {
                    $q->where('is_assigned_to_site', false)
                      ->orWhereNull('is_assigned_to_site');
                });
            }
        }

        $workers = $query->paginate(15)->withQueryString();

        // Statistics
        $stats = [
            'total' => ConstructionWorker::whereIn('contract_id', $contractIds)->count(),
            'active' => ConstructionWorker::whereIn('contract_id', $contractIds)
                ->where('status', ConstructionWorker::STATUS_ACTIVE)->count(),
            'inactive' => ConstructionWorker::whereIn('contract_id', $contractIds)
                ->where('status', ConstructionWorker::STATUS_INACTIVE)->count(),
            'completed' => ConstructionWorker::whereIn('contract_id', $contractIds)
                ->where('status', ConstructionWorker::STATUS_COMPLETED)->count(),
            'terminated' => ConstructionWorker::whereIn('contract_id', $contractIds)
                ->where('status', ConstructionWorker::STATUS_TERMINATED)->count(),
            'badge_sent' => WorkerBadge::whereIn('construction_contract_id', $contractIds)
                ->whereNotNull('email_sent_at')
                ->count(),
            'badge_active' => WorkerBadge::whereIn('construction_contract_id', $contractIds)
                ->where('is_active', true)
                ->where('valid_until', '>', now())
                ->count(),
            'site_workers' => ConstructionWorker::whereIn('contract_id', $contractIds)
                ->where('is_assigned_to_site', true)
                ->count(),
        ];

        // Get unique trades
        $trades = ConstructionWorker::whereIn('contract_id', $contractIds)
            ->select('trade')
            ->distinct()
            ->whereNotNull('trade')
            ->pluck('trade')
            ->toArray();

        return view('contractor.workers.global', compact('workers', 'stats', 'trades'));
    }
    
    /**
     * Toggle site assignment for a worker
     */
    public function toggleSiteAssignment(Request $request, $contractId, $workerId)
    {
        // ✅ Authorization check
        $contract = ConstructionContract::findOrFail($contractId);
        if ($contract->contractor_user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to modify this worker.'
            ], 403);
        }

        $worker = ConstructionWorker::where('contract_id', $contractId)
            ->where('id', $workerId)
            ->firstOrFail();

        // Only active workers can be assigned to site
        if ($worker->status !== ConstructionWorker::STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Only active workers can be assigned to site.'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Toggle site assignment
            $worker->is_assigned_to_site = !$worker->is_assigned_to_site;
            if ($worker->is_assigned_to_site) {
                $worker->assigned_to_site_at = now();
                $message = 'Worker assigned to site successfully!';
            } else {
                $worker->assigned_to_site_at = null;
                $message = 'Worker removed from site successfully!';
            }
            
            $worker->save();

            // Log activity
            try {
                if (method_exists($contract, 'logActivity')) {
                    $contract->logActivity(
                        'site_assignment_toggled', 
                        "Worker '{$worker->full_name}' " . ($worker->is_assigned_to_site ? 'assigned to' : 'removed from') . " site"
                    );
                }
            } catch (\Exception $e) {
                Log::warning('Failed to log worker activity: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'is_assigned' => $worker->is_assigned_to_site,
                'assigned_at' => $worker->assigned_to_site_at
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to toggle site assignment: ' . $e->getMessage(), [
                'contract_id' => $contractId,
                'worker_id' => $workerId,
                'contractor_id' => auth()->id(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update site assignment. Please try again.'
            ], 500);
        }
    }
}