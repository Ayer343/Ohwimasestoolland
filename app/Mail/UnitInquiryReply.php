<?php

namespace App\Mail;

use App\Models\UnitInquiry;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UnitInquiryReply extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public UnitInquiry $inquiry,
        public string $replyText,
        public ?User $landlord = null
    ) {}

    public function build(): self
    {
        $unitLabel = $this->inquiry->unit->unit_name
            ?? $this->inquiry->unit->unit_number
            ?? 'the unit';

        return $this
            ->subject("Reply to your inquiry about {$unitLabel}")
            ->markdown('emails.unit-inquiry-reply', [
                'inquiry'   => $this->inquiry,
                'replyText' => $this->replyText,
                'landlord'  => $this->landlord,
                'unitLabel' => $unitLabel,
            ]);
    }
}