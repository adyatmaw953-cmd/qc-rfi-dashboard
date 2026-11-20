@extends('layouts.module', [
    'active' => 'rfi',
    'pageTitle' => 'Detail RFI ' . $rfi->rfi_no,
    'pageCaption' => 'PT Mudalaya Indo',
])

@section('content')
<style>
/* ACC checked state */
#decision-container:has(#decision-acc:checked) .card-acc {
    background-color: #1F5B2A !important;
    border-color: #1F5B2A !important;
    color: #ffffff !important;
}
#decision-container:has(#decision-acc:checked) .card-acc .acc-icon {
    background-color: #ffffff !important;
    color: #1F5B2A !important;
}
#decision-container:has(#decision-acc:checked) .card-acc .acc-sub {
    color: #E8EFE6 !important;
}
#decision-container:has(#decision-acc:checked) .card-acc .acc-badge {
    display: flex !important;
}
#decision-container:has(#decision-acc:checked) .card-finding {
    border-color: #B9B5A6 !important;
    background-color: #ffffff !important;
    color: #5B665E !important;
}
#decision-container:has(#decision-acc:checked) .card-finding .finding-icon {
    background-color: #EEEBE1 !important;
    color: #5B665E !important;
}
#decision-container:has(#decision-acc:checked) .card-finding .finding-sub {
    color: #5B665E !important;
}

/* Finding checked state */
#decision-container:has(#decision-finding:checked) .card-finding {
    background-color: #B4470F !important;
    border-color: #B4470F !important;
    color: #ffffff !important;
}
#decision-container:has(#decision-finding:checked) .card-finding .finding-icon {
    background-color: #ffffff !important;
    color: #B4470F !important;
}
#decision-container:has(#decision-finding:checked) .card-finding .finding-sub {
    color: #FBE9DC !important;
}
#decision-container:has(#decision-finding:checked) .card-finding .finding-badge {
    display: flex !important;
}
#decision-container:has(#decision-finding:checked) .card-acc {
    border-color: #B9B5A6 !important;
    background-color: #ffffff !important;
    color: #5B665E !important;
}
#decision-container:has(#decision-finding:checked) .card-acc .acc-icon {
    background-color: #EEEBE1 !important;
    color: #5B665E !important;
}
#decision-container:has(#decision-finding:checked) .card-acc .acc-sub {
    color: #5B665E !important;
}
</style>

<div class="space-y-5">
    {{-- Navigasi Kembali --}}
    <div>
        <a href="{{ route('rfi.category', ['kategori' => $categoryCode]) }}"
           class="inline-flex min-h-[44px] items-center gap-1.5 text-[14px] font-semibold text-brand transition hover:text-brand-dark">
            <span aria-hidden="true">&larr;</span>
            <span>Kembali ke RFI {{ $category['label'] }}</span>
        </a>
    </div>

    {{-- Blok Identitas RFI --}}
    <div class="flex flex-wrap items-end justify-between gap-4 border-b border-line/60 pb-5">
        <div>
            <div class="text-[12px] font-medium uppercase tracking-[.06em] text-muted">
                RFI {{ $category['label'] }}
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-3">
                <span class="font-mono text-[36px] font-semibold tracking-tight text-ink">{{ $rfi->rfi_no }}</span>
                <x-status-chip :status="$rfi->status" />
            </div>
        </div>

        <div class="text-left text-[13px] leading-relaxed text-muted sm:text-right">
            Pengaju: <strong class="font-semibold text-ink">{{ $rfi->createdBy?->name ?? '—' }}</strong><br>
            Diajukan: {{ $rfi->submitted_at ? $rfi->submitted_at->copy()->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i \W\I\B') : '—' }}
        </div>
    </div>

    {{-- 1. Kartu Informasi Umum --}}
    <section class="rounded-2xl border border-line bg-white p-[26px]">
        <h2 class="mb-[18px] text-[18px] font-semibold text-ink">Informasi umum</h2>

        <x-detail-list class="grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
            {{-- Project --}}
            <x-detail-item label="Project">
                <span class="font-mono font-semibold text-brand">{{ $rfi->project->project_code }}</span>
                &mdash; {{ $rfi->project->project_name }}
            </x-detail-item>

            @if ($rfi->project?->customer)
                <x-detail-item label="Customer">
                    {{ $rfi->project->customer }}
                </x-detail-item>
            @endif

            {{-- Tanggal inspeksi --}}
            <x-detail-item label="Tanggal inspeksi">
                {{ ($rfi->date_inspect && empty($rfi->details['_draft_no_date'])) ? $rfi->date_inspect->copy()->setTimezone('Asia/Jakarta')->translatedFormat('d M Y') : '—' }}
            </x-detail-item>

            {{-- Catatan (span penuh) --}}
            <x-detail-item label="Catatan" :span="true">
                {{ $rfi->note ?: '' }}
            </x-detail-item>

            {{-- Field spesifik kategori (dirender generik dari config) --}}
            @php
                $categoryFields = array_merge($category['general_fields'] ?? [], $category['fields'] ?? []);
            @endphp
            @foreach ($categoryFields as $f)
                @if (isset($f['show_if']) && data_get($rfi->details, $f['show_if']['field']) !== $f['show_if']['value'])
                    @continue
                @endif
                @php
                    $val = $f['name'] === 'scheduled_at'
                        ? $rfi->scheduled_at?->copy()->setTimezone('Asia/Jakarta')->translatedFormat('d M Y')
                        : data_get($rfi->details, $f['name']);
                    $isMono = in_array($f['name'], ['surat_jalan_no', 'po_no', 'bom_no', 'drawing_no'], true);
                @endphp
                <x-detail-item :label="$f['label']" :span="($f['type'] ?? '') === 'textarea'">
                    @if ($val !== null && $val !== '')
                        @if (is_array($val))
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($val as $subVal)
                                    <span class="inline-flex items-center rounded-full bg-[#E9EFE5] px-2.5 py-0.5 text-[12px] font-medium text-[#1F5B2A]">
                                        {{ $subVal }}
                                    </span>
                                @endforeach
                            </div>
                        @elseif ($isMono)
                            <span class="font-mono">{{ $val }}</span>
                        @else
                            {{ $val }}
                        @endif
                    @endif
                </x-detail-item>
            @endforeach

            {{-- Detail lama (legacy) bila ada --}}
            @php
                $legacyKeys = [
                    'jenis_material' => 'Jenis material',
                    'spec' => 'Spesifikasi',
                    'quantity' => 'Jumlah',
                    'dimension' => 'Dimensi',
                ];
                $legacyItems = [];
                foreach ($legacyKeys as $k => $lbl) {
                    $val = data_get($rfi->details, $k);
                    if ($val !== null && $val !== '') {
                        $legacyItems[$lbl] = $val;
                    }
                }
            @endphp
            @if (!empty($legacyItems))
                <x-detail-item label="Detail lama" :span="true">
                    <span class="text-[13px] text-muted">
                        @foreach ($legacyItems as $lbl => $val)
                            <span>{{ $lbl }}: <strong class="text-ink font-medium">{{ $val }}</strong></span>{{ ! $loop->last ? ' · ' : '' }}
                        @endforeach
                    </span>
                </x-detail-item>
            @endif
        </x-detail-list>
    </section>

    {{-- 2. Kartu Tindakan QC (Tampil HANYA untuk QC/admin saat RFI open tanpa result) --}}
    @if ($canReview && $rfi->status === 'open' && $rfi->result === null)
        <section id="tindakan-qc" class="rounded-2xl border border-line bg-white p-[26px]">
            <h2 class="mb-1 text-[18px] font-semibold text-ink">Tindakan QC</h2>
            <p class="mb-[18px] text-[14px] leading-relaxed text-muted">Tentukan apakah material diterima (ACC) atau dibuatkan temuan (Finding).</p>

            {{-- Alert Ringkasan Validasi Error --}}
            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-[#E07B2E]/40 bg-[#FCE8D8] p-4 text-[13px] text-[#8F3F08]">
                    <p class="font-semibold mb-1">Terdapat kesalahan pada isian:</p>
                    <ul class="list-disc pl-5 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('rfi.review', ['kategori' => $categoryCode, 'rfi' => $rfi]) }}" id="form-tindakan-qc">
                @csrf
                <input type="hidden" name="result" id="input-result" value="{{ old('result', '') }}">

                {{-- Radio-card Keputusan QC --}}
                <div role="group" aria-label="Keputusan QC" id="decision-container" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Pilihan 1: ACC --}}
                    <label for="decision-acc"
                           class="card-acc relative flex min-h-[96px] cursor-pointer items-center gap-4 rounded-[16px] border-2 border-[#1F5B2A] bg-[#E1F0E2] p-[18px_20px] text-[#1F5B2A] transition box-border select-none has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand has-[:focus-visible]:ring-offset-2">
                        <input type="radio"
                               name="decision"
                               value="acc"
                               id="decision-acc"
                               class="sr-only"
                               @checked(old('decision') === 'acc' || old('result') === 'accepted')>
                        <span class="acc-icon flex h-[52px] w-[52px] shrink-0 items-center justify-center rounded-full bg-[#1F5B2A] text-white transition">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="m8 12 3 3 5-6"></path>
                            </svg>
                        </span>
                        <span class="block leading-[1.35]">
                            <span class="block text-[20px] font-semibold text-inherit">ACC</span>
                            <span class="acc-sub block text-[14px] font-medium text-[#1F5B2A] mt-0.5">Material diterima. RFI langsung Close.</span>
                        </span>
                        <span class="acc-badge hidden ml-auto h-[26px] w-[26px] shrink-0 items-center justify-center rounded-full bg-white text-[#1F5B2A]" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m5 12 5 5 9-10"></path>
                            </svg>
                        </span>
                    </label>

                    {{-- Pilihan 2: Finding --}}
                    <label for="decision-finding"
                           class="card-finding relative flex min-h-[96px] cursor-pointer items-center gap-4 rounded-[16px] border-2 border-[#E07B2E] bg-[#FCE8D8] p-[18px_20px] text-[#8F3F08] transition box-border select-none has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-[#B4470F] has-[:focus-visible]:ring-offset-2">
                        <input type="radio"
                               name="decision"
                               value="finding"
                               id="decision-finding"
                               class="sr-only"
                               @checked(old('decision') === 'finding' || in_array(old('result'), ['repair', 'use_as_is', 'reject'], true))>
                        <span class="finding-icon flex h-[52px] w-[52px] shrink-0 items-center justify-center rounded-full bg-[#B4470F] text-white transition">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m12 3.5 9 16H3l9-16Z"></path>
                                <path d="M12 9v4.5M12 17h.01"></path>
                            </svg>
                        </span>
                        <span class="block leading-[1.35]">
                            <span class="block text-[20px] font-semibold text-inherit">Finding</span>
                            <span class="finding-sub block text-[14px] font-medium text-[#8F3F08] mt-0.5">Buat temuan. RFI jadi NC dan dikirim ke Production.</span>
                        </span>
                        <span class="finding-badge hidden ml-auto h-[26px] w-[26px] shrink-0 items-center justify-center rounded-full bg-white text-[#B4470F]" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m5 12 5 5 9-10"></path>
                            </svg>
                        </span>
                    </label>
                </div>

                {{-- Hint saat belum memilih --}}
                <p id="hint-pilih-keputusan" class="mt-3.5 text-[13px] text-muted font-normal">Pilih salah satu. Keputusan ini mengubah status RFI.</p>

                {{-- Panel Aksi Saat ACC Dipilih --}}
                <div id="panel-acc" class="hidden mt-[22px] border-t border-[#E1DED3] pt-[22px]">
                    <div class="flex items-start gap-2.5 rounded-[12px] border border-line bg-paper-soft p-3 text-[14px] leading-relaxed text-[#3F4A41]">
                        <span class="text-[#366F3A] shrink-0 mt-0.5">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M12 8h.01M11 12h1v5h1"></path>
                            </svg>
                        </span>
                        <span>Setelah disimpan: RFI dinyatakan <b class="font-semibold text-close">Accepted (Close)</b>. Keputusan tidak dapat diubah setelah disimpan.</span>
                    </div>

                    <div class="mt-[18px] flex flex-wrap items-center justify-end gap-3">
                        <button type="button" class="btn-reset-decision inline-flex min-h-[48px] items-center justify-center rounded-[12px] border border-[#8C978B] bg-white px-5 text-[14px] font-semibold text-ink hover:bg-paper-soft transition">
                            Batal
                        </button>
                        <button type="submit" id="btn-submit-acc" class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] bg-[#1F5B2A] px-7 text-[14px] font-semibold text-white shadow-xs transition hover:bg-[#16431E] focus:outline-none focus:ring-2 focus:ring-[#1F5B2A] focus:ring-offset-2">
                            Simpan Keputusan ACC
                        </button>
                    </div>
                </div>

                {{-- Panel Detail Temuan Saat Finding Dipilih --}}
                <div id="panel-finding" class="hidden mt-[22px] border-t border-[#E1DED3] pt-[22px]">
                    <h3 class="mb-3.5 text-[16px] font-semibold text-ink">Detail temuan</h3>

                    {{-- Disposisi (3 Radio-card) --}}
                    <fieldset class="mb-[18px] border-0 p-0 m-0">
                        <legend class="mb-2 p-0 text-[14px] font-semibold text-ink">
                            Disposisi <span class="text-alert" aria-hidden="true">*</span>
                        </legend>
                        <div class="flex flex-wrap gap-2.5">
                            {{-- Repair --}}
                            <label class="flex min-w-[150px] flex-1 cursor-pointer items-start gap-2.5 rounded-[12px] border border-[#8C978B] bg-white p-[12px_14px] box-border transition hover:bg-paper-soft has-[:checked]:border-2 has-[:checked]:border-[#B4470F] has-[:checked]:bg-[#FCE8D8]">
                                <input type="radio"
                                       name="disp_option"
                                       value="repair"
                                       class="mt-0.5 h-[18px] w-[18px] shrink-0 accent-[#B4470F] text-[#B4470F]"
                                       @checked(old('result') === 'repair')>
                                <span class="text-[14px] leading-tight">
                                    <b class="block font-medium text-ink has-[:checked]:font-semibold">Repair</b>
                                    <span class="text-[12px] text-muted font-normal">Diperbaiki dulu</span>
                                </span>
                            </label>

                            {{-- Use as is --}}
                            <label class="flex min-w-[150px] flex-1 cursor-pointer items-start gap-2.5 rounded-[12px] border border-[#8C978B] bg-white p-[12px_14px] box-border transition hover:bg-paper-soft has-[:checked]:border-2 has-[:checked]:border-[#B4470F] has-[:checked]:bg-[#FCE8D8]">
                                <input type="radio"
                                       name="disp_option"
                                       value="use_as_is"
                                       class="mt-0.5 h-[18px] w-[18px] shrink-0 accent-[#B4470F] text-[#B4470F]"
                                       @checked(old('result') === 'use_as_is')>
                                <span class="text-[14px] leading-tight">
                                    <b class="block font-medium text-ink has-[:checked]:font-semibold">Use as is</b>
                                    <span class="text-[12px] text-muted font-normal">Dipakai apa adanya</span>
                                </span>
                            </label>

                            {{-- Reject --}}
                            <label class="flex min-w-[150px] flex-1 cursor-pointer items-start gap-2.5 rounded-[12px] border border-[#8C978B] bg-white p-[12px_14px] box-border transition hover:bg-paper-soft has-[:checked]:border-2 has-[:checked]:border-[#B4470F] has-[:checked]:bg-[#FCE8D8]">
                                <input type="radio"
                                       name="disp_option"
                                       value="reject"
                                       class="mt-0.5 h-[18px] w-[18px] shrink-0 accent-[#B4470F] text-[#B4470F]"
                                       @checked(old('result') === 'reject')>
                                <span class="text-[14px] leading-tight">
                                    <b class="block font-medium text-ink has-[:checked]:font-semibold">Reject</b>
                                    <span class="text-[12px] text-muted font-normal">Ditolak, tidak dipakai</span>
                                </span>
                            </label>
                        </div>
                        @error('result')
                            <p class="mt-1 text-[13px] text-alert font-medium">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    {{-- Catatan QC --}}
                    <div class="space-y-1.5">
                        <label for="qn" class="block text-[14px] font-semibold text-ink">
                            Catatan QC <span class="text-alert" aria-hidden="true">*</span>
                        </label>
                        <textarea id="qn"
                                  name="qc_note"
                                  rows="3"
                                  placeholder="Jelaskan ketidaksesuaian material yang ditemukan..."
                                  class="form-control min-h-[96px] resize-y leading-relaxed {{ $errors->has('qc_note') ? 'form-control-invalid' : '' }}">{{ old('qc_note') }}</textarea>
                        <div class="text-[12px] text-muted">Catatan ini terlihat oleh Production dan menjadi dasar temuan.</div>
                        @error('qc_note')
                            <p class="mt-1 text-[13px] text-alert font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Warning Banner --}}
                    <div class="mt-4 flex items-start gap-2.5 rounded-[12px] border border-line bg-paper-soft p-3 text-[14px] leading-relaxed text-[#3F4A41]">
                        <span class="text-[#366F3A] shrink-0 mt-0.5">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M12 8h.01M11 12h1v5h1"></path>
                            </svg>
                        </span>
                        <span>Setelah disimpan: RFI jadi <b class="font-semibold text-ink">NC</b>, temuan dibuat, dan Production menerima notifikasi untuk mengisi action plan. Keputusan tidak dapat diubah setelah disimpan.</span>
                    </div>

                    {{-- Tombol Aksi Finding --}}
                    <div class="mt-[18px] flex flex-wrap items-center justify-end gap-3">
                        <button type="button" class="btn-reset-decision inline-flex min-h-[48px] items-center justify-center rounded-[12px] border border-[#8C978B] bg-white px-5 text-[14px] font-semibold text-ink hover:bg-paper-soft transition">
                            Batal
                        </button>
                        <button type="submit" id="btn-submit-finding" class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] bg-[#B4470F] px-7 text-[14px] font-semibold text-white shadow-xs transition hover:bg-[#963B0C] focus:outline-none focus:ring-2 focus:ring-[#B4470F] focus:ring-offset-2">
                            Buat Temuan
                        </button>
                    </div>
                </div>
            </form>
        </section>
    @elseif ($rfi->result !== null || ($rfi->status !== 'open' && $rfi->status !== 'draft'))
        {{-- 3. Kartu Hasil QC (Read-Only saat RFI sudah punya hasil) --}}
        <section class="card rounded-2xl border border-line bg-white p-[26px]">
            <h2 class="mb-4 text-[18px] font-semibold text-ink">Hasil QC</h2>
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-[13px] font-medium text-muted">Disposisi:</span>
                    @php
                        $resVal = $rfi->result?->value;
                        $resLabel = $rfi->result?->label() ?? '—';
                        $chipClasses = match ($resVal) {
                            'accepted' => 'bg-[#E1F0E2] text-[#1F5B2A]',
                            'repair' => 'bg-[#FCE8D8] text-[#8F3F08]',
                            'use_as_is' => 'bg-[#EEEBE1] text-[#3F4A41]',
                            'reject' => 'bg-[#FEE2E2] text-[#991B1B]',
                            default => 'bg-[#E3EEFB] text-[#1B4C8C]',
                        };
                    @endphp
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[13px] font-semibold {{ $chipClasses }}">
                        {{ $resLabel }}
                    </span>
                    @if ($rfi->reviewedBy)
                        <span class="text-[12px] text-muted">
                            oleh <strong class="font-semibold text-ink">{{ $rfi->reviewedBy->name }}</strong>
                            @if ($rfi->reviewed_at)
                                pada {{ $rfi->reviewed_at->copy()->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i \W\I\B') }}
                            @endif
                        </span>
                    @endif
                </div>

                @if ($rfi->qc_note)
                    <div class="rounded-xl border border-line bg-paper-soft p-4">
                        <p class="text-[12px] font-semibold text-muted mb-1">Catatan QC:</p>
                        <p class="whitespace-pre-line text-[14px] text-ink leading-relaxed">{{ $rfi->qc_note }}</p>
                    </div>
                @endif

                @if ($rfi->status === 'nc' && $rfi->qcFinding)
                    <div class="pt-1">
                        <a href="{{ route('qc-finding.project', ['project' => $rfi->project_id, 'buka' => $rfi->qcFinding->id]) }}"
                           class="inline-flex min-h-[44px] items-center gap-1.5 font-semibold text-brand hover:text-brand-dark hover:underline text-[14px]">
                            <span>Lihat temuan di modul QC Finding</span>
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                @endif
            </div>
        </section>
    @elseif (! $canReview && $rfi->status === 'open')
        {{-- 4. Kartu Status Review untuk Non-QC --}}
        <section class="card rounded-2xl border border-line bg-white p-[26px]">
            <h2 class="mb-2 text-[18px] font-semibold text-ink">Hasil QC</h2>
            <div class="flex items-center gap-2 text-[14px] text-muted">
                <span class="h-2 w-2 rounded-full bg-brand" aria-hidden="true"></span>
                <span>Menunggu review dari tim QC / QA.</span>
            </div>
        </section>
    @endif
</div>

{{-- Vanilla JS untuk toggle ACC vs Finding dan sinkronisasi result --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const radioAcc = document.getElementById('decision-acc');
    const radioFinding = document.getElementById('decision-finding');
    const panelAcc = document.getElementById('panel-acc');
    const panelFinding = document.getElementById('panel-finding');
    const inputResult = document.getElementById('input-result');
    const form = document.getElementById('form-tindakan-qc');
    const hint = document.getElementById('hint-pilih-keputusan');
    const dispRadios = document.querySelectorAll('input[name="disp_option"]');

    function syncDecision() {
        if (radioAcc && radioAcc.checked) {
            if (panelAcc) panelAcc.classList.remove('hidden');
            if (panelFinding) panelFinding.classList.add('hidden');
            if (hint) hint.classList.add('hidden');
            if (inputResult) inputResult.value = 'accepted';
        } else if (radioFinding && radioFinding.checked) {
            if (panelAcc) panelAcc.classList.add('hidden');
            if (panelFinding) panelFinding.classList.remove('hidden');
            if (hint) hint.classList.add('hidden');
            const checkedDisp = document.querySelector('input[name="disp_option"]:checked');
            if (inputResult) inputResult.value = checkedDisp ? checkedDisp.value : '';
        } else {
            if (panelAcc) panelAcc.classList.add('hidden');
            if (panelFinding) panelFinding.classList.add('hidden');
            if (hint) hint.classList.remove('hidden');
            if (inputResult) inputResult.value = '';
        }
    }

    if (radioAcc) radioAcc.addEventListener('change', syncDecision);
    if (radioFinding) radioFinding.addEventListener('change', syncDecision);

    dispRadios.forEach(function (r) {
        r.addEventListener('change', function () {
            if (radioFinding && radioFinding.checked && inputResult) {
                inputResult.value = this.value;
            }
        });
    });

    document.querySelectorAll('.btn-reset-decision').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (radioAcc) radioAcc.checked = false;
            if (radioFinding) radioFinding.checked = false;
            dispRadios.forEach(function (r) { r.checked = false; });
            syncDecision();
        });
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.submitted === 'true') {
                e.preventDefault();
                return false;
            }
            form.dataset.submitted = 'true';
            const submitButtons = form.querySelectorAll('button[type="submit"]');
            submitButtons.forEach(function (b) {
                b.disabled = true;
                b.classList.add('opacity-70', 'cursor-not-allowed');
            });
        });
    }

    syncDecision();
});
</script>
@endsection
