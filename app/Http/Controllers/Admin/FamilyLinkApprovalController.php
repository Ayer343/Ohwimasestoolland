<?php
// app/Http/Controllers/Admin/FamilyLinkApprovalController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewFamilyLinkRequest;
use App\Models\PropertyFamilyLink;
use App\Services\PropertyFamilyLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FamilyLinkApprovalController extends Controller
{
    public function __construct(
        protected PropertyFamilyLinkService $service
    ) {}

    /* ================================================================
       INDEX — Admin review queue
       ================================================================ */

    /**
     * Show the admin review queue.
     *
     * The view consumes a paginated `$links` (LengthAwarePaginator)
     * and calls `firstItem()`, `lastItem()`, `total()`, and `links()`
     * on it. Any future refactor that changes `$links` to a plain
     * Collection will break the view — keep `paginate()` here.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $perPage = (int) $request->input('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 25, 50, 100], true) ? $perPage : 20;

        $links = PropertyFamilyLink::with([
                'property.landlord',
                'landlord',
                'linkedUser',
                'reviewer',
                'landlordConfirmer',
            ])
            ->when($request->filled('status'), fn ($q) =>
                $q->where('status', $request->status)
            , fn ($q) => $q->whereIn('status', [
                PropertyFamilyLink::STATUS_PENDING_ADMIN,
                PropertyFamilyLink::STATUS_PENDING_LANDLORD,
                PropertyFamilyLink::STATUS_PENDING,
            ]))
            ->when($request->filled('relationship'), fn ($q) =>
                $q->where('relationship', $request->relationship)
            )
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($qq) use ($s) {
                    $qq->where('proposed_name', 'like', "%{$s}%")
                       ->orWhere('proposed_phone', 'like', "%{$s}%")
                       ->orWhere('proposed_email', 'like', "%{$s}%");
                });
            })
            ->latest()
            ->paginate($perPage);

        // -------------------------------------------------------------
        // Single grouped query for stats — same pattern as the landlord
        // controller. One round trip, no per-status COUNTs.
        // -------------------------------------------------------------
        $rawStats = PropertyFamilyLink::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $pendingAdmin    = (int) ($rawStats[PropertyFamilyLink::STATUS_PENDING_ADMIN]    ?? 0);
        $pendingLandlord = (int) ($rawStats[PropertyFamilyLink::STATUS_PENDING_LANDLORD] ?? 0);
        $pendingLegacy   = (int) ($rawStats[PropertyFamilyLink::STATUS_PENDING]          ?? 0);

        $stats = [
            'pending_admin_review'          => $pendingAdmin,
            'pending_landlord_confirmation' => $pendingLandlord,
            'pending_legacy'                => $pendingLegacy,

            'approved'                      => (int) ($rawStats[PropertyFamilyLink::STATUS_APPROVED]  ?? 0),
            'rejected'                      => (int) ($rawStats[PropertyFamilyLink::STATUS_REJECTED]  ?? 0),
            'revoked'                       => (int) ($rawStats[PropertyFamilyLink::STATUS_REVOKED]   ?? 0),
            'cancelled'                     => (int) ($rawStats[PropertyFamilyLink::STATUS_CANCELLED] ?? 0),

            'pending'                       => $pendingAdmin + $pendingLandlord + $pendingLegacy,

            'total'                         => array_sum($rawStats),

            'approved_today'                => PropertyFamilyLink::where(
                'status',
                PropertyFamilyLink::STATUS_APPROVED
            )->whereDate('reviewed_at', today())->count(),
        ];

        return view('admin.family-links.index', compact('links', 'stats'));
    }

    /* ================================================================
       SHOW
       ================================================================ */

    /**
     * Show a single family link for review.
     *
     * We pass the per-link review context explicitly (rather than
     * recomputing it in the view) so the admin detail page can show
     * the same "what state is this in / what happens next" summary
     * the landlord pages use. Cheap: all values are on the model.
     */
    public function show(PropertyFamilyLink $link)
    {
        $this->authorizeAdmin();

        $link->load([
            'property.landlord',
            'landlord',
            'linkedUser',
            'reviewer',
            'landlordConfirmer',
        ]);

        $permissions = config('property_family_links.permissions');

        // Expose the review context the view needs without another query.
        $reviewContext = [
            'is_admin_actionable'   => $this->isAdminActionable($link),
            'state_message'         => $this->isAdminActionable($link)
                ? null
                : $this->unreviewableMessage($link),
            'has_rejection_reason'  => $link->status === PropertyFamilyLink::STATUS_REJECTED
                && !empty($link->admin_notes),
            'rejection_reason'      => $link->status === PropertyFamilyLink::STATUS_REJECTED
                ? $link->admin_notes
                : null,
        ];

        return view('admin.family-links.show', compact('link', 'permissions', 'reviewContext'));
    }

    /* ================================================================
       REVIEW — approve / reject
       ================================================================ */

    /**
     * Handle an admin's approve/reject decision.
     *
     * On approve, the request has already validated `channels[]` and
     * cross-checked them against the recipient's available contact
     * methods. We pass the validated channel list straight into the
     * service so the invitation goes out on exactly what the admin
     * picked.
     */
    public function review(ReviewFamilyLinkRequest $request, PropertyFamilyLink $link)
    {
        $this->authorizeAdmin();

        if (!$this->isAdminActionable($link)) {
            return $this->wrongState($request, $this->unreviewableMessage($link));
        }

        try {
            if ($request->decision === 'approve') {
                // The request layer guarantees a non-empty channel list on approve.
                $channels = $request->selectedChannels();

                $link = $this->service->approve(
                    $link,
                    auth()->user(),
                    array_merge($request->validated(), ['channels' => $channels])
                );

                $message = 'Family link approved. The family member has been invited to set up their account.';

                Log::channel('audit')->info('Family link approved by admin', [
                    'link_id'      => $link->id,
                    'property_id'  => $link->property_id,
                    'landlord_id'  => $link->landlord_id,
                    'admin_id'     => auth()->id(),
                    'channels'     => $channels,
                    'linked_user'  => $link->linked_user_id,
                    'approved_at'  => now()->toIso8601String(),
                ]);
            } else {
                $link = $this->service->reject(
                    $link,
                    auth()->user(),
                    $request->admin_notes ?? 'No reason provided'
                );
                $message = 'Family link rejected.';

                Log::channel('audit')->info('Family link rejected by admin', [
                    'link_id'     => $link->id,
                    'property_id' => $link->property_id,
                    'landlord_id' => $link->landlord_id,
                    'admin_id'    => auth()->id(),
                    'reason'      => $request->admin_notes ?? 'No reason provided',
                    'rejected_at' => now()->toIso8601String(),
                ]);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'link'    => $link,
                ]);
            }

            return redirect()
                ->route('admin.family-links.index')
                ->with('success', $message);

        } catch (\Throwable $e) {
            Log::error('Failed to review family link', [
                'link_id'  => $link->id,
                'admin_id' => auth()->id(),
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process review. Please try again.',
                ], 500);
            }

            return back()->with('error', 'Failed to process review.')->withInput();
        }
    }

    /* ================================================================
       REVOKE
       ================================================================ */

    public function revoke(Request $request, PropertyFamilyLink $link)
{
    // ── Authorization: admin or super-admin only ──
    $actor = auth()->user();

    if (!$actor->isSuperAdmin() && !$actor->isAdmin()) {
        return $this->unauthorized($request, 'Unauthorized.');
    }

    if (!$link->isApproved()) {
        return $this->unauthorized(
            $request,
            'Only approved links can be revoked.'
        );
    }

    try {
        // Admin-specified reason, with a sensible default.
        $reason = $request->input('reason', 'Revoked by administrator');

        // Capture the target before revoke mutates it.
        $linkedUserId = $link->linked_user_id;
        $linkedUser   = $linkedUserId ? \App\Models\User::find($linkedUserId) : null;

        // Delegate the state transition + archival gate to the service.
        // The service fires FamilyLinkOrphanArchivalService automatically.
        $this->service->revoke($link, $actor, $reason);

        // ✅ Detect whether orphan-archival fired.
        $wasArchived = false;
        if ($linkedUser) {
            $linkedUser->refresh();
            $wasArchived = $linkedUser->trashed()
                || $linkedUser->status === \App\Models\User::STATUS_ARCHIVED;
        }

        $message = 'Family link revoked.';
        if ($wasArchived) {
            $message .= ' The family member had no remaining property links and was archived automatically.';
        }

        Log::channel('audit')->info('Family link revoked by administrator', [
            'link_id'         => $link->id,
            'property_id'     => $link->property_id,
            'landlord_id'     => $link->landlord_id,   // owner of the property
            'admin_id'        => $actor->id,           // who performed the revoke
            'admin_name'      => $actor->name,
            'linked_user_id'  => $linkedUserId,
            'reason'          => $reason,
            'auto_archived'   => $wasArchived,
            'revoked_at'      => now()->toIso8601String(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success'       => true,
                'message'       => $message,
                'auto_archived' => $wasArchived,
            ]);
        }

        return back()->with('success', $message);

    } catch (\Throwable $e) {
        Log::error('Failed to revoke family link (admin)', [
            'link_id'  => $link->id,
            'admin_id' => auth()->id(),
            'error'    => $e->getMessage(),
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
       BULK REVIEW
       ================================================================ */

    /**
     * Bulk approve or reject several proposals.
     *
     * The `channels` list applies to every approval in the batch. Each
     * individual send is still filtered against that recipient's
     * available contact info inside the service, so an SMS-only
     * recipient in a batch submitted with ['email','sms'] will only
     * receive SMS.
     */
    public function bulkReview(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'link_ids'    => 'required|array|min:1|max:100',
            'link_ids.*'  => 'integer|exists:property_family_links,id',

            'decision'    => 'required|in:approve,reject',
            'admin_notes' => 'nullable|string|max:1000',

            // Channels — required on approve, ignored on reject.
            'channels'    => 'required_if:decision,approve|array|min:1',
            'channels.*'  => 'string|in:email,sms,whatsapp',
        ]);

        // -------------------------------------------------------------
        // Normalize once, outside the loop. Saves recomputation and
        // makes the intent clearer: we are guaranteed a clean set of
        // integer ids and a clean channel list before processing.
        // -------------------------------------------------------------
        $ids      = array_values(array_unique(array_map('intval', $validated['link_ids'])));
        $decision = $validated['decision'];
        $notes    = $validated['admin_notes'] ?? '';
        $channels = $this->normalizeBulkChannels($validated['channels'] ?? []);

        $ok      = 0;
        $failed  = 0;
        $skipped = 0;
        $errors  = [];

        // Eager-load everything the loop body touches so we don't fire
        // a fresh query per row. `find()` inside the loop still hits the
        // DB for the individual record — but the relationships come in
        // with it, which is the expensive part.
        $links = PropertyFamilyLink::with(['property', 'landlord'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($ids as $id) {
            try {
                $link = $links->get($id);

                if (!$link) {
                    $failed++;
                    $errors[] = "Link #{$id}: not found";
                    continue;
                }

                if (!$this->isAdminActionable($link)) {
                    $skipped++;
                    $errors[] = "Link #{$id}: not in admin-review state ({$link->status_label})";
                    continue;
                }

                if ($decision === 'approve') {
                    $this->service->approve(
                        $link,
                        auth()->user(),
                        array_merge($validated, ['channels' => $channels])
                    );

                    Log::channel('audit')->info('Family link approved by admin (bulk)', [
                        'link_id'     => $link->id,
                        'property_id' => $link->property_id,
                        'landlord_id' => $link->landlord_id,
                        'admin_id'    => auth()->id(),
                        'channels'    => $channels,
                        'approved_at' => now()->toIso8601String(),
                    ]);
                } else {
                    $this->service->reject($link, auth()->user(), $notes);

                    Log::channel('audit')->info('Family link rejected by admin (bulk)', [
                        'link_id'     => $link->id,
                        'property_id' => $link->property_id,
                        'landlord_id' => $link->landlord_id,
                        'admin_id'    => auth()->id(),
                        'reason'      => $notes,
                        'rejected_at' => now()->toIso8601String(),
                    ]);
                }
                $ok++;

            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Link #{$id}: " . $e->getMessage();
                Log::warning('Bulk family link review failed for one item', [
                    'link_id'  => $id,
                    'admin_id' => auth()->id(),
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        $message = "Bulk review: {$ok} processed, {$failed} failed";
        if ($skipped > 0) {
            $message .= ", {$skipped} skipped";
        }
        $message .= '.';

        return response()->json([
            'success' => $ok > 0,
            'message' => $message,
            'stats'   => [
                'processed' => $ok,
                'failed'    => $failed,
                'skipped'   => $skipped,
            ],
            'errors'  => $errors,
        ]);
    }

    /* ================================================================
       HELPERS
       ================================================================ */

    protected function authorizeAdmin(): void
    {
        $u = auth()->user();

        if (!$u || (!$u->isAdmin() && !$u->isSuperAdmin())) {
            abort(403, 'Unauthorized.');
        }
    }

    /**
     * Can an admin act on this link?
     *
     * Only links in `pending_admin_review` (or the legacy `pending`
     * status) are actionable. Everything else — including links still
     * awaiting landlord confirmation — must be blocked with a clear
     * message rather than a silent no-op.
     */
    protected function isAdminActionable(PropertyFamilyLink $link): bool
    {
        return $link->isAwaitingAdminReview()
            || $link->status === PropertyFamilyLink::STATUS_PENDING;
    }

    protected function unreviewableMessage(PropertyFamilyLink $link): string
    {
        return match ($link->status) {
            PropertyFamilyLink::STATUS_PENDING_LANDLORD =>
                "This proposal is still awaiting the landlord's confirmation. You cannot review it yet.",

            PropertyFamilyLink::STATUS_APPROVED =>
                'This proposal has already been approved.',

            PropertyFamilyLink::STATUS_REJECTED =>
                'This proposal has already been rejected.',

            PropertyFamilyLink::STATUS_REVOKED =>
                'This link has been revoked and cannot be re-reviewed.',

            PropertyFamilyLink::STATUS_CANCELLED =>
                'This proposal was cancelled by the landlord.',

            default =>
                'This proposal is not in a reviewable state.',
        };
    }

    protected function wrongState(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        return back()->with('error', $message);
    }

    /**
     * Normalize the bulk channel list to the supported set.
     *
     * @return array<int,string>
     */
    protected function normalizeBulkChannels(array $channels): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                fn ($c) => is_string($c) ? strtolower(trim($c)) : null,
                $channels
            ),
            fn ($c) => $c !== null && in_array($c, PropertyFamilyLinkService::SUPPORTED_INVITATION_CHANNELS, true)
        )));
    }
}