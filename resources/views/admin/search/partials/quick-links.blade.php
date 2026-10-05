<div class="grid grid-cols-2 md:grid-cols-4 gap-3 p-5">
    @foreach($items as $link)
        <a href="{{ $link['url'] }}"
           class="block p-4 rounded-lg transition-all hover:shadow-md"
           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
            <i class="fas {{ $link['icon'] }} text-xl mb-2" style="color: var(--primary);"></i>
            <div class="font-medium text-sm" style="color: var(--text-primary);">{{ $link['label'] }}</div>
            <div class="text-xs mt-1" style="color: var(--text-secondary);">{{ $link['sub'] }}</div>
        </a>
    @endforeach
</div>