<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EnsureRootSanitationAccess
{
    /**
     * Only allow THE ROOT sanitation supervisor.
     *
     * Root supervisor = a SanitationPersonnel record whose:
     *   1. `role` is in SanitationPersonnel::SUPERVISOR_ROLES (supervisor tier), AND
     *   2. `supervisor_id` is NULL (top of the reporting tree)
     *
     * Admins and Super Admins are NOT granted automatic access.
     * They must also hold the root-supervisor personnel record.
     * Everyone else gets 403.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Not authenticated → let the `auth` middleware handle it
        if (!$user) {
            return redirect()->route('login');
        }

        $personnel = $user->sanitationPersonnel;

        // ✅ Root sanitation supervisor only
        if ($personnel
            && $personnel->isSupervisor()
            && is_null($personnel->supervisor_id)) {
            return $next($request);
        }

        // Audit + deny (including admins who aren't the root supervisor)
        Log::warning('Root sanitation access denied', [
            'user_id'         => $user->id,
            'user_name'       => $user->name,
            'user_type'       => $user->type,
            'is_super_admin'  => $user->isSuperAdmin(),
            'is_admin'        => $user->isAdmin(),
            'personnel_id'    => $personnel?->id,
            'personnel_role'  => $personnel?->role,
            'supervisor_id'   => $personnel?->supervisor_id,
            'route'           => $request->route()?->getName(),
            'url'             => $request->fullUrl(),
            'ip'              => $request->ip(),
        ]);

        abort(403, 'Only the root sanitation supervisor can access sanitation settings.');
    }
}