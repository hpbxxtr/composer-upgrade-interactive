<?php

declare(strict_types=1);

use Composer\Composer;
use Composer\Package\BasePackage;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\RootPackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Repository\ArrayRepository;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Repository\RepositorySet;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\PackageResolver;

afterEach(function (): void {
    \Mockery::close();
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a CompletePackage with normalised version derived from $prettyVersion.
 */
function makeInstalledPackage(string $name, string $prettyVersion): CompletePackage
{
    $vp = new VersionParser();

    return new CompletePackage($name, $vp->normalize($prettyVersion), $prettyVersion);
}

/**
 * Build an abandoned CompletePackage.
 *
 * @param string|true $replacement string = replacement package name, true = no replacement stated
 */
function makeAbandonedInstalledPackage(string $name, string $prettyVersion, string|bool $replacement = true): CompletePackage
{
    $completePackage = \makeInstalledPackage($name, $prettyVersion);
    $completePackage->setAbandoned($replacement);

    return $completePackage;
}

/**
 * Build a RepositorySet containing the given available packages.
 *
 * @param list<CompletePackage> $packages
 */
function makeVersionSet(array $packages): RepositorySet
{
    $set = new RepositorySet('stable', []);
    $set->addRepository(new ArrayRepository($packages));

    return $set;
}

/**
 * Build a Composer mock wired with an InstalledRepository returning $installedPackages.
 *
 * @param list<CompletePackage> $installedPackages
 * @param list<string>          $devPackageNames   package names that live in require-dev
 */
function mockComposerWithInstalled(array $installedPackages, array $devPackageNames = []): Composer
{
    $mock = \Mockery::mock(InstalledRepositoryInterface::class);
    $mock->shouldReceive('getPackages')->andReturn($installedPackages);

    $repositoryManager = \Mockery::mock(RepositoryManager::class);
    $repositoryManager->shouldReceive('getLocalRepository')->andReturn($mock);

    $devRequires  = [];
    $prodRequires = [];

    foreach ($devPackageNames as $devPackageName) {
        $devRequires[$devPackageName] = \Mockery::mock(Link::class);
    }

    foreach ($installedPackages as $installedPackage) {
        if (!isset($devRequires[$installedPackage->getName()])) {
            $prodRequires[$installedPackage->getName()] = \Mockery::mock(Link::class);
        }
    }

    $rootPkg = \Mockery::mock(RootPackageInterface::class);
    $rootPkg->shouldReceive('getDevRequires')->andReturn($devRequires);
    $rootPkg->shouldReceive('getRequires')->andReturn($prodRequires);
    $rootPkg->shouldReceive('getMinimumStability')->andReturn('stable');
    $rootPkg->shouldReceive('getStabilityFlags')->andReturn([]);
    $rootPkg->shouldReceive('getPreferStable')->andReturn(false);

    $composer = \Mockery::mock(Composer::class);
    $composer->shouldReceive('getPackage')->andReturn($rootPkg);
    $composer->shouldReceive('getRepositoryManager')->andReturn($repositoryManager);

    return $composer;
}

// ---------------------------------------------------------------------------
// Returns empty list
// ---------------------------------------------------------------------------

\it('returns empty array when no packages are outdated', function (): void {
    $completePackage = \makeInstalledPackage('vendor/pkg', '1.0.0');
    $available = \makeInstalledPackage('vendor/pkg', '1.0.0'); // same version → nothing to do

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$completePackage]),
        \makeVersionSet([$available]),
    ))->resolve();

    \expect($packages)->toBe([]);
});

\it('returns empty array when installed list is empty', function (): void {
    $packages = (new PackageResolver(
        \mockComposerWithInstalled([]),
        \makeVersionSet([]),
    ))->resolve();

    \expect($packages)->toBe([]);
});

// ---------------------------------------------------------------------------
// Basic resolution
// ---------------------------------------------------------------------------

\it('resolves a minor update for a single package', function (): void {
    $completePackage = \makeInstalledPackage('vendor/pkg', '1.0.0');
    $available = \makeInstalledPackage('vendor/pkg', '1.1.0');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$completePackage]),
        \makeVersionSet([$available]),
    ))->resolve();

    \expect($packages)->toHaveCount(1);
    \expect($packages[0])->toBeInstanceOf(OutdatedPackage::class);
    \expect($packages[0]->name)->toBe('vendor/pkg');
    \expect($packages[0]->minor?->version)->toBe('1.1.0');
    \expect($packages[0]->patch)->toBeNull();
    \expect($packages[0]->major)->toBeNull();
});

\it('resolves a patch update for a single package', function (): void {
    $completePackage = \makeInstalledPackage('vendor/pkg', '1.0.0');
    $available = \makeInstalledPackage('vendor/pkg', '1.0.1');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$completePackage]),
        \makeVersionSet([$available]),
    ))->resolve();

    \expect($packages[0]->patch?->version)->toBe('1.0.1');
    \expect($packages[0]->minor)->toBeNull();
    \expect($packages[0]->major)->toBeNull();
});

\it('resolves all three bump types independently', function (): void {
    $completePackage = \makeInstalledPackage('vendor/pkg', '1.0.0');
    $patchPkg  = \makeInstalledPackage('vendor/pkg', '1.0.1');
    $minorPkg  = \makeInstalledPackage('vendor/pkg', '1.1.0');
    $majorPkg  = \makeInstalledPackage('vendor/pkg', '2.0.0');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$completePackage]),
        \makeVersionSet([$patchPkg, $minorPkg, $majorPkg]),
    ))->resolve();

    \expect($packages)->toHaveCount(1);
    \expect($packages[0]->patch?->version)->toBe('1.0.1');
    \expect($packages[0]->minor?->version)->toBe('1.1.0');
    \expect($packages[0]->major?->version)->toBe('2.0.0');
});

// ---------------------------------------------------------------------------
// Deduplication
// ---------------------------------------------------------------------------

\it('nulls minor when patch and minor report the same version', function (): void {
    // 1.0.1 satisfies both ~1.0.0 (>=1.0.0 <1.1.0) and ^1.0.0 (>=1.0.0 <2.0.0),
    // so both constraints resolve to the same candidate — minor should be deduped.
    $completePackage = \makeInstalledPackage('vendor/pkg', '1.0.0');
    $available = \makeInstalledPackage('vendor/pkg', '1.0.1');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$completePackage]),
        \makeVersionSet([$available]),
    ))->resolve();

    \expect($packages[0]->patch?->version)->toBe('1.0.1');
    \expect($packages[0]->minor)->toBeNull();
});

\it('nulls minor when only a major version is available', function (): void {
    // No version in the 1.x range → minor constraint (^1.0.0) finds nothing;
    // major constraint (>=2.0.0) finds 2.0.0.
    $completePackage = \makeInstalledPackage('vendor/pkg', '1.0.0');
    $major     = \makeInstalledPackage('vendor/pkg', '2.0.0');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$completePackage]),
        \makeVersionSet([$major]),
    ))->resolve();

    \expect($packages[0]->minor)->toBeNull();
    \expect($packages[0]->major?->version)->toBe('2.0.0');
});

// ---------------------------------------------------------------------------
// Stability flags / prefer-stable
// ---------------------------------------------------------------------------

\it('resolves an update when the package has a per-package stability flag', function (): void {
    $completePackage = \makeInstalledPackage('vendor/pkg', '1.0.0');
    $available = \makeInstalledPackage('vendor/pkg', '1.0.1');

    $mock = \Mockery::mock(InstalledRepositoryInterface::class);
    $mock->shouldReceive('getPackages')->andReturn([$completePackage]);

    $repositoryManager = \Mockery::mock(RepositoryManager::class);
    $repositoryManager->shouldReceive('getLocalRepository')->andReturn($mock);

    $rootPkg = \Mockery::mock(RootPackageInterface::class);
    $rootPkg->shouldReceive('getDevRequires')->andReturn([]);
    $rootPkg->shouldReceive('getRequires')->andReturn(['vendor/pkg' => \Mockery::mock(Link::class)]);
    $rootPkg->shouldReceive('getMinimumStability')->andReturn('stable');
    // beta stability flag for this package — exercises the stabilityFlags branch
    $rootPkg->shouldReceive('getStabilityFlags')->andReturn(['vendor/pkg' => BasePackage::STABILITIES['beta']]);
    $rootPkg->shouldReceive('getPreferStable')->andReturn(false);

    $composer = \Mockery::mock(Composer::class);
    $composer->shouldReceive('getPackage')->andReturn($rootPkg);
    $composer->shouldReceive('getRepositoryManager')->andReturn($repositoryManager);

    $packages = (new PackageResolver($composer, \makeVersionSet([$available])))->resolve();

    \expect($packages)->toHaveCount(1);
    \expect($packages[0]->patch?->version)->toBe('1.0.1');
});

\it('uses installed package stability as best stability when prefer-stable is enabled', function (): void {
    $completePackage = \makeInstalledPackage('vendor/pkg', '1.0.0');
    $available = \makeInstalledPackage('vendor/pkg', '1.0.1');

    $mock = \Mockery::mock(InstalledRepositoryInterface::class);
    $mock->shouldReceive('getPackages')->andReturn([$completePackage]);

    $repositoryManager = \Mockery::mock(RepositoryManager::class);
    $repositoryManager->shouldReceive('getLocalRepository')->andReturn($mock);

    $rootPkg = \Mockery::mock(RootPackageInterface::class);
    $rootPkg->shouldReceive('getDevRequires')->andReturn([]);
    $rootPkg->shouldReceive('getRequires')->andReturn(['vendor/pkg' => \Mockery::mock(Link::class)]);
    $rootPkg->shouldReceive('getMinimumStability')->andReturn('stable');
    $rootPkg->shouldReceive('getStabilityFlags')->andReturn([]);
    $rootPkg->shouldReceive('getPreferStable')->andReturn(true); // exercises the $isPreferStable true branch

    $composer = \Mockery::mock(Composer::class);
    $composer->shouldReceive('getPackage')->andReturn($rootPkg);
    $composer->shouldReceive('getRepositoryManager')->andReturn($repositoryManager);

    $packages = (new PackageResolver($composer, \makeVersionSet([$available])))->resolve();

    \expect($packages)->toHaveCount(1);
    \expect($packages[0]->patch?->version)->toBe('1.0.1');
});

// ---------------------------------------------------------------------------
// Dev-branch packages
// ---------------------------------------------------------------------------

\it('returns no update for a dev-branch package when no newer candidate is found', function (): void {
    $installed = new CompletePackage('vendor/pkg', 'dev-main', 'dev-main');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$installed]),
        \makeVersionSet([]), // nothing in the repo matches the dev-main constraint
    ))->resolve();

    // Dev-branch with no candidate → all targets null → excluded from results.
    \expect($packages)->toBe([]);
});

// ---------------------------------------------------------------------------
// computeMajorConstraint — null return for non-standard version format
// ---------------------------------------------------------------------------

\it('sets majorTarget to null when computeMajorConstraint cannot parse the version', function (): void {
    // A package whose getVersion() returns a single-segment string ('1') has no dot,
    // so computeMajorConstraint's regex fails → returns null → majorTarget stays null.
    $installed = new CompletePackage('vendor/pkg', '1', '1');
    $completePackage = \makeInstalledPackage('vendor/pkg', '1.0.1');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$installed]),
        \makeVersionSet([$completePackage]),
    ))->resolve();

    \expect($packages)->toHaveCount(1)
        ->and($packages[0]->major)->toBeNull();
});

// ---------------------------------------------------------------------------
// Non-direct dependency filtering
// ---------------------------------------------------------------------------

\it('excludes transitive dependencies not listed in require or require-dev', function (): void {
    $completePackage = \makeInstalledPackage('vendor/transitive', '1.0.0');
    $newer      = \makeInstalledPackage('vendor/transitive', '2.0.0');

    $mock = \Mockery::mock(InstalledRepositoryInterface::class);
    $mock->shouldReceive('getPackages')->andReturn([$completePackage]);

    $repositoryManager = \Mockery::mock(RepositoryManager::class);
    $repositoryManager->shouldReceive('getLocalRepository')->andReturn($mock);

    $rootPkg = \Mockery::mock(RootPackageInterface::class);
    $rootPkg->shouldReceive('getDevRequires')->andReturn([]);
    $rootPkg->shouldReceive('getRequires')->andReturn([]); // transitive NOT listed as direct
    $rootPkg->shouldReceive('getMinimumStability')->andReturn('stable');
    $rootPkg->shouldReceive('getStabilityFlags')->andReturn([]);
    $rootPkg->shouldReceive('getPreferStable')->andReturn(false);

    $composer = \Mockery::mock(Composer::class);
    $composer->shouldReceive('getPackage')->andReturn($rootPkg);
    $composer->shouldReceive('getRepositoryManager')->andReturn($repositoryManager);

    $packages = (new PackageResolver($composer, \makeVersionSet([$newer])))->resolve();

    \expect($packages)->toBe([]);
});

// ---------------------------------------------------------------------------
// Abandoned packages
// ---------------------------------------------------------------------------

\it('marks an abandoned package with a replacement', function (): void {
    $completePackage = \makeAbandonedInstalledPackage('vendor/old', '1.0.0', 'vendor/new');
    $available = \makeInstalledPackage('vendor/old', '1.0.1');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$completePackage]),
        \makeVersionSet([$available]),
    ))->resolve();

    \expect($packages[0]->abandonedBy)->toBe('vendor/new');
});

\it('marks an abandoned package with no stated replacement as empty string', function (): void {
    $completePackage = \makeAbandonedInstalledPackage('vendor/old', '1.0.0', true);
    $available = \makeInstalledPackage('vendor/old', '1.0.1');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$completePackage]),
        \makeVersionSet([$available]),
    ))->resolve();

    \expect($packages[0]->abandonedBy)->toBe('');
});

\it('does not mark a non-abandoned package', function (): void {
    $completePackage = \makeInstalledPackage('vendor/pkg', '1.0.0');
    $available = \makeInstalledPackage('vendor/pkg', '1.0.1');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$completePackage]),
        \makeVersionSet([$available]),
    ))->resolve();

    \expect($packages[0]->abandonedBy)->toBeNull();
});

// ---------------------------------------------------------------------------
// Dev packages
// ---------------------------------------------------------------------------

\it('marks packages listed in require-dev as dev', function (): void {
    $completePackage = \makeInstalledPackage('vendor/dev-pkg', '1.0.0');
    $available = \makeInstalledPackage('vendor/dev-pkg', '1.0.1');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$completePackage], ['vendor/dev-pkg']),
        \makeVersionSet([$available]),
    ))->resolve();

    \expect($packages[0]->isDev)->toBeTrue();
});

\it('marks packages not in require-dev as prod', function (): void {
    $completePackage = \makeInstalledPackage('vendor/pkg', '1.0.0');
    $available = \makeInstalledPackage('vendor/pkg', '1.0.1');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$completePackage], []),
        \makeVersionSet([$available]),
    ))->resolve();

    \expect($packages[0]->isDev)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Sorting
// ---------------------------------------------------------------------------

\it('sorts prod packages before dev packages', function (): void {
    $completePackage    = \makeInstalledPackage('vendor/dev-a', '1.0.0');
    $prodPkg   = \makeInstalledPackage('vendor/prod-b', '1.0.0');
    $newerDev  = \makeInstalledPackage('vendor/dev-a', '1.0.1');
    $newerProd = \makeInstalledPackage('vendor/prod-b', '1.0.1');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$completePackage, $prodPkg], ['vendor/dev-a']),
        \makeVersionSet([$newerDev, $newerProd]),
    ))->resolve();

    \expect($packages[0]->name)->toBe('vendor/prod-b');
    \expect($packages[1]->name)->toBe('vendor/dev-a');
});

\it('sorts packages alphabetically within the same section', function (): void {
    $completePackage   = \makeInstalledPackage('vendor/z-pkg', '1.0.0');
    $aPkg   = \makeInstalledPackage('vendor/a-pkg', '1.0.0');
    $newerZ = \makeInstalledPackage('vendor/z-pkg', '1.0.1');
    $newerA = \makeInstalledPackage('vendor/a-pkg', '1.0.1');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$completePackage, $aPkg]),
        \makeVersionSet([$newerZ, $newerA]),
    ))->resolve();

    \expect($packages[0]->name)->toBe('vendor/a-pkg');
    \expect($packages[1]->name)->toBe('vendor/z-pkg');
});

// ---------------------------------------------------------------------------
// Meta / repoUrl
// ---------------------------------------------------------------------------

\it('uses source URL from the installed package', function (): void {
    $completePackage = \makeInstalledPackage('vendor/pkg', '1.0.0');
    $completePackage->setSourceUrl('https://github.com/vendor/pkg.git');
    $available = \makeInstalledPackage('vendor/pkg', '1.0.1');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$completePackage]),
        \makeVersionSet([$available]),
    ))->resolve();

    \expect($packages[0]->repoUrl)->toBe('https://github.com/vendor/pkg.git');
});

\it('falls back to homepage when source URL is absent', function (): void {
    $completePackage = \makeInstalledPackage('vendor/pkg', '1.0.0');
    $completePackage->setHomepage('https://example.com/vendor/pkg');
    $available = \makeInstalledPackage('vendor/pkg', '1.0.1');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$completePackage]),
        \makeVersionSet([$available]),
    ))->resolve();

    \expect($packages[0]->repoUrl)->toBe('https://example.com/vendor/pkg');
});

\it('returns empty string when neither source URL nor homepage is set', function (): void {
    $completePackage = \makeInstalledPackage('vendor/pkg', '1.0.0');
    $available = \makeInstalledPackage('vendor/pkg', '1.0.1');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$completePackage]),
        \makeVersionSet([$available]),
    ))->resolve();

    \expect($packages[0]->repoUrl)->toBe('');
});

\it('returns empty repoUrl for a non-CompletePackage without a source URL', function (): void {
    $vp        = new VersionParser();
    // Package (not CompletePackage) does not implement CompletePackageInterface,
    // so the sourceUrl helper falls through to the bare `return ''` at the end.
    $installed = new \Composer\Package\Package('vendor/pkg', $vp->normalize('1.0.0'), '1.0.0');
    $completePackage = \makeInstalledPackage('vendor/pkg', '1.0.1');

    $mock = \Mockery::mock(InstalledRepositoryInterface::class);
    $mock->shouldReceive('getPackages')->andReturn([$installed]);

    $repositoryManager = \Mockery::mock(RepositoryManager::class);
    $repositoryManager->shouldReceive('getLocalRepository')->andReturn($mock);

    $rootPkg = \Mockery::mock(RootPackageInterface::class);
    $rootPkg->shouldReceive('getDevRequires')->andReturn([]);
    $rootPkg->shouldReceive('getRequires')->andReturn(['vendor/pkg' => \Mockery::mock(Link::class)]);
    $rootPkg->shouldReceive('getMinimumStability')->andReturn('stable');
    $rootPkg->shouldReceive('getStabilityFlags')->andReturn([]);
    $rootPkg->shouldReceive('getPreferStable')->andReturn(false);

    $composer = \Mockery::mock(Composer::class);
    $composer->shouldReceive('getPackage')->andReturn($rootPkg);
    $composer->shouldReceive('getRepositoryManager')->andReturn($repositoryManager);

    $packages = (new PackageResolver($composer, \makeVersionSet([$completePackage])))->resolve();

    \expect($packages)->toHaveCount(1);
    \expect($packages[0]->repoUrl)->toBe('');
});

// ---------------------------------------------------------------------------
// current / currentRaw
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// buildRepositorySet() + buildPlatformRepo() — exercised when neither is injected
// ---------------------------------------------------------------------------

\it('builds RepositorySet and PlatformRepository from Composer when neither is injected', function (): void {
    $mock = \Mockery::mock(InstalledRepositoryInterface::class);
    $mock->shouldReceive('getPackages')->andReturn([]);

    $repositoryManager = \Mockery::mock(RepositoryManager::class);
    $repositoryManager->shouldReceive('getLocalRepository')->andReturn($mock);
    $repositoryManager->shouldReceive('getRepositories')->andReturn([]);

    $config = \Mockery::mock(\Composer\Config::class);
    $config->shouldReceive('get')->andReturn([]);

    $rootPkg = \Mockery::mock(RootPackageInterface::class);
    $rootPkg->shouldReceive('getDevRequires')->andReturn([]);
    $rootPkg->shouldReceive('getRequires')->andReturn([]);
    $rootPkg->shouldReceive('getMinimumStability')->andReturn('stable');
    $rootPkg->shouldReceive('getStabilityFlags')->andReturn([]);
    $rootPkg->shouldReceive('getPreferStable')->andReturn(false);

    $composer = \Mockery::mock(Composer::class);
    $composer->shouldReceive('getPackage')->andReturn($rootPkg);
    $composer->shouldReceive('getRepositoryManager')->andReturn($repositoryManager);
    $composer->shouldReceive('getConfig')->andReturn($config);

    // No $repositorySet injected → buildRepositorySet() (lines 216–221) and
    // buildPlatformRepo() (lines 226–231) are both called.
    $packages = (new PackageResolver($composer))->resolve();

    \expect($packages)->toBe([]);
});

\it('strips leading v from current version display', function (): void {
    $completePackage = \makeInstalledPackage('vendor/pkg', 'v1.2.3');
    $available = \makeInstalledPackage('vendor/pkg', 'v1.2.4');

    $packages = (new PackageResolver(
        \mockComposerWithInstalled([$completePackage]),
        \makeVersionSet([$available]),
    ))->resolve();

    \expect($packages[0]->current)->toBe('1.2.3');
    \expect($packages[0]->currentRaw)->toBe('v1.2.3');
});
