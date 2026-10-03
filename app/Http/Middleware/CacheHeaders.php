<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CacheHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  int  $maxAge
     * @param  bool  $public
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $maxAge = 3600, $public = true)
    {
        $response = $next($request);
        
        $cacheControl = ($public ? 'public' : 'private') . ', max-age=' . $maxAge;
        
        $response->header('Cache-Control', $cacheControl);
        $response->header('Pragma', $public ? 'cache' : 'no-cache');
        $response->header('Expires', gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
        
        return $response;
    }
}