<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorSetupCompleted
{
    public const SESSION_USER_ID    = '2fa_setup_required:user_id';
    public const SESSION_EXPIRES_AT = '2fa_setup_required:expires_at';
    public const SESSION_REMEMBER   = '2fa_setup_required:remember';

    /**
     * Route names the user is allowed to hit while confined.
     * Everything else redirects (web) or returns 428 (API).
     */
    protected const ALLOWED_ROUTES = [
        '2fa.setup.required',
        '2fa.setup.required.enable',
        '2fa.setup.required.verify',
        '2fa.setup.required.cancel',
        '2fa.setup',
        '2fa.enable',
        '2fa.verify-setup',
        '2fa.recovery-codes',
        '2fa.recovery-codes.regenerate',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // ✅ FIX: Skip entirely when there's no bound session store.
        //
        // Why: this middleware enforces a *web-flow* gate — a user who
        // signed in via the browser is stashed in the session with a
        // "pending 2FA setup" flag and must complete setup before
        // proceeding. Stateless API requests (no StartSession in the
        // api middleware group) have no such session. Calling
        // `$request->session()` on them throws
        // `RuntimeException: Session store not set on request`,
        // which 500s every API route this middleware touches.
        //
        // API clients handle 2FA setup through the token flow — the
        // login response tells them it's required, and they call the
        // dedicated endpoints. There's nothing to enforce here.
        if (!$this->hasSession($request)) {
            return $next($request);
        }

        $userId = $request->session()->get(self::SESSION_USER_ID);

        // No handoff key → this middleware is a no-op.
        if (!$userId) {
            return $next($request);
        }

        $expiresAt = (int) $request->session()->get(self::SESSION_EXPIRES_AT, 0);

        if (now()->timestamp > $expiresAt) {
            $this->forgetHandoff($request);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'two_factor_setup_expired',
                    'message' => 'Your two-factor setup window expired. Please login again.',
                ], 401);
            }

            return redirect()->route('login')
                ->withErrors(['login' => 'Setup session expired. Please login again.']);
        }

        $currentRoute = $request->route()?->getName();
        if (in_array($currentRoute, self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success'                   => false,
                'error'                     => 'two_factor_setup_required',
                'message'                   => 'Your role requires two-factor authentication. Please set it up to continue.',
                'two_factor_setup_required' => true,
                'setup_url'                 => route('2fa.setup.required'),
            ], 428); // 428 Precondition Required
        }

        return redirect()->route('2fa.setup.required')
            ->with('warning', 'Your role requires two-factor authentication. Please set it up now to continue.');
    }

    protected function forgetHandoff(Request $request): void
    {
        // Defensive — this method is only reached when a session exists,
        // but wrapping it keeps the class safe against future refactors.
        if (!$this->hasSession($request)) {
            return;
        }

        $request->session()->forget([
            self::SESSION_USER_ID,
            self::SESSION_EXPIRES_AT,
            self::SESSION_REMEMBER,
        ]);
    }

    /**
     * True when the request has a bound, started session store.
     *
     * Returns false for:
     *   • API requests     — no StartSession in the api middleware group
     *   • Console commands — no HTTP request bound
     *   • Queue workers    — same
     *   • Any moment where `app('session')` isn't yet resolved
     *
     * Never throws. This is the guard that prevents the
     * `RuntimeException: Session store not set on request` from
     * bubbling up on stateless requests.
     */
    protected function hasSession(Request $request): bool
    {
        try {
            if (!app()->bound('session')) {
                return false;
            }

            $session = app('session');

            if (!($session instanceof \Illuminate\Session\Store)) {
                return false;
            }

            return $session->isStarted();
        } catch (\Throwable $e) {
            return false;
        }
    }
}