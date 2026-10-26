<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    {{-- ============================================================
         | FAVICON
         * ============================================================ --}}
    @php
        $settings = \App\Models\SystemSetting::getSettings();
        $faviconUrl = $settings->hasFavicon() ? $settings->getFaviconUrl() : asset('favicon.ico');
    @endphp

    <link rel="icon" href="{{ $faviconUrl }}" type="image/x-icon">
    <link rel="shortcut icon" href="{{ $faviconUrl }}" type="image/x-icon">
    <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ $faviconUrl }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $faviconUrl }}">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ $faviconUrl }}">
    <meta name="msapplication-TileImage" content="{{ $faviconUrl }}">
    <meta name="msapplication-TileColor" content="#667eea">

    {{-- ============================================================
         | CRITICAL: Theme + Sidebar initialization
         | Runs before ANY visual rendering to prevent FOUC.
         * ============================================================ --}}
    <script>
        (function () {
            'use strict';
            var html = document.documentElement;

            try {
                var theme = localStorage.getItem('theme');
                var sidebarTheme = localStorage.getItem('sidebar_theme') || 'default';
                var sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

                // First visit → follow OS preference
                if (!theme) {
                    theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }

                html.setAttribute('data-theme', theme);
                html.setAttribute('data-sidebar-theme', sidebarTheme);
                html.classList.add(theme + '-theme');
                html.classList.add('sidebar-loading', 'theme-loading');
                html.style.backgroundColor = (theme === 'dark') ? '#1a1e2c' : '#f3f4f6';

                // Expose to Alpine / other scripts
                window.__INITIAL_THEME          = theme;
                window.__INITIAL_SIDEBAR_THEME  = sidebarTheme;
                window.__INITIAL_SIDEBAR_STATE  = sidebarCollapsed;

                // Pre-paint body background to avoid a flash while CSS loads
                var bg = document.createElement('style');
                bg.id = 'theme-bootstrap';
                bg.textContent = 'body{background-color:' + (theme === 'dark' ? '#1a1e2c' : '#f3f4f6') + ' !important;}';
                document.head.appendChild(bg);
            } catch (e) {
                // Storage disabled / private mode / etc.
                html.setAttribute('data-theme', 'light');
                html.setAttribute('data-sidebar-theme', 'default');
                html.classList.add('light-theme');
                window.__INITIAL_THEME         = 'light';
                window.__INITIAL_SIDEBAR_THEME = 'default';
                window.__INITIAL_SIDEBAR_STATE = false;
            }
        })();
    </script>

    {{-- ============================================================
         | CRITICAL CSS: Theme variables + sidebar collapse + badges
         * ============================================================ --}}
    <style>
        /* ---------- Theme tokens ---------- */
        :root {
            --light-bg:              #f3f4f6;
            --light-surface:         #ffffff;
            --light-text:            #111827;
            --light-text-soft:       #6b7280;
            --light-border:          #e5e7eb;
            --light-card:            #ffffff;
            --light-primary:         #3b82f6;
            --light-primary-rgb:     59, 130, 246;
            --light-success:         #10b981;
            --light-success-rgb:     16, 185, 129;
            --light-danger:          #ef4444;
            --light-danger-rgb:      239, 68, 68;
            --light-warning:         #f59e0b;
            --light-warning-rgb:     245, 158, 11;
            --light-info:            #06b6d4;
            --light-info-rgb:        6, 182, 212;
            --light-secondary:       #8b5cf6;
            --light-secondary-rgb:   139, 92, 246;

            --dark-bg:               #1a1e2c;
            --dark-surface:          #252b3b;
            --dark-text:             #e2e8f0;
            --dark-text-soft:        #a0aec0;
            --dark-border:           #2d3748;
            --dark-card:             #252b3b;
            --dark-primary:          #60a5fa;
            --dark-primary-rgb:      96, 165, 250;
            --dark-success:          #34d399;
            --dark-success-rgb:      52, 211, 153;
            --dark-danger:           #f87171;
            --dark-danger-rgb:       248, 113, 113;
            --dark-warning:          #fbbf24;
            --dark-warning-rgb:      251, 191, 36;
            --dark-info:             #22d3ee;
            --dark-info-rgb:         34, 211, 238;
            --dark-secondary:        #a78bfa;
            --dark-secondary-rgb:    167, 139, 250;

            /* Active theme (light by default) */
            --bg-primary:      var(--light-bg);
            --bg-secondary:    var(--light-surface);
            --text-primary:    var(--light-text);
            --text-secondary:  var(--light-text-soft);
            --border-color:    var(--light-border);
            --card-bg:         var(--light-card);
            --primary:         var(--light-primary);
            --primary-rgb:     var(--light-primary-rgb);
            --success:         var(--light-success);
            --success-rgb:     var(--light-success-rgb);
            --danger:          var(--light-danger);
            --danger-rgb:      var(--light-danger-rgb);
            --warning:         var(--light-warning);
            --warning-rgb:     var(--light-warning-rgb);
            --info:            var(--light-info);
            --info-rgb:        var(--light-info-rgb);
            --secondary:       var(--light-secondary);
            --secondary-rgb:   var(--light-secondary-rgb);

            --sidebar-text:       #ffffff;
            --sidebar-text-hover: #ffffff;
        }

        [data-theme="dark"],
        .dark-theme {
            --bg-primary:      var(--dark-bg);
            --bg-secondary:    var(--dark-surface);
            --text-primary:    var(--dark-text);
            --text-secondary:  var(--dark-text-soft);
            --border-color:    var(--dark-border);
            --card-bg:         var(--dark-card);
            --primary:         var(--dark-primary);
            --primary-rgb:     var(--dark-primary-rgb);
            --success:         var(--dark-success);
            --success-rgb:     var(--dark-success-rgb);
            --danger:          var(--dark-danger);
            --danger-rgb:      var(--dark-danger-rgb);
            --warning:         var(--dark-warning);
            --warning-rgb:     var(--dark-warning-rgb);
            --info:            var(--dark-info);
            --info-rgb:        var(--dark-info-rgb);
            --secondary:       var(--dark-secondary);
            --secondary-rgb:   var(--dark-secondary-rgb);
        }

        /* ---------- Sidebar theme ---------- */
        [data-sidebar-theme="default"] .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); --sidebar-text: #ffffff; }
        [data-sidebar-theme="dark"]    .sidebar { background: #1a202c;                                            --sidebar-text: #e2e8f0; }
        [data-sidebar-theme="light"]   .sidebar { background: #ffffff; border-right: 1px solid #e5e7eb;           --sidebar-text: #1e293b; }
        [data-sidebar-theme="blue"]    .sidebar { background: linear-gradient(135deg, #1e3a5f 0%, #2d6da8 100%); --sidebar-text: #ffffff; }
        [data-sidebar-theme="green"]   .sidebar { background: linear-gradient(135deg, #065f46 0%, #059669 100%); --sidebar-text: #ffffff; }

        /* ---------- Base ---------- */
        html, body {
            background-color: var(--bg-primary) !important;
            color:            var(--text-primary) !important;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        /* Prevent theme flash during the first paint */
        html.theme-loading *,
        html.theme-loading *::before,
        html.theme-loading *::after {
            transition: none !important;
        }

        html:not(.theme-ready) body { opacity: 0; }
        html.theme-ready body       { opacity: 1; transition: opacity 0.1s ease; }

        [x-cloak] { display: none !important; }

        /* ---------- Sidebar collapse ---------- */
        html.sidebar-loading .sidebar,
        html.sidebar-loading .content { transition: none !important; }

        html.sidebar-initialized .sidebar,
        html.sidebar-initialized .content { transition: all 0.3s ease; }

        .sidebar.collapsed {
            width:      70px !important;
            min-width:  70px !important;
        }
        .sidebar.collapsed .nav-text,
        .sidebar.collapsed .logo-fullname,
        .sidebar.collapsed .menu-text,
        .sidebar.collapsed .nav-divider span { display: none !important; }
        .sidebar.collapsed .logo-shortname    { display: block !important; }

        .content.collapsed,
        #main-content.collapsed { margin-left: 70px !important; }

        /* ---------- Sidebar nav coloring ---------- */
        .sidebar .nav-item,
        .sidebar .nav-text,
        .sidebar .logo-fullname,
        .sidebar .logo-shortname,
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
        .sidebar button:hover { color: var(--primary) !important; }
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

        /* ---------- Notification badge ---------- */
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--card-bg);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
            animation: notificationPulse 2s infinite;
            z-index: 10;
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        #notificationBell:hover .notification-badge,
        .notification-bell-container:hover .notification-badge { transform: scale(1.1); }

        @keyframes notificationPulse {
            0%, 100% { transform: scale(1);   opacity: 1;    }
            50%      { transform: scale(1.1); opacity: 0.85; }
        }

        [data-theme="dark"]  .notification-badge { border-color: #1a1e2c; }
        [data-theme="light"] .notification-badge { border-color: #ffffff; }

        .notification-bell-icon {
            font-size: 20px;
            color: var(--text-secondary);
            transition: color 0.2s ease;
        }
        #notificationBell:hover .notification-bell-icon,
        .notification-bell-container:hover .notification-bell-icon { color: var(--primary); }

        #notificationBell:hover .fa-bell,
        .notification-bell-container:hover .fa-bell { animation: bellRing 0.5s ease-in-out; }

        @keyframes bellRing {
            0%   { transform: rotate(0); }
            25%  { transform: rotate(15deg); }
            50%  { transform: rotate(-15deg); }
            75%  { transform: rotate(10deg); }
            100% { transform: rotate(0); }
        }

        @media (prefers-reduced-motion: reduce) {
            .notification-badge,
            .notification-bell-icon,
            #notificationBell:hover .fa-bell { animation: none !important; transition: none !important; }
        }
    </style>

    {{-- ============================================================
         | Critical inline sidebar-collapse pre-boot
         * ============================================================ --}}
    <script>
        (function () {
            'use strict';
            var savedState = localStorage.getItem('sidebarCollapsed') === 'true';

            function applyCollapsedState() {
                var sidebar   = document.querySelector('.sidebar');
                var content   = document.querySelector('.content') || document.getElementById('main-content');

                if (sidebar) {
                    sidebar.classList.toggle('collapsed', savedState);
                }
                if (content) {
                    content.classList.toggle('collapsed', savedState);
                }
            }

            // Apply once DOM is partially available, then release the loading class.
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    applyCollapsedState();
                    requestAnimationFrame(function () {
                        document.documentElement.classList.remove('sidebar-loading', 'theme-loading');
                        document.documentElement.classList.add('sidebar-initialized', 'theme-ready');
                    });
                });
            } else {
                applyCollapsedState();
                requestAnimationFrame(function () {
                    document.documentElement.classList.remove('sidebar-loading', 'theme-loading');
                    document.documentElement.classList.add('sidebar-initialized', 'theme-ready');
                });
            }

            // Safety net: force-ready after 1s even if something never mounts.
            setTimeout(function () {
                document.documentElement.classList.remove('sidebar-loading', 'theme-loading');
                document.documentElement.classList.add('sidebar-initialized', 'theme-ready');
            }, 1000);
        })();
    </script>

    {{-- ============================================================
         | Perf hints
         * ============================================================ --}}
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">

    <title>@yield('title', config('app.name'))</title>

    {{-- ============================================================
         | Stylesheets
         * ============================================================ --}}
    @if(config('app.env') === 'production' && file_exists(public_path('css/admin.min.css')))
        <link rel="stylesheet" href="{{ asset('css/admin.min.css') }}?v={{ filemtime(public_path('css/admin.min.css')) }}">
    @else
        @include('layouts.partials.admin.styles')
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @endif

    @stack('head-scripts')
    @stack('styles')

    {{-- ============================================================
         | Alpine.js — deferred
         * ============================================================ --}}
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
          theme:            window.__INITIAL_THEME || 'light',
          sidebarTheme:     window.__INITIAL_SIDEBAR_THEME || 'default',

          init() {
              this.$watch('sidebarCollapsed', value => {
                  localStorage.setItem('sidebarCollapsed', value);
                  this.$nextTick(() => {
                      const s = document.querySelector('.sidebar');
                      const c = document.querySelector('.content') || document.getElementById('main-content');
                      if (s) { s.classList.toggle('collapsed', value); }
                      if (c) { c.classList.toggle('collapsed', value); }
                      window.dispatchEvent(new CustomEvent('sidebar-toggle', { detail: { collapsed: value } }));
                  });
              });

              this.$watch('theme', value => {
                  localStorage.setItem('theme', value);
                  const html = document.documentElement;
                  html.setAttribute('data-theme', value);
                  html.classList.remove('light-theme', 'dark-theme');
                  html.classList.add(value + '-theme');
                  document.body.style.backgroundColor = value === 'dark' ? '#1a1e2c' : '#f3f4f6';
                  document.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme: value } }));
              });

              this.$watch('sidebarTheme', value => {
                  localStorage.setItem('sidebar_theme', value);
                  document.documentElement.setAttribute('data-sidebar-theme', value);
              });

              // Sync any stale state on first paint
              this.$nextTick(() => {
                  const s = document.querySelector('.sidebar');
                  const c = document.querySelector('.content') || document.getElementById('main-content');
                  if (s) s.classList.toggle('collapsed', this.sidebarCollapsed);
                  if (c) c.classList.toggle('collapsed', this.sidebarCollapsed);
              });
          }
      }"
      :class="{ 'sidebar-collapsed': sidebarCollapsed }">

    {{-- ============================================================
         | Header (includes notification bell)
         * ============================================================ --}}
    <x-devheader>
        <div class="notification-wrapper" x-data="notificationComponent()" x-init="init()">
            @auth
                @include('components.notification-bell')
            @endauth
        </div>
    </x-devheader>

    {{-- ============================================================
         | Main content
         * ============================================================ --}}
    <main id="main-content" role="main">
        @yield('content')
    </main>

    {{-- ============================================================
         | Footer
         * ============================================================ --}}
    <x-superadminfooter />

    {{-- ============================================================
         | Modals / widgets
         * ============================================================ --}}
    @includeWhen(auth()->check() && auth()->user()->can('manage-theme'),
        'layouts.partials.admin.theme-modal')

    @stack('modals')

    {{-- Unified assistant + support chat widget --}}
    @include('chat._unified-widget')

    {{-- ============================================================
         | Scripts
         * ============================================================ --}}
    @if(config('app.env') === 'production' && file_exists(public_path('js/admin.min.js')))
        <script src="{{ asset('js/admin.min.js') }}?v={{ filemtime(public_path('js/admin.min.js')) }}" defer></script>
    @else
        @include('layouts.partials.admin.scripts')
    @endif

    @stack('scripts')

    {{-- Legacy yield — kept for pages still using @section('scripts') --}}
    @yield('scripts')

    @stack('notification-scripts')

    @includeWhen(session()->has('notification') || !empty($notification),
        'components.notification-toast')

    {{-- ============================================================
         | Loading indicator + misc
         * ============================================================ --}}
    <div id="loading-indicator" class="loading-spinner" style="display: none;" aria-hidden="true">
        <div class="spinner"></div>
    </div>

    @if(config('app.env') === 'production' && Route::currentRouteName() === 'dashboard')
        <link rel="prefetch" href="{{ route('profile') }}">
    @endif

    {{-- ============================================================
         | Service worker
         * ============================================================ --}}
    @if(config('app.env') === 'production')
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker.register('/sw.js').catch(function (err) {
                        console.log('ServiceWorker registration failed:', err);
                    });
                });
            }
        </script>
    @endif

    {{-- ============================================================
         | Post-boot theme helpers (page-agnostic)
         * ============================================================ --}}
    <script>
        (function () {
            'use strict';

            window.getCssVariable = function (name) {
                return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            };

            // Theme changed via external trigger (custom toggle button, shortcut, etc.)
            document.addEventListener('theme-toggle-clicked', function () {
                const html = document.documentElement;
                const current = html.getAttribute('data-theme');
                const next    = current === 'dark' ? 'light' : 'dark';

                html.setAttribute('data-theme', next);
                html.classList.remove(current + '-theme');
                html.classList.add(next + '-theme');
                localStorage.setItem('theme', next);
                document.body.style.backgroundColor = next === 'dark' ? '#1a1e2c' : '#f3f4f6';

                document.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme: next } }));
            });

            // Track OS theme if user has never explicitly picked one
            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
                    if (localStorage.getItem('theme')) return;

                    const next = e.matches ? 'dark' : 'light';
                    const html = document.documentElement;

                    html.setAttribute('data-theme', next);
                    html.classList.remove('light-theme', 'dark-theme');
                    html.classList.add(next + '-theme');
                    document.body.style.backgroundColor = next === 'dark' ? '#1a1e2c' : '#f3f4f6';

                    document.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme: next } }));
                });
            }

            // Re-apply persisted sidebar theme on late-loaded content
            const savedSidebarTheme = localStorage.getItem('sidebar_theme');
            if (savedSidebarTheme) {
                document.documentElement.setAttribute('data-sidebar-theme', savedSidebarTheme);
            }

            document.documentElement.classList.add('js-enabled');
        })();
    </script>
</body>
</html>