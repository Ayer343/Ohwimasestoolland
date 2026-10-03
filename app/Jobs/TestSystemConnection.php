<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use App\Services\EnvironmentConfigService;
use App\Services\WhatsAppService;
use App\Services\PaymentService;

class TestSystemConnection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $testType;
    public $userId;
    public $tries = 2;
    public $timeout = 120;

    protected $environmentService;
    protected $whatsappService;
    protected $paymentService;

    /**
     * Create a new job instance.
     */
    public function __construct(string $testType, $userId)
    {
        $this->testType = $testType;
        $this->userId = $userId;
        
        $this->onQueue('system_tests');
    }

    /**
     * Execute the job.
     */
    public function handle(
        EnvironmentConfigService $environmentService,
        WhatsAppService $whatsappService,
        PaymentService $paymentService
    ): void {
        $this->environmentService = $environmentService;
        $this->whatsappService = $whatsappService;
        $this->paymentService = $paymentService;

        try {
            Log::info('Starting system connection test job', [
                'user_id' => $this->userId,
                'test_type' => $this->testType
            ]);

            Artisan::call('config:clear');

            $results = $this->runSystemConfigurationTest($this->testType);

            Log::info('System connection test job completed', [
                'user_id' => $this->userId,
                'test_type' => $this->testType,
                'success' => $results['success']
            ]);

        } catch (\Exception $e) {
            Log::error('System connection test job failed: ' . $e->getMessage(), [
                'user_id' => $this->userId,
                'test_type' => $this->testType
            ]);
            
            throw $e;
        }
    }

    /**
     * Run system configuration test
     */
    protected function runSystemConfigurationTest($testType): array
    {
        $results = [];
        
        try {
            switch ($testType) {
                case 'email':
                    $results = $this->environmentService->testEmailConfiguration();
                    break;
                    
                case 'whatsapp':
                    $results = $this->whatsappService->testConnection();
                    break;
                    
                case 'payments':
                    $results = $this->paymentService->testPaymentConfiguration();
                    break;
                    
                case 'all':
                    $emailResults = $this->environmentService->testEmailConfiguration();
                    $whatsappResults = $this->whatsappService->testConnection();
                    $paymentResults = $this->paymentService->testPaymentConfiguration();
                    
                    $results = [
                        'success' => $emailResults['success'] && $whatsappResults['success'] && $paymentResults['success'],
                        'message' => 'Comprehensive system test completed',
                        'details' => [
                            'email' => $emailResults,
                            'whatsapp' => $whatsappResults,
                            'payments' => $paymentResults
                        ],
                        'timestamp' => now()->toISOString()
                    ];
                    break;
                    
                default:
                    $results = [
                        'success' => false,
                        'message' => 'Unknown test type: ' . $testType,
                        'error_code' => 'UNKNOWN_TEST_TYPE'
                    ];
                    break;
            }
            
            return $results;
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Test execution failed: ' . $e->getMessage(),
                'error_code' => 'TEST_EXECUTION_ERROR',
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('System connection test job failed permanently', [
            'user_id' => $this->userId,
            'test_type' => $this->testType,
            'error' => $exception->getMessage()
        ]);
    }
}