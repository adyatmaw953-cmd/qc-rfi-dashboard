@extends('layouts.module', [
    'active' => 'mdr',
    'pageTitle' => 'Detail MDR',
    'pageCaption' => 'PT Mudalaya Indo',
])

@section('header_action')
    <a href="{{ route('mdr-checklist.index') }}"
       class="inline-flex min-h-[44px] items-center justify-center rounded-full border border-line bg-white px-5 text-[13px] font-semibold text-ink transition hover:bg-paper-soft">
        &larr; Kembali
    </a>
@endsection

@section('content')
    @php
        $completedCount = 0;
        foreach (array_keys($reports) as $reportKey) {
            $statusVal = old("statuses.{$reportKey}", $reportStatuses->get($reportKey)?->status?->value ?? 'open');
            if (in_array($statusVal, ['close', 'na'], true)) {
                $completedCount++;
            }
        }
        if (isset($customReports)) {
            foreach ($customReports as $idx => $cr) {
                $statusVal = old("custom_reports.{$idx}.status", $cr->status?->value ?? 'open');
                if (in_array($statusVal, ['close', 'na'], true)) {
                    $completedCount++;
                }
            }
        }
        $formattedUnit = \Illuminate\Support\Str::startsWith(trim((string) $mdr->unit_no), ['Unit', 'unit']) ? $mdr->unit_no : ($mdr->unit_no ? 'Unit ' . $mdr->unit_no : ($mdr->owner_serial_no ? 'SN: ' . $mdr->owner_serial_no : ''));
        $projectTitle = ($mdr->project->project_code ? $mdr->project->project_code . ' - ' : '') . $mdr->project->project_name . ($formattedUnit ? ', ' . $formattedUnit : '');
        $totalAllReports = count($reports) + (isset($customReports) ? count($customReports) : 0);
    @endphp

    <div class="mx-auto max-w-[1000px] space-y-4">
        {{-- 1. Kartu Info Project & Unit --}}
        <section class="rounded-[16px] border border-[#E1DED3] bg-white p-[26px]">
            <div class="flex flex-col min-[820px]:flex-row min-[820px]:items-center justify-between gap-4">
                <div>
                    <h2 class="text-[18px] font-semibold text-ink">{{ $projectTitle }}</h2>
                    <p class="mt-0.5 text-[13px] text-muted">Customer: <span class="font-medium text-ink">{{ $mdr->project->customer ?? '—' }}</span></p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[13px] font-medium text-muted">Status:</span>
                    <x-status-chip :status="$mdr->overall_status->value" :label="$mdr->overall_status->label()" />
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 border-t border-[#E1DED3] pt-4">
                <div>
                    <span class="block text-[12px] font-medium text-muted">Project</span>
                    <span class="mt-1 block text-[14px] font-semibold text-ink">
                        {{ $mdr->project->project_code ? $mdr->project->project_code . ' — ' : '' }}{{ $mdr->project->project_name }}
                    </span>
                </div>
                <div>
                    <span class="block text-[12px] font-medium text-muted">Customer</span>
                    <span class="mt-1 block text-[14px] font-semibold text-ink">
                        {{ $mdr->project->customer ?? '—' }}
                    </span>
                </div>
                @if ($mdr->owner_serial_no)
                    <div>
                        <span class="block text-[12px] font-medium text-muted">Owner Serial No</span>
                        <span class="mt-1 block text-[14px] font-semibold text-ink">
                            {{ $mdr->owner_serial_no }}
                        </span>
                    </div>
                @endif
                @if ($mdr->manufacture_serial_no)
                    <div>
                        <span class="block text-[12px] font-medium text-muted">Manufaktur Serial No</span>
                        <span class="mt-1 block text-[14px] font-semibold text-ink">
                            {{ $mdr->manufacture_serial_no }}
                        </span>
                    </div>
                @endif
            </div>
        </section>

        {{-- 2. Form Status Report --}}
        <form id="mdr-form" method="POST" action="{{ route('mdr-checklist.update', ['mdr' => $mdr]) }}" data-total-reports="{{ $totalAllReports }}" class="space-y-4">
            @csrf
            @method('PUT')

            <section class="rounded-[16px] border border-[#E1DED3] bg-white p-[26px]">
                <div class="flex flex-col gap-3 min-[820px]:flex-row min-[820px]:items-center min-[820px]:justify-between">
                    <div>
                        <h2 class="text-[18px] font-semibold text-ink">Status Report</h2>
                        <p class="mt-0.5 text-[13px] text-muted">16 report standar + report tambahan unit ini (Open, Close, atau N/A).</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="add-custom-report-btn" class="inline-flex min-h-[40px] items-center justify-center gap-1.5 rounded-[10px] border border-brand bg-[#E9EFE5] px-3.5 text-[13px] font-semibold text-brand transition hover:bg-brand hover:text-white">
                            + Tambah Report
                        </button>
                        <x-bulk-status-buttons />
                    </div>
                </div>

                @php
                    $reportEntries = $reports;
                    $chunks = array_chunk($reportEntries, (int) ceil(count($reportEntries) / 2), true);
                @endphp

                <div data-report-list class="mt-6 grid grid-cols-1 min-[820px]:grid-cols-2 gap-x-[36px] gap-y-0">
                    @foreach ($chunks as $chunk)
                        <div class="flex flex-col">
                            @foreach ($chunk as $reportKey => $reportLabel)
                                <div class="flex flex-col min-[820px]:flex-row min-[820px]:items-center justify-between border-t border-[#E1DED3] py-2.5 gap-2 min-[820px]:gap-4">
                                    <span class="text-[14px] font-medium text-ink min-w-0">{{ $reportLabel }}</span>
                                    <x-report-status-group
                                        name="statuses[{{ $reportKey }}]"
                                        :value="old('statuses.' . $reportKey, $reportStatuses->get($reportKey)?->status?->value ?? 'open')"
                                        :label="$reportLabel"
                                    />
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                {{-- Custom Reports Container --}}
                <div class="mt-6 border-t-2 border-brand/20 pt-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-[15px] font-semibold text-ink">Report Tambahan (Manual)</h3>
                        <span class="text-[12px] text-muted">Bisa menambah lebih dari satu report</span>
                    </div>
                    <div id="custom-reports-list" class="space-y-3">
                        @if (isset($customReports))
                            @foreach ($customReports as $idx => $cr)
                                @php
                                    $crStatus = old("custom_reports.{$idx}.status", $cr->status?->value ?? 'open');
                                    $crName = old("custom_reports.{$idx}.name", $cr->report_title ?? $cr->report_key);
                                @endphp
                                <div id="custom_row_{{ $idx }}" class="flex flex-col min-[820px]:flex-row min-[820px]:items-center justify-between border-t border-[#E1DED3] py-2.5 gap-2 min-[820px]:gap-4 bg-[#FAF9F5] p-3 rounded-lg border">
                                    <input type="hidden" name="custom_reports[{{ $idx }}][key]" value="{{ $cr->report_key }}">
                                    <div class="flex-1 flex items-center gap-2">
                                        <input type="text" name="custom_reports[{{ $idx }}][name]" value="{{ $crName }}" required class="form-control text-[14px]">
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="inline-flex rounded-lg border border-[#8C978B] bg-[#EEEBE1] p-0.5">
                                            <label class="cursor-pointer px-2.5 py-1 text-[12px] font-semibold rounded-md has-[:checked]:bg-[#1F5B2A] has-[:checked]:text-white">
                                                <input type="radio" name="custom_reports[{{ $idx }}][status]" value="close" @checked($crStatus === 'close') class="sr-only">
                                                Close
                                            </label>
                                            <label class="cursor-pointer px-2.5 py-1 text-[12px] font-semibold rounded-md has-[:checked]:bg-[#E07B2E] has-[:checked]:text-white">
                                                <input type="radio" name="custom_reports[{{ $idx }}][status]" value="open" @checked($crStatus === 'open') class="sr-only">
                                                Open
                                            </label>
                                            <label class="cursor-pointer px-2.5 py-1 text-[12px] font-semibold rounded-md has-[:checked]:bg-[#5B665E] has-[:checked]:text-white">
                                                <input type="radio" name="custom_reports[{{ $idx }}][status]" value="na" @checked($crStatus === 'na') class="sr-only">
                                                N/A
                                            </label>
                                        </div>
                                        <button type="button" onclick="document.getElementById('custom_row_{{ $idx }}').remove();" class="text-alert hover:text-alert/80 p-1 font-bold text-[18px]" title="Hapus report">&times;</button>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </section>

            {{-- 3. Summary & tombol aksi --}}
            <div class="space-y-4 pt-2">
                <x-mdr-summary-bar :total="$totalAllReports" :reports="$reports" :completed="$completedCount" />

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-[16px] border border-[#E1DED3] bg-white p-4 shadow-xs">
                    <div>
                        @if (in_array(auth()->user()?->role, ['admin', 'qc_qa'], true))
                            <button
                                type="button"
                                onclick="if (confirm('Hapus MDR untuk project ini? Seluruh data status report di dalamnya akan ikut dihapus.')) { document.getElementById('delete-mdr-form').submit(); }"
                                class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] border border-alert/30 px-5 text-[14px] font-semibold text-alert transition hover:bg-alert/10"
                            >
                                Hapus MDR
                            </button>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <a
                            href="{{ route('mdr-checklist.index') }}"
                            class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] border border-[#8C978B] bg-white px-6 text-[14px] font-semibold text-ink transition hover:bg-paper"
                        >
                            Kembali
                        </a>
                        <button
                            type="submit"
                            id="mdr-submit-btn"
                            class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] bg-brand px-7 text-[14px] font-semibold text-white transition hover:bg-brand-dark focus:outline-none focus-visible:outline-2 focus-visible:outline-brand focus-visible:outline-offset-2"
                        >
                            Simpan perubahan
                        </button>
                    </div>
                </div>
            </div>
        </form>

        @if (in_array(auth()->user()?->role, ['admin', 'qc_qa'], true))
            <form id="delete-mdr-form" method="POST" action="{{ route('mdr-checklist.destroy', ['mdr' => $mdr]) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </div>

    <script>
        let customReportIdx = {{ isset($customReports) ? count($customReports) + 10 : 10 }};
        const customReportsList = document.getElementById('custom-reports-list');
        const addCustomReportBtn = document.getElementById('add-custom-report-btn');

        function addCustomReportRow(name = '', status = 'open') {
            const rowId = `custom_report_${customReportIdx++}`;
            const div = document.createElement('div');
            div.id = rowId;
            div.className = 'flex flex-col min-[820px]:flex-row min-[820px]:items-center justify-between border-t border-[#E1DED3] py-2.5 gap-2 min-[820px]:gap-4 bg-[#FAF9F5] p-3 rounded-lg border';
            div.innerHTML = `
                <div class="flex-1 flex items-center gap-2">
                    <input type="text" name="custom_reports[${customReportIdx}][name]" value="${name}" required placeholder="Ketik nama report tambahan..." class="form-control text-[14px]">
                </div>
                <div class="flex items-center gap-3">
                    <div class="inline-flex rounded-lg border border-[#8C978B] bg-[#EEEBE1] p-0.5">
                        <label class="cursor-pointer px-2.5 py-1 text-[12px] font-semibold rounded-md has-[:checked]:bg-[#1F5B2A] has-[:checked]:text-white">
                            <input type="radio" name="custom_reports[${customReportIdx}][status]" value="close" ${status === 'close' ? 'checked' : ''} class="sr-only">
                            Close
                        </label>
                        <label class="cursor-pointer px-2.5 py-1 text-[12px] font-semibold rounded-md has-[:checked]:bg-[#E07B2E] has-[:checked]:text-white">
                            <input type="radio" name="custom_reports[${customReportIdx}][status]" value="open" ${status === 'open' ? 'checked' : ''} class="sr-only">
                            Open
                        </label>
                        <label class="cursor-pointer px-2.5 py-1 text-[12px] font-semibold rounded-md has-[:checked]:bg-[#5B665E] has-[:checked]:text-white">
                            <input type="radio" name="custom_reports[${customReportIdx}][status]" value="na" ${status === 'na' ? 'checked' : ''} class="sr-only">
                            N/A
                        </label>
                    </div>
                    <button type="button" onclick="document.getElementById('${rowId}').remove();" class="text-alert hover:text-alert/80 p-1 font-bold text-[18px]" title="Hapus report">&times;</button>
                </div>
            `;
            customReportsList.appendChild(div);
        }

        addCustomReportBtn?.addEventListener('click', () => {
            addCustomReportRow();
        });
    </script>
@endsection
