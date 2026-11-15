@props([
    'name',
    'value' => 'open',
    'label' => '',
])

@php
    $current = strtolower(trim((string) $value));
    if (! in_array($current, ['open', 'close', 'na'], true)) {
        $current = 'open';
    }
@endphp

<div role="radiogroup" aria-label="{{ $label }}" class="flex w-full min-[820px]:max-w-[270px] gap-1.5">
    {{-- Open option --}}
    <label class="flex flex-1 min-h-[44px] items-center justify-center gap-1.5 rounded-[10px] px-2 text-[13px] cursor-pointer transition border border-[#8C978B] bg-white font-medium text-ink has-[:checked]:border-2 has-[:checked]:border-[#8DB6E8] has-[:checked]:bg-[#E3EEFB] has-[:checked]:text-[#1B4C8C] has-[:checked]:font-semibold has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-[#366F3A] has-[:focus-visible]:outline-offset-2">
        <input type="radio" name="{{ $name }}" value="open" @checked($current === 'open') class="h-4 w-4 shrink-0 accent-[#366F3A] focus:outline-none">
        <span>Open</span>
    </label>

    {{-- Close option --}}
    <label class="flex flex-1 min-h-[44px] items-center justify-center gap-1.5 rounded-[10px] px-2 text-[13px] cursor-pointer transition border border-[#8C978B] bg-white font-medium text-ink has-[:checked]:border-2 has-[:checked]:border-[#1F5B2A] has-[:checked]:bg-[#E1F0E2] has-[:checked]:text-[#1F5B2A] has-[:checked]:font-semibold has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-[#366F3A] has-[:focus-visible]:outline-offset-2">
        <input type="radio" name="{{ $name }}" value="close" @checked($current === 'close') class="h-4 w-4 shrink-0 accent-[#366F3A] focus:outline-none">
        <span>Close</span>
    </label>

    {{-- N/A option --}}
    <label class="flex flex-1 min-h-[44px] items-center justify-center gap-1.5 rounded-[10px] px-2 text-[13px] cursor-pointer transition border border-[#8C978B] bg-white font-medium text-ink has-[:checked]:border-2 has-[:checked]:border-[#8C978B] has-[:checked]:bg-[#EEEBE1] has-[:checked]:text-[#3F4A41] has-[:checked]:font-semibold has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-[#366F3A] has-[:focus-visible]:outline-offset-2">
        <input type="radio" name="{{ $name }}" value="na" @checked($current === 'na') class="h-4 w-4 shrink-0 accent-[#366F3A] focus:outline-none">
        <span>N/A</span>
    </label>
</div>
