<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LandlordInvoiceReminder extends Mailable
{
    use Queueable, SerializesModels;

    public Invoice $invoice;
    public SystemSetting $settings;
    public bool $isOverdue;

    public function __construct(Invoice $invoice, SystemSetting $settings)
    {
        $this->invoice  = $invoice;
        $this->settings = $settings;

        // Determine whether this reminder should read as "overdue" or "upcoming"
        $this->isOverdue = $invoice->status === 'overdue'
            || ($invoice->due_date && $invoice->due_date < now());
    }

    public function envelope(): Envelope
    {
        $prefix = $this->isOverdue ? 'Overdue Invoice' : 'Payment Reminder';

        return new Envelope(
            subject: "{$prefix} — {$this->invoice->invoice_number} ({$this->settings->system_name})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.landlord.invoice-reminder',
            with: [
                'invoice'   => $this->invoice,
                'landlord'  => $this->invoice->property->landlord,
                'property'  => $this->invoice->property,
                'settings'  => $this->settings,
                'isOverdue' => $this->isOverdue,
            ],
        );
    }
}