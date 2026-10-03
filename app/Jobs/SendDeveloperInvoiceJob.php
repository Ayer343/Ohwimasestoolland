<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\DeveloperBillingRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\DeveloperInvoiceMail;

class SendDeveloperInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice;
    public $developerEmail;

    public function __construct(DeveloperBillingRecord $invoice, string $developerEmail)
    {
        $this->invoice = $invoice;
        $this->developerEmail = $developerEmail;
    }

    public function handle(): void
    {
        try {
            Mail::to($this->developerEmail)->send(new DeveloperInvoiceMail($this->invoice));
            
            Log::info('Invoice sent to developer', [
                'invoice_id' => $this->invoice->id,
                'developer_email' => $this->developerEmail,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send invoice email', [
                'invoice_id' => $this->invoice->id,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
}