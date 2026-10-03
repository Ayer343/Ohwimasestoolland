@extends('layouts.app')

@section('title', 'Notification Channels')

@php
    // Provider availability flags — passed by controller, with safe fallbacks.
    $smsProviderConfigured      = $smsProviderConfigured      ?? ($smsStatus['system_ready'] ?? false);
    $whatsappProviderConfigured = $whatsappProviderConfigured ?? ($whatsappStatus['configured'] ?? false);
    $smsActiveProviderName      = $smsActiveProviderName      ?? ($smsStatus['default_provider'] ?? null);
    $whatsappActiveProviderName = $whatsappActiveProviderName ?? ($whatsappStatus['default_provider'] ?? null);
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, #00cc88 0%, #33ddaa 100%); color: white; border-color: #00cc88;">
                        <i class="fas fa-bell text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-bell mr-2" style="color: #00cc88;"></i>
                        Notification Channels
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-sliders-h mr-2" style="color: #33ddaa;"></i>
                        <span>Configure how landlords receive notifications</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-mobile-alt mr-2" style="color: #0066ff;"></i>
                        <span>SMS • Email • WhatsApp</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.system-settings.index') }}"
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background: linear-gradient(135deg, #ff0080 0%, #ff4da6 100%); color: white;">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Settings
                </a>
                <button type="button" onclick="testAllChannels()"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background: linear-gradient(135deg, #0066ff 0%, #4d94ff 100%); color: white;">
                    <i class="fas fa-vial mr-2"></i> Test All Channels
                </button>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
        <div class="card p-6">
            <div class="p-4 rounded-lg flex items-center"
                 style="background: linear-gradient(135deg, rgba(0,204,136,0.1) 0%, rgba(51,221,170,0.1) 100%); border-left: 4px solid #00cc88;">
                <i class="fas fa-check-circle mr-3 text-xl" style="color: #00cc88;"></i>
                <span style="color: var(--text-primary);">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="card p-6">
            <div class="p-4 rounded-lg flex items-center"
                 style="background: linear-gradient(135deg, rgba(255,51,0,0.1) 0%, rgba(255,102,51,0.1) 100%); border-left: 4px solid #ff3300;">
                <i class="fas fa-times-circle mr-3 text-xl" style="color: #ff3300;"></i>
                <span style="color: var(--text-primary);">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Global provider warnings -->
    @if(!$smsProviderConfigured)
        <div class="card p-4" style="border-left: 4px solid #ffaa00;">
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle mr-3 mt-1" style="color: #ffaa00;"></i>
                <div>
                    <p class="font-medium" style="color: #ffaa00;">No SMS provider configured</p>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        SMS toggles will be locked until an SMS provider is configured.
                        <a href="{{ route('admin.sms-providers.index') }}" class="text-blue-500 hover:underline ml-1">
                            Configure SMS Providers →
                        </a>
                    </p>
                </div>
            </div>
        </div>
    @endif

    @if(!$whatsappProviderConfigured)
        <div class="card p-4" style="border-left: 4px solid #ffaa00;">
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle mr-3 mt-1" style="color: #ffaa00;"></i>
                <div>
                    <p class="font-medium" style="color: #ffaa00;">No WhatsApp provider configured</p>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        WhatsApp toggles will be locked until a WhatsApp provider is configured.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- SMS Status Card -->
    <div class="card p-6" style="border-top: 4px solid #0066ff;">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                     style="background: linear-gradient(135deg, rgba(0,102,255,0.1) 0%, rgba(77,148,255,0.1) 100%);">
                    <i class="fas fa-mobile-alt text-xl" style="color: #0066ff;"></i>
                </div>
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">SMS Configuration</h3>
                @if($smsProviderConfigured && $smsActiveProviderName)
                    <span class="ml-3 text-xs px-2 py-1 rounded" style="background: rgba(0,204,136,0.1); color: #00cc88;">
                        Active: {{ $smsActiveProviderName }}
                    </span>
                @endif
            </div>
            <div class="flex items-center space-x-2">
                <span class="px-3 py-1 rounded-full text-sm font-medium"
                      style="background: linear-gradient(135deg, {{ $settings->isSmsEnabled() ? 'rgba(0,204,136,0.1)' : 'rgba(255,170,0,0.1)' }} 0%, {{ $settings->isSmsEnabled() ? 'rgba(51,221,170,0.1)' : 'rgba(255,187,51,0.1)' }} 100%); color: {{ $settings->isSmsEnabled() ? '#00cc88' : '#ffaa00' }};">
                    {{ $settings->isSmsEnabled() ? 'Active' : 'Inactive' }}
                </span>
                <button type="button" onclick="testSms()"
                        class="px-3 py-1 rounded-lg text-sm font-medium inline-flex items-center"
                        style="background: linear-gradient(135deg, #0066ff 0%, #4d94ff 100%); color: white;">
                    <i class="fas fa-paper-plane mr-1"></i> Test SMS
                </button>
            </div>
        </div>

        @if(!$smsProviderConfigured)
            <div class="p-4 rounded-lg" style="background: linear-gradient(135deg, rgba(255,170,0,0.1) 0%, rgba(255,187,51,0.1) 100%);">
                <p class="text-sm" style="color: #ffaa00;">
                    <i class="fas fa-lock mr-1"></i>
                    SMS notifications are locked. Configure an SMS provider first.
                </p>
            </div>
        @else
            <!-- SMS Global Settings -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--bg-secondary);">
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">Enable SMS Notifications</p>
                            <p class="text-xs" style="color: var(--text-secondary);">Master toggle for all SMS notifications</p>
                        </div>
                        <div class="toggle-modern">
                            {{-- Hidden fallback: submit "0" when unchecked --}}
                            <input type="hidden" name="sms_notifications_enabled" value="0">
                            <input type="checkbox" id="sms_notifications_enabled" name="sms_notifications_enabled" value="1"
                                   {{ $settings->isSmsEnabled() ? 'checked' : '' }} class="sr-only sms-toggle">
                            <label for="sms_notifications_enabled" class="toggle-slider"></label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-lg sms-dependent" style="background: var(--bg-secondary); {{ !$settings->isSmsEnabled() ? 'opacity: 0.6;' : '' }}">
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">Enable SMS Reminders</p>
                            <p class="text-xs" style="color: var(--text-secondary);">Send SMS reminders for upcoming due dates</p>
                        </div>
                        <div class="toggle-modern">
                            <input type="hidden" name="sms_reminder_enabled" value="0">
                            <input type="checkbox" id="sms_reminder_enabled" name="sms_reminder_enabled" value="1"
                                   {{ $settings->isSmsReminderEnabled() ? 'checked' : '' }} class="sr-only sms-reminder-toggle" {{ !$settings->isSmsEnabled() ? 'disabled' : '' }}>
                            <label for="sms_reminder_enabled" class="toggle-slider"></label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-lg sms-dependent" style="background: var(--bg-secondary); {{ !$settings->isSmsEnabled() ? 'opacity: 0.6;' : '' }}">
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">Enable SMS Payment Confirmations</p>
                            <p class="text-xs" style="color: var(--text-secondary);">Send SMS after successful payments</p>
                        </div>
                        <div class="toggle-modern">
                            <input type="hidden" name="sms_payment_confirmation_enabled" value="0">
                            <input type="checkbox" id="sms_payment_confirmation_enabled" name="sms_payment_confirmation_enabled" value="1"
                                   {{ $settings->isSmsPaymentConfirmationEnabled() ? 'checked' : '' }} class="sr-only" {{ !$settings->isSmsEnabled() ? 'disabled' : '' }}>
                            <label for="sms_payment_confirmation_enabled" class="toggle-slider"></label>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="p-3 rounded-lg sms-dependent" style="background: var(--bg-secondary); {{ !$settings->isSmsEnabled() ? 'opacity: 0.6;' : '' }}">
                        <label for="sms_daily_limit_per_user" class="block mb-2 font-medium" style="color: var(--text-primary);">Daily SMS Limit Per User</label>
                        <input type="number" class="w-full p-2 border rounded form-input"
                               id="sms_daily_limit_per_user" name="sms_daily_limit_per_user"
                               value="{{ $settings->sms_daily_limit_per_user ?? 10 }}"
                               min="1" max="100" {{ !$settings->isSmsEnabled() ? 'disabled' : '' }}>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Maximum SMS per user per day</p>
                    </div>

                    <div class="p-3 rounded-lg sms-dependent" style="background: var(--bg-secondary); {{ !$settings->isSmsEnabled() ? 'opacity: 0.6;' : '' }}">
                        <label for="sms_hourly_limit_per_user" class="block mb-2 font-medium" style="color: var(--text-primary);">Hourly SMS Limit Per User</label>
                        <input type="number" class="w-full p-2 border rounded form-input"
                               id="sms_hourly_limit_per_user" name="sms_hourly_limit_per_user"
                               value="{{ $settings->sms_hourly_limit_per_user ?? 3 }}"
                               min="1" max="20" {{ !$settings->isSmsEnabled() ? 'disabled' : '' }}>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Maximum SMS per user per hour</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- SMS Provider Status -->
        <div class="mt-4 p-4 rounded-lg" style="background: linear-gradient(135deg, rgba(0,102,255,0.05) 0%, rgba(77,148,255,0.05) 100%);">
            <h4 class="font-medium mb-3 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-server mr-2" style="color: #0066ff;"></i> SMS Provider Status
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="flex items-center justify-between p-2 rounded">
                    <span class="text-sm" style="color: var(--text-secondary);">Default Provider:</span>
                    <span class="text-sm font-medium" style="color: #0066ff;">{{ $smsStatus['default_provider'] ?? 'Not configured' }}</span>
                </div>
                <div class="flex items-center justify-between p-2 rounded">
                    <span class="text-sm" style="color: var(--text-secondary);">System Ready:</span>
                    <span class="text-sm font-medium" style="color: {{ ($smsStatus['system_ready'] ?? false) ? '#00cc88' : '#ffaa00' }};">
                        {{ ($smsStatus['system_ready'] ?? false) ? 'Yes' : 'No' }}
                    </span>
                </div>
                <div class="flex items-center justify-between p-2 rounded">
                    <span class="text-sm" style="color: var(--text-secondary);">Available Providers:</span>
                    <span class="text-sm font-medium" style="color: #0066ff;">{{ count($smsStatus['available_providers'] ?? []) }}</span>
                </div>
                <div class="flex items-center justify-between p-2 rounded">
                    <span class="text-sm" style="color: var(--text-secondary);">Health Status:</span>
                    <span class="text-sm font-medium" style="color: {{ ($smsStatus['health_status'] ?? '') === 'healthy' ? '#00cc88' : '#ffaa00' }};">
                        {{ ucfirst($smsStatus['health_status'] ?? 'Unknown') }}
                    </span>
                </div>
            </div>
            @if(!empty($smsStatus['status_message']))
                <p class="text-xs mt-2" style="color: var(--text-secondary);">{{ $smsStatus['status_message'] }}</p>
            @endif
        </div>
    </div>

    <!-- WhatsApp Status Card -->
    <div class="card p-6" style="border-top: 4px solid #25D366;">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                     style="background: linear-gradient(135deg, rgba(37,211,102,0.1) 0%, rgba(77,255,148,0.1) 100%);">
                    <i class="fab fa-whatsapp text-xl" style="color: #25D366;"></i>
                </div>
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">WhatsApp Configuration</h3>
                @if($whatsappProviderConfigured && $whatsappActiveProviderName)
                    <span class="ml-3 text-xs px-2 py-1 rounded" style="background: rgba(0,204,136,0.1); color: #00cc88;">
                        Active: {{ $whatsappActiveProviderName }}
                    </span>
                @endif
            </div>
            <div class="flex items-center space-x-2">
                <span class="px-3 py-1 rounded-full text-sm font-medium"
                      style="background: linear-gradient(135deg, {{ $whatsappStatus['configured'] ? 'rgba(0,204,136,0.1)' : 'rgba(255,170,0,0.1)' }} 0%, {{ $whatsappStatus['configured'] ? 'rgba(51,221,170,0.1)' : 'rgba(255,187,51,0.1)' }} 100%); color: {{ $whatsappStatus['configured'] ? '#00cc88' : '#ffaa00' }};">
                    {{ $whatsappStatus['configured'] ? 'Configured' : 'Not Configured' }}
                </span>
                <button type="button" onclick="testWhatsApp()"
                        class="px-3 py-1 rounded-lg text-sm font-medium inline-flex items-center"
                        style="background: linear-gradient(135deg, #25D366 0%, #4dff94 100%); color: white;">
                    <i class="fab fa-whatsapp mr-1"></i> Test WhatsApp
                </button>
            </div>
        </div>

        @if(!$whatsappProviderConfigured)
            <div class="p-4 rounded-lg" style="background: linear-gradient(135deg, rgba(255,170,0,0.1) 0%, rgba(255,187,51,0.1) 100%);">
                <p class="text-sm" style="color: #ffaa00;">
                    <i class="fas fa-lock mr-1"></i>
                    WhatsApp notifications are locked. Configure a WhatsApp provider first.
                </p>
            </div>
        @else
            <!-- WhatsApp Global Settings -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--bg-secondary);">
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">Enable WhatsApp Notifications</p>
                            <p class="text-xs" style="color: var(--text-secondary);">Master toggle for all WhatsApp notifications</p>
                        </div>
                        <div class="toggle-modern">
                            <input type="hidden" name="enable_whatsapp_notifications" value="0">
                            <input type="checkbox" id="enable_whatsapp_notifications" name="enable_whatsapp_notifications" value="1"
                                   {{ $settings->isWhatsAppEnabled() ? 'checked' : '' }} class="sr-only whatsapp-toggle">
                            <label for="enable_whatsapp_notifications" class="toggle-slider"></label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-lg whatsapp-dependent" style="background: var(--bg-secondary); {{ !$settings->isWhatsAppEnabled() ? 'opacity: 0.6;' : '' }}">
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">Enable WhatsApp Reminders</p>
                            <p class="text-xs" style="color: var(--text-secondary);">Send WhatsApp reminders for upcoming due dates</p>
                        </div>
                        <div class="toggle-modern">
                            <input type="hidden" name="whatsapp_reminder_enabled" value="0">
                            <input type="checkbox" id="whatsapp_reminder_enabled" name="whatsapp_reminder_enabled" value="1"
                                   {{ $settings->isWhatsAppReminderEnabled() ? 'checked' : '' }} class="sr-only" {{ !$settings->isWhatsAppEnabled() ? 'disabled' : '' }}>
                            <label for="whatsapp_reminder_enabled" class="toggle-slider"></label>
                        </div>
                    </div>
                </div>

                <div class="p-3 rounded-lg" style="background: var(--bg-secondary);">
                    <label for="whatsapp_provider" class="block mb-2 font-medium" style="color: var(--text-primary);">WhatsApp Provider</label>
                    <select id="whatsapp_provider" name="whatsapp_provider" class="w-full p-2 border rounded form-select" {{ !$settings->isWhatsAppEnabled() ? 'disabled' : '' }}>
                        <option value="twilio" {{ $settings->whatsapp_provider == 'twilio' ? 'selected' : '' }}>Twilio</option>
                        <option value="vonage" {{ $settings->whatsapp_provider == 'vonage' ? 'selected' : '' }}>Vonage</option>
                        <option value="custom" {{ $settings->whatsapp_provider == 'custom' ? 'selected' : '' }}>Custom API</option>
                        <option value="none" {{ $settings->whatsapp_provider == 'none' ? 'selected' : '' }}>None</option>
                    </select>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Select your WhatsApp provider</p>
                </div>
            </div>
        @endif

        <!-- WhatsApp Configuration Status -->
        @if(!$whatsappStatus['configured'] && $settings->isWhatsAppEnabled())
            <div class="mt-4 p-3 rounded-lg" style="background: linear-gradient(135deg, rgba(255,170,0,0.1) 0%, rgba(255,187,51,0.1) 100%);">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle mt-1 mr-2" style="color: #ffaa00;"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: #ffaa00;">WhatsApp Configuration Required</p>
                        <ul class="text-xs mt-1 space-y-1" style="color: var(--text-secondary);">
                            @foreach(($whatsappStatus['issues'] ?? []) as $issue)
                                <li>• {{ $issue }}</li>
                            @endforeach
                        </ul>
                        @if(Route::has('admin.whatsapp-config.index'))
                            <p class="text-xs mt-2">
                                Please configure WhatsApp in the
                                <a href="{{ route('admin.whatsapp-config.index') }}" class="text-blue-500 hover:underline">
                                    WhatsApp Configuration
                                </a> section.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Notification Channel Settings Card -->
    <div class="card p-6" style="border-top: 4px solid #ff0080;">
        <div class="flex items-center mb-6">
            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                 style="background: linear-gradient(135deg, rgba(255,0,128,0.1) 0%, rgba(255,77,166,0.1) 100%);">
                <i class="fas fa-sliders-h text-xl" style="color: #ff0080;"></i>
            </div>
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Notification Channels</h3>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Invoice Generated -->
            <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                             style="background: linear-gradient(135deg, rgba(0,204,136,0.1) 0%, rgba(51,221,170,0.1) 100%);">
                            <i class="fas fa-file-invoice text-sm" style="color: #00cc88;"></i>
                        </div>
                        <h4 class="font-semibold" style="color: var(--text-primary);">Invoice Generated</h4>
                    </div>
                    <span class="text-xs px-2 py-1 rounded-full" style="background: var(--bg-secondary); color: var(--text-secondary);">New invoice</span>
                </div>
                <p class="text-xs mb-3" style="color: var(--text-secondary);">When a new invoice is generated for a landlord</p>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" class="mr-2 channel-checkbox" data-type="invoice" data-channel="email" checked disabled>
                        <span>Email <span class="text-xs text-gray-500">(always enabled)</span></span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" class="mr-2 channel-checkbox" data-type="invoice" data-channel="sms"
                               {{ in_array('sms', $settings->getInvoiceNotificationChannels()) ? 'checked' : '' }}
                               {{ (!$settings->isSmsEnabled() || !$smsProviderConfigured) ? 'disabled' : '' }}>
                        <span>SMS</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" class="mr-2 channel-checkbox" data-type="invoice" data-channel="whatsapp"
                               {{ in_array('whatsapp', $settings->getInvoiceNotificationChannels()) ? 'checked' : '' }}
                               {{ (!$settings->isWhatsAppEnabled() || !$whatsappProviderConfigured) ? 'disabled' : '' }}>
                        <span>WhatsApp</span>
                    </label>
                </div>
            </div>

            <!-- Payment Reminder -->
            <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                             style="background: linear-gradient(135deg, rgba(255,170,0,0.1) 0%, rgba(255,187,51,0.1) 100%);">
                            <i class="fas fa-bell text-sm" style="color: #ffaa00;"></i>
                        </div>
                        <h4 class="font-semibold" style="color: var(--text-primary);">Payment Reminder</h4>
                    </div>
                    <span class="text-xs px-2 py-1 rounded-full" style="background: var(--bg-secondary); color: var(--text-secondary);">Before due date</span>
                </div>
                <p class="text-xs mb-3" style="color: var(--text-secondary);">Reminder before invoice due date</p>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" class="mr-2 channel-checkbox" data-type="payment_reminder" data-channel="email" checked disabled>
                        <span>Email <span class="text-xs text-gray-500">(always enabled)</span></span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" class="mr-2 channel-checkbox" data-type="payment_reminder" data-channel="sms"
                               {{ in_array('sms', $settings->getPaymentReminderChannels()) ? 'checked' : '' }}
                               {{ !$settings->isSmsReminderEnabled() ? 'disabled' : '' }}>
                        <span>SMS</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" class="mr-2 channel-checkbox" data-type="payment_reminder" data-channel="whatsapp"
                               {{ in_array('whatsapp', $settings->getPaymentReminderChannels()) ? 'checked' : '' }}
                               {{ !$settings->isWhatsAppReminderEnabled() ? 'disabled' : '' }}>
                        <span>WhatsApp</span>
                    </label>
                </div>
            </div>

            <!-- Overdue Notification -->
            <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                             style="background: linear-gradient(135deg, rgba(255,51,0,0.1) 0%, rgba(255,102,51,0.1) 100%);">
                            <i class="fas fa-exclamation-triangle text-sm" style="color: #ff3300;"></i>
                        </div>
                        <h4 class="font-semibold" style="color: var(--text-primary);">Overdue Notifications</h4>
                    </div>
                    <span class="text-xs px-2 py-1 rounded-full" style="background: var(--bg-secondary); color: var(--text-secondary);">Urgent</span>
                </div>
                <p class="text-xs mb-3" style="color: var(--text-secondary);">When invoice becomes overdue</p>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" class="mr-2 channel-checkbox" data-type="overdue" data-channel="email" checked disabled>
                        <span>Email <span class="text-xs text-gray-500">(always enabled)</span></span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" class="mr-2 channel-checkbox" data-type="overdue" data-channel="sms"
                               {{ in_array('sms', $settings->getOverdueNotificationChannels()) ? 'checked' : '' }}
                               {{ !$settings->isSmsReminderEnabled() ? 'disabled' : '' }}>
                        <span>SMS</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" class="mr-2 channel-checkbox" data-type="overdue" data-channel="whatsapp"
                               {{ in_array('whatsapp', $settings->getOverdueNotificationChannels()) ? 'checked' : '' }}
                               {{ !$settings->isWhatsAppReminderEnabled() ? 'disabled' : '' }}>
                        <span>WhatsApp</span>
                    </label>
                </div>
            </div>

            <!-- Payment Confirmation -->
            <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                             style="background: linear-gradient(135deg, rgba(0,204,136,0.1) 0%, rgba(51,221,170,0.1) 100%);">
                            <i class="fas fa-check-circle text-sm" style="color: #00cc88;"></i>
                        </div>
                        <h4 class="font-semibold" style="color: var(--text-primary);">Payment Confirmation</h4>
                    </div>
                    <span class="text-xs px-2 py-1 rounded-full" style="background: var(--bg-secondary); color: var(--text-secondary);">After payment</span>
                </div>
                <p class="text-xs mb-3" style="color: var(--text-secondary);">When payment is successfully processed</p>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" class="mr-2 channel-checkbox" data-type="payment_confirmation" data-channel="email" checked disabled>
                        <span>Email <span class="text-xs text-gray-500">(always enabled)</span></span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" class="mr-2 channel-checkbox" data-type="payment_confirmation" data-channel="sms"
                               {{ in_array('sms', $settings->getPaymentConfirmationChannels()) ? 'checked' : '' }}
                               {{ !$settings->isSmsPaymentConfirmationEnabled() ? 'disabled' : '' }}>
                        <span>SMS</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" class="mr-2 channel-checkbox" data-type="payment_confirmation" data-channel="whatsapp"
                               {{ in_array('whatsapp', $settings->getPaymentConfirmationChannels()) ? 'checked' : '' }}
                               {{ !$settings->isWhatsAppEnabled() ? 'disabled' : '' }}>
                        <span>WhatsApp</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Advanced Settings -->
        <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
            <h4 class="font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-cog mr-2" style="color: #0066ff;"></i> Advanced Notification Settings
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--bg-secondary);">
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">Force Email Fallback</p>
                        <p class="text-xs" style="color: var(--text-secondary);">Always send email if other channels fail</p>
                    </div>
                    <div class="toggle-modern">
                        <input type="hidden" name="force_email_fallback" value="0">
                        <input type="checkbox" id="force_email_fallback" name="force_email_fallback" value="1"
                               {{ $settings->shouldForceEmailFallback() ? 'checked' : '' }} class="sr-only">
                        <label for="force_email_fallback" class="toggle-slider"></label>
                    </div>
                </div>

                <div class="p-3 rounded-lg" style="background: var(--bg-secondary);">
                    <label for="notification_retry_attempts" class="block mb-2 font-medium" style="color: var(--text-primary);">Retry Attempts</label>
                    <input type="number" class="w-full p-2 border rounded form-input"
                           id="notification_retry_attempts" name="notification_retry_attempts"
                           value="{{ $settings->notification_retry_attempts ?? 3 }}" min="1" max="10">
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Number of retry attempts for failed notifications</p>
                </div>

                <div class="p-3 rounded-lg" style="background: var(--bg-secondary);">
                    <label for="notification_retry_delay_minutes" class="block mb-2 font-medium" style="color: var(--text-primary);">Retry Delay (Minutes)</label>
                    <input type="number" class="w-full p-2 border rounded form-input"
                           id="notification_retry_delay_minutes" name="notification_retry_delay_minutes"
                           value="{{ $settings->notification_retry_delay_minutes ?? 5 }}" min="1" max="60">
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Minutes to wait between retry attempts</p>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="mt-6 flex justify-end">
            <button type="button" onclick="saveSettings()"
                    class="px-6 py-2 rounded-lg font-medium inline-flex items-center save-settings-btn"
                    style="background: linear-gradient(135deg, #00cc88 0%, #33ddaa 100%); color: white;">
                <i class="fas fa-save mr-2"></i> Save All Settings
            </button>
        </div>
    </div>

    <!-- Test Modal -->
    <div id="testModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="rounded-lg p-6 max-w-md w-full" style="background: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Test Notification</h3>
                <button type="button" onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Select Channel</label>
                    <select id="test_channel" class="w-full p-2 border rounded form-select">
                        <option value="email">Email</option>
                        <option value="sms">SMS</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="all">All Channels</option>
                    </select>
                </div>
                <div id="email_field" class="test-field">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Email Address</label>
                    <input type="email" id="test_email" class="w-full p-2 border rounded form-input" placeholder="recipient@example.com">
                </div>
                <div id="phone_field" class="test-field hidden">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Phone Number</label>
                    <input type="tel" id="test_phone" class="w-full p-2 border rounded form-input" placeholder="+233XXXXXXXXX">
                </div>
                <div>
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Message Type</label>
                    <select id="test_message_type" class="w-full p-2 border rounded form-select">
                        <option value="invoice_generated">Invoice Generated</option>
                        <option value="payment_reminder">Payment Reminder</option>
                        <option value="overdue">Overdue Notification</option>
                        <option value="payment_confirmation">Payment Confirmation</option>
                        <option value="custom">Custom Message</option>
                    </select>
                </div>
                <div id="custom_message_field" class="hidden">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Custom Message</label>
                    <textarea id="test_custom_message" rows="3" class="w-full p-2 border rounded form-textarea" placeholder="Enter your custom message..."></textarea>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Max 160 characters for SMS</p>
                </div>
                <button type="button" onclick="sendTestNotification()" class="w-full py-2 rounded-lg font-medium text-white"
                        style="background: linear-gradient(135deg, #0066ff 0%, #4d94ff 100%);">
                    Send Test
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="rounded-lg p-6 flex items-center space-x-3" style="background: var(--card-bg);">
        <i class="fas fa-spinner fa-spin text-2xl" style="color: #00cc88;"></i>
        <span style="color: var(--text-primary);">Saving settings...</span>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Provider configuration flags (injected from server)
window.NOTIFICATION_CHANNEL_FLAGS = {
    smsProviderConfigured: @json($smsProviderConfigured),
    whatsappProviderConfigured: @json($whatsappProviderConfigured),
};

document.addEventListener('DOMContentLoaded', function() {
    // Initialize toggle switches
    document.querySelectorAll('.toggle-modern input[type="checkbox"]').forEach(checkbox => {
        updateToggleSwitch(checkbox);
        checkbox.addEventListener('change', function() {
            updateToggleSwitch(this);

            // Handle SMS dependent fields
            if (this.id === 'sms_notifications_enabled') {
                toggleSmsDependentFields(this.checked);
            }

            // Handle WhatsApp dependent fields
            if (this.id === 'enable_whatsapp_notifications') {
                toggleWhatsAppDependentFields(this.checked);
            }

            // Handle SMS reminder toggle
            if (this.id === 'sms_reminder_enabled') {
                updateChannelCheckboxes('payment_reminder', 'sms', this.checked);
                updateChannelCheckboxes('overdue', 'sms', this.checked);
            }

            // Handle WhatsApp reminder toggle
            if (this.id === 'whatsapp_reminder_enabled') {
                updateChannelCheckboxes('payment_reminder', 'whatsapp', this.checked);
                updateChannelCheckboxes('overdue', 'whatsapp', this.checked);
            }

            // Handle SMS payment confirmation toggle
            if (this.id === 'sms_payment_confirmation_enabled') {
                updateChannelCheckboxes('payment_confirmation', 'sms', this.checked);
            }
        });
    });

    // Initial state sync
    const smsEnabled = document.getElementById('sms_notifications_enabled')?.checked || false;
    toggleSmsDependentFields(smsEnabled);

    const whatsappEnabled = document.getElementById('enable_whatsapp_notifications')?.checked || false;
    toggleWhatsAppDependentFields(whatsappEnabled);
});

function updateToggleSwitch(checkbox) {
    const slider = checkbox.nextElementSibling;
    if (!slider) return;
    if (checkbox.checked) {
        slider.style.backgroundColor = 'var(--success)';
        slider.style.borderColor = 'var(--success)';
    } else {
        slider.style.backgroundColor = '#d1d5db';
        slider.style.borderColor = '#d1d5db';
    }
}

function toggleSmsDependentFields(enabled) {
    const smsFields = document.querySelectorAll('.sms-dependent');
    const smsReminderToggle = document.getElementById('sms_reminder_enabled');
    const smsPaymentToggle = document.getElementById('sms_payment_confirmation_enabled');
    const dailyLimit = document.getElementById('sms_daily_limit_per_user');
    const hourlyLimit = document.getElementById('sms_hourly_limit_per_user');

    smsFields.forEach(field => {
        field.style.opacity = enabled ? '1' : '0.6';
        field.querySelectorAll('input, select').forEach(input => {
            if (enabled) input.removeAttribute('disabled');
            else input.setAttribute('disabled', 'disabled');
        });
    });

    if (!enabled) {
        if (smsReminderToggle) {
            smsReminderToggle.checked = false;
            updateChannelCheckboxes('payment_reminder', 'sms', false);
            updateChannelCheckboxes('overdue', 'sms', false);
        }
        if (smsPaymentToggle) {
            smsPaymentToggle.checked = false;
            updateChannelCheckboxes('payment_confirmation', 'sms', false);
        }
        updateChannelCheckboxes('invoice', 'sms', false);
    }
}

function toggleWhatsAppDependentFields(enabled) {
    const whatsappFields = document.querySelectorAll('.whatsapp-dependent');
    const whatsappReminderToggle = document.getElementById('whatsapp_reminder_enabled');
    const whatsappProvider = document.getElementById('whatsapp_provider');

    whatsappFields.forEach(field => {
        field.style.opacity = enabled ? '1' : '0.6';
        field.querySelectorAll('input, select').forEach(input => {
            if (enabled) input.removeAttribute('disabled');
            else input.setAttribute('disabled', 'disabled');
        });
    });

    if (!enabled) {
        if (whatsappReminderToggle) {
            whatsappReminderToggle.checked = false;
            updateChannelCheckboxes('payment_reminder', 'whatsapp', false);
            updateChannelCheckboxes('overdue', 'whatsapp', false);
        }
        updateChannelCheckboxes('invoice', 'whatsapp', false);
        updateChannelCheckboxes('payment_confirmation', 'whatsapp', false);
    }

    if (whatsappProvider) whatsappProvider.disabled = !enabled;
}

function updateChannelCheckboxes(type, channel, enabled) {
    const checkboxes = document.querySelectorAll(`.channel-checkbox[data-type="${type}"][data-channel="${channel}"]`);
    checkboxes.forEach(checkbox => {
        if (enabled) {
            checkbox.disabled = false;
        } else {
            checkbox.checked = false;
            checkbox.disabled = true;
        }
    });
}

function saveSettings() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    loadingOverlay.classList.remove('hidden');

    // Collect SMS settings
    const smsData = {
        sms_notifications_enabled: document.getElementById('sms_notifications_enabled')?.checked || false,
        sms_reminder_enabled: document.getElementById('sms_reminder_enabled')?.checked || false,
        sms_payment_confirmation_enabled: document.getElementById('sms_payment_confirmation_enabled')?.checked || false,
        sms_daily_limit_per_user: parseInt(document.getElementById('sms_daily_limit_per_user')?.value || 10),
        sms_hourly_limit_per_user: parseInt(document.getElementById('sms_hourly_limit_per_user')?.value || 3)
    };

    // Collect WhatsApp settings
    const whatsappData = {
        enable_whatsapp_notifications: document.getElementById('enable_whatsapp_notifications')?.checked || false,
        whatsapp_reminder_enabled: document.getElementById('whatsapp_reminder_enabled')?.checked || false,
        whatsapp_provider: document.getElementById('whatsapp_provider')?.value || 'none'
    };

    // Collect channel settings
    const channelData = {
        invoice_notification_channels: [],
        payment_reminder_channels: [],
        overdue_notification_channels: [],
        payment_confirmation_channels: []
    };

    document.querySelectorAll('.channel-checkbox').forEach(checkbox => {
        const type = checkbox.dataset.type;
        const channel = checkbox.dataset.channel;
        if (checkbox.checked && !checkbox.disabled) {
            const key = `${type}_notification_channels`;
            if (channelData[key]) channelData[key].push(channel);
        }
    });

    // Ensure email always included
    Object.keys(channelData).forEach(key => {
        if (!channelData[key].includes('email')) channelData[key].unshift('email');
    });

    // Collect advanced settings
    const advancedData = {
        force_email_fallback: document.getElementById('force_email_fallback')?.checked || false,
        notification_retry_attempts: parseInt(document.getElementById('notification_retry_attempts')?.value || 3),
        notification_retry_delay_minutes: parseInt(document.getElementById('notification_retry_delay_minutes')?.value || 5)
    };

    const allData = {
        ...smsData,
        ...whatsappData,
        ...channelData,
        ...advancedData
    };

    // Client-side guard: block SMS/WhatsApp if provider not configured
    const flags = window.NOTIFICATION_CHANNEL_FLAGS;

    if (smsData.sms_notifications_enabled && !flags.smsProviderConfigured) {
        loadingOverlay.classList.add('hidden');
        showToast('SMS notifications cannot be enabled without a configured SMS provider.', 'error');
        return;
    }
    if (whatsappData.enable_whatsapp_notifications && !flags.whatsappProviderConfigured) {
        loadingOverlay.classList.add('hidden');
        showToast('WhatsApp notifications cannot be enabled without a configured WhatsApp provider.', 'error');
        return;
    }

    // Verify no forbidden channel selections leaked through
    if (!flags.smsProviderConfigured) {
        const anySms = ['invoice_notification_channels', 'payment_reminder_channels', 'overdue_notification_channels', 'payment_confirmation_channels']
            .some(k => channelData[k].includes('sms'));
        if (anySms) {
            loadingOverlay.classList.add('hidden');
            showToast('Cannot enable SMS channels: no SMS provider configured.', 'error');
            return;
        }
    }
    if (!flags.whatsappProviderConfigured) {
        const anyWa = ['invoice_notification_channels', 'payment_reminder_channels', 'overdue_notification_channels', 'payment_confirmation_channels']
            .some(k => channelData[k].includes('whatsapp'));
        if (anyWa) {
            loadingOverlay.classList.add('hidden');
            showToast('Cannot enable WhatsApp channels: no WhatsApp provider configured.', 'error');
            return;
        }
    }

    fetch('{{ route("admin.notification-channels.update") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(allData)
    })
    .then(async response => {
        const data = await response.json().catch(() => ({}));
        return { ok: response.ok, status: response.status, data };
    })
    .then(({ ok, status, data }) => {
        loadingOverlay.classList.add('hidden');
        if (ok && data.success) {
            showToast(data.message || 'Settings saved successfully', 'success');
            setTimeout(() => window.location.reload(), 1200);
        } else {
            let msg = data.message || 'Failed to save settings';
            if (data.errors) {
                const first = Object.values(data.errors)[0];
                if (Array.isArray(first) && first.length) msg = first[0];
            }
            showToast(msg, 'error');
        }
    })
    .catch(error => {
        console.error('Save error:', error);
        loadingOverlay.classList.add('hidden');
        showToast('Error saving settings: ' + error.message, 'error');
    });
}

function testSms() {
    const phone = prompt('Enter phone number to test SMS:', '+233');
    if (!phone) return;

    showToast('Sending test SMS...', 'info');
    fetch('{{ route("admin.notification-channels.sms.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ phone_number: phone, message_type: 'payment_reminder' })
    })
    .then(response => response.json())
    .then(data => {
        showToast(data.message || (data.success ? 'Test SMS sent' : 'Test SMS failed'), data.success ? 'success' : 'error');
    })
    .catch(error => {
        showToast('Error sending test SMS: ' + error.message, 'error');
    });
}

function testWhatsApp() {
    const phone = prompt('Enter phone number to test WhatsApp (with country code):', '+233');
    if (!phone) return;

    showToast('Sending test WhatsApp message...', 'info');
    fetch('{{ route("admin.notification-channels.whatsapp.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ phone_number: phone, message_type: 'payment_reminder' })
    })
    .then(response => response.json())
    .then(data => {
        showToast(data.message || (data.success ? 'Test WhatsApp sent' : 'Test WhatsApp failed'), data.success ? 'success' : 'error');
    })
    .catch(error => {
        showToast('Error sending test WhatsApp: ' + error.message, 'error');
    });
}

function testAllChannels() {
    const modal = document.getElementById('testModal');
    modal.classList.remove('hidden');

    const channelSelect = document.getElementById('test_channel');
    const emailField = document.getElementById('email_field');
    const phoneField = document.getElementById('phone_field');
    const messageTypeSelect = document.getElementById('test_message_type');
    const customField = document.getElementById('custom_message_field');

    // Defaults
    channelSelect.value = 'email';
    document.getElementById('test_email').value = '{{ auth()->user()->email ?? '' }}';
    document.getElementById('test_phone').value = '{{ auth()->user()->phone ?? '' }}';

    function syncChannelFields() {
        const channel = channelSelect.value;
        if (channel === 'email') {
            emailField.classList.remove('hidden');
            phoneField.classList.add('hidden');
        } else if (channel === 'sms' || channel === 'whatsapp') {
            emailField.classList.add('hidden');
            phoneField.classList.remove('hidden');
        } else if (channel === 'all') {
            emailField.classList.remove('hidden');
            phoneField.classList.remove('hidden');
        }
    }

    function syncMessageFields() {
        if (messageTypeSelect.value === 'custom') {
            customField.classList.remove('hidden');
        } else {
            customField.classList.add('hidden');
        }
    }

    channelSelect.onchange = syncChannelFields;
    messageTypeSelect.onchange = syncMessageFields;

    syncChannelFields();
    syncMessageFields();
}

function closeModal() {
    document.getElementById('testModal').classList.add('hidden');
}

function sendTestNotification() {
    const channel = document.getElementById('test_channel').value;
    const messageType = document.getElementById('test_message_type').value;
    const customMessage = document.getElementById('test_custom_message').value;
    const email = document.getElementById('test_email').value;
    const phone = document.getElementById('test_phone').value;

    const data = {
        channels: channel === 'all' ? ['email', 'sms', 'whatsapp'] : [channel],
        message_type: messageType
    };

    if (messageType === 'custom') data.custom_message = customMessage;
    if (email) data.email = email;
    if (phone) data.phone_number = phone;

    closeModal();
    showToast('Sending test notification...', 'info');

    fetch('{{ route("admin.notification-channels.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        showToast(data.message || (data.success ? 'Test sent' : 'Test failed'), data.success ? 'success' : 'error');
        if (data.results) console.log('Test results:', data.results);
    })
    .catch(error => {
        showToast('Error sending test notification: ' + error.message, 'error');
    });
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = 'p-4 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full';
    toast.style.color = 'white';

    if (type === 'success') toast.style.background = 'linear-gradient(135deg, #00cc88 0%, #33ddaa 100%)';
    else if (type === 'error') toast.style.background = 'linear-gradient(135deg, #ff3300 0%, #ff6633 100%)';
    else if (type === 'warning') toast.style.background = 'linear-gradient(135deg, #ffaa00 0%, #ffbb33 100%)';
    else toast.style.background = 'linear-gradient(135deg, #0066ff 0%, #4d94ff 100%)';

    const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : type === 'warning' ? 'exclamation-circle' : 'info-circle';

    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${icon} mr-2"></i>
            <span>${message}</span>
        </div>
    `;

    container.appendChild(toast);
    requestAnimationFrame(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    });

    setTimeout(() => {
        toast.classList.remove('translate-x-0');
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.parentNode && toast.parentNode.removeChild(toast), 300);
    }, 4500);
}
</script>

<style>
/* Enhanced Toggle Switch Styles */
.toggle-modern {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}

.toggle-modern input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #d1d5db;
    border: 2px solid #d1d5db;
    transition: .4s;
    border-radius: 34px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-modern input:checked + .toggle-slider {
    background-color: var(--success);
    border-color: var(--success);
}

.toggle-modern input:checked + .toggle-slider:before {
    transform: translateX(24px);
}

.toggle-modern input:disabled + .toggle-slider {
    cursor: not-allowed;
    opacity: 0.5;
}

/* Card hover effects */
.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1) !important;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-secondary);
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #00cc88, #33ddaa);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #33ddaa, #66ffcc);
}

/* Form inputs */
.form-input, .form-select, .form-textarea {
    background-color: var(--bg-input, var(--card-bg));
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: #00cc88;
    box-shadow: 0 0 0 3px rgba(0, 204, 136, 0.1);
}

.form-input:disabled, .form-select:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
@endsection