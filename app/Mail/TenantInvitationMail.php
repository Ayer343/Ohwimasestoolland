<?php

namespace App\Mail;

use App\Models\User;  // Changed from Tenant
use App\Models\Property;
use App\Models\TenantInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class TenantInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;  // Changed from $tenant to $user
    public $property;
    public $invitation;
    public $invitationUrl;
    public $expiresAt;
    public $propertyType;
    public $landlord;
    public $subject;
    public $greeting;
    public $instructions;
    public $actionText = 'Complete Registration';
    public $actionUrl;
    public $salutation = 'Thank you!';
    public $footerText;
    public $supportContact;
    public $companyName;
    public $companyLogo;
    public $privacyPolicyUrl;
    public $termsUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(
        User $user,  // Changed from Tenant $tenant to User $user
        Property $property, 
        TenantInvitation $invitation
    ) {
        $this->user = $user;
        $this->property = $property;
        $this->invitation = $invitation;
        
        // Generate invitation URL
        $this->invitationUrl = $invitation->invitation_url ?? 
            URL::signedRoute('tenant.invitation.accept', [
                'invitation' => $invitation->token,
                'tenant' => $user->id  // Changed from $tenant->id to $user->id
            ], $invitation->expires_at);
        
        $this->actionUrl = $this->invitationUrl;
        
        // Set expiration date
        $this->expiresAt = $invitation->expires_at;
        
        // Get property type
        $this->propertyType = $property->propertyType->name ?? 
            ($property->custom_property_type ?? 'Property');
        
        // Get landlord info
        $this->landlord = $property->landlord;
        
        // Set company information
        $this->companyName = config('app.name', 'Property Portal');
        $this->companyLogo = asset('images/logo.png');
        $this->supportContact = config('app.support_email', 'support@example.com');
        $this->privacyPolicyUrl = config('app.privacy_policy_url', '#');
        $this->termsUrl = config('app.terms_url', '#');
        
        // Set email subject
        $this->subject = "Tenant Registration Invitation - {$this->companyName}";
        
        // Set greeting
        $this->greeting = "Hello {$user->name}!";  // Changed from $tenant->name to $user->name
        
        // Set instructions
        $this->instructions = "You've been registered as a tenant at {$property->property_name}. " .
            "Please complete your registration to access your tenant dashboard and manage your account.";
        
        // Set footer text
        $this->footerText = "This invitation link will expire on {$invitation->expires_at->format('F j, Y')}. " .
            "If you did not expect this invitation or have any questions, please contact us at {$this->supportContact}.";
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address', 'noreply@example.com'),
                config('mail.from.name', $this->companyName)
            ),
            subject: $this->subject,
            replyTo: [
                new Address($this->supportContact, $this->companyName . ' Support')
            ]
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant.invitation',
            text: 'emails.tenant.invitation-text',
            with: [
                'user' => $this->user,  // Changed from 'tenant' => $this->tenant
                'property' => $this->property,
                'invitation' => $this->invitation,
                'invitationUrl' => $this->invitationUrl,
                'expiresAt' => $this->expiresAt,
                'propertyType' => $this->propertyType,
                'landlord' => $this->landlord,
                'greeting' => $this->greeting,
                'instructions' => $this->instructions,
                'actionText' => $this->actionText,
                'actionUrl' => $this->actionUrl,
                'salutation' => $this->salutation,
                'footerText' => $this->footerText,
                'companyName' => $this->companyName,
                'companyLogo' => $this->companyLogo,
                'supportContact' => $this->supportContact,
                'privacyPolicyUrl' => $this->privacyPolicyUrl,
                'termsUrl' => $this->termsUrl,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Build the message.
     * Alternative method for Laravel < 9.x or custom builds
     */
    public function build()
    {
        return $this
            ->subject($this->subject)
            ->markdown('emails.tenant.invitation-markdown', [
                'user' => $this->user,  // Changed from 'tenant' => $this->tenant
                'property' => $this->property,
                'invitation' => $this->invitation,
                'invitationUrl' => $this->invitationUrl,
                'expiresAt' => $this->expiresAt,
                'propertyType' => $this->propertyType,
                'landlord' => $this->landlord,
                'greeting' => $this->greeting,
                'instructions' => $this->instructions,
                'actionText' => $this->actionText,
                'actionUrl' => $this->actionUrl,
                'salutation' => $this->salutation,
                'footerText' => $this->footerText,
                'companyName' => $this->companyName,
                'companyLogo' => $this->companyLogo,
                'supportContact' => $this->supportContact,
                'privacyPolicyUrl' => $this->privacyPolicyUrl,
                'termsUrl' => $this->termsUrl,
            ]);
    }
}