@extends('layouts.app')

@section('title', 'System Settings')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-4">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-cogs text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-cogs mr-2" style="color: var(--primary);"></i>
                        System Settings

                        {{-- Office payment status pill — visible at a glance --}}
                        @if($settings->exists)
                            @if($settings->isOfflinePaymentAllowed())
                                <span class="px-3 py-1 rounded-full text-xs font-medium badge-success">
                                    <i class="fas fa-hand-holding-usd mr-1"></i> Office Payments On
                                </span>
                            @else
                                <span class="px-3 py-1 rounded-full text-xs font-medium badge-danger">
                                    <i class="fas fa-ban mr-1"></i> Office Payments Blocked
                                </span>
                            @endif
                        @endif
                    </h2>
                    <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-sliders-h mr-2"></i>
                        <span>Configure and manage system parameters</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-shield-alt mr-1"></i>
                        <span>Administration</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3 flex-wrap gap-2">
                @if($settings->exists)
                    <a href="{{ route('admin.system-settings.edit') }}"
                       class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-edit mr-2"></i> Edit Settings
                    </a>
                    <a href="{{ route('admin.notification-channels.index') }}"
                       class="btn-success px-4 py-2 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-bell mr-2"></i> Notification Channels
                    </a>
                @endif
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- LIVE BACKGROUND UPDATE STATUS BANNER -->
    <!-- ============================================================ -->
    <div id="bg-update-banner" class="card hidden">
        <div class="p-6" id="bg-update-banner-inner">
            <div class="flex items-start">
                <i id="bg-update-icon" class="fas fa-sync-alt fa-spin mt-1 mr-3 text-xl" style="color: var(--info);"></i>
                <div class="flex-1">
                    <h4 id="bg-update-title" class="font-semibold" style="color: var(--info);">
                        Applying configuration in the background…
                    </h4>
                    <p id="bg-update-message" class="text-sm mt-1" style="color: var(--text-secondary);">
                        The system is updating the environment file. This usually takes a few seconds.
                    </p>
                    <div class="flex items-center gap-3 mt-3 flex-wrap">
                        <form action="{{ route('admin.system-settings.retry-env-update') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" id="bg-update-run-now"
                                    class="btn-info px-3 py-1 rounded-lg font-medium inline-flex items-center text-sm">
                                <i class="fas fa-play mr-1"></i> Run Now
                            </button>
                        </form>
                        <button type="button" id="bg-update-refresh"
                                class="btn-secondary px-3 py-1 rounded-lg font-medium inline-flex items-center text-sm">
                            <i class="fas fa-sync mr-1"></i> Check Again
                        </button>
                        <span id="bg-update-meta" class="text-xs" style="color: var(--text-secondary);">
                            Checking…
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Environment Update Status (existing) -->
    @if(session('env_update_complete') || session('env_update_warning') || session('env_update_error') || ($hasPendingEnvUpdate ?? false))
    <div class="card">
        <div class="p-6" style="border-top: 4px solid var(--primary);">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-sync-alt mr-2" style="color: var(--primary);"></i> Configuration Status
            </h3>

            <!-- Email Configuration Status -->
            <div class="mb-6">
                <h4 class="font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-envelope mr-2" style="color: var(--success);"></i> Email Configuration
                </h4>

                @if(session('env_update_complete'))
                <div class="p-4 rounded-lg mb-4" style="background-color: rgba(var(--success-rgb), 0.1); border-left: 4px solid var(--success);">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-3 text-xl" style="color: var(--success);"></i>
                        <div>
                            <h4 class="font-semibold" style="color: var(--success);">Email Configuration Updated Successfully</h4>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">{{ session('env_update_message', 'Email settings have been synchronized with the system environment.') }}</p>
                        </div>
                    </div>
                </div>
                @endif

                @if(session('env_update_warning'))
                <div class="p-4 rounded-lg mb-4" style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-3 text-xl" style="color: var(--warning);"></i>
                        <div>
                            <h4 class="font-semibold" style="color: var(--warning);">Email Configuration Update Warning</h4>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">{{ session('env_update_message', 'Email settings may need manual configuration.') }}</p>
                            @if(session()->has('pending_env_update'))
                                <div class="mt-3 flex space-x-2">
                                    <form action="{{ route('admin.system-settings.retry-env-update') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn-warning px-3 py-1 rounded-lg font-medium inline-flex items-center text-sm">
                                            <i class="fas fa-redo mr-1"></i> Retry Update
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.system-settings.edit') }}#email-configuration"
                                       class="btn-info px-3 py-1 rounded-lg font-medium inline-flex items-center text-sm">
                                        <i class="fas fa-cog mr-1"></i> Manual Setup
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                @if(session('env_update_error'))
                <div class="p-4 rounded-lg mb-4" style="background-color: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
                    <div class="flex items-center">
                        <i class="fas fa-times-circle mr-3 text-xl" style="color: var(--danger);"></i>
                        <div>
                            <h4 class="font-semibold" style="color: var(--danger);">Email Configuration Update Failed</h4>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">{{ session('env_update_message', 'Failed to update email configuration in environment file.') }}</p>
                            @if(session()->has('pending_env_update'))
                                <div class="mt-3 flex space-x-2">
                                    <form action="{{ route('admin.system-settings.retry-env-update') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn-danger px-3 py-1 rounded-lg font-medium inline-flex items-center text-sm">
                                            <i class="fas fa-redo mr-1"></i> Retry Update
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.system-settings.edit') }}#email-configuration"
                                       class="btn-info px-3 py-1 rounded-lg font-medium inline-flex items-center text-sm">
                                        <i class="fas fa-cog mr-1"></i> Manual Setup
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                @if(($hasPendingEnvUpdate ?? false) && !session('pending_env_update.attempted', false))
                <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1); border-left: 4px solid var(--info);">
                    <div class="flex items-center">
                        <i class="fas fa-sync-alt fa-spin mr-3 text-xl" style="color: var(--info);"></i>
                        <div>
                            <h4 class="font-semibold" style="color: var(--info);">Email Configuration Update Processing</h4>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">Email settings are being updated in the background. This may take a few moments.</p>
                            <div class="mt-3 flex items-center">
                                <form action="{{ route('admin.system-settings.retry-env-update') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn-info px-3 py-1 rounded-lg font-medium inline-flex items-center text-sm">
                                        <i class="fas fa-play mr-1"></i> Run Now
                                    </button>
                                </form>
                                <span class="text-xs ml-3" style="color: var(--text-secondary);">
                                    Last updated: {{ \Carbon\Carbon::createFromTimestamp($pendingEnvUpdate['timestamp'] ?? now()->timestamp)->diffForHumans() }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <!-- Configuration Status Overview -->
            @if($settings->exists)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                <div class="card p-4 text-center" style="border-top: 4px solid {{ $settings->canSendEmails() ? 'var(--success)' : 'var(--warning)' }};">
                    <div class="flex items-center justify-center mb-2">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-2"
                             style="background-color: rgba(var({{ $settings->canSendEmails() ? '--success-rgb' : '--warning-rgb' }}), 0.1);">
                            <i class="fas fa-envelope text-xl" style="color: {{ $settings->canSendEmails() ? 'var(--success)' : 'var(--warning)' }};"></i>
                        </div>
                        <span class="font-semibold" style="color: var(--text-primary);">Email Sending</span>
                    </div>
                    <p class="text-sm font-medium" style="color: {{ $settings->canSendEmails() ? 'var(--success)' : 'var(--warning)' }};">
                        {{ $settings->canSendEmails() ? '✓ Ready' : '⚠ Needs Setup' }}
                    </p>
                    @if(!$settings->canSendEmails())
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Configure email settings to send notifications</p>
                    @endif
                </div>

                <div class="card p-4 text-center" style="border-top: 4px solid {{ ($emailConfiguration['configured'] ?? false) ? 'var(--success)' : 'var(--warning)' }};">
                    <div class="flex items-center justify-center mb-2">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-2"
                             style="background-color: rgba(var({{ ($emailConfiguration['configured'] ?? false) ? '--success-rgb' : '--warning-rgb' }}), 0.1);">
                            <i class="fas fa-file-code text-xl" style="color: {{ ($emailConfiguration['configured'] ?? false) ? 'var(--success)' : 'var(--warning)' }};"></i>
                        </div>
                        <span class="font-semibold" style="color: var(--text-primary);">Environment File</span>
                    </div>
                    <p class="text-sm font-medium" style="color: {{ ($emailConfiguration['configured'] ?? false) ? 'var(--success)' : 'var(--warning)' }};">
                        {{ ($emailConfiguration['configured'] ?? false) ? '✓ Configured' : '⚠ Needs Setup' }}
                    </p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ $emailConfiguration['username'] ?? 'No email configured' }}
                    </p>
                </div>

                <div class="card p-4 text-center" style="border-top: 4px solid {{ $settings->isPaymentConfigurationComplete() ? 'var(--success)' : 'var(--warning)' }};">
                    <div class="flex items-center justify-center mb-2">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-2"
                             style="background-color: rgba(var({{ $settings->isPaymentConfigurationComplete() ? '--success-rgb' : '--warning-rgb' }}), 0.1);">
                            <i class="fas fa-cogs text-xl" style="color: {{ $settings->isPaymentConfigurationComplete() ? 'var(--success)' : 'var(--warning)' }};"></i>
                        </div>
                        <span class="font-semibold" style="color: var(--text-primary);">System Status</span>
                    </div>
                    <p class="text-sm font-medium" style="color: {{ $settings->isPaymentConfigurationComplete() ? 'var(--success)' : 'var(--warning)' }};">
                        {{ $settings->isPaymentConfigurationComplete() ? '✓ Ready' : '⚠ Setup Needed' }}
                    </p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ $settings->isPaymentConfigurationComplete() ? 'System fully configured' : 'Complete system setup' }}
                    </p>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Notification Channels Quick Status Card -->
    @if($settings->exists)
    <div class="card">
        <div class="p-6" style="border-top: 4px solid var(--success);">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-bell text-xl" style="color: var(--success);"></i>
                    </div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Notification Channels</h3>
                </div>
                <a href="{{ route('admin.notification-channels.index') }}"
                   class="btn-success px-3 py-1 rounded-lg text-sm font-medium inline-flex items-center">
                    <i class="fas fa-cog mr-1"></i> Configure Channels
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @php
                    $channels = [
                        'invoice_generated' => ['label' => 'Invoice Generated', 'desc' => 'New invoice notification', 'method' => 'getInvoiceNotificationChannels'],
                        'payment_reminder' => ['label' => 'Payment Reminder', 'desc' => 'Before due date reminders', 'method' => 'getPaymentReminderChannels'],
                        'overdue_notification' => ['label' => 'Overdue Notifications', 'desc' => 'When invoice becomes overdue', 'method' => 'getOverdueNotificationChannels'],
                        'payment_confirmation' => ['label' => 'Payment Confirmation', 'desc' => 'After successful payment', 'method' => 'getPaymentConfirmationChannels']
                    ];
                @endphp

                @foreach($channels as $key => $channel)
                <div class="p-3 rounded-lg" style="background: linear-gradient(135deg, rgba(var(--secondary-rgb), 0.02) 0%, rgba(var(--secondary-rgb), 0.05) 100%); border: 1px solid var(--border-color);">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $channel['label'] }}</span>
                        <div class="flex space-x-1">
                            @if(in_array('email', $settings->{$channel['method']}()))
                                <span class="px-1 py-0.5 rounded text-xs" style="background: rgba(var(--success-rgb), 0.2); color: var(--success);">📧</span>
                            @endif
                            @if(in_array('sms', $settings->{$channel['method']}()))
                                <span class="px-1 py-0.5 rounded text-xs" style="background: rgba(var(--info-rgb), 0.2); color: var(--info);">📱</span>
                            @endif
                            @if(in_array('whatsapp', $settings->{$channel['method']}()))
                                <span class="px-1 py-0.5 rounded text-xs" style="background: rgba(37,211,102,0.2); color: #25D366;">💬</span>
                            @endif
                        </div>
                    </div>
                    <p class="text-xs" style="color: var(--text-secondary);">{{ $channel['desc'] }}</p>
                </div>
                @endforeach
            </div>

            <!-- SMS/WhatsApp Status -->
            <div class="mt-4 pt-3 border-t grid grid-cols-1 md:grid-cols-2 gap-4" style="border-color: var(--border-color);">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-mobile-alt mr-2" style="color: {{ $settings->isSmsEnabled() ? 'var(--success)' : 'var(--warning)' }};"></i>
                        <span class="text-sm" style="color: var(--text-primary);">SMS Notifications</span>
                    </div>
                    <span class="px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->isSmsEnabled() ? 'success' : 'warning' }}">
                        {{ $settings->isSmsEnabled() ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fab fa-whatsapp mr-2" style="color: {{ $settings->isWhatsAppEnabled() ? '#25D366' : 'var(--warning)' }};"></i>
                        <span class="text-sm" style="color: var(--text-primary);">WhatsApp Notifications</span>
                    </div>
                    <span class="px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->isWhatsAppEnabled() ? 'success' : 'warning' }}">
                        {{ $settings->isWhatsAppEnabled() ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
            </div>

            @if($settings->isSmsEnabled())
            <div class="mt-3 text-xs" style="color: var(--text-secondary);">
                <i class="fas fa-chart-line mr-1"></i>
                Rate limits: {{ $settings->sms_daily_limit_per_user ?? 10 }} SMS/day, {{ $settings->sms_hourly_limit_per_user ?? 3 }} SMS/hour per user
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- ============================================================ -->
    <!-- OFFICE PAYMENT COLLECTION CARD (NEW)                         -->
    <!-- Controls whether admins can mark invoices as paid manually    -->
    <!-- ============================================================ -->
    @if($settings->exists)
    <div class="card">
        <div class="p-6" style="border-top: 4px solid {{ $settings->isOfflinePaymentAllowed() ? 'var(--success)' : 'var(--danger)' }};">
            <div class="flex items-center justify-between mb-6 flex-wrap gap-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var({{ $settings->isOfflinePaymentAllowed() ? '--success-rgb' : '--danger-rgb' }}), 0.1);">
                        <i class="fas fa-hand-holding-usd text-xl"
                           style="color: {{ $settings->isOfflinePaymentAllowed() ? 'var(--success)' : 'var(--danger)' }};"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Office Payment Collection
                        </h3>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Control whether admins can mark invoices as paid from the office
                        </p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-sm font-medium badge-{{ $settings->isOfflinePaymentAllowed() ? 'success' : 'danger' }}">
                    <i class="fas fa-{{ $settings->isOfflinePaymentAllowed() ? 'check-circle' : 'ban' }} mr-1"></i>
                    {{ $settings->isOfflinePaymentAllowed() ? 'ENABLED' : 'BLOCKED' }}
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Current Status --}}
                <div class="card p-4"
                     style="border-top: 4px solid {{ $settings->isOfflinePaymentAllowed() ? 'var(--success)' : 'var(--danger)' }};">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium" style="color: var(--text-primary);">
                            Physical / Office Payments
                        </span>
                        <span class="px-3 py-1 rounded-full text-sm font-medium badge-{{ $settings->isOfflinePaymentAllowed() ? 'success' : 'danger' }}">
                            {{ $settings->isOfflinePaymentAllowed() ? 'ALLOWED' : 'BLOCKED' }}
                        </span>
                    </div>
                    <p class="text-sm mt-2" style="color: var(--text-secondary);">
                        @if($settings->isOfflinePaymentAllowed())
                            <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                            Admins can mark invoices as paid at the office when a landlord
                            pays cash, by cheque, or through a bank deposit.
                        @else
                            <i class="fas fa-ban mr-1" style="color: var(--danger);"></i>
                            Admins cannot mark invoices as paid. Landlords must use the
                            online payment gateway.
                        @endif
                    </p>
                </div>

                {{-- What this controls --}}
                <div class="card p-4" style="border-top: 4px solid var(--info);">
                    <span class="font-medium mb-2 block" style="color: var(--text-primary);">
                        What this controls
                    </span>
                    <ul class="text-xs mt-2 space-y-1" style="color: var(--text-secondary);">
                        <li>
                            <i class="fas fa-{{ $settings->isOfflinePaymentAllowed() ? 'check' : 'times' }} mr-1"
                               style="color: {{ $settings->isOfflinePaymentAllowed() ? 'var(--success)' : 'var(--danger)' }};"></i>
                            <strong>Mark as Paid</strong> button on each invoice row
                        </li>
                        <li>
                            <i class="fas fa-{{ $settings->isOfflinePaymentAllowed() ? 'check' : 'times' }} mr-1"
                               style="color: {{ $settings->isOfflinePaymentAllowed() ? 'var(--success)' : 'var(--danger)' }};"></i>
                            <strong>Mark as Paid</strong> button in the bulk selection bar
                        </li>
                        <li>
                            <i class="fas fa-{{ $settings->isOfflinePaymentAllowed() ? 'check' : 'times' }} mr-1"
                               style="color: {{ $settings->isOfflinePaymentAllowed() ? 'var(--success)' : 'var(--danger)' }};"></i>
                            <strong>Paid</strong> status in the Bulk Update modal
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Toggle form --}}
            <div class="mt-6 pt-4 border-t" style="border-color: var(--border-color);">
                <form action="{{ route('admin.system-settings.toggle-offline-payment') }}"
                      method="POST"
                      class="flex items-center justify-between flex-wrap gap-4">
                    @csrf
                    <input type="hidden" name="allow_offline_payment"
                           value="{{ $settings->isOfflinePaymentAllowed() ? '0' : '1' }}">

                    <div class="flex items-center space-x-4 flex-wrap">
                        <span class="font-medium" style="color: var(--text-primary);">Quick Toggle:</span>
                        @if($settings->isOfflinePaymentAllowed())
                            <button type="submit"
                                    class="btn-danger px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                    onclick="return confirm('Block office payments? Admins will no longer be able to mark invoices as paid — landlords must pay online.')">
                                <i class="fas fa-ban mr-2"></i> Block Office Payments
                            </button>
                        @else
                            <button type="submit"
                                    class="btn-success px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                    onclick="return confirm('Allow office payments? Admins will be able to mark invoices as paid for cash, cheque, or bank-deposit payments.')">
                                <i class="fas fa-check mr-2"></i> Allow Office Payments
                            </button>
                        @endif
                    </div>

                    <a href="{{ route('admin.system-settings.edit') }}#payment-settings"
                       class="btn-info px-3 py-1 rounded-lg font-medium inline-flex items-center text-sm">
                        <i class="fas fa-cog mr-1"></i> Advanced Settings
                    </a>
                </form>
            </div>

            {{-- Warning banner when blocked --}}
            @if(!$settings->isOfflinePaymentAllowed())
            <div class="mt-4 p-3 rounded-lg"
                 style="background-color: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle mt-1 mr-2" style="color: var(--danger);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--danger);">
                            Office payments are currently blocked
                        </p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Landlords can only pay through the online gateway. If a landlord
                            arrives with cash, they will need to be redirected to the online
                            payment flow.
                        </p>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Alerts -->
    @if(session('success') || session('error') || session('info'))
    <div class="card">
        <div class="p-6">
            @if(session('success'))
                <div class="p-4 rounded-lg flex items-center" style="background-color: rgba(var(--success-rgb), 0.1); border-left: 4px solid var(--success);">
                    <i class="fas fa-check-circle mr-3 text-xl" style="color: var(--success);"></i>
                    <span style="color: var(--text-primary);">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="p-4 rounded-lg flex items-center" style="background-color: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
                    <i class="fas fa-times-circle mr-3 text-xl" style="color: var(--danger);"></i>
                    <span style="color: var(--text-primary);">{{ session('error') }}</span>
                </div>
            @endif

            @if(session('info'))
                <div class="p-4 rounded-lg flex items-center" style="background-color: rgba(var(--info-rgb), 0.1); border-left: 4px solid var(--info);">
                    <i class="fas fa-info-circle mr-3 text-xl" style="color: var(--info);"></i>
                    <span style="color: var(--text-primary);">{{ session('info') }}</span>
                </div>
            @endif
        </div>
    </div>
    @endif

    @if($settings->exists)
        <!-- System Overview Card -->
        <div class="card">
            <div class="p-6" style="border-top: 4px solid var(--primary);">
                <div class="flex items-center mb-6">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-building text-xl" style="color: var(--primary);"></i>
                    </div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">System Overview</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                    <!-- System Logo Display -->
                    <div class="card p-4 flex flex-col items-center justify-center text-center" style="border-top: 4px solid var(--primary);">
                        <div class="mb-4">
                            @if($settings->hasLogo())
                                <img src="{{ $settings->getLogoUrl() }}"
                                     alt="System Logo"
                                     class="h-20 w-20 object-contain rounded-lg border-2"
                                     style="border-color: var(--primary);"
                                     loading="eager">
                            @else
                                <div class="h-20 w-20 flex items-center justify-center rounded-lg border-2" style="border-color: var(--primary); background-color: rgba(var(--primary-rgb), 0.05);">
                                    <i class="fas fa-building text-3xl" style="color: var(--primary);"></i>
                                </div>
                            @endif
                        </div>
                        <p class="font-medium text-center" style="color: var(--text-primary);">System Logo</p>
                        <p class="text-xs text-center mt-1 px-2 py-1 rounded-full badge-{{ $settings->hasLogo() ? 'success' : 'warning' }}">
                            {{ $settings->hasLogo() ? '✓ Configured' : '⚠ No logo set' }}
                        </p>
                    </div>

                    <!-- Favicon Display -->
                    <div class="card p-4 flex flex-col items-center justify-center text-center" style="border-top: 4px solid var(--info);">
                        <div class="mb-4">
                            @if($settings->hasFavicon())
                                <img src="{{ $settings->getFaviconUrl() }}"
                                     alt="Favicon"
                                     class="h-16 w-16 object-contain rounded-lg border-2"
                                     style="border-color: var(--info);"
                                     loading="eager">
                                <div class="mt-2 flex flex-wrap justify-center gap-1">
                                    <span class="text-xs px-1 py-0.5 rounded" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">16×16</span>
                                    <span class="text-xs px-1 py-0.5 rounded" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">32×32</span>
                                    <span class="text-xs px-1 py-0.5 rounded" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">64×64</span>
                                </div>
                            @else
                                <div class="h-16 w-16 flex items-center justify-center rounded-lg border-2" style="border-color: var(--info); background-color: rgba(var(--info-rgb), 0.05);">
                                    <i class="fas fa-image text-3xl" style="color: var(--info);"></i>
                                </div>
                            @endif
                        </div>
                        <p class="font-medium text-center" style="color: var(--text-primary);">Favicon</p>
                        <p class="text-xs text-center mt-1 px-2 py-1 rounded-full badge-{{ $settings->hasFavicon() ? 'success' : 'warning' }}">
                            {{ $settings->hasFavicon() ? '✓ Configured' : '⚠ No favicon set' }}
                        </p>
                        @if($settings->hasFavicon())
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">Appears in browser tabs</p>
                        @else
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">Browser uses default icon</p>
                        @endif
                    </div>

                    <!-- System Name & Info -->
                    <div class="card p-4 flex flex-col justify-center" style="border-top: 4px solid var(--info);">
                        <div class="mb-2">
                            <p class="font-semibold text-lg" style="color: var(--text-primary);">{{ $settings->system_name }}</p>
                            <p class="text-xs" style="color: var(--text-secondary);">System Name</p>
                        </div>
                        <div class="mb-3">
                            <p class="font-medium text-sm flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-tag mr-2" style="color: var(--info);"></i>
                                {{ $settings->system_short_name }}
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">System Short Name</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-envelope mr-2" style="color: var(--success);"></i>
                                {{ $settings->system_email }}
                            </p>
                            <p class="text-sm flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-phone mr-2" style="color: var(--warning);"></i>
                                {{ $settings->system_phone }}
                            </p>
                            <!-- SMS SENDER ID -->
                            <p class="text-sm flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-comment-dots mr-2" style="color: var(--primary);"></i>
                                @if($settings->sms_sender_id)
                                    <span class="font-mono font-medium">{{ $settings->sms_sender_id }}</span>
                                @else
                                    <span class="text-xs" style="color: var(--text-secondary);">No default SMS sender ID</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <!-- Communication Status -->
                    <div class="card p-4 flex flex-col justify-center" style="border-top: 4px solid var(--success);">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-primary);">Email</span>
                                <span class="px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->canSendEmails() ? 'success' : 'warning' }}">
                                    {{ $settings->canSendEmails() ? 'Ready' : 'Setup Needed' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-primary);">System Status</span>
                                <span class="px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->isPaymentConfigurationComplete() ? 'success' : 'warning' }}">
                                    {{ $settings->isPaymentConfigurationComplete() ? 'Complete' : 'Incomplete' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-primary);">Currency</span>
                                <span class="px-2 py-1 rounded-full text-xs font-medium badge-info">
                                    {{ $settings->currency_code }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Configuration Status -->
                    <div class="card p-4 flex flex-col justify-center" style="border-top: 4px solid var(--secondary);">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-primary);">Logo Status</span>
                                <span class="px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->hasLogo() ? 'success' : 'warning' }}">
                                    {{ $settings->hasLogo() ? 'Set' : 'Not Set' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-primary);">Favicon Status</span>
                                <span class="px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->hasFavicon() ? 'success' : 'warning' }}">
                                    {{ $settings->hasFavicon() ? 'Set' : 'Not Set' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-primary);">SMS Sender ID</span>
                                <span class="px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->sms_sender_id ? 'success' : 'warning' }}">
                                    {{ $settings->sms_sender_id ? 'Set' : 'Not Set' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-primary);">Payment Setup</span>
                                <span class="px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->isPaymentConfigurationComplete() ? 'success' : 'warning' }}">
                                    {{ $settings->isPaymentConfigurationComplete() ? 'Complete' : 'Incomplete' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-primary);">Bulk Payments</span>
                                <span class="px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->enable_bulk_payments ? 'success' : 'warning' }}">
                                    {{ $settings->enable_bulk_payments ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                            {{-- New row: Office Payments status --}}
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-primary);">Office Payments</span>
                                <span class="px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->isOfflinePaymentAllowed() ? 'success' : 'danger' }}">
                                    {{ $settings->isOfflinePaymentAllowed() ? 'Allowed' : 'Blocked' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registration Control Card -->
        <div class="card">
            <div class="p-6" style="border-top: 4px solid var(--warning);">
                <div class="flex items-center mb-6">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-user-plus text-xl" style="color: var(--warning);"></i>
                    </div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Registration Control</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="card p-4" style="border-top: 4px solid {{ $settings->isRegistrationAllowed() ? 'var(--success)' : 'var(--danger)' }};">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-medium" style="color: var(--text-primary);">Registration Status</span>
                            <span class="px-3 py-1 rounded-full text-sm font-medium badge-{{ $settings->isRegistrationAllowed() ? 'success' : 'danger' }}">
                                {{ $settings->isRegistrationAllowed() ? 'ENABLED' : 'DISABLED' }}
                            </span>
                        </div>
                        <p class="text-sm mt-2" style="color: var(--text-secondary);">
                            @if($settings->isRegistrationAllowed())
                                <i class="fas fa-unlock mr-1" style="color: var(--success);"></i> New landlords can register properties
                            @else
                                <i class="fas fa-lock mr-1" style="color: var(--danger);"></i> New registrations are blocked
                            @endif
                        </p>
                    </div>

                    <div class="card p-4" style="border-top: 4px solid var(--secondary);">
                        <span class="font-medium mb-2 block" style="color: var(--text-primary);">Disabled Message</span>
                        @if($settings->isRegistrationAllowed())
                            <p class="text-sm flex items-center" style="color: var(--info);">
                                <i class="fas fa-info-circle mr-1"></i> Message shown when registration is disabled
                            </p>
                        @else
                            <div class="p-2 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1);">
                                <p class="text-sm" style="color: var(--danger);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    {{ $settings->getRegistrationDisabledMessage() }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t" style="border-color: var(--border-color);">
                    <form action="{{ route('admin.system-settings.toggle-registration') }}" method="POST" class="flex items-center justify-between flex-wrap gap-4">
                        @csrf
                        <input type="hidden" name="allow_registration" value="{{ $settings->isRegistrationAllowed() ? '0' : '1' }}">

                        <div class="flex items-center space-x-4">
                            <span class="font-medium" style="color: var(--text-primary);">Quick Toggle:</span>
                            @if($settings->isRegistrationAllowed())
                                <button type="submit" class="btn-danger px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                        onclick="return confirm('Are you sure you want to disable registrations? New landlords will not be able to sign up.')">
                                    <i class="fas fa-ban mr-2"></i> Disable Registrations
                                </button>
                            @else
                                <button type="submit" class="btn-success px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                    <i class="fas fa-unlock mr-2"></i> Enable Registrations
                                </button>
                            @endif
                        </div>

                        @if(!$settings->isRegistrationAllowed() && $settings->registration_disabled_message)
                            <a href="{{ route('admin.system-settings.edit') }}#registration-settings"
                               class="btn-info px-3 py-1 rounded-lg font-medium inline-flex items-center text-sm">
                                <i class="fas fa-edit mr-1"></i> Edit Message
                            </a>
                        @endif
                    </form>
                </div>

                <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle mt-1 mr-2" style="color: var(--info);"></i>
                        <div>
                            <p class="text-sm font-medium" style="color: var(--info);">Impact of Registration Control:</p>
                            <ul class="text-xs mt-1 space-y-1" style="color: var(--text-secondary);">
                                <li>• When <strong>disabled</strong>, the registration button is hidden from the homepage</li>
                                <li>• All registration routes are blocked with the custom message above</li>
                                <li>• API endpoints return a 403 error with the disabled message</li>
                                <li>• Existing landlords can still log in and manage their properties</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tenant Dues Configuration Card -->
        <div class="card">
            <div class="p-6" style="border-top: 4px solid var(--success);">
                <div class="flex items-center mb-6">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-users text-xl" style="color: var(--success);"></i>
                    </div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Tenant Dues Configuration</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="card p-4" style="border-top: 4px solid {{ $settings->isTenantInvoicingEnabled() ? 'var(--success)' : 'var(--warning)' }};">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-medium" style="color: var(--text-primary);">Tenant Invoicing</span>
                            <span class="px-3 py-1 rounded-full text-sm font-medium badge-{{ $settings->isTenantInvoicingEnabled() ? 'success' : 'warning' }}">
                                {{ $settings->isTenantInvoicingEnabled() ? 'ENABLED' : 'DISABLED' }}
                            </span>
                        </div>
                        <p class="text-sm mt-2" style="color: var(--text-secondary);">
                            @if($settings->isTenantInvoicingEnabled())
                                <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i> Tenants receive monthly invoices
                            @else
                                <i class="fas fa-ban mr-1" style="color: var(--warning);"></i> Tenant invoicing is turned off
                            @endif
                        </p>
                    </div>

                    <div class="card p-4" style="border-top: 4px solid var(--info);">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-medium" style="color: var(--text-primary);">Monthly Dues Amount</span>
                        </div>
                        <p class="text-xl font-bold" style="color: var(--info);">{{ $settings->formatAmount($settings->tenant_monthly_dues_amount ?? 0) }}</p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Per tenant per month</p>
                    </div>

                    <div class="card p-4" style="border-top: 4px solid var(--secondary);">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-medium" style="color: var(--text-primary);">Calculation Method</span>
                        </div>
                        <p class="text-base font-semibold" style="color: var(--secondary);">{{ $settings->getTenantCalculationMethodText() }}</p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            {{ $settings->getTenantCalculationMethod() === 'fixed' ? 'Fixed amount per tenant' : 'Amount per property unit' }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div class="card p-4" style="border-top: 4px solid var(--primary);">
                        <h4 class="font-medium mb-3 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-cog mr-2" style="color: var(--primary);"></i> Invoice Generation
                        </h4>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-primary);">Auto-generate</span>
                                <span class="px-2 py-1 rounded-full text-xs font-medium badge-{{ ($settings->auto_generate_tenant_invoices ?? true) ? 'success' : 'warning' }}">
                                    {{ ($settings->auto_generate_tenant_invoices ?? true) ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-primary);">Send reminders</span>
                                <span class="px-2 py-1 rounded-full text-xs font-medium badge-{{ ($settings->send_tenant_payment_reminders ?? true) ? 'success' : 'warning' }}">
                                    {{ ($settings->send_tenant_payment_reminders ?? true) ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-primary);">Grace period</span>
                                <span class="text-sm font-medium" style="color: var(--info);">{{ $settings->tenant_grace_period_days ?? $settings->grace_period_days ?? 7 }} days</span>
                            </div>
                        </div>
                    </div>

                    <div class="card p-4" style="border-top: 4px solid var(--warning);">
                        <h4 class="font-medium mb-3 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i> Penalty Configuration
                        </h4>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-primary);">Late payment %</span>
                                <span class="text-sm font-medium" style="color: var(--warning);">{{ $settings->tenant_late_payment_percentage ?? $settings->late_payment_percentage ?? 5 }}%</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-primary);">Fixed penalty</span>
                                <span class="text-sm font-medium" style="color: var(--info);">{{ $settings->formatAmount($settings->tenant_fixed_penalty_amount ?? 0) }}</span>
                            </div>
                            <div class="flex items-start mt-2 p-2 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                                <i class="fas fa-info-circle mt-1 mr-2" style="color: var(--info);"></i>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    @if(($settings->tenant_fixed_penalty_amount ?? 0) > 0)
                                        Fixed penalty amount of {{ $settings->formatAmount($settings->tenant_fixed_penalty_amount) }} will be applied for late payments.
                                    @else
                                        Late payment percentage ({{ $settings->tenant_late_payment_percentage ?? $settings->late_payment_percentage ?? 5 }}%) will be applied.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <div class="flex items-center mb-3">
                        <i class="fas fa-calculator mr-2" style="color: var(--success);"></i>
                        <h4 class="font-medium" style="color: var(--text-primary);">Example Tenant Invoice Calculation</h4>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-xs" style="color: var(--text-secondary);">Landlord monthly dues</span>
                            <p class="font-medium" style="color: var(--text-primary);">{{ $settings->formatAmount($settings->monthly_dues_amount) }}</p>
                        </div>
                        <div>
                            <span class="text-xs" style="color: var(--text-secondary);">Calculation method</span>
                            <p class="font-medium" style="color: var(--text-primary);">{{ $settings->getTenantCalculationMethodText() }}</p>
                        </div>
                        <div>
                            <span class="text-xs" style="color: var(--text-secondary);">Tenant monthly dues</span>
                            <p class="text-xl font-bold" style="color: var(--success);">{{ $settings->formatAmount($settings->getCalculatedTenantDues()) }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle mt-1 mr-2" style="color: var(--info);"></i>
                        <div>
                            <p class="text-sm font-medium" style="color: var(--info);">About Tenant Invoicing:</p>
                            <ul class="text-xs mt-1 space-y-1" style="color: var(--text-secondary);">
                                <li>• When <strong>enabled</strong>, tenants receive monthly invoices for community development fees</li>
                                <li>• Invoices are generated alongside landlord invoices on the same schedule</li>
                                <li>• Tenants can view and pay their invoices through their dashboard</li>
                                <li>• Payment reminders and penalties apply separately for tenants</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Configuration Status Card -->
        <div class="card">
            <div class="p-6" style="border-top: 4px solid var(--success);">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-cog text-xl" style="color: var(--success);"></i>
                    </div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Payment Configuration Status</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="card p-4 text-center" style="border-top: 4px solid {{ $settings->hasEnabledPaymentMethods() ? 'var(--success)' : 'var(--danger)' }};">
                        <div class="flex items-center justify-center mb-2">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-2"
                                 style="background-color: rgba(var({{ $settings->hasEnabledPaymentMethods() ? '--success-rgb' : '--danger-rgb' }}), 0.1);">
                                <i class="fas fa-plug text-xl" style="color: {{ $settings->hasEnabledPaymentMethods() ? 'var(--success)' : 'var(--danger)' }};"></i>
                            </div>
                            <span class="font-semibold" style="color: var(--text-primary);">Payment Gateways</span>
                        </div>
                        <p class="text-sm font-medium" style="color: {{ $settings->hasEnabledPaymentMethods() ? 'var(--success)' : 'var(--danger)' }};">
                            {{ $settings->hasEnabledPaymentMethods() ? '✓ Configured' : '✗ Missing' }}
                        </p>
                        @if(!$settings->hasEnabledPaymentMethods())
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">Enable at least one payment gateway</p>
                        @endif
                    </div>

                    <div class="card p-4 text-center" style="border-top: 4px solid {{ $settings->isPaymentConfigurationComplete() ? 'var(--success)' : 'var(--danger)' }};">
                        <div class="flex items-center justify-center mb-2">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-2"
                                 style="background-color: rgba(var({{ $settings->isPaymentConfigurationComplete() ? '--success-rgb' : '--danger-rgb' }}), 0.1);">
                                <i class="fas fa-check-circle text-xl" style="color: {{ $settings->isPaymentConfigurationComplete() ? 'var(--success)' : 'var(--danger)' }};"></i>
                            </div>
                            <span class="font-semibold" style="color: var(--text-primary);">Overall Status</span>
                        </div>
                        <p class="text-sm font-medium" style="color: {{ $settings->isPaymentConfigurationComplete() ? 'var(--success)' : 'var(--danger)' }};">
                            {{ $settings->isPaymentConfigurationComplete() ? '✓ Ready' : '✗ Incomplete' }}
                        </p>
                        @if($settings->isPaymentConfigurationComplete())
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">Payments can be processed</p>
                        @else
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">Configuration required</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Providers Card -->
        <div class="card">
            <div class="p-6" style="border-top: 4px solid var(--warning);">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-plug text-xl" style="color: var(--warning);"></i>
                    </div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Payment Providers</h3>
                </div>
                <div class="space-y-4">
                    @php
                        $paymentConfiguration = $paymentConfiguration ?? [];
                        $availableMethods = $settings->getAvailablePaymentMethods();
                    @endphp

                    @foreach(['expresspay' => 'ExpressPay', 'hubtel' => 'Hubtel', 'paystack' => 'Paystack', 'flutterwave' => 'Flutterwave'] as $provider => $name)
                        @php
                            $isEnabled = $settings->isPaymentMethodEnabled($provider);
                            $providerConfig = $paymentConfiguration[$provider] ?? null;
                            $isConfigured = $providerConfig && $providerConfig['configured'] && $providerConfig['available'];
                            $isAvailable = $isEnabled && $isConfigured;
                        @endphp

                        <div class="flex items-center justify-between p-3 rounded-lg"
                             style="background: linear-gradient(135deg, rgba(var(--secondary-rgb), 0.02) 0%, rgba(var(--secondary-rgb), 0.05) 100%); border: 1px solid var(--border-color);">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                                    @if($provider === 'expresspay')
                                        <i class="fas fa-credit-card" style="color: #0066CC;"></i>
                                    @elseif($provider === 'hubtel')
                                        <i class="fas fa-phone-alt" style="color: #2563EB;"></i>
                                    @elseif($provider === 'paystack')
                                        <i class="fas fa-credit-card" style="color: #3B82F6;"></i>
                                    @else
                                        <i class="fas fa-cloud-upload-alt" style="color: #F97316;"></i>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">{{ $name }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        @if($isAvailable)
                                            <span style="color: var(--success);">✓ Available to landlords</span>
                                        @elseif($isEnabled && !$isConfigured)
                                            <span style="color: var(--warning);">⚠ Enabled but not configured</span>
                                        @elseif(!$isEnabled && $isConfigured)
                                            <span style="color: var(--info);">ℹ Configured but disabled</span>
                                        @else
                                            <span style="color: var(--danger);">✗ Not available</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div>
                                @if($isEnabled)
                                    <span class="px-2 py-1 rounded-full text-xs font-medium badge-success">
                                        Enabled
                                    </span>
                                @else
                                    <span class="px-2 py-1 rounded-full text-xs font-medium badge-danger">
                                        Disabled
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    @if(!$settings->hasEnabledPaymentMethods())
                        <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1);">
                            <p class="text-xs" style="color: var(--warning);">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                No payment providers are enabled. Landlords won't be able to make payments.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- System Information & Currency Configuration -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="card">
                <div class="p-6" style="border-top: 4px solid var(--primary);">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-info-circle text-xl" style="color: var(--primary);"></i>
                        </div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">System Information</h3>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <div class="w-32">
                                <p class="font-medium" style="color: var(--text-primary);">System Name</p>
                            </div>
                            <p class="text-sm flex-1" style="color: var(--text-secondary);">{{ $settings->system_name }}</p>
                        </div>
                        <div class="flex items-start">
                            <div class="w-32">
                                <p class="font-medium" style="color: var(--text-primary);">Short Name</p>
                            </div>
                            <p class="text-sm flex-1" style="color: var(--text-secondary);">{{ $settings->system_short_name }}</p>
                        </div>
                        <div class="flex items-start">
                            <div class="w-32">
                                <p class="font-medium" style="color: var(--text-primary);">Email</p>
                            </div>
                            <p class="text-sm flex-1" style="color: var(--text-secondary);">{{ $settings->system_email }}</p>
                        </div>
                        <div class="flex items-start">
                            <div class="w-32">
                                <p class="font-medium" style="color: var(--text-primary);">Phone</p>
                            </div>
                            <p class="text-sm flex-1" style="color: var(--text-secondary);">{{ $settings->system_phone }}</p>
                        </div>
                        <!-- SMS SENDER ID -->
                        <div class="flex items-start">
                            <div class="w-32">
                                <p class="font-medium" style="color: var(--text-primary);">SMS Sender ID</p>
                            </div>
                            <div class="flex-1">
                                @if($settings->sms_sender_id)
                                    <p class="text-sm font-mono font-medium" style="color: var(--text-primary);">
                                        <i class="fas fa-comment-dots mr-1" style="color: var(--primary);"></i>
                                        {{ $settings->sms_sender_id }}
                                    </p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Default sender name on outgoing SMS</p>
                                @else
                                    <p class="text-sm" style="color: var(--warning);">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> Not set
                                    </p>
                                    <a href="{{ route('admin.system-settings.edit') }}#general"
                                       class="text-xs" style="color: var(--info);">
                                        Set a default SMS sender ID
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="w-32">
                                <p class="font-medium" style="color: var(--text-primary);">Address</p>
                            </div>
                            <p class="text-sm flex-1" style="color: var(--text-secondary);">{{ $settings->system_address ?? 'Not set' }}</p>
                        </div>
                        <div class="flex items-start">
                            <div class="w-32">
                                <p class="font-medium" style="color: var(--text-primary);">Logo</p>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center space-x-3">
                                    @if($settings->hasLogo())
                                        <img src="{{ $settings->getLogoUrl() }}"
                                             alt="System Logo"
                                             class="h-12 w-12 object-contain rounded border-2"
                                             style="border-color: var(--primary);"
                                             loading="eager">
                                        <div>
                                            <p class="text-sm" style="color: var(--success);">✓ Logo is set</p>
                                            <a href="{{ $settings->getLogoUrl() }}" target="_blank" class="text-xs" style="color: var(--info);">View Logo</a>
                                        </div>
                                    @else
                                        <div class="h-12 w-12 flex items-center justify-center rounded border-2" style="border-color: var(--primary); background-color: rgba(var(--primary-rgb), 0.05);">
                                            <i class="fas fa-building text-lg" style="color: var(--primary);"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm" style="color: var(--warning);">No logo set</p>
                                            <p class="text-xs" style="color: var(--text-secondary);">Add a logo in edit settings</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="w-32">
                                <p class="font-medium" style="color: var(--text-primary);">Favicon</p>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center space-x-3">
                                    @if($settings->hasFavicon())
                                        <img src="{{ $settings->getFaviconUrl() }}"
                                             alt="Favicon"
                                             class="h-8 w-8 object-contain rounded border-2"
                                             style="border-color: var(--info);"
                                             loading="eager">
                                        <div>
                                            <p class="text-sm" style="color: var(--success);">✓ Favicon is set</p>
                                            <a href="{{ $settings->getFaviconUrl() }}" target="_blank" class="text-xs" style="color: var(--info);">View Favicon</a>
                                        </div>
                                    @else
                                        <div class="h-8 w-8 flex items-center justify-center rounded border-2" style="border-color: var(--info); background-color: rgba(var(--info-rgb), 0.05);">
                                            <i class="fas fa-image text-sm" style="color: var(--info);"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm" style="color: var(--warning);">No favicon set</p>
                                            <p class="text-xs" style="color: var(--text-secondary);">Add a favicon in edit settings</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="p-6" style="border-top: 4px solid var(--success);">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--success-rgb), 0.1);">
                            <i class="fas fa-money-bill-wave text-xl" style="color: var(--success);"></i>
                        </div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Currency Configuration</h3>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <div class="w-32">
                                <p class="font-medium" style="color: var(--text-primary);">Currency</p>
                            </div>
                            <p class="text-sm flex-1" style="color: var(--text-secondary);">{{ $settings->currency_code }} ({{ $settings->currency_symbol }})</p>
                        </div>
                        <div class="flex items-start">
                            <div class="w-32">
                                <p class="font-medium" style="color: var(--text-primary);">Position</p>
                            </div>
                            <p class="text-sm flex-1" style="color: var(--text-secondary);">{{ ucfirst(str_replace('_', ' ', $settings->currency_position)) }}</p>
                        </div>
                        <div class="flex items-start">
                            <div class="w-32">
                                <p class="font-medium" style="color: var(--text-primary);">Decimal Places</p>
                            </div>
                            <p class="text-sm flex-1" style="color: var(--text-secondary);">{{ $settings->decimal_places }}</p>
                        </div>
                        <div class="flex items-start">
                            <div class="w-32">
                                <p class="font-medium" style="color: var(--text-primary);">Example</p>
                            </div>
                            <p class="text-sm flex-1 font-semibold" style="color: var(--primary);">{{ $settings->formatAmount(1000.50) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <div class="card">
                <div class="p-6" style="border-top: 4px solid var(--warning);">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--warning-rgb), 0.1);">
                            <i class="fas fa-file-invoice-dollar text-xl" style="color: var(--warning);"></i>
                        </div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Dues Configuration</h3>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <div class="w-40">
                                <p class="font-medium" style="color: var(--text-primary);">Calculation Method</p>
                            </div>
                            <p class="text-sm flex-1" style="color: var(--text-secondary);">{{ str_replace('_', ' ', ucfirst($settings->calculation_method)) }}</p>
                        </div>
                        <div class="flex items-start">
                            <div class="w-40">
                                <p class="font-medium" style="color: var(--text-primary);">Amount</p>
                            </div>
                            <p class="text-sm flex-1" style="color: var(--text-secondary);">{{ $settings->getFormattedDuesAmount() }}</p>
                        </div>
                        @if($settings->calculation_method === 'per_property')
                        <div class="flex items-start">
                            <div class="w-40">
                                <p class="font-medium" style="color: var(--text-primary);">Per Property Amount</p>
                            </div>
                            <p class="text-sm flex-1" style="color: var(--text-secondary);">{{ $settings->formatAmount($settings->per_property_amount) }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="p-6" style="border-top: 4px solid var(--danger);">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--danger-rgb), 0.1);">
                            <i class="fas fa-file-contract text-xl" style="color: var(--danger);"></i>
                        </div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Payment Terms</h3>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <div class="w-40">
                                <p class="font-medium" style="color: var(--text-primary);">Grace Period</p>
                            </div>
                            <p class="text-sm flex-1" style="color: var(--text-secondary);">{{ $settings->grace_period_days }} days</p>
                        </div>
                        <div class="flex items-start">
                            <div class="w-40">
                                <p class="font-medium" style="color: var(--text-primary);">Late Payment Penalty</p>
                            </div>
                            <p class="text-sm flex-1" style="color: var(--text-secondary);">{{ $settings->late_payment_percentage }}%</p>
                        </div>
                        @if($settings->fixed_penalty_amount)
                        <div class="flex items-start">
                            <div class="w-40">
                                <p class="font-medium" style="color: var(--text-primary);">Fixed Penalty</p>
                            </div>
                            <p class="text-sm flex-1" style="color: var(--text-secondary);">{{ $settings->formatAmount($settings->fixed_penalty_amount) }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <div class="card">
                <div class="p-6" style="border-top: 4px solid var(--info);">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--info-rgb), 0.1);">
                            <i class="fas fa-cogs text-xl" style="color: var(--info);"></i>
                        </div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">System Operations</h3>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <div class="w-48">
                                <p class="font-medium" style="color: var(--text-primary);">Auto Generate Invoices</p>
                            </div>
                            <span class="px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->auto_generate_invoices ? 'success' : 'danger' }}">
                                {{ $settings->auto_generate_invoices ? 'Yes' : 'No' }}
                            </span>
                        </div>
                        <div class="flex items-start">
                            <div class="w-48">
                                <p class="font-medium" style="color: var(--text-primary);">Send Payment Reminders</p>
                            </div>
                            <span class="px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->send_payment_reminders ? 'success' : 'danger' }}">
                                {{ $settings->send_payment_reminders ? 'Yes' : 'No' }}
                            </span>
                        </div>
                        <div class="flex items-start">
                            <div class="w-48">
                                <p class="font-medium" style="color: var(--text-primary);">Reminder Days Before</p>
                            </div>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $settings->reminder_days_before }} days</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="p-6" style="border-top: 4px solid var(--success);">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--success-rgb), 0.1);">
                            <i class="fas fa-calendar-check text-xl" style="color: var(--success);"></i>
                        </div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Bulk Payment Settings</h3>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <div class="w-40">
                                <p class="font-medium" style="color: var(--text-primary);">Bulk Payments</p>
                            </div>
                            <span class="px-2 py-1 rounded-full text-xs font-medium badge-{{ $settings->enable_bulk_payments ? 'success' : 'danger' }}">
                                {{ $settings->enable_bulk_payments ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                        @if($settings->enable_bulk_payments)
                        <div class="flex items-start">
                            <div class="w-40">
                                <p class="font-medium" style="color: var(--text-primary);">Maximum Months</p>
                            </div>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $settings->max_bulk_months }} months</p>
                        </div>
                        <div class="flex items-start">
                            <div class="w-40">
                                <p class="font-medium" style="color: var(--text-primary);">Status</p>
                            </div>
                            <span class="px-2 py-1 rounded-full text-xs font-medium badge-success">
                                Active - Landlords can pay up to {{ $settings->max_bulk_months }} months in advance
                            </span>
                        </div>
                        @else
                        <div class="flex items-start">
                            <div class="w-40">
                                <p class="font-medium" style="color: var(--text-primary);">Status</p>
                            </div>
                            <span class="px-2 py-1 rounded-full text-xs font-medium badge-danger">
                                Disabled - Landlords cannot make advance payments
                            </span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Audit Information Card -->
        <div class="card mt-6">
            <div class="p-6" style="border-top: 4px solid var(--secondary);">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var(--secondary-rgb), 0.1);">
                        <i class="fas fa-history text-xl" style="color: var(--secondary);"></i>
                    </div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Audit Information</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex items-start">
                        <div class="w-32">
                            <p class="font-medium" style="color: var(--text-primary);">Created By</p>
                        </div>
                        <p class="text-sm" style="color: var(--text-secondary);">{{ $settings->creator->name ?? 'System' }} on {{ $settings->created_at->format('M d, Y H:i') }}</p>
                    </div>
                    <div class="flex items-start">
                        <div class="w-32">
                            <p class="font-medium" style="color: var(--text-primary);">Last Updated By</p>
                        </div>
                        <p class="text-sm" style="color: var(--text-secondary);">{{ $settings->updater->name ?? 'System' }} on {{ $settings->updated_at->format('M d, Y H:i') }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <div class="flex items-start">
                            <div class="w-32">
                                <p class="font-medium" style="color: var(--text-primary);">Status</p>
                            </div>
                            @if($settings->trashed())
                                <span class="px-2 py-1 rounded-full text-xs font-medium badge-danger">
                                    Deleted (Can be restored)
                                </span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs font-medium badge-success">
                                    Active
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- No Settings Found -->
        <div class="card text-center">
            <div class="p-12" style="border-top: 4px solid var(--primary);">
                <div class="flex flex-col items-center justify-center">
                    <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4"
                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-cogs text-5xl" style="color: var(--primary);"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2" style="color: var(--text-primary);">No System Settings Found</h3>
                    <p class="mb-6" style="color: var(--text-secondary);">Please create the initial system settings to configure the application.</p>
                    <div class="flex space-x-4">
                        <a href="{{ route('admin.system-settings.create') }}"
                           class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                            <i class="fas fa-plus mr-2"></i> Create System Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================================
    // BACKGROUND UPDATE POLLING
    // ============================================================
    const banner       = document.getElementById('bg-update-banner');
    const bannerInner  = document.getElementById('bg-update-banner-inner');
    const iconEl       = document.getElementById('bg-update-icon');
    const titleEl      = document.getElementById('bg-update-title');
    const messageEl    = document.getElementById('bg-update-message');
    const metaEl       = document.getElementById('bg-update-meta');
    const refreshBtn   = document.getElementById('bg-update-refresh');

    const STATUS_URL   = '{{ route("admin.system-settings.update-status") }}';
    const justDispatched = {{ session('system_update_status') === 'processing' ? 'true' : 'false' }};

    let pollTimer      = null;
    let pollAttempts   = 0;
    const MAX_ATTEMPTS = 40;   // ~2 minutes at 3s intervals
    const POLL_MS      = 3000;

    function showBanner(state, title, message, meta) {
        if (!banner) return;
        banner.classList.remove('hidden');

        // Reset border color for the inner block
        bannerInner.style.borderTop = '';

        if (state === 'processing') {
            iconEl.className = 'fas fa-sync-alt fa-spin mt-1 mr-3 text-xl';
            iconEl.style.color = 'var(--info)';
            titleEl.style.color = 'var(--info)';
            bannerInner.style.borderLeft = '4px solid var(--info)';
        } else if (state === 'success') {
            iconEl.className = 'fas fa-check-circle mt-1 mr-3 text-xl';
            iconEl.style.color = 'var(--success)';
            titleEl.style.color = 'var(--success)';
            bannerInner.style.borderLeft = '4px solid var(--success)';
        } else if (state === 'error') {
            iconEl.className = 'fas fa-times-circle mt-1 mr-3 text-xl';
            iconEl.style.color = 'var(--danger)';
            titleEl.style.color = 'var(--danger)';
            bannerInner.style.borderLeft = '4px solid var(--danger)';
        } else if (state === 'warning') {
            iconEl.className = 'fas fa-exclamation-triangle mt-1 mr-3 text-xl';
            iconEl.style.color = 'var(--warning)';
            titleEl.style.color = 'var(--warning)';
            bannerInner.style.borderLeft = '4px solid var(--warning)';
        }

        titleEl.textContent = title;
        messageEl.textContent = message;
        if (meta !== undefined) metaEl.textContent = meta;
    }

    function hideBanner() {
        if (!banner) return;
        banner.classList.add('hidden');
    }

    function stopPolling() {
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
    }

    function pollStatus() {
        pollAttempts++;

        fetch(STATUS_URL, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            cache: 'no-store',
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                showBanner('warning',
                    'Status check failed',
                    'Could not reach the status endpoint: ' + data.error,
                    'Attempt ' + pollAttempts
                );
                scheduleNext();
                return;
            }

            const pending = data.pending_jobs || 0;
            const failed  = data.failed_jobs  || 0;

            if (data.done) {
                showBanner('success',
                    '✅ Configuration applied',
                    'System name is now "' + (data.app_name || '') + '". The .env file has been updated.',
                    'Completed after ' + pollAttempts + ' checks'
                );
                stopPolling();

                // Reload once so all rendered values (config('app.name') etc.) refresh.
                setTimeout(() => window.location.reload(), 1500);
                return;
            }

            if (failed > 0) {
                showBanner('error',
                    '❌ Background update failed',
                    failed + ' job(s) failed. Check storage/logs/laravel.log for details, then retry.',
                    'Attempt ' + pollAttempts
                );
                stopPolling();
                return;
            }

            if (pending > 0) {
                showBanner('processing',
                    'Applying configuration in the background…',
                    pending + ' job(s) queued. A queue worker will process them shortly.',
                    'Attempt ' + pollAttempts + ' • ' + (data.queue_driver || '')
                );
            } else {
                showBanner('warning',
                    'Waiting for queue worker…',
                    'No jobs pending and .env hasn\'t updated yet. Is a worker running? Try "Run Now".',
                    'Attempt ' + pollAttempts
                );
            }

            scheduleNext();
        })
        .catch(err => {
            showBanner('warning',
                'Polling error',
                'Could not check update status: ' + err.message,
                'Attempt ' + pollAttempts
            );
            scheduleNext();
        });
    }

    function scheduleNext() {
        if (pollAttempts >= MAX_ATTEMPTS) {
            showBanner('warning',
                'Still processing…',
                'The update is taking longer than expected. Check the queue worker or click "Run Now".',
                'Gave up after ' + pollAttempts + ' checks'
            );
            return;
        }
        pollTimer = setTimeout(pollStatus, POLL_MS);
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            pollAttempts = 0;
            pollStatus();
        });
    }

    // Auto-start polling if the controller just told us an update is in-flight,
    // or if any job is already pending.
    if (justDispatched) {
        pollAttempts = 0;
        pollStatus();
    } else {
        // Quiet background check: if there are pending/failed jobs, surface the banner.
        fetch(STATUS_URL, {
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
        })
        .then(r => r.json())
        .then(data => {
            if (data.pending_jobs > 0 || data.failed_jobs > 0) {
                pollAttempts = 0;
                pollStatus();
            }
        })
        .catch(() => {/* silent */});
    }

    // ============================================================
    // EXISTING BEHAVIOR — auto-hide alerts, toasts, test email
    // ============================================================

    setTimeout(function() {
        const alerts = document.querySelectorAll('[class*="rounded-lg"][class*="border-left"]');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);

    window.testEmailConfiguration = function() {
        const testEmail = prompt('Enter email address to test configuration:');
        if (testEmail && testEmail.includes('@')) {
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Testing...';
            button.disabled = true;

            fetch('{{ route("admin.system-settings.test-email") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ test_email: testEmail })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Test email sent successfully!', 'success');
                } else {
                    showToast('Failed to send test email: ' + data.message, 'error');
                }
            })
            .catch(() => {
                showToast('Error testing email configuration', 'error');
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
    };

    window.refreshConfiguration = function() {
        window.location.reload();
    };

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = 'fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transition-all duration-300 transform translate-x-full';

        if (type === 'success') {
            toast.style.backgroundColor = 'rgba(var(--success-rgb), 0.9)';
            toast.style.color = 'white';
        } else if (type === 'error') {
            toast.style.backgroundColor = 'rgba(var(--danger-rgb), 0.9)';
            toast.style.color = 'white';
        } else if (type === 'warning') {
            toast.style.backgroundColor = 'rgba(var(--warning-rgb), 0.9)';
            toast.style.color = 'white';
        } else {
            toast.style.backgroundColor = 'rgba(var(--info-rgb), 0.9)';
            toast.style.color = 'white';
        }

        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : type === 'warning' ? 'exclamation-circle' : 'info-circle'} mr-2"></i>
                <span>${escapeHtml(message)}</span>
            </div>
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('translate-x-full');
            toast.classList.add('translate-x-0');
        }, 100);

        setTimeout(() => {
            toast.classList.remove('translate-x-0');
            toast.classList.add('translate-x-full');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 5000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    @if(session('success'))
        showToast('{{ session('success') }}', 'success');
    @endif

    @if(session('error'))
        showToast('{{ session('error') }}', 'error');
    @endif

    @if(session('info'))
        showToast('{{ session('info') }}', 'info');
    @endif

    // Expose showToast to window so other snippets can use it
    window.showToast = showToast;
});
</script>

<style>
.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1) !important;
}

.progress-bar {
    transition: width 0.3s ease;
}

:root {
    --chart-axis: var(--text-secondary);
    --chart-legend: var(--text-primary);
    --chart-text: var(--text-primary);
}

::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-secondary);
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, var(--secondary), var(--primary));
}

@media (max-width: 768px) {
    .modal-container {
        margin: 1rem;
    }
}

.form-input {
    background-color: var(--input-bg);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

.btn-success {
    background-color: var(--success) !important;
    color: white !important;
    border: 1px solid var(--success) !important;
    transition: all 0.2s ease;
}

.btn-success:hover {
    background-color: #00b377 !important;
    transform: translateY(-1px);
}

.btn-danger {
    background-color: var(--danger) !important;
    color: white !important;
    border: 1px solid var(--danger) !important;
    transition: all 0.2s ease;
}

.btn-danger:hover {
    background-color: #dc3545 !important;
    transform: translateY(-1px);
}

.btn-warning {
    background-color: var(--warning) !important;
    color: white !important;
    border: 1px solid var(--warning) !important;
    transition: all 0.2s ease;
}

.btn-warning:hover {
    background-color: #e0a800 !important;
    transform: translateY(-1px);
}

.btn-info {
    background-color: var(--info) !important;
    color: white !important;
    border: 1px solid var(--info) !important;
    transition: all 0.2s ease;
}

.btn-info:hover {
    background-color: #0052cc !important;
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: var(--secondary) !important;
    color: white !important;
    border: 1px solid var(--secondary) !important;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background-color: var(--primary) !important;
    border-color: var(--primary) !important;
    transform: translateY(-1px);
}

.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
}

.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1) !important;
    color: var(--danger) !important;
    border: 1px solid rgba(var(--danger-rgb), 0.3) !important;
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to   { transform: translateX(0);    opacity: 1; }
}

@keyframes slideOut {
    from { transform: translateX(0);    opacity: 1; }
    to   { transform: translateX(100%); opacity: 0; }
}

#bg-update-banner-inner {
    transition: border-left-color 0.3s ease;
    border-left: 4px solid transparent;
}
</style>
@endsection