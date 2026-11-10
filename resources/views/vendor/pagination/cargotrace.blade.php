{{--
 ============================================================
 BUAT APA : Tampilan pagination CargoTrace (Previous / Page X of Y / Next).
 Dipakai dengan: {{ $items->links('vendor.pagination.cargotrace') }}
 Sengaja ringkas supaya muat di layar HP.
 ============================================================
--}}
@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Pagination">
        <p class="pager-info">Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }}</p>

        <div class="pager-links">
            @if ($paginator->onFirstPage())
                <span class="pager-btn pager-disabled">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="pager-btn" rel="prev">Previous</a>
            @endif

            <span class="pager-page">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="pager-btn" rel="next">Next</a>
            @else
                <span class="pager-btn pager-disabled">Next</span>
            @endif
        </div>
    </nav>
@endif
