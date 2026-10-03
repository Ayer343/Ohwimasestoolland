<?php

namespace App\Jobs;

use App\Models\AdminBillingRecord;
use App\Models\User;
use App\Traits\BillingHelperTrait;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class GenerateAgreementPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels, BillingHelperTrait;

    public $timeout = 120;
    public $tries = 3;
    public $backoff = [5, 10, 30];

    protected $agreementId;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct($agreementId, $userId = null)
    {
        $this->agreementId = $agreementId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $agreement = AdminBillingRecord::with(['superAdmin', 'developerSetting'])
                ->findOrFail($this->agreementId);

            // Prepare agreement data using trait
            $agreementData = $this->prepareAgreementData($agreement);

            // Generate the PDF (HTML fallback)
            $pdfPath = $this->generateSimpleAgreementPdf($agreementData, $agreement->id);

            // Update agreement with PDF path
            $agreement->update([
                'agreement_pdf_path' => $pdfPath,
                'agreement_generated_at' => now(),
                'agreement_generated_by' => $this->userId,
            ]);

            Log::info('Agreement PDF generated successfully', [
                'agreement_id' => $this->agreementId,
                'agreement_number' => $agreement->agreement_number,
                'pdf_path' => $pdfPath
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate agreement PDF', [
                'agreement_id' => $this->agreementId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->fail($e);
        }
    }

    /**
     * Generate simple agreement PDF (HTML fallback)
     */
    private function generateSimpleAgreementPdf($agreementData, $agreementId)
    {
        try {
            $filename = "agreement_{$agreementId}_" . time() . '.html';
            $directory = "agreements/unsigned";
            $path = "{$directory}/{$filename}";

            // Create directory if not exists
            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory, 0755, true);
            }

            // Create simple HTML agreement
            $html = $this->createAgreementHtml($agreementData);

            // Save as HTML
            Storage::put($path, $html);

            return $path;

        } catch (\Exception $e) {
            Log::error('Failed to generate simple agreement PDF', [
                'agreement_id' => $agreementId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create HTML agreement
     */
    private function createAgreementHtml($agreementData)
    {
        // Extract amount safely - handle both string and numeric values
        $amount = $agreementData['agreement_terms']['amount'];
        // Remove any commas and convert to float
        if (is_string($amount)) {
            $amount = floatval(str_replace(',', '', $amount));
        }
        $formattedAmount = number_format($amount, 2);
        
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <title>Agreement ' . htmlspecialchars($agreementData['agreement_number']) . '</title>
            <meta charset="UTF-8">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: Arial, Helvetica, sans-serif;
                    margin: 40px;
                    line-height: 1.6;
                    color: #333;
                }
                .container {
                    max-width: 900px;
                    margin: 0 auto;
                    background: white;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    padding-bottom: 20px;
                    border-bottom: 2px solid #2c3e50;
                }
                .header h1 {
                    color: #2c3e50;
                    margin-bottom: 10px;
                }
                .header h2 {
                    color: #555;
                    font-size: 18px;
                }
                .section {
                    margin-bottom: 25px;
                    page-break-inside: avoid;
                }
                .section-title {
                    font-size: 18px;
                    font-weight: bold;
                    border-bottom: 2px solid #2c3e50;
                    padding-bottom: 8px;
                    margin-bottom: 15px;
                    color: #2c3e50;
                }
                .info-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 20px;
                    margin-bottom: 20px;
                }
                .info-box {
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    border-left: 4px solid #3498db;
                }
                .info-box h4 {
                    margin-bottom: 10px;
                    color: #2c3e50;
                }
                .info-box p {
                    margin: 5px 0;
                    color: #555;
                }
                .terms-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 15px 0;
                }
                .terms-table td {
                    padding: 10px;
                    border: 1px solid #ddd;
                }
                .terms-table td:first-child {
                    font-weight: bold;
                    width: 30%;
                    background: #f8f9fa;
                }
                .signature-area {
                    margin-top: 50px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                }
                .signature-line {
                    display: inline-block;
                    width: 300px;
                    border-bottom: 1px solid #000;
                    margin: 20px 20px 20px 0;
                }
                .signature-box {
                    margin: 30px 0;
                    padding: 20px;
                    background: #f8f9fa;
                    border-radius: 8px;
                }
                .footer {
                    margin-top: 40px;
                    padding-top: 20px;
                    text-align: center;
                    font-size: 12px;
                    color: #777;
                    border-top: 1px solid #ddd;
                }
                @media print {
                    body {
                        margin: 0;
                        padding: 20px;
                    }
                    .no-print {
                        display: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>BILLING AGREEMENT</h1>
                    <h2>Agreement Number: ' . htmlspecialchars($agreementData['agreement_number']) . '</h2>
                    <p>Date: ' . $agreementData['agreement_date'] . '</p>
                </div>
                
                <div class="section">
                    <div class="section-title">PARTIES</div>
                    <div class="info-grid">
                        <div class="info-box">
                            <h4>Developer Information</h4>
                            <p><strong>Name:</strong> ' . htmlspecialchars($agreementData['developer_info']['name']) . '</p>
                            <p><strong>Email:</strong> ' . htmlspecialchars($agreementData['developer_info']['email']) . '</p>
                            <p><strong>Phone:</strong> ' . htmlspecialchars($agreementData['developer_info']['phone']) . '</p>
                        </div>
                        <div class="info-box">
                            <h4>Super Admin Information</h4>
                            <p><strong>Name:</strong> ' . htmlspecialchars($agreementData['super_admin_info']['name']) . '</p>
                            <p><strong>Email:</strong> ' . htmlspecialchars($agreementData['super_admin_info']['email']) . '</p>
                            <p><strong>Phone:</strong> ' . htmlspecialchars($agreementData['super_admin_info']['phone']) . '</p>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">AGREEMENT TERMS</div>
                    <table class="terms-table">
                        <tr>
                            <td>Amount</td>
                            <td>' . $agreementData['agreement_terms']['currency'] . ' ' . $formattedAmount . '</td>
                        </tr>
                        <tr>
                            <td>Billing Frequency</td>
                            <td>' . ucfirst($agreementData['agreement_terms']['billing_frequency']) . '</td>
                        </tr>
                        <tr>
                            <td>Start Date</td>
                            <td>' . $agreementData['agreement_terms']['start_date'] . '</td>
                        </tr>
                        <tr>
                            <td>Due Date</td>
                            <td>' . ($agreementData['agreement_terms']['due_date'] ?? 'N/A') . '</td>
                        </tr>
                        <tr>
                            <td>Description</td>
                            <td>' . nl2br(htmlspecialchars($agreementData['agreement_terms']['description'])) . '</td>
                        </tr>
                        <tr>
                            <td>Payment Method</td>
                            <td>' . ucfirst(str_replace('_', ' ', $agreementData['agreement_terms']['payment_method'])) . '</td>
                        </tr>
                        <tr>
                            <td>Payment Details</td>
                            <td>' . nl2br(htmlspecialchars($agreementData['agreement_terms']['payment_details'])) . '</td>
                        </tr>
                    </table>
                </div>
                
                <div class="section">
                    <div class="section-title">LEGAL TERMS</div>';
                    
        foreach ($agreementData['legal_terms'] as $title => $content) {
            $html .= '<div class="info-box" style="margin-bottom: 15px;">
                        <h4>' . htmlspecialchars($title) . '</h4>
                        <p>' . nl2br(htmlspecialchars($content)) . '</p>
                      </div>';
        }
        
        $html .= '</div>
                
                <div class="signature-area">
                    <div class="section-title">SIGNATURES</div>
                    <div class="signature-box">
                        <p><strong>Developer Signature:</strong></p>
                        <div class="signature-line"></div>
                        <p>Date: ___________________</p>
                        <p>Printed Name: ' . htmlspecialchars($agreementData['developer_info']['name']) . '</p>
                    </div>
                    
                    <div class="signature-box">
                        <p><strong>Super Admin Signature:</strong></p>
                        <div class="signature-line"></div>
                        <p>Date: ___________________</p>
                        <p>Printed Name: ___________________</p>
                    </div>
                </div>
                
                <div class="footer">
                    <p>This agreement is generated electronically and is legally binding.</p>
                    <p>Generated on: ' . $agreementData['generated_at'] . '</p>
                    <p>© ' . date('Y') . ' Billing Management System</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('GenerateAgreementPdfJob failed permanently', [
            'agreement_id' => $this->agreementId,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}