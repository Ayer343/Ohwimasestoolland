<?php
// app/Console/Commands/SendSystemSettingsReminders.php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\SystemSetting;
use App\Notifications\SystemSettingsReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendSystemSettingsReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:send-settings-reminders 
                            {--type= : Reminder type (first, second, urgent, daily)}
                            {--force : Force send reminders even if settings exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminders to super admins to create system settings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if system settings already exist (unless force flag is used)
        if (!$this->option('force') && SystemSetting::exists()) {
            $this->info('System settings already exist. No reminders needed.');
            return 0;
        }

        $reminderType = $this->option('type');
        $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
            ->where('status', User::STATUS_ACTIVE)
            ->get();

        if ($superAdmins->isEmpty()) {
            $this->warn('No active super admins found to send reminders.');
            return 0;
        }

        // Calculate days without settings
        $daysWithoutSettings = $this->calculateDaysWithoutSettings();
        
        // Determine reminder type if not specified
        if (!$reminderType) {
            $reminderType = $this->determineReminderType($daysWithoutSettings);
        }

        $sentCount = 0;
        
        foreach ($superAdmins as $admin) {
            try {
                $admin->notify(new SystemSettingsReminderNotification(
                    $reminderType,
                    $daysWithoutSettings
                ));
                $sentCount++;
                $this->info("Sent {$reminderType} reminder to: {$admin->email}");
            } catch (\Exception $e) {
                $this->error("Failed to send reminder to {$admin->email}: " . $e->getMessage());
                Log::error("Failed to send system settings reminder", [
                    'admin_id' => $admin->id,
                    'admin_email' => $admin->email,
                    'reminder_type' => $reminderType,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Successfully sent {$sentCount} {$reminderType} reminders to super admins.");
        
        // Log the reminder sending
        Log::info("System settings reminders sent", [
            'reminder_type' => $reminderType,
            'days_without_settings' => $daysWithoutSettings,
            'super_admins_notified' => $sentCount,
            'timestamp' => now()->toISOString()
        ]);

        return 0;
    }

    /**
     * Calculate days without system settings
     */
    protected function calculateDaysWithoutSettings(): int
    {
        // Check if settings were ever created (soft delete check)
        $settings = SystemSetting::withTrashed()->first();
        
        if (!$settings) {
            // Check if any system settings record ever existed
            $anySettingsEver = SystemSetting::withTrashed()->count() > 0;
            
            if (!$anySettingsEver) {
                // Find when the first user was created (system start)
                $firstUser = User::orderBy('created_at', 'asc')->first();
                if ($firstUser) {
                    return $firstUser->created_at->diffInDays(now());
                }
                return 0;
            }
            
            // Settings were deleted
            if ($settings->deleted_at) {
                return $settings->deleted_at->diffInDays(now());
            }
        }
        
        return 0;
    }

    /**
     * Determine reminder type based on days without settings
     */
    protected function determineReminderType(int $daysWithoutSettings): string
    {
        if ($daysWithoutSettings >= 7) {
            return 'urgent';
        } elseif ($daysWithoutSettings >= 3) {
            return 'daily';
        } elseif ($daysWithoutSettings >= 1) {
            return 'second';
        } else {
            return 'first';
        }
    }
}