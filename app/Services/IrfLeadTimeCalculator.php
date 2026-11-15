<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;
use UnexpectedValueException;

class IrfLeadTimeCalculator
{
    public function deadlineFor(CarbonInterface $submittedAt): CarbonImmutable
    {
        $candidate = CarbonImmutable::instance($submittedAt)
            ->setTimezone('Asia/Jakarta')
            ->startOfDay()
            ->addDay();

        while (true) {
            $holidays = $this->holidaysForYear($candidate->year);

            if ($candidate->dayOfWeek !== Carbon::SUNDAY && ! in_array($candidate->toDateString(), $holidays, true)) {
                return $candidate->setTime(23, 59);
            }

            $candidate = $candidate->addDay();
        }
    }

    private function holidaysForYear(int $year): array
    {
        try {
            return Cache::remember(
                "national-holidays:ID:{$year}",
                now('Asia/Jakarta')->endOfDay(),
                function () use ($year): array {
                    $holidays = Http::acceptJson()
                        ->timeout(5)
                        ->get("https://date.nager.at/api/v3/PublicHolidays/{$year}/ID")
                        ->throw()
                        ->json();

                    if (! is_array($holidays)) {
                        throw new UnexpectedValueException('Invalid national holiday API response.');
                    }

                    return array_values(array_filter(array_column($holidays, 'date'), 'is_string'));
                }
            );
        } catch (Throwable) {
            return [];
        }
    }
}
