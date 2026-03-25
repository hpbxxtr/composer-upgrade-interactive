<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\Url\GitlabUrlResolver;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;

function gitlabPkg(string $repoUrl, string $current = '1.0.0'): OutdatedPackage
{
    return new OutdatedPackage(
        name: 'vendor/pkg',
        current: $current,
        currentRaw: $current,
        patch: null,
        minor: VersionTarget::fromRaw('v1.1.0'),
        major: null,
        isDev: false,
        repoUrl: $repoUrl,
    );
}

\it('matches a gitlab.com HTTPS URL', function (): void {
    $resolver = new GitlabUrlResolver(\gitlabPkg('https://gitlab.com/vendor/pkg.git'));

    \expect($resolver->isMatch())->toBeTrue();
});

\it('matches a gitlab.com SSH URL', function (): void {
    $resolver = new GitlabUrlResolver(\gitlabPkg('git@gitlab.com:vendor/pkg.git'));

    \expect($resolver->isMatch())->toBeTrue();
});

\it('does not match a non-GitLab URL', function (): void {
    $resolver = new GitlabUrlResolver(\gitlabPkg('https://github.com/vendor/pkg.git'));

    \expect($resolver->isMatch())->toBeFalse();
});

\it('builds the correct compare URL', function (): void {
    $urlResult = (new GitlabUrlResolver(\gitlabPkg('https://gitlab.com/vendor/pkg.git')))->resolve(BumpType::Minor);

    \expect($urlResult->compareUrl)->toBe('https://gitlab.com/vendor/pkg/-/compare/1.0.0...v1.1.0');
});

\it('builds the correct releases URL', function (): void {
    $urlResult = (new GitlabUrlResolver(\gitlabPkg('https://gitlab.com/vendor/pkg.git')))->resolve(BumpType::Minor);

    \expect($urlResult->releaseUrl)->toBe('https://gitlab.com/vendor/pkg/-/releases/v1.1.0');
});

\it('returns null URLs when no target exists for the requested bump type', function (): void {
    $urlResult = (new GitlabUrlResolver(\gitlabPkg('https://gitlab.com/vendor/pkg.git')))->resolve(BumpType::Patch); // patch is null

    \expect($urlResult->compareUrl)->toBeNull()
        ->and($urlResult->releaseUrl)->toBeNull()
    ;
});
