<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Services\WhatsAppService;

class WhatsAppNotification extends Notification
{
    use Queueable;

    protected $message;
    protected $template;

    public function __construct($message, $template = null)
    {
        $this->message = $message;
        $this->template = $template;
    }

    public function via($notifiable)
    {
        return ['whatsapp'];
    }

    public function toWhatsApp($notifiable)
    {
        $whatsappService = app(WhatsAppService::class);
        
        return $whatsappService->sendMessage(
            $notifiable->phone_number,
            $this->message,
            $this->template
        );
    }
}