@extends('layouts.app')

@section('title', 'Generate QR Code - ' . $post->name)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="p-6">
            <div class="flex justify-between items-start">
                <div class="flex items-center">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mr-4"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                        <i class="fas fa-qrcode text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-qrcode mr-2" style="color: var(--primary);"></i>
                            Generate QR Code - {{ $post->name }}
                        </h2>
                        <div class="flex items-center mt-1 space-x-3 text-sm" style="color: var(--text-secondary);">
                            <span><i class="fas fa-map-marker-alt mr-1" style="color: var(--info);"></i> {{ $post->code }} | {{ $post->location }}</span>
                            <span>•</span>
                            <span><i class="fas fa-user-shield mr-1" style="color: var(--success);"></i> {{ auth()->user()->name }}</span>
                            @if($post->latitude && $post->longitude)
                                <span>•</span>
                                <span><i class="fas fa-satellite-dish mr-1" style="color: var(--warning);"></i> GPS Enabled</span>
                            @endif
                        </div>
                    </div>
                </div>
                <a href="{{ route('admin.security-posts.qr-codes.index', $post->id) }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center transition-all duration-200 hover:scale-105"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to QR Codes
                </a>
            </div>
        </div>
    </div>

    <!-- Breadcrumb Navigation -->
    <div class="card p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.security-posts.index') }}" 
                   class="inline-flex items-center text-sm font-medium hover:underline"
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Security Posts
                </a>
                <span style="color: var(--text-secondary);">/</span>
                <a href="{{ route('admin.security-posts.show', $post->id) }}" 
                   class="inline-flex items-center text-sm font-medium hover:underline"
                   style="color: var(--primary);">
                    {{ $post->name }}
                </a>
                <span style="color: var(--text-secondary);">/</span>
                <a href="{{ route('admin.security-posts.qr-codes.index', $post->id) }}" 
                   class="inline-flex items-center text-sm font-medium hover:underline"
                   style="color: var(--primary);">
                    QR Codes
                </a>
                <span style="color: var(--text-secondary);">/</span>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium"
                      style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-plus mr-1"></i> Generate New
                </span>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1" style="color: var(--info);"></i>
                {{ now()->format('l, F j, Y') }}
            </div>
        </div>
    </div>

    <!-- Information Alert -->
    <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-lg" style="color: var(--info);"></i>
            </div>
            <div class="ml-3 flex-1">
                <h4 class="text-sm font-medium" style="color: var(--text-primary);">QR Code Information</h4>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    QR codes are used by security personnel for location verification during check-in. 
                    <strong>Static codes</strong> are permanent, never expire, and ideal for fixed locations.
                    <strong>One-time codes</strong> expire after first use, and 
                    <strong>time-based codes</strong> expire on a specific date. The generated QR code will contain 
                    encrypted post and schedule information for secure verification.
                </p>
            </div>
        </div>
    </div>

    <!-- Generate QR Code Form -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-cog mr-2" style="color: var(--primary);"></i>
                QR Code Configuration
            </h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">Configure the settings for your new QR code</p>
        </div>

        <div class="p-6">
            <form method="POST" action="{{ route('admin.security-posts.qr-codes.store', $post->id) }}" class="space-y-8" id="qrCodeForm">
                @csrf

                <!-- ADD THIS HIDDEN FIELD - FIX FOR STATIC QR CODES -->
                <input type="hidden" name="static_expiry_override" id="static_expiry_override" value="0">

                <!-- Basic Information Section -->
                <div class="space-y-4">
                    <div class="flex items-center">
                        <div class="w-1 h-8 rounded-full mr-3" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);"></div>
                        <h4 class="text-lg font-medium" style="color: var(--text-primary);">Basic Information</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block mb-2 text-sm font-medium required-field" style="color: var(--text-primary);">
                                Name <span class="text-xs" style="color: var(--danger);">*</span>
                            </label>
                            <input type="text" 
                                   id="name"
                                   name="name" 
                                   value="{{ old('name') }}" 
                                   class="form-input w-full p-3 rounded-lg border transition-all duration-200 focus:ring-2 @error('name') error-field @enderror"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   placeholder="e.g., Main Entrance QR, Emergency Access, etc."
                                   maxlength="100"
                                   required>
                            <div class="flex items-center mt-1">
                                <i class="fas fa-info-circle text-xs mr-1" style="color: var(--info);"></i>
                                <span class="text-xs" style="color: var(--text-secondary);">A descriptive name for easy identification (max 100 characters)</span>
                            </div>
                            @error('name')
                                <div class="validation-message mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label for="description" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Description (Optional)</label>
                            <input type="text" 
                                   id="description"
                                   name="description" 
                                   value="{{ old('description') }}" 
                                   class="form-input w-full p-3 rounded-lg border transition-all duration-200 focus:ring-2 @error('description') error-field @enderror"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   placeholder="Brief description of this QR code's purpose"
                                   maxlength="255">
                            <div class="flex items-center mt-1">
                                <i class="fas fa-info-circle text-xs mr-1" style="color: var(--info);"></i>
                                <span class="text-xs" style="color: var(--text-secondary);">Optional description to provide context (max 255 characters)</span>
                            </div>
                            @error('description')
                                <div class="validation-message mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- QR Code Type Section -->
                <div class="space-y-4">
                    <div class="flex items-center">
                        <div class="w-1 h-8 rounded-full mr-3" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);"></div>
                        <h4 class="text-lg font-medium" style="color: var(--text-primary);">QR Code Type</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Static QR Code -->
                        <label class="relative cursor-pointer group qr-type-label">
                            <input type="radio" name="code_type" value="static" class="hidden qr-type-radio" {{ old('code_type', 'static') == 'static' ? 'checked' : '' }} required>
                            <div class="qr-type-card p-6 border-2 rounded-xl text-center transition-all duration-300 group-hover:scale-105"
                                 data-type="static"
                                 style="border-color: {{ old('code_type', 'static') == 'static' ? 'var(--primary)' : 'var(--border-color)' }}; 
                                        background-color: {{ old('code_type', 'static') == 'static' ? 'rgba(var(--primary-rgb), 0.05)' : 'var(--card-bg)' }};">
                                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                                    <i class="fas fa-infinity text-3xl" style="color: var(--primary);"></i>
                                </div>
                                <h5 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Static</h5>
                                <p class="text-sm mb-4" style="color: var(--text-secondary);">Permanent, never expires. Ideal for fixed locations.</p>
                                <div class="space-y-2">
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-medium"
                                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                        <i class="fas fa-infinity mr-1"></i> Unlimited Uses
                                    </span>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-check-circle" style="color: var(--success);"></i> No expiration
                                    </div>
                                </div>
                            </div>
                        </label>

                        <!-- One-Time QR Code -->
                        <label class="relative cursor-pointer group qr-type-label">
                            <input type="radio" name="code_type" value="one_time" class="hidden qr-type-radio" {{ old('code_type') == 'one_time' ? 'checked' : '' }}>
                            <div class="qr-type-card p-6 border-2 rounded-xl text-center transition-all duration-300 group-hover:scale-105"
                                 data-type="one_time"
                                 style="border-color: {{ old('code_type') == 'one_time' ? 'var(--warning)' : 'var(--border-color)' }}; 
                                        background-color: {{ old('code_type') == 'one_time' ? 'rgba(var(--warning-rgb), 0.05)' : 'var(--card-bg)' }};">
                                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                                     style="background-color: rgba(var(--warning-rgb), 0.1);">
                                    <i class="fas fa-clock text-3xl" style="color: var(--warning);"></i>
                                </div>
                                <h5 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">One-Time</h5>
                                <p class="text-sm mb-4" style="color: var(--text-secondary);">Single use only. Self-destructs after first scan.</p>
                                <div class="space-y-2">
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-medium"
                                          style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                        <i class="fas fa-clock mr-1"></i> Single Use
                                    </span>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i> Auto-expires after 1 use
                                    </div>
                                </div>
                            </div>
                        </label>

                        <!-- Time-Based QR Code -->
                        <label class="relative cursor-pointer group qr-type-label">
                            <input type="radio" name="code_type" value="time_based" class="hidden qr-type-radio" {{ old('code_type') == 'time_based' ? 'checked' : '' }}>
                            <div class="qr-type-card p-6 border-2 rounded-xl text-center transition-all duration-300 group-hover:scale-105"
                                 data-type="time_based"
                                 style="border-color: {{ old('code_type') == 'time_based' ? 'var(--danger)' : 'var(--border-color)' }}; 
                                        background-color: {{ old('code_type') == 'time_based' ? 'rgba(var(--danger-rgb), 0.05)' : 'var(--card-bg)' }};">
                                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                                    <i class="fas fa-hourglass-half text-3xl" style="color: var(--danger);"></i>
                                </div>
                                <h5 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Time-Based</h5>
                                <p class="text-sm mb-4" style="color: var(--text-secondary);">Expires at specified date. Perfect for temporary access.</p>
                                <div class="space-y-2">
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-medium"
                                          style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                        <i class="fas fa-calendar-times mr-1"></i> Expires on Date
                                    </span>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-hourglass-end" style="color: var(--danger);"></i> Set expiration below
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>
                    @error('code_type')
                        <div class="validation-message mt-2">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Additional Settings Section -->
                <div id="additionalSettings" class="space-y-4">
                    <div class="flex items-center">
                        <div class="w-1 h-8 rounded-full mr-3" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);"></div>
                        <h4 class="text-lg font-medium" style="color: var(--text-primary);">Additional Settings</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- FIXED: Expiry Date Field - Only shown for time_based, cleared for others -->
                        <div id="expiryField" class="space-y-2 {{ old('code_type') != 'time_based' ? 'hidden' : '' }}">
                            <label for="expires_at" class="block text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-calendar-alt mr-1" style="color: var(--danger);"></i>
                                Expiration Date & Time 
                                @if(old('code_type') == 'time_based')
                                    <span class="text-xs" style="color: var(--danger);">*</span>
                                @endif
                            </label>
                            <div class="relative">
                                <input type="datetime-local" 
                                       id="expires_at"
                                       name="expires_at" 
                                       value="{{ old('expires_at') }}" 
                                       class="form-input w-full p-3 rounded-lg border pl-10 transition-all duration-200 focus:ring-2 @error('expires_at') error-field @enderror"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       min="{{ now()->format('Y-m-d\TH:i') }}">
                                <i class="fas fa-clock absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--text-secondary);"></i>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-xs mr-1" style="color: var(--info);"></i>
                                <span class="text-xs" style="color: var(--text-secondary);">QR code will become invalid after this date/time</span>
                            </div>
                            @error('expires_at')
                                <div class="validation-message">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Max Uses Field -->
                        <div id="maxUsesField" class="space-y-2">
                            <label for="max_uses" class="block text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-hashtag mr-1" style="color: var(--info);"></i>
                                Maximum Uses
                            </label>
                            <div class="relative">
                                <input type="number" 
                                       id="max_uses"
                                       name="max_uses" 
                                       value="{{ old('max_uses') }}" 
                                       class="form-input w-full p-3 rounded-lg border pl-10 transition-all duration-200 focus:ring-2 @error('max_uses') error-field @enderror"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       placeholder="Leave empty for unlimited"
                                       min="1"
                                       max="1000"
                                       id="maxUsesInput">
                                <i class="fas fa-sort-numeric-up-alt absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--text-secondary);"></i>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-xs mr-1" style="color: var(--info);"></i>
                                <span class="text-xs" style="color: var(--text-secondary);">Maximum number of times this QR can be scanned (1-1000)</span>
                            </div>
                            @error('max_uses')
                                <div class="validation-message">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <!-- FIXED: Warning message for static codes if expiry is attempted -->
                    <div id="staticWarning" class="hidden p-3 rounded-lg text-sm" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Note:</strong> Static codes never expire. Any expiration date entered will be ignored.
                    </div>
                </div>

                <!-- Design Settings Section -->
                <div class="space-y-4">
                    <div class="flex items-center">
                        <div class="w-1 h-8 rounded-full mr-3" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);"></div>
                        <h4 class="text-lg font-medium" style="color: var(--text-primary);">Design Settings</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Size Selection -->
                        <div class="space-y-2">
                            <label for="size" class="block text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-arrows-alt mr-1" style="color: var(--info);"></i>
                                Size (pixels)
                            </label>
                            <select name="size" 
                                    id="size"
                                    class="form-input w-full p-3 rounded-lg border transition-all duration-200 focus:ring-2"
                                    style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                                <option value="200" {{ old('size') == '200' ? 'selected' : '' }}>200 x 200 (Small)</option>
                                <option value="300" {{ old('size') == '300' || !old('size') ? 'selected' : '' }}>300 x 300 (Medium - Recommended)</option>
                                <option value="400" {{ old('size') == '400' ? 'selected' : '' }}>400 x 400 (Large)</option>
                                <option value="500" {{ old('size') == '500' ? 'selected' : '' }}>500 x 500 (Extra Large)</option>
                                <option value="600" {{ old('size') == '600' ? 'selected' : '' }}>600 x 600 (2x)</option>
                                <option value="800" {{ old('size') == '800' ? 'selected' : '' }}>800 x 800 (3x)</option>
                                <option value="1000" {{ old('size') == '1000' ? 'selected' : '' }}>1000 x 1000 (4x)</option>
                            </select>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-ruler-combined mr-1"></i> Physical size depends on print resolution
                            </div>
                        </div>

                        <!-- Style Selection -->
                        <div class="space-y-2">
                            <label for="style" class="block text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-paint-brush mr-1" style="color: var(--info);"></i>
                                Module Style
                            </label>
                            <select name="style" 
                                    id="style"
                                    class="form-input w-full p-3 rounded-lg border transition-all duration-200 focus:ring-2"
                                    style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                                <option value="square" {{ old('style') == 'square' || !old('style') ? 'selected' : '' }}>Square (Classic)</option>
                                <option value="dot" {{ old('style') == 'dot' ? 'selected' : '' }}>Dot (Modern)</option>
                                <option value="round" {{ old('style') == 'round' ? 'selected' : '' }}>Round (Soft)</option>
                            </select>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-shapes mr-1"></i> Affects the shape of QR code modules
                            </div>
                        </div>

                        <!-- Format Selection -->
                        <div class="space-y-2">
                            <label for="format" class="block text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-file-image mr-1" style="color: var(--info);"></i>
                                Output Format
                            </label>
                            <select name="format" 
                                    id="format"
                                    class="form-input w-full p-3 rounded-lg border transition-all duration-200 focus:ring-2"
                                    style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                                <option value="png" {{ old('format') == 'png' || !old('format') ? 'selected' : '' }}>PNG (Recommended - Web/Print)</option>
                                <option value="svg" {{ old('format') == 'svg' ? 'selected' : '' }}>SVG (Vector - Scalable)</option>
                                <option value="eps" {{ old('format') == 'eps' ? 'selected' : '' }}>EPS (Print - Professional)</option>
                            </select>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-download mr-1"></i> PNG is most compatible
                            </div>
                        </div>
                    </div>

                    <!-- Color Settings -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                        <!-- Foreground Color -->
                        <div class="space-y-2">
                            <label for="foreground_color" class="block text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-palette mr-1" style="color: var(--primary);"></i>
                                Foreground Color
                            </label>
                            <div class="flex items-center space-x-3">
                                <div class="color-picker-wrapper relative">
                                    <input type="color" 
                                           id="foreground_color"
                                           name="foreground_color" 
                                           value="{{ old('foreground_color', '#000000') }}" 
                                           class="w-12 h-12 rounded-lg border-2 cursor-pointer"
                                           style="border-color: var(--border-color);"
                                           title="Click to select color">
                                    <div class="color-preview absolute -top-1 -right-1 w-4 h-4 rounded-full border-2 border-white shadow-lg"
                                         style="background-color: {{ old('foreground_color', '#000000') }};"></div>
                                </div>
                                <input type="text" 
                                       id="foreground_color_hex"
                                       value="{{ old('foreground_color', '#000000') }}" 
                                       class="form-input flex-1 p-3 rounded-lg border font-mono transition-all duration-200 focus:ring-2"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       placeholder="#000000"
                                       pattern="^#[a-fA-F0-9]{6}$"
                                       maxlength="7"
                                       readonly>
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-moon mr-1"></i> Color of QR code modules
                            </div>
                            @error('foreground_color')
                                <div class="validation-message">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Background Color -->
                        <div class="space-y-2">
                            <label for="background_color" class="block text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-fill-drip mr-1" style="color: var(--secondary);"></i>
                                Background Color
                            </label>
                            <div class="flex items-center space-x-3">
                                <div class="color-picker-wrapper relative">
                                    <input type="color" 
                                           id="background_color"
                                           name="background_color" 
                                           value="{{ old('background_color', '#ffffff') }}" 
                                           class="w-12 h-12 rounded-lg border-2 cursor-pointer"
                                           style="border-color: var(--border-color);"
                                           title="Click to select color">
                                    <div class="color-preview absolute -top-1 -right-1 w-4 h-4 rounded-full border-2 border-white shadow-lg"
                                         style="background-color: {{ old('background_color', '#ffffff') }};"></div>
                                </div>
                                <input type="text" 
                                       id="background_color_hex"
                                       value="{{ old('background_color', '#ffffff') }}" 
                                       class="form-input flex-1 p-3 rounded-lg border font-mono transition-all duration-200 focus:ring-2"
                                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                       placeholder="#ffffff"
                                       pattern="^#[a-fA-F0-9]{6}$"
                                       maxlength="7"
                                       readonly>
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-sun mr-1"></i> Background color (should contrast with foreground)
                            </div>
                            @error('background_color')
                                <div class="validation-message">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Live Preview Section -->
                <div class="space-y-4">
                    <div class="flex items-center">
                        <div class="w-1 h-8 rounded-full mr-3" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);"></div>
                        <h4 class="text-lg font-medium" style="color: var(--text-primary);">Live Preview</h4>
                    </div>

                    <div class="border-2 rounded-xl p-8 text-center" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <div id="qrPreview" class="inline-block">
                            <!-- QR Code Preview Container -->
                            <div class="relative w-64 h-64 mx-auto rounded-xl border-2 shadow-xl overflow-hidden transition-all duration-300 hover:shadow-2xl"
                                 id="previewContainer"
                                 style="border-color: var(--border-color); background-color: {{ old('background_color', '#ffffff') }};">
                                
                                <!-- QR Code Pattern Preview -->
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="grid grid-cols-5 gap-1 transform rotate-0 transition-all duration-500 hover:rotate-180">
                                        @php
                                            $foreground = old('foreground_color', '#000000');
                                        @endphp
                                        @for($i = 0; $i < 25; $i++)
                                            <div class="w-3 h-3 rounded-sm animate-pulse transition-all duration-300" 
                                                 id="preview-square-{{ $i }}"
                                                 style="background-color: {{ $i % 2 == 0 ? $foreground : 'transparent' }}; 
                                                        animation-delay: {{ $i * 0.1 }}s;"></div>
                                        @endfor
                                    </div>
                                </div>
                                
                                <!-- Center Logo Placeholder -->
                                <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-12 h-12 rounded-lg flex items-center justify-center transition-all duration-300 hover:scale-110"
                                     id="previewCenter"
                                     style="background-color: {{ old('background_color', '#ffffff') }}; border: 2px solid {{ $foreground }};">
                                    <i class="fas fa-qrcode text-2xl" id="previewIcon" style="color: {{ $foreground }};"></i>
                                </div>
                            </div>
                            
                            <!-- Preview Metadata -->
                            <div class="mt-6 space-y-3">
                                <div class="flex items-center justify-center space-x-4">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium"
                                          id="stylePreview"
                                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        <i class="fas fa-{{ old('style', 'square') }} mr-1"></i>
                                        {{ ucfirst(old('style', 'square')) }} Style
                                    </span>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium"
                                          id="formatPreview"
                                          style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                        <i class="fas fa-{{ old('format', 'png') == 'png' ? 'file-image' : (old('format') == 'svg' ? 'code' : 'file-alt') }} mr-1"></i>
                                        {{ strtoupper(old('format', 'png')) }} Format
                                    </span>
                                </div>
                                
                                <!-- Data Size Info (from controller optimization) -->
                                <div class="text-xs p-2 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                                    <div class="flex items-center justify-center space-x-4">
                                        <span><i class="fas fa-database mr-1" style="color: var(--info);"></i> <span id="dataSize">~200</span> bytes</span>
                                        <span><i class="fas fa-microchip mr-1" style="color: var(--warning);"></i> Version <span id="qrVersion">10</span></span>
                                    </div>
                                </div>
                                
                                <div class="text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Final QR code will be generated after creation
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Audit Trail Info (matches controller metadata) -->
                <div class="space-y-2 p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid rgba(var(--secondary-rgb), 0.1);">
                    <div class="flex items-center">
                        <i class="fas fa-history mr-2" style="color: var(--secondary);"></i>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">Audit Information</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs" style="color: var(--text-secondary);">
                        <div><i class="fas fa-user mr-1"></i> Generated by: {{ auth()->user()->name }}</div>
                        <div><i class="fas fa-clock mr-1"></i> Generated at: {{ now()->format('Y-m-d H:i:s') }}</div>
                        <div><i class="fas fa-globe mr-1"></i> IP: {{ request()->ip() }}</div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end space-x-3 pt-6 border-t" style="border-color: var(--border-color);">
                    <a href="{{ route('admin.security-posts.qr-codes.index', $post->id) }}" 
                       class="px-6 py-3 rounded-lg font-medium inline-flex items-center transition-all duration-200 hover:scale-105"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </a>
                    <button type="submit" 
                            id="submitBtn"
                            class="px-6 py-3 rounded-lg font-medium inline-flex items-center text-white btn-primary transition-all duration-200 hover:scale-105"
                            style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                        <i class="fas fa-qrcode mr-2"></i>
                        Generate QR Code
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* QR Type Card Animations */
.qr-type-card {
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
}

.qr-type-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(
        45deg,
        transparent 30%,
        rgba(var(--primary-rgb), 0.1) 50%,
        transparent 70%
    );
    transform: rotate(45deg);
    animation: shimmer 3s infinite;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.qr-type-card:hover::before {
    opacity: 1;
}

.qr-type-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
}

@keyframes shimmer {
    0% { transform: translateX(-100%) rotate(45deg); }
    100% { transform: translateX(100%) rotate(45deg); }
}

/* Color Picker Styles */
.color-picker-wrapper {
    position: relative;
    overflow: hidden;
    border-radius: 0.5rem;
    cursor: pointer;
}

.color-preview {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* Form Input Focus States */
.form-input:focus {
    outline: none;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.2);
}

.form-input.error-field {
    border-color: var(--danger) !important;
}

.form-input.error-field:focus {
    box-shadow: 0 0 0 3px rgba(var(--danger-rgb), 0.2);
}

/* Validation Message */
.validation-message {
    color: var(--danger);
    font-size: 0.75rem;
    margin-top: 0.25rem;
    display: flex;
    align-items: center;
}

.validation-message::before {
    content: '⚠️';
    margin-right: 0.25rem;
    font-size: 0.75rem;
}

/* Required Field Indicator */
.required-field::after {
    content: '*';
    color: var(--danger);
    margin-left: 0.25rem;
}

/* Hidden Class */
.hidden {
    display: none !important;
}

/* Loading State */
.btn-primary.loading {
    position: relative;
    color: transparent !important;
    pointer-events: none;
}

.btn-primary.loading::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin-left: -10px;
    margin-top: -10px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: button-spin 0.8s linear infinite;
}

@keyframes button-spin {
    to { transform: rotate(360deg); }
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .grid-cols-1.md\:grid-cols-3 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .grid-cols-1.md\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .flex.justify-end.space-x-3 {
        flex-direction: column;
        space-x: 0;
        space-y: 3;
    }
    
    .flex.justify-end.space-x-3 a,
    .flex.justify-end.space-x-3 button {
        width: 100%;
        justify-content: center;
    }
}
</style>
@endsection

@push('scripts')
<script>
(function() {
    'use strict';

    // DOM Elements
    const elements = {
        radioInputs: document.querySelectorAll('.qr-type-radio'),
        typeCards: document.querySelectorAll('.qr-type-card'),
        expiryField: document.getElementById('expiryField'),
        maxUsesField: document.getElementById('maxUsesField'),
        maxUsesInput: document.getElementById('maxUsesInput'),
        staticWarning: document.getElementById('staticWarning'),
        staticExpiryOverride: document.getElementById('static_expiry_override'),
        foregroundPicker: document.getElementById('foreground_color'),
        foregroundHex: document.getElementById('foreground_color_hex'),
        backgroundPicker: document.getElementById('background_color'),
        backgroundHex: document.getElementById('background_color_hex'),
        previewContainer: document.getElementById('previewContainer'),
        previewSquares: document.querySelectorAll('[id^="preview-square-"]'),
        previewCenter: document.getElementById('previewCenter'),
        previewIcon: document.getElementById('previewIcon'),
        styleSelect: document.getElementById('style'),
        formatSelect: document.getElementById('format'),
        stylePreview: document.getElementById('stylePreview'),
        formatPreview: document.getElementById('formatPreview'),
        form: document.getElementById('qrCodeForm'),
        submitBtn: document.getElementById('submitBtn'),
        nameInput: document.getElementById('name'),
        descriptionInput: document.getElementById('description'),
        expiresAtInput: document.getElementById('expires_at'),
        dataSizeSpan: document.getElementById('dataSize'),
        qrVersionSpan: document.getElementById('qrVersion')
    };

    // ==================== INITIALIZATION ====================
    function initialize() {
        const selectedRadio = document.querySelector('.qr-type-radio:checked');
        if (selectedRadio) {
            updateTypeStyles(selectedRadio.value);
            toggleAdditionalFields(selectedRadio.value);
            updateMaxUsesBasedOnType(selectedRadio.value);
            toggleStaticWarning(selectedRadio.value);
        }
        updatePreviewColors();
        attachEventListeners();
        calculateDataSize();
    }

    // ==================== TYPE CARD HANDLING ====================
    function updateTypeStyles(selectedValue) {
        elements.typeCards.forEach(card => {
            const cardType = card.getAttribute('data-type');
            
            // Reset all cards
            card.style.borderColor = 'var(--border-color)';
            card.style.backgroundColor = 'var(--card-bg)';
            card.style.transform = 'scale(1)';
            
            // Apply selected style
            if (cardType === selectedValue) {
                card.style.transform = 'scale(1.02)';
                card.style.boxShadow = '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1)';
                
                const colors = {
                    static: { border: 'var(--primary)', bg: 'rgba(var(--primary-rgb), 0.05)' },
                    one_time: { border: 'var(--warning)', bg: 'rgba(var(--warning-rgb), 0.05)' },
                    time_based: { border: 'var(--danger)', bg: 'rgba(var(--danger-rgb), 0.05)' }
                };
                
                if (colors[selectedValue]) {
                    card.style.borderColor = colors[selectedValue].border;
                    card.style.backgroundColor = colors[selectedValue].bg;
                }
            }
        });
    }

    // FIXED: Enhanced toggle function that clears expiry value for static and one-time codes
    // AND sets the static_expiry_override flag
    function toggleAdditionalFields(type) {
        if (!elements.expiryField || !elements.maxUsesField) return;

        if (type === 'time_based') {
            // Show expiry field for time-based codes
            elements.expiryField.classList.remove('hidden');
            elements.maxUsesField.classList.remove('hidden');
            if (elements.expiresAtInput) {
                elements.expiresAtInput.required = true;
            }
            // Set override to 0 for time-based codes
            if (elements.staticExpiryOverride) {
                elements.staticExpiryOverride.value = '0';
            }
        } else if (type === 'one_time') {
            // Hide expiry field for one-time codes and clear its value
            elements.expiryField.classList.add('hidden');
            elements.maxUsesField.classList.remove('hidden');
            if (elements.expiresAtInput) {
                elements.expiresAtInput.required = false;
                elements.expiresAtInput.value = ''; // FIXED: Clear expiry value
            }
            // Set override to 0 for one-time codes
            if (elements.staticExpiryOverride) {
                elements.staticExpiryOverride.value = '0';
            }
        } else { // static
            // Hide expiry field for static codes and clear its value
            elements.expiryField.classList.add('hidden');
            elements.maxUsesField.classList.remove('hidden');
            if (elements.expiresAtInput) {
                elements.expiresAtInput.required = false;
                elements.expiresAtInput.value = ''; // FIXED: Clear expiry value
            }
            // Set override to 1 for static codes to indicate to controller that this is definitely static
            if (elements.staticExpiryOverride) {
                elements.staticExpiryOverride.value = '1';
            }
        }
    }

    // FIXED: Show warning message when static is selected
    function toggleStaticWarning(type) {
        if (!elements.staticWarning) return;
        
        if (type === 'static') {
            elements.staticWarning.classList.remove('hidden');
        } else {
            elements.staticWarning.classList.add('hidden');
        }
    }

    function updateMaxUsesBasedOnType(type) {
        if (!elements.maxUsesInput) return;

        if (type === 'one_time') {
            elements.maxUsesInput.value = '1';
            elements.maxUsesInput.disabled = true;
            elements.maxUsesInput.readOnly = true;
            elements.maxUsesInput.placeholder = '1 (fixed for one-time)';
        } else {
            elements.maxUsesInput.disabled = false;
            elements.maxUsesInput.readOnly = false;
            elements.maxUsesInput.placeholder = 'Leave empty for unlimited';
            if (elements.maxUsesInput.value === '1' && type !== 'one_time') {
                elements.maxUsesInput.value = '';
            }
        }
    }

    // ==================== COLOR PREVIEW ====================
    function updateForegroundPreview(color) {
        if (!elements.previewSquares.length || !elements.previewIcon) return;
        
        elements.previewSquares.forEach((square, index) => {
            if (index % 2 === 0) {
                square.style.backgroundColor = color;
            }
        });
        
        if (elements.previewCenter) {
            elements.previewCenter.style.borderColor = color;
        }
        if (elements.previewIcon) {
            elements.previewIcon.style.color = color;
        }
    }

    function updateBackgroundPreview(color) {
        if (elements.previewContainer) {
            elements.previewContainer.style.backgroundColor = color;
        }
        if (elements.previewCenter) {
            elements.previewCenter.style.backgroundColor = color;
        }
    }

    function updatePreviewColors() {
        if (elements.foregroundPicker) {
            updateForegroundPreview(elements.foregroundPicker.value);
        }
        if (elements.backgroundPicker) {
            updateBackgroundPreview(elements.backgroundPicker.value);
        }
    }

    // ==================== STYLE/FORMAT PREVIEW ====================
    function updateStylePreview(style) {
        if (!elements.stylePreview) return;
        
        const icon = elements.stylePreview.querySelector('i');
        if (icon) {
            icon.className = `fas fa-${style} mr-1`;
        }
        const text = elements.stylePreview.childNodes[1];
        if (text) {
            text.nodeValue = ` ${style.charAt(0).toUpperCase() + style.slice(1)} Style`;
        }
    }

    function updateFormatPreview(format) {
        if (!elements.formatPreview) return;
        
        const icon = elements.formatPreview.querySelector('i');
        if (icon) {
            let iconName = 'file-image';
            if (format === 'svg') iconName = 'code';
            if (format === 'eps') iconName = 'file-alt';
            icon.className = `fas fa-${iconName} mr-1`;
        }
        const text = elements.formatPreview.childNodes[1];
        if (text) {
            text.nodeValue = ` ${format.toUpperCase()} Format`;
        }
    }

    // ==================== DATA SIZE CALCULATION ====================
    function calculateDataSize() {
        if (!elements.dataSizeSpan || !elements.qrVersionSpan) return;
        
        // Simulate data size based on form inputs (matches controller's optimized JSON)
        const selectedType = document.querySelector('.qr-type-radio:checked')?.value || 'static';
        const nameLength = (elements.nameInput?.value || '').length;
        const hasExpiry = selectedType === 'time_based' && elements.expiresAtInput?.value;
        const hasMaxUses = elements.maxUsesInput?.value && elements.maxUsesInput.value !== '';
        
        // Base data: {t:"sc",p:123,c:"QR-...",n:"...",vf:1234567890,v:"1"}
        let dataSize = 60; // Base size
        
        // Add name length (truncated to 20 chars in controller)
        dataSize += Math.min(nameLength, 20);
        
        // Add type-specific fields
        if (selectedType === 'one_time') {
            dataSize += 10; // "ot":1
        }
        if (hasExpiry) {
            dataSize += 25; // "ve":1234567890
        }
        if (selectedType === 'static') {
            dataSize += 8; // "perm":1 (optional)
        }
        
        // Add post_id digits
        dataSize += 5; // Average
        
        // Calculate QR version based on data size (bits)
        const dataBits = dataSize * 8;
        let version = 1;
        const capacities = [80, 128, 208, 288, 368, 480, 592, 688, 800, 912, 1024, 1152, 1280, 1408, 1536, 1664, 1792, 1920, 2048, 2176, 2304, 2432, 2560, 2688, 2816, 2944, 3072, 3200, 3328, 3456, 3584, 3712, 3840, 3968, 4096, 4224, 4352, 4480, 4608, 4736];
        
        for (let i = 0; i < capacities.length; i++) {
            if (capacities[i] >= dataBits) {
                version = i + 1;
                break;
            }
        }
        
        elements.dataSizeSpan.textContent = dataSize;
        elements.qrVersionSpan.textContent = version;
    }

    // ==================== FORM VALIDATION ====================
    function validateForm() {
        const selectedType = document.querySelector('.qr-type-radio:checked')?.value;
        
        if (!selectedType) {
            alert('Please select a QR code type');
            return false;
        }
        
        if (selectedType === 'time_based' && elements.expiresAtInput) {
            const expiryDate = new Date(elements.expiresAtInput.value);
            const now = new Date();
            
            if (!elements.expiresAtInput.value) {
                alert('Please set an expiration date for time-based QR code');
                return false;
            }
            
            if (expiryDate <= now) {
                alert('Expiration date must be in the future');
                return false;
            }
        }
        
        // FIXED: For static codes, ignore any expiry validation since field is hidden/cleared
        if (selectedType !== 'time_based' && elements.expiresAtInput && elements.expiresAtInput.value) {
            // This shouldn't happen due to our toggle function, but just in case
            elements.expiresAtInput.value = '';
        }
        
        if (elements.maxUsesInput && !elements.maxUsesInput.disabled && elements.maxUsesInput.value) {
            const maxUses = parseInt(elements.maxUsesInput.value);
            if (isNaN(maxUses) || maxUses < 1 || maxUses > 1000) {
                alert('Maximum uses must be between 1 and 1000');
                return false;
            }
        }
        
        // Validate hex colors
        const hexColorRegex = /^#[a-fA-F0-9]{6}$/;
        if (!hexColorRegex.test(elements.foregroundPicker?.value || '#000000')) {
            alert('Foreground color must be a valid hex color (e.g., #000000)');
            return false;
        }
        
        if (!hexColorRegex.test(elements.backgroundPicker?.value || '#ffffff')) {
            alert('Background color must be a valid hex color (e.g., #ffffff)');
            return false;
        }
        
        return true;
    }

    // ==================== EVENT LISTENERS ====================
    function attachEventListeners() {
        // Radio change events
        elements.radioInputs.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    updateTypeStyles(this.value);
                    toggleAdditionalFields(this.value);
                    updateMaxUsesBasedOnType(this.value);
                    toggleStaticWarning(this.value);
                    calculateDataSize();
                }
            });
        });

        // Label click events for better UX
        document.querySelectorAll('.qr-type-label').forEach(label => {
            label.addEventListener('click', function(e) {
                const radio = this.querySelector('.qr-type-radio');
                if (radio && !radio.checked) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                }
            });
        });

        // Color picker events
        if (elements.foregroundPicker && elements.foregroundHex) {
            elements.foregroundPicker.addEventListener('input', function() {
                elements.foregroundHex.value = this.value;
                updateForegroundPreview(this.value);
                calculateDataSize();
            });
        }

        if (elements.backgroundPicker && elements.backgroundHex) {
            elements.backgroundPicker.addEventListener('input', function() {
                elements.backgroundHex.value = this.value;
                updateBackgroundPreview(this.value);
                calculateDataSize();
            });
        }

        // Style select
        if (elements.styleSelect) {
            elements.styleSelect.addEventListener('change', function() {
                updateStylePreview(this.value);
                calculateDataSize();
            });
        }

        // Format select
        if (elements.formatSelect) {
            elements.formatSelect.addEventListener('change', function() {
                updateFormatPreview(this.value);
            });
        }

        // Input events for data size calculation
        if (elements.nameInput) {
            elements.nameInput.addEventListener('input', calculateDataSize);
        }
        
        if (elements.descriptionInput) {
            elements.descriptionInput.addEventListener('input', calculateDataSize);
        }
        
        if (elements.expiresAtInput) {
            elements.expiresAtInput.addEventListener('change', calculateDataSize);
        }
        
        if (elements.maxUsesInput) {
            elements.maxUsesInput.addEventListener('input', calculateDataSize);
        }

        // Form submission
        if (elements.form && elements.submitBtn) {
            elements.form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    return;
                }
                
                elements.submitBtn.disabled = true;
                elements.submitBtn.classList.add('loading');
                elements.submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Generating...';
            });
        }
    }

    // ==================== DEBOUNCE UTILITY ====================
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // ==================== RESPONSIVE HANDLING ====================
    const handleResize = debounce(function() {
        if (window.innerWidth <= 640) {
            document.querySelectorAll('.grid-cols-1.md\\:grid-cols-3').forEach(grid => {
                grid.classList.add('space-y-4');
            });
        } else {
            document.querySelectorAll('.grid-cols-1.md\\:grid-cols-3').forEach(grid => {
                grid.classList.remove('space-y-4');
            });
        }
    }, 150);

    window.addEventListener('resize', handleResize);

    // Initialize everything when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
</script>
@endpush