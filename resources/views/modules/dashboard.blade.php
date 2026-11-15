@extends('layouts.module', ['active' => 'dashboard', 'pageTitle' => 'Dashboard'])

@section('header_action')
    <x-create-rfi-button />
@endsection

@section('content')
    {{-- Segmented Control / Tabs --}}
    <nav aria-label="Bagian dashboard" class="mb-6 flex flex-wrap gap-2">
        <a href="{{ route('dashboard', ['tab' => 'ringkasan']) }}"
           class="inline-flex min-h-[44px] items-center gap-2 rounded-[10px] border px-4 text-[14px] transition {{ $activeTab === 'ringkasan' ? 'border-ink bg-ink font-semibold text-white' : 'border-line bg-white font-medium text-ink hover:bg-paper' }}"
           @if ($activeTab === 'ringkasan') aria-current="page" @endif>
            Ringkasan
        </a>
        <a href="{{ route('dashboard', ['tab' => 'qc-finding']) }}"
           class="inline-flex min-h-[44px] items-center gap-2 rounded-[10px] border px-4 text-[14px] transition {{ $activeTab === 'qc-finding' ? 'border-ink bg-ink font-semibold text-white' : 'border-line bg-white font-medium text-ink hover:bg-paper' }}"
           @if ($activeTab === 'qc-finding') aria-current="page" @endif>
            QC Finding
        </a>
        @if ($canViewNcr)
            <a href="{{ route('dashboard', ['tab' => 'ncr', 'ncr_year' => $ncrData['selectedYear'] ?? null]) }}"
               class="inline-flex min-h-[44px] items-center gap-2 rounded-[10px] border px-4 text-[14px] transition {{ $activeTab === 'ncr' ? 'border-ink bg-ink font-semibold text-white' : 'border-line bg-white font-medium text-ink hover:bg-paper' }}"
               @if ($activeTab === 'ncr') aria-current="page" @endif>
                NCR
            </a>
        @endif
    </nav>

    <div data-tab-panel>
    {{-- ========================================================================= --}}
    {{-- TAB 1: RINGKASAN                                                          --}}
    {{-- ========================================================================= --}}
    @if ($activeTab === 'ringkasan')
        {{-- 1. Section "Perlu tindakan Anda" --}}
        <section aria-labelledby="heading-actions" class="mb-8">
            <h2 id="heading-actions" class="mb-3.5 text-[18px] font-semibold text-ink">Perlu tindakan Anda</h2>

            @if ($actionItems['hasActions'])
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {{-- Card 1: RFI menunggu review --}}
                    @if ($actionItems['rfiReview']['show'])
                        @php
                            $rfiCount = $actionItems['rfiReview']['count'];
                            $rfiDays = $actionItems['rfiReview']['oldestDays'];
                            $rfiDesc = $rfiCount > 0
                                ? ($rfiDays > 0 ? "Paling lama menunggu {$rfiDays} hari." : 'Menunggu review QC hari ini.')
                                : 'Semua RFI saat ini sudah direview.';
                        @endphp
                        <x-action-card
                            title="RFI menunggu review"
                            :count="$rfiCount"
                            :description="$rfiDesc"
                            button-label="Review sekarang"
                            :button-url="route('rfi.index')"
                            :is-primary="true"
                            color="blue"
                            icon="rfi"
                        />
                    @endif

                    {{-- Card 2: Re-Inspect menunggu verifikasi --}}
                    @if ($actionItems['reinspectVerify']['show'])
                        @php
                            $verifyCount = $actionItems['reinspectVerify']['count'];
                            $verifyDesc = $verifyCount > 0
                                ? 'Production sudah mengirim bukti perbaikan.'
                                : 'Tidak ada pengajuan verifikasi yang tertunda.';
                        @endphp
                        <x-action-card
                            title="Re-Inspect menunggu verifikasi"
                            :count="$verifyCount"
                            :description="$verifyDesc"
                            button-label="Verifikasi"
                            :button-url="route('qc-finding.index')"
                            :is-primary="false"
                            color="blue"
                            icon="verify"
                        />
                    @endif

                    {{-- Card 3: Temuan lewat due date --}}
                    @if ($actionItems['overdueFindings']['show'])
                        @php
                            $overdueCount  = $actionItems['overdueFindings']['count'];
                            $dueTodayCount = $actionItems['overdueFindings']['dueTodayCount'];
                            $overdueDays   = $actionItems['overdueFindings']['oldestDays'];
                            $totalUrgent   = $overdueCount + $dueTodayCount;

                            if ($totalUrgent === 0) {
                                $cardTitle   = 'Tidak ada yang perlu ditindak';
                                $cardCount   = null;
                                $overdueDesc = 'Tidak ada temuan yang jatuh tempo atau terlambat.';
                            } else {
                                $cardTitle   = 'Temuan lewat due date';
                                $cardCount   = $totalUrgent;
                                $parts = [];
                                if ($dueTodayCount > 0) {
                                    $parts[] = "{$dueTodayCount} temuan jatuh tempo hari ini";
                                }
                                if ($overdueCount > 0) {
                                    $suffix = ($dueTodayCount === 0 && $overdueDays > 0) ? " (terlama {$overdueDays} hari)" : '';
                                    $parts[] = "{$overdueCount} terlambat{$suffix}";
                                }
                                $overdueDesc = ucfirst(implode(', ', $parts)).'.';
                            }
                        @endphp
                        <x-action-card
                            :title="$cardTitle"
                            :count="$cardCount"
                            :description="$overdueDesc"
                            button-label="Lihat temuan"
                            :button-url="route('qc-finding.index', ['pf' => 'terlambat'])"
                            :is-primary="false"
                            color="orange"
                            icon="overdue"
                        />
                    @endif
                </div>
            @else
                <div class="rounded-2xl border border-line bg-white p-6 text-center text-[14px] text-muted">
                    Tidak ada yang perlu ditindak sekarang.
                </div>
            @endif
        </section>

        {{-- 2. Section "Ringkasan modul" --}}
        <section aria-labelledby="heading-modules-summary" class="mb-8">
            <h2 id="heading-modules-summary" class="mb-3.5 text-[18px] font-semibold text-ink">Ringkasan modul</h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {{-- Modul RFI --}}
                @if (isset($modules['rfi']))
                    <x-module-summary-card
                        title="RFI"
                        icon="rfi"
                        :detail-url="route('rfi.index')"
                        :stats="[
                            ['label' => 'Open', 'value' => $counts['open'] ?? 0],
                            ['label' => 'NC', 'value' => $counts['nc'] ?? 0],
                            ['label' => 'Close', 'value' => $counts['close'] ?? 0],
                        ]"
                    />
                @endif

                {{-- Modul QC Finding --}}
                @if (isset($modules['qc_finding']))
                    <x-module-summary-card
                        title="QC Finding"
                        icon="qc_finding"
                        :detail-url="route('qc-finding.index')"
                        :stats="[
                            ['label' => 'Belum closed', 'value' => $findingSummary['openCount']],
                            ['label' => 'Terlambat', 'value' => $findingSummary['overdueCount']],
                            ['label' => 'Closed bulan ini', 'value' => $findingSummary['closedThisMonth']],
                        ]"
                    />
                @endif

                {{-- Modul NCR --}}
                @if (isset($modules['ncr']) && $canViewNcr)
                    @php
                        $compactCost = \App\Services\DashboardSummary::formatCompactRupiah($ncrData['cards']['totalNcCost'] ?? 0);
                    @endphp
                    <x-module-summary-card
                        title="NCR {{ $ncrData['selectedYear'] ?? '' }}"
                        icon="ncr"
                        :detail-url="route('ncr.index')"
                        :stats="[
                            ['label' => 'Total', 'value' => $ncrData['cards']['totalNcr'] ?? 0],
                            ['label' => 'Open', 'value' => $ncrData['cards']['openNcr'] ?? 0],
                            ['label' => 'NC Cost', 'value' => $compactCost],
                        ]"
                    />
                @endif
            </div>
        </section>

        {{-- 3. Section "Grafik utama" --}}
        <section aria-labelledby="heading-main-charts" class="mb-8">
            <h2 id="heading-main-charts" class="mb-3.5 text-[18px] font-semibold text-ink">Grafik utama</h2>

            <div class="grid gap-4 {{ $canViewNcr ? 'lg:grid-cols-2' : '' }}">
                {{-- Grafik 1: QC Finding per Bulan --}}
                <div class="flex flex-col justify-between rounded-2xl border border-line bg-white p-[22px]">
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-[16px] font-semibold text-ink">QC Finding per Bulan</h3>
                                <p class="mt-0.5 mb-3 text-[13px] text-muted">Closed dibanding belum closed</p>
                            </div>
                            <a href="{{ route('qc-finding.index') }}"
                               class="inline-flex min-h-[44px] items-center text-[13px] font-semibold text-brand transition hover:text-brand-dark">
                                QC Finding &rarr;
                            </a>
                        </div>
                        <div class="mb-2.5 flex items-center gap-4 text-[13px] text-muted">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-2.5 w-2.5 rounded-[3px] bg-close" aria-hidden="true"></span>
                                Closed
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-2.5 w-2.5 rounded-[3px] bg-nc" aria-hidden="true"></span>
                                Belum Closed
                            </span>
                        </div>
                    </div>
                    <x-bar-chart
                        chart-id="chart-finding-status-summary"
                        title="Grafik batang QC Finding per bulan (Closed vs Belum Closed)"
                        :months="$findingStatusChart['months']"
                        :series="$findingStatusChart['series']"
                        layout="grouped"
                        :show-values="true"
                    />
                </div>

                @if ($canViewNcr)
                {{-- Grafik 2: Tren Jumlah NCR per Bulan --}}
                <div class="flex flex-col justify-between rounded-2xl border border-line bg-white p-[22px]">
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-[16px] font-semibold text-ink">Tren Jumlah NCR per Bulan</h3>
                                <p class="mt-0.5 mb-3 text-[13px] text-muted">Januari sampai Desember {{ $ncrData['selectedYear'] }}</p>
                            </div>
                            <a href="{{ route('dashboard', ['tab' => 'ncr', 'ncr_year' => $ncrData['selectedYear']]) }}"
                               class="inline-flex min-h-[44px] items-center text-[13px] font-semibold text-brand transition hover:text-brand-dark">
                                Dashboard NCR lengkap &rarr;
                            </a>
                        </div>
                    </div>
                    <x-bar-chart
                        chart-id="chart-ncr-monthly-summary"
                        title="Grafik batang tren NCR per bulan"
                        :months="array_map(fn($m) => mb_substr($m, 0, 3), $ncrData['charts']['monthlyTrend']['labels'])"
                        :series="['NCR' => $ncrData['charts']['monthlyTrend']['data']]"
                        layout="stacked"
                        :show-values="true"
                    />
                </div>
                @endif
            </div>
        </section>

    {{-- ========================================================================= --}}
    {{-- TAB 2: QC FINDING                                                         --}}
    {{-- ========================================================================= --}}
    @elseif ($activeTab === 'qc-finding')
        <x-qc-finding-overview
            :overview="$qcDashboard"
            :base-url="route('dashboard')"
            :extra-params="['tab' => 'qc-finding']"
        />

    {{-- ========================================================================= --}}
    {{-- TAB 3: NCR                                                                --}}
    {{-- ========================================================================= --}}
    @elseif ($activeTab === 'ncr' && $canViewNcr)
        <section aria-label="Dashboard NCR">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-[20px] font-semibold text-ink">Dashboard NCR</h2>
                    <p class="text-xs text-muted">Statistik dan analitik Non-Conformance Report tahun {{ $ncrData['selectedYear'] }}</p>
                </div>
                <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2">
                    <input type="hidden" name="tab" value="ncr">
                    <label for="ncr_year" class="text-xs font-semibold text-muted">Tahun:</label>
                    <select id="ncr_year" name="ncr_year" onchange="this.form.submit()" class="rounded-lg border border-line bg-white px-3 py-1.5 text-xs font-semibold text-ink focus:border-brand focus:outline-none">
                        @foreach ($ncrData['years'] as $yr)
                            <option value="{{ $yr }}" @selected($ncrData['selectedYear'] === $yr)>{{ $yr }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            {{-- Summary Cards --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                {{-- Total NCR --}}
                <div class="rounded-2xl border border-line bg-white p-4">
                    <span class="text-xs font-medium text-muted">Total NCR</span>
                    <div class="mt-2 font-mono text-2xl font-bold text-ink tabular-nums" data-count-to="{{ (int) ($ncrData['cards']['totalNcr'] ?? 0) }}">
                        {{ $ncrData['cards']['totalNcr'] }}
                    </div>
                </div>

                {{-- Open NCR --}}
                <div class="rounded-2xl border border-line bg-white p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-muted">Open NCR</span>
                        <span class="h-2 w-2 rounded-full bg-open"></span>
                    </div>
                    <div class="mt-2 font-mono text-2xl font-bold text-ink tabular-nums" data-count-to="{{ (int) ($ncrData['cards']['openNcr'] ?? 0) }}">
                        {{ $ncrData['cards']['openNcr'] }}
                    </div>
                </div>

                {{-- Closed NCR --}}
                <div class="rounded-2xl border border-line bg-white p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-muted">Closed NCR</span>
                        <span class="h-2 w-2 rounded-full bg-close"></span>
                    </div>
                    <div class="mt-2 font-mono text-2xl font-bold text-ink tabular-nums" data-count-to="{{ (int) ($ncrData['cards']['closedNcr'] ?? 0) }}">
                        {{ $ncrData['cards']['closedNcr'] }}
                    </div>
                </div>

                {{-- Total NC Cost --}}
                <div class="rounded-2xl border border-line bg-white p-4">
                    <span class="text-xs font-medium text-muted">Total NC Cost</span>
                    <div class="mt-2 font-mono text-lg font-bold text-ink truncate tabular-nums"
                         title="{{ $ncrData['cards']['totalNcCostFormatted'] }}"
                         data-count-to="{{ (float) ($ncrData['cards']['totalNcCost'] ?? 0) }}"
                         data-prefix="Rp ">
                        {{ $ncrData['cards']['totalNcCostFormatted'] }}
                    </div>
                </div>

                {{-- Total Major NCR --}}
                <div class="rounded-2xl border border-line bg-white p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-muted">Total Major</span>
                        <span class="h-2 w-2 rounded-full bg-alert"></span>
                    </div>
                    <div class="mt-2 font-mono text-2xl font-bold text-alert tabular-nums" data-count-to="{{ (int) ($ncrData['cards']['totalMajorNcr'] ?? 0) }}">
                        {{ $ncrData['cards']['totalMajorNcr'] }}
                    </div>
                </div>

                {{-- High NC Department --}}
                <div class="rounded-2xl border border-line bg-white p-4">
                    <span class="text-xs font-medium text-muted">High NC Dept</span>
                    <div class="mt-2 text-sm font-bold text-ink truncate" title="{{ $ncrData['cards']['highDepartment'] }}">
                        {{ $ncrData['cards']['highDepartment'] }}
                    </div>
                </div>

                {{-- High Root Cause --}}
                <div class="rounded-2xl border border-line bg-white p-4">
                    <span class="text-xs font-medium text-muted">High Root Cause</span>
                    <div class="mt-2 text-sm font-bold text-ink truncate" title="{{ $ncrData['cards']['highRootCauseCategory'] }}">
                        {{ $ncrData['cards']['highRootCauseCategory'] }}
                    </div>
                </div>
            </div>

            {{-- Charts Grid --}}
            <div class="mt-6 grid gap-5 lg:grid-cols-2">
                {{-- 1. NCR Trend per Bulan (12 Bulan) --}}
                <div class="flex flex-col rounded-2xl border border-line bg-white p-5">
                    <h3 class="text-sm font-semibold text-ink">Tren NCR per Bulan ({{ $ncrData['selectedYear'] }})</h3>
                    <div class="mt-4">
                        <x-bar-chart
                            chart-id="chart-ncr-monthly"
                            title="Grafik tren NCR per bulan"
                            :months="array_map(fn($m) => mb_substr($m, 0, 3), $ncrData['charts']['monthlyTrend']['labels'])"
                            :series="['Close' => $ncrData['charts']['monthlyTrend']['data']]"
                            layout="stacked"
                            :show-values="true"
                        />
                    </div>
                </div>

                {{-- 2. NC Cost Trend per Bulan (12 Bulan) --}}
                <div class="flex flex-col rounded-2xl border border-line bg-white p-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-ink">Tren NC Cost per Bulan (Rp)</h3>
                        <span class="text-xs font-mono font-semibold text-brand">{{ $ncrData['cards']['totalNcCostFormatted'] }}</span>
                    </div>
                    <div class="mt-4">
                        <x-line-chart
                            chart-id="chart-ncr-cost-monthly"
                            title="Grafik tren NC cost per bulan"
                            :months="array_map(fn($m) => mb_substr($m, 0, 3), $ncrData['charts']['monthlyCostTrend']['labels'])"
                            :series="['Ditutup' => $ncrData['charts']['monthlyCostTrend']['data']]"
                        />
                    </div>
                </div>
            </div>

            @php
                $totalNcr = (int) ($ncrData['cards']['totalNcr'] ?? 0);
                $selectedYear = $ncrData['selectedYear'] ?? date('Y');

                // 1. Tipe NCR
                $typeLabels = $ncrData['charts']['typeDistribution']['labels'] ?? [];
                $typeData = $ncrData['charts']['typeDistribution']['data'] ?? [];
                $typeMap = array_combine($typeLabels, $typeData) ?: [];
                $typeItems = [
                    ['label' => 'Project', 'value' => $typeMap['Project'] ?? 0, 'color' => '#366F3A'],
                    ['label' => 'Internal', 'value' => $typeMap['Internal'] ?? 0, 'color' => '#D9CBA2'],
                ];

                // 2. Status Mutu (Urutan tetap Open, CA Closed, Closed)
                $statusLabels = $ncrData['charts']['statusDistribution']['labels'] ?? [];
                $statusData = $ncrData['charts']['statusDistribution']['data'] ?? [];
                $statusMap = array_combine($statusLabels, $statusData) ?: [];
                $statusItems = [
                    ['label' => 'Open', 'value' => $statusMap['Open'] ?? 0, 'color' => '#8DB6E8'],
                    ['label' => 'CA Closed', 'value' => $statusMap['CA Closed'] ?? 0, 'color' => '#E07B2E'],
                    ['label' => 'Closed', 'value' => $statusMap['Closed'] ?? 0, 'color' => '#1F5B2A'],
                ];

                // 3. Kategori Akar Masalah (4M)
                $rcLabels = $ncrData['charts']['rootCauseCategoryDistribution']['labels'] ?? [];
                $rcData = $ncrData['charts']['rootCauseCategoryDistribution']['data'] ?? [];
                $rcRows = [];
                foreach ($rcLabels as $idx => $lbl) {
                    $rcRows[] = [
                        'label' => format_root_cause_category($lbl),
                        'value' => $rcData[$idx] ?? 0,
                    ];
                }

                // 4. Temuan per Area Proses
                $areaLabels = $ncrData['charts']['areaDistribution']['labels'] ?? [];
                $areaData = $ncrData['charts']['areaDistribution']['data'] ?? [];
                $areaRows = [];
                foreach ($areaLabels as $idx => $lbl) {
                    $areaRows[] = [
                        'label' => str_replace('Process ', '', $lbl),
                        'value' => $areaData[$idx] ?? 0,
                    ];
                }
            @endphp

            {{-- Section Karakteristik data --}}
            <section aria-labelledby="heading-ncr-characteristics" class="mt-8">
                <h3 id="heading-ncr-characteristics" class="mb-4 text-[18px] font-semibold text-ink">Karakteristik data</h3>
                <div class="grid grid-cols-1 min-[820px]:grid-cols-2 gap-4">
                    {{-- Card Distribusi Tipe NCR --}}
                    <div class="flex flex-col justify-between rounded-[16px] border border-[#E1DED3] bg-white p-6">
                        <div>
                            <h4 class="text-[16px] font-semibold text-ink">Distribusi Tipe NCR</h4>
                            <p class="mt-0.5 text-[13px] text-muted">Project dibanding Internal</p>
                        </div>
                        @if ($totalNcr === 0)
                            <div class="py-10 text-center text-[13px] text-muted">Belum ada data NCR pada tahun {{ $selectedYear }}.</div>
                        @else
                            <div class="my-6 flex justify-center">
                                <x-donut-chart :items="$typeItems" :total="$totalNcr" center-label="NCR" title="Tipe NCR" />
                            </div>
                            <div class="border-t border-[#E1DED3] pt-4">
                                <x-legend-list :items="$typeItems" :decimals="1" shape="dot" :total="$totalNcr" />
                            </div>
                        @endif
                    </div>

                    {{-- Card Distribusi Status Mutu --}}
                    <div class="flex flex-col justify-between rounded-[16px] border border-[#E1DED3] bg-white p-6">
                        <div>
                            <h4 class="text-[16px] font-semibold text-ink">Distribusi Status Mutu</h4>
                            <p class="mt-0.5 text-[13px] text-muted">Status penyelesaian saat ini</p>
                        </div>
                        @if ($totalNcr === 0)
                            <div class="py-10 text-center text-[13px] text-muted">Belum ada data NCR pada tahun {{ $selectedYear }}.</div>
                        @else
                            <div class="my-8">
                                <x-stacked-bar :items="$statusItems" title="Distribusi Status Mutu" />
                            </div>
                            <div class="border-t border-[#E1DED3] pt-4">
                                <x-legend-list :items="$statusItems" :decimals="1" shape="square" :total="$totalNcr" />
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Section Analisis akar masalah --}}
            <section aria-labelledby="heading-ncr-root-cause" class="mt-8">
                <h3 id="heading-ncr-root-cause" class="mb-4 text-[18px] font-semibold text-ink">Analisis akar masalah</h3>
                <div class="grid grid-cols-1 min-[820px]:grid-cols-2 gap-4">
                    {{-- Card Kategori Akar Masalah (4M) --}}
                    <div class="rounded-[16px] border border-[#E1DED3] bg-white p-6">
                        <h4 class="text-[16px] font-semibold text-ink">Kategori Akar Masalah (4M)</h4>
                        <p class="mt-0.5 text-[13px] text-muted">Sumber masalah dari {{ $totalNcr }} NCR</p>
                        <div class="mt-6">
                            @if ($totalNcr === 0)
                                <div class="py-10 text-center text-[13px] text-muted">Belum ada data NCR pada tahun {{ $selectedYear }}.</div>
                            @else
                                <x-metric-bars :rows="$rcRows" :total="$totalNcr" />
                            @endif
                        </div>
                    </div>

                    {{-- Card Temuan per Area Proses --}}
                    <div class="rounded-[16px] border border-[#E1DED3] bg-white p-6">
                        <h4 class="text-[16px] font-semibold text-ink">Temuan per Area Proses</h4>
                        <p class="mt-0.5 text-[13px] text-muted">Urutan area dengan temuan terbanyak</p>
                        <div class="mt-6">
                            @if ($totalNcr === 0)
                                <div class="py-10 text-center text-[13px] text-muted">Belum ada data NCR pada tahun {{ $selectedYear }}.</div>
                            @else
                                <x-rank-list :rows="$areaRows" :limit="5" unit="temuan" />
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            {{-- Section Temuan NCR per Department --}}
            <section aria-labelledby="heading-ncr-department" class="mt-8">
                @php
                    $dld = $ncrData['charts']['departmentLevelDistribution'];
                    $dldLevels = $dld['levels'] ?? ['Major', 'Minor', 'Near Miss'];
                    $dldLabels = $dld['labels'] ?? [];
                    $dldData = $dld['data'] ?? [];

                    // Build sorted rows (department => [total, levelCounts[]])
                    $deptRows = [];
                    foreach ($dldLabels as $i => $dName) {
                        $levelCounts = $dldData[$i] ?? [];
                        $total = array_sum($levelCounts);
                        if ($total > 0) {
                            $deptRows[] = [
                                'name' => $dName,
                                'total' => $total,
                                'levels' => $levelCounts,
                            ];
                        }
                    }
                    usort($deptRows, fn ($a, $b) => $b['total'] <=> $a['total']);

                    $totalWithFindings = count($deptRows);
                    $maxTotal = $totalWithFindings > 0 ? $deptRows[0]['total'] : 1;
                    $deptLimit = 8;
                    $topDepts = array_slice($deptRows, 0, $deptLimit);
                    $remainingDepts = array_slice($deptRows, $deptLimit);

                    $levelColors = [
                        'Major' => '#E07B2E',
                        'Minor' => '#D9CBA2',
                        'Near Miss' => '#8DB6E8',
                    ];
                @endphp

                <div class="rounded-[16px] border border-[#E1DED3] bg-white p-6">
                    <h4 id="heading-ncr-department" class="text-[16px] font-semibold text-ink">Temuan NCR per Department</h4>
                    <p class="mt-0.5 text-[13px] text-muted">
                        Jumlah NCR per department, dibagi menurut level ({{ $ncrData['selectedYear'] }})
                    </p>

                    @if ($totalNcr === 0 || $totalWithFindings === 0)
                        <div class="py-10 text-center text-[13px] text-muted">Belum ada data NCR pada tahun {{ $selectedYear }}.</div>
                    @else
                        {{-- Legend --}}
                        <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-1.5">
                            @foreach ($dldLevels as $lvl)
                                <span class="flex items-center gap-1.5 text-[13px] text-muted">
                                    <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-[3px]"
                                          style="background-color: {{ $levelColors[$lvl] ?? '#B9B5A6' }};"
                                          aria-hidden="true"></span>
                                    {{ $lvl }}
                                </span>
                            @endforeach
                        </div>

                        {{-- Rows --}}
                        <div class="mt-5 space-y-5">
                            @foreach ($topDepts as $rank => $row)
                                @php
                                    $rankNum = $rank + 1;
                                    $pct = $totalNcr > 0 ? ($row['total'] / $totalNcr) * 100 : 0;
                                    $barWidthPct = round(($row['total'] / $maxTotal) * 100, 1);
                                @endphp
                                <div>
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex min-w-0 items-center gap-2.5">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full font-mono text-[12px] font-semibold
                                                {{ $rankNum === 1 ? 'bg-[#366F3A] text-white' : 'bg-[#E9EFE5] text-[#366F3A]' }}">
                                                {{ $rankNum }}
                                            </span>
                                            <span class="text-[14px] font-semibold text-ink">{{ $row['name'] }}</span>
                                            @if ($rankNum === 1)
                                                <span class="inline-flex items-center rounded-full bg-ink px-2.5 py-0.5 text-[11px] font-semibold text-white">
                                                    Tertinggi
                                                </span>
                                            @endif
                                        </div>
                                        <span class="shrink-0 pl-2 font-mono text-[13px] font-medium text-ink whitespace-nowrap">
                                            {{ $row['total'] }} <span class="text-muted font-normal">NCR</span>
                                            <span class="text-muted">&middot;</span>
                                            {{ format_percentage($pct, 1) }}
                                        </span>
                                    </div>
                                    {{-- Level stacked bar --}}
                                    <div class="mt-2 flex h-[10px] w-full overflow-hidden rounded-full bg-[#EEEBE1]" aria-hidden="true">
                                        @php
                                            // Build proportional segments within the bar's max-relative width
                                            $segments = [];
                                            foreach ($dldLevels as $li => $lvl) {
                                                $cnt = $row['levels'][$li] ?? 0;
                                                if ($cnt > 0) {
                                                    $segWidth = round(($cnt / $maxTotal) * 100, 2);
                                                    $segments[] = [
                                                        'color' => $levelColors[$lvl] ?? '#B9B5A6',
                                                        'width' => $segWidth,
                                                    ];
                                                }
                                            }
                                        @endphp
                                        @foreach ($segments as $si => $seg)
                                            <div class="h-full {{ $si === 0 ? 'rounded-l-full' : '' }} {{ $si === count($segments) - 1 ? 'rounded-r-full' : '' }}"
                                                 style="background-color: {{ $seg['color'] }}; width: {{ $seg['width'] }}%; margin-right: {{ $si < count($segments) - 1 ? '2px' : '0' }}">
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach

                            @if (! empty($remainingDepts))
                                <details class="group">
                                    <div class="hidden group-open:block space-y-5">
                                        @foreach ($remainingDepts as $rank => $row)
                                            @php
                                                $rankNum = $deptLimit + $rank + 1;
                                                $pct = $totalNcr > 0 ? ($row['total'] / $totalNcr) * 100 : 0;
                                            @endphp
                                            <div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <div class="flex min-w-0 items-center gap-2.5">
                                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#E9EFE5] font-mono text-[12px] font-semibold text-[#366F3A]">
                                                            {{ $rankNum }}
                                                        </span>
                                                        <span class="text-[14px] font-semibold text-ink">{{ $row['name'] }}</span>
                                                    </div>
                                                    <span class="shrink-0 pl-2 font-mono text-[13px] font-medium text-ink whitespace-nowrap">
                                                        {{ $row['total'] }} <span class="text-muted font-normal">NCR</span>
                                                        <span class="text-muted">&middot;</span>
                                                        {{ format_percentage($pct, 1) }}
                                                    </span>
                                                </div>
                                                <div class="mt-2 flex h-[10px] w-full overflow-hidden rounded-full bg-[#EEEBE1]" aria-hidden="true">
                                                    @php
                                                        $segments = [];
                                                        foreach ($dldLevels as $li => $lvl) {
                                                            $cnt = $row['levels'][$li] ?? 0;
                                                            if ($cnt > 0) {
                                                                $segments[] = [
                                                                    'color' => $levelColors[$lvl] ?? '#B9B5A6',
                                                                    'width' => round(($cnt / $maxTotal) * 100, 2),
                                                                ];
                                                            }
                                                        }
                                                    @endphp
                                                    @foreach ($segments as $si => $seg)
                                                        <div class="h-full {{ $si === 0 ? 'rounded-l-full' : '' }} {{ $si === count($segments) - 1 ? 'rounded-r-full' : '' }}"
                                                             style="background-color: {{ $seg['color'] }}; width: {{ $seg['width'] }}%; margin-right: {{ $si < count($segments) - 1 ? '2px' : '0' }}">
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="mt-5 flex items-center justify-between border-t border-[#E1DED3] pt-4">
                                        <p class="text-[13px] text-muted">
                                            Menampilkan {{ min($deptLimit, $totalWithFindings) }} dari {{ $totalWithFindings }} department yang punya temuan
                                        </p>
                                        <summary class="cursor-pointer list-none text-[13px] font-semibold text-brand hover:underline focus:outline-none">
                                            <span class="group-open:hidden">Lihat semua department →</span>
                                            <span class="hidden group-open:inline">Sembunyikan ↑</span>
                                        </summary>
                                    </div>
                                </details>
                            @else
                                <div class="mt-2 border-t border-[#E1DED3] pt-4">
                                    <p class="text-[13px] text-muted">
                                        Menampilkan {{ $totalWithFindings }} department yang punya temuan
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </section>
        </section>
    @endif
    </div>
@endsection
