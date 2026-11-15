<?php

namespace App\Http\Controllers;

use App\Enums\MdrStatus;
use App\Http\Requests\StoreMdrRequest;
use App\Http\Requests\UpdateMdrRequest;
use App\Models\Mdr;
use App\Models\Project;
use App\Services\DashboardCharts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MdrChecklistController extends Controller
{
    public function index(Request $request): View
    {
        $reportKeys = array_keys(config('mudalaya.mdr_reports', []));
        $validated = $request->validate([
            'status' => ['nullable', 'in:open,close'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = $validated['search'] ?? null;
        $reportCount = count($reportKeys);
        $completedStatuses = [MdrStatus::Close->value, MdrStatus::NotApplicable->value];
        $completedReports = fn (Builder $query): Builder => $query
            ->whereIn('report_key', $reportKeys)
            ->whereIn('status', $completedStatuses);

        // $mdrTotals (total all MDRs without filter via single aggregate query)
        $totalsQuery = DB::query()
            ->fromSub(
                Mdr::query()->withCount(['reportStatuses as completed_count' => $completedReports]),
                'totals_sub'
            )
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN completed_count >= ? THEN 1 ELSE 0 END) as close_count', [$reportCount])
            ->first();

        $totalAll = (int) ($totalsQuery->total ?? 0);
        $totalClose = (int) ($totalsQuery->close_count ?? 0);
        $totalOpen = max(0, $totalAll - $totalClose);

        $mdrTotals = [
            'total' => $totalAll,
            'open' => $totalOpen,
            'close' => $totalClose,
        ];

        // $statusCounts (follows ?search but ignores ?status)
        if (empty($search)) {
            $statusCounts = [
                'all' => $totalAll,
                'open' => $totalOpen,
                'close' => $totalClose,
            ];
        } else {
            $searchSub = Mdr::query()
                ->where(function (Builder $q) use ($search): void {
                    $q->where('unit_no', 'like', "%{$search}%")
                        ->orWhere('owner_serial_no', 'like', "%{$search}%")
                        ->orWhere('manufacture_serial_no', 'like', "%{$search}%")
                        ->orWhereHas('project', function (Builder $pq) use ($search): void {
                            $pq->where('project_name', 'like', "%{$search}%")
                                ->orWhere('project_code', 'like', "%{$search}%");
                        });
                })
                ->withCount(['reportStatuses as completed_count' => $completedReports]);

            $searchTotals = DB::query()
                ->fromSub($searchSub, 'search_sub')
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN completed_count >= ? THEN 1 ELSE 0 END) as close_count', [$reportCount])
                ->first();

            $sTotal = (int) ($searchTotals->total ?? 0);
            $sClose = (int) ($searchTotals->close_count ?? 0);
            $sOpen = max(0, $sTotal - $sClose);

            $statusCounts = [
                'all' => $sTotal,
                'open' => $sOpen,
                'close' => $sClose,
            ];
        }

        $mdrs = Mdr::query()
            ->with([
                'project:id,project_code,project_name,customer',
                'reportStatuses' => fn (HasMany $query): HasMany => $query
                    ->whereIn('report_key', $reportKeys),
            ])
            ->withCount(['reportStatuses as completed_reports_count' => $completedReports])
            ->when(
                $search,
                fn (Builder $query): Builder => $query->where(function (Builder $q) use ($search): void {
                    $q->where('unit_no', 'like', "%{$search}%")
                        ->orWhere('owner_serial_no', 'like', "%{$search}%")
                        ->orWhere('manufacture_serial_no', 'like', "%{$search}%")
                        ->orWhereHas('project', function (Builder $pq) use ($search): void {
                            $pq->where('project_name', 'like', "%{$search}%")
                                ->orWhere('project_code', 'like', "%{$search}%");
                        });
                })
            )
            ->when(
                ($validated['status'] ?? null) === MdrStatus::Close->value,
                fn (Builder $query): Builder => $query->whereHas(
                    'reportStatuses',
                    $completedReports,
                    '=',
                    $reportCount
                )
            )
            ->when(
                ($validated['status'] ?? null) === MdrStatus::Open->value,
                fn (Builder $query): Builder => $query->whereHas(
                    'reportStatuses',
                    $completedReports,
                    '<',
                    $reportCount
                )
            )
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $mdrChart = app(DashboardCharts::class)->mdrByMonth();

        return view('modules.mdr-checklist-index', compact('mdrs', 'validated', 'mdrChart', 'mdrTotals', 'statusCounts'));
    }

    public function create(): View
    {
        $projects = Project::query()
            ->orderBy('project_name')
            ->get(['id', 'project_code', 'project_name', 'customer']);

        return view('modules.mdr-checklist-create', [
            'projects' => $projects,
            'reports' => config('mudalaya.mdr_reports'),
            'statuses' => MdrStatus::cases(),
        ]);
    }

    public function store(StoreMdrRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $reports = config('mudalaya.mdr_reports');

        $mdr = DB::transaction(function () use ($request, $validated, $reports): Mdr {
            $mdr = Mdr::query()->create([
                'project_id' => $validated['project_id'],
                'owner_serial_no' => $validated['owner_serial_no'] ?? null,
                'manufacture_serial_no' => $validated['manufacture_serial_no'] ?? null,
                'unit_no' => $validated['unit_no'] ?? ($validated['owner_serial_no'] ?: ($validated['manufacture_serial_no'] ?: 'Unit 1')),
                'created_by' => $request->user()->id,
            ]);

            foreach ($reports as $reportKey => $label) {
                $mdr->reportStatuses()->create([
                    'report_key' => $reportKey,
                    'report_title' => $label,
                    'status' => $validated['statuses'][$reportKey] ?? MdrStatus::Open->value,
                ]);
            }

            if (! empty($validated['custom_reports'])) {
                foreach ($validated['custom_reports'] as $idx => $custom) {
                    $name = trim($custom['name'] ?? '');
                    if ($name === '') {
                        continue;
                    }
                    $mdr->reportStatuses()->create([
                        'report_key' => 'custom_'.($idx + 1).'_'.Str::slug($name),
                        'report_title' => $name,
                        'status' => $custom['status'] ?? MdrStatus::Open->value,
                    ]);
                }
            }

            return $mdr;
        });

        return redirect()->route('mdr-checklist.show', ['mdr' => $mdr])
            ->with('success', 'MDR berhasil dibuat.');
    }

    public function show(Mdr $mdr): View
    {
        $mdr = DB::transaction(function () use ($mdr): Mdr {
            $lockedMdr = Mdr::query()
                ->whereKey($mdr->id)
                ->lockForUpdate()
                ->firstOrFail();

            foreach (config('mudalaya.mdr_reports') as $reportKey => $label) {
                $lockedMdr->reportStatuses()->firstOrCreate(
                    ['report_key' => $reportKey],
                    [
                        'report_title' => $label,
                        'status' => MdrStatus::Open->value,
                    ]
                );
            }

            return $lockedMdr->load([
                'project:id,project_code,project_name,customer',
                'reportStatuses',
            ]);
        });

        $standardKeys = array_keys(config('mudalaya.mdr_reports'));
        $customReportStatuses = $mdr->reportStatuses
            ->whereNotIn('report_key', $standardKeys);

        return view('modules.mdr-checklist-show', [
            'mdr' => $mdr,
            'reports' => config('mudalaya.mdr_reports'),
            'statuses' => MdrStatus::cases(),
            'reportStatuses' => $mdr->reportStatuses->keyBy('report_key'),
            'customReports' => $customReportStatuses,
        ]);
    }

    public function update(UpdateMdrRequest $request, Mdr $mdr): RedirectResponse
    {
        $validated = $request->validated();
        $reports = config('mudalaya.mdr_reports');
        $userId = $request->user()->id;

        DB::transaction(function () use ($mdr, $validated, $reports, $userId): void {
            $lockedMdr = Mdr::query()
                ->whereKey($mdr->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (isset($validated['owner_serial_no']) || isset($validated['manufacture_serial_no'])) {
                $lockedMdr->update([
                    'owner_serial_no' => $validated['owner_serial_no'] ?? $lockedMdr->owner_serial_no,
                    'manufacture_serial_no' => $validated['manufacture_serial_no'] ?? $lockedMdr->manufacture_serial_no,
                ]);
            }

            foreach ($reports as $reportKey => $label) {
                $reportStatus = $lockedMdr->reportStatuses()
                    ->where('report_key', $reportKey)
                    ->lockForUpdate()
                    ->first();
                $newStatus = $validated['statuses'][$reportKey] ?? MdrStatus::Open->value;

                if ($reportStatus === null) {
                    $lockedMdr->reportStatuses()->create([
                        'report_key' => $reportKey,
                        'report_title' => $label,
                        'status' => $newStatus,
                        'updated_by' => $newStatus === MdrStatus::Open->value ? null : $userId,
                    ]);

                    continue;
                }

                if ($reportStatus->status->value !== $newStatus) {
                    $reportStatus->update([
                        'status' => $newStatus,
                        'updated_by' => $userId,
                    ]);
                }
            }

            // Handle custom reports
            $customReports = $validated['custom_reports'] ?? [];
            $existingCustom = $lockedMdr->reportStatuses()
                ->whereNotIn('report_key', array_keys($reports))
                ->get()
                ->keyBy('report_key');

            $submittedKeys = [];
            foreach ($customReports as $idx => $custom) {
                $name = trim($custom['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $key = ! empty($custom['key']) ? $custom['key'] : ('custom_'.($idx + 1).'_'.Str::slug($name));
                $submittedKeys[] = $key;
                $status = $custom['status'] ?? MdrStatus::Open->value;

                if ($existingCustom->has($key)) {
                    $rec = $existingCustom->get($key);
                    $rec->update([
                        'report_title' => $name,
                        'status' => $status,
                        'updated_by' => $userId,
                    ]);
                } else {
                    $lockedMdr->reportStatuses()->create([
                        'report_key' => $key,
                        'report_title' => $name,
                        'status' => $status,
                        'updated_by' => $userId,
                    ]);
                }
            }

            foreach ($existingCustom as $k => $rec) {
                if (! in_array($k, $submittedKeys, true)) {
                    $rec->delete();
                }
            }
        });

        return redirect()->route('mdr-checklist.show', ['mdr' => $mdr])
            ->with('success', 'Status report MDR berhasil disimpan.');
    }

    public function destroy(Request $request, Mdr $mdr): RedirectResponse
    {
        $role = $request->user()?->role;
        abort_unless(in_array($role, ['admin', 'qc_qa'], true), 403);

        $projectName = $mdr->project?->project_name ?? 'MDR';
        $mdr->delete();

        return redirect()->route('mdr-checklist.index')
            ->with('success', "MDR untuk {$projectName} berhasil dihapus.");
    }
}
