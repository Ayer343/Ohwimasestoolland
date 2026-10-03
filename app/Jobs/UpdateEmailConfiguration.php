<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\EnvironmentConfigService;
use Illuminate\Support\Facades\Log;

class UpdateEmailConfiguration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;
    protected $password;

    public function __construct($email, $password = null)
    {
        $this->email = $email;
        $this->password = $password;
        
        // Optional: Configure job settings
        $this->onQueue('env-updates');
        $this->delay = now()->addSeconds(10); // Delay to ensure request completes
    }

    public function handle(EnvironmentConfigService $environmentService)
    {
        try {
            Log::info('Processing queued email configuration update', ['email' => $this->email]);
            
            $result = $environmentService->updateEmailConfiguration($this->email, $this->password);
            
            if ($result['success']) {
                Log::info('Queued email configuration update completed successfully');
            } else {
                Log::error('Queued email configuration update failed', [
                    'email' => $this->email,
                    'error' => $result['message']
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Queue job failed for email configuration update', [
                'email' => $this->email,
                'error' => $e->getMessage()
            ]);
        }
    }
}