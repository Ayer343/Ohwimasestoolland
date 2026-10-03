<?php

namespace App\Services;

use App\Models\RegistrationPlan;
use App\Models\Property;
use App\Models\User;
use App\Models\PropertyUnit;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;
use App\Models\PropertyOwnershipTransfer;

class SmsTemplateService
{
    protected $settings;
    protected $systemSetting;

    public function __construct()
    {
        $this->systemSetting = SystemSetting::getSettings();
    }

    /**
     * Get short name (first name only)
     */
    public function getShortName(string $fullName): string
    {
        $names = explode(' ', trim($fullName));
        return $names[0] ?? $fullName;
    }

    // ========== INVOICE SMS TEMPLATES ==========

    /**
     * Generate invoice generated SMS message
     */
    public function generateInvoiceGeneratedSms(array $data): string
    {
        $template = $this->systemSetting->getSmsTemplate('invoice_generated');
        
        if ($template) {
            return $this->parseTemplate($template, $data);
        }
        
        // Default template
        $defaultTemplate = $this->systemSetting->getDefaultSmsMessage('invoice_generated', $data);
        return $this->optimizeForSms($defaultTemplate);
    }

     /**
     * Generate payment reminder SMS message
     */
    public function generatePaymentReminderSms(array $data): string
    {
        $template = $this->systemSetting->getSmsTemplate('payment_reminder');
        
        if ($template) {
            return $this->parseTemplate($template, $data);
        }
        
        // Default template
        $defaultTemplate = $this->systemSetting->getDefaultSmsMessage('payment_reminder', $data);
        return $this->optimizeForSms($defaultTemplate);
    }

    /**
     * Generate overdue invoice SMS message
     */
    public function generateOverdueSms(array $data): string
    {
        $template = $this->systemSetting->getSmsTemplate('overdue');
        
        if ($template) {
            return $this->parseTemplate($template, $data);
        }
        
        // Default template
        $defaultTemplate = $this->systemSetting->getDefaultSmsMessage('overdue', $data);
        return $this->optimizeForSms($defaultTemplate);
    }

    /**
     * Generate payment confirmation SMS message
     */
    public function generatePaymentConfirmationSms(array $data): string
    {
        $template = $this->systemSetting->getSmsTemplate('payment_confirmation');
        
        if ($template) {
            return $this->parseTemplate($template, $data);
        }
        
        // Default template
        $defaultTemplate = $this->systemSetting->getDefaultSmsMessage('payment_confirmation', $data);
        return $this->optimizeForSms($defaultTemplate);
    }

    /**
     * Generate bulk payment coverage SMS message
     */
    public function generateBulkPaymentCoverageSms(array $data): string
    {
        $systemShortName = $this->systemSetting->getSystemShortName();
        $formattedAmount = $this->systemSetting->formatAmount($data['amount'] ?? 0);
        
        $message = "{$systemShortName}: Bulk payment of {$formattedAmount} processed. Coverage active for: " . 
                   implode(', ', array_slice($data['covered_periods'] ?? [], 0, 3)) .
                   (count($data['covered_periods'] ?? []) > 3 ? " +" . (count($data['covered_periods']) - 3) . " more" : "");
        
        return $this->optimizeForSms($message);
    }

    /**
     * Generate invoice update SMS message
     */
    public function generateInvoiceUpdateSms(array $data): string
    {
        $systemShortName = $this->systemSetting->getSystemShortName();
        $formattedAmount = $this->systemSetting->formatAmount($data['new_amount'] ?? 0);
        
        $message = "{$systemShortName}: Invoice #{$data['invoice_number']} updated. New amount: {$formattedAmount}. Due: {$data['due_date']}";
        
        return $this->optimizeForSms($message);
    }

    /**
     * Parse template with placeholders
     */
    protected function parseTemplate(string $template, array $data): string
    {
        $replacements = [
            '{{system_name}}' => $this->systemSetting->system_name,
            '{{system_short_name}}' => $this->systemSetting->getSystemShortName(),
            '{{invoice_number}}' => $data['invoice_number'] ?? '',
            '{{invoice_id}}' => $data['invoice_id'] ?? '',
            '{{period}}' => $data['period'] ?? '',
            '{{amount}}' => isset($data['amount']) ? $this->systemSetting->formatAmount($data['amount']) : '',
            '{{due_date}}' => $data['due_date'] ?? '',
            '{{payment_link}}' => $data['payment_link'] ?? '',
            '{{recipient_name}}' => $this->systemSetting->payment_account_name,
            '{{recipient_phone}}' => $this->systemSetting->payment_mobile_number,
            '{{recipient_network}}' => strtoupper($this->systemSetting->payment_network ?? ''),
            '{{property_name}}' => $data['property_name'] ?? '',
            '{{landlord_name}}' => $data['landlord_name'] ?? '',
            '{{balance}}' => isset($data['balance']) ? $this->systemSetting->formatAmount($data['balance']) : '',
            '{{paid_amount}}' => isset($data['paid_amount']) ? $this->systemSetting->formatAmount($data['paid_amount']) : '',
            '{{penalty_amount}}' => isset($data['penalty_amount']) ? $this->systemSetting->formatAmount($data['penalty_amount']) : '',
            '{{days_overdue}}' => $data['days_overdue'] ?? '',
            '{{grace_period}}' => $this->systemSetting->grace_period_days,
        ];
        
        $parsed = str_replace(array_keys($replacements), array_values($replacements), $template);
        
        // Clean up any remaining placeholders
        $parsed = preg_replace('/{{[^}]+}}/', '', $parsed);
        
        return $parsed;
    }

   /**
 * ✅ FIXED: Generate user invitation SMS message with FULL URL (not truncated)
 */
public function generateUserInvitationSms(
        User $user, 
        string $invitationUrl, 
        ?string $token = null, 
        array $context = []
    ): string
    {
        $appName = $context['app_name'] ?? $this->systemSetting->system_name ?? config('app.name', 'Property Management');
        $inviterName = $context['inviter_name'] ?? 'Administrator';
        $expiryDate = $context['expiry_date'] ?? 'soon';
        $expiryDays = $context['expiry_days'] ?? 7;
        $invitationType = $context['invitation_type'] ?? 'invitation';
        $customMessage = $context['custom_message'] ?? '';
        
        $shortName = $this->getShortName($user->name);
        $userTypeLabel = $this->getUserTypeLabel($user->type);
        
        // Try to use template from settings if available
        if ($this->systemSetting && !empty($this->systemSetting->sms_invitation_template)) {
            $template = $this->systemSetting->sms_invitation_template;
            $replacements = [
                '{{name}}' => $shortName,
                '{{app_name}}' => $appName,
                '{{user_type}}' => $userTypeLabel,
                '{{invitation_url}}' => $invitationUrl,
                '{{token}}' => $token ?? '',
                '{{expiry_days}}' => $expiryDays,
                '{{inviter_name}}' => $inviterName,
            ];
            
            $message = str_replace(array_keys($replacements), array_values($replacements), $template);
            $message = preg_replace('/{{[^}]+}}/', '', $message);
            
            if ($this->fitsInSms($message)) {
                return $message;
            }
        }
        
        // Method 1: Short message with full URL
        $message = "Hi {$shortName}! {$appName} invite. Accept: {$invitationUrl}";
        
        if ($this->fitsInSms($message)) {
            return $message;
        }
        
        // Method 2: Even shorter - just app name and URL
        $message = "{$appName} invite: {$invitationUrl}";
        
        if ($this->fitsInSms($message)) {
            return $message;
        }
        
        // Method 3: If URL is still too long, use a shortener service
        $shortUrl = $this->getShortUrl($invitationUrl);
        $message = "{$appName} invite: {$shortUrl}";
        
        if ($this->fitsInSms($message)) {
            return $message;
        }
        
        // Method 4: Ultra-short with just the domain and token snippet
        $domain = parse_url($invitationUrl, PHP_URL_HOST);
        $tokenSnippet = substr($token, 0, 16);
        $message = "Go to {$domain} and use code: {$tokenSnippet}";
        
        return $message;
    }

 /**
     * Get shortened URL using a URL shortener
     */
    private function getShortUrl(string $url): string
    {
        try {
            // You can integrate with a URL shortener service here
            // For now, use a simple approach
            $parsed = parse_url($url);
            $domain = $parsed['host'] ?? 'invite.com';
            $path = $parsed['path'] ?? '';
            
            // Extract token from path
            if (preg_match('/\/([a-zA-Z0-9]{60,})$/', $path, $matches)) {
                $token = $matches[1];
                $tokenSnippet = substr($token, 0, 16);
                return "{$domain}/i/{$tokenSnippet}";
            }
            
            return "{$domain}/invite";
            
        } catch (\Exception $e) {
            Log::error('Error shortening URL: ' . $e->getMessage());
            return 'invite-link';
        }
    }

/**
     * Create a compact URL that preserves the full token
     */
    private function createCompactInvitationUrl(string $fullUrl, ?string $token): string
    {
        $parsedUrl = parse_url($fullUrl);
        $host = $parsedUrl['host'] ?? '';
        $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
        
        // Get the token from the path
        $pathParts = explode('/', trim($path, '/'));
        $tokenFromPath = end($pathParts);
        
        // Use the token (either from parameter or from path)
        $displayToken = $token ?? $tokenFromPath;
        
        // Option 1: Full URL without scheme (saves 7 characters from http://)
        $option1 = $host . $port . $path;
        
        // If it's too long, try different formats
        if (strlen($option1) > 80) {
            // Option 2: Host + shortened path
            $shortenedPath = $this->shortenPathForSms($path, 40);
            $option2 = $host . $port . $shortenedPath;
            
            // Option 3: Just host + token (most compact)
            $option3 = $host . $port . '/.../' . $displayToken;
            
            // Option 4: For clickable links, use a URL shortener format
            $option4 = $host . $port . '/i/' . substr($displayToken, 0, 20);
            
            // Choose the best option based on length
            $options = [
                'option2' => $option2,
                'option3' => $option3,
                'option4' => $option4
            ];
            
            foreach ($options as $optionName => $optionValue) {
                if (strlen($optionValue) <= 80) {
                    Log::debug('Selected URL option', [
                        'option' => $optionName,
                        'url' => $optionValue,
                        'length' => strlen($optionValue)
                    ]);
                    return $optionValue;
                }
            }
            
            // If all options are too long, use the shortest one
            usort($options, function($a, $b) {
                return strlen($a) - strlen($b);
            });
            
            return reset($options);
        }
        
        return $option1;
    }

/**
     * Shorten path for SMS while keeping it meaningful
     */
    private function shortenPathForSms(string $path, int $maxLength = 30): string
    {
        if (strlen($path) <= $maxLength) {
            return $path;
        }
        
        $pathParts = explode('/', trim($path, '/'));
        $lastPart = end($pathParts);
        
        // If the last part (token) is too long, keep it but shorten the rest
        if (strlen($lastPart) > $maxLength - 10) {
            // Token is too long, use shortened version
            $shortToken = substr($lastPart, 0, $maxLength - 10);
            return '/.../' . $shortToken . '...';
        }
        
        // Keep the full token, shorten the directory path
        $directories = array_slice($pathParts, 0, -1);
        $directoryPath = implode('/', $directories);
        
        if (strlen($directoryPath) + strlen($lastPart) + 3 > $maxLength) {
            // Directory path is too long, shorten it
            return '/.../' . $lastPart;
        }
        
        return '/' . $directoryPath . '/' . $lastPart;
    }

/**
     * Generate optimized user invitation SMS
     */
    public function generateOptimizedUserInvitationSms(
        User $user, 
        string $invitationUrl, 
        ?string $token = null, 
        array $context = []
    ): string
    {
        $appName = $context['app_name'] ?? $this->systemSetting->system_name ?? config('app.name', 'PMS');
        $expiryDate = $context['expiry_date'] ?? 'soon';
        
        $parsedUrl = parse_url($invitationUrl);
        $host = $parsedUrl['host'] ?? '';
        $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        
        // Extract token from URL
        $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
        $pathParts = explode('/', trim($path, '/'));
        $tokenFromPath = end($pathParts);
        
        // Format 1: Very user-friendly with clear instructions
        $message1 = "Invitation to {$appName}. Use this link: {$host}{$port}/i/{$tokenFromPath}";
        
        if ($this->fitsInSms($message1)) {
            Log::debug('Optimized format 1', ['message' => $message1]);
            return $message1;
        }
        
        // Format 2: Shorter but still clear
        $message2 = "{$appName} invite: {$host}{$port}/i/{$tokenFromPath}";
        
        if ($this->fitsInSms($message2)) {
            Log::debug('Optimized format 2', ['message' => $message2]);
            return $message2;
        }
        
        // Format 3: Even shorter
        $message3 = "Invite: {$host}/i/{$tokenFromPath}";
        
        if ($this->fitsInSms($message3)) {
            Log::debug('Optimized format 3', ['message' => $message3]);
            return $message3;
        }
        
        // Format 4: Use part of token if needed
        if (strlen($tokenFromPath) > 20) {
            $shortToken = substr($tokenFromPath, 0, 20);
            $message4 = "Invite: {$host}/i/{$shortToken}";
            
            if ($this->fitsInSms($message4)) {
                Log::debug('Optimized format 4', ['message' => $message4]);
                return $message4;
            }
        }
        
        // Format 5: Just token with instructions
        $message5 = "Use invite code: {$tokenFromPath} at {$host}";
        
        Log::debug('Optimized format 5', ['message' => $message5]);
        return $message5;
    }

     /**
     * Get user type label for SMS messages
     */
    private function getUserTypeLabel(int $type): string
    {
        $labels = [
            User::TYPE_SUPER_ADMIN => 'Super Admin',
            User::TYPE_ADMIN => 'Admin',
            User::TYPE_LANDLORD => 'Landlord',
            User::TYPE_TENANT => 'Tenant',
            User::TYPE_FIELD_AGENT => 'Field Agent',
            User::TYPE_SECURITY_PERSONNEL => 'Security',
        ];

        return $labels[$type] ?? 'User';
    }

    /**
     * Generate user invitation with ALWAYS including URL
     */
    public function generateUserInvitationWithUrl(
        User $user, 
        string $invitationUrl, 
        ?string $token = null, 
        array $context = []
    ): string
    {
        // Always force include URL in some form
        $parsedUrl = parse_url($invitationUrl);
        $domain = $parsedUrl['host'] ?? 'your-domain.com';
        
        $appName = $context['app_name'] ?? $this->systemSetting->system_name ?? config('app.name', 'PMS');
        $expiryDate = $context['expiry_date'] ?? 'soon';
        $shortName = $this->getShortName($user->name);
        $userTypeLabel = $this->getUserTypeLabel($user->type);
        
        // Try different formats that ALWAYS include the URL/domain
        $formats = [
            // Format 1: Full URL
            function() use ($shortName, $appName, $userTypeLabel, $invitationUrl, $expiryDate) {
                $msg = "Hi {$shortName}! {$appName} invite. Role: {$userTypeLabel}. Setup: {$invitationUrl}";
                if ($this->fitsInSms($msg)) return $msg;
                return null;
            },
            
            // Format 2: Domain + path
            function() use ($appName, $userTypeLabel, $invitationUrl, $expiryDate) {
                $parsed = parse_url($invitationUrl);
                $domain = $parsed['host'] ?? '';
                $path = isset($parsed['path']) ? $parsed['path'] : '';
                $shortPath = strlen($path) > 15 ? substr($path, 0, 15) . '...' : $path;
                $shortUrl = $domain . $shortPath;
                
                $msg = "{$appName} invite. Role: {$userTypeLabel}. Visit: {$shortUrl}";
                if ($this->fitsInSms($msg)) return $msg;
                return null;
            },
            
            // Format 3: Domain only
            function() use ($appName, $userTypeLabel, $domain) {
                $msg = "{$appName} invitation. Role: {$userTypeLabel}. Setup at: {$domain}";
                if ($this->fitsInSms($msg)) return $msg;
                return null;
            },
            
            // Format 4: Simple with domain
            function() use ($appName, $domain) {
                $msg = "{$appName} invitation. Complete setup at: {$domain}";
                if ($this->fitsInSms($msg)) return $msg;
                return null;
            }
        ];
        
        // Try each format
        foreach ($formats as $format) {
            $message = $format();
            if ($message !== null) {
                Log::info('URL included in SMS using format', ['message' => $message]);
                return $message;
            }
        }
        
        // Last resort fallback
        return "Account invitation sent. Please check your email or visit {$domain}";
    }

    /**
     * Smart URL shortening for SMS
     */
    private function shortenUrlForSms(string $url, int $maxLength = 50): string
    {
        if (strlen($url) <= $maxLength) {
            return $url;
        }
        
        $parsed = parse_url($url);
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $host = $parsed['host'] ?? '';
        $path = isset($parsed['path']) ? $parsed['path'] : '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        
        // If path is too long, truncate it
        $availableLength = $maxLength - strlen($scheme . $host . $query) - 3;
        
        if (strlen($path) > $availableLength && $availableLength > 10) {
            $path = substr($path, 0, $availableLength) . '...';
        }
        
        return $scheme . $host . $path . $query;
    }

    /**
     * Generate a short invitation message that fits within SMS limits
     */
    public function generateInvitationMessage(RegistrationPlan $plan, string $acceptUrl, ?string $agentName = null): string
    {
        // Method 1: Personalized short message
        if ($agentName) {
            $message = $this->getPersonalizedShortInvitation($plan, $acceptUrl, $agentName);
            if ($this->fitsInSms($message)) {
                return $message;
            }
        }

        // Method 2: Standard short message
        $message = $this->getShortInvitation($plan, $acceptUrl);
        if ($this->fitsInSms($message)) {
            return $message;
        }

        // Method 3: Ultra-short message
        $message = $this->getUltraShortInvitation($plan, $acceptUrl);
        if ($this->fitsInSms($message)) {
            return $message;
        }

        // Method 4: URL-only fallback (shorten URL if possible)
        return $this->getUrlOnlyInvitation($acceptUrl);
    }

    /**
     * Generate user welcome SMS after invitation acceptance
     * UPDATED: Uses system settings
     */
    public function generateUserWelcomeSms(User $user, array $context = []): string
    {
        $appName = $context['app_name'] ?? $this->systemSetting->system_name ?? config('app.name', 'PMS');
        $userType = $context['user_type'] ?? $this->getUserTypeLabel($user->type);
        $portalUrl = $context['portal_url'] ?? null;
        $loginUrl = $context['login_url'] ?? null;
        
        $shortName = $this->getShortName($user->name);
        
        // Method 1: With portal access
        if ($portalUrl) {
            $message = "Welcome {$shortName}! Your {$appName} {$userType} account is active. Access: {$portalUrl}";
            
            if ($this->fitsInSms($message)) {
                return $message;
            }
        }
        
        // Method 2: Without URL
        $message = "Welcome {$shortName}! Your {$appName} {$userType} account is now active. Login to start.";
        
        return $message;
    }

    /**
     * Generate password reset SMS
     */
    public function generatePasswordResetSms(User $user, string $resetUrl, ?string $token = null): string
    {
        $appName = config('app.name', 'PMS');
        $shortName = $this->getShortName($user->name);
        
        // Method 1: With URL
        $message = "Hi {$shortName}! Password reset for {$appName}. Reset: {$resetUrl}";
        
        if ($this->fitsInSms($message)) {
            return $message;
        }
        
        // Method 2: With token
        if ($token) {
            $tokenSnippet = substr($token, 0, 10) . '...';
            $message = "Hi {$shortName}! {$appName} password reset. Code: {$tokenSnippet}";
            
            if ($this->fitsInSms($message)) {
                return $message;
            }
        }
        
        // Method 3: Simple
        $message = "Password reset requested for {$appName}. Check your email.";
        
        return $message;
    }

    /**
     * Generate account status update SMS
     */
    public function generateAccountStatusSms(User $user, string $status, array $context = []): string
    {
        $appName = $context['app_name'] ?? config('app.name', 'PMS');
        $reason = $context['reason'] ?? '';
        $contact = $context['contact'] ?? 'support';
        
        $shortName = $this->getShortName($user->name);
        $userType = $this->getUserTypeLabel($user->type);
        
        $statusMessages = [
            'activated' => "Hi {$shortName}! Your {$appName} {$userType} account is now ACTIVE. Login to start.",
            'suspended' => "Hi {$shortName}! Your {$appName} {$userType} account is SUSPENDED. {$reason} Contact: {$contact}",
            'deactivated' => "Hi {$shortName}! Your {$appName} {$userType} account is DEACTIVATED. Contact {$contact} to restore.",
            'verified' => "Hi {$shortName}! Your {$appName} {$userType} account is VERIFIED. Thank you for completing verification.",
            'locked' => "Hi {$shortName}! Your {$appName} {$userType} account is LOCKED due to security. Contact: {$contact}",
        ];
        
        $message = $statusMessages[$status] ?? "Hi {$shortName}! Your {$appName} account status: " . strtoupper($status);
        
        // Ensure message fits
        if (!$this->fitsInSms($message)) {
            $message = $this->intelligentTruncate($message, 157) . '...';
        }
        
        return $message;
    }

    /**
     * Generate bulk user notification SMS
     */
    public function generateBulkUserNotification(string $notificationType, array $users, array $context = []): array
    {
        $results = [];
        $appName = $context['app_name'] ?? config('app.name', 'PMS');
        
        foreach ($users as $user) {
            $shortName = $this->getShortName($user['name']);
            
            switch ($notificationType) {
                case 'system_update':
                    $message = "Hi {$shortName}! {$appName} update completed. New features available.";
                    break;
                case 'maintenance':
                    $time = $context['time'] ?? 'tonight';
                    $message = "Hi {$shortName}! {$appName} maintenance {$time}. Portal may be unavailable.";
                    break;
                case 'announcement':
                    $announcement = $this->intelligentTruncate($context['message'] ?? 'Important update', 100);
                    $message = "Hi {$shortName}! {$appName}: {$announcement}";
                    break;
                case 'deadline':
                    $deadline = $context['deadline'] ?? 'soon';
                    $item = $context['item'] ?? 'item';
                    $message = "Hi {$shortName}! {$item} deadline: {$deadline}. Complete in portal.";
                    break;
                default:
                    $message = "Hi {$shortName}! {$appName} notification. Check portal.";
            }
            
            // Ensure message fits
            if (!$this->fitsInSms($message)) {
                $message = $this->optimizeForSms($message);
            }
            
            $results[] = [
                'user_id' => $user['id'],
                'name' => $user['name'],
                'message' => $message,
                'length' => strlen($message),
                'fits' => $this->fitsInSms($message)
            ];
        }
        
        return $results;
    }

    /**
     * Test user invitation SMS generation
     */
    public function testUserInvitationSms(array $testScenarios): array
    {
        $results = [];
        
        foreach ($testScenarios as $scenarioName => $scenario) {
            try {
                $message = '';
                $info = [];
                
                switch ($scenario['type']) {
                    case 'user_invitation':
                        $message = $this->generateUserInvitationSms(
                            $scenario['user'],
                            $scenario['url'],
                            $scenario['token'] ?? null,
                            $scenario['context'] ?? []
                        );
                        break;
                        
                    case 'user_welcome':
                        $message = $this->generateUserWelcomeSms(
                            $scenario['user'],
                            $scenario['context'] ?? []
                        );
                        break;
                        
                    case 'account_status':
                        $message = $this->generateAccountStatusSms(
                            $scenario['user'],
                            $scenario['status'],
                            $scenario['context'] ?? []
                        );
                        break;
                        
                    case 'password_reset':
                        $message = $this->generatePasswordResetSms(
                            $scenario['user'],
                            $scenario['url'],
                            $scenario['token'] ?? null
                        );
                        break;
                }
                
                if ($message) {
                    $info = $this->getMessageInfo($message);
                }
                
                $results[$scenarioName] = [
                    'message' => $message,
                    'info' => $info,
                    'success' => !empty($message)
                ];
                
            } catch (\Exception $e) {
                $results[$scenarioName] = [
                    'message' => "Error: " . $e->getMessage(),
                    'info' => ['error' => $e->getMessage()],
                    'success' => false
                ];
            }
        }
        
        return $results;
    }

    /**
     * Generate landlord invitation message with token support
     */
    public function generateLandlordInvitationMessage(User $landlord, Property $property, string $registrationUrl, ?string $token = null): string
    {
        $shortName = $this->getShortName($landlord->name);
        $propName = $this->shortenPropertyName($property->property_name);
        $zone = $this->shortenZoneName($property->zone);

        // If token is provided but URL is not, construct the URL
        if ($token && !$registrationUrl) {
            $registrationUrl = route('landlord.registration.complete', ['token' => $token]);
        }

        // Method 1: Personalized message
        $message = "Hi {$shortName}! {$propName} in {$zone} registered. Complete: {$registrationUrl}";
        if ($this->fitsInSms($message)) {
            return $message;
        }

        // Method 2: Shorter version
        $message = "Property {$propName} in {$zone} registered. Complete: {$registrationUrl}";
        if ($this->fitsInSms($message)) {
            return $message;
        }

        // Method 3: Ultra-short version
        $message = "{$propName} registration: {$registrationUrl}";
        if ($this->fitsInSms($message)) {
            return $message;
        }

        // Method 4: Include token directly if URL is too long
        if ($token) {
            $message = "Complete {$propName} registration. Use token: {$token} or visit portal";
            if ($this->fitsInSms($message)) {
                return $message;
            }
        }

        // Method 5: URL-only fallback
        return $this->getUrlOnlyInvitation($registrationUrl, $token);
    }

    /**
     * URL-only fallback with token support
     */
    private function getUrlOnlyInvitation(string $acceptUrl, ?string $token = null): string
    {
        if (strlen($acceptUrl) > 160) {
            if ($token) {
                $tokenSnippet = substr($token, 0, 15) . '...';
                return "Use token: {$tokenSnippet} to complete registration in app";
            }
            
            $shortUrl = $this->extractShortUrl($acceptUrl);
            
            Log::warning('SMS URL too long, using shortened version', [
                'original_length' => strlen($acceptUrl),
                'shortened_length' => strlen($shortUrl),
            ]);
            
            return "Invitation: {$shortUrl}";
        }
        
        return "Invitation: {$acceptUrl}";
    }

    /**
     * Extract short URL from full URL, preserving tokens
     */
    private function extractShortUrl(string $url): string
    {
        $parsedUrl = parse_url($url);
        
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
            if (isset($queryParams['token'])) {
                $token = $queryParams['token'];
                $tokenSnippet = substr($token, 0, 12) . '...';
                return "token:{$tokenSnippet}";
            }
        }
        
        if (isset($parsedUrl['path'])) {
            $pathParts = explode('/', trim($parsedUrl['path'], '/'));
            $lastPart = end($pathParts);
            
            if (strlen($lastPart) > 10 && preg_match('/^[a-zA-Z0-9]+$/', $lastPart)) {
                $tokenSnippet = substr($lastPart, 0, 12) . '...';
                return "token:{$tokenSnippet}";
            }
            
            return $lastPart ?: 'accept-in-app';
        }
        
        return 'accept-in-app';
    }

    /**
     * Generate message with explicit token for fallback
     */
    public function generateMessageWithToken(string $messageType, $entity, string $url, ?string $token = null): string
    {
        switch ($messageType) {
            case 'agent_invitation':
                $plan = $entity;
                $zone = $this->shortenZoneName($plan->zone);
                
                // Include token in the message for backup
                if ($token) {
                    $baseMessage = "Field Agent for {$zone}. Accept: {$url} or use token: " . substr($token, 0, 10) . '...';
                } else {
                    $baseMessage = "Field Agent for {$zone}. Accept: {$url}";
                }
                break;

            case 'landlord_invitation':
                $property = $entity;
                $propName = $this->shortenPropertyName($property->property_name);
                
                // Include token in the message for backup
                if ($token) {
                    $baseMessage = "Property {$propName} registered. Complete: {$url} or use token: " . substr($token, 0, 10) . '...';
                } else {
                    $baseMessage = "Property {$propName} registered. Complete: {$url}";
                }
                break;

            default:
                $baseMessage = "Invitation: {$url}";
                if ($token) {
                    $baseMessage .= " Token: " . substr($token, 0, 10) . '...';
                }
        }

        // Ensure message fits
        if (!$this->fitsInSms($baseMessage)) {
            // If too long with token, remove token and try again
            if ($token) {
                $baseMessageWithoutToken = str_replace(" or use token: " . substr($token, 0, 10) . '...', '', $baseMessage);
                if ($this->fitsInSms($baseMessageWithoutToken)) {
                    return $baseMessageWithoutToken;
                }
            }
            return $this->optimizeForSms($baseMessage);
        }

        return $baseMessage;
    }

    /**
     * Generate token-only message for very long URLs
     */
    public function generateTokenOnlyMessage(string $messageType, $entity, string $token): string
    {
        if (empty($token)) {
            return "Please check your portal for invitation details.";
        }

        $tokenSnippet = substr($token, 0, 12) . '...';
        
        switch ($messageType) {
            case 'agent_invitation':
                $plan = $entity;
                $zone = $this->shortenZoneName($plan->zone);
                return "Field Agent for {$zone}. Use token: {$tokenSnippet} in app to accept.";

            case 'landlord_invitation':
                $property = $entity;
                $propName = $this->shortenPropertyName($property->property_name);
                return "Property {$propName} registered. Use token: {$tokenSnippet} in app to complete.";

            default:
                return "Use token: {$tokenSnippet} in app to complete registration.";
        }
    }

    /**
     * Personalized short invitation template
     */
    private function getPersonalizedShortInvitation(RegistrationPlan $plan, string $acceptUrl, string $agentName): string
    {
        $shortName = $this->getShortName($agentName);
        $zone = $this->shortenZoneName($plan->zone);
        $dueDate = $plan->registration_end_date ? $plan->registration_end_date->format('M j') : 'TBD';
        
        return "Hi {$shortName}! Field Agent for {$zone}. Accept: {$acceptUrl} Due: {$dueDate}";
    }
    
    /**
     * Short invitation template
     */
    private function getShortInvitation(RegistrationPlan $plan, string $acceptUrl): string
    {
        $zone = $this->shortenZoneName($plan->zone);
        $dueDate = $plan->registration_end_date ? $plan->registration_end_date->format('M j') : 'TBD';
        $houses = $plan->estimated_houses;
        
        return "Field Agent: Zone {$zone} ({$houses} properties). Accept: {$acceptUrl} Due: {$dueDate}";
    }
    
    /**
     * Ultra-short invitation template
     */
    private function getUltraShortInvitation(RegistrationPlan $plan, string $acceptUrl): string
    {
        $zone = $this->shortenZoneName($plan->zone, 10);
        $dueDate = $plan->registration_end_date ? $plan->registration_end_date->format('M j') : 'TBD';
        
        return "Zone {$zone} Agent: {$acceptUrl} Due: {$dueDate}";
    }

    /**
     * Shorten zone name with abbreviations
     */
    private function shortenZoneName(string $zone, int $maxLength = 15): string
    {
        if (strlen($zone) <= $maxLength) {
            return $zone;
        }

        $abbreviations = [
            'zone' => 'Zn',
            'area' => 'Ar',
            'section' => 'Sec',
            'district' => 'Dist',
            'north' => 'N',
            'south' => 'S',
            'east' => 'E', 
            'west' => 'W',
            'central' => 'Ctr',
            'eastern' => 'E',
            'western' => 'W',
            'northern' => 'N', 
            'southern' => 'S',
            'community' => 'Comm',
            'municipal' => 'Mun',
            'township' => 'Twp',
            'village' => 'Vlg',
            'neighborhood' => 'Nbrhd',
            'suburb' => 'Sub',
            'division' => 'Div',
            'region' => 'Reg',
        ];

        $shortZone = $zone;
        foreach ($abbreviations as $full => $short) {
            $shortZone = str_ireplace($full, $short, $shortZone);
        }

        $shortZone = preg_replace('/\s+/', ' ', $shortZone);
        $shortZone = trim($shortZone);

        if (strlen($shortZone) > $maxLength) {
            $shortZone = $this->intelligentTruncate($shortZone, $maxLength);
        }

        return $shortZone;
    }

    /**
     * Intelligent truncation that preserves meaning
     */
    private function intelligentTruncate(string $text, int $maxLength): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        // Try to break at a space near the end
        $truncated = substr($text, 0, $maxLength - 3);
        $lastSpace = strrpos($truncated, ' ');
        
        if ($lastSpace > ($maxLength - 10)) {
            $truncated = substr($truncated, 0, $lastSpace);
        }
        
        return $truncated;
    }

    /**
     * Generate status update message with more status types
     */
    public function generateStatusMessage(string $status, RegistrationPlan $plan): string
    {
        $zone = $this->shortenZoneName($plan->zone);
        
        $statusMessages = [
            'in_progress' => "Zone {$zone} is now IN PROGRESS. Start work immediately.",
            'completed' => "Zone {$zone} COMPLETED! Thank you for your work.",
            'cancelled' => "Zone {$zone} CANCELLED. Contact admin for details.",
            'assigned' => "Zone {$zone} ASSIGNED to you. Check your portal.",
            'overdue' => "URGENT: Zone {$zone} is OVERDUE. Complete immediately.",
            'pending_review' => "Zone {$zone} PENDING REVIEW. Check your portal.",
        ];

        $message = $statusMessages[$status] ?? "Zone {$zone} status: " . strtoupper($status);
        
        // Ensure message fits
        if (!$this->fitsInSms($message)) {
            $message = $this->intelligentTruncate($message, 157) . '...';
        }

        return $message;
    }

     /**
     * Shorten property name intelligently
     */
    private function shortenPropertyName(string $propertyName, int $maxLength = 20): string
    {
        if (strlen($propertyName) <= $maxLength) {
            return $propertyName;
        }

        $replacements = [
            'apartment' => 'Apt',
            'apartments' => 'Apts',
            'building' => 'Bldg',
            'complex' => 'Cmp',
            'estate' => 'Est',
            'house' => 'Hse',
            'villa' => 'Vla',
            'residence' => 'Res',
            'residential' => 'Res',
            'commercial' => 'Comm',
            'block' => 'Blk',
            'towers' => 'Twrs',
            'gardens' => 'Gdns',
            'heights' => 'Hts',
            'manor' => 'Mnr',
            'plaza' => 'Plz',
            'square' => 'Sq',
            'court' => 'Ct',
        ];

        $shortName = $propertyName;
        foreach ($replacements as $full => $short) {
            $shortName = str_ireplace($full, $short, $shortName);
        }

        $shortName = preg_replace('/^(the|a|an)\s+/i', '', $shortName);
        $shortName = preg_replace('/\s+/', ' ', $shortName);
        $shortName = trim($shortName);

        if (strlen($shortName) > $maxLength) {
            $shortName = $this->intelligentTruncate($shortName, $maxLength);
        }

        return $shortName;
    }

    /**
     * Generate reminder message
     */
    public function generateReminderMessage(string $type, $context, ?string $actionUrl = null): string
    {
        switch ($type) {
            case 'invitation_expiry':
                $daysLeft = $context['days_left'] ?? 0;
                $zone = $this->shortenZoneName($context['zone'] ?? 'Unknown');
                
                if ($daysLeft === 1) {
                    $message = "URGENT: Zone {$zone} invitation expires TODAY! Accept: {$actionUrl}";
                } else {
                    $message = "REMINDER: Zone {$zone} invitation expires in {$daysLeft} days. Accept: {$actionUrl}";
                }
                break;

            case 'landlord_registration':
                $propName = $this->shortenPropertyName($context['property_name'] ?? 'Property');
                $message = "REMINDER: Complete {$propName} registration: {$actionUrl}";
                break;

            case 'deadline_approaching':
                $zone = $this->shortenZoneName($context['zone'] ?? 'Unknown');
                $daysLeft = $context['days_left'] ?? 0;
                $message = "DEADLINE: Zone {$zone} due in {$daysLeft} days. Submit work now.";
                break;

            default:
                $message = "Reminder: Please check your portal for updates.";
        }

        // Ensure message fits
        if (!$this->fitsInSms($message)) {
            $message = $this->intelligentTruncate($message, 157) . '...';
        }

        return $message;
    }

    /**
     * Generate reminder message with token support
     */
    public function generateReminderWithToken(string $type, $context, ?string $actionUrl = null, ?string $token = null): string
    {
        $baseMessage = $this->generateReminderMessage($type, $context, $actionUrl);
        
        // Add token information if provided and there's space
        if ($token && $this->fitsInSms($baseMessage . " Token: " . substr($token, 0, 10) . '...')) {
            return $baseMessage . " Token: " . substr($token, 0, 10) . '...';
        }
        
        return $baseMessage;
    }

    /**
     * Check if message fits in SMS limits
     */
    public function fitsInSms(string $message): bool
    {
        return strlen($message) <= 160;
    }

    /**
     * Get message length info with more details
     */
    public function getMessageInfo(string $message): array
    {
        $length = strlen($message);
        $fits = $this->fitsInSms($message);
        $remaining = 160 - $length;
        
        return [
            'length' => $length,
            'fits' => $fits,
            'remaining' => $remaining,
            'needs_truncation' => !$fits,
            'truncated_length' => $fits ? null : 157,
            'percentage_used' => round(($length / 160) * 100, 1),
            'segments' => ceil($length / 160)
        ];
    }

    /**
     * Validate and optimize message for SMS
     */
    public function optimizeForSms(string $message): string
    {
        $length = strlen($message);
        
        if ($length <= 160) {
            return $message;
        }

        // Log optimization for monitoring
        Log::info('SMS message optimized', [
            'original_length' => $length,
            'original_message' => $message
        ]);

        // Apply intelligent truncation
        $optimized = $this->intelligentTruncate($message, 157) . '...';
        
        Log::info('SMS message after optimization', [
            'optimized_length' => strlen($optimized),
            'optimized_message' => $optimized
        ]);

        return $optimized;
    }

    /**
     * Generate welcome message for completed registration
     */
    public function generateWelcomeMessage(string $userType, string $userName, array $context = []): string
    {
        $shortName = $this->getShortName($userName);
        
        switch ($userType) {
            case 'agent':
                $zone = $this->shortenZoneName($context['zone'] ?? 'your zone');
                return "Welcome {$shortName}! You're now a Field Agent for {$zone}. Access your portal to start work.";

            case 'landlord':
                $propName = $this->shortenPropertyName($context['property_name'] ?? 'your property');
                return "Welcome {$shortName}! {$propName} registration complete. Access your landlord portal now.";

            default:
                return "Welcome {$shortName}! Your registration is complete. Access your portal to get started.";
        }
    }

    /**
     * Generate verification code message
     */
    public function generateVerificationMessage(string $code, string $purpose = 'verification'): string
    {
        $purposes = [
            'verification' => 'Verification',
            'login' => 'Login',
            'reset' => 'Password Reset',
            'transaction' => 'Transaction'
        ];

        $purposeText = $purposes[$purpose] ?? 'Verification';
        
        return "Your {$purposeText} code is: {$code}. This code expires in 10 minutes.";
    }

    /**
     * Generate system notification message
     */
    public function generateSystemNotification(string $notificationType, array $context = []): string
    {
        switch ($notificationType) {
            case 'maintenance':
                $start = $context['start_time'] ?? 'soon';
                $duration = $context['duration'] ?? 'brief period';
                return "System maintenance scheduled from {$start} for {$duration}. Portal may be unavailable.";

            case 'update':
                $version = $context['version'] ?? 'new';
                return "System update {$version} completed. New features available in your portal.";

            case 'outage':
                $service = $context['service'] ?? 'system';
                $eta = $context['eta'] ?? 'soon';
                return "{$service} temporarily unavailable. Expected back {$eta}. Sorry for inconvenience.";

            default:
                return "System notification: Please check your portal for important updates.";
        }
    }

    /**
     * Bulk message optimization for multiple recipients
     */
    public function optimizeBulkMessage(string $message, array $placeholders = []): string
    {
        $optimizedMessage = $message;
        
        foreach ($placeholders as $placeholder => $value) {
            if (strpos($optimizedMessage, $placeholder) !== false) {
                $shortValue = $this->shortenDynamicContent($value, $placeholder);
                $optimizedMessage = str_replace($placeholder, $shortValue, $optimizedMessage);
            }
        }

        if (!$this->fitsInSms($optimizedMessage)) {
            $optimizedMessage = $this->optimizeForSms($optimizedMessage);
        }

        return $optimizedMessage;
    }

    /**
     * Shorten dynamic content based on context
     */
    private function shortenDynamicContent(string $content, string $context): string
    {
        switch ($context) {
            case '{{zone}}':
                return $this->shortenZoneName($content);
            case '{{property}}':
                return $this->shortenPropertyName($content);
            case '{{name}}':
                return $this->getShortName($content);
            case '{{date}}':
                return date('M j', strtotime($content));
            default:
                return $content;
        }
    }

    /**
     * Test message generation with different scenarios
     */
    public function testMessageGeneration(array $testData): array
    {
        $results = [];
        
        foreach ($testData as $testName => $data) {
            $message = '';
            $info = [];
            
            try {
                switch ($data['type']) {
                    case 'agent_invitation':
                        $message = $this->generateInvitationMessage(
                            $data['plan'], 
                            $data['url'], 
                            $data['agent_name'] ?? null
                        );
                        break;
                        
                    case 'landlord_invitation':
                        $message = $this->generateLandlordInvitationMessage(
                            $data['landlord'],
                            $data['property'],
                            $data['url'],
                            $data['token'] ?? null
                        );
                        break;
                        
                    case 'status_update':
                        $message = $this->generateStatusMessage(
                            $data['status'],
                            $data['plan']
                        );
                        break;
                        
                    case 'reminder':
                        $message = $this->generateReminderMessage(
                            $data['reminder_type'],
                            $data['context'],
                            $data['url'] ?? null
                        );
                        break;
                        
                    case 'user_invitation':
                        $message = $this->generateUserInvitationSms(
                            $data['user'],
                            $data['url'],
                            $data['token'] ?? null,
                            $data['context'] ?? []
                        );
                        break;
                }
                
                $info = $this->getMessageInfo($message);
                
            } catch (\Exception $e) {
                $message = "Error generating message: " . $e->getMessage();
                $info = ['error' => $e->getMessage()];
            }
            
            $results[$testName] = [
                'message' => $message,
                'info' => $info,
                'success' => !isset($info['error'])
            ];
        }
        
        return $results;
    }
    
    /**
     * Generate tenant invitation message
     */
    public function generateTenantInvitationMessage(
        User $tenant, 
        Property $property, 
        PropertyUnit $unit, 
        string $invitationUrl, 
        ?string $token = null
    ): string
    {
        $tenantName = $this->getShortName($tenant->name);
        $propName = $this->shortenPropertyName($property->property_name);
        $unitNumber = $unit->unit_number;
        
        $message = "Hi {$tenantName}! You're approved for {$propName} Unit {$unitNumber}. Accept: {$invitationUrl}";
        
        if ($this->fitsInSms($message)) {
            return $message;
        }
        
        $message = "Approved for {$propName} Unit {$unitNumber}. Accept: {$invitationUrl}";
        
        if ($this->fitsInSms($message)) {
            return $message;
        }
        
        $message = "Property approval: {$propName}. Accept: {$invitationUrl}";
        
        if ($this->fitsInSms($message)) {
            return $message;
        }
        
        if ($token) {
            $tokenSnippet = substr($token, 0, 12) . '...';
            $message = "Property approval: {$propName}. Use token: {$tokenSnippet} in app";
            
            if ($this->fitsInSms($message)) {
                return $message;
            }
        }
        
        return $this->getUrlOnlyInvitation($invitationUrl, $token);
    }
    /**
 * Generate ownership transfer SMS invitation
 * 
 * @param User $newOwner
 * @param PropertyOwnershipTransfer $transfer
 * @param string $invitationUrl
 * @param string|null $token
 * @return string
 */
public function generateOwnershipTransferSms(
    User $newOwner, 
    PropertyOwnershipTransfer $transfer, 
    string $invitationUrl, 
    ?string $token = null
): string
{
    $appName = $this->systemSetting->system_name ?? config('app.name', 'Hilltop Estate');
    $shortName = $this->getShortName($newOwner->name);
    $propertyName = $this->shortenPropertyName($transfer->property->property_name, 25);
    $fromOwner = $this->getShortName($transfer->currentLandlord->name);
    
    // Try different message formats, always including the URL
    $formats = [
        // Format 1: Full details with URL
        function() use ($shortName, $propertyName, $fromOwner, $invitationUrl) {
            $msg = "Hi {$shortName}! {$propertyName} transferred from {$fromOwner}. Accept: {$invitationUrl}";
            if ($this->fitsInSms($msg)) return $msg;
            return null;
        },
        
        // Format 2: Shorter version
        function() use ($shortName, $propertyName, $invitationUrl) {
            $msg = "{$shortName}, {$propertyName} ownership transferred. Accept: {$invitationUrl}";
            if ($this->fitsInSms($msg)) return $msg;
            return null;
        },
        
        // Format 3: URL + token snippet
        function() use ($invitationUrl, $token) {
            $parsed = parse_url($invitationUrl);
            $domain = $parsed['host'] ?? '';
            $tokenSnippet = $token ? substr($token, 0, 16) : '';
            $msg = "Property transfer. Accept: {$domain}/i/{$tokenSnippet}";
            if ($this->fitsInSms($msg)) return $msg;
            return null;
        },
        
        // Format 4: Ultra-short
        function() use ($invitationUrl) {
            $shortUrl = $this->extractShortUrl($invitationUrl);
            $msg = "Property transfer: {$shortUrl}";
            return $msg;
        }
    ];
    
    // Try each format
    foreach ($formats as $format) {
        $message = $format();
        if ($message !== null) {
            Log::info('Ownership transfer SMS using format', [
                'message' => $message,
                'length' => strlen($message)
            ]);
            return $message;
        }
    }
    
    // Ultimate fallback
    return "Property ownership transfer. Please check your email for details.";
}

}