<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Cookie;

class HandleRailwaySession
{
    public function handle(Request $request, Closure $next)
    {
        // Fix for Railway's proxy setup
        if ($request->server->has('HTTP_X_FORWARDED_PROTO')) {
            $request->server->set('HTTPS', 'on');
        }

        // Ensure session is started
        if (!Session::isStarted()) {
            Session::start();
        }

        // Ensure CSRF token exists
        if (!Session::has('_token')) {
            Session::regenerateToken();
        }

        $response = $next($request);

        // Force the session cookie to be set with correct parameters for Railway
        $sessionId = Session::getId();
        if ($sessionId) {
            $cookie = new Cookie(
                config('session.cookie', 'hilltop_session'),
                $sessionId,
                time() + (config('session.lifetime', 120) * 60),
                config('session.path', '/'),
                config('session.domain', 'up.railway.app'),
                config('session.secure', true),
                config('session.http_only', true),
                false,
                config('session.same_site', 'none')
            );
            $response->headers->setCookie($cookie);
        }

        // Add CSRF token to response headers
        $response->headers->set('X-CSRF-TOKEN', Session::token());
        $response->headers->set('X-Session-ID', Session::getId());

        return $response;
    }
}