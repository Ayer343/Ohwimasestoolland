{{-- resources/views/property_units/witness-sign-landlord.blade.php --}}
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign as witness for landlord's lease agreement">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign as Witness - Landlord | Lease Agreement</title>

    <!-- Using only Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* ========================================================================= */
        /* CSS RESET AND ACCESSIBILITY FOUNDATION                                    */
        /* ========================================================================= */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :focus:not(:focus-visible) { outline: none; }

        :focus-visible {
            outline: 3px solid var(--focus-outline, #7267f0);
            outline-offset: 2px;
            border-radius: 4px;
        }

        /* ========================================================================= */
        /* CSS VARIABLES WITH FALLBACK VALUES                                        */
        /* ========================================================================= */
        :root {
            --primary: #7267f0;
            --primary-light: #8c82ff;
            --primary-dark: #5e59e6;
            --secondary: #5e59e6;

            --success: #28c76f;
            --success-light: #3ae187;
            --danger: #ea5455;
            --danger-light: #ff6b6b;
            --warning: #ff9f43;
            --warning-light: #ffb74d;
            --info: #00cfe8;
            --info-light: #45d4e8;

            --light: #f6f6f6;
            --dark: #4b4b4b;
            --white: #ffffff;
            --black: #000000;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;

            --primary-rgb: 114, 103, 240;
            --success-rgb: 40, 199, 111;
            --warning-rgb: 255, 159, 67;
            --danger-rgb: 234, 84, 85;
            --info-rgb: 0, 207, 232;

            --bg-primary: #f8f8f8;
            --bg-secondary: #ffffff;
            --text-primary: #4b4b4b;
            --text-secondary: #6b7280;
            --card-bg: #ffffff;
            --header-bg: #ffffff;
            --border-color: #e5e7eb;
            --focus-outline: #7267f0;

            --transition-fast: 150ms;
            --transition-base: 300ms;
            --transition-slow: 500ms;

            --radius-sm: 4px;
            --radius-base: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-2xl: 20px;

            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-base: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        [data-theme="dark"] {
            --primary: #8c82ff;
            --primary-light: #a59cff;
            --primary-dark: #7873ff;
            --secondary: #7873ff;

            --success: #3ae187;
            --danger: #ff6b6b;
            --warning: #ffb74d;
            --info: #45d4e8;

            --light: #2a2a2a;
            --dark: #f5f5f5;

            --bg-primary: #1e1e2d;
            --bg-secondary: #2a2a3c;
            --text-primary: #e4e4e4;
            --text-secondary: #a0a0a0;
            --card-bg: #2a2a3c;
            --header-bg: #2a2a3c;
            --border-color: #39394a;
            --focus-outline: #8c82ff;
        }

        /* ========================================================================= */
        /* BASE STYLES                                                               */
        /* ========================================================================= */
        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Segoe UI Emoji', 'Segoe UI Symbol', sans-serif;
            line-height: 1.6;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            transition: background-color var(--transition-base), color var(--transition-base);
        }

        /* ========================================================================= */
        /* CUSTOM COMPONENT STYLES                                                   */
        /* ========================================================================= */
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .header {
            position: relative;
            text-align: center;
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .header h1 {
            font-size: 2.25rem;
            font-weight: 700;
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .header-subtitle {
            color: var(--text-secondary);
            font-size: 1.125rem;
        }

        /* ✅ THEME: toggle button in header */
        .theme-toggle-btn {
            position: absolute;
            top: 0;
            right: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: var(--radius-base);
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            cursor: pointer;
            transition: all var(--transition-fast) ease;
        }

        .theme-toggle-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
        }

        .theme-toggle-btn i { font-size: 1rem; }

        .alert {
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            box-shadow: var(--shadow-md);
        }

        .alert-info {
            border-left: 4px solid var(--info);
            background: linear-gradient(to right, rgba(var(--info-rgb), 0.05), transparent);
        }

        .alert h4 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert h4 i { color: var(--info); }

        .alert p {
            margin-bottom: 0.75rem;
            padding-left: 1.75rem;
            position: relative;
        }

        .alert p:before {
            content: "•";
            position: absolute;
            left: 0.75rem;
            color: var(--info);
            font-weight: bold;
        }

        .form-group { margin-bottom: 1.75rem; }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .form-group label.required:after {
            content: " *";
            color: var(--danger);
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-base);
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all var(--transition-fast) ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15);
        }

        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.7;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1.5em;
            padding-right: 2.5rem;
        }

        [data-theme="dark"] select.form-control {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23a0a0a0'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        }

        /* ✅ GHANA PHONE: hint text below the phone field */
        .phone-hint {
            display: block;
            margin-top: 0.35rem;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .phone-hint code {
            background: rgba(var(--primary-rgb), 0.1);
            color: var(--primary);
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
        }

        .signature-container {
            background: var(--bg-secondary);
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-top: 0.5rem;
            transition: all var(--transition-fast) ease;
        }

        .signature-container:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
        }

        #signaturePad {
            width: 100%;
            height: 200px;
            border-radius: var(--radius-base);
            border: 1px solid var(--border-color);
            background: var(--white);
            cursor: crosshair;
            touch-action: none;
            display: block;
        }

        /* Keep the signature canvas white in dark mode */
        [data-theme="dark"] #signaturePad {
            background: #ffffff;
        }

        .signature-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.875rem 1.75rem;
            border-radius: var(--radius-base);
            font-weight: 500;
            font-size: 1rem;
            transition: all var(--transition-fast) ease;
            border: none;
            cursor: pointer;
            gap: 0.5rem;
            min-height: 44px;
            min-width: 44px;
        }

        .btn-primary {
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background-color: rgba(var(--primary-rgb), 0.05);
            border-color: var(--primary);
        }

        .btn-success {
            background: linear-gradient(to right, var(--success), var(--success-light));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-lg {
            padding: 1rem 2rem;
            font-size: 1.125rem;
        }

        .form-check {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: rgba(var(--primary-rgb), 0.03);
            border-radius: var(--radius-base);
            border: 1px solid var(--border-color);
        }

        .form-check-input {
            width: 1.25rem;
            height: 1.25rem;
            margin-top: 0.25rem;
            accent-color: var(--primary);
        }

        .form-check-label {
            flex: 1;
            color: var(--text-primary);
            font-weight: 500;
        }

        .lease-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .detail-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            box-shadow: var(--shadow-sm);
            transition: transform var(--transition-fast) ease;
        }

        .detail-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .detail-card h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .detail-card p {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .footer {
            text-align: center;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .footer a {
            color: var(--primary);
            text-decoration: none;
        }

        .footer a:hover { text-decoration: underline; }

        .is-invalid { border-color: var(--danger) !important; }

        .invalid-feedback {
            color: var(--danger);
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }

        .is-invalid + .invalid-feedback { display: block; }

        .loading {
            opacity: 0.7;
            pointer-events: none;
            position: relative;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid var(--primary);
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .witness-type-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(to right, rgba(var(--success-rgb), 0.1), rgba(var(--success-rgb), 0.05));
            border-radius: var(--radius-base);
            color: var(--success);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        /* ✅ GHANA: governing-law badge */
        .governing-law-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.85rem;
            background: rgba(var(--warning-rgb), 0.1);
            border: 1px solid rgba(var(--warning-rgb), 0.3);
            border-radius: var(--radius-base);
            color: var(--warning);
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* ========================================================================= */
        /* RESPONSIVE DESIGN                                                         */
        /* ========================================================================= */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .header h1 { font-size: 1.75rem; }
            .header-subtitle { font-size: 1rem; }
            .lease-details-grid { grid-template-columns: 1fr; }
            .signature-actions { flex-direction: column; }
            .signature-actions .btn { width: 100%; }
            #signaturePad { height: 180px; }
            .theme-toggle-btn { top: -3rem; right: 0; }
        }

        @media (max-width: 480px) {
            .header h1 { font-size: 1.5rem; }
            .alert { padding: 1rem; }
            .btn { padding: 0.75rem 1.5rem; }
            #signaturePad { height: 150px; }
        }

        @media print {
            .header, .alert, .signature-container, .form-check, .footer, .theme-toggle-btn { display: none !important; }
            .container { max-width: 100%; padding: 0; }
            body { background: white !important; color: black !important; }
            .form-control { border: 1px solid #000 !important; background: white !important; color: black !important; }
        }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-primary); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: var(--radius-base); }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary-dark); }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            {{-- ✅ THEME: toggle button --}}
            <button type="button"
                    id="themeToggle"
                    class="theme-toggle-btn"
                    aria-label="Toggle dark mode"
                    onclick="toggleTheme()">
                <i class="fas fa-moon" id="themeIcon"></i>
            </button>

            <h1>
                <i class="fas fa-user-tie mr-2"></i>Sign as Witness (Landlord)
            </h1>
            <p class="header-subtitle">
                Please provide your witness signature for the landlord's lease agreement
            </p>

            {{-- ✅ GHANA: governing law badge --}}
            <div class="mt-3">
                <span class="governing-law-badge">
                    <i class="fas fa-balance-scale"></i>
                    Governed by {{ $governingLaw ?? config('leases.ghana.governing_law', 'Rent Act, 1963 (Act 220)') }}
                </span>
            </div>
        </header>

        <!-- Witness Type Indicator -->
        <div class="witness-type-indicator">
            <i class="fas fa-user-tie"></i>
            <span>You are witnessing the landlord's signature</span>
        </div>

        <!-- Lease Information Alert -->
        <div class="alert alert-info">
            <h4>
                <i class="fas fa-info-circle"></i>Lease Agreement Details
            </h4>
            <div class="lease-details-grid">
                <div class="detail-card">
                    <h4><i class="fas fa-building mr-2"></i>Property</h4>
                    <p>{{ $unit->property->property_name }}</p>
                </div>
                <div class="detail-card">
                    <h4><i class="fas fa-door-closed mr-2"></i>Unit</h4>
                    <p>{{ $unit->unit_number }}</p>
                </div>
                <div class="detail-card">
                    <h4><i class="fas fa-user-tie mr-2"></i>Landlord</h4>
                    <p>{{ $lease->landlord->name }}</p>
                </div>
                <div class="detail-card">
                    <h4><i class="fas fa-user mr-2"></i>Tenant</h4>
                    <p>{{ $lease->tenant->name }}</p>
                </div>
            </div>

            @if($lease->landlord_signed_at)
            <p>
                <i class="fas fa-calendar-check mr-2"></i>
                <strong>Lease Signed By Landlord On:</strong> {{ $lease->landlord_signed_at->format('F j, Y H:i') }}
            </p>
            @endif

            @if($lease->tenant_signed_at)
            <p>
                <i class="fas fa-calendar-alt mr-2"></i>
                <strong>Lease Signed By Tenant On:</strong> {{ $lease->tenant_signed_at->format('F j, Y H:i') }}
            </p>
            @endif

            {{-- ✅ GHANA: advance-rent context --}}
            @if($lease->advance_rent_months > 0)
            <p>
                <i class="fas fa-hand-holding-usd mr-2"></i>
                <strong>Advance Rent:</strong>
                {{ $lease->advance_rent_months }} month(s) —
                {{ config('leases.ghana.currency.symbol', 'GH₵') }}
                {{ number_format($lease->advance_rent_amount, 2) }}
            </p>
            @endif

            @if($lease->monthly_rent)
            <p>
                <i class="fas fa-money-bill-wave mr-2"></i>
                <strong>Monthly Rent:</strong>
                {{ config('leases.ghana.currency.symbol', 'GH₵') }}
                {{ number_format($lease->monthly_rent, 2) }}
            </p>
            @endif
        </div>

        <!-- Witness Signature Form -->
        {{-- ✅ Route existence check --}}
        @php
            $witnessRouteName = Route::has('witness.landlord-sign') ? 'witness.landlord-sign' : null;
            $witnessRouteAction = $witnessRouteName
                ? route($witnessRouteName, [$unit->id, $lease->id])
                : '#';
        @endphp

        <form method="POST"
              action="{{ $witnessRouteAction }}"
              id="witnessForm"
              @if(!$witnessRouteName) onsubmit="event.preventDefault(); showToast('Witness signing route not configured. Please contact support.', 'error');" @endif
        >
            @csrf

            <div class="form-group">
                <label for="witness_name" class="required">
                    <i class="fas fa-user mr-2"></i>Your Full Name
                </label>
                <input type="text"
                       name="witness_name"
                       id="witness_name"
                       required
                       class="form-control"
                       placeholder="Enter your full legal name"
                       value="{{ old('witness_name') }}">
                <div class="invalid-feedback">Please enter your full name</div>
            </div>

            <div class="form-group">
                <label for="witness_relationship">
                    <i class="fas fa-handshake mr-2"></i>Your Relationship to Landlord
                </label>
                <select name="witness_relationship" id="witness_relationship" class="form-control">
                    <option value="">Select Relationship</option>
                    <option value="family_member" {{ old('witness_relationship') == 'family_member' ? 'selected' : '' }}>Family Member</option>
                    <option value="friend" {{ old('witness_relationship') == 'friend' ? 'selected' : '' }}>Friend</option>
                    <option value="colleague" {{ old('witness_relationship') == 'colleague' ? 'selected' : '' }}>Colleague</option>
                    <option value="business_partner" {{ old('witness_relationship') == 'business_partner' ? 'selected' : '' }}>Business Partner</option>
                    <option value="lawyer" {{ old('witness_relationship') == 'lawyer' ? 'selected' : '' }}>Lawyer</option>
                    <option value="other" {{ old('witness_relationship') == 'other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="witness_role">
                    <i class="fas fa-briefcase mr-2"></i>Your Role/Position (Optional)
                </label>
                <input type="text"
                       name="witness_role"
                       id="witness_role"
                       class="form-control"
                       placeholder="e.g., Attorney, Business Partner, Manager"
                       value="{{ old('witness_role') }}">
            </div>

            <!-- Signature Section -->
            <div class="form-group">
                <label class="required">
                    <i class="fas fa-signature mr-2"></i>Your Signature
                </label>
                <div class="signature-container">
                    <canvas id="signaturePad"></canvas>
                    <input type="hidden" name="witness_signature_data" id="signatureData" value="{{ old('witness_signature_data') }}">
                    <div class="signature-actions">
                        <button type="button" onclick="clearSignature()" class="btn btn-secondary">
                            <i class="fas fa-undo mr-2"></i>Clear Signature
                        </button>
                        <button type="button" onclick="saveSignature()" class="btn btn-primary">
                            <i class="fas fa-save mr-2"></i>Save Signature
                        </button>
                    </div>
                </div>
                <div class="invalid-feedback" id="signatureError">Please provide your signature</div>
            </div>

            <input type="hidden" name="witness_signature_type" value="digital">
            <input type="hidden" name="signed_at" value="{{ now()->format('Y-m-d\TH:i') }}">

            <div class="form-group">
                <label for="witness_email">
                    <i class="fas fa-envelope mr-2"></i>Email (for confirmation)
                </label>
                <input type="email"
                       name="witness_email"
                       id="witness_email"
                       class="form-control"
                       placeholder="your.email@example.com"
                       value="{{ old('witness_email') }}">
                <div class="invalid-feedback">Please enter a valid email address</div>
            </div>

            {{-- ============================================================
                 ✅ GHANA PHONE: updated placeholder + validation + hint
                 ============================================================ --}}
            <div class="form-group">
                <label for="witness_phone">
                    <i class="fas fa-phone mr-2"></i>Phone (Optional)
                </label>
                <input type="tel"
                       name="witness_phone"
                       id="witness_phone"
                       class="form-control"
                       placeholder="+233 59 565 2410"
                       inputmode="tel"
                       autocomplete="tel"
                       pattern="^(\+233|0)[0-9\s\-]{9,14}$"
                       value="{{ old('witness_phone') }}">
                <span class="phone-hint">
                    <i class="fas fa-info-circle mr-1"></i>
                    Ghanaian format:
                    <code>+233 59 565 2410</code>
                    or
                    <code>0595652410</code>
                </span>
                <div class="invalid-feedback">Please enter a valid Ghanaian phone number (e.g., +233 59 565 2410).</div>
            </div>

            <!-- Confirmation Checkbox -->
            <div class="form-check">
                <input type="checkbox"
                       name="confirmation"
                       id="confirmation"
                       required
                       class="form-check-input">
                <label for="confirmation" class="form-check-label">
                    <strong>Confirmation:</strong> I confirm that I witnessed the landlord signing this lease agreement
                    @if($lease->landlord_signed_at)
                        on {{ $lease->landlord_signed_at->format('F j, Y') }}
                    @endif
                    and that all information provided is accurate. I understand this is a legally binding witness signature.
                </label>
            </div>

            <!-- Submit Button -->
            <div class="form-group">
                <button type="submit" class="btn btn-success btn-lg w-full" id="submitBtn">
                    <i class="fas fa-check-circle mr-2"></i>Submit Witness Signature
                </button>
            </div>
        </form>

        <!-- Footer -->
        <footer class="footer">
            <p>
                <i class="fas fa-shield-alt mr-2"></i>
                Your information is secure and will only be used for lease documentation purposes.
            </p>
            <p class="mt-2">
                <i class="fas fa-question-circle mr-2"></i>
                Need help? Contact support at <a href="mailto:support@example.com">support@example.com</a>
            </p>
        </footer>
    </div>

    <!-- Signature Pad Library -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

    <script>
        let signaturePad = null;
        let isSignatureSaved = false;
        let userHasDrawn = false;

        // ========== ✅ THEME ==========
        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            const icon = document.getElementById('themeIcon');
            if (icon) {
                icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        }

        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme') || 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            localStorage.setItem('theme', next);
        }

        // Respect saved theme, else OS preference
        (function initTheme() {
            const saved = localStorage.getItem('theme');
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            applyTheme(saved || (prefersDark ? 'dark' : 'light'));
        })();

        // ========== Signature Pad ==========
        function initSignaturePad() {
            const canvas = document.getElementById('signaturePad');
            if (!canvas) return;

            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);

            signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgba(255, 255, 255, 0)',
                penColor: 'rgb(0, 0, 0)',
                minWidth: 0.8,
                maxWidth: 2.5,
                throttle: 16,
                minDistance: 5
            });

            signaturePad.addEventListener('beginStroke', () => {
                userHasDrawn = true;
            });

            window.addEventListener('resize', debounce(resizeSignatureCanvas, 200));

            // Load existing signature if available
            const savedSignature = document.getElementById('signatureData').value;
            if (savedSignature) {
                const img = new Image();
                img.onload = function () {
                    canvas.getContext('2d').drawImage(img, 0, 0, canvas.offsetWidth, canvas.offsetHeight);
                    isSignatureSaved = true;
                };
                img.src = savedSignature;
            }
        }

        // ✅ FIX: preserve drawn strokes on resize instead of wiping them
        function resizeSignatureCanvas() {
            const canvas = document.getElementById('signaturePad');
            if (!canvas || !signaturePad) return;

            const strokeData = userHasDrawn && !signaturePad.isEmpty()
                ? signaturePad.toData()
                : null;

            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);

            if (strokeData) {
                signaturePad.fromData(strokeData);
            }
        }

        function debounce(fn, wait) {
            let t;
            return function (...args) {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), wait);
            };
        }

        // ========== Signature Actions ==========
        function clearSignature() {
            if (signaturePad) {
                signaturePad.clear();
                document.getElementById('signatureData').value = '';
                isSignatureSaved = false;
                userHasDrawn = false;
                document.getElementById('signatureError').style.display = 'none';
            }
        }

        function saveSignature() {
            if (signaturePad && !signaturePad.isEmpty()) {
                const signatureData = signaturePad.toDataURL('image/png');
                document.getElementById('signatureData').value = signatureData;
                isSignatureSaved = true;
                document.getElementById('signatureError').style.display = 'none';
                showToast('Signature saved successfully!', 'success');
            } else {
                showToast('Please draw your signature first.', 'error');
                document.getElementById('signatureError').style.display = 'block';
            }
        }

        // ========== Toast ==========
        function showToast(message, type = 'info') {
            const existingToast = document.querySelector('.toast');
            if (existingToast) existingToast.remove();

            const toast = document.createElement('div');
            toast.className = `toast fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 transform transition-transform duration-300 ${
                type === 'success' ? 'bg-green-500 text-white' :
                type === 'error' ? 'bg-red-500 text-white' :
                'bg-blue-500 text-white'
            }`;
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // ========== ✅ Validators ==========
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        /**
         * ✅ GHANA PHONE: Accepts +233xxxxxxxxx, 233xxxxxxxxx, or 0xxxxxxxxx.
         * Strips spaces and hyphens before checking.
         */
        function validateGhanaPhone(phone) {
            if (!phone) return true; // optional
            const cleaned = phone.replace(/[\s\-]/g, '');
            return /^(\+233|233|0)[2-5]\d{8}$/.test(cleaned);
        }

        // ========== Form submission ==========
        document.getElementById('witnessForm')?.addEventListener('submit', function (e) {
            @if(!$witnessRouteName)
                return; // inline onsubmit handles the "route missing" case
            @endif

            e.preventDefault();

            // Reset previous errors
            this.querySelectorAll('.form-control').forEach(input => {
                input.classList.remove('is-invalid');
                const feedback = input.nextElementSibling;
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                    feedback.style.display = 'none';
                }
            });

            let isValid = true;
            const nameInput = document.getElementById('witness_name');
            const emailInput = document.getElementById('witness_email');
            const phoneInput = document.getElementById('witness_phone');
            const confirmationCheck = document.getElementById('confirmation');

            // Name
            if (!nameInput.value.trim()) {
                nameInput.classList.add('is-invalid');
                nameInput.nextElementSibling.style.display = 'block';
                isValid = false;
            }

            // Email (optional, but if provided must be valid)
            if (emailInput.value.trim() && !validateEmail(emailInput.value)) {
                emailInput.classList.add('is-invalid');
                emailInput.nextElementSibling.style.display = 'block';
                isValid = false;
            }

            // ✅ GHANA PHONE (optional, but if provided must match Ghana format)
            if (phoneInput.value.trim() && !validateGhanaPhone(phoneInput.value.trim())) {
                phoneInput.classList.add('is-invalid');
                const feedback = phoneInput.parentElement.querySelector('.invalid-feedback');
                if (feedback) feedback.style.display = 'block';
                showToast('Please enter a valid Ghanaian phone number (e.g., +233 59 565 2410).', 'error');
                isValid = false;
            }

            // Signature
            if (!isSignatureSaved) {
                document.getElementById('signatureError').style.display = 'block';
                isValid = false;
            }

            // Confirmation
            if (!confirmationCheck.checked) {
                confirmationCheck.classList.add('is-invalid');
                showToast('Please confirm by checking the confirmation box.', 'error');
                isValid = false;
            }

            if (!isValid) {
                showToast('Please fill in all required fields correctly.', 'error');
                return;
            }

            // Normalize the phone to E.164 before submit
            if (phoneInput.value.trim()) {
                let cleaned = phoneInput.value.trim().replace(/[\s\-]/g, '');
                if (cleaned.startsWith('0')) {
                    cleaned = '+233' + cleaned.substring(1);
                } else if (cleaned.startsWith('233')) {
                    cleaned = '+' + cleaned;
                }
                phoneInput.value = cleaned;
            }

            // Loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;

            setTimeout(() => this.submit(), 500);
        });

        // ========== Auto-save on canvas leave ==========
        document.getElementById('signaturePad')?.addEventListener('mouseleave', function () {
            if (signaturePad && !signaturePad.isEmpty() && !isSignatureSaved) {
                saveSignature();
            }
        });

        // ========== Init ==========
        document.addEventListener('DOMContentLoaded', function () {
            initSignaturePad();

            // Real-time validation on blur
            document.querySelectorAll('.form-control[required]').forEach(input => {
                input.addEventListener('blur', function () {
                    const feedback = this.nextElementSibling;
                    if (this.value.trim() === '') {
                        this.classList.add('is-invalid');
                        if (feedback && feedback.classList.contains('invalid-feedback')) {
                            feedback.style.display = 'block';
                        }
                    } else {
                        this.classList.remove('is-invalid');
                        if (feedback && feedback.classList.contains('invalid-feedback')) {
                            feedback.style.display = 'none';
                        }
                    }
                });
            });

            document.getElementById('witness_name')?.focus();

            @if($errors->any())
                @foreach($errors->all() as $error)
                    showToast(@json($error), 'error');
                @endforeach
            @endif
        });
    </script>
</body>
</html>