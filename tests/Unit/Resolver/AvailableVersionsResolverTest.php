<?php

declare(strict_types=1);

use Composer\Util\ProcessExecutor;
use Hpbxxtr\UpgradeInteractive\Resolver\AvailableVersionsResolver;
use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a ProcessExecutor fake whose execute() fills $output with $json and
 * returns $exitCode.  Uses an anonymous subclass to avoid Mockery's inability
 * to handle pass-by-reference parameters cleanly.
 */
function fakeProcessExecutor(string $json, int $exitCode = 0): ProcessExecutor
{
    return new class ($json, $exitCode) extends ProcessExecutor {
        public function __construct(
            private readonly string $json,
            private readonly int $exitCode,
        ) {
            // Do not call parent — we skip all real process logic
        }

        public function execute($command, &$output = null, ?string $cwd = null): int
        {
            $output = $this->json;

            return $this->exitCode;
        }
    };
}

// ---------------------------------------------------------------------------
// Process failure / empty output
// ---------------------------------------------------------------------------

\it('returns empty array when the process exits non-zero', function (): void {
    $resolver = new AvailableVersionsResolver(\fakeProcessExecutor('', exitCode: 1));

    \expect($resolver->resolve('vendor/pkg', '1.0.0', BumpType::Minor))->toBe([]);
});

\it('returns empty array when the process output is empty', function (): void {
    $resolver = new AvailableVersionsResolver(\fakeProcessExecutor(''));

    \expect($resolver->resolve('vendor/pkg', '1.0.0', BumpType::Minor))->toBe([]);
});

\it('returns empty array when the JSON is malformed', function (): void {
    $resolver = new AvailableVersionsResolver(\fakeProcessExecutor('not-json'));

    \expect($resolver->resolve('vendor/pkg', '1.0.0', BumpType::Minor))->toBe([]);
});

\it('returns empty array when versions key is absent from JSON', function (): void {
    $resolver = new AvailableVersionsResolver(\fakeProcessExecutor('{}'));

    \expect($resolver->resolve('vendor/pkg', '1.0.0', BumpType::Minor))->toBe([]);
});

// ---------------------------------------------------------------------------
// Stability filter
// ---------------------------------------------------------------------------

\it('excludes pre-release versions', function (): void {
    $json     = (string) json_encode(['versions' => ['1.2.0', '1.2.0-beta', '1.1.5-rc1', '1.1.4']]);
    $resolver = new AvailableVersionsResolver(\fakeProcessExecutor($json));
    $result   = $resolver->resolve('vendor/pkg', '1.0.0', BumpType::Minor);

    \expect($result)->toHaveCount(2)
        ->and($result[0]->version)->toBe('1.2.0')
        ->and($result[1]->version)->toBe('1.1.4')
    ;
});

\it('excludes alpha, dev and pre versions', function (): void {
    $json = (string) json_encode([
        'versions' => ['2.0.0', '2.0.0-alpha.1', '2.0.0-dev', '2.0.0-pre', '1.5.0'],
    ]);
    $resolver = new AvailableVersionsResolver(\fakeProcessExecutor($json));
    $result   = $resolver->resolve('vendor/pkg', '1.0.0', BumpType::Major);

    \expect($result)->toHaveCount(1)
        ->and($result[0]->version)->toBe('2.0.0')
    ;
});

// ---------------------------------------------------------------------------
// Bump-type filtering — Patch
// ---------------------------------------------------------------------------

\it('returns only patch versions (same major.minor, higher patch)', function (): void {
    $json = (string) json_encode([
        'versions' => ['1.2.5', '1.2.4', '1.3.0', '2.0.0', '1.2.2'],
    ]);
    $resolver = new AvailableVersionsResolver(\fakeProcessExecutor($json));
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
    $json = (string) json_encode([
        'versions' => ['1.4.0', '1.3.2', '1.2.9', '0.9.0', '2.0.0'],
    ]);
    $resolver = new AvailableVersionsResolver(\fakeProcessExecutor($json));
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
    $json = (string) json_encode([
        'versions' => ['3.0.0', '2.5.0', '1.9.0', '1.2.3'],
    ]);
    $resolver = new AvailableVersionsResolver(\fakeProcessExecutor($json));
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
    $json     = (string) json_encode(['versions' => ['v1.3.0', 'v1.2.5']]);
    $resolver = new AvailableVersionsResolver(\fakeProcessExecutor($json));
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
    $json     = (string) json_encode(['versions' => ['1.3.0', '1.2.5', '1.2.2']]);
    $resolver = new AvailableVersionsResolver(\fakeProcessExecutor($json));
    $result   = $resolver->resolve('vendor/pkg', 'v1.2.3', BumpType::Patch);

    \expect($result)->toHaveCount(1)
        ->and($result[0]->version)->toBe('1.2.5')
    ;
});

// ---------------------------------------------------------------------------
// Empty result when no versions match
// ---------------------------------------------------------------------------

\it('returns empty array when no versions satisfy the bump filter', function (): void {
    $json     = (string) json_encode(['versions' => ['1.2.2', '1.2.1', '1.1.0']]);
    $resolver = new AvailableVersionsResolver(\fakeProcessExecutor($json));

    \expect($resolver->resolve('vendor/pkg', '1.2.3', BumpType::Patch))->toBe([]);
});

// ---------------------------------------------------------------------------
// Invalid current version
// ---------------------------------------------------------------------------

\it('returns empty array when current version cannot be parsed', function (): void {
    $json     = (string) json_encode(['versions' => ['1.3.0', '1.2.5']]);
    $resolver = new AvailableVersionsResolver(\fakeProcessExecutor($json));

    // 'abc' has no numeric version segments — VersionParser::normalize() throws UnexpectedValueException
    \expect($resolver->resolve('vendor/pkg', 'abc', BumpType::Minor))->toBe([]);
});
