<?php

namespace App\Services;

use App\Models\WorkerBadge;
use App\Models\ConstructionWorker;
use Illuminate\Support\Facades\Mail;
use App\Mail\WorkerBadgeMail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use PDF;
use Exception;

class BadgeService
{
    /**
     * Generate or update a badge for a worker
     */
    public function generateBadge(ConstructionWorker $worker)
    {
        try {
            // Create or update badge
            $badge = WorkerBadge::updateOrCreate(
                ['construction_worker_id' => $worker->id],
                [
                    'construction_contract_id' => $worker->contract_id,
                    'valid_from' => $worker->start_date ?? now(),
                    'valid_until' => $worker->end_date ?? now()->addMonths(6),
                    'is_active' => $worker->status === 'active',
                    'email_sent_to' => $worker->email,
                ]
            );

            // If email was updated, update the badge
            if ($worker->email && $badge->email_sent_to !== $worker->email) {
                $badge->email_sent_to = $worker->email;
            }

            // Generate badge number if not exists
            if (!$badge->badge_number) {
                $badge->generateBadgeNumber();
            }

            // Generate QR code if not exists
            if (!$badge->qr_code) {
                $badge->generateQRCode();
            }

            $badge->save();

            Log::info('Badge generated successfully', [
                'badge_id' => $badge->id,
                'worker_id' => $worker->id,
                'badge_number' => $badge->badge_number,
                'email' => $badge->email_sent_to,
            ]);

            return $badge;

        } catch (Exception $e) {
            Log::error('Failed to generate badge: ' . $e->getMessage(), [
                'worker_id' => $worker->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate QR code image using chillerlan (Pure PHP, no Imagick needed)
     * This is the primary method that works on Windows
     */
    public function generateQRCodeImage($qrCodeData)
    {
        $fileName = 'qrcodes/' . md5($qrCodeData) . '.png';
        $fullPath = Storage::disk('public')->path($fileName);
        
        // If file already exists, return the path
        if (Storage::disk('public')->exists($fileName)) {
            Log::debug('QR Code already exists', ['file' => $fileName]);
            return $fullPath;
        }

        // Ensure directory exists
        $this->ensureDirectoryExists();

        try {
            // Use chillerlan as the primary method
            Log::info('Generating QR code with chillerlan');
            $qrCode = $this->generateQRWithChillerlan($qrCodeData);
            
            if ($qrCode) {
                Storage::disk('public')->put($fileName, $qrCode);
                Log::info('QR Code generated successfully using chillerlan', [
                    'file' => $fileName,
                    'size' => strlen($qrCode),
                ]);
                return $fullPath;
            }
        } catch (Exception $e) {
            Log::warning('chillerlan QR generation failed: ' . $e->getMessage());
        }

        // If chillerlan fails, try the visual fallback
        try {
            Log::info('Falling back to visual QR generation');
            $qrCode = $this->generateQRFallback($qrCodeData);
            
            if ($qrCode) {
                Storage::disk('public')->put($fileName, $qrCode);
                Log::info('QR Code generated using visual fallback', [
                    'file' => $fileName,
                    'size' => strlen($qrCode),
                ]);
                return $fullPath;
            }
        } catch (Exception $e) {
            Log::warning('Visual fallback failed: ' . $e->getMessage());
        }

        // Ultimate fallback: create a text fallback
        Log::error('All QR generation methods failed. Creating text fallback.');
        return $this->createTextFallback($fileName, $qrCodeData);
    }

    /**
     * Generate QR code using chillerlan/php-qrcode v6.0
     */
    protected function generateQRWithChillerlan($data)
    {
        if (!class_exists('chillerlan\\QRCode\\QRCode')) {
            throw new Exception('chillerlan/php-qrcode not installed. Run: composer require chillerlan/php-qrcode');
        }

        // Use higher version for more data capacity
        $options = new \chillerlan\QRCode\QROptions([
            'version' => 10,
            'eccLevel' => 'H',
            'scale' => 10,
            'outputType' => 'png',
            'bgColor' => '#ffffff',
            'fgColor' => '#000000',
            'drawLightModules' => false,
            'drawCircularModules' => false,
        ]);

        $qrcode = new \chillerlan\QRCode\QRCode($options);
        $imageData = $qrcode->render($data);
        
        if (empty($imageData)) {
            throw new Exception('chillerlan QR generation returned empty result');
        }
        
        return $imageData;
    }

    /**
     * Visual fallback using GD (Pure PHP)
     * Creates a QR-like visual pattern
     */
    protected function generateQRFallback($data)
    {
        if (!function_exists('imagecreate')) {
            throw new Exception('GD extension not available');
        }

        $size = 300;
        $image = imagecreate($size, $size);
        
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $gray = imagecolorallocate($image, 200, 200, 200);
        $blue = imagecolorallocate($image, 37, 99, 235);
        $lightBlue = imagecolorallocate($image, 219, 234, 254);
        
        imagefilledrectangle($image, 0, 0, $size, $size, $lightBlue);
        
        $border = 20;
        imagerectangle($image, $border, $border, $size - $border, $size - $border, $blue);
        imagerectangle($image, $border + 2, $border + 2, $size - $border - 2, $size - $border - 2, $blue);
        
        $seed = crc32($data);
        mt_srand($seed);
        
        $blockSize = 12;
        $margin = 40;
        $cols = floor(($size - 2 * $margin) / $blockSize);
        
        for ($i = 0; $i < $cols; $i++) {
            for ($j = 0; $j < $cols; $j++) {
                $x = $margin + $j * $blockSize;
                $y = $margin + $i * $blockSize;
                
                $value = crc32($data . $i . $j);
                $random = ($value % 100);
                $color = $random > 45 ? $black : $white;
                
                if (($i + $j) % 3 === 0 && $random > 30) {
                    $color = $black;
                }
                
                imagefilledrectangle($image, $x, $y, $x + $blockSize - 1, $y + $blockSize - 1, $color);
            }
        }
        
        $this->addFinderPattern($image, $margin, $margin, $blockSize, $black, $white);
        $this->addFinderPattern($image, $size - $margin - 7 * $blockSize, $margin, $blockSize, $black, $white);
        $this->addFinderPattern($image, $margin, $size - $margin - 7 * $blockSize, $blockSize, $black, $white);
        
        for ($i = 0; $i < $cols; $i++) {
            $x = $margin + $i * $blockSize;
            $color = ($i % 2 === 0) ? $black : $white;
            imagefilledrectangle($image, $x, $margin + 6 * $blockSize, $x + $blockSize - 1, $margin + 6 * $blockSize + $blockSize - 1, $color);
            imagefilledrectangle($image, $margin + 6 * $blockSize, $x, $margin + 6 * $blockSize + $blockSize - 1, $x + $blockSize - 1, $color);
        }
        
        $text = "QR CODE";
        $fontSize = 5;
        $textWidth = imagefontwidth($fontSize) * strlen($text);
        $textX = ($size - $textWidth) / 2;
        imagestring($image, $fontSize, $textX, $size - 35, $text, $blue);
        
        $hash = strtoupper(substr(md5($data), 0, 8));
        $hashWidth = imagefontwidth(3) * strlen($hash);
        $hashX = ($size - $hashWidth) / 2;
        imagestring($image, 3, $hashX, $size - 25, $hash, $gray);
        
        ob_start();
        imagepng($image);
        $content = ob_get_clean();
        imagedestroy($image);
        
        if (empty($content)) {
            throw new Exception('Fallback QR generation returned empty result');
        }
        
        return $content;
    }

    /**
     * Add finder pattern to QR image
     */
    protected function addFinderPattern($image, $x, $y, $blockSize, $black, $white)
    {
        for ($i = 0; $i < 7; $i++) {
            for ($j = 0; $j < 7; $j++) {
                $isBorder = ($i == 0 || $i == 6 || $j == 0 || $j == 6);
                $color = $isBorder ? $black : $white;
                imagefilledrectangle($image, $x + $j * $blockSize, $y + $i * $blockSize, $x + $j * $blockSize + $blockSize - 1, $y + $i * $blockSize + $blockSize - 1, $color);
            }
        }
        
        for ($i = 2; $i < 5; $i++) {
            for ($j = 2; $j < 5; $j++) {
                imagefilledrectangle($image, $x + $j * $blockSize, $y + $i * $blockSize, $x + $j * $blockSize + $blockSize - 1, $y + $i * $blockSize + $blockSize - 1, $black);
            }
        }
    }

    /**
     * Create text fallback when image generation fails
     */
    protected function createTextFallback($fileName, $qrCodeData)
    {
        try {
            $htmlFile = str_replace('.png', '.html', $fileName);
            $htmlContent = $this->generateHTMLFallback($qrCodeData);
            Storage::disk('public')->put($htmlFile, $htmlContent);
            
            $txtFile = str_replace('.png', '.txt', $fileName);
            Storage::disk('public')->put($txtFile, $qrCodeData);
            
            Log::info('Created text fallback for QR code', [
                'html_file' => $htmlFile,
                'txt_file' => $txtFile,
            ]);
            
            return Storage::disk('public')->path($htmlFile);
        } catch (Exception $e) {
            Log::error('Failed to create text fallback: ' . $e->getMessage());
            return $this->createPlaceholderImage($fileName);
        }
    }

    /**
     * Create a placeholder image when everything fails
     */
    protected function createPlaceholderImage($fileName)
    {
        try {
            if (function_exists('imagecreate')) {
                $width = 300;
                $height = 300;
                $image = imagecreate($width, $height);
                
                $white = imagecolorallocate($image, 255, 255, 255);
                $black = imagecolorallocate($image, 0, 0, 0);
                $gray = imagecolorallocate($image, 200, 200, 200);
                
                imagefilledrectangle($image, 0, 0, $width, $height, $white);
                imagerectangle($image, 10, 10, $width - 10, $height - 10, $black);
                imagerectangle($image, 12, 12, $width - 12, $height - 12, $gray);
                
                $icon = "QR";
                $iconWidth = imagefontwidth(5) * strlen($icon);
                $iconX = ($width - $iconWidth) / 2;
                imagestring($image, 5, $iconX, 120, $icon, $black);
                
                $text = "Code Unavailable";
                $textWidth = imagefontwidth(4) * strlen($text);
                $textX = ($width - $textWidth) / 2;
                imagestring($image, 4, $textX, 150, $text, $gray);
                
                $subText = "Contact Support";
                $subWidth = imagefontwidth(3) * strlen($subText);
                $subX = ($width - $subWidth) / 2;
                imagestring($image, 3, $subX, 170, $subText, $gray);
                
                ob_start();
                imagepng($image);
                $content = ob_get_clean();
                imagedestroy($image);
                
                Storage::disk('public')->put($fileName, $content);
                Log::info('Created placeholder image for QR code');
                return Storage::disk('public')->path($fileName);
            }
        } catch (Exception $e) {
            Log::error('Failed to create placeholder image: ' . $e->getMessage());
        }
        
        $defaultPath = storage_path('app/public/qrcodes/default.png');
        if (!file_exists($defaultPath)) {
            $this->createDefaultImage();
        }
        return $defaultPath;
    }

    /**
     * Create default image
     */
    protected function createDefaultImage()
    {
        try {
            if (function_exists('imagecreate')) {
                $width = 300;
                $height = 300;
                $image = imagecreate($width, $height);
                
                $white = imagecolorallocate($image, 255, 255, 255);
                $black = imagecolorallocate($image, 0, 0, 0);
                
                imagefilledrectangle($image, 0, 0, $width, $height, $white);
                imagerectangle($image, 5, 5, $width - 5, $height - 5, $black);
                
                $text = "QR Code System";
                $textWidth = imagefontwidth(5) * strlen($text);
                $textX = ($width - $textWidth) / 2;
                imagestring($image, 5, $textX, 130, $text, $black);
                
                $subText = "Ready";
                $subWidth = imagefontwidth(4) * strlen($subText);
                $subX = ($width - $subWidth) / 2;
                imagestring($image, 4, $subX, 155, $subText, $black);
                
                ob_start();
                imagepng($image);
                $content = ob_get_clean();
                imagedestroy($image);
                
                $path = storage_path('app/public/qrcodes');
                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }
                
                File::put($path . '/default.png', $content);
                Log::info('Created default QR placeholder image');
            }
        } catch (Exception $e) {
            Log::error('Failed to create default image: ' . $e->getMessage());
        }
    }

    /**
     * Generate HTML fallback
     */
    protected function generateHTMLFallback($qrCodeData)
    {
        $encodedData = htmlspecialchars($qrCodeData);
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Worker Badge QR Code</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    margin: 0;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                }
                .qr-container {
                    background: white;
                    padding: 40px;
                    border-radius: 16px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    text-align: center;
                    max-width: 420px;
                    width: 90%;
                }
                .qr-icon {
                    font-size: 72px;
                    margin-bottom: 15px;
                }
                .qr-title {
                    font-size: 22px;
                    font-weight: 700;
                    color: #1a2332;
                    margin-bottom: 5px;
                }
                .qr-subtitle {
                    font-size: 14px;
                    color: #6b7280;
                    margin-bottom: 20px;
                }
                .qr-box {
                    background: #f8fafc;
                    border: 2px solid #e2e8f0;
                    border-radius: 12px;
                    padding: 20px;
                    margin: 20px 0;
                    word-break: break-all;
                    font-family: 'Courier New', monospace;
                    font-size: 12px;
                    color: #1a2332;
                    line-height: 1.6;
                }
                .qr-box .label {
                    font-size: 10px;
                    text-transform: uppercase;
                    color: #94a3b8;
                    font-weight: 600;
                    letter-spacing: 0.5px;
                }
                .qr-instructions {
                    font-size: 13px;
                    color: #64748b;
                    margin: 15px 0;
                    line-height: 1.6;
                }
                .qr-badge {
                    display: inline-block;
                    background: #2563eb;
                    color: white;
                    padding: 8px 24px;
                    border-radius: 50px;
                    font-size: 13px;
                    font-weight: 600;
                    margin-top: 10px;
                }
                .qr-footer {
                    margin-top: 20px;
                    padding-top: 15px;
                    border-top: 1px solid #e2e8f0;
                    font-size: 11px;
                    color: #94a3b8;
                }
                .qr-copy-btn {
                    background: #f1f5f9;
                    border: none;
                    padding: 8px 20px;
                    border-radius: 8px;
                    color: #475569;
                    cursor: pointer;
                    font-size: 12px;
                    margin-top: 10px;
                    transition: all 0.2s;
                }
                .qr-copy-btn:hover {
                    background: #e2e8f0;
                }
            </style>
        </head>
        <body>
            <div class="qr-container">
                <div class="qr-icon">📱</div>
                <div class="qr-title">Worker Badge QR Code</div>
                <div class="qr-subtitle">Security Verification Code</div>
                
                <div class="qr-box">
                    <div class="label">Verification Code</div>
                    <div style="margin-top: 8px;">{$encodedData}</div>
                </div>
                
                <button class="qr-copy-btn" onclick="copyToClipboard()">📋 Copy Code</button>
                
                <div class="qr-instructions">
                    <strong>How to use:</strong><br>
                    Present this code at the security post for verification.
                    Security personnel will scan or enter this code to validate your identity.
                </div>
                
                <div class="qr-badge">🔐 Valid Worker ID</div>
                
                <div class="qr-footer">
                    <div>This is a digital worker identification code.</div>
                    <div style="margin-top: 4px;">Please keep this code secure.</div>
                </div>
            </div>
            
            <script>
                function copyToClipboard() {
                    const code = '{$encodedData}';
                    navigator.clipboard.writeText(code).then(() => {
                        const btn = document.querySelector('.qr-copy-btn');
                        const originalText = btn.textContent;
                        btn.textContent = '✅ Copied!';
                        setTimeout(() => {
                            btn.textContent = originalText;
                        }, 2000);
                    });
                }
            </script>
        </body>
        </html>
        HTML;
    }

    /**
     * Ensure the QR code directory exists
     */
    protected function ensureDirectoryExists()
    {
        try {
            if (!Storage::disk('public')->exists('qrcodes')) {
                Storage::disk('public')->makeDirectory('qrcodes', 0755);
                Log::info('Created qrcodes directory');
            }
        } catch (Exception $e) {
            Log::error('Failed to create qrcodes directory: ' . $e->getMessage());
            
            $path = storage_path('app/public/qrcodes');
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
                Log::info('Created qrcodes directory with PHP mkdir');
            }
        }
    }

    /**
     * Generate badge PDF with fallback for missing QR images
     */
    public function generateBadgePDF(WorkerBadge $badge)
    {
        try {
            $data = $badge->getCardData();
            
            $qrCodePath = null;
            try {
                $qrCodePath = $this->generateQRCodeImage($badge->qr_code);
                
                if (!file_exists($qrCodePath) || !is_readable($qrCodePath)) {
                    Log::warning('QR code file not readable, using fallback', [
                        'path' => $qrCodePath,
                    ]);
                    $qrCodePath = null;
                }
            } catch (Exception $e) {
                Log::warning('Failed to generate QR code for PDF: ' . $e->getMessage());
                $qrCodePath = null;
            }

            $pdfData = array_merge($data, [
                'qr_code_path' => $qrCodePath,
                'qr_code_text' => $badge->qr_code ?? 'QR Code Not Available',
                'has_qr_code' => $qrCodePath && file_exists($qrCodePath) && filesize($qrCodePath) > 0,
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'status' => $badge->status,
                'job_title' => $badge->worker->job_title ?? null,
                'specialization' => $badge->worker->specialization ?? null,
            ]);

            try {
                $pdf = PDF::loadView('pdf.worker-badge', $pdfData);
                $pdf->setPaper('a4', 'portrait');
                $pdf->setOptions([
                    'defaultFont' => 'sans-serif',
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true,
                    'dpi' => 150,
                ]);
                return $pdf;
            } catch (Exception $e) {
                Log::error('PDF generation failed: ' . $e->getMessage());
                return $this->generateSimplePDF($badge);
            }
        } catch (Exception $e) {
            Log::error('Failed to generate badge PDF: ' . $e->getMessage(), [
                'badge_id' => $badge->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate a simple text-based PDF as fallback
     */
    protected function generateSimplePDF(WorkerBadge $badge)
    {
        try {
            $data = [
                'badge_number' => $badge->badge_number,
                'worker_name' => $badge->worker->full_name,
                'trade' => $badge->worker->trade ?? 'N/A',
                'job_title' => $badge->worker->job_title ?? null,
                'specialization' => $badge->worker->specialization ?? null,
                'contract_number' => $badge->contract->contract_number,
                'valid_from' => $badge->valid_from ? $badge->valid_from->format('d M Y') : 'N/A',
                'valid_until' => $badge->valid_until ? $badge->valid_until->format('d M Y') : 'N/A',
                'company_name' => $badge->contract->contractor->company_name ?? 'Construction Company',
                'qr_code_text' => $badge->qr_code ?? 'QR Code Not Available',
                'has_qr_code' => false,
                'status' => $badge->status,
                'generated_at' => now()->format('Y-m-d H:i:s'),
            ];

            $pdf = PDF::loadView('pdf.worker-badge-simple', $data);
            return $pdf;
        } catch (Exception $e) {
            Log::error('Simple PDF generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send badge email with retry mechanism
     */
    public function sendBadgeEmail(WorkerBadge $badge, $maxRetries = 3)
    {
        if (empty($badge->email_sent_to)) {
            Log::error('Cannot send badge email: No email address provided', [
                'badge_id' => $badge->id,
                'worker_id' => $badge->construction_worker_id,
            ]);
            throw new Exception('Worker does not have an email address.');
        }

        $attempts = 0;
        $lastError = null;

        while ($attempts < $maxRetries) {
            try {
                $attempts++;
                Log::info('Attempting to send badge email', [
                    'badge_id' => $badge->id,
                    'attempt' => $attempts,
                    'max_retries' => $maxRetries,
                    'email' => $badge->email_sent_to,
                ]);

                $pdf = $this->generateBadgePDF($badge);
                Mail::to($badge->email_sent_to)->send(new WorkerBadgeMail($badge, $pdf));

                $badge->update([
                    'email_sent_at' => now(),
                ]);

                Log::info('Badge email sent successfully', [
                    'badge_id' => $badge->id,
                    'email' => $badge->email_sent_to,
                    'attempts' => $attempts,
                ]);

                return true;

            } catch (Exception $e) {
                $lastError = $e->getMessage();
                Log::warning('Badge email sending attempt failed', [
                    'badge_id' => $badge->id,
                    'attempt' => $attempts,
                    'error' => $lastError,
                ]);

                if ($attempts >= $maxRetries) {
                    Log::error('All badge email attempts failed', [
                        'badge_id' => $badge->id,
                        'last_error' => $lastError,
                    ]);
                    throw $e;
                }

                sleep(pow(2, $attempts - 1));
            }
        }

        return false;
    }

    /**
     * Resend badge with fresh QR code
     */
    public function resendBadge(WorkerBadge $badge)
    {
        try {
            $badge->generateQRCode();
            $badge->save();

            Log::info('QR code regenerated for resend', [
                'badge_id' => $badge->id,
                'new_qr_code' => $badge->qr_code,
            ]);
            
            return $this->sendBadgeEmail($badge);
        } catch (Exception $e) {
            Log::error('Failed to resend badge: ' . $e->getMessage(), [
                'badge_id' => $badge->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify badge
     */
    public function verifyBadge($qrCode, $postId = null, $userId = null)
    {
        try {
            $badge = WorkerBadge::where('qr_code', $qrCode)->first();
            
            if (!$badge) {
                Log::warning('Invalid badge verification attempt', [
                    'qr_code' => $qrCode,
                    'post_id' => $postId,
                    'user_id' => $userId,
                ]);
                return [
                    'success' => false,
                    'message' => 'Invalid badge',
                    'status' => 'not_found'
                ];
            }

            Log::info('Badge verification attempt', [
                'badge_id' => $badge->id,
                'badge_number' => $badge->badge_number,
                'post_id' => $postId,
                'user_id' => $userId,
            ]);

            $result = $badge->verify($postId, $userId);

            Log::info('Badge verification result', [
                'badge_id' => $badge->id,
                'success' => $result['success'],
                'status' => $result['status'] ?? 'unknown',
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Badge verification failed: ' . $e->getMessage(), [
                'qr_code' => $qrCode,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Verification failed due to system error',
                'status' => 'error'
            ];
        }
    }

    /**
     * Get badge status
     */
    public function getBadgeStatus(WorkerBadge $badge)
    {
        try {
            return [
                'badge_number' => $badge->badge_number,
                'status' => $badge->status,
                'is_expired' => $badge->is_expired,
                'is_expiring_soon' => $badge->is_expiring_soon,
                'days_until_expiry' => $badge->days_until_expiry,
                'verification_count' => $badge->verification_count,
                'last_verified' => $badge->last_verified_at,
                'valid_from' => $badge->valid_from,
                'valid_until' => $badge->valid_until,
                'is_active' => $badge->is_active,
                'email_sent_at' => $badge->email_sent_at,
                'worker_name' => $badge->worker->full_name,
                'contract_number' => $badge->contract->contract_number,
            ];
        } catch (Exception $e) {
            Log::error('Failed to get badge status: ' . $e->getMessage(), [
                'badge_id' => $badge->id,
            ]);
            
            return [
                'badge_number' => $badge->badge_number,
                'status' => 'error',
                'error' => 'Failed to retrieve status',
            ];
        }
    }

    /**
     * Get card data with fallbacks
     */
    public function getCardData(WorkerBadge $badge)
    {
        try {
            return $badge->getCardData();
        } catch (Exception $e) {
            Log::error('Failed to get card data: ' . $e->getMessage(), [
                'badge_id' => $badge->id,
            ]);
            
            return [
                'badge_number' => $badge->badge_number ?? 'N/A',
                'worker_name' => $badge->worker->full_name ?? 'Unknown Worker',
                'trade' => $badge->worker->trade ?? 'N/A',
                'contract_number' => $badge->contract->contract_number ?? 'N/A',
                'valid_from' => $badge->valid_from ? $badge->valid_from->format('d M Y') : 'N/A',
                'valid_until' => $badge->valid_until ? $badge->valid_until->format('d M Y') : 'N/A',
                'company_name' => 'Construction Company',
                'qr_code' => $badge->qr_code ?? 'N/A',
            ];
        }
    }

    /**
     * Clean up old QR code files
     */
    public function cleanupOldQRCodes($days = 30)
    {
        try {
            $files = Storage::disk('public')->files('qrcodes');
            $deleted = 0;
            $now = now();

            foreach ($files as $file) {
                $lastModified = Storage::disk('public')->lastModified($file);
                $modifiedDate = \Carbon\Carbon::createFromTimestamp($lastModified);
                
                if ($modifiedDate->diffInDays($now) > $days) {
                    Storage::disk('public')->delete($file);
                    $deleted++;
                }
            }

            Log::info('QR code cleanup completed', [
                'deleted' => $deleted,
                'days' => $days,
            ]);

            return $deleted;
        } catch (Exception $e) {
            Log::error('QR code cleanup failed: ' . $e->getMessage());
            return 0;
        }
    }
}