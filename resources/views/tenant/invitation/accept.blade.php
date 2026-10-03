<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept Tenant Invitation - {{ $systemSettings->system_name ?? 'Property Management System' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #10b981;
            --danger: #ef4444;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 0.5rem 0;
        }
        
        .invitation-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 100%;
        }
        
        .invitation-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 1rem;
            text-align: center;
        }
        
        .invitation-body {
            padding: 0.75rem 1rem;
        }
        
        .tenant-icon {
            font-size: 1.25rem;
            background: rgba(255, 255, 255, 0.2);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.25rem;
        }
        
        .invitation-header h5 {
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        
        .invitation-header p {
            font-size: 0.7rem;
            margin-bottom: 0;
            opacity: 0.8;
        }
        
        /* Ultra Compact Step Indicators */
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            position: relative;
            padding: 0 0.25rem;
        }
        
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 12px;
            left: 10%;
            right: 10%;
            height: 2px;
            background: #e2e8f0;
            z-index: 0;
        }
        
        .step-item {
            position: relative;
            z-index: 1;
            text-align: center;
            flex: 1;
        }
        
        .step-circle {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2px;
            font-weight: 600;
            font-size: 0.6rem;
            color: #64748b;
            transition: all 0.3s ease;
        }
        
        .step-item.active .step-circle {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-color: transparent;
            color: white;
        }
        
        .step-item.completed .step-circle {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }
        
        .step-item.completed .step-circle i {
            font-size: 0.6rem;
        }
        
        .step-label {
            font-size: 0.55rem;
            color: #94a3b8;
            font-weight: 500;
            display: none;
        }
        
        .step-item.active .step-label {
            color: var(--primary);
            font-weight: 600;
            display: block;
        }
        
        .step-item.completed .step-label {
            display: block;
            color: var(--success);
        }
        
        /* Ultra Compact Sections */
        .section-card {
            background: #f8fafc;
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            margin-bottom: 0.5rem;
            border: 1px solid #e2e8f0;
        }
        
        .section-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        
        .section-title i {
            color: var(--primary);
            font-size: 0.7rem;
        }
        
        /* Ultra Compact Property Grid */
        .property-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.1rem 0.75rem;
        }
        
        .property-item {
            display: flex;
            align-items: baseline;
            font-size: 0.7rem;
            padding: 0.05rem 0;
        }
        
        .property-item .label {
            color: #94a3b8;
            font-weight: 500;
            width: 60px;
            flex-shrink: 0;
            font-size: 0.6rem;
        }
        
        .property-item .value {
            color: #1e293b;
            font-weight: 500;
            font-size: 0.7rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Ultra Compact Form */
        .form-control, .form-select {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            height: auto;
            min-height: 30px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.15);
        }
        
        .form-label {
            font-size: 0.65rem;
            font-weight: 500;
            color: #475569;
            margin-bottom: 0.1rem;
        }
        
        .form-check {
            padding-left: 1.5rem;
            margin-bottom: 0.1rem;
        }
        
        .form-check-input {
            width: 0.85rem;
            height: 0.85rem;
            margin-top: 0.15rem;
            margin-left: -1.5rem;
        }
        
        .form-check-label {
            font-size: 0.7rem;
        }
        
        .form-text {
            font-size: 0.6rem !important;
        }
        
        .progress {
            height: 2px;
            border-radius: 1px;
            margin-top: 0.15rem;
        }
        
        .row.g-2 {
            --bs-gutter-y: 0.25rem;
            --bs-gutter-x: 0.5rem;
        }
        
        /* Buttons */
        .btn {
            font-size: 0.75rem;
            padding: 0.3rem 0.75rem;
            border-radius: 6px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            transform: translateY(-1px);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-outline-secondary {
            border: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 0.7rem;
            padding: 0.2rem 0.6rem;
        }
        
        .btn-outline-secondary:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        
        /* Password requirements - ultra compact */
        .password-reqs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.05rem 0.5rem;
            padding-left: 0.25rem;
            margin-top: 0.15rem;
        }
        
        .password-reqs .req {
            font-size: 0.6rem;
            padding: 0.05rem 0;
        }
        
        .password-reqs .req.text-success {
            color: var(--success) !important;
        }
        
        .password-reqs .req.text-muted {
            color: #94a3b8 !important;
        }
        
        details summary {
            font-size: 0.65rem;
            cursor: pointer;
            color: #94a3b8;
        }
        
        details summary:hover {
            color: #64748b;
        }
        
        /* Alert */
        .alert {
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        
        .alert h6 {
            font-size: 0.85rem;
            margin-bottom: 0.15rem;
        }
        
        /* Footer */
        .footer-text {
            font-size: 0.6rem;
            color: #94a3b8;
        }
        
        .footer-text a {
            font-size: 0.6rem;
        }
        
        /* Responsive */
        @media (max-width: 576px) {
            .invitation-body {
                padding: 0.5rem 0.75rem;
            }
            .section-card {
                padding: 0.35rem 0.5rem;
            }
            .property-grid {
                grid-template-columns: 1fr;
                gap: 0.05rem;
            }
            .property-item .label {
                width: 55px;
            }
            .password-reqs {
                grid-template-columns: 1fr;
            }
            .step-label {
                font-size: 0.5rem;
            }
            .step-circle {
                width: 20px;
                height: 20px;
                font-size: 0.5rem;
            }
            .step-indicator::before {
                top: 10px;
            }
            .btn {
                font-size: 0.7rem;
                padding: 0.25rem 0.6rem;
            }
        }
        
        @media (max-width: 400px) {
            .invitation-header {
                padding: 0.5rem;
            }
            .tenant-icon {
                width: 28px;
                height: 28px;
                font-size: 1rem;
            }
            .invitation-header h5 {
                font-size: 0.8rem;
            }
            .form-control, .form-select {
                font-size: 0.7rem;
                padding: 0.2rem 0.4rem;
                min-height: 26px;
            }
            .section-title {
                font-size: 0.65rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-7 col-11">
                <div class="invitation-card">
                    <!-- Header - Ultra Compact -->
                    <div class="invitation-header">
                        <div class="tenant-icon">
                            @if(isset($systemSettings) && $systemSettings->system_logo)
                                <img src="{{ asset('storage/' . $systemSettings->system_logo) }}" 
                                     alt="{{ $systemSettings->system_name ?? 'Logo' }}" 
                                     style="width: 22px; height: auto;">
                            @else
                                <i class="fas fa-user-friends"></i>
                            @endif
                        </div>
                        <h5>{{ $systemSettings->system_name ?? 'Property Management' }}</h5>
                        <p>Tenant Registration</p>
                    </div>

                    <div class="invitation-body">
                        @if($invitation)
                            <!-- Step Indicators - Ultra Compact -->
                            <div class="step-indicator">
                                <div class="step-item completed">
                                    <div class="step-circle"><i class="fas fa-check"></i></div>
                                    <div class="step-label">Property</div>
                                </div>
                                <div class="step-item active">
                                    <div class="step-circle">2</div>
                                    <div class="step-label">Your Info</div>
                                </div>
                                <div class="step-item">
                                    <div class="step-circle">3</div>
                                    <div class="step-label">Security</div>
                                </div>
                                <div class="step-item">
                                    <div class="step-circle">4</div>
                                    <div class="step-label">Confirm</div>
                                </div>
                            </div>

                            <!-- Property Details - Ultra Compact -->
                            <div class="section-card">
                                <div class="section-title">
                                    <i class="fas fa-building"></i>
                                    <span>Property</span>
                                </div>
                                <div class="property-grid">
                                    <div class="property-item">
                                        <span class="label">Name:</span>
                                        <span class="value">{{ $invitation->property->property_name ?? 'N/A' }}</span>
                                    </div>
                                    <div class="property-item">
                                        <span class="label">Zone:</span>
                                        <span class="value">{{ $invitation->property->zone ?? 'N/A' }}</span>
                                    </div>
                                    <div class="property-item">
                                        <span class="label">Pattern:</span>
                                        <span class="value font-monospace">{{ $invitation->property->registration_pattern ?? 'N/A' }}</span>
                                    </div>
                                    <div class="property-item">
                                        <span class="label">Landlord:</span>
                                        <span class="value">{{ $invitation->property->landlord->name ?? 'N/A' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Registration Form - Ultra Compact -->
                            <form action="{{ route('tenant.invitation.process-acceptance', $invitation->token) }}" method="POST" id="registrationForm">
                                @csrf

                                <!-- Personal Details -->
                                <div class="section-card">
                                    <div class="section-title">
                                        <i class="fas fa-user"></i>
                                        <span>Personal Details</span>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <label for="name" class="form-label">Full Name *</label>
                                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                                   id="name" name="name" value="{{ old('name', $invitation->user->name ?? '') }}" 
                                                   placeholder="Full name" required>
                                            @error('name')
                                                <div class="invalid-feedback" style="font-size: 0.6rem;">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email *</label>
                                            <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                                   id="email" name="email" value="{{ old('email', $invitation->user->email ?? '') }}" 
                                                   placeholder="Email" required>
                                            @error('email')
                                                <div class="invalid-feedback" style="font-size: 0.6rem;">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="phone" class="form-label">Phone *</label>
                                            <input type="tel" class="form-control @error('phone') is-invalid @enderror" 
                                                   id="phone" name="phone" value="{{ old('phone', $invitation->user->phone ?? '') }}" 
                                                   placeholder="Phone" required>
                                            @error('phone')
                                                <div class="invalid-feedback" style="font-size: 0.6rem;">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        
                                        <div class="col-12">
                                            <label for="gender" class="form-label">Gender *</label>
                                            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required>
                                                <option value="">Select</option>
                                                <option value="male" {{ old('gender', $invitation->user->gender ?? '') == 'male' ? 'selected' : '' }}>Male</option>
                                                <option value="female" {{ old('gender', $invitation->user->gender ?? '') == 'female' ? 'selected' : '' }}>Female</option>
                                                <option value="other" {{ old('gender', $invitation->user->gender ?? '') == 'other' ? 'selected' : '' }}>Other</option>
                                            </select>
                                            @error('gender')
                                                <div class="invalid-feedback" style="font-size: 0.6rem;">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Password Setup - Ultra Compact -->
                                <div class="section-card">
                                    <div class="section-title">
                                        <i class="fas fa-lock"></i>
                                        <span>Password</span>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label for="password" class="form-label">Password *</label>
                                            <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                                   id="password" name="password" placeholder="Create" required>
                                            @error('password')
                                                <div class="invalid-feedback" style="font-size: 0.6rem;">{{ $message }}</div>
                                            @enderror
                                            <div class="mt-1">
                                                <div class="progress">
                                                    <div id="strength-bar" class="progress-bar" style="width: 0%"></div>
                                                </div>
                                                <small id="strength-text" class="form-text text-muted">Min 8 chars</small>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="password_confirmation" class="form-label">Confirm *</label>
                                            <input type="password" class="form-control" 
                                                   id="password_confirmation" name="password_confirmation" 
                                                   placeholder="Confirm" required>
                                            <small id="match-message" class="form-text"></small>
                                        </div>
                                    </div>
                                    
                                    <!-- Password Requirements - Ultra Compact -->
                                    <div class="mt-1">
                                        <details>
                                            <summary>Password requirements</summary>
                                            <div class="password-reqs">
                                                <div id="req-length" class="req text-muted">○ 8+ characters</div>
                                                <div id="req-uppercase" class="req text-muted">○ Uppercase letter</div>
                                                <div id="req-lowercase" class="req text-muted">○ Lowercase letter</div>
                                                <div id="req-number" class="req text-muted">○ One number</div>
                                                <div id="req-special" class="req text-muted">○ Special character</div>
                                            </div>
                                        </details>
                                    </div>
                                </div>

                                <!-- Terms & Submit - Ultra Compact -->
                                <div class="section-card">
                                    <div class="form-check">
                                        <input class="form-check-input @error('agree_terms') is-invalid @enderror" 
                                               type="checkbox" id="agree_terms" name="agree_terms" value="1" 
                                               {{ old('agree_terms') ? 'checked' : '' }} required>
                                        <label class="form-check-label" for="agree_terms">
                                            I agree to <a href="{{ route('terms') }}" target="_blank" class="text-primary">Terms</a> &amp; <a href="{{ route('privacy') }}" target="_blank" class="text-primary">Privacy</a> *
                                        </label>
                                        @error('agree_terms')
                                            <div class="invalid-feedback d-block" style="font-size: 0.6rem;">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    
                                    <div class="d-grid gap-1 mt-1">
                                        <button type="submit" class="btn btn-primary" id="submitBtn">
                                            <i class="fas fa-user-check me-1"></i>
                                            Complete Registration
                                            <span class="spinner-border spinner-border-sm d-none ms-1" id="submitSpinner"></span>
                                        </button>
                                        <a href="{{ route('login') }}" class="btn btn-outline-secondary">
                                            <i class="fas fa-sign-in-alt me-1"></i> Login
                                        </a>
                                    </div>
                                </div>
                            </form>

                            <!-- Footer - Ultra Compact -->
                            <div class="text-center mt-2 pt-1 border-top">
                                <p class="footer-text mb-1">
                                    <i class="far fa-clock me-1"></i>
                                    Expires: <strong>{{ $invitation->expires_at->format('M j, Y g:i A') }}</strong>
                                    @if($invitation->isExpired())
                                        <span class="text-danger ms-1">(Expired)</span>
                                    @endif
                                </p>
                                
                                @if(isset($systemSettings) && ($systemSettings->system_email || $systemSettings->system_phone))
                                <div class="footer-text">
                                    <span>Need help? </span>
                                    @if($systemSettings->system_email)
                                    <a href="mailto:{{ $systemSettings->system_email }}" class="text-decoration-none">
                                        <i class="fas fa-envelope text-primary me-1"></i>{{ $systemSettings->system_email }}
                                    </a>
                                    @endif
                                    @if($systemSettings->system_phone)
                                    <span class="mx-1">|</span>
                                    <a href="tel:{{ $systemSettings->system_phone }}" class="text-decoration-none">
                                        <i class="fas fa-phone text-success me-1"></i>{{ $systemSettings->system_phone }}
                                    </a>
                                    @endif
                                </div>
                                @endif
                                
                                <p class="footer-text mt-1 mb-0">
                                    {{ $systemSettings->system_name ?? 'System' }} &copy; {{ date('Y') }}
                                </p>
                            </div>
                        @else
                            <!-- Invalid Invitation - Compact -->
                            <div class="text-center py-2">
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle fa-2x d-block mb-1"></i>
                                    <h6>Invalid Invitation</h6>
                                    <p class="mb-0">This link is no longer valid. Please contact the property administrator.</p>
                                </div>
                                @if(isset($systemSettings) && $systemSettings->system_email)
                                <p class="footer-text">
                                    Contact: <a href="mailto:{{ $systemSettings->system_email }}" class="text-primary">{{ $systemSettings->system_email }}</a>
                                    @if($systemSettings->system_phone)
                                    or <a href="tel:{{ $systemSettings->system_phone }}" class="text-success">{{ $systemSettings->system_phone }}</a>
                                    @endif
                                </p>
                                @endif
                                <a href="{{ route('login') }}" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-1"></i> Login
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
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
            
            const strengthBar = document.getElementById('strength-bar');
            const colors = ['#ef4444', '#ef4444', '#f59e0b', '#3b82f6', '#10b981'];
            const messages = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
            
            if (strengthBar) {
                strengthBar.style.width = `${(strength / 5) * 100}%`;
                strengthBar.style.backgroundColor = colors[strength] || '#3b82f6';
            }
            
            const strengthText = document.getElementById('strength-text');
            if (strengthText) {
                if (password.length > 0) {
                    strengthText.innerHTML = `<i class="fas fa-shield-alt me-1"></i>${messages[strength]}`;
                    strengthText.className = `form-text ${strength >= 3 ? 'text-success' : strength >= 2 ? 'text-warning' : 'text-danger'}`;
                } else {
                    strengthText.innerHTML = 'Min 8 chars';
                    strengthText.className = 'form-text text-muted';
                }
            }
            
            updateRequirementIndicators();
            validateForm();
        }
        
        function updateRequirementIndicators() {
            const reqMap = ['length', 'uppercase', 'lowercase', 'number', 'special'];
            const labels = ['8+ characters', 'Uppercase letter', 'Lowercase letter', 'One number', 'Special character'];
            
            reqMap.forEach((key, index) => {
                const element = document.getElementById(`req-${key}`);
                if (element) {
                    const met = requirements[key];
                    element.textContent = met ? `✓ ${labels[index]}` : `○ ${labels[index]}`;
                    element.className = `req ${met ? 'text-success' : 'text-muted'}`;
                }
            });
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password')?.value || '';
            const confirm = document.getElementById('password_confirmation')?.value || '';
            const matchMsg = document.getElementById('match-message');
            
            if (matchMsg) {
                if (confirm && password === confirm) {
                    matchMsg.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i>Passwords match';
                    matchMsg.className = 'form-text text-success';
                } else if (confirm) {
                    matchMsg.innerHTML = '<i class="fas fa-times-circle text-danger me-1"></i>Passwords do not match';
                    matchMsg.className = 'form-text text-danger';
                } else {
                    matchMsg.innerHTML = '';
                    matchMsg.className = 'form-text';
                }
            }
            
            validateForm();
        }
        
        function validateForm() {
            const name = document.getElementById('name')?.value.trim() || '';
            const email = document.getElementById('email')?.value.trim() || '';
            const phone = document.getElementById('phone')?.value.trim() || '';
            const gender = document.getElementById('gender')?.value || '';
            const password = document.getElementById('password')?.value || '';
            const confirm = document.getElementById('password_confirmation')?.value || '';
            const termsAccepted = document.getElementById('agree_terms')?.checked || false;
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            const isValid = name.length >= 2 && 
                           emailRegex.test(email) && 
                           phone.replace(/[^0-9]/g, '').length >= 9 && 
                           gender !== '' &&
                           Object.values(requirements).every(Boolean) &&
                           password === confirm && 
                           password.length > 0 &&
                           termsAccepted;
            
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.disabled = !isValid;
            }
        }
        
        // Phone number formatting
        document.getElementById('phone')?.addEventListener('input', function(e) {
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
        
        // Form submission
        document.getElementById('registrationForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password')?.value || '';
            const confirm = document.getElementById('password_confirmation')?.value || '';
            
            if (password !== confirm) {
                e.preventDefault();
                const matchMsg = document.getElementById('match-message');
                if (matchMsg) {
                    matchMsg.innerHTML = '<i class="fas fa-times-circle text-danger me-1"></i>Passwords do not match';
                    matchMsg.className = 'form-text text-danger';
                }
                return;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            const spinner = document.getElementById('submitSpinner');
            if (submitBtn && spinner) {
                submitBtn.disabled = true;
                spinner.classList.remove('d-none');
            }
        });
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('name');
            const emailInput = document.getElementById('email');
            const phoneInput = document.getElementById('phone');
            const genderSelect = document.getElementById('gender');
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('password_confirmation');
            const termsCheckbox = document.getElementById('agree_terms');
            
            if (nameInput) nameInput.addEventListener('input', validateForm);
            if (emailInput) emailInput.addEventListener('input', validateForm);
            if (phoneInput) phoneInput.addEventListener('input', validateForm);
            if (genderSelect) genderSelect.addEventListener('change', validateForm);
            if (passwordInput) passwordInput.addEventListener('input', function(e) {
                checkPasswordStrength(e.target.value);
            });
            if (confirmInput) confirmInput.addEventListener('input', checkPasswordMatch);
            if (termsCheckbox) termsCheckbox.addEventListener('change', validateForm);
            
            if (passwordInput) checkPasswordStrength('');
            validateForm();
            
            setTimeout(() => {
                const nameField = document.getElementById('name');
                if (nameField && !nameField.value) nameField.focus();
            }, 100);
        });
    </script>
</body>
</html>