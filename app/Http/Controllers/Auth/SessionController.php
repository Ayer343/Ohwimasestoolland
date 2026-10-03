<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Authentication\LoginActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class SessionController extends Controller
{
    protected LoginActivityService $activityService;

    /**
     * Constructor
     * 
     * ⭐ FIXED: Removed $this->middleware() call from constructor
     * Middleware should be defined in routes/web.php instead
     */
    public function __construct(LoginActivityService $activityService)
    {
        $this->activityService = $activityService;
        // ⭐ REMOVED: $this->middleware('auth')->except(['check', 'continue', 'invalidate']);
        // Middleware is now handled in routes
    }

    /**
     * Check if there's an existing active session
     */
    public function check(Request $request)
    {
        try {
            // Check if user is authenticated
            if (Auth::check()) {
                $user = Auth::user();
                
                // Update last activity
                if (method_exists($user, 'updateLastActivity')) {
                    $user->updateLastActivity();
                }
                
                return response()->json([
                    'has_session' => true,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_type' => $user->type,
                    'last_activity' => $user->last_activity_at ? $user->last_activity_at->diffForHumans() : null,
                    'session_id' => Session::getId(),
                    'csrf_token' => $request->session()->token(),
                    'session_lifetime' => config('session.lifetime'),
                    'is_secure' => $request->isSecure(),
                ]);
            }
            
            // Check for remember token cookie
            if ($request->hasCookie('remember_web_'.md5(config('app.key')))) {
                return response()->json([
                    'has_session' => false,
                    'has_remember_token' => true,
                    'message' => 'Remember me token found but not authenticated'
                ]);
            }
            
            // Check if session exists but user not authenticated
            if (Session::has('url.intended') || Session::has('_token')) {
                return response()->json([
                    'has_session' => false,
                    'has_anonymous_session' => true,
                    'message' => 'Anonymous session exists but user not authenticated'
                ]);
            }
            
            return response()->json([
                'has_session' => false
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Session check error: ' . $e->getMessage());
            return response()->json([
                'has_session' => false,
                'error' => 'Error checking session'
            ], 500);
        }
    }

    /**
     * Continue with existing session
     */
    public function continueSession(Request $request)
    {
        try {
            if (Auth::check()) {
                $user = Auth::user();
                
                // Update last activity
                if (method_exists($user, 'updateLastActivity')) {
                    $user->updateLastActivity();
                }
                
                // Regenerate CSRF token for security
                $request->session()->regenerateToken();
                
                // Log the continue session activity
                if ($this->activityService && method_exists($this->activityService, 'recordActivity')) {
                    $this->activityService->recordActivity($user, $request, 'session_continued', 'web');
                }
                
                // Redirect based on user type
                $redirect = $this->getRedirectBasedOnType($user);
                
                // Check if intended URL exists
                if (Session::has('url.intended')) {
                    $intended = Session::get('url.intended');
                    // Validate intended URL to prevent open redirect
                    if (str_starts_with($intended, url('/'))) {
                        $redirect = $intended;
                    }
                    Session::forget('url.intended');
                }
                
                return redirect()->intended($redirect)
                    ->with('success', 'Welcome back, ' . $user->name . '!');
            }
            
            // If not authenticated, redirect to login
            return redirect()->route('login')
                ->with('info', 'Your session has expired. Please login again.');
                
        } catch (\Exception $e) {
            \Log::error('Continue session error: ' . $e->getMessage());
            return redirect()->route('login')
                ->with('error', 'An error occurred. Please login again.');
        }
    }

    /**
     * Handle expired session gracefully
     */
    public function handleExpiredSession(Request $request)
    {
        // Clear any stale session data
        if (!Auth::check()) {
            Session::flush();
            Session::regenerateToken();
        }
        
        if ($request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired',
                'requires_login' => true
            ], 419);
        }
        
        return redirect()->route('login')
            ->with('error', 'Your session has expired. Please login again.');
    }

    /**
     * Invalidate/clear current session
     */
    public function invalidateSession(Request $request)
    {
        try {
            $user = null;
            
            if (Auth::check()) {
                $user = Auth::user();
                
                // Log the logout activity
                if ($this->activityService && method_exists($this->activityService, 'recordLogout')) {
                    $this->activityService->recordLogout($user, $request, 'session_invalidation');
                }
            }
            
            // Clear all session data properly
            Session::flush();
            Session::regenerate();
            Session::regenerateToken();
            
            // Forget all cookies
            foreach ($request->cookies->all() as $name => $value) {
                if (str_starts_with($name, config('session.cookie')) || 
                    str_starts_with($name, 'remember_web_')) {
                    cookie()->queue(cookie()->forget($name));
                }
            }
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Session invalidated successfully.'
                ]);
            }
            
            return redirect()->route('login')
                ->with('status', 'Your session has been cleared. You can now login again.');
                
        } catch (\Exception $e) {
            \Log::error('Session invalidation error: ' . $e->getMessage());
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Error invalidating session'
                ], 500);
            }
            
            return redirect()->route('login')
                ->with('error', 'An error occurred while clearing your session.');
        }
    }

    /**
     * Get all active sessions for current user
     */
    public function getActiveSessions(Request $request)
    {
        try {
            $user = $request->user();
            $sessionLifetime = config('session.lifetime');
            $cutoffTime = now()->subMinutes($sessionLifetime);
            
            // Get sessions from database (if using database session driver)
            $activeSessions = [];
            
            if (config('session.driver') === 'database') {
                $sessions = DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->where('last_activity', '>', $cutoffTime->timestamp)
                    ->orderBy('last_activity', 'desc')
                    ->get();
                
                foreach ($sessions as $session) {
                    try {
                        $payload = unserialize(base64_decode($session->payload));
                        $userAgent = $payload['_previous']['user_agent'] ?? 
                                    $payload['_token'] ?? 
                                    'Unknown';
                        
                        $deviceInfo = $this->parseUserAgent($userAgent);
                        
                        $activeSessions[] = [
                            'id' => $session->id,
                            'ip_address' => $session->ip_address ?? 'Unknown',
                            'user_agent' => $userAgent,
                            'device_info' => $deviceInfo,
                            'last_activity' => Carbon::createFromTimestamp($session->last_activity),
                            'is_current' => $session->id === Session::getId()
                        ];
                    } catch (\Exception $e) {
                        \Log::warning('Error parsing session payload: ' . $e->getMessage());
                        $activeSessions[] = [
                            'id' => $session->id,
                            'ip_address' => $session->ip_address ?? 'Unknown',
                            'user_agent' => 'Unknown',
                            'device_info' => ['type' => 'Unknown', 'browser' => 'Unknown', 'platform' => 'Unknown'],
                            'last_activity' => Carbon::createFromTimestamp($session->last_activity),
                            'is_current' => $session->id === Session::getId()
                        ];
                    }
                }
            }
            
            // Get from Sanctum tokens (if using Sanctum)
            $tokens = collect();
            if (method_exists($user, 'tokens')) {
                $tokens = $user->tokens()
                    ->where('last_used_at', '>', now()->subHours(24))
                    ->get()
                    ->map(function ($token) {
                        return [
                            'id' => $token->id,
                            'name' => $token->name,
                            'device_type' => $this->parseDeviceType($token->name),
                            'last_used' => $token->last_used_at,
                            'created_at' => $token->created_at,
                            'is_current' => $token->id === $user->currentAccessToken()?->id
                        ];
                    });
            }
            
            // Clean up expired sessions
            $this->cleanupExpiredSessions($user, $cutoffTime);
            
            // Single session mode check
            if (config('auth.session.single_session', false) && count($activeSessions) > 1) {
                $this->terminateOtherSessions($user, Session::getId());
                return redirect()->route('session.list')
                    ->with('info', 'Single session mode is enabled. All other sessions have been terminated.');
            }
            
            return view('auth.sessions', [
                'sessions' => $activeSessions,
                'tokens' => $tokens,
                'current_session_id' => Session::getId(),
                'session_lifetime' => $sessionLifetime,
                'single_session_mode' => config('auth.session.single_session', false)
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Get active sessions error: ' . $e->getMessage());
            return back()->with('error', 'Error retrieving active sessions.');
        }
    }

    /**
     * Parse user agent for better device identification
     */
    protected function parseUserAgent($userAgent)
    {
        try {
            if (class_exists('Jenssegers\Agent\Agent')) {
                $agent = new \Jenssegers\Agent\Agent();
                $agent->setUserAgent($userAgent);
                
                return [
                    'type' => $this->getDeviceType($agent),
                    'browser' => $agent->browser(),
                    'platform' => $agent->platform(),
                    'is_mobile' => $agent->isMobile(),
                    'is_tablet' => $agent->isTablet(),
                    'is_desktop' => $agent->isDesktop(),
                    'is_robot' => $agent->isRobot(),
                ];
            }
        } catch (\Exception $e) {
            // If Agent package not available, use simple parsing
            return $this->simpleUserAgentParse($userAgent);
        }
        
        return ['type' => 'Unknown', 'browser' => 'Unknown', 'platform' => 'Unknown'];
    }

    /**
     * Simple user agent parsing fallback
     */
    protected function simpleUserAgentParse($userAgent)
    {
        $type = 'Desktop';
        if (stripos($userAgent, 'Mobile') !== false) {
            $type = 'Mobile';
        } elseif (stripos($userAgent, 'Tablet') !== false) {
            $type = 'Tablet';
        }
        
        $browser = 'Unknown';
        $platform = 'Unknown';
        
        if (stripos($userAgent, 'Chrome') !== false) $browser = 'Chrome';
        elseif (stripos($userAgent, 'Firefox') !== false) $browser = 'Firefox';
        elseif (stripos($userAgent, 'Safari') !== false) $browser = 'Safari';
        elseif (stripos($userAgent, 'Edge') !== false) $browser = 'Edge';
        elseif (stripos($userAgent, 'Opera') !== false) $browser = 'Opera';
        
        if (stripos($userAgent, 'Windows') !== false) $platform = 'Windows';
        elseif (stripos($userAgent, 'Mac') !== false) $platform = 'MacOS';
        elseif (stripos($userAgent, 'Linux') !== false) $platform = 'Linux';
        elseif (stripos($userAgent, 'Android') !== false) $platform = 'Android';
        elseif (stripos($userAgent, 'iOS') !== false || stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false) $platform = 'iOS';
        
        return [
            'type' => $type,
            'browser' => $browser,
            'platform' => $platform,
        ];
    }

    /**
     * Get device type from agent
     */
    protected function getDeviceType($agent)
    {
        if ($agent->isMobile()) {
            return 'Mobile';
        }
        if ($agent->isTablet()) {
            return 'Tablet';
        }
        if ($agent->isDesktop()) {
            return 'Desktop';
        }
        return 'Unknown';
    }

    /**
     * Revoke a specific session
     */
    public function revokeSession(Request $request, $sessionId)
    {
        try {
            $user = $request->user();
            $currentSessionId = Session::getId();
            
            // Prevent revoking current session
            if ($sessionId === $currentSessionId) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'error' => 'Cannot revoke current session. Use logout instead.'
                    ], 400);
                }
                return back()->with('error', 'Cannot revoke current session. Use logout instead.');
            }
            
            // Check if session exists and belongs to user
            $session = DB::table('sessions')
                ->where('id', $sessionId)
                ->where('user_id', $user->id)
                ->first();
            
            if (!$session) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'error' => 'Session not found or already expired.'
                    ], 404);
                }
                return back()->with('error', 'Session not found or already expired.');
            }
            
            // Delete the session
            DB::table('sessions')
                ->where('id', $sessionId)
                ->where('user_id', $user->id)
                ->delete();
            
            // Log activity
            if ($this->activityService && method_exists($this->activityService, 'recordActivity')) {
                $this->activityService->recordActivity($user, $request, 'session_revoked', 'web', [
                    'session_id' => $sessionId,
                    'ip_address' => $session->ip_address,
                    'last_activity' => $session->last_activity
                ]);
            }
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Session revoked successfully.'
                ]);
            }
            
            return back()->with('success', 'Session revoked successfully.');
            
        } catch (\Exception $e) {
            \Log::error('Revoke session error: ' . $e->getMessage());
            
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'Error revoking session'
                ], 500);
            }
            
            return back()->with('error', 'Error revoking session.');
        }
    }

    /**
     * Revoke a specific API token
     */
    public function revokeToken(Request $request, $tokenId)
    {
        try {
            $user = $request->user();
            
            // Check if token exists and belongs to user
            $token = $user->tokens()->find($tokenId);
            
            if (!$token) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'error' => 'Token not found.'
                    ], 404);
                }
                return back()->with('error', 'Token not found.');
            }
            
            // Prevent revoking current token
            if ($token->id === $user->currentAccessToken()?->id) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'error' => 'Cannot revoke current token. Use logout instead.'
                    ], 400);
                }
                return back()->with('error', 'Cannot revoke current token. Use logout instead.');
            }
            
            $token->delete();
            
            // Log activity
            if ($this->activityService && method_exists($this->activityService, 'recordActivity')) {
                $this->activityService->recordActivity($user, $request, 'api_token_revoked', 'web', [
                    'token_id' => $tokenId,
                    'token_name' => $token->name
                ]);
            }
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'API token revoked successfully.'
                ]);
            }
            
            return back()->with('success', 'API token revoked successfully.');
            
        } catch (\Exception $e) {
            \Log::error('Revoke token error: ' . $e->getMessage());
            
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'Error revoking token'
                ], 500);
            }
            
            return back()->with('error', 'Error revoking token.');
        }
    }

    /**
     * Logout from all devices
     */
    public function logoutAllDevices(Request $request)
    {
        try {
            $user = $request->user();
            
            $request->validate([
                'password' => 'required|string'
            ]);
            
            // Verify password
            if (!\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'password' => 'Invalid password. Please try again.'
                ]);
            }
            
            // Get current session ID
            $currentSessionId = Session::getId();
            
            // Revoke all sessions except current
            $revokedCount = DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->delete();
            
            // Revoke all API tokens except current
            $tokenCount = 0;
            if (method_exists($user, 'tokens')) {
                $tokenCount = $user->tokens()
                    ->where('id', '!=', $user->currentAccessToken()?->id)
                    ->delete();
            }
            
            // Log activity
            if ($this->activityService && method_exists($this->activityService, 'recordActivity')) {
                $this->activityService->recordActivity($user, $request, 'logout_all_devices', 'web', [
                    'revoked_sessions' => $revokedCount,
                    'revoked_tokens' => $tokenCount
                ]);
            }
            
            // Regenerate CSRF token after mass logout
            $request->session()->regenerateToken();
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Logged out from {$revokedCount} other devices and {$tokenCount} API tokens successfully."
                ]);
            }
            
            return back()->with('success', "Logged out from {$revokedCount} other devices and {$tokenCount} API tokens successfully.");
            
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Logout all devices error: ' . $e->getMessage());
            
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'Error logging out from all devices'
                ], 500);
            }
            
            return back()->with('error', 'Error logging out from all devices. Please try again.');
        }
    }

    /**
     * Terminate all other sessions for single session mode
     */
    protected function terminateOtherSessions(User $user, string $currentSessionId): void
    {
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    /**
     * Clean up expired sessions
     */
    protected function cleanupExpiredSessions(User $user, $cutoffTime): void
    {
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('last_activity', '<', $cutoffTime->timestamp)
            ->delete();
    }

    /**
     * Get session statistics
     */
    public function getSessionStats(Request $request)
    {
        try {
            $user = $request->user();
            $cutoffTime = now()->subMinutes(config('session.lifetime'))->timestamp;
            
            $totalSessions = DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('last_activity', '>', $cutoffTime)
                ->count();
            
            $activeTokens = 0;
            if (method_exists($user, 'tokens')) {
                $activeTokens = $user->tokens()
                    ->where('last_used_at', '>', now()->subHours(24))
                    ->count();
            }
            
            $lastSession = DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', Session::getId())
                ->orderBy('last_activity', 'desc')
                ->first();
            
            return response()->json([
                'success' => true,
                'total_active_sessions' => $totalSessions,
                'total_api_tokens' => $activeTokens,
                'current_session_id' => Session::getId(),
                'last_other_activity' => $lastSession ? Carbon::createFromTimestamp($lastSession->last_activity)->diffForHumans() : null,
                'session_lifetime_minutes' => config('session.lifetime'),
                'single_session_mode' => config('auth.session.single_session', false),
                'csrf_token' => $request->session()->token()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Get session stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error retrieving session statistics'
            ], 500);
        }
    }

    /**
     * Parse device type from user agent
     * @deprecated Use parseUserAgent instead
     */
    protected function parseDeviceType($userAgent): string
    {
        try {
            if (class_exists('Jenssegers\Agent\Agent')) {
                $agent = new \Jenssegers\Agent\Agent();
                $agent->setUserAgent($userAgent);
                return $this->getDeviceType($agent);
            }
        } catch (\Exception $e) {
            // Fallback to simple parsing
        }
        
        return $this->simpleUserAgentParse($userAgent)['type'];
    }

    /**
     * Get redirect based on user type
     */
    protected function getRedirectBasedOnType(User $user): string
    {
        try {
            $userType = $user->getRawOriginal('type');
            
            $typeMap = [
                0 => 'superadmin.dashboard',
                1 => 'admin.dashboard',
                2 => 'landlord.dashboard',
                3 => 'tenant.dashboard',
                4 => 'field-agent.dashboard',
                5 => 'developer.dashboard',
                6 => 'security-personnel.dashboard',
            ];
            
            $route = $typeMap[$userType] ?? 'dashboard';
            
            // Check if route exists before returning
            if (function_exists('route_exists')) {
                return route_exists($route) ? route($route) : route('dashboard');
            }
            
            // Fallback: try to get route, if fails return dashboard
            try {
                return route($route);
            } catch (\Exception $e) {
                return route('dashboard');
            }
            
        } catch (\Exception $e) {
            \Log::warning('Error getting redirect route: ' . $e->getMessage());
            return route('dashboard');
        }
    }
}