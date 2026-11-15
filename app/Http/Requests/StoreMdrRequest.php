<?php

namespace App\Http\Requests;

use App\Enums\MdrStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMdrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, config('mudalaya.modules.mdr.roles', []), true);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('unit_no')) {
            $sn = $this->input('owner_serial_no') ?: ($this->input('manufacture_serial_no') ?: 'Unit 1');
            $this->merge(['unit_no' => $sn]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'project_id' => [
                'required',
                'integer',
                'exists:projects,id',
            ],
            'owner_serial_no' => ['nullable', 'string', 'max:100'],
            'manufacture_serial_no' => ['nullable', 'string', 'max:100'],
            'unit_no' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('mdrs', 'unit_no')->where(
                    fn ($query) => $query->where('project_id', $this->input('project_id'))
                ),
            ],
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
