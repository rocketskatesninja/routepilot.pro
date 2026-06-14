<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for weekday-name → Carbon dayOfWeek integer.
 * Carbon dayOfWeek constants: 0=Sunday through 6=Saturday.
 */
final class Weekday
{
    /** @var array<string, int> */
    public const MAP = [
        'sunday' => 0,
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
    ];
}
