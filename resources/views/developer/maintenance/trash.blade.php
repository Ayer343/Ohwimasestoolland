@extends('layouts.dev')

@section('title', 'Maintenance Trash')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Maintenance Trash
                </h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    View and manage deleted maintenance schedules
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('developer.maintenance.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                </a>
                @if($trashedMaintenances->total() > 0)
                <form action="{{ route('developer.maintenance.trash.empty') }}" method="POST" 
                      onsubmit="return confirmEmptyTrash()" class="inline">
                    @csrf
                    <button type="submit" class="btn-danger flex items-center">
                        <i class="fas fa-trash mr-2"></i> Empty Trash
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>

    <!-- Statistics Card -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total in Trash</div>
                    <div class="text-2xl font-semibold">{{ $trashedMaintenances->total() }}</div>
                </div>
                <i class="fas fa-trash-alt text-2xl opacity-70"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Oldest Item</div>
                    <div class="text-lg font-semibold">
                        @if($oldestItem)
                            {{ $oldestItem->deleted_at->diffForHumans() }}
                        @else
                            N/A
                        @endif
                    </div>
                </div>
                <i class="fas fa-calendar-times text-2xl opacity-70"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Size</div>
                    <div class="text-lg font-semibold">
                        {{ number_format($trashedMaintenances->total() * 2) }} KB
                    </div>
                </div>
                <i class="fas fa-database text-2xl opacity-70"></i>
            </div>
        </div>

        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Restored Items</div>
                    <div class="text-lg font-semibold">
                        {{ $restoredCount ?? 0 }}
                    </div>
                </div>
                <i class="fas fa-redo-alt text-2xl opacity-70"></i>
            </div>
        </div>
    </div>

    <!-- Warning Banner -->
    <div class="card" style="border-left: 4px solid var(--warning);">
        <div class="p-6">
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle mt-1 mr-3 text-lg" style="color: var(--warning);"></i>
                <div>
                    <h3 class="font-medium mb-2" style="color: var(--warning);">Important Information</h3>
                    <ul class="space-y-2 text-sm" style="color: var(--text-secondary);">
                        <li class="flex items-start">
                            <i class="fas fa-circle text-xs mt-1 mr-2" style="color: var(--warning);"></i>
                            Deleted items are automatically removed after 30 days
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-circle text-xs mt-1 mr-2" style="color: var(--warning);"></i>
                            Restoring an item will return it to "draft" status
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-circle text-xs mt-1 mr-2" style="color: var(--warning);"></i>
                            Permanent deletion cannot be undone
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card">
        <div class="p-6">
            <form action="{{ route('developer.maintenance.trash') }}" method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" 
                               class="w-full p-2 border rounded" 
                               placeholder="Search title, description..." 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Deleted By</label>
                        <select name="deleted_by" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Users</option>
                            @foreach($deletedByUsers as $user)
                                <option value="{{ $user->id }}" {{ request('deleted_by') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Deleted Date From</label>
                        <input type="date" name="deleted_from" value="{{ request('deleted_from') }}" 
                               class="w-full p-2 border rounded" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>
                    
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Deleted Date To</label>
                        <input type="date" name="deleted_to" value="{{ request('deleted_to') }}" 
                               class="w-full p-2 border rounded" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>
                </div>
                
                <div class="flex justify-between items-center">
                    <div class="text-sm" style="color: var(--text-secondary);">
                        Showing {{ $trashedMaintenances->firstItem() ?? 0 }} to {{ $trashedMaintenances->lastItem() ?? 0 }} of {{ $trashedMaintenances->total() }} deleted items
                    </div>
                    
                    <div class="flex space-x-2">
                        <button type="submit" class="btn-primary w-full md:w-auto">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                        <a href="{{ route('developer.maintenance.trash') }}" class="btn-secondary w-full md:w-auto">
                            <i class="fas fa-times mr-2"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Trash List -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-list mr-2"></i> Deleted Maintenance Schedules
                    </h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Items will be permanently deleted after 30 days
                    </p>
                </div>
                <div class="text-sm" style="color: var(--text-secondary);">
                    {{ $trashedMaintenances->total() }} items in trash
                </div>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr style="background-color: var(--bg-secondary);">
                        <th class="p-3 text-left" style="color: var(--text-secondary);">
                            <input type="checkbox" id="selectAll" class="rounded">
                        </th>
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Reference ID</th>
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Title</th>
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Status</th>
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Deleted By</th>
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Deleted At</th>
                        <th class="p-3 text-left" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trashedMaintenances as $maintenance)
                    <tr class="border-b hover:bg-opacity-5 transition-colors duration-200" style="border-color: var(--border-color);">
                        <td class="p-3">
                            <input type="checkbox" name="selected_items[]" value="{{ $maintenance->id }}" 
                                   class="item-checkbox rounded">
                        </td>
                        <td class="p-3" style="color: var(--text-primary);">
                            <div class="font-mono text-sm">{{ $maintenance->reference_id }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                Created: {{ $maintenance->created_at->format('M d, Y') }}
                            </div>
                        </td>
                        <td class="p-3">
                            <div class="font-medium" style="color: var(--text-primary);">{{ Str::limit($maintenance->title, 50) }}</div>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ Str::limit($maintenance->description, 70) }}
                            </div>
                            <div class="flex flex-wrap gap-1 mt-1">
                                @if($maintenance->is_emergency)
                                <span class="inline-block px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800">
                                    Emergency
                                </span>
                                @endif
                                <span class="inline-block px-2 py-0.5 text-xs rounded-full capitalize"
                                    style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    {{ str_replace('_', ' ', $maintenance->maintenance_type) }}
                                </span>
                            </div>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-1 text-xs rounded-full capitalize"
                                style="background-color: {{ $maintenance->status === 'in_progress' ? 'rgba(var(--warning-rgb), 0.1)' : 
                                                         ($maintenance->status === 'completed' ? 'rgba(var(--success-rgb), 0.1)' : 
                                                         ($maintenance->status === 'cancelled' ? 'rgba(var(--danger-rgb), 0.1)' : 
                                                         'rgba(var(--info-rgb), 0.1)')) }};
                                       color: {{ $maintenance->status === 'in_progress' ? 'var(--warning)' : 
                                               ($maintenance->status === 'completed' ? 'var(--success)' : 
                                               ($maintenance->status === 'cancelled' ? 'var(--danger)' : 'var(--info)')) }};
                                       border: 1px solid {{ $maintenance->status === 'in_progress' ? 'rgba(var(--warning-rgb), 0.3)' : 
                                                         ($maintenance->status === 'completed' ? 'rgba(var(--success-rgb), 0.3)' : 
                                                         ($maintenance->status === 'cancelled' ? 'rgba(var(--danger-rgb), 0.3)' : 
                                                         'rgba(var(--info-rgb), 0.3)')) }};">
                                {{ str_replace('_', ' ', $maintenance->status) }}
                            </span>
                        </td>
                        <td class="p-3" style="color: var(--text-primary);">
                            @if($maintenance->deletedBy)
                            <div class="flex items-center">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center mr-2"
                                     style="background-color: rgba(var(--danger-rgb), 0.2); color: var(--danger);">
                                    <i class="fas fa-user-times text-xs"></i>
                                </div>
                                <span>{{ $maintenance->deletedBy->name }}</span>
                            </div>
                            @else
                            <span class="text-sm" style="color: var(--text-secondary);">System</span>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="text-sm" style="color: var(--text-primary);">
                                {{ $maintenance->deleted_at->format('M d, Y H:i') }}
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                {{ $maintenance->deleted_at->diffForHumans() }}
                            </div>
                            <div class="text-xs mt-1" style="color: var(--danger);">
                                <i class="fas fa-clock mr-1"></i>
                                Auto-delete in {{ $maintenance->deleted_at->addDays(30)->diffForHumans() }}
                            </div>
                        </td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-1">
                                <!-- View Details Button -->
                                <button type="button" onclick="showMaintenanceDetails({{ $maintenance->id }})" 
                                        class="p-2 rounded hover:bg-opacity-20 transition-colors"
                                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <!-- Restore Button -->
                                <form action="{{ route('developer.maintenance.trash.restore', $maintenance->id) }}" 
                                      method="POST" class="inline">
                                    @csrf
                                    <button type="submit" 
                                            onclick="return confirm('Restore this maintenance schedule?')"
                                            class="p-2 rounded hover:bg-opacity-20 transition-colors"
                                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                            title="Restore">
                                        <i class="fas fa-redo-alt"></i>
                                    </button>
                                </form>
                                
                                <!-- Permanent Delete Button -->
                                <form action="{{ route('developer.maintenance.trash.force-delete', $maintenance->id) }}" 
                                      method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            onclick="return confirmForceDelete()"
                                            class="p-2 rounded hover:bg-opacity-20 transition-colors"
                                            style="background-color: rgba(var(--danger-rgb), 0.2); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);"
                                            title="Delete Permanently">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center"
                                 style="background-color: rgba(var(--text-secondary), 0.1);">
                                <i class="fas fa-trash-alt" style="color: var(--text-secondary); font-size: 1.5rem;"></i>
                            </div>
                            <h4 class="font-medium mb-2" style="color: var(--text-primary);">Trash is Empty</h4>
                            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                No deleted maintenance schedules found.
                            </p>
                            <a href="{{ route('developer.maintenance.index') }}" class="btn-primary">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Maintenance
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Bulk Actions -->
        @if($trashedMaintenances->count() > 0)
        <div class="p-4 border-t" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    <select id="bulkAction" class="p-2 border rounded text-sm"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">Bulk Actions</option>
                        <option value="restore">Restore Selected</option>
                        <option value="force_delete">Delete Permanently</option>
                    </select>
                    <button type="button" onclick="applyBulkAction()" class="btn-primary text-sm">
                        Apply
                    </button>
                    <span class="text-sm" style="color: var(--text-secondary);" id="selectedCount">
                        0 items selected
                    </span>
                </div>
                @if($trashedMaintenances->hasPages())
                <div>
                    {{ $trashedMaintenances->links() }}
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="card w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2"></i> Maintenance Details
                </h3>
                <button type="button" onclick="closeModal('detailsModal')" 
                        class="p-2 rounded-lg hover:bg-opacity-20"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="detailsContent">
                <!-- Details will be loaded via AJAX -->
            </div>
            
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('detailsModal')" class="btn-secondary">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Setup select all checkbox
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }
    
    // Setup individual checkbox change events
    document.querySelectorAll('.item-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
    
    // Setup filter auto-submit
    document.querySelectorAll('select[name="deleted_by"]').forEach(select => {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
});

function updateSelectedCount() {
    const selectedCount = document.querySelectorAll('.item-checkbox:checked').length;
    const countElement = document.getElementById('selectedCount');
    if (countElement) {
        countElement.textContent = `${selectedCount} item${selectedCount !== 1 ? 's' : ''} selected`;
    }
    
    // Update select all checkbox state
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        const totalCheckboxes = document.querySelectorAll('.item-checkbox').length;
        const checkedCheckboxes = document.querySelectorAll('.item-checkbox:checked').length;
        selectAll.checked = checkedCheckboxes === totalCheckboxes && totalCheckboxes > 0;
        selectAll.indeterminate = checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes;
    }
}

function applyBulkAction() {
    const action = document.getElementById('bulkAction').value;
    const selectedItems = Array.from(document.querySelectorAll('.item-checkbox:checked'))
        .map(checkbox => checkbox.value);
    
    if (!action) {
        alert('Please select a bulk action.');
        return;
    }
    
    if (selectedItems.length === 0) {
        alert('Please select at least one item.');
        return;
    }
    
    let confirmMessage = '';
    let route = '';
    
    switch (action) {
        case 'restore':
            confirmMessage = `Are you sure you want to restore ${selectedItems.length} item(s)?`;
            route = '{{ route("developer.maintenance.trash.bulk-restore") }}';
            break;
        case 'force_delete':
            confirmMessage = `⚠️ WARNING: This will permanently delete ${selectedItems.length} item(s)!\n\nThis action cannot be undone.\n\nAre you sure?`;
            route = '{{ route("developer.maintenance.trash.bulk-force-delete") }}';
            break;
    }
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    // Create form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = route;
    
    const csrfToken = document.createElement('input');
    csrfToken.type = 'hidden';
    csrfToken.name = '_token';
    csrfToken.value = document.querySelector('meta[name="csrf-token"]').content;
    form.appendChild(csrfToken);
    
    if (action === 'force_delete') {
        const methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        methodInput.value = 'DELETE';
        form.appendChild(methodInput);
    }
    
    selectedItems.forEach(itemId => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'items[]';
        input.value = itemId;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
}

async function showMaintenanceDetails(maintenanceId) {
    try {
        const response = await fetch(`/developer/maintenance/trash/${maintenanceId}/details`, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        if (!response.ok) {
            throw new Error('Failed to load details');
        }
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('detailsContent').innerHTML = data.html;
            document.getElementById('detailsModal').classList.remove('hidden');
        } else {
            throw new Error(data.message || 'Failed to load details');
        }
    } catch (error) {
        console.error('Error loading details:', error);
        alert('Failed to load maintenance details.');
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function confirmEmptyTrash() {
    const count = {{ $trashedMaintenances->total() }};
    return confirm(`⚠️ WARNING: This will permanently delete ALL ${count} item(s) in trash!\n\nThis action cannot be undone.\n\nAre you sure you want to empty the trash?`);
}

function confirmForceDelete() {
    return confirm(`⚠️ WARNING: This will permanently delete this maintenance schedule!\n\nThis action cannot be undone.\n\nAre you sure?`);
}

// Close modal when clicking outside
document.querySelectorAll('.fixed.inset-0').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('detailsModal');
    }
    if (e.key === 'a' && e.ctrlKey) {
        e.preventDefault();
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.click();
        }
    }
});
</script>

<style>
/* Checkbox styling */
input[type="checkbox"] {
    cursor: pointer;
    width: 16px;
    height: 16px;
}

input[type="checkbox"]:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* Table row styling */
tbody tr {
    transition: all 0.2s ease;
}

tbody tr:hover {
    background-color: rgba(var(--danger-rgb), 0.02) !important;
}

/* Action buttons */
.flex.gap-1 a,
.flex.gap-1 button {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.flex.gap-1 a:hover,
.flex.gap-1 button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Permanent delete button specific styling */
.flex.gap-1 form button[title="Delete Permanently"]:hover {
    background-color: rgba(var(--danger-rgb), 0.3) !important;
    border-color: rgba(var(--danger-rgb), 0.5) !important;
}

/* Auto-delete warning */
.text-xs.mt-1[style*="color: var(--danger)"] {
    font-weight: 500;
}

/* Responsive design */
@media (max-width: 768px) {
    .overflow-x-auto {
        font-size: 0.875rem;
    }
    
    .overflow-x-auto th,
    .overflow-x-auto td {
        padding: 0.75rem 0.5rem;
    }
    
    .grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    /* Adjust action buttons for mobile */
    .flex.gap-1 {
        gap: 0.25rem;
    }
    
    .flex.gap-1 a,
    .flex.gap-1 button {
        width: 28px;
        height: 28px;
        font-size: 0.8rem;
    }
    
    /* Hide checkbox on very small screens */
    @media (max-width: 640px) {
        th:first-child,
        td:first-child {
            display: none;
        }
    }
}

/* Bulk actions styling */
#bulkAction {
    min-width: 150px;
}

#selectedCount {
    margin-left: 1rem;
    padding: 0.25rem 0.5rem;
    background-color: rgba(var(--primary-rgb), 0.1);
    border-radius: 4px;
    color: var(--primary);
}

/* Fade animation for deleted items */
@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0.3; }
}

.deleting {
    animation: fadeOut 0.3s ease-out;
    pointer-events: none;
}
</style>
@endsection