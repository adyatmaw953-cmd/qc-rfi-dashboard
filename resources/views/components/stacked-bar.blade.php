@props([
    'items' => [],
    'title' => 'Distribusi Status Mutu',
])

@php
    $items = is_array($items) ? $items : [];
    $total = array_sum(array_column($items, 'value'));

    $ariaParts = [];
    foreach ($items as $it) {
        $lbl = $it['label'] ?? '';
        $val = $it['value'] ?? 0;
        $ariaParts[] = "{$lbl} {$val}";
    }
    $ariaLabel = ! empty($ariaParts) ? "{$title}: " . implode(', ', $ariaParts) : "{$title}: Kosong";

    // Filter positive segments
    $positiveItems = array_filter($items, fn($it) => ($it['value'] ?? 0) > 0);

    // Compute raw percentages with minimum 3% floor for values > 0
    $computedSegments = [];
    if ($total > 0 && ! empty($positiveItems)) {
        $rawWeights = [];
        foreach ($positiveItems as $it) {
            $rawPct = (($it['value'] ?? 0) / $total) * 100;
            $rawWeights[] = max(3, $rawPct);
        }
        $sumWeights = array_sum($rawWeights);

        $idx = 0;
        foreach ($positiveItems as $it) {
            // Normalize so sum of percentages equals exactly 100%
            $normPct = ($rawWeights[$idx] / $sumWeights) * 100;
            $computedSegments[] = [
                'label' => $it['label'] ?? '',
                'value' => $it['value'] ?? 0,
                'color' => $it['color'] ?? '#366F3A',
                'width' => round($normPct, 2),
            ];
            $idx++;
        }
    }
@endphp

<div class="chart-animate flex h-4 w-full overflow-hidden rounded-[8px] bg-[#EEEBE1] gap-[2px]"
     role="img"
     aria-label="{{ $ariaLabel }}">
    @if ($total > 0 && ! empty($computedSegments))
        @foreach ($computedSegments as $idx => $seg)
            <div class="chart-bar-horizontal h-full first:rounded-l-[8px] last:rounded-r-[8px]"
                 style="background-color: {{ $seg['color'] }}; width: {{ $seg['width'] }}%; min-width: 3%; --i: {{ min(10, $idx) }};"
                 title="{{ $seg['label'] }}: {{ $seg['value'] }}"
                 tabindex="0"
                 aria-hidden="true"></div>
        @endforeach
    @endif
</div>

