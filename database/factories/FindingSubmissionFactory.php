<?php

namespace Database\Factories;

use App\Models\FindingSubmission;
use App\Models\QcFinding;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FindingSubmission>
 */
class FindingSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'qc_finding_id' => QcFinding::factory(),
            'round_no' => 1,
            'note' => fake()->paragraph(),
            'submitted_by' => User::factory(),
            'submitted_at' => now(),
            'verification_result' => null,
            'verification_note' => null,
            'verified_by' => null,
            'verified_at' => null,
        ];
    }
}
