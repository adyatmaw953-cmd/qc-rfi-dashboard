<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyFindingSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'qc_qa'], true);
    }

    public function rules(): array
    {
        return [
            'result' => ['required', Rule::in(['ok', 'not_ok'])],
            'verification_note' => [
                Rule::requiredIf(fn (): bool => $this->input('result') === 'not_ok'),
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
