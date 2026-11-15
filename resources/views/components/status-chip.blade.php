@props([
    'status' => 'open',
    'label' => null,
])

@php
    $normalized = strtolower(trim((string) $status));
    $chip = match ($normalized) {
        'open' => [
            'bg' => 'bg-[#E3EEFB]',
            'fg' => 'text-[#1B4C8C]',
            'dot' => 'bg-[#8DB6E8]',
            'label' => 'Open',
        ],
        'nc' => [
            'bg' => 'bg-[#FCE8D8]',
            'fg' => 'text-[#8F3F08]',
            'dot' => 'bg-[#E07B2E]',
            'label' => 'NC',
        ],
        'close', 'closed' => [
            'bg' => 'bg-[#E1F0E2]',
            'fg' => 'text-[#1F5B2A]',
            'dot' => 'bg-[#1F5B2A]',
            'label' => 'Closed',
        ],
        'waiting_verification', 'menunggu verifikasi' => [
            'bg' => 'bg-[#FCE8D8]',
            'fg' => 'text-[#8F3F08]',
            'dot' => 'bg-[#E07B2E]',
            'label' => 'Menunggu verifikasi',
        ],
        'perlu_action_plan', 'perlu action plan' => [
            'bg' => 'bg-[#FCE8D8]',
            'fg' => 'text-[#8F3F08]',
            'dot' => 'bg-[#E07B2E]',
            'label' => 'Perlu action plan',
        ],
        'ca closed', 'ca_closed' => [
            'bg' => 'bg-[#FBF0C9]',
            'fg' => 'text-[#6B4E00]',
            'dot' => 'bg-[#D4A000]',
            'label' => 'CA Closed',
        ],
        'terlambat', 'overdue' => [
            'bg' => 'bg-[#FEE2E2]',
            'fg' => 'text-[#991B1B]',
            'dot' => 'bg-[#EF4444]',
            'label' => 'Terlambat',
        ],
        'jatuh_tempo_hari_ini', 'due_today', 'jatuh tempo hari ini' => [
            'bg' => 'bg-[#FEF3C7]',
            'fg' => 'text-[#92400E]',
            'dot' => 'bg-[#F59E0B]',
            'label' => 'Jatuh tempo hari ini',
        ],
        default => [
            'bg' => 'bg-[#E3EEFB]',
            'fg' => 'text-[#1B4C8C]',
            'dot' => 'bg-[#8DB6E8]',
            'label' => ucfirst((string) $status),
        ],
    };

    $displayLabel = $label ?? $chip['label'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-[10px] py-[3px] text-[12px] font-semibold ' . $chip['bg'] . ' ' . $chip['fg']]) }}>
    <span class="inline-block h-2 w-2 rounded-full {{ $chip['dot'] }}" aria-hidden="true"></span>
    <span>{{ $displayLabel }}</span>
</span>
