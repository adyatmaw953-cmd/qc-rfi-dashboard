<?php

namespace App\Services;

use App\Models\FindingSubmission;
use App\Models\QcFinding;
use App\Models\Rfi;
use App\Models\User;
use App\Notifications\FindingCreated;
use App\Notifications\FindingDueDateChanged;
use App\Notifications\FindingReinspectSubmitted;
use App\Notifications\FindingVerifiedOk;
use App\Notifications\FindingVerifiedRejected;
use App\Notifications\RfiNc;
use App\Notifications\RfiReviewedOk;
use App\Notifications\RfiSubmitted;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

class NotificationDispatcher
{
    public function rfiSubmitted(Rfi $rfi, User $actor): void
    {
        try {
            $eventKey = 'rfi.submitted';
            $rfi->loadMissing('project');
            $recipients = $this->resolveRecipients($eventKey, $rfi, null, null, $actor);
            $recipients = $this->filterRfiAccess($recipients, $rfi->category);

            if ($recipients->isEmpty()) {
                return;
            }

            $payload = [
                'type' => $eventKey,
                'title' => 'RFI Perlu Direview',
                'body' => "RFI {$rfi->rfi_no} telah diajukan oleh {$actor->name} dan menunggu review.",
                'url' => route('rfi.category', ['kategori' => $rfi->category]),
                'rfi_id' => $rfi->id,
                'rfi_no' => $rfi->rfi_no,
                'finding_id' => null,
                'category' => $rfi->category,
                'project_name' => $rfi->project?->project_name ?? '',
                'actor_name' => $actor->name,
                'actor_role' => $actor->role,
            ];

            $this->sendNotification($recipients, new RfiSubmitted($payload));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function rfiReviewedOk(Rfi $rfi, User $actor): void
    {
        try {
            $eventKey = 'rfi.reviewed_ok';
            $rfi->loadMissing('project');
            $recipients = $this->resolveRecipients($eventKey, $rfi, null, null, $actor);
            $recipients = $this->filterRfiAccess($recipients, $rfi->category);

            if ($recipients->isEmpty()) {
                return;
            }

            $payload = [
                'type' => $eventKey,
                'title' => 'RFI Selesai (OK)',
                'body' => "RFI {$rfi->rfi_no} telah direview oleh {$actor->name} dengan hasil OK.",
                'url' => route('rfi.category', ['kategori' => $rfi->category]),
                'rfi_id' => $rfi->id,
                'rfi_no' => $rfi->rfi_no,
                'finding_id' => null,
                'category' => $rfi->category,
                'project_name' => $rfi->project?->project_name ?? '',
                'actor_name' => $actor->name,
                'actor_role' => $actor->role,
            ];

            $this->sendNotification($recipients, new RfiReviewedOk($payload));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function rfiReviewedNc(Rfi $rfi, ?QcFinding $finding, User $actor): void
    {
        try {
            $eventKey = 'rfi.reviewed_nc';
            $rfi->loadMissing('project');
            $candidates = $this->resolveRecipients($eventKey, $rfi, $finding, null, $actor);

            // Dedupe per user: satu user hanya menerima satu notifikasi per kejadian.
            // Pada kejadian NC, user Production menerima FindingCreated ("isi action plan"),
            // sedangkan pembuat RFI non-Production menerima RfiNc.
            $productionUsers = collect();
            $nonProductionCreators = collect();

            foreach ($candidates as $user) {
                if ($user->role === 'production') {
                    if ($this->hasFindingAccess($user, $rfi->category)) {
                        $productionUsers->push($user);
                    }
                } else {
                    if ($this->hasRfiAccess($user, $rfi->category)) {
                        $nonProductionCreators->push($user);
                    }
                }
            }

            if ($productionUsers->isNotEmpty()) {
                $findingPayload = [
                    'type' => $eventKey,
                    'title' => 'Temuan NC Baru',
                    'body' => "RFI {$rfi->rfi_no} dinyatakan NC. Silakan isi action plan.",
                    'url' => route('qc-finding.project', ['project' => $rfi->project_id]),
                    'rfi_id' => $rfi->id,
                    'rfi_no' => $rfi->rfi_no,
                    'finding_id' => $finding?->id,
                    'category' => $rfi->category,
                    'project_name' => $rfi->project?->project_name ?? '',
                    'actor_name' => $actor->name,
                    'actor_role' => $actor->role,
                ];

                $this->sendNotification($productionUsers, new FindingCreated($findingPayload));
            }

            if ($nonProductionCreators->isNotEmpty()) {
                $ncPayload = [
                    'type' => $eventKey,
                    'title' => 'RFI Dinyatakan NC',
                    'body' => "RFI {$rfi->rfi_no} dinyatakan NC oleh {$actor->name}.",
                    'url' => route('rfi.category', ['kategori' => $rfi->category]),
                    'rfi_id' => $rfi->id,
                    'rfi_no' => $rfi->rfi_no,
                    'finding_id' => $finding?->id,
                    'category' => $rfi->category,
                    'project_name' => $rfi->project?->project_name ?? '',
                    'actor_name' => $actor->name,
                    'actor_role' => $actor->role,
                ];

                $this->sendNotification($nonProductionCreators, new RfiNc($ncPayload));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function findingReinspectSubmitted(QcFinding $finding, FindingSubmission $submission, User $actor): void
    {
        try {
            $eventKey = 'finding.reinspect_submitted';
            $finding->loadMissing('rfi.project');
            $rfi = $finding->rfi;

            $recipients = $this->resolveRecipients($eventKey, $rfi, $finding, $submission, $actor);
            $recipients = $this->filterFindingAccess($recipients, $rfi->category);

            if ($recipients->isEmpty()) {
                return;
            }

            $payload = [
                'type' => $eventKey,
                'title' => 'Re-Inspect Diajukan',
                'body' => "Re-Inspect putaran {$submission->round_no} untuk RFI {$rfi->rfi_no} telah diajukan oleh {$actor->name}.",
                'url' => route('qc-finding.project', ['project' => $rfi->project_id]),
                'rfi_id' => $rfi->id,
                'rfi_no' => $rfi->rfi_no,
                'finding_id' => $finding->id,
                'category' => $rfi->category,
                'project_name' => $rfi->project?->project_name ?? '',
                'actor_name' => $actor->name,
                'actor_role' => $actor->role,
            ];

            $this->sendNotification($recipients, new FindingReinspectSubmitted($payload));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function findingVerifiedOk(QcFinding $finding, FindingSubmission $submission, User $actor): void
    {
        try {
            $eventKey = 'finding.verified_ok';
            $finding->loadMissing('rfi.project');
            $rfi = $finding->rfi;

            $recipients = $this->resolveRecipients($eventKey, $rfi, $finding, $submission, $actor);
            $recipients = $this->filterFindingAccess($recipients, $rfi->category);

            if ($recipients->isEmpty()) {
                return;
            }

            $payload = [
                'type' => $eventKey,
                'title' => 'Re-Inspect Terverifikasi (OK)',
                'body' => "Re-Inspect untuk RFI {$rfi->rfi_no} diverifikasi OK oleh {$actor->name} dan temuan telah ditutup.",
                'url' => route('qc-finding.project', ['project' => $rfi->project_id]),
                'rfi_id' => $rfi->id,
                'rfi_no' => $rfi->rfi_no,
                'finding_id' => $finding->id,
                'category' => $rfi->category,
                'project_name' => $rfi->project?->project_name ?? '',
                'actor_name' => $actor->name,
                'actor_role' => $actor->role,
            ];

            $this->sendNotification($recipients, new FindingVerifiedOk($payload));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function findingVerifiedRejected(QcFinding $finding, FindingSubmission $submission, User $actor): void
    {
        try {
            $eventKey = 'finding.verified_rejected';
            $finding->loadMissing('rfi.project');
            $rfi = $finding->rfi;

            $recipients = $this->resolveRecipients($eventKey, $rfi, $finding, $submission, $actor);
            $recipients = $this->filterFindingAccess($recipients, $rfi->category);

            if ($recipients->isEmpty()) {
                return;
            }

            $payload = [
                'type' => $eventKey,
                'title' => 'Re-Inspect Ditolak',
                'body' => "Re-Inspect untuk RFI {$rfi->rfi_no} dinyatakan Belum OK oleh {$actor->name}.",
                'url' => route('qc-finding.project', ['project' => $rfi->project_id]),
                'rfi_id' => $rfi->id,
                'rfi_no' => $rfi->rfi_no,
                'finding_id' => $finding->id,
                'category' => $rfi->category,
                'project_name' => $rfi->project?->project_name ?? '',
                'actor_name' => $actor->name,
                'actor_role' => $actor->role,
            ];

            $this->sendNotification($recipients, new FindingVerifiedRejected($payload));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function findingDueDateChanged(QcFinding $finding, User $actor): void
    {
        try {
            $eventKey = 'finding.due_date_changed';
            $finding->loadMissing('rfi.project');
            $rfi = $finding->rfi;

            $recipients = $this->resolveRecipients($eventKey, $rfi, $finding, null, $actor);
            $recipients = $this->filterFindingAccess($recipients, $rfi->category);

            if ($recipients->isEmpty()) {
                return;
            }

            $formattedDate = $finding->due_date?->format('d M Y') ?? '—';

            $payload = [
                'type' => $eventKey,
                'title' => 'Due Date Diperbarui',
                'body' => "Due date temuan RFI {$rfi->rfi_no} diubah menjadi {$formattedDate} oleh {$actor->name}.",
                'url' => route('qc-finding.project', ['project' => $rfi->project_id]),
                'rfi_id' => $rfi->id,
                'rfi_no' => $rfi->rfi_no,
                'finding_id' => $finding->id,
                'category' => $rfi->category,
                'project_name' => $rfi->project?->project_name ?? '',
                'actor_name' => $actor->name,
                'actor_role' => $actor->role,
            ];

            $this->sendNotification($recipients, new FindingDueDateChanged($payload));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function warehouseFindingRejected(QcFinding $finding, User $actor): void
    {
        try {
            $finding->loadMissing(['rfi.project', 'rfi.createdBy']);
            $rfi = $finding->rfi;
            $recipient = $rfi?->createdBy;

            if (! $recipient || ! $recipient->is_active || $recipient->id === $actor->id) {
                return;
            }

            $payload = [
                'type' => 'finding.warehouse_rejected',
                'title' => 'Incoming Material Ditolak (Reject)',
                'body' => "Temuan QC untuk RFI {$rfi->rfi_no} dinyatakan Reject oleh {$actor->name}.",
                'url' => route('qc-finding.project', ['project' => $rfi->project_id]),
                'rfi_id' => $rfi->id,
                'rfi_no' => $rfi->rfi_no,
                'finding_id' => $finding->id,
                'category' => $rfi->category,
                'project_name' => $rfi->project?->project_name ?? '',
                'actor_name' => $actor->name,
                'actor_role' => $actor->role,
            ];

            $this->sendNotification(collect([$recipient]), new RfiNc($payload));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function warehouseFindingUseAsIs(QcFinding $finding, User $actor): void
    {
        try {
            $finding->loadMissing(['rfi.project', 'rfi.createdBy']);
            $rfi = $finding->rfi;
            $recipient = $rfi?->createdBy;

            if (! $recipient || ! $recipient->is_active || $recipient->id === $actor->id) {
                return;
            }

            $payload = [
                'type' => 'finding.warehouse_use_as_is',
                'title' => 'Incoming Material Diterima (Use as is)',
                'body' => "Incoming Material {$rfi->rfi_no} diterima (Use as is) oleh {$actor->name}.",
                'url' => route('qc-finding.project', ['project' => $rfi->project_id]),
                'rfi_id' => $rfi->id,
                'rfi_no' => $rfi->rfi_no,
                'finding_id' => $finding->id,
                'category' => $rfi->category,
                'project_name' => $rfi->project?->project_name ?? '',
                'actor_name' => $actor->name,
                'actor_role' => $actor->role,
            ];

            $this->sendNotification(collect([$recipient]), new RfiReviewedOk($payload));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return Collection<int, User>
     */
    protected function resolveRecipients(
        string $eventKey,
        ?Rfi $rfi,
        ?QcFinding $finding,
        ?FindingSubmission $submission,
        User $actor
    ): Collection {
        $notificationsConfig = config('mudalaya.notifications', []);
        $eventConfig = $notificationsConfig[$eventKey] ?? [];
        $roles = $eventConfig['roles'] ?? [];
        $accounts = $eventConfig['accounts'] ?? [];

        $roleUsers = ! empty($roles)
            ? User::query()->where('is_active', true)->whereIn('role', $roles)->get()
            : collect();

        $accountUserIds = $this->resolveAccountUserIds($accounts, $rfi, $finding, $submission);
        $accountUsers = ! empty($accountUserIds)
            ? User::query()->where('is_active', true)->whereIn('id', $accountUserIds)->get()
            : collect();

        return $roleUsers
            ->concat($accountUsers)
            ->reject(fn (User $user): bool => $user->id === $actor->id)
            ->unique('id')
            ->values();
    }

    /**
     * @param  array<int, string>  $accounts
     * @return array<int, int>
     */
    protected function resolveAccountUserIds(
        array $accounts,
        ?Rfi $rfi,
        ?QcFinding $finding,
        ?FindingSubmission $submission
    ): array {
        $ids = [];

        foreach ($accounts as $account) {
            $id = match ($account) {
                'rfi.created_by' => $rfi?->created_by,
                'submission.submitted_by' => $submission?->submitted_by,
                'finding.action_plan_by' => $finding?->action_plan_by,
                default => null,
            };

            if ($id !== null) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    protected function filterRfiAccess(Collection $users, string $category): Collection
    {
        return $users->filter(fn (User $user): bool => $this->hasRfiAccess($user, $category))->values();
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    protected function filterFindingAccess(Collection $users, string $category): Collection
    {
        return $users->filter(fn (User $user): bool => $this->hasFindingAccess($user, $category))->values();
    }

    protected function hasRfiAccess(User $user, string $category): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        $allowedRoles = config("mudalaya.rfi_categories.{$category}.roles", []);

        return in_array($user->role, $allowedRoles, true);
    }

    protected function hasFindingAccess(User $user, string $category): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        $allowedCategories = config("mudalaya.findings.view_categories.{$user->role}", []);

        return in_array($category, $allowedCategories, true);
    }

    /**
     * @param  Collection<int, User>  $users
     */
    protected function sendNotification(Collection $users, Notification $notification): void
    {
        $activeUsers = $users->filter(fn (User $user): bool => (bool) $user->is_active);

        if ($activeUsers->isNotEmpty()) {
            NotificationFacade::send($activeUsers, $notification);
        }
    }
}
