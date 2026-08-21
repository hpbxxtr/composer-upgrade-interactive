<?php

declare(strict_types=1);

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
