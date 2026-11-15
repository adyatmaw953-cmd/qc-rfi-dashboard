@props([
    'label',
    'span' => false,
    'value' => null,
])

@php
    $spanClass = $span ? 'sm:col-span-2' : '';
    $slotContent = trim((string) $slot);
    $hasValue = filled($value) || $slotContent !== '';
@endphp

<div {{ $attributes->merge(['class' => $spanClass]) }}>
    <dt class="text-[13px] font-medium text-muted">{{ $label }}</dt>
    <dd class="mt-1 text-[15px] font-medium text-ink">
        @if ($hasValue)
            {{ filled($value) ? $value : $slot }}
        @else
            <span class="text-muted font-normal">Belum diisi</span>
        @endif
    </dd>
</div>

