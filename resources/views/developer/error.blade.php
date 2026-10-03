{{-- developer/error.blade.php --}}
@extends('layouts.dev')

@php
    $pageTitle = 'System Error - Developer Dashboard';
    $errorMessage = $message ?? session('error') ?? 'An unexpected error occurred';
    $errorCode = $code ?? 500;
    $errorDetails = $details ?? null;
    $trace = $trace ?? null;
    $timestamp = now()->format('Y-m-d H:i:s');
    
    // FIX: Check if developer settings exist first
    $developerSettings = \App\Models\DeveloperSetting::first();
    
    // Theme detection
    $isDarkMode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] === 'true' : false;
@endphp

@section('title', $pageTitle)

@section('content')
<div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-4xl">
        <!-- Error Header -->
        <div class="card dark:bg-gray-800 transition-colors duration-200 mb-6">
            <div class="p-8">
                <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0">
                            <div class="w-20 h-20 rounded-full flex items-center justify-center border-4"
                                 style="background: linear-gradient(135deg, var(--danger) 0%, #dc3545 100%); color: white; font-weight: 600; border-color: rgba(var(--danger-rgb), 0.3);">
                                <i class="fas fa-exclamation-triangle text-3xl"></i>
                            </div>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold flex items-center dark:text-gray-100" style="color: var(--danger);">
                                <i class="fas fa-bug mr-3"></i> 
                                System Error
                            </h1>
                            <div class="text-lg mt-2 dark:text-gray-400">
                                Error Code: <span class="font-mono font-bold">{{ $errorCode }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 mt-4 md:mt-0">
                        <a href="{{ route('developer.dashboard') }}" 
                           class="px-4 py-2 rounded-lg font-medium inline-flex items-center transition-all duration-200 hover:scale-105 dark:text-gray-300 dark:hover:text-gray-100"
                           style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                        </a>
                        
                        <button onclick="location.reload()" 
                                class="px-4 py-2 rounded-lg font-medium inline-flex items-center transition-all duration-200 hover:scale-105 dark:text-gray-300 dark:hover:text-gray-100"
                                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                            <i class="fas fa-redo mr-2"></i> Retry
                        </button>
                        
                        <button onclick="toggleDarkMode()" 
                                class="px-4 py-2 rounded-lg font-medium inline-flex items-center transition-all duration-200 hover:scale-105 dark:text-gray-300 dark:hover:text-gray-100"
                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <i class="fas fa-moon mr-2"></i> Theme
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Error Card -->
        <div class="card dark:bg-gray-800 transition-colors duration-200 mb-6">
            <div class="p-8">
                <!-- Error Message -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 flex items-center dark:text-gray-100">
                        <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i> 
                        Error Message
                    </h2>
                    <div class="p-4 rounded-lg border dark:border-gray-700 transition-colors duration-200" 
                         style="background-color: rgba(var(--danger-rgb), 0.05);">
                        <div class="flex items-start">
                            <i class="fas fa-times-circle text-xl mt-1 mr-3" style="color: var(--danger);"></i>
                            <div>
                                <p class="text-lg font-medium dark:text-gray-100">{{ $errorMessage }}</p>
                                <p class="text-sm mt-2 flex items-center dark:text-gray-400">
                                    <i class="fas fa-clock mr-2"></i>
                                    Occurred at: {{ $timestamp }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error Details (if available) -->
                @if($errorDetails)
                <div class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 flex items-center dark:text-gray-100">
                        <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i> 
                        Additional Details
                    </h2>
                    <div class="p-4 rounded-lg border dark:border-gray-700 transition-colors duration-200" 
                         style="background-color: rgba(var(--info-rgb), 0.05);">
                        <pre class="whitespace-pre-wrap font-mono text-sm dark:text-gray-200">{{ json_encode($errorDetails, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                </div>
                @endif

                <!-- Stack Trace (if available and in debug mode) -->
                @if($trace && config('app.debug'))
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold flex items-center dark:text-gray-100">
                            <i class="fas fa-code-branch mr-2" style="color: var(--warning);"></i> 
                            Stack Trace
                        </h2>
                        <button id="toggle-trace" 
                                class="px-3 py-1 rounded text-sm font-medium dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-eye mr-1"></i> Show Trace
                        </button>
                    </div>
                    
                    <div id="trace-container" class="hidden">
                        <div class="p-4 rounded-lg border dark:border-gray-700 overflow-x-auto transition-colors duration-200" 
                             style="background-color: rgba(var(--warning-rgb), 0.05); max-height: 400px;">
                            <pre class="font-mono text-xs whitespace-pre dark:text-gray-200">{{ $trace }}</pre>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Quick Actions -->
                <div class="mt-8 pt-8 border-t dark:border-gray-700 transition-colors duration-200">
                    <h3 class="text-lg font-semibold mb-4 flex items-center dark:text-gray-100">
                        <i class="fas fa-wrench mr-2" style="color: var(--primary);"></i> 
                        Quick Actions
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {{-- FIXED: Changed from developer.settings to developer.settings.index --}}
                        @if($developerSettings)
                        <a href="{{ route('developer.settings.index') }}" 
                           class="p-4 rounded-lg border text-center transition-all duration-200 hover:scale-105 hover:shadow-md dark:border-gray-700 dark:hover:bg-gray-800"
                           style="border-color: var(--border-color); background-color: rgba(var(--primary-rgb), 0.05);">
                            <i class="fas fa-cog text-2xl mb-3" style="color: var(--primary);"></i>
                            <p class="font-medium dark:text-gray-100">Check Settings</p>
                            <p class="text-sm mt-1 dark:text-gray-400">Review configuration</p>
                        </a>
                        @else
                        <a href="{{ route('developer.dashboard') }}" 
                           class="p-4 rounded-lg border text-center transition-all duration-200 hover:scale-105 hover:shadow-md dark:border-gray-700 dark:hover:bg-gray-800"
                           style="border-color: var(--border-color); background-color: rgba(var(--primary-rgb), 0.05);">
                            <i class="fas fa-home text-2xl mb-3" style="color: var(--primary);"></i>
                            <p class="font-medium dark:text-gray-100">Go to Dashboard</p>
                            <p class="text-sm mt-1 dark:text-gray-400">Initialize settings first</p>
                        </a>
                        @endif
                        
                        {{-- FIXED: Only show monitoring link if it exists --}}
                        @if(Route::has('developer.monitoring.dashboard'))
                        <a href="{{ route('developer.monitoring.dashboard') }}" 
                           class="p-4 rounded-lg border text-center transition-all duration-200 hover:scale-105 hover:shadow-md dark:border-gray-700 dark:hover:bg-gray-800"
                           style="border-color: var(--border-color); background-color: rgba(var(--info-rgb), 0.05);">
                            <i class="fas fa-chart-line text-2xl mb-3" style="color: var(--info);"></i>
                            <p class="font-medium dark:text-gray-100">System Monitoring</p>
                            <p class="text-sm mt-1 dark:text-gray-400">Check system status</p>
                        </a>
                        @else
                        <a href="{{ route('developer.dashboard') }}" 
                           class="p-4 rounded-lg border text-center transition-all duration-200 hover:scale-105 hover:shadow-md dark:border-gray-700 dark:hover:bg-gray-800"
                           style="border-color: var(--border-color); background-color: rgba(var(--info-rgb), 0.05);">
                            <i class="fas fa-server text-2xl mb-3" style="color: var(--info);"></i>
                            <p class="font-medium dark:text-gray-100">System Status</p>
                            <p class="text-sm mt-1 dark:text-gray-400">Check dashboard</p>
                        </a>
                        @endif
                        
                        {{-- FIXED: Changed to a direct mailto link without routes --}}
                        <a href="mailto:support@example.com?subject=Developer%20Dashboard%20Error%20{{ $errorCode }}&body=Error%20Details:%0A{{ urlencode($errorMessage) }}" 
                           class="p-4 rounded-lg border text-center transition-all duration-200 hover:scale-105 hover:shadow-md dark:border-gray-700 dark:hover:bg-gray-800"
                           style="border-color: var(--border-color); background-color: rgba(var(--success-rgb), 0.05);">
                            <i class="fas fa-headset text-2xl mb-3" style="color: var(--success);"></i>
                            <p class="font-medium dark:text-gray-100">Contact Support</p>
                            <p class="text-sm mt-1 dark:text-gray-400">Get help from our team</p>
                        </a>
                    </div>
                </div>

                <!-- Debug Information (only in debug mode) -->
                @if(config('app.debug'))
                <div class="mt-8 pt-8 border-t dark:border-gray-700 transition-colors duration-200">
                    <h3 class="text-lg font-semibold mb-4 flex items-center dark:text-gray-100">
                        <i class="fas fa-bug mr-2" style="color: var(--danger);"></i> 
                        Debug Information
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-4 rounded-lg border dark:border-gray-700 transition-colors duration-200">
                            <h4 class="font-medium mb-2 dark:text-gray-100">Environment</h4>
                            <div class="space-y-1">
                                <div class="flex justify-between">
                                    <span class="dark:text-gray-400">App Name:</span>
                                    <span class="font-mono dark:text-gray-200">{{ config('app.name') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="dark:text-gray-400">Environment:</span>
                                    <span class="badge badge-{{ config('app.env') === 'production' ? 'danger dark:bg-red-900/30 dark:text-red-300 dark:border-red-800' : 'warning dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-800' }}">
                                        {{ config('app.env') }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="dark:text-gray-400">Debug Mode:</span>
                                    <span class="badge badge-{{ config('app.debug') ? 'warning dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-800' : 'success dark:bg-green-900/30 dark:text-green-300 dark:border-green-800' }}">
                                        {{ config('app.debug') ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-4 rounded-lg border dark:border-gray-700 transition-colors duration-200">
                            <h4 class="font-medium mb-2 dark:text-gray-100">Request Info</h4>
                            <div class="space-y-1">
                                <div class="flex justify-between">
                                    <span class="dark:text-gray-400">URL:</span>
                                    <span class="font-mono text-sm dark:text-gray-200">{{ request()->fullUrl() }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="dark:text-gray-400">Method:</span>
                                    <span class="badge badge-info dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800">{{ request()->method() }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="dark:text-gray-400">IP Address:</span>
                                    <span class="font-mono dark:text-gray-200">{{ request()->ip() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Help & Documentation -->
        <div class="card dark:bg-gray-800 transition-colors duration-200">
            <div class="p-8">
                <h3 class="text-lg font-semibold mb-4 flex items-center dark:text-gray-100">
                    <i class="fas fa-question-circle mr-2" style="color: var(--secondary);"></i> 
                    Need Help?
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="p-4 rounded-lg border dark:border-gray-700 transition-colors duration-200">
                        <h4 class="font-medium mb-3 flex items-center dark:text-gray-100">
                            <i class="fas fa-book mr-2" style="color: var(--primary);"></i> Common Solutions
                        </h4>
                        <ul class="space-y-2">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-xs mt-1 mr-2" style="color: var(--success);"></i>
                                <span class="dark:text-gray-400">Check your internet connection</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-xs mt-1 mr-2" style="color: var(--success);"></i>
                                <span class="dark:text-gray-400">Clear your browser cache and cookies</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-xs mt-1 mr-2" style="color: var(--success);"></i>
                                <span class="dark:text-gray-400">Verify your account permissions</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-xs mt-1 mr-2" style="color: var(--success);"></i>
                                <span class="dark:text-gray-400">Check system maintenance status</span>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="p-4 rounded-lg border dark:border-gray-700 transition-colors duration-200">
                        <h4 class="font-medium mb-3 flex items-center dark:text-gray-100">
                            <i class="fas fa-life-ring mr-2" style="color: var(--info);"></i> Support Resources
                        </h4>
                        <div class="space-y-3">
                            {{-- FIXED: Changed from route() to direct links or conditional checks --}}
                            @if(Route::has('developer.api-management.dashboard'))
                            <a href="{{ route('developer.api-management.dashboard') }}" 
                               class="flex items-center p-2 rounded transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <i class="fas fa-file-alt mr-3" style="color: var(--secondary);"></i>
                                <span class="dark:text-gray-200">Developer Dashboard</span>
                            </a>
                            @endif
                            
                            {{-- FIXED: Use direct link instead of non-existent route --}}
                            <a href="{{ config('app.url') }}/docs" target="_blank"
                               class="flex items-center p-2 rounded transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <i class="fas fa-question mr-3" style="color: var(--secondary);"></i>
                                <span class="dark:text-gray-200">Documentation</span>
                            </a>
                            
                            {{-- FIXED: Already correct, but kept for reference --}}
                            <a href="https://github.com/your-org/your-app/issues" 
                               target="_blank"
                               class="flex items-center p-2 rounded transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <i class="fab fa-github mr-3" style="color: var(--secondary);"></i>
                                <span class="dark:text-gray-200">Report Issue on GitHub</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Report (Optional) -->
        <div class="mt-6 text-center">
            <p class="text-sm dark:text-gray-400">
                <i class="fas fa-shield-alt mr-1"></i>
                This error has been automatically logged. Reference ID: 
                <span class="font-mono font-bold dark:text-gray-300">{{ uniqid('ERR_', true) }}</span>
            </p>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
/* Error Page Specific Styles with Dark Mode Support */
.card {
    border-radius: 0.75rem;
    background-color: white;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
}

.dark .card {
    background-color: var(--bg-secondary);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3), 0 1px 2px rgba(0, 0, 0, 0.2);
}

.card:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.dark .card:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
}

/* Badge Styles with Dark Mode */
.badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 9999px;
}

.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border: 1px solid rgba(var(--danger-rgb), 0.3);
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border: 1px solid rgba(var(--warning-rgb), 0.3);
}

.badge-success {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border: 1px solid rgba(var(--success-rgb), 0.3);
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border: 1px solid rgba(var(--info-rgb), 0.3);
}

.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    border: 1px solid rgba(var(--primary-rgb), 0.3);
}

.dark .badge-danger {
    background-color: rgba(var(--danger-rgb), 0.15);
    border-color: rgba(var(--danger-rgb), 0.4);
    color: #fecaca;
}

.dark .badge-warning {
    background-color: rgba(var(--warning-rgb), 0.15);
    border-color: rgba(var(--warning-rgb), 0.4);
    color: #fef3c7;
}

.dark .badge-success {
    background-color: rgba(var(--success-rgb), 0.15);
    border-color: rgba(var(--success-rgb), 0.4);
    color: #bbf7d0;
}

.dark .badge-info {
    background-color: rgba(var(--info-rgb), 0.15);
    border-color: rgba(var(--info-rgb), 0.4);
    color: #bfdbfe;
}

.dark .badge-primary {
    background-color: rgba(var(--primary-rgb), 0.15);
    border-color: rgba(var(--primary-rgb), 0.4);
    color: #93c5fd;
}

/* Preformatted text styling */
pre {
    font-family: 'Fira Code', 'Cascadia Code', 'Consolas', monospace;
    line-height: 1.5;
    tab-size: 4;
}

/* Trace container animation */
#trace-container {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Button hover effects */
button, a.btn {
    transition: all 0.2s ease;
}

button:hover, a.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.dark button:hover, .dark a.btn:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

/* Button styling consistent with settings blade */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.625rem 1rem;
    border-radius: 0.5rem;
    font-weight: 500;
    font-size: 0.875rem;
    line-height: 1;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn:hover:not(:disabled) {
    transform: translateY(-1px);
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}

.btn-primary {
    background-color: var(--primary);
    color: white;
    border-color: var(--primary);
}

.btn-primary:hover:not(:disabled) {
    background-color: var(--secondary);
    border-color: var(--secondary);
}

/* Custom form controls (if any on error page) */
.custom-input, .custom-select {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.5rem;
    padding: 0.625rem 0.75rem;
    width: 100%;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.dark .custom-input,
.dark .custom-select {
    background-color: #374151;
    border-color: #4b5563;
    color: #f3f4f6;
}

.custom-input:focus,
.custom-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card {
        padding: 1.5rem !important;
    }
    
    .flex-col-md {
        flex-direction: column;
    }
    
    .grid-cols-1.md\:grid-cols-3 {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    .grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
}

/* Accessibility focus styles */
button:focus, a:focus {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

.dark button:focus, .dark a:focus {
    outline-color: rgba(var(--primary-rgb), 0.8);
}

/* Print styles */
@media print {
    .no-print {
        display: none !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #ccc !important;
    }
    
    .btn, .action-buttons {
        display: none !important;
    }
}

/* Dark mode transitions */
.dark-mode-transition {
    transition: background-color 0.3s ease, 
                color 0.3s ease, 
                border-color 0.3s ease;
}

/* Theme toggle button styling */
[onclick="toggleDarkMode()"] {
    cursor: pointer;
}

[onclick="toggleDarkMode()"]:hover i {
    animation: spin 0.5s ease;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Loading animation for page transitions */
.page-load {
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
</style>
@endpush

@push('scripts')
<script>
// Theme toggle function (same as settings blade)
function toggleDarkMode() {
    const html = document.documentElement;
    const isDark = html.classList.contains('dark');
    
    if (isDark) {
        html.classList.remove('dark');
        localStorage.setItem('theme', 'light');
        document.cookie = 'dark_mode=false; path=/; max-age=31536000'; // 1 year
        showToast('Switched to Light Mode', 'info');
    } else {
        html.classList.add('dark');
        localStorage.setItem('theme', 'dark');
        document.cookie = 'dark_mode=true; path=/; max-age=31536000'; // 1 year
        showToast('Switched to Dark Mode', 'info');
    }
}

// Initialize theme on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check for saved theme preference
    const savedTheme = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
    
    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        if (!localStorage.getItem('theme')) {
            if (e.matches) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
    });
    
    // Toggle stack trace visibility
    const toggleButton = document.getElementById('toggle-trace');
    const traceContainer = document.getElementById('trace-container');
    
    if (toggleButton && traceContainer) {
        toggleButton.addEventListener('click', function() {
            const isVisible = !traceContainer.classList.contains('hidden');
            
            if (isVisible) {
                traceContainer.classList.add('hidden');
                toggleButton.innerHTML = '<i class="fas fa-eye mr-1"></i> Show Trace';
                toggleButton.classList.remove('bg-yellow-100', 'dark:bg-yellow-900/20');
                toggleButton.classList.add('bg-gray-100', 'dark:bg-gray-700');
            } else {
                traceContainer.classList.remove('hidden');
                toggleButton.innerHTML = '<i class="fas fa-eye-slash mr-1"></i> Hide Trace';
                toggleButton.classList.remove('bg-gray-100', 'dark:bg-gray-700');
                toggleButton.classList.add('bg-yellow-100', 'dark:bg-yellow-900/20');
            }
        });
    }
    
    // Auto-refresh countdown (optional)
    let refreshCountdown = 60;
    const countdownElement = document.getElementById('refresh-countdown');
    
    if (countdownElement) {
        const countdownInterval = setInterval(function() {
            refreshCountdown--;
            countdownElement.textContent = refreshCountdown;
            
            if (refreshCountdown <= 0) {
                clearInterval(countdownInterval);
                location.reload();
            }
        }, 1000);
    }
    
    // Copy error details to clipboard
    const copyButton = document.getElementById('copy-error-details');
    if (copyButton) {
        copyButton.addEventListener('click', function() {
            const errorDetails = `Error: {{ $errorMessage }}\nCode: {{ $errorCode }}\nTime: {{ $timestamp }}\nURL: ${window.location.href}`;
            
            navigator.clipboard.writeText(errorDetails).then(function() {
                // Show success message using toast function
                showToast('Error details copied to clipboard!', 'success');
                
                // Visual feedback
                const originalText = copyButton.innerHTML;
                copyButton.innerHTML = '<i class="fas fa-check mr-1"></i> Copied!';
                copyButton.style.backgroundColor = 'rgba(var(--success-rgb), 0.2)';
                copyButton.style.color = 'var(--success)';
                copyButton.style.borderColor = 'rgba(var(--success-rgb), 0.5)';
                
                setTimeout(function() {
                    copyButton.innerHTML = originalText;
                    copyButton.style.backgroundColor = '';
                    copyButton.style.color = '';
                    copyButton.style.borderColor = '';
                }, 2000);
            }).catch(function() {
                showToast('Failed to copy to clipboard', 'error');
            });
        });
    }
    
    // Add page load animation
    document.body.classList.add('page-load');
    
    // Auto-hide success messages after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert-success').forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
});

// Toast notification function (same as settings blade)
function showToast(message, type = 'info') {
    // Check if toast container exists
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast element with dark mode support
    const toast = document.createElement('div');
    const isDark = document.documentElement.classList.contains('dark');
    
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
        type === 'success' ? (isDark ? 'bg-green-900 text-green-100 border border-green-800' : 'bg-green-100 text-green-800 border border-green-200') :
        type === 'error' ? (isDark ? 'bg-red-900 text-red-100 border border-red-800' : 'bg-red-100 text-red-800 border border-red-200') :
        type === 'warning' ? (isDark ? 'bg-yellow-900 text-yellow-100 border border-yellow-800' : 'bg-yellow-100 text-yellow-800 border border-yellow-200') :
        (isDark ? 'bg-blue-900 text-blue-100 border border-blue-800' : 'bg-blue-100 text-blue-800 border border-blue-200')
    }`;
    
    // Create message element
    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.textContent = message;
    
    // Create close button
    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-200';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };
    
    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    toastContainer.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (toast.parentNode === toastContainer) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}
</script>
@endpush