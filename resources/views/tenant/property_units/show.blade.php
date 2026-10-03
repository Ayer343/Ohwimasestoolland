{{-- resources/views/tenant/property_units/show.blade.php --}}
@php
    use App\Models\PropertyUnitInvoice;

    $layout   = 'layouts.tenant';
    $isTenant = true;
    $user     = auth()->user();

    // ========== ✅ GHANA: config snapshots ==========
    $currencySymbol = config('leases.ghana.currency.symbol', 'GH₵');
    $governingLaw   = config('leases.ghana.governing_law', 'Rent Act, 1963 (Act 220)');
    $legalMaxNew    = (int) config('leases.ghana.legal_max_advance_months.new_tenancy', 6);

    // ========== LEASE CONTEXT ==========
    $currentLease     = $unit->currentLease;
    $hasAdvanceRent   = $currentLease?->has_advance_rent ?? false;
    $isInAdvancePhase = $currentLease?->is_in_advance_phase ?? false;
    $isInMonthlyPhase = $currentLease?->is_in_monthly_phase ?? false;
    $isCompliant      = $currentLease?->is_advance_rent_compliant ?? true;
    $advanceMonths    = (int) ($currentLease?->advance_rent_months ?? 0);
    $advanceAmount    = (float) ($currentLease?->advance_rent_amount ?? 0);
    $advanceMonthsLeft= $currentLease?->advance_months_remaining ?? 0;
    $advancePeriodEnd = $currentLease?->advance_rent_period_end;
    $nextPaymentDate  = $currentLease?->next_payment_due_date;
    $nextPaymentAmt   = $currentLease?->next_payment_amount ?? 0;

    // ========== ✅ INVOICE: SUMMARY ==========
    $invoices = $unit->invoices ?? collect();
    $recentInvoices = $invoices->sortByDesc('created_at')->take(5);

    $invoiceSummary = [
        'total_invoiced' => (float) $invoices->sum('amount'),
        'total_paid'     => (float) $invoices->sum('amount_paid'),
        'outstanding'    => (float) $invoices
            ->whereIn('status', [PropertyUnitInvoice::STATUS_PENDING, PropertyUnitInvoice::STATUS_PARTIAL, PropertyUnitInvoice::STATUS_OVERDUE])
            ->sum(fn ($i) => max(0, $i->amount - $i->amount_paid)),
        'overdue_count'  => $invoices->where('status', PropertyUnitInvoice::STATUS_OVERDUE)->count(),
        'paid_count'     => $invoices->where('status', PropertyUnitInvoice::STATUS_PAID)->count(),
        'pending_count'  => $invoices->where('status', PropertyUnitInvoice::STATUS_PENDING)->count(),
        'partial_count'  => $invoices->where('status', PropertyUnitInvoice::STATUS_PARTIAL)->count(),
    ];

    // ========== ✅ INVOICE: STATUS META ==========
    $invoiceStatusMeta = [
        PropertyUnitInvoice::STATUS_PENDING => ['class' => 'badge-warning',   'icon' => 'clock',                'label' => 'Pending'],
        PropertyUnitInvoice::STATUS_PARTIAL => ['class' => 'badge-info',      'icon' => 'adjust',               'label' => 'Partial'],
        PropertyUnitInvoice::STATUS_PAID    => ['class' => 'badge-success',   'icon' => 'check-circle',         'label' => 'Paid'],
        PropertyUnitInvoice::STATUS_OVERDUE => ['class' => 'badge-danger',    'icon' => 'exclamation-triangle', 'label' => 'Overdue'],
        PropertyUnitInvoice::STATUS_VOID    => ['class' => 'badge-secondary', 'icon' => 'ban',                  'label' => 'Void'],
    ];

    // ========== AMENITY META (single source of truth) ==========
    $amenityMeta = [
        'parking'            => ['fa-parking',           'Parking Space'],
        'balcony'            => ['fa-umbrella-beach',    'Balcony'],
        'air_conditioning'   => ['fa-snowflake',         'Air Conditioning'],
        'furnished'          => ['fa-couch',             'Fully Furnished'],
        'wifi'               => ['fa-wifi',              'Wi-Fi'],
        'security'           => ['fa-shield-alt',       '24/7 Security'],
        'gym'                => ['fa-dumbbell',          'Gym Access'],
        'pool'               => ['fa-swimming-pool',     'Swimming Pool'],
        'laundry'            => ['fa-tshirt',            'Laundry Facility'],
        'elevator'           => ['fa-elevator',          'Elevator'],
        'generator'          => ['fa-bolt',              'Backup Generator'],
        'cctv'               => ['fa-video',             'CCTV Surveillance'],
        'fire_safety'        => ['fa-fire-extinguisher', 'Fire Safety System'],
        'water_heater'       => ['fa-shower',            'Water Heater'],
        'kitchen_appliances' => ['fa-blender',           'Kitchen Appliances'],
    ];

    // ========== LEASE STATUS META ==========
    $leaseStatusMeta = [
        'active'             => ['badge-success',   'check-circle', 'Active'],
        'draft'              => ['badge-warning',   'edit',         'Draft'],
        'pending_signature'  => ['badge-info',      'signature',    'Pending Signature'],
        'pending_landlord'   => ['badge-info',      'user-tie',     'Pending Landlord'],
        'pending_tenant'     => ['badge-info',      'user',         'Pending Tenant'],
        'expired'            => ['badge-secondary', 'clock',        'Expired'],
        'terminated'         => ['badge-danger',    'times-circle', 'Terminated'],
        'completed'          => ['badge-secondary', 'flag-checkered','Completed'],
        'renewed'            => ['badge-primary',   'sync-alt',     'Renewed'],
    ];
@endphp

@extends($layout)

@section('title', 'My Unit - ' . $unit->full_unit_identifier)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-4 gap-6 animate-fadeInUp">
    <!-- Main Content (3 columns) -->
    <div class="lg:col-span-3 space-y-6">

        {{-- ============================================================
             HEADER
             ============================================================ --}}
        <div class="card">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center gradient-bg">
                        <i class="fas fa-home text-white text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold" style="color: var(--text-primary);">
                            {{ $unit->full_unit_identifier }}
                        </h2>
                        <p class="text-sm flex items-center" style="color: var(--text-secondary);">
                            <i class="fas fa-building mr-1"></i>
                            {{ $unit->property->property_name }}
                            @if($currentLease)
                                <span class="mx-2">•</span>
                                <i class="fas fa-file-contract mr-1"></i>
                                {{ ucfirst(str_replace('_', ' ', $currentLease->status)) }}
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 mt-4 md:mt-0">
                    {{-- ✅ GHANA: phase badge --}}
                    @if($hasAdvanceRent)
                        @if($isInAdvancePhase)
                            <span class="px-3 py-1 rounded-full text-xs font-semibold badge-primary">
                                <i class="fas fa-calendar-check mr-1"></i>
                                Advance Phase ({{ $advanceMonthsLeft }} mo left)
                            </span>
                        @elseif($isInMonthlyPhase)
                            <span class="px-3 py-1 rounded-full text-xs font-semibold badge-success">
                                <i class="fas fa-calendar-day mr-1"></i> Monthly Phase
                            </span>
                        @endif
                    @endif

                    @if(Route::has('tenant.property-units.my-unit'))
                    <a href="{{ route('tenant.property-units.my-unit') }}"
                       class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                        <i class="fas fa-arrow-left mr-2"></i> Back to My Unit
                    </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- ============================================================
             SESSION MESSAGES
             ============================================================ --}}
        @foreach(['success' => ['bg-green-100','border-green-400','text-green-700','check-circle','Success!'],
                  'error'   => ['bg-red-100','border-red-400','text-red-700','exclamation-circle','Error!'],
                  'info'    => ['bg-blue-100','border-blue-400','text-blue-700','info-circle','Info!']] as $key => $cfg)
            @if(session($key))
            <div class="{{ $cfg[0] }} border {{ $cfg[1] }} {{ $cfg[2] }} px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">{{ $cfg[4] }}</strong>
                <span class="block sm:inline ml-1">{{ session($key) }}</span>
                <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            @endif
        @endforeach

        {{-- ============================================================
             QUICK STATS ROW
             ============================================================ --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <!-- Rent -->
            <div class="card p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full mr-3" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-money-bill-wave text-lg"></i>
                    </div>
                    <div>
                        <p class="text-xs font-medium" style="color: var(--text-secondary);">Monthly Rent</p>
                        <p class="text-xl font-bold" style="color: var(--text-primary);">
                            {{ $currencySymbol }} {{ number_format($unit->current_rent_amount ?? $unit->monthly_rent, 2) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Paid Invoices -->
            <div class="card p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full mr-3" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-file-invoice text-lg"></i>
                    </div>
                    <div>
                        <p class="text-xs font-medium" style="color: var(--text-secondary);">Paid Invoices</p>
                        <p class="text-xl font-bold" style="color: var(--text-primary);">
                            {{ $invoiceSummary['paid_count'] }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Pending -->
            <div class="card p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full mr-3" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-clock text-lg"></i>
                    </div>
                    <div>
                        <p class="text-xs font-medium" style="color: var(--text-secondary);">Pending</p>
                        <p class="text-xl font-bold" style="color: var(--text-primary);">
                            {{ $invoiceSummary['pending_count'] + $invoiceSummary['partial_count'] }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Maintenance -->
            <div class="card p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full mr-3" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-tools text-lg"></i>
                    </div>
                    <div>
                        <p class="text-xs font-medium" style="color: var(--text-secondary);">Maintenance</p>
                        <p class="text-xl font-bold" style="color: var(--text-primary);">
                            {{ $stats['active_maintenance_requests'] ?? 0 }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             ✅ INVOICE: OUTSTANDING BALANCE ALERT
             ============================================================ --}}
        @if($invoiceSummary['outstanding'] > 0)
        <div class="card border-l-4" style="border-left-color: var(--warning); background-color: rgba(var(--warning-rgb), 0.03);">
            <div class="p-5 flex items-start gap-3">
                <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--warning);"></i>
                <div class="flex-1">
                    <h4 class="font-semibold" style="color: var(--text-primary);">
                        Outstanding balance
                    </h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Total due:
                        <strong style="color: var(--warning);">
                            {{ $currencySymbol }} {{ number_format($invoiceSummary['outstanding'], 2) }}
                        </strong>
                        @if($invoiceSummary['overdue_count'] > 0)
                            · {{ $invoiceSummary['overdue_count'] }} overdue invoice(s)
                        @endif
                    </p>
                    @if(Route::has('tenant.property-units.financials'))
                    <a href="{{ route('tenant.property-units.financials', $unit->id) }}"
                       class="btn-primary mt-3 inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium text-white">
                        <i class="fas fa-money-bill-wave mr-2"></i> View Financials
                    </a>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- ============================================================
             ✅ GHANA: ADVANCE RENT PANEL
             ============================================================ --}}
        @if($hasAdvanceRent && $currentLease)
        <div class="card border-l-4" style="border-left-color: var(--primary);">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-hand-holding-usd mr-2" style="color: var(--primary);"></i>
                        Advance Rent & Payment Phase
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full badge-primary">Ghana</span>
                    </h3>
                    @if($isCompliant)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full badge-success">
                            <i class="fas fa-check-circle mr-1"></i> Compliant
                        </span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold rounded-full badge-warning"
                              title="Exceeds {{ $legalMaxNew }}-month cap under {{ $governingLaw }}">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Exceeds Cap
                        </span>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                    <div class="p-3 rounded-lg text-center"
                         style="background-color: {{ $isInAdvancePhase ? 'rgba(var(--primary-rgb), 0.1)' : 'var(--card-bg)' }};
                                border: 1px solid {{ $isInAdvancePhase ? 'var(--primary)' : 'var(--border-color)' }};">
                        <div class="text-xs" style="color: var(--text-secondary);">Advance Phase</div>
                        <div class="text-sm font-semibold mt-1"
                             style="color: {{ $isInAdvancePhase ? 'var(--primary)' : 'var(--text-primary)' }};">
                            @if($isInAdvancePhase)
                                {{ $advanceMonthsLeft }} mo remaining
                            @else
                                Completed
                            @endif
                        </div>
                    </div>

                    <div class="p-3 rounded-lg text-center"
                         style="background-color: {{ $isInMonthlyPhase ? 'rgba(var(--success-rgb), 0.1)' : 'var(--card-bg)' }};
                                border: 1px solid {{ $isInMonthlyPhase ? 'var(--success)' : 'var(--border-color)' }};">
                        <div class="text-xs" style="color: var(--text-secondary);">Monthly Phase</div>
                        <div class="text-sm font-semibold mt-1"
                             style="color: {{ $isInMonthlyPhase ? 'var(--success)' : 'var(--text-primary)' }};">
                            @if($isInMonthlyPhase)
                                Active
                            @elseif($currentLease->payment_frequency === 'advance_only')
                                N/A (Full Advance)
                            @else
                                Pending
                            @endif
                        </div>
                    </div>

                    <div class="p-3 rounded-lg text-center"
                         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div class="text-xs" style="color: var(--text-secondary);">Total Advance Paid</div>
                        <div class="text-sm font-bold mt-1" style="color: var(--primary);">
                            {{ $currencySymbol }} {{ number_format($advanceAmount, 2) }}
                        </div>
                    </div>
                </div>

                @if($nextPaymentDate)
                    <div class="p-3 rounded-lg text-xs"
                         style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                        <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                        <strong>Next payment:</strong>
                        {{ $currencySymbol }} {{ number_format($nextPaymentAmt, 2) }}
                        due {{ $nextPaymentDate->format('F j, Y') }}
                    </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ============================================================
             UNIT INFORMATION + LEASE CARD (2-column)
             ============================================================ --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Unit Information -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2"></i> Unit Information
                    </h3>

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    Unit Type
                                </label>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-info">
                                    <i class="fas fa-tag mr-1"></i>
                                    {{ ucfirst(str_replace('_', ' ', $unit->unit_type)) }}
                                </span>
                            </div>

                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    Status
                                </label>
                                @php
                                    $unitStatusMeta = match($unit->status) {
                                        'available'         => ['badge-success',   'check-circle', 'Available'],
                                        'occupied'          => ['badge-primary',   'user',         'Occupied'],
                                        'under_maintenance' => ['badge-warning',   'tools',        'Under Maintenance'],
                                        'reserved'          => ['badge-info',      'clock',        'Reserved'],
                                        'unavailable'       => ['badge-secondary', 'ban',          'Unavailable'],
                                        default             => ['badge-secondary', 'circle',       ucfirst($unit->status)],
                                    };
                                @endphp
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $unitStatusMeta[0] }}">
                                    <i class="fas fa-{{ $unitStatusMeta[1] }} mr-1"></i>
                                    {{ $unitStatusMeta[2] }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    Furnished
                                </label>
                                @if($unit->is_furnished)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-success">
                                        <i class="fas fa-couch mr-1"></i> Yes
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium badge-secondary">
                                        <i class="fas fa-couch mr-1"></i> No
                                    </span>
                                @endif
                            </div>

                            @if($unit->floor_area)
                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    Floor Area
                                </label>
                                <p class="text-lg font-semibold" style="color: var(--text-primary);">
                                    {{ $unit->floor_area }} sq ft
                                </p>
                            </div>
                            @endif
                        </div>

                        <!-- Specifications -->
                        <div class="grid grid-cols-2 gap-3 pt-2">
                            @foreach([
                                ['icon' => 'fa-bed',    'label' => 'Bedrooms',    'value' => $unit->bedrooms,    'color' => 'primary'],
                                ['icon' => 'fa-bath',   'label' => 'Bathrooms',   'value' => $unit->bathrooms,   'color' => 'success'],
                                ['icon' => 'fa-couch',  'label' => 'Living Rooms','value' => $unit->living_rooms,'color' => 'warning'],
                                ['icon' => 'fa-utensils','label' => 'Kitchens',   'value' => $unit->kitchens,    'color' => 'info'],
                            ] as $spec)
                                @if(($spec['value'] ?? 0) > 0)
                                <div class="p-3 rounded-lg text-center"
                                     style="background-color: rgba(var(--{{ $spec['color'] }}-rgb), 0.05);
                                            border: 1px solid rgba(var(--{{ $spec['color'] }}-rgb), 0.1);">
                                    <p class="text-2xl font-bold mb-1" style="color: var(--{{ $spec['color'] }});">
                                        {{ $spec['value'] }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas {{ $spec['icon'] }} mr-1"></i> {{ $spec['label'] }}
                                    </p>
                                </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lease Information -->
            @if($currentLease)
            @php $leaseMeta = $leaseStatusMeta[$currentLease->status] ?? $leaseStatusMeta['draft']; @endphp
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-file-contract mr-2"></i> Lease Agreement
                    </h3>

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    Start Date
                                </label>
                                <p class="font-semibold" style="color: var(--text-primary);">
                                    {{ $currentLease->start_date->format('M d, Y') }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                    End Date
                                </label>
                                <p class="font-semibold" style="color: var(--text-primary);">
                                    @if($currentLease->end_date)
                                        {{ $currentLease->end_date->format('M d, Y') }}
                                    @else
                                        Month-to-Month
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium mb-1 uppercase tracking-wider" style="color: var(--text-secondary);">
                                Status
                            </label>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $leaseMeta[0] }}">
                                <i class="fas fa-{{ $leaseMeta[1] }} mr-1"></i> {{ $leaseMeta[2] }}
                            </span>
                        </div>

                        {{-- Signatures --}}
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <div class="flex flex-wrap gap-4">
                                <span class="inline-flex items-center text-sm"
                                      style="color: {{ $currentLease->landlord_signed_at ? 'var(--success)' : 'var(--text-secondary)' }};">
                                    <i class="fas fa-{{ $currentLease->landlord_signed_at ? 'check-circle' : 'clock' }} mr-1"></i>
                                    Landlord {{ $currentLease->landlord_signed_at ? 'Signed' : 'Pending' }}
                                </span>
                                <span class="inline-flex items-center text-sm"
                                      style="color: {{ $currentLease->tenant_signed_at ? 'var(--success)' : 'var(--warning)' }};">
                                    <i class="fas fa-{{ $currentLease->tenant_signed_at ? 'check-circle' : 'clock' }} mr-1"></i>
                                    You {{ $currentLease->tenant_signed_at ? 'Signed' : 'Pending' }}
                                </span>
                            </div>

                            {{-- ✅ FIXED: Sign link routes to actual lease-details page --}}
                            @if(!$currentLease->tenant_signed_at && Route::has('tenant.property-units.lease-details'))
                            <a href="{{ route('tenant.property-units.lease-details', [$unit->id, $currentLease->id]) }}?sign=true"
                               class="btn-primary mt-3 w-full px-4 py-2 rounded-lg inline-flex items-center justify-center text-sm font-medium text-white">
                                <i class="fas fa-signature mr-2"></i> Sign Lease Agreement
                            </a>
                            @endif
                        </div>

                        {{-- Lease actions --}}
                        <div class="pt-4 border-t flex gap-2" style="border-color: var(--border-color);">
                            @if(Route::has('tenant.property-units.lease-details'))
                            <a href="{{ route('tenant.property-units.lease-details', [$unit->id, $currentLease->id]) }}"
                               class="btn-primary flex-1 px-3 py-2 rounded-lg inline-flex items-center justify-center text-sm font-medium text-white">
                                <i class="fas fa-eye mr-2"></i> View Lease
                            </a>
                            @endif
                            @if(Route::has('tenant.property-units.lease-download-pdf'))
                            <a href="{{ route('tenant.property-units.lease-download-pdf', [$unit->id, $currentLease->id]) }}"
                               class="btn-secondary flex-1 px-3 py-2 rounded-lg inline-flex items-center justify-center text-sm font-medium">
                                <i class="fas fa-download mr-2"></i> PDF
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- ============================================================
             DESCRIPTION
             ============================================================ --}}
        @if($unit->description)
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-align-left mr-2"></i> Description
                </h3>
                <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">{{ $unit->description }}</p>
            </div>
        </div>
        @endif

        {{-- ============================================================
             AMENITIES
             ============================================================ --}}
        @if(!empty($unit->amenities))
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-concierge-bell mr-2"></i> Amenities
                </h3>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach($unit->amenities as $amenity)
                        @php
                            [$icon, $label] = $amenityMeta[$amenity]
                                ?? ['fa-check', ucfirst(str_replace('_', ' ', $amenity))];
                        @endphp
                        <div class="p-3 rounded-lg flex items-center"
                             style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                            <i class="fas {{ $icon }} text-sm mr-2" style="color: var(--success);"></i>
                            <span class="text-sm" style="color: var(--text-primary);">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- ============================================================
             ✅ INVOICE: RECENT INVOICES
             ============================================================ --}}
        @if($recentInvoices->isNotEmpty())
        <div class="card">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-file-invoice mr-2"></i> Recent Invoices
                    </h3>
                    @if(Route::has('tenant.property-units.financials'))
                    <a href="{{ route('tenant.property-units.financials', $unit->id) }}"
                       class="text-sm font-medium hover:underline" style="color: var(--primary);">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                    @endif
                </div>

                <div class="space-y-3">
                    @foreach($recentInvoices as $invoice)
                        @php
                            $invMeta = $invoiceStatusMeta[$invoice->status] ?? $invoiceStatusMeta[PropertyUnitInvoice::STATUS_PENDING];
                            $balance = max(0, (float) $invoice->amount - (float) $invoice->amount_paid);
                        @endphp
                        <div class="flex items-center justify-between p-3 rounded-lg"
                             style="background-color: rgba(0,0,0,0.02); border: 1px solid var(--border-color);">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--{{ str_replace('badge-', '', $invMeta['class']) }}-rgb), 0.1);
                                            color: var(--{{ str_replace('badge-', '', $invMeta['class']) }});">
                                    <i class="fas fa-{{ $invMeta['icon'] }}"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-sm font-mono" style="color: var(--text-primary);">
                                        {{ $invoice->reference }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        {{ $invoice->description }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        {{ ucfirst(str_replace('_', ' ', $invoice->invoice_type)) }}
                                        · {{ optional($invoice->due_date)->format('M d, Y') ?? '—' }}
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-bold" style="color: var(--text-primary);">
                                    {{ $currencySymbol }} {{ number_format($invoice->amount, 2) }}
                                </p>
                                @if($balance > 0 && $invoice->amount_paid > 0)
                                    <p class="text-xs" style="color: var(--warning);">
                                        {{ $currencySymbol }} {{ number_format($balance, 2) }} due
                                    </p>
                                @endif
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $invMeta['class'] }}">
                                    {{ $invMeta['label'] }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- ============================================================
         SIDEBAR
         ============================================================ --}}
    <div class="space-y-6">
        <!-- Landlord -->
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-user-tie mr-2"></i> Landlord
                </h3>

                @if($unit->property->landlord)
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0 mr-3">
                        @if($unit->property->landlord->profile_photo)
                            <div class="w-12 h-12 rounded-full overflow-hidden border-2" style="border-color: var(--primary);">
                                <img src="{{ Storage::url($unit->property->landlord->profile_photo) }}"
                                     alt="{{ $unit->property->landlord->name }}"
                                     class="w-full h-full object-cover">
                            </div>
                        @else
                            <div class="w-12 h-12 rounded-full flex items-center justify-center avatar">
                                <span class="text-white text-lg font-bold">
                                    {{ strtoupper(substr($unit->property->landlord->name, 0, 1)) }}
                                </span>
                            </div>
                        @endif
                    </div>
                    <div>
                        <p class="font-semibold" style="color: var(--text-primary);">
                            {{ $unit->property->landlord->name }}
                        </p>
                        <p class="text-xs" style="color: var(--text-secondary);">Property Owner</p>
                    </div>
                </div>

                <div class="space-y-2">
                    @if($unit->property->landlord->phone)
                    <div class="flex items-center text-sm">
                        <i class="fas fa-phone mr-2" style="color: var(--text-secondary);"></i>
                        <a href="tel:{{ $unit->property->landlord->phone }}"
                           class="hover:underline" style="color: var(--primary);">
                            {{ $unit->property->landlord->phone }}
                        </a>
                    </div>
                    @endif
                    @if($unit->property->landlord->email)
                    <div class="flex items-center text-sm">
                        <i class="fas fa-envelope mr-2" style="color: var(--text-secondary);"></i>
                        <a href="mailto:{{ $unit->property->landlord->email }}"
                           class="hover:underline" style="color: var(--primary);">
                            {{ $unit->property->landlord->email }}
                        </a>
                    </div>
                    @endif
                </div>

                <a href="mailto:{{ $unit->property->landlord->email }}"
                   class="mt-4 w-full px-4 py-2 rounded-lg inline-flex items-center justify-center text-sm font-medium btn-primary">
                    <i class="fas fa-envelope mr-2"></i> Send Message
                </a>
                @else
                <p class="text-sm" style="color: var(--text-secondary);">No landlord information available.</p>
                @endif
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2"></i> Quick Actions
                </h3>

                <div class="space-y-2">
                    @php
                        $actions = [];

                        if (Route::has('tenant.property-units.financials')) {
                            $actions[] = [
                                'href' => route('tenant.property-units.financials', $unit->id),
                                'icon' => 'fa-file-invoice',
                                'label' => 'View All Invoices',
                                'color' => 'primary',
                            ];
                        }

                        if (Route::has('tenant.property-units.maintenance-requests')) {
                            $actions[] = [
                                'href' => route('tenant.property-units.maintenance-requests', $unit->id),
                                'icon' => 'fa-tools',
                                'label' => 'Report Issue',
                                'color' => 'warning',
                            ];
                        } elseif (Route::has('tenant.maintenance.index')) {
                            $actions[] = [
                                'href' => route('tenant.maintenance.index'),
                                'icon' => 'fa-tools',
                                'label' => 'Report Issue',
                                'color' => 'warning',
                            ];
                        }

                        if ($currentLease && !$currentLease->tenant_signed_at && Route::has('tenant.property-units.lease-details')) {
                            $actions[] = [
                                'href' => route('tenant.property-units.lease-details', [$unit->id, $currentLease->id]) . '?sign=true',
                                'icon' => 'fa-file-signature',
                                'label' => 'Sign Lease',
                                'color' => 'info',
                            ];
                        }
                    @endphp

                    @foreach($actions as $action)
                    <a href="{{ $action['href'] }}"
                       class="w-full px-3 py-3 rounded-lg inline-flex items-center text-sm font-medium transition-all duration-200"
                       style="background-color: rgba(var(--{{ $action['color'] }}-rgb), 0.1);
                              color: var(--{{ $action['color'] }});
                              border: 1px solid rgba(var(--{{ $action['color'] }}-rgb), 0.2);">
                        <i class="fas {{ $action['icon'] }} mr-3"></i>
                        <span class="flex-1 text-left">{{ $action['label'] }}</span>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Property Details -->
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-building mr-2"></i> Property Details
                </h3>

                <div class="space-y-3">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">
                            Property Name
                        </p>
                        <p class="font-semibold" style="color: var(--text-primary);">
                            {{ $unit->property->property_name }}
                        </p>
                    </div>

                    @if($unit->property->address)
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">
                            Address
                        </p>
                        <p class="text-sm" style="color: var(--text-primary);">
                            {{ $unit->property->address }}
                            @if($unit->property->city)
                                <br>{{ $unit->property->city }}
                            @endif
                        </p>
                    </div>
                    @endif

                    @if($unit->property->property_type ?? null)
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">
                            Property Type
                        </p>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs badge-info">
                            {{ ucfirst($unit->property->property_type) }}
                        </span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Important Notes -->
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-exclamation-circle mr-2" style="color: var(--warning);"></i> Important Notes
                </h3>

                <ul class="space-y-3 text-sm" style="color: var(--text-secondary);">
                    <li class="flex items-start">
                        <i class="fas fa-circle text-xs mt-1 mr-2" style="color: var(--warning);"></i>
                        <span>Report maintenance issues promptly to avoid further damage</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-circle text-xs mt-1 mr-2" style="color: var(--warning);"></i>
                        <span>
                            Rent is due on the
                            {{ $currentLease?->payment_due_day ?? 5 }}
                            {{-- ✅ FIXED: was hardcoded "5th" --}}
                            @if($currentLease?->payment_due_day)
                                {{ $currentLease->payment_due_day == 1 ? 'st' : ($currentLease->payment_due_day == 2 ? 'nd' : ($currentLease->payment_due_day == 3 ? 'rd' : 'th')) }}
                            @endif
                            of each month
                        </span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-circle text-xs mt-1 mr-2" style="color: var(--warning);"></i>
                        <span>For emergencies, contact landlord immediately</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-circle text-xs mt-1 mr-2" style="color: var(--warning);"></i>
                        <span>All terms governed by the {{ $governingLaw }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(() => {
        document.querySelectorAll('.bg-green-100, .bg-red-100, .bg-blue-100')
            .forEach(msg => msg.style.display = 'none');
    }, 5000);
});
</script>
@endsection

@section('styles')
<style>
.card {
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    transition: transform 0.3s, box-shadow 0.3s;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.badge-success   { background-color: rgba(var(--success-rgb), 0.1) !important;   color: var(--success) !important;   border: 1px solid rgba(var(--success-rgb), 0.2); }
.badge-danger    { background-color: rgba(var(--danger-rgb), 0.1) !important;    color: var(--danger) !important;    border: 1px solid rgba(var(--danger-rgb), 0.2); }
.badge-warning   { background-color: rgba(var(--warning-rgb), 0.1) !important;   color: var(--warning) !important;   border: 1px solid rgba(var(--warning-rgb), 0.2); }
.badge-info      { background-color: rgba(var(--info-rgb), 0.1) !important;      color: var(--info) !important;      border: 1px solid rgba(var(--info-rgb), 0.2); }
.badge-primary   { background-color: rgba(var(--primary-rgb), 0.1) !important;   color: var(--primary) !important;   border: 1px solid rgba(var(--primary-rgb), 0.2); }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.2); }

.gradient-bg {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
}

.avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: white;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    box-shadow: 0 4px 8px rgba(var(--primary-rgb), 0.3);
}

.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: none;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background-color: rgba(var(--secondary-rgb), 0.2) !important;
    transform: translateY(-2px);
}

.bg-green-100 { background-color: rgba(209, 250, 229, 0.9) !important; border-color: rgba(16, 185, 129, 0.3) !important; }
.bg-red-100   { background-color: rgba(254, 226, 226, 0.9) !important; border-color: rgba(239, 68, 68, 0.3) !important; }

@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-4 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.lg\:grid-cols-2 { grid-template-columns: 1fr; }
    .grid.grid-cols-2.md\:grid-cols-4 { grid-template-columns: repeat(2, 1fr); }
    .grid.grid-cols-2.md\:grid-cols-3 { grid-template-columns: repeat(2, 1fr); }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}

.card { animation: fadeIn 0.3s ease-out; }

a, button { transition: all 0.3s ease; }
</style>
@endsection