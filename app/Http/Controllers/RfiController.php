<?php

namespace App\Http\Controllers;

use App\Enums\RfiResult;
use App\Http\Requests\ReviewRfiRequest;
use App\Http\Requests\StoreRfiRequest;
use App\Models\FindingActivity;
use App\Models\Project;
use App\Models\QcFinding;
use App\Models\Rfi;
use App\Models\User;
use App\Services\FindingActivityRecorder;
use App\Services\IrfLeadTimeCalculator;
use App\Services\NotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class RfiController extends Controller
{
    public function index(Request $request): View
    {
        $role = $request->user()->role;
        $categories = collect(config('mudalaya.rfi_categories'))
            ->filter(fn (array $category): bool => in_array($role, $category['roles'], true))
            ->all();
        $counts = ['open' => 0, 'nc' => 0, 'close' => 0];

        if ($categories !== []) {
            $counts = Rfi::query()
                ->whereIn('category', array_keys($categories))
                ->where('status', '!=', 'draft')
                ->select('status')
                ->selectRaw('COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->union($counts)
                ->all();
        }

        return view('modules.rfi', [
            'categories' => $categories,
            'counts' => $counts,
        ]);
    }

    public function category(Request $request, string $kategori): View
    {
        $category = $this->categoryConfig($kategori);
        $user = $request->user();
        abort_unless(in_array($user->role, $category['roles'], true), 403);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'nc', 'close', 'draft'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $validated['status'] ?? null;
        $q = trim((string) ($validated['q'] ?? ''));

        $canSeeDrafts = ! empty($category['drafts'])
            && ($user->role === 'admin' || in_array($user->role, $category['submit_roles'], true));

        $rawCounts = Rfi::query()
            ->where('category', $kategori)
            ->where('status', '!=', 'draft')
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $draftCount = 0;
        if ($canSeeDrafts) {
            $draftCount = Rfi::query()
                ->where('category', $kategori)
                ->where('status', 'draft')
                ->when($user->role !== 'admin', fn (Builder $q): Builder => $q->where('created_by', $user->id))
                ->count();
        }

        $statusCounts = [
            'all' => (int) array_sum($rawCounts),
            'open' => (int) ($rawCounts['open'] ?? 0),
            'nc' => (int) ($rawCounts['nc'] ?? 0),
            'close' => (int) ($rawCounts['close'] ?? 0),
        ];

        $query = Rfi::query()
            ->with([
                'project:id,project_code,project_name',
                'createdBy:id,name,role',
                'qcFinding:id,rfi_id',
            ])
            ->where('category', $kategori)
            ->where(function (Builder $b) use ($user): void {
                $b->where('status', '!=', 'draft');
                if ($user->role === 'admin') {
                    $b->orWhere('status', 'draft');
                } else {
                    $b->orWhere(function (Builder $sub) use ($user): void {
                        $sub->where('status', 'draft')
                            ->where('created_by', $user->id);
                    });
                }
            })
            ->when(
                $status,
                fn (Builder $query, string $status): Builder => $query->where('status', $status)
            )
            ->when(
                $q !== '',
                function (Builder $query) use ($q): Builder {
                    return $query->where(function (Builder $sub) use ($q): void {
                        $sub->where('rfi_no', 'like', "%{$q}%")
                            ->orWhereHas('project', function (Builder $pq) use ($q): void {
                                $pq->where('project_name', 'like', "%{$q}%")
                                    ->orWhere('project_code', 'like', "%{$q}%");
                            });
                    });
                }
            )
            ->orderByDesc('id');
        $rfis = $query->paginate(15)->withQueryString();
        $now = Carbon::now('UTC');

        $rfis->getCollection()->transform(function (Rfi $rfi) use ($now): Rfi {
            $deadline = $rfi->deadline_at;
            $rfi->setAttribute(
                'deadline_status',
                $rfi->status !== 'open' || $deadline === null
                    ? ($deadline === null && $rfi->status === 'open' ? 'OPEN' : null)
                    : ($now->greaterThanOrEqualTo($deadline)
                        ? 'OVERDUE'
                        : ($now->diffInSeconds($deadline) < 86400 ? 'URGENT' : 'OPEN'))
            );

            return $rfi;
        });

        return view('modules.rfi-category', [
            'categoryCode' => $kategori,
            'category' => $category,
            'rfis' => $rfis,
            'canCreate' => $user->role === 'admin'
                || in_array($user->role, $category['submit_roles'], true),
            'canReview' => in_array($user->role, ['admin', 'qc_qa'], true),
            'canSeeDrafts' => $canSeeDrafts,
            'isAdmin' => $user->role === 'admin',
            'results' => RfiResult::cases(),
            'selectedStatus' => $status ?? '',
            'statusCounts' => $statusCounts,
            'draftCount' => $draftCount,
            'q' => $q,
        ]);
    }

    public function create(Request $request, string $kategori): View
    {
        $category = $this->categoryConfig($kategori);
        abort_unless(
            $request->user()->role === 'admin'
                || in_array($request->user()->role, $category['submit_roles'], true),
            403
        );

        return view('modules.rfi-create', [
            'categoryCode' => $kategori,
            'category' => $category,
            'commonFields' => config('mudalaya.rfi_common_fields'),
            'projects' => Project::query()->orderBy('project_name')->get(['id', 'project_code', 'project_name']),
        ]);
    }

    public function store(
        StoreRfiRequest $request,
        string $kategori,
        IrfLeadTimeCalculator $leadTimeCalculator
    ): RedirectResponse {
        $validated = $request->validated();
        $category = $this->categoryConfig($kategori);
        $isDraft = ($request->input('action') === 'draft') && ! empty($category['drafts']);

        $categoryFields = array_merge($category['general_fields'] ?? [], $category['fields'] ?? []);
        $detailKeys = collect($categoryFields)
            ->reject(fn (array $field): bool => $field['name'] === 'scheduled_at')
            ->pluck('name')
            ->all();

        $details = Arr::only($validated['details'] ?? [], $detailKeys);

        if ($isDraft) {
            $hasDate = ! empty($validated['date_inspect']);
            $dateInspect = $hasDate ? $validated['date_inspect'] : Carbon::now('Asia/Jakarta')->toDateString();
            if (! $hasDate) {
                $details['_draft_no_date'] = true;
            }

            $rfi = DB::transaction(function () use ($request, $validated, $kategori, $details, $dateInspect): Rfi {
                $rfi = Rfi::query()->create([
                    'project_id' => $validated['project_id'],
                    'category' => $kategori,
                    'details' => $details,
                    'date_inspect' => $dateInspect,
                    'scheduled_at' => $validated['scheduled_at'] ?? null,
                    'note' => $validated['note'] ?? null,
                    'status' => 'draft',
                    'created_by' => $request->user()->id,
                    'submitted_at' => null,
                    'deadline_at' => null,
                ]);

                $rfi->rfi_no = sprintf('RFI-%s-%04d', $kategori, $rfi->id);
                $rfi->save();

                return $rfi;
            });

            return redirect()->route('rfi.category', ['kategori' => $kategori])
                ->with('success', 'Draft RFI berhasil disimpan.');
        }

        $submittedAt = Carbon::now('UTC');
        $deadlineAt = $leadTimeCalculator->deadlineFor($submittedAt)->setTimezone('UTC');

        $rfi = DB::transaction(function () use (
            $request,
            $validated,
            $kategori,
            $details,
            $submittedAt,
            $deadlineAt
        ): Rfi {
            $rfi = Rfi::query()->create([
                'project_id' => $validated['project_id'],
                'category' => $kategori,
                'details' => $details,
                'date_inspect' => $validated['date_inspect'],
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'note' => $validated['note'] ?? null,
                'status' => 'open',
                'created_by' => $request->user()->id,
                'submitted_at' => $submittedAt,
                'deadline_at' => $deadlineAt,
            ]);

            $rfi->rfi_no = sprintf('RFI-%s-%04d', $kategori, $rfi->id);
            $rfi->save();

            return $rfi;
        });

        try {
            app(NotificationDispatcher::class)->rfiSubmitted($rfi, $request->user());
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->route('rfi.category', ['kategori' => $kategori])
            ->with('success', 'RFI berhasil dibuat.');
    }

    public function edit(Request $request, string $kategori, Rfi $rfi): View
    {
        $category = $this->categoryConfig($kategori);
        abort_unless($rfi->category === $kategori, 404);
        abort_unless($rfi->status === 'draft', 403);
        abort_unless(
            $request->user()->role === 'admin' || $rfi->created_by === $request->user()->id,
            403
        );

        return view('modules.rfi-create', [
            'categoryCode' => $kategori,
            'category' => $category,
            'commonFields' => config('mudalaya.rfi_common_fields'),
            'projects' => Project::query()->orderBy('project_name')->get(['id', 'project_code', 'project_name']),
            'rfi' => $rfi,
        ]);
    }

    public function show(Request $request, string $kategori, Rfi $rfi): View
    {
        $category = $this->categoryConfig($kategori);
        abort_unless($rfi->category === $kategori, 404);

        $user = $request->user();
        abort_unless(
            $user->role === 'admin' || in_array($user->role, $category['roles'], true),
            403
        );

        if ($rfi->status === 'draft') {
            abort_unless($user->role === 'admin' || $rfi->created_by === $user->id, 403);
        }

        $canReview = in_array($user->role, ['admin', 'qc_qa'], true);

        return view('modules.rfi-show', [
            'categoryCode' => $kategori,
            'category' => $category,
            'rfi' => $rfi->loadMissing(['project', 'createdBy', 'qcFinding']),
            'canReview' => $canReview,
        ]);
    }

    public function makeFinding(Request $request, string $kategori, Rfi $rfi): RedirectResponse
    {
        $this->categoryConfig($kategori);
        abort_unless(in_array($request->user()->role, ['admin', 'qc_qa'], true), 403);
        abort_unless($rfi->category === $kategori, 404);
        abort_unless($rfi->status === 'open', 400);

        $qcFinding = DB::transaction(function () use ($request, $rfi): QcFinding {
            $lockedRfi = Rfi::query()->whereKey($rfi->id)->lockForUpdate()->firstOrFail();
            $lockedRfi->status = 'nc';
            $lockedRfi->result = RfiResult::Repair;
            $lockedRfi->reviewed_by = $request->user()->id;
            $lockedRfi->reviewed_at = Carbon::now('UTC');
            $lockedRfi->save();

            $qcFinding = QcFinding::query()->firstOrCreate(
                ['rfi_id' => $lockedRfi->id],
                [
                    'result' => RfiResult::Repair,
                    'finding_list' => 'Incoming Material Finding',
                    'date_of_finding' => Carbon::now('Asia/Jakarta')->toDateString(),
                    'due_date' => Carbon::now('Asia/Jakarta')->addDay()->toDateString(),
                    'status' => 'open',
                ]
            );

            if ($qcFinding->wasRecentlyCreated) {
                $rfiSubmitter = $lockedRfi->created_by ? User::find($lockedRfi->created_by) : null;
                FindingActivityRecorder::record(
                    finding: $qcFinding,
                    type: FindingActivity::TYPE_RFI_SUBMITTED,
                    actor: $rfiSubmitter,
                    meta: ['rfi_no' => $lockedRfi->rfi_no],
                    at: $lockedRfi->submitted_at ? Carbon::instance($lockedRfi->submitted_at) : Carbon::now('UTC'),
                );
                FindingActivityRecorder::record(
                    finding: $qcFinding,
                    type: FindingActivity::TYPE_FINDING_CREATED,
                    actor: $request->user(),
                    meta: array_filter([
                        'disposition' => RfiResult::Repair->value,
                        'rfi_no' => $lockedRfi->rfi_no,
                    ]),
                    at: Carbon::instance($qcFinding->created_at ?? Carbon::now('UTC')),
                );
            }

            return $qcFinding;
        });

        return redirect()->route('qc-finding.project', [
            'project' => $rfi->project_id,
            'buka' => $qcFinding->id,
        ])->with('success', 'RFI dialihkan ke QC Finding. Silakan tentukan tindakan QC untuk Incoming Material.');
    }

    public function update(
        StoreRfiRequest $request,
        string $kategori,
        Rfi $rfi,
        IrfLeadTimeCalculator $leadTimeCalculator
    ): RedirectResponse {
        $category = $this->categoryConfig($kategori);
        abort_unless($rfi->category === $kategori, 404);
        abort_unless($rfi->status === 'draft', 403);
        abort_unless(
            $request->user()->role === 'admin' || $rfi->created_by === $request->user()->id,
            403
        );

        $validated = $request->validated();
        $isDraft = ($request->input('action') === 'draft') && ! empty($category['drafts']);

        $categoryFields = array_merge($category['general_fields'] ?? [], $category['fields'] ?? []);
        $detailKeys = collect($categoryFields)
            ->reject(fn (array $field): bool => $field['name'] === 'scheduled_at')
            ->pluck('name')
            ->all();

        $details = Arr::only($validated['details'] ?? [], $detailKeys);

        if ($isDraft) {
            $hasDate = ! empty($validated['date_inspect']);
            $dateInspect = $hasDate ? $validated['date_inspect'] : ($rfi->date_inspect?->toDateString() ?? Carbon::now('Asia/Jakarta')->toDateString());
            if (! $hasDate) {
                $details['_draft_no_date'] = true;
            } else {
                unset($details['_draft_no_date']);
            }

            $rfi->update([
                'project_id' => $validated['project_id'],
                'details' => $details,
                'date_inspect' => $dateInspect,
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'note' => $validated['note'] ?? null,
            ]);

            return redirect()->route('rfi.category', ['kategori' => $kategori])
                ->with('success', 'Draft RFI berhasil diperbarui.');
        }

        unset($details['_draft_no_date']);

        $submittedAt = Carbon::now('UTC');
        $deadlineAt = $leadTimeCalculator->deadlineFor($submittedAt)->setTimezone('UTC');

        $rfi->update([
            'project_id' => $validated['project_id'],
            'details' => $details,
            'date_inspect' => $validated['date_inspect'],
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'note' => $validated['note'] ?? null,
            'status' => 'open',
            'submitted_at' => $submittedAt,
            'deadline_at' => $deadlineAt,
        ]);

        try {
            app(NotificationDispatcher::class)->rfiSubmitted($rfi, $request->user());
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->route('rfi.category', ['kategori' => $kategori])
            ->with('success', 'RFI berhasil dikirim ke QC.');
    }

    public function destroy(Request $request, string $kategori, Rfi $rfi): RedirectResponse
    {
        $this->categoryConfig($kategori);
        abort_unless($rfi->category === $kategori, 404);
        abort_unless($rfi->status === 'draft', 403);
        abort_unless(
            $request->user()->role === 'admin' || $rfi->created_by === $request->user()->id,
            403
        );

        $rfi->delete();

        return redirect()->route('rfi.category', ['kategori' => $kategori])
            ->with('success', 'Draft RFI berhasil dihapus.');
    }

    public function review(
        ReviewRfiRequest $request,
        string $kategori,
        Rfi $rfi
    ): RedirectResponse {
        $validated = $request->validated();
        $result = RfiResult::from($validated['result']);
        $qcFinding = null;

        DB::transaction(function () use ($request, $kategori, $rfi, $result, $validated, &$qcFinding): void {
            $lockedRfi = Rfi::query()
                ->whereKey($rfi->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedRfi->category === $kategori, 404);

            if ($lockedRfi->status !== 'open' || $lockedRfi->result !== null) {
                throw ValidationException::withMessages([
                    'result' => 'Hasil untuk RFI ini sudah diberikan dan tidak dapat diubah.',
                ]);
            }

            $lockedRfi->result = $result;
            $lockedRfi->qc_note = $validated['qc_note'] ?? null;
            $lockedRfi->status = $result === RfiResult::Accepted ? 'close' : 'nc';
            $lockedRfi->reviewed_by = $request->user()->id;
            $lockedRfi->reviewed_at = Carbon::now('UTC');
            $lockedRfi->save();

            if ($result !== RfiResult::Accepted) {
                if ($lockedRfi->qcFinding()->exists()) {
                    throw ValidationException::withMessages([
                        'result' => 'Temuan QC untuk RFI ini sudah ada dan tidak dapat dibuat ulang.',
                    ]);
                }

                $qcFinding = $lockedRfi->qcFinding()->create([
                    'result' => $result,
                    'finding_list' => $validated['qc_note'],
                    'date_of_finding' => Carbon::now('Asia/Jakarta')->toDateString(),
                    'due_date' => Carbon::now('Asia/Jakarta')->addDay()->toDateString(),
                    'status' => 'open',
                ]);

                // Record rfi_submitted (actor = RFI submitter, occurred_at = when RFI was submitted)
                $rfiSubmitter = $lockedRfi->created_by ? User::find($lockedRfi->created_by) : null;
                FindingActivityRecorder::record(
                    finding: $qcFinding,
                    type: FindingActivity::TYPE_RFI_SUBMITTED,
                    actor: $rfiSubmitter,
                    meta: ['rfi_no' => $lockedRfi->rfi_no],
                    at: $lockedRfi->submitted_at ? Carbon::instance($lockedRfi->submitted_at) : Carbon::now('UTC'),
                );

                // Record finding_created (actor = reviewer)
                FindingActivityRecorder::record(
                    finding: $qcFinding,
                    type: FindingActivity::TYPE_FINDING_CREATED,
                    actor: $request->user(),
                    meta: array_filter([
                        'disposition' => $result->value,
                        'rfi_no' => $lockedRfi->rfi_no,
                    ]),
                    at: Carbon::instance($qcFinding->created_at ?? Carbon::now('UTC')),
                );
            }
        });

        try {
            $rfi->refresh();
            $dispatcher = app(NotificationDispatcher::class);
            if ($result === RfiResult::Accepted) {
                $dispatcher->rfiReviewedOk($rfi, $request->user());
            } else {
                $dispatcher->rfiReviewedNc($rfi, $qcFinding, $request->user());
            }
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->route('rfi.category', ['kategori' => $kategori])
            ->with('success', 'Hasil QC berhasil disimpan.');
    }

    /**
     * @return array{label: string, roles: array<int, string>, submit_roles: array<int, string>, fields: array<int, array<string, mixed>>}
     */
    private function categoryConfig(string $kategori): array
    {
        $category = config("mudalaya.rfi_categories.{$kategori}");

        abort_if($category === null, 404);

        return $category;
    }
}
