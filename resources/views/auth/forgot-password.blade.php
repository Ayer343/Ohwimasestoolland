<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.short_name', 'Hilltop')) - Forgot Password</title>
    
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
    
    <!-- CRITICAL: Anti-flash dark theme script - runs immediately to prevent light theme flash -->
    <script>
        (function() {
            let theme = 'dark';
            
            try {
                const stored = localStorage.getItem('theme');
                if (stored === 'light' || stored === 'dark') {
                    theme = stored;
                }
            } catch(e) { /* fail silently */ }
            
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
                document.documentElement.style.colorScheme = 'dark';
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.style.colorScheme = 'light';
            }
            
            document.documentElement.setAttribute('data-theme', theme);
            
            const style = document.createElement('style');
            style.textContent = `
                html.dark { background-color: #0a0f1c !important; }
                html:not(.dark) { background-color: #f8fafc !important; }
                body { visibility: visible !important; }
            `;
            document.head.appendChild(style);
        })();
    </script>
    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #0f172a;
            --secondary: #1e3a8a;
            --accent: #8b5cf6;
            --card-bg: rgba(255, 255, 255, 0.95);
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --border-light: #e2e8f0;
            --input-bg: transparent;
            --label-color: #64748b;
            --divider-color: #e2e8f0;
        }
        
        html.dark {
            --primary: #0f172a;
            --secondary: #3b82f6;
            --accent: #a78bfa;
            --card-bg: rgba(15, 23, 42, 0.92);
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --border-light: #334155;
            --input-bg: rgba(51, 65, 85, 0.3);
            --label-color: #94a3b8;
            --divider-color: #334155;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            background-image: url('https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?ixlib=rb-4.0.3&auto=format&fit=crop&w=2076&q=80');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            padding: 1rem;
            margin: 0;
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
        
        html.dark body::before {
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.85) 0%, rgba(15, 23, 42, 0.85) 100%);
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
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            z-index: 2;
            position: relative;
            transition: all 0.3s ease;
        }
        
        html.dark .glass-card {
            background: rgba(15, 23, 42, 0.88);
            border: 1px solid rgba(255, 255, 255, 0.15);
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
        
        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .floating-label {
            position: relative;
            margin-bottom: 20px;
        }
        
        .floating-input {
            border: 0;
            border-bottom: 2px solid var(--border-light);
            outline: none;
            transition: all 0.3s ease;
            background: var(--input-bg);
            padding-left: 10px;
            color: var(--text-primary);
            width: 100%;
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
        }
        
        .floating-input:focus {
            border-color: var(--secondary);
        }
        
        .floating-input.error {
            border-color: #ef4444;
            animation: shake 0.5s ease-in-out;
        }
        
        .floating-label label {
            position: absolute;
            top: 12px;
            left: 10px;
            color: var(--label-color);
            transition: all 0.3s ease;
            pointer-events: none;
        }
        
        .floating-input:focus ~ label,
        .floating-input:not(:placeholder-shown) ~ label {
            top: -15px;
            left: 0;
            font-size: 12px;
            color: var(--secondary);
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
            max-width: 500px;
            margin: 0 auto;
        }
        
        .info-box {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        html.dark .info-box {
            background: rgba(59, 130, 246, 0.15);
        }
        
        .success-box {
            background: rgba(34, 197, 94, 0.1);
            border-left: 4px solid #22c55e;
        }
        
        .error-box {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid #ef4444;
        }
        
        .back-to-login {
            transition: all 0.3s ease;
        }
        
        .back-to-login:hover {
            transform: translateX(-4px);
        }
        
        @media (max-width: 640px) {
            body { padding: 0.5rem; }
            .glass-card { padding: 1.5rem !important; }
        }
    </style>
</head>
<body>
    <div class="bg-pattern"></div>
    
    <div class="content-container">
        <div class="glass-card rounded-3xl shadow-2xl p-8">
            <!-- Header with Icon -->
            <div class="text-center mb-6">
                <div class="w-20 h-20 bg-indigo-100 dark:bg-indigo-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-key text-3xl text-indigo-600 dark:text-indigo-400"></i>
                </div>
                <h2 class="text-2xl font-semibold text-slate-800 dark:text-slate-100">Forgot Password?</h2>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-2">
                    No worries! Enter your email address or phone number below and we'll send you a password reset link.
                </p>
            </div>
            
            <!-- Info Message -->
            <div class="info-box flex items-start">
                <i class="fas fa-info-circle text-blue-500 mr-3 mt-0.5"></i>
                <div class="text-xs text-slate-600 dark:text-slate-300">
                    <p class="font-semibold mb-1">How it works:</p>
                    <ul class="list-disc list-inside space-y-1">
                        <li>Enter your registered email or phone number</li>
                        <li>We'll send a password reset link to your email</li>
                        <li>Click the link to create a new password</li>
                        <li>The link expires after 60 minutes for security</li>
                    </ul>
                </div>
            </div>
            
            <!-- Success Message -->
            @if (session('status'))
                <div class="success-box flex items-start rounded-lg p-4 mb-4">
                    <i class="fas fa-check-circle text-green-500 mr-3 mt-0.5"></i>
                    <div class="text-sm text-green-700 dark:text-green-300">
                        {{ session('status') }}
                    </div>
                </div>
            @endif
            
            <!-- Error Messages -->
            @if ($errors->any())
                <div class="error-box rounded-lg p-4 mb-4">
                    @foreach ($errors->all() as $error)
                        <div class="flex items-start text-sm text-red-700 dark:text-red-300 mb-1">
                            <i class="fas fa-exclamation-circle mr-2 mt-0.5"></i>
                            <span>{{ $error }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
            
            <!-- Reset Form -->
            <form method="POST" action="{{ route('password.email') }}" id="resetForm">
                @csrf
                
                <div class="floating-label">
                    <input 
                        id="login" 
                        type="text" 
                        name="login" 
                        value="{{ old('login') }}" 
                        required 
                        autofocus
                        class="floating-input"
                        placeholder=" "
                        autocomplete="email"
                    >
                    <label for="login">
                        <i class="fas fa-envelope text-slate-400 mr-2"></i>Email or Phone Number
                    </label>
                </div>
                
                <div class="text-xs text-slate-500 dark:text-slate-400 mb-6 -mt-3">
                    <i class="fas fa-info-circle mr-1"></i> 
                    Enter the email address or phone number you used to register
                </div>
                
                <button 
                    type="submit" 
                    class="w-full btn-primary text-white py-3 px-4 rounded-xl font-semibold transition-all duration-200 flex items-center justify-center"
                    id="submitBtn"
                >
                    <i class="fas fa-paper-plane mr-2"></i>
                    Send Reset Link
                </button>
                
                <div class="mt-6 text-center">
                    <a href="{{ route('login') }}" class="back-to-login inline-flex items-center text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Login
                    </a>
                </div>
            </form>
            
            <!-- Support Contact -->
            <div class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-700">
                <p class="text-xs text-center text-slate-500 dark:text-slate-400">
                    <i class="fas fa-headset mr-1"></i>
                    Still having trouble? 
                    <a href="#" class="text-indigo-600 dark:text-indigo-400 hover:underline">Contact Support</a>
                </p>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetForm');
            const loginField = document.getElementById('login');
            const submitBtn = document.getElementById('submitBtn');
            
            // Auto-detect input type and provide helpful hints
            if (loginField) {
                loginField.addEventListener('input', function(e) {
                    const value = e.target.value.trim();
                    
                    // Remove error class if user starts typing
                    this.classList.remove('error');
                    
                    // Optional: Show real-time validation hints
                    if (value.includes('@')) {
                        this.setAttribute('data-type', 'email');
                    } else if (/^\d+$/.test(value) && value.length > 5) {
                        this.setAttribute('data-type', 'phone');
                    }
                });
            }
            
            // Form validation
            if (form) {
                form.addEventListener('submit', function(e) {
                    let valid = true;
                    
                    if (!loginField.value.trim()) {
                        loginField.classList.add('error');
                        valid = false;
                        setTimeout(() => loginField.classList.remove('error'), 500);
                    }
                    
                    if (!valid) {
                        e.preventDefault();
                        return;
                    }
                    
                    // Show loading state
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
                    submitBtn.disabled = true;
                    
                    // Optional: Add timeout to re-enable if something goes wrong
                    setTimeout(() => {
                        if (submitBtn.disabled) {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }
                    }, 30000);
                });
            }
            
            // Focus on login field
            if (loginField) {
                loginField.focus();
            }
            
            // Add keyboard shortcut (Enter to submit)
            if (loginField) {
                loginField.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (form) {
                            form.dispatchEvent(new Event('submit'));
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>