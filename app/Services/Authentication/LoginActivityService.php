<?php

namespace App\Services\Authentication;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class LoginActivityService
{
    /**
     * Agent instance (if package is available)
     */
    protected $agent = null;

    public function __construct()
    {
        // Only initialize Agent if the class exists
        if (class_exists('Jenssegers\Agent\Agent')) {
            $this->agent = new \Jenssegers\Agent\Agent();
        }
    }

    /**
     * Get device information from user agent
     */
    protected function getDeviceInfo(Request $request): array
    {
        $userAgent = $request->userAgent();
        
        // If Agent package is available, use it for detailed info
        if ($this->agent) {
            $this->agent->setUserAgent($userAgent);
            return [
                'device' => $this->agent->device(),
                'platform' => $this->agent->platform(),
                'browser' => $this->agent->browser(),
                'is_mobile' => $this->agent->isMobile(),
                'is_tablet' => $this->agent->isTablet(),
                'is_desktop' => $this->agent->isDesktop(),
            ];
        }
        
        // Fallback: Parse basic info from user agent
        return [
            'device' => $this->parseDeviceType($userAgent),
            'platform' => $this->parsePlatform($userAgent),
            'browser' => $this->parseBrowser($userAgent),
            'is_mobile' => $this->isMobileDevice($userAgent),
            'is_tablet' => $this->isTabletDevice($userAgent),
            'is_desktop' => !$this->isMobileDevice($userAgent) && !$this->isTabletDevice($userAgent),
        ];
    }

    /**
     * Parse device type from user agent (fallback)
     */
    protected function parseDeviceType(string $userAgent): string
    {
        if ($this->isMobileDevice($userAgent)) {
            return 'Mobile';
        }
        if ($this->isTabletDevice($userAgent)) {
            return 'Tablet';
        }
        return 'Desktop';
    }

    /**
     * Parse platform from user agent (fallback)
     */
    protected function parsePlatform(string $userAgent): string
    {
        if (str_contains($userAgent, 'Windows')) return 'Windows';
        if (str_contains($userAgent, 'Mac')) return 'Mac';
        if (str_contains($userAgent, 'Linux')) return 'Linux';
        if (str_contains($userAgent, 'Android')) return 'Android';
        if (str_contains($userAgent, 'iOS') || str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) return 'iOS';
        return 'Unknown';
    }

    /**
     * Parse browser from user agent (fallback)
     */
    protected function parseBrowser(string $userAgent): string
    {
        if (str_contains($userAgent, 'Chrome')) return 'Chrome';
        if (str_contains($userAgent, 'Firefox')) return 'Firefox';
        if (str_contains($userAgent, 'Safari')) return 'Safari';
        if (str_contains($userAgent, 'Edge')) return 'Edge';
        if (str_contains($userAgent, 'MSIE') || str_contains($userAgent, 'Trident')) return 'IE';
        return 'Unknown';
    }

    /**
     * Check if mobile device (fallback)
     */
    protected function isMobileDevice(string $userAgent): bool
    {
        $mobileKeywords = ['Mobile', 'Android', 'iPhone', 'iPod', 'BlackBerry', 'Windows Phone', 'Opera Mini'];
        foreach ($mobileKeywords as $keyword) {
            if (str_contains($userAgent, $keyword)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if tablet device (fallback)
     */
    protected function isTabletDevice(string $userAgent): bool
    {
        $tabletKeywords = ['iPad', 'Tablet', 'Kindle', 'PlayBook', 'Nexus 7'];
        foreach ($tabletKeywords as $keyword) {
            if (str_contains($userAgent, $keyword)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Record successful login activity
     */
    public function recordLogin(User $user, Request $request, string $type = 'web'): void
    {
        $this->recordActivity($user, $request, 'login', $type, [
            'success' => true,
            'method' => $request->input('social_provider') ?? 'credentials'
        ]);
    }

    /**
     * Record failed login attempt
     */
    public function recordFailedLogin(?User $user, Request $request, ?string $reason = null): void
    {
        $this->recordActivity(
            $user,
            $request,
            'login',
            'web',
            ['success' => false, 'reason' => $reason]
        );
    }

    /**
     * Record logout activity
     */
    public function recordLogout(User $user, Request $request, string $type = 'web'): void
    {
        $this->recordActivity($user, $request, 'logout', $type);
    }

    /**
     * Record password change
     */
    public function recordPasswordChange(User $user, Request $request): void
    {
        $this->recordActivity($user, $request, 'password_change', 'web');
    }

    /**
     * Record 2FA success
     */
    public function recordTwoFactorSuccess(User $user, Request $request): void
    {
        $this->recordActivity($user, $request, 'two_factor_verify', 'web', ['success' => true]);
    }

    /**
     * Record 2FA failure
     */
    public function recordTwoFactorFailure(User $user, Request $request): void
    {
        $this->recordActivity($user, $request, 'two_factor_verify', 'web', ['success' => false]);
    }

    /**
     * Generic activity recorder
     */
    public function recordActivity(
        ?User $user,
        Request $request,
        string $action,
        string $type = 'web',
        array $metadata = []
    ): void {
        // Don't log if table doesn't exist
        if (!Schema::hasTable('login_activities')) {
            // Just log to Laravel log as fallback
            Log::info('Login activity', [
                'user_id' => $user?->id,
                'action' => $action,
                'type' => $type,
                'ip' => $request->ip(),
                'metadata' => $metadata
            ]);
            return;
        }
        
        try {
            // Get device info
            $deviceInfo = $this->getDeviceInfo($request);
            
            $activity = [
                'user_id' => $user?->id,
                'action' => $action,
                'type' => $type,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device' => $deviceInfo['device'] ?? null,
                'platform' => $deviceInfo['platform'] ?? null,
                'browser' => $deviceInfo['browser'] ?? null,
                'metadata' => json_encode($metadata),
                'created_at' => now()
            ];
            
            // Insert into database
            DB::table('login_activities')->insert($activity);
            
        } catch (\Exception $e) {
            // Don't let activity logging break the login process
            Log::error('Failed to record login activity: ' . $e->getMessage());
        }
    }

    /**
     * Get recent login activity for user
     */
    public function getRecentActivity(User $user, int $limit = 10)
    {
        if (!Schema::hasTable('login_activities')) {
            return collect();
        }
        
        try {
            return DB::table('login_activities')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get login activity: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get login statistics for user
     */
    public function getLoginStats(User $user): array
    {
        if (!Schema::hasTable('login_activities')) {
            return [
                'total_logins' => 0,
                'last_login' => null,
                'unique_devices' => 0,
                'login_trend' => []
            ];
        }
        
        try {
            $totalLogins = DB::table('login_activities')
                ->where('user_id', $user->id)
                ->where('action', 'login')
                ->where('success', true)
                ->count();
            
            $lastLogin = DB::table('login_activities')
                ->where('user_id', $user->id)
                ->where('action', 'login')
                ->where('success', true)
                ->orderBy('created_at', 'desc')
                ->first();
            
            $uniqueDevices = DB::table('login_activities')
                ->where('user_id', $user->id)
                ->where('action', 'login')
                ->where('success', true)
                ->distinct('device')
                ->count('device');
            
            // Last 7 days login trend
            $loginTrend = DB::table('login_activities')
                ->where('user_id', $user->id)
                ->where('action', 'login')
                ->where('success', true)
                ->where('created_at', '>', now()->subDays(7))
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get()
                ->pluck('count', 'date')
                ->toArray();
            
            return [
                'total_logins' => $totalLogins,
                'last_login' => $lastLogin?->created_at,
                'last_ip' => $lastLogin?->ip_address,
                'last_device' => $lastLogin?->device,
                'unique_devices' => $uniqueDevices,
                'login_trend' => $loginTrend
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get login stats: ' . $e->getMessage());
            return [
                'total_logins' => 0,
                'last_login' => null,
                'unique_devices' => 0,
                'login_trend' => []
            ];
        }
    }

    /**
     * Clean old activity logs (should be called by scheduled job)
     */
    public function cleanOldLogs(int $days = 90): int
    {
        if (!Schema::hasTable('login_activities')) {
            return 0;
        }
        
        try {
            $deleted = DB::table('login_activities')
                ->where('created_at', '<', now()->subDays($days))
                ->delete();
            
            Log::info("Cleaned {$deleted} old login activity records");
            return $deleted;
        } catch (\Exception $e) {
            Log::error('Failed to clean old logs: ' . $e->getMessage());
            return 0;
        }
    }
}