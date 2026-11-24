<?php

namespace App\Http\Resources;

use App\Models\Mdr;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Mdr
 */
class MdrResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $standardReports = config('mudalaya.mdr_reports', []);
        $standardKeys = array_keys($standardReports);

        $reportStatuses = $this->reportStatuses;
        $reportStatusesByKey = $reportStatuses->keyBy('report_key');

        $reportsList = [];
        $doneCount = 0;

        foreach ($standardReports as $key => $standardName) {
            $record = $reportStatusesByKey->get($key);
            $statusVal = $record?->status?->value ?? 'open';
            $name = $record?->report_title ?: $standardName;

            if (in_array($statusVal, ['close', 'na'], true)) {
                $doneCount++;
            }

            $reportsList[] = [
                'key' => $key,
                'name' => $name,
                'status' => $statusVal,
            ];
        }

        $customRecords = $reportStatuses->whereNotIn('report_key', $standardKeys);
        foreach ($customRecords as $custom) {
            $statusVal = $custom->status?->value ?? 'open';
            $name = $custom->report_title ?: $custom->report_key;

            if (in_array($statusVal, ['close', 'na'], true)) {
                $doneCount++;
            }

            $reportsList[] = [
                'key' => $custom->report_key,
                'name' => $name,
                'status' => $statusVal,
            ];
        }

        $totalCount = count($standardReports) + $customRecords->count();
        $overallStatus = ($doneCount >= $totalCount && $totalCount > 0) ? 'close' : 'open';

        return [
            'id' => $this->id,
            'project_code' => $this->project?->project_code,
            'project_name' => $this->project?->project_name,
            'customer' => $this->project?->customer,
            'unit_no' => $this->unit_no,
            'owner_serial_no' => $this->owner_serial_no,
            'manufacture_serial_no' => $this->manufacture_serial_no,
            'status' => $overallStatus,
            'progress' => [
                'done' => $doneCount,
                'total' => $totalCount,
            ],
            'reports' => $reportsList,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
