@extends('layouts.module', [
    'active' => 'users',
    'pageTitle' => 'Edit Akun Pengguna',
    'backUrl' => route('users.index'),
    'backLabel' => 'Kelola Akun Pengguna',
])

@section('content')

@php
    $roleOptions = [
        'admin' => [
            'title' => 'Super Admin',
            'chip_bg' => 'bg-[#E3EEFB]',
            'chip_fg' => 'text-[#1B4C8C]',
            'desc' => 'Akses penuh, termasuk kelola akun dan hapus data.',
        ],
        'qc_qa' => [
            'title' => 'QC / QA',
            'chip_bg' => 'bg-[#E1F0E2]',
            'chip_fg' => 'text-[#1F5B2A]',
            'desc' => 'Review RFI, verifikasi temuan, serta kelola NCR dan MDR.',
        ],
        'production' => [
            'title' => 'Production',
            'chip_bg' => 'bg-[#FCE8D8]',
            'chip_fg' => 'text-[#8F3F08]',
            'desc' => 'Ajukan RFI Preparation, Fabrication, Finishing dan isi action plan.',
        ],
        'warehouse' => [
            'title' => 'Warehouse',
            'chip_bg' => 'bg-[#FBF0C9]',
            'chip_fg' => 'text-[#6B4E00]',
            'desc' => 'Ajukan RFI Incoming Material.',
        ],
        'ppic' => [
            'title' => 'PPIC',
            'chip_bg' => 'bg-[#F3E8FF]',
            'chip_fg' => 'text-[#6B21A8]',
            'desc' => 'Ajukan RFI Vendor.',
        ],
    ];
@endphp

<div class="mx-auto max-w-2xl">
    {{-- Subheading --}}
    <div class="mb-4">
        <span class="text-[13px] font-medium text-brand">PT Mudalaya</span>
        <h2 class="text-[20px] sm:text-[24px] font-bold text-ink leading-tight">Edit Akun Pengguna</h2>
    </div>

    <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-4 sm:space-y-5">
        @csrf
        @method('PUT')

        {{-- 1. Kartu Data Akun --}}
        <section class="rounded-2xl border border-line bg-white p-5 sm:p-6" aria-labelledby="heading-data-akun">
            <h3 id="heading-data-akun" class="mb-3.5 text-[16px] font-semibold text-ink">Data akun</h3>
            <div class="space-y-4">
                {{-- Nama --}}
                <div>
                    <label for="name" class="block text-[14px] font-semibold text-ink">
                        Nama Lengkap <span class="text-alert">*</span>
                    </label>
                    <input type="text"
                           id="name"
                           name="name"
                           value="{{ old('name', $user->name) }}"
                           required
                           placeholder="Contoh: Budi Santoso"
                           class="mt-1.5 w-full min-h-[48px] rounded-[10px] border @error('name') border-alert @else border-[#8C978B] sm:border-line @enderror bg-white px-3.5 text-[14px] text-ink placeholder:text-muted focus:border-brand focus:outline-none">
                    @error('name')
                        <p class="mt-1 text-[12px] font-medium text-alert">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-[14px] font-semibold text-ink">
                        Email <span class="text-alert">*</span>
                    </label>
                    <input type="email"
                           id="email"
                           name="email"
                           value="{{ old('email', $user->email) }}"
                           required
                           placeholder="nama@perusahaan.com"
                           class="mt-1.5 w-full min-h-[48px] rounded-[10px] border @error('email') border-alert @else border-[#8C978B] sm:border-line @enderror bg-white px-3.5 text-[14px] text-ink placeholder:text-muted focus:border-brand focus:outline-none">
                    @error('email')
                        <p class="mt-1 text-[12px] font-medium text-alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        {{-- 2. Kartu Role Akun --}}
        <section class="rounded-2xl border border-line bg-white p-5 sm:p-6" aria-labelledby="heading-role-akun">
            <h3 id="heading-role-akun" class="mb-3.5 text-[16px] font-semibold text-ink">
                Role akun <span class="text-alert">*</span>
            </h3>

            <fieldset class="space-y-2.5">
                <legend class="sr-only">Pilih Role Akun</legend>
                @foreach ($roleOptions as $roleKey => $item)
                    <label class="flex items-start gap-3 p-3.5 sm:p-4 rounded-xl border cursor-pointer transition border-[#8C978B] sm:border-line bg-white hover:bg-paper-soft has-[:checked]:border-2 has-[:checked]:border-brand has-[:checked]:bg-[#E9EFE5]">
                        <input type="radio"
                               name="role"
                               value="{{ $roleKey }}"
                               required
                               @checked(old('role', $user->role) === $roleKey)
                               class="mt-1 h-4.5 w-4.5 shrink-0 accent-brand">
                        <span class="leading-snug flex-1">
                            <span class="flex items-center gap-2 flex-wrap">
                                <b class="text-[15px] font-semibold text-ink">{{ $item['title'] }}</b>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $item['chip_bg'] }} {{ $item['chip_fg'] }}">
                                    {{ $item['title'] }}
                                </span>
                            </span>
                            <span class="mt-1 block text-[13px] font-normal text-muted">
                                {{ $item['desc'] }}
                            </span>
                        </span>
                    </label>
                @endforeach
            </fieldset>
            @error('role')
                <p class="mt-2 text-[12px] font-medium text-alert">{{ $message }}</p>
            @enderror
        </section>

        {{-- 3. Kartu Reset Kata Sandi (Opsional) --}}
        <section class="rounded-2xl border border-line bg-white p-5 sm:p-6" aria-labelledby="heading-password-akun">
            <h3 id="heading-password-akun" class="mb-3.5 text-[16px] font-semibold text-ink">Kata sandi</h3>
            <div>
                <label for="password" class="block text-[14px] font-semibold text-ink">
                    Reset Kata Sandi <span class="text-[13px] font-normal text-muted">(Opsional)</span>
                </label>
                <div class="relative mt-1.5">
                    <input type="password"
                           id="password"
                           name="password"
                           placeholder="Biarkan kosong jika tidak ingin mengubah kata sandi"
                           class="w-full min-h-[48px] rounded-[10px] border @error('password') border-alert @else border-[#8C978B] sm:border-line @enderror bg-white px-3.5 pr-12 text-[14px] text-ink placeholder:text-muted focus:border-brand focus:outline-none">
                    <button type="button"
                            onclick="togglePasswordVisibility('password', this)"
                            class="absolute right-1.5 top-1.5 flex h-[36px] w-[36px] items-center justify-center text-muted hover:text-ink focus:outline-none"
                            aria-label="Tampilkan kata sandi">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                <p class="mt-1.5 text-[12px] text-muted">Kosongkan jika tidak ingin mengubah kata sandi. Jika diisi, minimal 8 karakter.</p>
                @error('password')
                    <p class="mt-1 text-[12px] font-medium text-alert">{{ $message }}</p>
                @enderror
            </div>
        </section>

        {{-- Action buttons --}}
        <div class="flex items-center gap-3 pt-2">
            <a href="{{ route('users.index') }}"
               class="flex-1 sm:flex-initial inline-flex min-h-[48px] items-center justify-center rounded-[12px] border border-[#8C978B] bg-white px-6 text-[14px] font-semibold text-ink transition hover:bg-paper-soft">
                Batal
            </a>
            <button type="submit"
                    class="flex-[2] sm:flex-initial inline-flex min-h-[48px] sm:min-h-[50px] items-center justify-center rounded-[12px] bg-brand px-8 text-[15px] font-semibold text-white shadow-xs transition hover:bg-brand-dark">
                Simpan Perubahan
            </button>
        </div>
    </form>
</div>

<script>
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    btn.setAttribute('aria-label', isPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    btn.innerHTML = isPassword
        ? '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
        : '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
}
</script>

@endsection
