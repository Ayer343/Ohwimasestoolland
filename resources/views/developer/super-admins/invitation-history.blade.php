{{-- developer/super-admins/invitation-history.blade.php --}}
@php
    $layout = 'layouts.dev';
    $routePrefix = 'developer.super-admins';
    $pageTitle = 'Invitation History - Developer Portal';
    
    $user = $user ?? null;
    if (!$user) {
        abort(404, 'Super Admin not found');
    }
    
    $invitations = $user->invitations ?? collect();
    
    // Status colors and labels
    $statusColors = [
        'sent' => 'warning',
        'accepted' => 'success',
        'expired' => 'danger',
        'failed' => 'danger',
        'cancelled' => 'secondary',
        'pending' => 'warning',
    ];
    
    $statusLabels = [
        'sent' => 'Sent',
        'accepted' => 'Accepted',
        'expired' => 'Expired',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'pending' => 'Pending',
    ];
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="flex items-center mb-4 md:mb-0">
                <div class="mr-4">
                    @if($user->photo)
                        <img src="{{ Storage::url($user->photo) }}" 
                             alt="{{ $user->name }}" 
                             class="w-16 h-16 rounded-full border-2"
                             style="border-color: var(--border-color);">
                    @else
                        <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                            <span class="text-xl">{{ substr($user->name, 0, 2) }}</span>
                        </div>
                    @endif
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2" style="color: var(--primary);"></i> 
                        Invitation History
                        <span class="ml-2 status-indicator {{ 'status-' . $user->status }}">
                            {{ $user->display_status['label'] ?? $user->status }}
                        </span>
                    </h2>
                    <div class="text-sm flex flex-wrap items-center mt-1 gap-2" style="color: var(--text-secondary);">
                        <span class="flex items-center">
                            <i class="fas fa-user-shield mr-1"></i>
                            {{ $user->name }}
                        </span>
                        <span>•</span>
                        <span class="flex items-center">
                            <i class="fas fa-envelope mr-1"></i>
                            {{ $user->email }}
                        </span>
                        <span>•</span>
                        <span class="flex items-center">
                            <i class="fas fa-paper-plane mr-1"></i>
                            {{ $invitations->count() }} invitation(s)
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('developer.super-admins.show', $user->id) }}" 
                   class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Details
                </a>
                @if($user->email && in_array($user->status, [\App\Models\User::STATUS_PENDING, \App\Models\User::STATUS_ACTIVE]))
                <button onclick="showInvitationModal({{ $user->id }})" 
                        class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium text-white btn-primary">
                    <i class="fas fa-paper-plane mr-2"></i> Send New Invitation
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Invitations Card -->
    <div class="card">
        <div class="p-6">
            <!-- Statistics -->
            @php
                $stats = [
                    'total' => $invitations->count(),
                    'sent' => $invitations->where('status', 'sent')->count(),
                    'accepted' => $invitations->where('status', 'accepted')->count(),
                    'expired' => $invitations->where('status', 'expired')->count(),
                    'failed' => $invitations->where('status', 'failed')->count(),
                    'pending' => $invitations->where('status', 'pending')->count(),
                ];
            @endphp
            
            <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
                <div class="text-center p-4 rounded-lg" 
                     style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                    <p class="text-2xl font-bold mb-1" style="color: var(--text-primary);">
                        {{ $stats['total'] }}
                    </p>
                    <p class="text-xs" style="color: var(--text-secondary);">Total</p>
                </div>
                
                <div class="text-center p-4 rounded-lg" 
                     style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                    <p class="text-2xl font-bold mb-1" style="color: var(--text-primary);">
                        {{ $stats['sent'] }}
                    </p>
                    <p class="text-xs" style="color: var(--text-secondary);">Sent</p>
                </div>
                
                <div class="text-center p-4 rounded-lg" 
                     style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                    <p class="text-2xl font-bold mb-1" style="color: var(--text-primary);">
                        {{ $stats['accepted'] }}
                    </p>
                    <p class="text-xs" style="color: var(--text-secondary);">Accepted</p>
                </div>
                
                <div class="text-center p-4 rounded-lg" 
                     style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.1);">
                    <p class="text-2xl font-bold mb-1" style="color: var(--text-primary);">
                        {{ $stats['expired'] }}
                    </p>
                    <p class="text-xs" style="color: var(--text-secondary);">Expired</p>
                </div>
                
                <div class="text-center p-4 rounded-lg" 
                     style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.1);">
                    <p class="text-2xl font-bold mb-1" style="color: var(--text-primary);">
                        {{ $stats['failed'] }}
                    </p>
                    <p class="text-xs" style="color: var(--text-secondary);">Failed</p>
                </div>
                
                <div class="text-center p-4 rounded-lg" 
                     style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                    <p class="text-2xl font-bold mb-1" style="color: var(--text-primary);">
                        {{ $stats['pending'] }}
                    </p>
                    <p class="text-xs" style="color: var(--text-secondary);">Pending</p>
                </div>
            </div>

            @if($invitations->isEmpty())
                <!-- Empty State -->
                <div class="text-center py-12">
                    <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-envelope text-2xl" style="color: var(--primary);"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                        No invitations sent yet
                    </h4>
                    <p class="mb-6 max-w-md mx-auto text-sm" style="color: var(--text-secondary);">
                        No invitations have been sent to this Super Admin yet. You can send the first invitation.
                    </p>
                    @if($user->email && in_array($user->status, [\App\Models\User::STATUS_PENDING, \App\Models\User::STATUS_ACTIVE]))
                    <button onclick="showInvitationModal({{ $user->id }})" 
                            class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                        <i class="fas fa-paper-plane mr-2"></i> Send First Invitation
                    </button>
                    @else
                    <p class="text-sm italic" style="color: var(--text-secondary);">
                        User cannot receive invitations (missing email or wrong status)
                    </p>
                    @endif
                </div>
            @else
                <!-- Invitations Table -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr>
                                <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Date</th>
                                <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Channel</th>
                                <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Status</th>
                                <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Expires</th>
                                <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invitations as $invitation)
                            <tr>
                                <td class="p-3">
                                    <div class="text-sm font-medium" style="color: var(--text-primary);">
                                        {{ $invitation->created_at->format('M d, Y') }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $invitation->created_at->format('g:i A') }}
                                        @if($invitation->sent_at)
                                        <br>
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            Sent: {{ $invitation->sent_at->format('M d, g:i A') }}
                                        </span>
                                        @endif
                                    </div>
                                </td>
                                
                                <td class="p-3">
                                    <div class="flex items-center">
                                        <span class="px-2 py-1 text-xs rounded-full flex items-center" 
                                              style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                            <i class="fas fa-envelope mr-1"></i> Email
                                        </span>
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $invitation->send_attempts ?? 0 }} attempt(s)
                                    </div>
                                </td>
                                
                                <td class="p-3">
                                    @php
                                        $statusColor = $statusColors[$invitation->status] ?? 'secondary';
                                        $statusLabel = $statusLabels[$invitation->status] ?? ucfirst($invitation->status);
                                    @endphp
                                    <span class="px-2 py-1 text-xs rounded-full inline-flex items-center" 
                                          style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.1); color: var(--{{ $statusColor }}); border: 1px solid rgba(var(--{{ $statusColor }}-rgb), 0.3);">
                                        <i class="fas fa-circle mr-1" style="font-size: 8px;"></i>
                                        {{ $statusLabel }}
                                    </span>
                                    @if($invitation->failure_reason && $invitation->status == 'failed')
                                    <div class="text-xs mt-1" style="color: var(--danger);">
                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                        {{ Str::limit($invitation->failure_reason, 30) }}
                                    </div>
                                    @endif
                                </td>
                                
                                <td class="p-3">
                                    <div class="text-sm" style="color: var(--text-primary);">
                                        {{ $invitation->expires_at->format('M d, Y') }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        @if($invitation->expires_at->isPast())
                                            <span style="color: var(--danger);">
                                                <i class="fas fa-clock mr-1"></i>
                                                Expired {{ $invitation->expires_at->diffForHumans() }}
                                            </span>
                                        @else
                                            <span style="color: var(--warning);">
                                                <i class="fas fa-clock mr-1"></i>
                                                Expires in {{ $invitation->expires_at->diffForHumans() }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                
                                <td class="p-3">
                                    <div class="flex items-center space-x-2">
                                        @if($invitation->token && in_array($invitation->status, ['sent', 'pending']) && !$invitation->expires_at->isPast())
                                        <button onclick="copyInvitationUrl('{{ route('invitation.accept', ['token' => $invitation->token]) }}')" 
                                                class="text-xs px-3 py-1 rounded flex items-center"
                                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);"
                                                data-tooltip="Copy Invitation URL">
                                            <i class="fas fa-copy mr-1"></i> Copy
                                        </button>
                                        
                                        <button onclick="resendInvitation({{ $user->id }})" 
                                                class="text-xs px-3 py-1 rounded flex items-center"
                                                style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);"
                                                data-tooltip="Resend Invitation">
                                            <i class="fas fa-redo mr-1"></i> Resend
                                        </button>
                                        @endif
                                        
                                        <button onclick="viewInvitationDetails({{ $user->id }}, {{ $invitation->id }})" 
                                                class="text-xs px-3 py-1 rounded flex items-center"
                                                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);"
                                                data-tooltip="View Details">
                                            <i class="fas fa-eye mr-1"></i> View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Last Invitation Info -->
                @php
                    $latestInvitation = $invitations->first();
                    $hasActiveInvitation = $latestInvitation && 
                                          in_array($latestInvitation->status, ['sent', 'pending']) && 
                                          $latestInvitation->expires_at > now();
                @endphp
                
                @if($hasActiveInvitation)
                <div class="mt-6 p-4 rounded-lg" 
                     style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-3" style="color: var(--warning);"></i>
                            <div>
                                <h4 class="text-sm font-medium" style="color: var(--text-primary);">
                                    Active Invitation
                                </h4>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    User has an active invitation that expires 
                                    {{ $latestInvitation->expires_at->diffForHumans() }}
                                    ({{ $latestInvitation->expires_at->format('M d, Y g:i A') }})
                                </p>
                            </div>
                        </div>
                        <button onclick="copyInvitationUrl('{{ route('invitation.accept', ['token' => $latestInvitation->token]) }}')"
                                class="text-xs px-3 py-1 rounded-lg flex items-center"
                                style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-copy mr-1"></i> Copy URL
                        </button>
                    </div>
                </div>
                @endif
            @endif
        </div>
    </div>
</div>

<!-- Send Invitation Modal -->
<div id="invitationModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideInvitationModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-paper-plane mr-2" style="color: var(--primary);"></i> Send Invitation
                </h3>
                <button type="button" onclick="hideInvitationModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="invitationForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <!-- Invitation Info -->
                    <div class="mb-4 p-3 rounded" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <div class="flex items-center text-sm" style="color: var(--text-primary);">
                            <i class="fas fa-user mr-2" style="color: var(--primary);"></i>
                            <span>Super Admin: <strong>{{ $user->name }}</strong></span>
                        </div>
                        <div class="flex items-center text-sm mt-1" style="color: var(--text-primary);">
                            <i class="fas fa-envelope mr-2" style="color: var(--primary);"></i>
                            <span>{{ $user->email }}</span>
                        </div>
                    </div>
                    
                    <!-- Expiry Days -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i> 
                            Expires In (Days)
                        </label>
                        <input type="number" 
                               name="expires_in_days" 
                               value="7" 
                               min="1" 
                               max="30"
                               class="index-custom-input w-full"
                               required>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Number of days before invitation expires (1-30)
                        </p>
                    </div>
                    
                    <!-- Custom Message -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-comment-alt mr-2" style="color: var(--primary);"></i> 
                            Custom Message (Optional)
                        </label>
                        <textarea name="custom_message" 
                                  rows="4" 
                                  class="index-custom-textarea"
                                  placeholder="Add a personal message to the invitation email..."></textarea>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            This message will be included in the invitation email
                        </p>
                    </div>
                    
                    <!-- Email Configuration Status -->
                    @php
                        $emailConfigStatus = app(\App\Services\DeveloperEmailService::class)->checkConfigurationStatus();
                    @endphp
                    
                    @if(!$emailConfigStatus['can_send'])
                    <div class="mb-4 p-3 rounded-lg" 
                         style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i>
                            <span class="text-sm font-medium" style="color: var(--danger);">
                                Email Configuration Required
                            </span>
                        </div>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            {{ $emailConfigStatus['message'] ?? 'Email configuration is not set up.' }}
                        </p>
                        <a href="{{ route('developer.email-settings.index') }}" 
                           class="text-xs mt-2 inline-flex items-center text-blue-600 hover:text-blue-800">
                            <i class="fas fa-cog mr-1"></i> Configure Email Settings
                        </a>
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideInvitationModal()" 
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="btn-primary px-4 py-2 rounded-lg font-medium text-white"
                            {{ !$emailConfigStatus['can_send'] ? 'disabled' : '' }}>
                        <i class="fas fa-paper-plane mr-2"></i> Send Invitation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Invitation Details Modal -->
<div id="invitationDetailsModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideInvitationDetailsModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Invitation Details
                </h3>
                <button type="button" onclick="hideInvitationDetailsModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="invitationDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="hideInvitationDetailsModal()" 
                        class="btn-secondary px-4 py-2 rounded-lg font-medium">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function showInvitationModal(userId) {
    const modal = document.getElementById('invitationModal');
    const form = document.getElementById('invitationForm');
    
    // Set form action
    form.action = `/developer/super-admins/${userId}/send-invitation`;
    form.reset();
    
    // Reset to default values
    form.querySelector('[name="expires_in_days"]').value = 7;
    form.querySelector('[name="custom_message"]').value = '';
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideInvitationModal() {
    const modal = document.getElementById('invitationModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function copyInvitationUrl(url) {
    navigator.clipboard.writeText(url).then(() => {
        showToast('Invitation URL copied to clipboard!', 'success');
    }).catch(err => {
        console.error('Failed to copy:', err);
        showToast('Failed to copy URL.', 'error');
    });
}

function resendInvitation(userId) {
    if (confirm('Resend invitation to this user?')) {
        // Simple form submission
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/developer/super-admins/${userId}/resend-invitation`;
        form.style.display = 'none';
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';
        
        form.appendChild(csrfToken);
        document.body.appendChild(form);
        form.submit();
    }
}

function viewInvitationDetails(userId, invitationId) {
    // Show loading state
    const content = document.getElementById('invitationDetailsContent');
    content.innerHTML = `
        <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2" 
                 style="border-color: var(--primary);"></div>
            <p class="mt-2 text-sm" style="color: var(--text-secondary);">
                Loading invitation details...
            </p>
        </div>
    `;
    
    // Show modal immediately with loading state
    const modal = document.getElementById('invitationDetailsModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Fetch details - FIXED URL with both userId and invitationId
    fetch(`/developer/super-admins/${userId}/invitations/${invitationId}/details`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                content.innerHTML = data.html;
                
                // Re-attach copy functionality to the new button
                const copyBtn = content.querySelector('button[onclick*="copyInvitationUrl"]');
                if (copyBtn) {
                    const oldOnclick = copyBtn.getAttribute('onclick');
                    copyBtn.removeAttribute('onclick');
                    copyBtn.addEventListener('click', function() {
                        const urlMatch = oldOnclick.match(/copyInvitationUrl\('([^']+)'\)/);
                        if (urlMatch && urlMatch[1]) {
                            copyInvitationUrl(urlMatch[1]);
                        }
                    });
                }
            } else {
                content.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-3xl mb-3" style="color: var(--danger);"></i>
                        <p class="text-sm" style="color: var(--text-primary);">
                            ${data.message || 'Failed to load invitation details.'}
                        </p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-3xl mb-3" style="color: var(--danger);"></i>
                    <p class="text-sm" style="color: var(--text-primary);">
                        Network error. Please try again.
                    </p>
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        ${error.message}
                    </p>
                </div>
            `;
        });
}

function hideInvitationDetailsModal() {
    const modal = document.getElementById('invitationDetailsModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// Toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium transform transition-all duration-300 translate-y-0 opacity-100';
    
    switch(type) {
        case 'success':
            toast.style.backgroundColor = 'var(--success)';
            break;
        case 'error':
            toast.style.backgroundColor = 'var(--danger)';
            break;
        case 'warning':
            toast.style.backgroundColor = 'var(--warning)';
            break;
        default:
            toast.style.backgroundColor = 'var(--info)';
    }
    
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.transform = 'translateY(-100%)';
        toast.style.opacity = '0';
        setTimeout(() => {
            if (toast.parentNode) {
                document.body.removeChild(toast);
            }
        }, 300);
    }, 3000);
}

// Close modals with Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        hideInvitationModal();
        hideInvitationDetailsModal();
    }
});

// Auto-hide flash messages
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 500);
    });
}, 5000);
</script>

<style>
/* Invitation history specific styles */
.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-indicator::before {
    content: '';
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    display: inline-block;
}

.status-active {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.status-active::before {
    background-color: var(--success);
}

.status-pending {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
}

.status-pending::before {
    background-color: var(--warning);
}

/* Table styles */
table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

table th {
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.75rem;
    padding: 0.75rem;
    border-bottom: 2px solid var(--border-color);
    background-color: var(--bg-secondary) !important;
}

table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
    background-color: var(--card-bg) !important;
}

table tr:last-child td {
    border-bottom: none;
}

table tr {
    background-color: var(--card-bg) !important;
}

table tr:hover td {
    background-color: rgba(var(--primary-rgb), 0.02) !important;
}

/* Form controls */
.index-custom-input {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

.index-custom-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.index-custom-input:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.index-custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
    resize: vertical;
}

.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Button styles */
.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover:not(:disabled) {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background-color: rgba(var(--secondary-rgb), 0.2) !important;
    transform: translateY(-1px);
}

/* Modal styles */
.modal-container {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 16px;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.modal-close-btn {
    padding: 0.5rem;
    border-radius: 0.375rem;
    transition: background-color 0.2s;
    cursor: pointer;
    background: none;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close-btn:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-2.md\\:grid-cols-6 {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .grid.grid-cols-2.gap-4 {
        grid-template-columns: repeat(1, 1fr);
    }
    
    table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
    
    .modal-container {
        width: 95%;
        max-height: 80vh;
        margin: 0.5rem;
    }
    
    .modal-footer {
        flex-direction: column-reverse;
    }
    
    .modal-footer button {
        width: 100%;
    }
}

/* Tooltip */
[data-tooltip] {
    position: relative;
}

[data-tooltip]:hover::after {
    content: attr(data-tooltip);
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
}

/* Loading spinner */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.animate-spin {
    animation: spin 1s linear infinite;
}
</style>