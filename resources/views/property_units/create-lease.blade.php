{{-- property_units/create-lease.blade.php --}}
@php
    // ========== AUTH & PERMISSIONS ==========
    $user = auth()->user();
    $isLandlord   = $user->isLandlord()   || $user->hasRole('landlord');
    $isAdmin      = $user->isAdmin()      || $user->hasRole('admin');
    $isSuperAdmin = $user->isSuperAdmin() || $user->hasRole('super-admin');
    $isTenant     = $user->isTenant()     || $user->hasRole('tenant');

    // ✅ GHANA: Only landlords can create leases
    if (!$isLandlord || $unit->property->landlord_id !== $user->id) {
        abort(403, 'Only the property owner can create leases.');
    }

    if ($unit->tenant_status !== \App\Models\PropertyUnit::TENANT_STATUS_APPROVED) {
        abort(403, 'Unit must have an approved tenant to create a lease.');
    }

    if ($unit->currentLease && $unit->currentLease->status === 'active') {
        abort(403, 'Unit already has an active lease. You can renew or terminate the existing lease.');
    }

    // ========== LAYOUT ==========
    $layout = $isLandlord ? 'layouts.landlord'
            : (($isAdmin || $isSuperAdmin) ? 'layouts.app'
            : ($isTenant ? 'layouts.tenant' : 'layouts.app'));

    // ========== PAGE TITLE ==========
    $pageTitle = 'Create Lease Agreement';
    if ($unit) {
        $pageTitle .= ' - ' . $unit->property->property_name . ' - Unit ' . $unit->unit_number;
    }

    // ========== SESSION MESSAGES ==========
    $successMessage    = session('success');
    $errorMessage      = session('error');
    $validationErrors  = session('errors');

    // ========== DATES ==========
    $today          = now()->format('Y-m-d');
    $oneYearFromNow = now()->addYear()->format('Y-m-d');

    // ========== ✅ GHANA: LEGAL & COMPLIANCE CONSTANTS ==========
    $legalMaxAdvanceNew     = (int) config('leases.ghana.legal_max_advance_months.new_tenancy', 6);
    $legalMaxAdvanceRenewal = (int) config('leases.ghana.legal_max_advance_months.renewal', 3);
    $legalMaxAdvanceShort   = (int) config('leases.ghana.legal_max_advance_months.short_tenancy', 1);
    $shortTenancyThreshold  = (int) config('leases.ghana.short_tenancy_threshold_months', 6);
    $enforceMode            = config('leases.ghana.enforce_compliance', 'warn');
    $requireAcknowledgement = (bool) config('leases.ghana.require_acknowledgement', true);
    $governingLaw           = config('leases.ghana.governing_law', 'Rent Act, 1963 (Act 220)');
    $currencySymbol         = config('leases.ghana.currency.symbol', 'GH₵');

    // ========== ✅ GHANA: DEFAULTS FROM CONFIG ==========
    $defaultDurationMonths  = (int) config('leases.defaults.duration_months', 12);
    $defaultPaymentDueDay   = (int) config('leases.defaults.payment_due_day', 5);
    $defaultGraceDays       = (int) config('leases.defaults.grace_period_days', 0);
    $defaultNoticeDays      = (int) config('leases.defaults.notice_period_days', 30);
    $defaultAdvanceMonths   = (int) config('leases.defaults.advance_rent_months', 6);
    $defaultPaymentFreq     = config('leases.defaults.payment_frequency', 'monthly');
    $depositMultiplier      = (float) config('leases.defaults.deposit_multiplier', 2.0);
    $defaultLateFeePct      = (float) config('leases.defaults.late_fee.late_fee_percentage', 0);
    $defaultLateFeeFixed    = (float) config('leases.defaults.late_fee.late_fee_fixed', 0);

    // ========== ✅ GHANA: SUGGESTED AMOUNTS ==========
    $unitRent = $unit->current_rent_amount ?? $unit->monthly_rent;
    $suggestedSecurityDeposit = $unitRent * $depositMultiplier;
    $suggestedAdvanceAmount   = $unitRent * $defaultAdvanceMonths;

    // ========== ✅ GHANA: ADVANCE RENT OPTIONS ==========
    $advanceRentOptions = [
        1  => '1 Month — ' . $currencySymbol . ' ' . number_format($unitRent * 1, 2)  . ' (Short tenancy / monthly)',
        3  => '3 Months — ' . $currencySymbol . ' ' . number_format($unitRent * 3, 2)  . ' (Renewal max)',
        6  => '6 Months — ' . $currencySymbol . ' ' . number_format($unitRent * 6, 2)  . ' (Legal max for new tenancy)',
        12 => '12 Months — ' . $currencySymbol . ' ' . number_format($unitRent * 12, 2) . ' (1 Year — market practice)',
        24 => '24 Months — ' . $currencySymbol . ' ' . number_format($unitRent * 24, 2) . ' (2 Years — market practice)',
        36 => '36 Months — ' . $currencySymbol . ' ' . number_format($unitRent * 36, 2) . ' (3 Years — market practice)',
    ];

    // ========== ✅ GHANA: DROPDOWN OPTIONS FROM CONTROLLER ==========
    $leaseTemplates = $leaseTemplates ?? [
        'standard_12_month' => 'Standard 12-Month Lease',
        'month_to_month'    => 'Month-to-Month Agreement',
        'commercial'        => 'Commercial Lease',
        'student'           => 'Student Housing Agreement',
        'furnished'         => 'Furnished Unit Lease',
    ];

    $paymentTerms = $paymentTerms ?? [
        '1' => '1st of each month',
        '5' => '5th of each month',
        '10' => '10th of each month',
        '15' => '15th of each month',
        '20' => '20th of each month',
        '25' => '25th of each month',
    ];

    // ✅ GHANA: Pull the acknowledgement field name for JS
    $ackFieldName = 'advance_rent_acknowledged';
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
                        Create Lease Agreement
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Create a new lease agreement for your tenant</span>
                        @if($unit)
                        <span class="mx-2">•</span>
                        <i class="fas fa-building mr-1"></i>
                        <span>{{ $unit->property->property_name }} - Unit {{ $unit->unit_number }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user mr-1"></i>
                        <span>Tenant: {{ $unit->tenant->name ?? 'Not assigned' }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <a href="{{ route('property-units.show', $unit->id) }}"
                   class="ml-3 px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Unit
                </a>
            </div>
        </div>
    </div>

    {{-- ✅ GHANA: Legal Compliance Notice (top of page) --}}
    <div class="card border p-4" style="border-color: var(--warning); background-color: rgba(var(--warning-rgb), 0.05);">
        <div class="flex items-start">
            <i class="fas fa-balance-scale text-2xl mr-3" style="color: var(--warning);"></i>
            <div class="flex-1">
                <h4 class="font-semibold mb-1" style="color: var(--text-primary);">
                    Ghana Rent Act Compliance — {{ $governingLaw }}
                </h4>
                <p class="text-xs mb-2" style="color: var(--text-secondary);">
                    Advance rent is capped by law, but tenants may voluntarily offer more.
                    Exceeding the cap requires your acknowledgement.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2 text-green-600"></i>
                        <span style="color: var(--text-secondary);">New tenancy ≤6 mo: <strong>{{ $legalMaxAdvanceShort }} month max</strong></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2 text-green-600"></i>
                        <span style="color: var(--text-secondary);">New tenancy &gt;6 mo: <strong>{{ $legalMaxAdvanceNew }} months max</strong></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2 text-green-600"></i>
                        <span style="color: var(--text-secondary);">Renewal: <strong>{{ $legalMaxAdvanceRenewal }} months max</strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if($successMessage)
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
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
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
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

    @if($validationErrors)
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <span class="font-bold">Please fix the following errors:</span>
        </div>
        <ul class="mt-2 ml-6 list-disc">
            @foreach ($validationErrors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Lease Creation Form -->
    <div class="card">
        <div class="p-6">
            <form method="POST" action="{{ route('property-units.create-lease', $unit->id) }}" id="leaseForm">
                @csrf

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Left Column: Basic Information -->
                    <div class="space-y-6">
                        <!-- Unit Information Card -->
                        <div class="card border" style="border-color: var(--border-color);">
                            <div class="p-4">
                                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Unit Information
                                </h3>

                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm" style="color: var(--text-secondary);">Property:</span>
                                        <span class="font-medium" style="color: var(--text-primary);">{{ $unit->property->property_name }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm" style="color: var(--text-secondary);">Unit Number:</span>
                                        <span class="font-medium" style="color: var(--text-primary);">{{ $unit->unit_number }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm" style="color: var(--text-secondary);">Unit Type:</span>
                                        <span class="font-medium" style="color: var(--text-primary);">{{ ucfirst(str_replace('_', ' ', $unit->unit_type)) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm" style="color: var(--text-secondary);">Current Rent:</span>
                                        <span class="font-medium" style="color: var(--text-primary);">{{ $currencySymbol }} {{ number_format($unitRent, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tenant Information Card -->
                        <div class="card border" style="border-color: var(--border-color);">
                            <div class="p-4">
                                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-user mr-2" style="color: var(--primary);"></i> Tenant Information
                                </h3>

                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm" style="color: var(--text-secondary);">Tenant Name:</span>
                                        <span class="font-medium" style="color: var(--text-primary);">{{ $unit->tenant->name ?? 'Not assigned' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm" style="color: var(--text-secondary);">Tenant Email:</span>
                                        <span class="font-medium" style="color: var(--text-primary);">{{ $unit->tenant->email ?? 'N/A' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm" style="color: var(--text-secondary);">Tenant Phone:</span>
                                        <span class="font-medium" style="color: var(--text-primary);">{{ $unit->tenant->phone ?? 'N/A' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm" style="color: var(--text-secondary);">Tenant Status:</span>
                                        <span class="px-2 py-1 text-xs rounded-full badge-success">Approved</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lease Term Card -->
                        <div class="card border" style="border-color: var(--border-color);">
                            <div class="p-4">
                                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i> Lease Term
                                </h3>

                                <div class="space-y-4">
                                    <!-- Lease Type -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Lease Type *
                                        </label>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div class="relative">
                                                <input type="radio"
                                                       id="lease_type_fixed"
                                                       name="lease_type"
                                                       value="fixed"
                                                       class="hidden peer"
                                                       {{ old('lease_type', 'fixed') === 'fixed' ? 'checked' : '' }}
                                                       onchange="toggleLeaseDuration(this.value)">
                                                <label for="lease_type_fixed"
                                                       class="block p-3 border rounded-lg cursor-pointer text-center transition-all peer-checked:border-primary peer-checked:ring-2 peer-checked:ring-primary peer-checked:bg-primary/5">
                                                    <i class="fas fa-calendar-check mb-1 block" style="color: var(--primary);"></i>
                                                    <span class="font-medium">Fixed Term</span>
                                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Specific end date</p>
                                                </label>
                                            </div>
                                            <div class="relative">
                                                <input type="radio"
                                                       id="lease_type_month_to_month"
                                                       name="lease_type"
                                                       value="month_to_month"
                                                       class="hidden peer"
                                                       {{ old('lease_type') === 'month_to_month' ? 'checked' : '' }}
                                                       onchange="toggleLeaseDuration(this.value)">
                                                <label for="lease_type_month_to_month"
                                                       class="block p-3 border rounded-lg cursor-pointer text-center transition-all peer-checked:border-primary peer-checked:ring-2 peer-checked:ring-primary peer-checked:bg-primary/5">
                                                    <i class="fas fa-calendar-day mb-1 block" style="color: var(--primary);"></i>
                                                    <span class="font-medium">Month-to-Month</span>
                                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">No fixed end date</p>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Start Date -->
                                    <div>
                                        <label for="start_date" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Lease Start Date *
                                        </label>
                                        <input type="date"
                                               id="start_date"
                                               name="start_date"
                                               value="{{ old('start_date', $today) }}"
                                               min="{{ $today }}"
                                               class="lease-custom-input w-full"
                                               required
                                               onchange="updateEndDate(); updateAdvancePreview();">
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Date when the lease becomes effective</p>
                                    </div>

                                    <!-- Fixed Term Duration -->
                                    <div id="fixedTermFields">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label for="duration_months" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                    Duration (Months) *
                                                </label>
                                                <select id="duration_months"
                                                        name="duration_months"
                                                        class="lease-custom-dropdown w-full"
                                                        onchange="updateEndDate(); updateAdvancePreview();"
                                                        required>
                                                    <option value="6"  {{ old('duration_months') == '6'  ? 'selected' : '' }}>6 Months</option>
                                                    <option value="12" {{ old('duration_months', $defaultDurationMonths) == '12' ? 'selected' : '' }}>12 Months (1 Year)</option>
                                                    <option value="18" {{ old('duration_months') == '18' ? 'selected' : '' }}>18 Months</option>
                                                    <option value="24" {{ old('duration_months') == '24' ? 'selected' : '' }}>24 Months (2 Years)</option>
                                                    <option value="36" {{ old('duration_months') == '36' ? 'selected' : '' }}>36 Months (3 Years)</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                    Calculated End Date *
                                                </label>
                                                <div class="lease-custom-input w-full flex items-center justify-between" style="padding: 0.5rem 0.75rem;">
                                                    <span id="calculatedEndDate" style="color: var(--text-primary);">
                                                        {{ now()->addYear()->format('F j, Y') }}
                                                    </span>
                                                    <input type="hidden" id="end_date" name="end_date" value="{{ $oneYearFromNow }}">
                                                </div>
                                                <p class="text-xs mt-1" style="color: var(--text-secondary);">End date for fixed term lease</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ============================================================
                             ✅ GHANA: ADVANCE RENT & PAYMENT STRUCTURE CARD
                             ============================================================ --}}
                        <div class="card border" style="border-color: var(--primary); background-color: rgba(var(--primary-rgb), 0.03);">
                            <div class="p-4">
                                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-hand-holding-usd mr-2" style="color: var(--primary);"></i>
                                    Advance Rent & Payment Structure
                                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full badge-primary">Ghana</span>
                                </h3>

                                <div class="space-y-4">
                                    {{-- Payment Frequency --}}
                                    <div>
                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Payment Structure *
                                        </label>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div class="relative">
                                                <input type="radio"
                                                       id="payment_frequency_monthly"
                                                       name="payment_frequency"
                                                       value="monthly"
                                                       class="hidden peer"
                                                       {{ old('payment_frequency', $defaultPaymentFreq) === 'monthly' ? 'checked' : '' }}
                                                       onchange="togglePaymentFrequency(this.value)">
                                                <label for="payment_frequency_monthly"
                                                       class="block p-3 border rounded-lg cursor-pointer text-center transition-all peer-checked:border-primary peer-checked:ring-2 peer-checked:ring-primary peer-checked:bg-primary/5">
                                                    <i class="fas fa-sync-alt mb-1 block" style="color: var(--primary);"></i>
                                                    <span class="font-medium">Advance + Monthly</span>
                                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                        Pay advance now, then monthly rent afterwards (recommended)
                                                    </p>
                                                </label>
                                            </div>
                                            <div class="relative">
                                                <input type="radio"
                                                       id="payment_frequency_advance_only"
                                                       name="payment_frequency"
                                                       value="advance_only"
                                                       class="hidden peer"
                                                       {{ old('payment_frequency') === 'advance_only' ? 'checked' : '' }}
                                                       onchange="togglePaymentFrequency(this.value)">
                                                <label for="payment_frequency_advance_only"
                                                       class="block p-3 border rounded-lg cursor-pointer text-center transition-all peer-checked:border-primary peer-checked:ring-2 peer-checked:ring-primary peer-checked:bg-primary/5">
                                                    <i class="fas fa-money-bill-wave mb-1 block" style="color: var(--primary);"></i>
                                                    <span class="font-medium">Full Advance</span>
                                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                        Entire lease term paid upfront
                                                    </p>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Advance Rent Months --}}
                                    <div>
                                        <label for="advance_rent_months" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Advance Rent Period *
                                        </label>
                                        <select id="advance_rent_months"
                                                name="advance_rent_months"
                                                class="lease-custom-dropdown w-full"
                                                onchange="updateAdvancePreview(); checkAdvanceCompliance();"
                                                required>
                                            @foreach($advanceRentOptions as $months => $label)
                                                <option value="{{ $months }}"
                                                        data-months="{{ $months }}"
                                                        {{ old('advance_rent_months', $defaultAdvanceMonths) == $months ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            Number of months of rent paid upfront before occupancy
                                        </p>
                                    </div>

                                    {{-- Advance Rent Amount Display --}}
                                    <div class="p-3 rounded-lg" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-sm" style="color: var(--text-secondary);">Total Advance Rent:</span>
                                            <span class="font-bold text-lg" style="color: var(--primary);" id="advanceAmountDisplay">
                                                {{ $currencySymbol }} {{ number_format($suggestedAdvanceAmount, 2) }}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs" style="color: var(--text-secondary);">Covers period:</span>
                                            <span class="text-xs" style="color: var(--text-primary);" id="advancePeriodDisplay">
                                                {{ now()->format('M j, Y') }} → {{ now()->addMonths($defaultAdvanceMonths)->format('M j, Y') }}
                                            </span>
                                        </div>
                                    </div>

                                    {{-- ✅ GHANA: Compliance Warning (shown when exceeding cap) --}}
                                    <div id="complianceWarning" class="p-3 rounded-lg" style="display: none; background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid var(--warning);">
                                        <div class="flex items-start">
                                            <i class="fas fa-exclamation-triangle mt-0.5 mr-2" style="color: var(--warning);"></i>
                                            <div class="flex-1 text-xs" style="color: var(--text-primary);">
                                                <p class="font-semibold mb-1">Exceeds Legal Advance Cap</p>
                                                <p class="mb-2" style="color: var(--text-secondary);">
                                                    The selected advance period exceeds the legal maximum of
                                                    <span id="complianceLegalMax">{{ $legalMaxAdvanceNew }}</span> months
                                                    under the {{ $governingLaw }}. The tenant must voluntarily offer this
                                                    extra advance — it cannot be demanded.
                                                </p>
                                                @if($requireAcknowledgement)
                                                <label class="flex items-start cursor-pointer">
                                                    <input type="checkbox"
                                                           id="advance_rent_acknowledged"
                                                           name="advance_rent_acknowledged"
                                                           value="1"
                                                           class="lease-custom-checkbox mt-0.5"
                                                           onchange="toggleAckHighlight(this.checked)">
                                                    <span class="ml-2" style="color: var(--text-primary);">
                                                        <strong>I confirm the tenant voluntarily offered this advance rent.</strong>
                                                        This will be recorded on the lease for compliance purposes.
                                                    </span>
                                                </label>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- ✅ GHANA: Monthly Phase Info (shown when payment_frequency = monthly) --}}
                                    <div id="monthlyPhaseInfo" class="p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                        <div class="flex items-start">
                                            <i class="fas fa-info-circle mt-0.5 mr-2" style="color: var(--success);"></i>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                <p class="font-medium mb-1" style="color: var(--text-primary);">Monthly Phase:</p>
                                                <p>
                                                    After <strong id="monthlyPhaseAfterMonths">{{ $defaultAdvanceMonths }}</strong> months,
                                                    the tenant will begin paying monthly rent of
                                                    <strong id="monthlyPhaseAmount">{{ $currencySymbol }} {{ number_format($unitRent, 2) }}</strong>
                                                    starting on <strong id="monthlyPhaseStartDate">{{ now()->addMonths($defaultAdvanceMonths)->format('F j, Y') }}</strong>.
                                                    Monthly invoices will be generated automatically.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Financial Terms -->
                    <div class="space-y-6">
                        <!-- Financial Terms Card -->
                        <div class="card border" style="border-color: var(--border-color);">
                            <div class="p-4">
                                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-money-bill-wave mr-2" style="color: var(--primary);"></i> Financial Terms
                                </h3>

                                <div class="space-y-4">
                                    <!-- Monthly Rent -->
                                    <div>
                                        <label for="monthly_rent" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Monthly Rent *
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                <span class="font-medium" style="color: var(--text-secondary);">{{ $currencySymbol }}</span>
                                            </div>
                                            <input type="number"
                                                   id="monthly_rent"
                                                   name="monthly_rent"
                                                   value="{{ old('monthly_rent', $unitRent) }}"
                                                   min="0.01"
                                                   step="0.01"
                                                   class="lease-custom-input w-full pl-16"
                                                   required
                                                   placeholder="0.00"
                                                   oninput="updateFinancialPreview(); updateAdvancePreview();">
                                        </div>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            Current unit rent: {{ $currencySymbol }} {{ number_format($unit->monthly_rent, 2) }}
                                            @if($unit->current_rent_amount)
                                                <br>Current tenant rent: {{ $currencySymbol }} {{ number_format($unit->current_rent_amount, 2) }}
                                            @endif
                                        </p>
                                    </div>

                                    <!-- Security Deposit -->
                                    <div class="border-t pt-4 mt-4" style="border-color: var(--border-color);">
                                        <div class="flex items-start mb-4">
                                            <input type="checkbox"
                                                   id="enable_security_deposit"
                                                   name="enable_security_deposit"
                                                   value="1"
                                                   class="lease-custom-checkbox mt-1"
                                                   {{ old('enable_security_deposit') ? 'checked' : '' }}
                                                   onchange="toggleSecurityDeposit(this.checked)">
                                            <label for="enable_security_deposit" class="ml-2 text-sm" style="color: var(--text-primary);">
                                                <span class="font-medium">Require Security Deposit</span>
                                                <p class="mt-1" style="color: var(--text-secondary);">
                                                    Enable this if you want to collect a security deposit from the tenant
                                                </p>
                                            </label>
                                        </div>

                                        <div id="securityDepositFields" style="display: {{ old('enable_security_deposit') ? 'block' : 'none' }};">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label for="security_deposit" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                        Total Security Deposit Amount *
                                                    </label>
                                                    <div class="relative">
                                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                            <span class="font-medium" style="color: var(--text-secondary);">{{ $currencySymbol }}</span>
                                                        </div>
                                                        <input type="number"
                                                               id="security_deposit"
                                                               name="security_deposit"
                                                               value="{{ old('security_deposit') }}"
                                                               min="0"
                                                               step="0.01"
                                                               class="lease-custom-input w-full pl-16"
                                                               placeholder="0.00"
                                                               oninput="updateDepositCalculation()">
                                                    </div>
                                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                        Suggested: {{ $depositMultiplier }} months rent = {{ $currencySymbol }} {{ number_format($suggestedSecurityDeposit, 2) }}
                                                    </p>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                        Deposit Payment Method *
                                                    </label>
                                                    <div class="grid grid-cols-2 gap-3">
                                                        <div class="relative">
                                                            <input type="radio"
                                                                   id="deposit_upfront"
                                                                   name="deposit_payment_method"
                                                                   value="upfront"
                                                                   class="hidden peer"
                                                                   {{ old('deposit_payment_method', 'upfront') === 'upfront' ? 'checked' : '' }}
                                                                   onchange="toggleDepositInstallment(false)">
                                                            <label for="deposit_upfront"
                                                                   class="block p-3 border rounded-lg cursor-pointer text-center transition-all peer-checked:border-primary peer-checked:ring-2 peer-checked:ring-primary peer-checked:bg-primary/5">
                                                                <i class="fas fa-money-bill-wave mb-1 block" style="color: var(--primary);"></i>
                                                                <span class="font-medium">Pay Upfront</span>
                                                                <p class="text-xs mt-1" style="color: var(--text-secondary);">Full amount before move-in</p>
                                                            </label>
                                                        </div>
                                                        <div class="relative">
                                                            <input type="radio"
                                                                   id="deposit_installment"
                                                                   name="deposit_payment_method"
                                                                   value="installment"
                                                                   class="hidden peer"
                                                                   {{ old('deposit_payment_method') === 'installment' ? 'checked' : '' }}
                                                                   onchange="toggleDepositInstallment(true)">
                                                            <label for="deposit_installment"
                                                                   class="block p-3 border rounded-lg cursor-pointer text-center transition-all peer-checked:border-primary peer-checked:ring-2 peer-checked:ring-primary peer-checked:bg-primary/5">
                                                                <i class="fas fa-calendar-alt mb-1 block" style="color: var(--primary);"></i>
                                                                <span class="font-medium">Monthly Installment</span>
                                                                <p class="text-xs mt-1" style="color: var(--text-secondary);">Spread over several months</p>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div id="installmentOptions" class="mt-4 space-y-4" style="display: {{ old('deposit_payment_method') === 'installment' ? 'block' : 'none' }};">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <label for="deposit_installment_months" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                            Installment Duration (Months) *
                                                        </label>
                                                        <select id="deposit_installment_months"
                                                                name="deposit_installment_months"
                                                                class="lease-custom-dropdown w-full"
                                                                onchange="updateDepositCalculation()">
                                                            <option value="2"  {{ old('deposit_installment_months') == 2  ? 'selected' : '' }}>2 Months</option>
                                                            <option value="3"  {{ old('deposit_installment_months', 3) == 3 ? 'selected' : '' }}>3 Months</option>
                                                            <option value="4"  {{ old('deposit_installment_months') == 4  ? 'selected' : '' }}>4 Months</option>
                                                            <option value="6"  {{ old('deposit_installment_months') == 6  ? 'selected' : '' }}>6 Months</option>
                                                            <option value="12" {{ old('deposit_installment_months') == 12 ? 'selected' : '' }}>12 Months</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                                            Monthly Deposit Installment
                                                        </label>
                                                        <div class="lease-custom-input w-full flex items-center justify-between" style="padding: 0.5rem 0.75rem;">
                                                            <span id="monthlyDepositInstallment" style="color: var(--text-primary);">{{ $currencySymbol }} 0.00</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg">
                                                    <div class="flex items-start">
                                                        <i class="fas fa-info-circle mt-0.5 mr-2" style="color: var(--primary);"></i>
                                                        <div class="text-xs" style="color: var(--text-secondary);">
                                                            <p class="font-medium mb-1">How Deposit Installment Works:</p>
                                                            <ul class="list-disc list-inside space-y-1">
                                                                <li>For the first <span id="installmentPeriodDisplay">3</span> months, tenant pays <strong>Monthly Rent + Deposit Installment</strong></li>
                                                                <li>After <span id="installmentPeriodDisplay2">3</span> months, tenant pays only the monthly rent</li>
                                                                <li>The full deposit amount ({{ $currencySymbol }} <span id="totalDepositDisplay">0</span>) will be held as security</li>
                                                                <li>Deposit is fully refundable at move-out</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Utility Deposit -->
                                    <div>
                                        <label for="utility_deposit" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Utility Deposit (Optional)
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                <span class="font-medium" style="color: var(--text-secondary);">{{ $currencySymbol }}</span>
                                            </div>
                                            <input type="number"
                                                   id="utility_deposit"
                                                   name="utility_deposit"
                                                   value="{{ old('utility_deposit') }}"
                                                   min="0"
                                                   step="0.01"
                                                   class="lease-custom-input w-full pl-16"
                                                   placeholder="0.00"
                                                   oninput="updateFinancialPreview()">
                                        </div>
                                    </div>

                                    <!-- Payment Due Day -->
                                    <div>
                                        <label for="payment_due_day" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Rent Due Day (Day of Month) *
                                        </label>
                                        <select id="payment_due_day"
                                                name="payment_due_day"
                                                class="lease-custom-dropdown w-full"
                                                onchange="updatePaymentPreview(); updateAdvancePreview();">
                                            @for($i = 1; $i <= 28; $i++)
                                                <option value="{{ $i }}" {{ old('payment_due_day', $defaultPaymentDueDay) == $i ? 'selected' : '' }}>
                                                    {{ $i }}{{ $i == 1 ? 'st' : ($i == 2 ? 'nd' : ($i == 3 ? 'rd' : 'th')) }} of each month
                                                </option>
                                            @endfor
                                        </select>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            Day when rent payment is due each month
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Late Payment Terms Card -->
                        <div class="card border" style="border-color: var(--border-color);">
                            <div class="p-4">
                                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-clock mr-2" style="color: var(--warning);"></i> Late Payment Terms
                                </h3>

                                <div class="space-y-4">
                                    <div>
                                        <label for="grace_period_days" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Grace Period (Days) <span class="text-xs font-normal" style="color: var(--text-secondary);">(Optional)</span>
                                        </label>
                                        <div class="relative">
                                            <input type="number"
                                                   id="grace_period_days"
                                                   name="grace_period_days"
                                                   value="{{ old('grace_period_days', $defaultGraceDays) }}"
                                                   min="0"
                                                   max="15"
                                                   class="lease-custom-input w-full pr-12"
                                                   placeholder="e.g., 5"
                                                   style="padding-left: 1rem;">
                                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                                <span class="text-sm" style="color: var(--text-secondary);">days</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="late_fee_percentage" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Late Fee Percentage <span class="text-xs font-normal" style="color: var(--text-secondary);">(Optional)</span>
                                        </label>
                                        <div class="relative">
                                            <input type="number"
                                                   id="late_fee_percentage"
                                                   name="late_fee_percentage"
                                                   value="{{ old('late_fee_percentage', $defaultLateFeePct ?: '') }}"
                                                   min="0"
                                                   max="50"
                                                   step="0.1"
                                                   class="lease-custom-input w-full pr-12"
                                                   placeholder="e.g., 5"
                                                   style="padding-left: 1rem;">
                                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                                <span class="text-sm" style="color: var(--text-secondary);">%</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="late_fee_fixed" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Late Fee Fixed Amount (Optional)
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                <span class="font-medium" style="color: var(--text-secondary);">{{ $currencySymbol }}</span>
                                            </div>
                                            <input type="number"
                                                   id="late_fee_fixed"
                                                   name="late_fee_fixed"
                                                   value="{{ old('late_fee_fixed', $defaultLateFeeFixed ?: '') }}"
                                                   min="0"
                                                   step="0.01"
                                                   class="lease-custom-input w-full pl-16"
                                                   placeholder="0.00">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Termination Terms Card -->
                        <div class="card border" style="border-color: var(--border-color);">
                            <div class="p-4">
                                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i> Termination Terms
                                </h3>

                                <div class="space-y-4">
                                    <div>
                                        <label for="notice_period_days" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Notice Period (Days) *
                                        </label>
                                        <div class="relative">
                                            <input type="number"
                                                   id="notice_period_days"
                                                   name="notice_period_days"
                                                   value="{{ old('notice_period_days', $defaultNoticeDays) }}"
                                                   min="15"
                                                   max="90"
                                                   class="lease-custom-input w-full pr-12"
                                                   required
                                                   placeholder="30"
                                                   style="padding-left: 1rem;">
                                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                                <span class="text-sm" style="color: var(--text-secondary);">days</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="early_termination_fee" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                            Early Termination Fee (Optional)
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                <span class="font-medium" style="color: var(--text-secondary);">{{ $currencySymbol }}</span>
                                            </div>
                                            <input type="number"
                                                   id="early_termination_fee"
                                                   name="early_termination_fee"
                                                   value="{{ old('early_termination_fee') }}"
                                                   min="0"
                                                   step="0.01"
                                                   class="lease-custom-input w-full pl-16"
                                                   placeholder="0.00"
                                                   oninput="updateFinancialPreview()">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Special Terms Card -->
                <div class="card border mt-6" style="border-color: var(--border-color);">
                    <div class="p-4">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-file-alt mr-2" style="color: var(--primary);"></i> Additional Terms & Conditions
                        </h3>

                        <div class="space-y-4">
                            <div class="flex items-start">
                                <input type="checkbox"
                                       id="include_standard_terms"
                                       name="include_standard_terms"
                                       value="1"
                                       class="lease-custom-checkbox mt-1"
                                       checked>
                                <label for="include_standard_terms" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    <span class="font-medium">Include Standard Lease Terms</span>
                                    <p class="mt-1" style="color: var(--text-secondary);">
                                        Includes the 20 standard clauses (advance rent, monthly rent, utilities, maintenance, governing law — {{ $governingLaw }})
                                    </p>
                                </label>
                            </div>

                            <div>
                                <label for="special_terms" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Special Terms & Conditions (Optional)
                                </label>
                                <textarea id="special_terms"
                                          name="special_terms"
                                          rows="4"
                                          class="lease-custom-textarea w-full"
                                          placeholder="Add any special terms, conditions, or notes for this lease agreement...">{{ old('special_terms') }}</textarea>
                            </div>

                            <div>
                                <label for="renewal_terms" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Renewal Terms (Optional)
                                </label>
                                <textarea id="renewal_terms"
                                          name="renewal_terms"
                                          rows="2"
                                          class="lease-custom-textarea w-full"
                                          placeholder="Describe renewal terms (e.g., automatic renewal with 60 days notice)...">{{ old('renewal_terms') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tenant Invitation Card -->
                <div class="card border mt-6" style="border-color: var(--border-color);">
                    <div class="p-4">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-paper-plane mr-2" style="color: var(--primary);"></i> Send to Tenant
                        </h3>

                        <div class="space-y-4">
                            <div class="flex items-start">
                                <input type="checkbox"
                                       id="send_to_tenant"
                                       name="send_to_tenant"
                                       value="1"
                                       class="lease-custom-checkbox mt-1"
                                       checked
                                       onchange="toggleInvitationChannels(this.checked)">
                                <label for="send_to_tenant" class="ml-2 text-sm" style="color: var(--text-primary);">
                                    <span class="font-medium">Send Lease to Tenant for Signature</span>
                                    <p class="mt-1" style="color: var(--text-secondary);">
                                        Tenant will receive the lease agreement for review and signature
                                    </p>
                                </label>
                            </div>

                            <div id="invitationChannels" class="space-y-3">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Send via *
                                </label>
                                <div class="grid grid-cols-3 gap-3">
                                    <div class="relative">
                                        <input type="checkbox"
                                               id="channel_email"
                                               name="invitation_channels[]"
                                               value="email"
                                               class="hidden peer"
                                               checked>
                                        <label for="channel_email"
                                               class="block p-3 border rounded-lg cursor-pointer text-center transition-all peer-checked:border-primary peer-checked:ring-2 peer-checked:ring-primary peer-checked:bg-primary/5">
                                            <i class="fas fa-envelope mb-1 block" style="color: var(--primary);"></i>
                                            <span class="font-medium">Email</span>
                                        </label>
                                    </div>
                                    <div class="relative">
                                        <input type="checkbox"
                                               id="channel_sms"
                                               name="invitation_channels[]"
                                               value="sms"
                                               class="hidden peer">
                                        <label for="channel_sms"
                                               class="block p-3 border rounded-lg cursor-pointer text-center transition-all peer-checked:border-primary peer-checked:ring-2 peer-checked:ring-primary peer-checked:bg-primary/5">
                                            <i class="fas fa-comment-alt mb-1 block" style="color: var(--primary);"></i>
                                            <span class="font-medium">SMS</span>
                                        </label>
                                    </div>
                                    <div class="relative">
                                        <input type="checkbox"
                                               id="channel_whatsapp"
                                               name="invitation_channels[]"
                                               value="whatsapp"
                                               class="hidden peer">
                                        <label for="channel_whatsapp"
                                               class="block p-3 border rounded-lg cursor-pointer text-center transition-all peer-checked:border-primary peer-checked:ring-2 peer-checked:ring-primary peer-checked:bg-primary/5">
                                            <i class="fab fa-whatsapp mb-1 block" style="color: var(--primary);"></i>
                                            <span class="font-medium">WhatsApp</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="flex items-start mt-4">
                                    <input type="checkbox"
                                           id="tenant_signature_required"
                                           name="tenant_signature_required"
                                           value="1"
                                           class="lease-custom-checkbox mt-1"
                                           checked>
                                    <label for="tenant_signature_required" class="ml-2 text-sm" style="color: var(--text-primary);">
                                        <span class="font-medium">Require Tenant Signature</span>
                                        <p class="mt-1" style="color: var(--text-secondary);">
                                            Tenant must sign before the lease becomes active
                                        </p>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="mt-8 pt-6 border-t flex justify-between" style="border-color: var(--border-color);">
                    <div>
                        <a href="{{ route('property-units.show', $unit->id) }}"
                           class="btn-secondary px-6 py-2 rounded-lg font-medium inline-flex items-center">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </a>
                    </div>
                    <div class="flex space-x-3">
                        <button type="submit"
                                class="btn-primary px-6 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-file-contract mr-2"></i> Create Lease Agreement
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Lease Summary Preview -->
    <div class="card mt-6">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-eye mr-2" style="color: var(--primary);"></i> Lease Summary Preview
            </h3>

            {{-- ✅ GHANA: 6-card grid now includes Advance Rent + Monthly Payment --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4" id="previewGrid">
                <div class="card border text-center p-4" style="border-color: var(--border-color);">
                    <div class="text-sm" style="color: var(--text-secondary);">Monthly Rent</div>
                    <div class="text-2xl font-bold mt-1" style="color: var(--text-primary);" id="previewMonthlyRent">
                        {{ $currencySymbol }} {{ number_format($unitRent, 2) }}
                    </div>
                </div>

                {{-- ✅ GHANA: Advance Rent preview card --}}
                <div class="card border text-center p-4" style="border-color: var(--primary); background-color: rgba(var(--primary-rgb), 0.03);">
                    <div class="text-sm" style="color: var(--text-secondary);">Advance Rent</div>
                    <div class="text-2xl font-bold mt-1" style="color: var(--primary);" id="previewAdvanceRent">
                        {{ $currencySymbol }} {{ number_format($suggestedAdvanceAmount, 2) }}
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);" id="previewAdvanceMonths">
                        {{ $defaultAdvanceMonths }} month(s) upfront
                    </div>
                </div>

                <div class="card border text-center p-4" style="border-color: var(--border-color);">
                    <div class="text-sm" style="color: var(--text-secondary);">Security Deposit</div>
                    <div class="text-2xl font-bold mt-1" style="color: var(--text-primary);" id="previewSecurityDeposit">
                        {{ $currencySymbol }} 0.00
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);" id="previewDepositMethod"></div>
                </div>

                <div class="card border text-center p-4" style="border-color: var(--border-color);">
                    <div class="text-sm" style="color: var(--text-secondary);">Lease Duration</div>
                    <div class="text-2xl font-bold mt-1" style="color: var(--text-primary);" id="previewLeaseDuration">
                        {{ $defaultDurationMonths }} Months
                    </div>
                </div>

                <div class="card border text-center p-4" style="border-color: var(--border-color);">
                    <div class="text-sm" style="color: var(--text-secondary);">Total First Payment</div>
                    <div class="text-2xl font-bold mt-1" style="color: var(--text-primary);" id="previewTotalFirstPayment">
                        {{ $currencySymbol }} {{ number_format($suggestedAdvanceAmount + $unitRent, 2) }}
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);" id="previewFirstPaymentNote"></div>
                </div>

                <div class="card border text-center p-4" style="border-color: var(--border-color);">
                    <div class="text-sm" style="color: var(--text-secondary);">Monthly Phase Starts</div>
                    <div class="text-2xl font-bold mt-1" style="color: var(--text-primary);" id="previewMonthlyPhaseStart">
                        {{ now()->addMonths($defaultAdvanceMonths)->format('M j, Y') }}
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);" id="previewMonthlyPhaseNote">
                        Advance period ends
                    </div>
                </div>
            </div>

            {{-- ✅ GHANA: Compliance badge --}}
            <div class="mt-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Compliance:</span>
                    <span id="previewComplianceBadge" class="px-3 py-1 rounded-full text-xs badge-success">
                        <i class="fas fa-check-circle mr-1"></i> Compliant with {{ $governingLaw }}
                    </span>
                </div>
                <div class="text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Legal max: {{ $legalMaxAdvanceNew }} months (new tenancy)
                </div>
            </div>

            <!-- Additional Preview Details -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="card border p-4" style="border-color: var(--border-color);">
                    <div class="text-sm font-medium mb-2" style="color: var(--text-primary);">Lease Term Details</div>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Start Date:</span>
                            <span style="color: var(--text-primary);" id="previewStartDate">{{ now()->format('F j, Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">End Date:</span>
                            <span style="color: var(--text-primary);" id="previewEndDate">{{ now()->addYear()->format('F j, Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Lease Type:</span>
                            <span style="color: var(--text-primary);" id="previewLeaseType">Fixed Term</span>
                        </div>
                    </div>
                </div>

                <div class="card border p-4" style="border-color: var(--border-color);">
                    <div class="text-sm font-medium mb-2" style="color: var(--text-primary);">Payment Terms</div>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Due Day:</span>
                            <span style="color: var(--text-primary);" id="previewDueDay">{{ $defaultPaymentDueDay }}{{ $defaultPaymentDueDay == 1 ? 'st' : ($defaultPaymentDueDay == 2 ? 'nd' : ($defaultPaymentDueDay == 3 ? 'rd' : 'th')) }} of each month</span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Grace Period:</span>
                            <span style="color: var(--text-primary);" id="previewGracePeriod">{{ $defaultGraceDays > 0 ? $defaultGraceDays . ' days' : 'Not specified' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Late Fee:</span>
                            <span style="color: var(--text-primary);" id="previewLateFee">Not specified</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmationModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideConfirmationModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i> Confirm Lease Creation
                </h3>
                <button type="button" onclick="hideConfirmationModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                        Are you sure you want to create this lease agreement?
                    </p>
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                        <ul class="text-xs space-y-2" style="color: var(--text-secondary);">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                                <span>A new lease agreement will be created</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                                <span>Advance-rent invoice will be generated automatically</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                                <span>Monthly invoices will be scheduled for the post-advance phase</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                                <span>Tenant will be notified for signature</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="hideConfirmationModal()"
                        class="btn-secondary px-4 py-2 rounded-lg font-medium">
                    Cancel
                </button>
                <button type="button"
                        onclick="submitLeaseForm()"
                        class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                    <i class="fas fa-file-contract mr-2"></i> Create Lease
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// ✅ GHANA: Constants exposed to JS
const GHANA_CONFIG = {
    legalMaxNew: {{ $legalMaxAdvanceNew }},
    legalMaxRenewal: {{ $legalMaxAdvanceRenewal }},
    legalMaxShort: {{ $legalMaxAdvanceShort }},
    shortTenancyThreshold: {{ $shortTenancyThreshold }},
    currencySymbol: @json($currencySymbol),
    requireAcknowledgement: {{ $requireAcknowledgement ? 'true' : 'false' }},
};

document.addEventListener('DOMContentLoaded', function() {
    initFormInteractions();
    setupPreviewUpdates();
    autoHideMessages();
    initializeCurrencyFields();

    // ✅ GHANA: initialize compliance state from old() or defaults
    togglePaymentFrequency(
        document.querySelector('input[name="payment_frequency"]:checked')?.value || 'monthly'
    );
    checkAdvanceCompliance();
});

function initFormInteractions() {
    const selectedLeaseType = document.querySelector('input[name="lease_type"]:checked');
    if (selectedLeaseType) toggleLeaseDuration(selectedLeaseType.value);

    toggleInvitationChannels(document.getElementById('send_to_tenant').checked);

    const enableDeposit = document.getElementById('enable_security_deposit');
    if (enableDeposit) toggleSecurityDeposit(enableDeposit.checked);

    document.getElementById('start_date').addEventListener('change', () => {
        updateEndDate();
        updateAdvancePreview();
    });
    document.getElementById('duration_months').addEventListener('change', () => {
        updateEndDate();
        updateAdvancePreview();
    });

    document.getElementById('monthly_rent').addEventListener('input', () => {
        updateFinancialPreview();
        updateAdvancePreview();
    });
    document.getElementById('utility_deposit').addEventListener('input', updateFinancialPreview);
    document.getElementById('grace_period_days').addEventListener('input', updatePaymentPreview);
    document.getElementById('late_fee_percentage').addEventListener('input', updatePaymentPreview);
    document.getElementById('payment_due_day').addEventListener('change', () => {
        updatePaymentPreview();
        updateAdvancePreview();
    });
    document.getElementById('notice_period_days').addEventListener('input', updatePaymentPreview);

    // ✅ GHANA: advance rent month changes
    const advanceSelect = document.getElementById('advance_rent_months');
    if (advanceSelect) {
        advanceSelect.addEventListener('change', () => {
            updateAdvancePreview();
            checkAdvanceCompliance();
        });
    }
}

function initializeCurrencyFields() {
    const currencyFields = ['monthly_rent','security_deposit','utility_deposit','early_termination_fee','late_fee_fixed'];

    currencyFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.style.paddingLeft = '3.5rem';

            field.addEventListener('focus', function() {
                if (this.value === '0.00' || this.value === '0') this.value = '';
            });

            field.addEventListener('blur', function() {
                if (this.value === '') this.value = '0.00';
                if (this.value && !isNaN(parseFloat(this.value))) {
                    this.value = parseFloat(this.value).toFixed(2);
                }
            });
        }
    });
}

function setupPreviewUpdates() {
    updateFinancialPreview();
    updateEndDate();
    updatePaymentPreview();
    updateAdvancePreview();
}

function toggleLeaseDuration(leaseType) {
    const fixedTermFields = document.getElementById('fixedTermFields');
    const durationMonthsField = document.getElementById('duration_months');
    const endDateInput = document.getElementById('end_date');
    const calculatedEndDate = document.getElementById('calculatedEndDate');

    if (leaseType === 'fixed') {
        fixedTermFields.style.display = 'block';
        durationMonthsField.required = true;
        endDateInput.required = true;
        updateEndDate();
        document.getElementById('previewLeaseType').textContent = 'Fixed Term';
    } else {
        fixedTermFields.style.display = 'none';
        durationMonthsField.required = false;
        endDateInput.required = false;
        endDateInput.value = '';
        calculatedEndDate.textContent = 'Month-to-Month';
        document.getElementById('previewLeaseType').textContent = 'Month-to-Month';
        document.getElementById('previewLeaseDuration').textContent = 'Month-to-Month';
        document.getElementById('previewEndDate').textContent = 'Ongoing';
    }
    // ✅ GHANA: recheck compliance when term changes
    checkAdvanceCompliance();
}

function toggleInvitationChannels(isEnabled) {
    const invitationChannels = document.getElementById('invitationChannels');
    const channelInputs = invitationChannels.querySelectorAll('input[type="checkbox"]');

    if (isEnabled) {
        invitationChannels.style.display = 'block';
        channelInputs.forEach(input => input.disabled = false);
    } else {
        invitationChannels.style.display = 'none';
        channelInputs.forEach(input => {
            input.disabled = true;
            if (input.value !== 'email') input.checked = false;
        });
    }
}

function toggleSecurityDeposit(enabled) {
    const securityDepositFields = document.getElementById('securityDepositFields');
    const depositInput = document.getElementById('security_deposit');

    if (enabled) {
        securityDepositFields.style.display = 'block';
        depositInput.required = true;

        if (!depositInput.value) {
            const monthlyRent = parseFloat(document.getElementById('monthly_rent').value) || 0;
            depositInput.value = (monthlyRent * 2).toFixed(2);
        }
        updateDepositCalculation();
    } else {
        securityDepositFields.style.display = 'none';
        depositInput.required = false;
        depositInput.value = '';
        document.getElementById('deposit_upfront').checked = true;
        toggleDepositInstallment(false);
        updateFinancialPreview();
    }
}

function toggleDepositInstallment(isInstallment) {
    const installmentOptions = document.getElementById('installmentOptions');

    if (isInstallment) {
        installmentOptions.style.display = 'block';
        document.getElementById('deposit_installment_months').required = true;
    } else {
        installmentOptions.style.display = 'none';
        document.getElementById('deposit_installment_months').required = false;
    }
    updateDepositCalculation();
}

function updateDepositCalculation() {
    const totalDeposit = parseFloat(document.getElementById('security_deposit').value) || 0;
    const isInstallment = document.getElementById('deposit_installment').checked;
    const installmentMonths = parseInt(document.getElementById('deposit_installment_months').value) || 3;

    document.getElementById('totalDepositDisplay').textContent = formatCurrencyAmount(totalDeposit);
    document.getElementById('installmentPeriodDisplay').textContent = installmentMonths;
    document.getElementById('installmentPeriodDisplay2').textContent = installmentMonths;

    if (isInstallment && totalDeposit > 0) {
        const monthlyInstallment = totalDeposit / installmentMonths;
        document.getElementById('monthlyDepositInstallment').textContent = formatCurrency(monthlyInstallment);
    } else {
        document.getElementById('monthlyDepositInstallment').textContent = formatCurrency(0);
    }
    updateFinancialPreview();
}

function updateEndDate() {
    const startDateInput = document.getElementById('start_date');
    const durationMonths = document.getElementById('duration_months').value;
    const calculatedEndDate = document.getElementById('calculatedEndDate');
    const endDateInput = document.getElementById('end_date');
    const leaseType = document.querySelector('input[name="lease_type"]:checked').value;

    if (leaseType === 'fixed' && startDateInput.value && durationMonths) {
        const startDate = new Date(startDateInput.value);
        const endDate = new Date(startDate);
        endDate.setMonth(endDate.getMonth() + parseInt(durationMonths));

        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        calculatedEndDate.textContent = endDate.toLocaleDateString('en-US', options);

        const formattedEndDate = endDate.toISOString().split('T')[0];
        endDateInput.value = formattedEndDate;

        document.getElementById('previewLeaseDuration').textContent =
            durationMonths + ' Month' + (durationMonths > 1 ? 's' : '');
        document.getElementById('previewStartDate').textContent = startDate.toLocaleDateString('en-US', options);
        document.getElementById('previewEndDate').textContent = endDate.toLocaleDateString('en-US', options);
    }
}

// ============================================================
// ✅ GHANA: ADVANCE RENT LOGIC
// ============================================================

function togglePaymentFrequency(frequency) {
    const monthlyInfo = document.getElementById('monthlyPhaseInfo');
    const advanceSelect = document.getElementById('advance_rent_months');

    if (frequency === 'monthly') {
        monthlyInfo.style.display = 'block';
        advanceSelect.disabled = false;
    } else {
        monthlyInfo.style.display = 'none';
        // For full-advance: auto-select duration_months
        const durationMonths = document.getElementById('duration_months').value || 12;
        if (advanceSelect.querySelector(`option[value="${durationMonths}"]`)) {
            advanceSelect.value = durationMonths;
        }
    }

    updateAdvancePreview();
    checkAdvanceCompliance();
}

function updateAdvancePreview() {
    const monthlyRent = parseFloat(document.getElementById('monthly_rent').value) || 0;
    const advanceMonths = parseInt(document.getElementById('advance_rent_months').value) || 0;
    const startDate = document.getElementById('start_date').value;
    const paymentDueDay = parseInt(document.getElementById('payment_due_day').value) || 5;
    const paymentFrequency = document.querySelector('input[name="payment_frequency"]:checked')?.value || 'monthly';

    const advanceAmount = monthlyRent * advanceMonths;

    // Update the advance amount display
    document.getElementById('advanceAmountDisplay').textContent =
        GHANA_CONFIG.currencySymbol + ' ' + formatCurrencyAmount(advanceAmount);

    // Update advance period display
    if (startDate) {
        const start = new Date(startDate);
        const end = new Date(start);
        end.setMonth(end.getMonth() + advanceMonths);
        document.getElementById('advancePeriodDisplay').textContent =
            start.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) +
            ' → ' +
            end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

        // Update monthly phase info
        if (paymentFrequency === 'monthly') {
            const monthlyStart = new Date(end);
            monthlyStart.setDate(Math.min(paymentDueDay, 28));
            document.getElementById('monthlyPhaseAfterMonths').textContent = advanceMonths;
            document.getElementById('monthlyPhaseAmount').textContent =
                GHANA_CONFIG.currencySymbol + ' ' + formatCurrencyAmount(monthlyRent);
            document.getElementById('monthlyPhaseStartDate').textContent =
                monthlyStart.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

            document.getElementById('previewMonthlyPhaseStart').textContent =
                monthlyStart.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
    }

    // Update preview cards
    document.getElementById('previewAdvanceRent').textContent =
        GHANA_CONFIG.currencySymbol + ' ' + formatCurrencyAmount(advanceAmount);
    document.getElementById('previewAdvanceMonths').textContent =
        advanceMonths + ' month(s) upfront';

    // Recompute total first payment
    updateFinancialPreview();
}

function checkAdvanceCompliance() {
    const advanceMonths = parseInt(document.getElementById('advance_rent_months').value) || 0;
    const leaseType = document.querySelector('input[name="lease_type"]:checked')?.value || 'fixed';
    const durationMonths = parseInt(document.getElementById('duration_months').value) || 0;

    // Determine the correct legal cap
    let legalMax = GHANA_CONFIG.legalMaxNew;
    let reason = 'new tenancy >6 months';

    if (leaseType === 'fixed' && durationMonths <= GHANA_CONFIG.shortTenancyThreshold) {
        legalMax = GHANA_CONFIG.legalMaxShort;
        reason = 'short tenancy (≤' + GHANA_CONFIG.shortTenancyThreshold + ' months)';
    } else if (leaseType === 'month_to_month') {
        legalMax = GHANA_CONFIG.legalMaxShort;
        reason = 'month-to-month tenancy';
    }

    const warning = document.getElementById('complianceWarning');
    const badge = document.getElementById('previewComplianceBadge');
    const legalMaxSpan = document.getElementById('complianceLegalMax');
    if (legalMaxSpan) legalMaxSpan.textContent = legalMax;

    if (advanceMonths > legalMax) {
        warning.style.display = 'block';
        badge.className = 'px-3 py-1 rounded-full text-xs badge-warning';
        badge.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Exceeds legal cap (' + legalMax + ' months)';
    } else {
        warning.style.display = 'none';
        badge.className = 'px-3 py-1 rounded-full text-xs badge-success';
        badge.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Compliant with ' + GHANA_CONFIG.legalMaxNew + '-month cap';
    }
}

function toggleAckHighlight(checked) {
    const ack = document.getElementById('advance_rent_acknowledged');
    if (!ack) return;
    ack.closest('label').style.opacity = checked ? '1' : '0.85';
}

// ============================================================
// EXISTING PREVIEW & VALIDATION LOGIC
// ============================================================

function updateFinancialPreview() {
    const monthlyRent = parseFloat(document.getElementById('monthly_rent').value) || 0;
    const utilityDeposit = parseFloat(document.getElementById('utility_deposit').value) || 0;
    const enableDeposit = document.getElementById('enable_security_deposit').checked;
    const totalDeposit = enableDeposit ? (parseFloat(document.getElementById('security_deposit').value) || 0) : 0;
    const isInstallment = enableDeposit && document.getElementById('deposit_installment')?.checked;
    const installmentMonths = isInstallment ? (parseInt(document.getElementById('deposit_installment_months')?.value) || 3) : 1;
    const advanceMonths = parseInt(document.getElementById('advance_rent_months').value) || 0;
    const paymentFrequency = document.querySelector('input[name="payment_frequency"]:checked')?.value || 'monthly';

    // ✅ GHANA: First payment depends on payment frequency
    let firstPayment = 0;
    let depositNote = '';
    let depositMethodText = '';
    let note = '';

    if (paymentFrequency === 'advance_only') {
        // Full advance — first payment = advance + deposit + utility
        firstPayment = (monthlyRent * advanceMonths) + utilityDeposit;
        note = 'Full advance (' + advanceMonths + ' months)';
    } else {
        // Advance + monthly — first payment = advance + deposit + utility
        firstPayment = (monthlyRent * advanceMonths) + utilityDeposit;
        note = 'Advance rent (' + advanceMonths + ' months)';
    }

    // Update monthly rent preview
    document.getElementById('previewMonthlyRent').textContent =
        GHANA_CONFIG.currencySymbol + ' ' + formatCurrencyAmount(monthlyRent);

    // Deposit section
    if (enableDeposit && totalDeposit > 0) {
        if (isInstallment) {
            const monthlyInstallment = totalDeposit / installmentMonths;
            firstPayment += monthlyInstallment;
            depositNote = 'First installment (' + formatCurrency(monthlyInstallment) + ') + remaining ' + (installmentMonths - 1) + ' installments';
            depositMethodText = 'Paid as ' + formatCurrency(monthlyInstallment) + '/month for ' + installmentMonths + ' months';

            document.getElementById('previewSecurityDeposit').textContent =
                GHANA_CONFIG.currencySymbol + ' ' + formatCurrencyAmount(totalDeposit);
            document.getElementById('previewDepositMethod').textContent = depositMethodText;
            document.getElementById('previewFirstPaymentNote').textContent = depositNote;
        } else {
            firstPayment += totalDeposit;
            depositNote = 'Full deposit paid upfront';
            depositMethodText = 'Paid upfront';
            document.getElementById('previewSecurityDeposit').textContent =
                GHANA_CONFIG.currencySymbol + ' ' + formatCurrencyAmount(totalDeposit);
            document.getElementById('previewDepositMethod').textContent = depositMethodText;
            document.getElementById('previewFirstPaymentNote').textContent = depositNote;
        }
    } else {
        document.getElementById('previewSecurityDeposit').textContent = GHANA_CONFIG.currencySymbol + ' 0.00';
        document.getElementById('previewDepositMethod').textContent = '';
        document.getElementById('previewFirstPaymentNote').textContent = note;
    }

    // Update total first payment
    document.getElementById('previewTotalFirstPayment').textContent =
        GHANA_CONFIG.currencySymbol + ' ' + formatCurrencyAmount(firstPayment);
}

function updatePaymentPreview() {
    const gracePeriod = document.getElementById('grace_period_days').value;
    const lateFeePercentage = document.getElementById('late_fee_percentage').value;
    const paymentDueDay = document.getElementById('payment_due_day').value || 5;

    document.getElementById('previewGracePeriod').textContent =
        (gracePeriod && gracePeriod > 0) ? gracePeriod + ' days' : 'Not specified';

    document.getElementById('previewLateFee').textContent =
        (lateFeePercentage && lateFeePercentage > 0) ? lateFeePercentage + '% of rent' : 'Not specified';

    document.getElementById('previewDueDay').textContent =
        paymentDueDay + getDaySuffix(paymentDueDay) + ' of each month';
}

function getDaySuffix(day) {
    if (day >= 11 && day <= 13) return 'th';
    switch (day % 10) {
        case 1: return 'st';
        case 2: return 'nd';
        case 3: return 'rd';
        default: return 'th';
    }
}

function formatCurrency(amount) {
    return GHANA_CONFIG.currencySymbol + ' ' + amount.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function formatCurrencyAmount(amount) {
    return amount.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function showConfirmationModal() {
    document.getElementById('confirmationModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideConfirmationModal() {
    const modal = document.getElementById('confirmationModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function submitLeaseForm() {
    document.getElementById('leaseForm').submit();
}

function validateLeaseForm() {
    const leaseType = document.querySelector('input[name="lease_type"]:checked').value;
    const monthlyRent = parseFloat(document.getElementById('monthly_rent').value) || 0;
    const startDate = document.getElementById('start_date').value;
    const enableDeposit = document.getElementById('enable_security_deposit').checked;
    const securityDeposit = enableDeposit ? (parseFloat(document.getElementById('security_deposit').value) || 0) : 0;
    const noticePeriod = parseInt(document.getElementById('notice_period_days').value) || 0;
    const sendToTenant = document.getElementById('send_to_tenant').checked;
    const advanceMonths = parseInt(document.getElementById('advance_rent_months').value) || 0;

    let isValid = true;
    let errorMessages = [];

    if (!startDate) { isValid = false; errorMessages.push('Lease start date is required'); }
    if (monthlyRent <= 0) { isValid = false; errorMessages.push('Monthly rent must be greater than 0'); }
    if (enableDeposit && securityDeposit < 0) { isValid = false; errorMessages.push('Security deposit cannot be negative'); }

    if (leaseType === 'fixed') {
        const durationMonths = document.getElementById('duration_months').value;
        if (!durationMonths || parseInt(durationMonths) < 1) {
            isValid = false;
            errorMessages.push('Lease duration is required for fixed term leases');
        }
    }

    if (noticePeriod < 15 || noticePeriod > 90) {
        isValid = false;
        errorMessages.push('Notice period must be between 15 and 90 days');
    }

    if (sendToTenant) {
        const channelInputs = document.querySelectorAll('input[name="invitation_channels[]"]:checked');
        if (channelInputs.length === 0) {
            isValid = false;
            errorMessages.push('At least one communication channel must be selected');
        }
    }

    // ✅ GHANA: advance-rent validation
    if (advanceMonths < 1 || advanceMonths > 60) {
        isValid = false;
        errorMessages.push('Advance rent must be between 1 and 60 months');
    }

    if (leaseType === 'fixed') {
        const durationMonths = parseInt(document.getElementById('duration_months').value) || 0;
        if (advanceMonths > durationMonths) {
            isValid = false;
            errorMessages.push('Advance rent cannot exceed the total lease duration');
        }
    }

    // ✅ GHANA: acknowledgement required if exceeds cap
    const advanceMonthsInt = parseInt(advanceMonths);
    const durationMonthsInt = parseInt(document.getElementById('duration_months').value) || 0;
    let legalMax = GHANA_CONFIG.legalMaxNew;
    if (leaseType === 'fixed' && durationMonthsInt <= GHANA_CONFIG.shortTenancyThreshold) {
        legalMax = GHANA_CONFIG.legalMaxShort;
    } else if (leaseType === 'month_to_month') {
        legalMax = GHANA_CONFIG.legalMaxShort;
    }

    if (advanceMonthsInt > legalMax && GHANA_CONFIG.requireAcknowledgement) {
        const ack = document.getElementById('advance_rent_acknowledged');
        if (ack && !ack.checked) {
            isValid = false;
            errorMessages.push(
                'Advance rent exceeds the legal maximum of ' + legalMax + ' months. ' +
                'Please tick the acknowledgement checkbox to confirm the tenant voluntarily offered it.'
            );
        }
    }

    if (!isValid) alert('Please fix the following errors:\n\n' + errorMessages.join('\n'));
    return isValid;
}

function autoHideMessages() {
    setTimeout(() => {
        ['bg-green-100','bg-red-100','bg-yellow-100'].forEach(cls => {
            document.querySelectorAll('.' + cls).forEach(msg => {
                if (msg.style.display !== 'none') msg.style.display = 'none';
            });
        });
    }, 5000);
}

document.getElementById('leaseForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (!validateLeaseForm()) return false;

    const sendToTenant = document.getElementById('send_to_tenant').checked;
    if (sendToTenant) {
        showConfirmationModal();
    } else {
        if (confirm('Create lease without sending to tenant?')) this.submit();
    }
});
</script>


<style>
/* Lease-specific form control styles */
.lease-custom-dropdown {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}

/* Dark mode SVG icon */
[data-theme="dark"] .lease-custom-dropdown {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%23e4e4e4' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
}

/* Light mode SVG icon */
[data-theme="light"] .lease-custom-dropdown {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%234b4b4b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
}

.lease-custom-dropdown:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.lease-custom-input {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

.lease-custom-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Specific styles for inputs with GHS symbol */
.lease-custom-input[style*="padding-left"] {
    padding-left: 3.5rem !important;
}

/* Position GHS symbol inside inputs */
.relative .absolute.inset-y-0.left-0.pl-3 {
    padding-left: 1rem;
    z-index: 10;
    pointer-events: none;
}

.relative .absolute.inset-y-0.left-0.pl-3 span {
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 0.875rem;
}

/* Position unit labels (days, %) */
.relative .absolute.inset-y-0.right-0.pr-3 {
    padding-right: 0.75rem;
    z-index: 10;
    pointer-events: none;
}

.relative .absolute.inset-y-0.right-0.pr-3 span {
    color: var(--text-secondary);
    font-size: 0.75rem;
}

.lease-custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
    resize: vertical;
    min-height: 80px;
}

.lease-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.lease-custom-checkbox {
    width: 1rem;
    height: 1rem;
    border-radius: 0.25rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    cursor: pointer;
    transition: all 0.2s;
}

.lease-custom-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z'/%3E%3C/svg%3E");
    background-size: 100% 100%;
    background-position: center;
    background-repeat: no-repeat;
}

.lease-custom-checkbox:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Placeholder color */
.lease-custom-input::placeholder,
.lease-custom-textarea::placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

/* Card hover effects */
.card.border:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Radio/Checkbox card selection */
.peer:checked + label {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.2);
}

/* Modal styles */
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
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.modal-close-btn {
    padding: 0.5rem;
    border-radius: 0.375rem;
    transition: background-color 0.2s;
    cursor: pointer;
    background: none;
    border: none;
}

.modal-close-btn:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

/* Button styles */
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

.btn-secondary:hover {
    background-color: rgba(var(--secondary-rgb), 0.2) !important;
    transform: translateY(-1px);
}

.btn-modern {
    background: linear-gradient(to right, var(--primary), var(--secondary)) !important;
    color: white !important;
    border: none !important;
    transition: all 0.2s ease;
}

.btn-modern:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

/* Preview card styles */
.preview-card {
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.preview-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

/* Badge styles */
.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
}

.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1) !important;
    color: var(--danger) !important;
    border: 1px solid rgba(var(--danger-rgb), 0.3) !important;
}

.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1) !important;
    color: var(--primary) !important;
    border: 1px solid rgba(var(--primary-rgb), 0.3) !important;
}

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .modal-container {
        width: 95%;
        max-height: 80vh;
        margin: 0.5rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn, .action-btn {
        width: 100%;
        justify-content: center;
    }
    
    /* Adjust GHS padding for mobile */
    .lease-custom-input[style*="padding-left"] {
        padding-left: 3rem !important;
    }
    
    .relative .absolute.inset-y-0.left-0.pl-3 {
        padding-left: 0.75rem;
    }
}

/* Animation for form sections */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    animation: fadeInUp 0.3s ease-out;
}

/* Custom scrollbar for modal */
.modal-container::-webkit-scrollbar {
    width: 8px;
}

.modal-container::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 4px;
}

.modal-container::-webkit-scrollbar-thumb {
    background: rgba(var(--primary-rgb), 0.3);
    border-radius: 4px;
}

.modal-container::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--primary-rgb), 0.5);
}

/* Focus states for accessibility */
.lease-custom-dropdown:focus-visible,
.lease-custom-input:focus-visible,
.lease-custom-textarea:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Required field indicator */
label[for] + span.required::after {
    content: " *";
    color: var(--danger);
    font-weight: bold;
}

/* Tooltip styles */
[data-tooltip] {
    position: relative;
    cursor: help;
}

[data-tooltip]:hover::before {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 0.5rem 0.75rem;
    background-color: var(--card-bg);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 0.375rem;
    font-size: 0.75rem;
    white-space: nowrap;
    z-index: 10;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

/* Loading animation */
.fa-spinner.fa-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Status colors */
.status-active {
    color: var(--success);
}

.status-pending {
    color: var(--warning);
}

.status-draft {
    color: var(--secondary);
}

.status-terminated {
    color: var(--danger);
}

/* Number input spinner */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    opacity: 1;
    height: auto;
    margin: 0;
}

/* Form validation styles */
.lease-custom-input:invalid,
.lease-custom-dropdown:invalid,
.lease-custom-textarea:invalid {
    border-color: var(--danger);
}

.lease-custom-input:valid,
.lease-custom-dropdown:valid,
.lease-custom-textarea:valid {
    border-color: var(--success);
}

/* Section divider */
.section-divider {
    position: relative;
    text-align: center;
    margin: 2rem 0;
}

.section-divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background-color: var(--border-color);
    z-index: 1;
}

.section-divider span {
    position: relative;
    display: inline-block;
    padding: 0 1rem;
    background-color: var(--card-bg);
    color: var(--text-secondary);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    z-index: 2;
}

/* Currency field specific styles */
.currency-input-group {
    position: relative;
}

.currency-input-group .currency-symbol {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 0.875rem;
    pointer-events: none;
    z-index: 10;
}

/* Ensure proper spacing for fields with both prefix and suffix */
.input-with-both {
    padding-left: 3.5rem !important;
    padding-right: 2.5rem !important;
}

/* Number input specific styling */
input[type="number"] {
    -moz-appearance: textfield;
}

input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Date input styling */
input[type="date"] {
    appearance: none;
    -webkit-appearance: none;
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

input[type="date"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Ensure consistent height for all form controls */
.lease-custom-dropdown,
.lease-custom-input,
.lease-custom-textarea,
input[type="date"] {
    min-height: 42px;
}

/* Improved placeholder visibility */
.lease-custom-input::placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

/* Dark mode adjustments for currency fields */
[data-theme="dark"] .relative .absolute.inset-y-0.left-0.pl-3 span {
    color: #a0aec0;
}

[data-theme="light"] .relative .absolute.inset-y-0.left-0.pl-3 span {
    color: #6b7280;
}

/* Enhanced validation feedback */
.valid-feedback {
    color: var(--success);
    font-size: 0.875rem;
    margin-top: 0.25rem;
    display: none;
}

.invalid-feedback {
    color: var(--danger);
    font-size: 0.875rem;
    margin-top: 0.25rem;
    display: none;
}

.was-validated .form-control:valid ~ .valid-feedback {
    display: block;
}

.was-validated .form-control:invalid ~ .invalid-feedback {
    display: block;
}

/* Alert styles */
.alert {
    padding: 1rem;
    border-radius: 0.375rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
}

.alert-success {
    background-color: rgba(var(--success-rgb), 0.1);
    border-color: rgba(var(--success-rgb), 0.3);
    color: var(--success);
}

.alert-warning {
    background-color: rgba(var(--warning-rgb), 0.1);
    border-color: rgba(var(--warning-rgb), 0.3);
    color: var(--warning);
}

.alert-danger {
    background-color: rgba(var(--danger-rgb), 0.1);
    border-color: rgba(var(--danger-rgb), 0.3);
    color: var(--danger);
}

.alert-info {
    background-color: rgba(var(--primary-rgb), 0.1);
    border-color: rgba(var(--primary-rgb), 0.3);
    color: var(--primary);
}
</style>
@endsection