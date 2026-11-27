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
                    @php
                        $selectedMdrProjectId = old('project_id', '');
                        $selectedMdrProject = $projects->firstWhere('id', (int) $selectedMdrProjectId);
                        $mdrProjectDisplay = $selectedMdrProject
                            ? (($selectedMdrProject->project_code ? $selectedMdrProject->project_code . ' — ' : '') . $selectedMdrProject->project_name)
                            : '';
                        $mdrProjectBadge = $projects->count() . ' Project';
                        $mdrProjectErr = $errors->first('project_id');
                    @endphp
                    <div>
                        <label for="project_id_trigger" class="block text-[14px] font-semibold text-ink">Project <span class="text-[#B4470F]">*</span></label>
                        <div class="mt-2">
                            <x-searchable-dropdown
                                id="project_id"
                                name="project_id"
                                :label="'Pilih project'"
                                :value="$selectedMdrProjectId"
                                :displayText="$mdrProjectDisplay"
                                placeholder="Cari project..."
                                :required="true"
                                :badge-count="$mdrProjectBadge"
                                :error="$mdrProjectErr"
                                data-sdd-type="static"
                            />
                        </div>
                        <div class="hidden" aria-hidden="true">
                            @foreach ($projects as $project)
                                <span data-project-id="{{ $project->id }}" data-customer="{{ $project->customer ?? '' }}"></span>
                            @endforeach
                        </div>
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
        // SDD init for project_id (static list)
        (function () {
            @php
                $mdrProjectItemsData = $projects->map(fn($p) => [
                    'value' => (string) $p->id,
                    'label' => ($p->project_code ? $p->project_code . ' — ' : '') . $p->project_name,
                    'title' => $p->project_name,
                    'code' => $p->project_code ?: '-',
                    'category' => 'PROJECT',
                    'metaLabel' => 'Customer',
                    'metaValue' => $p->customer ?: '—',
                ])->values();
            @endphp
            const projectItems = @json($mdrProjectItemsData);

            const projectMapMdr = {};
            @foreach ($projects as $p)
            projectMapMdr['{{ $p->id }}'] = @json($p->customer ?? '');
            @endforeach

            const customerLabel = document.getElementById('project-customer');
            const projectHiddenMdr = document.getElementById('project_id');

            function updateMdrCustomer() {
                if (!projectHiddenMdr || !customerLabel) return;
                const cust = projectMapMdr[projectHiddenMdr.value] || '';
                customerLabel.textContent = cust && cust.trim() ? cust : '— (pilih project dulu)';
            }
            projectHiddenMdr?.addEventListener('change', updateMdrCustomer);
            updateMdrCustomer();

            function escHtml(str) {
                return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            function renderItems(listEl, items, query) {
                const q = (query || '').trim().toLowerCase();
                const filtered = q ? items.filter(it => {
                    const l = (it.label || '').toLowerCase();
                    const t = (it.title || '').toLowerCase();
                    const c = (it.code || '').toLowerCase();
                    const m = (it.metaValue || '').toLowerCase();
                    return l.includes(q) || t.includes(q) || c.includes(q) || m.includes(q);
                }) : items;
                listEl.innerHTML = '';
                if (!filtered.length) {
                    listEl.innerHTML = '<li class="px-4 py-3 text-[13px] text-muted italic">Tidak ada project</li>';
                    return;
                }
                filtered.forEach(item => {
                    const li = document.createElement('li');
                    li.setAttribute('role', 'option');
                    li.setAttribute('data-value', item.value);
                    li.setAttribute('data-label', item.label);

                    const category = item.category || 'PROJECT';
                    const title = item.title || item.label;
                    const code = item.code || '';
                    const metaLabel = item.metaLabel || 'Customer';
                    const metaValue = item.metaValue || '';

                    li.innerHTML = `
                        <div class="group rounded-xl border border-slate-200/90 bg-white p-2.5 hover:border-[#1B6E41] hover:bg-[#F6FAF7] hover:shadow-xs transition cursor-pointer">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider bg-slate-100 text-slate-700">
                                    ${escHtml(category)}
                                </span>
                                <span class="text-[13px] font-bold text-slate-900 group-hover:text-[#1B6E41] transition truncate">
                                    ${escHtml(title)}
                                </span>
                            </div>
                            <div class="mt-1.5 flex items-center justify-between gap-2 text-[11px]">
                                ${code ? `
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold text-slate-600 bg-slate-100/80 border border-slate-200/60 truncate max-w-[190px]">
                                    ${escHtml(code)}
                                </span>` : '<span></span>'}
                                ${metaValue ? `
                                <div class="text-right truncate font-medium text-slate-500">
                                    <span>${escHtml(metaLabel ? metaLabel + ': ' : '')}</span><span class="font-bold text-[#1B6E41]">${escHtml(metaValue)}</span>
                                </div>` : ''}
                            </div>
                        </div>`;
                    listEl.appendChild(li);
                });
            }

            function openPanel(root, trigger) {
                const panel = root.querySelector('[role="listbox"]');
                if (!panel) return;
                panel.classList.remove('hidden');
                trigger.setAttribute('aria-expanded', 'true');
                const chevron = trigger.querySelector('svg');
                if (chevron) chevron.style.transform = 'rotate(180deg)';
                const searchInput = panel.querySelector('input[type="text"]');
                if (searchInput) setTimeout(() => searchInput.focus(), 30);
            }

            function closePanel(root, trigger) {
                const panel = root.querySelector('[role="listbox"]');
                if (!panel) return;
                panel.classList.add('hidden');
                trigger.setAttribute('aria-expanded', 'false');
                const chevron = trigger.querySelector('svg');
                if (chevron) chevron.style.transform = '';
            }

            const root = document.querySelector('[data-sdd-root="project_id"]');
            if (!root) return;

            const id = 'project_id';
            const trigger = root.querySelector(`#${id}_trigger`);
            const panel = root.querySelector(`#${id}_panel`);
            const searchInput = root.querySelector(`#${id}_search`);
            const listEl = root.querySelector(`#${id}_list`);
            const hiddenInput = root.querySelector(`#${id}`);
            const displayEl = root.querySelector(`#${id}_display`);
            const badgeEl = root.querySelector(`#${id}_badge`);
            const clearBtn = root.querySelector('[data-sdd-clear]');

            if (!trigger || !listEl || !hiddenInput) return;

            renderItems(listEl, projectItems, '');
            if (badgeEl) badgeEl.textContent = projectItems.length + ' Project';

            trigger.addEventListener('click', () => {
                const isOpen = !panel.classList.contains('hidden');
                if (isOpen) { closePanel(root, trigger); return; }
                openPanel(root, trigger);
                renderItems(listEl, projectItems, searchInput?.value || '');
            });

            searchInput?.addEventListener('input', () => {
                renderItems(listEl, projectItems, searchInput.value);
            });

            listEl.addEventListener('click', (e) => {
                const li = e.target.closest('li[data-value]');
                if (!li) return;
                hiddenInput.value = li.dataset.value;
                displayEl.textContent = li.dataset.label || li.dataset.value;
                displayEl.classList.remove('text-muted');
                displayEl.classList.add('text-ink');
                closePanel(root, trigger);
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            });

            clearBtn?.addEventListener('click', () => {
                hiddenInput.value = '';
                displayEl.textContent = 'Pilih project';
                displayEl.classList.add('text-muted');
                displayEl.classList.remove('text-ink');
                closePanel(root, trigger);
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            });

            document.addEventListener('click', (e) => {
                if (!root.contains(e.target)) closePanel(root, trigger);
            }, true);

            panel?.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') { closePanel(root, trigger); trigger.focus(); }
            });
        })();

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
