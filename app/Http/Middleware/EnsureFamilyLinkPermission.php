<?php
// app/Http/Middleware/EnsureFamilyLinkPermission.php

namespace App\Http\Middleware;

use App\Models\Property;
use App\Models\PropertyFamilyLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFamilyLinkPermission
{
    /**
     * Usage on a route:
     *
     *   Route::middleware(['family.link.active', 'family.link.permission:edit'])
     *       ->put('landlord/properties/{property}/construction', ...);
     *
     * The middleware runs AFTER family.link.active so ownership has
     * already been verified. If the current user is the owner, they
     * pass. Otherwise the linked family member's `permissions` array
     * must contain the requested permission.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user     = $request->user();
        $property = $request->route('property');

        if (!$property instanceof Property) {
            $property = Property::find($property);
        }

        // Owner short-circuit
        if ($property && (int) $property->landlord_id === (int) $user->id) {
            return $next($request);
        }

        // Linked family member — check permissions
        $link = PropertyFamilyLink::query()
            ->where('property_id', $property?->id)
            ->where('linked_user_id', $user->id)
            ->where('status', PropertyFamilyLink::STATUS_APPROVED)
            ->first();

        $perms = is_array($link?->permissions) ? $link->permissions : [];

        if (!in_array($permission, $perms, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your family link does not grant this action.',
                ], 403);
            }
            abort(403, 'Your family link does not grant this action.');
        }

        return $next($request);
    }
}