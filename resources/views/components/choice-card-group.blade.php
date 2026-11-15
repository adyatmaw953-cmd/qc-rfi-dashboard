@props([
    'name' => '',
    'options' => [],
    'selected' => null,
    'required' => false,
    'grid' => 'sm:grid-cols-2',
])

@php
    $gridClass = match ($grid) {
        'grid-cols-1 sm:grid-cols-3' => 'grid-cols-1 sm:grid-cols-3',
        'grid-cols-1 sm:grid-cols-2' => 'grid-cols-1 sm:grid-cols-2',
        'sm:grid-cols-3' => 'grid-cols-1 sm:grid-cols-3',
        'sm:grid-cols-2' => 'grid-cols-1 sm:grid-cols-2',
        default => $grid,
    };
@endphp

<div {{ $attributes->merge(['class' => 'grid gap-3 ' . $gridClass]) }}>
    @if (!empty($options))
        @foreach ($options as $opt)
            @php
                $val = is_array($opt) ? ($opt['value'] ?? '') : $opt;
                $optLabel = is_array($opt) ? ($opt['label'] ?? $val) : $opt;
                $desc = is_array($opt) ? ($opt['description'] ?? null) : null;
                $isChecked = old($name, $selected) == $val;
            @endphp
            <x-choice-card
                :name="$name"
                :value="$val"
                :label="$optLabel"
                :description="$desc"
                :checked="$isChecked"
                :required="$required"
            />
        @endforeach
    @else
        {{ $slot }}
    @endif
</div>
