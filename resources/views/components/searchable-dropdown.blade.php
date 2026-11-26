{{--
    Searchable dropdown panel (like "Cari Barang Katalog").

    Props:
      id          - unique id for the hidden input + panel (required)
      name        - form input name (required)
      label       - label text shown in the trigger button (required)
      value       - current/selected value (the actual submitted value)
      displayText - text shown in the trigger when a value is selected (defaults to value)
      placeholder - search placeholder inside the panel
      required    - whether the field is required
      badgeCount  - optional badge text shown top-right of panel (e.g. "23 Project")
      error       - error message string
      disabled    - disables the trigger
--}}
@props([
    'id',
    'name',
    'label',
    'value' => '',
    'displayText' => null,
    'placeholder' => 'Cari...',
    'required' => false,
    'badgeCount' => null,
    'error' => null,
    'disabled' => false,
])

@php
    $triggerId  = $id . '_trigger';
    $panelId    = $id . '_panel';
    $searchId   = $id . '_search';
    $listId     = $id . '_list';
    $display    = $displayText ?? $value;
    $hasValue   = $display !== '' && $display !== null;
@endphp

<div class="relative" data-sdd-root="{{ $id }}">

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
        <span id="{{ $id }}_display" class="truncate {{ $hasValue ? 'text-ink' : 'text-muted' }}">
            {{ $hasValue ? $display : $label }}
        </span>
        <svg class="h-4 w-4 shrink-0 text-muted transition-transform duration-150" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m6 9 6 6 6-6"/>
        </svg>
    </button>

    @if($error)
        <p id="{{ $id }}-error" class="mt-1.5 text-[13px] font-medium text-[#8F3F08]" role="alert">{{ $error }}</p>
    @endif

    {{-- Dropdown panel --}}
    <div
        id="{{ $panelId }}"
        role="listbox"
        aria-label="{{ $label }}"
        class="absolute left-0 top-full z-50 mt-1.5 hidden w-full min-w-[280px] rounded-2xl border border-line bg-white shadow-2xl"
        style="max-height: 380px;"
    >
        {{-- Panel header --}}
        <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
            <span class="text-[13px] font-semibold text-ink truncate">{{ $label }}</span>
            @if($badgeCount)
                <span id="{{ $id }}_badge" class="shrink-0 rounded-full bg-brand/10 px-2 py-0.5 text-[12px] font-semibold text-brand">{{ $badgeCount }}</span>
            @endif
        </div>

        {{-- Search input --}}
        <div class="border-b border-line px-3 py-2">
            <div class="flex items-center gap-2 rounded-xl border border-line bg-paper px-3">
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
            class="overflow-y-auto overscroll-contain"
            style="max-height: 260px;"
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

{{-- Per-instance init is handled by the shared sdd-init.js (loaded once in rfi-create) --}}
