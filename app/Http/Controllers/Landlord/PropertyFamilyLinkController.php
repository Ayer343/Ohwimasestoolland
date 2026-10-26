<?php
// app/Http/Controllers/Landlord/PropertyFamilyLinkController.php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StoreFamilyLinkRequest;
use App\Models\Property;
use App\Models\PropertyFamilyLink;
use App\Models\User;
use App\Services\PropertyFamilyLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PropertyFamilyLinkController extends Controller
{
    public function __construct(
        protected PropertyFamilyLinkService $service
    ) {}

    /* ================================================================
       INDEX
       ================================================================ */

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

        $rawStats = PropertyFamilyLink::where('landlord_id', $landlord->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $awaitingLandlord = (int) ($rawStats[PropertyFamilyLink::STATUS_PENDING_LANDLORD] ?? 0);
        $awaitingAdmin    = (int) ($rawStats[PropertyFamilyLink::STATUS_PENDING_ADMIN]    ?? 0);
        $legacyPending    = (int) ($rawStats[PropertyFamilyLink::STATUS_PENDING]          ?? 0);

        $stats = [
            'total'             => array_sum($rawStats),
            'awaiting_landlord' => $awaitingLandlord,
            'awaiting_admin'    => $awaitingAdmin,
            'pending'           => $awaitingLandlord + $awaitingAdmin + $legacyPending,
            'approved'          => (int) ($rawStats[PropertyFamilyLink::STATUS_APPROVED]  ?? 0),
            'rejected'          => (int) ($rawStats[PropertyFamilyLink::STATUS_REJECTED]  ?? 0),
            'revoked'           => (int) ($rawStats[PropertyFamilyLink::STATUS_REVOKED]   ?? 0),
            'cancelled'         => (int) ($rawStats[PropertyFamilyLink::STATUS_CANCELLED] ?? 0),
        ];

        $properties = $this->modalPropertiesFor($landlord);
        $existingFamilyMembers = $this->existingFamilyMembersFor($landlord);

        return view('landlord.family-links.index', compact(
            'links', 'stats', 'properties', 'existingFamilyMembers'
        ));
    }

    /* ================================================================
       FOR PROPERTY
       ================================================================ */

    public function forProperty(Property $property)
    {
        $this->authorizePropertyOwnership($property);

        $landlord = auth()->user();

        $links = $property->familyLinks()
            ->with(['linkedUser', 'reviewer', 'landlordConfirmer'])
            ->latest()
            ->paginate(15);

        $rawStats = PropertyFamilyLink::where('property_id', $property->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $awaitingLandlord = (int) ($rawStats[PropertyFamilyLink::STATUS_PENDING_LANDLORD] ?? 0);
        $awaitingAdmin    = (int) ($rawStats[PropertyFamilyLink::STATUS_PENDING_ADMIN]    ?? 0);
        $legacyPending    = (int) ($rawStats[PropertyFamilyLink::STATUS_PENDING]          ?? 0);

        $stats = [
            'total'             => array_sum($rawStats),
            'awaiting_landlord' => $awaitingLandlord,
            'awaiting_admin'    => $awaitingAdmin,
            'pending'           => $awaitingLandlord + $awaitingAdmin + $legacyPending,
            'approved'          => (int) ($rawStats[PropertyFamilyLink::STATUS_APPROVED]  ?? 0),
            'rejected'          => (int) ($rawStats[PropertyFamilyLink::STATUS_REJECTED]  ?? 0),
            'revoked'           => (int) ($rawStats[PropertyFamilyLink::STATUS_REVOKED]   ?? 0),
            'cancelled'         => (int) ($rawStats[PropertyFamilyLink::STATUS_CANCELLED] ?? 0),
        ];

        $properties = $this->modalPropertiesFor($landlord);
        $existingFamilyMembers = $this->existingFamilyMembersFor($landlord);

        $permissions   = config('property_family_links.permissions', []);
        $relationships = config('property_family_links.relationship_options', []);

        return view('landlord.family-links.for-property', compact(
            'property', 'links', 'permissions', 'relationships',
            'properties', 'stats', 'existingFamilyMembers'
        ));
    }

    /* ================================================================
       STORE
       ================================================================ */

    public function store(StoreFamilyLinkRequest $request)
    {
        $landlord = auth()->user();
        $mode     = $request->input('link_mode', 'new');

        $propertyIds = (array) $request->input('property_ids', []);
        if (empty($propertyIds) && $request->filled('property_id')) {
            $propertyIds = [$request->input('property_id')];
        }
        $propertyIds = array_values(array_unique(array_map('intval', $propertyIds)));

        if (empty($propertyIds)) {
            return $this->unprocessable($request, 'Select at least one property.');
        }

        $properties = Property::whereIn('id', $propertyIds)
            ->where('landlord_id', $landlord->id)
            ->get();

        if ($properties->count() !== count($propertyIds)) {
            return $this->unprocessable(
                $request,
                'One or more selected properties do not belong to you.'
            );
        }

        if ($mode === 'existing') {
            return $this->storeForExistingMember($request, $landlord, $properties);
        }

        return $this->storeNewMemberAcrossProperties($request, $landlord, $properties);
    }

    /**
     * New-member path.
     *
     * ✅ FIX: The $created array now carries `property_id`, so the
     *         redirect at the bottom can target the specific property
     *         when exactly one link is created. Before this fix,
     *         `$created[0]['property_id']` was undefined and the
     *         redirect route parameter was always null.
     */
    protected function storeNewMemberAcrossProperties(
        StoreFamilyLinkRequest $request,
        $landlord,
        $properties
    ) {
        $resubmitId = $request->input('resubmit_from');
        $resubmit   = null;

        if ($resubmitId) {
            if ($properties->count() > 1) {
                return $this->unprocessable(
                    $request,
                    'A resubmission must target exactly one property.'
                );
            }

            $targetProperty = $properties->first();

            $resubmit = PropertyFamilyLink::query()
                ->where('id', $resubmitId)
                ->where('landlord_id', $landlord->id)
                ->where('status', PropertyFamilyLink::STATUS_REJECTED)
                ->where('property_id', $targetProperty->id)
                ->first();

            if (!$resubmit) {
                return $this->unprocessable(
                    $request,
                    'The proposal you are trying to resubmit is no longer eligible. Refresh the page and try again.'
                );
            }
        }

        $payload = $request->validated();
        $payload['link_mode'] = 'new';

        $created  = [];
        $skipped  = [];
        $failed   = [];

        $statuses = [
            PropertyFamilyLink::STATUS_PENDING_LANDLORD,
            PropertyFamilyLink::STATUS_PENDING_ADMIN,
            PropertyFamilyLink::STATUS_APPROVED,
        ];

        foreach ($properties as $property) {
            $duplicate = PropertyFamilyLink::query()
                ->where('landlord_id', $landlord->id)
                ->where('property_id', $property->id)
                ->where(function ($q) use ($request) {
                    $email = trim((string) $request->input('proposed_email'));
                    $phone = trim((string) $request->input('proposed_phone'));

                    $q->when($email !== '', fn ($qq) => $qq->orWhere('proposed_email', $email))
                      ->when($phone !== '', fn ($qq) => $qq->orWhere('proposed_phone', $phone));
                })
                ->whereIn('status', $statuses)
                ->exists();

            if ($duplicate) {
                $skipped[] = $property->property_name;
                continue;
            }

            try {
                $link = $this->service->propose($property, $payload, $landlord, $resubmit);

                // ✅ FIX: include property_id for the redirect at the end.
                $created[] = [
                    'property_id' => $property->id,
                    'property'    => $property->property_name,
                    'link_id'     => $link->id,
                    'status'      => $link->status,
                ];

                Log::channel('audit')->info(
                    $resubmit
                        ? 'Family link proposal resubmitted (bulk)'
                        : 'Family link proposal submitted (bulk)',
                    [
                        'link_id'      => $link->id,
                        'property_id'  => $property->id,
                        'landlord_id'  => $landlord->id,
                        'status'       => $link->status,
                        'was_resubmit' => $resubmit !== null,
                        'submitted_at' => now()->toIso8601String(),
                    ]
                );
            } catch (\Throwable $e) {
                $failed[] = $property->property_name;

                Log::error('Bulk family link proposal failed for one property', [
                    'property_id' => $property->id,
                    'landlord_id' => $landlord->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $okCount = count($created);

        $message = $this->bulkResultMessage(
            $okCount,
            $skipped,
            $failed,
            $resubmit !== null
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => $okCount > 0,
                'message' => $message,
                'created' => $created,
                'skipped' => $skipped,
                'failed'  => $failed,
            ], $okCount > 0 ? ($resubmit ? 200 : 201) : 422);
        }

        // ✅ FIX: only redirect to the per-property page when we have
        //         exactly one created row AND its property_id is set.
        $redirect = ($okCount === 1 && !empty($created[0]['property_id']))
            ? route('landlord.properties.family-links', $created[0]['property_id'])
            : route('landlord.family-links.index');

        return redirect()->to($redirect)->with(
            $okCount > 0 ? 'success' : 'error',
            $message
        );
    }

    protected function storeForExistingMember(
        Request $request,
        $landlord,
        $properties
    ) {
        $existingUserId = (int) $request->input('existing_user_id');

        if (!$existingUserId) {
            return $this->unprocessable($request, 'Choose a family member to link.');
        }

        $hasPriorLink = PropertyFamilyLink::query()
            ->where('landlord_id', $landlord->id)
            ->where('linked_user_id', $existingUserId)
            ->where('status', PropertyFamilyLink::STATUS_APPROVED)
            ->exists();

        if (!$hasPriorLink) {
            return $this->unauthorized(
                $request,
                'You can only add properties for family members you have already linked.'
            );
        }

        $user = User::find($existingUserId);
        if (!$user) {
            return $this->unprocessable($request, 'The selected family member no longer exists.');
        }

        $allowed   = array_keys(config('property_family_links.permissions', []));
        $submitted = (array) $request->input('permissions', []);
        $normalised = array_values(array_intersect(
            $allowed,
            array_unique(array_map('strval', $submitted))
        ));

        $landlordNotes = $request->input('landlord_notes');

        $created = [];
        $skipped = [];
        $failed  = [];

        DB::transaction(function () use (
            $properties, $landlord, $user, $normalised, $landlordNotes,
            &$created, &$skipped, &$failed
        ) {
            foreach ($properties as $property) {
                $existing = PropertyFamilyLink::query()
                    ->where('landlord_id', $landlord->id)
                    ->where('property_id', $property->id)
                    ->where(function ($q) use ($user) {
                        $q->where('linked_user_id', $user->id)
                          ->orWhere('proposed_email', $user->email);
                    })
                    ->first();

                if ($existing) {
                    $skipped[] = $property->property_name;
                    continue;
                }

                try {
                    $link = $this->service->linkExistingUser(
                        $property,
                        $user,
                        [
                            'permissions'    => $normalised,
                            'landlord_notes' => $landlordNotes,
                            'relationship'   => $this->resolveRelationshipForExisting($landlord, $user),
                        ],
                        $landlord
                    );

                    $created[] = [
                        'property_id' => $property->id,
                        'property'    => $property->property_name,
                        'link_id'     => $link->id,
                        'status'      => $link->status,
                    ];

                    Log::channel('audit')->info('Family link added for existing member', [
                        'link_id'        => $link->id,
                        'property_id'    => $property->id,
                        'landlord_id'    => $landlord->id,
                        'linked_user_id' => $user->id,
                        'status'         => $link->status,
                        'permissions'    => $normalised,
                        'added_at'       => now()->toIso8601String(),
                    ]);
                } catch (\Throwable $e) {
                    $failed[] = $property->property_name;

                    Log::error('Failed to add existing family member to property', [
                        'property_id'    => $property->id,
                        'landlord_id'    => $landlord->id,
                        'linked_user_id' => $user->id,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }
        });

        $okCount = count($created);

        $name = $user->name ?: 'this family member';

        if ($okCount > 0) {
            $message = $okCount === 1
                ? "{$name} now has access to {$created[0]['property']}."
                : "{$name} now has access to {$okCount} additional properties.";
        } else {
            $message = "No new properties were added for {$name}.";
        }

        if (!empty($skipped)) {
            $message .= ' Skipped (already linked): ' . implode(', ', $skipped) . '.';
        }
        if (!empty($failed)) {
            $message .= ' Failed: ' . implode(', ', $failed) . '.';
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => $okCount > 0,
                'message' => $message,
                'created' => $created,
                'skipped' => $skipped,
                'failed'  => $failed,
            ], $okCount > 0 ? 201 : 422);
        }

        return redirect()
            ->route('landlord.family-links.index')
            ->with($okCount > 0 ? 'success' : 'error', $message);
    }

    protected function resolveRelationshipForExisting($landlord, User $user): ?string
    {
        $prior = PropertyFamilyLink::query()
            ->where('landlord_id', $landlord->id)
            ->where('linked_user_id', $user->id)
            ->where('status', PropertyFamilyLink::STATUS_APPROVED)
            ->latest()
            ->first();

        return $prior->relationship ?? null;
    }

    protected function bulkResultMessage(
        int $okCount,
        array $skipped,
        array $failed,
        bool $isResubmit
    ): string {
        if ($okCount === 0) {
            $msg = $isResubmit
                ? 'Resubmission failed.'
                : 'No proposals were submitted.';
        } elseif ($okCount === 1 && !$isResubmit) {
            $msg = 'Proposal submitted. Please confirm it to send it to administrators for review.';
        } elseif ($okCount === 1 && $isResubmit) {
            $msg = 'Proposal updated. Please confirm it to send it to administrators for review.';
        } else {
            $msg = "{$okCount} proposals submitted. Confirm each one to send them to administrators.";
        }

        if (!empty($skipped)) {
            $msg .= ' Skipped (already linked): ' . implode(', ', $skipped) . '.';
        }
        if (!empty($failed)) {
            $msg .= ' Failed: ' . implode(', ', $failed) . '.';
        }

        return $msg;
    }

    /* ================================================================
       CONFIRM
       ================================================================ */

    public function confirm(Request $request, PropertyFamilyLink $link)
    {
        if ((int) $link->landlord_id !== (int) auth()->id()) {
            return $this->unauthorized($request, 'Unauthorized.');
        }

        if (!$link->isAwaitingLandlordConfirmation()) {
            return $this->unauthorized(
                $request,
                'This proposal is not awaiting your confirmation.'
            );
        }

        try {
            $link = $this->service->confirmByLandlord($link, auth()->user());

            $message = 'Proposal confirmed. It has been sent to administrators for review.';

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
       UPDATE PERMISSIONS
       ================================================================ */

    public function updatePermissions(Request $request, PropertyFamilyLink $link)
    {
        $landlord = auth()->user();

        if ((int) $link->landlord_id !== (int) $landlord->id) {
            return $this->unauthorized($request, 'Unauthorized.');
        }

        if (!$link->isApproved()) {
            return $this->unprocessable(
                $request,
                'Only approved links can have their permissions edited. Cancel or resubmit pending proposals instead.'
            );
        }

        $allowed = array_keys(config('property_family_links.permissions', []));

        $validated = $request->validate([
            'permissions'   => 'nullable|array',
            'permissions.*' => ['string', Rule::in($allowed)],
        ], [
            'permissions.*.in' => 'One or more selected permissions are not recognised.',
        ]);

        $submitted  = $validated['permissions'] ?? [];
        $normalised = array_values(array_intersect($allowed, array_unique($submitted)));

        $before = is_array($link->permissions) ? $link->permissions : [];
        sort($before);
        $after = $normalised;
        sort($after);

        if ($before === $after) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No changes to save.',
                    'link'    => $link,
                ]);
            }

            return back()->with('info', 'No changes to save.');
        }

        try {
            $link->update(['permissions' => $normalised]);

            Log::channel('audit')->info('Family link permissions updated by landlord', [
                'link_id'            => $link->id,
                'property_id'        => $link->property_id,
                'landlord_id'        => $landlord->id,
                'linked_user'        => $link->linked_user_id,
                'permissions_before' => $before,
                'permissions_after'  => $after,
                'updated_at'         => now()->toIso8601String(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Permissions updated.',
                    'link'    => $link->fresh(),
                ]);
            }

            return back()->with('success', 'Permissions updated.');

        } catch (\Throwable $e) {
            Log::error('Failed to update family link permissions', [
                'link_id'     => $link->id,
                'landlord_id' => $landlord->id,
                'error'       => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update permissions. Please try again.',
                ], 500);
            }

            return back()->with('error', 'Failed to update permissions. Please try again.');
        }
    }

    /* ================================================================
       REVOKE
       ================================================================ */

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

            $linkedUserId = $link->linked_user_id;
            $linkedUser   = $linkedUserId ? User::find($linkedUserId) : null;

            $this->service->revoke($link, auth()->user(), $reason);

            $wasArchived = false;
            if ($linkedUser) {
                $linkedUser->refresh();
                $wasArchived = $linkedUser->trashed()
                    || $linkedUser->status === User::STATUS_ARCHIVED;
            }

            $message = 'Family link revoked.';
            if ($wasArchived) {
                $message .= ' The family member had no remaining property links and was archived automatically.';
            }

            Log::channel('audit')->info('Family link revoked by landlord', [
                'link_id'         => $link->id,
                'property_id'     => $link->property_id,
                'landlord_id'     => $link->landlord_id,
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
       CANCEL
       ================================================================ */

    public function cancel(Request $request, PropertyFamilyLink $link)
    {
        if ((int) $link->landlord_id !== (int) auth()->id() || !$link->isPending()) {
            return $this->unauthorized(
                $request,
                'Unauthorized or link already reviewed.'
            );
        }

        try {
            Log::channel('audit')->info('Family link proposal cancelled', [
                'link_id'         => $link->id,
                'property_id'     => $link->property_id,
                'landlord_id'     => $link->landlord_id,
                'proposed_name'   => $link->proposed_name,
                'proposed_phone'  => $link->proposed_phone,
                'proposed_email'  => $link->proposed_email,
                'relationship'    => $link->relationship,
                'permissions'     => $link->permissions,
                'previous_status' => $link->status,
                'cancelled_by'    => auth()->id(),
                'cancelled_at'    => now()->toIso8601String(),
            ]);

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

    protected function modalPropertiesFor($landlord)
    {
        return Property::query()
            ->where('landlord_id', $landlord->id)
            ->orderBy('property_name')
            ->get(['id', 'property_name', 'digital_address']);
    }

    protected function existingFamilyMembersFor($landlord)
    {
        return PropertyFamilyLink::query()
            ->where('landlord_id', $landlord->id)
            ->where('status', PropertyFamilyLink::STATUS_APPROVED)
            ->whereNotNull('linked_user_id')
            ->with([
                'linkedUser:id,name,email',
                'property:id,property_name,digital_address',
            ])
            ->get()
            ->groupBy('linked_user_id')
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'user_id'      => $first->linked_user_id,
                    'name'         => $first->linkedUser->name
                                     ?? $first->display_name
                                     ?? $first->proposed_name
                                     ?? 'Family member',
                    'email'        => $first->linkedUser->email ?? $first->proposed_email,
                    'phone'        => $first->proposed_phone,
                    'relationship' => $first->relationship,
                    'properties'   => $group->map(fn ($l) => [
                        'id'   => $l->property_id,
                        'name' => $l->property->property_name ?? ('Property #' . $l->property_id),
                    ])->values()->all(),
                ];
            })
            ->values();
    }

    protected function authorizePropertyOwnership(Property $property): void
    {
        if ((int) $property->landlord_id !== (int) auth()->id()) {
            abort(403, 'You do not own this property.');
        }
    }

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

    protected function unprocessable(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        return back()->with('error', $message)->withInput();
    }
}