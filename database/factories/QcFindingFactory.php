<?php

namespace Database\Factories;

use App\Models\QcFinding;
use App\Models\Rfi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QcFinding>
 */
class QcFindingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rfi_id' => Rfi::factory(),
            'result' => 'repair',
            'finding_list' => fake()->sentence(),
            'date_of_finding' => now()->toDateString(),
            'due_date' => null,
            'status' => 'open',
            'action_plan' => null,
            'action_plan_by' => null,
            'action_plan_at' => null,
            'date_of_close' => null,
        ];
    }
}
