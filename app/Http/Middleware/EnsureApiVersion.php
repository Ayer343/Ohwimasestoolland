<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureApiVersion
{
    public function handle(Request $request, Closure $next)
    {
        $version = $request->header('X-API-Version', 'v1');
        
        // Validate API version
        $supportedVersions = ['v1'];
        
        if (!in_array($version, $supportedVersions)) {
            return response()->json([
                'success' => false,
                'error' => 'unsupported_api_version',
                'message' => 'API version not supported. Supported versions: ' . implode(', ', $supportedVersions),
                'supported_versions' => $supportedVersions
            ], 400);
        }
        
        // Add version to request attributes
        $request->attributes->set('api_version', $version);
        
        return $next($request);
    }
}