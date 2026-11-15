<?php

namespace Tests\Feature\Services;

use App\Services\IrfLeadTimeCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IrfLeadTimeCalculatorTest extends TestCase
{
    public function test_deadline_is_next_day_at_2359_regardless_of_submission_time(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://date.nager.at/api/v3/PublicHolidays/*/ID' => Http::response([], 200),
        ]);

        $calculator = new IrfLeadTimeCalculator;

        $morningDeadline = $calculator->deadlineFor(CarbonImmutable::parse('2026-09-28 07:00:00', 'Asia/Jakarta'));
        $eveningDeadline = $calculator->deadlineFor(CarbonImmutable::parse('2026-09-28 20:00:00', 'Asia/Jakarta'));

        $this->assertSame('2026-09-29 23:59:00', $morningDeadline->format('Y-m-d H:i:s'));
        $this->assertSame($morningDeadline->toDateTimeString(), $eveningDeadline->toDateTimeString());
        Http::assertSentCount(1);
    }

    public function test_saturday_submission_skips_sunday_but_keeps_monday(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://date.nager.at/api/v3/PublicHolidays/*/ID' => Http::response([], 200),
        ]);

        $deadline = (new IrfLeadTimeCalculator)->deadlineFor(
            CarbonImmutable::parse('2026-09-26 09:00:00', 'Asia/Jakarta')
        );

        $this->assertSame('2026-09-28 23:59:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_friday_submission_uses_saturday_deadline(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://date.nager.at/api/v3/PublicHolidays/*/ID' => Http::response([], 200),
        ]);

        $deadline = (new IrfLeadTimeCalculator)->deadlineFor(
            CarbonImmutable::parse('2026-09-25 15:00:00', 'Asia/Jakarta')
        );

        $this->assertSame('2026-09-26 23:59:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_sunday_submission_uses_monday_deadline(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://date.nager.at/api/v3/PublicHolidays/*/ID' => Http::response([], 200),
        ]);

        $deadline = (new IrfLeadTimeCalculator)->deadlineFor(
            CarbonImmutable::parse('2026-09-27 15:00:00', 'Asia/Jakarta')
        );

        $this->assertSame('2026-09-28 23:59:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_holiday_api_failure_falls_back_to_skipping_only_sunday(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://date.nager.at/api/v3/PublicHolidays/*/ID' => Http::response([], 503),
        ]);
        Cache::flush();

        $deadline = (new IrfLeadTimeCalculator)->deadlineFor(
            CarbonImmutable::parse('2026-09-25 15:00:00', 'Asia/Jakarta')
        );

        $this->assertSame('2026-09-26 23:59:00', $deadline->format('Y-m-d H:i:s'));
        $this->assertFalse(Cache::has('national-holidays:ID:2026'));
    }

    public function test_national_holiday_is_skipped_until_a_working_day_is_found(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://date.nager.at/api/v3/PublicHolidays/*/ID' => Http::response([
                ['date' => '2026-09-29'],
            ], 200),
        ]);

        $deadline = (new IrfLeadTimeCalculator)->deadlineFor(
            CarbonImmutable::parse('2026-09-28 20:00:00', 'Asia/Jakarta')
        );

        $this->assertSame('2026-09-30 23:59:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_national_holidays_are_requested_only_once_per_day(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://date.nager.at/api/v3/PublicHolidays/*/ID' => Http::response([], 200),
        ]);
        Cache::flush();

        $calculator = new IrfLeadTimeCalculator;
        $submittedAt = CarbonImmutable::parse('2026-09-28 20:00:00', 'Asia/Jakarta');

        $calculator->deadlineFor($submittedAt);
        $calculator->deadlineFor($submittedAt->addHour());

        Http::assertSentCount(1);
    }
}
