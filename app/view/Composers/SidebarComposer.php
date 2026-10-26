<?php

namespace App\View\Composers;

use App\Models\EmergencyMode;
use App\Models\Maintenance;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SidebarComposer
{
    /**
     * Bind the sidebar's data once per render, all cached for 60s.
     */
    public function compose(View $view): void
    {
        $user = auth()->user();

        // Quick path: unauthenticated (login pages etc.) — no data needed
        if (!$user) {
            return;
        }

        $view->with([
            'pendingSuperAdmins'         => $this->cached('sidebar.pending_super_admins', 60, fn () =>
                User::where('type', User::TYPE_SUPER_ADMIN)
                    ->where('status', User::STATUS_PENDING)
                    ->where('created_by', $user->id)
                    ->count()
            ),

            'unresolvedCriticalLogs'     => $this->cached('sidebar.unresolved_critical_logs', 60, fn () =>
                class_exists(SystemLog::class)
                    ? SystemLog::where('resolved', false)
                        ->whereIn('severity', [SystemLog::SEVERITY_CRITICAL, SystemLog::SEVERITY_HIGH])
                        ->count()
                    : 0
            ),

            'activeEmergencies'          => $this->cached('sidebar.active_emergencies', 60, fn () =>
                class_exists(EmergencyMode::class)
                    ? EmergencyMode::active()->count()
                    : 0
            ),

            'activeMaintenanceCount'     => $this->cached('sidebar.active_maintenance', 60, fn () =>
                class_exists(Maintenance::class)
                    ? Maintenance::active()->count()
                    : 0
            ),

            'upcomingMaintenanceCount'   => $this->cached('sidebar.upcoming_maintenance', 60, fn () =>
                class_exists(Maintenance::class)
                    ? Maintenance::upcoming()
                        ->where('scheduled_start', '<=', now()->addHours(24))
                        ->count()
                    : 0
            ),

            'backupCount'                => $this->cached('sidebar.backup_count', 300, fn () =>
                // Count files in the backup directory — avoids a service call per render
                $this->countBackupFiles()
            ),

            // Static-ish data (safe to compute on every render — no DB)
            'smsProviderConfigured'      => false,
            'whatsappProviderConfigured' => false,
            'totalCommunicationProviders'=> 0,
            'totalEmailIssues'           => 0,
            'totalUnreadEmails'          => 0,
            'pendingSmsCount'            => 0,
            'smsQuickStatus'             => ['system_ready' => true],
            'whatsappQuickStatus'        => ['system_ready' => true],
            'sidebarNotificationCount'   => method_exists($user, 'unreadNotifications')
                ? $user->unreadNotifications()->count()
                : 0,
        ]);
    }

    /**
     * Cache helper with graceful fallback when the driver is unavailable.
     */
    private function cached(string $key, int $seconds, callable $callback)
    {
        try {
            return Cache::remember($key, $seconds, $callback);
        } catch (\Throwable $e) {
            // Cache backend unavailable — compute live but don't crash the page.
            try {
                return $callback();
            } catch (\Throwable $inner) {
                return 0;
            }
        }
    }

    /**
     * Best-effort count of backup files without invoking the monitoring service.
     */
    private function countBackupFiles(): int
    {
        try {
            $dir = storage_path('app/backups');
            if (!is_dir($dir)) {
                return 0;
            }

            $files = glob($dir . '/*.{zip,sql,gz}', GLOB_BRACE) ?: [];
            return count($files);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}