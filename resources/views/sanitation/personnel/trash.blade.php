{{-- resources/views/sanitation/personnel/trash.blade.php --}}

@php
    $user    = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout  = $isAdmin ? 'layouts.app' : 'layouts.san';
@endphp

@extends($layout)

@section('title', 'Personnel Trash')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="card p-0 overflow-hidden border-l-4 mb-6" style="border-left-color: var(--success);">
                <div class="p-4" style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(34, 197, 94, 0.05));">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-2xl mr-3" style="color: var(--success);"></i>
                            <div>
                                <h3 class="text-lg font-semibold" style="color: var(--success);">Success</h3>
                                <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('success') }}</p>
                            </div>
                        </div>
                        <button type="button" onclick="this.closest('.card').remove()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="card p-0 overflow-hidden border-l-4 mb-6" style="border-left-color: var(--danger);">
                <div class="p-4" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle text-2xl mr-3" style="color: var(--danger);"></i>
                            <div>
                                <h3 class="text-lg font-semibold" style="color: var(--danger);">Error</h3>
                                <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('error') }}</p>
                            </div>
                        </div>
                        <button type="button" onclick="this.closest('.card').remove()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i>
                    Personnel Trash
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Restore or permanently delete soft-deleted sanitation personnel.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('sanitation.personnel.index') }}" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Personnel
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="card p-3 text-center">
                <div class="text-xl font-bold" style="color: var(--danger);">{{ $stats['total_trashed'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Total Deleted</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-yellow-500">{{ $stats['trashed_today'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Deleted Today</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-orange-500">{{ $stats['trashed_this_week'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">This Week</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-blue-500">
                    @if($stats['oldest_deletion'])
                        {{ \Carbon\Carbon::parse($stats['oldest_deletion'])->diffInDays(now()) }}d
                    @else
                        —
                    @endif
                </div>
                <div class="text-xs" style="color: var(--text-secondary);">Oldest Deletion</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card p-4 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Search</label>
                    <input type="search" name="search" value="{{ request('search') }}"
                           autocomplete="off"
                           class="w-full p-2 border rounded-lg text-sm"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="Name, phone, employee ID...">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Role</label>
                    <select name="role" class="w-full p-2 border rounded-lg text-sm"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="all">All Roles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role }}" {{ request('role') == $role ? 'selected' : '' }}>
                                {{ ucfirst($role) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2 flex items-end gap-2">
                    <button type="submit" class="btn-primary flex-1">
                        <i class="fas fa-search mr-2"></i> Filter
                    </button>
                    <a href="{{ route('sanitation.personnel.trash') }}" class="btn-secondary flex-1 text-center">
                        <i class="fas fa-undo mr-2"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Trash Table -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead style="background-color: var(--bg-secondary);">
                        <tr>
                            <th class="px-4 py-3 text-left">
                                <input type="checkbox" id="select-all" class="rounded border-gray-300">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Employee</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Role</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Deleted At</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Days Ago</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($personnel as $person)
                            @php
                                $daysAgo = $person->deleted_at ? $person->deleted_at->diffInDays(now()) : 0;
                                $color = 'text-green-600';
                                if ($daysAgo >= 7)  $color = 'text-yellow-600';
                                if ($daysAgo >= 30) $color = 'text-orange-600';
                                if ($daysAgo >= 90) $color = 'text-red-600';
                            @endphp
                            <tr style="background-color: var(--bg-secondary);">
                                <td class="px-4 py-3">
                                    <input type="checkbox" name="ids[]" value="{{ $person->id }}"
                                           class="person-checkbox rounded border-gray-300"
                                           data-person-name="{{ $person->full_name }}">
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold text-sm opacity-60"
                                             style="background: linear-gradient(135deg, #6b7280, #9ca3af);">
                                            {{ strtoupper(substr($person->first_name, 0, 1) . substr($person->last_name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $person->full_name }}
                                                <span class="ml-2 px-2 py-0.5 text-[10px] rounded-full bg-gray-500 text-white">Deleted</span>
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                ID: {{ $person->employee_id }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                {{ $person->phone }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ ucfirst($person->role) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm" style="color: var(--text-primary);">
                                        {{ $person->deleted_at?->format('M j, Y') ?? '—' }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $person->deleted_at?->format('g:i A') ?? '' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-medium {{ $color }}">
                                        {{ $daysAgo }} day{{ $daysAgo !== 1 ? 's' : '' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button type="button"
                                                onclick="restorePersonnel({{ $person->id }}, {{ json_encode($person->full_name) }})"
                                                class="text-sm hover:underline"
                                                style="color: var(--success);"
                                                title="Restore">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <button type="button"
                                                onclick="forceDeletePersonnel({{ $person->id }}, {{ json_encode($person->full_name) }})"
                                                class="text-sm hover:underline"
                                                style="color: var(--danger);"
                                                title="Delete permanently">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-trash-alt text-4xl mb-3 block" style="opacity: 0.3;"></i>
                                    <p>Trash is empty</p>
                                    <p class="text-sm mt-1">No deleted personnel found.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($personnel->hasPages())
                <div class="px-4 py-3 border-t" style="border-color: var(--border-color);">
                    {{ $personnel->appends(request()->query())->links() }}
                </div>
            @endif
        </div>

        <!-- Bulk Actions -->
        <div class="card p-6 mt-6">
            <div class="flex flex-wrap justify-between items-center gap-3">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Bulk Actions</h3>
                <div class="flex items-center gap-3">
                    <select id="bulk-action" class="p-2 border rounded"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">Choose Action</option>
                        <option value="restore">Restore Selected</option>
                        <option value="force_delete">Permanently Delete Selected</option>
                    </select>
                    <button type="button" onclick="applyBulkAction()" class="btn-primary">
                        <i class="fas fa-play mr-2"></i> Apply
                    </button>
                </div>
            </div>
            <p class="mt-3 text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i>
                Selected: <span id="selected-count">0</span>
            </p>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .btn-primary, .btn-secondary {
        display: inline-flex; align-items: center;
        padding: 0.5rem 1rem; border-radius: 0.5rem;
        font-size: 0.875rem; font-weight: 500;
        transition: all 0.2s; border: none; cursor: pointer; text-decoration: none;
    }
    .btn-primary { background-color: var(--primary); color: white; }
    .btn-secondary {
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    .btn-primary:hover, .btn-secondary:hover { opacity: 0.9; text-decoration: none; }
    .btn-primary:hover { color: white; }
    .btn-secondary:hover { color: var(--text-primary); }
    .card {
        background-color: var(--card-bg); border-radius: 0.75rem;
        border: 1px solid var(--border-color);
        box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1);
    }
    .text-green-600 { color: #059669; }
    .text-yellow-600 { color: #d97706; }
    .text-orange-600 { color: #ea580c; }
    .text-red-600 { color: #dc2626; }
</style>
@endpush

@push('scripts')
<script>
    const restoreTemplate     = "{{ route('sanitation.personnel.restore',      ['id' => '__ID__']) }}";
    const forceDeleteTemplate = "{{ route('sanitation.personnel.force-delete', ['id' => '__ID__']) }}";
    const csrfToken           = "{{ csrf_token() }}";

    function updateSelectedCount() {
        const n = document.querySelectorAll('.person-checkbox:checked').length;
        const el = document.getElementById('selected-count');
        if (el) el.textContent = n;
    }

    document.getElementById('select-all')?.addEventListener('change', function (e) {
        document.querySelectorAll('.person-checkbox').forEach(cb => cb.checked = e.target.checked);
        updateSelectedCount();
    });

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('person-checkbox')) updateSelectedCount();
    });

    function restorePersonnel(id, name) {
        if (!confirm(`Restore "${name}"?`)) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = restoreTemplate.replace('__ID__', id);
        form.innerHTML = `<input type="hidden" name="_token" value="${csrfToken}">`;
        document.body.appendChild(form);
        form.submit();
    }

    function forceDeletePersonnel(id, name) {
        if (!confirm(`⚠️ PERMANENTLY delete "${name}"?\n\nThis cannot be undone.`)) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = forceDeleteTemplate.replace('__ID__', id);
        form.innerHTML = `
            <input type="hidden" name="_token" value="${csrfToken}">
            <input type="hidden" name="_method" value="DELETE">
        `;
        document.body.appendChild(form);
        form.submit();
    }

    function applyBulkAction() {
        const action = document.getElementById('bulk-action').value;
        const ids = Array.from(document.querySelectorAll('.person-checkbox:checked')).map(cb => cb.value);

        if (!action) { alert('Please select an action.'); return; }
        if (ids.length === 0) { alert('Please select at least one personnel.'); return; }

        const verb = action === 'restore' ? 'restore' : 'PERMANENTLY delete';
        if (!confirm(`Are you sure you want to ${verb} ${ids.length} record(s)?`)) return;

        const url = action === 'restore'
            ? "{{ route('sanitation.personnel.bulk-restore') }}"
            : "{{ route('sanitation.personnel.bulk-force-delete') }}";

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ ids })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(err => alert('Error: ' + err.message));
    }

    updateSelectedCount();
</script>
@endpush