<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNcrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'qc_qa'], true);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nc_cost')) {
            $raw = (string) $this->input('nc_cost');
            $clean = preg_replace('/[^\d]/', '', $raw);
            $this->merge([
                'nc_cost' => $clean === '' ? 0 : (int) $clean,
            ]);
        }

        if ($this->input('project_code') === '') {
            $this->merge(['project_code' => null]);
        }

        if ($this->has('root_cause_category')) {
            $rcc = $this->input('root_cause_category');
            if (is_string($rcc)) {
                $rcc = array_values(array_filter(array_map('trim', explode(',', $rcc))));
                $this->merge(['root_cause_category' => $rcc]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isProject = $this->input('type') === 'Project';
        $isInternal = $this->input('type') === 'Internal';
        $status = $this->input('status');
        $hasPreventiveAction = filled($this->input('preventive_action'));

        return [
            'finding_date' => ['required', 'date'],
            'department' => ['required', 'string', Rule::in(config('ncr.departments', []))],
            'related_departments' => ['nullable', 'array'],
            'related_departments.*' => ['string', Rule::in(config('ncr.related_departments', []))],
            'area_finding' => ['required', 'string', 'max:255'],
            'level' => ['required', 'string', Rule::in(config('ncr.levels', []))],
            'type' => ['required', 'string', Rule::in(config('ncr.types', []))],
            'project_code' => [
                'nullable',
                Rule::requiredIf($isProject),
                Rule::prohibitedIf($isInternal),
                Rule::when($isProject, ['exists:projects,project_code']),
            ],
            'customer' => ['nullable', 'string', 'max:255'],
            'responsible_pic' => ['required', 'string', 'max:255'],
            'problem_description' => ['required', 'string'],
            'root_cause' => ['required', 'string'],
            'root_cause_category' => ['required', 'array', 'min:1'],
            'root_cause_category.*' => ['string', Rule::in(config('ncr.root_cause_categories', []))],
            'corrective_action' => ['nullable', 'string'],
            'pic_ca' => ['nullable', 'string', 'max:255'],
            'ca_closed_date' => [
                'nullable',
                'date',
                'after_or_equal:finding_date',
                Rule::requiredIf(in_array($status, ['CA Closed', 'Closed'], true)),
            ],
            'preventive_action' => ['nullable', 'string'],
            'pic_pa' => ['nullable', 'string', 'max:255'],
            'pa_closed_date' => [
                'nullable',
                'date',
                'after_or_equal:finding_date',
                Rule::requiredIf($status === 'Closed' && $hasPreventiveAction),
            ],
            'status' => ['required', 'string', Rule::in(config('ncr.statuses', []))],
            'nc_cost' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'root_cause_category.required' => 'Pilih minimal satu kategori akar masalah.',
            'root_cause_category.min' => 'Pilih minimal satu kategori akar masalah.',
            'project_code.required' => 'Project wajib dipilih jika jenis NCR adalah Project.',
            'project_code.prohibited' => 'Project harus dikosongkan jika jenis NCR adalah Internal.',
            'project_code.exists' => 'Project yang dipilih tidak valid.',
            'ca_closed_date.after_or_equal' => 'Tanggal CA closed tidak boleh lebih awal dari tanggal temuan.',
            'ca_closed_date.required' => 'Tanggal CA closed wajib diisi untuk status CA Closed atau Closed.',
            'pa_closed_date.after_or_equal' => 'Tanggal PA closed tidak boleh lebih awal dari tanggal temuan.',
            'pa_closed_date.required' => 'Tanggal PA closed wajib diisi jika status Closed dan preventive action terisi.',
        ];
    }
}
