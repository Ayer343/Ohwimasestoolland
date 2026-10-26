<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\SecuritySupervisorAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupervisorAssignmentController extends Controller
{
    // ================================================================
    // LIST
    // ================================================================

    /**
     * List appointments visible to the current user.
     *
     * Visibility:
     *   - Active Head of Security → their own appointment + history
     *   - Admin / super-admin    → all
     *   - Anyone else            → redirected
     *
     * Read-only — no create/edit links are offered from here. Admins
     * appoint and edit; the officer can only resign from the detail page.
     */
    public function index(Request $request)
    {
        $currentUser = Auth::user();

        if (! $this->isActiveHead($currentUser)
            && ! $currentUser->isAdmin()
            && ! $currentUser->isSuperAdmin()) {
            return redirect()->route('security.dashboard')
                ->with('error', 'Only the current Head of Security can view appointments.');
        }

        $query = SecuritySupervisorAssignment::with(['user', 'assignedBy']);

        // Heads only see their own appointment; admins see everything.
        if (! $currentUser->isAdmin() && ! $currentUser->isSuperAdmin()) {
            $query->where('user_id', $currentUser->id);
        }

        $this->applyFilters($query, $request);

        $sortBy    = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSort = ['created_at', 'start_date', 'end_date', 'is_active'];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'created_at';
        }
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc'], true)
            ? strtolower($sortOrder)
            : 'desc';

        $assignments = $query->orderBy($sortBy, $sortOrder)->paginate(20)->withQueryString();

        $stats       = $this->buildStats($currentUser);
        $currentHead = SecuritySupervisorAssignment::where('is_active', true)->first();

        return view('security.supervisor-assignments.index', compact(
            'assignments', 'stats', 'currentHead', 'request'
        ));
    }

    // ================================================================
    // SHOW
    // ================================================================

    /**
     * Appointment detail page (read-only).
     */
    public function show(SecuritySupervisorAssignment $assignment)
    {
        $currentUser = Auth::user();

        if (! $this->canViewAssignment($currentUser, $assignment)) {
            return redirect()->route('security.supervisor-assignments.index')
                ->with('error', 'You do not have access to this appointment.');
        }

        $assignment->load(['user', 'assignedBy']);

        $auditTrail = $assignment->metadata['update_history'] ?? [];

        // Team the head oversees = all other active security personnel.
        $team = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->where('id', '!=', $assignment->user_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'photo', 'status', 'can_be_supervisor']);

        // Stubbed — the security-ops module (swaps/OT/verifications) is
        // orthogonal to the appointment.
        $pendingApprovals = [
            'swap_requests'         => 0,
            'overtime_requests'     => 0,
            'pending_verifications' => 0,
        ];

        return view('security.supervisor-assignments.show', compact(
            'assignment', 'team', 'auditTrail', 'pendingApprovals'
        ));
    }

    // ================================================================
    // RESIGN (self-service)
    // ================================================================

    /**
     * Allow the active head to resign from their own appointment.
     *
     * This is the *only* mutation a security officer can perform on an
     * appointment. It requires:
     *   - The officer owns the appointment
     *   - The appointment is currently active
     *   - A non-empty reason
     *
     * Under the hood this delegates to `SecuritySupervisorAssignment::terminate()`,
     * which sets `is_active = false`, `end_date = now()`, and appends a
     * termination record to metadata. The method is called `resign()` here
     * so route and UI terminology match what the officer sees.
     */
    public function resign(Request $request, SecuritySupervisorAssignment $assignment)
    {
        $currentUser = Auth::user();

        if (! $this->canResign($currentUser, $assignment)) {
            return redirect()
                ->route('security.supervisor-assignments.index')
                ->with('error', 'You cannot resign from this appointment.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $assignment->terminate($validated['reason'], $currentUser->id);

            DB::commit();

            Log::info('Head of Security resigned from post', [
                'assignment_id' => $assignment->id,
                'officer_id'    => $currentUser->id,
                'officer_name'  => $currentUser->name,
                'reason'        => $validated['reason'],
            ]);

            // Optional: notify admins so a successor can be appointed.
            $this->notifyAdminsOfResignation($assignment, $currentUser->name, $validated['reason']);

            return redirect()
                ->route('security.supervisor-assignments.show', $assignment)
                ->with('success', 'You have resigned from the Head of Security post. Administrators have been notified.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process Head of Security resignation: ' . $e->getMessage(), [
                'assignment_id' => $assignment->id,
                'officer_id'    => $currentUser->id,
                'trace'         => $e->getTraceAsString(),
            ]);

            return back()
                ->with('error', 'Failed to process resignation. Please try again.');
        }
    }

    // ================================================================
    // AJAX HELPERS
    // ================================================================

    /**
     * Eligible officers — used by the "who might take over next" suggestion
     * panel on the show page. Read-only.
     */
    public function getEligiblePersonnel(Request $request)
    {
        $currentUser = Auth::user();

        if (! $this->isActiveHead($currentUser)
            && ! $currentUser->isAdmin()
            && ! $currentUser->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only the current Head of Security can access this.',
            ], 403);
        }

        $search = trim($request->get('q', ''));

        $personnel = User::eligibleSecuritySupervisors()
            ->when($search, fn ($q) =>
                $q->where(fn ($q2) =>
                    $q2->where('name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%")
                )
            )
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json([
            'success' => true,
            'data'    => $personnel->map(fn ($u) => [
                'id'    => $u->id,
                'text'  => $u->name . ($u->email ? " ({$u->email})" : ''),
                'name'  => $u->name,
                'email' => $u->email,
            ]),
        ]);
    }

    /**
     * Snapshot of an appointment for a modal / AJAX panel. Read-only.
     */
    public function getAssignmentDetails($id)
    {
        $currentUser = Auth::user();

        try {
            $a = SecuritySupervisorAssignment::with(['user', 'assignedBy'])->findOrFail($id);

            if (! $this->canViewAssignment($currentUser, $a)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
            }

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
            Log::error('Failed to get appointment details (supervisor): ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }
    }

    // ================================================================
    // STATS
    // ================================================================

    /**
     * Stats scoped to the current head (or all for admins).
     */
    private function buildStats(User $currentUser): array
    {
        $base = SecuritySupervisorAssignment::query();

        if (! $currentUser->isAdmin() && ! $currentUser->isSuperAdmin()) {
            $base->where('user_id', $currentUser->id);
        }

        return [
            'total_assignments'   => (clone $base)->count(),
            'active_assignments'  => (clone $base)->active()->count(),
            'expired_assignments' => (clone $base)->expired()->count(),
            'expiring_soon'       => (clone $base)->expiringSoon()->count(),
        ];
    }

    // ================================================================
    // AUTH HELPERS
    // ================================================================

    /**
     * Is this user the current, active Head of Security?
     */
    private function isActiveHead(User $user): bool
    {
        return $user->isSecurityPersonnel()
            && $user->can_be_supervisor
            && SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                })
                ->exists();
    }

    /**
     * Can the current user *see* a specific appointment?
     *
     *   - The officer themselves
     *   - The active head
     *   - Admins / super-admins
     */
    private function canViewAssignment(User $currentUser, SecuritySupervisorAssignment $assignment): bool
    {
        if ($currentUser->isAdmin() || $currentUser->isSuperAdmin()) {
            return true;
        }
        if ($assignment->user_id === $currentUser->id) {
            return true;
        }
        if ($this->isActiveHead($currentUser)) {
            return true;
        }
        return false;
    }

    /**
     * Can the current user resign from this appointment?
     *
     * Only the owning officer, only while it's active.
     * Admins use the admin controller's terminate(), not this one.
     */
    private function canResign(User $currentUser, SecuritySupervisorAssignment $assignment): bool
    {
        return $assignment->user_id === $currentUser->id
            && $assignment->is_active
            && (! $assignment->end_date || $assignment->end_date->isFuture());
    }

    // ================================================================
    // FILTERS
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

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', fn ($q) =>
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

    // ================================================================
    // NOTIFICATIONS
    // ================================================================

    /**
     * Best-effort admin notification on resignation.
     * Wrapped in try/catch — a notification failure never blocks the resignation.
     */
    private function notifyAdminsOfResignation(
        SecuritySupervisorAssignment $assignment,
        string $officerName,
        string $reason
    ): void {
        try {
            $notificationClass = \App\Notifications\HeadOfSecurityResigned::class;

            if (! class_exists($notificationClass)) {
                Log::info('Resignation notification class not found — skipping.', [
                    'assignment_id' => $assignment->id,
                ]);
                return;
            }

            User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])
                ->where('status', User::STATUS_ACTIVE)
                ->get()
                ->each(fn ($admin) => $admin->notify(
                    new $notificationClass($assignment, $officerName, $reason)
                ));
        } catch (\Throwable $e) {
            Log::warning('Failed to notify admins of Head-of-Security resignation', [
                'assignment_id' => $assignment->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}