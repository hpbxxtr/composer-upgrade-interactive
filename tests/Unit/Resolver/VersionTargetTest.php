<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\Resolver\Age\CooldownPolicy;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;

\it('strips leading v from display version', function (): void {
    $versionTarget = VersionTarget::fromRaw('v1.2.3');

    \expect($versionTarget->version)->toBe('1.2.3')
        ->and($versionTarget->versionRaw)->toBe('v1.2.3')
    ;
});

\it('keeps version unchanged when no v prefix', function (): void {
    $versionTarget = VersionTarget::fromRaw('1.2.3');

    \expect($versionTarget->version)->toBe('1.2.3')
        ->and($versionTarget->versionRaw)->toBe('1.2.3')
    ;
});

\it('has no release date by default', function (): void {
    \expect(VersionTarget::fromRaw('1.2.3')->releaseDate)->toBeNull();
});

\it('keeps a release date passed to fromRaw', function (): void {
    $date = new DateTimeImmutable('2026-08-01 10:00:00');

    \expect(VersionTarget::fromRaw('1.2.3', $date)->releaseDate)->toEqual($date);
});

\it('reads the release date off a Composer package', function (): void {
    $package = new Composer\Package\CompletePackage('vendor/pkg', '1.2.3.0', 'v1.2.3');
    $package->setReleaseDate(new DateTime('2026-07-15 08:30:00'));

    $versionTarget = VersionTarget::fromPackage($package);

    \expect($versionTarget->version)->toBe('1.2.3')
        ->and($versionTarget->versionRaw)->toBe('v1.2.3')
        ->and($versionTarget->releaseDate)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($versionTarget->releaseDate?->format('Y-m-d H:i:s'))->toBe('2026-07-15 08:30:00')
    ;
});

\it('leaves the release date null when the package has none', function (): void {
    $package = new Composer\Package\CompletePackage('vendor/pkg', '1.2.3.0', '1.2.3');

    \expect(VersionTarget::fromPackage($package)->releaseDate)->toBeNull();
});

\it('prefers the published date over the release date', function (): void {
    $package = new Composer\Package\CompletePackage('vendor/pkg', '1.2.3.0', '1.2.3');
    $package->setReleaseDate(new DateTime('2026-07-01 08:00:00'));
    $package->setPublishedDate(new DateTimeImmutable('2026-07-10 09:00:00'));

    \expect(VersionTarget::fromPackage($package)->releaseDate?->format('Y-m-d H:i:s'))->toBe('2026-07-10 09:00:00');
});

\it('falls back to the release date without a published date', function (): void {
    $package = new Composer\Package\CompletePackage('vendor/pkg', '1.2.3.0', '1.2.3');
    $package->setReleaseDate(new DateTime('2026-07-01 08:00:00'));

    \expect(VersionTarget::fromPackage($package)->releaseDate?->format('Y-m-d H:i:s'))->toBe('2026-07-01 08:00:00');
});

\it('is not cooldown exempt by default', function (): void {
    \expect(VersionTarget::fromRaw('1.2.3')->isCooldownExempt)->toBeFalse();
});

\it('keeps a cooldown exemption passed to fromRaw', function (): void {
    \expect(VersionTarget::fromRaw('1.2.3', null, true)->isCooldownExempt)->toBeTrue();
});

\it('takes the cooldown exemption from the policy', function (): void {
    $cooldownPolicy = CooldownPolicy::fromComposerConfig(\cooldownConfig(86400));

    $devPackage    = new Composer\Package\CompletePackage('vendor/pkg', 'dev-main', 'dev-main');
    $stablePackage = new Composer\Package\CompletePackage('vendor/pkg', '1.2.3.0', '1.2.3');

    \expect(VersionTarget::fromPackage($devPackage, $cooldownPolicy)->isCooldownExempt)->toBeTrue()
        ->and(VersionTarget::fromPackage($stablePackage, $cooldownPolicy)->isCooldownExempt)->toBeFalse()
    ;
});
