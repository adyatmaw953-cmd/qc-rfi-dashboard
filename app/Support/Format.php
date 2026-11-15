<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;

class Format
{
    private const TZ = 'Asia/Jakarta';

    /**
     * Format date into compact Indonesian format (e.g., "26 Okt 2026").
     */
    public static function date(DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        $carbon = $date instanceof CarbonInterface
            ? $date->copy()->setTimezone(self::TZ)
            : Carbon::parse($date, self::TZ);

        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        $day = $carbon->format('j');
        $month = $months[(int) $carbon->format('n')] ?? $carbon->format('M');
        $year = $carbon->format('Y');

        return "{$day} {$month} {$year}";
    }

    /**
     * Format a timestamp into compact Indonesian relative time (e.g. "5 mnt lalu", "Kemarin").
     */
    public static function relativeTime(DateTimeInterface|string|null $timestamp): string
    {
        if ($timestamp === null || $timestamp === '') {
            return '—';
        }

        $now = Carbon::now(self::TZ);
        $time = $timestamp instanceof CarbonInterface
            ? $timestamp->copy()->setTimezone(self::TZ)
            : Carbon::parse($timestamp, self::TZ);

        $diffInSeconds = (int) $now->diffInSeconds($time, false);

        // Future timestamps
        if ($diffInSeconds > 0) {
            $diffInMinutes = (int) floor($diffInSeconds / 60);
            if ($diffInMinutes < 60) {
                return "{$diffInMinutes} mnt lagi";
            }
            $diffInHours = (int) floor($diffInSeconds / 3600);
            if ($diffInHours < 24) {
                return "{$diffInHours} jam lagi";
            }

            return self::date($time);
        }

        $absSeconds = abs($diffInSeconds);

        if ($absSeconds < 60) {
            return 'Baru saja';
        }

        $diffInMinutes = (int) floor($absSeconds / 60);
        if ($diffInMinutes < 60) {
            return "{$diffInMinutes} mnt lalu";
        }

        if ($time->isYesterday()) {
            return 'Kemarin';
        }

        $diffInHours = (int) floor($absSeconds / 3600);
        if ($diffInHours < 24) {
            return "{$diffInHours} jam lalu";
        }

        $diffInDays = (int) floor($absSeconds / 86400);
        if ($diffInDays < 7) {
            return "{$diffInDays} hari lalu";
        }

        return self::date($time);
    }

    /**
     * Format currency into full Indonesian Rupiah (e.g., "Rp 13.100.000").
     */
    public static function rupiah(int|float|null $amount): string
    {
        $val = (float) ($amount ?? 0);

        return 'Rp '.number_format($val, 0, ',', '.');
    }

    /**
     * Format currency into compact Indonesian Rupiah (e.g., "Rp 83,9 jt", "Rp 1,5 M", "Rp 500 rb", "Rp 0").
     */
    public static function compactRupiah(int|float|null $amount): string
    {
        $val = (float) ($amount ?? 0);

        if ($val <= 0) {
            return 'Rp 0';
        }

        if ($val >= 1_000_000_000) {
            $num = $val / 1_000_000_000;
            $formatted = number_format($num, $num < 10 ? 1 : 0, ',', '.');

            return "Rp {$formatted} M";
        }

        if ($val >= 1_000_000) {
            $num = $val / 1_000_000;
            $formatted = number_format($num, $num < 100 ? 1 : 0, ',', '.');

            return "Rp {$formatted} jt";
        }

        if ($val >= 1_000) {
            $num = $val / 1_000;
            $formatted = number_format($num, 0, ',', '.');

            return "Rp {$formatted} rb";
        }

        return 'Rp '.number_format($val, 0, ',', '.');
    }

    /**
     * Format a percentage into Indonesian format (e.g., "63,6%", "45,45%").
     */
    public static function percentage(float|int|null $value, int $decimals = 1): string
    {
        $val = (float) ($value ?? 0);

        return number_format($val, $decimals, ',', '.').'%';
    }

    /**
     * Map root cause category code to descriptive display label.
     */
    public static function rootCauseCategoryLabel(string|array|null $category): string
    {
        if (is_array($category)) {
            return implode(', ', array_map(fn ($c) => static::rootCauseCategoryLabel($c), $category));
        }

        $map = [
            'Man' => 'Man (Manusia)',
            'Method' => 'Method (Metode)',
            'Machine' => 'Machine (Mesin)',
            'Material' => 'Material (Bahan)',
        ];

        return $map[$category ?? ''] ?? ($category ?? '—');
    }
}
