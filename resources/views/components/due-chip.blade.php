@props([
    'date' => null,
    'dueDate' => null,
    'done' => false,
    'allClosed' => false,
    'label' => null,
    'simple' => false,
])

@php
    use Carbon\Carbon;

    $tz = 'Asia/Jakarta';
    $targetDate = $date ?? $dueDate;
    $isDone = (bool) ($done || $allClosed);

    $chipLabel = '';
    $classes = '';
    $dotClass = '';

    if ($isDone) {
        $chipLabel = $label ?? ($allClosed ? 'Semua closed' : 'Selesai');
        $classes = 'bg-[#E1F0E2] text-[#1F5B2A]';
        $dotClass = 'bg-[#1F5B2A]';
    } elseif ($targetDate) {
        $today = Carbon::now($tz)->startOfDay();
        $due = Carbon::parse($targetDate, $tz)->startOfDay();
        $diffDays = (int) $today->diffInDays($due, false);

        if ($simple) {
            if ($diffDays < 0) {
                $chipLabel = $label ?? 'Terlambat';
                $classes = 'bg-[#FEE2E2] text-[#991B1B]';
                $dotClass = 'bg-[#EF4444]';
            } elseif ($diffDays === 0) {
                $chipLabel = $label ?? 'Jatuh tempo hari ini';
                $classes = 'bg-[#FEF3C7] text-[#92400E]';
                $dotClass = 'bg-[#F59E0B]';
            } else {
                $chipLabel = $label ?? '';
            }
        } else {
            if ($diffDays < 0) {
                $days = abs($diffDays);
                $chipLabel = $label ?? "Terlambat {$days} hari";
                $classes = 'bg-[#FCE8D8] text-[#8F3F08]';
                $dotClass = 'bg-[#E07B2E]';
            } elseif ($diffDays === 0) {
                $chipLabel = $label ?? 'Jatuh tempo hari ini';
                $classes = 'bg-[#FCE8D8] text-[#8F3F08]';
                $dotClass = 'bg-[#E07B2E]';
            } else {
                $chipLabel = $label ?? "{$diffDays} hari lagi";
                $classes = 'bg-[#EEEBE1] text-[#3F4A41]';
                $dotClass = 'bg-[#8C978B]';
            }
        }
    }
@endphp

@if ($chipLabel !== '')
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-[10px] py-[3px] text-[12px] font-semibold ' . $classes]) }}>
        <span class="inline-block h-2 w-2 rounded-full {{ $dotClass }}" aria-hidden="true"></span>
        <span>{{ $chipLabel }}</span>
    </span>
@endif
