<?php

namespace App\Http\Controllers;

use App\Models\PropertyUnit;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PropertyUnitPublicController extends Controller
{
    public function show(Request $request, int $id)
    {
        // ============================================================
        // ✅ IMPROVED: Parent-property visibility check.
        //
        //    The original only checked the UNIT's soft-delete +
        //    archive state — but a trashed/archived PROPERTY could
        //    still leak its units publicly. The whereHas() below
        //    enforces the same rules on the parent property.
        //
        // ✅ IMPROVED: Eager-load `property.registrationPlan` too —
        //    the show blade now uses region/area off the property,
        //    and registrationPlan is surfaced in the plan summary.
        // ============================================================
        $unit = PropertyUnit::with([
                'property.landlord',
                'property.registrationPlan',
                'photos',
            ])
            ->withoutTrashed()
            ->where('is_archived', false)
            ->whereHas('property', function ($q) {
                // Mirror the parent property's own visibility rules so
                // trashed/archived properties can't leak their units.
                $q->whereNull('deleted_at')
                  ->where('is_archived', false);
            })
            ->findOrFail($id);

        // ============================================================
        // ✅ IMPROVED: Occupied-unit handling.
        //
        //    A rented unit is a privacy concern — a stranger could use
        //    the address to find who lives there. We redirect rather
        //    than dead-ending with a generic 404, so the visitor has a
        //    path forward.
        //
        //    Fallback to 410 Gone if the search route doesn't exist —
        //    preserves the no-leak posture without breaking the page.
        // ============================================================
        if ($unit->status === PropertyUnit::STATUS_OCCUPIED) {
            try {
                return redirect()
                    ->route('property-units.search')
                    ->with('info', 'That unit has been rented. Here are similar listings.');
            } catch (\Throwable $e) {
                // Search route not configured — fall back to 410 Gone,
                // which is semantically correct ("this existed, it's gone")
                // and avoids dead-ending the user on a misleading 404.
                abort(410, 'This unit has been rented.');
            }
        }

        $systemSettings = SystemSetting::getSettings();

        // ============================================================
        // ✅ IMPROVED: Throttled view tracking.
        //
        //    Increment once per session per unit — avoids a single
        //    bot or refreshing visitor from skewing analytics. Wrapped
        //    in try/catch so a DB write failure never blocks the
        //    render (analytics should never break the page).
        // ============================================================
        try {
            $viewedUnitIds = session()->get('viewed_unit_ids', []);

            if (!in_array($unit->id, $viewedUnitIds, true)) {
                $unit->increment('views_count');

                $viewedUnitIds[] = $unit->id;
                session()->put('viewed_unit_ids', $viewedUnitIds);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to increment unit view count', [
                'unit_id' => $unit->id,
                'error'   => $e->getMessage(),
            ]);
        }

        // ============================================================
        // ✅ NEW: Structured data (JSON-LD) for SEO.
        //
        //    Enables Google rich snippets:
        //      "₵2,500/month · East Legon, Greater Accra"
        //    directly in search results. Uses the region/area fields
        //    we added to the Property model earlier.
        // ============================================================
        $structuredData = $this->buildStructuredData($unit);

        // ============================================================
        // ✅ NEW: Similar units for the "You might also like" section.
        //    Prefers same area → same region → same landlord.
        //    Only fires for AVAILABLE units (won't show rented ones).
        // ============================================================
        $similarUnits = $this->getSimilarUnits($unit);

        // ============================================================
        // ✅ IMPROVED: Landlord verification flag.
        //    The blade should only show "Verified landlord" when the
        //    landlord is actually verified. Otherwise the badge is a
        //    lie that erodes trust when users find out.
        // ============================================================
        $landlordVerified = $this->isLandlordVerified($unit);

        // ============================================================
        // ✅ NEW: Cache headers for a public, anonymous, read-mostly
        //    page. 5-minute browser cache, 10-minute CDN cache. Safe
        //    because the page is determined entirely by the URL and
        //    the ad/unit data — no per-user content.
        // ============================================================
        return response()
            ->view('property_units.public-show', [
                'unit'             => $unit,
                'systemSettings'   => $systemSettings,
                'structuredData'   => $structuredData,
                'similarUnits'     => $similarUnits,
                'landlordVerified' => $landlordVerified,
            ])
            ->header('Cache-Control', 'public, max-age=300, s-maxage=600');
    }

    // ================================================================
    //  HELPERS
    // ================================================================

    /**
     * ✅ NEW: Build JSON-LD structured data for the unit.
     *
     * Google uses this for rich snippets. The nested structure follows
     * schema.org's Apartment / Offer pattern, which surfaces price and
     * location in search results.
     */
    protected function buildStructuredData(PropertyUnit $unit): array
    {
        $property = $unit->property;
        $landlord = $property?->landlord;

        $unitLabel = $unit->unit_name
            ?? $unit->unit_number
            ?? ('Unit #' . $unit->id);

        $data = [
            '@context' => 'https://schema.org',
            '@type'    => 'Apartment',
            'name'     => $unitLabel,
            'url'      => url()->current(),
        ];

        // Address — prefer granular region/area (via the property), fall
        // back to the flat `address` string if those aren't populated.
        if ($property) {
            $address = ['@type' => 'PostalAddress', 'addressCountry' => 'GH'];

            if ($property->street_name)  $address['streetAddress']   = $property->street_name;
            if ($property->area)         $address['addressLocality'] = $property->area;
            if ($property->region)       $address['addressRegion']   = $property->region;
            if (!$property->area && !$property->region && $property->address) {
                $address['streetAddress'] = $property->address;
            }

            $data['address'] = $address;
        }

        // Offer — only when we have a price.
        if ($unit->monthly_rent) {
            $data['offers'] = [
                '@type'         => 'Offer',
                'price'         => (float) $unit->monthly_rent,
                'priceCurrency' => $unit->currency ?? 'GHS',
                'availability'  => $unit->status === PropertyUnit::STATUS_AVAILABLE
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ];
        }

        // Landlord as the "seller" / contact.
        if ($landlord) {
            $data['provider'] = [
                '@type' => 'Person',
                'name'  => $landlord->name,
            ];
        }

        return $data;
    }

    /**
     * ✅ NEW: Find units similar to the one being viewed.
     *
     * Matching priority (first non-empty set wins):
     *   1. Same area (suburb) + same region
     *   2. Same region
     *   3. Same landlord
     *   4. (skip — no good match, return empty)
     *
     * Only returns AVAILABLE units, so a visitor who landed on an
     * occupied unit and got redirected here would still see live options.
     */
    protected function getSimilarUnits(PropertyUnit $unit, int $limit = 3)
    {
        $baseQuery = PropertyUnit::with(['property.landlord', 'photos'])
            ->withoutTrashed()
            ->where('is_archived', false)
            ->where('id', '!=', $unit->id)
            ->where('status', PropertyUnit::STATUS_AVAILABLE)
            ->whereHas('property', function ($q) {
                $q->whereNull('deleted_at')->where('is_archived', false);
            });

        $property = $unit->property;

        // Attempt 1: same area + region
        if ($property?->area && $property?->region) {
            $matches = (clone $baseQuery)
                ->whereHas('property', function ($q) use ($property) {
                    $q->where('area', $property->area)
                      ->where('region', $property->region);
                })
                ->inRandomOrder()
                ->limit($limit)
                ->get();

            if ($matches->isNotEmpty()) {
                return $matches;
            }
        }

        // Attempt 2: same region
        if ($property?->region) {
            $matches = (clone $baseQuery)
                ->whereHas('property', function ($q) use ($property) {
                    $q->where('region', $property->region);
                })
                ->inRandomOrder()
                ->limit($limit)
                ->get();

            if ($matches->isNotEmpty()) {
                return $matches;
            }
        }

        // Attempt 3: same landlord
        if ($property?->landlord_id) {
            $matches = (clone $baseQuery)
                ->whereHas('property', function ($q) use ($property) {
                    $q->where('landlord_id', $property->landlord_id);
                })
                ->inRandomOrder()
                ->limit($limit)
                ->get();

            if ($matches->isNotEmpty()) {
                return $matches;
            }
        }

        // No good match found — empty collection, blade handles gracefully.
        return collect();
    }

    /**
     * ✅ NEW: Is the landlord verified?
     *
     * Defensive — works with any of the common verification column names
     * so this controller doesn't break across schema variations.
     */
    protected function isLandlordVerified(PropertyUnit $unit): bool
    {
        $landlord = $unit->property?->landlord;
        if (!$landlord) {
            return false;
        }

        // Check any of the common verification flags without assuming
        // a specific column name.
        foreach (['verified_at', 'is_verified', 'email_verified_at'] as $column) {
            if (isset($landlord->{$column}) && $landlord->{$column} !== null && $landlord->{$column} !== false) {
                return true;
            }
        }

        return false;
    }
}