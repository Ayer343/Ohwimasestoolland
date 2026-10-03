{{-- resources/views/tenant/contact-landlord.blade.php --}}
@php
    $layout = 'layouts.tenant';
    $user = auth()->user();
    
    // Check if we have a specific unit passed (for unit-specific contact)
    $unit = $unit ?? null;
    $propertyName = $unit ? $unit->property->property_name : ($user->propertyUnits()->first()->property->property_name ?? 'N/A');
    $unitNumber = $unit ? $unit->unit_number : ($user->propertyUnits()->first()->unit_number ?? 'N/A');
    $landlord = $unit ? $unit->property->landlord : ($user->propertyUnits()->first()->property->landlord ?? null);
    
    $pageTitle = 'Contact Landlord';
    if ($unit) {
        $pageTitle .= ' - ' . $propertyName . ' - Unit ' . $unitNumber;
    }
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-envelope mr-2"></i> Contact Landlord
            </h2>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i> 
                Send a message to your landlord
            </div>
        </div>
    </div>

    @if(!$landlord)
        <!-- No Landlord Assigned -->
        <div class="card">
            <div class="p-6 text-center">
                <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                     style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-user-slash text-2xl" style="color: var(--warning);"></i>
                </div>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Landlord Assigned</h4>
                <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                    You don't have a landlord assigned to your unit yet. Please contact the administration for assistance.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="{{ route('tenant.dashboard') }}" 
                       class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    @else
        <!-- Success Message -->
        @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Success!</strong>
            <span class="block sm:inline">{{ session('success') }}</span>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @endif

        <!-- Error Message -->
        @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline">{{ session('error') }}</span>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Contact Form -->
            <div class="lg:col-span-2">
                <div class="card p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-paper-plane mr-2"></i> Send Message
                    </h3>
                    
                    <form action="{{ $unit ? route('tenant.property-units.contact-landlord.send', $unit->id) : route('tenant.contact-landlord.send') }}" 
                          method="POST" id="contactForm">
                        @csrf
                        
                        <!-- Unit Information (if available) -->
                        @if($unit)
                        <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                            <h4 class="font-medium mb-2 flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-home mr-2"></i> Unit Information
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <span class="text-sm" style="color: var(--text-secondary);">Property:</span>
                                    <p class="font-medium" style="color: var(--text-primary);">{{ $propertyName }}</p>
                                </div>
                                <div>
                                    <span class="text-sm" style="color: var(--text-secondary);">Unit Number:</span>
                                    <p class="font-medium" style="color: var(--text-primary);">{{ $unitNumber }}</p>
                                </div>
                            </div>
                        </div>
                        @endif
                        
                        <!-- Subject -->
                        <div class="mb-6">
                            <label for="subject" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-tag mr-1"></i> Subject *
                            </label>
                            <input type="text" 
                                   id="subject" 
                                   name="subject" 
                                   required
                                   class="w-full px-4 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-offset-2"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="Enter message subject"
                                   value="{{ old('subject') }}">
                            @error('subject')
                                <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <!-- Message -->
                        <div class="mb-6">
                            <label for="message" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-comment-alt mr-1"></i> Message *
                            </label>
                            <textarea 
                                id="message" 
                                name="message" 
                                rows="6"
                                required
                                class="w-full px-4 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-offset-2"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color); resize: vertical;"
                                placeholder="Type your message here...">{{ old('message') }}</textarea>
                            <div class="flex justify-between items-center mt-1">
                                <span class="text-xs" style="color: var(--text-secondary);">
                                    <span id="charCount">0</span> / 2000 characters
                                </span>
                                <span class="text-xs" style="color: var(--text-secondary);">
                                    * Required fields
                                </span>
                            </div>
                            @error('message')
                                <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <!-- Priority -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-exclamation-circle mr-1"></i> Priority
                            </label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <label class="flex items-center p-3 rounded-lg border cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                       style="border-color: var(--border-color);">
                                    <input type="radio" name="priority" value="low" class="mr-3" {{ old('priority', 'normal') === 'low' ? 'checked' : '' }}>
                                    <div>
                                        <div class="font-medium flex items-center" style="color: var(--text-primary);">
                                            <i class="fas fa-circle text-blue-500 mr-2 text-xs"></i> Low
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">General inquiry</div>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-3 rounded-lg border cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                       style="border-color: var(--border-color);">
                                    <input type="radio" name="priority" value="normal" class="mr-3" {{ old('priority', 'normal') === 'normal' ? 'checked' : '' }}>
                                    <div>
                                        <div class="font-medium flex items-center" style="color: var(--text-primary);">
                                            <i class="fas fa-circle text-green-500 mr-2 text-xs"></i> Normal
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Standard request</div>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-3 rounded-lg border cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                       style="border-color: var(--border-color);">
                                    <input type="radio" name="priority" value="high" class="mr-3" {{ old('priority') === 'high' ? 'checked' : '' }}>
                                    <div>
                                        <div class="font-medium flex items-center" style="color: var(--text-primary);">
                                            <i class="fas fa-circle text-yellow-500 mr-2 text-xs"></i> High
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Urgent matter</div>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-3 rounded-lg border cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                       style="border-color: var(--border-color);">
                                    <input type="radio" name="priority" value="urgent" class="mr-3" {{ old('priority') === 'urgent' ? 'checked' : '' }}>
                                    <div>
                                        <div class="font-medium flex items-center" style="color: var(--text-primary);">
                                            <i class="fas fa-circle text-red-500 mr-2 text-xs"></i> Urgent
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Emergency</div>
                                    </div>
                                </label>
                            </div>
                            @error('priority')
                                <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <!-- Contact Method -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-paper-plane mr-1"></i> Contact Method
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <label class="flex items-center p-3 rounded-lg border cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                       style="border-color: var(--border-color);">
                                    <input type="radio" name="contact_method" value="email" class="mr-3" {{ old('contact_method') === 'email' ? 'checked' : '' }}>
                                    <div>
                                        <div class="font-medium flex items-center" style="color: var(--text-primary);">
                                            <i class="fas fa-envelope text-blue-500 mr-2"></i> Email
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Send via email</div>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-3 rounded-lg border cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                       style="border-color: var(--border-color);">
                                    <input type="radio" name="contact_method" value="sms" class="mr-3" {{ old('contact_method') === 'sms' ? 'checked' : '' }}>
                                    <div>
                                        <div class="font-medium flex items-center" style="color: var(--text-primary);">
                                            <i class="fas fa-sms text-green-500 mr-2"></i> SMS
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Send via SMS</div>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-3 rounded-lg border cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                       style="border-color: var(--border-color);">
                                    <input type="radio" name="contact_method" value="both" class="mr-3" {{ old('contact_method', 'email') === 'both' ? 'checked' : '' }}>
                                    <div>
                                        <div class="font-medium flex items-center" style="color: var(--text-primary);">
                                            <i class="fas fa-mail-bulk text-purple-500 mr-2"></i> Both
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">Email & SMS</div>
                                    </div>
                                </label>
                            </div>
                            @error('contact_method')
                                <p class="mt-1 text-sm" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t" style="border-color: var(--border-color);">
                            <button type="submit" 
                                    class="btn-primary flex-1 px-6 py-3 rounded-lg font-medium text-white inline-flex items-center justify-center transition-all hover:scale-[1.02]">
                                <i class="fas fa-paper-plane mr-2"></i> Send Message
                            </button>
                            <a href="{{ $unit ? route('tenant.property-units.show', $unit->id) : route('tenant.dashboard') }}" 
                               class="btn-secondary flex-1 px-6 py-3 rounded-lg font-medium inline-flex items-center justify-center transition-all">
                                <i class="fas fa-times mr-2"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Landlord Information & Guidelines -->
            <div>
                <!-- Landlord Card -->
                <div class="card p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-tie mr-2"></i> Landlord Information
                    </h3>
                    
                    <div class="space-y-4">
                        <!-- Landlord Profile -->
                        <div class="flex items-center space-x-4 p-4 rounded-lg" style="background-color: rgba(var(--bg-secondary-rgb), 0.5);">
                            <div class="flex-shrink-0">
                                <div class="w-16 h-16 rounded-full flex items-center justify-center avatar-lg"
                                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    <i class="fas fa-user-tie text-2xl"></i>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold" style="color: var(--text-primary);">{{ $landlord->name }}</h4>
                                <p class="text-sm" style="color: var(--text-secondary);">Property Owner</p>
                                <div class="flex items-center mt-1">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs badge-primary">
                                        <i class="fas fa-check-circle mr-1"></i> Verified Landlord
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contact Details -->
                        <div class="space-y-3">
                            @if($landlord->email)
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div>
                                    <p class="text-sm" style="color: var(--text-secondary);">Email</p>
                                    <a href="mailto:{{ $landlord->email }}" 
                                       class="font-medium hover:underline" style="color: var(--primary);">
                                        {{ $landlord->email }}
                                    </a>
                                </div>
                            </div>
                            @endif
                            
                            @if($landlord->phone)
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div>
                                    <p class="text-sm" style="color: var(--text-secondary);">Phone</p>
                                    <a href="tel:{{ $landlord->phone }}" 
                                       class="font-medium hover:underline" style="color: var(--primary);">
                                        {{ $landlord->phone }}
                                    </a>
                                </div>
                            </div>
                            @endif
                            
                            @if($landlord->created_at)
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div>
                                    <p class="text-sm" style="color: var(--text-secondary);">Member Since</p>
                                    <p class="font-medium" style="color: var(--text-primary);">
                                        {{ $landlord->created_at->format('M Y') }}
                                    </p>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                
                <!-- Guidelines Card -->
                <div class="card p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-lightbulb mr-2"></i> Communication Guidelines
                    </h3>
                    
                    <div class="space-y-4">
                        <!-- Priority Guidelines -->
                        <div>
                            <h4 class="font-medium mb-2 flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-flag mr-2 text-sm"></i> Priority Levels
                            </h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex items-start">
                                    <i class="fas fa-circle text-blue-500 mt-1 mr-2 text-xs"></i>
                                    <div>
                                        <span class="font-medium" style="color: var(--text-primary);">Low:</span>
                                        <span style="color: var(--text-secondary);"> General inquiries, non-urgent matters</span>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <i class="fas fa-circle text-green-500 mt-1 mr-2 text-xs"></i>
                                    <div>
                                        <span class="font-medium" style="color: var(--text-primary);">Normal:</span>
                                        <span style="color: var(--text-secondary);"> Standard requests, maintenance issues</span>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <i class="fas fa-circle text-yellow-500 mt-1 mr-2 text-xs"></i>
                                    <div>
                                        <span class="font-medium" style="color: var(--text-primary);">High:</span>
                                        <span style="color: var(--text-secondary);"> Urgent repairs, payment issues</span>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <i class="fas fa-circle text-red-500 mt-1 mr-2 text-xs"></i>
                                    <div>
                                        <span class="font-medium" style="color: var(--text-primary);">Urgent:</span>
                                        <span style="color: var(--text-secondary);"> Emergencies only (fire, flood, security)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tips -->
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h4 class="font-medium mb-2 flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-tips mr-2 text-sm"></i> Tips for Effective Communication
                            </h4>
                            <ul class="space-y-2 text-sm" style="color: var(--text-secondary);">
                                <li class="flex items-start">
                                    <i class="fas fa-check text-green-500 mt-0.5 mr-2 text-xs"></i>
                                    <span>Be clear and specific about your request</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-green-500 mt-0.5 mr-2 text-xs"></i>
                                    <span>Include relevant details (unit number, dates)</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-green-500 mt-0.5 mr-2 text-xs"></i>
                                    <span>Use appropriate priority levels</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-green-500 mt-0.5 mr-2 text-xs"></i>
                                    <span>Allow 24-48 hours for response</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-green-500 mt-0.5 mr-2 text-xs"></i>
                                    <span>For emergencies, call the provided phone number</span>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Response Time -->
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                                <i class="fas fa-clock text-lg mb-2" style="color: var(--warning);"></i>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">Expected Response Time</p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    <span class="font-bold" style="color: var(--warning);">24-48 hours</span> for normal priority
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide success and error messages after 5 seconds
    const successMessage = document.querySelector('.bg-green-100');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 5000);
    }
    
    const errorMessage = document.querySelector('.bg-red-100');
    if (errorMessage) {
        setTimeout(() => {
            errorMessage.style.display = 'none';
        }, 5000);
    }
    
    // Character counter for message textarea
    const messageTextarea = document.getElementById('message');
    const charCount = document.getElementById('charCount');
    
    if (messageTextarea && charCount) {
        // Update count on input
        messageTextarea.addEventListener('input', function() {
            charCount.textContent = this.value.length;
            
            // Add warning class if approaching limit
            if (this.value.length > 1900) {
                charCount.style.color = 'var(--danger)';
            } else if (this.value.length > 1500) {
                charCount.style.color = 'var(--warning)';
            } else {
                charCount.style.color = '';
            }
        });
        
        // Initialize count
        charCount.textContent = messageTextarea.value.length;
    }
    
    // Form validation and confirmation
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            const subject = document.getElementById('subject').value.trim();
            const message = document.getElementById('message').value.trim();
            const priority = document.querySelector('input[name="priority"]:checked')?.value || 'normal';
            
            // Basic validation
            if (!subject) {
                e.preventDefault();
                alert('Please enter a subject for your message.');
                document.getElementById('subject').focus();
                return false;
            }
            
            if (!message) {
                e.preventDefault();
                alert('Please enter your message.');
                document.getElementById('message').focus();
                return false;
            }
            
            // Confirm for urgent messages
            if (priority === 'urgent') {
                if (!confirm('⚠️ URGENT MESSAGE CONFIRMATION\n\nYou are about to send an URGENT message.\n\nUse this priority only for true emergencies.\n\nClick OK to proceed or Cancel to review.')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            // Show loading state
            const submitBtn = contactForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
                submitBtn.disabled = true;
            }
        });
    }
    
    // Priority selection animation
    const priorityRadios = document.querySelectorAll('input[name="priority"]');
    priorityRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            // Remove all active classes
            document.querySelectorAll('label[for*="priority"]').forEach(label => {
                label.style.boxShadow = 'none';
                label.style.transform = 'none';
            });
            
            // Add active class to selected
            if (this.checked) {
                const label = this.closest('label');
                label.style.boxShadow = '0 0 0 2px var(--primary)';
                label.style.transform = 'translateY(-2px)';
            }
        });
        
        // Initialize active state
        if (radio.checked) {
            const label = radio.closest('label');
            label.style.boxShadow = '0 0 0 2px var(--primary)';
            label.style.transform = 'translateY(-2px)';
        }
    });
    
    // Example message templates (quick fill)
    const exampleMessages = [
        { subject: 'Maintenance Request', message: 'Dear Landlord,\n\nI would like to report a maintenance issue in my unit.\n\nIssue: \nLocation: \nAdditional Details: \n\nThank you.' },
        { subject: 'Rent Payment Inquiry', message: 'Dear Landlord,\n\nI have a question regarding my rent payment.\n\nQuestion: \n\nPlease advise on the best way to proceed.\n\nThank you.' },
        { subject: 'Parking Space Issue', message: 'Dear Landlord,\n\nI\'m experiencing an issue with my assigned parking space.\n\nIssue: \n\nCould you please look into this matter?\n\nThank you.' }
    ];
    
    // Add example message button (optional feature)
    const addExampleButton = () => {
        const messageContainer = document.querySelector('.mb-6:has(#message)');
        if (messageContainer) {
            const exampleBtn = document.createElement('button');
            exampleBtn.type = 'button';
            exampleBtn.className = 'text-xs px-3 py-1 rounded-lg mt-2 inline-flex items-center';
            exampleBtn.style.backgroundColor = 'rgba(var(--info-rgb), 0.1)';
            exampleBtn.style.color = 'var(--info)';
            exampleBtn.style.border = '1px solid rgba(var(--info-rgb), 0.2)';
            exampleBtn.innerHTML = '<i class="fas fa-lightbulb mr-1"></i> Load Example';
            
            exampleBtn.addEventListener('click', function() {
                const randomExample = exampleMessages[Math.floor(Math.random() * exampleMessages.length)];
                if (confirm('Load an example message? This will replace your current message.')) {
                    document.getElementById('subject').value = randomExample.subject;
                    document.getElementById('message').value = randomExample.message;
                    document.getElementById('charCount').textContent = randomExample.message.length;
                }
            });
            
            messageContainer.appendChild(exampleBtn);
        }
    };
    
    // Uncomment to enable example button
    // addExampleButton();
});
</script>

<style>
/* Card styles */
.card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* Badge styles */
.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    border: 1px solid rgba(var(--primary-rgb), 0.2);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    display: inline-flex;
    align-items: center;
}

/* Button styles */
.btn-primary {
    background-color: var(--primary);
    color: white;
    transition: all 0.2s;
    border: none;
}

.btn-primary:hover {
    background-color: var(--primary-dark);
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: var(--secondary);
    color: white;
    transition: all 0.2s;
    border: none;
}

.btn-secondary:hover {
    background-color: var(--secondary-dark);
}

/* Avatar styles */
.avatar-lg {
    width: 64px;
    height: 64px;
}

/* Form input styles */
input[type="text"],
textarea {
    transition: border-color 0.2s, box-shadow 0.2s;
}

input[type="text"]:focus,
textarea:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Radio card selection */
input[type="radio"] {
    accent-color: var(--primary);
}

/* Success and error message styles */
.bg-green-100 {
    background-color: rgba(209, 250, 229, 0.9);
    border-color: rgba(16, 185, 129, 0.3);
}

.bg-red-100 {
    background-color: rgba(254, 226, 226, 0.9);
    border-color: rgba(239, 68, 68, 0.3);
}

/* Priority color indicators */
.fa-circle.text-blue-500 { color: #3b82f6; }
.fa-circle.text-green-500 { color: #10b981; }
.fa-circle.text-yellow-500 { color: #f59e0b; }
.fa-circle.text-red-500 { color: #ef4444; }

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-2.sm\:grid-cols-4,
    .grid.grid-cols-1.sm\:grid-cols-3 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .flex.flex-col.sm\:flex-row {
        flex-direction: column;
    }
    
    .flex.flex-col.sm\:flex-row.gap-3 > * {
        width: 100%;
    }
    
    .grid.grid-cols-1.sm\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
}

/* Animation for form submission */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

button[type="submit"]:hover {
    animation: pulse 2s infinite;
}

/* Loading spinner */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
@endsection
@endsection