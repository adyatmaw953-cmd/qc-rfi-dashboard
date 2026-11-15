<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class LocalProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        foreach ([
            ['project_code' => 'PRJ-001', 'project_name' => 'Project Alpha', 'customer' => 'PT Nusantara Energi'],
            ['project_code' => 'PRJ-002', 'project_name' => 'Project Bravo', 'customer' => 'PT Energi Sejahtera'],
            ['project_code' => 'PRJ-003', 'project_name' => 'Project Charlie', 'customer' => 'PT Karya Industri'],
        ] as $project) {
            Project::updateOrCreate(
                ['project_code' => $project['project_code']],
                $project
            );
        }
    }
}
