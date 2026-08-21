<?php

declare(strict_types=1);

use Composer\Composer;
use Composer\Package\CompletePackage;
use Composer\Package\Version\VersionParser;
use Composer\Repository\ArrayRepository;
use Composer\Repository\RepositoryManager;
use Composer\Repository\RepositorySet;
use Hpbxxtr\UpgradeInteractive\Resolver\AvailableVersionsResolver;
use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;

afterEach(function (): void {
    \Mockery::close();
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a CompletePackage representing an available version in the registry.
 */
function makeAvailPackage(string $name, string $prettyVersion): CompletePackage
{
    $vp = new VersionParser();

    return new CompletePackage($name, $vp->normalize($prettyVersion), $prettyVersion);
}

/**
 * Build a RepositorySet containing the given packages.
 *
 * @param list<CompletePackage> $packages
 */
function makeAvailRepoSet(array $packages): RepositorySet
{
    $set = new RepositorySet('stable', []);
    $set->addRepository(new ArrayRepository($packages));

    return $set;
}

// ---------------------------------------------------------------------------
// Stability filter
// ---------------------------------------------------------------------------

\it('excludes pre-release versions', function (): void {
    $packages = [
        \makeAvailPackage('vendor/pkg', '1.2.0'),
        \makeAvailPackage('vendor/pkg', '1.2.0-beta.1'),
        \makeAvailPackage('vendor/pkg', '1.1.5-RC1'),
        \makeAvailPackage('vendor/pkg', '1.1.4'),
    ];
    $resolver = new AvailableVersionsResolver(\Mockery::mock(Composer::class), \makeAvailRepoSet($packages));
    $result   = $resolver->resolve('vendor/pkg', '1.0.0', BumpType::Minor);

    \expect($result)->toHaveCount(2)
        ->and($result[0]->version)->toBe('1.2.0')
        ->and($result[1]->version)->toBe('1.1.4')
    ;
});

\it('excludes alpha, dev and beta versions', function (): void {
    $packages = [
        \makeAvailPackage('vendor/pkg', '2.0.0'),
        \makeAvailPackage('vendor/pkg', '2.0.0-alpha.1'),
        \makeAvailPackage('vendor/pkg', '2.0.0-beta.1'),
        \makeAvailPackage('vendor/pkg', '1.5.0'),
    ];
    $resolver = new AvailableVersionsResolver(\Mockery::mock(Composer::class), \makeAvailRepoSet($packages));
    $result   = $resolver->resolve('vendor/pkg', '1.0.0', BumpType::Major);

    \expect($result)->toHaveCount(1)
        ->and($result[0]->version)->toBe('2.0.0')
    ;
});

// ---------------------------------------------------------------------------
// Bump-type filtering — Patch
// ---------------------------------------------------------------------------

\it('returns only patch versions (same major.minor, higher patch)', function (): void {
    $packages = [
        \makeAvailPackage('vendor/pkg', '1.2.5'),
        \makeAvailPackage('vendor/pkg', '1.2.4'),
        \makeAvailPackage('vendor/pkg', '1.3.0'),
        \makeAvailPackage('vendor/pkg', '2.0.0'),
        \makeAvailPackage('vendor/pkg', '1.2.2'),
    ];
    $resolver = new AvailableVersionsResolver(\Mockery::mock(Composer::class), \makeAvailRepoSet($packages));
    $result   = $resolver->resolve('vendor/pkg', '1.2.3', BumpType::Patch);

    \expect($result)->toHaveCount(2)
        ->and($result[0]->version)->toBe('1.2.5')
        ->and($result[1]->version)->toBe('1.2.4')
    ;
});

// ---------------------------------------------------------------------------
// Bump-type filtering — Minor
// ---------------------------------------------------------------------------

\it('returns only minor versions (same major, higher minor)', function (): void {
    $packages = [
        \makeAvailPackage('vendor/pkg', '1.4.0'),
        \makeAvailPackage('vendor/pkg', '1.3.2'),
        \makeAvailPackage('vendor/pkg', '1.2.9'),
        \makeAvailPackage('vendor/pkg', '0.9.0'),
        \makeAvailPackage('vendor/pkg', '2.0.0'),
    ];
    $resolver = new AvailableVersionsResolver(\Mockery::mock(Composer::class), \makeAvailRepoSet($packages));
    $result   = $resolver->resolve('vendor/pkg', '1.2.3', BumpType::Minor);

    \expect($result)->toHaveCount(2)
        ->and($result[0]->version)->toBe('1.4.0')
        ->and($result[1]->version)->toBe('1.3.2')
    ;
});

// ---------------------------------------------------------------------------
// Bump-type filtering — Major
// ---------------------------------------------------------------------------

\it('returns only major versions (higher major)', function (): void {
    $packages = [
        \makeAvailPackage('vendor/pkg', '3.0.0'),
        \makeAvailPackage('vendor/pkg', '2.5.0'),
        \makeAvailPackage('vendor/pkg', '1.9.0'),
        \makeAvailPackage('vendor/pkg', '1.2.3'),
    ];
    $resolver = new AvailableVersionsResolver(\Mockery::mock(Composer::class), \makeAvailRepoSet($packages));
    $result   = $resolver->resolve('vendor/pkg', '1.2.3', BumpType::Major);

    \expect($result)->toHaveCount(2)
        ->and($result[0]->version)->toBe('3.0.0')
        ->and($result[1]->version)->toBe('2.5.0')
    ;
});

// ---------------------------------------------------------------------------
// v-prefixed raw version tags
// ---------------------------------------------------------------------------

\it('strips leading v from version for display but keeps raw in versionRaw', function (): void {
    $packages = [
        \makeAvailPackage('vendor/pkg', 'v1.3.0'),
        \makeAvailPackage('vendor/pkg', 'v1.2.5'),
    ];
    $resolver = new AvailableVersionsResolver(\Mockery::mock(Composer::class), \makeAvailRepoSet($packages));
    $result   = $resolver->resolve('vendor/pkg', '1.2.3', BumpType::Minor);

    \expect($result)->toHaveCount(1)
        ->and($result[0])->toBeInstanceOf(VersionTarget::class)
        ->and($result[0]->version)->toBe('1.3.0')
        ->and($result[0]->versionRaw)->toBe('v1.3.0')
    ;
});

// ---------------------------------------------------------------------------
// Current version also with v-prefix
// ---------------------------------------------------------------------------

\it('handles v-prefixed current version when filtering', function (): void {
    $packages = [
        \makeAvailPackage('vendor/pkg', '1.3.0'),
        \makeAvailPackage('vendor/pkg', '1.2.5'),
        \makeAvailPackage('vendor/pkg', '1.2.2'),
    ];
    $resolver = new AvailableVersionsResolver(\Mockery::mock(Composer::class), \makeAvailRepoSet($packages));
    $result   = $resolver->resolve('vendor/pkg', 'v1.2.3', BumpType::Patch);

    \expect($result)->toHaveCount(1)
        ->and($result[0]->version)->toBe('1.2.5')
    ;
});

// ---------------------------------------------------------------------------
// Empty result when no versions match
// ---------------------------------------------------------------------------

\it('returns empty array when no versions satisfy the bump filter', function (): void {
    $packages = [
        \makeAvailPackage('vendor/pkg', '1.2.2'),
        \makeAvailPackage('vendor/pkg', '1.2.1'),
        \makeAvailPackage('vendor/pkg', '1.1.0'),
    ];
    $resolver = new AvailableVersionsResolver(\Mockery::mock(Composer::class), \makeAvailRepoSet($packages));

    \expect($resolver->resolve('vendor/pkg', '1.2.3', BumpType::Patch))->toBe([]);
});

// ---------------------------------------------------------------------------
// Invalid current version
// ---------------------------------------------------------------------------

\it('returns empty array when current version cannot be parsed', function (): void {
    $packages = [
        \makeAvailPackage('vendor/pkg', '1.3.0'),
        \makeAvailPackage('vendor/pkg', '1.2.5'),
    ];
    $resolver = new AvailableVersionsResolver(\Mockery::mock(Composer::class), \makeAvailRepoSet($packages));

    // 'abc' has no numeric version segments — VersionParser::normalize() throws UnexpectedValueException
    \expect($resolver->resolve('vendor/pkg', 'abc', BumpType::Minor))->toBe([]);
});

// ---------------------------------------------------------------------------
// Deduplication
// ---------------------------------------------------------------------------

\it('deduplicates identical versions present multiple times in the repository', function (): void {
    $packages = [
        \makeAvailPackage('vendor/pkg', '1.3.0'),
        \makeAvailPackage('vendor/pkg', '1.3.0'),
        \makeAvailPackage('vendor/pkg', '1.4.0'),
    ];
    $resolver = new AvailableVersionsResolver(\Mockery::mock(Composer::class), \makeAvailRepoSet($packages));
    $result   = $resolver->resolve('vendor/pkg', '1.2.0', BumpType::Minor);

    \expect($result)->toHaveCount(2)
        ->and($result[0]->version)->toBe('1.4.0')
        ->and($result[1]->version)->toBe('1.3.0')
    ;
});

// ---------------------------------------------------------------------------
// Stability filter
// ---------------------------------------------------------------------------

\it('excludes pre-release versions that pass through a dev-stability repository', function (): void {
    // A RepositorySet with 'stable' min-stability silently drops pre-release packages in
    // findPackages(), so the stability continue (line 59) is never reached.
    // Using 'dev' min-stability lets all packages through findPackages() and exercises
    // the explicit stability guard inside the resolve() loop.
    $packages = [
        \makeAvailPackage('vendor/pkg', '1.3.0'),
        \makeAvailPackage('vendor/pkg', '1.3.0-beta.1'),
    ];
    $repoSet = new RepositorySet('dev', []);
    $repoSet->addRepository(new ArrayRepository($packages));

    $resolver = new AvailableVersionsResolver(\Mockery::mock(Composer::class), $repoSet);
    $result   = $resolver->resolve('vendor/pkg', '1.2.0', BumpType::Minor);

    \expect($result)->toHaveCount(1)
        ->and($result[0]->version)->toBe('1.3.0');
});

// ---------------------------------------------------------------------------
// parseSegments failure inside loop
// ---------------------------------------------------------------------------

\it('skips a package whose pretty version passes the stability check but cannot be normalized', function (): void {
    // '1.2.0.0' is a valid normalized version (so ArrayRepository accepts the package),
    // but the prettyVersion 'not-a-version' has no numeric segments → normalize() throws
    // UnexpectedValueException inside parseSegments() → line 65 (continue) is reached.
    $weirdPkg = new CompletePackage('vendor/pkg', '1.2.0.0', 'not-a-version');
    $completePackage = \makeAvailPackage('vendor/pkg', '1.1.0');

    $repoSet = new RepositorySet('stable', []);
    $repoSet->addRepository(new ArrayRepository([$weirdPkg, $completePackage]));

    $resolver = new AvailableVersionsResolver(\Mockery::mock(Composer::class), $repoSet);
    $result   = $resolver->resolve('vendor/pkg', '1.0.0', BumpType::Minor);

    \expect($result)->toHaveCount(1)
        ->and($result[0]->version)->toBe('1.1.0');
});

// ---------------------------------------------------------------------------
// buildRepositorySet() — exercised when no RepositorySet is injected
// ---------------------------------------------------------------------------

\it('builds its own RepositorySet from Composer when none is injected', function (): void {
    $mock = \Mockery::mock(RepositoryManager::class);
    $mock->shouldReceive('getRepositories')->andReturn([]);

    $rootPackage = \Mockery::mock(\Composer\Package\RootPackageInterface::class);
    $rootPackage->shouldReceive('getMinimumStability')->andReturn('stable');
    $rootPackage->shouldReceive('getStabilityFlags')->andReturn([]);

    $composer = \Mockery::mock(Composer::class);
    $composer->shouldReceive('getPackage')->andReturn($rootPackage);
    $composer->shouldReceive('getRepositoryManager')->andReturn($mock);

    // No RepositorySet injected → buildRepositorySet() is called internally
    $resolver = new AvailableVersionsResolver($composer);

    // Empty repo → no matching packages → empty result
    \expect($resolver->resolve('vendor/pkg', '1.0.0', BumpType::Minor))->toBe([]);
});

// ---------------------------------------------------------------------------
// Release dates
// ---------------------------------------------------------------------------

\it('carries release dates onto the resolved targets', function (): void {
    $completePackage = \makeAvailPackage('vendor/pkg', '1.2.0');
    $completePackage->setReleaseDate(new DateTime('2026-08-18 09:00:00'));
    $older = \makeAvailPackage('vendor/pkg', '1.1.0');
    $older->setReleaseDate(new DateTime('2026-01-02 09:00:00'));

    $resolver = new AvailableVersionsResolver(\Mockery::mock(Composer::class), \makeAvailRepoSet([$completePackage, $older]));
    $result   = $resolver->resolve('vendor/pkg', '1.0.0', BumpType::Minor);

    \expect($result[0]->version)->toBe('1.2.0')
        ->and($result[0]->releaseDate?->format('Y-m-d'))->toBe('2026-08-18')
        ->and($result[1]->releaseDate?->format('Y-m-d'))->toBe('2026-01-02')
    ;
});

\it('leaves the release date null when the repository has no date', function (): void {
    $resolver = new AvailableVersionsResolver(
        \Mockery::mock(Composer::class),
        \makeAvailRepoSet([\makeAvailPackage('vendor/pkg', '1.2.0')]),
    );

    \expect($resolver->resolve('vendor/pkg', '1.0.0', BumpType::Minor)[0]->releaseDate)->toBeNull();
});
