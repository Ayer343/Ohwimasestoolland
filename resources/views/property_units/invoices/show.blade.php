{{-- resources/views/property_units/invoices/show.blade.php --}}
@php
    use App\Models\PropertyUnitInvoice;

    // ========== AUTH & ROLE ==========
    $user = auth()->user();
    $isLandlord   = $user->isLandlord()   || $user->hasRole('landlord');
    $isAdmin      = $user->isAdmin()      || $user->hasRole('admin');
    $isSuperAdmin = $user->isSuperAdmin() || $user->hasRole('super-admin');
    $isTenant     = $user->isTenant()     || $user->hasRole('tenant');

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
    } else {
        $layout = 'layouts.app';
        $routePrefix = '';
    }

    // ========== CURRENCY & LAW ==========
    $currencySymbol = config('leases.ghana.currency.symbol', 'GH₵');
    $governingLaw   = config('leases.ghana.governing_law', 'Rent Act, 1963 (Act 220)');

    // ========== STATUS META ==========
    $statusMap = [
        PropertyUnitInvoice::STATUS_PENDING => ['badge-warning',  'clock',                'Pending'],
        PropertyUnitInvoice::STATUS_PARTIAL => ['badge-info',     'adjust',               'Partial'],
        PropertyUnitInvoice::STATUS_PAID    => ['badge-success',  'check-circle',         'Paid'],
        PropertyUnitInvoice::STATUS_OVERDUE => ['badge-danger',   'exclamation-triangle', 'Overdue'],
        PropertyUnitInvoice::STATUS_VOID    => ['badge-secondary','ban',                  'Void'],
    ];
    [$statusClass, $statusIcon, $statusLabel] = $statusMap[$invoice->status]
        ?? ['badge-secondary', 'circle', ucfirst($invoice->status)];

    // ========== COMPUTED ==========
    $balance = max(0, (float) $invoice->amount - (float) $invoice->amount_paid);
    $isOverdue = $invoice->status === PropertyUnitInvoice::STATUS_OVERDUE;
    $canPay    = !$isTenant && in_array($invoice->status, [
        PropertyUnitInvoice::STATUS_PENDING,
        PropertyUnitInvoice::STATUS_PARTIAL,
        PropertyUnitInvoice::STATUS_OVERDUE,
    ]);
    $canVoid   = $canPay;
    $canLateFee= !$isTenant && $isOverdue;
    $canSend   = $canPay && $invoice->tenant?->email;
    $canRecalc = !$isTenant && $invoice->status !== PropertyUnitInvoice::STATUS_VOID;

    // ========== ROUTE DISCOVERY ==========
    $findRoute = function (array $candidates) {
        foreach ($candidates as $name) {
            if ($name && Route::has($name)) return $name;
        }
        return null;
    };

    $backRouteName    = $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.financials" : null,
        'property-units.financials',
    ]));
    $pdfRouteName     = $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.invoice-pdf" : null,
        'property-units.invoice-pdf',
    ]));
    $recordPayRoute   = !$isTenant ? $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.record-payment" : null,
        'property-units.record-payment',
    ])) : null;
    $voidRoute        = !$isTenant ? $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.void-invoice" : null,
        'property-units.void-invoice',
    ])) : null;
    $lateFeeRoute     = !$isTenant ? $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.apply-late-fee" : null,
        'property-units.apply-late-fee',
    ])) : null;
    $sendRoute        = !$isTenant ? $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.send-invoice" : null,
        'property-units.send-invoice',
    ])) : null;
    $recalcRoute      = !$isTenant ? $findRoute(array_filter([
        $routePrefix ? "{$routePrefix}.property-units.recalculate-invoice" : null,
        'property-units.recalculate-invoice',
    ])) : null;

    // ========== PAGE TITLE ==========
    $pageTitle = "Invoice {$invoice->reference} - Unit {$unit->unit_number}";
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ============================================================
         HEADER
         ============================================================ --}}
    <div class="card">
        <div class="flex flex-wrap justify-between items-center gap-3 p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-file-invoice text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-file-invoice mr-2" style="color: var(--primary);"></i>
                        Invoice {{ $invoice->reference }}
                        <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full {{ $statusClass }}">
                            <i class="fas fa-{{ $statusIcon }} mr-1"></i> {{ $statusLabel }}
                        </span>
                    </h2>
                    <div class="text-sm flex flex-wrap items-center gap-x-2 mt-1" style="color: var(--text-secondary);">
                        <span><i class="fas fa-building mr-1"></i>{{ $unit->property->property_name }}</span>
                        <span>•</span>
                        <span><i class="fas fa-door-closed mr-1"></i>Unit {{ $unit->unit_number }}</span>
                        @if($invoice->tenant)
                        <span>•</span>
                        <span><i class="fas fa-user mr-1"></i>{{ $invoice->tenant->name }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if($backRouteName)
                <a href="{{ route($backRouteName, $unit->id) }}"
                   class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Financials
                </a>
                @endif
                @if($pdfRouteName)
                <a href="{{ route($pdfRouteName, [$unit->id, $invoice->id]) }}"
                   target="_blank"
                   class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium btn-primary">
                    <i class="fas fa-file-pdf mr-1"></i> Download PDF
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Session messages --}}
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
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3"
                    onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @endif
    @endforeach

    {{-- ============================================================
         MAIN GRID
         ============================================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- LEFT COLUMN: Invoice details --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Amount Summary Card --}}
            <div class="card border-l-4"
                 style="border-left-color: {{ $balance > 0 ? 'var(--warning)' : 'var(--success)' }};">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-money-bill-wave mr-2" style="color: var(--primary);"></i> Amount Summary
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="p-4 rounded-lg text-center"
                             style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.15);">
                            <p class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary);">
                                Total Amount
                            </p>
                            <p class="text-2xl font-bold" style="color: var(--primary);">
                                {{ $currencySymbol }} {{ number_format($invoice->amount, 2) }}
                            </p>
                        </div>

                        <div class="p-4 rounded-lg text-center"
                             style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.15);">
                            <p class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary);">
                                Amount Paid
                            </p>
                            <p class="text-2xl font-bold" style="color: var(--success);">
                                {{ $currencySymbol }} {{ number_format($invoice->amount_paid, 2) }}
                            </p>
                        </div>

                        <div class="p-4 rounded-lg text-center"
                             style="background-color: {{ $balance > 0 ? 'rgba(var(--warning-rgb), 0.05)' : 'rgba(var(--success-rgb), 0.05)' }};
                                    border: 1px solid {{ $balance > 0 ? 'rgba(var(--warning-rgb), 0.15)' : 'rgba(var(--success-rgb), 0.15)' }};">
                            <p class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary);">
                                Balance
                            </p>
                            <p class="text-2xl font-bold"
                               style="color: {{ $balance > 0 ? 'var(--warning)' : 'var(--success)' }};">
                                {{ $currencySymbol }} {{ number_format($balance, 2) }}
                            </p>
                        </div>
                    </div>

                    @if($invoice->amount > 0 && $invoice->amount_paid > 0)
                    <div class="mt-4">
                        @php $paidPct = min(100, ($invoice->amount_paid / $invoice->amount) * 100); @endphp
                        <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                            <div class="h-2 rounded-full"
                                 style="width: {{ $paidPct }}%; background-color: {{ $paidPct >= 100 ? 'var(--success)' : 'var(--primary)' }};"></div>
                        </div>
                        <p class="text-xs mt-1 text-right" style="color: var(--text-secondary);">
                            {{ round($paidPct, 1) }}% paid
                        </p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Invoice Information --}}
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Invoice Information
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Reference</p>
                            <p class="font-mono font-semibold" style="color: var(--text-primary);">
                                {{ $invoice->reference }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Type</p>
                            <p class="font-semibold" style="color: var(--text-primary);">
                                {{ ucfirst(str_replace('_', ' ', $invoice->invoice_type)) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Issue Date</p>
                            <p class="font-semibold" style="color: var(--text-primary);">
                                {{ optional($invoice->issue_date)->format('F j, Y') ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Due Date</p>
                            <p class="font-semibold" style="color: {{ $isOverdue ? 'var(--danger)' : 'var(--text-primary)' }};">
                                {{ optional($invoice->due_date)->format('F j, Y') ?? '—' }}
                                @if($isOverdue)
                                    <span class="ml-1 text-xs">(overdue)</span>
                                @endif
                            </p>
                        </div>

                        @if($invoice->period_start || $invoice->period_end)
                        <div>
                            <p class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Period Start</p>
                            <p class="font-semibold" style="color: var(--text-primary);">
                                {{ optional($invoice->period_start)->format('F j, Y') ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Period End</p>
                            <p class="font-semibold" style="color: var(--text-primary);">
                                {{ optional($invoice->period_end)->format('F j, Y') ?? '—' }}
                            </p>
                        </div>
                        @endif
                    </div>

                    {{-- Description --}}
                    @if($invoice->description)
                    <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                        <p class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Description</p>
                        <p class="text-sm" style="color: var(--text-primary);">{{ $invoice->description }}</p>
                    </div>
                    @endif

                    {{-- Notes --}}
                    @if($invoice->notes)
                    <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                        <p class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Notes</p>
                        <p class="text-sm whitespace-pre-line" style="color: var(--text-secondary);">{{ $invoice->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Void Reason --}}
            @if($invoice->status === PropertyUnitInvoice::STATUS_VOID)
            <div class="card border-l-4" style="border-left-color: var(--danger);">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-3 flex items-center" style="color: var(--danger);">
                        <i class="fas fa-ban mr-2"></i> Invoice Voided
                    </h3>
                    <p class="text-sm mb-2" style="color: var(--text-primary);">
                        <strong>Reason:</strong> {{ $invoice->void_reason ?? 'No reason recorded' }}
                    </p>
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-calendar mr-1"></i>
                        Voided on {{ optional($invoice->voided_at)->format('F j, Y H:i') ?? '—' }}
                    </p>
                </div>
            </div>
            @endif

            {{-- Payment History --}}
            @if($invoice->amount_paid > 0)
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2" style="color: var(--primary);"></i> Payment Information
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @if($invoice->last_payment_at)
                        <div>
                            <p class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Last Payment</p>
                            <p class="font-semibold" style="color: var(--text-primary);">
                                {{ optional($invoice->last_payment_at)->format('M j, Y') }}
                            </p>
                        </div>
                        @endif

                        @if($invoice->payment_method)
                        <div>
                            <p class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Method</p>
                            <p class="font-semibold capitalize" style="color: var(--text-primary);">
                                {{ str_replace('_', ' ', $invoice->payment_method) }}
                            </p>
                        </div>
                        @endif

                        @if($invoice->payment_reference)
                        <div>
                            <p class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Reference</p>
                            <p class="font-mono text-sm" style="color: var(--text-primary);">
                                {{ $invoice->payment_reference }}
                            </p>
                        </div>
                        @endif

                        @if($invoice->paid_at)
                        <div>
                            <p class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Fully Paid On</p>
                            <p class="font-semibold" style="color: var(--success);">
                                {{ optional($invoice->paid_at)->format('F j, Y H:i') }}
                            </p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- Related invoices (same lease) --}}
            @php
                $relatedInvoices = \App\Models\PropertyUnitInvoice::where('lease_id', $invoice->lease_id)
                    ->where('id', '!=', $invoice->id)
                    ->orderBy('due_date', 'desc')
                    ->limit(5)
                    ->get();
            @endphp

            @if($relatedInvoices->isNotEmpty())
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-layer-group mr-2" style="color: var(--primary);"></i> Related Invoices
                    </h3>

                    <div class="space-y-2">
                        @foreach($relatedInvoices as $related)
                        @php
                            [$relClass, $relIcon, $relLabel] = $statusMap[$related->status]
                                ?? ['badge-secondary', 'circle', ucfirst($related->status)];
                        @endphp
                        <div class="flex items-center justify-between p-3 rounded-lg"
                             style="background-color: rgba(0,0,0,0.02); border: 1px solid var(--border-color);">
                            <div>
                                <p class="font-mono text-sm" style="color: var(--text-primary);">
                                    {{ $related->reference }}
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    {{ ucfirst(str_replace('_', ' ', $related->invoice_type)) }}
                                    · {{ optional($related->due_date)->format('M j, Y') ?? '—' }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-sm" style="color: var(--text-primary);">
                                    {{ $currencySymbol }} {{ number_format($related->amount, 2) }}
                                </p>
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $relClass }}">
                                    {{ $relLabel }}
                                </span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- RIGHT COLUMN: Actions & context --}}
        <div class="lg:col-span-1 space-y-6">

            {{-- Quick Actions --}}
            @if($canPay || $canVoid || $canLateFee || $canSend || $canRecalc)
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i> Actions
                    </h3>

                    <div class="space-y-2">
                        @if($canPay && $recordPayRoute)
                        <button type="button"
                                onclick="openPaymentModal()"
                                class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg font-medium text-white btn-primary">
                            <i class="fas fa-money-bill-wave mr-2"></i> Record Payment
                        </button>
                        @endif

                        @if($canLateFee && $lateFeeRoute)
                        <form method="POST" action="{{ route($lateFeeRoute, [$unit->id, $invoice->id]) }}">
                            @csrf
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg font-medium"
                                    style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);"
                                    onclick="return confirm('Apply the lease\'s late fee? A new LATE invoice will be created.');">
                                <i class="fas fa-clock mr-2"></i> Apply Late Fee
                            </button>
                        </form>
                        @endif

                        @if($canSend && $sendRoute)
                        <form method="POST" action="{{ route($sendRoute, [$unit->id, $invoice->id]) }}">
                            @csrf
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg font-medium"
                                    style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);"
                                    onclick="return confirm('Send this invoice to {{ $invoice->tenant->email }}?');">
                                <i class="fas fa-paper-plane mr-2"></i> Send to Tenant
                            </button>
                        </form>
                        @endif

                        @if($canRecalc && $recalcRoute)
                        <form method="POST" action="{{ route($recalcRoute, [$unit->id, $invoice->id]) }}">
                            @csrf
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg font-medium btn-secondary"
                                    onclick="return confirm('Recalculate this invoice\'s status from its paid amount?');">
                                <i class="fas fa-sync-alt mr-2"></i> Recalculate Status
                            </button>
                        </form>
                        @endif

                        @if($canVoid && $voidRoute)
                        <button type="button"
                                onclick="openVoidModal()"
                                class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg font-medium"
                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-ban mr-2"></i> Void Invoice
                        </button>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- Tenant --}}
            @if($invoice->tenant)
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user mr-2" style="color: var(--primary);"></i> Tenant
                    </h3>
                    <p class="font-semibold" style="color: var(--text-primary);">{{ $invoice->tenant->name }}</p>
                    @if($invoice->tenant->email)
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-envelope mr-1"></i>
                        <a href="mailto:{{ $invoice->tenant->email }}" class="hover:underline" style="color: var(--primary);">
                            {{ $invoice->tenant->email }}
                        </a>
                    </p>
                    @endif
                    @if($invoice->tenant->phone)
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-phone mr-1"></i>
                        <a href="tel:{{ $invoice->tenant->phone }}" class="hover:underline" style="color: var(--primary);">
                            {{ $invoice->tenant->phone }}
                        </a>
                    </p>
                    @endif
                </div>
            </div>
            @endif

            {{-- Landlord --}}
            @if($invoice->landlord)
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-tie mr-2" style="color: var(--primary);"></i> Landlord
                    </h3>
                    <p class="font-semibold" style="color: var(--text-primary);">{{ $invoice->landlord->name }}</p>
                    @if($invoice->landlord->email)
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-envelope mr-1"></i> {{ $invoice->landlord->email }}
                    </p>
                    @endif
                </div>
            </div>
            @endif

            {{-- Meta --}}
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Meta
                    </h3>
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Invoice ID</span>
                            <span class="font-mono" style="color: var(--text-primary);">#{{ $invoice->id }}</span>
                        </div>
                        @if($invoice->lease_id)
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Lease ID</span>
                            <span class="font-mono" style="color: var(--text-primary);">#{{ $invoice->lease_id }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Created</span>
                            <span style="color: var(--text-primary);">{{ $invoice->created_at->format('M j, Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Updated</span>
                            <span style="color: var(--text-primary);">{{ $invoice->updated_at->format('M j, Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Currency</span>
                            <span style="color: var(--text-primary);">{{ $currencySymbol }}</span>
                        </div>
                        <div class="pt-2 border-t mt-2" style="border-color: var(--border-color);">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-balance-scale mr-1"></i>
                                Governed by {{ $governingLaw }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     Record Payment Modal
     ============================================================ --}}
@if($canPay && $recordPayRoute)
<div id="recordPaymentModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closePaymentModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-lg"
             style="background: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header" style="padding: 1.5rem; border-bottom: 1px solid var(--border-color);">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-money-bill-wave mr-2" style="color: var(--success);"></i> Record Payment
                </h3>
                <button type="button" onclick="closePaymentModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form method="POST" action="{{ route($recordPayRoute, [$unit->id, $invoice->id]) }}">
                @csrf
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="mb-4 p-3 rounded-lg"
                         style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.15);">
                        <div class="text-xs" style="color: var(--text-secondary);">Outstanding</div>
                        <div class="text-lg font-bold" style="color: var(--warning);">
                            {{ $currencySymbol }} {{ number_format($balance, 2) }}
                        </div>
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
                            <input type="number" name="payment_amount" required
                                   step="0.01" min="0.01" max="{{ $balance }}"
                                   value="{{ number_format($balance, 2, '.', '') }}"
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
                <div class="modal-footer"
                     style="padding: 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" onclick="closePaymentModal()"
                            class="px-4 py-2 rounded-lg font-medium btn-secondary">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg font-medium text-white btn-primary">
                        <i class="fas fa-check mr-2"></i> Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ============================================================
     Void Modal
     ============================================================ --}}
@if($canVoid && $voidRoute)
<div id="voidModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeVoidModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md"
             style="background: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header" style="padding: 1.5rem; border-bottom: 1px solid var(--border-color);">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--danger);">
                    <i class="fas fa-ban mr-2"></i> Void Invoice
                </h3>
                <button type="button" onclick="closeVoidModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form method="POST" action="{{ route($voidRoute, [$unit->id, $invoice->id]) }}">
                @csrf
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="mb-4 p-3 rounded-lg"
                         style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                        <p class="text-sm" style="color: var(--text-secondary);">
                            You are about to void invoice
                            <strong style="color: var(--text-primary);">{{ $invoice->reference }}</strong>.
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
                <div class="modal-footer"
                     style="padding: 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" onclick="closeVoidModal()"
                            class="px-4 py-2 rounded-lg font-medium btn-secondary">
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
function openPaymentModal() {
    const m = document.getElementById('recordPaymentModal');
    if (m) { m.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
}
function closePaymentModal() {
    const m = document.getElementById('recordPaymentModal');
    if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
}
function openVoidModal() {
    const m = document.getElementById('voidModal');
    if (m) { m.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
}
function closeVoidModal() {
    const m = document.getElementById('voidModal');
    if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
}

// ESC closes modals
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closePaymentModal(); closeVoidModal(); }
});
</script>
@endsection

@section('styles')
<style>
.card {
    border-radius: 16px;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}

.badge-success   { background-color: rgba(var(--success-rgb), 0.1) !important;   color: var(--success) !important;   border: 1px solid rgba(var(--success-rgb), 0.3) !important; }
.badge-warning   { background-color: rgba(var(--warning-rgb), 0.1) !important;   color: var(--warning) !important;   border: 1px solid rgba(var(--warning-rgb), 0.3) !important; }
.badge-danger    { background-color: rgba(var(--danger-rgb), 0.1) !important;    color: var(--danger) !important;    border: 1px solid rgba(var(--danger-rgb), 0.3) !important; }
.badge-info      { background-color: rgba(var(--info-rgb), 0.1) !important;      color: var(--info) !important;      border: 1px solid rgba(var(--info-rgb), 0.3) !important; }
.badge-primary   { background-color: rgba(var(--primary-rgb), 0.1) !important;   color: var(--primary) !important;   border: 1px solid rgba(var(--primary-rgb), 0.3) !important; }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; }

.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}
.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
    transition: all 0.2s ease;
}
.btn-secondary:hover { background-color: rgba(var(--secondary-rgb), 0.2) !important; }

.modal-container { max-height: 90vh; overflow-y: auto; }
.modal-close-btn {
    padding: 0.5rem; border-radius: 0.375rem; background: none; border: none;
    cursor: pointer; width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
}
.modal-close-btn:hover { background-color: rgba(0, 0, 0, 0.05); }

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
.financial-custom-input:focus,
.financial-custom-textarea:focus,
.financial-custom-dropdown:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.md\:grid-cols-3,
    .grid.grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }
    .modal-container { width: 95%; }
}
</style>
@endsection