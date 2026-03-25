<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive;

use Composer\Command\BaseCommand;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Hpbxxtr\UpgradeInteractive\Command\UpgradeInteractiveCommand;
use Override;

/**
 * @api
 */
final class CommandProvider implements CommandProviderCapability
{
    /**
     * @return list<BaseCommand>
     *
     * @throws \Symfony\Component\Console\Exception\LogicException
     */
    #[Override]
    public function getCommands(): array
    {
        return [new UpgradeInteractiveCommand()];
    }
}
