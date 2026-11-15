<?php

namespace App\Enums;

enum RfiResult: string
{
    case Accepted = 'accepted';
    case Repair = 'repair';
    case UseAsIs = 'use_as_is';
    case Reject = 'reject';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Accepted',
            self::Repair => 'Repair',
            self::UseAsIs => 'Use as is',
            self::Reject => 'Reject',
        };
    }
}
