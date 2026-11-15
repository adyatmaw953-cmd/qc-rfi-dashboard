@props([
    'chartId'  => 'line-chart',
    'title'    => '',
    'months'   => [],
    'series'   => [],
])

@php
    /*
     * Multi-series line chart rendered entirely in SVG.
     * $months  – list<string>
     * $series  – array<string, list<int|float>>
     */
    $allEmpty = true;
    foreach ($series as $vals) {
        foreach ($vals as $v) {
            if ($v > 0) { $allEmpty = false; break 2; }
        }
    }

    $defaultColors = [
        'Dibuat' => '#1F5B2A',
        'Ditutup' => '#E07B2E',
    ];
    $seriesKeys = array_keys($series);
    $colors = [];
    foreach ($seriesKeys as $k) {
        $colors[$k] = $defaultColors[$k] ?? '#1F5B2A';
    }

    $n = count($months);

    $allVals = array_merge(...array_values($series));
    $maxVal  = max([1, ...$allVals]);
    $niceMax = (int) (ceil($maxVal / 4) * 4);

    $svgW   = 600;
    $svgH   = 260;
    $padL   = 36;
    $padR   = 12;
    $padT   = 16;
    $padB   = 50;
    $chartW = $svgW - $padL - $padR;
    $chartH = $svgH - $padT - $padB;

    $xStep = $n > 1 ? $chartW / ($n - 1) : $chartW;

    $yTicks = 4;
    $tickStep = $niceMax / $yTicks;

    /**
     * Map a value to SVG y-coordinate.
     */
    $yCoord = function (int|float $v) use ($padT, $chartH, $niceMax): float {
        return $padT + $chartH - ($chartH * $v / $niceMax);
    };
@endphp

<figure aria-label="{{ $title }}" class="chart-animate m-0">
    @if ($allEmpty)
        <div class="flex h-40 items-center justify-center text-sm text-slate-400">Belum ada data</div>
    @else
        <svg
            id="{{ $chartId }}"
            viewBox="0 0 {{ $svgW }} {{ $svgH }}"
            role="img"
            aria-label="{{ $title }}"
            class="w-full"
            style="max-height:260px"
        >
            {{-- Y grid lines & labels --}}
            @for ($t = 0; $t <= $yTicks; $t++)
                @php
                    $yVal = (int) round($tickStep * $t);
                    $yPos = $yCoord($tickStep * $t);
                @endphp
                <line x1="{{ $padL }}" y1="{{ round($yPos, 2) }}" x2="{{ $svgW - $padR }}" y2="{{ round($yPos, 2) }}"
                      stroke="#E1DED3" stroke-width="1" />
                <text x="{{ $padL - 4 }}" y="{{ round($yPos + 4, 2) }}" text-anchor="end"
                      font-family="'Poppins', sans-serif" font-size="11" fill="#5B665E">{{ $yVal }}</text>
            @endfor

            {{-- Lines & dots --}}
            @foreach ($series as $seriesName => $vals)
                @php
                    $points = [];
                    for ($i = 0; $i < $n; $i++) {
                        $px = $n > 1 ? round($padL + $xStep * $i, 2) : round($padL + $chartW / 2, 2);
                        $py = round($yCoord($vals[$i] ?? 0), 2);
                        $points[] = "{$px},{$py}";
                    }
                    $polyline = implode(' ', $points);
                    $pathD = ! empty($points) ? 'M ' . str_replace(' ', ' L ', $polyline) : '';
                    $isDashed = ($seriesName === 'Ditutup');
                @endphp
                <path
                    d="{{ $pathD }}"
                    pathLength="1"
                    fill="none"
                    stroke="{{ $colors[$seriesName] }}"
                    stroke-width="2.5"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                    class="chart-line-path"
                    style="--i: {{ $loop->index }};"
                    @if ($isDashed) stroke-dasharray="6 5" @endif
                />
                @for ($i = 0; $i < $n; $i++)
                    @php
                        [$cx, $cy] = explode(',', $points[$i]);
                        $val = $vals[$i] ?? 0;
                    @endphp
                    <circle cx="{{ $cx }}" cy="{{ $cy }}" r="4" fill="{{ $colors[$seriesName] }}"
                            class="chart-line-dot"
                            style="--i: {{ min(10, $i) }};">
                        <title>{{ ! empty($months[$i]) ? $months[$i].': ' : '' }}{{ $seriesName }}: {{ $val }}</title>
                    </circle>
                @endfor
            @endforeach

            {{-- X labels --}}
            @for ($i = 0; $i < $n; $i++)
                @php
                    $lx = $n > 1 ? round($padL + $xStep * $i, 2) : round($padL + $chartW / 2, 2);
                @endphp
                <text x="{{ $lx }}" y="{{ $svgH - $padB + 16 }}" text-anchor="middle"
                      font-family="'Poppins', sans-serif" font-size="11" fill="#5B665E">{{ $months[$i] ?? '' }}</text>
            @endfor

            {{-- Legend --}}
            <g class="chart-legend">
                @php $lx = $padL; $ly = $svgH - $padB + 30; @endphp
                @foreach ($seriesKeys as $name)
                    @php $isDashed = ($name === 'Ditutup'); @endphp
                    <line x1="{{ $lx }}" y1="{{ $ly - 4 }}" x2="{{ $lx + 14 }}" y2="{{ $ly - 4 }}"
                          stroke="{{ $colors[$name] }}" stroke-width="2.5"
                          @if ($isDashed) stroke-dasharray="6 5" @endif />
                    <circle cx="{{ $lx + 7 }}" cy="{{ $ly - 4 }}" r="3" fill="{{ $colors[$name] }}" />
                    <text x="{{ $lx + 19 }}" y="{{ $ly }}" font-family="'Poppins', sans-serif" font-size="11" fill="#5B665E" class="chart-legend-text">{{ $name }}</text>
                    @php $lx += strlen($name) * 7 + 38; @endphp
                @endforeach
            </g>
        </svg>
    @endif
</figure>
