<?php

namespace App\Services;

use App\Models\QcFinding;
use App\Models\Rfi;
use App\Models\User;
use App\Notifications\DailyReminder;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class DailyReminderService
{
    private const TZ = 'Asia/Jakarta';

    public function __construct(
        private readonly IrfLeadTimeCalculator $leadTimeCalculator
    ) {}

    /**
     * Process daily reminders for all eligible users.
     *
     * @return array<int, array{user: User, status: string, message: ?string}>
     */
    public function sendDailyReminders(bool $dryRun = false, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now)->setTimezone(self::TZ) : Carbon::now(self::TZ);
        $users = User::query()->orderBy('id')->get();
        $results = [];

        foreach ($users as $user) {
            $reminder = $this->buildReminderForUser($user, $now);

            if ($reminder === null) {
                $results[] = [
                    'user' => $user,
                    'status' => 'no_items',
                    'message' => null,
                ];

                continue;
            }

            if ($this->alreadyReceivedReminderToday($user, $now)) {
                $results[] = [
                    'user' => $user,
                    'status' => 'already_sent',
                    'message' => $reminder['body'],
                ];

                continue;
            }

            if (! $dryRun) {
                $user->notify(new DailyReminder($reminder));
            }

            $results[] = [
                'user' => $user,
                'status' => $dryRun ? 'dry_run' : 'sent',
                'message' => $reminder['body'],
            ];
        }

        return $results;
    }

    /**
     * Build the single reminder payload for a specific user, or null if no reminder is needed.
     *
     * @return array{type: string, title: string, body: string, url: string, actor_name: string, actor_role: string}|null
     */
    public function buildReminderForUser(User $user, ?CarbonInterface $now = null): ?array
    {
        $now = $now ? Carbon::instance($now)->setTimezone(self::TZ) : Carbon::now(self::TZ);

        return match ($user->role) {
            'production' => $this->buildProductionReminder($user, $now),
            'qc_qa', 'admin' => $this->buildQcReminder($user, $now),
            default => null,
        };
    }

    /**
     * Check if user has already received a daily reminder today (Asia/Jakarta).
     */
    public function alreadyReceivedReminderToday(User $user, ?CarbonInterface $now = null): bool
    {
        $now = $now ? Carbon::instance($now)->setTimezone(self::TZ) : Carbon::now(self::TZ);
        $todayDate = $now->toDateString();

        return $user->notifications()
            ->where(function ($query): void {
                $query->where('type', DailyReminder::class)
                    ->orWhere('type', 'daily_reminder')
                    ->orWhereJsonContains('data->type', 'daily_reminder');
            })
            ->whereDate('created_at', $todayDate)
            ->exists();
    }

    /**
     * @return array{type: string, title: string, body: string, url: string, actor_name: string, actor_role: string}|null
     */
    private function buildProductionReminder(User $user, CarbonInterface $now): ?array
    {
        $allowedCategories = config("mudalaya.findings.view_categories.{$user->role}", ['A', 'B', 'C', 'D', 'E']);
        if (empty($allowedCategories)) {
            return null;
        }

        $todayDate = $now->toDateString();

        $urgentCounts = QcFinding::query()
            ->where('status', '!=', 'closed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $todayDate)
            ->whereHas('rfi', fn ($q) => $q->whereIn('category', $allowedCategories))
            ->selectRaw(
                'COUNT(CASE WHEN date(due_date) < ? THEN id END) as overdue_count,'.
                'COUNT(CASE WHEN date(due_date) = ? THEN id END) as due_today_count',
                [$todayDate, $todayDate]
            )
            ->first();

        $overdueCount = (int) ($urgentCounts?->overdue_count ?? 0);
        $dueTodayCount = (int) ($urgentCounts?->due_today_count ?? 0);

        if ($overdueCount === 0 && $dueTodayCount === 0) {
            return null;
        }

        if ($dueTodayCount > 0 && $overdueCount > 0) {
            $body = "Pengingat: {$dueTodayCount} temuan jatuh tempo hari ini dan {$overdueCount} terlambat. Buka QC Finding.";
        } elseif ($dueTodayCount > 0) {
            $body = "Pengingat: {$dueTodayCount} temuan jatuh tempo hari ini. Buka QC Finding.";
        } else {
            $body = "Pengingat: {$overdueCount} temuan terlambat. Buka QC Finding.";
        }

        return [
            'type' => 'daily_reminder',
            'title' => 'Pengingat QC Finding',
            'body' => $body,
            'url' => route('qc-finding.index', ['pf' => 'terlambat']),
            'actor_name' => 'Sistem',
            'actor_role' => 'system',
        ];
    }

    /**
     * @return array{type: string, title: string, body: string, url: string, actor_name: string, actor_role: string}|null
     */
    private function buildQcReminder(User $user, CarbonInterface $now): ?array
    {
        $openRfis = Rfi::query()
            ->where('status', 'open')
            ->whereNotNull('submitted_at')
            ->get(['id', 'submitted_at']);

        $totalUnreviewed = $openRfis->count();
        if ($totalUnreviewed === 0) {
            return null;
        }

        $passedH1Count = 0;
        foreach ($openRfis as $rfi) {
            $deadline = $this->leadTimeCalculator->deadlineFor($rfi->submitted_at);
            if ($now->greaterThan($deadline)) {
                $passedH1Count++;
            }
        }

        if ($passedH1Count > 0) {
            $body = "Pengingat: {$totalUnreviewed} RFI belum direspon, {$passedH1Count} sudah melewati batas H+1. Buka daftar RFI.";
        } else {
            $body = "Pengingat: {$totalUnreviewed} RFI belum direspon. Buka daftar RFI.";
        }

        return [
            'type' => 'daily_reminder',
            'title' => 'Pengingat RFI',
            'body' => $body,
            'url' => route('rfi.index'),
            'actor_name' => 'Sistem',
            'actor_role' => 'system',
        ];
    }
}
