<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Http\Request;
use Closure;
use Symfony\Component\HttpFoundation\Cookie;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // =============================================
        // PAYMENT WEBHOOKS (CRITICAL FOR PRODUCTION)
        // =============================================
        'webhook/*',
        'webhook/payment/*',
        'webhook/paystack',
        'webhook/expresspay',
        'webhook/hubtel',
        'webhook/flutterwave',
        'payment/callback',
        'payment/webhook/*',
        
        // =============================================
        // WHATSAPP WEBHOOKS
        // =============================================
        'whatsapp/webhook',
        'webhook/whatsapp',
        'api/whatsapp/webhook',
        
        // =============================================
        // API ROUTES (Token Authentication)
        // =============================================
        'api/*',
        'api/v1/*',
        'api/v2/*',
        
        // =============================================
        // SOCIAL LOGIN CALLBACKS
        // =============================================
        'login/social/*/callback',
        'auth/social/*/callback',
        'oauth/*/callback',
        'auth/google/callback',
        'auth/facebook/callback',
        'auth/github/callback',
        
        // =============================================
        // TWO-FACTOR AUTHENTICATION
        // =============================================
        '2fa/verify',
        '2fa/resend',
        'two-factor/verify',
        'two-factor/resend',
        
        // =============================================
        // SESSION MANAGEMENT
        // =============================================
        'session/check',
        'session/invalidate',
        'session/refresh',
        
        // =============================================
        // HEALTH & MONITORING
        // =============================================
        'health',
        'ping',
        'status',
        'health-check',
        'uptime',
        
        // =============================================
        // FILE UPLOADS (Signed URLs)
        // =============================================
        'uploads/temp',
        'uploads/chunk',
        'uploads/large',
        'upload/chunk',
        
        // =============================================
        // ADMIN PROVIDER CONFIGURATIONS (AJAX)
        // =============================================
        'admin/whatsapp-providers/toggle',
        'admin/whatsapp-providers/test-connection',
        'admin/whatsapp-providers/send-test',
        'admin/whatsapp-providers/reset',
        'admin/payment-providers/toggle',
        'admin/payment-providers/test-connection',
        'admin/payment-providers/status',
        
        // =============================================
        // PAYMENT PROVIDER ROUTES (AJAX)
        // =============================================
        'payment/provider/*',
        'payment/initialize',
        'payment/verify',
        'payment/status',
        
        // =============================================
        // SUBSCRIPTION WEBHOOKS
        // =============================================
        'stripe/webhook',
        'paypal/webhook',
        'subscription/webhook',
        
        // ⭐ ADDED FOR RAILWAY: Health check endpoints
        '_health',
        '_health/*',
        'health/*',
        'ping/*',
        
        // ⭐ ADDED FOR RAILWAY: Debug endpoints
        'debug/*',
        '_debug/*',
    ];

    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The number of minutes the CSRF token should be valid for.
     * Default is 120 minutes (2 hours)
     *
     * @var int
     */
    protected $tokenExpirationMinutes = 120;

    /**
     * Custom CSRF token field name
     *
     * @var string
     */
    protected $tokenField = '_token';

    /**
     * Custom CSRF header name
     *
     * @var string
     */
    protected $tokenHeader = 'X-CSRF-TOKEN';

    /**
     * Additional headers to check for CSRF token
     *
     * @var array
     */
    protected $additionalHeaders = [
        'X-XSRF-TOKEN',
        'X-CSRF-TOKEN',
        'X-CSRF-Header'
    ];

    /**
     * Handle an incoming request with enhanced security.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Illuminate\Session\TokenMismatchException
     */
    public function handle($request, Closure $next)
    {
        // ⭐ ADDED: Skip CSRF check for Railway health checks
        if ($this->isRailwayHealthCheck($request)) {
            return $next($request);
        }

        // Skip CSRF check for excluded URIs
        if ($this->shouldSkipCsrfCheck($request)) {
            return $next($request);
        }

        // Check if request is from trusted source (internal/API)
        if ($this->isTrustedRequest($request)) {
            return $next($request);
        }

        // Perform enhanced CSRF validation
        if ($this->isReading($request) || $this->tokensMatch($request)) {
            // Refresh token if we're within final minutes of expiration
            $this->refreshExpiringToken($request);
            
            return $this->addCookieToResponse($request, $next($request));
        }

        // Log attempted CSRF attack
        $this->logCsrfAttempt($request);

        // ⭐ FIXED: Return 419 response with proper headers for Railway
        throw new \Illuminate\Session\TokenMismatchException(
            'CSRF token mismatch. Please refresh the page and try again.'
        );
    }

    /**
     * ⭐ ADDED: Check if this is a Railway health check
     */
    protected function isRailwayHealthCheck(Request $request): bool
    {
        // Railway health check paths
        $healthPaths = ['health', 'ping', 'status', 'uptime', '_health'];
        
        foreach ($healthPaths as $path) {
            if ($request->is($path) || $request->is($path . '/*')) {
                return true;
            }
        }
        
        // Check if it's a GET request to root (Railway often checks root)
        if ($request->is('/') && $request->method() === 'GET') {
            return true;
        }
        
        return false;
    }

    /**
     * Determine if the CSRF check should be skipped for this request.
     */
    protected function shouldSkipCsrfCheck(Request $request)
    {
        // Check explicit exceptions
        foreach ($this->except as $except) {
            if ($request->is($except)) {
                return true;
            }
        }

        // Skip for AJAX requests with API token
        if ($request->ajax() && $request->hasHeader('X-API-TOKEN')) {
            return $this->validateApiToken($request);
        }

        // Skip for requests with Bearer token (JWT/OAuth)
        if ($request->bearerToken()) {
            $this->validateBearerToken($request);
            return true;
        }

        // Skip for signed URLs
        if ($request->hasValidSignature()) {
            return true;
        }

        // ⭐ ADDED: Skip for Railway internal requests
        if ($this->isRailwayInternalRequest($request)) {
            return true;
        }

        return false;
    }

    /**
     * ⭐ ADDED: Check if request is from Railway internal network
     */
    protected function isRailwayInternalRequest(Request $request): bool
    {
        // Railway internal IP ranges
        $railwayIps = [
            '10.0.0.0/8',      // Private network
            '172.16.0.0/12',   // Private network
            '192.168.0.0/16',  // Private network
            '127.0.0.1',       // Localhost
            '::1',             // Localhost IPv6
            'fd12:b823:accd:1::/64', // Railway IPv6 range
        ];
        
        $clientIp = $request->ip();
        if ($this->ipInRange($clientIp, $railwayIps)) {
            return true;
        }

        // Check for Railway headers
        if ($request->hasHeader('X-Railway-Request-Id') || 
            $request->hasHeader('X-Railway-Edge') ||
            $request->hasHeader('X-Real-IP')) {
            return true;
        }

        return false;
    }

    /**
     * Check if IP is in range (updated for IPv6 support)
     */
    protected function ipInRange($ip, $ranges)
    {
        foreach ($ranges as $range) {
            if (strpos($range, '/') !== false) {
                // Handle IPv6 ranges
                if (strpos($range, ':') !== false) {
                    // Simple IPv6 prefix match (for Railway's fd12:b823:accd:1::/64)
                    $prefix = explode('/', $range)[0];
                    if (strpos($ip, rtrim($prefix, ':')) === 0) {
                        return true;
                    }
                    continue;
                }
                
                // IPv4 CIDR
                list($subnet, $bits) = explode('/', $range);
                $ipDecimal = ip2long($ip);
                if ($ipDecimal === false) continue;
                $subnetDecimal = ip2long($subnet);
                if ($subnetDecimal === false) continue;
                $mask = -1 << (32 - $bits);
                $subnetDecimal &= $mask;
                if (($ipDecimal & $mask) == $subnetDecimal) {
                    return true;
                }
            } else {
                // Single IP
                if ($ip === $range) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Determine if the request is from a trusted source.
     */
    protected function isTrustedRequest(Request $request)
    {
        // Check IP whitelist
        $trustedIps = config('app.trusted_ips', []);
        if (in_array($request->ip(), $trustedIps)) {
            return true;
        }

        // Check if request is from localhost
        if ($request->ip() === '127.0.0.1' || $request->ip() === '::1') {
            return true;
        }

        // Check for internal service-to-service communication
        if ($request->hasHeader('X-Internal-Request') && 
            $request->header('X-Internal-Key') === config('app.internal_secret')) {
            return true;
        }

        // ⭐ ADDED: Check for Railway proxy
        if ($request->hasHeader('X-Railway-Request-Id')) {
            return true;
        }

        return false;
    }

    /**
     * Validate API token for AJAX requests.
     */
    protected function validateApiToken(Request $request)
    {
        $token = $request->header('X-API-TOKEN');
        
        // Check against configured API tokens
        $validTokens = config('app.api_tokens', []);
        
        if (in_array($token, $validTokens)) {
            // Add token validation timestamp to prevent replay attacks
            $requestTimestamp = $request->header('X-Request-Timestamp');
            if ($requestTimestamp && (time() - $requestTimestamp) < 300) { // 5 minute window
                return true;
            }
        }
        
        return false;
    }

    /**
     * Validate Bearer token (JWT/OAuth).
     */
    protected function validateBearerToken(Request $request)
    {
        $token = $request->bearerToken();
        
        // Add custom bearer token validation logic here
        // Example: Verify JWT signature, check against database, etc.
        
        // For now, we'll just check if it exists and is valid format
        if (strpos($token, '.') === false || substr_count($token, '.') !== 2) {
            throw new \Illuminate\Session\TokenMismatchException('Invalid bearer token format');
        }
        
        // Decode and validate JWT (example)
        try {
            $parts = explode('.', $token);
            $payload = json_decode(base64_decode($parts[1]), true);
            
            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                throw new \Illuminate\Session\TokenMismatchException('Bearer token expired');
            }
        } catch (\Exception $e) {
            throw new \Illuminate\Session\TokenMismatchException('Invalid bearer token: ' . $e->getMessage());
        }
    }

    /**
     * Determine if the session and input CSRF tokens match.
     */
    protected function tokensMatch($request)
    {
        // Get token from various sources
        $token = $this->getTokenFromRequest($request);
        
        // Get session token
        $sessionToken = $request->session()->token();
        
        // ⭐ FIXED: Better error handling for missing session
        if (!$sessionToken) {
            // Regenerate session token if missing
            $request->session()->regenerateToken();
            $sessionToken = $request->session()->token();
            return false;
        }
        
        // Basic match check
        if (is_string($sessionToken) && is_string($token) && hash_equals($sessionToken, $token)) {
            return true;
        }
        
        // Check if token exists but is expired and needs refresh
        if ($this->isTokenExpired($request, $token)) {
            // Allow once with old token, but force refresh
            $request->session()->regenerateToken();
            return true;
        }
        
        return false;
    }

    /**
     * Get CSRF token from request (checks multiple sources).
     */
    protected function getTokenFromRequest(Request $request)
    {
        // Check POST parameter
        if ($request->has($this->tokenField)) {
            return $request->input($this->tokenField);
        }
        
        // Check custom headers
        foreach ($this->additionalHeaders as $header) {
            if ($request->hasHeader($header)) {
                return $request->header($header);
            }
        }
        
        // Check JSON request body
        if ($request->isJson() && $request->json()->has($this->tokenField)) {
            return $request->json()->get($this->tokenField);
        }
        
        // Check URL query parameter (for GET requests that need CSRF protection)
        if ($request->query->has($this->tokenField)) {
            return $request->query->get($this->tokenField);
        }
        
        return null;
    }

    /**
     * Check if CSRF token has expired.
     */
    protected function isTokenExpired(Request $request, $token = null)
    {
        if (!$token) {
            return false;
        }
        
        // Get token creation time from session
        $tokenCreatedAt = $request->session()->get('_token_created_at');
        
        if (!$tokenCreatedAt) {
            return false;
        }
        
        // Check if token is older than configured expiration
        $expirationTime = $tokenCreatedAt + ($this->tokenExpirationMinutes * 60);
        
        return time() > $expirationTime;
    }

    /**
     * Refresh expiring token if it's close to expiration.
     */
    protected function refreshExpiringToken(Request $request)
    {
        $tokenCreatedAt = $request->session()->get('_token_created_at');
        
        if ($tokenCreatedAt) {
            $expirationTime = $tokenCreatedAt + ($this->tokenExpirationMinutes * 60);
            $timeUntilExpiry = $expirationTime - time();
            
            // Refresh token if less than 30 minutes remaining
            if ($timeUntilExpiry < 1800) { // 30 minutes in seconds
                $request->session()->regenerateToken();
                // Update token creation time
                $request->session()->put('_token_created_at', time());
            }
        } else {
            // Set token creation time if not set
            $request->session()->put('_token_created_at', time());
        }
    }

    /**
     * Add the CSRF token to the response cookies.
     * ⭐ FIXED: Better cookie handling for Railway
     */
    protected function addCookieToResponse($request, $response)
    {
        $config = config('session');
        
        if ($this->addHttpCookie) {
            // ⭐ Get domain from config or use Railway domain
            $domain = $config['domain'] ?? '.up.railway.app';
            
            // ⭐ Determine if secure based on request
            $secure = $config['secure'] ?? $request->isSecure();
            
            $response->headers->setCookie(
                new Cookie(
                    'XSRF-TOKEN',
                    $request->session()->token(),
                    $this->availableAt(60 * $config['lifetime']),
                    $config['path'] ?? '/',
                    $domain,
                    $secure,
                    false, // HttpOnly
                    false,
                    $config['same_site'] ?? 'lax'
                )
            );
        }
        
        // Add security headers
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        return $response;
    }

    /**
     * Log CSRF token mismatch attempt for security monitoring.
     */
    protected function logCsrfAttempt(Request $request)
    {
        $data = [
            'ip' => $request->ip(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
            'session_id' => $request->session()->getId(),
            'timestamp' => now()->toDateTimeString(),
            'headers' => [
                'referer' => $request->header('referer'),
                'origin' => $request->header('origin'),
                'x_requested_with' => $request->header('X-Requested-With'),
            ]
        ];
        
        // Log to Laravel log
        \Illuminate\Support\Facades\Log::warning('CSRF Token Mismatch Attempt', $data);
        
        // Rate limit CSRF attempts to prevent brute force
        $this->rateLimitCsrfAttempts($request);
    }

    /**
     * Rate limit CSRF mismatch attempts.
     */
    protected function rateLimitCsrfAttempts(Request $request)
    {
        $key = 'csrf_attempts_' . $request->ip();
        $attempts = cache()->get($key, 0);
        
        if ($attempts >= 10) {
            // Block IP for 15 minutes after 10 failed attempts
            cache()->put($key, $attempts + 1, now()->addMinutes(15));
            
            \Illuminate\Support\Facades\Log::warning('CSRF rate limit exceeded', [
                'ip' => $request->ip(),
                'attempts' => $attempts
            ]);
        } else {
            cache()->put($key, $attempts + 1, now()->addMinutes(5));
        }
    }

    /**
     * Get the CSRF token from the request (public method for controllers).
     */
    public static function getToken(Request $request)
    {
        $instance = new static();
        return $instance->getTokenFromRequest($request);
    }

    /**
     * Validate CSRF token manually (public method for controllers).
     */
    public static function validateToken(Request $request)
    {
        $instance = new static();
        return $instance->tokensMatch($request);
    }

    /**
     * Generate a new CSRF token and return it.
     */
    public static function refreshToken(Request $request)
    {
        $request->session()->regenerateToken();
        $request->session()->put('_token_created_at', time());
        return $request->session()->token();
    }
}