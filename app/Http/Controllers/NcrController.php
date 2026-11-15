<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNcrRequest;
use App\Http\Requests\UpdateNcrRequest;
use App\Models\Ncr;
use App\Models\Project;
use App\Services\NcrDashboardService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NcrController extends Controller
{
    public function index(Request $request, NcrDashboardService $dashboardService): View
    {
        Gate::authorize('viewAny', Ncr::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'status' => ['nullable', 'string'],
            'level' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'department' => ['nullable', 'string'],
            'project' => ['nullable', 'string'],
        ]);

        $query = Ncr::query()->with('project');

        // Text search
        if (! empty($validated['search'])) {
            $search = '%'.$validated['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('ncr_number', 'like', $search)
                    ->orWhere('problem_description', 'like', $search)
                    ->orWhere('responsible_pic', 'like', $search);
            });
        }

        // Filters
        if (! empty($validated['year'])) {
            $query->whereYear('finding_date', (int) $validated['year']);
        }

        if (! empty($validated['month'])) {
            $query->whereMonth('finding_date', (int) $validated['month']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['level'])) {
            $query->where('level', $validated['level']);
        }

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (! empty($validated['department'])) {
            $query->where('department', $validated['department']);
        }

        if (! empty($validated['project'])) {
            $query->where('project_code', $validated['project']);
        }

        $ncrs = $query->orderByDesc('finding_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Aggregate count per status, honoring all filters EXCEPT status
        $countQuery = Ncr::query();
        if (! empty($validated['search'])) {
            $search = '%'.$validated['search'].'%';
            $countQuery->where(function ($q) use ($search) {
                $q->where('ncr_number', 'like', $search)
                    ->orWhere('problem_description', 'like', $search)
                    ->orWhere('responsible_pic', 'like', $search);
            });
        }
        if (! empty($validated['year'])) {
            $countQuery->whereYear('finding_date', (int) $validated['year']);
        }
        if (! empty($validated['month'])) {
            $countQuery->whereMonth('finding_date', (int) $validated['month']);
        }
        if (! empty($validated['level'])) {
            $countQuery->where('level', $validated['level']);
        }
        if (! empty($validated['type'])) {
            $countQuery->where('type', $validated['type']);
        }
        if (! empty($validated['department'])) {
            $countQuery->where('department', $validated['department']);
        }
        if (! empty($validated['project'])) {
            $countQuery->where('project_code', $validated['project']);
        }

        $statusRows = (clone $countQuery)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $statusCounts = [
            'Semua' => $statusRows->sum(),
            'Open' => $statusRows->get('Open', 0),
            'CA Closed' => $statusRows->get('CA Closed', 0),
            'Closed' => $statusRows->get('Closed', 0),
        ];

        $availableYears = $dashboardService->getAvailableYears();
        $projects = Project::query()->orderBy('project_code')->get(['project_code', 'project_name']);

        return view('modules.ncr.index', [
            'ncrs' => $ncrs,
            'filters' => $validated,
            'years' => $availableYears,
            'projects' => $projects,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Ncr::class);

        $projects = Project::query()
            ->orderBy('project_code')
            ->get(['id', 'project_code', 'project_name', 'customer']);

        return view('modules.ncr.create', [
            'projects' => $projects,
        ]);
    }

    public function store(StoreNcrRequest $request): RedirectResponse
    {
        Gate::authorize('create', Ncr::class);

        $year = Carbon::parse($request->input('finding_date'))->year;

        $ncr = DB::transaction(function () use ($request, $year) {
            $data = $request->validated();
            $data['ncr_number'] = Ncr::generateNcrNumber($year);
            $data['created_by'] = $request->user()->id;

            return Ncr::create($data);
        });

        return redirect()->route('ncr.show', $ncr)
            ->with('success', 'NCR '.$ncr->ncr_number.' berhasil dibuat.');
    }

    public function show(Ncr $ncr): View
    {
        Gate::authorize('view', $ncr);

        $ncr->loadMissing(['project', 'creator']);

        return view('modules.ncr.show', [
            'ncr' => $ncr,
        ]);
    }

    public function edit(Ncr $ncr): View
    {
        Gate::authorize('update', $ncr);

        $projects = Project::query()
            ->orderBy('project_code')
            ->get(['id', 'project_code', 'project_name', 'customer']);

        return view('modules.ncr.edit', [
            'ncr' => $ncr,
            'projects' => $projects,
        ]);
    }

    public function update(UpdateNcrRequest $request, Ncr $ncr): RedirectResponse
    {
        Gate::authorize('update', $ncr);

        $data = $request->validated();
        $ncr->update($data);

        return redirect()->route('ncr.show', $ncr)
            ->with('success', 'NCR '.$ncr->ncr_number.' berhasil diperbarui.');
    }

    public function destroy(Ncr $ncr): RedirectResponse
    {
        Gate::authorize('delete', $ncr);

        $ncrNumber = $ncr->ncr_number;
        $ncr->delete();

        return redirect()->route('ncr.index')
            ->with('success', 'NCR '.$ncrNumber.' berhasil dihapus.');
    }
}
