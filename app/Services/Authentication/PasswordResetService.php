<?php

namespace App\Services\Authentication;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PasswordResetService
{
    /**
     * Get password reset table name (compatible with different Laravel versions)
     */
    protected function getPasswordResetTable(): string
    {
        if (Schema::hasTable('password_reset_tokens')) {
            return 'password_reset_tokens';
        }
        
        return 'password_resets';
    }

    /**
     * Send password reset link
     */
    public function sendResetLink(string $login): array
    {
        // Find user by email or phone
        $user = null;
        
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $login)->first();
        } else {
            $phoneNormalizer = app(PhoneNormalizationService::class);
            $normalizedPhone = $phoneNormalizer->normalize($login);
            $user = User::where('phone', $normalizedPhone)->first();
        }

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // Check if user is archived
        if ($user->isArchived()) {
            return ['success' => false, 'message' => 'Archived accounts cannot reset passwords'];
        }

        // Check if user has a valid email
        if (!$user->email) {
            return ['success' => false, 'message' => 'No email address associated with this account'];
        }

        // Generate reset token
        $token = Str::random(64);
        
        // Get the appropriate table name
        $tableName = $this->getPasswordResetTable();
        
        // Store in password reset table
        DB::table($tableName)->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($token),
                'created_at' => Carbon::now()
            ]
        );

        // Send reset link via email
        try {
            $this->sendResetEmail($user, $token);
            return ['success' => true, 'message' => 'Reset link sent successfully'];
        } catch (\Exception $e) {
            \Log::error('Password reset email failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send reset email'];
        }
    }

    /**
     * Send password reset email
     */
    protected function sendResetEmail(User $user, string $token): void
    {
        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $user->email
        ]);
        
        Mail::send('emails.password-reset', [
            'user' => $user,
            'resetUrl' => $resetUrl,
            'token' => $token
        ], function ($message) use ($user) {
            $message->to($user->email)
                    ->subject('Reset Your Password - ' . config('app.name'));
        });
    }

    /**
     * Validate reset token
     */
    public function validateToken(?string $email, string $token): bool
    {
        if (!$email) {
            return false;
        }
        
        $tableName = $this->getPasswordResetTable();
        
        $resetRecord = DB::table($tableName)
            ->where('email', $email)
            ->first();

        if (!$resetRecord || !Hash::check($token, $resetRecord->token)) {
            return false;
        }

        // Check if token is expired (1 hour)
        if (Carbon::parse($resetRecord->created_at)->diffInMinutes(Carbon::now()) > 60) {
            DB::table($tableName)->where('email', $email)->delete();
            return false;
        }
        
        return true;
    }

    /**
     * Reset password
     */
    public function resetPassword(string $email, string $token, string $newPassword): array
    {
        // Validate token
        if (!$this->validateToken($email, $token)) {
            return ['success' => false, 'message' => 'Invalid or expired reset token'];
        }
        
        // Find user
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // Check if user is archived
        if ($user->isArchived()) {
            return ['success' => false, 'message' => 'Archived accounts cannot reset passwords'];
        }

        // Update password
        $user->update([
            'password' => Hash::make($newPassword),
            'temp_password' => false,
            'password_changed_at' => now(),
        ]);

        // Store in password history
        $this->storePasswordHistory($user, $user->password);
        
        // Delete reset token
        $tableName = $this->getPasswordResetTable();
        DB::table($tableName)->where('email', $email)->delete();
        
        // Clear any lockouts
        $user->failed_login_attempts = 0;
        $user->account_locked_until = null;
        $user->save();
        
        return ['success' => true, 'message' => 'Password reset successful'];
    }

    /**
     * Store password in history
     */
    protected function storePasswordHistory(User $user, string $passwordHash): void
    {
        $history = json_decode($user->password_history ?? '[]', true);
        
        // Add new password hash
        array_unshift($history, $passwordHash);
        
        // Keep only last 5 passwords
        $history = array_slice($history, 0, 5);
        
        $user->password_history = json_encode($history);
        $user->save();
    }

    /**
     * Check if password was used before
     */
    public function isPasswordReused(User $user, string $newPassword): bool
    {
        $history = json_decode($user->password_history ?? '[]', true);
        
        foreach ($history as $oldHash) {
            if (Hash::check($newPassword, $oldHash)) {
                return true;
            }
        }
        
        return false;
    }
}