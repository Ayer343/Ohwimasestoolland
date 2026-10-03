<?php

namespace App\Mail;

use App\Models\RentalAgreement;
use App\Models\PropertyUnit;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Models\Property;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class LeaseInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tenant;
    public $unit;
    public $lease;
    public $invitation;
    public $landlord;
    public $signatureUrl;
    public $property; // Add property separately

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($tenant, $unit, RentalAgreement $lease, TenantInvitation $invitation, $landlord = null)
    {
        $this->tenant = $tenant;
        $this->lease = $lease;
        $this->invitation = $invitation;
        $this->landlord = $landlord;
        
        // Handle both PropertyUnit and Property types
        if ($unit instanceof PropertyUnit) {
            $this->unit = $unit;
            $this->property = $unit->property ?? null;
        } elseif ($unit instanceof Property) {
            // If only property is passed, we need to get the unit from the lease
            $this->property = $unit;
            $this->unit = $lease->unit ?? null;
        } else {
            // Fallback: get unit from lease
            $this->unit = $lease->unit ?? null;
            $this->property = $this->unit->property ?? null;
        }
        
        // Generate signature URL safely
        $this->signatureUrl = $this->generateSignatureUrl();
    }

    /**
     * Generate the signature URL safely
     */
    private function generateSignatureUrl(): string
    {
        try {
            if ($this->invitation && $this->invitation->invitation_token) {
                return route('tenant.lease-invitation.show', [
                    'invitation' => $this->invitation->id,
                    'token' => $this->invitation->invitation_token
                ]);
            }
            
            // Fallback URL
            return route('tenant.lease-invitation.show', [
                'invitation' => $this->invitation->id ?? 'invitation',
                'token' => $this->invitation->token ?? $this->invitation->invitation_token ?? 'token'
            ]);
        } catch (\Exception $e) {
            // Log error but don't break the email
            \Log::warning('Failed to generate lease signature URL: ' . $e->getMessage());
            return config('app.url') . '/tenant/lease-invitation';
        }
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $propertyName = $this->property ? $this->property->property_name : 'Property';
        $unitNumber = $this->unit ? $this->unit->unit_number : 'Unit';
        
        $subject = "Lease Agreement Invitation - {$propertyName} - Unit {$unitNumber}";
        
        return $this->subject($subject)
                    ->markdown('emails.lease-invitation')
                    ->with([
                        'tenant' => $this->tenant,
                        'unit' => $this->unit,
                        'lease' => $this->lease,
                        'invitation' => $this->invitation,
                        'landlord' => $this->landlord,
                        'signatureUrl' => $this->signatureUrl,
                        'property' => $this->property,
                    ]);
    }
}