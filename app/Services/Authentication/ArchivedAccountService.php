<?php

namespace App\Services\Authentication;

use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ArchivedAccountService
{
    /**
     * Handle archived account login attempt (web)
     */
    public function handleArchivedLogin(User $user, Request $request): RedirectResponse
    {
        // Clear any existing rate limiter for this attempt
        RateLimiter::clear($this->getThrottleKey($request));
        
        // Get archival information from metadata
        $metadata = $this->getUserMetadata($user);
        $archivalInfo = $metadata['archival_info'] ?? [];
        
        // Get original email for display (before modification)
        $originalEmail = $archivalInfo['original_email'] ?? $user->email;
        
        // Get archival reason
        $archivalReason = $archivalInfo['archived_reason'] ?? 'Account archived due to no properties';
        
        // Get archival date
        $archivedAt = $archivalInfo['archived_at'] ?? $user->deleted_at;
        $daysArchived = null;
        if ($archivedAt) {
            $daysArchived = $this->calculateDaysDifference($archivedAt);
        }
        
        // Get deletion schedule
        $deletionScheduledAt = $user->deletion_scheduled_at;
        $daysUntilDeletion = null;
        if ($deletionScheduledAt && $deletionScheduledAt > now()) {
            $daysUntilDeletion = now()->diffInDays($deletionScheduledAt);
        }
        
        // Get system contact information from SystemSettings
        $contactInfo = $this->getContactInfo();
        
        // Build the detailed archived account message
        $message = $this->buildArchivedMessage(
            $archivalReason,
            $daysArchived,
            $daysUntilDeletion,
            $deletionScheduledAt
        );
        
        // Log the archived login attempt
        Log::info('Archived account login attempt', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'original_email' => $originalEmail,
            'ip' => $request->ip(),
            'archived_at' => $archivedAt,
            'days_archived' => $daysArchived,
            'deletion_scheduled_at' => $deletionScheduledAt,
            'contact_email_used' => $contactInfo['email'],
            'contact_phone_used' => $contactInfo['phone']
        ]);
        
        // Redirect back with archived account message and all necessary data
        return redirect()->route('login')
            ->withInput($request->only('login', 'remember'))
            ->with('archived_account', $message)
            ->with('contact_email', $contactInfo['email'])
            ->with('contact_phone', $contactInfo['phone'])
            ->with('system_name', $contactInfo['system_name'])
            ->with('archived_user_email', $originalEmail)
            ->with('archived_at', $this->formatDate($archivedAt))
            ->with('days_archived', $daysArchived)
            ->with('days_until_deletion', $daysUntilDeletion)
            ->with('can_restore', $this->canRestore($daysArchived));
    }

    /**
     * Handle archived account login attempt (mobile API)
     */
    public function handleMobileArchivedLogin(User $user): JsonResponse
    {
        // Get archival information from metadata
        $metadata = $this->getUserMetadata($user);
        $archivalInfo = $metadata['archival_info'] ?? [];
        
        $originalEmail = $archivalInfo['original_email'] ?? $user->email;
        $archivalReason = $archivalInfo['archived_reason'] ?? 'Account archived due to no properties';
        
        $archivedAt = $archivalInfo['archived_at'] ?? $user->deleted_at;
        $daysArchived = null;
        if ($archivedAt) {
            $daysArchived = $this->calculateDaysDifference($archivedAt);
        }
        
        $deletionScheduledAt = $user->deletion_scheduled_at;
        $daysUntilDeletion = null;
        if ($deletionScheduledAt && $deletionScheduledAt > now()) {
            $daysUntilDeletion = now()->diffInDays($deletionScheduledAt);
        }
        
        $contactInfo = $this->getContactInfo();
        
        Log::info('Mobile archived account login attempt', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'original_email' => $originalEmail,
            'days_archived' => $daysArchived,
            'contact_email_used' => $contactInfo['email'],
            'contact_phone_used' => $contactInfo['phone']
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'account_archived',
            'message' => $this->buildArchivedMessage(
                $archivalReason,
                $daysArchived,
                $daysUntilDeletion,
                $deletionScheduledAt
            ),
            'archived_at' => $this->formatDate($archivedAt),
            'days_archived' => $daysArchived,
            'deletion_scheduled_at' => $this->formatDate($deletionScheduledAt),
            'days_until_deletion' => $daysUntilDeletion,
            'original_email' => $originalEmail,
            'contact' => [
                'email' => $contactInfo['email'],
                'phone' => $contactInfo['phone']
            ],
            'system_name' => $contactInfo['system_name'],
            'can_restore' => $this->canRestore($daysArchived)
        ], 403);
    }

    /**
     * Get contact information from system settings
     * 
     * Prioritizes:
     * 1. support_email / support_phone from system settings
     * 2. system_email / system_phone from system settings  
     * 3. Config fallbacks
     */
    protected function getContactInfo(): array
    {
        // Default fallback values from config
        $defaults = [
            'email' => config('mail.from.address', 'support@hilltop.com'),
            'phone' => config('app.support_phone', '+233 123 456 789'),
            'system_name' => config('app.name', 'Hilltop Estate Management'),
        ];
        
        try {
            // Check if SystemSetting model exists and has records
            if (class_exists(SystemSetting::class) && SystemSetting::exists()) {
                $settings = SystemSetting::getSettings();
                
                if ($settings) {
                    // Priority 1: Dedicated support contact fields
                    // Priority 2: System email/phone fields as fallback
                    return [
                        'email' => $settings->support_email 
                            ?? $settings->system_email 
                            ?? $defaults['email'],
                        'phone' => $settings->support_phone 
                            ?? $settings->system_phone 
                            ?? $defaults['phone'],
                        'system_name' => $settings->system_name 
                            ?? $settings->app_name 
                            ?? $defaults['system_name'],
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to load system settings for archived account contact info', [
                'error' => $e->getMessage(),
                'fallback_used' => true
            ]);
        }
        
        // Fallback to config values if system settings not available
        return $defaults;
    }

    /**
     * Get user metadata safely (handles both string and array)
     */
    protected function getUserMetadata(User $user): array
    {
        $metadata = [];
        if ($user->metadata) {
            if (is_string($user->metadata)) {
                $metadata = json_decode($user->metadata, true) ?? [];
            } elseif (is_array($user->metadata)) {
                $metadata = $user->metadata;
            }
        }
        return $metadata;
    }

    /**
     * Build the detailed archived account message
     */
    protected function buildArchivedMessage(
        string $reason,
        ?int $daysArchived,
        ?int $daysUntilDeletion,
        $deletionScheduledAt = null
    ): string {
        $message = "⚠️ ACCOUNT ARCHIVED\n\n";
        $message .= "Your account has been archived because: {$reason}\n\n";
        
        if ($daysArchived !== null) {
            $message .= "📅 Archived: {$daysArchived} " . $this->pluralize('day', $daysArchived) . " ago\n";
        }
        
        if ($daysUntilDeletion !== null && $daysUntilDeletion > 0) {
            $message .= "🗑️ Scheduled for permanent deletion in: {$daysUntilDeletion} " . $this->pluralize('day', $daysUntilDeletion) . "\n";
            if ($deletionScheduledAt) {
                $message .= "   Deletion date: " . $this->formatDate($deletionScheduledAt) . "\n";
            }
        } elseif ($daysUntilDeletion !== null && $daysUntilDeletion === 0) {
            $message .= "🗑️ Scheduled for permanent deletion today!\n";
        }
        
        $message .= "\n🔒 What this means:\n";
        $message .= "• You cannot access your account\n";
        $message .= "• Your data is preserved for {$this->getRestoreWindow()} days\n";
        
        if ($daysUntilDeletion !== null && $daysUntilDeletion > 0 && $daysUntilDeletion <= $this->getRestoreWindow()) {
            $message .= "• You can still restore your account within the restore window\n";
        }
        
        if ($daysUntilDeletion !== null && $daysUntilDeletion > 0) {
            $message .= "• Permanent deletion will occur on " . $this->formatDate($deletionScheduledAt) . "\n";
        }
        
        $message .= "\n📞 To restore your account, please contact support using the information below.\n";
        
        return $message;
    }

    /**
     * Calculate days difference between a date and now
     */
    protected function calculateDaysDifference($date): int
    {
        if (is_string($date)) {
            $date = new \DateTime($date);
        }
        return now()->diffInDays($date);
    }

    /**
     * Format date for display
     */
    protected function formatDate($date): ?string
    {
        if (!$date) {
            return null;
        }
        
        if (is_string($date)) {
            try {
                $date = new \DateTime($date);
            } catch (\Exception $e) {
                return $date;
            }
        }
        
        if ($date instanceof \DateTime) {
            return $date->format('F j, Y');
        }
        
        return null;
    }

    /**
     * Pluralize a word
     */
    protected function pluralize(string $word, int $count): string
    {
        return $count === 1 ? $word : $word . 's';
    }

    /**
     * Get restore window in days (from settings or default)
     */
    protected function getRestoreWindow(): int
    {
        try {
            if (class_exists(SystemSetting::class) && SystemSetting::exists()) {
                $settings = SystemSetting::getSettings();
                if ($settings && isset($settings->account_archival)) {
                    $archivalSettings = $settings->account_archival;
                    if (is_string($archivalSettings)) {
                        $archivalSettings = json_decode($archivalSettings, true);
                    }
                    return $archivalSettings['restore_window_days'] ?? 30;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get restore window from settings', [
                'error' => $e->getMessage()
            ]);
        }
        return 30;
    }

    /**
     * Check if an archived account can be restored
     */
    protected function canRestore(?int $daysArchived): bool
    {
        if ($daysArchived === null) {
            return true;
        }
        return $daysArchived <= $this->getRestoreWindow();
    }

    /**
     * Get throttle key for rate limiting
     */
    protected function getThrottleKey(Request $request): string
    {
        $login = $request->input('login', '');
        return Str::transliterate(Str::lower($login) . '|' . $request->ip());
    }

    /**
     * Get restoration details for an archived account (for API/Admin use)
     */
    public function getRestorationDetails(User $user): array
    {
        $metadata = $this->getUserMetadata($user);
        $archivalInfo = $metadata['archival_info'] ?? [];
        $contactInfo = $this->getContactInfo();
        
        $archivedAt = $archivalInfo['archived_at'] ?? $user->deleted_at;
        $daysArchived = null;
        if ($archivedAt) {
            $daysArchived = $this->calculateDaysDifference($archivedAt);
        }
        
        return [
            'can_restore' => $this->canRestore($daysArchived),
            'archived_at' => $this->formatDate($archivedAt),
            'days_archived' => $daysArchived,
            'restore_window_days' => $this->getRestoreWindow(),
            'days_remaining_to_restore' => $daysArchived !== null 
                ? max(0, $this->getRestoreWindow() - $daysArchived) 
                : $this->getRestoreWindow(),
            'original_email' => $archivalInfo['original_email'] ?? null,
            'original_phone' => $archivalInfo['original_phone'] ?? null,
            'original_username' => $archivalInfo['original_username'] ?? null,
            'archived_reason' => $archivalInfo['archived_reason'] ?? 'Not specified',
            'archived_by_name' => $archivalInfo['archived_by_name'] ?? 'System',
            'deletion_scheduled_at' => $this->formatDate($user->deletion_scheduled_at),
            'days_until_deletion' => $user->deletion_scheduled_at && $user->deletion_scheduled_at > now()
                ? now()->diffInDays($user->deletion_scheduled_at)
                : null,
            'support_contact' => [
                'email' => $contactInfo['email'],
                'phone' => $contactInfo['phone']
            ],
            'system_name' => $contactInfo['system_name']
        ];
    }

    /**
     * Check if a user account is archived (soft deleted or status archived)
     */
    public function isArchived(User $user): bool
    {
        return $user->trashed() || $user->status === 'archived';
    }

    /**
     * Get the system contact information directly (for use in controllers)
     */
    public function getSystemContactInfo(): array
    {
        return $this->getContactInfo();
    }
}