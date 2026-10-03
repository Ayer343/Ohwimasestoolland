<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Registration Complete - {{ $settings->system_name ?? ($system_name ?? 'HSM') }}</title>
    <style>
        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            line-height: 1.6;
            color: #1a202c;
            background: #f8fafc;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 650px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        /* Header Styles */
        .header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3730a3 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="%23ffffff" opacity="0.05"><polygon points="0,0 1000,50 1000,100 0,100"/></svg>');
            background-size: cover;
        }
        
        .header-content {
            position: relative;
            z-index: 2;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
            font-weight: 400;
        }
        
        /* Content Styles */
        .content {
            padding: 40px 35px;
        }
        
        .welcome-section {
            margin-bottom: 30px;
        }
        
        .welcome-section h2 {
            color: #1e293b;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .welcome-section p {
            color: #64748b;
            font-size: 16px;
            line-height: 1.7;
        }
        
        /* Progress Steps */
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin: 35px 0;
            position: relative;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 12px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .step.active .step-circle {
            background: #3b82f6;
            border-color: #3b82f6;
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .step.completed .step-circle {
            background: #10b981;
            border-color: #10b981;
            color: white;
        }
        
        .step-label {
            font-size: 13px;
            font-weight: 600;
            color: #94a3b8;
            text-align: center;
            max-width: 100px;
        }
        
        .step.active .step-label {
            color: #3b82f6;
        }
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }
        
        .card-header .icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #3b82f6;
            color: white;
            border-radius: 6px;
            font-size: 14px;
        }
        
        /* Detail Grid */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }
        
        .detail-row {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #f8fafc;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            min-width: 150px;
            font-weight: 600;
            color: #475569;
            font-size: 14px;
        }
        
        .detail-value {
            flex: 1;
            color: #1e293b;
            font-weight: 500;
        }
        
        .highlight-value {
            color: #3b82f6;
            font-weight: 700;
        }
        
        /* Action Section */
        .action-section {
            text-align: center;
            margin: 35px 0;
            padding: 30px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        
        .action-title {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 15px;
        }
        
        .action-description {
            color: #64748b;
            margin-bottom: 25px;
            font-size: 15px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 28px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: #3b82f6;
            border: 2px solid #3b82f6;
            padding: 12px 26px;
        }
        
        .btn-secondary:hover {
            background: #f8faff;
        }
        
        /* Link Reveal Section */
        .link-reveal-section {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        
        .link-reveal-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .link-reveal-header h4 {
            font-size: 16px;
            color: #d97706;
            margin: 0;
        }
        
        .reveal-btn {
            background: #fbbf24;
            color: #92400e;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .reveal-btn:hover {
            background: #f59e0b;
        }
        
        .hidden-link {
            display: none;
            margin-top: 15px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #fcd34d;
        }
        
        .hidden-link.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        .registration-link {
            word-break: break-all;
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', monospace;
            font-size: 14px;
            color: #3b82f6;
            padding: 12px;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px dashed #cbd5e1;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .registration-link:hover {
            background: #f1f5f9;
        }
        
        /* Alert Styles */
        .alert {
            padding: 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .alert-warning {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            color: #92400e;
        }
        
        .alert-info {
            background: #eff6ff;
            border: 1px solid #dbeafe;
            color: #1e40af;
        }
        
        .alert-success {
            background: #ecfdf5;
            border: 1px solid #d1fae5;
            color: #065f46;
        }
        
        .alert-icon {
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-content h4 {
            margin: 0 0 8px 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .alert-content p {
            margin: 0;
            font-size: 14px;
        }
        
        /* Next Steps */
        .next-steps {
            margin: 30px 0;
        }
        
        .next-steps h3 {
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .steps-list {
            display: grid;
            gap: 16px;
        }
        
        .step-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        
        .step-number {
            width: 28px;
            height: 28px;
            background: #3b82f6;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .step-content {
            flex: 1;
        }
        
        .step-content h4 {
            font-size: 15px;
            margin: 0 0 6px 0;
            color: #1e293b;
            font-weight: 600;
        }
        
        .step-content p {
            font-size: 14px;
            color: #64748b;
            margin: 0;
        }
        
        /* Footer */
        .footer {
            background: #0f172a;
            color: #94a3b8;
            padding: 30px;
            text-align: center;
        }
        
        .footer p {
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        
        .footer-logo {
            font-size: 18px;
            font-weight: 700;
            color: white;
            margin-bottom: 15px;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 15px 0;
        }
        
        .footer-link {
            color: #94a3b8;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s ease;
        }
        
        .footer-link:hover {
            color: white;
        }
        
        .copyright {
            font-size: 12px;
            color: #64748b;
            margin-top: 20px;
            line-height: 1.6;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive Styles */
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }
            
            .container {
                border-radius: 10px;
            }
            
            .header {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .content {
                padding: 25px 20px;
            }
            
            .progress-steps {
                margin: 25px 0;
            }
            
            .step-circle {
                width: 36px;
                height: 36px;
            }
            
            .step-label {
                font-size: 11px;
                max-width: 70px;
            }
            
            .detail-row {
                flex-direction: column;
                gap: 5px;
                padding: 10px 0;
            }
            
            .detail-label {
                min-width: auto;
            }
            
            .action-section {
                padding: 20px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .footer-links {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <h1>{{ $settings->system_name ?? ($system_name ?? 'HSM Property Management') }}</h1>
                <p>{{ $settings->system_short_name ?? 'HSM' }} Property Registration Portal</p>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="content">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <h2>Property Registration Complete</h2>
                <p>Welcome to {{ $settings->system_name ?? ($system_name ?? 'HSM Property System') }}, {{ $landlord->name ?? 'Valued Landlord' }}! Your property has been successfully registered in our system.</p>
            </div>
            
            <!-- Progress Steps -->
            <div class="progress-steps">
                <div class="step active">
                    <div class="step-circle">1</div>
                    <div class="step-label">Property Registered</div>
                </div>
                <div class="step">
                    <div class="step-circle">2</div>
                    <div class="step-label">Complete Registration</div>
                </div>
                <div class="step">
                    <div class="step-circle">3</div>
                    <div class="step-label">Access Portal</div>
                </div>
            </div>
            
            <!-- Property Details Card -->
            <div class="card">
                <div class="card-header">
                    <div class="icon">🏠</div>
                    <h3>Property Registration Details</h3>
                </div>
                <div class="detail-grid">
                    <div class="detail-row">
                        <div class="detail-label">Property Name:</div>
                        <div class="detail-value">{{ $property->property_name ?? 'N/A' }}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Registration ID:</div>
                        <div class="detail-value highlight-value">{{ $property->registration_pattern ?? 'N/A' }}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Location:</div>
                        <div class="detail-value">
                            {{ $property->street_name ?? 'N/A' }}, 
                            {{ $property->zone ?? 'N/A' }}
                            @if($property->section)
                                , Section {{ $property->section }}
                            @endif
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Property Type:</div>
                        <div class="detail-value">
                            {{ $property->propertyType->name ?? ($property->custom_property_type ?? 'N/A') }}
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Registration Date:</div>
                        <div class="detail-value">
                            {{ $property->registration_date ? $property->registration_date->format('F j, Y') : 'N/A' }}
                        </div>
                    </div>
                    @if($property->digital_address)
                    <div class="detail-row">
                        <div class="detail-label">Digital Address:</div>
                        <div class="detail-value">{{ $property->digital_address }}</div>
                    </div>
                    @endif
                    @if($property->house_number)
                    <div class="detail-row">
                        <div class="detail-label">House Number:</div>
                        <div class="detail-value">{{ $property->house_number }}</div>
                    </div>
                    @endif
                </div>
            </div>
            
            <!-- Registration Plan Info -->
            @if($property->registrationPlan)
            <div class="alert alert-info">
                <div class="alert-icon">📊</div>
                <div class="alert-content">
                    <h4>Registration Plan Information</h4>
                    <p>
                        Zone: {{ $property->registrationPlan->zone }}
                        @if($property->registrationPlan->section)
                            | Section: {{ $property->registrationPlan->section }}
                        @endif
                        @if($property->registrationPlan->is_global_sequence)
                            | Global Sequence
                            @if($property->registrationPlan->continues_from_plan_id)
                                (Continues from previous plan)
                            @endif
                        @endif
                    </p>
                </div>
            </div>
            @endif
            
            <!-- Agent Information -->
            @if($property->registeredBy && $property->registeredBy->isFieldAgent())
            <div class="alert alert-success">
                <div class="alert-icon">👤</div>
                <div class="alert-content">
                    <h4>Registered By Field Agent</h4>
                    <p>Your property was registered by: <strong>{{ $property->registeredBy->name }}</strong></p>
                </div>
            </div>
            @endif
            
            <!-- Action Required Alert -->
            <div class="alert alert-warning">
                <div class="alert-icon">🚀</div>
                <div class="alert-content">
                    <h4>Action Required: Complete Your Registration</h4>
                    <p>To access your landlord portal and manage your property, you need to complete your registration by setting up your password.</p>
                </div>
            </div>
            
            <!-- Main Action Section -->
            <div class="action-section">
                <h3 class="action-title">Complete Your Registration</h3>
                <p class="action-description">Click the button below to set up your password and access your landlord dashboard.</p>
                
                @if(isset($invitation) && $invitation->token)
                    <!-- Token-based registration URL -->
                    <a href="{{ route('landlord.invitations.accept', ['token' => $invitation->token]) }}" class="btn btn-primary">
                        Complete Registration & Set Password
                    </a>
                @elseif(isset($registrationUrl))
                    <!-- Fallback to old registration URL -->
                    <a href="{{ $registrationUrl }}" class="btn btn-primary">
                        Complete Registration & Set Password
                    </a>
                @else
                    <!-- Fallback if no URL is available -->
                    <div class="alert alert-warning">
                        <div class="alert-icon">❌</div>
                        <div class="alert-content">
                            <h4>Registration Link Not Available</h4>
                            <p>Please contact our support team to complete your registration.</p>
                        </div>
                    </div>
                @endif
            </div>
            
            <!-- Link Reveal Section -->
            <div class="link-reveal-section">
                <div class="link-reveal-header">
                    <h4>Alternative Access Method</h4>
                </div>
                <p style="margin-bottom: 15px; color: #92400e;">If the button doesn't work, click below to reveal your secure registration link:</p>
                
                <button class="reveal-btn" id="revealLinkBtn">
                    Reveal Registration Link
                </button>
                
                <div class="hidden-link" id="hiddenLink">
                    <p style="margin-bottom: 10px; font-size: 14px; color: #92400e;">Copy and paste this link into your browser:</p>
                    <div class="registration-link" id="registrationLink" onclick="copyToClipboard(this)">
                        @if(isset($invitation) && $invitation->token)
                            {{ route('landlord.invitations.accept', ['token' => $invitation->token]) }}
                        @elseif(isset($registrationUrl))
                            {{ $registrationUrl }}
                        @else
                            Registration link not available - please contact support
                        @endif
                    </div>
                    <p style="margin-top: 10px; font-size: 12px; color: #92400e;">Click the link above to copy it to your clipboard</p>
                </div>
            </div>
            
            <!-- Security Notice -->
            <div class="alert alert-warning">
                <div class="alert-icon">🔒</div>
                <div class="alert-content">
                    <h4>Security Notice</h4>
                    <p>
                        This registration link contains a secure token and will expire in <strong>7 days</strong> for your protection. 
                        Please complete your registration before <strong>{{ now()->addDays(7)->format('F j, Y') }}</strong>.
                        @if(isset($invitation) && $invitation->expires_at)
                        <br><small>Your invitation expires on: <strong>{{ $invitation->expires_at->format('F j, Y \a\t g:i A') }}</strong></small>
                        @endif
                    </p>
                </div>
            </div>
            
            <!-- Next Steps -->
            <div class="next-steps">
                <h3>What Happens Next?</h3>
                <div class="steps-list">
                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h4>Set Your Password</h4>
                            <p>Click the registration link to create your secure password</p>
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h4>Access Your Dashboard</h4>
                            <p>Log in to your personalized landlord portal</p>
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h4>Manage Your Property</h4>
                            <p>View property details, update information, and track activities</p>
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <h4>Add More Properties</h4>
                            <p>Register additional properties from your dashboard</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Support Contact -->
            <div class="alert alert-info">
                <div class="alert-icon">🆘</div>
                <div class="alert-content">
                    <h4>Need Help?</h4>
                    <p>
                        If you encounter any issues with the registration link or have questions about your property registration, 
                        please contact our support team immediately.
                        @if(isset($invitation) && $invitation->id)
                        <br><small><strong>Reference:</strong> Invitation #{{ $invitation->id }}</small>
                        @endif
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div class="footer-logo">{{ $settings->system_name ?? ($system_name ?? 'HSM Property Management') }}</div>
            <p>{{ $settings->system_short_name ?? 'HSM' }} Property Registration System</p>
            
            <div class="footer-links">
                <a href="#" class="footer-link">Support Center</a>
                <a href="#" class="footer-link">Privacy Policy</a>
                <a href="#" class="footer-link">Terms of Service</a>
            </div>
            
            <p class="copyright">
                This is an automated message from our property registration system.<br>
                Please do not reply to this email. For assistance, contact our support team.<br><br>
                &copy; {{ date('Y') }} {{ $settings->system_name ?? ($system_name ?? 'HSM Property System') }}. All rights reserved.
            </p>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Link reveal functionality
            const revealBtn = document.getElementById('revealLinkBtn');
            const hiddenLink = document.getElementById('hiddenLink');
            
            if (revealBtn && hiddenLink) {
                revealBtn.addEventListener('click', function() {
                    hiddenLink.classList.toggle('show');
                    
                    if (hiddenLink.classList.contains('show')) {
                        this.innerHTML = 'Hide Registration Link';
                    } else {
                        this.innerHTML = 'Reveal Registration Link';
                    }
                });
            }
            
            // Add click tracking for the main button
            const mainButton = document.querySelector('.btn-primary');
            if (mainButton) {
                mainButton.addEventListener('click', function(e) {
                    console.log('Registration completion button clicked - Token: {{ $invitation->token ?? "N/A" }}');
                    
                    // Add visual feedback
                    this.style.opacity = '0.8';
                    this.style.transform = 'translateY(0)';
                    
                    setTimeout(() => {
                        this.style.opacity = '1';
                    }, 300);
                });
            }
        });
        
        // Enhanced copy functionality
        function copyToClipboard(element) {
            const text = element.textContent;
            
            // Create a temporary textarea to copy from
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            
            try {
                const successful = document.execCommand('copy');
                document.body.removeChild(textarea);
                
                if (successful) {
                    // Show success feedback
                    const originalText = element.textContent;
                    const originalBackground = element.style.background;
                    
                    element.textContent = 'Link copied to clipboard!';
                    element.style.background = '#d1fae5';
                    element.style.color = '#065f46';
                    element.style.borderColor = '#10b981';
                    
                    setTimeout(() => {
                        element.textContent = originalText;
                        element.style.background = originalBackground;
                        element.style.color = '#3b82f6';
                        element.style.borderColor = '#cbd5e1';
                    }, 2000);
                }
            } catch (err) {
                document.body.removeChild(textarea);
                console.error('Failed to copy text: ', err);
                
                // Fallback feedback
                const originalText = element.textContent;
                element.textContent = 'Copy failed - please select and copy manually';
                element.style.background = '#fee2e2';
                element.style.color = '#dc2626';
                element.style.borderColor = '#fca5a5';
                
                setTimeout(() => {
                    element.textContent = originalText;
                    element.style.background = '#f8fafc';
                    element.style.color = '#3b82f6';
                    element.style.borderColor = '#cbd5e1';
                }, 3000);
            }
        }
    </script>
</body>
</html>