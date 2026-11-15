<?php

namespace App\Enums;

enum MdrStatus: string
{
    case Open = 'open';
    case Close = 'close';
    case NotApplicable = 'na';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Close => 'Close',
            self::NotApplicable => 'N/A',
        };
    }
}
