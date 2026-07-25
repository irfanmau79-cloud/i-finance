@if ($paginator->hasPages())
    <nav class="inv-pager" role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <div class="pg-info">Menampilkan {{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }} dari {{ $paginator->total() }} data</div>
        <div class="pg-btns">
            @if ($paginator->onFirstPage())
                <span class="inv-pg" aria-disabled="true">&lsaquo;</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inv-pg">&lsaquo;</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="inv-pg dots">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="inv-pg active" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="inv-pg" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inv-pg">&rsaquo;</a>
            @else
                <span class="inv-pg" aria-disabled="true">&rsaquo;</span>
            @endif
        </div>
    </nav>
@endif
