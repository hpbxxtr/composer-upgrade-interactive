<?php

declare(strict_types=1);

use Composer\Composer;
use Composer\IO\NullIO;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Hpbxxtr\UpgradeInteractive\CommandProvider;
use Hpbxxtr\UpgradeInteractive\Plugin;

// ---------------------------------------------------------------------------
// getCapabilities
// ---------------------------------------------------------------------------

\it('maps the CommandProvider capability to its implementation', function (): void {
    $plugin = new Plugin();

    \expect($plugin->getCapabilities())->toBe([
        CommandProviderCapability::class => CommandProvider::class,
    ]);
});

// ---------------------------------------------------------------------------
// Lifecycle no-ops (activate / deactivate / uninstall)
// ---------------------------------------------------------------------------

\it('activate does not throw', function (): void {
    $plugin   = new Plugin();
    $mock = \Mockery::mock(Composer::class);
    $io       = new NullIO();

    \expect(static fn () => $plugin->activate($mock, $io))->not->toThrow(\Throwable::class);
});

\it('deactivate does not throw', function (): void {
    $plugin   = new Plugin();
    $mock = \Mockery::mock(Composer::class);
    $io       = new NullIO();

    \expect(static fn () => $plugin->deactivate($mock, $io))->not->toThrow(\Throwable::class);
});

\it('uninstall does not throw', function (): void {
    $plugin   = new Plugin();
    $mock = \Mockery::mock(Composer::class);
    $io       = new NullIO();

    \expect(static fn () => $plugin->uninstall($mock, $io))->not->toThrow(\Throwable::class);
});
