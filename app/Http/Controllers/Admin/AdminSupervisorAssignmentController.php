<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecuritySupervisorAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AdminSupervisorAssignmentController extends Controller
{
    // ================================================================
    // LIST
    // ================================================================

    /**
     * Display a listing of Head-of-Security appointments.
     */
    public function index(Request $request)
    {
        $query = SecuritySupervisorAssignment::with(['user', 'assignedBy']);

        $this->applyFilters($query, $request);

        $sortBy    = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        // Whitelist sortable columns to prevent SQL injection via request params
        $allowedSort = ['created_at', 'start_date', 'end_date', 'is_active'];
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'created_at';
        }
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc'], true)
            ? strtolower($sortOrder)
            : 'desc';

        $assignments = $query->orderBy($sortBy, $sortOrder)->paginate(20)->withQueryString();

        $stats = $this->getAdminStats();

        // Only officers eligible to be appointed appear in the dropdown
        $supervisors = User::eligibleSecuritySupervisors()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.supervisor-assignments.index', compact(
            'assignments', 'stats', 'supervisors', 'request'
        ));
    }

    // ================================================================
    // CREATE
    // ================================================================

    /**
     * Show the form for appointing a new Head of Security.
     */
    public function create()
    {
        $supervisors = User::eligibleSecuritySupervisors()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $currentHead = SecuritySupervisorAssignment::currentHead();

        return view('admin.supervisor-assignments.create', compact(
            'supervisors', 'currentHead'
        ));
    }

    // ================================================================
    // STORE
    // ================================================================

    /**
     * Appoint a Head of Security. Enforces a single active head at a time.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id'          => 'required|exists:users,id',
            'start_date'       => 'required|date',
            'end_date'         => 'nullable|date|after_or_equal:start_date',
            'title'            => 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:1000',
            'replace_existing' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user = User::find($request->user_id);

        if (!$user || !$user->isEligibleSecuritySupervisor()) {
            return redirect()->back()
                ->with('error', 'Selected user is not eligible to be appointed Head of Security. ' .
                                'Enable their eligibility on the user edit page first.')
                ->withInput();
        }

        DB::beginTransaction();

        try {
            $existing = SecuritySupervisorAssignment::where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (!$request->boolean('replace_existing')) {
                    DB::rollBack();
                    return redirect()->back()
                        ->with('error', "{$existing->user->name} is currently Head of Security. " .
                                        'Confirm replacement to proceed.')
                        ->withInput();
                }

                $existing->update([
                    'is_active' => false,
                    'end_date'  => Carbon::parse($request->start_date)->subDay(),
                    'notes'     => trim(($existing->notes ?? '') .
                        "\nReplaced by {$user->name} on " . now()->toDateString() . '.'),
                ]);

                Log::info('Previous Head of Security demoted', [
                    'old_assignment_id' => $existing->id,
                    'old_user_id'       => $existing->user_id,
                    'new_user_id'       => $user->id,
                    'admin_id'          => Auth::id(),
                ]);
            }

            $assignment = SecuritySupervisorAssignment::create([
                'user_id'     => $user->id,
                'assigned_by' => Auth::id(),
                'title'       => $request->input('title', 'Head of Security'),
                'start_date'  => $request->start_date,
                'end_date'    => $request->end_date,
                'is_active'   => true,
                'notes'       => $request->notes,
                'metadata'    => [
                    'created_via'       => 'admin_manual',
                    'created_at'        => now()->toDateTimeString(),
                    'admin_id'          => Auth::id(),
                    'admin_name'        => Auth::user()->name,
                    'replaced_existing' => (bool) $existing,
                ],
            ]);

            DB::commit();

            Log::info('Head of Security appointed', [
                'assignment_id' => $assignment->id,
                'user_id'       => $user->id,
                'admin_id'      => Auth::id(),
            ]);

            return redirect()
                ->route('admin.supervisor-assignments.show', $assignment)
                ->with('success', "{$user->name} appointed as Head of Security.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to appoint Head of Security: ' . $e->getMessage(), [
                'payload' => $request->except(['_token', '_method']),
                'trace'   => $e->getTraceAsString(),
            ]);
            return redirect()->back()
                ->with('error', 'Failed to appoint: ' . $e->getMessage())
                ->withInput();
        }
    }

    // ================================================================
    // SHOW
    // ================================================================

    /**
     * Display the appointment plus the team they oversee.
     */
    public function show(SecuritySupervisorAssignment $assignment)
    {
        $assignment->load(['user', 'assignedBy']);

        // The team = all active security personnel excluding the head themselves.
        $team = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->where('id', '!=', $assignment->user_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'photo', 'status', 'can_be_supervisor']);

        $auditTrail = $assignment->metadata['update_history'] ?? [];

        // Pending-approval counters stay zeroed in the simplified model —
        // these are derived from the security sub-system (swaps/OT/etc.)
        // which is orthogonal to head-of-security appointment.
        $pendingApprovals = [
            'swap_requests'         => 0,
            'overtime_requests'     => 0,
            'pending_verifications' => 0,
        ];

        return view('admin.supervisor-assignments.show', compact(
            'assignment', 'team', 'auditTrail', 'pendingApprovals'
        ));
    }

    // ================================================================
    // EDIT / UPDATE
    // ================================================================

    /**
     * Show the form for editing the appointment.
     */
    public function edit(SecuritySupervisorAssignment $assignment)
    {
        $supervisors = User::eligibleSecuritySupervisors()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $otherActiveHead = SecuritySupervisorAssignment::where('is_active', true)
            ->where('id', '!=', $assignment->id)
            ->first();

        return view('admin.supervisor-assignments.edit', compact(
            'assignment', 'supervisors', 'otherActiveHead'
        ));
    }

    /**
     * Update the appointment. Enforces the single-active-head invariant.
     */
    public function update(Request $request, SecuritySupervisorAssignment $assignment)
    {
        $validator = Validator::make($request->all(), [
            'user_id'          => 'required|exists:users,id',
            'start_date'       => 'required|date',
            'end_date'         => 'nullable|date|after_or_equal:start_date',
            'title'            => 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:1000',
            'is_active'        => 'sometimes|boolean',
            'replace_existing' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user          = User::findOrFail($request->user_id);
        $becomingActive = $request->boolean('is_active', $assignment->is_active);

        if ($becomingActive && !$user->isEligibleSecuritySupervisor()) {
            return redirect()->back()
                ->with('error', 'Selected user is not eligible to be Head of Security. ' .
                                'Enable eligibility or set the assignment to inactive.')
                ->withInput();
        }

        DB::beginTransaction();

        try {
            if ($becomingActive) {
                $occupied = SecuritySupervisorAssignment::where('is_active', true)
                    ->where('id', '!=', $assignment->id)
                    ->lockForUpdate()
                    ->first();

                if ($occupied) {
                    if (!$request->boolean('replace_existing')) {
                        DB::rollBack();
                        return redirect()->back()
                            ->with('error', "{$occupied->user->name} is currently Head of Security. " .
                                            'Confirm replacement to proceed.')
                            ->withInput();
                    }

                    $occupied->update([
                        'is_active' => false,
                        'end_date'  => Carbon::parse($request->start_date)->subDay(),
                        'notes'     => trim(($occupied->notes ?? '') .
                            "\nReplaced by {$user->name} on " . now()->toDateString() . '.'),
                    ]);
                }
            }

            $changes = $this->diffAssignment($assignment, $request);

            $metadata = $assignment->metadata ?? [];
            $metadata['update_history'][] = [
                'updated_at'      => now()->toDateTimeString(),
                'updated_by'      => Auth::id(),
                'updated_by_name' => Auth::user()->name,
                'changes'         => $changes,
            ];

            $assignment->update([
                'user_id'     => $user->id,
                'title'       => $request->input('title', $assignment->title ?: 'Head of Security'),
                'start_date'  => $request->start_date,
                'end_date'    => $request->end_date,
                'is_active'   => $becomingActive,
                'notes'       => $request->notes,
                'metadata'    => $metadata,
            ]);

            DB::commit();

            Log::info('Head of Security appointment updated', [
                'assignment_id' => $assignment->id,
                'admin_id'      => Auth::id(),
                'changes'       => $changes,
            ]);

            return redirect()
                ->route('admin.supervisor-assignments.show', $assignment)
                ->with('success', 'Assignment updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update Head of Security appointment: ' . $e->getMessage(), [
                'assignment_id' => $assignment->id,
                'trace'         => $e->getTraceAsString(),
            ]);
            return redirect()->back()
                ->with('error', 'Failed to update: ' . $e->getMessage())
                ->withInput();
        }
    }

    // ================================================================
    // DESTROY / TRASH
    // ================================================================

    /**
     * Soft-delete an assignment.
     */
    public function destroy(SecuritySupervisorAssignment $assignment)
    {
        try {
            $assignment->delete();

            Log::info('Head of Security appointment soft-deleted', [
                'assignment_id' => $assignment->id,
                'admin_id'      => Auth::id(),
            ]);

            return redirect()
                ->route('admin.supervisor-assignments.index')
                ->with('success', 'Assignment moved to trash.');
        } catch (\Exception $e) {
            Log::error('Failed to delete assignment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete assignment.');
        }
    }

    /**
     * Trashed assignments.
     */
    public function trash(Request $request)
    {
        $assignments = SecuritySupervisorAssignment::onlyTrashed()
            ->with(['user'])
            ->orderByDesc('deleted_at')
            ->paginate(20);

        return view('admin.supervisor-assignments.trash', compact('assignments'));
    }

    /**
     * Restore a trashed assignment.
     */
    public function restore($id)
    {
        try {
            $assignment = SecuritySupervisorAssignment::onlyTrashed()->findOrFail($id);

            // Restore only if it won't violate the single-active-head invariant.
            if ($assignment->is_active
                && SecuritySupervisorAssignment::where('is_active', true)->exists()) {
                return redirect()->back()
                    ->with('error', 'Cannot restore: an active Head of Security already exists.');
            }

            $assignment->restore();

            Log::info('Head of Security appointment restored', [
                'assignment_id' => $assignment->id,
                'admin_id'      => Auth::id(),
            ]);

            return redirect()
                ->route('admin.supervisor-assignments.trash')
                ->with('success', 'Assignment restored.');
        } catch (\Exception $e) {
            Log::error('Failed to restore assignment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to restore assignment.');
        }
    }

    /**
     * Permanently delete a trashed assignment.
     */
    public function forceDelete($id)
    {
        try {
            SecuritySupervisorAssignment::onlyTrashed()->findOrFail($id)->forceDelete();

            Log::info('Head of Security appointment permanently deleted', [
                'assignment_id' => $id,
                'admin_id'      => Auth::id(),
            ]);

            return redirect()
                ->route('admin.supervisor-assignments.trash')
                ->with('success', 'Assignment permanently deleted.');
        } catch (\Exception $e) {
            Log::error('Failed to permanently delete assignment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to permanently delete assignment.');
        }
    }

    // ================================================================
    // BULK ACTIONS
    // ================================================================

    public function bulkAction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:activate,deactivate,delete,extend',
            'ids'    => 'required|array',
            'ids.*'  => 'exists:security_supervisor_assignments,id',
            'extend_days' => 'required_if:action,extend|integer|min:1|max:365',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $result = $this->processBulkAction($request);

            Log::info('Bulk action performed on Head-of-Security assignments', [
                'action'    => $request->action,
                'processed' => $result['processed'],
                'failed'    => count($result['failed']),
                'admin_id'  => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'success'   => true,
                'message'   => $result['message'],
                'processed' => $result['processed'],
                'failed'    => $result['failed'],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk action failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk action.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function bulkRestore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids'   => 'required|array',
            'ids.*' => 'exists:security_supervisor_assignments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $restored = SecuritySupervisorAssignment::onlyTrashed()
                ->whereIn('id', $request->ids)
                ->restore();

            Log::info('Bulk restored Head-of-Security assignments', [
                'count'    => $restored,
                'admin_id' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$restored} assignment(s) restored.",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk restore failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to restore.'], 500);
        }
    }

    public function bulkForceDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids'   => 'required|array',
            'ids.*' => 'exists:security_supervisor_assignments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $deleted = SecuritySupervisorAssignment::onlyTrashed()
                ->whereIn('id', $request->ids)
                ->forceDelete();

            Log::info('Bulk permanently deleted assignments', [
                'count'    => $deleted,
                'admin_id' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$deleted} assignment(s) permanently deleted.",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk force delete failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete.'], 500);
        }
    }

    // ================================================================
    // AJAX ENDPOINTS
    // ================================================================

    /**
     * Eligible personnel autocomplete.
     */
    public function getEligiblePersonnel(Request $request)
    {
        try {
            $search = trim($request->get('q', ''));

            $personnel = User::eligibleSecuritySupervisors()
                ->when($search, fn($q) =>
                    $q->where(fn($q2) =>
                        $q2->where('name', 'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%")
                    )
                )
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'email']);

            return response()->json([
                'success' => true,
                'data'    => $personnel->map(fn($u) => [
                    'id'    => $u->id,
                    'text'  => $u->name . ($u->email ? " ({$u->email})" : ''),
                    'name'  => $u->name,
                    'email' => $u->email,
                ]),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get eligible personnel: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load personnel.'], 500);
        }
    }

    /**
     * Dashboard stats.
     */
    public function getStats()
    {
        try {
            $stats = $this->getAdminStats();

            $stats['eligible_officers'] = User::eligibleSecuritySupervisors()->count();

            return response()->json(['success' => true, 'stats' => $stats]);
        } catch (\Exception $e) {
            Log::error('Failed to get stats: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load statistics.'], 500);
        }
    }

    /**
     * Assignment details for AJAX modals.
     */
    public function getAssignmentDetails($id)
    {
        try {
            $a = SecuritySupervisorAssignment::with(['user', 'assignedBy'])->findOrFail($id);

            return response()->json([
                'success'    => true,
                'assignment' => [
                    'id'          => $a->id,
                    'title'       => $a->title_label,
                    'start_date'  => $a->start_date?->format('Y-m-d'),
                    'end_date'    => $a->end_date?->format('Y-m-d'),
                    'is_active'   => $a->is_active,
                    'notes'       => $a->notes,
                    'created_at'  => $a->created_at?->format('Y-m-d H:i:s'),
                    'assigned_by' => $a->assignedBy?->name,
                    'user'        => $a->user ? [
                        'id'    => $a->user->id,
                        'name'  => $a->user->name,
                        'email' => $a->user->email,
                        'phone' => $a->user->phone,
                    ] : null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get assignment details: ' . $e->getMessage(), [
                'assignment_id' => $id,
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to load details.'], 500);
        }
    }

    // ================================================================
    // PRIVATE HELPERS
    // ================================================================

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->inactive();
            } elseif ($request->status === 'expired') {
                $query->expired();
            }
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', fn($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
            );
        }

        if ($request->filled('start_date')) {
            $query->whereDate('start_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('end_date', '<=', $request->end_date);
        }
    }

    private function getAdminStats(): array
    {
        return [
            'total_assignments'  => SecuritySupervisorAssignment::count(),
            'active_assignments' => SecuritySupervisorAssignment::active()->count(),
            'expired_assignments'=> SecuritySupervisorAssignment::expired()->count(),
            'expiring_soon'      => SecuritySupervisorAssignment::expiringSoon()->count(),
            'by_status' => [
                'active'   => SecuritySupervisorAssignment::where('is_active', true)->count(),
                'inactive' => SecuritySupervisorAssignment::where('is_active', false)->count(),
            ],
        ];
    }

    private function processBulkAction(Request $request): array
    {
        $assignments = SecuritySupervisorAssignment::whereIn('id', $request->ids)->get();
        $processed   = 0;
        $failed      = [];

        foreach ($assignments as $assignment) {
            try {
                switch ($request->action) {
                    case 'activate':
                        // Guard the single-active-head invariant
                        if (SecuritySupervisorAssignment::where('is_active', true)
                            ->where('id', '!=', $assignment->id)
                            ->exists()) {
                            $failed[] = [
                                'id'     => $assignment->id,
                                'name'   => $assignment->user->name ?? 'Unknown',
                                'reason' => 'Another Head of Security is already active.',
                            ];
                            continue 2;
                        }
                        $assignment->update(['is_active' => true]);
                        break;

                    case 'deactivate':
                        $assignment->update(['is_active' => false]);
                        break;

                    case 'delete':
                        $assignment->delete();
                        break;

                    case 'extend':
                        if ($assignment->end_date) {
                            $newEndDate = Carbon::parse($assignment->end_date)
                                ->addDays((int) $request->extend_days);

                            $metadata = $assignment->metadata ?? [];
                            $metadata['bulk_extension'][] = [
                                'extended_at' => now()->toDateTimeString(),
                                'extended_by' => Auth::id(),
                                'days'        => (int) $request->extend_days,
                                'reason'      => $request->reason,
                            ];

                            $assignment->update([
                                'end_date' => $newEndDate,
                                'metadata' => $metadata,
                            ]);
                        }
                        break;
                }

                $processed++;
            } catch (\Exception $e) {
                $failed[] = [
                    'id'     => $assignment->id,
                    'name'   => $assignment->user->name ?? 'Unknown',
                    'reason' => $e->getMessage(),
                ];
            }
        }

        $message = "{$processed} assignment(s) processed successfully.";
        if (!empty($failed)) {
            $message .= ' ' . count($failed) . ' failed.';
        }

        return ['message' => $message, 'processed' => $processed, 'failed' => $failed];
    }

    private function diffAssignment(SecuritySupervisorAssignment $assignment, Request $request): array
    {
        $changes = [];

        foreach (['user_id', 'title', 'start_date', 'end_date', 'notes'] as $field) {
            $old = $assignment->$field;
            $new = $request->input($field);

            if (is_object($old)) {
                $old = $old->format('Y-m-d');
            }

            if ((string) $old !== (string) $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }
}