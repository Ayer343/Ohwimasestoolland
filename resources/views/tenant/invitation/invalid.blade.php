<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invalid Invitation - {{ $systemSettings->system_name ?? 'Property Management System' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 10px 0;
        }
        .error-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin: 10px auto;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            transition: all 0.3s ease;
            font-size: 0.8rem;
            padding: 0.4rem 1rem;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .btn-outline-primary {
            font-size: 0.8rem;
            padding: 0.4rem 1rem;
        }
        .btn-outline-secondary {
            font-size: 0.8rem;
            padding: 0.4rem 1rem;
        }
        .system-logo {
            max-height: 35px;
            max-width: 120px;
            object-fit: contain;
        }
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .system-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        .error-icon {
            font-size: 2.5rem;
            color: #dc3545;
            animation: shake 0.5s ease-in-out;
        }
        .error-icon i.fa-ban {
            font-size: 2.5rem;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }
        .alert {
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }
        .alert h5, .alert h6 {
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }
        .alert p, .alert ul {
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
        }
        .reason-list {
            text-align: left;
            margin: 0;
            padding-left: 1.2rem;
        }
        .reason-list li {
            margin-bottom: 3px;
            color: #6c757d;
            font-size: 0.8rem;
        }
        .reason-list li:last-child {
            margin-bottom: 0;
        }
        .card-title {
            color: #2c3e50;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        .error-header {
            border-bottom: 1px solid #f8f9fa;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .action-buttons .btn {
            min-width: 140px;
            font-size: 0.75rem;
            padding: 0.35rem 0.8rem;
        }
        .text-muted small {
            font-size: 0.7rem;
        }
        .error-card .p-4 {
            padding: 1.25rem !important;
        }
        .error-card .p-md-5 {
            padding: 1.5rem !important;
        }
        .mb-4 {
            margin-bottom: 0.75rem !important;
        }
        .mb-3 {
            margin-bottom: 0.5rem !important;
        }
        .mt-4 {
            margin-top: 0.75rem !important;
        }
        .mt-5 {
            margin-top: 1rem !important;
        }
        .py-3 {
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
        }
        .pt-3 {
            padding-top: 0.5rem !important;
        }
        .pt-4 {
            padding-top: 0.75rem !important;
        }
        .pb-2 {
            padding-bottom: 0.25rem !important;
        }
        .gap-2 {
            gap: 0.5rem !important;
        }
        .error-card .display-5 {
            font-size: 1.5rem;
        }
        .error-card .lead {
            font-size: 0.95rem;
        }
        .error-card h2 {
            font-size: 1.2rem;
            margin-bottom: 0.1rem;
        }
        .error-card .text-muted {
            font-size: 0.8rem;
            margin-bottom: 0.1rem;
        }
        .error-header .logo-container .error-icon {
            margin-bottom: 0;
        }
        .error-header .logo-container .error-icon i.fa-ban {
            font-size: 2rem;
        }
        /* Compact support info */
        .support-info {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            font-size: 0.7rem;
        }
        .support-info .text-muted {
            margin-bottom: 0;
        }
        /* Responsive */
        @media (max-width: 576px) {
            .error-card .p-4 {
                padding: 1rem !important;
            }
            .action-buttons .btn {
                min-width: 100%;
                font-size: 0.7rem;
            }
            .logo-container {
                flex-direction: column;
                gap: 5px;
            }
            .system-name {
                font-size: 0.95rem;
            }
            .error-icon i.fa-ban {
                font-size: 1.8rem !important;
            }
            .support-info {
                gap: 0.5rem;
                flex-direction: column;
                align-items: center;
            }
            .reason-list li {
                font-size: 0.7rem;
            }
            .alert {
                font-size: 0.75rem;
                padding: 0.4rem 0.6rem;
            }
            .error-card .display-5 {
                font-size: 1.2rem;
            }
            .error-card .lead {
                font-size: 0.85rem;
            }
            .action-buttons {
                gap: 0.5rem;
            }
        }
        @media (max-width: 400px) {
            .error-card .p-4 {
                padding: 0.75rem !important;
            }
            .system-name {
                font-size: 0.85rem;
            }
            .error-icon i.fa-ban {
                font-size: 1.5rem !important;
            }
            .btn {
                font-size: 0.7rem !important;
                padding: 0.25rem 0.6rem !important;
            }
            .action-buttons .btn {
                min-width: auto;
                padding: 0.25rem 0.6rem !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="error-card p-3 p-md-4">
                    <!-- Header - Ultra Compact -->
                    <div class="error-header text-center">
                        <div class="logo-container">
                            @if(isset($systemSettings) && $systemSettings->system_logo)
                                <img src="{{ asset('storage/' . $systemSettings->system_logo) }}" 
                                     alt="{{ $systemSettings->system_name ?? 'System Logo' }}" 
                                     class="system-logo">
                            @else
                                <div class="error-icon">
                                    <i class="fas fa-ban"></i>
                                </div>
                            @endif
                            @if(isset($systemSettings) && $systemSettings->system_name)
                                <h1 class="system-name">{{ $systemSettings->system_name }}</h1>
                            @endif
                        </div>
                        <h2 class="mb-0">Invitation Error</h2>
                        <p class="text-muted mb-0">There's an issue with your invitation link</p>
                    </div>

                    <!-- Error Content - Compact -->
                    <div class="text-center py-2">
                        <div class="error-icon mb-2">
                            <i class="fas fa-ban"></i>
                        </div>
                        
                        <h1 class="display-5 text-danger mb-2">Invalid Invitation</h1>
                        
                        <p class="lead mb-2">
                            This invitation link is invalid or has already been used.
                        </p>
                        
                        @if(session('error'))
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                {{ session('error') }}
                            </div>
                        @endif
                        
                        <!-- Detailed Error Information - Compact -->
                        <div class="alert alert-info mb-2">
                            <h6 class="alert-heading mb-1">
                                <i class="fas fa-info-circle me-2"></i>
                                Possible Reasons
                            </h6>
                            <ul class="reason-list">
                                <li>The invitation link has already been used</li>
                                <li>The invitation was cancelled by the administrator</li>
                                <li>The invitation has expired</li>
                                <li>Invalid or malformed invitation link</li>
                                <li>The associated user account was not found</li>
                            </ul>
                        </div>
                        
                        <!-- Additional Information - Compact -->
                        <div class="alert alert-light border mb-2">
                            <h6 class="mb-1">
                                <i class="fas fa-lightbulb me-2 text-warning"></i>
                                What to do next?
                            </h6>
                            <p class="mb-0">
                                If you believe this is an error, please contact the property administrator 
                                or system support for assistance.
                            </p>
                        </div>
                    </div>

                    <!-- Action Buttons - Compact -->
                    <div class="text-center mt-2 pt-2">
                        <div class="action-buttons">
                            @php
                                $isAuthenticated = false;
                                $dashboardRoute = null;
                                $loginRoute = null;
                                $homeRoute = url('/');
                                
                                try {
                                    $loginRoute = route('login');
                                } catch (\Exception $e) {
                                    $loginRoute = '#';
                                }
                                
                                try {
                                    $dashboardRoute = route('tenant.dashboard');
                                } catch (\Exception $e) {
                                    try {
                                        $dashboardRoute = route('dashboard');
                                    } catch (\Exception $e) {
                                        $dashboardRoute = null;
                                    }
                                }
                            @endphp
                            
                            @if(isset($isAuthenticated) && $isAuthenticated && $dashboardRoute)
                                <a href="{{ $dashboardRoute }}" class="btn btn-primary">
                                    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                                </a>
                            @else
                                <a href="{{ $loginRoute }}" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-1"></i>Login
                                </a>
                            @endif
                            
                            <a href="{{ $homeRoute }}" class="btn btn-outline-primary">
                                <i class="fas fa-home me-1"></i>Home
                            </a>
                            
                            <a href="mailto:support@example.com" class="btn btn-outline-secondary">
                                <i class="fas fa-headset me-1"></i>Support
                            </a>
                        </div>
                    </div>

                    <!-- Support Information - Ultra Compact -->
                    <div class="text-center mt-2">
                        <div class="support-info">
                            <p class="text-muted mb-0">
                                <small>
                                    <i class="fas fa-envelope me-1"></i>
                                    <strong>Email:</strong> support@example.com
                                </small>
                            </p>
                            <p class="text-muted mb-0">
                                <small>
                                    <i class="fas fa-phone me-1"></i>
                                    <strong>Phone:</strong> +1 (555) 123-4567
                                </small>
                            </p>
                        </div>
                    </div>

                    <!-- Footer - Ultra Compact -->
                    <div class="text-center mt-2 pt-2 border-top">
                        <p class="text-muted mb-0">
                            <small>
                                {{ $systemSettings->system_name ?? 'Property Management System' }} &copy; {{ date('Y') }}
                            </small>
                            <span class="mx-2">|</span>
                            <small>
                                Error: INV-{{ date('YmdHis') }}
                            </small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animation for error icon
        document.addEventListener('DOMContentLoaded', function() {
            const errorIcon = document.querySelector('.error-icon i.fa-ban');
            if (errorIcon) {
                setInterval(() => {
                    errorIcon.style.transform = 'scale(1.1)';
                    errorIcon.style.transition = 'transform 0.3s ease';
                    setTimeout(() => {
                        errorIcon.style.transform = 'scale(1)';
                    }, 300);
                }, 3000);
            }
            
            // Add animation to card
            const card = document.querySelector('.error-card');
            card.style.opacity = '0';
            card.style.transform = 'translateY(15px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
            
            // Add hover effects to buttons
            document.querySelectorAll('.btn').forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-1px)';
                    this.style.boxShadow = '0 3px 10px rgba(0,0,0,0.1)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                });
            });
            
            // Add click animation to buttons
            document.querySelectorAll('.btn').forEach(button => {
                button.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 200);
                });
            });
        });

        // Copy error code to clipboard
        function copyErrorCode() {
            const errorCode = 'INV-' + new Date().toISOString().replace(/[^0-9]/g, '').slice(0, 14);
            navigator.clipboard.writeText(errorCode).then(() => {
                const toast = document.createElement('div');
                toast.className = 'alert alert-success alert-dismissible fade show position-fixed';
                toast.style.top = '20px';
                toast.style.right = '20px';
                toast.style.zIndex = '1050';
                toast.style.fontSize = '0.85rem';
                toast.style.padding = '0.5rem 1rem';
                toast.innerHTML = `
                    <i class="fas fa-check-circle me-2"></i>
                    Error code copied!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size: 0.7rem;"></button>
                `;
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        }

        // Add error code copy button
        document.addEventListener('DOMContentLoaded', function() {
            const footer = document.querySelector('.border-top .text-muted');
            if (footer) {
                const copyBtn = document.createElement('button');
                copyBtn.className = 'btn btn-link btn-sm p-0 ms-1';
                copyBtn.style.fontSize = '0.65rem';
                copyBtn.style.textDecoration = 'none';
                copyBtn.innerHTML = '<i class="fas fa-copy"></i>';
                copyBtn.title = 'Copy error code';
                copyBtn.onclick = copyErrorCode;
                footer.appendChild(copyBtn);
            }
        });
    </script>
</body>
</html>