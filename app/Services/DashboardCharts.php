<?php

namespace App\Services;

use App\Models\Mdr;
use App\Models\QcFinding;
use App\Models\Rfi;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class DashboardCharts
{
    private const TZ = 'Asia/Jakarta';

    /**
     * Return the 6-month period labels and keys.
     *
     * @return array{0: CarbonImmutable, 1: list<array{key: string, label: string}>}
     */
    private function buildMonths(): array
    {
        $now = CarbonImmutable::now(self::TZ);
        $months = [];

        for ($i = 5; $i >= 0; $i--) {
            $m = $now->startOfMonth()->subMonths($i);
            $months[] = [
                'key' => $m->format('Y-m'),
                'label' => $m->locale('id')->isoFormat('MMM YYYY'),
            ];
        }

        $firstMonth = CarbonImmutable::parse($months[0]['key'].'-01', self::TZ)->utc();

        return [$firstMonth, $months];
    }

    /**
     * Convert a raw UTC datetime string to a Jakarta "Y-m" key.
     */
    private function toMonthKey(?string $utcDatetime): ?string
    {
        if ($utcDatetime === null) {
            return null;
        }

        return Carbon::parse($utcDatetime, 'UTC')
            ->setTimezone(self::TZ)
            ->format('Y-m');
    }

    /**
     * RFI per bulan: seri Open, NC, Close.
     *
     * @param  list<string>  $allowedCategories
     * @return array{months: list<string>, series: array<string, list<int>>}
     */
    public function rfiByMonth(array $allowedCategories): array
    {
        [$startUtc, $months] = $this->buildMonths();

        $rows = Rfi::query()
            ->whereIn('category', $allowedCategories)
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', $startUtc->toDateTimeString())
            ->get(['submitted_at', 'status']);

        $keys = array_column($months, 'key');
        $tally = array_fill_keys($keys, ['open' => 0, 'nc' => 0, 'close' => 0]);

        foreach ($rows as $row) {
            $key = $this->toMonthKey($row->getRawOriginal('submitted_at'));

            if ($key !== null && isset($tally[$key])) {
                $status = $row->status ?? 'open';

                if ($status === 'nc') {
                    $tally[$key]['nc']++;
                } elseif ($status === 'close') {
                    $tally[$key]['close']++;
                } else {
                    $tally[$key]['open']++;
                }
            }
        }

        return [
            'months' => array_column($months, 'label'),
            'series' => [
                'Open' => array_values(array_map(fn ($t) => $t['open'], $tally)),
                'NC' => array_values(array_map(fn ($t) => $t['nc'], $tally)),
                'Close' => array_values(array_map(fn ($t) => $t['close'], $tally)),
            ],
        ];
    }

    /**
     * Temuan QC per bulan: jumlah dibuat vs ditutup.
     *
     * @param  list<string>  $allowedCategories
     * @return array{months: list<string>, series: array<string, list<int>>}
     */
    public function findingCreatedVsClosed(array $allowedCategories): array
    {
        [$startUtc, $months] = $this->buildMonths();

        $startDate = $startUtc->toDateString();

        $findings = QcFinding::query()
            ->whereHas('rfi', fn ($q) => $q->whereIn('category', $allowedCategories))
            ->where(function ($query) use ($startDate) {
                $query->where('date_of_finding', '>=', $startDate)
                    ->orWhere('date_of_close', '>=', $startDate);
            })
            ->get(['date_of_finding', 'date_of_close']);

        $keys = array_column($months, 'key');
        $tally = array_fill_keys($keys, ['created' => 0, 'closed' => 0]);

        foreach ($findings as $f) {
            $createdKey = $f->date_of_finding?->format('Y-m');

            if ($createdKey !== null && isset($tally[$createdKey])) {
                $tally[$createdKey]['created']++;
            }

            if ($f->date_of_close !== null) {
                $closedKey = $f->date_of_close->format('Y-m');

                if (isset($tally[$closedKey])) {
                    $tally[$closedKey]['closed']++;
                }
            }
        }

        return [
            'months' => array_column($months, 'label'),
            'series' => [
                'Dibuat' => array_values(array_map(fn ($t) => $t['created'], $tally)),
                'Ditutup' => array_values(array_map(fn ($t) => $t['closed'], $tally)),
            ],
        ];
    }

    /**
     * Temuan per bulan: closed vs belum closed (berdasarkan date_of_finding).
     *
     * @param  list<string>  $allowedCategories
     * @return array{months: list<string>, series: array<string, list<int>>}
     */
    public function findingStatusByMonth(array $allowedCategories): array
    {
        [$startUtc, $months] = $this->buildMonths();

        $findings = QcFinding::query()
            ->whereHas('rfi', fn ($q) => $q->whereIn('category', $allowedCategories))
            ->where('date_of_finding', '>=', $startUtc->toDateString())
            ->get(['date_of_finding', 'status']);

        $keys = array_column($months, 'key');
        $tally = array_fill_keys($keys, ['closed' => 0, 'open' => 0]);

        foreach ($findings as $f) {
            $key = $f->date_of_finding?->format('Y-m');

            if ($key !== null && isset($tally[$key])) {
                if ($f->status === 'closed') {
                    $tally[$key]['closed']++;
                } else {
                    $tally[$key]['open']++;
                }
            }
        }

        return [
            'months' => array_column($months, 'label'),
            'series' => [
                'Closed' => array_values(array_map(fn ($t) => $t['closed'], $tally)),
                'Belum Closed' => array_values(array_map(fn ($t) => $t['open'], $tally)),
            ],
        ];
    }

    /**
     * MDR dibuat per bulan: seri Open vs Close (overall_status dihitung di PHP).
     *
     * @return array{months: list<string>, series: array<string, list<int>>}
     */
    public function mdrByMonth(): array
    {
        [$startUtc, $months] = $this->buildMonths();

        $mdrs = Mdr::query()
            ->with('reportStatuses')
            ->where('created_at', '>=', $startUtc->toDateTimeString())
            ->get(['id', 'created_at']);

        $keys = array_column($months, 'key');
        $tally = array_fill_keys($keys, ['open' => 0, 'close' => 0]);

        foreach ($mdrs as $mdr) {
            $key = $this->toMonthKey($mdr->getRawOriginal('created_at'));

            if ($key !== null && isset($tally[$key])) {
                $status = $mdr->overall_status->value;

                if ($status === 'close') {
                    $tally[$key]['close']++;
                } else {
                    $tally[$key]['open']++;
                }
            }
        }

        return [
            'months' => array_column($months, 'label'),
            'series' => [
                'Open' => array_values(array_map(fn ($t) => $t['open'], $tally)),
                'Close' => array_values(array_map(fn ($t) => $t['close'], $tally)),
            ],
        ];
    }
}
