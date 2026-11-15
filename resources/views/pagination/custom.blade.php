@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigasi Halaman" class="flex items-center justify-between gap-3 pt-4">
        <div class="flex items-center gap-2">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <span class="inline-flex min-h-[44px] items-center justify-center rounded-[10px] border border-line bg-white px-4 text-[13px] font-medium text-muted/60 opacity-60">
                    &larr; Sebelumnya
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-[44px] items-center justify-center rounded-[10px] border border-line bg-white px-4 text-[13px] font-semibold text-ink transition hover:bg-paper">
                    &larr; Sebelumnya
                </a>
            @endif

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-[44px] items-center justify-center rounded-[10px] border border-line bg-white px-4 text-[13px] font-semibold text-ink transition hover:bg-paper">
                    Berikutnya &rarr;
                </a>
            @else
                <span class="inline-flex min-h-[44px] items-center justify-center rounded-[10px] border border-line bg-white px-4 text-[13px] font-medium text-muted/60 opacity-60">
                    Berikutnya &rarr;
                </span>
            @endif
        </div>

        <div class="text-[13px] font-medium text-muted">
            Halaman <span class="font-mono font-semibold text-ink">{{ $paginator->currentPage() }}</span> dari <span class="font-mono font-semibold text-ink">{{ $paginator->lastPage() }}</span>
        </div>
    </nav>
@endif

