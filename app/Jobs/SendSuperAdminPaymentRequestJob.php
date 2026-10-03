<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\AdminBillingRecord;
use App\Models\User;
use App\Models\DeveloperSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SuperAdminPaymentRequestNotification;
use App\Notifications\PaymentRequestSmsNotification;

class SendSuperAdminPaymentRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The payment request instance.
     */
    protected AdminBillingRecord $paymentRequest;

    /**
     * The super admin user.
     */
    protected User $superAdmin;

    /**
     * Whether this is a reminder.
     */
    protected bool $isReminder;

    /**
     * The developer settings.
     */
    protected DeveloperSetting $developerSettings;

    /**
     * Create a new job instance.
     */
    public function __construct(AdminBillingRecord $paymentRequest, User $superAdmin, bool $isReminder = false)
    {
        $this->paymentRequest = $paymentRequest->withoutRelations();
        $this->superAdmin = $superAdmin->withoutRelations();
        $this->isReminder = $isReminder;
        
        // Load developer settings
        $this->developerSettings = DeveloperSetting::find($paymentRequest->developer_setting_id);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Check if payment request is still valid
            if (!$this->isPaymentRequestValid()) {
                Log::warning('Payment request is no longer valid, skipping notification', [
                    'payment_request_id' => $this->paymentRequest->id,
                    'status' => $this->paymentRequest->status
                ]);
                return;
            }

            if ($this->isReminder) {
                $this->sendPaymentReminder();
            } else {
                $this->sendPaymentRequest();
            }
            
            Log::info('Super Admin payment request sent successfully', [
                'payment_request_id' => $this->paymentRequest->id,
                'super_admin_id' => $this->superAdmin->id,
                'super_admin_email' => $this->superAdmin->email,
                'is_reminder' => $this->isReminder,
                'amount' => $this->paymentRequest->amount,
                'currency' => $this->paymentRequest->currency,
                'invoice_number' => $this->paymentRequest->invoice_number
            ]);
            
            // Update metadata
            $this->updatePaymentRequestMetadata();
            
        } catch (\Exception $e) {
            Log::error('Failed to send Super Admin payment request: ' . $e->getMessage(), [
                'payment_request_id' => $this->paymentRequest->id,
                'super_admin_id' => $this->superAdmin->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->markPaymentRequestAsFailed($e->getMessage());
            
            // Re-throw for queue retry mechanism
            throw $e;
        }
    }

    /**
     * Send payment request email.
     */
    private function sendPaymentRequest(): void
    {
        $subject = $this->isReminder 
            ? 'Reminder: Payment Request - ' . $this->paymentRequest->invoice_number
            : 'New Payment Request - ' . $this->paymentRequest->invoice_number;
        
        $mailData = [
            'paymentRequest' => $this->paymentRequest,
            'superAdmin' => $this->superAdmin,
            'developer' => $this->developerSettings,
            'developerUser' => User::where('email', $this->developerSettings->developer_email)->first(),
            'isReminder' => $this->isReminder,
            'subject' => $subject,
            'action_url' => route('admin.billing.requests.view', $this->paymentRequest->id),
            'payment_details' => $this->getPaymentDetails(),
            'due_in_days' => now()->diffInDays($this->paymentRequest->due_date, false),
            'formatted_amount' => number_format($this->paymentRequest->amount, 2),
            'formatted_due_date' => $this->paymentRequest->due_date->format('F d, Y'),
            'current_date' => now()->format('F d, Y'),
        ];
        
        // Send email using Laravel Notification
        Notification::send($this->superAdmin, new SuperAdminPaymentRequestNotification($mailData));
        
        // Also send to developer for record
        if ($this->developerSettings->developer_email) {
            $this->sendCopyToDeveloper($mailData);
        }
        
        // Send SMS if configured
        $this->sendSmsNotification();
        
        // Send in-app notification
        $this->sendInAppNotification();
    }

    /**
     * Send payment reminder email.
     */
    private function sendPaymentReminder(): void
    {
        $this->sendPaymentRequest();
        
        // Record the reminder specifically
        $this->recordReminder();
    }

    /**
     * Send SMS notification.
     */
    private function sendSmsNotification(): void
    {
        // Check if super admin has phone number and SMS is enabled
        if (empty($this->superAdmin->phone) || !config('services.sms.enabled', false)) {
            return;
        }
        
        try {
            $message = $this->buildSmsMessage();
            
            // Log SMS for now (integration would go here)
            Log::info('SMS notification prepared', [
                'to' => $this->superAdmin->phone,
                'message' => $message,
                'payment_request_id' => $this->paymentRequest->id,
                'gateway' => config('services.sms.default', 'log')
            ]);
            
            // Send SMS via Notification
            Notification::send($this->superAdmin, new PaymentRequestSmsNotification([
                'message' => $message,
                'payment_request' => $this->paymentRequest,
                'is_reminder' => $this->isReminder
            ]));
            
        } catch (\Exception $e) {
            Log::warning('Failed to send SMS notification: ' . $e->getMessage(), [
                'payment_request_id' => $this->paymentRequest->id,
                'super_admin_phone' => $this->superAdmin->phone
            ]);
        }
    }

    /**
     * Build SMS message.
     */
    private function buildSmsMessage(): string
    {
        $prefix = $this->isReminder ? 'REMINDER: ' : '';
        $amount = number_format($this->paymentRequest->amount, 2);
        $currency = $this->paymentRequest->currency;
        $dueDate = $this->paymentRequest->due_date->format('M d');
        $invoice = $this->paymentRequest->invoice_number;
        $developer = $this->developerSettings->developer_name;
        
        if ($this->isReminder) {
            return "{$prefix}Payment request #{$invoice} from {$developer} for {$currency}{$amount} is due on {$dueDate}. Please make payment.";
        }
        
        return "NEW: Payment request #{$invoice} from {$developer} for {$currency}{$amount}. Due: {$dueDate}. Please review and process.";
    }

    /**
     * Send in-app notification.
     */
    private function sendInAppNotification(): void
    {
        try {
            $notificationType = $this->isReminder ? 'payment_request_reminder' : 'new_payment_request';
            
            $notificationData = [
                'title' => $this->isReminder 
                    ? '⏰ Payment Request Reminder'
                    : '💰 New Payment Request',
                'message' => $this->isReminder
                    ? "Reminder: Payment request #{$this->paymentRequest->invoice_number} for {$this->paymentRequest->currency}{$this->paymentRequest->amount} is due soon"
                    : "New payment request #{$this->paymentRequest->invoice_number} for {$this->paymentRequest->currency}{$this->paymentRequest->amount}",
                'icon' => $this->isReminder ? 'fas fa-bell text-warning' : 'fas fa-money-bill-wave text-primary',
                'category' => 'billing',
                'action_url' => route('admin.billing.requests.view', $this->paymentRequest->id),
                'priority' => $this->isReminder ? 2 : 1,
                'data' => [
                    'type' => $notificationType,
                    'payment_request_id' => $this->paymentRequest->id,
                    'invoice_number' => $this->paymentRequest->invoice_number,
                    'amount' => $this->paymentRequest->amount,
                    'currency' => $this->paymentRequest->currency,
                    'developer_id' => $this->developerSettings->id,
                    'developer_name' => $this->developerSettings->developer_name,
                    'due_date' => $this->paymentRequest->due_date->toISOString(),
                    'is_reminder' => $this->isReminder,
                    'sent_at' => now()->toISOString()
                ]
            ];
            
            // Use Laravel's notification system
            $this->superAdmin->notifications()->create([
                'type' => 'App\\Notifications\\PaymentRequestNotification',
                'notifiable_type' => get_class($this->superAdmin),
                'notifiable_id' => $this->superAdmin->id,
                'data' => json_encode($notificationData),
                'read_at' => null
            ]);
            
        } catch (\Exception $e) {
            Log::warning('Failed to send in-app notification: ' . $e->getMessage());
        }
    }

    /**
     * Send copy to developer.
     */
    private function sendCopyToDeveloper(array $mailData): void
    {
        try {
            $developerSubject = $this->isReminder 
                ? "Copy: Reminder sent for payment request #{$this->paymentRequest->invoice_number}"
                : "Copy: Payment request #{$this->paymentRequest->invoice_number} sent to Super Admin";
            
            $developerMailData = array_merge($mailData, [
                'subject' => $developerSubject,
                'is_copy' => true,
                'action_url' => route('developer.billing.super-admin-payments')
            ]);
            
            Mail::send('emails.developer.payment-request-copy', $developerMailData, function ($message) use ($developerSubject) {
                $message->to($this->developerSettings->developer_email)
                       ->subject($developerSubject)
                       ->from(
                           config('mail.from.address'),
                           config('mail.from.name')
                       );
            });
            
        } catch (\Exception $e) {
            Log::warning('Failed to send copy to developer: ' . $e->getMessage());
        }
    }

    /**
     * Get payment details for display.
     */
    private function getPaymentDetails(): array
    {
        $details = [
            'Bank Transfer' => [],
            'Mobile Money' => [],
            'Cash' => []
        ];
        
        // Get developer's payment details from settings
        $developerSettings = $this->developerSettings;
        
        switch ($this->paymentRequest->payment_method) {
            case 'bank_transfer':
                $details['Bank Transfer'] = [
                    'Bank Name' => $developerSettings->payment_bank_name ?? 'Not specified',
                    'Account Name' => $developerSettings->payment_account_name ?? 'Not specified',
                    'Account Number' => $developerSettings->payment_account_number ?? 'Not specified',
                    'Branch' => $developerSettings->payment_bank_branch ?? 'Not specified'
                ];
                break;
                
            case 'mobile_money':
                $details['Mobile Money'] = [
                    'Provider' => 'MTN/Telecel/AirtelTigo',
                    'Number' => $developerSettings->payment_mobile_number ?? 'Not specified',
                    'Name' => $developerSettings->payment_account_name ?? $developerSettings->developer_name
                ];
                break;
                
            case 'cash':
                $details['Cash'] = [
                    'Instructions' => 'Please contact developer for cash payment arrangements',
                    'Contact' => $developerSettings->developer_phone ?? $developerSettings->developer_email
                ];
                break;
        }
        
        return $details;
    }

    /**
     * Check if payment request is still valid.
     */
    private function isPaymentRequestValid(): bool
    {
        // Check if payment request exists and is in a valid state
        if (!$this->paymentRequest->exists) {
            return false;
        }
        
        // Only send notifications for pending or overdue requests
        if (!in_array($this->paymentRequest->status, ['pending', 'overdue'])) {
            return false;
        }
        
        // Check if super admin is still active
        if ($this->superAdmin->status !== 'active') {
            return false;
        }
        
        return true;
    }

    /**
     * Update payment request metadata.
     */
    private function updatePaymentRequestMetadata(): void
    {
        try {
            $metadata = $this->paymentRequest->metadata ?? [];
            $notificationType = $this->isReminder ? 'reminder' : 'initial';
            
            $newMetadata = array_merge($metadata, [
                'last_notification_sent' => now()->toISOString(),
                'last_notification_type' => $notificationType,
                'last_notification_to' => $this->superAdmin->email,
                'notification_status' => 'sent',
                'total_notifications_sent' => ($metadata['total_notifications_sent'] ?? 0) + 1,
                'last_notification_details' => [
                    'method' => 'email',
                    'is_reminder' => $this->isReminder,
                    'sent_at' => now()->toISOString()
                ]
            ]);
            
            // If reminder, add specific reminder data
            if ($this->isReminder) {
                $newMetadata['last_reminder_sent'] = now()->toISOString();
                $newMetadata['total_reminders_sent'] = ($metadata['total_reminders_sent'] ?? 0) + 1;
                $newMetadata['reminder_history'][] = [
                    'sent_at' => now()->toISOString(),
                    'sent_to' => $this->superAdmin->email,
                    'sent_by' => 'system'
                ];
            }
            
            $this->paymentRequest->update([
                'metadata' => $newMetadata,
                'last_notification_sent_at' => now(),
                'notification_count' => ($this->paymentRequest->notification_count ?? 0) + 1
            ]);
            
            if ($this->isReminder) {
                $this->paymentRequest->update([
                    'reminder_sent_at' => now()
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to update payment request metadata: ' . $e->getMessage());
        }
    }

    /**
     * Record reminder in database.
     */
    private function recordReminder(): void
    {
        try {
            \DB::table('payment_reminders')->insert([
                'admin_billing_record_id' => $this->paymentRequest->id,
                'sent_to' => $this->superAdmin->email,
                'sent_to_id' => $this->superAdmin->id,
                'sent_by' => 'system',
                'sent_by_id' => null, // System initiated
                'reminder_type' => 'super_admin',
                'reminder_method' => 'email',
                'is_reminder' => true,
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to record reminder: ' . $e->getMessage());
        }
    }

    /**
     * Mark payment request as failed.
     */
    private function markPaymentRequestAsFailed(string $errorMessage): void
    {
        try {
            $metadata = $this->paymentRequest->metadata ?? [];
            
            $this->paymentRequest->update([
                'metadata' => array_merge($metadata, [
                    'last_notification_attempt' => now()->toISOString(),
                    'notification_status' => 'failed',
                    'notification_error' => $errorMessage,
                    'failed_attempts' => ($metadata['failed_attempts'] ?? 0) + 1,
                    'last_failed_at' => now()->toISOString()
                ])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark payment request as failed: ' . $e->getMessage());
        }
    }

    /**
     * Get the tags for the job.
     */
    public function tags(): array
    {
        return [
            'payment-request',
            'super-admin',
            'notification',
            'invoice-' . $this->paymentRequest->invoice_number,
            'admin-id-' . $this->superAdmin->id,
            'developer-id-' . $this->developerSettings->id,
            'amount-' . $this->paymentRequest->amount,
            'currency-' . $this->paymentRequest->currency,
            $this->isReminder ? 'reminder' : 'initial'
        ];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 120, 300, 600]; // 1 min, 2 min, 5 min, 10 min
    }

    /**
     * The number of times the job may be attempted.
     */
    public function tries(): int
    {
        return 3;
    }

    /**
     * Get the job's unique ID for deduplication.
     */
    public function uniqueId(): string
    {
        return 'payment_request_' . $this->paymentRequest->id . '_' . $this->superAdmin->id . '_' . ($this->isReminder ? 'reminder' : 'initial');
    }

    /**
     * The unique lock for the job.
     */
    public function uniqueFor(): int
    {
        return 300; // 5 minutes
    }
}