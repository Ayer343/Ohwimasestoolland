<?php

namespace App\Services\Authentication;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthService
{
    protected Google2FA $google2fa;

    protected string $cachePrefix = '2fa_code:';
    protected int $codeValidity = 10;
    protected int $maxAttempts = 5;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /* ============================================================
     | STATE CHECKS
     * ============================================================ */

    public function isEnabled(User $user): bool
    {
        return (bool) ($user->two_factor_enabled ?? false);
    }

    /* ============================================================
     | ENABLE / DISABLE
     * ============================================================ */

    public function enable(User $user): void
    {
        if (!$user->two_factor_secret) {
            $user->two_factor_secret = $this->generateSecret();
        }

        $user->two_factor_enabled    = true;
        $user->two_factor_enabled_at = now();
        $user->save();
    }

    public function enableWithSecret(User $user, string $secret): void
    {
        $user->two_factor_secret     = $secret;
        $user->two_factor_enabled    = true;
        $user->two_factor_enabled_at = now();
        $user->save();
    }

    public function disable(User $user): void
    {
        $user->two_factor_enabled      = false;
        $user->two_factor_secret       = null;
        $user->two_factor_backup_codes = null;
        $user->save();

        Cache::forget($this->cachePrefix . $user->id);
        Cache::forget($this->cachePrefix . 'attempts:' . $user->id);
    }

    /* ============================================================
     | SECRET / QR CODE
     * ============================================================ */

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getQRCodeUrl(User $user): string
    {
        return $this->getQRCodeUrlForSecret($user, (string) $user->two_factor_secret);
    }

    public function getQRCodeUrlForSecret(User $user, string $secret): string
    {
        $company = config('app.name', 'Hilltop Estate Management');

        return $this->google2fa->getQRCodeUrl($company, (string) $user->email, $secret);
    }

    public function getQRCodeSvg(User $user): string
    {
        return $this->getQRCodeSvgForSecret($user, (string) $user->two_factor_secret);
    }

    public function getQRCodeSvgForSecret(User $user, string $secret): string
    {
        $qrCodeUrl = $this->getQRCodeUrlForSecret($user, $secret);

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($qrCodeUrl);
    }

    /* ============================================================
     | VERIFICATION
     * ============================================================ */

    public function verify(User $user, string $code): bool
    {
        $code = trim((string) $code);

        if ($code === '') {
            return false;
        }

        // 1. Backup code
        if ($this->verifyBackupCode($user, $code)) {
            return true;
        }

        // 2. TOTP
        if (!empty($user->two_factor_secret)) {
            if ($this->google2fa->verifyKey($user->two_factor_secret, $code, 1)) {
                return true;
            }
        }

        // 3. Cached email/SMS code
        return $this->verifyFallbackCode($user, $code);
    }

    public function verifyCode(string $secret, string $code): bool
    {
        $secret = trim($secret);
        $code   = trim((string) $code);

        if ($secret === '' || $code === '') {
            return false;
        }

        return $this->google2fa->verifyKey($secret, $code, 1);
    }

    /* ============================================================
     | BACKUP CODES
     | ------------------------------------------------------------
     | The storage format adapts to the User model's cast. If
     | `two_factor_backup_codes` is cast to `array`, we store the
     | raw PHP array; otherwise we JSON-encode manually.
     |
     | Without this adaptation, a model with an `array` cast would
     | double-encode the value: the service's manual json_encode()
     | would produce a string, then the cast would re-encode it,
     | and decodeBackupCodes() would receive an unparseable shape.
     * ============================================================ */

    public function generateBackupCodes(User $user): array
    {
        $plainCodes = [];
        $hashCodes  = [];

        for ($i = 0; $i < 8; $i++) {
            $plain = $this->generateBackupCode();
            $plainCodes[] = $plain;
            $hashCodes[]  = hash('sha256', $plain);
        }

        $user->two_factor_backup_codes = $this->encodeBackupCodes($user, $hashCodes);
        $user->save();

        Log::info('Backup codes generated', [
            'user_id' => $user->id,
            'count'   => count($plainCodes),
        ]);

        return $plainCodes;
    }

    public function regenerateBackupCodes(User $user): array
    {
        return $this->generateBackupCodes($user);
    }

    protected function verifyBackupCode(User $user, string $code): bool
    {
        $stored = $this->decodeBackupCodes($user);

        if (empty($stored)) {
            return false;
        }

        $incomingHash = hash('sha256', $code);

        foreach ($stored as $index => $storedHash) {
            if (!is_string($storedHash)) {
                continue;
            }

            // Match against the hash OR the raw code (legacy plaintext
            // storage from earlier versions).
            $matches = hash_equals($storedHash, $incomingHash)
                    || hash_equals($storedHash, $code);

            if ($matches) {
                unset($stored[$index]);

                $user->two_factor_backup_codes = $this->encodeBackupCodes(
                    $user,
                    array_values($stored)
                );
                $user->save();

                Log::info('Backup code used for 2FA', [
                    'user_id'         => $user->id,
                    'remaining_codes' => count($stored),
                ]);

                return true;
            }
        }

        return false;
    }

    protected function generateBackupCode(): string
    {
        return strtoupper(
            sprintf('%04d-%04d', random_int(0, 9999), random_int(0, 9999))
        );
    }

    /**
     * Returns the array of stored backup code entries, regardless of
     * how the User model stores them. Handles four possible shapes:
     *
     *   1. Native PHP array (model has an `array` cast)
     *   2. JSON string of an array (no cast, standard storage)
     *   3. JSON string of a JSON string (double-encoded legacy)
     *   4. Null / empty string / anything else → returns []
     *
     * Always returns a list (0-indexed) so callers can `unset()` and
     * `array_values()` without surprises.
     */
    protected function decodeBackupCodes(User $user): array
    {
        $value = $user->two_factor_backup_codes;

        // Case 1: already an array (thanks to the model's `array` cast).
        if (is_array($value)) {
            return array_values($value);
        }

        // Case 2 & 3: JSON string, possibly double-encoded.
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            // If the first decode yielded a string, it was double-encoded.
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            if (is_array($decoded)) {
                return array_values($decoded);
            }

            Log::warning('decodeBackupCodes: string could not be decoded', [
                'user_id'    => $user->id,
                'raw_len'    => strlen($value),
                'json_error' => json_last_error_msg(),
            ]);
            return [];
        }

        return [];
    }

    /**
     * Encodes the given array of backup codes for storage on the model.
     * Respects the model's cast if one exists.
     */
    protected function encodeBackupCodes(User $user, array $codes): array|string
    {
        return $this->modelCastsBackupCodes($user)
            ? $codes
            : json_encode($codes);
    }

    /**
     * Checks whether the User model declares a cast for
     * `two_factor_backup_codes`.
     */
    protected function modelCastsBackupCodes(User $user): bool
    {
        return array_key_exists('two_factor_backup_codes', $user->getCasts());
    }

    /* ============================================================
     | EMAIL / SMS FALLBACK
     * ============================================================ */

    /**
     * Generate a numeric code, dispatch it via the user's preferred
     * channel, and return whether delivery actually succeeded.
     *
     * - Uses `sendWithResult()` on SmsService so SMS delivery failures
     *   are visible to the caller, not silently swallowed.
     * - Rolls back the cached code when delivery fails, so a "live"
     *   code never sits in the cache waiting to be read by someone
     *   other than the intended recipient.
     * - Logs the outcome with enough detail to diagnose gateway issues
     *   without leaking the code itself.
     */
    public function sendCode(User $user): bool
    {
        $code = $this->generateNumericCode();

        // Store the code first so the delivery method can reference it
        // (SMS body includes it; email template includes it).
        Cache::put(
            $this->cachePrefix . $user->id,
            $code,
            now()->addMinutes($this->codeValidity)
        );

        $method = $user->two_factor_method ?? 'email';

        try {
            $sent = match ($method) {
                'sms'   => $this->sendViaSMS($user, $code),
                default => $this->sendViaEmail($user, $code),
            };
        } catch (\Throwable $e) {
            Log::error('2FA code delivery threw', [
                'user_id' => $user->id,
                'method'  => $method,
                'error'   => $e->getMessage(),
            ]);
            $sent = false;
        }

        if (!$sent) {
            // The user never received the code — purge it so it can't
            // be used by anyone else.
            Cache::forget($this->cachePrefix . $user->id);

            Log::warning('2FA code delivery failed', [
                'user_id' => $user->id,
                'method'  => $method,
                'email'   => $user->email,
                'phone'   => $user->phone ? $this->maskPhone($user->phone) : null,
            ]);
        }

        return $sent;
    }

    protected function verifyFallbackCode(User $user, string $code): bool
    {
        $cached = Cache::get($this->cachePrefix . $user->id);

        if ($cached && hash_equals((string) $cached, (string) $code)) {
            Cache::forget($this->cachePrefix . $user->id);
            return true;
        }

        return false;
    }

    protected function generateNumericCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    protected function sendViaEmail(User $user, string $code): bool
    {
        try {
            $viewExists = view()->exists('emails.two-factor-code');

            if ($viewExists) {
                Mail::send('emails.two-factor-code', [
                    'user'     => $user,
                    'code'     => $code,
                    'validity' => $this->codeValidity,
                ], function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Your Two-Factor Authentication Code - ' . config('app.name'));
                });
            } else {
                Mail::raw(
                    "Your 2FA verification code is: {$code}\n\n"
                    . "This code will expire in {$this->codeValidity} minutes.",
                    function ($message) use ($user) {
                        $message->to($user->email)
                            ->subject('Your Two-Factor Authentication Code - ' . config('app.name'));
                    }
                );
            }

            Log::info('2FA code sent via email', ['user_id' => $user->id]);
            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send 2FA email: ' . $e->getMessage(), [
                'user_id' => $user->id,
            ]);
            return false;
        }
    }

    /**
     * Send the 2FA code by SMS through the SmsService default provider.
     *
     * - Uses `sendWithResult()` instead of `send()` so we know WHY a
     *   send failed (no provider, disabled provider, API error).
     * - Validates the user actually has a phone number before
     *   attempting the send.
     * - Returns false on every real failure path — never "pretends"
     *   success when there's no configured provider.
     */
    protected function sendViaSMS(User $user, string $code): bool
    {
        if (!class_exists(\App\Services\SmsService::class)) {
            Log::warning('2FA SMS skipped — SmsService class not found', [
                'user_id' => $user->id,
            ]);
            return false;
        }

        if (empty($user->phone)) {
            Log::warning('2FA SMS skipped — user has no phone number', [
                'user_id' => $user->id,
            ]);
            return false;
        }

        $smsService = app(\App\Services\SmsService::class);

        $appName = config('app.name', 'Hilltop Estate Management');

        $message = "Your {$appName} verification code is {$code}. "
                 . "It expires in {$this->codeValidity} minutes. "
                 . "Never share this code with anyone.";

        $result = $smsService->sendWithResult($user->phone, $message);

        if (($result['success'] ?? false) === true) {
            Log::info('2FA code sent via SMS', [
                'user_id'    => $user->id,
                'provider'   => $result['provider'] ?? 'unknown',
                'message_id' => $result['message_id'] ?? null,
            ]);

            return true;
        }

        Log::error('Failed to send 2FA SMS', [
            'user_id'    => $user->id,
            'provider'   => $result['provider']   ?? 'unknown',
            'error_code' => $result['error_code'] ?? 'UNKNOWN',
            'message'    => $result['message']    ?? 'No message',
            'status'     => $result['status_code'] ?? null,
        ]);

        return false;
    }

    /**
     * Mask a phone number for logging so we don't leak full PII.
     * Example: "+233501234567" → "+233****4567"
     */
    protected function maskPhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $len = strlen($phone);

        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($phone, 0, 4) . '****' . substr($phone, -4);
    }

    /* ============================================================
     | ATTEMPT COUNTERS
     * ============================================================ */

    public function getRemainingAttempts(User $user): int
    {
        $attempts = (int) Cache::get(
            $this->cachePrefix . 'attempts:' . $user->id,
            0
        );

        return max(0, $this->maxAttempts - $attempts);
    }

    public function incrementFailedAttempts(User $user): void
    {
        $key      = $this->cachePrefix . 'attempts:' . $user->id;
        $attempts = (int) Cache::get($key, 0);

        Cache::put($key, $attempts + 1, now()->addMinutes(30));
    }

    public function clearFailedAttempts(User $user): void
    {
        Cache::forget($this->cachePrefix . 'attempts:' . $user->id);
    }

    /* ============================================================
     | UTIL
     * ============================================================ */

    public function getMaskedSecret(User $user): string
    {
        $secret = (string) $user->two_factor_secret;

        if ($secret === '') {
            return '';
        }

        $length = strlen($secret);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($secret, 0, 4)
            . str_repeat('*', $length - 8)
            . substr($secret, -4);
    }

    public function codeValidityMinutes(): int
    {
        return $this->codeValidity;
    }
}