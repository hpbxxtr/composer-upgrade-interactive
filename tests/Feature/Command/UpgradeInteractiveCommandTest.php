<?php

declare(strict_types=1);

use Composer\Console\Application as ComposerApplication;
use Composer\IO\BufferIO;
use Composer\IO\ConsoleIO;
use Hpbxxtr\UpgradeInteractive\Command\UpgradeInteractiveCommand;
use Hpbxxtr\UpgradeInteractive\Resolver\PackageResolverInterface;
use Hpbxxtr\UpgradeInteractive\UI\InteractiveUIInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Wire up a command backed by a Composer\Console\Application (required by
 * BaseCommand::getApplication()) and a non-interactive BufferIO.
 *
 * setIO() is called after app->add() so our IO is never overridden:
 * BaseCommand::getIO() returns $this->io directly when it is already set.
 *
 * @return array{CommandTester, BufferIO}
 */
function makeCommandTester(
    PackageResolverInterface $packageResolver,
    ?InteractiveUIInterface $interactiveUI = null,
): array {
    $command = new UpgradeInteractiveCommand($packageResolver, $interactiveUI);

    $app = new ComposerApplication();
    $app->setAutoExit(false);
    $app->addCommand($command);

    $io = new BufferIO();
    $command->setIO($io);   // after add() so the app never pulls its NullIO back

    return [new CommandTester($command), $io];
}

/**
 * Same as makeCommandTester but wires a ConsoleIO whose isInteractive()
 * returns true, enabling the interactive branch of the command.
 *
 * @return array{CommandTester, ConsoleIO, BufferedOutput}
 */
function makeInteractiveCommandTester(
    PackageResolverInterface $packageResolver,
    ?InteractiveUIInterface $interactiveUI = null,
): array {
    $command = new UpgradeInteractiveCommand($packageResolver, $interactiveUI);

    $app = new ComposerApplication();
    $app->setAutoExit(false);
    $app->addCommand($command);

    $input = new ArrayInput([]);
    $input->setInteractive(true);
    $buffered = new BufferedOutput();
    $io       = new ConsoleIO($input, $buffered, new HelperSet());
    $command->setIO($io);

    return [new CommandTester($command), $io, $buffered];
}

// ---------------------------------------------------------------------------
// All packages up to date
// ---------------------------------------------------------------------------

\it('exits 0 and writes "up to date" when resolver returns no packages', function (): void {
    $mock = \Mockery::mock(PackageResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([]);

    [$tester, $io] = \makeCommandTester($mock);
    $tester->execute([]);

    \expect($tester->getStatusCode())->toBe(0)
        ->and($io->getOutput())->toContain('All direct dependencies are up to date.')
    ;
});

\it('does not invoke the UI when no packages are outdated', function (): void {
    $mock = \Mockery::mock(PackageResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([]);

    $ui = \Mockery::mock(InteractiveUIInterface::class);
    $ui->shouldNotReceive('ask');

    [$tester] = \makeCommandTester($mock, $ui);
    $tester->execute([]);

    \expect($tester->getStatusCode())->toBe(0);
});

// ---------------------------------------------------------------------------
// Non-interactive terminal
// ---------------------------------------------------------------------------

\it('exits 1 with an error when the terminal is non-interactive', function (): void {
    $mock = \Mockery::mock(PackageResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([\outdatedPackageWithMinor()]);

    // BufferIO is always non-interactive
    [$tester, $io] = \makeCommandTester($mock);
    $tester->execute([]);

    \expect($tester->getStatusCode())->toBe(1)
        ->and($io->getOutput())->toContain('upgrade-interactive requires an interactive terminal.')
    ;
});

\it('does not invoke the UI when the terminal is non-interactive', function (): void {
    $mock = \Mockery::mock(PackageResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([\outdatedPackageWithMinor()]);

    $ui = \Mockery::mock(InteractiveUIInterface::class);
    $ui->shouldNotReceive('ask');

    [$tester] = \makeCommandTester($mock, $ui);
    $tester->execute([]);

    \expect($tester->getStatusCode())->toBe(1);
});

// ---------------------------------------------------------------------------
// Interactive terminal — user makes no selection
// ---------------------------------------------------------------------------

\it('exits 0 and writes "nothing to do" when the user confirms with no packages selected', function (): void {
    $mock = \Mockery::mock(PackageResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([\outdatedPackageWithMinor()]);

    $ui = \Mockery::mock(InteractiveUIInterface::class);
    $ui->shouldReceive('ask')->once()->andReturn([]);

    [$tester, , $buffered] = \makeInteractiveCommandTester($mock, $ui);
    $tester->execute([]);

    \expect($tester->getStatusCode())->toBe(0)
        ->and($buffered->fetch())->toContain('No packages selected. Nothing to do.')
    ;
});

// ---------------------------------------------------------------------------
// Resolver output is forwarded intact to the UI
// ---------------------------------------------------------------------------

\it('passes every package returned by the resolver to the UI', function (): void {
    $packages = [
        \outdatedPackageWithMinor('vendor/alpha', '1.0.0', '1.1.0'),
        \outdatedPackageWithMinor('vendor/beta', '2.0.0', '2.1.0'),
    ];

    $mock = \Mockery::mock(PackageResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn($packages);

    $ui = \Mockery::mock(InteractiveUIInterface::class);
    $ui->shouldReceive('ask')
        ->once()
        ->withArgs(static fn (array $entries): bool => $entries === $packages)
        ->andReturn([])
    ;

    [$tester] = \makeInteractiveCommandTester($mock, $ui);
    $tester->execute([]);

    \expect($tester->getStatusCode())->toBe(0);
});

\it('forwards both prod and dev packages to the UI', function (): void {
    $packages = [
        \outdatedPackageWithMinor('vendor/prod-lib', isDev: false),
        \outdatedPackageWithMinor('vendor/dev-lib', isDev: true),
    ];

    $mock = \Mockery::mock(PackageResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn($packages);

    $capturedEntries = null;
    $ui              = \Mockery::mock(InteractiveUIInterface::class);
    $ui->shouldReceive('ask')
        ->once()
        ->andReturnUsing(static function (array $entries) use (&$capturedEntries): array {
            $capturedEntries = $entries;

            return [];
        })
    ;

    [$tester] = \makeInteractiveCommandTester($mock, $ui);
    $tester->execute([]);

    \expect($capturedEntries)->toHaveCount(2)
        ->and($capturedEntries[0]->isDev)->toBeFalse()
        ->and($capturedEntries[1]->isDev)->toBeTrue()
    ;
});

// ---------------------------------------------------------------------------
// Abandoned packages
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// Resolver exception handling
// ---------------------------------------------------------------------------

\it('exits 1 and writes the error when the resolver throws', function (): void {
    $mock = \Mockery::mock(PackageResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andThrow(new \RuntimeException('connection refused'));

    [$tester, $io] = \makeCommandTester($mock);
    $tester->execute([]);

    \expect($tester->getStatusCode())->toBe(1)
        ->and($io->getOutput())->toContain('connection refused')
    ;
});

// ---------------------------------------------------------------------------
// Abandoned packages
// ---------------------------------------------------------------------------

\it('includes abandoned-only packages (no available update) in the UI call', function (): void {
    $outdatedPackage = \outdatedPackage(
        name: 'vendor/dead-lib',
        current: '1.0.0',
        abandonedBy: 'vendor/successor',
    );

    $mock = \Mockery::mock(PackageResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([$outdatedPackage]);

    $ui = \Mockery::mock(InteractiveUIInterface::class);
    $ui->shouldReceive('ask')
        ->once()
        ->withArgs(
            static fn (array $entries): bool => \count($entries) === 1 && $entries[0]->abandonedBy === 'vendor/successor',
        )
        ->andReturn([])
    ;

    [$tester] = \makeInteractiveCommandTester($mock, $ui);
    $tester->execute([]);

    \expect($tester->getStatusCode())->toBe(0);
});
