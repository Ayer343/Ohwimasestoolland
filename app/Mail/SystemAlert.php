<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SystemAlert extends Mailable
{
    use Queueable, SerializesModels;

    public $message;
    public $subject;

    public function __construct(string $message, string $subject = 'System Alert')
    {
        $this->message = $message;
        $this->subject = $subject;
    }

    public function build()
    {
        return $this->subject($this->subject)
                    ->view('emails.system-alert')
                    ->with(['content' => $this->message]);
    }
}