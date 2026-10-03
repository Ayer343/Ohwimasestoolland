{{-- resources/views/sanitation/requests/pending.blade.php --}}

@php
    $user = auth()->user();
    $currentPersonnel = $user->sanitationPersonnel;
    $currentPersonnelId = $currentPersonnel?->id;

    $isAdmin             = $user->isAdmin() || $user->isSuperAdmin();
    $isRootSupervisor    = $currentPersonnel
        && $currentPersonnel->isSupervisor()
        && is_null($currentPersonnel->supervisor_id);
    $isSubSupervisor     = $currentPersonnel
        && $currentPersonnel->isSupervisor()
        && !is_null($currentPersonnel->supervisor_id);
    $isRegularPersonnel  = $currentPersonnel
        && !$currentPersonnel->isSupervisor();

    $layout = $isAdmin ? 'layouts.app' : 'layouts.san';
@endphp

@extends($layout)

@section('title', 'Pending Requests')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">

        {{-- ============================================================ --}}
        {{-- Flash messages                                               --}}
        {{-- ============================================================ --}}
        @if(session('success'))
            <div class="card p-3 mb-4" style="background-color: rgba(34,197,94,0.1); border-color: #22c55e;">
                <div class="flex items-center gap-2" style="color: #16a34a;">
                    <i class="fas fa-check-circle"></i>
                    <span class="text-sm font-medium">{{ session('success') }}</span>
                </div>
            </div>
        @endif
        @if(session('error'))
            <div class="card p-3 mb-4" style="background-color: rgba(239,68,68,0.1); border-color: #ef4444;">
                <div class="flex items-center gap-2" style="color: #dc2626;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span class="text-sm font-medium">{{ session('error') }}</span>
                </div>
            </div>
        @endif

        {{-- ============================================================ --}}
        {{-- Header                                                       --}}
        {{-- ============================================================ --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-clock mr-2" style="color: var(--warning);"></i>
                    Pending Requests
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    @if($isRootSupervisor)
                        Manage all pending requests for your entire team
                    @elseif($isSubSupervisor)
                        Pending requests assigned to your team
                    @elseif($isAdmin)
                        All pending waste collection requests across the system
                    @else
                        View and manage your pending collection requests
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('sanitation.requests.index') }}" class="btn-secondary btn-sm">
                    <i class="fas fa-arrow-left mr-2"></i> All Requests
                </a>
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- ✅ Root supervisor scope banner                              --}}
        {{-- ============================================================ --}}
        @if($isRootSupervisor)
            <div class="card p-4 mb-6"
                 style="background-color: rgba(245, 158, 11, 0.08);
                        border-left: 4px solid #f59e0b;">
                <div class="flex items-start gap-3">
                    <i class="fas fa-crown text-2xl mt-0.5" style="color: #f59e0b;"></i>
                    <div>
                        <h4 class="font-semibold text-sm" style="color: var(--text-primary);">
                            Root Supervisor View
                        </h4>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            You're seeing <strong>all pending requests for your entire team</strong>.
                            You can assign any request to any personnel in your hierarchy —
                            including yourself, your sub-supervisors, and their teams.
                            Requests marked <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium"
                                                     style="background-color: rgba(59, 130, 246, 0.15); color: #3b82f6;">
                                <i class="fas fa-user-slash"></i> Unassigned
                            </span>
                            need a personnel picked before work can begin.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- ============================================================ --}}
        {{-- Stats strip                                                  --}}
        {{-- ============================================================ --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-yellow-500">
                    {{ $requests->total() }}
                </div>
                <div class="text-xs" style="color: var(--text-secondary);">
                    @if($isRootSupervisor || $isSubSupervisor || $isAdmin)
                        Total Pending
                    @else
                        My Pending
                    @endif
                </div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-red-500">
                    {{ $priorityCounts['emergency'] ?? 0 }}
                </div>
                <div class="text-xs" style="color: var(--text-secondary);">Emergency</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-orange-500">
                    {{ $priorityCounts['high'] ?? 0 }}
                </div>
                <div class="text-xs" style="color: var(--text-secondary);">High Priority</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-blue-500">
                    {{ $priorityCounts['medium'] ?? 0 }}
                </div>
                <div class="text-xs" style="color: var(--text-secondary);">Medium Priority</div>
            </div>
            @if($isRootSupervisor || $isSubSupervisor || $isAdmin)
                <div class="card p-4 text-center"
                     style="{{ ($unassignedCount ?? 0) > 0 ? 'border-color: #3b82f6; background-color: rgba(59, 130, 246, 0.05);' : '' }}">
                    <div class="text-2xl font-bold text-blue-600">
                        {{ $unassignedCount ?? 0 }}
                    </div>
                    <div class="text-xs" style="color: var(--text-secondary);">Unassigned</div>
                </div>
            @else
                <div class="card p-4 text-center">
                    <div class="text-2xl font-bold text-gray-500">
                        {{ $requests->where('assigned_to', $currentPersonnelId)->count() }}
                    </div>
                    <div class="text-xs" style="color: var(--text-secondary);">Assigned to Me</div>
                </div>
            @endif
        </div>

        {{-- ============================================================ --}}
        {{-- Sort hint                                                    --}}
        {{-- ============================================================ --}}
        @if(($isRootSupervisor || $isSubSupervisor || $isAdmin) && ($unassignedCount ?? 0) > 0)
            <div class="flex items-center gap-2 mb-3 text-xs"
                 style="color: var(--text-secondary);">
                <i class="fas fa-sort-amount-down" style="color: var(--primary);"></i>
                <span>Unassigned requests are shown first — they need action.</span>
            </div>
        @endif

        {{-- ============================================================ --}}
        {{-- Requests List                                                --}}
        {{-- ============================================================ --}}
        <div class="space-y-4">
            @forelse($requests as $request)
                @php
                    $isUnassigned = empty($request->assigned_to);
                    $isAssignedToMe = $currentPersonnelId && (int) $request->assigned_to === (int) $currentPersonnelId;
                    $assignedToSomeoneElse = !$isUnassigned && !$isAssignedToMe;
                @endphp
                <div class="card p-4 hover:shadow-lg transition-shadow
                            {{ $isUnassigned ? 'border-l-4' : '' }}"
                     style="{{ $isUnassigned ? 'border-left-color: #3b82f6;' : '' }} background-color: var(--card-bg);">

                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">

                        {{-- LEFT: request details --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-3 mb-2">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    @if($request->priority == 'emergency') bg-red-100 text-red-600
                                    @elseif($request->priority == 'high') bg-orange-100 text-orange-600
                                    @elseif($request->priority == 'medium') bg-yellow-100 text-yellow-600
                                    @else bg-blue-100 text-blue-600 @endif">
                                    <i class="fas fa-flag mr-1"></i>
                                    {{ ucfirst($request->priority) }}
                                </span>

                                <span class="text-sm" style="color: var(--text-secondary);">
                                    #{{ $request->id }}
                                </span>

                                <span class="text-sm" style="color: var(--text-secondary);">
                                    <i class="far fa-clock mr-1"></i>
                                    {{ $request->created_at->diffForHumans() }}
                                </span>

                                {{-- Assignment status chip --}}
                                @if($isUnassigned)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold"
                                          style="background-color: rgba(59, 130, 246, 0.15); color: #3b82f6;">
                                        <i class="fas fa-user-slash"></i> Unassigned
                                    </span>
                                @elseif($isAssignedToMe)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold"
                                          style="background-color: rgba(34, 197, 94, 0.15); color: #22c55e;">
                                        <i class="fas fa-user-check"></i> Assigned to you
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium"
                                          style="background-color: rgba(148, 163, 184, 0.15); color: var(--text-secondary);">
                                        <i class="fas fa-user"></i>
                                        {{ $request->assignedTo?->full_name ?? 'Someone else' }}
                                    </span>
                                @endif
                            </div>

                            <div class="font-medium" style="color: var(--text-primary);">
                                {{ $request->property->property_name ?? 'Unknown Property' }}
                            </div>

                            <div class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-map-marker-alt mr-1"></i>
                                {{ $request->digital_address ?? $request->property->digital_address ?? 'No address' }}
                            </div>

                            <div class="text-sm mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-trash mr-1"></i>
                                {{ $wasteTypes[$request->waste_type] ?? $request->waste_type }}
                                <span class="mx-2">•</span>
                                Requested by: {{ $request->requestedBy?->name ?? 'Unknown' }}
                            </div>
                        </div>

                        {{-- RIGHT: actions --}}
                        <div class="flex flex-col items-end gap-2 flex-shrink-0">
                            <div class="flex space-x-2">
                                {{-- ✅ Assign button — now a proper button wired to the modal --}}
                                <button type="button"
                                        data-assign-request="{{ $request->id }}"
                                        class="btn-primary btn-sm">
                                    <i class="fas fa-user-plus mr-1"></i>
                                    {{ $isUnassigned ? 'Assign' : 'Reassign' }}
                                </button>

                                <a href="{{ route('sanitation.requests.show', ['collectionRequest' => $request->id]) }}"
                                   class="btn-secondary btn-sm">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card p-8 text-center" style="color: var(--text-secondary);">
                    <i class="fas fa-check-circle text-5xl mb-4 block" style="color: var(--success);"></i>
                    <h3 class="text-xl font-semibold mb-2" style="color: var(--text-primary);">
                        @if($isRootSupervisor || $isSubSupervisor)
                            Your Team Is All Caught Up!
                        @else
                            All Caught Up!
                        @endif
                    </h3>
                    <p class="text-sm">
                        @if($isRootSupervisor)
                            There are no pending collection requests across your team right now.
                        @elseif($isSubSupervisor)
                            There are no pending collection requests for your team right now.
                        @else
                            There are no pending collection requests at this time.
                        @endif
                    </p>
                </div>
            @endforelse
        </div>

        @if($requests->hasPages())
            <div class="mt-6">
                {{ $requests->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</div>

{{-- ============================================================== --}}
{{-- Assign Request Modal                                            --}}
{{-- ============================================================== --}}
<div id="assignModal" class="fixed inset-0 z-50 hidden flex items-center justify-center">
    <div class="absolute inset-0 bg-black bg-opacity-50" onclick="closeAssignModal()"></div>
    <div class="relative rounded-xl shadow-2xl max-w-md w-full mx-4 p-6"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">

        <div class="flex items-start gap-3 mb-4">
            <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                 style="background-color: rgba(var(--primary-rgb, 59,130,246), 0.15);">
                <i class="fas fa-user-plus" style="color: var(--primary);"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    Assign Request
                </h3>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    @if($isRootSupervisor)
                        Choose any personnel in your team hierarchy.
                    @elseif($isSubSupervisor)
                        Choose one of your direct reports.
                    @else
                        Choose a personnel to assign this request.
                    @endif
                </p>
            </div>
        </div>

        <form id="assignForm" method="POST" action="">
            @csrf

            <div class="mb-4">
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                    Select Personnel <span class="text-red-500">*</span>
                </label>
                <select name="personnel_id" id="personnelSelect"
                        class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                        required>
                    <option value="">Select a personnel...</option>

                    @forelse($availablePersonnel ?? [] as $person)
                        <option value="{{ $person->id }}">
                            {{ $person->full_name }}
                            ({{ $person->role }})
                            @if($person->id === $currentPersonnelId)
                                — You
                            @endif
                        </option>
                    @empty
                        <option value="" disabled>
                            No personnel available for assignment
                        </option>
                    @endforelse
                </select>

                @if(empty($availablePersonnel) || count($availablePersonnel) === 0)
                    <p class="text-xs mt-2" style="color: #ef4444;">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        No assignable personnel found.
                        @if($isRootSupervisor)
                            Add sub-supervisors or personnel to your team first.
                        @endif
                    </p>
                @endif
            </div>

            <div class="flex justify-end space-x-3 pt-4 border-t"
                 style="border-color: var(--border-color);">
                <button type="button" onclick="closeAssignModal()" class="btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-check mr-1"></i> Assign Request
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    // ✅ Route template with placeholder — populated at runtime
    const assignRouteTemplate = "{{ route('sanitation.requests.assign', ['collectionRequest' => '__ID__']) }}";

    // -------------------------------------------------------------
    // Modal controls
    // -------------------------------------------------------------
    function openAssignModal(requestId) {
        const modal = document.getElementById('assignModal');
        const form  = document.getElementById('assignForm');
        if (!modal || !form) return;

        form.action = assignRouteTemplate.replace('__ID__', requestId);

        // Reset dropdown so previous selection doesn't linger
        const select = document.getElementById('personnelSelect');
        if (select) select.value = '';

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeAssignModal() {
        const modal = document.getElementById('assignModal');
        if (modal) modal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    // Expose globally (called by inline onclick handlers if any)
    window.assignRequest = openAssignModal;
    window.closeAssignModal = closeAssignModal;

    // -------------------------------------------------------------
    // Wire up every "Assign" button
    // -------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-assign-request]').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const id = this.getAttribute('data-assign-request');
                openAssignModal(id);
            });
        });

        // ---------------------------------------------------------
        // AJAX form submission
        // ---------------------------------------------------------
        const form = document.getElementById('assignForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Assigning...';

                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    }
                })
                .then(async response => {
                    // Handle both JSON success and validation error responses
                    const data = await response.json().catch(() => ({}));

                    if (response.ok && data.success) {
                        showToast('success', data.message || 'Request assigned successfully!');
                        setTimeout(() => window.location.reload(), 1200);
                    } else if (response.status === 419) {
                        showToast('error', 'Session expired. Please refresh the page.');
                    } else {
                        showToast('error', data.message || 'Failed to assign request.');
                    }
                })
                .catch(error => {
                    console.error('[assign] error:', error);
                    showToast('error', 'An error occurred while assigning the request.');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    closeAssignModal();
                });
            });
        }

        // ---------------------------------------------------------
        // Escape key closes modal
        // ---------------------------------------------------------
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeAssignModal();
        });

        // ---------------------------------------------------------
        // Click outside modal closes
        // ---------------------------------------------------------
        const modal = document.getElementById('assignModal');
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === this) closeAssignModal();
            });
        }
    });

    // -------------------------------------------------------------
    // Toast notifications
    // -------------------------------------------------------------
    function showToast(type, message) {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'fixed top-4 right-4 z-[9999] space-y-2';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500' };
        const icons  = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle' };

        toast.className = `${colors[type] || 'bg-gray-500'} text-white px-4 py-3 rounded-lg shadow-lg flex items-center transform transition-all duration-300`;
        toast.innerHTML = `
            <i class="fas ${icons[type] || 'fa-bell'} mr-2"></i>
            <span>${message}</span>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
})();
</script>
@endpush

@push('styles')
<style>
    .card {
        background-color: var(--card-bg);
        border-radius: 0.75rem;
        border: 1px solid var(--border-color);
        transition: all 0.2s ease;
    }

    .card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .btn-primary, .btn-secondary {
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-weight: 500;
        transition: all 0.2s;
        border: 1px solid transparent;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
        text-decoration: none;
    }

    .btn-primary {
        background: linear-gradient(to right, var(--primary), var(--info));
        color: white;
    }
    .btn-primary:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        color: white;
        text-decoration: none;
    }
    .btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .btn-secondary {
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        border-color: var(--border-color);
    }
    .btn-secondary:hover {
        background-color: var(--bg-tertiary, var(--bg-secondary));
        opacity: 0.85;
        text-decoration: none;
        color: var(--text-primary);
    }

    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.75rem;
    }

    #assignModal {
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }

    #toastContainer {
        position: fixed;
        top: 1rem;
        right: 1rem;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        max-width: 24rem;
        width: 100%;
    }

    /* Smooth fade for unassigned cards */
    .card[style*="border-left-color"] {
        animation: unassignedGlow 3s ease-in-out infinite;
    }

    @keyframes unassignedGlow {
        0%, 100% { border-left-color: #3b82f6; }
        50%      { border-left-color: #93c5fd; }
    }
</style>
@endpush