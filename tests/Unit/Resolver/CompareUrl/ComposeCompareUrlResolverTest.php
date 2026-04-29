<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\Url\ComposeUrlResolver;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;

function makePackage(string $repoUrl, string $current = '1.0.0'): OutdatedPackage
{
    return new OutdatedPackage(
        name: 'vendor/pkg',
        current: $current,
        currentRaw: $current,
        patch: null,
        minor: VersionTarget::fromRaw('1.1.0'),
        major: null,
        isDev: false,
        repoUrl: $repoUrl,
    );
}

\it('builds a GitHub compare URL', function (): void {
    $urlResult = (new ComposeUrlResolver(\makePackage('https://github.com/vendor/pkg.git')))->resolve(BumpType::Minor);

    \expect($urlResult->compareUrl)->toBe('https://github.com/vendor/pkg/compare/1.0.0...1.1.0');
});

\it('builds a GitLab compare URL', function (): void {
    $urlResult = (new ComposeUrlResolver(\makePackage('https://gitlab.com/vendor/pkg.git')))->resolve(BumpType::Minor);

    \expect($urlResult->compareUrl)->toBe('https://gitlab.com/vendor/pkg/-/compare/1.0.0...1.1.0');
});

\it('falls back to Packagist when URL is unknown', function (): void {
    $urlResult = (new ComposeUrlResolver(\makePackage('https://bitbucket.org/vendor/pkg')))->resolve(BumpType::Minor);

    \expect($urlResult->compareUrl)->toBe('https://packagist.org/packages/vendor/pkg#releases');
});

\it('returns null URLs when no target exists for the given bump type', function (): void {
    $urlResult = (new ComposeUrlResolver(\makePackage('https://github.com/vendor/pkg.git')))->resolve(BumpType::Patch); // patch is null

    \expect($urlResult->compareUrl)->toBeNull();
    \expect($urlResult->releaseUrl)->toBeNull();
});

\it('returns null URLs when constructed with an empty resolver list', function (): void {
    $urlResult = (new ComposeUrlResolver(\makePackage('https://github.com/vendor/pkg.git'), []))->resolve(BumpType::Minor);

    \expect($urlResult->compareUrl)->toBeNull()
        ->and($urlResult->releaseUrl)->toBeNull()
    ;
});

\it('isMatch returns false when constructed with an empty resolver list', function (): void {
    \expect((new ComposeUrlResolver(\makePackage('https://github.com/vendor/pkg.git'), []))->isMatch())->toBeFalse();
});

\it('builds the correct GitHub releases URL', function (): void {
    $urlResult = (new ComposeUrlResolver(\makePackage('https://github.com/vendor/pkg.git')))->resolve(BumpType::Minor);

    \expect($urlResult->releaseUrl)->toBe('https://github.com/vendor/pkg/releases/1.1.0');
});
