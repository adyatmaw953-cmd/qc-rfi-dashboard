@props([
    'name' => '',
    'value' => '',
    'label' => '',
    'checked' => false,
    'id' => null,
])

@php
    $checked = (bool) $checked;
    $cleanValue = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) $value);
    $cleanName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) $name);
    $inputId = $id ?? ($cleanName . '_' . strtolower($cleanValue));
    $activeClasses = 'border-2 border-brand bg-[#E9EFE5] font-semibold text-ink';
    $inactiveClasses = 'border border-[#8C978B] bg-white font-medium text-ink';
@endphp

<label for="{{ $inputId }}"
       {{ $attributes->merge(['class' => 'checkbox-pill inline-flex min-h-[44px] cursor-pointer items-center justify-center gap-2 rounded-[22px] px-[14px] text-[13px] transition hover:bg-paper-soft focus-within:ring-2 focus-within:ring-brand focus-within:ring-offset-1 has-[:checked]:border-2 has-[:checked]:border-brand has-[:checked]:bg-[#E9EFE5] has-[:checked]:font-semibold ' . ($checked ? $activeClasses : $inactiveClasses)]) }}>
    <input
        type="checkbox"
        id="{{ $inputId }}"
        name="{{ $name }}"
        value="{{ $value }}"
        @checked($checked)
        class="h-[18px] w-[18px] rounded border-[#8C978B] accent-brand text-brand focus:ring-brand focus:outline-none"
    >
    <span>{{ $label !== '' ? $label : $slot }}</span>
</label>
