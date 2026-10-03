<?php

namespace App\Mail;

use App\Models\User;
use App\Models\PropertyOwnershipTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PropertyOwnershipTransferInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public $landlord;
    public $property;
    public $transfer;
    public $currentOwner;
    public $invitationUrl;
    public $propertyAddress;
    public $registrationPattern;
    public $transferDate;

    /**
     * Create a new message instance.
     */
    public function __construct($data)
    {
        $this->landlord = $data['landlord'];
        $this->property = $data['property'];
        $this->transfer = $data['transfer'];
        $this->currentOwner = $data['current_owner'];
        $this->invitationUrl = $data['invitation_url'];
        $this->propertyAddress = $data['property_address'];
        $this->registrationPattern = $data['registration_pattern'];
        $this->transferDate = $data['transfer_date'];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Property Ownership Transfer Invitation',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.ownership-transfer-invitation',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}