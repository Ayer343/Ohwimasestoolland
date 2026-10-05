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
     * Admin queue — family link proposals awaiting admin review.
     *
     * Two-stage workflow:
     *   - pending_landlord_confirmation  → landlord hasn't confirmed yet
     *                                      (NOT shown by default)
     *   - pending_admin_review           → landlord confirmed, ready for admin
     *                                      (shown by default)
     *
     * The default filter shows only admin-actionable proposals. Admins
     * can additionally filter by `pending_landlord_confirmation` to see
     * stalled proposals waiting on the landlord.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

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
                // ✅ THE FIX — include the admin-actionable states
                PropertyFamilyLink::STATUS_PENDING_ADMIN,
                PropertyFamilyLink::STATUS_PENDING,       // legacy rows
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
            ->paginate(20);

        // -------------------------------------------------------------
        // Grouped stats — one query covers every status bucket
        // -------------------------------------------------------------
        $rawStats = PropertyFamilyLink::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $stats = [
            'pending_admin_review'          => (int) ($rawStats[PropertyFamilyLink::STATUS_PENDING_ADMIN]    ?? 0),
            'pending_landlord_confirmation' => (int) ($rawStats[PropertyFamilyLink::STATUS_PENDING_LANDLORD] ?? 0),
            'pending_legacy'                => (int) ($rawStats[PropertyFamilyLink::STATUS_PENDING]          ?? 0),

            'approved'                      => (int) ($rawStats[PropertyFamilyLink::STATUS_APPROVED]         ?? 0),
            'rejected'                      => (int) ($rawStats[PropertyFamilyLink::STATUS_REJECTED]         ?? 0),
            'revoked'                       => (int) ($rawStats[PropertyFamilyLink::STATUS_REVOKED]          ?? 0),
            'cancelled'                     => (int) ($rawStats[PropertyFamilyLink::STATUS_CANCELLED]        ?? 0),

            // "pending" = admin-actionable + legacy (excludes landlord-only)
            'pending'                       =>
                (int) ($rawStats[PropertyFamilyLink::STATUS_PENDING_ADMIN] ?? 0)
                + (int) ($rawStats[PropertyFamilyLink::STATUS_PENDING]     ?? 0),

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

        return view('admin.family-links.show', compact('link', 'permissions'));
    }

    /* ================================================================
       REVIEW — approve / reject
       ================================================================ */

    public function review(ReviewFamilyLinkRequest $request, PropertyFamilyLink $link)
    {
        $this->authorizeAdmin();

        // Guard: only admin-actionable states can be reviewed.
        if (!$link->isAwaitingAdminReview()
            && $link->status !== PropertyFamilyLink::STATUS_PENDING) {
            $message = match ($link->status) {
                PropertyFamilyLink::STATUS_PENDING_LANDLORD =>
                    'This proposal is still awaiting the landlord\'s confirmation. You cannot review it yet.',

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

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return back()->with('error', $message);
        }

        try {
            if ($request->decision === 'approve') {
                $link    = $this->service->approve($link, auth()->user(), $request->validated());
                $message = 'Family link approved. The family member has been invited to set up their account.';
            } else {
                $link = $this->service->reject(
                    $link,
                    auth()->user(),
                    $request->admin_notes ?? 'No reason provided'
                );
                $message = 'Family link rejected.';
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
                'link_id' => $link->id,
                'error'   => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed: ' . $e->getMessage(),
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
        $this->authorizeAdmin();

        if (!$link->isApproved()) {
            $message = 'Only approved links can be revoked.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return back()->with('error', $message);
        }

        try {
            $this->service->revoke(
                $link,
                auth()->user(),
                $request->input('reason', 'Revoked by admin')
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Link revoked.',
                ]);
            }

            return back()->with('success', 'Link revoked.');

        } catch (\Throwable $e) {
            Log::error('Failed to revoke family link', [
                'link_id' => $link->id,
                'error'   => $e->getMessage(),
            ]);

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $e->getMessage()], 500)
                : back()->with('error', $e->getMessage());
        }
    }

    /* ================================================================
       BULK REVIEW
       ================================================================ */

    public function bulkReview(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'link_ids'    => 'required|array',
            'link_ids.*'  => 'exists:property_family_links,id',
            'decision'    => 'required|in:approve,reject',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $ok      = 0;
        $failed  = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($validated['link_ids'] as $id) {
            try {
                $link = PropertyFamilyLink::find($id);

                if (!$link) {
                    $failed++;
                    $errors[] = "Link #{$id}: not found";
                    continue;
                }

                // State guard — only admin-actionable links pass through
                if (!$link->isAwaitingAdminReview()
                    && $link->status !== PropertyFamilyLink::STATUS_PENDING) {
                    $skipped++;
                    $errors[] = "Link #{$id}: not in admin-review state ({$link->status_label})";
                    continue;
                }

                if ($validated['decision'] === 'approve') {
                    $this->service->approve($link, auth()->user(), $validated);
                } else {
                    $this->service->reject($link, auth()->user(), $validated['admin_notes'] ?? '');
                }
                $ok++;

            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Link #{$id}: " . $e->getMessage();
                Log::warning('Bulk family link review failed for one item', [
                    'link_id' => $id,
                    'error'   => $e->getMessage(),
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
}