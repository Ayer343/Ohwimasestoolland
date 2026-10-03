@extends('layouts.app')

@section('title', $user->name . ' - User Details')

@php
    /*
     * ✅ FIX: Compute profile completion in the view as a fallback.
     *
     * `UserRepository::getUserStatistics()` doesn't return
     * `profile_completion` for every user type — only tenant, security,
     * sanitation, and contractor. Field agents, landlords, admins, and
     * super admins get 0% on the "Profile Complete" card because the key
     * is missing and `?? 0` kicks in.
     *
     * To make the card correct for every user type, compute it here using
     * the same field list as the repository, and prefer the repository's
     * value when it exists.
     */
    $profileCompletionFields = [
        'name'            => !empty($user->name),
        'email'           => !empty($user->email) && !is_null($user->email_verified_at),
        'phone'           => !empty($user->phone) && !is_null($user->phone_verified_at),
        'digital_address' => !empty($user->digital_address),
        'region'          => !empty($user->region),
        'location'        => !empty($user->location),
        'gender'          => !empty($user->gender),
        'dob'             => !empty($user->dob),
        'photo'           => !empty($user->photo),
    ];
    $completedFields = count(array_filter($profileCompletionFields));
    $totalFields     = count($profileCompletionFields);
    $computedProfileCompletion = $totalFields > 0
        ? (int) round(($completedFields / $totalFields) * 100)
        : 0;

    // Prefer the repository's value when present (so future backend
    // changes stay authoritative), otherwise use the computed fallback.
    $profileCompletion = isset($userStats['profile_completion'])
        ? (int) $userStats['profile_completion']
        : $computedProfileCompletion;
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center space-x-4">
                <!-- Profile Photo or Initials -->
                <div class="relative">
                    @if($user->has_photo)
                        <img src="{{ $user->photo_url }}" alt="{{ $user->name }}"
                             class="w-16 h-16 rounded-full object-cover border-4"
                             style="border-color: {{ $user->isFieldAgent() ? 'var(--info)' : ($user->isLandlord() ? 'var(--success)' : ($user->isTenant() ? 'var(--warning)' : 'var(--primary)')) }};">
                    @else
                        <div class="w-16 h-16 rounded-full flex items-center justify-center font-semibold text-white text-xl"
                             style="background-color: {{ $user->isFieldAgent() ? 'var(--info)' : ($user->isLandlord() ? 'var(--success)' : ($user->isTenant() ? 'var(--warning)' : 'var(--primary)')) }};">
                            {{ $user->getInitials() }}
                        </div>
                    @endif
                    @if($user->has_photo)
                        <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-green-500 rounded-full border-2 border-white flex items-center justify-center">
                            <i class="fas fa-camera text-white text-xs"></i>
                        </div>
                    @endif
                </div>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">{{ $user->name }}</h2>
                    <div class="flex items-center space-x-3 mt-1">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium
                            {{ $user->isSuperAdmin() ? 'bg-purple-100 text-purple-800' :
                               ($user->isAdmin() ? 'bg-blue-100 text-blue-800' :
                               ($user->isFieldAgent() ? 'bg-cyan-100 text-cyan-800' :
                               ($user->isLandlord() ? 'bg-green-100 text-green-800' :
                               ($user->isTenant() ? 'bg-yellow-100 text-yellow-800' :
                               'bg-gray-100 text-gray-800')))) }}">
                            <i class="fas fa-{{ $user->isFieldAgent() ? 'user-check' : ($user->isLandlord() ? 'home' : ($user->isTenant() ? 'user-friends' : 'user-shield')) }} mr-1"></i>
                            {{ $user->type_name }}
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium
                            {{ $user->status === 'active' ? 'bg-green-100 text-green-800' :
                               ($user->status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                               ($user->status === 'suspended' ? 'bg-red-100 text-red-800' :
                               'bg-gray-100 text-gray-800')) }}">
                            <i class="fas fa-{{ $user->status === 'active' ? 'check-circle' : ($user->status === 'pending' ? 'clock' : ($user->status === 'suspended' ? 'ban' : 'minus-circle')) }} mr-1"></i>
                            {{ $user->status_with_color['label'] ?? ucfirst($user->status) }}
                        </span>
                        @if($user->is_phone_verified)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                            <i class="fas fa-shield-alt mr-1"></i> Phone Verified
                        </span>
                        @endif
                        @if($user->has_photo)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800">
                            <i class="fas fa-camera mr-1"></i> Has Photo
                        </span>
                        @endif
                        @if($user->id === auth()->id())
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                <i class="fas fa-user mr-1"></i> You
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                @if($smsStatus['system_ready'] ?? false)
                <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium"
                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-comment-alt mr-1"></i>
                    SMS Ready
                </div>
                @endif
                <div class="flex space-x-2">
                    @if($user->id !== auth()->id())
                        <a href="{{ route('admin.users.edit', $user->id) }}" class="btn-primary">
                            <i class="fas fa-edit mr-2"></i> Edit
                        </a>
                    @else
                        <span class="btn-primary opacity-50 cursor-not-allowed" title="Cannot edit your own account">
                            <i class="fas fa-edit mr-2"></i> Edit
                        </span>
                    @endif
                    <a href="{{ route('admin.users.index') }}" class="btn-secondary">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="delete-modal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-lg max-w-md w-full" style="background-color: var(--bg-primary);">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-center mb-2" style="color: var(--text-primary);">Delete User Account</h3>
                <p class="text-sm text-center mb-6" style="color: var(--text-secondary);">
                    Are you sure you want to delete <span class="font-semibold">{{ $user->name }}</span>? This action will move the user to trash and cannot be undone.
                </p>

                <form id="delete-form" method="POST" action="{{ route('admin.users.destroy', $user->id) }}">
                    @csrf
                    @method('DELETE')
                    <div class="mb-4">
                        <label for="deletion_reason" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Reason for deletion (optional):</label>
                        <textarea id="deletion_reason" name="deletion_reason" rows="2"
                                  class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-red-500"
                                  style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);"
                                  placeholder="Provide a reason for deletion..."></textarea>
                    </div>

                    <div id="critical-relations-alert" class="hidden mb-4 p-4 rounded border border-red-300 bg-red-50">
                        <h4 class="font-semibold text-red-800 mb-1">⚠️ Warning: Critical Relations Found</h4>
                        <p class="text-sm text-red-700 mb-2">This user has critical relations that may prevent deletion:</p>
                        <ul id="critical-relations-list" class="text-sm text-red-700 list-disc list-inside"></ul>
                        <p class="text-sm text-red-700 mt-2">Consider deactivating the account instead.</p>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeDeleteModal()" class="btn-secondary px-4 py-2">Cancel</button>
                        <button type="submit" id="delete-confirm-btn" class="btn-danger px-4 py-2">
                            <i class="fas fa-trash mr-2"></i> Delete User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - User Information -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Profile Photo Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Profile Photo</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Photo Display -->
                    <div class="md:col-span-1 flex flex-col items-center">
                        <div class="relative mb-4">
                            <div class="w-32 h-32 rounded-full border-4 overflow-hidden"
                                 style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                @if($user->has_photo)
                                    <img id="user-photo" src="{{ $user->photo_url }}" alt="{{ $user->name }}"
                                         class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center">
                                        <i class="fas fa-user text-5xl" style="color: var(--text-secondary);"></i>
                                    </div>
                                @endif
                            </div>
                            @if($user->has_photo)
                                <div class="absolute -bottom-2 -right-2 w-6 h-6 bg-green-500 rounded-full border-2 border-white flex items-center justify-center">
                                    <i class="fas fa-check text-white text-xs"></i>
                                </div>
                            @endif
                        </div>
                        <div class="text-center">
                            <span class="text-sm" style="color: var(--text-secondary);">
                                @if($user->has_photo)
                                    Photo uploaded
                                @else
                                    No profile photo
                                @endif
                            </span>
                        </div>
                    </div>

                    <!-- Photo Actions -->
                    <div class="md:col-span-2 space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Photo Actions</label>
                            <div class="flex flex-wrap gap-2">
                                @if($user->has_photo)
                                    <button onclick="viewPhoto('{{ $user->photo_url }}')" class="btn-info">
                                        <i class="fas fa-eye mr-2"></i> View Full Size
                                    </button>
                                    <button onclick="downloadPhoto('{{ $user->photo_url }}', '{{ $user->name }}')" class="btn-secondary">
                                        <i class="fas fa-download mr-2"></i> Download
                                    </button>
                                    @if($user->id !== auth()->id())
                                        <button onclick="removePhoto({{ $user->id }})" class="btn-warning">
                                            <i class="fas fa-trash mr-2"></i> Remove Photo
                                        </button>
                                    @endif
                                @else
                                    <span class="text-sm" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        No photo available. Upload one in the edit section.
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Photo Information -->
                        @if($user->has_photo)
                        <div class="p-4 rounded border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: var(--border-color);">
                            <h4 class="font-semibold mb-2 text-sm" style="color: var(--text-primary);">Photo Information:</h4>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span style="color: var(--text-secondary);">File Name:</span>
                                    <div style="color: var(--text-primary);" class="font-mono truncate">{{ $user->photo }}</div>
                                </div>
                                <div>
                                    <span style="color: var(--text-secondary);">Status:</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-check mr-1"></i> Uploaded
                                    </span>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Basic Information Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Basic Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Email Address</label>
                            <div class="flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-envelope mr-2 text-gray-400"></i>
                                {{ $user->email }}
                                @if($user->email_verified_at)
                                    <i class="fas fa-check-circle ml-2 text-green-500" title="Email verified"></i>
                                @endif
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Phone Number</label>
                            <div class="flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-phone mr-2 text-gray-400"></i>
                                {{ $user->phone ?? 'Not set' }}
                                @if($user->is_phone_verified)
                                    <i class="fas fa-check-circle ml-2 text-green-500" title="Phone verified"></i>
                                @elseif($user->phone)
                                    <i class="fas fa-exclamation-triangle ml-2 text-yellow-500" title="Phone not verified"></i>
                                @endif
                            </div>
                        </div>
                        @if($user->username)
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Username</label>
                            <div class="flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-at mr-2 text-gray-400"></i>
                                {{ $user->username }}
                            </div>
                        </div>
                        @endif
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Account Created</label>
                            <div class="flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-calendar-plus mr-2 text-gray-400"></i>
                                {{ $user->created_at->format('M j, Y g:i A') }}
                            </div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">
                                by {{ $user->creator->name ?? 'System' }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Last Login</label>
                            <div class="flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-sign-in-alt mr-2 text-gray-400"></i>
                                @if($user->last_login_at)
                                    {{ \Carbon\Carbon::parse($user->last_login_at)->format('M j, Y g:i A') }}
                                @else
                                    Never logged in
                                @endif
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Last Activity</label>
                            <div class="flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-clock mr-2 text-gray-400"></i>
                                @if($user->last_activity_at)
                                    {{ \Carbon\Carbon::parse($user->last_activity_at)->diffForHumans() }}
                                @else
                                    No recent activity
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Information Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Additional Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        @if($user->gender)
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Gender</label>
                            <div style="color: var(--text-primary);" class="capitalize">{{ $user->gender }}</div>
                        </div>
                        @endif
                        @if($user->dob)
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Date of Birth</label>
                            <div style="color: var(--text-primary);">
                                {{ \Carbon\Carbon::parse($user->dob)->format('M j, Y') }}
                                ({{ \Carbon\Carbon::parse($user->dob)->age }} years old)
                            </div>
                        </div>
                        @endif
                    </div>
                    <div class="space-y-4">
                        @if($user->region)
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Region</label>
                            <div style="color: var(--text-primary);">{{ $user->region }}</div>
                        </div>
                        @endif
                        @if($user->location)
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Location</label>
                            <div style="color: var(--text-primary);">{{ $user->location }}</div>
                        </div>
                        @endif
                        @if($user->digital_address)
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Digital Address</label>
                            <div style="color: var(--text-primary);">{{ $user->digital_address }}</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Field Agent Specific Information -->
            @if($user->isFieldAgent())
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Field Agent Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Invitation Status</label>
                            <div class="flex items-center">
                                @if($user->has_accepted_invitation)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i> Accepted
                                    </span>
                                    <div class="text-sm ml-2" style="color: var(--text-secondary);">
                                        on {{ $user->invitation_accepted_at ? \Carbon\Carbon::parse($user->invitation_accepted_at)->format('M j, Y g:i A') : 'Unknown' }}
                                    </div>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-clock mr-1"></i> Pending
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Phone Verification</label>
                            <div class="flex items-center">
                                @if($user->is_phone_verified)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                        <i class="fas fa-shield-alt mr-1"></i> Verified
                                    </span>
                                    <div class="text-sm ml-2" style="color: var(--text-secondary);">
                                        on {{ $user->phone_verified_at ? \Carbon\Carbon::parse($user->phone_verified_at)->format('M j, Y g:i A') : 'Unknown' }}
                                    </div>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-orange-100 text-orange-800">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> Not Verified
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Last Invitation Sent</label>
                            <div style="color: var(--text-primary);">
                                @if($user->last_invitation_sent_at)
                                    {{ \Carbon\Carbon::parse($user->last_invitation_sent_at)->format('M j, Y g:i A') }}
                                @else
                                    Never sent
                                @endif
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Invitation Method</label>
                            <div style="color: var(--text-primary);" class="capitalize">
                                {{ $user->invitation_method ?? 'Not specified' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- User Statistics Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">User Statistics</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @if($user->isFieldAgent())
                        <div class="text-center p-4 rounded-lg stat-card" style="background-color: rgba(var(--info-rgb), 0.1);">
                            <div class="text-2xl font-bold" style="color: var(--info);">{{ $userStats['assigned_plans_count'] ?? 0 }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Assigned Plans</div>
                        </div>
                        <div class="text-center p-4 rounded-lg stat-card" style="background-color: rgba(var(--success-rgb), 0.1);">
                            <div class="text-2xl font-bold" style="color: var(--success);">{{ $userStats['completed_plans_count'] ?? 0 }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Completed</div>
                        </div>
                        <div class="text-center p-4 rounded-lg stat-card" style="background-color: rgba(var(--warning-rgb), 0.1);">
                            <div class="text-2xl font-bold" style="color: var(--warning);">{{ $userStats['active_plans_count'] ?? 0 }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Active</div>
                        </div>
                        <div class="text-center p-4 rounded-lg stat-card" style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <div class="text-2xl font-bold" style="color: var(--primary);">{{ $userStats['total_properties_registered'] ?? 0 }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Properties</div>
                        </div>
                    @elseif($user->isLandlord())
                        <div class="text-center p-4 rounded-lg stat-card" style="background-color: rgba(var(--success-rgb), 0.1);">
                            <div class="text-2xl font-bold" style="color: var(--success);">{{ $userStats['total_properties'] ?? 0 }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Properties</div>
                        </div>
                        <div class="text-center p-4 rounded-lg stat-card" style="background-color: rgba(var(--info-rgb), 0.1);">
                            <div class="text-2xl font-bold" style="color: var(--info);">{{ $userStats['active_properties'] ?? 0 }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Active</div>
                        </div>
                        <div class="text-center p-4 rounded-lg stat-card" style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <div class="text-2xl font-bold" style="color: var(--primary);">{{ $userStats['total_payments'] ?? 0 }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Payments</div>
                        </div>
                        <div class="text-center p-4 rounded-lg stat-card" style="background-color: rgba(var(--warning-rgb), 0.1);">
                            <div class="text-2xl font-bold" style="color: var(--warning);">GHS {{ number_format($userStats['total_payment_amount'] ?? 0, 2) }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Total Amount</div>
                        </div>
                    @else
                        {{-- ✅ FIX: Use the computed $profileCompletion for the
                             Profile Complete stat, so every user type shows a
                             correct percentage. --}}
                        <div class="text-center p-4 rounded-lg stat-card" style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <div class="text-2xl font-bold" style="color: var(--primary);">{{ $profileCompletion }}%</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Profile Complete</div>
                        </div>
                        <div class="text-center p-4 rounded-lg stat-card" style="background-color: rgba(var(--info-rgb), 0.1);">
                            <div class="text-2xl font-bold" style="color: var(--info);">{{ $userStats['member_since'] ?? 'N/A' }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Member Since</div>
                        </div>
                        <div class="text-center p-4 rounded-lg stat-card" style="background-color: rgba(var(--success-rgb), 0.1);">
                            <div class="text-2xl font-bold" style="color: var(--success);">
                                @if($user->has_photo) Yes @else No @endif
                            </div>
                            <div class="text-sm" style="color: var(--text-secondary);">Has Photo</div>
                        </div>
                        <div class="text-center p-4 rounded-lg stat-card" style="background-color: rgba(var(--warning-rgb), 0.1);">
                            <div class="text-2xl font-bold" style="color: var(--warning);">
                                @if($user->is_phone_verified) Yes @else No @endif
                            </div>
                            <div class="text-sm" style="color: var(--text-secondary);">Phone Verified</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right Column - Actions & Quick Stats -->
        <div class="space-y-6">
            <!-- Quick Actions Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Quick Actions</h3>
                <div class="space-y-3">
                    @if($user->isFieldAgent() && !$user->has_accepted_invitation && $user->id !== auth()->id())
                        <button onclick="sendInvitation({{ $user->id }})" class="btn-info w-full justify-center">
                            <i class="fas fa-paper-plane mr-2"></i> Send Invitation
                        </button>
                    @endif

                    @if($user->phone && !$user->is_phone_verified && $user->id !== auth()->id())
                        <button onclick="sendVerificationCode({{ $user->id }})" class="btn-warning w-full justify-center">
                            <i class="fas fa-sms mr-2"></i> Send Verification Code
                        </button>
                        <button onclick="verifyPhone({{ $user->id }})" class="btn-success w-full justify-center">
                            <i class="fas fa-check-circle mr-2"></i> Mark Phone Verified
                        </button>
                    @endif

                    <!-- Photo Management Actions -->
                    @if($user->has_photo)
                        @if($user->id !== auth()->id())
                            <button onclick="removePhoto({{ $user->id }})" class="btn-warning w-full justify-center">
                                <i class="fas fa-trash mr-2"></i> Remove Photo
                            </button>
                        @endif
                    @else
                        @if($user->id !== auth()->id())
                            <a href="{{ route('admin.users.edit', $user->id) }}#photo-preview" class="btn-info w-full justify-center">
                                <i class="fas fa-camera mr-2"></i> Upload Photo
                            </a>
                        @endif
                    @endif

                    @if($user->id !== auth()->id())
                        @if($user->status === 'active')
                            <button onclick="suspendUser({{ $user->id }})" class="btn-warning w-full justify-center">
                                <i class="fas fa-pause mr-2"></i> Suspend User
                            </button>
                        @elseif($user->status === 'suspended')
                            <button onclick="activateUser({{ $user->id }})" class="btn-success w-full justify-center">
                                <i class="fas fa-play mr-2"></i> Activate User
                            </button>
                        @elseif($user->status === 'pending')
                            <button onclick="activateUser({{ $user->id }})" class="btn-success w-full justify-center">
                                <i class="fas fa-check mr-2"></i> Activate User
                            </button>
                        @endif
                    @endif

                    @if($user->id !== auth()->id())
                        <button onclick="openDeleteModal({{ $user->id }})" class="btn-danger w-full justify-center">
                            <i class="fas fa-trash mr-2"></i> Delete User
                        </button>
                    @else
                        <span class="btn-danger w-full justify-center opacity-50 cursor-not-allowed" title="Cannot delete your own account">
                            <i class="fas fa-trash mr-2"></i> Delete User
                        </span>
                    @endif
                </div>
            </div>

            <!-- Performance Metrics (Field Agents) -->
            @if($user->isFieldAgent())
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Performance Metrics</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Performance Score</label>
                        <div class="flex items-center">
                            <div class="w-full bg-gray-200 rounded-full h-2.5 progress-bar">
                                <div class="h-2.5 rounded-full progress-fill"
                                     style="width: {{ $user->performance_metrics['completion_rate'] ?? 0 }}%; background-color: var(--success);"></div>
                            </div>
                            <span class="ml-2 text-sm font-medium" style="color: var(--text-primary);">
                                {{ $user->performance_metrics['completion_rate'] ?? 0 }}%
                            </span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div style="color: var(--text-secondary);">Assignments</div>
                            <div class="font-semibold" style="color: var(--text-primary);">{{ $user->performance_metrics['total_assignments'] ?? 0 }}</div>
                        </div>
                        <div>
                            <div style="color: var(--text-secondary);">Completed</div>
                            <div class="font-semibold" style="color: var(--text-primary);">{{ $user->performance_metrics['completed_assignments'] ?? 0 }}</div>
                        </div>
                        <div>
                            <div style="color: var(--text-secondary);">Active</div>
                            <div class="font-semibold" style="color: var(--text-primary);">{{ $user->performance_metrics['active_assignments'] ?? 0 }}</div>
                        </div>
                        <div>
                            <div style="color: var(--text-secondary);">Properties</div>
                            <div class="font-semibold" style="color: var(--text-primary);">{{ $user->performance_metrics['properties_registered'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- System Information Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">System Information</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">User ID:</span>
                        <span style="color: var(--text-primary);" class="font-mono">{{ $user->id }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Account Type:</span>
                        <span style="color: var(--text-primary);">{{ $user->type_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Profile Photo:</span>
                        <span style="color: var(--text-primary);">
                            @if($user->has_photo)
                                <span class="text-green-600">Yes</span>
                            @else
                                <span class="text-gray-500">No</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Created:</span>
                        <span style="color: var(--text-primary);">{{ $user->created_at->format('Y-m-d') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Updated:</span>
                        <span style="color: var(--text-primary);">{{ $user->updated_at->format('Y-m-d') }}</span>
                    </div>
                    @if($user->deleted_at)
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Deleted:</span>
                        <span style="color: var(--text-primary);">{{ \Carbon\Carbon::parse($user->deleted_at)->format('Y-m-d') }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Photo Modal -->
<div id="photo-modal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-hidden" style="background-color: var(--bg-primary);">
        <div class="flex justify-between items-center p-4 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">{{ $user->name }} - Profile Photo</h3>
            <button onclick="closePhotoModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-4 flex justify-center">
            <img id="modal-photo" src="" alt="Profile Photo" class="max-w-full max-h-[70vh] object-contain rounded">
        </div>
        <div class="flex justify-end space-x-3 p-4 border-t" style="border-color: var(--border-color);">
            <button onclick="closePhotoModal()" class="btn-secondary">Close</button>
            <button id="download-photo-btn" class="btn-primary">
                <i class="fas fa-download mr-2"></i> Download
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Delete User Functions
function openDeleteModal(userId) {
    fetch(`/admin/users/${userId}/check-relations`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        const modal = document.getElementById('delete-modal');
        const criticalAlert = document.getElementById('critical-relations-alert');
        const relationsList = document.getElementById('critical-relations-list');
        const deleteBtn = document.getElementById('delete-confirm-btn');
        const deleteForm = document.getElementById('delete-form');

        relationsList.innerHTML = '';
        criticalAlert.classList.add('hidden');

        if (data.success) {
            if (data.has_critical_relations) {
                criticalAlert.classList.remove('hidden');
                data.critical_relations.forEach(relation => {
                    const li = document.createElement('li');
                    li.textContent = relation;
                    relationsList.appendChild(li);
                });

                deleteBtn.disabled = true;
                deleteBtn.classList.add('opacity-50', 'cursor-not-allowed');
                deleteBtn.innerHTML = '<i class="fas fa-ban mr-2"></i> Cannot Delete (Has Relations)';
            } else {
                deleteBtn.disabled = false;
                deleteBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                deleteBtn.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete User';
            }
        }

        deleteForm.action = `/admin/users/${userId}`;
        modal.classList.remove('hidden');
    })
    .catch(error => {
        console.error('Error checking relations:', error);
        alert('Error checking user relations. Please try again.');
    });
}

function closeDeleteModal() {
    document.getElementById('delete-modal').classList.add('hidden');
}

document.getElementById('delete-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = this;
    const formData = new FormData(form);
    const deleteBtn = document.getElementById('delete-confirm-btn');

    deleteBtn.disabled = true;
    const originalText = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';

    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (response.ok) {
            return response.json();
        }
        throw new Error('Network response was not ok.');
    })
    .then(data => {
        if (data.success) {
            const modal = document.getElementById('delete-modal');
            modal.classList.add('hidden');

            showNotification('User deleted successfully! Redirecting...', 'success');

            setTimeout(() => {
                window.location.href = '{{ route('admin.users.index') }}';
            }, 2000);
        } else {
            showNotification(data.message || 'Error deleting user.', 'error');
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while deleting the user.', 'error');
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = originalText;
    });
});

function showNotification(message, type = 'info') {
    const existingNotifications = document.querySelectorAll('.custom-notification');
    existingNotifications.forEach(notification => notification.remove());

    const notification = document.createElement('div');
    notification.className = `custom-notification fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg animate-slide-in ${
        type === 'success' ? 'bg-green-100 border-green-400 text-green-700' :
        type === 'error' ? 'bg-red-100 border-red-400 text-red-700' :
        'bg-blue-100 border-blue-400 text-blue-700'
    } border`;

    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.classList.add('animate-slide-out');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

function viewPhoto(photoUrl) {
    const modal = document.getElementById('photo-modal');
    const modalPhoto = document.getElementById('modal-photo');
    const downloadBtn = document.getElementById('download-photo-btn');

    modalPhoto.src = photoUrl;
    downloadBtn.onclick = () => downloadPhoto(photoUrl, '{{ $user->name }}');
    modal.classList.remove('hidden');
}

function closePhotoModal() {
    document.getElementById('photo-modal').classList.add('hidden');
}

function downloadPhoto(photoUrl, userName) {
    const link = document.createElement('a');
    link.href = photoUrl;
    link.download = `${userName.replace(/\s+/g, '_')}_profile_photo.jpg`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function removePhoto(userId) {
    if (confirm('Are you sure you want to remove the profile photo? This action cannot be undone.')) {
        fetch(`/admin/users/${userId}/remove-photo`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Profile photo removed successfully!', 'success');
                location.reload();
            } else {
                showNotification('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while removing the photo.', 'error');
        });
    }
}

function sendInvitation(userId) {
    if (confirm('Send invitation to this field agent?')) {
        fetch(`/admin/users/${userId}/send-invitation`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Invitation sent successfully!', 'success');
                location.reload();
            } else {
                showNotification('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while sending the invitation.', 'error');
        });
    }
}

function sendVerificationCode(userId) {
    if (confirm('Send phone verification code to this user?')) {
        fetch(`/admin/users/${userId}/send-verification-code`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Verification code sent successfully!', 'success');
            } else {
                showNotification('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while sending the verification code.', 'error');
        });
    }
}

function verifyPhone(userId) {
    if (confirm('Mark this user\'s phone as verified?')) {
        fetch(`/admin/users/${userId}/verify-phone`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => {
            if (response.ok) {
                showNotification('Phone marked as verified successfully!', 'success');
                location.reload();
            } else {
                showNotification('Error verifying phone.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while verifying the phone.', 'error');
        });
    }
}

function activateUser(userId) {
    if (confirm('Activate this user account?')) {
        fetch(`/admin/users/${userId}/activate`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => {
            if (response.ok) {
                showNotification('User activated successfully!', 'success');
                location.reload();
            } else {
                showNotification('Error activating user.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while activating the user.', 'error');
        });
    }
}

function suspendUser(userId) {
    if (confirm('Suspend this user account?')) {
        fetch(`/admin/users/${userId}/suspend`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => {
            if (response.ok) {
                showNotification('User suspended successfully!', 'success');
                location.reload();
            } else {
                showNotification('Error suspending user.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while suspending the user.', 'error');
        });
    }
}

document.getElementById('delete-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

document.getElementById('photo-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePhotoModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
        closePhotoModal();
    }
});
</script>

<style>
.btn {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-info {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border: 1px solid rgba(var(--info-rgb), 0.3);
}

.btn-info:hover {
    background-color: rgba(var(--info-rgb), 0.2);
}

.btn-warning {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border: 1px solid rgba(var(--warning-rgb), 0.3);
}

.btn-warning:hover {
    background-color: rgba(var(--warning-rgb), 0.2);
}

.btn-success {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border: 1px solid rgba(var(--success-rgb), 0.3);
}

.btn-success:hover {
    background-color: rgba(var(--success-rgb), 0.2);
}

.btn-danger {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border: 1px solid rgba(var(--danger-rgb), 0.3);
}

.btn-danger:hover {
    background-color: rgba(var(--danger-rgb), 0.2);
}

.stat-card {
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.progress-bar {
    background-color: rgba(var(--secondary-rgb), 0.2);
    border-radius: 9999px;
    overflow: hidden;
}

.progress-fill {
    height: 0.5rem;
    border-radius: 9999px;
    transition: width 0.3s ease;
}

.fixed {
    backdrop-filter: blur(4px);
}

.btn-primary.opacity-50,
.btn-danger.opacity-50 {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-primary.opacity-50:hover,
.btn-danger.opacity-50:hover {
    transform: none;
    background-color: rgba(var(--primary-rgb), 0.1);
}

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

@keyframes slide-out {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

.animate-slide-in {
    animation: slide-in 0.3s ease-out;
}

.animate-slide-out {
    animation: slide-out 0.3s ease-in forwards;
}

@media (max-width: 1024px) {
    .grid.grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }

    .grid.grid-cols-2.md\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
@endsection