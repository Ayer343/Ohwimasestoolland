@extends('layouts.app')

@section('title', 'Deleted Users - Trash Management')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Deleted Users Management</h2>
                <p class="mt-1 text-sm" style="color: var(--text-secondary);">
                    Manage soft-deleted user accounts. Restore or permanently delete users from the trash.
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.users.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Users
                </a>

                <button onclick="emptyTrash()" class="btn-danger flex items-center">
                    <i class="fas fa-trash mr-2"></i> Empty Trash
                </button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="card p-6">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Total Deleted</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $users->total() }}</h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-trash-alt text-lg" style="color: var(--danger);"></i>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Deleted Today</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">
                        {{ \App\Models\User::onlyTrashed()->whereDate('deleted_at', today())->count() }}
                    </h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-clock text-lg" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Oldest Deletion</p>
                    <h3 class="text-lg font-bold mt-1" style="color: var(--text-primary);">
                        @php
                            $oldest = \App\Models\User::onlyTrashed()->orderBy('deleted_at')->first();
                            // ✅ Cast to int — Carbon 3 returns a float from diffInDays()
                            $oldestDays = $oldest ? (int) $oldest->deleted_at->diffInDays(now()) : null;
                        @endphp
                        @if($oldest)
                            {{ $oldestDays }} day{{ $oldestDays === 1 ? '' : 's' }}
                        @else
                            N/A
                        @endif
                    </h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-calendar text-lg" style="color: var(--info);"></i>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Storage Used</p>
                    <h3 class="text-lg font-bold mt-1" style="color: var(--text-primary);">
                        @php
                            $totalSize = 0;
                            $deletedUsers = \App\Models\User::onlyTrashed()->get();
                            foreach ($deletedUsers as $user) {
                                if ($user->photo) {
                                    $path = 'users/photos/' . $user->photo;
                                    if (Storage::disk('public')->exists($path)) {
                                        $totalSize += Storage::disk('public')->size($path);
                                    }
                                }
                            }
                            $totalSizeKB = round($totalSize / 1024, 2);
                        @endphp
                        {{ $totalSizeKB }} KB
                    </h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-database text-lg" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <form action="{{ route('admin.users.trash') }}" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                    @foreach($userTypes as $value => $label)
                        <option value="{{ $value }}" {{ request('type') == $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-3 flex justify-between items-center pt-4 border-t" style="border-color: var(--border-color);">
                <div class="text-sm" style="color: var(--text-secondary);">
                    {{ $users->total() }} deleted users found
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('admin.users.trash') }}" class="btn-secondary">
                        Clear Filters
                    </a>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Deleted Users Table -->
    <div class="card p-6">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr style="border-bottom-color: var(--border-color);">
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">
                            <input type="checkbox" id="select-all" class="rounded border-gray-300">
                        </th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">User</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Type</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Deleted By</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Deleted At</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Days Ago</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    @php
                        // =============================================================
                        // Per-row: parse metadata + detect scrubbed records.
                        // metadata is cast to 'array' on the User model, but we
                        // still decode defensively in case a raw string slips in.
                        // =============================================================
                        $rawMetadata = $user->metadata ?? [];
                        if (is_string($rawMetadata)) {
                            $rawMetadata = json_decode($rawMetadata, true) ?: [];
                        }
                        if (!is_array($rawMetadata)) {
                            $rawMetadata = [];
                        }

                        $deletionInfo   = $rawMetadata['deletion_info'] ?? [];
                        $originalData   = $deletionInfo['original_data'] ?? [];

                        // Real originals (before scrub) — fall back to null if they're
                        // themselves tombstones from the pre-fix era.
                        $originalName   = $originalData['name']  ?? null;
                        $originalEmail  = $originalData['email'] ?? null;
                        $originalPhone  = $originalData['phone'] ?? null;

                        // Sanity-check: discard "originals" that are the scrub values.
                        if ($originalEmail && str_contains($originalEmail, '@deleted.example')) {
                            $originalEmail = null;
                        }
                        if ($originalName === 'Deleted User') {
                            $originalName = null;
                        }

                        // Detect a scrubbed/tombstoned record.
                        $isScrubbed = str_contains((string) $user->email, '@deleted.example')
                            || ($user->name ?? '') === 'Deleted User';

                        // Whether the restore button should be enabled.
                        $canRestore = !($isScrubbed && !$originalEmail);

                        // Days ago — cast to int to avoid fractional output from Carbon 3.
                        $daysAgo = $user->deleted_at
                            ? (int) $user->deleted_at->diffInDays(now())
                            : 0;
                    @endphp
                    <tr class="border-b hover:bg-opacity-50" style="border-bottom-color: var(--border-color); background-color: var(--bg-secondary);">
                        <td class="py-4 px-4">
                            <input type="checkbox" name="user_ids[]" value="{{ $user->id }}"
                                   class="user-checkbox rounded border-gray-300"
                                   data-user-name="{{ $user->name }}">
                        </td>

                        <!-- User Info -->
                        <td class="py-4 px-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-10 h-10 opacity-50">
                                    @if($user->photo)
                                        <img src="{{ $user->photo_url }}"
                                             alt="{{ $user->name }}"
                                             class="w-10 h-10 rounded-full object-cover border-2 border-gray-400">
                                    @else
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold text-white text-sm bg-gray-400">
                                            {{ $user->getInitials() }}
                                        </div>
                                    @endif
                                </div>
                                <div class="ml-4">
                                    <div class="font-medium flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                                        {{ $user->name }}

                                        <span class="px-2 py-1 text-xs rounded-full bg-gray-500 text-white">
                                            Deleted
                                        </span>

                                        @if($isScrubbed)
                                            <span class="px-2 py-1 text-xs rounded-full"
                                                  style="background-color: rgba(107, 114, 128, 0.2); color: #6b7280;"
                                                  title="Personal data was scrubbed when the account was deleted.">
                                                <i class="fas fa-user-secret mr-1"></i> Scrubbed
                                            </span>
                                        @endif
                                    </div>

                                    <div class="text-sm" style="color: var(--text-secondary);">{{ $user->email }}</div>

                                    @if($originalName || $originalEmail)
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-history mr-1"></i>
                                            Original:
                                            @if($originalName) <strong>{{ $originalName }}</strong>@endif
                                            @if($originalEmail) &lt;{{ $originalEmail }}&gt;@endif
                                        </div>
                                    @endif

                                    @if($user->phone)
                                        <div class="text-xs" style="color: var(--text-secondary);">{{ $user->phone }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <!-- User Type -->
                        <td class="py-4 px-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                <i class="fas fa-{{ $user->isFieldAgent() ? 'user-check' : ($user->isLandlord() ? 'home' : ($user->isTenant() ? 'user-friends' : 'user-shield')) }} mr-1"></i>
                                {{ $user->type_name }}
                            </span>
                        </td>

                        <!-- ✅ FIXED: Deleted By -->
                        <td class="py-4 px-4">
                            @php
                                // Priority for deleter resolution:
                                // 1. metadata.deletion_info.deleted_by  (new flow)
                                // 2. $user->deleted_by column          (fallback)
                                // 3. "Unknown"
                                $deleterId = $deletionInfo['deleted_by']
                                    ?? $user->deleted_by
                                    ?? null;

                                $deleter = $deleterId
                                    ? \App\Models\User::withTrashed()->find($deleterId)
                                    : null;

                                $deleterName = $deleter->name
                                    ?? ($deletionInfo['deleted_by_name'] ?? null)
                                    ?? 'Unknown';

                                // Reason: metadata → column → null
                                $deletionReason = $deletionInfo['deletion_reason']
                                    ?? $user->deletion_reason
                                    ?? null;
                            @endphp

                            <div style="color: var(--text-primary);">
                                {{ $deleterName }}
                            </div>

                            @if($deletionReason)
                                <div class="text-xs mt-1" style="color: var(--text-secondary);" title="{{ $deletionReason }}">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    {{ Str::limit($deletionReason, 40) }}
                                </div>
                            @endif
                        </td>

                        <!-- Deleted At -->
                        <td class="py-4 px-4">
                            <div style="color: var(--text-primary);">
                                {{ $user->deleted_at->format('M j, Y') }}
                            </div>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                {{ $user->deleted_at->format('g:i A') }}
                            </div>
                        </td>

                        <!-- ✅ FIXED: Days Ago (int cast) -->
                        <td class="py-4 px-4">
                            @php
                                $color = 'text-green-600';
                                if ($daysAgo >= 7)  $color = 'text-yellow-600';
                                if ($daysAgo >= 30) $color = 'text-orange-600';
                                if ($daysAgo >= 90) $color = 'text-red-600';

                                $exactTime = $user->deleted_at->format('M j, Y g:i A');
                            @endphp
                            <span class="font-medium {{ $color }}" title="Deleted on {{ $exactTime }}">
                                {{ $daysAgo }} day{{ $daysAgo === 1 ? '' : 's' }}
                            </span>
                            @if($daysAgo === 0)
                            <div class="text-xs text-gray-500 mt-1">Today</div>
                            @elseif($daysAgo === 1)
                            <div class="text-xs text-gray-500 mt-1">Yesterday</div>
                            @endif
                        </td>

                        <!-- Actions -->
                        <td class="py-4 px-4">
                            <div class="flex items-center space-x-2">
                                <button onclick="viewDeletedUser({{ $user->id }})"
                                        class="btn-secondary btn-sm" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>

                                @if($canRestore)
                                    <button onclick="restoreUser({{ $user->id }}, '{{ addslashes($originalName ?: $user->name) }}')"
                                            class="btn-success btn-sm" title="Restore User">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                @else
                                    <button type="button"
                                            disabled
                                            class="btn-secondary btn-sm"
                                            style="opacity: 0.4; cursor: not-allowed;"
                                            title="This record was scrubbed on deletion. Its original email is no longer available.">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                @endif

                                <button onclick="permanentlyDeleteUser({{ $user->id }}, '{{ addslashes($user->name) }}')"
                                        class="btn-danger btn-sm" title="Permanently Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-8 px-4 text-center" style="color: var(--text-secondary);">
                            <i class="fas fa-trash-alt text-4xl mb-4 opacity-50"></i>
                            <p class="text-lg">Trash is empty</p>
                            <p class="text-sm mt-2">No deleted users found in the trash bin.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
        <div class="border-t pt-4 mt-4" style="border-color: var(--border-color);">
            {{ $users->links() }}
        </div>
        @endif
    </div>

    <!-- Bulk Actions Card -->
    <div class="card p-6">
        <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Bulk Actions</h3>
            <div class="flex items-center space-x-3">
                <select id="bulk-action" class="p-2 border rounded"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">Choose Action</option>
                    <option value="restore">Restore Selected</option>
                    <option value="permanent_delete">Permanently Delete Selected</option>
                </select>
                <button onclick="performBulkAction()" class="btn-primary">
                    <i class="fas fa-play mr-2"></i> Apply
                </button>
            </div>
        </div>
        <div class="mt-4 text-sm" style="color: var(--text-secondary);">
            <i class="fas fa-info-circle mr-2"></i>
            Select deleted users using the checkboxes and choose an action to perform on multiple users at once.
        </div>
    </div>
</div>

{{-- ============================================================== --}}
{{-- MODALS                                                          --}}
{{-- ============================================================== --}}

<div id="viewUserModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto" style="background-color: var(--bg-primary);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Deleted User Details</h3>
        <div id="userDetailsContent"></div>
        <div class="flex justify-end space-x-3 mt-6">
            <button type="button" onclick="closeViewModal()" class="btn-secondary">Close</button>
        </div>
    </div>
</div>

<div id="permanentDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-6 w-full max-w-md" style="background-color: var(--bg-primary);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Permanently Delete User</h3>

        <div class="alert alert-danger mb-4">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <strong>Warning:</strong> This action cannot be undone!
        </div>

        <p class="mb-4" style="color: var(--text-primary);">
            Are you sure you want to permanently delete <strong id="permanentDeleteUserName"></strong>?
            This will completely remove the user account and all associated data from the system.
        </p>

        <div class="mb-4">
            <label for="permanent_deletion_reason" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                Reason for Permanent Deletion (Required)
            </label>
            <textarea id="permanent_deletion_reason" name="permanent_deletion_reason" rows="3"
                      class="w-full p-2 border rounded"
                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                      placeholder="Enter reason for permanent deletion..." required></textarea>
        </div>

        <div class="flex justify-end space-x-3">
            <button type="button" onclick="closePermanentDeleteModal()" class="btn-secondary">Cancel</button>
            <button type="button" id="confirmPermanentDeleteBtn" onclick="confirmPermanentDelete()" class="btn-danger">
                <i class="fas fa-trash mr-2"></i> Permanently Delete
            </button>
        </div>
    </div>
</div>

<div id="bulkPermanentDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-6 w-full max-w-md" style="background-color: var(--bg-primary);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Permanently Delete Selected Users</h3>

        <div class="alert alert-danger mb-4">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <strong>Warning:</strong> This action cannot be undone!
        </div>

        <p class="mb-4" style="color: var(--text-primary);">
            You are about to permanently delete <strong><span id="bulkDeleteCount">0</span></strong> user(s).
            This will completely remove these user accounts and all associated data from the system.
        </p>

        <div class="mb-4">
            <label for="bulk_permanent_deletion_reason" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                Reason for Permanent Deletion (Required)
            </label>
            <textarea id="bulk_permanent_deletion_reason" name="bulk_permanent_deletion_reason" rows="3"
                      class="w-full p-2 border rounded"
                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                      placeholder="Enter reason for permanent deletion..." required></textarea>
        </div>

        <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeBulkPermanentDeleteModal()" class="btn-secondary">Cancel</button>
            <button type="button" id="confirmBulkPermanentDeleteBtn" onclick="confirmBulkPermanentDelete()" class="btn-danger">
                <i class="fas fa-trash mr-2"></i> Permanently Delete All
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let currentUserId = null;
let currentUserName = null;
let selectedUserIds = [];
let selectedUserNames = [];

// Select All
document.getElementById('select-all').addEventListener('change', function(e) {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(checkbox => { checkbox.checked = e.target.checked; });
    updateSelectedUsers();
});

function updateSelectedUsers() {
    selectedUserIds = [];
    selectedUserNames = [];
    const checkboxes = document.querySelectorAll('input[name="user_ids[]"]:checked');
    checkboxes.forEach(checkbox => {
        selectedUserIds.push(checkbox.value);
        selectedUserNames.push(checkbox.dataset.userName);
    });
    document.getElementById('bulkDeleteCount').textContent = selectedUserIds.length;
}

function viewDeletedUser(userId) {
    fetch(`/admin/users/${userId}/deleted-details`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            document.getElementById('userDetailsContent').innerHTML = data.html;
            document.getElementById('viewUserModal').classList.remove('hidden');
        } else {
            showNotification('Error loading user details: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error loading user details:', error);
        showNotification('An error occurred while loading user details: ' + error.message, 'error');
    });
}

function closeViewModal() { document.getElementById('viewUserModal').classList.add('hidden'); }

function restoreUser(userId, userName) {
    if (confirm(`Are you sure you want to restore "${userName}"?`)) {
        fetch(`/admin/users/${userId}/restore`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(errorData => {
                    throw new Error(errorData.message || `Restore failed: ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showNotification(data.message || 'User restored successfully!', 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                throw new Error(data.message || 'Restore operation failed');
            }
        })
        .catch(error => {
            console.error('Restore error:', error);
            showNotification('An error occurred while restoring the user: ' + error.message, 'error');
        });
    }
}

function permanentlyDeleteUser(userId, userName) {
    currentUserId = userId;
    currentUserName = userName;
    document.getElementById('permanentDeleteUserName').textContent = userName;
    document.getElementById('permanent_deletion_reason').value = '';
    document.getElementById('permanentDeleteModal').classList.remove('hidden');
}

function closePermanentDeleteModal() {
    document.getElementById('permanentDeleteModal').classList.add('hidden');
    currentUserId = null;
    currentUserName = null;
}

function confirmPermanentDelete() {
    const deletionReason = document.getElementById('permanent_deletion_reason').value;
    if (!deletionReason.trim()) {
        showNotification('Please provide a reason for permanent deletion.', 'error');
        return;
    }
    const button = document.getElementById('confirmPermanentDeleteBtn');
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';

    fetch(`/admin/users/${currentUserId}/force-delete`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ deletion_reason: deletionReason, _method: 'DELETE' })
    })
    .then(response => response.json().then(data => ({ data, ok: response.ok, status: response.status })))
    .then(({ data, ok, status }) => {
        if (ok && data.success) {
            showNotification(data.message || 'User permanently deleted!', 'success');
            closePermanentDeleteModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            throw new Error(data.message || `Delete failed with status: ${status}`);
        }
    })
    .catch(error => {
        console.error('Permanent delete error:', error);
        showNotification('Error: ' + error.message, 'error');
        button.disabled = false;
        button.innerHTML = originalText;
    });
}

function emptyTrash() {
    if (confirm('⚠️ ARE YOU ABSOLUTELY SURE?\n\nThis will permanently delete ALL users in the trash!\n\nThis action cannot be undone.')) {
        fetch(`/admin/users/empty-trash`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ confirmation: 'empty_all_trash' })
        })
        .then(response => response.json().then(data => ({ data, ok: response.ok })))
        .then(({ data, ok }) => {
            if (ok && data.success) {
                showNotification(data.message || 'Trash emptied successfully!', 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                throw new Error(data.message || 'Empty trash operation failed');
            }
        })
        .catch(error => {
            console.error('Empty trash error:', error);
            showNotification('An error occurred while emptying the trash: ' + error.message, 'error');
        });
    }
}

function performBulkAction() {
    const action = document.getElementById('bulk-action').value;
    updateSelectedUsers();

    if (!action) { showNotification('Please select an action.', 'error'); return; }
    if (selectedUserIds.length === 0) { showNotification('Please select at least one user.', 'error'); return; }

    if (action === 'restore') {
        if (confirm(`Are you sure you want to restore ${selectedUserIds.length} user(s)?`)) {
            performBulkRestore();
        }
    } else if (action === 'permanent_delete') {
        showBulkPermanentDeleteModal();
    }
}

function showBulkPermanentDeleteModal() {
    if (selectedUserIds.length === 0) {
        showNotification('Please select at least one user.', 'error');
        return;
    }
    document.getElementById('bulkDeleteCount').textContent = selectedUserIds.length;
    document.getElementById('bulk_permanent_deletion_reason').value = '';
    document.getElementById('bulkPermanentDeleteModal').classList.remove('hidden');
}

function closeBulkPermanentDeleteModal() {
    document.getElementById('bulkPermanentDeleteModal').classList.add('hidden');
}

function confirmBulkPermanentDelete() {
    const deletionReason = document.getElementById('bulk_permanent_deletion_reason').value;
    if (!deletionReason.trim()) {
        showNotification('Please provide a reason for permanent deletion.', 'error');
        return;
    }
    const button = document.getElementById('confirmBulkPermanentDeleteBtn');
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';

    fetch('{{ route("admin.users.bulk-permanent-delete") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            user_ids: selectedUserIds,
            deletion_reason: deletionReason,
            _method: 'DELETE'
        })
    })
    .then(response => response.json().then(data => ({ data, ok: response.ok })))
    .then(({ data, ok }) => {
        button.disabled = false;
        button.innerHTML = originalText;
        if (ok && data.success) {
            showNotification(data.message || `Successfully permanently deleted ${selectedUserIds.length} user(s)!`, 'success');
            closeBulkPermanentDeleteModal();
            setTimeout(() => window.location.reload(), 1500);
        } else if (data.deleted_count > 0) {
            showNotification(`Partially completed: ${data.message}`, 'warning');
            setTimeout(() => window.location.reload(), 2000);
        } else {
            throw new Error(data.message || `Bulk permanent delete failed`);
        }
    })
    .catch(error => {
        console.error('Bulk permanent delete error:', error);
        button.disabled = false;
        button.innerHTML = originalText;
        showNotification('An error occurred during bulk permanent delete: ' + error.message, 'error');
    });
}

function performBulkRestore() {
    const button = document.querySelector('#bulk-action').nextElementSibling;
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';

    fetch('{{ route("admin.users.bulk-restore") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ user_ids: selectedUserIds })
    })
    .then(response => response.json().then(data => ({ data, ok: response.ok })))
    .then(({ data, ok }) => {
        button.disabled = false;
        button.innerHTML = originalText;
        if (ok && data.success) {
            showNotification(data.message || `Successfully restored ${selectedUserIds.length} user(s)!`, 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else if (data.restored_count > 0) {
            showNotification(`Partially completed: ${data.message}`, 'warning');
            setTimeout(() => window.location.reload(), 2000);
        } else {
            throw new Error(data.message || `Bulk restore failed`);
        }
    })
    .catch(error => {
        console.error('Bulk restore error:', error);
        button.disabled = false;
        button.innerHTML = originalText;
        showNotification('An error occurred during bulk restore: ' + error.message, 'error');
    });
}

function showNotification(message, type = 'info') {
    const existing = document.querySelectorAll('.custom-notification');
    existing.forEach(n => n.remove());

    const notification = document.createElement('div');
    notification.className = `custom-notification fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transform transition-all duration-300 ${
        type === 'success' ? 'bg-green-500 text-white' :
        type === 'error' ? 'bg-red-500 text-white' :
        type === 'warning' ? 'bg-yellow-500 text-white' :
        'bg-blue-500 text-white'
    }`;
    notification.innerHTML = `<div class="flex items-center"><i class="fas fa-${
        type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'
    } mr-2"></i><span>${message}</span></div>`;
    document.body.appendChild(notification);
    setTimeout(() => { notification.style.transform = 'translateX(0)'; }, 10);
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => { if (notification.parentNode) notification.remove(); }, 300);
    }, 5000);
}

document.getElementById('viewUserModal')?.addEventListener('click', function(e) { if (e.target === this) closeViewModal(); });
document.getElementById('permanentDeleteModal')?.addEventListener('click', function(e) { if (e.target === this) closePermanentDeleteModal(); });
document.getElementById('bulkPermanentDeleteModal')?.addEventListener('click', function(e) { if (e.target === this) closeBulkPermanentDeleteModal(); });

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeViewModal();
        closePermanentDeleteModal();
        closeBulkPermanentDeleteModal();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
        e.preventDefault();
        const checkboxes = document.querySelectorAll('.user-checkbox');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(checkbox => { checkbox.checked = !allChecked; });
        document.getElementById('select-all').checked = !allChecked;
        updateSelectedUsers();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.user-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedUsers);
    });
});
</script>

<style>
.btn-sm { padding: 0.375rem 0.75rem; font-size: 0.875rem; border-radius: 0.375rem; }
.alert { padding: 0.75rem 1rem; border-radius: 0.375rem; margin-bottom: 1rem; }
.alert-danger {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border: 1px solid rgba(var(--danger-rgb), 0.3);
}
tr:hover { background-color: rgba(var(--primary-rgb), 0.02) !important; }
#viewUserModal, #permanentDeleteModal, #bulkPermanentDeleteModal { transition: opacity 0.3s ease; }
#viewUserModal:not(.hidden), #permanentDeleteModal:not(.hidden), #bulkPermanentDeleteModal:not(.hidden) { animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
input[type="checkbox"] { cursor: pointer; }
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-3 { grid-template-columns: 1fr; }
    table { font-size: 0.875rem; }
    .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
    .flex.space-x-2 { flex-wrap: wrap; gap: 0.25rem; }
}
.opacity-50 { opacity: 0.5; }
.text-green-600 { color: #059669; }
.text-yellow-600 { color: #d97706; }
.text-orange-600 { color: #ea580c; }
.text-red-600 { color: #dc2626; }
.fa-spinner { animation: spin 1s linear infinite; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
button:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
@endsection