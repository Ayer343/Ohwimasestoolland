{{-- resources/views/financials/index.blade.php --}}
@php
    use App\Models\PropertyUnitInvoice;

    // ========== AUTH & ROLE ==========
    $user = auth()->user();
    $isLandlord   = $user->isLandlord()   || $user->hasRole('landlord');
    $isAdmin      = $user->isAdmin()      || $user->hasRole('admin');
    $isSuperAdmin = $user->isSuperAdmin() || $user->hasRole('super-admin');
    $isTenant     = $user->isTenant()     || $user->hasRole('tenant');
    $isDeveloper  = $user->isDeveloper()  || $user->hasRole('developer');

    // ========== LAYOUT & ROUTE PREFIX ==========
    if ($isLandlord) {
        $layout = 'layouts.landlord';
        $routePrefix = 'landlord';
    } elseif ($isAdmin || $isSuperAdmin) {
        $layout = 'layouts.app';
        $routePrefix = 'admin';
    } elseif ($isTenant) {
        $layout = 'layouts.tenant';
        $routePrefix = 'tenant';
    } elseif ($isDeveloper) {
        $layout = 'layouts.app';
        $routePrefix = 'developer';
    } else {
        $layout = 'layouts.app';
        $routePrefix = '';
    }

    // ========== PAGE TITLE ==========
    $pageTitle = 'Financial Overview - ' . $unit->unit_number;
    if ($isTenant) {
        $pageTitle = 'My Financial Overview';
    }

    // ========== ✅ INVOICE: SOURCE OF TRUTH ==========
    $allInvoices = $invoices ?? $unit->invoices ?? collect();

    // ========== ✅ INVOICE: SUMMARY ==========
    $invoiceSummary = $invoiceSummary ?? [
        'total_invoiced' => $allInvoices->sum('amount'),
        'total_paid'     => $allInvoices->sum('amount_paid'),
        'outstanding'    => $allInvoices
            ->whereIn('status', [
                PropertyUnitInvoice::STATUS_PENDING,
                PropertyUnitInvoice::STATUS_PARTIAL,
                PropertyUnitInvoice::STATUS_OVERDUE,
            ])
            ->sum(fn ($i) => max(0, $i->amount - $i->amount_paid)),
        'overdue_count'  => $allInvoices->where('status', PropertyUnitInvoice::STATUS_OVERDUE)->count(),
        'paid_count'     => $allInvoices->where('status', PropertyUnitInvoice::STATUS_PAID)->count(),
    ];

    // ========== ✅ INVOICE: COUNTS ==========
    $totalInvoices     = $allInvoices->count();
    $paidInvoices      = $invoiceSummary['paid_count'];
    $pendingInvoices   = $allInvoices->where('status', PropertyUnitInvoice::STATUS_PENDING)->count();
    $partialInvoices   = $allInvoices->where('status', PropertyUnitInvoice::STATUS_PARTIAL)->count();
    $overdueInvoices   = $invoiceSummary['overdue_count'];
    $voidInvoices      = $allInvoices->where('status', PropertyUnitInvoice::STATUS_VOID)->count();

    // ========== PERCENTAGES ==========
    $paidPercentage    = $totalInvoices > 0 ? ($paidInvoices    / $totalInvoices) * 100 : 0;
    $overduePercentage = $totalInvoices > 0 ? ($overdueInvoices / $totalInvoices) * 100 : 0;
    $pendingPercentage = $totalInvoices > 0 ? ($pendingInvoices / $totalInvoices) * 100 : 0;

    // ========== ✅ GHANA: CURRENCY & GOVERNING LAW ==========
    $currencySymbol = config('leases.ghana.currency.symbol', 'GH₵');
    $governingLaw   = config('leases.ghana.governing_law', 'Rent Act, 1963 (Act 220)');

    // ========== ✅ INVOICE: CATEGORIES ==========
    $advanceInvoices = $allInvoices->where('invoice_type', PropertyUnitInvoice::TYPE_ADVANCE_RENT);
    $monthlyInvoices = $allInvoices->where('invoice_type', PropertyUnitInvoice::TYPE_MONTHLY_RENT);
    $depositInvoices = $allInvoices->where('invoice_type', PropertyUnitInvoice::TYPE_SECURITY_DEPOSIT);
    $lateFeeInvoices = $allInvoices->where('invoice_type', PropertyUnitInvoice::TYPE_LATE_FEE);

    // ========== ✅ GHANA: LEASE PHASE ==========
    $currentLease   = $unit->currentLease;
    $currentPhase   = $currentLease?->current_phase;
    $hasAdvanceRent = (bool) ($currentLease && $currentLease->has_advance_rent);

    // ========== ✅ NEW: ROUTE DISCOVERY HELPERS ==========
    // Try role-prefixed first, then generic. Returns null if none exist.
    $findRoute = function (array $candidates) {
        foreach ($candidates as $name) {
            if ($name && Route::has($name)) return $name;
        }
        return null;
    };

    // Financials back-link
    $showUnitRoute = $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.show" : null,
        'property-units.show',
    ]));

    // Export CSV
    $exportRouteName = $findRoute(array_filter([
        $isTenant    ? 'tenant.property-units.export-financial-report'    : null,
        $isLandlord  ? 'landlord.property-units.export-financial-report'  : null,
        ($isAdmin || $isSuperAdmin) ? 'admin.property-units.export-financial-report' : null,
        'property-units.export-financial-report',
    ]));

    // Generate invoice
    $generateInvoiceRouteName = !$isTenant ? $findRoute(array_filter([
        $isLandlord ? 'landlord.property-units.generate-invoice' : null,
        ($isAdmin || $isSuperAdmin) ? 'admin.property-units.generate-invoice' : null,
        'property-units.generate-invoice',
    ])) : null;

    // ✅ NEW: Invoice action routes
    $showInvoiceRouteName = $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.show-invoice" : null,
        'property-units.show-invoice',
    ]));

    $recordPaymentRouteName = !$isTenant ? $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.record-payment" : null,
        'property-units.record-payment',
    ])) : null;

    $voidInvoiceRouteName = !$isTenant ? $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.void-invoice" : null,
        'property-units.void-invoice',
    ])) : null;

    $applyLateFeeRouteName = !$isTenant ? $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.apply-late-fee" : null,
        'property-units.apply-late-fee',
    ])) : null;

    $sendInvoiceRouteName = !$isTenant ? $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.send-invoice" : null,
        'property-units.send-invoice',
    ])) : null;

    $recalculateRouteName = !$isTenant ? $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.recalculate-invoice" : null,
        'property-units.recalculate-invoice',
    ])) : null;

    $pdfRouteName = $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.invoice-pdf" : null,
        'property-units.invoice-pdf',
    ]));

    $bulkVoidRouteName = !$isTenant ? $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.bulk-void" : null,
        'property-units.bulk-void',
    ])) : null;

    $markOverdueRouteName = !$isTenant ? $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.mark-overdue" : null,
        'property-units.mark-overdue',
    ])) : null;

    // ========== ✅ GHANA: Next Payment ==========
    $nextPaymentDue    = $currentLease?->next_payment_due_date;
    $nextPaymentAmount = $currentLease?->next_payment_amount;

    // ========== ✅ INVOICE: Recent payments ==========
    $recentPayments = $allInvoices
        ->where('status', PropertyUnitInvoice::STATUS_PAID)
        ->sortByDesc('paid_at')
        ->take(3);
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>
                        @if($isTenant)
                            My Financial Overview
                        @else
                            Financial Overview - {{ $unit->unit_number }}
                        @endif

                        {{-- ✅ GHANA: Phase badge --}}
                        @if($currentLease && $hasAdvanceRent)
                            @if($currentPhase === 'advance')
                                <span class="ml-2 px-2 py-0.5 text-xs rounded-full badge-primary">Advance Phase</span>
                            @elseif($currentPhase === 'monthly')
                                <span class="ml-2 px-2 py-0.5 text-xs rounded-full badge-success">Monthly Phase</span>
                            @endif
                        @endif
                    </h2>
                    <div class="text-sm flex flex-wrap items-center gap-x-2 mt-1" style="color: var(--text-secondary);">
                        <span>
                            <i class="fas fa-info-circle mr-2"></i>
                            @if($isTenant)
                                Track your payment history and financial details
                            @else
                                Manage all financial transactions for this unit
                            @endif
                        </span>
                        <span>•</span>
                        <span><i class="fas fa-building mr-1"></i>{{ $unit->property->property_name }}</span>
                        @if(!$isTenant)
                            <span>•</span>
                            <span><i class="fas fa-user mr-1"></i>{{ $unit->tenant->name ?? 'No Tenant' }}</span>
                        @endif
                        <span>•</span>
                        <span><i class="fas fa-money-bill-wave mr-1"></i>Currency: {{ $currencySymbol }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                </div>
                @if($showUnitRoute)
                <a href="{{ route($showUnitRoute, $unit->id) }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Unit
                </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Session Messages -->
    @foreach(['success' => ['bg-green-100','border-green-400','text-green-700','check-circle','Success!'],
              'error'   => ['bg-red-100','border-red-400','text-red-700','exclamation-circle','Error!'],
              'info'    => ['bg-blue-100','border-blue-400','text-blue-700','info-circle','Info!']] as $key => $cfg)
        @if(session($key))
        <div class="{{ $cfg[0] }} border {{ $cfg[1] }} {{ $cfg[2] }} px-4 py-3 rounded relative" role="alert">
            <div class="flex items-center">
                <i class="fas fa-{{ $cfg[3] }} mr-2"></i>
                <span class="font-bold">{{ $cfg[4] }}</span>
                <span class="ml-2">{{ session($key) }}</span>
            </div>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @endif
    @endforeach

    <!-- Navigation Card -->
    <div class="card">
        <div class="p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center space-x-3 flex-wrap">
                    @if(!$isTenant)
                        @if($generateInvoiceRouteName)
                        <button onclick="showGenerateInvoiceModal()"
                                class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-white btn-primary">
                            <i class="fas fa-file-invoice-dollar mr-2"></i> Generate Invoice
                        </button>
                        @else
                        <button disabled
                                class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-white btn-primary opacity-50 cursor-not-allowed">
                            <i class="fas fa-file-invoice-dollar mr-2"></i> Generate Invoice
                        </button>
                        @endif

                        {{-- ✅ NEW: Mark Overdue button --}}
                        @if($markOverdueRouteName && $pendingInvoices + $partialInvoices > 0)
                        <form method="POST" action="{{ route($markOverdueRouteName, $unit->id) }}" class="inline">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 rounded-lg font-medium"
                                    style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);"
                                    onclick="return confirm('Mark all past-due pending invoices as overdue?');">
                                <i class="fas fa-exclamation-triangle mr-2"></i> Mark Overdue
                            </button>
                        </form>
                        @endif
                    @endif

                    @if($exportRouteName)
                    <a href="{{ route($exportRouteName, $unit->id) }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg font-medium btn-secondary">
                        <i class="fas fa-file-export mr-2"></i> Export Report
                    </a>
                    @else
                    <button disabled
                            class="inline-flex items-center px-4 py-2 rounded-lg font-medium btn-secondary opacity-50 cursor-not-allowed">
                        <i class="fas fa-file-export mr-2"></i> Export Report
                    </button>
                    @endif

                    @if($invoiceSummary['outstanding'] > 0)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium badge-danger animate-pulse">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        {{ $currencySymbol }} {{ number_format($invoiceSummary['outstanding'], 2) }} Outstanding
                    </span>
                    @endif
                </div>

                <div class="flex items-center space-x-3">
                    <span class="text-xs px-3 py-1 rounded-full"
                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-filter mr-1"></i>
                        {{ request()->has('status') ? ucfirst(request('status')) . ' Only' : 'All Invoices' }}
                    </span>

                    <div class="relative">
                        <select class="financial-custom-dropdown text-xs pl-3 pr-8 py-1 rounded-lg appearance-none">
                            <option value="{{ $currencySymbol }}" selected>{{ $currencySymbol }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Collected -->
        <div class="card stat-card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-money-bill-wave text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Collected</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $currencySymbol }} {{ number_format($invoiceSummary['total_paid'], 2) }}
                    </p>
                </div>
                @if($invoiceSummary['total_paid'] > 0)
                <div class="text-right">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-success">
                        <i class="fas fa-arrow-up mr-1"></i> {{ round($paidPercentage, 1) }}%
                    </span>
                </div>
                @endif
            </div>
        </div>

        <!-- Outstanding -->
        <div class="card stat-card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-exclamation-circle text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Outstanding Balance</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $currencySymbol }} {{ number_format($invoiceSummary['outstanding'], 2) }}
                    </p>
                </div>
                @if($invoiceSummary['outstanding'] > 0)
                <div class="text-right">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-danger">
                        <i class="fas fa-clock mr-1"></i> Needs Attention
                    </span>
                </div>
                @endif
            </div>
        </div>

        <!-- ✅ GHANA: Next Payment -->
        <div class="card stat-card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-calendar-check text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Next Payment</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        @if($nextPaymentDue)
                            {{ $nextPaymentDue->format('M d') }}
                        @else
                            —
                        @endif
                    </p>
                    @if($nextPaymentAmount)
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            {{ $currencySymbol }} {{ number_format($nextPaymentAmount, 2) }}
                        </p>
                    @endif
                </div>
                @if($nextPaymentDue)
                <div class="text-right">
                    @php $daysUntilDue = now()->diffInDays($nextPaymentDue, false); @endphp
                    @if($daysUntilDue >= 0 && $daysUntilDue <= 7)
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-warning">
                            <i class="fas fa-clock mr-1"></i> {{ $daysUntilDue }}d
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-success">
                            <i class="fas fa-check mr-1"></i> {{ abs($daysUntilDue) }}d
                        </span>
                    @endif
                </div>
                @endif
            </div>
        </div>

        <!-- Overdue Count -->
        <div class="card stat-card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-exclamation-triangle text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Overdue Invoices</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $overdueInvoices }}</p>
                </div>
                @if($overdueInvoices > 0)
                <div class="text-right">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-danger">
                        Action
                    </span>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- ✅ INVOICE: Status counters -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="card"><div class="p-4 text-center">
            <div class="text-2xl font-bold mb-1" style="color: var(--text-primary);">{{ $totalInvoices }}</div>
            <div class="text-sm" style="color: var(--text-secondary);">Total</div>
        </div></div>
        <div class="card"><div class="p-4 text-center">
            <div class="text-2xl font-bold mb-1" style="color: var(--success);">{{ $paidInvoices }}</div>
            <div class="text-sm" style="color: var(--text-secondary);">Paid</div>
        </div></div>
        <div class="card"><div class="p-4 text-center">
            <div class="text-2xl font-bold mb-1" style="color: var(--warning);">{{ $pendingInvoices }}</div>
            <div class="text-sm" style="color: var(--text-secondary);">Pending</div>
        </div></div>
        <div class="card"><div class="p-4 text-center">
            <div class="text-2xl font-bold mb-1" style="color: var(--info);">{{ $partialInvoices }}</div>
            <div class="text-sm" style="color: var(--text-secondary);">Partial</div>
        </div></div>
        <div class="card"><div class="p-4 text-center">
            <div class="text-2xl font-bold mb-1" style="color: var(--danger);">{{ $overdueInvoices }}</div>
            <div class="text-sm" style="color: var(--text-secondary);">Overdue</div>
        </div></div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Invoices Table -->
        <div class="lg:col-span-2">
            <div class="card p-6">

                {{-- ✅ NEW: Bulk actions bar --}}
                @if($bulkVoidRouteName && $totalInvoices > 0)
                <form id="bulkVoidForm" method="POST" action="{{ route($bulkVoidRouteName, $unit->id) }}" class="hidden">
                    @csrf
                    <div id="bulkVoidInputs"></div>
                </form>
                <div id="bulkActions" class="hidden mb-4 p-3 rounded-lg flex items-center justify-between"
                     style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                    <div class="text-sm" style="color: var(--text-primary);">
                        <i class="fas fa-check-square mr-1" style="color: var(--danger);"></i>
                        <span id="selectedCount">0</span> selected
                    </div>
                    <button type="button" onclick="submitBulkVoid()"
                            class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium text-white"
                            style="background-color: var(--danger);">
                        <i class="fas fa-ban mr-1"></i> Void Selected
                    </button>
                </div>
                @endif

                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-3">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Invoices ({{ $totalInvoices }})
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            @if($overdueInvoices > 0)
                                <span class="text-red-500 font-medium">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    {{ $overdueInvoices }} overdue invoice(s)
                                </span>
                            @else
                                All invoices are up to date
                            @endif
                        </p>
                    </div>

                    <div class="flex items-center space-x-3">
                        <div class="relative">
                            <input type="text"
                                   placeholder="Search invoices..."
                                   class="financial-custom-input text-sm pl-3 pr-8 py-1 rounded-lg"
                                   onkeyup="searchInvoices(this.value)">
                            <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <i class="fas fa-search" style="color: var(--text-secondary);"></i>
                            </div>
                        </div>

                        <div class="relative">
                            <select onchange="filterInvoices(this.value)"
                                    class="financial-custom-dropdown text-sm pl-3 pr-8 py-1 rounded-lg appearance-none">
                                <option value="">All Status</option>
                                <option value="paid">Paid</option>
                                <option value="pending">Pending</option>
                                <option value="partial">Partial</option>
                                <option value="overdue">Overdue</option>
                                <option value="void">Void</option>
                            </select>
                        </div>
                    </div>
                </div>

                @if($allInvoices->isEmpty())
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                             style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-file-invoice text-2xl" style="color: var(--primary);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Invoices Found</h4>
                        <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                            @if($isTenant)
                                You have no invoices yet. Invoices will appear here when generated by your landlord.
                            @else
                                This unit has no invoices. Generate your first invoice using the button above.
                            @endif
                        </p>
                        @if(!$isTenant && $generateInvoiceRouteName)
                        <button onclick="showGenerateInvoiceModal()"
                                class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-file-invoice-dollar mr-2"></i> Generate First Invoice
                        </button>
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    @if($bulkVoidRouteName)
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary); width: 40px;">
                                        <input type="checkbox"
                                               id="selectAllInvoices"
                                               class="invoice-checkbox"
                                               onchange="toggleSelectAll(this.checked)">
                                    </th>
                                    @endif
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Reference</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Description</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Due Date</th>
                                    <th class="text-right p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Amount</th>
                                    <th class="text-right p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Balance</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Status</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($allInvoices as $invoice)
                                @php
                                    $balance = max(0, $invoice->amount - $invoice->amount_paid);
                                    $statusMap = [
                                        'pending' => ['badge-warning',  'clock',                'Pending'],
                                        'partial' => ['badge-info',     'adjust',               'Partial'],
                                        'paid'    => ['badge-success',  'check-circle',         'Paid'],
                                        'overdue' => ['badge-danger',   'exclamation-triangle', 'Overdue'],
                                        'void'    => ['badge-secondary','ban',                  'Void'],
                                    ];
                                    [$statusClass, $statusIcon, $statusLabel] = $statusMap[$invoice->status] ?? ['badge-secondary', 'circle', ucfirst($invoice->status)];

                                    // Determines which action buttons to render
                                    $canPay       = !$isTenant && in_array($invoice->status, ['pending','partial','overdue']);
                                    $canVoid      = !$isTenant && in_array($invoice->status, ['pending','partial','overdue']);
                                    $canLateFee   = !$isTenant && $invoice->status === 'overdue';
                                    $canSend      = !$isTenant && in_array($invoice->status, ['pending','partial','overdue']);
                                    $canRecalc    = !$isTenant && $invoice->status !== 'void';
                                    $canPdf       = (bool) $pdfRouteName;
                                    $canTenantPay = $isTenant && $invoice->status === 'pending';
                                @endphp
                                <tr class="invoice-row" data-status="{{ $invoice->status }}">
                                    @if($bulkVoidRouteName)
                                    <td class="p-3">
                                        @if(in_array($invoice->status, ['pending','partial','overdue']))
                                        <input type="checkbox"
                                               class="invoice-checkbox invoice-selector"
                                               value="{{ $invoice->id }}"
                                               onchange="updateBulkActions()">
                                        @endif
                                    </td>
                                    @endif

                                    <td class="p-3">
                                        <div class="text-sm font-mono font-medium" style="color: var(--text-primary);">
                                            {{ $invoice->reference }}
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            {{ ucfirst(str_replace('_', ' ', $invoice->invoice_type)) }}
                                        </div>
                                    </td>

                                    <td class="p-3">
                                        <div class="text-sm font-medium" style="color: var(--text-primary);">
                                            {{ $invoice->description }}
                                        </div>
                                        @if($invoice->period_start && $invoice->period_end)
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-calendar-alt mr-1"></i>
                                            {{ $invoice->period_start->format('M d') }} -
                                            {{ $invoice->period_end->format('M d') }}
                                        </div>
                                        @endif
                                    </td>

                                    <td class="p-3 whitespace-nowrap">
                                        <div class="text-sm" style="color: var(--text-primary);">
                                            {{ optional($invoice->due_date)->format('M d, Y') ?? '—' }}
                                        </div>
                                        @php $daysUntilDue = $invoice->due_date ? now()->diffInDays($invoice->due_date, false) : null; @endphp
                                        @if($daysUntilDue !== null && $invoice->status === 'pending' && $daysUntilDue >= 0)
                                            <div class="text-xs" style="color: var(--text-secondary);">{{ $daysUntilDue }} days</div>
                                        @elseif($invoice->status === 'overdue')
                                            <div class="text-xs text-red-500">Overdue</div>
                                        @endif
                                    </td>

                                    <td class="p-3 whitespace-nowrap text-right">
                                        <div class="text-sm font-medium" style="color: var(--text-primary);">
                                            {{ $currencySymbol }} {{ number_format($invoice->amount, 2) }}
                                        </div>
                                        @if($invoice->amount_paid > 0)
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            Paid: {{ $currencySymbol }} {{ number_format($invoice->amount_paid, 2) }}
                                        </div>
                                        @endif
                                    </td>

                                    <td class="p-3 whitespace-nowrap text-right">
                                        <div class="text-sm font-bold"
                                             style="color: {{ $balance > 0 ? 'var(--danger)' : 'var(--success)' }};">
                                            {{ $currencySymbol }} {{ number_format($balance, 2) }}
                                        </div>
                                    </td>

                                    <td class="p-3 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusClass }}">
                                            <i class="fas fa-{{ $statusIcon }} mr-1"></i> {{ $statusLabel }}
                                        </span>
                                    </td>

                                    <td class="p-3 whitespace-nowrap">
                                        <div class="action-buttons">
                                            {{-- View --}}
                                            @if($showInvoiceRouteName)
                                            <a href="{{ route($showInvoiceRouteName, [$unit->id, $invoice->id]) }}"
                                               class="action-btn view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @endif

                                            {{-- Record payment (staff) --}}
                                            @if($canPay && $recordPaymentRouteName)
                                            <button type="button"
                                                    onclick="openPaymentModal({{ $invoice->id }}, '{{ $invoice->reference }}', {{ $balance }})"
                                                    class="action-btn pay" title="Record Payment">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </button>
                                            @endif

                                            {{-- Tenant makes payment --}}
                                            @if($canTenantPay)
                                            <button type="button"
                                                    onclick="makePayment({{ $invoice->id }})"
                                                    class="action-btn pay" title="Make Payment">
                                                <i class="fas fa-credit-card"></i>
                                            </button>
                                            @endif

                                            {{-- PDF download --}}
                                            @if($canPdf)
                                            <a href="{{ route($pdfRouteName, [$unit->id, $invoice->id]) }}"
                                               target="_blank"
                                               class="action-btn view" title="Download PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                            @endif

                                            {{-- Apply late fee --}}
                                            @if($canLateFee && $applyLateFeeRouteName)
                                            <button type="button"
                                                    onclick="confirmApplyLateFee({{ $invoice->id }}, '{{ $invoice->reference }}')"
                                                    class="action-btn pay"
                                                    style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border-color: rgba(var(--warning-rgb), 0.3);"
                                                    title="Apply Late Fee">
                                                <i class="fas fa-clock"></i>
                                            </button>
                                            @endif

                                            {{-- Send to tenant --}}
                                            @if($canSend && $sendInvoiceRouteName)
                                            <form method="POST"
                                                  action="{{ route($sendInvoiceRouteName, [$unit->id, $invoice->id]) }}"
                                                  class="inline">
                                                @csrf
                                                <button type="submit"
                                                        class="action-btn"
                                                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border-color: rgba(var(--info-rgb), 0.3);"
                                                        title="Send to Tenant"
                                                        onclick="return confirm('Send this invoice to the tenant?');">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            </form>
                                            @endif

                                            {{-- Void --}}
                                            @if($canVoid && $voidInvoiceRouteName)
                                            <button type="button"
                                                    onclick="openVoidModal({{ $invoice->id }}, '{{ $invoice->reference }}')"
                                                    class="action-btn"
                                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border-color: rgba(var(--danger-rgb), 0.3);"
                                                    title="Void Invoice">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                            @endif

                                            {{-- Recalculate --}}
                                            @if($canRecalc && $recalculateRouteName)
                                            <form method="POST"
                                                  action="{{ route($recalculateRouteName, [$unit->id, $invoice->id]) }}"
                                                  class="inline">
                                                @csrf
                                                <button type="submit"
                                                        class="action-btn"
                                                        style="background-color: rgba(0, 0, 0, 0.04); color: var(--text-secondary); border-color: var(--border-color);"
                                                        title="Recalculate Status"
                                                        onclick="return confirm('Recalculate this invoice\'s status from its paid amount?');">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                            </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if(method_exists($allInvoices, 'links'))
                    <div class="flex flex-col md:flex-row justify-between items-center pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                        <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                            Showing {{ $allInvoices->firstItem() }} to {{ $allInvoices->lastItem() }} of {{ $allInvoices->total() }} invoices
                        </div>
                        <div class="pagination">
                            {{ $allInvoices->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                    @endif
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <!-- Quick Stats -->
            <div class="card mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i> Quick Stats
                    </h3>

                    <div class="space-y-4">
                        <!-- Paid vs Outstanding -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm" style="color: var(--text-secondary);">Paid vs Outstanding</span>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $currencySymbol }} {{ number_format($invoiceSummary['total_paid'], 2) }} /
                                    {{ $currencySymbol }} {{ number_format($invoiceSummary['outstanding'], 2) }}
                                </span>
                            </div>
                            @php
                                $paidRatio = $invoiceSummary['total_invoiced'] > 0
                                    ? ($invoiceSummary['total_paid'] / $invoiceSummary['total_invoiced']) * 100
                                    : 0;
                            @endphp
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: {{ round($paidRatio, 1) }}%"></div>
                            </div>
                            <div class="flex justify-between text-xs mt-1">
                                <span class="text-green-600">Collected {{ round($paidRatio, 1) }}%</span>
                                <span class="text-red-600">Outstanding {{ round(100 - $paidRatio, 1) }}%</span>
                            </div>
                        </div>

                        {{-- ✅ GHANA: Advance Rent summary --}}
                        @if($advanceInvoices->count() > 0)
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-hand-holding-usd mr-1"></i> Advance Rent
                            </h4>
                            <div class="flex justify-between text-sm">
                                <span style="color: var(--text-secondary);">{{ $advanceInvoices->count() }} invoice(s)</span>
                                <span class="font-medium" style="color: var(--text-primary);">
                                    {{ $currencySymbol }} {{ number_format($advanceInvoices->sum('amount'), 2) }}
                                </span>
                            </div>
                        </div>
                        @endif

                        {{-- Monthly Rent summary --}}
                        @if($monthlyInvoices->count() > 0)
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-calendar-day mr-1"></i> Monthly Rent
                            </h4>
                            <div class="flex justify-between text-sm">
                                <span style="color: var(--text-secondary);">{{ $monthlyInvoices->count() }} invoice(s)</span>
                                <span class="font-medium" style="color: var(--text-primary);">
                                    {{ $currencySymbol }} {{ number_format($monthlyInvoices->sum('amount'), 2) }}
                                </span>
                            </div>
                        </div>
                        @endif

                        {{-- Late fees summary --}}
                        @if($lateFeeInvoices->count() > 0)
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-clock mr-1"></i> Late Fees
                            </h4>
                            <div class="flex justify-between text-sm">
                                <span style="color: var(--text-secondary);">{{ $lateFeeInvoices->count() }} invoice(s)</span>
                                <span class="font-medium" style="color: var(--warning);">
                                    {{ $currencySymbol }} {{ number_format($lateFeeInvoices->sum('amount'), 2) }}
                                </span>
                            </div>
                        </div>
                        @endif

                        <!-- Recent Payments -->
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-history mr-1"></i> Recent Payments
                            </h4>
                            @if($recentPayments->isEmpty())
                            <div class="text-center py-4">
                                <i class="fas fa-money-bill-wave text-gray-300 text-2xl mb-2"></i>
                                <p class="text-xs" style="color: var(--text-secondary);">No recent payments</p>
                            </div>
                            @else
                            <div class="space-y-3">
                                @foreach($recentPayments as $payment)
                                <div class="flex items-center justify-between p-2 rounded-lg"
                                     style="background-color: rgba(var(--success-rgb), 0.05);">
                                    <div>
                                        <div class="text-sm font-medium" style="color: var(--text-primary);">
                                            {{ $currencySymbol }} {{ number_format($payment->amount_paid, 2) }}
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            {{ optional($payment->paid_at)->format('M d, Y') ?? '—' }}
                                        </div>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full badge-success">Paid</span>
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- ✅ GHANA: Governing law card -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-sm font-semibold mb-2 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-balance-scale mr-2" style="color: var(--primary);"></i> Governing Law
                    </h3>
                    <p class="text-xs" style="color: var(--text-secondary);">
                        All lease and financial terms on this page are governed by the
                        <strong>{{ $governingLaw }}</strong>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     ✅ INVOICE: Generate Invoice Modal
     ============================================================ --}}
@if($generateInvoiceRouteName)
<div id="generateInvoiceModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideGenerateInvoiceModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-file-invoice-dollar mr-2" style="color: var(--primary);"></i> Generate Invoice
                </h3>
                <button type="button" onclick="hideGenerateInvoiceModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>

            <form id="generateInvoiceForm" method="POST" action="{{ route($generateInvoiceRouteName, $unit->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-6">
                        <h4 class="font-medium mb-3 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-info-circle mr-2"></i> Invoice Details
                        </h4>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Invoice Type *
                                </label>
                                <select name="invoice_type" required class="financial-custom-dropdown">
                                    <option value="">Select Type</option>
                                    <option value="{{ PropertyUnitInvoice::TYPE_MONTHLY_RENT }}">Monthly Rent</option>
                                    <option value="{{ PropertyUnitInvoice::TYPE_ADVANCE_RENT }}">Advance Rent</option>
                                    <option value="{{ PropertyUnitInvoice::TYPE_SECURITY_DEPOSIT }}">Security Deposit</option>
                                    <option value="{{ PropertyUnitInvoice::TYPE_UTILITY_DEPOSIT }}">Utility Deposit</option>
                                    <option value="{{ PropertyUnitInvoice::TYPE_LATE_FEE }}">Late Fee</option>
                                    <option value="{{ PropertyUnitInvoice::TYPE_EARLY_TERMINATION }}">Early Termination</option>
                                    <option value="{{ PropertyUnitInvoice::TYPE_OTHER }}">Other</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Amount ({{ $currencySymbol }}) *
                                </label>
                                <input type="number" name="amount" required step="0.01" min="0.01"
                                       class="financial-custom-input" placeholder="0.00">
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Description *
                                </label>
                                <textarea name="description" rows="3" required
                                          class="financial-custom-textarea"
                                          placeholder="Enter invoice description..."></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        Invoice Date *
                                    </label>
                                    <input type="date" name="issue_date" required
                                           class="financial-custom-input"
                                           value="{{ now()->format('Y-m-d') }}">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        Due Date *
                                    </label>
                                    <input type="date" name="due_date" required
                                           class="financial-custom-input"
                                           value="{{ now()->addDays(7)->format('Y-m-d') }}">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        Period Start (Optional)
                                    </label>
                                    <input type="date" name="period_start" class="financial-custom-input">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        Period End (Optional)
                                    </label>
                                    <input type="date" name="period_end" class="financial-custom-input">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Notes (Optional)
                                </label>
                                <textarea name="notes" rows="2" class="financial-custom-textarea"
                                          placeholder="Additional notes..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 rounded-lg mb-4"
                         style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <h4 class="font-medium mb-2 flex items-center" style="color: var(--info);">
                            <i class="fas fa-clipboard-check mr-2"></i> Invoice Summary
                        </h4>
                        <div class="text-sm space-y-1">
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Tenant:</span>
                                <span style="color: var(--text-primary);">{{ $unit->tenant->name ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Property:</span>
                                <span style="color: var(--text-primary);">{{ $unit->property->property_name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span style="color: var(--text-secondary);">Unit:</span>
                                <span style="color: var(--text-primary);">{{ $unit->unit_number }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideGenerateInvoiceModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button type="submit"
                            class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-file-invoice mr-2"></i> Generate Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ============================================================
     ✅ NEW: Record Payment Modal
     ============================================================ --}}
@if($recordPaymentRouteName)
<div id="recordPaymentModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hidePaymentModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-money-bill-wave mr-2" style="color: var(--success);"></i> Record Payment
                </h3>
                <button type="button" onclick="hidePaymentModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>

            <form id="recordPaymentForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                        <div class="text-xs" style="color: var(--text-secondary);">Invoice</div>
                        <div class="font-mono font-semibold" id="paymentInvoiceRef" style="color: var(--text-primary);">—</div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">Outstanding</div>
                        <div class="text-lg font-bold" id="paymentOutstanding" style="color: var(--warning);">—</div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Payment Date *
                            </label>
                            <input type="date" name="payment_date" required
                                   class="financial-custom-input"
                                   value="{{ now()->format('Y-m-d') }}"
                                   max="{{ now()->format('Y-m-d') }}">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Amount ({{ $currencySymbol }}) *
                            </label>
                            <input type="number" name="payment_amount" id="paymentAmount" required
                                   step="0.01" min="0.01"
                                   class="financial-custom-input">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Payment Method *
                            </label>
                            <select name="payment_method" required class="financial-custom-dropdown">
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="card">Card</option>
                                <option value="cheque">Cheque</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Reference Number (Optional)
                            </label>
                            <input type="text" name="reference_number" maxlength="100"
                                   class="financial-custom-input" placeholder="e.g., bank ref, cheque no.">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Notes (Optional)
                            </label>
                            <textarea name="notes" rows="2" maxlength="500"
                                      class="financial-custom-textarea"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hidePaymentModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button type="submit"
                            class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-check mr-2"></i> Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ============================================================
     ✅ NEW: Void Invoice Modal
     ============================================================ --}}
@if($voidInvoiceRouteName)
<div id="voidModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideVoidModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--danger);">
                    <i class="fas fa-ban mr-2"></i> Void Invoice
                </h3>
                <button type="button" onclick="hideVoidModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>

            <form id="voidForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                        <p class="text-sm" style="color: var(--text-secondary);">
                            You are about to void invoice
                            <strong id="voidInvoiceRef" style="color: var(--text-primary);">—</strong>.
                            This action cannot be undone.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for voiding *
                        </label>
                        <textarea name="void_reason" rows="3" required minlength="5" maxlength="500"
                                  class="financial-custom-textarea"
                                  placeholder="e.g., Duplicate invoice, incorrect amount..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideVoidModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg font-medium text-white"
                            style="background-color: var(--danger);">
                        <i class="fas fa-ban mr-2"></i> Void Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
// ============================================================
// ✅ INVOICE: Route map for JS
// ============================================================
const FINANCIAL_CONFIG = {
    unitId: {{ $unit->id }},
    currency: @json($currencySymbol),
    routes: {
        recordPayment: @json($recordPaymentRouteName ? route($recordPaymentRouteName, [$unit->id, '__INVOICE__']) : null),
        voidInvoice:   @json($voidInvoiceRouteName   ? route($voidInvoiceRouteName,   [$unit->id, '__INVOICE__']) : null),
        applyLateFee:  @json($applyLateFeeRouteName  ? route($applyLateFeeRouteName,  [$unit->id, '__INVOICE__']) : null),
        bulkVoid:      @json($bulkVoidRouteName      ? route($bulkVoidRouteName,      $unit->id) : null),
        makePayment:   @json(url('/payments/create?invoice_id=__INVOICE__')),
    },
};

document.addEventListener('DOMContentLoaded', function () {
    initFormValidation();
    autoHideMessages();

    // Select-all checkbox
    const selectAll = document.getElementById('selectAllInvoices');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.invoice-selector').forEach(cb => cb.checked = this.checked);
            updateBulkActions();
        });
    }
});

function autoHideMessages() {
    setTimeout(() => {
        document.querySelectorAll('.bg-green-100, .bg-red-100, .bg-blue-100').forEach(msg => {
            if (msg.style.display !== 'none') msg.style.display = 'none';
        });
    }, 5000);
}

// ============================================================
// ✅ Generate Invoice Modal
// ============================================================
function showGenerateInvoiceModal() {
    const modal = document.getElementById('generateInvoiceModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    const form = document.getElementById('generateInvoiceForm');
    if (form) form.reset();
}

function hideGenerateInvoiceModal() {
    const modal = document.getElementById('generateInvoiceModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function initFormValidation() {
    const form = document.getElementById('generateInvoiceForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        const amount      = form.querySelector('input[name="amount"]');
        const description = form.querySelector('textarea[name="description"]');
        const invoiceType = form.querySelector('select[name="invoice_type"]');
        const issueDate   = form.querySelector('input[name="issue_date"]');
        const dueDate     = form.querySelector('input[name="due_date"]');

        if (!amount.value || parseFloat(amount.value) <= 0) {
            alert('Please enter a valid amount'); e.preventDefault(); amount.focus(); return;
        }
        if (!description.value.trim()) {
            alert('Please enter a description'); e.preventDefault(); description.focus(); return;
        }
        if (!invoiceType.value) {
            alert('Please select an invoice type'); e.preventDefault(); invoiceType.focus(); return;
        }
        if (!issueDate.value || !dueDate.value) {
            alert('Please select both dates'); e.preventDefault(); return;
        }
        if (new Date(dueDate.value) < new Date(issueDate.value)) {
            alert('Due date must be after invoice date'); e.preventDefault(); dueDate.focus(); return;
        }
    });
}

// ============================================================
// ✅ Record Payment Modal
// ============================================================
function openPaymentModal(invoiceId, reference, outstanding) {
    const modal = document.getElementById('recordPaymentModal');
    const form = document.getElementById('recordPaymentForm');
    if (!modal || !form) return;

    form.action = FINANCIAL_CONFIG.routes.recordPayment.replace('__INVOICE__', invoiceId);

    document.getElementById('paymentInvoiceRef').textContent = reference;
    document.getElementById('paymentOutstanding').textContent =
        `${FINANCIAL_CONFIG.currency} ${parseFloat(outstanding).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

    const amountInput = document.getElementById('paymentAmount');
    amountInput.value = parseFloat(outstanding).toFixed(2);
    amountInput.max = outstanding;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hidePaymentModal() {
    const modal = document.getElementById('recordPaymentModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// ============================================================
// ✅ Void Invoice Modal
// ============================================================
function openVoidModal(invoiceId, reference) {
    const modal = document.getElementById('voidModal');
    const form = document.getElementById('voidForm');
    if (!modal || !form) return;

    form.action = FINANCIAL_CONFIG.routes.voidInvoice.replace('__INVOICE__', invoiceId);
    document.getElementById('voidInvoiceRef').textContent = reference;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideVoidModal() {
    const modal = document.getElementById('voidModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// ============================================================
// ✅ Apply Late Fee
// ============================================================
function confirmApplyLateFee(invoiceId, reference) {
    if (!confirm(`Apply the lease's late fee to invoice ${reference}?\n\nA new LATE- invoice will be created.`)) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = FINANCIAL_CONFIG.routes.applyLateFee.replace('__INVOICE__', invoiceId);

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = document.querySelector('meta[name="csrf-token"]').content;
    form.appendChild(csrf);

    document.body.appendChild(form);
    form.submit();
}

// ============================================================
// ✅ Bulk Void
// ============================================================
function toggleSelectAll(checked) {
    document.querySelectorAll('.invoice-selector').forEach(cb => cb.checked = checked);
    updateBulkActions();
}

function updateBulkActions() {
    const selected = document.querySelectorAll('.invoice-selector:checked');
    const bar = document.getElementById('bulkActions');
    const count = document.getElementById('selectedCount');
    if (!bar || !count) return;

    if (selected.length > 0) {
        bar.classList.remove('hidden');
        count.textContent = selected.length;
    } else {
        bar.classList.add('hidden');
    }
}

function submitBulkVoid() {
    const selected = document.querySelectorAll('.invoice-selector:checked');
    if (selected.length === 0) {
        alert('Please select at least one invoice.');
        return;
    }

    const reason = prompt('Reason for voiding ' + selected.length + ' invoice(s):');
    if (!reason || reason.trim().length < 5) {
        alert('Please provide a reason (at least 5 characters).');
        return;
    }

    const inputsContainer = document.getElementById('bulkVoidInputs');
    inputsContainer.innerHTML = '';

    const reasonInput = document.createElement('input');
    reasonInput.type = 'hidden';
    reasonInput.name = 'void_reason';
    reasonInput.value = reason.trim();
    inputsContainer.appendChild(reasonInput);

    selected.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'invoice_ids[]';
        input.value = cb.value;
        inputsContainer.appendChild(input);
    });

    document.getElementById('bulkVoidForm').submit();
}

// ============================================================
// Search / filter
// ============================================================
function searchInvoices(query) {
    document.querySelectorAll('.invoice-row').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(query.toLowerCase()) ? '' : 'none';
    });
}

function filterInvoices(status) {
    document.querySelectorAll('.invoice-row').forEach(row => {
        row.style.display = (!status || row.getAttribute('data-status') === status) ? '' : 'none';
    });
}

// ============================================================
// Tenant payment redirect
// ============================================================
function makePayment(invoiceId) {
    if (confirm('Proceed to payment for this invoice?')) {
        showToast('Redirecting to payment gateway...', 'info');
        setTimeout(() => {
            window.location.href = FINANCIAL_CONFIG.routes.makePayment.replace('__INVOICE__', invoiceId);
        }, 800);
    }
}

// ============================================================
// Toast
// ============================================================
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = 'fixed bottom-4 right-4 px-4 py-3 rounded-lg shadow-lg z-50 transition-all duration-300';
    const colors = { success: 'var(--success)', error: 'var(--danger)', warning: 'var(--warning)', info: 'var(--info)' };
    toast.style.backgroundColor = colors[type] ?? colors.info;
    toast.style.color = 'white';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.transform = 'translateY(100px)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>

<style>
/* ---------- Form controls ---------- */
.financial-custom-dropdown,
.financial-custom-input,
.financial-custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

.financial-custom-dropdown {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}

[data-theme="dark"] .financial-custom-dropdown {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%23e4e4e4' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
}

.financial-custom-input:focus,
.financial-custom-textarea:focus,
.financial-custom-dropdown:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* ---------- Badges ---------- */
.badge-success   { background-color: rgba(var(--success-rgb), 0.1) !important;   color: var(--success) !important;   border: 1px solid rgba(var(--success-rgb), 0.3) !important; }
.badge-warning   { background-color: rgba(var(--warning-rgb), 0.1) !important;   color: var(--warning) !important;   border: 1px solid rgba(var(--warning-rgb), 0.3) !important; }
.badge-danger    { background-color: rgba(var(--danger-rgb), 0.1) !important;    color: var(--danger) !important;    border: 1px solid rgba(var(--danger-rgb), 0.3) !important; }
.badge-info      { background-color: rgba(var(--info-rgb), 0.1) !important;      color: var(--info) !important;      border: 1px solid rgba(var(--info-rgb), 0.3) !important; }
.badge-primary   { background-color: rgba(var(--primary-rgb), 0.1) !important;   color: var(--primary) !important;   border: 1px solid rgba(var(--primary-rgb), 0.3) !important; }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; }

/* ---------- Buttons ---------- */
.btn-primary   { background-color: var(--primary) !important;   color: white !important; border: 1px solid var(--primary) !important;   transition: all 0.2s ease; }
.btn-primary:hover   { background-color: var(--secondary) !important; border-color: var(--secondary) !important; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }

.btn-secondary { background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; transition: all 0.2s ease; }
.btn-secondary:hover { background-color: rgba(var(--secondary-rgb), 0.2) !important; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }

/* ---------- Table action buttons ---------- */
.action-buttons { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.action-btn { padding: 0.375rem 0.75rem; border-radius: 6px; font-size: 0.75rem; font-weight: 500;
    display: inline-flex; align-items: center; justify-content: center; gap: 0.25rem;
    transition: all 0.2s ease; border: 1px solid transparent; cursor: pointer; min-width: 36px; min-height: 36px; text-decoration: none; }
.action-btn.view     { background-color: rgba(var(--info-rgb), 0.1);    color: var(--info);    border-color: rgba(var(--info-rgb), 0.3); }
.action-btn.view:hover { background-color: rgba(var(--info-rgb), 0.2); transform: translateY(-1px); }
.action-btn.pay      { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border-color: rgba(var(--success-rgb), 0.3); }
.action-btn.pay:hover { background-color: rgba(var(--success-rgb), 0.2); transform: translateY(-1px); }

/* ---------- Table ---------- */
table { border-collapse: separate; border-spacing: 0; width: 100%; }
table th { font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.75rem; padding: 0.75rem; border-bottom: 2px solid var(--border-color); background-color: var(--bg-secondary) !important; }
table td { padding: 0.75rem; border-bottom: 1px solid var(--border-color); vertical-align: top; background-color: var(--card-bg) !important; }
table tr:last-child td { border-bottom: none; }
table tr:hover td { background-color: var(--bg-secondary) !important; }

/* Checkbox styling */
.invoice-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--primary);
}

/* ---------- Modal ---------- */
.modal-container { background: var(--card-bg); border: 1px solid var(--border-color); max-height: 90vh; overflow-y: auto; border-radius: 16px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); }
.modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background-color: var(--bg-secondary); }
.modal-body { padding: 1.5rem; }
.modal-footer { padding: 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; background-color: var(--bg-secondary); }
.modal-close-btn { padding: 0.5rem; border-radius: 0.375rem; transition: background-color 0.2s; cursor: pointer; background: none; border: none; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; }
.modal-close-btn:hover { background-color: rgba(0, 0, 0, 0.05); }

/* ---------- Stat cards ---------- */
.stat-card { transition: all 0.3s ease; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1); }

/* ---------- Responsive ---------- */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 { grid-template-columns: 1fr; }
    .lg\:col-span-2 { margin-bottom: 1.5rem; }
    table { display: block; overflow-x: auto; white-space: nowrap; }
    .action-buttons { flex-direction: column; }
    .action-btn { width: 100%; justify-content: flex-start; }
    .modal-container { width: 95%; max-height: 80vh; margin: 0.5rem; }
    .grid.grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.md\:grid-cols-4 { grid-template-columns: repeat(2, 1fr); }
    .grid.grid-cols-2.md\:grid-cols-5 { grid-template-columns: repeat(2, 1fr); }
}

@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
.animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
</style>
@endsection