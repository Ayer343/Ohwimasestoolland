<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\StoreSecurityPersonnelRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\MultiChannelInvitationService;
use App\Services\UserInvitationService;
use App\Events\UserCreated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SecurityPersonnelAssignmentController extends Controller
{
    protected UserRepository $userRepository;
    protected UserInvitationService $invitationService;
    protected MultiChannelInvitationService $multiChannelService;

    public const DEFAULT_PER_PAGE  = 15;
    public const PER_PAGE_OPTIONS  = [10, 15, 20, 25, 50, 100];

    /** Minimum days a record must sit in trash before permanent deletion. */
    public const MIN_DAYS_BEFORE_PERMANENT_DELETE = 30;

    public function __construct(
        UserRepository $userRepository,
        UserInvitationService $invitationService,
        MultiChannelInvitationService $multiChannelService
    ) {
        $this->userRepository      = $userRepository;
        $this->invitationService   = $invitationService;
        $this->multiChannelService = $multiChannelService;
    }

    // ================================================================
    // LIST
    // ================================================================

    public function index(Request $request)
    {
        $currentUser = Auth::user();

        if (! $this->isActiveHead($currentUser)) {
            return redirect()->route('security.dashboard')
                ->with('error', 'Only the current Head of Security can manage personnel.');
        }

        $tab = $request->get('tab', 'active');

        $personnel = $this->getScopedSecurityPersonnel($currentUser, $request, $tab);

        $stats = $this->buildStats($currentUser);

        $perPage        = $this->resolvePerPage($request->get('per_page'));
        $perPageOptions = self::PER_PAGE_OPTIONS;

        return view('security.personnel.index', compact(
            'personnel', 'tab', 'stats', 'perPage', 'perPageOptions'
        ));
    }

    // ================================================================
    // TRASH (list soft-deleted head submissions)
    // ================================================================

    /**
     * List soft-deleted security personnel submitted by the current head.
     *
     * Scope: created_by = head.id AND deleted_at IS NOT NULL.
     *
     * Admin-created records are intentionally EXCLUDED — they belong to
     * the admin, not the head, even after soft-delete.
     */
    public function trash(Request $request)
    {
        $currentUser = Auth::user();

        if (! $this->isActiveHead($currentUser)) {
            return redirect()->route('security.dashboard')
                ->with('error', 'Only the current Head of Security can view the trash.');
        }

        $perPage = $this->resolvePerPage($request->get('per_page'));

        $query = $this->isScopedTrashed($currentUser);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Deleted-by filter
        if ($request->filled('deleted_by')) {
            $query->where('deleted_by', $request->deleted_by);
        }

        // Time filter
        if ($request->filled('filter')) {
            switch ($request->filter) {
                case 'today':
                    $query->whereDate('deleted_at', today());
                    break;
                case 'week':
                    $query->where('deleted_at', '>=', now()->startOfWeek());
                    break;
                case 'month':
                    $query->where('deleted_at', '>=', now()->startOfMonth());
                    break;
                case 'old':
                    $query->where('deleted_at', '<', now()->subDays(self::MIN_DAYS_BEFORE_PERMANENT_DELETE));
                    break;
            }
        }

        $personnel = $query
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $stats = [
            'total_trashed'      => $this->isScopedTrashed($currentUser)->count(),
            'trashed_this_week'  => $this->isScopedTrashed($currentUser)
                ->where('deleted_at', '>=', now()->startOfWeek())->count(),
            'trashed_this_month' => $this->isScopedTrashed($currentUser)
                ->where('deleted_at', '>=', now()->startOfMonth())->count(),
            'old_deleted_count'  => $this->isScopedTrashed($currentUser)
                ->where('deleted_at', '<', now()->subDays(self::MIN_DAYS_BEFORE_PERMANENT_DELETE))->count(),
        ];

        // Users who appear as `deleted_by` in the current head's trash
        $deletedByUsers = User::withTrashed()
            ->whereIn(
                'id',
                $this->isScopedTrashed($currentUser)
                    ->whereNotNull('deleted_by')
                    ->distinct()
                    ->pluck('deleted_by')
                    ->filter()
                    ->all()
            )
            ->get(['id', 'name']);

        $perPageOptions = self::PER_PAGE_OPTIONS;

        return view('security.personnel.trash', compact(
            'personnel', 'stats', 'deletedByUsers', 'perPage', 'perPageOptions'
        ));
    }

    // ================================================================
    // RESTORE (single)
    // ================================================================

    /**
     * Restore a soft-deleted submission.
     *
     * The record is brought back as `pending` and re-enters the approval
     * queue (awaiting_approval = true). The admin is re-notified, so a
     * rejected record can't be silently reactivated by the head.
     */
    public function restore(Request $request, $userId)
    {
        try {
            $currentUser = Auth::user();

            if (! $this->isActiveHead($currentUser)) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Only the current Head of Security can restore personnel.',
                ], 403);
            }

            $personnel = $this->isScopedTrashed($currentUser)->where('id', $userId)->first();

            if (! $personnel) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Personnel not found in your trash.',
                ], 404);
            }

            DB::beginTransaction();

            try {
                // Update metadata FIRST (before restore()) so handleUpdating
                // sees the re-opened approval flag.
                $metadata = $this->decodeMetadata($personnel->metadata);

                $metadata['awaiting_approval'] = true;
                $metadata['restored_at']       = now()->toISOString();
                $metadata['restored_by']       = $currentUser->id;
                $metadata['restored_by_name']  = $currentUser->name;

                // Clear rejection flags so the admin queue doesn't see
                // the old rejection reason on the restored record.
                unset($metadata['rejected_at']);
                unset($metadata['rejected_by']);
                unset($metadata['rejected_by_name']);
                unset($metadata['rejection_reason']);

                $personnel->metadata = $metadata;
                $personnel->saveQuietly();

                // Restore (clears deleted_at, deleted_by).
                $personnel->restore();

                $personnel->update([
                    'status'     => User::STATUS_PENDING,
                    'deleted_by' => null,
                ]);

                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            $personnel = $personnel->fresh();

            Log::info('Security personnel restored from trash', [
                'user_id'        => $personnel->id,
                'user_name'      => $personnel->name,
                'restored_by'    => $currentUser->id,
                'restored_by_name' => $currentUser->name,
                'new_status'     => $personnel->status,
                'awaiting_approval' => true,
            ]);

            return $this->cleanJsonResponse([
                'success'      => true,
                'message'      => "{$personnel->name} restored. The record is pending re-approval.",
                'redirect_url' => route('security.personnel.trash'),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to restore security personnel: ' . $e->getMessage(), [
                'user_id'     => $userId,
                'error_trace' => $e->getTraceAsString(),
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to restore personnel: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ================================================================
    // FORCE DELETE (single, permanent)
    // ================================================================

    /**
     * Permanently delete a soft-deleted submission.
     *
     * Enforces MIN_DAYS_BEFORE_PERMANENT_DELETE — a record must sit in
     * trash for at least 30 days before permanent deletion is allowed.
     * This gives the admin time to investigate before evidence is gone.
     */
    public function forceDelete(Request $request, $userId)
    {
        try {
            $currentUser = Auth::user();

            if (! $this->isActiveHead($currentUser)) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Only the current Head of Security can permanently delete personnel.',
                ], 403);
            }

            $personnel = $this->isScopedTrashed($currentUser)->where('id', $userId)->first();

            if (! $personnel) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Personnel not found in your trash.',
                ], 404);
            }

            // Age gate
            $deletedAt = $personnel->deleted_at;
            $daysInTrash = $deletedAt ? (int) $deletedAt->diffInDays(now()) : 0;

            if ($daysInTrash < self::MIN_DAYS_BEFORE_PERMANENT_DELETE) {
                return $this->cleanJsonResponse([
                    'success'  => false,
                    'message'  => "Records must be in trash for at least "
                        . self::MIN_DAYS_BEFORE_PERMANENT_DELETE
                        . " days before permanent deletion. This record has been in trash for {$daysInTrash} day(s).",
                    'days_in_trash' => $daysInTrash,
                    'required_days' => self::MIN_DAYS_BEFORE_PERMANENT_DELETE,
                ], 422);
            }

            $deletionReason = (string) $request->input('deletion_reason', 'Manual permanent deletion by head of security');

            $userInfo = [
                'id'    => $personnel->id,
                'name'  => $personnel->name,
                'email' => $personnel->email,
                'type'  => $personnel->type,
                'photo' => $personnel->photo,
            ];

            DB::beginTransaction();

            try {
                // Scrub PII before force-delete.
                $personnel->forceFill([
                    'name'              => 'Deleted User',
                    'email'             => "deleted_{$personnel->id}@deleted.example",
                    'phone'             => null,
                    'username'          => "deleted_{$personnel->id}",
                    'digital_address'   => null,
                    'location'          => null,
                    'photo'             => null,
                    'phone_verified_at' => null,
                    'email_verified_at' => null,
                    'last_login_at'     => null,
                    'last_activity_at'  => null,
                ])->saveQuietly();

                $personnel->roles()->detach();
                $personnel->forceDelete();

                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            Log::warning('Security personnel permanently deleted (PII scrubbed)', [
                'user_id_before'    => $userInfo['id'],
                'name_before'       => $userInfo['name'],
                'email_before'      => $userInfo['email'],
                'deleted_by'        => $currentUser->id,
                'deleted_by_name'   => $currentUser->name,
                'deletion_reason'   => $deletionReason,
                'days_in_trash'     => $daysInTrash,
            ]);

            return $this->cleanJsonResponse([
                'success' => true,
                'message' => "{$userInfo['name']} permanently deleted.",
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to permanently delete security personnel: ' . $e->getMessage(), [
                'user_id'     => $userId,
                'error_trace' => $e->getTraceAsString(),
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to permanently delete: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ================================================================
    // BULK RESTORE
    // ================================================================

    public function bulkRestore(Request $request)
    {
        $currentUser = Auth::user();

        if (! $this->isActiveHead($currentUser)) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Only the current Head of Security can restore personnel.',
            ], 403);
        }

        $validated = $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $results = ['success' => 0, 'failed' => 0, 'details' => []];

        DB::beginTransaction();

        try {
            foreach ($validated['user_ids'] as $userId) {
                try {
                    $personnel = $this->isScopedTrashed($currentUser)->where('id', $userId)->first();

                    if (! $personnel) {
                        $results['failed']++;
                        $results['details'][] = [
                            'id'      => $userId,
                            'success' => false,
                            'message' => 'Not found in your trash',
                        ];
                        continue;
                    }

                    $metadata = $this->decodeMetadata($personnel->metadata);
                    $metadata['awaiting_approval'] = true;
                    $metadata['restored_at']       = now()->toISOString();
                    $metadata['restored_by']       = $currentUser->id;
                    $metadata['restored_by_name']  = $currentUser->name;
                    unset($metadata['rejected_at']);
                    unset($metadata['rejected_by']);
                    unset($metadata['rejected_by_name']);
                    unset($metadata['rejection_reason']);

                    $personnel->metadata = $metadata;
                    $personnel->saveQuietly();
                    $personnel->restore();
                    $personnel->update([
                        'status'     => User::STATUS_PENDING,
                        'deleted_by' => null,
                    ]);

                    $results['success']++;
                    $results['details'][] = [
                        'id'      => $userId,
                        'success' => true,
                        'message' => 'Restored',
                    ];
                } catch (Throwable $e) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id'      => $userId,
                        'success' => false,
                        'message' => $e->getMessage(),
                    ];
                    Log::error("Bulk restore failed for user {$userId}: " . $e->getMessage());
                }
            }

            DB::commit();

            Log::info('Bulk restore completed', [
                'restored_by' => $currentUser->id,
                'success'     => $results['success'],
                'failed'      => $results['failed'],
            ]);

            return $this->cleanJsonResponse([
                'success'        => $results['success'] > 0,
                'restored_count' => $results['success'],
                'failed_count'   => $results['failed'],
                'message'        => "Bulk restore: {$results['success']} restored, {$results['failed']} failed.",
                'results'        => $results,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Bulk restore transaction failed: ' . $e->getMessage());

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Bulk restore failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ================================================================
    // BULK PERMANENT DELETE
    // ================================================================

    public function bulkPermanentDelete(Request $request)
    {
        $currentUser = Auth::user();

        if (! $this->isActiveHead($currentUser)) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Only the current Head of Security can permanently delete personnel.',
            ], 403);
        }

        $validated = $request->validate([
            'user_ids'        => 'required|array|min:1',
            'user_ids.*'      => 'integer|exists:users,id',
            'deletion_reason' => 'nullable|string|max:500',
        ]);

        $deletionReason = $validated['deletion_reason'] ?? 'Bulk permanent deletion by head of security';

        $results = ['success' => 0, 'failed' => 0, 'skipped' => 0, 'details' => []];

        DB::beginTransaction();

        try {
            foreach ($validated['user_ids'] as $userId) {
                try {
                    $personnel = $this->isScopedTrashed($currentUser)->where('id', $userId)->first();

                    if (! $personnel) {
                        $results['failed']++;
                        $results['details'][] = [
                            'id'      => $userId,
                            'success' => false,
                            'message' => 'Not found in your trash',
                        ];
                        continue;
                    }

                    $daysInTrash = $personnel->deleted_at
                        ? (int) $personnel->deleted_at->diffInDays(now())
                        : 0;

                    if ($daysInTrash < self::MIN_DAYS_BEFORE_PERMANENT_DELETE) {
                        $results['skipped']++;
                        $results['details'][] = [
                            'id'      => $userId,
                            'success' => false,
                            'message' => "Skipped: only {$daysInTrash} day(s) in trash (needs "
                                . self::MIN_DAYS_BEFORE_PERMANENT_DELETE . ")",
                        ];
                        continue;
                    }

                    $personnel->forceFill([
                        'name'              => 'Deleted User',
                        'email'             => "deleted_{$personnel->id}@deleted.example",
                        'phone'             => null,
                        'username'          => "deleted_{$personnel->id}",
                        'digital_address'   => null,
                        'location'          => null,
                        'photo'             => null,
                        'phone_verified_at' => null,
                        'email_verified_at' => null,
                        'last_login_at'     => null,
                        'last_activity_at'  => null,
                    ])->saveQuietly();

                    $personnel->roles()->detach();
                    $personnel->forceDelete();

                    $results['success']++;
                    $results['details'][] = [
                        'id'      => $userId,
                        'success' => true,
                        'message' => 'Permanently deleted',
                    ];
                } catch (Throwable $e) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id'      => $userId,
                        'success' => false,
                        'message' => $e->getMessage(),
                    ];
                    Log::error("Bulk permanent delete failed for user {$userId}: " . $e->getMessage());
                }
            }

            DB::commit();

            Log::warning('Bulk permanent delete completed', [
                'deleted_by'      => $currentUser->id,
                'deleted_by_name' => $currentUser->name,
                'deletion_reason' => $deletionReason,
                'success'         => $results['success'],
                'failed'          => $results['failed'],
                'skipped'         => $results['skipped'],
            ]);

            $message = "Bulk permanent delete: {$results['success']} deleted, {$results['failed']} failed";
            if ($results['skipped'] > 0) {
                $message .= ", {$results['skipped']} skipped (age gate)";
            }

            return $this->cleanJsonResponse([
                'success'       => $results['success'] > 0,
                'deleted_count' => $results['success'],
                'failed_count'  => $results['failed'],
                'skipped_count' => $results['skipped'],
                'message'       => $message,
                'results'       => $results,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Bulk permanent delete transaction failed: ' . $e->getMessage());

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Bulk permanent delete failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ================================================================
    // EMPTY TRASH
    // ================================================================

    /**
     * Empty the current head's trash entirely.
     *
     * SCOPED to `created_by = head.id` — does NOT touch other heads' or
     * admin-created records. Even with that scope, every record must
     * pass the same age gate as single-row force-delete; anything newer
     * than MIN_DAYS_BEFORE_PERMANENT_DELETE is skipped.
     *
     * The blade will only surface this button when there are old records
     * to delete, but the gate is enforced here regardless.
     */
    public function emptyTrash(Request $request)
    {
        $currentUser = Auth::user();

        if (! $this->isActiveHead($currentUser)) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Only the current Head of Security can empty trash.',
            ], 403);
        }

        $validated = $request->validate([
            'confirmation' => 'required|string|in:empty_all_trash',
        ]);

        $oldRecords = $this->isScopedTrashed($currentUser)
            ->where('deleted_at', '<', now()->subDays(self::MIN_DAYS_BEFORE_PERMANENT_DELETE))
            ->get();

        $count = $oldRecords->count();
        $deletedCount = 0;
        $failed = [];

        DB::beginTransaction();

        try {
            foreach ($oldRecords as $personnel) {
                try {
                    $personnel->forceFill([
                        'name'              => 'Deleted User',
                        'email'             => "deleted_{$personnel->id}@deleted.example",
                        'phone'             => null,
                        'username'          => "deleted_{$personnel->id}",
                        'digital_address'   => null,
                        'location'          => null,
                        'photo'             => null,
                        'phone_verified_at' => null,
                        'email_verified_at' => null,
                        'last_login_at'     => null,
                        'last_activity_at'  => null,
                    ])->saveQuietly();

                    $personnel->roles()->detach();
                    $personnel->forceDelete();
                    $deletedCount++;
                } catch (Throwable $e) {
                    $failed[] = "{$personnel->name} (ID: {$personnel->id}): " . $e->getMessage();
                    Log::error("Failed to permanently delete user {$personnel->id} during empty trash: " . $e->getMessage());
                }
            }

            DB::commit();

            Log::warning('Trash emptied (age-gated)', [
                'emptied_by'      => $currentUser->id,
                'emptied_by_name' => $currentUser->name,
                'total_eligible'  => $count,
                'deleted'         => $deletedCount,
                'failed'          => count($failed),
            ]);

            $message = "Trash emptied: {$deletedCount} of {$count} eligible records permanently deleted.";
            if (!empty($failed)) {
                $message .= " Failed: " . implode(', ', array_slice($failed, 0, 3));
            }

            return $this->cleanJsonResponse([
                'success'       => true,
                'deleted_count' => $deletedCount,
                'total_count'   => $count,
                'failed_users'  => $failed,
                'message'       => $message,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to empty trash: ' . $e->getMessage());

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to empty trash: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ================================================================
    // CREATE / STORE (unchanged from previous version)
    // ================================================================

    public function create()
    {
        $currentUser = Auth::user();

        if (! $this->isActiveHead($currentUser)) {
            return redirect()->route('security.dashboard')
                ->with('error', 'Only the current Head of Security can submit personnel.');
        }

        $statuses = ['pending' => 'Pending Approval'];

        $communicationStatus = $this->getCommunicationServiceStatus();
        $smsStatus           = $communicationStatus['sms'] ?? [];
        $whatsappStatus      = $communicationStatus['whatsapp'] ?? [];
        $emailStatus         = $communicationStatus['email'] ?? [];
        $multiChannelStatus  = $communicationStatus['multi_channel'] ?? [];

        $smsStatus['system_ready']      = $smsStatus['system_ready']      ?? ($smsStatus['enabled'] ?? false);
        $whatsappStatus['system_ready'] = $whatsappStatus['system_ready'] ?? ($whatsappStatus['enabled'] ?? false);
        $emailStatus['system_ready']    = $emailStatus['system_ready']    ?? ($emailStatus['enabled'] ?? false);

        return view('security.personnel.create', compact(
            'statuses',
            'smsStatus', 'whatsappStatus', 'emailStatus', 'multiChannelStatus'
        ));
    }

    public function store(StoreSecurityPersonnelRequest $request)
    {
        $currentUser = Auth::user();

        if (! $this->isActiveHead($currentUser)) {
            return redirect()->route('security.dashboard')
                ->with('error', 'Only the current Head of Security can submit personnel.');
        }

        DB::beginTransaction();

        try {
            $userData = method_exists($request, 'validatedPayload')
                ? $request->validatedPayload()
                : $request->validated();

            $userData['type']              = User::TYPE_SECURITY_PERSONNEL;
            $userData['created_by']        = $currentUser->id;
            $userData['status']            = User::STATUS_PENDING;
            $userData['can_be_supervisor'] = false;

            $passwordWasSeeded = empty($userData['password']);
            if ($passwordWasSeeded) {
                $userData['password'] = Str::random(64);
            }

            $queuedInvitation = method_exists($request, 'queuedInvitation')
                ? $request->queuedInvitation()
                : null;

            $metadata = array_merge($this->decodeMetadata($userData['metadata'] ?? []), [
                'created_via'          => 'head_of_security',
                'created_by_head_id'   => $currentUser->id,
                'created_by_head_name' => $currentUser->name,
                'awaiting_approval'    => true,
                'submitted_at'         => now()->toISOString(),
            ]);

            if ($queuedInvitation) {
                $metadata['queued_invitation'] = $queuedInvitation;
            }

            $userData['metadata'] = $metadata;

            $user = $this->userRepository->createUser($userData, $currentUser);

            $persistedMetadata = $this->decodeMetadata($user->metadata);
            $needsRepair =
                empty($persistedMetadata['awaiting_approval'])
                || ($queuedInvitation && empty($persistedMetadata['queued_invitation']));

            if ($needsRepair) {
                Log::warning('store(): createUser() did not preserve metadata — repairing', [
                    'user_id'        => $user->id,
                    'had_awaiting'   => ! empty($persistedMetadata['awaiting_approval']),
                    'had_queued_inv' => ! empty($persistedMetadata['queued_invitation']),
                ]);

                $user->update(['metadata' => array_merge($persistedMetadata, $metadata)]);
            }

            DB::commit();

            if ($queuedInvitation) {
                Log::info('Invitation queued for admin approval', [
                    'user_id'  => $user->id,
                    'channels' => $queuedInvitation['channels'] ?? [],
                ]);
            }

            $user = $user->fresh() ?? $user;

            $eventPayload = array_merge($request->validated(), [
                'metadata'          => $this->decodeMetadata($user->metadata),
                'queued_invitation' => $queuedInvitation,
            ]);

            try {
                event(new UserCreated($user, $currentUser, $eventPayload));
            } catch (Throwable $notifyError) {
                Log::warning('UserCreated listeners failed after successful submission', [
                    'user_id'      => $user->id,
                    'submitted_by' => $currentUser->id,
                    'error'        => $notifyError->getMessage(),
                ]);
            }

            return redirect()
                ->route('security.personnel.index', ['tab' => 'pending'])
                ->with('success',
                    "{$user->name} submitted for admin approval. You will be able to invite them once an administrator approves the record."
                )
                ->with('created_user_id', $user->id);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Failed to submit security personnel: ' . $e->getMessage(), [
                'user_data'    => $request->validated(),
                'error_trace'  => $e->getTraceAsString(),
                'submitted_by' => $currentUser->id,
            ]);

            return back()->with('error', 'Failed to submit personnel: ' . $e->getMessage())->withInput();
        }
    }

    // ================================================================
    // SHOW / INVITATIONS (unchanged)
    // ================================================================

    public function show($userId)
    {
        $currentUser = Auth::user();

        if (! $this->isActiveHead($currentUser)) {
            return $this->cleanJsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        try {
            $personnel = User::findOrFail($userId);

            if ($personnel->type !== User::TYPE_SECURITY_PERSONNEL) {
                return $this->cleanJsonResponse(['success' => false, 'message' => 'User is not security personnel.'], 422);
            }

            if (! $this->isInScope($currentUser, $personnel)) {
                return $this->cleanJsonResponse(['success' => false, 'message' => 'This personnel is not within your scope.'], 403);
            }

            try { $personnel->loadMissing('roles'); } catch (Throwable $e) {}

            $metadata = $this->decodeMetadata($personnel->metadata);

            $payload = [
                'id'    => $personnel->id,
                'name'  => $personnel->name,
                'email' => $personnel->email,
                'phone' => $personnel->phone,
            ];

            $createdVia    = $metadata['created_via'] ?? null;
            $createdByRole = $metadata['created_by_role'] ?? null;
            $isAdminCreated = $createdVia === 'manual_by_admin'
                || in_array($createdByRole, ['admin', 'super_admin'], true);

            $payload += [
                'username'          => $personnel->username,
                'status'            => $personnel->status,
                'type'              => $personnel->type,
                'can_be_supervisor' => (bool) $personnel->can_be_supervisor,
                'awaiting_approval' => (bool) ($metadata['awaiting_approval'] ?? false),
                'rejected_at'       => $metadata['rejected_at'] ?? null,
                'rejection_reason'  => $metadata['rejection_reason'] ?? null,
                'created_by'        => $personnel->created_by,
                'approved_at'       => $metadata['approved_at'] ?? null,
                'approved_by_name'  => $metadata['approved_by_name'] ?? null,
                'rejected_by_name'  => $metadata['rejected_by_name'] ?? null,
                'created_via'       => $createdVia,
                'created_by_role'   => $createdByRole,
                'is_admin_created'  => $isAdminCreated,
                'invitation_sent_at' => optional($personnel->invitation_sent_at)->toIso8601String(),
                'last_login_at'      => optional($personnel->last_login_at)->toIso8601String(),
                'created_at'         => optional($personnel->created_at)->toIso8601String(),
                'updated_at'         => optional($personnel->updated_at)->toIso8601String(),
                'submitted_at'       => $metadata['submitted_at']
                    ?? optional($personnel->created_at)->toIso8601String(),
            ];

            return $this->cleanJsonResponse($payload);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->cleanJsonResponse(['success' => false, 'message' => 'Personnel not found.'], 404);
        } catch (Throwable $e) {
            Log::error('Failed to load personnel details: ' . $e->getMessage(), [
                'user_id'     => $userId,
                'error_class' => get_class($e),
                'file'        => $e->getFile() . ':' . $e->getLine(),
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to load personnel details.',
                'debug'   => config('app.debug') ? [
                    'class'   => get_class($e),
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile() . ':' . $e->getLine(),
                ] : null,
            ], 500);
        }
    }

    public function sendInvitation(Request $request, $userId) { /* unchanged — keep from your version */ }
    public function resendInvitation(Request $request, $userId) { /* unchanged — keep from your version */ }
    public function getInvitationInfo($userId) { /* unchanged — keep from your version */ }
    public function getUserChannels($userId) { /* unchanged — keep from your version */ }
    public function bulkSendInvitation(Request $request) { /* unchanged — keep from your version */ }

    // ================================================================
    // HELPERS
    // ================================================================

    private function isActiveHead(User $user): bool
    {
        if (! $user->isSecurityPersonnel() || ! $user->can_be_supervisor) {
            return false;
        }

        return \App\Models\SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->exists();
    }

    private function isAwaitingApproval(User $user): bool
    {
        $metadata = $this->decodeMetadata($user->metadata);
        return ! empty($metadata['awaiting_approval']);
    }

    /**
     * Scope for the trash view and all trash actions.
     *
     * Only personnel the current head personally submitted — never
     * admin-created records, never another head's submissions.
     */
    private function isScopedTrashed(User $head)
    {
        return User::onlyTrashed()
            ->where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('created_by', $head->id);
    }

    private function isInScope(User $currentUser, User $personnel): bool
    {
        if ((int) $personnel->id === (int) $currentUser->id) return true;
        if ((int) $personnel->created_by === (int) $currentUser->id) return true;

        $metadata = $this->decodeMetadata($personnel->metadata);
        if (($metadata['created_via'] ?? null) === 'manual_by_admin') return true;
        if (in_array($metadata['created_by_role'] ?? null, ['admin', 'super_admin'], true)) return true;

        try {
            $creator = $personnel->relationLoaded('creator')
                ? $personnel->creator
                : $personnel->creator()->first();

            if ($creator && in_array($creator->type, [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN], true)) {
                return true;
            }
        } catch (Throwable $e) {}

        return false;
    }

    private function authorizePersonnelAccess(User $currentUser, ?User $target, string $msg = 'Not security personnel.'): ?JsonResponse
    {
        if (! $target) {
            return $this->cleanJsonResponse(['success' => false, 'message' => 'User not found.'], 404);
        }

        if ($target->type !== User::TYPE_SECURITY_PERSONNEL) {
            return $this->cleanJsonResponse(['success' => false, 'message' => $msg], 422);
        }

        if (! $this->isInScope($currentUser, $target)) {
            return $this->cleanJsonResponse(['success' => false, 'message' => 'This personnel is not within your scope.'], 403);
        }

        return null;
    }

    private function resolvePerPage($requested): int
    {
        $perPage = (int) $requested;
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }
        return $perPage;
    }

    private function getScopedSecurityPersonnel(User $head, Request $request, string $tab)
    {
        $query = User::query()
            ->where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('id', '!=', $head->id)
            ->where(function ($scope) use ($head) {
                $scope->where('created_by', $head->id)
                    ->orWhere('metadata->created_via', 'manual_by_admin')
                    ->orWhereIn('metadata->created_by_role', ['admin', 'super_admin'])
                    ->orWhereHas('creator', function ($q) {
                        $q->where('type', User::TYPE_ADMIN)
                          ->orWhere('type', User::TYPE_SUPER_ADMIN);
                    });
            });

        if ($tab === 'pending') {
            $query->where('status', User::STATUS_PENDING)
                  ->where(function ($q) {
                      $q->where('metadata->awaiting_approval', true)
                        ->orWhereNull('metadata->awaiting_approval');
                  });
        } elseif ($tab === 'active') {
            $query->where('status', User::STATUS_ACTIVE)
                  ->where(function ($q) {
                      $q->whereNull('metadata->awaiting_approval')
                        ->orWhere('metadata->awaiting_approval', false);
                  })
                  ->whereNull('metadata->rejected_at');
        } elseif ($tab === 'unassigned') {
            $query->where('status', User::STATUS_ACTIVE)
                  ->where('can_be_supervisor', false);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $this->resolvePerPage($request->get('per_page'));

        return $query->orderBy('name')->paginate($perPage)->withQueryString();
    }

    private function buildStats(User $head): array
    {
        $base = User::query()
            ->where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('id', '!=', $head->id)
            ->where(function ($scope) use ($head) {
                $scope->where('created_by', $head->id)
                    ->orWhere('metadata->created_via', 'manual_by_admin')
                    ->orWhereIn('metadata->created_by_role', ['admin', 'super_admin'])
                    ->orWhereHas('creator', function ($q) {
                        $q->where('type', User::TYPE_ADMIN)
                          ->orWhere('type', User::TYPE_SUPER_ADMIN);
                    });
            });

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', User::STATUS_ACTIVE)
                ->where(function ($q) {
                    $q->whereNull('metadata->awaiting_approval')
                      ->orWhere('metadata->awaiting_approval', false);
                })
                ->whereNull('metadata->rejected_at')
                ->count(),
            'pending' => (clone $base)->where('status', User::STATUS_PENDING)
                ->where(function ($q) {
                    $q->where('metadata->awaiting_approval', true)
                      ->orWhereNull('metadata->awaiting_approval');
                })
                ->count(),
            'unassigned' => (clone $base)->where('status', User::STATUS_ACTIVE)
                ->where('can_be_supervisor', false)->count(),
            'eligible_supervisors' => (clone $base)->where('can_be_supervisor', true)->count(),
        ];
    }

    private function getAvailableChannelsForUser(User $user): array
    {
        $channels = [];
        if (! empty($user->email)) $channels[] = 'email';
        if (! empty($user->phone)) { $channels[] = 'sms'; $channels[] = 'whatsapp'; }
        return $channels;
    }

    private function getCommunicationServiceStatus(): array
    {
        try {
            return cache()->remember('security_communication_service_status', 300, function () {
                $status = [];
                try {
                    $status['sms'] = class_exists(\App\Services\SmsService::class)
                        ? app(\App\Services\SmsService::class)->getSystemStatus() ?? []
                        : ['enabled' => false, 'system_ready' => false, 'status' => 'unavailable', 'health' => 'unavailable'];
                } catch (Throwable $e) {
                    $status['sms'] = ['enabled' => false, 'system_ready' => false, 'status' => 'error', 'health' => 'unhealthy'];
                }
                try {
                    $status['whatsapp'] = class_exists(\App\Services\WhatsAppService::class)
                        ? app(\App\Services\WhatsAppService::class)->getSystemStatus() ?? []
                        : ['enabled' => false, 'system_ready' => false, 'status' => 'unavailable', 'health' => 'unavailable'];
                } catch (Throwable $e) {
                    $status['whatsapp'] = ['enabled' => false, 'system_ready' => false, 'status' => 'error', 'health' => 'unhealthy'];
                }
                try {
                    $status['email'] = class_exists(\App\Services\EmailService::class)
                        ? app(\App\Services\EmailService::class)->getSystemStatus() ?? []
                        : ['enabled' => true, 'system_ready' => true, 'status' => 'active', 'health' => 'healthy'];
                } catch (Throwable $e) {
                    $status['email'] = ['enabled' => true, 'system_ready' => true, 'status' => 'degraded', 'health' => 'degraded'];
                }
                $status['multi_channel'] = ['enabled' => true, 'system_ready' => true, 'status' => 'active', 'health' => 'healthy'];
                return $status;
            });
        } catch (Throwable $e) {
            return [
                'sms'           => ['enabled' => false, 'system_ready' => false, 'status' => 'error'],
                'email'         => ['enabled' => true,  'system_ready' => true,  'status' => 'active'],
                'whatsapp'      => ['enabled' => false, 'system_ready' => false, 'status' => 'error'],
                'multi_channel' => ['enabled' => true,  'system_ready' => true,  'status' => 'active'],
            ];
        }
    }

    private function decodeMetadata($metadata): array
    {
        if (is_array($metadata)) return $metadata;
        if (! is_string($metadata) || $metadata === '') return [];
        $decoded = json_decode($metadata, true);
        if (is_string($decoded)) $decoded = json_decode($decoded, true) ?? [];
        return is_array($decoded) ? $decoded : [];
    }

    protected function cleanJsonResponse($data, $status = 200): JsonResponse
    {
        while (ob_get_level()) ob_end_clean();
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($json) && substr($json, 0, 3) === "\xEF\xBB\xBF") $json = substr($json, 3);
        return response()->json(json_decode($json, true), $status, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'no-cache, must-revalidate',
        ]);
    }
}