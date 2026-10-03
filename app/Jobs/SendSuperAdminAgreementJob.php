<?php

namespace App\Jobs;

use App\Models\AdminBillingRecord;
use App\Models\User;
use App\Notifications\SuperAdminAgreementNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendSuperAdminAgreementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = [30, 60, 120];

    protected $agreement;
    protected $notificationType;
    protected $message;

    /**
     * Create a new job instance.
     */
    public function __construct(AdminBillingRecord $agreement, $notificationType = 'agreement_created', $message = '')
    {
        $this->agreement = $agreement;
        $this->notificationType = $notificationType;
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            Log::info('SendSuperAdminAgreementJob started', [
                'agreement_id' => $this->agreement->id,
                'notification_type' => $this->notificationType,
                'super_admin_id' => $this->agreement->super_admin_id
            ]);

            // Load super admin with agreement
            $agreement = AdminBillingRecord::with(['superAdmin', 'developerSetting'])->find($this->agreement->id);
            
            if (!$agreement || !$agreement->superAdmin) {
                Log::error('SendSuperAdminAgreementJob: Agreement or Super Admin not found', [
                    'agreement_id' => $this->agreement->id
                ]);
                return;
            }

            $superAdmin = $agreement->superAdmin;
            $developerSettings = $agreement->developerSetting;

            // Prepare notification data
            $notificationData = [
                'agreement_id' => $agreement->id,
                'agreement_number' => $agreement->agreement_number,
                'amount' => $agreement->amount,
                'currency' => $agreement->currency,
                'description' => $agreement->description,
                'start_date' => $agreement->start_date,
                'billing_frequency' => $agreement->billing_frequency,
                'payment_method' => $agreement->payment_method,
                'notification_type' => $this->notificationType,
                'message' => $this->message,
                'developer_name' => $developerSettings->developer_name ?? 'Developer',
                'developer_email' => $developerSettings->developer_email,
                'developer_phone' => $developerSettings->developer_phone,
            ];

            // Send notification (this will create the database record via toDatabase method)
            // REMOVED the duplicate notifications()->create() call
            $superAdmin->notify(new SuperAdminAgreementNotification($notificationData));

            // Update agreement if it's a signature request
            if ($this->notificationType === 'signature_request') {
                $agreement->update([
                    'signing_invitation_sent_at' => now(),
                    'signing_invitation_sent_by' => auth()->id() ?? $developerSettings->developer_id,
                ]);
            }

            Log::info('SendSuperAdminAgreementJob completed successfully', [
                'agreement_id' => $this->agreement->id,
                'super_admin_email' => $superAdmin->email,
                'notification_type' => $this->notificationType
            ]);

        } catch (\Exception $e) {
            Log::error('SendSuperAdminAgreementJob failed: ' . $e->getMessage(), [
                'agreement_id' => $this->agreement->id,
                'exception' => $e
            ]);
            
            // Retry the job
            if ($this->attempts() < $this->tries) {
                $this->release(60);
            } else {
                Log::critical('SendSuperAdminAgreementJob failed after maximum retries', [
                    'agreement_id' => $this->agreement->id
                ]);
            }
        }
    }

    /**
     * The job failed to process.
     */
    public function failed(\Throwable $exception)
    {
        Log::critical('SendSuperAdminAgreementJob failed permanently', [
            'agreement_id' => $this->agreement->id,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}