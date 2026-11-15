<?php

namespace Database\Factories;

use App\Models\Irf;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Irf>
 */
class IrfFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'reviewed_by' => null,
            'produk' => fake()->words(2, true),
            'irf_no' => fake()->unique()->bothify('IRF-####'),
            'status' => 'IRF Pending',
            'date_inspect' => fake()->date(),
            'inspect_item' => fake()->word(),
            'drawing_ref' => null,
            'note' => null,
            'qc_result' => null,
            'response_to_finding' => null,
            'is_new' => false,
        ];
    }
}
