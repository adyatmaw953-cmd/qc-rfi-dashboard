@extends('layouts.module', ['active' => 'users', 'pageTitle' => 'Kelola Akun Pengguna'])

@section('header_action')
    <a href="{{ route('users.create') }}"
       class="hidden sm:inline-flex min-h-[44px] items-center justify-center rounded-[10px] bg-brand px-5 py-2 text-[14px] font-semibold text-white transition hover:bg-brand-dark">
        + Tambah Akun
    </a>
@endsection

@section('content')

@php
    $hasAnyFilter = ! empty($filters['search']) || ! empty($filters['role']) || ! empty($filters['status']);

    $statusTabs = [
        ['key' => '', 'label' => 'Semua', 'count' => $statusCounts['all'] ?? 0],
        ['key' => 'active', 'label' => 'Aktif', 'count' => $statusCounts['active'] ?? 0],
        ['key' => 'inactive', 'label' => 'Nonaktif', 'count' => $statusCounts['inactive'] ?? 0],
    ];

    $roleLabels = [
        'admin' => 'Super Admin',
        'qc_qa' => 'QC / QA',
        'production' => 'Production',
        'warehouse' => 'Warehouse',
        'ppic' => 'PPIC',
    ];

    $roleChipStyles = [
        'admin' => ['bg' => 'bg-[#E3EEFB]', 'fg' => 'text-[#1B4C8C]'],
        'qc_qa' => ['bg' => 'bg-[#E1F0E2]', 'fg' => 'text-[#1F5B2A]'],
        'production' => ['bg' => 'bg-[#FCE8D8]', 'fg' => 'text-[#8F3F08]'],
        'warehouse' => ['bg' => 'bg-[#FBF0C9]', 'fg' => 'text-[#6B4E00]'],
        'ppic' => ['bg' => 'bg-[#F3E8FF]', 'fg' => 'text-[#6B21A8]'],
    ];
@endphp

{{-- Subtitle on Mobile/Desktop --}}
<div class="mb-4">
    <span class="text-[13px] font-medium text-brand">PT Mudalaya</span>
    <h2 class="text-[20px] sm:text-[24px] font-bold text-ink leading-tight">Kelola Akun Pengguna</h2>
</div>

{{-- Filter bar --}}
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">

    {{-- Segmented status tabs --}}
    <div class="no-scrollbar flex gap-2 overflow-x-auto pb-1 sm:flex-wrap sm:overflow-visible sm:pb-0" role="group" aria-label="Filter status akun">
        @foreach ($statusTabs as $tab)
            @php
                $isActive = ($filters['status'] ?? '') === $tab['key'];
                $tabUrl = route('users.index', array_merge(
                    array_filter($filters, fn($v) => $v !== null && $v !== ''),
                    ['status' => $tab['key'] !== '' ? $tab['key'] : null, 'page' => null]
                ));
            @endphp
            <a href="{{ $tabUrl }}"
               class="inline-flex shrink-0 min-h-[38px] sm:min-h-[44px] items-center gap-1.5 rounded-[10px] px-3.5 py-1.5 text-[13px] sm:text-[14px] transition
                      {{ $isActive ? 'bg-ink font-semibold text-white shadow-xs' : 'border border-line bg-white font-medium text-ink hover:bg-paper-soft' }}"
               aria-current="{{ $isActive ? 'page' : 'false' }}">
                <span>{{ $tab['label'] }}</span>
                <span class="font-mono text-[12px] {{ $isActive ? 'opacity-80' : 'text-muted' }}">{{ $tab['count'] }}</span>
            </a>
        @endforeach
    </div>

    {{-- Search & Role filter --}}
    <form method="GET" action="{{ route('users.index') }}" id="users-filter-form" class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:gap-2 w-full sm:w-auto">
        @if (! empty($filters['status']))
            <input type="hidden" name="status" value="{{ $filters['status'] }}">
        @endif

        {{-- Search input --}}
        <div class="relative w-full sm:w-[260px]">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/>
                </svg>
            </span>
            <input type="search"
                   name="search"
                   value="{{ $filters['search'] ?? '' }}"
                   aria-label="Cari nama atau email"
                   placeholder="Cari nama atau email..."
                   class="h-[44px] w-full rounded-[10px] border border-[#8C978B] sm:border-line bg-white pl-10 pr-3.5 text-[14px] text-ink placeholder:text-muted focus:border-brand focus:outline-none">
        </div>

        {{-- Role dropdown filter --}}
        <div class="relative w-full sm:w-[170px]">
            <select name="role"
                    onchange="document.getElementById('users-filter-form').submit()"
                    aria-label="Filter Role"
                    class="h-[44px] w-full appearance-none rounded-[10px] border border-[#8C978B] sm:border-line bg-white px-3.5 pr-9 text-[14px] font-medium text-ink focus:border-brand focus:outline-none">
                <option value="">Semua Role</option>
                @foreach ($roles as $r)
                    <option value="{{ $r }}" @selected(($filters['role'] ?? '') === $r)>
                        {{ $roleLabels[$r] ?? $r }}
                    </option>
                @endforeach
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3.5 flex items-center text-muted">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </span>
        </div>

        @if ($hasAnyFilter)
            <a href="{{ route('users.index') }}"
               class="inline-flex min-h-[44px] items-center justify-center rounded-[10px] border border-line bg-white px-3.5 text-[13px] font-semibold text-muted transition hover:text-ink">
                Reset
            </a>
        @endif
    </form>
</div>

{{-- MOBILE VIEW: Card list (sm:hidden) --}}
<section aria-label="Daftar akun pengguna" class="space-y-3 sm:hidden">
    @forelse ($users as $user)
        @php
            $rStyle = $roleChipStyles[$user->role] ?? ['bg' => 'bg-[#E3EEFB]', 'fg' => 'text-[#1B4C8C]'];
            $isSelf = Auth::id() === $user->id;
            $userInitials = collect(preg_split('/\s+/', trim($user->name)) ?: [])
                ->filter()
                ->take(2)
                ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                ->implode('') ?: 'U';
        @endphp
        <article class="flex flex-col gap-2.5 rounded-2xl border border-line bg-white p-4">
            {{-- Header user card --}}
            <div class="flex items-center gap-3">
                <span aria-hidden="true" class="flex h-[44px] w-[44px] shrink-0 items-center justify-center rounded-full font-mono text-[13px] font-bold {{ $rStyle['bg'] }} {{ $rStyle['fg'] }}">
                    {{ $userInitials }}
                </span>
                <div class="min-w-0 flex-1 leading-snug">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-[15px] font-semibold text-ink truncate">{{ $user->name }}</span>
                        @if ($isSelf)
                            <span class="inline-flex items-center rounded-full bg-[#EEEBE1] px-2 py-0.5 text-[11px] font-semibold text-[#3F4A41]">Anda</span>
                        @endif
                    </div>
                    <div class="text-[13px] text-muted truncate break-all">{{ $user->email }}</div>
                </div>
            </div>

            {{-- Chips: Role + Status --}}
            <div class="flex flex-wrap items-center gap-2 pt-0.5">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[12px] font-semibold {{ $rStyle['bg'] }} {{ $rStyle['fg'] }}">
                    {{ $roleLabels[$user->role] ?? $user->role }}
                </span>
                @if ($user->is_active)
                    <span class="inline-flex items-center gap-1.5 text-[13px] font-medium text-[#1F5B2A]">
                        <span class="h-2 w-2 rounded-full bg-[#1F5B2A]" aria-hidden="true"></span>
                        <span>Aktif</span>
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-[13px] font-medium text-[#5B665E]">
                        <span class="h-2 w-2 rounded-full bg-[#8E9B90]" aria-hidden="true"></span>
                        <span>Nonaktif</span>
                    </span>
                @endif
            </div>

            {{-- Action buttons --}}
            <div class="flex gap-2 pt-1">
                <a href="{{ route('users.edit', $user) }}"
                   class="flex-1 min-h-[44px] inline-flex items-center justify-center rounded-[10px] border border-[#8C978B] bg-white text-[13px] font-semibold text-ink transition hover:bg-paper-soft">
                    Edit
                </a>

                @if ($isSelf)
                    <span aria-disabled="true"
                          class="flex-1 min-h-[44px] inline-flex items-center justify-center rounded-[10px] border border-line bg-white text-[13px] font-semibold text-muted opacity-50 cursor-not-allowed">
                        Nonaktifkan
                    </span>
                @else
                    <form method="POST" action="{{ route('users.toggle-status', $user) }}" class="flex-1 inline-flex">
                        @csrf
                        @method('PATCH')
                        @if ($user->is_active)
                            <button type="submit"
                                    onclick="return confirm('Apakah Anda yakin ingin menonaktifkan akun {{ $user->name }}?')"
                                    class="w-full min-h-[44px] inline-flex items-center justify-center rounded-[10px] border border-[#8C978B] bg-white text-[13px] font-semibold text-[#8F3F08] transition hover:bg-[#FCE8D8]/50">
                                Nonaktifkan
                            </button>
                        @else
                            <button type="submit"
                                    onclick="return confirm('Apakah Anda yakin ingin mengaktifkan kembali akun {{ $user->name }}?')"
                                    class="w-full min-h-[44px] inline-flex items-center justify-center rounded-[10px] border border-[#8C978B] bg-white text-[13px] font-semibold text-[#1F5B2A] transition hover:bg-[#E1F0E2]/60">
                                Aktifkan
                            </button>
                        @endif
                    </form>
                @endif
            </div>
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-line bg-white p-8 text-center">
            @if ($hasAnyFilter)
                <p class="text-[14px] font-semibold text-ink">Tidak ada akun yang cocok</p>
                <p class="mt-1 text-[13px] text-muted">Coba ubah kata kunci pencarian atau filter yang dipilih.</p>
                <a href="{{ route('users.index') }}"
                   class="mt-2 inline-block text-[13px] font-semibold text-brand hover:underline">Reset filter</a>
            @else
                <p class="text-[14px] font-semibold text-ink">Belum ada akun pengguna</p>
                <p class="mt-1 text-[13px] text-muted">Klik tombol &ldquo;+ Tambah Akun&rdquo; untuk membuat akun baru.</p>
            @endif
        </div>
    @endforelse
</section>

{{-- DESKTOP VIEW: Data table card (hidden sm:block) --}}
<div class="hidden sm:block overflow-hidden rounded-2xl border border-line bg-white">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-left text-[13px]">
            <thead class="border-b border-line bg-paper-soft">
                <tr>
                    <th class="px-5 py-[14px] text-[13px] font-semibold text-muted" data-l="Nama">Nama</th>
                    <th class="px-5 py-[14px] text-[13px] font-semibold text-muted" data-l="Email">Email</th>
                    <th class="px-5 py-[14px] text-[13px] font-semibold text-muted" data-l="Role">Role</th>
                    <th class="px-5 py-[14px] text-[13px] font-semibold text-muted" data-l="Status">Status</th>
                    <th class="px-5 py-[14px] text-[13px] font-semibold text-muted text-right" data-l="Aksi">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($users as $user)
                    @php
                        $rStyle = $roleChipStyles[$user->role] ?? ['bg' => 'bg-paper-soft', 'fg' => 'text-ink'];
                        $isSelf = Auth::id() === $user->id;
                        $userInitials = collect(preg_split('/\s+/', trim($user->name)) ?: [])
                            ->filter()
                            ->take(2)
                            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                            ->implode('') ?: 'U';
                    @endphp
                    <tr class="transition hover:bg-paper-soft/60">
                        {{-- Nama --}}
                        <td class="px-5 py-4 font-semibold text-ink" data-l="Nama">
                            <div class="flex items-center gap-3">
                                <span aria-hidden="true" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full font-mono text-[12px] font-bold {{ $rStyle['bg'] }} {{ $rStyle['fg'] }}">
                                    {{ $userInitials }}
                                </span>
                                <div class="flex items-center gap-2">
                                    <span>{{ $user->name }}</span>
                                    @if ($isSelf)
                                        <span class="inline-flex items-center rounded-full bg-[#EEEBE1] px-2 py-0.5 text-[11px] font-semibold text-[#3F4A41]">Anda</span>
                                    @endif
                                </div>
                            </div>
                        </td>

                        {{-- Email --}}
                        <td class="px-5 py-4 text-muted" data-l="Email">
                            {{ $user->email }}
                        </td>

                        {{-- Role --}}
                        <td class="px-5 py-4" data-l="Role">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[12px] font-semibold {{ $rStyle['bg'] }} {{ $rStyle['fg'] }}">
                                {{ $roleLabels[$user->role] ?? $user->role }}
                            </span>
                        </td>

                        {{-- Status --}}
                        <td class="px-5 py-4" data-l="Status">
                            @if ($user->is_active)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#E1F0E2] px-2.5 py-0.5 text-[12px] font-semibold text-[#1F5B2A]">
                                    <span class="h-1.5 w-1.5 rounded-full bg-[#1F5B2A]" aria-hidden="true"></span>
                                    <span>Aktif</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#F0EEE6] px-2.5 py-0.5 text-[12px] font-semibold text-[#5B665E]">
                                    <span class="h-1.5 w-1.5 rounded-full bg-[#8E9B90]" aria-hidden="true"></span>
                                    <span>Nonaktif</span>
                                </span>
                            @endif
                        </td>

                        {{-- Aksi --}}
                        <td class="px-5 py-4 text-right" data-l="Aksi">
                            <div class="inline-flex items-center justify-end gap-2">
                                <a href="{{ route('users.edit', $user) }}"
                                   class="inline-flex min-h-[36px] items-center justify-center rounded-[8px] border border-[#8C978B] bg-white px-3.5 text-[12px] font-semibold text-ink transition hover:bg-paper-soft">
                                    Edit
                                </a>

                                @if ($isSelf)
                                    <span aria-disabled="true"
                                          class="inline-flex min-h-[36px] items-center justify-center rounded-[8px] border border-line bg-white px-3 text-[12px] font-semibold text-muted opacity-50 cursor-not-allowed">
                                        Nonaktifkan
                                    </span>
                                @else
                                    <form method="POST" action="{{ route('users.toggle-status', $user) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        @if ($user->is_active)
                                            <button type="submit"
                                                    onclick="return confirm('Apakah Anda yakin ingin menonaktifkan akun {{ $user->name }}?')"
                                                    class="inline-flex min-h-[36px] items-center justify-center rounded-[8px] border border-[#8C978B] bg-white px-3 text-[12px] font-semibold text-[#8F3F08] transition hover:bg-[#FCE8D8]/50">
                                                Nonaktifkan
                                            </button>
                                        @else
                                            <button type="submit"
                                                    onclick="return confirm('Apakah Anda yakin ingin mengaktifkan kembali akun {{ $user->name }}?')"
                                                    class="inline-flex min-h-[36px] items-center justify-center rounded-[8px] border border-[#8C978B] bg-white px-3 text-[12px] font-semibold text-[#1F5B2A] transition hover:bg-[#E1F0E2]/60">
                                                Aktifkan
                                            </button>
                                        @endif
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <svg class="h-12 w-12 text-muted opacity-40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                                @if ($hasAnyFilter)
                                    <p class="text-[14px] font-semibold text-ink">Tidak ada akun yang cocok</p>
                                    <p class="text-[13px] text-muted">Coba ubah kata kunci pencarian atau filter yang dipilih.</p>
                                    <a href="{{ route('users.index') }}"
                                       class="mt-1 text-[13px] font-semibold text-brand hover:underline">Reset filter</a>
                                @else
                                    <p class="text-[14px] font-semibold text-ink">Belum ada akun pengguna</p>
                                    <p class="text-[13px] text-muted">Klik tombol &ldquo;+ Tambah Akun&rdquo; untuk membuat akun baru.</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Footer / pagination --}}
    {{ $users->links('vendor.pagination.mudalaya', ['totalLabel' => 'Akun']) }}
</div>

{{-- Mobile pagination --}}
<div class="sm:hidden mt-4">
    {{ $users->links('vendor.pagination.mudalaya', ['totalLabel' => 'Akun']) }}
</div>

{{-- Mobile FAB: + Tambah Akun --}}
<a href="{{ route('users.create') }}"
   class="sm:hidden fixed bottom-[88px] right-5 z-40 inline-flex min-h-[52px] items-center gap-2 rounded-[26px] bg-brand px-6 text-[14px] font-semibold text-white shadow-[0_6px_16px_rgba(20,32,26,0.25)] transition hover:bg-brand-dark active:scale-95"
   aria-label="Tambah akun baru">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
    Tambah Akun
</a>

@endsection
