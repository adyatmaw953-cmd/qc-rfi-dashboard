@extends('layouts.module', ['active' => 'mdr', 'pageTitle' => 'MDR Checklist'])

@section('header_action')
    <a href="{{ route('mdr-checklist.create') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] bg-brand px-6 text-[14px] font-semibold text-white transition hover:bg-brand-dark">
        + New MDR
    </a>
@endsection

@section('content')
    @php
        $currentStatus = $validated['status'] ?? null;
        $search = $validated['search'] ?? null;
    @endphp

    {{-- Top section: 3 Stat Cards + Monthly Chart --}}
    <div class="mb-6 grid grid-cols-1 min-[820px]:grid-cols-[1fr_1.6fr] gap-4">
        {{-- Kiri: 3 Kartu Statistik bertumpuk --}}
        <div class="flex flex-col gap-4">
            {{-- Card 1: Total MDR --}}
            <div class="flex flex-col justify-between rounded-2xl border border-line bg-white p-[18px_22px]">
                <div class="text-[13px] font-medium text-muted">Total MDR</div>
                <div class="mt-2 font-mono text-[34px] font-semibold leading-tight text-ink tabular-nums" data-count-to="{{ (int) ($mdrTotals['total'] ?? 0) }}">
                    {{ $mdrTotals['total'] }}
                </div>
                <div class="mt-1 text-[12px] text-muted">satu MDR per unit</div>
            </div>

            {{-- Card 2: MDR Open --}}
            <div class="flex flex-col justify-between rounded-2xl border border-line bg-white p-[18px_22px]">
                <div class="flex items-center justify-between">
                    <span class="text-[13px] font-medium text-muted">MDR Open</span>
                    <x-status-chip status="Open" />
                </div>
                <div class="mt-2 font-mono text-[34px] font-semibold leading-tight text-ink tabular-nums" data-count-to="{{ (int) ($mdrTotals['open'] ?? 0) }}">
                    {{ $mdrTotals['open'] }}
                </div>
                <div class="mt-1 text-[12px] text-muted">masih ada report yang belum selesai</div>
            </div>

            {{-- Card 3: MDR Close --}}
            <div class="flex flex-col justify-between rounded-2xl border border-line bg-white p-[18px_22px]">
                <div class="flex items-center justify-between">
                    <span class="text-[13px] font-medium text-muted">MDR Close</span>
                    <x-status-chip status="Close" label="Close" />
                </div>
                <div class="mt-2 font-mono text-[34px] font-semibold leading-tight text-ink tabular-nums" data-count-to="{{ (int) ($mdrTotals['close'] ?? 0) }}">
                    {{ $mdrTotals['close'] }}
                </div>
                <div class="mt-1 text-[12px] text-muted">semua report Close atau N/A</div>
            </div>
        </div>

        {{-- Kanan: Kartu MDR Dibuat per Bulan --}}
        <div class="flex flex-col justify-between rounded-2xl border border-line bg-white p-6">
            <div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-[16px] font-semibold text-ink">MDR Dibuat per Bulan</h2>
                        <p class="mt-0.5 text-[13px] text-muted">Open dibanding Close</p>
                    </div>
                    <div class="flex items-center gap-4 text-[13px] text-muted">
                        <span class="flex items-center gap-1.5">
                            <span class="inline-block h-2.5 w-2.5 rounded-[3px] bg-[#8DB6E8]" aria-hidden="true"></span>
                            Open
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="inline-block h-2.5 w-2.5 rounded-[3px] bg-[#1F5B2A]" aria-hidden="true"></span>
                            Close
                        </span>
                    </div>
                </div>

                <div class="mt-4">
                    <x-bar-chart
                        chart-id="chart-mdr"
                        title="Grafik batang MDR dibuat per bulan (Open vs Close)"
                        :months="$mdrChart['months']"
                        :series="$mdrChart['series']"
                        layout="grouped"
                        :show-values="true"
                    />
                </div>
            </div>
        </div>
    </div>

    {{-- Filter bar: Segmented status tabs + Search input --}}
    <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
        {{-- Segmented control --}}
        <nav aria-label="Filter status MDR" class="flex flex-wrap items-center gap-2">
            {{-- Tab Semua --}}
            <a href="{{ route('mdr-checklist.index', array_filter(['search' => $search])) }}"
               class="inline-flex min-h-[44px] items-center gap-2 rounded-[10px] border px-4 text-[14px] transition {{ empty($currentStatus) ? 'border-ink bg-ink font-semibold text-white' : 'border-line bg-white font-medium text-ink hover:bg-paper' }}"
               @if (empty($currentStatus)) aria-current="page" @endif>
                <span>Semua</span>
                <span class="font-mono text-[12px] opacity-80">{{ $statusCounts['all'] }}</span>
            </a>

            {{-- Tab Open --}}
            <a href="{{ route('mdr-checklist.index', array_filter(['status' => 'open', 'search' => $search])) }}"
               class="inline-flex min-h-[44px] items-center gap-2 rounded-[10px] border px-4 text-[14px] transition {{ $currentStatus === 'open' ? 'border-ink bg-ink font-semibold text-white' : 'border-line bg-white font-medium text-ink hover:bg-paper' }}"
               @if ($currentStatus === 'open') aria-current="page" @endif>
                <span>Open</span>
                <span class="font-mono text-[12px] opacity-80">{{ $statusCounts['open'] }}</span>
            </a>

            {{-- Tab Close --}}
            <a href="{{ route('mdr-checklist.index', array_filter(['status' => 'close', 'search' => $search])) }}"
               class="inline-flex min-h-[44px] items-center gap-2 rounded-[10px] border px-4 text-[14px] transition {{ $currentStatus === 'close' ? 'border-ink bg-ink font-semibold text-white' : 'border-line bg-white font-medium text-ink hover:bg-paper' }}"
               @if ($currentStatus === 'close') aria-current="page" @endif>
                <span>Close</span>
                <span class="font-mono text-[12px] opacity-80">{{ $statusCounts['close'] }}</span>
            </a>
        </nav>

        {{-- Search input --}}
        <form method="GET" action="{{ route('mdr-checklist.index') }}" class="relative w-full max-w-[340px]">
            @if ($currentStatus)
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.3-4.3"/>
                </svg>
            </div>
            <input
                type="text"
                id="search"
                name="search"
                value="{{ $search ?? '' }}"
                placeholder="Cari project atau unit"
                aria-label="Cari project atau unit"
                class="form-control pl-[42px] text-[14px] focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand"
            />
        </form>
    </div>

    {{-- Tabel dalam kartu --}}
    <div class="overflow-hidden rounded-2xl border border-line bg-white shadow-xs">
        <div class="overflow-x-auto">
            <table class="mdr-table w-full min-w-[850px] text-left text-[14px]">
                <thead class="bg-paper-soft text-[13px] font-semibold text-muted">
                    <tr>
                        <th class="px-5 py-3.5 whitespace-nowrap" data-l="No">No</th>
                        <th class="px-5 py-3.5 whitespace-nowrap" data-l="Project">Project</th>
                        <th class="px-5 py-3.5 whitespace-nowrap" data-l="Serial No / Unit">Serial No / Unit</th>
                        <th class="px-5 py-3.5 whitespace-nowrap" data-l="Customer">Customer</th>
                        <th class="px-5 py-3.5 whitespace-nowrap" data-l="Progres report">Progres report</th>
                        <th class="px-5 py-3.5 whitespace-nowrap" data-l="Detail">Detail</th>
                        <th class="px-5 py-3.5 whitespace-nowrap" data-l="Status">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($mdrs as $mdr)
                        @php
                            $completed = $mdr->completed_reports_count;
                            $totalReports = count(config('mudalaya.mdr_reports'));
                            $progressPct = $totalReports > 0 ? round(($completed / $totalReports) * 100) : 0;
                        @endphp
                        <tr class="transition hover:bg-paper-soft">
                            <td class="px-5 py-4 font-mono text-[13px] text-muted whitespace-nowrap" data-l="No">
                                {{ $mdrs->firstItem() + $loop->index }}
                            </td>
                            <td class="px-5 py-4" data-l="Project">
                                <span class="block font-mono text-[13px] font-semibold text-brand">
                                    {{ $mdr->project->project_code ?? '—' }}
                                </span>
                                <span class="block font-semibold text-ink">
                                    {{ $mdr->project->project_name }}
                                </span>
                            </td>
                            <td class="px-5 py-4 font-mono font-semibold text-ink whitespace-nowrap" data-l="Serial No / Unit">
                                @if ($mdr->owner_serial_no)
                                    <span class="block">{{ $mdr->owner_serial_no }}</span>
                                    @if ($mdr->manufacture_serial_no)
                                        <span class="block text-[12px] font-normal text-muted">{{ $mdr->manufacture_serial_no }}</span>
                                    @endif
                                @else
                                    {{ $mdr->unit_no ?? '—' }}
                                @endif
                            </td>
                            <td class="px-5 py-4 text-muted" data-l="Customer">
                                {{ $mdr->project->customer ?? '—' }}
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap" data-l="Progres report">
                                <div class="flex flex-col gap-1.5" role="img" aria-label="{{ $completed }} dari {{ $totalReports }} report selesai">
                                    <span class="text-[13px] font-semibold text-ink">{{ $completed }} dari {{ $totalReports }}</span>
                                    <div class="h-2 w-full max-w-[140px] overflow-hidden rounded-full bg-[#EEEBE1]" aria-hidden="true">
                                        <div class="h-full rounded-full bg-[#366F3A] transition-all" style="width: {{ $progressPct }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap" data-l="Detail">
                                <a href="{{ route('mdr-checklist.show', ['mdr' => $mdr]) }}"
                                   class="inline-flex min-h-[44px] items-center justify-center rounded-[10px] border border-line bg-white px-4 text-[13px] font-semibold text-ink transition hover:bg-paper">
                                    Lihat MDR
                                </a>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap" data-l="Status">
                                <x-status-chip :status="$mdr->overall_status->value" :label="$mdr->overall_status->label()" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center">
                                @if (! empty($validated['status']) || ! empty($validated['search']))
                                    <div class="flex flex-col items-center gap-2">
                                        <p class="text-[14px] font-medium text-ink">Tidak ada MDR yang cocok</p>
                                        <a href="{{ route('mdr-checklist.index') }}" class="inline-flex min-h-[44px] items-center text-[13px] font-semibold text-brand hover:underline">
                                            Reset filter
                                        </a>
                                    </div>
                                @else
                                    <p class="text-[14px] text-muted">Belum ada MDR.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $mdrs->links('vendor.pagination.mudalaya', ['totalLabel' => 'MDR']) }}
    </div>

    {{-- Responsive styles for mobile < 820px --}}
    <style>
        @media (max-width: 820px) {
            .mdr-table thead {
                display: none;
            }
            .mdr-table tbody tr {
                display: block;
                border-bottom: 1px solid var(--color-line);
                padding: 12px 20px;
            }
            .mdr-table tbody td {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 8px 0;
                border-top: none;
            }
            .mdr-table tbody td::before {
                content: attr(data-l);
                font-size: 13px;
                color: var(--color-muted);
                font-weight: 600;
                flex-shrink: 0;
            }
        }
    </style>
@endsection
