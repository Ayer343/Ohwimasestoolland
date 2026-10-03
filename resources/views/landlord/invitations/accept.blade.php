<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept Invitation - {{ $systemSettings->system_name ?? 'Property Management System' }}</title>
    
    <!-- ============ FAVICON ============ -->
    @php
        $settings = \App\Models\SystemSetting::getSettings();
    @endphp
    @if($settings->hasFavicon())
        <link rel="icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="apple-touch-icon" href="{{ $settings->getFaviconUrl() }}">
        <!-- Additional favicon sizes for better browser support -->
        <link rel="icon" type="image/png" sizes="16x16" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="64x64" href="{{ $settings->getFaviconUrl() }}">
        <!-- Microsoft Edge Tile -->
        <meta name="msapplication-TileImage" content="{{ $settings->getFaviconUrl() }}">
        <meta name="msapplication-TileColor" content="#0f172a">
    @else
        <!-- Default favicon fallback -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <meta name="msapplication-TileColor" content="#0f172a">
    @endif
    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0f172a;
            --secondary: #1e3a8a;
            --accent: #8b5cf6;
            --light: #f8fafc;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            background-image: url('https://images.unsplash.com/photo-1600585154340-043788447eb3?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            padding: 0.5rem;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.85) 0%, rgba(30, 58, 138, 0.8) 100%);
            z-index: 0;
        }
        
        .bg-pattern {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.3;
            z-index: 0;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.25);
            z-index: 2;
            position: relative;
            border-radius: 16px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
            transition: all 0.3s ease;
            font-size: 0.8rem;
            padding: 0.4rem 1rem;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px -5px rgba(30, 58, 138, 0.4);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .floating-input {
            border: 0;
            border-bottom: 2px solid #cbd5e1;
            outline: none;
            transition: all 0.3s ease;
            background: transparent;
            padding-left: 8px;
            padding-right: 36px;
            font-size: 0.85rem;
            padding-top: 0.3rem;
            padding-bottom: 0.3rem;
        }
        
        .floating-input:focus {
            border-color: var(--secondary);
        }
        
        .floating-label {
            position: absolute;
            top: 8px;
            left: 8px;
            color: #64748b;
            transition: all 0.3s ease;
            pointer-events: none;
            font-size: 0.8rem;
        }
        
        .floating-input:focus ~ label,
        .floating-input:not(:placeholder-shown) ~ label {
            top: -10px;
            left: 0;
            font-size: 0.6rem;
            color: var(--secondary);
        }
        
        .password-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .password-toggle:hover {
            color: var(--secondary);
            background: rgba(0, 0, 0, 0.05);
        }
        
        .progress-bar {
            height: 4px;
            background-color: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .progress-bar-fill {
            height: 100%;
            transition: width 0.3s ease;
            border-radius: 2px;
        }
        
        .loading-dots {
            display: inline-flex;
            align-items: center;
        }
        
        .loading-dots span {
            width: 5px;
            height: 5px;
            margin: 0 2px;
            background-color: currentColor;
            border-radius: 50%;
            animation: loading-bounce 1.4s ease-in-out infinite both;
        }
        
        .loading-dots span:nth-child(1) { animation-delay: -0.32s; }
        .loading-dots span:nth-child(2) { animation-delay: -0.16s; }
        
        @keyframes loading-bounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }
        
        .section-card {
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            margin-bottom: 0.5rem;
        }
        
        .section-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.05);
        }
        
        .step-indicator {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.7rem;
        }
        
        .step-active {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
        }
        
        .step-completed {
            background: #10b981;
            color: white;
        }
        
        .step-pending {
            background: #e2e8f0;
            color: #64748b;
        }
        
        .d-none {
            display: none !important;
        }
        
        /* Welcome text compact */
        .welcome-text h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .welcome-text p {
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }
        .feature-list .feature-item {
            font-size: 0.85rem;
            margin-bottom: 0.35rem;
        }
        .feature-icon {
            width: 28px;
            height: 28px;
        }
        
        /* Responsive Styles */
        @media (max-width: 968px) {
            .content-container {
                flex-direction: column;
                padding: 0.25rem;
            }
            
            .welcome-text {
                padding-right: 0;
                padding-bottom: 0.75rem;
                text-align: center;
                width: 100%;
            }
            
            .form-container {
                width: 100%;
                max-width: 550px;
            }
        }
        
        @media (max-width: 640px) {
            body {
                padding: 0.25rem;
                align-items: flex-start;
                padding-top: 0.5rem;
            }
            
            .glass-card {
                padding: 0.75rem;
                border-radius: 12px;
            }
            
            .grid-cols-2 {
                grid-template-columns: 1fr;
            }
            
            .welcome-text h1 {
                font-size: 1.5rem;
            }
            .welcome-text p {
                font-size: 0.8rem;
            }
            .feature-list .feature-item {
                font-size: 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .glass-card {
                padding: 0.5rem;
            }
            
            .section-card {
                padding: 0.35rem 0.5rem;
                margin-bottom: 0.35rem;
            }
            
            .step-indicator {
                width: 24px;
                height: 24px;
                font-size: 0.6rem;
            }
            
            .floating-input {
                font-size: 0.75rem;
                padding-top: 0.2rem;
                padding-bottom: 0.2rem;
                padding-left: 6px;
                padding-right: 28px;
            }
            
            .floating-label {
                font-size: 0.7rem;
                top: 6px;
                left: 6px;
            }
            
            .floating-input:focus ~ label,
            .floating-input:not(:placeholder-shown) ~ label {
                top: -8px;
                font-size: 0.55rem;
            }
        }
        
        /* Touch target improvements */
        @media (hover: none) and (pointer: coarse) {
            .btn-primary,
            .password-toggle {
                min-height: 36px;
            }
            
            input[type="checkbox"] {
                transform: scale(1.1);
                margin-right: 0.4rem;
            }
        }
    </style>
</head>
<body>
    <div class="bg-pattern"></div>
    
    <div class="content-container" style="z-index: 10; position: relative; width: 100%; max-width: 1100px; margin: 0 auto; display: flex; align-items: center; justify-content: center; padding: 0.5rem;">
        <!-- Welcome Section - Compact -->
        <div class="welcome-text" style="flex: 1; color: white; padding-right: 1.5rem;">
            <h1 class="font-bold">Welcome to Your Property Portal</h1>
            <p class="opacity-90">{{ $systemSettings->system_name ?? 'Property Management System' }} helps you manage your properties efficiently.</p>
            
            <div class="feature-list space-y-1">
                <div class="feature-item flex items-center">
                    <div class="feature-icon bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-2">
                        <i class="fas fa-home text-white text-xs"></i>
                    </div>
                    <span>Complete Property Management</span>
                </div>
                <div class="feature-item flex items-center">
                    <div class="feature-icon bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-2">
                        <i class="fas fa-chart-line text-white text-xs"></i>
                    </div>
                    <span>Real-time Rental Tracking</span>
                </div>
                <div class="feature-item flex items-center">
                    <div class="feature-icon bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-2">
                        <i class="fas fa-file-invoice-dollar text-white text-xs"></i>
                    </div>
                    <span>Digital Rent Collection</span>
                </div>
            </div>
        </div>

        <!-- Form Container - Compact -->
        <div class="form-container" style="flex: 0 0 520px; width: 100%;">
            <div class="glass-card shadow-2xl p-4 md:p-5">
                <!-- Header - Compact -->
                <div class="text-center mb-3">
                    @if(isset($systemSettings) && $systemSettings->system_logo)
                        <img src="{{ asset('storage/' . $systemSettings->system_logo) }}" 
                             alt="{{ $systemSettings->system_name ?? 'System Logo' }}" 
                             class="mx-auto mb-2" style="max-height: 35px;">
                    @else
                        <div class="mb-2">
                            <i class="fas fa-building fa-2x" style="color: var(--secondary);"></i>
                        </div>
                    @endif
                    
                    <h2 class="text-lg font-semibold text-slate-800">Complete Registration</h2>
                    <p class="text-slate-500 text-xs">Set up your account to access your property portal</p>
                </div>

                <!-- Invitation Status - Compact -->
                @if(!$invitation->isActive())
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-2 rounded mb-3 text-sm">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-2 mt-0.5"></i>
                        <div>
                            <h4 class="font-bold text-xs mb-1">Invitation Not Available</h4>
                            <p class="text-xs mb-2">
                                @if($invitation->isExpired())
                                    This invitation has expired.
                                @elseif($invitation->isAccepted())
                                    This invitation has already been accepted.
                                @else
                                    This invitation is no longer valid.
                                @endif
                            </p>
                            <div class="p-2 bg-red-100 rounded text-xs">
                                <p class="font-medium mb-1">Contact Support:</p>
                                <div class="flex flex-wrap gap-2">
                                    <a href="mailto:{{ $systemSettings->system_email ?? 'support@example.com' }}" class="text-blue-600">
                                        <i class="fas fa-envelope mr-1"></i>{{ $systemSettings->system_email ?? 'support@example.com' }}
                                    </a>
                                    @if($systemSettings && $systemSettings->system_phone)
                                    <a href="tel:{{ $systemSettings->system_phone }}" class="text-green-600">
                                        <i class="fas fa-phone mr-1"></i>{{ $systemSettings->system_phone }}
                                    </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @else

                <!-- Step Indicators - Compact -->
                <div class="flex justify-between mb-3">
                    <div class="text-center flex-1">
                        <div class="step-indicator mx-auto step-active">1</div>
                        <p class="text-xs mt-0.5 text-slate-500">Property</p>
                    </div>
                    <div class="text-center flex-1">
                        <div class="step-indicator mx-auto step-pending">2</div>
                        <p class="text-xs mt-0.5 text-slate-500">Your Info</p>
                    </div>
                    <div class="text-center flex-1">
                        <div class="step-indicator mx-auto step-pending">3</div>
                        <p class="text-xs mt-0.5 text-slate-500">Password</p>
                    </div>
                    <div class="text-center flex-1">
                        <div class="step-indicator mx-auto step-pending">4</div>
                        <p class="text-xs mt-0.5 text-slate-500">Confirm</p>
                    </div>
                </div>

                <form action="{{ route('landlord.invitations.process-acceptance', $token) }}" method="POST" id="accept-invitation-form">
                    @csrf

                    <!-- Section 1: Property Details - Compact -->
                    <div class="section-card bg-blue-50">
                        <div class="flex items-center mb-1.5">
                            <div class="bg-blue-100 p-1.5 rounded mr-2">
                                <i class="fas fa-home text-blue-600 text-sm"></i>
                            </div>
                            <h3 class="font-semibold text-slate-800 text-sm">Property Details</h3>
                        </div>
                        <div class="grid grid-cols-2 gap-1 text-xs">
                            <div>
                                <span class="text-slate-500">Property:</span>
                                <p class="font-medium text-slate-800">{{ $invitation->property->property_name }}</p>
                            </div>
                            <div>
                                <span class="text-slate-500">Pattern:</span>
                                <p class="font-medium font-mono text-slate-800">{{ $invitation->property->registration_pattern }}</p>
                            </div>
                            <div>
                                <span class="text-slate-500">Address:</span>
                                <p class="font-medium text-slate-800">{{ $invitation->property->street_name }}</p>
                            </div>
                            <div>
                                <span class="text-slate-500">Zone:</span>
                                <p class="font-medium text-slate-800">{{ $invitation->property->zone }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Your Information - Compact -->
                    <div class="section-card">
                        <div class="flex items-center mb-1.5">
                            <div class="bg-gray-100 p-1.5 rounded mr-2">
                                <i class="fas fa-user text-gray-600 text-sm"></i>
                            </div>
                            <h3 class="font-semibold text-slate-800 text-sm">Your Information</h3>
                        </div>
                        <div class="space-y-2">
                            <div class="floating-label-group relative">
                                <input type="text" id="name" name="name" value="{{ old('name', $invitation->landlord->name) }}" required
                                       class="floating-input w-full border-0 border-b-2 focus:ring-0" placeholder=" ">
                                <label for="name" class="floating-label">Full Name *</label>
                                @error('name')<p class="text-red-500 text-xs mt-0.5">{{ $message }}</p>@enderror
                            </div>
                            
                            <div class="floating-label-group relative">
                                <input type="email" id="email" name="email" value="{{ old('email', $invitation->landlord->email) }}" required
                                       class="floating-input w-full border-0 border-b-2 focus:ring-0" placeholder=" ">
                                <label for="email" class="floating-label">Email Address *</label>
                                @error('email')<p class="text-red-500 text-xs mt-0.5">{{ $message }}</p>@enderror
                            </div>
                            
                            <div class="floating-label-group relative">
                                <input type="tel" id="phone" name="phone" value="{{ old('phone', $invitation->landlord->phone) }}" required
                                       class="floating-input w-full border-0 border-b-2 focus:ring-0" placeholder=" ">
                                <label for="phone" class="floating-label">Phone Number *</label>
                                @error('phone')<p class="text-red-500 text-xs mt-0.5">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Password Setup - Compact -->
                    <div class="section-card">
                        <div class="flex items-center mb-1.5">
                            <div class="bg-gray-100 p-1.5 rounded mr-2">
                                <i class="fas fa-lock text-gray-600 text-sm"></i>
                            </div>
                            <h3 class="font-semibold text-slate-800 text-sm">Password Setup</h3>
                        </div>
                        <div class="space-y-2">
                            <div class="floating-label-group relative">
                                <input type="password" id="password" name="password" required minlength="8"
                                       class="floating-input w-full border-0 border-b-2 focus:ring-0" placeholder=" ">
                                <label for="password" class="floating-label">Create Password *</label>
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password')">
                                    <i class="fas fa-eye text-slate-400"></i>
                                </button>
                            </div>
                            
                            <!-- Password Strength - Compact -->
                            <div>
                                <div class="progress-bar">
                                    <div id="password-strength-fill" class="progress-bar-fill w-0"></div>
                                </div>
                                <div id="password-strength-text" class="text-xs mt-0.5"></div>
                            </div>
                            
                            <div class="floating-label-group relative">
                                <input type="password" id="password_confirmation" name="password_confirmation" required
                                       class="floating-input w-full border-0 border-b-2 focus:ring-0" placeholder=" ">
                                <label for="password_confirmation" class="floating-label">Confirm Password *</label>
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password_confirmation')">
                                    <i class="fas fa-eye text-slate-400"></i>
                                </button>
                            </div>
                            <div id="password-match" class="text-green-500 text-xs d-none"></div>
                            
                            <!-- Requirements (collapsible) - Compact -->
                            <details class="text-xs">
                                <summary class="cursor-pointer text-slate-400 text-xs">Password requirements</summary>
                                <div class="mt-1 space-y-0.5 pl-2">
                                    <div id="req-length" class="text-slate-400">○ At least 8 characters</div>
                                    <div id="req-uppercase" class="text-slate-400">○ One uppercase letter</div>
                                    <div id="req-lowercase" class="text-slate-400">○ One lowercase letter</div>
                                    <div id="req-number" class="text-slate-400">○ One number</div>
                                    <div id="req-special" class="text-slate-400">○ One special character</div>
                                </div>
                            </details>
                        </div>
                    </div>

                    <!-- Section 4: Terms & Submit - Compact -->
                    <div class="section-card">
                        <div class="flex items-start mb-1.5">
                            <input type="checkbox" id="agree_terms" name="agree_terms" required class="mt-0.5 mr-2">
                            <label for="agree_terms" class="text-xs text-slate-700">
                                I agree to the <a href="{{ route('terms') }}" target="_blank" class="text-blue-600">Terms</a> &amp; <a href="{{ route('privacy') }}" target="_blank" class="text-blue-600">Privacy</a>
                            </label>
                        </div>
                        
                        <button type="submit" id="submit-button" class="w-full btn-primary text-white rounded-lg font-semibold transition-all flex items-center justify-center" disabled>
                            <i class="fas fa-user-check mr-1.5"></i>
                            <span id="submit-text">Complete Registration</span>
                            <span id="submit-loading" class="d-none ml-2">
                                <span class="loading-dots"><span></span><span></span><span></span></span>
                            </span>
                        </button>
                        
                        <!-- Security Note - Compact -->
                        <div class="mt-2 p-2 bg-green-50 rounded-lg text-center">
                            <div class="flex items-center justify-center text-green-700 text-xs">
                                <i class="fas fa-shield-alt mr-1.5"></i>
                                <span>Your information is encrypted and secure</span>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Support Footer - Compact -->
                <div class="text-center pt-2 border-t border-slate-200">
                    <p class="text-xs text-slate-500">
                        Need help? 
                        <a href="mailto:{{ $systemSettings->system_email ?? 'support@example.com' }}" class="text-blue-600">
                            {{ $systemSettings->system_email ?? 'support@example.com' }}
                        </a>
                        @if($systemSettings && $systemSettings->system_phone)
                        or <a href="tel:{{ $systemSettings->system_phone }}" class="text-green-600">{{ $systemSettings->system_phone }}</a>
                        @endif
                    </p>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Invitation expires in {{ $invitation->getDaysUntilExpiry() ?? 7 }} days
                    </p>
                </div>

                @endif
            </div>
        </div>
    </div>

    <script>
        // Password requirements tracking
        let requirements = {
            length: false,
            uppercase: false,
            lowercase: false,
            number: false,
            special: false
        };
        
        function checkPasswordStrength(password) {
            requirements.length = password.length >= 8;
            requirements.uppercase = /[A-Z]/.test(password);
            requirements.lowercase = /[a-z]/.test(password);
            requirements.number = /[0-9]/.test(password);
            requirements.special = /[^A-Za-z0-9]/.test(password);
            
            let strength = Object.values(requirements).filter(Boolean).length;
            
            updateStrengthUI(strength);
            updateRequirementsUI();
            validateForm();
        }
        
        function updateStrengthUI(strength) {
            const fill = document.getElementById('password-strength-fill');
            const text = document.getElementById('password-strength-text');
            
            if (!fill) return;
            
            const percentage = (strength / 5) * 100;
            fill.style.width = `${percentage}%`;
            
            const colors = ['#ef4444', '#ef4444', '#f59e0b', '#10b981', '#10b981', '#3b82f6'];
            const labels = ['', 'Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
            fill.style.backgroundColor = colors[strength] || '#3b82f6';
            
            if (text) {
                text.textContent = labels[strength] || '';
                text.className = `text-xs mt-0.5 ${strength >= 3 ? 'text-green-600' : strength >= 2 ? 'text-orange-500' : 'text-red-500'}`;
            }
        }
        
        function updateRequirementsUI() {
            const reqMap = {
                length: 'At least 8 characters',
                uppercase: 'One uppercase letter',
                lowercase: 'One lowercase letter', 
                number: 'One number',
                special: 'One special character'
            };
            
            for (const [key, met] of Object.entries(requirements)) {
                const element = document.getElementById(`req-${key}`);
                if (element) {
                    if (met) {
                        element.innerHTML = '✓ ' + reqMap[key];
                        element.className = 'text-green-600';
                    } else {
                        element.innerHTML = '○ ' + reqMap[key];
                        element.className = 'text-slate-400';
                    }
                }
            }
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password')?.value || '';
            const confirm = document.getElementById('password_confirmation')?.value || '';
            const matchEl = document.getElementById('password-match');
            
            if (!matchEl) return;
            
            if (confirm && password === confirm) {
                matchEl.innerHTML = '✓ Passwords match';
                matchEl.classList.remove('d-none');
            } else if (confirm) {
                matchEl.innerHTML = '✗ Passwords do not match';
                matchEl.classList.remove('d-none');
            } else {
                matchEl.classList.add('d-none');
            }
            validateForm();
        }
        
        function validateForm() {
            const name = document.getElementById('name')?.value.trim() || '';
            const email = document.getElementById('email')?.value || '';
            const phone = document.getElementById('phone')?.value.trim() || '';
            const password = document.getElementById('password')?.value || '';
            const confirm = document.getElementById('password_confirmation')?.value || '';
            const termsAccepted = document.getElementById('agree_terms')?.checked || false;
            
            const isValid = name.length >= 2 && 
                           /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) &&
                           phone.replace(/[^0-9]/g, '').length >= 9 &&
                           Object.values(requirements).every(Boolean) &&
                           password === confirm && password.length > 0 &&
                           termsAccepted;
            
            const submitBtn = document.getElementById('submit-button');
            if (submitBtn) {
                submitBtn.disabled = !isValid;
            }
            
            updateStepIndicators();
        }
        
        function togglePasswordVisibility(fieldId) {
            const field = document.getElementById(fieldId);
            if (!field) return;
            
            const icon = field.parentElement?.querySelector('.password-toggle i');
            if (field.type === 'password') {
                field.type = 'text';
                if (icon) {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            } else {
                field.type = 'password';
                if (icon) {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        }
        
        function updateStepIndicators() {
            const name = document.getElementById('name')?.value.trim() || '';
            const email = document.getElementById('email')?.value || '';
            const phone = document.getElementById('phone')?.value.trim() || '';
            const hasPassword = Object.values(requirements).every(Boolean);
            
            const steps = document.querySelectorAll('.step-indicator');
            if (steps.length >= 3) {
                const hasInfo = name.length >= 2 && 
                               /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) &&
                               phone.replace(/[^0-9]/g, '').length >= 9;
                
                if (hasInfo) {
                    steps[1].className = 'step-indicator mx-auto step-completed';
                    steps[1].innerHTML = '<i class="fas fa-check text-xs"></i>';
                }
                if (hasPassword && hasInfo) {
                    steps[2].className = 'step-indicator mx-auto step-completed';
                    steps[2].innerHTML = '<i class="fas fa-check text-xs"></i>';
                }
            }
        }
        
        // Event Listeners
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('password_confirmation');
        const nameInput = document.getElementById('name');
        const emailInput = document.getElementById('email');
        const phoneInput = document.getElementById('phone');
        const termsCheckbox = document.getElementById('agree_terms');
        
        if (passwordInput) passwordInput.addEventListener('input', (e) => checkPasswordStrength(e.target.value));
        if (confirmInput) confirmInput.addEventListener('input', checkPasswordMatch);
        if (nameInput) nameInput.addEventListener('input', validateForm);
        if (emailInput) emailInput.addEventListener('input', validateForm);
        if (phoneInput) phoneInput.addEventListener('input', validateForm);
        if (termsCheckbox) termsCheckbox.addEventListener('change', validateForm);
        
        // Phone number formatting
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.startsWith('0')) value = value.substring(1);
                if (value.length <= 3) {
                    e.target.value = value;
                } else if (value.length <= 6) {
                    e.target.value = value.substring(0, 3) + '-' + value.substring(3);
                } else {
                    e.target.value = value.substring(0, 3) + '-' + value.substring(3, 6) + '-' + value.substring(6, 9);
                }
                validateForm();
            });
        }
        
        // Form submission
        const form = document.getElementById('accept-invitation-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('password')?.value || '';
                const confirm = document.getElementById('password_confirmation')?.value || '';
                
                if (password !== confirm) {
                    e.preventDefault();
                    const matchEl = document.getElementById('password-match');
                    if (matchEl) {
                        matchEl.innerHTML = '✗ Passwords do not match';
                        matchEl.classList.remove('d-none');
                    }
                    return;
                }
                
                const btn = document.getElementById('submit-button');
                const submitText = document.getElementById('submit-text');
                const submitLoading = document.getElementById('submit-loading');
                
                if (btn) btn.disabled = true;
                if (submitText) submitText.classList.add('d-none');
                if (submitLoading) submitLoading.classList.remove('d-none');
            });
        }
        
        // Initial validation
        validateForm();
        
        // Auto-focus
        setTimeout(() => {
            const nameField = document.getElementById('name');
            if (nameField && !nameField.value) {
                nameField.focus();
            }
        }, 100);
    </script>
</body>
</html>