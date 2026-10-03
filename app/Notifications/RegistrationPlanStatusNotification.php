<?php

namespace App\Notifications;

use App\Models\RegistrationPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationPlanStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $plan;
    public $status;
    public $message;

    /**
     * Create a new notification instance.
     */
    public function __construct(RegistrationPlan $plan, string $status, string $message)
    {
        $this->plan = $plan;
        $this->status = $status;
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $zoneSection = $this->plan->zone . ($this->plan->section ? " - {$this->plan->section}" : '');
        
        return [
            'plan_id' => $this->plan->id,
            'status' => $this->status,
            'title' => 'Registration Plan Status Update',
            'message' => $this->message,
            'zone_section' => $zoneSection,
            'action_url' => route('field-agent.registration-plans.show', $this->plan->id),
            'icon' => $this->getStatusIcon(),
            'type' => $this->getNotificationType(),
        ];
    }

    /**
     * Get notification type based on status
     */
    private function getNotificationType(): string
    {
        return match($this->status) {
            'completed' => 'success',
            'cancelled' => 'warning',
            'in_progress' => 'info',
            default => 'info'
        };
    }

    /**
     * Get status icon
     */
    private function getStatusIcon(): string
    {
        return match($this->status) {
            'completed' => 'fas fa-check-circle',
            'cancelled' => 'fas fa-times-circle',
            'in_progress' => 'fas fa-play-circle',
            default => 'fas fa-info-circle'
        };
    }
}