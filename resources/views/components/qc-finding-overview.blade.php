@props([
    'overview',
    'baseUrl' => route('qc-finding.index'),
    'extraParams' => [],
])

@php
    $stats = $overview['stats'];
    $projects = $overview['projects'];
    $filterCounts = $overview['filterCounts'];
    $urgentFindings = $overview['urgentFindings'];
    $currentPf = $overview['filter'] ?? 'semua';
    $currentPq = $overview['q'] ?? '';

    $makeUrl = function (array $params) use ($baseUrl, $extraParams, $currentPq) {
        $merged = array_merge($extraParams, [
            'pf' => $params['pf'] ?? 'semua',
            'pq' => $params['pq'] ?? $currentPq,
        ]);

        if (blank($merged['pq'])) {
            unset($merged['pq']);
        }

        $query = http_build_query($merged);

        return $query !== '' ? $baseUrl . '?' . $query : $baseUrl;
    };
@endphp

<div class="space-y-6">
    {{-- 4 Kartu Statistik QC Finding --}}
    <section aria-label="Statistik QC Finding" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Temuan Open" :value="$stats['openCount']" status="open" subtext="Temuan belum selesai" />
        <x-stat-card label="Menunggu Verifikasi" :value="$stats['waitingCount']" status="waiting_verification" subtext="Menunggu verifikasi QC" />
        <x-stat-card label="Terlambat" :value="$stats['overdueCount']" status="terlambat" subtext="Melewati due date" />
        <x-stat-card label="Closed" :value="$stats['closedCount']" status="close" subtext="Selesai dan terverifikasi" />
    </section>

    {{-- Grid 2 Kolom: Temuan per Project & Perlu Perhatian --}}
    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
        {{-- Kolom Kiri: Temuan per Project --}}
        <div class="flex flex-col rounded-2xl border border-line bg-white p-[22px]">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-[18px] font-semibold text-ink">Temuan per Project</h2>
                    <p class="text-[13px] text-muted">Daftar project dengan progres pemenuhan temuan</p>
                </div>
            </div>

            {{-- Filter & Pencarian --}}
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ $makeUrl(['pf' => 'semua']) }}"
                       class="inline-flex min-h-[44px] items-center gap-1.5 rounded-[10px] px-4 text-[13px] transition {{ $currentPf === 'semua' ? 'bg-ink font-semibold text-white' : 'border border-line bg-white font-medium text-ink hover:bg-paper-soft' }}">
                        <span>Semua</span>
                        <span class="font-mono text-[12px] opacity-80">{{ $filterCounts['semua'] }}</span>
                    </a>
                    <a href="{{ $makeUrl(['pf' => 'terlambat']) }}"
                       class="inline-flex min-h-[44px] items-center gap-1.5 rounded-[10px] px-4 text-[13px] transition {{ $currentPf === 'terlambat' ? 'bg-ink font-semibold text-white' : 'border border-line bg-white font-medium text-ink hover:bg-paper-soft' }}">
                        <span>Ada Terlambat</span>
                        <span class="font-mono text-[12px] opacity-80">{{ $filterCounts['terlambat'] }}</span>
                    </a>
                    <a href="{{ $makeUrl(['pf' => 'verifikasi']) }}"
                       class="inline-flex min-h-[44px] items-center gap-1.5 rounded-[10px] px-4 text-[13px] transition {{ $currentPf === 'verifikasi' ? 'bg-ink font-semibold text-white' : 'border border-line bg-white font-medium text-ink hover:bg-paper-soft' }}">
                        <span>Menunggu Verifikasi</span>
                        <span class="font-mono text-[12px] opacity-80">{{ $filterCounts['verifikasi'] }}</span>
                    </a>
                </div>

                {{-- Search Input --}}
                <form method="GET" action="{{ $baseUrl }}" class="relative w-full sm:w-auto">
                    @foreach ($extraParams as $key => $val)
                        <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                    @endforeach
                    <input type="hidden" name="pf" value="{{ $currentPf }}">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input
                            type="text"
                            name="pq"
                            value="{{ $currentPq }}"
                            placeholder="Cari project..."
                            class="h-[44px] w-full rounded-[10px] border border-line bg-white pl-10 pr-3.5 text-[13px] text-ink placeholder:text-muted focus:border-brand focus:outline-none sm:w-[220px]"
                        >
                    </div>
                </form>
            </div>

            {{-- List Project --}}
            <div class="space-y-4">
                @forelse ($projects as $project)
                    <article class="flex flex-col gap-3 rounded-xl border border-line bg-white p-4 transition hover:border-brand/40 hover:bg-paper-soft">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    @if ($project->project_code)
                                        <span class="rounded bg-brand-pale px-2 py-0.5 font-mono text-[12px] font-semibold text-brand">
                                            {{ $project->project_code }}
                                        </span>
                                    @endif
                                    <h3 class="text-[15px] font-semibold text-ink">
                                        <a href="{{ route('qc-finding.project', ['project' => $project->id]) }}" class="hover:text-brand">
                                            {{ $project->project_name }}
                                        </a>
                                    </h3>
                                </div>
                                <p class="mt-0.5 text-[12px] text-muted">Customer: {{ $project->customer ?? '—' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-due-chip
                                    :due-date="$project->nearest_due_date"
                                    :all-closed="$project->closed_count > 0 && $project->open_count === 0 && $project->waiting_count === 0"
                                />
                            </div>
                        </div>

                        {{-- Progress bar --}}
                        <div class="pt-1">
                            <x-progress-stack
                                :open="$project->open_count"
                                :waiting="$project->waiting_count"
                                :closed="$project->closed_count"
                            />
                        </div>

                        <div class="flex items-center justify-between border-t border-line/60 pt-3">
                            <span class="text-[12px] text-muted">
                                Total <strong class="font-mono text-ink">{{ $project->total_count }}</strong> temuan
                            </span>
                            <a href="{{ route('qc-finding.project', ['project' => $project->id]) }}"
                               class="inline-flex min-h-[44px] items-center text-[13px] font-semibold text-brand hover:text-brand-dark">
                                Lihat temuan &rarr;
                            </a>
                        </div>
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-line p-8 text-center text-[13px] text-muted">
                        Belum ada QC Finding.
                    </div>
                @endforelse
            </div>

            @if ($projects->hasPages())
                <div class="mt-6">
                    {{ $projects->links() }}
                </div>
            @endif
        </div>

        {{-- Kolom Kanan: Perlu Perhatian --}}
        <div class="flex flex-col rounded-2xl border border-line bg-white p-[22px]">
            <div class="mb-4">
                <h2 class="text-[18px] font-semibold text-ink">Perlu Perhatian</h2>
                <p class="text-[13px] text-muted">Temuan mendesak yang memerlukan tindakan</p>
            </div>

            <div class="space-y-3">
                @forelse ($urgentFindings as $urgent)
                    <article class="flex flex-col gap-2 rounded-xl border border-line bg-[#FBFAF6] p-3.5 transition hover:border-brand/40">
                        <div class="flex items-center justify-between gap-2">
                            @if ($urgent['project_code'])
                                <span class="rounded bg-brand-pale px-2 py-0.5 font-mono text-[11px] font-semibold text-brand">
                                    {{ $urgent['project_code'] }}
                                </span>
                            @else
                                <span class="text-[12px] font-semibold text-ink truncate max-w-[140px]">
                                    {{ $urgent['project_name'] }}
                                </span>
                            @endif
                            <x-status-chip :status="$urgent['status']" />
                        </div>
                        <p class="line-clamp-2 text-[13px] text-ink">
                            {{ $urgent['finding_list'] }}
                        </p>
                        <div class="mt-1 flex items-center justify-between pt-1">
                            <x-due-chip :due-date="$urgent['due_date']" />
                            <a href="{{ route('qc-finding.project', ['project' => $urgent['project_id'], 'buka' => $urgent['id']]) }}#temuan-{{ $urgent['id'] }}"
                               class="inline-flex min-h-[44px] items-center text-[12px] font-semibold text-brand hover:text-brand-dark">
                                Tindak lanjuti &rarr;
                            </a>
                        </div>
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-line p-6 text-center text-[13px] text-muted">
                        Tidak ada temuan yang memerlukan perhatian mendesak saat ini.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

