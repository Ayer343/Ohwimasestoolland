<?php
// app/Services/SystemSettingsReminderService.php

namespace App\Services;

use App\Models\User;
use App\Models\SystemSetting;
use App\Notifications\SystemSettingsReminderNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SystemSettingsReminderService
{
    /**
     * Check and send reminders if needed
     */
    public function checkAndSendReminders(): void
    {
        // Skip if settings exist
        if (SystemSetting::exists()) {
            return;
        }

        // Prevent sending too many reminders (rate limiting)
        $cacheKey = 'last_settings_reminder_sent';
        $lastSent = Cache::get($cacheKey);
        
        if ($lastSent && $lastSent->diffInHours(now()) < 12) {
            Log::info('Skipping settings reminder - last sent less than 12 hours ago');
            return;
        }

        $daysWithoutSettings = $this->calculateDaysWithoutSettings();
        $reminderType = $this->determineReminderType($daysWithoutSettings);
        
        // Only send if reminder type requires action
        if (!$this->shouldSendReminder($reminderType, $daysWithoutSettings)) {
            return;
        }

        $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
            ->where('status', User::STATUS_ACTIVE)
            ->get();

        if ($superAdmins->isEmpty()) {
            Log::warning('No super admins to send settings reminders to');
            return;
        }

        $sentCount = 0;
        
        foreach ($superAdmins as $admin) {
            try {
                // Check if user has already been reminded recently
                if ($this->wasRecentlyReminded($admin, $reminderType)) {
                    continue;
                }
                
                $admin->notify(new SystemSettingsReminderNotification(
                    $reminderType,
                    $daysWithoutSettings
                ));
                $sentCount++;
                
                // Track that this user was reminded
                $this->trackReminderSent($admin, $reminderType);
                
            } catch (\Exception $e) {
                Log::error("Failed to send settings reminder to admin", [
                    'admin_id' => $admin->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if ($sentCount > 0) {
            Cache::put($cacheKey, now(), now()->addHours(12));
            
            Log::info("System settings reminders sent", [
                'reminder_type' => $reminderType,
                'days_without_settings' => $daysWithoutSettings,
                'super_admins_notified' => $sentCount
            ]);
        }
    }

    /**
     * Calculate days without system settings
     */
    protected function calculateDaysWithoutSettings(): int
    {
        $settings = SystemSetting::withTrashed()->first();
        
        if (!$settings) {
            $anySettingsEver = SystemSetting::withTrashed()->count() > 0;
            
            if (!$anySettingsEver) {
                $firstUser = User::orderBy('created_at', 'asc')->first();
                if ($firstUser) {
                    return max(0, $firstUser->created_at->diffInDays(now()));
                }
                return 0;
            }
            
            if ($settings && $settings->deleted_at) {
                return $settings->deleted_at->diffInDays(now());
            }
        }
        
        return 0;
    }

    /**
     * Determine reminder type based on days
     */
    protected function determineReminderType(int $daysWithoutSettings): string
    {
        if ($daysWithoutSettings >= 7) {
            return 'urgent';
        } elseif ($daysWithoutSettings >= 3) {
            return 'daily';
        } elseif ($daysWithoutSettings >= 1) {
            return 'second';
        }
        return 'first';
    }

    /**
     * Check if we should send this reminder type
     */
    protected function shouldSendReminder(string $reminderType, int $daysWithoutSettings): bool
    {
        return match($reminderType) {
            'urgent' => $daysWithoutSettings >= 7,
            'daily' => $daysWithoutSettings >= 3 && $daysWithoutSettings < 7,
            'second' => $daysWithoutSettings >= 1 && $daysWithoutSettings < 3,
            'first' => $daysWithoutSettings == 0,
            default => false
        };
    }

    /**
     * Check if user was recently reminded
     */
    protected function wasRecentlyReminded(User $user, string $reminderType): bool
    {
        $cacheKey = "user_{$user->id}_last_settings_reminder";
        $lastReminder = Cache::get($cacheKey);
        
        if (!$lastReminder) {
            return false;
        }
        
        // Different intervals based on reminder type
        $intervalHours = match($reminderType) {
            'urgent' => 6,   // Urgent reminders every 6 hours
            'daily' => 24,    // Daily reminders once per day
            'second' => 48,   // Second reminder only once
            'first' => 48,    // First reminder only once
            default => 24
        };
        
        return $lastReminder->diffInHours(now()) < $intervalHours;
    }

    /**
     * Track that a reminder was sent
     */
    protected function trackReminderSent(User $user, string $reminderType): void
    {
        $cacheKey = "user_{$user->id}_last_settings_reminder";
        Cache::put($cacheKey, now(), now()->addDays(7));
        
        // Also track in database for analytics
        try {
            \App\Models\NotificationLog::create([
                'user_id' => $user->id,
                'type' => 'system_settings_reminder',
                'reminder_type' => $reminderType,
                'sent_at' => now()
            ]);
        } catch (\Exception $e) {
            // Silently fail - cache tracking is enough
        }
    }
}