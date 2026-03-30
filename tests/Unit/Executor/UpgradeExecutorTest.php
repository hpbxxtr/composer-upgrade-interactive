<?php

declare(strict_types=1);

use Composer\Composer;
use Composer\Console\Application;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Hpbxxtr\UpgradeInteractive\Executor\ConstraintType;
use Hpbxxtr\UpgradeInteractive\Executor\UpgradeExecutor;
use Symfony\Component\Console\Input\InputInterface;

afterEach(function (): void {
    \Mockery::close();
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a Composer mock with getPackage()->getDevRequires() returning $devRequires.
 *
 * @param array<string, mixed> $devRequires package-name-keyed array (values ignored)
 */
function makeComposer(array $devRequires = []): Composer
{
    $mock = \Mockery::mock(RootPackageInterface::class);
    $mock->shouldReceive('getDevRequires')->andReturn($devRequires);

    $composer = \Mockery::mock(Composer::class);
    $composer->shouldReceive('getPackage')->andReturn($mock);

    return $composer;
}

/**
 * Build an Application mock whose run() always returns $exitCode.
 */
function makeApp(int $exitCode = 0): Application
{
    $mock = \Mockery::mock(Application::class);
    $mock->shouldReceive('setAutoExit')->byDefault();
    $mock->shouldReceive('run')->byDefault()->andReturn($exitCode);
    $mock->shouldReceive('resetComposer')->byDefault();

    return $mock;
}

/**
 * Build an IOInterface mock that accepts write() / writeError() calls.
 */
function makeIO(): IOInterface
{
    $mock = \Mockery::mock(IOInterface::class);
    $mock->shouldReceive('write')->byDefault();
    $mock->shouldReceive('writeError')->byDefault();

    return $mock;
}

// ---------------------------------------------------------------------------
// Prod-only selection
// ---------------------------------------------------------------------------

\it('runs require then update for a prod-only selection', function (): void {
    $application = \makeApp();
    $application->shouldReceive('run')->twice()->andReturn(0);
    $application->shouldReceive('resetComposer')->once();

    $executor = new UpgradeExecutor(\makeComposer(), \makeIO(), $application);
    $executor->execute(['vendor/pkg' => '1.3.0']);
});

\it('writes the package count header', function (): void {
    $io = \makeIO();
    $io->shouldReceive('write')
        ->once()
        ->withArgs(static fn (string $msg): bool => str_contains($msg, 'Updating 1 package'))
    ;

    $executor = new UpgradeExecutor(\makeComposer(), $io, \makeApp());
    $executor->execute(['vendor/pkg' => '1.3.0']);
});

\it('writes each package name before running require', function (): void {
    $io = \makeIO();
    $io->shouldReceive('write')
        ->once()
        ->withArgs(static fn (string $msg): bool => str_contains($msg, 'vendor/pkg'))
    ;

    $executor = new UpgradeExecutor(\makeComposer(), $io, \makeApp());
    $executor->execute(['vendor/pkg' => '1.3.0']);
});

\it('strips a leading v from the version passed to require', function (): void {
    $application = \makeApp();

    $application->shouldReceive('run')
        ->once()
        ->withArgs(static fn (InputInterface $input): bool => str_contains((string) $input, 'vendor/pkg:1.3.0'))
        ->andReturn(0)
    ;
    $application->shouldReceive('run')->once()->andReturn(0); // update call
    $application->shouldReceive('resetComposer');

    $executor = new UpgradeExecutor(\makeComposer(), \makeIO(), $application);
    $executor->execute(['vendor/pkg' => 'v1.3.0']); // leading v
});

// ---------------------------------------------------------------------------
// Dev selection
// ---------------------------------------------------------------------------

\it('adds --dev flag for dev packages', function (): void {
    $application = \makeApp();

    $application->shouldReceive('run')
        ->once()
        ->withArgs(static fn (InputInterface $input): bool => str_contains((string) $input, '--dev'))
        ->andReturn(0)
    ;
    $application->shouldReceive('run')->once()->andReturn(0); // update call
    $application->shouldReceive('resetComposer');

    $devRequires = ['vendor/dev-pkg' => new stdClass()];
    $executor    = new UpgradeExecutor(\makeComposer($devRequires), \makeIO(), $application);
    $executor->execute(['vendor/dev-pkg' => '1.1.0']);
});

// ---------------------------------------------------------------------------
// Failure paths
// ---------------------------------------------------------------------------

\it('writes an error and skips update when require fails', function (): void {
    $application = \makeApp(1); // run() always returns non-zero
    $application->shouldReceive('resetComposer')->never();

    $io = \makeIO();
    $io->shouldReceive('writeError')
        ->once()
        ->withArgs(static fn (string $msg): bool => str_contains($msg, 'composer require failed'))
    ;

    $executor = new UpgradeExecutor(\makeComposer(), $io, $application);
    $executor->execute(['vendor/pkg' => '1.3.0']);
});

// ---------------------------------------------------------------------------
// Constraint type
// ---------------------------------------------------------------------------

\it('uses an exact version constraint by default', function (): void {
    $application = \makeApp();

    $application->shouldReceive('run')
        ->once()
        ->withArgs(static fn (InputInterface $input): bool => str_contains((string) $input, 'vendor/pkg:1.3.0'))
        ->andReturn(0)
    ;
    $application->shouldReceive('run')->once()->andReturn(0); // update call
    $application->shouldReceive('resetComposer');

    $executor = new UpgradeExecutor(\makeComposer(), \makeIO(), $application);
    $executor->execute(['vendor/pkg' => '1.3.0'], ConstraintType::Exact);
});

\it('prefixes the version with ^ when caret constraint type is requested', function (): void {
    $application = \makeApp();

    $application->shouldReceive('run')
        ->once()
        ->withArgs(static fn (InputInterface $input): bool => str_contains((string) $input, 'vendor/pkg:^1.3.0'))
        ->andReturn(0)
    ;
    $application->shouldReceive('run')->once()->andReturn(0); // update call
    $application->shouldReceive('resetComposer');

    $executor = new UpgradeExecutor(\makeComposer(), \makeIO(), $application);
    $executor->execute(['vendor/pkg' => '1.3.0'], ConstraintType::Caret);
});

\it('strips leading v before adding the caret prefix', function (): void {
    $application = \makeApp();

    $application->shouldReceive('run')
        ->once()
        ->withArgs(static fn (InputInterface $input): bool => str_contains((string) $input, 'vendor/pkg:^1.3.0'))
        ->andReturn(0)
    ;
    $application->shouldReceive('run')->once()->andReturn(0); // update call
    $application->shouldReceive('resetComposer');

    $executor = new UpgradeExecutor(\makeComposer(), \makeIO(), $application);
    $executor->execute(['vendor/pkg' => 'v1.3.0'], ConstraintType::Caret);
});

\it('writes an error when the final update fails', function (): void {
    $mock = \Mockery::mock(Application::class);
    $mock->shouldReceive('setAutoExit')->byDefault();
    $mock->shouldReceive('resetComposer')->byDefault();
    $mock->shouldReceive('run')->once()->andReturn(0); // require succeeds
    $mock->shouldReceive('run')->once()->andReturn(1); // update fails

    $io = \makeIO();
    $io->shouldReceive('writeError')
        ->once()
        ->withArgs(static fn (string $msg): bool => str_contains($msg, 'composer update failed'))
    ;

    $executor = new UpgradeExecutor(\makeComposer(), $io, $mock);
    $executor->execute(['vendor/pkg' => '1.3.0']);
});
