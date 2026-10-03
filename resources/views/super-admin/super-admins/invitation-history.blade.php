{{-- super-admin/super-admins/invitation-history.blade.php --}}
@extends('layouts.app')

@php
    $isOwnProfile = auth()->id() == $user->id;
    $pageTitle = $isOwnProfile ? 'My Invitation History' : "Invitation History: {$user->name}";
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                        <i class="fas fa-paper-plane text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                        {{ $pageTitle }}
                    </h2>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Track all invitation emails sent to this Super Admin
                    </p>
                </div>
            </div>
            <div>
                <a href="{{ route('super-admin.super-admins.show', $user->id) }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium btn-secondary">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Profile
                </a>
            </div>
        </div>
    </div>

    <!-- User Info Card -->
    <div class="card p-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    @if(isset($user->has_photo) && $user->has_photo && $user->photo)
                    <div class="w-12 h-12 rounded-full overflow-hidden">
                        <img src="{{ Storage::url('users/photos/' . $user->photo) }}" 
                             alt="{{ $user->name }}" 
                             class="w-full h-full object-cover">
                    </div>
                    @else
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600;">
                        {{ $user->initials ?? substr($user->name, 0, 2) }}
                    </div>
                    @endif
                </div>
                <div class="ml-4">
                    <h3 class="font-semibold text-lg" style="color: var(--text-primary);">{{ $user->name }}</h3>
                    <p class="text-sm" style="color: var(--text-secondary);">{{ $user->email }}</p>
                </div>
            </div>
            
            <!-- Statistics Summary -->
            <div class="flex space-x-4">
                <div class="text-center">
                    <p class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['total'] ?? 0 }}</p>
                    <p class="text-xs" style="color: var(--text-secondary);">Total</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-green-600">{{ $stats['accepted'] ?? 0 }}</p>
                    <p class="text-xs" style="color: var(--text-secondary);">Accepted</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-yellow-600">{{ ($stats['sent'] ?? 0) + ($stats['pending'] ?? 0) }}</p>
                    <p class="text-xs" style="color: var(--text-secondary);">Pending/Sent</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-red-600">{{ ($stats['expired'] ?? 0) + ($stats['failed'] ?? 0) }}</p>
                    <p class="text-xs" style="color: var(--text-secondary);">Expired/Failed</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Invitations Table -->
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr style="background-color: rgba(var(--secondary-rgb), 0.05); border-bottom: 2px solid var(--border-color);">
                        <th class="text-left py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Invited At</th>
                        <th class="text-left py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Invited By</th>
                        <th class="text-center py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Status</th>
                        <th class="text-center py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Expires At</th>
                        <th class="text-left py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Custom Message</th>
                        <th class="text-center py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invitations as $invitation)
                    <tr style="border-bottom: 1px solid var(--border-color);" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                        <td class="py-4 px-6">
                            <p class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ $invitation->created_at->format('M j, Y') }}
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                {{ $invitation->created_at->format('g:i A') }}
                            </p>
                        </td>
                        <td class="py-4 px-6">
                            @if($invitation->invitedBy)
                                <p class="text-sm" style="color: var(--text-primary);">{{ $invitation->invitedBy->name }}</p>
                                <p class="text-xs" style="color: var(--text-secondary);">{{ $invitation->invitedBy->email }}</p>
                            @else
                                <p class="text-sm" style="color: var(--text-secondary);">System</p>
                            @endif
                        </td>
                        <td class="py-4 px-6 text-center">
                            @php
                                $statusColors = [
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'sent' => 'bg-blue-100 text-blue-800',
                                    'accepted' => 'bg-green-100 text-green-800',
                                    'expired' => 'bg-red-100 text-red-800',
                                    'failed' => 'bg-red-100 text-red-800',
                                    'cancelled' => 'bg-gray-100 text-gray-800',
                                ];
                                $statusIcons = [
                                    'pending' => 'fa-clock',
                                    'sent' => 'fa-paper-plane',
                                    'accepted' => 'fa-check-circle',
                                    'expired' => 'fa-hourglass-end',
                                    'failed' => 'fa-exclamation-circle',
                                    'cancelled' => 'fa-ban',
                                ];
                                $statusLabel = ucfirst($invitation->status);
                            @endphp
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $statusColors[$invitation->status] ?? 'bg-gray-100 text-gray-800' }}">
                                <i class="fas {{ $statusIcons[$invitation->status] ?? 'fa-envelope' }} mr-1"></i>
                                {{ $statusLabel }}
                            </span>
                            @if($invitation->status == 'failed' && $invitation->failure_reason)
                                <div class="mt-1">
                                    <span class="text-xs text-red-600 cursor-help" title="{{ $invitation->failure_reason }}">
                                        <i class="fas fa-info-circle"></i> Error
                                    </span>
                                </div>
                            @endif
                        </td>
                        <td class="py-4 px-6 text-center">
                            @if($invitation->expires_at)
                                <p class="text-sm" style="color: {{ $invitation->expires_at->isPast() ? 'var(--danger)' : 'var(--text-primary)' }};">
                                    {{ $invitation->expires_at->format('M j, Y g:i A') }}
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    @if($invitation->expires_at->isPast())
                                        Expired {{ $invitation->expires_at->diffForHumans() }}
                                    @else
                                        Expires in {{ $invitation->expires_at->diffForHumans() }}
                                    @endif
                                </p>
                            @else
                                <span class="text-sm" style="color: var(--text-secondary);">N/A</span>
                            @endif
                        </td>
                        <td class="py-4 px-6">
                            <p class="text-sm" style="color: var(--text-primary); max-width: 250px;">
                                {{ $invitation->custom_message ?: 'No custom message' }}
                            </p>
                        </td>
                        <td class="py-4 px-6 text-center">
                            <button type="button" 
                                    onclick="viewInvitationDetails({{ $invitation->id }})"
                                    class="btn-info px-3 py-1 rounded text-xs font-medium inline-flex items-center">
                                <i class="fas fa-info-circle mr-1"></i> Details
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center">
                            <i class="fas fa-paper-plane text-5xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                            <p class="text-lg font-medium" style="color: var(--text-primary);">No Invitations Found</p>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">No invitation records exist for this Super Admin.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if(isset($invitations) && method_exists($invitations, 'links') && $invitations->hasPages())
        <div class="p-6 border-t" style="border-color: var(--border-color);">
            {{ $invitations->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Invitation Details Modal -->
<div id="invitationModal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="modal-container w-full max-w-2xl" style="background: var(--card-bg); border-radius: 16px;">
            <div class="modal-header">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Invitation Details</h3>
                <button onclick="closeModal()" class="modal-close-btn">
                    <i class="fas fa-times text-xl" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div id="modalContent" class="modal-body">
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-3xl" style="color: var(--primary);"></i>
                    <p class="mt-2" style="color: var(--text-secondary);">Loading details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="closeModal()" class="px-4 py-2 rounded-lg btn-secondary">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function viewInvitationDetails(invitationId) {
    const modal = document.getElementById('invitationModal');
    const modalContent = document.getElementById('modalContent');
    
    modal.classList.remove('hidden');
    modalContent.innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-3xl" style="color: var(--primary);"></i>
            <p class="mt-2" style="color: var(--text-secondary);">Loading details...</p>
        </div>
    `;
    
    // Use the correct route for invitation details
    const url = `{{ url('super-admin/super-admins/invitation-details') }}/${invitationId}`;
    
    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.invitation) {
            const inv = data.invitation;
            
            // Get status color class
            const statusColorClass = getStatusColorClass(inv.status_color || inv.status);
            
            modalContent.innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Invitation ID</label>
                            <p class="text-sm font-mono" style="color: var(--text-primary);">#${inv.id}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Status</label>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${statusColorClass}">
                                ${inv.status_label || inv.status}
                            </span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Invited By</label>
                        <p class="text-sm" style="color: var(--text-primary);">
                            ${inv.invited_by ? `${escapeHtml(inv.invited_by.name)} (${escapeHtml(inv.invited_by.email)})` : 'System'}
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Created At</label>
                            <p class="text-sm" style="color: var(--text-primary);">${inv.created_at_formatted || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Sent At</label>
                            <p class="text-sm" style="color: var(--text-primary);">${inv.sent_at_formatted || 'Not sent yet'}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Expires At</label>
                            <p class="text-sm" style="color: var(--text-primary);">${inv.expires_at_formatted || 'N/A'}</p>
                            ${inv.expires_in ? `<p class="text-xs" style="color: var(--text-secondary);">${inv.expires_in}</p>` : ''}
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Send Attempts</label>
                            <p class="text-sm" style="color: var(--text-primary);">${inv.send_attempts || 0}</p>
                        </div>
                    </div>
                    
                    ${inv.custom_message ? `
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Custom Message</label>
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                            <p class="text-sm" style="color: var(--text-primary);">${escapeHtml(inv.custom_message)}</p>
                        </div>
                    </div>
                    ` : ''}
                    
                    ${inv.failure_reason ? `
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Failure Reason</label>
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1);">
                            <p class="text-sm text-red-600">${escapeHtml(inv.failure_reason)}</p>
                        </div>
                    </div>
                    ` : ''}
                    
                    ${inv.invitation_url ? `
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Invitation URL</label>
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05); word-break: break-all;">
                            <code class="text-xs" style="color: var(--text-secondary);">${escapeHtml(inv.invitation_url)}</code>
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;
        } else {
            modalContent.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-circle text-3xl text-red-500"></i>
                    <p class="mt-2" style="color: var(--text-secondary);">${data.message || 'Failed to load invitation details.'}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        modalContent.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-exclamation-circle text-3xl text-red-500"></i>
                <p class="mt-2" style="color: var(--text-secondary);">An error occurred. Please try again.</p>
            </div>
        `;
    });
}

function getStatusColorClass(status) {
    const colors = {
        'success': 'bg-green-100 text-green-800',
        'danger': 'bg-red-100 text-red-800',
        'warning': 'bg-yellow-100 text-yellow-800',
        'info': 'bg-blue-100 text-blue-800',
        'primary': 'bg-indigo-100 text-indigo-800',
        'secondary': 'bg-gray-100 text-gray-800'
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
}

function closeModal() {
    document.getElementById('invitationModal').classList.add('hidden');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});

// Close modal when clicking outside
document.getElementById('invitationModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeModal();
    }
});
</script>

<style>
.modal-container {
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
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
}

.modal-close-btn:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
    border: 1px solid rgba(var(--secondary-rgb), 0.3);
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background-color: rgba(var(--secondary-rgb), 0.2);
    transform: translateY(-1px);
}

.btn-info {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border: 1px solid rgba(var(--info-rgb), 0.3);
    transition: all 0.2s ease;
}

.btn-info:hover {
    background-color: rgba(var(--info-rgb), 0.2);
    transform: translateY(-1px);
}

/* Dark mode support for modal */
[data-theme="dark"] .modal-container {
    background-color: #1a1a2e;
}

[data-theme="light"] .modal-container {
    background-color: #ffffff;
}
</style>
@endsection