@props([
    'total' => 16,
    'reports' => [],
    'completed' => null,
])

@php
    $total = (int) $total;
    $reportKeys = ! empty($reports) ? array_keys($reports) : array_keys(config('mudalaya.mdr_reports', []));
    if (empty($total)) {
        $total = count($reportKeys);
    }
    if ($completed !== null) {
        $completed = (int) $completed;
    } else {
        // Calculate initial completed count from old('statuses') or default
        $completed = 0;
        foreach ($reportKeys as $key) {
            $val = old("statuses.{$key}", 'open');
            if (in_array($val, ['close', 'na'], true)) {
                $completed++;
            }
        }
    }
    $percent = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
    $isClose = ($total > 0 && $completed === $total);
@endphp

<div class="rounded-[16px] border border-[#E1DED3] bg-white p-5 shadow-xs">
    <div class="flex flex-col gap-3 min-[820px]:flex-row min-[820px]:items-center min-[820px]:justify-between">
        <div class="text-[14px] text-ink">
            Progres report: <strong class="font-semibold text-ink" data-mdr-summary-text>{{ $completed }} dari {{ $total }} report selesai</strong>
            <span class="text-[13px] text-muted">(Close atau N/A)</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-[13px] font-medium text-muted">Status:</span>
            <span id="mdr-summary-chip-gray" class="{{ $completed === 0 ? 'inline-flex' : 'hidden' }} items-center gap-1.5 rounded-full bg-[#EEEBE1] px-3 py-1 text-[12px] font-semibold text-[#5B665E]">
                <span class="h-1.5 w-1.5 rounded-full bg-[#B9B5A6]"></span>
                Open
            </span>
            <span id="mdr-summary-chip-open" class="{{ ($completed > 0 && ! $isClose) ? 'inline-flex' : 'hidden' }} items-center gap-1.5 rounded-full bg-[#E3EEFB] px-3 py-1 text-[12px] font-semibold text-[#1B4C8C]">
                <span class="h-1.5 w-1.5 rounded-full bg-[#8DB6E8]"></span>
                Open
            </span>
            <span id="mdr-summary-chip-close" class="{{ $isClose ? 'inline-flex' : 'hidden' }} items-center gap-1.5 rounded-full bg-[#E1F0E2] px-3 py-1 text-[12px] font-semibold text-[#1F5B2A]">
                <span class="h-1.5 w-1.5 rounded-full bg-[#1F5B2A]"></span>
                Close
            </span>
        </div>
    </div>

    <div class="mt-4 flex items-center gap-3.5">
        <div class="h-2 flex-1 rounded-full bg-[#EEEBE1] overflow-hidden" aria-hidden="true">
            <div data-mdr-progress-bar class="h-full rounded-full bg-[#366F3A] transition-all duration-300" style="width: {{ $percent }}%"></div>
        </div>
        <span class="shrink-0 font-mono text-[13px] font-semibold text-ink" data-mdr-fraction>
            {{ $completed }} dari {{ $total }}
        </span>
    </div>
</div>
