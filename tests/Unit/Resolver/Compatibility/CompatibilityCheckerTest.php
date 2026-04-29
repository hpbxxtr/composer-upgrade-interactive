<?php

declare(strict_types=1);

use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\Version\VersionParser;
use Composer\Repository\ArrayRepository;
use Composer\Repository\RepositorySet;
use Composer\Semver\Constraint\ConstraintInterface;
use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\Compatibility\CompatibilityChecker;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionSelection;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;

afterEach(function (): void {
    \Mockery::close();
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a stable CompletePackage with normalized version.
 */
function makeCheckerPackage(string $name, string $prettyVersion): CompletePackage
{
    $vp = new VersionParser();

    return new CompletePackage($name, $vp->normalize($prettyVersion), $prettyVersion);
}

/**
 * Build a Link representing a requires entry.
 */
function makeLink(string $source, string $target, string $constraint): Link
{
    $vp = new VersionParser();

    return new Link($source, $target, $vp->parseConstraints($constraint), Link::TYPE_REQUIRE, $constraint);
}

/**
 * Build a RepositorySet containing the given packages.
 *
 * @param list<CompletePackage> $packages
 */
function makeCheckerRepoSet(array $packages): RepositorySet
{
    $set = new RepositorySet('stable', []);
    $set->addRepository(new ArrayRepository($packages));

    return $set;
}

/**
 * Build a VersionSelection for use in $selections maps.
 */
function sel(string $versionRaw): VersionSelection
{
    return new VersionSelection(BumpType::Minor, VersionTarget::fromRaw($versionRaw));
}

// ---------------------------------------------------------------------------
// Counting repository to verify metadata cache
// ---------------------------------------------------------------------------

/**
 * @internal test helper
 */
class CountingArrayRepository extends ArrayRepository
{
    public int $loadPackagesCallCount = 0;

    /**
     * @param array<string, ConstraintInterface|null> $packageNameMap
     * @param array<string, int> $acceptableStabilities
     * @param array<string, int> $stabilityFlags
     * @param array<string, array<string, PackageInterface>> $alreadyLoaded
     * @return array{namesFound: array<string, true>, packages: list<\Composer\Package\BasePackage>}
     */
    #[\Override]
    public function loadPackages(array $packageNameMap, array $acceptableStabilities, array $stabilityFlags, array $alreadyLoaded = []): array
    {
        ++$this->loadPackagesCallCount;

        return parent::loadPackages($packageNameMap, $acceptableStabilities, $stabilityFlags, $alreadyLoaded);
    }
}

// ---------------------------------------------------------------------------
// No selections
// ---------------------------------------------------------------------------

describe('CompatibilityChecker', function (): void {
    it('returns no conflicts when selections is empty', function (): void {
        $completePackage = \makeCheckerPackage('vendor/pkg', '2.0.0');
        $completePackage->setRequires(['vendor/dep' => \makeLink('vendor/pkg', 'vendor/dep', '^6.4')]);

        $checker = new CompatibilityChecker(
            \Mockery::mock(\Composer\Composer::class),
            \makeCheckerRepoSet([$completePackage]),
        );

        $conflicts = $checker->checkCandidate(
            'vendor/pkg',
            VersionTarget::fromRaw('2.0.0'),
            [],
        );

        expect($conflicts)->toBe([]);
    });

    // ---------------------------------------------------------------------------
    // Forward conflicts (candidate requires dep at constraint, selection violates it)
    // ---------------------------------------------------------------------------

    it('detects a forward conflict when candidate requires dep at ^6.4 but 7.0.0 is selected', function (): void {
        $completePackage = \makeCheckerPackage('vendor/pkg', '2.0.0');
        $completePackage->setRequires(['vendor/dep' => \makeLink('vendor/pkg', 'vendor/dep', '^6.4')]);

        $checker = new CompatibilityChecker(
            \Mockery::mock(\Composer\Composer::class),
            \makeCheckerRepoSet([$completePackage]),
        );

        $conflicts = $checker->checkCandidate(
            'vendor/pkg',
            VersionTarget::fromRaw('2.0.0'),
            ['vendor/dep' => \sel('7.0.0')],
        );

        expect($conflicts)->toHaveCount(1);
        expect($conflicts[0]->dependentPackage)->toBe('vendor/pkg');
        expect($conflicts[0]->dependentVersion)->toBe('2.0.0');
        expect($conflicts[0]->requiredPackage)->toBe('vendor/dep');
        expect($conflicts[0]->requiredConstraint)->toBe('^6.4');
        expect($conflicts[0]->selectedVersion)->toBe('7.0.0');
    });

    // ---------------------------------------------------------------------------
    // Backward conflicts (selection requires candidate at constraint, candidate violates it)
    // ---------------------------------------------------------------------------

    it('detects a backward conflict when selection requires candidate at ^1.0 but candidate is 2.0.0', function (): void {
        $completePackage = \makeCheckerPackage('vendor/other', '1.5.0');
        $completePackage->setRequires(['vendor/pkg' => \makeLink('vendor/other', 'vendor/pkg', '^1.0')]);

        $checker = new CompatibilityChecker(
            \Mockery::mock(\Composer\Composer::class),
            \makeCheckerRepoSet([$completePackage]),
        );

        $conflicts = $checker->checkCandidate(
            'vendor/pkg',
            VersionTarget::fromRaw('2.0.0'),
            ['vendor/other' => \sel('1.5.0')],
        );

        expect($conflicts)->toHaveCount(1);
        expect($conflicts[0]->dependentPackage)->toBe('vendor/other');
        expect($conflicts[0]->dependentVersion)->toBe('1.5.0');
        expect($conflicts[0]->requiredPackage)->toBe('vendor/pkg');
        expect($conflicts[0]->requiredConstraint)->toBe('^1.0');
        expect($conflicts[0]->selectedVersion)->toBe('2.0.0');
    });

    // ---------------------------------------------------------------------------
    // Compatible (no conflicts)
    // ---------------------------------------------------------------------------

    it('returns no conflicts when candidate requires ^7.0 and selection is 7.2.0', function (): void {
        $completePackage = \makeCheckerPackage('vendor/pkg', '2.0.0');
        $completePackage->setRequires(['vendor/dep' => \makeLink('vendor/pkg', 'vendor/dep', '^7.0')]);

        $checker = new CompatibilityChecker(
            \Mockery::mock(\Composer\Composer::class),
            \makeCheckerRepoSet([$completePackage]),
        );

        $conflicts = $checker->checkCandidate(
            'vendor/pkg',
            VersionTarget::fromRaw('2.0.0'),
            ['vendor/dep' => \sel('7.2.0')],
        );

        expect($conflicts)->toBe([]);
    });

    it('returns no conflicts when backward selection constraint is satisfied', function (): void {
        $completePackage = \makeCheckerPackage('vendor/other', '1.5.0');
        $completePackage->setRequires(['vendor/pkg' => \makeLink('vendor/other', 'vendor/pkg', '^2.0')]);

        $checker = new CompatibilityChecker(
            \Mockery::mock(\Composer\Composer::class),
            \makeCheckerRepoSet([$completePackage]),
        );

        $conflicts = $checker->checkCandidate(
            'vendor/pkg',
            VersionTarget::fromRaw('2.3.0'),
            ['vendor/other' => \sel('1.5.0')],
        );

        expect($conflicts)->toBe([]);
    });

    // ---------------------------------------------------------------------------
    // Package not in repo → treated as compatible
    // ---------------------------------------------------------------------------

    it('returns no conflicts when candidate package is not found in repository', function (): void {
        $checker = new CompatibilityChecker(
            \Mockery::mock(\Composer\Composer::class),
            \makeCheckerRepoSet([]), // empty repo
        );

        $conflicts = $checker->checkCandidate(
            'vendor/unknown',
            VersionTarget::fromRaw('1.0.0'),
            ['vendor/dep' => \sel('2.0.0')],
        );

        expect($conflicts)->toBe([]);
    });

    it('skips backward check when selected package metadata is not found in repository', function (): void {
        $completePackage = \makeCheckerPackage('vendor/pkg', '2.0.0');
        $completePackage->setRequires([]);

        $checker = new CompatibilityChecker(
            \Mockery::mock(\Composer\Composer::class),
            \makeCheckerRepoSet([$completePackage]), // selection package not in repo
        );

        $conflicts = $checker->checkCandidate(
            'vendor/pkg',
            VersionTarget::fromRaw('2.0.0'),
            ['vendor/other' => \sel('1.5.0')], // vendor/other not in repo
        );

        expect($conflicts)->toBe([]);
    });

    // ---------------------------------------------------------------------------
    // Metadata cache — second call with same (name, version) hits cache, not repo
    // ---------------------------------------------------------------------------

    it('uses metadata cache and does not re-query repository for the same name+version', function (): void {
        $completePackage = \makeCheckerPackage('vendor/pkg', '2.0.0');
        $completePackage->setRequires([]);

        $countingRepo = new CountingArrayRepository([$completePackage]);
        $repoSet = new RepositorySet('stable', []);
        $repoSet->addRepository($countingRepo);

        $checker = new CompatibilityChecker(
            \Mockery::mock(\Composer\Composer::class),
            $repoSet,
        );

        $checker->checkCandidate('vendor/pkg', VersionTarget::fromRaw('2.0.0'), []);
        $checker->checkCandidate('vendor/pkg', VersionTarget::fromRaw('2.0.0'), []);

        // The repository should only be queried once for (vendor/pkg, 2.0.0)
        expect($countingRepo->loadPackagesCallCount)->toBe(1);
    });

    // ---------------------------------------------------------------------------
    // Backward check — unrelated requires are skipped (line 74 continue branch)
    // ---------------------------------------------------------------------------

    it('does not report a conflict when the selection requires an unrelated dep (not the candidate)', function (): void {
        // vendor/other requires vendor/unrelated, NOT vendor/pkg — backward check must skip it
        $completePackage = \makeCheckerPackage('vendor/other', '1.5.0');
        $completePackage->setRequires(['vendor/unrelated' => \makeLink('vendor/other', 'vendor/unrelated', '^3.0')]);

        $checker = new CompatibilityChecker(
            \Mockery::mock(\Composer\Composer::class),
            \makeCheckerRepoSet([$completePackage]),
        );

        $conflicts = $checker->checkCandidate(
            'vendor/pkg',
            VersionTarget::fromRaw('2.0.0'),
            ['vendor/other' => \sel('1.5.0')],
        );

        expect($conflicts)->toBe([]);
    });

    it('queries repository separately for different versions of the same package', function (): void {
        $completePackage = \makeCheckerPackage('vendor/pkg', '1.0.0');
        $completePackageTwo = \makeCheckerPackage('vendor/pkg', '2.0.0');
        $completePackage->setRequires([]);
        $completePackageTwo->setRequires([]);

        $countingRepo = new CountingArrayRepository([$completePackage, $completePackageTwo]);
        $repoSet = new RepositorySet('stable', []);
        $repoSet->addRepository($countingRepo);

        $checker = new CompatibilityChecker(
            \Mockery::mock(\Composer\Composer::class),
            $repoSet,
        );

        $checker->checkCandidate('vendor/pkg', VersionTarget::fromRaw('1.0.0'), []);
        $checker->checkCandidate('vendor/pkg', VersionTarget::fromRaw('2.0.0'), []);

        expect($countingRepo->loadPackagesCallCount)->toBe(2);
    });

    // ---------------------------------------------------------------------------
    // Installed-package conflicts
    // ---------------------------------------------------------------------------

    it('detects a forward conflict when candidate requires dep at ^2.0 but dep is installed at 1.5.0', function (): void {
        $completePackage    = \makeCheckerPackage('vendor/pkg', '2.0.0');
        $installedDep = \makeCheckerPackage('vendor/dep', '1.5.0');
        $completePackage->setRequires(['vendor/dep' => \makeLink('vendor/pkg', 'vendor/dep', '^2.0')]);
        $installedDep->setRequires([]);

        $checker = new CompatibilityChecker(
            \Mockery::mock(\Composer\Composer::class),
            \makeCheckerRepoSet([$completePackage, $installedDep]),
            installedVersions: ['vendor/dep' => '1.5.0'],
        );

        $conflicts = $checker->checkCandidate('vendor/pkg', VersionTarget::fromRaw('2.0.0'), []);

        expect($conflicts)->toHaveCount(1);
        expect($conflicts[0]->dependentPackage)->toBe('vendor/pkg');
        expect($conflicts[0]->requiredPackage)->toBe('vendor/dep');
        expect($conflicts[0]->selectedVersion)->toBe('1.5.0');
        expect($conflicts[0]->isInstalled)->toBeTrue();
    });

    it('returns no conflict when installed dep satisfies the candidate constraint', function (): void {
        $completePackage    = \makeCheckerPackage('vendor/pkg', '2.0.0');
        $installedDep = \makeCheckerPackage('vendor/dep', '2.1.0');
        $completePackage->setRequires(['vendor/dep' => \makeLink('vendor/pkg', 'vendor/dep', '^2.0')]);
        $installedDep->setRequires([]);

        $checker = new CompatibilityChecker(
            \Mockery::mock(\Composer\Composer::class),
            \makeCheckerRepoSet([$completePackage, $installedDep]),
            installedVersions: ['vendor/dep' => '2.1.0'],
        );

        $conflicts = $checker->checkCandidate('vendor/pkg', VersionTarget::fromRaw('2.0.0'), []);

        expect($conflicts)->toBe([]);
    });

    it('detects a backward conflict when an installed package requires candidate at ^1.0 but candidate is 2.0.0', function (): void {
        $completePackage  = \makeCheckerPackage('vendor/pkg', '2.0.0');
        $installedA = \makeCheckerPackage('vendor/locked', '1.5.0');
        $completePackage->setRequires([]);
        $installedA->setRequires(['vendor/pkg' => \makeLink('vendor/locked', 'vendor/pkg', '^1.0')]);

        $checker = new CompatibilityChecker(
            \Mockery::mock(\Composer\Composer::class),
            \makeCheckerRepoSet([$completePackage, $installedA]),
            installedVersions: ['vendor/locked' => '1.5.0'],
        );

        $conflicts = $checker->checkCandidate('vendor/pkg', VersionTarget::fromRaw('2.0.0'), []);

        expect($conflicts)->toHaveCount(1);
        expect($conflicts[0]->dependentPackage)->toBe('vendor/locked');
        expect($conflicts[0]->dependentVersion)->toBe('1.5.0');
        expect($conflicts[0]->requiredPackage)->toBe('vendor/pkg');
        expect($conflicts[0]->requiredConstraint)->toBe('^1.0');
        expect($conflicts[0]->isInstalled)->toBeTrue();
    });

    it('uses the selected version instead of the installed version when a package appears in both', function (): void {
        // vendor/dep is installed at 1.5.0 (which would conflict with ^2.0),
        // but selected at 2.0.0 — selection must override installed; no conflict expected.
        $completePackage    = \makeCheckerPackage('vendor/pkg', '3.0.0');
        $installedDep = \makeCheckerPackage('vendor/dep', '1.5.0');
        $selectedDep  = \makeCheckerPackage('vendor/dep', '2.0.0');
        $completePackage->setRequires(['vendor/dep' => \makeLink('vendor/pkg', 'vendor/dep', '^2.0')]);
        $installedDep->setRequires([]);
        $selectedDep->setRequires([]);

        $checker = new CompatibilityChecker(
            \Mockery::mock(\Composer\Composer::class),
            \makeCheckerRepoSet([$completePackage, $installedDep, $selectedDep]),
            installedVersions: ['vendor/dep' => '1.5.0'],
        );

        $conflicts = $checker->checkCandidate(
            'vendor/pkg',
            VersionTarget::fromRaw('3.0.0'),
            ['vendor/dep' => \sel('2.0.0')],
        );

        expect($conflicts)->toBe([]);
    });

    it('sets isInstalled to false for a cross-selection conflict even when the package is also in installedVersions', function (): void {
        // vendor/dep installed at 6.9.0 AND selected at 7.0.0 — the forward conflict uses the
        // selected version (7.0.0 vs ^6.4), so isInstalled must be false.
        $completePackage    = \makeCheckerPackage('vendor/pkg', '2.0.0');
        $installedDep = \makeCheckerPackage('vendor/dep', '6.9.0');
        $selectedDep  = \makeCheckerPackage('vendor/dep', '7.0.0');
        $completePackage->setRequires(['vendor/dep' => \makeLink('vendor/pkg', 'vendor/dep', '^6.4')]);
        $installedDep->setRequires([]);
        $selectedDep->setRequires([]);

        $checker = new CompatibilityChecker(
            \Mockery::mock(\Composer\Composer::class),
            \makeCheckerRepoSet([$completePackage, $installedDep, $selectedDep]),
            installedVersions: ['vendor/dep' => '6.9.0'],
        );

        $conflicts = $checker->checkCandidate(
            'vendor/pkg',
            VersionTarget::fromRaw('2.0.0'),
            ['vendor/dep' => \sel('7.0.0')],
        );

        expect($conflicts)->toHaveCount(1);
        expect($conflicts[0]->isInstalled)->toBeFalse();
        expect($conflicts[0]->selectedVersion)->toBe('7.0.0');
    });

    // ---------------------------------------------------------------------------
    // buildRepositorySet() — exercised when no RepositorySet is injected
    // ---------------------------------------------------------------------------

    it('builds its own RepositorySet from Composer when none is injected', function (): void {
        $mock = \Mockery::mock(\Composer\Repository\RepositoryManager::class);
        $mock->shouldReceive('getRepositories')->andReturn([]);

        $rootPackage = \Mockery::mock(\Composer\Package\RootPackageInterface::class);
        $rootPackage->shouldReceive('getMinimumStability')->andReturn('stable');
        $rootPackage->shouldReceive('getStabilityFlags')->andReturn([]);

        $composer = \Mockery::mock(\Composer\Composer::class);
        $composer->shouldReceive('getPackage')->andReturn($rootPackage);
        $composer->shouldReceive('getRepositoryManager')->andReturn($mock);

        // No second argument → buildRepositorySet() is called internally (lines 131–135)
        $checker = new CompatibilityChecker($composer);

        // Empty repo → no metadata found → no conflicts; verifies construction and execution succeed
        $conflicts = $checker->checkCandidate('vendor/pkg', VersionTarget::fromRaw('1.0.0'), []);

        expect($conflicts)->toBe([]);
    });

    it('skips backward check for an installed package that does not require the candidate', function (): void {
        // vendor/unrelated is installed and requires vendor/other, not vendor/pkg — must be skipped
        $completePackage  = \makeCheckerPackage('vendor/pkg', '2.0.0');
        $unrelated  = \makeCheckerPackage('vendor/unrelated', '1.0.0');
        $completePackage->setRequires([]);
        $unrelated->setRequires(['vendor/other' => \makeLink('vendor/unrelated', 'vendor/other', '^3.0')]);

        $checker = new CompatibilityChecker(
            \Mockery::mock(\Composer\Composer::class),
            \makeCheckerRepoSet([$completePackage, $unrelated]),
            installedVersions: ['vendor/unrelated' => '1.0.0'],
        );

        $conflicts = $checker->checkCandidate('vendor/pkg', VersionTarget::fromRaw('2.0.0'), []);

        expect($conflicts)->toBe([]);
    });
});
