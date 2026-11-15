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
           class="inline-flex min-h-[44px] items-center justify-center rounded-[10px] bg-brand px-4 py-2 text-[14px] font-semibold text-white transition hover:bg-brand-dark">
            + Buat RFI
        </a>
    @endsection
@endif

@section('content')
    <section aria-label="Status RFI" class="grid gap-4 sm:grid-cols-3">
        <x-stat-card label="RFI Open" :value="$counts['open'] ?? 0" status="open" />
        <x-stat-card label="RFI NC" :value="$counts['nc'] ?? 0" status="nc" />
        <x-stat-card label="RFI Close" :value="$counts['close'] ?? 0" status="close" />
    </section>

    <section id="location-rfi" class="mt-8">
        <h2 class="mb-4 text-[18px] font-semibold text-ink">Location RFI</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($categories as $code => $category)
                <a href="{{ route('rfi.category', ['kategori' => $code]) }}" class="flex items-center justify-between rounded-2xl border border-line bg-white p-5 transition hover:border-brand">
                    <div class="flex items-center gap-4">
                        <div class="flex h-[44px] w-[44px] shrink-0 items-center justify-center rounded-xl bg-brand-pale text-brand">
                            <x-module-menu-icon name="rfi" class="h-5 w-5" />
                        </div>
                        <div>
                            <span class="block text-[12px] text-muted">Location</span>
                            <span class="mt-0.5 block text-[16px] font-semibold text-ink">{{ $category['label'] }}</span>
                        </div>
                    </div>
                    <span class="text-lg text-brand" aria-hidden="true">&rarr;</span>
                </a>
            @endforeach
        </div>
    </section>
@endsection
