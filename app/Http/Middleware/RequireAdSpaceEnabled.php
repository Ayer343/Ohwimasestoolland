<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdSpaceEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        // Defensive: if settings or the ad config aren't available, fail closed.
        try {
            $settings = SystemSetting::getSettings();
            $enabled  = method_exists($settings, 'isAdSpaceEnabled')
                && $settings->isAdSpaceEnabled();
        } catch (\Throwable $e) {
            $enabled = false;
        }

        if (!$enabled) {
            // For AJAX/JSON, return JSON so clients don't get HTML.
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The AD space feature is currently disabled.',
                ], 404);
            }

            // Landlord dashboard → redirect to their dashboard with a flash.
            if ($request->is('landlord/*')) {
                return redirect()
                    ->route('landlord.dashboard')
                    ->with('error', 'The AD space feature is currently disabled by the administrator.');
            }

            abort(404, 'The AD space feature is currently disabled.');
        }

        return $next($request);
    }
}