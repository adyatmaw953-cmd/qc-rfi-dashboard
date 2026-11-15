@props([
    'notification',
])

@php
    $type = $notification->data['type'] ?? $notification->type;
    $title = $notification->data['title'] ?? 'Notifikasi';
    $body = $notification->data['body'] ?? '';
    $rfiNo = $notification->data['rfi_no'] ?? null;
    $url = $notification->data['url'] ?? null;

    $meta = \App\Support\NotificationHelper::meta($type, $title, $url);
    $iconName = $meta['icon'];
    $chip = $meta['chip'];

    $isUnread = ! $notification->read_at;
    $timeFormatted = \App\Support\NotificationHelper::formatTime($notification->created_at);
    $timeTitle = $notification->created_at->copy()->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i:s') . ' WIB';
@endphp

<form method="POST" action="{{ route('notifications.read', ['id' => $notification->id]) }}" class="block w-full">
    @csrf
    <button type="submit"
            class="flex min-h-[44px] w-full flex-col items-start gap-4 p-[18px_22px] text-left transition hover:bg-paper/50 focus-visible:outline-2 focus-visible:outline-brand sm:flex-row sm:items-center sm:gap-4 {{ $isUnread ? 'bg-paper-soft' : 'bg-white' }}">

        {{-- Titik status unread (lebar tetap 10px untuk menjaga alignment) --}}
        <div class="flex h-2.5 w-2.5 shrink-0 items-center justify-center pt-0">
            @if ($isUnread)
                <span class="h-2.5 w-2.5 rounded-full bg-brand" aria-hidden="true"></span>
                <span class="sr-only">Belum dibaca</span>
            @endif
        </div>

        {{-- Icon container 40x40 rounded-10 bg brand-pale --}}
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-brand-pale text-brand" aria-hidden="true">
            <x-module-menu-icon :name="$iconName" class="h-5 w-5" />
        </div>

        {{-- Main body: Judul + Chip, Deskripsi, RFI No --}}
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-[15px] text-ink {{ $isUnread ? 'font-semibold' : 'font-medium' }}">
                    {{ $title }}
                </span>
                <span class="inline-flex items-center rounded-full px-2.5 py-[3px] text-[12px] font-semibold {{ $chip['class'] }}">
                    {{ $chip['label'] }}
                </span>
            </div>

            @if ($body)
                <p class="mt-1 text-[13px] font-normal leading-relaxed text-muted">
                    {{ $body }}
                </p>
            @endif

            @if ($rfiNo)
                <p class="mt-1 font-mono text-[12px] font-normal text-muted">
                    {{ $rfiNo }}
                </p>
            @endif
        </div>

        {{-- Waktu di kanan --}}
        <time datetime="{{ $notification->created_at->toIso8601String() }}"
              title="{{ $timeTitle }}"
              class="shrink-0 text-left text-[13px] font-normal whitespace-nowrap text-muted sm:text-right">
            {{ $timeFormatted }}
        </time>
    </button>
</form>

