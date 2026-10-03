{{-- resources/views/landlord/waste/approvals.blade.php --}}

@extends('layouts.landlord')

@section('title', 'Waste Collection Approvals')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">

        <!-- Flash messages -->
        @if(session('success'))
            <div class="mb-4 px-4 py-3 rounded-lg" style="background-color: rgba(34,197,94,0.1); color: #16a34a; border: 1px solid #16a34a;">
                <i class="fas fa-check-circle mr-2"></i> {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 px-4 py-3 rounded-lg" style="background-color: rgba(239,68,68,0.1); color: #dc2626; border: 1px solid #dc2626;">
                <i class="fas fa-exclamation-circle mr-2"></i> {{ session('error') }}
            </div>
        @endif

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-check-double mr-2" style="color: var(--primary);"></i>
                    Waste Collection Approvals
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Review and approve/reject waste collection requests for your properties
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('landlord.waste.requests') }}" class="btn-info">
                    <i class="fas fa-list mr-2"></i> All Requests
                </a>
                <a href="{{ route('landlord.waste.history') }}" class="btn-secondary">
                    <i class="fas fa-history mr-2"></i> History
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-yellow-500">{{ $stats['total_pending'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Pending Approval</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-green-500">{{ $stats['total_approved'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Approved</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-red-500">{{ $stats['total_rejected'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Rejected</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold" style="color: var(--text-primary);">{{ $stats['total_requests'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Total Requests</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-orange-500">{{ $stats['expiring_soon'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Expiring Soon</div>
            </div>
        </div>

        <!-- Pending Requests Table -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead style="background-color: var(--bg-secondary);">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary); width: 40px;">
                                <input type="checkbox" id="selectAll" class="rounded" style="color: var(--primary);">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Property
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Requested By
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Waste Type
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Requested At
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Expires
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--border-color);">
                        @forelse($pendingRequests as $request)
                            <tr style="background-color: var(--bg-secondary);">
                                <td class="px-4 py-3">
                                    <input type="checkbox" class="request-checkbox rounded"
                                           value="{{ $request->id }}" style="color: var(--primary);">
                                </td>
                                <td class="px-4 py-3">
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            {{ $request->property->property_name ?? 'N/A' }}
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            {{ $request->property->digital_address ?? 'No address' }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm" style="color: var(--text-primary);">
                                        {{ $request->requestedBy->name ?? 'Unknown' }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $request->requestedBy->email ?? '' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        @if($request->waste_type == 'general') bg-gray-100 text-gray-600
                                        @elseif($request->waste_type == 'recyclable') bg-green-100 text-green-600
                                        @elseif($request->waste_type == 'organic') bg-yellow-100 text-yellow-600
                                        @elseif($request->waste_type == 'hazardous') bg-red-100 text-red-600
                                        @elseif($request->waste_type == 'bulk') bg-purple-100 text-purple-600
                                        @else bg-gray-100 text-gray-600 @endif">
                                        {{ ucfirst($request->waste_type ?? 'General') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm" style="color: var(--text-primary);">
                                        {{ $request->created_at->format('M d, Y') }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $request->created_at->format('g:i A') }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($request->approval_expires_at)
                                        @php
                                            $secondsLeft = now()->diffInSeconds($request->approval_expires_at, false);
                                            $daysLeft = $secondsLeft > 0 ? ceil($secondsLeft / 86400) : 0;
                                        @endphp
                                        @if($secondsLeft <= 0)
                                            <span class="text-xs text-red-500 font-medium">Expired</span>
                                        @elseif($daysLeft <= 2)
                                            <span class="text-xs text-orange-500 font-medium">{{ $daysLeft }} day{{ $daysLeft == 1 ? '' : 's' }} left</span>
                                        @else
                                            <span class="text-xs" style="color: var(--text-secondary);">{{ $daysLeft }} days left</span>
                                        @endif
                                    @else
                                        <span class="text-xs" style="color: var(--text-secondary);">N/A</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex space-x-2">
                                        <button type="button"
                                                onclick="approveRequest('{{ $request->id }}')"
                                                class="text-sm hover:underline px-2 py-1 rounded"
                                                style="color: #22c55e; background-color: rgba(34, 197, 94, 0.1);">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="button"
                                                onclick="showRejectModal('{{ $request->id }}')"
                                                class="text-sm hover:underline px-2 py-1 rounded"
                                                style="color: #ef4444; background-color: rgba(239, 68, 68, 0.1);">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                        <a href="{{ route('landlord.waste.request.show', ['wasteCollectionRequest' => $request->id]) }}"
                                           class="text-sm hover:underline px-2 py-1 rounded"
                                           style="color: var(--primary); background-color: rgba(var(--primary-rgb, 59,130,246), 0.1);">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-check-circle text-4xl mb-3 block" style="color: var(--text-secondary); opacity: 0.3;"></i>
                                    <p>No pending approvals</p>
                                    <p class="text-xs mt-1">All waste collection requests have been reviewed</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Bulk Actions -->
            @if($pendingRequests->count() > 0)
                <div class="px-4 py-3 border-t flex items-center justify-between" style="border-color: var(--border-color);">
                    <div class="flex items-center space-x-4">
                        <span class="text-xs" style="color: var(--text-secondary);">
                            <span id="selectedCount">0</span> selected
                        </span>
                        <button type="button"
                                onclick="bulkApprove()"
                                class="text-sm px-3 py-1 rounded transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                style="background-color: #22c55e; color: white;"
                                id="bulkApproveBtn" disabled>
                            <i class="fas fa-check mr-1"></i> Approve Selected
                        </button>
                    </div>
                    @if($pendingRequests->hasPages())
                        {{ $pendingRequests->links() }}
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="rounded-lg shadow-xl w-11/12 md:w-1/2 lg:w-1/3 max-w-lg"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="px-6 py-4 border-b flex justify-between items-center"
             style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-times-circle mr-2" style="color: #ef4444;"></i>
                Reject Request
            </h3>
            <button type="button" onclick="closeRejectModal()" class="p-1 rounded-full transition-colors duration-200"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <form id="rejectForm" method="POST">
            @csrf
            <div class="p-6">
                <p class="text-sm mb-4" style="color: var(--text-primary);">
                    Please provide a reason for rejecting this request:
                </p>
                <textarea name="rejection_reason" id="rejectionReason" rows="4" required
                          class="w-full px-4 py-3 rounded-lg focus:ring-2 transition-colors duration-200"
                          style="background-color: var(--bg-secondary);
                                 color: var(--text-primary);
                                 border: 1px solid var(--border-color);
                                 outline: none;"
                          placeholder="Enter rejection reason..."></textarea>
                <input type="hidden" name="request_id" id="rejectRequestId">
            </div>
            <div class="px-6 py-4 border-t flex justify-end space-x-3"
                 style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                <button type="button" onclick="closeRejectModal()"
                        class="px-4 py-2 text-sm font-medium transition-colors duration-200 rounded-lg"
                        style="color: var(--text-secondary);">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                        style="background-color: #ef4444; color: white;">
                    <i class="fas fa-times mr-2"></i> Reject
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('styles')
<style>
    .btn-primary {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        background-color: var(--primary);
        color: white;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-primary:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
        text-decoration: none;
    }
    .btn-secondary {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-secondary:hover {
        background-color: var(--bg-secondary);
        opacity: 0.8;
        text-decoration: none;
        color: var(--text-primary);
    }
    .btn-info {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        background-color: var(--info);
        color: white;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-info:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
        text-decoration: none;
    }
    .card {
        background-color: var(--card-bg);
        border-radius: 0.75rem;
        border: 1px solid var(--border-color);
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }
</style>
@endpush

@push('scripts')
<script>
    // ✅ Route templates — placeholders replaced at runtime
    const approveRouteBase = "{{ route('landlord.waste.request.approve', ['wasteCollectionRequest' => '__ID__']) }}";
    const rejectRouteBase  = "{{ route('landlord.waste.request.reject',  ['wasteCollectionRequest' => '__ID__']) }}";
    const bulkApproveUrl   = "{{ route('landlord.waste.bulk.approve') }}";

    document.addEventListener('DOMContentLoaded', function () {

        // --- Select All / per-row checkbox sync ---
        const selectAll = document.getElementById('selectAll');
        const rowCheckboxes = () => document.querySelectorAll('.request-checkbox');

        selectAll?.addEventListener('change', function () {
            rowCheckboxes().forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
        });

        rowCheckboxes().forEach(cb => {
            cb.addEventListener('change', function () {
                // Sync "Select All" state
                const all = rowCheckboxes();
                const checked = document.querySelectorAll('.request-checkbox:checked');
                if (selectAll) {
                    selectAll.checked = all.length > 0 && checked.length === all.length;
                    selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
                }
                updateSelectedCount();
            });
        });

        // Close reject modal on Escape
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeRejectModal();
        });

        // Close reject modal when clicking outside the inner card
        const rejectModal = document.getElementById('rejectModal');
        rejectModal?.addEventListener('click', function (event) {
            if (event.target === this) closeRejectModal();
        });
    });

    function updateSelectedCount() {
        const selected = document.querySelectorAll('.request-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = selected;
        document.getElementById('bulkApproveBtn').disabled = selected === 0;
    }

    // --- Approve single request ---
    function approveRequest(id) {
        if (!confirm('Are you sure you want to approve this request?')) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = approveRouteBase.replace('__ID__', id);
        form.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}">`;
        document.body.appendChild(form);
        form.submit();
    }

    // --- Reject modal ---
    function showRejectModal(id) {
        document.getElementById('rejectRequestId').value = id;
        document.getElementById('rejectForm').action = rejectRouteBase.replace('__ID__', id);
        document.getElementById('rejectModal').classList.remove('hidden');
    }

    function closeRejectModal() {
        const modal = document.getElementById('rejectModal');
        const textarea = document.getElementById('rejectionReason');
        if (modal) modal.classList.add('hidden');
        if (textarea) textarea.value = '';
    }

    // --- Bulk approve ---
    // ✅ FIX: send `request_ids[]` as repeated fields, not a comma-joined string.
    function bulkApprove() {
        const selected = Array.from(document.querySelectorAll('.request-checkbox:checked'));
        if (selected.length === 0) return;

        if (!confirm(`Are you sure you want to approve ${selected.length} request(s)?`)) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = bulkApproveUrl;

        // CSRF
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = "{{ csrf_token() }}";
        form.appendChild(csrf);

        // One hidden input per ID → Laravel parses request_ids as array
        selected.forEach(cb => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'request_ids[]';
            inp.value = cb.value;
            form.appendChild(inp);
        });

        document.body.appendChild(form);
        form.submit();
    }
</script>
@endpush