{{-- developer/super-admins/partials/super-admin-row.blade.php --}}
@php
    // Prepare user data for display
    $user = $superAdmin;
    $displayData = $user->display_data ?? [];
    $initials = $user->initials ?? 'SA';
    $hasPhoto = $user->has_photo ?? false;
    $photoUrl = $hasPhoto ? $user->photo_url ?? '#' : null;
    $displayStatus = $user->display_status ?? ['label' => 'Unknown', 'class' => 'bg-gray-100 text-gray-800'];
    $displayPhone = $user->display_phone ?? [
        'local_format' => $user->phone ?? 'Not set',
        'is_verified' => $user->phone_verified_at ? true : false,
        'status' => $user->phone_verified_at ? 'Verified' : 'Not Verified',
        'status_class' => $user->phone_verified_at ? 'text-green-600' : 'text-red-600'
    ];
    $emailVerified = $user->email_verified_at ? true : false;
    $lastActive = $user->last_active ?? 'Never';
    $age = $user->age ?? $user->created_at->diffForHumans() ?? 'N/A';
    $creatorName = $user->creator_name ?? 'System';
    
    // Status colors
    $statusColors = [
        'active' => 'text-green-600 bg-green-50 border-green-200',
        'pending' => 'text-yellow-600 bg-yellow-50 border-yellow-200',
        'suspended' => 'text-red-600 bg-red-50 border-red-200',
        'inactive' => 'text-gray-600 bg-gray-50 border-gray-200'
    ];
    
    $statusClass = $statusColors[strtolower($user->status)] ?? 'text-gray-600 bg-gray-50 border-gray-200';
    
    // Check for active invitation
    $hasActiveInvitation = $user->invitations->where('status', 'sent')
        ->where('expires_at', '>', now())
        ->isNotEmpty();
@endphp

<tr class="super-admin-row hover:bg-gray-50 transition-colors duration-200"
    data-user-id="{{ $user->id }}"
    data-status="{{ $user->status }}"
    data-invitation-active="{{ $hasActiveInvitation ? 'true' : 'false' }}">
    
    <!-- Bulk Selection Checkbox -->
    <td class="px-4 py-3 whitespace-nowrap">
        <div class="flex items-center">
            <input type="checkbox" 
                   name="user_ids[]" 
                   value="{{ $user->id }}" 
                   class="user-checkbox bulk-select-checkbox h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 cursor-pointer transition-all duration-200"
                   onchange="updateSelectedCount()">
        </div>
    </td>
    
    <!-- User Information -->
    <td class="px-4 py-3 whitespace-nowrap">
        <div class="flex items-center">
            <!-- Avatar/Initials -->
            <div class="flex-shrink-0 h-10 w-10">
                @if($hasPhoto && $photoUrl)
                <img class="h-10 w-10 rounded-full object-cover border-2 border-gray-200 shadow-sm hover:shadow-md transition-shadow duration-200" 
                     src="{{ $photoUrl }}" 
                     alt="{{ $user->name }}"
                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiByeD0iMjAiIGZpbGw9IiNlNWU3ZWYiLz4KPHBhdGggZD0iTTIwIDIzQzIzLjMxMzcgMjMgMjYgMjAuMzEzNyAyNiAxN0MyNiAxMy42ODYzIDIzLjMxMzcgMTEgMjAgMTFDMTYuNjg2MyAxMSAxNCAxMy42ODYzIDE0IDE3QzE0IDIwLjMxMzcgMTYuNjg2MyAyMyAyMCAyM1oiIGZpbGw9IiM5Q0EwQkIiLz4KPHBhdGggZD0iTTIwIDI1QzE0LjQ3NzEgMjUgMTAgMjkuNDc3MSAxMCAzNUMxMCAzNi4xMDQ2IDEwLjg5NTQgMzcgMTIgMzdIMjhDMjkuMTA0NiAzNyAzMCAzNi4xMDQ2IDMwIDM1QzMwIDI5LjQ3NzEgMjUuNTIyOSAyNSAyMCAyNVoiIGZpbGw9IiM5Q0EwQkIiLz4KPC9zdmc+'">
                @else
                <div class="h-10 w-10 rounded-full flex items-center justify-center text-white font-semibold text-sm shadow-sm hover:shadow-md transition-shadow duration-200"
                     style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    {{ $initials }}
                </div>
                @endif
            </div>
            
            <!-- User Details -->
            <div class="ml-4">
                <!-- Name with Role Badge -->
                <div class="flex items-center gap-2 mb-1">
                    <p class="text-sm font-medium text-gray-900 truncate max-w-xs hover:text-primary-600 transition-colors duration-200 cursor-default">
                        {{ $user->name }}
                    </p>
                    @if($user->is_super_admin ?? false)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
                        <i class="fas fa-crown mr-1 text-xs"></i> Super Admin
                    </span>
                    @endif
                </div>
                
                <!-- Additional Info -->
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                    <!-- Username -->
                    @if($user->username)
                    <span class="inline-flex items-center">
                        <i class="fas fa-at mr-1 text-gray-400"></i>
                        {{ $user->username }}
                    </span>
                    @endif
                    
                    <!-- Gender -->
                    @if($user->gender)
                    <span class="inline-flex items-center">
                        <i class="fas fa-{{ $user->gender == 'male' ? 'mars' : ($user->gender == 'female' ? 'venus' : 'transgender') }} mr-1 text-gray-400"></i>
                        {{ ucfirst($user->gender) }}
                    </span>
                    @endif
                    
                    <!-- Region -->
                    @if($user->region)
                    <span class="inline-flex items-center">
                        <i class="fas fa-map-marker-alt mr-1 text-gray-400"></i>
                        {{ $user->region }}
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </td>
    
    <!-- Contact Information -->
    <td class="px-4 py-3 whitespace-nowrap">
        <div class="space-y-1.5">
            <!-- Email with Verification Status -->
            <div class="flex items-center group">
                <a href="mailto:{{ $user->email }}" 
                   class="text-sm text-gray-900 hover:text-primary-600 hover:underline truncate max-w-xs transition-all duration-200 flex items-center"
                   title="Send email to {{ $user->email }}">
                    <i class="fas fa-envelope mr-2 text-gray-400 group-hover:text-primary-500 transition-colors duration-200"></i>
                    <span class="truncate">{{ $user->email }}</span>
                </a>
                @if($emailVerified)
                <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200"
                      title="Email Verified {{ $user->email_verified_at->format('M j, Y H:i') }}">
                    <i class="fas fa-check-circle mr-0.5 text-xs"></i>
                </span>
                @else
                <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200"
                      title="Email Not Verified">
                    <i class="fas fa-exclamation-circle mr-0.5 text-xs"></i>
                </span>
                @endif
            </div>
            
            <!-- Phone with Verification Status -->
            <div class="flex items-center group">
                @if($user->phone)
                <a href="tel:{{ $user->phone }}" 
                   class="text-sm text-gray-900 hover:text-primary-600 hover:underline truncate max-w-xs transition-all duration-200 flex items-center"
                   title="Call {{ $user->phone }}">
                    <i class="fas fa-phone mr-2 text-gray-400 group-hover:text-primary-500 transition-colors duration-200"></i>
                    <span class="truncate">{{ $displayPhone['local_format'] }}</span>
                </a>
                @if($displayPhone['is_verified'])
                <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200"
                      title="Phone Verified {{ $user->phone_verified_at->format('M j, Y H:i') ?? '' }}">
                    <i class="fas fa-check-circle mr-0.5 text-xs"></i>
                </span>
                @else
                <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200"
                      title="Phone Not Verified">
                    <i class="fas fa-exclamation-circle mr-0.5 text-xs"></i>
                </span>
                @endif
                @else
                <span class="text-sm text-gray-500 italic flex items-center">
                    <i class="fas fa-phone mr-2 text-gray-400"></i>
                    Not set
                </span>
                @endif
            </div>
            
            <!-- Digital Address (if available) -->
            @if($user->digital_address)
            <div class="flex items-center">
                <span class="text-xs text-gray-500 truncate max-w-xs flex items-center">
                    <i class="fas fa-qrcode mr-1.5 text-gray-400"></i>
                    {{ Str::limit($user->digital_address, 20) }}
                </span>
            </div>
            @endif
        </div>
    </td>
    
    <!-- Status -->
    <td class="px-4 py-3 whitespace-nowrap">
        <div class="space-y-2">
            <!-- Status Badge -->
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }} border capitalize">
                <i class="fas fa-circle mr-1.5 text-xs opacity-70"></i>
                {{ ucfirst($user->status) }}
            </span>
            
            <!-- Active Invitation Indicator -->
            @if($hasActiveInvitation)
            <div class="mt-1">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200 animate-pulse"
                      title="Active invitation pending">
                    <i class="fas fa-envelope-open-text mr-1 text-xs"></i>
                    Invitation Sent
                </span>
            </div>
            @endif
            
            <!-- Last Active -->
            <div class="text-xs text-gray-500 mt-1">
                <span class="flex items-center" title="Last active: {{ $user->last_login_at ? $user->last_login_at->format('M j, Y H:i') : 'Never' }}">
                    <i class="fas fa-clock mr-1 text-gray-400"></i>
                    {{ $lastActive }}
                </span>
            </div>
        </div>
    </td>
    
    <!-- Created & Creator -->
    <td class="px-4 py-3 whitespace-nowrap">
        <div class="space-y-1.5">
            <!-- Created Date -->
            <div class="text-sm text-gray-900 flex items-center">
                <i class="fas fa-calendar-plus mr-2 text-gray-400"></i>
                {{ $user->created_at->format('M j, Y') }}
            </div>
            
            <!-- Created Time -->
            <div class="text-xs text-gray-500 flex items-center">
                <i class="fas fa-clock mr-2 text-gray-400"></i>
                {{ $user->created_at->format('H:i') }}
            </div>
            
            <!-- Age -->
            <div class="text-xs text-gray-500 italic">
                <span title="Created {{ $user->created_at->diffForHumans() }}">
                    <i class="fas fa-history mr-1 text-gray-400"></i>
                    {{ $age }}
                </span>
            </div>
            
            <!-- Created By -->
            <div class="text-xs text-gray-600 mt-1 pt-1 border-t border-gray-100">
                <span class="flex items-center" title="Created by {{ $creatorName }}">
                    <i class="fas fa-user-edit mr-1.5 text-gray-400"></i>
                    By: {{ $creatorName }}
                </span>
            </div>
        </div>
    </td>
    
    <!-- Actions -->
    <td class="px-4 py-3 whitespace-nowrap">
        <div class="flex items-center space-x-1">
            <!-- View Button -->
            <a href="{{ route('developer.super-admins.show', $user->id) }}"
               class="action-btn view-btn p-2 rounded-lg transition-all duration-200 hover:scale-105"
               title="View Details"
               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                <i class="fas fa-eye text-sm"></i>
            </a>
            
            <!-- Edit Button -->
            <a href="{{ route('developer.super-admins.edit', $user->id) }}"
               class="action-btn edit-btn p-2 rounded-lg transition-all duration-200 hover:scale-105"
               title="Edit Super Admin"
               style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                <i class="fas fa-edit text-sm"></i>
            </a>
            
            <!-- Actions Dropdown -->
            <div class="relative" x-data="{ open: false }">
                <!-- Dropdown Toggle -->
                <button @click="open = !open"
                        @click.outside="open = false"
                        class="action-btn more-btn p-2 rounded-lg transition-all duration-200 hover:scale-105"
                        title="More Actions"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    <i class="fas fa-ellipsis-v text-sm"></i>
                </button>
                
                <!-- Dropdown Menu -->
                <div x-show="open" 
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute right-0 mt-1 w-48 rounded-lg shadow-lg z-10"
                     style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                    <div class="py-1">
                        <!-- Change Password -->
                        <a href="{{ route('developer.super-admins.change-password', $user->id) }}"
                           class="dropdown-item group">
                            <i class="fas fa-key text-xs mr-2 text-gray-400 group-hover:text-primary-500"></i>
                            Change Password
                        </a>
                        
                        <!-- Send/Resend Invitation -->
                        @if(!$hasActiveInvitation)
                        <button type="button"
                                onclick="showInvitationModal('{{ $user->id }}')"
                                class="dropdown-item group">
                            <i class="fas fa-paper-plane text-xs mr-2 text-gray-400 group-hover:text-primary-500"></i>
                            Send Invitation
                        </button>
                        @else
                        <button type="button"
                                onclick="resendInvitation('{{ $user->id }}', '{{ $user->name }}')"
                                class="dropdown-item group">
                            <i class="fas fa-redo text-xs mr-2 text-gray-400 group-hover:text-primary-500"></i>
                            Resend Invitation
                        </button>
                        @endif
                        
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
                        
                        <!-- Verification Actions -->
                        @if(!$emailVerified)
                        <button type="button"
                                onclick="verifyEmail('{{ $user->id }}', '{{ $user->name }}')"
                                class="dropdown-item group text-blue-600">
                            <i class="fas fa-envelope text-xs mr-2"></i>
                            Verify Email
                        </button>
                        @endif
                        
                        @if($user->phone && !$displayPhone['is_verified'])
                        <button type="button"
                                onclick="verifyPhone('{{ $user->id }}', '{{ $user->name }}')"
                                class="dropdown-item group text-blue-600">
                            <i class="fas fa-phone text-xs mr-2"></i>
                            Verify Phone
                        </button>
                        @endif
                        
                        <div class="border-t my-1" style="border-color: var(--border-color);"></div>
                        
                        <!-- Delete Action -->
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
    </td>
</tr>

@push('styles')
<style>
.super-admin-row {
    animation: fadeIn 0.3s ease;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
    transition: all 0.2s ease;
    cursor: pointer;
    border: 1px solid transparent;
}

.action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.view-btn:hover {
    background-color: rgba(var(--info-rgb), 0.2) !important;
    border-color: rgba(var(--info-rgb), 0.3) !important;
}

.edit-btn:hover {
    background-color: rgba(var(--warning-rgb), 0.2) !important;
    border-color: rgba(var(--warning-rgb), 0.3) !important;
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

.dropdown-item i {
    transition: all 0.2s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Tooltip styles */
[title] {
    position: relative;
}

[title]:hover::after {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background-color: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    white-space: nowrap;
    z-index: 1000;
    margin-bottom: 0.5rem;
    pointer-events: none;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .action-btn {
        width: 1.75rem;
        height: 1.75rem;
    }
    
    .action-btn i {
        font-size: 0.75rem;
    }
}

/* Hover effects for rows */
.super-admin-row:hover {
    background-color: rgba(var(--primary-rgb), 0.02) !important;
}

/* Status badge animations */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.animate-pulse {
    animation: pulse 2s infinite;
}

/* Loading state for checkboxes */
.bulk-select-checkbox:checked {
    animation: checkmark 0.3s ease;
}

@keyframes checkmark {
    0% {
        transform: scale(0.8);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
    }
}

/* Avatar hover effects */
.rounded-full:hover {
    transform: rotate(5deg) scale(1.05);
    transition: transform 0.3s ease;
}
</style>
@endpush

@push('scripts')
<script>
// Row-specific interactions
document.addEventListener('DOMContentLoaded', function() {
    // Add click event to entire row (except checkboxes and action buttons)
    document.querySelectorAll('.super-admin-row').forEach(row => {
        row.addEventListener('click', function(e) {
            // Don't trigger if clicking on checkbox, action buttons, or links
            if (e.target.closest('.bulk-select-checkbox') || 
                e.target.closest('.action-btn') ||
                e.target.closest('a') ||
                e.target.closest('button') ||
                e.target.closest('.dropdown')) {
                return;
            }
            
            // Navigate to view page
            const userId = this.dataset.userId;
            window.location.href = `/developer/super-admins/${userId}`;
        });
        
        // Add hover class to row
        row.addEventListener('mouseenter', function() {
            this.classList.add('bg-gray-50');
        });
        
        row.addEventListener('mouseleave', function() {
            this.classList.remove('bg-gray-50');
        });
    });
    
    // Initialize tooltips
    initRowTooltips();
});

function initRowTooltips() {
    // Use native title attributes or implement a tooltip library
    // For now, browser default tooltips are sufficient
}

// Export row data for debugging
function exportRowData(row) {
    const data = {
        id: row.dataset.userId,
        status: row.dataset.status,
        hasActiveInvitation: row.dataset.invitationActive === 'true'
    };
    console.log('Row Data:', data);
    return data;
}
</script>
@endpush