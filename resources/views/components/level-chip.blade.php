@props([
    'level' => 'Major',
    'label' => null,
])

@php
    $normalized = strtolower(trim((string) $level));
    $chip = match ($normalized) {
        'major' => [
            'bg' => 'bg-[#FCE8D8]',
            'fg' => 'text-[#8F3F08]',
            'dot' => 'bg-[#E07B2E]',
            'label' => 'Major',
        ],
        'minor' => [
            'bg' => 'bg-[#EEEBE1]',
            'fg' => 'text-[#3F4A41]',
            'dot' => 'bg-[#8C978B]',
            'label' => 'Minor',
        ],
        'near miss', 'near_miss', 'nearmiss' => [
            'bg' => 'bg-[#E3EEFB]',
            'fg' => 'text-[#1B4C8C]',
            'dot' => 'bg-[#8DB6E8]',
            'label' => 'Near Miss',
        ],
        default => [
            'bg' => 'bg-[#EEEBE1]',
            'fg' => 'text-[#3F4A41]',
            'dot' => 'bg-[#8C978B]',
            'label' => ucfirst((string) $level),
        ],
    };

    $displayLabel = $label ?? $chip['label'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-[10px] py-[3px] text-[12px] font-semibold ' . $chip['bg'] . ' ' . $chip['fg']]) }}>
    <span class="inline-block h-2 w-2 rounded-full {{ $chip['dot'] }}" aria-hidden="true"></span>
    <span>{{ $displayLabel }}</span>
</span>

