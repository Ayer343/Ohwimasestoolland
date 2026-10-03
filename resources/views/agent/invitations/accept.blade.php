<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept Agent Invitation - {{ $systemSettings->system_name ?? 'Property Management System' }}</title>
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
            background-image: url('https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2076&q=80');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            padding: 1rem;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.8) 0%, rgba(30, 58, 138, 0.7) 100%);
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
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.1);
            z-index: 2;
            position: relative;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(30, 58, 138, 0.4);
        }
        
        .floating-label {
            position: relative;
            margin-bottom: 14px;
        }
        
        .floating-input {
            border: 0;
            border-bottom: 2px solid #cbd5e1;
            outline: none;
            transition: all 0.3s ease;
            background: transparent;
            padding-left: 10px;
            padding-right: 40px;
        }
        
        .floating-input:focus {
            border-color: var(--secondary);
        }
        
        .floating-label label {
            position: absolute;
            top: 10px;
            left: 10px;
            color: #64748b;
            transition: all 0.3s ease;
            pointer-events: none;
            font-size: 13px;
        }
        
        .floating-input:focus ~ label,
        .floating-input:not(:placeholder-shown) ~ label {
            top: -14px;
            left: 0;
            font-size: 11px;
            color: var(--secondary);
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }
        
        .password-toggle:hover {
            color: var(--secondary);
            background: rgba(0, 0, 0, 0.05);
        }
        
        .progress-bar {
            height: 6px;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-bar-fill {
            height: 100%;
            transition: width 0.3s ease;
            border-radius: 3px;
        }
        
        .loading-dots {
            display: inline-flex;
            align-items: center;
        }
        
        .loading-dots span {
            width: 6px;
            height: 6px;
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
        
        .password-strength-weak { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); }
        .password-strength-medium { background: linear-gradient(135deg, #ff9f43 0%, #feca57 100%); }
        .password-strength-strong { background: linear-gradient(135deg, #1dd1a1 0%, #10ac84 100%); }
        .password-strength-very-strong { background: linear-gradient(135deg, #2e86de 0%, #0abde3 100%); }
        
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .animate-shake {
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .content-container {
            z-index: 10;
            position: relative;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem;
        }
        
        .welcome-text {
            flex: 1;
            color: white;
            padding-right: 3rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }
        
        .form-container {
            flex: 0 0 500px;
            width: 100%;
        }
        
        .feature-list {
            margin-top: 1.5rem;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .feature-icon {
            background: rgba(255, 255, 255, 0.2);
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 0.85rem;
        }
        
        /* Compact styles */
        .compact-section {
            margin-bottom: 0.75rem;
        }
        
        .compact-padding {
            padding: 0.5rem 0.75rem;
        }
        
        .agent-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 0.5rem;
        }
        
        .agent-info-item {
            display: flex;
            flex-direction: column;
        }
        
        .agent-info-label {
            font-size: 0.7rem;
            color: #64748b;
            margin-bottom: 0.1rem;
        }
        
        .agent-info-value {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.85rem;
        }
        
        .d-none {
            display: none !important;
        }
        
        /* Mobile-specific styles */
        @media (max-width: 968px) {
            .content-container {
                flex-direction: column;
                padding: 0.5rem;
            }
            
            .welcome-text {
                padding-right: 0;
                padding-bottom: 1rem;
                text-align: center;
                width: 100%;
            }
            
            .welcome-text h1 {
                font-size: 2rem;
                margin-bottom: 0.5rem;
            }
            
            .welcome-text p {
                font-size: 1rem;
                margin-bottom: 1rem;
            }
            
            .form-container {
                flex: 0 0 auto;
                width: 100%;
                max-width: 500px;
            }
            
            .glass-card {
                padding: 1.25rem;
            }
            
            .feature-list {
                display: none;
            }
        }
        
        @media (max-width: 640px) {
            body {
                padding: 0.5rem;
                align-items: flex-start;
                padding-top: 1.5rem;
            }
            
            .content-container {
                padding: 0;
            }
            
            .welcome-text {
                padding-bottom: 0.75rem;
            }
            
            .welcome-text h1 {
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }
            
            .welcome-text p {
                font-size: 0.9rem;
                margin-bottom: 1rem;
            }
            
            .glass-card {
                padding: 1rem;
                border-radius: 1.25rem;
            }
            
            .glass-card .text-center.mb-8 {
                margin-bottom: 0.75rem;
            }
            
            .glass-card .text-center .system-logo {
                max-height: 40px !important;
                margin-bottom: 0.5rem;
            }
            
            .glass-card .text-center h2 {
                font-size: 1.25rem;
                margin-bottom: 0.25rem;
            }
            
            .glass-card .text-center p {
                font-size: 0.8rem;
            }
            
            .agent-info-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.25rem;
            }
            
            .grid-cols-1.md\:grid-cols-2 {
                grid-template-columns: 1fr !important;
                gap: 0.5rem !important;
            }
            
            .floating-input {
                padding: 0.5rem 0.5rem 0.5rem 0.5rem;
                font-size: 14px;
            }
            
            .btn-primary {
                padding: 0.6rem;
                font-size: 0.9rem;
            }
            
            .compact-padding {
                padding: 0.25rem 0.5rem;
            }
            
            #password-requirements {
                margin-top: 0.5rem !important;
            }
            
            #password-requirements .text-xs {
                font-size: 0.65rem !important;
            }
            
            .agent-info-value {
                font-size: 0.8rem;
            }
            
            .agent-info-label {
                font-size: 0.65rem;
            }
        }
        
        @media (max-width: 480px) {
            .glass-card {
                padding: 0.75rem;
            }
            
            h2.text-2xl {
                font-size: 1.25rem;
            }
            
            .agent-info-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .btn-primary {
                padding: 0.5rem;
                font-size: 0.85rem;
            }
        }
        
        /* Improve touch targets for mobile */
        @media (hover: none) and (pointer: coarse) {
            .btn-primary,
            .password-toggle {
                min-height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            input[type="checkbox"] {
                transform: scale(1.1);
                margin-right: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="bg-pattern"></div>
    
    <div class="content-container">
        <!-- Welcome Section - Compact -->
        <div class="welcome-text">
            <h1 class="text-4xl font-bold mb-3">Join Our Elite Agent Team</h1>
            <p class="text-lg mb-4 opacity-90">Hilltop Executive Estate is excited to welcome you as a field agent.</p>
            
            <div class="feature-list">
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-map-marked-alt text-white"></i></div>
                    <span>Assigned Registration Zone</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-tasks text-white"></i></div>
                    <span>Clear Property Targets</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-chart-line text-white"></i></div>
                    <span>Performance Tracking</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-headset text-white"></i></div>
                    <span>Dedicated Support</span>
                </div>
            </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <!-- Invitation Card - Reduced Height -->
            <div class="glass-card rounded-3xl shadow-2xl p-6">
                <!-- Header - Compact -->
                <div class="text-center mb-4">
                    @if(isset($systemSettings) && $systemSettings->system_logo)
                        <img src="{{ asset('storage/' . $systemSettings->system_logo) }}" 
                             alt="{{ $systemSettings->system_name ?? 'System Logo' }}" 
                             class="system-logo mx-auto mb-2" style="max-height: 45px;">
                    @else
                        <div class="property-icon mx-auto mb-2">
                            <i class="fas fa-user-shield fa-2x" style="color: var(--secondary);"></i>
                        </div>
                    @endif
                    
                    <h2 class="text-xl font-semibold text-slate-800 mb-1">Field Agent Invitation</h2>
                    <p class="text-slate-500 text-sm">Complete your registration to access your portal</p>
                    
                    @if($invitation->isActive())
                    <div class="mt-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            <i class="fas fa-check-circle mr-1"></i>
                            Invitation Active
                        </span>
                    </div>
                    @endif
                </div>

                <!-- Invitation Status Validation - Compact -->
                @if(!$invitation->isActive())
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-3 rounded mb-4">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-2 mt-0.5"></i>
                        <div>
                            <h4 class="font-bold text-sm mb-1">
                                @if($invitation->isExpired())
                                    Invitation Expired
                                @elseif($invitation->isAccepted())
                                    Invitation Already Accepted
                                @elseif($invitation->isRevoked())
                                    Invitation Revoked
                                @else
                                    Invitation Not Available
                                @endif
                            </h4>
                            <p class="text-sm mb-2">
                                @if($invitation->isExpired())
                                    This invitation has expired after {{ config('app.invitation_expiry_days', 7) }} days.
                                @elseif($invitation->isAccepted())
                                    This invitation has already been accepted.
                                @elseif($invitation->isRevoked())
                                    This invitation has been revoked.
                                @else
                                    This invitation is no longer valid.
                                @endif
                            </p>
                            
                            <div class="p-2 bg-red-100 rounded text-xs">
                                <p class="mb-1"><strong>Need assistance?</strong> Contact support:</p>
                                @if($supportEmail ?? false)
                                <div class="flex items-center text-blue-600">
                                    <i class="fas fa-envelope mr-1 text-xs"></i>
                                    <a href="mailto:{{ $supportEmail }}" class="text-xs">{{ $supportEmail }}</a>
                                </div>
                                @endif
                                @if($systemSettings && $systemSettings->system_phone)
                                <div class="flex items-center text-green-600">
                                    <i class="fas fa-phone mr-1 text-xs"></i>
                                    <a href="tel:{{ $systemSettings->system_phone }}" class="text-xs">{{ $systemSettings->system_phone }}</a>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @else

                <!-- Invitation Details - Compact -->
                @if(isset($invitation) && $invitation)
                <div class="bg-blue-50 border-l-4 border-blue-500 p-3 rounded-lg mb-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 bg-blue-100 p-2 rounded mr-3">
                            <i class="fas fa-map-marker-alt text-blue-600 text-sm"></i>
                        </div>
                        <div class="flex-grow-1 w-full">
                            <h4 class="font-semibold text-blue-800 text-sm mb-2">Assignment Details</h4>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                                <div>
                                    <span class="text-slate-600">Zone:</span>
                                    <div class="font-semibold text-slate-800">{{ $invitation->plan->zone }}</div>
                                </div>
                                @if($invitation->plan->section)
                                <div>
                                    <span class="text-slate-600">Section:</span>
                                    <div class="font-semibold text-slate-800">{{ $invitation->plan->section }}</div>
                                </div>
                                @endif
                                <div>
                                    <span class="text-slate-600">Properties:</span>
                                    <div class="font-semibold text-slate-800">{{ $invitation->plan->estimated_houses }}</div>
                                </div>
                                @if($invitation->plan->registration_start_date && $invitation->plan->registration_end_date)
                                <div>
                                    <span class="text-slate-600">Timeline:</span>
                                    <div class="font-semibold text-slate-800 text-xs">
                                        {{ $invitation->plan->registration_start_date->format('M j') }} - 
                                        {{ $invitation->plan->registration_end_date->format('M j, Y') }}
                                    </div>
                                </div>
                                @endif
                            </div>

                            <!-- Expiration Status - Compact -->
                            @if($invitation->expires_at)
                            <div class="mt-2 p-2 bg-blue-100 rounded text-xs">
                                @php
                                    $daysRemaining = $invitation->getDaysUntilExpiry();
                                    $isExpiringSoon = $daysRemaining !== null && $daysRemaining <= config('app.invitation_warning_days', 2);
                                    $totalExpiryDays = config('app.invitation_expiry_days', 7);
                                    $daysUsed = max(0, $totalExpiryDays - $daysRemaining);
                                    $percentageUsed = min(100, max(0, ($daysUsed / $totalExpiryDays) * 100));
                                @endphp
                                
                                <div class="flex justify-between items-center mb-1">
                                    <span class="font-medium">Expires: <span class="font-bold">{{ $invitation->expires_at->format('M j, Y') }}</span></span>
                                    <span class="font-bold {{ $isExpiringSoon ? 'text-orange-500' : 'text-green-600' }}">
                                        {{ $daysRemaining ?? 'N/A' }} day{{ $daysRemaining != 1 ? 's' : '' }} left
                                    </span>
                                </div>
                                
                                <div class="progress-bar">
                                    <div class="progress-bar-fill {{ $isExpiringSoon ? 'password-strength-medium' : 'bg-blue-500' }}" 
                                         style="width: {{ $percentageUsed }}%"></div>
                                </div>
                                
                                @if($isExpiringSoon)
                                <div class="mt-1 text-orange-500 text-xs flex items-center">
                                    <i class="fas fa-clock mr-1"></i>
                                    Expiring soon - accept now
                                </div>
                                @endif
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                <!-- Account Setup Form - Compact -->
                <form action="{{ route('agent.invitations.accept', $invitation->token) }}" method="POST" id="accept-invitation-form">
                    @csrf

                    <!-- Agent Information - Compact -->
                    <div class="mb-3 p-3 bg-slate-50 rounded-lg">
                        <h5 class="font-semibold flex items-center mb-2 text-slate-700 text-sm">
                            <div class="bg-blue-100 p-1.5 rounded mr-2">
                                <i class="fas fa-user-circle text-blue-600 text-sm"></i>
                            </div>
                            Your Information
                        </h5>
                        <div class="agent-info-grid">
                            <div class="agent-info-item">
                                <span class="agent-info-label">Name:</span>
                                <span class="agent-info-value">{{ $invitation->agent->name }}</span>
                            </div>
                            @if($invitation->agent->email)
                            <div class="agent-info-item">
                                <span class="agent-info-label">Email:</span>
                                <span class="agent-info-value">{{ $invitation->agent->email }}</span>
                            </div>
                            @endif
                            @if($invitation->agent->phone)
                            <div class="agent-info-item">
                                <span class="agent-info-label">Phone:</span>
                                <span class="agent-info-value">{{ $invitation->agent->phone }}</span>
                            </div>
                            @endif
                            <div class="agent-info-item">
                                <span class="agent-info-label">Role:</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Field Agent
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Password Setup - Compact -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                        <div>
                            <label for="password" class="block text-xs font-medium text-slate-700 mb-1">
                                <i class="fas fa-lock text-blue-600 mr-1"></i> Create Password *
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       required 
                                       minlength="8"
                                       class="floating-input w-full py-2 px-2 border-0 border-b-2 focus:ring-0 text-sm"
                                       placeholder=" "
                                       autocomplete="new-password">
                                <label for="password" class="floating-label text-xs">
                                    <i class="fas fa-lock text-slate-400 mr-1"></i>Enter your password
                                </label>
                                <button type="button" 
                                        class="password-toggle text-xs"
                                        onclick="togglePasswordVisibility('password')">
                                    <i class="fas fa-eye" id="password-eye-icon"></i>
                                </button>
                            </div>
                            
                            <!-- Password Strength - Compact -->
                            <div class="mt-2">
                                <div class="progress-bar">
                                    <div id="password-strength-fill" class="progress-bar-fill w-0"></div>
                                </div>
                                <div id="password-strength-text" class="text-xs font-semibold mt-1"></div>
                            </div>

                            <!-- Password Requirements - Compact -->
                            <div id="password-requirements" class="mt-2 space-y-0.5">
                                <div class="text-xs text-slate-500 mb-1">Password must contain:</div>
                                <div id="req-length" class="text-xs flex items-center">
                                    <i class="fas fa-check-circle text-green-500 mr-1 d-none text-xs"></i>
                                    <i class="fas fa-times-circle text-red-500 mr-1 d-none text-xs"></i>
                                    At least 8 characters
                                </div>
                                <div id="req-uppercase" class="text-xs flex items-center">
                                    <i class="fas fa-check-circle text-green-500 mr-1 d-none text-xs"></i>
                                    <i class="fas fa-times-circle text-red-500 mr-1 d-none text-xs"></i>
                                    One uppercase letter
                                </div>
                                <div id="req-lowercase" class="text-xs flex items-center">
                                    <i class="fas fa-check-circle text-green-500 mr-1 d-none text-xs"></i>
                                    <i class="fas fa-times-circle text-red-500 mr-1 d-none text-xs"></i>
                                    One lowercase letter
                                </div>
                                <div id="req-number" class="text-xs flex items-center">
                                    <i class="fas fa-check-circle text-green-500 mr-1 d-none text-xs"></i>
                                    <i class="fas fa-times-circle text-red-500 mr-1 d-none text-xs"></i>
                                    One number
                                </div>
                                <div id="req-special" class="text-xs flex items-center">
                                    <i class="fas fa-check-circle text-green-500 mr-1 d-none text-xs"></i>
                                    <i class="fas fa-times-circle text-red-500 mr-1 d-none text-xs"></i>
                                    One special character
                                </div>
                            </div>

                            @error('password')
                                <div class="text-red-500 text-xs mt-1 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-xs font-medium text-slate-700 mb-1">
                                <i class="fas fa-lock text-blue-600 mr-1"></i> Confirm Password *
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       id="password_confirmation" 
                                       name="password_confirmation" 
                                       required
                                       class="floating-input w-full py-2 px-2 border-0 border-b-2 focus:ring-0 text-sm"
                                       placeholder=" "
                                       autocomplete="new-password">
                                <label for="password_confirmation" class="floating-label text-xs">
                                    <i class="fas fa-lock text-slate-400 mr-1"></i>Confirm your password
                                </label>
                                <button type="button" 
                                        class="password-toggle text-xs"
                                        onclick="togglePasswordVisibility('password_confirmation')">
                                    <i class="fas fa-eye" id="password_confirmation-eye-icon"></i>
                                </button>
                            </div>
                            
                            <!-- Password Match Indicator -->
                            <div id="password-match" class="mt-1 d-none">
                                <div class="text-green-500 text-xs flex items-center">
                                    <i class="fas fa-check-circle mr-1"></i> Passwords match
                                </div>
                            </div>
                            <div id="password-mismatch" class="mt-1 d-none">
                                <div class="text-red-500 text-xs flex items-center">
                                    <i class="fas fa-times-circle mr-1"></i> Passwords do not match
                                </div>
                            </div>

                            @error('password_confirmation')
                                <div class="text-red-500 text-xs mt-1 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>

                    <!-- Terms Agreement - Compact -->
                    <div class="mb-3">
                        <div class="flex items-start">
                            <input class="form-check-input mt-0.5 mr-2 h-3.5 w-3.5 text-blue-600 focus:ring-blue-500 border-slate-300 rounded" 
                                   type="checkbox" 
                                   id="terms" 
                                   name="terms" 
                                   required>
                            <label class="text-xs text-slate-700" for="terms">
                                I agree to the 
                                <a href="{{ route('terms') }}" target="_blank" class="text-blue-600 hover:text-blue-800">Terms of Service</a>
                                and 
                                <a href="{{ route('privacy') }}" target="_blank" class="text-blue-600 hover:text-blue-800">Privacy Policy</a>
                            </label>
                        </div>
                        <div class="text-xs text-slate-500 mt-0.5 ml-6">
                            By accepting, you agree to comply with all field agent guidelines.
                        </div>
                        @error('terms')
                            <div class="text-red-500 text-xs mt-1 flex items-center ml-6">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <!-- Submit Button - Compact -->
                    <div class="mb-3">
                        <button type="submit" 
                                id="submit-button"
                                class="w-full btn-primary text-white py-2 px-4 rounded-xl font-semibold focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 flex items-center justify-center text-sm"
                                disabled>
                            <i class="fas fa-user-check mr-2"></i>
                            <span id="submit-text">Activate Account & Start Working</span>
                            <span id="submit-loading" class="d-none">
                                <span class="loading-dots mr-2">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </span>
                                Setting up...
                            </span>
                        </button>
                    </div>

                    <!-- Security Notice - Compact -->
                    <div class="p-2 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center text-green-700 text-xs">
                            <i class="fas fa-shield-alt mr-2"></i>
                            <div>
                                <strong>Secure Setup:</strong> Your password is encrypted. 
                                You'll have access to your assigned registration plans immediately after activation.
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Support Information - Compact -->
                <div class="text-center pt-3 mt-3 border-t border-slate-200">
                    <p class="text-xs text-slate-600 mb-1">
                        Need help? Contact {{ $systemName ?? 'System' }} support at 
                        <a href="mailto:{{ $supportEmail ?? 'support@example.com' }}" class="text-blue-600 hover:text-blue-800">
                            {{ $supportEmail ?? 'support@example.com' }}
                        </a>
                    </p>
                    @if($systemSettings && $systemSettings->system_phone)
                    <p class="text-xs text-slate-600 mb-1">
                        or call <a href="tel:{{ $systemSettings->system_phone }}" class="text-green-600 hover:text-green-800">
                            {{ $systemSettings->system_phone }}
                        </a>
                    </p>
                    @endif
                    <p class="text-xs text-slate-500">
                        Invitations valid for {{ config('app.invitation_expiry_days', 7) }} days
                    </p>
                </div>

                @endif <!-- End of invitation active check -->
            </div>
        </div>
    </div>

    <script>
        // Password strength functionality
        function checkPasswordStrength(password) {
            let strength = 0;
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password)
            };

            if (requirements.length) strength += 1;
            if (requirements.uppercase) strength += 1;
            if (requirements.lowercase) strength += 1;
            if (requirements.number) strength += 1;
            if (requirements.special) strength += 1;

            return { strength, requirements };
        }

        function updatePasswordStrength() {
            const password = document.getElementById('password').value;
            const { strength, requirements } = checkPasswordStrength(password);

            const strengthFill = document.getElementById('password-strength-fill');
            const percentage = (strength / 5) * 100;
            
            if (strengthFill) {
                strengthFill.style.width = `${percentage}%`;
                
                if (strength >= 4) {
                    strengthFill.className = 'progress-bar-fill password-strength-very-strong';
                } else if (strength >= 3) {
                    strengthFill.className = 'progress-bar-fill password-strength-strong';
                } else if (strength >= 2) {
                    strengthFill.className = 'progress-bar-fill password-strength-medium';
                } else {
                    strengthFill.className = 'progress-bar-fill password-strength-weak';
                }
            }

            const strengthText = document.getElementById('password-strength-text');
            const strengthLabels = {
                0: 'Very Weak',
                1: 'Weak',
                2: 'Fair', 
                3: 'Good',
                4: 'Strong',
                5: 'Very Strong'
            };
            
            if (strengthText) {
                strengthText.textContent = password ? `${strengthLabels[strength] || 'Very Weak'}` : '';
                strengthText.className = `text-xs font-semibold ${
                    strength >= 4 ? 'text-blue-600' :
                    strength >= 3 ? 'text-green-600' :
                    strength >= 2 ? 'text-orange-500' :
                    'text-red-500'
                }`;
            }

            Object.keys(requirements).forEach(req => {
                updateRequirementIndicator(req, requirements[req]);
            });

            validateForm();
        }

        function updateRequirementIndicator(type, met) {
            const reqElement = document.getElementById(`req-${type}`);
            if (!reqElement) return;

            const checkIcon = reqElement.querySelector('.fa-check-circle');
            const timesIcon = reqElement.querySelector('.fa-times-circle');

            if (checkIcon && timesIcon) {
                if (met) {
                    checkIcon.classList.remove('d-none');
                    timesIcon.classList.add('d-none');
                    reqElement.classList.add('text-green-500');
                    reqElement.classList.remove('text-red-500', 'text-slate-500');
                } else {
                    checkIcon.classList.add('d-none');
                    timesIcon.classList.remove('d-none');
                    reqElement.classList.add('text-red-500');
                    reqElement.classList.remove('text-green-500', 'text-slate-500');
                }
            }
        }

        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirmation').value;
            const matchElement = document.getElementById('password-match');
            const mismatchElement = document.getElementById('password-mismatch');

            if (!confirmPassword) {
                if (matchElement) matchElement.classList.add('d-none');
                if (mismatchElement) mismatchElement.classList.add('d-none');
                return;
            }

            if (password === confirmPassword) {
                if (matchElement) {
                    matchElement.classList.remove('d-none');
                    matchElement.innerHTML = '<div class="text-green-500 text-xs flex items-center"><i class="fas fa-check-circle mr-1"></i>Passwords match</div>';
                }
                if (mismatchElement) mismatchElement.classList.add('d-none');
            } else {
                if (matchElement) matchElement.classList.add('d-none');
                if (mismatchElement) {
                    mismatchElement.classList.remove('d-none');
                    mismatchElement.innerHTML = '<div class="text-red-500 text-xs flex items-center"><i class="fas fa-times-circle mr-1"></i>Passwords do not match</div>';
                }
            }

            validateForm();
        }

        function validateForm() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirmation').value;
            const termsAccepted = document.getElementById('terms').checked;
            
            const { requirements } = checkPasswordStrength(password);
            
            const passwordValid = Object.values(requirements).every(met => met);
            const passwordsMatch = password === confirmPassword && confirmPassword.length > 0;
            
            const submitButton = document.getElementById('submit-button');
            if (submitButton) {
                submitButton.disabled = !(passwordValid && passwordsMatch && termsAccepted);
            }
        }

        function togglePasswordVisibility(fieldId) {
            const field = document.getElementById(fieldId);
            const eyeIcon = document.getElementById(`${fieldId}-eye-icon`);
            
            if (!field || !eyeIcon) return;

            if (field.type === 'password') {
                field.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }

        // Form submission
        document.getElementById('accept-invitation-form').addEventListener('submit', function(e) {
            const submitButton = document.getElementById('submit-button');
            const submitText = document.getElementById('submit-text');
            const submitLoading = document.getElementById('submit-loading');

            if (submitButton.disabled) {
                e.preventDefault();
                return;
            }

            submitText.classList.add('d-none');
            submitLoading.classList.remove('d-none');
            submitButton.disabled = true;
        });

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('password').addEventListener('input', updatePasswordStrength);
            document.getElementById('password_confirmation').addEventListener('input', checkPasswordMatch);
            document.getElementById('terms').addEventListener('change', validateForm);
            validateForm();
        });
    </script>
</body>
</html>