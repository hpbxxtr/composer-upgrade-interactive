<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver;

use Composer\Composer;
use Composer\Package\BasePackage;
use Composer\Package\CompletePackageInterface;
use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionSelector;
use Composer\Pcre\Preg;
use Composer\Repository\CompositeRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositorySet;
use Hpbxxtr\UpgradeInteractive\Core\Str;
use Override;
use RuntimeException;

use function array_flip;
use function array_keys;
use function array_merge;
use function substr_count;
use function usort;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final readonly class PackageResolver implements PackageResolverInterface
{
    /** Mirrors Composer ShowCommand's major-only upper bound sentinel. */
    private const string MAJOR_UPPER_BOUND = '9999999-dev';

    /**
     * @param RepositorySet|null    $repositorySet  Injected for testing; built from Composer in production.
     * @param PlatformRepository|null $platformRepository Injected for testing; built from config in production.
     */
    public function __construct(
        private Composer $composer,
        private ?RepositorySet $repositorySet = null,
        private ?PlatformRepository $platformRepository = null,
    ) {}

    /**
     * @return list<OutdatedPackage>
     */
    #[Override]
    public function resolve(): array
    {
        $rootPackage  = $this->composer->getPackage();
        $devSet   = array_flip(array_keys($rootPackage->getDevRequires()));
        $directSet = array_flip(array_merge(
            array_keys($rootPackage->getRequires()),
            array_keys($rootPackage->getDevRequires()),
        ));

        $repoSet      = $this->repositorySet ?? $this->buildRepositorySet();
        $platformRepo = $this->platformRepository ?? ($this->repositorySet instanceof RepositorySet ? null : $this->buildPlatformRepo());
        $versionSelector     = new VersionSelector($repoSet, $platformRepo);

        $stability        = $rootPackage->getMinimumStability();
        $stabilityFlags   = $rootPackage->getStabilityFlags();
        $isPreferStable   = $rootPackage->getPreferStable();

        $entries = [];

        foreach ($this->composer->getRepositoryManager()->getLocalRepository()->getPackages() as $basePackage) {
            $name = $basePackage->getName();

            // Direct dependencies only (replicates `composer outdated -D`)
            if (!isset($directSet[$name])) {
                continue;
            }

            $pkgStability = $stability;
            if (isset($stabilityFlags[$name])) {
                $found = array_search($stabilityFlags[$name], BasePackage::STABILITIES, true);
                $pkgStability = is_string($found) ? $found : $stability;
            }

            $bestStability = $isPreferStable ? $basePackage->getStability() : $pkgStability;

            [$patchTarget, $minorTarget, $majorTarget] = $this->findTargets(
                $versionSelector,
                $basePackage,
                $bestStability,
            );

            if ($patchTarget === null && $minorTarget === null && $majorTarget === null) {
                continue;
            }

            $entries[] = new OutdatedPackage(
                name: $name,
                current: Str::trimStart($basePackage->getPrettyVersion(), 'v'),
                currentRaw: $basePackage->getPrettyVersion(),
                patch: $patchTarget,
                minor: $minorTarget,
                major: $majorTarget,
                isDev: isset($devSet[$name]),
                repoUrl: $this->sourceUrl($basePackage),
                abandonedBy: $this->abandonedBy($basePackage),
            );
        }

        usort(
            $entries,
            static fn (OutdatedPackage $a, OutdatedPackage $b): int => $a->isDev !== $b->isDev ? ($a->isDev ? 1 : -1) : ($a->name <=> $b->name),
        );

        return $entries;
    }

    /**
     * Returns [patchTarget, minorTarget, majorTarget], each null when no upgrade available.
     *
     * @return array{VersionTarget|null, VersionTarget|null, VersionTarget|null}
     */
    private function findTargets(VersionSelector $versionSelector, PackageInterface $package, string $bestStability): array
    {
        $version = $package->getVersion();

        // Dev-branch packages: look for newer commits on the same branch only (no major bump).
        if (Str::startsWith($version, 'dev-')) {
            $candidate = $versionSelector->findBestCandidate($package->getName(), $version, $bestStability);
            $target    = ($candidate !== false && $candidate->getVersion() !== $version)
                ? VersionTarget::fromRaw($candidate->getPrettyVersion())
                : null;

            return [null, $target, null];
        }

        $patchTarget = $this->candidateTarget($versionSelector, $package->getName(), $this->computePatchConstraint($version), $version, $bestStability);
        $minorTarget = $this->candidateTarget($versionSelector, $package->getName(), '^' . $version, $version, $bestStability);
        $majorConstraint = $this->computeMajorConstraint($version);
        $majorTarget = $majorConstraint !== null
            ? $this->candidateTarget($versionSelector, $package->getName(), $majorConstraint, $version, $bestStability)
            : null;

        // Deduplicate: same version reported across adjacent bump levels.
        if ($patchTarget instanceof VersionTarget && $minorTarget instanceof VersionTarget && $patchTarget->version === $minorTarget->version) {
            $minorTarget = null;
        }

        if ($minorTarget instanceof VersionTarget && $majorTarget instanceof VersionTarget && $minorTarget->version === $majorTarget->version) {
            $minorTarget = null;
        }

        if ($patchTarget instanceof VersionTarget && $majorTarget instanceof VersionTarget && $patchTarget->version === $majorTarget->version) {
            $majorTarget = null;
        }

        return [$patchTarget, $minorTarget, $majorTarget];
    }

    private function candidateTarget(VersionSelector $versionSelector, string $name, string $constraint, string $installedVersion, string $bestStability): ?VersionTarget
    {
        $candidate = $versionSelector->findBestCandidate($name, $constraint, $bestStability);

        if ($candidate === false || $candidate->getVersion() === $installedVersion) {
            return null;
        }

        return VersionTarget::fromRaw($candidate->getPrettyVersion());
    }

    /**
     * Tilde constraint for patch-level upgrades — mirrors ShowCommand's --patch-only logic.
     */
    private function computePatchConstraint(string $version): string
    {
        $trimmed     = Preg::replace('{(\.0)+$}D', '', $version);
        $partsNeeded = Str::startsWith($trimmed, '0') ? 4 : 3;

        while (substr_count($trimmed, '.') + 1 < $partsNeeded) {
            $trimmed .= '.0';
        }

        return '~' . $trimmed;
    }

    /**
     * Constraint targeting the next major version — mirrors ShowCommand's --major-only logic.
     */
    private function computeMajorConstraint(string $version): ?string
    {
        if (!Preg::isMatch('{^(?P<zero_major>(?:0\.)+)?(?P<first_meaningful>\d+)\.}', $version, $match)) {
            return null;
        }

        return '>=' . $match['zero_major'] . (((int) $match['first_meaningful']) + 1) . ',<' . self::MAJOR_UPPER_BOUND;
    }

    private function sourceUrl(PackageInterface $package): string
    {
        $url = $package->getSourceUrl();

        if ($url !== null && $url !== '') {
            return $url;
        }

        if ($package instanceof CompletePackageInterface) {
            return $package->getHomepage() ?? '';
        }

        return '';
    }

    private function abandonedBy(PackageInterface $package): ?string
    {
        if (!($package instanceof CompletePackageInterface) || !$package->isAbandoned()) {
            return null;
        }

        return $package->getReplacementPackage() ?? '';
    }

    private function buildRepositorySet(): RepositorySet
    {
        $rootPackage = $this->composer->getPackage();
        $repositorySet     = new RepositorySet($rootPackage->getMinimumStability(), $rootPackage->getStabilityFlags());
        $repositorySet->addRepository(new CompositeRepository($this->composer->getRepositoryManager()->getRepositories()));

        return $repositorySet;
    }

    /**
     * @throws RuntimeException
     */
    private function buildPlatformRepo(): PlatformRepository
    {
        /** @var array<string, string> $overrides */
        $overrides = $this->composer->getConfig()->get('platform') ?: [];

        return new PlatformRepository([], $overrides);
    }
}
