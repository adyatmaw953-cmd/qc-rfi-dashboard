@props([
    'findingId' => '',
    'errors' => null,
])

@php
    $parseIniToBytes = function (?string $val): int {
        if (! $val) {
            return 0;
        }
        $val = trim($val);
        $lastChar = strtolower(substr($val, -1));
        $number = (float) $val;

        return match ($lastChar) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $val,
        };
    };

    $uploadMaxRaw = ini_get('upload_max_filesize') ?: '10M';
    $postMaxRaw = ini_get('post_max_size') ?: '10M';

    $uploadMaxBytes = $parseIniToBytes($uploadMaxRaw);
    $postMaxBytes = $parseIniToBytes($postMaxRaw);

    $tenMbBytes = 10 * 1024 * 1024;
    $effectiveFileLimitBytes = ($uploadMaxBytes > 0) ? min($tenMbBytes, $uploadMaxBytes) : $tenMbBytes;
@endphp

<div class="evidence-dropzone-wrapper space-y-2"
     data-dropzone-for="{{ $findingId }}"
     data-upload-max-filesize="{{ $uploadMaxBytes }}"
     data-upload-max-raw="{{ $uploadMaxRaw }}"
     data-post-max-size="{{ $postMaxBytes }}"
     data-post-max-raw="{{ $postMaxRaw }}"
     data-effective-file-limit="{{ $effectiveFileLimitBytes }}">
    <label
        for="evidence-input-{{ $findingId }}"
        class="evidence-dropzone flex min-h-[110px] cursor-pointer flex-col items-center justify-center rounded-[14px] border-2 border-dashed border-[#8C978B] bg-white p-[22px] text-center transition hover:border-brand hover:bg-[#FBFAF6]"
    >
        <svg class="h-6 w-6 text-muted mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
        <span class="text-[13px] font-semibold text-ink">Klik untuk memilih file atau seret file ke sini</span>
        <span class="mt-1 text-[11px] text-muted">1 sampai 5 file · JPG, JPEG, PNG, WEBP, atau PDF · maksimal 10 MB per file</span>
    </label>

    <input
        type="file"
        id="evidence-input-{{ $findingId }}"
        name="evidence[]"
        multiple
        accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
        required
        class="evidence-file-input sr-only"
    >

    {{-- Total size display --}}
    <div class="evidence-total-size hidden text-[12px] font-medium text-muted">
        Total ukuran: <span class="evidence-total-size-value font-mono font-semibold text-ink">0 KB</span>
    </div>

    {{-- File preview container for JS --}}
    <div class="evidence-file-list flex flex-wrap gap-2 pt-1" aria-live="polite"></div>

    {{-- Rejection messages / client validation messages container --}}
    <div class="evidence-error-list space-y-1" aria-live="polite"></div>

    @error('evidence')
        <p class="text-[12px] font-medium text-[#8F3F08]">{{ $message }}</p>
    @enderror
    @error('evidence.*')
        <p class="text-[12px] font-medium text-[#8F3F08]">{{ $message }}</p>
    @enderror
</div>
