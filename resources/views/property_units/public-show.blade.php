{{-- resources/views/property-units/public-show.blade.php --}}
@php
    use Illuminate\Support\Facades\Storage;

    $unit     = $unit     ?? null;
    $property = $unit?->property;
    $photos   = $unit?->photos ?? collect();

    if (!$unit) {
        abort(404);
    }

    $systemSettings = $systemSettings ?? \App\Models\SystemSetting::getSettings();

    $unitLabel    = $unit->unit_name ?? $unit->unit_number ?? 'Unit #' . $unit->id;
    $propertyName = $property?->property_name ?? 'Property';
    $propertyAddr = $property?->address ?? null;

    $propertyRegion = $property?->region ?? null;
    $propertyArea   = $property?->area   ?? null;

    $locationBits = collect([
        $propertyArea,
        $property?->street_name,
        $property?->zone,
        $propertyRegion,
    ])->filter(fn ($v) => $v !== null && $v !== '')->values();

    if ($locationBits->isEmpty() && $propertyAddr) {
        $locationBits->push($propertyAddr);
    }

    $locationSummary = $locationBits->implode(', ');

    $shortLocation = collect([$propertyArea, $propertyRegion])
        ->filter(fn ($v) => $v !== null && $v !== '')
        ->implode(', ');

    $coverPhoto = $photos->firstWhere('is_cover', true) ?? $photos->first();
    $allPhotos  = $photos->map(function ($photo) {
        try {
            $disk = $photo->disk ?? 'public';
            return [
                'url'   => $photo->path ? Storage::disk($disk)->url($photo->path) : null,
                'thumb' => $photo->thumbnail_path
                    ? Storage::disk($disk)->url($photo->thumbnail_path)
                    : ($photo->path ? Storage::disk($disk)->url($photo->path) : null),
                'caption' => $photo->caption,
                'is_cover'=> (bool) $photo->is_cover,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    })->filter()->values();

    $coverUrl = $allPhotos->firstWhere('is_cover', true)['url'] ?? ($allPhotos->first()['url'] ?? null);
    $coverUrl = $coverUrl ? url($coverUrl) : null;

    $isAvailable = $unit->status === \App\Models\PropertyUnit::STATUS_AVAILABLE
        && !$unit->tenant_id;

    $rentFormatted = $unit->rent_formatted
        ?? ($unit->monthly_rent
            ? $systemSettings->formatAmount($unit->monthly_rent)
            : null);

    $structuredData   = $structuredData   ?? null;
    $similarUnits     = $similarUnits     ?? collect();
    $landlordVerified = $landlordVerified ?? false;

    $channelsAvailable = $channelsAvailable ?? [
        'sms'      => true,
        'email'    => true,
        'whatsapp' => true,
    ];

    $amenityIcons = [
        'parking'          => ['fa-parking',        'Parking'],
        'security'         => ['fa-shield-alt',     '24/7 Security'],
        'air_conditioning' => ['fa-snowflake',      'Air Conditioning'],
        'wifi'             => ['fa-wifi',           'Wi-Fi'],
        'gym'              => ['fa-dumbbell',       'Gym'],
        'swimming_pool'    => ['fa-swimming-pool',  'Swimming Pool'],
        'furnished'        => ['fa-couch',          'Furnished'],
        'balcony'          => ['fa-door-open',      'Balcony'],
        'laundry'          => ['fa-tshirt',         'Laundry'],
        'elevator'         => ['fa-arrow-up',       'Elevator'],
        'generator'        => ['fa-bolt',           'Backup Generator'],
        'cctv'             => ['fa-video',          'CCTV'],
        'water_heater'     => ['fa-hot-tub',        'Water Heater'],
        'kitchen_appliances'=> ['fa-utensils',      'Kitchen Appliances'],
        'fire_safety'      => ['fa-fire-extinguisher','Fire Safety'],
    ];

    $ogTitle = $unitLabel . ' — ' . $propertyName;
    $ogDesc  = ($rentFormatted ? $rentFormatted . '/month' : 'For rent')
             . ($shortLocation ? ' · ' . $shortLocation : ($propertyAddr ? ' · ' . $propertyAddr : ''))
             . ($isAvailable ? ' · Available now' : '')
             . ' on ' . ($systemSettings->system_name ?? 'our platform');
    $ogUrl   = url()->current();
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta name="description" content="{{ $unitLabel }} — {{ $propertyName }}{{ $locationSummary ? ', ' . $locationSummary : '' }}. Available for rent.">

    {{-- 🌗 THEME: apply stored preference (or system pref) BEFORE paint
         to avoid a flash of the wrong theme. --}}
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('publicUnitTheme');
                var theme = stored
                    ? stored
                    : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', theme);
                document.documentElement.style.colorScheme = theme;
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

    {{-- ============ OPEN GRAPH / SOCIAL PREVIEW TAGS ============ --}}
    <meta property="og:title"       content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDesc }}">
    <meta property="og:url"         content="{{ $ogUrl }}">
    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="{{ $systemSettings->system_name ?? 'Community Portal' }}">

    @if($coverUrl)
        <meta property="og:image"        content="{{ $coverUrl }}">
        <meta property="og:image:secure_url" content="{{ $coverUrl }}">
        <meta property="og:image:width"  content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt"    content="{{ $unitLabel }}">
        <meta property="og:image:type"   content="image/jpeg">
    @endif

    <meta name="twitter:card"        content="{{ $coverUrl ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title"       content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDesc }}">
    @if($coverUrl)
        <meta name="twitter:image" content="{{ $coverUrl }}">
        <meta name="twitter:image:alt" content="{{ $unitLabel }}">
    @endif

    @if(!empty($structuredData))
        <script type="application/ld+json">
            {!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
        </script>
    @endif

    <title>{{ $unitLabel }} — {{ $propertyName }} | {{ $systemSettings->system_name ?? 'Community Portal' }}</title>

    @if($favicon = $systemSettings->getFaviconUrl())
        <link rel="icon" href="{{ $favicon }}" type="image/x-icon">
        <link rel="apple-touch-icon" href="{{ $favicon }}">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    @endif

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* ============================================================
           THEME TOKENS
           ------------------------------------------------------------
           All colors live here. The toggle flips [data-theme] on <html>,
           and every component reads from these variables — so dark mode
           is a one-attribute change, no component-level overrides.
           ============================================================ */
        :root,
        [data-theme="light"] {
            --primary: #3b82f6;
            --primary-rgb: 59, 130, 246;
            --secondary: #8b5cf6;
            --secondary-rgb: 139, 92, 246;
            --success: #10b981;
            --success-rgb: 16, 185, 129;
            --warning: #f59e0b;
            --warning-rgb: 245, 158, 11;
            --danger: #ef4444;
            --danger-rgb: 239, 68, 68;
            --info: #06b6d4;
            --info-rgb: 6, 182, 212;

            --text-primary: #1a1a2e;
            --text-secondary: #6c757d;
            --text-tertiary: #9ca3af;

            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #eef1f5;
            --card-bg: #ffffff;

            --border-color: #e9ecef;
            --border-strong: #d1d5db;

            --hero-badge-bg: rgba(0, 0, 0, 0.55);

            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;

            --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.12);
        }

        [data-theme="dark"] {
            --primary: #60a5fa;
            --primary-rgb: 96, 165, 250;
            --secondary: #a78bfa;
            --secondary-rgb: 167, 139, 250;
            --success: #34d399;
            --success-rgb: 52, 211, 153;
            --warning: #fbbf24;
            --warning-rgb: 251, 191, 36;
            --danger: #f87171;
            --danger-rgb: 248, 113, 113;
            --info: #22d3ee;
            --info-rgb: 34, 211, 238;

            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-tertiary: #64748b;

            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --card-bg: #1e293b;

            --border-color: #334155;
            --border-strong: #475569;

            --hero-badge-bg: rgba(0, 0, 0, 0.75);

            --shadow-sm: 0 1px 2px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.4);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.5);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html {
            background: var(--bg-secondary);
            /* Smooth transitions when toggling theme */
            transition: background-color 0.25s ease, color 0.25s ease;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            transition: background-color 0.25s ease, color 0.25s ease;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        /* ============ HEADER ============ */
        .topbar {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
            padding: 0.75rem 0;
            transition: background-color 0.25s ease, border-color 0.25s ease;
        }
        .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--text-primary);
            text-decoration: none;
        }
        .brand i { color: var(--primary); font-size: 1.4rem; }
        .brand img { height: 36px; width: auto; max-width: 140px; object-fit: contain; }

        .topbar-actions { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }

        /* ============ THEME TOGGLE ============ */
        .theme-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 1rem;
        }
        .theme-toggle:hover {
            background: var(--bg-tertiary);
            border-color: var(--border-strong);
            transform: translateY(-1px);
        }
        .theme-toggle .icon-sun,
        .theme-toggle .icon-moon {
            transition: opacity 0.2s ease, transform 0.3s ease;
        }
        /* Show sun in dark mode (clicking it goes light), moon in light mode */
        [data-theme="light"] .theme-toggle .icon-sun { display: none; }
        [data-theme="light"] .theme-toggle .icon-moon { display: inline-block; }
        [data-theme="dark"] .theme-toggle .icon-sun { display: inline-block; }
        [data-theme="dark"] .theme-toggle .icon-moon { display: none; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.6rem 1rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            font-family: inherit;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3); }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        .btn-outline:hover { background: var(--bg-tertiary); }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* ============ HERO IMAGE ============ */
        .hero {
            margin-top: 1.5rem;
            border-radius: var(--radius-lg);
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            position: relative;
            aspect-ratio: 16 / 9;
        }
        .hero img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .hero-empty {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.5);
            font-size: 4rem;
        }
        .hero-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            padding: 0.4rem 0.9rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            backdrop-filter: blur(6px);
        }
        .hero-badge--available { background: rgba(var(--success-rgb), 0.9); color: white; }
        .hero-badge--unavailable { background: rgba(var(--danger-rgb), 0.9); color: white; }

        .hero-region-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.4rem 0.9rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            background: var(--hero-badge-bg);
            color: white;
            backdrop-filter: blur(6px);
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            max-width: 60%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ============ LAYOUT ============ */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            margin: 1.5rem 0;
        }
        @media (min-width: 900px) {
            .content-grid { grid-template-columns: 2fr 1fr; }
        }

        /* ============ CARDS ============ */
        .card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: background-color 0.25s ease, border-color 0.25s ease;
        }
        .card-body { padding: 1.5rem; }
        .card-title {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-primary);
        }

        /* ============ INFO ============ */
        .unit-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.3rem;
            line-height: 1.2;
        }
        .unit-location {
            color: var(--text-secondary);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 1.25rem;
        }
        .price-line {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0.5rem 0;
        }
        .price-line small {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .location-pills { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 1rem; }
        .location-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            background: rgba(var(--info-rgb), 0.12);
            color: var(--info);
            border: 1px solid rgba(var(--info-rgb), 0.3);
        }
        .location-pill i { font-size: 0.75rem; }
        .location-pill--region {
            background: rgba(var(--primary-rgb), 0.12);
            color: var(--primary);
            border-color: rgba(var(--primary-rgb), 0.3);
        }

        /* ============ SPECS ============ */
        .specs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.75rem;
            margin-top: 1.25rem;
        }
        .spec {
            background: var(--bg-secondary);
            padding: 0.75rem;
            border-radius: var(--radius-md);
            text-align: center;
            transition: background-color 0.25s ease;
        }
        .spec i { font-size: 1.25rem; color: var(--primary); display: block; margin-bottom: 0.25rem; }
        .spec-value { font-weight: 700; font-size: 1.1rem; color: var(--text-primary); }
        .spec-label { font-size: 0.75rem; color: var(--text-secondary); }

        /* ============ DESCRIPTION ============ */
        .description { color: var(--text-secondary); font-size: 0.95rem; line-height: 1.7; }

        /* ============ AMENITIES ============ */
        .amenities {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 0.5rem;
        }
        .amenity {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 0.75rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            color: var(--text-primary);
            transition: background-color 0.25s ease;
        }
        .amenity i { color: var(--success); }

        /* ============ CONTACT ============ */
        .contact-box { position: sticky; top: 80px; }
        .contact-price {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }
        .contact-cta {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            margin-top: 1rem;
        }
        .contact-cta .btn { width: 100%; padding: 0.85rem; }

        .landlord-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            transition: background-color 0.25s ease;
        }
        .landlord-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .landlord-name { font-weight: 600; }
        .landlord-meta { font-size: 0.8rem; color: var(--text-secondary); }

        /* ============ ✅ GUEST: INQUIRY FORM STYLES ============ */
        .inquiry-form {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        .inquiry-form h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .inquiry-form label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-primary);
            display: block;
            margin-bottom: 0.25rem;
        }
        .inquiry-form input[type="text"],
        .inquiry-form input[type="tel"],
        .inquiry-form input[type="email"],
        .inquiry-form textarea {
            width: 100%;
            padding: 0.55rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.85rem;
            font-family: inherit;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.25s ease;
        }
        .inquiry-form input::placeholder,
        .inquiry-form textarea::placeholder {
            color: var(--text-tertiary);
        }
        .inquiry-form input:focus,
        .inquiry-form textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.18);
        }
        .inquiry-form textarea { resize: vertical; min-height: 70px; }
        .inquiry-form .field-error {
            font-size: 0.72rem;
            color: var(--danger);
            margin-top: 0.2rem;
        }
        .inquiry-form .form-note {
            font-size: 0.7rem;
            color: var(--text-secondary);
            text-align: center;
            line-height: 1.5;
            margin-top: 0.25rem;
        }
        .inquiry-form .form-note i { color: var(--success); }
        .inquiry-form .honeypot {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
            overflow: hidden;
            opacity: 0;
        }

        /* Inline flash messages inside the sidebar */
        .inquiry-flash {
            padding: 0.6rem 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: flex-start;
            gap: 0.4rem;
        }
        .inquiry-flash--success {
            background: rgba(var(--success-rgb), 0.12);
            color: var(--success);
            border-left: 3px solid var(--success);
        }
        .inquiry-flash--error {
            background: rgba(var(--danger-rgb), 0.12);
            color: var(--danger);
            border-left: 3px solid var(--danger);
        }

        /* ============ GALLERY ============ */
        .gallery-strip {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            padding: 0.5rem 0;
            margin-top: 1rem;
        }
        .gallery-thumb {
            flex-shrink: 0;
            width: 80px;
            height: 60px;
            border-radius: var(--radius-md);
            overflow: hidden;
            border: 2px solid transparent;
            cursor: pointer;
            background: var(--bg-secondary);
            transition: border-color 0.2s ease, background-color 0.25s ease;
        }
        .gallery-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .gallery-thumb.active { border-color: var(--primary); }
        .gallery-thumb:hover { border-color: var(--border-strong); }
        .gallery-thumb.active:hover { border-color: var(--primary); }

        /* ============ SIMILAR UNITS ============ */
        .similar-section {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        .similar-heading {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-primary);
        }
        .similar-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 640px) { .similar-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 900px) { .similar-grid { grid-template-columns: repeat(3, 1fr); } }
        .similar-card {
            display: block;
            text-decoration: none;
            color: inherit;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: all 0.2s ease, background-color 0.25s ease, border-color 0.25s ease;
        }
        .similar-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: rgba(var(--primary-rgb), 0.5);
        }
        .similar-card-img {
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            background: var(--bg-secondary);
            display: block;
        }
        .similar-card-img--empty {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: rgba(255,255,255,0.5);
            font-size: 2rem;
        }
        .similar-card-body { padding: 0.9rem; }
        .similar-card-title {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.2rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-primary);
        }
        .similar-card-location {
            font-size: 0.78rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.3rem;
            margin-bottom: 0.6rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .similar-card-price {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
        }
        .similar-card-price small {
            font-size: 0.72rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        /* ============ FOOTER ============ */
        .footer {
            margin-top: 2rem;
            padding: 1.5rem 0;
            border-top: 1px solid var(--border-color);
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.85rem;
            background: var(--card-bg);
            transition: background-color 0.25s ease, border-color 0.25s ease;
        }

        /* ============ MISC SHARED STYLES ============ */
        /* Some inline-styled elements need theme-aware colors.
           We override them here so the inline light-hardcoded colors
           (e.g. text-secondary) still work but the important bits
           (background, borders) adapt to dark. */
        [data-theme="dark"] hr {
            border-top-color: var(--border-color) !important;
        }
        [data-theme="dark"] a[style*="color: var(--primary)"] {
            color: var(--primary) !important;
        }

        /* ============ RESPONSIVE ============ */
        @media (max-width: 600px) {
            .unit-title { font-size: 1.4rem; }
            .price-line { font-size: 1.3rem; }
            .hero { aspect-ratio: 4 / 3; }
            .hero-region-badge { display: none; }

            /* On small screens, keep the theme toggle reachable but
               shrink the topbar actions so they don't wrap awkwardly */
            .topbar-actions { gap: 0.4rem; }
            .topbar-actions .btn { padding: 0.5rem 0.75rem; font-size: 0.8rem; }
            .theme-toggle { width: 34px; height: 34px; font-size: 0.9rem; }
        }
    </style>
</head>
<body>

    {{-- ============ TOPBAR ============ --}}
    <header class="topbar">
        <div class="container topbar-inner">
            <a href="{{ url('/') }}" class="brand">
                @if($logo = $systemSettings->getLogoUrl())
                    <img src="{{ $logo }}"
                         alt="{{ $systemSettings->system_name ?? 'Logo' }}"
                         onerror="this.style.display='none'; this.nextElementSibling?.style.setProperty('display','inline-block');">
                    <i class="fas fa-building" style="display:none;"></i>
                @else
                    <i class="fas fa-building"></i>
                @endif
                <span>{{ $systemSettings->system_name ?? 'Community Portal' }}</span>
            </a>
            <div class="topbar-actions">

                {{-- 🌗 THEME TOGGLE --}}
                <button type="button"
                        class="theme-toggle"
                        id="theme-toggle"
                        aria-label="Toggle dark mode"
                        title="Toggle dark mode">
                    <i class="fas fa-moon icon-moon" aria-hidden="true"></i>
                    <i class="fas fa-sun icon-sun" aria-hidden="true"></i>
                </button>

                <a href="{{ url('/') }}" class="btn btn-outline">
                    <i class="fas fa-home"></i> Home
                </a>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </a>
                @endauth
            </div>
        </div>
    </header>

    {{-- ============ HERO ============ --}}
    <div class="container">
        <div class="hero">
            @if($coverUrl)
                <img id="hero-image" src="{{ $coverUrl }}" alt="{{ $unitLabel }}">
            @else
                <div class="hero-empty">
                    <i class="fas fa-building"></i>
                </div>
            @endif

            <span class="hero-badge {{ $isAvailable ? 'hero-badge--available' : 'hero-badge--unavailable' }}">
                @if($isAvailable)
                    <i class="fas fa-check-circle"></i> Available Now
                @else
                    <i class="fas fa-times-circle"></i> Currently Unavailable
                @endif
            </span>

            @if($propertyRegion)
                <span class="hero-region-badge" title="{{ $propertyRegion }}">
                    <i class="fas fa-globe-africa"></i> {{ $propertyRegion }}
                </span>
            @endif
        </div>

        @if($allPhotos->count() > 1)
            <div class="gallery-strip">
                @foreach($allPhotos as $i => $p)
                    <div class="gallery-thumb {{ $i === 0 ? 'active' : '' }}"
                         data-url="{{ $p['url'] }}"
                         onclick="swapHero(this)">
                        <img src="{{ $p['thumb'] }}" alt="Photo {{ $i + 1 }}">
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ============ CONTENT ============ --}}
    <main class="container">
        <div class="content-grid">

            {{-- MAIN --}}
            <div class="space-y">

                <div class="card">
                    <div class="card-body">
                        <h1 class="unit-title">{{ $unitLabel }}</h1>

                        <p class="unit-location">
                            <i class="fas fa-map-marker-alt"></i>
                            {{ $propertyName }}{{ $locationSummary ? ' · ' . $locationSummary : '' }}
                        </p>

                        @if($propertyRegion || $propertyArea)
                            <div class="location-pills">
                                @if($propertyArea)
                                    <span class="location-pill">
                                        <i class="fas fa-map-pin"></i> {{ $propertyArea }}
                                    </span>
                                @endif
                                @if($propertyRegion)
                                    <span class="location-pill location-pill--region">
                                        <i class="fas fa-globe-africa"></i> {{ $propertyRegion }}
                                    </span>
                                @endif
                            </div>
                        @endif

                        @if($rentFormatted)
                            <div class="price-line">
                                {{ $rentFormatted }} <small>/ month</small>
                            </div>
                        @endif

                        <div class="specs">
                            @if($unit->bedrooms)
                                <div class="spec">
                                    <i class="fas fa-bed"></i>
                                    <div class="spec-value">{{ $unit->bedrooms }}</div>
                                    <div class="spec-label">Bedrooms</div>
                                </div>
                            @endif
                            @if($unit->bathrooms)
                                <div class="spec">
                                    <i class="fas fa-bath"></i>
                                    <div class="spec-value">{{ $unit->bathrooms }}</div>
                                    <div class="spec-label">Bathrooms</div>
                                </div>
                            @endif
                            @if($unit->living_rooms)
                                <div class="spec">
                                    <i class="fas fa-couch"></i>
                                    <div class="spec-value">{{ $unit->living_rooms }}</div>
                                    <div class="spec-label">Living Rooms</div>
                                </div>
                            @endif
                            @if($unit->floor_area)
                                <div class="spec">
                                    <i class="fas fa-ruler-combined"></i>
                                    <div class="spec-value">{{ $unit->floor_area }}</div>
                                    <div class="spec-label">sq ft</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if($unit->description)
                    <div class="card">
                        <div class="card-body">
                            <h2 class="card-title">
                                <i class="fas fa-align-left"></i>
                                About this unit
                            </h2>
                            <p class="description">{{ $unit->description }}</p>
                        </div>
                    </div>
                @endif

                @if(!empty($unit->amenities))
                    @php
                        $unitAmenities = is_array($unit->amenities) ? $unit->amenities : (json_decode($unit->amenities, true) ?: []);
                    @endphp
                    @if(!empty($unitAmenities))
                        <div class="card">
                            <div class="card-body">
                                <h2 class="card-title">
                                    <i class="fas fa-concierge-bell"></i>
                                    Amenities
                                </h2>
                                <div class="amenities">
                                    @foreach($unitAmenities as $amenity)
                                        @php
                                            [$icon, $label] = $amenityIcons[$amenity] ?? ['fa-check-circle', ucfirst(str_replace('_', ' ', $amenity))];
                                        @endphp
                                        <div class="amenity">
                                            <i class="fas {{ $icon }}"></i>
                                            {{ $label }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            {{-- SIDEBAR --}}
            <aside>
                <div class="card contact-box">
                    <div class="card-body">
                        @if($rentFormatted)
                            <div class="contact-price">{{ $rentFormatted }}</div>
                            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
                                per month · security deposit may apply
                            </p>
                        @endif

                        @if($shortLocation)
                            <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 1rem;">
                                <i class="fas fa-map-marker-alt" style="color: var(--info);"></i>
                                {{ $shortLocation }}
                            </p>
                        @endif

                        {{-- Landlord card --}}
                        @if($property?->landlord)
                            @php
                                $landlordName = $property->landlord->name ?? 'Property Owner';
                                $initials = collect(explode(' ', $landlordName))
                                    ->map(fn($p) => strtoupper(substr($p, 0, 1)))
                                    ->take(2)
                                    ->implode('');
                            @endphp
                            <div class="landlord-info">
                                <div class="landlord-avatar">{{ $initials }}</div>
                                <div>
                                    <div class="landlord-name">{{ $landlordName }}</div>
                                    <div class="landlord-meta">
                                        @if($landlordVerified)
                                            <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                            Verified landlord
                                        @else
                                            <i class="fas fa-user" style="color: var(--text-secondary);"></i>
                                            Landlord
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- ============================================================
                             ✅ GUEST: CONTACT / INQUIRY FORM
                             ============================================================ --}}
                        @if($isAvailable)
                            {{-- Inline flash for the inquiry form (only inside this sidebar) --}}
                            @if(session('inquiry_success'))
                                <div class="inquiry-flash inquiry-flash--success">
                                    <i class="fas fa-check-circle" style="margin-top: 2px;"></i>
                                    <span>{{ session('inquiry_success') }}</span>
                                </div>
                            @endif
                            @if(session('inquiry_error'))
                                <div class="inquiry-flash inquiry-flash--error">
                                    <i class="fas fa-exclamation-circle" style="margin-top: 2px;"></i>
                                    <span>{{ session('inquiry_error') }}</span>
                                </div>
                            @endif

                            <form method="POST"
                                  action="{{ route('property-units.inquiry.store', $unit->id) }}"
                                  class="inquiry-form"
                                  id="inquiry-form">
                                @csrf

                                {{-- Anti-spam honeypot — hidden from real users --}}
                                <div class="honeypot" aria-hidden="true">
                                    <label for="website">Website</label>
                                    <input type="text" name="website" id="website"
                                           tabindex="-1" autocomplete="off">
                                </div>

                                {{-- Anti-spam timing check — read by the controller --}}
                                <input type="hidden" name="_rendered_at" value="{{ now()->timestamp }}">

                                <h3>
                                    <i class="fas fa-paper-plane" style="color: var(--primary);"></i>
                                    Contact Landlord
                                </h3>

                                @auth
                                    {{-- Logged-in: contact pre-filled, only message needed --}}
                                    <input type="hidden" name="name"  value="{{ auth()->user()->name }}">
                                    <input type="hidden" name="phone" value="{{ auth()->user()->phone }}">
                                    <input type="hidden" name="email" value="{{ auth()->user()->email }}">

                                    <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                        Sending as
                                        <strong style="color: var(--text-primary);">{{ auth()->user()->name }}</strong>
                                        <span style="color: var(--text-secondary);">·</span>
                                        {{ auth()->user()->phone }}
                                    </div>
                                @else
                                    <div>
                                        <label for="inq-name">Your Name *</label>
                                        <input type="text" name="name" id="inq-name"
                                               value="{{ old('name') }}"
                                               required maxlength="100"
                                               placeholder="e.g., Kwame Osei"
                                               autocomplete="name">
                                        @error('name')
                                            <p class="field-error">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="inq-phone">Phone Number *</label>
                                        <input type="tel" name="phone" id="inq-phone"
                                               value="{{ old('phone') }}"
                                               required maxlength="20"
                                               placeholder="e.g., 024 123 4567"
                                               autocomplete="tel">
                                        @error('phone')
                                            <p class="field-error">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="inq-email">
                                            Email
                                            <span style="color: var(--text-secondary); font-weight: 400;">(optional)</span>
                                        </label>
                                        <input type="email" name="email" id="inq-email"
                                               value="{{ old('email') }}"
                                               maxlength="150"
                                               placeholder="you@example.com"
                                               autocomplete="email">
                                        @error('email')
                                            <p class="field-error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endauth

                                <div>
                                    <label for="inq-message">Message *</label>
                                    <textarea name="message" id="inq-message"
                                              required minlength="10" maxlength="2000"
                                              placeholder="Hi, I'm interested in this unit. Is it still available?">{{ old('message') }}</textarea>
                                    @error('message')
                                        <p class="field-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Channel availability hint --}}
                                @php
                                    $availableChannels = array_filter([
                                        'SMS'      => $channelsAvailable['sms']      ?? false,
                                        'Email'    => $channelsAvailable['email']    ?? false,
                                        'WhatsApp' => $channelsAvailable['whatsapp'] ?? false,
                                    ]);
                                @endphp

                                <button type="submit" class="btn btn-primary" id="inquiry-submit">
                                    <i class="fas fa-paper-plane"></i> Send Inquiry
                                </button>

                                <p class="form-note">
                                    <i class="fas fa-lock"></i>
                                    Your contact details are only shared with the landlord.
                                    @if(!empty($availableChannels))
                                        Delivered via {{ implode(', ', array_keys($availableChannels)) }}.
                                    @endif
                                </p>
                            </form>
                        @else
                            {{-- Unit is not available — show the closed state --}}
                            <div class="contact-cta">
                                <div class="btn btn-outline" style="cursor: not-allowed; opacity: 0.6;">
                                    <i class="fas fa-ban"></i> No Longer Available
                                </div>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); text-align: center;">
                                    This unit has been rented. Browse other listings on our homepage.
                                </p>
                                <a href="{{ url('/') }}" class="btn btn-outline">
                                    <i class="fas fa-search"></i> Browse Other Units
                                </a>
                            </div>
                        @endif

                        <hr style="margin: 1.25rem 0; border: none; border-top: 1px solid var(--border-color);">

                        <p style="font-size: 0.75rem; color: var(--text-secondary); text-align: center; line-height: 1.6;">
                            <i class="fas fa-shield-alt"></i>
                            All contact is handled through our secure platform.
                            Never pay a deposit without a signed agreement.
                        </p>

                        {{-- Support fallback (always visible) --}}
                        <p style="font-size: 0.72rem; color: var(--text-secondary); text-align: center; margin-top: 0.75rem;">
                            Having trouble?
                            <a href="mailto:{{ $systemSettings->support_email ?? 'support@yourdomain.com' }}"
                               style="color: var(--primary); text-decoration: underline;">
                                Contact support
                            </a>
                        </p>
                    </div>
                </div>
            </aside>
        </div>

        {{-- ============================================================
             SIMILAR UNITS
             ============================================================ --}}
        @if(($similarUnits ?? collect())->isNotEmpty())
            <section class="similar-section">
                <h2 class="similar-heading">
                    <i class="fas fa-th-large"></i>
                    Similar Units{{ $propertyRegion ? ' in ' . $propertyRegion : '' }}
                </h2>

                <div class="similar-grid">
                    @foreach($similarUnits as $similar)
                        @php
                            $simCover = null;
                            if ($similar->photos && $similar->photos->isNotEmpty()) {
                                $simPhoto = $similar->photos->firstWhere('is_cover', true)
                                    ?? $similar->photos->first();
                                try {
                                    $disk = $simPhoto->disk ?? 'public';
                                    $simCover = $simPhoto->thumbnail_path
                                        ? Storage::disk($disk)->url($simPhoto->thumbnail_path)
                                        : ($simPhoto->path ? Storage::disk($disk)->url($simPhoto->path) : null);
                                } catch (\Throwable $e) {
                                    $simCover = null;
                                }
                            }

                            $simLabel = $similar->unit_name ?? $similar->unit_number ?? 'Unit #' . $similar->id;

                            $simLocation = collect([
                                $similar->property?->area,
                                $similar->property?->street_name,
                                $similar->property?->zone,
                                $similar->property?->region,
                            ])->filter(fn ($v) => $v !== null && $v !== '')->implode(', ');

                            $simPrice = $similar->rent_formatted
                                ?? ($similar->monthly_rent
                                    ? $systemSettings->formatAmount($similar->monthly_rent)
                                    : null);
                        @endphp

                        <a href="{{ route('property-units.public-show', $similar->id) }}"
                           class="similar-card">
                            @if($simCover)
                                <img src="{{ $simCover }}"
                                     alt="{{ $simLabel }}"
                                     class="similar-card-img"
                                     loading="lazy"
                                     decoding="async">
                            @else
                                <div class="similar-card-img similar-card-img--empty">
                                    <i class="fas fa-building"></i>
                                </div>
                            @endif

                            <div class="similar-card-body">
                                <div class="similar-card-title">{{ $simLabel }}</div>
                                <div class="similar-card-location" title="{{ $simLocation ?: 'Location not specified' }}">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span style="overflow: hidden; text-overflow: ellipsis;">
                                        {{ $simLocation ?: 'Location not specified' }}
                                    </span>
                                </div>
                                @if($simPrice)
                                    <div class="similar-card-price">
                                        {{ $simPrice }} <small>/ month</small>
                                    </div>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </main>

    {{-- ============ FOOTER ============ --}}
    <footer class="footer">
        <div class="container">
            <p>
                {{ '© ' . date('Y') . ' ' . ($systemSettings->system_name ?? 'Community Portal') . '. All rights reserved.' }}
            </p>
        </div>
    </footer>

    <script>
    function swapHero(thumb) {
        const url = thumb.dataset.url;
        const hero = document.getElementById('hero-image');
        if (url && hero) {
            hero.src = url;
            document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
            thumb.classList.add('active');
        }
    }

    // 🌗 THEME TOGGLE
    (function () {
        const btn = document.getElementById('theme-toggle');
        if (!btn) return;

        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.style.colorScheme = theme;
            try {
                localStorage.setItem('publicUnitTheme', theme);
            } catch (e) { /* localStorage blocked — silently ignore */ }
        }

        btn.addEventListener('click', function () {
            const current = document.documentElement.getAttribute('data-theme') || 'light';
            applyTheme(current === 'dark' ? 'light' : 'dark');
        });
    })();

    // ✅ GUEST: prevent double-submits on the inquiry form
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('inquiry-form');
        const submit = document.getElementById('inquiry-submit');
        if (!form || !submit) return;

        form.addEventListener('submit', function () {
            submit.disabled = true;
            submit.style.opacity = '0.6';
            submit.style.cursor = 'wait';
            submit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
        });

        // If the browser restores the page from cache (back button),
        // re-enable the button so the user isn't stuck with a dead form.
        window.addEventListener('pageshow', function () {
            submit.disabled = false;
            submit.style.opacity = '';
            submit.style.cursor = '';
            submit.innerHTML = '<i class="fas fa-paper-plane"></i> Send Inquiry';
        });
    });
    </script>
</body>
</html>