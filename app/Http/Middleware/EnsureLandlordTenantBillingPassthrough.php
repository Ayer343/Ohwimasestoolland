<?php

namespace App\Http\Middleware;

use App\Services\BillingAccessService;
use Closure;
use Illuminate\Http\Request;

class EnsureLandlordTenantBillingPassthrough
{
    protected BillingAccessService $billing;

    public function __construct(BillingAccessService $billing)
    {
        $this->billing = $billing;
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !in_array($user->type, [
            \App\Models\User::TYPE_LANDLORD,
            \App\Models\User::TYPE_TENANT,
        ], true)) {
            return $next($request);
        }

        // Hard lock is opt-in only.
        if ($this->billing->shouldHardLockLandlordOrTenant($user)) {
            $routeName = $request->route()?->getName() ?? '';

            if ($this->billing->isAllowedForHardLockedTenantLandlord($routeName)) {
                return $next($request);
            }

            return redirect()->route('billing.suspended');
        }

        // Passthrough — but share a soft notice so views can show a banner.
        if ($this->billing->shouldRestrictSuperAdmin()) {
            view()->share('systemBillingNotice', true);
        }

        return $next($request);
    }
}