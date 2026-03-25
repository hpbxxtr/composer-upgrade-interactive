<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver;

use Composer\Composer;
use Composer\Util\ProcessExecutor;
use Hpbxxtr\UpgradeInteractive\Core\Str;
use Override;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

use function array_column;
use function array_combine;
use function array_filter;
use function array_flip;
use function array_keys;
use function array_values;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function usort;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 *
 * @phpstan-type OutdatedEntry array{
 *     name: string,
 *     version: string,
 *     latest: string,
 *     'latest-status': string,
 *     abandoned: bool|string,
 * }
 * @phpstan-type ShowEntry array{
 *     name: string,
 *     version: string,
 *     source?: array{url: string, ...}|string,
 *     homepage?: string,
 * }
 */
final readonly class PackageResolver implements PackageResolverInterface
{
    public function __construct(
        private Composer $composer,
        private ProcessExecutor $processExecutor,
    ) {}

    /**
     * @return list<OutdatedPackage>
     *
     * @throws RuntimeException
     * @throws \JsonException
     * @throws \ValueError
     */
    #[Override]
    public function resolve(): array
    {
        ['patch' => $patchJson, 'minor' => $minorJson, 'major' => $majorJson, 'show' => $showJson]
            = $this->fetchParallel();

        $patchMap = $this->parseOutdatedMap($patchJson);
        $minorMap = $this->parseOutdatedMap($minorJson);
        $majorMap = $this->parseOutdatedMap($majorJson);

        // array_keys of merged associative maps gives unique names without a separate array_unique pass
        $names = array_keys($patchMap + $minorMap + $majorMap);

        if ($names === []) {
            return [];
        }

        $devSet  = array_flip(array_keys($this->composer->getPackage()->getDevRequires()));
        $metaMap = $this->parseMetaMap($showJson, $devSet);
        $entries = [];

        foreach ($names as $name) {
            $ref = $patchMap[$name] ?? $minorMap[$name] ?? $majorMap[$name] ?? null;

            if ($ref === null) {
                continue; // logically unreachable: $names comes from array_keys of the merged maps
            }

            $currentRaw = $ref['version'];

            $patchTarget = (isset($patchMap[$name]) && $patchMap[$name]['latest-status'] !== 'up-to-date')
                ? VersionTarget::fromRaw($patchMap[$name]['latest']) : null;
            $minorTarget = (isset($minorMap[$name]) && $minorMap[$name]['latest-status'] !== 'up-to-date')
                ? VersionTarget::fromRaw($minorMap[$name]['latest']) : null;
            $majorTarget = (isset($majorMap[$name]) && $majorMap[$name]['latest-status'] !== 'up-to-date')
                ? VersionTarget::fromRaw($majorMap[$name]['latest']) : null;

            // Deduplicate: same version reported across adjacent bump levels
            if ($patchTarget instanceof VersionTarget && $minorTarget instanceof VersionTarget && $patchTarget->version === $minorTarget->version) {
                $minorTarget = null;
            }

            if ($minorTarget instanceof VersionTarget && $majorTarget instanceof VersionTarget && $minorTarget->version === $majorTarget->version) {
                $minorTarget = null;
            }

            // Deduplicate: patch and major reporting the same version (minor already nulled)
            if ($patchTarget instanceof VersionTarget && $majorTarget instanceof VersionTarget && $patchTarget->version === $majorTarget->version) {
                $majorTarget = null;
            }

            $meta      = $metaMap[$name] ?? ['repoUrl' => '', 'isDev' => isset($devSet[$name])];
            $abandoned = $ref['abandoned'];

            $entries[] = new OutdatedPackage(
                name: $name,
                current: Str::trimStart($currentRaw, 'v'),
                currentRaw: $currentRaw,
                patch: $patchTarget,
                minor: $minorTarget,
                major: $majorTarget,
                isDev: $meta['isDev'],
                repoUrl: $meta['repoUrl'],
                abandonedBy: $abandoned === false ? null : (is_string($abandoned) ? $abandoned : ''),
            );
        }

        usort(
            $entries,
            static fn (OutdatedPackage $a, OutdatedPackage $b): int => $a->isDev !== $b->isDev ? ($a->isDev ? 1 : -1) : ($a->name <=> $b->name),
        );

        return $entries;
    }

    /**
     * Fires all four composer commands in parallel via ProcessExecutor::executeAsync()
     * and drives them to completion through Composer's event loop.
     *
     * @return array{patch: string, minor: string, major: string, show: string}
     *
     * @throws RuntimeException
     */
    private function fetchParallel(): array
    {
        $outputs = ['patch' => '', 'minor' => '', 'major' => '', 'show' => ''];

        $commands = [
            'patch' => 'composer outdated -D --patch-only --format=json --no-interaction',
            'minor' => 'composer outdated -D --minor-only --format=json --no-interaction',
            'major' => 'composer outdated -D --major-only --format=json --no-interaction',
            'show'  => 'composer show --format=json --no-interaction',
        ];

        $errors   = [];
        $promises = [];

        foreach ($commands as $key => $cmd) {
            $promises[] = $this->processExecutor->executeAsync($cmd)
                ->then(
                    static function (Process $process) use (&$outputs, &$errors, $key): void {
                        if (!$process->isSuccessful()) {
                            $errors[$key] = Str::trim($process->getErrorOutput() ?: $process->getOutput());

                            return;
                        }

                        $outputs[$key] = $process->getOutput();
                    },
                    static function (Throwable $throwable) use (&$errors, $key): void {
                        $errors[$key] = $throwable->getMessage();
                    },
                )
            ;
        }

        $this->composer->getLoop()->wait($promises);

        if ($errors !== []) {
            throw new RuntimeException(
                'One or more composer commands failed:' . PHP_EOL . implode(PHP_EOL, $errors),
            );
        }

        return $outputs;
    }

    /**
     * @return array<string, OutdatedEntry>
     *
     * @throws \JsonException
     * @throws \ValueError
     */
    private function parseOutdatedMap(string $json): array
    {
        if ($json === '') {
            return [];
        }

        /** @var array{installed?: list<OutdatedEntry>} $decoded */
        $decoded  = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $filtered = array_filter(
            $decoded['installed'] ?? [],
            static fn (array $pkg): bool => $pkg['latest-status'] !== 'up-to-date'
                || $pkg['abandoned'] !== false,
        );

        return array_combine(
            array_column($filtered, 'name'),
            array_values($filtered),
        );
    }

    /**
     * @param array<string, int> $devSet pre-computed flip of dev-require names
     *
     * @return array<string, array{repoUrl: string, isDev: bool}>
     *
     * @throws \JsonException
     */
    private function parseMetaMap(string $json, array $devSet): array
    {
        if ($json === '') {
            return [];
        }

        /** @var array{installed?: list<ShowEntry>} $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $map     = [];

        foreach ($decoded['installed'] ?? [] as $pkg) {
            $src = $pkg['source'] ?? null;

            $sourceUrl = match (true) {
                is_string($src) => $src,
                is_array($src)  => $src['url'],
                default         => $pkg['homepage'] ?? '',
            };

            $map[$pkg['name']] = [
                'repoUrl' => $sourceUrl,
                'isDev'   => isset($devSet[$pkg['name']]),
            ];
        }

        return $map;
    }
}
