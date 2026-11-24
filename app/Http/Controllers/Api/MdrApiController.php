<?php

namespace App\Http\Controllers\Api;

use App\Enums\MdrStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MdrResource;
use App\Models\Mdr;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MdrApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $rawUpdatedSince = $request->query('updated_since');
        if (is_string($rawUpdatedSince) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2} \d{2}:\d{2}$/', $rawUpdatedSince)) {
            $rawUpdatedSince = str_replace(' ', '+', $rawUpdatedSince);
            $request->merge(['updated_since' => $rawUpdatedSince]);
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:open,close'],
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Mdr::query()->with([
            'project:id,project_code,project_name,customer',
            'reportStatuses',
        ]);

        if (! empty($validated['status'])) {
            $status = strtolower($validated['status']);
            $reportKeys = array_keys(config('mudalaya.mdr_reports', []));
            $reportCount = count($reportKeys);
            $completedStatuses = [MdrStatus::Close->value, MdrStatus::NotApplicable->value];
            $completedReports = fn (Builder $q): Builder => $q
                ->whereIn('report_key', $reportKeys)
                ->whereIn('status', $completedStatuses);

            if ($status === 'close') {
                $query->whereHas('reportStatuses', $completedReports, '=', $reportCount)
                    ->whereDoesntHave('reportStatuses', fn (Builder $q): Builder => $q->where('status', MdrStatus::Open->value));
            } else {
                $query->where(function (Builder $q) use ($completedReports, $reportCount): void {
                    $q->whereHas('reportStatuses', $completedReports, '<', $reportCount)
                        ->orWhereHas('reportStatuses', fn (Builder $sq): Builder => $sq->where('status', MdrStatus::Open->value));
                });
            }
        }

        if (! empty($validated['updated_since'])) {
            $date = Carbon::parse($validated['updated_since']);
            $query->where('updated_at', '>=', $date);
        }

        $perPage = min(200, max(1, (int) ($validated['per_page'] ?? 50)));

        $mdrs = $query->orderBy('id')->paginate($perPage);

        return MdrResource::collection($mdrs);
    }

    public function show(Mdr $mdr): MdrResource
    {
        $mdr->loadMissing([
            'project:id,project_code,project_name,customer',
            'reportStatuses',
        ]);

        return new MdrResource($mdr);
    }
}
