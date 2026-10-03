<?php

namespace App\Mail;

use App\Models\RentalAgreement;
use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class LeaseExpirationReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $recipient;
    public $unit;
    public $lease;
    public $daysUntilExpiration;
    public $recipientType; // 'tenant' or 'landlord'

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($recipient, PropertyUnit $unit, RentalAgreement $lease, $daysUntilExpiration, $recipientType = 'tenant')
    {
        $this->recipient = $recipient;
        $this->unit = $unit;
        $this->lease = $lease;
        $this->daysUntilExpiration = $daysUntilExpiration;
        $this->recipientType = $recipientType;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $subject = "Lease Agreement Expiring Soon - " . $this->unit->property->property_name . " - Unit " . $this->unit->unit_number;
        
        return $this->subject($subject)
                    ->markdown('emails.lease-expiration-reminder')
                    ->with([
                        'recipient' => $this->recipient,
                        'unit' => $this->unit,
                        'lease' => $this->lease,
                        'daysUntilExpiration' => $this->daysUntilExpiration,
                        'recipientType' => $this->recipientType,
                        'property' => $this->unit->property,
                    ]);
    }
}