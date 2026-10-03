@php
    // Guard against slow renders on big exports.
    // NOTE: This must be called as a plain PHP function — do NOT use @set_time_limit
    // (there is no such Blade directive; it would be a silent no-op or a compile error).
    if (function_exists('set_time_limit')) {
        set_time_limit(120);
    }
    @ini_set('memory_limit', '512M');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $systemName ?? 'Property Pro' }} Users Export - {{ date('F j, Y') }}</title>
    <style>
        /* Base */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            margin: 20px 25px 40px 25px;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 13px;
            line-height: 1.5;
            color: #1f2937;
            margin: 0;
            padding: 0;
            position: relative;
        }

        .page-container {
            position: relative;
            z-index: 1;
            background: #ffffff;
        }

        /* Watermark (static — no position:fixed, which breaks DomPDF) */
        .watermark-background {
            position: absolute;
            top: 180px;
            left: 0;
            right: 0;
            text-align: center;
            z-index: 0;
            opacity: 0.06;
        }

        .watermark-logo {
            max-width: 420px;
            max-height: 420px;
            width: auto;
            height: auto;
        }

        .watermark-text {
            font-size: 72px;
            color: #000000;
            font-weight: bold;
            letter-spacing: 5px;
            text-transform: uppercase;
            font-family: 'DejaVu Sans', Arial, sans-serif;
        }

        /* Header — table layout (DomPDF-safe) */
        .header {
            border-bottom: 3px solid #2563eb;
            padding-bottom: 16px;
            margin-bottom: 24px;
            position: relative;
            z-index: 2;
            background: #ffffff;
        }

        .header-top {
            display: table;
            width: 100%;
            margin-bottom: 16px;
        }

        .logo-section {
            display: table-cell;
            vertical-align: top;
            width: 55%;
        }

        .report-info {
            display: table-cell;
            vertical-align: top;
            text-align: right;
            width: 45%;
        }

        .logo { margin-bottom: 8px; }

        .logo img {
            max-height: 64px;
            max-width: 260px;
            width: auto;
            height: auto;
        }

        .logo-text {
            font-size: 26px;
            font-weight: bold;
            color: #2563eb;
            letter-spacing: 1px;
        }

        .company-name {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .company-tagline {
            font-size: 12px;
            color: #6b7280;
        }

        .report-title {
            font-size: 26px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 6px;
        }

        .report-subtitle {
            font-size: 14px;
            color: #6b7280;
        }

        .header-meta {
            display: table;
            width: 100%;
            background: #f8fafc;
            padding: 14px 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-top: 6px;
        }

        .meta-item {
            display: table-cell;
            vertical-align: middle;
            padding-right: 16px;
        }

        .meta-item:last-child { padding-right: 0; }

        .meta-label {
            display: block;
            font-size: 11px;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .meta-value {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
        }

        /* Statistics — table layout */
        .statistics-section {
            margin-bottom: 26px;
            position: relative;
            z-index: 2;
            background: #ffffff;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 14px;
            padding-bottom: 9px;
            border-bottom: 2px solid #e2e8f0;
        }

        .stats-grid {
            display: table;
            width: 100%;
            table-layout: fixed;
            border-spacing: 12px 0;
            margin-bottom: 16px;
        }

        .stat-card {
            display: table-cell;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px 14px;
            text-align: center;
            vertical-align: middle;
            width: 25%;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 6px;
        }

        .stat-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .breakdown-grid {
            display: table;
            width: 100%;
            table-layout: fixed;
            border-spacing: 14px 0;
            margin-bottom: 16px;
        }

        .breakdown-section {
            display: table-cell;
            width: 50%;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            vertical-align: top;
        }

        .breakdown-title {
            font-size: 15px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 12px;
            padding-bottom: 9px;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
        }

        .breakdown-item {
            display: table;
            width: 100%;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .breakdown-item:last-child { border-bottom: none; }

        .breakdown-name {
            display: table-cell;
            font-size: 13px;
            color: #374151;
            font-weight: 500;
            vertical-align: middle;
        }

        .breakdown-right {
            display: table-cell;
            text-align: right;
            vertical-align: middle;
            white-space: nowrap;
        }

        .breakdown-count {
            font-weight: 700;
            color: #1f2937;
            font-size: 14px;
        }

        .breakdown-percentage {
            font-size: 11px;
            color: #6b7280;
            background: #f1f5f9;
            padding: 3px 8px;
            border-radius: 10px;
            margin-left: 7px;
        }

        /* Type indicators in the breakdown */
        .breakdown-type-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 7px;
            vertical-align: middle;
        }
        .dot-super-admin { background-color: #8b5cf6; }
        .dot-admin       { background-color: #3b82f6; }
        .dot-landlord    { background-color: #10b981; }
        .dot-field-agent { background-color: #06b6d4; }
        .dot-tenant      { background-color: #f59e0b; }
        .dot-security    { background-color: #6b7280; }
        .dot-contractor  { background-color: #6366f1; }
        .dot-sanitation  { background-color: #14b8a6; }
        .dot-developer   { background-color: #a855f7; }
        .dot-default     { background-color: #9ca3af; }

        .completion-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            margin-top: 16px;
        }

        .completion-title {
            font-size: 15px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 14px;
            text-align: center;
        }

        .completion-grid {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .completion-item {
            display: table-cell;
            text-align: center;
            vertical-align: middle;
            width: 33.33%;
        }

        .completion-value {
            font-size: 26px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .completion-label {
            font-size: 12px;
            color: #6b7280;
        }

        /* Users Table */
        .users-section {
            margin-bottom: 26px;
            position: relative;
            z-index: 2;
            background: #ffffff;
        }

        .table-container {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .table-header {
            background: #2563eb;
            color: #ffffff;
        }

        .table-header th {
            padding: 12px 9px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
        }

        .table-body td {
            padding: 11px 9px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .table-body tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* User Type Badges */
        .user-type {
            display: inline-block;
            padding: 5px 11px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .type-super-admin { background-color: #8b5cf6; color: #ffffff; }
        .type-admin       { background-color: #3b82f6; color: #ffffff; }
        .type-landlord    { background-color: #10b981; color: #ffffff; }
        .type-field-agent { background-color: #06b6d4; color: #ffffff; }
        .type-tenant      { background-color: #f59e0b; color: #ffffff; }
        .type-security    { background-color: #6b7280; color: #ffffff; }
        .type-contractor  { background-color: #6366f1; color: #ffffff; }
        .type-sanitation  { background-color: #14b8a6; color: #ffffff; }
        .type-developer   { background-color: #a855f7; color: #ffffff; }
        .type-default     { background-color: #9ca3af; color: #ffffff; }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 5px 11px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }
        .status-active                { background-color: #d1fae5; color: #065f46; }
        .status-pending               { background-color: #fef3c7; color: #92400e; }
        .status-suspended             { background-color: #fee2e2; color: #991b1b; }
        .status-inactive              { background-color: #e5e7eb; color: #374151; }
        .status-verification_required { background-color: #fed7aa; color: #9a3412; }

        /* Verification Indicators */
        .verification-badge {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .verified     { background-color: #10b981; }
        .not-verified { background-color: #ef4444; }
        .verification-text { font-size: 10px; }

        /* Photo */
        .user-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
            margin: 0 auto;
        }

        .photo-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #4b5563;
            color: #ffffff;
            display: inline-block;
            text-align: center;
            line-height: 40px;
            font-size: 16px;
            font-weight: bold;
        }

        /* Role Badges */
        .role-badge {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: 600;
            margin: 1px;
            background: #e5e7eb;
            color: #374151;
        }
        .role-badge-extra {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Footer */
        .footer {
            margin-top: 26px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            position: relative;
            z-index: 2;
            background: #ffffff;
        }

        .footer-info {
            display: table;
            width: 100%;
            margin-bottom: 10px;
            font-size: 10px;
            color: #6b7280;
        }

        .footer-info > div {
            display: table-cell;
            vertical-align: middle;
        }

        .footer-info > div:last-child {
            text-align: right;
        }

        .page-info { font-weight: 600; }

        /* Utilities */
        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .text-left   { text-align: left; }
        .font-bold   { font-weight: bold; }
        .text-muted  { color: #6b7280; }
        .mt-2 { margin-top: 5px; }
        .mb-1 { margin-bottom: 3px; }
        .mb-2 { margin-bottom: 5px; }
    </style>
</head>
<body>
    {{-- Watermark (static, not position:fixed) --}}
    <div class="watermark-background">
        @if(!empty($systemLogo))
            <img src="{{ $systemLogo }}" class="watermark-logo" alt="{{ $systemName }}">
        @else
            <div class="watermark-text">{{ $systemShortName ?? $systemName }}</div>
        @endif
    </div>

    <div class="page-container">
        {{-- Header --}}
        <div class="header">
            <div class="header-top">
                <div class="logo-section">
                    @if(!empty($systemLogo))
                        <div class="logo">
                            <img src="{{ $systemLogo }}" alt="{{ $systemName }}">
                        </div>
                    @else
                        <div class="logo-text">{{ $systemShortName ?? $systemName }}</div>
                    @endif
                    <div class="company-name">{{ $systemName }}</div>
                    @if(!empty($systemTagline))
                        <div class="company-tagline">{{ $systemTagline }}</div>
                    @endif
                </div>
                <div class="report-info">
                    <div class="report-title">Users Export Report</div>
                    <div class="report-subtitle">Generated on {{ $exportDate }}</div>
                </div>
            </div>

            <div class="header-meta">
                <div class="meta-item">
                    <span class="meta-label">System Name</span>
                    <span class="meta-value">{{ $systemName }}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Total Users</span>
                    <span class="meta-value">{{ $totalUsers }}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Export Type</span>
                    <span class="meta-value">User Directory</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Generated By</span>
                    <span class="meta-value">{{ $exportedBy ?? 'System' }}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Date Range</span>
                    <span class="meta-value">
                        @if(!empty($filters['start_date']) && !empty($filters['end_date']))
                            {{ date('M j, Y', strtotime($filters['start_date'])) }} - {{ date('M j, Y', strtotime($filters['end_date'])) }}
                        @else
                            All Time
                        @endif
                    </span>
                </div>
            </div>
        </div>

        {{-- Statistics --}}
        @if($includeStatistics && isset($statistics))
        <div class="statistics-section">
            <div class="section-title">Export Summary &amp; Statistics</div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">{{ $totalUsers }}</div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{{ $statistics['verified_phones'] ?? 0 }}</div>
                    <div class="stat-label">Phone Verified</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{{ $statistics['verified_emails'] ?? 0 }}</div>
                    <div class="stat-label">Email Verified</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{{ $statistics['with_photos'] ?? 0 }}</div>
                    <div class="stat-label">With Photos</div>
                </div>
            </div>

            <div class="breakdown-grid">
                {{-- ═══════════════════════════════════════════════════════ --}}
                {{-- User Type Distribution — ALL user types covered       --}}
                {{-- ═══════════════════════════════════════════════════════ --}}
                <div class="breakdown-section">
                    <div class="breakdown-title">User Type Distribution</div>

                    @forelse(($statistics['type_breakdown'] ?? []) as $type => $data)
                        @php
                            // Map the type constant to a friendly label + dot colour
                            switch ($type) {
                                case \App\Models\User::TYPE_SUPER_ADMIN:
                                    $label = 'Super Admin';
                                    $dotClass = 'dot-super-admin';
                                    break;
                                case \App\Models\User::TYPE_ADMIN:
                                    $label = 'Admin';
                                    $dotClass = 'dot-admin';
                                    break;
                                case \App\Models\User::TYPE_LANDLORD:
                                    $label = 'Landlord';
                                    $dotClass = 'dot-landlord';
                                    break;
                                case \App\Models\User::TYPE_TENANT:
                                    $label = 'Tenant';
                                    $dotClass = 'dot-tenant';
                                    break;
                                case \App\Models\User::TYPE_FIELD_AGENT:
                                    $label = 'Field Agent';
                                    $dotClass = 'dot-field-agent';
                                    break;
                                case \App\Models\User::TYPE_SECURITY_PERSONNEL:
                                    $label = 'Security Personnel';
                                    $dotClass = 'dot-security';
                                    break;
                                case \App\Models\User::TYPE_CONTRACTOR:
                                    $label = 'Contractor';
                                    $dotClass = 'dot-contractor';
                                    break;
                                case \App\Models\User::TYPE_SANITATION_PERSONNEL:
                                    $label = 'Sanitation Personnel';
                                    $dotClass = 'dot-sanitation';
                                    break;
                                case \App\Models\User::TYPE_DEVELOPER:
                                    $label = 'Developer';
                                    $dotClass = 'dot-developer';
                                    break;
                                default:
                                    $label = ucfirst(str_replace('_', ' ', (string) $type));
                                    $dotClass = 'dot-default';
                            }
                        @endphp

                        <div class="breakdown-item">
                            <span class="breakdown-name">
                                <span class="breakdown-type-dot {{ $dotClass }}"></span>{{ $label }}
                            </span>
                            <span class="breakdown-right">
                                <span class="breakdown-count">{{ $data['count'] ?? 0 }}</span>
                                <span class="breakdown-percentage">{{ $data['percentage'] ?? 0 }}%</span>
                            </span>
                        </div>
                    @empty
                        <div class="breakdown-item">
                            <span class="breakdown-name text-muted">No user type data available</span>
                        </div>
                    @endforelse
                </div>

                {{-- ═══════════════════════════════════════════════════════ --}}
                {{-- Account Status Breakdown                              --}}
                {{-- ═══════════════════════════════════════════════════════ --}}
                <div class="breakdown-section">
                    <div class="breakdown-title">Account Status</div>

                    @forelse(($statistics['status_breakdown'] ?? []) as $status => $data)
                        <div class="breakdown-item">
                            <span class="breakdown-name">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                            <span class="breakdown-right">
                                <span class="breakdown-count">{{ $data['count'] ?? 0 }}</span>
                                <span class="breakdown-percentage">{{ $data['percentage'] ?? 0 }}%</span>
                            </span>
                        </div>
                    @empty
                        <div class="breakdown-item">
                            <span class="breakdown-name text-muted">No status data available</span>
                        </div>
                    @endforelse
                </div>
            </div>

            @if(isset($statistics['completion_rate']))
            <div class="completion-card">
                <div class="completion-title">Profile Completion Rates</div>
                <div class="completion-grid">
                    <div class="completion-item">
                        <div class="completion-value" style="color: #10b981;">
                            {{ $statistics['completion_rate']['phone_verification'] ?? 0 }}%
                        </div>
                        <div class="completion-label">Phone Verification</div>
                    </div>
                    <div class="completion-item">
                        <div class="completion-value" style="color: #3b82f6;">
                            {{ $statistics['completion_rate']['email_verification'] ?? 0 }}%
                        </div>
                        <div class="completion-label">Email Verification</div>
                    </div>
                    <div class="completion-item">
                        <div class="completion-value" style="color: #f59e0b;">
                            {{ $statistics['completion_rate']['profile_photos'] ?? 0 }}%
                        </div>
                        <div class="completion-label">Profile Photos</div>
                    </div>
                </div>
            </div>
            @endif
        </div>
        @endif

        {{-- Users Table --}}
        <div class="users-section">
            <div class="section-title">User Directory ({{ $totalUsers }} Users)</div>

            <table class="table-container">
                <thead class="table-header">
                    <tr>
                        @if($includePhotos ?? false)
                        <th width="46" class="text-center">Photo</th>
                        @endif
                        <th width="48" class="text-center">ID</th>
                        <th>Name &amp; Contact</th>
                        <th width="105">Type &amp; Roles</th>
                        <th width="90">Status</th>
                        <th width="100">Phone</th>
                        <th width="100">Email</th>
                        <th width="100">Registered</th>
                        <th width="95">Last Login</th>
                    </tr>
                </thead>
                <tbody class="table-body">
                    @forelse($users as $user)
                    <tr>
                        @if($includePhotos ?? false)
                        <td class="text-center">
                            @if(!empty($user->photo_base64))
                                <img src="{{ $user->photo_base64 }}"
                                     class="user-photo"
                                     alt="{{ $user->name }}">
                            @else
                                <div class="photo-placeholder">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                            @endif
                        </td>
                        @endif

                        <td class="text-center"><span style="font-weight: 600;">#{{ $user->id }}</span></td>

                        <td>
                            <div style="font-weight: 600; margin-bottom: 3px; color: #1f2937; font-size: 12px;">{{ $user->name }}</div>
                            @if($user->username)
                            <div style="font-size: 10px; color: #6b7280; margin-bottom: 2px;">@ {{ $user->username }}</div>
                            @endif
                            @if($user->email)
                            <div style="font-size: 10px; color: #3b82f6;">{{ $user->email }}</div>
                            @endif

                            @if(isset($user->roles) && $user->roles->count() > 1)
                            <div style="margin-top: 4px;">
                                <span style="font-size: 9px; background: #dbeafe; color: #1e40af; padding: 2px 6px; border-radius: 3px;">
                                    Multi-role ({{ $user->roles->count() }})
                                </span>
                            </div>
                            @endif
                        </td>

                        <td>
                            {{-- Type badge — one @switch for class, one for label --}}
                            @php
                                switch ($user->type) {
                                    case \App\Models\User::TYPE_SUPER_ADMIN:
                                        $typeClass = 'type-super-admin';
                                        $typeLabel = 'Super Admin';
                                        break;
                                    case \App\Models\User::TYPE_ADMIN:
                                        $typeClass = 'type-admin';
                                        $typeLabel = 'Admin';
                                        break;
                                    case \App\Models\User::TYPE_LANDLORD:
                                        $typeClass = 'type-landlord';
                                        $typeLabel = 'Landlord';
                                        break;
                                    case \App\Models\User::TYPE_FIELD_AGENT:
                                        $typeClass = 'type-field-agent';
                                        $typeLabel = 'Field Agent';
                                        break;
                                    case \App\Models\User::TYPE_TENANT:
                                        $typeClass = 'type-tenant';
                                        $typeLabel = 'Tenant';
                                        break;
                                    case \App\Models\User::TYPE_SECURITY_PERSONNEL:
                                        $typeClass = 'type-security';
                                        $typeLabel = 'Security';
                                        break;
                                    case \App\Models\User::TYPE_CONTRACTOR:
                                        $typeClass = 'type-contractor';
                                        $typeLabel = 'Contractor';
                                        break;
                                    case \App\Models\User::TYPE_SANITATION_PERSONNEL:
                                        $typeClass = 'type-sanitation';
                                        $typeLabel = 'Sanitation';
                                        break;
                                    case \App\Models\User::TYPE_DEVELOPER:
                                        $typeClass = 'type-developer';
                                        $typeLabel = 'Developer';
                                        break;
                                    default:
                                        $typeClass = 'type-default';
                                        $typeLabel = ucfirst($user->type_name ?? 'User');
                                }
                            @endphp

                            <div class="user-type {{ $typeClass }}" style="margin-bottom: 4px;">
                                {{ $typeLabel }}
                            </div>

                            @if(isset($user->roles) && $user->roles->count() > 0)
                                @foreach($user->roles->where('slug', '!=', strtolower(str_replace(' ', '-', $user->type))) as $role)
                                    @if(!in_array($role->slug, ['super-admin', 'admin', 'landlord', 'field-agent', 'tenant', 'security-personnel']))
                                    <div class="role-badge role-badge-extra">
                                        {{ ucfirst(str_replace('-', ' ', $role->slug)) }}
                                    </div>
                                    @endif
                                @endforeach
                            @endif
                        </td>

                        <td class="text-center">
                            <span class="status-badge status-{{ $user->status }}">
                                {{ ucfirst(str_replace('_', ' ', $user->status)) }}
                            </span>
                        </td>

                        <td class="text-center">
                            <div>
                                <span class="verification-badge {{ $user->phone_verified_at ? 'verified' : 'not-verified' }}"></span>
                                <span class="verification-text">{{ $user->phone_verified_at ? 'Verified' : 'Pending' }}</span>
                            </div>
                            @if($user->phone)
                            <div class="text-muted" style="font-size: 10px; margin-top: 3px;">{{ $user->phone }}</div>
                            @else
                            <div class="text-muted" style="font-size: 10px; margin-top: 3px;">&mdash;</div>
                            @endif
                        </td>

                        <td class="text-center">
                            <div>
                                <span class="verification-badge {{ $user->email_verified_at ? 'verified' : 'not-verified' }}"></span>
                                <span class="verification-text">{{ $user->email_verified_at ? 'Verified' : 'Pending' }}</span>
                            </div>
                        </td>

                        <td class="text-center">
                            <div style="font-weight: 600; font-size: 11px;">
                                {{ \Carbon\Carbon::parse($user->created_at)->format('M j, Y') }}
                            </div>
                            <div class="text-muted" style="font-size: 9px;">
                                {{ \Carbon\Carbon::parse($user->created_at)->format('g:i A') }}
                            </div>
                            @if($user->creator)
                            <div class="text-muted" style="font-size: 9px; margin-top: 3px;">
                                by {{ $user->creator->name }}
                            </div>
                            @endif
                        </td>

                        <td class="text-center">
                            @if($user->last_login_at)
                            <div style="font-weight: 600; font-size: 11px;">
                                {{ \Carbon\Carbon::parse($user->last_login_at)->format('M j, Y') }}
                            </div>
                            <div class="text-muted" style="font-size: 9px;">
                                {{ \Carbon\Carbon::parse($user->last_login_at)->format('g:i A') }}
                            </div>
                            @else
                            <div class="text-muted" style="font-size: 11px;">Never</div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ ($includePhotos ?? false) ? 9 : 8 }}" class="text-center" style="padding: 40px; color: #9ca3af;">
                            <div style="font-size: 16px; margin-bottom: 8px;">No users found</div>
                            <div style="font-size: 12px;">No users match the selected criteria or filters.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            @if($totalUsers > 0)
            <div style="margin-top: 12px; padding: 11px; background: #f8fafc; border-radius: 6px; font-size: 11px; color: #6b7280; text-align: center;">
                Showing {{ $users->count() }} of {{ $totalUsers }} total users
            </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="footer">
            <div class="footer-info">
                <div>Generated by {{ $systemName }} User Management System</div>
                <div class="page-info">Confidential Report</div>
            </div>
            <div class="text-center" style="font-size: 9px;">
                <div>This report contains confidential user information. Distribution restricted to authorized personnel only.</div>
                <div style="margin-top: 5px;">&copy; {{ date('Y') }} {{ $systemName }}. All rights reserved.</div>
                @if(!empty($filters['export_id']))
                <div style="margin-top: 5px;">Export ID: {{ $filters['export_id'] }}</div>
                @endif
            </div>
        </div>
    </div>

    {{--
        NOTE: Do not re-add <script type="text/php"> here.
        DomPDF's inline PHP executes AFTER all HTML is rendered.
        A silent failure there causes the "browser hangs forever after
        log says complete" bug. Page numbers, if needed, should be added
        via DomPDF's Canvas API in the controller instead.
    --}}
</body>
</html>