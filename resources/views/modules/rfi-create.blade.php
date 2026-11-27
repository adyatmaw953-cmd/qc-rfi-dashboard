@php
    $isEdit = isset($rfi) && $rfi->exists;
    $hasDrafts = ! empty($category['drafts']);
    $pageTitle = $isEdit
        ? ($categoryCode === 'F' ? "Ubah Draft RFI - Location: {$category['label']}" : "Ubah Draft RFI - Kategori {$categoryCode}: {$category['label']}")
        : ($categoryCode === 'F' ? "Buat RFI - Location: {$category['label']}" : "Buat RFI - Kategori {$categoryCode}: {$category['label']}");
@endphp

@extends('layouts.module', ['active' => 'rfi', 'pageTitle' => $pageTitle])

@section('header_action')
    <a href="{{ route('rfi.category', ['kategori' => $categoryCode]) }}"
       class="hidden sm:inline-flex min-h-[44px] items-center justify-center rounded-xl border border-line bg-white px-5 text-[14px] font-semibold text-ink hover:bg-paper transition">
        &larr; Kembali
    </a>
@endsection

@section('content')
@if ($categoryCode === 'F')
    <form id="createRfiForm" method="POST"
          action="{{ $isEdit ? route('rfi.update', ['kategori' => $categoryCode, 'rfi' => $rfi]) : route('rfi.store', ['kategori' => $categoryCode]) }}"
          class="max-w-[1000px] space-y-4">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        {{-- Top validation error alert matching InspCustomerAdditional.dc.html --}}
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-[#F0C7A8] bg-[#FCE8D8] p-[14px_18px] text-[14px] leading-relaxed text-[#8F3F08] mb-4">
                <b class="font-semibold">Periksa kembali isian berikut:</b>
                <ul class="mt-1.5 list-disc pl-5 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="sm:hidden mb-2">
            <div class="text-[13px] font-medium text-[#366F3A]">PT Mudalaya Indo</div>
            <h1 class="mt-0.5 text-[24px] font-semibold tracking-tight text-ink">Buat RFI - Location: Inspection by Customer</h1>
            <p class="mt-1 text-[13px] text-muted">RFI untuk inspeksi yang dilakukan oleh customer.</p>
        </div>

        <section class="rounded-2xl border border-line bg-white p-5 sm:p-[26px]">
            <h2 class="text-[18px] font-semibold text-ink">Informasi umum</h2>
            <div class="mt-0.5 mb-4 text-[13px] text-muted font-normal">RFI untuk inspeksi yang dilakukan oleh customer.</div>

            <div class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                {{-- 1. Project --}}
                @php
                    $selectedProjectId = old('project_id', $isEdit ? $rfi->project_id : (request('project') ?: ''));
                    $selectedProject = $projects->firstWhere('id', (int) $selectedProjectId);
                    $initialCustomer = $selectedProject?->customer ?? '';
                    $projectDisplayText = $selectedProject
                        ? ($selectedProject->project_code . ' — ' . $selectedProject->project_name)
                        : '';
                    $projectBadgeF = $projects->count() . ' Project';
                @endphp
                <div class="flex flex-col gap-1.5">
                    <label for="project_select_trigger" class="text-[14px] font-semibold text-ink">
                        Project<span class="text-[#B4470F]"> *</span>
                    </label>
                    <x-searchable-dropdown
                        id="project_select"
                        name="project_id"
                        :label="'Pilih project'"
                        :value="(string) ($selectedProjectId ?: '')"
                        :displayText="$projectDisplayText"
                        placeholder="Cari project..."
                        :required="true"
                        :badge-count="$projectBadgeF"
                        :error="$errors->first('project_id')"
                        data-sdd-type="static"
                    />
                </div>

                {{-- 2. Tanggal Inspeksi --}}
                <div class="flex flex-col gap-1.5">
                    <label for="date_inspect" class="text-[14px] font-semibold text-ink">
                        Tanggal inspeksi<span class="text-[#B4470F]"> *</span>
                    </label>
                    <input
                        id="date_inspect"
                        name="date_inspect"
                        type="date"
                        required
                        value="{{ old('date_inspect', $isEdit ? $rfi->date_inspect?->format('Y-m-d') : now()->toDateString()) }}"
                        class="form-control {{ $errors->has('date_inspect') ? 'form-control-invalid' : '' }}"
                        @if ($errors->has('date_inspect')) aria-invalid="true" @endif
                    >
                    @error('date_inspect')
                        <div class="text-[13px] font-medium text-[#8F3F08]" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                {{-- 3. Customer (Auto-filled from Project) --}}
                <div class="flex flex-col gap-1.5">
                    <span class="text-[14px] font-semibold text-ink">Customer</span>
                    <div class="flex min-h-[48px] items-center gap-2 rounded-xl border border-line bg-paper px-4 py-3 text-[14px] box-border">
                        <span class="text-[13px] font-medium text-muted">Customer</span>
                        <b id="customer_display" class="font-semibold text-ink">{{ $initialCustomer ?: '—' }}</b>
                    </div>
                    <div class="text-[12px] text-muted">Terisi otomatis dari project. Customer inilah yang melakukan inspeksi.</div>
                </div>

                {{-- Spacer to balance grid on desktop --}}
                <div class="hidden sm:block"></div>

                {{-- 4. Jenis Inspeksi --}}
                @php
                    $jenisOptions = [
                        'Material inspection',
                        'Fit up inspection',
                        'Welding inspection',
                        'Load test',
                        'NDE test',
                        'Painting inspection',
                        'Final inspection',
                        'Additional',
                    ];
                    $currentJenis = old('details.jenis_inspeksi', $isEdit ? data_get($rfi->details, 'jenis_inspeksi') : 'Welding inspection');
                @endphp
                <fieldset class="col-span-1 sm:col-span-2 border-0 p-0 m-0 min-w-0">
                    <legend class="text-[14px] font-semibold text-ink mb-2.5 p-0">
                        Jenis inspeksi<span class="text-[#B4470F]"> *</span>
                    </legend>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        @foreach ($jenisOptions as $option)
                            @php
                                $isChecked = ($currentJenis === $option);
                            @endphp
                            <label class="jenis-radio-card flex items-center gap-2.5 min-h-[56px] px-3.5 rounded-xl cursor-pointer transition box-border {{ $isChecked ? 'border-2 border-brand bg-[#E9EFE5]' : 'border border-[#8C978B] bg-white hover:border-brand' }}">
                                <input
                                    type="radio"
                                    name="details[jenis_inspeksi]"
                                    value="{{ $option }}"
                                    class="h-[18px] w-[18px] shrink-0 accent-brand cursor-pointer"
                                    @checked($isChecked)
                                >
                                <span class="text-[14px] leading-snug {{ $isChecked ? 'font-semibold text-ink' : 'font-medium text-ink' }}">
                                    {{ $option }}
                                    @if ($option === 'Additional')
                                        <span class="block text-[12px] font-normal text-muted">Isi nama inspeksi manual</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('details.jenis_inspeksi')
                        <div class="text-[13px] font-medium text-[#8F3F08] mt-1.5" role="alert">{{ $message }}</div>
                    @enderror

                    {{-- Additional Name Container --}}
                    <div id="additional_input_container" class="{{ $currentJenis === 'Additional' ? '' : 'hidden' }} mt-3.5 rounded-xl border border-line bg-paper p-4 sm:p-[16px_18px]">
                        <div class="flex flex-col gap-1.5">
                            <label for="manual" class="text-[14px] font-semibold text-ink">
                                Nama inspeksi tambahan<span class="text-[#B4470F]"> *</span>
                            </label>
                            <input
                                id="manual"
                                name="details[additional_name]"
                                type="text"
                                value="{{ old('details.additional_name', $isEdit ? data_get($rfi->details, 'additional_name') : '') }}"
                                placeholder="Contoh: Hydrotest"
                                class="form-control {{ $errors->has('details.additional_name') ? 'form-control-invalid' : '' }}"
                                @if ($errors->has('details.additional_name')) aria-invalid="true" @endif
                            >
                            @error('details.additional_name')
                                <div class="text-[13px] font-medium text-[#8F3F08]" role="alert">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </fieldset>

                {{-- 5. Catatan --}}
                <div class="col-span-1 sm:col-span-2 flex flex-col gap-1.5">
                    <label for="note" class="text-[14px] font-semibold text-ink">Catatan</label>
                    <textarea
                        id="note"
                        name="note"
                        rows="3"
                        placeholder="Opsional"
                        class="form-control"
                    >{{ old('note', $isEdit ? $rfi->note : '') }}</textarea>
                </div>
            </div>
        </section>

        {{-- Desktop Bottom Bar --}}
        <div class="hidden sm:flex rounded-2xl border border-line bg-white p-[16px_22px] justify-between items-center gap-3">
            <span class="text-[13px] text-muted">Field bertanda <span class="text-[#B4470F]">*</span> wajib diisi.</span>
            <div class="flex gap-3">
                <a href="{{ route('rfi.category', ['kategori' => $categoryCode]) }}"
                   class="inline-flex min-h-[48px] items-center justify-center rounded-xl border border-[#8C978B] bg-white px-5 text-[14px] font-semibold text-ink hover:bg-paper transition">
                    Batal
                </a>
                <button type="submit"
                        class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] bg-brand px-7 text-[14px] font-semibold text-white hover:bg-brand-dark transition">
                    Kirim RFI
                </button>
            </div>
        </div>

        {{-- Mobile Bottom Sticky Bar --}}
        <div class="sm:hidden flex gap-3 p-[12px_16px_24px] bg-white border-t border-line sticky bottom-0 z-20 -mx-4 -mb-6 mt-4">
            <a href="{{ route('rfi.category', ['kategori' => $categoryCode]) }}"
               class="inline-flex min-h-[48px] flex-1 items-center justify-center rounded-xl border border-[#8C978B] bg-white text-[14px] font-semibold text-ink">
                Batal
            </a>
            <button type="submit"
                    class="inline-flex min-h-[52px] flex-[2] items-center justify-center rounded-xl bg-brand text-[16px] font-semibold text-white hover:bg-brand-dark transition">
                Kirim RFI
            </button>
        </div>
    </form>
@else
    <form id="createRfiForm" method="POST"
          action="{{ $isEdit ? route('rfi.update', ['kategori' => $categoryCode, 'rfi' => $rfi]) : route('rfi.store', ['kategori' => $categoryCode]) }}"
          class="max-w-[920px] space-y-5">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <section class="rounded-2xl border border-line bg-white p-[26px]">
            <h2 class="mb-5 text-[16px] font-semibold text-ink">Informasi umum</h2>
            <div class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                @php
                    $topCommon = array_filter($commonFields, fn($f) => $f['name'] !== 'note');
                    $noteField = collect($commonFields)->firstWhere('name', 'note');
                @endphp

                @foreach ($topCommon as $field)
                    @php
                        $fieldName = $field['name'];
                        $err = $errors->first($fieldName);
                        $span = ($field['type'] === 'textarea');
                        $currentVal = old($fieldName, $isEdit ? ($fieldName === 'date_inspect' ? (! empty($rfi->details['_draft_no_date']) ? '' : $rfi->date_inspect?->format('Y-m-d')) : $rfi->$fieldName) : null);
                    @endphp

                    @if ($field['type'] === 'project')
                        @php
                            $selectedProject = $projects->firstWhere('id', (int) $currentVal);
                            $projectDisplayText = $selectedProject
                                ? ($selectedProject->project_code . ' — ' . $selectedProject->project_name)
                                : '';
                            $projectBadge = $projects->count() . ' Project';
                        @endphp
                        <div class="flex flex-col gap-[6px]">
                            <label for="project_id_trigger" class="text-[14px] font-semibold text-ink">
                                {{ $field['label'] }}<span aria-hidden="true" class="text-alert"> *</span>
                            </label>
                            <x-searchable-dropdown
                                id="project_id"
                                name="project_id"
                                :label="'Pilih project'"
                                :value="(string) ($currentVal ?? '')"
                                :displayText="$projectDisplayText"
                                placeholder="Cari project..."
                                :required="true"
                                :badge-count="$projectBadge"
                                :error="$err"
                                data-sdd-type="static"
                            />
                        </div>
                    @elseif ($field['type'] === 'textarea')
                        <x-form-field
                            :id="$fieldName"
                            :label="$field['label']"
                            :required="(bool) $field['required']"
                            :error="$err"
                            :span="$span"
                        >
                            <textarea
                                id="{{ $fieldName }}"
                                name="{{ $fieldName }}"
                                rows="3"
                                class="form-control {{ $err ? 'form-control-invalid' : '' }}"
                                @if ($err) aria-invalid="true" aria-describedby="{{ $fieldName }}-error" @endif
                            >{{ $currentVal }}</textarea>
                        </x-form-field>
                    @else
                        <x-form-field
                            :id="$fieldName"
                            :label="$field['label']"
                            :required="(bool) $field['required']"
                            :error="$err"
                            :span="$span"
                        >
                            <input
                                id="{{ $fieldName }}"
                                name="{{ $fieldName }}"
                                type="{{ $field['type'] }}"
                                @required($field['required'])
                                value="{{ $currentVal }}"
                                class="form-control {{ $err ? 'form-control-invalid' : '' }}"
                                @if ($err) aria-invalid="true" aria-describedby="{{ $fieldName }}-error" @endif
                            >
                        </x-form-field>
                    @endif
                @endforeach

                @if (! empty($category['general_fields']))
                    @foreach ($category['general_fields'] as $field)
                        @php
                            $isScheduledDate = $field['name'] === 'scheduled_at';
                            $fieldName = $isScheduledDate ? 'scheduled_at' : "details[{$field['name']}]";
                            $inputId = $isScheduledDate ? 'scheduled_at' : "details_{$field['name']}";
                            $errorKey = $isScheduledDate ? 'scheduled_at' : "details.{$field['name']}";
                            $err = $errors->first($errorKey);
                            $required = (bool) ($field['required'] ?? false) || isset($field['required_if']);
                            $hint = isset($field['required_if']) ? "Wajib diisi untuk item {$field['required_if']['value']}" : null;
                            $span = ($field['type'] ?? '') === 'textarea' || ($field['type'] ?? '') === 'multi_select';
                            $currentVal = old($errorKey, $isEdit ? ($isScheduledDate ? $rfi->scheduled_at?->format('Y-m-d') : data_get($rfi->details, $field['name'])) : null);
                            $showIfName = isset($field['show_if']) ? "details[{$field['show_if']['field']}]" : null;
                            $showIfValue = $field['show_if']['value'] ?? null;
                            $showIfClass = $showIfName ? 'hidden' : null;
                        @endphp

                        @if (in_array($field['name'], ['surat_jalan_no', 'vendor', 'po_no', 'bom_no'], true))
                            {{-- Searchable dropdown panel fields --}}
                            @php
                                $sddType  = match($field['name']) {
                                    'surat_jalan_no' => 'api-surat-jalan',
                                    'vendor'         => 'api-vendor',
                                    default          => 'vsm-material',
                                };
                                $sddLabel = match($field['name']) {
                                    'surat_jalan_no' => 'Cari No Surat Jalan (IN-...)...',
                                    'vendor'         => 'Cari vendor / supplier...',
                                    'po_no'          => 'Cari PO No...',
                                    default          => 'Cari BOM No...',
                                };
                            @endphp
                            <div class="flex flex-col gap-[6px] {{ $showIfClass }}"
                                 @if($showIfName) data-show-if-name="{{ $showIfName }}" data-show-if-value="{{ $showIfValue }}" @endif>
                                <label for="{{ $inputId }}_trigger" class="text-[14px] font-semibold text-ink">
                                    {{ $field['label'] }}@if($required)<span aria-hidden="true" class="text-alert"> *</span>@endif
                                </label>
                                <x-searchable-dropdown
                                    :id="$inputId"
                                    :name="$fieldName"
                                    :label="'Pilih ' . strtolower($field['label'])"
                                    :value="(string) ($currentVal ?? '')"
                                    :displayText="(string) ($currentVal ?? '')"
                                    :placeholder="$sddLabel"
                                    :required="$required"
                                    :error="$err"
                                    :sdd-type="$sddType"
                                    :data-sdd-type="$sddType"
                                />
                                @if($hint)
                                    <p class="text-[12px] text-muted">{{ $hint }}</p>
                                @endif
                            </div>
                        @elseif (($field['type'] ?? 'text') === 'select')
                            <x-form-field
                                :id="$inputId"
                                :label="$field['label']"
                                :required="$required"
                                :error="$err"
                                :hint="$hint"
                                :span="$span"
                                :class="$showIfClass"
                                :data-show-if-name="$showIfName"
                                :data-show-if-value="$showIfValue"
                            >
                                <select
                                    id="{{ $inputId }}"
                                    name="{{ $fieldName }}"
                                    @required($field['required'] ?? false)
                                    @if (isset($field['required_if']))
                                        data-required-if-name="details[{{ $field['required_if']['field'] }}]"
                                        data-required-if-value="{{ $field['required_if']['value'] }}"
                                    @endif
                                    class="form-control {{ $err ? 'form-control-invalid' : '' }}"
                                    @if ($err) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
                                >
                                    <option value="">Pilih {{ strtolower($field['label']) }}</option>
                                    @foreach ($field['options'] as $option)
                                        <option value="{{ $option }}" @selected((string) $currentVal === (string) $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </x-form-field>
                        @elseif (($field['type'] ?? 'text') === 'multi_select')
                            @php
                                $selectedItems = old($errorKey, $isEdit ? data_get($rfi->details, $field['name'], []) : []);
                                if (is_string($selectedItems)) {
                                    $selectedItems = array_values(array_filter(array_map('trim', explode(',', $selectedItems))));
                                } elseif (! is_array($selectedItems)) {
                                    $selectedItems = [];
                                }
                            @endphp
                            <x-form-field
                                :id="$inputId"
                                :label="$field['label']"
                                :required="$required"
                                :error="$err"
                                :hint="$hint"
                                :span="$span"
                                :class="$showIfClass"
                                :data-show-if-name="$showIfName"
                                :data-show-if-value="$showIfValue"
                            >
                                <div id="{{ $inputId }}_wrapper" class="space-y-2">
                                    <div class="flex flex-wrap sm:flex-nowrap gap-2">
                                        <select id="{{ $inputId }}_select" class="form-control flex-1 min-w-[180px]">
                                            <option value="">Pilih pengujian dari dropdown...</option>
                                            @foreach ($field['options'] as $option)
                                                <option value="{{ $option }}">{{ $option }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" id="{{ $inputId }}_custom" placeholder="Atau ketik jenis pengujian lain..." class="form-control flex-1 min-w-[200px]">
                                        <button type="button" id="{{ $inputId }}_add_btn" class="inline-flex min-h-[44px] shrink-0 items-center justify-center rounded-[10px] bg-brand px-4 text-[13px] font-semibold text-white transition hover:bg-brand-dark">
                                            + Tambah
                                        </button>
                                    </div>
                                    <p class="text-[12px] text-muted">Pilih dari dropdown atau ketik manual, lalu klik + Tambah. Bisa lebih dari satu.</p>
                                    <div id="{{ $inputId }}_tags" class="flex flex-wrap gap-2 pt-1 min-h-[44px] p-2.5 rounded-[10px] border border-[#B9B5A6] bg-paper-soft/40 items-center">
                                        @foreach ($selectedItems as $item)
                                            @if (trim((string) $item) !== '')
                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#E9EFE5] border border-brand/40 px-3 py-1 text-[13px] font-semibold text-[#1F5B2A]">
                                                    <span>{{ $item }}</span>
                                                    <input type="hidden" name="{{ $fieldName }}[]" value="{{ $item }}">
                                                    <button type="button" onclick="this.closest('span').remove(); checkTagsEmpty('{{ $inputId }}');" class="text-[#1F5B2A] hover:text-alert font-bold ml-1 focus:outline-none" title="Hapus">&times;</button>
                                                </span>
                                            @endif
                                        @endforeach
                                        <span id="{{ $inputId }}_empty_hint" class="text-[12px] text-muted italic {{ !empty($selectedItems) ? 'hidden' : '' }}">Belum ada jenis pengujian ditambahkan</span>
                                    </div>
                                </div>
                            </x-form-field>
                        @elseif (($field['type'] ?? 'text') === 'textarea')
                            <x-form-field
                                :id="$inputId"
                                :label="$field['label']"
                                :required="$required"
                                :error="$err"
                                :hint="$hint"
                                :span="true"
                                :class="$showIfClass"
                                :data-show-if-name="$showIfName"
                                :data-show-if-value="$showIfValue"
                            >
                                <textarea
                                    id="{{ $inputId }}"
                                    name="{{ $fieldName }}"
                                    rows="3"
                                    @required($field['required'] ?? false)
                                    class="form-control {{ $err ? 'form-control-invalid' : '' }}"
                                    @if ($err) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
                                >{{ $currentVal }}</textarea>
                            </x-form-field>
                        @else
                            <x-form-field
                                :id="$inputId"
                                :label="$field['label']"
                                :required="$required"
                                :error="$err"
                                :hint="$hint"
                                :span="$span"
                                :class="$showIfClass"
                                :data-show-if-name="$showIfName"
                                :data-show-if-value="$showIfValue"
                            >
                                <input
                                    id="{{ $inputId }}"
                                    name="{{ $fieldName }}"
                                    type="{{ $field['type'] ?? 'text' }}"
                                    @required($field['required'] ?? false)
                                    value="{{ $currentVal }}"
                                    class="form-control {{ $err ? 'form-control-invalid' : '' }}"
                                    @if ($err) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
                                >
                            </x-form-field>
                        @endif
                    @endforeach
                @endif

                @if ($noteField)
                    @php
                        $fieldName = $noteField['name'];
                        $err = $errors->first($fieldName);
                        $currentVal = old($fieldName, $isEdit ? $rfi->$fieldName : null);
                    @endphp
                    <x-form-field
                        :id="$fieldName"
                        :label="$noteField['label']"
                        :required="(bool) $noteField['required']"
                        :error="$err"
                        :span="true"
                    >
                        <textarea
                            id="{{ $fieldName }}"
                            name="{{ $fieldName }}"
                            rows="3"
                            class="form-control {{ $err ? 'form-control-invalid' : '' }}"
                            @if ($err) aria-invalid="true" aria-describedby="{{ $fieldName }}-error" @endif
                        >{{ $currentVal }}</textarea>
                    </x-form-field>
                @endif
            </div>
        </section>

        @if (! empty($category['fields']))
        <section class="rounded-2xl border border-line bg-white p-[26px]">
            <h2 class="mb-5 text-[16px] font-semibold text-ink">Detail {{ $category['label'] }}</h2>
            <div class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                @foreach ($category['fields'] as $field)
                    @php
                        $isScheduledDate = $field['name'] === 'scheduled_at';
                        $inputName = $isScheduledDate ? 'scheduled_at' : "details[{$field['name']}]";
                        $inputId = $isScheduledDate ? 'scheduled_at' : "details_{$field['name']}";
                        $errorKey = $isScheduledDate ? 'scheduled_at' : "details.{$field['name']}";
                        $err = $errors->first($errorKey);
                        $required = (bool) ($field['required'] ?? false) || isset($field['required_if']);
                        $hint = isset($field['required_if']) ? "Wajib diisi untuk item {$field['required_if']['value']}" : null;
                        $span = ($field['type'] === 'textarea');
                        $currentVal = old($errorKey, $isEdit ? ($isScheduledDate ? $rfi->scheduled_at?->format('Y-m-d') : data_get($rfi->details, $field['name'])) : null);
                    @endphp
                    @php
                        $showIfName = isset($field['show_if']) ? "details[{$field['show_if']['field']}]" : null;
                        $showIfValue = $field['show_if']['value'] ?? null;
                        $showIfClass = $showIfName ? 'hidden' : null;
                    @endphp
                    <x-form-field
                        :id="$inputId"
                        :label="$field['label']"
                        :required="$required"
                        :error="$err"
                        :hint="$hint"
                        :span="$span"
                        :class="$showIfClass"
                        :data-show-if-name="$showIfName"
                        :data-show-if-value="$showIfValue"
                    >
                        @if ($field['type'] === 'select')
                            <select
                                id="{{ $inputId }}"
                                name="{{ $inputName }}"
                                @required($field['required'] ?? false)
                                @if (isset($field['required_if']))
                                    data-required-if-name="details[{{ $field['required_if']['field'] }}]"
                                    data-required-if-value="{{ $field['required_if']['value'] }}"
                                @endif
                                class="form-control {{ $err ? 'form-control-invalid' : '' }}"
                                @if ($err) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
                            >
                                <option value="">Pilih {{ strtolower($field['label']) }}</option>
                                @foreach ($field['options'] as $option)
                                    <option value="{{ $option }}" @selected((string) $currentVal === (string) $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        @elseif ($field['type'] === 'textarea')
                            <textarea
                                id="{{ $inputId }}"
                                name="{{ $inputName }}"
                                rows="3"
                                @required($field['required'] ?? false)
                                class="form-control {{ $err ? 'form-control-invalid' : '' }}"
                                @if ($err) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
                            >{{ $currentVal }}</textarea>
                        @else
                            <input
                                id="{{ $inputId }}"
                                name="{{ $inputName }}"
                                type="{{ $field['type'] }}"
                                @required($field['required'] ?? false)
                                value="{{ $currentVal }}"
                                class="form-control {{ $err ? 'form-control-invalid' : '' }}"
                                @if ($err) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
                            >
                        @endif
                    </x-form-field>
                @endforeach
            </div>
        </section>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                @if ($isEdit)
                    <button type="button"
                            onclick="if(confirm('Hapus draft RFI ini?')) document.getElementById('deleteDraftForm').submit();"
                            class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] border border-alert/30 px-5 text-[14px] font-semibold text-alert transition hover:bg-alert/10">
                        Hapus draft
                    </button>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('rfi.category', ['kategori' => $categoryCode]) }}"
                   class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] border border-field bg-white px-6 text-[14px] font-semibold text-ink transition hover:bg-paper">
                    Batal
                </a>
                @if ($hasDrafts)
                    <button type="submit"
                            name="action"
                            value="draft"
                            formnovalidate
                            class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] border border-brand bg-white px-6 text-[14px] font-semibold text-brand transition hover:bg-brand/10">
                        Simpan
                    </button>
                    <button type="submit"
                            name="action"
                            value="submit"
                            class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] bg-brand px-7 text-[14px] font-semibold text-white transition hover:bg-brand-dark">
                        Kirim ke QC
                    </button>
                @else
                    <button type="submit"
                            class="inline-flex min-h-[48px] items-center justify-center rounded-[10px] bg-brand px-7 text-[14px] font-semibold text-white transition hover:bg-brand-dark">
                        Kirim RFI
                    </button>
                @endif
            </div>
        </div>
    </form>

    @if ($isEdit)
        <form id="deleteDraftForm" method="POST" action="{{ route('rfi.destroy', ['kategori' => $categoryCode, 'rfi' => $rfi]) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endif

    <script>
        // Category F interaction
        (function() {
            const customerDisplay = document.getElementById('customer_display');
            const projectHiddenF = document.getElementById('project_select');

            // Build lookup map from server-rendered project data
            const projectMapF = {};
            @foreach ($projects as $p)
            projectMapF['{{ $p->id }}'] = @json($p->customer ?? '');
            @endforeach

            function updateCustomer() {
                if (!projectHiddenF || !customerDisplay) return;
                const cust = projectMapF[projectHiddenF.value] || '';
                customerDisplay.textContent = cust && cust.trim() ? cust : '—';
            }
            projectHiddenF?.addEventListener('change', updateCustomer);
            updateCustomer();
        })();

        const radioCards = document.querySelectorAll('.jenis-radio-card');
        const additionalContainer = document.getElementById('additional_input_container');
        const additionalInput = document.getElementById('manual');

        function updateRadioCards() {
            radioCards.forEach(card => {
                const input = card.querySelector('input[type="radio"]');
                const textSpan = card.querySelector('span');
                if (input && input.checked) {
                    card.classList.remove('border-[#8C978B]', 'bg-white');
                    card.classList.add('border-2', 'border-brand', 'bg-[#E9EFE5]');
                    textSpan?.classList.remove('font-medium');
                    textSpan?.classList.add('font-semibold');
                    if (input.value === 'Additional') {
                        additionalContainer?.classList.remove('hidden');
                        if (document.activeElement === input) {
                            additionalInput?.focus();
                        }
                    } else {
                        additionalContainer?.classList.add('hidden');
                    }
                } else {
                    card.classList.remove('border-2', 'border-brand', 'bg-[#E9EFE5]');
                    card.classList.add('border', 'border-[#8C978B]', 'bg-white');
                    textSpan?.classList.remove('font-semibold');
                    textSpan?.classList.add('font-medium');
                }
            });
        }

        radioCards.forEach(card => {
            const input = card.querySelector('input[type="radio"]');
            input?.addEventListener('change', updateRadioCards);
        });

        const updateConditionalFields = () => {
            document.querySelectorAll('[data-show-if-name]').forEach((container) => {
                const controller = document.querySelector(`[name="${container.dataset.showIfName}"]`);
                const isVisible = controller?.value === container.dataset.showIfValue;
                container.classList.toggle('hidden', !isVisible);

                const field = container.querySelector('select, input, textarea');
                if (field && field.dataset.requiredIfName) {
                    const requiredController = document.querySelector(`[name="${field.dataset.requiredIfName}"]`);
                    field.required = isVisible && requiredController?.value === field.dataset.requiredIfValue;
                }
            });
        };

        document.querySelectorAll('[data-show-if-name]').forEach((container) => {
            const controller = document.querySelector(`[name="${container.dataset.showIfName}"]`);
            controller?.addEventListener('change', updateConditionalFields);
        });
        updateConditionalFields();

        // Multi-select custom helper
        function checkTagsEmpty(inputId) {
            const tagsContainer = document.getElementById(inputId + '_tags');
            const emptyHint = document.getElementById(inputId + '_empty_hint');
            if (tagsContainer && emptyHint) {
                const hasTags = tagsContainer.querySelectorAll('input[type="hidden"]').length > 0;
                emptyHint.classList.toggle('hidden', hasTags);
            }
        }

        function initMultiSelect(inputId, fieldName) {
            const select = document.getElementById(inputId + '_select');
            const custom = document.getElementById(inputId + '_custom');
            const addBtn = document.getElementById(inputId + '_add_btn');
            const tagsContainer = document.getElementById(inputId + '_tags');

            if (!tagsContainer || !addBtn) return;

            function addItem(val) {
                val = (val || '').trim();
                if (!val) return;

                const existing = Array.from(tagsContainer.querySelectorAll('input[type="hidden"]')).map(el => el.value.toLowerCase());
                if (existing.includes(val.toLowerCase())) {
                    alert('"' + val + '" sudah ada di daftar.');
                    return;
                }

                const span = document.createElement('span');
                span.className = 'inline-flex items-center gap-1.5 rounded-full bg-[#E9EFE5] border border-brand/40 px-3 py-1 text-[13px] font-semibold text-[#1F5B2A]';
                span.innerHTML = `<span>${val}</span><input type="hidden" name="${fieldName}[]" value="${val}"><button type="button" class="text-[#1F5B2A] hover:text-alert font-bold ml-1 focus:outline-none" title="Hapus">&times;</button>`;
                span.querySelector('button').addEventListener('click', () => {
                    span.remove();
                    checkTagsEmpty(inputId);
                });
                tagsContainer.appendChild(span);

                checkTagsEmpty(inputId);

                if (select) select.value = '';
                if (custom) custom.value = '';
            }

            addBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const val = (custom.value || select.value || '').trim();
                if (val) {
                    addItem(val);
                } else {
                    custom.focus();
                }
            });

            select?.addEventListener('change', () => {
                if (select.value) {
                    addItem(select.value);
                }
            });

            custom?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (custom.value.trim()) {
                        addItem(custom.value.trim());
                    }
                }
            });
        }

        document.querySelectorAll('[id$="_wrapper"]').forEach(wrapper => {
            const id = wrapper.id.replace('_wrapper', '');
            const fieldName = id.replace('details_', 'details[').concat(']');
            initMultiSelect(id, fieldName);
        });

        // Vendor / Supplier autocomplete via API
        // [replaced by searchable-dropdown component — see sdd init below]

        // =========================================================
        // Searchable Dropdown (SDD) — shared controller
        // Handles: project_id (static list), details_vendor (api),
        //          details_po_no / details_bom_no (vsm-material)
        // =========================================================
        (function () {
            const supplierUrl = @json(route('suppliers.search'));
            const suratJalanUrl = @json(route('surat-jalan.search'));
            const materialsBaseUrl = @json(url('/projects'));

            // Static items for project dropdown
            @php
                $rfiProjectItemsData = $projects->map(fn($p) => [
                    'value' => (string) $p->id,
                    'label' => $p->project_code . ' — ' . $p->project_name,
                    'title' => $p->project_name,
                    'code' => $p->project_code,
                    'category' => 'PROJECT',
                    'metaLabel' => 'Customer',
                    'metaValue' => $p->customer ?: '—',
                ])->values();
            @endphp
            const projectItems = @json($rfiProjectItemsData);

            // VSM material cache keyed by projectId
            const vsmCache = {};
            let vsmLoading = false;

            function renderItems(listEl, items, query, emptyMsg, customVal = '') {
                const q = (query || '').trim().toLowerCase();
                const filtered = q ? items.filter(it => {
                    const l = (it.label || '').toLowerCase();
                    const t = (it.title || '').toLowerCase();
                    const c = (it.code || '').toLowerCase();
                    const m = (it.metaValue || '').toLowerCase();
                    return l.includes(q) || t.includes(q) || c.includes(q) || m.includes(q);
                }) : items;
                listEl.innerHTML = '';

                const trimmedCustom = (customVal || '').trim();
                const exactMatch = trimmedCustom && items.some(it => (it.value || '').toLowerCase() === trimmedCustom.toLowerCase() || (it.label || '').toLowerCase() === trimmedCustom.toLowerCase());
                if (trimmedCustom && !exactMatch) {
                    const customLi = document.createElement('li');
                    customLi.setAttribute('role', 'option');
                    customLi.setAttribute('data-value', trimmedCustom);
                    customLi.setAttribute('data-label', trimmedCustom);
                    customLi.innerHTML = `
                        <div class="group rounded-xl border border-dashed border-emerald-400 bg-emerald-50/40 p-2.5 hover:border-emerald-600 hover:bg-emerald-50/70 transition cursor-pointer">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider bg-emerald-100 text-emerald-800">
                                    MANUAL
                                </span>
                                <span class="text-[13px] font-bold text-emerald-900 group-hover:text-emerald-700 transition truncate">
                                    + Gunakan &ldquo;${escHtml(trimmedCustom)}&rdquo;
                                </span>
                            </div>
                            <div class="mt-1.5 flex items-center justify-between text-[11px]">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold text-emerald-700 bg-emerald-100/60 border border-emerald-200/60">
                                    Input Kustom
                                </span>
                                <span class="text-slate-500 font-medium">
                                    Ketik: <span class="font-bold text-[#1B6E41]">${escHtml(trimmedCustom)}</span>
                                </span>
                            </div>
                        </div>`;
                    listEl.appendChild(customLi);
                }

                if (!filtered.length && (!trimmedCustom || exactMatch)) {
                    listEl.innerHTML += `<li class="px-4 py-3 text-[13px] text-muted italic">${emptyMsg || 'Tidak ada hasil'}</li>`;
                    return;
                }

                filtered.forEach(item => {
                    const li = document.createElement('li');
                    li.setAttribute('role', 'option');
                    li.setAttribute('data-value', item.value);
                    li.setAttribute('data-label', item.label);

                    const category = item.category || item.badge || 'ITEM';
                    const title = item.title || item.label;
                    const code = item.code || item.subLabel || '';
                    const metaLabel = item.metaLabel || '';
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

            function escHtml(str) {
                return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            function openPanel(root, trigger) {
                const panel = root.querySelector('[role="listbox"]');
                if (!panel) return;
                panel.classList.remove('hidden');
                trigger.setAttribute('aria-expanded', 'true');
                // Rotate chevron
                const chevron = trigger.querySelector('svg');
                if (chevron) chevron.style.transform = 'rotate(180deg)';
                const searchInput = panel.querySelector('input[type="text"]');
                if (searchInput) {
                    searchInput.value = '';
                    setTimeout(() => searchInput.focus(), 30);
                }
            }

            function closePanel(root, trigger) {
                const panel = root.querySelector('[role="listbox"]');
                if (!panel) return;
                panel.classList.add('hidden');
                trigger.setAttribute('aria-expanded', 'false');
                const chevron = trigger.querySelector('svg');
                if (chevron) chevron.style.transform = '';
            }

            function selectItem(root, trigger, hiddenInput, displayEl, value, label) {
                hiddenInput.value = value;
                displayEl.textContent = label || value;
                displayEl.classList.remove('text-muted');
                displayEl.classList.add('text-ink');
                closePanel(root, trigger);
                // Dispatch change for downstream listeners (PO/BOM reload)
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function clearItem(root, trigger, hiddenInput, displayEl, placeholder) {
                hiddenInput.value = '';
                displayEl.textContent = displayEl.dataset.placeholder || placeholder;
                displayEl.classList.remove('text-ink');
                displayEl.classList.add('text-muted');
                closePanel(root, trigger);
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function initSDD(rootEl) {
                const id = rootEl.getAttribute('data-sdd-root');
                const type = rootEl.getAttribute('data-sdd-type') || rootEl.querySelector('[data-sdd-type]')?.getAttribute('data-sdd-type') || 'static';
                const trigger = rootEl.querySelector(`#${id}_trigger`);
                const panel = rootEl.querySelector(`#${id}_panel`);
                const searchInput = rootEl.querySelector(`#${id}_search`);
                const listEl = rootEl.querySelector(`#${id}_list`);
                const hiddenInput = rootEl.querySelector(`#${id}`);
                const displayEl = rootEl.querySelector(`#${id}_display`);
                const badgeEl = rootEl.querySelector(`#${id}_badge`);
                const clearBtn = rootEl.querySelector('[data-sdd-clear]');
                const placeholder = displayEl?.dataset?.placeholder || trigger?.querySelector('span')?.textContent?.trim() || '';

                if (!trigger || !panel || !listEl || !hiddenInput) return;

                let items = [];

                // ---- Load data for a given type ----
                async function loadItems(query = '') {
                    if (type === 'static') {
                        // Project list from server-rendered PHP
                        items = projectItems;
                        renderItems(listEl, items, query, 'Tidak ada project');
                        if (badgeEl) {
                            badgeEl.textContent = items.length + ' Project';
                            badgeEl.classList.remove('hidden');
                        }

                    } else if (type === 'api-vendor') {
                        listEl.innerHTML = '<li class="px-4 py-3 text-[13px] text-muted italic">Memuat supplier...</li>';
                        try {
                            const res = await fetch(`${supplierUrl}?search=${encodeURIComponent(query || '')}`, {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            const data = res.ok ? await res.json() : [];
                            items = Array.isArray(data) ? data.map(s => {
                                const name = typeof s === 'string' ? s : (s.name || s.supplier || '');
                                return {
                                    value: name,
                                    label: name,
                                    title: name,
                                    code: 'API Material In',
                                    category: 'SUPPLIER',
                                    metaLabel: 'Status',
                                    metaValue: 'Terverifikasi',
                                };
                            }) : [];
                            renderItems(listEl, items, '', 'Tidak ada supplier', query);
                            if (badgeEl) {
                                badgeEl.textContent = items.length + ' Supplier';
                                badgeEl.classList.remove('hidden');
                            }
                        } catch {
                            listEl.innerHTML = '<li class="px-4 py-3 text-[13px] text-muted italic">Gagal memuat data supplier</li>';
                        }

                    } else if (type === 'api-surat-jalan') {
                        listEl.innerHTML = '<li class="px-4 py-3 text-[13px] text-muted italic">Memuat Surat Jalan...</li>';
                        try {
                            const res = await fetch(`${suratJalanUrl}?search=${encodeURIComponent(query || '')}`, {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            const data = res.ok ? await res.json() : [];
                            items = Array.isArray(data) ? data.map(item => {
                                const refNo = typeof item === 'string' ? item : item.reference_number;
                                const supplier = typeof item === 'object' && item?.supplier ? item.supplier : '';
                                const po = typeof item === 'object' && item?.po_number ? item.po_number : '';
                                const date = typeof item === 'object' && item?.date ? item.date : '';
                                return {
                                    value: refNo,
                                    label: refNo,
                                    title: refNo,
                                    code: supplier || 'QC Material In',
                                    category: 'SURAT JALAN',
                                    metaLabel: po ? 'PO' : (date ? 'Tgl' : 'QC'),
                                    metaValue: po || date || 'Verified',
                                    extra: item,
                                };
                            }) : [];
                            renderItems(listEl, items, '', 'Tidak ada Surat Jalan ditemukan', query);
                            if (badgeEl) {
                                badgeEl.textContent = items.length + ' Surat Jalan';
                                badgeEl.classList.remove('hidden');
                            }
                        } catch {
                            listEl.innerHTML = '<li class="px-4 py-3 text-[13px] text-muted italic">Gagal memuat data Surat Jalan</li>';
                        }

                    } else if (type === 'vsm-material') {
                        // Will be populated when project changes — see project listener below
                        renderItems(listEl, items, query, 'Pilih project terlebih dahulu', query);
                    }
                }

                // ---- Trigger open/close ----
                trigger.addEventListener('click', () => {
                    const isOpen = panel && !panel.classList.contains('hidden');
                    if (isOpen) {
                        closePanel(rootEl, trigger);
                        return;
                    }
                    // Close all other panels
                    document.querySelectorAll('[data-sdd-root]').forEach(r => {
                        if (r !== rootEl) {
                            const t = r.querySelector(`#${r.getAttribute('data-sdd-root')}_trigger`);
                            if (t) closePanel(r, t);
                        }
                    });
                    openPanel(rootEl, trigger);
                    if (type === 'api-vendor' || type === 'api-surat-jalan') {
                        loadItems(searchInput?.value || '');
                    } else if (type === 'static') {
                        if (!items.length) {
                            loadItems('');
                        } else {
                            renderItems(listEl, items, searchInput?.value || '', 'Tidak ada project');
                        }
                    } else if (type === 'vsm-material') {
                        renderItems(listEl, items, searchInput?.value || '', 'Pilih project terlebih dahulu', searchInput?.value || '');
                    }
                });

                // ---- Search ----
                let debounce = null;
                searchInput?.addEventListener('input', () => {
                    clearTimeout(debounce);
                    const q = searchInput.value;
                    if (type === 'api-vendor' || type === 'api-surat-jalan') {
                        debounce = setTimeout(() => loadItems(q), 300);
                    } else {
                        renderItems(listEl, items, q, 'Tidak ada hasil', q);
                    }
                });

                searchInput?.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const firstOption = listEl.querySelector('li[data-value]');
                        if (firstOption) {
                            selectItem(rootEl, trigger, hiddenInput, displayEl, firstOption.dataset.value, firstOption.dataset.label);
                        }
                    }
                });

                // ---- Select item from list ----
                listEl.addEventListener('click', (e) => {
                    const li = e.target.closest('li[data-value]');
                    if (!li) return;
                    selectItem(rootEl, trigger, hiddenInput, displayEl, li.dataset.value, li.dataset.label);

                    // If surat jalan has supplier, auto-fill vendor if vendor is empty
                    if (type === 'api-surat-jalan') {
                        const matched = items.find(it => it.value === li.dataset.value);
                        const supplierName = matched?.extra?.supplier;
                        if (supplierName) {
                            const vendorRoot = document.querySelector('[data-sdd-root="details_vendor"]');
                            if (vendorRoot) {
                                const vendorHid = vendorRoot.querySelector('#details_vendor');
                                const vendorDisp = vendorRoot.querySelector('#details_vendor_display');
                                const vendorTrig = vendorRoot.querySelector('#details_vendor_trigger');
                                if (vendorHid && vendorDisp && !vendorHid.value) {
                                    selectItem(vendorRoot, vendorTrig, vendorHid, vendorDisp, supplierName, supplierName);
                                }
                            }
                        }
                    }
                });

                // ---- Clear ----
                clearBtn?.addEventListener('click', () => clearItem(rootEl, trigger, hiddenInput, displayEl, placeholder));

                // ---- Close on outside click ----
                document.addEventListener('click', (e) => {
                    if (!rootEl.contains(e.target)) closePanel(rootEl, trigger);
                }, true);

                // ---- Keyboard close ----
                panel?.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') { closePanel(rootEl, trigger); trigger.focus(); }
                });

                // ---- Expose update API for external callers (VSM PO/BOM) ----
                rootEl._sddSetItems = function(newItems, countSuffix = '') {
                    items = newItems;
                    if (!panel.classList.contains('hidden')) {
                        renderItems(listEl, items, searchInput?.value || '', 'Tidak ada hasil', searchInput?.value || '');
                    }
                    if (badgeEl) {
                        badgeEl.textContent = items.length + (countSuffix ? ' ' + countSuffix : '');
                        badgeEl.classList.remove('hidden');
                    }
                };

                // Initial static load for project
                if (type === 'static') loadItems('');
            }

            // Init all SDD roots
            document.querySelectorAll('[data-sdd-root]').forEach(initSDD);

            // ---- Project → PO/BOM reload ----
            const projectHidden = document.getElementById('project_id');
            const poRoot   = document.querySelector('[data-sdd-root="details_po_no"]');
            const bomRoot  = document.querySelector('[data-sdd-root="details_bom_no"]');

            async function reloadVsmMaterials(projectId) {
                // Clear both
                [poRoot, bomRoot].forEach(r => {
                    if (!r) return;
                    // Clear selected value
                    const hid = r.querySelector(`#${r.getAttribute('data-sdd-root')}`);
                    const disp = r.querySelector(`#${r.getAttribute('data-sdd-root')}_display`);
                    if (hid && disp) {
                        hid.value = '';
                        const lbl = r.querySelector(`#${r.getAttribute('data-sdd-root')}_trigger span`)?.dataset?.placeholder || '';
                        disp.textContent = lbl || 'Pilih...';
                        disp.classList.add('text-muted');
                        disp.classList.remove('text-ink');
                    }
                    if (r._sddSetItems) r._sddSetItems([], '');
                    const listEl = r.querySelector(`#${r.getAttribute('data-sdd-root')}_list`);
                    if (listEl) listEl.innerHTML = '<li class="px-4 py-3 text-[13px] text-muted italic">Memuat...</li>';
                });

                if (!projectId) {
                    [poRoot, bomRoot].forEach(r => {
                        if (!r) return;
                        const listEl = r.querySelector(`#${r.getAttribute('data-sdd-root')}_list`);
                        if (listEl) listEl.innerHTML = '<li class="px-4 py-3 text-[13px] text-muted italic">Pilih project terlebih dahulu</li>';
                        if (r._sddSetItems) r._sddSetItems([], '');
                    });
                    return;
                }

                try {
                    const cached = vsmCache[projectId];
                    const data = cached || await fetch(`${materialsBaseUrl}/${encodeURIComponent(projectId)}/materials`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(r => r.ok ? r.json() : null);

                    if (data) vsmCache[projectId] = data;

                    const currentProj = projectItems.find(p => p.value === String(projectId));
                    const pCode = currentProj?.code || 'VSM';

                    if (poRoot && poRoot._sddSetItems) {
                        const poItems = (data?.po_numbers || []).map(v => ({
                            value: v,
                            label: v,
                            title: v,
                            code: pCode,
                            category: 'PO NUMBER',
                            metaLabel: 'Tipe',
                            metaValue: 'Material PO',
                        }));
                        poRoot._sddSetItems(poItems, 'PO');
                        // Auto-fill if single result and field is empty
                        const poHid = poRoot.querySelector('#details_po_no');
                        const poDisp = poRoot.querySelector('#details_po_no_display');
                        if (poHid && poDisp && !poHid.value && poItems.length === 1) {
                            poHid.value = poItems[0].value;
                            poDisp.textContent = poItems[0].label;
                            poDisp.classList.remove('text-muted');
                            poDisp.classList.add('text-ink');
                        }
                    }

                    if (bomRoot && bomRoot._sddSetItems) {
                        const bomItems = (data?.bom_numbers || []).map(v => ({
                            value: v,
                            label: v,
                            title: v,
                            code: pCode,
                            category: 'BOM NO',
                            metaLabel: 'Tipe',
                            metaValue: 'Bill of Material',
                        }));
                        bomRoot._sddSetItems(bomItems, 'BOM');
                        const bomHid = bomRoot.querySelector('#details_bom_no');
                        const bomDisp = bomRoot.querySelector('#details_bom_no_display');
                        if (bomHid && bomDisp && !bomHid.value && bomItems.length === 1) {
                            bomHid.value = bomItems[0].value;
                            bomDisp.textContent = bomItems[0].label;
                            bomDisp.classList.remove('text-muted');
                            bomDisp.classList.add('text-ink');
                        }
                    }
                } catch {
                    [poRoot, bomRoot].forEach(r => {
                        if (!r) return;
                        const listEl = r.querySelector(`#${r.getAttribute('data-sdd-root')}_list`);
                        if (listEl) listEl.innerHTML = '<li class="px-4 py-3 text-[13px] text-muted italic">Gagal memuat data VSM</li>';
                    });
                }
            }

            if (projectHidden) {
                projectHidden.addEventListener('change', () => reloadVsmMaterials(projectHidden.value));
                if (projectHidden.value) reloadVsmMaterials(projectHidden.value);
            }
        })();
    </script>
@endsection
