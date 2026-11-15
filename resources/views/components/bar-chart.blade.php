@props([
    'chartId' => 'bar-chart',
    'title' => '',
    'months' => [],
    'series' => [],
    'layout' => 'stacked', // 'stacked' | 'grouped'
    'showValues' => false,
])

@php
    /*
     * Bar chart rendered entirely in SVG.
     * Supports 'stacked' (default) and 'grouped' modes.
     * $months – list<string> e.g. ['Mei 2026', 'Jun 2026', …]
     * $series – array<string, list<int>> e.g. ['Closed'=>[5,7,...], 'Belum Closed'=>[3,2,...]]
     */
    $allEmpty = true;
    foreach ($series as $vals) {
        foreach ($vals as $v) {
            if ($v > 0) {
                $allEmpty = false;
                break 2;
            }
        }
    }

    $defaultColors = [
        'Open' => '#8DB6E8',
        'NC' => '#E07B2E',
        'Close' => '#1F5B2A',
        'Closed' => '#1F5B2A',
        'Belum Closed' => '#E07B2E',
        'Dibuat' => '#8DB6E8',
        'Ditutup' => '#1F5B2A',
        'NCR' => '#366F3A',
        'Total' => '#366F3A',
    ];
    $seriesKeys = array_keys($series);
    $numSeries = max(1, count($seriesKeys));
    $colors = [];
    foreach ($seriesKeys as $k) {
        $colors[$k] = $defaultColors[$k] ?? '#1F5B2A';
    }

    $n = count($months);

    if ($layout === 'grouped') {
        $maxVal = 1;
        foreach ($series as $vals) {
            foreach ($vals as $v) {
                if ($v > $maxVal) {
                    $maxVal = $v;
                }
            }
        }
    } else {
        $totals = [];
        for ($i = 0; $i < $n; $i++) {
            $sum = 0;
            foreach ($series as $vals) {
                $sum += ($vals[$i] ?? 0);
            }
            $totals[] = $sum;
        }
        $maxVal = max([1, ...$totals]);
    }

    $niceMax = (int) (ceil($maxVal / 4) * 4);
    if ($niceMax <= 0) {
        $niceMax = 4;
    }

    $svgW = 600;
    $svgH = 260;
    $padL = 36;
    $padR = 16;
    $padT = 24;
    $padB = 44;
    $chartW = $svgW - $padL - $padR;
    $chartH = $svgH - $padT - $padB;

    $barSlot = $chartW / max($n, 1);

    if ($layout === 'grouped') {
        $subBarW = max(6, min(24, ($barSlot * 0.75) / $numSeries));
        $groupW = $subBarW * $numSeries + ($numSeries > 1 ? ($numSeries - 1) * 2 : 0);
    } else {
        $barW = max(8, min(36, $barSlot * 0.55));
    }

    $yTicks = 4;
    $tickStep = $niceMax / $yTicks;
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
                    $yPos = $padT + $chartH - ($chartH * $t / $yTicks);
                @endphp
                <line x1="{{ $padL }}" y1="{{ $yPos }}" x2="{{ $svgW - $padR }}" y2="{{ $yPos }}"
                      stroke="#E1DED3" stroke-width="1" />
                <text x="{{ $padL - 6 }}" y="{{ $yPos + 4 }}" text-anchor="end"
                      font-family="'Poppins', sans-serif" font-size="11" fill="#5B665E">{{ $yVal }}</text>
            @endfor

            {{-- Bars --}}
            @for ($i = 0; $i < $n; $i++)
                @php
                    $slotCenterX = $padL + $barSlot * $i + $barSlot / 2;
                @endphp

                @if ($layout === 'grouped')
                    @php
                        $groupStartX = $slotCenterX - $groupW / 2;
                        $sIdx = 0;
                    @endphp
                    @foreach ($series as $seriesName => $vals)
                        @php
                            $val = $vals[$i] ?? 0;
                            $barH = $val > 0 ? max(2, $chartH * $val / $niceMax) : 0;
                            $barX = $groupStartX + $sIdx * ($subBarW + 2);
                            $barY = $padT + $chartH - $barH;
                            $sIdx++;
                        @endphp
                        @if ($barH > 0)
                            <rect
                                x="{{ round($barX, 2) }}"
                                y="{{ round($barY, 2) }}"
                                width="{{ round($subBarW, 2) }}"
                                height="{{ round($barH, 2) }}"
                                fill="{{ $colors[$seriesName] }}"
                                rx="4"
                                class="chart-bar-vertical"
                                style="--i: {{ min(10, $i) }};"
                            >
                                <title>{{ ! empty($months[$i]) ? $months[$i].': ' : '' }}{{ $seriesName }}: {{ $val }}</title>
                            </rect>
                            @if ($showValues)
                                <text
                                    x="{{ round($barX + $subBarW / 2, 2) }}"
                                    y="{{ round($barY - 5, 2) }}"
                                    text-anchor="middle"
                                    font-family="'Poppins', sans-serif"
                                    font-size="11"
                                    font-weight="600"
                                    fill="#14201A"
                                    class="chart-value-label"
                                    style="--i: {{ min(10, $i) }};"
                                >{{ $val }}</text>
                            @endif
                        @endif
                    @endforeach
                @else
                    @php
                        $barX = $slotCenterX - $barW / 2;
                        $stackBottom = $padT + $chartH;
                    @endphp
                    @foreach ($series as $seriesName => $vals)
                        @php
                            $val = $vals[$i] ?? 0;
                            $barH = $val > 0 ? max(2, $chartH * $val / $niceMax) : 0;
                            $barY = $stackBottom - $barH;
                            $stackBottom = $barY;
                        @endphp
                        @if ($barH > 0)
                            <rect
                                x="{{ round($barX, 2) }}"
                                y="{{ round($barY, 2) }}"
                                width="{{ round($barW, 2) }}"
                                height="{{ round($barH, 2) }}"
                                fill="{{ $colors[$seriesName] }}"
                                rx="4"
                                class="chart-bar-vertical"
                                style="--i: {{ min(10, $i) }};"
                            >
                                <title>{{ ! empty($months[$i]) ? $months[$i].': ' : '' }}{{ $seriesName }}: {{ $val }}</title>
                            </rect>
                        @endif
                    @endforeach
                    @if ($showValues && isset($totals[$i]) && $totals[$i] > 0)
                        @php
                            $topBarY = $padT + $chartH - ($chartH * $totals[$i] / $niceMax);
                        @endphp
                        <text
                            x="{{ round($slotCenterX, 2) }}"
                            y="{{ round($topBarY - 5, 2) }}"
                            text-anchor="middle"
                            font-family="'Poppins', sans-serif"
                            font-size="11"
                            font-weight="600"
                            fill="#14201A"
                            class="chart-value-label"
                            style="--i: {{ min(10, $i) }};"
                        >{{ $totals[$i] }}</text>
                    @endif
                @endif

                {{-- X label --}}
                <text
                    x="{{ round($slotCenterX, 2) }}"
                    y="{{ $svgH - $padB + 18 }}"
                    text-anchor="middle"
                    font-family="'Poppins', sans-serif"
                    font-size="11"
                    fill="#5B665E"
                >{{ $months[$i] ?? '' }}</text>
            @endfor
        </svg>
    @endif
</figure>
