<?php

namespace App\Services\Authentication;

class PhoneNormalizationService
{
    /**
     * Normalize phone number to standard format (+233XXXXXXXXX)
     */
    public function normalize(string $phone): string
    {
        // Remove all non-digit characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // If phone starts with 0 (like 0595652410), convert to +233 format
        if (preg_match('/^0(\d{9})$/', $phone, $matches)) {
            return '+233' . $matches[1];
        }
        
        // If phone has 9 digits without country code
        if (preg_match('/^(\d{9})$/', $phone, $matches)) {
            return '+233' . $matches[1];
        }
        
        // If phone already has country code, standardize it
        if (preg_match('/^\+?233(\d{9})$/', $phone, $matches)) {
            return '+233' . $matches[1];
        }
        
        // Return original if no pattern matches
        return $phone;
    }

    /**
     * Generate all possible phone number formats for a given input
     */
    public function getAllFormats(string $phoneInput): array
    {
        $formats = [];
        
        // Remove all non-digit characters
        $digitsOnly = preg_replace('/[^\d]/', '', $phoneInput);
        
        // Original input
        $formats[] = $phoneInput;
        $formats[] = $digitsOnly;
        
        // If input starts with 0
        if (preg_match('/^0(\d{9})$/', $digitsOnly, $matches)) {
            $nineDigits = $matches[1];
            $formats[] = '+233' . $nineDigits;
            $formats[] = '233' . $nineDigits;
            $formats[] = '0' . $nineDigits;
            $formats[] = $nineDigits;
        }
        
        // If input is 9 digits
        if (preg_match('/^(\d{9})$/', $digitsOnly, $matches)) {
            $nineDigits = $matches[1];
            $formats[] = '+233' . $nineDigits;
            $formats[] = '233' . $nineDigits;
            $formats[] = '0' . $nineDigits;
            $formats[] = $nineDigits;
        }
        
        // If input has country code
        if (preg_match('/^\+?233(\d{9})$/', $digitsOnly, $matches)) {
            $nineDigits = $matches[1];
            $formats[] = '+233' . $nineDigits;
            $formats[] = '233' . $nineDigits;
            $formats[] = '0' . $nineDigits;
            $formats[] = $nineDigits;
        }
        
        // Remove duplicates and return
        return array_values(array_unique($formats));
    }

    /**
     * Validate phone number format
     */
    public function isValid(string $phone): bool
    {
        $normalized = $this->normalize($phone);
        
        // Check if it matches Ghana phone number pattern
        return preg_match('/^\+233[0-9]{9}$/', $normalized) === 1;
    }

    /**
     * Extract the 9-digit local number from normalized phone
     */
    public function extractLocalNumber(string $phone): ?string
    {
        $normalized = $this->normalize($phone);
        
        if (preg_match('/^\+233(\d{9})$/', $normalized, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}