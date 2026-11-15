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
            'evidence.*' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'evidence.*.max' => 'Ukuran file evidence maksimal 10 MB per file.',
        ];
    }
}
