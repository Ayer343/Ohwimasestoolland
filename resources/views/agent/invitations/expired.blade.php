@extends('layouts.guest')

@section('title', 'Invitation Expired')

@section('content')
@php
    // Get system settings for email and contact information
    $systemSettings = \App\Models\SystemSetting::getSettings();
    $supportEmail = $systemSettings->system_email ?? 'support@example.com';
    $systemName = $systemSettings->system_name ?? config('app.name', 'Property Registration System');
    $supportPhone = $systemSettings->system_phone ?? '+233123456789';
@endphp

<div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <!-- Header -->
        <div class="text-center fade-in-up">
            <div class="mx-auto h-16 w-16 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-clock text-red-600 dark:text-red-400 text-2xl"></i>
            </div>
            <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white">
                Invitation Expired
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                This invitation link is no longer valid
            </p>
            
            <!-- System Name Display -->
            @if($systemName)
            <div class="mt-2 inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700">
                <i class="fas fa-building mr-1"></i>
                {{ $systemName }}
            </div>
            @endif
        </div>

        <!-- Expired Message Card -->
        <div class="card card-hover-lift border-l-4 border-red-500 fade-in-up" style="animation-delay: 0.1s">
            <div class="flex items-start space-x-3">
                <i class="fas fa-exclamation-triangle text-red-500 mt-1 flex-shrink-0"></i>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                        This invitation has expired
                    </h3>
                    
                    <div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
                        <p>
                            The invitation link you're trying to access has expired. 
                            Invitation links are typically valid for {{ config('app.invitation_expiry_days', 7) }} days from the date they were sent.
                        </p>
                        
                        <div class="alert-danger rounded-lg p-3">
                            <div class="flex items-center text-red-700 dark:text-red-300">
                                <i class="fas fa-info-circle mr-2 flex-shrink-0"></i>
                                <span class="text-sm">
                                    <strong>Expired on:</strong> 
                                    @if($invitation && $invitation->expires_at)
                                        {{ $invitation->expires_at->format('M j, Y g:i A') }}
                                        @php
                                            $daysAgo = $invitation->expires_at->diffInDays(now());
                                        @endphp
                                        <span class="text-red-600 dark:text-red-400 font-medium">
                                            ({{ $daysAgo }} day{{ $daysAgo != 1 ? 's' : '' }} ago)
                                        </span>
                                    @else
                                        <span class="text-red-600 dark:text-red-400">Unknown date</span>
                                    @endif
                                </span>
                            </div>
                        </div>

                        <p class="text-gray-700 dark:text-gray-300">
                            If you believe this is a mistake or need a new invitation, 
                            please contact {{ $systemName }} support.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invitation Details (if available) -->
        @if($invitation)
        <div class="card card-hover-lift fade-in-up" style="animation-delay: 0.2s">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-semibold text-gray-900 dark:text-white">
                    Invitation Details
                </h4>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                    <i class="fas fa-clock mr-1"></i>
                    Expired
                </span>
            </div>
            
            <div class="space-y-4 text-sm">
                @if($invitation->plan)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-3">
                        <div>
                            <div class="text-gray-600 dark:text-gray-400 text-xs font-medium uppercase tracking-wide">Assignment</div>
                            <div class="text-gray-900 dark:text-white font-medium mt-1">
                                {{ $invitation->plan->zone ?? 'N/A' }}
                                @if($invitation->plan->section)
                                    <span class="text-gray-500 dark:text-gray-400">- {{ $invitation->plan->section }}</span>
                                @endif
                            </div>
                        </div>
                        
                        <div>
                            <div class="text-gray-600 dark:text-gray-400 text-xs font-medium uppercase tracking-wide">Estimated Properties</div>
                            <div class="text-gray-900 dark:text-white font-medium mt-1">
                                {{ $invitation->plan->estimated_houses ?? 'N/A' }}
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <div>
                            <div class="text-gray-600 dark:text-gray-400 text-xs font-medium uppercase tracking-wide">Sent Via</div>
                            <div class="text-gray-900 dark:text-white font-medium mt-1 capitalize">
                                <span class="inline-flex items-center">
                                    @if($invitation->sent_via === 'sms')
                                        <i class="fas fa-sms mr-1 text-blue-500"></i>
                                    @elseif($invitation->sent_via === 'email')
                                        <i class="fas fa-envelope mr-1 text-green-500"></i>
                                    @elseif($invitation->sent_via === 'whatsapp')
                                        <i class="fab fa-whatsapp mr-1 text-green-500"></i>
                                    @else
                                        <i class="fas fa-paper-plane mr-1 text-gray-500"></i>
                                    @endif
                                    {{ $invitation->sent_via ?? 'Unknown' }}
                                </span>
                            </div>
                        </div>
                        
                        <div>
                            <div class="text-gray-600 dark:text-gray-400 text-xs font-medium uppercase tracking-wide">Sent On</div>
                            <div class="text-gray-900 dark:text-white font-medium mt-1">
                                {{ $invitation->created_at->format('M j, Y g:i A') }}
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if($invitation->agent)
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <h5 class="font-medium text-gray-900 dark:text-white mb-3 flex items-center">
                        <i class="fas fa-user mr-2 text-blue-500"></i>
                        Agent Information
                    </h5>
                    <div class="space-y-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 dark:text-gray-400">Name:</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $invitation->agent->name ?? 'N/A' }}</span>
                        </div>
                        @if($invitation->agent->email)
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 dark:text-gray-400">Email:</span>
                            <span class="font-medium text-gray-900 dark:text-white text-sm">{{ $invitation->agent->email }}</span>
                        </div>
                        @endif
                        @if($invitation->agent->phone)
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 dark:text-gray-400">Phone:</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $invitation->agent->phone }}</span>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                <!-- Additional Invitation Metadata -->
                @if($invitation->resend_count > 0)
                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-gray-500 dark:text-gray-400">Times Resent:</span>
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $invitation->resend_count }}</span>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Action Buttons -->
        <div class="space-y-4 fade-in-up" style="animation-delay: 0.3s">
            <!-- Request New Invitation -->
            <div class="alert-info rounded-lg p-4">
                <div class="flex items-start">
                    <i class="fas fa-envelope text-blue-500 mr-3 mt-0.5 flex-shrink-0"></i>
                    <div class="flex-1">
                        <h5 class="font-medium text-blue-900 dark:text-blue-100 text-sm mb-1">
                            Need a new invitation?
                        </h5>
                        <p class="text-blue-700 dark:text-blue-300 text-xs">
                            Contact {{ $systemName }} support to request a new invitation link. They can resend it with a new expiration date.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-3">
                <!-- Contact Support Button -->
                @php
                    $agentName = $invitation ? ($invitation->agent->name ?? 'Agent') : 'Agent';
                    $agentPhone = $invitation ? ($invitation->agent->phone ?? 'N/A') : 'N/A';
                    $agentEmail = $invitation ? ($invitation->agent->email ?? 'N/A') : 'N/A';
                    $expiredDate = $invitation && $invitation->expires_at ? $invitation->expires_at->format('M j, Y') : 'Unknown';
                    $assignmentZone = $invitation && $invitation->plan ? $invitation->plan->zone : 'N/A';
                    $assignmentSection = $invitation && $invitation->plan && $invitation->plan->section ? ' - ' . $invitation->plan->section : '';
                    
                    $emailSubject = "Expired Invitation - " . $agentName;
                    $emailBody = "Hello, I need a new invitation link for field agent registration.%0D%0A%0D%0A";
                    $emailBody .= "My details:%0D%0A";
                    $emailBody .= "Name: " . $agentName . "%0D%0A";
                    $emailBody .= "Phone: " . $agentPhone . "%0D%0A";
                    $emailBody .= "Email: " . $agentEmail . "%0D%0A%0D%0A";
                    $emailBody .= "Original invitation expired on: " . $expiredDate . "%0D%0A";
                    $emailBody .= "Assignment: " . $assignmentZone . $assignmentSection;
                @endphp
                
                <a href="mailto:{{ $supportEmail }}?subject={{ $emailSubject }}&body={{ $emailBody }}"
                   class="btn-primary btn-glow flex-1 inline-flex items-center justify-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200 transform hover:scale-105">
                    <i class="fas fa-envelope mr-2"></i>
                    Contact {{ $systemName }}
                </a>

                <!-- Back to Login Button -->
                <a href="{{ route('login') }}"
                   class="btn-outline-glow flex-1 inline-flex items-center justify-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200 transform hover:scale-105">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Back to Login
                </a>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-2 gap-2 text-xs">
                <a href="tel:{{ $supportPhone }}"
                   class="inline-flex items-center justify-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                    <i class="fas fa-phone mr-1"></i>
                    Call Support
                </a>
                <button onclick="copyContactInfo()"
                   class="inline-flex items-center justify-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                    <i class="fas fa-copy mr-1"></i>
                    Copy Details
                </button>
            </div>
        </div>

        <!-- Additional Help Information -->
        <div class="card fade-in-up" style="animation-delay: 0.4s">
            <h5 class="font-medium text-gray-900 dark:text-white text-sm mb-3 flex items-center">
                <i class="fas fa-question-circle text-purple-500 mr-2"></i>
                Why do invitations expire?
            </h5>
            <ul class="text-xs text-gray-600 dark:text-gray-300 space-y-2">
                <li class="flex items-start">
                    <i class="fas fa-shield-alt text-green-500 mr-2 mt-0.5 flex-shrink-0"></i>
                    <span><strong>Security:</strong> Prevents unauthorized access to old invitation links</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-sync-alt text-blue-500 mr-2 mt-0.5 flex-shrink-0"></i>
                    <span><strong>Fresh Assignments:</strong> Ensures you receive current and relevant assignments</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-user-clock text-purple-500 mr-2 mt-0.5 flex-shrink-0"></i>
                    <span><strong>Active Team:</strong> Maintains an engaged and up-to-date field agent team</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-database text-orange-500 mr-2 mt-0.5 flex-shrink-0"></i>
                    <span><strong>Data Management:</strong> Helps keep our system organized and efficient</span>
                </li>
            </ul>
        </div>

        <!-- Next Steps -->
        <div class="alert-success rounded-lg p-4 fade-in-up" style="animation-delay: 0.5s">
            <h5 class="font-medium text-green-900 dark:text-green-100 text-sm mb-2 flex items-center">
                <i class="fas fa-lightbulb text-green-500 mr-2"></i>
                What to do next?
            </h5>
            <ol class="text-xs text-green-700 dark:text-green-300 space-y-1 ml-4">
                <li class="list-decimal">Contact {{ $systemName }} using the button above</li>
                <li class="list-decimal">Provide your name and contact details</li>
                <li class="list-decimal">Mention the assignment zone you were assigned to</li>
                <li class="list-decimal">Check your email for the new invitation within 24 hours</li>
            </ol>
        </div>

        <!-- Support Information -->
        <div class="text-center fade-in-up" style="animation-delay: 0.6s">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Need immediate assistance? 
                <a href="tel:{{ $supportPhone }}" class="text-blue-600 hover:text-blue-500 dark:text-blue-400 font-medium">
                    Call {{ $supportPhone }}
                </a>
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                Available Monday - Friday, 8:00 AM - 6:00 PM
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                Or email: 
                <a href="mailto:{{ $supportEmail }}" class="text-blue-600 hover:text-blue-500 dark:text-blue-400">
                    {{ $supportEmail }}
                </a>
            </p>
        </div>
    </div>
</div>

<!-- Toast Notification Element -->
<div id="toast" class="fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300 hidden">
    <div class="flex items-center">
        <i class="fas fa-check-circle mr-2"></i>
        <span id="toast-message"></span>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize animations
    initializeAnimations();
    
    // Add interactive functionality
    initializeInteractiveElements();
    
    // Initialize theme if not already set by parent layout
    initializeTheme();
});

function initializeTheme() {
    // Check if theme is already initialized by parent layout
    if (!document.body.hasAttribute('data-theme')) {
        const savedTheme = localStorage.getItem('theme') || 
                          (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.body.setAttribute('data-theme', savedTheme);
    }
}

function initializeAnimations() {
    // Animate elements on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                entry.target.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            }
        });
    }, observerOptions);

    // Observe all fade-in elements
    document.querySelectorAll('.fade-in-up').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'all 0.6s ease-out';
        observer.observe(el);
    });
}

function initializeInteractiveElements() {
    // Add enhanced hover effects to cards
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-8px) scale(1.02)';
            card.style.boxShadow = '0 20px 40px rgba(0, 0, 0, 0.15)';
        });
        
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0) scale(1)';
            card.style.boxShadow = '';
        });
    });

    // Add loading states to buttons
    const buttons = document.querySelectorAll('a[href^="mailto:"], a[href^="tel:"]');
    buttons.forEach(button => {
        button.addEventListener('click', (e) => {
            // Add temporary loading state
            const originalHtml = button.innerHTML;
            button.innerHTML = '<div class="loading-dots mr-2"><span></span><span></span><span></span></div>Opening...';
            button.disabled = true;
            
            setTimeout(() => {
                button.innerHTML = originalHtml;
                button.disabled = false;
            }, 2000);
        });
    });

    // Enhanced button hover effects
    const primaryButtons = document.querySelectorAll('.btn-primary, .btn-outline-glow');
    primaryButtons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px) scale(1.05)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
}

function copyContactInfo() {
    const agentInfo = {
        name: '{{ $invitation ? ($invitation->agent->name ?? "N/A") : "N/A" }}',
        phone: '{{ $invitation ? ($invitation->agent->phone ?? "N/A") : "N/A" }}',
        email: '{{ $invitation ? ($invitation->agent->email ?? "N/A") : "N/A" }}',
        assignment: '{{ $invitation && $invitation->plan ? $invitation->plan->zone : "N/A" }}{{ $invitation && $invitation->plan && $invitation->plan->section ? " - " . $invitation->plan->section : "" }}',
        expired: '{{ $invitation && $invitation->expires_at ? $invitation->expires_at->format("M j, Y") : "Unknown" }}',
        supportEmail: '{{ $supportEmail }}',
        supportPhone: '{{ $supportPhone }}',
        systemName: '{{ $systemName }}'
    };

    const textToCopy = `Agent Information:
Name: ${agentInfo.name}
Phone: ${agentInfo.phone}
Email: ${agentInfo.email}
Assignment: ${agentInfo.assignment}
Invitation Expired: ${agentInfo.expired}

Contact ${agentInfo.systemName} Support:
Email: ${agentInfo.supportEmail}
Phone: ${agentInfo.supportPhone}`;

    navigator.clipboard.writeText(textToCopy).then(() => {
        showToast('Agent details and support information copied to clipboard!', 'success');
    }).catch(err => {
        console.error('Failed to copy: ', err);
        showToast('Failed to copy details', 'error');
    });
}

function showToast(message, type = 'success') {
    // Create toast element if it doesn't exist
    let toast = document.getElementById('toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast';
        toast.className = 'fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300';
        document.body.appendChild(toast);
    }

    const toastMessage = document.getElementById('toast-message') || (() => {
        const span = document.createElement('span');
        span.id = 'toast-message';
        toast.appendChild(span);
        return span;
    })();

    // Set toast style based on type
    const styles = {
        success: 'bg-green-500 text-white',
        error: 'bg-red-500 text-white',
        warning: 'bg-orange-500 text-white',
        info: 'bg-blue-500 text-white'
    };
    
    // Update toast content and style
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : type === 'warning' ? 'exclamation-circle' : 'info-circle'} mr-2"></i>
            <span id="toast-message">${message}</span>
        </div>
    `;
    
    toast.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300 ${styles[type] || styles.success}`;
    
    // Show toast
    toast.classList.remove('hidden', 'translate-x-full');
    toast.classList.add('translate-x-0');
    
    // Hide toast after 4 seconds
    setTimeout(() => {
        toast.classList.remove('translate-x-0');
        toast.classList.add('translate-x-full');
        setTimeout(() => {
            toast.classList.add('hidden');
        }, 300);
    }, 4000);
}

// Enhanced email link handling
function encodeEmailLinks() {
    const emailLinks = document.querySelectorAll('a[href^="mailto:"]');
    emailLinks.forEach(link => {
        const originalHref = link.getAttribute('href');
        const encodedHref = encodeURI(originalHref);
        link.setAttribute('href', encodedHref);
    });
}

// Initialize when page loads
window.addEventListener('load', () => {
    encodeEmailLinks();
    
    // Add print styles
    const style = document.createElement('style');
    style.textContent = `
        @media print {
            .no-print { display: none !important; }
            .bg-white { background: white !important; }
            .text-gray-900 { color: black !important; }
            .shadow-lg { box-shadow: none !important; }
            .border-l-4 { border-left: 2px solid #dc2626 !important; }
            .card { border: 1px solid #000 !important; }
        }
    `;
    document.head.appendChild(style);
});

// Keyboard navigation support
document.addEventListener('keydown', (e) => {
    // Escape key to go back
    if (e.key === 'Escape') {
        window.history.back();
    }
    
    // Enter key on buttons
    if (e.key === 'Enter' && e.target.tagName === 'BUTTON') {
        e.target.click();
    }
    
    // Space key on buttons
    if (e.key === ' ' && e.target.tagName === 'BUTTON') {
        e.preventDefault();
        e.target.click();
    }
});

// Enhanced error handling for offline scenario
window.addEventListener('online', () => {
    showToast('Connection restored', 'success');
});

window.addEventListener('offline', () => {
    showToast('You are currently offline. Some features may not work.', 'warning');
});

// Touch device optimizations
if ('ontouchstart' in window) {
    document.documentElement.classList.add('touch-device');
    
    // Add touch-specific styles
    const touchStyle = document.createElement('style');
    touchStyle.textContent = `
        .touch-device .btn-primary,
        .touch-device .btn-outline-glow {
            min-height: 44px;
        }
        
        .touch-device .card {
            cursor: pointer;
        }
    `;
    document.head.appendChild(touchStyle);
}

// Performance optimizations
let resizeTimeout;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
        // Re-initialize animations on resize
        initializeAnimations();
    }, 250);
});

// Enhanced focus management for accessibility
document.addEventListener('focusin', (e) => {
    if (e.target.matches('button, a, input, select, textarea')) {
        e.target.style.outline = '2px solid var(--primary)';
        e.target.style.outlineOffset = '2px';
    }
});

document.addEventListener('focusout', (e) => {
    if (e.target.matches('button, a, input, select, textarea')) {
        e.target.style.outline = 'none';
    }
});
</script>

<style>
/* Enhanced Custom Styles */
.border-l-4 {
    border-left-width: 4px;
}

/* Smooth transitions for all interactive elements */
.transition-all {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.transition-colors {
    transition: color 0.3s ease, background-color 0.3s ease, border-color 0.3s ease;
}

/* Enhanced hover effects */
.hover\:scale-105:hover {
    transform: scale(1.05);
}

/* Dark mode enhancements */
@media (prefers-color-scheme: dark) {
    .shadow-lg {
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
    }
}

/* Responsive design improvements */
@media (max-width: 640px) {
    .max-w-md {
        margin: 0.5rem;
    }
    
    .flex-col {
        flex-direction: column;
    }
    
    .text-3xl {
        font-size: 1.75rem;
    }
    
    .space-y-8 > * + * {
        margin-top: 1.5rem;
    }
    
    .card {
        margin: 0.25rem;
        padding: 1rem;
    }
}

/* Custom animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fade-in-up {
    animation: fadeInUp 0.6s ease-out forwards;
}

/* Loading animation */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Focus styles for accessibility */
button:focus,
a:focus,
input:focus,
select:focus,
textarea:focus {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .transition-all,
    .transition-colors,
    .fade-in-up {
        transition: none !important;
        animation: none !important;
    }
    
    .hover\:scale-105:hover {
        transform: none;
    }
    
    .card-hover-lift:hover {
        transform: none !important;
    }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .bg-red-100 {
        background-color: white;
        border: 2px solid red;
    }
    
    .text-red-600 {
        color: red;
        font-weight: bold;
    }
    
    .card {
        border: 2px solid currentColor !important;
    }
}

/* Print styles */
@media print {
    .no-print {
        display: none !important;
    }
    
    .bg-white {
        background: white !important;
        border: 1px solid #000 !important;
    }
    
    .text-gray-900 {
        color: black !important;
    }
    
    .shadow-lg {
        box-shadow: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        break-inside: avoid;
    }
    
    a::after {
        content: " (" attr(href) ")";
    }
}

/* Enhanced card styles for the layout */
.card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    backdrop-filter: blur(10px);
}

/* Ensure proper dark mode text colors */
.text-gray-900 {
    color: var(--text-primary);
}

.text-gray-600,
.text-gray-500,
.text-gray-400 {
    color: var(--text-secondary);
}

/* Enhanced button states */
.btn-primary:disabled,
.btn-outline-glow:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn-primary:disabled:hover,
.btn-outline-glow:disabled:hover {
    transform: none !important;
}

/* Mobile-first responsive improvements */
@media (max-width: 480px) {
    .space-y-4 > * + * {
        margin-top: 1rem;
    }
    
    .text-3xl {
        font-size: 1.5rem;
    }
    
    .card {
        padding: 1rem;
        border-radius: 12px;
    }
}

/* Large screen optimizations */
@media (min-width: 1536px) {
    .max-w-md {
        max-width: 28rem;
    }
}

/* Scrollbar styling for webkit browsers */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-primary);
}

::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--secondary);
}
</style>
@endsection