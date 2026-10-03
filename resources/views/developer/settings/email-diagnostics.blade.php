{{-- developer/settings/email-diagnostics.blade.php --}}
@extends('layouts.dev')

@php
    $pageTitle = 'Developer Email Diagnostics & Troubleshooting';
    $successMessage = session('success');
    $errorMessage = session('error');
    $testResults = session('test_results');
    $emailTestResult = session('email_test_result');
    
    // Diagnostic data from controller
    $diagnostics = $diagnostics ?? [];
    $currentConfig = $currentConfig ?? [];
    $testResults = $testResults ?? [];
    $emailAudits = $emailAudits ?? collect();
    $testHistory = $testHistory ?? [];
    
    // Helper functions with safe array access
    function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
    }
    
    function formatTimestamp($timestamp) {
        if (!$timestamp || $timestamp === 'Unknown' || $timestamp === 'Never') {
            return 'Never';
        }
        
        try {
            return \Carbon\Carbon::parse($timestamp)->format('M d, Y H:i:s');
        } catch (\Exception $e) {
            return 'Invalid date';
        }
    }
    
    function getStatusBadge($success) {
        if ($success === true) {
            return '<span class="badge badge-success">✓ Success</span>';
        } elseif ($success === false) {
            return '<span class="badge badge-danger">✗ Failed</span>';
        } else {
            return '<span class="badge badge-warning">⏳ Pending</span>';
        }
    }
    
    function getConfigSourceBadge($source) {
        switch ($source) {
            case 'env':
                return '<span class="badge badge-info">ENV</span>';
            case 'database':
                return '<span class="badge badge-success">Database</span>';
            case 'cache':
                return '<span class="badge badge-warning">Cache</span>';
            case 'default':
                return '<span class="badge badge-secondary">Default</span>';
            default:
                return '<span class="badge badge-secondary">Unknown</span>';
        }
    }
    
    // Safe access helper with null coalescing
    function safeArrayGet($array, $key, $default = null) {
        if (is_array($array)) {
            $keys = explode('.', $key);
            $current = $array;
            
            foreach ($keys as $k) {
                if (!is_array($current) || !array_key_exists($k, $current)) {
                    return $default;
                }
                $current = $current[$k];
            }
            
            return $current ?? $default;
        }
        
        return $default;
    }
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <!-- Icon -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2 header-icon">
                        <i class="fas fa-stethoscope text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center header-title">
                        <i class="fas fa-stethoscope mr-2"></i> 
                        Developer Email Diagnostics
                    </h2>
                    <div class="text-sm flex items-center mt-1 header-subtitle">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Comprehensive email configuration analysis and troubleshooting</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-envelope mr-1"></i>
                        <span class="font-medium">Developer Email System</span>
                    </div>
                </div>
            </div>
            <div class="header-info">
                <a href="{{ route('developer.settings.index', ['section' => 'email']) }}" class="btn-secondary-small">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Email Settings
                </a>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if($successMessage)
    <div class="alert alert-success" role="alert">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span class="font-bold">Success!</span>
            <span class="ml-2">{{ $successMessage }}</span>
        </div>
        <button type="button" class="alert-close-btn" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if($errorMessage)
    <div class="alert alert-danger" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span class="font-bold">Error!</span>
            <span class="ml-2">{{ $errorMessage }}</span>
        </div>
        <button type="button" class="alert-close-btn" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Quick Actions -->
    <div class="card">
        <div class="p-6">
            <h3 class="section-title">
                <i class="fas fa-bolt mr-2"></i> Quick Diagnostic Actions
            </h3>
            
            <div class="flex flex-wrap gap-3">
                <button onclick="runComprehensiveTest()" class="btn btn-primary">
                    <i class="fas fa-vial mr-2"></i> Run Comprehensive Test
                </button>
                
                <button onclick="validateConfiguration()" class="btn btn-info">
                    <i class="fas fa-check-circle mr-2"></i> Validate Configuration
                </button>
                
                <button onclick="showFixIssuesModal()" class="btn btn-warning">
                    <i class="fas fa-wrench mr-2"></i> Fix Common Issues
                </button>
                
                <button onclick="showTestEmailModal()" class="btn btn-secondary">
                    <i class="fas fa-paper-plane mr-2"></i> Test Email Sending
                </button>
                
                <a href="{{ route('developer.email.config.download') }}" class="btn btn-success">
                    <i class="fas fa-download mr-2"></i> Download Config Backup
                </a>
                
                <button onclick="showCompareModal()" class="btn btn-purple">
                    <i class="fas fa-exchange-alt mr-2"></i> Compare Configurations
                </button>
            </div>
            
            <div class="mt-4 flex items-center text-sm">
                <i class="fas fa-info-circle mr-2 text-info"></i>
                <span class="text-secondary">
                    These diagnostics only affect the <strong>developer email system</strong>. Super admin emails use a separate configuration.
                </span>
            </div>
        </div>
    </div>

    <!-- Main Diagnostics Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Configuration Status -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Current Configuration Card -->
            <div class="card">
                <div class="p-6">
                    <h3 class="section-title">
                        <i class="fas fa-cog mr-2"></i> Current Configuration
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="config-summary-item">
                            <div class="flex items-center justify-between mb-2">
                                <span class="config-summary-label">Configuration Source</span>
                                <span class="config-summary-value">
                                    {!! getConfigSourceBadge(safeArrayGet($currentConfig, 'source')) !!}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="config-summary-label">Last Updated</span>
                                <span class="config-summary-value">
                                    {{ formatTimestamp(safeArrayGet($currentConfig, 'updated_at')) }}
                                </span>
                            </div>
                        </div>
                        
                        <div class="config-summary-item">
                            <div class="flex items-center justify-between mb-2">
                                <span class="config-summary-label">Connection Type</span>
                                <span class="config-summary-value">
                                    {{ strtoupper(safeArrayGet($currentConfig, 'encryption', 'tls')) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="config-summary-label">Port</span>
                                <span class="config-summary-value">
                                    {{ safeArrayGet($currentConfig, 'port', 587) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Configuration Details -->
                    <div class="config-details">
                        <h4 class="section-subtitle">Configuration Details</h4>
                        
                        <div class="space-y-3">
                            <div class="config-detail-item">
                                <span class="config-detail-label">SMTP Host:</span>
                                <code class="config-detail-value">{{ safeArrayGet($currentConfig, 'host', 'Not set') }}</code>
                            </div>
                            
                            <div class="config-detail-item">
                                <span class="config-detail-label">SMTP Port:</span>
                                <code class="config-detail-value">{{ safeArrayGet($currentConfig, 'port', 'Not set') }}</code>
                            </div>
                            
                            <div class="config-detail-item">
                                <span class="config-detail-label">Username:</span>
                                <code class="config-detail-value">{{ safeArrayGet($currentConfig, 'username', 'Not set') }}</code>
                            </div>
                            
                            <div class="config-detail-item">
                                <span class="config-detail-label">Encryption:</span>
                                <code class="config-detail-value">{{ strtoupper(safeArrayGet($currentConfig, 'encryption', 'Not set')) }}</code>
                            </div>
                            
                            <div class="config-detail-item">
                                <span class="config-detail-label">From Address:</span>
                                <code class="config-detail-value">{{ safeArrayGet($currentConfig, 'from_address', 'Not set') }}</code>
                            </div>
                            
                            <div class="config-detail-item">
                                <span class="config-detail-label">From Name:</span>
                                <code class="config-detail-value">{{ safeArrayGet($currentConfig, 'from_name', 'Not set') }}</code>
                            </div>
                            
                            @if(safeArrayGet($currentConfig, 'local_domain'))
                            <div class="config-detail-item">
                                <span class="config-detail-label">Local Domain:</span>
                                <code class="config-detail-value">{{ safeArrayGet($currentConfig, 'local_domain') }}</code>
                            </div>
                            @endif
                            
                            @if(safeArrayGet($currentConfig, 'timeout'))
                            <div class="config-detail-item">
                                <span class="config-detail-label">Timeout:</span>
                                <code class="config-detail-value">{{ safeArrayGet($currentConfig, 'timeout') }} seconds</code>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Comprehensive Test Results -->
            <div class="card">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="section-title">
                            <i class="fas fa-vial mr-2"></i> Test Results
                        </h3>
                        <button onclick="runComprehensiveTest()" class="btn btn-primary btn-sm">
                            <i class="fas fa-redo mr-1"></i> Run Tests
                        </button>
                    </div>
                    
                    @if(!empty($testResults) && is_array($testResults))
                    <div class="space-y-4">
                        @foreach($testResults as $testName => $testResult)
                        @if(is_array($testResult))
                        <div class="test-result-item {{ (safeArrayGet($testResult, 'success', false)) ? 'test-success' : 'test-failed' }}">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center">
                                    <i class="fas fa-{{ (safeArrayGet($testResult, 'success', false)) ? 'check-circle text-success' : 'times-circle text-danger' }} mr-2"></i>
                                    <span class="test-result-label">{{ ucfirst(str_replace('_', ' ', $testName)) }}</span>
                                </div>
                                <div>
                                    {!! getStatusBadge(safeArrayGet($testResult, 'success')) !!}
                                </div>
                            </div>
                            
                            @if(safeArrayGet($testResult, 'message'))
                            <div class="test-result-message">
                                {{ safeArrayGet($testResult, 'message') }}
                            </div>
                            @endif
                            
                            @if(safeArrayGet($testResult, 'details'))
                            <div class="test-result-details">
                                <pre class="text-xs">{{ is_string(safeArrayGet($testResult, 'details')) ? safeArrayGet($testResult, 'details') : json_encode(safeArrayGet($testResult, 'details'), JSON_PRETTY_PRINT) }}</pre>
                            </div>
                            @endif
                            
                            @if(safeArrayGet($testResult, 'time_ms'))
                            <div class="test-result-meta">
                                <span class="test-result-time">{{ safeArrayGet($testResult, 'time_ms') }}ms</span>
                                @if(safeArrayGet($testResult, 'timestamp'))
                                <span class="test-result-timestamp">{{ formatTimestamp(safeArrayGet($testResult, 'timestamp')) }}</span>
                                @endif
                            </div>
                            @endif
                        </div>
                        @endif
                        @endforeach
                    </div>
                    @else
                    <div class="text-center py-8">
                        <i class="fas fa-vial text-4xl mb-4 text-secondary"></i>
                        <p class="text-secondary">No test results available</p>
                        <p class="text-sm mt-2">Run a comprehensive test to see results</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Right Column: System Status & Tools -->
        <div class="space-y-6">
            <!-- System Status Card -->
            <div class="card">
                <div class="p-6">
                    <h3 class="section-title">
                        <i class="fas fa-server mr-2"></i> System Status
                    </h3>
                    
                    @if(!empty($diagnostics['system']) && is_array($diagnostics['system']))
                    <div class="space-y-3">
                        <div class="status-item">
                            <span class="status-label">Laravel Version</span>
                            <span class="status-value">{{ safeArrayGet($diagnostics, 'system.laravel_version', app()->version()) }}</span>
                        </div>
                        
                        <div class="status-item">
                            <span class="status-label">PHP Version</span>
                            <span class="status-value">{{ safeArrayGet($diagnostics, 'php.version', phpversion()) }}</span>
                        </div>
                        
                        <div class="status-item">
                            <span class="status-label">Environment</span>
                            <span class="status-value">{{ safeArrayGet($diagnostics, 'system.environment', config('app.env', 'Unknown')) }}</span>
                        </div>
                        
                        <div class="status-item">
                            <span class="status-label">Debug Mode</span>
                            <span class="status-value">
                                @if(isset($diagnostics['system']['debug']))
                                    {{ $diagnostics['system']['debug'] ? 'Enabled' : 'Disabled' }}
                                @else
                                    {{ config('app.debug') ? 'Enabled' : 'Disabled' }}
                                @endif
                            </span>
                        </div>
                        
                        @if(isset($diagnostics['system']['maintenance_mode']))
                        <div class="status-item">
                            <span class="status-label">Maintenance Mode</span>
                            <span class="status-value {{ $diagnostics['system']['maintenance_mode'] ? 'text-danger' : 'text-success' }}">
                                {{ $diagnostics['system']['maintenance_mode'] ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        @endif
                        
                        @if(isset($diagnostics['system']['server']))
                        <div class="status-item">
                            <span class="status-label">Web Server</span>
                            <span class="status-value">{{ $diagnostics['system']['server'] }}</span>
                        </div>
                        @endif
                    </div>
                    @else
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle text-warning mb-2"></i>
                        <p class="text-secondary">System diagnostics not available</p>
                        <div class="mt-4 space-y-2">
                            <div class="status-item">
                                <span class="status-label">Laravel Version</span>
                                <span class="status-value">{{ app()->version() }}</span>
                            </div>
                            <div class="status-item">
                                <span class="status-label">PHP Version</span>
                                <span class="status-value">{{ phpversion() }}</span>
                            </div>
                            <div class="status-item">
                                <span class="status-label">Environment</span>
                                <span class="status-value">{{ config('app.env', 'Unknown') }}</span>
                            </div>
                            <div class="status-item">
                                <span class="status-label">Debug Mode</span>
                                <span class="status-value">{{ config('app.debug') ? 'Enabled' : 'Disabled' }}</span>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            
            <!-- File Permissions Card -->
            <div class="card">
                <div class="p-6">
                    <h3 class="section-title">
                        <i class="fas fa-file-alt mr-2"></i> File Permissions
                    </h3>
                    
                    @if(!empty($diagnostics['file_permissions']) && is_array($diagnostics['file_permissions']))
                    <div class="space-y-3">
                        @foreach($diagnostics['file_permissions'] as $file => $permission)
                        @if(is_array($permission))
                        <div class="permission-item">
                            <div class="flex items-center justify-between">
                                <span class="permission-file">{{ basename($file) }}</span>
                                <span class="permission-status {{ (safeArrayGet($permission, 'writable', false)) ? 'text-success' : 'text-danger' }}">
                                    <i class="fas fa-{{ (safeArrayGet($permission, 'writable', false)) ? 'check-circle' : 'times-circle' }} mr-1"></i>
                                    {{ (safeArrayGet($permission, 'writable', false)) ? 'Writable' : 'Not Writable' }}
                                </span>
                            </div>
                            <div class="permission-path">
                                <code class="text-xs">{{ $file }}</code>
                            </div>
                        </div>
                        @endif
                        @endforeach
                    </div>
                    @else
                    <div class="text-center py-4">
                        <p class="text-secondary">No file permission data available</p>
                        <div class="mt-4 space-y-2">
                            <div class="permission-item">
                                <div class="flex items-center justify-between">
                                    <span class="permission-file">.env</span>
                                    <span class="permission-status {{ is_writable(base_path('.env')) ? 'text-success' : 'text-danger' }}">
                                        <i class="fas fa-{{ is_writable(base_path('.env')) ? 'check-circle' : 'times-circle' }} mr-1"></i>
                                        {{ is_writable(base_path('.env')) ? 'Writable' : 'Not Writable' }}
                                    </span>
                                </div>
                                <div class="permission-path">
                                    <code class="text-xs">{{ base_path('.env') }}</code>
                                </div>
                            </div>
                            <div class="permission-item">
                                <div class="flex items-center justify-between">
                                    <span class="permission-file">storage/</span>
                                    <span class="permission-status {{ is_writable(storage_path()) ? 'text-success' : 'text-danger' }}">
                                        <i class="fas fa-{{ is_writable(storage_path()) ? 'check-circle' : 'times-circle' }} mr-1"></i>
                                        {{ is_writable(storage_path()) ? 'Writable' : 'Not Writable' }}
                                    </span>
                                </div>
                                <div class="permission-path">
                                    <code class="text-xs">{{ storage_path() }}</code>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            
            <!-- Connection Stats Card -->
            <div class="card">
                <div class="p-6">
                    <h3 class="section-title">
                        <i class="fas fa-chart-line mr-2"></i> Connection Statistics
                    </h3>
                    
                    <div class="space-y-3">
                        <div class="stat-item">
                            <span class="stat-label">Queue Driver</span>
                            <span class="stat-value">{{ safeArrayGet($diagnostics, 'queue.driver', config('queue.default', 'sync')) }}</span>
                        </div>
                        
                        <div class="stat-item">
                            <span class="stat-label">Cache Driver</span>
                            <span class="stat-value">{{ safeArrayGet($diagnostics, 'cache.driver', config('cache.default', 'file')) }}</span>
                        </div>
                        
                        <div class="stat-item">
                            <span class="stat-label">Session Driver</span>
                            <span class="stat-value">{{ safeArrayGet($diagnostics, 'session.driver', config('session.driver', 'file')) }}</span>
                        </div>
                        
                        @if(isset($diagnostics['redis']['available']))
                        <div class="stat-item">
                            <span class="stat-label">Redis Available</span>
                            <span class="stat-value {{ $diagnostics['redis']['available'] ? 'text-success' : 'text-danger' }}">
                                {{ $diagnostics['redis']['available'] ? 'Yes' : 'No' }}
                            </span>
                        </div>
                        @else
                        <div class="stat-item">
                            <span class="stat-label">Redis Available</span>
                            <span class="stat-value text-secondary">
                                Unknown
                            </span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Gmail Help Card -->
            @if(isset($diagnostics['gmail_help']) && $diagnostics['gmail_help'])
            <div class="card">
                <div class="p-6">
                    <h3 class="section-title">
                        <i class="fab fa-google mr-2"></i> Gmail Configuration Help
                    </h3>
                    
                    <div class="space-y-3">
                        <div class="alert alert-info">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle mt-1 mr-2"></i>
                                <div>
                                    <p class="font-bold">Gmail SMTP Settings</p>
                                    <ul class="text-sm mt-1 space-y-1">
                                        <li>• Host: <code>smtp.gmail.com</code></li>
                                        <li>• Port: <code>587</code> (TLS) or <code>465</code> (SSL)</li>
                                        <li>• Username: Your full Gmail address</li>
                                        <li>• Password: App Password (not your regular password)</li>
                                        <li>• Encryption: TLS (recommended)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="gmail-help-steps">
                            <p class="font-bold text-sm mb-2">To create an App Password:</p>
                            <ol class="text-xs space-y-1">
                                <li>1. Go to your Google Account settings</li>
                                <li>2. Navigate to "Security"</li>
                                <li>3. Enable 2-Step Verification if not already</li>
                                <li>4. Under "Signing in to Google", select "App passwords"</li>
                                <li>5. Generate a new app password for "Mail"</li>
                                <li>6. Use the 16-character password here</li>
                            </ol>
                        </div>
                        
                        <a href="https://support.google.com/accounts/answer/185833" 
                           target="_blank" 
                           class="btn btn-info btn-sm w-full">
                            <i class="fas fa-external-link-alt mr-1"></i> Official Google Guide
                        </a>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
    
    <!-- Email Audit Logs -->
    <div class="card">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="section-title">
                    <i class="fas fa-history mr-2"></i> Email Configuration Audit Log
                </h3>
                <button onclick="refreshAuditLogs()" class="btn btn-secondary btn-sm">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
            </div>
            
            @if($emailAudits->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="table-header">Timestamp</th>
                            <th class="table-header">Action</th>
                            <th class="table-header">Status</th>
                            <th class="table-header">IP Address</th>
                            <th class="table-header">Message</th>
                            <th class="table-header">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($emailAudits as $audit)
                        <tr>
                            <td class="table-cell table-cell-secondary">
                                {{ $audit->created_at->format('M d, Y H:i') }}
                            </td>
                            <td class="table-cell">
                                <span class="badge badge-{{ $audit->action == 'update' ? 'primary' : ($audit->action == 'test' ? 'info' : 'warning') }}">
                                    {{ ucfirst($audit->action) }}
                                </span>
                            </td>
                            <td class="table-cell">
                                <span class="badge badge-{{ $audit->status == 'success' ? 'success' : ($audit->status == 'failed' ? 'danger' : 'warning') }}">
                                    {{ ucfirst($audit->status) }}
                                </span>
                            </td>
                            <td class="table-cell table-cell-secondary">
                                <code class="text-xs">{{ $audit->ip_address }}</code>
                            </td>
                            <td class="table-cell">
                                <span class="truncate max-w-xs">{{ $audit->message }}</span>
                            </td>
                            <td class="table-cell">
                                <button onclick="showAuditDetails({{ $audit->id }})" class="btn btn-info btn-xs">
                                    <i class="fas fa-eye mr-1"></i> View
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4 flex justify-center">
                <a href="{{ route('developer.email.audit-logs') }}" class="btn btn-primary">
                    <i class="fas fa-list mr-2"></i> View All Audit Logs
                </a>
            </div>
            @else
            <div class="text-center py-8">
                <i class="fas fa-history text-4xl mb-4 text-secondary"></i>
                <p class="text-secondary">No audit logs available</p>
                <p class="text-sm mt-2">Configure your email settings to generate audit logs</p>
            </div>
            @endif
        </div>
    </div>
    
    <!-- Test History -->
    @if(!empty($testHistory) && is_array($testHistory))
    <div class="card">
        <div class="p-6">
            <h3 class="section-title">
                <i class="fas fa-chart-bar mr-2"></i> Test History (Last 30 Days)
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @php
                    $successCount = 0;
                    $failedCount = 0;
                    $totalTime = 0;
                    $testCount = count($testHistory);
                    
                    foreach($testHistory as $test) {
                        if (is_array($test)) {
                            if (safeArrayGet($test, 'success', false)) $successCount++;
                            else $failedCount++;
                            $totalTime += safeArrayGet($test, 'time_ms', 0);
                        }
                    }
                    
                    $successRate = $testCount > 0 ? round(($successCount / $testCount) * 100, 1) : 0;
                    $avgTime = $testCount > 0 ? round($totalTime / $testCount, 2) : 0;
                @endphp
                
                <div class="history-stat-card">
                    <div class="history-stat-icon bg-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <p class="history-stat-label">Success Rate</p>
                        <p class="history-stat-value">{{ $successRate }}%</p>
                    </div>
                </div>
                
                <div class="history-stat-card">
                    <div class="history-stat-icon bg-info">
                        <i class="fas fa-vial"></i>
                    </div>
                    <div>
                        <p class="history-stat-label">Total Tests</p>
                        <p class="history-stat-value">{{ $testCount }}</p>
                    </div>
                </div>
                
                <div class="history-stat-card">
                    <div class="history-stat-icon bg-warning">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div>
                        <p class="history-stat-label">Failed Tests</p>
                        <p class="history-stat-value">{{ $failedCount }}</p>
                    </div>
                </div>
                
                <div class="history-stat-card">
                    <div class="history-stat-icon bg-primary">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <p class="history-stat-label">Avg. Time</p>
                        <p class="history-stat-value">{{ $avgTime }}ms</p>
                    </div>
                </div>
            </div>
            
            <div class="mt-6">
                <h4 class="section-subtitle mb-3">Recent Tests</h4>
                <div class="space-y-3">
                    @foreach(array_slice($testHistory, 0, 5) as $test)
                    @if(is_array($test))
                    <div class="test-history-item">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="test-history-type">{{ safeArrayGet($test, 'type', 'Unknown') }}</span>
                                <span class="test-history-date">{{ formatTimestamp(safeArrayGet($test, 'timestamp')) }}</span>
                            </div>
                            <div>
                                @if(safeArrayGet($test, 'success', false))
                                <span class="test-history-success">
                                    <i class="fas fa-check-circle mr-1"></i> Success
                                </span>
                                @else
                                <span class="test-history-failed">
                                    <i class="fas fa-times-circle mr-1"></i> Failed
                                </span>
                                @endif
                            </div>
                        </div>
                        @if(safeArrayGet($test, 'message'))
                        <div class="test-history-message">
                            {{ safeArrayGet($test, 'message') }}
                        </div>
                        @endif
                        @if(safeArrayGet($test, 'time_ms'))
                        <div class="test-history-meta">
                            <span class="test-history-time">{{ safeArrayGet($test, 'time_ms') }}ms</span>
                            @if(safeArrayGet($test, 'email'))
                            <span class="test-history-email">{{ safeArrayGet($test, 'email') }}</span>
                            @endif
                        </div>
                        @endif
                    </div>
                    @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif
    
    <!-- Troubleshooting Tips -->
    <div class="card">
        <div class="p-6">
            <h3 class="section-title">
                <i class="fas fa-question-circle mr-2"></i> Troubleshooting Tips
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Connection Issues -->
                <div class="troubleshooting-tip">
                    <div class="tip-header">
                        <i class="fas fa-network-wired tip-icon"></i>
                        <h4 class="tip-title">Connection Issues</h4>
                    </div>
                    <ul class="tip-list">
                        <li>Check if port {{ safeArrayGet($currentConfig, 'port', 587) }} is open</li>
                        <li>Verify SMTP host name is correct</li>
                        <li>Try different encryption (TLS/SSL)</li>
                        <li>Check firewall settings</li>
                    </ul>
                </div>
                
                <!-- Authentication Issues -->
                <div class="troubleshooting-tip">
                    <div class="tip-header">
                        <i class="fas fa-key tip-icon"></i>
                        <h4 class="tip-title">Authentication Issues</h4>
                    </div>
                    <ul class="tip-list">
                        <li>Verify username/password</li>
                        <li>Check if app password is needed (Gmail)</li>
                        <li>Ensure account is not locked</li>
                        <li>Try resetting password</li>
                    </ul>
                </div>
                
                <!-- Sending Issues -->
                <div class="troubleshooting-tip">
                    <div class="tip-header">
                        <i class="fas fa-paper-plane tip-icon"></i>
                        <h4 class="tip-title">Sending Issues</h4>
                    </div>
                    <ul class="tip-list">
                        <li>Check "From" address is valid</li>
                        <li>Verify recipient addresses</li>
                        <li>Check email content for issues</li>
                        <li>Review server logs for errors</li>
                    </ul>
                </div>
                
                <!-- Performance Issues -->
                <div class="troubleshooting-tip">
                    <div class="tip-header">
                        <i class="fas fa-tachometer-alt tip-icon"></i>
                        <h4 class="tip-title">Performance Issues</h4>
                    </div>
                    <ul class="tip-list">
                        <li>Increase timeout settings</li>
                        <li>Check network latency</li>
                        <li>Verify DNS resolution</li>
                        <li>Monitor server resources</li>
                    </ul>
                </div>
                
                <!-- Security Issues -->
                <div class="troubleshooting-tip">
                    <div class="tip-header">
                        <i class="fas fa-shield-alt tip-icon"></i>
                        <h4 class="tip-title">Security Issues</h4>
                    </div>
                    <ul class="tip-list">
                        <li>Use TLS encryption</li>
                        <li>Enable certificate verification</li>
                        <li>Use strong passwords</li>
                        <li>Regularly rotate credentials</li>
                    </ul>
                </div>
                
                <!-- Common Errors -->
                <div class="troubleshooting-tip">
                    <div class="tip-header">
                        <i class="fas fa-exclamation-triangle tip-icon"></i>
                        <h4 class="tip-title">Common Error Codes</h4>
                    </div>
                    <ul class="tip-list">
                        <li><strong>535:</strong> Authentication failed</li>
                        <li><strong>550:</strong> Mailbox unavailable</li>
                        <li><strong>553:</strong> Invalid sender address</li>
                        <li><strong>554:</strong> Transaction failed</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test Email Modal -->
<div id="testEmailModal" class="modal hidden">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-paper-plane mr-2"></i> Test Email Sending
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('testEmailModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="testEmailForm" class="space-y-4">
                @csrf
                <div>
                    <label class="form-label">
                        Recipient Email Address *
                    </label>
                    <input type="email" 
                           id="test_recipient_email"
                           name="test_email" 
                           class="form-input w-full"
                           placeholder="test@example.com"
                           value="{{ auth()->user()->email }}"
                           required>
                </div>
                
                <div>
                    <label class="form-label">
                        Test Type *
                    </label>
                    <select name="test_type" class="form-select w-full" required>
                        <option value="connection">Connection Test Only</option>
                        <option value="send" selected>Send Test Email</option>
                        <option value="full">Full Test (Connection + Send)</option>
                        <option value="template">Test with Template</option>
                    </select>
                </div>
                
                <div id="templateSection" class="hidden">
                    <label class="form-label">
                        Email Template
                    </label>
                    <select name="template" class="form-select w-full">
                        <option value="welcome">Welcome Email</option>
                        <option value="invoice">Invoice Template</option>
                        <option value="notification">System Notification</option>
                        <option value="alert">Alert Template</option>
                    </select>
                </div>
                
                <div>
                    <label class="form-label">
                        Additional Notes
                    </label>
                    <textarea name="notes" 
                              class="form-textarea w-full" 
                              rows="3"
                              placeholder="Any additional notes for the test..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="btn btn-secondary"
                    onclick="closeModal('testEmailModal')">
                Cancel
            </button>
            <button type="button" 
                    onclick="submitTestEmail()"
                    class="btn btn-primary">
                <i class="fas fa-paper-plane mr-2"></i> Send Test Email
            </button>
        </div>
    </div>
</div>

<!-- Fix Issues Modal -->
<div id="fixIssuesModal" class="modal hidden">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-wrench mr-2"></i> Fix Common Issues
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('fixIssuesModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="space-y-4">
                <div class="alert alert-info">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle mt-1 mr-2"></i>
                        <div>
                            <p class="font-bold">Select issue to fix</p>
                            <p class="text-sm">The system will attempt to automatically fix the selected issue</p>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <div class="fix-option">
                        <input type="radio" 
                               id="fix_password" 
                               name="fix_option" 
                               value="fix_password_format"
                               class="form-radio">
                        <label for="fix_password" class="fix-option-label">
                            <span class="fix-option-title">Fix Password Format</span>
                            <span class="fix-option-description">
                                Fix password format issues (special characters, encoding)
                            </span>
                        </label>
                    </div>
                    
                    <div class="fix-option">
                        <input type="radio" 
                               id="sync_passwords" 
                               name="fix_option" 
                               value="sync_passwords"
                               class="form-radio">
                        <label for="sync_passwords" class="fix-option-label">
                            <span class="fix-option-title">Sync Passwords</span>
                            <span class="fix-option-description">
                                Sync .env password with database
                            </span>
                        </label>
                    </div>
                    
                    <div class="fix-option">
                        <input type="radio" 
                               id="clear_cache" 
                               name="fix_option" 
                               value="clear_cache"
                               class="form-radio">
                        <label for="clear_cache" class="fix-option-label">
                            <span class="fix-option-title">Clear Email Cache</span>
                            <span class="fix-option-description">
                                Clear cached email configuration
                            </span>
                        </label>
                    </div>
                    
                    <div class="fix-option">
                        <input type="radio" 
                               id="validate_config" 
                               name="fix_option" 
                               value="validate_config"
                               class="form-radio">
                        <label for="validate_config" class="fix-option-label">
                            <span class="fix-option-title">Validate & Repair Config</span>
                            <span class="fix-option-description">
                                Validate and repair configuration files
                            </span>
                        </label>
                    </div>
                </div>
                
                <!-- Password Input for Fix Password Option -->
                <div id="passwordInputSection" class="hidden space-y-2">
                    <label class="form-label">
                        New Password
                    </label>
                    <input type="password" 
                           id="new_password"
                           class="form-input w-full"
                           placeholder="Enter new password">
                    <p class="form-help text-xs">
                        Required for password format fixes
                    </p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="btn btn-secondary"
                    onclick="closeModal('fixIssuesModal')">
                Cancel
            </button>
            <button type="button" 
                    onclick="submitFixIssues()"
                    class="btn btn-warning">
                <i class="fas fa-wrench mr-2"></i> Fix Selected Issue
            </button>
        </div>
    </div>
</div>

<!-- Compare Configurations Modal -->
<div id="compareConfigModal" class="modal hidden">
    <div class="modal-container modal-content-lg">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-exchange-alt mr-2"></i> Compare Configurations
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('compareConfigModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <h4 class="section-subtitle">Current Configuration</h4>
                        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                            <pre id="currentConfigDisplay" class="text-xs overflow-x-auto"></pre>
                        </div>
                    </div>
                    <div>
                        <h4 class="section-subtitle">Last Working Configuration</h4>
                        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                            <pre id="lastConfigDisplay" class="text-xs overflow-x-auto">No backup available</pre>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-warning">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle mt-1 mr-2"></i>
                        <div>
                            <p class="font-bold">Configuration Differences</p>
                            <p class="text-sm" id="differencesMessage">No differences detected</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="btn btn-secondary"
                    onclick="closeModal('compareConfigModal')">
                Close
            </button>
            <button type="button" 
                    onclick="restoreConfiguration()"
                    class="btn btn-primary">
                <i class="fas fa-redo mr-2"></i> Restore Last Working Config
            </button>
        </div>
    </div>
</div>

<!-- Audit Details Modal -->
<div id="auditDetailsModal" class="modal hidden">
    <div class="modal-container modal-content-lg">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-eye mr-2"></i> Audit Log Details
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('auditDetailsModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="auditDetailsContent">
                <!-- Dynamically loaded content -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="btn btn-secondary"
                    onclick="closeModal('auditDetailsModal')">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="modal hidden">
    <div class="modal-backdrop"></div>
    <div class="modal-container modal-content-sm">
        <div class="modal-body">
            <div class="text-center py-8">
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin fa-3x"></i>
                </div>
                <p class="mt-4 font-medium" id="loadingMessage">Processing...</p>
                <p class="text-sm text-secondary mt-2" id="loadingSubmessage">Please wait</p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set current configuration for comparison modal
    const currentConfig = @json($currentConfig);
    document.getElementById('currentConfigDisplay').textContent = 
        JSON.stringify(currentConfig, null, 2);
    
    // Load last working configuration if available
    loadLastWorkingConfig();
    
    // Handle test type changes
    const testTypeSelect = document.querySelector('select[name="test_type"]');
    if (testTypeSelect) {
        testTypeSelect.addEventListener('change', function() {
            const templateSection = document.getElementById('templateSection');
            if (this.value === 'template') {
                templateSection.classList.remove('hidden');
            } else {
                templateSection.classList.add('hidden');
            }
        });
    }
    
    // Handle fix option changes
    const fixOptions = document.querySelectorAll('input[name="fix_option"]');
    fixOptions.forEach(option => {
        option.addEventListener('change', function() {
            const passwordSection = document.getElementById('passwordInputSection');
            if (this.value === 'fix_password_format') {
                passwordSection.classList.remove('hidden');
            } else {
                passwordSection.classList.add('hidden');
            }
        });
    });
    
    // Auto-hide success messages
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                if (alert.parentElement) {
                    alert.remove();
                }
            }, 500);
        });
    }, 5000);
});

// Loading functions
function showLoading(message = 'Processing...', submessage = 'Please wait') {
    const overlay = document.getElementById('loadingOverlay');
    const messageEl = document.getElementById('loadingMessage');
    const submessageEl = document.getElementById('loadingSubmessage');
    
    if (overlay && messageEl && submessageEl) {
        messageEl.textContent = message;
        submessageEl.textContent = submessage;
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// Comprehensive Test
function runComprehensiveTest() {
    showLoading('Running comprehensive email tests...', 'This may take a few moments');
    
    fetch('{{ route("developer.email.comprehensive-test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showToast('Comprehensive test completed successfully!', 'success');
            // Reload page to show new results
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Test failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showToast('Test failed: ' + error.message, 'error');
    });
}

// Validate Configuration
function validateConfiguration() {
    showLoading('Validating email configuration...');
    
    fetch('{{ route("developer.email.validate-config") }}', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            if (data.validation.valid) {
                showToast('Configuration is valid and properly configured!', 'success');
            } else {
                showToast('Configuration validation failed: ' + data.validation.message, 'error');
            }
            
            // Show validation details in modal
            showValidationResults(data);
        } else {
            showToast('Validation failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showToast('Validation failed: ' + error.message, 'error');
    });
}

function showValidationResults(data) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-backdrop" onclick="this.parentElement.remove()"></div>
        <div class="modal-content modal-content-lg">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-check-circle mr-2"></i>
                    Configuration Validation Results
                </h3>
                <button type="button" class="modal-close-btn" onclick="this.closest('.modal').remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="space-y-4">
                    <!-- Overall Status -->
                    <div class="validation-status ${data.validation.valid ? 'bg-success' : 'bg-danger'}">
                        <div class="flex items-center">
                            <i class="fas fa-${data.validation.valid ? 'check-circle' : 'times-circle'} mr-2"></i>
                            <span class="font-bold">${data.validation.valid ? 'VALID' : 'INVALID'}</span>
                            <span class="ml-2">${data.validation.message}</span>
                        </div>
                    </div>
                    
                    <!-- Validation Checks -->
                    <div>
                        <h4 class="section-subtitle">Validation Checks</h4>
                        <div class="space-y-2">
                            ${data.validation.checks ? Object.entries(data.validation.checks).map(([check, result]) => `
                                <div class="validation-check">
                                    <div class="flex items-center justify-between">
                                        <span class="validation-check-label">${check.replace(/_/g, ' ')}</span>
                                        <span class="validation-check-status ${result.valid ? 'text-success' : 'text-danger'}">
                                            <i class="fas fa-${result.valid ? 'check-circle' : 'times-circle'} mr-1"></i>
                                            ${result.valid ? 'Pass' : 'Fail'}
                                        </span>
                                    </div>
                                    ${result.message ? `<div class="validation-check-message">${result.message}</div>` : ''}
                                </div>
                            `).join('') : 'No validation checks performed'}
                        </div>
                    </div>
                    
                    <!-- Recommendations -->
                    ${data.validation.recommendations && data.validation.recommendations.length > 0 ? `
                        <div>
                            <h4 class="section-subtitle">Recommendations</h4>
                            <ul class="space-y-2">
                                ${data.validation.recommendations.map(rec => `
                                    <li class="validation-recommendation">
                                        <i class="fas fa-lightbulb mr-2 text-warning"></i>
                                        ${rec}
                                    </li>
                                `).join('')}
                            </ul>
                        </div>
                    ` : ''}
                    
                    <!-- Gmail Help -->
                    ${data.gmail_help && data.gmail_help.needed ? `
                        <div class="alert alert-info">
                            <div class="flex items-start">
                                <i class="fab fa-google mr-2 mt-1"></i>
                                <div>
                                    <p class="font-bold">Gmail Configuration Required</p>
                                    <p class="text-sm">${data.gmail_help.message}</p>
                                    ${data.gmail_help.steps ? `
                                        <ul class="text-xs mt-2 space-y-1">
                                            ${data.gmail_help.steps.map(step => `<li>• ${step}</li>`).join('')}
                                        </ul>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    ` : ''}
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="this.closest('.modal').remove()" class="btn btn-secondary">
                    Close
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Fix Issues
function showFixIssuesModal() {
    const modal = document.getElementById('fixIssuesModal');
    if (modal) {
        // Reset form
        modal.querySelectorAll('input[type="radio"]').forEach(input => {
            input.checked = false;
        });
        modal.querySelector('#passwordInputSection').classList.add('hidden');
        
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function submitFixIssues() {
    const selectedOption = document.querySelector('input[name="fix_option"]:checked');
    if (!selectedOption) {
        showToast('Please select an issue to fix', 'warning');
        return;
    }
    
    const action = selectedOption.value;
    let data = { action: action };
    
    // Add password if needed
    if (action === 'fix_password_format') {
        const newPassword = document.getElementById('new_password').value;
        if (!newPassword) {
            showToast('Please enter a new password', 'warning');
            return;
        }
        data.new_password = newPassword;
    }
    
    showLoading('Fixing issue...', 'This may take a few moments');
    
    fetch('{{ route("developer.email.fix-issues") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        closeModal('fixIssuesModal');
        
        if (data.success) {
            showToast('Issue fixed successfully: ' + data.message, 'success');
            // Reload page to reflect changes
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Failed to fix issue: ' + data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        closeModal('fixIssuesModal');
        console.error('Error:', error);
        showToast('Failed to fix issue: ' + error.message, 'error');
    });
}

// Test Email
function showTestEmailModal() {
    const modal = document.getElementById('testEmailModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function submitTestEmail() {
    const form = document.getElementById('testEmailForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    showLoading('Sending test email...', 'Please wait');
    
    fetch('{{ route("developer.email.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            ...data,
            config_type: 'developer'
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        closeModal('testEmailModal');
        
        if (data.success) {
            showToast('Test email sent successfully!', 'success');
            // Reload page to update test history
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Test failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        closeModal('testEmailModal');
        console.error('Error:', error);
        showToast('Test failed: ' + error.message, 'error');
    });
}

// Compare Configurations
function showCompareModal() {
    const modal = document.getElementById('compareConfigModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function loadLastWorkingConfig() {
    fetch('{{ route("developer.email.config.backup") }}', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        const display = document.getElementById('lastConfigDisplay');
        const diffMessage = document.getElementById('differencesMessage');
        
        if (data.success && data.backup) {
            display.textContent = JSON.stringify(data.backup.configuration, null, 2);
            
            // Compare with current config
            const currentConfig = @json($currentConfig);
            const differences = findConfigDifferences(currentConfig, data.backup.configuration);
            
            if (differences.length > 0) {
                diffMessage.innerHTML = `
                    <strong>${differences.length} difference(s) found:</strong><br>
                    ${differences.map(diff => `• ${diff}`).join('<br>')}
                `;
            } else {
                diffMessage.textContent = 'Configurations are identical';
            }
        } else {
            display.textContent = 'No backup configuration available';
            diffMessage.textContent = 'Cannot compare without backup';
        }
    })
    .catch(error => {
        console.error('Error loading backup:', error);
    });
}

function findConfigDifferences(config1, config2) {
    const differences = [];
    
    // Simple comparison - in production, use a more robust diff library
    const keys = new Set([...Object.keys(config1 || {}), ...Object.keys(config2 || {})]);
    
    keys.forEach(key => {
        const val1 = config1?.[key];
        const val2 = config2?.[key];
        
        if (val1 !== val2) {
            differences.push(`${key}: "${val1}" vs "${val2}"`);
        }
    });
    
    return differences;
}

function restoreConfiguration() {
    if (confirm('WARNING: This will overwrite your current email configuration with the last working backup.\n\nAre you sure you want to continue?')) {
        showLoading('Restoring configuration...');
        
        fetch('{{ route("developer.email.config.restore") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        closeModal('compareConfigModal');
        
        if (data.success) {
            showToast('Configuration restored successfully!', 'success');
            // Reload page to reflect changes
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Restore failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        closeModal('compareConfigModal');
        console.error('Error:', error);
        showToast('Restore failed: ' + error.message, 'error');
    });
    }
}

// Audit Logs
function refreshAuditLogs() {
    showLoading('Refreshing audit logs...');
    
    fetch('{{ route("developer.email.diagnostics") }}', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showToast('Audit logs refreshed successfully!', 'success');
            location.reload();
        } else {
            showToast('Failed to refresh audit logs: ' + data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showToast('Failed to refresh audit logs: ' + error.message, 'error');
    });
}

function showAuditDetails(auditId) {
    showLoading('Loading audit details...');
    
    fetch(`/developer/email/audit/${auditId}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showAuditDetailsModal(data.audit);
        } else {
            showToast('Failed to load audit details: ' + data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showToast('Failed to load audit details: ' + error.message, 'error');
    });
}

function showAuditDetailsModal(audit) {
    const modal = document.getElementById('auditDetailsModal');
    const content = document.getElementById('auditDetailsContent');
    
    if (modal && content) {
        content.innerHTML = `
            <div class="space-y-4">
                <!-- Basic Info -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="audit-detail-label">Action</p>
                        <p class="audit-detail-value">${audit.action}</p>
                    </div>
                    <div>
                        <p class="audit-detail-label">Status</p>
                        <span class="badge badge-${audit.status === 'success' ? 'success' : (audit.status === 'failed' ? 'danger' : 'warning')}">
                            ${audit.status}
                        </span>
                    </div>
                    <div>
                        <p class="audit-detail-label">Date & Time</p>
                        <p class="audit-detail-value">${new Date(audit.created_at).toLocaleString()}</p>
                    </div>
                    <div>
                        <p class="audit-detail-label">IP Address</p>
                        <p class="audit-detail-value">${audit.ip_address}</p>
                    </div>
                </div>
                
                <!-- Message -->
                <div>
                    <p class="audit-detail-label">Message</p>
                    <p class="audit-detail-value">${audit.message}</p>
                </div>
                
                <!-- Configuration Changes -->
                ${audit.old_configuration || audit.new_configuration ? `
                    <div>
                        <p class="audit-detail-label">Configuration Changes</p>
                        <div class="grid grid-cols-2 gap-4">
                            ${audit.old_configuration ? `
                                <div>
                                    <p class="audit-detail-subtitle">Old Configuration</p>
                                    <pre class="text-xs bg-gray-50 dark:bg-gray-800 p-3 rounded">${JSON.stringify(JSON.parse(audit.old_configuration), null, 2)}</pre>
                                </div>
                            ` : ''}
                            ${audit.new_configuration ? `
                                <div>
                                    <p class="audit-detail-subtitle">New Configuration</p>
                                    <pre class="text-xs bg-gray-50 dark:bg-gray-800 p-3 rounded">${JSON.stringify(JSON.parse(audit.new_configuration), null, 2)}</pre>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                ` : ''}
                
                <!-- Metadata -->
                ${audit.metadata ? `
                    <div>
                        <p class="audit-detail-label">Additional Metadata</p>
                        <pre class="text-xs bg-gray-50 dark:bg-gray-800 p-3 rounded">${JSON.stringify(JSON.parse(audit.metadata), null, 2)}</pre>
                    </div>
                ` : ''}
            </div>
        `;
        
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

// Utility Functions
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const messageEl = document.createElement('span');
    messageEl.className = 'toast-message';
    messageEl.textContent = message;
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'toast-close-btn';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('toast-hiding');
        setTimeout(() => toast.remove(), 300);
    };
    
    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('toast-showing');
    }, 10);
    
    setTimeout(() => {
        if (toast.parentNode === toastContainer) {
            toast.classList.add('toast-hiding');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}
</script>

<style>
/* Additional CSS for diagnostics page */

/* Config Summary */
.config-summary-item {
    padding: 1rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
}

.config-summary-label {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.config-summary-value {
    color: var(--text-primary);
    font-weight: 500;
    font-size: 0.875rem;
}

/* Config Details */
.config-details {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
}

.config-detail-item {
    display: flex;
    align-items: flex-start;
    padding: 0.5rem;
    border-radius: 0.375rem;
    background-color: var(--bg-secondary);
}

.config-detail-label {
    color: var(--text-secondary);
    font-size: 0.875rem;
    width: 150px;
    flex-shrink: 0;
}

.config-detail-value {
    color: var(--text-primary);
    font-family: 'Courier New', monospace;
    font-size: 0.875rem;
    flex: 1;
    word-break: break-all;
}

/* Test Results */
.test-result-item {
    padding: 1rem;
    border-radius: 0.5rem;
    border: 1px solid;
    background-color: var(--card-bg);
}

.test-success {
    border-color: var(--success);
    background-color: rgba(var(--success-rgb), 0.05);
}

.test-failed {
    border-color: var(--danger);
    background-color: rgba(var(--danger-rgb), 0.05);
}

.test-result-label {
    color: var(--text-primary);
    font-weight: 500;
}

.test-result-message {
    color: var(--text-secondary);
    font-size: 0.875rem;
    margin-top: 0.5rem;
}

.test-result-details {
    margin-top: 0.5rem;
    padding: 0.5rem;
    background-color: var(--bg-secondary);
    border-radius: 0.375rem;
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    overflow-x: auto;
}

.test-result-meta {
    display: flex;
    justify-content: space-between;
    margin-top: 0.5rem;
    font-size: 0.75rem;
}

.test-result-time {
    color: var(--text-secondary);
}

.test-result-timestamp {
    color: var(--text-secondary);
}

/* Status Items */
.status-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border-color);
}

.status-item:last-child {
    border-bottom: none;
}

.status-label {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.status-value {
    color: var(--text-primary);
    font-weight: 500;
    font-size: 0.875rem;
}

/* Permission Items */
.permission-item {
    padding: 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
}

.permission-file {
    color: var(--text-primary);
    font-weight: 500;
    font-size: 0.875rem;
}

.permission-status {
    font-size: 0.75rem;
    font-weight: 500;
}

.permission-path {
    margin-top: 0.25rem;
    padding-top: 0.25rem;
    border-top: 1px solid var(--border-color);
}

/* Stat Items */
.stat-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border-color);
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.stat-value {
    color: var(--text-primary);
    font-weight: 500;
    font-size: 0.875rem;
}

/* Gmail Help */
.gmail-help-steps {
    background-color: var(--bg-secondary);
    padding: 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
}

/* History Stats */
.history-stat-card {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
}

.history-stat-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    color: white;
    font-size: 1.25rem;
}

.history-stat-label {
    color: var(--text-secondary);
    font-size: 0.75rem;
    font-weight: 500;
}

.history-stat-value {
    color: var(--text-primary);
    font-size: 1.5rem;
    font-weight: 700;
}

/* Test History */
.test-history-item {
    padding: 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
}

.test-history-type {
    color: var(--text-primary);
    font-weight: 500;
    font-size: 0.875rem;
}

.test-history-date {
    color: var(--text-secondary);
    font-size: 0.75rem;
    margin-left: 0.5rem;
}

.test-history-success {
    color: var(--success);
    font-size: 0.75rem;
    font-weight: 500;
}

.test-history-failed {
    color: var(--danger);
    font-size: 0.75rem;
    font-weight: 500;
}

.test-history-message {
    color: var(--text-secondary);
    font-size: 0.75rem;
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid var(--border-color);
}

.test-history-meta {
    display: flex;
    justify-content: space-between;
    margin-top: 0.5rem;
    font-size: 0.75rem;
}

.test-history-time {
    color: var(--text-secondary);
}

.test-history-email {
    color: var(--text-secondary);
}

/* Troubleshooting Tips */
.troubleshooting-tip {
    padding: 1rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    transition: transform 0.2s ease;
}

.troubleshooting-tip:hover {
    transform: translateY(-2px);
}

.tip-header {
    display: flex;
    align-items: center;
    margin-bottom: 0.75rem;
}

.tip-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
}

.tip-title {
    color: var(--text-primary);
    font-weight: 500;
}

.tip-list {
    list-style-type: none;
    padding-left: 0;
}

.tip-list li {
    color: var(--text-secondary);
    font-size: 0.875rem;
    padding: 0.25rem 0;
    position: relative;
    padding-left: 1.25rem;
}

.tip-list li:before {
    content: '•';
    position: absolute;
    left: 0;
    color: var(--primary);
}

/* Validation Styles */
.validation-status {
    padding: 1rem;
    border-radius: 0.5rem;
    color: white;
}

.validation-check {
    padding: 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
}

.validation-check-label {
    color: var(--text-primary);
    font-weight: 500;
    font-size: 0.875rem;
}

.validation-check-status {
    font-size: 0.75rem;
    font-weight: 500;
}

.validation-check-message {
    color: var(--text-secondary);
    font-size: 0.75rem;
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid var(--border-color);
}

.validation-recommendation {
    color: var(--text-secondary);
    font-size: 0.875rem;
    padding: 0.5rem;
    background-color: var(--bg-secondary);
    border-radius: 0.375rem;
}

/* Fix Options */
.fix-option {
    padding: 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    cursor: pointer;
    transition: all 0.2s ease;
}

.fix-option:hover {
    background-color: var(--bg-secondary);
}

.fix-option-label {
    display: flex;
    flex-direction: column;
    cursor: pointer;
    margin-left: 0.5rem;
}

.fix-option-title {
    color: var(--text-primary);
    font-weight: 500;
    font-size: 0.875rem;
}

.fix-option-description {
    color: var(--text-secondary);
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

/* Audit Details */
.audit-detail-label {
    color: var(--text-secondary);
    font-size: 0.75rem;
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.audit-detail-value {
    color: var(--text-primary);
    font-size: 0.875rem;
}

.audit-detail-subtitle {
    color: var(--text-secondary);
    font-size: 0.75rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

/* Loading Spinner */
.loading-spinner {
    color: var(--primary);
}

/* Modal Improvements */
.modal-content-sm {
    max-width: 28rem;
}

/* Form Radio */
.form-radio {
    width: 1rem;
    height: 1rem;
    cursor: pointer;
}

/* Color Classes */
.bg-success {
    background-color: var(--success);
}

.bg-danger {
    background-color: var(--danger);
}

.bg-warning {
    background-color: var(--warning);
}

.bg-info {
    background-color: var(--info);
}

.bg-primary {
    background-color: var(--primary);
}

.btn-purple {
    background-color: #8b5cf6;
    color: white;
    border-color: #8b5cf6;
}

.btn-purple:hover {
    background-color: #7c3aed;
    border-color: #7c3aed;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-2,
    .grid.grid-cols-4 {
        grid-template-columns: 1fr;
    }
    
    .config-detail-item {
        flex-direction: column;
    }
    
    .config-detail-label {
        width: 100%;
        margin-bottom: 0.25rem;
    }
    
    .test-history-meta {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .modal-content,
    .modal-content-lg,
    .modal-content-sm {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
    }
}
</style>
@endsection