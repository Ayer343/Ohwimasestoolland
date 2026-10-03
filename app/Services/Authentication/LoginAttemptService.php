<?php

namespace App\Services\Authentication;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LoginAttemptService
{
    /**
     * Log a login attempt
     */
    public function logAttempt(Request $request, ?User $user): void
    {
        $data = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'login_input' => $request->input('login'),
            'timestamp' => now()->toDateTimeString(),
            'success' => $user ? true : false,
        ];
        
        if ($user) {
            $data['user_id'] = $user->id;
            $data['user_email'] = $user->email;
            $data['user_status'] = $user->status;
            $data['is_archived'] = $user->isArchived();
        }
        
        Log::info('Login attempt', $data);
        
        // Store in database if login_activities table exists
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('login_activities')) {
                \Illuminate\Support\Facades\DB::table('login_activities')->insert([
                    'user_id' => $user?->id,
                    'action' => 'login_attempt',
                    'type' => 'web',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => json_encode($data),
                    'success' => $user ? true : false,
                    'created_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            // Silent fail - don't break login for logging issues
        }
    }

    /**
     * Get recent failed attempts for a user
     */
    public function getRecentFailedAttempts(User $user, int $minutes = 15): int
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('login_activities')) {
                return \Illuminate\Support\Facades\DB::table('login_activities')
                    ->where('user_id', $user->id)
                    ->where('action', 'login_attempt')
                    ->where('success', false)
                    ->where('created_at', '>', now()->subMinutes($minutes))
                    ->count();
            }
        } catch (\Exception $e) {
            // Fallback to user's failed_login_attempts column
            return $user->failed_login_attempts ?? 0;
        }
        
        return 0;
    }

    /**
     * Clear failed attempts for a user
     */
    public function clearFailedAttempts(User $user): void
    {
        $user->failed_login_attempts = 0;
        $user->last_failed_login_at = null;
        $user->save();
    }

    /**
     * Record a failed attempt
     */
    public function recordFailedAttempt(User $user, Request $request): void
    {
        $user->increment('failed_login_attempts');
        $user->last_failed_login_at = now();
        $user->save();
        
        // Lock account if too many failed attempts
        $maxAttempts = config('auth.lockout.max_attempts', 5);
        if ($user->failed_login_attempts >= $maxAttempts) {
            $lockoutMinutes = config('auth.lockout.decay_minutes', 15);
            $user->account_locked_until = now()->addMinutes($lockoutMinutes);
            $user->save();
            
            Log::warning('Account locked due to too many failed attempts', [
                'user_id' => $user->id,
                'email' => $user->email,
                'attempts' => $user->failed_login_attempts,
                'locked_until' => $user->account_locked_until
            ]);
        }
    }

    /**
     * Check if account is locked
     */
    public function isAccountLocked(User $user): bool
    {
        if ($user->account_locked_until && $user->account_locked_until > now()) {
            return true;
        }
        
        // Unlock if lock expired
        if ($user->account_locked_until && $user->account_locked_until <= now()) {
            $user->account_locked_until = null;
            $user->failed_login_attempts = 0;
            $user->save();
        }
        
        return false;
    }
}