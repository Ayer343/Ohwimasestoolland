<?php
namespace App\Traits;

use App\Models\User;
use App\Models\AdminBillingRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

trait BillingHelperTrait
{
    /**
     * Get payment methods configuration
     */
    protected function getPaymentMethodsConfiguration()
    {
        return [
            'mtn' => [
                'name' => 'MTN Mobile Money',
                'icon' => 'fas fa-mobile-alt',
                'instructions' => 'Send payment to developer\'s MTN number',
                'required_fields' => ['payment_mobile_number']
            ],
            'telecel' => [
                'name' => 'Telecel (Vodafone) Cash',
                'icon' => 'fas fa-mobile-alt',
                'instructions' => 'Send payment to developer\'s Telecel number',
                'required_fields' => ['payment_mobile_number']
            ],
            'airteltigo' => [
                'name' => 'AirtelTigo Money',
                'icon' => 'fas fa-mobile-alt',
                'instructions' => 'Send payment to developer\'s AirtelTigo number',
                'required_fields' => ['payment_mobile_number']
            ],
            'bank_transfer' => [
                'name' => 'Bank Transfer',
                'icon' => 'fas fa-university',
                'instructions' => 'Transfer to developer\'s bank account',
                'required_fields' => ['payment_account_name', 'payment_account_number', 'payment_bank_name']
            ],
            'paystack' => [
                'name' => 'Paystack (Online Payment)',
                'icon' => 'fas fa-credit-card',
                'instructions' => 'Online payment via Paystack',
                'required_fields' => []
            ],
            'cash' => [
                'name' => 'Cash',
                'icon' => 'fas fa-money-bill',
                'instructions' => 'Physical cash payment',
                'required_fields' => []
            ],
            'mobile_money' => [
                'name' => 'Mobile Money',
                'icon' => 'fas fa-mobile-alt',
                'instructions' => 'Mobile money payment',
                'required_fields' => ['payment_mobile_number']
            ],
        ];
    }

    /**
     * Save signature image
     */
    protected function saveSignatureImage($signatureData, $agreementId, $userId)
    {
        try {
            $directory = "agreements/signatures/{$agreementId}";
            $filename = "signature_{$userId}_" . time() . ".png";
            $path = "{$directory}/{$filename}";
            
            // Create directory if not exists
            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory, 0755, true);
            }
            
            // Decode and save signature
            if (strpos($signatureData, 'data:image') === 0) {
                // Base64 image data
                list($type, $data) = explode(';', $signatureData);
                list(, $data) = explode(',', $data);
                $data = base64_decode($data);
                
                Storage::put($path, $data);
            } else {
                // Text signature - create image
                $image = $this->createTextSignatureImage($signatureData);
                Storage::put($path, $image);
            }
            
            return $path;
            
        } catch (\Exception $e) {
            Log::error('Failed to save signature image: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create text signature image
     */
    protected function createTextSignatureImage($text)
    {
        // Create a simple image with text signature
        $width = 400;
        $height = 150;
        
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        imagefilledrectangle($image, 0, 0, $width, $height, $white);
        
        // Add border
        imagerectangle($image, 0, 0, $width-1, $height-1, $black);
        
        // Add text (centered)
        $fontSize = 5;
        $textWidth = imagefontwidth($fontSize) * strlen($text);
        $textHeight = imagefontheight($fontSize);
        $x = ($width - $textWidth) / 2;
        $y = ($height - $textHeight) / 2;
        
        imagestring($image, $fontSize, $x, $y, $text, $black);
        
        // Add label
        imagestring($image, 3, 10, $height - 20, 'Signature', $black);
        
        // Capture output
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);
        
        return $imageData;
    }

    /**
     * Check if signatures are complete
     */
    protected function checkSignaturesComplete($agreementId)
    {
        $signatures = \App\Models\AgreementSignature::where('agreement_id', $agreementId)
            ->where('status', 'verified')
            ->get();
        
        $hasDeveloper = $signatures->where('signature_type', 'developer')->count() > 0;
        $hasSuperAdmin = $signatures->where('signature_type', 'super_admin')->count() > 0;
        
        return [
            'complete' => $hasDeveloper && $hasSuperAdmin,
            'developer_signed' => $hasDeveloper,
            'super_admin_signed' => $hasSuperAdmin,
            'signature_count' => $signatures->count(),
        ];
    }

    /**
     * Get signature status
     */
    protected function getSignatureStatus(AdminBillingRecord $agreement)
    {
        $signatures = $agreement->signatures()->where('status', 'verified')->get();
        
        return [
            'developer_signed' => $signatures->where('signature_type', 'developer')->count() > 0,
            'super_admin_signed' => $signatures->where('signature_type', 'super_admin')->count() > 0,
            'all_signed' => $signatures->where('signature_type', 'developer')->count() > 0 &&
                           $signatures->where('signature_type', 'super_admin')->count() > 0,
            'signature_count' => $signatures->count(),
        ];
    }

    /**
     * Prepare agreement data for display
     */
    protected function prepareAgreementData(AdminBillingRecord $agreement)
    {
        $developerSettings = $agreement->developerSetting;
        $superAdmin = $agreement->superAdmin;
        
        return [
            'agreement_number' => $agreement->agreement_number,
            'agreement_date' => $agreement->created_at->format('F j, Y'),
            'effective_date' => $agreement->start_date ? Carbon::parse($agreement->start_date)->format('F j, Y') : 'N/A',
            
            'developer_info' => [
                'name' => $developerSettings->developer_name ?? 'Developer',
                'company' => $developerSettings->developer_company ?? 'N/A',
                'email' => $developerSettings->developer_email ?? 'N/A',
                'phone' => $developerSettings->developer_phone ?? 'N/A',
                'address' => $developerSettings->developer_address ?? 'N/A',
            ],
            
            'super_admin_info' => [
                'name' => $superAdmin->name ?? 'N/A',
                'email' => $superAdmin->email ?? 'N/A',
                'phone' => $superAdmin->phone ?? 'N/A',
            ],
            
            'agreement_terms' => [
                'amount' => number_format($agreement->amount, 2),
                'currency' => $agreement->currency,
                'billing_frequency' => ucfirst($agreement->billing_frequency),
                'description' => $agreement->description,
                'notes' => $agreement->notes,
                'start_date' => $agreement->start_date ? Carbon::parse($agreement->start_date)->format('F j, Y') : 'N/A',
                'end_date' => $agreement->end_date ? Carbon::parse($agreement->end_date)->format('F j, Y') : 'N/A',
                'payment_method' => $agreement->payment_method ?? 'bank_transfer',
                'payment_details' => $this->getPaymentDetails($agreement),
                'category' => $agreement->category ?? 'other',
                'auto_renew' => $agreement->auto_renew ? 'Yes' : 'No',
                'renewal_notice_days' => $agreement->renewal_notice_days ?? 30,
            ],
            
            'legal_terms' => $this->getLegalTerms(),
            
            'generated_at' => now()->format('F j, Y \a\t g:i A'),
        ];
    }

    /**
     * Get payment details from agreement
     */
    protected function getPaymentDetails(AdminBillingRecord $agreement)
    {
        $method = $agreement->payment_method ?? 'bank_transfer';
        
        switch ($method) {
            case 'mtn':
            case 'telecel':
            case 'airteltigo':
                return "Mobile Money ({$method}): " . ($agreement->payment_mobile_number ?? 'N/A');
                
            case 'bank_transfer':
                return "Bank Transfer: " .
                       "Account Name: " . ($agreement->payment_account_name ?? 'N/A') . ", " .
                       "Account Number: " . ($agreement->payment_account_number ?? 'N/A') . ", " .
                       "Bank: " . ($agreement->payment_bank_name ?? 'N/A') . 
                       ($agreement->payment_bank_branch ? ", Branch: " . $agreement->payment_bank_branch : '');
                
            default:
                return ucfirst(str_replace('_', ' ', $method));
        }
    }

    /**
     * Get legal terms for agreement
     */
    protected function getLegalTerms()
    {
        return [
            '1. Payment Terms' => 'All payments are due according to the agreed schedule. Late payments may incur additional fees as specified in this agreement.',
            '2. Service Delivery' => 'Developer agrees to provide services as described. Super Admin agrees to provide necessary access and information.',
            '3. Termination' => 'Either party may terminate this agreement with 30 days written notice.',
            '4. Confidentiality' => 'Both parties agree to maintain confidentiality of proprietary information.',
            '5. Governing Law' => 'This agreement shall be governed by the laws of Ghana.',
            '6. Electronic Signature' => 'This agreement may be signed electronically, which shall be as binding as a handwritten signature.',
        ];
    }

    /**
     * Calculate due date based on billing frequency
     */
    protected function calculateDueDateBasedOnFrequency($startDate, $frequency)
    {
        $start = Carbon::parse($startDate);
        
        return match($frequency) {
            'weekly' => $start->addWeek(),
            'monthly' => $start->addMonth(),
            'quarterly' => $start->addMonths(3),
            'yearly' => $start->addYear(),
            'one_time' => $start->addMonth(), // Default one month for one-time payments
            default => $start->addMonth(),
        };
    }

    /**
     * Calculate next billing date
     */
    protected function calculateNextBillingDate($startDate, $cycle, $currentDate = null)
    {
        $current = $currentDate ? Carbon::parse($currentDate) : Carbon::now();
        $start = Carbon::parse($startDate);
        
        switch ($cycle) {
            case 'monthly':
                $next = $start->copy();
                while ($next->lte($current)) {
                    $next->addMonth();
                }
                return $next;
                
            case 'quarterly':
                $next = $start->copy();
                while ($next->lte($current)) {
                    $next->addMonths(3);
                }
                return $next;
                
            case 'yearly':
                $next = $start->copy();
                while ($next->lte($current)) {
                    $next->addYear();
                }
                return $next;
                
            case 'custom':
            default:
                return $start->copy()->addMonth();
        }
    }

    /**
     * Sanitize input
     */
    protected function sanitizeInput($value)
    {
        if (is_string($value)) {
            return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
        }
        return $value;
    }

    /**
     * Sanitize log data
     */
    protected function sanitizeLogData(array $data)
    {
        $sensitiveFields = [
            'password',
            'secret',
            'token',
            'key',
            'credit_card',
            'cvv',
            'ssn',
            'developer_smtp_password',
            'developer_secret_key',
            'payment_mobile_number',
            'payment_account_number',
            'payment_account_name',
            'signature_data'
        ];
        
        $sanitized = $data;
        
        foreach ($sanitized as $key => $value) {
            foreach ($sensitiveFields as $sensitive) {
                if (stripos($key, $sensitive) !== false && !empty($value)) {
                    $sanitized[$key] = '[REDACTED]';
                }
            }
        }
        
        return $sanitized;
    }

    /**
     * Generate agreement number
     */
    protected function generateAgreementNumber()
    {
        return 'AGR-' . strtoupper(Str::random(6)) . '-' . date('Ymd');
    }

    /**
     * Get user name by ID
     */
    protected function getUserName($userId)
    {
        if (!$userId) return 'N/A';
        
        $user = User::find($userId);
        return $user ? $user->name : 'N/A';
    }
}