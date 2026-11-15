@php
    $isEdit = isset($rfi) && $rfi->exists;
    $hasDrafts = ! empty($category['drafts']);
    $pageTitle = $isEdit
        ? "Ubah Draft RFI - Kategori {$categoryCode}: {$category['label']}"
        : "Buat RFI - Kategori {$categoryCode}: {$category['label']}";
@endphp

@extends('layouts.module', ['active' => 'rfi', 'pageTitle' => $pageTitle])

@section('content')
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
                    <x-form-field
                        :id="$fieldName"
                        :label="$field['label']"
                        :required="(bool) $field['required']"
                        :error="$err"
                        :span="$span"
                    >
                        @if ($field['type'] === 'project')
                            <select
                                id="{{ $fieldName }}"
                                name="{{ $fieldName }}"
                                required
                                class="form-control {{ $err ? 'form-control-invalid' : '' }}"
                                @if ($err) aria-invalid="true" aria-describedby="{{ $fieldName }}-error" @endif
                            >
                                <option value="">Pilih project</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected((string) $currentVal === (string) $project->id)>
                                        {{ $project->project_code }} — {{ $project->project_name }}
                                    </option>
                                @endforeach
                            </select>
                        @elseif ($field['type'] === 'textarea')
                            <textarea
                                id="{{ $fieldName }}"
                                name="{{ $fieldName }}"
                                rows="3"
                                class="form-control {{ $err ? 'form-control-invalid' : '' }}"
                                @if ($err) aria-invalid="true" aria-describedby="{{ $fieldName }}-error" @endif
                            >{{ $currentVal }}</textarea>
                        @else
                            <input
                                id="{{ $fieldName }}"
                                name="{{ $fieldName }}"
                                type="{{ $field['type'] }}"
                                @required($field['required'])
                                value="{{ $currentVal }}"
                                class="form-control {{ $err ? 'form-control-invalid' : '' }}"
                                @if ($err) aria-invalid="true" aria-describedby="{{ $fieldName }}-error" @endif
                            >
                        @endif
                    </x-form-field>
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
                            @if (($field['type'] ?? 'text') === 'select')
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
                            @elseif (($field['type'] ?? 'text') === 'multi_select')
                                @php
                                    $selectedItems = old($errorKey, $isEdit ? data_get($rfi->details, $field['name'], []) : []);
                                    if (is_string($selectedItems)) {
                                        $selectedItems = array_values(array_filter(array_map('trim', explode(',', $selectedItems))));
                                    } elseif (! is_array($selectedItems)) {
                                        $selectedItems = [];
                                    }
                                @endphp
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
                            @elseif (($field['type'] ?? 'text') === 'textarea')
                                <textarea
                                    id="{{ $inputId }}"
                                    name="{{ $fieldName }}"
                                    rows="3"
                                    @required($field['required'] ?? false)
                                    class="form-control {{ $err ? 'form-control-invalid' : '' }}"
                                    @if ($err) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
                                >{{ $currentVal }}</textarea>
                            @else
                                <input
                                    id="{{ $inputId }}"
                                    name="{{ $fieldName }}"
                                    type="{{ $field['type'] ?? 'text' }}"
                                    @required($field['required'] ?? false)
                                    value="{{ $currentVal }}"
                                    class="form-control {{ $err ? 'form-control-invalid' : '' }}"
                                    @if ($err) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
                                >
                            @endif
                        </x-form-field>
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

    <script>
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
    </script>
@endsection
