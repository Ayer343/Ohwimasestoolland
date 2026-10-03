{{-- resources/views/sms/logs.blade.php --}}
@extends('layouts.app')

@section('title', 'SMS Logs')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex flex-wrap justify-between items-center mb-6 gap-3">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
            <i class="fas fa-history mr-2" style="color: var(--primary);"></i>
            SMS Logs
            <span class="text-sm ml-2 px-2 py-0.5 rounded-full" style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                {{ $logs->total() }} records
            </span>
        </h1>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sms.index') }}" class="px-4 py-2 rounded-lg font-medium transition-all hover:scale-105"
               style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                <i class="fas fa-arrow-left mr-2"></i> Dashboard
            </a>
            <button onclick="exportLogs()" class="px-4 py-2 rounded-lg font-medium transition-all hover:scale-105"
                    style="background-color: var(--info); color: white;">
                <i class="fas fa-file-export mr-2"></i> Export
            </button>
            <button onclick="cleanupLogs()" class="px-4 py-2 rounded-lg font-medium transition-all hover:scale-105"
                    style="background-color: var(--warning); color: white;">
                <i class="fas fa-trash-alt mr-2"></i> Cleanup
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="rounded-lg p-4 mb-6" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
        <form method="GET" action="{{ route('sms.logs') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[150px]">
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Status</label>
                <select name="status" class="w-full rounded-lg px-3 py-2 text-sm transition-all"
                        style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <option value="">All Status</option>
                    <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>✅ Success</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>❌ Failed</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>⏳ Pending</option>
                </select>
            </div>

            <div class="flex-1 min-w-[150px]">
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Provider</label>
                <select name="provider" class="w-full rounded-lg px-3 py-2 text-sm transition-all"
                        style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <option value="">All Providers</option>
                    @foreach($providers as $provider)
                        <option value="{{ $provider }}" {{ request('provider') === $provider ? 'selected' : '' }}>{{ ucfirst($provider) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex-1 min-w-[130px]">
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">From Date</label>
                <input type="date" name="from_date" value="{{ request('from_date') }}"
                       class="w-full rounded-lg px-3 py-2 text-sm transition-all"
                       style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
            </div>

            <div class="flex-1 min-w-[130px]">
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">To Date</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}"
                       class="w-full rounded-lg px-3 py-2 text-sm transition-all"
                       style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
            </div>

            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Search</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search phone or message..."
                       class="w-full rounded-lg px-3 py-2 text-sm transition-all"
                       style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
            </div>

            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium text-white transition-all hover:scale-105"
                        style="background-color: var(--primary);">
                    <i class="fas fa-filter mr-1"></i> Filter
                </button>
                <a href="{{ route('sms.logs') }}" class="px-4 py-2 rounded-lg text-sm font-medium transition-all hover:scale-105"
                   style="background-color: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-secondary);">
                    <i class="fas fa-times mr-1"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="rounded-lg overflow-hidden" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Provider</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Phone</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Message</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">User</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Time</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td class="px-4 py-3">
                                <input type="checkbox" class="log-checkbox" value="{{ $log->id }}">
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $log->status_badge }}">
                                    <i class="fas {{ $log->status_icon }} mr-1"></i>
                                    {{ ucfirst($log->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm" style="color: var(--text-secondary);">{{ $log->provider_display }}</td>
                            <td class="px-4 py-3 text-sm" style="color: var(--text-secondary);">{{ $log->masked_phone }}</td>
                            <td class="px-4 py-3 text-sm max-w-xs" style="color: var(--text-secondary);">
                                <div class="truncate" title="{{ $log->message }}">{{ $log->preview }}</div>
                                @if($log->is_test)
                                    <span class="text-xs px-1 py-0.5 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        <i class="fas fa-flask mr-0.5"></i> Test
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm" style="color: var(--text-secondary);">
                                {{ $log->user ? $log->user->name : 'System' }}
                            </td>
                            <td class="px-4 py-3 text-sm" style="color: var(--text-secondary);">
                                {{ $log->created_at->format('Y-m-d H:i') }}
                                <div class="text-xs" style="color: var(--text-secondary); opacity: 0.7;">
                                    {{ $log->created_at->diffForHumans() }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-1">
                                    <a href="{{ route('sms.logs.show', $log) }}" 
                                       class="p-1.5 rounded transition hover:bg-opacity-20" style="color: var(--text-secondary);">
                                        <i class="fas fa-eye text-xs"></i>
                                    </a>
                                    <button onclick="deleteLog({{ $log->id }})" 
                                            class="p-1.5 rounded transition hover:bg-opacity-20" style="color: var(--text-secondary);">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center" style="color: var(--text-secondary);">
                                <i class="fas fa-inbox text-3xl mb-2 block"></i>
                                No SMS logs found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination & Bulk Actions -->
    <div class="flex flex-wrap justify-between items-center mt-4 gap-3">
        <div class="flex gap-2">
            <button onclick="bulkDelete()" class="px-4 py-2 rounded-lg text-sm font-medium transition-all hover:scale-105"
                    style="background-color: var(--danger); color: white;">
                <i class="fas fa-trash-alt mr-1"></i> Delete Selected
            </button>
        </div>
        <div>
            {{ $logs->appends(request()->query())->links() }}
        </div>
    </div>
</div>

<!-- Bulk Delete Confirmation Modal -->
<div id="bulkDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="rounded-lg p-6 max-w-md w-full" style="background-color: var(--card-bg);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Confirm Bulk Delete</h3>
        <p class="text-sm mb-6" style="color: var(--text-secondary);">
            Are you sure you want to delete the selected SMS logs? This action cannot be undone.
        </p>
        <div class="flex justify-end gap-3">
            <button onclick="closeBulkDeleteModal()" class="px-4 py-2 rounded-lg text-sm font-medium"
                    style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                Cancel
            </button>
            <button onclick="confirmBulkDelete()" class="px-4 py-2 rounded-lg text-sm font-medium text-white"
                    style="background-color: var(--danger);">
                <i class="fas fa-trash-alt mr-1"></i> Delete
            </button>
        </div>
    </div>
</div>

<script>
// Select all checkbox
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.log-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

// Bulk delete
function bulkDelete() {
    const selected = document.querySelectorAll('.log-checkbox:checked');
    if (selected.length === 0) {
        alert('Please select at least one log to delete.');
        return;
    }
    document.getElementById('bulkDeleteModal').classList.remove('hidden');
}

function closeBulkDeleteModal() {
    document.getElementById('bulkDeleteModal').classList.add('hidden');
}

function confirmBulkDelete() {
    const selected = document.querySelectorAll('.log-checkbox:checked');
    const ids = Array.from(selected).map(cb => cb.value);
    
    fetch('{{ route('sms.logs.bulk-delete') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ ids: ids })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'Failed to delete logs.');
        }
        closeBulkDeleteModal();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        closeBulkDeleteModal();
    });
}

// Delete single log
function deleteLog(id) {
    if (!confirm('Are you sure you want to delete this log?')) return;
    
    fetch(`{{ url('sms/logs') }}/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'Failed to delete log.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

// Export logs
function exportLogs() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = '{{ route('sms.logs.export') }}?' + params.toString();
}

// Cleanup logs
function cleanupLogs() {
    const days = prompt('Enter number of days to keep (logs older than this will be deleted):', '30');
    if (days === null) return;
    
    if (!days || isNaN(days) || days < 1 || days > 365) {
        alert('Please enter a valid number between 1 and 365.');
        return;
    }
    
    if (!confirm(`Delete SMS logs older than ${days} days?`)) return;
    
    fetch('{{ route('sms.logs.cleanup') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ days: days })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'Failed to cleanup logs.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}
</script>
@endsection