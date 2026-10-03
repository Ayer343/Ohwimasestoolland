@extends('layouts.field')

@section('title', 'Settings - Field Agent')

@section('content')
<div class="settings-container">
    <!-- Page Header -->
    <div class="settings-header mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-cog mr-2" style="color: var(--primary);"></i>Settings
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Manage your profile, preferences, and account settings
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('field-agent.dashboard') }}" 
                   class="px-4 py-2 rounded-lg transition-all hover:shadow-md inline-flex items-center"
                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Sidebar Navigation -->
        <div class="settings-sidebar rounded-xl" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color); height: fit-content;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background: linear-gradient(135deg, var(--primary) 0%, #2563eb 100%);">
                        <i class="fas fa-user text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="font-semibold" style="color: var(--text-primary);">{{ $user->name }}</p>
                        <p class="text-xs" style="color: var(--text-secondary);">{{ $user->email }}</p>
                    </div>
                </div>
            </div>
            <div class="p-2">
                <button class="settings-tab-btn w-full text-left px-4 py-3 rounded-lg transition-all active" 
                        data-tab="profile" style="color: var(--text-primary);">
                    <i class="fas fa-user mr-3" style="color: var(--primary);"></i> Profile Information
                </button>
                <button class="settings-tab-btn w-full text-left px-4 py-3 rounded-lg transition-all" 
                        data-tab="password" style="color: var(--text-primary);">
                    <i class="fas fa-lock mr-3" style="color: var(--primary);"></i> Change Password
                </button>
                <button class="settings-tab-btn w-full text-left px-4 py-3 rounded-lg transition-all" 
                        data-tab="preferences" style="color: var(--text-primary);">
                    <i class="fas fa-sliders-h mr-3" style="color: var(--primary);"></i> Preferences
                </button>
                <button class="settings-tab-btn w-full text-left px-4 py-3 rounded-lg transition-all" 
                        data-tab="notifications" style="color: var(--text-primary);">
                    <i class="fas fa-bell mr-3" style="color: var(--primary);"></i> Notifications
                </button>
                <button class="settings-tab-btn w-full text-left px-4 py-3 rounded-lg transition-all" 
                        data-tab="security" style="color: var(--text-primary);">
                    <i class="fas fa-shield-alt mr-3" style="color: var(--primary);"></i> Security
                </button>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="settings-content lg:col-span-2">
            <!-- Profile Information Tab -->
            <div id="profile-tab" class="settings-tab-content active">
                <div class="rounded-xl" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                    <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-user mr-2" style="color: var(--primary);"></i>Profile Information
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Update your personal information and contact details
                        </p>
                    </div>
                    <div class="p-6">
                        <form action="{{ route('field-agent.profile.update') }}" method="POST">
                            @csrf
                            @method('PUT')
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                                       class="w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                                @error('name')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                                       class="w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                                @error('email')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Phone Number
                                </label>
                                <input type="tel" name="phone" value="{{ old('phone', $user->phone ?? '') }}"
                                       class="w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                                       placeholder="+1234567890">
                                @error('phone')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Address
                                </label>
                                <textarea name="address" rows="3"
                                          class="w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                          style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                                          placeholder="Your physical address">{{ old('address', $user->address ?? '') }}</textarea>
                                @error('address')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex justify-end">
                                <button type="submit"
                                        class="px-6 py-2 rounded-lg transition-all hover:shadow-md"
                                        style="background-color: var(--primary); color: white;">
                                    <i class="fas fa-save mr-2"></i> Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Change Password Tab -->
            <div id="password-tab" class="settings-tab-content" style="display: none;">
                <div class="rounded-xl" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                    <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-lock mr-2" style="color: var(--primary);"></i>Change Password
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Ensure your account is using a strong password
                        </p>
                    </div>
                    <div class="p-6">
                        <form action="{{ route('field-agent.change-password') }}" method="POST">
                            @csrf
                            @method('PUT')
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Current Password <span class="text-red-500">*</span>
                                </label>
                                <input type="password" name="current_password" required
                                       class="w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                                @error('current_password')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    New Password <span class="text-red-500">*</span>
                                </label>
                                <input type="password" name="password" required
                                       class="w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Password must be at least 8 characters long
                                </p>
                                @error('password')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Confirm New Password <span class="text-red-500">*</span>
                                </label>
                                <input type="password" name="password_confirmation" required
                                       class="w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            </div>

                            <div class="flex justify-end">
                                <button type="submit"
                                        class="px-6 py-2 rounded-lg transition-all hover:shadow-md"
                                        style="background-color: var(--primary); color: white;">
                                    <i class="fas fa-key mr-2"></i> Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Preferences Tab -->
            <div id="preferences-tab" class="settings-tab-content" style="display: none;">
                <div class="rounded-xl" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                    <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-sliders-h mr-2" style="color: var(--primary);"></i>Preferences
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Customize your dashboard experience
                        </p>
                    </div>
                    <div class="p-6">
                        <form action="{{ route('field-agent.settings.update') }}" method="POST">
                            @csrf
                            @method('PUT')
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Items Per Page
                                </label>
                                <select name="items_per_page" 
                                        class="w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                                    <option value="10" {{ ($userPreferences['items_per_page'] ?? 20) == 10 ? 'selected' : '' }}>10 items</option>
                                    <option value="20" {{ ($userPreferences['items_per_page'] ?? 20) == 20 ? 'selected' : '' }}>20 items</option>
                                    <option value="50" {{ ($userPreferences['items_per_page'] ?? 20) == 50 ? 'selected' : '' }}>50 items</option>
                                    <option value="100" {{ ($userPreferences['items_per_page'] ?? 20) == 100 ? 'selected' : '' }}>100 items</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Default Registration Plan
                                </label>
                                <select name="default_plan_id" 
                                        class="w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                                    <option value="">-- Select default plan --</option>
                                    @foreach($registrationPlans ?? [] as $plan)
                                        <option value="{{ $plan->id }}" {{ ($userPreferences['default_plan_id'] ?? '') == $plan->id ? 'selected' : '' }}>
                                            {{ $plan->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit"
                                        class="px-6 py-2 rounded-lg transition-all hover:shadow-md"
                                        style="background-color: var(--primary); color: white;">
                                    <i class="fas fa-save mr-2"></i> Save Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Notifications Tab -->
            <div id="notifications-tab" class="settings-tab-content" style="display: none;">
                <div class="rounded-xl" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                    <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-bell mr-2" style="color: var(--primary);"></i>Notification Settings
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Control how you receive notifications
                        </p>
                    </div>
                    <div class="p-6">
                        <form action="{{ route('field-agent.settings.update') }}" method="POST">
                            @csrf
                            @method('PUT')
                            
                            <div class="mb-4 flex items-center justify-between">
                                <div>
                                    <label class="block text-sm font-medium" style="color: var(--text-primary);">
                                        Enable Notifications
                                    </label>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        Receive real-time notifications about your activities
                                    </p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="notifications_enabled" value="1" 
                                           class="sr-only peer"
                                           {{ ($userPreferences['notifications_enabled'] ?? true) ? 'checked' : '' }}>
                                    <div class="w-11 h-6 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all"
                                         style="background-color: {{ ($userPreferences['notifications_enabled'] ?? true) ? 'var(--primary)' : '#6b7280' }};"></div>
                                </label>
                            </div>

                            <div class="mb-4 flex items-center justify-between">
                                <div>
                                    <label class="block text-sm font-medium" style="color: var(--text-primary);">
                                        Daily Email Summary
                                    </label>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        Receive a daily summary of your activities via email
                                    </p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="email_daily_summary" value="1" 
                                           class="sr-only peer"
                                           {{ ($userPreferences['email_daily_summary'] ?? true) ? 'checked' : '' }}>
                                    <div class="w-11 h-6 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all"
                                         style="background-color: {{ ($userPreferences['email_daily_summary'] ?? true) ? 'var(--primary)' : '#6b7280' }};"></div>
                                </label>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit"
                                        class="px-6 py-2 rounded-lg transition-all hover:shadow-md"
                                        style="background-color: var(--primary); color: white;">
                                    <i class="fas fa-save mr-2"></i> Save Notification Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Security Tab -->
            <div id="security-tab" class="settings-tab-content" style="display: none;">
                <div class="rounded-xl" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                    <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-shield-alt mr-2" style="color: var(--primary);"></i>Security Settings
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Manage your account security
                        </p>
                    </div>
                    <div class="p-6">
                        <div class="mb-6">
                            <h4 class="font-medium mb-2" style="color: var(--text-primary);">Active Sessions</h4>
                            <div class="rounded-lg p-4" style="background-color: var(--bg-secondary);">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="text-sm" style="color: var(--text-primary);">
                                            <i class="fas fa-desktop mr-2"></i> Current Session
                                        </p>
                                        <p class="text-xs" style="color: var(--text-secondary);">
                                            {{ now()->format('F j, Y g:i A') }} - {{ request()->ip() }}
                                        </p>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full" style="background-color: var(--success); color: white;">
                                        Active
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-6">
                            <h4 class="font-medium mb-2" style="color: var(--text-primary);">Two-Factor Authentication</h4>
                            <div class="rounded-lg p-4" style="background-color: var(--bg-secondary);">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="text-sm" style="color: var(--text-primary);">
                                            <i class="fas fa-mobile-alt mr-2"></i> 2FA Status
                                        </p>
                                        <p class="text-xs" style="color: var(--text-secondary);">
                                            Add an extra layer of security to your account
                                        </p>
                                    </div>
                                    <button type="button" class="text-sm px-3 py-1 rounded-lg" 
                                            style="background-color: var(--primary); color: white;">
                                        Enable 2FA
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="border-t pt-4" style="border-color: var(--border-color);">
                            <h4 class="font-medium mb-2 text-red-500">Danger Zone</h4>
                            <div class="rounded-lg p-4" style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3);">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="text-sm font-medium" style="color: #ef4444;">
                                            <i class="fas fa-exclamation-triangle mr-2"></i> Logout All Devices
                                        </p>
                                        <p class="text-xs" style="color: var(--text-secondary);">
                                            This will log you out from all active sessions
                                        </p>
                                    </div>
                                    <button type="button" onclick="confirmLogoutAll()" 
                                            class="text-sm px-3 py-1 rounded-lg"
                                            style="background-color: #ef4444; color: white;">
                                        Logout All Devices
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    /* CSS Variables */
    :root {
        --primary: #3b82f6;
        --primary-rgb: 59, 130, 246;
        --success: #10b981;
        --success-rgb: 16, 185, 129;
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --card-bg: #ffffff;
        --bg-secondary: #f3f4f6;
        --border-color: #e5e7eb;
    }

    /* Dark mode support */
    @media (prefers-color-scheme: dark) {
        :root {
            --text-primary: #f9fafb;
            --text-secondary: #9ca3af;
            --card-bg: #1f2937;
            --bg-secondary: #374151;
            --border-color: #374151;
        }
    }

    .settings-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 1rem;
    }

    /* Settings Tab Buttons */
    .settings-tab-btn {
        transition: all 0.2s ease;
    }
    
    .settings-tab-btn:hover {
        background-color: rgba(var(--primary-rgb), 0.1);
    }
    
    .settings-tab-btn.active {
        background-color: rgba(var(--primary-rgb), 0.15);
        color: var(--primary) !important;
    }
    
    .settings-tab-btn.active i {
        color: var(--primary) !important;
    }

    /* Toggle Switch */
    .peer:checked ~ div {
        background-color: var(--primary);
    }
    
    .peer:checked ~ div:after {
        transform: translateX(100%);
    }
    
    .peer ~ div:after {
        transition: transform 0.2s ease;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .settings-container {
            padding: 0 0.5rem;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    // Tab Switching
    document.addEventListener('DOMContentLoaded', function() {
        // Get all tab buttons and content
        const tabBtns = document.querySelectorAll('.settings-tab-btn');
        const tabContents = document.querySelectorAll('.settings-tab-content');
        
        // Function to switch tabs
        function switchTab(tabId) {
            // Hide all tab contents
            tabContents.forEach(content => {
                content.style.display = 'none';
            });
            
            // Remove active class from all buttons
            tabBtns.forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            const selectedContent = document.getElementById(`${tabId}-tab`);
            if (selectedContent) {
                selectedContent.style.display = 'block';
            }
            
            // Add active class to clicked button
            const activeBtn = document.querySelector(`.settings-tab-btn[data-tab="${tabId}"]`);
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
        }
        
        // Add click event to all tab buttons
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                switchTab(tabId);
                
                // Update URL hash without scrolling
                window.location.hash = tabId;
            });
        });
        
        // Check URL hash for initial tab
        const hash = window.location.hash.substring(1);
        if (hash && ['profile', 'password', 'preferences', 'notifications', 'security'].includes(hash)) {
            switchTab(hash);
        }
        
        // Toggle switch styling
        const toggleSwitches = document.querySelectorAll('input[type="checkbox"]');
        toggleSwitches.forEach(toggle => {
            toggle.addEventListener('change', function() {
                const toggleDiv = this.nextElementSibling;
                if (this.checked) {
                    toggleDiv.style.backgroundColor = 'var(--primary)';
                } else {
                    toggleDiv.style.backgroundColor = '#6b7280';
                }
            });
        });
    });
    
    // Confirm logout all devices
    function confirmLogoutAll() {
        if (confirm('Are you sure you want to log out from all devices? You will need to log in again on all your devices.')) {
            fetch('{{ route("field-agent.logout-all-devices") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Logged out from all devices successfully.');
                    window.location.href = '{{ route("login") }}';
                } else {
                    alert('Failed to logout from all devices. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
    }
    
    // Show notification function (reuse from dashboard)
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 animate-slide-in';
        
        let bgColor = '#10b981';
        let icon = 'check-circle';
        
        switch(type) {
            case 'error':
                bgColor = '#ef4444';
                icon = 'exclamation-circle';
                break;
            case 'warning':
                bgColor = '#f59e0b';
                icon = 'exclamation-triangle';
                break;
            default:
                bgColor = '#10b981';
                icon = 'check-circle';
        }
        
        notification.style.backgroundColor = bgColor;
        notification.style.color = 'white';
        notification.innerHTML = `<div class="flex items-center"><i class="fas fa-${icon} mr-2"></i><span>${message}</span></div>`;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transition = 'opacity 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
</script>

<style>
    @keyframes slide-in {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .animate-slide-in {
        animation: slide-in 0.3s ease-out;
    }
</style>
@endpush

@endsection