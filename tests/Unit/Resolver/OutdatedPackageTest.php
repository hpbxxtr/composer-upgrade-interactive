<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;

\it('returns available bumps for non-null targets only', function (): void {
    $pkg = new OutdatedPackage(
        name: 'vendor/pkg',
        current: '1.2.3',
        currentRaw: '1.2.3',
        patch: VersionTarget::fromRaw('1.2.4'),
        minor: null,
        major: VersionTarget::fromRaw('2.0.0'),
        isDev: false,
        repoUrl: '',
    );

    \expect($pkg->availableBumps())->toBe([BumpType::Patch, BumpType::Major]);
});

\it('returns the correct target for each bump type', function (): void {
    $versionTarget = VersionTarget::fromRaw('1.2.4');
    $minor         = VersionTarget::fromRaw('1.3.0');
    $major         = VersionTarget::fromRaw('2.0.0');

    $pkg = new OutdatedPackage(
        name: 'vendor/pkg',
        current: '1.2.3',
        currentRaw: '1.2.3',
        patch: $versionTarget,
        minor: $minor,
        major: $major,
        isDev: false,
        repoUrl: '',
    );

    \expect($pkg->target(BumpType::Patch))->toBe($versionTarget)
        ->and($pkg->target(BumpType::Minor))->toBe($minor)
        ->and($pkg->target(BumpType::Major))->toBe($major)
    ;
});

\it('returns empty available bumps when all targets are null', function (): void {
    $pkg = new OutdatedPackage(
        name: 'vendor/pkg',
        current: '1.2.3',
        currentRaw: '1.2.3',
        patch: null,
        minor: null,
        major: null,
        isDev: false,
        repoUrl: '',
    );

    \expect($pkg->availableBumps())->toBeEmpty();
});
