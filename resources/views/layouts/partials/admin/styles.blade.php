<!-- Using only Tailwind CSS -->
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* ========================================================================= */
    /* CSS VARIABLES AND BASE STYLES                                             */
    /* ========================================================================= */
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

    /* ========================================================================= */
    /* SIDEBAR THEME TOKENS — canonical source on <html>                         */
    /* ========================================================================= */
    /*
     * These tokens live on <html> so the cascade is deterministic:
     *   • <html data-theme="dark|light">               → appearance
     *   • <html data-sidebar-theme="default|dark|..."> → sidebar palette
     *
     * For the "custom" theme, JS writes --sidebar-bg and text tokens inline
     * on <html>. That is why the [data-sidebar-theme="custom"] block below
     * does NOT declare --sidebar-bg — a self-referential declaration would
     * cause the browser to ignore the JS-inline value.
     */

    html {
        /* Fallback tokens — used before JS runs, and as a safety net. */
        --sidebar-bg: linear-gradient(180deg, #7267f0 0%, #6258e0 100%);
        --sidebar-text: #ffffff;
        --sidebar-text-muted: rgba(255, 255, 255, 0.72);
        --sidebar-icon: #ffffff;
        --sidebar-hover: rgba(255, 255, 255, 0.12);
        --sidebar-active: rgba(255, 255, 255, 0.20);
        --sidebar-border: transparent;
        --sidebar-shadow: 0 0 20px rgba(114, 103, 240, 0.30);
        --sidebar-divider: rgba(255, 255, 255, 0.10);
        --sidebar-logo: #ffffff;
        --sidebar-logo-muted: rgba(255, 255, 255, 0.72);

        /* Smooth token transitions when the theme changes. */
        transition: background-color 0.3s ease, color 0.3s ease;
    }

    [data-sidebar-theme="default"] {
        --sidebar-bg: linear-gradient(180deg, #7267f0 0%, #6258e0 100%);
        --sidebar-text: #ffffff;
        --sidebar-text-muted: rgba(255, 255, 255, 0.72);
        --sidebar-icon: #ffffff;
        --sidebar-hover: rgba(255, 255, 255, 0.12);
        --sidebar-active: rgba(255, 255, 255, 0.20);
        --sidebar-border: transparent;
        --sidebar-shadow: 0 0 20px rgba(114, 103, 240, 0.30);
        --sidebar-divider: rgba(255, 255, 255, 0.10);
        --sidebar-logo: #ffffff;
        --sidebar-logo-muted: rgba(255, 255, 255, 0.72);
    }

    [data-sidebar-theme="dark"] {
        --sidebar-bg: linear-gradient(180deg, #232933 0%, #1e2229 100%);
        --sidebar-text: #ecf0f1;
        --sidebar-text-muted: rgba(236, 240, 241, 0.70);
        --sidebar-icon: #ecf0f1;
        --sidebar-hover: rgba(236, 240, 241, 0.08);
        --sidebar-active: rgba(52, 152, 219, 0.20);
        --sidebar-border: #3498db;
        --sidebar-shadow: 0 0 20px rgba(0, 0, 0, 0.20);
        --sidebar-divider: rgba(236, 240, 241, 0.08);
        --sidebar-logo: #ecf0f1;
        --sidebar-logo-muted: rgba(236, 240, 241, 0.70);
    }

    [data-sidebar-theme="light"] {
        --sidebar-bg: linear-gradient(180deg, #ffffff 0%, #f5f7f9 100%);
        --sidebar-text: #1e293b;
        --sidebar-text-muted: rgba(30, 41, 59, 0.68);
        --sidebar-icon: #4a5568;
        --sidebar-hover: rgba(114, 103, 240, 0.10);
        --sidebar-active: rgba(114, 103, 240, 0.15);
        --sidebar-border: #7267f0;
        --sidebar-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        --sidebar-divider: rgba(0, 0, 0, 0.08);
        --sidebar-logo: #1e293b;
        --sidebar-logo-muted: rgba(30, 41, 59, 0.68);
    }

    [data-sidebar-theme="blue"] {
        --sidebar-bg: linear-gradient(180deg, #1a56db 0%, #1e429f 100%);
        --sidebar-text: #ffffff;
        --sidebar-text-muted: rgba(255, 255, 255, 0.72);
        --sidebar-icon: #ffffff;
        --sidebar-hover: rgba(255, 255, 255, 0.12);
        --sidebar-active: rgba(100, 255, 218, 0.20);
        --sidebar-border: #64FFDA;
        --sidebar-shadow: 0 0 20px rgba(26, 86, 219, 0.30);
        --sidebar-divider: rgba(255, 255, 255, 0.10);
        --sidebar-logo: #ffffff;
        --sidebar-logo-muted: rgba(255, 255, 255, 0.72);
    }

    [data-sidebar-theme="green"] {
        --sidebar-bg: linear-gradient(180deg, #057a55 0%, #0a5c36 100%);
        --sidebar-text: #ffffff;
        --sidebar-text-muted: rgba(255, 255, 255, 0.72);
        --sidebar-icon: #ffffff;
        --sidebar-hover: rgba(255, 255, 255, 0.12);
        --sidebar-active: rgba(105, 240, 174, 0.20);
        --sidebar-border: #69F0AE;
        --sidebar-shadow: 0 0 20px rgba(5, 122, 85, 0.30);
        --sidebar-divider: rgba(255, 255, 255, 0.10);
        --sidebar-logo: #ffffff;
        --sidebar-logo-muted: rgba(255, 255, 255, 0.72);
    }

    /*
     * Custom sidebar theme.
     * JS writes --sidebar-bg, --sidebar-text, --sidebar-*, --sidebar-logo*
     * inline on <html>. We only provide structural fallbacks here so the
     * sidebar remains usable if JS fails.
     */
    [data-sidebar-theme="custom"] {
        --sidebar-hover:  rgba(255, 255, 255, 0.12);
        --sidebar-active: rgba(255, 255, 255, 0.20);
        --sidebar-border: transparent;
        --sidebar-shadow: 0 0 20px rgba(114, 103, 240, 0.30);
        --sidebar-divider: rgba(255, 255, 255, 0.10);
        --sidebar-logo: #ffffff;
        --sidebar-logo-muted: rgba(255, 255, 255, 0.72);
    }

    body {
        background-color: var(--bg-primary);
        color: var(--text-primary);
        transition: background-color 0.3s ease, color 0.3s ease;
        overflow-x: hidden;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.6;
    }

    /* ========================================================================= */
    /* ENHANCED SIDEBAR STYLING                                                  */
    /* ========================================================================= */

    .sidebar {
        width: 280px;
        height: 100vh;
        max-height: 100vh;
        background-image: var(--sidebar-bg, linear-gradient(180deg, #7267f0 0%, #6258e0 100%));
        background-color: transparent;
        transition: width 0.3s ease, box-shadow 0.3s ease, background-image 0.3s ease;
        border-right: 1px solid var(--border-color);
        position: fixed;
        z-index: 50;
        left: 0;
        top: 0;
        box-shadow: var(--sidebar-shadow, 0 0 20px rgba(114, 103, 240, 0.30));
        display: flex;
        flex-direction: column;
        overflow: visible;
    }

    .sidebar.collapsed {
        width: 80px;
    }

    .sidebar .logo-section {
        flex-shrink: 0;
        padding: 1.5rem 1.25rem 1rem 1.25rem;
        position: relative;
        border-bottom: 1px solid var(--sidebar-divider, rgba(255, 255, 255, 0.1));
        z-index: 2;
        overflow: visible;
        transition: border-color 0.3s ease;
    }

    /* ========================================================================= */
    /* SIDEBAR TOGGLE BUTTON — always visible, solid, theme-independent          */
    /* ========================================================================= */
    /*
     * Strategy: solid WHITE background, double ring, dark gray icon.
     * Hover flips to --primary. Never inherits sidebar tokens so it stays
     * legible on every palette (including custom).
     */
    .toggle-sidebar {
        position: absolute;
        right: -18px;
        top: 24px;
        width: 38px;
        height: 38px;
        border-radius: 50%;

        background: #ffffff !important;
        background-color: #ffffff !important;
        color: #4b5563 !important;

        border: 2px solid #e5e7eb !important;
        box-shadow:
            0 0 0 3px rgba(255, 255, 255, 0.9),
            0 4px 12px rgba(0, 0, 0, 0.25);

        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 1000;
        pointer-events: auto;
        transition: transform 0.2s ease, box-shadow 0.2s ease,
                    background-color 0.2s ease, color 0.2s ease;
    }

    .toggle-sidebar i {
        font-size: 15px;
        color: inherit !important;
        transition: transform 0.3s ease;
    }

    .toggle-sidebar:hover {
        transform: scale(1.12);
        background: var(--primary) !important;
        background-color: var(--primary) !important;
        color: #ffffff !important;
        border-color: var(--primary) !important;
        box-shadow:
            0 0 0 3px rgba(255, 255, 255, 0.9),
            0 6px 18px rgba(var(--primary-rgb), 0.55);
    }

    .toggle-sidebar:hover i {
        transform: rotate(180deg);
        color: #ffffff !important;
    }

    .toggle-sidebar:active {
        transform: scale(0.94);
    }

    .sidebar.collapsed .toggle-sidebar {
        right: -18px;
        background: #ffffff !important;
        background-color: #ffffff !important;
        color: #4b5563 !important;
        border-color: #e5e7eb !important;
    }

    .sidebar.collapsed .toggle-sidebar i {
        transform: rotate(180deg);
    }

    .sidebar.collapsed .toggle-sidebar:hover {
        background: var(--primary) !important;
        background-color: var(--primary) !important;
        color: #ffffff !important;
        border-color: var(--primary) !important;
    }

    @keyframes gentlePulse {
        0%   { box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.9), 0 4px 12px rgba(114, 103, 240, 0.35); }
        50%  { box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.9), 0 4px 20px rgba(114, 103, 240, 0.75); }
        100% { box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.9), 0 4px 12px rgba(114, 103, 240, 0.35); }
    }

    .toggle-sidebar {
        animation: gentlePulse 2s infinite ease-in-out;
    }
    .toggle-sidebar.interacted {
        animation: none;
    }

    /* ========================================================================= */
    /* SIDEBAR NAVIGATION - SCROLLABLE                                           */
    /* ========================================================================= */

    .sidebar nav {
        flex: 1 1 auto;
        overflow-y: auto;
        overflow-x: hidden;
        scrollbar-width: thin;
        scrollbar-color: var(--sidebar-text) rgba(255, 255, 255, 0.1);
        padding-bottom: 1rem;
        scroll-behavior: smooth;
        -webkit-mask-image: linear-gradient(to bottom, transparent, black 10px, black 90%, transparent);
        mask-image: linear-gradient(to bottom, transparent, black 10px, black 90%, transparent);
    }

    .sidebar nav::-webkit-scrollbar { width: 4px; }
    .sidebar nav::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); border-radius: 4px; }
    .sidebar nav::-webkit-scrollbar-thumb {
        background: var(--sidebar-text);
        border-radius: 4px;
        opacity: 0.5;
        transition: all 0.3s ease;
    }
    .sidebar nav::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.8);
        width: 6px;
    }

    .sidebar.collapsed nav { scrollbar-width: none; }
    .sidebar.collapsed nav::-webkit-scrollbar { display: none; }

    .sidebar .absolute.bottom-0,
    .sidebar .settings-container {
        position: sticky;
        bottom: 0;
        flex-shrink: 0;
        background: inherit;
        backdrop-filter: blur(10px);
        border-top: 1px solid var(--sidebar-divider, rgba(255, 255, 255, 0.1));
        margin-top: auto;
        padding: 1rem;
        z-index: 2;
    }

    .sidebar::after {
        content: '';
        position: absolute;
        bottom: 70px;
        left: 0;
        right: 0;
        height: 30px;
        pointer-events: none;
        background: linear-gradient(to bottom, transparent, rgba(114, 103, 240, 0.8));
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 5;
    }

    .sidebar.scrollable-bottom::after { opacity: 1; }

    .sidebar .scroll-indicator {
        position: absolute;
        right: 8px;
        width: 4px;
        height: 40px;
        background: var(--sidebar-text);
        border-radius: 4px;
        opacity: 0;
        transition: opacity 0.3s ease;
        pointer-events: none;
        z-index: 10;
    }

    .sidebar:hover .scroll-indicator { opacity: 0.5; }

    .sidebar .scroll-hint {
        position: absolute;
        bottom: 80px;
        left: 50%;
        transform: translateX(-50%);
        color: var(--sidebar-text);
        font-size: 12px;
        white-space: nowrap;
        opacity: 0;
        transition: opacity 0.3s ease;
        pointer-events: none;
        z-index: 20;
        background: rgba(0, 0, 0, 0.3);
        padding: 4px 12px;
        border-radius: 20px;
        backdrop-filter: blur(5px);
    }

    .sidebar.has-scrollable-content .scroll-hint {
        animation: dragHint 2s ease-in-out 1;
    }
    .sidebar.has-scrollable-content:hover .scroll-hint { opacity: 1; }

    .sidebar .scroll-buttons {
        position: fixed;
        right: 20px;
        bottom: 100px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 100;
        pointer-events: none;
    }
    .sidebar:hover .scroll-buttons { opacity: 0.8; pointer-events: auto; }

    .sidebar .scroll-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        transition: all 0.3s ease;
        border: none;
    }
    .sidebar .scroll-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
        background: var(--secondary);
    }
    .sidebar.collapsed .scroll-buttons { right: 10px; }

    /* Collapsed sidebar adjustments */
    .sidebar.collapsed .logo-text,
    .sidebar.collapsed .nav-text,
    .sidebar.collapsed .menu-text,
    .sidebar.collapsed .version,
    .sidebar.collapsed .theme-text { display: none; }

    .sidebar.collapsed .nav-item {
        justify-content: center;
        padding: 12px 0;
        margin: 4px 8px;
    }
    .sidebar.collapsed .nav-item i { margin-right: 0; }
    .sidebar.collapsed .nav-badge { display: none; }
    .sidebar.collapsed .nav-divider { text-align: center; padding: 8px 0; }
    .sidebar.collapsed .nav-divider .menu-text { display: none; }
    .sidebar.collapsed .nav-divider::before {
        content: '•••';
        font-size: 12px;
        letter-spacing: 2px;
        opacity: 0.5;
    }

    /* ========================================================================= */
    /* CONTENT AREA                                                              */
    /* ========================================================================= */

    .content {
        transition: margin-left 0.3s ease, width 0.3s ease;
        margin-left: 280px;
        background-color: var(--bg-primary);
        min-height: 100vh;
        width: calc(100% - 280px);
        position: relative;
        z-index: 1;
    }
    .content.collapsed {
        margin-left: 80px;
        width: calc(100% - 80px);
    }

    /* ========================================================================= */
    /* NAVIGATION ITEMS                                                          */
    /* ========================================================================= */

    .nav-item {
        transition: background-color 0.3s, color 0.3s, border-color 0.3s, transform 0.3s;
        border-left: 3px solid transparent;
        color: var(--sidebar-text);
        margin: 0 12px;
        border-radius: 8px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .nav-item:hover, .nav-item.active {
        background-color: var(--sidebar-hover);
        border-left-color: var(--sidebar-active);
        transform: translateX(4px);
    }

    .nav-item::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.3s ease, height 0.3s ease;
    }

    .nav-item:active::after {
        width: 200px;
        height: 200px;
        opacity: 0;
    }

    .nav-divider {
        color: var(--sidebar-text-muted, var(--sidebar-text));
        opacity: 0.7;
        padding-left: 24px;
        margin-top: 16px;
        margin-bottom: 8px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    /* ========================================================================= */
    /* CARDS                                                                     */
    /* ========================================================================= */

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

    .users-card      { border-left-color: var(--primary); }
    .revenue-card    { border-left-color: var(--success); }
    .conversion-card { border-left-color: var(--warning); }
    .bounce-card     { border-left-color: var(--info); }

    /* ========================================================================= */
    /* BUTTONS                                                                   */
    /* ========================================================================= */

    .btn-primary {
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
    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 15px rgba(114, 103, 240, 0.4);
    }
    .btn-primary:hover::before { left: 100%; }

    /* ========================================================================= */
    /* HEADER / SEARCH / DROPDOWNS                                               */
    /* ========================================================================= */

    .header-search {
        background-color: var(--bg-secondary);
        border-radius: 10px;
        border: 1px solid transparent;
        padding: 12px 16px 12px 44px;
        color: var(--text-primary);
        transition: all 0.3s;
        width: 100%;
        font-size: 14px;
    }
    .header-search:focus {
        outline: none;
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.2);
    }
    .header-search::placeholder { color: var(--text-secondary); }

    .notification-dot {
        position: absolute;
        top: -5px;
        right: -5px;
        width: 12px;
        height: 12px;
        background: linear-gradient(to right, var(--danger), #ff7b7b);
        border-radius: 50%;
        border: 2px solid var(--header-bg);
        box-shadow: 0 0 5px rgba(234, 84, 85, 0.5);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%   { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(234, 84, 85, 0.7); }
        70%  { transform: scale(1);    box-shadow: 0 0 0 6px rgba(234, 84, 85, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(234, 84, 85, 0); }
    }

    .theme-switch { position: relative; display: inline-block; width: 50px; height: 26px; }
    .theme-switch input { opacity: 0; width: 0; height: 0; }
    .slider {
        position: absolute; cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background: linear-gradient(to right, #ccc, #aaa);
        transition: .4s; border-radius: 34px;
    }
    .slider:before {
        position: absolute; content: "";
        height: 20px; width: 20px; left: 3px; bottom: 3px;
        background: linear-gradient(to bottom, #fff, #f0f0f0);
        transition: .4s; border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    input:checked + .slider { background: linear-gradient(to right, var(--primary), var(--secondary)); }
    input:checked + .slider:before {
        transform: translateX(24px);
        background: linear-gradient(to bottom, #fff, #e6e6e6);
    }

    .dropdown-menu {
        display: none;
        position: absolute;
        right: 0;
        top: 100%;
        background-color: var(--card-bg);
        min-width: 240px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        border-radius: 12px;
        z-index: 1000;
        border: 1px solid var(--border-color);
        overflow: hidden;
        margin-top: 8px;
        backdrop-filter: blur(10px);
    }
    .dropdown-menu.show { display: block; animation: fadeIn 0.2s ease; }
    .dropdown-item {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        color: var(--text-primary);
        transition: all 0.3s;
        font-size: 14px;
        border-bottom: 1px solid rgba(var(--primary-rgb), 0.1);
    }
    .dropdown-item:last-child { border-bottom: none; }
    .dropdown-item:hover {
        background-color: rgba(var(--primary-rgb), 0.1);
        padding-left: 20px;
    }

    /* ========================================================================= */
    /* PROGRESS / NOTIFICATIONS / CHART                                          */
    /* ========================================================================= */

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

    /* ========================================================================= */
    /* HEADER / AVATAR / GRADIENTS                                               */
    /* ========================================================================= */

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

    .gradient-bg { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }

    .btn-modern {
        border-radius: 10px;
        padding: 12px 24px;
        font-weight: 500;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(to right, var(--primary), var(--secondary));
        color: white;
        box-shadow: 0 4px 6px rgba(114, 103, 240, 0.3);
        border: none;
    }
    .btn-modern i { margin-right: 8px; }
    .btn-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 15px rgba(114, 103, 240, 0.4);
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
        box-shadow: 0 4px 8px rgba(114, 103, 240, 0.3);
        cursor: pointer;
        transition: all 0.3s;
    }
    .avatar:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 12px rgba(114, 103, 240, 0.4);
    }

    /* ========================================================================= */
    /* THEME GEAR ANIMATION                                                      */
    /* ========================================================================= */

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
    .theme-settings-gear.gear-spin-fast {
        animation: spin-fast 1s linear infinite;
        color: var(--primary) !important;
    }
    @keyframes spin-fast {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }

    /* ========================================================================= */
    /* LOGO                                                                      */
    /* ========================================================================= */

    .logo-section {
        padding: 1.5rem 1.25rem 1rem 1.25rem;
        position: relative;
        border-bottom: 1px solid var(--sidebar-divider, rgba(255, 255, 255, 0.1));
        transition: border-color 0.3s ease;
    }
    .logo-container { display: flex; align-items: center; gap: 0.75rem; }
    .logo-wrapper { position: relative; flex-shrink: 0; }
    .logo-image-container { position: relative; width: 3rem; height: 3rem; }
    .logo-image {
        width: 100%; height: 100%; object-fit: cover;
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
        width: 3rem; height: 3rem; border-radius: 12px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 1.25rem;
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
    .logo-content { flex: 1; min-width: 0; }
    .logo-text-container { display: flex; flex-direction: column; gap: 0.125rem; }

    /* Logo colours come from sidebar tokens so they stay readable on every palette. */
    .logo-shortname {
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.2;
        letter-spacing: -0.025em;
        color: var(--sidebar-logo, #ffffff);
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: color 0.3s ease;
    }
    .logo-fullname {
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1.2;
        color: var(--sidebar-logo-muted, rgba(255, 255, 255, 0.72));
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: color 0.3s ease;
    }
    .logo-separator {
        height: 1px;
        background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.3) 50%, transparent 100%);
        margin: 0.75rem 1rem 0 1rem;
    }

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

    /* ========================================================================= */
    /* LEGACY THEME MODAL (kept for backward compatibility)                      */
    /* ========================================================================= */

    #themeSettingsModal {
        transition: opacity 0.3s ease;
        padding: 1rem;
        z-index: 9999;
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
    .theme-modal-body-compact { padding: 1.5rem; background-color: var(--card-bg); }
    .theme-modal-footer-compact {
        padding: 1.25rem 1.5rem;
        border-top: 1px solid var(--border-color);
        background-color: var(--bg-secondary);
        border-radius: 0 0 16px 16px;
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
    }

    .appearance-grid-compact {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
        margin-bottom: 1.5rem;
    }
    .appearance-option-compact {
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
    .appearance-option-compact:hover {
        background-color: rgba(var(--primary-rgb), 0.1);
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }
    .appearance-option-compact.active {
        border-color: var(--primary);
        background-color: rgba(var(--primary-rgb), 0.15);
        box-shadow: 0 8px 25px rgba(var(--primary-rgb), 0.2);
    }
    .appearance-icon-compact {
        width: 3.5rem; height: 3.5rem;
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
    .appearance-label-compact {
        font-size: 0.75rem;
        font-weight: 600;
        text-align: center;
        line-height: 1.2;
        color: var(--text-primary) !important;
    }

    .theme-grid-compact {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
    }
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
    .theme-option-compact:hover {
        background-color: rgba(var(--primary-rgb), 0.1);
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }
    .theme-option-compact.active {
        border-color: var(--primary);
        background-color: rgba(var(--primary-rgb), 0.15);
        box-shadow: 0 8px 25px rgba(var(--primary-rgb), 0.2);
    }
    .theme-preview-compact {
        width: 100%; height: 3.5rem;
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
    .theme-label-compact {
        font-size: 0.75rem;
        font-weight: 600;
        text-align: center;
        line-height: 1.2;
        color: var(--text-primary) !important;
    }

    .current-selection-compact {
        position: absolute;
        top: 6px; right: 6px;
        width: 18px; height: 18px;
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
        0%   { transform: scale(0); }
        50%  { transform: scale(1.2); }
        100% { transform: scale(1); }
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

    #closeThemeModal, #closeThemeModalBtn {
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
    #closeThemeModal:hover, #closeThemeModalBtn:hover {
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

    /* ========================================================================= */
    /* SCROLL-RELATED ANIMATIONS                                                 */
    /* ========================================================================= */

    @keyframes dragHint {
        0%   { transform: translateX(-50%) translateY(0); opacity: 0; }
        50%  { transform: translateX(-50%) translateY(10px); opacity: 1; }
        100% { transform: translateX(-50%) translateY(20px); opacity: 0; }
    }
    @keyframes slideIn {
        from { opacity: 0; transform: translateX(-20px); }
        to   { opacity: 1; transform: translateX(0); }
    }
    .nav-item {
        animation: slideIn 0.3s ease-out;
        animation-fill-mode: both;
    }
    .nav-item:nth-child(1)  { animation-delay: 0.05s; }
    .nav-item:nth-child(2)  { animation-delay: 0.1s; }
    .nav-item:nth-child(3)  { animation-delay: 0.15s; }
    .nav-item:nth-child(4)  { animation-delay: 0.2s; }
    .nav-item:nth-child(5)  { animation-delay: 0.25s; }
    .nav-item:nth-child(6)  { animation-delay: 0.3s; }
    .nav-item:nth-child(7)  { animation-delay: 0.35s; }
    .nav-item:nth-child(8)  { animation-delay: 0.4s; }
    .nav-item:nth-child(9)  { animation-delay: 0.45s; }
    .nav-item:nth-child(10) { animation-delay: 0.5s; }

    /* ========================================================================= */
    /* RESPONSIVE DESIGN                                                         */
    /* ========================================================================= */

    @media (max-width: 1280px) {
        .content, .content.collapsed { margin-left: 0; width: 100%; }
        .sidebar {
            transform: translateX(-100%);
            width: 280px;
            transition: transform 0.3s ease;
            overflow: hidden;
        }
        .sidebar.active { transform: translateX(0); }
        .sidebar.collapsed { transform: translateX(-100%); width: 280px; }
        .sidebar.collapsed.active { transform: translateX(0); width: 280px; }
        .sidebar.collapsed .logo-text,
        .sidebar.collapsed .nav-text,
        .sidebar.collapsed .menu-text,
        .sidebar.collapsed .version,
        .sidebar.collapsed .theme-text { display: block; }
        .sidebar.collapsed .nav-item {
            justify-content: flex-start;
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }
        .sidebar.collapsed .nav-item i { margin-right: 1rem; }
        .sidebar.collapsed .nav-badge { display: flex; }
        .sidebar.collapsed .nav-divider {
            text-align: left;
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }
        .sidebar.collapsed .nav-divider::before { display: none; }
        .sidebar.collapsed .nav-divider .menu-text { display: inline-block; }
        .sidebar.collapsed .theme-toggle-container {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }
        .toggle-sidebar { display: none; }
        .sidebar .scroll-buttons { display: none; }
        .overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 40;
            backdrop-filter: blur(5px);
        }
        .overlay.active { display: block; }
        .mobile-menu-btn {
            display: block;
            position: fixed;
            left: 16px;
            top: 16px;
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 45;
            box-shadow: 0 4px 12px rgba(114, 103, 240, 0.3);
            border: none;
        }
        .mobile-menu-btn:hover { transform: scale(1.05); background: var(--secondary); }
        .sidebar nav { -webkit-overflow-scrolling: touch; }
    }

    @media (max-width: 1024px) {
        .header-search { width: 200px; }
        .grid-cols-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 768px) {
        .header-search { display: none; }
        .grid-cols-4 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
        .grid-cols-2 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
        .lg\:grid-cols-3 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
        .lg\:grid-cols-2 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
        .chart-container { height: 250px; }
        .theme-modal-compact { width: 98vw; max-width: 450px; margin: 0.5rem; }
        .appearance-grid-compact { grid-template-columns: repeat(3, 1fr); gap: 0.5rem; }
        .theme-grid-compact { grid-template-columns: repeat(3, 1fr); gap: 0.5rem; }
        .appearance-icon-compact { width: 3rem; height: 3rem; font-size: 1.25rem; }
        .theme-preview-compact { height: 3rem; }
    }

    @media (max-width: 480px) {
        .header-buttons { display: none; }
        .stat-card h2 { font-size: 1.5rem; }
        .card { padding: 1rem; }
        .p-6 { padding: 1rem; }
        main.p-6 { padding: 0.5rem; }
        .chart-container { height: 200px; }
        .theme-modal-compact { width: 100vw; max-width: none; margin: 0; border-radius: 0; }
        .appearance-grid-compact { grid-template-columns: repeat(3, 1fr); gap: 0.25rem; }
        .theme-grid-compact { grid-template-columns: repeat(3, 1fr); gap: 0.25rem; }
        .appearance-option-compact,
        .theme-option-compact { padding: 0.5rem 0.25rem; }
        .appearance-icon-compact { width: 2.5rem; height: 2.5rem; font-size: 1.1rem; }
        .theme-preview-compact { height: 2.5rem; }
        .appearance-label-compact,
        .theme-label-compact { font-size: 0.65rem; }
    }

    @media (min-width: 1920px) {
        .container { max-width: 1800px; margin: 0 auto; }
        .content { margin-left: auto; margin-right: auto; }
        .sidebar { width: 320px; }
        .sidebar.collapsed { width: 100px; }
        .content { margin-left: 320px; width: calc(100% - 320px); }
        .content.collapsed { margin-left: 100px; width: calc(100% - 100px); }
        .sidebar nav::-webkit-scrollbar { width: 6px; }
        .toggle-sidebar { width: 42px; height: 42px; right: -21px; }
        .toggle-sidebar i { font-size: 18px; }
    }

    /* Custom scrollbar */
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: var(--bg-primary); }
    ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--secondary); }

    .mobile-menu-btn { display: none; }
    @media (max-width: 1280px) {
        .mobile-menu-btn { display: block; }
        .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
        .sidebar.active { transform: translateX(0); }
        .overlay.active { display: block; }
    }

    * { transition: color 0.3s, background-color 0.3s, border-color 0.3s; }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .animate-fadeInUp { animation: fadeInUp 0.5s ease-out; }

    @keyframes modalFadeIn {
        from { opacity: 0; transform: scale(0.9) translateY(-20px); }
        to   { opacity: 1; transform: scale(1) translateY(0); }
    }

    @keyframes modalSlideUp {
        from { opacity: 0; transform: translateY(30px) scale(0.96); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
            scroll-behavior: auto !important;
        }
    }

    @media print {
        .sidebar, .header, .mobile-menu-btn, .overlay,
        .scroll-buttons, .scroll-hint, .scroll-indicator {
            display: none !important;
        }
        .content { margin-left: 0 !important; width: 100% !important; }
    }

    /* ========================================================================= */
    /* THEME MODAL — ALPINE-COMPATIBLE (legacy active states)                    */
    /* ========================================================================= */

    .theme-modal-modern {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        box-shadow: 0 25px 80px rgba(0,0,0,0.35);
        animation: modalSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .theme-modal-modern::-webkit-scrollbar { width: 4px; }
    .theme-modal-modern::-webkit-scrollbar-track { background: transparent; }
    .theme-modal-modern::-webkit-scrollbar-thumb {
        background: var(--border-color);
        border-radius: 9999px;
    }

    .appearance-option.active,
    .sidebar-theme-option.active,
    .density-option.active,
    .header-option.active {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 2px var(--primary), 0 4px 12px rgba(var(--primary-rgb), 0.15);
    }
    .appearance-option:hover,
    .sidebar-theme-option:hover,
    .density-option:hover,
    .header-option:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    }

    @media (max-width: 640px) {
        .theme-modal-modern { max-width: 100%; margin: 0.5rem; border-radius: 16px; max-height: 95vh; }
        .theme-modal-modern .grid-cols-3 { grid-template-columns: repeat(3, 1fr); gap: 0.5rem; }
        .theme-modal-modern .grid-cols-5 { grid-template-columns: repeat(5, 1fr); gap: 0.25rem; }
        .theme-modal-modern .grid-cols-2 { grid-template-columns: 1fr; gap: 1rem; }
    }

    @media (prefers-color-scheme: dark) {
        .theme-modal-modern { border-color: rgba(255, 255, 255, 0.08); }
    }

    .theme-color-picker input[type="color"] {
        -webkit-appearance: none; -moz-appearance: none; appearance: none;
        padding: 2px; cursor: pointer; width: 32px; height: 32px;
    }
    .theme-color-picker input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
    .theme-color-picker input[type="color"]::-webkit-color-swatch { border: none; border-radius: 4px; }
    .theme-color-picker input[type="color"]::-moz-color-swatch { border: none; border-radius: 4px; }

    /* ========================================================================= */
    /* ALL MODALS — shared polish                                                */
    /* ========================================================================= */

    .billing-modal, .email-modal, .sms-modal, .whatsapp-modal,
    .invoice-modal, .admin-tools-modal, .search-modal-container {
        border-radius: 16px;
    }

    .billing-option-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12); }
    .billing-option-card .billing-arrow { transition: all 0.3s ease; }
    .billing-option-card:hover .billing-arrow { transform: translateX(4px); }
    .stat-badge { font-weight: 500; }

    .account-item:hover { background-color: rgba(255, 255, 255, 0.05); }
    .quick-action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    .quick-action-btn .w-8.h-8 { transition: all 0.3s ease; }
    .quick-action-btn:hover .w-8.h-8 { transform: scale(1.1); }

    .provider-card { transition: all 0.2s ease; }
    .provider-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }

    .modal-scrollable-body { scroll-behavior: smooth; }
    .scroll-indicator {
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        padding: 4px 12px;
        border-radius: 9999px;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(8px);
        color: white;
        font-size: 12px;
        z-index: 10;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .scroll-indicator.top-indicator    { top: 60px; }
    .scroll-indicator.bottom-indicator { bottom: 80px; }
    .scroll-indicator:hover            { background: rgba(0, 0, 0, 0.7); }

    .invoice-card { transition: all 0.3s ease; }
    .invoice-card:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1); }
    .invoice-card .invoice-arrow { transition: all 0.3s ease; }
    .invoice-card:hover .invoice-arrow { transform: translateX(4px); opacity: 1 !important; }

    .admin-tools-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem;
        border-radius: 12px;
        transition: all 0.3s ease;
        background-color: var(--bg-secondary);
        border: 1px solid var(--border-color);
        cursor: pointer;
        text-decoration: none;
    }
    .admin-tools-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        border-color: var(--primary);
    }
    .admin-tools-icon-container {
        width: 3rem; height: 3rem;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        background: rgba(var(--primary-rgb), 0.1);
    }
    .admin-tools-content { flex: 1; }
    .admin-tools-title { font-weight: 600; color: var(--text-primary); margin-bottom: 0.25rem; }
    .admin-tools-description { font-size: 0.8rem; color: var(--text-secondary); }
    .admin-tools-arrow { opacity: 0; transition: all 0.3s ease; color: var(--text-secondary); }
    .admin-tools-card:hover .admin-tools-arrow { opacity: 1; transform: translateX(4px); }

    /* ========================================================================= */
    /* THEME SETTINGS MODAL — v3 (SOLID CIRCLE SWATCHES + LIVE PREVIEW)          */
    /* ========================================================================= */

    .theme-modal .theme-option-label {
        font-size: 11px;
        color: var(--text-secondary);
        display: block;
        margin-top: 6px;
        text-align: center;
        transition: color 0.2s ease;
    }

    /* ---------- Appearance grid ---------- */
    .theme-appearance-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    }
    .theme-appearance-btn {
        background: transparent;
        border: none;
        padding: 0;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        transition: transform 0.2s ease;
    }
    .theme-appearance-btn:hover { transform: translateY(-2px); }

    .theme-appearance-preview {
        width: 100%;
        height: 64px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--border-color);
        transition: all 0.2s ease;
        font-size: 20px;
    }
    .theme-appearance-preview--light  { background: #ffffff; color: #f59e0b; }
    .theme-appearance-preview--dark   { background: #1a1e2c; color: #93c5fd; }
    .theme-appearance-preview--system {
        background: linear-gradient(90deg, #ffffff 0%, #ffffff 50%, #1a1e2c 50%, #1a1e2c 100%);
        color: var(--text-primary);
    }
    .theme-appearance-btn.active .theme-appearance-preview {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15);
    }
    .theme-appearance-btn.active .theme-option-label {
        color: var(--primary);
        font-weight: 600;
    }

    /* ---------- Sidebar theme grid (solid circles) ---------- */
    .sidebar-theme-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 10px;
    }
    @media (max-width: 480px) {
        .sidebar-theme-grid { grid-template-columns: repeat(3, 1fr); }
    }
    .sidebar-theme-btn {
        background: transparent;
        border: none;
        padding: 0;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        transition: transform 0.2s ease;
    }
    .sidebar-theme-btn:hover { transform: translateY(-2px); }

    .sidebar-swatch {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: block;
        position: relative;
        transition: all 0.2s ease;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
        border: 2px solid transparent;
    }
    .sidebar-theme-btn.active .sidebar-swatch {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.2), 0 2px 6px rgba(0, 0, 0, 0.15);
        transform: scale(1.05);
    }
    .sidebar-theme-btn.active .theme-option-label {
        color: var(--primary);
        font-weight: 600;
    }

    /* Custom swatch — split circle for primary / secondary */
    .sidebar-swatch--custom {
        overflow: hidden;
        background: transparent;
        display: flex;
    }
    .sidebar-swatch-half {
        flex: 1;
        height: 100%;
        display: block;
        transition: background-color 0.2s ease;
    }
    .sidebar-swatch-half--left {
        background-color: #7267f0;
        border-top-left-radius: 50%;
        border-bottom-left-radius: 50%;
    }
    .sidebar-swatch-half--right {
        background-color: #6258e0;
        border-top-right-radius: 50%;
        border-bottom-right-radius: 50%;
    }

    /* ---------- Custom color inputs ---------- */
    .custom-colors-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }
    @media (max-width: 480px) {
        .custom-colors-grid { grid-template-columns: 1fr; }
    }
    .color-input-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        border-radius: 10px;
        border: 1px solid var(--border-color);
        background-color: var(--bg-secondary);
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
    }
    .color-input-wrapper:hover {
        border-color: var(--primary);
        background-color: rgba(var(--primary-rgb), 0.04);
    }
    .color-input-hidden {
        position: absolute;
        opacity: 0;
        pointer-events: none;
        width: 1px;
        height: 1px;
        top: 0;
        left: 0;
    }
    .color-input-swatch {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        flex-shrink: 0;
        border: 2px solid var(--border-color);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2);
        transition: background-color 0.2s ease;
    }
    .color-input-meta {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .color-input-label {
        font-size: 11px;
        font-weight: 500;
        color: var(--text-primary);
        line-height: 1.2;
    }
    .color-input-value {
        font-size: 11px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        color: var(--text-secondary);
        line-height: 1.2;
    }

    /* ---------- Live preview frame ---------- */
    .theme-preview-frame {
        display: flex;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid var(--border-color);
        background-color: var(--bg-secondary);
        height: 120px;
        transition: all 0.3s ease;
    }
    .theme-preview-sidebar {
        width: 32%;
        background-color: #7267f0;
        padding: 10px 8px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        transition: background-color 0.3s ease;
    }
    .theme-preview-logo {
        width: 20px;
        height: 20px;
        border-radius: 6px;
        background-color: rgba(255, 255, 255, 0.35);
        transition: background-color 0.3s ease;
    }
    .theme-preview-nav {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-top: 6px;
    }
    .theme-preview-nav span {
        height: 5px;
        border-radius: 3px;
        background-color: rgba(255, 255, 255, 0.28);
        display: block;
        transition: background-color 0.3s ease;
    }
    .theme-preview-nav span:nth-child(1) { width: 80%; }
    .theme-preview-nav span:nth-child(2) { width: 65%; }
    .theme-preview-nav span:nth-child(3) { width: 72%; }
    .theme-preview-content {
        flex: 1;
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        transition: background-color 0.3s ease;
    }
    .theme-preview-header {
        height: 10px;
        border-radius: 4px;
        background-color: rgba(0, 0, 0, 0.08);
        width: 55%;
        transition: background-color 0.3s ease;
    }
    .theme-preview-block {
        height: 16px;
        border-radius: 4px;
        background-color: rgba(0, 0, 0, 0.06);
        transition: background-color 0.3s ease;
    }
    .theme-preview-block.short { width: 70%; }
    [data-theme="dark"] .theme-preview-header,
    [data-theme="dark"] .theme-preview-block {
        background-color: rgba(255, 255, 255, 0.08);
    }

    /* ---------- Save indicator ---------- */
    #themeSaveIndicator.hidden { display: none; }

    /* ---------- Theme modal scrollbar ---------- */
    .theme-modal-body::-webkit-scrollbar { width: 6px; }
    .theme-modal-body::-webkit-scrollbar-thumb {
        background: #94a3b8;
        border-radius: 3px;
    }
    [data-theme="dark"] .theme-modal-body::-webkit-scrollbar-thumb {
        background: #4a5568;
    }
</style>