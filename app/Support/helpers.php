<?php

use App\Support\Format;

if (! function_exists('format_date')) {
    function format_date(DateTimeInterface|string|null $date): string
    {
        return Format::date($date);
    }
}

if (! function_exists('format_relative_time')) {
    function format_relative_time(DateTimeInterface|string|null $timestamp): string
    {
        return Format::relativeTime($timestamp);
    }
}

if (! function_exists('format_rupiah')) {
    function format_rupiah(int|float|null $amount): string
    {
        return Format::rupiah($amount);
    }
}

if (! function_exists('format_compact_rupiah')) {
    function format_compact_rupiah(int|float|null $amount): string
    {
        return Format::compactRupiah($amount);
    }
}

if (! function_exists('format_percentage')) {
    function format_percentage(float|int|null $value, int $decimals = 1): string
    {
        return Format::percentage($value, $decimals);
    }
}

if (! function_exists('format_root_cause_category')) {
    function format_root_cause_category(string|array|null $category): string
    {
        return Format::rootCauseCategoryLabel($category);
    }
}
