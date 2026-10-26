<?php
// app/Http/Controllers/API/AuthController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DeveloperSetting;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\Authentication\PhoneNormalizationService;
use App\Services\Authentication\RoleRedirectionService;
use App\Services\Authentication\ArchivedAccountService;
use App\Services\Authentication\LoginAttemptService;
use App\Services\Authentication\SocialLoginService;
use App\Services\Authentication\PasswordResetService;
use App\Services\Authentication\TwoFactorAuthService;
use App\Services\Authentication\LoginActivityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    // ==================== CONFIG ====================
    protected int $maxAttempts = 5;
    protected int $decayMinutes = 1;
    protected int $tokenExpiryMonths = 6;
    protected int $maxDevicesPerUser = 5;

    /** Short-lived token TTL for the mobile 2FA handoff. */
    protected int $twoFactorTokenTtlSeconds = 300;

    protected const LOGIN_ALLOWED_TYPES = [
        User::TYPE_SUPER_ADMIN,
        User::TYPE_ADMIN,
        User::TYPE_LANDLORD,
        User::TYPE_TENANT,
        User::TYPE_FIELD_AGENT,
        User::TYPE_DEVELOPER,
        User::TYPE_SECURITY_PERSONNEL,
        User::TYPE_CONTRACTOR,
        User::TYPE_SANITATION_PERSONNEL,
    ];

    protected const SOCIAL_PROVIDERS = ['google', 'microsoft', 'facebook', 'apple', 'github'];

    // ==================== SERVICES ====================
    protected PhoneNormalizationService $phoneService;
    protected RoleRedirectionService $redirectionService;
    protected ArchivedAccountService $archivedAccountService;
    protected LoginAttemptService $loginAttemptService;
    protected SocialLoginService $socialLoginService;
    protected PasswordResetService $passwordResetService;
    protected TwoFactorAuthService $twoFactorAuthService;
    protected LoginActivityService $activityService;

    public function __construct(
        PhoneNormalizationService $phoneService,
        RoleRedirectionService $redirectionService,
        ArchivedAccountService $archivedAccountService,
        LoginAttemptService $loginAttemptService,
        SocialLoginService $socialLoginService,
        PasswordResetService $passwordResetService,
        TwoFactorAuthService $twoFactorAuthService,
        LoginActivityService $activityService
    ) {
        $this->phoneService = $phoneService;
        $this->redirectionService = $redirectionService;
        $this->archivedAccountService = $archivedAccountService;
        $this->loginAttemptService = $loginAttemptService;
        $this->socialLoginService = $socialLoginService;
        $this->passwordResetService = $passwordResetService;
        $this->twoFactorAuthService = $twoFactorAuthService;
        $this->activityService = $activityService;
    }

    // ==================== LOGIN ====================

    public function login(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'login'           => 'required|string',
                'password'        => 'required|string',
                'device_name'     => 'nullable|string|max:255',
                'device_token'    => 'nullable|string',
                'two_factor_code' => 'nullable|string|size:6',
            ]);

            // ---- Rate limiting ----
            if ($this->hasTooManyLoginAttempts($request)) {
                $seconds = RateLimiter::availableIn($this->throttleKey($request));
                return $this->errorResponse(
                    'too_many_attempts',
                    trans('auth.throttle', [
                        'seconds' => $seconds,
                        'minutes' => ceil($seconds / 60),
                    ]),
                    429,
                    ['retry_after' => $seconds]
                );
            }

            // ---- Find user (archival-aware) ----
            $credentials = $this->getCredentials($request);
            $user = $this->findUserByCredentials($credentials);

            $this->loginAttemptService->logAttempt($request, $user);

            if (!$user) {
                $this->incrementLoginAttempts($request);
                return $this->errorResponse(
                    'invalid_credentials',
                    'The provided credentials are incorrect.',
                    401
                );
            }

            // ---- Archived (trashed OR status=archived) ----
            if (method_exists($user, 'isArchived') && $user->isArchived()) {
                return $this->archivedAccountService->handleMobileArchivedLogin($user);
            }

            if (method_exists($user, 'trashed') && $user->trashed()) {
                return $this->archivedAccountService->handleMobileArchivedLogin($user);
            }

            // ---- Permission ----
            if (!$this->hasPermissionToLogin($user)) {
                return $this->errorResponse(
                    'forbidden',
                    'Your account type does not have login privileges.',
                    403
                );
            }

            // ---- Password check ----
            if (!Hash::check($credentials['password'], $user->password)) {
                $this->incrementLoginAttempts($request);
                Log::warning('API login failed - invalid password', [
                    'user_id' => $user->id,
                    'email'   => $user->email,
                    'ip'      => $request->ip(),
                ]);
                return $this->errorResponse(
                    'invalid_credentials',
                    'The provided credentials are incorrect.',
                    401
                );
            }

            // ---- 2FA ----
            if ($this->twoFactorAuthService->isEnabled($user)) {
                if ($request->filled('two_factor_code')) {
                    if ($this->hasTooMany2faAttempts($user)) {
                        $seconds = RateLimiter::availableIn($this->twoFactorThrottleKey($user));
                        return $this->errorResponse(
                            'too_many_2fa_attempts',
                            "Too many 2FA attempts. Try again in {$seconds} seconds.",
                            429,
                            ['retry_after' => $seconds]
                        );
                    }

                    if (!$this->twoFactorAuthService->verify($user, $request->two_factor_code)) {
                        $this->recordFailed2fa($user);
                        $this->activityService->recordTwoFactorFailure($user, $request);
                        return $this->errorResponse(
                            'invalid_2fa_code',
                            'Invalid two-factor authentication code.',
                            401
                        );
                    }

                    $this->clear2faAttempts($user);
                    $this->activityService->recordTwoFactorSuccess($user, $request);
                } else {
                    $twoFactorToken = $this->issueTwoFactorToken($user);

                    return response()->json([
                        'success'          => false,
                        'error'            => 'two_factor_required',
                        'message'          => 'Two-factor authentication code is required.',
                        'requires_2fa'     => true,
                        'two_factor_token' => $twoFactorToken,
                        'expires_in'       => $this->twoFactorTokenTtlSeconds,
                    ], 401);
                }
            }

            // ---- Success ----
            $this->clearLoginAttempts($request);

            $user->updateLastActivity();
            $this->activityService->recordLogin($user, $request, 'mobile');

            $user->increment('login_count');
            $user->last_login_at = now();
            $user->last_login_ip = $request->ip();
            $user->save();

            $this->trackDevice($request, $user);

            $deviceName = $request->input('device_name', 'flutter_app');

            if ($request->filled('device_token')) {
                $this->storeDeviceToken($user, $deviceName, $request->input('device_token'));
            }

            // ✅ FIX: prune old tokens BEFORE creating the new one.
            //    Previously the cleanup ran *after* createToken() and
            //    sorted by `last_used_at DESC`, which put freshly-created
            //    tokens (last_used_at = NULL) at the bottom of the list
            //    and deleted them. The client got a token string whose
            //    database row was gone, so every subsequent request 401'd.
            $this->cleanupTokensForUser($user, $deviceName);

            $token = $user->createToken(
                $deviceName,
                ['*'],
                now()->addMonths($this->tokenExpiryMonths)
            )->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data'    => [
                    'user'                  => $this->userPayload($user),
                    'token'                 => $token,
                    'token_type'            => 'Bearer',
                    'expires_in'            => now()->addMonths($this->tokenExpiryMonths)->timestamp,
                    'requires_verification' => method_exists($user, 'hasVerifiedEmail')
                                                && !$user->hasVerifiedEmail(),
                    'dashboard_url'         => $this->dashboardUrlFor($user),
                    'billing'               => $this->billingPayloadFor($user),
                ],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            Log::error('❌ API Login error: ' . $e->getMessage(), [
                'exception_class' => get_class($e),
                'file'            => $e->getFile(),
                'line'            => $e->getLine(),
                'trace'           => $e->getTraceAsString(),
                'request'         => [
                    'login'       => $request->input('login'),
                    'device_name' => $request->input('device_name'),
                    'ip'          => $request->ip(),
                    'ua'          => (string) $request->userAgent(),
                ],
            ]);
            return $this->errorResponse('login_failed', 'Login failed. Please try again.', 500);
        }
    }

    // ==================== ✅ TOKEN VALIDATION ====================

    public function checkAuth(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Token is valid.',
            'data'    => [
                'user'    => $this->userPayload($request->user()),
                'billing' => $this->billingPayloadFor($request->user()),
            ],
        ]);
    }

    // ==================== ✅ LOGIN METADATA ====================

    public function loginMetadata(Request $request): JsonResponse
    {
        try {
            $settings = \Illuminate\Support\Facades\Cache::remember(
                'system_settings_login_metadata',
                now()->addMinutes(30),
                fn () => \App\Models\SystemSetting::getSettings()
            );

            $system = [
                'name'          => $settings->system_name
                                    ?? config('app.name', 'Hilltop Estate Management'),
                'support_email' => $settings->contact_email
                                    ?? config('mail.from.address', 'support@hilltop.com'),
                'support_phone' => $settings->contact_phone
                                    ?? config('app.support_phone', '+233 123 456 789'),
            ];

            $notice = null;

            if ($request->hasSession()) {
                $archivedTenantMsg   = $request->session()->get('archived_tenant_account');
                $archivedLandlordMsg = $request->session()->get('archived_account')
                                        ?? $request->session()->get('archived_landlord_account');

                if (!empty($archivedTenantMsg)) {
                    $notice = [
                        'kind'    => 'archived_tenant',
                        'title'   => 'Tenant Account Archived',
                        'message' => $archivedTenantMsg,
                        'reason'  => 'Your tenant account has been archived because it remained unassigned to any property unit for 30+ days.',
                        'next'    => 'Please contact the administration to have your account restored or to be assigned to a property unit.',
                    ];
                } elseif (!empty($archivedLandlordMsg)) {
                    $notice = [
                        'kind'    => 'archived_landlord',
                        'title'   => 'Landlord Account Archived',
                        'message' => $archivedLandlordMsg,
                        'reason'  => 'Your account has been archived because you no longer own any properties.',
                        'next'    => 'Please contact the administration to have your account restored.',
                    ];
                }
            }

            $existingSession = null;

            return response()->json([
                'success' => true,
                'data'    => [
                    'system'           => $system,
                    'notice'           => $notice,
                    'existing_session' => $existingSession,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('loginMetadata failed: ' . $e->getMessage());

            return response()->json([
                'success' => true,
                'data'    => [
                    'system' => [
                        'name'          => config('app.name', 'Hilltop Estate Management'),
                        'support_email' => config('mail.from.address', 'support@hilltop.com'),
                        'support_phone' => config('app.support_phone', ''),
                    ],
                    'notice'           => null,
                    'existing_session' => null,
                ],
            ]);
        }
    }

    // ==================== LOGOUT ====================

    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            $deviceName = $request->input('device_name')
                ?? optional($user->currentAccessToken())->name;

            $this->activityService->recordLogout($user, $request, 'mobile');

            $token = $user->currentAccessToken();
            if ($token) {
                $token->delete();
            }

            if ($deviceName) {
                $this->removeDeviceToken($user, $deviceName);
            }

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
                'data'    => ['token_revoked' => true],
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ Logout error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('logout_failed', 'Logout failed. Please try again.', 500);
        }
    }

    public function logoutAllDevices(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            $currentTokenId = optional($user->currentAccessToken())->id;

            $query = $user->tokens();
            if ($currentTokenId) {
                $query->where('id', '!=', $currentTokenId);
            }
            $count = (clone $query)->count();
            $query->delete();

            if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            $this->activityService->recordLogout($user, $request, 'mobile_all_devices');

            Log::info('🔄 Logged out from all devices', [
                'user_id'        => $user->id,
                'tokens_deleted' => $count,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Logged out from all other devices',
                'data'    => ['tokens_deleted' => $count],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('logout_all_failed', 'Logout failed. Please try again.', 500);
        }
    }

    // ==================== CURRENT USER ====================

    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            return response()->json([
                'success' => true,
                'data'    => array_merge($this->userPayload($user), [
                    'billing' => $this->billingPayloadFor($user),
                ]),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('user_fetch_failed', 'Failed to fetch user.', 500);
        }
    }

    // ==================== TOKEN REFRESH ====================

    public function refreshToken(Request $request): JsonResponse
    {
        try {
            $request->validate(['device_name' => 'nullable|string|max:255']);

            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            // ✅ Delete the old token BEFORE creating the new one, so
            //    the cleanup logic and the create order are always
            //    consistent — and the fresh token can never be a
            //    cleanup target.
            $user->currentAccessToken()?->delete();

            $token = $user->createToken(
                $request->input('device_name', 'flutter_app'),
                ['*'],
                now()->addMonths($this->tokenExpiryMonths)
            )->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed',
                'data'    => [
                    'token'      => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => now()->addMonths($this->tokenExpiryMonths)->timestamp,
                    'user'       => $this->userPayload($user),
                    'billing'    => $this->billingPayloadFor($user),
                ],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            return $this->errorResponse('refresh_failed', 'Token refresh failed.', 500);
        }
    }

    // ==================== 2FA ====================

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'two_factor_token' => 'required|string',
                'code'             => 'required|string|size:6',
                'device_name'      => 'nullable|string|max:255',
                'device_token'     => 'nullable|string',
            ]);

            $user = $this->resolveTwoFactorToken($request->two_factor_token);

            if (!$user) {
                return $this->errorResponse(
                    'invalid_token',
                    'Verification session has expired. Please login again.',
                    401
                );
            }

            if ($this->hasTooMany2faAttempts($user)) {
                $seconds = RateLimiter::availableIn($this->twoFactorThrottleKey($user));
                return $this->errorResponse(
                    'too_many_2fa_attempts',
                    "Too many 2FA attempts. Try again in {$seconds} seconds.",
                    429,
                    ['retry_after' => $seconds]
                );
            }

            if (!$this->twoFactorAuthService->verify($user, $request->code)) {
                $this->recordFailed2fa($user);
                $this->activityService->recordTwoFactorFailure($user, $request);
                return $this->errorResponse(
                    'invalid_2fa_code',
                    'Invalid two-factor authentication code.',
                    401
                );
            }

            $this->clear2faAttempts($user);
            $this->activityService->recordTwoFactorSuccess($user, $request);

            $deviceName = $request->input('device_name', 'flutter_app');

            if ($request->filled('device_token')) {
                $this->storeDeviceToken($user, $deviceName, $request->input('device_token'));
            }

            // ✅ FIX: cleanup BEFORE createToken (same as login()).
            $this->cleanupTokensForUser($user, $deviceName);

            $token = $user->createToken(
                $deviceName,
                ['*'],
                now()->addMonths($this->tokenExpiryMonths)
            )->plainTextToken;

            $user->updateLastActivity();
            $this->activityService->recordLogin($user, $request, 'mobile_2fa');
            $this->trackDevice($request, $user);

            return response()->json([
                'success' => true,
                'message' => '2FA verified successfully.',
                'data'    => [
                    'user'       => $this->userPayload($user),
                    'token'      => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => now()->addMonths($this->tokenExpiryMonths)->timestamp,
                    'billing'    => $this->billingPayloadFor($user),
                ],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            Log::error('2FA verify failed: ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
            return $this->errorResponse('2fa_failed', '2FA verification failed.', 500);
        }
    }

    public function resendTwoFactorCode(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'two_factor_token' => 'required|string',
            ]);

            $cacheKey = 'mobile_2fa_token:' . hash('sha256', $request->two_factor_token);
            $payload  = Cache::get($cacheKey);

            if (!$payload || empty($payload['user_id'])) {
                return $this->errorResponse(
                    'invalid_token',
                    'Verification session has expired. Please login again.',
                    401
                );
            }

            $user = User::find($payload['user_id']);
            if (!$user) {
                return $this->errorResponse('user_not_found', 'User not found.', 404);
            }

            $key = 'mobile-2fa-resend:' . $user->id;
            if (RateLimiter::tooManyAttempts($key, 3)) {
                $seconds = RateLimiter::availableIn($key);
                return $this->errorResponse(
                    'too_many_attempts',
                    "Too many resend attempts. Try again in {$seconds} seconds.",
                    429,
                    ['retry_after' => $seconds]
                );
            }
            RateLimiter::hit($key, 300);

            $sent = $this->twoFactorAuthService->sendCode($user);

            if (!$sent) {
                return $this->errorResponse('send_failed', 'Failed to send verification code.', 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Verification code sent.',
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            return $this->errorResponse('resend_failed', 'Failed to resend code.', 500);
        }
    }

    public function enableTwoFactor(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            $request->validate([
                'password'          => 'required|string',
                'verification_code' => 'required|string|size:6',
            ]);

            if ($this->hasTooMany2faAttempts($user)) {
                $seconds = RateLimiter::availableIn($this->twoFactorThrottleKey($user));
                return $this->errorResponse(
                    'too_many_2fa_attempts',
                    "Too many attempts. Try again in {$seconds} seconds.",
                    429,
                    ['retry_after' => $seconds]
                );
            }

            if (!Hash::check($request->password, $user->password)) {
                return $this->errorResponse('invalid_password', 'Invalid password.', 400);
            }

            if (!$this->twoFactorAuthService->verify($user, $request->verification_code)) {
                $this->recordFailed2fa($user);
                return $this->errorResponse(
                    'invalid_2fa_code',
                    'Invalid verification code.',
                    400
                );
            }

            $this->clear2faAttempts($user);
            $this->twoFactorAuthService->enable($user);
            $backupCodes = $this->twoFactorAuthService->generateBackupCodes($user);

            return response()->json([
                'success' => true,
                'message' => 'Two-factor authentication enabled.',
                'data'    => [
                    'backup_codes' => $backupCodes,
                ],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            Log::error('2FA enable failed: ' . $e->getMessage());
            return $this->errorResponse('2fa_enable_failed', 'Failed to enable 2FA.', 500);
        }
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            $request->validate(['password' => 'required|string']);

            if ($this->hasTooManyPasswordAttempts($user)) {
                $seconds = RateLimiter::availableIn($this->passwordThrottleKey($user));
                return $this->errorResponse(
                    'too_many_attempts',
                    "Too many attempts. Try again in {$seconds} seconds.",
                    429,
                    ['retry_after' => $seconds]
                );
            }

            if (!Hash::check($request->password, $user->password)) {
                $this->recordFailedPassword($user);
                return $this->errorResponse('invalid_password', 'Invalid password.', 400);
            }

            $this->clearPasswordAttempts($user);
            $this->twoFactorAuthService->disable($user);

            return response()->json([
                'success' => true,
                'message' => 'Two-factor authentication disabled.',
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            return $this->errorResponse('2fa_disable_failed', 'Failed to disable 2FA.', 500);
        }
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            $request->validate(['password' => 'required|string']);

            if ($this->hasTooManyPasswordAttempts($user)) {
                $seconds = RateLimiter::availableIn($this->passwordThrottleKey($user));
                return $this->errorResponse(
                    'too_many_attempts',
                    "Too many attempts. Try again in {$seconds} seconds.",
                    429,
                    ['retry_after' => $seconds]
                );
            }

            if (!Hash::check($request->password, $user->password)) {
                $this->recordFailedPassword($user);
                return $this->errorResponse('invalid_password', 'Invalid password.', 400);
            }

            $this->clearPasswordAttempts($user);
            $codes = $this->twoFactorAuthService->regenerateBackupCodes($user);

            return response()->json([
                'success' => true,
                'message' => 'Recovery codes regenerated successfully.',
                'data'    => ['backup_codes' => $codes],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            return $this->errorResponse('recovery_regen_failed', 'Failed to regenerate recovery codes.', 500);
        }
    }

    // ==================== PASSWORD ====================

    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            $request->validate([
                'current_password' => 'required|string',
                'new_password'     => 'required|min:8|confirmed',
            ]);

            if ($this->hasTooManyPasswordAttempts($user)) {
                $seconds = RateLimiter::availableIn($this->passwordThrottleKey($user));
                return $this->errorResponse(
                    'too_many_attempts',
                    "Too many attempts. Try again in {$seconds} seconds.",
                    429,
                    ['retry_after' => $seconds]
                );
            }

            if (!Hash::check($request->current_password, $user->password)) {
                $this->recordFailedPassword($user);
                return $this->errorResponse(
                    'current_password_incorrect',
                    'Current password is incorrect.',
                    400
                );
            }

            if (Hash::check($request->new_password, $user->password)) {
                return $this->errorResponse(
                    'password_reused',
                    'New password must be different from the current password.',
                    400
                );
            }

            $this->clearPasswordAttempts($user);

            $user->update([
                'password'            => Hash::make($request->new_password),
                'temp_password'       => false,
                'password_changed_at' => now(),
            ]);

            $logoutOthers = $request->boolean('logout_other_devices', true);
            if ($logoutOthers) {
                $currentId = optional($user->currentAccessToken())->id;
                $user->tokens()
                    ->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
                    ->delete();
            }

            $this->activityService->recordPasswordChange($user, $request);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully.',
                'data'    => ['other_devices_logged_out' => $logoutOthers],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            Log::error('Change password failed: ' . $e->getMessage());
            return $this->errorResponse('change_password_failed', 'Failed to change password.', 500);
        }
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'login' => 'required_without:email|string',
                'email' => 'required_without:login|email',
            ]);

            $login = $request->input('login') ?? $request->input('email');

            $throttleKey = 'api-password-reset:' . $request->ip() . ':' . sha1((string) $login);
            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                $seconds = RateLimiter::availableIn($throttleKey);
                return $this->errorResponse(
                    'too_many_attempts',
                    "Too many password reset attempts. Try again in {$seconds} seconds.",
                    429,
                    ['retry_after' => $seconds]
                );
            }

            $this->passwordResetService->sendResetLink($login);
            RateLimiter::hit($throttleKey, 3600);

            return response()->json([
                'success' => true,
                'message' => 'If an account exists, a password reset link has been sent.',
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            Log::error('Forgot password failed: ' . $e->getMessage());
            return $this->errorResponse('forgot_password_failed', 'Failed to process request.', 500);
        }
    }

    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'token'    => 'required',
                'email'    => 'required|email',
                'password' => 'required|min:8|confirmed',
            ]);

            $result = $this->passwordResetService->resetPassword(
                $request->email,
                $request->token,
                $request->password
            );

            if (!($result['success'] ?? false)) {
                return $this->errorResponse(
                    'reset_failed',
                    $result['message'] ?? 'Password reset failed.',
                    400
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Password reset successful. You can now login.',
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            Log::error('Reset password failed: ' . $e->getMessage());
            return $this->errorResponse('reset_password_failed', 'Password reset failed.', 500);
        }
    }

    // ==================== DEVICES ====================

    public function getUserDevices(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            $currentTokenId = optional($user->currentAccessToken())->id;

            $tokens = $user->tokens()
                ->orderByDesc('last_used_at')
                ->get()
                ->map(fn ($t) => [
                    'id'           => $t->id,
                    'name'         => $t->name,
                    'abilities'    => $t->abilities,
                    'last_used_at' => $t->last_used_at,
                    'created_at'   => $t->created_at,
                    'expires_at'   => $t->expires_at,
                    'is_current'   => $t->id === $currentTokenId,
                ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'devices' => $tokens,
                    'total'   => $tokens->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('devices_fetch_failed', 'Failed to fetch devices.', 500);
        }
    }

    public function revokeDevice(Request $request, $tokenId): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            $token = $user->tokens()->find($tokenId);
            if (!$token) {
                return $this->errorResponse('device_not_found', 'Device not found.', 404);
            }

            $token->delete();

            return response()->json([
                'success' => true,
                'message' => 'Device revoked successfully.',
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('revoke_device_failed', 'Failed to revoke device.', 500);
        }
    }

    public function registerPushToken(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'device_name' => 'required|string',
                'push_token'  => 'required|string',
                'platform'    => 'required|in:ios,android',
            ]);

            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            $this->storeDeviceToken($user, $request->device_name, $request->push_token);

            if (Schema::hasTable('device_tokens')
                && Schema::hasColumn('device_tokens', 'platform')
                && Schema::hasColumn('device_tokens', 'updated_at')) {
                DB::table('device_tokens')
                    ->where('user_id', $user->id)
                    ->where('device_name', $request->device_name)
                    ->update([
                        'platform'   => $request->platform,
                        'updated_at' => now(),
                    ]);
            } elseif (Schema::hasTable('device_tokens')
                && Schema::hasColumn('device_tokens', 'platform')) {
                DB::table('device_tokens')
                    ->where('user_id', $user->id)
                    ->where('device_name', $request->device_name)
                    ->update(['platform' => $request->platform]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Push token registered successfully.',
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            return $this->errorResponse('push_token_failed', 'Failed to register push token.', 500);
        }
    }

    // ==================== ACTIVITY / HISTORY ====================

    public function getLoginHistory(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            $activities = [];

            if (Schema::hasTable('user_activities')) {
                $activities = DB::table('user_activities')
                    ->where('user_id', $user->id)
                    ->when(
                        Schema::hasColumn('user_activities', 'action'),
                        fn ($q) => $q->where('action', 'like', 'login%')
                    )
                    ->orderByDesc('created_at')
                    ->limit(50)
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'activities' => $activities,
                    'total'      => count($activities),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('history_failed', 'Failed to fetch login history.', 500);
        }
    }

    public function checkPasswordExpiry(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            $expiryDays = config('auth.password_expiry_days', 90);

            if (!$user->password_changed_at) {
                return response()->json([
                    'success' => true,
                    'data'    => [
                        'password_age_days' => null,
                        'expiry_days'       => $expiryDays,
                        'days_remaining'    => null,
                        'is_expiring'       => false,
                        'should_change'     => false,
                    ],
                ]);
            }

            $age = (int) now()->diffInDays($user->password_changed_at, false);
            $remaining = $expiryDays - $age;

            return response()->json([
                'success' => true,
                'data'    => [
                    'password_age_days' => $age,
                    'expiry_days'       => $expiryDays,
                    'days_remaining'    => $remaining,
                    'is_expiring'       => $remaining <= 7,
                    'should_change'     => $remaining <= 0,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('expiry_check_failed', 'Failed to check password expiry.', 500);
        }
    }

    // ==================== DASHBOARD REDIRECT ====================

    public function getDashboardRedirect(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'dashboard_url' => $this->dashboardUrlFor($user),
                    'user_type'     => $user->type,
                    'billing'       => $this->billingPayloadFor($user),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('dashboard_redirect_failed', 'Failed to resolve dashboard.', 500);
        }
    }

    // ==================== ADMIN: FORCE LOGOUT ====================

    public function forceLogoutUser(Request $request, $userId): JsonResponse
    {
        try {
            $actor = $request->user();
            if (!$actor) {
                return $this->unauthenticated();
            }

            if (!in_array($actor->getRawOriginal('type'), [
                User::TYPE_SUPER_ADMIN,
                User::TYPE_ADMIN,
            ], true)) {
                return $this->errorResponse('forbidden', 'Insufficient privileges.', 403);
            }

            $target = User::find($userId);
            if (!$target) {
                return $this->errorResponse('user_not_found', 'User not found.', 404);
            }

            if ($target->getRawOriginal('type') === User::TYPE_SUPER_ADMIN
                && $actor->getRawOriginal('type') !== User::TYPE_SUPER_ADMIN) {
                return $this->errorResponse('forbidden', 'Insufficient privileges.', 403);
            }

            $count = $target->tokens()->count();
            $target->tokens()->delete();

            if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $target->id)->delete();
            }

            $this->activityService->recordLogout($target, $request, 'forced_by_admin');

            return response()->json([
                'success' => true,
                'message' => 'User forced to log out from all devices.',
                'data'    => ['tokens_deleted' => $count],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('force_logout_failed', 'Failed to force logout.', 500);
        }
    }

    // ==================== SOCIAL LOGIN ====================

    public function socialLogin(Request $request, string $provider): JsonResponse
    {
        try {
            if (!in_array($provider, self::SOCIAL_PROVIDERS, true)) {
                return $this->errorResponse(
                    'unsupported_provider',
                    'Unsupported social login provider.',
                    400
                );
            }

            $request->validate([
                'access_token' => 'required|string',
                'device_name'  => 'nullable|string|max:255',
                'device_token' => 'nullable|string',
            ]);

            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->userFromToken($request->access_token);

            $result = $this->socialLoginService->handleLogin($provider, $socialUser);

            if (!($result['success'] ?? false)) {
                return $this->errorResponse(
                    'social_login_failed',
                    $result['message'] ?? 'Social login failed.',
                    401,
                    ['email' => $result['email'] ?? null]
                );
            }

            $user = $result['user'];

            if ($user->isArchived()) {
                return $this->archivedAccountService->handleMobileArchivedLogin($user);
            }

            if ($user->trashed()) {
                return $this->errorResponse(
                    'account_deactivated',
                    'This account has been deactivated.',
                    403
                );
            }

            if (!$this->hasPermissionToLogin($user)) {
                return $this->errorResponse(
                    'forbidden',
                    'Your account type does not have login privileges.',
                    403
                );
            }

            $deviceName = $request->input('device_name', 'flutter_app');

            if ($request->filled('device_token')) {
                $this->storeDeviceToken($user, $deviceName, $request->input('device_token'));
            }

            // ✅ FIX: cleanup BEFORE createToken (same as login()).
            $this->cleanupTokensForUser($user, $deviceName);

            $token = $user->createToken(
                $deviceName,
                ['*'],
                now()->addMonths($this->tokenExpiryMonths)
            )->plainTextToken;

            $user->updateLastActivity();
            $this->activityService->recordLogin($user, $request, "social_{$provider}");
            $this->trackDevice($request, $user);

            return response()->json([
                'success' => true,
                'message' => "Logged in with {$provider}.",
                'data'    => [
                    'user'       => $this->userPayload($user),
                    'token'      => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => now()->addMonths($this->tokenExpiryMonths)->timestamp,
                    'billing'    => $this->billingPayloadFor($user),
                ],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            Log::error("❌ Social login ({$provider}) failed: " . $e->getMessage());
            return $this->errorResponse('social_login_failed', 'Social login failed.', 500);
        }
    }

    public function linkSocialAccount(Request $request, string $provider): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            if (!in_array($provider, self::SOCIAL_PROVIDERS, true)) {
                return $this->errorResponse(
                    'unsupported_provider',
                    'Unsupported social login provider.',
                    400
                );
            }

            $request->validate(['access_token' => 'required|string']);

            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->userFromToken($request->access_token);

            $this->socialLoginService->linkAccount($user, $provider, $socialUser);

            return response()->json([
                'success' => true,
                'message' => "{$provider} account linked successfully.",
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            Log::error("Link social account ({$provider}) failed: " . $e->getMessage());
            return $this->errorResponse('link_failed', 'Failed to link social account.', 500);
        }
    }

    public function unlinkSocialAccount(Request $request, string $provider): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            $hasPassword = !empty($user->password) && !$user->temp_password;
            if (!$hasPassword) {
                return $this->errorResponse(
                    'cannot_unlink_last_method',
                    'You must set a password before unlinking your last social account.',
                    400
                );
            }

            if ($this->socialLoginService->unlinkAccount($user, $provider)) {
                return response()->json([
                    'success' => true,
                    'message' => "{$provider} account unlinked successfully.",
                ]);
            }

            return $this->errorResponse('unlink_failed', 'Failed to unlink social account.', 400);
        } catch (\Throwable $e) {
            Log::error("Unlink social account ({$provider}) failed: " . $e->getMessage());
            return $this->errorResponse('unlink_failed', 'Failed to unlink social account.', 500);
        }
    }

    // ==================== SESSIONS ====================

    public function getActiveSessions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            $sessions = [];

            if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
                $sessions = DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->where('last_activity', '>', now()->subMinutes(config('session.lifetime')))
                    ->orderByDesc('last_activity')
                    ->get()
                    ->map(fn ($s) => [
                        'id'            => $s->id,
                        'ip_address'    => $s->ip_address,
                        'user_agent'    => $s->user_agent,
                        'last_activity' => Carbon::createFromTimestamp($s->last_activity),
                    ]);
            }

            return response()->json([
                'success' => true,
                'data'    => ['sessions' => $sessions, 'total' => count($sessions)],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('sessions_failed', 'Failed to fetch sessions.', 500);
        }
    }

    // ==================== IMPERSONATION ====================

    public function startImpersonation(Request $request, $userId): JsonResponse
    {
        try {
            $actor = $request->user();
            if (!$actor) {
                return $this->unauthenticated();
            }

            if (!in_array($actor->getRawOriginal('type'), [
                User::TYPE_SUPER_ADMIN,
                User::TYPE_DEVELOPER,
            ], true)) {
                return $this->errorResponse(
                    'forbidden',
                    'Only super-admins and developers can impersonate users.',
                    403
                );
            }

            $target = User::find($userId);
            if (!$target) {
                return $this->errorResponse('user_not_found', 'User not found.', 404);
            }

            if ((int) $target->id === (int) $actor->id) {
                return $this->errorResponse(
                    'invalid_target',
                    'You cannot impersonate yourself.',
                    400
                );
            }

            if ($target->getRawOriginal('type') === User::TYPE_DEVELOPER
                && $actor->getRawOriginal('type') !== User::TYPE_DEVELOPER) {
                return $this->errorResponse(
                    'forbidden',
                    'Only developers can impersonate other developers.',
                    403
                );
            }

            if ($target->getRawOriginal('type') === User::TYPE_SUPER_ADMIN
                && $actor->getRawOriginal('type') !== User::TYPE_DEVELOPER
                && (int) $target->id !== (int) $actor->id) {
                return $this->errorResponse(
                    'forbidden',
                    'Super admins cannot impersonate other super admins.',
                    403
                );
            }

            $this->activityService->recordImpersonationStart($actor, $target, $request);

            $token = $target->createToken(
                'impersonation_by_' . $actor->id,
                ['*'],
                now()->addHour()
            )->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => "Impersonating {$target->name}.",
                'data'    => [
                    'user'         => $this->userPayload($target),
                    'token'        => $token,
                    'token_type'   => 'Bearer',
                    'expires_in'   => now()->addHour()->timestamp,
                    'impersonator' => [
                        'id'   => $actor->id,
                        'name' => $actor->name,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Start impersonation failed: ' . $e->getMessage());
            return $this->errorResponse('impersonate_failed', 'Failed to start impersonation.', 500);
        }
    }

    public function stopImpersonation(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $token = $user?->currentAccessToken();

            if (!$token || !Str::startsWith((string) $token->name, 'impersonation_by_')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Not currently impersonating.',
                ]);
            }

            $adminId = (int) Str::after($token->name, 'impersonation_by_');
            $admin = User::find($adminId) ?? $user;

            $this->activityService->recordImpersonationStop($admin, $user, $request);
            $token->delete();

            return response()->json([
                'success' => true,
                'message' => 'Impersonation stopped.',
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('stop_impersonation_failed', 'Failed to stop impersonation.', 500);
        }
    }

    // ==================== EMAIL / PHONE CHECK ====================

    public function checkEmailExists(Request $request): JsonResponse
    {
        try {
            $request->validate(['email' => 'required|email']);

            $key = 'api-check-email:' . $request->ip();
            if (RateLimiter::tooManyAttempts($key, 10)) {
                return $this->errorResponse(
                    'too_many_attempts',
                    'Too many requests. Try again later.',
                    429
                );
            }
            RateLimiter::hit($key, 60);

            return response()->json([
                'success' => true,
                'message' => 'If an account exists with that email, you will receive a recovery email.',
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            return $this->errorResponse('check_email_failed', 'Failed to check email.', 500);
        }
    }

    public function checkPhoneExists(Request $request): JsonResponse
    {
        try {
            $request->validate(['phone' => 'required|string']);

            $key = 'api-check-phone:' . $request->ip();
            if (RateLimiter::tooManyAttempts($key, 10)) {
                return $this->errorResponse(
                    'too_many_attempts',
                    'Too many requests. Try again later.',
                    429
                );
            }
            RateLimiter::hit($key, 60);

            return response()->json([
                'success' => true,
                'message' => 'If an account exists with that phone number, you will receive a recovery SMS.',
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            return $this->errorResponse('check_phone_failed', 'Failed to check phone.', 500);
        }
    }

    // ==================== VERIFICATION ====================

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        try {
            $request->validate(['email' => 'required|email']);

            $key = 'resend-verify:' . sha1(strtolower($request->email)) . ':' . $request->ip();
            if (RateLimiter::tooManyAttempts($key, 3)) {
                $seconds = RateLimiter::availableIn($key);
                return $this->errorResponse(
                    'too_many_attempts',
                    "Too many requests. Try again in {$seconds} seconds.",
                    429,
                    ['retry_after' => $seconds]
                );
            }
            RateLimiter::hit($key, 300);

            $user = User::whereRaw('LOWER(email) = ?', [strtolower($request->email)])->first();

            if ($user && !$user->hasVerifiedEmail()) {
                $user->sendEmailVerificationNotification();
            }

            return response()->json([
                'success' => true,
                'message' => 'If the email exists and is unverified, a verification link has been sent.',
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            Log::error('Resend verification failed: ' . $e->getMessage());
            return $this->errorResponse('resend_verification_failed', 'Failed to resend verification.', 500);
        }
    }

    // ==================== API TOKENS ====================

    public function listApiTokens(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            $tokens = $user->tokens()
                ->orderByDesc('last_used_at')
                ->get()
                ->map(fn ($t) => [
                    'id'           => $t->id,
                    'name'         => $t->name,
                    'abilities'    => $t->abilities,
                    'last_used_at' => $t->last_used_at,
                    'created_at'   => $t->created_at,
                    'expires_at'   => $t->expires_at,
                ]);

            return response()->json([
                'success' => true,
                'data'    => ['tokens' => $tokens],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('list_tokens_failed', 'Failed to list tokens.', 500);
        }
    }

    public function createApiToken(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name'            => 'required|string|max:255',
                'abilities'       => 'nullable|array',
                'expires_in_days' => 'nullable|integer|min:1|max:365',
            ]);

            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            $expiresAt = $request->expires_in_days
                ? now()->addDays((int) $request->expires_in_days)
                : now()->addMonths($this->tokenExpiryMonths);

            $abilities = $request->abilities ?? ['*'];

            $token = $user->createToken($request->name, $abilities, $expiresAt);

            return response()->json([
                'success' => true,
                'message' => 'API token created.',
                'data'    => [
                    'token'      => $token->plainTextToken,
                    'token_id'   => $token->accessToken->id,
                    'expires_at' => $expiresAt,
                ],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            return $this->errorResponse('create_token_failed', 'Failed to create token.', 500);
        }
    }

    public function revokeApiToken(Request $request, $tokenId): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            $token = $user->tokens()->find($tokenId);
            if (!$token) {
                return $this->errorResponse('token_not_found', 'Token not found.', 404);
            }

            $token->delete();

            return response()->json([
                'success' => true,
                'message' => 'Token revoked successfully.',
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('revoke_token_failed', 'Failed to revoke token.', 500);
        }
    }

    public function updateTokenPermissions(Request $request, $tokenId): JsonResponse
    {
        try {
            $request->validate(['abilities' => 'required|array']);

            $user = $request->user();
            if (!$user) {
                return $this->unauthenticated();
            }

            $token = $user->tokens()->find($tokenId);
            if (!$token) {
                return $this->errorResponse('token_not_found', 'Token not found.', 404);
            }

            $token->abilities = $request->abilities;
            $token->save();

            return response()->json([
                'success' => true,
                'message' => 'Token permissions updated successfully.',
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            return $this->errorResponse('update_token_failed', 'Failed to update token.', 500);
        }
    }

    // ==================== INTERNAL HELPERS ====================

    protected function unauthenticated(): JsonResponse
    {
        return $this->errorResponse('unauthenticated', 'Unauthenticated.', 401);
    }

    protected function validationError(ValidationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => 'validation_failed',
            'message' => 'Validation failed.',
            'errors'  => $e->errors(),
        ], 422);
    }

    protected function errorResponse(string $error, string $message, int $status = 400, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'success' => false,
            'error'   => $error,
            'message' => $message,
        ], $extra), $status);
    }

    protected function userPayload(User $user): array
    {
        return [
            'id'                 => $user->id,
            'name'               => $user->name,
            'email'              => $user->email,
            'phone'              => $user->phone,
            'type'               => $user->type,
            'status'             => $user->status ?? null,
            'role_name'          => method_exists($user, 'getRoleName') ? $user->getRoleName() : null,
            'avatar'             => $user->avatar_url ?? null,
            'email_verified_at'  => $user->email_verified_at,
            'two_factor_enabled' => (bool) ($user->two_factor_enabled ?? false),
            'dashboard_url'      => $this->dashboardUrlFor($user),
        ];
    }

    protected function dashboardUrlFor(User $user): string
    {
        $map = [
            User::TYPE_SUPER_ADMIN          => '/super-admin-dashboard',
            User::TYPE_ADMIN                => '/admin-dashboard',
            User::TYPE_LANDLORD             => '/landlord-dashboard',
            User::TYPE_TENANT               => '/tenant-dashboard',
            User::TYPE_FIELD_AGENT          => '/field-agent-dashboard',
            User::TYPE_DEVELOPER            => '/developer-dashboard',
            User::TYPE_SECURITY_PERSONNEL   => '/security-dashboard',
            User::TYPE_CONTRACTOR           => '/contractor-dashboard',
            User::TYPE_SANITATION_PERSONNEL => '/sanitation-dashboard',
        ];

        return $map[$user->getRawOriginal('type')] ?? '/dashboard';
    }

    protected function billingPayloadFor(User $user): array
    {
        try {
            $developer = DeveloperSetting::current();
            if (!$developer) {
                return ['state' => null, 'restricted' => false, 'pay_url' => null];
            }

            $restricted = false;
            $payUrl     = null;

            if ($user->type === User::TYPE_SUPER_ADMIN) {
                $restricted = $developer->isBillingRestrictingSuperAdmin();
                $payUrl     = $restricted
                    ? route('superadmin.billing.dashboard')
                    : null;
            } elseif ($user->type === User::TYPE_ADMIN) {
                $restricted = $developer->isBillingRestrictingAdmins();
            }

            return [
                'state'          => $developer->billing_state,
                'restricted'     => $restricted,
                'pay_url'        => $payUrl,
                'days_until_due' => $developer->daysUntilDue(),
            ];
        } catch (\Throwable $e) {
            return ['state' => null, 'restricted' => false, 'pay_url' => null];
        }
    }

    protected function getCredentials(Request $request): array
    {
        $login = $request->input('login');

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return [
                'email'    => strtolower($login),
                'password' => $request->input('password'),
            ];
        }

        return [
            'phone'    => $this->phoneService->normalize($login),
            'password' => $request->input('password'),
        ];
    }

    protected function findUserByCredentials(array $credentials): ?User
    {
        if (isset($credentials['email'])) {
            $email = $credentials['email'];

            $user = User::withTrashed()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();
            if ($user) {
                return $user;
            }

            if (preg_match('/^(.+)\.archived\.\d+@(.+)$/', $email, $m)) {
                $originalEmail = $m[1] . '@' . $m[2];
                $user = User::withTrashed()
                    ->whereRaw('LOWER(email) = ?', [$originalEmail])
                    ->first();
                if ($user) {
                    return $user;
                }
            }

            if (str_contains($email, '@')) {
                [$local, $domain] = explode('@', $email, 2);
                $tombstonePattern = $local . '.archived.%@' . $domain;

                $user = User::withTrashed()
                    ->where('email', 'like', $tombstonePattern)
                    ->first();
                if ($user) {
                    return $user;
                }
            }

            return null;
        }

        $phone = $credentials['phone'] ?? null;
        if (!$phone) {
            return null;
        }

        $formats = $this->phoneService->getAllFormats($phone);
        if (!empty($formats)) {
            $user = User::withTrashed()->whereIn('phone', $formats)->first();
            if ($user) {
                return $user;
            }
        }

        $user = User::withTrashed()->where('phone', $phone)->first();
        if ($user) {
            return $user;
        }

        if (is_string($phone) && strlen($phone) >= 6) {
            $needle = ltrim($phone, '+');
            $user = User::withTrashed()
                ->where(function ($q) use ($needle, $phone) {
                    $q->where('phone', 'like', '%' . $needle . '%')
                      ->orWhere('phone', 'like', '%' . $phone . '%');
                })
                ->first();
            if ($user) {
                return $user;
            }
        }

        return null;
    }

    protected function hasPermissionToLogin(User $user): bool
    {
        return in_array($user->getRawOriginal('type'), self::LOGIN_ALLOWED_TYPES, true);
    }

    // ---- Login rate limiting ----

    protected function hasTooManyLoginAttempts(Request $request): bool
    {
        return RateLimiter::tooManyAttempts($this->throttleKey($request), $this->maxAttempts);
    }

    protected function incrementLoginAttempts(Request $request): void
    {
        RateLimiter::hit($this->throttleKey($request), $this->decayMinutes * 60);
    }

    protected function clearLoginAttempts(Request $request): void
    {
        RateLimiter::clear($this->throttleKey($request));
    }

    protected function throttleKey(Request $request): string
    {
        $login = (string) $request->input('login');
        return Str::transliterate(Str::lower($login) . '|' . $request->ip());
    }

    // ---- 2FA / password rate limiting ----

    protected function twoFactorThrottleKey(User $user): string
    {
        return 'api-2fa:' . $user->id;
    }

    protected function hasTooMany2faAttempts(User $user): bool
    {
        return RateLimiter::tooManyAttempts($this->twoFactorThrottleKey($user), 5);
    }

    protected function recordFailed2fa(User $user): void
    {
        RateLimiter::hit($this->twoFactorThrottleKey($user), 300);
    }

    protected function clear2faAttempts(User $user): void
    {
        RateLimiter::clear($this->twoFactorThrottleKey($user));
    }

    protected function passwordThrottleKey(User $user): string
    {
        return 'api-password:' . $user->id;
    }

    protected function hasTooManyPasswordAttempts(User $user): bool
    {
        return RateLimiter::tooManyAttempts($this->passwordThrottleKey($user), 5);
    }

    protected function recordFailedPassword(User $user): void
    {
        RateLimiter::hit($this->passwordThrottleKey($user), 300);
    }

    protected function clearPasswordAttempts(User $user): void
    {
        RateLimiter::clear($this->passwordThrottleKey($user));
    }

    // ==================== 2FA TOKEN HANDOFF ====================

    protected function issueTwoFactorToken(User $user): string
    {
        $plain = Str::random(64);

        Cache::put(
            'mobile_2fa_token:' . hash('sha256', $plain),
            [
                'user_id'   => $user->id,
                'issued_at' => now()->timestamp,
            ],
            now()->addSeconds($this->twoFactorTokenTtlSeconds)
        );

        return $plain;
    }

    protected function resolveTwoFactorToken(?string $token): ?User
    {
        if (!$token) {
            return null;
        }

        $cacheKey = 'mobile_2fa_token:' . hash('sha256', $token);
        $payload  = Cache::get($cacheKey);

        if (!$payload || empty($payload['user_id'])) {
            return null;
        }

        Cache::forget($cacheKey);

        return User::find($payload['user_id']);
    }

    // ==================== DEVICE TRACKING ====================

    protected function trackDevice(Request $request, User $user): void
    {
        try {
            $userAgent = (string) $request->userAgent();

            $platform    = $this->getDevicePlatform($userAgent);
            $browser     = $this->getDeviceBrowser($userAgent);
            $type        = $this->getDeviceType($userAgent);
            $deviceModel = $this->getDeviceModel($userAgent);
            $osVersion   = $this->getOsVersion($userAgent);
            $deviceName  = $this->getDeviceName($userAgent, $browser, $platform);

            $token = hash('sha256', implode('|', [
                'user:' . $user->id,
                'ua:'   . $userAgent,
                'ip:'   . (string) $request->ip(),
                'lang:' . (string) $request->header('accept-language', ''),
            ]));

            $now = now();

            if (Schema::hasTable('device_tokens')) {
                try {
                    $data = [
                        'user_id'      => $user->id,
                        'token'        => $token,
                        'device_name'  => $deviceName,
                        'ip_address'   => $request->ip(),
                        'user_agent'   => $userAgent,
                        'last_used_at' => $now,
                        'updated_at'   => $now,
                    ];

                    $optional = [
                        'session_id'      => null,
                        'device_type'     => $type,
                        'device_platform' => $platform,
                        'device_browser'  => $browser,
                        'platform'        => $platform,
                        'device_model'    => $deviceModel,
                        'os_version'      => $osVersion,
                        'app_version'     => null,
                        'is_active'       => 1,
                    ];

                    foreach ($optional as $column => $value) {
                        if (Schema::hasColumn('device_tokens', $column)) {
                            $data[$column] = $value;
                        }
                    }

                    if (Schema::hasColumn('device_tokens', 'created_at')) {
                        $exists = DB::table('device_tokens')
                            ->where('user_id', $user->id)
                            ->where('token', $token)
                            ->exists();

                        if (!$exists) {
                            $data['created_at'] = $now;
                        }
                    }

                    DB::table('device_tokens')->updateOrInsert(
                        ['user_id' => $user->id, 'token' => $token],
                        $data
                    );

                    Log::info('📱 API device tracked', [
                        'user_id'     => $user->id,
                        'device_name' => $deviceName,
                        'platform'    => $platform,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Failed to track device in device_tokens: ' . $e->getMessage(), [
                        'user_id' => $user->id,
                    ]);
                }
            }

            if (Schema::hasTable('user_activities')) {
                try {
                    $payload = ['user_id' => $user->id];

                    if (Schema::hasColumn('user_activities', 'created_at')) {
                        $payload['created_at'] = $now;
                    }
                    if (Schema::hasColumn('user_activities', 'updated_at')) {
                        $payload['updated_at'] = $now;
                    }
                    if (Schema::hasColumn('user_activities', 'ip_address')) {
                        $payload['ip_address'] = $request->ip();
                    }
                    if (Schema::hasColumn('user_activities', 'user_agent')) {
                        $payload['user_agent'] = $userAgent;
                    }
                    if (Schema::hasColumn('user_activities', 'activity_type')) {
                        $activityType = 'device_tracked';
                        if (defined(UserActivity::class . '::TYPE_DEVICE_TRACKED')) {
                            $activityType = UserActivity::TYPE_DEVICE_TRACKED;
                        }
                        $payload['activity_type'] = $activityType;
                    }
                    if (Schema::hasColumn('user_activities', 'action')) {
                        $payload['action'] = 'device_tracked';
                    }
                    if (Schema::hasColumn('user_activities', 'description')) {
                        $payload['description'] = 'Device tracked for user';
                    }
                    if (Schema::hasColumn('user_activities', 'metadata')) {
                        $payload['metadata'] = json_encode([
                            'device_name'     => $deviceName,
                            'device_type'     => $type,
                            'device_platform' => $platform,
                            'device_browser'  => $browser,
                            'token'           => $token,
                        ]);
                    }
                    if (Schema::hasColumn('user_activities', 'performed_at')) {
                        $payload['performed_at'] = $now;
                    }
                    if (Schema::hasColumn('user_activities', 'is_suspicious')) {
                        $payload['is_suspicious'] = false;
                    }

                    UserActivity::create($payload);
                } catch (\Throwable $e) {
                    Log::warning('Failed to log device activity: ' . $e->getMessage(), [
                        'user_id' => $user->id,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('trackDevice failed: ' . $e->getMessage(), [
                'user_id' => $user->id ?? null,
            ]);
        }
    }

    protected function getDeviceType(?string $userAgent): string
    {
        if (!$userAgent) return 'Unknown';
        $ua = strtolower($userAgent);
        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) return 'Tablet';
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) return 'Mobile';
        return 'Desktop';
    }

    protected function getDevicePlatform(?string $userAgent): string
    {
        if (!$userAgent) return 'Unknown';
        $ua = strtolower($userAgent);
        if (str_contains($ua, 'windows')) return 'Windows';
        if (str_contains($ua, 'mac os') || str_contains($ua, 'macintosh')) return 'macOS';
        if (str_contains($ua, 'android')) return 'Android';
        if (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ios')) return 'iOS';
        if (str_contains($ua, 'linux')) return 'Linux';
        return 'Unknown';
    }

    protected function getDeviceBrowser(?string $userAgent): string
    {
        if (!$userAgent) return 'Unknown';
        $ua = strtolower($userAgent);
        if (str_contains($ua, 'edg/') || str_contains($ua, 'edge/')) return 'Microsoft Edge';
        if (str_contains($ua, 'opr/') || str_contains($ua, 'opera/')) return 'Opera';
        if (str_contains($ua, 'chrome/') && !str_contains($ua, 'edg/') && !str_contains($ua, 'opr/')) return 'Chrome';
        if (str_contains($ua, 'firefox/')) return 'Firefox';
        if (str_contains($ua, 'safari/') && !str_contains($ua, 'chrome/') && !str_contains($ua, 'chromium')) return 'Safari';
        if (str_contains($ua, 'msie') || str_contains($ua, 'trident/')) return 'Internet Explorer';
        return 'Unknown';
    }

    protected function getDeviceName(?string $userAgent, ?string $browser = null, ?string $platform = null): string
    {
        $browser  = $browser  ?? $this->getDeviceBrowser($userAgent);
        $platform = $platform ?? $this->getDevicePlatform($userAgent);

        if ($browser === 'Unknown' && $platform === 'Unknown') {
            return 'Unknown Device';
        }
        if ($browser === 'Unknown') {
            return $platform . ' Device';
        }
        if ($platform === 'Unknown') {
            return $browser . ' Browser';
        }

        return $browser . ' on ' . $platform;
    }

    protected function getDeviceModel(?string $userAgent): ?string
    {
        if (!$userAgent) return null;
        $ua = $userAgent;

        if (preg_match('/iPhone/', $ua)) return 'iPhone';
        if (preg_match('/iPad/', $ua)) return 'iPad';

        if (preg_match('/Android[^;]*;\s*([^;)]+?)(?:\s+Build|\))/i', $ua, $m)) {
            $model = trim($m[1]);
            if (!preg_match('/^[a-z]{2}[-_][a-z]{2}$/i', $model)) {
                return $model;
            }
        }
        return null;
    }

    protected function getOsVersion(?string $userAgent): ?string
    {
        if (!$userAgent) return null;
        $ua = $userAgent;

        if (preg_match('/Windows NT ([\d.]+)/', $ua, $m)) {
            return match ($m[1]) {
                '10.0' => '10/11',
                '6.3'  => '8.1',
                '6.2'  => '8',
                '6.1'  => '7',
                '6.0'  => 'Vista',
                '5.1'  => 'XP',
                default => $m[1],
            };
        }

        if (preg_match('/Mac OS X ([\d_.]+)/', $ua, $m)) {
            return str_replace('_', '.', $m[1]);
        }

        if (preg_match('/Android ([\d.]+)/', $ua, $m)) {
            return $m[1];
        }

        if (preg_match('/OS ([\d_]+) like Mac OS X/', $ua, $m)) {
            return str_replace('_', '.', $m[1]);
        }

        return null;
    }

    /**
     * Prune old access tokens for a user.
     *
     * ✅ FIXED: sort by `id DESC` (creation order) instead of
     *    `last_used_at DESC`.
     *
     * Why this matters: when the newly-created token is passed in
     * (or exists at all), its `last_used_at` is NULL. In MySQL and
     * SQLite, `ORDER BY ... DESC` puts NULLs last — so the freshly
     * created token lands at the bottom of the list, gets picked by
     * `skip($keep)`, and is deleted by the cleanup. The client then
     * holds a token string whose database row is gone, and every
     * subsequent authenticated request 401s.
     *
     * Sorting by id instead of last_used_at guarantees the newest
     * tokens are always kept, regardless of usage.
     *
     * Note: callers now invoke this BEFORE `createToken()`, so even
     * if the sort order ever changed, the fresh token would not exist
     * yet and could not be a cleanup target.
     */
    protected function cleanupTokensForUser(User $user, string $currentDeviceName): void
    {
        try {
            $keep = max(1, $this->maxDevicesPerUser - 1);

            $ids = $user->tokens()
                ->orderByDesc('id')
                ->skip($keep)
                ->take(PHP_INT_MAX)
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                $user->tokens()->whereIn('id', $ids)->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('Token cleanup failed: ' . $e->getMessage());
        }
    }

    protected function storeDeviceToken(User $user, string $deviceName, string $token): void
    {
        if (Schema::hasTable('device_tokens') && Schema::hasColumn('device_tokens', 'token')) {
            $token = trim($token);
            if ($token === '') {
                $token = hash('sha256', implode('|', [
                    'user:' . $user->id,
                    'name:' . $deviceName,
                    'time:' . microtime(true),
                ]));
            }

            $now = now();

            $data = [
                'token'        => $token,
                'last_used_at' => $now,
                'updated_at'   => $now,
            ];

            $exists = DB::table('device_tokens')
                ->where('user_id', $user->id)
                ->where('device_name', $deviceName)
                ->exists();

            if ($exists) {
                DB::table('device_tokens')
                    ->where('user_id', $user->id)
                    ->where('device_name', $deviceName)
                    ->update($data);
            } else {
                $data['user_id']     = $user->id;
                $data['device_name'] = $deviceName;
                $data['ip_address']  = request()->ip();
                $data['user_agent']  = (string) request()->userAgent();

                if (Schema::hasColumn('device_tokens', 'created_at')) {
                    $data['created_at'] = $now;
                }

                DB::table('device_tokens')->insert($data);
            }
            return;
        }

        $devices = $user->devices ?? [];
        if (is_string($devices)) {
            $devices = json_decode($devices, true) ?: [];
        }

        $found = false;
        foreach ($devices as &$d) {
            if (($d['name'] ?? null) === $deviceName) {
                $d['token']     = $token;
                $d['last_used'] = now()->toDateTimeString();
                $found = true;
                break;
            }
        }
        unset($d);

        if (!$found) {
            $devices[] = [
                'name'       => $deviceName,
                'token'      => $token,
                'last_used'  => now()->toDateTimeString(),
                'created_at' => now()->toDateTimeString(),
            ];
        }

        $user->devices = $user->hasCast('devices', 'array') ? $devices : json_encode($devices);
        $user->save();
    }

    protected function removeDeviceToken(User $user, string $deviceName): void
    {
        if (Schema::hasTable('device_tokens')) {
            DB::table('device_tokens')
                ->where('user_id', $user->id)
                ->where('device_name', $deviceName)
                ->delete();
        }

        $devices = $user->devices ?? [];
        if (is_string($devices)) {
            $devices = json_decode($devices, true) ?: [];
        }

        $devices = array_values(array_filter(
            $devices,
            fn ($d) => ($d['name'] ?? null) !== $deviceName
        ));

        $user->devices = $user->hasCast('devices', 'array') ? $devices : json_encode($devices);
        $user->save();
    }
}