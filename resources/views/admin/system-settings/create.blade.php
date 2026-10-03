@extends('layouts.app')

@section('title', 'Create System Settings')

@section('content')
@php
    // Server-side provider availability flags.
    // The controller SHOULD pass these. Fallbacks keep the view safe if not.
    $smsProviderConfigured      = $smsProviderConfigured      ?? false;
    $whatsappProviderConfigured = $whatsappProviderConfigured ?? false;
    $smsActiveProviderName      = $smsActiveProviderName      ?? null;
    $whatsappActiveProviderName = $whatsappActiveProviderName ?? null;
@endphp

<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Create System Settings</h2>
            <div class="flex space-x-3">
                <button type="button" id="previewButtonHeader" class="btn-info flex items-center">
                    <i class="fas fa-eye mr-2"></i> Preview Settings
                </button>
                <a href="{{ route('admin.system-settings.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Settings
                </a>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if(session('success'))
        <div class="card p-6">
            <div class="alert alert-success">
                <i class="fas fa-check-circle mr-2"></i>
                {{ session('success') }}
            </div>
        </div>
    @endif

    <!-- Environment Configuration Status -->
    @if(session('env_update_status'))
        <div class="card p-6">
            <div class="alert alert-{{ session('env_update_status') === 'success' ? 'success' : 'warning' }}">
                <i class="fas fa-{{ session('env_update_status') === 'success' ? 'check-circle' : 'exclamation-triangle' }} mr-2"></i>
                @if(session('env_update_status') === 'success')
                    Email configuration has been automatically synchronized with the system environment.
                @else
                    System settings created successfully, but email configuration may need manual update in .env file.
                @endif
            </div>
        </div>
    @endif

    <form action="{{ route('admin.system-settings.store') }}" method="POST" enctype="multipart/form-data" id="systemSettingsForm">
        @csrf
        
        @if($errors->any())
            <div class="card p-6">
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <!-- Tab Navigation -->
        <div class="card p-0 overflow-hidden">
            <div class="tab-navigation overflow-x-auto">
                <div class="flex border-b min-w-max" style="border-color: var(--border-color);">
                    <button type="button" class="tab-btn px-6 py-3 font-medium transition-all duration-200" data-tab="general">
                        <i class="fas fa-info-circle mr-2"></i> General
                    </button>
                    <button type="button" class="tab-btn px-6 py-3 font-medium transition-all duration-200" data-tab="payment">
                        <i class="fas fa-credit-card mr-2"></i> Payment
                    </button>
                    <button type="button" class="tab-btn px-6 py-3 font-medium transition-all duration-200" data-tab="notifications">
                        <i class="fas fa-bell mr-2"></i> Notifications
                    </button>
                    <button type="button" class="tab-btn px-6 py-3 font-medium transition-all duration-200" data-tab="tenant">
                        <i class="fas fa-users mr-2"></i> Tenant Dues
                    </button>
                    <button type="button" class="tab-btn px-6 py-3 font-medium transition-all duration-200" data-tab="advanced">
                        <i class="fas fa-cogs mr-2"></i> Advanced
                    </button>
                </div>
            </div>
            
            <!-- Tab Content -->
            <div class="p-6">
                <!-- ============================================================ -->
                <!-- GENERAL TAB -->
                <!-- ============================================================ -->
                <div class="tab-content" id="tab-general">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- System Information Card -->
                        <div class="card p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">System Information</h3>
                                <i class="fas fa-info-circle text-2xl opacity-70" style="color: var(--primary);"></i>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label for="system_name" class="block mb-2 font-medium required" style="color: var(--text-primary);">System Name *</label>
                                    <input type="text" class="w-full p-2 border rounded form-input" 
                                           id="system_name" name="system_name" value="{{ old('system_name') }}" required
                                           placeholder="Enter full system name">
                                </div>

                                <div>
                                    <label for="system_short_name" class="block mb-2 font-medium required" style="color: var(--text-primary);">Short Name *</label>
                                    <div class="relative">
                                        <input type="text" class="w-full p-2 border rounded form-input font-mono" 
                                               id="system_short_name" name="system_short_name" value="{{ old('system_short_name') }}" 
                                               required
                                               placeholder="property-system"
                                               pattern="[a-z0-9-]+"
                                               title="Only lowercase letters, numbers, and hyphens allowed">
                                        <button type="button" 
                                                class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none"
                                                onclick="generateSlug()"
                                                title="Generate from system name">
                                            <i class="fas fa-magic text-sm"></i>
                                        </button>
                                    </div>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        URL-friendly identifier. Use lowercase letters, numbers, and hyphens only.
                                    </p>
                                    <div id="slug-preview" class="hidden mt-2 p-2 text-xs border rounded">
                                        <span class="font-medium">Preview:</span>
                                        <span id="slug-preview-text" class="ml-1"></span>
                                    </div>
                                </div>

                                <div>
                                    <label for="system_email" class="block mb-2 font-medium required" style="color: var(--text-primary);">System Email *</label>
                                    <input type="email" class="w-full p-2 border rounded form-input" 
                                           id="system_email" name="system_email" value="{{ old('system_email', $emailConfiguration['username'] ?? '') }}" required>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Used as MAIL_USERNAME for sending emails</p>
                                </div>
                                
                                <div>
                                    <label for="system_email_password" class="block mb-2 font-medium required" style="color: var(--text-primary);">Email Password *</label>
                                    <div class="relative">
                                        <input type="password" class="w-full p-2 border rounded form-input pr-10" 
                                               id="system_email_password" name="system_email_password" 
                                               value="{{ old('system_email_password', $emailConfiguration['password'] ?? '') }}" required>
                                        <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700" 
                                                onclick="togglePasswordVisibility('system_email_password')">
                                            <i class="fas fa-eye" id="system_email_password_icon"></i>
                                        </button>
                                    </div>
                                    <p class="text-sm mt-1">For Gmail: Use an <a href="https://myaccount.google.com/apppasswords" target="_blank" class="text-blue-500">App Password</a></p>
                                </div>

                                <div>
                                    <label for="system_phone" class="block mb-2 font-medium required" style="color: var(--text-primary);">System Phone *</label>
                                    <input type="text" class="w-full p-2 border rounded form-input" 
                                           id="system_phone" name="system_phone" value="{{ old('system_phone') }}" required>
                                </div>

                                <!-- ========================================================== -->
                                <!-- DEFAULT SMS SENDER ID -->
                                <!-- ========================================================== -->
                                <div>
                                    <label for="sms_sender_id" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                        <i class="fas fa-comment-dots mr-1"></i> Default SMS Sender ID
                                    </label>
                                    <div class="relative">
                                        <input type="text"
                                               class="w-full p-2 border rounded form-input font-mono pr-16"
                                               id="sms_sender_id"
                                               name="sms_sender_id"
                                               value="{{ old('sms_sender_id', $smsSenderId ?? config('sms.sender_id', '')) }}"
                                               maxlength="11"
                                               pattern="[A-Za-z0-9 _\-]*"
                                               title="Only letters, numbers, spaces, hyphens and underscores. Max 11 characters."
                                               placeholder="e.g. PROPERTY">
                                        <span id="smsSenderIdCounter"
                                              class="absolute right-3 top-1/2 transform -translate-y-1/2 text-xs"
                                              style="color: var(--text-secondary);">0/11</span>
                                    </div>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        Used as the default sender name shown on outgoing SMS. Max 11 characters.
                                        Letters, numbers, spaces, hyphens and underscores only.
                                    </p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Per-provider sender IDs (set on the
                                        <a href="{{ route('admin.sms-providers.index') }}" class="text-blue-500 hover:underline">
                                            SMS Providers
                                        </a> page) override this value.
                                    </p>
                                    <div id="smsSenderIdWarning"
                                         class="hidden mt-2 p-2 text-xs rounded"
                                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        <span id="smsSenderIdWarningText"></span>
                                    </div>
                                </div>

                                <div>
                                    <label for="system_address" class="block mb-2 font-medium" style="color: var(--text-primary);">System Address</label>
                                    <textarea class="w-full p-2 border rounded form-textarea" 
                                              id="system_address" name="system_address" rows="3">{{ old('system_address') }}</textarea>
                                </div>
                                
                                <!-- ========================================================== -->
                                <!-- SYSTEM LOGO UPLOAD -->
                                <!-- ========================================================== -->
                                <div>
                                    <label for="system_logo" class="block mb-2 font-medium" style="color: var(--text-primary);">System Logo</label>
                                    <div class="space-y-3">
                                        <div id="logoPreview" class="hidden">
                                            <div class="flex items-center space-x-4 p-3 border rounded">
                                                <img id="logoPreviewImage" src="" alt="Logo Preview" class="h-16 w-16 object-contain">
                                                <div>
                                                    <p class="text-sm font-medium" id="logoFileName"></p>
                                                    <button type="button" id="removeLogoPreview" class="text-sm text-red-500 hover:text-red-700">
                                                        <i class="fas fa-times mr-1"></i>Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div id="logoUploadArea" class="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer">
                                            <i class="fas fa-cloud-upload-alt text-3xl mb-2"></i>
                                            <div>
                                                <label for="system_logo" class="cursor-pointer">
                                                    <span class="font-medium">Upload a file</span>
                                                    <span class="text-sm"> or drag and drop</span>
                                                </label>
                                            </div>
                                            <p class="text-xs mt-1">PNG, JPG, GIF, WebP up to 2MB</p>
                                            <input type="file" id="system_logo" name="system_logo" class="hidden" 
                                                   accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewLogo(this)">
                                        </div>
                                    </div>
                                </div>

                                <!-- ========================================================== -->
                                <!-- FAVICON UPLOAD -->
                                <!-- ========================================================== -->
                                <div>
                                    <label for="system_favicon" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                        <i class="fas fa-image mr-1"></i> Favicon
                                    </label>
                                    <div class="space-y-3">
                                        <div id="faviconPreview" class="hidden">
                                            <div class="flex items-center space-x-4 p-3 border rounded">
                                                <img id="faviconPreviewImage" src="" alt="Favicon Preview" class="h-8 w-8 object-contain">
                                                <div>
                                                    <p class="text-sm font-medium" id="faviconFileName"></p>
                                                    <button type="button" id="removeFaviconPreview" class="text-sm text-red-500 hover:text-red-700">
                                                        <i class="fas fa-times mr-1"></i>Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div id="faviconUploadArea" class="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer">
                                            <i class="fas fa-image text-3xl mb-2" style="color: var(--primary);"></i>
                                            <div>
                                                <label for="system_favicon" class="cursor-pointer">
                                                    <span class="font-medium">Upload a favicon</span>
                                                    <span class="text-sm"> or drag and drop</span>
                                                </label>
                                            </div>
                                            <p class="text-xs mt-1">ICO, PNG, SVG, JPG up to 100KB (Recommended: 16x16, 32x32, or 64x64)</p>
                                            <div class="flex justify-center space-x-4 mt-2 text-xs" style="color: var(--text-secondary);">
                                                <span>📐 16x16</span>
                                                <span>📐 32x32</span>
                                                <span>📐 64x64</span>
                                            </div>
                                            <input type="file" id="system_favicon" name="system_favicon" class="hidden" 
                                                   accept="image/x-icon,image/png,image/svg+xml,image/jpeg" onchange="previewFavicon(this)">
                                        </div>
                                    </div>
                                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        This icon appears in browser tabs, bookmarks, and address bar. 
                                        <a href="https://favicon.io/" target="_blank" class="text-blue-500 hover:underline">Generate a favicon</a>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- ========================================================== -->
                        <!-- SYSTEM OPERATIONS CARD -->
                        <!-- ========================================================== -->
                        <div class="card p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">System Operations</h3>
                                <i class="fas fa-cogs text-2xl opacity-70" style="color: var(--secondary);"></i>
                            </div>
                            
                            <div class="space-y-4">
                                <!-- LANDLORD AUTO-GENERATE INVOICES -->
                                <div class="p-3 border rounded" style="border-left: 3px solid var(--primary);">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <label class="flex items-center">
                                                {{-- Hidden fallback ensures "off" is submitted when unchecked --}}
                                                <input type="hidden" name="auto_generate_invoices" value="0">
                                                <input type="checkbox" id="auto_generate_invoices" name="auto_generate_invoices" value="1" checked class="mr-2 auto-invoice-toggle">
                                                <span class="font-medium">Auto Generate Invoices</span>
                                            </label>
                                            <p class="text-xs mt-1 ml-6" style="color: var(--text-secondary);">For landlords/property owners</p>
                                        </div>
                                        <span class="text-xs px-2 py-1 rounded" style="background: rgba(34, 197, 94, 0.1); color: rgb(22, 163, 74);">Landlord</span>
                                    </div>
                                </div>

                                <!-- LANDLORD PAYMENT REMINDERS -->
                                <div class="p-3 border rounded" style="border-left: 3px solid var(--secondary);">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <label class="flex items-center">
                                                <input type="hidden" name="send_payment_reminders" value="0">
                                                <input type="checkbox" id="send_payment_reminders" name="send_payment_reminders" value="1" checked class="mr-2 reminder-toggle">
                                                <span class="font-medium">Send Payment Reminders</span>
                                            </label>
                                            <p class="text-xs mt-1 ml-6" style="color: var(--text-secondary);">For landlords/property owners</p>
                                        </div>
                                        <span class="text-xs px-2 py-1 rounded" style="background: rgba(59, 130, 246, 0.1); color: rgb(59, 130, 246);">Landlord</span>
                                    </div>
                                </div>

                                <!-- REMINDER DAYS -->
                                <div class="reminder-dependent-field">
                                    <label for="reminder_days_before" class="block mb-2 font-medium">Reminder Days Before *</label>
                                    <input type="number" class="w-full p-2 border rounded" 
                                           id="reminder_days_before" name="reminder_days_before" value="7" min="1" max="30">
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Days before due date to send reminders</p>
                                </div>

                                <!-- GRACE PERIOD -->
                                <div>
                                    <label for="grace_period_days" class="block mb-2 font-medium">Grace Period (Days) *</label>
                                    <input type="number" class="w-full p-2 border rounded" 
                                           id="grace_period_days" name="grace_period_days" value="7" min="0" max="30">
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Days after due date before late penalties apply</p>
                                </div>

                                <hr style="border-color: var(--border-color);">

                                <!-- BULK PAYMENTS -->
                                <div>
                                    <label class="flex items-center">
                                        <input type="hidden" name="enable_bulk_payments" value="0">
                                        <input type="checkbox" id="enable_bulk_payments" name="enable_bulk_payments" value="1" checked class="mr-2">
                                        <span class="font-medium">Enable Bulk Payments</span>
                                    </label>
                                    <p class="text-sm mt-1 ml-6">Allow landlords to pay multiple months at once</p>
                                </div>
                                <div>
                                    <label for="max_bulk_months" class="block mb-2 font-medium">Maximum Bulk Months *</label>
                                    <input type="number" class="w-full p-2 border rounded" 
                                           id="max_bulk_months" name="max_bulk_months" value="6" min="1" max="12">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- PAYMENT TAB -->
                <!-- ============================================================ -->
                <div class="tab-content hidden" id="tab-payment">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Currency Configuration -->
                        <div class="card p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold">Currency Configuration</h3>
                                <i class="fas fa-money-bill-wave text-2xl"></i>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label for="currency_code" class="block mb-2 font-medium required">Currency Code *</label>
                                    <select class="w-full p-2 border rounded" id="currency_code" name="currency_code" required>
                                        <option value="">Select Currency</option>
                                        <option value="GHS" data-symbol="GH₵" selected>GHS - Ghanaian Cedi</option>
                                        <option value="USD" data-symbol="$">USD - US Dollar</option>
                                        <option value="EUR" data-symbol="€">EUR - Euro</option>
                                        <option value="GBP" data-symbol="£">GBP - British Pound</option>
                                        <option value="NGN" data-symbol="₦">NGN - Nigerian Naira</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="currency_symbol" class="block mb-2 font-medium required">Currency Symbol *</label>
                                    <select class="w-full p-2 border rounded" id="currency_symbol" name="currency_symbol" required>
                                        <option value="GH₵" selected>GH₵</option>
                                        <option value="$">$</option>
                                        <option value="€">€</option>
                                        <option value="£">£</option>
                                        <option value="₦">₦</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="currency_position" class="block mb-2 font-medium required">Currency Position *</label>
                                    <select class="w-full p-2 border rounded" id="currency_position" name="currency_position" required>
                                        <option value="left" selected>Left (GH₵100)</option>
                                        <option value="right">Right (100GH₵)</option>
                                        <option value="left_with_space">Left with space (GH₵ 100)</option>
                                        <option value="right_with_space">Right with space (100 GH₵)</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="decimal_places" class="block mb-2 font-medium required">Decimal Places *</label>
                                    <input type="number" class="w-full p-2 border rounded" id="decimal_places" name="decimal_places" value="2" min="0" max="4">
                                </div>
                            </div>
                        </div>

                        <!-- Payment Providers -->
                        <div class="card p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold">Payment Providers *</h3>
                                <i class="fas fa-plug text-2xl"></i>
                            </div>
                            <div id="payment-gateway-error" class="hidden mb-4 alert alert-danger">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span id="payment-error-message">At least one payment gateway must be enabled</span>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-4">
                                <div class="flex items-center justify-between p-3 border rounded">
                                    <div class="flex items-center">
                                        <i class="fas fa-credit-card text-blue-600 text-xl mr-3"></i>
                                        <span>ExpressPay</span>
                                    </div>
                                    <label class="toggle-modern">
                                        <input type="hidden" name="enable_expresspay" value="0">
                                        <input type="checkbox" name="enable_expresspay" value="1" class="payment-gateway-checkbox">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                
                                <div class="flex items-center justify-between p-3 border rounded">
                                    <div class="flex items-center">
                                        <i class="fas fa-phone-alt text-blue-500 text-xl mr-3"></i>
                                        <span>Hubtel</span>
                                    </div>
                                    <label class="toggle-modern">
                                        <input type="hidden" name="enable_hubtel" value="0">
                                        <input type="checkbox" name="enable_hubtel" value="1" class="payment-gateway-checkbox">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                
                                <div class="flex items-center justify-between p-3 border rounded">
                                    <div class="flex items-center">
                                        <i class="fas fa-credit-card text-purple-600 text-xl mr-3"></i>
                                        <span>Paystack</span>
                                    </div>
                                    <label class="toggle-modern">
                                        <input type="hidden" name="enable_paystack" value="0">
                                        <input type="checkbox" name="enable_paystack" value="1" class="payment-gateway-checkbox">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                
                                <div class="flex items-center justify-between p-3 border rounded">
                                    <div class="flex items-center">
                                        <i class="fas fa-cloud-upload-alt text-orange-500 text-xl mr-3"></i>
                                        <span>Flutterwave</span>
                                    </div>
                                    <label class="toggle-modern">
                                        <input type="hidden" name="enable_flutterwave" value="0">
                                        <input type="checkbox" name="enable_flutterwave" value="1" class="payment-gateway-checkbox">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mt-4 text-sm">
                                <span id="enabled-gateways-count">0</span> out of 4 payment gateways enabled
                            </div>
                        </div>

                        <!-- Dues & Payment Terms -->
                        <div class="card p-6">
                            <h3 class="text-lg font-semibold mb-4">Dues Configuration</h3>
                            <div class="space-y-4">
                                <div>
                                    <label for="calculation_method" class="block mb-2 font-medium required">Calculation Method *</label>
                                    <select class="w-full p-2 border rounded" id="calculation_method" name="calculation_method" required>
                                        <option value="fixed" selected>Fixed Amount</option>
                                        <option value="per_property">Per Property</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="monthly_dues_amount" class="block mb-2 font-medium required">Monthly Dues Amount *</label>
                                    <input type="number" step="0.01" class="w-full p-2 border rounded" id="monthly_dues_amount" name="monthly_dues_amount" value="100">
                                </div>
                                <div id="per_property_amount_field" class="hidden">
                                    <label for="per_property_amount" class="block mb-2 font-medium">Per Property Amount</label>
                                    <input type="number" step="0.01" class="w-full p-2 border rounded" id="per_property_amount" name="per_property_amount" value="100">
                                </div>
                            </div>
                        </div>

                        <div class="card p-6">
                            <h3 class="text-lg font-semibold mb-4">Payment Terms</h3>
                            <div class="space-y-4">
                                <div>
                                    <label for="late_payment_percentage" class="block mb-2 font-medium required">Late Payment Percentage *</label>
                                    <input type="number" step="0.01" class="w-full p-2 border rounded" id="late_payment_percentage" name="late_payment_percentage" value="5" min="0" max="100">
                                </div>
                                <div>
                                    <label for="fixed_penalty_amount" class="block mb-2 font-medium">Fixed Penalty Amount</label>
                                    <input type="number" step="0.01" class="w-full p-2 border rounded" id="fixed_penalty_amount" name="fixed_penalty_amount" value="0">
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Set to 0 to use percentage penalty only</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- NOTIFICATIONS TAB -->
                <!-- ============================================================ -->
                <div class="tab-content hidden" id="tab-notifications">
                    <div class="space-y-6">
                        <!-- Notification Channels -->
                        <div class="card p-6">
                            <h3 class="text-lg font-semibold mb-4">Notification Channels</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="border rounded p-4">
                                    <h4 class="font-semibold mb-2">Invoice Generated</h4>
                                    <div class="space-y-2">
                                        <label class="flex items-center"><input type="checkbox" name="invoice_notification_channels[]" value="email" checked disabled class="mr-2"> Email (always enabled)</label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="invoice_notification_channels[]" value="sms"
                                                   class="mr-2 notification-channel" data-type="sms"
                                                   @if(!$smsProviderConfigured) disabled @endif>
                                            SMS
                                            @if(!$smsProviderConfigured)
                                                <span class="ml-2 text-xs" style="color: var(--text-secondary);">(no SMS provider configured)</span>
                                            @endif
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="invoice_notification_channels[]" value="whatsapp"
                                                   class="mr-2 notification-channel" data-type="whatsapp"
                                                   @if(!$whatsappProviderConfigured) disabled @endif>
                                            WhatsApp
                                            @if(!$whatsappProviderConfigured)
                                                <span class="ml-2 text-xs" style="color: var(--text-secondary);">(no WhatsApp provider configured)</span>
                                            @endif
                                        </label>
                                    </div>
                                </div>
                                <div class="border rounded p-4">
                                    <h4 class="font-semibold mb-2">Payment Reminders</h4>
                                    <div class="space-y-2">
                                        <label class="flex items-center"><input type="checkbox" name="payment_reminder_channels[]" value="email" checked disabled class="mr-2"> Email (always enabled)</label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="payment_reminder_channels[]" value="sms"
                                                   class="mr-2 notification-channel" data-type="sms"
                                                   @if(!$smsProviderConfigured) disabled @endif>
                                            SMS
                                            @if(!$smsProviderConfigured)
                                                <span class="ml-2 text-xs" style="color: var(--text-secondary);">(no SMS provider configured)</span>
                                            @endif
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="payment_reminder_channels[]" value="whatsapp"
                                                   class="mr-2 notification-channel" data-type="whatsapp"
                                                   @if(!$whatsappProviderConfigured) disabled @endif>
                                            WhatsApp
                                            @if(!$whatsappProviderConfigured)
                                                <span class="ml-2 text-xs" style="color: var(--text-secondary);">(no WhatsApp provider configured)</span>
                                            @endif
                                        </label>
                                    </div>
                                </div>
                                <div class="border rounded p-4">
                                    <h4 class="font-semibold mb-2">Overdue Notifications</h4>
                                    <div class="space-y-2">
                                        <label class="flex items-center"><input type="checkbox" name="overdue_notification_channels[]" value="email" checked disabled class="mr-2"> Email (always enabled)</label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="overdue_notification_channels[]" value="sms"
                                                   class="mr-2 notification-channel" data-type="sms"
                                                   @if(!$smsProviderConfigured) disabled @endif>
                                            SMS
                                            @if(!$smsProviderConfigured)
                                                <span class="ml-2 text-xs" style="color: var(--text-secondary);">(no SMS provider configured)</span>
                                            @endif
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="overdue_notification_channels[]" value="whatsapp"
                                                   class="mr-2 notification-channel" data-type="whatsapp"
                                                   @if(!$whatsappProviderConfigured) disabled @endif>
                                            WhatsApp
                                            @if(!$whatsappProviderConfigured)
                                                <span class="ml-2 text-xs" style="color: var(--text-secondary);">(no WhatsApp provider configured)</span>
                                            @endif
                                        </label>
                                    </div>
                                </div>
                                <div class="border rounded p-4">
                                    <h4 class="font-semibold mb-2">Payment Confirmation</h4>
                                    <div class="space-y-2">
                                        <label class="flex items-center"><input type="checkbox" name="payment_confirmation_channels[]" value="email" checked disabled class="mr-2"> Email (always enabled)</label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="payment_confirmation_channels[]" value="sms"
                                                   class="mr-2 notification-channel" data-type="sms"
                                                   @if(!$smsProviderConfigured) disabled @endif>
                                            SMS
                                            @if(!$smsProviderConfigured)
                                                <span class="ml-2 text-xs" style="color: var(--text-secondary);">(no SMS provider configured)</span>
                                            @endif
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="payment_confirmation_channels[]" value="whatsapp"
                                                   class="mr-2 notification-channel" data-type="whatsapp"
                                                   @if(!$whatsappProviderConfigured) disabled @endif>
                                            WhatsApp
                                            @if(!$whatsappProviderConfigured)
                                                <span class="ml-2 text-xs" style="color: var(--text-secondary);">(no WhatsApp provider configured)</span>
                                            @endif
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================================ -->
                        <!-- SMS GLOBAL SETTINGS — only interactive if a provider exists  -->
                        <!-- ============================================================ -->
                        <div class="card p-6" id="smsGlobalSettingsCard" data-provider-configured="{{ $smsProviderConfigured ? '1' : '0' }}">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold mb-0">
                                    <i class="fas fa-mobile-alt mr-2"></i> SMS Global Settings
                                </h3>
                                @if($smsProviderConfigured && $smsActiveProviderName)
                                    <span class="text-xs px-2 py-1 rounded" style="background: rgba(34, 197, 94, 0.1); color: rgb(22, 163, 74);">
                                        Active: {{ $smsActiveProviderName }}
                                    </span>
                                @endif
                            </div>

                            @if(!$smsProviderConfigured)
                                {{-- Locked state: no SMS provider configured --}}
                                <div class="alert alert-warning">
                                    <div class="flex items-start">
                                        <i class="fas fa-exclamation-triangle mr-2 mt-1"></i>
                                        <div>
                                            <p class="font-medium mb-1">No SMS provider configured</p>
                                            <p class="text-sm mb-2">
                                                Configure at least one SMS provider before enabling SMS notifications.
                                            </p>
                                            <a href="{{ route('admin.sms-providers.index') }}" class="btn-info inline-flex items-center text-sm">
                                                <i class="fas fa-cog mr-1"></i> Configure SMS Providers
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                {{-- Hidden/disabled inputs so the form still posts consistent data --}}
                                <input type="hidden" name="sms_notifications_enabled" value="0">
                                <input type="hidden" name="sms_reminder_enabled" value="0">
                                <input type="hidden" name="sms_payment_confirmation_enabled" value="0">
                            @else
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="flex items-center">
                                            {{-- Hidden fallback ensures "off" is submitted when unchecked --}}
                                            <input type="hidden" name="sms_notifications_enabled" value="0">
                                            <input type="checkbox" id="sms_notifications_enabled" name="sms_notifications_enabled" value="1" class="mr-2 sms-global-toggle">
                                            <span class="font-medium">Enable SMS Notifications</span>
                                        </label>
                                    </div>
                                    <div class="sms-dependent-field hidden">
                                        <label for="sms_daily_limit_per_user" class="block mb-2 font-medium">Daily SMS Limit Per User</label>
                                        <input type="number" class="w-full p-2 border rounded" id="sms_daily_limit_per_user" name="sms_daily_limit_per_user" value="10" min="1" max="100">
                                    </div>
                                    <div class="sms-dependent-field hidden">
                                        <label for="sms_hourly_limit_per_user" class="block mb-2 font-medium">Hourly SMS Limit Per User</label>
                                        <input type="number" class="w-full p-2 border rounded" id="sms_hourly_limit_per_user" name="sms_hourly_limit_per_user" value="3" min="1" max="20">
                                    </div>
                                    <div class="sms-dependent-field hidden">
                                        <label class="flex items-center">
                                            <input type="hidden" name="sms_reminder_enabled" value="0">
                                            <input type="checkbox" id="sms_reminder_enabled" name="sms_reminder_enabled" value="1" class="mr-2">
                                            <span>Enable SMS Reminders</span>
                                        </label>
                                    </div>
                                    <div class="sms-dependent-field hidden">
                                        <label class="flex items-center">
                                            <input type="hidden" name="sms_payment_confirmation_enabled" value="0">
                                            <input type="checkbox" id="sms_payment_confirmation_enabled" name="sms_payment_confirmation_enabled" value="1" class="mr-2">
                                            <span>Enable SMS Payment Confirmations</span>
                                        </label>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- ============================================================ -->
                        <!-- WHATSAPP GLOBAL SETTINGS — only interactive if a provider    -->
                        <!-- ============================================================ -->
                        <div class="card p-6" id="whatsappGlobalSettingsCard" data-provider-configured="{{ $whatsappProviderConfigured ? '1' : '0' }}">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold mb-0">
                                    <i class="fab fa-whatsapp mr-2" style="color: #25D366;"></i> WhatsApp Global Settings
                                </h3>
                                @if($whatsappProviderConfigured && $whatsappActiveProviderName)
                                    <span class="text-xs px-2 py-1 rounded" style="background: rgba(34, 197, 94, 0.1); color: rgb(22, 163, 74);">
                                        Active: {{ $whatsappActiveProviderName }}
                                    </span>
                                @endif
                            </div>

                            @if(!$whatsappProviderConfigured)
                                {{-- Locked state: no WhatsApp provider configured --}}
                                <div class="alert alert-warning">
                                    <div class="flex items-start">
                                        <i class="fas fa-exclamation-triangle mr-2 mt-1"></i>
                                        <div>
                                            <p class="font-medium mb-1">No WhatsApp provider configured</p>
                                            <p class="text-sm mb-0">
                                                Configure at least one WhatsApp provider before enabling WhatsApp notifications.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Hidden/disabled inputs so the form still posts consistent data --}}
                                <input type="hidden" name="enable_whatsapp_notifications" value="0">
                                <input type="hidden" name="whatsapp_reminder_enabled" value="0">
                            @else
                                <div class="space-y-4">
                                    <div>
                                        <label class="flex items-center">
                                            {{-- Hidden fallback ensures "off" is submitted when unchecked --}}
                                            <input type="hidden" name="enable_whatsapp_notifications" value="0">
                                            <input type="checkbox" id="enable_whatsapp_notifications" name="enable_whatsapp_notifications" value="1" class="mr-2 whatsapp-global-toggle">
                                            <span class="font-medium">Enable WhatsApp Notifications</span>
                                        </label>
                                    </div>
                                    <div class="whatsapp-dependent-field hidden">
                                        <label class="flex items-center">
                                            <input type="hidden" name="whatsapp_reminder_enabled" value="0">
                                            <input type="checkbox" id="whatsapp_reminder_enabled" name="whatsapp_reminder_enabled" value="1" class="mr-2">
                                            <span>Enable WhatsApp Reminders</span>
                                        </label>
                                    </div>
                                    <div class="whatsapp-dependent-field hidden">
                                        <label for="whatsapp_provider" class="block mb-2 font-medium">WhatsApp Provider</label>
                                        <select class="w-full p-2 border rounded" id="whatsapp_provider" name="whatsapp_provider">
                                            <option value="twilio">Twilio</option>
                                            <option value="vonage">Vonage</option>
                                            <option value="custom">Custom API</option>
                                        </select>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Advanced Notification Settings -->
                        <div class="card p-6">
                            <h3 class="text-lg font-semibold mb-4">Advanced Notification Settings</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="flex items-center">
                                        <input type="hidden" name="force_email_fallback" value="0">
                                        <input type="checkbox" id="force_email_fallback" name="force_email_fallback" value="1" checked class="mr-2">
                                        <span class="font-medium">Force Email Fallback</span>
                                    </label>
                                </div>
                                <div>
                                    <label for="notification_retry_attempts" class="block mb-2 font-medium">Retry Attempts</label>
                                    <input type="number" class="w-full p-2 border rounded" id="notification_retry_attempts" name="notification_retry_attempts" value="3" min="1" max="10">
                                </div>
                                <div>
                                    <label for="notification_retry_delay_minutes" class="block mb-2 font-medium">Retry Delay (Minutes)</label>
                                    <input type="number" class="w-full p-2 border rounded" id="notification_retry_delay_minutes" name="notification_retry_delay_minutes" value="5" min="1" max="60">
                                </div>
                                <div>
                                    <label for="notification_log_retention_days" class="block mb-2 font-medium">Log Retention (Days)</label>
                                    <input type="number" class="w-full p-2 border rounded" id="notification_log_retention_days" name="notification_log_retention_days" value="90" min="30" max="365">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- TENANT DUES TAB -->
                <!-- ============================================================ -->
                <div class="tab-content hidden" id="tab-tenant">
                    <div class="card p-6" style="border-left: 4px solid var(--info);">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">Tenant Dues Configuration</h3>
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                        
                        <div class="mb-4 alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i>
                            Enable tenant invoicing if tenants should also pay monthly dues.
                        </div>
                        
                        <div class="space-y-6">
                            <!-- TENANT INVOICING TOGGLE -->
                            <div>
                                <label class="flex items-center">
                                    <input type="hidden" name="enable_tenant_invoicing" value="0">
                                    <input type="checkbox" id="enable_tenant_invoicing" name="enable_tenant_invoicing" value="1" checked class="mr-2 tenant-invoice-toggle">
                                    <span class="font-medium">Enable Tenant Invoicing</span>
                                </label>
                                <p class="text-xs mt-1 ml-6" style="color: var(--text-secondary);">When enabled, tenants will be invoiced monthly for their dues</p>
                            </div>
                            
                            <div class="tenant-dependent-field grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="tenant_monthly_dues_amount" class="block mb-2 font-medium required-conditional">Tenant Monthly Dues Amount</label>
                                    <input type="number" step="0.01" class="w-full p-2 border rounded" id="tenant_monthly_dues_amount" name="tenant_monthly_dues_amount" value="50">
                                </div>
                                <div>
                                    <label for="tenant_calculation_method" class="block mb-2 font-medium">Calculation Method</label>
                                    <select class="w-full p-2 border rounded" id="tenant_calculation_method" name="tenant_calculation_method">
                                        <option value="fixed" selected>Fixed Amount Per Tenant</option>
                                        <option value="per_property_unit">Per Property Unit</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- TENANT AUTO-GENERATE & REMINDER CHECKBOXES -->
                            <div class="tenant-dependent-field grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-3 border rounded" style="border-left: 3px solid var(--success);">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <label class="flex items-center">
                                                <input type="hidden" name="auto_generate_tenant_invoices" value="0">
                                                <input type="checkbox"
                                                       id="auto_generate_tenant_invoices"
                                                       name="auto_generate_tenant_invoices"
                                                       value="1" checked
                                                       class="mr-2 tenant-auto-invoice-toggle">
                                                <span class="font-medium">Auto-generate tenant invoices</span>
                                            </label>
                                            <p class="text-xs mt-1 ml-6" style="color: var(--text-secondary);">Automatically generate invoices for tenants each month</p>
                                        </div>
                                        <span class="text-xs px-2 py-1 rounded" style="background: rgba(34, 197, 94, 0.1); color: rgb(22, 163, 74);">Tenant</span>
                                    </div>
                                </div>

                                <div class="p-3 border rounded" style="border-left: 3px solid var(--warning);">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <label class="flex items-center">
                                                <input type="hidden" name="send_tenant_payment_reminders" value="0">
                                                <input type="checkbox"
                                                       id="send_tenant_payment_reminders"
                                                       name="send_tenant_payment_reminders"
                                                       value="1" checked
                                                       class="mr-2 tenant-reminder-toggle">
                                                <span class="font-medium">Send tenant payment reminders</span>
                                            </label>
                                            <p class="text-xs mt-1 ml-6" style="color: var(--text-secondary);">Send reminder notifications to tenants before due date</p>
                                        </div>
                                        <span class="text-xs px-2 py-1 rounded" style="background: rgba(245, 158, 11, 0.1); color: rgb(217, 119, 6);">Tenant</span>
                                    </div>
                                </div>
                            </div>

                            <div class="tenant-dependent-field grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="tenant_grace_period_days" class="block mb-2 font-medium">Tenant Grace Period (Days)</label>
                                    <input type="number" class="w-full p-2 border rounded" id="tenant_grace_period_days" name="tenant_grace_period_days" value="7" min="0" max="30">
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Days after due date before late penalties apply to tenants</p>
                                </div>
                                <div>
                                    <label for="tenant_late_payment_percentage" class="block mb-2 font-medium">Tenant Late Payment %</label>
                                    <input type="number" step="0.01" class="w-full p-2 border rounded" id="tenant_late_payment_percentage" name="tenant_late_payment_percentage" value="5" min="0" max="100">
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Percentage penalty for late tenant payments</p>
                                </div>
                            </div>

                            <div class="tenant-dependent-field">
                                <div>
                                    <label for="tenant_fixed_penalty_amount" class="block mb-2 font-medium">Tenant Fixed Penalty Amount</label>
                                    <input type="number" step="0.01" class="w-full p-2 border rounded" id="tenant_fixed_penalty_amount" name="tenant_fixed_penalty_amount" value="0">
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Set to 0 to use percentage penalty only. Use this instead of percentage.</p>
                                </div>
                            </div>
                            
                            <!-- Preview Box -->
                            <div class="tenant-dependent-field p-4 border rounded" style="border: 1px dashed var(--info);">
                                <div class="flex items-center text-sm mb-2">
                                    <i class="fas fa-calculator text-info mr-2"></i>
                                    <span>Tenant invoice calculation preview:</span>
                                </div>
                                <div id="tenantInvoicePreview" class="text-xs grid grid-cols-2 gap-2">
                                    <div>Landlord monthly dues: <span id="previewLandlordAmount">GH₵100.00</span></div>
                                    <div>Tenant method: <span id="previewTenantMethod">Fixed Amount</span></div>
                                    <div>Tenant monthly dues: <span id="previewTenantAmount" class="font-bold">GH₵50.00</span></div>
                                    <div>Auto-generate tenant invoices: <span id="previewTenantAutoGenerate" class="text-success">Yes</span></div>
                                    <div>Send tenant reminders: <span id="previewTenantReminders" class="text-success">Yes</span></div>
                                    <div>Tenant grace period: <span id="previewTenantGracePeriod">7 days</span></div>
                                    <div>Late payment %: <span id="previewTenantLatePenalty">Not set</span></div>
                                    <div>Fixed penalty: <span id="previewTenantFixedPenalty">Not set</span></div>
                                    <div class="col-span-2">Total penalty: <span id="previewTenantTotalPenalty">No penalty set</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- ADVANCED TAB -->
                <!-- ============================================================ -->
                <div class="tab-content hidden" id="tab-advanced">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="card p-6">
                            <h3 class="text-lg font-semibold mb-4">
                                <i class="fas fa-code mr-2"></i> API Settings
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="flex items-center">
                                        <input type="hidden" name="enable_api_access" value="0">
                                        <input type="checkbox" id="enable_api_access" name="enable_api_access" value="1" class="mr-2">
                                        <span class="font-medium">Enable API Access</span>
                                    </label>
                                </div>
                                <div>
                                    <label for="api_rate_limit" class="block mb-2 font-medium">API Rate Limit (requests per minute)</label>
                                    <input type="number" class="w-full p-2 border rounded" id="api_rate_limit" name="api_rate_limit" value="60" min="1" max="1000">
                                </div>
                                <div>
                                    <label for="api_token_expiry_days" class="block mb-2 font-medium">API Token Expiry (Days)</label>
                                    <input type="number" class="w-full p-2 border rounded" id="api_token_expiry_days" name="api_token_expiry_days" value="30" min="1" max="365">
                                </div>
                            </div>
                        </div>
                        
                        <div class="card p-6">
                            <h3 class="text-lg font-semibold mb-4">
                                <i class="fas fa-shield-alt mr-2"></i> Security Settings
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="flex items-center">
                                        <input type="hidden" name="force_2fa" value="0">
                                        <input type="checkbox" id="force_2fa" name="force_2fa" value="1" class="mr-2">
                                        <span class="font-medium">Force 2FA for Admin Users</span>
                                    </label>
                                </div>
                                <div>
                                    <label for="password_expiry_days" class="block mb-2 font-medium">Password Expiry (Days)</label>
                                    <input type="number" class="w-full p-2 border rounded" id="password_expiry_days" name="password_expiry_days" value="90" min="0" max="365">
                                </div>
                                <div>
                                    <label for="max_login_attempts" class="block mb-2 font-medium">Max Login Attempts</label>
                                    <input type="number" class="w-full p-2 border rounded" id="max_login_attempts" name="max_login_attempts" value="5" min="3" max="10">
                                </div>
                                <div>
                                    <label for="session_timeout_minutes" class="block mb-2 font-medium">Session Timeout (Minutes)</label>
                                    <input type="number" class="w-full p-2 border rounded" id="session_timeout_minutes" name="session_timeout_minutes" value="30" min="5" max="120">
                                </div>
                            </div>
                        </div>
                        
                        <div class="card p-6">
                            <h3 class="text-lg font-semibold mb-4">
                                <i class="fas fa-database mr-2"></i> Backup Settings
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="flex items-center">
                                        <input type="hidden" name="auto_backup_enabled" value="0">
                                        <input type="checkbox" id="auto_backup_enabled" name="auto_backup_enabled" value="1" checked class="mr-2">
                                        <span class="font-medium">Enable Automatic Backups</span>
                                    </label>
                                </div>
                                <div>
                                    <label for="backup_frequency" class="block mb-2 font-medium">Backup Frequency</label>
                                    <select class="w-full p-2 border rounded" id="backup_frequency" name="backup_frequency">
                                        <option value="daily">Daily</option>
                                        <option value="weekly" selected>Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="backup_retention_days" class="block mb-2 font-medium">Backup Retention (Days)</label>
                                    <input type="number" class="w-full p-2 border rounded" id="backup_retention_days" name="backup_retention_days" value="30" min="7" max="365">
                                </div>
                            </div>
                        </div>
                        
                        <div class="card p-6">
                            <h3 class="text-lg font-semibold mb-4">
                                <i class="fas fa-chart-line mr-2"></i> Logging & Monitoring
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="flex items-center">
                                        <input type="hidden" name="enable_audit_log" value="0">
                                        <input type="checkbox" id="enable_audit_log" name="enable_audit_log" value="1" checked class="mr-2">
                                        <span class="font-medium">Enable Audit Log</span>
                                    </label>
                                </div>
                                <div>
                                    <label for="log_retention_days" class="block mb-2 font-medium">Log Retention (Days)</label>
                                    <input type="number" class="w-full p-2 border rounded" id="log_retention_days" name="log_retention_days" value="90" min="30" max="365">
                                </div>
                                <div>
                                    <label class="flex items-center">
                                        <input type="hidden" name="enable_error_reporting" value="0">
                                        <input type="checkbox" id="enable_error_reporting" name="enable_error_reporting" value="1" checked class="mr-2">
                                        <span class="font-medium">Enable Error Reporting</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="card p-6 mt-6">
            <div class="flex justify-end space-x-4">
                <button type="button" id="previewButtonFooter" class="btn-info flex items-center">
                    <i class="fas fa-eye mr-2"></i> Preview Settings
                </button>
                <button type="submit" id="submitButton" class="btn-primary flex items-center">
                    <i class="fas fa-save mr-2"></i> Create System Settings
                </button>
                <a href="{{ route('admin.system-settings.index') }}" class="btn-secondary">Cancel</a>
            </div>
        </div>
    </form>
</div>

<!-- ========================================================== -->
<!-- PREVIEW MODAL -->
<!-- ========================================================== -->
<div id="previewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="display: none;">
    <div class="rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden" 
         id="previewModalContainer"
         style="background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                color: var(--text-primary);">
        
        <!-- Modal Header -->
        <div class="flex justify-between items-center p-4 border-b" 
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <h3 class="text-xl font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-eye mr-2" style="color: var(--primary);"></i> System Settings Preview
            </h3>
            <button type="button" id="closeModal" class="hover:opacity-70 transition-opacity" style="color: var(--text-secondary);">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6 overflow-y-auto" 
             id="previewModalBody"
             style="max-height: calc(90vh - 120px);
                    background-color: var(--bg-primary);
                    color: var(--text-primary);">
            <div id="previewContent" class="space-y-4"></div>
        </div>
        
        <!-- Modal Footer -->
        <div class="flex justify-end p-4 border-t" 
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <button type="button" id="submitFromModal" class="btn-primary mr-2">
                <i class="fas fa-save mr-2"></i> Submit Settings
            </button>
            <button type="button" id="closeModalBtn" class="btn-secondary">Close</button>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
// Provider configuration flags (injected from server)
window.SYSTEM_SETTINGS_FLAGS = {
    smsProviderConfigured: @json($smsProviderConfigured),
    whatsappProviderConfigured: @json($whatsappProviderConfigured),
};

document.addEventListener('DOMContentLoaded', function() {
    // Tab Navigation
    const tabs = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    function activateTab(tabId) {
        tabContents.forEach(content => content.classList.add('hidden'));
        document.getElementById(`tab-${tabId}`).classList.remove('hidden');
        
        tabs.forEach(tab => {
            tab.classList.remove('active-tab');
            tab.style.borderBottom = 'none';
            tab.style.color = '';
        });
        
        const activeTab = document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
        activeTab.classList.add('active-tab');
        activeTab.style.borderBottom = '2px solid var(--primary)';
        activeTab.style.color = 'var(--primary)';
    }
    
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const tabId = tab.getAttribute('data-tab');
            activateTab(tabId);
        });
    });
    
    activateTab('general');
    
    // Initialize all features
    initializeSlugGeneration();
    initializeCurrencySync();
    initializeCalculationFields();
    initializeTenantInvoiceFields();
    initializeLandlordInvoiceFields();
    initializeNotificationChannels(); // owns SMS + WhatsApp init & listeners
    initializePaymentGatewayValidation();
    initializeLogoUpload();
    initializeFaviconUpload();
    initializeSmsSenderId();
    initializePreviewModal();
    initializeReminderDependentFields();
    
    // Toggle switches styling
    document.querySelectorAll('.toggle-modern input').forEach(checkbox => {
        updateToggleSwitch(checkbox);
        checkbox.addEventListener('change', () => updateToggleSwitch(checkbox));
    });
    
    const tenantToggle = document.getElementById('enable_tenant_invoicing');
    if (tenantToggle) {
        tenantToggle.addEventListener('change', function() {
            initializeTenantInvoiceFields();
        });
    }
    
    const reminderToggle = document.getElementById('send_payment_reminders');
    if (reminderToggle) {
        reminderToggle.addEventListener('change', function() {
            initializeReminderDependentFields();
        });
    }
    
    // Form submission validation
    const form = document.getElementById('systemSettingsForm');
    if (form) {
        form.addEventListener('submit', handleFormSubmission);
    }
    
    // Real-time preview updates for tenant section
    const tenantPreviewInputs = [
        'monthly_dues_amount',
        'tenant_monthly_dues_amount',
        'currency_symbol',
        'tenant_calculation_method',
        'tenant_grace_period_days',
        'tenant_late_payment_percentage',
        'tenant_fixed_penalty_amount'
    ];
    
    tenantPreviewInputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', function() {
                if (document.getElementById('enable_tenant_invoicing')?.checked) {
                    updateTenantPreview();
                }
            });
        }
    });
    
    // ============================================================
    // FIX: target the checkbox specifically.
    //
    // Each of these names has TWO inputs in the DOM: a hidden
    // <input type="hidden" value="0"> and the visible checkbox.
    // A bare querySelector('input[name="..."]') matches the hidden
    // one first, so .checked always reads false. Adding
    // [type="checkbox"] ensures we hit the real checkbox.
    // ============================================================
    document.querySelectorAll(
        'input[type="checkbox"][name="auto_generate_tenant_invoices"], ' +
        'input[type="checkbox"][name="send_tenant_payment_reminders"]'
    ).forEach(el => {
        el.addEventListener('change', updateTenantPreview);
    });
});

// ============================================================
// DEFAULT SMS SENDER ID
// ============================================================
function initializeSmsSenderId() {
    const input    = document.getElementById('sms_sender_id');
    const counter  = document.getElementById('smsSenderIdCounter');
    const warning  = document.getElementById('smsSenderIdWarning');
    const warnText = document.getElementById('smsSenderIdWarningText');
    if (!input) return;

    const GSM7 = /^[A-Za-z0-9 _\-@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&'()*+,\-./:;<=>?¡ÄÖÑÜ§¿äöñüà]*$/;

    function update() {
        const val = input.value;

        if (counter) {
            counter.textContent = `${val.length}/11`;
            counter.style.color = val.length >= 11 ? 'var(--warning)' : 'var(--text-secondary)';
        }

        let msg = '';
        if (/\s/.test(val)) {
            msg = 'Spaces are allowed here but many SMS gateways reject them. Consider using an underscore instead.';
        } else if (val && !GSM7.test(val)) {
            msg = 'This sender ID contains non-GSM characters. Some gateways may not support it.';
        }

        if (warning && warnText) {
            if (msg) {
                warnText.textContent = msg;
                warning.classList.remove('hidden');
            } else {
                warning.classList.add('hidden');
            }
        }
    }

    input.addEventListener('input', update);
    update();
}

// ============================================================
// FAVICON UPLOAD
// ============================================================
function initializeFaviconUpload() {
    const uploadArea = document.getElementById('faviconUploadArea');
    const faviconInput = document.getElementById('system_favicon');
    
    if (uploadArea) {
        uploadArea.addEventListener('click', () => faviconInput?.click());
        uploadArea.addEventListener('dragover', (e) => { 
            e.preventDefault(); 
            uploadArea.classList.add('border-primary'); 
        });
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('border-primary');
        });
        uploadArea.addEventListener('drop', (e) => { 
            e.preventDefault(); 
            uploadArea.classList.remove('border-primary'); 
            if (e.dataTransfer.files[0]) { 
                faviconInput.files = e.dataTransfer.files; 
                previewFavicon(faviconInput); 
            } 
        });
    }
}

function previewFavicon(input) {
    const preview = document.getElementById('faviconPreview');
    const previewImage = document.getElementById('faviconPreviewImage');
    const fileName = document.getElementById('faviconFileName');
    const uploadArea = document.getElementById('faviconUploadArea');
    const removeBtn = document.getElementById('removeFaviconPreview');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        if (file.size > 100 * 1024) { 
            alert('Favicon size must be less than 100KB. Please compress or use a smaller file.');
            input.value = ''; 
            return; 
        }
        
        const validTypes = ['image/x-icon', 'image/png', 'image/svg+xml', 'image/jpeg', 'image/gif'];
        if (!validTypes.includes(file.type) && !file.name.match(/\.(ico|png|svg|jpg|jpeg|gif)$/i)) {
            alert('Please select an ICO, PNG, SVG, JPG, or GIF file for favicon.');
            input.value = ''; 
            return; 
        }
        
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImage.src = e.target.result;
            fileName.textContent = file.name + ` (${(file.size / 1024).toFixed(1)} KB)`;
            preview.classList.remove('hidden');
            if (uploadArea) uploadArea.classList.add('hidden');
        };
        reader.readAsDataURL(file);
        
        if (removeBtn) {
            removeBtn.onclick = () => {
                preview.classList.add('hidden');
                if (uploadArea) uploadArea.classList.remove('hidden');
                input.value = '';
            };
        }
    }
}

// ============================================================
// PREVIEW MODAL
// ============================================================
function initializePreviewModal() {
    const modal = document.getElementById('previewModal');
    const previewBtns = document.querySelectorAll('#previewButtonHeader, #previewButtonFooter');
    const closeBtns = document.querySelectorAll('#closeModal, #closeModalBtn');
    const submitFromModal = document.getElementById('submitFromModal');
    
    function getThemeStyles() {
        const root = document.documentElement;
        const styles = getComputedStyle(root);
        
        return {
            bgPrimary: styles.getPropertyValue('--bg-primary').trim() || '#ffffff',
            bgSecondary: styles.getPropertyValue('--bg-secondary').trim() || '#f3f4f6',
            cardBg: styles.getPropertyValue('--card-bg').trim() || '#ffffff',
            textPrimary: styles.getPropertyValue('--text-primary').trim() || '#1f2937',
            textSecondary: styles.getPropertyValue('--text-secondary').trim() || '#6b7280',
            borderColor: styles.getPropertyValue('--border-color').trim() || '#e5e7eb',
            primary: styles.getPropertyValue('--primary').trim() || '#6366f1',
            success: styles.getPropertyValue('--success').trim() || '#22c55e',
            danger: styles.getPropertyValue('--danger').trim() || '#ef4444',
            warning: styles.getPropertyValue('--warning').trim() || '#f59e0b',
            info: styles.getPropertyValue('--info').trim() || '#3b82f6',
        };
    }
    
    function collectSettings() {
        const tenantEnabled = document.getElementById('enable_tenant_invoicing')?.checked || false;
        
        const faviconInput = document.getElementById('system_favicon');
        const hasFavicon = faviconInput && faviconInput.files && faviconInput.files.length > 0;
        const faviconName = hasFavicon ? faviconInput.files[0].name : 'Not uploaded';
        
        const settings = {
            general: {
                system_name: document.getElementById('system_name')?.value || 'Not set',
                system_short_name: document.getElementById('system_short_name')?.value || 'Not set',
                system_email: document.getElementById('system_email')?.value || 'Not set',
                system_phone: document.getElementById('system_phone')?.value || 'Not set',
                system_address: document.getElementById('system_address')?.value || 'Not set',
                sms_sender_id: document.getElementById('sms_sender_id')?.value || 'Not set',
                has_favicon: hasFavicon,
                favicon_name: faviconName
            },
            operations: {
                auto_generate_invoices: document.getElementById('auto_generate_invoices')?.checked || false,
                send_payment_reminders: document.getElementById('send_payment_reminders')?.checked || false,
                reminder_days_before: document.getElementById('reminder_days_before')?.value || '7',
                grace_period_days: document.getElementById('grace_period_days')?.value || '7'
            },
            payment: {
                currency_code: document.getElementById('currency_code')?.value || 'Not set',
                currency_symbol: document.getElementById('currency_symbol')?.value || 'Not set',
                currency_position: document.getElementById('currency_position')?.value || 'Not set',
                monthly_dues: document.getElementById('monthly_dues_amount')?.value || '0',
                late_penalty: document.getElementById('late_payment_percentage')?.value || '0%'
            },
            gateways: Array.from(document.querySelectorAll('.payment-gateway-checkbox:checked')).map(cb => {
                const label = cb.closest('.flex')?.querySelector('span')?.innerText || 'Unknown';
                return label;
            }).filter(Boolean),
            notifications: {
                sms_enabled: document.getElementById('sms_notifications_enabled')?.checked ? 'Yes' : 'No',
                sms_provider_configured: window.SYSTEM_SETTINGS_FLAGS.smsProviderConfigured,
                whatsapp_enabled: document.getElementById('enable_whatsapp_notifications')?.checked ? 'Yes' : 'No',
                whatsapp_provider_configured: window.SYSTEM_SETTINGS_FLAGS.whatsappProviderConfigured,
                force_email_fallback: document.getElementById('force_email_fallback')?.checked ? 'Yes' : 'No'
            },
            tenant: tenantEnabled ? {
                enabled: true,
                monthly_amount: document.getElementById('tenant_monthly_dues_amount')?.value || '0',
                calculation_method: document.getElementById('tenant_calculation_method')?.value === 'fixed' ? 'Fixed Amount' : 'Per Property Unit',
                // FIX: target the checkbox specifically, otherwise the hidden
                // value="0" input with the same name is matched first.
                auto_generate: document.querySelector('input[type="checkbox"][name="auto_generate_tenant_invoices"]')?.checked || false,
                send_reminders: document.querySelector('input[type="checkbox"][name="send_tenant_payment_reminders"]')?.checked || false,
                grace_period: document.getElementById('tenant_grace_period_days')?.value || '7',
                late_percentage: document.getElementById('tenant_late_payment_percentage')?.value || '0',
                fixed_penalty: document.getElementById('tenant_fixed_penalty_amount')?.value || '0'
            } : { enabled: false },
            advanced: {
                api_enabled: document.getElementById('enable_api_access')?.checked ? 'Yes' : 'No',
                force_2fa: document.getElementById('force_2fa')?.checked ? 'Yes' : 'No',
                auto_backup: document.getElementById('auto_backup_enabled')?.checked ? 'Yes' : 'No'
            }
        };
        return settings;
    }
    
    function renderPreview() {
        const settings = collectSettings();
        const previewContent = document.getElementById('previewContent');
        const styles = getThemeStyles();
        
        if (!previewContent) return;
        
        const currencySymbol = settings.payment.currency_symbol || 'GH₵';
        
        const tenantHtml = settings.tenant.enabled ? `
            <div class="grid grid-cols-2 gap-2 text-sm">
                <span class="font-medium" style="color: ${styles.textSecondary};">Tenant Monthly Dues:</span>
                <span style="color: ${styles.textPrimary};">${currencySymbol}${settings.tenant.monthly_amount}</span>
                
                <span class="font-medium" style="color: ${styles.textSecondary};">Calculation Method:</span>
                <span style="color: ${styles.textPrimary};">${settings.tenant.calculation_method}</span>
                
                <span class="font-medium" style="color: ${styles.textSecondary};">Auto-Generate Invoices:</span>
                <span style="color: ${settings.tenant.auto_generate ? styles.success : styles.danger};">
                    ${settings.tenant.auto_generate ? 'Yes ✅' : 'No ❌'}
                </span>
                
                <span class="font-medium" style="color: ${styles.textSecondary};">Send Reminders:</span>
                <span style="color: ${settings.tenant.send_reminders ? styles.success : styles.danger};">
                    ${settings.tenant.send_reminders ? 'Yes ✅' : 'No ❌'}
                </span>
                
                <span class="font-medium" style="color: ${styles.textSecondary};">Grace Period:</span>
                <span style="color: ${styles.textPrimary};">${settings.tenant.grace_period} days</span>
                
                <span class="font-medium" style="color: ${styles.textSecondary};">Late Percentage:</span>
                <span style="color: ${styles.textPrimary};">${settings.tenant.late_percentage}%</span>
                
                <span class="font-medium" style="color: ${styles.textSecondary};">Fixed Penalty:</span>
                <span style="color: ${styles.textPrimary};">${currencySymbol}${settings.tenant.fixed_penalty}</span>
            </div>
        ` : `
            <div class="text-sm" style="color: ${styles.textSecondary};">⚠️ Tenant invoicing is disabled</div>
        `;
        
        const gatewayHtml = settings.gateways.length 
            ? `<div class="mt-2"><span class="font-medium" style="color: ${styles.textSecondary};">Enabled Gateways:</span> <span style="color: ${styles.textPrimary};">${settings.gateways.join(', ')}</span></div>` 
            : `<div class="mt-2" style="color: ${styles.danger};">⚠️ No payment gateways enabled</div>`;
        
        const faviconHtml = settings.general.has_favicon
            ? `<span style="color: ${styles.success};">✅ Uploaded (${settings.general.favicon_name})</span>`
            : `<span style="color: ${styles.textSecondary};">❌ Not uploaded (browser will use default)</span>`;
        
        // SMS / WhatsApp provider status lines
        const smsHtml = settings.notifications.sms_provider_configured
            ? `<span style="color: ${styles.textPrimary};">${settings.notifications.sms_enabled}</span>`
            : `<span style="color: ${styles.danger};">⚠️ No SMS provider configured</span>`;
        const whatsappHtml = settings.notifications.whatsapp_provider_configured
            ? `<span style="color: ${styles.textPrimary};">${settings.notifications.whatsapp_enabled}</span>`
            : `<span style="color: ${styles.danger};">⚠️ No WhatsApp provider configured</span>`;
        
        const cardStyle = `
            background-color: ${styles.bgSecondary};
            border: 1px solid ${styles.borderColor};
            border-radius: 0.5rem;
            padding: 1rem;
        `;
        
        const titleStyle = `
            font-weight: bold;
            font-size: 1.125rem;
            margin-bottom: 0.75rem;
            color: ${styles.textPrimary};
        `;
        
        previewContent.innerHTML = `
            <div style="${cardStyle}">
                <h4 style="${titleStyle}">📋 System Information</h4>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <span style="color: ${styles.textSecondary};">System Name:</span>
                    <span style="color: ${styles.textPrimary};">${settings.general.system_name}</span>
                    
                    <span style="color: ${styles.textSecondary};">Short Name:</span>
                    <span style="color: ${styles.textPrimary};">${settings.general.system_short_name}</span>
                    
                    <span style="color: ${styles.textSecondary};">System Email:</span>
                    <span style="color: ${styles.textPrimary};">${settings.general.system_email}</span>
                    
                    <span style="color: ${styles.textSecondary};">System Phone:</span>
                    <span style="color: ${styles.textPrimary};">${settings.general.system_phone}</span>
                    
                    <span style="color: ${styles.textSecondary};">Default SMS Sender ID:</span>
                    <span style="color: ${styles.textPrimary};">${settings.general.sms_sender_id}</span>
                    
                    <span style="color: ${styles.textSecondary};">System Address:</span>
                    <span style="color: ${styles.textPrimary};">${settings.general.system_address}</span>
                    
                    <span style="color: ${styles.textSecondary};">Favicon:</span>
                    <span style="color: ${styles.textPrimary};">${faviconHtml}</span>
                </div>
            </div>
            
            <div style="${cardStyle}">
                <h4 style="${titleStyle}">⚙️ System Operations</h4>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <span style="color: ${styles.textSecondary};">Auto-Generate Invoices:</span>
                    <span style="color: ${settings.operations.auto_generate_invoices ? styles.success : styles.danger};">
                        ${settings.operations.auto_generate_invoices ? 'Yes ✅' : 'No ❌'}
                    </span>
                    
                    <span style="color: ${styles.textSecondary};">Send Payment Reminders:</span>
                    <span style="color: ${settings.operations.send_payment_reminders ? styles.success : styles.danger};">
                        ${settings.operations.send_payment_reminders ? 'Yes ✅' : 'No ❌'}
                    </span>
                    
                    <span style="color: ${styles.textSecondary};">Reminder Days Before:</span>
                    <span style="color: ${styles.textPrimary};">${settings.operations.send_payment_reminders ? settings.operations.reminder_days_before + ' days' : 'Disabled'}</span>
                    
                    <span style="color: ${styles.textSecondary};">Grace Period:</span>
                    <span style="color: ${styles.textPrimary};">${settings.operations.grace_period_days} days</span>
                </div>
            </div>
            
            <div style="${cardStyle}">
                <h4 style="${titleStyle}">💰 Payment Configuration</h4>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <span style="color: ${styles.textSecondary};">Currency:</span>
                    <span style="color: ${styles.textPrimary};">${settings.payment.currency_code} (${settings.payment.currency_symbol})</span>
                    
                    <span style="color: ${styles.textSecondary};">Currency Position:</span>
                    <span style="color: ${styles.textPrimary};">${settings.payment.currency_position}</span>
                    
                    <span style="color: ${styles.textSecondary};">Monthly Dues:</span>
                    <span style="color: ${styles.textPrimary};">${settings.payment.currency_symbol}${settings.payment.monthly_dues}</span>
                    
                    <span style="color: ${styles.textSecondary};">Late Penalty:</span>
                    <span style="color: ${styles.textPrimary};">${settings.payment.late_penalty}%</span>
                </div>
                ${gatewayHtml}
            </div>
            
            <div style="${cardStyle}">
                <h4 style="${titleStyle}">🔔 Notification Settings</h4>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <span style="color: ${styles.textSecondary};">SMS Notifications:</span>
                    ${smsHtml}
                    
                    <span style="color: ${styles.textSecondary};">WhatsApp Notifications:</span>
                    ${whatsappHtml}
                    
                    <span style="color: ${styles.textSecondary};">Force Email Fallback:</span>
                    <span style="color: ${styles.textPrimary};">${settings.notifications.force_email_fallback}</span>
                </div>
            </div>
            
            <div style="${cardStyle}">
                <h4 style="${titleStyle}">👥 Tenant Dues Configuration</h4>
                ${tenantHtml}
            </div>
            
            <div style="${cardStyle}">
                <h4 style="${titleStyle}">⚙️ Advanced Settings</h4>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <span style="color: ${styles.textSecondary};">API Access:</span>
                    <span style="color: ${styles.textPrimary};">${settings.advanced.api_enabled}</span>
                    
                    <span style="color: ${styles.textSecondary};">Force 2FA:</span>
                    <span style="color: ${styles.textPrimary};">${settings.advanced.force_2fa}</span>
                    
                    <span style="color: ${styles.textSecondary};">Auto Backup:</span>
                    <span style="color: ${styles.textPrimary};">${settings.advanced.auto_backup}</span>
                </div>
            </div>
        `;
    }
    
    function openModal() {
        renderPreview();
        if (modal) modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal() {
        if (modal) modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    
    previewBtns.forEach(btn => btn?.addEventListener('click', openModal));
    closeBtns.forEach(btn => btn?.addEventListener('click', closeModal));
    
    if (submitFromModal) {
        submitFromModal.addEventListener('click', () => { 
            closeModal(); 
            document.getElementById('systemSettingsForm')?.submit(); 
        });
    }
    
    window.addEventListener('click', (e) => { 
        if (e.target === modal) closeModal(); 
    });
    
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal && modal.style.display === 'flex') {
            closeModal();
        }
    });
}

// ============================================================
// LANDLORD INVOICE FIELDS
// ============================================================
function initializeLandlordInvoiceFields() {
    const autoGenerateToggle = document.getElementById('auto_generate_invoices');
    const reminderToggle = document.getElementById('send_payment_reminders');
    const reminderDaysField = document.getElementById('reminder_days_before');
    const gracePeriodField = document.getElementById('grace_period_days');
    
    if (autoGenerateToggle) {
        autoGenerateToggle.addEventListener('change', function() {
            updateLandlordPreview();
        });
    }
    
    if (reminderToggle) {
        reminderToggle.addEventListener('change', function() {
            initializeReminderDependentFields();
            updateLandlordPreview();
        });
    }
    
    if (reminderDaysField) {
        reminderDaysField.addEventListener('input', updateLandlordPreview);
    }
    
    if (gracePeriodField) {
        gracePeriodField.addEventListener('input', updateLandlordPreview);
    }
    
    updateLandlordPreview();
}

function updateLandlordPreview() {
    const autoGenerate = document.getElementById('auto_generate_invoices')?.checked || false;
    const reminders = document.getElementById('send_payment_reminders')?.checked || false;
    const reminderDays = document.getElementById('reminder_days_before')?.value || '7';
    const gracePeriod = document.getElementById('grace_period_days')?.value || '7';
    
    const previewAutoGenerate = document.getElementById('previewLandlordAutoGenerate');
    const previewReminders = document.getElementById('previewLandlordReminders');
    const previewReminderDays = document.getElementById('previewLandlordReminderDays');
    const previewGracePeriod = document.getElementById('previewLandlordGracePeriod');
    
    if (previewAutoGenerate) {
        previewAutoGenerate.textContent = autoGenerate ? 'Yes ✅' : 'No ❌';
        previewAutoGenerate.style.color = autoGenerate ? 'rgb(22, 163, 74)' : 'rgb(220, 38, 38)';
    }
    
    if (previewReminders) {
        previewReminders.textContent = reminders ? 'Yes ✅' : 'No ❌';
        previewReminders.style.color = reminders ? 'rgb(22, 163, 74)' : 'rgb(220, 38, 38)';
    }
    
    if (previewReminderDays) {
        previewReminderDays.textContent = reminders ? `${reminderDays} days` : 'Disabled';
    }
    
    if (previewGracePeriod) {
        previewGracePeriod.textContent = `${gracePeriod} days`;
    }
}

function initializeReminderDependentFields() {
    const reminderToggle = document.getElementById('send_payment_reminders');
    const reminderFields = document.querySelectorAll('.reminder-dependent-field');
    
    if (reminderToggle) {
        const enabled = reminderToggle.checked;
        reminderFields.forEach(field => {
            field.style.display = enabled ? 'block' : 'none';
        });
    }
}

function initializeTenantInvoiceFields() {
    const tenantToggle = document.getElementById('enable_tenant_invoicing');
    const tenantFields = document.querySelectorAll('.tenant-dependent-field');
    const tenantAmountField = document.getElementById('tenant_monthly_dues_amount');
    const tenantMethodField = document.getElementById('tenant_calculation_method');
    const tenantGraceField  = document.getElementById('tenant_grace_period_days');
    const tenantLateField   = document.getElementById('tenant_late_payment_percentage');
    const tenantFixedPenaltyField = document.getElementById('tenant_fixed_penalty_amount');
    
    function toggleTenantFields() {
        const enabled = tenantToggle && tenantToggle.checked;
        tenantFields.forEach(field => {
            field.style.display = enabled ? 'block' : 'none';
        });
        
        if (tenantAmountField) {
            tenantAmountField.required = enabled;
            const label = tenantAmountField.closest('div')?.querySelector('label');
            if (label) {
                if (enabled) {
                    label.classList.add('required-conditional');
                } else {
                    label.classList.remove('required-conditional');
                }
            }
        }
        
        updateTenantPreview();
    }
    
    if (tenantToggle) {
        tenantToggle.addEventListener('change', toggleTenantFields);
        toggleTenantFields();
    }
    
    const tenantInputs = [
        tenantAmountField,
        tenantMethodField,
        tenantGraceField,
        tenantLateField,
        tenantFixedPenaltyField
    ];
    
    tenantInputs.forEach(el => {
        if (el) {
            el.addEventListener('input', function() {
                if (tenantToggle && tenantToggle.checked) {
                    updateTenantPreview();
                }
            });
        }
    });
    
    // NOTE: the two tenant checkboxes (auto_generate_tenant_invoices,
    // send_tenant_payment_reminders) are wired up in DOMContentLoaded
    // with a `[type="checkbox"]` selector to avoid matching the hidden
    // value="0" inputs. Do NOT bind them here without that qualifier.
}

function updateTenantPreview() {
    const tenantToggle = document.getElementById('enable_tenant_invoicing');
    
    if (!tenantToggle || !tenantToggle.checked) {
        const ids = [
            'previewTenantAmount',
            'previewTenantAutoGenerate',
            'previewTenantReminders',
            'previewTenantGracePeriod',
            'previewTenantLatePenalty',
            'previewTenantFixedPenalty',
            'previewTenantTotalPenalty'
        ];
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = 'Disabled';
                el.style.color = 'var(--text-secondary)';
            }
        });
        return;
    }
    
    const currencySymbol = document.getElementById('currency_symbol')?.value || 'GH₵';
    const landlordAmount = parseFloat(document.getElementById('monthly_dues_amount')?.value) || 0;
    const tenantAmount   = parseFloat(document.getElementById('tenant_monthly_dues_amount')?.value) || 0;
    const tenantMethod   = document.getElementById('tenant_calculation_method')?.value || 'fixed';
    
    // FIX: target the checkbox specifically. There is also a hidden
    // <input type="hidden" name="..." value="0"> with the same name, and a
    // bare querySelector would match that hidden input first — which would
    // always read as unchecked and falsely display "No ❌".
    const autoGenerate  = document.querySelector('input[type="checkbox"][name="auto_generate_tenant_invoices"]')?.checked  || false;
    const sendReminders = document.querySelector('input[type="checkbox"][name="send_tenant_payment_reminders"]')?.checked || false;
    
    const gracePeriod    = document.getElementById('tenant_grace_period_days')?.value || '7';
    const latePercentage = parseFloat(document.getElementById('tenant_late_payment_percentage')?.value) || 0;
    const fixedPenalty   = parseFloat(document.getElementById('tenant_fixed_penalty_amount')?.value) || 0;
    
    const previewLandlord  = document.getElementById('previewLandlordAmount');
    const previewMethod    = document.getElementById('previewTenantMethod');
    const previewAmount    = document.getElementById('previewTenantAmount');
    const previewAutoGen   = document.getElementById('previewTenantAutoGenerate');
    const previewReminders = document.getElementById('previewTenantReminders');
    const previewGrace     = document.getElementById('previewTenantGracePeriod');
    const previewLate      = document.getElementById('previewTenantLatePenalty');
    const previewFixed     = document.getElementById('previewTenantFixedPenalty');
    const previewTotal     = document.getElementById('previewTenantTotalPenalty');
    
    if (previewLandlord) previewLandlord.textContent = formatCurrency(landlordAmount, currencySymbol);
    if (previewMethod)   previewMethod.textContent   = tenantMethod === 'fixed' ? 'Fixed Amount' : 'Per Property Unit';
    
    if (previewAmount) {
        previewAmount.textContent = formatCurrency(tenantAmount, currencySymbol);
        previewAmount.style.color = tenantAmount > 0 ? 'var(--text-primary)' : 'rgb(220, 38, 38)';
    }
    
    // ---- Auto-generate tenant invoices: ✅ / ❌ ----
    if (previewAutoGen) {
        previewAutoGen.textContent = autoGenerate ? 'Yes ✅' : 'No ❌';
        previewAutoGen.style.color = autoGenerate ? 'rgb(22, 163, 74)' : 'rgb(220, 38, 38)';
        previewAutoGen.classList.toggle('text-success', autoGenerate);
    }
    
    // ---- Send tenant payment reminders: ✅ / ❌ ----
    if (previewReminders) {
        previewReminders.textContent = sendReminders ? 'Yes ✅' : 'No ❌';
        previewReminders.style.color = sendReminders ? 'rgb(22, 163, 74)' : 'rgb(220, 38, 38)';
        previewReminders.classList.toggle('text-success', sendReminders);
    }
    
    if (previewGrace) previewGrace.textContent = `${gracePeriod} days`;
    
    const hasPercentagePenalty = latePercentage > 0;
    const hasFixedPenalty      = fixedPenalty > 0;
    
    if (previewLate) {
        previewLate.textContent = hasPercentagePenalty ? `${latePercentage}%` : 'Not set';
        previewLate.style.color = hasPercentagePenalty ? 'rgb(217, 119, 6)' : 'var(--text-secondary)';
    }
    
    if (previewFixed) {
        previewFixed.textContent = hasFixedPenalty ? formatCurrency(fixedPenalty, currencySymbol) : 'Not set';
        previewFixed.style.color = hasFixedPenalty ? 'rgb(217, 119, 6)' : 'var(--text-secondary)';
    }
    
    if (previewTotal) {
        if (hasPercentagePenalty && hasFixedPenalty) {
            previewTotal.textContent = '⚠️ Both set (use only one)';
            previewTotal.style.color = 'rgb(220, 38, 38)';
        } else if (hasPercentagePenalty) {
            previewTotal.textContent = `${latePercentage}% of dues`;
            previewTotal.style.color = 'rgb(217, 119, 6)';
        } else if (hasFixedPenalty) {
            previewTotal.textContent = formatCurrency(fixedPenalty, currencySymbol);
            previewTotal.style.color = 'rgb(217, 119, 6)';
        } else {
            previewTotal.textContent = 'No penalty set';
            previewTotal.style.color = 'var(--text-secondary)';
        }
    }
}

function initializeCurrencySync() {
    const currencyCodeSelect = document.getElementById('currency_code');
    const currencySymbolSelect = document.getElementById('currency_symbol');
    
    if (currencyCodeSelect && currencySymbolSelect) {
        currencyCodeSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const symbol = selectedOption.getAttribute('data-symbol');
            if (symbol) {
                currencySymbolSelect.value = symbol;
                if (document.getElementById('enable_tenant_invoicing')?.checked) {
                    updateTenantPreview();
                }
                updateLandlordPreview();
            }
        });
    }
}

function initializeCalculationFields() {
    const calculationMethod = document.getElementById('calculation_method');
    const perPropertyField = document.getElementById('per_property_amount_field');
    
    function toggleCalculationFields() {
        if (calculationMethod && perPropertyField) {
            perPropertyField.style.display = calculationMethod.value === 'per_property' ? 'block' : 'none';
        }
    }
    
    if (calculationMethod) {
        calculationMethod.addEventListener('change', function() {
            toggleCalculationFields();
            if (document.getElementById('enable_tenant_invoicing')?.checked) {
                updateTenantPreview();
            }
        });
        toggleCalculationFields();
    }
}

function initializeSlugGeneration() {
    const systemName = document.getElementById('system_name');
    const systemShortName = document.getElementById('system_short_name');
    
    if (systemName && systemShortName) {
        systemName.addEventListener('blur', function() {
            if (!systemShortName.value) generateSlug();
        });
    }
    updateSlugPreview();
}

function generateSlug() {
    const systemName = document.getElementById('system_name');
    const systemShortName = document.getElementById('system_short_name');
    
    if (systemName && systemShortName && systemName.value) {
        let slug = systemName.value.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        systemShortName.value = slug || 'property-system';
        updateSlugPreview();
    }
}

function updateSlugPreview() {
    const shortName = document.getElementById('system_short_name');
    const preview = document.getElementById('slug-preview');
    const previewText = document.getElementById('slug-preview-text');
    
    if (shortName && preview && previewText) {
        if (shortName.value) {
            previewText.textContent = shortName.value;
            preview.classList.remove('hidden');
        } else {
            preview.classList.add('hidden');
        }
    }
}

// ============================================================
// SMS / WHATSAPP DEPENDENT FIELDS  (FIXED)
// ------------------------------------------------------------
// KEY BEHAVIOUR:
//   • On page load, TRUST the server-rendered state. Never clear
//     a checkbox that Blade rendered as checked.
//   • Only enforce clearing when the user actively toggles the
//     global switch off (userInitiated === true).
//   • If the provider is not configured at all, the checkbox is
//     always disabled and cleared — the server would reject it
//     anyway via assertNotificationChannelsAreAllowed().
//
// NOTE: On the CREATE page the toggles start OFF, so this mostly
// matters after a validation failure re-renders old() input — the
// same "trust the server state" rule keeps those checks intact.
// ============================================================

function initializeSmsDependentFields() {
    const toggle = document.getElementById('sms_notifications_enabled');
    if (!toggle) return;

    const fields      = document.querySelectorAll('.sms-dependent-field');
    const smsChannels = document.querySelectorAll('.notification-channel[data-type="sms"]');

    function apply(userInitiated) {
        const enabled    = toggle.checked;
        const providerOk = window.SYSTEM_SETTINGS_FLAGS.smsProviderConfigured;

        // Show/hide the SMS-dependent config fields
        fields.forEach(f => {
            if (enabled) {
                f.classList.remove('hidden');
                f.style.display = '';
            } else {
                f.classList.add('hidden');
            }
        });

        smsChannels.forEach(ch => {
            if (!providerOk) {
                // No provider → never allow SMS channels
                ch.checked  = false;
                ch.disabled = true;
            } else if (!enabled) {
                // Global SMS toggle is off.
                // Only clear the check on explicit user action, so a
                // saved selection survives a page reload.
                if (userInitiated) ch.checked = false;
                ch.disabled = true;
            } else {
                ch.disabled = false;
            }
        });
    }

    // Initial render: trust server state (do NOT clear checks)
    apply(false);

    // Only enforce clearing when the user flips the switch
    toggle.addEventListener('change', () => apply(true));
}

function initializeWhatsAppDependentFields() {
    const toggle = document.getElementById('enable_whatsapp_notifications');
    if (!toggle) return;

    const fields           = document.querySelectorAll('.whatsapp-dependent-field');
    const whatsappChannels = document.querySelectorAll('.notification-channel[data-type="whatsapp"]');

    function apply(userInitiated) {
        const enabled    = toggle.checked;
        const providerOk = window.SYSTEM_SETTINGS_FLAGS.whatsappProviderConfigured;

        fields.forEach(f => {
            if (enabled) {
                f.classList.remove('hidden');
                f.style.display = '';
            } else {
                f.classList.add('hidden');
            }
        });

        whatsappChannels.forEach(ch => {
            if (!providerOk) {
                ch.checked  = false;
                ch.disabled = true;
            } else if (!enabled) {
                if (userInitiated) ch.checked = false;
                ch.disabled = true;
            } else {
                ch.disabled = false;
            }
        });
    }

    apply(false);
    toggle.addEventListener('change', () => apply(true));
}

// Single entry point — delegates to the two functions above.
// Do NOT call the two functions separately elsewhere on page load,
// or you will double-bind their change listeners.
function initializeNotificationChannels() {
    initializeSmsDependentFields();
    initializeWhatsAppDependentFields();
}

function initializePaymentGatewayValidation() {
    const gateways = document.querySelectorAll('.payment-gateway-checkbox');
    const errorDiv = document.getElementById('payment-gateway-error');
    const countSpan = document.getElementById('enabled-gateways-count');
    const submitBtn = document.getElementById('submitButton');
    
    function validate() {
        const enabled = Array.from(gateways).filter(g => g.checked).length;
        if (countSpan) countSpan.textContent = enabled;
        
        if (enabled === 0) {
            if (errorDiv) errorDiv.classList.remove('hidden');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
            return false;
        } else {
            if (errorDiv) errorDiv.classList.add('hidden');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
            return true;
        }
    }
    
    gateways.forEach(g => g.addEventListener('change', validate));
    validate();
}

function initializeLogoUpload() {
    const uploadArea = document.getElementById('logoUploadArea');
    const logoInput = document.getElementById('system_logo');
    
    if (uploadArea) {
        uploadArea.addEventListener('click', () => logoInput?.click());
        uploadArea.addEventListener('dragover', (e) => { 
            e.preventDefault(); 
            uploadArea.classList.add('border-primary'); 
        });
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('border-primary');
        });
        uploadArea.addEventListener('drop', (e) => { 
            e.preventDefault(); 
            uploadArea.classList.remove('border-primary'); 
            if (e.dataTransfer.files[0]) { 
                logoInput.files = e.dataTransfer.files; 
                previewLogo(logoInput); 
            } 
        });
    }
}

function previewLogo(input) {
    const preview = document.getElementById('logoPreview');
    const previewImage = document.getElementById('logoPreviewImage');
    const fileName = document.getElementById('logoFileName');
    const uploadArea = document.getElementById('logoUploadArea');
    const removeBtn = document.getElementById('removeLogoPreview');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (!file.type.startsWith('image/')) { 
            alert('Please select an image file'); 
            input.value = ''; 
            return; 
        }
        if (file.size > 2 * 1024 * 1024) { 
            alert('File size must be less than 2MB'); 
            input.value = ''; 
            return; 
        }
        
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImage.src = e.target.result;
            fileName.textContent = file.name;
            preview.classList.remove('hidden');
            if (uploadArea) uploadArea.classList.add('hidden');
        };
        reader.readAsDataURL(file);
        
        if (removeBtn) {
            removeBtn.onclick = () => {
                preview.classList.add('hidden');
                if (uploadArea) uploadArea.classList.remove('hidden');
                input.value = '';
            };
        }
    }
}

function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(inputId + '_icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function updateToggleSwitch(checkbox) {
    const slider = checkbox.nextElementSibling;
    if (slider) {
        slider.style.backgroundColor = checkbox.checked ? 'var(--success)' : '#d1d5db';
        slider.style.borderColor = checkbox.checked ? 'var(--success)' : '#d1d5db';
    }
}

function formatCurrency(amount, symbol) {
    if (isNaN(amount) || amount === null || amount === undefined) {
        amount = 0;
    }
    return `${symbol}${parseFloat(amount).toFixed(2)}`;
}

function handleFormSubmission(e) {
    const enabledGateways = document.querySelectorAll('.payment-gateway-checkbox:checked').length;
    if (enabledGateways === 0) {
        e.preventDefault();
        alert('Please enable at least one payment gateway.');
        const paymentTab = document.querySelector('.tab-btn[data-tab="payment"]');
        if (paymentTab) paymentTab.click();
        return false;
    }
    
    const shortName = document.getElementById('system_short_name')?.value;
    if (shortName && !/^[a-z0-9-]+$/.test(shortName)) {
        e.preventDefault();
        alert('System short name can only contain lowercase letters, numbers, and hyphens.');
        return false;
    }
    
    // SMS Sender ID — client-side guard mirroring the server rules
    const senderId = document.getElementById('sms_sender_id')?.value || '';
    if (senderId.length > 11) {
        e.preventDefault();
        alert('SMS Sender ID cannot exceed 11 characters.');
        document.querySelector('.tab-btn[data-tab="general"]')?.click();
        return false;
    }
    if (senderId && !/^[A-Za-z0-9 _\-]*$/.test(senderId)) {
        e.preventDefault();
        alert('SMS Sender ID may only contain letters, numbers, spaces, hyphens, and underscores.');
        document.querySelector('.tab-btn[data-tab="general"]')?.click();
        return false;
    }
    
    // ============================================================
    // Block enabling SMS/WhatsApp if provider isn't configured
    // ============================================================
    if (!window.SYSTEM_SETTINGS_FLAGS.smsProviderConfigured) {
        const smsChannelsChecked = document.querySelectorAll('input[name="invoice_notification_channels[]"][value="sms"]:checked, input[name="payment_reminder_channels[]"][value="sms"]:checked, input[name="overdue_notification_channels[]"][value="sms"]:checked, input[name="payment_confirmation_channels[]"][value="sms"]:checked');
        if (smsChannelsChecked.length > 0) {
            e.preventDefault();
            alert('SMS notifications cannot be enabled because no SMS provider is configured. Please configure an SMS provider first.');
            document.querySelector('.tab-btn[data-tab="notifications"]')?.click();
            return false;
        }
    }
    if (!window.SYSTEM_SETTINGS_FLAGS.whatsappProviderConfigured) {
        const waChannelsChecked = document.querySelectorAll('input[name="invoice_notification_channels[]"][value="whatsapp"]:checked, input[name="payment_reminder_channels[]"][value="whatsapp"]:checked, input[name="overdue_notification_channels[]"][value="whatsapp"]:checked, input[name="payment_confirmation_channels[]"][value="whatsapp"]:checked');
        if (waChannelsChecked.length > 0) {
            e.preventDefault();
            alert('WhatsApp notifications cannot be enabled because no WhatsApp provider is configured. Please configure a WhatsApp provider first.');
            document.querySelector('.tab-btn[data-tab="notifications"]')?.click();
            return false;
        }
    }
    
    const tenantToggle = document.getElementById('enable_tenant_invoicing');
    if (tenantToggle?.checked) {
        const tenantAmount = document.getElementById('tenant_monthly_dues_amount')?.value;
        if (!tenantAmount || parseFloat(tenantAmount) <= 0) {
            e.preventDefault();
            alert('Please enter a valid tenant monthly dues amount (must be greater than 0).');
            const tenantTab = document.querySelector('.tab-btn[data-tab="tenant"]');
            if (tenantTab) tenantTab.click();
            return false;
        }
    }
    
    const reminderToggle = document.getElementById('send_payment_reminders');
    if (reminderToggle?.checked) {
        const reminderDays = document.getElementById('reminder_days_before')?.value;
        if (!reminderDays || parseInt(reminderDays) < 1) {
            e.preventDefault();
            alert('Please enter valid reminder days before due date (minimum 1 day).');
            const generalTab = document.querySelector('.tab-btn[data-tab="general"]');
            if (generalTab) generalTab.click();
            return false;
        }
    }
    
    const gracePeriod = document.getElementById('grace_period_days')?.value;
    if (gracePeriod && parseInt(gracePeriod) < 0) {
        e.preventDefault();
        alert('Grace period cannot be negative.');
        const generalTab = document.querySelector('.tab-btn[data-tab="general"]');
        if (generalTab) generalTab.click();
        return false;
    }
    
    if (reminderToggle?.checked && reminderToggle.checked) {
        const reminderDays = parseInt(document.getElementById('reminder_days_before')?.value) || 0;
        const graceDays = parseInt(document.getElementById('grace_period_days')?.value) || 0;
        if (reminderDays > graceDays) {
            if (!confirm(`⚠️ Reminder days (${reminderDays}) is greater than grace period (${graceDays}). Reminders may be sent after invoices are overdue. Continue?`)) {
                e.preventDefault();
                return false;
            }
        }
    }
    
    return true;
}
</script>
@endsection