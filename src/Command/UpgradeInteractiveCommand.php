<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Command;

use Composer\Command\BaseCommand;
use Composer\Util\ProcessExecutor;
use Hpbxxtr\UpgradeInteractive\Executor\ConstraintType;
use Hpbxxtr\UpgradeInteractive\Executor\UpgradeExecutor;
use Hpbxxtr\UpgradeInteractive\Executor\UpgradeExecutorInterface;
use Hpbxxtr\UpgradeInteractive\Resolver\AvailableVersionsResolver;
use Hpbxxtr\UpgradeInteractive\Resolver\PackageResolver;
use Hpbxxtr\UpgradeInteractive\Resolver\PackageResolverInterface;
use Hpbxxtr\UpgradeInteractive\UI\InteractiveUI;
use Hpbxxtr\UpgradeInteractive\UI\InteractiveUIInterface;
use Override;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @api
 */
final class UpgradeInteractiveCommand extends BaseCommand
{
    public function __construct(
        private readonly ?PackageResolverInterface $packageResolver = null,
        private readonly ?InteractiveUIInterface $interactiveUI = null,
        private readonly ?UpgradeExecutorInterface $upgradeExecutor = null,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->setName('hpbxxtr:upgrade-interactive')
            ->setAliases(['h:ui', 'upgrade-interactive'])
            ->setDescription('Interactively select and upgrade outdated dependencies')
            ->addOption(
                name: 'caret',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'Use caret (^) version range instead of exact version (e.g. ^1.2.3 instead of 1.2.3)',
            )
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getIO();
        $io->write('<info>Fetching composer package data…</info>');

        $processExecutor = null;

        try {
            if ($this->packageResolver instanceof PackageResolverInterface) {
                $entries = $this->packageResolver->resolve();
            } else {
                $composer        = $this->requireComposer();
                $processExecutor = $composer->getLoop()->getProcessExecutor() ?? new ProcessExecutor($this->getIO());
                $entries         = (new PackageResolver($composer))->resolve();
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

        $ui = $this->interactiveUI ?? new InteractiveUI(
            $processExecutor instanceof \Composer\Util\ProcessExecutor ? new AvailableVersionsResolver($processExecutor) : null,
        );

        $selections = $ui->ask($entries);

        if ($selections === []) {
            $io->write('<comment>No packages selected. Nothing to do.</comment>');

            return 0;
        }

        // getOption() returns mixed; !== false produces bool, isolating the mixed expression
        // from the executor try block so it doesn't contaminate type coverage there.
        $isCaret = $input->getOption('caret') !== false;

        try {
            $constraintType  = $isCaret ? ConstraintType::Caret : ConstraintType::Exact;
            $upgradeExecutor = $this->upgradeExecutor ?? new UpgradeExecutor($this->requireComposer(), $io);
            $upgradeExecutor->execute($selections, $constraintType);
        } catch (\Throwable $throwable) {
            $io->writeError('<error>' . $throwable->getMessage() . '</error>');

            return 1;
        }

        return 0;
    }
}
