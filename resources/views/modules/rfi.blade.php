@extends('layouts.module', ['active' => 'rfi', 'pageTitle' => 'RFI'])

@php
    $userRole = Auth::user()?->role;
    $submitCategories = collect(config('mudalaya.rfi_categories', []))
        ->filter(fn ($cat) => $userRole === 'admin' || in_array($userRole, $cat['submit_roles'] ?? [], true))
        ->keys();
@endphp

@if ($submitCategories->isNotEmpty())
    @php
        $createUrl = $submitCategories->count() === 1
            ? route('rfi.create', ['kategori' => $submitCategories->first()])
            : route('rfi.index') . '#location-rfi';
    @endphp
    @section('header_action')
        <a href="{{ $createUrl }}"
           class="hidden sm:inline-flex min-h-[44px] items-center justify-center rounded-[10px] bg-brand px-4 py-2 text-[14px] font-semibold text-white transition hover:bg-brand-dark">
            + Buat RFI
        </a>
    @endsection
@endif

@section('content')
    {{-- Mobile 3-column stat row (same layout and position as MDR checklist) --}}
    <section class="mb-3.5 grid grid-cols-3 gap-2 sm:hidden" aria-label="Status RFI">
        <div class="flex flex-col gap-1 rounded-2xl border border-line bg-white p-3">
            <div>
                <span class="inline-flex items-center gap-1 rounded-full bg-[#E3EEFB] px-2 py-0.5 text-[10px] font-semibold text-[#1B4C8C]">
                    <span class="h-1.5 w-1.5 rounded-full bg-[#8DB6E8]" aria-hidden="true"></span>Open
                </span>
            </div>
            <div class="font-mono text-[22px] font-semibold leading-snug text-ink tabular-nums"
                 @if (is_numeric($counts['open'] ?? 0)) data-count-to="{{ $counts['open'] ?? 0 }}" @endif>
                {{ $counts['open'] ?? 0 }}
            </div>
        </div>
        <div class="flex flex-col gap-1 rounded-2xl border border-line bg-white p-3">
            <div>
                <span class="inline-flex items-center gap-1 rounded-full bg-[#FCE8D8] px-2 py-0.5 text-[10px] font-semibold text-[#8F3F08]">
                    <span class="h-1.5 w-1.5 rounded-full bg-[#E07B2E]" aria-hidden="true"></span>NC
                </span>
            </div>
            <div class="font-mono text-[22px] font-semibold leading-snug text-ink tabular-nums"
                 @if (is_numeric($counts['nc'] ?? 0)) data-count-to="{{ $counts['nc'] ?? 0 }}" @endif>
                {{ $counts['nc'] ?? 0 }}
            </div>
        </div>
        <div class="flex flex-col gap-1 rounded-2xl border border-line bg-white p-3">
            <div>
                <span class="inline-flex items-center gap-1 rounded-full bg-[#E1F0E2] px-2 py-0.5 text-[10px] font-semibold text-[#1F5B2A]">
                    <span class="h-1.5 w-1.5 rounded-full bg-[#1F5B2A]" aria-hidden="true"></span>Close
                </span>
            </div>
            <div class="font-mono text-[22px] font-semibold leading-snug text-ink tabular-nums"
                 @if (is_numeric($counts['close'] ?? 0)) data-count-to="{{ $counts['close'] ?? 0 }}" @endif>
                {{ $counts['close'] ?? 0 }}
            </div>
        </div>
    </section>

    {{-- Desktop 3 Stat Cards (hidden on mobile) --}}
    <section aria-label="Status RFI" class="hidden sm:grid gap-4 sm:grid-cols-3">
        <x-stat-card label="RFI Open" :value="$counts['open'] ?? 0" status="open" />
        <x-stat-card label="RFI NC" :value="$counts['nc'] ?? 0" status="nc" />
        <x-stat-card label="RFI Close" :value="$counts['close'] ?? 0" status="close" />
    </section>

    <section id="location-rfi" class="mt-6 sm:mt-8">
        <h2 class="mb-3.5 sm:mb-4 text-[17px] sm:text-[18px] font-semibold text-ink">Location RFI</h2>
        <div class="grid gap-3 sm:gap-4 sm:grid-cols-2">
            @foreach ($categories as $code => $category)
                <a href="{{ route('rfi.category', ['kategori' => $code]) }}" class="flex items-center justify-between rounded-2xl border border-line bg-white p-4 sm:p-5 transition hover:border-brand">
                    <div class="flex items-center gap-3.5 sm:gap-4 min-w-0">
                        <span class="font-mono flex h-[44px] w-[44px] shrink-0 items-center justify-center rounded-xl bg-brand text-[20px] font-semibold text-white">
                            {{ $code }}
                        </span>
                        <div class="leading-snug truncate">
                            <span class="text-[12px] font-medium text-muted">Location {{ $code }}</span>
                            <span class="block text-[15px] sm:text-[16px] font-semibold text-ink truncate">{{ $category['label'] }}</span>
                        </div>
                    </div>
                    <span class="text-lg text-brand shrink-0" aria-hidden="true">&rarr;</span>
                </a>
            @endforeach
        </div>
    </section>

    @if ($submitCategories->isNotEmpty())
        {{-- FAB: + Buat RFI (mobile only, fixed bottom-right) --}}
        <a href="{{ $createUrl }}"
           class="sm:hidden fixed bottom-[88px] right-5 z-40 inline-flex min-h-[52px] items-center gap-2 rounded-[26px] bg-brand px-6 text-[14px] font-semibold text-white shadow-[0_6px_16px_rgba(20,32,26,0.25)] transition hover:bg-brand-dark active:scale-95"
           aria-label="Buat RFI baru">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Buat RFI
        </a>
    @endif
@endsection
