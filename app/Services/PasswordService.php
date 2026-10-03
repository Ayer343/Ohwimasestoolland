<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordService
{
    /**
     * Generate a strong random password
     */
    public function generate(int $length = 12): string
    {
        $password = '';
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }

    /**
     * Validate password strength
     */
    public function validate(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        // Check for common passwords
        $commonPasswords = [
            'password', '123456', '12345678', '123456789', 'qwerty',
            'abc123', 'password1', 'admin', 'letmein', 'welcome'
        ];

        if (in_array(strtolower($password), $commonPasswords)) {
            $errors[] = 'Password is too common. Please choose a stronger password.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'score' => $this->calculateStrengthScore($password),
        ];
    }

    /**
     * Calculate password strength score (0-100)
     */
    private function calculateStrengthScore(string $password): int
    {
        $score = 0;
        
        // Length score
        $length = strlen($password);
        if ($length >= 8) $score += 10;
        if ($length >= 12) $score += 10;
        if ($length >= 16) $score += 10;
        
        // Character variety
        if (preg_match('/[A-Z]/', $password)) $score += 15;
        if (preg_match('/[a-z]/', $password)) $score += 15;
        if (preg_match('/[0-9]/', $password)) $score += 15;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 20;
        
        // Deductions for patterns
        if (preg_match('/(.)\1{2,}/', $password)) $score -= 10; // Repeated characters
        if (preg_match('/^\d+$/', $password)) $score -= 15; // Only numbers
        if (preg_match('/^[a-zA-Z]+$/', $password)) $score -= 15; // Only letters
        
        return min(max($score, 0), 100);
    }

    /**
     * Hash password
     */
    public function hash(string $password): string
    {
        return Hash::make($password);
    }

    /**
     * Verify password against hash
     */
    public function verify(string $password, string $hash): bool
    {
        return Hash::check($password, $hash);
    }

    /**
     * Check if password needs rehash
     */
    public function needsRehash(string $hash): bool
    {
        return Hash::needsRehash($hash);
    }

    /**
     * Generate temporary password for invitations
     */
    public function generateTemporaryPassword(): string
    {
        return Str::random(12);
    }

    /**
     * Generate password reset token
     */
    public function generateResetToken(): string
    {
        return Str::random(64);
    }

    /**
     * Validate password reset token
     */
    public function validateResetToken(string $token): bool
    {
        return strlen($token) === 64 && preg_match('/^[a-zA-Z0-9]+$/', $token);
    }

    /**
     * Get password strength level
     */
    public function getStrengthLevel(string $password): string
    {
        $score = $this->calculateStrengthScore($password);
        
        if ($score >= 80) return 'strong';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'fair';
        return 'weak';
    }

    /**
     * Suggest improvements for weak password
     */
    public function suggestImprovements(string $password): array
    {
        $suggestions = [];
        
        if (strlen($password) < 8) {
            $suggestions[] = 'Make it at least 8 characters long';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $suggestions[] = 'Add at least one uppercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $suggestions[] = 'Add at least one number';
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $suggestions[] = 'Add at least one special character (!@#$%^&* etc.)';
        }
        
        if (strlen($password) < 12) {
            $suggestions[] = 'Consider making it 12+ characters for better security';
        }
        
        return $suggestions;
    }
}