<?php

namespace App\Services;

use App\Models\QcFinding;
use App\Models\Rfi;
use App\Models\User;
use Carbon\Carbon;

class DashboardSummary
{
    private const TZ = 'Asia/Jakarta';

    /**
     * @return array{
     *     rfiReview: array{count: int, oldestDays: int, show: bool},
     *     reinspectVerify: array{count: int, show: bool},
     *     overdueFindings: array{count: int, oldestDays: int, show: bool},
     *     hasActions: bool,
     * }
     */
    public function getActionItems(User $user): array
    {
        $role = $user->role;
        $now = Carbon::now(self::TZ);

        // 1. RFI Menunggu Review
        // Hak review RFI dimiliki oleh admin dan qc_qa
        $canReviewRfi = in_array($role, ['admin', 'qc_qa'], true);
        $rfiCount = 0;
        $rfiOldestDays = 0;

        if ($canReviewRfi) {
            $openRfis = Rfi::query()
                ->where('status', 'open')
                ->whereNotNull('submitted_at')
                ->get(['id', 'submitted_at']);

            $rfiCount = $openRfis->count();
            if ($rfiCount > 0) {
                $oldestRfi = $openRfis->min('submitted_at');
                if ($oldestRfi) {
                    $rfiOldestDays = (int) max(0, Carbon::parse($oldestRfi)->diffInDays($now));
                }
            }
        }

        // 2. Re-Inspect Menunggu Verifikasi
        // Hak verifikasi Re-Inspect dimiliki oleh admin dan qc_qa
        $canVerifyReinspect = in_array($role, ['admin', 'qc_qa'], true);
        $reinspectCount = 0;

        if ($canVerifyReinspect) {
            $allowedFindingCats = config('mudalaya.findings.view_categories.'.$role, []);
            $reinspectCount = QcFinding::query()
                ->where('status', 'waiting_verification')
                ->whereHas('rfi', fn ($q) => $q->whereIn('category', $allowedFindingCats))
                ->count();
        }

        // 3. Temuan Lewat Due Date (terlambat) + Jatuh Tempo Hari Ini
        // Semua role yang memiliki akses finding bisa melihat temuan lewat due date pada kategorinya
        $allowedFindingCats = config('mudalaya.findings.view_categories.'.$role, []);
        $todayDate = $now->toDateString();
        $overdueCount = 0;
        $dueTodayCount = 0;
        $overdueOldestDays = 0;

        if ($allowedFindingCats !== []) {
            // Single aggregate query: past overdue + due today in one hit
            $urgentCounts = QcFinding::query()
                ->where('status', '!=', 'closed')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<=', $todayDate)
                ->whereHas('rfi', fn ($q) => $q->whereIn('category', $allowedFindingCats))
                ->selectRaw(
                    'COUNT(CASE WHEN date(due_date) < ? THEN id END) as overdue_count,'.
                    'COUNT(CASE WHEN date(due_date) = ? THEN id END) as due_today_count,'.
                    'MIN(CASE WHEN date(due_date) < ? THEN date(due_date) END) as oldest_overdue_date',
                    [$todayDate, $todayDate, $todayDate]
                )
                ->first();

            $overdueCount = (int) ($urgentCounts?->overdue_count ?? 0);
            $dueTodayCount = (int) ($urgentCounts?->due_today_count ?? 0);

            if ($overdueCount > 0 && filled($urgentCounts?->oldest_overdue_date)) {
                $overdueOldestDays = (int) max(0, Carbon::parse($urgentCounts->oldest_overdue_date)->diffInDays($now));
            }
        }

        $showRfi = $canReviewRfi;
        $showVerify = $canVerifyReinspect;
        $showOverdue = $allowedFindingCats !== [];

        $hasActions = ($showRfi && $rfiCount > 0)
            || ($showVerify && $reinspectCount > 0)
            || ($showOverdue && ($overdueCount + $dueTodayCount) > 0);

        return [
            'rfiReview' => [
                'count' => $rfiCount,
                'oldestDays' => $rfiOldestDays,
                'show' => $showRfi,
            ],
            'reinspectVerify' => [
                'count' => $reinspectCount,
                'show' => $showVerify,
            ],
            'overdueFindings' => [
                'count' => $overdueCount,
                'dueTodayCount' => $dueTodayCount,
                'oldestDays' => $overdueOldestDays,
                'show' => $showOverdue,
            ],
            'hasActions' => $hasActions,
        ];
    }

    /**
     * @param  list<string>  $allowedCategories
     * @return array{openCount: int, overdueCount: int, closedThisMonth: int}
     */
    public function getFindingSummary(array $allowedCategories): array
    {
        if ($allowedCategories === []) {
            return [
                'openCount' => 0,
                'overdueCount' => 0,
                'closedThisMonth' => 0,
            ];
        }

        $now = Carbon::now(self::TZ);
        $todayDate = $now->toDateString();
        $startOfMonth = $now->copy()->startOfMonth()->toDateString();
        $endOfMonth = $now->copy()->endOfMonth()->toDateString();

        $openCount = QcFinding::query()
            ->where('status', '!=', 'closed')
            ->whereHas('rfi', fn ($q) => $q->whereIn('category', $allowedCategories))
            ->count();

        $overdueCount = QcFinding::query()
            ->where('status', '!=', 'closed')
            ->whereNotNull('due_date')
            ->where('due_date', '<', $todayDate)
            ->whereHas('rfi', fn ($q) => $q->whereIn('category', $allowedCategories))
            ->count();

        $closedThisMonth = QcFinding::query()
            ->where('status', 'closed')
            ->whereNotNull('date_of_close')
            ->whereBetween('date_of_close', [$startOfMonth, $endOfMonth])
            ->whereHas('rfi', fn ($q) => $q->whereIn('category', $allowedCategories))
            ->count();

        return [
            'openCount' => $openCount,
            'overdueCount' => $overdueCount,
            'closedThisMonth' => $closedThisMonth,
        ];
    }

    /**
     * Format NC cost into compact Indonesian Rupiah format.
     * e.g. 83922729 -> "Rp 83,9 jt", 1500000 -> "Rp 1,5 jt", 500000 -> "Rp 500 rb", 0 -> "Rp 0"
     */
    public static function formatCompactRupiah(int|float $amount): string
    {
        $amount = (float) $amount;

        if ($amount <= 0) {
            return 'Rp 0';
        }

        if ($amount >= 1_000_000_000) {
            $val = $amount / 1_000_000_000;
            $formatted = number_format($val, $val < 10 ? 1 : 0, ',', '.');

            return 'Rp '.$formatted.' M';
        }

        if ($amount >= 1_000_000) {
            $val = $amount / 1_000_000;
            $formatted = number_format($val, $val < 100 ? 1 : 0, ',', '.');

            return 'Rp '.$formatted.' jt';
        }

        if ($amount >= 1_000) {
            $val = $amount / 1_000;
            $formatted = number_format($val, 0, ',', '.');

            return 'Rp '.$formatted.' rb';
        }

        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
