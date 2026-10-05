{{-- admin/family-links/index.blade.php --}}
@php
    use App\Models\PropertyFamilyLink;
    use Illuminate\Support\Facades\Storage;

    $isAdmin = auth()->user()->isAdmin();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    $layout = 'layouts.app';

    $pageTitle = 'Family Link Proposals';

    $successMessage = session('success');
    $errorMessage = session('error');

    // ── Stats from the controller (two-stage aware) ──
    $pendingAdminReview      = $stats['pending_admin_review']          ?? 0;
    $pendingLandlordConfirm  = $stats['pending_landlord_confirmation'] ?? 0;
    $pendingLegacy           = $stats['pending_legacy']                ?? 0;
    $pendingCount            = $stats['pending']                       ?? 0; // admin-actionable + legacy
    $approvedCount           = $stats['approved']                      ?? 0;
    $rejectedCount           = $stats['rejected']                      ?? 0;
    $revokedCount            = $stats['revoked']                       ?? 0;
    $cancelledCount          = $stats['cancelled']                     ?? 0;
    $approvedToday           = $stats['approved_today']                ?? 0;
    $totalCount              = $pendingAdminReview
                             + $pendingLandlordConfirm
                             + $pendingLegacy
                             + $approvedCount
                             + $rejectedCount
                             + $revokedCount
                             + $cancelledCount;

    $currentStatus = request('status');
    $relationshipOptions = config('property_family_links.relationship_options', []);

    // ── Status presentation config (used in both the table and filter dropdown) ──
    $statusConfigs = [
        PropertyFamilyLink::STATUS_PENDING_ADMIN => [
            'label'   => 'Awaiting Admin Review',
            'short'   => 'Awaiting Admin',
            'class'   => 'badge-warning',
            'icon'    => 'hourglass-half',
            'color'   => 'var(--warning)',
        ],
        PropertyFamilyLink::STATUS_PENDING_LANDLORD => [
            'label'   => 'Awaiting Landlord Confirmation',
            'short'   => 'Awaiting Landlord',
            'class'   => 'badge-primary',
            'icon'    => 'hand-pointer',
            'color'   => 'var(--primary)',
        ],
        PropertyFamilyLink::STATUS_PENDING => [
            'label'   => 'Pending',
            'short'   => 'Pending',
            'class'   => 'badge-warning',
            'icon'    => 'clock',
            'color'   => 'var(--warning)',
        ],
        PropertyFamilyLink::STATUS_APPROVED => [
            'label'   => 'Approved',
            'short'   => 'Approved',
            'class'   => 'badge-success',
            'icon'    => 'check-circle',
            'color'   => 'var(--success)',
        ],
        PropertyFamilyLink::STATUS_REJECTED => [
            'label'   => 'Rejected',
            'short'   => 'Rejected',
            'class'   => 'badge-danger',
            'icon'    => 'times-circle',
            'color'   => 'var(--danger)',
        ],
        PropertyFamilyLink::STATUS_REVOKED => [
            'label'   => 'Revoked',
            'short'   => 'Revoked',
            'class'   => 'badge-secondary',
            'icon'    => 'ban',
            'color'   => 'var(--secondary)',
        ],
        PropertyFamilyLink::STATUS_CANCELLED => [
            'label'   => 'Cancelled',
            'short'   => 'Cancelled',
            'class'   => 'badge-secondary',
            'icon'    => 'times',
            'color'   => 'var(--secondary)',
        ],
    ];
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ============================================================
         HEADER CARD
         ============================================================ --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-4">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                        Family Link Proposals

                        @if($pendingAdminReview > 0)
                            <span class="px-3 py-1 text-xs font-semibold rounded-full"
                                  style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                <i class="fas fa-hourglass-half mr-1"></i> {{ $pendingAdminReview }} Awaiting Review
                            </span>
                        @endif

                        @if($pendingLandlordConfirm > 0)
                            <span class="px-3 py-1 text-xs font-semibold rounded-full"
                                  style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                <i class="fas fa-hand-pointer mr-1"></i> {{ $pendingLandlordConfirm }} With Landlord
                            </span>
                        @endif
                    </h2>
                    <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Review family members proposed by landlords to be linked to their properties</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-chart-bar mr-1"></i>
                        <span>{{ number_format($totalCount) }} total</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                </div>
                <a href="{{ route('admin.dashboard') }}"
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-dashboard mr-1"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    {{-- ============================================================
         NOTIFICATIONS
         ============================================================ --}}
    <div id="notificationContainer"></div>

    @if($successMessage)
    <div class="success-message" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3); border-radius: 0.5rem; padding: 1rem;">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                <span class="font-medium" style="color: var(--success);">{{ $successMessage }}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    @if($errorMessage)
    <div class="error-message" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3); border-radius: 0.5rem; padding: 1rem;">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i>
                <span class="font-medium" style="color: var(--danger);">{{ $errorMessage }}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    {{-- ============================================================
         STAT CARDS — two-stage aware
         ============================================================ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
        {{-- Awaiting Admin Review (actionable) --}}
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Awaiting Review</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($pendingAdminReview) }}</p>
                        @if($pendingLegacy > 0)
                            <p class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                +{{ $pendingLegacy }} legacy
                            </p>
                        @endif
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-hourglass-half text-lg" style="color: var(--warning);"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Awaiting Landlord (informational) --}}
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">With Landlord</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($pendingLandlordConfirm) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-hand-pointer text-lg" style="color: var(--primary);"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Approved --}}
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Approved</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($approvedCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-check-circle text-lg" style="color: var(--success);"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Rejected --}}
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Rejected</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($rejectedCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-times-circle text-lg" style="color: var(--danger);"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Revoked --}}
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Revoked</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($revokedCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--secondary-rgb), 0.1);">
                        <i class="fas fa-ban text-lg" style="color: var(--secondary);"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Approved Today --}}
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Approved Today</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($approvedToday) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-calendar-check text-lg" style="color: var(--info);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         FILTERS + TABLE
         ============================================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

        {{-- Filters Sidebar --}}
        <div class="lg:col-span-1">
            <div class="card mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filters
                    </h3>

                    <form method="GET" action="{{ route('admin.family-links.index') }}" class="space-y-4" id="filterForm">
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-search mr-1"></i> Search
                            </label>
                            <div class="relative">
                                <input type="text" name="search" value="{{ request('search') }}"
                                       class="index-custom-input w-full pl-10 pr-3 py-2"
                                       placeholder="Name, phone, email..."
                                       style="padding-left: 2.5rem;">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-search" style="color: var(--text-secondary);"></i>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-tag mr-1"></i> Status
                            </label>
                            <select name="status" class="index-custom-dropdown w-full">
                                <option value="">All Status</option>
                                <option value="{{ PropertyFamilyLink::STATUS_PENDING_ADMIN }}"
                                    {{ request('status') == PropertyFamilyLink::STATUS_PENDING_ADMIN ? 'selected' : '' }}>
                                    Awaiting Admin Review
                                </option>
                                <option value="{{ PropertyFamilyLink::STATUS_PENDING_LANDLORD }}"
                                    {{ request('status') == PropertyFamilyLink::STATUS_PENDING_LANDLORD ? 'selected' : '' }}>
                                    Awaiting Landlord Confirmation
                                </option>
                                <option value="{{ PropertyFamilyLink::STATUS_PENDING }}"
                                    {{ request('status') == PropertyFamilyLink::STATUS_PENDING ? 'selected' : '' }}>
                                    Pending (Legacy)
                                </option>
                                <option value="{{ PropertyFamilyLink::STATUS_APPROVED }}"
                                    {{ request('status') == PropertyFamilyLink::STATUS_APPROVED ? 'selected' : '' }}>
                                    Approved
                                </option>
                                <option value="{{ PropertyFamilyLink::STATUS_REJECTED }}"
                                    {{ request('status') == PropertyFamilyLink::STATUS_REJECTED ? 'selected' : '' }}>
                                    Rejected
                                </option>
                                <option value="{{ PropertyFamilyLink::STATUS_REVOKED }}"
                                    {{ request('status') == PropertyFamilyLink::STATUS_REVOKED ? 'selected' : '' }}>
                                    Revoked
                                </option>
                                <option value="{{ PropertyFamilyLink::STATUS_CANCELLED }}"
                                    {{ request('status') == PropertyFamilyLink::STATUS_CANCELLED ? 'selected' : '' }}>
                                    Cancelled
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-heart mr-1"></i> Relationship
                            </label>
                            <select name="relationship" class="index-custom-dropdown w-full">
                                <option value="">All Relationships</option>
                                @foreach($relationshipOptions as $rel)
                                    <option value="{{ $rel }}" {{ request('relationship') == $rel ? 'selected' : '' }}>
                                        {{ ucfirst($rel) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <div class="space-y-2">
                                <button type="submit" class="block w-full text-center btn-primary px-3 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-filter mr-2"></i> Apply Filters
                                </button>
                                <a href="{{ route('admin.family-links.index') }}"
                                   class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-redo mr-2"></i> Reset Filters
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Status Distribution --}}
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i> Status Distribution
                    </h3>
                    <div class="space-y-3">
                        @php
                            $dist = [
                                ['label' => 'Awaiting Admin',     'count' => $pendingAdminReview,     'color' => 'var(--warning)'],
                                ['label' => 'Awaiting Landlord',  'count' => $pendingLandlordConfirm, 'color' => 'var(--primary)'],
                                ['label' => 'Approved',           'count' => $approvedCount,          'color' => 'var(--success)'],
                                ['label' => 'Rejected',           'count' => $rejectedCount,          'color' => 'var(--danger)'],
                                ['label' => 'Revoked',            'count' => $revokedCount,           'color' => 'var(--secondary)'],
                                ['label' => 'Cancelled',          'count' => $cancelledCount,         'color' => 'var(--secondary)'],
                            ];
                        @endphp
                        @foreach($dist as $d)
                            @php $pct = $totalCount > 0 ? round(($d['count'] / $totalCount) * 100, 1) : 0; @endphp
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 rounded-full mr-2" style="background-color: {{ $d['color'] }};"></span>
                                    <span class="text-sm" style="color: var(--text-primary);">{{ $d['label'] }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $d['count'] }}</span>
                                    <span class="text-xs ml-1" style="color: var(--text-secondary);">({{ $pct }}%)</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Table --}}
        <div class="lg:col-span-3">
            <div class="card p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Proposals
                            @if($currentStatus)
                                <span class="text-sm font-normal ml-2 px-2 py-1 rounded-full badge-primary">
                                    {{ $statusConfigs[$currentStatus]['label'] ?? ucfirst(str_replace('_', ' ', $currentStatus)) }}
                                </span>
                            @endif
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Showing {{ $links->firstItem() }} to {{ $links->lastItem() }} of {{ $links->total() }} entries
                        </p>
                    </div>

                    <div class="flex items-center space-x-3 mt-4 md:mt-0">
                        @php
                            // Selectable = has at least one admin-reviewable link
                            $hasSelectable = $links->filter(function ($l) {
                                return $l->isAwaitingAdminReview()
                                    || $l->status === \App\Models\PropertyFamilyLink::STATUS_PENDING;
                            })->count() > 0;
                        @endphp
                        @if($hasSelectable)
                        <div class="relative">
                            <button type="button" id="bulkActionsBtn"
                                    class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-check-double mr-2"></i> Bulk Actions
                                <i class="fas fa-chevron-down ml-2 text-xs"></i>
                            </button>
                            <div id="bulkActionsDropdown"
                                 class="absolute right-0 mt-2 w-64 rounded-lg shadow-lg z-10 hidden"
                                 style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                                <div class="py-1">
                                    <button type="button" onclick="showBulkApproveModal()"
                                            class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-check-circle text-green-500 mr-2"></i> Bulk Approve
                                    </button>
                                    <button type="button" onclick="showBulkRejectModal()"
                                            class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-times-circle text-red-500 mr-2"></i> Bulk Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="flex items-center space-x-2">
                            <span class="text-sm" style="color: var(--text-secondary);">Show:</span>
                            <select onchange="updatePerPage(this.value)" class="index-custom-dropdown text-sm py-1 px-2 rounded">
                                <option value="10" {{ request('per_page', 20) == 10 ? 'selected' : '' }}>10</option>
                                <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                            </select>
                        </div>
                    </div>
                </div>

                @if($links->isEmpty())
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                             style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-users text-2xl" style="color: var(--primary);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No family link proposals found</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            @if(request('status'))
                                Try clearing your filters.
                            @else
                                There are no proposals awaiting admin review right now.
                            @endif
                        </p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1100px]" id="linksTable">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); width: 40px;">
                                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll()"
                                               class="rounded" style="width: 18px; height: 18px;">
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 200px;">
                                        Proposed Member
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 180px;">
                                        Property / Landlord
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 140px;">
                                        Relationship
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 180px;">
                                        Status
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 140px;">
                                        Submitted
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 180px;">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="linksTableBody">
                                @foreach($links as $link)
                                @php
                                    // Only admin-reviewable links can be bulk-selected.
                                    $canBulk = $link->isAwaitingAdminReview()
                                            || $link->status === PropertyFamilyLink::STATUS_PENDING;

                                    $sc = $statusConfigs[$link->status]
                                        ?? ['class' => 'badge-secondary', 'icon' => 'question-circle', 'label' => ucfirst($link->status)];
                                @endphp
                                <tr data-link-id="{{ $link->id }}"
                                    data-status="{{ $link->status }}"
                                    data-proposed-name="{{ addslashes($link->proposed_name) }}"
                                    data-property-name="{{ addslashes($link->property->property_name ?? 'N/A') }}"
                                    data-can-bulk="{{ $canBulk ? 'true' : 'false' }}">

                                    <td class="p-3 text-center align-top">
                                        <input type="checkbox" class="link-checkbox" value="{{ $link->id }}"
                                               data-can-bulk="{{ $canBulk ? 'true' : 'false' }}"
                                               {{ !$canBulk ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' }}
                                               onclick="updateBulkActions()">
                                    </td>

                                    {{-- Proposed Member --}}
                                    <td class="p-3 align-top">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 mr-2 mt-0.5">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                                                    <i class="fas fa-user text-xs" style="color: var(--primary);"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-semibold text-sm" style="color: var(--text-primary);">
                                                    {{ $link->proposed_name }}
                                                </div>
                                                @if($link->proposed_phone)
                                                <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                    <i class="fas fa-phone mr-1"></i> {{ $link->proposed_phone }}
                                                </div>
                                                @endif
                                                @if($link->proposed_email)
                                                <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                    <i class="fas fa-envelope mr-1"></i> {{ $link->proposed_email }}
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Property + Landlord --}}
                                    <td class="p-3 align-top">
                                        <div class="text-sm font-medium" style="color: var(--text-primary);">
                                            <i class="fas fa-building mr-1" style="color: var(--secondary);"></i>
                                            {{ $link->property->property_name ?? 'N/A' }}
                                        </div>
                                        @if($link->landlord)
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-user-tie mr-1"></i> {{ $link->landlord->name }}
                                        </div>
                                        @endif
                                    </td>

                                    {{-- Relationship --}}
                                    <td class="p-3 align-top">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                              style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                            <i class="fas fa-heart mr-1"></i> {{ $link->relationship_label }}
                                        </span>
                                    </td>

                                    {{-- Status --}}
                                    <td class="p-3 align-top">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $sc['class'] }}">
                                            <i class="fas fa-{{ $sc['icon'] }} mr-1 text-xs"></i> {{ $sc['label'] }}
                                        </span>

                                        @if($link->isAwaitingLandlordConfirmation())
                                            <div class="text-xs mt-1" style="color: var(--primary);">
                                                <i class="fas fa-info-circle mr-1"></i>Not yet confirmed by landlord
                                            </div>
                                        @elseif($link->isAwaitingAdminReview())
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                Ready for your review
                                            </div>
                                        @endif

                                        @if($link->admin_notes && $link->isRejected())
                                            <div class="text-xs mt-1" style="color: var(--danger);">
                                                <i class="fas fa-comment mr-0.5"></i> {{ Str::limit($link->admin_notes, 40) }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Submitted --}}
                                    <td class="p-3 align-top">
                                        <div class="text-sm" style="color: var(--text-primary);">
                                            {{ $link->created_at->format('M j, Y') }}
                                        </div>
                                        <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                            <i class="fas fa-clock mr-1"></i> {{ $link->created_at->diffForHumans() }}
                                        </div>
                                        @if($link->reviewed_at)
                                        <div class="text-xs mt-0.5" style="color: var(--success);">
                                            Reviewed: {{ $link->reviewed_at->diffForHumans() }}
                                        </div>
                                        @endif
                                    </td>

                                    {{-- Actions --}}
                                    <td class="p-3 align-top">
                                        <div class="flex flex-wrap items-center gap-1">
                                            <a href="{{ route('admin.family-links.show', $link->id) }}"
                                               class="action-btn view" data-tooltip="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            @if($link->isAwaitingAdminReview() || $link->status === PropertyFamilyLink::STATUS_PENDING)
                                                <button type="button"
                                                        onclick="showApproveModal('{{ $link->id }}', '{{ addslashes($link->proposed_name) }}')"
                                                        class="action-btn assign" data-tooltip="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button"
                                                        onclick="showRejectModal('{{ $link->id }}', '{{ addslashes($link->proposed_name) }}')"
                                                        class="action-btn delete" data-tooltip="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>

                                            @elseif($link->isAwaitingLandlordConfirmation())
                                                <span class="text-xs px-2 py-1 rounded" style="color: var(--text-secondary);"
                                                      title="Waiting on the landlord to confirm their own proposal">
                                                    <i class="fas fa-hand-pointer mr-1" style="color: var(--primary);"></i>
                                                    Awaiting landlord
                                                </span>

                                            @elseif($link->isApproved())
                                                <button type="button"
                                                        onclick="showRevokeModal('{{ $link->id }}', '{{ addslashes($link->proposed_name) }}')"
                                                        class="action-btn delete" data-tooltip="Revoke Access">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col md:flex-row justify-between items-center pt-6 mt-6 border-t"
                         style="border-color: var(--border-color);">
                        <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                            Showing {{ $links->firstItem() }} to {{ $links->lastItem() }} of {{ $links->total() }} entries
                        </div>
                        <div class="pagination">
                            {{ $links->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     MODALS
     ============================================================ --}}

{{-- Approve Modal --}}
<div id="approveModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideApproveModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Approve Family Link
                </h3>
                <button type="button" onclick="hideApproveModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="approveForm" method="POST" action="">
                @csrf
                <input type="hidden" name="decision" value="approve">
                <div class="modal-body">
                    <p class="text-sm mb-4" style="color: var(--text-primary);">
                        Approve family link for: <strong id="approveMemberName"></strong>
                    </p>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Admin Notes (Optional)
                        </label>
                        <textarea name="admin_notes" rows="3" class="index-custom-textarea w-full"
                                  placeholder="Add notes about this approval..."></textarea>
                    </div>
                    <div class="rounded-lg p-4"
                         style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                This will grant the family member access to the property.
                                <strong>The family member will receive an invitation to set up their account.</strong>
                                The landlord will be notified.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideApproveModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit"
                            class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-check mr-2"></i> Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Reject Modal --}}
<div id="rejectModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideRejectModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Reject Family Link
                </h3>
                <button type="button" onclick="hideRejectModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="rejectForm" method="POST" action="">
                @csrf
                <input type="hidden" name="decision" value="reject">
                <div class="modal-body">
                    <p class="text-sm mb-4" style="color: var(--text-primary);">
                        Reject family link for: <strong id="rejectMemberName"></strong>
                    </p>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Rejection <span class="text-red-500">*</span>
                        </label>
                        <textarea name="admin_notes" rows="4" class="index-custom-textarea w-full"
                                  placeholder="Please provide a reason..." required></textarea>
                    </div>
                    <div class="rounded-lg p-4"
                         style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                The landlord will be notified. The family member is <strong>not contacted</strong>.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideRejectModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit"
                            class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-times mr-2"></i> Reject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Revoke Modal --}}
<div id="revokeModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideRevokeModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-ban mr-2" style="color: var(--danger);"></i> Revoke Family Link
                </h3>
                <button type="button" onclick="hideRevokeModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="revokeForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <p class="text-sm mb-4" style="color: var(--text-primary);">
                        Revoke access for: <strong id="revokeMemberName"></strong>
                    </p>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Revocation
                        </label>
                        <textarea name="reason" rows="3" class="index-custom-textarea w-full"
                                  placeholder="Reason for revoking access..."></textarea>
                    </div>
                    <div class="rounded-lg p-4"
                         style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                The family member will immediately lose access to the property.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideRevokeModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit"
                            class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-ban mr-2"></i> Revoke Access
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Bulk Approve Modal --}}
<div id="bulkApproveModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideBulkApproveModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Bulk Approve Proposals
                </h3>
                <button type="button" onclick="hideBulkApproveModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="bulkApproveForm" method="POST" action="{{ route('admin.family-links.bulk-review') }}">
                @csrf
                <input type="hidden" name="decision" value="approve">
                <div class="modal-body">
                    <p class="text-sm mb-2" style="color: var(--text-primary);">Selected proposals:</p>
                    <div id="bulkApproveList" class="max-h-40 overflow-y-auto space-y-1 p-2 rounded mb-4"
                         style="background-color: var(--bg-secondary);"></div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Admin Notes (Optional)
                        </label>
                        <textarea name="admin_notes" rows="3" class="index-custom-textarea w-full"
                                  placeholder="Notes for all approvals..."></textarea>
                    </div>
                    <div class="rounded-lg p-4"
                         style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <p class="text-sm" style="color: var(--text-secondary);">
                            All selected proposals will be approved. Each family member will receive an account-setup invitation, and their landlords will be notified.
                            Proposals still awaiting landlord confirmation will be skipped.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="bulkApproveIdsContainer"></div>
                    <button type="button" onclick="hideBulkApproveModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit"
                            class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-check mr-2"></i> Approve Selected
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Bulk Reject Modal --}}
<div id="bulkRejectModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideBulkRejectModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Bulk Reject Proposals
                </h3>
                <button type="button" onclick="hideBulkRejectModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="bulkRejectForm" method="POST" action="{{ route('admin.family-links.bulk-review') }}">
                @csrf
                <input type="hidden" name="decision" value="reject">
                <div class="modal-body">
                    <p class="text-sm mb-2" style="color: var(--text-primary);">Selected proposals:</p>
                    <div id="bulkRejectList" class="max-h-40 overflow-y-auto space-y-1 p-2 rounded mb-4"
                         style="background-color: var(--bg-secondary);"></div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Rejection <span class="text-red-500">*</span>
                        </label>
                        <textarea name="admin_notes" rows="3" class="index-custom-textarea w-full"
                                  placeholder="Please provide a reason..." required></textarea>
                    </div>
                    <div class="rounded-lg p-4"
                         style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <p class="text-sm" style="color: var(--text-secondary);">
                            All selected proposals will be rejected. Landlords will be notified; family members will not be contacted.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="bulkRejectIdsContainer"></div>
                    <button type="button" onclick="hideBulkRejectModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit"
                            class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-times mr-2"></i> Reject Selected
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
let selectedLinkIds = [];

document.addEventListener('DOMContentLoaded', function() {
    autoHideMessages();
    initTooltips();

    const bulkActionsBtn = document.getElementById('bulkActionsBtn');
    const bulkActionsDropdown = document.getElementById('bulkActionsDropdown');
    if (bulkActionsBtn) {
        bulkActionsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            bulkActionsDropdown.classList.toggle('hidden');
        });
    }
    document.addEventListener('click', function(event) {
        if (bulkActionsDropdown && !bulkActionsDropdown.classList.contains('hidden')) {
            if (!bulkActionsBtn.contains(event.target) && !bulkActionsDropdown.contains(event.target)) {
                bulkActionsDropdown.classList.add('hidden');
            }
        }
    });

    // Wire up all forms
    bindForm('approveForm',      data => submitSingle('approve', data));
    bindForm('rejectForm',       data => submitSingle('reject',  data));
    bindForm('revokeForm',       data => submitSingle('revoke',  data));
    bindForm('bulkApproveForm',  data => submitBulk('approve',   data));
    bindForm('bulkRejectForm',   data => submitBulk('reject',    data));
});

function bindForm(id, handler) {
    const form = document.getElementById(id);
    if (!form) return;
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        handler(form);
    });
}

function updatePerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    document.querySelectorAll('.link-checkbox').forEach(cb => {
        if (!cb.disabled) cb.checked = selectAll.checked;
    });
    updateBulkActions();
}

function updateBulkActions() {
    selectedLinkIds = Array.from(document.querySelectorAll('.link-checkbox:checked'))
        .filter(cb => !cb.disabled && cb.getAttribute('data-can-bulk') === 'true')
        .map(cb => cb.value);

    const btn = document.getElementById('bulkActionsBtn');
    if (btn) {
        btn.innerHTML = selectedLinkIds.length > 0
            ? `<i class="fas fa-check-double mr-2"></i> ${selectedLinkIds.length} Selected <i class="fas fa-chevron-down ml-2 text-xs"></i>`
            : `<i class="fas fa-check-double mr-2"></i> Bulk Actions <i class="fas fa-chevron-down ml-2 text-xs"></i>`;
    }
}

// ── Single modals ────────────────────────────────────────
function showApproveModal(id, name) {
    document.getElementById('approveForm').action = `/admin/family-links/${id}/review`;
    document.getElementById('approveMemberName').textContent = name;
    document.getElementById('approveModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function hideApproveModal() {
    document.getElementById('approveModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showRejectModal(id, name) {
    document.getElementById('rejectForm').action = `/admin/family-links/${id}/review`;
    document.getElementById('rejectMemberName').textContent = name;
    document.getElementById('rejectModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function hideRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showRevokeModal(id, name) {
    document.getElementById('revokeForm').action = `/admin/family-links/${id}/revoke`;
    document.getElementById('revokeMemberName').textContent = name;
    document.getElementById('revokeModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function hideRevokeModal() {
    document.getElementById('revokeModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// ── Bulk modals ──────────────────────────────────────────
function showBulkApproveModal() {
    if (selectedLinkIds.length === 0) return showNotification('error', 'Select at least one proposal awaiting review.');
    buildBulkList('bulkApproveList', 'bulkApproveIdsContainer');
    document.getElementById('bulkApproveModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function hideBulkApproveModal() {
    document.getElementById('bulkApproveModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showBulkRejectModal() {
    if (selectedLinkIds.length === 0) return showNotification('error', 'Select at least one proposal awaiting review.');
    buildBulkList('bulkRejectList', 'bulkRejectIdsContainer');
    document.getElementById('bulkRejectModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function hideBulkRejectModal() {
    document.getElementById('bulkRejectModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function buildBulkList(listId, containerId) {
    const list = document.getElementById(listId);
    const container = document.getElementById(containerId);
    list.innerHTML = '';
    container.innerHTML = '';

    selectedLinkIds.forEach(id => {
        const row = document.querySelector(`tr[data-link-id="${id}"]`);
        if (!row) return;
        const name = row.getAttribute('data-proposed-name') || 'Unknown';
        const property = row.getAttribute('data-property-name') || 'Unknown';

        const div = document.createElement('div');
        div.className = 'text-sm py-1';
        div.innerHTML = `<i class="fas fa-user mr-2 text-gray-400"></i> ${escapeHtml(name)}
                         <span class="text-xs ml-1" style="color: var(--text-secondary);">— ${escapeHtml(property)}</span>`;
        list.appendChild(div);

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'link_ids[]';
        input.value = id;
        container.appendChild(input);
    });
}

// ── Form submission ──────────────────────────────────────
function submitSingle(action, form) {
    const btn = form.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    btn.disabled = true;

    fetch(form.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new FormData(form)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message || 'Action completed.');
            if (action === 'approve') hideApproveModal();
            if (action === 'reject')  hideRejectModal();
            if (action === 'revoke')  hideRevokeModal();
            setTimeout(() => window.location.reload(), 1200);
        } else {
            showNotification('error', data.message || 'Error.');
            btn.innerHTML = original;
            btn.disabled = false;
        }
    })
    .catch(() => {
        showNotification('error', 'Request failed.');
        btn.innerHTML = original;
        btn.disabled = false;
    });
}

function submitBulk(action, form) {
    const btn = form.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    btn.disabled = true;

    fetch(form.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new FormData(form)
    })
    .then(r => r.json())
    .then(data => {
        showNotification(data.success ? 'success' : 'error', data.message || 'Processed.');
        if (action === 'approve') hideBulkApproveModal();
        if (action === 'reject')  hideBulkRejectModal();
        setTimeout(() => window.location.reload(), 1500);
    })
    .catch(() => {
        showNotification('error', 'Bulk request failed.');
        btn.innerHTML = original;
        btn.disabled = false;
    });
}

// ── Helpers ─────────────────────────────────────────────
function escapeHtml(t) {
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}

function showNotification(type, message) {
    const c = document.getElementById('notificationContainer');
    if (!c) return;
    c.innerHTML = '';
    const n = document.createElement('div');
    n.className = 'mb-4 p-4 rounded-lg shadow-lg';
    n.style.backgroundColor = type === 'success' ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)';
    n.style.border = type === 'success' ? '1px solid rgba(var(--success-rgb), 0.3)' : '1px solid rgba(var(--danger-rgb), 0.3)';
    n.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"
                   style="color: ${type === 'success' ? 'var(--success)' : 'var(--danger)'};"></i>
                <span style="color: ${type === 'success' ? 'var(--success)' : 'var(--danger)'};">${escapeHtml(message)}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>`;
    c.appendChild(n);
    setTimeout(() => n.remove(), 5000);
}

function autoHideMessages() {
    setTimeout(() => {
        document.querySelectorAll('.success-message, .error-message').forEach(m => m.style.display = 'none');
    }, 5000);
}

function initTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(el => {
        el.addEventListener('mouseenter', function () {
            const tip = document.createElement('div');
            tip.className = 'tooltip';
            tip.textContent = this.getAttribute('data-tooltip');
            tip.style.cssText = `
                position: absolute; background: var(--text-primary); color: var(--card-bg);
                padding: 4px 8px; border-radius: 4px; font-size: 12px; z-index: 1000; white-space: nowrap;`;
            document.body.appendChild(tip);
            const r = this.getBoundingClientRect();
            tip.style.left = r.left + (r.width / 2) - (tip.offsetWidth / 2) + 'px';
            tip.style.top = r.top - tip.offsetHeight - 5 + 'px';
            this._tip = tip;
        });
        el.addEventListener('mouseleave', function () {
            if (this._tip) { this._tip.remove(); this._tip = null; }
        });
    });
}
</script>

<style>
.index-custom-input,
.index-custom-dropdown,
.index-custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
}
.index-custom-input:focus,
.index-custom-dropdown:focus,
.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.action-btn {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    cursor: pointer;
}
.action-btn.view {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border-color: rgba(var(--info-rgb), 0.3);
}
.action-btn.assign {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border-color: rgba(var(--success-rgb), 0.3);
}
.action-btn.delete {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border-color: rgba(var(--danger-rgb), 0.3);
}
.action-btn:hover { transform: translateY(-1px); }

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
    position: sticky;
    top: 0;
    background: var(--card-bg);
}
.modal-body { padding: 1.5rem; }
.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    position: sticky;
    bottom: 0;
    background: var(--card-bg);
}
.modal-close-btn {
    background: none; border: none; cursor: pointer;
    font-size: 1.25rem; padding: 0.5rem; border-radius: 50%;
}

.badge-success   { background-color: rgba(var(--success-rgb), 0.1) !important; color: var(--success) !important; border: 1px solid rgba(var(--success-rgb), 0.3) !important; }
.badge-warning   { background-color: rgba(var(--warning-rgb), 0.1) !important; color: var(--warning) !important; border: 1px solid rgba(var(--warning-rgb), 0.3) !important; }
.badge-danger    { background-color: rgba(var(--danger-rgb), 0.1) !important;  color: var(--danger) !important;  border: 1px solid rgba(var(--danger-rgb), 0.3) !important; }
.badge-info      { background-color: rgba(var(--info-rgb), 0.1) !important;    color: var(--info) !important;    border: 1px solid rgba(var(--info-rgb), 0.3) !important; }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; }
.badge-primary   { background-color: rgba(var(--primary-rgb), 0.1) !important; color: var(--primary) !important; border: 1px solid rgba(var(--primary-rgb), 0.3) !important; }

.btn-primary   { background-color: var(--primary) !important; color: white !important; border: 1px solid var(--primary) !important; }
.btn-primary:hover { background-color: var(--secondary) !important; border-color: var(--secondary) !important; }
.btn-secondary { background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; }
.btn-danger    { background-color: var(--danger) !important; color: white !important; border: 1px solid var(--danger) !important; }
.btn-danger:hover { background-color: #c82333 !important; }

table { border-collapse: separate; border-spacing: 0; width: 100%; }
table th {
    font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
    font-size: 0.75rem; padding: 0.75rem;
    border-bottom: 2px solid var(--border-color);
    background-color: var(--bg-secondary) !important;
}
table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: top;
    background-color: var(--card-bg) !important;
}

.link-checkbox, #selectAll {
    width: 18px; height: 18px; cursor: pointer;
}
.link-checkbox:disabled { cursor: not-allowed; opacity: 0.5; }
button:disabled { opacity: 0.6; cursor: not-allowed; }
.tooltip { pointer-events: none; }

@media (max-width: 768px) {
    .action-btn { padding: 0.25rem 0.5rem; }
    .modal-container { margin: 1rem; }
}
</style>
@endpush
@endsection