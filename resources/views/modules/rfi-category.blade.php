@extends('layouts.module', ['active' => 'rfi', 'pageTitle' => "RFI - Location: {$category['label']}"])

@section('header_action')
    @if ($canCreate)
        <a href="{{ route('rfi.create', ['kategori' => $categoryCode]) }}"
           class="inline-flex h-[48px] items-center justify-center gap-2 rounded-[10px] bg-brand px-5 text-[14px] font-semibold text-white shadow-xs transition hover:bg-brand-dark focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            + Buat RFI
        </a>
    @endif
@endsection

@section('content')
    @if ($statusCounts['all'] === 0 && ($draftCount ?? 0) === 0)
        {{-- Empty State: No RFIs at all in this location --}}
        <div class="flex flex-col items-center justify-center rounded-2xl border border-line bg-white px-6 py-14 text-center shadow-xs">
            <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-paper text-muted" aria-hidden="true">
                <svg class="h-8 w-8 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
            </div>
            <h2 class="text-[20px] font-semibold text-ink">Belum ada RFI pada location ini.</h2>
            <p class="mt-2 max-w-[420px] text-[15px] leading-relaxed text-muted">
                Begitu ada RFI yang diajukan, daftarnya muncul di sini. Mulai dengan membuat RFI pertama.
            </p>
            @if ($canCreate)
                <a href="{{ route('rfi.create', ['kategori' => $categoryCode]) }}"
                   class="mt-6 inline-flex h-[48px] items-center justify-center gap-2 rounded-[10px] bg-brand px-6 text-[14px] font-semibold text-white shadow-xs transition hover:bg-brand-dark focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    + Buat RFI
                </a>
            @endif
        </div>
    @else
        {{-- Filter & Search Bar --}}
        <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-1.5 rounded-[12px] bg-paper p-1">
                @php
                    $tabs = [
                        '' => ['label' => 'Semua', 'count' => $statusCounts['all']],
                        'open' => ['label' => 'Open', 'count' => $statusCounts['open']],
                        'nc' => ['label' => 'NC', 'count' => $statusCounts['nc']],
                        'close' => ['label' => 'Close', 'count' => $statusCounts['close']],
                    ];
                    if ($canSeeDrafts ?? false) {
                        $tabs['draft'] = ['label' => 'Draft', 'count' => $draftCount ?? 0];
                    }
                @endphp
                @foreach ($tabs as $key => $tab)
                    @php
                        $isActive = ($selectedStatus === $key);
                        $url = route('rfi.category', array_filter([
                            'kategori' => $categoryCode,
                            'status' => $key ?: null,
                            'q' => $q ?: null,
                        ]));
                    @endphp
                    <a href="{{ $url }}"
                       class="inline-flex h-[44px] items-center gap-2 rounded-[10px] px-4 text-[14px] transition {{ $isActive ? 'bg-ink font-semibold text-white shadow-xs' : 'font-medium text-muted hover:bg-white/70 hover:text-ink' }}">
                        <span>{{ $tab['label'] }}</span>
                        <span class="font-mono text-[12px] opacity-80">{{ $tab['count'] }}</span>
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('rfi.category', ['kategori' => $categoryCode]) }}" class="relative w-full sm:w-auto">
                @if ($selectedStatus)
                    <input type="hidden" name="status" value="{{ $selectedStatus }}">
                @endif
                <div class="relative w-full sm:w-[320px]">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted" aria-hidden="true">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </div>
                    <input
                        type="search"
                        name="q"
                        value="{{ $q }}"
                        placeholder="Cari nomor RFI atau project"
                        aria-label="Cari RFI"
                        class="h-[44px] w-full rounded-[10px] border border-line bg-white pl-[42px] pr-8 text-[14px] text-ink placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand"
                    >
                    @if ($q)
                        <a href="{{ route('rfi.category', array_filter(['kategori' => $categoryCode, 'status' => $selectedStatus ?: null])) }}"
                           class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-muted hover:text-ink"
                           title="Hapus pencarian">
                            ✕
                        </a>
                    @endif
                </div>
            </form>
        </div>

        @if ($rfis->isEmpty())
            {{-- Filtered Empty State --}}
            <div class="flex flex-col items-center justify-center rounded-2xl border border-line bg-white px-6 py-14 text-center shadow-xs">
                <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-paper text-muted" aria-hidden="true">
                    <svg class="h-7 w-7 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </div>
                <h2 class="text-[18px] font-semibold text-ink">Tidak ada RFI yang cocok</h2>
                <p class="mt-1 text-[14px] text-muted">
                    Tidak ditemukan data untuk filter atau kata kunci saat ini.
                </p>
                <a href="{{ route('rfi.category', ['kategori' => $categoryCode]) }}"
                   class="mt-4 inline-flex h-[40px] items-center justify-center rounded-[10px] border border-line bg-white px-4 text-[13px] font-semibold text-brand transition hover:bg-paper">
                    Reset filter
                </a>
            </div>
        @else
            {{-- Data Table Card --}}
            <div class="overflow-hidden rounded-2xl border border-line bg-white shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[14px] text-ink">
                        <thead class="hidden border-b border-line bg-paper-soft text-[13px] font-semibold text-muted min-[820px]:table-header-group">
                            <tr>
                                <th class="px-5 py-3.5 whitespace-nowrap">No RFI</th>
                                <th class="px-5 py-3.5">Project</th>
                                <th class="px-5 py-3.5 whitespace-nowrap">Tanggal inspeksi</th>
                                <th class="px-5 py-3.5 whitespace-nowrap">Pengaju</th>
                                <th class="px-5 py-3.5 whitespace-nowrap">Disposisi</th>
                                <th class="px-5 py-3.5 whitespace-nowrap">Batas waktu respon</th>
                                @if ($canReview)
                                    <th class="px-5 py-3.5 whitespace-nowrap">Tindakan QC</th>
                                @endif
                                <th class="px-5 py-3.5 whitespace-nowrap">Status</th>
                            </tr>
                        </thead>
                        <tbody class="block divide-y divide-line p-3 min-[820px]:table-row-group min-[820px]:divide-line min-[820px]:p-0">
                            @foreach ($rfis as $rfi)
                                @php
                                    $creatorRole = match ($rfi->createdBy?->role) {
                                        'qc_qa' => 'QC/QA',
                                        'ppic' => 'PPIC',
                                        default => ucfirst($rfi->createdBy?->role ?? ''),
                                    };
                                    $now = \Carbon\Carbon::now('UTC');
                                    $isOverdue = $rfi->deadline_status === 'OVERDUE' || ($rfi->deadline_at && $now->greaterThanOrEqualTo($rfi->deadline_at));
                                    $wibDate = $rfi->deadline_at ? $rfi->deadline_at->copy()->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i \W\I\B') : null;
                                @endphp
                                <tr class="mb-3 block rounded-xl border border-line bg-white p-4 transition hover:bg-paper-soft min-[820px]:mb-0 min-[820px]:table-row min-[820px]:rounded-none min-[820px]:border-0 min-[820px]:p-0">
                                    {{-- 1. No RFI --}}
                                    <td data-label="No RFI" class="block py-1.5 min-[820px]:table-cell min-[820px]:px-5 min-[820px]:py-4 min-[820px]:align-top">
                                        <div class="flex items-center justify-between min-[820px]:block">
                                            <span class="text-[12px] font-semibold text-muted min-[820px]:hidden">No RFI:</span>
                                            <div>
                                                @if ($rfi->status !== 'draft')
                                                    <a href="{{ route('rfi.show', ['kategori' => $categoryCode, 'rfi' => $rfi]) }}" class="font-mono font-semibold text-brand underline hover:text-brand-dark">
                                                        {{ $rfi->rfi_no }}
                                                    </a>
                                                @else
                                                    <span class="font-mono font-semibold text-ink">{{ $rfi->rfi_no }}</span>
                                                @endif
                                                @if ($rfi->status === 'nc' && $rfi->qcFinding)
                                                    <a href="{{ route('qc-finding.project', ['project' => $rfi->project_id]) }}#finding-{{ $rfi->qcFinding->id }}"
                                                       class="mt-1 block font-sans text-[12px] font-semibold text-brand underline hover:text-brand-dark">
                                                        Lihat temuan
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    {{-- 2. Project --}}
                                    <td data-label="Project" class="block py-1.5 min-[820px]:table-cell min-[820px]:px-5 min-[820px]:py-4 min-[820px]:align-top">
                                        <div class="min-[820px]:block">
                                            <span class="text-[12px] font-semibold text-muted min-[820px]:hidden">Project: </span>
                                            <span class="font-medium text-ink">{{ $rfi->project->project_code }} — {{ $rfi->project->project_name }}</span>
                                            @php
                                                $allCategoryFields = array_merge($category['general_fields'] ?? [], $category['fields'] ?? []);
                                                $detailItems = [];
                                                foreach ($allCategoryFields as $field) {
                                                    $val = $field['name'] === 'scheduled_at'
                                                        ? $rfi->scheduled_at?->format('d M Y')
                                                        : data_get($rfi->details, $field['name']);
                                                    if ($val !== null && $val !== '') {
                                                        $displayVal = is_array($val) ? implode(', ', $val) : $val;
                                                        $detailItems[] = $field['label'] . ': ' . $displayVal;
                                                    }
                                                }

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
                                                        $legacyItems[] = $lbl . ': ' . $val;
                                                    }
                                                }
                                            @endphp
                                            @if (!empty($detailItems))
                                                <p class="mt-1 text-[12px] text-muted">{{ implode(' · ', $detailItems) }}</p>
                                            @endif
                                            @if (!empty($legacyItems))
                                                <p class="mt-1 text-[12px] text-muted"><span class="font-semibold">Detail lama:</span> {{ implode(' · ', $legacyItems) }}</p>
                                            @endif
                                            @if ($rfi->note)
                                                <p class="mt-0.5 text-[12px] text-muted italic">Catatan: {{ $rfi->note }}</p>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- 3. Tanggal inspeksi --}}
                                    <td data-label="Tanggal inspeksi" class="block py-1.5 min-[820px]:table-cell min-[820px]:px-5 min-[820px]:py-4 min-[820px]:align-top whitespace-nowrap">
                                        <div class="flex items-center justify-between min-[820px]:block">
                                            <span class="text-[12px] font-semibold text-muted min-[820px]:hidden">Tanggal:</span>
                                            <span class="text-ink">{{ ($rfi->date_inspect && empty($rfi->details['_draft_no_date'])) ? $rfi->date_inspect->locale('id')->translatedFormat('d M Y') : '—' }}</span>
                                        </div>
                                    </td>

                                    {{-- 4. Pengaju --}}
                                    <td data-label="Pengaju" class="block py-1.5 min-[820px]:table-cell min-[820px]:px-5 min-[820px]:py-4 min-[820px]:align-top whitespace-nowrap">
                                        <div class="flex items-center justify-between min-[820px]:block">
                                            <span class="text-[12px] font-semibold text-muted min-[820px]:hidden">Pengaju:</span>
                                            <span class="text-ink">{{ $rfi->createdBy?->name ?? '—' }}{{ $creatorRole ? ' · ' . $creatorRole : '' }}</span>
                                        </div>
                                    </td>

                                    {{-- 5. Disposisi --}}
                                    <td data-label="Disposisi" class="block py-1.5 min-[820px]:table-cell min-[820px]:px-5 min-[820px]:py-4 min-[820px]:align-top">
                                        <div class="flex items-center justify-between min-[820px]:block">
                                            <span class="text-[12px] font-semibold text-muted min-[820px]:hidden">Disposisi:</span>
                                            <div>
                                                @if ($rfi->status === 'draft')
                                                    <span class="text-muted">—</span>
                                                @elseif ($rfi->result)
                                                    <span class="font-medium text-ink">{{ $rfi->result->label() }}</span>
                                                    @if ($rfi->qc_note)
                                                        <p class="mt-0.5 max-w-[220px] text-[12px] text-muted truncate" title="{{ $rfi->qc_note }}">
                                                            {{ $rfi->qc_note }}
                                                        </p>
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    {{-- 6. Batas waktu respon --}}
                                    <td data-label="Batas waktu respon" class="block py-1.5 min-[820px]:table-cell min-[820px]:px-5 min-[820px]:py-4 min-[820px]:align-top whitespace-nowrap">
                                        <div class="flex items-center justify-between min-[820px]:block">
                                            <span class="text-[12px] font-semibold text-muted min-[820px]:hidden">Batas respon:</span>
                                            <div>
                                                @if ($rfi->status === 'draft')
                                                    <span class="text-muted">—</span>
                                                @elseif ($rfi->status === 'close')
                                                    <span class="text-[13px] text-muted">Selesai</span>
                                                @elseif ($rfi->deadline_at)
                                                    @php
                                                        $diffDays = $isOverdue
                                                            ? max(1, (int) ceil($rfi->deadline_at->diffInHours($now) / 24))
                                                            : max(1, (int) ceil($now->diffInHours($rfi->deadline_at) / 24));
                                                        $deadlineText = $isOverdue
                                                            ? "Terlambat {$diffDays} hari"
                                                            : "{$diffDays} hari lagi";
                                                    @endphp
                                                    <span
                                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[12px] font-semibold {{ $isOverdue ? 'bg-[#FCE8D8] text-[#8F3F08]' : 'bg-[#EEEBE1] text-[#3F4A41]' }}"
                                                        title="{{ $wibDate }} ({{ $rfi->deadline_status }})"
                                                    >
                                                        <span class="inline-block h-2 w-2 rounded-full {{ $isOverdue ? 'bg-[#E07B2E]' : 'bg-[#8C978B]' }}" aria-hidden="true"></span>
                                                        <span>{{ $deadlineText }}</span>
                                                        @if ($rfi->deadline_status)
                                                            <span class="sr-only">{{ $rfi->deadline_status }}</span>
                                                        @endif
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    {{-- 7. Tindakan QC (if allowed) --}}
                                    @if ($canReview)
                                        <td data-label="Tindakan QC" class="block py-1.5 min-[820px]:table-cell min-[820px]:px-5 min-[820px]:py-4 min-[820px]:align-top">
                                            <div class="flex items-center justify-between min-[820px]:block">
                                                <span class="text-[12px] font-semibold text-muted min-[820px]:hidden">Tindakan QC:</span>
                                                <div>
                                                    @if ($rfi->status === 'open')
                                                        <a
                                                            href="{{ route('rfi.show', ['kategori' => $categoryCode, 'rfi' => $rfi]) }}#tindakan-qc"
                                                            class="inline-flex h-[44px] items-center justify-center rounded-[10px] bg-brand px-4 text-[13px] font-semibold text-white shadow-xs transition hover:bg-brand-dark focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2"
                                                        >
                                                            Review
                                                        </a>
                                                    @elseif ($rfi->status === 'draft')
                                                        <span class="text-[13px] text-muted">—</span>
                                                    @else
                                                        <a
                                                            href="{{ route('rfi.show', ['kategori' => $categoryCode, 'rfi' => $rfi]) }}"
                                                            class="inline-flex min-h-[44px] items-center justify-center rounded-[10px] border border-line bg-white px-3.5 text-[12px] font-semibold text-ink hover:bg-paper-soft transition"
                                                        >
                                                            Lihat
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    @endif

                                    {{-- 8. Status --}}
                                    <td data-label="Status" class="block py-1.5 min-[820px]:table-cell min-[820px]:px-5 min-[820px]:py-4 min-[820px]:align-top">
                                        <div class="flex items-center justify-between min-[820px]:block">
                                            <span class="text-[12px] font-semibold text-muted min-[820px]:hidden">Status:</span>
                                            <div>
                                                @if ($rfi->status === 'draft')
                                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-[#EEEBE1] px-[10px] py-[3px] text-[12px] font-semibold text-[#3F4A41]">
                                                        <span class="inline-block h-2 w-2 rounded-full bg-[#8C978B]" aria-hidden="true"></span>
                                                        <span>Draft</span>
                                                    </span>
                                                @else
                                                    <x-status-chip :status="$rfi->status" />
                                                @endif

                                                @if ($rfi->status === 'draft' && (($isAdmin ?? false) || $rfi->created_by === auth()->id()))
                                                    <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                                                        <a href="{{ route('rfi.edit', ['kategori' => $categoryCode, 'rfi' => $rfi]) }}"
                                                           class="inline-flex min-h-[30px] items-center justify-center rounded-lg border border-line bg-white px-2.5 text-[12px] font-semibold text-ink transition hover:bg-paper">
                                                            Ubah
                                                        </a>
                                                        <form method="POST" action="{{ route('rfi.update', ['kategori' => $categoryCode, 'rfi' => $rfi]) }}" class="inline">
                                                            @csrf
                                                            @method('PUT')
                                                            <input type="hidden" name="action" value="submit">
                                                            <button type="submit"
                                                                    class="inline-flex min-h-[30px] items-center justify-center rounded-lg bg-brand px-2.5 text-[12px] font-semibold text-white transition hover:bg-brand-dark">
                                                                Kirim ke QC
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="{{ route('rfi.destroy', ['kategori' => $categoryCode, 'rfi' => $rfi]) }}"
                                                              onsubmit="return confirm('Hapus draft RFI ini?');" class="inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                    class="inline-flex min-h-[30px] items-center justify-center rounded-lg border border-alert/30 px-2 text-[12px] font-semibold text-alert transition hover:bg-alert/10">
                                                                Hapus
                                                            </button>
                                                        </form>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Card Footer: Pagination --}}
                {{ $rfis->links() }}
            </div>
        @endif
    @endif
@endsection
