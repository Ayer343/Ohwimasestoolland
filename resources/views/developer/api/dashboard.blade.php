{{-- resources/views/developer/api/dashboard.blade.php --}}
@extends('layouts.dev')

@php
    $isDeveloper = auth()->user()->isDeveloper();
    $isAdmin = auth()->user()->isAdmin();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    
    $routePrefix = 'developer.api-management';
    $pageTitle = 'API Management Dashboard - Developer Portal';
    
    $successMessage = session('success');
    $errorMessage = session('error');
    $warningMessage = session('warning');
    
    $apiLogs = $apiLogs ?? collect();
    $rateLimits = $rateLimits ?? [];
    $usageStats = $usageStats ?? [];
    $settings = $settings ?? null;
    
    $filterStatus = request('status', 'all');
    $searchTerm = request('search', '');
    $dateFrom = request('date_from', now()->subDays(7)->format('Y-m-d'));
    $dateTo = request('date_to', now()->format('Y-m-d'));
    $methodFilter = request('method', 'all');
    
    // API Status
    $apiEnabled = $settings->api_enabled ?? false;
    $apiVersion = $settings->api_version ?? '1.0.0';
    $apiBaseUrl = $settings->api_base_url ?? url('/api');
    
    // Rate Limit Status
    $rateLimitEnabled = !empty($rateLimits);
    $currentRateLimit = $rateLimits['per_minute'] ?? 60;
    $rateLimitRemaining = $rateLimits['remaining'] ?? $currentRateLimit;
    $rateLimitReset = $rateLimits['reset_in'] ?? 60;
    
    // Usage Statistics
    $totalRequests = $usageStats['total_requests'] ?? 0;
    $successfulRequests = $usageStats['successful_requests'] ?? 0;
    $failedRequests = $usageStats['failed_requests'] ?? 0;
    $avgResponseTime = $usageStats['average_response_time'] ?? 0;
    $popularEndpoint = $usageStats['popular_endpoint'] ?? 'N/A';
    
    // Methods for filter
    $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
    
    // Status groups for filter
    $statusGroups = [
        'all' => 'All Status',
        'success' => 'Success (2xx)',
        'client_error' => 'Client Error (4xx)',
        'server_error' => 'Server Error (5xx)',
        'redirect' => 'Redirect (3xx)'
    ];
    
    // Fix: Check if apiLogs is a Paginator or Collection
    $isPaginator = $apiLogs instanceof \Illuminate\Pagination\LengthAwarePaginator;
    $logsCount = $isPaginator ? $apiLogs->total() : $apiLogs->count();
    $logsFirstItem = $isPaginator ? $apiLogs->firstItem() : 1;
    $logsLastItem = $isPaginator ? $apiLogs->lastItem() : $apiLogs->count();
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6 gap-4">
            <div class="flex items-start md:items-center gap-4">
                <!-- Icon -->
                <div class="flex-shrink-0">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-code text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-code mr-2" style="color: var(--primary);"></i> 
                        API Management Dashboard
                    </h2>
                    <div class="text-sm flex flex-wrap items-center gap-2 mt-1" style="color: var(--text-secondary);">
                        <span class="flex items-center">
                            <i class="fas fa-info-circle mr-1"></i>
                            Monitor and manage your API usage and configuration
                        </span>
                        <span class="hidden md:inline">•</span>
                        <span class="flex items-center">
                            <i class="fas fa-chart-bar mr-1"></i>
                            {{ number_format($totalRequests) }} total requests
                        </span>
                        <span class="hidden md:inline">•</span>
                        <span class="flex items-center">
                            <i class="fas fa-bolt mr-1"></i>
                            <span class="mr-2">API Status:</span>
                            @if($apiEnabled)
                                <span class="badge badge-success px-2 py-1 text-xs rounded-full">
                                    <i class="fas fa-check-circle mr-1"></i> Enabled
                                </span>
                            @else
                                <span class="badge badge-danger px-2 py-1 text-xs rounded-full">
                                    <i class="fas fa-times-circle mr-1"></i> Disabled
                                </span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
            <div class="text-sm flex flex-wrap items-center gap-3" style="color: var(--text-secondary);">
                <span class="flex items-center">
                    <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                </span>
                <a href="{{ route('developer.dashboard') }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center transition-all duration-200 hover:scale-105" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-dashboard mr-1"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Alerts Section -->
    <div class="alerts-container">
        @if($successMessage)
        <div class="alert alert-success" role="alert">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span class="font-bold">Success!</span>
                <span class="ml-2">{{ $successMessage }}</span>
            </div>
            <button type="button" class="alert-close" onclick="this.parentElement.remove()">
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
            <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @endif

        @if($warningMessage)
        <div class="alert alert-warning" role="alert">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span class="font-bold">Warning!</span>
                <span class="ml-2">{{ $warningMessage }}</span>
            </div>
            <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @endif
    </div>

    <!-- Navigation Card -->
    <div class="card">
        <div class="p-6">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('developer.api-management.documentation') }}" 
                       class="btn btn-primary inline-flex items-center text-sm px-4 py-2 rounded-lg"
                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-book mr-2"></i> Documentation
                    </a>
                    
                    <a href="{{ route('developer.api-management.tester') }}" 
                       class="btn btn-success inline-flex items-center text-sm px-4 py-2 rounded-lg"
                       style="background-color: var(--success); color: white;">
                        <i class="fas fa-flask mr-2"></i> API Tester
                    </a>
                    
                    @if(request()->hasAny(['search', 'status', 'method', 'date_from', 'date_to']))
                    <span class="badge badge-primary inline-flex items-center text-xs px-3 py-1 rounded-full">
                        <i class="fas fa-filter mr-1"></i> Filters Applied
                    </span>
                    @endif
                </div>
                
                <div class="flex flex-wrap items-center gap-3">
                    @if($apiLogs->isNotEmpty())
                    <a href="{{ route('developer.api-management.logs.export', array_merge(request()->except(['export']))) }}" 
                       class="btn btn-primary inline-flex items-center px-3 py-1.5 rounded-lg text-xs">
                        <i class="fas fa-file-export mr-1.5"></i> Export Logs
                    </a>
                    @endif
                    
                    <button type="button" 
                            onclick="showApiConfigModal()"
                            class="btn btn-info inline-flex items-center px-3 py-1.5 rounded-lg text-xs">
                        <i class="fas fa-cog mr-1.5"></i> Configure API
                    </button>
                    
                    <button type="button" 
                            onclick="generateApiKey()"
                            class="btn btn-primary inline-flex items-center px-3 py-1.5 rounded-lg text-xs"
                            style="background-color: var(--primary); color: white;">
                        <i class="fas fa-key mr-1.5"></i> Generate API Key
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- API Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Requests Card -->
        <div class="stat-card card">
            <div class="flex items-center">
                <div class="mr-4 flex-shrink-0">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-chart-line text-lg"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium mb-1 truncate" style="color: var(--text-secondary);">Total Requests</p>
                    <p class="text-2xl font-bold truncate" style="color: var(--text-primary);">{{ number_format($totalRequests) }}</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <span class="badge badge-primary inline-flex items-center px-2 py-1 rounded-full text-xs">
                        <i class="fas fa-bolt mr-1 text-xs"></i>
                        Last 30 days
                    </span>
                </div>
            </div>
            <div class="mt-3">
                <div class="flex justify-between text-xs mb-1" style="color: var(--text-secondary);">
                    <span>Success Rate</span>
                    <span>
                        @if($totalRequests > 0)
                            {{ round(($successfulRequests / $totalRequests) * 100, 1) }}%
                        @else
                            0%
                        @endif
                    </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <div class="bg-green-500 h-2 rounded-full" 
                         style="width: {{ $totalRequests > 0 ? ($successfulRequests / $totalRequests) * 100 : 0 }}%; background-color: var(--success);"></div>
                </div>
            </div>
        </div>
        
        <!-- Success Rate Card -->
        <div class="stat-card card">
            <div class="flex items-center">
                <div class="mr-4 flex-shrink-0">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-check-circle text-lg"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium mb-1 truncate" style="color: var(--text-secondary);">Success Rate</p>
                    <p class="text-2xl font-bold truncate" style="color: var(--text-primary);">
                        @if($totalRequests > 0)
                            {{ round(($successfulRequests / $totalRequests) * 100, 1) }}%
                        @else
                            0%
                        @endif
                    </p>
                </div>
                <div class="text-right flex-shrink-0">
                    @if($totalRequests > 0 && ($successfulRequests / $totalRequests) >= 0.95)
                        <span class="badge badge-success inline-flex items-center px-2 py-1 rounded-full text-xs">
                            <i class="fas fa-star mr-1 text-xs"></i>
                            Excellent
                        </span>
                    @elseif($totalRequests > 0 && ($successfulRequests / $totalRequests) >= 0.85)
                        <span class="badge badge-warning inline-flex items-center px-2 py-1 rounded-full text-xs">
                            <i class="fas fa-check mr-1 text-xs"></i>
                            Good
                        </span>
                    @else
                        <span class="badge badge-danger inline-flex items-center px-2 py-1 rounded-full text-xs">
                            <i class="fas fa-exclamation mr-1 text-xs"></i>
                            Needs Attention
                        </span>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Avg Response Time Card -->
        <div class="stat-card card">
            <div class="flex items-center">
                <div class="mr-4 flex-shrink-0">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-stopwatch text-lg"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium mb-1 truncate" style="color: var(--text-secondary);">Avg Response Time</p>
                    <p class="text-2xl font-bold truncate" style="color: var(--text-primary);">{{ number_format($avgResponseTime, 2) }}ms</p>
                </div>
                <div class="text-right flex-shrink-0">
                    @if($avgResponseTime < 100)
                        <span class="badge badge-success inline-flex items-center px-2 py-1 rounded-full text-xs">
                            <i class="fas fa-bolt mr-1 text-xs"></i>
                            Fast
                        </span>
                    @elseif($avgResponseTime < 500)
                        <span class="badge badge-warning inline-flex items-center px-2 py-1 rounded-full text-xs">
                            <i class="fas fa-tachometer-alt mr-1 text-xs"></i>
                            Moderate
                        </span>
                    @else
                        <span class="badge badge-danger inline-flex items-center px-2 py-1 rounded-full text-xs">
                            <i class="fas fa-exclamation-triangle mr-1 text-xs"></i>
                            Slow
                        </span>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Rate Limit Card -->
        <div class="stat-card card">
            <div class="flex items-center">
                <div class="mr-4 flex-shrink-0">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-tachometer-alt text-lg"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium mb-1 truncate" style="color: var(--text-secondary);">Rate Limit</p>
                    <p class="text-2xl font-bold truncate" style="color: var(--text-primary);">
                        {{ $rateLimitRemaining }}/{{ $currentRateLimit }}
                    </p>
                </div>
                <div class="text-right flex-shrink-0">
                    <span class="badge badge-info inline-flex items-center px-2 py-1 rounded-full text-xs">
                        <i class="fas fa-clock mr-1 text-xs"></i>
                        Resets in {{ $rateLimitReset }}s
                    </span>
                </div>
            </div>
            <div class="mt-3">
                <div class="flex justify-between text-xs mb-1" style="color: var(--text-secondary);">
                    <span>Usage</span>
                    <span>{{ round(($rateLimitRemaining / $currentRateLimit) * 100, 1) }}% remaining</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <div class="h-2 rounded-full" 
                         style="width: {{ ($rateLimitRemaining / $currentRateLimit) * 100 }}%; 
                                background-color: {{ ($rateLimitRemaining / $currentRateLimit) > 0.3 ? 'var(--success)' : 'var(--danger)' }};"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Filters Sidebar -->
        <div class="lg:col-span-1">
            <div class="card mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> API Log Filters
                    </h3>
                    
                    <form method="GET" action="{{ route($routePrefix . '.dashboard') }}" class="space-y-4" id="filterForm">
                        <!-- Search -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-search mr-1"></i> Search Endpoints
                            </label>
                            <div class="relative">
                                <input type="text" 
                                       name="search" 
                                       value="{{ $searchTerm }}" 
                                       class="custom-input w-full pl-10 pr-3 py-2"
                                       placeholder="Search endpoint, IP, user agent..."
                                       style="padding-left: 2.5rem;">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-search" style="color: var(--text-secondary);"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Method Filter -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-code-branch mr-1"></i> HTTP Method
                            </label>
                            <select name="method" class="custom-select w-full">
                                <option value="all" {{ $methodFilter == 'all' ? 'selected' : '' }}>All Methods</option>
                                @foreach($methods as $method)
                                    <option value="{{ $method }}" {{ $methodFilter == $method ? 'selected' : '' }}>
                                        {{ $method }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-check-circle mr-1"></i> Status Group
                            </label>
                            <select name="status" class="custom-select w-full">
                                @foreach($statusGroups as $key => $label)
                                    <option value="{{ $key }}" {{ $filterStatus == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Date Range -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-calendar mr-1"></i> From
                                </label>
                                <input type="date" 
                                       name="date_from" 
                                       value="{{ $dateFrom }}" 
                                       class="custom-input w-full">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-calendar mr-1"></i> To
                                </label>
                                <input type="date" 
                                       name="date_to" 
                                       value="{{ $dateTo }}" 
                                       class="custom-input w-full">
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-bolt mr-1"></i> Quick Actions
                            </h4>
                            <div class="space-y-2">
                                <button type="submit" 
                                        class="btn btn-primary w-full text-center px-3 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-filter mr-2"></i> Apply Filters
                                </button>
                                
                                <a href="{{ route($routePrefix . '.dashboard') }}" 
                                   class="btn btn-secondary w-full text-center px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-redo mr-2"></i> Reset Filters
                                </a>
                                
                                <button type="button" 
                                        onclick="clearApiLogs()"
                                        class="btn btn-danger w-full text-center px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-trash-alt mr-2"></i> Clear All Logs
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- API Information -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i> API Information
                    </h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 rounded-lg" 
                             style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                            <div class="flex items-center">
                                <i class="fas fa-toggle-on mr-3" style="color: var(--info);"></i>
                                <span style="color: var(--text-primary);">API Status</span>
                            </div>
                            <div>
                                @if($apiEnabled)
                                <span class="badge badge-success px-2 py-1 text-xs rounded-full">
                                    <i class="fas fa-check-circle mr-1"></i> Enabled
                                </span>
                                @else
                                <span class="badge badge-danger px-2 py-1 text-xs rounded-full">
                                    <i class="fas fa-times-circle mr-1"></i> Disabled
                                </span>
                                @endif
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between p-3 rounded-lg" 
                             style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                            <div class="flex items-center">
                                <i class="fas fa-code-branch mr-3" style="color: var(--info);"></i>
                                <span style="color: var(--text-primary);">API Version</span>
                            </div>
                            <div>
                                <span class="badge badge-info px-2 py-1 text-xs rounded-full">
                                    v{{ $apiVersion }}
                                </span>
                            </div>
                        </div>
                        
                        <div class="p-3 rounded-lg" 
                             style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-link mr-3" style="color: var(--info);"></i>
                                <span style="color: var(--text-primary);">Base URL</span>
                            </div>
                            <div class="text-sm break-all" style="color: var(--text-secondary);">
                                {{ $apiBaseUrl }}
                            </div>
                            <button type="button" 
                                    onclick="copyToClipboard('{{ $apiBaseUrl }}', 'API Base URL')"
                                    class="btn btn-primary-outline text-xs py-1 px-2 mt-2 rounded">
                                <i class="fas fa-copy mr-1"></i> Copy URL
                            </button>
                        </div>
                        
                        <div class="p-3 rounded-lg" 
                             style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-fire mr-3" style="color: var(--info);"></i>
                                <span style="color: var(--text-primary);">Popular Endpoint</span>
                            </div>
                            <div class="text-sm break-all" style="color: var(--text-secondary);">
                                {{ $popularEndpoint }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- API Logs Table -->
        <div class="lg:col-span-3">
            <div class="card p-6">
                <!-- Table Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <div class="min-w-0">
                        <h3 class="text-lg font-semibold truncate" style="color: var(--text-primary);">
                            API Request Logs ({{ $logsCount }})
                        </h3>
                        <p class="text-sm mt-1 truncate" style="color: var(--text-secondary);">
                            @if($isPaginator && $apiLogs->count() > 0)
                                Showing {{ $logsFirstItem }} to {{ $logsLastItem }} of {{ $logsCount }} entries
                            @elseif($apiLogs->count() > 0)
                                Showing {{ $apiLogs->count() }} entries
                            @endif
                            @if($dateFrom && $dateTo)
                                • From {{ $dateFrom }} to {{ $dateTo }}
                            @endif
                        </p>
                    </div>
                    
                    <!-- Controls -->
                    <div class="flex items-center space-x-3">
                        <!-- Refresh Button -->
                        <button onclick="refreshLogs()" 
                                class="btn btn-info px-3 py-1.5 rounded-lg text-sm">
                            <i class="fas fa-sync-alt mr-1.5"></i> Refresh
                        </button>
                        
                        <!-- View Logs Button -->
                        <a href="{{ route('developer.api-management.logs') }}" 
                           class="btn btn-primary px-3 py-1.5 rounded-lg text-sm">
                            <i class="fas fa-list mr-1.5"></i> View All Logs
                        </a>
                    </div>
                </div>

                @if($apiLogs->isEmpty())
                    <!-- Empty State -->
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                             style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-code text-2xl" style="color: var(--primary);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                            No API logs found
                        </h4>
                        <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                            @if(request()->hasAny(['search', 'status', 'method', 'date_from', 'date_to']))
                                No logs match your current filters. Try adjusting your search criteria.
                            @else
                                No API requests have been logged yet. Make your first API call to see logs here.
                            @endif
                        </p>
                        <div class="flex flex-wrap justify-center gap-3">
                            <a href="{{ route('developer.api-management.tester') }}" 
                               class="btn btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                                <i class="fas fa-flask mr-2"></i> Test API
                            </a>
                            <a href="{{ route($routePrefix . '.dashboard') }}" 
                               class="btn btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-redo mr-2"></i> Clear Filters
                            </a>
                        </div>
                    </div>
                @else
                    <!-- Logs Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Timestamp</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Method</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Endpoint</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Status</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Response Time</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">IP Address</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($apiLogs as $log)
                                <tr>
                                    <td class="p-3" style="color: var(--text-secondary);">
                                        <div class="flex items-center">
                                            <i class="fas fa-clock mr-2" style="color: var(--text-secondary);"></i>
                                            {{ $log->created_at->format('Y-m-d H:i:s') }}
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <span class="method-badge method-{{ strtolower($log->method) }} px-2 py-1 rounded text-xs font-medium">
                                            {{ $log->method }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <div class="font-mono text-sm truncate max-w-xs" style="color: var(--text-primary);" title="{{ $log->endpoint }}">
                                            {{ $log->endpoint }}
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        @php
                                            $statusClass = 'badge-secondary';
                                            if ($log->status_code >= 200 && $log->status_code < 300) {
                                                $statusClass = 'badge-success';
                                            } elseif ($log->status_code >= 400 && $log->status_code < 500) {
                                                $statusClass = 'badge-warning';
                                            } elseif ($log->status_code >= 500) {
                                                $statusClass = 'badge-danger';
                                            }
                                        @endphp
                                        <span class="badge {{ $statusClass }} px-2 py-1 rounded-full text-xs">
                                            {{ $log->status_code }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex items-center">
                                            <i class="fas fa-stopwatch mr-2" style="color: var(--text-secondary);"></i>
                                            <span class="{{ $log->response_time > 500 ? 'text-danger' : ($log->response_time > 200 ? 'text-warning' : 'text-success') }}">
                                                {{ number_format($log->response_time, 2) }}ms
                                            </span>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <div class="text-sm" style="color: var(--text-secondary);">
                                            {{ $log->ip_address }}
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex space-x-2">
                                            <button onclick="viewLogDetails({{ $log->id }})" 
                                                    class="btn btn-info px-2 py-1 rounded text-xs">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="copyLogData({{ $log->id }})" 
                                                    class="btn btn-primary-outline px-2 py-1 rounded text-xs">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                            <button onclick="deleteLog({{ $log->id }})" 
                                                    class="btn btn-danger px-2 py-1 rounded text-xs">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    @if($isPaginator && $apiLogs->hasPages())
                    <div class="flex flex-col md:flex-row justify-between items-center pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                        <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                            Showing {{ $logsFirstItem }} to {{ $logsLastItem }} of {{ $logsCount }} entries
                        </div>
                        <div class="pagination">
                            {{ $apiLogs->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Include Modals -->
@include('developer.api.modals.config')
@include('developer.api.modals.log-details')
@include('developer.api.modals.delete-confirmation')
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    initApiDashboard();
});

function initApiDashboard() {
    // Initialize modals
    initModals();
    
    // Initialize tooltips
    initTooltips();
    
    // Auto-hide messages
    autoHideMessages();
    
    // Initialize filter form
    initFilterForm();
    
    // Initialize real-time updates
    initRealTimeUpdates();
}

// Modal Functions
function initModals() {
    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideModal('apiConfigModal');
            hideModal('logDetailsModal');
            hideModal('deleteLogModal');
            hideModal('apiKeyModal');
        }
    });
    
    // Close modals when clicking outside
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function() {
            const modalId = this.closest('.modal').id;
            hideModal(modalId);
        });
    });
}

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        document.dispatchEvent(new Event('modal-shown'));
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        document.dispatchEvent(new Event('modal-hidden'));
    }
}

function showApiConfigModal() {
    showModal('apiConfigModal');
}

function hideApiConfigModal() {
    hideModal('apiConfigModal');
}

function showApiKeyModal() {
    showModal('apiKeyModal');
}

function hideApiKeyModal() {
    hideModal('apiKeyModal');
}

function viewLogDetails(logId) {
    // Fetch log details via AJAX
    fetch(`/developer/api-management/logs/${logId}/details`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const modal = document.getElementById('logDetailsModal');
                const content = document.getElementById('logDetailsContent');
                
                if (content) {
                    content.innerHTML = `
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <h4 class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Method</h4>
                                    <p class="font-medium" style="color: var(--text-primary);">${data.log.method}</p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Status</h4>
                                    <span class="badge badge-${data.log.status_code >= 200 && data.log.status_code < 300 ? 'success' : data.log.status_code >= 400 && data.log.status_code < 500 ? 'warning' : 'danger'} px-2 py-1 rounded-full text-xs">
                                        ${data.log.status_code}
                                    </span>
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Endpoint</h4>
                                <p class="font-mono text-sm break-all" style="color: var(--text-primary);">${data.log.endpoint}</p>
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Request Headers</h4>
                                <pre class="bg-gray-100 p-3 rounded text-xs overflow-auto max-h-32">${JSON.stringify(data.log.request_headers || {}, null, 2)}</pre>
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Request Body</h4>
                                <pre class="bg-gray-100 p-3 rounded text-xs overflow-auto max-h-32">${JSON.stringify(data.log.request_body || {}, null, 2)}</pre>
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Response Body</h4>
                                <pre class="bg-gray-100 p-3 rounded text-xs overflow-auto max-h-32">${JSON.stringify(data.log.response_body || {}, null, 2)}</pre>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <h4 class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Response Time</h4>
                                    <p class="font-medium" style="color: var(--text-primary);">${data.log.response_time}ms</p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium mb-1" style="color: var(--text-secondary);">IP Address</h4>
                                    <p class="font-medium" style="color: var(--text-primary);">${data.log.ip_address}</p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium mb-1" style="color: var(--text-secondary);">User Agent</h4>
                                    <p class="text-xs truncate" style="color: var(--text-secondary);" title="${data.log.user_agent}">${data.log.user_agent}</p>
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                showModal('logDetailsModal');
            } else {
                showToast('Failed to load log details.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred while loading log details.', 'error');
        });
}

function hideLogDetailsModal() {
    hideModal('logDetailsModal');
}

function showDeleteLogModal(logId) {
    const modal = document.getElementById('deleteLogModal');
    const form = document.getElementById('deleteLogForm');
    
    if (!form || !modal) return;
    
    form.action = `/developer/api-management/logs/${logId}`;
    form.reset();
    
    // Reset confirmation
    const confirmationInput = document.getElementById('delete_confirmation_log');
    const confirmBtn = document.getElementById('confirmDeleteLogBtn');
    const messageElement = document.getElementById('confirmationMessageLog');
    
    if (confirmationInput) confirmationInput.value = '';
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.classList.add('disabled');
    }
    if (messageElement) {
        messageElement.innerHTML = 'Type "DELETE" to confirm deletion';
        messageElement.style.color = 'var(--text-secondary)';
    }
    
    showModal('deleteLogModal');
    
    // Focus on input after modal animation
    setTimeout(() => {
        if (confirmationInput) confirmationInput.focus();
    }, 100);
}

function hideDeleteLogModal() {
    hideModal('deleteLogModal');
}

// API Functions
function generateApiKey() {
    if (!confirm('Generate a new API key? This will invalidate your current key.')) {
        return;
    }
    
    // Show loading state
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i> Generating...';
    btn.disabled = true;
    
    fetch('{{ route("developer.api-management.generate-key") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('API key generated successfully!', 'success');
            
            // Show the new API key in a modal
            if (data.api_key) {
                const apiKeyInput = document.getElementById('newApiKey');
                if (apiKeyInput) {
                    apiKeyInput.value = data.api_key;
                }
                showApiKeyModal();
            }
            
            // Reload after a delay to show updated key info
            setTimeout(() => location.reload(), 2000);
        } else {
            showToast(`Error: ${data.message}`, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to generate API key.', 'error');
    })
    .finally(() => {
        // Reset button state
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function copyApiKey() {
    const apiKeyInput = document.getElementById('newApiKey');
    if (apiKeyInput && apiKeyInput.value && apiKeyInput.value !== 'Loading...') {
        copyToClipboard(apiKeyInput.value, 'API Key');
    }
}

function copyLogDetails() {
    const content = document.getElementById('logDetailsContent');
    if (content) {
        const text = content.innerText || content.textContent;
        copyToClipboard(text, 'Log details');
    }
}

function clearApiLogs() {
    if (!confirm('Are you sure you want to clear all API logs? This action cannot be undone.')) {
        return;
    }
    
    fetch('{{ route("developer.api-management.clear-logs") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('API logs cleared successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(`Error: ${data.message}`, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to clear API logs.', 'error');
    });
}

function deleteLog(logId) {
    if (!confirm('Delete this log entry?')) {
        return;
    }
    
    fetch(`/developer/api-management/logs/${logId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Log entry deleted successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(`Error: ${data.message}`, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to delete log entry.', 'error');
    });
}

function copyLogData(logId) {
    // Fetch log data and copy to clipboard
    fetch(`/developer/api-management/logs/${logId}/details`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const logData = {
                    method: data.log.method,
                    endpoint: data.log.endpoint,
                    status: data.log.status_code,
                    response_time: data.log.response_time,
                    timestamp: data.log.created_at,
                    ip_address: data.log.ip_address,
                    request_body: data.log.request_body,
                    response_body: data.log.response_body
                };
                
                const text = JSON.stringify(logData, null, 2);
                copyToClipboard(text, 'Log data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to copy log data.', 'error');
        });
}

function refreshLogs() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i> Refreshing...';
    btn.disabled = true;
    
    // Simulate refresh by reloading the page
    setTimeout(() => {
        location.reload();
    }, 500);
}

function checkDeleteLogConfirmation(input) {
    const confirmBtn = document.getElementById('confirmDeleteLogBtn');
    const messageElement = document.getElementById('confirmationMessageLog');
    
    if (!confirmBtn || !messageElement) return;
    
    if (input.value === 'DELETE') {
        confirmBtn.disabled = false;
        confirmBtn.classList.remove('disabled');
        messageElement.style.color = 'var(--success)';
        messageElement.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Confirmation matches. You can now delete.';
    } else {
        confirmBtn.disabled = true;
        confirmBtn.classList.add('disabled');
        messageElement.style.color = 'var(--danger)';
        messageElement.innerHTML = `Type exactly "DELETE" (without quotes) to enable the delete button. You typed: "${input.value}"`;
    }
}

// Configuration Form Handling
function saveApiConfig() {
    const form = document.getElementById('apiConfigForm');
    if (!form) return;
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
    submitBtn.disabled = true;
    
    // Submit form via AJAX
    const formData = new FormData(form);
    
    fetch(form.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('API configuration saved successfully!', 'success');
            hideApiConfigModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(`Error: ${data.message}`, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to save configuration.', 'error');
    })
    .finally(() => {
        // Reset button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function initRealTimeUpdates() {
    // Update rate limit counter every second
    const rateLimitElement = document.querySelector('.rate-limit-counter');
    if (rateLimitElement) {
        setInterval(() => {
            // Simulate rate limit countdown (in a real app, this would come from an API)
            const current = parseInt(rateLimitElement.textContent.split('/')[0]);
            const max = parseInt(rateLimitElement.textContent.split('/')[1]);
            if (current < max) {
                const newCount = Math.min(max, current + 1);
                rateLimitElement.textContent = `${newCount}/${max}`;
            }
        }, 1000);
    }
}

// Utility Functions
function initTooltips() {
    // Initialize tooltips using browser's native title attribute
    // For more advanced tooltips, consider using a library like Popper.js
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function() {
            // Could enhance with custom tooltip implementation
        });
    });
}

function autoHideMessages() {
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
}

function copyToClipboard(text, label = 'Text') {
    if (!text) return;
    
    navigator.clipboard.writeText(text).then(() => {
        showToast(`${label} copied to clipboard!`, 'success');
    }).catch(err => {
        console.error('Failed to copy:', err);
        showToast('Failed to copy to clipboard.', 'error');
    });
}

function initFilterForm() {
    const form = document.getElementById('filterForm');
    if (!form) return;
    
    // Add debouncing to search input
    const searchInput = form.querySelector('input[name="search"]');
    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                form.submit();
            }, 500);
        });
    }
    
    // Prevent form submission on Enter in search input
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            form.submit();
        }
    });
}

function showToast(message, type = 'info') {
    // Remove existing toasts
    document.querySelectorAll('.toast').forEach(toast => toast.remove());
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(toast);
    
    // Show toast
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Export API Configuration
function exportApiConfig() {
    fetch('{{ route("developer.api-management.export-config") }}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const configText = JSON.stringify(data.config, null, 2);
                copyToClipboard(configText, 'API Configuration');
            } else {
                showToast('Failed to export configuration.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to export configuration.', 'error');
        });
}

// Import API Configuration
function importApiConfig() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json';
    
    input.onchange = function(e) {
        const file = e.target.files[0];
        const reader = new FileReader();
        
        reader.onload = function(e) {
            try {
                const config = JSON.parse(e.target.result);
                
                if (confirm('Import this API configuration? This will overwrite current settings.')) {
                    fetch('{{ route("developer.api-management.import-config") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(config)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Configuration imported successfully!', 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast(`Error: ${data.message}`, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('Failed to import configuration.', 'error');
                    });
                }
            } catch (error) {
                showToast('Invalid configuration file.', 'error');
            }
        };
        
        reader.readAsText(file);
    };
    
    input.click();
}

// Test API Connection
function testApiConnection() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i> Testing...';
    btn.disabled = true;
    
    fetch('{{ route("developer.api-management.test-connection") }}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('API connection successful! Response time: ' + data.response_time + 'ms', 'success');
            } else {
                showToast(`API connection failed: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('API connection test failed.', 'error');
        })
        .finally(() => {
            // Reset button state
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}
</script>
@endpush

@push('styles')
<style>
/* Modal Styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 1rem;
}

.modal.hidden {
    display: none;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.modal-container {
    position: relative;
    width: 100%;
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
    z-index: 10;
}

.modal-content {
    background-color: var(--card-bg);
    border-radius: 0.75rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-10px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    padding: 1.5rem 1.5rem 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background-color: rgba(0, 0, 0, 0.05);
    color: var(--text-primary);
}

.modal-body {
    padding: 1.5rem;
    max-height: calc(90vh - 200px);
    overflow-y: auto;
}

.modal-footer {
    padding: 0 1.5rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
    border-top: 1px solid var(--border-color);
    padding-top: 1.5rem;
}

/* Form Elements for Modals */
.form-group {
    margin-bottom: 1.25rem;
}

.form-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.form-input,
.form-textarea,
.form-select {
    width: 100%;
    padding: 0.625rem 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    background-color: var(--card-bg);
    color: var(--text-primary);
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.form-input:focus,
.form-textarea:focus,
.form-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.form-textarea {
    min-height: 100px;
    resize: vertical;
}

.form-help {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.form-radio,
.form-checkbox {
    accent-color: var(--primary);
}

/* Method Badges */
.method-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.method-get {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border: 1px solid rgba(var(--success-rgb), 0.3);
}

.method-post {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border: 1px solid rgba(var(--info-rgb), 0.3);
}

.method-put, .method-patch {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border: 1px solid rgba(var(--warning-rgb), 0.3);
}

.method-delete {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border: 1px solid rgba(var(--danger-rgb), 0.3);
}

.method-head, .method-options {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
    border: 1px solid rgba(var(--secondary-rgb), 0.3);
}

/* Progress Bars */
.w-full.bg-gray-200.rounded-full.h-2 {
    background-color: rgba(var(--primary-rgb), 0.1);
}

/* Status Colors */
.text-success {
    color: var(--success);
}

.text-warning {
    color: var(--warning);
}

.text-danger {
    color: var(--danger);
}

.text-info {
    color: var(--info);
}

/* Code styling */
code {
    font-family: 'Courier New', Courier, monospace;
    background-color: rgba(0, 0, 0, 0.05);
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-size: 0.875em;
}

/* Preformatted text for JSON display */
pre {
    font-family: 'Courier New', Courier, monospace;
    line-height: 1.4;
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.bg-gray-100 {
    background-color: rgba(0, 0, 0, 0.05);
}

/* Rate limit animation */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.rate-limit-low {
    animation: pulse 2s infinite;
    color: var(--danger);
}

/* Scrollbar styling */
.modal-body::-webkit-scrollbar,
pre::-webkit-scrollbar {
    width: 6px;
}

.modal-body::-webkit-scrollbar-track,
pre::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb,
pre::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb:hover,
pre::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}

/* Toast notifications */
.toast {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    padding: 1rem 1.25rem;
    border-radius: 0.5rem;
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-width: 300px;
    max-width: 400px;
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.3s ease;
    z-index: 1000;
}

.toast.show {
    transform: translateY(0);
    opacity: 1;
}

.toast-success {
    border-left: 4px solid var(--success);
}

.toast-error {
    border-left: 4px solid var(--danger);
}

.toast-info {
    border-left: 4px solid var(--info);
}

.toast-warning {
    border-left: 4px solid var(--warning);
}

.toast-content {
    display: flex;
    align-items: center;
    flex: 1;
}

.toast-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 0.25rem;
    margin-left: 0.5rem;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.toast-close:hover {
    opacity: 1;
}

/* Alert boxes */
.bg-yellow-50 {
    background-color: rgba(255, 193, 7, 0.05);
}

.border-yellow-200 {
    border-color: rgba(255, 193, 7, 0.2);
}

.text-yellow-400 {
    color: #ffc107;
}

.text-yellow-700 {
    color: #856404;
}

.text-yellow-800 {
    color: #856404;
}

/* Table Responsive */
@media (max-width: 768px) {
    table {
        font-size: 0.875rem;
    }
    
    table th,
    table td {
        padding: 0.5rem;
    }
    
    .method-badge {
        font-size: 0.625rem;
        padding: 0.125rem 0.25rem;
    }
    
    .grid.grid-cols-1.lg\\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
    
    .modal-container {
        margin: 0.5rem;
    }
    
    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 1rem;
    }
    
    .modal-footer {
        flex-direction: column-reverse;
    }
    
    .modal-footer .btn {
        width: 100%;
        justify-content: center;
    }
    
    .toast {
        min-width: calc(100% - 3rem);
        max-width: calc(100% - 3rem);
        left: 1.5rem;
        right: 1.5rem;
    }
}

@media (max-width: 480px) {
    .stat-card .flex {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .stat-card .text-right {
        align-self: flex-end;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .action-btn {
        width: 100%;
        justify-content: center;
    }
    
    .modal-container {
        padding: 0;
    }
    
    .modal-content {
        border-radius: 0;
        min-height: 100vh;
    }
}

/* Animation for loading spinner */
@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.fa-spinner {
    animation: spin 1s linear infinite;
}

/* Badge styles for modals */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    line-height: 1;
}

.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    border: 1px solid rgba(var(--primary-rgb), 0.3);
}

.badge-success {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border: 1px solid rgba(var(--success-rgb), 0.3);
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border: 1px solid rgba(var(--warning-rgb), 0.3);
}

.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border: 1px solid rgba(var(--danger-rgb), 0.3);
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border: 1px solid rgba(var(--info-rgb), 0.3);
}

/* Button disabled state */
.btn:disabled,
.btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}

/* Form validation styles */
.form-input.error {
    border-color: var(--danger);
}

.form-input.success {
    border-color: var(--success);
}

.error-message {
    color: var(--danger);
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

.success-message {
    color: var(--success);
    font-size: 0.75rem;
    margin-top: 0.25rem;
}
</style>
@endpush