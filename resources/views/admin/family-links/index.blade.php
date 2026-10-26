{{-- admin/family-links/index.blade.php --}}
@php
    use App\Models\PropertyFamilyLink;

    $layout = 'layouts.app';

    $pageTitle = 'Family Link Proposals';

    $successMessage = session('success');
    $errorMessage   = session('error');

    // ── Stats from the controller (two-stage aware) ──
    $pendingAdminReview     = $stats['pending_admin_review']          ?? 0;
    $pendingLandlordConfirm = $stats['pending_landlord_confirmation'] ?? 0;
    $pendingLegacy          = $stats['pending_legacy']                ?? 0;
    $pendingCount           = $stats['pending']                       ?? 0;
    $approvedCount          = $stats['approved']                      ?? 0;
    $rejectedCount          = $stats['rejected']                      ?? 0;
    $revokedCount           = $stats['revoked']                       ?? 0;
    $cancelledCount         = $stats['cancelled']                     ?? 0;
    $approvedToday          = $stats['approved_today']                ?? 0;

    $totalCount = $stats['total']
        ?? ($pendingAdminReview + $pendingLandlordConfirm + $pendingLegacy
            + $approvedCount + $rejectedCount + $revokedCount + $cancelledCount);

    $currentStatus       = request('status');
    $currentPerPage      = (int) request('per_page', 20);
    $relationshipOptions = config('property_family_links.relationship_options', []);

    // ── Status presentation config ──
    $statusConfigs = [
        PropertyFamilyLink::STATUS_PENDING_ADMIN => [
            'label' => 'Awaiting Admin Review',
            'short' => 'Awaiting Admin',
            'class' => 'badge-warning',
            'icon'  => 'hourglass-half',
            'color' => 'var(--warning)',
        ],
        PropertyFamilyLink::STATUS_PENDING_LANDLORD => [
            'label' => 'Awaiting Landlord Confirmation',
            'short' => 'Awaiting Landlord',
            'class' => 'badge-primary',
            'icon'  => 'hand-pointer',
            'color' => 'var(--primary)',
        ],
        PropertyFamilyLink::STATUS_PENDING => [
            'label' => 'Pending',
            'short' => 'Pending',
            'class' => 'badge-warning',
            'icon'  => 'clock',
            'color' => 'var(--warning)',
        ],
        PropertyFamilyLink::STATUS_APPROVED => [
            'label' => 'Approved',
            'short' => 'Approved',
            'class' => 'badge-success',
            'icon'  => 'check-circle',
            'color' => 'var(--success)',
        ],
        PropertyFamilyLink::STATUS_REJECTED => [
            'label' => 'Rejected',
            'short' => 'Rejected',
            'class' => 'badge-danger',
            'icon'  => 'times-circle',
            'color' => 'var(--danger)',
        ],
        PropertyFamilyLink::STATUS_REVOKED => [
            'label' => 'Revoked',
            'short' => 'Revoked',
            'class' => 'badge-secondary',
            'icon'  => 'ban',
            'color' => 'var(--secondary)',
        ],
        PropertyFamilyLink::STATUS_CANCELLED => [
            'label' => 'Cancelled',
            'short' => 'Cancelled',
            'class' => 'badge-secondary',
            'icon'  => 'times',
            'color' => 'var(--secondary)',
        ],
    ];

    // ── Quick filter chips shown above the table ──
    $quickFilters = [
        ['value' => '',                                          'label' => 'All Pending',    'count' => $pendingCount,           'icon' => 'layer-group'],
        ['value' => PropertyFamilyLink::STATUS_PENDING_ADMIN,    'label' => 'Awaiting Admin', 'count' => $pendingAdminReview,     'icon' => 'hourglass-half'],
        ['value' => PropertyFamilyLink::STATUS_PENDING_LANDLORD, 'label' => 'With Landlord',  'count' => $pendingLandlordConfirm, 'icon' => 'hand-pointer'],
        ['value' => PropertyFamilyLink::STATUS_REJECTED,         'label' => 'Rejected',       'count' => $rejectedCount,          'icon' => 'times-circle'],
    ];

    // Preset rejection / revocation reasons for the modals.
    $rejectPresets = [
        'Insufficient documentation provided.',
        'Relationship could not be verified.',
        'Duplicate of an existing proposal.',
        'Property ownership could not be confirmed.',
        'Suspected fraudulent or inaccurate information.',
        'Contact details are unreachable or invalid.',
    ];
    $revokePresets = [
        'Link no longer required by the landlord.',
        'Relationship status changed.',
        'Suspicious activity detected.',
        'Property sold or transferred.',
    ];
    $bulkRejectPresets = [
        'Insufficient documentation provided.',
        'Relationship could not be verified.',
        'Duplicate proposals.',
        'Suspected fraudulent or inaccurate information.',
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
                        <input type="hidden" name="per_page" value="{{ $currentPerPage }}">

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-search mr-1"></i> Search
                            </label>
                            <div class="relative">
                                <input type="text" name="search" value="{{ request('search') }}"
                                       class="index-custom-input w-full"
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
                                <option value="">All Statuses</option>
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
                                @foreach($relationshipOptions as $key => $label)
                                    @php
                                        $value = is_int($key) ? $label : $key;
                                        $text  = is_int($key) ? ucfirst($label) : $label;
                                    @endphp
                                    <option value="{{ $value }}" {{ request('relationship') == $value ? 'selected' : '' }}>
                                        {{ $text }}
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
                                ['label' => 'Awaiting Admin',    'count' => $pendingAdminReview,     'color' => 'var(--warning)'],
                                ['label' => 'Awaiting Landlord', 'count' => $pendingLandlordConfirm, 'color' => 'var(--primary)'],
                                ['label' => 'Approved',          'count' => $approvedCount,          'color' => 'var(--success)'],
                                ['label' => 'Rejected',          'count' => $rejectedCount,          'color' => 'var(--danger)'],
                                ['label' => 'Revoked',           'count' => $revokedCount,           'color' => 'var(--secondary)'],
                                ['label' => 'Cancelled',         'count' => $cancelledCount,         'color' => 'var(--secondary)'],
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

                {{-- Stalled-proposals banner --}}
                @if($pendingLandlordConfirm > 0 && !request('status'))
                <div class="mb-6 p-4 rounded-lg flex items-start flex-wrap gap-3"
                     style="background-color: rgba(var(--primary-rgb), 0.08); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-hand-pointer mr-1 mt-0.5" style="color: var(--primary);"></i>
                    <div class="flex-1 min-w-[200px]">
                        <p class="text-sm font-medium" style="color: var(--text-primary);">
                            {{ $pendingLandlordConfirm }}
                            {{ \Illuminate\Support\Str::plural('proposal', $pendingLandlordConfirm) }}
                            {{ $pendingLandlordConfirm === 1 ? 'is' : 'are' }}
                            still awaiting landlord confirmation.
                        </p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            These won't be actionable until the landlord confirms them — but you can review the details now.
                        </p>
                    </div>
                    <a href="{{ route('admin.family-links.index', ['status' => PropertyFamilyLink::STATUS_PENDING_LANDLORD]) }}"
                       class="text-xs font-medium px-3 py-1.5 rounded-lg whitespace-nowrap"
                       style="background-color: var(--primary); color: white;">
                        View them
                    </a>
                </div>
                @endif

                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Proposals
                            @if($currentStatus)
                                <span class="text-sm font-normal ml-2 px-2 py-1 rounded-full badge-primary">
                                    {{ data_get($statusConfigs, "$currentStatus.label", ucfirst(str_replace('_', ' ', $currentStatus))) }}
                                </span>
                            @endif
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            @if($links->total() > 0)
                                Showing {{ $links->firstItem() }} to {{ $links->lastItem() }} of {{ $links->total() }} entries
                            @else
                                No entries to display
                            @endif
                        </p>
                    </div>

                    <div class="flex items-center space-x-3 mt-4 md:mt-0">
                        @php
                            $hasSelectable = $links->filter(function ($l) {
                                return $l->isAwaitingAdminReview()
                                    || $l->status === PropertyFamilyLink::STATUS_PENDING;
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
                                @foreach([10, 20, 25, 50, 100] as $pp)
                                    <option value="{{ $pp }}" {{ $currentPerPage === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Quick filter chips --}}
                @unless(request('status'))
                <div class="flex flex-wrap items-center gap-2 mb-5">
                    @foreach($quickFilters as $chip)
                        @php
                            // "All Pending" chip is active when there's no status filter.
                            $isActive = $chip['value'] === '';
                            $url = $chip['value'] === ''
                                ? route('admin.family-links.index', request()->except(['status', 'page']))
                                : route('admin.family-links.index', array_merge(
                                    request()->except(['status', 'page']),
                                    ['status' => $chip['value']]
                                ));
                        @endphp
                        <a href="{{ $url }}"
                           class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium transition"
                           style="
                                {{ $isActive
                                    ? 'background-color: var(--primary); color: white; border: 1px solid var(--primary);'
                                    : 'background-color: rgba(var(--primary-rgb), 0.08); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);' }}">
                            <i class="fas fa-{{ $chip['icon'] }} mr-1.5"></i>
                            {{ $chip['label'] }}
                            <span class="ml-2 px-1.5 py-0.5 rounded-full text-[10px]"
                                  style="background-color: rgba(255,255,255,0.25);">
                                {{ number_format($chip['count']) }}
                            </span>
                        </a>
                    @endforeach
                </div>
                @endunless

                @if($links->isEmpty())
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                             style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-users text-2xl" style="color: var(--primary);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No family link proposals found</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            @if(request('status') || request('search') || request('relationship'))
                                No proposals match the current filters. Try resetting them.
                            @else
                                There are no family link proposals awaiting review right now.
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
                                    $canBulk = $link->isAwaitingAdminReview()
                                            || $link->status === PropertyFamilyLink::STATUS_PENDING;

                                    $sc = $statusConfigs[$link->status]
                                        ?? ['class' => 'badge-secondary', 'icon' => 'question-circle', 'label' => ucfirst($link->status)];

                                    $reasonText = $link->admin_notes ?? null;
                                    $isRejected = $link->status === PropertyFamilyLink::STATUS_REJECTED;
                                    $isRevoked  = $link->status === PropertyFamilyLink::STATUS_REVOKED;
                                    $hasReason  = in_array($link->status, [
                                        PropertyFamilyLink::STATUS_REJECTED,
                                        PropertyFamilyLink::STATUS_REVOKED,
                                    ], true) && !empty($reasonText);
                                    $reasonId   = "reason-{$link->id}";
                                @endphp
                                <tr data-link-id="{{ $link->id }}"
                                    data-status="{{ $link->status }}"
                                    data-proposed-name="{{ $link->proposed_name }}"
                                    data-property-name="{{ $link->property->property_name ?? 'N/A' }}"
                                    data-admin-notes="{{ $link->admin_notes }}"
                                    data-can-bulk="{{ $canBulk ? 'true' : 'false' }}"
                                    data-has-email="{{ !empty(trim((string) $link->proposed_email)) ? 'true' : 'false' }}"
                                    data-has-phone="{{ !empty(trim((string) $link->proposed_phone)) ? 'true' : 'false' }}"
                                    data-invitable-email="{{ $link->proposed_email }}"
                                    data-invitable-phone="{{ $link->proposed_phone }}">

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

                                        @if($hasReason)
                                            <div class="text-xs mt-1.5">
                                                <button type="button"
                                                        onclick="toggleReason('{{ $reasonId }}')"
                                                        class="inline-flex items-center font-medium"
                                                        style="color: {{ $isRejected ? 'var(--danger)' : 'var(--secondary)' }}; background: none; border: none; cursor: pointer; padding: 0;">
                                                    <i class="fas fa-comment mr-1"></i>
                                                    <span id="{{ $reasonId }}-short">
                                                        {{ Str::limit($reasonText, 40) }}
                                                    </span>
                                                    <i class="fas fa-chevron-down ml-1" id="{{ $reasonId }}-icon" style="transition: transform .15s ease;"></i>
                                                </button>
                                                <div id="{{ $reasonId }}-full"
                                                     class="hidden mt-1 p-2 rounded text-xs"
                                                     style="background-color: rgba(var(--danger-rgb), 0.06); border: 1px solid rgba(var(--danger-rgb), 0.2); color: var(--text-primary); max-width: 320px;">
                                                    <div class="font-medium mb-0.5"
                                                         style="color: {{ $isRejected ? 'var(--danger)' : 'var(--secondary)' }};">
                                                        <i class="fas fa-comment-dots mr-1"></i>
                                                        {{ $isRejected ? 'Rejection reason' : 'Revocation reason' }}
                                                    </div>
                                                    <div style="white-space: pre-wrap;">{{ $reasonText }}</div>
                                                    @if($link->reviewer)
                                                        <div class="mt-1" style="color: var(--text-secondary);">
                                                            — {{ $link->reviewer->name ?? 'Admin' }},
                                                            {{ optional($link->reviewed_at)->diffForHumans() ?? 'recently' }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Submitted --}}
                                    <td class="p-3 align-top">
                                        <div class="text-sm" style="color: var(--text-primary);">
                                            {{ optional($link->created_at)->format('M j, Y') ?? '—' }}
                                        </div>
                                        <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                            <i class="fas fa-clock mr-1"></i> {{ optional($link->created_at)->diffForHumans() ?? '—' }}
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
                                                        onclick="showApproveModal({{ $link->id }})"
                                                        class="action-btn assign" data-tooltip="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button"
                                                        onclick="showRejectModal({{ $link->id }})"
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
                                                        onclick="showRevokeModal({{ $link->id }})"
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

                    @if($links->total() > 0)
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
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     MODALS
     ============================================================ --}}

{{-- Approve Modal — with delivery channel selection --}}
<div id="approveModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideApproveModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-lg">
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

                    {{-- Delivery channels --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-paper-plane mr-1"></i> Delivery Channels
                        </label>
                        <p class="text-xs mb-2" style="color: var(--text-secondary);">
                            Choose how the invitation is sent. Only channels the landlord provided are selectable.
                        </p>

                        <div id="approveChannelList" class="space-y-2">
                            {{-- Email --}}
                            <label id="approveChannelEmailWrap"
                                   class="flex items-center p-3 rounded-lg cursor-pointer transition"
                                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <input type="checkbox"
                                       name="channels[]"
                                       value="email"
                                       id="approveChannelEmail"
                                       class="mr-3 w-4 h-4"
                                       style="accent-color: var(--primary);">
                                <div class="flex-1">
                                    <div class="text-sm font-medium" style="color: var(--text-primary);">
                                        <i class="fas fa-envelope mr-1" style="color: var(--info);"></i>
                                        Email
                                    </div>
                                    <div class="text-xs mt-0.5"
                                         id="approveChannelEmailValue"
                                         style="color: var(--text-secondary);">
                                        —
                                    </div>
                                </div>
                                <span id="approveChannelEmailUnavailable"
                                      class="text-xs px-2 py-0.5 rounded-full hidden"
                                      style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                    Not provided
                                </span>
                            </label>

                            {{-- SMS --}}
                            <label id="approveChannelSmsWrap"
                                   class="flex items-center p-3 rounded-lg cursor-pointer transition"
                                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <input type="checkbox"
                                       name="channels[]"
                                       value="sms"
                                       id="approveChannelSms"
                                       class="mr-3 w-4 h-4"
                                       style="accent-color: var(--primary);">
                                <div class="flex-1">
                                    <div class="text-sm font-medium" style="color: var(--text-primary);">
                                        <i class="fas fa-sms mr-1" style="color: var(--success);"></i>
                                        SMS
                                    </div>
                                    <div class="text-xs mt-0.5"
                                         id="approveChannelSmsValue"
                                         style="color: var(--text-secondary);">
                                        —
                                    </div>
                                </div>
                                <span id="approveChannelSmsUnavailable"
                                      class="text-xs px-2 py-0.5 rounded-full hidden"
                                      style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                    Not provided
                                </span>
                            </label>

                            {{-- WhatsApp --}}
                            <label id="approveChannelWhatsappWrap"
                                   class="flex items-center p-3 rounded-lg cursor-pointer transition"
                                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <input type="checkbox"
                                       name="channels[]"
                                       value="whatsapp"
                                       id="approveChannelWhatsapp"
                                       class="mr-3 w-4 h-4"
                                       style="accent-color: var(--primary);">
                                <div class="flex-1">
                                    <div class="text-sm font-medium" style="color: var(--text-primary);">
                                        <i class="fab fa-whatsapp mr-1" style="color: #25D366;"></i>
                                        WhatsApp
                                    </div>
                                    <div class="text-xs mt-0.5"
                                         id="approveChannelWhatsappValue"
                                         style="color: var(--text-secondary);">
                                        —
                                    </div>
                                </div>
                                <span id="approveChannelWhatsappUnavailable"
                                      class="text-xs px-2 py-0.5 rounded-full hidden"
                                      style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                    Not provided
                                </span>
                            </label>
                        </div>

                        {{-- Warning when nothing is selectable --}}
                        <div id="approveChannelEmptyWarning"
                             class="mt-2 p-2 rounded hidden text-xs"
                             style="background-color: rgba(var(--danger-rgb), 0.08); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.25);">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            No contact method was provided by the landlord. Cannot send an invitation.
                        </div>
                    </div>

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
                                <strong>The family member will receive an invitation on the selected channels.</strong>
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
                        <i class="fas fa-check mr-2"></i> Approve & Send Invitation
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

                    <div class="mb-3">
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-secondary);">
                            Common reasons (click to use)
                        </label>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($rejectPresets as $preset)
                                <button type="button"
                                        class="text-xs px-2 py-1 rounded-full preset-reason"
                                        data-reason="{{ $preset }}"
                                        data-target="rejectReasonInput"
                                        style="background-color: rgba(var(--danger-rgb), 0.08); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.25); cursor: pointer;">
                                    {{ $preset }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Rejection <span class="text-red-500">*</span>
                        </label>
                        <textarea name="admin_notes" id="rejectReasonInput" rows="4"
                                  class="index-custom-textarea w-full"
                                  placeholder="Please provide a reason..." required></textarea>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            This is shared with the landlord so they understand the decision.
                        </div>
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

                    <div class="mb-3">
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-secondary);">
                            Common reasons (click to use)
                        </label>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($revokePresets as $preset)
                                <button type="button"
                                        class="text-xs px-2 py-1 rounded-full preset-reason"
                                        data-reason="{{ $preset }}"
                                        data-target="revokeReasonInput"
                                        style="background-color: rgba(var(--danger-rgb), 0.08); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.25); cursor: pointer;">
                                    {{ $preset }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Revocation
                        </label>
                        <textarea name="reason" id="revokeReasonInput" rows="3"
                                  class="index-custom-textarea w-full"
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

                    {{-- Bulk channel selection --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-paper-plane mr-1"></i> Delivery Channels
                        </label>
                        <p class="text-xs mb-2" style="color: var(--text-secondary);">
                            Applies to every selected proposal. Channels the recipient doesn't have will be skipped.
                        </p>
                        <div class="space-y-2">
                            <label class="flex items-center p-2 rounded-lg cursor-pointer"
                                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <input type="checkbox" name="channels[]" value="email"
                                       class="mr-3 w-4 h-4" style="accent-color: var(--primary);" checked>
                                <span class="text-sm" style="color: var(--text-primary);">
                                    <i class="fas fa-envelope mr-1" style="color: var(--info);"></i> Email
                                </span>
                            </label>
                            <label class="flex items-center p-2 rounded-lg cursor-pointer"
                                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <input type="checkbox" name="channels[]" value="sms"
                                       class="mr-3 w-4 h-4" style="accent-color: var(--primary);" checked>
                                <span class="text-sm" style="color: var(--text-primary);">
                                    <i class="fas fa-sms mr-1" style="color: var(--success);"></i> SMS
                                </span>
                            </label>
                            <label class="flex items-center p-2 rounded-lg cursor-pointer"
                                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <input type="checkbox" name="channels[]" value="whatsapp"
                                       class="mr-3 w-4 h-4" style="accent-color: var(--primary);">
                                <span class="text-sm" style="color: var(--text-primary);">
                                    <i class="fab fa-whatsapp mr-1" style="color: #25D366;"></i> WhatsApp
                                </span>
                            </label>
                        </div>
                    </div>

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
                            All selected proposals will be approved. Each family member will receive an account-setup invitation on the selected channels, and their landlords will be notified.
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

                    <div class="mb-3">
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-secondary);">
                            Common reasons (click to use)
                        </label>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($bulkRejectPresets as $preset)
                                <button type="button"
                                        class="text-xs px-2 py-1 rounded-full preset-reason"
                                        data-reason="{{ $preset }}"
                                        data-target="bulkRejectReasonInput"
                                        style="background-color: rgba(var(--danger-rgb), 0.08); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.25); cursor: pointer;">
                                    {{ $preset }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Rejection <span class="text-red-500">*</span>
                        </label>
                        <textarea name="admin_notes" id="bulkRejectReasonInput" rows="3"
                                  class="index-custom-textarea w-full"
                                  placeholder="Please provide a reason..." required></textarea>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            This reason will be attached to every selected proposal.
                        </div>
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

    const bulkActionsBtn      = document.getElementById('bulkActionsBtn');
    const bulkActionsDropdown = document.getElementById('bulkActionsDropdown');
    if (bulkActionsBtn && bulkActionsDropdown) {
        bulkActionsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            bulkActionsDropdown.classList.toggle('hidden');
        });
        document.addEventListener('click', function(event) {
            if (!bulkActionsDropdown.classList.contains('hidden')) {
                if (!bulkActionsBtn.contains(event.target) && !bulkActionsDropdown.contains(event.target)) {
                    bulkActionsDropdown.classList.add('hidden');
                }
            }
        });
    }

    // Preset-reason chips — respect data-target if present, otherwise default to rejectReasonInput.
    document.querySelectorAll('.preset-reason').forEach(btn => {
        btn.addEventListener('click', function () {
            const reason = this.getAttribute('data-reason') || '';
            const targetId = this.getAttribute('data-target');
            const input = targetId
                ? document.getElementById(targetId)
                : document.getElementById('rejectReasonInput');

            if (input) {
                input.value = input.value ? (input.value.trimEnd() + ' ' + reason) : reason;
                input.focus();
                input.setSelectionRange(input.value.length, input.value.length);
            }
        });
    });

    bindForm('approveForm',     form => submitSingle('approve', form));
    bindForm('rejectForm',      form => submitSingle('reject',  form));
    bindForm('revokeForm',      form => submitSingle('revoke',  form));
    bindForm('bulkApproveForm', form => submitBulk('approve',   form));
    bindForm('bulkRejectForm',  form => submitBulk('reject',    form));
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

    updateSelectAllState();
}

function updateSelectAllState() {
    const master = document.getElementById('selectAll');
    if (!master) return;
    const enabled = Array.from(document.querySelectorAll('.link-checkbox')).filter(cb => !cb.disabled);
    const checked = enabled.filter(cb => cb.checked);
    master.checked       = enabled.length > 0 && checked.length === enabled.length;
    master.indeterminate = checked.length > 0 && checked.length < enabled.length;
}

function rowName(id) {
    const row = document.querySelector(`tr[data-link-id="${id}"]`);
    return row?.getAttribute('data-proposed-name') || 'this member';
}

function toggleReason(id) {
    const full  = document.getElementById(`${id}-full`);
    const icon  = document.getElementById(`${id}-icon`);
    const short = document.getElementById(`${id}-short`);
    if (!full) return;

    const isHidden = full.classList.contains('hidden');
    full.classList.toggle('hidden', !isHidden);
    if (short) short.classList.toggle('hidden', isHidden);
    if (icon)  icon.style.transform = isHidden ? 'rotate(180deg)' : '';
}

// ── Approve modal — with channel selection prefill ──
function showApproveModal(id) {
    const row = document.querySelector(`tr[data-link-id="${id}"]`);
    if (!row) return;

    const form = document.getElementById('approveForm');
    form.action = `/admin/family-links/${id}/review`;

    // Clear notes
    const notes = form.querySelector('textarea[name="admin_notes"]');
    if (notes) notes.value = '';

    // Read landlord-provided contact info
    const hasEmail   = row.getAttribute('data-has-email') === 'true';
    const hasPhone   = row.getAttribute('data-has-phone') === 'true';
    const emailValue = row.getAttribute('data-invitable-email') || '';
    const phoneValue = row.getAttribute('data-invitable-phone') || '';

    // ── Email channel ──
    const emailCb       = document.getElementById('approveChannelEmail');
    const emailWrap     = document.getElementById('approveChannelEmailWrap');
    const emailValueEl  = document.getElementById('approveChannelEmailValue');
    const emailMissing  = document.getElementById('approveChannelEmailUnavailable');

    emailValueEl.textContent = hasEmail ? emailValue : 'Not provided by landlord';
    if (hasEmail) {
        emailCb.disabled = false;
        emailCb.checked  = true;
        emailWrap.style.opacity = '1';
        emailWrap.style.cursor  = 'pointer';
        emailMissing.classList.add('hidden');
    } else {
        emailCb.disabled = true;
        emailCb.checked  = false;
        emailWrap.style.opacity = '0.55';
        emailWrap.style.cursor  = 'not-allowed';
        emailMissing.classList.remove('hidden');
    }

    // ── SMS channel ──
    const smsCb      = document.getElementById('approveChannelSms');
    const smsWrap    = document.getElementById('approveChannelSmsWrap');
    const smsValueEl = document.getElementById('approveChannelSmsValue');
    const smsMissing = document.getElementById('approveChannelSmsUnavailable');

    smsValueEl.textContent = hasPhone ? phoneValue : 'Not provided by landlord';
    if (hasPhone) {
        smsCb.disabled = false;
        smsCb.checked  = true;
        smsWrap.style.opacity = '1';
        smsWrap.style.cursor  = 'pointer';
        smsMissing.classList.add('hidden');
    } else {
        smsCb.disabled = true;
        smsCb.checked  = false;
        smsWrap.style.opacity = '0.55';
        smsWrap.style.cursor  = 'not-allowed';
        smsMissing.classList.remove('hidden');
    }

    // ── WhatsApp channel (needs phone, default OFF — opt-in) ──
    const waCb      = document.getElementById('approveChannelWhatsapp');
    const waWrap    = document.getElementById('approveChannelWhatsappWrap');
    const waValueEl = document.getElementById('approveChannelWhatsappValue');
    const waMissing = document.getElementById('approveChannelWhatsappUnavailable');

    waValueEl.textContent = hasPhone ? phoneValue : 'Not provided by landlord';
    if (hasPhone) {
        waCb.disabled = false;
        waCb.checked  = false; // opt-in
        waWrap.style.opacity = '1';
        waWrap.style.cursor  = 'pointer';
        waMissing.classList.add('hidden');
    } else {
        waCb.disabled = true;
        waCb.checked  = false;
        waWrap.style.opacity = '0.55';
        waWrap.style.cursor  = 'not-allowed';
        waMissing.classList.remove('hidden');
    }

    // Empty warning
    const warn = document.getElementById('approveChannelEmptyWarning');
    warn.classList.toggle('hidden', hasEmail || hasPhone);

    document.getElementById('approveMemberName').textContent = rowName(id);
    document.getElementById('approveModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideApproveModal() {
    document.getElementById('approveModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showRejectModal(id) {
    const form = document.getElementById('rejectForm');
    form.action = `/admin/family-links/${id}/review`;
    document.getElementById('rejectReasonInput').value = '';
    document.getElementById('rejectMemberName').textContent = rowName(id);
    document.getElementById('rejectModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('rejectReasonInput').focus(), 100);
}
function hideRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showRevokeModal(id) {
    const form = document.getElementById('revokeForm');
    form.action = `/admin/family-links/${id}/revoke`;
    document.getElementById('revokeReasonInput').value = '';
    document.getElementById('revokeMemberName').textContent = rowName(id);
    document.getElementById('revokeModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function hideRevokeModal() {
    document.getElementById('revokeModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showBulkApproveModal() {
    if (selectedLinkIds.length === 0) {
        return showNotification('error', 'Select at least one proposal awaiting review.');
    }
    buildBulkList('bulkApproveList', 'bulkApproveIdsContainer');
    document.getElementById('bulkApproveModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function hideBulkApproveModal() {
    document.getElementById('bulkApproveModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showBulkRejectModal() {
    if (selectedLinkIds.length === 0) {
        return showNotification('error', 'Select at least one proposal awaiting review.');
    }
    buildBulkList('bulkRejectList', 'bulkRejectIdsContainer');
    document.getElementById('bulkRejectReasonInput').value = '';
    document.getElementById('bulkRejectModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('bulkRejectReasonInput').focus(), 100);
}
function hideBulkRejectModal() {
    document.getElementById('bulkRejectModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function buildBulkList(listId, containerId) {
    const list      = document.getElementById(listId);
    const container = document.getElementById(containerId);
    list.innerHTML      = '';
    container.innerHTML = '';

    selectedLinkIds.forEach(id => {
        const row = document.querySelector(`tr[data-link-id="${id}"]`);
        if (!row) return;
        const name     = row.getAttribute('data-proposed-name') || 'Unknown';
        const property = row.getAttribute('data-property-name') || 'Unknown';

        const div = document.createElement('div');
        div.className = 'text-sm py-1';
        div.innerHTML = `<i class="fas fa-user mr-2 text-gray-400"></i> ${escapeHtml(name)}
                         <span class="text-xs ml-1" style="color: var(--text-secondary);">— ${escapeHtml(property)}</span>`;
        list.appendChild(div);

        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = 'link_ids[]';
        input.value = id;
        container.appendChild(input);
    });
}

// ── Submit handlers ──
function submitSingle(action, form) {
    if (action === 'approve') {
        const selectedChannels = Array.from(
            form.querySelectorAll('input[name="channels[]"]:checked')
        );
        if (selectedChannels.length === 0) {
            showNotification('error', 'Select at least one delivery channel for the invitation.');
            return;
        }
    }

    if (action === 'reject') {
        const reason = form.querySelector('textarea[name="admin_notes"]')?.value.trim();
        if (!reason) {
            showNotification('error', 'Please provide a rejection reason.');
            form.querySelector('textarea[name="admin_notes"]')?.focus();
            return;
        }
    }

    const btn      = form.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.innerHTML  = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    btn.disabled   = true;

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
            btn.disabled  = false;
        }
    })
    .catch(() => {
        showNotification('error', 'Request failed.');
        btn.innerHTML = original;
        btn.disabled  = false;
    });
}

function submitBulk(action, form) {
    if (action === 'approve') {
        const selectedChannels = Array.from(
            form.querySelectorAll('input[name="channels[]"]:checked')
        );
        if (selectedChannels.length === 0) {
            showNotification('error', 'Select at least one delivery channel for the bulk approval.');
            return;
        }
    }

    if (action === 'reject') {
        const reason = form.querySelector('textarea[name="admin_notes"]')?.value.trim();
        if (!reason) {
            showNotification('error', 'Please provide a rejection reason for all selected proposals.');
            form.querySelector('textarea[name="admin_notes"]')?.focus();
            return;
        }
    }

    const btn      = form.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.innerHTML  = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    btn.disabled   = true;

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
        btn.disabled  = false;
    });
}

// ── Helpers ──
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
            tip.style.top  = r.top - tip.offsetHeight - 5 + 'px';
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

.preset-reason:hover {
    background-color: rgba(var(--danger-rgb), 0.18) !important;
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