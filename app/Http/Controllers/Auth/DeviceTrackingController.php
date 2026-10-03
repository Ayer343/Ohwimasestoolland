<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Authentication\LoginActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;

class DeviceTrackingController extends Controller
{
    protected LoginActivityService $activityService;
    protected Agent $agent;

    /**
     * Constructor
     */
    public function __construct(LoginActivityService $activityService)
    {
        $this->middleware('auth')->except(['track']);
        $this->activityService = $activityService;
        $this->agent = new Agent();
    }

    /**
     * Track device information for current request
     */
    public function track(Request $request)
    {
        $request->validate([
            'screen_resolution' => 'nullable|string',
            'language' => 'nullable|string',
            'timezone' => 'nullable|string'
        ]);

        $this->agent->setUserAgent($request->userAgent());
        
        $deviceInfo = [
            'device_type' => $this->getDeviceType(),
            'device_name' => $this->agent->device(),
            'platform' => $this->agent->platform(),
            'platform_version' => $this->agent->version($this->agent->platform()),
            'browser' => $this->agent->browser(),
            'browser_version' => $this->agent->version($this->agent->browser()),
            'is_mobile' => $this->agent->isMobile(),
            'is_tablet' => $this->agent->isTablet(),
            'is_desktop' => $this->agent->isDesktop(),
            'is_robot' => $this->agent->isRobot(),
            'languages' => implode(',', $this->agent->languages() ?? []),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'screen_resolution' => $request->screen_resolution,
            'language' => $request->language,
            'timezone' => $request->timezone
        ];
        
        // Store device fingerprint
        $fingerprint = $this->generateDeviceFingerprint($request, $deviceInfo);
        
        // If user is authenticated, associate device with user
        if ($request->user()) {
            $this->associateDeviceWithUser($request->user(), $deviceInfo, $fingerprint);
        }
        
        // Store in session for current request
        session([
            'device_info' => $deviceInfo,
            'device_fingerprint' => $fingerprint
        ]);
        
        // Log device tracking
        \Log::info('Device tracked', [
            'fingerprint' => $fingerprint,
            'user_id' => $request->user()?->id,
            'device_info' => $deviceInfo
        ]);
        
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'fingerprint' => $fingerprint,
                'device_info' => $deviceInfo
            ]);
        }
        
        return response()->json(['success' => true]);
    }

    /**
     * Get all devices for current user
     */
    public function getUserDevices(Request $request)
    {
        $user = $request->user();
        
        // Get devices from login_activities table
        $devices = DB::table('login_activities')
            ->where('user_id', $user->id)
            ->where('action', 'login')
            ->where('success', true)
            ->select(
                'id',
                'ip_address',
                'user_agent',
                'device',
                'platform',
                'browser',
                'location',
                'created_at as first_seen',
                DB::raw('MAX(created_at) as last_seen')
            )
            ->groupBy('ip_address', 'device', 'platform', 'browser', 'user_agent', 'id', 'location', 'created_at')
            ->orderBy('last_seen', 'desc')
            ->get()
            ->map(function ($device) {
                // Parse device info
                $device->is_current_device = $this->isCurrentDevice($device->user_agent, $device->ip_address);
                $device->location_data = $device->location ? json_decode($device->location, true) : null;
                $device->last_seen_human = \Carbon\Carbon::parse($device->last_seen)->diffForHumans();
                $device->trusted = $this->isDeviceTrusted($device->user_agent, $device->ip_address);
                
                return $device;
            });
        
        // Get trusted devices explicitly
        $trustedDevices = DB::table('trusted_devices')
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->get()
            ->map(function ($device) {
                $device->type = 'trusted';
                $device->trusted_until = \Carbon\Carbon::parse($device->expires_at)->diffForHumans();
                return $device;
            });
        
        // Get API tokens/devices
        $apiDevices = $user->tokens()
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(function ($token) {
                $deviceInfo = json_decode($token->device_info ?? '{}', true);
                
                return (object)[
                    'id' => $token->id,
                    'type' => 'api',
                    'name' => $token->name,
                    'device_type' => $deviceInfo['device_type'] ?? 'Unknown',
                    'platform' => $deviceInfo['platform'] ?? 'Unknown',
                    'browser' => $deviceInfo['browser'] ?? 'API',
                    'last_seen' => $token->last_used_at,
                    'last_seen_human' => $token->last_used_at ? \Carbon\Carbon::parse($token->last_used_at)->diffForHumans() : 'Never',
                    'created_at' => $token->created_at,
                    'is_current' => $token->id === $user->currentAccessToken()?->id
                ];
            });
        
        // Get statistics
        $stats = [
            'total_devices' => $devices->count(),
            'trusted_devices' => $trustedDevices->count(),
            'api_tokens' => $apiDevices->count(),
            'unique_ips' => $devices->unique('ip_address')->count(),
            'unique_browsers' => $devices->unique('browser')->count(),
            'unique_platforms' => $devices->unique('platform')->count()
        ];
        
        return view('auth.devices', [
            'devices' => $devices,
            'trusted_devices' => $trustedDevices,
            'api_devices' => $apiDevices,
            'stats' => $stats,
            'current_device' => [
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip(),
                'device_type' => $this->getDeviceType(),
                'platform' => $this->agent->platform(),
                'browser' => $this->agent->browser()
            ]
        ]);
    }

    /**
     * Remove a specific device
     */
    public function removeDevice(Request $request, $deviceId)
    {
        $user = $request->user();
        
        // Try to remove from login_activities (if it's a device record)
        $deleted = DB::table('login_activities')
            ->where('user_id', $user->id)
            ->where('id', $deviceId)
            ->delete();
        
        if (!$deleted) {
            // Try to remove from trusted_devices
            $deleted = DB::table('trusted_devices')
                ->where('user_id', $user->id)
                ->where('id', $deviceId)
                ->delete();
        }
        
        if ($deleted) {
            // Log activity
            $this->activityService->recordActivity($user, $request, 'device_removed', 'web');
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Device removed successfully.'
                ]);
            }
            
            return back()->with('success', 'Device removed successfully.');
        }
        
        return response()->json([
            'error' => 'Device not found.'
        ], 404);
    }

    /**
     * Trust current device (skip 2FA for future logins)
     */
    public function trustDevice(Request $request)
    {
        $request->validate([
            'days' => 'sometimes|integer|min:1|max:90'
        ]);

        $user = $request->user();
        $days = $request->input('days', 30);
        
        // Generate device fingerprint
        $fingerprint = $this->generateDeviceFingerprint($request);
        
        // Store trusted device
        DB::table('trusted_devices')->updateOrInsert(
            [
                'user_id' => $user->id,
                'device_fingerprint' => $fingerprint
            ],
            [
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'device_type' => $this->getDeviceType(),
                'platform' => $this->agent->platform(),
                'browser' => $this->agent->browser(),
                'trusted_at' => now(),
                'expires_at' => now()->addDays($days),
                'last_used_at' => now()
            ]
        );
        
        // Log activity
        $this->activityService->recordActivity($user, $request, 'device_trusted', 'web');
        
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Device trusted for {$days} days.",
                'expires_at' => now()->addDays($days)->toDateTimeString()
            ]);
        }
        
        return back()->with('success', "Device trusted for {$days} days.");
    }

    /**
     * Untrust a trusted device
     */
    public function untrustDevice(Request $request, $deviceId)
    {
        $user = $request->user();
        
        $deleted = DB::table('trusted_devices')
            ->where('user_id', $user->id)
            ->where('id', $deviceId)
            ->delete();
        
        if ($deleted) {
            // Log activity
            $this->activityService->recordActivity($user, $request, 'device_untrusted', 'web');
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Device untrusted successfully.'
                ]);
            }
            
            return back()->with('success', 'Device untrusted successfully.');
        }
        
        return response()->json([
            'error' => 'Device not found.'
        ], 404);
    }

    /**
     * Check if current device is trusted
     */
    public function isCurrentDeviceTrusted(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['trusted' => false]);
        }
        
        $fingerprint = $this->generateDeviceFingerprint($request);
        
        $trusted = DB::table('trusted_devices')
            ->where('user_id', $user->id)
            ->where('device_fingerprint', $fingerprint)
            ->where('expires_at', '>', now())
            ->exists();
        
        return response()->json([
            'trusted' => $trusted,
            'expires_at' => $trusted ? DB::table('trusted_devices')
                ->where('user_id', $user->id)
                ->where('device_fingerprint', $fingerprint)
                ->value('expires_at') : null
        ]);
    }

    /**
     * Get device analytics (admin only)
     */
    public function getDeviceAnalytics(Request $request)
    {
        // This should be protected by admin middleware
        $this->authorize('view-device-analytics');
        
        $stats = [
            'by_device_type' => DB::table('login_activities')
                ->select('device', DB::raw('COUNT(*) as count'))
                ->where('device', '!=', '')
                ->groupBy('device')
                ->get(),
            
            'by_platform' => DB::table('login_activities')
                ->select('platform', DB::raw('COUNT(*) as count'))
                ->where('platform', '!=', '')
                ->groupBy('platform')
                ->get(),
            
            'by_browser' => DB::table('login_activities')
                ->select('browser', DB::raw('COUNT(*) as count'))
                ->where('browser', '!=', '')
                ->groupBy('browser')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            
            'mobile_vs_desktop' => [
                'mobile' => DB::table('login_activities')->where('device', 'Mobile')->count(),
                'desktop' => DB::table('login_activities')->where('device', 'Desktop')->count(),
                'tablet' => DB::table('login_activities')->where('device', 'Tablet')->count(),
                'unknown' => DB::table('login_activities')->whereNull('device')->count()
            ],
            
            'unique_devices_last_30_days' => DB::table('login_activities')
                ->where('created_at', '>', now()->subDays(30))
                ->distinct('user_agent')
                ->count('user_agent'),
            
            'trusted_devices_active' => DB::table('trusted_devices')
                ->where('expires_at', '>', now())
                ->count()
        ];
        
        return response()->json($stats);
    }

    /**
     * Get device type string
     */
    protected function getDeviceType(): string
    {
        if ($this->agent->isMobile()) {
            return 'Mobile';
        }
        
        if ($this->agent->isTablet()) {
            return 'Tablet';
        }
        
        if ($this->agent->isDesktop()) {
            return 'Desktop';
        }
        
        return 'Unknown';
    }

    /**
     * Generate unique device fingerprint
     */
    protected function generateDeviceFingerprint(Request $request, array $deviceInfo = []): string
    {
        $data = [
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'accept_language' => $request->header('Accept-Language'),
            'screen_resolution' => $deviceInfo['screen_resolution'] ?? $request->input('screen_resolution'),
            'timezone' => $deviceInfo['timezone'] ?? $request->input('timezone'),
            'platform' => $deviceInfo['platform'] ?? $this->agent->platform(),
            'browser' => $deviceInfo['browser'] ?? $this->agent->browser()
        ];
        
        // Remove null values
        $data = array_filter($data);
        
        // Generate hash
        return hash('sha256', json_encode($data));
    }

    /**
     * Check if a device is the current device
     */
    protected function isCurrentDevice(string $userAgent, string $ipAddress): bool
    {
        return $userAgent === request()->userAgent() && $ipAddress === request()->ip();
    }

    /**
     * Check if device is trusted
     */
    protected function isDeviceTrusted(string $userAgent, string $ipAddress): bool
    {
        $fingerprint = hash('sha256', json_encode([
            'user_agent' => $userAgent,
            'ip_address' => $ipAddress
        ]));
        
        return DB::table('trusted_devices')
            ->where('device_fingerprint', $fingerprint)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Associate device with user
     */
    protected function associateDeviceWithUser(User $user, array $deviceInfo, string $fingerprint): void
    {
        // Store device info in user metadata or separate table
        $devices = json_decode($user->devices ?? '[]', true);
        
        // Check if device already exists
        $existingIndex = null;
        foreach ($devices as $index => $device) {
            if ($device['fingerprint'] === $fingerprint) {
                $existingIndex = $index;
                break;
            }
        }
        
        $deviceRecord = [
            'fingerprint' => $fingerprint,
            'device_info' => $deviceInfo,
            'first_seen' => $existingIndex !== null ? $devices[$existingIndex]['first_seen'] : now()->toDateTimeString(),
            'last_seen' => now()->toDateTimeString(),
            'times_seen' => $existingIndex !== null ? $devices[$existingIndex]['times_seen'] + 1 : 1
        ];
        
        if ($existingIndex !== null) {
            $devices[$existingIndex] = $deviceRecord;
        } else {
            $devices[] = $deviceRecord;
        }
        
        // Keep only last 20 devices
        $devices = array_slice($devices, -20);
        
        $user->devices = json_encode($devices);
        $user->save();
    }
}