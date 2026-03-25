<?php

declare(strict_types=1);

use Composer\Composer;
use Composer\Package\RootPackageInterface;
use Composer\Util\Loop;
use Composer\Util\ProcessExecutor;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\PackageResolver;
use React\Promise\PromiseInterface;
use Symfony\Component\Process\Process;

afterEach(function (): void {
    \Mockery::close();
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a mock Process that reports success and returns $output.
 */
function mockProcess(bool $successful, string $output = '', string $errorOutput = ''): Process
{
    $mock = \Mockery::mock(Process::class);
    $mock->shouldReceive('isSuccessful')->andReturn($successful);
    $mock->shouldReceive('getOutput')->andReturn($output);
    $mock->shouldReceive('getErrorOutput')->andReturn($errorOutput);

    return $mock;
}

/**
 * Build a ProcessExecutor mock whose executeAsync() returns promises that
 * resolve synchronously via React\Promise\resolve().
 *
 * $outputs is a map keyed on the unique command fragment used to identify
 * each call: 'patch-only', 'minor-only', 'major-only', or default (show).
 *
 * @param array<string, string> $outputs keyed by fragment: patch|minor|major|show
 */
function mockProcessExecutor(array $outputs): ProcessExecutor
{
    $mock = \Mockery::mock(ProcessExecutor::class);

    $mock->shouldReceive('executeAsync')
        ->andReturnUsing(static function (string $cmd) use ($outputs): PromiseInterface {
            $json = match (true) {
                str_contains($cmd, '--patch-only') => $outputs['patch'] ?? '',
                str_contains($cmd, '--minor-only') => $outputs['minor'] ?? '',
                str_contains($cmd, '--major-only') => $outputs['major'] ?? '',
                default                             => $outputs['show'] ?? '',
            };

            return \React\Promise\resolve(\mockProcess(true, $json));
        })
    ;

    return $mock;
}

/**
 * Build a Composer mock wired with a no-op Loop and the given dev-require names.
 *
 * @param list<string> $devPackageNames
 */
function mockComposerForResolver(array $devPackageNames = []): Composer
{
    $mock = \Mockery::mock(Loop::class);
    $mock->shouldReceive('wait')->byDefault();

    $devRequires = [];

    foreach ($devPackageNames as $devPackageName) {
        $devRequires[$devPackageName] = new stdClass(); // Link value is not inspected
    }

    $package = \Mockery::mock(RootPackageInterface::class);
    $package->shouldReceive('getDevRequires')->andReturn($devRequires);

    $composer = \Mockery::mock(Composer::class);
    $composer->shouldReceive('getLoop')->andReturn($mock);
    $composer->shouldReceive('getPackage')->andReturn($package);

    return $composer;
}

/**
 * Encode the standard "composer outdated" JSON shape.
 *
 * @param list<array{name:string,version:string,latest:string,latest-status:string,abandoned:bool|string}> $installed
 */
function outdatedJson(array $installed): string
{
    return (string) json_encode(['installed' => $installed]);
}

/**
 * Encode the standard "composer show" JSON shape.
 *
 * @param list<array{name:string,version:string,source?:array{url:string,...}}> $installed
 */
function showJson(array $installed): string
{
    return (string) json_encode(['installed' => $installed]);
}

// ---------------------------------------------------------------------------
// Returns empty list
// ---------------------------------------------------------------------------

\it('returns empty array when no packages are outdated', function (): void {
    $processExecutor = \mockProcessExecutor([
        'patch' => \outdatedJson([]),
        'minor' => \outdatedJson([]),
        'major' => \outdatedJson([]),
        'show'  => \showJson([]),
    ]);

    $packages = (new PackageResolver(\mockComposerForResolver(), $processExecutor))->resolve();

    \expect($packages)->toBe([]);
});

// ---------------------------------------------------------------------------
// Basic resolution
// ---------------------------------------------------------------------------

\it('resolves a minor update for a single package', function (): void {
    $processExecutor = \mockProcessExecutor([
        'patch' => \outdatedJson([]),
        'minor' => \outdatedJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'latest' => '1.1.0', 'latest-status' => 'semver-safe-update', 'abandoned' => false],
        ]),
        'major' => \outdatedJson([]),
        'show'  => \showJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0'],
        ]),
    ]);

    $packages = (new PackageResolver(\mockComposerForResolver(), $processExecutor))->resolve();

    \expect($packages)->toHaveCount(1);
    \expect($packages[0])->toBeInstanceOf(OutdatedPackage::class);
    \expect($packages[0]->name)->toBe('vendor/pkg');
    \expect($packages[0]->minor?->version)->toBe('1.1.0');
    \expect($packages[0]->patch)->toBeNull();
    \expect($packages[0]->major)->toBeNull();
});

\it('resolves all three bump types independently', function (): void {
    $processExecutor = \mockProcessExecutor([
        'patch' => \outdatedJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'latest' => '1.0.1', 'latest-status' => 'semver-safe-update', 'abandoned' => false],
        ]),
        'minor' => \outdatedJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'latest' => '1.1.0', 'latest-status' => 'semver-safe-update', 'abandoned' => false],
        ]),
        'major' => \outdatedJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'latest' => '2.0.0', 'latest-status' => 'update-possible', 'abandoned' => false],
        ]),
        'show'  => \showJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0'],
        ]),
    ]);

    $packages = (new PackageResolver(\mockComposerForResolver(), $processExecutor))->resolve();

    \expect($packages)->toHaveCount(1);
    \expect($packages[0]->patch?->version)->toBe('1.0.1');
    \expect($packages[0]->minor?->version)->toBe('1.1.0');
    \expect($packages[0]->major?->version)->toBe('2.0.0');
});

// ---------------------------------------------------------------------------
// Deduplication
// ---------------------------------------------------------------------------

\it('nulls minor when patch and minor report the same version', function (): void {
    $processExecutor = \mockProcessExecutor([
        'patch' => \outdatedJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'latest' => '1.1.0', 'latest-status' => 'semver-safe-update', 'abandoned' => false],
        ]),
        'minor' => \outdatedJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'latest' => '1.1.0', 'latest-status' => 'semver-safe-update', 'abandoned' => false],
        ]),
        'major' => \outdatedJson([]),
        'show'  => \showJson([['name' => 'vendor/pkg', 'version' => '1.0.0']]),
    ]);

    $packages = (new PackageResolver(\mockComposerForResolver(), $processExecutor))->resolve();

    \expect($packages[0]->patch?->version)->toBe('1.1.0');
    \expect($packages[0]->minor)->toBeNull();
});

\it('nulls minor when minor and major report the same version', function (): void {
    // The deduplication rule: when minor == major, minor is set to null and major is kept.
    $processExecutor = \mockProcessExecutor([
        'patch' => \outdatedJson([]),
        'minor' => \outdatedJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'latest' => '2.0.0', 'latest-status' => 'semver-safe-update', 'abandoned' => false],
        ]),
        'major' => \outdatedJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'latest' => '2.0.0', 'latest-status' => 'update-possible', 'abandoned' => false],
        ]),
        'show'  => \showJson([['name' => 'vendor/pkg', 'version' => '1.0.0']]),
    ]);

    $packages = (new PackageResolver(\mockComposerForResolver(), $processExecutor))->resolve();

    \expect($packages[0]->minor)->toBeNull();
    \expect($packages[0]->major?->version)->toBe('2.0.0');
});

\it('nulls major when patch and major report the same version (minor already null)', function (): void {
    $processExecutor = \mockProcessExecutor([
        'patch' => \outdatedJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'latest' => '2.0.0', 'latest-status' => 'semver-safe-update', 'abandoned' => false],
        ]),
        'minor' => \outdatedJson([]),
        'major' => \outdatedJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'latest' => '2.0.0', 'latest-status' => 'update-possible', 'abandoned' => false],
        ]),
        'show'  => \showJson([['name' => 'vendor/pkg', 'version' => '1.0.0']]),
    ]);

    $packages = (new PackageResolver(\mockComposerForResolver(), $processExecutor))->resolve();

    \expect($packages[0]->patch?->version)->toBe('2.0.0');
    \expect($packages[0]->major)->toBeNull();
});

// ---------------------------------------------------------------------------
// Abandoned packages
// ---------------------------------------------------------------------------

\it('marks an abandoned package with a replacement', function (): void {
    $processExecutor = \mockProcessExecutor([
        'patch' => \outdatedJson([
            ['name' => 'vendor/old', 'version' => '1.0.0', 'latest' => '1.0.1', 'latest-status' => 'semver-safe-update', 'abandoned' => 'vendor/new'],
        ]),
        'minor' => \outdatedJson([]),
        'major' => \outdatedJson([]),
        'show'  => \showJson([['name' => 'vendor/old', 'version' => '1.0.0']]),
    ]);

    $packages = (new PackageResolver(\mockComposerForResolver(), $processExecutor))->resolve();

    \expect($packages[0]->abandonedBy)->toBe('vendor/new');
});

\it('marks an abandoned package with no stated replacement as empty string', function (): void {
    $processExecutor = \mockProcessExecutor([
        'patch' => \outdatedJson([
            ['name' => 'vendor/old', 'version' => '1.0.0', 'latest' => '1.0.1', 'latest-status' => 'semver-safe-update', 'abandoned' => true],
        ]),
        'minor' => \outdatedJson([]),
        'major' => \outdatedJson([]),
        'show'  => \showJson([['name' => 'vendor/old', 'version' => '1.0.0']]),
    ]);

    $packages = (new PackageResolver(\mockComposerForResolver(), $processExecutor))->resolve();

    \expect($packages[0]->abandonedBy)->toBe('');
});

\it('does not mark a non-abandoned package', function (): void {
    $processExecutor = \mockProcessExecutor([
        'patch' => \outdatedJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'latest' => '1.0.1', 'latest-status' => 'semver-safe-update', 'abandoned' => false],
        ]),
        'minor' => \outdatedJson([]),
        'major' => \outdatedJson([]),
        'show'  => \showJson([['name' => 'vendor/pkg', 'version' => '1.0.0']]),
    ]);

    $packages = (new PackageResolver(\mockComposerForResolver(), $processExecutor))->resolve();

    \expect($packages[0]->abandonedBy)->toBeNull();
});

// ---------------------------------------------------------------------------
// Dev packages
// ---------------------------------------------------------------------------

\it('marks packages listed in require-dev as dev', function (): void {
    $processExecutor = \mockProcessExecutor([
        'patch' => \outdatedJson([
            ['name' => 'vendor/dev-pkg', 'version' => '1.0.0', 'latest' => '1.0.1', 'latest-status' => 'semver-safe-update', 'abandoned' => false],
        ]),
        'minor' => \outdatedJson([]),
        'major' => \outdatedJson([]),
        'show'  => \showJson([['name' => 'vendor/dev-pkg', 'version' => '1.0.0']]),
    ]);

    $packages = (new PackageResolver(\mockComposerForResolver(['vendor/dev-pkg']), $processExecutor))->resolve();

    \expect($packages[0]->isDev)->toBeTrue();
});

\it('marks packages not in require-dev as prod', function (): void {
    $processExecutor = \mockProcessExecutor([
        'patch' => \outdatedJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'latest' => '1.0.1', 'latest-status' => 'semver-safe-update', 'abandoned' => false],
        ]),
        'minor' => \outdatedJson([]),
        'major' => \outdatedJson([]),
        'show'  => \showJson([['name' => 'vendor/pkg', 'version' => '1.0.0']]),
    ]);

    $packages = (new PackageResolver(\mockComposerForResolver(['vendor/other']), $processExecutor))->resolve();

    \expect($packages[0]->isDev)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Sorting
// ---------------------------------------------------------------------------

\it('sorts prod packages before dev packages', function (): void {
    $processExecutor = \mockProcessExecutor([
        'patch' => \outdatedJson([
            ['name' => 'vendor/dev-a', 'version' => '1.0.0', 'latest' => '1.0.1', 'latest-status' => 'semver-safe-update', 'abandoned' => false],
            ['name' => 'vendor/prod-b', 'version' => '1.0.0', 'latest' => '1.0.1', 'latest-status' => 'semver-safe-update', 'abandoned' => false],
        ]),
        'minor' => \outdatedJson([]),
        'major' => \outdatedJson([]),
        'show'  => \showJson([
            ['name' => 'vendor/dev-a', 'version' => '1.0.0'],
            ['name' => 'vendor/prod-b', 'version' => '1.0.0'],
        ]),
    ]);

    $packages = (new PackageResolver(\mockComposerForResolver(['vendor/dev-a']), $processExecutor))->resolve();

    \expect($packages[0]->name)->toBe('vendor/prod-b');
    \expect($packages[1]->name)->toBe('vendor/dev-a');
});

\it('sorts packages alphabetically within the same section', function (): void {
    $processExecutor = \mockProcessExecutor([
        'patch' => \outdatedJson([
            ['name' => 'vendor/z-pkg', 'version' => '1.0.0', 'latest' => '1.0.1', 'latest-status' => 'semver-safe-update', 'abandoned' => false],
            ['name' => 'vendor/a-pkg', 'version' => '1.0.0', 'latest' => '1.0.1', 'latest-status' => 'semver-safe-update', 'abandoned' => false],
        ]),
        'minor' => \outdatedJson([]),
        'major' => \outdatedJson([]),
        'show'  => \showJson([
            ['name' => 'vendor/z-pkg', 'version' => '1.0.0'],
            ['name' => 'vendor/a-pkg', 'version' => '1.0.0'],
        ]),
    ]);

    $packages = (new PackageResolver(\mockComposerForResolver(), $processExecutor))->resolve();

    \expect($packages[0]->name)->toBe('vendor/a-pkg');
    \expect($packages[1]->name)->toBe('vendor/z-pkg');
});

// ---------------------------------------------------------------------------
// Meta / repoUrl
// ---------------------------------------------------------------------------

\it('extracts the repo URL from the source array in show output', function (): void {
    $processExecutor = \mockProcessExecutor([
        'patch' => \outdatedJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'latest' => '1.0.1', 'latest-status' => 'semver-safe-update', 'abandoned' => false],
        ]),
        'minor' => \outdatedJson([]),
        'major' => \outdatedJson([]),
        'show'  => \showJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'source' => ['url' => 'https://github.com/vendor/pkg', 'type' => 'git', 'reference' => 'abc']],
        ]),
    ]);

    $packages = (new PackageResolver(\mockComposerForResolver(), $processExecutor))->resolve();

    \expect($packages[0]->repoUrl)->toBe('https://github.com/vendor/pkg');
});

\it('uses homepage as repoUrl when source is absent', function (): void {
    $processExecutor = \mockProcessExecutor([
        'patch' => \outdatedJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'latest' => '1.0.1', 'latest-status' => 'semver-safe-update', 'abandoned' => false],
        ]),
        'minor' => \outdatedJson([]),
        'major' => \outdatedJson([]),
        'show'  => \showJson([
            ['name' => 'vendor/pkg', 'version' => '1.0.0', 'homepage' => 'https://example.com/vendor/pkg'],
        ]),
    ]);

    $packages = (new PackageResolver(\mockComposerForResolver(), $processExecutor))->resolve();

    \expect($packages[0]->repoUrl)->toBe('https://example.com/vendor/pkg');
});

// ---------------------------------------------------------------------------
// Error handling
// ---------------------------------------------------------------------------

\it('throws RuntimeException when a composer command fails', function (): void {
    $failProcess = \mockProcess(false, '', 'connection refused');

    $mock = \Mockery::mock(ProcessExecutor::class);
    $mock->shouldReceive('executeAsync')
        ->andReturn(\React\Promise\resolve($failProcess))
    ;

    \expect(static fn (): array => (new PackageResolver(\mockComposerForResolver(), $mock))->resolve())
        ->toThrow(\RuntimeException::class, 'connection refused')
    ;
});

\it('throws JsonException when outdated output is invalid JSON', function (): void {
    $processExecutor = \mockProcessExecutor([
        'patch' => 'not-valid-json',
        'minor' => \outdatedJson([]),
        'major' => \outdatedJson([]),
        'show'  => \showJson([]),
    ]);

    \expect(static fn (): array => (new PackageResolver(\mockComposerForResolver(), $processExecutor))->resolve())
        ->toThrow(\JsonException::class)
    ;
});
