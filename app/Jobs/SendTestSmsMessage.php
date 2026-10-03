<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Services\SmsService;

class SendTestSmsMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $provider;
    public $phoneNumber;
    public $message;
    public $userId;
    public $timeout = 60;
    public $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(string $provider, string $phoneNumber, string $message, ?int $userId = null)
    {
        $this->provider = $provider;
        $this->phoneNumber = $phoneNumber;
        $this->message = $message;
        $this->userId = $userId;
        
        $this->onQueue('sms-messages');
    }

    /**
     * Execute the job.
     */
    public function handle(SmsService $smsService): void
    {
        Log::info("Starting test SMS sending job", [
            'provider' => $this->provider,
            'phone_number' => $this->phoneNumber,
            'user_id' => $this->userId
        ]);

        try {
            // Clear config cache before sending
            Artisan::call('config:clear');

            // Send test message using SMS service
            $result = $smsService->sendTestMessage(
                $this->provider, 
                $this->phoneNumber, 
                $this->message
            );

            Log::info("Test SMS sending job completed", [
                'provider' => $this->provider,
                'phone_number' => $this->phoneNumber,
                'success' => $result['success'],
                'user_id' => $this->userId
            ]);

            // Store result in cache for retrieval
            cache()->put(
                "sms_send_result_{$this->provider}_{$this->userId}",
                $result,
                now()->addMinutes(10)
            );

        } catch (\Exception $e) {
            Log::error("Test SMS sending job failed", [
                'provider' => $this->provider,
                'phone_number' => $this->phoneNumber,
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);

            // Store failure result
            cache()->put(
                "sms_send_result_{$this->provider}_{$this->userId}",
                [
                    'success' => false,
                    'message' => 'SMS sending failed: ' . $e->getMessage(),
                    'error_code' => 'JOB_FAILED'
                ],
                now()->addMinutes(10)
            );

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Test SMS sending job failed after all attempts", [
            'provider' => $this->provider,
            'phone_number' => $this->phoneNumber,
            'user_id' => $this->userId,
            'error' => $exception->getMessage()
        ]);
    }
}