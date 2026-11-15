@extends('layouts.module', [
    'active' => 'ncr',
    'pageTitle' => $ncr->ncr_number,
    'pageCaption' => 'NCR',
    'backUrl' => route('ncr.index'),
    'backLabel' => 'Daftar NCR',
    'pageTitleClass' => 'font-mono',
])

@section('header_action')
    <div class="flex items-center gap-2.5">
        @can('update', $ncr)
            <a href="{{ route('ncr.edit', $ncr) }}"
               class="inline-flex min-h-[44px] items-center justify-center rounded-full bg-brand px-5 text-[13px] font-semibold text-white transition hover:bg-brand-dark">
                Edit NCR
            </a>
        @endcan
        @can('delete', $ncr)
            <form method="POST" action="{{ route('ncr.destroy', $ncr) }}" onsubmit="return confirm('Yakin ingin menghapus NCR {{ $ncr->ncr_number }}?');" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="inline-flex min-h-[44px] items-center justify-center rounded-full border border-[#B4470F] bg-white px-5 text-[13px] font-semibold text-[#8F3F08] transition hover:bg-red-50">
                    Hapus
                </button>
            </form>
        @endcan
    </div>
@endsection

@section('content')
    <div class="ncr-detail-grid grid items-start gap-6">

        {{-- Kolom Kiri: 5 Kartu Section --}}
        <div class="ncr-left-col space-y-4">

            {{-- 1. Temuan --}}
            <section class="rounded-2xl border border-line bg-white p-[26px]">
                <div class="border-b border-line pb-4">
                    <h2 class="text-[18px] font-semibold text-ink">1. Temuan</h2>
                    <p class="mt-0.5 text-[13px] text-muted">Identitas temuan dan project terkait.</p>
                </div>
                <div class="mt-5">
                    <x-detail-list>
                        <x-detail-item label="Nomor NCR">
                            <span class="font-mono font-semibold">{{ $ncr->ncr_number }}</span>
                        </x-detail-item>

                        <x-detail-item label="Tanggal Temuan / Bulan">
                            @if ($ncr->finding_date)
                                <span>{{ format_date($ncr->finding_date) }}</span>
                                @if ($ncr->month_name)
                                    <span class="text-muted">({{ $ncr->month_name }})</span>
                                @endif
                            @endif
                        </x-detail-item>

                        <x-detail-item label="Department" :value="$ncr->department" />

                        <x-detail-item label="Area Finding" :value="$ncr->area_finding" />

                        <x-detail-item label="Tipe NCR" :value="$ncr->type" />

                        <x-detail-item label="Project">
                            @if ($ncr->project_code)
                                <span class="font-mono font-semibold text-brand">{{ $ncr->project_code }}</span>
                                @if ($ncr->project?->project_name)
                                    <span class="text-ink"> — {{ $ncr->project->project_name }}</span>
                                @endif
                            @else
                                <span class="text-muted font-normal">— (Internal)</span>
                            @endif
                        </x-detail-item>

                        <x-detail-item label="Customer" :value="$ncr->customer" />

                        <x-detail-item label="Responsible PIC" :value="$ncr->responsible_pic" />

                        <x-detail-item label="Related Departments">
                            @if (! empty($ncr->related_departments))
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($ncr->related_departments as $rdept)
                                        <span class="inline-flex items-center rounded-md bg-[#E9EFE5] px-2.5 py-0.5 text-[12px] font-semibold text-[#1F5B2A]">
                                            {{ $rdept }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </x-detail-item>

                        <x-detail-item label="Uraian Masalah (Problem Description)" :span="true">
                            @if ($ncr->problem_description)
                                <div class="whitespace-pre-line leading-relaxed">{{ $ncr->problem_description }}</div>
                            @endif
                        </x-detail-item>
                    </x-detail-list>
                </div>
            </section>

            {{-- 2. Akar Masalah --}}
            <section class="rounded-2xl border border-line bg-white p-[26px]">
                <div class="border-b border-line pb-4">
                    <h2 class="text-[18px] font-semibold text-ink">2. Akar Masalah</h2>
                    <p class="mt-0.5 text-[13px] text-muted">Analisis akar masalah dan kategorinya.</p>
                </div>
                <div class="mt-5">
                    <x-detail-list>
                        <x-detail-item label="Kategori Akar Masalah (4M)">
                            @php
                                $categories = (array) ($ncr->root_cause_category ?? []);
                            @endphp
                            @if (! empty($categories))
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($categories as $cat)
                                        @if (trim((string) $cat) !== '')
                                            <span class="inline-flex items-center rounded-full bg-[#E9EFE5] px-3 py-1 text-[12px] font-semibold text-[#1F5B2A]">
                                                {{ trim((string) $cat) }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </x-detail-item>

                        <x-detail-item label="Akar Masalah (Root Cause)" :span="true">
                            @if ($ncr->root_cause)
                                <div class="whitespace-pre-line leading-relaxed">{{ $ncr->root_cause }}</div>
                            @endif
                        </x-detail-item>
                    </x-detail-list>
                </div>
            </section>

            {{-- 3. Corrective Action (CA) --}}
            <section class="rounded-2xl border border-line bg-white p-[26px]">
                <div class="border-b border-line pb-4">
                    <h2 class="text-[18px] font-semibold text-ink">3. Corrective Action (CA)</h2>
                    <p class="mt-0.5 text-[13px] text-muted">Tindakan koreksi langsung dan status penyelesaiannya.</p>
                </div>
                <div class="mt-5">
                    <x-detail-list>
                        <x-detail-item label="Tindakan Koreksi" :span="true">
                            @if ($ncr->corrective_action)
                                <div class="whitespace-pre-line leading-relaxed">{{ $ncr->corrective_action }}</div>
                            @endif
                        </x-detail-item>

                        <x-detail-item label="PIC CA" :value="$ncr->pic_ca" />

                        <x-detail-item label="Tanggal CA Closed" :value="$ncr->ca_closed_date ? format_date($ncr->ca_closed_date) : null" />

                        <x-detail-item label="Time Leads">
                            @if ($ncr->time_leads !== null)
                                <span class="font-mono font-semibold">{{ $ncr->time_leads }} hari</span>
                                <span class="text-[13px] text-muted font-normal">(sejak tanggal temuan)</span>
                            @endif
                        </x-detail-item>
                    </x-detail-list>
                </div>
            </section>

            {{-- 4. Preventive Action (PA) & Status --}}
            <section class="rounded-2xl border border-line bg-white p-[26px]">
                <div class="border-b border-line pb-4">
                    <h2 class="text-[18px] font-semibold text-ink">4. Preventive Action (PA) & Status</h2>
                    <p class="mt-0.5 text-[13px] text-muted">Tindakan pencegahan agar ketidaksesuaian tidak berulang.</p>
                </div>
                <div class="mt-5">
                    <x-detail-list>
                        <x-detail-item label="Tindakan Pencegahan" :span="true">
                            @if ($ncr->preventive_action)
                                <div class="whitespace-pre-line leading-relaxed">{{ $ncr->preventive_action }}</div>
                            @endif
                        </x-detail-item>

                        <x-detail-item label="PIC PA" :value="$ncr->pic_pa" />

                        <x-detail-item label="Tanggal PA Closed" :value="$ncr->pa_closed_date ? format_date($ncr->pa_closed_date) : null" />

                        <x-detail-item label="Status">
                            <x-status-chip :status="$ncr->status" />
                        </x-detail-item>
                    </x-detail-list>
                </div>
            </section>

            {{-- 5. NC Cost & Riwayat --}}
            <section class="rounded-2xl border border-line bg-white p-[26px]">
                <div class="border-b border-line pb-4">
                    <h2 class="text-[18px] font-semibold text-ink">5. NC Cost & Riwayat</h2>
                    <p class="mt-0.5 text-[13px] text-muted">Estimasi biaya akibat ketidaksesuaian dan informasi pencatatan.</p>
                </div>
                <div class="mt-5">
                    <x-detail-list>
                        <x-detail-item label="Total NC Cost">
                            <span class="font-mono text-[18px] font-semibold text-ink">
                                Rp {{ number_format($ncr->nc_cost ?? 0, 0, ',', '.') }}
                            </span>
                        </x-detail-item>

                        <x-detail-item label="Dibuat Oleh">
                            @if ($ncr->creator)
                                <span class="font-medium text-ink">{{ $ncr->creator->name }}</span>
                                @if ($ncr->creator->role)
                                    <span class="text-muted">({{ $ncr->creator->role }})</span>
                                @endif
                            @endif
                        </x-detail-item>

                        <x-detail-item label="Tanggal Dibuat" :value="$ncr->created_at ? format_date($ncr->created_at) . ' ' . $ncr->created_at->timezone('Asia/Jakarta')->format('H:i') : null" />
                    </x-detail-list>
                </div>
            </section>

        </div>

        {{-- Kolom Kanan: Ringkasan NCR --}}
        <aside aria-label="Ringkasan NCR" class="ncr-summary-card rounded-2xl border border-line bg-white p-6">
            <div class="flex items-center gap-2">
                <x-level-chip :level="$ncr->level" />
                <x-status-chip :status="$ncr->status" />
            </div>

            <div class="mt-5 border-t border-line pt-4">
                <p class="text-[13px] font-medium text-muted">Total NC Cost</p>
                <p class="mt-1 font-mono text-[28px] font-semibold leading-tight text-ink">
                    Rp {{ number_format($ncr->nc_cost ?? 0, 0, ',', '.') }}
                </p>
            </div>

            <div class="mt-4 border-t border-line pt-4">
                <p class="text-[13px] font-medium text-muted">Time Leads</p>
                <p class="mt-1 font-mono text-[20px] font-semibold text-ink">
                    {{ $ncr->time_leads !== null ? $ncr->time_leads . ' hari' : '-' }}
                </p>
            </div>

            <div class="mt-5 border-t border-line pt-4">
                <h3 class="mb-4 text-[14px] font-semibold text-ink">Status</h3>
                <x-ncr-stepper :ncr="$ncr" />
            </div>
        </aside>

    </div>

    <style>
        @media (min-width: 980px) {
            .ncr-detail-grid {
                grid-template-columns: 1fr 300px;
            }
            .ncr-summary-card {
                position: sticky;
                top: 16px;
                order: 2;
            }
            .ncr-left-col {
                order: 1;
            }
        }
        @media (max-width: 979px) {
            .ncr-detail-grid {
                grid-template-columns: 1fr;
            }
            .ncr-summary-card {
                position: static;
                order: 1;
            }
            .ncr-left-col {
                order: 2;
            }
        }
    </style>
@endsection
