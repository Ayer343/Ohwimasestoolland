<?php

namespace App\Mail;

use App\Models\PropertyUnit;
use App\Models\UnitInquiry;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UnitInquiryReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public UnitInquiry $inquiry,
        public PropertyUnit $unit,
        public User $landlord
    ) {}

    public function build(): self
    {
        $unitLabel = $this->unit->unit_name
            ?? $this->unit->unit_number
            ?? 'Unit #' . $this->unit->id;

        return $this
            ->subject("New inquiry — {$unitLabel}")
            ->markdown('emails.unit-inquiry-received', [
                'inquiry'  => $this->inquiry,
                'unit'     => $this->unit,
                'landlord' => $this->landlord,
                'unitLabel' => $unitLabel,
                'replyUrl'  => route('landlord.inquiries.show', $this->inquiry->uuid),
            ]);
    }
}