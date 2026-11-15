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
    <a href="{{ $createUrl }}"
       class="inline-flex min-h-[44px] items-center justify-center rounded-[10px] bg-brand px-4 py-2 text-[14px] font-semibold text-white transition hover:bg-brand-dark">
        + Buat RFI
    </a>
@endif

