<?php

namespace App\Http\Controllers;

use App\Enums\RfiResult;
use App\Http\Requests\StoreFindingSubmissionRequest;
use App\Http\Requests\UpdateQcFindingActionPlanRequest;
use App\Http\Requests\UpdateQcFindingDueDateRequest;
use App\Http\Requests\VerifyFindingSubmissionRequest;
use App\Models\FindingActivity;
use App\Models\FindingEvidence;
use App\Models\Project;
use App\Models\QcFinding;
use App\Models\Rfi;
use App\Models\User;
use App\Services\FindingActivityRecorder;
use App\Services\FindingHistoryRecorder;
use App\Services\NotificationDispatcher;
use App\Services\QcDashboardSummary;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class QcFindingController extends Controller
{
    public function index(Request $request): View
    {
        $overview = QcDashboardSummary::overview($request->user(), [
            'pf' => (string) $request->query('pf', 'semua'),
            'pq' => (string) $request->query('pq', ''),
            'page' => $request->query('page'),
        ]);

        return view('modules.qc-finding', [
            'overview' => $overview,
            'projects' => $overview['projects'],
        ]);
    }

    public function project(Request $request, Project $project): View
    {
        $role = $request->user()->role;
        $allowedCategories = config('mudalaya.findings.view_categories.'.$role, []);

        $allFindings = QcFinding::query()
            ->with([
                'rfi:id,rfi_no,category,details,project_id,status,created_by,reviewed_by,submitted_at,reviewed_at',
                'rfi.createdBy:id,name',
                'rfi.reviewedBy:id,name',
                'histories.actor:id,name',
                'submissions.submittedBy:id,name',
                'submissions.verifiedBy:id,name',
                'submissions.evidences:id,finding_submission_id,original_name,path,mime,size',
                'latestActivity.actor:id,name,role',
                'activities.actor:id,name,role',
            ])
            ->whereHas('rfi', function (Builder $query) use ($project, $allowedCategories): void {
                $query->where('project_id', $project->id)
                    ->whereIn('category', $allowedCategories);
            })
            ->orderByDesc('date_of_finding')
            ->orderByDesc('id')
            ->get();

        // Validate rfi_baru parameter (Category A RFI → show form at top)
        $rfiBaru = null;
        $rfiBaruError = null;
        $rfiBaru_id = (int) $request->query('rfi_baru', 0);
        if ($rfiBaru_id > 0) {
            if (! in_array($role, ['admin', 'qc_qa'], true)) {
                $rfiBaruError = 'Anda tidak memiliki izin untuk menentukan tindakan QC Incoming Material.';
            } else {
                $candidateRfi = Rfi::query()
                    ->where('id', $rfiBaru_id)
                    ->where('project_id', $project->id)
                    ->where('category', 'A')
                    ->where('status', 'open')
                    ->first();
                if (! $candidateRfi) {
                    $rfiBaruError = 'RFI tidak ditemukan, bukan kategori A, bukan milik project ini, atau sudah tidak berstatus open.';
                } elseif ($candidateRfi->qcFinding()->exists()) {
                    $rfiBaruError = 'RFI ini sudah memiliki temuan QC.';
                } else {
                    $rfiBaru = $candidateRfi;
                }
            }
        }

        abort_if($allFindings->isEmpty() && $rfiBaru === null, 404);

        $isAdmin = $role === 'admin';
        $canUpdateDueDate = in_array($role, ['admin', 'qc_qa'], true);
        $actionPlanRolesConfig = config('mudalaya.findings.action_plan_roles', []);
        $submissionRolesConfig = config('mudalaya.findings.submission_roles', []);

        // Categories this role can open in RFI (for linking rfi_no)
        $rfiAccessCategories = collect(config('mudalaya.rfi_categories'))
            ->filter(fn (array $cat): bool => in_array($role, $cat['roles'], true))
            ->keys()
            ->all();

        $allFindings->each(function (QcFinding $finding) use ($isAdmin, $role, $canUpdateDueDate, $rfiAccessCategories, $actionPlanRolesConfig, $submissionRolesConfig): void {
            $category = $finding->rfi?->category;
            $allowedActionRoles = $actionPlanRolesConfig[$category] ?? [];
            $allowedSubmissionRoles = $submissionRolesConfig[$category] ?? [];

            $canActOnFinding = $isAdmin || in_array($role, $allowedActionRoles, true);
            $canSubmitFinding = $isAdmin || in_array($role, $allowedSubmissionRoles, true);

            $finding->setAttribute(
                'can_edit_action_plan',
                $finding->status === 'open' && $canActOnFinding
            );
            $finding->setAttribute(
                'can_submit',
                $finding->status !== 'closed' && $canSubmitFinding
            );
            $finding->setAttribute('can_update_due_date', $canUpdateDueDate);
            $finding->setAttribute(
                'can_link_rfi',
                in_array($category, $rfiAccessCategories, true)
            );
        });

        $chronologicalGrouped = $allFindings
            ->sort(function (QcFinding $a, QcFinding $b): int {
                $dateA = $a->date_of_finding?->toDateString() ?? '';
                $dateB = $b->date_of_finding?->toDateString() ?? '';
                if ($dateA !== $dateB) {
                    return $dateA <=> $dateB;
                }

                return $a->id <=> $b->id;
            })
            ->groupBy(fn (QcFinding $f): string => (string) ($f->rfi?->category ?? ''));

        foreach ($chronologicalGrouped as $categoryFindings) {
            $prevList = [];
            $seq = 1;
            foreach ($categoryFindings as $finding) {
                $finding->setAttribute('process_finding_number', $seq);
                $finding->setAttribute('previous_process_findings', $prevList);

                $prevList[] = [
                    'id' => $finding->id,
                    'rfi_no' => $finding->rfi?->rfi_no,
                    'finding_list' => $finding->finding_list,
                    'date_of_finding' => $finding->date_of_finding,
                    'status' => $finding->status,
                ];
                $seq++;
            }
        }

        $selectedProcess = (string) ($request->query('proses') ?? $request->query('kategori') ?? $request->query('category') ?? 'semua');

        $processOptions = collect(config('mudalaya.rfi_categories', []))
            ->filter(fn ($config, $code): bool => in_array($code, $allowedCategories, true))
            ->all();

        $processFiltered = $allFindings;
        if ($selectedProcess !== 'semua') {
            $processFiltered = $allFindings->filter(function (QcFinding $f) use ($selectedProcess): bool {
                return strtoupper($f->rfi?->category ?? '') === strtoupper($selectedProcess);
            })->values();
        }

        $statusCounts = [
            'semua' => $processFiltered->count(),
            'open' => $processFiltered->where('status', 'open')->count(),
            'waiting_verification' => $processFiltered->where('status', 'waiting_verification')->count(),
            'closed' => $processFiltered->where('status', 'closed')->count(),
            'terlambat' => $processFiltered->filter(fn (QcFinding $f) => $f->isOverdue() || $f->isDueToday())->count(),
        ];

        $selectedStatus = (string) $request->query('status', 'semua');
        $openFindingId = (int) $request->query('buka', 0);

        $findings = match ($selectedStatus) {
            'open' => $processFiltered->where('status', 'open')->values(),
            'waiting_verification' => $processFiltered->where('status', 'waiting_verification')->values(),
            'closed' => $processFiltered->where('status', 'closed')->values(),
            'terlambat', 'overdue', 'urgent' => $processFiltered->filter(fn (QcFinding $f) => $f->isOverdue() || $f->isDueToday())->values(),
            default => $processFiltered->values(),
        };

        $projectOpenCount = $statusCounts['open'];
        $projectWaitingCount = $statusCounts['waiting_verification'];
        $projectClosedCount = $statusCounts['closed'];
        $allClosed = ($projectOpenCount === 0 && $projectWaitingCount === 0 && $projectClosedCount > 0);
        $nearestDueDate = $processFiltered->where('status', '!=', 'closed')->whereNotNull('due_date')->min('due_date');

        // Temuan terpilih: dari ?buka={id} bila valid dan boleh dilihat role;
        // jika tidak, temuan pertama yang terbuka (punya aksi tertunda); jika tidak ada, temuan pertama di daftar.
        $selectedFinding = null;
        if ($openFindingId > 0) {
            $selectedFinding = $findings->firstWhere('id', $openFindingId)
                ?? $processFiltered->firstWhere('id', $openFindingId)
                ?? $allFindings->firstWhere('id', $openFindingId);
        }

        if ($selectedFinding === null) {
            $selectedFinding = $findings->first(function (QcFinding $f) use ($role, $isAdmin): bool {
                return ($f->can_edit_action_plan && blank($f->action_plan))
                    || ($f->status === 'waiting_verification' && ($isAdmin || $role === 'qc_qa'))
                    || ($f->status === 'open' && $f->can_submit && filled($f->action_plan));
            });

            $selectedFinding ??= $findings->firstWhere('status', '!=', 'closed');
            $selectedFinding ??= $findings->first();
        }

        $activities = collect();
        $activitiesTotalCount = 0;
        if ($selectedFinding !== null) {
            $activitiesQuery = $selectedFinding->activities()
                ->with('actor:id,name,role');

            $activitiesTotalCount = $activitiesQuery->count();
            $activities = $activitiesQuery->take(50)->get();
        }

        $totalActivities = $activitiesTotalCount;

        return view('modules.qc-finding-project', compact(
            'project',
            'allFindings',
            'findings',
            'statusCounts',
            'selectedStatus',
            'selectedProcess',
            'processOptions',
            'openFindingId',
            'projectOpenCount',
            'projectWaitingCount',
            'projectClosedCount',
            'allClosed',
            'nearestDueDate',
            'selectedFinding',
            'activities',
            'activitiesTotalCount',
            'totalActivities',
            'rfiBaru',
            'rfiBaruError'
        ));
    }

    public function updateActionPlan(
        UpdateQcFindingActionPlanRequest $request,
        QcFinding $finding
    ): RedirectResponse {
        $projectId = DB::transaction(function () use ($request, $finding): int {
            $lockedFinding = QcFinding::query()
                ->with('rfi:id,project_id')
                ->whereKey($finding->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedFinding->status === 'open', 403);

            $oldActionPlan = $lockedFinding->action_plan;
            $newActionPlan = $request->validated('action_plan');

            $lockedFinding->action_plan = $newActionPlan;
            $lockedFinding->action_plan_by = $request->user()->id;
            $lockedFinding->action_plan_at = Carbon::now('UTC');
            $lockedFinding->save();

            FindingHistoryRecorder::record(
                finding: $lockedFinding,
                event: 'action_plan_updated',
                actor: $request->user(),
                old: $oldActionPlan,
                new: $newActionPlan,
            );

            FindingActivityRecorder::record(
                finding: $lockedFinding,
                type: FindingActivity::TYPE_ACTION_PLAN_SAVED,
                actor: $request->user(),
                meta: ['first' => $oldActionPlan === null],
            );

            return $lockedFinding->rfi->project_id;
        });

        return redirect()->route('qc-finding.project', ['project' => $projectId])
            ->with('success', 'Action plan berhasil disimpan.');
    }

    public function storeSubmission(
        StoreFindingSubmissionRequest $request,
        QcFinding $finding
    ): RedirectResponse {
        $validated = $request->validated();
        $storedPaths = [];
        $submission = null;

        try {
            $projectId = DB::transaction(function () use ($request, $finding, $validated, &$storedPaths, &$submission): int {
                $lockedFinding = QcFinding::query()
                    ->whereKey($finding->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedFinding->status !== 'open') {
                    throw ValidationException::withMessages([
                        'submission' => 'Temuan ini tidak sedang terbuka untuk pengiriman Re-Inspect.',
                    ]);
                }

                if (blank($lockedFinding->action_plan)) {
                    throw ValidationException::withMessages([
                        'action_plan' => 'Action plan wajib diisi sebelum mengirim Re-Inspect.',
                    ]);
                }

                $submission = $lockedFinding->submissions()->create([
                    'round_no' => ($lockedFinding->submissions()->max('round_no') ?? 0) + 1,
                    'note' => $validated['note'],
                    'submitted_by' => $request->user()->id,
                    'submitted_at' => Carbon::now('UTC'),
                ]);

                foreach ($validated['evidence'] as $file) {
                    $path = $file->store("evidence/{$lockedFinding->id}", 'local');

                    if ($path === false) {
                        throw new RuntimeException('Evidence file could not be stored on the private local disk.');
                    }

                    $storedPaths[] = $path;
                    $submission->evidences()->create([
                        'original_name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'mime' => $file->getMimeType() ?? 'application/octet-stream',
                        'size' => $file->getSize(),
                    ]);
                }

                $lockedFinding->status = 'waiting_verification';

                // Mentor rule: reset due_date to today + 1 day in Asia/Jakarta
                $oldDueDate = $lockedFinding->due_date?->format('Y-m-d');
                $newDueDate = Carbon::now('Asia/Jakarta')->addDay()->toDateString();
                $lockedFinding->due_date = $newDueDate;
                $lockedFinding->save();

                FindingHistoryRecorder::record(
                    finding: $lockedFinding,
                    event: 'reinspect_submitted',
                    actor: $request->user(),
                    meta: [
                        'round_no' => $submission->round_no,
                    ],
                );

                FindingActivityRecorder::record(
                    finding: $lockedFinding,
                    type: FindingActivity::TYPE_REINSPECT_SUBMITTED,
                    actor: $request->user(),
                    meta: ['round' => $submission->round_no, 'submission_id' => $submission->id],
                );

                FindingHistoryRecorder::record(
                    finding: $lockedFinding,
                    event: 'due_date_changed',
                    actor: $request->user(),
                    old: $oldDueDate,
                    new: $newDueDate,
                    meta: ['auto_reset' => true],
                );

                FindingActivityRecorder::record(
                    finding: $lockedFinding,
                    type: FindingActivity::TYPE_DUE_DATE_CHANGED,
                    actor: $request->user(),
                    meta: ['from' => $oldDueDate, 'to' => $newDueDate, 'auto_reset' => true],
                    note: 'Due date direset otomatis',
                );

                return $lockedFinding->rfi()->value('project_id');
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $exception;
        }

        try {
            $finding->refresh();
            app(NotificationDispatcher::class)->findingReinspectSubmitted($finding, $submission, $request->user());
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->route('qc-finding.project', ['project' => $projectId])
            ->with('success', 'Re-Inspect berhasil dikirim.');
    }

    public function verifySubmission(
        VerifyFindingSubmissionRequest $request,
        QcFinding $finding
    ): RedirectResponse {
        $validated = $request->validated();
        $submission = null;
        $verificationResult = null;

        $projectId = DB::transaction(function () use ($request, $finding, $validated, &$submission, &$verificationResult): int {
            $lockedFinding = QcFinding::query()
                ->whereKey($finding->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedFinding->status !== 'waiting_verification') {
                throw ValidationException::withMessages([
                    'result' => 'Temuan ini tidak sedang menunggu verifikasi.',
                ]);
            }

            $submission = $lockedFinding->submissions()
                ->whereNull('verification_result')
                ->orderByDesc('round_no')
                ->lockForUpdate()
                ->first();

            if ($submission === null) {
                throw ValidationException::withMessages([
                    'result' => 'Pengiriman Re-Inspect yang menunggu verifikasi tidak ditemukan.',
                ]);
            }

            $verificationResult = $validated['result'];
            $submission->verification_result = $validated['result'];
            $submission->verification_note = $validated['verification_note'] ?? null;
            $submission->verified_by = $request->user()->id;
            $submission->verified_at = Carbon::now('UTC');
            $submission->save();

            if ($validated['result'] === 'ok') {
                $lockedFinding->status = 'closed';
                $lockedFinding->date_of_close = Carbon::now('Asia/Jakarta')->toDateString();
                $lockedFinding->save();

                $this->closeRelatedRfi($lockedFinding);

                FindingHistoryRecorder::record(
                    finding: $lockedFinding,
                    event: 'verified_ok',
                    actor: $request->user(),
                    meta: array_filter([
                        'round_no' => $submission->round_no,
                        'verification_note' => $submission->verification_note,
                    ], fn ($val) => $val !== null),
                );

                FindingActivityRecorder::record(
                    finding: $lockedFinding,
                    type: FindingActivity::TYPE_REINSPECT_VERIFIED,
                    actor: $request->user(),
                    meta: [
                        'round' => $submission->round_no,
                        'submission_id' => $submission->id,
                        'result' => 'ok',
                    ],
                    note: $submission->verification_note,
                );
            } else {
                $oldDueDate = $lockedFinding->due_date?->format('Y-m-d');
                $newDueDate = Carbon::now('Asia/Jakarta')->addDay()->toDateString();

                $lockedFinding->status = 'open';
                $lockedFinding->due_date = $newDueDate;
                $lockedFinding->save();

                FindingHistoryRecorder::record(
                    finding: $lockedFinding,
                    event: 'verified_rejected',
                    actor: $request->user(),
                    old: $oldDueDate,
                    new: $newDueDate,
                    meta: array_filter([
                        'round_no' => $submission->round_no,
                        'verification_note' => $submission->verification_note,
                        'due_date' => $newDueDate,
                    ], fn ($val) => $val !== null),
                );

                FindingActivityRecorder::record(
                    finding: $lockedFinding,
                    type: FindingActivity::TYPE_REINSPECT_VERIFIED,
                    actor: $request->user(),
                    meta: [
                        'round' => $submission->round_no,
                        'submission_id' => $submission->id,
                        'result' => 'not_ok',
                    ],
                    note: $submission->verification_note,
                );

                if ($oldDueDate !== $newDueDate) {
                    FindingActivityRecorder::record(
                        finding: $lockedFinding,
                        type: FindingActivity::TYPE_DUE_DATE_CHANGED,
                        actor: $request->user(),
                        meta: ['from' => $oldDueDate, 'to' => $newDueDate],
                    );
                }
            }

            return $lockedFinding->rfi()->value('project_id');
        });

        try {
            $finding->refresh();
            $dispatcher = app(NotificationDispatcher::class);
            if ($verificationResult === 'ok') {
                $dispatcher->findingVerifiedOk($finding, $submission, $request->user());
            } else {
                $dispatcher->findingVerifiedRejected($finding, $submission, $request->user());
            }
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->route('qc-finding.project', ['project' => $projectId])
            ->with('success', 'Verifikasi Re-Inspect berhasil disimpan.');
    }

    public function downloadEvidence(Request $request, FindingEvidence $evidence): StreamedResponse
    {
        $evidence->loadMissing('submission.qcFinding.rfi');
        $role = $request->user()->role;
        $category = $evidence->submission->qcFinding->rfi->category;
        $allowedCategories = config('mudalaya.findings.view_categories.'.$role, []);

        abort_unless(in_array($category, $allowedCategories, true), 403);
        abort_unless(Storage::disk('local')->exists($evidence->path), 404);

        return Storage::disk('local')->download(
            $evidence->path,
            $evidence->original_name,
            ['X-Content-Type-Options' => 'nosniff']
        );
    }

    public function updateDueDate(
        UpdateQcFindingDueDateRequest $request,
        QcFinding $finding
    ): RedirectResponse {
        $changed = false;

        DB::transaction(function () use ($finding, $request, &$changed): void {
            $lockedFinding = QcFinding::query()
                ->whereKey($finding->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newDueDate = $request->validated('due_date');
            $oldDueDate = $lockedFinding->due_date?->format('Y-m-d');

            if ($oldDueDate !== $newDueDate) {
                $lockedFinding->due_date = $newDueDate;
                $lockedFinding->save();
                $changed = true;

                FindingHistoryRecorder::record(
                    finding: $lockedFinding,
                    event: 'due_date_changed',
                    actor: $request->user(),
                    old: $oldDueDate,
                    new: $newDueDate,
                );

                FindingActivityRecorder::record(
                    finding: $lockedFinding,
                    type: FindingActivity::TYPE_DUE_DATE_CHANGED,
                    actor: $request->user(),
                    meta: ['from' => $oldDueDate, 'to' => $newDueDate],
                );
            }
        });

        if ($changed) {
            try {
                $finding->refresh();
                app(NotificationDispatcher::class)->findingDueDateChanged($finding, $request->user());
            } catch (Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('qc-finding.project', ['project' => $finding->rfi->project_id])
            ->with('success', 'Due date berhasil diperbarui.');
    }

    public function actionIncomingMaterial(Request $request, QcFinding $finding): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, ['admin', 'qc_qa'], true), 403);
        $finding->loadMissing('rfi');
        abort_unless($finding->rfi?->category === 'A', 400);

        $parsed = $this->validateAndParseIncomingMaterial($request);
        if ($parsed instanceof RedirectResponse) {
            return $parsed;
        }

        [$rows, $materialString, $materialDetails, $reasonString, $resultEnum, $isReject] = $parsed;

        $projectId = DB::transaction(function () use ($finding, $rows, $materialString, $materialDetails, $reasonString, $resultEnum, $request): int {
            $lockedFinding = QcFinding::query()->whereKey($finding->id)->lockForUpdate()->firstOrFail();
            $lockedRfi = $lockedFinding->rfi()->lockForUpdate()->firstOrFail();

            abort_if(
                $lockedFinding->status === 'closed' || $lockedRfi->status === 'close' || in_array($lockedRfi->result, [RfiResult::UseAsIs, RfiResult::Reject], true),
                400,
                'Temuan sudah ditutup atau RFI sudah memiliki hasil akhir.'
            );

            $this->applyIncomingMaterialToFindingAndRfi(
                $lockedFinding,
                $lockedRfi,
                $rows,
                $materialString,
                $materialDetails,
                $reasonString,
                $resultEnum,
                $request->user(),
                $request->input('result')
            );

            return $lockedRfi->project_id;
        });

        if ($isReject) {
            try {
                $finding->refresh();
                app(NotificationDispatcher::class)->warehouseFindingRejected($finding, $request->user());
            } catch (Throwable $e) {
                report($e);
            }
        } elseif ($resultEnum === RfiResult::UseAsIs) {
            try {
                $finding->refresh();
                app(NotificationDispatcher::class)->warehouseFindingUseAsIs($finding, $request->user());
            } catch (Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('qc-finding.project', ['project' => $projectId])
            ->with('success', 'Tindakan QC Incoming Material berhasil disimpan'.($isReject ? ' dan notifikasi dikirim ke Warehouse.' : '.'));
    }

    public function storeIncomingMaterial(Request $request, Rfi $rfi): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, ['admin', 'qc_qa'], true), 403);
        abort_unless($rfi->category === 'A', 400);
        abort_unless($rfi->status === 'open' && ! $rfi->qcFinding()->exists() && $rfi->result === null, 400);

        $parsed = $this->validateAndParseIncomingMaterial($request);
        if ($parsed instanceof RedirectResponse) {
            return $parsed;
        }

        [$rows, $materialString, $materialDetails, $reasonString, $resultEnum, $isReject] = $parsed;

        $createdFinding = null;
        $projectId = DB::transaction(function () use ($rfi, $rows, $materialString, $materialDetails, $reasonString, $resultEnum, $request, &$createdFinding): int {
            $lockedRfi = Rfi::query()->whereKey($rfi->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $lockedRfi->category === 'A' && $lockedRfi->status === 'open' && ! $lockedRfi->qcFinding()->exists() && $lockedRfi->result === null,
                400,
                'Temuan sudah ditutup atau RFI sudah memiliki hasil akhir.'
            );

            $nowJakarta = Carbon::now('Asia/Jakarta');
            $nowUtc = Carbon::now('UTC');

            $finding = QcFinding::query()->create([
                'rfi_id' => $lockedRfi->id,
                'result' => $resultEnum,
                'finding_list' => $reasonString,
                'action_plan' => $materialString,
                'action_plan_by' => $request->user()->id,
                'action_plan_at' => $nowUtc,
                'date_of_finding' => $nowJakarta->toDateString(),
                'due_date' => $nowJakarta->copy()->addDay()->toDateString(),
                'status' => 'open',
                'date_of_close' => null,
            ]);

            $createdFinding = $finding;

            $rfiSubmitter = $lockedRfi->created_by ? User::find($lockedRfi->created_by) : null;
            FindingActivityRecorder::record(
                finding: $finding,
                type: FindingActivity::TYPE_RFI_SUBMITTED,
                actor: $rfiSubmitter,
                meta: ['rfi_no' => $lockedRfi->rfi_no],
                at: $lockedRfi->submitted_at ? Carbon::instance($lockedRfi->submitted_at) : $nowUtc,
            );
            FindingActivityRecorder::record(
                finding: $finding,
                type: FindingActivity::TYPE_FINDING_CREATED,
                actor: $request->user(),
                meta: array_filter([
                    'disposition' => $resultEnum->value,
                    'rfi_no' => $lockedRfi->rfi_no,
                ]),
                at: Carbon::instance($finding->created_at ?? $nowUtc),
            );

            $this->applyIncomingMaterialToFindingAndRfi(
                $finding,
                $lockedRfi,
                $rows,
                $materialString,
                $materialDetails,
                $reasonString,
                $resultEnum,
                $request->user(),
                $request->input('result')
            );

            return $lockedRfi->project_id;
        });

        if ($isReject && $createdFinding) {
            try {
                $createdFinding->refresh();
                app(NotificationDispatcher::class)->warehouseFindingRejected($createdFinding, $request->user());
            } catch (Throwable $e) {
                report($e);
            }
        } elseif ($resultEnum === RfiResult::UseAsIs && $createdFinding) {
            try {
                $createdFinding->refresh();
                app(NotificationDispatcher::class)->warehouseFindingUseAsIs($createdFinding, $request->user());
            } catch (Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('qc-finding.project', ['project' => $projectId])
            ->with('success', 'Tindakan QC Incoming Material berhasil disimpan'.($isReject ? ' dan notifikasi dikirim ke Warehouse.' : '.'));
    }

    private function validateAndParseIncomingMaterial(Request $request): array|RedirectResponse
    {
        $request->validate([
            'result' => ['required', Rule::in(['use_as_is', 'reject'])],
            'items' => ['nullable', 'array'],
            'items.*.materials' => ['nullable', 'array'],
            'items.*.reason' => ['nullable', 'string', 'max:5000'],
            'material' => ['nullable'],
            'reason_of_finding' => ['nullable', 'string', 'max:5000'],
        ]);

        $rows = [];

        if ($request->has('items') && is_array($request->input('items'))) {
            foreach ($request->input('items') as $rawItem) {
                $mats = is_array($rawItem['materials'] ?? null)
                    ? array_values(array_filter(array_map('trim', $rawItem['materials'])))
                    : (filled($rawItem['materials'] ?? null) ? [trim($rawItem['materials'])] : []);

                if (empty($mats) && filled($rawItem['material_text'] ?? null)) {
                    $mats = [trim($rawItem['material_text'])];
                }

                $reason = trim((string) ($rawItem['reason'] ?? ''));

                if (! empty($mats) || $reason !== '') {
                    $rows[] = [
                        'materials' => $mats,
                        'reason' => $reason,
                    ];
                }
            }
        }

        if (empty($rows)) {
            $legacyMats = is_array($request->input('material'))
                ? array_values(array_filter(array_map('trim', $request->input('material'))))
                : (filled($request->input('material')) ? [trim($request->input('material'))] : []);
            $legacyReason = trim((string) $request->input('reason_of_finding'));

            if (! empty($legacyMats) || $legacyReason !== '') {
                $rows[] = [
                    'materials' => $legacyMats,
                    'reason' => $legacyReason,
                ];
            }
        }

        if (empty($rows)) {
            return back()->withErrors(['material' => 'Minimal satu data material dan reason of finding wajib diisi.'])->withInput();
        }

        foreach ($rows as $idx => $r) {
            if (empty($r['materials'])) {
                return back()->withErrors(['material' => 'Material pada baris ke-'.($idx + 1).' wajib diisi minimal satu.'])->withInput();
            }
            if ($r['reason'] === '') {
                return back()->withErrors(['reason_of_finding' => 'Reason of finding pada baris ke-'.($idx + 1).' wajib diisi.'])->withInput();
            }
        }

        $allMaterials = [];
        foreach ($rows as $r) {
            $allMaterials = array_merge($allMaterials, $r['materials']);
        }
        $allMaterials = array_values(array_unique($allMaterials));
        $materialString = implode(', ', $allMaterials);
        $materialDetails = count($allMaterials) === 1 ? $allMaterials[0] : $allMaterials;

        if (count($rows) === 1) {
            $reasonString = $rows[0]['reason'];
        } else {
            $reasonLines = [];
            foreach ($rows as $i => $r) {
                $reasonLines[] = ($i + 1).'. '.$r['reason'];
            }
            $reasonString = implode("\n", $reasonLines);
        }

        $resultEnum = RfiResult::from($request->input('result'));
        $isReject = ($request->input('result') === 'reject');

        return [$rows, $materialString, $materialDetails, $reasonString, $resultEnum, $isReject];
    }

    private function applyIncomingMaterialToFindingAndRfi(
        QcFinding $finding,
        Rfi $rfi,
        array $rows,
        string $materialString,
        mixed $materialDetails,
        string $reasonString,
        RfiResult $resultEnum,
        User $actor,
        string $resultInput
    ): void {
        abort_if(
            $finding->status === 'closed' || $rfi->status === 'close' || in_array($rfi->result, [RfiResult::UseAsIs, RfiResult::Reject], true),
            400,
            'Temuan sudah ditutup atau RFI sudah memiliki hasil akhir.'
        );

        $finding->finding_list = $reasonString;
        $finding->action_plan = $materialString;
        $finding->action_plan_by = $actor->id;
        $finding->action_plan_at = Carbon::now('UTC');
        $finding->result = $resultEnum;
        $finding->status = 'closed';
        $finding->date_of_close = Carbon::now('Asia/Jakarta')->toDateString();
        $finding->save();

        FindingHistoryRecorder::record(
            finding: $finding,
            event: 'closed_incoming',
            actor: $actor,
            meta: [
                'result' => $resultInput,
            ],
        );

        FindingActivityRecorder::record(
            finding: $finding,
            type: FindingActivity::TYPE_ACTION_PLAN_SAVED,
            actor: $actor,
            meta: ['first' => true],
        );

        $details = is_array($rfi->details)
            ? $rfi->details
            : (json_decode($rfi->details ?? '', true) ?: []);
        $details['material'] = $materialDetails;
        $details['incoming_findings'] = $rows;
        $rfi->details = $details;
        $rfi->result = $resultEnum;
        $rfi->qc_note = $reasonString;
        $rfi->status = ($resultEnum === RfiResult::UseAsIs) ? 'close' : 'nc';
        $rfi->reviewed_by ??= $actor->id;
        $rfi->reviewed_at ??= Carbon::now('UTC');
        $rfi->save();
    }

    private function closeRelatedRfi(QcFinding $finding): void
    {
        $rfi = $finding->rfi()->lockForUpdate()->firstOrFail();
        $rfi->status = 'close';
        $rfi->save();
    }
}
