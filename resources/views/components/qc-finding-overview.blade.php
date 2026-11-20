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
    <section aria-label="Statistik QC Finding" class="grid grid-cols-2 gap-2.5 sm:gap-4 lg:grid-cols-4">
        <x-stat-card label="Temuan Open" :value="$stats['openCount']" status="open" subtext="Temuan belum selesai" />
        <x-stat-card label="Menunggu Verifikasi" :value="$stats['waitingCount']" status="waiting_verification" subtext="Menunggu verifikasi QC" />
        <x-stat-card label="Terlambat" :value="$stats['overdueCount']" status="terlambat" subtext="Melewati due date" />
        <x-stat-card label="Closed" :value="$stats['closedCount']" status="close" subtext="Selesai dan terverifikasi" />
    </section>

    {{-- Grid 2 Kolom: Temuan per Project & Perlu Perhatian (di mobile, Perlu Perhatian tampil duluan) --}}
    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
        {{-- Kolom Temuan per Project --}}
        <div class="order-2 flex flex-col rounded-2xl border border-line bg-white p-4 sm:p-[22px] lg:order-1">
            <div class="mb-4 sm:mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-[17px] sm:text-[18px] font-semibold text-ink">Temuan per Project</h2>
                    <p class="hidden sm:block text-[13px] text-muted">Daftar project dengan progres pemenuhan temuan</p>
                </div>
            </div>

            {{-- Filter & Pencarian --}}
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                {{-- Filter chips — horizontal scroll di mobile --}}
                <div class="no-scrollbar flex gap-2 overflow-x-auto pb-1 sm:flex-wrap sm:overflow-visible sm:pb-0">
                    <a href="{{ $makeUrl(['pf' => 'semua']) }}"
                       class="inline-flex shrink-0 min-h-[36px] sm:min-h-[44px] items-center gap-1.5 rounded-[10px] px-3.5 py-1.5 text-[12px] sm:text-[13px] transition {{ $currentPf === 'semua' ? 'bg-ink font-semibold text-white' : 'border border-line bg-white font-medium text-ink hover:bg-paper-soft' }}">
                        <span>Semua</span>
                        <span class="font-mono text-[12px] opacity-80">{{ $filterCounts['semua'] }}</span>
                    </a>
                    <a href="{{ $makeUrl(['pf' => 'terlambat']) }}"
                       class="inline-flex shrink-0 min-h-[36px] sm:min-h-[44px] items-center gap-1.5 rounded-[10px] px-3.5 py-1.5 text-[12px] sm:text-[13px] transition {{ $currentPf === 'terlambat' ? 'bg-ink font-semibold text-white' : 'border border-line bg-white font-medium text-ink hover:bg-paper-soft' }}">
                        <span>Ada Terlambat</span>
                        <span class="font-mono text-[12px] opacity-80">{{ $filterCounts['terlambat'] }}</span>
                    </a>
                    <a href="{{ $makeUrl(['pf' => 'verifikasi']) }}"
                       class="inline-flex shrink-0 min-h-[36px] sm:min-h-[44px] items-center gap-1.5 rounded-[10px] px-3.5 py-1.5 text-[12px] sm:text-[13px] transition {{ $currentPf === 'verifikasi' ? 'bg-ink font-semibold text-white' : 'border border-line bg-white font-medium text-ink hover:bg-paper-soft' }}">
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
            <div class="space-y-3 sm:space-y-4">
                @forelse ($projects as $project)
                    <article class="flex flex-col gap-2.5 rounded-xl border border-line bg-white p-4 transition hover:border-brand/40 hover:bg-paper-soft">
                        <div class="flex items-center justify-between gap-2">
                            @if ($project->project_code)
                                <span class="inline-flex items-center rounded-full bg-[#E9EFE5] px-2.5 py-0.5 font-mono text-[11px] sm:text-[12px] font-bold text-[#1F5B2A]">
                                    {{ $project->project_code }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-[#E9EFE5] px-2.5 py-0.5 font-mono text-[11px] sm:text-[12px] font-bold text-[#1F5B2A]">
                                    PRJ
                                </span>
                            @endif
                            <x-due-chip
                                :due-date="$project->nearest_due_date"
                                :all-closed="$project->closed_count > 0 && $project->open_count === 0 && $project->waiting_count === 0"
                            />
                        </div>

                        <div>
                            <h3 class="text-[15px] font-semibold leading-snug text-ink">
                                <a href="{{ route('qc-finding.project', ['project' => $project->id]) }}" class="hover:text-brand">
                                    {{ $project->project_name }}
                                </a>
                            </h3>
                            <p class="mt-0.5 text-[13px] text-muted">
                                {{ $project->customer ? $project->customer . ' · ' : '' }}<span class="font-mono">{{ $project->total_count }}</span> temuan
                            </p>
                        </div>

                        {{-- Status chips per project --}}
                        <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                            <span class="inline-flex items-center gap-1 rounded-full bg-[#E3EEFB] px-2 py-0.5 text-[10px] sm:text-[11px] font-semibold text-[#1B4C8C]">
                                <span class="h-1.5 w-1.5 rounded-full bg-[#8DB6E8]" aria-hidden="true"></span>
                                <span class="font-mono">{{ $project->open_count }}</span> Open
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-[#FCE8D8] px-2 py-0.5 text-[10px] sm:text-[11px] font-semibold text-[#8F3F08]">
                                <span class="h-1.5 w-1.5 rounded-full bg-[#E07B2E]" aria-hidden="true"></span>
                                <span class="font-mono">{{ $project->waiting_count }}</span> Verifikasi
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-[#E1F0E2] px-2 py-0.5 text-[10px] sm:text-[11px] font-semibold text-[#1F5B2A]">
                                <span class="h-1.5 w-1.5 rounded-full bg-[#1F5B2A]" aria-hidden="true"></span>
                                <span class="font-mono">{{ $project->closed_count }}</span> Closed
                            </span>
                        </div>

                        {{-- Progress bar --}}
                        <div class="pt-1">
                            <x-progress-stack
                                :open="$project->open_count"
                                :waiting="$project->waiting_count"
                                :closed="$project->closed_count"
                                :show-legend="false"
                            />
                        </div>

                        <div class="pt-1 sm:pt-2">
                            <a href="{{ route('qc-finding.project', ['project' => $project->id]) }}"
                               class="flex min-h-[44px] sm:min-h-0 w-full sm:w-auto items-center justify-center rounded-[10px] border border-[#8C978B] sm:border-0 bg-white sm:bg-transparent px-3 py-2 sm:p-0 text-[13px] font-semibold text-ink sm:text-brand transition hover:bg-paper-soft sm:hover:bg-transparent sm:hover:text-brand-dark">
                                Lihat temuan <span class="hidden sm:inline">&rarr;</span>
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

        {{-- Kolom Perlu Perhatian --}}
        <div class="order-1 flex flex-col rounded-2xl border border-line bg-white p-4 sm:p-[22px] lg:order-2">
            <div class="mb-2 sm:mb-4">
                <h2 class="text-[17px] sm:text-[18px] font-semibold text-ink">Perlu Perhatian</h2>
                <p class="hidden sm:block text-[13px] text-muted">Temuan mendesak yang memerlukan tindakan</p>
            </div>

            <div class="divide-y divide-line">
                @forelse ($urgentFindings as $urgent)
                    <a href="{{ route('qc-finding.project', ['project' => $urgent['project_id'], 'buka' => $urgent['id']]) }}#temuan-{{ $urgent['id'] }}"
                       class="block py-3.5 transition hover:bg-paper-soft/60">
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <x-due-chip :due-date="$urgent['due_date']" />
                            @if ($urgent['project_code'])
                                <span class="inline-flex items-center rounded-full bg-[#E9EFE5] px-2.5 py-0.5 font-mono text-[11px] font-bold text-[#1F5B2A]">
                                    {{ $urgent['project_code'] }}
                                </span>
                            @else
                                <span class="truncate max-w-[140px] inline-flex items-center rounded-full bg-[#E9EFE5] px-2.5 py-0.5 font-mono text-[11px] font-semibold text-[#1F5B2A]">
                                    {{ $urgent['project_name'] }}
                                </span>
                            @endif
                        </div>
                        <p class="line-clamp-2 text-[14px] leading-relaxed text-ink">
                            {{ $urgent['finding_list'] }}
                        </p>
                    </a>
                @empty
                    <div class="py-6 text-center text-[13px] text-muted">
                        Tidak ada temuan yang memerlukan perhatian mendesak saat ini.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

