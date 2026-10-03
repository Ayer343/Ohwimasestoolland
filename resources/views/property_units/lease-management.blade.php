{{-- lease-management.blade.php --}}
@php
    // ========== AUTH & ROLE RESOLUTION ==========
    $user = auth()->user();

    $isLandlord   = $user->isLandlord()   || $user->hasRole('landlord');
    $isTenant     = $user->isTenant()     || $user->hasRole('tenant');
    $isAdmin      = $user->isAdmin()      || $user->hasRole('admin');
    $isSuperAdmin = $user->isSuperAdmin() || $user->hasRole('super-admin');

    $hasLandlordRole = $isLandlord;
    $canAdminAccess  = $isAdmin || $isSuperAdmin;

    // ========== LAYOUT ==========
    $layout = $isLandlord ? 'layouts.landlord'
            : ($isTenant ? 'layouts.tenant' : 'layouts.app');

    // ========== PAGE TITLE ==========
    $pageTitle = 'Lease Management - ' . $unit->unit_number;
    if ($isTenant) {
        $pageTitle = 'My Lease - ' . $unit->unit_number;
    }

    // ========== SESSION MESSAGES ==========
    $successMessage = session('success');
    $errorMessage   = session('error');

    // ========== LEASE ACCESS ==========
    $currentLease = $unit->currentLease;

    $canCreateLease = $hasLandlordRole
        && $unit->tenant_status === 'approved'
        && !$currentLease;

    $canTerminateLease = ($hasLandlordRole || $canAdminAccess)
        && $currentLease
        && $currentLease->status === 'active';

    $canRenewLease = $hasLandlordRole
        && $currentLease
        && $currentLease->status === 'active'
        && $currentLease->end_date
        && $currentLease->end_date->diffInDays(now()) <= 60;

    $canTenantSignLease = $isTenant
        && $currentLease
        && !$currentLease->tenant_signed_at;

    $canLandlordSignLease = $hasLandlordRole
        && $currentLease
        && !$currentLease->landlord_signed_at;

    // ========== DAYS REMAINING ==========
    $daysRemaining   = $currentLease && $currentLease->end_date
        ? now()->diffInDays($currentLease->end_date, false)
        : 0;
    $monthsRemaining = $currentLease && $currentLease->end_date
        ? now()->diffInMonths($currentLease->end_date)
        : 0;

    // ========== ✅ GHANA: CURRENCY, LEGAL, PHASE ==========
    $currencySymbol = config('leases.ghana.currency.symbol', 'GH₵');
    $governingLaw   = config('leases.ghana.governing_law', 'Rent Act, 1963 (Act 220)');
    $legalMaxNew    = (int) config('leases.ghana.legal_max_advance_months.new_tenancy', 6);

    // Prefer values passed from controller; fall back to deriving here
    $currentPhase = $currentPhase ?? ($currentLease ? $currentLease->current_phase : null);
    $advanceInfo  = $advanceInfo  ?? ($currentLease ? [
        'advance_rent_months'        => $currentLease->advance_rent_months,
        'advance_rent_amount'        => $currentLease->advance_rent_amount,
        'advance_rent_period_start'  => optional($currentLease->advance_rent_period_start)->format('Y-m-d'),
        'advance_rent_period_end'    => optional($currentLease->advance_rent_period_end)->format('Y-m-d'),
        'payment_frequency'          => $currentLease->payment_frequency,
        'first_monthly_payment_date' => optional($currentLease->first_monthly_payment_date)->format('Y-m-d'),
        'is_compliant'               => ($currentLease->advance_rent_compliance_status ?? 'compliant') === 'compliant',
    ] : null);

    $hasAdvanceRent      = $currentLease && $currentLease->has_advance_rent;
    $isInAdvancePhase    = $currentLease && $currentLease->is_in_advance_phase;
    $isInMonthlyPhase    = $currentLease && $currentLease->is_in_monthly_phase;
    $advanceMonthsLeft   = $currentLease ? $currentLease->advance_months_remaining : 0;
    $isCompliant         = $advanceInfo['is_compliant'] ?? true;

    // ========== ✅ INVOICE: SUMMARY ==========
    $invoiceSummary = $invoiceSummary ?? [
        'total_invoiced' => 0,
        'total_paid'     => 0,
        'outstanding'    => 0,
        'overdue_count'  => 0,
    ];

    // ========== ROUTES ==========
    $renewRoute           = route('property-units.renew-lease', $unit->id);
    $leaseDetailsRoute    = $currentLease ? route('property-units.lease-details', [$unit->id, $currentLease->id]) : '#';
    $pdfDownloadRoute     = $currentLease ? route('property-units.lease-download-pdf', [$unit->id, $currentLease->id]) : '#';
    $createLeaseFormRoute = route('property-units.create-lease.form', $unit->id);
    $showUnitRoute        = route('property-units.show', $unit->id);
    $leaseManagementRoute = route('property-units.lease-management', $unit->id);

    $tenantDownloadRoute = $isTenant && $currentLease
        ? route('tenant.property-units.lease-download-pdf', [$unit->id, $currentLease->id])
        : '#';

    // Back-to-dashboard route based on selected role
    $selectedRole = session('selected_role', 'landlord');
    $backRouteMap = [
        'super-admin'         => route('super-admin.dashboard'),
        'admin'               => route('admin.dashboard'),
        'landlord'            => route('landlord.dashboard'),
        'tenant'              => route('tenant.dashboard'),
        'field-agent'         => route('field-agent.dashboard'),
        'security-personnel'  => route('security.dashboard'),
        'developer'           => route('developer.dashboard'),
    ];
    $backRoute = $backRouteMap[$selectedRole] ?? route('landlord.dashboard');
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
                        <i class="fas fa-file-contract text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i>
                        @if($hasLandlordRole)
                            Lease Management
                        @elseif($isTenant)
                            My Lease Agreement
                        @else
                            Lease Details
                        @endif

                        {{-- ✅ GHANA: Phase badge next to title --}}
                        @if($currentLease && $hasAdvanceRent)
                            @if($isInAdvancePhase)
                                <span class="ml-2 px-2 py-0.5 text-xs rounded-full badge-primary">
                                    <i class="fas fa-calendar-check mr-1"></i> Advance Phase
                                </span>
                            @elseif($isInMonthlyPhase)
                                <span class="ml-2 px-2 py-0.5 text-xs rounded-full badge-success">
                                    <i class="fas fa-calendar-day mr-1"></i> Monthly Phase
                                </span>
                            @endif
                        @endif
                    </h2>
                    <div class="text-sm flex flex-wrap items-center mt-1 gap-x-2" style="color: var(--text-secondary);">
                        <span><i class="fas fa-home mr-2"></i>{{ $unit->property->property_name }} - Unit {{ $unit->unit_number }}</span>
                        @if($unit->tenant)
                            <span>•</span>
                            <span><i class="fas fa-user mr-1"></i>{{ $unit->tenant->name }}</span>
                        @endif
                        @if($currentLease)
                            <span>•</span>
                            <span><i class="fas fa-calendar-alt mr-1"></i>{{ $currentLease->status }} • {{ $daysRemaining }} days remaining</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-sm flex items-center gap-2 flex-wrap justify-end" style="color: var(--text-secondary);">
                <span><i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}</span>

                {{-- ✅ GHANA: Compliance badge --}}
                @if($currentLease && $hasAdvanceRent)
                    @if($isCompliant)
                        <span class="px-2 py-1 rounded-full text-xs font-medium badge-success" title="Compliant with {{ $governingLaw }}">
                            <i class="fas fa-check-circle mr-1"></i> Compliant
                        </span>
                    @else
                        <span class="px-2 py-1 rounded-full text-xs font-medium badge-warning" title="Exceeds the {{ $legalMaxNew }}-month cap under {{ $governingLaw }}">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Exceeds Cap
                        </span>
                    @endif
                @endif

                <a href="{{ $showUnitRoute }}"
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Unit
                </a>

                <a href="{{ $backRoute }}"
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if($successMessage)
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
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
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
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

    <!-- Current Lease Section -->
    @if($currentLease)
        <!-- ✅ INVOICE: Compact Invoice Summary Strip -->
        @if($invoiceSummary['total_invoiced'] > 0)
        <div class="card">
            <div class="p-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="p-3 rounded-lg" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div class="text-xs" style="color: var(--text-secondary);">Total Invoiced</div>
                        <div class="text-base font-bold mt-1" style="color: var(--text-primary);">
                            {{ $currencySymbol }} {{ number_format($invoiceSummary['total_invoiced'], 2) }}
                        </div>
                    </div>
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <div class="text-xs" style="color: var(--text-secondary);">Collected</div>
                        <div class="text-base font-bold mt-1" style="color: var(--success);">
                            {{ $currencySymbol }} {{ number_format($invoiceSummary['total_paid'], 2) }}
                        </div>
                    </div>
                    <div class="p-3 rounded-lg"
                         style="background-color: {{ $invoiceSummary['outstanding'] > 0 ? 'rgba(var(--warning-rgb), 0.05)' : 'rgba(var(--success-rgb), 0.05)' }};
                                border: 1px solid {{ $invoiceSummary['outstanding'] > 0 ? 'rgba(var(--warning-rgb), 0.3)' : 'rgba(var(--success-rgb), 0.3)' }};">
                        <div class="text-xs" style="color: var(--text-secondary);">Outstanding</div>
                        <div class="text-base font-bold mt-1" style="color: {{ $invoiceSummary['outstanding'] > 0 ? 'var(--warning)' : 'var(--success)' }};">
                            {{ $currencySymbol }} {{ number_format($invoiceSummary['outstanding'], 2) }}
                        </div>
                    </div>
                    <div class="p-3 rounded-lg"
                         style="background-color: {{ $invoiceSummary['overdue_count'] > 0 ? 'rgba(var(--danger-rgb), 0.05)' : 'var(--card-bg)' }};
                                border: 1px solid {{ $invoiceSummary['overdue_count'] > 0 ? 'rgba(var(--danger-rgb), 0.3)' : 'var(--border-color)' }};">
                        <div class="text-xs" style="color: var(--text-secondary);">Overdue</div>
                        <div class="text-base font-bold mt-1" style="color: {{ $invoiceSummary['overdue_count'] > 0 ? 'var(--danger)' : 'var(--text-primary)' }};">
                            {{ $invoiceSummary['overdue_count'] }} invoice(s)
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- ✅ GHANA: Advance Rent & Payment Phase Panel -->
        @if($hasAdvanceRent && $advanceInfo)
        <div class="card border-l-4" style="border-left-color: var(--primary);">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-hand-holding-usd mr-2" style="color: var(--primary);"></i>
                        Advance Rent & Payment Structure
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full badge-primary">Ghana</span>
                    </h3>
                    @if(!$isCompliant)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full badge-warning">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Exceeds {{ $legalMaxNew }}-month legal cap
                        </span>
                    @endif
                </div>

                {{-- Phase + advance summary grid --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    {{-- Advance phase --}}
                    <div class="p-4 rounded-lg text-center"
                         style="background-color: {{ $isInAdvancePhase ? 'rgba(var(--primary-rgb), 0.1)' : 'var(--card-bg)' }};
                                border: 1px solid {{ $isInAdvancePhase ? 'var(--primary)' : 'var(--border-color)' }};">
                        <div class="text-xs mb-1" style="color: var(--text-secondary);">
                            <i class="fas fa-calendar-check mr-1"></i> Advance Phase
                        </div>
                        <div class="text-sm font-medium mt-1" style="color: var(--text-primary);">
                            {{ $advanceInfo['advance_rent_period_start'] ? \Carbon\Carbon::parse($advanceInfo['advance_rent_period_start'])->format('M j, Y') : '—' }}
                            →
                            {{ $advanceInfo['advance_rent_period_end'] ? \Carbon\Carbon::parse($advanceInfo['advance_rent_period_end'])->format('M j, Y') : '—' }}
                        </div>
                        <div class="text-xs mt-2"
                             style="color: {{ $isInAdvancePhase ? 'var(--primary)' : 'var(--text-secondary)' }};">
                            @if($isInAdvancePhase)
                                <i class="fas fa-circle text-xs mr-1"></i> Active — {{ $advanceMonthsLeft }} mo remaining
                            @else
                                <i class="fas fa-check mr-1"></i> Completed
                            @endif
                        </div>
                    </div>

                    {{-- Monthly phase --}}
                    <div class="p-4 rounded-lg text-center"
                         style="background-color: {{ $isInMonthlyPhase ? 'rgba(var(--success-rgb), 0.1)' : 'var(--card-bg)' }};
                                border: 1px solid {{ $isInMonthlyPhase ? 'var(--success)' : 'var(--border-color)' }};">
                        <div class="text-xs mb-1" style="color: var(--text-secondary);">
                            <i class="fas fa-calendar-day mr-1"></i> Monthly Phase
                        </div>
                        <div class="text-sm font-medium mt-1" style="color: var(--text-primary);">
                            @if($advanceInfo['payment_frequency'] === 'monthly' && $advanceInfo['first_monthly_payment_date'])
                                Starts {{ \Carbon\Carbon::parse($advanceInfo['first_monthly_payment_date'])->format('M j, Y') }}
                            @else
                                Not applicable — Full advance
                            @endif
                        </div>
                        <div class="text-xs mt-2"
                             style="color: {{ $isInMonthlyPhase ? 'var(--success)' : 'var(--text-secondary)' }};">
                            @if($isInMonthlyPhase)
                                <i class="fas fa-circle text-xs mr-1"></i> Active
                            @elseif($advanceInfo['payment_frequency'] === 'advance_only')
                                <i class="fas fa-info-circle mr-1"></i> Full advance lease
                            @else
                                <i class="fas fa-clock mr-1"></i> Pending
                            @endif
                        </div>
                    </div>

                    {{-- Total advance --}}
                    <div class="p-4 rounded-lg text-center" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div class="text-xs mb-1" style="color: var(--text-secondary);">
                            <i class="fas fa-money-bill-wave mr-1"></i> Total Advance Paid
                        </div>
                        <div class="text-lg font-bold mt-1" style="color: var(--primary);">
                            {{ $currencySymbol }} {{ number_format($advanceInfo['advance_rent_amount'] ?? 0, 2) }}
                        </div>
                        <div class="text-xs mt-2" style="color: var(--text-secondary);">
                            {{ $advanceInfo['advance_rent_months'] }} month(s) upfront
                        </div>
                    </div>
                </div>

                {{-- Next payment / phase detail row --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                    <div class="p-3 rounded" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div style="color: var(--text-secondary);">Payment Frequency</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">
                            @if($advanceInfo['payment_frequency'] === 'advance_only')
                                <i class="fas fa-money-bill-wave mr-1"></i> Full Advance
                            @else
                                <i class="fas fa-sync-alt mr-1"></i> Advance + Monthly
                            @endif
                        </div>
                    </div>
                    <div class="p-3 rounded" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div style="color: var(--text-secondary);">Monthly Rent</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">
                            {{ $currencySymbol }} {{ number_format($currentLease->monthly_rent, 2) }}
                        </div>
                    </div>
                    <div class="p-3 rounded" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div style="color: var(--text-secondary);">Due Day</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">
                            {{ $currentLease->payment_due_day }}{{ $currentLease->payment_due_day == 1 ? 'st' : ($currentLease->payment_due_day == 2 ? 'nd' : ($currentLease->payment_due_day == 3 ? 'rd' : 'th')) }} of month
                        </div>
                    </div>
                    <div class="p-3 rounded" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div style="color: var(--text-secondary);">Next Payment Due</div>
                        <div class="font-medium mt-1" style="color: var(--text-primary);">
                            @if($currentLease->next_payment_due_date)
                                {{ $currentLease->next_payment_due_date->format('M j, Y') }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                </div>

                @if(!$isCompliant)
                <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.08); border: 1px solid var(--warning);">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle mt-0.5 mr-2" style="color: var(--warning);"></i>
                        <div class="text-xs" style="color: var(--text-primary);">
                            <p class="font-semibold mb-1">Advance rent exceeds the legal maximum</p>
                            <p style="color: var(--text-secondary);">
                                This lease carries an advance rent of {{ $advanceInfo['advance_rent_months'] }} months,
                                which exceeds the {{ $legalMaxNew }}-month cap under the {{ $governingLaw }}.
                                @if($currentLease->advance_rent_acknowledged_at)
                                    Tenant's voluntary acknowledgement was recorded on
                                    {{ $currentLease->advance_rent_acknowledged_at->format('M j, Y') }}.
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Current Lease Card -->
        <div class="card">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i> Current Lease
                    </h3>
                    @php
                        $statusConfig = [
                            'active'             => ['color' => 'success',   'icon' => 'check-circle'],
                            'draft'              => ['color' => 'warning',   'icon' => 'edit'],
                            'pending_signature'  => ['color' => 'info',      'icon' => 'signature'],
                            'pending_landlord'   => ['color' => 'info',      'icon' => 'user-tie'],
                            'pending_tenant'     => ['color' => 'info',      'icon' => 'user'],
                            'expired'            => ['color' => 'secondary', 'icon' => 'clock'],
                            'terminated'         => ['color' => 'danger',    'icon' => 'times-circle'],
                            'completed'          => ['color' => 'secondary', 'icon' => 'flag-checkered'],
                            'renewed'            => ['color' => 'primary',   'icon' => 'sync-alt'],
                        ];
                        $config = $statusConfig[$currentLease->status] ?? $statusConfig['draft'];
                    @endphp
                    <span class="px-3 py-1 text-xs font-semibold rounded-full badge-{{ $config['color'] }}">
                        <i class="fas fa-{{ $config['icon'] }} mr-1"></i>
                        {{ ucfirst(str_replace('_', ' ', $currentLease->status)) }}
                    </span>
                </div>

                <!-- Lease Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <!-- Lease Period -->
                    <div class="card stat-card" style="border-left: 4px solid var(--primary);">
                        <div class="p-4">
                            <div class="flex items-center mb-2">
                                <div class="mr-3">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                                        <i class="fas fa-calendar-alt" style="color: var(--primary);"></i>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Lease Period</p>
                                    <p class="text-lg font-bold" style="color: var(--text-primary);">
                                        {{ $currentLease->start_date->format('M d, Y') }}
                                        -
                                        {{ $currentLease->end_date ? $currentLease->end_date->format('M d, Y') : 'MTM' }}
                                    </p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-clock mr-1"></i>
                                        {{ $currentLease->duration_months ?? '—' }} months
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rent & Deposit -->
                    <div class="card stat-card" style="border-left: 4px solid var(--success);">
                        <div class="p-4">
                            <div class="flex items-center mb-2">
                                <div class="mr-3">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                         style="background-color: rgba(var(--success-rgb), 0.1);">
                                        <i class="fas fa-money-bill-wave" style="color: var(--success);"></i>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Monthly Rent</p>
                                    <p class="text-lg font-bold" style="color: var(--success);">
                                        {{ $currencySymbol }} {{ number_format($currentLease->monthly_rent, 2) }}
                                    </p>
                                    @if($currentLease->security_deposit)
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-shield-alt mr-1"></i>
                                            Deposit: {{ $currencySymbol }} {{ number_format($currentLease->security_deposit, 2) }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Time Remaining / Next Payment -->
                    <div class="card stat-card" style="border-left: 4px solid var(--info);">
                        <div class="p-4">
                            <div class="flex items-center mb-2">
                                <div class="mr-3">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                         style="background-color: rgba(var(--info-rgb), 0.1);">
                                        <i class="fas {{ $isInAdvancePhase ? 'fa-calendar-check' : 'fa-hourglass-half' }}" style="color: var(--info);"></i>
                                    </div>
                                </div>
                                <div>
                                    @if($isInAdvancePhase)
                                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Advance Phase</p>
                                        <p class="text-lg font-bold" style="color: var(--info);">
                                            {{ $advanceMonthsLeft }} mo left
                                        </p>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-calendar mr-1"></i> Monthly starts {{ \Carbon\Carbon::parse($advanceInfo['first_monthly_payment_date'] ?? now())->format('M j, Y') }}
                                        </p>
                                    @else
                                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Time Remaining</p>
                                        <p class="text-lg font-bold" style="color: var(--info);">
                                            {{ $daysRemaining }} days
                                        </p>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-calendar mr-1"></i> Ends in {{ $monthsRemaining }} months
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lease Details -->
                <div class="mb-6">
                    <h4 class="text-sm font-medium mb-4 flex items-center" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i> Lease Details
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-card rounded-lg p-3 border" style="border-color: var(--border-color);">
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-user mr-1"></i> Tenant
                            </p>
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $currentLease->tenant->name }}
                            </p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ $currentLease->tenant->email }}
                            </p>
                        </div>

                        <div class="bg-card rounded-lg p-3 border" style="border-color: var(--border-color);">
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-user-tie mr-1"></i> Landlord
                            </p>
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $currentLease->landlord->name }}
                            </p>
                            @if($isTenant)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ $currentLease->landlord->email }}
                                </p>
                            @endif
                        </div>

                        <div class="bg-card rounded-lg p-3 border" style="border-color: var(--border-color);">
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-user-plus mr-1"></i> Created By
                            </p>
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $currentLease->creator->name ?? 'System' }}
                            </p>
                        </div>

                        <div class="bg-card rounded-lg p-3 border" style="border-color: var(--border-color);">
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-calendar-plus mr-1"></i> Created Date
                            </p>
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $currentLease->created_at->format('M d, Y H:i') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Signatures Status -->
                @if($currentLease->landlord_signed_at || $currentLease->tenant_signed_at)
                <div class="mb-6">
                    <h4 class="text-sm font-medium mb-3 flex items-center" style="color: var(--text-secondary);">
                        <i class="fas fa-signature mr-2"></i> Signatures
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($currentLease->landlord_signed_at)
                        <div class="bg-card rounded-lg p-3 border" style="border-color: rgba(var(--success-rgb), 0.3);">
                            <p class="text-xs font-medium mb-1" style="color: var(--success);">
                                <i class="fas fa-check-circle mr-1"></i> Landlord Signed
                            </p>
                            <p class="text-sm" style="color: var(--text-primary);">
                                {{ $currentLease->landlord_signed_at->format('M d, Y H:i') }}
                            </p>
                        </div>
                        @else
                        <div class="bg-card rounded-lg p-3 border" style="border-color: rgba(var(--warning-rgb), 0.3);">
                            <p class="text-xs font-medium mb-1" style="color: var(--warning);">
                                <i class="fas fa-clock mr-1"></i> Awaiting Landlord Signature
                            </p>
                            @if($canLandlordSignLease)
                            <a href="{{ $leaseDetailsRoute }}?sign=true"
                               class="mt-2 inline-flex items-center text-xs px-3 py-1 rounded"
                               style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                <i class="fas fa-signature mr-1"></i> Sign Now
                            </a>
                            @endif
                        </div>
                        @endif

                        @if($currentLease->tenant_signed_at)
                        <div class="bg-card rounded-lg p-3 border" style="border-color: rgba(var(--success-rgb), 0.3);">
                            <p class="text-xs font-medium mb-1" style="color: var(--success);">
                                <i class="fas fa-check-circle mr-1"></i> Tenant Signed
                            </p>
                            <p class="text-sm" style="color: var(--text-primary);">
                                {{ $currentLease->tenant_signed_at->format('M d, Y H:i') }}
                            </p>
                        </div>
                        @else
                        <div class="bg-card rounded-lg p-3 border" style="border-color: rgba(var(--warning-rgb), 0.3);">
                            <p class="text-xs font-medium mb-1" style="color: var(--warning);">
                                <i class="fas fa-clock mr-1"></i> Awaiting Tenant Signature
                            </p>
                            @if($canTenantSignLease)
                            <a href="{{ $leaseDetailsRoute }}?sign=true"
                               class="mt-2 inline-flex items-center text-xs px-3 py-1 rounded"
                               style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                <i class="fas fa-signature mr-1"></i> Sign Now
                            </a>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                <!-- Lease Actions -->
                <div class="flex flex-wrap justify-end gap-3 pt-6 border-t" style="border-color: var(--border-color);">
                    @if($hasLandlordRole)
                        @if($canRenewLease)
                        <a href="{{ $renewRoute }}"
                           class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-sync-alt mr-2"></i> Renew Lease
                        </a>
                        @endif

                        @if($canTerminateLease)
                        <button onclick="showTerminateModal('{{ $currentLease->id }}')"
                                class="btn-danger px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-times-circle mr-2"></i> Terminate Lease
                        </button>
                        @endif

                        <a href="{{ $leaseDetailsRoute }}"
                           class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                            <i class="fas fa-eye mr-2"></i> View Details
                        </a>

                        <a href="{{ $pdfDownloadRoute }}"
                           target="_blank"
                           class="btn-modern bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-file-pdf mr-2"></i> Download PDF
                        </a>

                        @if($canLandlordSignLease)
                        <a href="{{ $leaseDetailsRoute }}?sign=true"
                           class="btn-success px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-signature mr-2"></i> Sign Lease
                        </a>
                        @endif

                    @elseif($isTenant)
                        @if($canTenantSignLease)
                        <a href="{{ $leaseDetailsRoute }}?sign=true"
                           class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-signature mr-2"></i> Sign Lease
                        </a>
                        @endif

                        <a href="{{ $leaseDetailsRoute }}"
                           class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                            <i class="fas fa-eye mr-2"></i> View Full Lease
                        </a>

                        <a href="{{ $tenantDownloadRoute }}"
                           target="_blank"
                           class="btn-modern bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-download mr-2"></i> Download PDF
                        </a>

                    @elseif($canAdminAccess)
                        @if($canTerminateLease)
                        <button onclick="showTerminateModal('{{ $currentLease->id }}')"
                                class="btn-danger px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-times-circle mr-2"></i> Terminate Lease
                        </button>
                        @endif

                        <a href="{{ $leaseDetailsRoute }}"
                           class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                            <i class="fas fa-eye mr-2"></i> View Details
                        </a>

                        <a href="{{ $pdfDownloadRoute }}"
                           target="_blank"
                           class="btn-modern bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-file-pdf mr-2"></i> Download PDF
                        </a>
                    @endif
                </div>
            </div>
        </div>

    @else
        <!-- No Active Lease -->
        <div class="card border-l-4" style="border-left-color: var(--warning); background-color: rgba(var(--warning-rgb), 0.05);">
            <div class="p-6">
                <div class="flex items-start">
                    <div class="flex-shrink-0 mr-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--warning-rgb), 0.1);">
                            <i class="fas fa-exclamation-triangle text-lg" style="color: var(--warning);"></i>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-semibold mb-2 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-file-contract mr-2"></i> No Active Lease
                        </h4>
                        <div class="text-sm mb-4" style="color: var(--text-secondary);">
                            <p>
                                @if($unit->tenant_status === 'approved')
                                    @if($hasLandlordRole)
                                        This unit has an approved tenant but no active lease agreement.
                                        You should create a lease agreement to formalize the tenancy.
                                    @elseif($isTenant)
                                        Your unit assignment has been approved, but a lease agreement hasn't been created yet.
                                        Please contact your landlord or wait for them to create the lease.
                                    @else
                                        This unit has an approved tenant but no active lease agreement.
                                    @endif
                                @else
                                    @if($hasLandlordRole)
                                        A tenant must be approved before creating a lease agreement.
                                    @elseif($isTenant)
                                        Your unit assignment is pending approval. Once approved, your landlord will create a lease agreement.
                                    @else
                                        This unit does not have an approved tenant or active lease.
                                    @endif
                                @endif
                            </p>
                        </div>
                        @if($canCreateLease)
                        <div class="flex items-center gap-3 flex-wrap">
                            <a href="{{ $createLeaseFormRoute }}"
                               class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                                <i class="fas fa-file-contract mr-2"></i> Create New Lease
                            </a>
                            <a href="{{ $showUnitRoute }}"
                               class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-eye mr-2"></i> View Unit Details
                            </a>
                        </div>
                        @elseif($canAdminAccess)
                        <div class="flex items-center gap-3 flex-wrap">
                            <a href="{{ $showUnitRoute }}"
                               class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-eye mr-2"></i> View Unit Details
                            </a>
                            @if($unit->property->landlord)
                            <a href="mailto:{{ $unit->property->landlord->email }}"
                               class="btn-info px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                                <i class="fas fa-envelope mr-2"></i> Contact Landlord
                            </a>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Lease History -->
    @if($unit->rentalAgreements->isNotEmpty())
    <div class="card">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2" style="color: var(--primary);"></i> Lease History
                </h3>
                <span class="px-3 py-1 text-xs font-medium rounded-full"
                      style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    {{ $unit->rentalAgreements->count() }} agreement(s)
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                <i class="fas fa-calendar mr-1"></i> Lease Period
                            </th>
                            @if($hasLandlordRole || $canAdminAccess)
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                <i class="fas fa-user-tie mr-1"></i> Tenant
                            </th>
                            @endif
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                <i class="fas fa-money-bill-wave mr-1"></i> Monthly Rent
                            </th>
                            {{-- ✅ GHANA: Advance rent column --}}
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                <i class="fas fa-hand-holding-usd mr-1"></i> Advance
                            </th>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                <i class="fas fa-tag mr-1"></i> Status
                            </th>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                <i class="fas fa-cog mr-1"></i> Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($unit->rentalAgreements as $lease)
                            <tr>
                                <td class="p-3">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $lease->start_date->format('M d, Y') }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        to {{ $lease->end_date ? $lease->end_date->format('M d, Y') : 'Month-to-month' }}
                                        @if($lease->duration_months)
                                            <span class="ml-2">({{ $lease->duration_months }} months)</span>
                                        @endif
                                    </div>
                                </td>
                                @if($hasLandlordRole || $canAdminAccess)
                                <td class="p-3">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $lease->tenant->name ?? '—' }}
                                    </div>
                                    @if($canAdminAccess && $lease->tenant)
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $lease->tenant->email }}
                                    </div>
                                    @endif
                                </td>
                                @endif
                                <td class="p-3">
                                    <div class="font-bold" style="color: var(--text-primary);">
                                        {{ $currencySymbol }} {{ number_format($lease->monthly_rent, 2) }}
                                    </div>
                                    @if($lease->security_deposit)
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        Deposit: {{ $currencySymbol }} {{ number_format($lease->security_deposit, 2) }}
                                    </div>
                                    @endif
                                </td>
                                {{-- ✅ GHANA: Advance column --}}
                                <td class="p-3">
                                    @if($lease->advance_rent_months)
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            {{ $lease->advance_rent_months }} mo
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            {{ $currencySymbol }} {{ number_format($lease->advance_rent_amount ?? 0, 2) }}
                                        </div>
                                        @if($lease->advance_rent_compliance_status === 'exceeds_legal_limit')
                                            <span class="mt-1 inline-block px-2 py-0.5 text-xs rounded-full badge-warning">
                                                <i class="fas fa-exclamation-triangle mr-1"></i> Exceeds cap
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-xs" style="color: var(--text-secondary);">—</span>
                                    @endif
                                </td>
                                <td class="p-3">
                                    @php
                                        $statusConfig = [
                                            'active'             => ['color' => 'success',   'icon' => 'check-circle'],
                                            'expired'            => ['color' => 'secondary', 'icon' => 'clock'],
                                            'terminated'         => ['color' => 'danger',    'icon' => 'times-circle'],
                                            'draft'              => ['color' => 'warning',   'icon' => 'edit'],
                                            'pending_signature'  => ['color' => 'info',      'icon' => 'signature'],
                                            'pending_landlord'   => ['color' => 'info',      'icon' => 'user-tie'],
                                            'pending_tenant'     => ['color' => 'info',      'icon' => 'user'],
                                            'completed'          => ['color' => 'secondary', 'icon' => 'flag-checkered'],
                                            'renewed'            => ['color' => 'primary',   'icon' => 'sync-alt'],
                                        ];
                                        $config = $statusConfig[$lease->status] ?? $statusConfig['draft'];
                                    @endphp
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium badge-{{ $config['color'] }}">
                                        <i class="fas fa-{{ $config['icon'] }} mr-1"></i>
                                        {{ ucfirst(str_replace('_', ' ', $lease->status)) }}
                                    </span>
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center gap-2 action-buttons">
                                        <a href="{{ route('property-units.lease-details', [$unit->id, $lease->id]) }}"
                                           class="action-btn view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        @if($hasLandlordRole || $canAdminAccess)
                                        <a href="{{ route('property-units.lease-download-pdf', [$unit->id, $lease->id]) }}"
                                           target="_blank"
                                           class="action-btn edit" title="Download PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        @endif

                                        @if($isTenant)
                                        <a href="{{ route('tenant.property-units.lease-download-pdf', [$unit->id, $lease->id]) }}"
                                           target="_blank"
                                           class="action-btn edit" title="Download PDF">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @elseif($currentLease)
        <!-- No History Message -->
        <div class="card">
            <div class="p-6 text-center">
                <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-history text-2xl" style="color: var(--primary);"></i>
                </div>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                    No Lease History
                </h4>
                <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                    @if($hasLandlordRole)
                        This is the first lease agreement for this unit. All future leases will appear here.
                    @else
                        This is your first lease agreement for this unit.
                    @endif
                </p>
            </div>
        </div>
    @endif
</div>

<!-- Terminate Lease Modal -->
@if(($hasLandlordRole || $canAdminAccess) && $currentLease)
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
            <form id="terminateForm" method="POST" action="{{ route('property-units.terminate-lease', [$unit->id, $currentLease->id]) }}">
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
                                @if($hasAdvanceRent)
                                <li class="flex items-start">
                                    <i class="fas fa-hand-holding-usd mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>Calculate advance-rent refund for unused period</span>
                                </li>
                                @endif
                                <li class="flex items-start">
                                    <i class="fas fa-home mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>Mark the unit as "under maintenance"</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-balance-scale mr-2 mt-0.5" style="color: var(--danger);"></i>
                                    <span>Comply with termination rules under the {{ $governingLaw }}</span>
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
                               max="{{ $currentLease->end_date ? $currentLease->end_date->format('Y-m-d') : '' }}">
                        @if($currentLease->end_date)
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Lease ends on {{ $currentLease->end_date->format('M d, Y') }}
                        </p>
                        @endif
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

                    <div class="mb-4">
                        <div class="flex items-center mb-2">
                            <input type="checkbox"
                                   id="refund_deposit"
                                   name="refund_deposit"
                                   value="1"
                                   class="index-custom-checkbox">
                            <label for="refund_deposit" class="ml-2 text-sm" style="color: var(--text-primary);">
                                Refund Security Deposit ({{ $currencySymbol }} {{ number_format($currentLease->security_deposit, 2) }})
                            </label>
                        </div>
                        <div id="depositRefundAmount" class="hidden mt-2">
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Refund Amount ({{ $currencySymbol }})
                            </label>
                            <input type="number"
                                   name="deposit_refund_amount"
                                   class="index-custom-input w-full"
                                   min="0"
                                   max="{{ $currentLease->security_deposit }}"
                                   step="0.01"
                                   placeholder="{{ number_format($currentLease->security_deposit, 2) }}">
                        </div>
                    </div>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    initModals();
    autoHideMessages();
});

function initModals() {
    const refundCheckbox = document.getElementById('refund_deposit');
    const depositRefundAmount = document.getElementById('depositRefundAmount');

    if (refundCheckbox) {
        refundCheckbox.addEventListener('change', function() {
            if (this.checked && depositRefundAmount) {
                depositRefundAmount.classList.remove('hidden');
                depositRefundAmount.querySelector('input').focus();
            } else if (depositRefundAmount) {
                depositRefundAmount.classList.add('hidden');
                depositRefundAmount.querySelector('input').value = '';
            }
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') hideTerminateModal();
    });
}

function autoHideMessages() {
    setTimeout(() => {
        document.querySelectorAll('.bg-green-100, .bg-red-100')
            .forEach(msg => msg.style.display = 'none');
    }, 5000);
}

function showTerminateModal(leaseId) {
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
</script>

<style>
/* ---------- Form controls ---------- */
.index-custom-input,
.index-custom-textarea,
.index-custom-checkbox,
.index-custom-dropdown {
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

/* ---------- Badges ---------- */
.badge-success   { background-color: rgba(var(--success-rgb), 0.1) !important;   color: var(--success) !important;   border: 1px solid rgba(var(--success-rgb), 0.3) !important; }
.badge-warning   { background-color: rgba(var(--warning-rgb), 0.1) !important;   color: var(--warning) !important;   border: 1px solid rgba(var(--warning-rgb), 0.3) !important; }
.badge-danger    { background-color: rgba(var(--danger-rgb), 0.1) !important;    color: var(--danger) !important;    border: 1px solid rgba(var(--danger-rgb), 0.3) !important; }
.badge-info      { background-color: rgba(var(--info-rgb), 0.1) !important;      color: var(--info) !important;      border: 1px solid rgba(var(--info-rgb), 0.3) !important; }
.badge-primary   { background-color: rgba(var(--primary-rgb), 0.1) !important;   color: var(--primary) !important;   border: 1px solid rgba(var(--primary-rgb), 0.3) !important; }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; }

/* ---------- Buttons ---------- */
.btn-primary   { background-color: var(--primary) !important;   color: white !important; border: 1px solid var(--primary) !important;   transition: all 0.2s ease; }
.btn-primary:hover   { background-color: var(--secondary) !important; border-color: var(--secondary) !important; transform: translateY(-1px); }

.btn-secondary { background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; transition: all 0.2s ease; }
.btn-secondary:hover { background-color: rgba(var(--secondary-rgb), 0.2) !important; transform: translateY(-1px); }

.btn-danger    { background-color: var(--danger) !important;    color: white !important; border: 1px solid var(--danger) !important;    transition: all 0.2s ease; }
.btn-danger:hover    { background-color: #dc3545 !important; transform: translateY(-1px); }

.btn-success   { background-color: var(--success) !important;   color: white !important; border: 1px solid var(--success) !important;   transition: all 0.2s ease; }
.btn-success:hover   { background-color: #28a745 !important; transform: translateY(-1px); }

.btn-info      { background-color: var(--info) !important;      color: white !important; border: 1px solid var(--info) !important;      transition: all 0.2s ease; }
.btn-info:hover      { background-color: #17a2b8 !important; transform: translateY(-1px); }

.btn-modern    { background: linear-gradient(to right, var(--primary), var(--secondary)) !important; color: white !important; border: none !important; transition: all 0.2s ease; }
.btn-modern:hover    { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3); }

/* ---------- Table action buttons ---------- */
.action-buttons { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.action-btn {
    padding: 0.375rem 0.75rem; border-radius: 6px; font-size: 0.75rem; font-weight: 500;
    display: inline-flex; align-items: center; justify-content: center; gap: 0.25rem;
    transition: all 0.2s ease; border: 1px solid transparent; text-decoration: none; cursor: pointer;
}
.action-btn.view { background-color: rgba(var(--info-rgb), 0.1);    color: var(--info);    border-color: rgba(var(--info-rgb), 0.3); }
.action-btn.view:hover { background-color: rgba(var(--info-rgb), 0.2); transform: translateY(-1px); }
.action-btn.edit { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border-color: rgba(var(--warning-rgb), 0.3); }
.action-btn.edit:hover { background-color: rgba(var(--warning-rgb), 0.2); transform: translateY(-1px); }

/* ---------- Modal ---------- */
.modal-container { background: var(--card-bg); border: 1px solid var(--border-color); max-height: 90vh; overflow-y: auto; border-radius: 16px; }
.modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
.modal-body { padding: 1.5rem; }
.modal-footer { padding: 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; }
.modal-close-btn { padding: 0.5rem; border-radius: 0.375rem; transition: background-color 0.2s; cursor: pointer; background: none; border: none; }
.modal-close-btn:hover { background-color: rgba(0, 0, 0, 0.05); }

/* ---------- Stat cards ---------- */
.stat-card { border-radius: 12px; border: 1px solid var(--border-color); background-color: var(--card-bg); transition: all 0.3s ease; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }

/* ---------- Table ---------- */
table { border-collapse: separate; border-spacing: 0; width: 100%; }
table th { font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.75rem; padding: 0.75rem; border-bottom: 2px solid var(--border-color); background-color: var(--bg-secondary) !important; }
table td { padding: 0.75rem; border-bottom: 1px solid var(--border-color); vertical-align: top; background-color: var(--card-bg) !important; }
table tr:last-child td { border-bottom: none; }
table tr { background-color: var(--card-bg) !important; }
table tr:hover td, table tr:hover { background-color: var(--card-bg) !important; }

/* ---------- Responsive ---------- */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-3 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }
    .action-buttons { flex-direction: column; }
    .action-btn { width: 100%; justify-content: flex-start; }
    .modal-container { width: 95%; max-height: 80vh; margin: 0.5rem; }
}
</style>
@endsection