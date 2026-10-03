<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Services\SmsService;

class BulkSmsOperations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $operation;
    public $providers;
    public $userId;
    public $timeout = 120;
    public $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(string $operation, array $providers, ?int $userId = null)
    {
        $this->operation = $operation;
        $this->providers = $providers;
        $this->userId = $userId;
        
        $this->onQueue('sms-bulk-operations');
    }

    /**
     * Execute the job.
     */
    public function handle(SmsService $smsService): void
    {
        Log::info("Starting bulk SMS operations job", [
            'operation' => $this->operation,
            'providers' => $this->providers,
            'user_id' => $this->userId
        ]);

        $results = [];

        try {
            foreach ($this->providers as $provider) {
                $result = $this->executeOperation($provider, $smsService);
                $results[$provider] = $result;
            }

            Log::info("Bulk SMS operations job completed", [
                'operation' => $this->operation,
                'total_providers' => count($this->providers),
                'successful_operations' => count(array_filter($results, fn($r) => $r['success'])),
                'user_id' => $this->userId
            ]);

            // Store results in cache
            cache()->put(
                "bulk_sms_operations_result_{$this->userId}",
                [
                    'operation' => $this->operation,
                    'results' => $results,
                    'completed_at' => now()->toISOString()
                ],
                now()->addMinutes(15)
            );

        } catch (\Exception $e) {
            Log::error("Bulk SMS operations job failed", [
                'operation' => $this->operation,
                'providers' => $this->providers,
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Execute specific operation for a provider
     */
    protected function executeOperation(string $provider, SmsService $smsService): array
    {
        Artisan::call('config:clear');

        switch ($this->operation) {
            case 'test_all':
                return $smsService->testConnection($provider);
                
            case 'enable_all':
                $result = $smsService->toggleProvider($provider, true);
                return [
                    'success' => $result['success'],
                    'message' => $result['message'] ?? 'Enabled',
                    'operation' => 'enable'
                ];
                
            case 'disable_all':
                $result = $smsService->toggleProvider($provider, false);
                return [
                    'success' => $result['success'],
                    'message' => $result['message'] ?? 'Disabled',
                    'operation' => 'disable'
                ];
                
            default:
                return [
                    'success' => false,
                    'message' => 'Unknown operation: ' . $this->operation,
                    'operation' => $this->operation
                ];
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Bulk SMS operations job failed after all attempts", [
            'operation' => $this->operation,
            'providers' => $this->providers,
            'user_id' => $this->userId,
            'error' => $exception->getMessage()
        ]);
    }
}