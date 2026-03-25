<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Override;

/**
 * @api
 */
final class Plugin implements Capable, PluginInterface
{
    #[Override]
    public function activate(Composer $composer, IOInterface $io): void {}

    #[Override]
    public function deactivate(Composer $composer, IOInterface $io): void {}

    #[Override]
    public function uninstall(Composer $composer, IOInterface $io): void {}

    /**
     * @return array<string, string>
     */
    #[Override]
    public function getCapabilities(): array
    {
        return [CommandProviderCapability::class => CommandProvider::class];
    }
}
