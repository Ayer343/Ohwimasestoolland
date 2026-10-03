{{-- resources/views/vendor/pagination/tailwind.blade.php --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between">
        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <span class="pagination-item disabled">
                <i class="fas fa-chevron-left mr-1"></i> Previous
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" 
               rel="prev" 
               class="pagination-item hoverable">
                <i class="fas fa-chevron-left mr-1"></i> Previous
            </a>
        @endif

        {{-- Pagination Elements --}}
        <div class="hidden md:flex items-center space-x-1">
            {{-- First Page Link --}}
            @if ($paginator->currentPage() > 2)
                <a href="{{ $paginator->url(1) }}" 
                   class="pagination-number hoverable">
                    1
                </a>
                @if ($paginator->currentPage() > 3)
                    <span class="pagination-ellipsis">...</span>
                @endif
            @endif

            {{-- Array Of Links --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <span class="pagination-ellipsis">{{ $element }}</span>
                @endif

                {{-- Links Array --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page >= $paginator->currentPage() - 1 && $page <= $paginator->currentPage() + 1)
                            @if ($page == $paginator->currentPage())
                                <span class="pagination-number active">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" 
                                   class="pagination-number hoverable">
                                    {{ $page }}
                                </a>
                            @endif
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Last Page Link --}}
            @if ($paginator->currentPage() < $paginator->lastPage() - 1)
                @if ($paginator->currentPage() < $paginator->lastPage() - 2)
                    <span class="pagination-ellipsis">...</span>
                @endif
                <a href="{{ $paginator->url($paginator->lastPage()) }}" 
                   class="pagination-number hoverable">
                    {{ $paginator->lastPage() }}
                </a>
            @endif
        </div>

        {{-- Mobile View: Page Info --}}
        <div class="md:hidden text-sm font-medium" style="color: var(--text-secondary);">
            Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
        </div>

        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" 
               rel="next" 
               class="pagination-item hoverable">
                Next <i class="fas fa-chevron-right ml-1"></i>
            </a>
        @else
            <span class="pagination-item disabled">
                Next <i class="fas fa-chevron-right ml-1"></i>
            </span>
        @endif
    </nav>
@endif