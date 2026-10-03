<!-- Using only Tailwind CSS -->
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* EXISTING CSS VARIABLES AND BASE STYLES */
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
        --info: 45, 212, 232;
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
    
    /* Sidebar themes - Modernized */
    [data-sidebar-theme="default"] {
        --sidebar-bg: linear-gradient(180deg, var(--primary) 0%, #6258e0 100%);
        --sidebar-text: white;
        --sidebar-hover: rgba(255, 255, 255, 0.12);
        --sidebar-active: rgba(255, 255, 255, 0.2);
        --sidebar-border: transparent;
        --sidebar-shadow: 0 0 20px rgba(114, 103, 240, 0.3);
    }
    
    [data-sidebar-theme="dark"] {
        --sidebar-bg: linear-gradient(180deg, #232933 0%, #1e2229 100%);
        --sidebar-text: #ecf0f1;
        --sidebar-hover: rgba(236, 240, 241, 0.08);
        --sidebar-active: rgba(52, 152, 219, 0.2);
        --sidebar-border: #3498db;
        --sidebar-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
    }
    
    [data-sidebar-theme="light"] {
        --sidebar-bg: linear-gradient(180deg, #ffffff 0%, #f5f7f9 100%);
        --sidebar-text: #4a5568;
        --sidebar-hover: rgba(114, 103, 240, 0.1);
        --sidebar-active: rgba(114, 103, 240, 0.15);
        --sidebar-border: #7267f0;
        --sidebar-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
    }
    
    [data-sidebar-theme="blue"] {
        --sidebar-bg: linear-gradient(180deg, #1a56db 0%, #1e429f 100%);
        --sidebar-text: white;
        --sidebar-hover: rgba(255, 255, 255, 0.12);
        --sidebar-active: rgba(100, 255, 218, 0.2);
        --sidebar-border: #64FFDA;
        --sidebar-shadow: 0 0 20px rgba(26, 86, 219, 0.3);
    }
    
    [data-sidebar-theme="green"] {
        --sidebar-bg: linear-gradient(180deg, #057a55 0%, #0a5c36 100%);
        --sidebar-text: white;
        --sidebar-hover: rgba(255, 255, 255, 0.12);
        --sidebar-active: rgba(105, 240, 174, 0.2);
        --sidebar-border: #69F0AE;
        --sidebar-shadow: 0 0 20px rgba(5, 122, 85, 0.3);
    }
    
    body {
        background-color: var(--bg-primary);
        color: var(--text-primary);
        transition: all 0.3s ease;
        overflow-x: hidden;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.6;
    }

    /* ========================================================================= */
    /* ENHANCED SIDEBAR STYLING WITH SCROLL FEATURES */
    /* ========================================================================= */
    
    .sidebar {
        width: 280px;
        height: 100vh;
        max-height: 100vh;
        background: var(--sidebar-bg);
        transition: all 0.3s ease;
        border-right: 1px solid var(--border-color);
        position: fixed;
        z-index: 50;
        left: 0;
        top: 0;
        box-shadow: var(--sidebar-shadow);
        display: flex;
        flex-direction: column;
        overflow: visible; /* Changed to visible to allow toggle button to overflow */
    }
    
    .sidebar.collapsed {
        width: 80px;
    }
    
    /* Logo section - fixed at top */
    .sidebar .logo-section {
        flex-shrink: 0;
        padding: 1.5rem 1.25rem 1rem 1.25rem;
        position: relative;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        z-index: 2;
        overflow: visible; /* Allow button to overflow */
    }
    
    /* ========================================================================= */
    /* FIXED SIDEBAR TOGGLE BUTTON - NOW VISIBLE AND PROPERLY POSITIONED */
    /* ========================================================================= */
    
    .toggle-sidebar {
        position: absolute;
        right: -16px; /* Increased negative offset for better visibility */
        top: 24px;
        width: 36px; /* Slightly larger */
        height: 36px; /* Slightly larger */
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        z-index: 1000; /* Ultra high z-index */
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 2px solid var(--primary);
        color: var(--primary);
        background-color: white;
        box-shadow: 0 4px 12px rgba(114, 103, 240, 0.3);
        pointer-events: auto; /* Ensure clickability */
    }
    
    .toggle-sidebar i {
        font-size: 16px;
        transition: transform 0.3s ease;
    }
    
    .toggle-sidebar:hover {
        transform: scale(1.15);
        background: var(--primary);
        color: white;
        box-shadow: 0 6px 16px rgba(114, 103, 240, 0.5);
        border-color: white;
    }
    
    .toggle-sidebar:hover i {
        transform: rotate(180deg);
    }
    
    .toggle-sidebar:active {
        transform: scale(0.95);
    }
    
    /* Collapsed state rotation */
    .sidebar.collapsed .toggle-sidebar i {
        transform: rotate(180deg);
    }
    
    /* Collapsed mode positioning */
    .sidebar.collapsed .toggle-sidebar {
        right: -18px; /* Slightly more offset in collapsed mode */
        background: var(--primary);
        color: white;
        border-color: white;
    }
    
    /* Theme-specific adjustments */
    [data-sidebar-theme="dark"] .toggle-sidebar,
    [data-sidebar-theme="default"] .toggle-sidebar,
    [data-sidebar-theme="blue"] .toggle-sidebar,
    [data-sidebar-theme="green"] .toggle-sidebar {
        background: white;
        color: var(--primary);
        border-color: var(--primary);
    }
    
    [data-sidebar-theme="dark"] .toggle-sidebar:hover,
    [data-sidebar-theme="default"] .toggle-sidebar:hover,
    [data-sidebar-theme="blue"] .toggle-sidebar:hover,
    [data-sidebar-theme="green"] .toggle-sidebar:hover {
        background: var(--primary);
        color: white;
        border-color: white;
    }
    
    [data-sidebar-theme="light"] .toggle-sidebar {
        background: var(--primary);
        color: white;
        border-color: white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    
    [data-sidebar-theme="light"] .toggle-sidebar:hover {
        background: white;
        color: var(--primary);
        border-color: var(--primary);
    }
    
    /* Gentle pulse animation to draw attention */
    @keyframes gentlePulse {
        0% {
            box-shadow: 0 4px 12px rgba(114, 103, 240, 0.3);
        }
        50% {
            box-shadow: 0 4px 20px rgba(114, 103, 240, 0.7);
            transform: scale(1.05);
        }
        100% {
            box-shadow: 0 4px 12px rgba(114, 103, 240, 0.3);
        }
    }
    
    .toggle-sidebar {
        animation: gentlePulse 2s infinite ease-in-out;
    }
    
    .toggle-sidebar.interacted {
        animation: none;
    }
    
    /* Navigation container - scrollable */
    .sidebar nav {
        flex: 1 1 auto;
        overflow-y: auto;
        overflow-x: hidden;
        scrollbar-width: thin;
        scrollbar-color: var(--sidebar-text) rgba(255, 255, 255, 0.1);
        padding-bottom: 1rem;
        scroll-behavior: smooth;
        
        -webkit-mask-image: linear-gradient(
            to bottom,
            transparent,
            black 10px,
            black 90%,
            transparent
        );
        mask-image: linear-gradient(
            to bottom,
            transparent,
            black 10px,
            black 90%,
            transparent
        );
        
        &::-webkit-scrollbar {
            width: 4px;
        }
        
        &::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }
        
        &::-webkit-scrollbar-thumb {
            background: var(--sidebar-text);
            border-radius: 4px;
            opacity: 0.5;
            transition: all 0.3s ease;
        }
        
        &::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.8);
            width: 6px;
        }
    }
    
    /* Collapsed mode specific scroll adjustments */
    .sidebar.collapsed nav {
        scrollbar-width: none;
    }
    
    .sidebar.collapsed nav::-webkit-scrollbar {
        display: none;
    }
    
    /* Settings button - fixed at bottom */
    .sidebar .absolute.bottom-0,
    .sidebar .settings-container {
        position: sticky;
        bottom: 0;
        flex-shrink: 0;
        background: inherit;
        backdrop-filter: blur(10px);
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        margin-top: auto;
        padding: 1rem;
        z-index: 2;
    }
    
    /* Gradient overlay at bottom for smooth transition */
    .sidebar::after {
        content: '';
        position: absolute;
        bottom: 70px;
        left: 0;
        right: 0;
        height: 30px;
        pointer-events: none;
        background: linear-gradient(
            to bottom,
            transparent,
            var(--sidebar-bg-start, rgba(114, 103, 240, 0.8))
        );
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 5;
    }
    
    .sidebar.scrollable-bottom::after {
        opacity: 1;
    }
    
    /* Scroll indicator */
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
    
    .sidebar:hover .scroll-indicator {
        opacity: 0.5;
    }
    
    /* Scroll hint for new users */
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
    
    .sidebar.has-scrollable-content:hover .scroll-hint {
        opacity: 1;
    }
    
    /* Floating scroll buttons */
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
    
    .sidebar:hover .scroll-buttons {
        opacity: 0.8;
        pointer-events: auto;
    }
    
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
    
    .sidebar.collapsed .scroll-buttons {
        right: 10px;
    }
    
    /* Collapsed mode adjustments */
    .sidebar.collapsed .logo-text,
    .sidebar.collapsed .nav-text,
    .sidebar.collapsed .menu-text,
    .sidebar.collapsed .version,
    .sidebar.collapsed .theme-text {
        display: none;
    }
    
    .sidebar.collapsed .nav-item {
        justify-content: center;
        padding: 12px 0;
        margin: 4px 8px;
    }
    
    .sidebar.collapsed .nav-item i {
        margin-right: 0;
    }
    
    .sidebar.collapsed .nav-badge {
        display: none;
    }
    
    .sidebar.collapsed .nav-divider {
        text-align: center;
        padding: 8px 0;
    }
    
    .sidebar.collapsed .nav-divider .menu-text {
        display: none;
    }
    
    .sidebar.collapsed .nav-divider::before {
        content: '•••';
        font-size: 12px;
        letter-spacing: 2px;
        opacity: 0.5;
    }
    
    /* Content area adjustments */
    .content {
        transition: all 0.3s ease;
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
    
    /* Enhanced Navigation Items */
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
        position: relative;
        overflow: hidden;
    }
    
    .nav-item:hover, .nav-item.active {
        background-color: var(--sidebar-hover);
        border-left-color: var(--sidebar-active);
        transform: translateX(4px);
    }
    
    /* Ripple effect on click */
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
    
    /* Enhanced Card Styling */
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
    
    .users-card {
        border-left-color: var(--primary);
    }
    
    .users-card::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%237267f0' viewBox='0 0 24 24'%3E%3Cpath d='M12 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-2a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm9 11a1 1 0 0 1-2 0 7 7 0 1 0-14 0 1 1 0 0 1-2 0 9 9 0 1 1 18 0z'/%3E%3C/svg%3E");
    }
    
    .revenue-card {
        border-left-color: var(--success);
    }
    
    .revenue-card::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%2328c76f' viewBox='0 0 24 24'%3E%3Cpath d='M3 0h18a3 3 0 0 1 3 3v18a3 3 0 0 1-3 3H3a3 3 0 0 1-3-3V3a3 3 0 0 1 3-3zm1 7a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H4zm2 2h10v2H6V9zm0 4h6v2H6v-2z'/%3E%3C/svg%3E");
    }
    
    .conversion-card {
        border-left-color: var(--warning);
    }
    
    .conversion-card::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23ff9f43' viewBox='0 0 24 24'%3E%3Cpath d='M13 20h-2V8l-5.5 5.5-1.42-1.42L12 4.16l7.92 7.92-1.42 1.42L13 8v12z'/%3E%3C/svg%3E");
    }
    
    .bounce-card {
        border-left-color: var(--info);
    }
    
    .bounce-card::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%2300cfe8' viewBox='0 0 24 24'%3E%3Cpath d='M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8zm-1-4h2v2h-2zm0-10h2v8h-2z'/%3E%3C/svg%3E");
    }
    
    /* Enhanced Button Styling */
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
    
    .btn-primary:hover::before {
        left: 100%;
    }
    
    /* Enhanced Search Bar */
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
    
    .header-search::placeholder {
        color: var(--text-secondary);
    }
    
    /* Enhanced Notification Dot */
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
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(234, 84, 85, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(234, 84, 85, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(234, 84, 85, 0); }
    }
    
    /* Enhanced Theme Switch */
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
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
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
    
    /* Enhanced Dropdown Menu */
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
    
    .dropdown-menu.show {
        display: block;
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
    }
    
    .dropdown-item:last-child {
        border-bottom: none;
    }
    
    .dropdown-item:hover {
        background-color: rgba(var(--primary-rgb), 0.1);
        padding-left: 20px;
    }
    
    /* Enhanced Progress Bar */
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
    
    /* Enhanced Notification Items */
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
    
    /* Enhanced Chart Container */
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
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Enhanced Theme Selector */
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
    
    .theme-option:hover {
        transform: scale(1.1);
    }
    
    .theme-option.active {
        border-color: white;
        transform: scale(1.15);
        box-shadow: 0 0 0 2px var(--primary);
    }
    
    .theme-option.default { background: linear-gradient(to bottom, #7267f0, #6258e0); }
    .theme-option.dark { background: linear-gradient(to bottom, #232933, #1e2229); }
    .theme-option.light { background: linear-gradient(to bottom, #ffffff, #f5f7f9); }
    .theme-option.blue { background: linear-gradient(to bottom, #1a56db, #1e429f); }
    .theme-option.green { background: linear-gradient(to bottom, #057a55, #0a5c36); }
    
    /* Enhanced Header Styling */
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
    
    /* Modern gradient backgrounds */
    .gradient-bg {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    }
    
    /* Modern buttons */
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
    
    .btn-modern i {
        margin-right: 8px;
    }
    
    .btn-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 15px rgba(114, 103, 240, 0.4);
    }
    
    /* Enhanced Avatar styling */
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
    /* ENHANCED THEME SETTINGS GEAR SPINNING ANIMATION */
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
        to { transform: rotate(360deg); }
    }
    
    /* Enhanced gear animation with color change */
    .theme-settings-gear.gear-spin-fast {
        animation: spin-fast 1s linear infinite;
        color: var(--primary) !important;
    }
    
    @keyframes spin-fast {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    /* ========================================================================= */
    /* ENHANCED PROFESSIONAL LOGO STYLES */
    /* ========================================================================= */
    
    .logo-section {
        padding: 1.5rem 1.25rem 1rem 1.25rem;
        position: relative;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
    
    .logo-fallback {
        display: none;
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
    
    .sidebar.collapsed .logo-content {
        display: none;
    }
    
    .sidebar.collapsed .logo-separator {
        margin: 0.75rem 0.5rem 0 0.5rem;
    }
    
    /* Enhanced logo visibility for different sidebar themes */
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
    
    /* ========================================================================= */
    /* ENHANCED THEME MODAL STYLES - UPDATED TO MATCH SEARCH MODAL SIZE */
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
    
    /* Updated theme modal to match search modal size */
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
    
    /* Enhanced appearance options */
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
    
    .appearance-label-compact {
        font-size: 0.75rem;
        font-weight: 600;
        text-align: center;
        line-height: 1.2;
        color: var(--text-primary) !important;
    }
    
    /* Enhanced theme options */
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
    
    .theme-label-compact {
        font-size: 0.75rem;
        font-weight: 600;
        text-align: center;
        line-height: 1.2;
        color: var(--text-primary) !important;
    }
    
    /* Current selection indicator */
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
        0% { transform: scale(0); }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); }
    }
    
    /* Section headers */
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
    
    /* Close button styling */
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
    
    /* Modal footer button styling */
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
    
    /* Ensure all text in modal is visible */
    #themeSettingsModal * {
        color: inherit !important;
    }
    
    /* ========================================================================= */
    /* SCROLL-RELATED ANIMATIONS */
    /* ========================================================================= */
    
    @keyframes dragHint {
        0% { transform: translateX(-50%) translateY(0); opacity: 0; }
        50% { transform: translateX(-50%) translateY(10px); opacity: 1; }
        100% { transform: translateX(-50%) translateY(20px); opacity: 0; }
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .nav-item {
        animation: slideIn 0.3s ease-out;
        animation-fill-mode: both;
    }
    
    /* Stagger animation for nav items */
    .nav-item:nth-child(1) { animation-delay: 0.05s; }
    .nav-item:nth-child(2) { animation-delay: 0.1s; }
    .nav-item:nth-child(3) { animation-delay: 0.15s; }
    .nav-item:nth-child(4) { animation-delay: 0.2s; }
    .nav-item:nth-child(5) { animation-delay: 0.25s; }
    .nav-item:nth-child(6) { animation-delay: 0.3s; }
    .nav-item:nth-child(7) { animation-delay: 0.35s; }
    .nav-item:nth-child(8) { animation-delay: 0.4s; }
    .nav-item:nth-child(9) { animation-delay: 0.45s; }
    .nav-item:nth-child(10) { animation-delay: 0.5s; }
    
    /* ========================================================================= */
    /* RESPONSIVE DESIGN */
    /* ========================================================================= */
    
    @media (max-width: 1280px) {
        .content, .content.collapsed {
            margin-left: 0;
            width: 100%;
        }
        
        .sidebar {
            transform: translateX(-100%);
            width: 280px;
            transition: transform 0.3s ease;
            overflow: hidden; /* Reset to hidden for mobile */
        }
        
        .sidebar.active {
            transform: translateX(0);
        }
        
        .sidebar.collapsed {
            transform: translateX(-100%);
            width: 280px;
        }
        
        .sidebar.collapsed.active {
            transform: translateX(0);
            width: 280px;
        }
        
        .sidebar.collapsed .logo-text,
        .sidebar.collapsed .nav-text,
        .sidebar.collapsed .menu-text,
        .sidebar.collapsed .version,
        .sidebar.collapsed .theme-text {
            display: block;
        }
        
        .sidebar.collapsed .nav-item {
            justify-content: flex-start;
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }
        
        .sidebar.collapsed .nav-item i {
            margin-right: 1rem;
        }
        
        .sidebar.collapsed .nav-badge {
            display: flex;
        }
        
        .sidebar.collapsed .nav-divider {
            text-align: left;
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }
        
        .sidebar.collapsed .nav-divider::before {
            display: none;
        }
        
        .sidebar.collapsed .nav-divider .menu-text {
            display: inline-block;
        }
        
        .sidebar.collapsed .theme-toggle-container {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }
        
        /* Hide toggle button on mobile */
        .toggle-sidebar {
            display: none;
        }
        
        /* Hide scroll buttons on mobile */
        .sidebar .scroll-buttons {
            display: none;
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 40;
            backdrop-filter: blur(5px);
        }
        
        .overlay.active {
            display: block;
        }
        
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
        
        .mobile-menu-btn:hover {
            transform: scale(1.05);
            background: var(--secondary);
        }
        
        /* Adjust scrollbar for mobile */
        .sidebar nav {
            -webkit-overflow-scrolling: touch;
        }
    }
    
    @media (max-width: 1024px) {
        .header-search {
            width: 200px;
        }
        
        .grid-cols-4 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    
    @media (max-width: 768px) {
        .header-search {
            display: none;
        }
        
        .grid-cols-4 {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }
        
        .grid-cols-2 {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }
        
        .lg\:grid-cols-3 {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }
        
        .lg\:grid-cols-2 {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }
        
        .chart-container {
            height: 250px;
        }
        
        .theme-modal-compact {
            width: 98vw;
            max-width: 450px;
            margin: 0.5rem;
        }
        
        .appearance-grid-compact {
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
        }
        
        .theme-grid-compact {
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
        }
        
        .appearance-icon-compact {
            width: 3rem;
            height: 3rem;
            font-size: 1.25rem;
        }
        
        .theme-preview-compact {
            height: 3rem;
        }
    }
    
    @media (max-width: 480px) {
        .header-buttons {
            display: none;
        }
        
        .stat-card h2 {
            font-size: 1.5rem;
        }
        
        .card {
            padding: 1rem;
        }
        
        .p-6 {
            padding: 1rem;
        }
        
        main.p-6 {
            padding: 0.5rem;
        }
        
        .chart-container {
            height: 200px;
        }
        
        .theme-modal-compact {
            width: 100vw;
            max-width: none;
            margin: 0;
            border-radius: 0;
        }
        
        .appearance-grid-compact {
            grid-template-columns: repeat(3, 1fr);
            gap: 0.25rem;
        }
        
        .theme-grid-compact {
            grid-template-columns: repeat(3, 1fr);
            gap: 0.25rem;
        }
        
        .appearance-option-compact,
        .theme-option-compact {
            padding: 0.5rem 0.25rem;
        }
        
        .appearance-icon-compact {
            width: 2.5rem;
            height: 2.5rem;
            font-size: 1.1rem;
        }
        
        .theme-preview-compact {
            height: 2.5rem;
        }
        
        .appearance-label-compact,
        .theme-label-compact {
            font-size: 0.65rem;
        }
    }
    
    /* Extra large screens */
    @media (min-width: 1920px) {
        .container {
            max-width: 1800px;
            margin: 0 auto;
        }
        
        .content {
            margin-left: auto;
            margin-right: auto;
        }
        
        .sidebar {
            width: 320px;
        }
        
        .sidebar.collapsed {
            width: 100px;
        }
        
        .content {
            margin-left: 320px;
            width: calc(100% - 320px);
        }
        
        .content.collapsed {
            margin-left: 100px;
            width: calc(100% - 100px);
        }
        
        /* Larger scrollbar for 4K */
        .sidebar nav::-webkit-scrollbar {
            width: 6px;
        }
        
        .toggle-sidebar {
            width: 42px;
            height: 42px;
            right: -21px;
        }
        
        .toggle-sidebar i {
            font-size: 18px;
        }
    }
    
    /* Custom scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
    }
    
    ::-webkit-scrollbar-track {
        background: var(--bg-primary);
    }
    
    ::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 4px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: var(--secondary);
    }

    /* FIXED: Mobile sidebar visibility */
    .mobile-menu-btn {
        display: none;
    }

    @media (max-width: 1280px) {
        .mobile-menu-btn {
            display: block;
        }
        
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        
        .sidebar.active {
            transform: translateX(0);
        }
        
        .overlay.active {
            display: block;
        }
    }
    
    /* Smooth transitions for all interactive elements */
    * {
        transition: color 0.3s, background-color 0.3s, border-color 0.3s;
    }
    
    /* Animation for page content */
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
    
    .animate-fadeInUp {
        animation: fadeInUp 0.5s ease-out;
    }
    
    /* Modal animation */
    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: scale(0.9) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
    
    /* Reduced motion preferences */
    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
            scroll-behavior: auto !important;
        }
    }
    
    /* Print styles */
    @media print {
        .sidebar,
        .header,
        .mobile-menu-btn,
        .overlay,
        .scroll-buttons,
        .scroll-hint,
        .scroll-indicator {
            display: none !important;
        }
        
        .content {
            margin-left: 0 !important;
            width: 100% !important;
        }
    }
</style>