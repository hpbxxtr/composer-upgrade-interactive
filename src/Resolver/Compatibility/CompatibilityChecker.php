<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver\Compatibility;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Repository\CompositeRepository;
use Composer\Repository\RepositorySet;
use Composer\Semver\Semver;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionSelection;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;
use Override;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final class CompatibilityChecker implements CompatibilityCheckerInterface
{
    /** @var array<string, array<string, PackageInterface|null>> name → versionRaw → metadata */
    private array $metadataCache = [];

    private readonly RepositorySet $repositorySet;

    /**
     * @param RepositorySet|null        $repositorySet    Injected for testing; built from Composer in production.
     * @param array<string, string>     $installedVersions name → versionRaw for all installed packages;
     *                                                     built by the caller from the local Composer repository.
     *                                                     Defaults to empty (tests and environments without Composer context).
     */
    public function __construct(
        private readonly Composer $composer,
        ?RepositorySet $repositorySet = null,
        private readonly array $installedVersions = [],
    ) {
        $this->repositorySet = $repositorySet ?? $this->buildRepositorySet();
    }

    /**
     * @param array<string, VersionSelection> $selections  other selections, excluding $packageName
     * @return list<ConflictReason>  empty = compatible
     * @throws \UnexpectedValueException if a Link was constructed without a prettyConstraint
     */
    #[Override]
    public function checkCandidate(
        string $packageName,
        VersionTarget $versionTarget,
        array $selections,
    ): array {
        $conflicts = [];

        // Build effective world: installed base + selection overrides, excluding the candidate itself
        /** @var array<string, string> $effectiveWorld */
        $effectiveWorld = $this->installedVersions;

        foreach ($selections as $selName => $sel) {
            $effectiveWorld[$selName] = $sel->target->versionRaw;
        }

        unset($effectiveWorld[$packageName]);

        // Forward: check candidate's requires against every package in the effective world
        $candidateMeta = $this->fetchMetadata($packageName, $versionTarget->versionRaw);

        foreach ($candidateMeta?->getRequires() ?? [] as $depName => $link) {
            if (!isset($effectiveWorld[$depName])) {
                continue;
            }

            $effectiveVersion = $effectiveWorld[$depName];

            if (!Semver::satisfies($effectiveVersion, $link->getPrettyConstraint())) {
                $isInstalled = !isset($selections[$depName]);
                $conflicts[] = new ConflictReason(
                    dependentPackage: $packageName,
                    dependentVersion: $versionTarget->versionRaw,
                    requiredPackage: $depName,
                    requiredConstraint: $link->getPrettyConstraint(),
                    selectedVersion: $effectiveVersion,
                    isInstalled: $isInstalled,
                );
            }
        }

        // Backward: check every package in the effective world against the candidate
        foreach ($effectiveWorld as $worldPkgName => $worldPkgVersion) {
            $isInstalled = !isset($selections[$worldPkgName]);
            $meta        = $this->fetchMetadata($worldPkgName, $worldPkgVersion);

            foreach ($meta?->getRequires() ?? [] as $depName => $link) {
                if ($depName !== $packageName) {
                    continue;
                }

                if (!Semver::satisfies($versionTarget->versionRaw, $link->getPrettyConstraint())) {
                    $conflicts[] = new ConflictReason(
                        dependentPackage: $worldPkgName,
                        dependentVersion: $worldPkgVersion,
                        requiredPackage: $packageName,
                        requiredConstraint: $link->getPrettyConstraint(),
                        selectedVersion: $versionTarget->versionRaw,
                        isInstalled: $isInstalled,
                    );
                }
            }
        }

        return $conflicts;
    }

    private function fetchMetadata(string $name, string $versionRaw): ?PackageInterface
    {
        if (array_key_exists($name, $this->metadataCache) && array_key_exists($versionRaw, $this->metadataCache[$name])) {
            return $this->metadataCache[$name][$versionRaw];
        }

        $versionParser = new VersionParser();
        $constraint = $versionParser->parseConstraints($versionRaw);
        $found = array_values($this->repositorySet->findPackages($name, $constraint));

        $package = $found !== [] ? $found[0] : null;
        $this->metadataCache[$name][$versionRaw] = $package;

        return $package;
    }

    private function buildRepositorySet(): RepositorySet
    {
        $rootPackage = $this->composer->getPackage();
        $repositorySet = new RepositorySet($rootPackage->getMinimumStability(), $rootPackage->getStabilityFlags());
        $repositorySet->addRepository(new CompositeRepository($this->composer->getRepositoryManager()->getRepositories()));

        return $repositorySet;
    }
}
