@extends('layouts.app')

@section('title', 'Trashed Properties')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-trash mr-2"></i>Trashed Properties
                <span id="trash-count-badge" class="trash-count-badge" data-initial-count="{{ $trashedProperties->total() }}">
                    {{ $trashedProperties->total() }}
                </span>
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('properties.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Properties
                </a>
                @if($trashedProperties->count() > 0)
                <div class="flex space-x-2">
                    <form action="{{ route('properties.trash.restore-all') }}" method="POST" class="inline" id="restore-all-form">
                        @csrf
                        <button type="submit" class="btn-primary flex items-center" onclick="return confirmRestoreAll()">
                            <i class="fas fa-undo mr-2"></i> Restore All
                        </button>
                    </form>
                    <form action="{{ route('properties.trash.empty') }}" method="POST" class="inline" id="empty-trash-form">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-danger flex items-center" onclick="return confirmEmptyTrash()">
                            <i class="fas fa-trash mr-2"></i> Empty Trash
                        </button>
                    </form>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Status Messages -->
    @if(session('success'))
    <div class="alert-success flex items-center justify-between px-4 py-3 rounded relative" role="alert">
        <div>
            <i class="fas fa-check-circle mr-2"></i>
            <span>{{ session('success') }}</span>
        </div>
        <button type="button" class="text-green-700 hover:text-green-900" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert-error flex items-center justify-between px-4 py-3 rounded relative" role="alert">
        <div>
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span>{{ session('error') }}</span>
        </div>
        <button type="button" class="text-red-700 hover:text-red-900" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Filter Section -->
    <div class="card p-4">
        <form action="{{ route('properties.trash.index') }}" method="GET" id="filter-form">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" 
                           placeholder="Search properties..." 
                           class="w-full px-3 py-2 rounded border" 
                           style="background-color: var(--bg-primary); color: var(--text-primary); border-color: var(--border-color);">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Landlord</label>
                    <select name="landlord_id" class="w-full px-3 py-2 rounded border" 
                            style="background-color: var(--bg-primary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Landlords</option>
                        @foreach($landlords as $landlord)
                            <option value="{{ $landlord->id }}" {{ request('landlord_id') == $landlord->id ? 'selected' : '' }}>
                                {{ $landlord->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Deleted From</label>
                    <input type="date" name="deleted_from" value="{{ request('deleted_from') }}" 
                           class="w-full px-3 py-2 rounded border" 
                           style="background-color: var(--bg-primary); color: var(--text-primary); border-color: var(--border-color);">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Deleted To</label>
                    <input type="date" name="deleted_to" value="{{ request('deleted_to') }}" 
                           class="w-full px-3 py-2 rounded border" 
                           style="background-color: var(--bg-primary); color: var(--text-primary); border-color: var(--border-color);">
                </div>
            </div>
            <div class="mt-4 flex space-x-2">
                <button type="submit" class="btn-primary flex items-center">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <a href="{{ route('properties.trash.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-undo mr-2"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Trashed Properties Table Card -->
    <div class="card p-6">
        <!-- Results Count -->
        <div class="mb-4 flex flex-wrap justify-between items-center gap-2">
            <p class="text-sm" style="color: var(--text-secondary);">
                Showing {{ $trashedProperties->firstItem() ?? 0 }} to {{ $trashedProperties->lastItem() ?? 0 }} of {{ $trashedProperties->total() }} trashed properties
            </p>
            
            @if($trashedProperties->count() > 0)
            <p class="text-sm font-semibold" style="color: var(--warning);">
                <i class="fas fa-exclamation-triangle mr-1"></i> 
                Properties will be automatically permanently deleted after 30 days
            </p>
            @endif
        </div>

        @if($trashedProperties->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">
                            <a href="{{ route('properties.trash.index', array_merge(request()->query(), ['sort' => 'house_number', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}" 
                               class="hover:text-primary flex items-center">
                                House No.
                                @if(request('sort') == 'house_number')
                                    <i class="fas fa-chevron-{{ request('direction') == 'asc' ? 'up' : 'down' }} ml-1"></i>
                                @endif
                            </a>
                        </th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">
                            <a href="{{ route('properties.trash.index', array_merge(request()->query(), ['sort' => 'street_name', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}" 
                               class="hover:text-primary flex items-center">
                                Street Name
                                @if(request('sort') == 'street_name')
                                    <i class="fas fa-chevron-{{ request('direction') == 'asc' ? 'up' : 'down' }} ml-1"></i>
                                @endif
                            </a>
                        </th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Digital Address</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">
                            <a href="{{ route('properties.trash.index', array_merge(request()->query(), ['sort' => 'landlord_id', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}" 
                               class="hover:text-primary flex items-center">
                                Landlord
                                @if(request('sort') == 'landlord_id')
                                    <i class="fas fa-chevron-{{ request('direction') == 'asc' ? 'up' : 'down' }} ml-1"></i>
                                @endif
                            </a>
                        </th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">
                            <a href="{{ route('properties.trash.index', array_merge(request()->query(), ['sort' => 'deleted_at', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}" 
                               class="hover:text-primary flex items-center">
                                Deleted At
                                @if(request('sort') == 'deleted_at')
                                    <i class="fas fa-chevron-{{ request('direction') == 'asc' ? 'up' : 'down' }} ml-1"></i>
                                @endif
                            </a>
                        </th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($trashedProperties as $property)
                    <tr class="border-b hover:bg-opacity-5 transition-colors" style="border-color: var(--border-color);">
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">{{ $property->house_number ?? 'N/A' }}</p>
                            @if($property->block_number)
                                <p class="text-sm" style="color: var(--text-secondary);">Block: {{ $property->block_number }}</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">{{ $property->street_name ?? 'N/A' }}</p>
                            @if($property->zone && $property->section)
                                <p class="text-sm" style="color: var(--text-secondary);">{{ $property->zone }} - {{ $property->section }}</p>
                            @endif
                        </td>
                        <td class="p-3">
                            @if($property->digital_address)
                                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                    <i class="fas fa-check-circle mr-1"></i> {{ $property->digital_address }}
                                </span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Not Assigned
                                </span>
                            @endif
                        </td>
                        <td class="p-3">
                            @if($property->landlord)
                                <p class="font-medium" style="color: var(--text-primary);">{{ $property->landlord->name }}</p>
                                <p class="text-sm" style="color: var(--text-secondary);">{{ $property->landlord->phone }}</p>
                            @else
                                <p class="text-sm" style="color: var(--danger);">Landlord Deleted</p>
                            @endif
                        </td>
                        <td class="p-3">
                            @php
                                $statusColors = [
                                    'active' => 'success',
                                    'inactive' => 'secondary',
                                    'under_maintenance' => 'warning',
                                    'vacant' => 'info',
                                    'under_construction' => 'primary'
                                ];
                                $statusColor = $statusColors[$property->status] ?? 'secondary';
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                                {{ ucfirst(str_replace('_', ' ', $property->status ?? 'Unknown')) }}
                            </span>
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $property->deleted_at ? $property->deleted_at->format('M d, Y') : 'N/A' }}
                            </p>
                            @if($property->deleted_at)
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    {{ $property->deleted_at->diffForHumans() }}
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    Days in trash: {{ now()->diffInDays($property->deleted_at) }}
                                </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex space-x-2">
                                <form action="{{ route('properties.trash.restore', $property->id) }}" method="POST" class="inline restore-form" data-property-id="{{ $property->id }}">
                                    @csrf
                                    <button type="submit" class="p-2 rounded hover:opacity-80 transition-opacity" 
                                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" 
                                            title="Restore" 
                                            onclick="return confirmRestore('{{ $property->property_name }}')">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </form>
                                <form action="{{ route('properties.trash.force-delete', $property->id) }}" method="POST" class="inline force-delete-form" data-property-id="{{ $property->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 rounded hover:opacity-80 transition-opacity" 
                                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);" 
                                            title="Permanently Delete" 
                                            onclick="return confirmForceDelete('{{ $property->property_name }}')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($trashedProperties->hasPages())
        <div class="flex justify-center mt-6">
            {{ $trashedProperties->appends(request()->query())->links() }}
        </div>
        @endif
        @else
        <div class="p-8 text-center">
            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                <i class="fas fa-trash-restore text-6xl mb-4 opacity-30"></i>
                <p class="text-lg font-medium mb-2">No trashed properties</p>
                <p class="text-sm">The trash is empty. Deleted properties will appear here.</p>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // AUTO-HIDE MESSAGES
    // ============================================
    const successMessage = document.querySelector('.alert-success');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 5000);
    }
    
    const errorMessage = document.querySelector('.alert-error');
    if (errorMessage) {
        setTimeout(() => {
            errorMessage.style.display = 'none';
        }, 5000);
    }

    // ============================================
    // CONFIRMATION DIALOGS
    // ============================================
    window.confirmRestore = function(propertyName) {
        return confirm(`Are you sure you want to restore "${propertyName}"?`);
    };

    window.confirmRestoreAll = function() {
        return confirm('Are you sure you want to restore ALL trashed properties?');
    };

    window.confirmForceDelete = function(propertyName) {
        return confirm(`⚠️ PERMANENT DELETION WARNING!\n\nYou are about to permanently delete "${propertyName}".\nThis action CANNOT be undone!\n\nAll associated data will be lost forever.\n\nAre you sure you want to continue?`);
    };

    window.confirmEmptyTrash = function() {
        return confirm(`⚠️ EMPTY TRASH WARNING!\n\nYou are about to PERMANENTLY DELETE ALL trashed properties.\nThis action CANNOT be undone!\n\nAll data in the trash will be lost forever.\n\nAre you sure you want to continue?`);
    };

    // ============================================
    // TRASH COUNT BADGE UPDATES
    // ============================================
    function updateTrashCount() {
        const trashBadge = document.getElementById('trash-count-badge');
        if (!trashBadge) return;
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        fetch('{{ route("properties.trash.count") }}', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const count = data.count;
                const previousCount = parseInt(trashBadge.getAttribute('data-initial-count') || 0);
                trashBadge.textContent = count;
                trashBadge.style.display = count > 0 ? 'inline-flex' : 'none';
                trashBadge.setAttribute('data-initial-count', count);
                
                // Update the "Empty Trash" and "Restore All" buttons visibility
                const bulkActions = document.querySelector('.flex-space-x-2 .btn-primary, .flex-space-x-2 .btn-danger');
                if (bulkActions) {
                    const parentContainer = bulkActions.closest('.flex-space-x-2');
                    if (parentContainer) {
                        // The buttons are conditionally rendered via Blade
                        // We'll need to reload the page or use AJAX to update
                        // For now, we'll just update the badge
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error updating trash count:', error);
        });
    }
    
    // Update trash count every 30 seconds
    const trashBadge = document.getElementById('trash-count-badge');
    if (trashBadge) {
        updateTrashCount();
        setInterval(updateTrashCount, 30000);
    }

    // ============================================
    // AJAX FORM SUBMISSION (Optional Enhancement)
    // ============================================
    // This allows restoring/deleting without page reload
    // Uncomment if you want AJAX functionality
    
    /*
    // Restore form handlers
    document.querySelectorAll('.restore-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!confirmRestore(this.dataset.propertyName)) {
                return;
            }
            
            const formData = new FormData(this);
            
            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    updateTrashCount();
                    // Remove the row or reload
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    showAlert('error', data.message);
                }
            })
            .catch(error => {
                showAlert('error', 'An error occurred. Please try again.');
            });
        });
    });
    
    // Force delete form handlers
    document.querySelectorAll('.force-delete-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!confirmForceDelete(this.dataset.propertyName)) {
                return;
            }
            
            const formData = new FormData(this);
            
            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    updateTrashCount();
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    showAlert('error', data.message);
                }
            })
            .catch(error => {
                showAlert('error', 'An error occurred. Please try again.');
            });
        });
    });
    
    // Alert helper
    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert-${type} flex items-center justify-between px-4 py-3 rounded relative mb-4`;
        alertDiv.innerHTML = `
            <div>
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} mr-2"></i>
                <span>${message}</span>
            </div>
            <button type="button" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        `;
        document.querySelector('.grid.grid-cols-1.gap-6').prepend(alertDiv);
        
        setTimeout(() => {
            alertDiv.style.display = 'none';
        }, 5000);
    }
    */
});
</script>

<style>
/* ============================================
   BUTTON STYLES
   ============================================ */
.btn-primary {
    background-color: var(--primary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.btn-primary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.btn-secondary {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: 1px solid var(--border-color);
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.btn-secondary:hover {
    background-color: var(--bg-tertiary);
    transform: translateY(-1px);
    text-decoration: none;
    color: var(--text-primary);
}

.btn-danger {
    background-color: var(--danger);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    cursor: pointer;
}

.btn-danger:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.btn-danger:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* ============================================
   ALERT MESSAGES
   ============================================ */
.alert-success {
    background-color: rgba(209, 250, 229, 0.9);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #065f46;
}

.alert-error {
    background-color: rgba(254, 226, 226, 0.9);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #991b1b;
}

/* ============================================
   CARD STYLING
   ============================================ */
.card {
    background-color: var(--bg-secondary);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border-color);
    border-radius: 0.75rem;
    transition: all 0.2s;
}

.card:hover {
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
}

/* ============================================
   TRASH BADGE
   ============================================ */
.trash-count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    padding: 0 8px;
    margin-left: 8px;
    font-size: 12px;
    font-weight: 700;
    background-color: var(--danger, #dc2626);
    color: white;
    border-radius: 9999px;
    transition: all 0.3s ease;
    line-height: 1;
}

.trash-count-badge:empty {
    display: none;
}

/* ============================================
   ANIMATIONS
   ============================================ */
@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.15);
        opacity: 0.8;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.animate-pulse {
    animation: pulse 0.5s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-success, .alert-error {
    animation: fadeIn 0.3s ease-out;
}

/* ============================================
   TABLE STYLES
   ============================================ */
table {
    border-collapse: collapse;
}

thead th {
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 0.75rem;
}

tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.03);
}

/* ============================================
   RESPONSIVE ADJUSTMENTS
   ============================================ */
@media (max-width: 768px) {
    .flex-space-x-2 {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .btn-primary, .btn-secondary, .btn-danger {
        width: 100%;
        justify-content: center;
    }
    
    .card {
        padding: 1rem;
    }
    
    .grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 640px) {
    .grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
}

/* ============================================
   SCROLLBAR STYLING
   ============================================ */
.overflow-x-auto::-webkit-scrollbar {
    height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: var(--bg-primary);
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}
</style>
@endsection