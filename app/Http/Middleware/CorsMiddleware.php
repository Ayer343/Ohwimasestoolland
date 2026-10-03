<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // ✅ FIX 1: Handle preflight OPTIONS request properly
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($request);
        }

        // ✅ FIX 2: Process the request
        $response = $next($request);
        
        // ✅ FIX 3: Add CORS headers to response
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Handle preflight OPTIONS request
     */
    private function handlePreflightRequest(Request $request): Response
    {
        $origin = $this->getAllowedOrigin($request);
        
        $response = response('', 200);
        
        // ✅ Essential CORS headers for preflight
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', implode(', ', $this->getAllowedMethods()));
        $response->headers->set('Access-Control-Allow-Headers', $this->getAllowedHeaders());
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400'); // 24 hours cache
        $response->headers->set('Vary', 'Origin');
        
        // ✅ Additional headers for better compatibility
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('Keep-Alive', 'timeout=300, max=100');
        
        return $response;
    }

    /**
     * Add CORS headers to the actual response
     */
    private function addCorsHeaders(Request $request, $response)
    {
        // ✅ Don't add CORS headers for error responses (optional)
        if ($this->shouldSkipCors($response)) {
            return $response;
        }

        $origin = $this->getAllowedOrigin($request);
        
        // ✅ Add CORS headers
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', implode(', ', $this->getAllowedMethods()));
        $response->headers->set('Access-Control-Allow-Headers', $this->getAllowedHeaders());
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Expose-Headers', $this->getExposedHeaders());
        $response->headers->set('Vary', 'Origin');
        
        // ✅ Keep-alive headers
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('Keep-Alive', 'timeout=300, max=100');
        
        // ✅ Security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // ✅ Add server info for debugging (optional)
        if (app()->environment('local', 'development')) {
            $response->headers->set('X-Environment', app()->environment());
        }

        return $response;
    }

    /**
     * Get allowed HTTP methods
     */
    private function getAllowedMethods(): array
    {
        return [
            'GET',
            'POST',
            'PUT',
            'PATCH',
            'DELETE',
            'OPTIONS',
            'HEAD',
        ];
    }

    /**
     * Get allowed headers - Enhanced for Flutter
     */
    private function getAllowedHeaders(): string
    {
        $headers = [
            // ✅ Standard headers
            'Content-Type',
            'Authorization',
            'X-Requested-With',
            'Accept',
            'Origin',
            'Referer',
            'User-Agent',
            
            // ✅ Flutter app specific headers
            'X-Device-ID',
            'X-API-Version',
            'X-Platform',
            'X-App-Version',
            'X-Device-Model',
            'X-Timezone',
            'X-Locale',
            'X-App-Build-Number',
            
            // ✅ Additional security/features
            'X-CSRF-TOKEN',
            'X-Session-ID',
            'X-Request-ID',
            
            // ✅ Content-related
            'Content-Disposition',
            'Content-Length',
            'Accept-Encoding',
            'Accept-Language',
            'Cache-Control',
            
            // ✅ File upload support
            'Content-Range',
            'Range',
        ];
        
        return implode(', ', $headers);
    }

    /**
     * Headers exposed to the client (JavaScript can access these)
     */
    private function getExposedHeaders(): string
    {
        $headers = [
            'X-API-Version',
            'X-Rate-Limit-Limit',
            'X-Rate-Limit-Remaining',
            'X-Rate-Limit-Reset',
            'X-Token-Expires-At',
            'X-Request-ID',
            'Content-Disposition',
            'Content-Length',
            'Content-Range',
            'ETag',
            'Last-Modified',
        ];
        
        return implode(', ', $headers);
    }

    /**
     * Get allowed origin based on environment and request
     */
    private function getAllowedOrigin(Request $request): string
    {
        $origin = $request->headers->get('Origin');
        
        // ✅ If no origin header, return appropriate default
        if (!$origin) {
            if (app()->environment('production')) {
                return config('app.url', 'https://yourdomain.com');
            }
            return '*';
        }
        
        // ✅ Development: allow all origins with logging
        if (app()->environment('local', 'development', 'staging')) {
            // Log for debugging
            \Log::debug('CORS Request from origin: ' . $origin);
            return $origin; // ✅ Return the actual origin
        }
        
        // ✅ Production: validate against allowed origins
        $allowedOrigins = $this->getProductionAllowedOrigins();
        
        // Direct match
        if (in_array($origin, $allowedOrigins)) {
            return $origin;
        }
        
        // Pattern match (for subdomains, localhost with ports, etc.)
        if ($this->matchesAllowedPatterns($origin)) {
            return $origin;
        }
        
        // ✅ Fallback: Return the origin anyway for better compatibility
        // But log it for security review
        \Log::warning('CORS request from unallowed origin: ' . $origin);
        return $origin; // Or return config('app.url') for stricter security
    }

    /**
     * Get allowed origins for production
     */
    private function getProductionAllowedOrigins(): array
    {
        return array_merge(
            config('cors.allowed_origins', []),
            [
                // ✅ Your production Flutter web URLs
                'https://app.ohwimase.com',
                'https://ohwimase.com',
                'https://www.ohwimase.com',
                
                // ✅ Local development URLs
                'http://localhost',
                'http://localhost:8000',
                'http://localhost:3000',
                'http://localhost:5000',
                'http://127.0.0.1',
                'http://127.0.0.1:8000',
                'http://127.0.0.1:3000',
                'http://127.0.0.1:5000',
                
                // ✅ Mobile app webviews
                'capacitor://localhost',
                'ionic://localhost',
                'file://',
                'https://localhost',
                
                // ✅ Your computer IPs (for development)
                'http://192.168.100.125',
                'http://192.168.100.125:8000',
                'http://192.168.100.125:80',
                
                // ✅ Flutter web default
                'http://localhost:51484', // Your Flutter web port
            ]
        );
    }

    /**
     * Check if origin matches any allowed patterns
     */
    private function matchesAllowedPatterns(string $origin): bool
    {
        $patterns = array_merge(
            config('cors.allowed_origins_patterns', []),
            [
                '/^https?:\/\/.*\.ohwimase\.com$/',
                '/^https?:\/\/ohwimase\.com$/',
                '/^https?:\/\/localhost:\d+$/',
                '/^https?:\/\/127\.0\.0\.1:\d+$/',
                '/^https?:\/\/192\.168\.[0-9]+\.[0-9]+(:\d+)?$/',
                '/^https?:\/\/10\.[0-9]+\.[0-9]+\.[0-9]+(:\d+)?$/',
                '/^capacitor:\/\/localhost$/',
                '/^ionic:\/\/localhost$/',
            ]
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if we should skip adding CORS headers
     */
    private function shouldSkipCors($response): bool
    {
        // ✅ Don't add CORS headers for certain responses
        if (!$response) {
            return true;
        }
        
        $statusCode = $response->getStatusCode();
        
        // Skip for server errors (500+) and redirects (304)
        if ($statusCode >= 500 || $statusCode === 304) {
            return true;
        }
        
        return false;
    }
}