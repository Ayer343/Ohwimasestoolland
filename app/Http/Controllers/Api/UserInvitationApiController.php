<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvitation;
use App\Models\SystemSetting;
use App\Services\UserInvitationService;
use App\Services\MultiChannelInvitationService;
use App\Services\SmsService;
use App\Services\EmailService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserInvitationApiController extends Controller
{
    protected UserInvitationService $userInvitationService;
    protected MultiChannelInvitationService $multiChannelInvitationService;
    protected SmsService $smsService;
    protected EmailService $emailService;
    protected WhatsAppService $whatsappService;

    // Configurable expiration properties
    protected int $invitationExpiryDays;
    protected int $invitationWarningDays;
    protected bool $invitationAutoExpiry;
    protected bool $invitationResendExtendsExpiry;

    protected const MAX_PER_PAGE = 100;
    protected const DEFAULT_PER_PAGE = 15;
    protected const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100];

    public function __construct(
        UserInvitationService $userInvitationService,
        MultiChannelInvitationService $multiChannelInvitationService,
        SmsService $smsService,
        EmailService $emailService,
        WhatsAppService $whatsappService
    ) {
        $this->userInvitationService = $userInvitationService;
        $this->multiChannelInvitationService = $multiChannelInvitationService;
        $this->smsService = $smsService;
        $this->emailService = $emailService;
        $this->whatsappService = $whatsappService;

        $this->invitationExpiryDays = (int) config('app.invitation_expiry_days', 7);
        $this->invitationWarningDays = (int) config('app.invitation_warning_days', 2);
        $this->invitationAutoExpiry = (bool) config('app.invitation_auto_expiry', true);
        $this->invitationResendExtendsExpiry = (bool) config('app.invitation_resend_extend_expiry', true);
    }

    // ==================================================================
    // ==================== RESPONSE HELPERS ============================
    // ==================================================================

    /**
     * Strip UTF-8 BOM (up to 3x) and fix Content-Length.
     */
    protected function cleanBomResponse($response)
    {
        $content = $response->getContent();
        if ($content === null || $content === '') {
            return $response;
        }

        for ($i = 0; $i < 3; $i++) {
            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $content = substr($content, 3);
            } else {
                break;
            }
        }

        $response->setContent($content);
        $response->headers->set('Content-Length', strlen($content));
        return $response;
    }

    protected function cleanJsonResponse($data, $status = 200, array $headers = [], $options = 0)
    {
        return $this->cleanBomResponse(response()->json($data, $status, $headers, $options));
    }

    protected function apiResponse(
        bool $success,
        $data = null,
        ?string $message = null,
        int $status = 200,
        array $extra = []
    ) {
        $payload = array_merge([
            'success' => $success,
            'data'    => $data,
            'message' => $message,
        ], $extra);

        return $this->cleanJsonResponse($payload, $status);
    }

    protected function apiError(string $message, int $status = 400, array $extra = [])
    {
        return $this->apiResponse(false, null, $message, $status, $extra);
    }

    // ==================================================================
    // ============ PUBLIC ACCEPTANCE FLOW (no auth) ====================
    // ==================================================================

    /**
     * ✅ Validate an invitation token and return metadata.
     * GET /api/invitations/{token}/verify
     */
    public function verifyToken($token)
    {
        try {
            // Reuse the same safe-repair + status logic as the web form.
            $result = $this->resolveInvitationByToken($token);

            if (!$result['success']) {
                return $this->apiError($result['message'], $result['status'] ?? 404, [
                    'error_code' => $result['error_code'] ?? 'INVALID_TOKEN',
                ]);
            }

            /** @var UserInvitation $invitation */
            $invitation = $result['invitation'];

            return $this->apiResponse(true, [
                'valid'                 => true,
                'invitation_id'         => $invitation->id,
                'token'                 => $invitation->token,
                'status'                => $invitation->status,
                'safe_status'           => $invitation->getSafeStatus(),
                'invitation_type'       => $invitation->invitation_type,
                'expires_at'            => $invitation->expires_at?->toISOString(),
                'expires_in_human'      => $invitation->expires_at?->diffForHumans(),
                'days_until_expiry'     => $invitation->getDaysUntilExpiry(),
                'is_expiring_soon'      => $invitation->isExpiringSoon($this->invitationWarningDays),
                'warning_days'          => $this->invitationWarningDays,
                'was_repaired'          => $result['was_repaired'] ?? false,
                'available_channels'    => $this->getAvailableVerificationChannels($invitation->user),
                'custom_message'        => $invitation->custom_message,
            ], 'Token is valid.');

        } catch (\Exception $e) {
            Log::error('API verify token failed', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return $this->apiError('Error verifying invitation token.', 500, [
                'error_code' => 'VERIFY_FAILED',
            ]);
        }
    }

    /**
     * ✅ Return full invitation details (user + inviter + branding).
     * GET /api/invitations/{token}/details
     */
    public function getInvitationDetails($token)
    {
        try {
            $result = $this->resolveInvitationByToken($token);

            if (!$result['success']) {
                return $this->apiError($result['message'], $result['status'] ?? 404, [
                    'error_code' => $result['error_code'] ?? 'INVALID_TOKEN',
                ]);
            }

            /** @var UserInvitation $invitation */
            $invitation = $result['invitation'];
            $systemSettings = SystemSetting::getSettings();

            return $this->apiResponse(true, [
                'invitation' => [
                    'id'                    => $invitation->id,
                    'token'                 => $invitation->token,
                    'status'                => $invitation->status,
                    'safe_status'           => $invitation->getSafeStatus(),
                    'invitation_type'       => $invitation->invitation_type,
                    'custom_message'        => $invitation->custom_message,
                    'channels'              => $invitation->channels,
                    'sent_at'               => $invitation->sent_at?->toISOString(),
                    'expires_at'            => $invitation->expires_at?->toISOString(),
                    'expires_in_human'      => $invitation->expires_at?->diffForHumans(),
                    'days_until_expiry'     => $invitation->getDaysUntilExpiry(),
                    'is_expiring_soon'      => $invitation->isExpiringSoon($this->invitationWarningDays),
                    'accepted_at'           => $invitation->accepted_at?->toISOString(),
                    'was_repaired'          => $result['was_repaired'] ?? false,
                ],
                'user' => [
                    'id'       => $invitation->user->id,
                    'name'     => $invitation->user->name,
                    'email'    => $invitation->user->email,
                    'phone'    => $invitation->user->phone,
                    'type'     => $invitation->user->type,
                    'type_name'=> $invitation->user->type_name,
                    'status'   => $invitation->user->status,
                    'initials' => $invitation->user->initials ?? null,
                    'avatar'   => $invitation->user->avatar_url ?? null,
                ],
                'invited_by' => $invitation->invitedBy ? [
                    'id'   => $invitation->invitedBy->id,
                    'name' => $invitation->invitedBy->name,
                ] : null,
                'available_channels' => $this->getAvailableVerificationChannels($invitation->user),
                'expiry_config' => [
                    'expiry_days'         => $this->invitationExpiryDays,
                    'warning_days'        => $this->invitationWarningDays,
                    'auto_expiry_enabled' => $this->invitationAutoExpiry,
                ],
                'branding' => [
                    'system_name'  => $systemSettings->system_name ?? config('app.name'),
                    'support_email'=> $systemSettings->system_email ?? 'support@example.com',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('API invitation details failed', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return $this->apiError('Error retrieving invitation details.', 500, [
                'error_code' => 'DETAILS_FAILED',
            ]);
        }
    }

    /**
     * ✅ Accept an invitation: set password, agree terms, optionally verify code.
     * POST /api/invitations/{token}/accept
     *
     * This fully replaces the old stub and delegates to UserInvitationService.
     */
    public function accept(Request $request, $token)
    {
        $validator = Validator::make($request->all(), [
            'password' => [
                'required', 'string', 'min:8', 'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            ],
            'agree_terms'   => 'required|accepted',
            'agree_privacy' => 'required|accepted',
            'verification_code'    => 'sometimes|required|size:6',
            'verification_channel' => 'sometimes|required|in:sms,whatsapp,email',
        ], [
            'password.required'   => 'Password is required.',
            'password.min'        => 'Password must be at least 8 characters.',
            'password.confirmed'  => 'Password confirmation does not match.',
            'password.regex'      => 'Password must contain uppercase, lowercase, number and special character.',
            'agree_terms.accepted'   => 'You must accept the terms and conditions.',
            'agree_privacy.accepted' => 'You must accept the privacy policy.',
            'verification_code.size'=> 'Verification code must be 6 digits.',
        ]);

        if ($validator->fails()) {
            return $this->apiError('Validation failed.', 422, [
                'errors' => $validator->errors(),
                'error_code' => 'VALIDATION_FAILED',
            ]);
        }

        // First: resolve & validate the invitation via the same safe path.
        $resolution = $this->resolveInvitationByToken($token);

        if (!$resolution['success']) {
            return $this->apiError($resolution['message'], $resolution['status'] ?? 404, [
                'error_code' => $resolution['error_code'] ?? 'INVALID_TOKEN',
            ]);
        }

        /** @var UserInvitation $invitation */
        $invitation = $resolution['invitation'];

        // Idempotency: already accepted → friendly success
        if ($invitation->isAccepted()) {
            return $this->apiResponse(true, [
                'already_accepted' => true,
                'user_id'          => $invitation->user_id,
                'invitation_id'    => $invitation->id,
            ], 'Invitation was already accepted.');
        }

        // Cancelled / revoked / failed → reject
        if (in_array($invitation->status, [
            UserInvitation::STATUS_CANCELLED,
            UserInvitation::STATUS_REVOKED,
            UserInvitation::STATUS_FAILED,
        ], true)) {
            return $this->apiError('This invitation is no longer valid.', 422, [
                'error_code' => 'INVITATION_INVALID',
                'status'     => $invitation->status,
            ]);
        }

        $acceptanceData = [
            'password'             => $request->password,
            'agree_terms'          => $request->boolean('agree_terms'),
            'agree_privacy'        => $request->boolean('agree_privacy'),
            'verification_code'    => $request->input('verification_code'),
            'verification_channel' => $request->input('verification_channel'),
            'ip'                   => $request->ip(),
            'user_agent'           => $request->userAgent(),
        ];

        DB::beginTransaction();

        try {
            // Delegate to service — same code path as web.
            $result = $this->userInvitationService->processInvitationAcceptance($token, $acceptanceData);

            if (!$result['success']) {
                DB::rollBack();
                return $this->apiError($result['message'] ?? 'Failed to accept invitation.', 422, [
                    'error_code' => 'ACCEPT_FAILED',
                ]);
            }

            DB::commit();

            $user = $result['user'];
            $wasRepaired = $resolution['was_repaired'] ?? false;

            // Issue a Sanctum token for the mobile client (if the app uses tokens).
            $authToken = null;
            $tokenName = 'invitation-accept-' . $user->id;
            if (method_exists($user, 'createToken')) {
                $authToken = $user->createToken($tokenName)->plainTextToken;
            }

            Log::info('Invitation accepted via API', [
                'invitation_id' => $invitation->id,
                'user_id'       => $user->id,
                'user_type'     => $user->type,
                'ip'            => $request->ip(),
                'was_repaired'  => $wasRepaired,
            ]);

            return $this->apiResponse(true, [
                'message'        => $this->getWelcomeMessage($user),
                'user'           => $this->formatUserForApi($user),
                'redirect_route' => $this->getRedirectRouteForUser($user),
                'auth_token'     => $authToken,
                'token_type'     => $authToken ? 'Bearer' : null,
                'was_repaired'   => $wasRepaired,
                'invitation_id'  => $invitation->id,
            ], 'Invitation accepted successfully.', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API invitation acceptance failed', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->apiError('Failed to accept invitation.', 500, [
                'error_code' => 'ACCEPT_FAILED',
            ]);
        }
    }

    // ==================================================================
    // ================ ADMIN SEND / RESEND =============================
    // ==================================================================

    /**
     * ✅ Send a new invitation (admin).
     * POST /api/invitations/send
     */
    public function sendInvitation(Request $request)
    {
        $currentUser = Auth::user();
        if (!$currentUser || (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin())) {
            return $this->apiError('Unauthorized.', 403, ['error_code' => 'UNAUTHORIZED']);
        }

        $validator = Validator::make($request->all(), [
            'user_id'         => 'required|exists:users,id',
            'invitation_type' => 'required|in:welcome,registration,account_setup,password_setup',
            'channels'        => 'sometimes|array',
            'channels.*'      => 'in:email,sms,whatsapp',
            'custom_message'  => 'nullable|string|max:500',
            'expires_in_days' => 'nullable|integer|min:1|max:30',
        ]);

        if ($validator->fails()) {
            return $this->apiError('Validation failed.', 422, [
                'errors' => $validator->errors(),
                'error_code' => 'VALIDATION_FAILED',
            ]);
        }

        try {
            $result = $this->userInvitationService->createAndSendInvitation(
                $request->all(),
                $currentUser
            );

            if (!$result['success']) {
                return $this->apiError($result['message'] ?? 'Failed to send invitation.', 422, [
                    'error_code' => 'SEND_FAILED',
                ]);
            }

            $invitation = $result['invitation'];
            $user = $invitation->user;

            return $this->apiResponse(true, [
                'invitation_url'     => $invitation->getInvitationUrl(),
                'delivery_results'   => $result['delivery_results'] ?? [],
                'available_channels' => $result['available_channels'] ?? [],
                'invitation' => [
                    'id'              => $invitation->id,
                    'token'           => $invitation->token,
                    'expires_at'      => $invitation->expires_at?->toISOString(),
                    'channels'        => $invitation->channels,
                    'invitation_type' => $invitation->invitation_type,
                    'custom_message'  => $invitation->custom_message,
                ],
                'user' => [
                    'id'     => $user->id,
                    'name'   => $user->name,
                    'email'  => $user->email,
                    'phone'  => $user->phone,
                    'type'   => $user->type_name,
                    'status' => $user->status,
                ],
                'config' => [
                    'expiry_days'         => $this->invitationExpiryDays,
                    'warning_days'        => $this->invitationWarningDays,
                    'auto_expiry_enabled' => $this->invitationAutoExpiry,
                ],
            ], 'Invitation sent successfully!', 201);

        } catch (\Exception $e) {
            Log::error('API send invitation failed', ['error' => $e->getMessage()]);
            return $this->apiError('Failed to send invitation.', 500, ['error_code' => 'SEND_FAILED']);
        }
    }

    /**
     * ✅ Resend an invitation (admin).
     * POST /api/invitations/{userId}/resend
     */
    public function resendInvitation(Request $request, $userId)
    {
        $currentUser = Auth::user();
        if (!$currentUser || (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin())) {
            return $this->apiError('Unauthorized.', 403, ['error_code' => 'UNAUTHORIZED']);
        }

        try {
            $user = User::findOrFail($userId);

            $result = $this->userInvitationService->resendInvitation(
                $user,
                $currentUser,
                $request->all()
            );

            if (!$result['success']) {
                return $this->apiError($result['message'] ?? 'Failed to resend invitation.', 422, [
                    'error_code' => 'RESEND_FAILED',
                ]);
            }

            $invitation = $result['invitation'];

            return $this->apiResponse(true, [
                'invitation_url'   => $invitation->getInvitationUrl(),
                'delivery_results' => $result['delivery_results'] ?? [],
                'invitation' => [
                    'id'              => $invitation->id,
                    'token'           => $invitation->token,
                    'expires_at'      => $invitation->expires_at?->toISOString(),
                    'channels'        => $invitation->channels,
                    'invitation_type' => $invitation->invitation_type,
                    'custom_message'  => $invitation->custom_message,
                ],
                'user' => [
                    'id'     => $user->id,
                    'name'   => $user->name,
                    'email'  => $user->email,
                    'phone'  => $user->phone,
                    'type'   => $user->type_name,
                    'status' => $user->status,
                ],
            ], 'Invitation resent successfully!');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->apiError('User not found.', 404, ['error_code' => 'USER_NOT_FOUND']);
        } catch (\Exception $e) {
            Log::error('API resend invitation failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return $this->apiError('Failed to resend invitation.', 500, ['error_code' => 'RESEND_FAILED']);
        }
    }

    // ==================================================================
    // ================ STATUS / HISTORY / ANALYTICS ====================
    // ==================================================================

    /**
     * GET /api/invitations/status/{userId}
     */
    public function getInvitationStatus($userId)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return $this->apiError('Unauthenticated.', 401, ['error_code' => 'UNAUTHENTICATED']);
        }

        try {
            $user = User::with(['invitations' => fn($q) => $q->latest(), 'invitations.invitedBy'])
                ->findOrFail($userId);

            if (!$currentUser->isSuperAdmin()
                && !$currentUser->isAdmin()
                && $currentUser->id !== $user->id
                && $currentUser->id !== $user->created_by) {
                return $this->apiError('Forbidden.', 403, ['error_code' => 'FORBIDDEN']);
            }

            $latest = $user->invitations->first();
            $availableChannels = $this->userInvitationService->getAvailableChannels($user);

            return $this->apiResponse(true, [
                'total_invitations'      => $user->invitations->count(),
                'has_valid_invitation'   => (bool) ($user->has_valid_invitation ?? false),
                'invitation_status'      => $latest?->status,
                'can_receive_invitation' => method_exists($user, 'canReceiveInvitation')
                    ? $user->canReceiveInvitation()
                    : true,
                'needs_password_setup'   => $user->status === User::STATUS_PENDING,
                'available_channels'     => $availableChannels,
                'latest_invitation'      => $latest ? [
                    'id'              => $latest->id,
                    'type'            => $latest->invitation_type,
                    'channels'        => $latest->channels,
                    'sent_at'         => $latest->sent_at?->toISOString(),
                    'expires_at'      => $latest->expires_at?->toISOString(),
                    'accepted_at'     => $latest->accepted_at?->toISOString(),
                    'invited_by'      => $latest->invitedBy?->name,
                ] : null,
                'latest_invitation_url' => $latest ? $latest->getInvitationUrl() : null,
                'user' => [
                    'id'     => $user->id,
                    'name'   => $user->name,
                    'email'  => $user->email,
                    'phone'  => $user->phone,
                    'type'   => $user->type_name,
                    'status' => $user->status,
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->apiError('User not found.', 404, ['error_code' => 'USER_NOT_FOUND']);
        } catch (\Exception $e) {
            Log::error('API invitation status failed', ['error' => $e->getMessage()]);
            return $this->apiError('Error retrieving invitation status.', 500, ['error_code' => 'STATUS_FAILED']);
        }
    }

    /**
     * GET /api/invitations/history/{userId}
     */
    public function getUserInvitationHistory($userId)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return $this->apiError('Unauthenticated.', 401, ['error_code' => 'UNAUTHENTICATED']);
        }

        try {
            $user = User::findOrFail($userId);

            if (!$currentUser->isSuperAdmin()
                && !$currentUser->isAdmin()
                && $currentUser->id !== $user->id) {
                return $this->apiError('Forbidden.', 403, ['error_code' => 'FORBIDDEN']);
            }

            $history = $this->userInvitationService->getUserInvitationHistory($user);

            return $this->apiResponse(true, [
                'history' => $history,
                'total'   => is_array($history) ? count($history) : $history->count(),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->apiError('User not found.', 404, ['error_code' => 'USER_NOT_FOUND']);
        } catch (\Exception $e) {
            Log::error('API invitation history failed', ['error' => $e->getMessage()]);
            return $this->apiError('Error retrieving invitation history.', 500, ['error_code' => 'HISTORY_FAILED']);
        }
    }

    /**
     * GET /api/invitations
     */
    public function index(Request $request)
    {
        $currentUser = Auth::user();
        if (!$currentUser || (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin())) {
            return $this->apiError('Forbidden.', 403, ['error_code' => 'FORBIDDEN']);
        }

        try {
            $perPage = $this->getPerPage($request->get('per_page'));
            $query = UserInvitation::with(['user', 'invitedBy']);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('type')) {
                $query->where('invitation_type', $request->type);
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            $invitations = $query->latest()->paginate($perPage);

            return $this->apiResponse(true, $invitations->items(), null, 200, [
                'meta' => [
                    'current_page' => $invitations->currentPage(),
                    'last_page'    => $invitations->lastPage(),
                    'per_page'     => $invitations->perPage(),
                    'total'        => $invitations->total(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('API invitation index failed', ['error' => $e->getMessage()]);
            return $this->apiError('Error retrieving invitations.', 500, ['error_code' => 'INDEX_FAILED']);
        }
    }

    /**
     * GET /api/invitations/{id}
     */
    public function show($invitationId)
    {
        $currentUser = Auth::user();
        if (!$currentUser || (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin())) {
            return $this->apiError('Forbidden.', 403, ['error_code' => 'FORBIDDEN']);
        }

        try {
            $invitation = UserInvitation::with(['user', 'invitedBy'])->findOrFail($invitationId);

            return $this->apiResponse(true, [
                'invitation' => $invitation,
                'invitation_url' => $invitation->getInvitationUrl(),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->apiError('Invitation not found.', 404, ['error_code' => 'INVITATION_NOT_FOUND']);
        } catch (\Exception $e) {
            Log::error('API invitation show failed', ['error' => $e->getMessage()]);
            return $this->apiError('Error retrieving invitation details.', 500, ['error_code' => 'SHOW_FAILED']);
        }
    }

    /**
     * GET /api/invitations/statistics
     */
    public function getInvitationStatistics()
    {
        $currentUser = Auth::user();
        if (!$currentUser || (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin())) {
            return $this->apiError('Forbidden.', 403, ['error_code' => 'FORBIDDEN']);
        }

        try {
            $statistics = $this->userInvitationService->getStatistics();
            return $this->apiResponse(true, ['statistics' => $statistics]);

        } catch (\Exception $e) {
            Log::error('API invitation statistics failed', ['error' => $e->getMessage()]);
            return $this->apiError('Error retrieving statistics.', 500, ['error_code' => 'STATS_FAILED']);
        }
    }

    /**
     * GET /api/invitations/analytics
     */
    public function getInvitationAnalytics()
    {
        $currentUser = Auth::user();
        if (!$currentUser || (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin())) {
            return $this->apiError('Forbidden.', 403, ['error_code' => 'FORBIDDEN']);
        }

        try {
            $analytics = $this->userInvitationService->getInvitationAnalytics();
            return $this->apiResponse(true, ['analytics' => $analytics]);

        } catch (\Exception $e) {
            Log::error('API invitation analytics failed', ['error' => $e->getMessage()]);
            return $this->apiError('Error retrieving analytics.', 500, ['error_code' => 'ANALYTICS_FAILED']);
        }
    }

    /**
     * GET /api/invitations/system-status
     */
    public function getSystemInvitationStatus()
    {
        $currentUser = Auth::user();
        if (!$currentUser || (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin())) {
            return $this->apiError('Forbidden.', 403, ['error_code' => 'FORBIDDEN']);
        }

        try {
            $status = $this->userInvitationService->getSystemInvitationStatus();
            return $this->apiResponse(true, ['system_status' => $status]);

        } catch (\Exception $e) {
            Log::error('API system invitation status failed', ['error' => $e->getMessage()]);
            return $this->apiError('Error retrieving system status.', 500, ['error_code' => 'SYSTEM_STATUS_FAILED']);
        }
    }

    /**
     * GET /api/invitations/expiry-configuration
     */
    public function getExpiryConfiguration()
    {
        try {
            $config = $this->userInvitationService->getExpiryConfiguration();
            return $this->apiResponse(true, ['expiry_configuration' => $config]);

        } catch (\Exception $e) {
            Log::error('API expiry configuration failed', ['error' => $e->getMessage()]);
            return $this->apiError('Error retrieving expiry configuration.', 500, ['error_code' => 'CONFIG_FAILED']);
        }
    }

    /**
     * GET /api/invitations/channels/{userId}
     */
    public function getAvailableChannels($userId)
    {
        $currentUser = Auth::user();
        if (!$currentUser || (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin())) {
            return $this->apiError('Forbidden.', 403, ['error_code' => 'FORBIDDEN']);
        }

        try {
            $user = User::findOrFail($userId);
            $channels = $this->userInvitationService->getAvailableChannels($user);

            return $this->apiResponse(true, [
                'available_channels' => $channels,
                'user' => [
                    'id'        => $user->id,
                    'name'      => $user->name,
                    'phone'     => $user->phone,
                    'email'     => $user->email,
                    'has_phone' => !empty($user->phone),
                    'has_email' => !empty($user->email) && filter_var($user->email, FILTER_VALIDATE_EMAIL),
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->apiError('User not found.', 404, ['error_code' => 'USER_NOT_FOUND']);
        } catch (\Exception $e) {
            Log::error('API available channels failed', ['error' => $e->getMessage()]);
            return $this->apiError('Error retrieving channels.', 500, ['error_code' => 'CHANNELS_FAILED']);
        }
    }

    // ==================================================================
    // ================ CANCEL / CLEANUP / TRACK ========================
    // ==================================================================

    /**
     * POST /api/invitations/{id}/cancel
     */
    public function cancelInvitation($invitationId)
    {
        $currentUser = Auth::user();
        if (!$currentUser || (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin())) {
            return $this->apiError('Forbidden.', 403, ['error_code' => 'FORBIDDEN']);
        }

        try {
            $result = $this->userInvitationService->cancelInvitation($invitationId, $currentUser);

            if (!$result['success']) {
                return $this->apiError($result['message'] ?? 'Failed to cancel invitation.', 422, [
                    'error_code' => 'CANCEL_FAILED',
                ]);
            }

            return $this->apiResponse(true, null, 'Invitation cancelled successfully.');

        } catch (\Exception $e) {
            Log::error('API cancel invitation failed', [
                'invitation_id' => $invitationId,
                'error'         => $e->getMessage(),
            ]);
            return $this->apiError('Failed to cancel invitation.', 500, ['error_code' => 'CANCEL_FAILED']);
        }
    }

    /**
     * POST /api/invitations/cleanup
     */
    public function cleanupExpiredInvitations()
    {
        $currentUser = Auth::user();
        if (!$currentUser || (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin())) {
            return $this->apiError('Forbidden.', 403, ['error_code' => 'FORBIDDEN']);
        }

        try {
            $count = $this->userInvitationService->cleanupOldInvitations();
            return $this->apiResponse(true, ['expired_count' => $count], 'Cleanup completed successfully.');

        } catch (\Exception $e) {
            Log::error('API cleanup expired invitations failed', ['error' => $e->getMessage()]);
            return $this->apiError('Cleanup failed.', 500, ['error_code' => 'CLEANUP_FAILED']);
        }
    }

    /**
     * POST /api/invitations/{token}/track-view
     */
    public function trackInvitationView($token)
    {
        try {
            $this->userInvitationService->trackInvitationView($token);
            return $this->apiResponse(true, null, 'View tracked successfully.');

        } catch (\Exception $e) {
            Log::error('API track invitation view failed', ['error' => $e->getMessage()]);
            return $this->apiError('Error tracking view.', 500, ['error_code' => 'TRACK_FAILED']);
        }
    }

    // ==================================================================
    // ================ PRIVATE HELPERS =================================
    // ==================================================================

    /**
     * ✅ Central token resolution — performs safe repair + status validation.
     * Returns ['success' => bool, 'invitation' => UserInvitation|null,
     *          'message' => string, 'status' => int, 'error_code' => string,
     *          'was_repaired' => bool]
     */
    private function resolveInvitationByToken(string $token): array
    {
        $invitation = UserInvitation::with(['user', 'invitedBy'])
            ->where('token', $token)
            ->first();

        if (!$invitation) {
            Log::warning('API: invalid invitation token', ['token' => $token]);
            return [
                'success'    => false,
                'invitation' => null,
                'message'    => 'Invalid invitation link. Please check the link or contact the administrator.',
                'status'     => 404,
                'error_code' => 'INVALID_TOKEN',
            ];
        }

        // Raw DB values (bypass casts/accessors).
        $rawExpiresAt = $invitation->getRawOriginal('expires_at');
        $rawStatus    = $invitation->getRawOriginal('status');
        $rawCreatedAt = $invitation->getRawOriginal('created_at');

        $isActuallyExpired = $rawExpiresAt && Carbon::parse($rawExpiresAt)->isPast();

        // Detect corruption: expiry less than 24h after creation.
        $isCorrupted = false;
        $hoursDifference = null;
        if ($rawCreatedAt && $rawExpiresAt) {
            $hoursDifference = Carbon::parse($rawCreatedAt)->diffInHours(Carbon::parse($rawExpiresAt));
            $isCorrupted = $hoursDifference < 24;
        }

        Log::info('API: invitation resolved', [
            'invitation_id'       => $invitation->id,
            'user_id'             => $invitation->user_id,
            'raw_status'          => $rawStatus,
            'raw_expires_at'      => $rawExpiresAt,
            'raw_created_at'      => $rawCreatedAt,
            'is_actually_expired' => $isActuallyExpired,
            'is_corrupted'        => $isCorrupted,
            'hours_difference'    => $hoursDifference,
        ]);

        $wasRepaired = false;

        // Auto-repair corrupted invitations.
        if ($isCorrupted && in_array($rawStatus, [UserInvitation::STATUS_SENT, UserInvitation::STATUS_PENDING], true)) {
            Log::warning('API: auto-repairing corrupted invitation', [
                'invitation_id' => $invitation->id,
            ]);

            if (method_exists($invitation, 'fixExpirationDate') && $invitation->fixExpirationDate()) {
                $invitation->refresh();
                $isActuallyExpired = false;
                $wasRepaired = true;

                Log::info('API: invitation auto-repaired', [
                    'invitation_id'  => $invitation->id,
                    'new_expires_at' => $invitation->getRawOriginal('expires_at'),
                ]);
            }
        }

        // Safe status (falls back to raw status if model lacks getSafeStatus()).
        $safeStatus = method_exists($invitation, 'getSafeStatus')
            ? $invitation->getSafeStatus()
            : $invitation->status;

        // Expiration handling.
        if ($safeStatus === 'expired' || $safeStatus === 'should_be_expired') {
            if ($this->invitationAutoExpiry
                && $safeStatus === 'should_be_expired'
                && !$isCorrupted
                && method_exists($invitation, 'markAsExpired')) {
                $invitation->markAsExpired();
                $invitation->refresh();
            }

            return [
                'success'      => false,
                'invitation'   => $invitation,
                'message'      => 'This invitation has expired. Please request a new one.',
                'status'       => 410,
                'error_code'   => 'INVITATION_EXPIRED',
                'was_repaired' => $wasRepaired,
            ];
        }

        if (method_exists($invitation, 'isAccepted') ? $invitation->isAccepted() : (bool) $invitation->accepted_at) {
            // Let caller decide idempotency — do NOT error here.
            // But for verify/details we still return an explicit error code.
            return [
                'success'      => false,
                'invitation'   => $invitation,
                'message'      => 'This invitation has already been accepted.',
                'status'       => 409,
                'error_code'   => 'ALREADY_ACCEPTED',
                'was_repaired' => $wasRepaired,
            ];
        }

        // Cancelled / revoked.
        if (in_array($rawStatus, [
            UserInvitation::STATUS_CANCELLED,
            UserInvitation::STATUS_REVOKED,
        ], true)) {
            return [
                'success'      => false,
                'invitation'   => $invitation,
                'message'      => 'This invitation has been cancelled. Please contact the administrator.',
                'status'       => 422,
                'error_code'   => 'INVITATION_CANCELLED',
                'was_repaired' => $wasRepaired,
            ];
        }

        // Failed delivery.
        if ($rawStatus === UserInvitation::STATUS_FAILED) {
            return [
                'success'      => false,
                'invitation'   => $invitation,
                'message'      => 'This invitation failed to deliver. Please contact the administrator.',
                'status'       => 422,
                'error_code'   => 'INVITATION_FAILED',
                'was_repaired' => $wasRepaired,
            ];
        }

        // Active check.
        $isActive = method_exists($invitation, 'isActive') ? $invitation->isActive() : true;
        if (!$isActive) {
            return [
                'success'      => false,
                'invitation'   => $invitation,
                'message'      => 'This invitation is no longer valid.',
                'status'       => 422,
                'error_code'   => 'INVITATION_INVALID',
                'was_repaired' => $wasRepaired,
            ];
        }

        return [
            'success'      => true,
            'invitation'   => $invitation,
            'message'      => 'OK',
            'status'       => 200,
            'error_code'   => null,
            'was_repaired' => $wasRepaired,
        ];
    }

    /**
     * ✅ Available verification channels for a user.
     */
    private function getAvailableVerificationChannels(User $user): array
    {
        $channels = [];
        if ($user->phone) {
            $channels[] = 'sms';
            $channels[] = 'whatsapp';
        }
        if ($user->email) {
            $channels[] = 'email';
        }
        return $channels;
    }

    /**
     * ✅ Type-aware redirect route (mirrors web controller).
     */
    private function getRedirectRouteForUser(User $user): string
    {
        $userType = $user->getRawOriginal('type');

        $routeMap = [
            User::TYPE_SUPER_ADMIN          => 'super-admin.dashboard',
            User::TYPE_ADMIN                => 'admin.dashboard',
            User::TYPE_LANDLORD             => 'landlord.dashboard',
            User::TYPE_TENANT               => 'tenant.dashboard',
            User::TYPE_FIELD_AGENT          => 'field-agent.dashboard',
            User::TYPE_DEVELOPER            => 'developer.dashboard',
            User::TYPE_SECURITY_PERSONNEL   => 'security.dashboard',
            User::TYPE_SANITATION_PERSONNEL => 'sanitation.dashboard',
            User::TYPE_CONTRACTOR           => 'contractor.dashboard',
            User::TYPE_FORMER_LANDLORD      => 'dashboard',
        ];

        $route = $routeMap[$userType] ?? 'dashboard';

        if (!Route::has($route)) {
            Log::warning('API: redirect route not found, falling back', [
                'user_id' => $user->id,
                'route'   => $route,
            ]);
            $route = 'dashboard';
        }

        return $route;
    }

    /**
     * ✅ Personalized welcome message.
     */
    private function getWelcomeMessage(User $user): string
    {
        $greeting = match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 17 => 'Good afternoon',
            default          => 'Good evening',
        };

        $typeMessage = match ((int) $user->getRawOriginal('type')) {
            User::TYPE_SUPER_ADMIN          => 'Your administrative account has been activated successfully.',
            User::TYPE_ADMIN                => 'Your administrator account is now active and ready to use.',
            User::TYPE_LANDLORD             => 'Your landlord account is now active. Start managing your properties!',
            User::TYPE_TENANT               => 'Your tenant account is now active. Welcome to your new home!',
            User::TYPE_FIELD_AGENT          => 'Your field agent account is now active. Check your assignments to get started!',
            User::TYPE_SECURITY_PERSONNEL   => 'Your security checkpoint account is now active.',
            User::TYPE_SANITATION_PERSONNEL => 'Your sanitation personnel account is now active. You can now manage waste collection and sanitation tasks!',
            User::TYPE_CONTRACTOR           => 'Your contractor account is now active. You can now view and manage your construction contracts!',
            default                         => 'Your account has been activated successfully.',
        };

        return "{$greeting}, {$user->name}! {$typeMessage}";
    }

    /**
     * ✅ Light user formatter for API responses.
     */
    private function formatUserForApi(User $user): array
    {
        return [
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'phone'          => $user->phone,
            'username'       => $user->username,
            'type'           => $user->type,
            'type_name'      => $user->type_name,
            'status'         => $user->status,
            'email_verified' => !is_null($user->email_verified_at),
            'phone_verified' => !is_null($user->phone_verified_at),
            'initials'       => $user->initials ?? null,
            'avatar_url'     => $user->avatar_url ?? null,
            'created_at'     => $user->created_at?->toISOString(),
        ];
    }

    /**
     * ✅ Pagination helper.
     */
    private function getPerPage($requestedPerPage): int
    {
        $perPage = (int) $requestedPerPage;
        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }
        return min($perPage, self::MAX_PER_PAGE);
    }
}