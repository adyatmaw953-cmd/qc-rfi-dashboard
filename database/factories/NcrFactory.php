<?php

namespace Database\Factories;

use App\Models\Ncr;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ncr>
 */
class NcrFactory extends Factory
{
    protected $model = Ncr::class;

    public function definition(): array
    {
        $year = (int) date('Y');

        return [
            'ncr_number' => 'NCR-'.$year.'-'.$this->faker->unique()->numberBetween(100, 999),
            'finding_date' => Carbon::now()->toDateString(),
            'department' => 'Produksi',
            'related_departments' => ['QC', 'Engineering'],
            'area_finding' => 'Process Produksi',
            'level' => 'Major',
            'type' => 'Internal',
            'project_code' => null,
            'customer' => null,
            'responsible_pic' => $this->faker->name(),
            'problem_description' => $this->faker->sentence(),
            'root_cause' => $this->faker->sentence(),
            'root_cause_category' => ['Man'],
            'corrective_action' => null,
            'pic_ca' => null,
            'ca_closed_date' => null,
            'preventive_action' => null,
            'pic_pa' => null,
            'pa_closed_date' => null,
            'status' => 'Open',
            'nc_cost' => 0,
            'created_by' => User::factory(),
        ];
    }
}
