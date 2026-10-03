<?php

namespace App\Mail;

use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SuperAdminInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $invitation;
    public $appName;
    public $developerName;
    public $expiryDate;
    public $invitationUrl;
    public $currentDate;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, UserInvitation $invitation, $appName, $developerName, $expiryDate, $invitationUrl)
    {
        $this->user = $user;
        $this->invitation = $invitation;
        $this->appName = $appName;
        $this->developerName = $developerName;
        $this->expiryDate = $expiryDate;
        $this->invitationUrl = $invitationUrl;
        $this->currentDate = now()->format('F j, Y');
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject("Super Administrator Invitation - {$this->appName}")
                    ->view('emails.super-admin-invitation')
                    ->with([
                        'user' => $this->user,
                        'invitation' => $this->invitation,
                        'appName' => $this->appName,
                        'developerName' => $this->developerName,
                        'expiryDate' => $this->expiryDate,
                        'invitationUrl' => $this->invitationUrl,
                        'currentDate' => $this->currentDate,
                    ]);
    }
}