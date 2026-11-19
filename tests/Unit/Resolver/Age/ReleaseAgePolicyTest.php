<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\Core\Duration;
use Hpbxxtr\UpgradeInteractive\Resolver\Age\AgeGate;
use Hpbxxtr\UpgradeInteractive\Resolver\Age\CooldownPolicy;
use Hpbxxtr\UpgradeInteractive\Resolver\Age\ReleaseAgePolicy;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;

function policy(?string $threshold, string $now = '2026-08-20 12:00:00', ?int $cooldownSeconds = null): ReleaseAgePolicy
{
    return new ReleaseAgePolicy(
        $threshold === null ? null : Duration::parse($threshold),
        new DateTimeImmutable($now),
        CooldownPolicy::fromComposerConfig(\cooldownConfig($cooldownSeconds)),
    );
}

function releasedAt(?string $date, bool $isCooldownExempt = false): VersionTarget
{
    return VersionTarget::fromRaw('1.0.0', $date === null ? null : new DateTimeImmutable($date), $isCooldownExempt);
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

    \expect($policy->isEligible(\releasedAt('2026-08-20 11:59:00')))->toBeTrue();
});

\it('blocks releases younger than the threshold', function (): void {
    $policy = \policy('7d');

    \expect($policy->isEligible(\releasedAt('2026-08-19 12:00:00')))->toBeFalse();
});

\it('accepts releases older than the threshold', function (): void {
    $policy = \policy('7d');

    \expect($policy->isEligible(\releasedAt('2026-08-10 12:00:00')))->toBeTrue();
});

\it('treats a release exactly at the cutoff as eligible', function (): void {
    $policy = \policy('7d');

    \expect($policy->isEligible(\releasedAt('2026-08-13 12:00:00')))->toBeTrue()
        ->and($policy->isEligible(\releasedAt('2026-08-13 12:00:01')))->toBeFalse()
    ;
});

\it('treats an unknown release date as eligible', function (): void {
    \expect(\policy('30d')->isEligible(\releasedAt(null)))->toBeTrue();
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

    \expect($policy->isEligible(\releasedAt('now')))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Composer cooldown floor
// ---------------------------------------------------------------------------

\it('blocks a release inside the cooldown when min age is off', function (): void {
    \expect(\policy(null, cooldownSeconds: 604800)->blockingGate(\releasedAt('2026-08-19 12:00:00')))->toBe(AgeGate::Cooldown);
});

\it('accepts a release past the cooldown', function (): void {
    $policy = \policy(null, cooldownSeconds: 604800);

    \expect($policy->blockingGate(\releasedAt('2026-08-10 12:00:00')))->toBeNull()
        ->and($policy->isEligible(\releasedAt('2026-08-10 12:00:00')))->toBeTrue()
    ;
});

\it('treats a release exactly at the cooldown cutoff as eligible', function (): void {
    $policy = \policy(null, cooldownSeconds: 604800);

    \expect($policy->isEligible(\releasedAt('2026-08-13 12:00:00')))->toBeTrue()
        ->and($policy->isEligible(\releasedAt('2026-08-13 12:00:01')))->toBeFalse()
    ;
});

\it('reports min age when it is the longer gate', function (): void {
    \expect(\policy('2w', cooldownSeconds: 604800)->blockingGate(\releasedAt('2026-08-19 12:00:00')))->toBe(AgeGate::MinAge);
});

\it('reports the cooldown when it is the longer gate', function (): void {
    \expect(\policy('2d', cooldownSeconds: 604800)->blockingGate(\releasedAt('2026-08-19 12:00:00')))->toBe(AgeGate::Cooldown);
});

\it('reports min age when only min age blocks', function (): void {
    // released 10 days ago: past the 7d cooldown, inside the 2w min age
    \expect(\policy('2w', cooldownSeconds: 604800)->blockingGate(\releasedAt('2026-08-10 12:00:00')))->toBe(AgeGate::MinAge);
});

\it('prefers the cooldown when both gates are equally long', function (): void {
    \expect(\policy('7d', cooldownSeconds: 604800)->blockingGate(\releasedAt('2026-08-19 12:00:00')))->toBe(AgeGate::Cooldown);
});

\it('applies only min age to a cooldown-exempt target', function (): void {
    \expect(\policy(null, cooldownSeconds: 604800)->blockingGate(\releasedAt('2026-08-19 12:00:00', isCooldownExempt: true)))->toBeNull()
        ->and(\policy('2d', cooldownSeconds: 604800)->blockingGate(\releasedAt('2026-08-19 12:00:00', isCooldownExempt: true)))->toBe(AgeGate::MinAge)
    ;
});

\it('treats an unknown release date as eligible under a cooldown', function (): void {
    \expect(\policy(null, cooldownSeconds: 604800)->isEligible(\releasedAt(null)))->toBeTrue();
});

\it('is enabled with only a cooldown', function (): void {
    $policy = \policy(null, cooldownSeconds: 604800);

    \expect($policy->isEnabled())->toBeTrue()
        ->and($policy->label())->toBe('off')
        ->and($policy->cooldownLabel())->toBe('7d')
    ;
});

\it('has no cooldown label without a cooldown', function (): void {
    \expect(\policy('7d')->cooldownLabel())->toBeNull();
});

\it('keeps the cooldown when the threshold changes', function (): void {
    $releaseAgePolicy = \policy('2w', cooldownSeconds: 604800)->withThreshold(null);

    \expect($releaseAgePolicy->blockingGate(\releasedAt('2026-08-19 12:00:00')))->toBe(AgeGate::Cooldown)
        ->and($releaseAgePolicy->cooldownLabel())->toBe('7d')
    ;
});
