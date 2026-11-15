@props([
    'items' => [],
    'total' => null,
    'centerLabel' => 'NCR',
    'title' => 'Tipe NCR',
])

@php
    $items = is_array($items) ? $items : [];
    $computedTotal = array_sum(array_column($items, 'value'));
    $total = $total ?? $computedTotal;

    $circumference = 2 * M_PI * 44; // ~276.46015
    $r = 44;
    $cx = 60;
    $cy = 60;

    $ariaParts = [];
    foreach ($items as $it) {
        $lbl = $it['label'] ?? '';
        $val = $it['value'] ?? 0;
        $ariaParts[] = "{$lbl} {$val}";
    }
    $ariaLabel = ! empty($ariaParts) ? "{$title}: " . implode(', ', $ariaParts) : "{$title}: Kosong";

    $segments = [];
    $accumulatedOffset = 0;
    if ($total > 0) {
        foreach ($items as $item) {
            $val = (float) ($item['value'] ?? 0);
            if ($val <= 0) {
                continue;
            }
            $dashLength = ($val / $total) * $circumference;
            $segments[] = [
                'color' => $item['color'] ?? '#366F3A',
                'dashLength' => round($dashLength, 2),
                'offset' => round(-$accumulatedOffset, 2),
                'label' => $item['label'] ?? '',
                'value' => $val,
            ];
            $accumulatedOffset += $dashLength;
        }
    }
@endphp

<svg viewBox="0 0 120 120"
     width="160"
     height="160"
     class="chart-animate max-w-full h-auto"
     role="img"
     aria-label="{{ $ariaLabel }}">
    {{-- Segments rotated to start at 12 o'clock (-90deg around center 60,60) --}}
    <g transform="rotate(-90 60 60)">
        @if ($total === 0 || empty($segments))
            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}"
                    fill="none"
                    stroke="#EEEBE1"
                    stroke-width="18" />
        @else
            {{-- Background track --}}
            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}"
                    fill="none"
                    stroke="#EEEBE1"
                    stroke-width="18" />

            @foreach ($segments as $idx => $seg)
                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}"
                        fill="none"
                        stroke="{{ $seg['color'] }}"
                        stroke-width="18"
                        stroke-linecap="butt"
                        stroke-dasharray="{{ $seg['dashLength'] }} {{ round($circumference, 2) }}"
                        stroke-dashoffset="{{ $seg['offset'] }}"
                        class="chart-donut-segment"
                        style="--final-offset: {{ $seg['offset'] }}; --initial-offset: {{ round($seg['offset'] + $circumference, 2) }}; --i: {{ $idx }};"
                >
                    <title>{{ $seg['label'] }}: {{ $seg['value'] }}</title>
                </circle>
            @endforeach
        @endif
    </g>

    {{-- Center text --}}
    <g class="chart-donut-center">
        <text x="60" y="55"
              text-anchor="middle"
              dominant-baseline="central"
              font-family="'Poppins', sans-serif"
              font-size="28"
              font-weight="600"
              fill="#14201A"
              class="tabular-nums"
              @if (is_numeric($total)) data-count-to="{{ $total }}" @endif>
            {{ $total }}
        </text>
        <text x="60" y="75"
              text-anchor="middle"
              dominant-baseline="central"
              font-size="12"
              fill="#5B665E">
            {{ $centerLabel }}
        </text>
    </g>
</svg>

