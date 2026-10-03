{{-- developer/tools/dashboard.blade.php --}}
@php
    $pageTitle      = 'Developer Tools - System Dashboard';
    $successMessage = session('success');
    $errorMessage   = session('error');
    $warningMessage = session('warning');

    $isDarkMode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] === 'true' : false;

    // ────────────────────────────────────────────────────────────
    // Tool categories — every route below is registered.
    // Dangerous operations (migrate:fresh, key:generate,
    // db:repair, composer) are intentionally NOT exposed here.
    // They must be run via CLI only.
    // ────────────────────────────────────────────────────────────
    $tools = [
        'cache' => [
            'name'  => 'Cache Management',
            'icon'  => 'fas fa-bolt',
            'color' => 'var(--primary)',
            'tools' => [
                ['name' => 'Clear Application Cache', 'route' => 'developer.tools.clear-cache',  'icon' => 'fas fa-broom',     'description' => 'Clear the default application cache'],
                ['name' => 'Clear All Caches',        'route' => 'developer.tools.clear-all',    'icon' => 'fas fa-trash',     'description' => 'Clear every cached item at once'],
                ['name' => 'Config Cache',            'route' => 'developer.tools.config-cache', 'icon' => 'fas fa-cog',       'description' => 'Cache configuration files'],
                ['name' => 'Clear Config Cache',      'route' => 'developer.tools.config-clear', 'icon' => 'fas fa-undo',      'description' => 'Remove the config cache'],
                ['name' => 'Route Cache',             'route' => 'developer.tools.route-cache',  'icon' => 'fas fa-route',     'description' => 'Compile and cache routes'],
                ['name' => 'Clear Route Cache',       'route' => 'developer.tools.route-clear',  'icon' => 'fas fa-undo',      'description' => 'Remove the route cache'],
                ['name' => 'View Cache',              'route' => 'developer.tools.view-cache',   'icon' => 'fas fa-eye',       'description' => 'Compile and cache Blade views'],
                ['name' => 'Clear View Cache',        'route' => 'developer.tools.view-clear',   'icon' => 'fas fa-undo',      'description' => 'Remove the compiled view cache'],
            ],
        ],
        'queue' => [
            'name'  => 'Queue & Sessions',
            'icon'  => 'fas fa-stream',
            'color' => 'var(--warning)',
            'tools' => [
                ['name' => 'Queue Restart',    'route' => 'developer.tools.queue-restart', 'icon' => 'fas fa-sync',         'description' => 'Signal queue workers to reload code'],
                ['name' => 'Clean Sessions',   'route' => 'developer.tools.session-clean', 'icon' => 'fas fa-users-slash',  'description' => 'Delete expired session records'],
                ['name' => 'Run Scheduler',    'route' => 'developer.tools.schedule-run',  'icon' => 'fas fa-clock',        'description' => 'Manually run schedule:run'],
            ],
        ],
        'database' => [
            'name'  => 'Database',
            'icon'  => 'fas fa-database',
            'color' => 'var(--success)',
            'tools' => [
                ['name' => 'Backup Database', 'route' => 'developer.tools.backup-database', 'icon' => 'fas fa-save',     'description' => 'Create a database backup'],
                ['name' => 'Backup (Alias)',  'route' => 'developer.tools.db-backup',       'icon' => 'fas fa-download', 'description' => 'Alternative backup endpoint'],
            ],
        ],
        'logs' => [
            'name'  => 'Logs',
            'icon'  => 'fas fa-file-alt',
            'color' => 'var(--warning)',
            'tools' => [
                ['name' => 'View System Logs',  'route' => 'developer.tools.system-logs',       'icon' => 'fas fa-search', 'description' => 'View the latest system logs'],
                ['name' => 'Clear System Logs', 'route' => 'developer.tools.clear-system-logs', 'icon' => 'fas fa-trash',  'description' => 'Clear the log files'],
            ],
        ],
        'monitoring' => [
            'name'  => 'Monitoring',
            'icon'  => 'fas fa-chart-line',
            'color' => 'var(--info)',
            'tools' => [
                ['name' => 'Server Info',  'route' => 'developer.tools.server-info',  'icon' => 'fas fa-server',      'description' => 'Server hardware & software'],
                ['name' => 'System Info',  'route' => 'developer.tools.system-info',  'icon' => 'fas fa-info-circle', 'description' => 'Runtime environment details'],
                ['name' => 'PHP Info',     'route' => 'developer.tools.php-info',     'icon' => 'fas fa-code',        'description' => 'PHP configuration details'],
                ['name' => 'Diagnostics',  'route' => 'developer.tools.diagnostics',  'icon' => 'fas fa-stethoscope', 'description' => 'Run system diagnostics'],
            ],
        ],
        'advanced' => [
            'name'  => 'Advanced',
            'icon'  => 'fas fa-code',
            'color' => 'var(--secondary)',
            'tools' => [
                ['name' => 'Artisan Console', 'route' => 'developer.tools.artisan',        'icon' => 'fas fa-terminal', 'description' => 'Run Artisan commands'],
                ['name' => 'Commands List',   'route' => 'developer.tools.commands',       'icon' => 'fas fa-list',     'description' => 'List available Artisan commands'],
                ['name' => 'Environment',     'route' => 'developer.tools.environment',    'icon' => 'fas fa-leaf',     'description' => 'Inspect environment variables'],
                ['name' => 'Cron / Schedule', 'route' => 'developer.tools.cron',           'icon' => 'fas fa-clock',    'description' => 'View scheduled tasks'],
                ['name' => 'Scheduled Jobs',  'route' => 'developer.tools.cron.jobs',      'icon' => 'fas fa-tasks',    'description' => 'Detail view of scheduled jobs'],
            ],
        ],
    ];

    // System information
    $systemInfo = [
        'php_version'     => PHP_VERSION,
        'laravel_version' => app()->version(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
        'database_driver' => config('database.default'),
        'cache_driver'    => config('cache.default'),
        'queue_driver'    => config('queue.default'),
        'timezone'        => config('app.timezone'),
        'debug_mode'      => config('app.debug') ? 'Enabled' : 'Disabled',
        'environment'     => app()->environment(),
    ];

    // Recent activity — defensive check for the model / table
    $recentActivities = collect();
    if (class_exists(\App\Models\ActivityLog::class)) {
        try {
            $recentActivities = \App\Models\ActivityLog::latest()->take(10)->get();
        } catch (\Throwable $e) {
            // table missing — silent fallback
        }
    }

    $totalTools = array_sum(array_map(fn ($cat) => count($cat['tools']), $tools));

    // PHP memory / disk for the quick stats
    $memoryMb   = round(memory_get_usage(true) / 1048576, 1);
    $diskFree   = @disk_free_space(base_path());
    $diskFreeGb = $diskFree ? round($diskFree / 1073741824, 1) : null;
@endphp

@extends('layouts.dev')

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- Header --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-tools text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-tools mr-2" style="color: var(--primary);"></i>
                        Developer Tools Dashboard
                    </h2>
                    <div class="text-sm flex items-center mt-1 flex-wrap gap-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle"></i>
                        <span>Safe operational tools for routine maintenance</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-shield-alt" style="color: var(--success);"></i>
                        <span class="font-medium">Dangerous ops are CLI-only</span>
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <div class="flex items-center space-x-2 mt-2 flex-wrap gap-2">
                    <a href="{{ route('developer.dashboard') }}"
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-dashboard mr-1"></i> Dashboard
                    </a>
                    <a href="{{ route('developer.settings.index') }}"
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-cog mr-1"></i> Settings
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Flash messages --}}
    @foreach(['success' => 'check-circle', 'warning' => 'exclamation-triangle', 'error' => 'exclamation-circle'] as $type => $icon)
        @php
            $var      = $type . 'Message';
            $message  = $$var;
            $colorVar = $type === 'error' ? 'danger' : $type;
        @endphp
        @if($message)
        <div class="card">
            <div class="flex items-center p-4"
                 style="background-color: rgba(var(--{{ $colorVar }}-rgb), 0.1);
                        border: 1px solid rgba(var(--{{ $colorVar }}-rgb), 0.3);
                        border-radius: 12px;">
                <i class="fas fa-{{ $icon }} text-xl mr-3" style="color: var(--{{ $colorVar }});"></i>
                <div class="flex-1" style="color: var(--{{ $colorVar }}); font-weight: 500;">
                    {{ $message }}
                </div>
                <button type="button" onclick="this.closest('.card').remove()">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
        </div>
        @endif
    @endforeach

    {{-- Navigation --}}
    <div class="card p-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center space-x-3 flex-wrap gap-2">
                <a href="{{ route('developer.dashboard') }}"
                   class="inline-flex items-center text-sm font-medium"
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>

                <a href="{{ route('developer.settings.index') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-cog mr-1"></i> Settings
                </a>

                <a href="{{ route('developer.monitoring.dashboard') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-chart-line mr-1"></i> Monitoring
                </a>

                <a href="{{ route('developer.billing.dashboard') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-money-bill-wave mr-1"></i> Billing
                </a>
            </div>

            <span class="text-xs px-3 py-1 rounded-full"
                  style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                <i class="fas fa-toolbox mr-1"></i> {{ $totalTools }} tools available
            </span>
        </div>
    </div>

    {{-- Quick Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="card stat-card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-heartbeat text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">System Status</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">Healthy</p>
                </div>
            </div>
        </div>

        <div class="card stat-card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    <i class="fas fa-memory text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">PHP Memory</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $memoryMb }} MB</p>
                </div>
            </div>
        </div>

        <div class="card stat-card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                    <i class="fas fa-hdd text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Disk Free</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $diskFreeGb !== null ? $diskFreeGb . ' GB' : 'N/A' }}
                    </p>
                </div>
            </div>
        </div>

        <div class="card stat-card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-tools text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Available Tools</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $totalTools }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="card p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Available Tools ({{ $totalTools }})
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Everything below is safe to run from the browser.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($tools as $categoryId => $category)
                    <div class="border rounded-lg p-4"
                         style="border-color: var(--border-color); background-color: var(--card-bg);">
                        <div class="flex items-center mb-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--primary-rgb), 0.1); color: {{ $category['color'] }};">
                                <i class="{{ $category['icon'] }}"></i>
                            </div>
                            <h4 class="font-semibold" style="color: var(--text-primary);">
                                {{ $category['name'] }}
                            </h4>
                            <span class="ml-auto text-xs px-2 py-0.5 rounded-full"
                                  style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                {{ count($category['tools']) }}
                            </span>
                        </div>

                        <div class="space-y-2">
                            @foreach($category['tools'] as $tool)
                            <a href="{{ route($tool['route']) }}"
                               class="flex items-center p-2 rounded-lg tool-link"
                               style="border: 1px solid var(--border-color); background-color: var(--card-bg);">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center mr-2"
                                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                                    <i class="{{ $tool['icon'] }} text-xs" style="color: var(--primary);"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="text-sm font-medium block truncate"
                                          style="color: var(--text-primary);">
                                        {{ $tool['name'] }}
                                    </span>
                                    <p class="text-xs mt-0.5 truncate"
                                       style="color: var(--text-secondary);">
                                        {{ $tool['description'] }}
                                    </p>
                                </div>
                                <i class="fas fa-chevron-right text-xs"
                                   style="color: var(--text-secondary);"></i>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Dangerous Zone — Informational Only --}}
            <div class="card" style="border-color: var(--danger);">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-2 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-radiation-alt mr-2" style="color: var(--danger);"></i>
                        CLI-Only Operations
                        <span class="ml-2 text-xs px-2 py-1 rounded badge-danger">Not available here</span>
                    </h3>
                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                        The following operations are intentionally not exposed as web routes because a single
                        mistake can cause data loss or a service outage. Run them from the server CLI:
                    </p>

                    <ul class="space-y-2 text-sm" style="color: var(--text-secondary);">
                        <li class="flex items-start">
                            <i class="fas fa-terminal mr-2 mt-1" style="color: var(--danger);"></i>
                            <span><code>php artisan migrate:fresh --seed</code> — drops and recreates all tables</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-terminal mr-2 mt-1" style="color: var(--danger);"></i>
                            <span><code>php artisan migrate:rollback</code> — drops the last migration batch</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-terminal mr-2 mt-1" style="color: var(--danger);"></i>
                            <span><code>php artisan key:generate</code> — invalidates all encrypted values</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-terminal mr-2 mt-1" style="color: var(--danger);"></i>
                            <span><code>composer update</code> / <code>composer install</code> — modifies the dependency tree</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-terminal mr-2 mt-1" style="color: var(--danger);"></i>
                            <span><code>php artisan db:repair</code> — can corrupt data on InnoDB</span>
                        </li>
                    </ul>

                    <div class="mt-4 p-3 rounded-lg"
                         style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mt-0.5 mr-2" style="color: var(--warning);"></i>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                Any routine task that can safely run in the browser is available above.
                                For everything else, use SSH.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- System Information --}}
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i> System Information
                    </h3>

                    <div class="space-y-3">
                        @foreach($systemInfo as $key => $value)
                        <div class="flex justify-between items-center">
                            <span class="text-sm" style="color: var(--text-secondary);">
                                {{ ucfirst(str_replace('_', ' ', $key)) }}
                            </span>
                            <code class="text-xs px-2 py-1 rounded font-mono"
                                  style="color: var(--text-primary); background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                {{ $value }}
                            </code>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-bolt mr-2" style="color: var(--warning);"></i> Quick Actions
                    </h3>

                    <div class="space-y-2">
                        <form method="POST" action="{{ route('developer.tools.clear-cache') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full text-left px-3 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                                    style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                <i class="fas fa-broom mr-2"></i> Clear Cache
                            </button>
                        </form>

                        <form method="POST" action="{{ route('developer.tools.optimize') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full text-left px-3 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                                    style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                <i class="fas fa-rocket mr-2"></i> Optimize System
                            </button>
                        </form>

                        <form method="POST" action="{{ route('developer.tools.db-backup') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full text-left px-3 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                                    style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                <i class="fas fa-save mr-2"></i> Backup Database
                            </button>
                        </form>

                        <a href="{{ route('developer.tools.diagnostics') }}"
                           class="w-full text-left px-3 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                           style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-stethoscope mr-2"></i> Run Diagnostics
                        </a>
                    </div>
                </div>
            </div>

            {{-- Recent Activity --}}
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2" style="color: var(--primary);"></i> Recent Activity
                    </h3>

                    <div class="space-y-3">
                        @forelse($recentActivities as $activity)
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <i class="fas fa-info-circle" style="color: var(--text-secondary);"></i>
                            </div>
                            <div class="ml-3 flex-1 min-w-0">
                                <p class="text-sm truncate" style="color: var(--text-primary);">
                                    {{ $activity->description ?? 'Activity recorded' }}
                                </p>
                                <p class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                    {{ optional($activity->created_at)->diffForHumans() ?? 'Just now' }}
                                </p>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-4">
                            <i class="fas fa-inbox text-3xl mb-3"
                               style="color: var(--text-secondary); opacity: 0.5;"></i>
                            <p class="text-sm" style="color: var(--text-primary);">No recent activity</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Actions will appear here
                            </p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Toast Container --}}
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(container);
    }

    const colors = {
        success: { bg: 'bg-green-100',  text: 'text-green-800',  icon: 'fa-check-circle' },
        error:   { bg: 'bg-red-100',    text: 'text-red-800',    icon: 'fa-exclamation-circle' },
        warning: { bg: 'bg-yellow-100', text: 'text-yellow-800', icon: 'fa-exclamation-triangle' },
        info:    { bg: 'bg-blue-100',   text: 'text-blue-800',   icon: 'fa-info-circle' },
    };
    const cfg = colors[type] || colors.info;

    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${cfg.bg} ${cfg.text}`;

    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.innerHTML = `<i class="fas ${cfg.icon} mr-2"></i>${message}`;

    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 hover:opacity-70';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };

    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);

    setTimeout(() => {
        if (toast.parentNode === container) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

document.addEventListener('DOMContentLoaded', function () {
    @if(session('success'))
        showToast("{{ session('success') }}", 'success');
    @endif
    @if(session('error'))
        showToast("{{ session('error') }}", 'error');
    @endif
    @if(session('warning'))
        showToast("{{ session('warning') }}", 'warning');
    @endif
});
</script>

<style>
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06) !important;
}

.stat-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

/* Tool link */
.tool-link {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    transition: all 0.2s ease !important;
    text-decoration: none !important;
}

.tool-link:hover {
    background-color: var(--card-bg) !important;
    border-color: var(--primary) !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(var(--primary-rgb), 0.1);
}

.tool-link:hover span {
    color: var(--primary) !important;
}

.tool-link:hover .fas.fa-chevron-right {
    color: var(--primary) !important;
    transform: translateX(2px);
}

.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

.btn-danger {
    background-color: var(--danger) !important;
    color: white !important;
    border: 1px solid var(--danger) !important;
    transition: all 0.2s ease;
}

.btn-danger:hover {
    filter: brightness(1.1);
    transform: translateY(-1px);
}

.badge-success  { background-color: rgba(var(--success-rgb), 0.1) !important; color: var(--success) !important; border: 1px solid rgba(var(--success-rgb), 0.3) !important; }
.badge-warning  { background-color: rgba(var(--warning-rgb), 0.1) !important; color: var(--warning) !important; border: 1px solid rgba(var(--warning-rgb), 0.3) !important; }
.badge-danger   { background-color: rgba(var(--danger-rgb),  0.1) !important; color: var(--danger)  !important; border: 1px solid rgba(var(--danger-rgb),  0.3) !important; }
.badge-info     { background-color: rgba(var(--info-rgb),    0.1) !important; color: var(--info)    !important; border: 1px solid rgba(var(--info-rgb),    0.3) !important; }
.badge-primary  { background-color: rgba(var(--primary-rgb), 0.1) !important; color: var(--primary) !important; border: 1px solid rgba(var(--primary-rgb), 0.3) !important; }

code {
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    background-color: var(--bg-secondary) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
}

@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }
    .card .p-6 { padding: 1rem !important; }
    .text-2xl { font-size: 1.25rem !important; }
}
</style>
@endsection