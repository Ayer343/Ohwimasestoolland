{{-- resources/views/landlord/invoices/index.blade.php --}}
@extends('layouts.landlord')

@section('title', 'My Invoices')

@php
    use Carbon\Carbon;

    // ── Normalize controller data ──
    $properties  = $properties  ?? collect();
    $bulkCoverages = $bulkCoverages ?? [];
    $settings    = $settings    ?? \App\Models\SystemSetting::getSettings();

    $invoices = $invoices instanceof \Illuminate\Pagination\LengthAwarePaginator
        ? $invoices
        : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);

    $totalDue             = $totalDue ?? 0;
    $outstandingInvoices  = $outstandingInvoices ?? 0;
    $totalCoveredMonths   = $totalCoveredMonths ?? 0;
    $remindersEnabled     = $remindersEnabled ?? false;
    $reminderDays         = $reminderDays ?? 7;
    $gracePeriodDays      = $gracePeriodDays ?? 7;
    $availableMethods     = $availableMethods ?? [];

    // Precompute counts once (not per-row, not inline deep in markup)
    $payableCount = 0;
    $consolidatedCount = 0;
    $processingCount = 0;
    $regularPaidCount = 0;
    $bulkPaidCount = 0;

    foreach ($invoices as $invoice) {
        if ($invoice->status === 'consolidated') {
            $consolidatedCount++;
        } elseif ($invoice->status === 'processing') {
            $processingCount++;
        }

        if ($invoice->status === 'paid' && !$invoice->bulk_payment_id && !$invoice->is_bulk_payment) {
            $regularPaidCount++;
        } elseif ($invoice->is_bulk_payment && $invoice->status === 'paid') {
            $bulkPaidCount++;
        }

        if (in_array($invoice->status, ['pending', 'overdue', 'processing'])
            && !$invoice->bulk_payment_id
            && !$invoice->is_bulk_payment) {
            $payableCount++;
        }
    }

    $totalPaidInvoices = $regularPaidCount + $bulkPaidCount;

    // Bulk coverage aggregate
    $activeBulkCount = 0;
    foreach ($bulkCoverages as $propertyCoverages) {
        if (is_array($propertyCoverages)) {
            $activeBulkCount += count($propertyCoverages);
        }
    }

    $currencySymbol   = $settings->currency_symbol   ?? '₵';
    $decimalPlaces    = (int) ($settings->decimal_places ?? 2);
    $currencyPosition = $settings->currency_position ?? 'left';
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
                        My Invoices
                        @if($outstandingInvoices > 0)
                        <span class="pill pill-warning">
                            <i class="fas fa-clock mr-1"></i> {{ $outstandingInvoices }} outstanding
                        </span>
                        @endif
                    </h2>
                    <div class="text-sm flex items-center mt-1 flex-wrap gap-2 text-secondary">
                        <i class="fas fa-info-circle"></i>
                        <span>Manage your monthly dues across {{ $properties->count() }} {{ Str::plural('property', $properties->count()) }}</span>
                        <span>•</span>
                        <i class="fas fa-circle status-dot status-dot-success"></i>
                        <span class="font-medium">{{ number_format($totalDue, $decimalPlaces) }} {{ $currencySymbol }} total due</span>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="openExportModal()" class="btn-export">
                    <i class="fas fa-file-pdf mr-2"></i> Export PDF
                </button>
            </div>
        </div>
    </div>

    {{-- ============================================================
         FLASH MESSAGES
    ============================================================ --}}
    @foreach(['success' => 'check-circle', 'error' => 'exclamation-circle', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'] as $type => $icon)
        @if(session($type))
        <div class="card" data-flash>
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
         STATS CARDS
    ============================================================ --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="card stat-card stat-primary">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1 text-secondary">Total Due</p>
                    <p class="text-2xl font-bold text-primary">{{ $settings->formatAmount($totalDue) }}</p>
                    <p class="text-xs mt-1 text-secondary">Pending + overdue</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center icon-circle-primary">
                    <i class="fas fa-money-bill-wave text-lg"></i>
                </div>
            </div>
        </div>

        <div class="card stat-card stat-warning">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1 text-secondary">Outstanding</p>
                    <p class="text-2xl font-bold text-warning">{{ number_format($outstandingInvoices) }}</p>
                    <p class="text-xs mt-1 text-secondary">Invoice(s)</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center icon-circle-warning">
                    <i class="fas fa-clock text-lg"></i>
                </div>
            </div>
        </div>

        <div class="card stat-card stat-info">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1 text-secondary">Properties</p>
                    <p class="text-2xl font-bold text-info">{{ number_format($properties->count()) }}</p>
                    <p class="text-xs mt-1 text-secondary">Registered</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center icon-circle-info">
                    <i class="fas fa-building text-lg"></i>
                </div>
            </div>
        </div>

        <div class="card stat-card stat-success">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1 text-secondary">Active Coverage</p>
                    <p class="text-2xl font-bold text-success">{{ number_format($totalCoveredMonths) }}</p>
                    <p class="text-xs mt-1 text-secondary">
                        {{ $activeBulkCount }} bulk payment(s)
                    </p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center icon-circle-success">
                    <i class="fas fa-shield-alt text-lg"></i>
                </div>
            </div>
        </div>

        <div class="card stat-card stat-success">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1 text-secondary">Paid Invoices</p>
                    <p class="text-2xl font-bold text-success">{{ number_format($totalPaidInvoices) }}</p>
                    <p class="text-xs mt-1 text-secondary">
                        @if($bulkPaidCount > 0)
                            {{ $regularPaidCount }} regular + {{ $bulkPaidCount }} bulk
                        @else
                            Regular only
                        @endif
                    </p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center icon-circle-success">
                    <i class="fas fa-check-circle text-lg"></i>
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
                <strong class="font-semibold text-primary">Understanding Your Invoice Statistics:</strong>
                <ul class="mt-2 space-y-1">
                    <li>• <strong class="text-success">Paid Invoices</strong> — Regular invoices you've paid individually</li>
                    <li>• <strong class="text-success">Bulk Payments</strong> — Multi-month payments covering future months</li>
                    <li>• <strong class="text-info">Consolidated Invoices</strong> — Original invoices now covered by a bulk payment (not counted separately)</li>
                    <li>• <strong class="text-primary">Active Coverage</strong> — Months already paid for via bulk payments</li>
                </ul>
                <p class="mt-2 text-xs">
                    <i class="fas fa-lightbulb mr-1"></i>
                    When you make a bulk payment, the original invoices become "Consolidated" and are no longer counted as separate paid invoices.
                </p>
            </div>
        </div>
    </div>

    {{-- ============================================================
         ACTIVE BULK COVERAGE BANNERS
    ============================================================ --}}
    @if(count($bulkCoverages) > 0)
        @foreach($bulkCoverages as $propertyId => $coverages)
            @if(is_array($coverages) && count($coverages) > 0)
                @foreach($coverages as $coverage)
                    @if(is_array($coverage) && !empty($coverage))
                        @php
                            $property = $properties->firstWhere('id', $propertyId);
                            $periods  = $coverage['periods'] ?? [];
                            $formattedPeriods = collect($periods)
                                ->map(fn ($p) => $p ? Carbon::parse($p . '-01')->format('M Y') : '')
                                ->filter()
                                ->values()
                                ->toArray();
                        @endphp
                        <div class="card" data-coverage-banner>
                            <div class="flex items-start p-4 rounded-lg flash-success">
                                <i class="fas fa-shield-alt icon-success text-xl mt-1 mr-3"></i>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between flex-wrap gap-2">
                                        <div>
                                            <strong class="font-bold text-success">✅ Active Bulk Coverage</strong>
                                            <span class="ml-2 text-sm text-secondary">
                                                {{ $property->property_name ?? ($property->street_name ?? 'Property') }}
                                            </span>
                                        </div>
                                        <span class="pill pill-success">{{ count($periods) }} months covered</span>
                                    </div>
                                    <p class="text-sm mt-2 text-secondary">
                                        <i class="fas fa-calendar-check mr-1"></i>
                                        Covered periods:
                                        {{ implode(', ', array_slice($formattedPeriods, 0, 3)) }}
                                        @if(count($formattedPeriods) > 3)
                                            and {{ count($formattedPeriods) - 3 }} more
                                        @endif
                                    </p>
                                    <div class="flex items-center justify-between mt-2 flex-wrap gap-2">
                                        <p class="text-xs text-secondary">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            No invoices will be generated for these months.
                                        </p>
                                        <a href="{{ route('landlord.invoices.show', $coverage['invoice_id']) }}" class="btn-soft-success">
                                            <i class="fas fa-eye mr-1"></i> View Bulk Invoice
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif
        @endforeach
    @endif

    {{-- ============================================================
         CONSOLIDATED / PROCESSING INFO BANNERS
    ============================================================ --}}
    @if($consolidatedCount > 0)
    <div class="card">
        <div class="flex items-start p-4 rounded-lg flash-info">
            <i class="fas fa-info-circle icon-info text-xl mt-1 mr-3"></i>
            <div>
                <strong class="font-bold text-info">Consolidated Invoices</strong>
                <p class="text-sm mt-1 text-secondary">
                    You have {{ $consolidatedCount }} consolidated invoice(s) that are part of bulk payments.
                    These are shown for reference but cannot be paid individually.
                </p>
            </div>
        </div>
    </div>
    @endif

    @if($processingCount > 0)
    <div class="card">
        <div class="flex items-start p-4 rounded-lg flash-warning">
            <i class="fas fa-clock icon-warning text-xl mt-1 mr-3"></i>
            <div>
                <strong class="font-bold text-warning">Processing Invoices</strong>
                <p class="text-sm mt-1 text-secondary">
                    You have {{ $processingCount }} invoice(s) currently in <strong>Processing</strong> status.
                    These are payments that were initiated but may have failed or been cancelled.
                    You can retry payment using the <strong>Retry Payment</strong> button.
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- ============================================================
         FILTERS CARD
    ============================================================ --}}
    <div class="card p-6">
        <form method="GET" action="{{ route('landlord.invoices') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-3">
                <select name="property_id" class="form-select">
                    <option value="">All Properties</option>
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}" @selected(request('property_id') == $property->id)>
                            @if($property->property_name){{ $property->property_name }} — @endif
                            {{ $property->house_number ?? '#' }} {{ $property->street_name }}
                        </option>
                    @endforeach
                </select>

                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach(['pending','processing','paid','overdue','consolidated'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>

                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    <option value="regular" @selected(request('type') === 'regular')>Regular Monthly</option>
                    <option value="bulk"    @selected(request('type') === 'bulk')>Bulk Payment</option>
                </select>

                <select name="coverage" class="form-select">
                    <option value="">All Invoices</option>
                    <option value="covered"     @selected(request('coverage') === 'covered')>Covered by Bulk</option>
                    <option value="not_covered" @selected(request('coverage') === 'not_covered')>Not Covered</option>
                </select>

                <div class="flex gap-2">
                    <button type="submit" class="btn-primary flex-1">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                    <a href="{{ route('landlord.invoices') }}" class="btn-secondary" title="Reset">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- ============================================================
         AVAILABLE PAYMENT METHODS
    ============================================================ --}}
    @if(count($availableMethods) > 0)
    <div class="card p-6">
        <h3 class="font-medium mb-4 text-primary">Available Payment Methods</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach($availableMethods as $method)
                @php
                    [$icon, $color] = match($method) {
                        'mtn_momo'        => ['fas fa-mobile-alt',  'primary'],
                        'telecel_cash'    => ['fas fa-sim-card',    'info'],
                        'airteltigo_cash' => ['fas fa-wifi',        'success'],
                        'bank_transfer'   => ['fas fa-university',  'warning'],
                        default           => ['fas fa-credit-card', 'secondary'],
                    };
                @endphp
                <div class="flex items-center p-3 rounded-lg" style="background-color: rgba(var(--{{ $color }}-rgb), 0.05); border: 1px solid rgba(var(--{{ $color }}-rgb), 0.1);">
                    <i class="{{ $icon }} text-xl mr-3 icon-{{ $color }}"></i>
                    <span class="text-sm text-primary font-medium">
                        {{ ucfirst(str_replace('_', ' ', $method)) }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ============================================================
         INVOICES TABLE CARD
    ============================================================ --}}
    <div class="card p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-3">
            <p class="text-sm text-secondary">
                Showing
                <span class="font-medium text-primary">{{ $invoices->firstItem() ?? 0 }}</span>–
                <span class="font-medium text-primary">{{ $invoices->lastItem() ?? 0 }}</span>
                of <span class="font-medium text-primary">{{ $invoices->total() }}</span> results
                @if(request('status') !== 'consolidated')
                    <span class="ml-2 text-xs">(consolidated invoices hidden by default)</span>
                @endif
            </p>

            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="loadOutstandingInvoices()" class="btn-soft-warning">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
                <a href="{{ route('landlord.invoices', ['status' => 'consolidated']) }}" class="btn-soft-info">
                    <i class="fas fa-layer-group mr-1"></i> Consolidated
                </a>
                <a href="{{ route('landlord.invoices', ['status' => 'processing']) }}" class="btn-soft-warning">
                    <i class="fas fa-clock mr-1"></i> Processing
                </a>
                <button type="button" onclick="showCoverageSummary()" class="btn-soft-success">
                    <i class="fas fa-shield-alt mr-1"></i> Coverage
                </button>
            </div>
        </div>

        {{-- Payment selection bar --}}
        @if($payableCount > 0)
        <div class="mb-6 p-4 rounded-lg flash-info">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="flex-1">
                    <p class="font-medium text-primary mb-1">Select Invoices to Pay</p>
                    <p class="text-sm text-secondary">
                        Select one or more invoices to pay at once. Consolidated invoices cannot be selected.
                        @if($processingCount > 0)
                            <span class="ml-2 text-xs text-warning">(Processing invoices can be retried)</span>
                        @endif
                    </p>

                    <div id="selectedInvoicesSummary" class="hidden mt-3">
                        <div class="flex items-center justify-between p-3 rounded-lg flash-success flex-wrap gap-2">
                            <div>
                                <p class="text-sm font-medium text-primary">
                                    <span id="selectedCount">0</span> invoice(s) selected
                                </p>
                                <p class="text-xs text-secondary">
                                    Total: <span id="selectedTotal" class="font-medium text-primary">{{ $settings->formatAmount(0) }}</span>
                                </p>
                                <div id="bulkPaymentInfo" class="hidden mt-2">
                                    <div class="flex items-center text-xs text-success">
                                        <i class="fas fa-shield-alt mr-1"></i>
                                        <span>Creating a bulk payment will cover future months and prevent duplicate invoices</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" onclick="processSelectedInvoices('invoices')" class="btn-primary">
                                    <i class="fas fa-credit-card mr-2"></i> Pay Selected
                                </button>
                                <button type="button" onclick="processSelectedInvoices('bulk')" class="btn-success">
                                    <i class="fas fa-layer-group mr-2"></i> Create Bulk
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div id="loadingIndicator" class="hidden mb-4">
            <div class="flex items-center justify-center p-4">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2" style="border-color: var(--primary);"></div>
                <span class="ml-3 text-sm text-secondary">Loading…</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        @if($payableCount > 0)
                        <th class="table-th w-10">
                            <input type="checkbox" id="selectAll" title="Select all payable invoices">
                        </th>
                        @endif
                        <th class="table-th">Invoice No.</th>
                        <th class="table-th">Property</th>
                        <th class="table-th">Period</th>
                        <th class="table-th">Amount</th>
                        <th class="table-th">Due Date</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">Actions</th>
                    </tr>
                </thead>
                <tbody id="invoicesTableBody">
                    @forelse($invoices as $invoice)
                        @php
                            // Coverage check — flatten and precompute once per invoice
                            $isCoveredByBulk = false;
                            $coveringBulkInvoice = null;

                            if (isset($bulkCoverages[$invoice->property_id])) {
                                foreach ($bulkCoverages[$invoice->property_id] as $coverage) {
                                    if (is_array($coverage) && isset($coverage['periods']) && in_array($invoice->period, $coverage['periods'])) {
                                        $isCoveredByBulk = true;
                                        $coveringBulkInvoice = $coverage;
                                        break;
                                    }
                                }
                            }

                            $isProcessing = $invoice->status === 'processing';
                            $isPayable = in_array($invoice->status, ['pending', 'overdue', 'processing'])
                                && !$invoice->is_bulk_payment
                                && !$invoice->bulk_payment_id
                                && !$isCoveredByBulk;
                        @endphp
                        <tr class="invoice-row {{ $invoice->status === 'consolidated' ? 'row-consolidated' : '' }} {{ $isCoveredByBulk ? 'row-covered' : '' }} {{ $isProcessing ? 'row-processing' : '' }}"
                            data-invoice-id="{{ $invoice->id }}"
                            data-amount="{{ $invoice->total_amount }}"
                            data-property-id="{{ $invoice->property_id }}"
                            data-status="{{ $invoice->status }}"
                            data-is-bulk="{{ $invoice->is_bulk_payment ? 'true' : 'false' }}"
                            data-has-parent="{{ $invoice->bulk_payment_id ? 'true' : 'false' }}"
                            data-covered-by-bulk="{{ $isCoveredByBulk ? 'true' : 'false' }}">

                            @if($payableCount > 0)
                            <td class="table-td">
                                @if($isPayable)
                                    <input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}"
                                           class="invoice-checkbox" data-amount="{{ $invoice->total_amount }}">
                                @elseif($invoice->status === 'consolidated')
                                    <span class="pill pill-info"><i class="fas fa-link mr-1"></i> Consolidated</span>
                                @elseif($invoice->bulk_payment_id)
                                    <span class="pill pill-secondary"><i class="fas fa-layer-group mr-1"></i> In Bulk</span>
                                @elseif($isCoveredByBulk)
                                    <span class="pill pill-success"><i class="fas fa-shield-alt mr-1"></i> Covered</span>
                                @endif
                            </td>
                            @endif

                            <td class="table-td">
                                <p class="font-medium text-primary">
                                    @if($invoice->is_bulk_payment)
                                        <i class="fas fa-layer-group mr-1 text-xs icon-info"></i>
                                    @elseif($invoice->bulk_payment_id)
                                        <i class="fas fa-link mr-1 text-xs icon-info"></i>
                                    @elseif($isCoveredByBulk)
                                        <i class="fas fa-shield-alt mr-1 text-xs icon-success"></i>
                                    @elseif($isProcessing)
                                        <i class="fas fa-clock mr-1 text-xs icon-warning"></i>
                                    @endif
                                    {{ $invoice->invoice_number ?? 'INV-' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}
                                </p>
                                @if($invoice->payment_reference)
                                    <p class="text-xs text-secondary">Ref: {{ $invoice->payment_reference }}</p>
                                @endif
                                @if($isProcessing)
                                    <p class="text-xs text-warning">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Failed or cancelled — retry available
                                    </p>
                                @endif
                            </td>

                            <td class="table-td">
                                @if($invoice->property)
                                    <div class="flex items-start gap-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center icon-circle-info flex-shrink-0">
                                            <i class="fas fa-home text-xs"></i>
                                        </div>
                                        <div>
                                            @if($invoice->property->property_name)
                                                <p class="font-medium text-sm text-primary">{{ $invoice->property->property_name }}</p>
                                            @endif
                                            <p class="text-xs text-secondary">
                                                {{ $invoice->property->house_number ?? '#' }} {{ $invoice->property->street_name }}
                                            </p>
                                        </div>
                                    </div>
                                @endif
                            </td>

                            <td class="table-td">
                                @php
                                    $periodDisplay = $invoice->period;
                                    if ($invoice->is_bulk_payment && $invoice->bulk_coverage_start && $invoice->bulk_coverage_end) {
                                        $periodDisplay = Carbon::parse($invoice->bulk_coverage_start . '-01')->format('M Y')
                                            . ' – ' .
                                            Carbon::parse($invoice->bulk_coverage_end . '-01')->format('M Y');
                                    } elseif (preg_match('/^\d{4}-\d{2}$/', (string) $invoice->period)) {
                                        $periodDisplay = Carbon::parse($invoice->period . '-01')->format('F Y');
                                    }
                                @endphp
                                <p class="font-medium text-primary">{{ $periodDisplay }}</p>
                                @if($invoice->is_bulk_payment)
                                    <p class="text-xs text-secondary">
                                        {{ $invoice->childInvoices->count() ?? 0 }} invoices consolidated
                                    </p>
                                @endif
                            </td>

                            <td class="table-td">
                                <p class="font-medium text-primary">{{ $settings->formatAmount($invoice->total_amount) }}</p>
                                @if($invoice->penalty_amount > 0)
                                    <p class="text-xs text-danger">+{{ $settings->formatAmount($invoice->penalty_amount) }} penalty</p>
                                @endif
                            </td>

                            <td class="table-td">
                                <p class="font-medium text-primary">{{ Carbon::parse($invoice->due_date)->format('M d, Y') }}</p>
                                @if(!in_array($invoice->status, ['paid', 'consolidated', 'cancelled']) && !$isCoveredByBulk)
                                    @php
                                        $daysDiff = Carbon::parse($invoice->due_date)->diffInDays(now(), false);
                                    @endphp
                                    <p class="text-xs text-secondary">
                                        @if($daysDiff > 0)
                                            <span class="text-danger">{{ $daysDiff }} days overdue</span>
                                        @elseif($daysDiff == 0)
                                            <span class="text-warning">Due today</span>
                                        @else
                                            In {{ abs($daysDiff) }} days
                                        @endif
                                    </p>
                                @endif
                            </td>

                            <td class="table-td">
                                @php
                                    $statusColors = [
                                        'paid'         => ['bg' => 'success', 'icon' => 'check-circle'],
                                        'pending'      => ['bg' => 'warning', 'icon' => 'clock'],
                                        'processing'   => ['bg' => 'warning', 'icon' => 'spinner'],
                                        'overdue'      => ['bg' => 'danger',  'icon' => 'exclamation-triangle'],
                                        'consolidated' => ['bg' => 'info',    'icon' => 'link'],
                                    ];
                                    $statusConfig = $statusColors[$invoice->status] ?? ['bg' => 'secondary', 'icon' => 'question-circle'];
                                @endphp
                                @if($isCoveredByBulk && $invoice->status !== 'paid')
                                    <span class="pill pill-success">
                                        <i class="fas fa-shield-alt mr-1"></i> Covered
                                    </span>
                                @else
                                    <span class="pill pill-{{ $statusConfig['bg'] }}">
                                        <i class="fas fa-{{ $statusConfig['icon'] }} mr-1"></i>
                                        {{ $invoice->status === 'consolidated' ? 'In Bulk' : ucfirst($invoice->status) }}
                                    </span>
                                @endif
                            </td>

                            <td class="table-td">
                                <div class="flex flex-wrap gap-1">
                                    <a href="{{ route('landlord.invoices.show', $invoice->id) }}" class="action-btn action-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    {{-- Pay / retry — regular --}}
                                    @if($isPayable)
                                        <a href="{{ route('landlord.invoices.payment.form', $invoice->property_id) }}?invoice_ids[]={{ $invoice->id }}&payment_type=invoices&pre_selected=true"
                                           class="action-btn {{ $isProcessing ? 'action-warning' : 'action-success' }}"
                                           title="{{ $isProcessing ? 'Retry Payment' : 'Pay Now' }}">
                                            <i class="fas fa-{{ $isProcessing ? 'sync-alt' : 'credit-card' }}"></i>
                                        </a>
                                    @endif

                                    {{-- Pay — bulk invoice --}}
                                    @if($invoice->is_bulk_payment && in_array($invoice->status, ['pending','overdue','processing']))
                                        <a href="{{ route('landlord.invoices.payment.form', $invoice->property_id) }}?invoice_ids[]={{ $invoice->id }}&payment_type=bulk&pre_selected=true"
                                           class="action-btn {{ $isProcessing ? 'action-warning' : 'action-success' }}"
                                           title="{{ $isProcessing ? 'Retry Bulk Payment' : 'Pay Bulk Invoice' }}">
                                            <i class="fas fa-{{ $isProcessing ? 'sync-alt' : 'layer-group' }}"></i>
                                        </a>
                                    @endif

                                    <button type="button" onclick="exportSinglePDF({{ $invoice->id }})" class="action-btn action-danger" title="Export PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </button>

                                    <a href="{{ route('landlord.invoices.print', $invoice->id) }}" target="_blank"
                                       class="action-btn action-secondary" title="Print">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $payableCount > 0 ? 8 : 7 }}" class="p-8 text-center">
                                <div class="flex flex-col items-center justify-center text-secondary">
                                    <i class="fas fa-file-invoice text-4xl mb-4 opacity-50"></i>
                                    <p class="text-lg font-medium mb-2">No invoices found</p>
                                    <p class="text-sm">You don't have any invoices for your properties yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($invoices->hasPages())
        <div class="flex justify-center mt-6">
            {{ $invoices->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>

{{-- ============================================================
     MODALS
============================================================ --}}
@include('landlord.invoices.partials.modals')

{{-- Hidden forms for exports --}}
<form id="currentPageExportForm" method="GET" action="{{ route('landlord.invoices.export-current-page') }}" target="_blank">
    @foreach(request()->except(['_token','page']) as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
</form>

<form id="allInvoicesExportForm" method="GET" action="{{ route('landlord.invoices.export-all') }}" target="_blank">
    @foreach(request()->except(['_token','page']) as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
</form>

<form id="bulkExportForm" method="POST" action="{{ route('landlord.invoices.bulk-export') }}" target="_blank">
    @csrf
    <input type="hidden" name="invoice_ids" id="bulkInvoiceIds">
</form>
@endsection

@section('scripts')
<script>
'use strict';

const APP_CONFIG = {
    currencySymbol:   @json($currencySymbol),
    decimalPlaces:    {{ $decimalPlaces }},
    currencyPosition: @json($currencyPosition),
    csrfToken:        @json(csrf_token()),
    routes: {
        outstandingInvoices: @json(route('landlord.outstanding-invoices')),
        exportSinglePdf:     (id) => @json(url('/landlord/invoices')) + `/${id}/export-pdf`,
    },
};

const selectedInvoices = new Set();

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

    // Auto-dismiss coverage banners after 15s
    document.querySelectorAll('[data-coverage-banner]').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, 15000);
    });

    initializeCheckboxes();

    // Refresh stats on load
    setTimeout(loadOutstandingInvoices, 2000);

    // Close modals on backdrop click
    document.querySelectorAll('.modal-backdrop').forEach(modal => {
        modal.addEventListener('click', e => {
            if (e.target === modal) closeAllModals();
        });
    });

    // Escape closes modals
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeAllModals();
    });
});

// ── Checkbox handling (delegated) ──
function initializeCheckboxes() {
    const tbody = document.getElementById('invoicesTableBody');
    const selectAll = document.getElementById('selectAll');
    if (!tbody) return;

    tbody.addEventListener('change', e => {
        const cb = e.target;
        if (!cb.classList.contains('invoice-checkbox')) return;
        const id = parseInt(cb.value, 10);
        cb.checked ? selectedInvoices.add(id) : selectedInvoices.delete(id);
        updateSelectionSummary();
    });

    selectAll?.addEventListener('change', () => {
        const checked = selectAll.checked;
        document.querySelectorAll('.invoice-checkbox').forEach(cb => {
            cb.checked = checked;
            const id = parseInt(cb.value, 10);
            checked ? selectedInvoices.add(id) : selectedInvoices.delete(id);
        });
        updateSelectionSummary();
    });
}

function updateSelectionSummary() {
    const all = document.querySelectorAll('.invoice-checkbox');
    const checked = document.querySelectorAll('.invoice-checkbox:checked');
    const count = checked.length;

    document.getElementById('selectedCount').textContent = count;

    let total = 0;
    checked.forEach(cb => { total += parseFloat(cb.dataset.amount) || 0; });
    document.getElementById('selectedTotal').textContent = formatAmount(total);

    const summary = document.getElementById('selectedInvoicesSummary');
    summary?.classList.toggle('hidden', count === 0);

    const bulkInfo = document.getElementById('bulkPaymentInfo');
    bulkInfo?.classList.toggle('hidden', count < 2);

    const selectAllEl = document.getElementById('selectAll');
    if (selectAllEl) {
        selectAllEl.checked = count > 0 && count === all.length;
        selectAllEl.indeterminate = count > 0 && count < all.length;
    }

    const modalCount = document.getElementById('modalSelectedCount');
    if (modalCount) modalCount.textContent = count;
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.invoice-checkbox:checked')).map(cb => cb.value);
}

// ── Payment selection ──
function processSelectedInvoices(paymentType = 'invoices') {
    const ids = getSelectedIds();
    if (ids.length === 0) return alert('Please select at least one invoice to pay.');

    const firstCb = document.querySelector('.invoice-checkbox:checked');
    const firstRow = firstCb?.closest('.invoice-row');
    const propertyId = firstRow?.dataset.propertyId;
    if (!propertyId) return alert('Unable to determine property.');

    const sameProperty = Array.from(document.querySelectorAll('.invoice-checkbox:checked'))
        .every(cb => cb.closest('.invoice-row')?.dataset.propertyId === propertyId);
    if (!sameProperty) return alert('Please select invoices from the same property only.');

    if (Array.from(document.querySelectorAll('.invoice-checkbox:checked'))
        .some(cb => cb.closest('.invoice-row')?.dataset.hasParent === 'true')) {
        return alert('Cannot select invoices already part of a bulk payment.');
    }

    if (Array.from(document.querySelectorAll('.invoice-checkbox:checked'))
        .some(cb => cb.closest('.invoice-row')?.dataset.coveredByBulk === 'true')) {
        return alert('Cannot select invoices covered by an existing bulk payment.');
    }

    const base = @json(route('landlord.invoices.payment.form', ':propertyId')).replace(':propertyId', propertyId);
    const url  = new URL(base, window.location.origin);
    ids.forEach(id => url.searchParams.append('invoice_ids[]', id));
    url.searchParams.append('payment_type', paymentType);
    url.searchParams.append('pre_selected', 'true');
    window.location.href = url.toString();
}

// ── Modals ──
function openModal(id)  { document.getElementById(id)?.classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }
function closeAllModals() {
    document.querySelectorAll('.modal-backdrop').forEach(m => m.classList.add('hidden'));
}

function openExportModal()  { openModal('exportModal'); }
function closeExportModal() { closeModal('exportModal'); }

function showCoverageSummary()       { openModal('coverageSummaryModal'); }
function closeCoverageSummaryModal() { closeModal('coverageSummaryModal'); }

function closeNoPaymentMethodsModal() { closeModal('noPaymentMethodsModal'); }

// ── Exports ──
function showLoadingModal() { openModal('pdfLoadingModal'); }
function hideLoadingModal() { closeModal('pdfLoadingModal'); }

function exportCurrentPagePDF() {
    closeExportModal();
    showLoadingModal();
    document.getElementById('currentPageExportForm')?.submit();
    setTimeout(hideLoadingModal, 2000);
}

function exportAllInvoices() {
    closeExportModal();
    showLoadingModal();
    document.getElementById('allInvoicesExportForm')?.submit();
    setTimeout(hideLoadingModal, 3000);
}

function exportSinglePDF(invoiceId) {
    showLoadingModal();
    window.open(APP_CONFIG.routes.exportSinglePdf(invoiceId), '_blank');
    setTimeout(hideLoadingModal, 2000);
}

function bulkExport() {
    const ids = getSelectedIds();
    if (ids.length === 0) return alert('Please select at least one invoice to export.');
    showLoadingModal();
    const input = document.getElementById('bulkInvoiceIds');
    if (input) input.value = ids.join(',');
    document.getElementById('bulkExportForm')?.submit();
    setTimeout(hideLoadingModal, 3000);
}

function openBulkExportFromModal() {
    closeExportModal();
    bulkExport();
}

// ── Stats refresh ──
function loadOutstandingInvoices() {
    const loading = document.getElementById('loadingIndicator');
    loading?.classList.remove('hidden');

    fetch(APP_CONFIG.routes.outstandingInvoices, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
    })
    .then(r => r.ok ? r.json() : Promise.reject(r.statusText))
    .then(data => {
        if (data.success) updateStatistics(data.data);
    })
    .catch(err => console.warn('Outstanding invoices refresh failed', err))
    .finally(() => loading?.classList.add('hidden'));
}

function updateStatistics(data) {
    if (!data) return;

    const statCards = document.querySelectorAll('.stat-card');
    if (statCards[0] && data.total_due !== undefined) {
        statCards[0].querySelector('.text-2xl').textContent = formatAmount(data.total_due);
    }
    if (statCards[1] && data.outstanding_count !== undefined) {
        statCards[1].querySelector('.text-2xl').textContent = data.outstanding_count;
    }
}
</script>
@endsection