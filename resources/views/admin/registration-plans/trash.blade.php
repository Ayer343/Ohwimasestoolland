@extends('layouts.app')

@section('title', 'Registration Plans - Trash')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="mb-4 md:mb-0">
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Registration Plans Trash</h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Deleted registration plans are kept here for 30 days before being permanently removed.
                    @if($plans->total() > 0)
                        <span class="block mt-1">Showing {{ $plans->total() }} trashed plan{{ $plans->total() !== 1 ? 's' : '' }}.</span>
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($plans->total() > 0)
                    <form action="{{ route('registration-plans.empty-trash') }}" method="POST" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-modern flex items-center px-4 py-3"
                                style="background: linear-gradient(to right, var(--danger), #e53935);"
                                onclick="return confirmEmptyTrash({{ $totalTrashedCount }})">
                            <i class="fas fa-trash mr-2"></i> Empty Trash
                        </button>
                    </form>
                @endif
                <a href="{{ route('registration-plans.index') }}" class="btn-secondary flex items-center px-4 py-2 rounded-lg">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Plans
                </a>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4" role="alert" style="background-color: rgba(var(--success-rgb), 0.1); border-color: rgba(var(--success-rgb), 0.3); color: var(--success);">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <strong class="font-bold">Success!</strong>
                <span class="block sm:inline ml-2">{{ session('success') }}</span>
            </div>
            <button type="button" class="text-green-700 hover:text-green-900" onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert" style="background-color: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.3); color: var(--danger);">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline ml-2">{{ session('error') }}</span>
            </div>
            <button type="button" class="text-red-700 hover:text-red-900" onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Statistics Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="stat-card users-card">
            <div class="text-3xl font-bold" style="color: var(--text-primary);">{{ $plans->total() }}</div>
            <div class="text-sm font-medium mt-1" style="color: var(--text-secondary);">Trashed Plans</div>
            <div class="text-xs mt-2" style="color: var(--text-secondary);">Currently in trash</div>
        </div>
        <div class="stat-card" style="border-left-color: var(--warning);">
            <div class="text-3xl font-bold" style="color: var(--text-primary);">
                {{ $plans->where('properties_count', '>', 0)->count() }}
            </div>
            <div class="text-sm font-medium mt-1" style="color: var(--text-secondary);">With Properties</div>
            <div class="text-xs mt-2" style="color: var(--text-secondary);">Contains registered data</div>
        </div>
        <div class="stat-card conversion-card">
            <div class="text-3xl font-bold" style="color: var(--text-primary);">
                {{ $plans->where('properties_count', 0)->count() }}
            </div>
            <div class="text-sm font-medium mt-1" style="color: var(--text-secondary);">Safe to Delete</div>
            <div class="text-xs mt-2" style="color: var(--text-secondary);">No registered properties</div>
        </div>
        <div class="stat-card bounce-card">
            <div class="text-3xl font-bold" style="color: var(--text-primary);">
                {{ $recentlyDeletedCount }}
            </div>
            <div class="text-sm font-medium mt-1" style="color: var(--text-secondary);">Deleted This Week</div>
            <div class="text-xs mt-2" style="color: var(--text-secondary);">Recent deletions</div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6 mb-6">
        <form method="GET" action="{{ route('registration-plans.trash') }}">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Original Status</label>
                    <select name="status" class="form-select w-full p-3 rounded-lg">
                        <option value="all">All Statuses</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Zone</label>
                    <input type="text" name="zone" value="{{ request('zone') }}" placeholder="Search zone..." 
                           class="form-input w-full p-3 rounded-lg">
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Section</label>
                    <input type="text" name="section" value="{{ request('section') }}" placeholder="Search section..." 
                           class="form-input w-full p-3 rounded-lg">
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Deleted Date</label>
                    <select name="deleted_period" class="form-select w-full p-3 rounded-lg">
                        <option value="all">All Time</option>
                        <option value="today" {{ request('deleted_period') == 'today' ? 'selected' : '' }}>Today</option>
                        <option value="week" {{ request('deleted_period') == 'week' ? 'selected' : '' }}>This Week</option>
                        <option value="month" {{ request('deleted_period') == 'month' ? 'selected' : '' }}>This Month</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <div class="flex space-x-2 w-full">
                        <button type="submit" class="btn-modern flex-1 flex items-center justify-center">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                        <a href="{{ route('registration-plans.trash') }}" class="btn-secondary flex items-center px-3 py-3" title="Reset Filters">
                            <i class="fas fa-sync-alt"></i>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Trash List Card -->
    <div class="card p-6">
        @if($plans->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full min-w-full">
                    <thead>
                        <tr class="border-b" style="border-bottom-color: var(--border-color);">
                            <th class="text-left p-3 font-medium text-sm" style="color: var(--text-secondary);">ID</th>
                            <th class="text-left p-3 font-medium text-sm" style="color: var(--text-secondary);">Zone/Section</th>
                            <th class="text-left p-3 font-medium text-sm" style="color: var(--text-secondary);">Original Status</th>
                            <th class="text-left p-3 font-medium text-sm" style="color: var(--text-secondary);">Properties</th>
                            <th class="text-left p-3 font-medium text-sm" style="color: var(--text-secondary);">Deleted On</th>
                            <th class="text-left p-3 font-medium text-sm" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($plans as $plan)
                            @php
                                // Status color mapping
                                $statusColors = [
                                    'completed' => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)', 'border' => 'var(--success)'],
                                    'in_progress' => ['bg' => 'rgba(var(--primary-rgb), 0.1)', 'text' => 'var(--primary)', 'border' => 'var(--primary)'],
                                    'assigned' => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)', 'border' => 'var(--warning)'],
                                    'cancelled' => ['bg' => 'rgba(var(--danger-rgb), 0.1)', 'text' => 'var(--danger)', 'border' => 'var(--danger)'],
                                    'draft' => ['bg' => 'rgba(var(--secondary-rgb), 0.1)', 'text' => 'var(--secondary)', 'border' => 'var(--secondary)'],
                                ];
                                $statusConfig = $statusColors[$plan->status] ?? $statusColors['draft'];
                                
                                // Days until auto-delete
                                $daysUntilAutoDelete = 30 - $plan->deleted_at->diffInDays(now());
                                $isNearAutoDelete = $daysUntilAutoDelete <= 7;
                                $isCritical = $daysUntilAutoDelete <= 3;
                            @endphp
                            
                            <tr class="border-b hover:bg-opacity-50 transition-colors duration-150" 
                                style="border-bottom-color: var(--border-color);">
                                <td class="p-3 align-top">
                                    <span class="text-sm font-mono font-medium" style="color: var(--text-primary);">#{{ $plan->id }}</span>
                                </td>
                                <td class="p-3 align-top">
                                    <div class="font-medium text-sm" style="color: var(--text-primary);">
                                        {{ $plan->zone }}
                                        @if($plan->section)
                                            <span class="text-sm font-normal ml-1" style="color: var(--text-secondary);">- {{ $plan->section }}</span>
                                        @endif
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <span class="inline-flex items-center">
                                            <i class="fas fa-hashtag mr-1 text-xs"></i>
                                            {{ $plan->naming_pattern }}
                                        </span>
                                        <span class="inline-flex items-center ml-3">
                                            <i class="fas fa-home mr-1 text-xs"></i>
                                            {{ $plan->estimated_houses }} houses
                                        </span>
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-user mr-1 text-xs"></i>
                                        Agent: {{ $plan->assignedAgent->name ?? 'Unassigned' }}
                                    </div>
                                </td>
                                <td class="p-3 align-top">
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium mb-2"
                                          style="background-color: {{ $statusConfig['bg'] }}; 
                                                 color: {{ $statusConfig['text'] }};
                                                 border: 1px solid {{ $statusConfig['border'] }};">
                                        <i class="fas 
                                            {{ $plan->status == 'completed' ? 'fa-check-circle' : 
                                               ($plan->status == 'in_progress' ? 'fa-spinner fa-spin' : 
                                               ($plan->status == 'assigned' ? 'fa-user-clock' : 
                                               ($plan->status == 'cancelled' ? 'fa-times-circle' : 'fa-edit'))) }} 
                                            mr-1.5"></i>
                                        {{ ucfirst(str_replace('_', ' ', $plan->status)) }}
                                    </span>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-calendar-plus mr-1"></i>
                                        Created: {{ $plan->created_at->format('M d, Y') }}
                                    </div>
                                </td>
                                <td class="p-3 align-top">
                                    <div class="flex flex-col items-center">
                                        <span class="text-xl font-bold mb-1" style="color: {{ $plan->properties_count > 0 ? 'var(--danger)' : 'var(--success)' }};">
                                            {{ $plan->properties_count }}
                                        </span>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            registered
                                        </div>
                                        @if($plan->properties_count > 0)
                                            <div class="text-xs mt-1 px-2 py-1 rounded" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                <i class="fas fa-exclamation-triangle mr-1"></i> Contains data
                                            </div>
                                        @else
                                            <div class="text-xs mt-1 px-2 py-1 rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                <i class="fas fa-check-circle mr-1"></i> Safe to delete
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="p-3 align-top">
                                    <div class="text-sm font-medium mb-1" style="color: var(--text-primary);">
                                        {{ $plan->deleted_at->format('M d, Y') }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-clock mr-1"></i>
                                        {{ $plan->deleted_at->diffForHumans() }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-user mr-1"></i>
                                        By: {{ $plan->deleter->name ?? 'System' }}
                                    </div>
                                    @if($daysUntilAutoDelete <= 30)
                                        <div class="text-xs mt-1 px-2 py-1 rounded {{ $isCritical ? 'bg-red-100 text-red-800 border border-red-200' : ($isNearAutoDelete ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' : 'bg-blue-100 text-blue-800 border border-blue-200') }}">
                                            <i class="fas fa-clock mr-1"></i> 
                                            Auto-delete in {{ $daysUntilAutoDelete }} days
                                        </div>
                                    @endif
                                </td>
                                <td class="p-3 align-top">
                                    <div class="flex flex-wrap gap-1 mb-2">
                                        <!-- Restore Button -->
                                        <form action="{{ route('registration-plans.restore', $plan->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="btn-sm btn-success" title="Restore Plan"
                                                    onclick="return confirm('Restore this plan?')">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </form>

                                        <!-- View Details Button -->
                                        <a href="{{ route('registration-plans.show', $plan->id) }}" 
                                           class="btn-sm btn-secondary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <!-- Permanent Delete Button -->
                                        @if($plan->properties_count == 0)
                                            <form action="{{ route('registration-plans.force-destroy', $plan->id) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-sm btn-danger" title="Delete Permanently"
                                                        onclick="return confirmForceDelete('{{ $plan->zone }}')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        @else
                                            <span class="btn-sm btn-disabled opacity-50 cursor-not-allowed" 
                                                  title="Cannot delete plan with registered properties">
                                                <i class="fas fa-trash"></i>
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Additional info -->
                                    <div class="text-xs space-y-1">
                                        <div style="color: var(--text-secondary);">
                                            <i class="fas fa-user-plus mr-1"></i>
                                            Created by: {{ $plan->creator->name }}
                                        </div>
                                        
                                        @if($plan->properties_count > 0)
                                            <div class="text-xs px-2 py-1 rounded" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                <i class="fas fa-info-circle mr-1"></i> 
                                                {{ $plan->properties_count }} properties will be restored
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="mt-6 pt-4 border-t" style="border-top-color: var(--border-color);">
                {{ $plans->withQueryString()->links() }}
            </div>

            <!-- Bulk Actions -->
            <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div class="text-sm" style="color: var(--text-secondary);">
                        Showing {{ $plans->firstItem() }} to {{ $plans->lastItem() }} of {{ $plans->total() }} trashed plans
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if($plans->where('properties_count', 0)->count() > 0)
                            <form action="{{ route('registration-plans.empty-trash') }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-modern flex items-center px-4 py-2"
                                        style="background: linear-gradient(to right, var(--danger), #e53935);"
                                        onclick="return confirmDeleteEmptyPlans({{ $plans->where('properties_count', 0)->count() }})">
                                    <i class="fas fa-trash mr-2"></i> Delete Empty Plans
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4" 
                     style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    <i class="fas fa-trash-alt text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium mb-2" style="color: var(--text-primary);">Trash is Empty</h3>
                <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                    No registration plans have been deleted yet. Deleted plans will appear here and are kept for 30 days before being permanently removed.
                </p>
                <a href="{{ route('registration-plans.index') }}" class="btn-modern inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Registration Plans
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    function confirmEmptyTrash(totalCount) {
        return confirm(`⚠️ WARNING: This will permanently delete ALL ${totalCount} plans in the trash!\n\nThis action cannot be undone and will remove all trashed registration plans permanently.\n\nAre you absolutely sure you want to empty the trash?`);
    }

    function confirmDeleteEmptyPlans(count) {
        return confirm(`⚠️ WARNING: This will permanently delete ${count} empty plan${count !== 1 ? 's' : ''}!\n\nThis action cannot be undone and will remove all plans with no registered properties.\n\nAre you sure you want to delete ${count} empty plan${count !== 1 ? 's' : ''}?`);
    }

    function confirmForceDelete(planName) {
        return confirm(`⚠️ WARNING: This will permanently delete "${planName}"!\n\nThis action cannot be undone. The plan will be removed from the system permanently.\n\nAre you sure you want to delete this plan?`);
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Auto-submit filter form when select changes
        const filterSelects = document.querySelectorAll('select[name="status"], select[name="deleted_period"]');
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                this.closest('form').submit();
            });
        });

        // Enhanced confirmation dialogs
        document.querySelectorAll('form[action*="/force-destroy"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const planName = this.closest('tr').querySelector('td:nth-child(2) .font-medium').textContent.trim();
                if (!confirm(`⚠️ WARNING: This will permanently delete "${planName}"!\n\nThis action cannot be undone. Are you sure?`)) {
                    e.preventDefault();
                }
            });
        });

        // Toast notifications for actions
        @if(session('success'))
            showToast('{{ session('success') }}', 'success');
        @endif
        
        @if(session('error'))
            showToast('{{ session('error') }}', 'error');
        @endif
        
        @if(session('warning'))
            showToast('{{ session('warning') }}', 'warning');
        @endif

        // Add loading state to buttons when clicked
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitButton = this.querySelector('button[type="submit"], input[type="submit"]');
                if (submitButton && !submitButton.classList.contains('no-loading')) {
                    const originalText = submitButton.innerHTML;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
                    submitButton.disabled = true;
                    
                    // Revert after 10 seconds (in case of error)
                    setTimeout(() => {
                        submitButton.innerHTML = originalText;
                        submitButton.disabled = false;
                    }, 10000);
                }
            });
        });
    });

    function showToast(message, type = 'info') {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-all duration-300 transform translate-x-full`;
        
        switch (type) {
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
        
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
            toast.classList.add('translate-x-0');
        }, 100);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.classList.remove('translate-x-0');
            toast.classList.add('translate-x-full');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 5000);
    }
</script>

<style>
/* Enhanced Table Styling */
table {
    border-collapse: separate;
    border-spacing: 0;
}

table thead tr th {
    border-bottom: 2px solid;
    border-bottom-color: var(--border-color);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.75rem;
}

table tbody tr {
    transition: all 0.2s ease;
}

table tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.03);
}

table tbody tr:last-child {
    border-bottom: none;
}

/* Button styles matching other pages */
.btn-sm {
    padding: 0.375rem 0.75rem !important;
    font-size: 0.875rem !important;
    border-radius: 8px !important;
    text-decoration: none !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: none !important;
    cursor: pointer !important;
    transition: all 0.2s !important;
    min-width: 32px !important;
    min-height: 32px !important;
}

.btn-sm.btn-success {
    background: linear-gradient(to right, var(--success), #20b86d) !important;
    color: white !important;
    box-shadow: 0 2px 4px rgba(40, 199, 111, 0.2) !important;
}

.btn-sm.btn-secondary {
    background-color: var(--bg-secondary) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
}

.btn-sm.btn-danger {
    background: linear-gradient(to right, var(--danger), #e53935) !important;
    color: white !important;
    box-shadow: 0 2px 4px rgba(234, 84, 85, 0.2) !important;
}

.btn-sm:hover {
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
    opacity: 0.9 !important;
}

.btn-sm:active {
    transform: translateY(0) !important;
}

.btn-disabled {
    opacity: 0.5 !important;
    cursor: not-allowed !important;
}

.btn-disabled:hover {
    transform: none !important;
    box-shadow: none !important;
    opacity: 0.5 !important;
}

/* Status badge styling */
.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.status-badge.success {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border: 1px solid rgba(var(--success-rgb), 0.2);
}

.status-badge.warning {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border: 1px solid rgba(var(--warning-rgb), 0.2);
}

.status-badge.danger {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border: 1px solid rgba(var(--danger-rgb), 0.2);
}

.status-badge.info {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border: 1px solid rgba(var(--info-rgb), 0.2);
}

/* Auto-delete warning levels */
.auto-delete-critical {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border: 1px solid rgba(var(--danger-rgb), 0.2);
    animation: pulseCritical 2s infinite;
}

.auto-delete-warning {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border: 1px solid rgba(var(--warning-rgb), 0.2);
}

.auto-delete-info {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border: 1px solid rgba(var(--info-rgb), 0.2);
}

@keyframes pulseCritical {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

/* Trash-specific styling */
.trash-icon {
    color: var(--danger);
}

.restore-icon {
    color: var(--success);
}

.view-icon {
    color: var(--primary);
}

/* Progress bar for auto-delete countdown */
.auto-delete-progress {
    height: 4px;
    border-radius: 2px;
    background-color: rgba(var(--primary-rgb), 0.1);
    overflow: hidden;
    margin-top: 0.25rem;
}

.auto-delete-progress-bar {
    height: 100%;
    border-radius: 2px;
    background: linear-gradient(to right, var(--success), var(--warning), var(--danger));
    transition: width 0.3s ease;
}

/* Empty trash state */
.empty-trash-state {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-trash-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-trash-message {
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

/* Form input focus styles */
.form-select:focus,
.form-input:focus,
.form-textarea:focus {
    outline: none !important;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card {
        padding: 1rem !important;
    }
    
    table {
        font-size: 0.875rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem !important;
        font-size: 0.75rem !important;
        min-width: 28px !important;
        min-height: 28px !important;
    }
    
    .grid-cols-1 {
        grid-template-columns: 1fr !important;
    }
    
    .grid-cols-2,
    .grid-cols-3,
    .grid-cols-4 {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    
    .flex-col {
        flex-direction: column !important;
    }
    
    .flex-col > * {
        width: 100% !important;
        margin-bottom: 0.5rem !important;
    }
}

/* Custom scrollbar for table */
.overflow-x-auto::-webkit-scrollbar {
    height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: var(--bg-primary);
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: var(--secondary);
}

/* Loading spinner animation */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Warning and danger highlight */
.warning-highlight {
    background-color: rgba(var(--warning-rgb), 0.05);
    border-left: 3px solid var(--warning);
}

.danger-highlight {
    background-color: rgba(var(--danger-rgb), 0.05);
    border-left: 3px solid var(--danger);
}

/* Hover effects for table rows */
.table-row-hover {
    position: relative;
}

.table-row-hover::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(var(--primary-rgb), 0.03), transparent);
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.table-row-hover:hover::after {
    opacity: 1;
}

/* Dark mode specific adjustments */
[data-theme="dark"] .form-select,
[data-theme="dark"] .form-input,
[data-theme="dark"] .form-textarea {
    background-color: var(--bg-secondary) !important;
    color: var(--text-primary) !important;
    border-color: var(--border-color) !important;
}

[data-theme="dark"] .form-select:focus,
[data-theme="dark"] .form-input:focus,
[data-theme="dark"] .form-textarea:focus {
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.2) !important;
}

[data-theme="dark"] .btn-sm.btn-secondary {
    background-color: var(--bg-secondary) !important;
    color: var(--text-primary) !important;
    border-color: var(--border-color) !important;
}

[data-theme="dark"] .auto-delete-critical {
    background-color: rgba(var(--danger-rgb), 0.2);
    border-color: rgba(var(--danger-rgb), 0.3);
}

[data-theme="dark"] .auto-delete-warning {
    background-color: rgba(var(--warning-rgb), 0.2);
    border-color: rgba(var(--warning-rgb), 0.3);
}

[data-theme="dark"] .auto-delete-info {
    background-color: rgba(var(--info-rgb), 0.2);
    border-color: rgba(var(--info-rgb), 0.3);
}

/* Confirmation dialog styling */
.confirm-dialog {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    max-width: 400px;
    margin: 2rem auto;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.confirm-dialog-title {
    color: var(--danger);
    font-weight: 600;
    font-size: 1.25rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.confirm-dialog-title i {
    margin-right: 0.75rem;
}

.confirm-dialog-message {
    color: var(--text-primary);
    margin-bottom: 1.5rem;
    line-height: 1.5;
}

.confirm-dialog-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.confirm-dialog-cancel {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.5rem 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.confirm-dialog-cancel:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
}

.confirm-dialog-confirm {
    background: linear-gradient(to right, var(--danger), #e53935);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.5rem 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.confirm-dialog-confirm:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

/* Bulk actions styling */
.bulk-actions {
    background-color: rgba(var(--secondary-rgb), 0.05);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1.5rem;
}

.bulk-actions-title {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.75rem;
    font-size: 0.875rem;
}

.bulk-actions-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.bulk-action-btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
}

.bulk-action-btn.danger {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border: 1px solid rgba(var(--danger-rgb), 0.2);
}

.bulk-action-btn.danger:hover {
    background-color: rgba(var(--danger-rgb), 0.2);
}

.bulk-action-btn i {
    margin-right: 0.5rem;
}

/* Statistics card hover effects */
.stat-card {
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}
</style>