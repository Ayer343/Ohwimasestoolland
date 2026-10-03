<?php

namespace App\Notifications;

use App\Models\SecuritySchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\NexmoMessage;
use NotificationChannels\WhatsApp\WhatsAppMessage;

class SecurityAssignmentNotification extends Notification
{
    use Queueable;

    protected $schedule;

    public function __construct(SecuritySchedule $schedule)
    {
        $this->schedule = $schedule;
    }

    public function via($notifiable)
    {
        // Determine channels based on user preferences
        $channels = ['database'];
        
        if ($notifiable->prefers_sms) $channels[] = 'nexmo';
        if ($notifiable->prefers_email) $channels[] = 'mail';
        if ($notifiable->prefers_whatsapp) $channels[] = 'whatsapp';
        
        return $channels;
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('New Security Assignment')
            ->markdown('emails.security.assignment', [
                'schedule' => $this->schedule,
                'user' => $notifiable,
            ]);
    }

    public function toNexmo($notifiable)
    {
        return (new NexmoMessage)
            ->content("New security assignment: {$this->schedule->post->name} on {$this->schedule->assignment_date->format('M j')} at {$this->schedule->shift->start_time}");
    }

    public function toWhatsApp($notifiable)
    {
        return WhatsAppMessage::create()
            ->content("New security assignment: {$this->schedule->post->name} on {$this->schedule->assignment_date->format('M j')} at {$this->schedule->shift->start_time}");
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'security_assignment',
            'schedule_id' => $this->schedule->id,
            'post_name' => $this->schedule->post->name,
            'date' => $this->schedule->assignment_date->format('Y-m-d'),
            'shift' => $this->schedule->shift->name,
            'message' => "You have been assigned to {$this->schedule->post->name} on {$this->schedule->assignment_date->format('M j, Y')}",
        ];
    }
}