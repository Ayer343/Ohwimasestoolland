@extends('layouts.app')

@section('title', 'Deleted Invoices - Trash')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-trash-restore text-2xl mr-3" style="color: var(--warning);"></i>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Deleted Invoices</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">View and restore soft-deleted invoices</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.tenant-invoices.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Invoices
                </a>
                <a href="{{ route('admin.tenant-invoices.archives') }}" class="btn-info flex items-center">
                    <i class="fas fa-archive mr-2"></i> Archives
                </a>
            </div>
        </div>
    </div>

    <!-- Info Message -->
    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-info-circle mr-3 text-xl"></i>
            <div>
                <strong class="font-bold">Trash Information:</strong>
                <span class="block sm:inline"> Invoices in the trash can be restored. Permanently deleted invoices will be moved to Archives and cannot be restored.</span>
            </div>
        </div>
    </div>

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

    <!-- Info Message -->
    @if(session('info'))
    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Info!</strong>
        <span class="block sm:inline">{{ session('info') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Invoices Table Card -->
    <div class="card p-6">
        <!-- Results Count -->
        <div class="mb-4 flex flex-wrap justify-between items-center gap-4">
            <p class="text-sm" style="color: var(--text-secondary);">
                Showing <span class="font-medium">{{ $invoices->firstItem() ?? 0 }}</span> to <span class="font-medium">{{ $invoices->lastItem() ?? 0 }}</span> of <span class="font-medium">{{ $invoices->total() }}</span> deleted invoices
            </p>
            
            <div class="flex items-center flex-wrap gap-2">
                <span class="text-sm mr-2" style="color: var(--text-secondary);">Summary:</span>
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                    Total Deleted: {{ $invoices->total() }}
                </span>
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--danger-rgb), 0.2); color: var(--danger);">
                    Total Amount: {{ $system_settings->formatAmount($invoices->sum('total_amount')) }}
                </span>
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                    Total Penalties: {{ $system_settings->formatAmount($invoices->sum('penalty_amount')) }}
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Invoice #</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Tenant</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Property / Unit</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Period</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Amount</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Deleted At</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                     </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <td class="p-3">
                            <span class="font-medium" style="color: var(--text-primary);">
                                {{ $invoice->invoice_number }}
                            </span>
                            @if($invoice->payment_reference)
                                <p class="text-xs" style="color: var(--text-secondary);">Ref: {{ $invoice->payment_reference }}</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center mr-2">
                                    <span class="text-sm font-medium">{{ substr($invoice->tenant->name ?? 'U', 0, 1) }}</span>
                                </div>
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">{{ $invoice->tenant->name ?? 'Unknown' }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">{{ $invoice->tenant->phone ?? 'No phone' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $invoice->propertyUnit->property->property_name ?? 'N/A' }}
                            </p>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                Unit: {{ $invoice->propertyUnit->unit_number ?? 'N/A' }}
                            </p>
                            @if($invoice->propertyUnit->property->zone)
                                <p class="text-xs" style="color: var(--text-secondary);">Zone: {{ $invoice->propertyUnit->property->zone }}</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ \Carbon\Carbon::parse($invoice->period . '-01')->format('M Y') }}
                            </p>
                        </td>
                        <td class="p-3">
                            <div class="space-y-1">
                                <p class="font-medium" style="color: var(--text-primary);">
                                    {{ $system_settings->formatAmount($invoice->total_amount) }}
                                </p>
                                <div class="flex items-center text-xs space-x-2">
                                    <span style="color: var(--text-secondary);">CD: {{ $system_settings->formatAmount($invoice->community_dues) }}</span>
                                    @if($invoice->additional_charges > 0)
                                    <span style="color: var(--warning);">+{{ $system_settings->formatAmount($invoice->additional_charges) }}</span>
                                    @endif
                                    @if($invoice->penalty_amount > 0)
                                    <span style="color: var(--danger);">+{{ $system_settings->formatAmount($invoice->penalty_amount) }} penalty</span>
                                    @endif
                                </div>
                                @if($invoice->paid_amount > 0)
                                <p class="text-xs text-success mt-1">
                                    <i class="fas fa-check-circle mr-1"></i> Paid: {{ $system_settings->formatAmount($invoice->paid_amount) }}
                                </p>
                                @endif
                            </div>
                        </td>
                        <td class="p-3">
                            @php
                                $statusColors = [
                                    'paid' => 'success',
                                    'pending' => 'warning',
                                    'overdue' => 'danger',
                                    'cancelled' => 'secondary'
                                ];
                                $statusColor = $statusColors[$invoice->status] ?? 'secondary';
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs font-medium" 
                                  style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                                <i class="{{ $invoice->status_icon }} mr-1"></i>
                                {{ ucfirst($invoice->status) }}
                            </span>
                            @if($invoice->penalty_amount > 0)
                            <div class="mt-1">
                                <span class="text-xs" style="color: var(--danger);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Penalty: {{ $system_settings->formatAmount($invoice->penalty_amount) }}
                                </span>
                            </div>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $invoice->deleted_at->format('M d, Y H:i') }}
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                {{ $invoice->deleted_at->diffForHumans() }}
                            </p>
                        </td>
                        <td class="p-3">
                            <div class="flex items-center space-x-2">
                                <button type="button" 
                                        onclick="confirmRestore({{ $invoice->id }}, '{{ $invoice->invoice_number }}')"
                                        class="p-2 rounded hover:opacity-80 transition-opacity" 
                                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" 
                                        title="Restore Invoice">
                                    <i class="fas fa-trash-restore"></i>
                                </button>
                                @if(auth()->user()->isSuperAdmin())
                                <button type="button" 
                                        onclick="confirmPermanentDelete({{ $invoice->id }}, '{{ $invoice->invoice_number }}')"
                                        class="p-2 rounded hover:opacity-80 transition-opacity" 
                                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);" 
                                        title="Permanently Delete (Move to Archives)">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-trash-alt text-5xl mb-4 opacity-30"></i>
                                <p class="text-lg font-medium mb-2">No deleted invoices found</p>
                                <p class="text-sm mb-4">The trash is empty. Deleted invoices will appear here.</p>
                                <a href="{{ route('admin.tenant-invoices.index') }}" class="btn-primary flex items-center">
                                    <i class="fas fa-arrow-left mr-2"></i> Back to Invoices
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($invoices->hasPages())
        <div class="flex justify-between items-center mt-6">
            <div class="text-sm" style="color: var(--text-secondary);">
                Page {{ $invoices->currentPage() }} of {{ $invoices->lastPage() }}
            </div>
            <div class="flex space-x-2">
                {{ $invoices->appends(request()->query())->links() }}
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div id="restoreModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <div class="flex items-center mb-4 text-success">
                <i class="fas fa-trash-restore text-3xl mr-3"></i>
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Confirm Restore</h3>
            </div>
            <p class="mb-4" style="color: var(--text-secondary);">Are you sure you want to restore this invoice? It will be moved back to active invoices.</p>
            <p class="mb-4 text-sm font-medium" id="restoreInvoiceNumber" style="color: var(--primary);"></p>
            <form id="restoreForm" method="POST">
                @csrf
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeRestoreModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded text-white" style="background-color: var(--success);">
                        <i class="fas fa-trash-restore mr-2"></i> Restore Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Permanent Delete Confirmation Modal -->
<div id="permanentDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <div class="flex items-center mb-4 text-danger">
                <i class="fas fa-exclamation-triangle text-3xl mr-3"></i>
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Confirm Permanent Deletion</h3>
            </div>
            <p class="mb-4" style="color: var(--text-secondary);">Are you sure you want to permanently delete this invoice? <strong>This action cannot be undone!</strong> The invoice will be moved to Archives.</p>
            <p class="mb-4 text-sm font-medium" id="permanentDeleteInvoiceNumber" style="color: var(--danger);"></p>
            
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Reason for permanent deletion (optional)</label>
                <textarea name="reason" id="permanentDeleteReason" rows="2" class="w-full p-2 border rounded" 
                          style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                          placeholder="Please provide a reason for permanently deleting this invoice..."></textarea>
            </div>
            
            <form id="permanentDeleteForm" method="POST">
                @csrf
                @method('DELETE')
                <input type="hidden" name="reason" id="permanentDeleteReasonInput">
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closePermanentDeleteModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded text-white" style="background-color: var(--danger);">
                        <i class="fas fa-trash-alt mr-2"></i> Permanently Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
let currentInvoiceId = null;

function confirmRestore(invoiceId, invoiceNumber) {
    currentInvoiceId = invoiceId;
    const form = document.getElementById('restoreForm');
    form.action = `/admin/tenant-invoices/${invoiceId}/restore`;
    document.getElementById('restoreInvoiceNumber').innerHTML = `Invoice #${invoiceNumber}`;
    document.getElementById('restoreModal').classList.remove('hidden');
}

function closeRestoreModal() {
    document.getElementById('restoreModal').classList.add('hidden');
    currentInvoiceId = null;
}

function confirmPermanentDelete(invoiceId, invoiceNumber) {
    currentInvoiceId = invoiceId;
    const form = document.getElementById('permanentDeleteForm');
    form.action = `/admin/tenant-invoices/${invoiceId}/force-delete`;
    document.getElementById('permanentDeleteInvoiceNumber').innerHTML = `Invoice #${invoiceNumber}`;
    document.getElementById('permanentDeleteModal').classList.remove('hidden');
}

function closePermanentDeleteModal() {
    document.getElementById('permanentDeleteModal').classList.add('hidden');
    document.getElementById('permanentDeleteReason').value = '';
    document.getElementById('permanentDeleteReasonInput').value = '';
    currentInvoiceId = null;
}

// Update hidden input with delete reason before form submission
document.getElementById('permanentDeleteForm')?.addEventListener('submit', function(e) {
    const reasonTextarea = document.getElementById('permanentDeleteReason');
    const reasonInput = document.getElementById('permanentDeleteReasonInput');
    if (reasonTextarea && reasonInput) {
        reasonInput.value = reasonTextarea.value;
    }
});

// Close modals when clicking outside
document.getElementById('restoreModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeRestoreModal();
    }
});

document.getElementById('permanentDeleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closePermanentDeleteModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const restoreModal = document.getElementById('restoreModal');
        if (restoreModal && !restoreModal.classList.contains('hidden')) {
            closeRestoreModal();
        }
        
        const permanentDeleteModal = document.getElementById('permanentDeleteModal');
        if (permanentDeleteModal && !permanentDeleteModal.classList.contains('hidden')) {
            closePermanentDeleteModal();
        }
    }
});
</script>

<style>
/* Button styles */
.btn-primary {
    background-color: var(--primary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-primary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-secondary {
    background-color: var(--secondary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-secondary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-info {
    background-color: var(--info);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-info:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

/* Message styles */
.bg-green-100 {
    background-color: rgba(209, 250, 229, 0.9);
    border-color: rgba(16, 185, 129, 0.3);
}

.bg-red-100 {
    background-color: rgba(254, 226, 226, 0.9);
    border-color: rgba(239, 68, 68, 0.3);
}

.bg-blue-100 {
    background-color: rgba(219, 234, 254, 0.9);
    border-color: rgba(59, 130, 246, 0.3);
}

/* Modal styles */
#restoreModal,
#permanentDeleteModal {
    transition: opacity 0.3s ease;
}

#restoreModal.hidden,
#permanentDeleteModal.hidden {
    opacity: 0;
    pointer-events: none;
}

#restoreModal:not(.hidden),
#permanentDeleteModal:not(.hidden) {
    opacity: 1;
    pointer-events: auto;
}

/* Dark mode adjustments */
.dark .bg-green-100 {
    background-color: rgba(16, 185, 129, 0.2) !important;
    border-color: rgba(16, 185, 129, 0.3) !important;
    color: #10b981 !important;
}

.dark .bg-red-100 {
    background-color: rgba(239, 68, 68, 0.2) !important;
    border-color: rgba(239, 68, 68, 0.3) !important;
    color: #ef4444 !important;
}

.dark .bg-blue-100 {
    background-color: rgba(59, 130, 246, 0.2) !important;
    border-color: rgba(59, 130, 246, 0.3) !important;
    color: #3b82f6 !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid {
        gap: 1rem;
    }
    
    table {
        font-size: 0.875rem;
    }
    
    .btn-primary,
    .btn-secondary,
    .btn-info {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
}
</style>
@endsection