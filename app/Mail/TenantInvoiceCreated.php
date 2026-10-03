<?php

namespace App\Mail;

use App\Models\SystemSetting;
use App\Models\TenantInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenantInvoiceCreated extends Mailable
{
    use Queueable, SerializesModels;

    public TenantInvoice $invoice;
    public SystemSetting $settings;

    public function __construct(TenantInvoice $invoice, SystemSetting $settings)
    {
        $this->invoice  = $invoice;
        $this->settings = $settings;
    }

    public function build()
    {
        return $this->subject("New Invoice — {$this->invoice->invoice_number}")
            ->view('emails.tenant-invoice-created')
            ->with([
                'invoice'  => $this->invoice,
                'settings' => $this->settings,
                'tenant'   => $this->invoice->tenant,
            ]);
    }
}