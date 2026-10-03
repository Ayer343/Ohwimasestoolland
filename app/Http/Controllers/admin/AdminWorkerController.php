<?php

namespace App\Http\Controllers\Admin;

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

class AdminWorkerController extends Controller
{
    protected $badgeService;

    public function __construct(BadgeService $badgeService)
    {
        $this->badgeService = $badgeService;
    }

   /**
 * Display all workers across all contractors with admin view
 * Only shows workers that have been assigned to a site by the contractor
 */
public function index(Request $request)
{
    $query = ConstructionWorker::with([
        'contract',
        'contract.contractor',  // ✅ Keep this - it exists
        'addedBy',
        'badge'
    ]);

    // ✅ IMPORTANT: Only show workers assigned to site
    // This ensures admin only sees workers that contractors have marked as "on site"
    $query->where('is_assigned_to_site', true);

    // 🔍 Filter by contractor
    if ($request->has('contractor_id') && $request->contractor_id) {
        $query->whereHas('contract', function($q) use ($request) {
            $q->where('contractor_user_id', $request->contractor_id);
        });
    }

    // 🔍 Filter by contract
    if ($request->has('contract_id') && $request->contract_id) {
        $query->where('contract_id', $request->contract_id);
    }

    // 🔍 Filter by status
    if ($request->has('status') && $request->status) {
        $query->where('status', $request->status);
    }

    // 🔍 Filter by trade
    if ($request->has('trade') && $request->trade) {
        $query->where('trade', $request->trade);
    }

    // 🔍 Filter by badge status
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
            case 'badge_active':
                $query->whereHas('badge', function($q) {
                    $q->where('is_active', true)
                      ->where('valid_until', '>', now());
                });
                break;
            case 'badge_expired':
                $query->whereHas('badge', function($q) {
                    $q->where('valid_until', '<=', now());
                });
                break;
            case 'badge_pending':
                $query->whereHas('badge', function($q) {
                    $q->where('status', 'pending');
                });
                break;
        }
    }

    // 🔍 Search
    if ($request->has('search') && $request->search) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('full_name', 'LIKE', "%{$search}%")
              ->orWhere('job_title', 'LIKE', "%{$search}%")
              ->orWhere('trade', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%")
              ->orWhere('phone', 'LIKE', "%{$search}%")
              ->orWhere('id_number', 'LIKE', "%{$search}%");
        });
    }

    // 📅 Date range filter
    if ($request->has('date_from') && $request->date_from) {
        $query->whereDate('created_at', '>=', $request->date_from);
    }
    if ($request->has('date_to') && $request->date_to) {
        $query->whereDate('created_at', '<=', $request->date_to);
    }

    // 🔄 Sorting
    $sortField = $request->get('sort', 'created_at');
    $sortDirection = $request->get('direction', 'desc');
    $allowedSorts = [
        'full_name', 'job_title', 'trade', 'status', 
        'daily_rate', 'start_date', 'end_date', 'created_at'
    ];
    
    if (in_array($sortField, $allowedSorts)) {
        $query->orderBy($sortField, $sortDirection);
    }

    $workers = $query->paginate(20)->withQueryString();

    // 📊 Statistics - Only count workers assigned to site
    $stats = $this->getAdminStats($request);

    // 📋 Get contractors for filter dropdown
    $contractors = User::whereHas('contracts')->get();

    // 📋 Get active contracts for filter dropdown
    $contracts = ConstructionContract::whereIn('status', [
        ConstructionContract::STATUS_APPROVED,
        ConstructionContract::STATUS_IN_PROGRESS
    ])->get();

    // 📋 Get unique trades
    $trades = ConstructionWorker::select('trade')
        ->distinct()
        ->whereNotNull('trade')
        ->pluck('trade')
        ->toArray();

    return view('admin.workers.index', compact(
        'workers', 
        'stats', 
        'contractors', 
        'contracts', 
        'trades'
    ));
}

    /**
 * Get admin statistics - Only counts workers assigned to site
 */
private function getAdminStats(Request $request)
{
    $baseQuery = ConstructionWorker::query();

    // ✅ IMPORTANT: Only count workers assigned to site
    $baseQuery->where('is_assigned_to_site', true);

    // Apply contractor filter to stats if selected
    if ($request->has('contractor_id') && $request->contractor_id) {
        $baseQuery->whereHas('contract', function($q) use ($request) {
            $q->where('contractor_user_id', $request->contractor_id);
        });
    }

    return [
        'total' => (clone $baseQuery)->count(),
        'active' => (clone $baseQuery)->where('status', ConstructionWorker::STATUS_ACTIVE)->count(),
        'inactive' => (clone $baseQuery)->where('status', ConstructionWorker::STATUS_INACTIVE)->count(),
        'completed' => (clone $baseQuery)->where('status', ConstructionWorker::STATUS_COMPLETED)->count(),
        'terminated' => (clone $baseQuery)->where('status', ConstructionWorker::STATUS_TERMINATED)->count(),
        
        // Badge stats
        'badge_sent' => (clone $baseQuery)->whereHas('badge', function($q) {
            $q->whereNotNull('email_sent_at');
        })->count(),
        'badge_active' => (clone $baseQuery)->whereHas('badge', function($q) {
            $q->where('is_active', true)
              ->where('valid_until', '>', now());
        })->count(),
        'no_badge' => (clone $baseQuery)->whereDoesntHave('badge')->count(),

        // Contractor stats
        'contractors_count' => User::whereHas('contracts')->count(),
        'contracts_active' => ConstructionContract::where('status', ConstructionContract::STATUS_IN_PROGRESS)->count(),
    ];
}

    /**
     * View specific worker details
     */
    public function show($id)
    {
        $worker = ConstructionWorker::with([
            'contract',
            'contract.contractor',  // ✅ Keep this - it exists
            'addedBy',
            'badge'
        ])->findOrFail($id);

        // Get site access logs
        $accessLogs = $worker->siteAccessLogs()
            ->with('site')
            ->orderBy('accessed_at', 'desc')
            ->limit(50)
            ->get();

        // Get worker's work history
        $workHistory = $worker->workHistory()
            ->with('contract')
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'worker' => $this->formatWorkerForAdmin($worker),
            'access_logs' => $accessLogs,
            'work_history' => $workHistory,
        ]);
    }

    /**
     * Format worker data for admin view
     */
    private function formatWorkerForAdmin($worker)
    {
        return [
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
            'start_date' => $worker->start_date?->toISOString(),
            'end_date' => $worker->end_date?->toISOString(),
            'created_at' => $worker->created_at?->toISOString(),
            'updated_at' => $worker->updated_at?->toISOString(),
            
            // Contract info - ✅ REMOVED project relationship
            'contract' => $worker->contract ? [
                'id' => $worker->contract->id,
                'contract_number' => $worker->contract->contract_number,
                'title' => $worker->contract->title,
                'status' => $worker->contract->status,
                'start_date' => $worker->contract->start_date?->toISOString(),
                'end_date' => $worker->contract->end_date?->toISOString(),
                'location' => $worker->contract->location ?? 'Not specified', // ✅ Use location instead of site/project
            ] : null,
            
            // Contractor info
            'contractor' => $worker->contract?->contractor ? [
                'id' => $worker->contract->contractor->id,
                'name' => $worker->contract->contractor->name,
                'email' => $worker->contract->contractor->email,
                'phone' => $worker->contract->contractor->phone,
                'company_name' => $worker->contract->contractor->company_name ?? null,
            ] : null,
            
            // Added by
            'added_by' => $worker->addedBy ? [
                'id' => $worker->addedBy->id,
                'name' => $worker->addedBy->name,
                'email' => $worker->addedBy->email,
            ] : null,
            
            // Badge
            'badge' => $worker->badge ? [
                'badge_number' => $worker->badge->badge_number,
                'status' => $worker->badge->status,
                'valid_from' => $worker->badge->valid_from?->toISOString(),
                'valid_until' => $worker->badge->valid_until?->toISOString(),
                'is_expired' => $worker->badge->is_expired,
                'days_until_expiry' => $worker->badge->days_until_expiry,
                'verification_count' => $worker->badge->verification_count,
                'last_verified_at' => $worker->badge->last_verified_at?->toISOString(),
                'email_sent_at' => $worker->badge->email_sent_at?->toISOString(),
                'email_sent_to' => $worker->badge->email_sent_to,
                'qr_code' => $worker->badge->qr_code,
                'verification_url' => $worker->badge->getVerificationUrl(),
                'is_active' => $worker->badge->is_active,
            ] : null,
        ];
    }

    /**
     * Get workers by contractor
     */
    public function getByContractor($contractorId)
    {
        $workers = ConstructionWorker::whereHas('contract', function($q) use ($contractorId) {
            $q->where('contractor_user_id', $contractorId);
        })
        ->with(['contract', 'badge'])
        ->where('status', ConstructionWorker::STATUS_ACTIVE)
        ->get()
        ->map(function($worker) {
            return [
                'id' => $worker->id,
                'full_name' => $worker->full_name,
                'job_title' => $worker->job_title,
                'trade' => $worker->trade,
                'contract' => $worker->contract->contract_number,
                'has_badge' => $worker->badge ? true : false,
                'badge_status' => $worker->badge?->status,
            ];
        });

        return response()->json([
            'success' => true,
            'workers' => $workers,
        ]);
    }

    /**
     * Get workers by contract
     */
    public function getByContract($contractId)
    {
        $workers = ConstructionWorker::where('contract_id', $contractId)
            ->with(['badge'])
            ->where('status', ConstructionWorker::STATUS_ACTIVE)
            ->get()
            ->map(function($worker) {
                return [
                    'id' => $worker->id,
                    'full_name' => $worker->full_name,
                    'job_title' => $worker->job_title,
                    'trade' => $worker->trade,
                    'has_badge' => $worker->badge ? true : false,
                    'badge_status' => $worker->badge?->status,
                ];
            });

        return response()->json([
            'success' => true,
            'workers' => $workers,
        ]);
    }

    /**
     * Get site entry report (who is on site today)
     */
    public function getTodaySiteReport(Request $request)
    {
        $query = ConstructionWorker::where('status', ConstructionWorker::STATUS_ACTIVE)
            ->with(['contract', 'contract.contractor', 'badge']);

        // Filter by contractor if specified
        if ($request->has('contractor_id') && $request->contractor_id) {
            $query->whereHas('contract', function($q) use ($request) {
                $q->where('contractor_user_id', $request->contractor_id);
            });
        }

        // Filter by site if specified
        if ($request->has('site_id') && $request->site_id) {
            $query->whereHas('contract', function($q) use ($request) {
                $q->where('site_id', $request->site_id);
            });
        }

        $workers = $query->get()->map(function($worker) {
            return [
                'worker' => $worker->full_name,
                'trade' => $worker->trade,
                'contract' => $worker->contract->contract_number,
                'contractor' => $worker->contract->contractor->name,
                'phone' => $worker->phone,
                'badge_number' => $worker->badge?->badge_number,
                'badge_valid' => $worker->badge?->is_active && !$worker->badge?->is_expired,
                'check_in_time' => $worker->todayCheckInTime(),
                'on_site' => $worker->isOnSiteToday(),
            ];
        });

        return response()->json([
            'success' => true,
            'date' => now()->toDateString(),
            'workers' => $workers,
            'total' => $workers->count(),
        ]);
    }

    /**
     * Export workers data
     */
    public function export(Request $request)
    {
        $query = ConstructionWorker::with(['contract', 'contract.contractor', 'badge']);

        // Apply filters (same as index)
        if ($request->has('contractor_id') && $request->contractor_id) {
            $query->whereHas('contract', function($q) use ($request) {
                $q->where('contractor_user_id', $request->contractor_id);
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $workers = $query->get();

        // Generate CSV
        $filename = 'workers_export_' . now()->format('Y-m-d_H-i') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($workers) {
            $file = fopen('php://output', 'w');
            
            // Headers
            fputcsv($file, [
                'Worker Name', 'Email', 'Phone', 'Trade', 'Job Title', 
                'Status', 'Contract Number', 'Contractor', 'Daily Rate',
                'Start Date', 'End Date', 'Badge Number', 'Badge Status',
                'Added On', 'Added By'
            ]);

            // Data
            foreach ($workers as $worker) {
                fputcsv($file, [
                    $worker->full_name,
                    $worker->email,
                    $worker->phone,
                    $worker->trade,
                    $worker->job_title,
                    $worker->status,
                    $worker->contract?->contract_number,
                    $worker->contract?->contractor?->name,
                    $worker->daily_rate,
                    $worker->start_date?->format('Y-m-d'),
                    $worker->end_date?->format('Y-m-d'),
                    $worker->badge?->badge_number,
                    $worker->badge?->status,
                    $worker->created_at?->format('Y-m-d H:i'),
                    $worker->addedBy?->name,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Revoke a worker's badge
     */
    public function revokeBadge(Request $request, $id)
    {
        $worker = ConstructionWorker::findOrFail($id);
        
        if (!$worker->badge) {
            return response()->json([
                'success' => false,
                'message' => 'This worker does not have a badge.'
            ], 400);
        }

        try {
            $worker->badge->update([
                'is_active' => false,
                'revoked_at' => now(),
                'revoked_by' => auth()->id(),
                'revocation_reason' => $request->reason ?? 'Revoked by admin',
            ]);

            Log::info('Badge revoked by admin', [
                'worker_id' => $worker->id,
                'badge_number' => $worker->badge->badge_number,
                'admin_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Badge revoked successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to revoke badge', [
                'worker_id' => $worker->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke badge: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send or resend badge to a worker
     */
    public function sendBadge(Request $request, $id)
    {
        $worker = ConstructionWorker::findOrFail($id);

        if (!$worker->email) {
            return response()->json([
                'success' => false,
                'message' => 'Worker does not have an email address.'
            ], 400);
        }

        if ($worker->status !== ConstructionWorker::STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Badge can only be sent to active workers.'
            ], 400);
        }

        try {
            $badge = $worker->getOrCreateBadge();
            $this->badgeService->sendBadgeEmail($badge);

            Log::info('Badge sent by admin', [
                'worker_id' => $worker->id,
                'badge_number' => $badge->badge_number,
                'admin_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Badge sent successfully to ' . $worker->email
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send badge', [
                'worker_id' => $worker->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send badge: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resend badge to a worker
     */
    public function resendBadge(Request $request, $id)
    {
        return $this->sendBadge($request, $id);
    }
}