<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MaintenanceNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $message;
    public $subject;

    public function __construct(string $message, string $subject = 'System Maintenance Notification')
    {
        $this->message = $message;
        $this->subject = $subject;
    }

    public function build()
    {
        return $this->subject($this->subject)
                    ->view('emails.maintenance-notification')
                    ->with(['content' => $this->message]);
    }
}