<?php

namespace Database\Factories;

use App\Models\FindingEvidence;
use App\Models\FindingSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FindingEvidence>
 */
class FindingEvidenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'finding_submission_id' => FindingSubmission::factory(),
            'original_name' => 'evidence.pdf',
            'path' => fake()->unique()->uuid().'.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
        ];
    }
}
