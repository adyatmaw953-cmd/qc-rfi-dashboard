<?php

namespace App\Services;

use App\Models\Project;
use App\Models\QcFinding;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class QcDashboardSummary
{
    private const TZ = 'Asia/Jakarta';

    /** @param list<string> $allowedCategories */
    public function __construct(private readonly array $allowedCategories) {}

    /**
     * Single data source for QC Finding overview.
     * Used by both QcFindingController::index and ModuleController::dashboard.
     *
     * @param  array{pf?: string, pq?: string, page?: int|string}  $filters
     * @return array{
     *     stats: array{openCount: int, waitingCount: int, overdueCount: int, closedCount: int},
     *     projects: LengthAwarePaginator,
     *     filterCounts: array{semua: int, terlambat: int, verifikasi: int},
     *     urgentFindings: Collection,
     *     filter: string,
     *     q: string,
     *     allowedCategories: list<string>,
     * }
     */
    public static function overview(User $user, array $filters = []): array
    {
        $role = $user->role;
        $allowedCategories = config('mudalaya.findings.view_categories.'.$role, []);
        $instance = new self($allowedCategories);

        $filter = (string) ($filters['pf'] ?? 'semua');
        $q = (string) ($filters['pq'] ?? '');

        $stats = $instance->getStats();
        $projectData = $instance->getProjectList($filter, $q);
        $urgentFindings = $instance->getUrgentFindings(4);

        return [
            'stats' => $stats,
            'projects' => $projectData['projects'],
            'filterCounts' => $projectData['counts'],
            'urgentFindings' => $urgentFindings,
            'filter' => $filter,
            'q' => $q,
            'allowedCategories' => $allowedCategories,
        ];
    }

    /**
     * @return array{openCount: int, waitingCount: int, overdueCount: int, closedCount: int}
     */
    public function getStats(): array
    {
        if ($this->allowedCategories === []) {
            return ['openCount' => 0, 'waitingCount' => 0, 'overdueCount' => 0, 'closedCount' => 0];
        }

        $today = Carbon::now(self::TZ)->toDateString();

        $openCount = QcFinding::query()
            ->where('status', 'open')
            ->whereHas('rfi', fn ($q) => $q->whereIn('category', $this->allowedCategories))
            ->count();

        $waitingCount = QcFinding::query()
            ->where('status', 'waiting_verification')
            ->whereHas('rfi', fn ($q) => $q->whereIn('category', $this->allowedCategories))
            ->count();

        $overdueCount = QcFinding::query()
            ->where('status', '!=', 'closed')
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->whereHas('rfi', fn ($q) => $q->whereIn('category', $this->allowedCategories))
            ->count();

        $closedCount = QcFinding::query()
            ->where('status', 'closed')
            ->whereHas('rfi', fn ($q) => $q->whereIn('category', $this->allowedCategories))
            ->count();

        return compact('openCount', 'waitingCount', 'overdueCount', 'closedCount');
    }

    /**
     * Returns project list with finding counts, sorted for urgency display and paginated.
     *
     * @return array{
     *     projects: LengthAwarePaginator,
     *     counts: array{semua: int, terlambat: int, verifikasi: int},
     * }
     */
    public function getProjectList(string $filter = 'semua', string $q = ''): array
    {
        if ($this->allowedCategories === []) {
            $emptyPaginator = new LengthAwarePaginator(collect(), 0, 15, 1, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ]);

            return [
                'projects' => $emptyPaginator,
                'counts' => ['semua' => 0, 'terlambat' => 0, 'verifikasi' => 0],
            ];
        }

        $today = Carbon::now(self::TZ)->toDateString();
        $cats = $this->allowedCategories;

        $base = Project::query()
            ->select('projects.id', 'projects.project_code', 'projects.project_name', 'projects.customer')
            ->join('rfis', 'rfis.project_id', '=', 'projects.id')
            ->join('qc_findings', 'qc_findings.rfi_id', '=', 'rfis.id')
            ->whereIn('rfis.category', $cats)
            ->selectRaw("COUNT(CASE WHEN qc_findings.status = 'open' THEN qc_findings.id END) as open_count")
            ->selectRaw("COUNT(CASE WHEN qc_findings.status = 'waiting_verification' THEN qc_findings.id END) as waiting_count")
            ->selectRaw("COUNT(CASE WHEN qc_findings.status = 'closed' THEN qc_findings.id END) as closed_count")
            ->selectRaw('COUNT(qc_findings.id) as total_count')
            ->selectRaw("COUNT(CASE WHEN qc_findings.status != 'closed' THEN qc_findings.id END) as open_findings_count")
            ->selectRaw("MIN(CASE WHEN qc_findings.status != 'closed' AND qc_findings.due_date IS NOT NULL THEN date(qc_findings.due_date) END) as nearest_due_date")
            ->selectRaw("MIN(CASE WHEN qc_findings.status != 'closed' AND qc_findings.due_date IS NOT NULL AND date(qc_findings.due_date) < '{$today}' THEN date(qc_findings.due_date) END) as oldest_overdue_date")
            ->selectRaw("COUNT(CASE WHEN qc_findings.status != 'closed' AND qc_findings.due_date IS NOT NULL AND date(qc_findings.due_date) = '{$today}' THEN qc_findings.id END) as due_today_count")
            ->groupBy('projects.id', 'projects.project_code', 'projects.project_name', 'projects.customer');

        if ($q !== '') {
            $like = '%'.mb_strtolower($q).'%';
            $base->where(function ($query) use ($like): void {
                $query->whereRaw('LOWER(projects.project_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(projects.project_code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(projects.customer) LIKE ?', [$like]);
            });
        }

        $all = $base->get()->map(function ($p) use ($today) {
            $p->open_count = (int) $p->open_count;
            $p->waiting_count = (int) $p->waiting_count;
            $p->closed_count = (int) $p->closed_count;
            $p->total_count = (int) $p->total_count;
            $p->open_findings_count = (int) $p->open_findings_count;
            $p->due_today_count = (int) ($p->due_today_count ?? 0);
            $p->has_due_today = $p->due_today_count > 0;
            $p->most_overdue_days = $p->oldest_overdue_date
                ? (int) max(0, Carbon::parse($p->oldest_overdue_date, self::TZ)->diffInDays(Carbon::parse($today, self::TZ)))
                : 0;

            return $p;
        });

        $semuaCount = $all->count();
        $terlambatCount = $all->filter(fn ($p) => ($p->most_overdue_days ?? 0) > 0 || $p->has_due_today)->count();
        $verifikasiCount = $all->filter(fn ($p) => $p->waiting_count > 0)->count();

        $filtered = match ($filter) {
            'terlambat' => $all->filter(fn ($p) => ($p->most_overdue_days ?? 0) > 0 || $p->has_due_today)->values(),
            'verifikasi' => $all->filter(fn ($p) => $p->waiting_count > 0)->values(),
            default => $all->values(),
        };

        // Sort: most overdue first, then waiting, then nearest due date, then all-closed last
        $sorted = $filtered->sort(function ($a, $b): int {
            $aOverdue = $a->most_overdue_days ?? 0;
            $bOverdue = $b->most_overdue_days ?? 0;

            if ($aOverdue !== $bOverdue) {
                return $bOverdue <=> $aOverdue;
            }

            if ($a->waiting_count !== $b->waiting_count) {
                return $b->waiting_count <=> $a->waiting_count;
            }

            $aNearest = $a->nearest_due_date;
            $bNearest = $b->nearest_due_date;

            if ($aNearest === null && $bNearest === null) {
                return 0;
            }

            if ($aNearest === null) {
                return 1;
            }

            if ($bNearest === null) {
                return -1;
            }

            return $aNearest <=> $bNearest;
        })->values();

        $perPage = 15;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $sorted->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginated = new LengthAwarePaginator(
            $currentItems,
            $sorted->count(),
            $perPage,
            $currentPage,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ]
        );

        return [
            'projects' => $paginated,
            'counts' => [
                'semua' => $semuaCount,
                'terlambat' => $terlambatCount,
                'verifikasi' => $verifikasiCount,
            ],
        ];
    }

    /**
     * Top N urgent findings (overdue first, then waiting_verification).
     *
     * @return Collection<int, array{id: int, project_id: int, project_code: string|null, project_name: string, finding_list: string, due_date: string|null, status: string}>
     */
    public function getUrgentFindings(int $limit = 4): Collection
    {
        if ($this->allowedCategories === []) {
            return collect();
        }

        $today = Carbon::now(self::TZ)->toDateString();

        return QcFinding::query()
            ->with('rfi.project:id,project_code,project_name')
            ->where('status', '!=', 'closed')
            ->whereHas('rfi', fn ($q) => $q->whereIn('category', $this->allowedCategories))
            ->orderByRaw(
                "CASE WHEN due_date IS NOT NULL AND due_date < '{$today}' THEN 0 ELSE 1 END"
            )
            ->orderByRaw(
                "CASE WHEN due_date IS NOT NULL AND due_date < '{$today}' THEN due_date END ASC"
            )
            ->orderByRaw(
                "CASE WHEN status = 'waiting_verification' THEN 0 ELSE 1 END"
            )
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (QcFinding $f) => [
                'id' => $f->id,
                'project_id' => $f->rfi->project_id,
                'project_code' => $f->rfi->project->project_code ?? null,
                'project_name' => $f->rfi->project->project_name ?? '—',
                'finding_list' => $f->finding_list ?? '',
                'due_date' => $f->due_date?->toDateString(),
                'status' => $f->status,
            ]);
    }
}
