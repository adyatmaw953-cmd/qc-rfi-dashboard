@props([
    'open' => 0,
    'verifikasi' => null,
    'waiting' => 0,
    'closed' => 0,
    'showLegend' => true,
])

@php
    $verifCount = (int) ($verifikasi ?? $waiting ?? 0);
    $openCount = (int) $open;
    $closedCount = (int) $closed;

    $total = $openCount + $verifCount + $closedCount;
    $openPct = $total > 0 ? round(($openCount / $total) * 100, 1) : 0;
    $verifPct = $total > 0 ? round(($verifCount / $total) * 100, 1) : 0;
    $closedPct = $total > 0 ? round(($closedCount / $total) * 100, 1) : 0;

    $ariaLabel = "Open {$openCount}, menunggu verifikasi {$verifCount}, closed {$closedCount}";
@endphp

<div {{ $attributes->merge(['class' => 'chart-animate w-full space-y-2']) }}>
    <div
        class="flex h-[10px] w-full overflow-hidden rounded-[5px] bg-[#EEEBE1] p-0.5 gap-[2px]"
        role="img"
        aria-label="{{ $ariaLabel }}"
    >
        @if ($openCount > 0)
            <div style="width: {{ $openPct }}%; --i: 0;" class="chart-bar-horizontal h-full rounded-sm bg-[#8DB6E8]" title="Open: {{ $openCount }}" tabindex="0"></div>
        @endif
        @if ($verifCount > 0)
            <div style="width: {{ $verifPct }}%; --i: 1;" class="chart-bar-horizontal h-full rounded-sm bg-[#E07B2E]" title="Menunggu verifikasi: {{ $verifCount }}" tabindex="0"></div>
        @endif
        @if ($closedCount > 0)
            <div style="width: {{ $closedPct }}%; --i: 2;" class="chart-bar-horizontal h-full rounded-sm bg-[#1F5B2A]" title="Closed: {{ $closedCount }}" tabindex="0"></div>
        @endif
    </div>

    @if ($showLegend)
        <div class="flex flex-wrap items-center gap-x-3.5 gap-y-1 text-[12px] sm:text-[13px] text-muted">
            <div class="flex items-center gap-1.5">
                <span class="inline-block h-[9px] w-[9px] rounded-[3px] bg-[#8DB6E8]" aria-hidden="true"></span>
                <span><strong class="font-mono font-semibold text-ink">{{ $openCount }}</strong> Open</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="inline-block h-[9px] w-[9px] rounded-[3px] bg-[#E07B2E]" aria-hidden="true"></span>
                <span><strong class="font-mono font-semibold text-ink">{{ $verifCount }}</strong> Verifikasi</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="inline-block h-[9px] w-[9px] rounded-[3px] bg-[#1F5B2A]" aria-hidden="true"></span>
                <span><strong class="font-mono font-semibold text-ink">{{ $closedCount }}</strong> Closed</span>
            </div>
        </div>
    @endif
</div>
