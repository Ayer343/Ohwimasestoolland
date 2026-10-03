<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Your Password - {{ config('app.name', 'Hilltop') }}</title>
    
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
        <meta name="msapplication-TileColor" content="#4a6ee0">
    @else
        <!-- Default favicon fallback -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <meta name="msapplication-TileColor" content="#4a6ee0">
    @endif
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .invitation-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            margin: 20px;
        }
        
        .invitation-header {
            background: linear-gradient(135deg, #4a6ee0 0%, #667eea 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .invitation-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .invitation-header p {
            opacity: 0.9;
            margin: 0;
        }
        
        .invitation-body {
            padding: 40px;
        }
        
        .user-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #4a6ee0;
        }
        
        .user-info h5 {
            color: #4a6ee0;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: 600;
            min-width: 120px;
            color: #666;
        }
        
        .info-value {
            color: #333;
        }
        
        .security-tips {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .security-tips h6 {
            color: #856404;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .security-tips ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .security-tips li {
            margin-bottom: 5px;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .password-strength {
            height: 5px;
            background: #e9ecef;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            background: #dc3545;
            transition: all 0.3s ease;
        }
        
        .password-strength-bar.weak {
            background: #dc3545;
            width: 30%;
        }
        
        .password-strength-bar.fair {
            background: #ffc107;
            width: 60%;
        }
        
        .password-strength-bar.good {
            background: #28a745;
            width: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4a6ee0 0%, #667eea 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 110, 224, 0.4);
        }
        
        .expiry-warning {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            color: #721c24;
            font-size: 14px;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .logo i {
            font-size: 36px;
            color: #4a6ee0;
        }
        
        .help-text {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="invitation-card">
        <div class="invitation-header">
            <div class="logo">
                <i class="fas fa-user-shield"></i>
            </div>
            <h1>Set Your Password</h1>
            <p>Complete your account setup</p>
        </div>
        
        <div class="invitation-body">
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            
            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            
            <!-- User Information -->
            <div class="user-info">
                <h5><i class="fas fa-user-circle me-2"></i> Account Information</h5>
                <div class="info-item">
                    <span class="info-label">Name:</span>
                    <span class="info-value">{{ $user->name }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value">{{ $user->email }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Role:</span>
                    <span class="info-value">Super Administrator</span>
                </div>
            </div>
            
            <!-- Expiry Warning -->
            @if($invitation->is_expiring_soon)
                <div class="expiry-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This invitation expires in {{ $invitation->days_until_expiry }} day(s)
                </div>
            @endif
            
            <!-- Security Tips -->
            <div class="security-tips">
                <h6><i class="fas fa-shield-alt me-2"></i> Security Requirements</h6>
                <ul>
                    <li>Password must be at least 8 characters long</li>
                    <li>Use a combination of uppercase and lowercase letters</li>
                    <li>Include numbers and special characters for better security</li>
                    <li>Avoid using personal information in your password</li>
                </ul>
            </div>
            
            <!-- Password Setup Form -->
            <form method="POST" action="{{ route('invitation.setup-password') }}" id="passwordSetupForm">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                
                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="fas fa-key me-2"></i> New Password
                    </label>
                    <div class="input-group">
                        <input type="password" 
                               class="form-control @error('password') is-invalid @enderror" 
                               id="password" 
                               name="password" 
                               required
                               minlength="8"
                               autocomplete="new-password"
                               placeholder="Enter your new password">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    @error('password')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <div class="password-strength mt-2">
                        <div class="password-strength-bar" id="passwordStrengthBar"></div>
                    </div>
                    <div class="help-text" id="passwordHelp"></div>
                </div>
                
                <div class="mb-4">
                    <label for="password_confirmation" class="form-label">
                        <i class="fas fa-key me-2"></i> Confirm Password
                    </label>
                    <div class="input-group">
                        <input type="password" 
                               class="form-control @error('password_confirmation') is-invalid @enderror" 
                               id="password_confirmation" 
                               name="password_confirmation" 
                               required
                               minlength="8"
                               autocomplete="new-password"
                               placeholder="Confirm your new password">
                        <button class="btn btn-outline-secondary" type="button" id="togglePasswordConfirmation">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    @error('password_confirmation')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <div class="help-text" id="passwordMatchMessage"></div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                        <i class="fas fa-check-circle me-2"></i> Set Password & Continue
                    </button>
                    <a href="{{ route('invitation.cancel') }}" class="btn btn-outline-secondary" onclick="return confirm('Are you sure you want to cancel? You will need to use your invitation link again.')">
                        <i class="fas fa-times me-2"></i> Cancel
                    </a>
                </div>
            </form>
            
            <div class="text-center mt-4">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    By setting your password, you agree to our Terms of Service and Privacy Policy
                </small>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const passwordConfirmInput = document.getElementById('password_confirmation');
            const togglePasswordBtn = document.getElementById('togglePassword');
            const togglePasswordConfirmationBtn = document.getElementById('togglePasswordConfirmation');
            const passwordStrengthBar = document.getElementById('passwordStrengthBar');
            const passwordHelp = document.getElementById('passwordHelp');
            const passwordMatchMessage = document.getElementById('passwordMatchMessage');
            const submitBtn = document.getElementById('submitBtn');
            const form = document.getElementById('passwordSetupForm');
            
            // Toggle password visibility
            togglePasswordBtn.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
            
            togglePasswordConfirmationBtn.addEventListener('click', function() {
                const type = passwordConfirmInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordConfirmInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
            
            // Password strength checker
            function checkPasswordStrength(password) {
                let strength = 0;
                let messages = [];
                
                if (password.length >= 8) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[a-z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^A-Za-z0-9]/.test(password)) strength++;
                
                // Set strength bar
                passwordStrengthBar.className = 'password-strength-bar';
                
                if (password.length === 0) {
                    passwordHelp.textContent = '';
                    return;
                }
                
                if (strength <= 2) {
                    passwordStrengthBar.classList.add('weak');
                    passwordHelp.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle"></i> Weak password</span>';
                } else if (strength <= 4) {
                    passwordStrengthBar.classList.add('fair');
                    passwordHelp.innerHTML = '<span class="text-warning"><i class="fas fa-check-circle"></i> Fair password</span>';
                } else {
                    passwordStrengthBar.classList.add('good');
                    passwordHelp.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Strong password</span>';
                }
            }
            
            // Password match checker
            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = passwordConfirmInput.value;
                
                if (confirmPassword.length === 0) {
                    passwordMatchMessage.textContent = '';
                    return;
                }
                
                if (password === confirmPassword) {
                    passwordMatchMessage.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Passwords match</span>';
                } else {
                    passwordMatchMessage.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> Passwords do not match</span>';
                }
            }
            
            // Form validation
            function validateForm() {
                const password = passwordInput.value;
                const confirmPassword = passwordConfirmInput.value;
                
                if (password.length < 8) {
                    passwordHelp.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle"></i> Password must be at least 8 characters</span>';
                    return false;
                }
                
                if (password !== confirmPassword) {
                    passwordMatchMessage.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> Passwords must match</span>';
                    return false;
                }
                
                return true;
            }
            
            // Event listeners
            passwordInput.addEventListener('input', function() {
                checkPasswordStrength(this.value);
                checkPasswordMatch();
            });
            
            passwordConfirmInput.addEventListener('input', checkPasswordMatch);
            
            // Form submission
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    // Scroll to first error
                    const firstError = document.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else {
                    // Disable submit button to prevent double submission
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Setting up your account...';
                }
            });
            
            // Auto-focus on password field
            passwordInput.focus();
        });
    </script>
</body>
</html>