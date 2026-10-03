<?php
// app/Notifications/SystemSettingsReminderNotification.php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;

class SystemSettingsReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $reminderType;
    protected $priority;
    protected $daysWithoutSettings;

    /**
     * Create a new notification instance.
     *
     * @param string $reminderType The type of reminder (first, second, urgent, daily)
     * @param int $daysWithoutSettings Number of days without settings
     */
    public function __construct(string $reminderType = 'first', int $daysWithoutSettings = 0)
    {
        $this->reminderType = $reminderType;
        $this->daysWithoutSettings = $daysWithoutSettings;
        
        // Set priority based on urgency
        $this->priority = match($reminderType) {
            'urgent' => 3,      // Highest priority
            'daily' => 2,       // Medium priority
            'second' => 2,      // Medium priority
            'first' => 1,       // Normal priority
            default => 1
        };
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // In-app notifications only (no email)
        // Add 'mail' if you want email reminders too
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $systemName = config('app.name', 'Hilltop Estate Management');
        
        // Customize message based on reminder type
        $message = $this->getReminderMessage();
        $title = $this->getReminderTitle();
        $icon = $this->getReminderIcon();
        
        return [
            'title' => $title,
            'message' => $message,
            'icon' => $icon,
            'category' => 'system_settings_reminder',
            'priority' => $this->priority,
            'action_url' => route('admin.system-settings.create'),
            'data' => [
                'type' => 'system_settings_reminder',
                'reminder_type' => $this->reminderType,
                'days_without_settings' => $this->daysWithoutSettings,
                'alert_level' => $this->getAlertLevel(),
                'requires_attention' => true,
                'action_required' => 'Create System Settings',
                'impact' => 'System cannot function properly without settings',
                'recommendation' => $this->getRecommendation(),
                'timestamp' => Carbon::now()->toISOString(),
                'system_name' => $systemName,
                'notification_type' => 'in_app_only'
            ]
        ];
    }

    /**
     * Get the reminder title based on type
     */
    protected function getReminderTitle(): string
    {
        return match($this->reminderType) {
            'urgent' => '🚨 URGENT: System Settings Required',
            'daily' => '⚠️ Daily Reminder: Complete System Setup',
            'second' => '🔔 Second Reminder: System Settings Needed',
            'first' => '📋 System Setup Required',
            default => '⚙️ System Configuration Needed'
        };
    }

    /**
     * Get the reminder message based on type
     */
    protected function getReminderMessage(): string
    {
        $baseMessage = "Your property management system is missing critical configuration settings. ";
        
        switch($this->reminderType) {
            case 'urgent':
                return $baseMessage . "The system has been without proper configuration for {$this->daysWithoutSettings} days. Please create system settings IMMEDIATELY to ensure proper functionality.";
                
            case 'daily':
                return $baseMessage . "This is a daily reminder that system settings have not been configured. Please complete the setup wizard to enable all features.";
                
            case 'second':
                return $baseMessage . "This is your second reminder. System settings are still not configured. Please take action to complete the setup.";
                
            case 'first':
                return $baseMessage . "Please take a moment to configure your system settings. This will enable payment processing, invoicing, and notification features.";
                
            default:
                return $baseMessage . "Please configure your system settings to enable all features of the application.";
        }
    }

    /**
     * Get the reminder icon based on type
     */
    protected function getReminderIcon(): string
    {
        return match($this->reminderType) {
            'urgent' => 'fas fa-exclamation-triangle text-danger',
            'daily' => 'fas fa-clock text-warning',
            'second' => 'fas fa-bell text-warning',
            'first' => 'fas fa-cog text-info',
            default => 'fas fa-bell text-primary'
        };
    }

    /**
     * Get alert level
     */
    protected function getAlertLevel(): string
    {
        return match($this->reminderType) {
            'urgent' => 'critical',
            'daily' => 'high',
            'second' => 'medium',
            'first' => 'medium',
            default => 'low'
        };
    }

    /**
     * Get recommendation
     */
    protected function getRecommendation(): string
    {
        return match($this->reminderType) {
            'urgent' => 'Immediate action required. Navigate to System Settings and complete the configuration wizard.',
            'daily' => 'Please complete system setup today to avoid service interruptions.',
            default => 'Navigate to System Settings and complete the setup process to enable all features.'
        };
    }

    /**
     * Get the mail representation of the notification (optional)
     * Uncomment if you want email reminders
     */
    // public function toMail(object $notifiable): MailMessage
    // {
    //     return (new MailMessage)
    //         ->subject($this->getReminderTitle())
    //         ->greeting("Hello {$notifiable->name}!")
    //         ->line($this->getReminderMessage())
    //         ->action('Create System Settings Now', route('admin.system-settings.create'))
    //         ->line('Please complete the setup to ensure your property management system functions properly.')
    //         ->line('Thank you for using our application!');
    // }
}