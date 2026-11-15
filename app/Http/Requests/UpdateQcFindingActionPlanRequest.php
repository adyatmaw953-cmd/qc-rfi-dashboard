<?php

namespace App\Http\Requests;

use App\Models\QcFinding;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQcFindingActionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $finding = $this->route('finding');
        $role = $this->user()?->role;

        if (! $finding instanceof QcFinding || $finding->status !== 'open') {
            return false;
        }

        if ($role === 'admin') {
            return true;
        }

        $finding->loadMissing('rfi');
        $category = $finding->rfi?->category;
        $actionPlanRoles = config("mudalaya.findings.action_plan_roles.{$category}", []);

        return in_array($role, $actionPlanRoles, true);
    }

    public function rules(): array
    {
        return [
            'action_plan' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
