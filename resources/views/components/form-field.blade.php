@props([
    'id',
    'label',
    'required' => false,
    'error' => null,
    'hint' => null,
    'hintId' => null,
    'span' => false,
])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-[6px]' . ($span ? ' sm:col-span-2' : '')]) }}>
    <label for="{{ $id }}" class="text-[14px] font-semibold text-ink">
        {{ $label }}@if ($required)<span aria-hidden="true" class="text-alert"> *</span>@endif
    </label>

    {{ $slot }}

    @if ($error)
        <p id="{{ $id }}-error" class="text-[13px] font-medium text-[#8F3F08]" role="alert">
            {{ $error }}
        </p>
    @elseif ($hint)
        <p @if($hintId) id="{{ $hintId }}" @endif class="text-[12px] text-muted">
            {{ $hint }}
        </p>
    @endif
</div>
