<?php

namespace App\Http\Requests;

use App\Models\Ncr;
use Closure;

class UpdateNcrRequest extends StoreNcrRequest
{
    public function authorize(): bool
    {
        $ncr = $this->route('ncr');
        if (! ($ncr instanceof Ncr) && $ncr) {
            $ncr = Ncr::find($ncr);
        }

        if ($ncr && ! $this->user()?->can('update', $ncr)) {
            return false;
        }

        return parent::authorize();
    }

    public function rules(): array
    {
        $rules = parent::rules();

        $statusRules = $rules['status'] ?? [];
        if (is_string($statusRules)) {
            $statusRules = explode('|', $statusRules);
        }

        $statusRules[] = function (string $attribute, mixed $value, Closure $fail): void {
            $ncr = $this->route('ncr');
            if (! ($ncr instanceof Ncr) && $ncr) {
                $ncr = Ncr::find($ncr);
            }

            if (! $ncr) {
                return;
            }

            $statuses = array_values(config('ncr.statuses', ['Open', 'CA Closed', 'Closed']));
            $currentIndex = array_search($ncr->status, $statuses, true);

            if ($currentIndex === false) {
                return;
            }

            $allowedStatuses = [$statuses[$currentIndex]];
            if (isset($statuses[$currentIndex + 1])) {
                $allowedStatuses[] = $statuses[$currentIndex + 1];
            }

            if (! in_array($value, $allowedStatuses, true)) {
                $fail('Status baru hanya boleh sama dengan status sekarang atau satu langkah setelahnya.');
            }
        };

        $rules['status'] = $statusRules;

        return $rules;
    }
}
