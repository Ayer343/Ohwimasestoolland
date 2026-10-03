<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    
    <!-- ============ FAVICON ============ -->
    @php
        $settings = \App\Models\SystemSetting::getSettings();
    @endphp
    @if($settings->hasFavicon())
        <link rel="icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <!-- Apple Touch Icon for iOS devices -->
        <link rel="apple-touch-icon" href="{{ $settings->getFaviconUrl() }}">
        <!-- Additional favicon sizes for better browser support -->
        <link rel="icon" type="image/png" sizes="16x16" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="64x64" href="{{ $settings->getFaviconUrl() }}">
        <!-- Microsoft Edge Tile -->
        <meta name="msapplication-TileImage" content="{{ $settings->getFaviconUrl() }}">
        <meta name="msapplication-TileColor" content="#2563eb">
    @else
        <!-- Default favicon fallback -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <meta name="msapplication-TileColor" content="#2563eb">
    @endif
    
    <title>@yield('title', config('app.name'))</title>
    <style>
        /* Email CSS - Inline styles for better email client compatibility */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f7fc;
            color: #1a2332;
            line-height: 1.6;
        }
        
        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .email-header {
            text-align: center;
            padding: 20px 0 10px 0;
            border-bottom: 3px solid #2563eb;
            margin-bottom: 20px;
        }
        
        .email-header .logo {
            font-size: 28px;
            font-weight: 700;
            color: #1a2332;
        }
        
        .email-header .logo span {
            color: #2563eb;
        }
        
        .email-header .subtitle {
            color: #6b7280;
            font-size: 14px;
            margin-top: 4px;
        }
        
        .email-body {
            padding: 10px 0;
        }
        
        .email-footer {
            text-align: center;
            padding: 20px 0 10px 0;
            border-top: 1px solid #e5e7eb;
            margin-top: 20px;
            font-size: 12px;
            color: #6b7280;
        }
        
        .email-footer .company-name {
            font-weight: 600;
            color: #374151;
        }
        
        .email-footer .footer-links {
            margin-top: 8px;
        }
        
        .email-footer .footer-links a {
            color: #6b7280;
            text-decoration: none;
            margin: 0 8px;
        }
        
        .email-footer .footer-links a:hover {
            color: #2563eb;
        }
        
        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-wrapper {
                padding: 15px;
                border-radius: 0;
            }
            
            .email-header .logo {
                font-size: 24px;
            }
        }
        
        @media only screen and (max-width: 480px) {
            .email-wrapper {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div style="max-width: 640px; margin: 20px auto; padding: 0 15px;">
        <div class="email-wrapper">
            <!-- Header -->
            <div class="email-header">
                <div class="logo">
                    @if(isset($settings) && $settings->hasLogo())
                        <img src="{{ $settings->getLogoUrl() }}" 
                             alt="{{ $settings->system_name ?? config('app.name', 'Construction') }}" 
                             style="height: 40px; width: auto; max-height: 50px; display: inline-block; vertical-align: middle;">
                    @else
                        <span>🏗️</span>
                    @endif
                    {{ $settings->system_name ?? config('app.name', 'Construction') }}
                </div>
                <div class="subtitle">@yield('subtitle', 'Worker Management System')</div>
            </div>
            
            <!-- Body -->
            <div class="email-body">
                @yield('content')
            </div>
            
            <!-- Footer -->
            <div class="email-footer">
                <div class="company-name">{{ $settings->system_name ?? config('app.name', 'Construction Company') }}</div>
                <p style="margin: 5px 0;">
                    This is an automated message. Please do not reply to this email.
                </p>
                <div class="footer-links">
                    <a href="{{ url('/') }}">Home</a>
                    <span>|</span>
                    <a href="{{ url('/contact') }}">Contact</a>
                    <span>|</span>
                    <a href="{{ url('/privacy') }}">Privacy Policy</a>
                </div>
                <p style="margin: 10px 0 0 0; font-size: 11px; color: #9ca3af;">
                    &copy; {{ date('Y') }} {{ $settings->system_name ?? config('app.name', 'Construction Company') }}. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</body>
</html>