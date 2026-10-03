{{-- resources/views/developer/billing/trashed-agreements.blade.php --}}
@extends('layouts.dev')

@php
    $pageTitle = 'Deleted Agreements - Trash';
    
    function formatCurrency($amount, $currency = 'GHS') {
        if (empty($amount)) return 'GH₵0.00';
        if ($currency === 'GHS') {
            return 'GH₵' . number_format($amount, 2);
        }
        return $currency . ' ' . number_format($amount, 2);
    }
    
    function getStatusColor($status) {
        $colors = [
            'active' => 'success',
            'pending' => 'warning',
            'completed' => 'info',
            'terminated' => 'danger',
            'superseded' => 'secondary',
            'cancelled' => 'secondary'
        ];
        return $colors[$status] ?? 'secondary';
    }
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--danger) 0%, var(--warning) 100%); color: white; font-weight: 600; border-color: var(--danger);">
                        <i class="fas fa-trash-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> 
                        Deleted Agreements (Trash)
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>View and restore soft-deleted agreements</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-trash mr-1" style="color: var(--danger);"></i>
                        <span class="font-medium">{{ $agreements->total() }} deleted agreements</span>
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <div class="flex items-center space-x-2 mt-2">
                    <a href="{{ route('developer.billing.agreements-list') }}" 
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-arrow-left mr-1"></i> Active Agreements
                    </a>
                    <a href="{{ route('developer.billing.dashboard') }}" 
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-trash-alt text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Deleted</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $stats['total_deleted'] }}</p>
                </div>
            </div>
        </div>
        
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-clock text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Pending Deleted</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $stats['deleted_by_status']['pending'] }}</p>
                </div>
            </div>
        </div>
        
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-ban text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Terminated Deleted</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $stats['deleted_by_status']['terminated'] }}</p>
                </div>
            </div>
        </div>
        
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                        <i class="fas fa-times-circle text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Cancelled Deleted</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $stats['deleted_by_status']['cancelled'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filter Deleted Agreements
        </h3>
        
        <form method="GET" action="{{ route('developer.billing.agreements-trashed') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Status Filter -->
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Status Before Deletion
                    </label>
                    <select name="status" class="form-select w-full p-3 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>All Statuses</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="terminated" {{ request('status') == 'terminated' ? 'selected' : '' }}>Terminated</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                
                <!-- Search -->
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Search
                    </label>
                    <div class="relative">
                        <input type="text" 
                               name="search" 
                               value="{{ request('search') }}" 
                               class="form-input w-full p-3 rounded-lg border pl-10"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               placeholder="Search by agreement number, description, or super admin">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search" style="color: var(--text-secondary);"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Per Page -->
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Items Per Page
                    </label>
                    <select name="per_page" class="form-select w-full p-3 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                            onchange="this.form.submit()">
                        <option value="10" {{ request('per_page', 20) == 10 ? 'selected' : '' }}>10 per page</option>
                        <option value="20" {{ request('per_page', 20) == 20 ? 'selected' : '' }}>20 per page</option>
                        <option value="50" {{ request('per_page', 20) == 50 ? 'selected' : '' }}>50 per page</option>
                        <option value="100" {{ request('per_page', 20) == 100 ? 'selected' : '' }}>100 per page</option>
                    </select>
                </div>
            </div>
            
            <!-- Filter Buttons -->
            <div class="flex justify-between items-center pt-4" style="border-top: 1px solid var(--border-color);">
                <div>
                    <a href="{{ route('developer.billing.agreements-trashed') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-redo mr-2"></i> Reset Filters
                    </a>
                </div>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Warning Banner -->
    <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle mr-3 mt-0.5" style="color: var(--warning);"></i>
            <div>
                <p class="font-semibold" style="color: var(--text-primary);">About Deleted Agreements</p>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Agreements in trash have been soft-deleted. You can restore them or permanently delete them. 
                    Permanently deleted agreements cannot be recovered. Agreements with payment history cannot be permanently deleted.
                </p>
            </div>
        </div>
    </div>

    <!-- Trashed Agreements Table -->
    <div class="card p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Deleted Agreements
            </h3>
            <div class="text-sm" style="color: var(--text-secondary);">
                Showing {{ $agreements->firstItem() }} - {{ $agreements->lastItem() }} of {{ $agreements->total() }}
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--danger-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Agreement</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--danger-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Super Admin</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--danger-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Amount</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--danger-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Status (Before)</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--danger-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Deleted At</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--danger-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Days in Trash</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--danger-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($agreements as $agreement)
                    @php
                        $deletedAt = \Carbon\Carbon::parse($agreement->deleted_at);
                        $daysInTrash = $deletedAt->diffInDays(now());
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div>
                                <div class="font-mono text-sm font-medium" style="color: var(--text-primary);">
                                    @if($agreement->is_primary_for_billing)
                                        <i class="fas fa-crown text-xs mr-1" style="color: var(--primary);"></i>
                                    @endif
                                    {{ $agreement->agreement_number }}
                                </div>
                                <div class="text-xs truncate max-w-xs" style="color: var(--text-secondary);">
                                    {{ Str::limit($agreement->description, 50) }}
                                </div>
                            </div>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                     style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                    <i class="fas fa-user-shield text-xs"></i>
                                </div>
                                <div>
                                    <div style="color: var(--text-primary);">{{ $agreement->superAdmin->name ?? 'N/A' }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $agreement->superAdmin->email ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div class="font-medium" style="color: var(--text-primary);">{{ formatCurrency($agreement->amount, $agreement->currency) }}</div>
                            <div class="text-xs text-gray-500">
                                Received: {{ formatCurrency($agreement->amount_received, $agreement->currency) }}
                            </div>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ getStatusColor($agreement->status) }}">
                                <i class="fas fa-circle mr-1" style="font-size: 0.5rem;"></i>
                                {{ ucfirst($agreement->status) }}
                            </span>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div style="color: var(--text-primary);">{{ $deletedAt->format('M d, Y') }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">{{ $deletedAt->format('h:i A') }}</div>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ $daysInTrash > 30 ? 'danger' : ($daysInTrash > 7 ? 'warning' : 'info') }}">
                                <i class="fas fa-calendar-day mr-1"></i>
                                {{ $daysInTrash }} day{{ $daysInTrash != 1 ? 's' : '' }}
                            </span>
                            @if($daysInTrash > 30)
                                <div class="text-xs mt-1" style="color: var(--danger);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Auto-deletion pending
                                </div>
                            @endif
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div class="flex flex-wrap gap-2">
                                <!-- View Details Button -->
                                <a href="{{ route('developer.billing.view-agreement', $agreement->id) }}" 
                                   class="px-3 py-1.5 rounded text-xs font-medium inline-flex items-center"
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);"
                                   title="View Details">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                                
                                <!-- Restore Button -->
                                <button onclick="showRestoreModal({{ $agreement->id }}, '{{ $agreement->agreement_number }}')"
                                        class="px-3 py-1.5 rounded text-xs font-medium inline-flex items-center"
                                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);"
                                        title="Restore Agreement">
                                    <i class="fas fa-undo-alt mr-1"></i> Restore
                                </button>
                                
                                <!-- Permanent Delete Button -->
                                <button onclick="showForceDeleteModal({{ $agreement->id }}, '{{ $agreement->agreement_number }}')"
                                        class="px-3 py-1.5 rounded text-xs font-medium inline-flex items-center"
                                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);"
                                        title="Permanently Delete">
                                    <i class="fas fa-trash-alt mr-1"></i> Delete Permanently
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center" style="border-color: var(--border-color);">
                            <i class="fas fa-trash-alt text-3xl mb-2 opacity-50" style="color: var(--text-secondary);"></i>
                            <p style="color: var(--text-secondary);">No deleted agreements found</p>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">When you delete agreements, they will appear here</p>
                            <a href="{{ route('developer.billing.agreements-list') }}" 
                               class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center mt-2"
                               style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                <i class="fas fa-handshake mr-1"></i> View Active Agreements
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if($agreements->hasPages())
        <div class="mt-6 pt-6" style="border-top: 1px solid var(--border-color);">
            {{ $agreements->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Restore Agreement Modal -->
<div id="restoreModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeRestoreModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container" style="background-color: var(--card-bg); border: 1px solid var(--border-color); max-width: 500px;">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-undo-alt mr-2" style="color: var(--success);"></i> Restore Agreement
                </h3>
                <button type="button" onclick="closeRestoreModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="restoreForm" method="POST" action="">
                    @csrf
                    @method('POST')
                    <div class="space-y-4">
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.2);">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                                <div>
                                    <p class="font-bold" style="color: var(--success);">Restore Agreement</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        This will restore the agreement to its original status. The agreement will be available in the active agreements list.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <p class="text-sm" style="color: var(--text-secondary);">
                            Are you sure you want to restore agreement <strong id="restoreAgreementNumber" style="color: var(--success);"></strong>?
                        </p>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeRestoreModal()">
                    Cancel
                </button>
                <button type="submit" 
                        form="restoreForm"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--success); border: 1px solid var(--success);">
                    <i class="fas fa-undo-alt mr-2"></i> Restore Agreement
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Force Delete Modal -->
<div id="forceDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeForceDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container" style="background-color: var(--card-bg); border: 1px solid var(--border-color); max-width: 500px;">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i> Permanently Delete Agreement
                </h3>
                <button type="button" onclick="closeForceDeleteModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="forceDeleteForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <div class="space-y-4">
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                                <div>
                                    <p class="font-bold" style="color: var(--danger);">Warning!</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        This action <strong>CANNOT</strong> be undone. The agreement will be permanently deleted from the database.
                                        This includes all associated payments, signatures, and PDF files.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Type <strong id="forceDeleteAgreementNumber" style="color: var(--danger);"></strong> to confirm permanent deletion:
                            </label>
                            <input type="text" 
                                   name="confirmation_text" 
                                   id="forceDeleteConfirmationText"
                                   class="form-input w-full p-3 rounded-lg border"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary); font-family: monospace;"
                                   placeholder="Enter agreement number"
                                   autocomplete="off"
                                   required>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Please enter the exact agreement number to confirm permanent deletion.
                            </p>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeForceDeleteModal()">
                    Cancel
                </button>
                <button type="submit" 
                        form="forceDeleteForm"
                        id="forceDeleteConfirmButton"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--danger); border: 1px solid var(--danger); opacity: 0.5; cursor: not-allowed;"
                        disabled>
                    <i class="fas fa-trash-alt mr-2"></i> Permanently Delete
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let currentAgreementId = null;

function closeRestoreModal() {
    const modal = document.getElementById('restoreModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function openRestoreModal() {
    const modal = document.getElementById('restoreModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeForceDeleteModal() {
    const modal = document.getElementById('forceDeleteModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function openForceDeleteModal() {
    const modal = document.getElementById('forceDeleteModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function showRestoreModal(agreementId, agreementNumber) {
    currentAgreementId = agreementId;
    
    const form = document.getElementById('restoreForm');
    form.action = `/developer/billing/agreements/${agreementId}/restore`;
    
    document.getElementById('restoreAgreementNumber').textContent = agreementNumber;
    
    openRestoreModal();
}

function showForceDeleteModal(agreementId, agreementNumber) {
    currentAgreementId = agreementId;
    
    const form = document.getElementById('forceDeleteForm');
    form.action = `/developer/billing/agreements/${agreementId}/force-delete`;
    
    document.getElementById('forceDeleteAgreementNumber').textContent = agreementNumber;
    
    const confirmationInput = document.getElementById('forceDeleteConfirmationText');
    const confirmButton = document.getElementById('forceDeleteConfirmButton');
    
    // Reset input and button state
    confirmationInput.value = '';
    confirmButton.disabled = true;
    confirmButton.style.opacity = '0.5';
    confirmButton.style.cursor = 'not-allowed';
    
    // Add input event listener for confirmation
    confirmationInput.oninput = function() {
        if (this.value === agreementNumber) {
            confirmButton.disabled = false;
            confirmButton.style.opacity = '1';
            confirmButton.style.cursor = 'pointer';
        } else {
            confirmButton.disabled = true;
            confirmButton.style.opacity = '0.5';
            confirmButton.style.cursor = 'not-allowed';
        }
    };
    
    openForceDeleteModal();
}

function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;
    
    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.textContent = message;
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 transition-colors duration-200';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };
    
    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);
    
    setTimeout(() => {
        if (toast.parentNode === toastContainer) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRestoreModal();
        closeForceDeleteModal();
    }
});

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('fixed') && e.target.classList.contains('inset-0') && e.target.classList.contains('bg-black')) {
        closeRestoreModal();
        closeForceDeleteModal();
    }
});
</script>
@endsection

@push('styles')
<style>
.modal-container {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    max-width: 550px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
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
    background-color: rgba(var(--secondary-rgb), 0.1);
}

.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
}

.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1) !important;
    color: var(--danger) !important;
    border: 1px solid rgba(var(--danger-rgb), 0.3) !important;
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}

.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1) !important;
    color: var(--primary) !important;
    border: 1px solid rgba(var(--primary-rgb), 0.3) !important;
}

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

.form-input, .form-select, .form-checkbox {
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    transition: all 0.2s ease !important;
}

.form-input:focus, .form-select:focus {
    outline: none !important;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

.stat-card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    padding: 1.5rem !important;
    transition: transform 0.2s ease, box-shadow 0.2s ease !important;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02) !important;
}

@media (max-width: 768px) {
    .modal-container {
        margin: 1rem;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .modal-footer button {
        width: 100%;
    }
}

@media (max-width: 640px) {
    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 1rem;
    }
}
</style>
@endpush