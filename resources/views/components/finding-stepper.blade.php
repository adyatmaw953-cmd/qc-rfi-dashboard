@props([
    'stages' => [],
])

<ol aria-label="Tahapan temuan" {{ $attributes->merge(['class' => 'flex w-full items-center justify-between gap-2 overflow-x-auto py-2']) }}>
    @foreach ($stages as $index => $stage)
        @php
            $isLast = $loop->last;
            $done = $stage['done'] ?? false;
            $isCurrent = $stage['isCurrent'] ?? false;
        @endphp

        <li class="flex flex-1 items-center gap-2 {{ $isLast ? 'flex-none' : '' }}" @if($isCurrent) aria-current="step" @endif>
            <div class="flex items-center gap-2.5">
                @if ($done)
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand text-white" title="{{ $stage['label'] }} (Selesai)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                @elseif ($isCurrent)
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 border-brand bg-white font-mono text-[13px] font-bold text-brand shadow-[0_0_0_4px_#E9EFE5]" title="{{ $stage['label'] }} (Sekarang)">
                        {{ $stage['number'] }}
                    </div>
                @else
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-[#B9B5A6] bg-white font-mono text-[13px] text-muted" title="{{ $stage['label'] }} (Menunggu)">
                        {{ $stage['number'] }}
                    </div>
                @endif

                <div class="hidden sm:block">
                    <p class="text-[13px] leading-tight {{ $isCurrent ? 'font-semibold text-ink' : ($done ? 'font-medium text-ink' : 'font-normal text-muted') }}">
                        {{ $stage['label'] }}
                    </p>
                    <p class="text-[11px] leading-tight {{ $isCurrent ? 'font-medium text-brand' : ($done ? 'text-brand' : 'text-muted') }}">
                        @if ($done)
                            Selesai
                        @elseif ($isCurrent)
                            Sekarang
                        @else
                            Menunggu
                        @endif
                    </p>
                </div>
            </div>

            @if (! $isLast)
                <div class="h-[2px] flex-1 mx-2 {{ $done ? 'bg-brand' : 'bg-[#E1DED3]' }}" aria-hidden="true"></div>
            @endif
        </li>
    @endforeach
</ol>

