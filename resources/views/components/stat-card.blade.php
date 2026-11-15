@props([
    'label',
    'value' => 0,
    'status' => 'open',
    'subtext' => null,
])

<div class="rounded-2xl border border-line bg-white p-[22px]">
    <div class="flex items-center justify-between gap-2">
        <span class="text-[13px] text-muted">{{ $label }}</span>
        <x-status-chip :status="$status" />
    </div>
    <div class="mt-4 font-mono text-[44px] font-semibold leading-none text-ink tabular-nums"
         @if (is_numeric($value)) data-count-to="{{ $value }}" @endif>
        {{ $value }}
    </div>
    @if ($subtext)
        <p class="mt-2 text-[12px] text-muted">{{ $subtext }}</p>
    @endif
</div>
