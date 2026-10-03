@extends('layouts.app')

@section('title', 'Archived Users')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-archive mr-2"></i> Archived Users
                </h2>
                <p class="mt-1 text-sm" style="color: var(--text-secondary);">
                    View and manage archived user accounts. Archived accounts have restricted access and can be restored or permanently deleted.
                </p>
                <div class="mt-3">
                    <a href="{{ route('admin.users.index') }}" 
                       class="inline-flex items-center text-sm font-medium transition-colors hover:opacity-80"
                       style="color: var(--primary);">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to User Management
                    </a>
                </div>
            </div>
            <div class="flex items-center space-x-3 flex-wrap gap-2">
                <!-- Export Button -->
                <button onclick="showExportArchivedModal()" 
                   class="btn-success flex items-center px-3 py-2 rounded-lg text-sm" 
                   title="Export Archived Users">
                    <i class="fas fa-file-export mr-2"></i> Export
                </button>
                
                <!-- Bulk Actions Dropdown -->
                <div class="relative">
                    <button id="bulkActionsBtn" 
                            class="btn-primary flex items-center px-3 py-2 rounded-lg text-sm"
                            onclick="toggleBulkActions()">
                        <i class="fas fa-tasks mr-2"></i> Bulk Actions
                        <i class="fas fa-chevron-down ml-2 text-xs"></i>
                    </button>
                    <div id="bulkActionsDropdown" 
                         class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border z-10 hidden"
                         style="background-color: var(--bg-primary); border-color: var(--border-color);">
                        <div class="py-1">
                            <button onclick="bulkRestoreArchived()" 
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-opacity-10"
                                    style="color: var(--text-primary);">
                                <i class="fas fa-undo-alt mr-2 text-success"></i> Restore Selected
                            </button>
                            <button onclick="bulkPermanentDeleteArchived()" 
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-opacity-10"
                                    style="color: var(--danger);">
                                <i class="fas fa-trash-alt mr-2"></i> Permanently Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Archival Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="card p-6">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Total Archived</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $stats['total_archived'] ?? 0 }}</h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(108, 117, 125, 0.1);">
                    <i class="fas fa-archive text-lg" style="color: #6c757d;"></i>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Archived This Month</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $stats['archived_this_month'] ?? 0 }}</h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(23, 162, 184, 0.1);">
                    <i class="fas fa-calendar-alt text-lg" style="color: #17a2b8;"></i>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Scheduled for Deletion</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $stats['scheduled_for_deletion'] ?? 0 }}</h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(220, 53, 69, 0.1);">
                    <i class="fas fa-clock text-lg" style="color: #dc3545;"></i>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Restored This Month</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $stats['restored_this_month'] ?? 0 }}</h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(40, 167, 69, 0.1);">
                    <i class="fas fa-undo-alt text-lg" style="color: #28a745;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <form action="{{ route('admin.users.archived') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" 
                       class="w-full p-2 border rounded" 
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                       placeholder="Name, email, phone...">
            </div>

            <div>
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">User Type</label>
                <select name="type" class="w-full p-2 border rounded" 
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="all">All Types</option>
                    @foreach($userTypes ?? [] as $value => $label)
                        <option value="{{ $value }}" {{ request('type', '') == $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Archival Date</label>
                <select name="archival_period" class="w-full p-2 border rounded" 
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="all">All Time</option>
                    <option value="today" {{ request('archival_period') == 'today' ? 'selected' : '' }}>Today</option>
                    <option value="week" {{ request('archival_period') == 'week' ? 'selected' : '' }}>This Week</option>
                    <option value="month" {{ request('archival_period') == 'month' ? 'selected' : '' }}>This Month</option>
                    <option value="year" {{ request('archival_period') == 'year' ? 'selected' : '' }}>This Year</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Deletion Status</label>
                <select name="deletion_status" class="w-full p-2 border rounded" 
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="all">All</option>
                    <option value="scheduled" {{ request('deletion_status') == 'scheduled' ? 'selected' : '' }}>Scheduled for Deletion</option>
                    <option value="not_scheduled" {{ request('deletion_status') == 'not_scheduled' ? 'selected' : '' }}>Not Scheduled</option>
                </select>
            </div>

            <div class="md:col-span-4 flex justify-between items-center pt-4 border-t" style="border-color: var(--border-color);">
                <div class="text-sm" style="color: var(--text-secondary);">
                    {{ $users->total() }} archived users found
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('admin.users.archived') }}" class="btn-secondary px-4 py-2 rounded-lg text-sm">
                        Clear Filters
                    </a>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Archived Users Table -->
    <div class="card p-6">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr style="border-bottom-color: var(--border-color);">
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">
                            <input type="checkbox" id="select-all-archived" class="rounded border-gray-300">
                        </th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">User</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Type</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Archived At</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Deletion Schedule</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Reason</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr class="border-b archived-row" style="border-bottom-color: var(--border-color); background-color: var(--bg-secondary);">
                        <td class="py-4 px-4">
                            <input type="checkbox" name="archived_user_ids[]" value="{{ $user->id }}" 
                                   class="archived-user-checkbox rounded border-gray-300"
                                   data-user-name="{{ $user->name }}"
                                   data-user-type="{{ $user->type }}">
                        </td>
                        
                        <td class="py-4 px-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-10 h-10">
                                    @if($user->has_photo)
                                        <img src="{{ $user->avatar_url }}" 
                                             alt="{{ $user->name }}"
                                             class="w-10 h-10 rounded-full object-cover border-2 cursor-pointer"
                                             style="border-color: #6c757d;"
                                             onclick="showArchivedUserDetails({{ $user->id }})">
                                    @else
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold text-white text-sm"
                                             style="background-color: #6c757d;">
                                            {{ $user->initials }}
                                        </div>
                                    @endif
                                </div>
                                <div class="ml-4">
                                    <div class="font-medium flex items-center" style="color: var(--text-primary);">
                                        {{ $user->name }}
                                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-500 text-white">
                                            Archived
                                        </span>
                                    </div>
                                    <div class="text-sm" style="color: var(--text-secondary);">{{ $user->email }}</div>
                                    @if($user->username)
                                    <div class="text-xs font-mono" style="color: var(--text-secondary);">@{{ $user->username }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <td class="py-4 px-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-700">
                                <i class="fas fa-archive mr-1"></i>
                                {{ $user->type_name }}
                            </span>
                        </td>

                        <td class="py-4 px-4">
                            <div style="color: var(--text-primary);">{{ $user->archived_at ? $user->archived_at->format('M j, Y H:i') : 'N/A' }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                {{ $user->archived_at ? $user->archived_at->diffForHumans() : '' }}
                            </div>
                        </td>

                        <td class="py-4 px-4">
                            @if($user->deletion_scheduled_at)
                                <div class="text-danger">
                                    <i class="fas fa-calendar-times mr-1"></i>
                                    {{ $user->deletion_scheduled_at->format('M j, Y') }}
                                </div>
                                <div class="text-xs text-warning mt-1">
                                    {{ now()->diffInDays($user->deletion_scheduled_at) }} days remaining
                                </div>
                            @else
                                <span class="text-muted">Not scheduled</span>
                            @endif
                        </td>

                        <td class="py-4 px-4">
                            <div class="text-sm" style="color: var(--text-secondary);">
                                {{ $user->archival_info['archived_reason'] ?? 'No reason provided' }}
                            </div>
                            @if(($user->archival_info['archived_by'] ?? false))
                                <div class="text-xs text-muted mt-1">
                                    By: {{ $user->archival_info['archived_by_name'] ?? 'System' }}
                                </div>
                            @endif
                        </td>

                        <td class="py-4 px-4">
                            <div class="flex items-center space-x-2 flex-wrap gap-1">
                                <!-- View Details -->
                                <button onclick="showArchivedUserDetails({{ $user->id }})" 
                                        class="btn-info btn-sm inline-flex items-center px-2 py-1 rounded text-xs" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <!-- Restore User -->
                                <button onclick="restoreArchivedUser({{ $user->id }}, '{{ addslashes($user->name) }}')" 
                                        class="btn-success btn-sm inline-flex items-center px-2 py-1 rounded text-xs" title="Restore Account">
                                    <i class="fas fa-undo-alt"></i>
                                </button>
                                
                                <!-- Cancel Deletion Schedule -->
                                @if($user->deletion_scheduled_at && $user->deletion_scheduled_at->isFuture())
                                <button onclick="cancelArchivalSchedule({{ $user->id }}, '{{ addslashes($user->name) }}')" 
                                        class="btn-warning btn-sm inline-flex items-center px-2 py-1 rounded text-xs" title="Cancel Deletion Schedule">
                                    <i class="fas fa-ban"></i>
                                </button>
                                @endif
                                
                                <!-- Permanent Delete -->
                                <button onclick="permanentDeleteUser({{ $user->id }}, '{{ addslashes($user->name) }}')" 
                                        class="btn-danger btn-sm inline-flex items-center px-2 py-1 rounded text-xs" title="Permanently Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-8 px-4 text-center" style="color: var(--text-secondary);">
                            <i class="fas fa-archive text-4xl mb-4 opacity-50"></i>
                            <p class="text-lg">No archived users found</p>
                            <p class="text-sm mt-2">Archived users will appear here when landlords transfer all properties.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($users->hasPages())
        <div class="border-t pt-4 mt-4" style="border-color: var(--border-color);">
            {{ $users->links() }}
        </div>
        @endif
    </div>

    <!-- Info Card -->
    <div class="card p-6" style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.05) 0%, rgba(108, 117, 125, 0.02) 100%);">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-2xl mr-4" style="color: var(--info);"></i>
            <div>
                <h4 class="font-semibold mb-1" style="color: var(--text-primary);">About Account Archival</h4>
                <p class="text-sm" style="color: var(--text-secondary);">
                    Landlord accounts are automatically archived 30 days after transferring all properties. 
                    Archived accounts cannot log in but all historical data is preserved. 
                    Accounts can be restored within 30 days of archival. After 365 days, archived accounts may be permanently deleted.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Restore User Modal -->
<div id="restoreArchivedModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-6 w-full max-w-md" style="background-color: var(--bg-primary);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Restore Archived Account</h3>
        <div class="alert alert-info mb-4 p-3 rounded" style="background-color: rgba(23, 162, 184, 0.1); border-left: 4px solid #17a2b8;">
            <i class="fas fa-info-circle mr-2"></i>
            <strong>Restore Account:</strong> This will reactivate the user account and restore full access.
        </div>
        
        <p class="mb-4" style="color: var(--text-primary);">
            Are you sure you want to restore <strong id="restoreArchivedUserName"></strong>?
        </p>

        <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeRestoreArchivedModal()" class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
            <button type="button" id="confirmRestoreArchivedBtn" onclick="confirmRestoreArchived()" class="btn-success px-4 py-2 rounded-lg">
                <i class="fas fa-undo-alt mr-2"></i> Restore User
            </button>
        </div>
    </div>
</div>

<!-- Permanent Delete Modal -->
<div id="permanentDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-6 w-full max-w-md" style="background-color: var(--bg-primary);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Permanently Delete User</h3>
        <div class="alert alert-danger mb-4 p-3 rounded" style="background-color: rgba(220, 53, 69, 0.1); border-left: 4px solid #dc3545;">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <strong>Warning:</strong> This action cannot be undone. All user data will be permanently deleted.
        </div>
        
        <p class="mb-4" style="color: var(--text-primary);">
            Are you sure you want to permanently delete <strong id="permanentDeleteUserName"></strong>?
        </p>

        <div class="mb-4">
            <label for="permanent_delete_reason" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                Reason for Permanent Deletion (Optional)
            </label>
            <textarea id="permanent_delete_reason" rows="3" 
                      class="w-full p-2 border rounded" 
                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                      placeholder="Enter reason for permanent deletion..."></textarea>
        </div>

        <div class="flex justify-end space-x-3">
            <button type="button" onclick="closePermanentDeleteModal()" class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
            <button type="button" id="confirmPermanentDeleteBtn" onclick="confirmPermanentDelete()" class="btn-danger px-4 py-2 rounded-lg">
                <i class="fas fa-trash-alt mr-2"></i> Permanently Delete
            </button>
        </div>
    </div>
</div>

<!-- User Details Modal -->
<div id="archivedUserDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg w-full max-w-2xl max-h-[80vh] overflow-y-auto" style="background-color: var(--bg-primary);">
        <div class="sticky top-0 p-6 border-b flex justify-between items-center" style="background-color: var(--bg-primary); border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Archived User Details</h3>
            <button onclick="closeArchivedUserDetailsModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="archivedUserDetailsContent" class="p-6">
            <!-- Content loaded via AJAX -->
            <div class="text-center py-8">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
                <p class="mt-4">Loading user details...</p>
            </div>
        </div>
    </div>
</div>

<!-- Export Archived Modal -->
<div id="exportArchivedModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white rounded-lg w-full max-w-md" style="background-color: var(--bg-primary);">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Export Archived Users</h3>
                <button onclick="closeExportArchivedModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <form id="exportArchivedForm" method="GET" action="{{ route('admin.users.archived.export') }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Export Format</label>
                    <select name="format" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <option value="csv">CSV Format</option>
                        <option value="pdf">PDF Format</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Include Data</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="include_details" value="1" checked class="rounded">
                            <span class="ml-2 text-sm">Include archival details</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="include_deletion_schedule" value="1" checked class="rounded">
                            <span class="ml-2 text-sm">Include deletion schedule</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeExportArchivedModal()" class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" class="btn-success px-4 py-2 rounded-lg">
                        <i class="fas fa-download mr-2"></i> Export
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let currentRestoreUserId = null;
let currentRestoreUserName = null;
let currentPermanentDeleteUserId = null;
let currentPermanentDeleteUserName = null;

// ==================== DEBUGGING HELPER ====================

function logDebug(message, data = null) {
    console.log(`[DEBUG] ${message}`, data || '');
}

// ==================== BULK ACTIONS DROPDOWN ====================

function toggleBulkActions() {
    const dropdown = document.getElementById('bulkActionsDropdown');
    dropdown.classList.toggle('hidden');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('bulkActionsDropdown');
    const btn = document.getElementById('bulkActionsBtn');
    if (dropdown && !dropdown.classList.contains('hidden') && 
        !dropdown.contains(event.target) && !btn.contains(event.target)) {
        dropdown.classList.add('hidden');
    }
});

// ==================== SELECT ALL FUNCTIONALITY ====================

document.getElementById('select-all-archived')?.addEventListener('change', function(e) {
    const checkboxes = document.querySelectorAll('.archived-user-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = e.target.checked;
    });
});

// ==================== RESTORE FUNCTIONS WITH DEBUGGING ====================

function restoreArchivedUser(userId, userName) {
    logDebug('restoreArchivedUser called', { userId, userName, userIdType: typeof userId });
    
    // Validate userId
    if (!userId || isNaN(parseInt(userId))) {
        logDebug('Invalid userId detected!', userId);
        showNotification('Error: Invalid user ID. Please refresh the page and try again.', 'error');
        return;
    }
    
    currentRestoreUserId = parseInt(userId);
    currentRestoreUserName = userName;
    
    logDebug('Stored restore data', { 
        currentRestoreUserId, 
        currentRestoreUserName,
        url: `/admin/users/${currentRestoreUserId}/restore-archived`
    });
    
    document.getElementById('restoreArchivedUserName').textContent = userName;
    document.getElementById('restoreArchivedModal').classList.remove('hidden');
}

function closeRestoreArchivedModal() {
    document.getElementById('restoreArchivedModal').classList.add('hidden');
    currentRestoreUserId = null;
    currentRestoreUserName = null;
}

function confirmRestoreArchived() {
    if (!currentRestoreUserId) {
        logDebug('No user ID in currentRestoreUserId');
        showNotification('Error: No user selected for restoration.', 'error');
        closeRestoreArchivedModal();
        return;
    }
    
    logDebug('confirmRestoreArchived called', { 
        userId: currentRestoreUserId, 
        userName: currentRestoreUserName,
        url: `/admin/users/${currentRestoreUserId}/restore-archived`
    });
    
    const restoreBtn = document.getElementById('confirmRestoreArchivedBtn');
    restoreBtn.disabled = true;
    restoreBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Restoring...';
    
    const url = `/admin/users/${currentRestoreUserId}/restore-archived`;
    logDebug('Sending restore request to:', url);
    
    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({})
    })
    .then(response => {
        logDebug('Restore response received', { 
            status: response.status, 
            statusText: response.statusText,
            ok: response.ok 
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        logDebug('Restore response data:', data);
        
        if (data.success) {
            showNotification('User account restored successfully!', 'success');
            closeRestoreArchivedModal();
            setTimeout(() => {
                logDebug('Reloading page after successful restore');
                window.location.reload();
            }, 1500);
        } else {
            throw new Error(data.message || 'Failed to restore user');
        }
    })
    .catch(error => {
        logDebug('Restore error:', error);
        console.error('Full error details:', error);
        showNotification('Error restoring user: ' + error.message, 'error');
        restoreBtn.disabled = false;
        restoreBtn.innerHTML = '<i class="fas fa-undo-alt mr-2"></i> Restore User';
    });
}

function bulkRestoreArchived() {
    const selectedUsers = Array.from(document.querySelectorAll('.archived-user-checkbox:checked'))
                               .map(checkbox => checkbox.value);
    
    logDebug('bulkRestoreArchived called', { selectedUsers, count: selectedUsers.length });
    
    if (selectedUsers.length === 0) {
        showNotification('Please select at least one user to restore.', 'error');
        return;
    }
    
    if (!confirm(`Are you sure you want to restore ${selectedUsers.length} archived user(s)?`)) {
        return;
    }
    
    showNotification('Restoring selected users...', 'info');
    
    fetch('{{ route("admin.users.bulk-restore-archived") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            user_ids: selectedUsers
        })
    })
    .then(response => response.json())
    .then(data => {
        logDebug('Bulk restore response:', data);
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while restoring users.', 'error');
    });
}

// ==================== PERMANENT DELETE FUNCTIONS ====================

function permanentDeleteUser(userId, userName) {
    logDebug('permanentDeleteUser called', { userId, userName });
    currentPermanentDeleteUserId = userId;
    currentPermanentDeleteUserName = userName;
    
    document.getElementById('permanentDeleteUserName').textContent = userName;
    document.getElementById('permanent_delete_reason').value = '';
    document.getElementById('permanentDeleteModal').classList.remove('hidden');
}

function closePermanentDeleteModal() {
    document.getElementById('permanentDeleteModal').classList.add('hidden');
    currentPermanentDeleteUserId = null;
    currentPermanentDeleteUserName = null;
}

function confirmPermanentDelete() {
    if (!currentPermanentDeleteUserId) return;
    
    const reason = document.getElementById('permanent_delete_reason').value;
    const deleteBtn = document.getElementById('confirmPermanentDeleteBtn');
    deleteBtn.disabled = true;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
    
    fetch(`/admin/users/${currentPermanentDeleteUserId}/permanent-delete`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            reason: reason || 'Manual permanent deletion by admin'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('User permanently deleted successfully!', 'success');
            closePermanentDeleteModal();
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            throw new Error(data.message || 'Failed to delete user');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error deleting user: ' + error.message, 'error');
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = '<i class="fas fa-trash-alt mr-2"></i> Permanently Delete';
    });
}

function bulkPermanentDeleteArchived() {
    const selectedUsers = Array.from(document.querySelectorAll('.archived-user-checkbox:checked'))
                               .map(checkbox => checkbox.value);
    
    if (selectedUsers.length === 0) {
        showNotification('Please select at least one user to delete.', 'error');
        return;
    }
    
    if (!confirm(`⚠️ WARNING: You are about to permanently delete ${selectedUsers.length} user(s). This action CANNOT be undone. Are you absolutely sure?`)) {
        return;
    }
    
    showNotification('Permanently deleting selected users...', 'info');
    
    fetch('{{ route("admin.users.bulk-permanent-delete-archived") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            user_ids: selectedUsers,
            reason: 'Bulk permanent deletion by admin'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while deleting users.', 'error');
    });
}

// ==================== CANCEL ARCHIVAL SCHEDULE ====================

function cancelArchivalSchedule(userId, userName) {
    if (!confirm(`Cancel deletion schedule for ${userName}? The account will remain archived but will not be automatically deleted.`)) {
        return;
    }
    
    fetch(`/admin/users/${userId}/cancel-archival-schedule`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while cancelling the schedule.', 'error');
    });
}

// ==================== USER DETAILS MODAL ====================

function showArchivedUserDetails(userId) {
    logDebug('showArchivedUserDetails called', { userId });
    const modal = document.getElementById('archivedUserDetailsModal');
    const content = document.getElementById('archivedUserDetailsContent');
    modal.classList.remove('hidden');
    
    content.innerHTML = '<div class="text-center py-8"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div><p class="mt-4">Loading user details...</p></div>';
    
    fetch(`/admin/users/archived/${userId}`, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderArchivedUserDetails(data.user, data.archival_info, data.transfer_history);
        } else {
            content.innerHTML = '<div class="text-center py-8 text-danger">Failed to load user details.</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        content.innerHTML = '<div class="text-center py-8 text-danger">Error loading user details.</div>';
    });
}

function renderArchivedUserDetails(user, archivalInfo, transferHistory) {
    const content = document.getElementById('archivedUserDetailsContent');
    
    let transferHistoryHtml = '';
    if (transferHistory && transferHistory.length > 0) {
        transferHistoryHtml = `
            <div class="mt-6">
                <h4 class="font-semibold mb-3" style="color: var(--text-primary);">Property Transfer History</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr style="border-bottom-color: var(--border-color);">
                                <th class="text-left py-2">Property</th>
                                <th class="text-left py-2">Transfer Date</th>
                                <th class="text-left py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${transferHistory.map(transfer => `
                                <tr style="border-bottom-color: var(--border-color);">
                                    <td class="py-2">${transfer.property?.property_name || 'N/A'}</td>
                                    <td class="py-2">${transfer.transfer_date ? new Date(transfer.transfer_date).toLocaleDateString() : 'N/A'}</td>
                                    <td class="py-2">${transfer.status || 'N/A'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }
    
    content.innerHTML = `
        <div class="space-y-4">
            <div class="flex items-center">
                <div class="w-20 h-20 rounded-full bg-gray-200 flex items-center justify-center">
                    ${user.has_photo ? 
                        `<img src="${user.avatar_url}" class="w-20 h-20 rounded-full object-cover">` : 
                        `<div class="w-20 h-20 rounded-full flex items-center justify-center text-white text-2xl" style="background-color: #6c757d;">${user.initials}</div>`
                    }
                </div>
                <div class="ml-4">
                    <h3 class="text-xl font-semibold" style="color: var(--text-primary);">${user.name}</h3>
                    <p style="color: var(--text-secondary);">${user.email}</p>
                    <p class="text-sm" style="color: var(--text-secondary);">${user.phone || 'No phone'}</p>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium" style="color: var(--text-secondary);">User Type</label>
                    <p style="color: var(--text-primary);">${user.type_name}</p>
                </div>
                <div>
                    <label class="text-sm font-medium" style="color: var(--text-secondary);">Archived At</label>
                    <p style="color: var(--text-primary);">${archivalInfo?.archived_at ? new Date(archivalInfo.archived_at).toLocaleString() : 'N/A'}</p>
                </div>
                <div>
                    <label class="text-sm font-medium" style="color: var(--text-secondary);">Archival Reason</label>
                    <p style="color: var(--text-primary);">${archivalInfo?.archived_reason || 'No reason provided'}</p>
                </div>
                <div>
                    <label class="text-sm font-medium" style="color: var(--text-secondary);">Original Email</label>
                    <p style="color: var(--text-primary);">${archivalInfo?.original_email || user.email}</p>
                </div>
                <div>
                    <label class="text-sm font-medium" style="color: var(--text-secondary);">Deletion Scheduled</label>
                    <p style="color: var(--text-primary);">${user.deletion_scheduled_at ? new Date(user.deletion_scheduled_at).toLocaleDateString() : 'Not scheduled'}</p>
                </div>
                <div>
                    <label class="text-sm font-medium" style="color: var(--text-secondary);">Days Archived</label>
                    <p style="color: var(--text-primary);">${archivalInfo?.days_since_archival || 'N/A'} days</p>
                </div>
            </div>
            
            ${transferHistoryHtml}
        </div>
    `;
}

function closeArchivedUserDetailsModal() {
    document.getElementById('archivedUserDetailsModal').classList.add('hidden');
}

// ==================== EXPORT MODAL ====================

function showExportArchivedModal() {
    document.getElementById('exportArchivedModal').classList.remove('hidden');
}

function closeExportArchivedModal() {
    document.getElementById('exportArchivedModal').classList.add('hidden');
}

document.getElementById('exportArchivedForm')?.addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Generating...';
    submitBtn.disabled = true;
});

// ==================== NOTIFICATION FUNCTION ====================

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transform transition-all duration-300 ${
        type === 'success' ? 'bg-green-500 text-white' :
        type === 'error' ? 'bg-red-500 text-white' :
        type === 'warning' ? 'bg-yellow-500 text-white' :
        'bg-blue-500 text-white'
    }`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

// ==================== PAGE LOAD DEBUGGING ====================

document.addEventListener('DOMContentLoaded', function() {
    logDebug('Page loaded - Archived Users page');
    
    // Log all restore buttons found
    const restoreButtons = document.querySelectorAll('[onclick*="restoreArchivedUser"]');
    logDebug(`Found ${restoreButtons.length} restore buttons on the page`);
    
    restoreButtons.forEach((btn, index) => {
        const onclickAttr = btn.getAttribute('onclick');
        logDebug(`Button ${index}: ${onclickAttr}`);
    });
    
    // Log all archived users
    const userRows = document.querySelectorAll('tbody tr');
    logDebug(`Found ${userRows.length} user rows`);
    
    userRows.forEach((row, index) => {
        const nameCell = row.querySelector('td:nth-child(2) .font-medium');
        const name = nameCell ? nameCell.textContent.trim() : 'Unknown';
        logDebug(`User ${index}: ${name}`);
    });
});

// Close modals when clicking outside
document.getElementById('restoreArchivedModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeRestoreArchivedModal();
});

document.getElementById('permanentDeleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) closePermanentDeleteModal();
});

document.getElementById('archivedUserDetailsModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeArchivedUserDetailsModal();
});

document.getElementById('exportArchivedModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeExportArchivedModal();
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRestoreArchivedModal();
        closePermanentDeleteModal();
        closeArchivedUserDetailsModal();
        closeExportArchivedModal();
    }
});

// Initial debug output
console.log('Script loaded - Archived Users Management');
console.log('CSRF Token present:', '{{ csrf_token() }}' ? 'Yes' : 'No');
</script>

<style>
.archived-row {
    opacity: 0.85;
}

.archived-row:hover {
    opacity: 1;
}

.alert-info {
    background-color: rgba(23, 162, 184, 0.1);
    border-left: 4px solid #17a2b8;
}

.alert-danger {
    background-color: rgba(220, 53, 69, 0.1);
    border-left: 4px solid #dc3545;
}

.text-muted {
    color: #6c757d;
}

.text-warning {
    color: #ffc107;
}

.text-danger {
    color: #dc3545;
}

.text-success {
    color: #28a745;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border-radius: 0.25rem;
}
</style>
@endsection