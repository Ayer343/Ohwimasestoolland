<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Authentication\PhoneNormalizationService;
use App\Services\Authentication\RoleRedirectionService;
use App\Services\Authentication\ArchivedAccountService;
use App\Services\Authentication\LoginAttemptService;
use App\Services\Authentication\SocialLoginService;
use App\Services\Authentication\PasswordResetService;
use App\Services\Authentication\TwoFactorAuthService;
use App\Services\Authentication\LoginActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\DeveloperSetting;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class LoginController extends Controller
{
    /**
     * Services
     */
    protected PhoneNormalizationService $phoneService;
    protected RoleRedirectionService $redirectionService;
    protected ArchivedAccountService $archivedAccountService;
    protected LoginAttemptService $loginAttemptService;
    protected SocialLoginService $socialLoginService;
    protected PasswordResetService $passwordResetService;
    protected TwoFactorAuthService $twoFactorAuthService;
    protected LoginActivityService $activityService;

    /**
     * Maximum number of login attempts
     */
    protected int $maxAttempts = 5;

    /**
     * Decay minutes for login attempts
     */
    protected int $decayMinutes = 1;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected string $redirectTo = '/dashboard';

    /**
     * Constructor
     */
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

    /* ============================================================
     | ✅ BILLING: post-login redirect
     | ------------------------------------------------------------
     | Decides where a freshly authenticated user should land based
     | on the current system billing state.
     |
     | Rules:
     |  - Developers are never redirected — they're the creditor.
     |  - Super admins with overdue/pending billing go straight to the
     |    billing dashboard.
     |  - Admin staff with overdue billing land on the admin dashboard
     |    (read-only mode) with a banner.
     |  - Landlords and tenants are never redirected — their payments
     |    fund the resolution of the debt.
     * ============================================================ */

    /**
     * Build the post-login redirect response for a web login.
     *
     * @param  User    $user
     * @param  Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function postLoginRedirect(User $user, Request $request)
    {
        try {
            $developer = DeveloperSetting::current();

            if ($developer) {
                $billingState = $developer->billing_state;

                // Super admin with restricted billing → billing dashboard
                if ($user->type === User::TYPE_SUPER_ADMIN
                    && $developer->isBillingRestrictingSuperAdmin()) {

                    $message = match ($billingState) {
                        DeveloperSetting::STATE_PENDING_SIGNATURE =>
                            'Your billing agreement is pending signature. '
                            . 'Please review and sign to activate your account.',
                        DeveloperSetting::STATE_ACTIVE_OVERDUE =>
                            'Your system billing is overdue. '
                            . 'Please settle your invoice to restore full access.',
                        DeveloperSetting::STATE_ACTIVE_SUSPENDED =>
                            'Your system billing is suspended. '
                            . 'Please settle your invoice to restore service.',
                        default => 'Please review your billing status.',
                    };

                    \Log::info('[Login] Super admin redirected to billing dashboard', [
                        'user_id'       => $user->id,
                        'billing_state' => $billingState,
                    ]);

                    return redirect()
                        ->route('superadmin.billing.dashboard')
                        ->with('warning', $message);
                }

                // Admin staff with overdue billing → normal redirect + banner
                if ($user->type === User::TYPE_ADMIN
                    && $developer->isBillingRestrictingAdmins()) {

                    $response = $this->redirectionService->redirect($user);

                    \Log::info('[Login] Admin logged in with billing overdue (read-only)', [
                        'user_id'       => $user->id,
                        'billing_state' => $billingState,
                    ]);

                    return $response->with('warning',
                        'System billing is overdue. Administrative write actions are temporarily disabled. '
                        . 'Contact your super admin.');
                }

                // Billing due soon → soft notice
                if ($developer->isDueSoon()
                    && $user->type === User::TYPE_SUPER_ADMIN) {

                    $days = $developer->daysUntilBillingDue();

                    $response = $this->redirectionService->redirect($user);

                    \Log::info('[Login] Super admin logged in with billing due soon', [
                        'user_id'        => $user->id,
                        'days_until_due' => $days,
                    ]);

                    return $response->with('warning',
                        'Your system billing is due in ' . $days . ' day' . ($days === 1 ? '' : 's')
                        . '. Please settle it soon to avoid service restrictions.');
                }
            }
        } catch (\Throwable $e) {
            // Never block login because billing lookup failed
            \Log::warning('[Login] Billing check failed during login', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        // Default: role-based redirect
        return $this->redirectionService->redirect($user);
    }

    /**
     * Billing state for the login page (used by showLoginForm view).
     *
     * @return array{state: ?string, overdue: bool, pending: bool}
     */
    protected function loginPageBillingState(): array
    {
        try {
            $developer = DeveloperSetting::current();
            $state     = $developer?->billing_state;

            return [
                'state'   => $state,
                'overdue' => in_array($state, [
                    DeveloperSetting::STATE_ACTIVE_OVERDUE,
                    DeveloperSetting::STATE_ACTIVE_SUSPENDED,
                ], true),
                'pending' => $state === DeveloperSetting::STATE_PENDING_SIGNATURE,
            ];
        } catch (\Throwable $e) {
            return ['state' => null, 'overdue' => false, 'pending' => false];
        }
    }

    /* ============================================================
     | SHOW LOGIN FORM
     | ============================================================ */

    public function showLoginForm(Request $request)
    {
        $this->debugSessionInfo($request, 'showLoginForm');

        if (Auth::check()) {
            // If a super admin is already logged in with billing restricted,
            // send them to the billing dashboard. Otherwise normal redirect.
            return $this->postLoginRedirect(Auth::user(), $request);
        }

        $socialError = session('social_error');
        if ($socialError) {
            return view('auth.login', [
                'social_error'          => $socialError,
                'loginBillingState'     => $this->loginPageBillingState(),
            ]);
        }

        if ($request->session()->has('session_expired')) {
            $request->session()->forget('session_expired');
            return view('auth.login', [
                'loginBillingState' => $this->loginPageBillingState(),
            ])->with('warning', 'Your session expired. Please login again.');
        }

        if ($request->query('expired') == '1') {
            return view('auth.login', [
                'loginBillingState' => $this->loginPageBillingState(),
            ])->with('error', 'Page expired due to session timeout. Please try again.');
        }

        return view('auth.login', [
            'loginBillingState' => $this->loginPageBillingState(),
        ]);
    }

    /**
     * Debug session information
     */
    protected function debugSessionInfo(Request $request, string $location): void
    {
        if (app()->environment('local') || config('app.debug')) {
            \Log::info('Session Debug - ' . $location, [
                'session_id' => session()->getId(),
                'token' => $request->session()->token(),
                'has_csrf' => $request->has('_token'),
                'csrf_match' => $request->session()->token() === $request->input('_token'),
                'cookies' => $request->cookies->all(),
                'headers' => [
                    'x-csrf-token' => $request->header('X-CSRF-TOKEN'),
                    'x-xsrf-token' => $request->header('X-XSRF-TOKEN'),
                    'referer' => $request->header('referer'),
                    'user_agent' => $request->header('user-agent'),
                ],
                'is_secure' => $request->isSecure(),
                'server_https' => $_SERVER['HTTPS'] ?? 'not set',
                'forwarded_proto' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'not set',
                'session_config' => [
                    'driver' => config('session.driver'),
                    'domain' => config('session.domain'),
                    'secure' => config('session.secure'),
                    'same_site' => config('session.same_site'),
                    'cookie' => config('session.cookie'),
                ]
            ]);
        }
    }

    /* ============================================================
     | WEB LOGIN
     | ============================================================ */

    public function login(Request $request)
    {
        $this->debugLoginRequest($request);

        if (!$this->validateSessionBeforeLogin($request)) {
            throw ValidationException::withMessages([
                'login' => 'Session validation failed. Please refresh the page and try again.'
            ]);
        }

        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
            'two_factor_code' => 'nullable|string|size:6',
        ]);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->sendLockoutResponse($request);
        }

        $credentials = $this->getCredentials($request);
        $user = $this->findUserByCredentials($credentials);

        $this->loginAttemptService->logAttempt($request, $user);

        if ($user && $user->isArchived()) {
            return $this->archivedAccountService->handleArchivedLogin($user, $request);
        }

        if ($user && $user->trashed()) {
            throw ValidationException::withMessages([
                'login' => 'This account has been deactivated. Please contact administrator.',
            ]);
        }

        if ($user && !$this->hasPermissionToLogin($user)) {
            throw ValidationException::withMessages([
                'login' => 'Your account type does not have login privileges.',
            ]);
        }

        $authenticated = false;
        $remember = $request->boolean('remember');

        if ($user && !$user->isArchived()) {
            if (Hash::check($credentials['password'], $user->password)) {
                $authenticated = $this->performLogin($user, $remember);
                \Log::info('Authentication successful for user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'authenticated' => $authenticated,
                    'session_id' => session()->getId()
                ]);
            }
        }

        if (!$authenticated && $this->attemptLoginWithPhoneVariations($credentials, $remember)) {
            $authenticated = true;
            $user = Auth::user();
            \Log::info('Authentication successful via phone variation', [
                'user_id' => $user->id,
                'email' => $user->email,
                'session_id' => session()->getId()
            ]);
        }

        if (!$authenticated) {
            $this->incrementLoginAttempts($request);
            \Log::warning('Authentication failed', [
                'login' => $request->input('login'),
                'ip' => $request->ip(),
                'session_id' => session()->getId()
            ]);
            throw ValidationException::withMessages([
                'login' => [trans('auth.failed')],
            ]);
        }

        $user = Auth::user();

        $this->regenerateSession($request);
        $this->trackDevice($request, $user);

        if ($this->twoFactorAuthService->isEnabled($user)) {
            session(['2fa:user_id' => $user->id]);
            session(['2fa:remember' => $remember]);
            Auth::logout();
            return redirect()->route('2fa.verify');
        }

        $this->clearLoginAttempts($request);
        $this->finalizeLogin($request, $user);

        // ✅ BILLING: route super admins to the billing dashboard when needed
        return $this->postLoginRedirect($user, $request)
            ->with('success', __('auth.welcome_back', ['name' => $user->name]));
    }

    /**
     * Debug login request
     */
    protected function debugLoginRequest(Request $request): void
    {
        if (app()->environment('local') || config('app.debug')) {
            \Log::info('Login Request Debug', [
                'session_id' => session()->getId(),
                'token' => $request->session()->token(),
                'submitted_token' => $request->input('_token'),
                'has_csrf' => $request->has('_token'),
                'csrf_match' => $request->session()->token() === $request->input('_token'),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'login_type' => filter_var($request->input('login'), FILTER_VALIDATE_EMAIL) ? 'email' : 'phone',
                'is_secure' => $request->isSecure(),
                'headers' => [
                    'x-csrf-token' => $request->header('X-CSRF-TOKEN'),
                    'x-xsrf-token' => $request->header('X-XSRF-TOKEN'),
                ],
                'cookies' => $request->cookies->all(),
            ]);
        }
    }

    /**
     * Validate session before login
     */
    protected function validateSessionBeforeLogin(Request $request): bool
    {
        try {
            if (!$request->session()->token()) {
                \Log::warning('Session token missing', [
                    'session_id' => session()->getId(),
                    'ip' => $request->ip()
                ]);
                return false;
            }

            if ($request->has('_token') && $request->session()->token() !== $request->input('_token')) {
                \Log::warning('CSRF token mismatch', [
                    'session_token' => $request->session()->token(),
                    'submitted_token' => $request->input('_token'),
                    'session_id' => session()->getId()
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            \Log::error('Session validation error: ' . $e->getMessage(), [
                'session_id' => session()->getId(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Regenerate session safely
     */
    protected function regenerateSession(Request $request): void
    {
        try {
            $oldSessionId = session()->getId();
            $request->session()->regenerate();
            $request->session()->regenerateToken();
            \Log::info('Session regenerated after login', [
                'old_session_id' => $oldSessionId,
                'new_session_id' => session()->getId(),
                'user_id' => Auth::id()
            ]);
        } catch (\Exception $e) {
            \Log::error('Session regeneration error: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | DEVICE TRACKING
     | ------------------------------------------------------------
     | ✅ FIXED (2026-10-01): the previous implementation of this
     | method performed a raw updateOrInsert() into device_tokens
     | but never supplied a value for the `token` column, which is
     | NOT NULL with no default. Under MySQL strict mode this
     | produced:
     |
     |   SQLSTATE[HY000]: General error: 1364
     |   Field 'token' doesn't have a default value
     |
     | The fix:
     |  1. Generate a deterministic SHA-256 `token` derived from
     |     (user_id, user_agent, ip, accept-language) — so the same
     |     browser always maps to the same row, and upserts are
     |     idempotent.
     |  2. Use (user_id, token) as the natural key for
     |     updateOrInsert().
     |  3. Store a human-readable `device_name` like
     |     "Microsoft Edge on Windows" instead of the raw UA string.
     |  4. Populate every device_* column the table actually has
     |     (checked via Schema::hasColumn), so schema drift is
     |     tolerated across environments.
     * ============================================================ */

    protected function trackDevice(Request $request, User $user): void
    {
        try {
            $userAgent = (string) $request->userAgent();

            // ---- Derive all device facts once ----
            $platform    = $this->getDevicePlatform($userAgent);
            $browser     = $this->getDeviceBrowser($userAgent);
            $type        = $this->getDeviceType($userAgent);
            $deviceModel = $this->getDeviceModel($userAgent);
            $osVersion   = $this->getOsVersion($userAgent);
            $deviceName  = $this->getDeviceName($userAgent, $browser, $platform);

            // Deterministic token — same browser + IP + UA → same token.
            $token = hash('sha256', implode('|', [
                'user:' . $user->id,
                'ua:'   . $userAgent,
                'ip:'   . (string) $request->ip(),
                'lang:' . (string) $request->header('accept-language', ''),
            ]));

            $sessionId = session()->getId();
            $now       = now();

            // ---- device_tokens: upsert on (user_id, token) ----
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

                    // Only set columns that actually exist on the table.
                    $optional = [
                        'session_id'      => $sessionId,
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

                    // updateOrInsert only applies the update array on existing
                    // rows, so we need to guard created_at on first insert.
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

                    \Log::info('Device tracked in device_tokens table', [
                        'user_id'     => $user->id,
                        'device_name' => $deviceName,
                        'platform'    => $platform,
                    ]);
                } catch (\Throwable $e) {
                    \Log::warning('Failed to track device in device_tokens: ' . $e->getMessage(), [
                        'user_id' => $user->id,
                    ]);
                }
            }

            // ---- user_activities: log the tracking event ----
            if (Schema::hasTable('user_activities')) {
                try {
                    $activityData = [
                        'user_id'    => $user->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $activityType = 'device_tracked';
                    if (defined(UserActivity::class . '::TYPE_DEVICE_TRACKED')) {
                        $activityType = UserActivity::TYPE_DEVICE_TRACKED;
                    }

                    $activityOptional = [
                        'ip_address'    => $request->ip(),
                        'user_agent'    => $userAgent,
                        'activity_type' => $activityType,
                        'action'        => 'device_tracked',
                        'description'   => 'Device tracked for user',
                        'metadata'      => json_encode([
                            'device_name'     => $deviceName,
                            'device_type'     => $type,
                            'device_platform' => $platform,
                            'device_browser'  => $browser,
                            'token'           => $token,
                        ]),
                        'performed_at'  => $now,
                        'is_suspicious' => false,
                    ];

                    foreach ($activityOptional as $column => $value) {
                        if (Schema::hasColumn('user_activities', $column)) {
                            $activityData[$column] = $value;
                        }
                    }

                    // Route through the model so the creating hook can
                    // backstop any NOT NULL column the caller forgot.
                    UserActivity::create($activityData);
                } catch (\Throwable $e) {
                    \Log::warning('Failed to log device activity: ' . $e->getMessage(), [
                        'user_id' => $user->id,
                    ]);
                }
            }

            // ---- Mirror in user->devices JSON (legacy fallback) ----
            try {
                $devices = json_decode($user->devices ?? '[]', true);
                if (!is_array($devices)) {
                    $devices = [];
                }

                $existingIndex = null;
                foreach ($devices as $index => $device) {
                    if (($device['token'] ?? null) === $token) {
                        $existingIndex = $index;
                        break;
                    }
                }

                $deviceEntry = [
                    'token'           => $token,
                    'device_name'     => $deviceName,
                    'device_type'     => $type,
                    'device_platform' => $platform,
                    'device_browser'  => $browser,
                    'ip_address'      => $request->ip(),
                    'user_agent'      => $userAgent,
                    'last_used_at'    => $now->toDateTimeString(),
                ];

                if ($existingIndex !== null) {
                    $devices[$existingIndex] = array_merge($devices[$existingIndex], $deviceEntry);
                } else {
                    $deviceEntry['created_at'] = $now->toDateTimeString();
                    $devices[] = $deviceEntry;
                    if (count($devices) > 10) {
                        $devices = array_slice($devices, -10);
                    }
                }

                $user->devices = json_encode($devices);
                $user->save();

                \Log::info('Device tracked in user metadata', ['user_id' => $user->id]);
            } catch (\Throwable $e) {
                \Log::warning('Failed to track device in metadata: ' . $e->getMessage(), [
                    'user_id' => $user->id,
                ]);
            }

            \Log::info('Device tracked for user', [
                'user_id'     => $user->id,
                'user_email'  => $user->email,
                'device_name' => $deviceName,
                'ip'          => $request->ip(),
                'session_id'  => $sessionId,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Failed to track device: ' . $e->getMessage(), [
                'user_id' => $user->id ?? null,
            ]);
        }
    }

    /**
     * Classify a device as Desktop / Mobile / Tablet.
     */
    protected function getDeviceType(?string $userAgent): string
    {
        if (!$userAgent) return 'Unknown';
        $ua = strtolower($userAgent);
        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) return 'Tablet';
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) return 'Mobile';
        return 'Desktop';
    }

    /**
     * Extract the operating system family from a UA string.
     */
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

    /**
     * Extract the browser family from a UA string.
     */
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

    /**
     * Human-readable device label, e.g. "Microsoft Edge on Windows".
     */
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

    /**
     * Best-effort device model extraction (only meaningful on mobile).
     */
    protected function getDeviceModel(?string $userAgent): ?string
    {
        if (!$userAgent) return null;
        $ua = $userAgent;

        if (preg_match('/iPhone/', $ua)) return 'iPhone';
        if (preg_match('/iPad/', $ua)) return 'iPad';

        // Android model — "Android X; <model>" or "Android X; <locale>; <model>"
        if (preg_match('/Android[^;]*;\s*([^;)]+?)(?:\s+Build|\))/i', $ua, $m)) {
            $model = trim($m[1]);
            if (!preg_match('/^[a-z]{2}[-_][a-z]{2}$/i', $model)) {
                return $model;
            }
        }
        return null;
    }

    /**
     * Best-effort OS version extraction.
     */
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

    /* ============================================================
     | LOGIN FINALIZATION
     | ============================================================ */

    protected function performLogin(User $user, bool $remember): bool
    {
        try {
            Auth::login($user, $remember);

            if (Auth::check()) {
                \Log::info('User logged in successfully', [
                    'user_id' => $user->id,
                    'session_id' => session()->getId(),
                    'remember' => $remember
                ]);
                return true;
            }

            \Log::warning('Login attempt failed after Auth::login', [
                'user_id' => $user->id,
                'session_id' => session()->getId()
            ]);
            return false;
        } catch (\Exception $e) {
            \Log::error('Login error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    protected function finalizeLogin(Request $request, User $user): void
    {
        try {
            $request->session()->regenerate();
            $request->session()->regenerateToken();

            $user->updateLastActivity();

            $this->activityService->recordLogin($user, $request);

            $user->increment('login_count');
            $user->last_login_at = now();
            $user->last_login_ip = $request->ip();
            $user->save();

            \Log::info('Login finalized', [
                'user_id' => $user->id,
                'session_id' => session()->getId(),
                'ip' => $request->ip()
            ]);
        } catch (\Exception $e) {
            \Log::error('Error finalizing login: ' . $e->getMessage(), [
                'user_id' => $user->id
            ]);
        }
    }

    /* ============================================================
     | MOBILE LOGIN — API, always returns JSON
     | ------------------------------------------------------------
     | Mobile clients get an explicit `billing` block in the response
     | so the app can route to a billing screen itself.
     * ============================================================ */

    public function mobileLogin(Request $request)
    {
        if (app()->environment('local') || config('app.debug')) {
            \Log::info('Mobile Login Request', [
                'login' => $request->input('login'),
                'device_name' => $request->input('device_name'),
                'ip' => $request->ip(),
                'session_id' => session()->getId()
            ]);
        }

        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
            'device_name' => 'required|string|max:255',
            'device_token' => 'nullable|string',
            'two_factor_code' => 'nullable|string|size:6',
        ]);

        if ($this->hasTooManyLoginAttempts($request)) {
            return response()->json([
                'error' => 'too_many_attempts',
                'message' => trans('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($this->throttleKey($request)),
                    'minutes' => ceil(RateLimiter::availableIn($this->throttleKey($request)) / 60),
                ]),
                'retry_after' => RateLimiter::availableIn($this->throttleKey($request))
            ], 429);
        }

        $credentials = $this->getCredentials($request);
        $user = $this->findUserByCredentials($credentials);

        if (!$user) {
            $this->incrementLoginAttempts($request);
            return response()->json([
                'error' => 'invalid_credentials',
                'message' => 'The provided credentials are incorrect.'
            ], 401);
        }

        if ($user->isArchived()) {
            return $this->archivedAccountService->handleMobileArchivedLogin($user);
        }

        if ($user->trashed()) {
            return response()->json([
                'error' => 'account_deactivated',
                'message' => 'This account has been deactivated. Please contact administrator.'
            ], 403);
        }

        if (!Hash::check($credentials['password'], $user->password)) {
            $this->incrementLoginAttempts($request);
            return response()->json([
                'error' => 'invalid_credentials',
                'message' => 'The provided credentials are incorrect.'
            ], 401);
        }

        if ($this->twoFactorAuthService->isEnabled($user)) {
            if (!$request->has('two_factor_code')) {
                return response()->json([
                    'error' => 'two_factor_required',
                    'message' => 'Two-factor authentication code is required.',
                    'requires_2fa' => true
                ], 401);
            }

            if (!$this->twoFactorAuthService->verify($user, $request->two_factor_code)) {
                return response()->json([
                    'error' => 'invalid_2fa_code',
                    'message' => 'Invalid two-factor authentication code.'
                ], 401);
            }
        }

        $this->clearLoginAttempts($request);

        $user->updateLastActivity();
        $this->activityService->recordLogin($user, $request, 'mobile');

        $user->increment('login_count');
        $user->last_login_at = now();
        $user->last_login_ip = $request->ip();
        $user->save();

        $this->trackDevice($request, $user);

        try {
            $user->tokens()->where('name', '!=', $request->device_name)
                ->orderBy('last_used_at', 'desc')
                ->skip(5)
                ->take(PHP_INT_MAX)
                ->delete();

            if ($request->device_token) {
                $this->storeDeviceToken($user, $request->device_name, $request->device_token);
            }

            $token = $user->createToken($request->device_name, ['*'], now()->addMonths(6))->plainTextToken;

            if (method_exists($user, 'roles')) {
                $user->load(['roles', 'permissions']);
            }

            \Log::info('Mobile login successful', [
                'user_id' => $user->id,
                'device_name' => $request->device_name,
                'ip' => $request->ip()
            ]);

            // ✅ BILLING: expose state so the mobile app can route itself
            $billingPayload = $this->mobileBillingPayload($user);

            return response()->json([
                'success' => true,
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => now()->addMonths(6)->timestamp,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'type' => $user->type,
                    'role_name' => $user->getRoleName(),
                    'avatar' => $user->avatar_url ?? null,
                    'email_verified_at' => $user->email_verified_at,
                    'two_factor_enabled' => $user->two_factor_enabled ?? false,
                ],
                'requires_verification' => method_exists($user, 'hasVerifiedEmail') && !$user->hasVerifiedEmail(),
                'billing' => $billingPayload,
            ]);
        } catch (\Exception $e) {
            \Log::error('Mobile login token generation error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'token_generation_failed',
                'message' => 'Failed to generate access token. Please try again.'
            ], 500);
        }
    }

    /**
     * ✅ BILLING: mobile payload for the login response.
     */
    protected function mobileBillingPayload(User $user): array
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
            // Landlord/tenant: never restricted

            return [
                'state'          => $developer->billing_state,
                'restricted'     => $restricted,
                'pay_url'        => $payUrl,
                'days_until_due' => $developer->daysUntilBillingDue(),
            ];
        } catch (\Throwable $e) {
            return ['state' => null, 'restricted' => false, 'pay_url' => null];
        }
    }

    /* ============================================================
     | DEVICE TOKEN HELPERS
     | ============================================================ */

    /**
     * Store (or refresh) a device's push/API token.
     *
     * ✅ FIXED (2026-10-01): if no token is supplied by the caller, we
     * generate a deterministic fallback so the NOT NULL `token` column
     * never causes a strict-mode insert failure.
     */
    protected function storeDeviceToken(User $user, string $deviceName, string $token): void
    {
        if (!Schema::hasTable('device_tokens')) {
            // Legacy JSON fallback
            $devices = json_decode($user->devices ?? '[]', true);
            if (!is_array($devices)) {
                $devices = [];
            }

            $found = false;
            foreach ($devices as &$device) {
                if (($device['device_name'] ?? $device['name'] ?? null) === $deviceName) {
                    $device['token']        = $token;
                    $device['last_used_at'] = now()->toDateTimeString();
                    $found = true;
                    break;
                }
            }
            unset($device);

            if (!$found) {
                $devices[] = [
                    'device_name'  => $deviceName,
                    'name'         => $deviceName, // legacy alias
                    'token'        => $token,
                    'last_used_at' => now()->toDateTimeString(),
                    'created_at'   => now()->toDateTimeString(),
                ];
            }

            $user->devices = json_encode($devices);
            $user->save();
            return;
        }

        // Normalize the push token so it's always non-empty.
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
    }

    protected function removeDeviceToken(User $user, string $deviceName): void
    {
        if (Schema::hasTable('device_tokens')) {
            DB::table('device_tokens')
                ->where('user_id', $user->id)
                ->where('device_name', $deviceName)
                ->delete();
        } else {
            $devices = json_decode($user->devices ?? '[]', true);
            if (!is_array($devices)) {
                $devices = [];
            }
            $devices = array_filter($devices, function ($device) use ($deviceName) {
                $name = $device['name'] ?? $device['device_name'] ?? null;
                return $name !== $deviceName;
            });
            $user->devices = json_encode(array_values($devices));
            $user->save();
        }
    }

    public function mobileLogout(Request $request)
    {
        $user = $request->user();
        $deviceName = $request->input('device_name', $user->currentAccessToken()->name);

        $this->activityService->recordLogout($user, $request, 'mobile');

        $user->currentAccessToken()->delete();

        if ($deviceName) {
            $this->removeDeviceToken($user, $deviceName);
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }

    public function refreshToken(Request $request)
    {
        $request->validate([
            'device_name' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $user->currentAccessToken()->delete();

        $token = $user->createToken($request->device_name, ['*'], now()->addMonths(6))->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => now()->addMonths(6)->timestamp,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'type' => $user->type,
                'role_name' => $user->getRoleName(),
            ],
            'billing' => $this->mobileBillingPayload($user),
        ]);
    }

    /* ============================================================
     | SOCIAL LOGIN
     | ============================================================ */

    public function redirectToProvider(string $provider)
    {
        $allowedProviders = ['google', 'microsoft', 'facebook', 'apple', 'github'];

        if (!in_array($provider, $allowedProviders)) {
            return redirect()->route('login')
                ->withErrors(['login' => 'Unsupported social login provider.']);
        }

        $this->socialLoginService->validateProviderConfig($provider);

        \Log::info("Initiating {$provider} login", [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);

        $scopes = config("services.{$provider}.scopes", []);

        if (empty($scopes)) {
            return Socialite::driver($provider)->redirect();
        }

        return Socialite::driver($provider)
            ->scopes($scopes)
            ->redirect();
    }

    public function handleProviderCallback(string $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();

            \Log::info("{$provider} callback received", [
                'email' => $socialUser->getEmail(),
                'id' => $socialUser->getId()
            ]);

            $result = $this->socialLoginService->handleLogin($provider, $socialUser);

            if (!$result['success']) {
                return redirect()->route('login')
                    ->with('info', $result['message'])
                    ->with('social_email', $result['email'] ?? null)
                    ->with('social_name', $result['name'] ?? null)
                    ->with('social_provider', $provider);
            }

            if ($result['user']->isArchived()) {
                return $this->archivedAccountService->handleArchivedLogin($result['user'], request());
            }

            Auth::login($result['user'], true);

            $this->trackDevice(request(), $result['user']);
            $this->activityService->recordLogin($result['user'], request(), "social_{$provider}");
            $result['user']->updateLastActivity();

            // ✅ BILLING: route super admins through the billing check too
            return $this->postLoginRedirect($result['user'], request())
                ->with('success', "Welcome back! You have successfully logged in with {$provider}.");

        } catch (\Exception $e) {
            \Log::error("{$provider} login error: " . $e->getMessage());
            \Log::error($e->getTraceAsString());

            return $this->socialLoginService->handleCallbackError($provider, $e);
        }
    }

    public function linkSocialAccount(Request $request, string $provider)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'error' => 'invalid_credentials',
                'message' => 'Invalid credentials. Please try again.'
            ], 401);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
            $this->socialLoginService->linkAccount(Auth::user(), $provider, $socialUser);

            return response()->json([
                'success' => true,
                'message' => "{$provider} account linked successfully."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'link_failed',
                'message' => 'Failed to link social account. Please try again.'
            ], 500);
        }
    }

    public function unlinkSocialAccount(string $provider)
    {
        $user = Auth::user();

        if ($this->socialLoginService->unlinkAccount($user, $provider)) {
            return back()->with('success', "{$provider} account unlinked successfully.");
        }

        return back()->withErrors(['error' => 'Failed to unlink social account.']);
    }

    /* ============================================================
     | TWO FACTOR AUTH
     | ============================================================ */

    public function showTwoFactorForm()
    {
        if (!session('2fa:user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-verify');
    }

    public function verifyTwoFactor(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $userId = session('2fa:user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login');
        }

        if ($this->twoFactorAuthService->verify($user, $request->code)) {
            session()->forget('2fa:user_id');

            Auth::login($user, session('2fa:remember', false));

            $this->trackDevice($request, $user);

            session()->forget('2fa:remember');

            $this->activityService->recordTwoFactorSuccess($user, $request);

            // ✅ BILLING: 2FA-verified super admins still get routed correctly
            return $this->postLoginRedirect($user, $request)
                ->with('success', 'Two-factor authentication verified successfully.');
        }

        $this->activityService->recordTwoFactorFailure($user, $request);

        return back()->withErrors(['code' => 'Invalid verification code. Please try again.']);
    }

    public function resendTwoFactorCode(Request $request)
    {
        $userId = session('2fa:user_id');
        if (!$userId) {
            return response()->json(['error' => 'Session expired'], 400);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $sent = $this->twoFactorAuthService->sendCode($user);

        if ($sent) {
            return response()->json(['message' => 'Verification code resent successfully.']);
        }

        return response()->json(['error' => 'Failed to resend code. Please try again.'], 500);
    }

    public function enableTwoFactor(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'verification_code' => 'required|string|size:6',
        ]);

        if ($this->twoFactorAuthService->verify($user, $request->verification_code)) {
            $this->twoFactorAuthService->enable($user);
            $backupCodes = $this->twoFactorAuthService->generateBackupCodes($user);

            return response()->json([
                'success' => true,
                'message' => 'Two-factor authentication enabled.',
                'backup_codes' => $backupCodes
            ]);
        }

        return response()->json([
            'error' => 'Invalid verification code.'
        ], 400);
    }

    public function disableTwoFactor(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'password' => 'required|string',
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid password.'], 400);
        }

        $this->twoFactorAuthService->disable($user);

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication disabled.'
        ]);
    }

    /* ============================================================
     | PASSWORD RESET
     | ============================================================ */

    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
        ]);

        $throttleKey = 'password-reset:' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'login' => "Too many password reset attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $result = $this->passwordResetService->sendResetLink($request->login);

        RateLimiter::hit($throttleKey);

        if (!$result['success']) {
            return back()->with('status', 'If an account exists with this email/phone, you will receive a password reset link.');
        }

        RateLimiter::clear($throttleKey);

        return back()->with('status', 'We have emailed your password reset link!');
    }

    public function showResetForm(Request $request, string $token)
    {
        $email = $request->query('email');

        if ($email) {
            $email = urldecode($email);
        }

        if (!$email) {
            $email = old('email');
        }

        if (!$this->passwordResetService->validateToken($email, $token)) {
            return redirect()->route('password.request')
                ->withErrors(['email' => 'Invalid or expired password reset token.']);
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $result = $this->passwordResetService->resetPassword(
            $request->email,
            $request->token,
            $request->password
        );

        if (!$result['success']) {
            return back()->withErrors(['email' => $result['message']]);
        }

        return redirect()->route('login')
            ->with('status', 'Password reset successful! You can now login with your new password.');
    }

    public function resendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $result = $this->passwordResetService->sendResetLink($request->email);

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 404);
        }

        return response()->json(['message' => 'Reset link sent successfully']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'error' => 'current_password_incorrect',
                'message' => 'Current password is incorrect.'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
            'temp_password' => false,
            'password_changed_at' => now(),
        ]);

        if ($request->boolean('logout_other_devices')) {
            Auth::logoutOtherDevices($request->current_password);
        }

        $this->activityService->recordPasswordChange($user, $request);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.'
        ]);
    }

    /* ============================================================
     | TWO FACTOR SETUP
     | ============================================================ */

    public function showTwoFactorSetup()
    {
        $user = Auth::user();

        if (!$user->two_factor_secret) {
            $secret = $this->twoFactorAuthService->generateSecret();
            session(['2fa:setup_secret' => $secret]);
        } else {
            $secret = $user->two_factor_secret;
        }

        $qrCode = $this->twoFactorAuthService->getQRCode($user, $secret);

        return view('auth.2fa-setup', [
            'secret' => $secret,
            'qr_code' => $qrCode,
            'recovery_codes' => session('2fa:recovery_codes')
        ]);
    }

    public function verifyTwoFactorSetup(Request $request)
    {
        $request->validate([
            'verification_code' => 'required|string|size:6',
        ]);

        $user = Auth::user();
        $secret = session('2fa:setup_secret');

        if (!$secret) {
            return redirect()->route('2fa.setup')
                ->withErrors(['error' => 'Setup session expired. Please try again.']);
        }

        if ($this->twoFactorAuthService->verifyCode($secret, $request->verification_code)) {
            $this->twoFactorAuthService->enableWithSecret($user, $secret);

            $backupCodes = $this->twoFactorAuthService->generateBackupCodes($user);

            session(['2fa:recovery_codes' => $backupCodes]);
            session()->forget('2fa:setup_secret');

            return redirect()->route('2fa.recovery-codes')
                ->with('success', 'Two-factor authentication has been enabled. Save your recovery codes.');
        }

        return redirect()->route('2fa.setup')
            ->withErrors(['verification_code' => 'Invalid verification code. Please try again.']);
    }

    public function showRecoveryCodes()
    {
        $user = Auth::user();

        $recoveryCodes = session('2fa:recovery_codes');

        if (!$recoveryCodes && $user->two_factor_backup_codes) {
            $recoveryCodes = json_decode($user->two_factor_backup_codes, true);
        }

        if (!$recoveryCodes) {
            return redirect()->route('2fa.setup')
                ->withErrors(['error' => 'No recovery codes found. Please set up 2FA first.']);
        }

        return view('auth.2fa-recovery-codes', [
            'recovery_codes' => $recoveryCodes
        ]);
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'password' => 'required|string',
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid password.'], 400);
        }

        $backupCodes = $this->twoFactorAuthService->regenerateBackupCodes($user);

        return response()->json([
            'success' => true,
            'message' => 'Recovery codes regenerated successfully.',
            'backup_codes' => $backupCodes
        ]);
    }

    /* ============================================================
     | SESSION MANAGEMENT
     | ============================================================ */

    public function checkSession(Request $request)
    {
        return response()->json([
            'authenticated' => Auth::check(),
            'user' => Auth::check() ? [
                'id' => Auth::id(),
                'name' => Auth::user()->name,
                'email' => Auth::user()->email
            ] : null,
            'session_id' => session()->getId(),
            'expires_in' => config('session.lifetime') * 60
        ]);
    }

    public function invalidateSession(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Session invalidated successfully.'
        ]);
    }

    public function continueSession(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        return redirect()->intended($this->redirectTo);
    }

    public function getActiveSessions(Request $request)
    {
        $user = $request->user();

        $sessions = [];

        if (config('session.driver') === 'database') {
            $sessions = DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('last_activity', '>', now()->subMinutes(config('session.lifetime')))
                ->orderBy('last_activity', 'desc')
                ->get()
                ->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'ip_address' => $session->ip_address,
                        'user_agent' => $session->user_agent,
                        'last_activity' => Carbon::createFromTimestamp($session->last_activity),
                        'is_current' => $session->id === session()->getId()
                    ];
                });
        }

        return response()->json([
            'sessions' => $sessions,
            'total' => count($sessions)
        ]);
    }

    public function revokeSession(Request $request, string $sessionId)
    {
        $user = $request->user();

        if (config('session.driver') === 'database') {
            if ($sessionId === session()->getId()) {
                return response()->json([
                    'error' => 'Cannot revoke current session. Use logout instead.'
                ], 400);
            }

            DB::table('sessions')
                ->where('id', $sessionId)
                ->where('user_id', $user->id)
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Session revoked successfully.'
        ]);
    }

    public function logoutAllDevices(Request $request)
    {
        $user = $request->user();

        Auth::logoutOtherDevices($request->input('password', ''));

        if (method_exists($user, 'tokens')) {
            $user->tokens()->where('id', '!=', $user->currentAccessToken()->id ?? 0)->delete();
        }

        if (config('session.driver') === 'database') {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', session()->getId())
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all other devices.'
        ]);
    }

    /* ============================================================
     | DEVICE MANAGEMENT
     | ============================================================ */

    public function getUserDevices(Request $request)
    {
        $user = $request->user();
        $devices = [];

        if (Schema::hasTable('device_tokens')) {
            $devices = DB::table('device_tokens')
                ->where('user_id', $user->id)
                ->orderBy('last_used_at', 'desc')
                ->get()
                ->toArray();
        } elseif (Schema::hasTable('user_devices')) {
            $devices = DB::table('user_devices')
                ->where('user_id', $user->id)
                ->orderBy('last_used_at', 'desc')
                ->get()
                ->toArray();
        } else {
            $devices = json_decode($user->devices ?? '[]', true);
        }

        return response()->json([
            'devices' => $devices,
            'total' => count($devices)
        ]);
    }

    public function removeDevice(Request $request, $deviceId)
    {
        $user = $request->user();

        if (Schema::hasTable('device_tokens')) {
            DB::table('device_tokens')
                ->where('user_id', $user->id)
                ->where('id', $deviceId)
                ->delete();
        } elseif (Schema::hasTable('user_devices')) {
            DB::table('user_devices')
                ->where('user_id', $user->id)
                ->where('id', $deviceId)
                ->delete();
        } else {
            $devices = json_decode($user->devices ?? '[]', true);
            if (!is_array($devices)) {
                $devices = [];
            }
            $devices = array_filter($devices, function ($device) use ($deviceId) {
                return ($device['id'] ?? $device['device_name'] ?? null) !== $deviceId;
            });
            $user->devices = json_encode(array_values($devices));
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Device removed successfully.'
        ]);
    }

    public function trustDevice(Request $request)
    {
        $user = $request->user();
        $deviceFingerprint = $this->generateDeviceFingerprint($request);

        if (Schema::hasTable('trusted_devices')) {
            DB::table('trusted_devices')->updateOrInsert(
                [
                    'user_id' => $user->id,
                    'device_fingerprint' => $deviceFingerprint
                ],
                [
                    'expires_at' => now()->addDays(30),
                    'updated_at' => now()
                ]
            );
        } else {
            $trustedDevices = json_decode($user->trusted_devices ?? '[]', true);
            if (!is_array($trustedDevices)) {
                $trustedDevices = [];
            }
            $trustedDevices[$deviceFingerprint] = now()->addDays(30)->toDateTimeString();
            $user->trusted_devices = json_encode($trustedDevices);
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Device trusted for 30 days.'
        ]);
    }

    public function getCurrentDeviceInfo(Request $request)
    {
        $ua = $request->userAgent();

        return response()->json([
            'device_name' => $this->getDeviceName($ua),
            'device_type' => $this->getDeviceType($ua),
            'device_platform' => $this->getDevicePlatform($ua),
            'device_browser' => $this->getDeviceBrowser($ua),
            'device_model' => $this->getDeviceModel($ua),
            'os_version' => $this->getOsVersion($ua),
            'ip_address' => $request->ip(),
            'user_agent' => $ua,
            'device_fingerprint' => $this->generateDeviceFingerprint($request)
        ]);
    }

    protected function generateDeviceFingerprint(Request $request): string
    {
        $data = implode('|', [
            $request->userAgent(),
            $request->ip(),
            $request->header('accept-language', ''),
            $request->header('accept-encoding', '')
        ]);

        return hash('sha256', $data);
    }

    /* ============================================================
     | PASSWORD EXPIRY
     | ============================================================ */

    public function checkPasswordExpiry(Request $request)
    {
        $user = $request->user();

        if (!$user->password_changed_at) {
            return response()->json([
                'expiring' => false,
                'message' => 'No password expiry information available.'
            ]);
        }

        $expiryDays = config('auth.password_expiry_days', 90);
        $passwordAge = now()->diffInDays($user->password_changed_at);
        $daysRemaining = $expiryDays - $passwordAge;

        return response()->json([
            'expiring' => $daysRemaining <= 7,
            'days_remaining' => $daysRemaining,
            'password_age_days' => $passwordAge,
            'expiry_days' => $expiryDays,
            'should_change' => $daysRemaining <= 0
        ]);
    }

    /* ============================================================
     | MOBILE 2FA
     | ============================================================ */

    public function mobileVerifyTwoFactor(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'code' => 'required|string|size:6',
        ]);

        $user = User::find($request->user_id);

        if ($this->twoFactorAuthService->verify($user, $request->code)) {
            $token = $user->createToken('2fa_verified', ['*'], now()->addMinutes(5))->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => '2FA verified successfully.',
                'temporary_token' => $token
            ]);
        }

        return response()->json([
            'error' => 'invalid_code',
            'message' => 'Invalid verification code.'
        ], 401);
    }

    public function mobileResendTwoFactor(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::find($request->user_id);

        $sent = $this->twoFactorAuthService->sendCode($user);

        if ($sent) {
            return response()->json([
                'success' => true,
                'message' => 'Verification code sent.'
            ]);
        }

        return response()->json([
            'error' => 'send_failed',
            'message' => 'Failed to send verification code.'
        ], 500);
    }

    /* ============================================================
     | MOBILE DEVICE MANAGEMENT
     | ============================================================ */

    public function getMobileDevices(Request $request)
    {
        $user = $request->user();

        $devices = $user->tokens()
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'last_used_at' => $token->last_used_at,
                    'created_at' => $token->created_at,
                    'is_current' => $token->id === $token->currentAccessToken()?->id
                ];
            });

        return response()->json([
            'devices' => $devices,
            'total' => $devices->count()
        ]);
    }

    public function revokeMobileDevice(Request $request, $deviceId)
    {
        $user = $request->user();

        $token = $user->tokens()->find($deviceId);

        if (!$token) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        $token->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device revoked successfully.'
        ]);
    }

    public function registerPushToken(Request $request)
    {
        $request->validate([
            'device_name' => 'required|string',
            'push_token' => 'required|string',
            'platform' => 'required|in:ios,android'
        ]);

        $user = $request->user();

        $this->storeDeviceToken($user, $request->device_name, $request->push_token);

        if (Schema::hasTable('device_tokens')) {
            $update = [];
            if (Schema::hasColumn('device_tokens', 'platform')) {
                $update['platform'] = $request->platform;
            }
            if (Schema::hasColumn('device_tokens', 'updated_at')) {
                $update['updated_at'] = now();
            }

            if (!empty($update)) {
                DB::table('device_tokens')
                    ->where('user_id', $user->id)
                    ->where('device_name', $request->device_name)
                    ->update($update);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Push token registered successfully.'
        ]);
    }

    /* ============================================================
     | IMPERSONATION
     | ============================================================ */

    public function startImpersonation(Request $request, $userId)
    {
        $originalUser = Auth::user();
        $targetUser = User::findOrFail($userId);

        session(['impersonate.original_id' => $originalUser->id]);
        session(['impersonate.original_type' => $originalUser->type]);

        Auth::login($targetUser);

        $this->activityService->recordImpersonationStart($originalUser, $targetUser, $request);

        return redirect()->route('dashboard')
            ->with('warning', "You are currently impersonating {$targetUser->name}. Click the stop button to return to your account.");
    }

    public function stopImpersonation(Request $request)
    {
        $originalUserId = session('impersonate.original_id');

        if (!$originalUserId) {
            return redirect()->route('dashboard')
                ->with('error', 'No impersonation session found.');
        }

        $originalUser = User::find($originalUserId);

        if (!$originalUser) {
            Auth::logout();
            return redirect()->route('login')
                ->with('error', 'Original user not found. Please login again.');
        }

        $this->activityService->recordImpersonationStop($originalUser, Auth::user(), $request);

        Auth::login($originalUser);

        session()->forget('impersonate.original_id');
        session()->forget('impersonate.original_type');

        return redirect()->route('dashboard')
            ->with('success', 'You are no longer impersonating another user.');
    }

    public function getImpersonationStatus(Request $request)
    {
        return response()->json([
            'is_impersonating' => session()->has('impersonate.original_id'),
            'original_user_id' => session('impersonate.original_id'),
            'original_user_type' => session('impersonate.original_type')
        ]);
    }

    /* ============================================================
     | ACTIVITY LOGGING
     | ============================================================ */

    public function getLoginHistory(Request $request)
    {
        $user = $request->user();

        $activities = [];

        if (Schema::hasTable('user_activities')) {
            $activities = DB::table('user_activities')
                ->where('user_id', $user->id)
                ->where('action', 'login')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
        }

        return response()->json([
            'activities' => $activities,
            'total' => count($activities)
        ]);
    }

    public function getActivityDetails(Request $request, $activityId)
    {
        $user = $request->user();

        $activity = null;

        if (Schema::hasTable('user_activities')) {
            $activity = DB::table('user_activities')
                ->where('user_id', $user->id)
                ->where('id', $activityId)
                ->first();
        }

        if (!$activity) {
            return response()->json(['error' => 'Activity not found'], 404);
        }

        return response()->json(['activity' => $activity]);
    }

    public function getSuspiciousActivity(Request $request)
    {
        $user = $request->user();

        $suspicious = [];

        if (Schema::hasTable('user_activities')) {
            $suspicious = DB::table('user_activities')
                ->where('user_id', $user->id)
                ->where('is_suspicious', true)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
        }

        return response()->json([
            'suspicious_activities' => $suspicious,
            'total' => count($suspicious)
        ]);
    }

    public function clearActivityHistory(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'password' => 'required|string'
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid password'], 400);
        }

        if (Schema::hasTable('user_activities')) {
            DB::table('user_activities')
                ->where('user_id', $user->id)
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Activity history cleared successfully.'
        ]);
    }

    /* ============================================================
     | ACCOUNT RECOVERY
     | ============================================================ */

    public function showRecoveryRequestForm()
    {
        return view('auth.account-recovery-request');
    }

    public function sendRecoveryRequest(Request $request)
    {
        $request->validate([
            'email' => 'required_without:phone|email',
            'phone' => 'required_without:email|string'
        ]);

        $user = null;

        if ($request->email) {
            $user = User::where('email', $request->email)->first();
        } elseif ($request->phone) {
            $phone = $this->phoneService->normalize($request->phone);
            $user = User::where('phone', $phone)->first();
        }

        if (!$user) {
            return back()->with('status', 'If an account exists, you will receive recovery instructions.');
        }

        $token = Str::random(64);

        DB::table('account_recovery_requests')->insert([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addHours(24),
            'created_at' => now()
        ]);

        if ($user->email) {
            Mail::send('emails.account-recovery', ['user' => $user, 'token' => $token], function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Account Recovery Request');
            });
        }

        return back()->with('status', 'Recovery instructions have been sent to your email/phone.');
    }

    public function showRecoveryVerification($token)
    {
        $recoveryRequest = DB::table('account_recovery_requests')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$recoveryRequest) {
            return redirect()->route('recovery.request')
                ->withErrors(['error' => 'Invalid or expired recovery token.']);
        }

        return view('auth.account-recovery-verify', [
            'token' => $token,
            'user_id' => $recoveryRequest->user_id
        ]);
    }

    public function verifyRecoveryIdentity(Request $request, $token)
    {
        $request->validate([
            'identity_answer' => 'required|string'
        ]);

        $recoveryRequest = DB::table('account_recovery_requests')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$recoveryRequest) {
            return response()->json(['error' => 'Invalid or expired recovery token.'], 400);
        }

        DB::table('account_recovery_requests')
            ->where('token', $token)
            ->update(['verified_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Identity verified. You can now reset your password.'
        ]);
    }

    public function completeRecovery(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'password' => 'required|min:8|confirmed'
        ]);

        $recoveryRequest = DB::table('account_recovery_requests')
            ->where('token', $request->token)
            ->where('expires_at', '>', now())
            ->whereNotNull('verified_at')
            ->first();

        if (!$recoveryRequest) {
            return response()->json(['error' => 'Invalid or expired recovery token.'], 400);
        }

        $user = User::find($recoveryRequest->user_id);

        $user->update([
            'password' => Hash::make($request->password),
            'recovery_completed_at' => now()
        ]);

        DB::table('account_recovery_requests')->where('token', $request->token)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password recovered successfully. You can now login.'
        ]);
    }

    /* ============================================================
     | WEBHOOKS
     | ============================================================ */

    public function handleGoogleWebhook(Request $request)
    {
        \Log::info('Google webhook received', $request->all());
        return response()->json(['status' => 'ok']);
    }

    public function handleMicrosoftWebhook(Request $request)
    {
        \Log::info('Microsoft webhook received', $request->all());
        return response()->json(['status' => 'ok']);
    }

    public function handleSmsDeliveryStatus(Request $request)
    {
        \Log::info('SMS delivery status', $request->all());
        return response()->json(['status' => 'ok']);
    }

    public function handleEmailBounce(Request $request)
    {
        \Log::info('Email bounce received', $request->all());
        return response()->json(['status' => 'ok']);
    }

    /* ============================================================
     | EMAIL CHECK
     | ============================================================ */

    public function checkEmailExists(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $exists = User::where('email', $request->email)->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Email found' : 'Email not found'
        ]);
    }

    public function checkPhoneExists(Request $request)
    {
        $request->validate([
            'phone' => 'required|string'
        ]);

        $phone = $this->phoneService->normalize($request->phone);
        $exists = User::where('phone', $phone)->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Phone number found' : 'Phone number not found'
        ]);
    }

    public function resendVerificationEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.'
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification email sent.'
        ]);
    }

    /* ============================================================
     | API TOKEN MANAGEMENT
     | ============================================================ */

    public function listApiTokens(Request $request)
    {
        $user = $request->user();

        $tokens = $user->tokens()
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'last_used_at' => $token->last_used_at,
                    'created_at' => $token->created_at,
                    'expires_at' => $token->expires_at
                ];
            });

        return response()->json(['tokens' => $tokens]);
    }

    public function createApiToken(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'nullable|array',
            'expires_in_days' => 'nullable|integer|min:1|max:365'
        ]);

        $user = $request->user();

        $expiresAt = $request->expires_in_days
            ? now()->addDays($request->expires_in_days)
            : now()->addMonths(6);

        $abilities = $request->abilities ?? ['*'];

        $token = $user->createToken($request->name, $abilities, $expiresAt);

        return response()->json([
            'success' => true,
            'token' => $token->plainTextToken,
            'token_id' => $token->accessToken->id,
            'expires_at' => $expiresAt
        ]);
    }

    public function revokeApiToken(Request $request, $tokenId)
    {
        $user = $request->user();

        $token = $user->tokens()->find($tokenId);

        if (!$token) {
            return response()->json(['error' => 'Token not found'], 404);
        }

        $token->delete();

        return response()->json([
            'success' => true,
            'message' => 'Token revoked successfully.'
        ]);
    }

    public function updateTokenPermissions(Request $request, $tokenId)
    {
        $request->validate([
            'abilities' => 'required|array'
        ]);

        $user = $request->user();

        $token = $user->tokens()->find($tokenId);

        if (!$token) {
            return response()->json(['error' => 'Token not found'], 404);
        }

        $token->abilities = $request->abilities;
        $token->save();

        return response()->json([
            'success' => true,
            'message' => 'Token permissions updated successfully.'
        ]);
    }

    /* ============================================================
     | EMAIL VERIFICATION
     | ============================================================ */

    public function verifyEmail($id, $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Invalid verification link.']);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('login')
                ->with('status', 'Email already verified.');
        }

        $user->markEmailAsVerified();

        return redirect()->route('login')
            ->with('status', 'Email verified successfully! You can now login.');
    }

    public function sendVerificationEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.'
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification email sent.'
        ]);
    }

    /* ============================================================
     | LEGACY SOCIAL LOGIN
     | ============================================================ */

    public function redirectToGoogle()
    {
        return $this->redirectToProvider('google');
    }

    public function handleGoogleCallback()
    {
        return $this->handleProviderCallback('google');
    }

    public function redirectToMicrosoft()
    {
        return $this->redirectToProvider('microsoft');
    }

    public function handleMicrosoftCallback()
    {
        return $this->handleProviderCallback('microsoft');
    }

    /* ============================================================
     | HELPER METHODS
     | ============================================================ */

    protected function getCredentials(Request $request): array
    {
        $login = $request->input('login');

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return [
                'email' => $login,
                'password' => $request->input('password')
            ];
        }

        $phone = $this->phoneService->normalize($login);

        return [
            'phone' => $phone,
            'password' => $request->input('password')
        ];
    }

    protected function findUserByCredentials(array $credentials): ?User
    {
        if (isset($credentials['email'])) {
            return User::where('email', $credentials['email'])->first();
        }

        $phoneFormats = $this->phoneService->getAllFormats($credentials['phone']);

        return User::whereIn('phone', $phoneFormats)->first();
    }

    protected function attemptLoginWithPhoneVariations(array $credentials, bool $remember = false): bool
    {
        if (isset($credentials['email'])) {
            return Auth::attempt($credentials, $remember);
        }

        $phoneFormats = $this->phoneService->getAllFormats($credentials['phone']);

        foreach ($phoneFormats as $format) {
            if (Auth::attempt(['phone' => $format, 'password' => $credentials['password']], $remember)) {
                return true;
            }
        }

        return false;
    }

    protected function hasTooManyLoginAttempts(Request $request): bool
    {
        return RateLimiter::tooManyAttempts($this->throttleKey($request), $this->maxAttempts());
    }

    protected function incrementLoginAttempts(Request $request): void
    {
        RateLimiter::hit($this->throttleKey($request), $this->decayMinutes() * 60);
    }

    protected function clearLoginAttempts(Request $request): void
    {
        RateLimiter::clear($this->throttleKey($request));
    }

    protected function throttleKey(Request $request): string
    {
        $login = $request->input('login');
        return Str::transliterate(Str::lower($login) . '|' . $request->ip());
    }

    protected function decayMinutes(): int
    {
        return $this->decayMinutes;
    }

    protected function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    protected function sendLockoutResponse(Request $request): void
    {
        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function hasPermissionToLogin(User $user): bool
    {
        $allowedTypes = [
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

        return in_array($user->getRawOriginal('type'), $allowedTypes);
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        try {
            if ($user) {
                $this->activityService->recordLogout($user, $request, 'web');
                \Log::info('User logged out', [
                    'user_id' => $user->id,
                    'session_id' => session()->getId()
                ]);
            }

            Auth::logout();

            $request->session()->flush();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->hasCookie(config('session.cookie'))) {
                cookie()->queue(cookie()->forget(config('session.cookie')));
            }

            return redirect('/')->with('success', 'You have been successfully logged out.');
        } catch (\Exception $e) {
            \Log::error('Logout error: ' . $e->getMessage());
            return redirect('/')->with('error', 'Error during logout. Please clear your browser cookies.');
        }
    }

    public function handlePageExpired(Request $request)
    {
        \Log::warning('Page expired (419) error', [
            'session_id' => session()->getId(),
            'ip' => $request->ip(),
            'url' => $request->fullUrl(),
            'referer' => $request->header('referer'),
            'user_agent' => $request->userAgent()
        ]);

        if (!Auth::check()) {
            session()->flush();
            session()->regenerateToken();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'error' => 'session_expired',
                'message' => 'Your session has expired. Please refresh the page and try again.',
                'requires_login' => true
            ], 419);
        }

        return redirect()->route('login', ['expired' => 1])
            ->with('error', 'Page expired. Please login again.');
    }

    public function sessionStatus(Request $request)
    {
        $isAuthenticated = Auth::check();

        return response()->json([
            'authenticated' => $isAuthenticated,
            'session_id' => session()->getId(),
            'token' => $request->session()->token(),
            'has_csrf' => $request->has('_token'),
            'csrf_match' => $request->session()->token() === $request->input('_token'),
            'lifetime' => config('session.lifetime'),
            'cookie_name' => config('session.cookie'),
            'is_secure' => $request->isSecure(),
            'user' => $isAuthenticated ? [
                'id' => Auth::id(),
                'name' => Auth::user()->name,
                'email' => Auth::user()->email,
                'type' => Auth::user()->type,
            ] : null,
        ]);
    }

    public function refreshCsrfToken(Request $request)
    {
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'token' => $request->session()->token(),
            'message' => 'CSRF token refreshed'
        ]);
    }

    public function username(): string
    {
        return 'login';
    }

    public static function getAvailableUserTypes(): array
    {
        return [
            User::TYPE_SUPER_ADMIN => 'Super Administrator',
            User::TYPE_ADMIN => 'Administrator',
            User::TYPE_LANDLORD => 'Landlord',
            User::TYPE_TENANT => 'Tenant',
            User::TYPE_FIELD_AGENT => 'Field Agent',
            User::TYPE_DEVELOPER => 'Developer',
            User::TYPE_SECURITY_PERSONNEL => 'Security Personnel',
            User::TYPE_CONTRACTOR => 'Contractor',
            User::TYPE_SANITATION_PERSONNEL => 'Sanitation Personnel',
        ];
    }

    public function debugPhoneNormalization(Request $request)
    {
        if (!app()->environment('local')) {
            abort(404);
        }

        $phone = $request->input('phone');

        if (!$phone) {
            return response()->json(['error' => 'Phone parameter required'], 400);
        }

        $normalized = $this->phoneService->normalize($phone);
        $formats = $this->phoneService->getAllFormats($phone);

        $matchingUsers = User::whereIn('phone', $formats)->get(['id', 'name', 'phone', 'type']);

        return response()->json([
            'input' => $phone,
            'normalized' => $normalized,
            'all_formats' => $formats,
            'matching_users' => $matchingUsers,
            'total_matches' => $matchingUsers->count()
        ]);
    }
}