@props([
    'label',
    'value' => 0,
    'status' => 'open',
    'subtext' => null,
])

<div class="rounded-2xl border border-line bg-white p-3.5 sm:p-[22px]">
    <div class="flex items-center justify-between gap-1.5 sm:gap-2">
        <span class="text-[12px] sm:text-[13px] text-muted truncate">{{ $label }}</span>
        <div class="shrink-0">
            <x-status-chip :status="$status" class="!px-2 !py-0.5 !text-[10px] sm:!px-[10px] sm:!py-[3px] sm:!text-[12px]" />
        </div>
    </div>
    <div class="mt-1 sm:mt-4 font-mono text-[28px] sm:text-[44px] font-semibold leading-none text-ink tabular-nums"
         @if (is_numeric($value)) data-count-to="{{ $value }}" @endif>
        {{ $value }}
    </div>
    @if ($subtext)
        <p class="hidden sm:block mt-2 text-[12px] text-muted">{{ $subtext }}</p>
    @endif
</div>
