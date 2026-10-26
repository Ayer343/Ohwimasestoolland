<?php

namespace App\Http\Middleware;

use App\Models\AdCampaign;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdCampaignOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        // The route parameter can be `{uuid}`, `{campaign}` (a UUID), or
        // `{id}` (a numeric id). Support all three.
        $identifier = $request->route('uuid')
            ?? $request->route('campaign')
            ?? $request->route('id');

        if (!$identifier) {
            // If there's no identifier, this middleware isn't applicable —
            // let the request through and let the controller deal with it.
            return $next($request);
        }

        $campaign = is_numeric($identifier)
            ? AdCampaign::find($identifier)
            : AdCampaign::where('uuid', $identifier)->first();

        if (!$campaign) {
            abort(404);
        }

        // Admins/super-admins bypass the ownership check.
        $isAdmin = method_exists($user, 'isAdmin') && $user->isAdmin();
        $isSuperAdmin = method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

        if ($isAdmin || $isSuperAdmin) {
            return $next($request);
        }

        if ((int) $campaign->landlord_id !== (int) $user->id) {
            abort(403, 'You do not have permission to manage this campaign.');
        }

        return $next($request);
    }
}