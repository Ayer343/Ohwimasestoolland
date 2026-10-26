<?php

namespace App\Http\Middleware;

use App\Models\AdCampaign;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackAdCampaignView
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only track GET requests that reach the controller successfully.
        // We track BEFORE the response so a slow render still counts the view.
        if ($request->isMethod('GET')) {
            try {
                $campaignUuid = $request->query('campaign')
                    ?? $request->query('ad_uuid')
                    ?? $request->headers->get('referer-uuid');

                // Fallback: infer from referer URL if it's an ad-click URL.
                if (!$campaignUuid && $referer = $request->headers->get('referer')) {
                    if (preg_match('#/ads/([a-f0-9-]{36})/click#', (string) $referer, $m)) {
                        $campaignUuid = $m[1];
                    }
                }

                if ($campaignUuid) {
                    $campaign = AdCampaign::where('uuid', $campaignUuid)->first();
                    if ($campaign && $campaign->isActive()) {
                        $campaign->increment('views_count');
                    }
                }
            } catch (\Throwable $e) {
                // Never break a request because analytics failed.
                // Log::debug('Ad view tracking failed: ' . $e->getMessage());
            }
        }

        return $next($request);
    }
}