<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the mandatory 2FA setup routes.
 *
 * Rejects any request that doesn't carry a valid, non-expired
 * `2fa_setup_required:*` handoff in the session. This is what stops
 * anonymous visitors from reaching `/2fa/setup-required` by typing
 * the URL directly.
 *
 * NOTE: deliberately NOT `auth`. During mandatory setup the user is
 * logged OUT of the application (LoginController calls Auth::logout()
 * before writing the handoff keys). If we wrapped the setup routes in
 * `auth`, the Authenticate middleware would bounce the user to /login
 * before ever seeing the setup form.
 */
class RequireTwoFactorSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->session()->get('2fa_setup_required:user_id');

        if (!$userId) {
            return redirect()->route('login');
        }

        $expiresAt = (int) $request->session()->get('2fa_setup_required:expires_at', 0);

        if (now()->timestamp > $expiresAt) {
            $request->session()->forget([
                '2fa_setup_required:user_id',
                '2fa_setup_required:expires_at',
                '2fa_setup_required:remember',
            ]);

            return redirect()->route('login')
                ->withErrors(['login' => 'Setup session expired. Please login again.']);
        }

        return $next($request);
    }
}