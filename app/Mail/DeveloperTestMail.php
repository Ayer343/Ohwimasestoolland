<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address; // ADD THIS IMPORT
use Illuminate\Queue\SerializesModels;

class DeveloperTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $testType;

    public function __construct($testType = 'connection')
    {
        $this->testType = $testType;
    }

    public function envelope(): Envelope
    {
        // FIX: Use Address object instead of config() array
        return new Envelope(
            subject: 'Developer Email Configuration Test - ' . $this->testType,
            from: new Address(
                config('mail.mailers.developer.username'), // or env('DEVELOPER_MAIL_FROM_ADDRESS')
                config('mail.from.name') // or env('DEVELOPER_MAIL_FROM_NAME')
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.developer-test',
            with: [
                'testType' => $this->testType,
                'timestamp' => now()->toDateTimeString(),
                'config' => [
                    'host' => config('mail.mailers.developer.host'),
                    'port' => config('mail.mailers.developer.port'),
                    'username' => config('mail.mailers.developer.username'),
                ]
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}