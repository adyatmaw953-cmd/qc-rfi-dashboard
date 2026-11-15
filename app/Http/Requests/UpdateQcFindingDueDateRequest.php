<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQcFindingDueDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'qc_qa'], true);
    }

    public function rules(): array
    {
        return [
            'due_date' => ['nullable', 'date'],
        ];
    }
}
