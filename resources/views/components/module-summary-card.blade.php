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
                @endphp
                <div class="min-w-0 flex-1">
                    <div class="font-mono text-[24px] font-semibold leading-[1.2] text-ink truncate tabular-nums"
                         @if ($countTo !== null)
                             data-count-to="{{ $countTo }}"
                             @if ($decimals > 0) data-decimals="{{ $decimals }}" @endif
                             @if ($prefix) data-prefix="{{ $prefix }}" @endif
                             @if ($suffix) data-suffix="{{ $suffix }}" @endif
                         @endif
                    >
                        {{ $val }}
                    </div>
                    <div class="mt-0.5 text-[12px] font-normal leading-[1.35] text-muted">
                        {{ $stat['label'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

