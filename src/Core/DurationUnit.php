<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Core;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
enum DurationUnit: string
{
    case Day   = 'd';
    case Week  = 'w';
    case Month = 'm';
    case Year  = 'y';

    /**
     * Length in days. Months and years use fixed lengths (30 / 365) so a
     * threshold means the same number of days regardless of the current date —
     * calendar month arithmetic would overflow (Mar 31 minus one month is Mar 3
     * in PHP) and make the cutoff depend on when the command runs.
     */
    public function days(): int
    {
        return match ($this) {
            self::Day   => 1,
            self::Week  => 7,
            self::Month => 30,
            self::Year  => 365,
        };
    }
}
