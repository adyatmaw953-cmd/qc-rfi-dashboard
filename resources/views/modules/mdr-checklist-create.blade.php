@extends('layouts.module', [
    'active' => 'mdr',
    'pageTitle' => 'New MDR',
    'pageCaption' => 'PT Mudalaya Indo',
])

@section('header_action')
    <a href="{{ route('mdr-checklist.index') }}"
       class="inline-flex min-h-[44px] items-center justify-center rounded-full border border-line bg-white px-5 text-[13px] font-semibold text-ink transition hover:bg-paper-soft">
        &larr; Kembali
    </a>
@endsection

@section('content')
    @if ($projects->isEmpty())
        <div class="mx-auto max-w-[1000px] rounded-2xl border border-line bg-white p-6 text-[14px] text-muted shadow-xs">
            Belum ada project yang tersedia.
        </div>
    @else
        <form id="mdr-form" method="POST" action="{{ route('mdr-checklist.store') }}" data-total-reports="{{ count($reports) }}" class="mx-auto max-w-[1000px] space-y-4">
            @csrf

            {{-- 1. Kartu Project dan Unit --}}
            <section class="rounded-[16px] border border-[#E1DED3] bg-white p-[26px]">
                <h2 class="text-[18px] font-semibold text-ink">Project dan Identitas Unit</h2>
                <p class="mt-0.5 text-[13px] text-muted">Pilih project, lalu isi serial number yang akan dibuatkan MDR.</p>

                <div class="mt-5 grid grid-cols-1 min-[820px]:grid-cols-2 gap-5">
                    {{-- Project --}}
                    <div>
                        <label for="project-id" class="block text-[14px] font-semibold text-ink">Project <span class="text-[#B4470F]">*</span></label>
                        <div class="relative mt-2">
                            <select
                                id="project-id"
                                name="project_id"
                                required
                                @error('project_id') aria-invalid="true" aria-describedby="project_id-error" @enderror
                                class="w-full min-h-[48px] rounded-[10px] border appearance-none pr-10 pl-3.5 text-[15px] text-ink transition focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand {{ $errors->has('project_id') ? 'border-[1.5px] border-[#B4470F] bg-white' : 'border-[#8C978B] bg-white' }}"
                            >
                                <option value="">Pilih project</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" data-customer="{{ $project->customer ?? '' }}" @selected((string) old('project_id') === (string) $project->id)>
                                        {{ $project->project_code ? $project->project_code . ' — ' : '' }}{{ $project->project_name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-muted">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/></svg>
                            </div>
                        </div>
                        @error('project_id')
                            <p id="project_id-error" role="alert" class="mt-1.5 text-[13px] font-medium text-[#8F3F08]">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Customer (Read-only) --}}
                    <div>
                        <span class="block text-[14px] font-semibold text-ink">Customer</span>
                        <div class="mt-2 flex min-h-[48px] flex-col justify-center rounded-[12px] border border-[#E1DED3] bg-[#F4F2EB] px-4 py-3">
                            <span id="project-customer" class="text-[14px] font-semibold text-ink leading-tight">— (pilih project dulu)</span>
                        </div>
                        <p class="mt-1.5 text-[12px] text-muted">Terisi otomatis dari project</p>
                    </div>

                    {{-- Owner Serial No --}}
                    <div>
                        <label for="owner-serial-no" class="block text-[14px] font-semibold text-ink">Owner Serial No <span class="text-[#B4470F]">*</span></label>
                        <input
                            type="text"
                            id="owner-serial-no"
                            name="owner_serial_no"
                            value="{{ old('owner_serial_no') }}"
                            maxlength="100"
                            required
                            placeholder="Contoh: OSN-2026-001"
                            @error('owner_serial_no') aria-invalid="true" aria-describedby="owner_serial_no-error" @enderror
                            class="mt-2 w-full min-h-[48px] rounded-[10px] border px-3.5 text-[15px] text-ink transition placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand {{ $errors->has('owner_serial_no') ? 'border-[1.5px] border-[#B4470F] bg-white' : 'border-[#8C978B] bg-white' }}"
                        >
                        @error('owner_serial_no')
                            <p id="owner_serial_no-error" role="alert" class="mt-1.5 text-[13px] font-medium text-[#8F3F08]">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Manufaktur Serial No --}}
                    <div>
                        <label for="manufacture-serial-no" class="block text-[14px] font-semibold text-ink">Manufaktur Serial No <span class="text-[#B4470F]">*</span></label>
                        <input
                            type="text"
                            id="manufacture-serial-no"
                            name="manufacture_serial_no"
                            value="{{ old('manufacture_serial_no') }}"
                            maxlength="100"
                            required
                            placeholder="Contoh: MSN-2026-001"
                            @error('manufacture_serial_no') aria-invalid="true" aria-describedby="manufacture_serial_no-error" @enderror
                            class="mt-2 w-full min-h-[48px] rounded-[10px] border px-3.5 text-[15px] text-ink transition placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand {{ $errors->has('manufacture_serial_no') ? 'border-[1.5px] border-[#B4470F] bg-white' : 'border-[#8C978B] bg-white' }}"
                        >
                        @error('manufacture_serial_no')
                            <p id="manufacture_serial_no-error" role="alert" class="mt-1.5 text-[13px] font-medium text-[#8F3F08]">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>

            {{-- 2. Kartu Status Report --}}
            <section class="rounded-[16px] border border-[#E1DED3] bg-white p-[26px]">
                <div class="flex flex-col gap-3 min-[820px]:flex-row min-[820px]:items-center min-[820px]:justify-between">
                    <div>
                        <h2 class="text-[18px] font-semibold text-ink">Status Report</h2>
                        <p class="mt-0.5 text-[13px] text-muted">16 report standar + report tambahan manual yang diperlukan.</p>
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
                                        :value="old('statuses.' . $reportKey, 'open')"
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
                        {{-- Populated dynamically --}}
                    </div>
                </div>
            </section>

            {{-- 3. Ringkasan & Tombol Aksi --}}
            <div class="space-y-4 pt-2">
                <x-mdr-summary-bar :total="count($reports)" :reports="$reports" />

                <div class="flex items-center justify-end gap-3 rounded-[16px] border border-[#E1DED3] bg-white p-4 shadow-xs">
                    <a
                        href="{{ route('mdr-checklist.index') }}"
                        class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] border border-[#8C978B] bg-white px-6 text-[14px] font-semibold text-ink transition hover:bg-paper"
                    >
                        Batal
                    </a>
                    <button
                        type="submit"
                        id="mdr-submit-btn"
                        class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] bg-brand px-7 text-[14px] font-semibold text-white transition hover:bg-brand-dark focus:outline-none focus-visible:outline-2 focus-visible:outline-brand focus-visible:outline-offset-2"
                    >
                        Simpan MDR
                    </button>
                </div>
            </div>
        </form>
    @endif

    {{-- Script Customer Autofill --}}
    <script>
        const projectSelect = document.getElementById('project-id');
        const customerLabel = document.getElementById('project-customer');

        projectSelect?.addEventListener('change', () => {
            customerLabel.textContent = projectSelect.selectedOptions[0]?.dataset.customer || '— (pilih project dulu)';
        });

        projectSelect?.dispatchEvent(new Event('change'));

        // Custom Reports Management
        let customReportIdx = 0;
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
