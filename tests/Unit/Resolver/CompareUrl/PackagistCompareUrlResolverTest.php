<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\Url\PackagistUrlResolver;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;

function packagistPkg(string $name = 'vendor/pkg'): OutdatedPackage
{
    return new OutdatedPackage(
        name: $name,
        current: '1.0.0',
        currentRaw: '1.0.0',
        patch: null,
        minor: VersionTarget::fromRaw('1.1.0'),
        major: null,
        isDev: false,
        repoUrl: '',
    );
}

\it('always matches', function (): void {
    $resolver = new PackagistUrlResolver(\packagistPkg());

    \expect($resolver->isMatch())->toBeTrue();
});

\it('builds the correct compare URL', function (): void {
    $urlResult = (new PackagistUrlResolver(\packagistPkg('vendor/pkg-compare')))->resolve(BumpType::Minor);

    \expect($urlResult->compareUrl)->toBe('https://packagist.org/packages/vendor/pkg-compare#releases');
});

\it('builds the correct releases URL', function (): void {
    $urlResult = (new PackagistUrlResolver(\packagistPkg('vendor/pkg-release')))->resolve(BumpType::Minor);

    \expect($urlResult->releaseUrl)->toBe('https://packagist.org/packages/vendor/pkg-release#releases');
});
