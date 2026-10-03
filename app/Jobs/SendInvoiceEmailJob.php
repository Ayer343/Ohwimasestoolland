<?php

namespace App\Jobs;

use App\Models\BillingInvoice;
use App\Models\AdminBillingRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class SendInvoiceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var BillingInvoice
     */
    protected $invoice;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var array
     */
    protected $options;

    /**
     * Create a new job instance.
     *
     * @param BillingInvoice $invoice
     * @param string $email
     * @param array $options
     * @return void
     */
    public function __construct(BillingInvoice $invoice, string $email, array $options = [])
    {
        $this->invoice = $invoice;
        $this->email = $email;
        $this->options = $options;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            // Get the agreement related to this invoice
            $agreement = null;
            if ($this->invoice->agreement_id) {
                $agreement = AdminBillingRecord::find($this->invoice->agreement_id);
            } elseif ($this->invoice->admin_billing_record_id) {
                $agreement = AdminBillingRecord::find($this->invoice->admin_billing_record_id);
            }

            // Prepare email data
            $data = [
                'invoice' => $this->invoice,
                'agreement' => $agreement,
                'email' => $this->email,
                'subject' => $this->options['subject'] ?? 'Invoice #' . $this->invoice->invoice_number,
                'message' => $this->options['message'] ?? 'Please find your invoice attached.',
                'currency' => $this->invoice->currency ?? 'GHS',
            ];

            // Generate PDF attachment
            $pdf = $this->generateInvoicePdf($data);
            
            // Send email
            Mail::send('emails.invoice', $data, function ($message) use ($data, $pdf) {
                $message->to($data['email'])
                        ->subject($data['subject'])
                        ->attachData($pdf->output(), 'invoice-' . $this->invoice->invoice_number . '.pdf', [
                            'mime' => 'application/pdf',
                        ]);
            });

            // Mark invoice as sent
            $this->invoice->update([
                'sent_at' => now(),
                'sent_to' => $this->email,
            ]);

            Log::info('Invoice email sent successfully', [
                'invoice_number' => $this->invoice->invoice_number,
                'email' => $this->email,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send invoice email', [
                'invoice_number' => $this->invoice->invoice_number,
                'email' => $this->email,
                'error' => $e->getMessage(),
            ]);

            // Re-throw if retry is needed
            throw $e;
        }
    }

    /**
     * Generate invoice PDF
     *
     * @param array $data
     * @return \Barryvdh\DomPDF\PDF
     */
    protected function generateInvoicePdf(array $data)
    {
        $html = $this->generateInvoiceHtml($data);
        return Pdf::loadHTML($html);
    }

    /**
     * Generate invoice HTML
     *
     * @param array $data
     * @return string
     */
    protected function generateInvoiceHtml(array $data)
    {
        $invoice = $data['invoice'];
        $agreement = $data['agreement'];
        $currency = $data['currency'];

        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Invoice #' . $invoice->invoice_number . '</title>
            <style>
                body { font-family: "DejaVu Sans", sans-serif; margin: 40px; line-height: 1.6; color: #333; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 20px; }
                .header h1 { margin: 0; color: #2c3e50; }
                .invoice-details { margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px; }
                .amount { font-size: 24px; font-weight: bold; color: #27ae60; }
                .footer { margin-top: 50px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 20px; }
                .status-paid { color: #27ae60; font-weight: bold; }
                .status-pending { color: #f39c12; font-weight: bold; }
                .status-overdue { color: #e74c3c; font-weight: bold; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #ddd; }
                td { padding: 12px; border-bottom: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>INVOICE</h1>
                <h2>#' . $invoice->invoice_number . '</h2>
                <p>Date: ' . ($invoice->issue_date ? $invoice->issue_date->format('F j, Y') : date('F j, Y')) . '</p>
                <p>Due Date: ' . ($invoice->due_date ? $invoice->due_date->format('F j, Y') : 'N/A') . '</p>
            </div>

            <div class="invoice-details">
                <p><strong>Status:</strong> <span class="status-' . $invoice->status . '">' . ucfirst($invoice->status) . '</span></p>
                <p><strong>Amount:</strong> <span class="amount">' . $currency . ' ' . number_format($invoice->amount, 2) . '</span></p>
                <p><strong>Description:</strong> ' . $invoice->description . '</p>
                ' . ($agreement ? '<p><strong>Agreement:</strong> ' . $agreement->agreement_number . '</p>' : '') . '
            </div>

            <div class="footer">
                <p>Thank you for your business!</p>
                <p>Generated on: ' . date('F j, Y g:i A') . '</p>
            </div>
        </body>
        </html>';
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags()
    {
        return [
            'invoice',
            'email',
            'invoice:' . $this->invoice->id,
            'email:' . $this->email,
        ];
    }
}