<?php

namespace Database\Factories;

use App\Enums\RfiResult;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rfi>
 */
class RfiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'category' => 'B',
            'details' => ['inspect_item' => 'Cutting'],
            'date_inspect' => now()->toDateString(),
            'status' => 'nc',
            'result' => RfiResult::Repair,
            'qc_note' => fake()->sentence(),
            'created_by' => User::factory(),
            'reviewed_by' => User::factory(['role' => 'qc_qa']),
            'reviewed_at' => now(),
        ];
    }
}
