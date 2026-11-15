@props([
    'name' => '',
    'value' => '',
    'label' => '',
    'description' => null,
    'checked' => false,
    'required' => false,
    'id' => null,
])

@php
    $checked = (bool) $checked;
    $cleanValue = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) $value);
    $inputId = $id ?? ($name . '_' . strtolower($cleanValue));
    $activeClasses = 'border-2 border-brand bg-[#E9EFE5] font-semibold text-ink';
    $inactiveClasses = 'border border-[#8C978B] bg-white font-medium text-ink';
@endphp

<label for="{{ $inputId }}"
       {{ $attributes->merge(['class' => 'choice-card flex min-h-[48px] cursor-pointer items-start gap-3 rounded-[12px] p-[12px_14px] transition hover:bg-paper-soft has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-brand has-[:focus-visible]:outline-offset-2 ' . ($checked ? $activeClasses : $inactiveClasses)]) }}>
    <input
        type="radio"
        id="{{ $inputId }}"
        name="{{ $name }}"
        value="{{ $value }}"
        @checked($checked)
        @required($required)
        aria-description="Klik lagi untuk membatalkan pilihan"
        class="choice-card-radio mt-0.5 h-[18px] w-[18px] shrink-0 accent-brand text-brand focus:outline-none"
    >
    <div class="flex-1">
        <span class="block text-[14px] leading-tight">{{ $label !== '' ? $label : $slot }}</span>
        @if ($description)
            <span class="mt-0.5 block text-[12px] text-muted font-normal leading-normal">{{ $description }}</span>
        @endif
    </div>
</label>
