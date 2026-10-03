<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\PropertyUnit;
use App\Models\RentalAgreement;

class WitnessConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $witnessName;
    public $signerName;
    public $unit;
    public $lease;
    public $party; // 'tenant' or 'landlord'
    public $role;
    public $signatureDate;

    /**
     * Create a new message instance.
     */
    public function __construct($witnessName, $signerName, PropertyUnit $unit, RentalAgreement $lease, $party, $role = null)
    {
        $this->witnessName = $witnessName;
        $this->signerName = $signerName;
        $this->unit = $unit;
        $this->lease = $lease;
        $this->party = $party;
        $this->role = $role;
        $this->signatureDate = now()->format('F j, Y');
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $subject = "Witness Confirmation - Lease Agreement for {$this->unit->property->property_name}";
        
        return $this->subject($subject)
                    ->markdown('emails.witness_confirmation')
                    ->with([
                        'witnessName' => $this->witnessName,
                        'signerName' => $this->signerName,
                        'unit' => $this->unit,
                        'lease' => $this->lease,
                        'party' => $this->party,
                        'role' => $this->role,
                        'signatureDate' => $this->signatureDate,
                    ]);
    }
}