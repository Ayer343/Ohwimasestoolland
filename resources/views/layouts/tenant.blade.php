<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- ============ FAVICON ============ -->
    @php
        $settings = \App\Models\SystemSetting::getSettings();
    @endphp
    @if($settings->hasFavicon())
        <link rel="icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="apple-touch-icon" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="64x64" href="{{ $settings->getFaviconUrl() }}">
        <meta name="msapplication-TileImage" content="{{ $settings->getFaviconUrl() }}">
        <meta name="msapplication-TileColor" content="#667eea">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <meta name="msapplication-TileColor" content="#667eea">
    @endif
    
    <!-- ============ CRITICAL: Theme Initialization (BEFORE any rendering) ============ -->
    <script>
        (function() {
            try {
                let theme = localStorage.getItem('theme');
                let sidebarTheme = localStorage.getItem('sidebar_theme') || 'default';
                
                if (!theme) {
                    theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                
                const html = document.documentElement;
                html.setAttribute('data-theme', theme);
                html.setAttribute('data-sidebar-theme', sidebarTheme);
                html.classList.add(theme + '-theme');
                
                const colorMap = {
                    'default': '#ffffff',
                    'dark': '#e2e8f0',
                    'light': '#1e293b',
                    'blue': '#ffffff',
                    'green': '#ffffff'
                };
                html.style.setProperty('--sidebar-text', colorMap[sidebarTheme] || '#ffffff');
                
                if (theme === 'dark') {
                    html.style.backgroundColor = '#1a1e2c';
                    document.write('<style>body{background-color:#1a1e2c !important;}</style>');
                } else {
                    html.style.backgroundColor = '#f3f4f6';
                    document.write('<style>body{background-color:#f3f4f6 !important;}</style>');
                }
                
                window.__INITIAL_THEME = theme;
                window.__INITIAL_SIDEBAR_THEME = sidebarTheme;
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
                document.documentElement.setAttribute('data-sidebar-theme', 'default');
                document.documentElement.classList.add('light-theme');
                document.documentElement.style.setProperty('--sidebar-text', '#ffffff');
            }
        })();
    </script>
    
    <!-- ============ CRITICAL: Inline Theme CSS ============ -->
    <style>
        /* CSS Variables with both themes ready */
        :root {
            --light-bg: #f3f4f6;
            --light-surface: #ffffff;
            --light-text: #111827;
            --light-text-soft: #6b7280;
            --light-border: #e5e7eb;
            --light-card: #ffffff;
            --light-primary: #3b82f6;
            --light-primary-rgb: 59, 130, 246;
            --light-success: #10b981;
            --light-success-rgb: 16, 185, 129;
            --light-danger: #ef4444;
            --light-danger-rgb: 239, 68, 68;
            --light-warning: #f59e0b;
            --light-warning-rgb: 245, 158, 11;
            --light-info: #06b6d4;
            --light-info-rgb: 6, 182, 212;
            --light-secondary: #8b5cf6;
            --light-secondary-rgb: 139, 92, 246;
            
            --dark-bg: #1a1e2c;
            --dark-surface: #252b3b;
            --dark-text: #e2e8f0;
            --dark-text-soft: #a0aec0;
            --dark-border: #2d3748;
            --dark-card: #252b3b;
            --dark-primary: #60a5fa;
            --dark-primary-rgb: 96, 165, 250;
            --dark-success: #34d399;
            --dark-success-rgb: 52, 211, 153;
            --dark-danger: #f87171;
            --dark-danger-rgb: 248, 113, 113;
            --dark-warning: #fbbf24;
            --dark-warning-rgb: 251, 191, 36;
            --dark-info: #22d3ee;
            --dark-info-rgb: 34, 211, 238;
            --dark-secondary: #a78bfa;
            --dark-secondary-rgb: 167, 139, 250;
            
            --sidebar-default: #667eea;
            --sidebar-dark: #1a202c;
            --sidebar-light: #ffffff;
            --sidebar-blue: #1e3a5f;
            --sidebar-green: #065f46;
            
            --bg-primary: var(--light-bg);
            --bg-secondary: var(--light-surface);
            --text-primary: var(--light-text);
            --text-secondary: var(--light-text-soft);
            --border-color: var(--light-border);
            --card-bg: var(--light-card);
            --primary: var(--light-primary);
            --primary-rgb: var(--light-primary-rgb);
            --success: var(--light-success);
            --success-rgb: var(--light-success-rgb);
            --danger: var(--light-danger);
            --danger-rgb: var(--light-danger-rgb);
            --warning: var(--light-warning);
            --warning-rgb: var(--light-warning-rgb);
            --info: var(--light-info);
            --info-rgb: var(--light-info-rgb);
            --secondary: var(--light-secondary);
            --secondary-rgb: var(--light-secondary-rgb);
            
            --sidebar-text: #ffffff;
            --sidebar-text-hover: #ffffff;
        }
        
        [data-theme="dark"],
        .dark-theme {
            --bg-primary: var(--dark-bg);
            --bg-secondary: var(--dark-surface);
            --text-primary: var(--dark-text);
            --text-secondary: var(--dark-text-soft);
            --border-color: var(--dark-border);
            --card-bg: var(--dark-card);
            --primary: var(--dark-primary);
            --primary-rgb: var(--dark-primary-rgb);
            --success: var(--dark-success);
            --success-rgb: var(--dark-success-rgb);
            --danger: var(--dark-danger);
            --danger-rgb: var(--dark-danger-rgb);
            --warning: var(--dark-warning);
            --warning-rgb: var(--dark-warning-rgb);
            --info: var(--dark-info);
            --info-rgb: var(--dark-info-rgb);
            --secondary: var(--dark-secondary);
            --secondary-rgb: var(--dark-secondary-rgb);
        }
        
        /* Sidebar theme styles */
        [data-sidebar-theme="default"] .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --sidebar-text: #ffffff;
        }
        
        [data-sidebar-theme="dark"] .sidebar {
            background: #1a202c;
            --sidebar-text: #e2e8f0;
        }
        
        [data-sidebar-theme="light"] .sidebar {
            background: #ffffff;
            --sidebar-text: #1e293b;
            border-right: 1px solid #e5e7eb;
        }
        
        [data-sidebar-theme="blue"] .sidebar {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d6da8 100%);
            --sidebar-text: #ffffff;
        }
        
        [data-sidebar-theme="green"] .sidebar {
            background: linear-gradient(135deg, #065f46 0%, #059669 100%);
            --sidebar-text: #ffffff;
        }
        
        html, body {
            background-color: var(--bg-primary) !important;
            color: var(--text-primary) !important;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            transition: none !important;
        }
        
        .sidebar, .content, .header, .modal, .card, 
        .billing-option-card, .admin-tools-card, .stat-card,
        [class*="modal"], [class*="container"] {
            background-color: inherit;
            color: inherit;
        }
        
        /* Sidebar specific styles */
        .sidebar .nav-item,
        .sidebar .logo-shortname,
        .sidebar .logo-fullname,
        .sidebar .nav-text,
        .sidebar a:not(.active),
        .sidebar button:not(.active) {
            color: var(--sidebar-text, #ffffff) !important;
        }
        
        .sidebar .nav-item i,
        .sidebar button i {
            color: var(--sidebar-text, #ffffff) !important;
            opacity: 0.8;
        }
        
        .sidebar .nav-item:hover,
        .sidebar button:hover {
            color: var(--primary) !important;
        }
        
        .sidebar .nav-item:hover i,
        .sidebar button:hover i {
            color: var(--primary) !important;
            opacity: 1;
        }
        
        .sidebar .nav-item.active,
        .sidebar button.active {
            color: var(--primary) !important;
            background-color: rgba(var(--primary-rgb), 0.15) !important;
        }
        
        .sidebar .nav-item.active i,
        .sidebar button.active i {
            color: var(--primary) !important;
            opacity: 1;
        }
        
        .theme-loading *,
        .theme-loading *::before,
        .theme-loading *::after {
            transition: none !important;
        }
        
        [x-cloak] { display: none !important; }
        
        html:not(.theme-ready) body {
            opacity: 0;
            transition: opacity 0.01s ease;
        }
        
        html.theme-ready body {
            opacity: 1;
        }
        
        /* =====================================================
           NOTIFICATION BADGE STYLES - CONSOLIDATED
           ===================================================== */
        #notificationBell,
        .notification-bell-container {
            position: relative;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            padding: 4px;
        }
        
        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background-color: #ef4444;
            color: white;
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
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
            animation: notificationPulse 2s infinite;
            z-index: 10;
            transform-origin: center;
            transition: transform 0.2s ease, opacity 0.2s ease;
        }
        
        #notificationBell:hover .notification-badge,
        .notification-bell-container:hover .notification-badge {
            transform: scale(1.1);
        }
        
        @keyframes notificationPulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.85; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        [data-theme="dark"] .notification-badge {
            border-color: #1a1e2c;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
        }
        
        [data-theme="light"] .notification-badge {
            border-color: #ffffff;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }
        
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
            0% { transform: rotate(0); }
            25% { transform: rotate(15deg); }
            50% { transform: rotate(-15deg); }
            75% { transform: rotate(10deg); }
            100% { transform: rotate(0); }
        }
        
        /* Disable transitions during load */
        .sidebar-loading .sidebar,
        .sidebar-loading .content {
            transition: none !important;
        }
        .sidebar-loading [x-cloak] {
            display: none !important;
        }
        .sidebar-initialized .sidebar,
        .sidebar-initialized .content {
            transition: all 0.3s ease !important;
        }
        
        /* Sidebar collapsed states */
        .sidebar.collapsed {
            width: 70px !important;
            min-width: 70px !important;
        }
        
        .sidebar.collapsed .nav-text,
        .sidebar.collapsed .logo-fullname,
        .sidebar.collapsed .menu-text,
        .sidebar.collapsed .nav-divider span {
            display: none !important;
        }
        
        .sidebar.collapsed .logo-shortname {
            display: block !important;
        }
        
        .content.collapsed,
        main.collapsed {
            margin-left: 70px !important;
        }
    </style>
    
    <!-- Preconnect for external resources -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- DNS Prefetch for faster resolution -->
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    
    <title>@yield('title', config('app.name'))</title>
    
    <!-- CRITICAL FIX: Initialize sidebar state before any rendering -->
    <script>
        (function() {
            var savedState = localStorage.getItem('sidebarCollapsed') === 'true';
            window.__INITIAL_SIDEBAR_STATE = savedState;
            document.documentElement.classList.add('sidebar-loading', 'theme-loading');
            
            var style = document.createElement('style');
            style.textContent = `
                .sidebar-loading .sidebar,
                .sidebar-loading .content {
                    transition: none !important;
                }
                .sidebar-loading [x-cloak] {
                    display: none !important;
                }
                .sidebar-initialized .sidebar,
                .sidebar-initialized .content {
                    transition: all 0.3s ease !important;
                }
                
                .sidebar.collapsed {
                    width: 70px !important;
                    min-width: 70px !important;
                }
                
                .sidebar.collapsed .nav-text,
                .sidebar.collapsed .logo-fullname,
                .sidebar.collapsed .menu-text,
                .sidebar.collapsed .nav-divider span {
                    display: none !important;
                }
                
                .sidebar.collapsed .logo-shortname {
                    display: block !important;
                }
                
                .content.collapsed,
                main.collapsed {
                    margin-left: 70px !important;
                }
            `;
            document.head.appendChild(style);
            
            var observer = new MutationObserver(function(mutations) {
                var sidebar = document.querySelector('.sidebar');
                var mainContent = document.querySelector('.content');
                var mainContentAlt = document.getElementById('main-content');
                
                if (sidebar) {
                    if (savedState) {
                        sidebar.classList.add('collapsed');
                    } else {
                        sidebar.classList.remove('collapsed');
                    }
                }
                
                if (mainContent) {
                    if (savedState) {
                        mainContent.classList.add('collapsed');
                    } else {
                        mainContent.classList.remove('collapsed');
                    }
                }
                
                if (mainContentAlt && !mainContent) {
                    if (savedState) {
                        mainContentAlt.classList.add('collapsed');
                    } else {
                        mainContentAlt.classList.remove('collapsed');
                    }
                }
                
                if (sidebar && (mainContent || mainContentAlt)) {
                    observer.disconnect();
                    
                    setTimeout(function() {
                        document.documentElement.classList.remove('sidebar-loading', 'theme-loading');
                        document.documentElement.classList.add('sidebar-initialized', 'theme-ready');
                        document.body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
                    }, 50);
                }
            });
            
            observer.observe(document.documentElement, {
                childList: true,
                subtree: true
            });
        })();
    </script>
    
    <!-- Critical inline styles for above-the-fold content -->
    @if(config('app.env') === 'production')
        <style>
            .sidebar-preload { margin: 0; padding: 0; }
            .notification-wrapper { display: inline-block; position: relative; }
            [x-cloak] { display: none !important; }
            
            .notification-bell .bell-icon {
                animation: none !important;
                box-shadow: none !important;
            }
            
            .notification-bell.has-notifications .bell-icon {
                animation: none !important;
                box-shadow: none !important;
            }
            
            @keyframes bell-ring {
                0%, 100% { transform: none; }
            }
            
            @keyframes notification-glow {
                0%, 100% { box-shadow: none; }
            }
        </style>
    @endif
    
    <!-- Styles - Combined in production -->
    @if(config('app.env') === 'production' && file_exists(public_path('css/admin.min.css')))
        <link rel="stylesheet" href="{{ asset('css/admin.min.css') }}?v={{ filemtime(public_path('css/admin.min.css')) }}">
    @else
        @include('layouts.partials.admin.styles')
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @endif
    
    @stack('styles')
    
    <!-- Conditional Alpine.js loading with request coalescing -->
    @stack('alpine-needed')
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.0/dist/cdn.min.js" defer
            @if(config('app.env') === 'production')
            integrity="sha384-5UF2kGgXqYlUHnN/qWY1wL8rP3yy0M/azUw1jD4wJ3R5KpP3CjBw5CqG1q5q0WxL"
            crossorigin="anonymous"
            @endif
    ></script>
</head>
<body class="sidebar-preload" 
      x-data="{ 
          sidebarCollapsed: window.__INITIAL_SIDEBAR_STATE || false,
          theme: window.__INITIAL_THEME || 'light',
          sidebarTheme: window.__INITIAL_SIDEBAR_THEME || 'default',
          init() {
              this.$watch('sidebarCollapsed', value => {
                  localStorage.setItem('sidebarCollapsed', value);
                  
                  this.$nextTick(() => {
                      const sidebar = document.querySelector('.sidebar');
                      const mainContent = document.querySelector('.content');
                      const mainContentAlt = document.getElementById('main-content');
                      
                      if (sidebar) {
                          sidebar.classList.toggle('collapsed', value);
                          window.dispatchEvent(new CustomEvent('sidebar-toggle', { 
                              detail: { collapsed: value } 
                          }));
                      }
                      
                      if (mainContent) {
                          mainContent.classList.toggle('collapsed', value);
                      }
                      
                      if (mainContentAlt && !mainContent) {
                          mainContentAlt.classList.toggle('collapsed', value);
                      }
                  });
              });
              
              this.$watch('theme', value => {
                  localStorage.setItem('theme', value);
                  document.documentElement.setAttribute('data-theme', value);
                  document.documentElement.classList.remove('light-theme', 'dark-theme');
                  document.documentElement.classList.add(value + '-theme');
                  
                  if (value === 'dark') {
                      document.body.style.backgroundColor = '#1a1e2c';
                  } else {
                      document.body.style.backgroundColor = '#f3f4f6';
                  }
                  
                  document.dispatchEvent(new CustomEvent('theme-changed', {
                      detail: { theme: value }
                  }));
              });
              
              this.$watch('sidebarTheme', value => {
                  localStorage.setItem('sidebar_theme', value);
                  document.documentElement.setAttribute('data-sidebar-theme', value);
                  
                  const colorMap = {
                      'default': '#ffffff',
                      'dark': '#e2e8f0',
                      'light': '#1e293b',
                      'blue': '#ffffff',
                      'green': '#ffffff'
                  };
                  document.documentElement.style.setProperty('--sidebar-text', colorMap[value] || '#ffffff');
                  
                  document.dispatchEvent(new CustomEvent('sidebar-theme-changed', {
                      detail: { sidebarTheme: value }
                  }));
              });
              
              this.$nextTick(() => {
                  const sidebar = document.querySelector('.sidebar');
                  const mainContent = document.querySelector('.content');
                  const mainContentAlt = document.getElementById('main-content');
                  
                  if (sidebar) {
                      sidebar.classList.toggle('collapsed', this.sidebarCollapsed);
                  }
                  
                  if (mainContent) {
                      mainContent.classList.toggle('collapsed', this.sidebarCollapsed);
                  }
                  
                  if (mainContentAlt && !mainContent) {
                      mainContentAlt.classList.toggle('collapsed', this.sidebarCollapsed);
                  }
              });
          }
      }" 
      :class="{ 'sidebar-collapsed': sidebarCollapsed }">
      
    <!-- Header with notification bell -->
    <x-tenantheader>
        <div class="notification-wrapper" x-data="notificationComponent()" x-init="init()">
            @auth
                @include('components.notification-bell')
            @endauth
        </div>
    </x-tenantheader>
    
    <!-- Main content -->
    <main id="main-content" role="main">
        @yield('content')
    </main>
    
    <!-- Footer -->
    <x-superadminfooter />
    
    <!-- Theme Settings Modal - Lazy loaded -->
    @includeWhen(auth()->check() && auth()->user()->can('manage-theme'), 'layouts.partials.admin.theme-modal')
    
    <!-- Scripts - Combined in production -->
    @if(config('app.env') === 'production' && file_exists(public_path('js/admin.min.js')))
        <script src="{{ asset('js/admin.min.js') }}?v={{ filemtime(public_path('js/admin.min.js')) }}" defer></script>
    @else
        @include('layouts.partials.admin.scripts')
    @endif
    
    <!-- Page-specific scripts stacked -->
    @stack('scripts')
    
    <!-- Yield for legacy compatibility -->
    @yield('scripts')
    
    <!-- Notification scripts stack -->
    @stack('notification-scripts')
    
    <!-- Lazy-loaded notification toast template -->
    @includeWhen(session()->has('notification') || !empty($notification), 'components.notification-toast')
    
    <!-- Loading indicator for AJAX requests (optional) -->
    <div id="loading-indicator" class="loading-spinner" style="display: none;" aria-hidden="true">
        <div class="spinner"></div>
    </div>
    
    <!-- Preload next likely page (optional, based on analytics) -->
    @if(config('app.env') === 'production' && Route::currentRouteName() === 'dashboard')
        <link rel="prefetch" href="{{ route('profile') }}">
    @endif
    
    <!-- Service Worker registration for offline capability (optional) -->
    @if(config('app.env') === 'production')
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function() {
                    navigator.serviceWorker.register('/sw.js').catch(function(error) {
                        console.log('ServiceWorker registration failed: ', error);
                    });
                });
            }
        </script>
    @endif
    
    <!-- ========================================================================= -->
    <!-- COMPREHENSIVE THEME AND SIDEBAR MANAGEMENT SCRIPT                        -->
    <!-- ========================================================================= -->
    <script>
        (function() {
            // =========================================================================
            // THEME MANAGER - Centralized theme management system
            // =========================================================================
            
            const ThemeManager = {
                init() {
                    // Read preferences
                    this.appearance = localStorage.getItem('appearance') || 'system';
                    this.sidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
                    
                    // Apply themes immediately (already applied in head, but ensure consistency)
                    this.applyAppearance(this.appearance, false);
                    this.applySidebarTheme(this.sidebarTheme, false);
                    
                    // Setup listeners
                    this.setupSystemThemeListener();
                },
                
                applyAppearance(appearance, save = true) {
                    let theme;
                    
                    if (appearance === 'system') {
                        theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                    } else {
                        theme = appearance;
                    }
                    
                    // Apply to document element
                    document.documentElement.setAttribute('data-theme', theme);
                    
                    if (save) {
                        localStorage.setItem('appearance', appearance);
                        this.appearance = appearance;
                    }
                },
                
                applySidebarTheme(theme, save = true) {
                    document.documentElement.setAttribute('data-sidebar-theme', theme);
                    
                    if (save) {
                        localStorage.setItem('sidebarTheme', theme);
                        this.sidebarTheme = theme;
                    }
                },
                
                setupSystemThemeListener() {
                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                        if (this.appearance === 'system') {
                            const newTheme = e.matches ? 'dark' : 'light';
                            document.documentElement.setAttribute('data-theme', newTheme);
                        }
                    });
                }
            };
            
            // Initialize theme manager
            ThemeManager.init();
            window.ThemeManager = ThemeManager;
            
            // =========================================================================
            // SIDEBAR COLLAPSE STATE MANAGEMENT
            // =========================================================================
            
            // Function to toggle sidebar with proper class application
            function toggleSidebar(collapsed, saveToStorage = true) {
                const sidebar = document.querySelector('.sidebar');
                const mainContent = document.querySelector('.content');
                const mainContentAlt = document.getElementById('main-content');
                
                // Apply to sidebar
                if (sidebar) {
                    if (collapsed) {
                        sidebar.classList.add('collapsed');
                    } else {
                        sidebar.classList.remove('collapsed');
                    }
                }
                
                // Apply to main content/element
                if (mainContent) {
                    if (collapsed) {
                        mainContent.classList.add('collapsed');
                    } else {
                        mainContent.classList.remove('collapsed');
                    }
                }
                
                if (mainContentAlt && !mainContent) {
                    if (collapsed) {
                        mainContentAlt.classList.add('collapsed');
                    } else {
                        mainContentAlt.classList.remove('collapsed');
                    }
                }
                
                // Save to localStorage if requested
                if (saveToStorage) {
                    localStorage.setItem('sidebarCollapsed', collapsed);
                }
                
                // Update Alpine.js state if it exists
                if (window.Alpine) {
                    const alpineElements = document.querySelectorAll('[x-data]');
                    alpineElements.forEach(el => {
                        if (el.__x && el.__x.$data.sidebarCollapsed !== undefined) {
                            el.__x.$data.sidebarCollapsed = collapsed;
                        }
                    });
                }
                
                return collapsed;
            }
            
            // Make toggleSidebar available globally
            window.toggleSidebar = toggleSidebar;
            
            // =========================================================================
            // THEME MODAL HANDLERS - Enhanced with ThemeManager
            // =========================================================================
            
            document.addEventListener('DOMContentLoaded', function() {
                // Theme settings modal elements
                const themeSettingsButton = document.getElementById('themeSettingsButton');
                const themeSettingsModal = document.getElementById('themeSettingsModal');
                const closeThemeModal = document.getElementById('closeThemeModal');
                const closeThemeModalBtn = document.getElementById('closeThemeModalBtn');
                
                // Theme selection variables
                let selectedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
                let selectedAppearance = localStorage.getItem('appearance') || 'system';
                
                // Initialize theme settings display
                function initializeThemeSettings() {
                    const savedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
                    const savedAppearance = localStorage.getItem('appearance') || 'system';
                    
                    // Set initial selections in modal if modal exists
                    const themeOptions = document.querySelectorAll('#themeSettingsModal .theme-option-compact');
                    const appearanceOptions = document.querySelectorAll('#themeSettingsModal .appearance-option-compact');
                    
                    if (themeOptions.length) {
                        setThemeSelection(savedSidebarTheme);
                    }
                    
                    if (appearanceOptions.length) {
                        setAppearanceSelection(savedAppearance);
                    }
                }
                
                // Enhanced theme modal functionality
                if (themeSettingsButton && themeSettingsModal) {
                    themeSettingsButton.addEventListener('click', function() {
                        themeSettingsModal.classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                        
                        // Update current selections
                        selectedAppearance = getCurrentAppearance();
                        selectedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
                        
                        // Refresh theme options
                        const themeOptions = document.querySelectorAll('#themeSettingsModal .theme-option-compact');
                        const appearanceOptions = document.querySelectorAll('#themeSettingsModal .appearance-option-compact');
                        
                        setThemeSelection(selectedSidebarTheme);
                        setAppearanceSelection(selectedAppearance);
                        
                        // Add opening animation
                        const modalContent = themeSettingsModal.querySelector('.theme-modal-compact');
                        if (modalContent) {
                            modalContent.style.animation = 'modalFadeIn 0.3s ease-out';
                        }
                    });
                }
                
                // Close theme modal
                const closeThemeModalFunc = function() {
                    if (themeSettingsModal) {
                        themeSettingsModal.classList.add('hidden');
                        document.body.style.overflow = 'auto';
                    }
                };
                
                if (closeThemeModal) closeThemeModal.addEventListener('click', closeThemeModalFunc);
                if (closeThemeModalBtn) closeThemeModalBtn.addEventListener('click', closeThemeModalFunc);
                
                // Close theme modal when clicking outside
                if (themeSettingsModal) {
                    themeSettingsModal.addEventListener('click', function(e) {
                        if (e.target === themeSettingsModal) {
                            closeThemeModalFunc();
                        }
                    });
                }
                
                // Enhanced theme option selection
                const themeOptions = document.querySelectorAll('#themeSettingsModal .theme-option-compact');
                themeOptions.forEach(option => {
                    option.addEventListener('click', function() {
                        const theme = this.getAttribute('data-theme');
                        setThemeSelection(theme);
                        selectedSidebarTheme = theme;
                        
                        // Apply sidebar theme using ThemeManager
                        window.ThemeManager.applySidebarTheme(theme, true);
                        
                        // Apply with smooth transition
                        document.body.style.transition = 'all 0.5s ease';
                        
                        setTimeout(() => {
                            document.body.style.transition = '';
                        }, 500);
                    });
                });
                
                // Enhanced appearance option selection
                const appearanceOptions = document.querySelectorAll('#themeSettingsModal .appearance-option-compact');
                appearanceOptions.forEach(option => {
                    option.addEventListener('click', function() {
                        const appearance = this.getAttribute('data-theme');
                        setAppearanceSelection(appearance);
                        
                        // Apply appearance using ThemeManager
                        window.ThemeManager.applyAppearance(appearance, true);
                        
                        // Apply with smooth transition
                        document.body.style.transition = 'all 0.5s ease';
                        
                        setTimeout(() => {
                            document.body.style.transition = '';
                        }, 500);
                    });
                });
                
                // Helper functions
                function getCurrentAppearance() {
                    const currentTheme = document.documentElement.getAttribute('data-theme');
                    if (currentTheme === 'light' || currentTheme === 'dark') {
                        return currentTheme;
                    }
                    const savedAppearance = localStorage.getItem('appearance');
                    if (savedAppearance === 'light' || savedAppearance === 'dark') {
                        return savedAppearance;
                    }
                    return 'system';
                }
                
                function setThemeSelection(theme) {
                    const themeOptions = document.querySelectorAll('#themeSettingsModal .theme-option-compact');
                    themeOptions.forEach(opt => {
                        const existingIndicator = opt.querySelector('.current-selection-compact');
                        if (existingIndicator) existingIndicator.remove();
                        
                        if (opt.getAttribute('data-theme') === theme) {
                            opt.classList.add('active');
                            const indicator = document.createElement('div');
                            indicator.className = 'current-selection-compact';
                            indicator.innerHTML = '<i class="fas fa-check"></i>';
                            opt.appendChild(indicator);
                        } else {
                            opt.classList.remove('active');
                        }
                    });
                }
                
                function setAppearanceSelection(appearance) {
                    const appearanceOptions = document.querySelectorAll('#themeSettingsModal .appearance-option-compact');
                    appearanceOptions.forEach(opt => {
                        const existingIndicator = opt.querySelector('.current-selection-compact');
                        if (existingIndicator) existingIndicator.remove();
                        
                        if (opt.getAttribute('data-theme') === appearance) {
                            opt.classList.add('active');
                            const indicator = document.createElement('div');
                            indicator.className = 'current-selection-compact';
                            indicator.innerHTML = '<i class="fas fa-check"></i>';
                            opt.appendChild(indicator);
                        } else {
                            opt.classList.remove('active');
                        }
                    });
                }
                
                // Close modals with Escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && themeSettingsModal && !themeSettingsModal.classList.contains('hidden')) {
                        closeThemeModalFunc();
                    }
                });
                
                // Initialize theme settings
                initializeThemeSettings();
                
                // Sidebar collapse toggle functionality
                const toggleSidebarBtn = document.getElementById('toggleSidebar');
                if (toggleSidebarBtn) {
                    toggleSidebarBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const isCollapsed = localStorage.getItem('sidebarCollapsed') !== 'true';
                        toggleSidebar(isCollapsed, true);
                    });
                }
                
                // Fix for any third-party scripts that might interfere
                const fixInterference = function() {
                    // Ensure sidebar state remains correct
                    const currentState = localStorage.getItem('sidebarCollapsed') === 'true';
                    const sidebar = document.querySelector('.sidebar');
                    
                    if (sidebar) {
                        const hasCollapsedClass = sidebar.classList.contains('collapsed');
                        if (hasCollapsedClass !== currentState) {
                            console.log('Sidebar state interference detected, correcting...');
                            toggleSidebar(currentState, false);
                        }
                    }
                    
                    // Ensure theme is correct
                    const savedAppearance = localStorage.getItem('appearance') || 'system';
                    const currentTheme = document.documentElement.getAttribute('data-theme');
                    let expectedTheme;
                    
                    if (savedAppearance === 'system') {
                        expectedTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                    } else {
                        expectedTheme = savedAppearance;
                    }
                    
                    if (currentTheme !== expectedTheme) {
                        console.log('Theme interference detected, correcting...');
                        window.ThemeManager.applyAppearance(savedAppearance, false);
                    }
                };
                
                // Run interference fix after a short delay
                setTimeout(fixInterference, 1000);
                
                // Monitor for AJAX requests that might affect state
                const originalFetch = window.fetch;
                if (originalFetch) {
                    window.fetch = function() {
                        return originalFetch.apply(this, arguments)
                            .then(response => {
                                setTimeout(fixInterference, 100);
                                return response;
                            })
                            .catch(error => {
                                throw error;
                            });
                    };
                }
                
                // Handle jQuery AJAX if present
                if (window.jQuery) {
                    $(document).ajaxComplete(function() {
                        setTimeout(fixInterference, 100);
                    });
                }
            });
            
            // =========================================================================
            // WINDOW LOAD EVENT - Final checks and cleanup
            // =========================================================================
            
            window.addEventListener('load', function() {
                setTimeout(function() {
                    const savedState = localStorage.getItem('sidebarCollapsed') === 'true';
                    const sidebar = document.querySelector('.sidebar');
                    const mainContent = document.querySelector('.content');
                    const mainContentAlt = document.getElementById('main-content');
                    
                    // Verify sidebar class matches saved state
                    if (sidebar) {
                        const hasCollapsedClass = sidebar.classList.contains('collapsed');
                        if (hasCollapsedClass !== savedState) {
                            console.log('Final sidebar state correction on window load');
                            if (savedState) {
                                sidebar.classList.add('collapsed');
                            } else {
                                sidebar.classList.remove('collapsed');
                            }
                        }
                    }
                    
                    // Verify main content class matches saved state
                    const contentElement = mainContent || mainContentAlt;
                    if (contentElement) {
                        const hasCollapsedClass = contentElement.classList.contains('collapsed');
                        if (hasCollapsedClass !== savedState) {
                            if (savedState) {
                                contentElement.classList.add('collapsed');
                            } else {
                                contentElement.classList.remove('collapsed');
                            }
                        }
                    }
                    
                    // Update Alpine state if needed
                    if (window.Alpine && window.__INITIAL_SIDEBAR_STATE !== undefined) {
                        const alpineElements = document.querySelectorAll('[x-data]');
                        alpineElements.forEach(el => {
                            if (el.__x && el.__x.$data.sidebarCollapsed !== undefined) {
                                if (el.__x.$data.sidebarCollapsed !== savedState) {
                                    el.__x.$data.sidebarCollapsed = savedState;
                                }
                            }
                        });
                    }
                    
                    // Remove loading classes
                    document.documentElement.classList.remove('sidebar-loading');
                    document.documentElement.classList.remove('theme-loading');
                    document.documentElement.classList.add('sidebar-initialized');
                    document.documentElement.classList.add('theme-ready');
                    
                    // Ensure body overflow is reset
                    document.body.style.overflow = 'auto';
                }, 10);
            });
        })();
    </script>
</body>
</html>