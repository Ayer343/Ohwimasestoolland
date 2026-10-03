<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UpdateSuperAdminStatusOnLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;
        
        // Only process if user is a Super Admin
        if ($user->type === User::TYPE_SUPER_ADMIN) {
            
            Log::info('Super Admin login detected', [
                'user_id' => $user->id,
                'current_status' => $user->status,
                'original_status' => $user->getOriginal('status')
            ]);
            
            // ⚡ FIX: Update status to ACTIVE if they were PENDING and created via manual setup
            if ($user->status === User::STATUS_PENDING) {
                // Check if they were created via manual setup (no invitation needed)
                $metadata = $user->metadata ?? [];
                $setupMethod = $metadata['setup_method'] ?? null;
                
                if ($setupMethod === 'manual_password') {
                    $user->update([
                        'status' => User::STATUS_ACTIVE,
                        'last_login_at' => now()
                    ]);
                    
                    Log::info('Updated Super Admin status from PENDING to ACTIVE on login', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'setup_method' => $setupMethod
                    ]);
                }
            } else {
                // Update last login time for all logins
                $user->update(['last_login_at' => now()]);
            }
        }
    }
}