@props([
    'items' => [],
    'decimals' => 1,
    'shape' => 'dot',
    'total' => null,
])

@php
    $items = is_array($items) ? $items : [];
    $computedTotal = array_sum(array_column($items, 'value'));
    $total = $total ?? $computedTotal;
@endphp

<ul class="space-y-3">
    @foreach ($items as $item)
        @php
            $val = $item['value'] ?? 0;
            $pct = $total > 0 ? ($val / $total) * 100 : 0;
            $pctStr = $total > 0 ? format_percentage($pct, $decimals) : '0%';
            $color = $item['color'] ?? '#366F3A';
        @endphp
        <li class="flex items-center justify-between text-[14px]">
            <span class="flex items-center gap-2.5 text-ink">
                @if ($shape === 'square')
                    <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-[3px]"
                          style="background-color: {{ $color }};"
                          aria-hidden="true"></span>
                @else
                    <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                          style="background-color: {{ $color }};"
                          aria-hidden="true"></span>
                @endif
                <span class="font-normal">{{ $item['label'] ?? '' }}</span>
            </span>
            <span class="font-mono text-[14px] font-medium text-ink">
                {{ $val }} &middot; {{ $pctStr }}
            </span>
        </li>
    @endforeach
</ul>

