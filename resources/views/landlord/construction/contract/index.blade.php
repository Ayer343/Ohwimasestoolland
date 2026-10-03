@extends('layouts.landlord')

@section('title', 'Construction Contracts')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-wrap justify-between items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Construction Contracts</h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i> 
                    Manage all your construction contracts and track project progress
                </p>
            </div>
            <div class="flex flex-wrap gap-2 mt-2 sm:mt-0">
                <a href="{{ route('landlord.construction.contract.create') }}" 
                   class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center transition-colors">
                    <i class="fas fa-plus mr-2"></i> 
                    <span>New Contract</span>
                </a>
                <a href="{{ route('properties.my-properties') }}" 
                   class="px-4 py-2 border rounded-lg hover:bg-gray-50 flex items-center transition-colors" 
                   style="border-color: var(--border-color); color: var(--text-primary);">
                    <i class="fas fa-building mr-2"></i> 
                    <span>My Properties</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                    <i class="fas fa-file-contract text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Total</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $stats['total'] ?? 0 }}
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                    <i class="fas fa-clock text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Pending</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $stats['pending'] ?? 0 }}
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                    <i class="fas fa-check-circle text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Approved</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $stats['approved'] ?? 0 }}
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                    <i class="fas fa-hard-hat text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">In Progress</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $stats['in_progress'] ?? 0 }}
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-gray-100 text-gray-600 mr-4">
                    <i class="fas fa-flag-checkered text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Completed</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $stats['completed'] ?? 0 }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline">{{ session('success') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline">{{ session('error') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Filters Card -->
    <div class="card p-6">
        <form method="GET" action="{{ route('landlord.construction.contract.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[150px]">
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Status</label>
                <select name="status" class="w-full p-2 rounded-lg border" style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="pending_approval" {{ request('status') == 'pending_approval' ? 'selected' : '' }}>Pending Approval</option>
                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            
            <div class="flex-1 min-w-[150px]">
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Contractor Name</label>
                <input type="text" name="contractor_name" value="{{ request('contractor_name') }}" 
                       class="w-full p-2 rounded-lg border" style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary);"
                       placeholder="Search contractor...">
            </div>
            
            <div class="flex-1 min-w-[130px]">
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Date From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" 
                       class="w-full p-2 rounded-lg border" style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
            </div>
            
            <div class="flex-1 min-w-[130px]">
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Date To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" 
                       class="w-full p-2 rounded-lg border" style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
            </div>
            
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-filter mr-1"></i> Filter
                </button>
                <a href="{{ route('landlord.construction.contract.index') }}" class="px-4 py-2 border rounded-lg hover:bg-gray-50 transition-colors" style="border-color: var(--border-color); color: var(--text-primary);">
                    <i class="fas fa-undo mr-1"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Contracts Table Card -->
    <div class="card p-6">
        <div class="flex justify-between items-center mb-4">
            <p class="text-sm" style="color: var(--text-secondary);">
                Showing {{ $contracts->firstItem() ?? 0 }} to {{ $contracts->lastItem() ?? 0 }} of {{ $contracts->total() }} contracts
            </p>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-sort mr-1"></i> Latest first
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Contract</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Property</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Contractor</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Amount</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Timeline</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contracts as $contract)
                    <tr class="border-b transition-colors hover:bg-gray-50" style="border-color: var(--border-color);">
                        <td class="p-3">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center" 
                                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                                    <i class="fas fa-file-signature" style="color: var(--primary);"></i>
                                </div>
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">
                                        {{ Str::limit($contract->title, 40) }}
                                    </p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-calendar mr-1"></i>
                                        Created: {{ $contract->created_at->format('M d, Y') }}
                                    </p>
                                    @if($contract->description)
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            {{ Str::limit($contract->description, 60) }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="p-3">
                            @if($contract->property)
                                <a href="{{ route('properties.show', $contract->property->id) }}" 
                                   class="hover:underline" style="color: var(--primary);">
                                    {{ $contract->property->property_name ?? 'Property #' . $contract->property->id }}
                                </a>
                                @if($contract->property->digital_address)
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        {{ $contract->property->digital_address }}
                                    </p>
                                @endif
                            @else
                                <span class="text-sm" style="color: var(--text-secondary);">Property deleted</span>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $contract->contractor_name }}
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-user-tag mr-1"></i>
                                {{ ucfirst($contract->contractor_type) }}
                            </p>
                            @if($contract->contractor_phone)
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-phone mr-1"></i>
                                    {{ $contract->contractor_phone }}
                                </p>
                            @endif
                        </td>
                        <td class="p-3">
                            @if($contract->contract_amount)
                                <p class="font-bold" style="color: var(--text-primary);">
                                    ₵{{ number_format($contract->contract_amount, 2) }}
                                </p>
                            @else
                                <span class="text-sm" style="color: var(--text-secondary);">Not specified</span>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-play mr-1" style="color: var(--success);"></i>
                                {{ \Carbon\Carbon::parse($contract->contract_start_date)->format('M d, Y') }}
                            </p>
                            <p class="text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-flag-checkered mr-1" style="color: var(--warning);"></i>
                                {{ \Carbon\Carbon::parse($contract->estimated_completion_date)->format('M d, Y') }}
                            </p>
                            @php
                                $daysRemaining = \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::parse($contract->estimated_completion_date), false);
                            @endphp
                            @if($daysRemaining > 0 && $contract->status !== 'completed')
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-hourglass-half mr-1"></i>
                                    {{ $daysRemaining }} days remaining
                                </p>
                            @elseif($daysRemaining <= 0 && $contract->status !== 'completed')
                                <p class="text-xs mt-1" style="color: var(--danger);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Overdue by {{ abs($daysRemaining) }} days
                                </p>
                            @endif
                        </td>
                        <td class="p-3">
                            @php
                                $statusColors = [
                                    'draft' => ['bg' => 'secondary', 'icon' => 'file', 'label' => 'Draft'],
                                    'pending_approval' => ['bg' => 'warning', 'icon' => 'clock', 'label' => 'Pending Approval'],
                                    'approved' => ['bg' => 'success', 'icon' => 'check-circle', 'label' => 'Approved'],
                                    'in_progress' => ['bg' => 'primary', 'icon' => 'hard-hat', 'label' => 'In Progress'],
                                    'completed' => ['bg' => 'success', 'icon' => 'flag-checkered', 'label' => 'Completed'],
                                    'rejected' => ['bg' => 'danger', 'icon' => 'times-circle', 'label' => 'Rejected']
                                ];
                                $statusConfig = $statusColors[$contract->status] ?? ['bg' => 'secondary', 'icon' => 'question-circle', 'label' => ucfirst(str_replace('_', ' ', $contract->status))];
                            @endphp
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" 
                                  style="background-color: rgba(var(--{{ $statusConfig['bg'] }}-rgb), 0.2); color: var(--{{ $statusConfig['bg'] }});">
                                <i class="fas fa-{{ $statusConfig['icon'] }} mr-1"></i>
                                {{ $statusConfig['label'] }}
                            </span>
                            
                            @if($contract->status === 'pending_approval')
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-hourglass-half mr-1"></i>
                                    Awaiting admin review
                                </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-1">
                                <!-- View Button -->
                                <a href="{{ route('landlord.construction.contract.show', $contract) }}" 
                                   class="p-2 rounded-lg" 
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" 
                                   title="View Contract">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <!-- Edit Button (only for draft or pending) -->
                                @if(in_array($contract->status, ['draft', 'pending_approval']))
                                    <a href="{{ route('landlord.construction.contract.edit', $contract) }}" 
                                       class="p-2 rounded-lg" 
                                       style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);" 
                                       title="Edit Contract">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                @endif
                                
                                <!-- View Property Button -->
                                @if($contract->property)
                                    <a href="{{ route('properties.show', $contract->property->id) }}" 
                                       class="p-2 rounded-lg" 
                                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);" 
                                       title="View Property">
                                        <i class="fas fa-building"></i>
                                    </a>
                                @endif
                            </div>
                            
                            <!-- Quick Status Info -->
                            <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                                @if($contract->status === 'pending_approval')
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-clock mr-1" style="color: var(--warning);"></i>
                                        Submitted {{ $contract->created_at->diffForHumans() }}
                                    </span>
                                @elseif($contract->status === 'approved')
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-check mr-1" style="color: var(--success);"></i>
                                        Ready to start
                                    </span>
                                @elseif($contract->status === 'in_progress')
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-hard-hat mr-1" style="color: var(--primary);"></i>
                                        In progress
                                    </span>
                                @elseif($contract->status === 'completed')
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                        Completed
                                    </span>
                                @elseif($contract->status === 'rejected')
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-times mr-1" style="color: var(--danger);"></i>
                                        Rejected
                                    </span>
                                @else
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-file mr-1" style="color: var(--secondary);"></i>
                                        Draft
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-file-contract text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium mb-2">No construction contracts found</p>
                                <p class="text-sm">You haven't created any construction contracts yet.</p>
                                <a href="{{ route('landlord.construction.contract.create') }}" 
                                   class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors inline-flex items-center">
                                    <i class="fas fa-plus mr-2"></i> Create Your First Contract
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $contracts->withQueryString()->links() }}
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- ⭐ STATUS CHANGE MODAL -->
<!-- ============================================ -->
<div id="statusModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: var(--card-bg, #ffffff); border-radius: 0.75rem; width: 100%; max-width: 500px; position: relative;">
        <div style="padding: 1.25rem; border-bottom: 1px solid var(--border-color, #e9ecef); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.2rem; color: var(--text-primary, #1a1a2e);">
                <i class="fas fa-exchange-alt" style="color: var(--warning, #f59e0b);"></i> 
                Update Status
            </h3>
            <button onclick="closeStatusModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary, #6c757d); padding: 0.5rem; min-width: 44px; min-height: 44px; border-radius: 0.5rem;">
                &times;
            </button>
        </div>
        <div style="padding: 1.5rem;">
            <form id="statusUpdateForm" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">New Status</label>
                    <select name="status" id="statusSelect" class="w-full p-2 rounded-lg border" style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                        <option value="pending_approval">Pending Approval</option>
                        <option value="approved">Approved</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Notes (Optional)</label>
                    <textarea name="status_notes" rows="3" class="w-full p-2 rounded-lg border" style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary);" placeholder="Add notes about this status change..."></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeStatusModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-50 transition-colors" style="border-color: var(--border-color); color: var(--text-primary);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Overlay Styles -->
<style>
/* Modal Styles */
#statusModal {
    display: none !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: rgba(0, 0, 0, 0.5) !important;
    z-index: 9999 !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 1rem !important;
}

#statusModal.active {
    display: flex !important;
}

/* Dark theme support */
[data-theme="dark"] #statusModal > div {
    background: #1e293b !important;
}

[data-theme="dark"] #statusModal .form-control {
    background: #1a1a2e !important;
    color: #f1f5f9 !important;
    border-color: #334155 !important;
}

@media (max-width: 768px) {
    #statusModal > div {
        max-width: 100% !important;
        max-height: 95vh !important;
        border-radius: 0.5rem !important;
    }
}
</style>
@endsection

@section('scripts')
<script>
// ============================================
// STATUS UPDATE MODAL
// ============================================
function openStatusModal(contractId, currentStatus) {
    const modal = document.getElementById('statusModal');
    const form = document.getElementById('statusUpdateForm');
    const select = document.getElementById('statusSelect');
    
    if (!modal || !form || !select) return;
    
    // Set form action
    form.action = `/landlord/construction/contracts/${contractId}/status`;
    
    // Set current status
    select.value = currentStatus;
    
    // Show modal
    modal.classList.add('active');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeStatusModal() {
    const modal = document.getElementById('statusModal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// ============================================
// KEYBOARD SHORTCUTS
// ============================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeStatusModal();
    }
});

// ============================================
// CLOSE MODAL ON OVERLAY CLICK
// ============================================
document.getElementById('statusModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeStatusModal();
    }
});

// ============================================
// AUTO-HIDE MESSAGES
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const messages = document.querySelectorAll('.bg-green-100, .bg-red-100');
    messages.forEach(function(msg) {
        setTimeout(function() {
            msg.style.opacity = '0';
            msg.style.transition = 'opacity 0.5s ease';
            setTimeout(function() {
                msg.style.display = 'none';
            }, 500);
        }, 5000);
    });
});

console.log('🚀 Construction Contracts Index Script Loaded');
</script>
@endsection