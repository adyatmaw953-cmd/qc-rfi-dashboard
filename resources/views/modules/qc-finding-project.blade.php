@extends('layouts.module', ['active' => 'qc_finding', 'pageTitle' => 'QC Finding - ' . $project->project_name])

@section('content')
    <div class="space-y-6">
        {{-- Navigasi Kembali --}}
        <div>
            <a href="{{ route('qc-finding.index') }}"
               class="inline-flex min-h-[44px] items-center gap-2 text-[14px] font-semibold text-brand transition hover:text-brand-dark">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Kembali ke QC Finding</span>
            </a>
        </div>

        {{-- Header Project --}}
        <div class="flex flex-col gap-1">
            <span class="text-[12px] font-semibold uppercase tracking-wider text-muted">QC Finding</span>
            <div class="flex flex-wrap items-baseline gap-2">
                @if ($project->project_code)
                    <span class="font-mono text-[22px] font-bold text-brand">{{ $project->project_code }}</span>
                @endif
                <h1 class="text-[26px] font-bold text-ink sm:text-[30px]">{{ $project->project_name }}</h1>
            </div>
            <p class="text-[14px] text-muted">
                Customer: <strong class="font-semibold text-ink">{{ $project->customer ?? '—' }}</strong> ·
                <span class="font-mono">{{ $statusCounts['semua'] }}</span> temuan
            </p>
        </div>

        {{-- Summary Card Progres Project --}}
        <div class="rounded-2xl border border-line bg-white p-[22px]">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h2 class="text-[16px] font-semibold text-ink">Ringkasan Status Temuan</h2>
                <div class="flex items-center gap-2">
                    <span class="text-[13px] text-muted">Due date terdekat:</span>
                    <x-due-chip :due-date="$nearestDueDate" :all-closed="$allClosed" />
                </div>
            </div>
            <x-progress-stack
                :open="$projectOpenCount"
                :waiting="$projectWaitingCount"
                :closed="$projectClosedCount"
            />
        </div>

        {{-- Filter Segmented Tabs & Filter Proses --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @php
                    $statusTabs = [
                        'semua' => ['label' => 'Semua', 'count' => $statusCounts['semua']],
                        'open' => ['label' => 'Open', 'count' => $statusCounts['open']],
                        'waiting_verification' => ['label' => 'Menunggu verifikasi', 'count' => $statusCounts['waiting_verification']],
                        'closed' => ['label' => 'Closed', 'count' => $statusCounts['closed']],
                    ];
                @endphp
                @foreach ($statusTabs as $key => $tab)
                    @php
                        $tabParams = ['project' => $project, 'status' => $key];
                        if (isset($selectedProcess) && $selectedProcess !== 'semua') {
                            $tabParams['proses'] = $selectedProcess;
                        }
                    @endphp
                    <a href="{{ route('qc-finding.project', $tabParams) }}"
                       class="inline-flex min-h-[44px] items-center gap-1.5 rounded-[10px] px-4 text-[13px] transition {{ $selectedStatus === $key ? 'bg-ink font-semibold text-white' : 'border border-line bg-white font-medium text-ink hover:bg-paper-soft' }}">
                        <span>{{ $tab['label'] }}</span>
                        <span class="font-mono text-[12px] opacity-80">{{ $tab['count'] }}</span>
                    </a>
                @endforeach
            </div>

            @if (! empty($processOptions))
                <form method="GET" action="{{ route('qc-finding.project', ['project' => $project]) }}" class="flex items-center gap-2">
                    @if ($selectedStatus !== 'semua')
                        <input type="hidden" name="status" value="{{ $selectedStatus }}">
                    @endif
                    <label for="filter-proses" class="text-[13px] font-medium text-muted">Proses:</label>
                    <select id="filter-proses" name="proses" onchange="this.form.submit()" class="h-[44px] rounded-[10px] border border-line bg-white px-3 text-[13px] font-medium text-ink focus:border-brand focus:outline-none">
                        <option value="semua">Semua</option>
                        @foreach ($processOptions as $code => $cat)
                            <option value="{{ $code }}" @selected(strtoupper($selectedProcess ?? 'semua') === (string) $code)>{{ $cat['label'] }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        @php
            $selectedFinding = $selectedFinding
                ?? ($openFindingId ? $findings->firstWhere('id', $openFindingId) : null)
                ?? $findings->firstWhere('status', '!=', 'closed')
                ?? $findings->first();
        @endphp

        <div class="grid grid-cols-1 min-[1100px]:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            {{-- Kolom Kiri: Daftar Kartu Temuan --}}
            <div class="space-y-5 min-w-0">
                @forelse ($findings as $finding)
                @php
                    $latestSubmission = $finding->submissions->last();
                    $hasPendingAction = ($finding->can_edit_action_plan && blank($finding->action_plan))
                        || ($finding->status === 'waiting_verification' && in_array(auth()->user()->role, ['admin', 'qc_qa'], true))
                        || ($finding->status === 'open' && $finding->can_submit && filled($finding->action_plan));
                    $isOpen = $selectedFinding ? ($finding->id === $selectedFinding->id) : false;
                    $stepper = \App\Services\FindingPresenter::getStepperState($finding);
                @endphp

                <article id="temuan-{{ $finding->id }}" class="flex flex-col rounded-2xl border border-line bg-white p-[22px] sm:p-[26px] transition hover:border-brand/40">
                    <span id="finding-{{ $finding->id }}" class="sr-only" aria-hidden="true"></span>
                    {{-- Baris Header Temuan --}}
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-pale font-mono text-[13px] font-bold text-brand">
                                {{ $loop->iteration }}
                            </span>

                            @if ($finding->can_link_rfi)
                                <a href="{{ route('rfi.category', ['kategori' => $finding->rfi->category, 'status' => 'nc']) }}"
                                   class="font-mono text-[14px] font-semibold text-brand underline hover:text-brand-dark">
                                    {{ $finding->rfi->rfi_no }}
                                </a>
                            @else
                                <span class="font-mono text-[14px] font-semibold text-brand">
                                    {{ $finding->rfi->rfi_no }}
                                </span>
                            @endif

                            <x-status-chip :status="$finding->status" />

                            @if ($finding->status === 'open' && blank($finding->action_plan) && $finding->rfi?->category !== 'A')
                                <x-status-chip status="perlu_action_plan" />
                            @endif

                            @php
                                $reinspectCount = $finding->submissions->count();
                                $notOkCount = $finding->submissions->where('verification_result', 'not_ok')->count();
                            @endphp

                            @if ($reinspectCount > 0)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#E0E7FF] px-[10px] py-[3px] text-[12px] font-semibold text-[#3730A3]">
                                    Re-inspect {{ $reinspectCount }}x
                                </span>
                            @endif

                            @if ($notOkCount > 0)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#FEE2E2] px-[10px] py-[3px] text-[12px] font-semibold text-[#991B1B]">
                                    Not OK {{ $notOkCount }}x
                                </span>
                            @endif

                        </div>

                        @if (($finding->process_finding_number ?? 1) > 1 && ! empty($finding->previous_process_findings))
                            <details class="recurring-finding-details relative mt-2 inline-block">
                                <summary class="inline-flex cursor-pointer select-none items-center gap-1.5 rounded-full bg-[#FEF3C7] px-[10px] py-[3px] text-[12px] font-semibold text-[#92400E] transition hover:bg-[#FDE68A] [&::-webkit-details-marker]:hidden list-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"
                                         title="Klik untuk melihat temuan sebelumnya di proses ini">
                                    <svg width="14" height="14" class="shrink-0 text-[#B45309]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    <span>Temuan ke-{{ $finding->process_finding_number }} di proses ini</span>
                                </summary>

                                <div class="absolute left-0 top-full z-40 mt-1.5 w-[320px] max-w-[90vw] rounded-xl border border-line bg-white p-3.5 shadow-xl">
                                    <div class="mb-2 flex items-center justify-between border-b border-line pb-2">
                                        <span class="text-[12px] font-bold text-ink">Temuan sebelumnya di proses ini</span>
                                        <span class="text-[11px] text-muted">{{ count($finding->previous_process_findings) }} temuan</span>
                                    </div>
                                    <div class="max-h-60 space-y-2.5 overflow-y-auto">
                                        @foreach (array_reverse($finding->previous_process_findings) as $prev)
                                            <div class="border-b border-line/60 pb-2.5 text-[12px] last:border-b-0 last:pb-0">
                                                <div class="flex items-center justify-between gap-1">
                                                    <span class="font-mono font-semibold text-brand">{{ $prev['rfi_no'] }}</span>
                                                    <span class="text-[11px] text-muted">
                                                        {{ $prev['date_of_finding'] ? \Carbon\Carbon::parse($prev['date_of_finding'])->setTimezone('Asia/Jakarta')->format('d M Y') : '—' }}
                                                    </span>
                                                </div>
                                                <p class="mt-1 text-[12px] leading-snug text-ink">
                                                    {{ Str::limit($prev['finding_list'] ?: '—', 90) }}
                                                </p>
                                                <div class="mt-1.5 flex items-center gap-1.5">
                                                    <x-status-chip :status="$prev['status']" class="!px-2 !py-0.5 !text-[10px]" />
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </details>
                        @endif

                        <div class="flex items-center gap-2">
                            <x-due-chip :due-date="$finding->due_date" :all-closed="$finding->status === 'closed'" :simple="true" />

                            @if (! $isOpen)
                                @php
                                    $bukaParams = ['project' => $project, 'status' => $selectedStatus, 'buka' => $finding->id];
                                    if (isset($selectedProcess) && $selectedProcess !== 'semua') {
                                        $bukaParams['proses'] = $selectedProcess;
                                    }
                                @endphp
                                <a href="{{ route('qc-finding.project', $bukaParams) }}#finding-{{ $finding->id }}"
                                   class="inline-flex min-h-[38px] items-center rounded-lg border border-line bg-white px-3.5 text-[13px] font-semibold text-ink transition hover:bg-paper-soft">
                                    Buka detail
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Informasi Meta Temuan --}}
                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px] text-muted">
                        @php
                            $rfiCat = $finding->rfi?->category;
                            $catConfig = config("mudalaya.rfi_categories.{$rfiCat}", []);
                            $catLabel = $catConfig['label'] ?? $rfiCat;
                            $rfiDetails = is_array($finding->rfi?->details)
                                ? $finding->rfi->details
                                : (json_decode($finding->rfi?->details ?? '', true) ?: []);
                            $detailText = '';
                            if (in_array($rfiCat, ['B', 'C', 'D'], true)) {
                                $item = $rfiDetails['inspect_item'] ?? null;
                                $test = $rfiDetails['test_type'] ?? null;
                                if ($item && $test) {
                                    $detailText = "{$item} ({$test})";
                                } elseif ($item) {
                                    $detailText = $item;
                                } elseif ($test) {
                                    $detailText = $test;
                                }
                            } elseif ($rfiCat === 'A') {
                                $detailText = $finding->action_plan ?: ($rfiDetails['material'] ?? ($rfiDetails['jenis_material'] ?? ($rfiDetails['surat_jalan_no'] ?? '')));
                                if (is_array($detailText)) {
                                    $detailText = implode(', ', $detailText);
                                }
                            } elseif ($rfiCat === 'E') {
                                $detailText = $rfiDetails['vendor'] ?? '';
                            }
                            $prosesLabel = $catLabel . ($detailText !== '' ? ' · ' . $detailText : '');
                        @endphp
                        @if ($rfiCat)
                            <span class="inline-flex items-center rounded-full bg-[#EEEBE1] px-2.5 py-0.5 text-[11px] font-semibold text-[#3F4A41]">
                                Proses: {{ $prosesLabel }}
                            </span>
                            <span>&bull;</span>
                        @endif
                        <span>Tanggal temuan: <strong class="font-medium text-ink">{{ $finding->date_of_finding->format('d M Y') }}</strong></span>
                        <span>&bull;</span>
                        <span>Disposisi: <strong class="font-medium text-ink">{{ $finding->result->label() }}</strong></span>
                        @if ($finding->due_date)
                            <span>&bull;</span>
                            <span class="inline-flex items-center gap-1.5">
                                <span>Due date: <strong class="font-medium text-ink">{{ $finding->due_date->format('d M Y') }}</strong></span>
                                @if ($finding->isOverdue())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-[#FEE2E2] px-2 py-0.5 text-[11px] font-semibold text-[#991B1B]">
                                        <span class="h-1.5 w-1.5 rounded-full bg-[#EF4444]" aria-hidden="true"></span>
                                        Terlambat
                                    </span>
                                @elseif ($finding->isDueToday())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-[#FEF3C7] px-2 py-0.5 text-[11px] font-semibold text-[#92400E]">
                                        <span class="h-1.5 w-1.5 rounded-full bg-[#F59E0B]" aria-hidden="true"></span>
                                        Jatuh tempo hari ini
                                    </span>
                                @endif
                            </span>
                        @endif
                        @if ($finding->date_of_close)
                            <span>&bull;</span>
                            <span>Closed: <strong class="font-medium text-close">{{ $finding->date_of_close->format('d M Y') }}</strong></span>
                        @endif
                    </div>

                    {{-- Deskripsi Temuan --}}
                    <div class="mt-3">
                        <p class="whitespace-pre-line text-[15px] font-medium text-ink leading-relaxed">
                            {{ $finding->finding_list }}
                        </p>
                    </div>

                    {{-- Baris Aktivitas Terakhir untuk Kartu Ringkas (Tertutup) --}}
                    @if (! $isOpen && $finding->latestActivity)
                        @php
                            $latestPres = \App\Services\FindingActivityPresenter::present($finding->latestActivity);
                            $latestSentence = trim(($latestPres['actor'] !== '' ? $latestPres['actor'] . ' ' : '') . $latestPres['verb'] . ($latestPres['object'] !== '' ? ' ' . $latestPres['object'] : ''));
                        @endphp
                        <div class="mt-3 flex items-center gap-2 border-t border-dashed border-[#E1DED3] pt-3 text-[13px] text-[#5B665E]">
                            <svg class="h-4 w-4 shrink-0 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            <span>
                                Aktivitas terakhir: <b class="font-semibold text-[#14201A]">{{ $latestSentence }}</b> · {{ $latestPres['time_relative'] }}
                            </span>
                        </div>
                    @endif

                    {{-- TAMPILAN TERBUKA (FULL CARD) --}}
                    @if ($isOpen)
                        {{-- Stepper Tahapan Temuan --}}
                        <div class="my-5 border-y border-line/60 py-3.5">
                            <x-finding-stepper :stages="$stepper['stages']" />
                        </div>

                                @if ($finding->rfi?->category === 'A')
                                    <div class="space-y-4">
                                @php
                                    $isQcOrAdmin = in_array(auth()->user()->role, ['admin', 'qc_qa'], true);
                                    $materialVal = $finding->action_plan ?: ($rfiDetails['material'] ?? '');
                                    $reasonVal = ($finding->finding_list !== 'Incoming Material Finding') ? $finding->finding_list : '';
                                    $currentResult = $finding->result?->value;

                                    $incomingFindings = data_get($rfiDetails, 'incoming_findings');
                                    if (! is_array($incomingFindings) || empty($incomingFindings)) {
                                        $existingMats = [];
                                        if (! empty($materialVal)) {
                                            if (is_array($materialVal)) {
                                                $existingMats = $materialVal;
                                            } else {
                                                $decoded = json_decode($materialVal, true);
                                                if (is_array($decoded)) {
                                                    $existingMats = $decoded;
                                                } else {
                                                    $existingMats = array_values(array_filter(array_map('trim', explode(',', $materialVal))));
                                                }
                                            }
                                        }
                                        $incomingFindings = [
                                            [
                                                'materials' => $existingMats,
                                                'reason' => $reasonVal,
                                            ],
                                        ];
                                    }

                                    if (old('items') !== null && is_array(old('items'))) {
                                        $incomingFindings = [];
                                        foreach (old('items') as $oldItem) {
                                            $mats = is_array($oldItem['materials'] ?? null)
                                                ? array_values(array_filter(array_map('trim', $oldItem['materials'])))
                                                : (filled($oldItem['materials'] ?? null) ? [trim($oldItem['materials'])] : []);
                                            $incomingFindings[] = [
                                                'materials' => $mats,
                                                'reason' => (string) ($oldItem['reason'] ?? ''),
                                            ];
                                        }
                                    }
                                    if (empty($incomingFindings)) {
                                        $incomingFindings = [
                                            ['materials' => [], 'reason' => ''],
                                        ];
                                    }
                                @endphp

                                @if ($finding->status !== 'closed' && $isQcOrAdmin)
                                    <div class="rounded-xl border border-line bg-paper-soft p-5">
                                        <div class="mb-4">
                                            <h4 class="text-[16px] font-bold text-ink">Tindakan QC — Incoming Material</h4>
                                            <p class="text-[13px] text-muted">Lengkapi data temuan QC untuk material dari Warehouse ini.</p>
                                        </div>

                                        <form method="POST" action="{{ route('qc-finding.incoming-action', ['finding' => $finding]) }}" id="incoming-form-{{ $finding->id }}" class="space-y-4">
                                            @csrf

                                            @if ($errors->has('material') || $errors->has('reason_of_finding'))
                                                <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-[13px] text-alert">
                                                    {{ $errors->first('material') ?: $errors->first('reason_of_finding') }}
                                                </div>
                                            @endif

                                            <div class="overflow-x-auto rounded-[14px] border border-line bg-white shadow-xs">
                                                <table class="w-full text-left text-[13px] text-ink" id="incoming-table-{{ $finding->id }}">
                                                    <thead class="border-b border-line bg-paper-soft text-[12px] font-semibold text-ink">
                                                        <tr>
                                                            <th class="w-[50px] px-4 py-3 text-center">NO.</th>
                                                            <th class="w-[45%] px-4 py-3">Material <span class="text-alert">*</span></th>
                                                            <th class="px-4 py-3">Reason of Finding <span class="text-alert">*</span></th>
                                                            <th class="w-[60px] px-4 py-3 text-center">Aksi</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-line" id="incoming-tbody-{{ $finding->id }}">
                                                        @foreach ($incomingFindings as $fIndex => $fRow)
                                                            <tr data-row-index="{{ $fIndex }}">
                                                                <td class="px-4 py-3 text-center font-medium text-ink align-top pt-4">
                                                                    <span class="row-no">{{ $fIndex + 1 }}</span>
                                                                </td>
                                                                <td class="px-4 py-3 align-top">
                                                                    <div class="space-y-2">
                                                                        <div class="flex gap-1.5">
                                                                            <input
                                                                                type="text"
                                                                                placeholder="Ketik nama material..."
                                                                                class="material-row-input form-control text-[13px] h-[38px] flex-1 min-w-[150px]"
                                                                            >
                                                                            <button
                                                                                type="button"
                                                                                class="btn-add-tag inline-flex h-[38px] shrink-0 items-center justify-center rounded-[8px] bg-brand px-3 text-[12px] font-semibold text-white transition hover:bg-brand-dark"
                                                                            >
                                                                                + Tambah
                                                                            </button>
                                                                        </div>
                                                                        <div class="material-tags-container flex flex-wrap gap-1.5 min-h-[46px] p-2 rounded-[10px] border border-[#B9B5A6] bg-paper-soft/40 items-center">
                                                                            @foreach ($fRow['materials'] ?? [] as $mItem)
                                                                                @if (trim($mItem) !== '')
                                                                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-[#E9EFE5] border border-brand/40 px-3 py-1 text-[12px] font-semibold text-[#1F5B2A]">
                                                                                        <span>{{ $mItem }}</span>
                                                                                        <input type="hidden" name="items[{{ $fIndex }}][materials][]" value="{{ $mItem }}">
                                                                                        <button type="button" class="btn-remove-tag text-[#1F5B2A] hover:text-alert font-bold ml-1 focus:outline-none" title="Hapus">&times;</button>
                                                                                    </span>
                                                                                @endif
                                                                            @endforeach
                                                                            <span class="tag-empty-hint text-[11px] text-muted italic {{ !empty($fRow['materials']) ? 'hidden' : '' }}">Belum ada material</span>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td class="px-4 py-3 align-top">
                                                                    <textarea
                                                                        name="items[{{ $fIndex }}][reason]"
                                                                        rows="3"
                                                                        placeholder="[isian teks]"
                                                                        class="form-control text-[13px] resize-y min-h-[92px]"
                                                                        required
                                                                    >{{ $fRow['reason'] ?? '' }}</textarea>
                                                                </td>
                                                                <td class="px-4 py-3 text-center align-top pt-4">
                                                                    <button
                                                                        type="button"
                                                                        class="btn-delete-row inline-flex h-[36px] w-[36px] items-center justify-center rounded-[8px] bg-[#FEE2E2] text-[#EF4444] transition hover:bg-[#FCA5A5] focus:outline-none"
                                                                        title="Hapus baris"
                                                                    >
                                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                                                        </svg>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>

                                            <div class="pt-1">
                                                <button
                                                    type="button"
                                                    id="btn-add-material-row-{{ $finding->id }}"
                                                    class="inline-flex min-h-[42px] items-center justify-center rounded-[10px] bg-brand px-5 text-[13px] font-semibold text-white shadow-xs transition hover:bg-brand-dark"
                                                >
                                                    + Tambah Material
                                                </button>
                                                <p class="mt-1.5 text-[12px] text-muted">Ketik nama material lalu klik + Tambah. Bisa lebih dari satu.</p>
                                            </div>

                                            <div class="mt-5 border-t border-line/60 pt-4">
                                                <span class="mb-2 block text-[13px] font-semibold text-ink">
                                                    Result <span class="text-alert">*</span>
                                                </span>
                                                <div class="flex flex-wrap items-center gap-6">
                                                    <label class="inline-flex items-center gap-2 cursor-pointer text-[13px] text-ink font-medium">
                                                        <input
                                                            type="radio"
                                                            name="result"
                                                            value="use_as_is"
                                                            class="h-4 w-4 text-brand focus:ring-brand border-line"
                                                            @checked(old('result', $currentResult) === 'use_as_is')
                                                            required
                                                        >
                                                        <span>1. Use as is</span>
                                                    </label>
                                                    <label class="inline-flex items-center gap-2 cursor-pointer text-[13px] text-ink font-medium">
                                                        <input
                                                            type="radio"
                                                            name="result"
                                                            value="reject"
                                                            class="h-4 w-4 text-brand focus:ring-brand border-line"
                                                            @checked(old('result', $currentResult) === 'reject')
                                                            required
                                                        >
                                                        <span>2. Reject</span>
                                                    </label>
                                                </div>
                                                <p class="mt-1.5 text-[11px] text-muted">Jika memilih Reject, notifikasi otomatis akan dikirim ke pihak Warehouse.</p>
                                            </div>

                                            <div class="flex justify-end pt-2">
                                                <button
                                                    type="submit"
                                                    class="inline-flex min-h-[44px] items-center justify-center rounded-[10px] bg-brand px-7 text-[13px] font-semibold text-white shadow-xs transition hover:bg-brand-dark"
                                                >
                                                    Simpan
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                @else
                                    <div class="rounded-xl border border-line bg-white p-5 space-y-4">
                                        <div class="overflow-x-auto rounded-[12px] border border-line bg-white">
                                            <table class="w-full text-left text-[13px] text-ink">
                                                <thead class="border-b border-line bg-paper-soft text-[12px] font-semibold text-ink">
                                                    <tr>
                                                        <th class="w-[50px] px-4 py-2.5 text-center">NO.</th>
                                                        <th class="w-[45%] px-4 py-2.5">Material</th>
                                                        <th class="px-4 py-2.5">Reason of Finding</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-line">
                                                    @foreach ($incomingFindings as $fIndex => $fRow)
                                                        <tr>
                                                            <td class="px-4 py-3 text-center font-medium text-ink align-top">
                                                                {{ $fIndex + 1 }}
                                                            </td>
                                                            <td class="px-4 py-3 align-top">
                                                                <div class="flex flex-wrap gap-1.5">
                                                                    @forelse ($fRow['materials'] ?? [] as $fMat)
                                                                        <span class="inline-flex items-center rounded-full bg-[#E9EFE5] px-2.5 py-0.5 text-[12px] font-medium text-[#1F5B2A]">
                                                                            {{ $fMat }}
                                                                        </span>
                                                                    @empty
                                                                        <span class="text-muted">—</span>
                                                                    @endforelse
                                                                </div>
                                                            </td>
                                                            <td class="px-4 py-3 align-top whitespace-pre-line text-[13px] text-ink">
                                                                {{ $fRow['reason'] ?: '—' }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="grid gap-4 sm:grid-cols-2 pt-2 border-t border-line/50">
                                            <div>
                                                <span class="text-[12px] font-semibold text-muted">Result:</span>
                                                <div class="mt-1">
                                                    @if ($finding->result === \App\Enums\RfiResult::UseAsIs)
                                                        <span class="inline-flex items-center rounded-full bg-[#EEEBE1] px-2.5 py-0.5 text-[12px] font-semibold text-[#3F4A41]">
                                                            Use as is
                                                        </span>
                                                    @elseif ($finding->result === \App\Enums\RfiResult::Reject)
                                                        <span class="inline-flex items-center rounded-full bg-[#FEE2E2] px-2.5 py-0.5 text-[12px] font-semibold text-[#991B1B]">
                                                            Reject
                                                        </span>
                                                    @else
                                                        <span class="text-[14px] text-ink">{{ $finding->result?->label() ?? '—' }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            @if ($finding->date_of_close)
                                                <div>
                                                    <span class="text-[12px] font-semibold text-muted">Selesai pada:</span>
                                                    <p class="mt-1 text-[13px] font-semibold text-close">{{ $finding->date_of_close->format('d M Y') }}</p>
                                                </div>
                                            @elseif (! $isQcOrAdmin)
                                                <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-[12px] text-amber-800 font-medium">
                                                    Menunggu tindakan QC untuk Incoming Material ini.
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                        <div class="space-y-4">
                            {{-- 1. Bagian Action Plan --}}
                            <div class="rounded-xl border border-line bg-paper-soft p-4">
                                <div class="mb-2 flex items-center justify-between">
                                    <h4 class="text-[14px] font-semibold text-ink">Action Plan</h4>
                                    @if ($finding->actionPlanBy)
                                        <span class="text-[11px] text-muted">
                                            Oleh {{ $finding->actionPlanBy->name }} · {{ $finding->action_plan_at?->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}
                                        </span>
                                    @endif
                                </div>

                                @if ($finding->can_edit_action_plan)
                                    <form method="POST" action="{{ route('qc-finding.action-plan.update', ['finding' => $finding]) }}" class="space-y-3">
                                        @csrf
                                        <textarea
                                            name="action_plan"
                                            rows="3"
                                            class="form-control"
                                            placeholder="Tuliskan rencana tindakan perbaikan (action plan)..."
                                        >{{ old('action_plan', $finding->action_plan) }}</textarea>
                                        <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-[10px] bg-brand px-5 text-[13px] font-semibold text-white transition hover:bg-brand-dark">
                                            Simpan action plan
                                        </button>
                                    </form>
                                @else
                                    <p class="whitespace-pre-line text-[14px] text-ink">
                                        {{ $finding->action_plan ?: '—' }}
                                    </p>
                                @endif
                            </div>

                            {{-- 2. Bagian Kirim Re-Inspect (Pemenuhan) --}}
                            @if ($finding->status === 'open' && $finding->can_submit)
                                @if (filled($finding->action_plan))
                                    <div class="rounded-xl border border-line bg-white p-5 shadow-sm space-y-4">
                                        <div>
                                            <h4 class="text-[15px] font-semibold text-ink">Kirim Pemenuhan (Re-Inspect)</h4>
                                            <p class="text-[12px] text-muted">Kirimkan catatan perbaikan dan bukti foto/dokumen untuk diverifikasi oleh tim QC.</p>
                                        </div>

                                        <form method="POST" enctype="multipart/form-data" action="{{ route('qc-finding.submissions.store', ['finding' => $finding]) }}" class="space-y-4">
                                            @csrf
                                            <label class="block text-[13px] font-semibold text-ink">
                                                Catatan pemenuhan
                                                <textarea
                                                    name="note"
                                                    required
                                                    rows="3"
                                                    class="form-control mt-1.5"
                                                    placeholder="Deskripsikan pekerjaan perbaikan yang sudah diselesaikan..."
                                                >{{ old('note') }}</textarea>
                                            </label>

                                            <div class="space-y-1.5">
                                                <span class="block text-[13px] font-semibold text-ink">
                                                    Evidence (1–5 file, JPG/PNG/PDF, maksimal 10 MB per file)
                                                </span>
                                                <x-evidence-dropzone :finding-id="$finding->id" />
                                            </div>

                                            <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-[10px] bg-brand px-6 text-[13px] font-semibold text-white transition hover:bg-brand-dark">
                                                Kirim Re-Inspect
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-[13px] font-medium text-amber-800">
                                        Isi action plan sebelum mengirim Re-Inspect.
                                    </div>
                                @endif
                            @endif

                            {{-- 3. Bagian Verifikasi QC (Saat Menunggu Verifikasi) --}}
                            @if ($finding->status === 'waiting_verification' && in_array(auth()->user()->role, ['admin', 'qc_qa'], true) && $latestSubmission)
                                <div class="rounded-xl border border-amber-300 bg-[#FFFDF5] p-5 space-y-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 rounded-full bg-nc" aria-hidden="true"></span>
                                            <h4 class="text-[15px] font-bold text-amber-950">Menunggu verifikasi QC</h4>
                                        </div>
                                        <span class="text-[12px] text-muted">
                                            Putaran {{ $latestSubmission->round_no }} · {{ $latestSubmission->evidences->count() }} evidence
                                        </span>
                                    </div>

                                    <div class="rounded-lg bg-white/80 p-3.5 border border-amber-200/60">
                                        <p class="text-[12px] font-medium text-muted">Catatan pemenuhan dari {{ $latestSubmission->submittedBy?->name ?? 'Production' }}:</p>
                                        <p class="mt-1 whitespace-pre-line text-[14px] text-ink">{{ $latestSubmission->note }}</p>
                                    </div>

                                    <div>
                                        <p class="text-[12px] font-semibold text-ink mb-1.5">Berkas Bukti (Evidence):</p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($latestSubmission->evidences as $evidence)
                                                <a href="{{ route('qc-finding.evidence.download', ['evidence' => $evidence]) }}"
                                                   class="inline-flex min-h-[44px] items-center gap-2 rounded-[10px] border border-line bg-white px-3.5 text-[12px] font-semibold text-brand transition hover:bg-paper-soft hover:underline">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                    <span>{{ $evidence->original_name }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('qc-finding.verify', ['finding' => $finding]) }}" class="space-y-3 pt-2" data-finding-verification>
                                        @csrf
                                        <label class="block text-[13px] font-semibold text-ink">
                                            Catatan verifikasi
                                            <textarea
                                                name="verification_note"
                                                rows="2"
                                                data-verification-note
                                                placeholder="Catatan wajib jika memilih Belum OK"
                                                class="form-control mt-1.5 text-[13px]"
                                            ></textarea>
                                        </label>

                                        <div class="flex flex-wrap items-center gap-3">
                                            <button type="submit" name="result" value="ok"
                                                    class="inline-flex min-h-[44px] items-center justify-center rounded-[10px] bg-brand px-6 text-[13px] font-semibold text-white transition hover:bg-brand-dark">
                                                Sudah OK
                                            </button>
                                            <button type="submit" name="result" value="not_ok" data-verification-not-ok
                                                    class="inline-flex min-h-[44px] items-center justify-center rounded-[10px] bg-[#B4470F] px-6 text-[13px] font-semibold text-white transition hover:bg-alert">
                                                Belum OK
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            @endif

                            {{-- 4. Bagian Riwayat Re-Inspect --}}
                            @if ($finding->submissions->isNotEmpty())
                                <details class="rounded-xl border border-line bg-paper-soft p-4" open>
                                    <summary class="cursor-pointer text-[13px] font-semibold text-ink select-none">
                                        Riwayat Re-Inspect ({{ $finding->submissions->count() }})
                                    </summary>

                                    <div class="mt-4 space-y-4">
                                        @foreach ($finding->submissions as $submission)
                                            <section class="border-t border-line/60 pt-3 text-[13px]">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <p class="font-bold text-ink">Putaran {{ $submission->round_no }}</p>
                                                    <p class="text-[12px] text-muted">
                                                        Dikirim {{ $submission->submittedBy?->name ?? '—' }} ·
                                                        {{ $submission->submitted_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}
                                                    </p>
                                                </div>

                                                <p class="mt-2 whitespace-pre-line text-ink">{{ $submission->note }}</p>

                                                <p class="mt-2 text-[12px] text-muted">
                                                    {{ $submission->evidences->count() }} evidence
                                                </p>

                                                <div class="mt-1.5 flex flex-wrap gap-2">
                                                    @foreach ($submission->evidences as $evidence)
                                                        <a href="{{ route('qc-finding.evidence.download', ['evidence' => $evidence]) }}"
                                                           class="inline-flex min-h-[44px] items-center gap-1.5 rounded-[10px] border border-line bg-white px-3 text-[12px] font-semibold text-brand transition hover:bg-paper-soft hover:underline">
                                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                            </svg>
                                                            <span>{{ $evidence->original_name }}</span>
                                                        </a>
                                                    @endforeach
                                                </div>

                                                <div class="mt-2.5 rounded-lg border border-line/80 bg-white p-3 text-[12px]">
                                                    <p class="text-muted">
                                                        Hasil QC:
                                                        @if ($submission->verification_result === 'ok')
                                                            <strong class="font-semibold text-close">Sudah OK</strong>
                                                        @elseif ($submission->verification_result === 'not_ok')
                                                            <strong class="font-semibold text-alert">Belum OK</strong>
                                                        @else
                                                            <strong class="font-semibold text-nc">Menunggu verifikasi</strong>
                                                        @endif
                                                        @if ($submission->verified_at)
                                                            · {{ $submission->verifiedBy?->name ?? '—' }} · {{ $submission->verified_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}
                                                        @endif
                                                    </p>
                                                    @if ($submission->verification_note)
                                                        <p class="mt-1 whitespace-pre-line text-ink">{{ $submission->verification_note }}</p>
                                                    @endif
                                                </div>
                                            </section>
                                        @endforeach
                                    </div>
                                </details>
                            @endif

                            {{-- 5. Bagian Due Date (Untuk QC / Admin) --}}
                            @if ($finding->can_update_due_date)
                                <div class="rounded-xl border border-line bg-paper-soft p-4">
                                    <form method="POST" action="{{ route('qc-finding.due-date.update', ['finding' => $finding]) }}" class="flex flex-wrap items-center gap-3">
                                        @csrf
                                        <label class="text-[13px] font-semibold text-ink">Due date:</label>
                                        <input
                                            type="date"
                                            name="due_date"
                                            value="{{ $finding->due_date?->format('Y-m-d') }}"
                                            class="h-[44px] rounded-[10px] border border-line bg-white px-3 text-[13px] text-ink focus:border-brand focus:outline-none"
                                        >
                                        @if ($finding->isOverdue())
                                            <span class="inline-flex items-center gap-1 rounded-full bg-[#FEE2E2] px-2 py-0.5 text-[11px] font-semibold text-[#991B1B]">
                                                <span class="h-1.5 w-1.5 rounded-full bg-[#EF4444]" aria-hidden="true"></span>
                                                Terlambat
                                            </span>
                                        @elseif ($finding->isDueToday())
                                            <span class="inline-flex items-center gap-1 rounded-full bg-[#FEF3C7] px-2 py-0.5 text-[11px] font-semibold text-[#92400E]">
                                                <span class="h-1.5 w-1.5 rounded-full bg-[#F59E0B]" aria-hidden="true"></span>
                                                Jatuh tempo hari ini
                                            </span>
                                        @endif
                                        <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-[10px] border border-line bg-white px-4 text-[13px] font-semibold text-ink transition hover:bg-paper-soft">
                                            Simpan due date
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                        @endif
                    @endif
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-line bg-white p-12 text-center text-[14px] text-muted">
                    Tidak ada temuan dengan status ini.
                </div>
            @endforelse
        </div>

        {{-- Kolom Kanan: Panel Riwayat Aktivitas (Sticky di Desktop) --}}
        <aside aria-label="Riwayat aktivitas" class="w-full min-[1100px]:w-[360px] min-[1100px]:shrink-0 min-[1100px]:sticky min-[1100px]:top-4 rounded-2xl border border-line bg-white p-0 overflow-hidden shadow-xs">
            <header class="border-b border-[#E1DED3] px-[22px] pt-[20px] pb-[14px]">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-[18px] font-semibold text-ink">
                        Riwayat aktivitas
                        <span class="sr-only">History Finding</span>
                    </h2>
                    <span class="rounded-full bg-[#E9EFE5] px-2.5 py-0.5 font-mono text-[12px] font-semibold text-[#1F5B2A]">
                        {{ $activitiesTotalCount ?? $activities->count() }}
                    </span>
                </div>
                @if ($selectedFinding && $selectedFinding->rfi?->rfi_no)
                    <p class="mt-1 text-[13px] text-muted">
                        Temuan <b class="font-semibold text-brand">{{ $selectedFinding->rfi->rfi_no }}</b> · terbaru di atas
                    </p>
                @endif
            </header>

            @if ($activities->isNotEmpty())
                @php
                    $topActivities = $activities->take(20);
                    $remainingActivities = $activities->slice(20);
                @endphp

                <ol aria-label="Riwayat aktivitas temuan" class="divide-y divide-[#E1DED3]/40">
                    @foreach ($topActivities as $act)
                        @php
                            $pres = \App\Services\FindingActivityPresenter::present($act);
                            $isFirstItem = $loop->first;
                            $isLastItem = $loop->last && $remainingActivities->isEmpty();
                            $toneStyles = match ($pres['tone']) {
                                'info' => 'bg-[#E3EEFB] text-[#1B4C8C]',
                                'warn' => 'bg-[#FCE8D8] text-[#8F3F08]',
                                'ok' => 'bg-[#E1F0E2] text-[#1F5B2A]',
                                'neutral' => 'bg-[#EEEBE1] text-[#3F4A41]',
                                default => 'bg-[#EEEBE1] text-[#3F4A41]',
                            };
                        @endphp
                        <li class="relative flex items-start gap-3 px-[22px] py-[14px] text-[14px] leading-[1.5] transition {{ $isFirstItem ? 'bg-[#FBFAF6]' : '' }}">
                            @if (! $isLastItem)
                                <span class="absolute left-[33px] sm:left-[38px] top-[46px] bottom-[-14px] w-[2px] bg-[#E1DED3] z-0" aria-hidden="true"></span>
                            @endif

                            <div class="relative z-[1] flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full {{ $toneStyles }}" aria-hidden="true">
                                @if ($pres['icon'] === 'arrow-up')
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                    </svg>
                                @elseif ($pres['icon'] === 'calendar')
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                @elseif ($pres['icon'] === 'x-circle')
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                @elseif ($pres['icon'] === 'check')
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                @elseif ($pres['icon'] === 'pencil')
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                @elseif ($pres['icon'] === 'flag')
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                                    </svg>
                                @elseif ($pres['icon'] === 'document')
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                @else
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <circle cx="12" cy="12" r="9"></circle>
                                    </svg>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1 pt-0.5">
                                <p class="text-[14px] leading-[1.5] text-ink">
                                    <b class="font-semibold text-ink">{{ $pres['actor'] }}</b>
                                    <span>{{ $pres['verb'] }}</span>
                                    @if ($pres['object'] !== '')
                                        <b class="font-semibold text-ink">{{ $pres['object'] }}</b>
                                    @endif
                                </p>

                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <time datetime="{{ $act->occurred_at ? $act->occurred_at->toIso8601String() : '' }}" class="text-[12px] text-[#5B665E] font-medium">
                                        {{ $pres['time_absolute'] }}
                                    </time>
                                    <span class="text-[12px] text-muted">· {{ $pres['time_relative'] }}</span>

                                    @foreach ($pres['chips'] as $chip)
                                        @if (str_starts_with($chip, 'Putaran'))
                                            <span class="inline-flex items-center rounded-full bg-[#E3EEFB] text-[#1B4C8C] px-2.5 py-0.5 text-[11px] font-semibold">
                                                {{ $chip }}
                                            </span>
                                        @elseif ($chip === 'OK' || $chip === 'Sudah OK')
                                            <x-status-chip status="close" :label="$chip" class="!px-2.5 !py-0.5 !text-[11px]" />
                                        @elseif ($chip === 'Belum OK' || $chip === 'not_ok')
                                            <x-status-chip status="nc" :label="$chip" class="!px-2.5 !py-0.5 !text-[11px]" />
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-[#E3EEFB] text-[#1B4C8C] px-2.5 py-0.5 text-[11px] font-semibold">
                                                {{ $chip }}
                                            </span>
                                        @endif
                                    @endforeach

                                    @if ($isFirstItem)
                                        <span class="inline-flex items-center rounded-full bg-[#14201A] px-2.5 py-0.5 text-[11px] font-semibold text-white">
                                            Terbaru
                                        </span>
                                    @endif
                                </div>

                                @if (filled($pres['note']))
                                    <div class="mt-1.5 rounded-[10px] border border-[#E1DED3] bg-[#FBFAF6] px-3 py-2 text-[13px] text-[#3F4A41] leading-relaxed break-words whitespace-pre-line">
                                        {!! nl2br(e($pres['note'])) !!}
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>

                @if ($remainingActivities->isNotEmpty())
                    <details class="group border-t border-[#E1DED3]">
                        <summary class="flex cursor-pointer select-none items-center justify-between px-[22px] py-3 text-[13px] font-semibold text-brand transition hover:bg-paper-soft focus-visible:outline-2 focus-visible:outline-brand">
                            <span>Tampilkan semua ({{ $activitiesTotalCount ?? $activities->count() }})</span>
                            <svg class="h-4 w-4 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </summary>
                        <ol class="divide-y divide-[#E1DED3]/40">
                            @foreach ($remainingActivities as $act)
                                @php
                                    $pres = \App\Services\FindingActivityPresenter::present($act);
                                    $isFirstItem = false;
                                    $isLastItem = $loop->last;
                                    $toneStyles = match ($pres['tone']) {
                                        'info' => 'bg-[#E3EEFB] text-[#1B4C8C]',
                                        'warn' => 'bg-[#FCE8D8] text-[#8F3F08]',
                                        'ok' => 'bg-[#E1F0E2] text-[#1F5B2A]',
                                        'neutral' => 'bg-[#EEEBE1] text-[#3F4A41]',
                                        default => 'bg-[#EEEBE1] text-[#3F4A41]',
                                    };
                                @endphp
                                <li class="relative flex items-start gap-3 px-[22px] py-[14px] text-[14px] leading-[1.5] transition">
                                    @if (! $isLastItem)
                                        <span class="absolute left-[33px] sm:left-[38px] top-[46px] bottom-[-14px] w-[2px] bg-[#E1DED3] z-0" aria-hidden="true"></span>
                                    @endif

                                    <div class="relative z-[1] flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full {{ $toneStyles }}" aria-hidden="true">
                                        @if ($pres['icon'] === 'arrow-up')
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                            </svg>
                                        @elseif ($pres['icon'] === 'calendar')
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        @elseif ($pres['icon'] === 'x-circle')
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        @elseif ($pres['icon'] === 'check')
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        @elseif ($pres['icon'] === 'pencil')
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                        @elseif ($pres['icon'] === 'flag')
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                                            </svg>
                                        @elseif ($pres['icon'] === 'document')
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        @else
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <circle cx="12" cy="12" r="9"></circle>
                                            </svg>
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1 pt-0.5">
                                        <p class="text-[14px] leading-[1.5] text-ink">
                                            <b class="font-semibold text-ink">{{ $pres['actor'] }}</b>
                                            <span>{{ $pres['verb'] }}</span>
                                            @if ($pres['object'] !== '')
                                                <b class="font-semibold text-ink">{{ $pres['object'] }}</b>
                                            @endif
                                        </p>

                                        <div class="mt-1 flex flex-wrap items-center gap-2">
                                            <time datetime="{{ $act->occurred_at ? $act->occurred_at->toIso8601String() : '' }}" class="text-[12px] text-[#5B665E] font-medium">
                                                {{ $pres['time_absolute'] }}
                                            </time>
                                            <span class="text-[12px] text-muted">· {{ $pres['time_relative'] }}</span>

                                            @foreach ($pres['chips'] as $chip)
                                                @if (str_starts_with($chip, 'Putaran'))
                                                    <span class="inline-flex items-center rounded-full bg-[#E3EEFB] text-[#1B4C8C] px-2.5 py-0.5 text-[11px] font-semibold">
                                                        {{ $chip }}
                                                    </span>
                                                @elseif ($chip === 'OK' || $chip === 'Sudah OK')
                                                    <x-status-chip status="close" :label="$chip" class="!px-2.5 !py-0.5 !text-[11px]" />
                                                @elseif ($chip === 'Belum OK' || $chip === 'not_ok')
                                                    <x-status-chip status="nc" :label="$chip" class="!px-2.5 !py-0.5 !text-[11px]" />
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-[#E3EEFB] text-[#1B4C8C] px-2.5 py-0.5 text-[11px] font-semibold">
                                                        {{ $chip }}
                                                    </span>
                                                @endif
                                            @endforeach
                                        </div>

                                        @if (filled($pres['note']))
                                            <div class="mt-1.5 rounded-[10px] border border-[#E1DED3] bg-[#FBFAF6] px-3 py-2 text-[13px] text-[#3F4A41] leading-relaxed break-words whitespace-pre-line">
                                                {!! nl2br(e($pres['note'])) !!}
                                            </div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </details>
                @endif
            @else
                <div class="p-6 text-center text-[13px] text-muted">
                    Belum ada aktivitas untuk temuan ini.
                </div>
            @endif
        </aside>
    </div>

    {{-- Script untuk validasi tombol Not OK dan multi-material table --}}
    <script>
        document.querySelectorAll('[data-finding-verification]').forEach((form) => {
            const note = form.querySelector('[data-verification-note]');
            const notOkButton = form.querySelector('[data-verification-not-ok]');

            if (note && notOkButton) {
                notOkButton.addEventListener('click', () => {
                    note.required = true;
                });
            }
        });

        function bindIncomingRowEvents(tr) {
            const input = tr.querySelector('.material-row-input');
            const addBtn = tr.querySelector('.btn-add-tag');
            const tagsContainer = tr.querySelector('.material-tags-container');
            const emptyHint = tr.querySelector('.tag-empty-hint');
            const deleteBtn = tr.querySelector('.btn-delete-row');

            function addTag() {
                if (!input || !tagsContainer) return;
                const val = input.value.trim();
                if (!val) {
                    input.focus();
                    return;
                }

                const existing = Array.from(tagsContainer.querySelectorAll('input[type="hidden"]')).map(el => el.value.toLowerCase());
                if (existing.includes(val.toLowerCase())) {
                    alert('"' + val + '" sudah ada di daftar material baris ini.');
                    return;
                }

                const span = document.createElement('span');
                span.className = 'inline-flex items-center gap-1.5 rounded-full bg-[#E9EFE5] border border-brand/40 px-3 py-1 text-[12px] font-semibold text-[#1F5B2A]';
                span.innerHTML = `<span>${val}</span><input type="hidden" name="items[${tr.dataset.rowIndex}][materials][]" value="${val}"><button type="button" class="btn-remove-tag text-[#1F5B2A] hover:text-alert font-bold ml-1 focus:outline-none" title="Hapus">&times;</button>`;
                span.querySelector('.btn-remove-tag').addEventListener('click', () => {
                    span.remove();
                    if (emptyHint) {
                        emptyHint.classList.toggle('hidden', tagsContainer.querySelectorAll('input[type="hidden"]').length > 0);
                    }
                });
                tagsContainer.appendChild(span);
                if (emptyHint) emptyHint.classList.add('hidden');

                input.value = '';
                input.focus();
            }

            addBtn?.addEventListener('click', (e) => {
                e.preventDefault();
                addTag();
            });

            input?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addTag();
                }
            });

            tr.querySelectorAll('.btn-remove-tag').forEach(btn => {
                btn.addEventListener('click', function () {
                    this.closest('span').remove();
                    if (emptyHint) {
                        emptyHint.classList.toggle('hidden', tagsContainer.querySelectorAll('input[type="hidden"]').length > 0);
                    }
                });
            });

            deleteBtn?.addEventListener('click', (e) => {
                e.preventDefault();
                const tbody = tr.closest('tbody');
                if (!tbody) return;
                const rows = tbody.querySelectorAll('tr');
                if (rows.length > 1) {
                    tr.remove();
                    renumberIncomingRows(tbody);
                } else {
                    tr.querySelectorAll('.material-tags-container span.inline-flex').forEach(s => s.remove());
                    if (emptyHint) emptyHint.classList.remove('hidden');
                    const reasonArea = tr.querySelector('textarea');
                    if (reasonArea) reasonArea.value = '';
                    if (input) input.value = '';
                }
            });
        }

        function renumberIncomingRows(tbody) {
            tbody.querySelectorAll('tr').forEach((row, i) => {
                row.dataset.rowIndex = i;
                const noSpan = row.querySelector('.row-no');
                if (noSpan) noSpan.textContent = i + 1;

                row.querySelectorAll('input[type="hidden"]').forEach(h => {
                    h.name = `items[${i}][materials][]`;
                });
                const textarea = row.querySelector('textarea');
                if (textarea) {
                    textarea.name = `items[${i}][reason]`;
                }
            });
        }

        document.querySelectorAll('[id^="incoming-tbody-"]').forEach(tbody => {
            tbody.querySelectorAll('tr').forEach(tr => {
                bindIncomingRowEvents(tr);
            });

            const findingId = tbody.id.replace('incoming-tbody-', '');
            const addRowBtn = document.getElementById('btn-add-material-row-' + findingId);
            const form = document.getElementById('incoming-form-' + findingId);

            addRowBtn?.addEventListener('click', (e) => {
                e.preventDefault();
                const rows = tbody.querySelectorAll('tr');
                const newIndex = rows.length;
                const tr = document.createElement('tr');
                tr.dataset.rowIndex = newIndex;
                tr.innerHTML = `
                    <td class="px-4 py-3 text-center font-medium text-ink align-top pt-4">
                        <span class="row-no">${newIndex + 1}</span>
                    </td>
                    <td class="px-4 py-3 align-top">
                        <div class="space-y-2">
                            <div class="flex gap-1.5">
                                <input
                                    type="text"
                                    placeholder="Ketik nama material..."
                                    class="material-row-input form-control text-[13px] h-[38px] flex-1 min-w-[150px]"
                                >
                                <button
                                    type="button"
                                    class="btn-add-tag inline-flex h-[38px] shrink-0 items-center justify-center rounded-[8px] bg-brand px-3 text-[12px] font-semibold text-white transition hover:bg-brand-dark"
                                >
                                    + Tambah
                                </button>
                            </div>
                            <div class="material-tags-container flex flex-wrap gap-1.5 min-h-[46px] p-2 rounded-[10px] border border-[#B9B5A6] bg-paper-soft/40 items-center">
                                <span class="tag-empty-hint text-[11px] text-muted italic">Belum ada material</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 align-top">
                        <textarea
                            name="items[${newIndex}][reason]"
                            rows="3"
                            placeholder="[isian teks]"
                            class="form-control text-[13px] resize-y min-h-[92px]"
                            required
                        ></textarea>
                    </td>
                    <td class="px-4 py-3 text-center align-top pt-4">
                        <button
                            type="button"
                            class="btn-delete-row inline-flex h-[36px] w-[36px] items-center justify-center rounded-[8px] bg-[#FEE2E2] text-[#EF4444] transition hover:bg-[#FCA5A5] focus:outline-none"
                            title="Hapus baris"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
                bindIncomingRowEvents(tr);
                tr.querySelector('.material-row-input')?.focus();
            });

            form?.addEventListener('submit', () => {
                tbody.querySelectorAll('tr').forEach(tr => {
                    const input = tr.querySelector('.material-row-input');
                    const tags = tr.querySelector('.material-tags-container');
                    if (input && tags && tags.querySelectorAll('input[type="hidden"]').length === 0 && input.value.trim()) {
                        tr.querySelector('.btn-add-tag')?.click();
                    }
                });
            });
        });
    </script>

    <style>
        /* Sembunyikan dropdown saat details tertutup */
        .recurring-finding-details > div { display: none; }
        .recurring-finding-details[open] > div { display: block; }

        /* Backdrop transparan — klik area luar menutup details */
        .recurring-finding-details[open] > summary::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 30;
            cursor: default;
            background: transparent;
        }

        /* Pastikan dropdown & summary di atas backdrop */
        .recurring-finding-details[open] > div { z-index: 41; }
        .recurring-finding-details[open] > summary { position: relative; z-index: 42; }
    </style>
@endsection
