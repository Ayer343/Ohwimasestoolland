<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class DeveloperOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return redirect()->route('login')->with('error', 'Please login first.');
        }

        // Check if user is a developer
        if (auth()->user()->type !== User::TYPE_DEVELOPER) {
            abort(403, 'Access denied. Developer access only.');
        }

        return $next($request);
    }
}