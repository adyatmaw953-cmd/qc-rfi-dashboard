<?php

namespace App\Services;

use App\Models\Ncr;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NcrDashboardService
{
    public const MONTH_NAMES = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    /**
     * @return list<int>
     */
    public function getAvailableYears(): array
    {
        $isSqlite = DB::getDriverName() === 'sqlite';
        $rawExpr = $isSqlite
            ? 'DISTINCT CAST(strftime("%Y", finding_date) AS INTEGER) as yr'
            : 'DISTINCT YEAR(finding_date) as yr';

        $dbYears = Ncr::query()
            ->whereNotNull('finding_date')
            ->selectRaw($rawExpr)
            ->orderByDesc('yr')
            ->pluck('yr')
            ->filter()
            ->map(fn ($y) => (int) $y)
            ->values()
            ->all();

        $currentYear = (int) Carbon::now('Asia/Jakarta')->year;
        if (! in_array($currentYear, $dbYears, true)) {
            $dbYears[] = $currentYear;
            rsort($dbYears);
        }

        return array_values($dbYears);
    }

    /**
     * @return array{
     *     years: list<int>,
     *     selectedYear: int,
     *     cards: array{
     *         totalNcr: int,
     *         openNcr: int,
     *         closedNcr: int,
     *         totalNcCost: int,
     *         totalNcCostFormatted: string,
     *         totalMajorNcr: int,
     *         highDepartment: string,
     *         highRootCauseCategory: string,
     *     },
     *     charts: array{
     *         monthlyTrend: array{labels: list<string>, data: list<int>},
     *         monthlyCostTrend: array{labels: list<string>, data: list<int>},
     *         typeDistribution: array{labels: list<string>, data: list<int>},
     *         statusDistribution: array{labels: list<string>, data: list<int>},
     *         departmentDistribution: array{labels: list<string>, data: list<int>},
     *         departmentLevelDistribution: array{labels: list<string>, levels: list<string>, data: list<list<int>>},
     *         areaDistribution: array{labels: list<string>, data: list<int>},
     *         rootCauseCategoryDistribution: array{labels: list<string>, data: list<int>},
     *     }
     * }
     */
    public function getDashboardData(int $year): array
    {
        $availableYears = $this->getAvailableYears();

        $ncrs = Ncr::query()
            ->whereYear('finding_date', $year)
            ->get();

        $totalNcr = $ncrs->count();
        $openNcr = $ncrs->whereIn('status', ['Open', 'CA Closed'])->count();
        $closedNcr = $ncrs->where('status', 'Closed')->count();
        $totalNcCost = (int) $ncrs->sum('nc_cost');
        $totalMajorNcr = $ncrs->where('level', 'Major')->count();

        // Department counts
        $deptCounts = $ncrs->groupBy('department')->map->count()->all();
        $highDepartment = $this->formatHighest($deptCounts);

        // Root cause category counts
        $rootCauseCounts = [];
        foreach ($ncrs as $ncr) {
            $categories = (array) ($ncr->root_cause_category ?? []);
            foreach ($categories as $cat) {
                $cat = trim((string) $cat);
                if ($cat !== '') {
                    $rootCauseCounts[$cat] = ($rootCauseCounts[$cat] ?? 0) + 1;
                }
            }
        }
        $highRootCauseCategory = $this->formatHighest($rootCauseCounts);

        // 12 Months trends
        $monthlyCounts = array_fill(1, 12, 0);
        $monthlyCosts = array_fill(1, 12, 0);

        foreach ($ncrs as $ncr) {
            if ($ncr->finding_date) {
                $month = (int) $ncr->finding_date->format('n');
                if (isset($monthlyCounts[$month])) {
                    $monthlyCounts[$month]++;
                    $monthlyCosts[$month] += (int) ($ncr->nc_cost ?? 0);
                }
            }
        }

        $monthLabels = array_values(self::MONTH_NAMES);

        // Type distribution
        $types = config('ncr.types', ['Project', 'Internal']);
        $typeDistribution = [
            'labels' => $types,
            'data' => array_map(fn ($t) => $ncrs->where('type', $t)->count(), $types),
        ];

        // Status distribution
        $statuses = config('ncr.statuses', ['Open', 'CA Closed', 'Closed']);
        $statusDistribution = [
            'labels' => $statuses,
            'data' => array_map(fn ($s) => $ncrs->where('status', $s)->count(), $statuses),
        ];

        // Department distribution
        $departments = config('ncr.departments', []);
        $departmentDistribution = [
            'labels' => $departments,
            'data' => array_map(fn ($d) => $ncrs->where('department', $d)->count(), $departments),
        ];

        // Department level distribution (Major / Minor / Near Miss per department)
        $levels = config('ncr.levels', ['Major', 'Minor', 'Near Miss']);
        $departmentLevelDistribution = [
            'labels' => $departments,
            'levels' => $levels,
            'data' => array_map(
                fn ($d) => array_map(
                    fn ($l) => $ncrs->where('department', $d)->where('level', $l)->count(),
                    $levels
                ),
                $departments
            ),
        ];

        // Area distribution
        $areas = config('ncr.areas', []);
        $areaDistribution = [
            'labels' => $areas,
            'data' => array_map(fn ($a) => $ncrs->where('area_finding', $a)->count(), $areas),
        ];

        // Root cause category distribution
        $rootCauseCategories = config('ncr.root_cause_categories', ['Man', 'Method', 'Machine', 'Material']);
        $rootCauseCategoryDistribution = [
            'labels' => $rootCauseCategories,
            'data' => array_map(function ($r) use ($ncrs) {
                return $ncrs->filter(function ($ncr) use ($r) {
                    $cats = (array) ($ncr->root_cause_category ?? []);

                    return in_array($r, $cats, true);
                })->count();
            }, $rootCauseCategories),
        ];

        return [
            'years' => $availableYears,
            'selectedYear' => $year,
            'cards' => [
                'totalNcr' => $totalNcr,
                'openNcr' => $openNcr,
                'closedNcr' => $closedNcr,
                'totalNcCost' => $totalNcCost,
                'totalNcCostFormatted' => 'Rp '.number_format($totalNcCost, 0, ',', '.'),
                'totalMajorNcr' => $totalMajorNcr,
                'highDepartment' => $highDepartment,
                'highRootCauseCategory' => $highRootCauseCategory,
            ],
            'charts' => [
                'monthlyTrend' => [
                    'labels' => $monthLabels,
                    'data' => array_values($monthlyCounts),
                ],
                'monthlyCostTrend' => [
                    'labels' => $monthLabels,
                    'data' => array_values($monthlyCosts),
                ],
                'typeDistribution' => $typeDistribution,
                'statusDistribution' => $statusDistribution,
                'departmentDistribution' => $departmentDistribution,
                'departmentLevelDistribution' => $departmentLevelDistribution,
                'areaDistribution' => $areaDistribution,
                'rootCauseCategoryDistribution' => $rootCauseCategoryDistribution,
            ],
        ];
    }

    /**
     * Return highest frequency label(s) and count.
     * If tied, join with ' / ', e.g. "Man / Method (20)".
     *
     * @param  array<string, int>  $counts
     */
    public function formatHighest(array $counts): string
    {
        if (empty($counts)) {
            return '—';
        }

        $max = max($counts);
        if ($max <= 0) {
            return '—';
        }

        $highest = [];
        foreach ($counts as $name => $count) {
            if ($count === $max && (string) $name !== '') {
                $highest[] = (string) $name;
            }
        }

        if (empty($highest)) {
            return '—';
        }

        return implode(' / ', $highest)." ({$max})";
    }
}
