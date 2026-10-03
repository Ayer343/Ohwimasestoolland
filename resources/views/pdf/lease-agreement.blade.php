{{-- resources/views/pdf/lease-agreement.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lease Agreement - {{ $property->property_name }} - Unit {{ $unit->unit_number }}</title>

    {{-- ============ FAVICON (safe against missing settings) ============ --}}
    @php
        try {
            $settings = \App\Models\SystemSetting::getSettings();
        } catch (\Throwable $e) {
            $settings = null;
        }
    @endphp
    @if($settings && method_exists($settings, 'hasFavicon') && $settings->hasFavicon())
        <link rel="icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
    @endif

    {{-- ============ ✅ GHANA: config values ============ --}}
    @php
        $currencySymbol = config('leases.ghana.currency.symbol', 'GH₵');
        $governingLaw   = config('leases.ghana.governing_law', 'Rent Act, 1963 (Act 220)');
        $legalMaxNew    = (int) config('leases.ghana.legal_max_advance_months.new_tenancy', 6);

        $hasAdvanceRent   = method_exists($lease, 'has_advance_rent') ? $lease->has_advance_rent : false;
        $isInAdvancePhase = method_exists($lease, 'is_in_advance_phase') ? $lease->is_in_advance_phase : false;
        $isInMonthlyPhase = method_exists($lease, 'is_in_monthly_phase') ? $lease->is_in_monthly_phase : false;
        $isCompliant      = method_exists($lease, 'is_advance_rent_compliant') ? $lease->is_advance_rent_compliant : true;

        $advanceMonths  = (int) ($lease->advance_rent_months ?? 0);
        $advanceAmount  = (float) ($lease->advance_rent_amount ?? 0);
        $advanceStart   = $lease->advance_rent_period_start ?? null;
        $advanceEnd     = $lease->advance_rent_period_end ?? null;
        $firstMonthly   = $lease->first_monthly_payment_date ?? null;

        $paymentFrequency = $lease->payment_frequency ?? 'monthly';
        $isAdvanceOnly    = $paymentFrequency === 'advance_only';

        // Legacy / fallback calculations
        $totalLeaseValue = method_exists($lease, 'calculateTotalRent')
            ? $lease->calculateTotalRent()
            : ($lease->monthly_rent ?? 0) * ($lease->duration_months ?? 12);

        $securityDeposit  = (float) ($lease->security_deposit ?? 0);
        $utilityDeposit   = (float) ($lease->utility_deposit ?? 0);

        // Safe end date (month-to-month has null end_date)
        $endDate = $lease->end_date ?? null;

        // Ground rent / monthly rent for the summary
        $monthlyRent = (float) ($lease->monthly_rent ?? 0);
    @endphp

    <style>
        /* ============================================================
           PDF — Optimized for DomPDF (no webfonts, no flex, no CSS vars)
           ============================================================ */
        @page {
            margin: 45px 40px 60px 40px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', 'Helvetica', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.55;
            color: #2c3e50;
            margin: 0;
            padding: 0;
        }

        /* ============ Typography ============ */
        h1, h2, h3, h4 {
            margin: 0;
            font-weight: bold;
            color: #1a2332;
        }

        /* ============ Header ============ */
        .doc-header {
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .doc-header .brand {
            font-size: 9px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .doc-header h1 {
            font-size: 22px;
            letter-spacing: 0.5px;
            margin: 8px 0 3px;
        }

        .doc-header h2 {
            font-size: 14px;
            font-weight: normal;
            color: #54637a;
            margin: 0 0 8px;
        }

        .doc-meta {
            font-size: 10px;
            color: #7f8c8d;
        }

        .doc-meta .meta-item {
            display: inline-block;
            margin-right: 18px;
        }

        /* ============ Status Badge ============ */
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .status-active      { background: #d4edda; color: #155724; }
        .status-draft       { background: #fff3cd; color: #856404; }
        .status-pending     { background: #cce5ff; color: #004085; }
        .status-terminated  { background: #f8d7da; color: #721c24; }
        .status-expired     { background: #e2e3e5; color: #383d41; }
        .status-compliant   { background: #d4edda; color: #155724; }
        .status-noncompliant{ background: #fff3cd; color: #856404; }

        /* ============ Sections ============ */
        .section {
            margin-bottom: 22px;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #2c3e50;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 1px solid #cfd6df;
            padding-bottom: 4px;
            margin-bottom: 12px;
        }

        /* ============ Info Tables ============ */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .info-table th,
        .info-table td {
            border: 1px solid #dfe4ea;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
            font-size: 10.5px;
        }

        .info-table th {
            background-color: #f6f8fa;
            font-weight: bold;
            color: #2c3e50;
            width: 32%;
        }

        .info-table td.highlight {
            background-color: #f6f8fa;
            font-weight: bold;
            color: #1a2332;
        }

        /* ============ Callout boxes ============ */
        .callout {
            border-left: 4px solid #3498db;
            background: #f6f8fa;
            padding: 12px 14px;
            margin-bottom: 14px;
            page-break-inside: avoid;
        }

        .callout.warning {
            border-left-color: #f39c12;
            background: #fff8e6;
        }

        .callout.danger {
            border-left-color: #e74c3c;
            background: #fdecea;
        }

        .callout.success {
            border-left-color: #27ae60;
            background: #eafaf1;
        }

        .callout .callout-title {
            font-weight: bold;
            font-size: 10.5px;
            margin-bottom: 4px;
            color: #1a2332;
        }

        .callout .callout-body {
            font-size: 10px;
            color: #3a4a5e;
        }

        /* ============ Financial Summary ============ */
        .financial-summary {
            border: 2px solid #2c3e50;
            padding: 12px 14px;
            background: #f6f8fa;
            margin-bottom: 14px;
            page-break-inside: avoid;
        }

        .financial-item {
            display: block;
            padding: 5px 0;
            border-bottom: 1px dashed #cfd6df;
            font-size: 10.5px;
        }

        .financial-item:last-child {
            border-bottom: none;
        }

        .financial-item .label {
            display: inline-block;
            width: 55%;
            font-weight: 600;
            color: #3a4a5e;
        }

        .financial-item .value {
            display: inline-block;
            width: 44%;
            text-align: right;
            font-weight: bold;
            color: #1a2332;
        }

        .financial-item.total {
            border-top: 2px solid #2c3e50;
            border-bottom: none;
            margin-top: 6px;
            padding-top: 8px;
            font-size: 11px;
        }

        /* ============ Two-column layout (DomPDF safe: uses table) ============ */
        .two-col {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px 0;
            margin: 0 -12px;
        }

        .two-col > tbody > tr > td {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }

        /* ============ Signature Blocks ============ */
        .sig-block {
            border: 1px solid #dfe4ea;
            background: #ffffff;
            padding: 12px;
            text-align: center;
            page-break-inside: avoid;
            min-height: 140px;
        }

        .sig-block .sig-role {
            font-size: 9px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 2px;
        }

        .sig-block .sig-name {
            font-size: 12px;
            font-weight: bold;
            color: #1a2332;
            margin-bottom: 10px;
        }

        .sig-block .sig-image-wrap {
            height: 60px;
            margin: 8px 0;
            text-align: center;
        }

        .sig-block .sig-image {
            max-height: 60px;
            max-width: 180px;
        }

        .sig-block .sig-line {
            border-top: 1px solid #2c3e50;
            margin-top: 55px;
            margin-bottom: 4px;
        }

        .sig-block .sig-meta {
            font-size: 9px;
            color: #54637a;
            line-height: 1.4;
        }

        /* ============ Witness Blocks ============ */
        .witness-block {
            border: 1px dashed #cfd6df;
            padding: 10px 12px;
            background: #fbfcfd;
            page-break-inside: avoid;
        }

        .witness-block .witness-role {
            font-size: 9px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
            margin-bottom: 6px;
        }

        .witness-block .witness-meta {
            font-size: 9px;
            color: #3a4a5e;
            line-height: 1.5;
            text-align: center;
        }

        /* ============ Compliance stamp ============ */
        .compliance-stamp {
            display: inline-block;
            padding: 3px 8px;
            border: 1px solid #27ae60;
            color: #155724;
            background: #eafaf1;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.5px;
            border-radius: 4px;
        }

        /* ============ Footer ============ */
        .doc-footer {
            position: fixed;
            bottom: -35px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8.5px;
            color: #95a5a6;
            border-top: 1px solid #dfe4ea;
            padding-top: 6px;
        }

        .doc-footer .page-number:after {
            content: counter(page);
        }

        /* ============ Utilities ============ */
        .text-center   { text-align: center; }
        .text-right    { text-align: right; }
        .text-muted    { color: #7f8c8d; }
        .text-bold     { font-weight: bold; }
        .mb-0          { margin-bottom: 0; }
        .mt-6          { margin-top: 6px; }
        .mt-10         { margin-top: 10px; }
        .mt-14         { margin-top: 14px; }
        .mt-20         { margin-top: 20px; }
        .mb-6          { margin-bottom: 6px; }
        .mb-10         { margin-bottom: 10px; }
        .mb-14         { margin-bottom: 14px; }
        .avoid-break   { page-break-inside: avoid; }
        .page-break    { page-break-before: always; }
    </style>
</head>
<body>

    {{-- DRAFT watermark --}}
    @if($lease->status === 'draft')
        <div style="position: fixed; top: 45%; left: 0; right: 0; text-align: center; font-size: 90px; color: rgba(0,0,0,0.06); font-weight: bold; z-index: -1;">
            DRAFT
        </div>
    @endif

    {{-- ============================================================
         HEADER
         ============================================================ --}}
    <div class="doc-header">
        <div class="brand">{{ config('app.name', 'Property Management System') }}</div>
        <h1>LEASE AGREEMENT</h1>
        <h2>{{ $property->property_name }} — Unit {{ $unit->unit_number }}</h2>
        <div class="doc-meta">
            <span class="meta-item"><strong>Agreement #:</strong> {{ $lease->agreement_number }}</span>
            <span class="meta-item"><strong>Generated:</strong> {{ $generated_date }}</span>
            <span class="meta-item"><strong>Currency:</strong> {{ $currencySymbol }}</span>
        </div>
        <div class="mt-10">
            @php
                $statusClass = match($lease->status) {
                    'active'      => 'status-active',
                    'draft'       => 'status-draft',
                    'terminated'  => 'status-terminated',
                    'expired'     => 'status-expired',
                    default       => 'status-pending',
                };
            @endphp
            <span class="status-badge {{ $statusClass }}">{{ strtoupper(str_replace('_', ' ', $lease->status)) }}</span>

            {{-- ✅ GHANA: compliance stamp --}}
            @if($hasAdvanceRent)
                @if($isCompliant)
                    <span class="compliance-stamp">COMPLIANT · {{ $governingLaw }}</span>
                @else
                    <span class="compliance-stamp" style="border-color:#f39c12; color:#856404; background:#fff8e6;">
                        NON-COMPLIANT · EXCEEDS {{ $legalMaxNew }}-MONTH CAP
                    </span>
                @endif
            @endif
        </div>
    </div>

    {{-- ============================================================
         1. PARTIES
         ============================================================ --}}
    <div class="section">
        <div class="section-title">1. Parties to this Agreement</div>

        <table class="info-table">
            <tr>
                <th>LANDLORD</th>
                <td>
                    <strong>{{ $landlord->name }}</strong><br>
                    <span class="text-muted">
                        @if($landlord->email) Email: {{ $landlord->email }}<br> @endif
                        @if($landlord->phone) Phone: {{ $landlord->phone }}<br> @endif
                        @if($landlord->address) Address: {{ $landlord->address }} @endif
                    </span>
                </td>
            </tr>
            <tr>
                <th>TENANT</th>
                <td>
                    <strong>{{ $tenant->name }}</strong><br>
                    <span class="text-muted">
                        @if($tenant->email) Email: {{ $tenant->email }}<br> @endif
                        @if($tenant->phone) Phone: {{ $tenant->phone }}<br> @endif
                        @if($tenant->national_id) National ID: {{ $tenant->national_id }} @endif
                    </span>
                </td>
            </tr>
            <tr>
                <th>PROPERTY</th>
                <td>
                    <strong>{{ $property->property_name }}</strong><br>
                    <span class="text-muted">
                        Address: {{ $property->address }}<br>
                        Unit: {{ $unit->unit_number }}@if($unit->unit_name) ({{ $unit->unit_name }})@endif
                    </span>
                </td>
            </tr>
        </table>
    </div>

    {{-- ============================================================
         2. LEASE TERMS
         ============================================================ --}}
    <div class="section">
        <div class="section-title">2. Lease Term &amp; Duration</div>

        <table class="info-table">
            <tr>
                <th>Agreement Number</th>
                <td>{{ $lease->agreement_number }}</td>
            </tr>
            <tr>
                <th>Start Date</th>
                <td>{{ $lease->start_date->format('F j, Y') }}</td>
            </tr>
            <tr>
                <th>End Date</th>
                <td>
                    @if($endDate)
                        {{ $endDate->format('F j, Y') }}
                    @else
                        <em>Month-to-Month (no fixed end date)</em>
                    @endif
                </td>
            </tr>
            @if($lease->duration_months)
            <tr>
                <th>Duration</th>
                <td>{{ $lease->duration_months }} months</td>
            </tr>
            @endif
            <tr>
                <th>Lease Type</th>
                <td>{{ ucfirst(str_replace('_', ' ', $lease->lease_type ?? 'fixed')) }}</td>
            </tr>
            <tr>
                <th>Renewable</th>
                <td>{{ ($lease->is_renewable ?? false) ? 'Yes' : 'No' }}</td>
            </tr>
            @if(($lease->is_renewable ?? false) && ($lease->renewal_notice_days ?? false))
            <tr>
                <th>Renewal Notice</th>
                <td>{{ $lease->renewal_notice_days }} days before expiry</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- ============================================================
         3. ADVANCE RENT & PAYMENT STRUCTURE (Ghana)
         ============================================================ --}}
    @if($hasAdvanceRent)
    <div class="section">
        <div class="section-title">3. Advance Rent &amp; Payment Structure ({{ $governingLaw }})</div>

        <div class="callout {{ $isCompliant ? 'success' : 'warning' }}">
            <div class="callout-title">
                @if($isCompliant)
                    ✓ Advance rent is compliant with the {{ $governingLaw }}.
                @else
                    ⚠ Advance rent exceeds the {{ $legalMaxNew }}-month legal cap.
                @endif
            </div>
            <div class="callout-body">
                @if($isCompliant)
                    The advance rent on this lease does not exceed the maximum permitted by
                    Section 25(5) of the {{ $governingLaw }}.
                @else
                    This lease carries an advance rent of <strong>{{ $advanceMonths }} month(s)</strong>,
                    exceeding the {{ $legalMaxNew }}-month cap under the {{ $governingLaw }}.
                    The advance was recorded as a voluntary offer by the Tenant.
                    @if($lease->advance_rent_acknowledged_at)
                        Acknowledged on {{ $lease->advance_rent_acknowledged_at->format('F j, Y') }}.
                    @endif
                @endif
            </div>
        </div>

        <table class="info-table">
            <tr>
                <th>Payment Frequency</th>
                <td>
                    @if($isAdvanceOnly)
                        Full Advance (entire term paid upfront)
                    @else
                        Advance + Monthly (advance now, monthly thereafter)
                    @endif
                </td>
            </tr>
            <tr>
                <th>Advance Rent Period</th>
                <td>
                    @if($advanceStart && $advanceEnd)
                        {{ $advanceStart->format('F j, Y') }} — {{ $advanceEnd->format('F j, Y') }}
                        ({{ $advanceMonths }} month{{ $advanceMonths > 1 ? 's' : '' }})
                    @else
                        {{ $advanceMonths }} month{{ $advanceMonths > 1 ? 's' : '' }}
                    @endif
                </td>
            </tr>
            <tr>
                <th>Advance Rent Amount</th>
                <td class="highlight">{{ $currencySymbol }} {{ number_format($advanceAmount, 2) }}</td>
            </tr>
            @if(!$isAdvanceOnly && $firstMonthly)
            <tr>
                <th>First Monthly Payment Due</th>
                <td>
                    {{ $firstMonthly->format('F j, Y') }}
                    ({{ $currencySymbol }} {{ number_format($monthlyRent, 2) }})
                </td>
            </tr>
            @endif
            <tr>
                <th>Monthly Rent (post-advance)</th>
                <td>{{ $currencySymbol }} {{ number_format($monthlyRent, 2) }}</td>
            </tr>
            <tr>
                <th>Due Day</th>
                <td>
                    {{ $lease->payment_due_day }}{{ $lease->payment_due_day == 1 ? 'st' : ($lease->payment_due_day == 2 ? 'nd' : ($lease->payment_due_day == 3 ? 'rd' : 'th')) }}
                    of each month
                </td>
            </tr>
        </table>

        @if($isInAdvancePhase)
        <div class="callout">
            <div class="callout-title">Current Status: Advance Phase</div>
            <div class="callout-body">
                The lease is currently covered by advance rent. No monthly payments are due
                until {{ $advanceEnd ? $advanceEnd->format('F j, Y') : 'the end of the advance period' }}.
            </div>
        </div>
        @elseif($isInMonthlyPhase)
        <div class="callout success">
            <div class="callout-title">Current Status: Monthly Phase</div>
            <div class="callout-body">
                The advance rent period has ended. Monthly rent of
                <strong>{{ $currencySymbol }} {{ number_format($monthlyRent, 2) }}</strong>
                is now due on the {{ $lease->payment_due_day }}{{ $lease->payment_due_day == 1 ? 'st' : ($lease->payment_due_day == 2 ? 'nd' : ($lease->payment_due_day == 3 ? 'rd' : 'th')) }}
                of each month, as required by the {{ $governingLaw }}.
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- ============================================================
         4. FINANCIAL TERMS
         ============================================================ --}}
    <div class="section">
        <div class="section-title">{{ $hasAdvanceRent ? '4' : '3' }}. Financial Terms</div>

        <div class="financial-summary">
            <div class="financial-item">
                <span class="label">Monthly Rent</span>
                <span class="value">{{ $currencySymbol }} {{ number_format($monthlyRent, 2) }}</span>
            </div>

            @if($hasAdvanceRent)
            <div class="financial-item">
                <span class="label">Advance Rent ({{ $advanceMonths }} mo)</span>
                <span class="value">{{ $currencySymbol }} {{ number_format($advanceAmount, 2) }}</span>
            </div>
            @endif

            <div class="financial-item">
                <span class="label">Security Deposit</span>
                <span class="value">{{ $currencySymbol }} {{ number_format($securityDeposit, 2) }}</span>
            </div>

            @if($utilityDeposit > 0)
            <div class="financial-item">
                <span class="label">Utility Deposit</span>
                <span class="value">{{ $currencySymbol }} {{ number_format($utilityDeposit, 2) }}</span>
            </div>
            @endif

            @if(($lease->late_fee_percentage ?? 0) > 0 || ($lease->late_fee_fixed ?? 0) > 0)
            <div class="financial-item">
                <span class="label">Late Fee</span>
                <span class="value">
                    @if(($lease->late_fee_percentage ?? 0) > 0)
                        {{ $lease->late_fee_percentage }}% of rent
                    @endif
                    @if(($lease->late_fee_percentage ?? 0) > 0 && ($lease->late_fee_fixed ?? 0) > 0)
                        +
                    @endif
                    @if(($lease->late_fee_fixed ?? 0) > 0)
                        {{ $currencySymbol }} {{ number_format($lease->late_fee_fixed, 2) }}
                    @endif
                </span>
            </div>
            @endif

            <div class="financial-item">
                <span class="label">Payment Due Day</span>
                <span class="value">{{ $lease->payment_due_day }}th of each month</span>
            </div>

            <div class="financial-item">
                <span class="label">Grace Period</span>
                <span class="value">{{ $lease->grace_period_days ?? 0 }} days</span>
            </div>

            @if(($lease->early_termination_fee ?? 0) > 0)
            <div class="financial-item">
                <span class="label">Early Termination Fee</span>
                <span class="value" style="color:#c0392b;">{{ $currencySymbol }} {{ number_format($lease->early_termination_fee, 2) }}</span>
            </div>
            @endif

            <div class="financial-item total">
                <span class="label">Total Lease Value</span>
                <span class="value">{{ $currencySymbol }} {{ number_format($totalLeaseValue, 2) }}</span>
            </div>

            <div class="financial-item total">
                <span class="label">Total Deposits</span>
                <span class="value">{{ $currencySymbol }} {{ number_format($securityDeposit + $utilityDeposit, 2) }}</span>
            </div>
        </div>
    </div>

    {{-- ============================================================
         5. PROPERTY DETAILS
         ============================================================ --}}
    <div class="section">
        <div class="section-title">{{ $hasAdvanceRent ? '5' : '4' }}. Property Details</div>

        <table class="info-table">
            <tr>
                <th>Unit Number</th>
                <td>{{ $unit->unit_number }}</td>
            </tr>
            @if($unit->unit_name)
            <tr>
                <th>Unit Name</th>
                <td>{{ $unit->unit_name }}</td>
            </tr>
            @endif
            <tr>
                <th>Bedrooms</th>
                <td>{{ $unit->bedrooms ?? 0 }}</td>
            </tr>
            <tr>
                <th>Bathrooms</th>
                <td>{{ $unit->bathrooms ?? 0 }}</td>
            </tr>
            @if($unit->floor_area)
            <tr>
                <th>Floor Area</th>
                <td>{{ number_format($unit->floor_area, 2) }} m²</td>
            </tr>
            @endif
            <tr>
                <th>Furnished</th>
                <td>{{ ($unit->is_furnished ?? false) ? 'Yes' : 'No' }}</td>
            </tr>
            @if(!empty($unit->amenities))
            <tr>
                <th>Amenities</th>
                <td>
                    @php
                        $amenityLabels = [
                            'parking' => 'Parking Space', 'balcony' => 'Balcony',
                            'air_conditioning' => 'Air Conditioning', 'furnished' => 'Fully Furnished',
                            'wifi' => 'Wi-Fi', 'security' => '24/7 Security',
                            'gym' => 'Gym Access', 'pool' => 'Swimming Pool',
                            'laundry' => 'Laundry Facility', 'elevator' => 'Elevator',
                            'generator' => 'Backup Generator', 'cctv' => 'CCTV Surveillance',
                            'fire_safety' => 'Fire Safety System', 'water_heater' => 'Water Heater',
                            'kitchen_appliances' => 'Kitchen Appliances',
                        ];
                        $amenities = collect((array) $unit->amenities)->map(function ($a) use ($amenityLabels) {
                            return $amenityLabels[$a] ?? ucfirst(str_replace('_', ' ', $a));
                        });
                    @endphp
                    {{ $amenities->implode(', ') }}
                </td>
            </tr>
            @endif
        </table>
    </div>

    {{-- ============================================================
         6. RESPONSIBILITIES
         ============================================================ --}}
    @if(($lease->tenant_responsibilities && is_array($lease->tenant_responsibilities)) || ($lease->landlord_responsibilities && is_array($lease->landlord_responsibilities)))
    <div class="section">
        <div class="section-title">{{ $hasAdvanceRent ? '6' : '5' }}. Responsibilities</div>

        <div class="callout">
            @if($lease->tenant_responsibilities && is_array($lease->tenant_responsibilities))
                <div class="callout-title">Tenant Responsibilities</div>
                <div class="callout-body">
                    <ul style="margin: 4px 0 10px 20px; padding: 0;">
                        @foreach($lease->tenant_responsibilities as $r)
                            <li>{{ $r }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($lease->landlord_responsibilities && is_array($lease->landlord_responsibilities))
                <div class="callout-title">Landlord Responsibilities</div>
                <div class="callout-body">
                    <ul style="margin: 4px 0 0 20px; padding: 0;">
                        @foreach($lease->landlord_responsibilities as $r)
                            <li>{{ $r }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ============================================================
         7. TERMS & CONDITIONS
         ============================================================ --}}
    @if(($lease->special_terms && is_array($lease->special_terms)) || ($lease->house_rules && is_array($lease->house_rules)) || ($lease->utilities_included && is_array($lease->utilities_included)) || $lease->terms)
    <div class="section">
        <div class="section-title">{{ $hasAdvanceRent ? '7' : '6' }}. Terms &amp; Conditions</div>

        @if($lease->terms)
            <div class="callout">
                <div class="callout-body" style="white-space: pre-line;">{!! nl2br(e($lease->terms)) !!}</div>
            </div>
        @endif

        @if($lease->special_terms && is_array($lease->special_terms))
            <div class="callout">
                <div class="callout-title">Special Terms</div>
                <div class="callout-body">
                    <ul style="margin: 4px 0 0 20px; padding: 0;">
                        @foreach($lease->special_terms as $t) <li>{{ $t }}</li> @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if($lease->house_rules && is_array($lease->house_rules))
            <div class="callout">
                <div class="callout-title">House Rules</div>
                <div class="callout-body">
                    <ul style="margin: 4px 0 0 20px; padding: 0;">
                        @foreach($lease->house_rules as $r) <li>{{ $r }}</li> @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if($lease->utilities_included && is_array($lease->utilities_included))
            <div class="callout">
                <div class="callout-title">Utilities Included</div>
                <div class="callout-body">
                    <ul style="margin: 4px 0 0 20px; padding: 0;">
                        @foreach($lease->utilities_included as $u) <li>{{ $u }}</li> @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
    @endif

    {{-- ============================================================
         8. SIGNATURES
         ============================================================ --}}
    <div class="section avoid-break">
        <div class="section-title">{{ $hasAdvanceRent ? '8' : '7' }}. Signatures</div>

        {{-- Status strip --}}
        <div class="callout">
            <div class="callout-body">
                @if($lease->is_fully_signed)
                    <span class="status-badge status-active">✓ FULLY EXECUTED</span>
                @elseif($lease->is_landlord_signed || $lease->is_tenant_signed)
                    <span class="status-badge status-pending">⏱ PARTIALLY SIGNED</span>
                @else
                    <span class="status-badge status-draft">✗ NOT SIGNED</span>
                @endif

                @if($lease->has_witness_signatures)
                    &nbsp;<span class="status-badge status-active">✓ WITNESSED</span>
                @endif
            </div>
        </div>

        {{-- Two-column signature block --}}
        <table class="two-col">
            <tr>
                {{-- Landlord --}}
                <td>
                    <div class="sig-block">
                        <div class="sig-role">Landlord</div>
                        <div class="sig-name">{{ $landlord->name }}</div>

                        @if($lease->landlord_signed_at && $lease->landlord_signature)
                            <div class="sig-image-wrap">
                                @if(str_starts_with($lease->landlord_signature, 'data:image'))
                                    <img src="{{ $lease->landlord_signature }}" class="sig-image" alt="Landlord Signature">
                                @else
                                    <div class="text-muted" style="padding-top: 20px;">[Signature on file]</div>
                                @endif
                            </div>
                            <div class="sig-meta">
                                <strong>Signed:</strong> {{ $lease->landlord_signed_at->format('M j, Y H:i') }}<br>
                                @if($lease->landlord_signature_type)
                                    ({{ ucfirst($lease->landlord_signature_type) }} signature)
                                @endif
                            </div>
                        @else
                            <div class="sig-line"></div>
                            <div class="sig-meta text-muted">
                                Signature &amp; Date<br>
                                <em>Not signed yet</em>
                            </div>
                        @endif
                    </div>
                </td>

                {{-- Tenant --}}
                <td>
                    <div class="sig-block">
                        <div class="sig-role">Tenant</div>
                        <div class="sig-name">{{ $tenant->name }}</div>

                        @if($lease->tenant_signed_at && $lease->tenant_signature)
                            <div class="sig-image-wrap">
                                @if(str_starts_with($lease->tenant_signature, 'data:image'))
                                    <img src="{{ $lease->tenant_signature }}" class="sig-image" alt="Tenant Signature">
                                @else
                                    <div class="text-muted" style="padding-top: 20px;">[Signature on file]</div>
                                @endif
                            </div>
                            <div class="sig-meta">
                                <strong>Signed:</strong> {{ $lease->tenant_signed_at->format('M j, Y H:i') }}<br>
                                @if($lease->tenant_signature_type)
                                    ({{ ucfirst($lease->tenant_signature_type) }} signature)
                                @endif
                            </div>
                        @else
                            <div class="sig-line"></div>
                            <div class="sig-meta text-muted">
                                Signature &amp; Date<br>
                                <em>Not signed yet</em>
                            </div>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ============================================================
         9. WITNESSES
         ============================================================ --}}
    <div class="section avoid-break">
        <div class="section-title">{{ $hasAdvanceRent ? '9' : '8' }}. Witness Signatures</div>

        <table class="two-col">
            <tr>
                {{-- Landlord Witness --}}
                <td>
                    <div class="witness-block">
                        <div class="witness-role">
                            Landlord's Witness
                            @if($lease->has_landlord_witness)
                                &nbsp;<span class="compliance-stamp">✓ PRESENT</span>
                            @endif
                        </div>

                        @if($lease->has_landlord_witness && $lease->landlord_witness_details)
                            @php $lw = $lease->landlord_witness_details; @endphp
                            @if(!empty($lw['signature']) && str_starts_with($lw['signature'], 'data:image'))
                                <div class="sig-image-wrap text-center">
                                    <img src="{{ $lw['signature'] }}" class="sig-image" alt="Witness Signature">
                                </div>
                            @else
                                <div class="sig-line" style="margin-top: 40px;"></div>
                            @endif
                            <div class="witness-meta">
                                <strong>{{ $lw['name'] ?? '—' }}</strong><br>
                                @if(!empty($lw['relationship'])) Relationship: {{ $lw['relationship'] }}<br> @endif
                                @if(!empty($lw['role'])) Role: {{ $lw['role'] }}<br> @endif
                                <strong>Witnessed:</strong> {{ $lease->landlord_witness_signed_at?->format('M j, Y H:i') }}<br>
                                @if(!empty($lw['phone'])) Phone: {{ $lw['phone'] }} @endif
                            </div>
                        @else
                            <div class="sig-line" style="margin-top: 40px;"></div>
                            <div class="witness-meta text-muted">
                                Witness Signature &amp; Date<br>
                                <em>No witness signature</em>
                            </div>
                        @endif
                    </div>
                </td>

                {{-- Tenant Witness --}}
                <td>
                    <div class="witness-block">
                        <div class="witness-role">
                            Tenant's Witness
                            @if($lease->has_tenant_witness)
                                &nbsp;<span class="compliance-stamp">✓ PRESENT</span>
                            @endif
                        </div>

                        @if($lease->has_tenant_witness && $lease->tenant_witness_details)
                            @php $tw = $lease->tenant_witness_details; @endphp
                            @if(!empty($tw['signature']) && str_starts_with($tw['signature'], 'data:image'))
                                <div class="sig-image-wrap text-center">
                                    <img src="{{ $tw['signature'] }}" class="sig-image" alt="Witness Signature">
                                </div>
                            @else
                                <div class="sig-line" style="margin-top: 40px;"></div>
                            @endif
                            <div class="witness-meta">
                                <strong>{{ $tw['name'] ?? '—' }}</strong><br>
                                @if(!empty($tw['relationship'])) Relationship: {{ $tw['relationship'] }}<br> @endif
                                @if(!empty($tw['role'])) Role: {{ $tw['role'] }}<br> @endif
                                <strong>Witnessed:</strong> {{ $lease->tenant_witness_signed_at?->format('M j, Y H:i') }}<br>
                                @if(!empty($tw['phone'])) Phone: {{ $tw['phone'] }} @endif
                            </div>
                        @else
                            <div class="sig-line" style="margin-top: 40px;"></div>
                            <div class="witness-meta text-muted">
                                Witness Signature &amp; Date<br>
                                <em>No witness signature</em>
                            </div>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ============================================================
         10. AGREEMENT STATUS & VERIFICATION
         ============================================================ --}}
    <div class="section avoid-break">
        <div class="section-title">{{ $hasAdvanceRent ? '10' : '9' }}. Agreement Status &amp; Verification</div>

        <table class="info-table">
            <tr>
                <th>Agreement Status</th>
                <td class="text-bold">{{ strtoupper(str_replace('_', ' ', $lease->status)) }}</td>
            </tr>
            <tr>
                <th>Created</th>
                <td>{{ $lease->created_at->format('F j, Y') }}</td>
            </tr>
            @if($lease->approved_at)
            <tr><th>Approved</th><td>{{ $lease->approved_at->format('F j, Y') }}</td></tr>
            @endif
            @if($lease->terminated_at)
            <tr><th>Terminated</th><td>{{ $lease->terminated_at->format('F j, Y') }}</td></tr>
            <tr><th>Termination Reason</th><td>{{ $lease->termination_reason }}</td></tr>
            @endif
            @if($lease->notes)
            <tr><th>Notes</th><td>{{ $lease->notes }}</td></tr>
            @endif
            <tr>
                <th>Governing Law</th>
                <td>{{ $governingLaw }}</td>
            </tr>
            <tr>
                <th>Verification Reference</th>
                <td>
                    <strong>Agreement #:</strong> {{ $lease->agreement_number }}<br>
                    <strong>Lease ID:</strong> {{ $lease->id }}<br>
                    <strong>Property:</strong> {{ $property->property_name }} — Unit {{ $unit->unit_number }}
                </td>
            </tr>
        </table>
    </div>

    {{-- ============================================================
         FOOTER
         ============================================================ --}}
    <div class="doc-footer">
        <div>
            Lease Agreement {{ $lease->agreement_number }} ·
            {{ $property->property_name }} — Unit {{ $unit->unit_number }}
        </div>
        <div>
            Page <span class="page-number"></span> ·
            Generated {{ $generated_date }} ·
            This is a computer-generated document governed by the {{ $governingLaw }}.
        </div>
    </div>

</body>
</html>