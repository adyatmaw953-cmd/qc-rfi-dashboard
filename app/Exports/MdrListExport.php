<?php

namespace App\Exports;

use App\Enums\MdrStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MdrListExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles
{
    public function __construct(
        protected Builder $query
    ) {}

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'No',
            'Project Code',
            'Project Name',
            'Customer',
            'Unit No',
            'Owner Serial No',
            'Manufacture Serial No',
            'Progres',
            'Status',
            ...array_values(config('mudalaya.mdr_reports', [])),
        ];
    }

    /**
     * @return Collection<int, array<int, mixed>>
     */
    public function collection(): Collection
    {
        $reports = config('mudalaya.mdr_reports', []);
        $reportKeys = array_keys($reports);
        $totalReports = count($reportKeys);

        $mdrs = $this->query->get();

        $rows = [];
        $no = 1;

        foreach ($mdrs as $mdr) {
            $reportStatuses = $mdr->reportStatuses->keyBy('report_key');
            $completedCount = 0;
            $reportColumns = [];

            foreach ($reportKeys as $key) {
                $statusRecord = $reportStatuses->get($key);
                $statusEnum = $statusRecord?->status;

                if ($statusEnum instanceof MdrStatus) {
                    $label = $statusEnum->label();
                    if ($statusEnum === MdrStatus::Close || $statusEnum === MdrStatus::NotApplicable) {
                        $completedCount++;
                    }
                } else {
                    $label = 'Open';
                }

                $reportColumns[] = $label;
            }

            $status = ($completedCount >= $totalReports && $totalReports > 0) ? 'Close' : 'Open';

            $rows[] = [
                $no++,
                $mdr->project?->project_code ?? '',
                $mdr->project?->project_name ?? '',
                $mdr->project?->customer ?? '',
                $mdr->unit_no ?? '',
                $mdr->owner_serial_no ?? '',
                $mdr->manufacture_serial_no ?? '',
                "{$completedCount} dari {$totalReports}",
                $status,
                ...$reportColumns,
            ];
        }

        return collect($rows);
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
