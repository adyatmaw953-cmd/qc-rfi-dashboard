<?php

namespace App\Services\Vsm;

use App\Contracts\VsmClient;

class FakeVsmClient implements VsmClient
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $projects = [
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

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $materials = [
        [
            'project_code' => 'VSM-PRJ-001',
            'project_name' => 'Proyek Contoh Utama',
            'customer' => 'PT Contoh Utama',
            'bom_no' => 'BOM-001-A',
            'po_no' => 'PO-001-X',
        ],
        [
            'project_code' => 'VSM-PRJ-001',
            'project_name' => 'Proyek Contoh Utama',
            'customer' => 'PT Contoh Utama',
            'bom_no' => 'BOM-001-B',
            'po_no' => 'PO-001-X',
        ],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function projects(): array
    {
        return $this->projects;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function materials(): array
    {
        return $this->materials;
    }

    /**
     * @return array{bom_numbers: array<int, string>, po_numbers: array<int, string>}
     */
    public function bomAndPoNumbersByProject(string $projectCode): array
    {
        $boms = [];
        $pos = [];
        $allBoms = [];
        $allPos = [];

        $cleanProjectCode = preg_replace('/[^a-zA-Z0-9]/', '', $projectCode);

        foreach ($this->materials as $item) {
            $bom = ! empty($item['bom_no']) && is_string($item['bom_no']) ? trim($item['bom_no']) : null;
            $po = ! empty($item['po_no']) && is_string($item['po_no']) ? trim($item['po_no']) : null;

            if ($bom !== null && $bom !== '' && ! in_array($bom, $allBoms, true)) {
                $allBoms[] = $bom;
            }
            if ($po !== null && $po !== '' && ! in_array($po, $allPos, true)) {
                $allPos[] = $po;
            }

            $code = (string) ($item['project_code'] ?? '');
            $cleanCode = preg_replace('/[^a-zA-Z0-9]/', '', $code);

            if ($cleanProjectCode !== '' && strcasecmp($cleanCode, $cleanProjectCode) === 0) {
                if ($bom !== null && $bom !== '' && ! in_array($bom, $boms, true)) {
                    $boms[] = $bom;
                }

                if ($po !== null && $po !== '' && ! in_array($po, $pos, true)) {
                    $pos[] = $po;
                }
            }
        }

        $finalBoms = ! empty($boms) ? $boms : $allBoms;
        $finalPos = ! empty($pos) ? $pos : $allPos;

        sort($finalBoms, SORT_NATURAL | SORT_FLAG_CASE);
        sort($finalPos, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'bom_numbers' => $finalBoms,
            'po_numbers' => $finalPos,
        ];
    }
}
