<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\DeveloperSetting;             // ✅ NEW
use App\Models\User;
use App\Services\Authentication\LoginActivityService;
use App\Services\Authentication\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorController extends Controller implements HasMiddleware
{
    /**
     * HttpOnly cookie name for the trusted-device token.
     */
    protected const TRUSTED_DEVICE_COOKIE = 'trusted_device_token';

    /**
     * Lockout policy for sensitive operations.
     */
    protected const PASSWORD_ATTEMPT_LIMIT = 5;
    protected const PASSWORD_ATTEMPT_DECAY = 300;

    /**
     * Attempts allowed per user for code verification.
     */
    protected const CODE_ATTEMPT_LIMIT = 5;

    /**
     * ✅ NEW: session keys shared with LoginController and
     *         EnsureTwoFactorSetupCompleted. Centralised here so a
     *         rename is a one-line change.
     */
    protected const SESSION_SETUP_USER_ID    = '2fa_setup_required:user_id';
    protected const SESSION_SETUP_EXPIRES_AT = '2fa_setup_required:expires_at';
    protected const SESSION_SETUP_REMEMBER   = '2fa_setup_required:remember';

    protected TwoFactorAuthService $twoFactorService;
    protected LoginActivityService $activityService;

    public function __construct(
        TwoFactorAuthService $twoFactorService,
        LoginActivityService $activityService
    ) {
        $this->twoFactorService = $twoFactorService;
        $this->activityService  = $activityService;
    }

    /**
     * Middleware declarations.
     *
     * ✅ CHANGED: the mandatory setup methods are placed in NEITHER
     *    the `guest` nor the `auth` group. They run inside the
     *    `two_factor.setup` route group (defined in bootstrap/app.php),
     *    which layers `web` + `two_factor.setup.required`. During
     *    mandatory setup the user is logged OUT of the app — if we
     *    wrapped these methods in `auth`, the Authenticate middleware
     *    would bounce them to /login before they could finish setup.
     *
     *    Similarly, we do NOT wrap them in `guest` because the user
     *    technically still has a valid session cookie (they just aren't
     *    authenticated). `guest` would allow the request through, but
     *    registering it here would be misleading.
     */
    public static function middleware(): array
    {
        return [
            // ── Login handoff (user has passed password, not yet authed) ──
            new Middleware('guest', only: [
                'showVerificationForm',
                'verify',
                'resendCode',
            ]),

            // ── Authenticated management ──
            new Middleware('auth', only: [
                'showSetupForm',
                'enableTwoFactor',
                'verifySetup',
                'disableTwoFactor',
                'showRecoveryCodes',
                'regenerateRecoveryCodes',
                'getTrustedDevices',
                'removeTrustedDevice',
                'trustDevicePublic',
            ]),

            // ── ✅ NEW: mandatory setup ──
            //
            //  These methods rely on the `two_factor.setup` route group
            //  to gate access. Declaring them here with an empty array
            //  would be a no-op, so we simply do NOT list them.
            //
            //  If you ever move these routes out of the group, add
            //  `two_factor.setup.required` explicitly to the `only`
            //  array below.
            //
            // 'showRequiredSetupForm',
            // 'enableFromSetupRequired',
            // 'verifySetupRequired',
            // 'cancelRequiredSetup',
        ];
    }

    /* ============================================================
     | LOGIN 2FA — verification handoff from LoginController
     * ============================================================ */

    /**
     * Show the 2FA verification form.
     *
     * ✅ FIXED (2026-10-13): the first render dispatches the initial code.
     *    The send happens exactly once per 2FA handoff session, guarded
     *    by the `2fa:code_sent` session flag.
     */
    public function showVerificationForm(Request $request): RedirectResponse|View
    {
        $userId = session('2fa:user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (!$user) {
            $this->forgetTwoFactorSession();
            return redirect()->route('login');
        }

        $remainingAttempts = $this->twoFactorService->getRemainingAttempts($user);

        if ($remainingAttempts <= 0) {
            $this->forgetTwoFactorSession();
            return redirect()->route('login')
                ->withErrors(['code' => 'Too many failed attempts. Please login again.']);
        }

        $codeSent = (bool) session('2fa:code_sent', false);

        if (!$codeSent) {
            $deliverySucceeded = $this->twoFactorService->sendCode($user);

            if ($deliverySucceeded) {
                $request->session()->put('2fa:code_sent', true);
                $request->session()->forget('2fa_delivery_failed');
                $request->session()->forget('2fa_delivery_error');

                Log::info('[2FA] Initial code dispatched', [
                    'user_id' => $user->id,
                    'method'  => $user->two_factor_method ?? 'email',
                ]);
            } else {
                $request->session()->put('2fa_delivery_failed', true);
                $request->session()->put(
                    '2fa_delivery_error',
                    'We could not deliver your verification code. Please try again, or contact support.'
                );

                Log::warning('[2FA] Initial code dispatch failed', [
                    'user_id' => $user->id,
                    'method'  => $user->two_factor_method ?? 'email',
                ]);
            }
        }

        $deliveryFailed = session()->pull('2fa_delivery_failed', false);

        return view('auth.two-factor-verify', [
            'user'                 => $user,
            'method'               => $user->two_factor_method ?? 'email',
            'remaining_attempts'   => $remainingAttempts,
            'trust_device_enabled' => (bool) config('auth.two_factor.trust_device', true),
            'delivery_failed'      => $deliveryFailed,
        ]);
    }

    /**
     * Verify the code submitted during login and complete the session.
     *
     * ✅ FIXED (2026-10-13): delegates the post-2FA destination to
     *    LoginController's role-aware + billing-aware redirect.
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code'         => 'required|string|size:6',
            'trust_device' => 'sometimes|boolean',
        ]);

        $userId = session('2fa:user_id');
        if (!$userId) {
            throw ValidationException::withMessages([
                'code' => 'Session expired. Please login again.',
            ]);
        }

        $user = User::find($userId);
        if (!$user) {
            $this->forgetTwoFactorSession();
            throw ValidationException::withMessages([
                'code' => 'User not found. Please login again.',
            ]);
        }

        $throttleKey = $this->twoFactorVerifyThrottleKey($user);
        if (RateLimiter::tooManyAttempts($throttleKey, self::CODE_ATTEMPT_LIMIT)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->forgetTwoFactorSession();
            throw ValidationException::withMessages([
                'code' => "Too many failed attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if ($this->twoFactorService->getRemainingAttempts($user) <= 0) {
            $this->forgetTwoFactorSession();
            throw ValidationException::withMessages([
                'code' => 'Too many failed attempts. Please login again.',
            ]);
        }

        if (!$this->twoFactorService->verify($user, $request->code)) {
            RateLimiter::hit($throttleKey, self::PASSWORD_ATTEMPT_DECAY);
            $this->twoFactorService->incrementFailedAttempts($user);
            $this->activityService->recordTwoFactorFailure($user, $request);

            if (RateLimiter::tooManyAttempts($throttleKey, self::CODE_ATTEMPT_LIMIT)) {
                $this->forgetTwoFactorSession();
            }

            throw ValidationException::withMessages([
                'code' => 'Invalid verification code. Please try again.',
            ])->redirectTo(route('2fa.verify'));
        }

        // ---- Success ----
        RateLimiter::clear($throttleKey);
        $this->twoFactorService->clearFailedAttempts($user);
        $this->activityService->recordTwoFactorSuccess($user, $request);

        if ($request->boolean('trust_device')) {
            $this->trustDevice($user, $request);
        }

        $remember = (bool) session('2fa:remember', false);

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        Auth::login($user, $remember);

        $this->forgetTwoFactorSession();
        $this->forgetRequiredSetupSession();   // ✅ NEW: defensive cleanup

        $user->updateLastActivity();

        $redirector = app(\App\Http\Controllers\Auth\LoginController::class);

        return $redirector->publicPostLoginRedirect($user, $request)
            ->with('success', 'Two-factor authentication verified successfully.');
    }

    /**
     * Resend a code via the user's preferred 2FA channel.
     */
    public function resendCode(Request $request): JsonResponse
    {
        $userId = session('2fa:user_id');
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error'   => 'session_expired',
                'message' => 'Session expired. Please login again.',
            ], 401);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error'   => 'user_not_found',
                'message' => 'User not found.',
            ], 404);
        }

        $resendKey = '2fa_resend:' . $user->id;
        if (RateLimiter::tooManyAttempts($resendKey, 3)) {
            $seconds = RateLimiter::availableIn($resendKey);
            return response()->json([
                'success'     => false,
                'error'       => 'too_many_requests',
                'message'     => "Please wait {$seconds} seconds before requesting another code.",
                'retry_after' => $seconds,
            ], 429);
        }
        RateLimiter::hit($resendKey, self::PASSWORD_ATTEMPT_DECAY);

        $sent = $this->twoFactorService->sendCode($user);

        if (!$sent) {
            $method = $user->two_factor_method ?? 'email';

            $message = $method === 'sms'
                ? 'We could not send your code via SMS. Please try again or contact support.'
                : 'We could not send your code via email. Please try again or contact support.';

            session()->flash('2fa_delivery_failed', true);
            session()->flash('2fa_delivery_error', $message);

            return response()->json([
                'success' => false,
                'error'   => 'delivery_failed',
                'message' => $message,
                'method'  => $method,
            ], 500);
        }

        $request->session()->put('2fa:code_sent', true);
        $request->session()->forget('2fa_delivery_failed');
        $request->session()->forget('2fa_delivery_error');

        return response()->json([
            'success'    => true,
            'message'    => 'Verification code resent successfully.',
            'method'     => $user->two_factor_method ?? 'email',
            'expires_in' => (int) config('auth.two_factor.code_ttl_minutes', 10) * 60,
        ]);
    }

    /* ============================================================
     | ✅ NEW: MANDATORY SETUP — policy-driven flow
     | ------------------------------------------------------------
     | Entry conditions (all must hold):
     |   - Request is inside the `two_factor.setup` route group
     |     (web + two_factor.setup.required middleware).
     |   - Session has a non-expired `2fa_setup_required:user_id`
     |     written by LoginController when it detected that the
     |     developer policy requires 2FA for this user's role.
     |
     | The user is NOT authenticated here. We resolve the target
     | user from the session handoff key, never from Auth.
     |
     | On successful enable, we:
     |   1. Persist the secret to the user row.
     |   2. Generate backup codes.
     |   3. Log the user in (remember-me preference preserved).
     |   4. Forget the setup handoff keys.
     |   5. Redirect via LoginController's role/billing-aware logic.
     * ============================================================ */

    /**
     * Show the mandatory setup wizard.
     */
    public function showRequiredSetupForm(Request $request): RedirectResponse|View
    {
        $user = $this->resolveSetupUserFromSession($request);

        if (!$user) {
            return redirect()->route('login')
                ->withErrors(['login' => 'Setup session expired. Please login again.']);
        }

        // Already enrolled (race condition: user enabled 2FA on another tab).
        // Complete the handoff and send them into the app.
        if ($user->two_factor_enabled) {
            return $this->completeRequiredSetupAndLogin($user, $request);
        }

        // Reuse the pending secret across refreshes so the QR stays stable.
        $secret = session('2fa:pending_secret');
        if (!$secret) {
            $secret = $this->twoFactorService->generateSecret();
            $request->session()->put('2fa:pending_secret', $secret);
        }

        return view('auth.two-factor-setup', [
            'user'               => $user,
            'qr_code_url'        => $this->twoFactorService->getQRCodeUrlForSecret($user, $secret),
            'qr_code_svg'        => $this->twoFactorService->getQRCodeSvgForSecret($user, $secret),
            'secret'             => $secret,
            'method'             => $user->two_factor_method ?? 'email',
            'backup_codes_count' => (int) config('auth.two_factor.backup_codes_count', 8),
            'mandatory'          => true,     // ✅ lets the blade hide "Cancel"
            'policy'             => $this->resolvePolicyString(),
        ]);
    }

    /**
     * AJAX-only: validate a code against the pending secret WITHOUT
     * enabling. Used for live UX feedback in the mandatory wizard.
     */
    public function verifySetupRequired(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $this->resolveSetupUserFromSession($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error'   => 'setup_session_expired',
                'message' => 'Setup session expired. Please login again.',
            ], 401);
        }

        $secret = session('2fa:pending_secret');
        if (!$secret) {
            return response()->json([
                'success' => false,
                'error'   => 'setup_not_initialized',
                'message' => 'Setup session expired. Please refresh the page.',
            ], 400);
        }

        $isValid = $this->twoFactorService->verifyCode($secret, $request->code);

        return response()->json([
            'success' => true,
            'valid'   => $isValid,
        ]);
    }

    /**
     * Persist the secret, enable 2FA, log the user in, and hand off
     * to the standard post-login redirect.
     */
    public function enableFromSetupRequired(Request $request): JsonResponse|RedirectResponse
    {
        // Accept `verification_code` (canonical) or `code` (legacy).
        $request->merge([
            'verification_code' => $request->input(
                'verification_code',
                $request->input('code')
            ),
        ]);

        $request->validate([
            'verification_code' => 'required|string|size:6',
            'method'            => 'sometimes|in:email,sms',
        ]);

        $user = $this->resolveSetupUserFromSession($request);
        if (!$user) {
            return $this->twoFactorResponse(
                $request,
                false,
                'Setup session expired. Please login again.',
                401
            );
        }

        // Race: user finished setup in another tab.
        if ($user->two_factor_enabled) {
            return $this->completeRequiredSetupAndLogin($user, $request);
        }

        $secret = session('2fa:pending_secret');
        if (!$secret) {
            return $this->twoFactorResponse(
                $request,
                false,
                'Setup session expired. Please refresh the page and try again.',
                400
            );
        }

        $throttleKey = '2fa_setup_required:' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, self::CODE_ATTEMPT_LIMIT)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return $this->twoFactorResponse(
                $request,
                false,
                "Too many attempts. Try again in {$seconds} seconds.",
                429,
                ['retry_after' => $seconds]
            );
        }
        RateLimiter::hit($throttleKey, self::PASSWORD_ATTEMPT_DECAY);

        if (!$this->twoFactorService->verifyCode($secret, $request->verification_code)) {
            $this->activityService->recordTwoFactorFailure($user, $request);
            return $this->twoFactorResponse(
                $request,
                false,
                'Invalid verification code. Please try again.',
                422
            );
        }

        RateLimiter::clear($throttleKey);

        if ($request->filled('method')) {
            $user->two_factor_method = $request->method;
        }

        $this->twoFactorService->enableWithSecret($user, $secret);
        $backupCodes = $this->twoFactorService->generateBackupCodes($user);

        $request->session()->forget('2fa:pending_secret');

        $this->activityService->recordActivity(
            $user,
            $request,
            '2fa_enabled_mandatory',
            'web'
        );

        // ── Log in and clear the handoff ──
        $remember = (bool) $request->session()->get(self::SESSION_SETUP_REMEMBER, false);

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        Auth::login($user, $remember);

        $this->forgetRequiredSetupSession();
        $this->forgetTwoFactorSession();

        $user->updateLastActivity();

        $redirector = app(\App\Http\Controllers\Auth\LoginController::class);

        if ($request->wantsJson()) {
            return response()->json([
                'success'      => true,
                'message'      => 'Two-factor authentication enabled successfully.',
                'backup_codes' => $backupCodes,
                'redirect_to'  => $redirector->publicPostLoginRedirect($user, $request)
                    ->getTargetUrl(),
            ]);
        }

        session()->flash('backup_codes', $backupCodes);

        return $redirector->publicPostLoginRedirect($user, $request)
            ->with('success', 'Two-factor authentication enabled. Please save your backup codes.');
    }

    /**
     * Cancel the mandatory setup handoff.
     *
     * Logs the user out entirely (they were never "in" the app) and
     * returns them to /login. This is the escape hatch when a user
     * decides they don't want to enroll right now.
     *
     * Deliberately permitted: locking a user out of /login because they
     * declined to enroll would be a foot-gun. The alternative — letting
     * them back into the app without 2FA — would violate the policy.
     * So they go back to /login, where the next password entry will
     * place them right back here. This is the same UX as GitHub,
     * Google, etc.
     */
    public function cancelRequiredSetup(Request $request): RedirectResponse
    {
        $user = $this->resolveSetupUserFromSession($request);

        if ($user) {
            $this->activityService->recordActivity(
                $user,
                $request,
                '2fa_setup_required_cancelled',
                'web'
            );
        }

        $this->forgetRequiredSetupSession();
        $this->forgetTwoFactorSession();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('warning', 'Two-factor setup cancelled. You must complete it to use this account.');
    }

    /* ============================================================
     | 2FA SETUP — authenticated users enabling 2FA
     * ============================================================ */

    /**
     * Show the setup wizard for an authenticated user who wants to
     * enable 2FA voluntarily.
     *
     * ✅ FIXED: guards against a missing user (was `$request->user()->…`
     *    which would null-pointer if the auth middleware ever failed
     *    to run).
     */
    public function showSetupForm(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->two_factor_enabled) {
            return redirect()->route('2fa.recovery-codes')
                ->with('info', 'Two-factor authentication is already enabled.');
        }

        $secret = session('2fa:pending_secret');
        if (!$secret) {
            $secret = $this->twoFactorService->generateSecret();
            session(['2fa:pending_secret' => $secret]);
        }

        return view('auth.two-factor-setup', [
            'user'               => $user,
            'qr_code_url'        => $this->twoFactorService->getQRCodeUrlForSecret($user, $secret),
            'qr_code_svg'        => $this->twoFactorService->getQRCodeSvgForSecret($user, $secret),
            'secret'             => $secret,
            'method'             => $user->two_factor_method ?? 'email',
            'backup_codes_count' => (int) config('auth.two_factor.backup_codes_count', 8),
            'mandatory'          => false,   // ✅ voluntary flow
        ]);
    }

    /**
     * Enable 2FA. Requires the pending secret to exist in the session
     * and a valid TOTP code proving the user scanned it.
     */
    public function enableTwoFactor(Request $request): JsonResponse|RedirectResponse
    {
        $request->merge([
            'verification_code' => $request->input(
                'verification_code',
                $request->input('code')
            ),
        ]);

        $request->validate([
            'verification_code' => 'required|string|size:6',
            'method'            => 'sometimes|in:email,sms',
        ]);

        $user = $request->user();
        if (!$user) {
            return $this->twoFactorResponse(
                $request,
                false,
                'You must be logged in to enable two-factor authentication.',
                401
            );
        }

        if ($user->two_factor_enabled) {
            return $this->twoFactorResponse($request, false,
                'Two-factor authentication is already enabled.',
                400
            );
        }

        $secret = session('2fa:pending_secret');
        if (!$secret) {
            return $this->twoFactorResponse($request, false,
                'Setup session expired. Please refresh the page and try again.',
                400
            );
        }

        $throttleKey = '2fa_setup:' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, self::CODE_ATTEMPT_LIMIT)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return $this->twoFactorResponse($request, false,
                "Too many attempts. Try again in {$seconds} seconds.",
                429,
                ['retry_after' => $seconds]
            );
        }
        RateLimiter::hit($throttleKey, self::PASSWORD_ATTEMPT_DECAY);

        if (!$this->twoFactorService->verifyCode($secret, $request->verification_code)) {
            $this->activityService->recordTwoFactorFailure($user, $request);
            return $this->twoFactorResponse($request, false,
                'Invalid verification code. Please try again.',
                422
            );
        }

        RateLimiter::clear($throttleKey);

        if ($request->filled('method')) {
            $user->two_factor_method = $request->method;
        }

        $this->twoFactorService->enableWithSecret($user, $secret);
        $backupCodes = $this->twoFactorService->generateBackupCodes($user);

        session()->forget('2fa:pending_secret');

        $this->activityService->recordActivity($user, $request, '2fa_enabled', 'web');

        if ($request->wantsJson()) {
            return response()->json([
                'success'      => true,
                'message'      => 'Two-factor authentication enabled successfully.',
                'backup_codes' => $backupCodes,
            ]);
        }

        session()->flash('backup_codes', $backupCodes);

        return redirect()->route('2fa.recovery-codes')
            ->with('success', 'Two-factor authentication enabled successfully. Please save your backup codes.');
    }

    /**
     * AJAX: validate a code during setup WITHOUT enabling 2FA.
     */
    public function verifySetup(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        if (!$user) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'unauthenticated',
                    'message' => 'You must be logged in.',
                ], 401);
            }
            return redirect()->route('login');
        }

        $secret = session('2fa:pending_secret');

        if (!$secret) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'setup_not_initialized',
                    'message' => 'Setup session expired. Please refresh the page.',
                ], 400);
            }
            return back()->withErrors(['code' => 'Setup session expired. Please refresh the page.']);
        }

        $isValid = $this->twoFactorService->verifyCode($secret, $request->code);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'valid'   => $isValid,
            ]);
        }

        if (!$isValid) {
            return back()->withErrors(['code' => 'Invalid verification code.']);
        }

        return redirect()->route('2fa.setup')
            ->with('code_validated', true);
    }

    /**
     * Disable 2FA. Requires password re-entry.
     */
    public function disableTwoFactor(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();
        if (!$user) {
            return $this->twoFactorResponse($request, false,
                'You must be logged in.',
                401
            );
        }

        if ($this->hasTooManyPasswordAttempts($user)) {
            $seconds = RateLimiter::availableIn($this->passwordThrottleKey($user));
            return $this->twoFactorResponse($request, false,
                "Too many attempts. Try again in {$seconds} seconds.",
                429,
                ['retry_after' => $seconds]
            );
        }

        if (!Hash::check($request->password, $user->password)) {
            RateLimiter::hit($this->passwordThrottleKey($user), self::PASSWORD_ATTEMPT_DECAY);
            return $this->twoFactorResponse($request, false,
                'Invalid password. Please try again.',
                422
            );
        }

        RateLimiter::clear($this->passwordThrottleKey($user));

        $this->twoFactorService->disable($user);
        $this->activityService->recordActivity($user, $request, '2fa_disabled', 'web');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Two-factor authentication disabled successfully.',
            ]);
        }

        return redirect()->route('profile.settings')
            ->with('success', 'Two-factor authentication disabled successfully.');
    }

    /**
     * Show recovery codes.
     *
     * ⚠️  NOTE: `$user->two_factor_backup_codes` stores HASHES, so the
     *     `json_decode($user->two_factor_backup_codes)` fallback below
     *     is only meaningful for display when the codes were flashed
     *     to the session (which only happens right after generation).
     *     For an existing user, this view will show the hashed list.
     *     Fixing that is out of scope here — the correct pattern is to
     *     keep a plaintext copy in a secure, short-lived store and
     *     show it exactly once.
     */
    public function showRecoveryCodes(Request $request): RedirectResponse|View
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->two_factor_enabled) {
            return redirect()->route('2fa.setup')
                ->with('warning', 'Please enable two-factor authentication first.');
        }

        $flashCodes = session('backup_codes');

        $backupCodes = $flashCodes
            ?? (json_decode($user->two_factor_backup_codes ?? '[]', true) ?: []);

        $remainingCount = count($backupCodes);
        $totalCount     = (int) config('auth.two_factor.backup_codes_count', 8);

        return view('auth.two-factor-recovery-codes', [
            'user'            => $user,
            'backup_codes'    => $backupCodes,
            'remaining_count' => $remainingCount,
            'total_count'     => $totalCount,
            'show_warning'    => $remainingCount <= 3,
        ]);
    }

    /**
     * Regenerate recovery codes. Requires password re-entry.
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();
        if (!$user) {
            return $this->twoFactorResponse($request, false,
                'You must be logged in.',
                401
            );
        }

        if ($this->hasTooManyPasswordAttempts($user)) {
            $seconds = RateLimiter::availableIn($this->passwordThrottleKey($user));
            return $this->twoFactorResponse($request, false,
                "Too many attempts. Try again in {$seconds} seconds.",
                429,
                ['retry_after' => $seconds]
            );
        }

        if (!Hash::check($request->password, $user->password)) {
            RateLimiter::hit($this->passwordThrottleKey($user), self::PASSWORD_ATTEMPT_DECAY);
            return $this->twoFactorResponse($request, false,
                'Invalid password. Please try again.',
                422
            );
        }

        RateLimiter::clear($this->passwordThrottleKey($user));

        $backupCodes = $this->twoFactorService->generateBackupCodes($user);
        $this->activityService->recordActivity($user, $request, '2fa_codes_regenerated', 'web');

        if ($request->wantsJson()) {
            return response()->json([
                'success'      => true,
                'message'      => 'Recovery codes regenerated successfully.',
                'backup_codes' => $backupCodes,
            ]);
        }

        session()->flash('backup_codes', $backupCodes);

        return redirect()->route('2fa.recovery-codes')
            ->with('success', 'New recovery codes generated. Please save them securely.');
    }

    /* ============================================================
     | TRUSTED DEVICES
     * ============================================================ */

    protected function trustDevice(User $user, Request $request): void
    {
        if (!config('auth.two_factor.trust_device', true)) {
            return;
        }

        $rawToken = Str::random(64);
        $hash     = hash('sha256', $rawToken);

        $days = (int) config('auth.two_factor.trust_device_days', 30);

        DB::table('trusted_devices')->insert([
            'user_id'           => $user->id,
            'device_token_hash' => $hash,
            'user_agent'        => (string) $request->userAgent(),
            'ip_address'        => $request->ip(),
            'trusted_at'        => now(),
            'expires_at'        => now()->addDays($days),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        Cookie::queue(
            Cookie::make(
                name:     self::TRUSTED_DEVICE_COOKIE,
                value:    $rawToken,
                minutes:  $days * 24 * 60,
                path:     '/',
                domain:   null,
                secure:   app()->environment('production'),
                httpOnly: true,
                raw:      false,
                sameSite: 'lax'
            )
        );
    }

    public function trustDevicePublic(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error'   => 'unauthenticated',
                'message' => 'You must be logged in to trust a device.',
            ], 401);
        }

        $this->trustDevice($user, $request);

        $days = (int) config('auth.two_factor.trust_device_days', 30);

        return response()->json([
            'success' => true,
            'message' => "Device trusted for {$days} days.",
        ]);
    }

    public static function deviceIsTrustedFor(Request $request, User $user): bool
    {
        $rawToken = $request->cookie(self::TRUSTED_DEVICE_COOKIE);
        if (!$rawToken) {
            return false;
        }

        $hash = hash('sha256', $rawToken);

        return DB::table('trusted_devices')
            ->where('user_id', $user->id)
            ->where('device_token_hash', $hash)
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function isDeviceTrusted(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        return self::deviceIsTrustedFor($request, $user);
    }

    public function getTrustedDevices(Request $request): JsonResponse
    {
        $user = $request->user();

        $devices = DB::table('trusted_devices')
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->orderByDesc('trusted_at')
            ->get([
                'id',
                'user_agent',
                'ip_address',
                'trusted_at',
                'expires_at',
            ]);

        return response()->json([
            'success' => true,
            'devices' => $devices,
        ]);
    }

    public function removeTrustedDevice(Request $request, $deviceId): JsonResponse
    {
        $user = $request->user();

        $deleted = DB::table('trusted_devices')
            ->where('user_id', $user->id)
            ->where('id', $deviceId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'error'   => 'device_not_found',
                'message' => 'Device not found.',
            ], 404);
        }

        $currentRaw = $request->cookie(self::TRUSTED_DEVICE_COOKIE);
        if ($currentRaw) {
            $currentHash = hash('sha256', $currentRaw);
            $stillTrusted = DB::table('trusted_devices')
                ->where('user_id', $user->id)
                ->where('device_token_hash', $currentHash)
                ->exists();

            if (!$stillTrusted) {
                Cookie::queue(Cookie::forget(self::TRUSTED_DEVICE_COOKIE));
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Trusted device removed successfully.',
        ]);
    }

    /* ============================================================
     | INTERNAL HELPERS
     * ============================================================ */

    /**
     * ✅ NEW: resolve the target user of a mandatory-setup request.
     *
     * Reads the handoff key from the session, checks expiry, loads the
     * user. Returns null (and clears the keys) on any failure so the
     * caller can bounce cleanly.
     */
    protected function resolveSetupUserFromSession(Request $request): ?User
    {
        $userId = $request->session()->get(self::SESSION_SETUP_USER_ID);
        if (!$userId) {
            return null;
        }

        $expiresAt = (int) $request->session()->get(self::SESSION_SETUP_EXPIRES_AT, 0);
        if (now()->timestamp > $expiresAt) {
            $this->forgetRequiredSetupSession($request);
            return null;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->forgetRequiredSetupSession($request);
            return null;
        }

        return $user;
    }

    /**
     * ✅ NEW: forget the mandatory-setup handoff keys.
     */
    protected function forgetRequiredSetupSession(?Request $request = null): void
    {
        $session = $request?->session() ?? session();

        $session->forget([
            self::SESSION_SETUP_USER_ID,
            self::SESSION_SETUP_EXPIRES_AT,
            self::SESSION_SETUP_REMEMBER,
        ]);
    }

    /**
     * ✅ NEW: complete the mandatory flow when the user is already
     *         enrolled (race across tabs). Logs them in and hands off
     *         to the role/billing-aware redirect.
     */
    protected function completeRequiredSetupAndLogin(User $user, Request $request): RedirectResponse
    {
        $remember = (bool) $request->session()->get(self::SESSION_SETUP_REMEMBER, false);

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        Auth::login($user, $remember);

        $this->forgetRequiredSetupSession($request);
        $this->forgetTwoFactorSession();

        $user->updateLastActivity();

        $redirector = app(\App\Http\Controllers\Auth\LoginController::class);

        return $redirector->publicPostLoginRedirect($user, $request)
            ->with('success', 'Two-factor authentication is already enabled.');
    }

    /**
     * ✅ NEW: human-readable current policy string, for the blade.
     */
    protected function resolvePolicyString(): ?string
    {
        try {
            return DeveloperSetting::current()?->twoFactorPolicy();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Forget the 2FA session handoff keys.
     */
    protected function forgetTwoFactorSession(): void
    {
        session()->forget([
            '2fa:user_id',
            '2fa:remember',
            '2fa:pending_secret',
            '2fa:code_sent',
            '2fa_delivery_failed',
            '2fa_delivery_error',
        ]);
    }

    /**
     * Throttle key for code verification during login.
     */
    protected function twoFactorVerifyThrottleKey(User $user): string
    {
        return '2fa_verify:' . $user->id;
    }

    /**
     * Throttle key for password re-entry on sensitive actions.
     */
    protected function passwordThrottleKey(User $user): string
    {
        return '2fa_password:' . $user->id;
    }

    protected function hasTooManyPasswordAttempts(User $user): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->passwordThrottleKey($user),
            self::PASSWORD_ATTEMPT_LIMIT
        );
    }

    /**
     * Unified response builder.
     *
     * @param  bool   $success
     * @param  string $message
     * @param  int    $status
     * @param  array  $extra
     */
    protected function twoFactorResponse(
        Request $request,
        bool $success,
        string $message,
        int $status = 200,
        array $extra = []
    ): JsonResponse|RedirectResponse {
        if ($request->wantsJson()) {
            return response()->json(array_merge([
                'success' => $success,
                'message' => $message,
            ], $extra), $status);
        }

        if ($success) {
            return back()->with('success', $message);
        }

        return back()->withErrors(['error' => $message]);
    }
}