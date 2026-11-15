@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigasi Halaman" class="flex flex-wrap items-center justify-between gap-3 border-t border-line bg-white px-5 py-3.5">
        <div class="text-[13px] text-muted">
            Menampilkan <span class="font-medium text-ink">{{ $paginator->firstItem() }}</span>–<span class="font-medium text-ink">{{ $paginator->lastItem() }}</span> dari <span class="font-medium text-ink">{{ $paginator->total() }}</span>{{ isset($totalLabel) ? ' ' . $totalLabel : '' }}
        </div>

        <div class="inline-flex rounded-[10px] border border-line bg-white shadow-xs overflow-hidden">
            @if ($paginator->onFirstPage())
                <span class="inline-flex min-h-[44px] items-center px-4 text-[13px] font-medium text-muted opacity-50 cursor-not-allowed select-none">
                    Sebelumnya
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-[44px] items-center px-4 text-[13px] font-medium text-ink hover:bg-paper transition">
                    Sebelumnya
                </a>
            @endif

            <span class="w-[1px] bg-line"></span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-[44px] items-center px-4 text-[13px] font-medium text-ink hover:bg-paper transition">
                    Berikutnya
                </a>
            @else
                <span class="inline-flex min-h-[44px] items-center px-4 text-[13px] font-medium text-muted opacity-50 cursor-not-allowed select-none">
                    Berikutnya
                </span>
            @endif
        </div>
    </nav>
@elseif ($paginator->total() > 0)
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line bg-white px-5 py-3.5">
        <div class="text-[13px] text-muted">
            Menampilkan <span class="font-medium text-ink">{{ $paginator->firstItem() }}</span>–<span class="font-medium text-ink">{{ $paginator->lastItem() }}</span> dari <span class="font-medium text-ink">{{ $paginator->total() }}</span>{{ isset($totalLabel) ? ' ' . $totalLabel : '' }}
        </div>
    </div>
@endif
