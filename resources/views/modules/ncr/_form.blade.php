@php
    $ncr = $ncr ?? null;
    $selectedRelated = old('related_departments', $ncr?->related_departments ?? []);
    if (! is_array($selectedRelated)) {
        $selectedRelated = [];
    }
    $selectedRootCauses = old('root_cause_category', $ncr?->root_cause_category ?? []);
    if (is_string($selectedRootCauses)) {
        $selectedRootCauses = json_decode($selectedRootCauses, true) ?? array_values(array_filter(array_map('trim', explode(',', $selectedRootCauses))));
    }
    if (! is_array($selectedRootCauses)) {
        $selectedRootCauses = [];
    }
    $currentLevel = old('level', $ncr?->level);
    $selectedType = old('type', $ncr?->type);
    $currentStatus = old('status', $ncr?->status);
    $customerHint = ($selectedType === 'Internal') ? 'Tipe Internal tidak memakai project' : 'Terisi otomatis dari project, tetap bisa diubah';
@endphp

<div class="ncr-form-layout">
    {{-- Navigasi Sticky Kiri: Isian NCR --}}
    <aside aria-label="Navigasi Isian NCR" class="ncr-form-nav rounded-2xl border border-line bg-white p-3">
        <h2 class="px-3 py-1.5 text-[14px] font-semibold text-ink">Isian NCR</h2>

        <nav aria-label="Navigasi bagian formulir" class="mt-1 space-y-1 nav-list">
            @php
                $navSections = [
                    ['id' => 's1', 'num' => 1, 'name' => 'Temuan'],
                    ['id' => 's2', 'num' => 2, 'name' => 'Akar Masalah'],
                    ['id' => 's3', 'num' => 3, 'name' => 'Corrective Action'],
                    ['id' => 's4', 'num' => 4, 'name' => 'Preventive Action'],
                    ['id' => 's5', 'num' => 5, 'name' => 'NC Cost & Status'],
                ];
            @endphp
            @foreach ($navSections as $sec)
                <a href="#{{ $sec['id'] }}"
                   data-nav-sec="{{ $sec['id'] }}"
                   data-sec-num="{{ $sec['num'] }}"
                   class="ncr-nav-link flex min-h-[44px] items-center gap-2.5 rounded-[10px] px-3 py-2 text-[13px] font-medium text-muted transition hover:bg-paper-soft hover:text-ink">
                    <span class="ncr-nav-indicator flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-full border-2 border-[#B9B5A6] font-mono text-[11px] font-semibold text-muted">
                        {{ $sec['num'] }}
                    </span>
                    <span class="truncate">{{ $sec['name'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="nav-note mt-3 border-t border-line px-3 pt-3">
            <p class="text-[12px] leading-relaxed text-muted">
                @if (isset($ncr) && $ncr->ncr_number)
                    Nomor NCR: <span class="font-mono font-semibold text-ink">{{ $ncr->ncr_number }}</span>
                @else
                    Nomor NCR dibuat otomatis setelah disimpan (format NCR-{{ date('Y') }}-xxx).
                @endif
            </p>
        </div>
    </aside>

    {{-- Kolom Kanan: 5 Kartu Seksi + Footer --}}
    <div class="ncr-form-content space-y-4">

        {{-- 1. Temuan --}}
        <section id="s1" class="rounded-2xl border border-line bg-white p-[26px]">
            <div class="mb-5 flex items-center justify-between border-b border-line pb-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full bg-brand font-mono text-[14px] font-bold text-white">
                        1
                    </span>
                    <div>
                        <h2 class="text-[18px] font-semibold text-ink">1. Temuan</h2>
                        <p class="mt-0.5 text-[13px] text-muted">Informasi identitas temuan, lokasi, dan project terkait.</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-x-6 gap-y-5 grid-cols-1 sm:grid-cols-2">
                {{-- Tanggal Temuan --}}
                <x-form-field id="finding_date" label="Tanggal Temuan" :required="true" :error="$errors->first('finding_date')">
                    <input type="date" id="finding_date" name="finding_date" required
                           value="{{ old('finding_date', $ncr?->finding_date?->format('Y-m-d') ?? date('Y-m-d')) }}"
                           class="form-control {{ $errors->has('finding_date') ? 'form-control-invalid' : '' }}"
                           aria-invalid="{{ $errors->has('finding_date') ? 'true' : 'false' }}"
                           @if($errors->has('finding_date')) aria-describedby="finding_date-error" @endif />
                </x-form-field>

                {{-- Department --}}
                <x-form-field id="department" label="Department" :required="true" :error="$errors->first('department')">
                    <select id="department" name="department" required class="form-control {{ $errors->has('department') ? 'form-control-invalid' : '' }}"
                            aria-invalid="{{ $errors->has('department') ? 'true' : 'false' }}"
                            @if($errors->has('department')) aria-describedby="department-error" @endif>
                        <option value="">Pilih department</option>
                        @foreach (config('ncr.departments', []) as $dept)
                            <option value="{{ $dept }}" @selected(old('department', $ncr?->department) === $dept)>{{ $dept }}</option>
                        @endforeach
                    </select>
                </x-form-field>

                {{-- Area Finding --}}
                <x-form-field id="area_finding" label="Area Finding" :required="true" :error="$errors->first('area_finding')" hint="Pilih dari daftar atau ketik manual">
                    <input type="text" id="area_finding" name="area_finding" list="area_finding_options" required
                           value="{{ old('area_finding', $ncr?->area_finding) }}"
                           placeholder="Pilih atau ketik area temuan..."
                           class="form-control {{ $errors->has('area_finding') ? 'form-control-invalid' : '' }}"
                           aria-invalid="{{ $errors->has('area_finding') ? 'true' : 'false' }}"
                           @if($errors->has('area_finding')) aria-describedby="area_finding-error" @endif />
                    <datalist id="area_finding_options">
                        @foreach (config('ncr.areas', []) as $area)
                            <option value="{{ $area }}"></option>
                        @endforeach
                    </datalist>
                </x-form-field>

                {{-- Level --}}
                <div class="sm:col-span-2 flex flex-col gap-[6px]">
                    <label class="text-[14px] font-semibold text-ink">Level<span aria-hidden="true" class="text-alert"> *</span></label>
                    <x-choice-card-group name="level" :selected="$currentLevel" :required="true" grid="grid-cols-1 sm:grid-cols-3">
                        <x-choice-card name="level" value="Major" label="Major" :checked="$currentLevel === 'Major'" :required="true" />
                        <x-choice-card name="level" value="Minor" label="Minor" :checked="$currentLevel === 'Minor'" :required="true" />
                        <x-choice-card name="level" value="Near Miss" label="Near Miss" :checked="$currentLevel === 'Near Miss'" :required="true" />
                    </x-choice-card-group>
                    @if ($errors->has('level'))
                        <p id="level-error" class="text-[13px] font-medium text-[#8F3F08]" role="alert">{{ $errors->first('level') }}</p>
                    @endif
                </div>

                {{-- Tipe NCR --}}
                <div class="sm:col-span-2 flex flex-col gap-[6px]">
                    <label class="text-[14px] font-semibold text-ink">Tipe NCR<span aria-hidden="true" class="text-alert"> *</span></label>
                    <x-choice-card-group name="type" :selected="$selectedType" :required="true" grid="grid-cols-1 sm:grid-cols-2">
                        <x-choice-card name="type" value="Project" label="Project" :checked="$selectedType === 'Project'" :required="true" />
                        <x-choice-card name="type" value="Internal" label="Internal" :checked="$selectedType === 'Internal'" :required="true" />
                    </x-choice-card-group>
                    @if ($errors->has('type'))
                        <p id="type-error" class="text-[13px] font-medium text-[#8F3F08]" role="alert">{{ $errors->first('type') }}</p>
                    @endif
                </div>

                {{-- Project Code --}}
                <div id="project_code_wrapper" style="{{ $selectedType === 'Internal' ? 'display: none;' : '' }}">
                    <x-form-field id="project_code" label="Project" :required="true" :error="$errors->first('project_code')" hint="Wajib untuk tipe Project">
                        <select id="project_code" name="project_code" class="form-control {{ $errors->has('project_code') ? 'form-control-invalid' : '' }}"
                                aria-invalid="{{ $errors->has('project_code') ? 'true' : 'false' }}"
                                @if($errors->has('project_code')) aria-describedby="project_code-error" @endif>
                            <option value="">Pilih project</option>
                            @foreach ($projects as $prj)
                                <option value="{{ $prj->project_code }}"
                                        data-customer="{{ $prj->customer }}"
                                        @selected(old('project_code', $ncr?->project_code) === $prj->project_code)>
                                    {{ $prj->project_code }} — {{ $prj->project_name }}
                                </option>
                            @endforeach
                        </select>
                    </x-form-field>
                </div>

                {{-- Customer --}}
                <x-form-field id="customer" label="Customer" :required="false" :error="$errors->first('customer')" :hint="$customerHint" hintId="customer_hint">
                    <input type="text" id="customer" name="customer"
                           value="{{ old('customer', $ncr?->customer) }}"
                           placeholder="Nama customer / klien"
                           class="form-control {{ $errors->has('customer') ? 'form-control-invalid' : '' }}"
                           aria-invalid="{{ $errors->has('customer') ? 'true' : 'false' }}"
                           @if($errors->has('customer')) aria-describedby="customer-error" @endif />
                </x-form-field>

                {{-- Responsible PIC --}}
                <x-form-field id="responsible_pic" label="Responsible PIC" :required="true" :error="$errors->first('responsible_pic')">
                    <input type="text" id="responsible_pic" name="responsible_pic" required
                           value="{{ old('responsible_pic', $ncr?->responsible_pic) }}"
                           placeholder="Nama penanggung jawab"
                           class="form-control {{ $errors->has('responsible_pic') ? 'form-control-invalid' : '' }}"
                           aria-invalid="{{ $errors->has('responsible_pic') ? 'true' : 'false' }}"
                           @if($errors->has('responsible_pic')) aria-describedby="responsible_pic-error" @endif />
                </x-form-field>

                {{-- Related Departments --}}
                <div class="sm:col-span-2 flex flex-col gap-2">
                    <label class="text-[14px] font-semibold text-ink">Related Departments</label>
                    <div class="flex flex-wrap gap-2.5">
                        @foreach (config('ncr.related_departments', []) as $rdept)
                            <x-checkbox-pill name="related_departments[]"
                                             :value="$rdept"
                                             :label="$rdept"
                                             :checked="in_array($rdept, $selectedRelated, true)" />
                        @endforeach
                    </div>
                    @if ($errors->has('related_departments'))
                        <p class="text-[13px] font-medium text-[#8F3F08]" role="alert">{{ $errors->first('related_departments') }}</p>
                    @endif
                </div>

                {{-- Problem Description --}}
                <x-form-field id="problem_description" label="Uraian Masalah (Problem Description)" :required="true" :span="true" :error="$errors->first('problem_description')">
                    <textarea id="problem_description" name="problem_description" rows="3" required
                              placeholder="Jelaskan ketidaksesuaian yang ditemukan..."
                              class="form-control {{ $errors->has('problem_description') ? 'form-control-invalid' : '' }}"
                              aria-invalid="{{ $errors->has('problem_description') ? 'true' : 'false' }}"
                              @if($errors->has('problem_description')) aria-describedby="problem_description-error" @endif>{{ old('problem_description', $ncr?->problem_description) }}</textarea>
                </x-form-field>
            </div>
        </section>

        {{-- 2. Akar Masalah --}}
        <section id="s2" class="rounded-2xl border border-line bg-white p-[26px]">
            <div class="mb-5 flex items-center justify-between border-b border-line pb-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full bg-brand font-mono text-[14px] font-bold text-white">
                        2
                    </span>
                    <div>
                        <h2 class="text-[18px] font-semibold text-ink">2. Akar Masalah</h2>
                        <p class="mt-0.5 text-[13px] text-muted">Analisis penyebab ketidaksesuaian dan kategorinya.</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-x-6 gap-y-5 grid-cols-1 sm:grid-cols-2">
                {{-- Root Cause Category (multi-checkbox) --}}
                <div class="sm:col-span-2 flex flex-col gap-2">
                    <label class="text-[14px] font-semibold text-ink">Kategori Akar Masalah<span aria-hidden="true" class="text-alert"> *</span></label>
                    <p class="text-[12px] text-muted">Pilih satu atau lebih kategori (4M) yang sesuai.</p>
                    <div class="flex flex-wrap gap-2.5" role="group" aria-label="Kategori Akar Masalah">
                        @foreach (config('ncr.root_cause_categories', []) as $rcCat)
                            <x-checkbox-pill
                                name="root_cause_category[]"
                                :value="$rcCat"
                                :label="$rcCat"
                                :checked="in_array($rcCat, $selectedRootCauses, true)" />
                        @endforeach
                    </div>
                    @if ($errors->has('root_cause_category') || $errors->has('root_cause_category.*'))
                        <p id="root_cause_category-error" class="text-[13px] font-medium text-[#8F3F08]" role="alert">
                            {{ $errors->first('root_cause_category') ?: $errors->first('root_cause_category.*') }}
                        </p>
                    @endif
                </div>

                {{-- Root Cause Description --}}
                <x-form-field id="root_cause" label="Akar Masalah (Root Cause)" :required="true" :span="true" :error="$errors->first('root_cause')">
                    <textarea id="root_cause" name="root_cause" rows="3" required
                              placeholder="Jelaskan akar penyebab terjadinya ketidaksesuaian..."
                              class="form-control {{ $errors->has('root_cause') ? 'form-control-invalid' : '' }}"
                              aria-invalid="{{ $errors->has('root_cause') ? 'true' : 'false' }}"
                              @if($errors->has('root_cause')) aria-describedby="root_cause-error" @endif>{{ old('root_cause', $ncr?->root_cause) }}</textarea>
                </x-form-field>
            </div>
        </section>

        {{-- 3. Corrective Action (CA) --}}
        <section id="s3" class="rounded-2xl border border-line bg-white p-[26px]">
            <div class="mb-5 flex items-center justify-between border-b border-line pb-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full bg-brand font-mono text-[14px] font-bold text-white">
                        3
                    </span>
                    <div>
                        <h2 class="text-[18px] font-semibold text-ink">3. Corrective Action (CA)</h2>
                        <p class="mt-0.5 text-[13px] text-muted">Tindakan perbaikan langsung terhadap masalah yang terjadi.</p>
                    </div>
                </div>
                <span class="inline-flex items-center rounded-full bg-[#EEEBE1] px-2.5 py-0.5 text-[12px] font-medium text-[#3F4A41]">
                    Opsional
                </span>
            </div>

            <div class="grid gap-x-6 gap-y-5 grid-cols-1 sm:grid-cols-2">
                {{-- Corrective Action --}}
                <x-form-field id="corrective_action" label="Tindakan Koreksi" :required="false" :span="true" :error="$errors->first('corrective_action')">
                    <textarea id="corrective_action" name="corrective_action" rows="3"
                              placeholder="Jelaskan tindakan koreksi yang dilakukan..."
                              class="form-control {{ $errors->has('corrective_action') ? 'form-control-invalid' : '' }}"
                              aria-invalid="{{ $errors->has('corrective_action') ? 'true' : 'false' }}"
                              @if($errors->has('corrective_action')) aria-describedby="corrective_action-error" @endif>{{ old('corrective_action', $ncr?->corrective_action) }}</textarea>
                </x-form-field>

                {{-- PIC CA --}}
                <x-form-field id="pic_ca" label="PIC Corrective Action" :required="false" :error="$errors->first('pic_ca')">
                    <input type="text" id="pic_ca" name="pic_ca"
                           value="{{ old('pic_ca', $ncr?->pic_ca) }}"
                           placeholder="Nama PIC CA"
                           class="form-control {{ $errors->has('pic_ca') ? 'form-control-invalid' : '' }}"
                           aria-invalid="{{ $errors->has('pic_ca') ? 'true' : 'false' }}"
                           @if($errors->has('pic_ca')) aria-describedby="pic_ca-error" @endif />
                </x-form-field>

                {{-- Tanggal CA Closed --}}
                <x-form-field id="ca_closed_date" label="Tanggal CA Closed" :required="false" :error="$errors->first('ca_closed_date')" hint="Wajib jika status CA Closed atau Closed">
                    <input type="date" id="ca_closed_date" name="ca_closed_date"
                           value="{{ old('ca_closed_date', $ncr?->ca_closed_date?->format('Y-m-d')) }}"
                           class="form-control {{ $errors->has('ca_closed_date') ? 'form-control-invalid' : '' }}"
                           aria-invalid="{{ $errors->has('ca_closed_date') ? 'true' : 'false' }}"
                           @if($errors->has('ca_closed_date')) aria-describedby="ca_closed_date-error" @endif />
                </x-form-field>
            </div>
        </section>

        {{-- 4. Preventive Action (PA) & Status --}}
        <section id="s4" class="rounded-2xl border border-line bg-white p-[26px]">
            <div class="mb-5 flex items-center justify-between border-b border-line pb-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full bg-brand font-mono text-[14px] font-bold text-white">
                        4
                    </span>
                    <div>
                        <h2 class="text-[18px] font-semibold text-ink">4. Preventive Action (PA) & Status</h2>
                        <p class="mt-0.5 text-[13px] text-muted">Tindakan pencegahan agar masalah tidak berulang dan penentuan status NCR.</p>
                    </div>
                </div>
                <span class="inline-flex items-center rounded-full bg-[#EEEBE1] px-2.5 py-0.5 text-[12px] font-medium text-[#3F4A41]">
                    Opsional
                </span>
            </div>

            <div class="grid gap-x-6 gap-y-5 grid-cols-1 sm:grid-cols-2">
                {{-- Preventive Action --}}
                <x-form-field id="preventive_action" label="Tindakan Pencegahan" :required="false" :span="true" :error="$errors->first('preventive_action')">
                    <textarea id="preventive_action" name="preventive_action" rows="3"
                              placeholder="Jelaskan tindakan preventif agar masalah tidak berulang..."
                              class="form-control {{ $errors->has('preventive_action') ? 'form-control-invalid' : '' }}"
                              aria-invalid="{{ $errors->has('preventive_action') ? 'true' : 'false' }}"
                              @if($errors->has('preventive_action')) aria-describedby="preventive_action-error" @endif>{{ old('preventive_action', $ncr?->preventive_action) }}</textarea>
                </x-form-field>

                {{-- PIC PA --}}
                <x-form-field id="pic_pa" label="PIC Preventive Action" :required="false" :error="$errors->first('pic_pa')">
                    <input type="text" id="pic_pa" name="pic_pa"
                           value="{{ old('pic_pa', $ncr?->pic_pa) }}"
                           placeholder="Nama PIC PA"
                           class="form-control {{ $errors->has('pic_pa') ? 'form-control-invalid' : '' }}"
                           aria-invalid="{{ $errors->has('pic_pa') ? 'true' : 'false' }}"
                           @if($errors->has('pic_pa')) aria-describedby="pic_pa-error" @endif />
                </x-form-field>

                {{-- Tanggal PA Closed --}}
                <x-form-field id="pa_closed_date" label="Tanggal PA Closed" :required="false" :error="$errors->first('pa_closed_date')" hint="Wajib jika status Closed dan tindakan pencegahan diisi">
                    <input type="date" id="pa_closed_date" name="pa_closed_date"
                           value="{{ old('pa_closed_date', $ncr?->pa_closed_date?->format('Y-m-d')) }}"
                           class="form-control {{ $errors->has('pa_closed_date') ? 'form-control-invalid' : '' }}"
                           aria-invalid="{{ $errors->has('pa_closed_date') ? 'true' : 'false' }}"
                           @if($errors->has('pa_closed_date')) aria-describedby="pa_closed_date-error" @endif />
                </x-form-field>
            </div>
        </section>

        {{-- 5. NC Cost & Status --}}
        <section id="s5" class="rounded-2xl border border-line bg-white p-[26px]">
            <div class="mb-5 flex items-center justify-between border-b border-line pb-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full bg-brand font-mono text-[14px] font-bold text-white">
                        5
                    </span>
                    <div>
                        <h2 class="text-[18px] font-semibold text-ink">5. NC Cost & Status</h2>
                        <p class="mt-0.5 text-[13px] text-muted">Biaya akibat ketidaksesuaian dan status penyelesaian NCR.</p>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="max-w-md">
                    <x-form-field id="nc_cost" label="Total NC Cost" :required="false" :error="$errors->first('nc_cost')" hint="Bisa memasukkan angka dengan titik pemisah ribuan">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 font-mono text-[15px] font-semibold text-muted">
                                Rp
                            </span>
                            <input type="text" id="nc_cost" name="nc_cost"
                                   value="{{ old('nc_cost', isset($ncr) ? number_format($ncr->nc_cost, 0, ',', '.') : '') }}"
                                   placeholder="0"
                                   class="form-control font-mono text-[16px] font-semibold {{ $errors->has('nc_cost') ? 'form-control-invalid' : '' }}"
                                   style="padding-left: 44px;"
                                   aria-invalid="{{ $errors->has('nc_cost') ? 'true' : 'false' }}"
                                   @if($errors->has('nc_cost')) aria-describedby="nc_cost-error" @endif />
                        </div>
                    </x-form-field>
                </div>

                {{-- Status NCR (dipindah ke sini, setelah NC Cost) --}}
                <div class="flex flex-col gap-[6px]">
                    <label class="text-[14px] font-semibold text-ink">Status NCR<span aria-hidden="true" class="text-alert"> *</span></label>
                    <x-choice-card-group name="status" :selected="$currentStatus" :required="true" grid="grid-cols-1 sm:grid-cols-3">
                        <x-choice-card name="status" value="Open" label="Open" description="Temuan baru, tindakan belum selesai" :checked="$currentStatus === 'Open'" :required="true" />
                        <x-choice-card name="status" value="CA Closed" label="CA Closed" description="Tindakan koreksi sudah selesai" :checked="$currentStatus === 'CA Closed'" :required="true" />
                        <x-choice-card name="status" value="Closed" label="Closed" description="Koreksi dan pencegahan selesai" :checked="$currentStatus === 'Closed'" :required="true" />
                    </x-choice-card-group>
                    @if ($errors->has('status'))
                        <p id="status-error" class="text-[13px] font-medium text-[#8F3F08]" role="alert">{{ $errors->first('status') }}</p>
                    @endif
                </div>
            </div>
        </section>

        {{-- Footer --}}
        <div class="flex flex-wrap items-center justify-between gap-4 pt-4">
            <p class="text-[13px] text-muted">Field bertanda <span class="text-alert">*</span> wajib diisi.</p>
            <div class="flex items-center gap-3">
                <a href="{{ isset($ncr) ? route('ncr.show', $ncr) : route('ncr.index') }}"
                   class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] border border-[#8C978B] bg-white px-6 text-[14px] font-semibold text-ink transition hover:bg-paper-soft">
                    Batal
                </a>
                <button type="submit" id="ncr-submit-btn"
                        class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] bg-brand px-7 text-[14px] font-semibold text-white transition hover:bg-brand-dark disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ isset($ncr) ? 'Perbarui NCR' : 'Simpan NCR' }}
                </button>
            </div>
        </div>

    </div>
</div>

<style>
    @media (min-width: 980px) {
        .ncr-form-layout {
            display: grid;
            grid-template-columns: 230px 1fr;
            gap: 24px;
            align-items: start;
        }
        .ncr-form-nav {
            position: sticky;
            top: 16px;
        }
    }
    @media (max-width: 979px) {
        .ncr-form-layout {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .ncr-form-nav {
            position: static;
            overflow-x: auto;
            white-space: nowrap;
        }
        .ncr-form-nav .nav-list {
            display: flex;
            flex-direction: row;
            gap: 8px;
        }
        .ncr-form-nav .nav-note {
            display: none;
        }
    }
</style>

<script>
    (function() {
        const form = document.getElementById('ncr-form');
        if (!form) return;
        const projectWrapper = document.getElementById('project_code_wrapper');
        const customerHint = document.getElementById('customer_hint');

        // Sync project wrapper visibility based on type (null = not selected → show wrapper)
        function syncType() {
            const checked = form.querySelector('input[name="type"]:checked')?.value ?? null;
            if (checked === 'Internal') {
                if (projectWrapper) projectWrapper.style.display = 'none';
                if (customerHint) customerHint.textContent = 'Tipe Internal tidak memakai project';
            } else {
                // Show for Project OR when nothing selected (default state)
                if (projectWrapper) projectWrapper.style.display = checked ? 'block' : 'none';
                if (customerHint) customerHint.textContent = checked ? 'Terisi otomatis dari project, tetap bisa diubah' : '';
            }
        }
        form.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'type') syncType();
        });
        syncType();

        // Toggle deselect: track checked state before pointer hits the card
        let _radioWasChecked = false;
        form.addEventListener('pointerdown', function(e) {
            const input = e.target.closest('.choice-card')?.querySelector('input[type="radio"]')
                ?? (e.target.type === 'radio' ? e.target : null);
            _radioWasChecked = input ? input.checked : false;
        }, true);

        form.addEventListener('click', function(e) {
            const card = e.target.closest('.choice-card');
            if (!card) return;
            const input = card.querySelector('input[type="radio"]');
            if (!input) return;
            if (_radioWasChecked) {
                input.checked = false;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            _radioWasChecked = false;
        });

        // Keyboard: Space on focused+checked radio deselects it
        form.addEventListener('keydown', function(e) {
            if (e.key !== ' ') return;
            const input = e.target;
            if (input.type !== 'radio' || !input.closest('.choice-card')) return;
            if (input.checked) {
                e.preventDefault();
                input.checked = false;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        // Sync card visual classes on change
        function syncCard(radio) {
            const card = radio.closest('.choice-card') || radio.closest('label');
            if (!card) return;
            if (radio.checked) {
                card.classList.remove('border', 'border-[#8C978B]', 'bg-white', 'font-medium');
                card.classList.add('border-2', 'border-brand', 'bg-[#E9EFE5]', 'font-semibold');
            } else {
                card.classList.remove('border-2', 'border-brand', 'bg-[#E9EFE5]', 'font-semibold');
                card.classList.add('border', 'border-[#8C978B]', 'bg-white', 'font-medium');
            }
        }

        form.addEventListener('change', function(e) {
            if (e.target && e.target.type === 'radio' && e.target.name) {
                const radios = form.querySelectorAll('input[type="radio"][name="' + e.target.name + '"]');
                radios.forEach(syncCard);
            }
        });
    })();
</script>
