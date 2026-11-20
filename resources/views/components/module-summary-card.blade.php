    @props([
    'title' => '',
    'icon' => 'rfi',
    'detailUrl' => '#',
    'stats' => [], // list of ['label' => '...', 'value' => '...']
])

<div class="flex flex-col justify-between rounded-2xl border border-line bg-white p-5">
    <div>
        <div class="mb-4 flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-brand-pale text-brand" aria-hidden="true">
                <x-module-menu-icon :name="$icon" />
            </span>
            <b class="flex-1 text-[16px] font-semibold text-ink">{{ $title }}</b>
            @if ($detailUrl && $detailUrl !== '#')
                <a href="{{ $detailUrl }}" class="inline-flex min-h-[44px] items-center text-[13px] font-semibold text-brand transition hover:text-brand-dark">
                    Lihat detail &rarr;
                </a>
            @endif
        </div>

        <div class="flex items-start gap-3">
            @foreach ($stats as $stat)
                @php
                    $val = $stat['value'] ?? 0;
                    $rawLabel = (string) ($stat['label'] ?? '');
                    $labelLower = strtolower(trim($rawLabel));
                    $countTo = $stat['count_to'] ?? null;
                    $prefix = $stat['prefix'] ?? '';
                    $suffix = $stat['suffix'] ?? '';
                    $decimals = $stat['decimals'] ?? 0;

                    if ($countTo === null) {
                        if (is_numeric($val)) {
                            $countTo = (float) $val;
                        } elseif (is_string($val)) {
                            if (preg_match('/^(?:(Rp\s*))?([0-9]+(?:[\.,][0-9]+)?)\s*(.*)$/u', trim($val), $m)) {
                                $prefix = $m[1] ?: '';
                                $numStr = str_replace(',', '.', str_replace('.', '', $m[2]));
                                $countTo = (float) $numStr;
                                $suffix = ($m[3] !== '') ? ' ' . trim($m[3]) : '';
                            }
                        }
                    }

                    $chip = $stat['chip'] ?? match (true) {
                        str_contains($labelLower, 'open') || str_contains($labelLower, 'belum') => [
                            'bg' => 'bg-[#E3EEFB]',
                            'fg' => 'text-[#1B4C8C]',
                            'dot' => 'bg-[#8DB6E8]',
                        ],
                        str_contains($labelLower, 'nc cost') || str_contains($labelLower, 'cost') => [
                            'bg' => 'bg-[#E0E7FF]',
                            'fg' => 'text-[#3730A3]',
                            'dot' => 'bg-[#6366F1]',
                        ],
                        str_contains($labelLower, 'nc') => [
                            'bg' => 'bg-[#FCE8D8]',
                            'fg' => 'text-[#8F3F08]',
                            'dot' => 'bg-[#E07B2E]',
                        ],
                        str_contains($labelLower, 'close') => [
                            'bg' => 'bg-[#E1F0E2]',
                            'fg' => 'text-[#1F5B2A]',
                            'dot' => 'bg-[#1F5B2A]',
                        ],
                        str_contains($labelLower, 'terlambat') || str_contains($labelLower, 'overdue') => [
                            'bg' => 'bg-[#FEE2E2]',
                            'fg' => 'text-[#991B1B]',
                            'dot' => 'bg-[#EF4444]',
                        ],
                        str_contains($labelLower, 'verifikasi') || str_contains($labelLower, 'waiting') => [
                            'bg' => 'bg-[#FCE8D8]',
                            'fg' => 'text-[#8F3F08]',
                            'dot' => 'bg-[#E07B2E]',
                        ],
                        str_contains($labelLower, 'total') => [
                            'bg' => 'bg-[#F4F2EB]',
                            'fg' => 'text-[#5B665E]',
                            'dot' => 'bg-[#8C978B]',
                        ],
                        default => [
                            'bg' => 'bg-[#F4F2EB]',
                            'fg' => 'text-[#5B665E]',
                            'dot' => 'bg-[#8C978B]',
                        ],
                    };
                @endphp
                <div class="min-w-0 flex-1">
                    <div class="mb-1.5 flex items-center">
                        <span class="inline-flex max-w-full items-center gap-1 rounded-full px-2 py-0.5 text-[10px] sm:text-[11px] font-semibold {{ $chip['bg'] }} {{ $chip['fg'] }}" title="{{ $rawLabel }}">
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $chip['dot'] }}" aria-hidden="true"></span>
                            <span class="truncate">{{ $rawLabel }}</span>
                        </span>
                    </div>
                    <div class="font-mono text-[22px] sm:text-[24px] font-semibold leading-[1.2] text-ink truncate tabular-nums"
                         @if ($countTo !== null)
                             data-count-to="{{ $countTo }}"
                             @if ($decimals > 0) data-decimals="{{ $decimals }}" @endif
                             @if ($prefix) data-prefix="{{ $prefix }}" @endif
                             @if ($suffix) data-suffix="{{ $suffix }}" @endif
                         @endif
                    >
                        {{ $val }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

