<?php

namespace App\Http\Controllers;

use App\Models\Ncr;
use App\Models\Rfi;
use App\Services\DashboardCharts;
use App\Services\DashboardSummary;
use App\Services\NcrDashboardService;
use App\Services\QcDashboardSummary;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function dashboard(Request $request): View
    {
        $role = $request->user()->role;
        $canViewNcr = $request->user()->can('viewAny', Ncr::class);

        $activeTab = $request->query('tab', 'ringkasan');
        if (! in_array($activeTab, ['ringkasan', 'qc-finding', 'ncr'], true) || ($activeTab === 'ncr' && ! $canViewNcr)) {
            $activeTab = 'ringkasan';
        }

        $modules = array_filter(
            config('mudalaya.modules'),
            fn (array $module): bool => in_array($role, $module['roles'], true)
        );

        $allowedCategories = config('mudalaya.findings.view_categories.'.$role, []);

        $categories = collect(config('mudalaya.rfi_categories'))
            ->filter(fn (array $category): bool => in_array($role, $category['roles'], true))
            ->all();
        $counts = ['open' => 0, 'nc' => 0, 'close' => 0];

        if ($categories !== []) {
            $counts = Rfi::query()
                ->whereIn('category', array_keys($categories))
                ->select('status')
                ->selectRaw('COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->union($counts)
                ->all();
        }

        $charts = app(DashboardCharts::class);

        $ncrData = null;
        if ($canViewNcr) {
            $ncrService = app(NcrDashboardService::class);
            $selectedYear = (int) $request->query('ncr_year', Carbon::now('Asia/Jakarta')->year);
            $ncrData = $ncrService->getDashboardData($selectedYear);
        }

        $summaryService = app(DashboardSummary::class);
        $actionItems = $summaryService->getActionItems($request->user());
        $findingSummary = $summaryService->getFindingSummary($allowedCategories);

        $qcDashboard = QcDashboardSummary::overview($request->user(), [
            'pf' => (string) $request->query('pf', 'semua'),
            'pq' => (string) $request->query('pq', ''),
            'page' => $request->query('page'),
        ]);

        return view('modules.dashboard', [
            'activeTab' => $activeTab,
            'canViewNcr' => $canViewNcr,
            'modules' => $modules,
            'counts' => $counts,
            'findingStatusChart' => $charts->findingStatusByMonth($allowedCategories),
            'findingChart' => $charts->findingCreatedVsClosed($allowedCategories),
            'ncrData' => $ncrData,
            'actionItems' => $actionItems,
            'findingSummary' => $findingSummary,
            'qcDashboard' => $qcDashboard,
        ]);
    }

    public function ncr(): View
    {
        Gate::authorize('viewAny', Ncr::class);

        return view('modules.placeholder', ['title' => 'NCR']);
    }
}
