@php
    $items = [
        ['route' => 'developer.system-logs.index',       'label' => 'Logs',       'icon' => 'fa-list'],
        ['route' => 'developer.health.overall',   'label' => 'Health',     'icon' => 'fa-heartbeat'],
        // add other developer nav entries here as needed
    ];
@endphp

<div class="mb-6 flex flex-wrap gap-2">
    @foreach($items as $item)
        @php
            $isActive = request()->routeIs($item['route']);
            $bg   = $isActive ? 'var(--primary)' : 'var(--bg-secondary)';
            $fg   = $isActive ? '#ffffff'         : 'var(--text-secondary)';
        @endphp
        <a href="{{ route($item['route']) }}"
           class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
           style="background-color: {{ $bg }}; color: {{ $fg }};">
            <i class="fas {{ $item['icon'] }} mr-2"></i>{{ $item['label'] }}
        </a>
    @endforeach
</div>