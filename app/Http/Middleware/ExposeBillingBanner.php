<?php

namespace App\Http\Middleware;

use App\Services\BillingAccessService;
use Closure;
use Illuminate\Http\Request;

class ExposeBillingBanner
{
    protected BillingAccessService $billing;

    public function __construct(BillingAccessService $billing)
    {
        $this->billing = $billing;
    }

    public function handle(Request $request, Closure $next)
    {
        // Only compute on web (skip API/console noise)
        if (!$request->is('api/*')) {
            try {
                $payload = $this->billing->bannerPayload();

                view()->share('billingBanner', $payload);

                if ($payload['visible']) {
                    view()->share('billingState', $payload['state']);
                }
            } catch (\Throwable $e) {
                // Never break a request because of a banner
                report($e);
                view()->share('billingBanner', ['visible' => false]);
            }
        }

        return $next($request);
    }
}