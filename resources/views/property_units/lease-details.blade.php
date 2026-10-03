{{-- resources/views/property_units/lease-details.blade.php --}}
@php
    // ========== AUTH & ROLE RESOLUTION ==========
    $user = auth()->user();
    $isLandlord   = $user->isLandlord()   || $user->hasRole('landlord');
    $isTenant     = $user->isTenant()     || $user->hasRole('tenant');
    $isAdmin      = $user->isAdmin()      || $user->hasRole('admin');
    $isSuperAdmin = $user->isSuperAdmin() || $user->hasRole('super-admin');

    // ========== LAYOUT & ROUTE PREFIX ==========
    if ($isLandlord) {
        $layout = 'layouts.landlord';
        $routePrefix = 'landlord.property-units';
    } elseif ($isTenant) {
        $layout = 'layouts.tenant';
        $routePrefix = 'tenant.property-units';
    } elseif ($isAdmin) {
        $layout = 'layouts.app';
        $routePrefix = 'admin.property-units';
    } elseif ($isSuperAdmin) {
        $layout = 'layouts.app';
        $routePrefix = 'super-admin.property-units';
    } else {
        $layout = 'layouts.app';
        $routePrefix = 'property-units';
    }

    // ========== SIGNING MODE ==========
    $isTenantSigning   = request()->has('sign') && $isTenant   && !$lease->tenant_signed_at;
    $isLandlordSigning = request()->has('sign') && $isLandlord && !$lease->landlord_signed_at;

    $tenantSignatureUrl   = $isTenant   && $lease->id ? route($routePrefix . '.tenant-sign-lease',   [$unit->id, $lease->id]) : null;
    $landlordSignatureUrl = $isLandlord && $lease->id ? route($routePrefix . '.landlord-sign-lease', [$unit->id, $lease->id]) : null;

    // ========== PAGE TITLE ==========
    $pageTitle = 'Lease Details - ' . $unit->unit_number;
    if ($isTenantSigning || $isLandlordSigning) {
        $pageTitle = 'Sign Lease Agreement - ' . $unit->unit_number;
    }

    // ========== SESSION MESSAGES ==========
    $successMessage = session('success');
    $errorMessage   = session('error');

    // ========== SIGNATURE STATE ==========
    $landlordSigned = !empty($lease->landlord_signed_at);
    $tenantSigned   = !empty($lease->tenant_signed_at);
    $canTenantSign   = $isTenant   && !$lease->tenant_signed_at;
    $canLandlordSign = $isLandlord && !$lease->landlord_signed_at;

    // ========== DATES & COUNTS ==========
    $daysRemaining   = $lease->end_date ? $lease->end_date->diffInDays(now(), false) : 0;
    $monthsRemaining = $lease->end_date ? $lease->end_date->diffInMonths(now()) : 0;

    // ========== ✅ GHANA: CURRENCY & CONFIG ==========
    $currencySymbol = config('leases.ghana.currency.symbol', 'GH₵');
    $governingLaw   = config('leases.ghana.governing_law', 'Rent Act, 1963 (Act 220)');
    $legalMaxNew    = (int) config('leases.ghana.legal_max_advance_months.new_tenancy', 6);

    // ========== ✅ GHANA: PHASE & ADVANCE INFO ==========
    $currentPhase           = $lease->current_phase;                  // 'advance' | 'monthly' | null
    $isInAdvancePhase       = $lease->is_in_advance_phase;
    $isInMonthlyPhase       = $lease->is_in_monthly_phase;
    $hasAdvanceRent         = $lease->has_advance_rent;
    $advanceMonths          = (int) ($lease->advance_rent_months ?? 0);
    $advanceAmount          = (float) ($lease->advance_rent_amount ?? 0);
    $advancePeriodStart     = $lease->advance_rent_period_start;
    $advancePeriodEnd       = $lease->advance_rent_period_end;
    $firstMonthlyPayment    = $lease->first_monthly_payment_date;
    $advanceMonthsRemaining = $lease->advance_months_remaining;
    $advanceMonthsElapsed   = $lease->advance_months_elapsed;
    $complianceStatus       = $lease->advance_rent_compliance_status ?? 'compliant';
    $isCompliant            = $lease->is_advance_rent_compliant;
    $paymentFrequency       = $lease->payment_frequency ?? 'monthly';
    $isAdvanceOnly          = $lease->is_advance_only;

    // ========== ✅ INVOICE: SUMMARY ==========
    $invoices          = $invoices ?? $lease->invoices()->orderBy('due_date')->get();
    $invoiceSummary    = $invoiceSummary ?? [
        'total_invoiced' => $invoices->sum('amount'),
        'total_paid'     => $invoices->sum('amount_paid'),
        'outstanding'    => $invoices->whereIn('status', ['pending', 'partial', 'overdue'])
                                    ->sum(fn ($inv) => $inv->amount - $inv->amount_paid),
        'overdue_count'  => $invoices->where('status', 'overdue')->count(),
        'paid_count'     => $invoices->where('status', 'paid')->count(),
    ];

    // ========== ✅ INVOICE: CATEGORISE ==========
    $advanceInvoices  = $invoices->where('invoice_type', \App\Models\PropertyUnitInvoice::TYPE_ADVANCE_RENT);
    $monthlyInvoices  = $invoices->where('invoice_type', \App\Models\PropertyUnitInvoice::TYPE_MONTHLY_RENT);
    $depositInvoices  = $invoices->where('invoice_type', \App\Models\PropertyUnitInvoice::TYPE_SECURITY_DEPOSIT);

    // ========== SIGNATORIES LIST ==========
    $signatories = [];
    if ($landlordSigned && $lease->landlord) {
        $signatories[] = [
            'name'      => $lease->landlord->name,
            'role'      => 'Landlord',
            'date'      => $lease->landlord_signed_at,
            'signed_by' => $lease->landlord_signed_by ?? 'Unknown',
            'type'      => 'landlord',
        ];
    }
    if ($tenantSigned && $lease->tenant) {
        $signatories[] = [
            'name'      => $lease->tenant->name,
            'role'      => 'Tenant',
            'date'      => $lease->tenant_signed_at,
            'signed_by' => $lease->tenant_signed_by ?? 'Unknown',
            'type'      => 'tenant',
        ];
    }
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Left Column - Lease Information -->
    <div class="lg:col-span-2">
        <!-- Header Card -->
        <div class="card">
            <div class="flex justify-between items-center p-6">
                <div class="flex items-center">
                    <div class="mr-4">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                            <i class="fas fa-file-contract text-2xl"></i>
                        </div>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i>
                            @if($isTenantSigning)
                                Sign Lease Agreement (Tenant)
                            @elseif($isLandlordSigning)
                                Sign Lease Agreement (Landlord)
                            @else
                                Lease Agreement Details
                            @endif
                        </h2>
                        <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-home mr-2"></i>
                            <span>{{ $unit->property->property_name }} - Unit {{ $unit->unit_number }}</span>
                            <span class="mx-2">•</span>
                            <i class="fas fa-calendar-alt mr-1"></i>
                            <span>Lease #{{ $lease->id }}</span>
                            @if($lease->agreement_number)
                                <span class="mx-2">•</span>
                                <i class="fas fa-hashtag mr-1"></i>
                                <span>{{ $lease->agreement_number }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex flex-col items-end gap-2">
                    @php
                        $statusConfig = [
                            'active'            => ['color' => 'success',   'icon' => 'check-circle'],
                            'draft'             => ['color' => 'warning',   'icon' => 'edit'],
                            'pending_signature' => ['color' => 'info',      'icon' => 'signature'],
                            'pending_landlord'  => ['color' => 'info',      'icon' => 'user-tie'],
                            'pending_tenant'    => ['color' => 'info',      'icon' => 'user'],
                            'expired'           => ['color' => 'secondary', 'icon' => 'clock'],
                            'terminated'        => ['color' => 'danger',    'icon' => 'times-circle'],
                            'completed'         => ['color' => 'secondary', 'icon' => 'flag-checkered'],
                            'renewed'           => ['color' => 'primary',   'icon' => 'sync-alt'],
                        ];
                        $config = $statusConfig[$lease->status] ?? $statusConfig['draft'];
                    @endphp
                    <span class="px-4 py-2 text-sm font-semibold rounded-full badge-{{ $config['color'] }}">
                        <i class="fas fa-{{ $config['icon'] }} mr-1"></i> {{ strtoupper(str_replace('_', ' ', $lease->status)) }}
                    </span>

                    {{-- ✅ GHANA: Phase badge --}}
                    @if($hasAdvanceRent && !$isTenantSigning && !$isLandlordSigning)
                        @if($isInAdvancePhase)
                            <span class="px-3 py-1 text-xs font-semibold rounded-full badge-primary">
                                <i class="fas fa-calendar-check mr-1"></i> Advance Phase
                                @if($advanceMonthsRemaining > 0)
                                    ({{ $advanceMonthsRemaining }} mo left)
                                @endif
                            </span>
                        @elseif($isInMonthlyPhase)
                            <span class="px-3 py-1 text-xs font-semibold rounded-full badge-success">
                                <i class="fas fa-calendar-day mr-1"></i> Monthly Phase
                            </span>
                        @endif
                    @endif
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="border-t p-6" style="border-color: var(--border-color);">
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route($routePrefix . '.lease-management', $unit->id) }}"
                       class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Lease Management
                    </a>

                    @if(!$isTenantSigning && !$isLandlordSigning)
                        <a href="{{ route($routePrefix . '.lease-download-pdf', [$unit->id, $lease->id]) }}"
                           target="_blank"
                           class="btn-modern bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-file-pdf mr-2"></i> Download PDF
                        </a>

                        @if($isLandlord && $canLandlordSign)
                            <a href="{{ route($routePrefix . '.lease-details', [$unit->id, $lease->id]) }}?sign=true"
                               class="btn-success px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                                <i class="fas fa-signature mr-2"></i> Sign as Landlord
                            </a>
                        @endif

                        @if($isTenant && $canTenantSign)
                            <a href="{{ route($routePrefix . '.lease-details', [$unit->id, $lease->id]) }}?sign=true"
                               class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                                <i class="fas fa-signature mr-2"></i> Sign Lease
                            </a>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        @if($successMessage)
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 mt-6" role="alert">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span class="font-bold">Success!</span>
                <span class="ml-2">{{ $successMessage }}</span>
            </div>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @endif

        @if($errorMessage)
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 mt-6" role="alert">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span class="font-bold">Error!</span>
                <span class="ml-2">{{ $errorMessage }}</span>
            </div>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @endif

        {{-- ============================================================
             ✅ GHANA: ADVANCE RENT & PAYMENT PHASE PANEL
             ============================================================ --}}
        @if(!$isTenantSigning && !$isLandlordSigning && $hasAdvanceRent)
        <div class="card border-l-4 mt-6" style="border-left-color: var(--primary);">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-hand-holding-usd mr-2" style="color: var(--primary);"></i>
                        Advance Rent & Payment Structure
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full badge-primary">Ghana</span>
                    </h3>

                    {{-- ✅ GHANA: Compliance badge --}}
                    @if($isCompliant)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full badge-success">
                            <i class="fas fa-check-circle mr-1"></i> Compliant with {{ $governingLaw }}
                        </span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold rounded-full badge-warning">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Exceeds legal cap ({{ $legalMaxNew }} mo)
                        </span>
                    @endif
                </div>

                {{-- Phase indicator --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="p-4 rounded-lg text-center"
                         style="background-color: {{ $isInAdvancePhase ? 'rgba(var(--primary-rgb), 0.1)' : 'var(--card-bg)' }}; border: 1px solid {{ $isInAdvancePhase ? 'var(--primary)' : 'var(--border-color)' }};">
                        <div class="text-xs mb-1" style="color: var(--text-secondary);">Phase 1 — Advance</div>
                        <div class="text-sm font-medium" style="color: var(--text-primary);">
                            {{ $advancePeriodStart ? $advancePeriodStart->format('M j, Y') : '—' }}
                            →
                            {{ $advancePeriodEnd ? $advancePeriodEnd->format('M j, Y') : '—' }}
                        </div>
                        <div class="text-xs mt-1" style="color: {{ $isInAdvancePhase ? 'var(--primary)' : 'var(--text-secondary)' }};">
                            @if($isInAdvancePhase)
                                <i class="fas fa-circle text-xs mr-1"></i> Active — {{ $advanceMonthsRemaining }} mo remaining
                            @else
                                <i class="fas fa-check mr-1"></i> Completed
                            @endif
                        </div>
                    </div>

                    <div class="p-4 rounded-lg text-center"
                         style="background-color: {{ $isInMonthlyPhase ? 'rgba(var(--success-rgb), 0.1)' : 'var(--card-bg)' }}; border: 1px solid {{ $isInMonthlyPhase ? 'var(--success)' : 'var(--border-color)' }};">
                        <div class="text-xs mb-1" style="color: var(--text-secondary);">Phase 2 — Monthly</div>
                        <div class="text-sm font-medium" style="color: var(--text-primary);">
                            @if($paymentFrequency === 'monthly' && $firstMonthlyPayment)
                                Starts {{ $firstMonthlyPayment->format('M j, Y') }}
                            @elseif($isAdvanceOnly)
                                Not applicable — Full advance
                            @else
                                —
                            @endif
                        </div>
                        <div class="text-xs mt-1" style="color: {{ $isInMonthlyPhase ? 'var(--success)' : 'var(--text-secondary)' }};">
                            @if($isInMonthlyPhase)
                                <i class="fas fa-circle text-xs mr-1"></i> Active — Monthly billing
                            @elseif($isAdvanceOnly)
                                <i class="fas fa-info-circle mr-1"></i> Full advance lease
                            @else
                                <i class="fas fa-clock mr-1"></i> Pending
                            @endif
                        </div>
                    </div>

                    <div class="p-4 rounded-lg text-center" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div class="text-xs mb-1" style="color: var(--text-secondary);">Total Advance Paid</div>
                        <div class="text-lg font-bold" style="color: var(--primary);">
                            {{ $currencySymbol }} {{ number_format($advanceAmount, 2) }}
                        </div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            {{ $advanceMonths }} month(s) upfront
                        </div>
                    </div>
                </div>

                {{-- Details row --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                    <div class="p-3 rounded" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div style="color: var(--text-secondary);">Payment Frequency</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">
                            @if($isAdvanceOnly)
                                <i class="fas fa-money-bill-wave mr-1"></i> Full Advance
                            @else
                                <i class="fas fa-sync-alt mr-1"></i> Advance + Monthly
                            @endif
                        </div>
                    </div>
                    <div class="p-3 rounded" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div style="color: var(--text-secondary);">Monthly Rent (post-advance)</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">
                            {{ $currencySymbol }} {{ number_format($lease->monthly_rent, 2) }}
                        </div>
                    </div>
                    <div class="p-3 rounded" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div style="color: var(--text-secondary);">Due Day</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">
                            {{ $lease->payment_due_day }}{{ $lease->payment_due_day == 1 ? 'st' : ($lease->payment_due_day == 2 ? 'nd' : ($lease->payment_due_day == 3 ? 'rd' : 'th')) }} of month
                        </div>
                    </div>
                    <div class="p-3 rounded" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div style="color: var(--text-secondary);">Next Payment Due</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">
                            @if($lease->next_payment_due_date)
                                {{ $lease->next_payment_due_date->format('M j, Y') }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ✅ GHANA: Non-compliance note --}}
                @if(!$isCompliant)
                <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.08); border: 1px solid var(--warning);">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle mt-0.5 mr-2" style="color: var(--warning);"></i>
                        <div class="text-xs" style="color: var(--text-primary);">
                            <p class="font-semibold mb-1">Advance rent exceeds the legal maximum</p>
                            <p style="color: var(--text-secondary);">
                                This lease carries an advance rent of {{ $advanceMonths }} months, which exceeds the
                                {{ $legalMaxNew }}-month cap under the {{ $governingLaw }}.
                                @if($lease->advance_rent_acknowledged_at)
                                    The tenant's voluntary acknowledgement was recorded on
                                    {{ $lease->advance_rent_acknowledged_at->format('M j, Y') }}.
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ============================================================
             ✅ INVOICE: INVOICE SUMMARY PANEL
             ============================================================ --}}
        @if(!$isTenantSigning && !$isLandlordSigning && $invoices->count() > 0)
        <div class="card mt-6">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-file-invoice-dollar mr-2" style="color: var(--primary);"></i>
                        Invoice Summary
                    </h3>
                    @if($invoiceSummary['overdue_count'] > 0)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full badge-danger">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            {{ $invoiceSummary['overdue_count'] }} overdue
                        </span>
                    @endif
                </div>

                {{-- Summary cards --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                    <div class="p-3 rounded-lg text-center" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div class="text-xs" style="color: var(--text-secondary);">Total Invoiced</div>
                        <div class="text-lg font-bold mt-1" style="color: var(--text-primary);">
                            {{ $currencySymbol }} {{ number_format($invoiceSummary['total_invoiced'], 2) }}
                        </div>
                    </div>
                    <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <div class="text-xs" style="color: var(--text-secondary);">Collected</div>
                        <div class="text-lg font-bold mt-1" style="color: var(--success);">
                            {{ $currencySymbol }} {{ number_format($invoiceSummary['total_paid'], 2) }}
                        </div>
                    </div>
                    <div class="p-3 rounded-lg text-center" style="background-color: {{ $invoiceSummary['outstanding'] > 0 ? 'rgba(var(--warning-rgb), 0.05)' : 'rgba(var(--success-rgb), 0.05)' }}; border: 1px solid {{ $invoiceSummary['outstanding'] > 0 ? 'rgba(var(--warning-rgb), 0.3)' : 'rgba(var(--success-rgb), 0.3)' }};">
                        <div class="text-xs" style="color: var(--text-secondary);">Outstanding</div>
                        <div class="text-lg font-bold mt-1" style="color: {{ $invoiceSummary['outstanding'] > 0 ? 'var(--warning)' : 'var(--success)' }};">
                            {{ $currencySymbol }} {{ number_format($invoiceSummary['outstanding'], 2) }}
                        </div>
                    </div>
                    <div class="p-3 rounded-lg text-center" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div class="text-xs" style="color: var(--text-secondary);">Paid / Total</div>
                        <div class="text-lg font-bold mt-1" style="color: var(--text-primary);">
                            {{ $invoiceSummary['paid_count'] }} / {{ $invoices->count() }}
                        </div>
                    </div>
                </div>

                {{-- Invoice table (collapsed to show first 5) --}}
                <div class="overflow-x-auto rounded-lg" style="border: 1px solid var(--border-color);">
                    <table class="w-full text-sm">
                        <thead style="background-color: rgba(var(--primary-rgb), 0.05);">
                            <tr>
                                <th class="text-left p-2 font-medium" style="color: var(--text-secondary);">Reference</th>
                                <th class="text-left p-2 font-medium" style="color: var(--text-secondary);">Type</th>
                                <th class="text-left p-2 font-medium" style="color: var(--text-secondary);">Description</th>
                                <th class="text-right p-2 font-medium" style="color: var(--text-secondary);">Amount</th>
                                <th class="text-right p-2 font-medium" style="color: var(--text-secondary);">Paid</th>
                                <th class="text-right p-2 font-medium" style="color: var(--text-secondary);">Due Date</th>
                                <th class="text-center p-2 font-medium" style="color: var(--text-secondary);">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoices->take(8) as $invoice)
                            <tr style="border-top: 1px solid var(--border-color);">
                                <td class="p-2 font-mono text-xs" style="color: var(--text-primary);">{{ $invoice->reference }}</td>
                                <td class="p-2 text-xs" style="color: var(--text-secondary);">
                                    {{ ucfirst(str_replace('_', ' ', $invoice->invoice_type)) }}
                                </td>
                                <td class="p-2 text-xs" style="color: var(--text-secondary);">{{ $invoice->description }}</td>
                                <td class="p-2 text-right text-xs" style="color: var(--text-primary);">
                                    {{ $currencySymbol }} {{ number_format($invoice->amount, 2) }}
                                </td>
                                <td class="p-2 text-right text-xs" style="color: var(--success);">
                                    {{ $currencySymbol }} {{ number_format($invoice->amount_paid, 2) }}
                                </td>
                                <td class="p-2 text-right text-xs" style="color: var(--text-secondary);">
                                    {{ optional($invoice->due_date)->format('M j, Y') ?? '—' }}
                                </td>
                                <td class="p-2 text-center">
                                    @php
                                        $statusMap = [
                                            'pending' => ['class' => 'badge-warning',   'label' => 'Pending',  'icon' => 'clock'],
                                            'partial' => ['class' => 'badge-info',      'label' => 'Partial',  'icon' => 'adjust'],
                                            'paid'    => ['class' => 'badge-success',   'label' => 'Paid',     'icon' => 'check-circle'],
                                            'overdue' => ['class' => 'badge-danger',    'label' => 'Overdue',  'icon' => 'exclamation-triangle'],
                                            'void'    => ['class' => 'badge-secondary', 'label' => 'Void',     'icon' => 'ban'],
                                        ];
                                        $sm = $statusMap[$invoice->status] ?? $statusMap['pending'];
                                    @endphp
                                    <span class="px-2 py-0.5 text-xs rounded-full {{ $sm['class'] }}">
                                        <i class="fas fa-{{ $sm['icon'] }} mr-1"></i> {{ $sm['label'] }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($invoices->count() > 8)
                    <p class="text-xs text-center mt-3" style="color: var(--text-secondary);">
                        Showing 8 of {{ $invoices->count() }} invoices.
                    </p>
                @endif
            </div>
        </div>
        @endif

        {{-- ============================================================
             ✅ INVOICE: DEPOSIT TRACKING PROGRESS
             ============================================================ --}}
        @if(!$isTenantSigning && !$isLandlordSigning && $lease->security_deposit > 0)
        <div class="card mt-6">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-shield-alt mr-2" style="color: var(--primary);"></i>
                    Security Deposit Tracking
                </h3>

                @php
                    $depositTotal    = (float) $lease->security_deposit;
                    $depositCollected= (float) $lease->deposit_collected_so_far;
                    $depositPct      = $depositTotal > 0 ? min(100, round(($depositCollected / $depositTotal) * 100)) : 0;
                    $depositRemaining= max(0, $depositTotal - $depositCollected);
                @endphp

                <div class="grid grid-cols-3 gap-3 mb-4 text-xs">
                    <div class="p-3 rounded text-center" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div style="color: var(--text-secondary);">Total Required</div>
                        <div class="text-base font-bold mt-1" style="color: var(--text-primary);">
                            {{ $currencySymbol }} {{ number_format($depositTotal, 2) }}
                        </div>
                    </div>
                    <div class="p-3 rounded text-center" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <div style="color: var(--text-secondary);">Collected</div>
                        <div class="text-base font-bold mt-1" style="color: var(--success);">
                            {{ $currencySymbol }} {{ number_format($depositCollected, 2) }}
                        </div>
                    </div>
                    <div class="p-3 rounded text-center" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <div style="color: var(--text-secondary);">Remaining</div>
                        <div class="text-base font-bold mt-1" style="color: var(--warning);">
                            {{ $currencySymbol }} {{ number_format($depositRemaining, 2) }}
                        </div>
                    </div>
                </div>

                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                    <div class="h-3 rounded-full transition-all"
                         style="width: {{ $depositPct }}%; background-color: {{ $depositPct >= 100 ? 'var(--success)' : 'var(--primary)' }};"></div>
                </div>
                <div class="flex justify-between text-xs mt-2" style="color: var(--text-secondary);">
                    <span>{{ $depositPct }}% collected</span>
                    @if($lease->deposit_fully_paid_at)
                        <span class="text-green-600">
                            <i class="fas fa-check-circle mr-1"></i>
                            Fully paid {{ $lease->deposit_fully_paid_at->format('M j, Y') }}
                        </span>
                    @elseif($lease->deposit_payment_method === 'installment')
                        <span>
                            <i class="fas fa-sync-alt mr-1"></i>
                            Installment: {{ $lease->deposit_installment_months }} months
                        </span>
                    @endif
                </div>
            </div>
        </div>
        @endif

        <!-- Tenant Signing Section -->
        @if($isTenantSigning && $canTenantSign && $tenantSignatureUrl)
        <div class="card border-l-4 mb-6 mt-6" style="border-left-color: var(--primary);">
            <div class="p-6">
                <div class="flex items-start mb-4">
                    <div class="flex-shrink-0 mr-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-signature text-xl" style="color: var(--primary);"></i>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                            Sign Lease Agreement (Tenant)
                        </h3>
                        <p class="text-sm mb-4" style="color: var(--text-secondary);">
                            Please review the lease agreement below carefully before signing. Once signed, this becomes a legally binding document under the {{ $governingLaw }}.
                        </p>
                    </div>
                </div>

                <div id="signatureSection" class="mt-4">
                    <form id="signatureForm" method="POST" action="{{ $tenantSignatureUrl }}">
                        @csrf

                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-calendar-alt mr-1"></i> Signature Date
                            </label>
                            <input type="datetime-local"
                                   name="signed_at"
                                   id="signed_at"
                                   required
                                   class="index-custom-input w-full max-w-xs"
                                   value="{{ now()->format('Y-m-d\TH:i') }}">
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-signature mr-1"></i> Signature Type
                            </label>
                            <div class="flex space-x-4">
                                <label class="flex items-center">
                                    <input type="radio" name="signature_type" value="digital" checked class="index-custom-radio mr-2">
                                    <span class="text-sm" style="color: var(--text-primary);">Digital Signature</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="signature_type" value="upload" class="index-custom-radio mr-2">
                                    <span class="text-sm" style="color: var(--text-primary);">Upload Signature</span>
                                </label>
                            </div>
                        </div>

                        <div id="digitalSignatureSection" class="mb-6">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-paint-brush mr-1"></i> Draw Your Signature
                            </label>
                            <div class="border-2 border-dashed rounded-lg p-4" style="border-color: var(--border-color);">
                                <canvas id="signaturePad"
                                        class="w-full h-48 bg-white dark:bg-gray-800 rounded"
                                        style="border: 1px solid var(--border-color);"></canvas>
                                <input type="hidden" name="signature_data" id="signatureData">
                                <div class="mt-3 flex space-x-2">
                                    <button type="button" onclick="clearSignature()" class="btn-secondary px-3 py-1 text-sm rounded">
                                        <i class="fas fa-undo mr-1"></i> Clear
                                    </button>
                                    <button type="button" onclick="saveSignature()" class="btn-primary px-3 py-1 text-sm rounded text-white">
                                        <i class="fas fa-save mr-1"></i> Save Signature
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="uploadSignatureSection" class="mb-6 hidden">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-upload mr-1"></i> Upload Signature Image
                            </label>
                            <div class="border-2 border-dashed rounded-lg p-6 text-center"
                                 style="border-color: var(--border-color);"
                                 onclick="document.getElementById('signatureUpload').click()">
                                <i class="fas fa-cloud-upload-alt text-3xl mb-2" style="color: var(--secondary);"></i>
                                <p class="text-sm mb-2" style="color: var(--text-primary);">Click to upload signature image</p>
                                <p class="text-xs" style="color: var(--text-secondary);">PNG, JPG, or SVG (Max 2MB)</p>
                                <input type="file"
                                       id="signatureUpload"
                                       name="signature_file"
                                       accept=".png,.jpg,.jpeg,.svg"
                                       class="hidden"
                                       onchange="previewSignatureUpload(event)">
                            </div>
                            <div id="signaturePreview" class="mt-3 hidden">
                                <p class="text-sm font-medium mb-2" style="color: var(--text-primary);">Preview:</p>
                                <img id="signaturePreviewImage" class="max-w-xs border rounded" style="border-color: var(--border-color);">
                            </div>
                        </div>

                        <div class="mb-6">
                            <div class="flex items-start">
                                <input type="checkbox"
                                       id="agreement_check"
                                       name="agreement_check"
                                       required
                                       class="index-custom-checkbox mt-1 mr-3">
                                <label for="agreement_check" class="text-sm" style="color: var(--text-primary);">
                                    <span class="font-medium">I agree to the terms and conditions</span> of this lease agreement.
                                    I confirm I have read and understood all clauses, including advance rent, monthly payment terms,
                                    maintenance responsibilities, and termination conditions under the {{ $governingLaw }}.
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <button type="button"
                                    onclick="window.location.href='{{ route($routePrefix . '.lease-details', [$unit->id, $lease->id]) }}'"
                                    class="btn-secondary px-4 py-2 rounded-lg font-medium">
                                <i class="fas fa-times mr-2"></i> Cancel
                            </button>
                            <button type="submit" id="signSubmitBtn" class="btn-success px-4 py-2 rounded-lg font-medium text-white">
                                <i class="fas fa-signature mr-2"></i> Sign Lease Agreement
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        <!-- Landlord Signing Section -->
        @if($isLandlordSigning && $canLandlordSign && $landlordSignatureUrl)
        <div class="card border-l-4 mb-6 mt-6" style="border-left-color: var(--success);">
            <div class="p-6">
                <div class="flex items-start mb-4">
                    <div class="flex-shrink-0 mr-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--success-rgb), 0.1);">
                            <i class="fas fa-user-tie text-xl" style="color: var(--success);"></i>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                            Sign Lease Agreement (Landlord)
                        </h3>
                        <p class="text-sm mb-4" style="color: var(--text-secondary);">
                            As the landlord, your signature is required to finalize this lease agreement. Please review the terms carefully before signing.
                        </p>
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 mb-4 border" style="border-color: rgba(var(--info-rgb), 0.3);">
                            <p class="text-sm flex items-center" style="color: var(--info);">
                                <i class="fas fa-info-circle mr-2"></i>
                                <span>You are signing as: <strong>{{ $user->name }}</strong></span>
                            </p>
                        </div>
                    </div>
                </div>

                <div id="landlordSignatureSection" class="mt-4">
                    <form id="landlordSignatureForm" method="POST" action="{{ $landlordSignatureUrl }}">
                        @csrf

                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-calendar-alt mr-1"></i> Signature Date
                            </label>
                            <input type="datetime-local"
                                   name="signed_at"
                                   id="landlord_signed_at"
                                   required
                                   class="index-custom-input w-full max-w-xs"
                                   value="{{ now()->format('Y-m-d\TH:i') }}">
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-user-tie mr-1"></i> Signing As
                            </label>
                            <div class="bg-card rounded-lg p-3 border" style="border-color: var(--border-color);">
                                <p class="font-medium flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-user-tie mr-2"></i> {{ $user->name }}
                                </p>
                                <p class="text-sm mt-2" style="color: var(--text-secondary);">
                                    <i class="fas fa-envelope mr-2"></i> {{ $user->email }}
                                    @if($user->phone)
                                        <span class="ml-4"><i class="fas fa-phone mr-2"></i> {{ $user->phone }}</span>
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-signature mr-1"></i> Signature Type
                            </label>
                            <div class="flex space-x-4">
                                <label class="flex items-center">
                                    <input type="radio" name="signature_type" value="digital" checked class="index-custom-radio mr-2">
                                    <span class="text-sm" style="color: var(--text-primary);">Digital Signature</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="signature_type" value="upload" class="index-custom-radio mr-2">
                                    <span class="text-sm" style="color: var(--text-primary);">Upload Signature</span>
                                </label>
                            </div>
                        </div>

                        <div id="landlordDigitalSignatureSection" class="mb-6">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-paint-brush mr-1"></i> Draw Your Signature
                            </label>
                            <div class="border-2 border-dashed rounded-lg p-4" style="border-color: var(--border-color);">
                                <canvas id="landlordSignaturePad"
                                        class="w-full h-48 bg-white dark:bg-gray-800 rounded"
                                        style="border: 1px solid var(--border-color);"></canvas>
                                <input type="hidden" name="signature_data" id="landlordSignatureData">
                                <div class="mt-3 flex space-x-2">
                                    <button type="button" onclick="clearLandlordSignature()" class="btn-secondary px-3 py-1 text-sm rounded">
                                        <i class="fas fa-undo mr-1"></i> Clear
                                    </button>
                                    <button type="button" onclick="saveLandlordSignature()" class="btn-success px-3 py-1 text-sm rounded text-white">
                                        <i class="fas fa-save mr-1"></i> Save Signature
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="landlordUploadSignatureSection" class="mb-6 hidden">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-upload mr-1"></i> Upload Signature Image
                            </label>
                            <div class="border-2 border-dashed rounded-lg p-6 text-center"
                                 style="border-color: var(--border-color);"
                                 onclick="document.getElementById('landlordSignatureUpload').click()">
                                <i class="fas fa-cloud-upload-alt text-3xl mb-2" style="color: var(--success);"></i>
                                <p class="text-sm mb-2" style="color: var(--text-primary);">Click to upload signature image</p>
                                <p class="text-xs" style="color: var(--text-secondary);">PNG, JPG, or SVG (Max 2MB)</p>
                                <input type="file"
                                       id="landlordSignatureUpload"
                                       name="signature_file"
                                       accept=".png,.jpg,.jpeg,.svg"
                                       class="hidden"
                                       onchange="previewLandlordSignatureUpload(event)">
                            </div>
                            <div id="landlordSignaturePreview" class="mt-3 hidden">
                                <p class="text-sm font-medium mb-2" style="color: var(--text-primary);">Preview:</p>
                                <img id="landlordSignaturePreviewImage" class="max-w-xs border rounded" style="border-color: var(--border-color);">
                            </div>
                        </div>

                        <div class="mb-6">
                            <div class="flex items-start">
                                <input type="checkbox"
                                       id="landlord_agreement_check"
                                       name="agreement_check"
                                       required
                                       class="index-custom-checkbox mt-1 mr-3">
                                <label for="landlord_agreement_check" class="text-sm" style="color: var(--text-primary);">
                                    <span class="font-medium">I agree to the terms and conditions</span> of this lease agreement as the landlord.
                                    I confirm I have read and understood all clauses including advance rent caps under the {{ $governingLaw }},
                                    rent terms, maintenance responsibilities, and tenant obligations.
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <button type="button"
                                    onclick="window.location.href='{{ route($routePrefix . '.lease-details', [$unit->id, $lease->id]) }}'"
                                    class="btn-secondary px-4 py-2 rounded-lg font-medium">
                                <i class="fas fa-times mr-2"></i> Cancel
                            </button>
                            <button type="submit" id="landlordSignSubmitBtn" class="btn-success px-4 py-2 rounded-lg font-medium text-white">
                                <i class="fas fa-signature mr-2"></i> Sign as Landlord
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        <!-- Lease Information (view mode) -->
        @if(!$isTenantSigning && !$isLandlordSigning)
        <div class="card mt-6">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Lease Information
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h4 class="text-sm font-medium mb-3 flex items-center" style="color: var(--text-secondary);">
                            <i class="fas fa-users mr-2"></i> Parties
                        </h4>
                        <div class="space-y-3">
                            <div class="bg-card rounded-lg p-3 border" style="border-color: var(--border-color);">
                                <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-user-tie mr-1"></i> Landlord
                                </p>
                                <p class="font-medium" style="color: var(--text-primary);">{{ $lease->landlord->name }}</p>
                                @if($isTenant || $isAdmin || $isSuperAdmin)
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $lease->landlord->email }}
                                        @if($lease->landlord->phone)
                                            • {{ $lease->landlord->phone }}
                                        @endif
                                    </p>
                                @endif
                                @if($isLandlord && !$lease->landlord_signed_at)
                                    <div class="mt-3">
                                        <a href="{{ route($routePrefix . '.lease-details', [$unit->id, $lease->id]) }}?sign=true"
                                           class="btn-success w-full py-2 rounded-lg font-medium text-white inline-flex items-center justify-center">
                                            <i class="fas fa-signature mr-2"></i> Sign Now
                                        </a>
                                    </div>
                                @endif
                            </div>

                            <div class="bg-card rounded-lg p-3 border" style="border-color: var(--border-color);">
                                <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-user mr-1"></i> Tenant
                                </p>
                                <p class="font-medium" style="color: var(--text-primary);">{{ $lease->tenant->name }}</p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ $lease->tenant->email }}
                                    @if($lease->tenant->phone)
                                        • {{ $lease->tenant->phone }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-sm font-medium mb-3 flex items-center" style="color: var(--text-secondary);">
                            <i class="fas fa-home mr-2"></i> Property Details
                        </h4>
                        <div class="space-y-3">
                            <div class="bg-card rounded-lg p-3 border" style="border-color: var(--border-color);">
                                <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-building mr-1"></i> Property
                                </p>
                                <p class="font-medium" style="color: var(--text-primary);">{{ $unit->property->property_name }}</p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $unit->property->address }}</p>
                            </div>

                            <div class="bg-card rounded-lg p-3 border" style="border-color: var(--border-color);">
                                <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-door-closed mr-1"></i> Unit
                                </p>
                                <p class="font-medium" style="color: var(--text-primary);">
                                    Unit {{ $unit->unit_number }}
                                    @if($unit->unit_name)
                                        <span class="ml-1">({{ $unit->unit_name }})</span>
                                    @endif
                                </p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ $unit->bedrooms }} bedroom(s) • {{ $unit->bathrooms }} bathroom(s)
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <h4 class="text-sm font-medium mb-3 flex items-center" style="color: var(--text-secondary);">
                        <i class="fas fa-money-bill-wave mr-2"></i> Financial Terms
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-card rounded-lg p-3 border" style="border-color: rgba(var(--success-rgb), 0.3);">
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-calendar-check mr-1"></i> Monthly Rent
                            </p>
                            <p class="text-xl font-bold" style="color: var(--success);">
                                {{ $currencySymbol }} {{ number_format($lease->monthly_rent, 2) }}
                            </p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Due on {{ $lease->payment_due_day }}th of each month
                            </p>
                        </div>

                        <div class="bg-card rounded-lg p-3 border" style="border-color: rgba(var(--info-rgb), 0.3);">
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-shield-alt mr-1"></i> Security Deposit
                            </p>
                            <p class="text-xl font-bold" style="color: var(--info);">
                                {{ $currencySymbol }} {{ number_format($lease->security_deposit, 2) }}
                            </p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ $lease->is_deposit_fully_paid ? 'Fully collected' : 'Refundable at end of lease' }}
                            </p>
                        </div>

                        <div class="bg-card rounded-lg p-3 border" style="border-color: rgba(var(--warning-rgb), 0.3);">
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-exclamation-triangle mr-1"></i> Late Fee
                            </p>
                            <p class="text-xl font-bold" style="color: var(--warning);">
                                {{ $lease->late_fee_percentage }}%
                            </p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                After {{ $lease->grace_period_days }} days grace period
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <h4 class="text-sm font-medium mb-3 flex items-center" style="color: var(--text-secondary);">
                        <i class="fas fa-calendar-alt mr-2"></i> Lease Period
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-card rounded-lg p-3 border" style="border-color: var(--border-color);">
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-play-circle mr-1"></i> Start Date
                            </p>
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $lease->start_date->format('F j, Y') }}
                            </p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                @php $startDaysAgo = now()->diffInDays($lease->start_date); @endphp
                                @if($startDaysAgo > 0)
                                    Started {{ $startDaysAgo }} day(s) ago
                                @elseif($startDaysAgo < 0)
                                    Starts in {{ abs($startDaysAgo) }} day(s)
                                @else
                                    Starts today
                                @endif
                            </p>
                        </div>

                        <div class="bg-card rounded-lg p-3 border" style="border-color: var(--border-color);">
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-stop-circle mr-1"></i> End Date
                            </p>
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $lease->end_date ? $lease->end_date->format('F j, Y') : 'Month-to-month' }}
                            </p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                @if($daysRemaining > 0)
                                    {{ $daysRemaining }} day(s) remaining
                                @elseif($daysRemaining < 0)
                                    Expired {{ abs($daysRemaining) }} day(s) ago
                                @elseif($lease->end_date)
                                    Ends today
                                @else
                                    No fixed end date
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Terms and Conditions -->
        <div class="card mt-6">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-file-alt mr-2" style="color: var(--primary);"></i> Terms & Conditions
                </h3>

                <div class="bg-card rounded-lg p-4 border mb-4" style="border-color: var(--border-color); max-height: 400px; overflow-y: auto;">
                    @if($lease->terms)
                        {!! nl2br(e($lease->terms)) !!}
                    @else
                        <div class="text-center py-8">
                            <i class="fas fa-file-alt text-3xl mb-3" style="color: var(--text-secondary);"></i>
                            <p style="color: var(--text-secondary);">No detailed terms provided. Standard lease terms apply.</p>
                        </div>
                    @endif
                </div>

                @if($lease->special_terms)
                <div class="mt-4">
                    <h4 class="text-sm font-medium mb-2 flex items-center" style="color: var(--text-secondary);">
                        <i class="fas fa-star mr-2"></i> Special Terms
                    </h4>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-3 border" style="border-color: rgba(var(--warning-rgb), 0.3);">
                        {!! nl2br(e($lease->special_terms)) !!}
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>

    <!-- Right Column - Sidebar -->
    <div class="lg:col-span-1">
        <!-- Signatures Status Card -->
        <div class="card mb-6">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-signature mr-2" style="color: var(--primary);"></i> Signatures Status
                </h3>

                <div class="space-y-3">
                    <!-- Landlord -->
                    <div class="bg-card rounded-lg p-3 border" style="border-color: {{ $landlordSigned ? 'rgba(var(--success-rgb), 0.3)' : 'rgba(var(--warning-rgb), 0.3)' }};">
                        <div class="flex justify-between items-center mb-2">
                            <p class="text-sm font-medium flex items-center"
                               style="color: {{ $landlordSigned ? 'var(--success)' : 'var(--warning)' }};">
                                <i class="fas fa-user-tie mr-2"></i> Landlord
                            </p>
                            @if($landlordSigned)
                                <span class="px-2 py-1 text-xs rounded-full badge-success">
                                    <i class="fas fa-check mr-1"></i> Signed
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs rounded-full badge-warning">
                                    <i class="fas fa-clock mr-1"></i> Pending
                                </span>
                            @endif
                        </div>
                        @if($landlordSigned)
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-calendar mr-1"></i>
                                {{ $lease->landlord_signed_at->format('M d, Y H:i') }}
                            </p>
                            @if($lease->landlord_signature_type)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-pen mr-1"></i>
                                    {{ ucfirst($lease->landlord_signature_type) }} signature
                                </p>
                            @endif

                            @if($landlordSigned && !$lease->landlord_witness_signature && ($isLandlord || $isAdmin || $isSuperAdmin))
                                <div class="mt-3">
                                    <a href="{{ route('witness.landlord-sign-form', [$unit->id, $lease->id]) }}"
                                       target="_blank"
                                       class="btn-secondary w-full py-2 rounded-lg font-medium inline-flex items-center justify-center">
                                        <i class="fas fa-user-plus mr-2"></i> Add Witness Signature
                                    </a>
                                </div>
                            @endif
                        @else
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i> Awaiting landlord signature
                            </p>
                        @endif

                        @if($isLandlord && !$landlordSigned && !$isLandlordSigning)
                            <div class="mt-3">
                                <a href="{{ route($routePrefix . '.lease-details', [$unit->id, $lease->id]) }}?sign=true"
                                   class="btn-success w-full py-2 rounded-lg font-medium text-white inline-flex items-center justify-center">
                                    <i class="fas fa-signature mr-2"></i> Sign Now
                                </a>
                            </div>
                        @endif
                    </div>

                    <!-- Tenant -->
                    <div class="bg-card rounded-lg p-3 border" style="border-color: {{ $tenantSigned ? 'rgba(var(--success-rgb), 0.3)' : 'rgba(var(--warning-rgb), 0.3)' }};">
                        <div class="flex justify-between items-center mb-2">
                            <p class="text-sm font-medium flex items-center"
                               style="color: {{ $tenantSigned ? 'var(--success)' : 'var(--warning)' }};">
                                <i class="fas fa-user mr-2"></i> Tenant
                            </p>
                            @if($tenantSigned)
                                <span class="px-2 py-1 text-xs rounded-full badge-success">
                                    <i class="fas fa-check mr-1"></i> Signed
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs rounded-full badge-warning">
                                    <i class="fas fa-clock mr-1"></i> Pending
                                </span>
                            @endif
                        </div>
                        @if($tenantSigned)
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-calendar mr-1"></i>
                                {{ $lease->tenant_signed_at->format('M d, Y H:i') }}
                            </p>
                            @if($lease->tenant_signature_type)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-pen mr-1"></i>
                                    {{ ucfirst($lease->tenant_signature_type) }} signature
                                </p>
                            @endif

                            @if($tenantSigned && !$lease->tenant_witness_signature && ($isTenant || $isAdmin || $isSuperAdmin))
                                <div class="mt-3">
                                    <a href="{{ route('witness.tenant-sign-form', [$unit->id, $lease->id]) }}"
                                       target="_blank"
                                       class="btn-secondary w-full py-2 rounded-lg font-medium inline-flex items-center justify-center">
                                        <i class="fas fa-user-plus mr-2"></i> Add Witness Signature
                                    </a>
                                </div>
                            @endif
                        @else
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                @if($isTenant) You haven't signed yet
                                @else Awaiting tenant signature
                                @endif
                            </p>
                        @endif
                    </div>
                </div>

                @if(!$landlordSigned || !$tenantSigned)
                <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                    <h4 class="text-sm font-medium mb-2 flex items-center" style="color: var(--text-secondary);">
                        <i class="fas fa-tasks mr-2"></i> Signing Progress
                    </h4>
                    <div class="relative pt-1">
                        <div class="flex mb-2 items-center justify-between">
                            <div>
                                <span class="text-xs font-semibold inline-block" style="color: var(--primary);">
                                    @php
                                        $totalSignatures = ($landlordSigned ? 1 : 0) + ($tenantSigned ? 1 : 0);
                                        $percentage = ($totalSignatures / 2) * 100;
                                    @endphp
                                    {{ $totalSignatures }}/2 Signatures
                                </span>
                            </div>
                            <div class="text-right">
                                <span class="text-xs font-semibold inline-block" style="color: var(--primary);">
                                    {{ round($percentage) }}%
                                </span>
                            </div>
                        </div>
                        <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-gray-200 dark:bg-gray-700">
                            <div style="width: {{ $percentage }}%; background-color: var(--primary);"
                                 class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center"></div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- ✅ GHANA: Next Payment Card -->
        @if(!$isTenantSigning && !$isLandlordSigning && $hasAdvanceRent)
        <div class="card mb-6">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-calendar-day mr-2" style="color: var(--primary);"></i> Next Payment
                </h3>

                @if($isInAdvancePhase)
                    <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                        <i class="fas fa-calendar-check text-3xl mb-2" style="color: var(--primary);"></i>
                        <p class="text-sm" style="color: var(--text-secondary);">Advance rent covers this period</p>
                        <p class="text-lg font-bold mt-2" style="color: var(--primary);">
                            {{ $advanceMonthsRemaining }} month(s) remaining
                        </p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Advance phase ends {{ $advancePeriodEnd->format('F j, Y') }}
                        </p>
                    </div>
                @else
                    @php $nextDue = $lease->next_payment_due_date; @endphp
                    @if($nextDue)
                        <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                            <i class="fas fa-money-bill-wave text-3xl mb-2" style="color: var(--success);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">Next monthly rent due</p>
                            <p class="text-lg font-bold mt-2" style="color: var(--success);">
                                {{ $currencySymbol }} {{ number_format($lease->next_payment_amount, 2) }}
                            </p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ $nextDue->format('F j, Y') }}
                                ({{ (int) now()->diffInDays($nextDue, false) }} days)
                            </p>
                        </div>
                    @else
                        <p class="text-sm text-center" style="color: var(--text-secondary);">No upcoming payments</p>
                    @endif
                @endif
            </div>
        </div>
        @endif

        <!-- Quick Actions Card (view mode) -->
        @if(!$isTenantSigning && !$isLandlordSigning)
        <div class="card mb-6">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-cogs mr-2" style="color: var(--primary);"></i> Quick Actions
                </h3>

                <div class="space-y-2">
                    @if($isLandlord)
                        @if($lease->status == 'active' && $lease->end_date && $lease->end_date->diffInDays(now()) <= 60)
                            <a href="{{ route($routePrefix . '.renew-lease', $unit->id) }}"
                               class="flex items-center p-3 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                               style="color: var(--text-primary); border: 1px solid var(--border-color);">
                                <i class="fas fa-sync-alt mr-3" style="color: var(--primary);"></i>
                                <span>Renew Lease</span>
                            </a>
                        @endif

                        @if($lease->status == 'active')
                            <button onclick="showTerminateModal()"
                                    class="w-full flex items-center p-3 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition"
                                    style="color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                <i class="fas fa-times-circle mr-3"></i>
                                <span>Terminate Lease</span>
                            </button>
                        @endif

                        @if(!$lease->landlord_signed_at)
                            <a href="{{ route($routePrefix . '.lease-details', [$unit->id, $lease->id]) }}?sign=true"
                               class="flex items-center p-3 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20 transition"
                               style="color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                <i class="fas fa-signature mr-3"></i>
                                <span>Sign as Landlord</span>
                            </a>
                        @endif

                    @elseif($isTenant)
                        @if(!$lease->tenant_signed_at)
                            <a href="{{ route($routePrefix . '.lease-details', [$unit->id, $lease->id]) }}?sign=true"
                               class="flex items-center p-3 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20 transition"
                               style="color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                <i class="fas fa-signature mr-3"></i>
                                <span>Sign Lease Agreement</span>
                            </a>
                        @endif

                        @if($lease->status == 'active')
                            <a href="mailto:{{ $lease->landlord->email }}?subject=Question about lease for Unit {{ $unit->unit_number }}"
                               class="flex items-center p-3 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition"
                               style="color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                <i class="fas fa-envelope mr-3"></i>
                                <span>Contact Landlord</span>
                            </a>
                        @endif

                    @elseif($isAdmin || $isSuperAdmin)
                        @if($lease->status == 'active')
                            <button onclick="showTerminateModal()"
                                    class="w-full flex items-center p-3 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition"
                                    style="color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                <i class="fas fa-times-circle mr-3"></i>
                                <span>Terminate Lease</span>
                            </button>
                        @endif

                        @if($unit->property->landlord)
                            <a href="mailto:{{ $unit->property->landlord->email }}?subject=Lease #{{ $lease->id }} for Unit {{ $unit->unit_number }}"
                               class="flex items-center p-3 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition"
                               style="color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                <i class="fas fa-user-tie mr-3"></i>
                                <span>Contact Landlord</span>
                            </a>
                        @endif

                        <a href="mailto:{{ $lease->tenant->email }}?subject=Lease #{{ $lease->id }} for Unit {{ $unit->unit_number }}"
                           class="flex items-center p-3 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition"
                           style="color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <i class="fas fa-user mr-3"></i>
                            <span>Contact Tenant</span>
                        </a>
                    @endif

                    <a href="{{ route($routePrefix . '.lease-download-pdf', [$unit->id, $lease->id]) }}"
                       target="_blank"
                       class="flex items-center p-3 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                       style="color: var(--text-primary); border: 1px solid var(--border-color);">
                        <i class="fas fa-file-pdf mr-3" style="color: var(--danger);"></i>
                        <span>Download PDF</span>
                    </a>

                    <a href="{{ route($routePrefix . '.show', $unit->id) }}"
                       class="flex items-center p-3 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                       style="color: var(--text-primary); border: 1px solid var(--border-color);">
                        <i class="fas fa-home mr-3" style="color: var(--primary);"></i>
                        <span>View Unit Details</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Metadata Card -->
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Metadata
                </h3>

                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Lease ID:</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">#{{ $lease->id }}</span>
                    </div>

                    @if($lease->agreement_number)
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Agreement #:</span>
                        <span class="text-sm font-mono" style="color: var(--text-primary);">{{ $lease->agreement_number }}</span>
                    </div>
                    @endif

                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Created:</span>
                        <span class="text-sm" style="color: var(--text-primary);">{{ $lease->created_at->format('M d, Y') }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Created By:</span>
                        <span class="text-sm" style="color: var(--text-primary);">{{ $lease->creator->name ?? 'System' }}</span>
                    </div>

                    @if($lease->updated_at && $lease->updated_at != $lease->created_at)
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Last Updated:</span>
                        <span class="text-sm" style="color: var(--text-primary);">{{ $lease->updated_at->format('M d, Y') }}</span>
                    </div>
                    @endif

                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Lease Type:</span>
                        <span class="text-sm font-medium badge-primary px-2 py-1 rounded text-xs">
                            {{ ucfirst(str_replace('_', ' ', $lease->lease_type)) }}
                        </span>
                    </div>

                    @if($lease->duration_months)
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Duration:</span>
                        <span class="text-sm" style="color: var(--text-primary);">{{ $lease->duration_months }} months</span>
                    </div>
                    @endif

                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Notice Period:</span>
                        <span class="text-sm" style="color: var(--text-primary);">{{ $lease->notice_period_days }} days</span>
                    </div>

                    @if($hasAdvanceRent)
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Advance Rent:</span>
                        <span class="text-sm" style="color: var(--text-primary);">
                            {{ $advanceMonths }} mo / {{ $currencySymbol }} {{ number_format($advanceAmount, 2) }}
                        </span>
                    </div>
                    @endif

                    @if($lease->early_termination_fee)
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Early Termination:</span>
                        <span class="text-sm" style="color: var(--text-primary);">{{ $currencySymbol }} {{ number_format($lease->early_termination_fee, 2) }}</span>
                    </div>
                    @endif

                    @if($lease->utility_deposit)
                    <div class="flex justify-between">
                        <span class="text-sm" style="color: var(--text-secondary);">Utility Deposit:</span>
                        <span class="text-sm" style="color: var(--text-primary);">{{ $currencySymbol }} {{ number_format($lease->utility_deposit, 2) }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Terminate Lease Modal -->
@if(($isLandlord || $isAdmin || $isSuperAdmin) && $lease->status == 'active' && !$isTenantSigning && !$isLandlordSigning)
<div id="terminateModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideTerminateModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Terminate Lease Agreement
                </h3>
                <button type="button" onclick="hideTerminateModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form method="POST" action="{{ route($routePrefix . '.terminate-lease', [$unit->id, $lease->id]) }}">
                @csrf
                @method('POST')
                <div class="modal-body">
                    <div class="mb-4">
                        <div class="mb-3 p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                            <p class="font-medium flex items-center" style="color: var(--danger);">
                                <i class="fas fa-exclamation-triangle mr-2"></i> Warning
                            </p>
                            <ul class="text-xs mt-2 space-y-1" style="color: var(--text-secondary);">
                                <li class="flex items-start">
                                    <i class="fas fa-times mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>Mark the lease as terminated</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-user-times mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>Update tenant status to "terminated"</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-file-invoice mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>Void any future pending invoices</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-hand-holding-usd mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>Calculate advance-rent refund for unused period</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-home mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>Mark the unit as "under maintenance"</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-calendar-alt mr-1"></i> Termination Date *
                        </label>
                        <input type="date"
                               name="termination_date"
                               required
                               class="index-custom-input w-full"
                               value="{{ date('Y-m-d') }}"
                               min="{{ date('Y-m-d') }}"
                               max="{{ $lease->end_date ? $lease->end_date->format('Y-m-d') : '' }}">
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            @if($lease->end_date)
                                Lease ends on {{ $lease->end_date->format('M d, Y') }}
                            @endif
                        </p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-comment-alt mr-1"></i> Termination Reason *
                        </label>
                        <textarea name="termination_reason"
                                  required
                                  rows="3"
                                  class="index-custom-textarea w-full"
                                  placeholder="Please provide a detailed reason for terminating the lease..."></textarea>
                    </div>

                    @if($lease->security_deposit > 0)
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox"
                                   name="refund_deposit"
                                   id="refund_deposit"
                                   value="1"
                                   class="index-custom-checkbox mr-2">
                            <span class="text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-shield-alt mr-1"></i> Refund security deposit
                            </span>
                        </label>
                        <div id="refundAmountField" class="mt-2 hidden">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Refund Amount *
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <span class="font-medium" style="color: var(--text-secondary);">{{ $currencySymbol }}</span>
                                </div>
                                <input type="number"
                                       name="deposit_refund_amount"
                                       min="0"
                                       max="{{ $lease->security_deposit }}"
                                       step="0.01"
                                       class="index-custom-input w-full pl-16"
                                       placeholder="0.00">
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideTerminateModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button type="submit"
                            class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-times-circle mr-2"></i> Terminate Lease
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
@if($isTenantSigning && $canTenantSign)
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
@endif

@if($isLandlordSigning && $canLandlordSign)
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
@endif

<script>
document.addEventListener('DOMContentLoaded', function() {
    initLeaseDetails();

    setTimeout(() => {
        document.querySelectorAll('.bg-green-100, .bg-red-100').forEach(msg => msg.style.display = 'none');
    }, 5000);

    // ✅ Terminate modal: refund checkbox toggle
    const refundCheckbox = document.getElementById('refund_deposit');
    const refundField = document.getElementById('refundAmountField');
    if (refundCheckbox && refundField) {
        refundCheckbox.addEventListener('change', function() {
            refundField.classList.toggle('hidden', !this.checked);
        });
    }

    // Signature type toggle (for both tenant and landlord)
    document.querySelectorAll('input[name="signature_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const isTenant = this.closest('#signatureSection') !== null;
            const isLandlord = this.closest('#landlordSignatureSection') !== null;

            if (isTenant) {
                const d = document.getElementById('digitalSignatureSection');
                const u = document.getElementById('uploadSignatureSection');
                if (this.value === 'digital') {
                    d?.classList.remove('hidden');
                    u?.classList.add('hidden');
                } else {
                    d?.classList.add('hidden');
                    u?.classList.remove('hidden');
                }
            } else if (isLandlord) {
                const d = document.getElementById('landlordDigitalSignatureSection');
                const u = document.getElementById('landlordUploadSignatureSection');
                if (this.value === 'digital') {
                    d?.classList.remove('hidden');
                    u?.classList.add('hidden');
                } else {
                    d?.classList.add('hidden');
                    u?.classList.remove('hidden');
                }
            }
        });
    });
});

function initLeaseDetails() {
    initModals();
    @if($isTenantSigning && $canTenantSign)
    initSignaturePad();
    @endif
    @if($isLandlordSigning && $canLandlordSign)
    initLandlordSignaturePad();
    @endif
}

function initModals() {
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') hideTerminateModal();
    });
}

function showTerminateModal() {
    const modal = document.getElementById('terminateModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function hideTerminateModal() {
    const modal = document.getElementById('terminateModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

@if($isTenantSigning && $canTenantSign)
let signaturePad = null;

function initSignaturePad() {
    const canvas = document.getElementById('signaturePad');
    if (canvas) {
        signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgba(255, 255, 255, 0)',
            penColor: 'rgb(0, 0, 0)',
            minWidth: 0.5, maxWidth: 2.5, throttle: 16, minDistance: 5
        });
        window.addEventListener('resize', resizeTenantCanvas);
        resizeTenantCanvas();
    }
}

function resizeTenantCanvas() {
    const canvas = document.getElementById('signaturePad');
    if (!canvas) return;
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    canvas.width = canvas.offsetWidth * ratio;
    canvas.height = canvas.offsetHeight * ratio;
    canvas.getContext("2d").scale(ratio, ratio);
    signaturePad?.clear();
}

function clearSignature() {
    if (signaturePad) {
        signaturePad.clear();
        document.getElementById('signatureData').value = '';
    }
}

function saveSignature() {
    if (signaturePad && !signaturePad.isEmpty()) {
        document.getElementById('signatureData').value = signaturePad.toDataURL();
        alert('Signature saved successfully! You can now submit the form.');
    } else {
        alert('Please draw your signature first.');
    }
}

function previewSignatureUpload(event) {
    const input = event.target;
    const preview = document.getElementById('signaturePreview');
    const previewImage = document.getElementById('signaturePreviewImage');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            previewImage.src = e.target.result;
            preview.classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

document.getElementById('signatureForm')?.addEventListener('submit', function(e) {
    const signatureType = document.querySelector('#signatureSection input[name="signature_type"]:checked').value;
    const agreementCheck = document.getElementById('agreement_check').checked;
    if (!agreementCheck) { e.preventDefault(); alert('You must agree to the terms and conditions before signing.'); return; }
    if (signatureType === 'digital' && !document.getElementById('signatureData').value) {
        e.preventDefault(); alert('Please save your digital signature before submitting.'); return;
    }
    if (signatureType !== 'digital' && !document.getElementById('signatureUpload').files[0]) {
        e.preventDefault(); alert('Please upload a signature image before submitting.'); return;
    }
});
@endif

@if($isLandlordSigning && $canLandlordSign)
let landlordSignaturePad = null;

function initLandlordSignaturePad() {
    const canvas = document.getElementById('landlordSignaturePad');
    if (canvas) {
        landlordSignaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgba(255, 255, 255, 0)',
            penColor: 'rgb(0, 0, 0)',
            minWidth: 0.5, maxWidth: 2.5, throttle: 16, minDistance: 5
        });
        window.addEventListener('resize', resizeLandlordCanvas);
        resizeLandlordCanvas();
    }
}

function resizeLandlordCanvas() {
    const canvas = document.getElementById('landlordSignaturePad');
    if (!canvas) return;
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    canvas.width = canvas.offsetWidth * ratio;
    canvas.height = canvas.offsetHeight * ratio;
    canvas.getContext("2d").scale(ratio, ratio);
    landlordSignaturePad?.clear();
}

function clearLandlordSignature() {
    if (landlordSignaturePad) {
        landlordSignaturePad.clear();
        document.getElementById('landlordSignatureData').value = '';
    }
}

function saveLandlordSignature() {
    if (landlordSignaturePad && !landlordSignaturePad.isEmpty()) {
        document.getElementById('landlordSignatureData').value = landlordSignaturePad.toDataURL();
        alert('Signature saved successfully! You can now submit the form.');
    } else {
        alert('Please draw your signature first.');
    }
}

function previewLandlordSignatureUpload(event) {
    const input = event.target;
    const preview = document.getElementById('landlordSignaturePreview');
    const previewImage = document.getElementById('landlordSignaturePreviewImage');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            previewImage.src = e.target.result;
            preview.classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

document.getElementById('landlordSignatureForm')?.addEventListener('submit', function(e) {
    const signatureType = document.querySelector('#landlordSignatureSection input[name="signature_type"]:checked').value;
    const agreementCheck = document.getElementById('landlord_agreement_check').checked;
    if (!agreementCheck) { e.preventDefault(); alert('You must agree to the terms and conditions before signing.'); return; }
    if (signatureType === 'digital' && !document.getElementById('landlordSignatureData').value) {
        e.preventDefault(); alert('Please save your digital signature before submitting.'); return;
    }
    if (signatureType !== 'digital' && !document.getElementById('landlordSignatureUpload').files[0]) {
        e.preventDefault(); alert('Please upload a signature image before submitting.'); return;
    }
});
@endif

@if($isTenantSigning || $isLandlordSigning)
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        const section = document.getElementById('signatureSection') || document.getElementById('landlordSignatureSection');
        section?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 300);
});
@endif
</script>

<style>
/* Same styles as your original - apply consistent theming */
.index-custom-input,
.index-custom-textarea,
.index-custom-checkbox,
.index-custom-radio {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

.index-custom-input:focus,
.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.index-custom-checkbox,
.index-custom-radio {
    width: auto;
    margin-right: 0.5rem;
}

.badge-success    { background-color: rgba(var(--success-rgb), 0.1) !important; color: var(--success) !important; border: 1px solid rgba(var(--success-rgb), 0.3) !important; }
.badge-warning    { background-color: rgba(var(--warning-rgb), 0.1) !important; color: var(--warning) !important; border: 1px solid rgba(var(--warning-rgb), 0.3) !important; }
.badge-danger     { background-color: rgba(var(--danger-rgb), 0.1) !important; color: var(--danger) !important; border: 1px solid rgba(var(--danger-rgb), 0.3) !important; }
.badge-info       { background-color: rgba(var(--info-rgb), 0.1) !important; color: var(--info) !important; border: 1px solid rgba(var(--info-rgb), 0.3) !important; }
.badge-primary    { background-color: rgba(var(--primary-rgb), 0.1) !important; color: var(--primary) !important; border: 1px solid rgba(var(--primary-rgb), 0.3) !important; }
.badge-secondary  { background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; }

.btn-primary    { background-color: var(--primary) !important; color: white !important; border: 1px solid var(--primary) !important; transition: all 0.2s ease; }
.btn-primary:hover { background-color: var(--secondary) !important; border-color: var(--secondary) !important; transform: translateY(-1px); }

.btn-secondary  { background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; transition: all 0.2s ease; }
.btn-secondary:hover { background-color: rgba(var(--secondary-rgb), 0.2) !important; transform: translateY(-1px); }

.btn-danger     { background-color: var(--danger) !important; color: white !important; border: 1px solid var(--danger) !important; transition: all 0.2s ease; }
.btn-danger:hover { background-color: #dc3545 !important; transform: translateY(-1px); }

.btn-success    { background-color: var(--success) !important; color: white !important; border: 1px solid var(--success) !important; transition: all 0.2s ease; }
.btn-success:hover { background-color: #28a745 !important; transform: translateY(-1px); }

.btn-modern     { background: linear-gradient(to right, var(--primary), var(--secondary)) !important; color: white !important; border: none !important; transition: all 0.2s ease; }
.btn-modern:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3); }

.modal-container { background: var(--card-bg); border: 1px solid var(--border-color); max-height: 90vh; overflow-y: auto; border-radius: 16px; }
.modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
.modal-body { padding: 1.5rem; }
.modal-footer { padding: 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; }
.modal-close-btn { padding: 0.5rem; border-radius: 0.375rem; transition: background-color 0.2s; cursor: pointer; background: none; border: none; }
.modal-close-btn:hover { background-color: rgba(0, 0, 0, 0.05); }

#signaturePad, #landlordSignaturePad { touch-action: none; cursor: crosshair; }

@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.md\:grid-cols-2, .grid.grid-cols-1.md\:grid-cols-3 { grid-template-columns: 1fr; }
    .modal-container { width: 95%; max-height: 80vh; margin: 0.5rem; }
}

.card { border: 1px solid var(--border-color); border-radius: 12px; background-color: var(--card-bg); transition: all 0.3s ease; }
.card:hover { box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); }

.bg-card::-webkit-scrollbar { width: 6px; }
.bg-card::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.05); border-radius: 3px; }
.bg-card::-webkit-scrollbar-thumb { background: rgba(0, 0, 0, 0.2); border-radius: 3px; }
.bg-card::-webkit-scrollbar-thumb:hover { background: rgba(0, 0, 0, 0.3); }

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
#signatureSection, #landlordSignatureSection { animation: fadeInUp 0.5s ease-out; }
</style>
@endsection