<?php

namespace App\Services\Vsm;

use App\Contracts\VsmClient;

class FakeVsmClient implements VsmClient
{
    public function projects(): array
    {
        return [
            [
                'external_id' => 20001,
                'project_code' => 'VSM-PRJ-001',
                'project_name' => 'Proyek Contoh Utama',
                'customer' => 'PT Contoh Utama',
            ],
            [
                'external_id' => 20002,
                'project_code' => 'VSM-PRJ-002',
                'project_name' => 'Proyek Contoh Kedua',
                'customer' => null,
            ],
        ];
    }
}
