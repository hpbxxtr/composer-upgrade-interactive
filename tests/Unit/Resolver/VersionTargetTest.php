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
