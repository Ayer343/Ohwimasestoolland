{{-- developer/super-admins/partials/super-admin-card.blade.php --}}
@php
    // Prepare user data for display
    $user = $superAdmin;
    $initials = $user->initials ?? 'SA';
    $hasPhoto = $user->has_photo ?? false;
    $photoUrl = $hasPhoto ? $user->photo_url ?? '#' : null;
    $displayStatus = $user->display_status ?? ['label' => 'Unknown', 'class' => 'bg-gray-100 text-gray-800'];
    $emailVerified = $user->email_verified_at ? true : false;
    $phoneVerified = $user->phone_verified_at ? true : false;
    $lastActive = $user->last_active ?? 'Never';
    $creatorName = $user->creator_name ?? 'System';
    
    // Check for active invitation
    $hasActiveInvitation = $user->invitations->where('status', 'sent')
        ->where('expires_at', '>', now())
        ->isNotEmpty();
    
    // Status colors for cards
    $statusColors = [
        'active' => ['bg' => 'bg-green-50', 'text' => 'text-green-700', 'border' => 'border-green-200'],
        'pending' => ['bg' => 'bg-yellow-50', 'text' => 'text-yellow-700', 'border' => 'border-yellow-200'],
        'suspended' => ['bg' => 'bg-red-50', 'text' => 'text-red-700', 'border' => 'border-red-200'],
        'inactive' => ['bg' => 'bg-gray-50', 'text' => 'text-gray-700', 'border' => 'border-gray-200']
    ];
    
    $statusColor = $statusColors[strtolower($user->status)] ?? $statusColors['inactive'];
@endphp

<div class="super-admin-card card hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1"
     data-user-id="{{ $user->id }}"
     data-status="{{ $user->status }}"
     style="border: 1px solid var(--border-color);">
    
    <!-- Card Header -->
    <div class="relative">
        <!-- Status Indicator -->
        <div class="absolute top-3 right-3">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor['bg'] }} {{ $statusColor['text'] }} border {{ $statusColor['border'] }} capitalize">
                <i class="fas fa-circle mr-1.5 text-xs opacity-70"></i>
                {{ ucfirst($user->status) }}
            </span>
        </div>
        
        <!-- User Info -->
        <div class="p-5 pb-3">
            <div class="flex items-start space-x-4">
                <!-- Avatar -->
                <div class="flex-shrink-0">
                    @if($hasPhoto && $photoUrl)
                    <img class="h-16 w-16 rounded-full object-cover border-4 border-white shadow-lg"
                         src="{{ $photoUrl }}" 
                         alt="{{ $user->name }}"
                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIHZpZXdCb3g9IjAgMCA2NCA2NCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjY0IiBoZWlnaHQ9IjY0IiByeD0iMzIiIGZpbGw9IiNlNWU3ZWYiLz4KPHBhdGggZD0iTTMyIDM3QzM3LjUyMjggMzcgNDIgMzIuNTIyOCA0MiAyN0M0MiAyMS40NzcyIDM3LjUyMjggMTcgMzIgMTdDMjYuNDc3MiAxNyAyMiAyMS40NzcyIDIyIDI3QzIyIDMyLjUyMjggMjYuNDc3MiAzNyAzMiAzN1oiIGZpbGw9IiM5Q0EwQkIiLz4KPHBhdGggZD0iTTMyIDQwQzIzLjE2MzQgNDAgMTYgNDcuMTYzNCAxNiA1NkMxNiA1OC4yMDkxIDE3Ljc5MDkgNjAgMjAgNjBINDRDMjYuMjA5MSA2MCAyOCA1OC4yMDkxIDI4IDU2QzI4IDQ3LjE2MzQgMjAuODM2NiA0MCAzMiA0MFoiIGZpbGw9IiM5Q0EwQkIiLz4KPC9zdmc+'">
                    @else
                    <div class="h-16 w-16 rounded-full flex items-center justify-center text-white font-semibold text-lg shadow-lg"
                         style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        {{ $initials }}
                    </div>
                    @endif
                </div>
                
                <!-- Name and Basic Info -->
                <div class="flex-1 min-w-0">
                    <h3 class="text-lg font-semibold text-gray-900 truncate group-hover:text-primary-600 transition-colors duration-200">
                        {{ $user->name }}
                    </h3>
                    
                    <!-- Super Admin Badge -->
                    <div class="mt-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
                            <i class="fas fa-crown mr-1 text-xs"></i> Super Admin
                        </span>
                    </div>
                    
                    <!-- Additional Info -->
                    <div class="mt-2 space-y-1">
                        @if($user->username)
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-at mr-2 text-gray-400"></i>
                            <span class="truncate">{{ $user->username }}</span>
                        </div>
                        @endif
                        
                        @if($user->gender)
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-{{ $user->gender == 'male' ? 'mars' : ($user->gender == 'female' ? 'venus' : 'transgender') }} mr-2 text-gray-400"></i>
                            <span>{{ ucfirst($user->gender) }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Card Body -->
    <div class="px-5 pb-5">
        <!-- Contact Information -->
        <div class="space-y-3 mb-4">
            <!-- Email -->
            <div class="flex items-center justify-between group">
                <div class="flex items-center flex-1 min-w-0">
                    <i class="fas fa-envelope text-gray-400 mr-3"></i>
                    <a href="mailto:{{ $user->email }}" 
                       class="text-sm text-gray-900 hover:text-primary-600 hover:underline truncate"
                       title="{{ $user->email }}">
                        {{ $user->email }}
                    </a>
                </div>
                @if($emailVerified)
                <span class="ml-2 flex-shrink-0 inline-flex items-center p-1 rounded-full bg-green-100 text-green-600"
                      title="Email Verified">
                    <i class="fas fa-check text-xs"></i>
                </span>
                @else
                <span class="ml-2 flex-shrink-0 inline-flex items-center p-1 rounded-full bg-yellow-100 text-yellow-600"
                      title="Email Not Verified">
                    <i class="fas fa-exclamation text-xs"></i>
                </span>
                @endif
            </div>
            
            <!-- Phone -->
            @if($user->phone)
            <div class="flex items-center justify-between group">
                <div class="flex items-center flex-1 min-w-0">
                    <i class="fas fa-phone text-gray-400 mr-3"></i>
                    <a href="tel:{{ $user->phone }}" 
                       class="text-sm text-gray-900 hover:text-primary-600 hover:underline truncate"
                       title="{{ $user->phone }}">
                        {{ $user->local_phone ?? $user->phone }}
                    </a>
                </div>
                @if($phoneVerified)
                <span class="ml-2 flex-shrink-0 inline-flex items-center p-1 rounded-full bg-green-100 text-green-600"
                      title="Phone Verified">
                    <i class="fas fa-check text-xs"></i>
                </span>
                @else
                <span class="ml-2 flex-shrink-0 inline-flex items-center p-1 rounded-full bg-yellow-100 text-yellow-600"
                      title="Phone Not Verified">
                    <i class="fas fa-exclamation text-xs"></i>
                </span>
                @endif
            </div>
            @endif
            
            <!-- Location Info -->
            @if($user->region || $user->location)
            <div class="flex items-center text-sm text-gray-600">
                <i class="fas fa-map-marker-alt text-gray-400 mr-3"></i>
                <span class="truncate">
                    {{ $user->region ?? '' }}{{ $user->region && $user->location ? ', ' : '' }}{{ $user->location ?? '' }}
                </span>
            </div>
            @endif
        </div>
        
        <!-- Active Invitation Indicator -->
        @if($hasActiveInvitation)
        <div class="mb-4">
            <div class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium bg-blue-100 text-blue-800 border border-blue-200 animate-pulse">
                <i class="fas fa-envelope-open-text mr-2"></i>
                Active Invitation
            </div>
        </div>
        @endif
        
        <!-- Metadata -->
        <div class="border-t border-gray-200 pt-4 mt-4">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <!-- Created -->
                <div class="space-y-1">
                    <div class="text-gray-500 flex items-center">
                        <i class="fas fa-calendar-plus mr-2"></i>
                        Created
                    </div>
                    <div class="text-gray-900 font-medium">
                        {{ $user->created_at->format('M j, Y') }}
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ $user->age ?? $user->created_at->diffForHumans() }}
                    </div>
                </div>
                
                <!-- Last Active -->
                <div class="space-y-1">
                    <div class="text-gray-500 flex items-center">
                        <i class="fas fa-clock mr-2"></i>
                        Last Active
                    </div>
                    <div class="text-gray-900 font-medium">
                        {{ $lastActive }}
                    </div>
                    <div class="text-xs text-gray-500">
                        @if($user->last_login_at)
                        {{ $user->last_login_at->format('H:i') }}
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Created By -->
            <div class="mt-3 pt-3 border-t border-gray-100 text-sm text-gray-600">
                <div class="flex items-center">
                    <i class="fas fa-user-edit mr-2 text-gray-400"></i>
                    <span>Created by: <span class="font-medium">{{ $creatorName }}</span></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Card Footer - Actions -->
    <div class="px-5 py-4 bg-gray-50 rounded-b-lg border-t border-gray-200">
        <div class="flex items-center justify-between">
            <!-- Quick Actions -->
            <div class="flex space-x-2">
                <!-- View -->
                <a href="{{ route('developer.super-admins.show', $user->id) }}"
                   class="action-btn view-btn p-2 rounded-lg transition-all duration-200 hover:scale-105"
                   title="View Details"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    <i class="fas fa-eye"></i>
                </a>
                
                <!-- Edit -->
                <a href="{{ route('developer.super-admins.edit', $user->id) }}"
                   class="action-btn edit-btn p-2 rounded-lg transition-all duration-200 hover:scale-105"
                   title="Edit"
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                    <i class="fas fa-edit"></i>
                </a>
                
                <!-- Send Invitation -->
                @if(!$hasActiveInvitation)
                <button type="button"
                        onclick="showInvitationModal('{{ $user->id }}')"
                        class="action-btn invite-btn p-2 rounded-lg transition-all duration-200 hover:scale-105"
                        title="Send Invitation"
                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-paper-plane"></i>
                </button>
                @else
                <button type="button"
                        onclick="resendInvitation('{{ $user->id }}', '{{ $user->name }}')"
                        class="action-btn resend-btn p-2 rounded-lg transition-all duration-200 hover:scale-105"
                        title="Resend Invitation"
                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-redo"></i>
                </button>
                @endif
            </div>
            
            <!-- More Actions Dropdown -->
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open"
                        @click.outside="open = false"
                        class="action-btn more-btn p-2 rounded-lg transition-all duration-200 hover:scale-105"
                        title="More Actions"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                
                <div x-show="open" 
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute right-0 bottom-full mb-1 w-48 rounded-lg shadow-lg z-10"
                     style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                    <div class="py-1">
                        <!-- Change Password -->
                        <a href="{{ route('developer.super-admins.change-password', $user->id) }}"
                           class="dropdown-item group">
                            <i class="fas fa-key text-xs mr-2 text-gray-400 group-hover:text-primary-500"></i>
                            Change Password
                        </a>
                        
                        <!-- View Activities -->
                        <a href="{{ route('developer.super-admins.activities', $user->id) }}"
                           class="dropdown-item group">
                            <i class="fas fa-history text-xs mr-2 text-gray-400 group-hover:text-primary-500"></i>
                            View Activities
                        </a>
                        
                        <div class="border-t my-1" style="border-color: var(--border-color);"></div>
                        
                        <!-- Status Actions -->
                        @if($user->status !== 'active')
                        <button type="button"
                                onclick="activateUser('{{ $user->id }}', '{{ $user->name }}')"
                                class="dropdown-item group text-green-600">
                            <i class="fas fa-check-circle text-xs mr-2"></i>
                            Activate
                        </button>
                        @endif
                        
                        @if($user->status !== 'suspended' && $user->id !== auth()->id())
                        <button type="button"
                                onclick="suspendUser('{{ $user->id }}', '{{ $user->name }}')"
                                class="dropdown-item group text-yellow-600">
                            <i class="fas fa-pause-circle text-xs mr-2"></i>
                            Suspend
                        </button>
                        @endif
                        
                        @if($user->status !== 'inactive' && $user->id !== auth()->id())
                        <button type="button"
                                onclick="deactivateUser('{{ $user->id }}', '{{ $user->name }}')"
                                class="dropdown-item group text-gray-600">
                            <i class="fas fa-minus-circle text-xs mr-2"></i>
                            Deactivate
                        </button>
                        @endif
                        
                        <div class="border-t my-1" style="border-color: var(--border-color);"></div>
                        
                        <!-- Delete -->
                        @if($user->id !== auth()->id())
                        <button type="button"
                                onclick="checkRelations('{{ $user->id }}')"
                                class="dropdown-item group text-red-600">
                            <i class="fas fa-trash text-xs mr-2"></i>
                            Delete
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bulk Selection Checkbox (Bottom Right) -->
        <div class="absolute bottom-3 left-3">
            <input type="checkbox" 
                   name="user_ids[]" 
                   value="{{ $user->id }}" 
                   class="user-checkbox bulk-select-checkbox h-5 w-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 cursor-pointer transition-all duration-200"
                   onchange="updateSelectedCount()">
        </div>
    </div>
</div>

@push('styles')
<style>
.super-admin-card {
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.super-admin-card:hover {
    border-color: var(--primary) !important;
}

.super-admin-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.super-admin-card:hover::before {
    opacity: 1;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.5rem;
    transition: all 0.2s ease;
    cursor: pointer;
    border: 1px solid transparent;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.view-btn:hover {
    background-color: rgba(var(--info-rgb), 0.2) !important;
    border-color: rgba(var(--info-rgb), 0.3) !important;
}

.edit-btn:hover {
    background-color: rgba(var(--warning-rgb), 0.2) !important;
    border-color: rgba(var(--warning-rgb), 0.3) !important;
}

.invite-btn:hover {
    background-color: rgba(var(--success-rgb), 0.2) !important;
    border-color: rgba(var(--success-rgb), 0.3) !important;
}

.resend-btn:hover {
    background-color: rgba(var(--primary-rgb), 0.2) !important;
    border-color: rgba(var(--primary-rgb), 0.3) !important;
}

.more-btn:hover {
    background-color: rgba(var(--secondary-rgb), 0.2) !important;
    border-color: rgba(var(--secondary-rgb), 0.3) !important;
}

.dropdown-item {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
    background: none;
    border: none;
    text-align: left;
}

.dropdown-item:hover {
    background-color: rgba(var(--primary-rgb), 0.05);
    color: var(--primary);
}

/* Avatar animation */
.rounded-full {
    transition: transform 0.3s ease;
}

.super-admin-card:hover .rounded-full {
    transform: rotate(5deg) scale(1.05);
}

/* Verification badges */
.bg-green-100, .bg-yellow-100 {
    transition: all 0.2s ease;
}

.bg-green-100:hover {
    background-color: #d1fae5 !important;
}

.bg-yellow-100:hover {
    background-color: #fef3c7 !important;
}

/* Status badge glow */
@keyframes statusGlow {
    0%, 100% {
        box-shadow: 0 0 0 rgba(34, 197, 94, 0);
    }
    50% {
        box-shadow: 0 0 10px rgba(34, 197, 94, 0.5);
    }
}

.bg-green-50 {
    animation: statusGlow 3s infinite;
}

/* Mobile responsiveness */
@media (max-width: 640px) {
    .super-admin-card {
        margin-bottom: 1rem;
    }
    
    .action-btn {
        width: 2rem;
        height: 2rem;
    }
    
    .action-btn i {
        font-size: 0.875rem;
    }
}
</style>
@endpush