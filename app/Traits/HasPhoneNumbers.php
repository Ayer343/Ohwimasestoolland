<?php

namespace App\Traits;

use App\Models\LandlordPhone;
use Illuminate\Support\Facades\Log;

trait HasPhoneNumbers
{
    /* ============================================================
     | CONSTANTS (mirrored from User for use inside the trait)
     * ============================================================ */

    public static function phoneCountryCode(): string
    {
        return static::PHONE_COUNTRY_CODE;
    }

    public static function phoneLocalPrefix(): string
    {
        return static::PHONE_LOCAL_PREFIX;
    }

    /* ============================================================
     | STANDARDIZATION
     * ============================================================ */

    /**
     * Standardize phone number to +233XXXXXXXXX format.
     * Idempotent — safe to call repeatedly.
     */
    public function standardizePhoneNumber($phone): string
    {
        if (empty($phone)) {
            return '';
        }

        $phone = preg_replace('/[^\d+]/', '', (string) $phone);

        if (preg_match('/^\+233(\d{9})$/', $phone)) {
            return $phone;
        }

        if (preg_match('/^233(\d{9})$/', $phone, $m)) {
            return static::PHONE_COUNTRY_CODE . $m[1];
        }

        if (preg_match('/^0(\d{9})$/', $phone, $m)) {
            return static::PHONE_COUNTRY_CODE . $m[1];
        }

        if (preg_match('/^(\d{9})$/', $phone, $m)) {
            return static::PHONE_COUNTRY_CODE . $m[1];
        }

        return $phone;
    }

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = !empty($value)
            ? $this->standardizePhoneNumber($value)
            : $value;
    }

    public static function isValidPhoneNumber($phone): bool
    {
        if (empty($phone)) {
            return false;
        }

        $phone = preg_replace('/[^\d+]/', '', (string) $phone);

        return (bool) (
            preg_match('/^\+233(\d{9})$/', $phone) ||
            preg_match('/^233(\d{9})$/', $phone) ||
            preg_match('/^0(\d{9})$/', $phone) ||
            preg_match('/^(\d{9})$/', $phone)
        );
    }

    /* ============================================================
     | LOOKUP
     * ============================================================ */

    public static function findByAnyPhoneFormat($phone)
    {
        if (empty($phone)) {
            return null;
        }

        $instance = new static();
        $standardized = $instance->standardizePhoneNumber($phone);

        if ($standardized && $found = static::where('phone', $standardized)->first()) {
            return $found;
        }

        $digits = $instance->extractPhoneDigitsFromInput($phone);
        if ($digits) {
            return static::whereIn('phone', [
                '+233' . $digits,
                '233' . $digits,
                '0' . $digits,
                $digits,
            ])->first();
        }

        return null;
    }

    public static function findByPhone($phone)
    {
        return static::findByAnyPhoneFormat($phone);
    }

    private function extractPhoneDigitsFromInput($phone): ?string
    {
        $phone = preg_replace('/[^\d+]/', '', (string) $phone);

        foreach (['/^\+233(\d{9})$/', '/^233(\d{9})$/', '/^0(\d{9})$/', '/^(\d{9})$/'] as $pattern) {
            if (preg_match($pattern, $phone, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /* ============================================================
     | FORMATS
     * ============================================================ */

    public function getLocalPhoneAttribute(): string
    {
        if (empty($this->phone)) {
            return 'Not set';
        }

        if (preg_match('/^\+233(\d{9})$/', $this->phone, $m)) {
            return static::PHONE_LOCAL_PREFIX . $m[1];
        }

        return $this->phone;
    }

    public function getPhoneFormatsAttribute(): array
    {
        if (empty($this->phone)) {
            return [];
        }

        if (preg_match('/^\+233(\d{9})$/', $this->phone, $m)) {
            $nine = $m[1];

            return array_values(array_unique([
                '+233' . $nine,
                '233' . $nine,
                '0' . $nine,
                $nine,
            ]));
        }

        // Non-standard phone — try normalizing once, then return as-is
        $normalized = $this->standardizePhoneNumber($this->phone);

        return $normalized === $this->phone
            ? [$this->phone]
            : array_values(array_unique([
                $normalized,
                $this->phone,
            ]));
    }

    public function getAllPhonesAttribute(): array
    {
        $phones = [];

        if (!empty($this->phone)) {
            $phones[] = [
                'phone_number' => $this->phone,
                'local_format' => $this->local_phone,
                'is_primary'   => true,
                'type'         => 'primary',
            ];
        }

        if (method_exists($this, 'phones')) {
            foreach ($this->phones()->get() as $phone) {
                $phones[] = [
                    'phone_number' => $phone->phone_number,
                    'local_format' => $this->convertToLocalFormat($phone->phone_number),
                    'is_primary'   => false,
                    'type'         => 'additional',
                    'id'           => $phone->id,
                ];
            }
        }

        return $phones;
    }

    public function getAllPhoneNumbers(): array
    {
        $phones = [];

        if ($this->phone) {
            $phones[] = [
                'phone_number' => $this->phone,
                'local_format' => $this->local_phone,
                'is_primary'   => true,
                'is_verified'  => !is_null($this->phone_verified_at),
            ];
        }

        foreach ($this->phones as $phone) {
            $phones[] = [
                'phone_number' => $phone->phone_number,
                'local_format' => $this->convertToLocalFormat($phone->phone_number),
                'is_primary'   => $phone->is_primary ?? false,
                'is_verified'  => $phone->is_verified ?? false,
                'id'           => $phone->id,
            ];
        }

        return $phones;
    }

    public function getIsPhoneVerifiedAttribute(): bool
    {
        return !is_null($this->phone_verified_at);
    }

    public function getPhoneVerificationStatusAttribute(): array
    {
        $verified = !is_null($this->phone_verified_at);

        return [
            'verified'           => $verified,
            'status'             => $verified ? static::PHONE_VERIFIED : static::PHONE_UNVERIFIED,
            'verified_at'        => $this->phone_verified_at?->format('M j, Y g:i A'),
            'phone'              => $this->phone,
            'local_phone'        => $this->local_phone,
            'needs_verification' => $this->isFieldAgent() && !$verified,
            'can_send_code'      => $this->canSendVerificationCode(),
            'wait_time'          => $this->getVerificationWaitTime(),
        ];
    }

    /* ============================================================
     | MUTATION (ADD / REMOVE / UPDATE)
     * ============================================================ */

    /**
     * Sync additional phones in a single transaction.
     */
    public function updatePhones(array $phones): self
    {
        $clean = collect($phones)
            ->map(fn ($p) => trim((string) $p))
            ->filter()
            ->map(fn ($p) => $this->standardizePhoneNumber($p))
            ->unique()
            ->values()
            ->all();

        $primaryPhone     = $this->phone;
        $additionalPhones = array_values(array_filter($clean, fn ($p) => $p !== $primaryPhone));

        \DB::transaction(function () use ($additionalPhones, $primaryPhone) {
            $existing = $this->phones()->pluck('phone_number')->all();

            $toAdd    = array_diff($additionalPhones, $existing);
            $toRemove = array_diff($existing, $additionalPhones);

            foreach ($toAdd as $phone) {
                $this->phones()->create([
                    'phone_number' => $phone,
                    'is_verified'  => false,
                    'is_primary'   => false,
                    'created_by'   => auth()->id(),
                ]);

                Log::info('Additional phone added for user', [
                    'user_id' => $this->id,
                    'phone'   => $phone,
                ]);
            }

            if (!empty($toRemove)) {
                $this->phones()->whereIn('phone_number', $toRemove)->delete();

                Log::info('Additional phones removed for user', [
                    'user_id'        => $this->id,
                    'removed_phones' => array_values($toRemove),
                ]);
            }

            $metadata = $this->metadata ?? [];
            $metadata['phone_history']   = $metadata['phone_history'] ?? [];
            $metadata['phone_history'][] = [
                'action'            => 'update_phones',
                'primary_phone'     => $primaryPhone,
                'additional_phones' => $additionalPhones,
                'timestamp'         => now()->toISOString(),
                'updated_by'        => auth()->id(),
            ];

            if (count($metadata['phone_history']) > 10) {
                $metadata['phone_history'] = array_slice($metadata['phone_history'], -10);
            }

            $this->metadata = $metadata;
            $this->saveQuietly();
        });

        Log::info('User phones updated successfully', [
            'user_id'                 => $this->id,
            'name'                    => $this->name,
            'primary_phone'           => $primaryPhone,
            'additional_phones_count' => count($additionalPhones),
        ]);

        return $this;
    }

    public function addPhone(string $phone, bool $isVerified = false): ?LandlordPhone
    {
        $phone = $this->standardizePhoneNumber($phone);

        if ($this->phone === $phone) {
            Log::warning('Cannot add primary phone as additional', ['user_id' => $this->id, 'phone' => $phone]);
            return null;
        }

        if ($this->phones()->where('phone_number', $phone)->exists()) {
            Log::warning('Phone number already exists for user', ['user_id' => $this->id, 'phone' => $phone]);
            return null;
        }

        return $this->phones()->create([
            'phone_number' => $phone,
            'is_verified'  => $isVerified,
            'is_primary'   => false,
            'created_by'   => auth()->id(),
        ]);
    }

    public function removePhone(string $phone): bool
    {
        $phone = $this->standardizePhoneNumber($phone);

        if ($this->phone === $phone) {
            Log::warning('Cannot remove primary phone', ['user_id' => $this->id, 'phone' => $phone]);
            return false;
        }

        $deleted = (bool) $this->phones()->where('phone_number', $phone)->delete();

        if ($deleted) {
            Log::info('Additional phone removed', ['user_id' => $this->id, 'phone' => $phone]);
        }

        return $deleted;
    }

    public function verifyPhoneNumber(string $phone): bool
    {
        $phone = $this->standardizePhoneNumber($phone);

        if ($this->phone === $phone) {
            return $this->markPhoneVerified();
        }

        $record = $this->phones()->where('phone_number', $phone)->first();

        return $record
            ? (bool) $record->update(['is_verified' => true, 'verified_at' => now()])
            : false;
    }

    /* ============================================================
     | VERIFICATION CODE FLOW
     * ============================================================ */

    public function canSendVerificationCode(): bool
    {
        if (!$this->phone_verification_sent_at) {
            return true;
        }

        return $this->phone_verification_sent_at->addMinutes(5)->isPast();
    }

    public function getVerificationWaitTime(): int
    {
        if (!$this->phone_verification_sent_at) {
            return 0;
        }

        $next = $this->phone_verification_sent_at->addMinutes(5);

        return $next->isPast() ? 0 : $next->diffInSeconds(now());
    }

    public function sendPhoneVerificationCode(): bool
    {
        if (!$this->phone || !$this->canSendVerificationCode()) {
            return false;
        }

        try {
            $code = sprintf('%06d', random_int(1, 999999));

            $this->update([
                'phone_verification_code'    => $code,
                'phone_verification_sent_at' => now(),
            ]);

            Log::info('Phone verification code generated', [
                'user_id'      => $this->id,
                'phone'        => $this->phone,
                'local_phone'  => $this->local_phone,
                'code_sent_at' => now()->toDateTimeString(),
            ]);

            return true;

        } catch (\Throwable $e) {
            Log::error('Failed to send phone verification code: ' . $e->getMessage());
            return false;
        }
    }

    public function verifyPhone($code): bool
    {
        if (
            !$this->phone_verification_code ||
            !$this->phone_verification_sent_at ||
            !hash_equals((string) $this->phone_verification_code, (string) $code)
        ) {
            return false;
        }

        if ($this->phone_verification_sent_at->addMinutes(15)->isPast()) {
            return false;
        }

        return $this->update([
            'phone_verified_at'          => now(),
            'phone_verification_code'    => null,
            'phone_verification_sent_at' => null,
        ]);
    }

    public function markPhoneVerified(): bool
    {
        return $this->update([
            'phone_verified_at'          => now(),
            'phone_verification_code'    => null,
            'phone_verification_sent_at' => null,
        ]);
    }

    /* ============================================================
     | INTERNAL
     * ============================================================ */

    private function convertToLocalFormat($phone): string
    {
        if (preg_match('/^\+233(\d{9})$/', (string) $phone, $m)) {
            return static::PHONE_LOCAL_PREFIX . $m[1];
        }

        return (string) $phone;
    }
}