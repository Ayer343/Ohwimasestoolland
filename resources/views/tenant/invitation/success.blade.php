<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful - {{ $systemSettings->system_name ?? 'Property Management System' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
        }
        .success-card {
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
            margin-bottom: 30px;
        }
        .system-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        .success-icon {
            font-size: 5rem;
            color: #28a745;
            animation: successPop 0.6s ease-in-out;
        }
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .feature-icon {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        /* Success Checkmark Animation */
        .success-checkmark {
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
        }
        
        .check-icon {
            width: 80px;
            height: 80px;
            position: relative;
            border-radius: 50%;
            box-sizing: content-box;
            border: 4px solid #28a745;
        }
        
        .check-icon::before {
            top: 3px;
            left: -2px;
            width: 30px;
            transform-origin: 100% 50%;
            border-radius: 100px 0 0 100px;
        }
        
        .check-icon::after {
            top: 0;
            left: 30px;
            width: 60px;
            transform-origin: 0 50%;
            border-radius: 0 100px 100px 0;
            animation: rotate-circle 4.25s ease-in;
        }
        
        .check-icon::before, .check-icon::after {
            content: '';
            height: 100px;
            position: absolute;
            background: #ffffff;
            transform: rotate(-45deg);
        }
        
        .icon-line {
            height: 5px;
            background-color: #28a745;
            display: block;
            border-radius: 2px;
            position: absolute;
            z-index: 10;
        }
        
        .line-tip {
            top: 46px;
            left: 14px;
            width: 25px;
            transform: rotate(45deg);
            animation: icon-line-tip 0.75s;
        }
        
        .line-long {
            top: 38px;
            right: 8px;
            width: 47px;
            transform: rotate(-45deg);
            animation: icon-line-long 0.75s;
        }
        
        .icon-circle {
            top: -4px;
            left: -4px;
            z-index: 10;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            position: absolute;
            box-sizing: content-box;
            border: 4px solid rgba(40, 167, 69, 0.5);
        }
        
        .icon-fix {
            top: 8px;
            width: 5px;
            left: 26px;
            z-index: 1;
            height: 85px;
            position: absolute;
            transform: rotate(-45deg);
            background-color: #ffffff;
        }
        
        @keyframes rotate-circle {
            0% { transform: rotate(-45deg); }
            5% { transform: rotate(-45deg); }
            12% { transform: rotate(-405deg); }
            100% { transform: rotate(-405deg); }
        }
        
        @keyframes icon-line-tip {
            0% { width: 0; left: 1px; top: 19px; }
            54% { width: 0; left: 1px; top: 19px; }
            70% { width: 50px; left: -8px; top: 37px; }
            84% { width: 17px; left: 21px; top: 48px; }
            100% { width: 25px; left: 14px; top: 45px; }
        }
        
        @keyframes icon-line-long {
            0% { width: 0; right: 46px; top: 54px; }
            65% { width: 0; right: 46px; top: 54px; }
            84% { width: 55px; right: 0px; top: 35px; }
            100% { width: 47px; right: 8px; top: 38px; }
        }
        
        @keyframes successPop {
            0% { transform: scale(0); }
            70% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .card-title {
            color: #2c3e50;
            font-weight: 600;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .route-unavailable {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="success-card p-4 p-md-5">
                    <!-- Header with System Logo -->
                    <div class="text-center mb-5">
                        <div class="logo-container">
                            @if(isset($systemSettings) && $systemSettings->system_logo)
                                <img src="{{ asset('storage/' . $systemSettings->system_logo) }}" 
                                     alt="{{ $systemSettings->system_name ?? 'System Logo' }}" 
                                     class="system-logo">
                            @else
                                <div class="feature-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                            @endif
                            @if(isset($systemSettings) && $systemSettings->system_name)
                                <h1 class="system-name">{{ $systemSettings->system_name }}</h1>
                            @endif
                        </div>
                    </div>

                    <!-- Success Content -->
                    <div class="text-center py-4">
                        <!-- Success Checkmark -->
                        <div class="success-checkmark">
                            <div class="check-icon">
                                <span class="icon-line line-tip"></span>
                                <span class="icon-line line-long"></span>
                                <div class="icon-circle"></div>
                                <div class="icon-fix"></div>
                            </div>
                        </div>
                        
                        <h1 class="display-5 text-success mb-3">Registration Successful!</h1>
                        
                        <p class="lead mb-4">
                            Welcome to our tenant portal, <strong>{{ $user->name }}</strong>!<br>
                            Your account has been successfully activated.
                        </p>
                        
                        @if($invitation && $invitation->property)
                            <div class="alert alert-success mb-4 text-start">
                                <h5 class="alert-heading mb-3">
                                    <i class="fas fa-building me-2"></i>Property Information
                                </h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-2"><strong>Property:</strong><br>{{ $invitation->property->property_name }}</p>
                                        <p class="mb-2"><strong>Address:</strong><br>{{ $invitation->property->street_name }}, {{ $invitation->property->zone }}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-2"><strong>Landlord:</strong><br>{{ $invitation->property->landlord->name ?? 'N/A' }}</p>
                                        <p class="mb-0"><strong>Registration:</strong><br>{{ $invitation->property->registration_pattern ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                        
                        @if(session('success'))
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                {{ session('success') }}
                            </div>
                        @endif
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mt-4 g-4">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="feature-icon">
                                        <i class="fas fa-home"></i>
                                    </div>
                                    <h5 class="card-title">View Your Property</h5>
                                    <p class="card-text">Access property details, documents, and announcements</p>
                                    @php
                                        // Check if route exists using try-catch
                                        $propertyRoute = null;
                                        try {
                                            $propertyRoute = route('tenant.properties');
                                        } catch (\Exception $e) {
                                            try {
                                                $propertyRoute = route('tenant.properties.index');
                                            } catch (\Exception $e) {
                                                try {
                                                    $propertyRoute = route('tenant.property.index');
                                                } catch (\Exception $e) {
                                                    $propertyRoute = null;
                                                }
                                            }
                                        }
                                    @endphp
                                    @if($propertyRoute)
                                        <a href="{{ $propertyRoute }}" class="btn btn-outline-primary">
                                            <i class="fas fa-external-link-alt me-2"></i>View Property
                                        </a>
                                    @else
                                        <button class="btn btn-outline-secondary route-unavailable" disabled>
                                            <i class="fas fa-external-link-alt me-2"></i>View Property
                                        </button>
                                        <small class="text-muted d-block mt-1">Coming soon</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="feature-icon">
                                        <i class="fas fa-credit-card"></i>
                                    </div>
                                    <h5 class="card-title">Rent & Payments</h5>
                                    <p class="card-text">View rent statements and make payments online</p>
                                    @php
                                        // Check if route exists using try-catch
                                        $paymentRoute = null;
                                        try {
                                            $paymentRoute = route('tenant.payments');
                                        } catch (\Exception $e) {
                                            try {
                                                $paymentRoute = route('tenant.payments.index');
                                            } catch (\Exception $e) {
                                                try {
                                                    $paymentRoute = route('payments.index');
                                                } catch (\Exception $e) {
                                                    $paymentRoute = null;
                                                }
                                            }
                                        }
                                    @endphp
                                    @if($paymentRoute)
                                        <a href="{{ $paymentRoute }}" class="btn btn-outline-primary">
                                            <i class="fas fa-external-link-alt me-2"></i>View Payments
                                        </a>
                                    @else
                                        <button class="btn btn-outline-secondary route-unavailable" disabled>
                                            <i class="fas fa-external-link-alt me-2"></i>View Payments
                                        </button>
                                        <small class="text-muted d-block mt-1">Coming soon</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Actions -->
                    <div class="text-center mt-5 pt-4 border-top">
                        @php
                            // Check if dashboard route exists
                            $dashboardRoute = null;
                            try {
                                $dashboardRoute = route('tenant.dashboard');
                            } catch (\Exception $e) {
                                try {
                                    $dashboardRoute = route('dashboard');
                                } catch (\Exception $e) {
                                    try {
                                        $dashboardRoute = route('home');
                                    } catch (\Exception $e) {
                                        $dashboardRoute = route('login');
                                    }
                                }
                            }
                            
                            // Check if profile route exists
                            $profileRoute = null;
                            try {
                                $profileRoute = route('tenant.profile');
                            } catch (\Exception $e) {
                                try {
                                    $profileRoute = route('profile');
                                } catch (\Exception $e) {
                                    $profileRoute = null;
                                }
                            }
                        @endphp
                        
                        <a href="{{ $dashboardRoute }}" class="btn btn-primary btn-lg px-5 py-3">
                            <i class="fas fa-tachometer-alt me-2"></i>Go to Your Dashboard
                        </a>
                        
                        @if($profileRoute)
                            <a href="{{ $profileRoute }}" class="btn btn-outline-primary btn-lg px-5 py-3 ms-3">
                                <i class="fas fa-user-circle me-2"></i>Complete Your Profile
                            </a>
                        @else
                            <button class="btn btn-outline-secondary btn-lg px-5 py-3 ms-3 route-unavailable" disabled>
                                <i class="fas fa-user-circle me-2"></i>Complete Your Profile
                            </button>
                        @endif
                    </div>

                    <!-- Instructions -->
                    <div class="mt-4 text-center text-muted">
                        <p class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <strong>You have been automatically logged in.</strong>
                        </p>
                        <p class="small">
                            You can now access all tenant features. Check your email for a confirmation message.
                        </p>
                    </div>

                    <!-- Footer -->
                    <div class="text-center mt-5 pt-4 border-top">
                        <p class="text-muted mb-0">
                            <small>
                                {{ $systemSettings->system_name ?? 'Property Management System' }} &copy; {{ date('Y') }}
                            </small>
                        </p>
                        <p class="text-muted">
                            <small>
                                Need help? <a href="mailto:support@example.com" class="text-decoration-none">Contact Support</a>
                            </small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-redirect after 10 seconds if dashboard route is available
        let dashboardRoute = "{{ $dashboardRoute }}";
        let isLoginRoute = dashboardRoute.includes('login');
        
        if (!isLoginRoute && dashboardRoute && dashboardRoute !== '#') {
            setTimeout(function() {
                window.location.href = dashboardRoute;
            }, 10000); // 10 seconds

            // Show redirect countdown
            let countdown = 10;
            const countdownElement = document.createElement('div');
            countdownElement.className = 'alert alert-info mt-3';
            countdownElement.innerHTML = `
                <i class="fas fa-clock me-2"></i>
                You will be automatically redirected to your dashboard in <span id="countdown">${countdown}</span> seconds.
                <a href="javascript:void(0)" onclick="cancelRedirect()" class="ms-2 text-decoration-none">
                    <small>Cancel auto-redirect</small>
                </a>
            `;
            
            // Add countdown element after instructions
            const instructions = document.querySelector('.mt-4.text-center.text-muted');
            if (instructions) {
                instructions.parentNode.insertBefore(countdownElement, instructions.nextSibling);
                
                const countdownSpan = document.getElementById('countdown');
                const countdownInterval = setInterval(function() {
                    countdown--;
                    if (countdownSpan) countdownSpan.textContent = countdown;
                    if (countdown <= 0) {
                        clearInterval(countdownInterval);
                    }
                }, 1000);
                
                // Store the redirect timeout so we can cancel it
                window.redirectTimeout = setTimeout(function() {
                    window.location.href = dashboardRoute;
                }, 10000);
                
                // Function to cancel redirect
                window.cancelRedirect = function() {
                    if (window.redirectTimeout) {
                        clearTimeout(window.redirectTimeout);
                        countdownElement.innerHTML = '<i class="fas fa-ban me-2"></i>Auto-redirect cancelled. You can manually navigate using the buttons above.';
                        countdownElement.className = 'alert alert-warning mt-3';
                        clearInterval(countdownInterval);
                    }
                };
            }
        } else if (isLoginRoute) {
            // Show login message if redirected to login
            const messageDiv = document.createElement('div');
            messageDiv.className = 'alert alert-warning mt-3';
            messageDiv.innerHTML = `
                <i class="fas fa-info-circle me-2"></i>
                Please use the login button to access your account.
            `;
            const instructions = document.querySelector('.mt-4.text-center.text-muted');
            if (instructions) {
                instructions.parentNode.insertBefore(messageDiv, instructions.nextSibling);
            }
        }

        // Animation for cards on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 * (index + 1));
            });
        });

        // Add hover effects for available buttons
        document.querySelectorAll('.btn:not(.route-unavailable)').forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = '';
                this.style.boxShadow = '';
            });
        });
    </script>
</body>
</html>