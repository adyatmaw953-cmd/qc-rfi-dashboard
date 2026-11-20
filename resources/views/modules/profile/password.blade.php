@extends('layouts.module', [
    'active' => '',
    'pageTitle' => 'Ganti Kata Sandi',
])

@section('content')

<div class="mx-auto max-w-xl">
    <div class="rounded-2xl border border-line bg-white p-6 md:p-8">
        <div class="mb-6 border-b border-line pb-4">
            <h2 class="text-[18px] font-semibold text-ink">Perbarui Kata Sandi Akun</h2>
            <p class="mt-1 text-[13px] text-muted">Pastikan kata sandi baru Anda minimal 8 karakter dan sulit ditebak orang lain.</p>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Password Lama --}}
            <div>
                <label for="current_password" class="block text-[13px] font-semibold text-ink">
                    Kata Sandi Saat Ini <span class="text-alert">*</span>
                </label>
                <input type="password"
                       id="current_password"
                       name="current_password"
                       required
                       autocomplete="current-password"
                       placeholder="Masukkan kata sandi lama Anda"
                       class="mt-1.5 w-full min-h-[44px] rounded-[10px] border @error('current_password') border-alert @else border-line @enderror bg-white px-4 text-[14px] text-ink placeholder:text-muted focus:border-brand focus:outline-none">
                @error('current_password')
                    <p class="mt-1 text-[12px] font-medium text-alert">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password Baru --}}
            <div>
                <label for="password" class="block text-[13px] font-semibold text-ink">
                    Kata Sandi Baru <span class="text-alert">*</span>
                </label>
                <input type="password"
                       id="password"
                       name="password"
                       required
                       autocomplete="new-password"
                       placeholder="Minimal 8 karakter"
                       class="mt-1.5 w-full min-h-[44px] rounded-[10px] border @error('password') border-alert @else border-line @enderror bg-white px-4 text-[14px] text-ink placeholder:text-muted focus:border-brand focus:outline-none">
                @error('password')
                    <p class="mt-1 text-[12px] font-medium text-alert">{{ $message }}</p>
                @enderror
            </div>

            {{-- Konfirmasi Password Baru --}}
            <div>
                <label for="password_confirmation" class="block text-[13px] font-semibold text-ink">
                    Konfirmasi Kata Sandi Baru <span class="text-alert">*</span>
                </label>
                <input type="password"
                       id="password_confirmation"
                       name="password_confirmation"
                       required
                       autocomplete="new-password"
                       placeholder="Ulangi kata sandi baru"
                       class="mt-1.5 w-full min-h-[44px] rounded-[10px] border @error('password_confirmation') border-alert @else border-line @enderror bg-white px-4 text-[14px] text-ink placeholder:text-muted focus:border-brand focus:outline-none">
                @error('password_confirmation')
                    <p class="mt-1 text-[12px] font-medium text-alert">{{ $message }}</p>
                @enderror
            </div>

            {{-- Action button --}}
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="submit"
                        class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] bg-brand px-6 text-[14px] font-semibold text-white transition hover:bg-brand-dark">
                    Simpan Kata Sandi Baru
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
