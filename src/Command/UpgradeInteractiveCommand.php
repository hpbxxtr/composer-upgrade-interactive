<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Command;

use Composer\Command\BaseCommand;
use Hpbxxtr\UpgradeInteractive\Executor\UpgradeExecutor;
use Hpbxxtr\UpgradeInteractive\Resolver\PackageResolver;
use Hpbxxtr\UpgradeInteractive\Resolver\PackageResolverInterface;
use Hpbxxtr\UpgradeInteractive\UI\InteractiveUI;
use Hpbxxtr\UpgradeInteractive\UI\InteractiveUIInterface;
use Composer\Util\ProcessExecutor;
use Override;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @api
 */
final class UpgradeInteractiveCommand extends BaseCommand
{
    public function __construct(
        private readonly ?PackageResolverInterface $packageResolver = null,
        private readonly ?InteractiveUIInterface $interactiveUI = null,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->setName('hpbxxtr:upgrade-interactive')
            ->setAliases(['h:ui', 'upgrade-interactive'])
            ->setDescription('Interactively select and upgrade outdated dependencies')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getIO();
        $io->write('<info>Fetching composer package data…</info>');

        try {
            if ($this->packageResolver instanceof PackageResolverInterface) {
                $entries = $this->packageResolver->resolve();
            } else {
                $composer        = $this->requireComposer();
                $processExecutor = $composer->getLoop()->getProcessExecutor() ?? new ProcessExecutor($this->getIO());
                $entries         = (new PackageResolver($composer, $processExecutor))->resolve();
            }
        } catch (\Throwable $throwable) {
            $io->writeError('<error>' . $throwable->getMessage() . '</error>');

            return 1;
        }

        if ($entries === []) {
            $io->write('<info>All direct dependencies are up to date.</info>');

            return 0;
        }

        if (!$io->isInteractive()) {
            $io->writeError('<error>upgrade-interactive requires an interactive terminal.</error>');

            return 1;
        }

        $ui         = $this->interactiveUI ?? new InteractiveUI();
        $selections = $ui->ask($entries);

        if ($selections === []) {
            $io->write('<comment>No packages selected. Nothing to do.</comment>');

            return 0;
        }

        try {
            $upgradeExecutor = new UpgradeExecutor($this->requireComposer(), $io);
            $upgradeExecutor->execute($selections);
        } catch (\Throwable $throwable) {
            $io->writeError('<error>' . $throwable->getMessage() . '</error>');

            return 1;
        }

        return 0;
    }
}
