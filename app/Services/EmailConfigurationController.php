<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\DeveloperEmailConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class EmailConfigurationController extends Controller
{
    protected $developerEmailService;
    
    public function __construct(DeveloperEmailConfigurationService $developerEmailService)
    {
        $this->developerEmailService = $developerEmailService;
        $this->middleware('developer'); // Custom developer middleware
    }
    
    /**
     * Get comprehensive developer email diagnostics
     */
    public function getDeveloperDiagnostics()
    {
        try {
            $diagnostics = $this->developerEmailService->getDeveloperDiagnostics();
            
            return response()->json([
                'success' => true,
                'timestamp' => now()->toDateTimeString(),
                'developer_id' => auth()->id(),
                'diagnostics' => $diagnostics
            ]);
            
        } catch (\Exception $e) {
            Log::error('Developer diagnostics failed', [
                'developer' => auth()->user()->email,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Diagnostics failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Test custom SMTP configuration
     */
    public function testCustomSmtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|email',
            'password' => 'required|string',
            'encryption' => 'nullable|string|in:tls,ssl',
            'from_address' => 'nullable|email',
            'from_name' => 'nullable|string|max:255',
            'test_email' => 'required|email'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $config = $validator->validated();
        $testEmail = $config['test_email'];
        unset($config['test_email']);
        
        $result = $this->developerEmailService->testDeveloperSmtpConnection($config, $testEmail);
        
        return response()->json($result);
    }
    
    /**
     * Save developer SMTP configuration
     */
    public function saveSmtpConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|email',
            'password' => 'required|string',
            'encryption' => 'nullable|string|in:tls,ssl',
            'timeout' => 'nullable|integer|min:1|max:60',
            'local_domain' => 'nullable|string|max:255'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $result = $this->developerEmailService->saveDeveloperSmtpConfig($validator->validated());
        
        return response()->json($result);
    }
    
    /**
     * Test multiple SMTP configurations
     */
    public function testMultipleSmtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'configurations' => 'required|array|min:1|max:5',
            'configurations.*.name' => 'required|string|max:100',
            'configurations.*.host' => 'required|string|max:255',
            'configurations.*.port' => 'required|integer|min:1|max:65535',
            'configurations.*.username' => 'required|email',
            'configurations.*.password' => 'required|string',
            'configurations.*.encryption' => 'nullable|string|in:tls,ssl',
            'test_email' => 'required|email'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $configurations = [];
        foreach ($request->configurations as $config) {
            $configurations[$config['name']] = $config;
        }
        
        $result = $this->developerEmailService->testMultipleSmtpConfigurations(
            $configurations,
            $request->test_email
        );
        
        return response()->json($result);
    }
    
    /**
     * Bulk email testing
     */
    public function bulkEmailTest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recipients' => 'required|array|min:1|max:20',
            'recipients.*' => 'email',
            'templates' => 'required|array|min:1',
            'templates.*' => 'string|in:welcome,invitation,notification,test,error'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $result = $this->developerEmailService->bulkDeveloperEmailTest(
            $request->recipients,
            $request->templates
        );
        
        return response()->json($result);
    }
    
    /**
     * Reset developer email configuration
     */
    public function resetConfiguration()
    {
        $result = $this->developerEmailService->resetDeveloperEmailConfig();
        
        return response()->json($result);
    }
    
    /**
     * Export developer configuration
     */
    public function exportConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'include_secrets' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $result = $this->developerEmailService->exportDeveloperConfig(
            $request->boolean('include_secrets', false)
        );
        
        // Return as downloadable JSON if requested
        if ($request->boolean('download', false)) {
            $filename = 'developer_email_config_' . date('Y-m-d_H-i-s') . '.json';
            
            return response()->streamDownload(function () use ($result) {
                echo json_encode($result, JSON_PRETTY_PRINT);
            }, $filename, ['Content-Type' => 'application/json']);
        }
        
        return response()->json($result);
    }
    
    /**
     * Import developer configuration
     */
    public function importConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'configuration' => 'required|json'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $config = json_decode($request->configuration, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid JSON configuration'
            ], 422);
        }
        
        $result = $this->developerEmailService->importDeveloperConfig($config);
        
        return response()->json($result);
    }
    
    /**
     * Get developer email logs
     */
    public function getEmailLogs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'days' => 'integer|min:1|max:90',
            'level' => 'in:error,warning,info,debug,all',
            'search' => 'nullable|string|max:100'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $days = $request->days ?? 7;
        $logFile = storage_path('logs/developer-email.log');
        
        if (!file_exists($logFile)) {
            return response()->json([
                'success' => false,
                'message' => 'Developer email log file not found'
            ], 404);
        }
        
        // Read and filter logs
        $logs = [];
        $content = file_get_contents($logFile);
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            // Parse JSON log entries (assuming Laravel structured logging)
            $logEntry = json_decode($line, true);
            
            if ($logEntry) {
                $logDate = \Carbon\Carbon::parse($logEntry['datetime'] ?? now());
                
                // Filter by date
                if ($logDate->lt(now()->subDays($days))) {
                    continue;
                }
                
                // Filter by level
                $level = strtolower($logEntry['level_name'] ?? 'info');
                $requestLevel = $request->level ?? 'all';
                if ($requestLevel !== 'all' && $level !== $requestLevel) {
                    continue;
                }
                
                // Filter by search
                $search = $request->search ?? '';
                if ($search && stripos($line, $search) === false) {
                    continue;
                }
                
                $logs[] = [
                    'timestamp' => $logEntry['datetime'] ?? now()->toDateTimeString(),
                    'level' => $logEntry['level_name'] ?? 'INFO',
                    'message' => $logEntry['message'] ?? $line,
                    'context' => $logEntry['context'] ?? [],
                    'developer' => $logEntry['context']['developer'] ?? 'unknown'
                ];
            }
        }
        
        // Reverse to show newest first
        $logs = array_reverse($logs);
        
        return response()->json([
            'success' => true,
            'total_logs' => count($logs),
            'logs' => array_slice($logs, 0, 100) // Limit to 100 entries
        ]);
    }
    
    /**
     * Clear developer email logs
     */
    public function clearEmailLogs()
    {
        $logFile = storage_path('logs/developer-email.log');
        
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
            
            Log::channel('developer')->info('Developer email logs cleared', [
                'developer' => auth()->user()->email,
                'timestamp' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Developer email logs cleared successfully'
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Developer email log file not found'
        ], 404);
    }
}