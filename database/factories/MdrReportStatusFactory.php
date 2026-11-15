<?php

namespace Database\Factories;

use App\Enums\MdrStatus;
use App\Models\Mdr;
use App\Models\MdrReportStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MdrReportStatus>
 */
class MdrReportStatusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mdr_id' => Mdr::factory(),
            'report_key' => 'certificate_of_conformance',
            'status' => MdrStatus::Open,
            'updated_by' => null,
        ];
    }
}
