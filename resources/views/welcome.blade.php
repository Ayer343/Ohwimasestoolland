{{-- ⭐ ADDED: Defensive defaults so the view never breaks if $systemSettings or $stats are missing --}}
@php
    if (!isset($systemSettings)) {
        $systemSettings = (object) [
            'system_name'   => 'Community Portal',
            'system_email'  => 'support@example.com',
            'system_phone'  => '+233 123 456 789',
            'system_logo'   => null,
        ];
    }
    $stats = $stats ?? [
        'total_properties'   => 0,
        'under_construction' => 0,
        'total_tenants'      => 0,
        'total_landlords'    => 0,
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="{{ $systemSettings->system_name ?? 'Community Portal' }} - Register your property, manage estate services, and connect with your community">
    <meta name="keywords" content="property registration, estate management, community portal, land registration">
    <meta name="author" content="{{ $systemSettings->system_name ?? 'Community Portal' }}">
    
    <!-- ============ FAVICON ============ -->
    @if(isset($systemSettings) && method_exists($systemSettings, 'hasFavicon') && $systemSettings->hasFavicon())
        <link rel="icon" href="{{ $systemSettings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ $systemSettings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="apple-touch-icon" href="{{ $systemSettings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ $systemSettings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ $systemSettings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="64x64" href="{{ $systemSettings->getFaviconUrl() }}">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    @endif
    
    <!-- ============ CRITICAL: Theme initialization - PREVENTS FLASH ============ -->
    <script>
        (function() {
            try {
                let theme = localStorage.getItem('theme');
                if (!theme) {
                    theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                const html = document.documentElement;
                html.setAttribute('data-theme', theme);
                if (theme === 'dark') {
                    html.style.backgroundColor = '#1a1a2e';
                } else {
                    html.style.backgroundColor = '#ffffff';
                }
                window.__INITIAL_THEME = theme;
                html.classList.add('theme-loading');
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
                document.documentElement.classList.add('theme-loading');
            }
        })();
    </script>
    
    <!-- ============ CRITICAL: Inline CSS to prevent flash ============ -->
    <style>
        .theme-loading *,
        .theme-loading *::before,
        .theme-loading *::after {
            transition: none !important;
        }
        html, body {
            background-color: var(--bg-primary, #ffffff);
            min-height: 100vh;
        }
        [data-theme="dark"] html,
        [data-theme="dark"] body {
            background-color: #1a1a2e !important;
        }
        [data-theme="light"] html,
        [data-theme="light"] body {
            background-color: #ffffff !important;
        }
        [x-cloak] { display: none !important; }
        
        /* CSS Variables for themes */
        [data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --text-primary: #1a1a2e;
            --text-secondary: #6c757d;
            --card-bg: #ffffff;
            --border-color: #e9ecef;
            --primary: #3b82f6;
            --primary-rgb: 59, 130, 246;
            --secondary: #8b5cf6;
            --secondary-rgb: 139, 92, 246;
            --danger: #ef4444;
            --danger-rgb: 239, 68, 68;
            --success: #10b981;
            --success-rgb: 16, 185, 129;
            --warning: #f59e0b;
            --warning-rgb: 245, 158, 11;
            --info: #06b6d4;
            --info-rgb: 6, 182, 212;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --touch-target-size: 44px;
        }
        
        [data-theme="dark"] {
            --bg-primary: #1a1a2e;
            --bg-secondary: #16213e;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --card-bg: #1e293b;
            --border-color: #334155;
            --primary: #60a5fa;
            --primary-rgb: 96, 165, 250;
            --secondary: #a78bfa;
            --secondary-rgb: 167, 139, 250;
            --danger: #f87171;
            --danger-rgb: 248, 113, 113;
            --success: #34d399;
            --success-rgb: 52, 211, 153;
            --warning: #fbbf24;
            --warning-rgb: 251, 191, 36;
            --info: #22d3ee;
            --info-rgb: 34, 211, 238;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --touch-target-size: 44px;
        }
    </style>
    
    <title>{{ $systemSettings->system_name ?? 'Community Portal' }} | Property Registration & Estate Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="{{ asset('css/welcome.css') }}">
    
    <style>
        /* ============================================
           RESET & BASE STYLES
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        html {
            scroll-behavior: smooth;
            -webkit-text-size-adjust: 100%;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        a, button, input, select, textarea {
            touch-action: manipulation;
        }
        
        button {
            cursor: pointer;
            -webkit-touch-callout: none;
            user-select: none;
        }
        
        /* ============================================
           CONTAINER
           ============================================ */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            width: 100%;
        }
        
        /* ============================================
           HEADER - FULLY RESPONSIVE
           ============================================ */
        header {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 0.75rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
            background: rgba(var(--card-bg-rgb, 255, 255, 255), 0.95);
        }
        
        [data-theme="dark"] header {
            background: rgba(30, 41, 59, 0.95);
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
            min-height: var(--touch-target-size);
            padding: 0.25rem 0;
        }
        
        .logo i {
            font-size: 1.5rem;
            color: var(--primary);
        }
        
        .logo-text {
            white-space: nowrap;
        }
        
        .logo-image {
            height: 40px;
            width: auto;
            max-width: 150px;
            object-fit: contain;
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            padding: 0.75rem;
            min-width: var(--touch-target-size);
            min-height: var(--touch-target-size);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            transition: background 0.2s ease;
            touch-action: manipulation;
        }
        
        .mobile-menu-btn:active {
            background: var(--bg-secondary);
        }
        
        nav {
            display: flex;
            align-items: center;
        }
        
        nav ul {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            list-style: none;
            flex-wrap: wrap;
        }
        
        nav ul li {
            display: flex;
            align-items: center;
        }
        
        nav ul li a {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.6rem 0.8rem;
            border-radius: var(--radius-md);
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            min-height: var(--touch-target-size);
            touch-action: manipulation;
        }
        
        nav ul li a:hover {
            background: var(--bg-secondary);
            color: var(--primary);
        }
        
        nav ul li a:active {
            transform: scale(0.96);
        }
        
        .login-btn {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white !important;
            padding: 0.6rem 1.2rem !important;
            border-radius: 50px !important;
            font-weight: 600 !important;
            min-height: var(--touch-target-size);
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
        }
        
        .login-btn:active {
            transform: scale(0.96);
        }
        
        .theme-toggle {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            padding: 0.5rem 1rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            min-height: var(--touch-target-size);
            min-width: 80px;
            justify-content: center;
            touch-action: manipulation;
        }
        
        .theme-toggle:active {
            transform: scale(0.95);
        }
        
        .theme-toggle i {
            font-size: 1rem;
        }
        
        .theme-toggle .fa-sun { color: #f39c12; }
        .theme-toggle .fa-moon { color: #8b5cf6; }
        
        [data-theme="light"] .theme-toggle .fa-moon { display: inline-block; }
        [data-theme="light"] .theme-toggle .fa-sun { display: none; }
        [data-theme="dark"] .theme-toggle .fa-sun { display: inline-block; }
        [data-theme="dark"] .theme-toggle .fa-moon { display: none; }
        
        .mobile-menu-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            touch-action: none;
        }
        
        .mobile-menu-overlay.active {
            display: block;
        }
        
        /* ============================================
           HERO SECTION - RESPONSIVE
           ============================================ */
        .hero {
            min-height: 75vh;
            position: relative;
            background: linear-gradient(135deg, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0.75) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-4.0.3&auto=format&fit=crop&w=1773&q=80');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0.35;
            z-index: 0;
        }
        
        [data-theme="dark"] .hero::before {
            opacity: 0.25;
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            width: 100%;
        }
        
        .hero-content h1 {
            font-size: clamp(2rem, 6vw, 3.8rem);
            font-weight: 700;
            background: linear-gradient(135deg, #fff 0%, #e0e0e0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
            line-height: 1.2;
        }
        
        .hero-content p {
            font-size: clamp(1rem, 2vw, 1.3rem);
            color: rgba(255, 255, 255, 0.9);
            max-width: 700px;
            margin: 0 auto 2rem;
            padding: 0 1rem;
        }
        
        .hero-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            padding: 0 0.5rem;
        }
        
        .hero-buttons .btn {
            min-height: var(--touch-target-size);
            min-width: 200px;
            padding: 0.75rem 1.5rem;
            font-size: clamp(0.9rem, 1.5vw, 1.1rem);
        }
        
        /* ============================================
           BUTTONS - LARGE TOUCH TARGETS
           ============================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            border: none;
            text-decoration: none;
            min-height: var(--touch-target-size);
            min-width: 48px;
            touch-action: manipulation;
            font-size: 1rem;
            -webkit-touch-callout: none;
            user-select: none;
        }
        
        .btn:active {
            transform: scale(0.96) !important;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--border-color);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .btn-outline:hover {
            background: var(--bg-secondary);
        }
        
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            min-height: 36px;
        }
        
        .register-btn-large {
            padding: 0.75rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.3);
            min-height: var(--touch-target-size);
            min-width: 220px;
        }
        
        .register-btn-large:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(var(--primary-rgb), 0.4);
        }
        
        .btn-modern {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 1rem 2.5rem;
            font-size: 1.2rem;
            border-radius: 50px;
            min-height: var(--touch-target-size);
            min-width: 200px;
        }
        
        .testimonial-form-btn {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            min-height: var(--touch-target-size);
            min-width: 200px;
        }
        
        .testimonial-form-btn:hover {
            background: rgba(255, 255, 255, 0.25);
        }
        
        /* ============================================
           SECTIONS - RESPONSIVE
           ============================================ */
        section {
            padding: 4rem 0;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .section-title h2 {
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .section-title p {
            font-size: clamp(0.95rem, 1.5vw, 1.2rem);
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 2rem 1.5rem;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            min-height: 220px;
        }
        
        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }
        
        .feature-card:active {
            transform: scale(0.98);
        }
        
        .feature-card i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .feature-card h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        
        .feature-card p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .benefit-item {
            text-align: center;
            padding: 2rem 1.5rem;
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            min-height: 200px;
        }
        
        .benefit-item:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }
        
        .benefit-item:active {
            transform: scale(0.98);
        }
        
        .benefit-item i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .benefit-item h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        
        .benefit-item p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .step-circle {
            width: 60px;
            height: 60px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 1rem;
            min-width: 60px;
            min-height: 60px;
        }
        
        /* ============================================
           STATS SECTION - RESPONSIVE
           ============================================ */
        .stats {
            position: relative;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
            padding: 4rem 0;
        }
        
        .stats::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-4.0.3&auto=format&fit=crop&w=1773&q=80');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            opacity: 0.08;
            z-index: 0;
        }
        
        [data-theme="dark"] .stats::before {
            opacity: 0.05;
        }
        
        .stats .container {
            position: relative;
            z-index: 1;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 2rem 1rem;
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            min-height: 160px;
        }
        
        .stat-item:active {
            transform: scale(0.98);
        }
        
        .stat-item i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .stat-number {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-item p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        /* ============================================
           TESTIMONIALS - RESPONSIVE
           ============================================ */
        .testimonials-carousel {
            position: relative;
            overflow: hidden;
            padding: 1rem 0;
            margin: 0 -0.5rem;
        }
        
        .testimonials-track {
            display: flex;
            gap: 1.5rem;
            transition: transform 0.5s ease-in-out;
            will-change: transform;
            padding: 0.5rem 0;
        }
        
        .testimonial-slide {
            flex: 0 0 calc(33.333% - 1rem);
            min-width: calc(33.333% - 1rem);
        }
        
        .testimonial-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            height: 100%;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        
        .testimonial-card:active {
            transform: scale(0.98);
        }
        
        .testimonial-rating {
            color: #f39c12;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            letter-spacing: 2px;
        }
        
        .testimonial-content {
            font-style: italic;
            margin-bottom: 1rem;
            line-height: 1.6;
            flex-grow: 1;
            font-size: 0.95rem;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        
        .author-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        
        .author-info {
            flex: 1;
            min-width: 0;
        }
        
        .author-name {
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .author-role {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            touch-action: manipulation;
            min-width: 44px;
            min-height: 44px;
        }
        
        .carousel-btn:active {
            transform: translateY(-50%) scale(0.92);
        }
        
        .carousel-btn.prev {
            left: -8px;
        }
        
        .carousel-btn.next {
            right: -8px;
        }
        
        .carousel-dots {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .carousel-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--border-color);
            cursor: pointer;
            transition: all 0.3s ease;
            touch-action: manipulation;
            min-width: 12px;
            min-height: 12px;
        }
        
        .carousel-dot:active {
            transform: scale(0.8);
        }
        
        .carousel-dot.active {
            background: var(--primary);
            width: 32px;
            border-radius: 6px;
        }
        
        .carousel-status {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .share-btn-container {
            text-align: center;
            margin-top: 2rem;
        }
        
        /* ============================================
           PAYMENT METHODS - RESPONSIVE
           ============================================ */
        .payment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
        }
        
        .payment-method {
            text-align: center;
            padding: 2rem 1rem;
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            min-height: 140px;
        }
        
        .payment-method:active {
            transform: scale(0.98);
        }
        
        .payment-method i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .payment-method h3 {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }
        
        .payment-method p {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        
        /* ============================================
           CTA SECTION
           ============================================ */
        .cta {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            text-align: center;
            padding: 4rem 1.5rem;
        }
        
        .cta .section-title h2 {
            color: white;
        }
        
        .cta .section-title p {
            color: rgba(255, 255, 255, 0.9);
        }
        
        .cta .btn {
            background: white;
            color: var(--primary);
            border: none;
        }
        
        .cta .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        .cta .btn:active {
            transform: scale(0.96);
        }
        
        /* ============================================
           FOOTER - RESPONSIVE
           ============================================ */
        .footer {
            background: var(--bg-secondary);
            padding: 3rem 0 2rem;
            border-top: 1px solid var(--border-color);
        }
        
        .footer .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
        }
        
        .social-icons-footer {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .social-icons-footer a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--card-bg);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 1.1rem;
            min-width: 44px;
            min-height: 44px;
        }
        
        .social-icons-footer a:active {
            transform: scale(0.9);
        }
        
        .social-icons-footer a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1.5rem;
            justify-content: center;
        }
        
        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s ease;
            padding: 0.5rem 0;
            min-height: var(--touch-target-size);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .footer-links a:active {
            color: var(--primary);
        }
        
        .footer-links a:hover {
            color: var(--primary);
        }
        
        .footer-copyright {
            color: var(--text-secondary);
            font-size: 0.85rem;
            text-align: center;
        }
        
        /* ============================================
           MODALS - RESPONSIVE
           ============================================ */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            max-width: 800px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            margin: 0 auto;
        }
        
        .modal-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: var(--card-bg);
            z-index: 10;
            gap: 0.5rem;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: clamp(1.1rem, 2.5vw, 1.4rem);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            padding: 0.5rem;
            min-width: var(--touch-target-size);
            min-height: var(--touch-target-size);
            border-radius: var(--radius-md);
            transition: background 0.2s ease;
        }
        
        .modal-close:active {
            background: var(--bg-secondary);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        /* ============================================
           FORMS - RESPONSIVE
           ============================================ */
        .form-section {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            margin-bottom: 1.25rem;
        }
        
        .form-section-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary);
            display: inline-block;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        
        .form-group {
            margin-bottom: 0.75rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .form-group label.required::after {
            content: '*';
            color: var(--danger);
            margin-left: 0.25rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.95rem;
            min-height: var(--touch-target-size);
            -webkit-appearance: none;
            appearance: none;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
        }
        
        .form-control.error {
            border-color: var(--danger);
        }
        
        .error-message {
            color: var(--danger);
            font-size: 0.75rem;
            margin-top: 0.25rem;
            display: block;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        
        select.form-control {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='7'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236c757d' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            padding-right: 2.5rem;
        }
        
        .registration-type-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .reg-type-card {
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            touch-action: manipulation;
            min-height: 140px;
        }
        
        .reg-type-card:active {
            transform: scale(0.97);
        }
        
        .reg-type-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
        }
        
        .reg-type-card.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.1), rgba(var(--secondary-rgb), 0.05));
        }
        
        .reg-type-card i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .reg-type-card h4 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        
        .reg-type-card p {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .file-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            touch-action: manipulation;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .file-upload-area:active {
            transform: scale(0.98);
        }
        
        .file-upload-area:hover {
            border-color: var(--primary);
            background: rgba(var(--primary-rgb), 0.05);
        }
        
        .file-upload-area i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .file-upload-area p {
            margin: 0;
            font-size: 0.9rem;
        }
        
        .file-hint {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }
        
        .file-input {
            display: none;
        }
        
        .file-preview {
            margin-top: 0.75rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .file-preview-item {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.5rem 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            min-height: 40px;
        }
        
        .file-preview-item button {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            padding: 0.25rem;
            min-width: 30px;
            min-height: 30px;
            touch-action: manipulation;
        }
        
        .file-preview-item button:active {
            transform: scale(0.9);
        }
        
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-top: 0.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            margin-top: 0.15rem;
            flex-shrink: 0;
            min-width: 20px;
            min-height: 20px;
        }
        
        .checkbox-group label {
            margin-bottom: 0;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .tenant-entry {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .tenant-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .tenant-header h5 {
            margin: 0;
            color: var(--primary);
            font-size: 0.95rem;
        }
        
        .remove-tenant-btn {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            font-size: 0.85rem;
            padding: 0.5rem;
            min-height: var(--touch-target-size);
            touch-action: manipulation;
        }
        
        .remove-tenant-btn:active {
            transform: scale(0.9);
        }
        
        .add-tenant-btn {
            background: none;
            border: 1px dashed var(--border-color);
            color: var(--primary);
            padding: 0.75rem;
            width: 100%;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: var(--touch-target-size);
            touch-action: manipulation;
        }
        
        .add-tenant-btn:active {
            transform: scale(0.98);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
            position: sticky;
            bottom: 0;
            background: var(--card-bg);
            flex-wrap: wrap;
        }
        
        .form-actions .btn {
            min-height: var(--touch-target-size);
            padding: 0.75rem 1.5rem;
        }

        .preview-section {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            margin-bottom: 1.25rem;
        }
        
        .preview-section h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary);
            display: inline-block;
        }
        
        .preview-item {
            display: flex;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .preview-item:last-child {
            border-bottom: none;
        }
        
        .preview-label {
            font-weight: 500;
            color: var(--text-secondary);
            width: 35%;
            flex-shrink: 0;
            font-size: 0.85rem;
        }
        
        .preview-value {
            color: var(--text-primary);
            width: 65%;
            font-size: 0.9rem;
            word-break: break-word;
        }
        
        .preview-value .file-preview-item {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            margin: 0.15rem;
        }
        
        .preview-value .file-preview-item i {
            font-size: 0.8rem;
        }
        
        .preview-value .badge {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .preview-value .badge-success {
            background: rgba(var(--success-rgb), 0.15);
            color: var(--success);
        }
        
        .preview-value .badge-warning {
            background: rgba(var(--warning-rgb), 0.15);
            color: var(--warning);
        }
        
        .preview-value .badge-info {
            background: rgba(var(--info-rgb), 0.15);
            color: var(--info);
        }
        
        .preview-value .badge-primary {
            background: rgba(var(--primary-rgb), 0.15);
            color: var(--primary);
        }
        
        .preview-empty {
            color: var(--text-secondary);
            font-style: italic;
            font-size: 0.85rem;
        }
        
        .preview-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        
        .preview-actions .btn {
            min-height: var(--touch-target-size);
        }
        
        #previewModal .modal-content {
            max-width: 700px;
        }
        
        .toast-container {
            position: fixed;
            top: 80px;
            right: 16px;
            z-index: 1100;
            max-width: 90%;
            width: 400px;
            pointer-events: none;
        }
        
        .toast {
            background: var(--card-bg);
            border-left: 4px solid;
            border-radius: var(--radius-md);
            padding: 1rem 1.25rem;
            margin-bottom: 0.5rem;
            box-shadow: var(--shadow-lg);
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            pointer-events: auto;
            font-size: 0.9rem;
        }
        
        .toast.success { border-left-color: var(--success); }
        .toast.error { border-left-color: var(--danger); }
        .toast.warning { border-left-color: var(--warning); }
        .toast.info { border-left-color: var(--info); }
        
        .toast i {
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .toast.success i { color: var(--success); }
        .toast.error i { color: var(--danger); }
        .toast.warning i { color: var(--warning); }
        .toast.info i { color: var(--info); }
        
        .alert {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .alert-info {
            background: rgba(var(--info-rgb), 0.1);
            border: 1px solid rgba(var(--info-rgb), 0.3);
            color: var(--info);
        }
        
        .alert-success {
            background: rgba(var(--success-rgb), 0.1);
            border: 1px solid rgba(var(--success-rgb), 0.3);
            color: var(--success);
        }
        
        .alert-warning {
            background: rgba(var(--warning-rgb), 0.1);
            border: 1px solid rgba(var(--warning-rgb), 0.3);
            color: var(--warning);
        }
        
        .alert-danger {
            background: rgba(var(--danger-rgb), 0.1);
            border: 1px solid rgba(var(--danger-rgb), 0.3);
            color: var(--danger);
        }
        
        .rating-input {
            display: flex;
            gap: 0.5rem;
            cursor: pointer;
            flex-wrap: wrap;
        }
        
        .rating-input i {
            font-size: 1.75rem;
            transition: all 0.2s ease;
            cursor: pointer;
            touch-action: manipulation;
            min-width: 32px;
            min-height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .rating-input i:active {
            transform: scale(0.85);
        }
        
        .rating-input i:hover,
        .rating-input i.active {
            color: #f39c12;
            transform: scale(1.1);
        }
        
        .character-counter {
            text-align: right;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }
        
        .character-counter.warning {
            color: var(--warning);
        }
        
        .character-counter.danger {
            color: var(--danger);
        }
        
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: inline-block;
        }
        
        .loading-spinner {
            text-align: center;
            padding: 3rem;
        }
        
        .loading-spinner .spinner {
            width: 40px;
            height: 40px;
            border-width: 3px;
        }
        
        .rate-limit-info {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-align: center;
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: rgba(var(--warning-rgb), 0.1);
            border-radius: var(--radius-md);
        }
        
        .additional-phone-entry {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .additional-phone-entry .form-control {
            flex: 1;
        }
        
        .remove-phone-btn {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            padding: 0 0.5rem;
            min-height: var(--touch-target-size);
            min-width: var(--touch-target-size);
            touch-action: manipulation;
        }
        
        .remove-phone-btn:active {
            transform: scale(0.9);
        }

        /* ⭐ Floating Chat Widget Styles */
        .floating-chat-launcher {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(var(--primary-rgb), 0.4);
            z-index: 999;               /* below modals (z-index 1000) */
            transition: transform .2s ease, box-shadow .2s ease;
            touch-action: manipulation;
            border: none;
        }
        .floating-chat-launcher:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 28px rgba(var(--primary-rgb), 0.55);
        }
        .floating-chat-launcher:active { transform: scale(0.94); }

        .floating-chat-panel {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 380px;
            max-width: calc(100vw - 32px);
            height: 560px;
            max-height: calc(100vh - 120px);
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            box-shadow: 0 20px 50px rgba(0, 0, 0, .25);
            z-index: 999;               /* below modals (z-index 1000) */
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: translateY(20px) scale(0.96);
            opacity: 0;
            pointer-events: none;
            transition: transform .25s ease, opacity .25s ease;
        }
        .floating-chat-panel.open {
            transform: translateY(0) scale(1);
            opacity: 1;
            pointer-events: auto;
        }
        .floating-chat-panel__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .75rem 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
        }
        .floating-chat-panel__sub { font-size: .75rem; opacity: .9; }
        .floating-chat-panel__close {
            background: rgba(255,255,255,.15);
            border: none;
            color: #fff;
            width: 32px; height: 32px;
            border-radius: .5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .floating-chat-panel__body {
            flex: 1;
            overflow: hidden;
            padding: 0;
        }
        /* Override the widget's own sizing inside the floating panel */
        .floating-chat-panel__body .chat-widget {
            margin: 0;
            height: 100%;
            min-height: 0;
            border: none;
            border-radius: 0;
            box-shadow: none;
            max-width: 100%;
        }
        .floating-chat-panel__body .chat-widget__topics {
            max-height: 90px;
        }

        @media (max-width: 480px) {
            .floating-chat-panel {
                bottom: 0;
                right: 0;
                left: 0;
                width: 100%;
                height: 100vh;
                max-height: 100vh;
                border-radius: 0;
            }
            .floating-chat-launcher {
                bottom: 16px;
                right: 16px;
            }
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 1024px) {
            .testimonial-slide {
                flex: 0 0 calc(50% - 0.75rem);
                min-width: calc(50% - 0.75rem);
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            header {
                padding: 0.5rem 0;
            }
            
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            nav {
                position: fixed;
                top: 0;
                right: -100%;
                width: 75%;
                max-width: 320px;
                height: 100%;
                background: var(--card-bg);
                padding: 2rem 1.5rem;
                transition: right 0.3s ease;
                z-index: 999;
                box-shadow: var(--shadow-lg);
                overflow-y: auto;
                align-items: flex-start;
            }
            
            nav.active {
                right: 0;
            }
            
            nav ul {
                flex-direction: column;
                gap: 0.5rem;
                width: 100%;
                align-items: stretch;
            }
            
            nav ul li {
                width: 100%;
            }
            
            nav ul li a {
                width: 100%;
                padding: 0.75rem 1rem;
                justify-content: flex-start;
                font-size: 1rem;
                min-height: 48px;
            }
            
            .login-btn {
                justify-content: center !important;
                margin-top: 0.5rem;
            }
            
            .theme-toggle {
                width: 100%;
                justify-content: center;
                margin-top: 0.5rem;
                min-height: 48px;
            }
            
            .hero {
                min-height: 60vh;
                padding: 2rem 0.75rem;
            }
            
            .hero-content h1 {
                font-size: clamp(1.8rem, 5vw, 2.5rem);
            }
            
            .hero-buttons .btn {
                min-width: 160px;
                width: 100%;
                max-width: 300px;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            section {
                padding: 2.5rem 0;
            }
            
            .section-title {
                margin-bottom: 2rem;
            }
            
            .features-grid {
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }
            
            .benefits-grid {
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }
            
            .payment-grid {
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }
            
            .registration-type-cards {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .testimonial-slide {
                flex: 0 0 100%;
                min-width: 100%;
            }
            
            .carousel-btn {
                width: 36px;
                height: 36px;
                min-width: 36px;
                min-height: 36px;
            }
            
            .carousel-btn.prev {
                left: -4px;
            }
            
            .carousel-btn.next {
                right: -4px;
            }
            
            .modal-content {
                max-height: 95vh;
                border-radius: var(--radius-md);
            }
            
            .modal-body {
                padding: 1rem;
            }
            
            .form-section {
                padding: 1rem;
            }
            
            .footer-links {
                gap: 0.5rem 1rem;
            }
            
            .footer-links a {
                font-size: 0.8rem;
            }
            
            .toast-container {
                right: 8px;
                left: 8px;
                width: auto;
                max-width: 100%;
                top: 70px;
            }
            
            .stat-item {
                padding: 1.25rem 0.75rem;
                min-height: 120px;
            }
            
            .feature-card {
                padding: 1.25rem 0.75rem;
                min-height: 160px;
            }
            
            .benefit-item {
                padding: 1.25rem 0.75rem;
                min-height: 150px;
            }
            
            .payment-method {
                padding: 1.25rem 0.75rem;
                min-height: 110px;
            }
            
            .cta {
                padding: 3rem 1rem;
            }
            
            .cta .btn {
                width: 100%;
                max-width: 300px;
            }
            
            .form-actions {
                flex-direction: column-reverse;
            }
            
            .form-actions .btn {
                width: 100%;
                justify-content: center;
            }

            #previewModal .modal-content {
                max-width: 100%;
            }
            
            .preview-item {
                flex-direction: column;
                padding: 0.4rem 0;
            }
            
            .preview-label {
                width: 100%;
                margin-bottom: 0.15rem;
                font-size: 0.8rem;
            }
            
            .preview-value {
                width: 100%;
                font-size: 0.85rem;
            }
        }
        
        @media (max-width: 480px) {
            .logo-text {
                font-size: 0.9rem;
                max-width: 120px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            .logo-image {
                height: 32px;
                max-width: 100px;
            }
            
            .hero {
                min-height: 50vh;
                padding: 1.5rem 0.5rem;
            }
            
            .hero-content h1 {
                font-size: 1.8rem;
            }
            
            .hero-content p {
                font-size: 0.95rem;
                padding: 0 0.5rem;
            }
            
            .hero-buttons .btn {
                min-width: 140px;
                font-size: 0.9rem;
                padding: 0.6rem 1rem;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .benefits-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.75rem;
            }
            
            .payment-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.75rem;
            }
            
            .stat-number {
                font-size: 1.8rem;
            }
            
            .modal-content {
                max-width: 100%;
                border-radius: var(--radius-md);
            }
            
            .modal-header {
                padding: 0.75rem 1rem;
            }
            
            .modal-header h3 {
                font-size: 1rem;
            }
            
            .modal-body {
                padding: 0.75rem;
            }
            
            .form-section {
                padding: 0.75rem;
            }
            
            .file-upload-area {
                padding: 1rem;
                min-height: 80px;
            }
            
            .btn {
                font-size: 0.9rem;
                padding: 0.6rem 1rem;
                min-height: 40px;
            }
            
            .register-btn-large {
                min-width: 160px;
                font-size: 0.95rem;
                padding: 0.6rem 1.2rem;
            }
            
            .testimonial-form-btn {
                min-width: 160px;
                font-size: 0.9rem;
            }
            
            nav {
                width: 85%;
                max-width: 280px;
                padding: 1.5rem 1rem;
            }
            
            nav ul li a {
                font-size: 0.95rem;
                padding: 0.6rem 0.8rem;
            }
            
            .footer-links {
                flex-direction: column;
                align-items: center;
                gap: 0.25rem;
            }
            
            .footer-links a {
                padding: 0.4rem 0;
            }
            
            .social-icons-footer a {
                width: 40px;
                height: 40px;
                min-width: 40px;
                min-height: 40px;
                font-size: 1rem;
            }
            
            .section-title h2 {
                font-size: 1.5rem;
            }
            
            .section-title p {
                font-size: 0.9rem;
            }
            
            .carousel-btn {
                width: 32px;
                height: 32px;
                min-width: 32px;
                min-height: 32px;
                font-size: 0.8rem;
            }
            
            .rating-input i {
                font-size: 1.5rem;
            }
            
            .tenant-entry {
                padding: 0.75rem;
            }
            
            .reg-type-card {
                padding: 1rem;
                min-height: 100px;
            }
            
            .reg-type-card i {
                font-size: 2rem;
            }
            
            .step-circle {
                width: 48px;
                height: 48px;
                min-width: 48px;
                min-height: 48px;
                font-size: 1.2rem;
            }
        }
        
        @media (max-width: 360px) {
            .logo-text {
                font-size: 0.8rem;
                max-width: 80px;
            }
            
            .hero-content h1 {
                font-size: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .payment-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            .btn {
                font-size: 0.8rem;
                padding: 0.5rem 0.8rem;
                min-height: 36px;
            }
            
            .register-btn-large {
                min-width: 140px;
                font-size: 0.85rem;
            }
        }
        
        .hidden {
            display: none !important;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mt-1 { margin-top: 0.5rem; }
        .mt-2 { margin-top: 1rem; }
        .mt-3 { margin-top: 1.5rem; }
        .mb-1 { margin-bottom: 0.5rem; }
        .mb-2 { margin-bottom: 1rem; }
        .mb-3 { margin-bottom: 1.5rem; }
        
        .w-full { width: 100%; }
        .max-w-full { max-width: 100%; }
    </style>
    
    {{-- Stack for pushed styles (chat widget CSS) --}}
    @stack('styles')
</head>
<body>
    <!-- Toast Messages Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Mobile Menu Overlay -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
    
    <!-- ============ HEADER ============ -->
    <header>
        <div class="container header-container">
            <a href="/" class="logo">
                @if(isset($systemSettings) && $systemSettings->system_logo)
                    <img src="{{ asset('storage/' . $systemSettings->system_logo) }}" 
                         alt="{{ $systemSettings->system_name ?? 'System Logo' }}" 
                         class="logo-image">
                @else
                    <i class="fas fa-building"></i>
                @endif
                <span class="logo-text">{{ $systemSettings->system_name ?? 'Community Portal' }}</span>
            </a>
            
            <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
            
            <nav id="mainNav">
                <ul>
                    <li><a href="/">Home</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                    <li><a href="#benefits">Benefits</a></li>
                    <li><a href="#testimonials">Testimonials</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <li><a href="{{ route('login') }}" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a></li>
                    <li>
                        <button class="theme-toggle" id="themeToggleBtn" aria-label="Toggle theme">
                            <i class="fas fa-sun"></i>
                            <i class="fas fa-moon"></i>
                            <span id="themeText">Light</span>
                        </button>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- ============ HERO SECTION ============ -->
    <section class="hero">
        <div class="container hero-content">
            <h1>{{ $systemSettings->system_name ?? 'Community Portal' }}</h1>
            <p>
                Your trusted platform for property registration and estate management. 
                Register your land or property today and join our growing community.
            </p>
            <div class="hero-buttons">
                @if($systemSettings->isRegistrationAllowed())
                    <button class="btn btn-primary register-btn-large" id="landlordRegistrationBtn">
                        <i class="fas fa-user-plus"></i> Register Your Land/Property
                    </button>
                @else
                    <button class="btn btn-secondary register-btn-large" id="registrationDisabledBtn" disabled style="opacity: 0.7; cursor: not-allowed;" 
                            title="{{ $systemSettings->getRegistrationDisabledMessage() }}">
                        <i class="fas fa-ban"></i> Registration Currently Disabled
                    </button>
                @endif
                
                <button class="btn testimonial-form-btn" id="shareExperienceBtn">
                    <i class="fas fa-star"></i> Share Your Experience
                </button>
            </div>
            
            @if(!$systemSettings->isRegistrationAllowed())
                <div class="alert alert-info" style="margin-top: 2rem; display: inline-block;">
                    <i class="fas fa-info-circle"></i>
                    <span>{{ $systemSettings->getRegistrationDisabledMessage() }}</span>
                </div>
            @endif
        </div>
    </section>

    <!-- ============ HOW IT WORKS ============ -->
    <section id="how-it-works" class="features">
        <div class="container">
            <div class="section-title">
                <h2>How It Works</h2>
                <p>Simple 3-step process to register your property</p>
            </div>
            <div class="benefits-grid">
                <div class="benefit-item">
                    <div class="step-circle">1</div>
                    <i class="fas fa-file-alt"></i>
                    <h3>Fill Registration Form</h3>
                    <p>Complete the online registration form with your property and personal details</p>
                </div>
                <div class="benefit-item">
                    <div class="step-circle">2</div>
                    <i class="fas fa-clock"></i>
                    <h3>Await Verification</h3>
                    <p>Our team reviews your submission and verifies the information provided</p>
                </div>
                <div class="benefit-item">
                    <div class="step-circle">3</div>
                    <i class="fas fa-check-circle"></i>
                    <h3>Get Approved</h3>
                    <p>Receive confirmation and access to all estate management services</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ SERVICES ============ -->
    <section id="services" class="features" style="background: var(--bg-secondary);">
        <div class="container">
            <div class="section-title">
                <h2>Our Services</h2>
                <p>Comprehensive estate management solutions for property owners</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-home"></i>
                    <h3>Property Registration</h3>
                    <p>Register vacant land or existing properties with proper documentation</p>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-hard-hat"></i>
                    <h3>Construction Approval</h3>
                    <p>Get approval for new construction projects on your land</p>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-users"></i>
                    <h3>Tenant Management</h3>
                    <p>Register and manage tenants for your rented properties</p>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-credit-card"></i>
                    <h3>Secure Payments</h3>
                    <p>Multiple payment options including mobile money and bank transfers</p>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Property Analytics</h3>
                    <p>Track property value trends and get insights about your investment</p>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-headset"></i>
                    <h3>24/7 Support</h3>
                    <p>Get assistance with registration, payments, and estate services</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ BENEFITS ============ -->
    <section id="benefits">
        <div class="container">
            <div class="section-title">
                <h2>Why Choose Us</h2>
                <p>Benefits of registering with our platform</p>
            </div>
            <div class="benefits-grid">
                <div class="benefit-item">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Secure & Verified</h3>
                    <p>All registrations are verified by estate management for authenticity</p>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-mobile-alt"></i>
                    <h3>Easy Access</h3>
                    <p>Access your property information anytime, anywhere from any device</p>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-bell"></i>
                    <h3>Real-time Updates</h3>
                    <p>Get instant notifications about your registration status and estate news</p>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-file-pdf"></i>
                    <h3>Digital Documents</h3>
                    <p>Store and access all property documents securely online</p>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-hand-holding-usd"></i>
                    <h3>Easy Payments</h3>
                    <p>Pay estate dues conveniently through multiple payment channels</p>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-chart-bar"></i>
                    <h3>Property Insights</h3>
                    <p>Get valuable insights about property values and market trends</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ STATS ============ -->
    <section class="stats">
        <div class="container">
            <div class="section-title">
                <h2>Our Impact</h2>
                <p>Key metrics about {{ $systemSettings->system_name ?? 'our community' }}</p>
            </div>
            <div class="stats-grid">
                <div class="stat-item">
                    <i class="fas fa-home"></i>
                    <div class="stat-number" id="stat-properties" data-target="{{ $stats['total_properties'] ?? 0 }}">0</div>
                    <p>Properties Registered</p>
                </div>
                <div class="stat-item">
                    <i class="fas fa-hard-hat"></i>
                    <div class="stat-number" id="stat-construction" data-target="{{ $stats['under_construction'] ?? 0 }}">0</div>
                    <p>Under Construction</p>
                </div>
                <div class="stat-item">
                    <i class="fas fa-users"></i>
                    <div class="stat-number" id="stat-tenants" data-target="{{ $stats['total_tenants'] ?? 0 }}">0</div>
                    <p>Registered Tenants</p>
                </div>
                <div class="stat-item">
                    <i class="fas fa-building"></i>
                    <div class="stat-number" id="stat-landlords" data-target="{{ $stats['total_landlords'] ?? 0 }}">0</div>
                    <p>Registered Landlords</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ TESTIMONIALS ============ -->
    <section id="testimonials" class="features">
        <div class="container">
            <div class="section-title">
                <h2>What Our Clients Say</h2>
                <p>Trusted by property owners across the community</p>
            </div>
            
            <div class="testimonials-carousel" id="testimonialsCarousel">
                <button class="carousel-btn prev" id="carouselPrevBtn" aria-label="Previous testimonials">
                    <i class="fas fa-chevron-left"></i>
                </button>
                
                <div class="testimonials-track" id="testimonialsTrack">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <p>Loading testimonials...</p>
                    </div>
                </div>
                
                <button class="carousel-btn next" id="carouselNextBtn" aria-label="Next testimonials">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            
            <div class="carousel-dots" id="carouselDots"></div>
            <div class="carousel-status" id="carouselStatus"></div>
            
            <div class="share-btn-container">
                <button class="btn btn-outline" id="shareExperienceBtnFooter">
                    <i class="fas fa-pen"></i> Share Your Experience
                </button>
            </div>
        </div>
    </section>

    <!-- ============ PAYMENT METHODS ============ -->
    <section class="payment-methods" style="background: var(--bg-secondary);">
        <div class="container">
            <div class="section-title">
                <h2>Payment Methods</h2>
                <p>Multiple secure payment options for estate dues</p>
            </div>
            <div class="payment-grid">
                <div class="payment-method">
                    <i class="fas fa-mobile-alt"></i>
                    <h3>Mobile Money</h3>
                    <p>MTN, AirtelTigo, Telecel</p>
                </div>
                <div class="payment-method">
                    <i class="fas fa-university"></i>
                    <h3>Bank Transfer</h3>
                    <p>Direct bank transfers</p>
                </div>
                <div class="payment-method">
                    <i class="fas fa-credit-card"></i>
                    <h3>Card Payment</h3>
                    <p>Visa, Mastercard, Amex</p>
                </div>
                <div class="payment-method">
                    <i class="fas fa-money-bill-wave"></i>
                    <h3>Cash Payment</h3>
                    <p>At estate office</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ FAQ ============ -->
    <section class="features">
        <div class="container">
            <div class="section-title">
                <h2>Frequently Asked Questions</h2>
                <p>Got questions? We've got answers</p>
            </div>
            <div class="benefits-grid">
                <div class="benefit-item">
                    <i class="fas fa-question-circle"></i>
                    <h3>How long does registration take?</h3>
                    <p>Registration typically takes 10-15 minutes to complete. Approval takes 2-3 business days.</p>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-file-signature"></i>
                    <h3>What documents are needed?</h3>
                    <p>You'll need land ownership documents, identification, and property details.</p>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-clock"></i>
                    <h3>Is there a registration fee?</h3>
                    <p>Registration is free. Only estate dues apply after approval.</p>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Is my information secure?</h3>
                    <p>Yes, we use industry-standard encryption to protect your data.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ CTA ============ -->
    <section class="cta">
        <div class="container">
            <div class="section-title">
                <h2>Ready to Get Started?</h2>
                <p>Join our growing community of property owners. Register your land or property today.</p>
            </div>
            @if($systemSettings->isRegistrationAllowed())
                <button class="btn btn-modern register-btn-large" id="ctaRegisterBtn">
                    <i class="fas fa-user-plus"></i> Register Now
                </button>
            @else
                <button class="btn btn-secondary register-btn-large" disabled style="opacity: 0.7; cursor: not-allowed;">
                    <i class="fas fa-ban"></i> Registration Currently Disabled
                </button>
            @endif
        </div>
    </section>

    <!-- ============ CONTACT ============ -->
    <section id="contact" class="features" style="background: var(--bg-secondary);">
        <div class="container">
            <div class="section-title">
                <h2>Contact Us</h2>
                <p>Get in touch with our support team</p>
            </div>
            <div class="benefits-grid">
                <div class="benefit-item">
                    <i class="fas fa-envelope"></i>
                    <h3>Email</h3>
                    <p>{{ $systemSettings->system_email ?? 'support@hilltop.com' }}</p>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-phone-alt"></i>
                    <h3>Phone</h3>
                    <p>{{ $systemSettings->system_phone ?? '+233 123 456 789' }}</p>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Address</h3>
                    <p>Estate Office, {{ $systemSettings->system_name ?? 'Community Portal' }}</p>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-clock"></i>
                    <h3>Office Hours</h3>
                    <p>Mon-Fri: 8:00 AM - 5:00 PM<br>Sat: 9:00 AM - 1:00 PM</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ FOOTER ============ -->
    <footer class="footer">
        <div class="container">
            <div class="social-icons-footer">
                <a href="#" aria-label="Facebook">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="#" aria-label="Twitter">
                    <i class="fab fa-twitter"></i>
                </a>
                <a href="#" aria-label="Instagram">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="#" aria-label="WhatsApp">
                    <i class="fab fa-whatsapp"></i>
                </a>
            </div>
            
            <div class="footer-links">
                <a href="/">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="#how-it-works">
                    <i class="fas fa-info-circle"></i> How It Works
                </a>
                <a href="#services">
                    <i class="fas fa-cogs"></i> Services
                </a>
                <a href="#testimonials">
                    <i class="fas fa-star"></i> Testimonials
                </a>
                <a href="{{ route('login') }}">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
                <a href="#contact">
                    <i class="fas fa-headset"></i> Contact
                </a>
            </div>
            
            <p class="footer-copyright">
                &copy; {{ date('Y') }} {{ $systemSettings->system_name ?? 'Community Portal' }}. All rights reserved.
            </p>
        </div>
    </footer>

    <!-- ============ REGISTRATION MODAL ============ -->
    <div class="modal" id="registrationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-building"></i> Register Your Land/Property</h3>
                <button class="modal-close" id="closeModalBtn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="registrationForm" action="{{ route('landlord.construction.register') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <!-- Registration Type Selection -->
                    <div class="form-section">
                        <h4 class="form-section-title">What would you like to register?</h4>
                        <div class="registration-type-cards">
                            <div class="reg-type-card" data-type="vacant_land">
                                <i class="fas fa-tree"></i>
                                <h4>Vacant Land</h4>
                                <p>Register empty land/plot for future construction</p>
                            </div>
                            <div class="reg-type-card" data-type="existing_property">
                                <i class="fas fa-home"></i>
                                <h4>Existing Property</h4>
                                <p>Register an already built property with or without tenants</p>
                            </div>
                        </div>
                        <input type="hidden" name="registration_type" id="registration_type" value="">
                        <input type="hidden" name="purpose" id="purpose" value="">
                        <input type="hidden" name="include_construction" id="include_construction" value="0">
                    </div>
                    
                    <!-- Landlord Information -->
                    <div class="form-section" id="landlordSection">
                        <h4 class="form-section-title">Landlord Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name" class="required">Full Name</label>
                                <input type="text" id="name" name="name" class="form-control" required>
                                <span class="error-message" id="nameError"></span>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" class="form-control">
                                <span class="error-message" id="emailError"></span>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="primary_phone" class="required">Primary Phone Number</label>
                                <input type="tel" id="primary_phone" name="primary_phone" class="form-control" required placeholder="e.g., 0595652410">
                                <span class="error-message" id="primaryPhoneError"></span>
                            </div>
                            <div class="form-group">
                                <label>Additional Phone Numbers</label>
                                <div id="additionalPhonesContainer">
                                    <div class="additional-phone-entry">
                                        <input type="tel" name="additional_phones[]" class="form-control" placeholder="Optional">
                                        <button type="button" class="remove-phone-btn" style="display: none;" disabled>
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="button" id="addPhoneBtn" class="btn btn-sm btn-outline" style="margin-top: 0.5rem;">
                                    <i class="fas fa-plus"></i> Add Another Phone
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Property/Land Information -->
                    <div class="form-section" id="propertySection">
                        <h4 class="form-section-title">Property/Land Details</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="property_name" class="required">Property/Land Name</label>
                                <input type="text" id="property_name" name="property_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="plot_number" class="required">Plot Number</label>
                                <input type="text" id="plot_number" name="plot_number" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="street_name" class="required">Street Name</label>
                                <input type="text" id="street_name" name="street_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="digital_address">Digital Address (GPS)</label>
                                <input type="text" id="digital_address" name="digital_address" class="form-control" placeholder="e.g., GW-1234-5678">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="land_description">Land/Property Description</label>
                            <textarea id="land_description" name="land_description" class="form-control" placeholder="Describe boundaries, landmarks, etc."></textarea>
                        </div>
                        <div class="form-group">
                            <label for="land_ownership_document" class="required">Land Ownership Document</label>
                            <div class="file-upload-area" id="docUploadArea">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click or drag to upload ownership document</p>
                                <p class="file-hint">PDF, JPG, JPEG, PNG (Max 5MB)</p>
                                <input type="file" name="land_ownership_document" id="land_ownership_document" class="file-input" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                            <div id="docPreview" class="file-preview hidden"></div>
                        </div>
                    </div>
                    
                    <!-- Construction Details (Vacant Land) -->
                    <div class="form-section hidden" id="constructionSection">
                        <h4 class="form-section-title">Construction Details</h4>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <small>You're registering vacant land. You can provide construction plans now or later.</small>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="provide_construction_details" name="provide_construction_details">
                            <label for="provide_construction_details">I want to provide construction details now</label>
                        </div>
                        
                        <div id="constructionDetailsForm" class="hidden">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="property_type" class="required">Planned Property Type</label>
                                    <select id="property_type" name="property_type" class="form-control">
                                        <option value="">Select property type</option>
                                        <option value="residential">Residential House</option>
                                        <option value="apartment">Apartment Building</option>
                                        <option value="commercial">Commercial Property</option>
                                        <option value="mixed">Mixed Use</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group hidden" id="customPropertyTypeGroup">
                                    <label for="custom_property_type">Specify Property Type</label>
                                    <input type="text" id="custom_property_type" name="custom_property_type" class="form-control">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="property_status" class="required">Construction Status</label>
                                <select id="property_status" name="property_status" class="form-control">
                                    <option value="under_construction">Under Construction</option>
                                    <option value="active">Active/Completed</option>
                                    <option value="vacant">Vacant Land</option>
                                    <option value="inactive">Inactive/Paused</option>
                                </select>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="estimated_bedrooms">Estimated Bedrooms</label>
                                    <input type="number" id="estimated_bedrooms" name="estimated_bedrooms" class="form-control" min="1" max="20">
                                </div>
                                <div class="form-group">
                                    <label for="estimated_completion">Estimated Completion Date</label>
                                    <input type="date" id="estimated_completion" name="estimated_completion" class="form-control">
                                </div>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="has_plans" name="has_plans" value="yes">
                                <label for="has_plans">I have architectural/construction plans</label>
                            </div>
                            <div class="form-group">
                                <label for="construction_documents">Construction Plans/Documents</label>
                                <div class="file-upload-area" id="constructionUploadArea">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Upload construction plans, architectural drawings</p>
                                    <p class="file-hint">PDF, JPG, JPEG, PNG (Max 10MB each)</p>
                                    <input type="file" name="construction_documents[]" id="construction_documents" class="file-input" multiple accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                                <div id="constructionPreview" class="file-preview hidden"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Existing Property Details -->
                    <div class="form-section hidden" id="existingPropertySection">
                        <h4 class="form-section-title">Existing Property Details</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="existing_property_type" class="required">Property Type</label>
                                <select id="existing_property_type" name="existing_property_type" class="form-control">
                                    <option value="">Select property type</option>
                                    <option value="residential">Residential House</option>
                                    <option value="apartment">Apartment Building</option>
                                    <option value="commercial">Commercial Property</option>
                                    <option value="mixed">Mixed Use</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group hidden" id="existingCustomPropertyTypeGroup">
                                <label for="existing_custom_property_type">Specify Property Type</label>
                                <input type="text" id="existing_custom_property_type" name="existing_custom_property_type" class="form-control">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="existing_property_status" class="required">Property Status</label>
                                <select id="existing_property_status" name="existing_property_status" class="form-control">
                                    <option value="active">Active (Occupied)</option>
                                    <option value="vacant">Vacant</option>
                                    <option value="under_maintenance">Under Maintenance</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="year_built">Year Built</label>
                                <input type="number" id="year_built" name="year_built" class="form-control" min="1900" max="{{ date('Y') }}">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="existing_bedrooms">Number of Bedrooms</label>
                                <input type="number" id="existing_bedrooms" name="existing_bedrooms" class="form-control" min="0" max="50">
                            </div>
                            <div class="form-group">
                                <label for="existing_bathrooms">Number of Bathrooms</label>
                                <input type="number" id="existing_bathrooms" name="existing_bathrooms" class="form-control" min="0" max="50">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="property_photos">Property Photos</label>
                            <div class="file-upload-area" id="photoUploadArea">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Upload property photos (exterior & interior)</p>
                                <p class="file-hint">JPG, JPEG, PNG (Max 10MB each)</p>
                                <input type="file" name="property_photos[]" id="property_photos" class="file-input" multiple accept=".jpg,.jpeg,.png">
                            </div>
                            <div id="photoPreview" class="file-preview hidden"></div>
                        </div>
                        <div class="form-group">
                            <label for="property_documents">Additional Property Documents</label>
                            <div class="file-upload-area" id="propertyDocUploadArea">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Upload property documents, permits, etc.</p>
                                <p class="file-hint">PDF (Max 10MB each)</p>
                                <input type="file" name="property_documents[]" id="property_documents" class="file-input" multiple accept=".pdf">
                            </div>
                            <div id="propertyDocPreview" class="file-preview hidden"></div>
                        </div>
                    </div>
                    
                    <!-- Tenants Section -->
                    <div class="form-section hidden" id="tenantsSection">
                        <h4 class="form-section-title">Tenant Information</h4>
                        <div class="checkbox-group">
                            <input type="checkbox" id="has_tenants" name="has_tenants" value="yes">
                            <label for="has_tenants">This property has existing tenants</label>
                        </div>
                        
                        <div id="tenantsList" class="hidden">
                            <div id="tenantEntries"></div>
                            <button type="button" class="add-tenant-btn" id="addTenantBtn">
                                <i class="fas fa-plus"></i> Add Tenant
                            </button>
                        </div>
                    </div>
                    
                    <!-- Declaration -->
                    <div class="form-section">
                        <div class="checkbox-group">
                            <input type="checkbox" id="declaration" name="declaration" value="1" required>
                            <label for="declaration" class="required">I hereby declare that the information provided is true and correct to the best of my knowledge.</label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" id="previewBtn">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <button type="button" class="btn btn-secondary" id="cancelRegBtn">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submitRegBtn">
                            <i class="fas fa-paper-plane"></i> Submit Registration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============ PREVIEW MODAL ============ -->
    <div class="modal" id="previewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-alt"></i> Registration Preview</h3>
                <button class="modal-close" id="closePreviewModalBtn">&times;</button>
            </div>
            <div class="modal-body" id="previewBody">
                <div id="previewContent">
                    <!-- Preview content will be rendered here -->
                </div>
                <div class="preview-actions">
                    <button type="button" class="btn btn-secondary" id="closePreviewBtn">
                        <i class="fas fa-arrow-left"></i> Back to Form
                    </button>
                    <button type="button" class="btn btn-primary" id="submitFromPreviewBtn">
                        <i class="fas fa-paper-plane"></i> Submit Registration
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ TESTIMONIAL MODAL ============ -->
    <div class="modal" id="testimonialModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i class="fas fa-star" style="color: #f39c12;"></i> Share Your Experience</h3>
                <button class="modal-close" id="closeTestimonialModalBtn">&times;</button>
            </div>
            <div class="modal-body">
                <div id="testimonialRateLimitInfo" class="rate-limit-info" style="display: none;">
                    <i class="fas fa-clock"></i> Please wait before submitting another testimonial.
                </div>
                
                <form id="testimonialForm">
                    @csrf
                    <div class="form-group">
                        <label for="testimonial_name" class="required">Your Name</label>
                        <input type="text" id="testimonial_name" name="name" class="form-control" required>
                        <span class="error-message" id="testimonialNameError"></span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="testimonial_email">Email (Optional)</label>
                            <input type="email" id="testimonial_email" name="email" class="form-control">
                            <span class="error-message" id="testimonialEmailError"></span>
                        </div>
                        <div class="form-group">
                            <label for="testimonial_role">Your Role</label>
                            <select id="testimonial_role" name="role" class="form-control">
                                <option value="">Select your role</option>
                                <option value="Property Owner">Property Owner</option>
                                <option value="Landlord">Landlord</option>
                                <option value="Tenant">Tenant</option>
                                <option value="Property Developer">Property Developer</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Rating</label>
                        <div class="rating-input" id="ratingInput">
                            <i class="fas fa-star" data-rating="1"></i>
                            <i class="fas fa-star" data-rating="2"></i>
                            <i class="fas fa-star" data-rating="3"></i>
                            <i class="fas fa-star" data-rating="4"></i>
                            <i class="fas fa-star" data-rating="5"></i>
                            <input type="hidden" name="rating" id="testimonial_rating" value="5">
                        </div>
                        <span class="error-message" id="testimonialRatingError"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="testimonial_content" class="required">Your Feedback</label>
                        <textarea id="testimonial_content" name="content" rows="5" class="form-control" required maxlength="1000"></textarea>
                        <div class="character-counter" id="charCounter">
                            <span id="charCount">0</span> / 1000 characters
                        </div>
                        <span class="error-message" id="testimonialContentError"></span>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" id="cancelTestimonialBtn">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submitTestimonialBtn">
                            <i class="fas fa-paper-plane"></i> Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ⭐ FLOATING CHAT WIDGET — now visible to guests AND authenticated users --}}
    @php
        $chatUser     = auth()->user();
        $chatProvider = app(\App\Services\ChatHelpTopicProvider::class);
        $chatRoleName = $chatUser?->type_name ?? 'Guest';
    @endphp

    <button type="button"
            id="floatingChatLauncher"
            class="floating-chat-launcher"
            title="Chat with us"
            aria-label="Open chat">
        <i class="fas fa-comments"></i>
    </button>

    <div id="floatingChatPanel"
         class="floating-chat-panel"
         role="dialog"
         aria-label="Chat assistant">
        <div class="floating-chat-panel__header">
            <div>
                <strong>Assistant</strong>
                <div class="floating-chat-panel__sub">
                    @if($chatUser)
                        Helping you as <strong>{{ $chatRoleName }}</strong>
                    @else
                        Ask anything before you register
                    @endif
                </div>
            </div>
            <button type="button"
                    id="floatingChatClose"
                    class="floating-chat-panel__close"
                    aria-label="Close chat">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="floating-chat-panel__body">
            @include('chat._widget', [
                'userRoleName'    => $chatRoleName,
                'userRoleId'      => $chatUser?->type,
                'recentMessages'  => collect(),
                'quickHelpTopics' => $chatProvider->forUser($chatUser),
            ])
        </div>
    </div>

    <script>
    // ============================================
    // THEME TOGGLE
    // ============================================
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const themeText = document.getElementById('themeText');
    
    function setTheme(theme) {
        const html = document.documentElement;
        html.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        
        if (themeText) {
            themeText.textContent = theme === 'dark' ? 'Dark' : 'Light';
        }
    }
    
    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        setTheme(newTheme);
    }
    
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', toggleTheme);
        themeToggleBtn.addEventListener('touchend', function(e) {
            e.preventDefault();
            toggleTheme();
        });
    }
    
    const savedTheme = localStorage.getItem('theme') || 
                      (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    setTheme(savedTheme);
    document.documentElement.classList.remove('theme-loading');

    // ============================================
    // TOAST SYSTEM
    // ============================================
    const Toast = {
        container: document.getElementById('toastContainer'),
        
        show(message, type = 'info', duration = 5000) {
            if (!this.container) {
                console.warn('Toast container not found');
                return;
            }
            
            const toast = document.createElement('div');
            const iconMap = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <i class="fas ${iconMap[type] || 'fa-info-circle'}"></i>
                <span>${message}</span>
            `;
            this.container.appendChild(toast);
            
            toast.addEventListener('click', () => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.remove();
                    }
                }, 300);
            });
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.style.animation = 'slideOut 0.3s ease';
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.remove();
                        }
                    }, 300);
                }
            }, duration);
        },
        
        success(msg, duration = 5000) { this.show(msg, 'success', duration); },
        error(msg, duration = 5000) { this.show(msg, 'error', duration); },
        info(msg, duration = 5000) { this.show(msg, 'info', duration); },
        warning(msg, duration = 5000) { this.show(msg, 'warning', duration); }
    };

    // ============================================
    // MOBILE MENU
    // ============================================
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mainNav = document.getElementById('mainNav');
    const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');

    function toggleMobileMenu() {
        mainNav.classList.toggle('active');
        mobileMenuOverlay.classList.toggle('active');
        document.body.style.overflow = mainNav.classList.contains('active') ? 'hidden' : '';
    }

    function closeMobileMenu() {
        mainNav.classList.remove('active');
        mobileMenuOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', toggleMobileMenu);
        mobileMenuBtn.addEventListener('touchend', function(e) {
            e.preventDefault();
            toggleMobileMenu();
        });
    }

    if (mobileMenuOverlay) {
        mobileMenuOverlay.addEventListener('click', closeMobileMenu);
        mobileMenuOverlay.addEventListener('touchend', function(e) {
            e.preventDefault();
            closeMobileMenu();
        });
    }

    document.querySelectorAll('#mainNav ul li a').forEach(link => {
        link.addEventListener('click', closeMobileMenu);
    });

    // ============================================
    // COUNTER ANIMATION
    // ============================================
    function animateCounter(element, target) {
        if (!element) return;
        let current = 0;
        const increment = Math.ceil(target / 50);
        const interval = setInterval(() => {
            current += increment;
            if (current >= target) {
                element.textContent = target.toLocaleString();
                clearInterval(interval);
            } else {
                element.textContent = current.toLocaleString();
            }
        }, 30);
    }

    document.querySelectorAll('.stat-number').forEach(el => {
        const target = parseInt(el.dataset.target) || 0;
        animateCounter(el, target);
    });

    // ============================================
    // TESTIMONIAL CAROUSEL
    // ============================================
    let testimonialsData = [];
    let currentIndex = 0;
    let slidesToShow = 3;
    let autoScrollInterval = null;
    let isUserInteracting = false;
    let autoScrollDelay = 5000;
    let totalSlides = 0;

    const track = document.getElementById('testimonialsTrack');
    const prevBtn = document.getElementById('carouselPrevBtn');
    const nextBtn = document.getElementById('carouselNextBtn');
    const dotsContainer = document.getElementById('carouselDots');
    const statusContainer = document.getElementById('carouselStatus');

    function updateSlidesToShow() {
        if (window.innerWidth <= 480) {
            slidesToShow = 1;
        } else if (window.innerWidth <= 768) {
            slidesToShow = 1;
        } else if (window.innerWidth <= 1024) {
            slidesToShow = 2;
        } else {
            slidesToShow = 3;
        }
    }

    function createTestimonialCard(testimonial) {
        const ratingStars = '★'.repeat(testimonial.rating) + '☆'.repeat(5 - testimonial.rating);
        const initials = testimonial.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
        
        return `
            <div class="testimonial-card">
                <div class="testimonial-rating">
                    <span style="color: #f39c12; font-size: 1rem;">${ratingStars}</span>
                </div>
                <div class="testimonial-content">
                    "${testimonial.content.length > 200 ? testimonial.content.substring(0, 200) + '...' : testimonial.content}"
                </div>
                <div class="testimonial-author">
                    <div class="author-avatar">
                        ${testimonial.avatar_url ? `<img src="${testimonial.avatar_url}" alt="${testimonial.name}" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">` : initials}
                    </div>
                    <div class="author-info">
                        <div class="author-name">${testimonial.name}</div>
                        <div class="author-role">${testimonial.role || 'Property Owner'}</div>
                    </div>
                </div>
            </div>
        `;
    }

    function renderCarousel() {
        if (!testimonialsData.length) return;
        
        updateSlidesToShow();
        totalSlides = testimonialsData.length;
        
        const duplicatedItems = [...testimonialsData, ...testimonialsData, ...testimonialsData];
        const startIndex = testimonialsData.length;
        
        let html = '';
        for (let i = 0; i < slidesToShow * 3; i++) {
            const item = duplicatedItems[startIndex + i];
            if (item) {
                html += `<div class="testimonial-slide">${createTestimonialCard(item)}</div>`;
            }
        }
        track.innerHTML = html;
        
        const slideWidth = track.children[0]?.offsetWidth + 24;
        const initialOffset = -(testimonialsData.length * slideWidth);
        track.style.transform = `translateX(${initialOffset}px)`;
        currentIndex = testimonialsData.length;
        
        updateDots();
        updateStatus();
    }

    function updateDots() {
        if (!dotsContainer) return;
        
        const dotCount = Math.ceil(totalSlides / slidesToShow);
        let dotsHtml = '';
        const currentPage = Math.floor((currentIndex % totalSlides) / slidesToShow);
        
        for (let i = 0; i < dotCount; i++) {
            dotsHtml += `<div class="carousel-dot ${i === currentPage ? 'active' : ''}" data-page="${i}"></div>`;
        }
        dotsContainer.innerHTML = dotsHtml;
        
        document.querySelectorAll('.carousel-dot').forEach(dot => {
            dot.addEventListener('click', () => {
                const page = parseInt(dot.dataset.page);
                goToPage(page);
                resetAutoScroll();
            });
            dot.addEventListener('touchend', function(e) {
                e.preventDefault();
                const page = parseInt(this.dataset.page);
                goToPage(page);
                resetAutoScroll();
            });
        });
    }

    function updateStatus() {
        if (!statusContainer) return;
        const currentPage = Math.floor((currentIndex % totalSlides) / slidesToShow) + 1;
        const totalPages = Math.ceil(totalSlides / slidesToShow);
        statusContainer.textContent = `Showing page ${currentPage} of ${totalPages}`;
    }

    function goToPage(page) {
        const slideWidth = track.children[0]?.offsetWidth + 24;
        const targetIndex = (page * slidesToShow) + testimonialsData.length;
        track.style.transform = `translateX(${-(targetIndex * slideWidth)}px)`;
        currentIndex = targetIndex;
        updateDots();
        updateStatus();
    }

    function nextSlide() {
        const slideWidth = track.children[0]?.offsetWidth + 24;
        const newIndex = currentIndex + slidesToShow;
        
        track.style.transition = 'transform 0.5s ease-in-out';
        track.style.transform = `translateX(${-(newIndex * slideWidth)}px)`;
        currentIndex = newIndex;
        
        updateDots();
        updateStatus();
        
        setTimeout(() => {
            if (currentIndex >= testimonialsData.length * 2) {
                track.style.transition = 'none';
                const resetIndex = currentIndex - testimonialsData.length;
                track.style.transform = `translateX(${-(resetIndex * slideWidth)}px)`;
                currentIndex = resetIndex;
                updateDots();
                updateStatus();
                track.offsetHeight;
                track.style.transition = 'transform 0.5s ease-in-out';
            }
        }, 500);
    }

    function prevSlide() {
        const slideWidth = track.children[0]?.offsetWidth + 24;
        const newIndex = currentIndex - slidesToShow;
        
        track.style.transition = 'transform 0.5s ease-in-out';
        track.style.transform = `translateX(${-(newIndex * slideWidth)}px)`;
        currentIndex = newIndex;
        
        updateDots();
        updateStatus();
        
        setTimeout(() => {
            if (currentIndex < testimonialsData.length) {
                track.style.transition = 'none';
                const resetIndex = currentIndex + testimonialsData.length;
                track.style.transform = `translateX(${-(resetIndex * slideWidth)}px)`;
                currentIndex = resetIndex;
                updateDots();
                updateStatus();
                track.offsetHeight;
                track.style.transition = 'transform 0.5s ease-in-out';
            }
        }, 500);
    }

    function startAutoScroll() {
        if (autoScrollInterval) {
            clearInterval(autoScrollInterval);
        }
        if (testimonialsData.length > slidesToShow) {
            autoScrollInterval = setInterval(() => {
                if (!isUserInteracting) {
                    nextSlide();
                }
            }, autoScrollDelay);
        }
    }

    function stopAutoScroll() {
        if (autoScrollInterval) {
            clearInterval(autoScrollInterval);
            autoScrollInterval = null;
        }
    }

    function resetAutoScroll() {
        stopAutoScroll();
        startAutoScroll();
    }

    async function loadTestimonials() {
        if (!track) return;
        
        try {
            const response = await fetch('/api/testimonials?limit=20');
            const data = await response.json();
            
            if (data.success && data.testimonials && data.testimonials.length > 0) {
                testimonialsData = data.testimonials;
                renderCarousel();
                startAutoScroll();
                
                if (prevBtn) {
                    prevBtn.addEventListener('click', () => {
                        isUserInteracting = true;
                        prevSlide();
                        resetAutoScroll();
                        setTimeout(() => { isUserInteracting = false; }, 10000);
                    });
                    prevBtn.addEventListener('touchend', function(e) {
                        e.preventDefault();
                        isUserInteracting = true;
                        prevSlide();
                        resetAutoScroll();
                        setTimeout(() => { isUserInteracting = false; }, 10000);
                    });
                }
                
                if (nextBtn) {
                    nextBtn.addEventListener('click', () => {
                        isUserInteracting = true;
                        nextSlide();
                        resetAutoScroll();
                        setTimeout(() => { isUserInteracting = false; }, 10000);
                    });
                    nextBtn.addEventListener('touchend', function(e) {
                        e.preventDefault();
                        isUserInteracting = true;
                        nextSlide();
                        resetAutoScroll();
                        setTimeout(() => { isUserInteracting = false; }, 10000);
                    });
                }
                
                const carousel = document.getElementById('testimonialsCarousel');
                carousel.addEventListener('mouseenter', stopAutoScroll);
                carousel.addEventListener('mouseleave', startAutoScroll);
                
                let resizeTimeout;
                window.addEventListener('resize', () => {
                    clearTimeout(resizeTimeout);
                    resizeTimeout = setTimeout(() => {
                        renderCarousel();
                        startAutoScroll();
                    }, 250);
                });
            } else {
                track.innerHTML = `
                    <div style="text-align: center; width: 100%; padding: 3rem;">
                        <i class="fas fa-comments" style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem; display: block;"></i>
                        <h3>Be the First to Review</h3>
                        <p>No testimonials yet. Share your experience with us!</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Failed to load testimonials:', error);
            track.innerHTML = `
                <div style="text-align: center; width: 100%; padding: 3rem;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--danger); margin-bottom: 1rem; display: block;"></i>
                    <h3>Unable to Load Testimonials</h3>
                    <p>Please refresh the page to see testimonials.</p>
                </div>
            `;
        }
    }

    loadTestimonials();

    // ============================================
    // TESTIMONIAL FORM
    // ============================================
    const testimonialModal = document.getElementById('testimonialModal');
    const testimonialForm = document.getElementById('testimonialForm');
    const nameInput = document.getElementById('testimonial_name');
    const emailInput = document.getElementById('testimonial_email');
    const contentTextarea = document.getElementById('testimonial_content');
    const ratingInputTestimonial = document.getElementById('testimonial_rating');
    const submitTestimonialBtn = document.getElementById('submitTestimonialBtn');
    const rateLimitInfo = document.getElementById('testimonialRateLimitInfo');
    const charCountSpan = document.getElementById('charCount');
    const charCounterDiv = document.querySelector('.character-counter');

    function updateCharacterCounter() {
        const length = contentTextarea.value.length;
        charCountSpan.textContent = length;
        
        if (charCounterDiv) {
            charCounterDiv.classList.remove('warning', 'danger');
            if (length > 900) {
                charCounterDiv.classList.add('danger');
            } else if (length > 700) {
                charCounterDiv.classList.add('warning');
            }
        }
    }

    if (contentTextarea) {
        contentTextarea.addEventListener('input', updateCharacterCounter);
        updateCharacterCounter();
    }

    function clearFieldErrors() {
        document.querySelectorAll('#testimonialForm .error-message').forEach(el => el.textContent = '');
        document.querySelectorAll('#testimonialForm .form-control').forEach(el => el.classList.remove('error'));
    }

    function showFieldError(id, message) {
        const errorSpan = document.getElementById(id);
        if (errorSpan) errorSpan.textContent = message;
    }

    function validateTestimonialForm() {
        clearFieldErrors();
        let isValid = true;

        const name = nameInput.value.trim();
        if (name.length < 2) {
            showFieldError('testimonialNameError', 'Please enter your name (minimum 2 characters)');
            isValid = false;
        }

        const email = emailInput.value.trim();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showFieldError('testimonialEmailError', 'Please enter a valid email address');
            isValid = false;
        }

        const rating = parseInt(ratingInputTestimonial.value);
        if (isNaN(rating) || rating < 1 || rating > 5) {
            showFieldError('testimonialRatingError', 'Please select a rating');
            isValid = false;
        }

        const content = contentTextarea.value.trim();
        if (content.length < 10) {
            showFieldError('testimonialContentError', 'Please provide more detailed feedback (minimum 10 characters)');
            isValid = false;
        } else if (content.length > 1000) {
            showFieldError('testimonialContentError', 'Feedback is too long. Maximum 1000 characters allowed.');
            isValid = false;
        }

        return isValid;
    }

    const shareBtns = document.querySelectorAll('#shareExperienceBtn, #shareExperienceBtnFooter');
    const closeTestimonialModalBtn = document.getElementById('closeTestimonialModalBtn');
    const cancelTestimonialBtn = document.getElementById('cancelTestimonialBtn');

    function openTestimonialModal(e) {
        if (e) e.preventDefault();
        clearFieldErrors();
        testimonialModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeTestimonialModal() {
        testimonialModal.classList.remove('active');
        document.body.style.overflow = '';
        testimonialForm.reset();
        ratingInputTestimonial.value = 5;
        const stars = document.querySelectorAll('#ratingInput i');
        stars.forEach((star, index) => {
            if (index < 5) {
                star.classList.remove('far');
                star.classList.add('fas');
                star.classList.add('active');
            }
        });
        clearFieldErrors();
        updateCharacterCounter();
    }

    shareBtns.forEach(btn => {
        btn.addEventListener('click', openTestimonialModal);
        btn.addEventListener('touchend', function(e) {
            e.preventDefault();
            openTestimonialModal(e);
        });
    });

    if (closeTestimonialModalBtn) {
        closeTestimonialModalBtn.addEventListener('click', closeTestimonialModal);
        closeTestimonialModalBtn.addEventListener('touchend', function(e) {
            e.preventDefault();
            closeTestimonialModal();
        });
    }
    
    if (cancelTestimonialBtn) {
        cancelTestimonialBtn.addEventListener('click', closeTestimonialModal);
        cancelTestimonialBtn.addEventListener('touchend', function(e) {
            e.preventDefault();
            closeTestimonialModal();
        });
    }

    testimonialModal.addEventListener('click', (e) => {
        if (e.target === testimonialModal) closeTestimonialModal();
    });

    const ratingStars = document.querySelectorAll('#ratingInput i');

    ratingStars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.dataset.rating);
            ratingInputTestimonial.value = rating;
            ratingStars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                    s.classList.add('active');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                    s.classList.remove('active');
                }
            });
            document.getElementById('testimonialRatingError').textContent = '';
        });
        star.addEventListener('touchend', function(e) {
            e.preventDefault();
            const rating = parseInt(this.dataset.rating);
            ratingInputTestimonial.value = rating;
            ratingStars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                    s.classList.add('active');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                    s.classList.remove('active');
                }
            });
            document.getElementById('testimonialRatingError').textContent = '';
        });
    });

    testimonialForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!validateTestimonialForm()) {
            Toast.warning('Please fix the errors above before submitting.');
            return;
        }

        const originalText = submitTestimonialBtn.innerHTML;
        submitTestimonialBtn.disabled = true;
        submitTestimonialBtn.innerHTML = '<span class="spinner"></span> Submitting...';

        const formData = new FormData(testimonialForm);

        try {
            const response = await fetch('/api/testimonials', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                Toast.success('Thank you for your feedback! Your testimonial will be reviewed before being published.', 6000);
                closeTestimonialModal();
                setTimeout(() => loadTestimonials(), 2000);
            } else {
                if (data.errors) {
                    for (let key in data.errors) {
                        Toast.error(data.errors[key][0]);
                    }
                } else {
                    Toast.error(data.message || 'Failed to submit testimonial. Please try again.');
                }
            }
        } catch (error) {
            console.error('Error:', error);
            Toast.error('Network error. Please check your connection and try again.');
        } finally {
            submitTestimonialBtn.disabled = false;
            submitTestimonialBtn.innerHTML = originalText;
        }
    });

    // ============================================
    // REGISTRATION MODAL
    // ============================================
    const registrationModal = document.getElementById('registrationModal');
    const registrationTypeInput = document.getElementById('registration_type');
    const purposeInput = document.getElementById('purpose');
    const includeConstructionInput = document.getElementById('include_construction');
    const constructionSection = document.getElementById('constructionSection');
    const existingPropertySection = document.getElementById('existingPropertySection');
    const tenantsSection = document.getElementById('tenantsSection');
    const regTypeCards = document.querySelectorAll('.reg-type-card');
    let selectedType = null;

    const addPhoneBtn = document.getElementById('addPhoneBtn');
    const additionalPhonesContainer = document.getElementById('additionalPhonesContainer');

    function addPhoneField() {
        const phoneDiv = document.createElement('div');
        phoneDiv.className = 'additional-phone-entry';
        phoneDiv.innerHTML = `
            <input type="tel" name="additional_phones[]" class="form-control" placeholder="Additional phone number">
            <button type="button" class="remove-phone-btn" onclick="this.closest('.additional-phone-entry').remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        additionalPhonesContainer.appendChild(phoneDiv);
    }

    if (addPhoneBtn) {
        addPhoneBtn.addEventListener('click', addPhoneField);
        addPhoneBtn.addEventListener('touchend', function(e) {
            e.preventDefault();
            addPhoneField();
        });
    }

    function isValidPhoneNumber(phone) {
        if (!phone) return false;
        const clean = phone.replace(/[\s\-\(\)]/g, '');
        return /^(\+?233|0)\d{9}$/.test(clean);
    }

    regTypeCards.forEach(card => {
        card.addEventListener('click', () => {
            regTypeCards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            selectedType = card.dataset.type;
            
            if (selectedType === 'vacant_land') {
                registrationTypeInput.value = 'construction';
                
                const provideConstruction = document.getElementById('provide_construction_details');
                if (provideConstruction && provideConstruction.checked) {
                    purposeInput.value = 'both';
                    includeConstructionInput.value = '1';
                } else {
                    purposeInput.value = 'construction';
                    includeConstructionInput.value = '0';
                }
                
                constructionSection.classList.remove('hidden');
                existingPropertySection.classList.add('hidden');
                tenantsSection.classList.add('hidden');
            } else {
                registrationTypeInput.value = 'property_capture';
                purposeInput.value = 'permanent_registration';
                includeConstructionInput.value = '0';
                constructionSection.classList.add('hidden');
                existingPropertySection.classList.remove('hidden');
                tenantsSection.classList.remove('hidden');
            }
        });
        card.addEventListener('touchend', function(e) {
            e.preventDefault();
            this.click();
        });
    });

    const provideConstructionCheckbox = document.getElementById('provide_construction_details');
    const constructionDetailsForm = document.getElementById('constructionDetailsForm');

    if (provideConstructionCheckbox) {
        provideConstructionCheckbox.addEventListener('change', () => {
            if (provideConstructionCheckbox.checked) {
                constructionDetailsForm.classList.remove('hidden');
                includeConstructionInput.value = '1';
                purposeInput.value = 'both';
                document.getElementById('property_type').required = true;
                document.getElementById('property_status').required = true;
            } else {
                constructionDetailsForm.classList.add('hidden');
                includeConstructionInput.value = '0';
                purposeInput.value = 'construction';
                document.getElementById('property_type').required = false;
                document.getElementById('property_status').required = false;
            }
        });
    }

    const propertyTypeSelect = document.getElementById('property_type');
    const customPropertyTypeGroup = document.getElementById('customPropertyTypeGroup');

    if (propertyTypeSelect) {
        propertyTypeSelect.addEventListener('change', () => {
            if (propertyTypeSelect.value === 'other') {
                customPropertyTypeGroup.classList.remove('hidden');
            } else {
                customPropertyTypeGroup.classList.add('hidden');
            }
        });
    }

    const existingPropertyTypeSelect = document.getElementById('existing_property_type');
    const existingCustomPropertyTypeGroup = document.getElementById('existingCustomPropertyTypeGroup');

    if (existingPropertyTypeSelect) {
        existingPropertyTypeSelect.addEventListener('change', () => {
            if (existingPropertyTypeSelect.value === 'other') {
                existingCustomPropertyTypeGroup.classList.remove('hidden');
            } else {
                existingCustomPropertyTypeGroup.classList.add('hidden');
            }
        });
    }

    const hasTenantsCheckbox = document.getElementById('has_tenants');
    const tenantsList = document.getElementById('tenantsList');
    const tenantEntries = document.getElementById('tenantEntries');
    let tenantCount = 0;

    if (hasTenantsCheckbox) {
        hasTenantsCheckbox.addEventListener('change', () => {
            if (hasTenantsCheckbox.checked) {
                tenantsList.classList.remove('hidden');
                addTenant();
            } else {
                tenantsList.classList.add('hidden');
                tenantEntries.innerHTML = '';
                tenantCount = 0;
            }
        });
    }

    function addTenant() {
        const tenantDiv = document.createElement('div');
        tenantDiv.className = 'tenant-entry';
        tenantDiv.dataset.index = tenantCount;
        tenantDiv.innerHTML = `
            <div class="tenant-header">
                <h5>Tenant ${tenantCount + 1}</h5>
                <button type="button" class="remove-tenant-btn" onclick="this.closest('.tenant-entry').remove()">
                    <i class="fas fa-trash"></i> Remove
                </button>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Tenant Name</label>
                    <input type="text" name="tenant_name_${tenantCount}" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="required">Phone Number</label>
                    <input type="tel" name="tenant_phone_${tenantCount}" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="tenant_email_${tenantCount}" class="form-control">
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <input type="text" name="tenant_notes_${tenantCount}" class="form-control" placeholder="Optional">
                </div>
            </div>
        `;
        tenantEntries.appendChild(tenantDiv);
        tenantCount++;
    }

    const addTenantBtn = document.getElementById('addTenantBtn');
    if (addTenantBtn) {
        addTenantBtn.addEventListener('click', addTenant);
        addTenantBtn.addEventListener('touchend', function(e) {
            e.preventDefault();
            addTenant();
        });
    }

    function setupFileUpload(uploadAreaId, inputId, previewId, isMultiple = false) {
        const uploadArea = document.getElementById(uploadAreaId);
        const fileInput = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        
        if (!uploadArea || !fileInput) return;
        
        uploadArea.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('touchend', function(e) {
            e.preventDefault();
            fileInput.click();
        });
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = 'var(--primary)';
        });
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.style.borderColor = 'var(--border-color)';
        });
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            const files = e.dataTransfer.files;
            fileInput.files = files;
            updateFilePreview(fileInput, preview, isMultiple);
        });
        
        fileInput.addEventListener('change', () => {
            updateFilePreview(fileInput, preview, isMultiple);
        });
    }

    function updateFilePreview(fileInput, previewElement, isMultiple) {
        if (!previewElement) return;
        const files = Array.from(fileInput.files);
        if (files.length === 0) {
            previewElement.classList.add('hidden');
            previewElement.innerHTML = '';
            return;
        }
        
        previewElement.classList.remove('hidden');
        previewElement.innerHTML = files.map((file, index) => `
            <div class="file-preview-item">
                <i class="fas ${file.type.startsWith('image/') ? 'fa-image' : 'fa-file-pdf'}"></i>
                <span>${file.name.substring(0, 30)}${file.name.length > 30 ? '...' : ''}</span>
                <button type="button" onclick="this.parentElement.remove(); if(document.getElementById('${fileInput.id}').files.length === 0) document.getElementById('${previewElement.id}').classList.add('hidden')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');
    }

    setupFileUpload('docUploadArea', 'land_ownership_document', 'docPreview');
    setupFileUpload('constructionUploadArea', 'construction_documents', 'constructionPreview', true);
    setupFileUpload('photoUploadArea', 'property_photos', 'photoPreview', true);
    setupFileUpload('propertyDocUploadArea', 'property_documents', 'propertyDocPreview', true);

    const registerBtns = ['landlordRegistrationBtn', 'ctaRegisterBtn'];
    registerBtns.forEach(btnId => {
        const btn = document.getElementById(btnId);
        if (btn) {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                registrationModal.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
            btn.addEventListener('touchend', function(e) {
                e.preventDefault();
                registrationModal.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        }
    });

    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelRegBtn = document.getElementById('cancelRegBtn');

    function closeRegistrationModal() {
        registrationModal.classList.remove('active');
        document.body.style.overflow = '';
        
        const form = document.getElementById('registrationForm');
        if (form) form.reset();
        
        regTypeCards.forEach(c => c.classList.remove('selected'));
        selectedType = null;
        
        constructionSection.classList.add('hidden');
        existingPropertySection.classList.add('hidden');
        tenantsSection.classList.add('hidden');
        constructionDetailsForm.classList.add('hidden');
        tenantsList.classList.add('hidden');
        
        if (tenantEntries) {
            tenantEntries.innerHTML = '';
        }
        tenantCount = 0;
        
        if (additionalPhonesContainer) {
            const phoneEntries = additionalPhonesContainer.querySelectorAll('.additional-phone-entry');
            phoneEntries.forEach((entry, index) => {
                if (index > 0) {
                    entry.remove();
                } else {
                    const input = entry.querySelector('input');
                    if (input) input.value = '';
                    const removeBtn = entry.querySelector('.remove-phone-btn');
                    if (removeBtn) {
                        removeBtn.style.display = 'none';
                        removeBtn.disabled = true;
                    }
                }
            });
        }
        
        document.querySelectorAll('.file-preview').forEach(preview => {
            preview.classList.add('hidden');
            preview.innerHTML = '';
        });
        document.querySelectorAll('.file-input').forEach(input => {
            input.value = '';
        });
        
        document.querySelectorAll('.error-message').forEach(el => {
            el.textContent = '';
        });
        document.querySelectorAll('.form-control').forEach(el => {
            el.classList.remove('error');
        });
        
        const declaration = document.getElementById('declaration');
        if (declaration) declaration.checked = false;
        
        closePreviewModal();
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeRegistrationModal);
        closeModalBtn.addEventListener('touchend', function(e) {
            e.preventDefault();
            closeRegistrationModal();
        });
    }
    
    if (cancelRegBtn) {
        cancelRegBtn.addEventListener('click', closeRegistrationModal);
        cancelRegBtn.addEventListener('touchend', function(e) {
            e.preventDefault();
            closeRegistrationModal();
        });
    }

    registrationModal.addEventListener('click', (e) => {
        if (e.target === registrationModal) closeRegistrationModal();
    });

    const previewModal = document.getElementById('previewModal');
    const previewBtn = document.getElementById('previewBtn');
    const closePreviewModalBtn = document.getElementById('closePreviewModalBtn');
    const closePreviewBtn = document.getElementById('closePreviewBtn');
    const submitFromPreviewBtn = document.getElementById('submitFromPreviewBtn');
    const previewContent = document.getElementById('previewContent');

    function openPreviewModal() {
        previewModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closePreviewModal() {
        previewModal.classList.remove('active');
        document.body.style.overflow = '';
    }

    function generatePreview() {
        const name = document.getElementById('name').value || 'Not provided';
        const email = document.getElementById('email').value || 'Not provided';
        const primaryPhone = document.getElementById('primary_phone').value || 'Not provided';
        const additionalPhones = Array.from(document.querySelectorAll('input[name="additional_phones[]"]'))
            .map(input => input.value).filter(v => v);
        
        const propertyName = document.getElementById('property_name').value || 'Not provided';
        const plotNumber = document.getElementById('plot_number').value || 'Not provided';
        const streetName = document.getElementById('street_name').value || 'Not provided';
        const digitalAddress = document.getElementById('digital_address').value || 'Not provided';
        const landDescription = document.getElementById('land_description').value || 'Not provided';
        
        const hasLandDoc = document.getElementById('land_ownership_document').files.length > 0;
        
        let registrationType = 'Not selected';
        if (selectedType === 'vacant_land') {
            registrationType = 'Vacant Land';
        } else if (selectedType === 'existing_property') {
            registrationType = 'Existing Property';
        }
        
        let constructionDetails = {};
        if (selectedType === 'vacant_land') {
            const provideDetails = document.getElementById('provide_construction_details').checked;
            if (provideDetails) {
                const propertyType = document.getElementById('property_type').value || 'Not selected';
                const customType = document.getElementById('custom_property_type').value || '';
                const propertyStatus = document.getElementById('property_status').value || 'Not selected';
                const estimatedBedrooms = document.getElementById('estimated_bedrooms').value || 'Not provided';
                const estimatedCompletion = document.getElementById('estimated_completion').value || 'Not provided';
                const hasPlans = document.getElementById('has_plans').checked ? 'Yes' : 'No';
                const hasConstructionDocs = document.getElementById('construction_documents').files.length > 0;
                
                constructionDetails = {
                    propertyType: propertyType === 'other' ? customType || 'Other (specified)' : propertyType,
                    propertyStatus: propertyStatus,
                    estimatedBedrooms: estimatedBedrooms,
                    estimatedCompletion: estimatedCompletion,
                    hasPlans: hasPlans,
                    hasConstructionDocs: hasConstructionDocs
                };
            } else {
                constructionDetails = { notProvided: true };
            }
        }
        
        let existingPropertyDetails = {};
        if (selectedType === 'existing_property') {
            const existingType = document.getElementById('existing_property_type').value || 'Not selected';
            const customExistingType = document.getElementById('existing_custom_property_type').value || '';
            const existingStatus = document.getElementById('existing_property_status').value || 'Not selected';
            const yearBuilt = document.getElementById('year_built').value || 'Not provided';
            const bedrooms = document.getElementById('existing_bedrooms').value || 'Not provided';
            const bathrooms = document.getElementById('existing_bathrooms').value || 'Not provided';
            const hasPhotos = document.getElementById('property_photos').files.length > 0;
            const hasPropertyDocs = document.getElementById('property_documents').files.length > 0;
            
            existingPropertyDetails = {
                propertyType: existingType === 'other' ? customExistingType || 'Other (specified)' : existingType,
                propertyStatus: existingStatus,
                yearBuilt: yearBuilt,
                bedrooms: bedrooms,
                bathrooms: bathrooms,
                hasPhotos: hasPhotos,
                hasPropertyDocs: hasPropertyDocs
            };
        }
        
        let tenantData = [];
        if (selectedType === 'existing_property' && document.getElementById('has_tenants').checked) {
            const tenantEntriesList = document.querySelectorAll('.tenant-entry');
            tenantEntriesList.forEach((entry, index) => {
                const nameInputTenant = entry.querySelector(`[name="tenant_name_${index}"]`);
                const phoneInputTenant = entry.querySelector(`[name="tenant_phone_${index}"]`);
                const emailInputTenant = entry.querySelector(`[name="tenant_email_${index}"]`);
                
                if (nameInputTenant && nameInputTenant.value) {
                    tenantData.push({
                        name: nameInputTenant.value,
                        phone: phoneInputTenant?.value || 'Not provided',
                        email: emailInputTenant?.value || 'Not provided'
                    });
                }
            });
        }
        
        let html = `
            <div class="preview-section">
                <h4>Registration Type</h4>
                <div class="preview-item">
                    <span class="preview-label">Type</span>
                    <span class="preview-value"><span class="badge badge-primary">${registrationType}</span></span>
                </div>
            </div>
            
            <div class="preview-section">
                <h4>Landlord Information</h4>
                <div class="preview-item">
                    <span class="preview-label">Full Name</span>
                    <span class="preview-value">${escapeHtml(name)}</span>
                </div>
                <div class="preview-item">
                    <span class="preview-label">Email</span>
                    <span class="preview-value">${escapeHtml(email)}</span>
                </div>
                <div class="preview-item">
                    <span class="preview-label">Primary Phone</span>
                    <span class="preview-value">${escapeHtml(primaryPhone)}</span>
                </div>
                ${additionalPhones.length > 0 ? `
                <div class="preview-item">
                    <span class="preview-label">Additional Phones</span>
                    <span class="preview-value">${additionalPhones.map(p => escapeHtml(p)).join(', ')}</span>
                </div>
                ` : ''}
            </div>
            
            <div class="preview-section">
                <h4>Property/Land Details</h4>
                <div class="preview-item">
                    <span class="preview-label">Property/Land Name</span>
                    <span class="preview-value">${escapeHtml(propertyName)}</span>
                </div>
                <div class="preview-item">
                    <span class="preview-label">Plot Number</span>
                    <span class="preview-value">${escapeHtml(plotNumber)}</span>
                </div>
                <div class="preview-item">
                    <span class="preview-label">Street Name</span>
                    <span class="preview-value">${escapeHtml(streetName)}</span>
                </div>
                <div class="preview-item">
                    <span class="preview-label">Digital Address</span>
                    <span class="preview-value">${escapeHtml(digitalAddress)}</span>
                </div>
                <div class="preview-item">
                    <span class="preview-label">Description</span>
                    <span class="preview-value">${escapeHtml(landDescription)}</span>
                </div>
                <div class="preview-item">
                    <span class="preview-label">Land Ownership Document</span>
                    <span class="preview-value">${hasLandDoc ? '<span class="badge badge-success"><i class="fas fa-check"></i> Uploaded</span>' : '<span class="preview-empty">Not uploaded</span>'}</span>
                </div>
            </div>
        `;
        
        if (selectedType === 'vacant_land') {
            html += `
                <div class="preview-section">
                    <h4>Construction Details</h4>
                    ${constructionDetails.notProvided ? `
                        <div class="preview-item">
                            <span class="preview-label">Status</span>
                            <span class="preview-value"><span class="badge badge-info">Will provide later</span></span>
                        </div>
                    ` : `
                        <div class="preview-item">
                            <span class="preview-label">Property Type</span>
                            <span class="preview-value">${escapeHtml(constructionDetails.propertyType)}</span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Construction Status</span>
                            <span class="preview-value">${escapeHtml(constructionDetails.propertyStatus)}</span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Estimated Bedrooms</span>
                            <span class="preview-value">${escapeHtml(constructionDetails.estimatedBedrooms)}</span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Estimated Completion</span>
                            <span class="preview-value">${escapeHtml(constructionDetails.estimatedCompletion)}</span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Has Plans</span>
                            <span class="preview-value">${escapeHtml(constructionDetails.hasPlans)}</span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Construction Documents</span>
                            <span class="preview-value">${constructionDetails.hasConstructionDocs ? '<span class="badge badge-success"><i class="fas fa-check"></i> Uploaded</span>' : '<span class="preview-empty">Not uploaded</span>'}</span>
                        </div>
                    `}
                </div>
            `;
        }
        
        if (selectedType === 'existing_property') {
            html += `
                <div class="preview-section">
                    <h4>Existing Property Details</h4>
                    <div class="preview-item">
                        <span class="preview-label">Property Type</span>
                        <span class="preview-value">${escapeHtml(existingPropertyDetails.propertyType)}</span>
                    </div>
                    <div class="preview-item">
                        <span class="preview-label">Property Status</span>
                        <span class="preview-value">${escapeHtml(existingPropertyDetails.propertyStatus)}</span>
                    </div>
                    <div class="preview-item">
                        <span class="preview-label">Year Built</span>
                        <span class="preview-value">${escapeHtml(existingPropertyDetails.yearBuilt)}</span>
                    </div>
                    <div class="preview-item">
                        <span class="preview-label">Bedrooms</span>
                        <span class="preview-value">${escapeHtml(existingPropertyDetails.bedrooms)}</span>
                    </div>
                    <div class="preview-item">
                        <span class="preview-label">Bathrooms</span>
                        <span class="preview-value">${escapeHtml(existingPropertyDetails.bathrooms)}</span>
                    </div>
                    <div class="preview-item">
                        <span class="preview-label">Property Photos</span>
                        <span class="preview-value">${existingPropertyDetails.hasPhotos ? '<span class="badge badge-success"><i class="fas fa-check"></i> Uploaded</span>' : '<span class="preview-empty">Not uploaded</span>'}</span>
                    </div>
                    <div class="preview-item">
                        <span class="preview-label">Property Documents</span>
                        <span class="preview-value">${existingPropertyDetails.hasPropertyDocs ? '<span class="badge badge-success"><i class="fas fa-check"></i> Uploaded</span>' : '<span class="preview-empty">Not uploaded</span>'}</span>
                    </div>
                </div>
            `;
        }
        
        if (selectedType === 'existing_property') {
            html += `
                <div class="preview-section">
                    <h4>Tenant Information</h4>
                    ${document.getElementById('has_tenants').checked ? `
                        <div class="preview-item">
                            <span class="preview-label">Has Tenants</span>
                            <span class="preview-value"><span class="badge badge-success">Yes</span></span>
                        </div>
                        ${tenantData.length > 0 ? `
                            <div class="preview-item" style="flex-direction: column; align-items: flex-start;">
                                <span class="preview-label" style="width: 100%; margin-bottom: 0.5rem;">Tenants (${tenantData.length})</span>
                                <span class="preview-value" style="width: 100%;">
                                    ${tenantData.map((t, i) => `
                                        <div style="padding: 0.3rem 0; border-bottom: 1px solid var(--border-color);">
                                            <strong>#${i + 1}</strong> ${escapeHtml(t.name)} - ${escapeHtml(t.phone)} ${t.email !== 'Not provided' ? `(${escapeHtml(t.email)})` : ''}
                                        </div>
                                    `).join('')}
                                </span>
                            </div>
                        ` : `
                            <div class="preview-item">
                                <span class="preview-label">Tenants</span>
                                <span class="preview-value"><span class="preview-empty">No tenants added yet</span></span>
                            </div>
                        `}
                    ` : `
                        <div class="preview-item">
                            <span class="preview-label">Has Tenants</span>
                            <span class="preview-value"><span class="badge badge-info">No</span></span>
                        </div>
                    `}
                </div>
            `;
        }
        
        const declarationChecked = document.getElementById('declaration').checked;
        html += `
            <div class="preview-section">
                <h4>Declaration</h4>
                <div class="preview-item">
                    <span class="preview-label">Status</span>
                    <span class="preview-value">${declarationChecked ? '<span class="badge badge-success"><i class="fas fa-check"></i> Accepted</span>' : '<span class="badge badge-danger"><i class="fas fa-times"></i> Not accepted</span>'}</span>
                </div>
            </div>
        `;
        
        return html;
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    if (previewBtn) {
        previewBtn.addEventListener('click', (e) => {
            e.preventDefault();
            
            if (!selectedType) {
                Toast.warning('Please select a registration type first.');
                return;
            }
            
            const name = document.getElementById('name').value.trim();
            const primaryPhone = document.getElementById('primary_phone').value.trim();
            const propertyName = document.getElementById('property_name').value.trim();
            const plotNumber = document.getElementById('plot_number').value.trim();
            const streetName = document.getElementById('street_name').value.trim();
            
            if (!name || !primaryPhone || !propertyName || !plotNumber || !streetName) {
                Toast.warning('Please fill in all required fields before previewing.');
                return;
            }
            
            if (!isValidPhoneNumber(primaryPhone)) {
                Toast.warning('Please enter a valid phone number.');
                return;
            }
            
            const landDoc = document.getElementById('land_ownership_document');
            if (!landDoc.files || landDoc.files.length === 0) {
                Toast.warning('Please upload the land ownership document.');
                return;
            }
            
            const previewHtml = generatePreview();
            previewContent.innerHTML = previewHtml;
            openPreviewModal();
        });
        previewBtn.addEventListener('touchend', function(e) {
            e.preventDefault();
            this.click();
        });
    }

    if (closePreviewModalBtn) {
        closePreviewModalBtn.addEventListener('click', closePreviewModal);
        closePreviewModalBtn.addEventListener('touchend', function(e) {
            e.preventDefault();
            closePreviewModal();
        });
    }

    if (closePreviewBtn) {
        closePreviewBtn.addEventListener('click', closePreviewModal);
        closePreviewBtn.addEventListener('touchend', function(e) {
            e.preventDefault();
            closePreviewModal();
        });
    }

    if (submitFromPreviewBtn) {
        submitFromPreviewBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closePreviewModal();
            const form = document.getElementById('registrationForm');
            if (form) {
                const declaration = document.getElementById('declaration');
                if (!declaration.checked) {
                    Toast.warning('Please accept the declaration before submitting.');
                    return;
                }
                form.dispatchEvent(new Event('submit'));
            }
        });
        submitFromPreviewBtn.addEventListener('touchend', function(e) {
            e.preventDefault();
            this.click();
        });
    }

    previewModal.addEventListener('click', (e) => {
        if (e.target === previewModal) closePreviewModal();
    });

    const registrationForm = document.getElementById('registrationForm');

    function validateRegistrationForm() {
        let isValid = true;

        if (!selectedType) {
            Toast.warning('Please select whether you want to register vacant land or an existing property');
            return false;
        }

        const requiredFields = ['name', 'primary_phone', 'property_name', 'plot_number', 'street_name'];
        for (const fieldId of requiredFields) {
            const field = document.getElementById(fieldId);
            if (field && !field.value.trim()) {
                const errorEl = document.getElementById(fieldId + 'Error');
                if (errorEl) errorEl.textContent = 'This field is required';
                isValid = false;
            }
        }

        const primaryPhone = document.getElementById('primary_phone').value;
        if (!isValidPhoneNumber(primaryPhone)) {
            const phoneError = document.getElementById('primaryPhoneError');
            if (phoneError) phoneError.textContent = 'Please enter a valid phone number (e.g., 0595652410)';
            isValid = false;
        }

        const declaration = document.getElementById('declaration');
        if (!declaration.checked) {
            Toast.warning('Please accept the declaration to proceed');
            return false;
        }

        return isValid;
    }

    function cleanBOM(text) {
        if (!text) return text;
        
        let cleaned = text;
        
        if (cleaned.charCodeAt(0) === 0xFEFF) {
            cleaned = cleaned.substring(1);
        }
        
        if (cleaned.startsWith('\uFEFF')) {
            cleaned = cleaned.substring(1);
        }
        
        if (cleaned.startsWith('﻿')) {
            cleaned = cleaned.substring(1);
        }
        
        if (cleaned.length >= 3 && 
            cleaned.charCodeAt(0) === 0xEF && 
            cleaned.charCodeAt(1) === 0xBB && 
            cleaned.charCodeAt(2) === 0xBF) {
            cleaned = cleaned.substring(3);
        }
        
        return cleaned;
    }

    registrationForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!validateRegistrationForm()) {
            return;
        }

        const submitBtn = document.getElementById('submitRegBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner"></span> Submitting...';

        const formData = new FormData(registrationForm);

        const provideConstruction = document.getElementById('provide_construction_details');
        const isConstructionProvided = provideConstruction && provideConstruction.checked;
        
        formData.set('include_construction', isConstructionProvided ? '1' : '0');
        
        if (selectedType === 'vacant_land') {
            if (isConstructionProvided) {
                formData.set('purpose', 'both');
            } else {
                formData.set('purpose', 'construction');
            }
            formData.set('registration_type', 'construction');
        } else if (selectedType === 'existing_property') {
            formData.set('registration_type', 'property_capture');
            formData.set('purpose', 'permanent_registration');
        }

        if (hasTenantsCheckbox && hasTenantsCheckbox.checked) {
            const tenants = [];
            const tenantEntriesList = document.querySelectorAll('.tenant-entry');
            tenantEntriesList.forEach((entry, index) => {
                const nameInputTenant = entry.querySelector(`[name="tenant_name_${index}"]`);
                const phoneInputTenant = entry.querySelector(`[name="tenant_phone_${index}"]`);
                const emailInputTenant = entry.querySelector(`[name="tenant_email_${index}"]`);
                const notesInput = entry.querySelector(`[name="tenant_notes_${index}"]`);
                
                if (nameInputTenant && nameInputTenant.value && phoneInputTenant && phoneInputTenant.value) {
                    tenants.push({
                        name: nameInputTenant.value,
                        phone: phoneInputTenant.value,
                        email: emailInputTenant?.value || null,
                        notes: notesInput?.value || null
                    });
                }
            });
            
            if (tenants.length > 0) {
                formData.append('tenant_data_json', JSON.stringify(tenants));
                formData.append('tenant_count', tenants.length);
            }
        }

        console.log('📦 Submitting registration with:');
        console.log('  registration_type:', formData.get('registration_type'));
        console.log('  purpose:', formData.get('purpose'));
        console.log('  include_construction:', formData.get('include_construction'));
        console.log('  selectedType:', selectedType);
        console.log('  isConstructionProvided:', isConstructionProvided);

        try {
            const response = await fetch('{{ route("landlord.construction.register") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            let data;
            const contentType = response.headers.get('content-type');
            
            if (contentType && contentType.includes('application/json')) {
                const text = await response.text();
                const cleanText = cleanBOM(text);
                
                try {
                    data = JSON.parse(cleanText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    const jsonMatch = cleanText.match(/\{.*\}/s);
                    if (jsonMatch) {
                        try {
                            data = JSON.parse(jsonMatch[0]);
                        } catch (e) {
                            throw new Error('Invalid JSON response from server');
                        }
                    } else {
                        throw new Error('Invalid JSON response from server');
                    }
                }
            } else {
                const text = await response.text();
                const cleanText = cleanBOM(text);
                
                try {
                    data = JSON.parse(cleanText);
                } catch (e) {
                    data = {
                        success: response.ok,
                        message: response.ok ? 'Registration submitted successfully' : 'Server error occurred',
                        raw: text
                    };
                }
            }

            if (response.ok && data.success) {
                Toast.success(data.message || 'Your registration has been submitted successfully!');
                
                closeRegistrationModal();
                
                if (data.access_token) {
                    setTimeout(() => {
                        Toast.info(`Your tracking token: ${data.access_token}. Please save this to check your registration status.`, 8000);
                    }, 500);
                }

                setTimeout(() => {
                    location.reload();
                }, 3000);

            } else if (response.ok && !data.success) {
                if (data.errors) {
                    for (let key in data.errors) {
                        Toast.error(`${key}: ${data.errors[key][0]}`);
                    }
                } else {
                    Toast.error(data.message || 'Registration failed. Please check your details and try again.');
                }
            } else {
                let errorMessage = data?.message || data?.error || 'Server error occurred. Please try again.';
                
                if (response.status === 422) {
                    if (data.errors) {
                        for (let key in data.errors) {
                            Toast.error(`${key}: ${data.errors[key][0]}`);
                        }
                        return;
                    }
                    errorMessage = 'Please check your form for errors and try again.';
                } else if (response.status === 403) {
                    errorMessage = 'You do not have permission to register. Please login or contact support.';
                } else if (response.status === 429) {
                    errorMessage = 'Too many registration attempts. Please wait a moment and try again.';
                } else if (response.status === 500) {
                    errorMessage = 'Server error. Our team has been notified. Please try again later.';
                }
                
                Toast.error(errorMessage);
            }

        } catch (error) {
            console.error('Registration error:', error);
            
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                Toast.error('Network connection issue. Please check your internet and try again.');
            } else if (error.name === 'AbortError') {
                Toast.error('Request was cancelled. Please try again.');
            } else if (error.message) {
                Toast.error('An error occurred: ' + error.message);
            } else {
                Toast.error('Something went wrong. Please try again or contact support.');
            }
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    // ============================================
    // TOUCH EVENT HANDLING FOR ALL BUTTONS
    // ============================================
    document.querySelectorAll('button, .btn, .reg-type-card, .carousel-btn, .carousel-dot, .social-icons-footer a, .footer-links a, .theme-toggle, .mobile-menu-btn, .modal-close, .file-upload-area, .add-tenant-btn, .add-phone-btn').forEach(el => {
        el.addEventListener('touchstart', function(e) {
            this.style.touchAction = 'manipulation';
        }, { passive: true });
    });

    // ============================================
    // ⭐ FLOATING CHAT TOGGLE (visible to guests + auth)
    // ============================================
    (function () {
        const launcher = document.getElementById('floatingChatLauncher');
        const panel    = document.getElementById('floatingChatPanel');
        const closeBtn = document.getElementById('floatingChatClose');
        if (!launcher || !panel) return;

        function open() {
            panel.classList.add('open');
            launcher.style.display = 'none';
        }
        function close() {
            panel.classList.remove('open');
            launcher.style.display = 'flex';
        }

        launcher.addEventListener('click', open);
        launcher.addEventListener('touchend', (e) => { e.preventDefault(); open(); });

        if (closeBtn) {
            closeBtn.addEventListener('click', close);
            closeBtn.addEventListener('touchend', (e) => { e.preventDefault(); close(); });
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && panel.classList.contains('open')) close();
        });

        // Auto-open after 20 seconds on first visit per session
        if (!sessionStorage.getItem('chatAutoOpened')) {
            setTimeout(() => {
                if (!panel.classList.contains('open')) {
                    open();
                    sessionStorage.setItem('chatAutoOpened', '1');
                }
            }, 20000);
        }
    })();

    console.log('Homepage loaded with full responsiveness, touch support, preview functionality, and floating chat widget (guest-accessible)');
</script>

{{-- Stack for pushed scripts (chat widget JS) --}}
@stack('scripts')
</body>
</html>