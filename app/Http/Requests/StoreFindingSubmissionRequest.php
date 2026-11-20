<?php

namespace App\Http\Requests;

use App\Models\QcFinding;
use Illuminate\Foundation\Http\FormRequest;

class StoreFindingSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $finding = $this->route('finding');
        $role = $this->user()?->role;

        if (! $finding instanceof QcFinding) {
            return false;
        }

        if ($role === 'admin') {
            return true;
        }

        $finding->loadMissing('rfi');
        $category = $finding->rfi?->category;
        $submissionRoles = config("mudalaya.findings.submission_roles.{$category}", []);

        return in_array($role, $submissionRoles, true);
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:5000'],
            'evidence' => ['required', 'array', 'min:1', 'max:5'],
            'evidence.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $uploadMax = ini_get('upload_max_filesize') ?: '10M';

        return [
            'note.required' => 'Catatan pemenuhan wajib diisi.',
            'evidence.required' => 'Bukti foto atau dokumen perbaikan wajib diunggah.',
            'evidence.min' => 'Unggah minimal 1 file bukti perbaikan.',
            'evidence.max' => 'Maksimal 5 file bukti yang dapat diunggah.',
            'evidence.*.required' => 'File bukti perbaikan wajib diunggah.',
            'evidence.*.file' => 'Bukti harus berupa file yang valid.',
            'evidence.*.mimes' => 'Format file tidak didukung. Gunakan format JPG, JPEG, PNG, WEBP, atau PDF.',
            'evidence.*.max' => 'Ukuran file evidence maksimal 10 MB per file.',
            'evidence.*.uploaded' => "Foto gagal diunggah. Kemungkinan ukurannya melebihi batas server ({$uploadMax}). Kecilkan ukuran foto atau hubungi admin.",
        ];
    }
}
