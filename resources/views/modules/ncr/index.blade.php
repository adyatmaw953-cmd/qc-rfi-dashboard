@extends('layouts.module', ['active' => 'ncr', 'pageTitle' => 'NCR'])

@section('header_action')
    @can('create', \App\Models\Ncr::class)
        <a href="{{ route('ncr.create') }}"
           class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] bg-brand px-5 py-2 text-[14px] font-semibold text-white transition hover:bg-brand-dark">
            + Buat NCR
        </a>
    @endcan
@endsection

@section('content')

@php
    $advancedFilters = array_filter([
        $filters['year'] ?? null,
        $filters['month'] ?? null,
        $filters['level'] ?? null,
        $filters['type'] ?? null,
        $filters['department'] ?? null,
        $filters['project'] ?? null,
    ]);
    $advancedCount = count($advancedFilters);
    $hasAnyFilter = $advancedCount > 0 || ! empty($filters['search']) || ! empty($filters['status']);

    $tabs = [
        ['key' => '', 'label' => 'Semua'],
        ['key' => 'Open', 'label' => 'Open'],
        ['key' => 'CA Closed', 'label' => 'CA Closed'],
        ['key' => 'Closed', 'label' => 'Closed'],
    ];
@endphp

{{-- Filter bar --}}
<div class="mb-5 flex flex-wrap items-center gap-3">

    {{-- Segmented status tabs --}}
    <div class="inline-flex overflow-hidden rounded-[10px] border border-line bg-white" role="group" aria-label="Filter status">
        @foreach ($tabs as $tab)
            @php
                $isActive = ($filters['status'] ?? '') === $tab['key'];
                $count = $statusCounts[$tab['label']] ?? ($statusCounts['Semua'] ?? 0);
                $tabUrl = route('ncr.index', array_merge(
                    array_filter($filters, fn($v) => $v !== null && $v !== ''),
                    ['status' => $tab['key'], 'page' => null]
                ));
            @endphp
            <a href="{{ $tabUrl }}"
               class="inline-flex min-h-[44px] items-center gap-2 px-4 text-[13px] font-semibold transition border-r border-line last:border-r-0
                      {{ $isActive ? 'bg-ink text-white' : 'text-muted hover:text-ink hover:bg-paper-soft' }}"
               aria-current="{{ $isActive ? 'page' : 'false' }}">
                <span>{{ $tab['label'] }}</span>
                <span class="font-mono text-[12px] {{ $isActive ? 'text-white/70' : 'text-muted' }}">{{ $count }}</span>
            </a>
        @endforeach
    </div>

    {{-- Spacer --}}
    <div class="flex flex-1 items-center justify-end gap-2 flex-wrap">

        {{-- Search --}}
        <div class="relative" style="max-width:320px; width:100%">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                </svg>
            </span>
            <form method="GET" action="{{ route('ncr.index') }}" id="ncr-search-form">
                @foreach ($filters as $fk => $fv)
                    @if ($fk !== 'search' && $fk !== 'page' && $fv !== null && $fv !== '')
                        <input type="hidden" name="{{ $fk }}" value="{{ $fv }}">
                    @endif
                @endforeach
                <input type="search"
                       name="search"
                       value="{{ $filters['search'] ?? '' }}"
                       aria-label="Pencarian"
                       placeholder="Cari no NCR, uraian masalah, PIC..."
                       class="w-full min-h-[44px] rounded-[10px] border border-line bg-white py-2 pr-4 text-[13px] text-ink placeholder:text-muted focus:border-brand focus:outline-none"
                       style="padding-left:42px"
                       form="ncr-search-form">
            </form>
        </div>

        {{-- Filter lainnya toggle --}}
        <button id="ncr-filter-toggle"
                type="button"
                aria-controls="ncr-filter-panel"
                aria-expanded="{{ $advancedCount > 0 ? 'true' : 'false' }}"
                class="inline-flex min-h-[44px] items-center gap-2 rounded-full border border-line bg-white px-4 text-[13px] font-semibold text-ink transition hover:bg-paper-soft">
            <svg class="h-4 w-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/>
            </svg>
            Filter lainnya
            @if ($advancedCount > 0)
                <span class="inline-flex h-[20px] min-w-[20px] items-center justify-center rounded-full bg-brand-pale px-1.5 font-mono text-[11px] font-semibold text-[#1F5B2A]">
                    {{ $advancedCount }}
                </span>
            @endif
        </button>
    </div>
</div>

{{-- Advanced filter panel --}}
<div id="ncr-filter-panel" style="{{ $advancedCount > 0 ? '' : 'display:none' }}" class="mb-5">
    <div class="rounded-2xl border border-line bg-white" style="padding:22px 24px">
        <form method="GET" action="{{ route('ncr.index') }}" id="ncr-advanced-form">
            {{-- preserve status & search --}}
            @if (! empty($filters['status']))
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
            @endif
            @if (! empty($filters['search']))
                <input type="hidden" name="search" value="{{ $filters['search'] }}">
            @endif

            <div class="grid gap-4 grid-cols-1 sm:grid-cols-3">

                {{-- Tahun --}}
                <div>
                    <label for="year" class="mb-1 block text-[13px] font-semibold text-ink">Tahun</label>
                    <div class="relative">
                        <select id="year" name="year"
                                class="w-full min-h-[44px] appearance-none rounded-[10px] border border-line bg-white px-3 pr-9 text-[13px] text-ink focus:border-brand focus:outline-none">
                            <option value="">Semua tahun</option>
                            @foreach ($years as $yr)
                                <option value="{{ $yr }}" @selected((string) ($filters['year'] ?? '') === (string) $yr)>{{ $yr }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-muted">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none"><path stroke="#5B665E" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m6 8 4 4 4-4"/></svg>
                        </span>
                    </div>
                </div>

                {{-- Bulan --}}
                <div>
                    <label for="month" class="mb-1 block text-[13px] font-semibold text-ink">Bulan</label>
                    <div class="relative">
                        <select id="month" name="month"
                                class="w-full min-h-[44px] appearance-none rounded-[10px] border border-line bg-white px-3 pr-9 text-[13px] text-ink focus:border-brand focus:outline-none">
                            <option value="">Semua bulan</option>
                            @foreach (\App\Services\NcrDashboardService::MONTH_NAMES as $num => $name)
                                <option value="{{ $num }}" @selected((string) ($filters['month'] ?? '') === (string) $num)>{{ $name }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-muted">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none"><path stroke="#5B665E" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m6 8 4 4 4-4"/></svg>
                        </span>
                    </div>
                </div>

                {{-- Level --}}
                <div>
                    <label for="level" class="mb-1 block text-[13px] font-semibold text-ink">Level</label>
                    <div class="relative">
                        <select id="level" name="level"
                                class="w-full min-h-[44px] appearance-none rounded-[10px] border border-line bg-white px-3 pr-9 text-[13px] text-ink focus:border-brand focus:outline-none">
                            <option value="">Semua level</option>
                            @foreach (config('ncr.levels', []) as $lvl)
                                <option value="{{ $lvl }}" @selected(($filters['level'] ?? '') === $lvl)>{{ $lvl }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-muted">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none"><path stroke="#5B665E" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m6 8 4 4 4-4"/></svg>
                        </span>
                    </div>
                </div>

                {{-- Tipe --}}
                <div>
                    <label for="type" class="mb-1 block text-[13px] font-semibold text-ink">Tipe</label>
                    <div class="relative">
                        <select id="type" name="type"
                                class="w-full min-h-[44px] appearance-none rounded-[10px] border border-line bg-white px-3 pr-9 text-[13px] text-ink focus:border-brand focus:outline-none">
                            <option value="">Semua tipe</option>
                            @foreach (config('ncr.types', []) as $tp)
                                <option value="{{ $tp }}" @selected(($filters['type'] ?? '') === $tp)>{{ $tp }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-muted">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none"><path stroke="#5B665E" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m6 8 4 4 4-4"/></svg>
                        </span>
                    </div>
                </div>

                {{-- Department --}}
                <div>
                    <label for="department" class="mb-1 block text-[13px] font-semibold text-ink">Department</label>
                    <div class="relative">
                        <select id="department" name="department"
                                class="w-full min-h-[44px] appearance-none rounded-[10px] border border-line bg-white px-3 pr-9 text-[13px] text-ink focus:border-brand focus:outline-none">
                            <option value="">Semua department</option>
                            @foreach (config('ncr.departments', []) as $dept)
                                <option value="{{ $dept }}" @selected(($filters['department'] ?? '') === $dept)>{{ $dept }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-muted">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none"><path stroke="#5B665E" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m6 8 4 4 4-4"/></svg>
                        </span>
                    </div>
                </div>

                {{-- Project --}}
                <div>
                    <label for="project" class="mb-1 block text-[13px] font-semibold text-ink">Project</label>
                    <div class="relative">
                        <select id="project" name="project"
                                class="w-full min-h-[44px] appearance-none rounded-[10px] border border-line bg-white px-3 pr-9 text-[13px] text-ink focus:border-brand focus:outline-none">
                            <option value="">Semua project</option>
                            @foreach ($projects as $prj)
                                <option value="{{ $prj->project_code }}" @selected(($filters['project'] ?? '') === $prj->project_code)>
                                    {{ $prj->project_code }} — {{ $prj->project_name }}
                                </option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-muted">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none"><path stroke="#5B665E" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m6 8 4 4 4-4"/></svg>
                        </span>
                    </div>
                </div>

            </div>

            <div class="mt-5 flex items-center justify-end gap-3">
                <a href="{{ route('ncr.index') }}"
                   class="inline-flex min-h-[44px] items-center justify-center rounded-full border border-line bg-white px-5 text-[13px] font-semibold text-muted transition hover:text-ink">
                    Reset
                </a>
                <button type="submit"
                        class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] bg-brand px-6 text-[14px] font-semibold text-white transition hover:bg-brand-dark">
                    Terapkan Filter
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Data table card --}}
<div class="overflow-hidden rounded-2xl border border-line bg-white">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[960px] text-left text-[13px]">
            <thead class="border-b border-line bg-paper-soft">
                <tr>
                    <th class="px-5 py-[14px] text-[13px] font-semibold text-muted" data-l="No NCR">No NCR</th>
                    <th class="px-5 py-[14px] text-[13px] font-semibold text-muted" data-l="Department">Department</th>
                    <th class="px-5 py-[14px] text-[13px] font-semibold text-muted" data-l="Project">Project</th>
                    <th class="px-5 py-[14px] text-[13px] font-semibold text-muted" data-l="Level">Level</th>
                    <th class="px-5 py-[14px] text-[13px] font-semibold text-muted" data-l="Time Leads">Time Leads</th>
                    <th class="px-5 py-[14px] text-[13px] font-semibold text-muted text-right" data-l="NC Cost">NC Cost</th>
                    <th class="px-5 py-[14px] text-[13px] font-semibold text-muted" data-l="Aksi">Aksi</th>
                    <th class="px-5 py-[14px] text-[13px] font-semibold text-muted" data-l="Status">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($ncrs as $ncr)
                    <tr class="transition hover:bg-paper-soft">

                        {{-- No NCR + tanggal --}}
                        <td class="px-5 py-4" data-l="No NCR">
                            <a href="{{ route('ncr.show', $ncr) }}"
                               class="font-mono font-semibold text-brand hover:underline">{{ $ncr->ncr_number }}</a>
                            @if ($ncr->finding_date)
                                <div class="mt-0.5 font-mono text-[12px] text-muted">
                                    {{ $ncr->finding_date->translatedFormat('d M Y') }}
                                </div>
                            @endif
                        </td>

                        {{-- Department --}}
                        <td class="px-5 py-4 font-medium text-ink" data-l="Department">{{ $ncr->department }}</td>

                        {{-- Project --}}
                        <td class="px-5 py-4" data-l="Project">
                            @if ($ncr->project_code && $ncr->project)
                                <span class="font-mono text-[13px] font-semibold text-brand">{{ $ncr->project_code }}</span>
                                <div class="mt-0.5 text-[12px] text-muted">{{ $ncr->project->project_name }}</div>
                            @else
                                <span class="text-muted">— (Internal)</span>
                            @endif
                        </td>

                        {{-- Level + type --}}
                        <td class="px-5 py-4 whitespace-nowrap" data-l="Level">
                            <x-level-chip :level="$ncr->level" />
                            <div class="mt-1 text-[12px] text-muted">{{ $ncr->type }}</div>
                        </td>

                        {{-- Time Leads --}}
                        <td class="px-5 py-4 font-mono text-[13px] text-ink" data-l="Time Leads">
                            {{ $ncr->time_leads !== null ? $ncr->time_leads . ' hari' : '-' }}
                        </td>

                        {{-- NC Cost --}}
                        <td class="px-5 py-4 text-right font-mono font-semibold text-ink" data-l="NC Cost">
                            Rp {{ number_format($ncr->nc_cost ?? 0, 0, ',', '.') }}
                        </td>

                        {{-- Aksi --}}
                        <td class="px-5 py-4 whitespace-nowrap" data-l="Aksi">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('ncr.show', $ncr) }}"
                                   class="inline-flex min-h-[44px] items-center justify-center rounded-[10px] border border-line bg-white px-3 text-[13px] font-semibold text-brand transition hover:bg-brand-pale">
                                    Detail
                                </a>
                                @can('update', $ncr)
                                    <a href="{{ route('ncr.edit', $ncr) }}"
                                       class="text-[13px] font-semibold text-brand hover:underline">
                                        Edit
                                    </a>
                                @endcan
                                @can('delete', $ncr)
                                    <form method="POST" action="{{ route('ncr.destroy', $ncr) }}"
                                          onsubmit="return confirm('Yakin ingin menghapus NCR {{ $ncr->ncr_number }}?');"
                                          class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-[13px] font-semibold text-[#8F3F08] hover:underline">
                                            Hapus
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>

                        {{-- Status --}}
                        <td class="px-5 py-4" data-l="Status">
                            <x-status-chip :status="$ncr->status" />
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <svg class="h-12 w-12 text-muted opacity-40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
                                    <rect x="9" y="3" width="6" height="4" rx="1"/>
                                    <line x1="9" y1="12" x2="15" y2="12"/>
                                    <line x1="9" y1="16" x2="11" y2="16"/>
                                </svg>
                                @if ($hasAnyFilter)
                                    <p class="text-[14px] font-semibold text-ink">Tidak ada NCR yang cocok</p>
                                    <p class="text-[13px] text-muted">Coba ubah filter atau pencarian Anda.</p>
                                    <a href="{{ route('ncr.index') }}"
                                       class="mt-1 text-[13px] font-semibold text-brand hover:underline">Reset filter</a>
                                @else
                                    <p class="text-[14px] font-semibold text-ink">Belum ada NCR</p>
                                    <p class="text-[13px] text-muted">Belum ada data NCR yang sesuai dengan filter.</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Footer / pagination --}}
    {{ $ncrs->links('vendor.pagination.mudalaya', ['totalLabel' => 'NCR']) }}
</div>

{{-- Responsive: stack cards on small screens --}}
<style>
    @media (max-width: 820px) {
        table.min-w-\[960px\] thead { display: none; }
        table.min-w-\[960px\] tbody tr {
            display: block;
            border-bottom: 1px solid var(--color-line);
            padding: 12px 20px;
        }
        table.min-w-\[960px\] tbody td {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 6px 0;
        }
        table.min-w-\[960px\] tbody td::before {
            content: attr(data-l);
            min-width: 100px;
            font-size: 12px;
            color: var(--color-muted);
            font-weight: 600;
            padding-top: 2px;
            flex-shrink: 0;
        }
        #ncr-filter-panel .grid { grid-template-columns: 1fr; }
    }
</style>

@endsection
