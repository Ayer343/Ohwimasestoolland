<?php
// app/Http/Middleware/EnsureFamilyLinkIsActive.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\PropertyFamilyLink;

class EnsureFamilyLinkIsActive
{
    /**
     * Allow the request only if the authenticated user has an approved,
     * not-revoked family link. Otherwise 403 / redirect.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Authentication required.'], 401)
                : redirect()->route('login');
        }

        $hasActiveLink = PropertyFamilyLink::query()
            ->where('linked_user_id', $user->id)
            ->where('status', PropertyFamilyLink::STATUS_APPROVED)
            ->exists();

        if (!$hasActiveLink) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active family link for this account.',
                ], 403);
            }

            abort(403, 'No active family link for this account.');
        }

        return $next($request);
    }
}