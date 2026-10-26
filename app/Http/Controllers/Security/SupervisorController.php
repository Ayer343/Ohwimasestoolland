<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\SecuritySupervisorAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class SupervisorController extends Controller
{
    /**
     * Landing page for the active Head of Security.
     *
     * Surfaces:
     *   - the current appointment (summary card + link to detail)
     *   - the team of active security personnel they oversee
     *   - the last 5 appointments they held (context)
     *
     * Everything else — edit, extend, terminate, approve, verify,
     * post schedule, team attendance — lives either on the admin
     * appointment flow or has been removed from the simplified
     * single-head model.
     */
    public function dashboard()
    {
        $user = Auth::user();

        $activeAssignment = SecuritySupervisorAssignment::where('user_id', $user->id)
            ->active()
            ->with(['assignedBy'])
            ->first();

        // Fall back gracefully: no active appointment → appointment list.
        if (! $activeAssignment) {
            return redirect()
                ->route('security.supervisor-assignments.index')
                ->with('info', 'You are not currently Head of Security.');
        }

        $team = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'photo', 'status', 'can_be_supervisor']);

        $history = SecuritySupervisorAssignment::where('user_id', $user->id)
            ->orderByDesc('start_date')
            ->limit(5)
            ->with(['assignedBy'])
            ->get();

        // Lightweight counters for the dashboard cards.
        $counters = [
            'team_size'         => $team->count(),
            'days_active'       => $activeAssignment->start_date
                ? (int) $activeAssignment->start_date->diffInDays(now())
                : 0,
            'days_left'         => $activeAssignment->end_date
                ? max(0, (int) now()->diffInDays($activeAssignment->end_date, false))
                : null,
            'total_appointments' => SecuritySupervisorAssignment::where('user_id', $user->id)->count(),
        ];

        return view('security.supervisor.dashboard', compact(
            'activeAssignment', 'team', 'history', 'counters'
        ));
    }
}