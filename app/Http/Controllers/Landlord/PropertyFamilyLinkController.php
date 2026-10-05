<?php
// app/Http/Controllers/Landlord/PropertyFamilyLinkController.php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StoreFamilyLinkRequest;
use App\Models\Property;
use App\Models\PropertyFamilyLink;
use App\Services\PropertyFamilyLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PropertyFamilyLinkController extends Controller
{
    public function __construct(
        protected PropertyFamilyLinkService $service
    ) {}

    /* ================================================================
       INDEX — all family links across the landlord's properties
       ================================================================ */

    /**
     * Show all family links across all of the landlord's properties.
     *
     * Loads a paginated list plus a single grouped COUNT query for the
     * stats cards. Stats include both new pending stages so the
     * landlord can see how many links need their own confirmation
     * versus how many are already with admins.
     */
    public function index(Request $request)
    {
        $landlord = auth()->user();

        $links = PropertyFamilyLink::with([
                'property',
                'linkedUser',
                'reviewer',
                'landlordConfirmer',
            ])
            ->where('landlord_id', $landlord->id)
            ->when($request->filled('status'), fn ($q) =>
                $q->where('status', $request->status)
            )
            ->when($request->filled('property_id'), fn ($q) =>
                $q->where('property_id', $request->property_id)
            )
            ->latest()
            ->paginate(15);

        // -------------------------------------------------------------
        // Single grouped query → one round trip instead of many.
        // -------------------------------------------------------------
        $rawStats = PropertyFamilyLink::where('landlord_id', $landlord->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $awaitingLandlord = (int) ($rawStats[PropertyFamilyLink::STATUS_PENDING_LANDLORD] ?? 0);
        $awaitingAdmin    = (int) ($rawStats[PropertyFamilyLink::STATUS_PENDING_ADMIN] ?? 0);
        $legacyPending    = (int) ($rawStats[PropertyFamilyLink::STATUS_PENDING] ?? 0);

        $stats = [
            'total'               => array_sum($rawStats),
            'awaiting_landlord'   => $awaitingLandlord,
            'awaiting_admin'      => $awaitingAdmin,
            'pending'             => $awaitingLandlord + $awaitingAdmin + $legacyPending,
            'approved'            => (int) ($rawStats[PropertyFamilyLink::STATUS_APPROVED] ?? 0),
            'rejected'            => (int) ($rawStats[PropertyFamilyLink::STATUS_REJECTED] ?? 0),
            'revoked'             => (int) ($rawStats[PropertyFamilyLink::STATUS_REVOKED] ?? 0),
            'cancelled'           => (int) ($rawStats[PropertyFamilyLink::STATUS_CANCELLED] ?? 0),
        ];

        $properties = Property::where('landlord_id', $landlord->id)
            ->orderBy('property_name')
            ->get(['id', 'property_name', 'digital_address']);

        return view('landlord.family-links.index', compact(
            'links', 'stats', 'properties'
        ));
    }

    /* ================================================================
       FOR PROPERTY — family links for one specific property
       ================================================================ */

    /**
     * Show family links for a specific property.
     */
    public function forProperty(Property $property)
    {
        $this->authorizePropertyOwnership($property);

        $links = $property->familyLinks()
            ->with(['linkedUser', 'reviewer', 'landlordConfirmer'])
            ->latest()
            ->get();

        $permissions   = config('property_family_links.permissions');
        $relationships = config('property_family_links.relationship_options');

        return view('landlord.family-links.for-property', compact(
            'property', 'links', 'permissions', 'relationships'
        ));
    }

    /* ================================================================
       STORE — propose a new family member
       ================================================================ */

    /**
     * Store a new proposed link.
     *
     * The service decides the initial status:
     *   - Default:    pending_landlord_confirmation
     *   - Auto-confirm: pending_admin_review
     *
     * The success message reflects which path was taken so the
     * landlord knows what to expect next.
     */
    public function store(StoreFamilyLinkRequest $request)
    {
        $landlord = auth()->user();
        $property = Property::findOrFail($request->property_id);

        // Defensive: Request already validates ownership, but double-check.
        if ($property->landlord_id !== $landlord->id) {
            return $this->unauthorized($request, 'You do not own this property.');
        }

        try {
            $link = $this->service->propose($property, $request->validated(), $landlord);

            $message = match ($link->status) {
                PropertyFamilyLink::STATUS_PENDING_LANDLORD =>
                    'Proposal submitted. Please confirm it to send it to administrators for review.',

                PropertyFamilyLink::STATUS_PENDING_ADMIN =>
                    'Proposal submitted. An administrator will review it shortly.',

                PropertyFamilyLink::STATUS_APPROVED =>
                    'Family member linked successfully.',

                default => 'Proposal submitted.',
            };

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'link'    => $link,
                ], 201);
            }

            return redirect()
                ->route('landlord.properties.family-links', $property->id)
                ->with('success', $message);

        } catch (\Throwable $e) {
            Log::error('Failed to propose family link', [
                'property_id' => $property->id,
                'landlord_id' => $landlord->id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to submit proposal. Please try again.',
                ], 500);
            }

            return back()->with('error', 'Failed to submit proposal.')->withInput();
        }
    }

    /* ================================================================
       CONFIRM — landlord confirms their own proposal (Gate 1)
       ================================================================ */

    /**
     * Landlord confirms a proposal they previously submitted.
     *
     * Transitions: pending_landlord_confirmation → pending_admin_review.
     * After this fires, admins are notified (via the event listener)
     * and can review the proposal.
     *
     * This is the FIRST gate. Admin approval is the SECOND gate and
     * remains a hard requirement even for auto-confirmed relationships
     * (which skip this method entirely at submit time).
     */
    public function confirm(Request $request, PropertyFamilyLink $link)
    {
        // Ownership gate
        if ((int) $link->landlord_id !== (int) auth()->id()) {
            return $this->unauthorized($request, 'Unauthorized.');
        }

        // State gate — must be awaiting landlord confirmation
        if (!$link->isAwaitingLandlordConfirmation()) {
            return $this->unauthorized(
                $request,
                'This proposal is not awaiting your confirmation.'
            );
        }

        try {
            $link = $this->service->confirmByLandlord($link, auth()->user());

            $message = 'Proposal confirmed. It has been sent to administrators for review.';

            // Audit trail — record the confirmation explicitly
            Log::channel('audit')->info('Family link confirmed by landlord', [
                'link_id'      => $link->id,
                'property_id'  => $link->property_id,
                'landlord_id'  => $link->landlord_id,
                'confirmed_at' => now()->toIso8601String(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'link'    => $link,
                ]);
            }

            return back()->with('success', $message);

        } catch (\Throwable $e) {
            Log::error('Failed to confirm family link', [
                'link_id' => $link->id,
                'error'   => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to confirm proposal. Please try again.',
                ], 500);
            }

            return back()->with('error', 'Failed to confirm proposal. Please try again.');
        }
    }

    /* ================================================================
       REVOKE — landlord revokes an approved link
       ================================================================ */

    /**
     * Landlord revokes an approved link.
     *
     * Only `approved` links can be revoked. Pending proposals should
     * use `cancel()` instead — the semantic difference matters for
     * the audit trail.
     */
    public function revoke(Request $request, PropertyFamilyLink $link)
    {
        if ((int) $link->landlord_id !== (int) auth()->id()) {
            return $this->unauthorized($request, 'Unauthorized.');
        }

        if (!$link->isApproved()) {
            return $this->unauthorized(
                $request,
                'Only approved links can be revoked.'
            );
        }

        try {
            $reason = $request->input('reason', 'Revoked by landlord');

            $this->service->revoke($link, auth()->user(), $reason);

            Log::channel('audit')->info('Family link revoked by landlord', [
                'link_id'      => $link->id,
                'property_id'  => $link->property_id,
                'landlord_id'  => $link->landlord_id,
                'reason'       => $reason,
                'revoked_at'   => now()->toIso8601String(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Family link revoked.',
                ]);
            }

            return back()->with('success', 'Family link revoked.');

        } catch (\Throwable $e) {
            Log::error('Failed to revoke family link', [
                'link_id'     => $link->id,
                'landlord_id' => auth()->id(),
                'error'       => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to revoke link. Please try again.',
                ], 500);
            }

            return back()->with('error', 'Failed to revoke link. Please try again.');
        }
    }

    /* ================================================================
       CANCEL — landlord cancels a pending proposal
       ================================================================ */

    /**
     * Landlord cancels a pending proposal.
     *
     * Applies to ANY pending stage (landlord confirmation OR admin
     * review). Sets status to `cancelled` for the audit trail, then
     * soft-deletes the row so it disappears from the active list but
     * remains queryable.
     */
    public function cancel(Request $request, PropertyFamilyLink $link)
    {
        if ((int) $link->landlord_id !== (int) auth()->id() || !$link->isPending()) {
            return $this->unauthorized(
                $request,
                'Unauthorized or link already reviewed.'
            );
        }

        try {
            // -------------------------------------------------------------
            // Audit log BEFORE the soft delete so the trailing metadata
            // (proposed_name, phone, email) is still attached to the row
            // when the log entry is written.
            // -------------------------------------------------------------
            Log::channel('audit')->info('Family link proposal cancelled', [
                'link_id'        => $link->id,
                'property_id'    => $link->property_id,
                'landlord_id'    => $link->landlord_id,
                'proposed_name'  => $link->proposed_name,
                'proposed_phone' => $link->proposed_phone,
                'proposed_email' => $link->proposed_email,
                'relationship'   => $link->relationship,
                'previous_status'=> $link->status,
                'cancelled_by'   => auth()->id(),
                'cancelled_at'   => now()->toIso8601String(),
            ]);

            // Mark as cancelled before soft-deleting so audit tools that
            // query withTrashed() can distinguish cancel from hard-delete.
            $link->update(['status' => PropertyFamilyLink::STATUS_CANCELLED]);
            $link->delete();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Proposal cancelled.',
                ]);
            }

            return back()->with('success', 'Proposal cancelled.');

        } catch (\Throwable $e) {
            Log::error('Failed to cancel family link proposal', [
                'link_id'     => $link->id,
                'landlord_id' => auth()->id(),
                'error'       => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel proposal. Please try again.',
                ], 500);
            }

            return back()->with('error', 'Failed to cancel proposal. Please try again.');
        }
    }

    /* ================================================================
       HELPERS
       ================================================================ */

    /**
     * Abort with 403 if the property does not belong to the current user.
     */
    protected function authorizePropertyOwnership(Property $property): void
    {
        if ((int) $property->landlord_id !== (int) auth()->id()) {
            abort(403, 'You do not own this property.');
        }
    }

    /**
     * Dual-response 403 helper (JSON for AJAX, redirect-with-flash otherwise).
     */
    protected function unauthorized(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 403);
        }

        return back()->with('error', $message);
    }
}