<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmergencyAlert extends Mailable
{
    use Queueable, SerializesModels;

    public $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function build()
    {
        return $this->subject('🚨 EMERGENCY SYSTEM ALERT')
                    ->view('emails.emergency-alert')
                    ->with(['content' => $this->message]);
    }
}