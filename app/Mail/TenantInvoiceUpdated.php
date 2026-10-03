<?php

namespace App\Mail;

use App\Models\SystemSetting;
use App\Models\TenantInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantInvoiceUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public TenantInvoice $invoice;
    public SystemSetting $settings;
    public array $updateData;
    public bool $isOverdue;

    public function __construct(TenantInvoice $invoice, SystemSetting $settings, array $updateData)
    {
        $this->invoice    = $invoice;
        $this->settings   = $settings;
        $this->updateData = $updateData;

        $this->isOverdue = ($invoice->status === 'overdue')
            || ($invoice->due_date && $invoice->due_date < now());
    }

    public function envelope(): Envelope
    {
        $prefix = $this->isOverdue ? 'Overdue Invoice Updated' : 'Invoice Updated';

        return new Envelope(
            subject: "{$prefix} — {$this->invoice->invoice_number} ({$this->settings->system_name})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant.invoice-updated',
            with: [
                'invoice'             => $this->invoice,
                'tenant'              => $this->invoice->tenant,
                'propertyUnitDisplay' => $this->invoice->property_unit_display,
                'settings'            => $this->settings,
                'updateData'          => $this->updateData,
                'isOverdue'           => $this->isOverdue,
            ],
        );
    }
}