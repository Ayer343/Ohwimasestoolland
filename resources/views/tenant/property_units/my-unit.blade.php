{{-- tenant/property_units/my-unit.blade.php --}}
@php
    $layout = 'layouts.tenant';
    $user   = auth()->user();

    // ✅ FIXED: role fallback
    $isTenant = $user->isTenant() || $user->hasRole('tenant');

    // ✅ FIXED: derive cache TTL from config so ops can tune it
    $cacheTtl = (int) config('cache.ttl', 3600);

    // ✅ GHANA: config snapshots (single source of truth)
    $currencySymbol = config('leases.ghana.currency.symbol', 'GH₵');
    $governingLaw   = config('leases.ghana.governing_law', 'Rent Act, 1963 (Act 220)');
    $legalMaxNew    = (int) config('leases.ghana.legal_max_advance_months.new_tenancy', 6);

    // ========== UNIT (cached) ==========
    $cacheKey = 'tenant_unit_' . $user->id;
    $unit = Cache::remember($cacheKey, $cacheTtl, function () use ($user) {
        return \App\Models\PropertyUnit::where('tenant_id', $user->id)
            ->where('tenant_status', \App\Models\PropertyUnit::TENANT_STATUS_APPROVED)
            ->with([
                'property.landlord',
                'currentLease.invoices',
                'currentLease.tenant',
                'currentLease.landlord',
            ])
            ->latest('tenant_assigned_at')
            ->first();
    });

    // ========== PAGE TITLE ==========
    $pageTitle = 'My Unit';
    if ($unit) {
        $pageTitle .= ' - ' . $unit->property->property_name . ' - Unit ' . $unit->unit_number;
    }

    // ========== LEASE CONTEXT ==========
    $currentLease = $unit?->currentLease;
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
    $invoices = $currentLease?->invoices ?? collect();
    $invoiceSummary = [
        'total_invoiced' => (float) $invoices->sum('amount'),
        'total_paid'     => (float) $invoices->sum('amount_paid'),
        'outstanding'    => (float) $invoices
            ->whereIn('status', [
                \App\Models\PropertyUnitInvoice::STATUS_PENDING,
                \App\Models\PropertyUnitInvoice::STATUS_PARTIAL,
                \App\Models\PropertyUnitInvoice::STATUS_OVERDUE,
            ])
            ->sum(fn ($i) => max(0, $i->amount - $i->amount_paid)),
        'overdue_count'  => $invoices->where('status', \App\Models\PropertyUnitInvoice::STATUS_OVERDUE)->count(),
    ];

    // ========== EMERGENCY CONTACT (safe parse) ==========
    $emergency = $user->emergency_contact ?? null;
    if (is_string($emergency)) {
        $decoded = json_decode($emergency, true);
        $emergency = is_array($decoded) ? $decoded : null;
    }
    $emergencyName         = is_array($emergency) ? ($emergency['name'] ?? null) : null;
    $emergencyPhone        = is_array($emergency) ? ($emergency['phone'] ?? null) : null;
    $emergencyRelationship = is_array($emergency) ? ($emergency['relationship'] ?? null) : null;

    // ========== RECENT ACTIVITY (cached) ==========
    $recentActivities = collect();
    if ($unit) {
        $activityKey = 'tenant_unit_activities_' . $unit->id;
        $recentActivities = Cache::remember($activityKey, 300, function () use ($user, $unit) {
            return \App\Models\ActivityLog::where(function ($q) use ($user, $unit) {
                    $q->where('user_id', $user->id)
                      ->orWhere(function ($q2) use ($unit) {
                          $q2->where('unit_id', $unit->id)
                             ->whereIn('type', [
                                 'maintenance_request_created',
                                 'invoice_generated',
                                 'lease_created',
                                 'tenant_approved',
                                 'tenant_vacated',
                             ]);
                      });
                })
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        });
    }
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-home mr-2" style="color: var(--primary);"></i> My Unit
                </h2>

                {{-- ✅ GHANA: phase badge --}}
                @if($currentLease && $hasAdvanceRent && !request()->routeIs('tenant.maintenance.*'))
                    @if($isInAdvancePhase)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full badge-primary">
                            <i class="fas fa-calendar-check mr-1"></i> Advance Phase
                            @if($advanceMonthsLeft > 0)
                                ({{ $advanceMonthsLeft }} mo left)
                            @endif
                        </span>
                    @elseif($isInMonthlyPhase)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full badge-success">
                            <i class="fas fa-calendar-day mr-1"></i> Monthly Phase
                        </span>
                    @endif
                @endif
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i>
                View and manage your assigned property unit
            </div>
        </div>
    </div>

    @if(!$unit)
        <!-- No Unit Assigned State -->
        <div class="card">
            <div class="p-6 text-center">
                <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                     style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-home text-2xl" style="color: var(--warning);"></i>
                </div>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Unit Assigned</h4>
                <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                    You don't have a unit assigned to you yet. Please contact your landlord
                    or administrator to get assigned to a unit.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    @if(Route::has('tenant.contact-landlord'))
                    <a href="{{ route('tenant.contact-landlord') }}"
                       class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                        <i class="fas fa-envelope mr-2"></i> Contact Landlord
                    </a>
                    @endif
                    @if(Route::has('tenant.profile.show'))
                    <a href="{{ route('tenant.profile.show') }}"
                       class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-user mr-2"></i> Update Profile
                    </a>
                    @endif
                </div>
            </div>
        </div>
    @else
        <!-- Session Messages -->
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

        <!-- ========== OVERVIEW CARDS ========== -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Rent -->
            <div class="card p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                        <i class="fas fa-money-bill-wave text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Monthly Rent</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">
                            {{ $currencySymbol }} {{ number_format($unit->current_rent_amount ?? $unit->monthly_rent, 2) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Lease -->
            <div class="card p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                        <i class="fas fa-file-contract text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Lease Status</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">
                            @if($currentLease)
                                @php
                                    $leaseStatusColor = match($currentLease->status) {
                                        'active'            => 'text-green-600',
                                        'pending_signature' => 'text-yellow-600',
                                        'expired'           => 'text-red-600',
                                        default             => 'text-gray-600',
                                    };
                                @endphp
                                <span class="{{ $leaseStatusColor }} capitalize">
                                    {{ str_replace('_', ' ', $currentLease->status) }}
                                </span>
                            @else
                                <span class="text-yellow-600">No Lease</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <!-- Property -->
            <div class="card p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                        <i class="fas fa-building text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Property</p>
                        <p class="text-2xl font-bold truncate" style="color: var(--text-primary);"
                           title="{{ $unit->property->property_name }}">
                            {{ \Illuminate\Support\Str::limit($unit->property->property_name, 15) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- ✅ GHANA: Next Payment / Move-in hybrid card -->
            <div class="card p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-orange-100 text-orange-600 mr-4">
                        <i class="fas fa-calendar-alt text-lg"></i>
                    </div>
                    <div>
                        @if($currentLease && $hasAdvanceRent && $isInAdvancePhase)
                            <p class="text-sm font-medium" style="color: var(--text-secondary);">Advance Phase</p>
                            <p class="text-2xl font-bold" style="color: var(--primary);">
                                {{ $advanceMonthsLeft }} mo left
                            </p>
                        @elseif($nextPaymentDate)
                            <p class="text-sm font-medium" style="color: var(--text-secondary);">Next Payment</p>
                            <p class="text-2xl font-bold" style="color: var(--text-primary);">
                                {{ $nextPaymentDate->format('M d') }}
                            </p>
                        @elseif($unit->tenant_move_in_date)
                            <p class="text-sm font-medium" style="color: var(--text-secondary);">Move In Date</p>
                            <p class="text-2xl font-bold" style="color: var(--text-primary);">
                                {{ $unit->tenant_move_in_date->format('M d') }}
                            </p>
                        @else
                            <p class="text-sm font-medium" style="color: var(--text-secondary);">Move In Date</p>
                            <p class="text-2xl font-bold" style="color: var(--text-secondary);">N/A</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

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
                        <div class="text-xs" style="color: var(--text-secondary);">Phase 1 — Advance</div>
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
                        <div class="text-xs" style="color: var(--text-secondary);">Phase 2 — Monthly</div>
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
             ✅ INVOICE: OUTSTANDING BALANCE ALERT
             ============================================================ --}}
        @if($invoiceSummary['outstanding'] > 0)
        <div class="card border-l-4" style="border-left-color: var(--warning); background-color: rgba(var(--warning-rgb), 0.03);">
            <div class="p-5 flex items-start gap-3">
                <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--warning);"></i>
                <div class="flex-1">
                    <h4 class="font-semibold" style="color: var(--text-primary);">
                        You have an outstanding balance
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

        <!-- ========== UNIT DETAILS SECTION ========== -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Unit Information -->
            <div class="lg:col-span-2">
                <div class="card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-home mr-2"></i> Unit Information
                        </h3>
                        @if(Route::has('tenant.property-units.show'))
                        <a href="{{ route('tenant.property-units.show', $unit->id) }}"
                           class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center text-sm">
                            <i class="fas fa-external-link-alt mr-2"></i> View Full Details
                        </a>
                        @endif
                    </div>

                    <div class="space-y-4">
                        <!-- Unit Header -->
                        <div class="flex items-start space-x-4 p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                            <div class="flex-shrink-0">
                                @php
                                    $unitIcon = match($unit->unit_type) {
                                        'apartment'  => 'fa-building',
                                        'house'      => 'fa-home',
                                        'studio'     => 'fa-cube',
                                        'commercial' => 'fa-store',
                                        default      => 'fa-home',
                                    };
                                    $unitColor = match($unit->status) {
                                        'available'          => 'success',
                                        'occupied'           => 'primary',
                                        'under_maintenance'  => 'warning',
                                        'vacant'             => 'secondary',
                                        default              => 'info',
                                    };
                                @endphp
                                <div class="w-16 h-16 rounded-lg flex items-center justify-center"
                                     style="background-color: rgba(var(--{{ $unitColor }}-rgb), 0.1); color: var(--{{ $unitColor }});">
                                    <i class="fas {{ $unitIcon }} text-2xl"></i>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xl font-bold mb-1" style="color: var(--text-primary);">
                                    Unit {{ $unit->unit_number }}
                                    @if($unit->unit_name)
                                        <span class="text-base font-normal" style="color: var(--text-secondary);">- {{ $unit->unit_name }}</span>
                                    @endif
                                </h4>
                                <div class="flex flex-wrap gap-2 mb-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs badge-{{ $unitColor }}">
                                        <i class="fas fa-circle mr-1 text-xs"></i>
                                        {{ ucfirst(str_replace('_', ' ', $unit->status)) }}
                                    </span>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs badge-secondary">
                                        <i class="fas fa-tag mr-1"></i> {{ $unit->unit_type }}
                                    </span>
                                    @if($unit->is_furnished)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs badge-success">
                                            <i class="fas fa-couch mr-1"></i> Furnished
                                        </span>
                                    @endif
                                </div>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    {{ $unit->description ?? 'No description available.' }}
                                </p>
                            </div>
                        </div>

                        <!-- Specifications -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            @foreach([
                                ['icon' => 'fa-bed',           'label' => 'Bedrooms',     'value' => $unit->bedrooms],
                                ['icon' => 'fa-bath',          'label' => 'Bathrooms',    'value' => $unit->bathrooms],
                                ['icon' => 'fa-couch',         'label' => 'Living Rooms', 'value' => $unit->living_rooms],
                                ['icon' => 'fa-ruler-combined','label' => 'Floor Area',   'value' => $unit->floor_area ? $unit->floor_area . ' sqft' : null],
                            ] as $spec)
                                @if($spec['value'])
                                <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--bg-secondary-rgb, 240, 240, 240), 0.5);">
                                    <i class="fas {{ $spec['icon'] }} text-xl mb-2" style="color: var(--primary);"></i>
                                    <div class="text-sm" style="color: var(--text-secondary);">{{ $spec['label'] }}</div>
                                    <div class="font-bold text-lg" style="color: var(--text-primary);">{{ $spec['value'] }}</div>
                                </div>
                                @endif
                            @endforeach
                        </div>

                        <!-- Amenities -->
                        @if(!empty($unit->amenities))
                        <div>
                            <h5 class="font-medium mb-2 flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-star mr-2"></i> Amenities
                            </h5>
                            <div class="flex flex-wrap gap-2">
                                @php
                                    $amenityMeta = [
                                        'parking'            => ['fa-parking',           'Parking Space'],
                                        'balcony'            => ['fa-umbrella-beach',    'Balcony'],
                                        'air_conditioning'   => ['fa-snowflake',         'Air Conditioning'],
                                        'furnished'          => ['fa-couch',             'Furnished'],
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
                                @endphp
                                @foreach($unit->amenities as $amenity)
                                    @php
                                        [$icon, $label] = $amenityMeta[$amenity]
                                            ?? ['fa-check', ucfirst(str_replace('_', ' ', $amenity))];
                                    @endphp
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm badge-info">
                                        <i class="fas {{ $icon }} mr-2"></i> {{ $label }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Property & Contacts -->
            <div>
                <div class="card p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-building mr-2"></i> Property & Contacts
                    </h3>

                    <div class="space-y-4">
                        <!-- Property -->
                        <div>
                            <h5 class="font-medium mb-2" style="color: var(--text-primary);">
                                {{ $unit->property->property_name }}
                            </h5>
                            <div class="space-y-1 text-sm" style="color: var(--text-secondary);">
                                @if($unit->property->address)
                                <div class="flex items-start">
                                    <i class="fas fa-map-marker-alt mr-2 mt-0.5"></i>
                                    <span>{{ $unit->property->address }}</span>
                                </div>
                                @endif
                                @if($unit->property->city)
                                <div class="flex items-center">
                                    <i class="fas fa-city mr-2"></i>
                                    <span>{{ $unit->property->city }}</span>
                                </div>
                                @endif
                                @if($unit->property->zone)
                                <div class="flex items-center">
                                    <i class="fas fa-map mr-2"></i>
                                    <span>{{ $unit->property->zone }}</span>
                                </div>
                                @endif
                            </div>
                        </div>

                        <!-- Landlord -->
                        @if($unit->property->landlord)
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h5 class="font-medium mb-3 flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-user-tie mr-2"></i> Landlord Contact
                            </h5>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2 avatar-sm"
                                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <span style="color: var(--text-primary);">{{ $unit->property->landlord->name }}</span>
                                </div>
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
                        </div>
                        @endif

                        <!-- Emergency contact -->
                        @if($emergencyName || $emergencyPhone)
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h5 class="font-medium mb-3 flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-ambulance mr-2"></i> Your Emergency Contact
                            </h5>
                            <div class="space-y-2 text-sm">
                                @if($emergencyName)
                                <div class="flex items-center">
                                    <i class="fas fa-user mr-2" style="color: var(--text-secondary);"></i>
                                    <span style="color: var(--text-primary);">{{ $emergencyName }}</span>
                                </div>
                                @endif
                                @if($emergencyPhone)
                                <div class="flex items-center">
                                    <i class="fas fa-phone mr-2" style="color: var(--text-secondary);"></i>
                                    <span style="color: var(--primary);">{{ $emergencyPhone }}</span>
                                </div>
                                @endif
                                @if($emergencyRelationship)
                                <div class="flex items-center">
                                    <i class="fas fa-users mr-2" style="color: var(--text-secondary);"></i>
                                    <span style="color: var(--text-secondary);">{{ ucfirst($emergencyRelationship) }}</span>
                                </div>
                                @endif
                            </div>
                            @if(Route::has('tenant.profile.edit'))
                            <a href="{{ route('tenant.profile.edit') }}" class="text-xs text-primary hover:underline mt-2 inline-block">
                                <i class="fas fa-edit mr-1"></i> Update Contact
                            </a>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== QUICK ACTIONS & LEASE INFO ========== -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Quick Actions -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2"></i> Quick Actions
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @php
                        $actions = [];

                        // Maintenance
                        if (Route::has('tenant.property-units.maintenance-requests')) {
                            $actions[] = [
                                'href' => route('tenant.property-units.maintenance-requests', $unit->id),
                                'icon' => 'fa-tools',
                                'label' => 'Maintenance',
                                'sub' => 'Request repairs',
                                'color' => 'warning',
                            ];
                        } elseif (Route::has('tenant.maintenance.index')) {
                            $actions[] = [
                                'href' => route('tenant.maintenance.index'),
                                'icon' => 'fa-tools',
                                'label' => 'Maintenance',
                                'sub' => 'Request repairs',
                                'color' => 'warning',
                            ];
                        }

                        // Financials
                        if (Route::has('tenant.property-units.financials')) {
                            $actions[] = [
                                'href' => route('tenant.property-units.financials', $unit->id),
                                'icon' => 'fa-money-bill-wave',
                                'label' => 'Financials',
                                'sub' => 'View payments & invoices',
                                'color' => 'success',
                            ];
                        }

                        // Lease
                        if ($currentLease && Route::has('tenant.property-units.lease-details')) {
                            $actions[] = [
                                'href' => route('tenant.property-units.lease-details', [$unit->id, $currentLease->id]),
                                'icon' => 'fa-file-contract',
                                'label' => 'Lease Agreement',
                                'sub' => 'View & download',
                                'color' => 'info',
                            ];
                        }

                        // Documents (guarded — may not exist)
                        if (Route::has('tenant.property-units.documents')) {
                            $actions[] = [
                                'href' => route('tenant.property-units.documents', $unit->id),
                                'icon' => 'fa-file-alt',
                                'label' => 'Documents',
                                'sub' => 'Important files',
                                'color' => 'secondary',
                            ];
                        }
                    @endphp

                    @foreach($actions as $action)
                    <a href="{{ $action['href'] }}"
                       class="p-4 rounded-lg flex items-center space-x-3 transition-colors group quick-action-card"
                       style="background-color: rgba(var(--{{ $action['color'] }}-rgb), 0.05);
                              border: 1px solid rgba(var(--{{ $action['color'] }}-rgb), 0.2);">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--{{ $action['color'] }}-rgb), 0.1); color: var(--{{ $action['color'] }});">
                            <i class="fas {{ $action['icon'] }}"></i>
                        </div>
                        <div>
                            <h4 class="font-medium group-hover:underline" style="color: var(--text-primary);">
                                {{ $action['label'] }}
                            </h4>
                            <p class="text-xs" style="color: var(--text-secondary);">{{ $action['sub'] }}</p>
                        </div>
                        <div class="ml-auto">
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </div>
                    </a>
                    @endforeach
                </div>

                <!-- Important Notes -->
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <h4 class="font-medium mb-3 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-exclamation-circle mr-2" style="color: var(--warning);"></i> Important Notes
                    </h4>
                    <ul class="space-y-2 text-sm" style="color: var(--text-secondary);">
                        <li class="flex items-start">
                            <i class="fas fa-circle text-xs mt-1 mr-2" style="color: var(--warning);"></i>
                            <span>Always report maintenance issues promptly</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-circle text-xs mt-1 mr-2" style="color: var(--warning);"></i>
                            <span>
                                Rent is due on the
                                {{ $currentLease?->payment_due_day ?? '1st' }}
                                @if($currentLease?->payment_due_day)
                                    {{ $currentLease->payment_due_day == 1 ? 'st' : ($currentLease->payment_due_day == 2 ? 'nd' : ($currentLease->payment_due_day == 3 ? 'rd' : 'th')) }}
                                @endif
                                of each month
                            </span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-circle text-xs mt-1 mr-2" style="color: var(--warning);"></i>
                            <span>Contact landlord for emergency issues after hours</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-circle text-xs mt-1 mr-2" style="color: var(--warning);"></i>
                            <span>All terms governed by the {{ $governingLaw }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Lease Information -->
            <div class="card p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-file-contract mr-2"></i> Lease Information
                    </h3>
                    @if($currentLease)
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs {{ $currentLease->status === 'active' ? 'badge-success' : 'badge-warning' }}">
                            {{ ucfirst(str_replace('_', ' ', $currentLease->status)) }}
                        </span>
                    @endif
                </div>

                @if($currentLease)
                <div class="space-y-4">
                    <!-- Period -->
                    <div>
                        <h5 class="font-medium mb-2" style="color: var(--text-primary);">Lease Period</h5>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--bg-secondary-rgb, 240, 240, 240), 0.5);">
                                <div class="text-sm" style="color: var(--text-secondary);">Start Date</div>
                                <div class="font-bold" style="color: var(--text-primary);">
                                    {{ $currentLease->start_date->format('M d, Y') }}
                                </div>
                            </div>
                            <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--bg-secondary-rgb, 240, 240, 240), 0.5);">
                                <div class="text-sm" style="color: var(--text-secondary);">End Date</div>
                                <div class="font-bold" style="color: var(--text-primary);">
                                    @if($currentLease->end_date)
                                        {{ $currentLease->end_date->format('M d, Y') }}
                                    @else
                                        Month-to-Month
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rent Details -->
                    <div>
                        <h5 class="font-medium mb-2" style="color: var(--text-primary);">Rent Details</h5>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span style="color: var(--text-secondary);">Monthly Rent:</span>
                                <span class="font-bold" style="color: var(--text-primary);">
                                    {{ $currencySymbol }} {{ number_format($currentLease->monthly_rent, 2) }}
                                </span>
                            </div>
                            @if($currentLease->security_deposit)
                            <div class="flex justify-between items-center">
                                <span style="color: var(--text-secondary);">Security Deposit:</span>
                                <span class="font-bold" style="color: var(--text-primary);">
                                    {{ $currencySymbol }} {{ number_format($currentLease->security_deposit, 2) }}
                                </span>
                            </div>
                            @endif
                            @if($currentLease->late_fee_percentage)
                            <div class="flex justify-between items-center">
                                <span style="color: var(--text-secondary);">Late Fee:</span>
                                <span class="font-bold" style="color: var(--text-primary);">
                                    {{ $currentLease->late_fee_percentage }}%
                                </span>
                            </div>
                            @endif
                            @if($hasAdvanceRent)
                            <div class="flex justify-between items-center">
                                <span style="color: var(--text-secondary);">Advance Rent:</span>
                                <span class="font-bold" style="color: var(--text-primary);">
                                    {{ $advanceMonths }} mo · {{ $currencySymbol }} {{ number_format($advanceAmount, 2) }}
                                </span>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="pt-4 border-t" style="border-color: var(--border-color);">
                        <div class="flex gap-3">
                            @if(Route::has('tenant.property-units.lease-details'))
                            <a href="{{ route('tenant.property-units.lease-details', [$unit->id, $currentLease->id]) }}"
                               class="btn-primary flex-1 px-4 py-2 rounded-lg font-medium text-white inline-flex items-center justify-center">
                                <i class="fas fa-eye mr-2"></i> View Details
                            </a>
                            @endif
                            @if(Route::has('tenant.property-units.lease-download-pdf'))
                            <a href="{{ route('tenant.property-units.lease-download-pdf', [$unit->id, $currentLease->id]) }}"
                               class="btn-secondary flex-1 px-4 py-2 rounded-lg font-medium inline-flex items-center justify-center">
                                <i class="fas fa-download mr-2"></i> Download PDF
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
                @else
                <div class="text-center py-8">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-file-contract text-2xl" style="color: var(--warning);"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Active Lease</h4>
                    <p class="mb-4" style="color: var(--text-secondary);">
                        You don't have an active lease agreement for this unit.
                    </p>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Please contact your landlord to create a lease agreement.
                    </p>
                </div>
                @endif
            </div>
        </div>

        <!-- ========== RECENT ACTIVITY ========== -->
        @if($recentActivities->count() > 0)
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-history mr-2"></i> Recent Activity
            </h3>

            <div class="space-y-3">
                @foreach($recentActivities as $activity)
                    @php
                        $activityMeta = [
                            'maintenance_request_created' => ['icon' => 'fa-tools',           'color' => 'warning'],
                            'invoice_generated'            => ['icon' => 'fa-money-bill-wave','color' => 'success'],
                            'tenant_vacated'               => ['icon' => 'fa-sign-out-alt',   'color' => 'danger'],
                            'tenant_approved'              => ['icon' => 'fa-user-check',     'color' => 'success'],
                            'lease_created'                => ['icon' => 'fa-file-contract',  'color' => 'info'],
                            'unit_assigned'                => ['icon' => 'fa-home',           'color' => 'primary'],
                        ];
                        $meta = $activityMeta[$activity->type] ?? ['icon' => 'fa-circle', 'color' => 'secondary'];
                    @endphp

                    <div class="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                        <div class="flex-shrink-0 mr-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                 style="background-color: rgba(var(--{{ $meta['color'] }}-rgb), 0.1); color: var(--{{ $meta['color'] }});">
                                <i class="fas {{ $meta['icon'] }}"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <div class="font-medium text-sm" style="color: var(--text-primary);">
                                {{ $activity->description }}
                            </div>
                            <div class="text-xs flex items-center mt-1" style="color: var(--text-secondary);">
                                <i class="far fa-clock mr-1"></i>
                                {{ $activity->created_at->diffForHumans() }}
                            </div>
                        </div>
                        @if($activity->type === 'maintenance_request_created' && Route::has('tenant.maintenance.index'))
                        <a href="{{ route('tenant.maintenance.index') }}"
                           class="text-xs px-2 py-1 rounded badge-info">
                            View
                        </a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    @endif
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide session messages
    setTimeout(() => {
        document.querySelectorAll('.bg-green-100, .bg-red-100, .bg-blue-100')
            .forEach(msg => msg.style.display = 'none');
    }, 5000);

    // ✅ REMOVED: 30-minute page-refresh hack.
    // Replaced by natural navigation; if live refresh is truly needed, use
    // a scoped AJAX call to a dedicated endpoint that only fetches
    // `$unit->fresh()` — not a full page reload.
});
</script>
@endsection