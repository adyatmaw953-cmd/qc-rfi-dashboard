@props([
    'title' => '',
    'count' => 0,
    'description' => '',
    'buttonLabel' => '',
    'buttonUrl' => '#',
    'isPrimary' => false,
    'color' => 'blue', // 'blue' | 'orange'
    'icon' => 'rfi', // 'rfi' | 'verify' | 'overdue'
])

@php
    $bgIcon = $color === 'orange' ? 'bg-[#FCE8D8]' : 'bg-[#E3EEFB]';
    $textIcon = $color === 'orange' ? 'text-[#8F3F08]' : 'text-[#1B4C8C]';
@endphp

<div class="flex flex-col justify-between rounded-2xl border border-line bg-white p-[18px]">
    <div class="flex flex-col gap-3.5">
        <div class="flex items-center gap-3.5">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-[14px] {{ $bgIcon }} {{ $textIcon }}" aria-hidden="true">
                @if ($icon === 'rfi')
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M7 3.75h7l4.25 4.5v11A1.75 1.75 0 0 1 16.5 21h-9a1.75 1.75 0 0 1-1.75-1.75V5.5A1.75 1.75 0 0 1 7.5 3.75Z"></path>
                        <path d="M14 4v5h4.5M8.5 13h7M8.5 16.5h7"></path>
                    </svg>
                @elseif ($icon === 'verify')
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="10.75" cy="10.75" r="6.5"></circle>
                        <path d="m16 16 4.25 4.25M8 10.75l1.8 1.8 3.8-4"></path>
                    </svg>
                @elseif ($icon === 'overdue')
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m12 3.5 9 16H3l9-16Z"></path>
                        <path d="M12 9v4.5M12 17h.01"></path>
                    </svg>
                @endif
            </span>
            <div class="min-w-0">
                <div class="text-[15px] font-semibold leading-[1.3] text-ink">
                    @if ($count !== null)
                        <span class="font-mono text-[22px] font-semibold text-ink mr-1.5 tabular-nums" data-count-to="{{ $count }}">{{ $count }}</span>
                    @endif
                    {{ $title }}
                </div>
                @if ($description)
                    <p class="mt-0.5 text-[13px] font-normal leading-[1.4] text-muted">{{ $description }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-4">
        <a href="{{ $buttonUrl }}"
           class="inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl px-5 text-[14px] font-semibold transition {{ $isPrimary ? 'bg-brand text-white hover:bg-brand-dark' : 'border border-[#8C978B] bg-white text-ink hover:bg-[#FBFAF6]' }}">
            {{ $buttonLabel }}
        </a>
    </div>
</div>

