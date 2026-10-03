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

class TestSmsConnection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $provider;
    public $userId;
    public $timeout = 30;
    public $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(string $provider, ?int $userId = null)
    {
        $this->provider = $provider;
        $this->userId = $userId;
        
        $this->onQueue('sms-tests');
    }

    /**
     * Execute the job.
     */
    public function handle(SmsService $smsService): void
    {
        Log::info("Starting SMS connection test job", [
            'provider' => $this->provider,
            'user_id' => $this->userId
        ]);

        try {
            // Clear config cache before testing
            Artisan::call('config:clear');

            // Test the connection using SMS service
            $result = $smsService->testConnection($this->provider);

            Log::info("SMS connection test job completed", [
                'provider' => $this->provider,
                'success' => $result['success'],
                'user_id' => $this->userId
            ]);

            // Store result in cache or database for retrieval if needed
            cache()->put(
                "sms_test_result_{$this->provider}_{$this->userId}",
                $result,
                now()->addMinutes(10)
            );

        } catch (\Exception $e) {
            Log::error("SMS connection test job failed", [
                'provider' => $this->provider,
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);

            // Store failure result
            cache()->put(
                "sms_test_result_{$this->provider}_{$this->userId}",
                [
                    'success' => false,
                    'message' => 'Test failed: ' . $e->getMessage(),
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
        Log::error("SMS connection test job failed after all attempts", [
            'provider' => $this->provider,
            'user_id' => $this->userId,
            'error' => $exception->getMessage()
        ]);
    }
}