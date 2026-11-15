@extends('layouts.module', ['active' => '', 'pageTitle' => 'Notifikasi'])

@section('header_action')
    <x-create-rfi-button />
@endsection

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
        {{-- Filter Buttons --}}
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('notifications.index') }}"
               class="inline-flex min-h-[44px] items-center gap-2 rounded-[10px] px-[18px] text-sm transition {{ $currentFilter !== 'unread' ? 'bg-ink font-semibold text-white' : 'border border-line bg-white font-medium text-ink hover:bg-paper/50' }}">
                <span>Semua</span>
                <span class="font-mono text-xs opacity-80">{{ $totalCount }}</span>
            </a>
            <a href="{{ route('notifications.index', ['filter' => 'unread']) }}"
               class="inline-flex min-h-[44px] items-center gap-2 rounded-[10px] px-[18px] text-sm transition {{ $currentFilter === 'unread' ? 'bg-ink font-semibold text-white' : 'border border-line bg-white font-medium text-ink hover:bg-paper/50' }}">
                <span>Belum dibaca</span>
                <span class="font-mono text-xs opacity-80">{{ $unreadCount }}</span>
            </a>
        </div>

        {{-- Mark all as read --}}
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit"
                    class="inline-flex min-h-[44px] items-center rounded-[10px] border border-line bg-white px-4 text-sm font-semibold text-brand transition hover:bg-brand-pale/50">
                Tandai semua dibaca
            </button>
        </form>
    </div>

    {{-- Notification List Card --}}
    <div class="overflow-hidden rounded-2xl border border-line bg-white shadow-sm">
        <div class="border-b border-line px-[22px] py-[18px]">
            <h2 class="text-[16px] font-semibold text-ink">Untuk akun ini</h2>
        </div>

        <div class="divide-y divide-line">
            @forelse ($notifications as $notification)
                <x-notification-row :notification="$notification" />
            @empty
                <div class="flex flex-col items-center justify-center p-12 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-paper text-muted mb-3" aria-hidden="true">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
                            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
                        </svg>
                    </span>
                    <p class="text-[15px] font-semibold text-ink">Belum ada notifikasi.</p>
                    <p class="mt-1 text-[13px] text-muted">
                        {{ $currentFilter === 'unread' ? 'Semua notifikasi sudah Anda baca.' : 'Notifikasi baru terkait RFI dan QC Finding akan muncul di sini.' }}
                    </p>
                </div>
            @endforelse
        </div>
    </div>

    @if ($notifications->hasPages())
        <div class="mt-4">
            {{ $notifications->links('pagination.custom') }}
        </div>
    @endif
@endsection
