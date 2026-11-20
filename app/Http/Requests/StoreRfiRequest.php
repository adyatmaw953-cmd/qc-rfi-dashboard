<?php

namespace App\Http\Requests;

use App\Models\Rfi;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRfiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = config('mudalaya.rfi_categories.'.$this->route('kategori'));
        $role = $this->user()?->role;

        if ($category === null || ($role !== 'admin' && ! in_array($role, $category['submit_roles'], true))) {
            return false;
        }

        $rfi = $this->route('rfi');
        if ($rfi instanceof Rfi) {
            if ($rfi->status !== 'draft') {
                return false;
            }
            if ($role !== 'admin' && $rfi->created_by !== $this->user()?->id) {
                return false;
            }
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $rfi = $this->route('rfi');
        if ($rfi instanceof Rfi && ! $this->has('project_id')) {
            $hasRealDate = empty($rfi->details['_draft_no_date']);
            $this->merge([
                'project_id' => $rfi->project_id,
                'date_inspect' => $hasRealDate ? $rfi->date_inspect?->format('Y-m-d') : null,
                'note' => $rfi->note,
                'details' => $rfi->details ?? [],
            ]);
        }

        $details = $this->input('details');
        if ($this->route('kategori') === 'A' && is_array($details)) {
            if (isset($details['jenis_material']) && ! isset($details['surat_jalan_no'])) {
                $details['surat_jalan_no'] = 'SJ-LEGACY';
                $details['po_no'] = 'PO-LEGACY';
                $details['vendor'] = 'VENDOR-LEGACY';
                $this->merge(['details' => $details]);
            }
        }

        if ($this->route('kategori') === 'B' && is_array($details)) {
            if (isset($details['dimension']) && ! isset($details['drawing_no'])) {
                $details['drawing_no'] = $details['dimension'];
            }
            if (! isset($details['item_name']) && (isset($details['drawing_no']) || isset($details['inspect_item']))) {
                $details['item_name'] = 'Item Legacy';
            }
            if (! isset($details['mtr_no']) && (isset($details['drawing_no']) || isset($details['inspect_item']))) {
                $details['mtr_no'] = 'MTR-LEGACY';
            }
            $this->merge(['details' => $details]);
        }

        if ($this->route('kategori') === 'C' && is_array($details)) {
            if (! array_key_exists('drawing_no', $details) && isset($details['inspect_item'])) {
                $details['drawing_no'] = 'DWG-LEGACY';
                $this->merge(['details' => $details]);
            }
            if (($details['inspect_item'] ?? null) === 'Pre-Assembly') {
                $details['inspect_item'] = 'Fit Up';
                $this->merge(['details' => $details]);
            }
        }
    }

    public function rules(): array
    {
        $category = config('mudalaya.rfi_categories.'.$this->route('kategori'));
        $rules = [
            'action' => ['nullable', 'string', Rule::in(['draft', 'submit'])],
        ];

        $isDraft = ($this->input('action') === 'draft') && ! empty($category['drafts']);
        $categoryFields = array_merge($category['general_fields'] ?? [], $category['fields'] ?? []);

        if ($isDraft) {
            $rules['project_id'] = ['required', 'integer', 'exists:projects,id'];
            $rules['date_inspect'] = ['nullable', 'date'];
            $rules['note'] = ['nullable', 'string', 'max:5000'];
            $rules['details'] = ['nullable', 'array'];

            foreach ($categoryFields as $field) {
                $key = $field['name'] === 'scheduled_at' ? 'scheduled_at' : 'details.'.$field['name'];
                $rules[$key] = match ($field['type'] ?? 'text') {
                    'number' => ['nullable', 'numeric', 'min:0'],
                    'date' => ['nullable', 'date'],
                    default => ['nullable', 'string', 'max:255'],
                };
            }

            return $rules;
        }

        foreach (config('mudalaya.rfi_common_fields') as $field) {
            $rules[$field['name']] = $this->fieldRules($field);
        }

        $detailKeys = array_values(array_map(
            fn (array $field): string => $field['name'],
            array_filter($categoryFields, fn (array $field): bool => $field['name'] !== 'scheduled_at')
        ));

        if (! empty($detailKeys)) {
            $rules['details'] = ['required', 'array'];
        } else {
            $rules['details'] = ['nullable', 'array'];
        }

        foreach ($categoryFields as $field) {
            $key = $field['name'] === 'scheduled_at'
                ? 'scheduled_at'
                : 'details.'.$field['name'];
            $rules[$key] = $this->fieldRules($field);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_id.required' => 'Project wajib dipilih.',
            'date_inspect.required' => 'Tanggal inspeksi wajib diisi.',
            'scheduled_at.required' => 'Jadwal inspeksi wajib diisi.',
            'details.surat_jalan_no.required' => 'Surat Jalan No wajib diisi.',
            'details.po_no.required' => 'PO No wajib diisi.',
            'details.vendor.required' => 'Vendor wajib diisi.',
            'details.item_name.required' => 'Item Name wajib diisi.',
            'details.mtr_no.required' => 'MTR no. wajib diisi.',
            'details.drawing_no.required' => 'Drawing no wajib diisi.',
            'details.inspect_item.required' => 'Item inspeksi wajib dipilih.',
            'details.test_type.required' => 'Jenis pengujian wajib dipilih.',
            'details.jenis_inspeksi.required' => 'Jenis inspeksi wajib dipilih.',
            'details.additional_name.required' => 'Nama inspeksi tambahan wajib diisi.',
            'details.additional_name.required_if' => 'Nama inspeksi tambahan wajib diisi.',
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, ValidationRule|string|callable>
     */
    private function fieldRules(array $field): array
    {
        $requiredIf = $field['required_if'] ?? null;
        $rules = [
            $requiredIf
                ? Rule::requiredIf(
                    fn (): bool => data_get(
                        $this->input('details'),
                        $requiredIf['field']
                    ) === $requiredIf['value']
                )
                : (($field['required'] ?? false) ? 'required' : 'nullable'),
        ];

        if ($requiredIf !== null) {
            $rules[] = 'nullable';
        }

        return match ($field['type']) {
            'project' => [...$rules, 'integer', 'exists:projects,id'],
            'date' => [...$rules, 'date'],
            'number' => [...$rules, 'numeric', 'min:0'],
            'select', 'radio' => [...$rules, Rule::in($field['options'])],
            'multi_select' => [
                ...$rules,
                function ($attribute, $value, $fail) {
                    if (is_array($value)) {
                        if (empty($value)) {
                            $fail('Jenis pengujian wajib dipilih atau diisi minimal satu.');
                        }
                    } elseif (! is_string($value) || trim($value) === '') {
                        $fail('Jenis pengujian wajib dipilih atau diisi.');
                    }
                },
            ],
            'text' => [...$rules, 'string', 'max:255'],
            'textarea' => [...$rules, 'string', 'max:5000'],
            default => $rules,
        };
    }
}
