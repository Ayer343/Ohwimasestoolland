<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LandlordInvoiceUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public Invoice $invoice;
    public SystemSetting $settings;
    public array $updateData;

    public function __construct(Invoice $invoice, SystemSetting $settings, array $updateData)
    {
        $this->invoice    = $invoice;
        $this->settings   = $settings;
        $this->updateData = $updateData;
    }

    public function envelope(): Envelope
    {
        $isOverdue = ($this->invoice->status === 'overdue')
            || ($this->invoice->due_date && $this->invoice->due_date < now());

        $prefix = $isOverdue ? 'Overdue Invoice Updated' : 'Invoice Updated';

        return new Envelope(
            subject: "{$prefix} — {$this->invoice->invoice_number} ({$this->settings->system_name})",
        );
    }

    public function content(): Content
    {
        $isOverdue = ($this->invoice->status === 'overdue')
            || ($this->invoice->due_date && $this->invoice->due_date < now());

        return new Content(
            view: 'emails.landlord.invoice-updated',
            with: [
                'invoice'    => $this->invoice,
                'landlord'   => $this->invoice->property->landlord,
                'property'   => $this->invoice->property,
                'settings'   => $this->settings,
                'updateData' => $this->updateData,
                'isOverdue'  => $isOverdue,
            ],
        );
    }
}