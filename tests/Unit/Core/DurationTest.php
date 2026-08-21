<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\Core\Duration;
use Hpbxxtr\UpgradeInteractive\Core\DurationUnit;

\it('parses a bare integer as days', function (): void {
    $duration = Duration::parse('14');

    \expect($duration->amount)->toBe(14)
        ->and($duration->unit)->toBe(DurationUnit::Day)
        ->and($duration->format())->toBe('14d')
    ;
});

\it('parses short unit suffixes', function (string $input, int $amount, DurationUnit $durationUnit): void {
    $duration = Duration::parse($input);

    \expect($duration->amount)->toBe($amount)
        ->and($duration->unit)->toBe($durationUnit)
    ;
})->with([
    ['7d', 7, DurationUnit::Day],
    ['2w', 2, DurationUnit::Week],
    ['3m', 3, DurationUnit::Month],
    ['1y', 1, DurationUnit::Year],
]);

\it('parses long unit names, whitespace and mixed case', function (): void {
    \expect(Duration::parse(' 3 MONTHS ')->unit)->toBe(DurationUnit::Month)
        ->and(Duration::parse('1 Day')->unit)->toBe(DurationUnit::Day)
        ->and(Duration::parse('2weeks')->amount)->toBe(2)
    ;
});

\it('rejects malformed input', function (string $input): void {
    Duration::parse($input);
})->with([['7 days ago'], ['-1d'], ['d'], [''], ['1.5d'], ['7x']])
    ->throws(InvalidArgumentException::class);

\it('converts to seconds', function (): void {
    \expect(Duration::parse('3m')->toSeconds())->toBe(90 * 86400)
        ->and(Duration::parse('1d')->toSeconds())->toBe(86400)
        ->and(Duration::parse('1y')->toSeconds())->toBe(365 * 86400)
    ;
});

\it('treats zero as zero regardless of unit', function (): void {
    \expect(Duration::parse('0')->isZero())->toBeTrue()
        ->and(Duration::parse('0w')->isZero())->toBeTrue()
        ->and(Duration::parse('1d')->isZero())->toBeFalse()
    ;
});

\it('converts to days', function (): void {
    \expect(Duration::parse('2w')->toDays())->toBe(14)
        ->and(Duration::parse('1m')->toDays())->toBe(30)
        ->and(Duration::parse('1y')->toDays())->toBe(365)
    ;
});
