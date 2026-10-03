<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LandlordInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $landlord;
    public $property;
    public $invitation;
    public $actionUrl;
    public $customMessage;

    public function __construct($landlord, $property, $invitation, $customMessage = null)
    {
        $this->landlord = $landlord;
        $this->property = $property;
        $this->invitation = $invitation;
        $this->customMessage = $customMessage;
        
        // Generate the invitation URL
        $this->actionUrl = $invitation->getInvitationUrl();
    }

    public function build()
    {
        $subject = $this->generateSubject();
        
        return $this->subject($subject)
                    ->view('emails.landlord_invitation')
                    ->with([
                        'landlord' => $this->landlord,
                        'property' => $this->property,
                        'invitation' => $this->invitation,
                        'actionUrl' => $this->actionUrl,
                        'customMessage' => $this->customMessage,
                        'propertyType' => $this->getPropertyType(),
                        'invitationExpires' => $this->invitation->expires_at->format('F j, Y'),
                        'daysRemaining' => now()->diffInDays($this->invitation->expires_at),
                    ]);
    }

    private function generateSubject()
    {
        $propertyType = $this->getPropertyType();
        $zone = $this->property->zone ?? 'Property';
        
        return "Complete Your {$propertyType} Registration - {$zone}";
    }

    private function getPropertyType()
    {
        if ($this->property->propertyType) {
            return $this->property->propertyType->name;
        }
        
        return $this->property->custom_property_type ?? 'Property';
    }
}