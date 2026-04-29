<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver;

use Composer\Semver\VersionParser;
use Composer\Util\ProcessExecutor;
use Override;
use UnexpectedValueException;

use function explode;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final readonly class AvailableVersionsResolver implements AvailableVersionsResolverInterface
{
    private const int JSON_DECODE_DEPTH = 512;

    public function __construct(
        private ProcessExecutor $processExecutor,
    ) {}

    /**
     * @return list<VersionTarget>
     *
     * @throws \JsonException
     */
    #[Override]
    public function resolve(string $packageName, string $currentVersion, BumpType $bumpType): array
    {
        $output   = '';
        $exitCode = $this->processExecutor->execute(
            'composer show ' . ProcessExecutor::escape($packageName) . ' -a --format=json --no-interaction',
            $output,
        );

        if ($exitCode !== 0) {
            return [];
        }

        if (!is_string($output) || $output === '') {
            return [];
        }

        try {
            /** @var array{versions?: list<string>} $decoded */
            $decoded = json_decode($output, true, self::JSON_DECODE_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        $rawVersions = $decoded['versions'] ?? [];
        $versionParser      = new VersionParser();

        try {
            $curSegments = $this->parseSegments($versionParser, $currentVersion);
        } catch (UnexpectedValueException) {
            return [];
        }

        $result = [];

        foreach ($rawVersions as $rawVersion) {
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

            $result[] = VersionTarget::fromRaw($rawVersion);
        }

        return $result;
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
