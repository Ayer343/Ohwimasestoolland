<?php

namespace App\Mail;

use App\Models\SystemSetting;
use App\Models\TenantInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenantInvoiceReminder extends Mailable
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
        return $this->subject("Payment Reminder — {$this->invoice->invoice_number}")
            ->view('emails.tenant-invoice-reminder')
            ->with([
                'invoice'  => $this->invoice,
                'settings' => $this->settings,
                'tenant'   => $this->invoice->tenant,
            ]);
    }
}