<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LandlordInvoiceGenerated extends Mailable
{
    use Queueable, SerializesModels;

    public Invoice $invoice;
    public SystemSetting $settings;

    public function __construct(Invoice $invoice, SystemSetting $settings)
    {
        $this->invoice  = $invoice;
        $this->settings = $settings;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Invoice — {$this->invoice->invoice_number} ({$this->settings->system_name})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.landlord.invoice-generated',
            with: [
                'invoice'  => $this->invoice,
                'landlord' => $this->invoice->property->landlord,
                'property' => $this->invoice->property,
                'settings' => $this->settings,
            ],
        );
    }
}