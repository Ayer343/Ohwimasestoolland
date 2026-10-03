{{-- developer/settings/index.blade.php — ALIGNED WITH DECOUPLED BILLING SETTINGS --}}
@extends('layouts.dev')

@php
    // ============================================================
    // Page meta & session flash
    // ============================================================
    $pageTitle       = 'Developer Settings - System Configuration';
    $successMessage  = session('success');
    $errorMessage    = session('error');
    $emailTestResult = session('email_test_result');
    $apiKeysGenerated = session('api_keys_generated');
    $billingUpdated  = session('billing_updated');
    $billingSettingsUpdated = session('billing_settings_updated');

    // ============================================================
    // Model / controller-provided data — defensive defaults
    // ============================================================
    $settings            = $settings            ?? new \App\Models\DeveloperSetting();
    $emailStatus         = $emailStatus         ?? [];
    $apiKeys             = $apiKeys             ?? [];
    $emailConfig         = $emailConfig         ?? [];
    $emailUpdateStatus   = $emailUpdateStatus   ?? [];
    $emailAudits         = $emailAudits         ?? collect();

    // ---------- BILLING DATA (from controller) ----------
    $superAdmins            = $superAdmins            ?? collect();
    $primaryAgreement       = $primaryAgreement       ?? null;
    $primarySuperAdmin      = $primarySuperAdmin      ?? null;
    $primaryBillingContact  = $primaryBillingContact  ?? null;
    $billingAgreements      = $billingAgreements      ?? collect();
    $recentInvoices         = $recentInvoices         ?? collect();
    $billingRules           = $billingRules           ?? [];
    $billingStats           = $billingStats           ?? [
        'total_agreements'       => 0,
        'active_agreements'      => 0,
        'pending_agreements'     => 0,
        'total_amount_agreed'    => 0,
        'total_amount_received'  => 0,
        'total_pending_payments' => 0,
    ];

    // Controller sends snake_case — normalise to camelCase
    $hasPrimarySuperAdmin = $hasPrimarySuperAdmin
        ?? $has_primary_super_admin
        ?? false;

    // ============================================================
    // Section routing
    // ============================================================
    $currentSection = $section ?? request()->get('section', 'general');
    if (!in_array($currentSection, ['general', 'email', 'billing', 'api', 'monitoring', 'analytics'], true)) {
        $currentSection = 'general';
    }

    // ============================================================
    // Email status — safe access with defaults
    // ============================================================
    $emailConnection     = $emailStatus['connection']     ?? false;
    $emailAuthentication = $emailStatus['authentication'] ?? false;
    $emailCanSend        = $emailStatus['can_send']       ?? false;

    // ============================================================
    // Parse JSON fields safely
    // ============================================================
    $allowedIps = is_array($settings->allowed_ips)
        ? $settings->allowed_ips
        : (is_string($settings->allowed_ips) ? (json_decode($settings->allowed_ips, true) ?: []) : []);
    $allowedIpsString = implode(', ', $allowedIps);

    $reportRecipients = is_array($settings->report_recipients)
        ? $settings->report_recipients
        : (is_string($settings->report_recipients) ? (json_decode($settings->report_recipients, true) ?: []) : []);
    $reportRecipientsString = implode(', ', $reportRecipients);

    // ============================================================
    // Billing label maps & defaults
    // ============================================================
    $billingCycleLabels = [
        'weekly'    => 'Weekly',
        'monthly'   => 'Monthly',
        'quarterly' => 'Quarterly',
        'yearly'    => 'Yearly',
    ];

    $autoGenerateInvoices = old('auto_generate_invoices', $billingRules['auto_generate_invoices'] ?? false);
    $sendPaymentReminders = old('send_payment_reminders', $billingRules['send_payment_reminders'] ?? false);
    $invoiceDueDays       = old('invoice_due_days', $billingRules['invoice_due_days'] ?? 30);
    $reminderDaysBefore   = old('reminder_days_before', $billingRules['reminder_days_before'] ?? [7, 3, 1]);
    if (!is_array($reminderDaysBefore)) {
        $reminderDaysBefore = [7, 3, 1];
    }

    $defaultSettings = [
        'cache_duration'       => config('developer.defaults.cache_duration', 3600),
        'max_upload_size'      => config('developer.defaults.max_upload_size', 2048),
        'session_timeout'      => config('developer.defaults.session_timeout', 120),
        'max_login_attempts'   => config('developer.defaults.max_login_attempts', 5),
        'password_expiry_days' => config('developer.defaults.password_expiry_days', 90),
        'log_retention_days'   => config('developer.defaults.log_retention_days', 90),
    ];
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    {{-- ============================================================
         HEADER
    ============================================================ --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-cog text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-cog mr-2" style="color: var(--primary);"></i>
                        Developer Settings Dashboard
                    </h2>
                    <div class="text-sm flex items-center mt-1 flex-wrap gap-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle"></i>
                        <span>System configuration and management</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-circle" style="color: var(--success); font-size: 0.5rem;"></i>
                        <span class="font-medium">Active</span>
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <div class="flex items-center space-x-2 mt-2 flex-wrap gap-2">
                    {{-- ✅ Theme toggle button REMOVED --}}
                    <a href="{{ route('developer.dashboard') }}"
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-dashboard mr-1"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         ALERTS
    ============================================================ --}}
    @if($successMessage)
    <div class="alert-success animate-slide-down" role="alert">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span class="font-bold">Success!</span>
            <span class="ml-2">{{ $successMessage }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if($errorMessage)
    <div class="alert-error animate-slide-down" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span class="font-bold">Error!</span>
            <span class="ml-2">{{ $errorMessage }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if($apiKeysGenerated)
    <div class="alert-warning animate-slide-down" role="alert">
        <div class="flex items-center">
            <i class="fas fa-key mr-2"></i>
            <span class="font-bold">New API Keys Generated!</span>
            <span class="ml-2">Copy and save your new API keys. They will not be shown again.</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if($billingUpdated)
    <div class="alert-success animate-slide-down" role="alert">
        <div class="flex items-center">
            <i class="fas fa-money-bill-wave mr-2"></i>
            <span class="font-bold">Billing updated!</span>
            <span class="ml-2">Agreements have been synced. Check the Billing Dashboard for details.</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if($billingSettingsUpdated)
    <div class="alert-success animate-slide-down" role="alert">
        <div class="flex items-center">
            <i class="fas fa-robot mr-2"></i>
            <span class="font-bold">Billing automation updated!</span>
            <span class="ml-2">Changes take effect on the next scheduled run.</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    {{-- ============================================================
         NAVIGATION + SEARCH
         ✅ Search field now uses same theme tokens as the rest
         ✅ Container adapts to light/dark without a hardcoded white
    ============================================================ --}}
    <div class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('developer.dashboard') }}"
                   class="inline-flex items-center text-sm font-medium"
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>

                @if(Route::has('developer.billing.dashboard'))
                <a href="{{ route('developer.billing.dashboard') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-money-bill-wave mr-1"></i> Billing Dashboard
                </a>
                @endif

                @if(Route::has('developer.monitoring.dashboard'))
                <a href="{{ route('developer.monitoring.dashboard') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-chart-line mr-1"></i> Monitoring
                </a>
                @endif

                @if(Route::has('developer.tools.dashboard'))
                <a href="{{ route('developer.tools.dashboard') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-tools mr-1"></i> Tools
                </a>
                @endif
            </div>

            {{-- ✅ Themed search wrapper --}}
            <div class="settings-search-wrapper">
                <input type="text"
                       id="settingsSearch"
                       placeholder="Search settings..."
                       class="settings-search-input"
                       autocomplete="off">
                <i class="fas fa-search settings-search-icon"></i>
            </div>
        </div>
    </div>

    {{-- ============================================================
         QUICK STATS
    ============================================================ --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="card stat-card hover-scale">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center pulse-animation"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-user-check text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Account Status</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ ucfirst($settings->status ?? 'active') }}</p>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->status == 'active' ? 'success' : ($settings->status == 'suspended' ? 'danger' : 'warning') }}">
                        <i class="fas fa-circle mr-1" style="font-size: 0.5rem;"></i>
                        {{ $settings->status == 'active' ? 'Active' : ucfirst($settings->status ?? 'unknown') }}
                    </span>
                </div>
            </div>
        </div>

        <div class="card stat-card hover-scale">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-envelope text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Email Status</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $emailCanSend ? 'Ready' : 'Setup' }}</p>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ $emailCanSend ? 'success' : 'danger' }}">
                        <i class="fas fa-{{ $emailCanSend ? 'check' : 'times' }} mr-1"></i>
                        {{ $emailCanSend ? 'Configured' : 'Not Configured' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="card stat-card hover-scale">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-key text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">API Status</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $settings->api_enabled ? 'On' : 'Off' }}</p>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->api_enabled ? 'success' : 'danger' }}">
                        <i class="fas fa-plug mr-1"></i>
                        {{ $settings->api_enabled ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="card stat-card hover-scale">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                        <i class="fas fa-money-bill-wave text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Billing Status</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $hasPrimarySuperAdmin ? 'Active' : 'Setup' }}
                    </p>
                    @if($hasPrimarySuperAdmin)
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            {{ number_format($billingStats['total_amount_received'] ?? 0, 2) }} {{ $settings->billing_currency ?? 'GHS' }} received
                        </p>
                    @endif
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ $hasPrimarySuperAdmin ? 'success' : 'warning' }}">
                        <i class="fas fa-{{ $hasPrimarySuperAdmin ? 'check-circle' : 'exclamation-circle' }} mr-1"></i>
                        {{ $hasPrimarySuperAdmin ? 'Configured' : 'Setup Needed' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         MAIN CONTENT
         ✅ Sidebar no longer sticky → fixes overlap with
            System Status / Quick Actions.
         ✅ All sidebar cards live in one column and scroll together.
    ============================================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        {{-- SIDEBAR --}}
        <div class="lg:col-span-1 settings-sidebar-column">
            <div class="card p-6 mb-6 settings-sidebar-card">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-cog mr-2" style="color: var(--primary);"></i> Settings Navigation
                </h3>

                <div class="space-y-2" id="settingsNav">
                    <a href="?section=general"
                       class="flex items-center p-3 rounded-lg transition-all duration-200 group tool-link {{ $currentSection == 'general' ? 'active-nav' : '' }}"
                       data-tool-link="true" data-section="general">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-user text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <span class="text-sm font-medium" style="color: var(--text-primary);">General Settings</span>
                        </div>
                        <i class="fas fa-chevron-right text-xs transition-transform duration-200" style="color: var(--text-secondary);"></i>
                    </a>

                    <a href="?section=email"
                       class="flex items-center p-3 rounded-lg transition-all duration-200 group tool-link {{ $currentSection == 'email' ? 'active-nav' : '' }}"
                       data-tool-link="true" data-section="email">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-envelope text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <span class="text-sm font-medium" style="color: var(--text-primary);">Email Configuration</span>
                            @if(!$emailCanSend)
                            <span class="text-xs ml-2 badge-warning">Incomplete</span>
                            @endif
                        </div>
                        <i class="fas fa-chevron-right text-xs transition-transform duration-200" style="color: var(--text-secondary);"></i>
                    </a>

                    <a href="?section=billing"
                       class="flex items-center p-3 rounded-lg transition-all duration-200 group tool-link {{ $currentSection == 'billing' ? 'active-nav' : '' }}"
                       data-tool-link="true" data-section="billing">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                            <i class="fas fa-robot text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <span class="text-sm font-medium" style="color: var(--text-primary);">Billing Automation</span>
                            @if(!$hasPrimarySuperAdmin)
                            <span class="text-xs ml-2 badge-warning">Setup needed</span>
                            @else
                            <span class="text-xs ml-2 badge-success">
                                {{ $primarySuperAdmin->name ?? 'Primary assigned' }}
                            </span>
                            @endif
                        </div>
                        <i class="fas fa-chevron-right text-xs transition-transform duration-200" style="color: var(--text-secondary);"></i>
                    </a>

                    <a href="?section=api"
                       class="flex items-center p-3 rounded-lg transition-all duration-200 group tool-link {{ $currentSection == 'api' ? 'active-nav' : '' }}"
                       data-tool-link="true" data-section="api">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-key text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <span class="text-sm font-medium" style="color: var(--text-primary);">API & Security</span>
                        </div>
                        <i class="fas fa-chevron-right text-xs transition-transform duration-200" style="color: var(--text-secondary);"></i>
                    </a>

                    <a href="?section=monitoring"
                       class="flex items-center p-3 rounded-lg transition-all duration-200 group tool-link {{ $currentSection == 'monitoring' ? 'active-nav' : '' }}"
                       data-tool-link="true" data-section="monitoring">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            <i class="fas fa-chart-line text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <span class="text-sm font-medium" style="color: var(--text-primary);">Monitoring Settings</span>
                        </div>
                        <i class="fas fa-chevron-right text-xs transition-transform duration-200" style="color: var(--text-secondary);"></i>
                    </a>

                    <a href="?section=analytics"
                       class="flex items-center p-3 rounded-lg transition-all duration-200 group tool-link {{ $currentSection == 'analytics' ? 'active-nav' : '' }}"
                       data-tool-link="true" data-section="analytics">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                            <i class="fas fa-chart-pie text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <span class="text-sm font-medium" style="color: var(--text-primary);">Analytics</span>
                        </div>
                        <i class="fas fa-chevron-right text-xs transition-transform duration-200" style="color: var(--text-secondary);"></i>
                    </a>
                </div>
            </div>

            {{-- System Status --}}
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-heartbeat mr-2" style="color: var(--success);"></i> System Status
                </h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Developer Access</span>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->developer_access_enabled ? 'success' : 'danger' }}">
                            {{ $settings->developer_access_enabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Monitoring</span>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->enable_system_monitoring ? 'success' : 'danger' }}">
                            {{ $settings->enable_system_monitoring ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Error Tracking</span>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->enable_error_tracking ? 'success' : 'danger' }}">
                            {{ $settings->enable_error_tracking ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">API Access</span>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->api_enabled ? 'success' : 'danger' }}">
                            {{ $settings->api_enabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Billing</span>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ $hasPrimarySuperAdmin ? 'success' : 'warning' }}">
                            {{ $hasPrimarySuperAdmin ? 'Configured' : 'Not Configured' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2" style="color: var(--warning);"></i> Quick Actions
                </h3>
                <div class="space-y-2">
                    @if(Route::has('developer.tools.clear-cache'))
                    <form method="POST" action="{{ route('developer.tools.clear-cache') }}" class="quick-action-form" data-confirm="Are you sure you want to clear all system cache?">
                        @csrf
                        <button type="submit" class="w-full text-left px-3 py-2 rounded-lg text-sm font-medium inline-flex items-center justify-between group"
                                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                            <span><i class="fas fa-broom mr-2"></i> Clear Cache</span>
                            <i class="fas fa-arrow-right opacity-0 group-hover:opacity-100 transition-opacity"></i>
                        </button>
                    </form>
                    @endif

                    @if(Route::has('developer.tools.optimize'))
                    <form method="POST" action="{{ route('developer.tools.optimize') }}" class="quick-action-form" data-confirm="Optimize system performance? This may take a moment.">
                        @csrf
                        <button type="submit" class="w-full text-left px-3 py-2 rounded-lg text-sm font-medium inline-flex items-center justify-between group"
                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                            <span><i class="fas fa-rocket mr-2"></i> Optimize System</span>
                            <i class="fas fa-arrow-right opacity-0 group-hover:opacity-100 transition-opacity"></i>
                        </button>
                    </form>
                    @endif

                    @if(Route::has('developer.tools.db-backup'))
                    <form method="POST" action="{{ route('developer.tools.db-backup') }}" class="quick-action-form" data-confirm="Create a database backup?">
                        @csrf
                        <button type="submit" class="w-full text-left px-3 py-2 rounded-lg text-sm font-medium inline-flex items-center justify-between group"
                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <span><i class="fas fa-save mr-2"></i> Backup Database</span>
                            <i class="fas fa-arrow-right opacity-0 group-hover:opacity-100 transition-opacity"></i>
                        </button>
                    </form>
                    @endif

                    @if(Route::has('developer.billing.dashboard'))
                    <a href="{{ route('developer.billing.dashboard') }}"
                       class="w-full text-left px-3 py-2 rounded-lg text-sm font-medium inline-flex items-center justify-between group"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <span><i class="fas fa-money-bill-wave mr-2"></i> Billing Dashboard</span>
                        <i class="fas fa-arrow-right opacity-0 group-hover:opacity-100 transition-opacity"></i>
                    </a>
                    @endif
                </div>
            </div>

            {{-- Recent Email Activity --}}
            @if($emailAudits->count() > 0)
            <div class="card p-6 mt-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-envelope-open-text mr-2" style="color: var(--info);"></i> Recent Email Activity
                </h3>
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    @foreach($emailAudits as $audit)
                    <div class="text-sm p-2 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <div class="flex justify-between items-start">
                            <span class="font-medium" style="color: var(--text-primary);">
                                {{ ucfirst(str_replace('_', ' ', $audit->action ?? 'activity')) }}
                            </span>
                            <span class="text-xs" style="color: var(--text-secondary);">
                                {{ optional($audit->created_at)->diffForHumans() }}
                            </span>
                        </div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            {{ Str::limit($audit->message ?? '', 80) }}
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- CONTENT --}}
        <div class="lg:col-span-2">

            {{-- ==================================================================
                 GENERAL
            ================================================================== --}}
            @if($currentSection == 'general')
                <form method="POST" action="{{ route('developer.settings.update') }}" class="space-y-6" id="generalSettingsForm" data-section="general">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="section" value="general">

                    <div class="card p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-user mr-2" style="color: var(--primary);"></i> Developer Information
                            </h3>
                            <button type="button" onclick="resetToDefaults('general')"
                                    class="text-xs px-2 py-1 rounded"
                                    style="color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                <i class="fas fa-undo-alt mr-1"></i> Reset to Defaults
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Full Name *</label>
                                <input type="text" name="developer_name"
                                       value="{{ old('developer_name', $settings->developer_name) }}"
                                       class="form-input w-full p-3 rounded-lg border focus-ring"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       required data-validation="required">
                                @error('developer_name')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Email Address *</label>
                                <input type="email" name="developer_email"
                                       value="{{ old('developer_email', $settings->developer_email) }}"
                                       class="form-input w-full p-3 rounded-lg border focus-ring"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       required data-validation="email">
                                @error('developer_email')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Phone Number</label>
                                <input type="tel" name="developer_phone"
                                       value="{{ old('developer_phone', $settings->developer_phone) }}"
                                       class="form-input w-full p-3 rounded-lg border focus-ring"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       data-validation="phone">
                                @error('developer_phone')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Company</label>
                                <input type="text" name="developer_company"
                                       value="{{ old('developer_company', $settings->developer_company) }}"
                                       class="form-input w-full p-3 rounded-lg border focus-ring"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                                @error('developer_company')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Website</label>
                                <input type="url" name="developer_website"
                                       value="{{ old('developer_website', $settings->developer_website) }}"
                                       class="form-input w-full p-3 rounded-lg border focus-ring"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       data-validation="url">
                                @error('developer_website')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                            </div>

                            <div class="md:col-span-2">
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Address</label>
                                <textarea name="developer_address"
                                          class="form-input w-full p-3 rounded-lg border focus-ring"
                                          style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary); min-height: 80px; resize: vertical;">{{ old('developer_address', $settings->developer_address) }}</textarea>
                                @error('developer_address')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="card p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-lock mr-2" style="color: var(--warning);"></i> System Access
                        </h3>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 rounded-lg setting-item" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">Developer Access</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Enable or disable your developer account access</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="hidden" name="developer_access_enabled" value="0">
                                    <input type="checkbox" name="developer_access_enabled" value="1" class="toggle-checkbox"
                                           {{ old('developer_access_enabled', $settings->developer_access_enabled) ? 'checked' : '' }}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Allowed IP Addresses</label>
                                <textarea name="allowed_ips"
                                          class="form-input w-full p-3 rounded-lg border focus-ring"
                                          style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary); min-height: 80px; resize: vertical;"
                                          placeholder="Enter comma-separated IP addresses (e.g., 192.168.1.1, 10.0.0.1)"
                                          data-validation="ips">{{ old('allowed_ips', $allowedIpsString) }}</textarea>
                                <p class="mt-1 text-sm" style="color: var(--text-secondary);">Restrict access to specific IP addresses. Leave empty to allow all.</p>
                                @error('allowed_ips')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Account Status *</label>
                                <select name="status" class="form-select w-full p-3 rounded-lg border focus-ring"
                                        style="background-color: var(--card-bg); color: var(--text-primary); border-color: var(--border-color);" required>
                                    <option value="active"    {{ old('status', $settings->status) == 'active'    ? 'selected' : '' }}>Active</option>
                                    <option value="inactive"  {{ old('status', $settings->status) == 'inactive'  ? 'selected' : '' }}>Inactive</option>
                                    <option value="suspended" {{ old('status', $settings->status) == 'suspended' ? 'selected' : '' }}>Suspended</option>
                                </select>
                                @error('status')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="card p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-tachometer-alt mr-2" style="color: var(--info);"></i> Performance Settings
                            </h3>
                            <button type="button" onclick="resetPerformanceDefaults()"
                                    class="text-xs px-2 py-1 rounded"
                                    style="color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                <i class="fas fa-undo-alt mr-1"></i> Reset to Defaults
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Cache Duration (seconds)</label>
                                <input type="number" name="cache_duration" id="cache_duration"
                                       value="{{ old('cache_duration', $settings->cache_duration ?? $defaultSettings['cache_duration']) }}"
                                       class="form-input w-full p-3 rounded-lg border focus-ring"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       min="60" max="86400" step="60">
                                <div class="mt-1 flex gap-2">
                                    <button type="button" onclick="setPreset('cache', 3600)"  class="text-xs px-2 py-0.5 rounded">1 hour</button>
                                    <button type="button" onclick="setPreset('cache', 21600)" class="text-xs px-2 py-0.5 rounded">6 hours</button>
                                    <button type="button" onclick="setPreset('cache', 86400)" class="text-xs px-2 py-0.5 rounded">24 hours</button>
                                </div>
                                @error('cache_duration')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Max Upload Size (MB)</label>
                                <input type="number" name="max_upload_size"
                                       value="{{ old('max_upload_size', $settings->max_upload_size ?? $defaultSettings['max_upload_size']) }}"
                                       class="form-input w-full p-3 rounded-lg border focus-ring"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       min="100" max="10240" step="100">
                                <div class="mt-1 flex gap-2">
                                    <button type="button" onclick="setPreset('upload', 512)"  class="text-xs px-2 py-0.5 rounded">512 MB</button>
                                    <button type="button" onclick="setPreset('upload', 2048)" class="text-xs px-2 py-0.5 rounded">2 GB</button>
                                    <button type="button" onclick="setPreset('upload', 5120)" class="text-xs px-2 py-0.5 rounded">5 GB</button>
                                </div>
                                @error('max_upload_size')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Max Execution Time (seconds)</label>
                                <input type="number" name="max_execution_time"
                                       value="{{ old('max_execution_time', $settings->max_execution_time ?? 300) }}"
                                       class="form-input w-full p-3 rounded-lg border focus-ring"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       min="30" max="3600" step="30">
                                @error('max_execution_time')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                            </div>

                            <div class="flex items-center justify-between p-4 rounded-lg setting-item" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">Query Caching</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Enable database query caching</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="hidden" name="enable_query_cache" value="0">
                                    <input type="checkbox" name="enable_query_cache" value="1" class="toggle-checkbox"
                                           {{ old('enable_query_cache', $settings->enable_query_cache) ? 'checked' : '' }}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="card p-6">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                            <div class="flex items-center gap-3">
                                <div id="saveIndicator" class="hidden flex items-center gap-2">
                                    <i class="fas fa-spinner fa-spin" style="color: var(--primary);"></i>
                                    <span class="text-sm" style="color: var(--text-secondary);">Saving changes...</span>
                                </div>
                                <div id="saveSuccess" class="hidden flex items-center gap-2">
                                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                    <span class="text-sm" style="color: var(--success);">Changes saved!</span>
                                </div>
                                <div id="saveError" class="hidden flex items-center gap-2">
                                    <i class="fas fa-exclamation-circle" style="color: var(--danger);"></i>
                                    <span class="text-sm" style="color: var(--danger);">Save failed. Please try again.</span>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <button type="button"
                                        onclick="validateAndSubmitForm('generalSettingsForm')"
                                        class="btn-primary px-6 py-3 rounded-lg font-medium text-white inline-flex items-center group">
                                    <i class="fas fa-save mr-2 group-hover:scale-110 transition-transform"></i>
                                    Save General Settings
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

            {{-- ==================================================================
                 EMAIL
            ================================================================== --}}
            @elseif($currentSection == 'email')
                <div class="card p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-envelope mr-2" style="color: var(--info);"></i> Developer Email Configuration
                    </h3>

                    <div class="p-4 rounded-lg mb-6" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mt-1 mr-3" style="color: var(--info);"></i>
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">Separate Email System</p>
                                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                    This configuration is for <strong>developer-specific emails only</strong>.
                                    It does NOT affect the main system email configuration.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="card p-6 mb-6">
                        <h4 class="text-md font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i> Email Configuration Status
                        </h4>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="p-4 rounded-lg status-card" data-status="{{ $emailConnection ? 'success' : 'danger' }}"
                                 style="{{ $emailConnection ? 'background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);' : 'background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);' }}">
                                <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Connection Status</div>
                                <div class="text-lg font-semibold mb-1" style="{{ $emailConnection ? 'color: var(--success);' : 'color: var(--danger);' }}">
                                    {{ $emailConnection ? 'Connected' : 'Not Connected' }}
                                </div>
                            </div>

                            <div class="p-4 rounded-lg status-card" data-status="{{ $emailAuthentication ? 'success' : 'danger' }}"
                                 style="{{ $emailAuthentication ? 'background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);' : 'background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);' }}">
                                <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Authentication</div>
                                <div class="text-lg font-semibold mb-1" style="{{ $emailAuthentication ? 'color: var(--success);' : 'color: var(--danger);' }}">
                                    {{ $emailAuthentication ? 'Authenticated' : 'Failed' }}
                                </div>
                            </div>

                            <div class="p-4 rounded-lg status-card" data-status="{{ $emailCanSend ? 'success' : 'danger' }}"
                                 style="{{ $emailCanSend ? 'background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);' : 'background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);' }}">
                                <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Sending Capability</div>
                                <div class="text-lg font-semibold mb-1" style="{{ $emailCanSend ? 'color: var(--success);' : 'color: var(--danger);' }}">
                                    {{ $emailCanSend ? 'Can Send' : 'Cannot Send' }}
                                </div>
                            </div>
                        </div>

                        @if($emailTestResult)
                        <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <div class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Last Test Results:</div>
                            <div class="flex items-center mb-1" style="{{ $emailTestResult['success'] ? 'color: var(--success);' : 'color: var(--danger);' }}">
                                <i class="fas fa-{{ $emailTestResult['success'] ? 'check-circle' : 'exclamation-circle' }} mr-2"></i>
                                {{ $emailTestResult['message'] }}
                            </div>
                        </div>
                        @endif

                        <div class="mt-4 flex gap-3">
                            <button type="button" onclick="showEmailTestModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-vial mr-2"></i> Test Configuration
                            </button>
                            <button type="button" onclick="sendTestEmail()" class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-paper-plane mr-2"></i> Send Test Email
                            </button>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('developer.settings.email.update') }}" class="space-y-6" id="emailSettingsForm" data-section="email">
                        @csrf

                        <div class="card p-6">
                            <h4 class="text-md font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-server mr-2" style="color: var(--info);"></i> Developer SMTP Server Configuration
                            </h4>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                        <i class="fas fa-network-wired mr-1"></i> SMTP Host *
                                    </label>
                                    <input type="text" name="developer_mail_host"
                                           value="{{ old('developer_mail_host', $settings->developer_smtp_host) }}"
                                           class="form-input w-full p-3 rounded-lg border focus-ring"
                                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                           placeholder="smtp.gmail.com" data-validation="required" required>
                                    @error('developer_mail_host')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                        <i class="fas fa-signal mr-1"></i> SMTP Port *
                                    </label>
                                    <input type="number" name="developer_mail_port"
                                           value="{{ old('developer_mail_port', $settings->developer_smtp_port) }}"
                                           class="form-input w-full p-3 rounded-lg border focus-ring"
                                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                           placeholder="587" min="1" max="65535" required>
                                    @error('developer_mail_port')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                        <i class="fas fa-user mr-1"></i> SMTP Username *
                                    </label>
                                    <input type="email" name="developer_mail_username"
                                           value="{{ old('developer_mail_username', $settings->developer_smtp_username) }}"
                                           class="form-input w-full p-3 rounded-lg border focus-ring"
                                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                           placeholder="your-email@gmail.com" required>
                                    @error('developer_mail_username')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                        <i class="fas fa-key mr-1"></i> SMTP Password *
                                    </label>
                                    <div class="relative">
                                        <input type="password" name="developer_mail_password" id="developer_smtp_password"
                                               class="form-input w-full p-3 rounded-lg border pr-10 focus-ring"
                                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                               placeholder="{{ $settings->developer_smtp_password ? '••••••••' : 'Enter new password' }}"
                                               autocomplete="new-password" required>
                                        <button type="button" onclick="togglePasswordVisibility('developer_smtp_password', this)"
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center"
                                                style="color: var(--text-secondary);">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <p class="mt-1 text-xs" style="color: var(--text-secondary);">Password is encrypted and stored securely.</p>
                                    @error('developer_mail_password')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="card p-6">
                            <h4 class="text-md font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-cog mr-2" style="color: var(--primary);"></i> Developer Email Settings
                            </h4>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                        <i class="fas fa-lock mr-1"></i> Encryption *
                                    </label>
                                    <select name="developer_mail_encryption"
                                            class="form-select w-full p-3 rounded-lg border focus-ring"
                                            style="background-color: var(--card-bg); color: var(--text-primary); border-color: var(--border-color);" required>
                                        <option value="tls"  {{ old('developer_mail_encryption', $settings->developer_smtp_encryption) == 'tls'  ? 'selected' : '' }}>TLS (Recommended)</option>
                                        <option value="ssl"  {{ old('developer_mail_encryption', $settings->developer_smtp_encryption) == 'ssl'  ? 'selected' : '' }}>SSL</option>
                                        <option value="none" {{ old('developer_mail_encryption', $settings->developer_smtp_encryption) == 'none' ? 'selected' : '' }}>None (Not Recommended)</option>
                                    </select>
                                    @error('developer_mail_encryption')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                        <i class="fas fa-at mr-1"></i> From Email *
                                    </label>
                                    <input type="email" name="developer_mail_from_address"
                                           value="{{ old('developer_mail_from_address', $settings->developer_email_from) }}"
                                           class="form-input w-full p-3 rounded-lg border focus-ring"
                                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                           placeholder="developer@yourdomain.com" required>
                                    @error('developer_mail_from_address')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                        <i class="fas fa-user-tag mr-1"></i> From Name *
                                    </label>
                                    <input type="text" name="developer_mail_from_name"
                                           value="{{ old('developer_mail_from_name', $settings->developer_email_from_name) }}"
                                           class="form-input w-full p-3 rounded-lg border focus-ring"
                                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                           placeholder="Developer System" required>
                                    @error('developer_mail_from_name')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="card p-6">
                            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm" style="color: var(--text-secondary);">
                                        <i class="fas fa-shield-alt mr-1"></i> Developer email settings are stored securely
                                    </p>
                                </div>
                                <div class="flex gap-3">
                                    <button type="button" onclick="resetEmailForm()" class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                        <i class="fas fa-redo mr-2"></i> Reset Form
                                    </button>
                                    <button type="submit" class="btn-primary px-4 py-3 rounded-lg font-medium text-white inline-flex items-center group">
                                        <i class="fas fa-save mr-2 group-hover:scale-110 transition-transform"></i>
                                        Save Configuration
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

            {{-- ==================================================================
                 BILLING — AUTOMATION ONLY
            ================================================================== --}}
            @elseif($currentSection == 'billing')

                @if($superAdmins->isEmpty())
                    <div class="card p-8 text-center">
                        <i class="fas fa-users-slash text-4xl mb-4" style="color: var(--warning);"></i>
                        <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Active Super Admins</h3>
                        <p class="text-sm mb-4" style="color: var(--text-secondary);">
                            At least one active super admin must exist before billing can be configured.
                        </p>
                    </div>
                @else

                    <div class="card p-6 mb-6">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Billing Overview
                            </h3>
                            @if($hasPrimarySuperAdmin)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium badge-success">
                                    <i class="fas fa-check-circle mr-1"></i> Active
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium badge-warning">
                                    <i class="fas fa-exclamation-circle mr-1"></i> Setup Needed
                                </span>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div class="p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Primary Super Admin</p>
                                @if($primarySuperAdmin)
                                    <p class="text-base font-semibold" style="color: var(--text-primary);">{{ $primarySuperAdmin->name }}</p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $primaryBillingContact['email'] ?? $primarySuperAdmin->email }}
                                    </p>
                                @else
                                    <p class="text-base font-semibold" style="color: var(--warning);">Not assigned</p>
                                    @if(Route::has('developer.billing.dashboard'))
                                        <a href="{{ route('developer.billing.dashboard') }}"
                                           class="text-xs mt-1 inline-block"
                                           style="color: var(--primary);">
                                            Go to Billing Dashboard to assign one →
                                        </a>
                                    @endif
                                @endif
                            </div>

                            <div class="p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid rgba(var(--secondary-rgb), 0.1);">
                                <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Agreed Amount</p>
                                <p class="text-base font-semibold" style="color: var(--text-primary);">
                                    {{ number_format($primaryAgreement->amount ?? 0, 2) }}
                                    {{ $primaryAgreement->currency ?? $settings->billing_currency ?? 'GHS' }}
                                    <span class="text-xs font-normal" style="color: var(--text-secondary);">
                                        / {{ $billingCycleLabels[$primaryAgreement->billing_frequency ?? $settings->billing_cycle ?? 'monthly'] ?? 'monthly' }}
                                    </span>
                                </p>
                                @if(Route::has('developer.billing.agreements-list'))
                                    <a href="{{ route('developer.billing.agreements-list') }}"
                                       class="text-xs mt-1 inline-block"
                                       style="color: var(--primary);">
                                        Manage agreements →
                                    </a>
                                @endif
                            </div>

                            <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                                <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Next Billing Date</p>
                                <p class="text-base font-semibold" style="color: var(--text-primary);">
                                    {{ $settings->next_billing_date ? \Carbon\Carbon::parse($settings->next_billing_date)->format('M j, Y') : '—' }}
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-4" style="border-top: 1px solid var(--border-color);">
                            <div>
                                <p class="text-xs" style="color: var(--text-secondary);">Agreements</p>
                                <p class="text-lg font-bold" style="color: var(--text-primary);">{{ $billingStats['total_agreements'] }}</p>
                            </div>
                            <div>
                                <p class="text-xs" style="color: var(--text-secondary);">Active</p>
                                <p class="text-lg font-bold" style="color: var(--success);">{{ $billingStats['active_agreements'] }}</p>
                            </div>
                            <div>
                                <p class="text-xs" style="color: var(--text-secondary);">Total Agreed</p>
                                <p class="text-lg font-bold" style="color: var(--text-primary);">{{ number_format($billingStats['total_amount_agreed'], 2) }}</p>
                            </div>
                            <div>
                                <p class="text-xs" style="color: var(--text-secondary);">Total Received</p>
                                <p class="text-lg font-bold" style="color: var(--success);">{{ number_format($billingStats['total_amount_received'], 2) }}</p>
                            </div>
                        </div>
                    </div>

                    <form method="POST"
                          action="{{ route('developer.settings.billing.update') }}"
                          class="space-y-6"
                          id="billingSettingsForm"
                          data-section="billing">
                        @csrf
                        @method('PUT')

                        <input type="hidden" name="section" value="billing">

                        <div class="card p-6">
                            <h3 class="text-lg font-semibold mb-2 flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-robot mr-2" style="color: var(--warning);"></i> Billing Automation
                            </h3>
                            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                Control how invoices and reminders are generated.
                                Agreement amounts, cycles, and the primary super admin are managed on the
                                @if(Route::has('developer.billing.dashboard'))
                                    <a href="{{ route('developer.billing.dashboard') }}"
                                       style="color: var(--primary); text-decoration: underline;">Billing Dashboard</a>.
                                @else
                                    Billing Dashboard.
                                @endif
                            </p>

                            <div class="space-y-4">

                                <div class="flex items-center justify-between p-4 rounded-lg setting-item"
                                     style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                    <div>
                                        <p class="font-medium" style="color: var(--text-primary);">Auto-generate Invoices</p>
                                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                            Automatically create an invoice each billing cycle for the primary super admin.
                                        </p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="hidden" name="auto_generate_invoices" value="0">
                                        <input type="checkbox" name="auto_generate_invoices" value="1" class="toggle-checkbox"
                                               {{ $autoGenerateInvoices ? 'checked' : '' }}>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>

                                <div class="flex items-center justify-between p-4 rounded-lg setting-item"
                                     style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                    <div>
                                        <p class="font-medium" style="color: var(--text-primary);">Send Payment Reminders</p>
                                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                            Email the super admin before each invoice's due date.
                                        </p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="hidden" name="send_payment_reminders" value="0">
                                        <input type="checkbox" name="send_payment_reminders" value="1" class="toggle-checkbox"
                                               {{ $sendPaymentReminders ? 'checked' : '' }}>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                        Invoice Due Days
                                    </label>
                                    <input type="number" name="invoice_due_days"
                                           value="{{ $invoiceDueDays }}"
                                           class="form-input w-full p-3 rounded-lg border focus-ring"
                                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                           min="1" max="90">
                                    <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                        Days between invoice issue date and due date.
                                    </p>
                                    @error('invoice_due_days')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                        Reminder Days Before Due
                                    </label>
                                    <div class="flex gap-3 flex-wrap">
                                        @foreach([1, 3, 5, 7, 14, 30] as $day)
                                            <label class="inline-flex items-center px-3 py-1 rounded-lg cursor-pointer"
                                                   style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                                                <input type="checkbox"
                                                       name="reminder_days_before[]"
                                                       value="{{ $day }}"
                                                       class="form-checkbox mr-2"
                                                       {{ in_array($day, $reminderDaysBefore) ? 'checked' : '' }}>
                                                <span class="text-sm" style="color: var(--text-primary);">{{ $day }}d</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                                        Each selected day produces one reminder email.
                                        Leave empty to use the default (7, 3, 1).
                                    </p>
                                    @error('reminder_days_before')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                                </div>

                            </div>
                        </div>

                        <div class="card p-6">
                            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                                <div class="text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Changes take effect on the next scheduled run.
                                </div>
                                <div class="flex gap-3">
                                    @if(Route::has('developer.billing.dashboard'))
                                    <a href="{{ route('developer.billing.dashboard') }}"
                                       class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                        <i class="fas fa-money-bill-wave mr-2"></i> Billing Dashboard
                                    </a>
                                    @endif
                                    <button type="submit"
                                            class="btn-primary px-6 py-3 rounded-lg font-medium text-white inline-flex items-center group">
                                        <i class="fas fa-save mr-2 group-hover:scale-110 transition-transform"></i>
                                        Save Automation Settings
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    @if($recentInvoices->count() > 0)
                    <div class="card p-6 mt-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-file-invoice mr-2" style="color: var(--info);"></i> Recent Invoices
                            </h3>
                            @if(Route::has('developer.billing.invoices'))
                                <a href="{{ route('developer.billing.invoices') }}" class="text-xs" style="color: var(--primary);">
                                    View all <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            @endif
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <th class="text-left py-2" style="color: var(--text-secondary);">Invoice</th>
                                        <th class="text-right py-2" style="color: var(--text-secondary);">Amount</th>
                                        <th class="text-center py-2" style="color: var(--text-secondary);">Status</th>
                                        <th class="text-right py-2" style="color: var(--text-secondary);">Due</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentInvoices as $invoice)
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td class="py-2" style="color: var(--text-primary);">{{ $invoice->invoice_number ?? $invoice->id }}</td>
                                        <td class="py-2 text-right" style="color: var(--text-primary);">
                                            {{ number_format((float) ($invoice->amount ?? 0), 2) }}
                                            {{ $invoice->currency ?? $settings->billing_currency ?? 'GHS' }}
                                        </td>
                                        <td class="py-2 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-{{
                                                ($invoice->status ?? '') === 'paid' ? 'success' :
                                                (($invoice->status ?? '') === 'overdue' ? 'danger' : 'warning')
                                            }}">
                                                {{ ucfirst($invoice->status ?? 'unknown') }}
                                            </span>
                                        </td>
                                        <td class="py-2 text-right" style="color: var(--text-secondary);">
                                            {{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('M j, Y') : '—' }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                @endif

            {{-- ==================================================================
                 API & SECURITY
            ================================================================== --}}
            @elseif($currentSection == 'api')
                <div class="card p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-key mr-2" style="color: var(--warning);"></i> API Keys
                    </h3>

                    @if($apiKeysGenerated)
                        <div class="p-4 rounded-lg mb-4 animate-pulse-once"
                             style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <div class="flex items-start">
                                <i class="fas fa-key mr-3 mt-1" style="color: var(--warning);"></i>
                                <div class="flex-1">
                                    <p class="font-bold" style="color: var(--text-primary);">New API Keys Generated!</p>
                                    <p class="text-sm mt-1" style="color: var(--warning);">Save these keys securely. They will not be shown again.</p>
                                    <div class="mt-3 space-y-2">
                                        <div class="p-3 rounded-lg" style="background-color: rgba(0,0,0,0.05);">
                                            <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">API Key:</p>
                                            <code class="text-sm font-mono break-all" style="color: var(--text-primary);" id="newApiKey">{{ $apiKeysGenerated['api_key'] }}</code>
                                            <button onclick="copyToClipboard('newApiKey')" class="ml-2 text-xs px-2 py-1 rounded">
                                                <i class="fas fa-copy"></i> Copy
                                            </button>
                                        </div>
                                        <div class="p-3 rounded-lg" style="background-color: rgba(0,0,0,0.05);">
                                            <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Secret Key:</p>
                                            <code class="text-sm font-mono break-all" style="color: var(--text-primary);" id="newSecretKey">{{ $apiKeysGenerated['secret_key'] }}</code>
                                            <button onclick="copyToClipboard('newSecretKey')" class="ml-2 text-xs px-2 py-1 rounded">
                                                <i class="fas fa-copy"></i> Copy
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-xs mt-3" style="color: var(--danger);">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Copy these keys now. They cannot be recovered later.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="space-y-6">
                        <div class="p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                            <div class="flex flex-col md:flex-row md:items-center justify-between mb-3">
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">API Access Status</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        {{ ($apiKeys['has_api_key'] ?? false) ? 'API keys are configured' : 'No API keys configured' }}
                                    </p>
                                </div>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium mt-2 md:mt-0 badge-{{ ($apiKeys['api_enabled'] ?? false) ? 'success' : 'danger' }}">
                                    {{ ($apiKeys['api_enabled'] ?? false) ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-3">
                                <form method="POST" action="{{ route('developer.api.generate-keys') }}" class="inline" id="generateKeysForm" data-confirm="⚠️ Warning: Generating new API keys will invalidate your current keys. Are you sure you want to continue?">
                                    @csrf
                                    <button type="submit" class="btn-warning px-4 py-3 rounded-lg font-medium inline-flex items-center">
                                        <i class="fas fa-redo mr-2"></i> Generate New Keys
                                    </button>
                                </form>

                                <button type="button" onclick="showApiDocumentation()" class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                    <i class="fas fa-book mr-2"></i> API Documentation
                                </button>
                            </div>
                        </div>

                        <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                            <h4 class="font-medium mb-3" style="color: var(--text-primary);">API Information</h4>
                            <div class="space-y-3">
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <span class="text-sm" style="color: var(--text-secondary);">Base URL:</span>
                                    <code class="text-sm font-mono mt-1 md:mt-0 break-all md:text-right" style="color: var(--text-primary);" id="apiBaseUrl">{{ $apiKeys['base_url'] ?? config('app.url') . '/api/developer' }}</code>
                                    <button onclick="copyToClipboard('apiBaseUrl')" class="ml-2 text-xs px-2 py-1 rounded">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <span class="text-sm" style="color: var(--text-secondary);">Keys Generated:</span>
                                    <span class="text-sm mt-1 md:mt-0" style="color: var(--text-primary);">
                                        {{ ($apiKeys['has_api_key'] ?? false) ? 'Yes' : 'No' }}
                                    </span>
                                </div>
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <span class="text-sm" style="color: var(--text-secondary);">Last Generated:</span>
                                    <span class="text-sm mt-1 md:mt-0" style="color: var(--text-primary);">
                                        @if(!empty($apiKeys['last_generated']))
                                            {{ \Carbon\Carbon::parse($apiKeys['last_generated'])->format('M d, Y H:i') }}
                                        @else
                                            Never
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('developer.security.update') }}" class="space-y-6" id="securitySettingsForm" data-section="security">
                    @csrf

                    <div class="card p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-shield-alt mr-2" style="color: var(--success);"></i> Security Settings
                            </h3>
                            <button type="button" onclick="resetSecurityDefaults()"
                                    class="text-xs px-2 py-1 rounded"
                                    style="color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                <i class="fas fa-undo-alt mr-1"></i> Reset to Defaults
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 rounded-lg setting-item" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">Two-Factor Authentication</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Require 2FA for developer account access</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="hidden" name="enable_two_factor" value="0">
                                    <input type="checkbox" name="enable_two_factor" value="1" class="toggle-checkbox"
                                           {{ old('enable_two_factor', $settings->enable_two_factor) ? 'checked' : '' }}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Session Timeout (minutes)</label>
                                <input type="number" name="session_timeout" id="session_timeout"
                                       value="{{ old('session_timeout', $settings->session_timeout ?? $defaultSettings['session_timeout']) }}"
                                       class="form-input w-full p-3 rounded-lg border focus-ring"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       min="5" max="1440" step="5">
                                <div class="mt-1 flex gap-2">
                                    <button type="button" onclick="setPreset('session', 30)"  class="text-xs px-2 py-0.5 rounded">30 min</button>
                                    <button type="button" onclick="setPreset('session', 60)"  class="text-xs px-2 py-0.5 rounded">1 hour</button>
                                    <button type="button" onclick="setPreset('session', 120)" class="text-xs px-2 py-0.5 rounded">2 hours</button>
                                    <button type="button" onclick="setPreset('session', 480)" class="text-xs px-2 py-0.5 rounded">8 hours</button>
                                </div>
                                @error('session_timeout')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Max Login Attempts</label>
                                <input type="number" name="max_login_attempts"
                                       value="{{ old('max_login_attempts', $settings->max_login_attempts ?? $defaultSettings['max_login_attempts']) }}"
                                       class="form-input w-full p-3 rounded-lg border focus-ring"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       min="1" max="20">
                                @error('max_login_attempts')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Password Expiry (days)</label>
                                <input type="number" name="password_expiry_days"
                                       value="{{ old('password_expiry_days', $settings->password_expiry_days ?? $defaultSettings['password_expiry_days']) }}"
                                       class="form-input w-full p-3 rounded-lg border focus-ring"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       min="0" max="365">
                                @error('password_expiry_days')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="mt-6 pt-4" style="border-top: 1px solid var(--border-color);">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-sm" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Security settings affect both web and API access.
                                    </p>
                                </div>
                                <button type="submit" class="btn-primary px-4 py-3 rounded-lg font-medium text-white inline-flex items-center group">
                                    <i class="fas fa-save mr-2 group-hover:scale-110 transition-transform"></i>
                                    Save Security Settings
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

            {{-- ==================================================================
                 MONITORING
            ================================================================== --}}
            @elseif($currentSection == 'monitoring')
                <div class="card p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-line mr-2" style="color: var(--info);"></i> Monitoring & Error Tracking
                    </h3>

                    <form method="POST" action="{{ route('developer.settings.update') }}" class="space-y-6" id="monitoringSettingsForm" data-section="monitoring">
                        @csrf
                        @method('PUT')

                        <input type="hidden" name="section" value="monitoring">

                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 rounded-lg setting-item" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">System Monitoring</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Enable system monitoring and health checks</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="hidden" name="enable_system_monitoring" value="0">
                                    <input type="checkbox" name="enable_system_monitoring" value="1" class="toggle-checkbox"
                                           {{ old('enable_system_monitoring', $settings->enable_system_monitoring) ? 'checked' : '' }}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="flex items-center justify-between p-4 rounded-lg setting-item" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">Error Tracking</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Track and log system errors</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="hidden" name="enable_error_tracking" value="0">
                                    <input type="checkbox" name="enable_error_tracking" value="1" class="toggle-checkbox"
                                           {{ old('enable_error_tracking', $settings->enable_error_tracking) ? 'checked' : '' }}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="flex items-center justify-between p-4 rounded-lg setting-item" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">Performance Monitoring</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Monitor system performance metrics</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="hidden" name="enable_performance_monitoring" value="0">
                                    <input type="checkbox" name="enable_performance_monitoring" value="1" class="toggle-checkbox"
                                           {{ old('enable_performance_monitoring', $settings->enable_performance_monitoring) ? 'checked' : '' }}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Log Retention (days)</label>
                                <input type="number" name="log_retention_days"
                                       value="{{ old('log_retention_days', $settings->log_retention_days ?? $defaultSettings['log_retention_days']) }}"
                                       class="form-input w-full p-3 rounded-lg border focus-ring"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       min="1" max="3650" step="7">
                                <div class="mt-1 flex gap-2">
                                    <button type="button" onclick="setPreset('log_retention', 30)"  class="text-xs px-2 py-0.5 rounded">30 days</button>
                                    <button type="button" onclick="setPreset('log_retention', 90)"  class="text-xs px-2 py-0.5 rounded">90 days</button>
                                    <button type="button" onclick="setPreset('log_retention', 180)" class="text-xs px-2 py-0.5 rounded">180 days</button>
                                    <button type="button" onclick="setPreset('log_retention', 365)" class="text-xs px-2 py-0.5 rounded">1 year</button>
                                </div>
                                @error('log_retention_days')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Report Recipients</label>
                                <textarea name="report_recipients" id="report_recipients"
                                          class="form-input w-full p-3 rounded-lg border focus-ring"
                                          style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary); min-height: 80px; resize: vertical;"
                                          placeholder="Enter comma-separated email addresses for reports"
                                          data-validation="emails">{{ old('report_recipients', $reportRecipientsString) }}</textarea>
                                @error('report_recipients')<p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="mt-6 pt-4" style="border-top: 1px solid var(--border-color);">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Monitoring settings affect system performance tracking and error reporting.
                                    </p>
                                </div>
                                <div class="flex gap-3">
                                    @if(Route::has('developer.monitoring.dashboard'))
                                    <a href="{{ route('developer.monitoring.dashboard') }}" class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                        <i class="fas fa-chart-bar mr-2"></i> View Dashboard
                                    </a>
                                    @endif
                                    <button type="submit" class="btn-primary px-4 py-3 rounded-lg font-medium text-white inline-flex items-center group">
                                        <i class="fas fa-save mr-2 group-hover:scale-110 transition-transform"></i>
                                        Save Monitoring Settings
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

            {{-- ==================================================================
                 ANALYTICS
            ================================================================== --}}
            @elseif($currentSection == 'analytics')
                <form method="POST" action="{{ route('developer.settings.update') }}" class="space-y-6" id="analyticsSettingsForm" data-section="analytics">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="section" value="analytics">

                    <div class="card p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-chart-pie mr-2" style="color: var(--secondary);"></i> Analytics Settings
                        </h3>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 rounded-lg setting-item" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">Enable Analytics</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Collect and analyze system usage data</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="hidden" name="enable_analytics" value="0">
                                    <input type="checkbox" name="enable_analytics" value="1" class="toggle-checkbox"
                                           {{ old('enable_analytics', $settings->enable_analytics) ? 'checked' : '' }}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Analytics Provider</label>
                                <select name="analytics_provider" id="analytics_provider"
                                        class="form-select w-full p-3 rounded-lg border focus-ring"
                                        style="background-color: var(--card-bg); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="">-- Select Provider --</option>
                                    <option value="google"   {{ old('analytics_provider', $settings->analytics_provider) == 'google'   ? 'selected' : '' }}>Google Analytics</option>
                                    <option value="internal" {{ old('analytics_provider', $settings->analytics_provider) == 'internal' ? 'selected' : '' }}>Internal Analytics</option>
                                    <option value="matomo"   {{ old('analytics_provider', $settings->analytics_provider) == 'matomo'   ? 'selected' : '' }}>Matomo (Self-hosted)</option>
                                    <option value="mixpanel" {{ old('analytics_provider', $settings->analytics_provider) == 'mixpanel' ? 'selected' : '' }}>Mixpanel</option>
                                </select>
                            </div>

                            <div id="tracking_id_field">
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Analytics Tracking ID</label>
                                <input type="text" name="analytics_tracking_id"
                                       value="{{ old('analytics_tracking_id', $settings->analytics_tracking_id) }}"
                                       class="form-input w-full p-3 rounded-lg border focus-ring"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       placeholder="UA-XXXXXXXXX-X or G-XXXXXXXX">
                                <p class="mt-1 text-xs" style="color: var(--text-secondary);" id="tracking_id_help">
                                    For Google Analytics: UA-XXXXXXXXX-X or G-XXXXXXXX<br>
                                    For other providers: Enter your tracking ID
                                </p>
                            </div>

                            <div class="flex items-center justify-between p-4 rounded-lg setting-item" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">Daily Reports</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Send daily analytics reports via email</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="hidden" name="enable_daily_reports" value="0">
                                    <input type="checkbox" name="enable_daily_reports" value="1" class="toggle-checkbox"
                                           {{ old('enable_daily_reports', $settings->enable_daily_reports) ? 'checked' : '' }}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="flex items-center justify-between p-4 rounded-lg setting-item" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">Anonymize IP Addresses</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Anonymize visitor IP addresses for privacy (GDPR compliant)</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="hidden" name="anonymize_ip" value="0">
                                    <input type="checkbox" name="anonymize_ip" value="1" class="toggle-checkbox"
                                           {{ old('anonymize_ip', $settings->anonymize_ip) ? 'checked' : '' }}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="mt-6 pt-4" style="border-top: 1px solid var(--border-color);">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-sm" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Analytics settings affect data collection and privacy.
                                    </p>
                                </div>
                                <button type="submit" class="btn-primary px-4 py-3 rounded-lg font-medium text-white inline-flex items-center group">
                                    <i class="fas fa-save mr-2 group-hover:scale-110 transition-transform"></i>
                                    Save Analytics Settings
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>

{{-- ============================================================
     MODALS
============================================================ --}}

<!-- Email Test Modal -->
<div id="emailTestModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" onclick="closeModal('emailTestModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container max-w-md w-full" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-vial mr-2" style="color: var(--info);"></i> Test Email Configuration
                </h3>
                <button type="button" onclick="closeModal('emailTestModal')" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Test Email Address *</label>
                        <input type="email" id="test_email_input"
                               class="form-input w-full p-3 rounded-lg border focus-ring"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               placeholder="test@example.com" value="{{ auth()->user()->email }}">
                        <p class="mt-1 text-xs" style="color: var(--text-secondary);" id="testEmailError"></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Test Type</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" onclick="testEmailConfiguration('connection')"
                                    class="btn-secondary px-4 py-2 rounded-lg font-medium text-center transition-all">
                                <i class="fas fa-plug mr-2"></i> Test Connection
                            </button>
                            <button type="button" onclick="testEmailConfiguration('send')"
                                    class="btn-primary px-4 py-2 rounded-lg font-medium text-white text-center transition-all">
                                <i class="fas fa-paper-plane mr-2"></i> Send Test Email
                            </button>
                        </div>
                    </div>

                    <div id="testProgress" class="hidden">
                        <div class="flex items-center justify-center py-4">
                            <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i>
                            <span class="ml-3" style="color: var(--text-secondary);">Testing connection...</span>
                        </div>
                    </div>

                    <div id="testResult" class="hidden p-3 rounded-lg"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="closeModal('emailTestModal')" class="btn-secondary px-4 py-2 rounded-lg font-medium">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- API Documentation Modal -->
<div id="apiDocsModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" onclick="closeModal('apiDocsModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container w-full max-w-4xl" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-book mr-2" style="color: var(--secondary);"></i> API Documentation
                </h3>
                <button type="button" onclick="closeModal('apiDocsModal')" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body max-h-96 overflow-y-auto">
                <div class="space-y-6">
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <p class="font-medium" style="color: var(--text-primary);">API Base URL</p>
                        <div class="flex items-center mt-2">
                            <code class="text-sm font-mono break-all flex-1" style="color: var(--text-primary);" id="apiBaseUrlModal">{{ $apiKeys['base_url'] ?? config('app.url') . '/api/developer' }}</code>
                            <button onclick="copyToClipboard('apiBaseUrlModal')" class="ml-2 text-xs px-2 py-1 rounded">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-medium mb-2" style="color: var(--text-primary);">Authentication</h4>
                        <p class="text-sm mb-3" style="color: var(--text-secondary);">All API requests require authentication using your API keys:</p>
                        <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                            <pre class="text-sm font-mono whitespace-pre-wrap" style="color: var(--text-primary);">
Authorization: Bearer YOUR_API_KEY
X-API-Secret: YOUR_SECRET_KEY</pre>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-medium mb-2" style="color: var(--text-primary);">Available Endpoints</h4>
                        <div class="space-y-2">
                            @foreach([
                                ['GET',  '/api/developer/health',   'System health check',           'success'],
                                ['GET',  '/api/developer/stats',    'System statistics',             'success'],
                                ['GET',  '/api/developer/users',    'List users (requires admin)',   'success'],
                                ['POST', '/api/developer/settings', 'Update developer settings',     'warning'],
                            ] as [$method, $endpoint, $desc, $badge])
                            <div class="flex flex-wrap items-center gap-3 p-3 rounded-lg endpoint-item">
                                <span class="px-2 py-1 text-xs font-semibold rounded badge-{{ $badge }}">{{ $method }}</span>
                                <span class="flex-1 font-mono text-sm" style="color: var(--text-primary);">{{ $endpoint }}</span>
                                <span class="text-sm" style="color: var(--text-secondary);">{{ $desc }}</span>
                                <button onclick="copyEndpoint('{{ $endpoint }}')" class="text-xs px-2 py-1 rounded">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <h4 class="font-medium mb-2" style="color: var(--text-primary);">Example Request</h4>
                        <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                            <pre class="text-sm font-mono whitespace-pre-wrap" style="color: var(--text-primary);">
curl -X GET "{{ $apiKeys['base_url'] ?? config('app.url') . '/api/developer' }}/health" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_SECRET_KEY"</pre>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="closeModal('apiDocsModal')" class="btn-secondary px-4 py-2 rounded-lg font-medium">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

<!-- Unsaved Changes Warning Modal -->
<div id="unsavedChangesModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('unsavedChangesModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container max-w-md w-full" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i> Unsaved Changes
                </h3>
                <button type="button" onclick="closeModal('unsavedChangesModal')" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <p style="color: var(--text-primary);">You have unsaved changes. What would you like to do?</p>
            </div>
            <div class="modal-footer flex gap-3">
                <button onclick="stayOnPage()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Stay and Review</button>
                <button onclick="discardChanges()" class="btn-danger px-4 py-2 rounded-lg font-medium">Discard Changes</button>
                <button onclick="saveAndContinue()" class="btn-primary px-4 py-2 rounded-lg font-medium">Save Changes</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// =====================================================
// ✅ Theme toggle removed (button no longer exists)
// =====================================================

// =====================================================
// Form Validation
// =====================================================
const validators = {
    required: (value) => value && value.trim() !== '',
    email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
    phone: (value) => !value || /^[\+\d\s\-\(\)]{10,}$/.test(value),
    url: (value) => !value || /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/.test(value),
    ips: (value) => {
        if (!value) return true;
        return value.split(',').map(ip => ip.trim()).every(ip => /^(\d{1,3}\.){3}\d{1,3}$|^[\w\-\.]+$/.test(ip));
    },
    emails: (value) => {
        if (!value) return true;
        return value.split(',').map(email => email.trim()).every(email => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email));
    }
};

function validateField(field) {
    const validation = field.dataset.validation;
    if (!validation) return true;
    const isValid = validators[validation](field.value);
    if (!isValid) {
        field.style.borderColor = 'var(--danger)';
        let errorMsg = field.nextElementSibling;
        if (!errorMsg || !errorMsg.classList.contains('field-error')) {
            errorMsg = document.createElement('p');
            errorMsg.className = 'mt-1 text-sm field-error';
            errorMsg.style.color = 'var(--danger)';
            field.parentNode.insertBefore(errorMsg, field.nextSibling);
        }
        errorMsg.textContent = `Invalid ${validation} format`;
        return false;
    }
    field.style.borderColor = 'var(--border-color)';
    const errorMsg = field.nextElementSibling;
    if (errorMsg && errorMsg.classList.contains('field-error')) errorMsg.remove();
    return true;
}

// =====================================================
// DOMContentLoaded
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-validation]').forEach(input => {
        input.addEventListener('blur', () => validateField(input));
        input.addEventListener('input', () => {
            if (input.style.borderColor === 'rgb(var(--danger-rgb))') validateField(input);
        });
    });

    initToggleSwitches();
    autoHideMessages();
    initSearch();
    trackUnsavedChanges();
    animateStatCards();
    initPresetButtons();
    initConfirmForms();
});

// =====================================================
// Toggle Switches
// =====================================================
function initToggleSwitches() {
    document.querySelectorAll('.toggle-switch').forEach(container => {
        const checkbox = container.querySelector('.toggle-checkbox');
        const hiddenInput = container.querySelector('input[type="hidden"]');
        if (checkbox && hiddenInput) {
            checkbox.addEventListener('change', function() {
                hiddenInput.value = this.checked ? '1' : '0';
                markFormDirty(this.closest('form'));
            });
            hiddenInput.value = checkbox.checked ? '1' : '0';
        }
    });
}

// =====================================================
// Confirm Forms
// =====================================================
function initConfirmForms() {
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', function(e) {
            const message = this.dataset.confirm;
            if (message && !confirm(message)) e.preventDefault();
        });
    });
}

// =====================================================
// Search
// =====================================================
function initSearch() {
    const searchInput = document.getElementById('settingsSearch');
    if (!searchInput) return;
    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        const navLinks = document.querySelectorAll('#settingsNav a');
        let hasVisible = false;
        navLinks.forEach(link => {
            const match = link.textContent.toLowerCase().includes(term);
            link.style.display = match ? 'flex' : 'none';
            if (match) hasVisible = true;
        });
        let noResults = document.getElementById('noSearchResults');
        if (!hasVisible && term) {
            if (!noResults) {
                noResults = document.createElement('div');
                noResults.id = 'noSearchResults';
                noResults.className = 'text-center p-4 text-sm';
                noResults.style.color = 'var(--text-secondary)';
                noResults.innerHTML = '<i class="fas fa-search mr-2"></i>No settings found for "' + term + '"';
                document.getElementById('settingsNav').appendChild(noResults);
            }
        } else if (noResults) {
            noResults.remove();
        }
    });
}

// =====================================================
// Unsaved Changes
// =====================================================
let formDirty = false;
let currentForm = null;

function trackUnsavedChanges() {
    document.querySelectorAll('form[id$="SettingsForm"]').forEach(form => {
        form.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('change', () => markFormDirty(form));
            input.addEventListener('input',  () => markFormDirty(form));
        });
    });

    window.addEventListener('beforeunload', (e) => {
        if (formDirty) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    });
}

function markFormDirty(form) {
    if (!formDirty) {
        formDirty = true;
        currentForm = form;
    }
}

function clearDirtyState() {
    formDirty = false;
    currentForm = null;
}

// =====================================================
// AJAX Form Submission (general form)
// =====================================================
async function validateAndSubmitForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    let isValid = true;
    form.querySelectorAll('[data-validation]').forEach(input => {
        if (!validateField(input)) isValid = false;
    });
    if (!isValid) {
        showToast('Please fix validation errors before saving', 'error');
        return;
    }

    const saveIndicator = document.getElementById('saveIndicator');
    const saveSuccess   = document.getElementById('saveSuccess');
    const saveError     = document.getElementById('saveError');

    saveIndicator?.classList.remove('hidden');
    saveSuccess?.classList.add('hidden');
    saveError?.classList.add('hidden');

    const formData = new FormData(form);

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        const data = await response.json().catch(() => ({}));

        if (response.ok && data.success) {
            saveSuccess?.classList.remove('hidden');
            showToast(data.message || 'Settings saved successfully!', 'success');
            clearDirtyState();
            setTimeout(() => location.reload(), 1000);
        } else {
            saveError?.classList.remove('hidden');
            const msg = data.message || 'Failed to save settings';
            showToast(msg, 'error');
            if (data.errors) {
                Object.values(data.errors).flat().forEach(err => showToast(err, 'error'));
            }
        }
    } catch (error) {
        saveError?.classList.remove('hidden');
        showToast('Network error: ' + error.message, 'error');
    } finally {
        saveIndicator?.classList.add('hidden');
        setTimeout(() => {
            saveSuccess?.classList.add('hidden');
            saveError?.classList.add('hidden');
        }, 3000);
    }
}

// =====================================================
// Preset Values
// =====================================================
function setPreset(type, value) {
    const map = {
        cache: 'cache_duration',
        upload: 'max_upload_size',
        session: 'session_timeout',
        log_retention: 'log_retention_days'
    };
    const input = document.getElementById(map[type]);
    if (input) {
        input.value = value;
        input.dispatchEvent(new Event('change'));
        showToast(`${type} preset applied: ${value}`, 'info');
    }
}

function initPresetButtons() {
    document.querySelectorAll('[onclick^="setPreset"]').forEach(btn => {
        btn.addEventListener('click', e => e.preventDefault());
    });
}

// =====================================================
// Reset Functions
// =====================================================
function resetToDefaults(section) {
    if (!confirm('Reset all general settings to default values?')) return;
    const form = document.getElementById('generalSettingsForm');
    const defaults = {
        developer_name: '{{ auth()->user()->name }}',
        developer_email: '{{ auth()->user()->email }}',
        cache_duration: '{{ $defaultSettings["cache_duration"] }}',
        max_upload_size: '{{ $defaultSettings["max_upload_size"] }}'
    };
    for (const [key, value] of Object.entries(defaults)) {
        const input = form?.querySelector(`[name="${key}"]`);
        if (input) input.value = value;
    }
    showToast('Reset to default values', 'info');
    if (form) markFormDirty(form);
}

function resetSecurityDefaults() {
    if (!confirm('Reset all security settings to default values?')) return;
    const form = document.getElementById('securitySettingsForm');
    const defaults = {
        session_timeout: '{{ $defaultSettings["session_timeout"] }}',
        max_login_attempts: '{{ $defaultSettings["max_login_attempts"] }}',
        password_expiry_days: '{{ $defaultSettings["password_expiry_days"] }}'
    };
    for (const [key, value] of Object.entries(defaults)) {
        const input = form?.querySelector(`[name="${key}"]`);
        if (input) input.value = value;
    }
    showToast('Reset to default values', 'info');
    if (form) markFormDirty(form);
}

function resetPerformanceDefaults() {
    const form = document.getElementById('generalSettingsForm');
    const defaults = {
        cache_duration: '{{ $defaultSettings["cache_duration"] }}',
        max_upload_size: '{{ $defaultSettings["max_upload_size"] }}'
    };
    for (const [key, value] of Object.entries(defaults)) {
        const input = form?.querySelector(`[name="${key}"]`);
        if (input) input.value = value;
    }
    showToast('Reset to default values', 'info');
    if (form) markFormDirty(form);
}

// =====================================================
// Email Testing
// =====================================================
let testInProgress = false;

function testEmailConfiguration(testType) {
    if (testInProgress) {
        showToast('Test already in progress. Please wait.', 'warning');
        return;
    }
    const testEmail = document.getElementById('test_email_input').value;
    const testProgress = document.getElementById('testProgress');
    const testResult = document.getElementById('testResult');
    const testEmailError = document.getElementById('testEmailError');

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(testEmail)) {
        testEmailError.textContent = 'Please enter a valid email address';
        return;
    }
    testEmailError.textContent = '';

    testInProgress = true;
    testProgress.classList.remove('hidden');
    testResult.classList.add('hidden');

    const button = event?.target;
    const originalText = button?.innerHTML || '';
    if (button) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Testing...';
        button.disabled = true;
    }

    fetch('{{ route("developer.email.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ test_email: testEmail, test_type: testType })
    })
    .then(r => r.json())
    .then(data => {
        testProgress.classList.add('hidden');
        testResult.classList.remove('hidden');
        if (data.success) {
            testResult.style.cssText = 'background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3); color: var(--success);';
            testResult.innerHTML = `<i class="fas fa-check-circle mr-2"></i> ${data.message}`;
            showToast('Test successful! ' + data.message, 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            testResult.style.cssText = 'background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3); color: var(--danger);';
            testResult.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i> ${data.message || 'Test failed'}`;
            showToast('Test failed: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        testProgress.classList.add('hidden');
        testResult.classList.remove('hidden');
        testResult.style.cssText = 'background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3); color: var(--danger);';
        testResult.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i> Network error: ${error.message}`;
        showToast('Test failed: ' + error.message, 'error');
    })
    .finally(() => {
        testInProgress = false;
        if (button) {
            button.innerHTML = originalText;
            button.disabled = false;
        }
    });
}

function sendTestEmail() {
    testEmailConfiguration('send');
}

// =====================================================
// Email Form
// =====================================================
function togglePasswordVisibility(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function resetEmailForm() {
    if (!confirm('Are you sure you want to reset all email configuration fields?')) return;
    const form = document.getElementById('emailSettingsForm');
    if (form) {
        form.reset();
        initToggleSwitches();
        showToast('Email form reset successfully', 'info');
    }
}

// =====================================================
// Modals
// =====================================================
function showEmailTestModal() {
    const modal = document.getElementById('emailTestModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('test_email_input')?.focus(), 100);
    }
}

function showApiDocumentation() {
    const modal = document.getElementById('apiDocsModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// =====================================================
// Unsaved Changes Modal
// =====================================================
let pendingNavigation = null;

function showUnsavedChangesModal(callback) {
    pendingNavigation = callback;
    const modal = document.getElementById('unsavedChangesModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function stayOnPage() {
    closeModal('unsavedChangesModal');
    pendingNavigation = null;
}

function discardChanges() {
    closeModal('unsavedChangesModal');
    clearDirtyState();
    if (pendingNavigation) {
        pendingNavigation();
        pendingNavigation = null;
    }
}

function saveAndContinue() {
    if (currentForm) {
        closeModal('unsavedChangesModal');
        const submitButton = currentForm.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.click();
            setTimeout(() => {
                if (pendingNavigation) {
                    pendingNavigation();
                    pendingNavigation = null;
                }
            }, 1500);
        }
    }
}

document.querySelectorAll('#settingsNav a, .quick-action-form button, .card a').forEach(link => {
    link.addEventListener('click', function(e) {
        if (formDirty && this.closest('form') !== currentForm) {
            e.preventDefault();
            const href = this.getAttribute('href');
            if (href) showUnsavedChangesModal(() => { window.location.href = href; });
        }
    });
});

// =====================================================
// Toasts
// =====================================================
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    const bg = type === 'success' ? 'var(--success)' :
               type === 'error'   ? 'var(--danger)'  :
               type === 'warning' ? 'var(--warning)' : 'var(--info)';
    toast.className = 'px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full';
    toast.style.backgroundColor = `rgba(${getRgbValue(bg)}, 0.95)`;
    toast.style.color = 'white';
    toast.style.borderLeft = `4px solid ${bg}`;

    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.textContent = message;

    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 hover:opacity-80';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };

    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);
    setTimeout(() => {
        if (toast.parentNode === container) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

function getRgbValue(color) {
    if (color.startsWith('var(--')) {
        const varName = color.match(/var\(--([^)]+)\)/)[1];
        const rgbVar = getComputedStyle(document.documentElement).getPropertyValue(`--${varName}-rgb`);
        return rgbVar.trim() || '0,0,0';
    }
    return '0,0,0';
}

// =====================================================
// Copy to Clipboard
// =====================================================
async function copyToClipboard(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const text = el.textContent || el.innerText;
    try {
        await navigator.clipboard.writeText(text);
        showToast('Copied to clipboard!', 'success');
        document.querySelectorAll(`[onclick*="copyToClipboard('${elementId}')"]`).forEach(btn => {
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => { btn.innerHTML = original; }, 1500);
        });
    } catch (err) {
        showToast('Failed to copy: ' + err.message, 'error');
    }
}

function copyEndpoint(endpoint) {
    const baseUrl = document.getElementById('apiBaseUrlModal')?.textContent || '';
    navigator.clipboard.writeText(baseUrl + endpoint);
    showToast('Endpoint copied to clipboard!', 'success');
}

// =====================================================
// Auto-hide Messages
// =====================================================
function autoHideMessages() {
    setTimeout(() => {
        document.querySelectorAll('.alert-success, .alert-warning, .alert-error').forEach(msg => {
            if (msg.style.display !== 'none') {
                msg.style.opacity = '0';
                msg.style.transform = 'translateY(-10px)';
                setTimeout(() => { msg.style.display = 'none'; }, 300);
            }
        });
    }, 5000);
}

// =====================================================
// Animations
// =====================================================
function animateStatCards() {
    document.querySelectorAll('.stat-card').forEach((card, index) => {
        card.style.animation = `fadeInUp 0.5s ease-out ${index * 0.1}s forwards`;
        card.style.opacity = '0';
    });
}

const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInUp { from { opacity:0; transform: translateY(20px);} to {opacity:1; transform:translateY(0);} }
    @keyframes slideDown { from {opacity:0; transform:translateY(-20px);} to {opacity:1; transform:translateY(0);} }
    @keyframes pulseOnce { 0%{transform:scale(1);} 50%{transform:scale(1.05);} 100%{transform:scale(1);} }
    .animate-slide-down { animation: slideDown 0.3s ease-out; }
    .animate-pulse-once { animation: pulseOnce 0.5s ease-out; }
    .hover-scale { transition: transform 0.2s ease; }
    .hover-scale:hover { transform: translateY(-2px); }
    .endpoint-item { transition: all 0.2s ease; }
    .endpoint-item:hover { background-color: rgba(var(--primary-rgb), 0.05); transform: translateX(4px); }
    .status-card { transition: all 0.3s ease; }
    .status-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .setting-item { transition: all 0.2s ease; }
    .setting-item:hover { border-color: var(--primary) !important; }
`;
document.head.appendChild(style);

// =====================================================
// Analytics Provider Help
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
    const providerSelect = document.getElementById('analytics_provider');
    const helpText = document.getElementById('tracking_id_help');
    if (providerSelect && helpText) {
        providerSelect.addEventListener('change', function() {
            switch(this.value) {
                case 'google':   helpText.innerHTML = 'For Google Analytics 4: G-XXXXXXXX<br>For Universal Analytics: UA-XXXXXXXXX-X'; break;
                case 'matomo':   helpText.innerHTML = 'Enter your Matomo site ID (e.g., 1, 2, 3)'; break;
                case 'mixpanel': helpText.innerHTML = 'Enter your Mixpanel project token'; break;
                case 'internal': helpText.innerHTML = 'No tracking ID needed for internal analytics'; break;
                default:         helpText.innerHTML = 'For Google Analytics: UA-XXXXXXXXX-X or G-XXXXXXXX<br>For other providers: Enter your tracking ID';
            }
        });
    }
});

// =====================================================
// Keyboard / Modal close
// =====================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('emailTestModal');
        closeModal('apiDocsModal');
        closeModal('unsavedChangesModal');
    }
});

window.onclick = function(event) {
    if (event.target.classList.contains('fixed') &&
        ['emailTestModal', 'apiDocsModal', 'unsavedChangesModal'].includes(event.target.id)) {
        closeModal(event.target.id);
    }
};
</script>

<style>
.btn-warning { background-color: rgba(var(--warning-rgb), 0.9) !important; color: white !important; border: 1px solid var(--warning) !important; transition: all 0.2s ease; }
.btn-warning:hover { background-color: var(--warning) !important; transform: translateY(-1px); }
.btn-danger { background-color: rgba(var(--danger-rgb), 0.9) !important; color: white !important; border: 1px solid var(--danger) !important; transition: all 0.2s ease; }
.btn-danger:hover { background-color: var(--danger) !important; transform: translateY(-1px); }
.alert-success { background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3); color: var(--success); padding: 1rem; border-radius: 0.5rem; position: relative; transition: all 0.3s ease; }
.alert-error { background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3); color: var(--danger); padding: 1rem; border-radius: 0.5rem; position: relative; transition: all 0.3s ease; }
.alert-warning { background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3); color: var(--warning); padding: 1rem; border-radius: 0.5rem; position: relative; transition: all 0.3s ease; }

/* ============================================================
   ✅ Themed search wrapper — no more hardcoded white on the
   search input. Uses the same tokens as every other input
   in the app.
   ============================================================ */
.settings-search-wrapper {
    position: relative;
    width: 260px;
    max-width: 100%;
}

.settings-search-input {
    width: 100%;
    padding: 0.625rem 2.5rem 0.625rem 2.5rem;
    border-radius: 10px;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    color: var(--text-primary);
    font-size: 0.875rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.settings-search-input::placeholder {
    color: var(--text-secondary);
    opacity: 0.8;
}

.settings-search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15);
    background-color: var(--card-bg);
}

.settings-search-icon {
    position: absolute;
    left: 0.875rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.875rem;
    color: var(--text-secondary);
    pointer-events: none;
}

/* ============================================================
   ✅ Sidebar column — no sticky, no z-index fights.
   The nav card and the System Status / Quick Actions cards
   now scroll together. No more overlap.
   ============================================================ */
.settings-sidebar-column {
    position: relative;
    align-self: flex-start;
}

.settings-sidebar-card {
    position: relative;
    z-index: 1;
    background-color: var(--card-bg);
}

/* Remove any leftover sticky styling on smaller devices. */
@media (max-width: 1023px) {
    .settings-sidebar-column,
    .settings-sidebar-card {
        position: static !important;
    }
}

@media (max-width: 768px) {
    .settings-search-wrapper {
        width: 100%;
    }
    .modal-container { margin: 1rem; max-height: calc(100vh - 2rem); }
    .btn-primary, .btn-secondary, .btn-danger, .btn-warning { width: 100%; justify-content: center; }
    .modal-footer { flex-direction: column; }
    .modal-footer button { width: 100%; margin-bottom: 0.5rem; }
}

::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: var(--bg-secondary); border-radius: 4px; }
::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--primary); }

@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.fa-spinner.fa-spin { animation: spin 1s linear infinite; }

* { transition-property: background-color, border-color, color, fill, stroke; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms; }

.badge-success, .badge-warning, .badge-danger, .badge-info, .badge-primary, .badge-secondary { transition: all 0.2s ease; }
.badge-success:hover, .badge-warning:hover, .badge-danger:hover, .badge-info:hover, .badge-primary:hover, .badge-secondary:hover { transform: scale(1.05); }
.badge-success  { background-color: rgba(var(--success-rgb), 0.15); color: var(--success); }
.badge-warning  { background-color: rgba(var(--warning-rgb), 0.15); color: var(--warning); }
.badge-danger   { background-color: rgba(var(--danger-rgb), 0.15); color: var(--danger); }
.badge-info     { background-color: rgba(var(--info-rgb), 0.15); color: var(--info); }
.badge-primary  { background-color: rgba(var(--primary-rgb), 0.15); color: var(--primary); }
.badge-secondary{ background-color: rgba(var(--secondary-rgb), 0.15); color: var(--secondary); }
</style>
@endsection