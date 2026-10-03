<?php

namespace App\Http\Middleware;

use App\Services\BillingAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EnsureSuperAdminBillingAccess
{
    protected BillingAccessService $billing;

    public function __construct(BillingAccessService $billing)
    {
        $this->billing = $billing;
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // ---------------- Super admin ----------------
        if ($user->type === \App\Models\User::TYPE_SUPER_ADMIN
            && $this->billing->shouldRestrictSuperAdmin($user)) {

            $routeName = $request->route()?->getName() ?? '';

            if ($this->billing->isAllowedForRestrictedSuperAdmin($routeName)) {
                return $next($request);
            }

            Log::info('[BillingAccess] Super admin redirected to billing dashboard', [
                'user_id'    => $user->id,
                'route'      => $routeName,
                'state'      => $this->billing->currentDeveloper()?->billing_state,
            ]);

            return redirect()
                ->route('superadmin.billing.dashboard')
                ->with('error',
                    'Your system billing is overdue. '
                    . 'Settle your invoice with the developer to restore full access.');
        }

        // ---------------- Admin (staff) — read-only ----------------
        if ($user->type === \App\Models\User::TYPE_ADMIN
            && $this->billing->shouldRestrictAdminToReadOnly($user)) {

            $method = $request->method();

            if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
                Log::info('[BillingAccess] Admin blocked from write (middleware)', [
                    'user_id' => $user->id,
                    'method'  => $method,
                    'route'   => $request->route()?->getName(),
                ]);

                abort(403,
                    'Administrative actions are locked while the system billing is overdue. '
                    . 'Please contact your super admin.');
            }

            view()->share('billingReadOnly', true);
            $request->attributes->set('billing_read_only', true);
        }

        return $next($request);
    }
}