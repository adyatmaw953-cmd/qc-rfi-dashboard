<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => null,
            'project_code' => fake()->unique()->bothify('PRJ-####'),
            'project_name' => fake()->company(),
            'customer' => fake()->company(),
            'synced_at' => now(),
        ];
    }
}
