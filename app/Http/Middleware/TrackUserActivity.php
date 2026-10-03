<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class TrackUserActivity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Update last activity for authenticated users
        if (Auth::check()) {
            $user = Auth::user();
            // Check if the user model has an updateLastActivity method
            if (method_exists($user, 'updateLastActivity')) {
                $user->updateLastActivity();
            }
        }

        return $response;
    }
}