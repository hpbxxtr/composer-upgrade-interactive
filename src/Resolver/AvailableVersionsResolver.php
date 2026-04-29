<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver;

use Composer\Composer;
use Composer\Repository\CompositeRepository;
use Composer\Repository\RepositorySet;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Override;
use UnexpectedValueException;

use function array_map;
use function explode;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final readonly class AvailableVersionsResolver implements AvailableVersionsResolverInterface
{
    /**
     * @param RepositorySet|null $repositorySet Injected for testing; built from Composer in production.
     */
    public function __construct(
        private Composer $composer,
        private ?RepositorySet $repositorySet = null,
    ) {}

    /**
     * @return list<VersionTarget>
     */
    #[Override]
    public function resolve(string $packageName, string $currentVersion, BumpType $bumpType): array
    {
        $repoSet       = $this->repositorySet ?? $this->buildRepositorySet();
        $versionParser = new VersionParser();

        try {
            $curSegments = $this->parseSegments($versionParser, $currentVersion);
        } catch (UnexpectedValueException) {
            return [];
        }

        $seen        = [];
        $rawVersions = [];

        foreach ($repoSet->findPackages($packageName) as $basePackage) {
            $rawVersion = $basePackage->getPrettyVersion();

            if (isset($seen[$rawVersion])) {
                continue;
            }

            $seen[$rawVersion] = true;

            if (VersionParser::parseStability($rawVersion) !== 'stable') {
                continue;
            }

            try {
                $segments = $this->parseSegments($versionParser, $rawVersion);
            } catch (UnexpectedValueException) {
                continue;
            }

            if (!$this->doesMatchBump($segments, $curSegments, $bumpType)) {
                continue;
            }

            $rawVersions[] = $rawVersion;
        }

        return array_map(
            VersionTarget::fromRaw(...),
            Semver::rsort($rawVersions),
        );
    }

    private function buildRepositorySet(): RepositorySet
    {
        $rootPackage   = $this->composer->getPackage();
        $repositorySet = new RepositorySet(
            $rootPackage->getMinimumStability(),
            $rootPackage->getStabilityFlags(),
        );
        $repositorySet->addRepository(
            new CompositeRepository($this->composer->getRepositoryManager()->getRepositories()),
        );

        return $repositorySet;
    }

    /**
     * @return array{int, int, int}
     *
     * @throws UnexpectedValueException
     */
    private function parseSegments(VersionParser $versionParser, string $version): array
    {
        $normalized = $versionParser->normalize($version);
        $parts      = explode('.', $normalized);

        return [(int) $parts[0], (int) ($parts[1] ?? 0), (int) ($parts[2] ?? 0)];
    }

    /**
     * @param array{int, int, int} $seg
     * @param array{int, int, int} $cur
     */
    private function doesMatchBump(array $seg, array $cur, BumpType $bumpType): bool
    {
        return match ($bumpType) {
            BumpType::Patch => $seg[0] === $cur[0] && $seg[1] === $cur[1] && $seg[2] > $cur[2],
            BumpType::Minor => $seg[0] === $cur[0] && $seg[1] > $cur[1],
            BumpType::Major => $seg[0] > $cur[0],
        };
    }
}
