<!-- Using only Tailwind CSS -->
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* =====================================================
       CSS VARIABLES AND BASE THEME TOKENS
       ===================================================== */
    :root {
        --primary: #7267f0;
        --secondary: #5e59e6;
        --success: #28c76f;
        --danger: #ea5455;
        --warning: #ff9f43;
        --info: #00cfe8;
        --light: #f6f6f6;
        --dark: #4b4b4b;
        --primary-rgb: 114, 103, 240;
        --secondary-rgb: 94, 89, 230;
        --success-rgb: 40, 199, 111;
        --warning-rgb: 255, 159, 67;
        --danger-rgb: 234, 84, 85;
        --info-rgb: 0, 207, 232;

        /* Layout constants — keep in sync with JS */
        --sidebar-width: 280px;
        --sidebar-collapsed-width: 70px;
    }

    [data-theme="dark"] {
        --primary: #8c82ff;
        --secondary: #7873ff;
        --success: #3ae187;
        --danger: #ff6b6b;
        --warning: #ffb74d;
        --info: #2dd4e8;
        --light: #2a2a2a;
        --dark: #f5f5f5;
        --bg-primary: #1e1e2d;
        --bg-secondary: #2a2a3c;
        --text-primary: #e4e4e4;
        --text-secondary: #a0a0a0;
        --card-bg: #2a2a3c;
        --header-bg: #2a2a3c;
        --border-color: #39394a;
    }

    [data-theme="light"] {
        --bg-primary: #f8f8f8;
        --bg-secondary: #ffffff;
        --text-primary: #4b4b4b;
        --text-secondary: #6b7280;
        --card-bg: #ffffff;
        --header-bg: #ffffff;
        --border-color: #e5e7eb;
    }

    /* =====================================================
       SIDEBAR THEMES - Modernized
       ===================================================== */
    [data-sidebar-theme="default"] {
        --sidebar-bg: linear-gradient(180deg, var(--primary) 0%, #6258e0 100%);
        --sidebar-text: #ffffff;
        --sidebar-text-hover: #ffffff;
        --sidebar-hover: rgba(255, 255, 255, 0.12);
        --sidebar-active: rgba(255, 255, 255, 0.2);
        --sidebar-border: transparent;
        --sidebar-shadow: 0 0 20px rgba(114, 103, 240, 0.3);
        --sidebar-scrollbar-thumb: rgba(255, 255, 255, 0.25);
        --sidebar-scrollbar-thumb-hover: rgba(255, 255, 255, 0.45);
    }

    [data-sidebar-theme="dark"] {
        --sidebar-bg: linear-gradient(180deg, #232933 0%, #1e2229 100%);
        --sidebar-text: #ecf0f1;
        --sidebar-text-hover: #ffffff;
        --sidebar-hover: rgba(236, 240, 241, 0.08);
        --sidebar-active: rgba(52, 152, 219, 0.2);
        --sidebar-border: #3498db;
        --sidebar-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
        --sidebar-scrollbar-thumb: rgba(255, 255, 255, 0.18);
        --sidebar-scrollbar-thumb-hover: rgba(255, 255, 255, 0.35);
    }

    [data-sidebar-theme="light"] {
        --sidebar-bg: linear-gradient(180deg, #ffffff 0%, #f5f7f9 100%);
        --sidebar-text: #4a5568;
        --sidebar-text-hover: #1e293b;
        --sidebar-hover: rgba(114, 103, 240, 0.1);
        --sidebar-active: rgba(114, 103, 240, 0.15);
        --sidebar-border: #7267f0;
        --sidebar-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        --sidebar-scrollbar-thumb: rgba(0, 0, 0, 0.18);
        --sidebar-scrollbar-thumb-hover: rgba(0, 0, 0, 0.35);
    }

    [data-sidebar-theme="blue"] {
        --sidebar-bg: linear-gradient(180deg, #1a56db 0%, #1e429f 100%);
        --sidebar-text: #ffffff;
        --sidebar-text-hover: #ffffff;
        --sidebar-hover: rgba(255, 255, 255, 0.12);
        --sidebar-active: rgba(100, 255, 218, 0.2);
        --sidebar-border: #64FFDA;
        --sidebar-shadow: 0 0 20px rgba(26, 86, 219, 0.3);
        --sidebar-scrollbar-thumb: rgba(255, 255, 255, 0.25);
        --sidebar-scrollbar-thumb-hover: rgba(255, 255, 255, 0.45);
    }

    [data-sidebar-theme="green"] {
        --sidebar-bg: linear-gradient(180deg, #057a55 0%, #0a5c36 100%);
        --sidebar-text: #ffffff;
        --sidebar-text-hover: #ffffff;
        --sidebar-hover: rgba(255, 255, 255, 0.12);
        --sidebar-active: rgba(105, 240, 174, 0.2);
        --sidebar-border: #69F0AE;
        --sidebar-shadow: 0 0 20px rgba(5, 122, 85, 0.3);
        --sidebar-scrollbar-thumb: rgba(255, 255, 255, 0.25);
        --sidebar-scrollbar-thumb-hover: rgba(255, 255, 255, 0.45);
    }

    body {
        background-color: var(--bg-primary);
        color: var(--text-primary);
        transition: all 0.3s ease;
        overflow-x: hidden;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.6;
    }

    /* =====================================================
       SIDEBAR - BASE LAYOUT
       FIXED: overflow changed from hidden → visible so the
       toggle button can hang outside the sidebar's right edge.
       The nav child handles its own scroll clipping.
       FIXED: z-index raised 50 → 60 so the button paints above
       the sticky header (z-index 40).
       ===================================================== */
    .sidebar {
        width: var(--sidebar-width);
        height: 100vh;
        background: var(--sidebar-bg);
        transition: width 0.3s ease;
        border-right: 1px solid var(--border-color);
        position: fixed;
        z-index: 60;                       /* ← FIXED: was 50; now above .header (40) */
        left: 0;
        top: 0;
        box-shadow: var(--sidebar-shadow);

        /* flex column so nav can grow + scroll, footer pinned */
        display: flex;
        flex-direction: column;

        /* ← FIXED: was `hidden`, which clipped the toggle button's -14px offset */
        overflow: visible;

        /* Guarantee our own stacking context above the sticky header */
        isolation: isolate;
    }

    /* =====================================================
       SIDEBAR NAV — SCROLL CONTAINER
       Nav gets its own overflow so the sidebar itself stays visible.
       ===================================================== */
    .sidebar > nav {
        flex: 1 1 auto;                    /* take remaining vertical space */
        min-height: 0;                     /* REQUIRED for flex child to scroll */
        overflow-y: auto;                  /* scrollbar appears here */
        overflow-x: hidden;
        padding-bottom: 70px;              /* clears the absolutely-positioned settings gear */
        scrollbar-width: thin;             /* Firefox */
        scrollbar-color: var(--sidebar-scrollbar-thumb, rgba(255,255,255,0.25)) transparent;
        -webkit-overflow-scrolling: touch; /* smooth momentum scroll on iOS */
        overscroll-behavior: contain;      /* don't chain-scroll to body */
    }

    /* WebKit scrollbar styling — matches sidebar theme */
    .sidebar > nav::-webkit-scrollbar { width: 6px; }
    .sidebar > nav::-webkit-scrollbar-track { background: transparent; }
    .sidebar > nav::-webkit-scrollbar-thumb {
        background: var(--sidebar-scrollbar-thumb, rgba(255, 255, 255, 0.25));
        border-radius: 3px;
        transition: background-color 0.2s ease;
    }
    .sidebar > nav::-webkit-scrollbar-thumb:hover {
        background: var(--sidebar-scrollbar-thumb-hover, rgba(255, 255, 255, 0.45));
    }

    /* Hide the scrollbar when collapsed */
    .sidebar.collapsed > nav {
        overflow-y: hidden;
    }

    /* Content area */
    .content {
        transition: all 0.3s ease;
        margin-left: var(--sidebar-width);
        background-color: var(--bg-primary);
        min-height: 100vh;
        width: calc(100% - var(--sidebar-width));
    }

    .content.collapsed {
        margin-left: var(--sidebar-collapsed-width);
        width: calc(100% - var(--sidebar-collapsed-width));
    }

    main.collapsed {
        margin-left: var(--sidebar-collapsed-width);
    }

    /* =====================================================
       SIDEBAR COLLAPSED MODE - FIXED WIDTH (70px)
       Section dividers + labels + badges hidden
       ===================================================== */
    .sidebar.collapsed {
        width: var(--sidebar-collapsed-width) !important;
        min-width: var(--sidebar-collapsed-width) !important;
        max-width: var(--sidebar-collapsed-width) !important;
    }

    /* Hide section dividers completely when collapsed */
    .sidebar.collapsed .nav-divider {
        display: none !important;
        visibility: hidden !important;
        height: 0 !important;
        min-height: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        border: 0 !important;
    }

    /* Compact nav items in collapsed mode */
    .sidebar.collapsed nav {
        padding: 4px 0 !important;
    }

    .sidebar.collapsed .nav-item {
        padding: 8px 0 !important;
        margin: 2px 4px !important;
        border-radius: 8px !important;
        justify-content: center !important;
        width: calc(100% - 8px) !important;
        min-height: 40px !important;
        display: flex !important;
        align-items: center !important;
    }

    .sidebar.collapsed .nav-item i {
        margin: 0 !important;
        font-size: 1.15rem !important;
    }

    .sidebar.collapsed .nav-item .nav-text,
    .sidebar.collapsed .nav-item span.ml-auto,
    .sidebar.collapsed .nav-item .ml-auto,
    .sidebar.collapsed .nav-item .badge,
    .sidebar.collapsed .nav-item .chevron-right,
    .sidebar.collapsed .nav-item .flex-grow {
        display: none !important;
    }

    /* Hide badges and counts in collapsed mode */
    .sidebar.collapsed .nav-item .bg-yellow-500,
    .sidebar.collapsed .nav-item .bg-red-500,
    .sidebar.collapsed .nav-item .bg-blue-500,
    .sidebar.collapsed .nav-item .bg-orange-500 {
        display: none !important;
    }

    /* Compact logo section */
    .sidebar.collapsed .logo-section {
        padding: 10px 6px !important;
        position: relative !important;
    }

    .sidebar.collapsed .logo-text-container,
    .sidebar.collapsed .logo-fullname,
    .sidebar.collapsed .logo-shortname {
        display: none !important;
    }

    .sidebar.collapsed .logo-wrapper {
        justify-content: center !important;
    }

    .sidebar.collapsed .logo-image-container,
    .sidebar.collapsed .logo-default {
        width: 32px !important;
        height: 32px !important;
    }

    .sidebar.collapsed .logo-separator {
        margin: 4px 8px !important;
    }

    .sidebar.collapsed .absolute.bottom-0 {
        padding: 6px 0 !important;
    }

    .sidebar.collapsed #settingsIcon {
        font-size: 1rem !important;
    }

    /* Active state in collapsed mode */
    .sidebar.collapsed .nav-item.active {
        background: rgba(var(--primary-rgb), 0.15) !important;
        border-left: 3px solid var(--primary) !important;
    }

    /* Remove extra spacing between nav items in collapsed mode */
    .sidebar.collapsed .nav-divider + .nav-item {
        margin-top: 2px !important;
    }

    .sidebar.collapsed .nav-item + .nav-item {
        margin-top: 2px !important;
    }

    /* Ensure buttons are properly centered in collapsed mode */
    .sidebar.collapsed .nav-item button,
    .sidebar.collapsed .nav-item a {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 100% !important;
        padding: 0 !important;
    }

    /* Hide any remaining text or labels in collapsed mode */
    .sidebar.collapsed .menu-text,
    .sidebar.collapsed .nav-divider .menu-text {
        display: none !important;
    }

    /* =====================================================
       SIDEBAR TOGGLE BUTTON — FIXED
       Was previously:
         - clipped by .sidebar { overflow: hidden }
         - buried under .header (z-index 40) due to z-index 100
           living inside a lower stacking context
         - anchored to .logo-section (short height), so its
           "50%" was only ~45px from the top of the page
       Now:
         - .sidebar has overflow: visible + z-index 60 + isolation: isolate
         - button has z-index 200 and is a direct child of .sidebar
         - hidden on mobile where the header hamburger takes over
       ===================================================== */
    .toggle-sidebar {
        /* Anchor: direct child of .sidebar, so "50%" = sidebar's midpoint */
        position: absolute;
        top: 50%;
        right: -14px;
        transform: translateY(-50%);

        /* Size + shape */
        width: 28px;
        height: 28px;
        border-radius: 50%;

        /* Layout */
        display: flex;
        align-items: center;
        justify-content: center;

        /* Colors — inherits --primary so it adapts to any sidebar theme */
        background-color: var(--primary);
        color: #ffffff;
        border: 2px solid var(--primary);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);

        /* Interaction */
        cursor: pointer;
        user-select: none;
        -webkit-tap-highlight-color: transparent;

        /* ← FIXED: was 100; now 200 to beat header (40) and overlay (45) */
        z-index: 200;
        pointer-events: auto;

        /* Composite hint for smoother animation */
        will-change: transform;

        transition:
            background-color 0.2s ease,
            border-color 0.2s ease,
            transform 0.2s ease,
            box-shadow 0.2s ease;
    }

    .toggle-sidebar:hover {
        background-color: var(--secondary);
        border-color: var(--secondary);
        transform: translateY(-50%) scale(1.1);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
    }

    .toggle-sidebar:active {
        transform: translateY(-50%) scale(0.95);
    }

    .toggle-sidebar:focus-visible {
        outline: 2px solid #ffffff;
        outline-offset: 2px;
    }

    .toggle-sidebar i {
        font-size: 12px;
        line-height: 1;
        transition: transform 0.3s ease;
    }

    /* Collapsed state — flip the chevron, keep the button in place */
    .sidebar.collapsed .toggle-sidebar {
        right: -14px;
        background-color: var(--primary);
        border-color: var(--primary);
        color: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }

    .sidebar.collapsed .toggle-sidebar i {
        transform: rotate(180deg);
    }

    .sidebar.collapsed .toggle-sidebar:hover {
        background-color: var(--secondary);
        border-color: var(--secondary);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    }

    /* Logo section no longer needs to host the toggle button,
       but keeping position: relative is harmless and useful as a
       stacking context for internal elements. */
    .logo-section {
        position: relative;
    }

    /* =====================================================
       SIDEBAR FOOTER (settings gear) — flex instead of absolute
       ===================================================== */
    .sidebar-footer,
    .sidebar > .absolute.bottom-0 {
        flex-shrink: 0;                    /* never shrinks; nav scrolls above it */
        background-color: transparent;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        position: static !important;       /* override the absolute positioning */
    }
    [data-sidebar-theme="light"] .sidebar-footer,
    [data-sidebar-theme="light"] .sidebar > .absolute.bottom-0 {
        border-top-color: rgba(0, 0, 0, 0.06);
    }

    /* =====================================================
       NAV ITEMS
       ===================================================== */
    .nav-item {
        transition: all 0.3s;
        border-left: 3px solid transparent;
        color: var(--sidebar-text);
        margin: 0 12px;
        border-radius: 8px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        cursor: pointer;
    }

    .nav-item:hover,
    .nav-item.active {
        background-color: var(--sidebar-hover);
        border-left-color: var(--sidebar-active);
        transform: translateX(4px);
    }

    .nav-item.active {
        color: var(--primary) !important;
        background-color: rgba(var(--primary-rgb), 0.15) !important;
    }

    .nav-item.active i {
        color: var(--primary) !important;
        opacity: 1;
    }

    .nav-divider {
        color: var(--sidebar-text);
        opacity: 0.7;
        padding-left: 24px;
        margin-top: 16px;
        margin-bottom: 8px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    /* Sidebar icons */
    .sidebar .nav-item i,
    .sidebar button i {
        color: var(--sidebar-text, #ffffff) !important;
        opacity: 0.8;
        transition: all 0.2s ease;
    }

    .sidebar .nav-item:hover i,
    .sidebar button:hover i {
        color: var(--sidebar-text-hover, var(--primary)) !important;
        opacity: 1;
    }

    /* =====================================================
       CARDS
       ===================================================== */
    .card {
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        transition: transform 0.3s, box-shadow 0.3s;
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        overflow: hidden;
        backdrop-filter: blur(10px);
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .stat-card {
        border-left: 4px solid;
        padding: 24px;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 80px;
        height: 80px;
        opacity: 0.1;
        background-size: contain;
        background-repeat: no-repeat;
        background-position: center right;
    }

    .users-card { border-left-color: var(--primary); }
    .users-card::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%237267f0' viewBox='0 0 24 24'%3E%3Cpath d='M12 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-2a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm9 11a1 1 0 0 1-2 0 7 7 0 1 0-14 0 1 1 0 0 1-2 0 9 9 0 1 1 18 0z'/%3E%3C/svg%3E");
    }

    .revenue-card { border-left-color: var(--success); }
    .revenue-card::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%2328c76f' viewBox='0 0 24 24'%3E%3Cpath d='M3 0h18a3 3 0 0 1 3 3v18a3 3 0 0 1-3 3H3a3 3 0 0 1-3-3V3a3 3 0 0 1 3-3zm1 7a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H4zm2 2h10v2H6V9zm0 4h6v2H6v-2z'/%3E%3C/svg%3E");
    }

    .conversion-card { border-left-color: var(--warning); }
    .conversion-card::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23ff9f43' viewBox='0 0 24 24'%3E%3Cpath d='M13 20h-2V8l-5.5 5.5-1.42-1.42L12 4.16l7.92 7.92-1.42 1.42L13 8v12z'/%3E%3C/svg%3E");
    }

    .bounce-card { border-left-color: var(--info); }
    .bounce-card::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%2300cfe8' viewBox='0 0 24 24'%3E%3Cpath d='M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8zm-1-4h2v2h-2zm0-10h2v8h-2z'/%3E%3C/svg%3E");
    }

    /* =====================================================
       BUTTONS
       ===================================================== */
    .btn-primary,
    .btn-modern {
        background: linear-gradient(to right, var(--primary), var(--secondary));
        color: white;
        border-radius: 10px;
        padding: 12px 24px;
        font-weight: 500;
        transition: all 0.2s;
        border: none;
        box-shadow: 0 4px 6px rgba(114, 103, 240, 0.3);
        position: relative;
        overflow: hidden;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-primary::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: 0.5s;
    }

    .btn-primary:hover,
    .btn-modern:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 15px rgba(114, 103, 240, 0.4);
    }

    .btn-primary:hover::before {
        left: 100%;
    }

    .btn-modern i {
        margin-right: 8px;
    }

    /* =====================================================
       HEADER
       ===================================================== */
    .header {
        background-color: var(--header-bg);
        border-bottom: 1px solid var(--border-color);
        padding: 16px 24px;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 40;
    }

    .header-search {
        background-color: var(--bg-secondary);
        border-radius: 10px;
        border: 1px solid transparent;
        padding: 12px 16px 12px 44px;
        color: var(--text-primary);
        transition: all 0.3s;
        width: 100%;
        font-size: 14px;
        min-width: 280px;
    }

    .header-search:focus {
        outline: none;
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.2);
    }

    .header-search::placeholder {
        color: var(--text-secondary);
    }

    [data-theme="dark"] .header-search {
        background-color: #2d3748 !important;
        border-color: #4a5568 !important;
        color: #e2e8f0 !important;
    }
    [data-theme="dark"] .header-search::placeholder { color: #a0aec0 !important; }

    [data-theme="light"] .header-search {
        background-color: #f7fafc !important;
        border-color: #e2e8f0 !important;
        color: #2d3748 !important;
    }
    [data-theme="light"] .header-search::placeholder { color: #718096 !important; }

    .header-buttons { gap: 8px; }

    /* =====================================================
       THEME SWITCH TOGGLE
       ===================================================== */
    .theme-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 26px;
    }

    .theme-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background: linear-gradient(to right, #ccc, #aaa);
        transition: .4s;
        border-radius: 34px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background: linear-gradient(to bottom, #fff, #f0f0f0);
        transition: .4s;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    input:checked + .slider {
        background: linear-gradient(to right, var(--primary), var(--secondary));
    }

    input:checked + .slider:before {
        transform: translateX(24px);
        background: linear-gradient(to bottom, #fff, #e6e6e6);
    }

    /* =====================================================
       NOTIFICATION SYSTEM - CONSOLIDATED SINGLE SOURCE OF TRUTH
       ===================================================== */

    /* --- Bell container --- */
    #notificationBell,
    .notification-bell-container,
    .notification-wrapper {
        position: relative;
        display: inline-flex;
        align-items: center;
        cursor: pointer;
        padding: 4px;
    }

    /* --- Count badge --- */
    .notification-badge,
    .notification-dot {
        position: absolute;
        top: -4px;
        right: -4px;
        background-color: var(--danger, #ef4444);
        color: #ffffff;
        border-radius: 50%;
        min-width: 20px;
        height: 20px;
        padding: 0 5px;
        font-size: 10px;
        font-weight: 700;
        line-height: 20px;
        text-align: center;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--card-bg, #ffffff);
        box-shadow: 0 2px 8px rgba(var(--danger-rgb, 234, 84, 85), 0.35);
        animation: notificationPulse 2s infinite;
        z-index: 10;
        transform-origin: center;
        transition: transform 0.2s ease, opacity 0.2s ease;
    }

    .notification-dot {
        min-width: 12px;
        width: 12px;
        height: 12px;
        padding: 0;
        top: -5px;
        right: -5px;
    }

    #notificationBell:hover .notification-badge,
    .notification-bell-container:hover .notification-badge {
        transform: scale(1.1);
    }

    [data-theme="dark"] .notification-badge,
    [data-theme="dark"] .notification-dot {
        border-color: #1a1e2c;
        box-shadow: 0 2px 8px rgba(var(--danger-rgb, 234, 84, 85), 0.45);
    }

    [data-theme="light"] .notification-badge,
    [data-theme="light"] .notification-dot {
        border-color: #ffffff;
        box-shadow: 0 2px 8px rgba(var(--danger-rgb, 234, 84, 85), 0.3);
    }

    @keyframes notificationPulse {
        0%   { transform: scale(1);    opacity: 1;    }
        50%  { transform: scale(1.1);  opacity: 0.85; }
        100% { transform: scale(1);    opacity: 1;    }
    }

    /* --- Bell icon + hover ring --- */
    .notification-bell-icon {
        font-size: 20px;
        color: var(--text-secondary);
        transition: color 0.2s ease;
    }

    #notificationBell:hover .notification-bell-icon,
    .notification-bell-container:hover .notification-bell-icon {
        color: var(--primary);
    }

    #notificationBell:hover .fa-bell,
    .notification-bell-container:hover .fa-bell {
        animation: bellRing 0.5s ease-in-out;
    }

    @keyframes bellRing {
        0%   { transform: rotate(0);    }
        25%  { transform: rotate(15deg);  }
        50%  { transform: rotate(-15deg); }
        75%  { transform: rotate(10deg);  }
        100% { transform: rotate(0);    }
    }

    /* --- Notification Alert / Toast (top-right) --- */
    .notification-alert,
    .notification-toast {
        position: fixed;
        top: 24px;
        right: 24px;
        z-index: 9999;
        min-width: 300px;
        max-width: 420px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 12px;
        background-color: var(--card-bg, #ffffff);
        color: var(--text-primary, #111827);
        border: 1px solid var(--border-color, #e5e7eb);
        border-left: 4px solid var(--primary, #3b82f6);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        animation: notificationSlideIn 0.35s cubic-bezier(0.22, 1, 0.36, 1);
        transform-origin: top right;
    }

    .notification-alert.notification-success,
    .notification-toast.notification-success { border-left-color: var(--success); }
    .notification-alert.notification-error,
    .notification-toast.notification-error   { border-left-color: var(--danger);  }
    .notification-alert.notification-warning,
    .notification-toast.notification-warning { border-left-color: var(--warning); }
    .notification-alert.notification-info,
    .notification-toast.notification-info    { border-left-color: var(--info);    }

    .notification-alert .notification-icon,
    .notification-toast .notification-icon {
        flex-shrink: 0;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        background-color: rgba(var(--primary-rgb), 0.12);
        color: var(--primary);
    }

    .notification-alert.notification-success .notification-icon,
    .notification-toast.notification-success .notification-icon {
        background-color: rgba(var(--success-rgb), 0.12);
        color: var(--success);
    }
    .notification-alert.notification-error .notification-icon,
    .notification-toast.notification-error .notification-icon {
        background-color: rgba(var(--danger-rgb), 0.12);
        color: var(--danger);
    }
    .notification-alert.notification-warning .notification-icon,
    .notification-toast.notification-warning .notification-icon {
        background-color: rgba(var(--warning-rgb), 0.12);
        color: var(--warning);
    }
    .notification-alert.notification-info .notification-icon,
    .notification-toast.notification-info .notification-icon {
        background-color: rgba(var(--info-rgb), 0.12);
        color: var(--info);
    }

    .notification-alert .notification-body,
    .notification-toast .notification-body {
        flex: 1;
        min-width: 0;
    }

    .notification-alert .notification-title,
    .notification-toast .notification-title {
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 2px 0;
        color: var(--text-primary);
    }

    .notification-alert .notification-message,
    .notification-toast .notification-message {
        font-size: 13px;
        line-height: 1.45;
        margin: 0;
        color: var(--text-secondary);
        word-wrap: break-word;
    }

    .notification-alert .notification-close,
    .notification-toast .notification-close {
        flex-shrink: 0;
        background: transparent;
        border: none;
        cursor: pointer;
        font-size: 14px;
        line-height: 1;
        padding: 4px;
        color: var(--text-secondary);
        border-radius: 6px;
        transition: background-color 0.15s ease, color 0.15s ease;
    }

    .notification-alert .notification-close:hover,
    .notification-toast .notification-close:hover {
        background-color: rgba(var(--primary-rgb), 0.1);
        color: var(--text-primary);
    }

    .notification-alert .notification-progress,
    .notification-toast .notification-progress {
        position: absolute;
        left: 0;
        bottom: 0;
        height: 3px;
        border-radius: 0 0 0 12px;
        background-color: var(--primary);
        animation: notificationProgress 5s linear forwards;
    }

    .notification-alert.notification-success .notification-progress,
    .notification-toast.notification-success .notification-progress { background-color: var(--success); }
    .notification-alert.notification-error .notification-progress,
    .notification-toast.notification-error .notification-progress   { background-color: var(--danger);  }
    .notification-alert.notification-warning .notification-progress,
    .notification-toast.notification-warning .notification-progress { background-color: var(--warning); }
    .notification-alert.notification-info .notification-progress,
    .notification-toast.notification-info .notification-progress    { background-color: var(--info);    }

    @keyframes notificationSlideIn {
        from { opacity: 0; transform: translateX(40px) scale(0.96); }
        to   { opacity: 1; transform: translateX(0)    scale(1);    }
    }
    @keyframes notificationSlideOut {
        from { opacity: 1; transform: translateX(0)    scale(1);    }
        to   { opacity: 0; transform: translateX(40px) scale(0.96); }
    }
    @keyframes notificationProgress {
        from { width: 100%; }
        to   { width: 0%;   }
    }

    .notification-alert.is-hiding,
    .notification-toast.is-hiding {
        animation: notificationSlideOut 0.3s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    }

    .notification-stack {
        position: fixed;
        top: 24px;
        right: 24px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 12px;
        pointer-events: none;
    }
    .notification-stack > * {
        pointer-events: auto;
        position: relative;
        top: auto;
        right: auto;
    }

    /* =====================================================
       NOTIFICATION ITEM (list item inside dropdown)
       ===================================================== */
    .notification-item {
        border-left: 3px solid transparent;
        transition: all 0.3s;
        border-radius: 8px;
        padding: 14px 16px;
        margin-bottom: 8px;
        background-color: var(--card-bg);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }

    .notification-item.unread {
        border-left-color: var(--primary);
        background-color: rgba(var(--primary-rgb), 0.05);
    }

    .notification-item:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    /* =====================================================
       USER DROPDOWN MENU
       ===================================================== */
    .dropdown { position: relative; }

    .dropdown-menu,
    #userDropdown,
    #dropdown-menu {
        display: none;
        position: absolute;
        right: 0;
        top: calc(100% + 8px);
        background-color: var(--card-bg);
        min-width: 220px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        border-radius: 12px;
        z-index: 1000;
        border: 1px solid var(--border-color);
        overflow: hidden;
        backdrop-filter: blur(10px);
        opacity: 0;
        transform: translateY(-6px);
        transition: opacity 0.15s ease, transform 0.15s ease;
    }

    .dropdown-menu.show,
    #userDropdown.show,
    #dropdown-menu.show {
        display: block;
        opacity: 1;
        transform: translateY(0);
        animation: fadeIn 0.2s ease;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        color: var(--text-primary);
        transition: all 0.3s;
        font-size: 14px;
        border-bottom: 1px solid rgba(var(--primary-rgb), 0.1);
        text-decoration: none;
        cursor: pointer;
        background: transparent;
        border-left: none;
        border-right: none;
        border-top: none;
        width: 100%;
        text-align: left;
    }

    .dropdown-item:last-child { border-bottom: none; }

    .dropdown-item:hover {
        background-color: rgba(var(--primary-rgb), 0.1);
        padding-left: 20px;
    }

    .dropdown-item i {
        width: 18px;
        text-align: center;
        color: var(--text-secondary);
    }

    .dropdown-item:hover i { color: var(--primary); }

    form .dropdown-item { color: var(--danger); }
    form .dropdown-item i { color: var(--danger); }
    form .dropdown-item:hover { background-color: rgba(var(--danger-rgb), 0.1); }

    /* =====================================================
       PROGRESS BAR
       ===================================================== */
    .progress-bar {
        height: 10px;
        border-radius: 5px;
        background-color: rgba(var(--primary-rgb), 0.1);
        overflow: hidden;
    }

    .progress-bar-fill {
        height: 100%;
        border-radius: 5px;
        transition: width 0.3s ease;
        background: linear-gradient(to right, var(--primary), var(--secondary));
        box-shadow: 0 0 10px rgba(114, 103, 240, 0.4);
    }

    /* =====================================================
       CHART CONTAINER
       ===================================================== */
    .chart-container {
        position: relative;
        height: 350px;
        border-radius: 12px;
        overflow: hidden;
        background: var(--card-bg);
        padding: 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* =====================================================
       THEME SELECTOR (color swatches)
       ===================================================== */
    .theme-selector {
        display: flex;
        gap: 10px;
        margin-top: 12px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .theme-option {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        cursor: pointer;
        border: 2px solid transparent;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .theme-option:hover { transform: scale(1.1); }

    .theme-option.active {
        border-color: white;
        transform: scale(1.15);
        box-shadow: 0 0 0 2px var(--primary);
    }

    .theme-option.default { background: linear-gradient(to bottom, #7267f0, #6258e0); }
    .theme-option.dark    { background: linear-gradient(to bottom, #232933, #1e2229); }
    .theme-option.light   { background: linear-gradient(to bottom, #ffffff, #f5f7f9); }
    .theme-option.blue    { background: linear-gradient(to bottom, #1a56db, #1e429f); }
    .theme-option.green   { background: linear-gradient(to bottom, #057a55, #0a5c36); }

    /* =====================================================
       GRADIENT BACKGROUNDS
       ===================================================== */
    .gradient-bg {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    }

    /* =====================================================
       AVATAR
       ===================================================== */
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
        box-shadow: 0 4px 8px rgba(114, 103, 240, 0.3);
        cursor: pointer;
        transition: all 0.3s;
    }

    .avatar:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 12px rgba(114, 103, 240, 0.4);
    }

    .avatar-minimal {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    .avatar-minimal:hover { transform: scale(1.05); }
    .avatar-minimal img {
        width: 100%; height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }

    /* =====================================================
       THEME SETTINGS GEAR
       ===================================================== */
    .theme-settings-gear {
        animation: spin-slow 3s linear infinite;
        transition: all 0.3s ease;
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
    }

    .theme-settings-gear:hover {
        animation-duration: 1s;
        transform: scale(1.1);
        filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.4));
    }

    @keyframes spin-slow {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }

    .theme-settings-gear.gear-spin-fast,
    .gear-spin-fast {
        animation: spin-fast 1s linear infinite;
        color: var(--primary) !important;
    }

    @keyframes spin-fast {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }

    /* =====================================================
       PROFESSIONAL LOGO STYLES
       ===================================================== */
    .logo-section {
        padding: 1.5rem 1.25rem 1rem 1.25rem;
        position: relative;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        flex-shrink: 0;                    /* keep logo pinned above scroll area */
    }

    .logo-container {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .logo-wrapper {
        position: relative;
        flex-shrink: 0;
    }

    .logo-image-container {
        position: relative;
        width: 3rem;
        height: 3rem;
    }

    .logo-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 12px;
        border: 2px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transition: all 0.3s ease;
    }

    .logo-image:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        border-color: rgba(255, 255, 255, 0.3);
    }

    .logo-fallback,
    .logo-default {
        width: 3rem;
        height: 3rem;
        border-radius: 12px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.25rem;
        border: 2px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transition: all 0.3s ease;
    }

    .logo-fallback:hover,
    .logo-default:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        border-color: rgba(255, 255, 255, 0.3);
    }

    .logo-content {
        flex: 1;
        min-width: 0;
    }

    .logo-text-container {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
    }

    .logo-shortname {
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.2;
        letter-spacing: -0.025em;
        color: white !important;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: all 0.3s ease;
    }

    .logo-fullname {
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1.2;
        color: rgba(255, 255, 255, 0.9) !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: all 0.3s ease;
    }

    .logo-separator {
        height: 1px;
        background: linear-gradient(90deg,
            transparent 0%,
            rgba(255, 255, 255, 0.3) 50%,
            transparent 100%);
        margin: 0.75rem 1rem 0 1rem;
        flex-shrink: 0;
    }

    /* Collapsed sidebar logo adjustments */
    .sidebar.collapsed .logo-section {
        padding: 1.5rem 0.75rem 1rem 0.75rem;
        justify-content: center;
    }

    .sidebar.collapsed .logo-container {
        justify-content: center;
        flex-direction: column;
        gap: 0.5rem;
    }

    .sidebar.collapsed .logo-content { display: none; }
    .sidebar.collapsed .logo-separator { margin: 0.75rem 0.5rem 0 0.5rem; }

    /* Logo visibility for light sidebar */
    [data-sidebar-theme="light"] .logo-shortname {
        color: #2d3748 !important;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }
    [data-sidebar-theme="light"] .logo-fullname {
        color: rgba(45, 55, 72, 0.8) !important;
    }
    [data-sidebar-theme="light"] .logo-separator {
        background: linear-gradient(90deg,
            transparent 0%,
            rgba(0, 0, 0, 0.2) 50%,
            transparent 100%);
    }
    [data-sidebar-theme="light"] .logo-image,
    [data-sidebar-theme="light"] .logo-fallback,
    [data-sidebar-theme="light"] .logo-default {
        border-color: rgba(0, 0, 0, 0.1);
    }

    /* =====================================================
       THEME SETTINGS MODAL
       ===================================================== */
    #themeSettingsModal {
        transition: opacity 0.3s ease;
        padding: 1rem;
        z-index: 9999;
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }

    #themeSettingsModal > div {
        animation: modalFadeIn 0.3s ease-out;
        max-height: 90vh;
        overflow-y: auto;
    }

    .theme-modal-compact {
        width: 95vw;
        max-width: 500px;
        margin: 1rem auto;
        background-color: var(--card-bg) !important;
        color: var(--text-primary) !important;
        border: 1px solid var(--border-color);
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        position: relative;
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }

    .theme-modal-header-compact {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
        background-color: var(--header-bg);
        border-radius: 16px 16px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .theme-modal-header-compact h3 {
        color: var(--text-primary) !important;
        font-weight: 600;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        margin: 0;
    }

    .theme-modal-body-compact {
        padding: 1.5rem;
        background-color: var(--card-bg);
    }

    .theme-modal-footer-compact {
        padding: 1.25rem 1.5rem;
        border-top: 1px solid var(--border-color);
        background-color: var(--bg-secondary);
        border-radius: 0 0 16px 16px;
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
    }

    .appearance-grid-compact,
    .theme-grid-compact {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
    }

    .appearance-grid-compact { margin-bottom: 1.5rem; }

    .appearance-option-compact,
    .theme-option-compact {
        display: flex;
        flex-direction: column;
        align-items: center;
        cursor: pointer;
        padding: 0.75rem 0.5rem;
        border-radius: 12px;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        background-color: var(--bg-secondary);
        position: relative;
    }

    .appearance-option-compact:hover,
    .theme-option-compact:hover {
        background-color: rgba(var(--primary-rgb), 0.1);
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }

    .appearance-option-compact.active,
    .theme-option-compact.active {
        border-color: var(--primary);
        background-color: rgba(var(--primary-rgb), 0.15);
        box-shadow: 0 8px 25px rgba(var(--primary-rgb), 0.2);
    }

    .appearance-icon-compact {
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 10px;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }

    .appearance-option-compact.active .appearance-icon-compact {
        transform: scale(1.05);
        box-shadow: 0 6px 20px rgba(var(--primary-rgb), 0.3);
    }

    .appearance-label-compact,
    .theme-label-compact {
        font-size: 0.75rem;
        font-weight: 600;
        text-align: center;
        line-height: 1.2;
        color: var(--text-primary) !important;
    }

    .theme-preview-compact {
        width: 100%;
        height: 3.5rem;
        border-radius: 8px;
        margin-bottom: 0.5rem;
        border: 2px solid transparent;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        position: relative;
        overflow: hidden;
    }

    .theme-option-compact.active .theme-preview-compact {
        transform: scale(1.05);
        box-shadow: 0 6px 20px rgba(var(--primary-rgb), 0.3);
    }

    .current-selection-compact {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 18px;
        height: 18px;
        background: var(--primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 10px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        z-index: 5;
        animation: bounceIn 0.5s ease;
    }

    @keyframes bounceIn {
        0%   { transform: scale(0);   }
        50%  { transform: scale(1.2); }
        100% { transform: scale(1);   }
    }

    .section-header-compact {
        font-size: 0.9rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
        color: var(--text-primary) !important;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .section-description-compact {
        font-size: 0.75rem;
        color: var(--text-secondary) !important;
        margin-bottom: 1.25rem;
        line-height: 1.4;
    }

    #closeThemeModal,
    #closeThemeModalBtn {
        color: var(--text-secondary) !important;
        transition: all 0.3s ease;
        border-radius: 8px;
        background: none;
        border: none;
        cursor: pointer;
        font-size: 1.25rem;
        padding: 0.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #closeThemeModal:hover,
    #closeThemeModalBtn:hover {
        color: var(--text-primary) !important;
        background-color: rgba(var(--primary-rgb), 0.1);
        transform: scale(1.05);
    }

    .theme-modal-footer-compact button {
        color: var(--text-primary) !important;
        transition: all 0.3s ease;
        border-radius: 8px;
        padding: 0.5rem 1rem;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        cursor: pointer;
        font-weight: 500;
    }

    .theme-modal-footer-compact button:hover {
        color: var(--primary) !important;
        background-color: rgba(var(--primary-rgb), 0.1);
    }

    #themeSettingsModal * { color: inherit !important; }

    /* =====================================================
       QUICK SEARCH DROPDOWN
       ===================================================== */
    .quick-search-results {
        animation: fadeIn 0.2s ease-out;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .quick-result-item {
        transition: all 0.15s ease;
        cursor: pointer;
    }

    .quick-result-item:hover {
        padding-left: 16px;
        background-color: rgba(var(--primary-rgb), 0.05) !important;
    }

    .quick-result-item mark {
        background-color: rgba(var(--primary-rgb), 0.3);
        color: inherit;
        padding: 0 2px;
        border-radius: 2px;
        font-weight: 500;
    }

    [data-theme="dark"] .quick-result-item mark {
        background-color: rgba(96, 165, 250, 0.3);
        color: #60a5fa;
    }

    [data-theme="light"] .quick-result-item mark {
        background-color: rgba(37, 99, 235, 0.2);
        color: #2563eb;
    }

    #quickSearchInput:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
        min-width: 350px;
        transition: min-width 0.3s ease;
    }

    /* =====================================================
       FORM ELEMENTS - THEME AWARE
       ===================================================== */
    [data-theme="light"] input,
    [data-theme="light"] select,
    [data-theme="light"] textarea,
    .input-light-theme {
        background-color: #f7fafc !important;
        border-color: #e2e8f0 !important;
        color: #2d3748 !important;
    }

    [data-theme="dark"] input,
    [data-theme="dark"] select,
    [data-theme="dark"] textarea,
    .input-dark-theme {
        background-color: #2d3748 !important;
        border-color: #4a5568 !important;
        color: #e2e8f0 !important;
    }

    input, select, textarea {
        transition: all 0.2s ease;
        border-radius: 8px;
        padding: 8px 12px;
        border-width: 1px;
        border-style: solid;
    }

    input:focus, select:focus, textarea:focus {
        outline: none;
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
    }

    [data-theme="light"] label,
    .label-light-theme { color: #4a5568 !important; }

    [data-theme="dark"] label,
    .label-dark-theme { color: #a0aec0 !important; }

    [data-theme="light"] ::placeholder { color: #a0aec0 !important; opacity: 1; }
    [data-theme="dark"]  ::placeholder { color: #718096 !important; opacity: 1; }

    [type="radio"], [type="checkbox"] {
        transition: all 0.2s ease;
        accent-color: var(--primary);
    }

    [data-theme="dark"] [type="radio"],
    [data-theme="dark"] [type="checkbox"] {
        filter: brightness(0.8);
    }

    /* =====================================================
       MODAL BACKDROP BLUR
       ===================================================== */
    #searchModal,
    #billingManagementModal,
    #administrativeToolsModal,
    #themeSettingsModal,
    #emailManagementModal,
    #smsManagementModal,
    #whatsappManagementModal,
    #invoiceManagementModal {
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }

    /* =====================================================
       MODAL FADE-IN
       ===================================================== */
    @keyframes modalFadeIn {
        from { opacity: 0; transform: scale(0.9) translateY(-20px); }
        to   { opacity: 1; transform: scale(1)   translateY(0);    }
    }

    /* =====================================================
       PAGE CONTENT FADE
       ===================================================== */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0);    }
    }

    .animate-fadeInUp { animation: fadeInUp 0.5s ease-out; }

    /* =====================================================
       SCROLLBARS
       ===================================================== */
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: var(--bg-primary); }
    ::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 4px;
    }
    ::-webkit-scrollbar-thumb:hover { background: var(--secondary); }

    .modal-scrollable-body {
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
        overflow-y: auto;
    }

    /* =====================================================
       MOBILE OVERLAY
       ===================================================== */
    .overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 45;
        backdrop-filter: blur(5px);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .overlay.active,
    .overlay.show {
        display: block;
        opacity: 1;
    }

    .mobile-menu-btn { display: none; }

    /* =====================================================
       RESPONSIVE — 1280px (tablet / mobile sidebar)
       ===================================================== */
    @media (max-width: 1280px) {
        .content,
        .content.collapsed {
            margin-left: 0;
            width: 100%;
        }

        .sidebar {
            transform: translateX(-100%);
            width: 280px;
            height: 100vh;
            transition: transform 0.3s ease;
        }

        /* .active and .mobile-open and .show-mobile all open the sidebar */
        .sidebar.active,
        .sidebar.mobile-open,
        .sidebar.show-mobile {
            transform: translateX(0);
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
            width: 280px;
        }

        .sidebar.collapsed.active,
        .sidebar.collapsed.mobile-open,
        .sidebar.collapsed.show-mobile {
            transform: translateX(0);
            width: 280px;
        }

        /* When mobile sidebar is open, reveal everything that was hidden */
        .sidebar.collapsed.active .logo-text,
        .sidebar.collapsed.active .logo-content,
        .sidebar.collapsed.active .nav-text,
        .sidebar.collapsed.active .menu-text,
        .sidebar.collapsed.active .version,
        .sidebar.collapsed.active .theme-text,
        .sidebar.collapsed.mobile-open .logo-text,
        .sidebar.collapsed.mobile-open .logo-content,
        .sidebar.collapsed.mobile-open .nav-text,
        .sidebar.collapsed.mobile-open .menu-text,
        .sidebar.collapsed.show-mobile .logo-text,
        .sidebar.collapsed.show-mobile .logo-content,
        .sidebar.collapsed.show-mobile .nav-text,
        .sidebar.collapsed.show-mobile .menu-text {
            display: block !important;
        }

        .sidebar.collapsed.active .nav-item,
        .sidebar.collapsed.mobile-open .nav-item,
        .sidebar.collapsed.show-mobile .nav-item {
            justify-content: flex-start !important;
            padding-left: 1.5rem !important;
            padding-right: 1.5rem !important;
        }

        .sidebar.collapsed.active .nav-item i,
        .sidebar.collapsed.mobile-open .nav-item i,
        .sidebar.collapsed.show-mobile .nav-item i {
            margin-right: 1rem !important;
        }

        .sidebar.collapsed.active .nav-badge,
        .sidebar.collapsed.mobile-open .nav-badge,
        .sidebar.collapsed.show-mobile .nav-badge {
            display: flex !important;
        }

        .sidebar.collapsed.active .nav-divider,
        .sidebar.collapsed.mobile-open .nav-divider,
        .sidebar.collapsed.show-mobile .nav-divider {
            display: flex !important;
            text-align: left;
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }

        .sidebar.collapsed.active .ml-auto,
        .sidebar.collapsed.mobile-open .ml-auto,
        .sidebar.collapsed.show-mobile .ml-auto {
            display: inline-flex !important;
        }

        .sidebar.collapsed.active .bg-yellow-500,
        .sidebar.collapsed.active .bg-red-500,
        .sidebar.collapsed.active .bg-blue-500,
        .sidebar.collapsed.active .bg-orange-500,
        .sidebar.collapsed.mobile-open .bg-yellow-500,
        .sidebar.collapsed.mobile-open .bg-red-500,
        .sidebar.collapsed.mobile-open .bg-blue-500,
        .sidebar.collapsed.mobile-open .bg-orange-500,
        .sidebar.collapsed.show-mobile .bg-yellow-500,
        .sidebar.collapsed.show-mobile .bg-red-500,
        .sidebar.collapsed.show-mobile .bg-blue-500,
        .sidebar.collapsed.show-mobile .bg-orange-500 {
            display: inline-flex !important;
        }

        /* Re-enable scrolling when mobile sidebar opens, even if
           collapsed class is still present */
        .sidebar.collapsed.active > nav,
        .sidebar.collapsed.mobile-open > nav,
        .sidebar.collapsed.show-mobile > nav,
        .sidebar.active > nav,
        .sidebar.mobile-open > nav,
        .sidebar.show-mobile > nav {
            overflow-y: auto !important;
        }

        /* ✅ FIXED: Hide the mid-sidebar toggle button on mobile —
           the header hamburger handles open/close here. */
        .toggle-sidebar,
        .sidebar.collapsed .toggle-sidebar {
            display: none !important;
        }

        .mobile-menu-btn { display: block; }
    }

    /* =====================================================
       RESPONSIVE — 1024px
       ===================================================== */
    @media (max-width: 1024px) {
        .header-search { width: 200px; min-width: 0; }
        .grid-cols-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    /* =====================================================
       RESPONSIVE — 768px
       ===================================================== */
    @media (max-width: 768px) {
        .header-search { display: none; }

        .grid-cols-4,
        .grid-cols-2,
        .lg\:grid-cols-3,
        .lg\:grid-cols-2 {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }

        .chart-container { height: 250px; }

        .theme-modal-compact {
            width: 98vw;
            max-width: 450px;
            margin: 0.5rem;
        }

        .appearance-grid-compact,
        .theme-grid-compact {
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
        }

        .appearance-icon-compact {
            width: 3rem;
            height: 3rem;
            font-size: 1.25rem;
        }

        .theme-preview-compact { height: 3rem; }

        .notification-alert,
        .notification-toast {
            top: 12px;
            right: 12px;
            left: 12px;
            min-width: 0;
            max-width: none;
        }

        .notification-stack {
            top: 12px;
            right: 12px;
            left: 12px;
        }
    }

    /* =====================================================
       RESPONSIVE — 480px
       ===================================================== */
    @media (max-width: 480px) {
        .header-buttons { display: none; }

        .stat-card h2 { font-size: 1.5rem; }
        .card { padding: 1rem; }
        .p-6 { padding: 1rem; }
        main.p-6 { padding: 0.5rem; }

        .chart-container { height: 200px; }

        .theme-modal-compact {
            width: 100vw;
            max-width: none;
            margin: 0;
            border-radius: 0;
        }

        .appearance-grid-compact,
        .theme-grid-compact {
            grid-template-columns: repeat(3, 1fr);
            gap: 0.25rem;
        }

        .appearance-option-compact,
        .theme-option-compact { padding: 0.5rem 0.25rem; }

        .appearance-icon-compact {
            width: 2.5rem;
            height: 2.5rem;
            font-size: 1.1rem;
        }

        .theme-preview-compact { height: 2.5rem; }

        .appearance-label-compact,
        .theme-label-compact { font-size: 0.65rem; }
    }

    /* =====================================================
       EXTRA LARGE SCREENS (≥1920px)
       ===================================================== */
    @media (min-width: 1920px) {
        .container {
            max-width: 1800px;
            margin: 0 auto;
        }

        .sidebar { width: 320px; }
        .sidebar.collapsed { width: 100px; }

        .content {
            margin-left: 320px;
            width: calc(100% - 320px);
        }
        .content.collapsed {
            margin-left: 100px;
            width: calc(100% - 100px);
        }
    }

    /* =====================================================
       GLOBAL SMOOTH TRANSITIONS
       ===================================================== */
    * {
        transition: color 0.3s, background-color 0.3s, border-color 0.3s;
    }
</style>