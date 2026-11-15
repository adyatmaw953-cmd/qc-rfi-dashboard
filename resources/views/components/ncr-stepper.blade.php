@props([
    'ncr' => null,
    'status' => null,
    'findingDate' => null,
    'caClosedDate' => null,
    'paClosedDate' => null,
])

@php
    $status = $status ?? $ncr?->status ?? 'Open';
    $findingDate = $findingDate ?? $ncr?->finding_date;
    $caClosedDate = $caClosedDate ?? $ncr?->ca_closed_date;
    $paClosedDate = $paClosedDate ?? $ncr?->pa_closed_date;

    $findingDateStr = $findingDate ? format_date($findingDate) : '—';
    $caClosedDateStr = $caClosedDate ? format_date($caClosedDate) : '—';
    $paClosedDateStr = $paClosedDate ? format_date($paClosedDate) : null;

    if ($status === 'Closed') {
        $steps = [
            [
                'name' => 'Open',
                'state' => 'done',
                'desc' => 'Dibuka ' . $findingDateStr,
            ],
            [
                'name' => 'CA Closed',
                'state' => 'done',
                'desc' => 'Koreksi selesai ' . $caClosedDateStr,
            ],
            [
                'name' => 'Closed',
                'state' => 'done',
                'desc' => $paClosedDateStr ?: 'Selesai',
            ],
        ];
    } elseif ($status === 'CA Closed') {
        $steps = [
            [
                'name' => 'Open',
                'state' => 'done',
                'desc' => 'Dibuka ' . $findingDateStr,
            ],
            [
                'name' => 'CA Closed',
                'state' => 'current',
                'desc' => 'Koreksi selesai ' . $caClosedDateStr . ' · Sekarang',
            ],
            [
                'name' => 'Closed',
                'state' => 'pending',
                'desc' => 'Menunggu tindakan pencegahan',
            ],
        ];
    } else {
        $steps = [
            [
                'name' => 'Open',
                'state' => 'current',
                'desc' => 'Dibuka ' . $findingDateStr . ' · Sekarang',
            ],
            [
                'name' => 'CA Closed',
                'state' => 'pending',
                'desc' => 'Menunggu tindakan koreksi',
            ],
            [
                'name' => 'Closed',
                'state' => 'pending',
                'desc' => 'Menunggu tindakan pencegahan',
            ],
        ];
    }
@endphp

<ol aria-label="Status alur NCR" {{ $attributes->merge(['class' => 'space-y-0']) }}>
    @foreach ($steps as $index => $step)
        @php
            $isLast = $loop->last;
            $isDone = $step['state'] === 'done';
            $isCurrent = $step['state'] === 'current';
            $lineColor = $isDone ? 'bg-brand' : 'bg-[#E1DED3]';
        @endphp
        <li class="relative flex items-start gap-3 pb-6 last:pb-0" @if($isCurrent) aria-current="step" @endif>
            {{-- Connecting line --}}
            @if (! $isLast)
                <div class="absolute left-[13px] top-[28px] bottom-0 w-[2px] {{ $lineColor }}" aria-hidden="true"></div>
            @endif

            {{-- Circle 28px --}}
            <div class="relative z-10 flex h-7 w-7 shrink-0 items-center justify-center">
                @if ($isDone)
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-brand text-white">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                @elseif ($isCurrent)
                    <div class="flex h-7 w-7 items-center justify-center rounded-full border-2 border-brand bg-white shadow-[0_0_0_4px_#E9EFE5]">
                        <span class="h-2 w-2 rounded-full bg-brand"></span>
                    </div>
                @else
                    <div class="flex h-7 w-7 items-center justify-center rounded-full border border-[#B9B5A6] bg-white">
                    </div>
                @endif
            </div>

            {{-- Step content --}}
            <div class="min-w-0 flex-1 pt-0.5">
                <p class="text-[14px] leading-tight {{ $isCurrent || $isDone ? 'font-semibold text-ink' : 'font-medium text-muted' }}">
                    {{ $step['name'] }}
                </p>
                <p class="mt-1 text-[12px] leading-normal text-muted">
                    {{ $step['desc'] }}
                </p>
            </div>
        </li>
    @endforeach
</ol>
