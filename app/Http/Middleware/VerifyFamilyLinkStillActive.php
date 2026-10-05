<?php

namespace App\Http\Middleware;

use App\Models\Property;
use App\Models\PropertyFamilyLink;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyFamilyLinkStillActive
{
    /**
     * Route parameters that may resolve to a Property instance.
     *
     * The middleware auto-detects which one is present so the same
     * class can guard routes like:
     *   /properties/{property}
     *   /landlord/properties/{site_allocation}
     *   /admin/properties/{prop}/edit
     */
    protected const PROPERTY_ROUTE_KEYS = [
        'property',
        'site_allocation',
        'prop',
    ];

    /**
     * Handle an incoming request.
     *
     * Guarantees that any landlord who reaches a property-related
     * route either:
     *   (a) owns the property,
     *   (b) is an admin / super admin, or
     *   (c) has an APPROVED family link granting at least `view`.
     *
     * Revoked, rejected, or never-existed links yield 403.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // No authenticated user → let the auth middleware handle it.
        if (!$user) {
            return $next($request);
        }

        $property = $this->resolvePropertyFromRoute($request);

        // Not a property-scoped route → nothing to verify.
        if (!$property instanceof Property) {
            return $next($request);
        }

        // ----------------------------------------------------------
        // 1. Administrators bypass all checks.
        // ----------------------------------------------------------
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return $next($request);
        }

        // ----------------------------------------------------------
        // 2. Landlord who OWNS the property → pass through.
        // ----------------------------------------------------------
        if ($user->isLandlord() && (int) $property->landlord_id === (int) $user->id) {
            return $next($request);
        }

        // ----------------------------------------------------------
        // 3. Field agent checks (unchanged from policy semantics).
        //    Registered-by is a pass; anything else is denied.
        // ----------------------------------------------------------
        if ($user->isFieldAgent()) {
            if ((int) $property->registered_by === (int) $user->id) {
                return $next($request);
            }

            Log::warning('Family-link middleware: field agent denied', [
                'user_id'         => $user->id,
                'property_id'     => $property->id,
                'registered_by'   => $property->registered_by,
            ]);

            return $this->deny($request, 'You do not have access to this property.');
        }

        // ----------------------------------------------------------
        // 4. Linked family member → must have an APPROVED link
        //    with at least the `view` permission.
        // ----------------------------------------------------------
        $link = $this->resolveApprovedFamilyLink($property, $user->id);

        if ($link && $this->linkHasPermission($link, 'view')) {
            return $next($request);
        }

        // ----------------------------------------------------------
        // 5. If the user previously had a link that is now revoked
        //    or rejected, surface a more specific message.
        // ----------------------------------------------------------
        if ($link === null && $this->hadRevokedOrRejectedLink($property, $user->id)) {
            Log::info('Family-link middleware: revoked/rejected link blocked access', [
                'user_id'     => $user->id,
                'property_id' => $property->id,
            ]);

            return $this->deny(
                $request,
                'Your access to this property has been revoked.'
            );
        }

        // ----------------------------------------------------------
        // 6. Fallback: no ownership, no admin, no approved link.
        // ----------------------------------------------------------
        Log::info('Family-link middleware: access denied', [
            'user_id'     => $user->id,
            'user_type'   => $user->type,
            'property_id' => $property->id,
        ]);

        return $this->deny($request, 'You do not have access to this property.');
    }

    /* ================================================================
       RESOLUTION HELPERS
       ================================================================ */

    /**
     * Find a Property instance among the route parameters.
     *
     * Uses the parameter name first, then falls back to scanning for
     * any value that is already a Property model.
     */
    protected function resolvePropertyFromRoute(Request $request): ?Property
    {
        foreach (self::PROPERTY_ROUTE_KEYS as $key) {
            $value = $request->route($key);

            if ($value instanceof Property) {
                return $value;
            }
        }

        // Fallback: scan all route parameters for a Property instance.
        foreach ($request->route()?->parameters() ?? [] as $value) {
            if ($value instanceof Property) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Find an approved family link for this user on this property.
     */
    protected function resolveApprovedFamilyLink(Property $property, int $userId): ?PropertyFamilyLink
    {
        return PropertyFamilyLink::query()
            ->where('property_id', $property->id)
            ->where('linked_user_id', $userId)
            ->where('status', 'approved')
            ->first();
    }

    /**
     * Was there a revoked or rejected link that would explain the denial?
     */
    protected function hadRevokedOrRejectedLink(Property $property, int $userId): bool
    {
        return PropertyFamilyLink::query()
            ->where('property_id', $property->id)
            ->where('linked_user_id', $userId)
            ->whereIn('status', ['revoked', 'rejected'])
            ->exists();
    }

    /**
     * Does the given link include the requested permission?
     *
     * Handles JSON arrays, PHP arrays, and comma-separated strings.
     */
    protected function linkHasPermission(PropertyFamilyLink $link, string $permission): bool
    {
        $permissions = $link->permissions ?? [];

        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $permissions = $decoded;
            } else {
                $permissions = array_filter(array_map('trim', explode(',', $permissions)));
            }
        }

        if (!is_array($permissions)) {
            return false;
        }

        return in_array($permission, $permissions, true);
    }

    /* ================================================================
       RESPONSE HELPERS
       ================================================================ */

    /**
     * Return a 403 for AJAX/JSON requests, or redirect back with an
     * error flash for standard web requests.
     */
    protected function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'reason'  => 'family_link_access_denied',
            ], 403);
        }

        return redirect()->back()
            ->with('error', $message);
    }
}