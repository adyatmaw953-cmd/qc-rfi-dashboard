@props([
    'subtitle' => null,
    'compact' => false,
])

<div class="flex min-w-0 items-center gap-2.5">
    <div class="flex shrink-0 items-center -space-x-1.5">
        <span class="relative z-10 flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-white ring-2 ring-forest" style="{{ $compact ? 'width: 28px; height: 28px; min-width: 28px; min-height: 28px; padding: 2px;' : 'width: 32px; height: 32px; min-width: 32px; min-height: 32px; padding: 2px;' }}">
            <img src="{{ asset('images/mei.png') }}" alt="Logo Mudalaya Energy Indonesia" style="width: 100%; height: 100%; max-width: 100%; max-height: 100%; object-fit: contain; display: block;">
        </span>
        <span class="relative z-0 flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-white ring-2 ring-forest" style="{{ $compact ? 'width: 28px; height: 28px; min-width: 28px; min-height: 28px; padding: 2px;' : 'width: 32px; height: 32px; min-width: 32px; min-height: 32px; padding: 2px;' }}">
            <img src="{{ asset('images/mgi.png') }}" alt="Logo Mutiara Global Industry" style="width: 100%; height: 100%; max-width: 100%; max-height: 100%; object-fit: contain; display: block;">
        </span>
    </div>
    <div class="min-w-0 flex-1 overflow-hidden">
        <span class="brand-carousel block text-xs font-bold leading-tight text-white {{ $compact ? 'text-[11px]' : '' }}" aria-label="PT. Mudalaya Energy Indonesia / PT. Mutiara Global Industry">
            <span class="brand-carousel-track" aria-hidden="true">
                <span class="brand-carousel-slide">PT. MUDALAYA ENERGY<br>INDONESIA</span>
                <span class="brand-carousel-slide">PT. MUTIARA GLOBAL<br>INDUSTRY</span>
                <span class="brand-carousel-slide">PT. MUDALAYA ENERGY<br>INDONESIA</span>
            </span>
        </span>
        @if ($subtitle)
            <span class="mt-0.5 block text-[10px] leading-tight text-forest-muted truncate">{{ $subtitle }}</span>
        @endif
    </div>
</div>

@once
    <style>
        .brand-carousel {
            height: 2.5em;
            overflow: hidden;
            display: block;
        }

        .brand-carousel-track {
            display: block;
            margin: 0;
            padding: 0;
            animation: brand-carousel-slide 6s ease-in-out infinite;
            will-change: transform;
        }

        .brand-carousel-slide {
            display: block;
            height: 2.5em;
            line-height: 1.25;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: -0.02em;
            box-sizing: border-box;
            color: #FFFFFF;
            white-space: nowrap;
        }

        @keyframes brand-carousel-slide {
            0%, 44% {
                transform: translateY(0);
            }

            50%, 94% {
                transform: translateY(-2.5em);
            }

            100% {
                transform: translateY(-5em);
            }
        }
    </style>
@endonce
