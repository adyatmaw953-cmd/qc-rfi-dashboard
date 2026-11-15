<?php

namespace App\Http\Requests;

use App\Enums\RfiResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewRfiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'qc_qa'], true);
    }

    public function rules(): array
    {
        return [
            'result' => ['required', Rule::enum(RfiResult::class)],
            'qc_note' => [
                Rule::requiredIf(fn (): bool => $this->input('result') !== RfiResult::Accepted->value),
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
