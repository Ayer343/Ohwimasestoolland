{{-- resources/views/sanitation/workers/index.blade.php --}}

@php
    use Illuminate\Support\Str;

    // -----------------------------------------------------------------
    // Layout detection
    // -----------------------------------------------------------------
    $user    = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout  = $isAdmin ? 'layouts.app' : 'layouts.san';

    // -----------------------------------------------------------------
    // Theme detection — server-side mirror of the layout's client theme
    // -----------------------------------------------------------------
    $currentTheme = null;

    if ($user && method_exists($user, 'getThemePreference')) {
        $currentTheme = $user->getThemePreference();
    }

    if (!$currentTheme) {
        $currentTheme = request()->cookie('theme')
            ?? session('theme')
            ?? 'light';
    }

    $isDark = $currentTheme === 'dark';

    // -----------------------------------------------------------------
    // Inline badge style builders — literal values, no CSS variables.
    // -----------------------------------------------------------------
    $baseBadgeStyle = 'display:inline-flex!important;'
        . 'align-items:center;'
        . 'justify-content:center;'
        . 'padding:2px 8px;'
        . 'border-radius:6px;'
        . 'font-size:11px;'
        . 'font-weight:600;'
        . 'line-height:1.4;'
        . 'white-space:nowrap;'
        . 'visibility:visible!important;'
        . 'opacity:1!important;'
        . 'border:1px solid transparent;';

    // ---------- Worker status ----------
    $statusStyles = [
        'active' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(34,197,94,0.22)' : 'rgba(34,197,94,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#4ade80' : '#16a34a') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(34,197,94,0.45)' : 'rgba(34,197,94,0.35)') . '!important;',

        'inactive' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;',

        'on_leave' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(245,158,11,0.22)' : 'rgba(245,158,11,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#fbbf24' : '#b45309') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(245,158,11,0.45)' : 'rgba(245,158,11,0.35)') . '!important;',

        'suspended' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(239,68,68,0.22)' : 'rgba(239,68,68,0.15)') . '!important;'
            . 'color:' . ($isDark ? '#f87171' : '#dc2626') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(239,68,68,0.45)' : 'rgba(239,68,68,0.35)') . '!important;',

        'unknown' => $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;',
    ];

    // ---------- Skill chips ----------
    $skillChipStyle = $baseBadgeStyle
        . 'padding:1px 6px;'
        . 'font-size:10px;'
        . 'border-radius:4px;'
        . 'background-color:' . ($isDark ? 'rgba(59,130,246,0.22)' : 'rgba(59,130,246,0.12)') . '!important;'
        . 'color:' . ($isDark ? '#60a5fa' : '#2563eb') . '!important;'
        . 'border-color:' . ($isDark ? 'rgba(59,130,246,0.45)' : 'rgba(59,130,246,0.3)') . '!important;';

    $skillOverflowChipStyle = $baseBadgeStyle
        . 'padding:1px 6px;'
        . 'font-size:10px;'
        . 'border-radius:4px;'
        . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
        . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
        . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;';

    // -----------------------------------------------------------------
    // Shared resolver — normalize key and fall back to 'unknown'
    // -----------------------------------------------------------------
    $resolveBadgeStyle = function (?string $key, array $map) use ($baseBadgeStyle, $isDark) {
        $normalized = is_string($key) ? strtolower(trim($key)) : '';
        $normalized = str_replace('-', '_', $normalized);

        if (isset($map[$normalized])) return $map[$normalized];
        if (isset($map['unknown']))    return $map['unknown'];

        return $baseBadgeStyle
            . 'background-color:' . ($isDark ? 'rgba(148,163,184,0.2)' : 'rgba(107,114,128,0.12)') . '!important;'
            . 'color:' . ($isDark ? '#cbd5e1' : '#6b7280') . '!important;'
            . 'border-color:' . ($isDark ? 'rgba(148,163,184,0.35)' : 'rgba(107,114,128,0.25)') . '!important;';
    };
@endphp

@extends($layout)

@section('title', 'Sanitation Workers')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-user-hard-hat mr-2" style="color: var(--primary);"></i>
                    Sanitation Workers
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Manage all sanitation workers and their assignments
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('sanitation.workers.create') }}" class="btn-primary">
                    <i class="fas fa-plus mr-2"></i> Add Worker
                </a>
                <a href="{{ route('sanitation.dashboard') }}" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="card p-3 text-center">
                <div class="text-xl font-bold" style="color: var(--text-primary);">{{ $workers->total() }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Total Workers</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-green-500">{{ $workers->where('status', 'active')->count() }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Active</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-gray-500">{{ $workers->where('status', 'inactive')->count() }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Inactive</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-blue-500">{{ $workers->whereNotNull('supervisor_id')->count() }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Assigned</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card p-4 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Search</label>
                    <input type="search" name="search" value="{{ request('search') }}"
                           autocomplete="off"
                           class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="Name, phone...">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Status</label>
                    <select name="status" class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Statuses</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Supervisor</label>
                    <select name="supervisor_id" class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Supervisors</option>
                        @foreach($supervisors as $supervisor)
                            <option value="{{ $supervisor->id }}" {{ request('supervisor_id') == $supervisor->id ? 'selected' : '' }}>
                                {{ $supervisor->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary flex-1">
                        <i class="fas fa-search mr-2"></i> Filter
                    </button>
                    <a href="{{ route('sanitation.workers.index') }}" class="btn-secondary flex-1 text-center">
                        <i class="fas fa-undo mr-2"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Workers Table -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead style="background-color: var(--bg-secondary);">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Worker</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Contact</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Supervisor</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Skills</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($workers as $worker)
                            @php
                                // ✅ Compute the status badge style once, per row
                                $statusKey   = $worker->status ?: 'unknown';
                                $statusStyle = $resolveBadgeStyle($statusKey, $statusStyles);
                                $statusLabel = ucfirst(str_replace('_', ' ', $statusKey));

                                $skills       = is_array($worker->skills) ? $worker->skills : [];
                                $skillsCount  = count($skills);
                                $skillsPreview = array_slice($skills, 0, 2);
                            @endphp
                            <tr style="background-color: var(--bg-secondary);">
                                <td class="px-4 py-3">
                                    <div class="flex items-center space-x-3">
                                        @if($worker->profile_photo)
                                            <img src="{{ Storage::url($worker->profile_photo) }}"
                                                 alt="{{ $worker->full_name }}"
                                                 class="w-10 h-10 rounded-full object-cover">
                                        @else
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold text-sm"
                                                 style="background: linear-gradient(135deg, var(--info), var(--primary));">
                                                {{ strtoupper(substr($worker->first_name, 0, 1) . substr($worker->last_name, 0, 1)) }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $worker->full_name }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                ID: {{ $worker->employee_id ?? 'N/A' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm" style="color: var(--text-primary);">{{ $worker->phone }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $worker->email }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($worker->supervisor)
                                        <span class="text-sm" style="color: var(--text-primary);">
                                            {{ $worker->supervisor->full_name }}
                                        </span>
                                    @else
                                        <span class="text-sm" style="color: var(--text-secondary);">Unassigned</span>
                                    @endif
                                </td>

                                {{-- ✅ Status badge — inline style, theme-aware, cannot be hidden --}}
                                <td class="px-4 py-3">
                                    <span style="{{ $statusStyle }}" data-worker-status="{{ $statusKey }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    @if($skillsCount > 0)
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($skillsPreview as $skill)
                                                <span style="{{ $skillChipStyle }}">
                                                    {{ $skill }}
                                                </span>
                                            @endforeach
                                            @if($skillsCount > 2)
                                                <span style="{{ $skillOverflowChipStyle }}">
                                                    +{{ $skillsCount - 2 }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs" style="color: var(--text-secondary);">No skills</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex space-x-2">
                                        <a href="{{ route('sanitation.workers.show', $worker) }}"
                                           class="text-sm hover:underline" style="color: var(--primary);"
                                           title="View details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('sanitation.workers.edit', $worker) }}"
                                           class="text-sm hover:underline" style="color: var(--info);"
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="deleteWorker({{ $worker->id }})"
                                                class="text-sm hover:underline" style="color: var(--danger);"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-users text-4xl mb-3 block" style="color: var(--text-secondary); opacity: 0.3;"></i>
                                    <p>No workers found</p>
                                    <a href="{{ route('sanitation.workers.create') }}" class="btn-primary mt-3 inline-block">
                                        <i class="fas fa-plus mr-2"></i> Add First Worker
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($workers->hasPages())
                <div class="px-4 py-3 border-t" style="border-color: var(--border-color);">
                    {{ $workers->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden flex items-center justify-center">
    <div class="absolute inset-0 bg-black bg-opacity-50" onclick="closeDeleteModal()"></div>
    <div class="relative rounded-xl shadow-2xl max-w-md w-full mx-4 p-6"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="flex items-start gap-3 mb-4">
            <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                 style="background-color: rgba(239, 68, 68, 0.15);">
                <i class="fas fa-exclamation-triangle" style="color: #dc2626;"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    Delete Worker?
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    This will permanently remove the worker and cannot be undone. Workers with active
                    assignments cannot be deleted.
                </p>
            </div>
        </div>

        <form id="deleteForm" method="POST">
            @csrf
            @method('DELETE')
            <div class="flex justify-end space-x-3 pt-4 border-t" style="border-color: var(--border-color);">
                <button type="button" onclick="closeDeleteModal()" class="btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn-danger">
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* ----------------------------------------------------------------- */
    /* Buttons                                                           */
    /* ----------------------------------------------------------------- */
    .btn-primary, .btn-secondary, .btn-danger {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-primary { background-color: var(--primary); color: white; }
    .btn-danger  { background-color: #ef4444;        color: white; }
    .btn-secondary {
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    .btn-primary:hover, .btn-danger:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
        text-decoration: none;
    }
    .btn-secondary:hover {
        opacity: 0.8;
        text-decoration: none;
        color: var(--text-primary);
    }

    /* ----------------------------------------------------------------- */
    /* Cards                                                             */
    /* ----------------------------------------------------------------- */
    .card {
        background-color: var(--card-bg);
        border-radius: 0.75rem;
        border: 1px solid var(--border-color);
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }

    /* ----------------------------------------------------------------- */
    /* Note: Status and skill badges use inline styles — no class rules. */
    /* ----------------------------------------------------------------- */
</style>
@endpush

@push('scripts')
<script>
    function deleteWorker(id) {
        const modal = document.getElementById('deleteModal');
        const form  = document.getElementById('deleteForm');

        // Route template with placeholder — replaced at runtime
        const routeTemplate = '{{ route("sanitation.workers.destroy", ["worker" => "__ID__"]) }}';
        form.action = routeTemplate.replace('__ID__', id);

        modal.classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    // Close modal on Escape
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeDeleteModal();
    });

    // Close modal when clicking on the backdrop
    document.getElementById('deleteModal')?.addEventListener('click', function (event) {
        if (event.target === this) closeDeleteModal();
    });
</script>
@endpush