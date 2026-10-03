<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Tenant Registration - {{ $systemSettings->system_name ?? 'Property Management System' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
        }
        .registration-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin: 20px auto;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .system-logo {
            max-height: 60px;
            max-width: 200px;
            object-fit: contain;
        }
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .system-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        .registration-header {
            border-bottom: 2px solid #f8f9fa;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .tenant-icon {
            font-size: 3rem;
            color: #667eea;
        }
        .alert-success {
            border-left: 4px solid #28a745;
            background: linear-gradient(135deg, #f8fff9 0%, #e8f5e9 100%);
            border: none;
            border-radius: 10px;
        }
        .property-details-card {
            border-left: 4px solid #667eea;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        .progress {
            height: 5px;
        }
        .progress-bar {
            border-radius: 2px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0 20px;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #dee2e6;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        .step.active .step-circle {
            background: #667eea;
            color: white;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }
        .step.completed .step-circle {
            background: #28a745;
            color: white;
        }
        .step-label {
            font-size: 0.875rem;
            color: #6c757d;
            font-weight: 500;
        }
        .step.active .step-label {
            color: #667eea;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="registration-card p-4 p-md-5">
                    <!-- Header with System Logo -->
                    <div class="registration-header text-center">
                        <div class="logo-container">
                            @if(isset($systemSettings) && $systemSettings->system_logo)
                                <img src="{{ asset('storage/' . $systemSettings->system_logo) }}" 
                                     alt="{{ $systemSettings->system_name ?? 'System Logo' }}" 
                                     class="system-logo">
                            @else
                                <div class="tenant-icon">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                            @endif
                            @if(isset($systemSettings) && $systemSettings->system_name)
                                <h1 class="system-name">{{ $systemSettings->system_name }}</h1>
                            @endif
                        </div>
                        <h2 class="mb-2">Complete Tenant Registration</h2>
                        <p class="text-muted">Finish setting up your tenant account</p>
                    </div>

                    <!-- Step Indicator -->
                    <div class="step-indicator">
                        <div class="step completed">
                            <div class="step-circle">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="step-label">Invitation</div>
                        </div>
                        <div class="step active">
                            <div class="step-circle">2</div>
                            <div class="step-label">Registration</div>
                        </div>
                        <div class="step">
                            <div class="step-circle">3</div>
                            <div class="step-label">Complete</div>
                        </div>
                    </div>

                    <!-- Success Alert -->
                    <div class="alert alert-success mb-4">
                        <div class="d-flex">
                            <div class="me-3">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                            <div>
                                <h5 class="alert-heading mb-2">Almost Done!</h5>
                                <p class="mb-0">Just a few more details to complete your tenant registration and start using your tenant portal.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Property Information (if showDetails is true) -->
                    @if(isset($showDetails) && $showDetails && $invitation && $invitation->property)
                        <div class="card border-0 property-details-card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-building text-primary me-2"></i>
                                    Property Details
                                </h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-2"><strong>Property:</strong><br>{{ $invitation->property->property_name ?? 'N/A' }}</p>
                                        <p class="mb-2"><strong>Address:</strong><br>{{ $invitation->property->street_name ?? '' }}, {{ $invitation->property->zone ?? '' }}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-2"><strong>Landlord:</strong><br>{{ $invitation->property->landlord->name ?? 'N/A' }}</p>
                                        <p class="mb-0"><strong>Registration Pattern:</strong><br>{{ $invitation->property->registration_pattern ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Check if invitation exists -->
                    @if($invitation)
                        <!-- Registration Form -->
                        <form action="{{ route('tenant.invitation.process-acceptance', $invitation->token) }}" method="POST">
                            @csrf

                            <div class="row">
                                <!-- Name -->
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                           id="name" name="name" value="{{ old('name', $invitation->user->name ?? '') }}" 
                                           placeholder="Enter your full name" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Email -->
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                           id="email" name="email" value="{{ old('email', $invitation->user->email ?? '') }}" 
                                           placeholder="Enter your email" required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Phone -->
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control @error('phone') is-invalid @enderror" 
                                           id="phone" name="phone" value="{{ old('phone', $invitation->user->phone ?? '') }}" 
                                           placeholder="Enter your phone number" required>
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Gender -->
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">Gender *</label>
                                    <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="male" {{ old('gender', $invitation->user->gender ?? '') == 'male' ? 'selected' : '' }}>Male</option>
                                        <option value="female" {{ old('gender', $invitation->user->gender ?? '') == 'female' ? 'selected' : '' }}>Female</option>
                                        <option value="other" {{ old('gender', $invitation->user->gender ?? '') == 'other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                    @error('gender')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Password -->
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                           id="password" name="password" placeholder="Create a password" required>
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Minimum 8 characters</small>
                                    <div id="password-strength" class="mt-2"></div>
                                </div>

                                <!-- Confirm Password -->
                                <div class="col-md-6 mb-3">
                                    <label for="password_confirmation" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" 
                                           id="password_confirmation" name="password_confirmation" 
                                           placeholder="Confirm your password" required>
                                </div>

                                <!-- Terms Agreement -->
                                <div class="col-12 mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input @error('agree_terms') is-invalid @enderror" 
                                               type="checkbox" id="agree_terms" name="agree_terms" value="1" 
                                               {{ old('agree_terms') ? 'checked' : '' }} required>
                                        <label class="form-check-label" for="agree_terms">
                                            I agree to the <a href="#" target="_blank">Terms of Service</a> 
                                            and <a href="#" target="_blank">Privacy Policy</a> *
                                        </label>
                                        @error('agree_terms')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-check me-2"></i>
                                    Complete Registration
                                </button>
                                @php
                                    // Check if login route exists
                                    $loginRoute = null;
                                    try {
                                        $loginRoute = route('login');
                                    } catch (\Exception $e) {
                                        $loginRoute = '#';
                                    }
                                @endphp
                                <a href="{{ $loginRoute }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Already have an account? Login
                                </a>
                            </div>
                        </form>

                        <!-- Footer -->
                        <div class="text-center mt-4">
                            <p class="text-muted mb-0">
                                <small>
                                    @if($invitation->expires_at)
                                        Invitation expires: {{ $invitation->expires_at->format('M j, Y \a\t g:i A') }}
                                        @if($invitation->isExpired())
                                            <span class="text-danger">(Expired)</span>
                                        @endif
                                    @endif
                                </small>
                            </p>
                            <p class="text-muted">
                                <small>
                                    {{ $systemSettings->system_name ?? 'System' }} &copy; {{ date('Y') }}. 
                                    Having trouble? Contact support for assistance.
                                </small>
                            </p>
                        </div>
                    @else
                        <!-- Invalid Invitation -->
                        <div class="alert alert-danger text-center">
                            <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                            <h4>Invalid or Expired Invitation</h4>
                            <p class="mb-0">This invitation link is no longer valid. Please contact the property administrator for a new invitation.</p>
                        </div>
                        <div class="text-center mt-4">
                            @php
                                // Check if login route exists
                                $loginRoute = null;
                                try {
                                    $loginRoute = route('login');
                                } catch (\Exception $e) {
                                    $loginRoute = '#';
                                }
                            @endphp
                            <a href="{{ $loginRoute }}" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Go to Login
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthIndicator = document.getElementById('password-strength');
            
            if (!strengthIndicator) {
                const indicator = document.createElement('div');
                indicator.id = 'password-strength';
                indicator.className = 'mt-2';
                this.parentNode.appendChild(indicator);
            }
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            const messages = ['Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
            const colors = ['danger', 'danger', 'warning', 'info', 'success'];
            const percentages = ['0%', '25%', '50%', '75%', '100%'];
            
            strengthIndicator.innerHTML = `
                <div class="progress mb-1">
                    <div class="progress-bar bg-${colors[strength]}" style="width: ${percentages[strength]}"></div>
                </div>
                <small class="text-${colors[strength]}">
                    <i class="fas fa-shield-alt me-1"></i>${messages[strength]}
                </small>
            `;
        });

        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.startsWith('0')) {
                value = value.substring(1);
            }
            
            if (value.length <= 3) {
                e.target.value = value;
            } else if (value.length <= 6) {
                e.target.value = value.substring(0, 3) + '-' + value.substring(3);
            } else {
                e.target.value = value.substring(0, 3) + '-' + value.substring(3, 6) + '-' + value.substring(6, 9);
            }
        });

        // Form validation enhancement
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirmation').value;
            const agreeTerms = document.getElementById('agree_terms').checked;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Passwords do not match. Please confirm your password.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.querySelector('.registration-card').prepend(alertDiv);
                document.getElementById('password_confirmation').focus();
                return;
            }
            
            if (!agreeTerms) {
                e.preventDefault();
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-warning alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Please agree to the Terms of Service and Privacy Policy to continue.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.querySelector('.registration-card').prepend(alertDiv);
                document.getElementById('agree_terms').focus();
                return;
            }
            
            // Show loading state
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            submitBtn.disabled = true;
            
            // Re-enable button after 5 seconds (safety measure)
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 5000);
        });

        // Auto-focus on first field
        document.addEventListener('DOMContentLoaded', function() {
            const nameField = document.getElementById('name');
            if (nameField && !nameField.value) {
                nameField.focus();
            }
            
            // Add animation to card
            const card = document.querySelector('.registration-card');
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
            
            // Animate step indicators
            const steps = document.querySelectorAll('.step');
            steps.forEach((step, index) => {
                step.style.opacity = '0';
                step.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    step.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    step.style.opacity = '1';
                    step.style.transform = 'translateY(0)';
                }, 300 + (index * 200));
            });
        });

        // Real-time validation
        const inputs = document.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value.trim() !== '' && !this.checkValidity()) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });
        });

        // Show password toggle
        const passwordField = document.getElementById('password');
        const passwordToggle = document.createElement('span');
        passwordToggle.innerHTML = '<i class="fas fa-eye"></i>';
        passwordToggle.style.position = 'absolute';
        passwordToggle.style.right = '15px';
        passwordToggle.style.top = '50%';
        passwordToggle.style.transform = 'translateY(-50%)';
        passwordToggle.style.cursor = 'pointer';
        passwordToggle.style.color = '#6c757d';
        passwordToggle.style.zIndex = '10';
        
        const passwordContainer = passwordField.parentElement;
        passwordContainer.style.position = 'relative';
        passwordContainer.appendChild(passwordToggle);
        
        passwordToggle.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
    </script>
</body>
</html>