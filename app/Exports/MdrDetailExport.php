<?php

namespace App\Exports;

use App\Enums\MdrStatus;
use App\Models\Mdr;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MdrDetailExport implements FromArray, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected Mdr $mdr
    ) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $mdr = $this->mdr;
        $standardReports = config('mudalaya.mdr_reports', []);
        $standardKeys = array_keys($standardReports);

        $reportStatuses = $mdr->reportStatuses;
        $reportStatusesByKey = $reportStatuses->keyBy('report_key');

        $tableRows = [];
        $completedCount = 0;
        $no = 1;

        // 16 report standar dari config
        foreach ($standardReports as $key => $title) {
            $record = $reportStatusesByKey->get($key);
            $statusEnum = $record?->status;

            if ($statusEnum instanceof MdrStatus) {
                $statusLabel = $statusEnum->label();
                if ($statusEnum === MdrStatus::Close || $statusEnum === MdrStatus::NotApplicable) {
                    $completedCount++;
                }
            } else {
                $statusLabel = 'Open';
            }

            $tableRows[] = [
                $no++,
                $record?->report_title ?: $title,
                $statusLabel,
            ];
        }

        // report custom (report_key di luar config)
        $customReports = $reportStatuses->whereNotIn('report_key', $standardKeys);
        foreach ($customReports as $customReport) {
            $statusEnum = $customReport->status;

            if ($statusEnum instanceof MdrStatus) {
                $statusLabel = $statusEnum->label();
                if ($statusEnum === MdrStatus::Close || $statusEnum === MdrStatus::NotApplicable) {
                    $completedCount++;
                }
            } else {
                $statusLabel = 'Open';
            }

            $tableRows[] = [
                $no++,
                $customReport->report_title ?: $customReport->report_key,
                $statusLabel,
            ];
        }

        $totalReports = count($standardReports) + $customReports->count();
        $overallStatus = ($completedCount >= $totalReports && $totalReports > 0) ? 'Close' : 'Open';
        $progresText = "{$completedCount} dari {$totalReports}";

        $projectCode = $mdr->project?->project_code ?? '';
        $projectName = $mdr->project?->project_name ?? '';
        $projectCodeName = trim("{$projectCode} - {$projectName}", ' -');

        return [
            ['Project Code - Name', $projectCodeName],
            ['Customer', $mdr->project?->customer ?? ''],
            ['Unit No', $mdr->unit_no ?? ''],
            ['Owner Serial No', $mdr->owner_serial_no ?? ''],
            ['Manufacture Serial No', $mdr->manufacture_serial_no ?? ''],
            ['Status', $overallStatus],
            ['Progres', $progresText],
            [],
            ['No', 'Report', 'Status'],
            ...$tableRows,
        ];
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            'A1:A7' => ['font' => ['bold' => true]],
            9 => ['font' => ['bold' => true]],
        ];
    }
}
