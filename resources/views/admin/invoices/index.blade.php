{{-- resources/views/admin/invoices/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Invoice Management')

@php
    use Carbon\Carbon;

    // ── Normalize controller data ──
    $totalInvoices        = isset($totalInvoices)        && is_numeric($totalInvoices)        ? (int) $totalInvoices        : 0;
    $paidInvoices         = isset($paidInvoices)         && is_numeric($paidInvoices)         ? (int) $paidInvoices         : 0;
    $pendingInvoices      = isset($pendingInvoices)      && is_numeric($pendingInvoices)      ? (int) $pendingInvoices      : 0;
    $overdueInvoices      = isset($overdueInvoices)      && is_numeric($overdueInvoices)      ? (int) $overdueInvoices      : 0;
    $consolidatedInvoices = isset($consolidatedInvoices) && is_numeric($consolidatedInvoices) ? (int) $consolidatedInvoices : 0;
    $activeCoverages      = isset($activeCoverages)      && is_numeric($activeCoverages)      ? (int) $activeCoverages      : 0;
    $totalDue             = isset($totalDue)             && is_numeric($totalDue)             ? (float) $totalDue           : 0;
    $totalRevenue         = isset($totalRevenue)         && is_numeric($totalRevenue)         ? (float) $totalRevenue       : 0;
    $totalPenalties       = isset($totalPenalties)       && is_numeric($totalPenalties)       ? (float) $totalPenalties     : 0;
    $collectionRate       = isset($collectionRate)       && is_numeric($collectionRate)       ? (float) $collectionRate     : 0;

    $properties  = isset($properties) && $properties instanceof \Illuminate\Support\Collection
        ? $properties
        : collect();

    // ── Normalize $invoices into a paginator, whatever the controller passed ──
    if (isset($invoices) && $invoices instanceof \Illuminate\Pagination\LengthAwarePaginator) {
        // Already a paginator — use it as-is.
    } elseif (isset($invoices) && $invoices instanceof \Illuminate\Database\Eloquent\Builder) {
        $invoices = $invoices->paginate(15)->appends(request()->query());
    } elseif (isset($invoices) && $invoices instanceof \Illuminate\Support\Collection) {
        $page = (int) request('page', 1);
        $invoices = new \Illuminate\Pagination\LengthAwarePaginator(
            $invoices->forPage($page, 15)->values(),
            $invoices->count(),
            15,
            $page,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
        );
    } else {
        $invoices = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
    }

    $settings = $settings ?? \App\Models\SystemSetting::getSettings();

    if (!function_exists('safeCount')) {
        function safeCount($items) {
            return (is_array($items) || $items instanceof \Countable) ? count($items) : 0;
        }
    }

    $autoGenerationEnabled = $autoGenerationEnabled ?? false;
    $remindersEnabled      = $remindersEnabled      ?? false;
    $reminderDays          = $reminderDays          ?? 7;
    $gracePeriodDays       = $gracePeriodDays       ?? 7;
    $nextGenerationDate    = $nextGenerationDate    ?? null;

    $trashCount = \Illuminate\Support\Facades\Cache::remember('invoices.trash_count', 60, function () {
        return \App\Models\Invoice::onlyTrashed()->count();
    });

    $grandTotalInvoices = \Illuminate\Support\Facades\Cache::remember('invoices.total_count', 60, function () {
        return \App\Models\Invoice::count();
    });

    $currencySymbol   = $settings->currency_symbol   ?? '₵';
    $decimalPlaces    = (int) ($settings->decimal_places ?? 2);
    $currencyPosition = $settings->currency_position ?? 'left';

    // ── Office payment state — the guard value is used repeatedly below ──
    $officePaymentsAllowed = method_exists($settings, 'isOfflinePaymentAllowed')
        ? $settings->isOfflinePaymentAllowed()
        : true;   // default true if the model hasn't been updated yet

    // ── Active-filter diagnostics ──
    $activeFilterKeys = array_filter(
        ['property_id', 'status', 'type', 'period', 'search', 'has_parent', 'is_bulk', 'has_coverage', 'has_discount', 'has_penalty'],
        fn ($k) => request()->filled($k)
    );
    $hasActiveFilters = !empty($activeFilterKeys);
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ============================================================
         HEADER CARD
    ============================================================ --}}
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6 gap-4">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2 icon-circle-primary">
                        <i class="fas fa-file-invoice text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2 text-primary">
                        <i class="fas fa-file-invoice icon-primary"></i>
                        Landlord Invoice Management
                        @if($autoGenerationEnabled)
                        <span class="pill pill-success">
                            <i class="fas fa-sync mr-1"></i> Auto-Generation On
                        </span>
                        @endif
                        @if(!$officePaymentsAllowed)
                        <span class="pill pill-warning" title="Office payments are disabled — landlords must pay online">
                            <i class="fas fa-ban mr-1"></i> Online-Only Payments
                        </span>
                        @endif
                    </h2>
                    <div class="text-sm flex items-center mt-1 flex-wrap gap-2 text-secondary">
                        <i class="fas fa-info-circle"></i>
                        <span>Manage monthly dues for all properties</span>
                        <span>•</span>
                        <i class="fas fa-circle status-dot status-dot-success"></i>
                        <span class="font-medium">{{ number_format($totalInvoices) }} total invoices</span>
                    </div>
                </div>
            </div>
            <div class="text-sm text-secondary">
                <i class="fas fa-calendar-alt mr-1"></i> {{ Carbon::now()->format('F j, Y') }}
            </div>
        </div>
    </div>

    {{-- ============================================================
         FLASH MESSAGES
    ============================================================ --}}
    @foreach(['success' => 'check-circle', 'error' => 'exclamation-circle', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'] as $type => $icon)
        @if(session($type))
        <div class="card flash-card" data-flash>
            <div class="flex items-center p-4 rounded-lg flash-{{ $type === 'error' ? 'danger' : $type }}">
                <div class="flex-shrink-0">
                    <i class="fas fa-{{ $icon }} text-xl icon-{{ $type === 'error' ? 'danger' : $type }}"></i>
                </div>
                <div class="ml-3 flex-1">
                    <p class="font-medium text-{{ $type === 'error' ? 'danger' : $type }}">{{ session($type) }}</p>
                </div>
                <button type="button" class="ml-auto flash-close" aria-label="Dismiss">
                    <i class="fas fa-times text-secondary"></i>
                </button>
            </div>
        </div>
        @endif
    @endforeach

    {{-- ============================================================
         AUTO-GENERATION DISABLED BANNER
    ============================================================ --}}
    @if(!$autoGenerationEnabled)
    <div class="card">
        <div class="flex items-center p-4 rounded-lg flash-warning">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-xl icon-warning"></i>
            </div>
            <div class="ml-3 flex-1">
                <p class="font-semibold text-warning">Auto-generation Disabled</p>
                <p class="text-xs text-secondary mt-1">
                    Monthly invoice auto-generation is currently disabled. Enable it in System Settings or use manual generation.
                </p>
            </div>
            <a href="{{ route('settings.invoice') }}" class="btn-warning ml-3">
                <i class="fas fa-cog mr-1"></i> Configure
            </a>
        </div>
    </div>
    @endif

    {{-- ============================================================
         OFFICE PAYMENTS DISABLED BANNER (NEW)
    ============================================================ --}}
    @if(!$officePaymentsAllowed)
    <div class="card">
        <div class="flex items-start p-4 rounded-lg flash-info">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-xl icon-info mt-1"></i>
            </div>
            <div class="ml-3 flex-1">
                <p class="font-semibold text-info">Office Payments Disabled</p>
                <p class="text-sm mt-1 text-secondary">
                    Landlords can only pay through the online gateway. The
                    <strong>Mark as Paid</strong> actions are hidden to prevent
                    manual payment recording. Existing paid invoices are unaffected.
                </p>
                <div class="mt-2">
                    <a href="{{ route('admin.system-settings.index') }}" class="link-inline font-medium">
                        <i class="fas fa-cog mr-1"></i> Change in System Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ============================================================
         GENERATION DETAILS (if a generation just ran)
    ============================================================ --}}
    @if(session('generation_details') && safeCount(session('generation_details')) > 0)
    <div class="card">
        <div class="flex items-start p-4 rounded-lg flash-info">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-xl icon-info mt-1"></i>
            </div>
            <div class="ml-3 flex-1">
                <p class="font-semibold text-info">Generation Summary</p>
                <p class="text-sm mt-1 text-secondary">{{ session('success') }}</p>
                <ul class="text-xs mt-2 list-disc list-inside text-secondary">
                    @foreach(session('generation_details') as $key => $value)
                        @if(is_numeric($value) && $value > 0)
                        <li>{{ ucfirst(str_replace('_', ' ', $key)) }}: {{ number_format($value) }}</li>
                        @endif
                    @endforeach
                </ul>
            </div>
            <button type="button" class="ml-auto flash-close" aria-label="Dismiss">
                <i class="fas fa-times text-secondary"></i>
            </button>
        </div>
    </div>
    @endif

    {{-- ============================================================
         NAVIGATION CARD
    ============================================================ --}}
    <div class="card p-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center flex-wrap gap-2">
                <a href="{{ route('invoices.create') }}" class="btn-primary">
                    <i class="fas fa-plus mr-2"></i> Generate Manual Invoice
                </a>

                <form action="{{ route('invoices.generate-monthly') }}" method="POST" class="inline" id="generateMonthlyForm">
                    @csrf
                    <button type="submit" class="btn-secondary" onclick="return confirmGenerateMonthly()">
                        <i class="fas fa-sync mr-2"></i> Generate Monthly
                    </button>
                </form>

                <form action="{{ route('invoices.mark-overdue') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="btn-warning" onclick="return confirm('Mark all pending invoices with past due dates as overdue?')">
                        <i class="fas fa-clock mr-2"></i> Mark Overdue
                    </button>
                </form>

                <a href="{{ route('invoices.index', ['status' => 'consolidated']) }}" class="btn-info">
                    <i class="fas fa-layer-group mr-2"></i> View Consolidated
                </a>

                <a href="{{ route('admin.tenant-invoices.index') }}" class="btn-tenant">
                    <i class="fas fa-users mr-2"></i> Tenant Invoices
                </a>

                @if($trashCount > 0)
                <a href="{{ route('invoices.trash') }}" class="btn-trash">
                    <i class="fas fa-trash-alt mr-2"></i> Trash
                    <span class="pill pill-warning ml-1">{{ $trashCount }}</span>
                </a>
                @endif
            </div>

            <div class="flex items-center flex-wrap gap-3">
                <a href="{{ route('superadmin.billing.dashboard') }}" class="link-inline">
                    <i class="fas fa-credit-card mr-1"></i> Billing
                </a>
                <a href="{{ route('superadmin.billing.reports') }}" class="link-inline">
                    <i class="fas fa-chart-bar mr-1"></i> Reports
                </a>
            </div>
        </div>
    </div>

    {{-- ============================================================
         STATS CARDS — primary row
    ============================================================ --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="card stat-card stat-primary">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1 text-secondary">Total Invoices</p>
                    <p class="text-2xl font-bold text-primary">{{ number_format($totalInvoices) }}</p>
                    <p class="text-xs mt-1 text-secondary">Excludes consolidated</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center icon-circle-primary">
                    <i class="fas fa-file-invoice text-lg"></i>
                </div>
            </div>
        </div>

        <div class="card stat-card stat-success">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1 text-secondary">Paid</p>
                    <p class="text-2xl font-bold text-success">{{ number_format($paidInvoices) }}</p>
                    <p class="text-xs mt-1 text-secondary">Regular paid</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center icon-circle-success">
                    <i class="fas fa-check-circle text-lg"></i>
                </div>
            </div>
        </div>

        <div class="card stat-card stat-warning">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1 text-secondary">Pending</p>
                    <p class="text-2xl font-bold text-warning">{{ number_format($pendingInvoices) }}</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center icon-circle-warning">
                    <i class="fas fa-clock text-lg"></i>
                </div>
            </div>
        </div>

        <div class="card stat-card stat-danger">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1 text-secondary">Overdue</p>
                    <p class="text-2xl font-bold text-danger">{{ number_format($overdueInvoices) }}</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center icon-circle-danger">
                    <i class="fas fa-exclamation-triangle text-lg"></i>
                </div>
            </div>
        </div>

        <div class="card stat-card stat-info">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1 text-secondary">Consolidated</p>
                    <p class="text-2xl font-bold text-info">{{ number_format($consolidatedInvoices) }}</p>
                    <p class="text-xs mt-1 text-secondary">In bulk payments</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center icon-circle-info">
                    <i class="fas fa-layer-group text-lg"></i>
                </div>
            </div>
        </div>

        <div class="card stat-card stat-success">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1 text-secondary">Active Coverage</p>
                    <p class="text-2xl font-bold text-success">{{ number_format($activeCoverages) }}</p>
                    <p class="text-xs mt-1 text-secondary">Bulk coverages</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center icon-circle-success">
                    <i class="fas fa-shield-alt text-lg"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         STATS CARDS — financial row
    ============================================================ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1 text-secondary">Total Due</p>
                    <p class="text-2xl font-bold text-primary">{{ $settings->formatAmount($totalDue) }}</p>
                    <p class="text-xs mt-1 text-secondary">Pending + overdue</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center icon-circle-warning">
                    <i class="fas fa-money-bill-wave text-lg"></i>
                </div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1 text-secondary">Total Revenue</p>
                    <p class="text-2xl font-bold text-success">{{ $settings->formatAmount($totalRevenue) }}</p>
                    <p class="text-xs mt-1 text-secondary">From paid invoices</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center icon-circle-success">
                    <i class="fas fa-chart-line text-lg"></i>
                </div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1 text-secondary">Collection Rate</p>
                    <p class="text-2xl font-bold text-primary">{{ number_format($collectionRate, 2) }}%</p>
                    <p class="text-xs mt-1 text-secondary">Excludes consolidated</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center icon-circle-primary">
                    <i class="fas fa-percent text-lg"></i>
                </div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1 text-secondary">Total Penalties</p>
                    <p class="text-2xl font-bold text-danger">{{ $settings->formatAmount($totalPenalties) }}</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center icon-circle-danger">
                    <i class="fas fa-exclamation-circle text-lg"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         EXPLANATION CARD
    ============================================================ --}}
    <div class="card">
        <div class="flex items-start p-4 rounded-lg flash-info">
            <i class="fas fa-info-circle icon-info text-xl mt-1 mr-3"></i>
            <div class="text-sm text-secondary">
                <strong class="font-semibold text-primary">Understanding Invoice Statistics:</strong>
                <ul class="mt-2 space-y-1">
                    <li>• <strong class="text-success">Paid Invoices</strong> — Regular invoices that have been paid</li>
                    <li>• <strong class="text-info">Consolidated Invoices</strong> — Original invoices covered by a bulk payment</li>
                    <li>• <strong class="text-success">Active Coverage</strong> — Bulk payments covering future months</li>
                    <li>• <strong class="text-primary">Collection Rate</strong> — Based on regular paid invoices only</li>
                </ul>
                <p class="mt-2 text-xs">
                    <i class="fas fa-lightbulb mr-1"></i>
                    When a bulk payment covers existing invoices, those become "Consolidated" and are excluded from "Paid" to prevent double-counting.
                </p>
            </div>
        </div>
    </div>

    {{-- ============================================================
         FILTERS CARD
    ============================================================ --}}
    <div class="card p-6">
        <form method="GET" action="{{ route('invoices.index') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <select name="property_id" class="form-select">
                    <option value="">All Properties</option>
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}" @selected(request('property_id') == $property->id)>
                            {{ $property->house_number }} {{ $property->street_name }}
                            ({{ optional($property->landlord)->name }})
                        </option>
                    @endforeach
                </select>

                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    @foreach(['paid','pending','overdue','partial','consolidated','cancelled'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>

                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    <option value="regular" @selected(request('type') === 'regular')>Regular Monthly</option>
                    <option value="bulk"    @selected(request('type') === 'bulk')>Bulk Payment</option>
                    <option value="child"   @selected(request('type') === 'child')>Consolidated Child</option>
                </select>

                <input type="month" name="period" class="form-input" placeholder="Period" value="{{ request('period') }}">

                <input type="text" name="search" class="form-input" placeholder="Search invoices…" value="{{ request('search') }}">

                <div class="flex gap-2">
                    <button type="submit" class="btn-primary flex-1">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                    <a href="{{ route('invoices.index') }}" class="btn-warning flex-1" title="Clear every filter">
                        <i class="fas fa-times mr-2"></i> Clear
                    </a>
                </div>
            </div>

            <div class="mt-4">
                <button type="button" onclick="toggleAdvancedFilters()" class="link-inline">
                    <i class="fas fa-chevron-down mr-1" id="advancedFilterIcon"></i> Advanced Filters
                </button>
            </div>

            <div id="advancedFilters" class="hidden mt-4 grid grid-cols-1 md:grid-cols-5 gap-3">
                @foreach([
                    'has_parent'   => ['Has Parent Bulk', ['yes' => 'Yes (Consolidated)', 'no' => 'No']],
                    'is_bulk'      => ['Is Bulk Payment', ['yes' => 'Yes', 'no' => 'No']],
                    'has_coverage' => ['Has Active Coverage', ['yes' => 'Yes', 'no' => 'No']],
                    'has_discount' => ['Has Discount', ['yes' => 'Yes', 'no' => 'No']],
                    'has_penalty'  => ['Has Penalty', ['yes' => 'Yes', 'no' => 'No']],
                ] as $field => [$label, $options])
                <div>
                    <label class="block text-sm mb-1 text-secondary">{{ $label }}</label>
                    <select name="{{ $field }}" class="form-select">
                        <option value="">All</option>
                        @foreach($options as $value => $text)
                            <option value="{{ $value }}" @selected(request($field) === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
                @endforeach
            </div>
        </form>
    </div>

    {{-- ============================================================
         SYSTEM STATUS + BULK OPERATIONS
    ============================================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-medium text-primary">System Status</h3>
                    <p class="text-sm text-secondary">Invoice generation and reminder settings</p>
                </div>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-secondary">Auto-generation</span>
                    <span class="pill {{ $autoGenerationEnabled ? 'pill-success' : 'pill-warning' }}">
                        <i class="fas fa-circle status-dot {{ $autoGenerationEnabled ? 'status-dot-success' : 'status-dot-warning' }}"></i>
                        {{ $autoGenerationEnabled ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-secondary">Reminders</span>
                    <span class="pill {{ $remindersEnabled ? 'pill-success' : 'pill-warning' }}">
                        <i class="fas fa-circle status-dot {{ $remindersEnabled ? 'status-dot-success' : 'status-dot-warning' }}"></i>
                        {{ $remindersEnabled ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-secondary">Reminder window</span>
                    <span class="pill pill-info">{{ $reminderDays }} days before due</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-secondary">Grace period</span>
                    <span class="pill pill-info">{{ $gracePeriodDays }} days</span>
                </div>
                {{-- NEW: office payment state also surfaced here --}}
                <div class="flex items-center justify-between">
                    <span class="text-sm text-secondary">Office Payments</span>
                    <span class="pill {{ $officePaymentsAllowed ? 'pill-success' : 'pill-danger' }}">
                        <i class="fas fa-circle status-dot {{ $officePaymentsAllowed ? 'status-dot-success' : 'status-dot-danger' }}"></i>
                        {{ $officePaymentsAllowed ? 'Allowed' : 'Blocked' }}
                    </span>
                </div>
                @if($nextGenerationDate)
                <div class="flex items-center justify-between">
                    <span class="text-sm text-secondary">Next generation</span>
                    <span class="pill pill-primary">
                        <i class="far fa-calendar-alt mr-1"></i> {{ $nextGenerationDate }}
                    </span>
                </div>
                @endif
            </div>
        </div>

        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-medium text-primary">Bulk Operations</h3>
                    <p class="text-sm text-secondary">Perform actions on multiple invoices at once</p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <button type="button" onclick="openBulkStatusModal()" class="btn-soft-primary">
                    <i class="fas fa-edit mr-2"></i> Bulk Update
                </button>
                <button type="button" onclick="openExportModal()" class="btn-soft-danger">
                    <i class="fas fa-file-pdf mr-2"></i> Export PDF
                </button>
                <button type="button" onclick="openBulkCoverageModal()" class="btn-soft-info">
                    <i class="fas fa-shield-alt mr-2"></i> Check Coverage
                </button>
            </div>

            {{-- When office payments are disabled, add a hint below the buttons --}}
            @if(!$officePaymentsAllowed)
            <div class="mt-4 p-3 rounded-lg flash-info">
                <p class="text-xs text-secondary">
                    <i class="fas fa-info-circle mr-1 icon-info"></i>
                    The <strong>Paid</strong> option in Bulk Update is hidden because office
                    payments are disabled. Landlords must pay through the online gateway.
                </p>
            </div>
            @endif
        </div>
    </div>

    {{-- ============================================================
         INVOICES TABLE
    ============================================================ --}}
    <div class="card p-6">
        <div class="mb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div class="flex items-center gap-4 flex-wrap">
                <p class="text-sm text-secondary">
                    Showing
                    <span class="font-medium text-primary">{{ $invoices->firstItem() ?? 0 }}</span>–
                    <span class="font-medium text-primary">{{ $invoices->lastItem() ?? 0 }}</span>
                    of <span class="font-medium text-primary">{{ $invoices->total() }}</span>

                    @if($hasActiveFilters)
                        <span class="text-xs ml-1">
                            ({{ number_format($grandTotalInvoices) }} total in system —
                            <a href="{{ route('invoices.index') }}" class="link-inline font-medium">clear filters</a>)
                        </span>
                    @elseif($grandTotalInvoices > 0 && $invoices->total() === 0)
                        <span class="text-xs ml-1 text-danger">
                            ({{ number_format($grandTotalInvoices) }} exist in the system but none matched the current view)
                        </span>
                    @endif
                </p>
                <label class="flex items-center text-sm text-secondary cursor-pointer">
                    <input type="checkbox" id="selectAllInvoices" class="mr-2">
                    Select this page
                </label>
            </div>

            @if($hasActiveFilters)
            <div class="flex items-center flex-wrap gap-2">
                <span class="text-sm text-secondary">Active filters:</span>

                @if(request('property_id'))
                    @php $sp = $properties->firstWhere('id', request('property_id')); @endphp
                    <span class="pill pill-primary">
                        Property: {{ $sp ? $sp->house_number . ' ' . $sp->street_name : 'N/A' }}
                    </span>
                @endif

                @if(request('status'))
                    <span class="pill pill-primary">Status: {{ ucfirst(request('status')) }}</span>
                @endif

                @if(request('type'))
                    <span class="pill pill-primary">Type: {{ ucfirst(request('type')) }}</span>
                @endif

                @if(request('period'))
                    @php
                        try {
                            $periodLabel = \Carbon\Carbon::parse(request('period') . '-01')->format('F Y');
                        } catch (\Throwable $e) {
                            $periodLabel = request('period');
                        }
                    @endphp
                    <span class="pill pill-primary">Period: {{ $periodLabel }}</span>
                @endif

                @if(request('search'))
                    <span class="pill pill-primary">Search: "{{ request('search') }}"</span>
                @endif

                @if(request('has_parent'))
                    <span class="pill pill-info">Parent: {{ ucfirst(request('has_parent')) }}</span>
                @endif

                @if(request('is_bulk'))
                    <span class="pill pill-info">Bulk: {{ ucfirst(request('is_bulk')) }}</span>
                @endif

                @if(request('has_coverage') === 'yes')
                    <span class="pill pill-success">
                        <i class="fas fa-shield-alt mr-1"></i> Active Coverage
                    </span>
                @elseif(request('has_coverage') === 'no')
                    <span class="pill pill-info">No Coverage</span>
                @endif

                @if(request('has_discount'))
                    <span class="pill pill-info">Discount: {{ ucfirst(request('has_discount')) }}</span>
                @endif

                @if(request('has_penalty'))
                    <span class="pill pill-info">Penalty: {{ ucfirst(request('has_penalty')) }}</span>
                @endif

                <a href="{{ route('invoices.index') }}" class="pill pill-warning">
                    <i class="fas fa-times mr-1"></i> Clear all
                </a>
            </div>
            @endif
        </div>

        {{-- Selection actions bar --}}
        <div id="selectionActions" class="hidden mb-4 p-3 rounded-lg flash-info">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div>
                    <span class="text-sm font-medium text-primary">
                        <span id="selectedCount">0</span> invoice(s) selected
                    </span>
                    <span class="text-sm ml-2 text-secondary">
                        Total: <span id="selectedTotal" class="font-medium text-primary">{{ $settings->formatAmount(0) }}</span>
                    </span>
                </div>
                <div class="flex gap-2 flex-wrap">
                    @if($officePaymentsAllowed)
                        <button onclick="bulkMarkAsPaid()" class="btn-soft-success">
                            <i class="fas fa-check mr-1"></i> Mark as Paid
                        </button>
                    @else
                        <span class="pill pill-warning" title="Office payments are disabled in System Settings">
                            <i class="fas fa-info-circle mr-1"></i>
                            Office payments disabled — landlords must pay online
                        </span>
                    @endif
                    <button onclick="bulkExport()" class="btn-soft-primary">
                        <i class="fas fa-download mr-1"></i> Export
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th class="table-th w-8"><input type="checkbox" id="selectAllCheckbox" title="Select all on this page"></th>
                        <th class="table-th">Invoice #</th>
                        <th class="table-th">Property</th>
                        <th class="table-th">Period</th>
                        <th class="table-th">Due Date</th>
                        <th class="table-th">Amount</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">Type</th>
                        <th class="table-th">Coverage</th>
                        <th class="table-th">Payment</th>
                        <th class="table-th">Actions</th>
                    </tr>
                </thead>
                <tbody id="invoicesTableBody">
                    @forelse($invoices as $invoice)
                        @include('admin.invoices.partials.row', ['invoice' => $invoice, 'settings' => $settings])
                    @empty
                     <tr>
                        <td colspan="11" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center text-secondary">

                                @if($hasActiveFilters)
                                    <i class="fas fa-filter text-4xl mb-4 opacity-50"></i>
                                    <p class="text-lg font-medium mb-2">No invoices match these filters</p>
                                    <p class="text-sm mb-3">
                                        {{ number_format($grandTotalInvoices) }} invoice(s) exist in the system.
                                        Try clearing one or more filters to see them.
                                    </p>
                                    <a href="{{ route('invoices.index') }}" class="btn-warning">
                                        <i class="fas fa-times mr-1"></i> Clear all filters
                                    </a>
                                @elseif($grandTotalInvoices > 0)
                                    <i class="fas fa-exclamation-triangle text-4xl mb-4 opacity-50"></i>
                                    <p class="text-lg font-medium mb-2">Unable to display invoices</p>
                                    <p class="text-sm mb-3">
                                        {{ number_format($grandTotalInvoices) }} invoice(s) exist but were not loaded into the view.
                                        Refresh the page or check the application log.
                                    </p>
                                    <a href="{{ route('invoices.index') }}" class="btn-secondary">
                                        <i class="fas fa-sync mr-1"></i> Refresh
                                    </a>
                                @else
                                    <i class="fas fa-file-invoice text-4xl mb-4 opacity-50"></i>
                                    <p class="text-lg font-medium mb-2">No invoices yet</p>
                                    <p class="text-sm">Generate invoices to see them here.</p>
                                @endif

                            </div>
                        </td>
                     </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($invoices->hasPages())
        <div class="flex flex-col md:flex-row items-center justify-between mt-6 gap-3">
            <div class="text-sm text-secondary">
                Page {{ $invoices->currentPage() }} of {{ $invoices->lastPage() }}
            </div>
            <div>
                {{ $invoices->appends(request()->query())->links() }}
            </div>
        </div>
        @endif
    </div>
</div>

{{-- ============================================================
     MODALS — unified styles
============================================================ --}}
@include('admin.invoices.partials.modals')

{{-- Hidden forms for exports --}}
<form id="currentPageExportForm" method="GET" action="{{ route('invoices.export-current-page') }}" target="_blank">
    @foreach(request()->except(['_token','page']) as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
</form>

<form id="allFilteredExportForm" method="GET" action="{{ route('invoices.export-all-filtered') }}" target="_blank">
    @foreach(request()->except(['_token','page']) as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
</form>

<form id="bulkExportForm" method="POST" action="{{ route('invoices.bulk-export') }}" target="_blank">
    @csrf
    <input type="hidden" name="invoice_ids" id="bulkInvoiceIds">
    <input type="hidden" name="format" value="pdf">
</form>
@endsection

@section('scripts')
<script>
'use strict';

/* ============================================================
   CONFIG
   ============================================================ */
let APP_CONFIG = {
    currencySymbol:   @json($currencySymbol ?? '₵'),
    decimalPlaces:    {{ (int) ($decimalPlaces ?? 2) }},
    currencyPosition: @json($currencyPosition ?? 'left'),
    csrfToken:        @json(csrf_token()),
    baseUrl:          @json(url('/')),
    officePaymentsAllowed: {{ $officePaymentsAllowed ? 'true' : 'false' }},
    routes: {
        bulkMarkPaid:  @json(url('/invoices/bulk-mark-paid')),
        bulkStatus:    @json(url('/invoices/bulk-update-status')),
        checkCoverage: @json(url('/invoices/check-coverage')),
    },
};

APP_CONFIG.routes.coverageInfo  = (id) => `${APP_CONFIG.baseUrl}/invoices/${id}/coverage-info`;
APP_CONFIG.routes.canDelete     = (id) => `${APP_CONFIG.baseUrl}/invoices/${id}/can-delete`;
APP_CONFIG.routes.markPaid      = (id) => `${APP_CONFIG.baseUrl}/invoices/${id}/mark-paid`;
APP_CONFIG.routes.softDelete    = (id) => `${APP_CONFIG.baseUrl}/invoices/${id}`;
APP_CONFIG.routes.reverseConsol = (id) => `${APP_CONFIG.baseUrl}/invoices/${id}/reverse-consolidation`;
APP_CONFIG.routes.exportPdf     = (id) => `${APP_CONFIG.baseUrl}/invoices/${id}/export-pdf`;

console.log('[Invoice] APP_CONFIG ready', APP_CONFIG);

/* ============================================================
   STATE
   ============================================================ */
let selectedInvoices = new Set();
let generationFormSubmitted = false;

/* ============================================================
   MONEY FORMATTING
   ============================================================ */
function formatAmount(amount) {
    const n = Number(amount) || 0;
    const formatted = n.toLocaleString('en-US', {
        minimumFractionDigits: APP_CONFIG.decimalPlaces,
        maximumFractionDigits: APP_CONFIG.decimalPlaces,
    });
    return APP_CONFIG.currencyPosition.startsWith('right')
        ? `${formatted} ${APP_CONFIG.currencySymbol}`
        : `${APP_CONFIG.currencySymbol}${formatted}`;
}

/* ============================================================
   DOM READY
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    // Auto-dismiss flash banners
    document.querySelectorAll('[data-flash]').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, 5000);
    });

    document.querySelectorAll('.flash-close').forEach(btn => {
        btn.addEventListener('click', () => btn.closest('.card')?.remove());
    });

    initializeCheckboxes();

    const generateForm = document.getElementById('generateMonthlyForm');
    if (generateForm) {
        generateForm.addEventListener('submit', function (e) {
            if (!generationFormSubmitted) {
                e.preventDefault();
                if (confirmGenerateMonthly()) {
                    showGenerationModal();
                    generationFormSubmitted = true;
                    generateForm.submit();
                }
            }
        });
    }

    document.querySelectorAll('.modal-backdrop').forEach(modal => {
        modal.addEventListener('click', e => {
            if (e.target === modal) closeAllModals();
        });
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeAllModals();
    });
});

/* ============================================================
   CHECKBOXES
   ============================================================ */
function initializeCheckboxes() {
    const tbody = document.getElementById('invoicesTableBody');
    if (!tbody) return;

    const headerAll = document.getElementById('selectAllCheckbox');
    const topAll    = document.getElementById('selectAllInvoices');

    tbody.addEventListener('change', e => {
        const cb = e.target;
        if (!cb.classList.contains('invoice-checkbox')) return;
        const id = parseInt(cb.value, 10);
        cb.checked ? selectedInvoices.add(id) : selectedInvoices.delete(id);
        updateSelectionSummary();
    });

    [headerAll, topAll].forEach(el => {
        if (!el) return;
        el.addEventListener('change', () => {
            const checked = el.checked;
            document.querySelectorAll('.invoice-checkbox:not(:disabled)').forEach(cb => {
                cb.checked = checked;
                const id = parseInt(cb.value, 10);
                checked ? selectedInvoices.add(id) : selectedInvoices.delete(id);
            });
            updateSelectionSummary();
        });
    });
}

function updateSelectionSummary() {
    const all     = document.querySelectorAll('.invoice-checkbox:not(:disabled)');
    const checked = document.querySelectorAll('.invoice-checkbox:checked');
    const count   = checked.length;

    const countEl = document.getElementById('selectedCount');
    const totalEl = document.getElementById('selectedTotal');
    if (countEl) countEl.textContent = count;

    let total = 0;
    checked.forEach(cb => { total += parseFloat(cb.dataset.amount) || 0; });
    if (totalEl) totalEl.textContent = formatAmount(total);

    const bar = document.getElementById('selectionActions');
    bar?.classList.toggle('hidden', count === 0);

    [document.getElementById('selectAllCheckbox'), document.getElementById('selectAllInvoices')].forEach(el => {
        if (!el) return;
        el.checked       = count > 0 && count === all.length;
        el.indeterminate = count > 0 && count < all.length;
    });
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.invoice-checkbox:checked')).map(cb => cb.value);
}

/* ============================================================
   MODAL HELPERS
   ============================================================ */
function openModal(id) {
    const el = document.getElementById(id);
    if (!el) {
        console.warn(`[Invoice] openModal: element #${id} not found`);
        return false;
    }
    el.classList.remove('hidden');
    return true;
}

function closeModal(id) {
    document.getElementById(id)?.classList.add('hidden');
}

function closeAllModals() {
    document.querySelectorAll('.modal-backdrop').forEach(m => m.classList.add('hidden'));
}

/* ============================================================
   GENERATION
   ============================================================ */
function confirmGenerateMonthly() {
    return confirm(
        'Generate monthly invoices for all properties?\n\n' +
        'This will queue the generation in the background and may take a few minutes. ' +
        'You can safely leave this page.'
    );
}

function showGenerationModal() {
    openModal('generationProgressModal');
}

function hideGenerationModal() {
    closeModal('generationProgressModal');
}

/* ============================================================
   EXPORT
   ============================================================ */
function openExportModal() {
    console.log('[Invoice] openExportModal invoked');
    const ok = openModal('exportModal');
    if (!ok) {
        alert('Export dialog not available. Please reload the page.');
    }
}

function closeExportModal() {
    closeModal('exportModal');
}

function exportCurrentPagePDF() {
    console.log('[Invoice] exportCurrentPagePDF invoked');
    closeExportModal();
    showLoading();

    const form = document.getElementById('currentPageExportForm');
    if (!form) {
        hideLoading();
        console.error('[Invoice] currentPageExportForm not found');
        return alert('Export form missing. Please reload the page.');
    }
    if (!form.action || form.action === window.location.href) {
        hideLoading();
        console.error('[Invoice] currentPageExportForm has no valid action', form.action);
        return alert('Export URL not configured.');
    }

    console.log('[Invoice] Submitting current-page export to', form.action);
    form.submit();
    setTimeout(hideLoading, 2500);
}

function exportFilteredPDF() {
    console.log('[Invoice] exportFilteredPDF invoked');
    closeExportModal();
    showLoading();

    const form = document.getElementById('allFilteredExportForm');
    if (!form) {
        hideLoading();
        console.error('[Invoice] allFilteredExportForm not found');
        return alert('Export form missing. Please reload the page.');
    }
    if (!form.action || form.action === window.location.href) {
        hideLoading();
        console.error('[Invoice] allFilteredExportForm has no valid action', form.action);
        return alert('Export URL not configured.');
    }

    console.log('[Invoice] Submitting filtered export to', form.action);
    form.submit();
    setTimeout(hideLoading, 3000);
}

function exportSinglePDF(id) {
    console.log('[Invoice] exportSinglePDF invoked for invoice', id);
    showLoading();

    const url = APP_CONFIG.routes.exportPdf(id);
    console.log('[Invoice] Opening', url);

    const popup = window.open(url, '_blank');
    if (!popup || popup.closed || typeof popup.closed === 'undefined') {
        hideLoading();
        console.warn('[Invoice] Popup blocked for', url);
        return alert('Popup blocked. Please allow popups for this site and try again.');
    }

    setTimeout(hideLoading, 2000);
}

function bulkExport() {
    const ids = getSelectedIds();
    if (!ids.length) return alert('Please select at least one invoice to export.');

    console.log('[Invoice] bulkExport invoked with IDs', ids);
    showLoading();

    const form  = document.getElementById('bulkExportForm');
    const input = document.getElementById('bulkInvoiceIds');

    if (!form || !input) {
        hideLoading();
        console.error('[Invoice] bulkExportForm or bulkInvoiceIds missing');
        return alert('Bulk export form missing. Please reload the page.');
    }

    input.value = ids.join(',');
    console.log('[Invoice] Submitting bulk export to', form.action, 'with IDs', input.value);
    form.submit();
    setTimeout(hideLoading, 3000);
}

function showLoading() { openModal('pdfLoadingModal'); }
function hideLoading() { closeModal('pdfLoadingModal'); }

/* ============================================================
   FILTERS
   ============================================================ */
function toggleAdvancedFilters() {
    const panel = document.getElementById('advancedFilters');
    const icon  = document.getElementById('advancedFilterIcon');
    panel?.classList.toggle('hidden');
    icon?.classList.toggle('fa-chevron-down');
    icon?.classList.toggle('fa-chevron-up');
}

/* ============================================================
   SOFT DELETE
   ============================================================ */
async function confirmSoftDelete(id) {
    const form = document.getElementById('softDeleteForm');
    if (form) form.action = APP_CONFIG.routes.softDelete(id);

    try {
        const res = await fetch(APP_CONFIG.routes.canDelete(id), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        const data = await res.json();
        const warning = document.getElementById('softDeleteWarning');
        const msg     = document.getElementById('softDeleteWarningMessage');
        if (!data.can_delete) {
            warning?.classList.remove('hidden');
            if (msg) msg.textContent = data.message;
        } else {
            warning?.classList.add('hidden');
        }
    } catch (e) {
        console.warn('[Invoice] Delete eligibility check failed', e);
    }

    openModal('softDeleteModal');
}

function closeSoftDeleteModal() {
    closeModal('softDeleteModal');
    document.getElementById('softDeleteForm')?.reset();
}

/* ============================================================
   MARK AS PAID
   ============================================================ */
function openMarkAsPaidModal(id) {
    // Guard — if office payments were disabled mid-session, don't open the modal
    if (!APP_CONFIG.officePaymentsAllowed) {
        return alert('Office payments are currently disabled. Landlords must pay through the online gateway.');
    }

    const form = document.getElementById('markAsPaidForm');
    if (form) form.action = APP_CONFIG.routes.markPaid(id);

    const row     = document.querySelector(`tr[data-invoice-id="${id}"]`);
    const section = document.getElementById('coverageActivationSection');
    section?.classList.toggle('hidden', row?.dataset.type !== 'bulk');

    openModal('markAsPaidModal');
}

function closeMarkAsPaidModal() {
    closeModal('markAsPaidModal');
    document.getElementById('markAsPaidForm')?.reset();
    document.getElementById('coverageActivationSection')?.classList.add('hidden');
}

function bulkMarkAsPaid() {
    // Guard — same check as openMarkAsPaidModal
    if (!APP_CONFIG.officePaymentsAllowed) {
        return alert('Office payments are currently disabled. Landlords must pay through the online gateway.');
    }

    const ids = getSelectedIds();
    if (!ids.length) return alert('Please select at least one invoice.');

    const hasConsolidated = Array.from(document.querySelectorAll('.invoice-checkbox:checked'))
        .some(cb => cb.closest('tr')?.dataset.status === 'consolidated');

    if (hasConsolidated) {
        return alert('Cannot mark consolidated invoices as paid. Use the bulk invoice instead.');
    }

    if (!confirm(`Mark ${ids.length} invoice(s) as paid?`)) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = APP_CONFIG.routes.bulkMarkPaid;
    form.innerHTML = `
        <input type="hidden" name="_token" value="${APP_CONFIG.csrfToken}">
        <input type="hidden" name="invoice_ids" value='${JSON.stringify(ids)}'>
    `;
    document.body.appendChild(form);
    form.submit();
}

/* ============================================================
   REVERSE CONSOLIDATION
   ============================================================ */
function openReverseConsolidationModal(id) {
    const form = document.getElementById('reverseConsolidationForm');
    if (form) form.action = APP_CONFIG.routes.reverseConsol(id);
    openModal('reverseConsolidationModal');
}

function closeReverseConsolidationModal() {
    closeModal('reverseConsolidationModal');
    document.getElementById('reverseConsolidationForm')?.reset();
}

/* ============================================================
   BULK STATUS UPDATE
   ============================================================ */
function openBulkStatusModal() {
    const ids = getSelectedIds();
    if (!ids.length) return alert('Please select at least one invoice.');

    const hasBulk = Array.from(document.querySelectorAll('.invoice-checkbox:checked'))
        .some(cb => cb.closest('tr')?.dataset.type === 'bulk');

    document.getElementById('bulkCoverageSection')?.classList.toggle('hidden', !hasBulk);

    const input = document.getElementById('bulkStatusInvoiceIds');
    if (input) input.value = JSON.stringify(ids);

    openModal('bulkStatusModal');
}

function closeBulkStatusModal() {
    closeModal('bulkStatusModal');
    document.getElementById('bulkStatusForm')?.reset();
    document.getElementById('bulkCoverageSection')?.classList.add('hidden');
}

/* ============================================================
   COVERAGE
   ============================================================ */
async function showCoverageInfo(id) {
    try {
        const res  = await fetch(APP_CONFIG.routes.coverageInfo(id), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (!data.success) return alert('Failed to load coverage: ' + (data.message || 'Unknown error'));

        const periods = data.coverage?.formatted_periods
            || (data.coverage?.periods || []).map(p => {
                const [y, m] = p.split('-');
                return new Date(y, m - 1).toLocaleString('default', { month: 'long', year: 'numeric' });
            });

        const html = `
            <div class="p-4 rounded-lg flash-success">
                <p class="font-medium mb-2 text-primary">Covered Periods:</p>
                <div class="flex flex-wrap gap-2">
                    ${periods.map(p => `<span class="pill pill-success">${p}</span>`).join('')}
                </div>
                <p class="text-sm mt-3 text-secondary">
                    <i class="fas fa-info-circle mr-1"></i>
                    No further invoices will be generated for these periods.
                </p>
            </div>`;

        const target = document.getElementById('coverageModalContent');
        if (target) target.innerHTML = html;

        openModal('coverageInfoModal');
    } catch (e) {
        console.error('[Invoice] showCoverageInfo failed', e);
        alert('Error loading coverage information.');
    }
}

function closeCoverageModal()     { closeModal('coverageInfoModal'); }
function openBulkCoverageModal()  { openModal('bulkCoverageCheckModal'); }

function closeBulkCoverageModal() {
    closeModal('bulkCoverageCheckModal');
    document.getElementById('bulkCoverageForm')?.reset();
}

async function checkBulkCoverage(event) {
    event.preventDefault();
    const propertyId = document.getElementById('coveragePropertyId')?.value;
    const period     = document.getElementById('coveragePeriod')?.value;
    if (!propertyId || !period) return alert('Please select both property and period.');

    try {
        const url = `${APP_CONFIG.routes.checkCoverage}?property_id=${propertyId}&period=${period}`;
        console.log('[Invoice] checkBulkCoverage →', url);

        const res  = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        const data = await res.json();

        closeBulkCoverageModal();
        if (!data.success) return alert('Error: ' + (data.message || 'Unknown'));

        let msg = data.covered
            ? `✅ Period ${period} is covered by bulk payment.`
            : `❌ Period ${period} is NOT covered by any bulk payment.`;

        if (data.coverage_details) {
            msg += `\n\nBulk Invoice: INV-${String(data.coverage_details.bulk_invoice_id).padStart(6, '0')}`;
            msg += `\nPayment Date: ${data.coverage_details.payment_date}`;
            msg += `\nTransaction: ${data.coverage_details.transaction_id}`;
        }
        alert(msg);
    } catch (e) {
        console.error('[Invoice] checkBulkCoverage failed', e);
        alert('Error checking coverage status.');
    }
}
</script>
@endsection