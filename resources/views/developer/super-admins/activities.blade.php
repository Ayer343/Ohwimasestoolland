{{-- developer/super-admins/activities.blade.php --}}
@extends('layouts.dev')

@php
    $routePrefix = 'developer.super-admins';
    $pageTitle = 'Super Admin Activities - Developer Portal';
    
    $user = $user ?? null;
    $activities = $activities ?? collect();
    
    if (!$user) {
        abort(404, 'Super Admin not found');
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
                    @if($user->photo)
                        <img src="{{ Storage::url($user->photo) }}" 
                             alt="{{ $user->name }}" 
                             class="w-16 h-16 rounded-full border-2"
                             style="border-color: var(--border-color);">
                    @else
                        <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                            <span class="text-xl">{{ $user->initials ?? 'SA' }}</span>
                        </div>
                    @endif
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2" style="color: var(--primary);"></i> 
                        Activity Log
                        <span class="ml-2 status-indicator {{ 'status-' . $user->status }}">
                            {{ $user->display_status['label'] ?? $user->status }}
                        </span>
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>Super Admin: {{ $user->name }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-envelope mr-1"></i>
                        <span>{{ $user->email }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <span>{{ $activities->total() }} activity log(s)</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('developer.super-admins.show', $user->id) }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Details
                </a>
                @if($activities->total() > 0)
                <button onclick="exportActivities()" 
                        class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium"
                        style="background: linear-gradient(to right, var(--primary), var(--secondary)); color: white;">
                    <i class="fas fa-file-export mr-2"></i> Export
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Activities Card -->
    <div class="card">
        <div class="p-6">
            <!-- Filters -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        Activity History
                    </h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Showing {{ $activities->firstItem() }} to {{ $activities->lastItem() }} of {{ $activities->total() }} entries
                    </p>
                </div>
                
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <!-- Date Filter -->
                    <div class="relative">
                        <select class="index-custom-dropdown text-sm pl-3 pr-8 py-2 rounded-lg appearance-none">
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                        </select>
                    </div>
                    
                    <!-- Search - Fixed to prevent icon overlap -->
                    <div class="relative">
                        <input type="text" 
                               placeholder="Search activities..." 
                               class="index-custom-input text-sm pl-10 pr-3 py-2 rounded-lg"
                               style="width: 200px; padding-left: 2.5rem;">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-search" style="color: var(--text-secondary);"></i>
                        </div>
                    </div>
                </div>
            </div>

            @if($activities->isEmpty())
                <!-- Empty State -->
                <div class="text-center py-12">
                    <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-history text-2xl" style="color: var(--primary);"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                        No activities found
                    </h4>
                    <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                        This Super Admin hasn't performed any activities yet.
                    </p>
                </div>
            @else
                <!-- Activities Timeline -->
                <div class="space-y-4">
                    @foreach($activities as $activity)
                    <div class="flex items-start p-4 rounded-lg border" 
                         style="border-color: var(--border-color); background-color: var(--card-bg);">
                        <div class="flex-shrink-0 mr-4">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                 style="background-color: rgba(var(--primary-rgb), 0.1);">
                                @php
                                    $icon = 'fa-history';
                                    if (str_contains(strtolower($activity->description), 'login')) {
                                        $icon = 'fa-sign-in-alt';
                                    } elseif (str_contains(strtolower($activity->description), 'password')) {
                                        $icon = 'fa-key';
                                    } elseif (str_contains(strtolower($activity->description), 'update')) {
                                        $icon = 'fa-edit';
                                    } elseif (str_contains(strtolower($activity->description), 'create')) {
                                        $icon = 'fa-plus-circle';
                                    } elseif (str_contains(strtolower($activity->description), 'delete')) {
                                        $icon = 'fa-trash-alt';
                                    }
                                @endphp
                                <i class="fas {{ $icon }} text-sm" style="color: var(--primary);"></i>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm mb-1" style="color: var(--text-primary);">
                                {{ $activity->description }}
                            </p>
                            <div class="flex items-center text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-clock mr-1"></i>
                                <span>{{ $activity->created_at->format('M d, Y \a\t g:i A') }}</span>
                                <span class="mx-2">•</span>
                                <i class="fas fa-hourglass-half mr-1"></i>
                                <span>{{ $activity->created_at->diffForHumans() }}</span>
                            </div>
                            @if($activity->metadata)
                            <div class="mt-2">
                                <button type="button" 
                                        onclick="toggleMetadata({{ $activity->id }})"
                                        class="text-xs px-2 py-1 rounded"
                                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                    <i class="fas fa-info-circle mr-1"></i> View Details
                                </button>
                                <div id="metadata-{{ $activity->id }}" 
                                     class="mt-2 p-2 rounded text-xs hidden"
                                     style="background-color: rgba(var(--secondary-rgb), 0.05); border: 1px solid rgba(var(--secondary-rgb), 0.1);">
                                    <pre class="whitespace-pre-wrap">{{ json_encode($activity->metadata, JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="flex flex-col md:flex-row justify-between items-center pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                    <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                        Showing {{ $activities->firstItem() }} to {{ $activities->lastItem() }} of {{ $activities->total() }} entries
                    </div>
                    <div class="pagination">
                        {{ $activities->appends(request()->except('page'))->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Export Modal -->
<div id="exportModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideExportModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-file-export mr-2" style="color: var(--primary);"></i> Export Activities
                </h3>
                <button type="button" onclick="hideExportModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-file-format mr-1"></i> Export Format
                    </label>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <input type="radio" 
                                   id="format_csv" 
                                   name="export_format" 
                                   value="csv" 
                                   class="index-custom-checkbox"
                                   checked>
                            <label for="format_csv" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-file-csv mr-1"></i> CSV (Excel)
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" 
                                   id="format_json" 
                                   name="export_format" 
                                   value="json" 
                                   class="index-custom-checkbox">
                            <label for="format_json" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-file-code mr-1"></i> JSON
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-calendar mr-1"></i> Date Range
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs mb-1" style="color: var(--text-secondary);">From</label>
                            <input type="date" 
                                   id="export_date_from" 
                                   class="index-custom-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs mb-1" style="color: var(--text-secondary);">To</label>
                            <input type="date" 
                                   id="export_date_to" 
                                   class="index-custom-input w-full">
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-filter mr-1"></i> Activity Types
                    </label>
                    <div class="space-y-1">
                        <div class="flex items-center">
                            <input type="checkbox" 
                                   id="type_all" 
                                   class="index-custom-checkbox"
                                   checked
                                   onchange="toggleAllActivityTypes(this)">
                            <label for="type_all" class="ml-2 text-sm" style="color: var(--text-primary);">
                                All Activity Types
                            </label>
                        </div>
                        <div class="ml-6 space-y-1">
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="type_login" 
                                       name="activity_types[]" 
                                       value="login" 
                                       class="index-custom-checkbox activity-type-checkbox"
                                       checked>
                                <label for="type_login" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    Login Activities
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="type_password" 
                                       name="activity_types[]" 
                                       value="password" 
                                       class="index-custom-checkbox activity-type-checkbox"
                                       checked>
                                <label for="type_password" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    Password Changes
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="type_profile" 
                                       name="activity_types[]" 
                                       value="profile" 
                                       class="index-custom-checkbox activity-type-checkbox"
                                       checked>
                                <label for="type_profile" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    Profile Updates
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="type_system" 
                                       name="activity_types[]" 
                                       value="system" 
                                       class="index-custom-checkbox activity-type-checkbox"
                                       checked>
                                <label for="type_system" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    System Activities
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="hideExportModal()" 
                        class="btn-secondary px-4 py-2 rounded-lg font-medium">
                    Cancel
                </button>
                <button type="button" onclick="performExport()" 
                        class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                    <i class="fas fa-download mr-2"></i> Export Activities
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function toggleMetadata(activityId) {
    const metadataDiv = document.getElementById('metadata-' + activityId);
    if (metadataDiv) {
        metadataDiv.classList.toggle('hidden');
    }
}

function exportActivities() {
    const modal = document.getElementById('exportModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideExportModal() {
    const modal = document.getElementById('exportModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function toggleAllActivityTypes(checkbox) {
    const typeCheckboxes = document.querySelectorAll('.activity-type-checkbox');
    typeCheckboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
}

function performExport() {
    const format = document.querySelector('input[name="export_format"]:checked').value;
    const dateFrom = document.getElementById('export_date_from').value;
    const dateTo = document.getElementById('export_date_to').value;
    
    // Get selected activity types
    const selectedTypes = Array.from(document.querySelectorAll('.activity-type-checkbox:checked'))
        .map(cb => cb.value);
    
    // Build export URL
    let url = '{{ route("developer.super-admins.activities.export", $user->id) }}';
    url += '?format=' + format;
    
    if (dateFrom) url += '&date_from=' + dateFrom;
    if (dateTo) url += '&date_to=' + dateTo;
    if (selectedTypes.length > 0) url += '&types=' + selectedTypes.join(',');
    
    // Open in new tab
    window.open(url, '_blank');
    
    // Hide modal
    hideExportModal();
}

// Auto-hide messages
setTimeout(() => {
    const successMessages = document.querySelectorAll('.bg-green-100');
    successMessages.forEach(msg => {
        if (msg.style.display !== 'none') {
            msg.style.display = 'none';
        }
    });
    
    const errorMessages = document.querySelectorAll('.bg-red-100');
    errorMessages.forEach(msg => {
        if (msg.style.display !== 'none') {
            msg.style.display = 'none';
        }
    });
}, 5000);
</script>

<style>
/* Activities page specific styles */
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

.status-suspended {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.status-suspended::before {
    background-color: var(--danger);
}

.status-inactive {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
}

.status-inactive::before {
    background-color: var(--secondary);
}

/* Button styles from index blade */
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

/* Badge styles from index blade */
.btn-modern {
    background: linear-gradient(to right, var(--primary), var(--secondary)) !important;
    color: white !important;
    border: none !important;
    transition: all 0.2s ease;
}

.btn-modern:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

/* Modal styles from index blade */
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
}

.modal-close-btn:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

/* Form controls from index blade - UPDATED FOR SEARCH FIELD */
.index-custom-input {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    padding-left: 2.5rem; /* Increased left padding for search icon */
    width: 100%;
    transition: all 0.3s ease;
}

.index-custom-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Search input specific styling */
.search-input-container {
    position: relative;
}

.search-input-container input {
    padding-left: 2.5rem !important; /* Ensure enough space for icon */
}

.search-input-container .search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    pointer-events: none;
}

.index-custom-dropdown {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}

[data-theme="dark"] .index-custom-dropdown {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%23e4e4e4' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
}

[data-theme="light"] .index-custom-dropdown {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%234b4b4b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
}

.index-custom-checkbox {
    width: 1rem;
    height: 1rem;
    border-radius: 0.25rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    cursor: pointer;
    transition: all 0.2s;
}

.index-custom-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* Radio button styling */
input[type="radio"] {
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    border: 2px solid var(--border-color);
    background-color: var(--card-bg);
    cursor: pointer;
    transition: all 0.2s;
    appearance: none;
    position: relative;
}

input[type="radio"]:checked {
    border-color: var(--primary);
    background-color: var(--primary);
}

input[type="radio"]:checked::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    background-color: white;
}

/* Card styles */
.card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}

/* Pagination styles from index blade */
.pagination .pagination {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
    gap: 0.25rem;
}

.pagination .pagination li a,
.pagination .pagination li span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.5rem;
    height: 2.5rem;
    padding: 0 0.75rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.pagination .pagination li a:hover:not(.disabled) {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    border-color: var(--primary);
}

.pagination .pagination li.active span {
    background-color: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination .pagination li.disabled span {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Metadata display */
pre {
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    line-height: 1.4;
    max-height: 200px;
    overflow-y: auto;
    margin: 0;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .flex.flex-col.md\\:flex-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .flex.items-center.space-x-3 {
        flex-direction: column;
        gap: 0.5rem;
        align-items: stretch;
    }
    
    .flex.items-center.space-x-3 > * {
        width: 100%;
    }
    
    /* Make search input full width on mobile */
    .flex.items-center.space-x-3 .relative {
        width: 100%;
    }
    
    .flex.items-center.space-x-3 .relative input {
        width: 100%;
    }
    
    .modal-container {
        width: 95%;
        max-height: 80vh;
        margin: 0.5rem;
    }
}

/* Improved input padding for all search inputs */
input[type="text"][placeholder*="Search"],
input[type="search"] {
    padding-left: 2.5rem !important;
}
</style>
@endsection