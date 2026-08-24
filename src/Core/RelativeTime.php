<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Core;

use DateTimeImmutable;

use function intdiv;
use function max;
use function sprintf;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final readonly class RelativeTime
{
    /**
     * Human-readable age of $date relative to $now ("3 days ago"). Dates in the
     * future — clock skew, mis-stamped metadata — are reported as "today".
     */
    public static function describe(DateTimeImmutable $date, DateTimeImmutable $now): string
    {
        $diff = $now->diff($date);
        $days = $diff->days ?: 0;

        if ($diff->invert === 0) {
            return 'today';
        }

        return match (true) {
            $days === 0   => 'today',
            $days === 1   => '1 day ago',
            $days < 30    => sprintf('%d days ago', $days),
            $days < 365   => self::plural(max(1, intdiv($days, 30)), 'month'),
            default       => self::plural(max(1, intdiv($days, 365)), 'year'),
        };
    }

    private static function plural(int $amount, string $unit): string
    {
        return sprintf('%d %s%s ago', $amount, $unit, $amount === 1 ? '' : 's');
    }
}
