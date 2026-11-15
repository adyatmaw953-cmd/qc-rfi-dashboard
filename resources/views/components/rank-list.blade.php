@props([
    'rows' => [],
    'limit' => 5,
    'unit' => 'temuan',
])

@php
    $rows = is_array($rows) ? $rows : [];

    // Sort descending by value; tie-break alphabetically by label
    usort($rows, function ($a, $b) {
        $valA = $a['value'] ?? 0;
        $valB = $b['value'] ?? 0;
        if ($valB === $valA) {
            return strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        }
        return $valB <=> $valA;
    });

    $maxVal = 0;
    foreach ($rows as $r) {
        if (($r['value'] ?? 0) > $maxVal) {
            $maxVal = $r['value'];
        }
    }

    $topRows = array_slice($rows, 0, $limit);
    $remainingRows = array_slice($rows, $limit);
@endphp

<div class="chart-animate space-y-4">
    @foreach ($topRows as $idx => $row)
        @php
            $rank = $idx + 1;
            $val = $row['value'] ?? 0;
            $barWidth = ($maxVal > 0 && $val > 0) ? max(4, round(($val / $maxVal) * 100, 1)) : 0;
        @endphp
        <div>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full font-mono text-[12px] font-semibold {{ $rank === 1 ? 'bg-[#366F3A] text-white' : 'bg-[#E9EFE5] text-[#366F3A]' }}">
                        {{ $rank }}
                    </span>
                    <span class="truncate text-[14px] font-semibold text-ink">{{ $row['label'] ?? '' }}</span>
                </div>
                <span class="shrink-0 pl-2 font-mono text-[13px] font-medium text-ink">
                    {{ $val }} {{ $unit }}
                </span>
            </div>
            <div class="mt-2 h-2 w-full rounded-full bg-[#EEEBE1] overflow-hidden" aria-hidden="true">
                <div class="chart-bar-horizontal h-full rounded-full {{ $rank === 1 ? 'bg-[#366F3A]' : 'bg-[#8FB28F]' }}"
                     style="width: {{ $barWidth }}%; --i: {{ min(10, $idx) }};"
                     title="{{ $row['label'] ?? '' }}: {{ $val }} {{ $unit }}"
                     tabindex="0"></div>
            </div>
        </div>
    @endforeach

    @if (! empty($remainingRows))
        <details class="group mt-3 pt-1">
            <summary class="cursor-pointer select-none text-[13px] font-semibold text-brand hover:underline">
                Tampilkan semua ({{ count($rows) }})
            </summary>
            <div class="mt-4 space-y-4">
                @foreach ($remainingRows as $idx => $row)
                    @php
                        $rank = $limit + $idx + 1;
                        $val = $row['value'] ?? 0;
                        $barWidth = ($maxVal > 0 && $val > 0) ? max(4, round(($val / $maxVal) * 100, 1)) : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full font-mono text-[12px] font-semibold bg-[#E9EFE5] text-[#366F3A]">
                                    {{ $rank }}
                                </span>
                                <span class="truncate text-[14px] font-semibold text-ink">{{ $row['label'] ?? '' }}</span>
                            </div>
                            <span class="shrink-0 pl-2 font-mono text-[13px] font-medium text-ink">
                                {{ $val }} {{ $unit }}
                            </span>
                        </div>
                        <div class="mt-2 h-2 w-full rounded-full bg-[#EEEBE1] overflow-hidden" aria-hidden="true">
                            <div class="chart-bar-horizontal h-full rounded-full bg-[#8FB28F]"
                                 style="width: {{ $barWidth }}%; --i: {{ min(10, $idx) }};"
                                 title="{{ $row['label'] ?? '' }}: {{ $val }} {{ $unit }}"
                                 tabindex="0"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </details>
    @endif
</div>

