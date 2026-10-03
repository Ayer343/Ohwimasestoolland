{{-- resources/views/sanitation/approvals/pending.blade.php --}}

@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout = $isAdmin ? 'layouts.app' : 'layouts.san';
@endphp

@extends($layout)

@section('title', 'Pending Approvals')

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
                    <i class="fas fa-user-check mr-2" style="color: var(--primary);"></i>
                    Pending Landlord Approvals
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Waste collection requests awaiting a landlord response
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('sanitation.dashboard') }}" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- Stats strip                                                  --}}
        {{-- ============================================================ --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-yellow-500">{{ $stats['pending'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Pending</div>
            </div>
            <div class="card p-3 text-center" style="{{ ($stats['expiring_soon'] ?? 0) > 0 ? 'border-color: #f59e0b;' : '' }}">
                <div class="text-xl font-bold text-orange-500">{{ $stats['expiring_soon'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Expiring Soon</div>
            </div>
            <div class="card p-3 text-center" style="{{ ($stats['expired'] ?? 0) > 0 ? 'border-color: #ef4444;' : '' }}">
                <div class="text-xl font-bold text-red-500">{{ $stats['expired'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Expired</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-green-500">{{ $stats['approved_today'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Approved Today</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-blue-500">{{ $stats['auto_approved_today'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Auto-Approved Today</div>
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- Filters                                                      --}}
        {{-- ============================================================ --}}
        <div class="card p-4 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="Property name or address...">
                </div>
                <div class="flex items-end gap-2">
                    <label class="flex items-center gap-2 text-sm cursor-pointer flex-1"
                           style="color: var(--text-primary);">
                        <input type="checkbox" name="expiring_soon" value="1"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               {{ request('expiring_soon') ? 'checked' : '' }}>
                        Expiring within 48h
                    </label>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search mr-1"></i> Filter
                    </button>
                    <a href="{{ route('sanitation.approvals.pending') }}" class="btn-secondary">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </form>
        </div>

        {{-- ============================================================ --}}
        {{-- Bulk approve bar (admin only, hidden until rows selected)     --}}
        {{-- ============================================================ --}}
        @if($isAdmin)
            <div id="bulkBar" class="card p-3 mb-4 hidden"
                 style="background-color: rgba(59,130,246,0.08); border-color: #3b82f6;">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-square" style="color: #3b82f6;"></i>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                            <span id="bulkCount">0</span> selected
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('sanitation.approvals.bulk') }}" id="bulkForm">
                            @csrf
                            <div id="bulkInputs"></div>
                            <button type="submit" class="btn-primary text-sm"
                                    onclick="return confirm('Approve all selected requests on behalf of the landlord?');">
                                <i class="fas fa-check-double mr-1"></i> Bulk Approve
                            </button>
                        </form>
                        <button type="button" id="clearSelection" class="btn-secondary text-sm">
                            <i class="fas fa-times mr-1"></i> Clear
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- ============================================================ --}}
        {{-- Pending requests table                                       --}}
        {{-- ============================================================ --}}
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead style="background-color: var(--bg-secondary);">
                        <tr>
                            @if($isAdmin)
                                <th class="px-3 py-3 text-left" style="width: 40px;">
                                    <input type="checkbox" id="selectAll"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </th>
                            @endif
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Property</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Landlord</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Requested By</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Expires</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--border-color);">
                        @forelse($pendingRequests as $request)
                            @php
                                $expiresAt = $request->approval_expires_at;
                                $isExpired = $expiresAt && $expiresAt->isPast();
                                $isExpiringSoon = $expiresAt && !$isExpired && $expiresAt->diffInHours(now()) <= 48;
                            @endphp
                            <tr style="background-color: var(--bg-secondary);">
                                @if($isAdmin)
                                    <td class="px-3 py-3">
                                        <input type="checkbox"
                                               class="row-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                               value="{{ $request->id }}">
                                    </td>
                                @endif
                                <td class="px-4 py-3">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $request->property->property_name ?? 'N/A' }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $request->property->digital_address ?? 'No address' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm" style="color: var(--text-primary);">
                                        {{ $request->property->landlord->name ?? 'N/A' }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $request->property->landlord->phone ?? '' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm" style="color: var(--text-primary);">
                                        {{ $request->requestedBy->name ?? 'System' }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ optional($request->created_at)->diffForHumans() }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($expiresAt)
                                        <div class="text-sm font-medium
                                            @if($isExpired) text-red-500
                                            @elseif($isExpiringSoon) text-orange-500
                                            @else text-green-500 @endif">
                                            @if($isExpired)
                                                <i class="fas fa-hourglass-end mr-1"></i> Expired
                                            @elseif($isExpiringSoon)
                                                <i class="fas fa-exclamation-triangle mr-1"></i> {{ $expiresAt->diffForHumans() }}
                                            @else
                                                <i class="fas fa-clock mr-1"></i> {{ $expiresAt->diffForHumans() }}
                                            @endif
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            {{ $expiresAt->format('M d, Y g:i A') }}
                                        </div>
                                    @else
                                        <span style="color: var(--text-secondary);">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('sanitation.properties.show', $request->property_id) }}"
                                           class="text-sm hover:underline"
                                           style="color: var(--primary);"
                                           title="View property">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <form method="POST"
                                              action="{{ route('sanitation.approvals.resend', $request) }}"
                                              class="inline">
                                            @csrf
                                            <button type="submit" class="text-sm hover:underline"
                                                    style="color: #3b82f6; background: none; border: none; cursor: pointer; padding: 0;"
                                                    title="Resend approval email">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>

                                        @if($isAdmin)
                                            <form method="POST"
                                                  action="{{ route('sanitation.approvals.handle', $request) }}"
                                                  class="inline"
                                                  onsubmit="return confirm('Approve this request on behalf of the landlord?');">
                                                @csrf
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="declaration" value="1">
                                                <button type="submit" class="text-sm hover:underline"
                                                        style="color: #22c55e; background: none; border: none; cursor: pointer; padding: 0;"
                                                        title="Force approve (admin)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-4 py-12 text-center">
                                    <i class="fas fa-check-circle text-4xl mb-3 block" style="color: #22c55e; opacity: 0.4;"></i>
                                    <p class="font-medium" style="color: var(--text-primary);">No pending approvals</p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        All landlord approvals have been resolved.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($pendingRequests->hasPages())
                <div class="px-4 py-3 border-t" style="border-color: var(--border-color);">
                    {{ $pendingRequests->appends(request()->query())->links() }}
                </div>
            @endif
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
        transition: all 0.2s; border: none;
        cursor: pointer; text-decoration: none;
    }
    .btn-primary { background-color: var(--primary); color: white; }
    .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); color: white; text-decoration: none; }
    .btn-secondary {
        background-color: var(--bg-secondary); color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    .btn-secondary:hover { opacity: 0.8; color: var(--text-primary); text-decoration: none; }
    .card {
        background-color: var(--card-bg); border-radius: 0.75rem;
        border: 1px solid var(--border-color);
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }
    .card.overflow-hidden { border-radius: 0.75rem; overflow: hidden; }
</style>
@endpush

@push('scripts')
@if($isAdmin)
<script>
(function () {
    const bulkBar     = document.getElementById('bulkBar');
    const bulkCount   = document.getElementById('bulkCount');
    const bulkInputs  = document.getElementById('bulkInputs');
    const selectAll   = document.getElementById('selectAll');
    const clearBtn    = document.getElementById('clearSelection');
    const checkboxes  = () => Array.from(document.querySelectorAll('.row-checkbox'));

    function refresh() {
        const selected = checkboxes().filter(cb => cb.checked);
        bulkBar?.classList.toggle('hidden', selected.length === 0);
        if (bulkCount) bulkCount.textContent = selected.length;

        if (bulkInputs) {
            bulkInputs.innerHTML = '';
            selected.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'request_ids[]';
                input.value = cb.value;
                bulkInputs.appendChild(input);
            });
        }

        if (selectAll) {
            const all = checkboxes();
            const allChecked = all.length > 0 && all.every(cb => cb.checked);
            selectAll.checked = allChecked;
            selectAll.indeterminate = !allChecked && selected.length > 0;
        }
    }

    document.addEventListener('change', e => {
        if (e.target.classList?.contains('row-checkbox')) refresh();
    });

    selectAll?.addEventListener('change', function () {
        checkboxes().forEach(cb => cb.checked = this.checked);
        refresh();
    });

    clearBtn?.addEventListener('click', () => {
        checkboxes().forEach(cb => cb.checked = false);
        if (selectAll) selectAll.checked = false;
        refresh();
    });
})();
</script>
@endif
@endpush