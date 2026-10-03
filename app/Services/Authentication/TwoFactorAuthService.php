<?php

namespace App\Services\Authentication;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorAuthService
{
    protected Google2FA $google2fa;
    
    /**
     * Cache key prefix for 2FA codes
     */
    protected string $cachePrefix = '2fa_code:';
    
    /**
     * Code validity in minutes
     */
    protected int $codeValidity = 10;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Check if 2FA is enabled for user
     */
    public function isEnabled(User $user): bool
    {
        return (bool) ($user->two_factor_enabled ?? false);
    }

    /**
     * Enable 2FA for user
     */
    public function enable(User $user): void
    {
        if (!$user->two_factor_secret) {
            $user->two_factor_secret = $this->generateSecret();
        }
        
        $user->two_factor_enabled = true;
        $user->two_factor_enabled_at = now();
        $user->save();
    }

    /**
     * Disable 2FA for user
     */
    public function disable(User $user): void
    {
        $user->two_factor_enabled = false;
        $user->two_factor_secret = null;
        $user->two_factor_backup_codes = null;
        $user->save();
        
        // Clear any cached codes
        Cache::forget($this->cachePrefix . $user->id);
    }

    /**
     * Generate new 2FA secret using Google2FA
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Generate QR code URL for Google Authenticator
     */
    public function getQRCodeUrl(User $user): string
    {
        $company = config('app.name', 'Laravel App');
        $secret = $user->two_factor_secret;
        
        return $this->google2fa->getQRCodeUrl($company, $user->email, $secret);
    }

    /**
     * Generate QR code as SVG string (for inline display)
     */
    public function getQRCodeSvg(User $user): string
    {
        $qrCodeUrl = $this->getQRCodeUrl($user);
        
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        
        $writer = new Writer($renderer);
        return $writer->writeString($qrCodeUrl);
    }

    /**
     * Verify 2FA code using Google2FA
     */
    public function verify(User $user, string $code): bool
    {
        // Check backup codes first
        if ($this->verifyBackupCode($user, $code)) {
            return true;
        }
        
        // Verify with Google2FA
        if ($user->two_factor_secret) {
            // Allow a window of 1 code before/after for time drift
            return $this->google2fa->verifyKey($user->two_factor_secret, $code, 1);
        }
        
        // Fallback to email/SMS code
        return $this->verifyCode($user, $code);
    }

    /**
     * Generate backup codes
     */
    public function generateBackupCodes(User $user): array
    {
        $codes = [];
        
        for ($i = 0; $i < 8; $i++) {
            $codes[] = $this->generateBackupCode();
        }
        
        $user->two_factor_backup_codes = json_encode($codes);
        $user->save();
        
        return $codes;
    }

    /**
     * Verify backup code
     */
    protected function verifyBackupCode(User $user, string $code): bool
    {
        $backupCodes = json_decode($user->two_factor_backup_codes ?? '[]', true);
        
        if (!is_array($backupCodes)) {
            $backupCodes = [];
        }
        
        $index = array_search($code, $backupCodes);
        
        if ($index !== false) {
            // Remove used backup code
            unset($backupCodes[$index]);
            $user->two_factor_backup_codes = json_encode(array_values($backupCodes));
            $user->save();
            
            Log::info('Backup code used for 2FA', ['user_id' => $user->id]);
            return true;
        }
        
        return false;
    }

    /**
     * Generate a single backup code
     */
    protected function generateBackupCode(): string
    {
        return sprintf('%04d-%04d', random_int(0, 9999), random_int(0, 9999));
    }

    /**
     * Verify email/SMS code (fallback method)
     */
    protected function verifyCode(User $user, string $code): bool
    {
        $cachedCode = Cache::get($this->cachePrefix . $user->id);
        
        if ($cachedCode && hash_equals($cachedCode, $code)) {
            Cache::forget($this->cachePrefix . $user->id);
            return true;
        }
        
        return false;
    }

    /**
     * Send 2FA code via email (fallback when TOTP is not available)
     */
    public function sendCode(User $user): bool
    {
        $code = $this->generateCode();
        
        // Store code in cache
        Cache::put($this->cachePrefix . $user->id, $code, now()->addMinutes($this->codeValidity));
        
        // Send via preferred method
        $method = $user->two_factor_method ?? 'email';
        
        if ($method === 'email') {
            return $this->sendViaEmail($user, $code);
        } elseif ($method === 'sms') {
            return $this->sendViaSMS($user, $code);
        }
        
        return false;
    }

    /**
     * Generate a random 6-digit code (for email/SMS fallback)
     */
    protected function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Send code via email
     */
    protected function sendViaEmail(User $user, string $code): bool
    {
        try {
            $viewExists = view()->exists('emails.two-factor-code');
            
            if ($viewExists) {
                Mail::send('emails.two-factor-code', [
                    'user' => $user,
                    'code' => $code,
                    'validity' => $this->codeValidity
                ], function ($message) use ($user) {
                    $message->to($user->email)
                            ->subject('Your Two-Factor Authentication Code - ' . config('app.name'));
                });
            } else {
                Mail::raw("Your 2FA verification code is: {$code}\n\nThis code will expire in {$this->codeValidity} minutes.", function ($message) use ($user) {
                    $message->to($user->email)
                            ->subject('Your Two-Factor Authentication Code - ' . config('app.name'));
                });
            }
            
            Log::info('2FA code sent via email', ['user_id' => $user->id]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send 2FA email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send code via SMS
     */
    protected function sendViaSMS(User $user, string $code): bool
    {
        try {
            // If you have an SMS service configured
            if (class_exists(\App\Services\SmsService::class)) {
                $smsService = app(\App\Services\SmsService::class);
                $message = "Your 2FA verification code is: {$code}";
                return $smsService->send($user->phone, $message);
            }
            
            Log::info('2FA SMS would be sent', ['user_id' => $user->id, 'phone' => $user->phone]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send 2FA SMS: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get remaining attempts for user
     */
    public function getRemainingAttempts(User $user): int
    {
        $attempts = Cache::get($this->cachePrefix . 'attempts:' . $user->id, 0);
        return max(0, 5 - $attempts);
    }

    /**
     * Increment failed attempts
     */
    public function incrementFailedAttempts(User $user): void
    {
        $attempts = Cache::get($this->cachePrefix . 'attempts:' . $user->id, 0);
        Cache::put($this->cachePrefix . 'attempts:' . $user->id, $attempts + 1, now()->addMinutes(30));
    }

    /**
     * Clear failed attempts
     */
    public function clearFailedAttempts(User $user): void
    {
        Cache::forget($this->cachePrefix . 'attempts:' . $user->id);
    }

    /**
     * Get the TOTP secret for display (partial, for security)
     */
    public function getMaskedSecret(User $user): string
    {
        $secret = $user->two_factor_secret;
        if (!$secret) {
            return '';
        }
        
        $length = strlen($secret);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }
        
        $start = substr($secret, 0, 4);
        $end = substr($secret, -4);
        $masked = str_repeat('*', $length - 8);
        
        return $start . $masked . $end;
    }
}