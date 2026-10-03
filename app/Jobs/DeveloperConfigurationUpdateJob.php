<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\DeveloperSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DeveloperConfigurationUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $developerSettings;
    public $userId;
    public $updatedData;
    public $section;

    /**
     * Create a new job instance.
     */
    public function __construct(DeveloperSetting $developerSettings, int $userId, array $updatedData = [], string $section = 'general')
    {
        $this->developerSettings = $developerSettings;
        $this->userId = $userId;
        $this->updatedData = $updatedData;
        $this->section = $section;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Processing developer configuration update job', [
                'developer_id' => $this->developerSettings->id,
                'developer_email' => $this->developerSettings->developer_email,
                'user_id' => $this->userId,
                'section' => $this->section,
                'updated_fields' => array_keys($this->updatedData),
            ]);

            // Handle section-specific logic
            $this->handleSectionUpdate();

            // Clear configuration cache
            $this->clearConfigurationCache();

            Log::info('Developer configuration update job completed successfully', [
                'developer_id' => $this->developerSettings->id,
                'developer_email' => $this->developerSettings->developer_email,
                'section' => $this->section,
            ]);

        } catch (\Exception $e) {
            Log::error('Developer configuration update job failed', [
                'developer_id' => $this->developerSettings->id,
                'developer_email' => $this->developerSettings->developer_email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Handle section-specific update
     */
    protected function handleSectionUpdate(): void
    {
        switch ($this->section) {
            case 'general':
                $this->handleGeneralUpdate();
                break;
                
            case 'billing':
                $this->handleBillingUpdate();
                break;
                
            case 'email':
                $this->handleEmailUpdate();
                break;
                
            case 'api':
                $this->handleApiUpdate();
                break;
                
            case 'security':
                $this->handleSecurityUpdate();
                break;
                
            case 'maintenance':
                $this->handleMaintenanceUpdate();
                break;
                
            case 'performance':
                $this->handlePerformanceUpdate();
                break;
                
            case 'monitoring':
                $this->handleMonitoringUpdate();
                break;
                
            case 'backup':
                $this->handleBackupUpdate();
                break;
                
            case 'analytics':
                $this->handleAnalyticsUpdate();
                break;
        }
    }

    /**
     * Handle general settings update
     */
    protected function handleGeneralUpdate(): void
    {
        // Check if developer email changed
        if (isset($this->updatedData['developer_email']) && 
            $this->updatedData['developer_email'] !== $this->developerSettings->developer_email) {
            
            Log::info('Developer email changed', [
                'old_email' => $this->developerSettings->developer_email,
                'new_email' => $this->updatedData['developer_email'],
            ]);
        }
    }

    /**
     * Handle billing settings update
     */
    protected function handleBillingUpdate(): void
    {
        // Check if billing status changed to active
        if (isset($this->updatedData['billing_status']) && 
            $this->updatedData['billing_status'] === 'active' &&
            $this->developerSettings->billing_status !== 'active') {
            
            Log::info('Developer billing activated', [
                'developer_id' => $this->developerSettings->id,
                'monthly_amount' => $this->developerSettings->monthly_billing_amount,
                'currency' => $this->developerSettings->billing_currency,
            ]);
        }
    }

    /**
     * Handle email settings update
     */
    protected function handleEmailUpdate(): void
    {
        if (isset($this->updatedData['developer_smtp_host']) || 
            isset($this->updatedData['developer_smtp_username'])) {
            
            Log::info('Email configuration updated', [
                'developer_id' => $this->developerSettings->id,
                'smtp_host' => $this->updatedData['developer_smtp_host'] ?? 'unchanged',
            ]);
        }
    }

    /**
     * Handle API settings update
     */
    protected function handleApiUpdate(): void
    {
        if (isset($this->updatedData['api_enabled'])) {
            $action = $this->updatedData['api_enabled'] ? 'enabled' : 'disabled';
            
            Log::info("Developer API {$action}", [
                'developer_id' => $this->developerSettings->id,
                'api_base_url' => $this->developerSettings->api_base_url,
            ]);
        }
    }

    /**
     * Handle security settings update
     */
    protected function handleSecurityUpdate(): void
    {
        $securityFields = ['enable_two_factor', 'session_timeout', 'max_login_attempts', 'password_expiry_days'];
        $changedSecurityFields = array_intersect($securityFields, array_keys($this->updatedData));
        
        if (!empty($changedSecurityFields)) {
            Log::info('Security settings updated', [
                'developer_id' => $this->developerSettings->id,
                'changed_fields' => $changedSecurityFields,
            ]);
        }
    }

    /**
     * Handle maintenance mode update
     */
    protected function handleMaintenanceUpdate(): void
    {
        if (isset($this->updatedData['maintenance_mode'])) {
            $action = $this->updatedData['maintenance_mode'] ? 'enabled' : 'disabled';
            
            Log::info("Maintenance mode {$action}", [
                'developer_id' => $this->developerSettings->id,
                'message' => $this->updatedData['maintenance_message'] ?? null,
            ]);
        }
    }

    /**
     * Handle performance settings update
     */
    protected function handlePerformanceUpdate(): void
    {
        if (isset($this->updatedData['cache_duration'])) {
            Log::info('Cache duration updated', [
                'developer_id' => $this->developerSettings->id,
                'old_duration' => $this->developerSettings->cache_duration,
                'new_duration' => $this->updatedData['cache_duration'],
            ]);
        }
    }

    /**
     * Handle monitoring settings update
     */
    protected function handleMonitoringUpdate(): void
    {
        $monitoringFields = ['enable_system_monitoring', 'enable_error_tracking', 'enable_performance_monitoring'];
        $changedMonitoringFields = array_intersect($monitoringFields, array_keys($this->updatedData));
        
        if (!empty($changedMonitoringFields)) {
            Log::info('Monitoring settings updated', [
                'developer_id' => $this->developerSettings->id,
                'changed_fields' => $changedMonitoringFields,
            ]);
        }
    }

    /**
     * Handle backup settings update
     */
    protected function handleBackupUpdate(): void
    {
        if (isset($this->updatedData['enable_auto_backup'])) {
            $action = $this->updatedData['enable_auto_backup'] ? 'enabled' : 'disabled';
            
            Log::info("Auto backup {$action}", [
                'developer_id' => $this->developerSettings->id,
                'frequency' => $this->updatedData['backup_frequency'] ?? 'unchanged',
            ]);
        }
    }

    /**
     * Handle analytics settings update
     */
    protected function handleAnalyticsUpdate(): void
    {
        if (isset($this->updatedData['enable_analytics'])) {
            $action = $this->updatedData['enable_analytics'] ? 'enabled' : 'disabled';
            
            Log::info("Analytics {$action}", [
                'developer_id' => $this->developerSettings->id,
                'provider' => $this->updatedData['analytics_provider'] ?? 'unchanged',
            ]);
        }
    }

    /**
     * Clear configuration cache
     */
    protected function clearConfigurationCache(): void
    {
        // Clear developer settings cache
        Cache::forget('developer_settings');
        Cache::forget('developer_settings_email_' . md5($this->developerSettings->developer_email));
        
        // Clear developer-specific cache keys
        $cacheKeys = [
            'developer_' . $this->developerSettings->id . '_email_config',
            'developer_' . $this->developerSettings->id . '_api_config',
            'developer_' . $this->developerSettings->id . '_billing_config',
            'developer_' . $this->developerSettings->id . '_security_config',
            'developer_' . $this->developerSettings->id . '_maintenance_config',
            'developer_' . $this->developerSettings->id . '_performance_config',
            'developer_' . $this->developerSettings->id . '_monitoring_config',
            'developer_' . $this->developerSettings->id . '_backup_config',
            'developer_' . $this->developerSettings->id . '_analytics_config',
            'developer_' . $this->developerSettings->id . '_all_config',
        ];
        
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
        
        Log::debug('Configuration cache cleared after job execution', [
            'developer_id' => $this->developerSettings->id,
        ]);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('DeveloperConfigurationUpdateJob failed', [
            'developer_id' => $this->developerSettings->id,
            'developer_email' => $this->developerSettings->developer_email,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}