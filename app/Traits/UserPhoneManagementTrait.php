<?php

namespace App\Traits;

trait UserPhoneManagementTrait
{
    /**
     * Standardize phone number to +233 format
     */
    public function standardizePhoneNumber($phone): string
    {
        if (empty($phone)) {
            return '';
        }
        
        // Remove all non-digit characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // If phone starts with 0 (like 0595652410), convert to +233 format
        if (preg_match('/^0(\d{9})$/', $phone, $matches)) {
            return self::PHONE_COUNTRY_CODE . $matches[1];
        }
        
        // If phone has 9 digits without country code (like 595652410), add +233
        if (preg_match('/^(\d{9})$/', $phone, $matches)) {
            return self::PHONE_COUNTRY_CODE . $matches[1];
        }
        
        // If phone has country code without + (like 233595652410), add +
        if (preg_match('/^233(\d{9})$/', $phone, $matches)) {
            return self::PHONE_COUNTRY_CODE . $matches[1];
        }
        
        // If phone already has +233 format, return as is
        if (preg_match('/^\+233(\d{9})$/', $phone, $matches)) {
            return $phone;
        }
        
        // Return original if no pattern matches
        return $phone;
    }

    /**
     * Set phone attribute with automatic standardization
     */
    public function setPhoneAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['phone'] = $this->standardizePhoneNumber($value);
        } else {
            $this->attributes['phone'] = $value;
        }
    }

    /**
     * Get phone number in local format (without country code)
     */
    public function getLocalPhoneAttribute(): string
    {
        if (empty($this->phone)) {
            return 'Not set';
        }
        
        if (preg_match('/^\+233(\d{9})$/', $this->phone, $matches)) {
            return '0' . $matches[1];
        }
        
        return $this->phone;
    }

    /**
     * Get all possible phone formats for login compatibility
     */
    public function getPhoneFormatsAttribute(): array
    {
        if (empty($this->phone)) {
            return [];
        }

        $formats = [];
        
        // Extract the 9-digit number if in +233 format
        if (preg_match('/^\+233(\d{9})$/', $this->phone, $matches)) {
            $nineDigits = $matches[1];
            
            $formats = [
                '+233' . $nineDigits,
                '233' . $nineDigits,
                '0' . $nineDigits,
                $nineDigits,
            ];
        } else {
            // If not in standard format, try to normalize
            $normalized = $this->standardizePhoneNumber($this->phone);
            if ($normalized !== $this->phone) {
                return $this->getPhoneFormatsAttribute();
            }
            $formats = [$this->phone];
        }

        return array_unique($formats);
    }

    /**
     * Validate phone number format
     */
    public static function isValidPhoneNumber($phone): bool
    {
        if (empty($phone)) {
            return false;
        }

        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Check for +233 format
        if (preg_match('/^\+233(\d{9})$/', $phone)) {
            return true;
        }

        // Check for 233 format (without +)
        if (preg_match('/^233(\d{9})$/', $phone)) {
            return true;
        }

        // Check for local format (0 followed by 9 digits)
        if (preg_match('/^0(\d{9})$/', $phone)) {
            return true;
        }

        // Check for digits only (9 digits)
        if (preg_match('/^(\d{9})$/', $phone)) {
            return true;
        }

        return false;
    }

    /**
     * Extract 9-digit number from any phone format
     */
    public function extractPhoneDigits(): ?string
    {
        if (empty($this->phone)) {
            return null;
        }

        $phone = preg_replace('/[^\d+]/', '', $this->phone);

        if (preg_match('/^\+233(\d{9})$/', $phone, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^233(\d{9})$/', $phone, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^0(\d{9})$/', $phone, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^(\d{9})$/', $phone, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Find user by any phone format
     */
    public static function findByAnyPhoneFormat($phone)
    {
        if (empty($phone)) {
            return null;
        }

        // Standardize the input phone number
        $user = new static();
        $standardizedPhone = $user->standardizePhoneNumber($phone);

        // First try exact match with standardized phone
        $foundUser = static::where('phone', $standardizedPhone)->first();
        if ($foundUser) {
            return $foundUser;
        }

        // If not found, try all possible formats
        $phoneDigits = $user->extractPhoneDigitsFromInput($phone);
        if ($phoneDigits) {
            $possiblePhones = [
                '+233' . $phoneDigits,
                '233' . $phoneDigits,
                '0' . $phoneDigits,
                $phoneDigits
            ];

            return static::whereIn('phone', $possiblePhones)->first();
        }

        return null;
    }

    /**
     * Extract 9-digit number from any input format
     */
    private function extractPhoneDigitsFromInput($phone): ?string
    {
        $phone = preg_replace('/[^\d+]/', '', $phone);

        if (preg_match('/^\+233(\d{9})$/', $phone, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^233(\d{9})$/', $phone, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^0(\d{9})$/', $phone, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^(\d{9})$/', $phone, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Find user by phone number (alias for findByAnyPhoneFormat)
     */
    public static function findByPhone($phone)
    {
        return static::findByAnyPhoneFormat($phone);
    }

    /**
     * Get phone validation rules
     */
    public static function getPhoneValidationRules(): array
    {
        return [
            'phone' => 'required|string|max:20',
        ];
    }

    /**
     * Debug phone information for troubleshooting
     */
    public function getPhoneDebugInfo(): array
    {
        return [
            'stored_phone' => $this->phone,
            'local_phone' => $this->local_phone,
            'all_formats' => $this->phone_formats,
            'extracted_digits' => $this->extractPhoneDigits(),
            'is_valid_format' => self::isValidPhoneNumber($this->phone),
            'phone_verified' => $this->is_phone_verified,
            'verification_status' => $this->phone_verification_status,
        ];
    }
}