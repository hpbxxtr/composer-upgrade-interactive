<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\Command\UpgradeInteractiveCommand;
use Hpbxxtr\UpgradeInteractive\CommandProvider;

\it('returns a list containing an UpgradeInteractiveCommand', function (): void {
    $provider = new CommandProvider();
    $commands = $provider->getCommands();

    \expect($commands)->toHaveCount(1)
        ->and($commands[0])->toBeInstanceOf(UpgradeInteractiveCommand::class)
    ;
});

\it('command has the expected name', function (): void {
    $provider = new CommandProvider();
    $commands = $provider->getCommands();

    \expect($commands[0]->getName())->toBe('hpbxxtr:upgrade-interactive');
});
