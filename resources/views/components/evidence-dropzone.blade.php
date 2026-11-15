@props([
    'findingId' => '',
    'errors' => null,
])

<div class="evidence-dropzone-wrapper space-y-2" data-dropzone-for="{{ $findingId }}">
    <label
        for="evidence-input-{{ $findingId }}"
        class="evidence-dropzone flex min-h-[110px] cursor-pointer flex-col items-center justify-center rounded-[14px] border-2 border-dashed border-[#8C978B] bg-white p-[22px] text-center transition hover:border-brand hover:bg-[#FBFAF6]"
    >
        <svg class="h-6 w-6 text-muted mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
        <span class="text-[13px] font-semibold text-ink">Klik untuk memilih file atau seret file ke sini</span>
        <span class="mt-1 text-[11px] text-muted">1 sampai 5 file · JPG, PNG, atau PDF · maksimal 10 MB per file</span>
    </label>

    <input
        type="file"
        id="evidence-input-{{ $findingId }}"
        name="evidence[]"
        multiple
        accept=".jpg,.jpeg,.png,.pdf"
        required
        class="evidence-file-input sr-only"
    >

    {{-- File preview container for JS --}}
    <div class="evidence-file-list flex flex-wrap gap-2 pt-1" aria-live="polite"></div>

    @error('evidence')
        <p class="text-[12px] text-[#8F3F08]">{{ $message }}</p>
    @enderror
    @error('evidence.*')
        <p class="text-[12px] text-[#8F3F08]">{{ $message }}</p>
    @enderror
</div>

