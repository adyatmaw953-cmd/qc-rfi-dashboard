<?php

namespace Database\Factories;

use App\Models\Mdr;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mdr>
 */
class MdrFactory extends Factory
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
            'owner_serial_no' => null,
            'manufacture_serial_no' => null,
            'unit_no' => 'Unit '.fake()->unique()->numberBetween(1, 99999),
            'created_by' => User::factory(),
        ];
    }
}
