<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Accept Invitation - ' . config('app.name'))</title>
    
    <!-- ============ FAVICON ============ -->
    @php
        $settings = \App\Models\SystemSetting::getSettings();
    @endphp
    @if($settings->hasFavicon())
        <link rel="icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="apple-touch-icon" href="{{ $settings->getFaviconUrl() }}">
        <!-- Additional favicon sizes for better browser support -->
        <link rel="icon" type="image/png" sizes="16x16" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="64x64" href="{{ $settings->getFaviconUrl() }}">
        <!-- Microsoft Edge Tile -->
        <meta name="msapplication-TileImage" content="{{ $settings->getFaviconUrl() }}">
        <meta name="msapplication-TileColor" content="#7267f0">
    @else
        <!-- Default favicon fallback -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <meta name="msapplication-TileColor" content="#7267f0">
    @endif
    
    <!-- Using only Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
        /* ENHANCED STYLES - COMPLEMENTARY ADDITIONS */
        /* ========================================================================= */

        /* NEW: Glass morphism effects */
        .glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .glass-dark {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* NEW: Advanced gradient backgrounds */
        .gradient-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .gradient-success {
            background: linear-gradient(135deg, var(--success) 0%, #1ee7a6 100%);
        }

        .gradient-warning {
            background: linear-gradient(135deg, var(--warning) 0%, #ffb74d 100%);
        }

        .gradient-danger {
            background: linear-gradient(135deg, var(--danger) 0%, #ff6b6b 100%);
        }

        .gradient-info {
            background: linear-gradient(135deg, var(--info) 0%, #29d2e4 100%);
        }

        /* NEW: Advanced card variations */
        .card-hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover-lift:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .card-glow {
            box-shadow: 0 0 20px rgba(var(--primary-rgb), 0.15);
        }

        .card-glow:hover {
            box-shadow: 0 0 30px rgba(var(--primary-rgb), 0.25);
        }

        /* NEW: Advanced button variations */
        .btn-glow {
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.3);
        }

        .btn-glow::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s;
        }

        .btn-glow:hover::before {
            left: 100%;
        }

        .btn-outline-glow {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
            position: relative;
            overflow: hidden;
        }

        .btn-outline-glow::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(var(--primary-rgb), 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-outline-glow:hover::before {
            left: 100%;
        }

        .btn-outline-glow:hover {
            background: var(--primary);
            color: white;
            box-shadow: 0 0 20px rgba(var(--primary-rgb), 0.4);
        }

        /* NEW: Advanced loading animations */
        .loading-dots {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .loading-dots span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            animation: loading-bounce 1.4s ease-in-out infinite both;
        }

        .loading-dots span:nth-child(1) { animation-delay: -0.32s; }
        .loading-dots span:nth-child(2) { animation-delay: -0.16s; }

        @keyframes loading-bounce {
            0%, 80%, 100% {
                transform: scale(0);
                opacity: 0.5;
            }
            40% {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* NEW: Advanced progress indicators */
        .progress-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: conic-gradient(var(--primary) 0%, var(--border-color) 0%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .progress-circle::before {
            content: '';
            position: absolute;
            width: 50px;
            height: 50px;
            background: var(--card-bg);
            border-radius: 50%;
        }

        .progress-circle span {
            position: relative;
            z-index: 1;
            font-weight: 600;
            font-size: 0.875rem;
        }

        /* NEW: Advanced badge styles */
        .badge-pulse {
            position: relative;
        }

        .badge-pulse::after {
            content: '';
            position: absolute;
            top: -2px;
            right: -2px;
            width: 12px;
            height: 12px;
            background: var(--danger);
            border-radius: 50%;
            animation: ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
        }

        @keyframes ping {
            75%, 100% {
                transform: scale(2);
                opacity: 0;
            }
        }

        /* NEW: Advanced table styles */
        .table-modern {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-modern th {
            background: rgba(var(--primary-rgb), 0.05);
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid var(--border-color);
        }

        .table-modern td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.2s;
        }

        .table-modern tr:hover td {
            background: rgba(var(--primary-rgb), 0.02);
        }

        .table-modern tr:last-child td {
            border-bottom: none;
        }

        /* NEW: Advanced form controls */
        .form-input-modern {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: all 0.3s;
            font-size: 14px;
        }

        .form-input-modern:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
            transform: translateY(-2px);
        }

        .form-input-modern:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* NEW: Advanced toggle switches */
        .toggle-modern {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .toggle-modern input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--border-color);
            transition: .4s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background: white;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        input:checked + .toggle-slider {
            background: var(--primary);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }

        /* NEW: Advanced tooltips */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltip-text {
            visibility: hidden;
            width: 200px;
            background: var(--dark);
            color: white;
            text-align: center;
            border-radius: 6px;
            padding: 8px 12px;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
            font-weight: 500;
        }

        .tooltip .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: var(--dark) transparent transparent transparent;
        }

        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* NEW: Advanced alert styles */
        .alert-modern {
            padding: 16px 20px;
            border-radius: 12px;
            border-left: 4px solid;
            background: var(--card-bg);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 16px;
        }

        .alert-success {
            border-left-color: var(--success);
            background: rgba(var(--success-rgb), 0.05);
        }

        .alert-warning {
            border-left-color: var(--warning);
            background: rgba(var(--warning-rgb), 0.05);
        }

        .alert-danger {
            border-left-color: var(--danger);
            background: rgba(var(--danger-rgb), 0.05);
        }

        .alert-info {
            border-left-color: var(--info);
            background: rgba(var(--info-rgb), 0.05);
        }

        /* NEW: Advanced grid layouts */
        .grid-auto-fit {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }

        .grid-auto-fill {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        /* NEW: Advanced animation classes */
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .animate-shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .animate-bounce-in {
            animation: bounceIn 0.6s ease-out;
        }

        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* NEW: Advanced utility classes */
        .text-gradient {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .border-gradient {
            border: 2px solid transparent;
            background: linear-gradient(var(--card-bg), var(--card-bg)) padding-box,
                        linear-gradient(135deg, var(--primary), var(--secondary)) border-box;
        }

        .shadow-glow {
            box-shadow: 0 0 20px rgba(var(--primary-rgb), 0.15);
        }

        .backdrop-glass {
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
        }

        /* ========================================================================= */
        /* EXISTING STYLES (KEEP ALL YOUR ORIGINAL STYLES BELOW) */
        /* ========================================================================= */
        
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
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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
        
        /* Enhanced Logo Styling */
        .logo {
            display: flex;
            align-items: center;
            padding: 24px;
        }
        
        .logo-icon {
            font-size: 28px;
            margin-right: 12px;
            background: linear-gradient(135deg, #fff 0%, #e0e0e0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }
        
        .logo-text {
            font-weight: 700;
            font-size: 22px;
            letter-spacing: -0.5px;
            background: linear-gradient(to right, #fff, #e0e0e0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 2px 2px rgba(0, 0, 0, 0.2));
        }

        /* Guest Layout Specific Styles */
        .guest-header {
            background-color: var(--header-bg);
            border-bottom: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .guest-main {
            min-height: calc(100vh - 140px);
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
        }

        .guest-footer {
            background-color: var(--header-bg);
            border-top: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        /* Form Input Styling */
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: all 0.3s;
            font-size: 14px;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
            transform: translateY(-2px);
        }

        .form-input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Password Strength Meter */
        .password-strength-bar {
            height: 6px;
            border-radius: 3px;
            background-color: rgba(var(--primary-rgb), 0.1);
            overflow: hidden;
            margin-top: 8px;
        }

        .password-strength-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
            background: linear-gradient(to right, var(--primary), var(--secondary));
        }

        /* Toast Notifications */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 20px;
            border-radius: 10px;
            color: white;
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast-success { background: var(--success); }
        .toast-error { background: var(--danger); }
        .toast-warning { background: var(--warning); }
        .toast-info { background: var(--info); }

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

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .card {
                margin: 0.5rem;
                padding: 1rem;
            }

            .guest-header .flex.justify-between {
                flex-direction: column;
                gap: 1rem;
            }

            .guest-header .flex.items-center.space-x-6 {
                justify-content: center;
                width: 100%;
            }
        }

        /* Focus states for accessibility */
        button:focus,
        input:focus,
        select:focus,
        textarea:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* Smooth transitions for all interactive elements */
        * {
            transition: color 0.3s, background-color 0.3s, border-color 0.3s;
        }

        /* Spinning animation for settings icon - Continuous */
        .spinning {
            animation: spin 3s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Animation for modal */
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(-10px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-content {
            animation: modalFadeIn 0.3s ease-out;
        }
    </style>
    
    @stack('styles')
</head>
<body data-theme="light">
    <!-- Enhanced Header for Guest Layout -->
    <header class="guest-header">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <!-- Logo/Brand with enhanced styling -->
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="h-10 w-10 gradient-primary rounded-xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-home text-white text-lg"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ config('app.name', 'PropertyReg') }}
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Field Agent Portal
                        </p>
                    </div>
                </div>

                <!-- Enhanced Theme Toggle and Actions -->
                <div class="flex items-center space-x-6">
                    <!-- Enhanced Theme Toggle -->
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-sun text-yellow-500 text-sm"></i>
                        <label class="theme-switch">
                            <input type="checkbox" id="theme-toggle">
                            <span class="slider"></span>
                        </label>
                        <i class="fas fa-moon text-blue-400 text-sm"></i>
                    </div>
                    
                    <!-- Help Link with enhanced styling -->
                    <a href="mailto:support@propertyreg.com" class="group flex items-center space-x-2 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-all duration-300">
                        <i class="fas fa-question-circle text-purple-500 group-hover:text-purple-600 transition-colors"></i>
                        <span class="text-sm font-medium">Help & Support</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="guest-main">
        @yield('content')
    </main>

    <!-- Enhanced Footer -->
    <footer class="guest-footer">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                <div class="flex items-center space-x-2">
                    <div class="h-6 w-6 gradient-primary rounded-lg flex items-center justify-center">
                        <i class="fas fa-home text-white text-xs"></i>
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        &copy; {{ date('Y') }} {{ config('app.name', 'PropertyReg') }}. All rights reserved.
                    </div>
                </div>
                <div class="flex space-x-6 text-sm">
                    <a href="#" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors duration-300 hover:underline">
                        Privacy Policy
                    </a>
                    <a href="#" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors duration-300 hover:underline">
                        Terms of Service
                    </a>
                    <a href="mailto:support@propertyreg.com" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors duration-300 hover:underline">
                        Contact Support
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Enhanced JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Enhanced Theme Toggle Functionality
            const themeToggle = document.getElementById('theme-toggle');
            const body = document.body;

            // Check for saved theme preference or respect OS preference
            const savedTheme = localStorage.getItem('theme') || 
                               (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

            // Apply the saved theme
            if (savedTheme === 'dark') {
                body.setAttribute('data-theme', 'dark');
                themeToggle.checked = true;
            } else {
                body.setAttribute('data-theme', 'light');
                themeToggle.checked = false;
            }

            // Theme toggle handler
            themeToggle.addEventListener('change', function() {
                if (this.checked) {
                    body.setAttribute('data-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                } else {
                    body.setAttribute('data-theme', 'light');
                    localStorage.setItem('theme', 'light');
                }
            });

            // Enhanced Auto-hide alerts with animation
            const alerts = document.querySelectorAll('.alert, .alert-modern');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    alert.style.transition = 'all 0.3s ease';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 300);
                }, 5000);
            });

            // Enhanced form submission loading states
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitButton = this.querySelector('button[type="submit"]');
                    if (submitButton && !submitButton.disabled) {
                        submitButton.disabled = true;
                        const originalText = submitButton.innerHTML;
                        submitButton.innerHTML = `
                            <div class="flex items-center justify-center">
                                <div class="loading-dots mr-2">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                                Processing...
                            </div>
                        `;
                        
                        // Store original content for potential reset
                        submitButton.setAttribute('data-original-content', originalText);
                    }
                });
            });

            // Add animation to cards when they come into view
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-fadeInUp');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);
            
            // Observe all cards for animation
            document.querySelectorAll('.card').forEach(card => {
                observer.observe(card);
            });

            // Enhanced focus management for accessibility
            const focusableElements = document.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );

            focusableElements.forEach(element => {
                element.addEventListener('focus', function() {
                    this.style.outline = '2px solid var(--primary)';
                    this.style.outlineOffset = '2px';
                });
                
                element.addEventListener('blur', function() {
                    this.style.outline = 'none';
                });
            });

            // Display any flash messages with enhanced toast
            @if(session('success'))
                showToast('{{ session('success') }}', 'success');
            @endif
            
            @if(session('error'))
                showToast('{{ session('error') }}', 'error');
            @endif
            
            @if(session('warning'))
                showToast('{{ session('warning') }}', 'warning');
            @endif
            
            @if(session('info'))
                showToast('{{ session('info') }}', 'info');
            @endif
        });

        // Enhanced Toast notification function
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            const icons = {
                success: 'check-circle',
                error: 'exclamation-triangle',
                warning: 'exclamation-circle',
                info: 'info-circle'
            };
            
            const titles = {
                success: 'Success',
                error: 'Error',
                warning: 'Warning',
                info: 'Information'
            };
            
            toast.innerHTML = `
                <div class="flex items-start space-x-3">
                    <i class="fas fa-${icons[type]} text-white text-lg mt-0.5"></i>
                    <div class="flex-1">
                        <div class="font-semibold text-white">${titles[type]}</div>
                        <div class="text-white text-opacity-90 text-sm mt-1">${message}</div>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-white text-opacity-70 hover:text-opacity-100 transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 5000);
            
            // Click to dismiss
            toast.addEventListener('click', function(e) {
                if (e.target.closest('button')) return;
                this.classList.remove('show');
                setTimeout(() => {
                    if (this.parentNode) {
                        this.parentNode.removeChild(this);
                    }
                }, 300);
            });
        }

        // Enhanced Password visibility toggle
        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(`${inputId}-icon`);
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                icon.classList.add('text-purple-500');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.remove('text-purple-500');
                icon.classList.add('fa-eye');
            }
        }

        // Enhanced Check password strength
        function checkPasswordStrength(password) {
            let strength = 0;
            
            // Length requirement
            if (password.length >= 8) strength += 1;
            
            // Letter requirement
            if (/[a-zA-Z]/.test(password)) strength += 1;
            
            // Number requirement
            if (/[0-9]/.test(password)) strength += 1;
            
            // Special character (bonus)
            if (/[^a-zA-Z0-9]/.test(password)) strength += 1;
            
            return strength;
        }

        // Enhanced Update password strength meter
        function updatePasswordStrength(password) {
            const strength = checkPasswordStrength(password);
            const strengthBar = document.querySelector('.password-strength-fill');
            const strengthText = document.getElementById('password-strength-text');
            
            if (strengthBar && strengthText) {
                const width = (strength / 4) * 100;
                strengthBar.style.width = `${width}%`;
                
                const colors = {
                    0: { color: '#ef4444', text: 'Very Weak' },
                    1: { color: '#f59e0b', text: 'Weak' },
                    2: { color: '#eab308', text: 'Fair' },
                    3: { color: '#84cc16', text: 'Good' },
                    4: { color: '#22c55e', text: 'Strong' }
                };
                
                strengthBar.style.background = `linear-gradient(to right, ${colors[strength].color}, ${colors[Math.min(strength + 1, 4)].color})`;
                strengthText.textContent = colors[strength].text;
                strengthText.className = `text-xs font-medium ${
                    strength <= 1 ? 'text-red-600' : 
                    strength <= 2 ? 'text-yellow-600' : 
                    'text-green-600'
                }`;
                
                // Add animation
                strengthBar.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
            }
        }

        // Reset form button state (useful for form validation errors)
        function resetFormButton(button) {
            const originalContent = button.getAttribute('data-original-content');
            if (originalContent) {
                button.innerHTML = originalContent;
                button.disabled = false;
            }
        }
    </script>
    
    @yield('scripts')
</body>
</html>