<?php

namespace App\Http\Requests;

use App\Enums\MdrStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMdrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, config('mudalaya.modules.mdr.roles', []), true);
    }

    public function rules(): array
    {
        $rules = [
            'owner_serial_no' => ['nullable', 'string', 'max:100'],
            'manufacture_serial_no' => ['nullable', 'string', 'max:100'],
            'statuses' => ['required', 'array'],
            'custom_reports' => ['nullable', 'array'],
            'custom_reports.*.name' => ['required', 'string', 'max:150'],
            'custom_reports.*.status' => ['required', Rule::enum(MdrStatus::class)],
        ];

        foreach (array_keys(config('mudalaya.mdr_reports', [])) as $reportKey) {
            $rules["statuses.{$reportKey}"] = ['required', Rule::enum(MdrStatus::class)];
        }

        return $rules;
    }
}
