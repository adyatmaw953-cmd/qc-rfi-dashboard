@props([
    'rows' => [],
    'total' => null,
])

@php
    $rows = is_array($rows) ? $rows : [];
    $computedTotal = array_sum(array_column($rows, 'value'));
    $total = $total ?? $computedTotal;

    // Sort descending by value; tie-break alphabetically by label
    usort($rows, function ($a, $b) {
        $valA = $a['value'] ?? 0;
        $valB = $b['value'] ?? 0;
        if ($valB === $valA) {
            return strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        }
        return $valB <=> $valA;
    });
@endphp

<div class="chart-animate space-y-[18px]">
    @foreach ($rows as $idx => $row)
        @php
            $val = $row['value'] ?? 0;
            $pct = $total > 0 ? ($val / $total) * 100 : 0;
            $pctStr = $total > 0 ? format_percentage($pct, 2) : '0%';
            $barWidth = $val > 0 ? max(4, round($pct, 2)) : 0;
        @endphp
        <div>
            <div class="flex items-center justify-between">
                <span class="text-[14px] font-semibold text-ink">{{ $row['label'] ?? '' }}</span>
                <span class="font-mono text-[13px] font-medium text-ink">{{ $val }} kasus &middot; {{ $pctStr }}</span>
            </div>
            <div class="mt-2 h-[10px] w-full rounded-full bg-[#EEEBE1] overflow-hidden" aria-hidden="true">
                <div class="chart-bar-horizontal h-full rounded-full bg-[#366F3A]"
                     style="width: {{ $barWidth }}%; --i: {{ min(10, $idx) }};"
                     title="{{ $row['label'] ?? '' }}: {{ $val }}"
                     tabindex="0"></div>
            </div>
        </div>
    @endforeach
</div>

