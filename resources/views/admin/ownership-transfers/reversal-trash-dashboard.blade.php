{{-- admin/ownership-transfers/reversal-trash-dashboard.blade.php --}}
@php
    $pageTitle = 'Reversal Records in Trash - Dashboard';
    $layout = 'layouts.app';
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--warning) 0%, var(--danger) 100%); color: white; font-weight: 600; border-color: var(--warning);">
                        <i class="fas fa-undo-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-undo-alt mr-2" style="color: var(--warning);"></i> 
                        Reversal Records in Trash
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Manage reversal audit records that have been soft-deleted</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-trash mr-1"></i>
                        <span>{{ number_format($stats['total_reversals_in_trash'] ?? 0) }}</span> reversal records in trash
                        @if(($stats['orphaned_reversals'] ?? 0) > 0)
                        <span class="mx-2">•</span>
                        <i class="fas fa-exclamation-triangle mr-1" style="color: var(--danger);"></i>
                        <span class="text-danger">{{ $stats['orphaned_reversals'] }} orphaned</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ url('/admin/ownership-transfers/trash') }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-trash-alt mr-1"></i> View All Trash
                </a>
                <a href="{{ route('admin.ownership-transfers.index') }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-exchange-alt mr-1"></i> Active Transfers
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-undo-alt text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Reversals</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($stats['total_reversals_in_trash'] ?? 0) }}</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-link text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">With Original</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($stats['reversals_with_original'] ?? 0) }}</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-link-broken text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Orphaned</p>
                    <p class="text-2xl font-bold {{ ($stats['orphaned_reversals'] ?? 0) > 0 ? 'text-danger' : '' }}" 
                       style="color: {{ ($stats['orphaned_reversals'] ?? 0) > 0 ? 'var(--danger)' : 'var(--text-primary)' }};">
                        {{ number_format($stats['orphaned_reversals'] ?? 0) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-database text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Storage Used</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($stats['storage_used_mb'] ?? 0, 2) }} MB</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-calendar-week text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">> 90 Days in Trash</p>
                    <p class="text-2xl font-bold {{ ($stats['reversals_by_date']['older_than_90_days'] ?? 0) > 0 ? 'text-warning' : '' }}" 
                       style="color: {{ ($stats['reversals_by_date']['older_than_90_days'] ?? 0) > 0 ? 'var(--warning)' : 'var(--text-primary)' }};">
                        {{ number_format($stats['reversals_by_date']['older_than_90_days'] ?? 0) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Warning Banner for Orphaned Reversals -->
    @if(($stats['orphaned_reversals'] ?? 0) > 0)
    <div class="bg-orange-50 border-l-4 border-orange-400 p-4 mb-6 rounded-r-lg">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-orange-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-orange-700">
                    <strong>Orphaned Reversal Records:</strong> {{ number_format($stats['orphaned_reversals']) }} reversal record(s) are missing their original transfer.
                    These can be safely deleted to clean up the database.
                </p>
                <div class="mt-2">
                    <a href="{{ url('/admin/ownership-transfers/trash?type=orphaned_reversals') }}" 
                       class="text-sm text-orange-600 hover:text-orange-800">
                        <i class="fas fa-arrow-right mr-1"></i> View Orphaned Reversals
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Left Column -->
        <div class="space-y-6">
            
            <!-- Reversals by Status Card -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-pie mr-2" style="color: var(--warning);"></i> Reversals by Status
                    </h3>
                    <div class="space-y-3">
                        @php
                            $statusColors = [
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                'cancelled' => 'secondary',
                                'completed' => 'info',
                            ];
                            $totalStatusCount = 0;
                            foreach($stats['reversals_by_status'] as $item) {
                                $totalStatusCount += $item->count;
                            }
                        @endphp
                        
                        @foreach($stats['reversals_by_status'] as $item)
                            @php
                                $percentage = $totalStatusCount > 0 ? round(($item->count / $totalStatusCount) * 100, 1) : 0;
                                $color = $statusColors[$item->status] ?? 'secondary';
                            @endphp
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">
                                        <span class="inline-block w-3 h-3 rounded-full mr-2" style="background-color: var(--{{ $color }});"></span>
                                        {{ ucfirst($item->status) }}
                                    </span>
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">
                                        {{ number_format($item->count) }} ({{ $percentage }}%)
                                    </span>
                                </div>
                                <div class="w-full rounded-full h-2" style="background-color: rgba(var(--{{ $color }}-rgb), 0.2);">
                                    <div class="rounded-full h-2" style="width: {{ $percentage }}%; background-color: var(--{{ $color }});"></div>
                                </div>
                            </div>
                        @endforeach
                        
                        @if(count($stats['reversals_by_status']) == 0)
                            <div class="text-center py-8">
                                <i class="fas fa-chart-pie text-4xl mb-2" style="color: var(--text-secondary);"></i>
                                <p class="text-sm" style="color: var(--text-secondary);">No reversal records found in trash.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Reversals by Date Card -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-alt mr-2" style="color: var(--warning);"></i> Time in Trash
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-calendar-day mr-2 text-info"></i> Today
                            </span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ number_format($stats['reversals_by_date']['today'] ?? 0) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-calendar-week mr-2 text-info"></i> This Week
                            </span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ number_format($stats['reversals_by_date']['this_week'] ?? 0) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-hourglass-half mr-2 text-warning"></i> Older than 30 days
                            </span>
                            <span class="text-sm font-medium {{ ($stats['reversals_by_date']['older_than_30_days'] ?? 0) > 0 ? 'text-warning' : '' }}" 
                                   style="color: {{ ($stats['reversals_by_date']['older_than_30_days'] ?? 0) > 0 ? 'var(--warning)' : 'var(--text-primary)' }};">
                                {{ number_format($stats['reversals_by_date']['older_than_30_days'] ?? 0) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-2">
                            <span class="text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-hourglass-end mr-2 text-danger"></i> Older than 90 days
                            </span>
                            <span class="text-sm font-bold {{ ($stats['reversals_by_date']['older_than_90_days'] ?? 0) > 0 ? 'text-danger' : '' }}" 
                                   style="color: {{ ($stats['reversals_by_date']['older_than_90_days'] ?? 0) > 0 ? 'var(--danger)' : 'var(--text-primary)' }};">
                                {{ number_format($stats['reversals_by_date']['older_than_90_days'] ?? 0) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-6">
            
            <!-- Reversals by Original Status Card -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-exchange-alt mr-2" style="color: var(--warning);"></i> Original Transfer Status
                    </h3>
                    <div class="space-y-3">
                        @php
                            $totalOriginalCount = 0;
                            foreach($stats['reversals_by_original_status'] as $item) {
                                $totalOriginalCount += $item->count;
                            }
                        @endphp
                        
                        @foreach($stats['reversals_by_original_status'] as $item)
                            @php
                                $percentage = $totalOriginalCount > 0 ? round(($item->count / $totalOriginalCount) * 100, 1) : 0;
                                $color = $statusColors[$item->status] ?? 'secondary';
                            @endphp
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">
                                        <span class="inline-block w-3 h-3 rounded-full mr-2" style="background-color: var(--{{ $color }});"></span>
                                        {{ ucfirst($item->status) }}
                                    </span>
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">
                                        {{ number_format($item->count) }} ({{ $percentage }}%)
                                    </span>
                                </div>
                                <div class="w-full rounded-full h-2" style="background-color: rgba(var(--{{ $color }}-rgb), 0.2);">
                                    <div class="rounded-full h-2" style="width: {{ $percentage }}%; background-color: var(--{{ $color }});"></div>
                                </div>
                            </div>
                        @endforeach
                        
                        @if(count($stats['reversals_by_original_status']) == 0)
                            <div class="text-center py-8">
                                <i class="fas fa-exchange-alt text-4xl mb-2" style="color: var(--text-secondary);"></i>
                                <p class="text-sm" style="color: var(--text-secondary);">No reversal records with original transfers found.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Deleted By Card -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-2" style="color: var(--warning);"></i> Deleted By
                    </h3>
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        @forelse($stats['by_deleted_by'] as $item)
                            <div class="flex justify-between items-center py-2 border-b" style="border-color: var(--border-color);">
                                <span class="text-sm" style="color: var(--text-primary);">
                                    <i class="fas fa-user-circle mr-2" style="color: var(--info);"></i>
                                    {{ $item->deleted_by }}
                                </span>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ number_format($item->count) }} record{{ $item->count != 1 ? 's' : '' }}
                                </span>
                            </div>
                        @empty
                            <div class="text-center py-8">
                                <i class="fas fa-users text-4xl mb-2" style="color: var(--text-secondary);"></i>
                                <p class="text-sm" style="color: var(--text-secondary);">No deletion records found.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Card -->
    <div class="card">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-bolt mr-2" style="color: var(--warning);"></i> Quick Actions
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="{{ url('/admin/ownership-transfers/trash?type=reversal') }}" 
                   class="flex items-center justify-center p-4 rounded-lg text-center transition-all hover:transform hover:scale-105"
                   style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-undo-alt text-xl mr-3" style="color: var(--warning);"></i>
                    <div>
                        <div class="font-medium" style="color: var(--text-primary);">View All Reversals</div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">See all reversal records in trash</div>
                    </div>
                </a>
                
                <a href="{{ url('/admin/ownership-transfers/trash?type=orphaned_reversals') }}" 
                   class="flex items-center justify-center p-4 rounded-lg text-center transition-all hover:transform hover:scale-105"
                   style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-link-broken text-xl mr-3" style="color: var(--danger);"></i>
                    <div>
                        <div class="font-medium" style="color: var(--text-primary);">View Orphaned</div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">See reversal records missing original</div>
                    </div>
                </a>
                
                <a href="{{ url('/admin/ownership-transfers/trash?type=reversal_with_original') }}" 
                   class="flex items-center justify-center p-4 rounded-lg text-center transition-all hover:transform hover:scale-105"
                   style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-link text-xl mr-3" style="color: var(--success);"></i>
                    <div>
                        <div class="font-medium" style="color: var(--text-primary);">View With Original</div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">See reversals linked to original</div>
                    </div>
                </a>
            </div>
            
            @if(($stats['orphaned_reversals'] ?? 0) > 0)
            <div class="mt-4 p-3 rounded-lg text-center" style="background-color: rgba(var(--danger-rgb), 0.05);">
                <p class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Orphaned reversal records have no linked original transfer. They can be safely deleted.
                </p>
                <button onclick="confirmDeleteOrphaned()" 
                        class="mt-2 btn-danger px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center">
                    <i class="fas fa-trash-alt mr-2"></i> Delete All Orphaned Reversals
                </button>
            </div>
            @endif
        </div>
    </div>
</div>

<script>
function confirmDeleteOrphaned() {
    if (confirm('⚠️ WARNING: Are you sure you want to delete ALL orphaned reversal records? This action cannot be undone!')) {
        showNotification('info', 'Fetching orphaned reversals...');
        
        // Get all orphaned reversal IDs using direct URL
        fetch('/admin/ownership-transfers/trash?type=orphaned_reversals&per_page=1000')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.transfers && data.transfers.data) {
                    const reversalIds = data.transfers.data.map(t => t.id);
                    if (reversalIds.length > 0) {
                        deleteBulkReversals(reversalIds);
                    } else {
                        showNotification('error', 'No orphaned reversals found.');
                    }
                } else {
                    showNotification('error', 'Failed to fetch orphaned reversals.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'An error occurred while fetching orphaned reversals.');
            });
    }
}

function deleteBulkReversals(ids) {
    showNotification('info', 'Deleting orphaned reversals...');
    
    // Use direct URL for the bulk delete endpoint
    fetch('/admin/ownership-transfers/trash/bulk-force-delete-reversals', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ 
            reversal_ids: ids,
            delete_originals: false
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message);
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification('error', data.message || 'An error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred while processing the request');
    });
}

function showNotification(type, message) {
    // Remove any existing notifications
    const existingContainer = document.getElementById('notificationContainer');
    if (existingContainer) {
        existingContainer.remove();
    }
    
    // Create new notification container
    const container = document.createElement('div');
    container.id = 'notificationContainer';
    container.className = 'fixed top-4 right-4 z-50 space-y-2';
    document.body.appendChild(container);
    
    const notificationDiv = document.createElement('div');
    notificationDiv.className = `mb-4 p-4 rounded-lg shadow-lg min-w-[300px]`;
    notificationDiv.style.backgroundColor = type === 'success' ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)';
    notificationDiv.style.border = type === 'success' ? '1px solid rgba(var(--success-rgb), 0.3)' : '1px solid rgba(var(--danger-rgb), 0.3)';
    notificationDiv.style.animation = 'slideIn 0.3s ease';
    notificationDiv.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2" style="color: ${type === 'success' ? 'var(--success)' : 'var(--danger)'};"></i>
                <span style="color: ${type === 'success' ? 'var(--success)' : 'var(--danger)'};">${escapeHtml(message)}</span>
            </div>
            <button type="button" onclick="this.closest('#notificationContainer').remove()" class="text-gray-500 hover:text-gray-700 ml-4">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    container.appendChild(notificationDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (container.parentNode) {
            container.remove();
        }
    }, 5000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Add animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);
</script>

<style>
/* Additional styles for the dashboard */
.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
}

.btn-danger {
    background-color: var(--danger) !important;
    color: white !important;
    border: 1px solid var(--danger) !important;
    cursor: pointer;
}

.btn-danger:hover {
    background-color: #dc3545 !important;
}
</style>
@endsection