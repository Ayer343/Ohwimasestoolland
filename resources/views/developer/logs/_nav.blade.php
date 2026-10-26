@php
    /*
     | Every route here is guarded by Route::has() so a missing route simply
     | renders as plain text instead of throwing RouteNotFoundException.
     | That's what previously broke the show/index blades when route names
     | drifted between developer.logs.* and developer.system-logs.*.
     */
    $items = [
        // Primary views
        ['route' => 'developer.logs.index',            'label' => 'Logs',       'icon' => 'fa-list'],
        ['route' => 'developer.logs.overview',         'label' => 'Overview',   'icon' => 'fa-chart-pie'],

        // Filtered level views
        ['route' => 'developer.logs.errors',           'label' => 'Errors',     'icon' => 'fa-circle-exclamation'],
        ['route' => 'developer.logs.warnings',         'label' => 'Warnings',   'icon' => 'fa-triangle-exclamation'],
        ['route' => 'developer.logs.critical',         'label' => 'Critical',   'icon' => 'fa-fire'],
        ['route' => 'developer.logs.debug',            'label' => 'Debug',      'icon' => 'fa-bug'],
        ['route' => 'developer.logs.info',             'label' => 'Info',       'icon' => 'fa-circle-info'],

        // Analysis
        ['route' => 'developer.logs.analysis',         'label' => 'Analysis',   'icon' => 'fa-chart-bar'],
        ['route' => 'developer.logs.trends',           'label' => 'Trends',     'icon' => 'fa-chart-line'],

        // Live
        ['route' => 'developer.logs.live',             'label' => 'Live',       'icon' => 'fa-bolt'],
        ['route' => 'developer.logs.realtime',         'label' => 'Realtime',   'icon' => 'fa-satellite-dish'],

        // Retention / config
        ['route' => 'developer.logs.archives',         'label' => 'Archives',   'icon' => 'fa-box-archive'],
        ['route' => 'developer.logs.settings',         'label' => 'Settings',   'icon' => 'fa-cog'],

        // Cross-link to the DB-backed workflow controller
        ['route' => 'developer.system-logs.index',     'label' => 'System Logs','icon' => 'fa-clipboard-list', 'external' => true],
    ];
@endphp

<div class="mb-6">
    <div class="flex flex-wrap gap-2 items-center">
        @foreach($items as $item)
            @php
                $routeName = $item['route'];
                $exists    = \Illuminate\Support\Facades\Route::has($routeName);
                $isActive  = $exists && request()->routeIs($routeName);
                $isExternal= $item['external'] ?? false;

                if ($isActive) {
                    $bg = 'var(--primary)';
                    $fg = '#ffffff';
                } elseif ($isExternal) {
                    $bg = 'transparent';
                    $fg = 'var(--primary)';
                } else {
                    $bg = 'var(--bg-secondary)';
                    $fg = 'var(--text-secondary)';
                }

                $border = $isExternal
                    ? '1px solid var(--primary)'
                    : '1px solid transparent';
            @endphp

            @if($exists)
                <a href="{{ route($routeName) }}"
                   class="px-3 py-1.5 rounded-lg text-xs transition-all hover:opacity-80 whitespace-nowrap"
                   style="background-color: {{ $bg }}; color: {{ $fg }}; border: {{ $border }};"
                   title="{{ $item['label'] }}">
                    <i class="fas {{ $item['icon'] }} mr-1"></i>{{ $item['label'] }}
                </a>
            @else
                <span class="px-3 py-1.5 rounded-lg text-xs whitespace-nowrap opacity-40 cursor-not-allowed"
                      style="background-color: var(--bg-secondary); color: var(--text-secondary); border: 1px solid transparent;"
                      title="Route {{ $routeName }} is not registered">
                    <i class="fas {{ $item['icon'] }} mr-1"></i>{{ $item['label'] }}
                </span>
            @endif
        @endforeach
    </div>
</div>