<?php

namespace Tests\Unit;

use App\Support\Format;
use Carbon\Carbon;
use Tests\TestCase;

class FormatHelperTest extends TestCase
{
    public function test_format_date_formats_indonesian_short_date(): void
    {
        $this->assertSame('26 Okt 2026', Format::date('2026-10-26'));
        $this->assertSame('1 Jan 2025', Format::date('2025-01-01'));
        $this->assertSame('17 Agu 1945', Format::date('1945-08-17'));
        $this->assertSame('—', Format::date(null));
    }

    public function test_format_relative_time_formats_compact_indonesian_time(): void
    {
        $now = Carbon::parse('2026-10-26 12:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        // < 1 minute
        $this->assertSame('Baru saja', Format::relativeTime($now->copy()->subSeconds(30)));

        // Minutes
        $this->assertSame('5 mnt lalu', Format::relativeTime($now->copy()->subMinutes(5)));

        // Hours (today)
        $this->assertSame('3 jam lalu', Format::relativeTime($now->copy()->subHours(3)));

        // Yesterday
        $yesterday = $now->copy()->subDay()->setTime(15, 0);
        $this->assertSame('Kemarin', Format::relativeTime($yesterday));

        // Days
        $this->assertSame('4 hari lalu', Format::relativeTime($now->copy()->subDays(4)));

        // Older than a week
        $this->assertSame('10 Okt 2026', Format::relativeTime($now->copy()->subDays(16)));

        Carbon::setTestNow();
    }

    public function test_format_rupiah_formats_full_currency(): void
    {
        $this->assertSame('Rp 13.100.000', Format::rupiah(13100000));
        $this->assertSame('Rp 0', Format::rupiah(0));
        $this->assertSame('Rp 50.000', Format::rupiah(50000));
    }

    public function test_format_compact_rupiah_formats_compact_currency(): void
    {
        $this->assertSame('Rp 83,9 jt', Format::compactRupiah(83922729));
        $this->assertSame('Rp 1,5 jt', Format::compactRupiah(1500000));
        $this->assertSame('Rp 2,5 M', Format::compactRupiah(2500000000));
        $this->assertSame('Rp 500 rb', Format::compactRupiah(500000));
        $this->assertSame('Rp 0', Format::compactRupiah(0));
    }

    public function test_global_helper_functions(): void
    {
        $this->assertSame('26 Okt 2026', format_date('2026-10-26'));
        $this->assertSame('Rp 13.100.000', format_rupiah(13100000));
        $this->assertSame('Rp 83,9 jt', format_compact_rupiah(83900000));
    }
}
