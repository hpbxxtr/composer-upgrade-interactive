<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\Core\RelativeTime;

\it('describes ages in the largest sensible unit', function (string $date, string $expected): void {
    $now = new DateTimeImmutable('2026-08-20 12:00:00');

    \expect(RelativeTime::describe(new DateTimeImmutable($date), $now))->toBe($expected);
})->with([
    ['2026-08-20 06:00:00', 'today'],
    ['2026-08-19 06:00:00', '1 day ago'],
    ['2026-08-15 12:00:00', '5 days ago'],
    ['2026-07-01 12:00:00', '1 month ago'],
    ['2026-02-20 12:00:00', '6 months ago'],
    ['2024-08-20 12:00:00', '2 years ago'],
]);

\it('describes future dates as today', function (): void {
    $now = new DateTimeImmutable('2026-08-20 12:00:00');

    \expect(RelativeTime::describe(new DateTimeImmutable('2026-09-01 12:00:00'), $now))->toBe('today');
});
