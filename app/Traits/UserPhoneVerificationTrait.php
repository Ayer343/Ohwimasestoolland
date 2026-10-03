<?php

namespace App\Traits;

trait UserPhoneVerificationTrait
{
    /**
     * Get phone verification status
     */
    public function getPhoneVerificationStatusAttribute(): array
    {
        $verified = !is_null($this->phone_verified_at);
        
        return [
            'verified' => $verified,
            'status' => $verified ? self::PHONE_VERIFIED : self::PHONE_UNVERIFIED,
            'verified_at' => $this->phone_verified_at?->format('M j, Y g:i A'),
            'phone' => $this->phone,
            'local_phone' => $this->local_phone,
            'needs_verification' => $this->isFieldAgent() && !$verified,
            'can_send_code' => $this->canSendVerificationCode(),
            'wait_time' => $this->getVerificationWaitTime(),
        ];
    }

    /**
     * Check if phone is verified
     */
    public function getIsPhoneVerifiedAttribute(): bool
    {
        return !is_null($this->phone_verified_at);
    }

    /**
     * Check if user can send verification code
     */
    public function canSendVerificationCode(): bool
    {
        if (!$this->phone_verification_sent_at) {
            return true;
        }

        // Allow sending new code after 5 minutes
        return $this->phone_verification_sent_at->addMinutes(5)->isPast();
    }

    /**
     * Get verification wait time in seconds
     */
    public function getVerificationWaitTime(): int
    {
        if (!$this->phone_verification_sent_at) {
            return 0;
        }

        $nextAvailable = $this->phone_verification_sent_at->addMinutes(5);
        $now = now();

        if ($nextAvailable->isPast()) {
            return 0;
        }

        return $nextAvailable->diffInSeconds($now);
    }

    /**
     * Send phone verification code
     */
    public function sendPhoneVerificationCode(): bool
    {
        if (!$this->phone) {
            return false;
        }

        // Check if we can send another code
        if (!$this->canSendVerificationCode()) {
            return false;
        }

        try {
            // Generate 6-digit verification code using secure random
            $code = sprintf('%06d', random_int(1, 999999));
            
            $this->update([
                'phone_verification_code' => $code,
                'phone_verification_sent_at' => now(),
            ]);

            \Log::info("Phone verification code generated", [
                'user_id' => $this->id,
                'phone' => $this->phone,
                'local_phone' => $this->local_phone,
                'code_sent_at' => now()->toDateTimeString()
            ]);

            return true;

        } catch (\Exception $e) {
            \Log::error('Failed to send phone verification code: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify phone with code
     */
    public function verifyPhone($code): bool
    {
        if (!$this->phone_verification_code || 
            !$this->phone_verification_sent_at ||
            $this->phone_verification_code !== $code) {
            return false;
        }

        // Check if code is expired (15 minutes)
        if ($this->phone_verification_sent_at->addMinutes(15)->isPast()) {
            return false;
        }

        return $this->update([
            'phone_verified_at' => now(),
            'phone_verification_code' => null,
            'phone_verification_sent_at' => null,
        ]);
    }

    /**
     * Mark phone as verified (admin manual verification)
     */
    public function markPhoneVerified(): bool
    {
        return $this->update([
            'phone_verified_at' => now(),
            'phone_verification_code' => null,
            'phone_verification_sent_at' => null,
        ]);
    }
}