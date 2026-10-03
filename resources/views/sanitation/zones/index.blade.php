{{-- resources/views/sanitation/zones/index.blade.php --}}

@php
    use Illuminate\Support\Str;

    $user    = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout  = $isAdmin ? 'layouts.app' : 'layouts.san';

    // -----------------------------------------------------------------
    // Status badge presentation map for zones (active/inactive only).
    // -----------------------------------------------------------------
    $zoneStatusMap = [
        1 => ['label' => 'Active',   'class' => 'status-active'],
        0 => ['label' => 'Inactive', 'class' => 'status-inactive'],
    ];
@endphp

@extends($layout)

@section('title', 'Collection Zones')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-map-marked-alt mr-2" style="color: var(--primary);"></i>
                    Collection Zones
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Manage waste collection zones and assignments
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('sanitation.zones.create') }}" class="btn-primary">
                    <i class="fas fa-plus mr-2"></i> New Zone
                </a>
                <a href="{{ route('sanitation.zones.export') }}" class="btn-secondary">
                    <i class="fas fa-download mr-2"></i> Export
                </a>
                <a href="{{ route('sanitation.dashboard') }}" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
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
                        <button type="button"
                                onclick="this.closest('.card').remove()"
                                class="text-gray-400 hover:text-gray-600">
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
                        <button type="button"
                                onclick="this.closest('.card').remove()"
                                class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="card p-3 text-center">
                <div class="text-xl font-bold" style="color: var(--text-primary);">{{ $stats['total'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Total Zones</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-green-500">{{ $stats['active'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Active</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-red-500">{{ $stats['inactive'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Inactive</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-blue-500">{{ $stats['with_assigned'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Assigned</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-yellow-500">{{ $stats['without_assigned'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Unassigned</div>
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
                           placeholder="Name, code, description...">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Status</label>
                    <select name="status" class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                {{ ucfirst($status) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Assigned Personnel</label>
                    <select name="assigned_personnel_id" class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All</option>
                        @foreach($personnel as $person)
                            <option value="{{ $person->id }}" {{ request('assigned_personnel_id') == $person->id ? 'selected' : '' }}>
                                {{ $person->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary flex-1">
                        <i class="fas fa-search mr-2"></i> Filter
                    </button>
                    <a href="{{ route('sanitation.zones.index') }}" class="btn-secondary flex-1 text-center">
                        <i class="fas fa-undo mr-2"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Zones Table -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead style="background-color: var(--bg-secondary);">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Name / Code
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Description
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Assigned Personnel
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Status
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Stats
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($zones as $zone)
                            @php
                                // --- Total properties ---
                                if (isset($zone->properties_count)) {
                                    $totalProperties = (int) $zone->properties_count;
                                } else {
                                    $totalProperties = $zone->properties()->count();
                                }

                                // --- Linked properties ---
                                if (isset($zone->linked_properties_count)) {
                                    $linkedProperties = (int) $zone->linked_properties_count;
                                } else {
                                    $linkedProperties = $zone->properties()
                                        ->whereHas('wasteCollectionRequests')
                                        ->count();

                                    if ($linkedProperties === 0) {
                                        $linkedProperties = \App\Models\Property::whereHas('wasteCollectionRequests', function ($q) use ($zone) {
                                            $q->where('collection_zone_id', $zone->id)
                                              ->orWhere('metadata->collection_zone_id', (string) $zone->id)
                                              ->orWhere('metadata->collection_zone_id', $zone->id);
                                        })->count();
                                    }
                                }

                                // --- Total requests ---
                                if (isset($zone->waste_collection_requests_count)) {
                                    $totalRequests = (int) $zone->waste_collection_requests_count;
                                } else {
                                    $totalRequests = $zone->wasteCollectionRequests()->count();
                                }

                                // --- Active requests ---
                                if (isset($zone->active_requests_count)) {
                                    $activeRequests = (int) $zone->active_requests_count;
                                } else {
                                    $activeRequests = $zone->wasteCollectionRequests()
                                        ->whereIn('status', ['pending', 'assigned', 'en_route', 'arrived', 'in_progress'])
                                        ->count();
                                }

                                // --- ✅ Status badge computed once, literal class ---
                                $isActive        = (bool) $zone->is_active;
                                $zoneStatusInfo  = $zoneStatusMap[$isActive ? 1 : 0];
                                $zoneStatusLabel = $zoneStatusInfo['label'];
                                $zoneStatusClass = $zoneStatusInfo['class'];
                            @endphp
                            <tr style="background-color: var(--bg-secondary);">
                                <td class="px-4 py-3">
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            {{ $zone->name }}
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            <span class="pill-neutral">
                                                {{ $zone->code ?? 'No code' }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm" style="color: var(--text-primary);">
                                        {{ Str::limit($zone->description ?? 'No description', 50) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($zone->assignedPersonnel)
                                        <div class="flex items-center space-x-2">
                                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs font-semibold"
                                                 style="background: linear-gradient(135deg, var(--primary), var(--secondary));">
                                                {{ substr($zone->assignedPersonnel->full_name, 0, 2) }}
                                            </div>
                                            <span class="text-sm" style="color: var(--text-primary);">
                                                {{ $zone->assignedPersonnel->full_name }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-xs" style="color: var(--text-secondary);">Unassigned</span>
                                    @endif
                                </td>

                                {{-- Status badge — literal class, theme-aware, always visible --}}
                                <td class="px-4 py-3">
                                    <span class="status-badge {{ $zoneStatusClass }}" data-zone-status="{{ $isActive ? 'active' : 'inactive' }}">
                                        {{ $zoneStatusLabel }}
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    <div class="flex flex-col gap-1 text-xs" style="color: var(--text-secondary);">
                                        {{-- Linked properties count (highlighted) --}}
                                        <div class="flex items-center gap-1.5">
                                            <i class="fas fa-home" style="color: var(--primary);"></i>
                                            <span style="color: var(--text-primary); font-weight: 700; font-size: 0.95rem;">
                                                {{ $linkedProperties }}
                                            </span>
                                            <span>linked {{ Str::plural('property', $linkedProperties) }}</span>
                                        </div>

                                        {{-- Total properties --}}
                                        <div class="flex items-center gap-1.5">
                                            <i class="fas fa-building" style="color: var(--text-secondary); opacity: 0.55;"></i>
                                            <span>{{ $totalProperties }} total</span>
                                        </div>

                                        {{-- Requests --}}
                                        <div class="flex items-center gap-1.5">
                                            <i class="fas fa-clipboard-list" style="color: var(--info);"></i>
                                            <span>{{ $totalRequests }} {{ Str::plural('request', $totalRequests) }}</span>
                                            @if($activeRequests > 0)
                                                <span class="status-badge status-active-count">
                                                    {{ $activeRequests }} active
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex space-x-2">
                                        {{-- View --}}
                                        <a href="{{ route('sanitation.zones.show', $zone) }}"
                                           class="text-sm hover:underline" style="color: var(--primary);"
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        {{-- Edit --}}
                                        <a href="{{ route('sanitation.zones.edit', $zone) }}"
                                           class="text-sm hover:underline" style="color: var(--info);"
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        {{-- Toggle active --}}
                                        <button type="button"
                                                class="text-sm hover:underline js-toggle-zone"
                                                data-zone-id="{{ $zone->id }}"
                                                data-zone-name="{{ $zone->name }}"
                                                data-zone-active="{{ $isActive ? '1' : '0' }}"
                                                style="color: {{ $isActive ? 'var(--warning)' : 'var(--success)' }};"
                                                title="{{ $isActive ? 'Deactivate zone' : 'Activate zone' }}">
                                            <i class="fas {{ $isActive ? 'fa-pause' : 'fa-play' }}"></i>
                                        </button>

                                        {{-- Delete --}}
                                        <button type="button"
                                                class="text-sm hover:underline js-delete-zone"
                                                data-zone-id="{{ $zone->id }}"
                                                data-zone-name="{{ $zone->name }}"
                                                style="color: var(--danger);"
                                                title="Delete zone">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-map-marked-alt text-4xl mb-3 block" style="color: var(--text-secondary); opacity: 0.3;"></i>
                                    <p>No collection zones found</p>
                                    <a href="{{ route('sanitation.zones.create') }}" class="btn-primary mt-3 inline-block">
                                        <i class="fas fa-plus mr-2"></i> Create First Zone
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($zones->hasPages())
                <div class="px-4 py-3 border-t" style="border-color: var(--border-color);">
                    {{ $zones->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="rounded-lg shadow-xl w-11/12 md:w-1/2 lg:w-1/3 max-w-lg"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="px-6 py-4 border-b flex justify-between items-center"
             style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-trash mr-2" style="color: var(--danger);"></i>
                Delete Zone
            </h3>
            <button type="button"
                    onclick="closeDeleteModal()"
                    class="p-1 rounded-full transition-colors duration-200"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="p-6">
            <p class="text-sm" style="color: var(--text-primary);">
                Are you sure you want to delete zone <strong id="deleteZoneName"></strong>?
            </p>
            <p class="text-xs mt-2" style="color: var(--text-secondary);">
                This action cannot be undone. All associated data will be removed.
            </p>

            {{-- ✅ Force delete option — only shown to admins --}}
            @if($isAdmin)
                <div class="mt-4 p-3 rounded-lg border" style="background-color: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.25);">
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox"
                               id="forceDeleteCheckbox"
                               name="force"
                               value="1"
                               class="mt-0.5"
                               style="color: var(--danger);">
                        <div>
                            <span class="text-sm font-medium" style="color: var(--danger);">
                                Force delete
                            </span>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Detaches all properties and waste collection requests from this zone
                                before deleting it. Only use if you intend to orphan those records.
                            </p>
                        </div>
                    </label>
                </div>
            @endif
        </div>

        <div class="px-6 py-4 border-t flex justify-end space-x-3"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <button type="button"
                    onclick="closeDeleteModal()"
                    class="px-4 py-2 text-sm font-medium transition-colors duration-200 rounded-lg"
                    style="color: var(--text-secondary);">
                Cancel
            </button>
            <form id="deleteForm" method="POST" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                        style="background-color: var(--danger); color: white;">
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    /* ----------------------------------------------------------------- */
    /* Buttons                                                           */
    /* ----------------------------------------------------------------- */
    .btn-primary, .btn-secondary {
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
    .btn-primary {
        background-color: var(--primary);
        color: white;
    }
    .btn-secondary {
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    .btn-primary:hover {
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
    /* ✅ STATUS BADGES — theme-aware, always visible                    */
    /* ----------------------------------------------------------------- */
    .status-badge,
    .pill-neutral {
        display: inline-flex !important;
        align-items: center;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
        line-height: 1.4;
        white-space: nowrap;
        visibility: visible !important;
        opacity: 1 !important;
        border: 1px solid transparent;
    }

    /* Neutral pills (code, unknown) */
    .pill-neutral,
    .status-unknown {
        background-color: rgba(107, 114, 128, 0.15);
        color: var(--text-secondary);
        border-color: rgba(107, 114, 128, 0.25);
    }
    [data-theme="dark"] .pill-neutral,
    [data-theme="dark"] .status-unknown {
        background-color: rgba(148, 163, 184, 0.18);
        color: #cbd5e1;
        border-color: rgba(148, 163, 184, 0.3);
    }

    /* Active = green */
    .status-active {
        background-color: rgba(34, 197, 94, 0.15);
        color: #16a34a;
        border-color: rgba(34, 197, 94, 0.3);
    }
    [data-theme="dark"] .status-active {
        background-color: rgba(34, 197, 94, 0.2);
        color: #4ade80;
        border-color: rgba(34, 197, 94, 0.4);
    }

    /* Inactive = red */
    .status-inactive {
        background-color: rgba(239, 68, 68, 0.15);
        color: #dc2626;
        border-color: rgba(239, 68, 68, 0.3);
    }
    [data-theme="dark"] .status-inactive {
        background-color: rgba(239, 68, 68, 0.2);
        color: #f87171;
        border-color: rgba(239, 68, 68, 0.4);
    }

    /* Small inline "N active" chip inside the Stats column */
    .status-active-count {
        padding: 1px 6px;
        font-size: 0.62rem;
        font-weight: 700;
        background-color: rgba(245, 158, 11, 0.2);
        color: #b45309;
        border-color: rgba(245, 158, 11, 0.35);
        margin-left: 4px;
    }
    [data-theme="dark"] .status-active-count {
        background-color: rgba(245, 158, 11, 0.28);
        color: #fbbf24;
        border-color: rgba(245, 158, 11, 0.45);
    }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        'use strict';

        // -----------------------------------------------------------------
        // Route templates — safe JSON-encoded, placeholder replaced at runtime.
        // -----------------------------------------------------------------
        const toggleRouteTemplate = @json(route('sanitation.zones.toggle-active', ['zone' => '__ZONE__']));
        const deleteRouteTemplate = @json(route('sanitation.zones.destroy',        ['zone' => '__ZONE__']));
        const csrfToken           = @json(csrf_token());

        // -----------------------------------------------------------------
        // Delete modal helpers
        // -----------------------------------------------------------------
        const deleteModal      = document.getElementById('deleteModal');
        const deleteForm       = document.getElementById('deleteForm');
        const deleteZoneNameEl = document.getElementById('deleteZoneName');

        function openDeleteModal(id, name) {
            if (!deleteModal || !deleteForm) {
                console.warn('Delete modal elements not found in DOM.');
                return;
            }

            deleteForm.action = deleteRouteTemplate.replace('__ZONE__', encodeURIComponent(id));
            if (deleteZoneNameEl) deleteZoneNameEl.textContent = name || 'this zone';

            deleteModal.classList.remove('hidden');
        }

        function closeDeleteModal() {
            if (deleteModal) deleteModal.classList.add('hidden');
        }

        // -----------------------------------------------------------------
        // Toggle zone active status — submits a hidden POST form
        // -----------------------------------------------------------------
        function toggleZone(id, name) {
            const label = name ? `"${name}"` : 'this zone';
            if (!confirm(`Toggle active status for ${label}?`)) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = toggleRouteTemplate.replace('__ZONE__', encodeURIComponent(id));
            form.style.display = 'none';

            const csrf = document.createElement('input');
            csrf.type  = 'hidden';
            csrf.name  = '_token';
            csrf.value = csrfToken;
            form.appendChild(csrf);

            // Method spoof — change to 'PATCH' if your route uses PATCH/PUT.
            const method = document.createElement('input');
            method.type  = 'hidden';
            method.name  = '_method';
            method.value = 'POST';
            form.appendChild(method);

            document.body.appendChild(form);
            form.submit();
        }

        // -----------------------------------------------------------------
        // Event delegation — works even for dynamically added rows
        // -----------------------------------------------------------------
        document.addEventListener('click', function (event) {
            const deleteBtn = event.target.closest('.js-delete-zone');
            if (deleteBtn) {
                event.preventDefault();
                openDeleteModal(
                    deleteBtn.dataset.zoneId,
                    deleteBtn.dataset.zoneName
                );
                return;
            }

            const toggleBtn = event.target.closest('.js-toggle-zone');
            if (toggleBtn) {
                event.preventDefault();
                toggleZone(
                    toggleBtn.dataset.zoneId,
                    toggleBtn.dataset.zoneName
                );
                return;
            }
        });

        // Close modal on Escape
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeDeleteModal();
        });

        // Close modal when clicking the backdrop
        if (deleteModal) {
            deleteModal.addEventListener('click', function (event) {
                if (event.target === this) closeDeleteModal();
            });
        }

        // Expose globally so inline handlers in the modal still work
        window.closeDeleteModal = closeDeleteModal;
        window.toggleZone       = toggleZone;
        window.deleteZone       = function (id, name) { openDeleteModal(id, name); };
    })();
</script>
@endpush