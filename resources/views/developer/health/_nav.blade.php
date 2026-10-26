{{-- resources/views/developer/health/_nav.blade.php --}}
@php
    $navItems = [
        ['route' => 'developer.health.index',             'match' => 'developer.health.index',      'icon' => 'fa-heartbeat',    'label' => 'Dashboard'],
        ['route' => 'developer.health.diagnostics.index', 'match' => 'developer.health.diagnostics.*','icon' => 'fa-stethoscope',  'label' => 'Diagnostics'],
        ['route' => 'developer.health.reports.index',     'match' => 'developer.health.report*',    'icon' => 'fa-file-medical', 'label' => 'Reports'],
        ['route' => 'developer.health.trends.page',       'match' => 'developer.health.trends*',    'icon' => 'fa-chart-line',   'label' => 'Trends & Alerts'],
    ];
@endphp

<nav class="health-subnav flex flex-wrap gap-2 mb-6 pb-3"
     style="border-bottom: 1px solid var(--border-color);">
    @foreach($navItems as $item)
        @if(\Illuminate\Support\Facades\Route::has($item['route']))
            @php
                $isActive = request()->routeIs($item['match']);
                $style = $isActive
                    ? 'background-color: var(--primary); color: white;'
                    : 'background-color: var(--bg-secondary); color: var(--text-secondary);';
            @endphp
            <a href="{{ route($item['route']) }}"
               class="px-3 py-1.5 rounded-lg text-sm transition-all hover:opacity-80 {{ $isActive ? 'font-semibold' : '' }}"
               style="{{ $style }}">
                <i class="fas {{ $item['icon'] }} mr-1"></i>{{ $item['label'] }}
            </a>
        @endif
    @endforeach
</nav>