<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\Core\Duration;
use Hpbxxtr\UpgradeInteractive\Resolver\Age\ReleaseAgePolicy;

function policy(?string $threshold, string $now = '2026-08-20 12:00:00'): ReleaseAgePolicy
{
    return new ReleaseAgePolicy(
        $threshold === null ? null : Duration::parse($threshold),
        new DateTimeImmutable($now),
    );
}

\it('is disabled without a threshold', function (): void {
    $policy = \policy(null);

    \expect($policy->isEnabled())->toBeFalse()
        ->and($policy->label())->toBe('off')
        ->and($policy->cutoff())->toBeNull()
    ;
});

\it('is disabled for a zero threshold', function (): void {
    \expect(\policy('0')->isEnabled())->toBeFalse();
});

\it('accepts everything while disabled', function (): void {
    $policy = \policy(null);

    \expect($policy->isEligible(new DateTimeImmutable('2026-08-20 11:59:00')))->toBeTrue();
});

\it('blocks releases younger than the threshold', function (): void {
    $policy = \policy('7d');

    \expect($policy->isEligible(new DateTimeImmutable('2026-08-19 12:00:00')))->toBeFalse();
});

\it('accepts releases older than the threshold', function (): void {
    $policy = \policy('7d');

    \expect($policy->isEligible(new DateTimeImmutable('2026-08-10 12:00:00')))->toBeTrue();
});

\it('treats a release exactly at the cutoff as eligible', function (): void {
    $policy = \policy('7d');

    \expect($policy->isEligible(new DateTimeImmutable('2026-08-13 12:00:00')))->toBeTrue()
        ->and($policy->isEligible(new DateTimeImmutable('2026-08-13 12:00:01')))->toBeFalse()
    ;
});

\it('treats an unknown release date as eligible', function (): void {
    \expect(\policy('30d')->isEligible(null))->toBeTrue();
});

\it('exposes the threshold label', function (): void {
    \expect(\policy('2w')->label())->toBe('2w');
});

\it('returns a new policy with the same clock when the threshold changes', function (): void {
    $policy  = \policy('7d');
    $releaseAgePolicy = $policy->withThreshold(null);

    \expect($releaseAgePolicy->isEnabled())->toBeFalse()
        ->and($releaseAgePolicy->now())->toEqual($policy->now())
        ->and($policy->isEnabled())->toBeTrue()
    ;
});

\it('treats a month threshold as 30 days', function (): void {
    $policy = \policy('1m', '2026-03-31 12:00:00');

    \expect($policy->cutoff()?->format('Y-m-d'))->toBe('2026-03-01');
});

\it('defaults its clock to now', function (): void {
    $policy = new ReleaseAgePolicy(Duration::parse('1d'));

    \expect($policy->isEligible(new DateTimeImmutable('now')))->toBeFalse();
});
