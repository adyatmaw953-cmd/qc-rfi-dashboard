{{--
    Searchable dropdown panel (styled like "Cari Barang Katalog").

    Props:
      id          - unique id for the hidden input + panel (required)
      name        - form input name (required)
      label       - label text shown in the trigger button (required)
      value       - current/selected value (the actual submitted value)
      displayText - text shown in the trigger when a value is selected (defaults to value)
      placeholder - search placeholder inside the panel
      headerTitle - title shown at top of panel (defaults to "Cari {label}")
      required    - whether the field is required
      badgeCount  - optional badge text shown top-right of panel (e.g. "23 Project")
      error       - error message string
      disabled    - disables the trigger
      sddType     - data source type ('static', 'api-vendor', 'api-surat-jalan', 'vsm-material')
--}}
@props([
    'id',
    'name',
    'label',
    'value' => '',
    'displayText' => null,
    'placeholder' => 'Ketik nama, kode, atau kata kunci...',
    'headerTitle' => null,
    'required' => false,
    'badgeCount' => null,
    'error' => null,
    'disabled' => false,
    'sddType' => 'static',
])

@php
    $triggerId  = $id . '_trigger';
    $panelId    = $id . '_panel';
    $searchId   = $id . '_search';
    $listId     = $id . '_list';
    $display    = $displayText ?? $value;
    $hasValue   = $display !== '' && $display !== null;
    $actualType = $sddType ?: ($attributes->get('data-sdd-type') ?? 'static');

    // Clean title for header (e.g. "Pilih project" -> "Cari Project", "Pilih vendor" -> "Cari Vendor")
    if ($headerTitle) {
        $headerText = $headerTitle;
    } else {
        $cleanLabel = trim($label);
        if (str_starts_with(strtolower($cleanLabel), 'pilih ')) {
            $headerText = 'Cari ' . ucwords(substr($cleanLabel, 6));
        } else {
            $headerText = 'Cari ' . ucwords($cleanLabel);
        }
    }
@endphp

<div {{ $attributes->merge(['class' => 'relative']) }} data-sdd-root="{{ $id }}" data-sdd-type="{{ $actualType }}">

    {{-- Hidden real input submitted with form --}}
    <input
        type="hidden"
        id="{{ $id }}"
        name="{{ $name }}"
        value="{{ $value }}"
        @if($required) data-sdd-required="1" @endif
    >

    {{-- Trigger button --}}
    <button
        type="button"
        id="{{ $triggerId }}"
        aria-haspopup="listbox"
        aria-expanded="false"
        aria-controls="{{ $panelId }}"
        @if($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        @disabled($disabled)
        class="form-control flex w-full items-center justify-between gap-2 text-left {{ $error ? 'form-control-invalid' : '' }} {{ $disabled ? 'opacity-60 pointer-events-none' : '' }}"
    >
        <span id="{{ $id }}_display" data-placeholder="{{ $label }}" class="truncate {{ $hasValue ? 'text-ink' : 'text-muted' }}">
            {{ $hasValue ? $display : $label }}
        </span>
        <svg class="h-4 w-4 shrink-0 text-muted transition-transform duration-150" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m6 9 6 6 6-6"/>
        </svg>
    </button>

    @if($error)
        <p id="{{ $id }}-error" class="mt-1.5 text-[13px] font-medium text-[#8F3F08]" role="alert">{{ $error }}</p>
    @endif

    {{-- Dropdown panel styled as Catalog Box --}}
    <div
        id="{{ $panelId }}"
        role="listbox"
        aria-label="{{ $label }}"
        class="absolute left-0 top-full z-50 mt-1.5 hidden w-full min-w-[300px] sm:min-w-[340px] rounded-2xl border border-line bg-white shadow-2xl"
        style="max-height: 420px;"
    >
        {{-- Panel header --}}
        <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
            <div class="flex items-center gap-2 truncate">
                <svg class="h-4 w-4 shrink-0 text-[#1B6E41]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m16.5 9.4-9-5.19M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                    <line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
                <span class="text-[14px] font-bold text-ink truncate">{{ $headerText }}</span>
            </div>
            <span id="{{ $id }}_badge" class="shrink-0 rounded-full bg-[#EBF5EE] px-2.5 py-0.5 text-[11px] font-bold text-[#1B6E41] {{ $badgeCount ? '' : 'hidden' }}">{{ $badgeCount }}</span>
        </div>

        {{-- Search input --}}
        <div class="border-b border-line px-3 py-2.5">
            <div class="flex items-center gap-2 rounded-xl border border-line bg-paper px-3 focus-within:border-[#1B6E41] focus-within:ring-1 focus-within:ring-[#1B6E41] transition">
                <svg class="h-4 w-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input
                    type="text"
                    id="{{ $searchId }}"
                    placeholder="{{ $placeholder }}"
                    autocomplete="off"
                    class="flex-1 bg-transparent py-2 text-[13px] text-ink placeholder:text-muted focus:outline-none"
                >
            </div>
        </div>

        {{-- List --}}
        <ul
            id="{{ $listId }}"
            class="overflow-y-auto overscroll-contain p-2.5 space-y-2"
            style="max-height: 280px;"
        >
            {{-- Items injected by JS --}}
            <li class="px-4 py-3 text-[13px] text-muted italic" data-sdd-empty>Memuat...</li>
        </ul>

        {{-- Clear selection --}}
        @if(!$required)
        <div class="border-t border-line px-4 py-2">
            <button type="button" data-sdd-clear class="text-[12px] text-muted hover:text-alert transition">Hapus pilihan</button>
        </div>
        @endif
    </div>
</div>

{{-- Per-instance init is handled by the caller views (rfi-create, mdr-checklist-create) --}}
