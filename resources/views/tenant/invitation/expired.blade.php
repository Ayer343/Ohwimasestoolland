<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation Expired - {{ $systemSettings->system_name ?? 'Property Management System' }}</title>
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
        .expired-card {
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
        .expired-icon {
            font-size: 5rem;
            color: #ffc107;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.9; }
            100% { transform: scale(1); opacity: 1; }
        }
        .alert-warning {
            border-left: 4px solid #ffc107;
            background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%);
            border: none;
            border-radius: 10px;
        }
        .expired-header {
            border-bottom: 2px solid #f8f9fa;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .action-buttons .btn {
            min-width: 180px;
        }
        .timeline {
            position: relative;
            margin: 30px 0;
            padding-left: 40px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -34px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #6c757d;
            border: 2px solid white;
            box-shadow: 0 0 0 3px #dee2e6;
        }
        .timeline-item.expired::before {
            background: #dc3545;
            box-shadow: 0 0 0 3px #f8d7da;
        }
        .timeline-item.current::before {
            background: #ffc107;
            box-shadow: 0 0 0 3px #fff3cd;
            animation: blink 1.5s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .timeline-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #6c757d;
        }
        .timeline-item.expired .timeline-content {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        .timeline-item.current .timeline-content {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        .instruction-list {
            text-align: left;
            margin: 0;
            padding-left: 1.5rem;
        }
        .instruction-list li {
            margin-bottom: 10px;
            color: #6c757d;
        }
        .instruction-list li strong {
            color: #495057;
        }
        .countdown {
            font-size: 2.5rem;
            font-weight: bold;
            color: #dc3545;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="expired-card p-4 p-md-5">
                    <!-- Header with System Logo -->
                    <div class="expired-header text-center">
                        <div class="logo-container">
                            @if(isset($systemSettings) && $systemSettings->system_logo)
                                <img src="{{ asset('storage/' . $systemSettings->system_logo) }}" 
                                     alt="{{ $systemSettings->system_name ?? 'System Logo' }}" 
                                     class="system-logo">
                            @else
                                <div class="expired-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                            @endif
                            @if(isset($systemSettings) && $systemSettings->system_name)
                                <h1 class="system-name">{{ $systemSettings->system_name }}</h1>
                            @endif
                        </div>
                        <h2 class="mb-2">Invitation Status</h2>
                        <p class="text-muted">Your invitation link has expired</p>
                    </div>

                    <!-- Main Content -->
                    <div class="text-center py-3">
                        <div class="expired-icon mb-4">
                            <i class="fas fa-hourglass-end"></i>
                        </div>
                        
                        <h1 class="display-5 text-warning mb-3">Invitation Expired</h1>
                        
                        <p class="lead mb-4">
                            This invitation link has expired. Invitation links are only valid for a limited time.
                        </p>
                        
                        <!-- Expiration Timeline -->
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-content">
                                    <h6 class="mb-2">
                                        <i class="fas fa-paper-plane text-primary me-2"></i>
                                        Invitation Sent
                                    </h6>
                                    <p class="mb-0 text-muted">
                                        @if(isset($invitation) && $invitation->created_at)
                                            {{ $invitation->created_at->format('M j, Y \a\t g:i A') }}
                                        @else
                                            Recently
                                        @endif
                                    </p>
                                </div>
                            </div>
                            
                            <div class="timeline-item current">
                                <div class="timeline-content">
                                    <h6 class="mb-2">
                                        <i class="fas fa-clock text-warning me-2"></i>
                                        Invitation Expired
                                    </h6>
                                    <p class="mb-0 text-muted">
                                        @if(isset($invitation) && $invitation->expires_at)
                                            Expired on {{ $invitation->expires_at->format('M j, Y \a\t g:i A') }}
                                        @else
                                            Expired recently
                                        @endif
                                    </p>
                                </div>
                            </div>
                            
                            <div class="timeline-item expired">
                                <div class="timeline-content">
                                    <h6 class="mb-2">
                                        <i class="fas fa-ban text-danger me-2"></i>
                                        Link No Longer Valid
                                    </h6>
                                    <p class="mb-0 text-muted">
                                        This link can no longer be used for registration
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Instructions -->
                        <div class="alert alert-warning mb-4">
                            <h5 class="alert-heading mb-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                What to do next
                            </h5>
                            <ul class="instruction-list">
                                <li>
                                    <strong>Contact your landlord or property manager</strong> for a new invitation
                                </li>
                                <li>
                                    <strong>Make sure to complete registration</strong> within the time frame when you receive a new invitation
                                </li>
                                <li>
                                    <strong>Check your email</strong> for any recent invitations or follow-up messages
                                </li>
                                <li>
                                    <strong>Keep an eye on your spam folder</strong> in case invitations are being filtered
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Additional Information -->
                        <div class="alert alert-light border">
                            <div class="row align-items-center">
                                <div class="col-md-8 text-start">
                                    <h6 class="mb-1">
                                        <i class="fas fa-info-circle text-info me-2"></i>
                                        Need immediate assistance?
                                    </h6>
                                    <p class="mb-0">
                                        Contact the property administrator directly for a new invitation link.
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button class="btn btn-sm btn-outline-info" onclick="showContactInfo()">
                                        <i class="fas fa-address-book me-2"></i>View Contact Info
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="text-center mt-4 pt-3">
                        <div class="action-buttons">
                            @php
                                // Check routes safely
                                $loginRoute = null;
                                $homeRoute = url('/');
                                
                                try {
                                    $loginRoute = route('login');
                                } catch (\Exception $e) {
                                    $loginRoute = '#';
                                }
                                
                                // Check if there's a property contact
                                $propertyContact = null;
                                if (isset($invitation) && $invitation->property && $invitation->property->landlord) {
                                    $propertyContact = $invitation->property->landlord->email ?? null;
                                }
                            @endphp
                            
                            <a href="{{ $loginRoute }}" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                            </a>
                            
                            <a href="{{ $homeRoute }}" class="btn btn-outline-primary">
                                <i class="fas fa-home me-2"></i>Back to Home
                            </a>
                            
                            @if($propertyContact)
                                <a href="mailto:{{ $propertyContact }}" class="btn btn-outline-success">
                                    <i class="fas fa-envelope me-2"></i>Contact Landlord
                                </a>
                            @else
                                <button class="btn btn-outline-success" onclick="showContactInfo()">
                                    <i class="fas fa-headset me-2"></i>Request New Invitation
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Contact Information (Hidden by default) -->
                    <div id="contactInfo" class="mt-4" style="display: none;">
                        <div class="alert alert-info">
                            <h6 class="mb-3">
                                <i class="fas fa-address-card me-2"></i>
                                Contact Information
                            </h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <i class="fas fa-building me-2"></i>
                                        <strong>Property:</strong><br>
                                        @if(isset($invitation) && $invitation->property)
                                            {{ $invitation->property->property_name }}
                                        @else
                                            N/A
                                        @endif
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <i class="fas fa-user-tie me-2"></i>
                                        <strong>Contact Person:</strong><br>
                                        @if(isset($invitation) && $invitation->property && $invitation->property->landlord)
                                            {{ $invitation->property->landlord->name ?? 'N/A' }}
                                        @else
                                            Property Administrator
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <i class="fas fa-envelope me-2"></i>
                                        <strong>Email:</strong><br>
                                        {{ $propertyContact ?? 'support@example.com' }}
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <i class="fas fa-phone me-2"></i>
                                        <strong>Phone:</strong><br>
                                        +1 (555) 123-4567
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Support Information -->
                    <div class="text-center mt-4">
                        <p class="text-muted mb-1">
                            <small>
                                <i class="fas fa-clock me-1"></i>
                                <strong>Invitation Links Expire:</strong> Typically within 7 days
                            </small>
                        </p>
                        <p class="text-muted">
                            <small>
                                <i class="fas fa-redo me-1"></i>
                                <strong>Need a new invitation?</strong> Request one from your property administrator
                            </small>
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
                                Error Code: EXP-{{ date('YmdHis') }}
                            </small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animation for expired icon
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to card
            const card = document.querySelector('.expired-card');
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
            
            // Animate timeline items sequentially
            const timelineItems = document.querySelectorAll('.timeline-item');
            timelineItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                
                setTimeout(() => {
                    item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateX(0)';
                }, 300 + (index * 300));
            });
            
            // Add hover effects to buttons
            document.querySelectorAll('.btn').forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                });
                
                // Add click animation
                button.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 200);
                });
            });
        });

        // Show contact information
        function showContactInfo() {
            const contactInfo = document.getElementById('contactInfo');
            if (contactInfo) {
                if (contactInfo.style.display === 'none') {
                    contactInfo.style.display = 'block';
                    contactInfo.style.opacity = '0';
                    contactInfo.style.transform = 'translateY(-20px)';
                    
                    setTimeout(() => {
                        contactInfo.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                        contactInfo.style.opacity = '1';
                        contactInfo.style.transform = 'translateY(0)';
                    }, 10);
                } else {
                    contactInfo.style.opacity = '0';
                    contactInfo.style.transform = 'translateY(-20px)';
                    
                    setTimeout(() => {
                        contactInfo.style.display = 'none';
                    }, 500);
                }
            }
        }

        // Copy error code to clipboard
        function copyErrorCode() {
            const errorCode = 'EXP-' + new Date().toISOString().replace(/[^0-9]/g, '').slice(0, 14);
            navigator.clipboard.writeText(errorCode).then(() => {
                // Show copied notification
                const toast = document.createElement('div');
                toast.className = 'alert alert-success alert-dismissible fade show position-fixed';
                toast.style.top = '20px';
                toast.style.right = '20px';
                toast.style.zIndex = '1050';
                toast.innerHTML = `
                    <i class="fas fa-check-circle me-2"></i>
                    Error code copied to clipboard!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(toast);
                
                // Auto-remove after 3 seconds
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        }

        // Add error code copy button
        document.addEventListener('DOMContentLoaded', function() {
            const errorCodeElement = document.querySelector('.text-muted small:last-child');
            if (errorCodeElement) {
                const copyBtn = document.createElement('button');
                copyBtn.className = 'btn btn-sm btn-link p-0 ms-2';
                copyBtn.innerHTML = '<i class="fas fa-copy"></i>';
                copyBtn.title = 'Copy error code';
                copyBtn.onclick = copyErrorCode;
                errorCodeElement.appendChild(copyBtn);
            }
            
            // Add countdown if expiration time is near
            @if(isset($invitation) && $invitation->expires_at)
                const expiredAt = new Date('{{ $invitation->expires_at }}');
                const now = new Date();
                
                if ((expiredAt - now) / (1000 * 60 * 60) < 1) {
                    // Show countdown for recently expired invitations
                    const countdownElement = document.createElement('div');
                    countdownElement.className = 'countdown';
                    countdownElement.innerHTML = 'EXPIRED';
                    
                    const leadParagraph = document.querySelector('.lead.mb-4');
                    if (leadParagraph) {
                        leadParagraph.parentNode.insertBefore(countdownElement, leadParagraph.nextSibling);
                    }
                }
            @endif
        });

        // Simulate clock ticking animation
        setInterval(() => {
            const expiredIcon = document.querySelector('.expired-icon i.fa-hourglass-end');
            if (expiredIcon) {
                expiredIcon.style.transform = 'rotate(5deg)';
                expiredIcon.style.transition = 'transform 0.3s ease';
                setTimeout(() => {
                    expiredIcon.style.transform = 'rotate(-5deg)';
                }, 300);
                setTimeout(() => {
                    expiredIcon.style.transform = 'rotate(0deg)';
                }, 600);
            }
        }, 2000);
    </script>
</body>
</html>