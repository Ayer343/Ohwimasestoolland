<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reset Password - {{ config('app.name', 'Hilltop') }}</title>
    
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
    
    <script>
        (function() {
            let theme = 'dark';
            try {
                const stored = localStorage.getItem('theme');
                if (stored === 'light' || stored === 'dark') {
                    theme = stored;
                }
            } catch(e) {}
            
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
                document.documentElement.style.colorScheme = 'dark';
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.style.colorScheme = 'light';
            }
        })();
    </script>
    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #0f172a;
            --secondary: #1e3a8a;
            --card-bg: rgba(255, 255, 255, 0.95);
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --border-light: #e2e8f0;
            --input-bg: transparent;
            --label-color: #64748b;
        }
        
        html.dark {
            --primary: #0f172a;
            --secondary: #3b82f6;
            --card-bg: rgba(15, 23, 42, 0.92);
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --border-light: #334155;
            --input-bg: rgba(51, 65, 85, 0.3);
            --label-color: #94a3b8;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: url('https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=2076&q=80');
            background-size: cover;
            background-position: center;
            padding: 1rem;
            position: relative;
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
        
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            position: relative;
            z-index: 2;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
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
            margin-bottom: 25px;
        }
        
        .floating-input {
            border: 0;
            border-bottom: 2px solid var(--border-light);
            outline: none;
            background: var(--input-bg);
            padding: 0.75rem 10px;
            color: var(--text-primary);
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .floating-input:focus {
            border-color: var(--secondary);
        }
        
        .floating-input:read-only {
            opacity: 0.8;
            cursor: not-allowed;
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
            top: -20px;
            left: 0;
            font-size: 12px;
            color: var(--secondary);
        }
        
        .password-strength {
            margin-top: 5px;
            height: 4px;
            border-radius: 2px;
            overflow: hidden;
            background: var(--border-light);
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .requirement-list {
            font-size: 0.7rem;
            margin-top: 0.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .requirement {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            background: rgba(0, 0, 0, 0.05);
        }
        
        html.dark .requirement {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .requirement.met {
            color: #10b981;
        }
        
        .requirement.unmet {
            color: #ef4444;
        }
        
        .error-box {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid #ef4444;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .success-box {
            background: rgba(34, 197, 94, 0.1);
            border-left: 4px solid #22c55e;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .content-container {
            z-index: 10;
            position: relative;
            max-width: 450px;
            width: 100%;
            margin: 0 auto;
        }
        
        @media (max-width: 640px) {
            .glass-card { padding: 1.5rem !important; }
        }
    </style>
</head>
<body>
    <div class="content-container">
        <div class="glass-card rounded-3xl shadow-2xl p-8">
            <div class="text-center mb-6">
                <div class="w-20 h-20 bg-indigo-100 dark:bg-indigo-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-lock-open text-3xl text-indigo-600 dark:text-indigo-400"></i>
                </div>
                <h2 class="text-2xl font-semibold text-slate-800 dark:text-slate-100">Reset Password</h2>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-2">
                    Please enter your new password
                </p>
            </div>
            
            @if ($errors->any())
                <div class="error-box">
                    @foreach ($errors->all() as $error)
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
                    @endforeach
                </div>
            @endif
            
            @if (session('status'))
                <div class="success-box">
                    <p class="text-sm text-green-600 dark:text-green-400">{{ session('status') }}</p>
                </div>
            @endif
            
            <form method="POST" action="{{ route('password.update') }}" id="resetForm">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                
                @php
                    // Decode the email if it's URL encoded
                    $decodedEmail = urldecode(request()->get('email', old('email')));
                @endphp
                
                <div class="floating-label">
                    <input 
                        id="email" 
                        type="email" 
                        name="email" 
                        value="{{ $decodedEmail }}" 
                        required 
                        class="floating-input"
                        placeholder=" "
                        readonly
                    >
                    <label for="email">
                        <i class="fas fa-envelope text-slate-400 mr-2"></i>Email Address
                    </label>
                </div>
                
                <div class="floating-label">
                    <input 
                        id="password" 
                        type="password" 
                        name="password" 
                        required 
                        class="floating-input"
                        placeholder=" "
                        autocomplete="new-password"
                    >
                    <label for="password">
                        <i class="fas fa-lock text-slate-400 mr-2"></i>New Password
                    </label>
                </div>
                
                <div class="password-strength">
                    <div class="password-strength-bar" id="strengthBar"></div>
                </div>
                
                <div class="requirement-list" id="requirements">
                    <span class="requirement unmet" id="reqLength"><i class="fas fa-circle"></i> 8+ characters</span>
                    <span class="requirement unmet" id="reqUpper"><i class="fas fa-circle"></i> Uppercase letter</span>
                    <span class="requirement unmet" id="reqLower"><i class="fas fa-circle"></i> Lowercase letter</span>
                    <span class="requirement unmet" id="reqNumber"><i class="fas fa-circle"></i> Number</span>
                    <span class="requirement unmet" id="reqSpecial"><i class="fas fa-circle"></i> Special character</span>
                </div>
                
                <div class="floating-label mt-4">
                    <input 
                        id="password_confirmation" 
                        type="password" 
                        name="password_confirmation" 
                        required 
                        class="floating-input"
                        placeholder=" "
                        autocomplete="new-password"
                    >
                    <label for="password_confirmation">
                        <i class="fas fa-check-circle text-slate-400 mr-2"></i>Confirm Password
                    </label>
                </div>
                
                <button 
                    type="submit" 
                    class="w-full btn-primary text-white py-3 px-4 rounded-xl font-semibold transition-all duration-200 flex items-center justify-center mt-6"
                    id="submitBtn"
                >
                    <i class="fas fa-save mr-2"></i>
                    Reset Password
                </button>
                
                <div class="mt-6 text-center">
                    <a href="{{ route('login') }}" class="inline-flex items-center text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 transition duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Login
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('password');
            const confirmField = document.getElementById('password_confirmation');
            const submitBtn = document.getElementById('submitBtn');
            const form = document.getElementById('resetForm');
            const strengthBar = document.getElementById('strengthBar');
            
            // Also decode email in JavaScript as a backup
            const emailField = document.getElementById('email');
            if (emailField && emailField.value) {
                try {
                    // Check if email contains %40 (URL encoded @)
                    if (emailField.value.includes('%40')) {
                        emailField.value = decodeURIComponent(emailField.value);
                    }
                } catch(e) {
                    console.error('Failed to decode email:', e);
                }
            }
            
            function checkPasswordStrength(password) {
                const tests = {
                    length: password.length >= 8,
                    upper: /[A-Z]/.test(password),
                    lower: /[a-z]/.test(password),
                    number: /[0-9]/.test(password),
                    special: /[^A-Za-z0-9]/.test(password)
                };
                
                // Update requirement icons
                document.getElementById('reqLength').className = `requirement ${tests.length ? 'met' : 'unmet'}`;
                document.getElementById('reqUpper').className = `requirement ${tests.upper ? 'met' : 'unmet'}`;
                document.getElementById('reqLower').className = `requirement ${tests.lower ? 'met' : 'unmet'}`;
                document.getElementById('reqNumber').className = `requirement ${tests.number ? 'met' : 'unmet'}`;
                document.getElementById('reqSpecial').className = `requirement ${tests.special ? 'met' : 'unmet'}`;
                
                // Calculate strength percentage
                let strength = Object.values(tests).filter(Boolean).length;
                let width = (strength / 5) * 100;
                let color = '#ef4444';
                
                if (strength >= 4) color = '#10b981';
                else if (strength >= 3) color = '#f59e0b';
                
                strengthBar.style.width = width + '%';
                strengthBar.style.backgroundColor = color;
                
                return strength === 5;
            }
            
            if (passwordField) {
                passwordField.addEventListener('input', function() {
                    checkPasswordStrength(this.value);
                    
                    // Check password confirmation match
                    if (confirmField && confirmField.value) {
                        if (this.value === confirmField.value) {
                            confirmField.style.borderColor = '#10b981';
                        } else {
                            confirmField.style.borderColor = '#ef4444';
                        }
                    }
                });
            }
            
            if (confirmField) {
                confirmField.addEventListener('input', function() {
                    if (passwordField && this.value === passwordField.value) {
                        this.style.borderColor = '#10b981';
                    } else {
                        this.style.borderColor = '#ef4444';
                    }
                });
            }
            
            // Form validation
            if (form) {
                form.addEventListener('submit', function(e) {
                    let valid = true;
                    let errorMessage = '';
                    
                    if (!passwordField.value) {
                        valid = false;
                        errorMessage = 'Please enter a password';
                    } else if (passwordField.value.length < 8) {
                        valid = false;
                        errorMessage = 'Password must be at least 8 characters';
                    } else if (!/[A-Z]/.test(passwordField.value)) {
                        valid = false;
                        errorMessage = 'Password must contain at least one uppercase letter';
                    } else if (!/[a-z]/.test(passwordField.value)) {
                        valid = false;
                        errorMessage = 'Password must contain at least one lowercase letter';
                    } else if (!/[0-9]/.test(passwordField.value)) {
                        valid = false;
                        errorMessage = 'Password must contain at least one number';
                    } else if (!/[^A-Za-z0-9]/.test(passwordField.value)) {
                        valid = false;
                        errorMessage = 'Password must contain at least one special character';
                    } else if (passwordField.value !== confirmField.value) {
                        valid = false;
                        errorMessage = 'Passwords do not match';
                    }
                    
                    if (!valid) {
                        e.preventDefault();
                        // Show error message temporarily
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'error-box mb-4';
                        errorDiv.innerHTML = `<p class="text-sm text-red-600 dark:text-red-400"><i class="fas fa-exclamation-circle mr-2"></i>${errorMessage}</p>`;
                        form.insertBefore(errorDiv, form.firstChild);
                        setTimeout(() => errorDiv.remove(), 3000);
                        return;
                    }
                    
                    // Show loading state
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Resetting Password...';
                    submitBtn.disabled = true;
                    
                    // Optional: Add timeout to re-enable if something goes wrong
                    setTimeout(() => {
                        if (submitBtn.disabled && !form.submitted) {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }
                    }, 30000);
                });
            }
        });
    </script>
</body>
</html>