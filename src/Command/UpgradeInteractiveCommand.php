<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Command;

use Composer\Composer;
use Composer\Command\BaseCommand;
use InvalidArgumentException;
use Hpbxxtr\UpgradeInteractive\Executor\ConstraintType;
use Hpbxxtr\UpgradeInteractive\Executor\UpgradeExecutor;
use Hpbxxtr\UpgradeInteractive\Executor\UpgradeExecutorInterface;
use Hpbxxtr\UpgradeInteractive\Core\Duration;
use Hpbxxtr\UpgradeInteractive\Resolver\Age\AgeThresholdResolver;
use Hpbxxtr\UpgradeInteractive\Resolver\Age\CooldownPolicy;
use Hpbxxtr\UpgradeInteractive\Resolver\Age\ReleaseAgePolicy;
use Hpbxxtr\UpgradeInteractive\Resolver\AvailableVersionsResolver;
use Hpbxxtr\UpgradeInteractive\Resolver\Compatibility\CompatibilityChecker;
use Hpbxxtr\UpgradeInteractive\Resolver\Compatibility\ConflictMap;
use Hpbxxtr\UpgradeInteractive\Resolver\PackageResolver;
use Hpbxxtr\UpgradeInteractive\Resolver\PackageResolverInterface;
use Hpbxxtr\UpgradeInteractive\UI\InteractiveUI;
use Hpbxxtr\UpgradeInteractive\UI\InteractiveUIInterface;
use Override;
use RuntimeException;

use function is_string;
use function Laravel\Prompts\spin;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

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
                mode: InputOption::VALUE_NONE,
                description: 'Use caret (^) version range instead of exact version (e.g. ^1.2.3 instead of 1.2.3)',
            )
            ->addOption(
                name: 'min-age',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Minimum release age before a version may be selected, e.g. 7d, 2w, 3m, 1y or a number of days',
            )
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getIO();

        try {
            $cooldownPolicy   = $this->resolveCooldown($input);
            $releaseAgePolicy = new ReleaseAgePolicy($this->resolveAgeThreshold($input), cooldownPolicy: $cooldownPolicy);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $io->writeError('<error>' . $exception->getMessage() . '</error>');

            return 1;
        }

        /** @var CompatibilityChecker|null $checker */
        $checker = null;
        /** @var ConflictMap|null $initialConflictMap */
        $initialConflictMap = null;

        try {
            if ($this->packageResolver instanceof PackageResolverInterface) {
                /** @var list<\Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage> $entries */
                $entries = spin(fn (): array => $this->packageResolver->resolve(), 'Fetching package data…');
            } else {
                $composer = $this->requireComposer();

                /** @var list<\Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage> $entries */
                $entries = spin(function () use ($composer, $cooldownPolicy, &$checker, &$initialConflictMap): array {
                    $resolved = (new PackageResolver($composer, cooldownPolicy: $cooldownPolicy))->resolve();

                    if ($resolved !== []) {
                        $installedVersions  = self::collectInstalledVersions($composer);
                        $checker            = new CompatibilityChecker($composer, installedVersions: $installedVersions);
                        $initialConflictMap = $checker->computeInitialConflicts($resolved);
                    }

                    return $resolved;
                }, 'Fetching package data…');
            }
        } catch (Throwable $throwable) {
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
            availableVersionsResolver: isset($composer) ? new AvailableVersionsResolver($composer, cooldownPolicy: $cooldownPolicy) : null,
            compatibilityChecker: $checker,
            initialConflictMap: $initialConflictMap,
            releaseAgePolicy: $releaseAgePolicy,
        );

        $selections = $ui->ask($entries);

        if ($selections === []) {
            $io->write('<comment>No packages selected. Nothing to do.</comment>');

            return 0;
        }

        $isCaret = $input->getOption('caret') !== false;

        try {
            $constraintType  = $isCaret ? ConstraintType::Caret : ConstraintType::Exact;
            $upgradeExecutor = $this->upgradeExecutor ?? new UpgradeExecutor($this->requireComposer(), $io);
            $upgradeExecutor->execute($selections, $constraintType);
        } catch (Throwable $throwable) {
            $io->writeError('<error>' . $throwable->getMessage() . '</error>');

            return 1;
        }

        return 0;
    }

    /**
     * CLI option first, then the root package's extra section.
     *
     * @throws InvalidArgumentException on a malformed threshold
     */
    private function resolveAgeThreshold(InputInterface $input): ?Duration
    {
        $option = $input->getOption('min-age');

        if (is_string($option) && $option !== '') {
            return AgeThresholdResolver::resolve($option);
        }

        return AgeThresholdResolver::resolve(null, $this->tryComposer()?->getPackage()->getExtra() ?? []);
    }

    /**
     * Composer's policy.cooldown, but only when Composer would actually block an update
     * with it (honours COMPOSER_POLICY_COOLDOWN_* and COMPOSER_NO_BLOCKING).
     *
     * @throws RuntimeException on a malformed cooldown period in config or environment
     */
    private function resolveCooldown(InputInterface $input): ?CooldownPolicy
    {
        if (!CooldownPolicy::isSupported()) {
            return null; // @codeCoverageIgnore
        }

        $composer = $this->tryComposer();

        if (!$composer instanceof Composer) {
            return null;
        }

        // @phpstan-ignore brnshkr.internalUsage (Composer's own resolution of policy.cooldown incl. env overrides; no public API exists)
        return CooldownPolicy::fromComposerConfig($this->getBlockingCooldownPolicy($composer->getConfig(), $input));
    }

    /**
     * @return array<string, string>
     */
    private static function collectInstalledVersions(Composer $composer): array
    {
        $directDepNames = array_merge(
            array_keys($composer->getPackage()->getRequires()),
            array_keys($composer->getPackage()->getDevRequires()),
        );
        $isDirectDep = array_flip($directDepNames);
        $result      = [];

        foreach ($composer->getRepositoryManager()->getLocalRepository()->getPackages() as $basePackage) {
            if (isset($isDirectDep[$basePackage->getName()])) {
                $result[$basePackage->getName()] = $basePackage->getPrettyVersion();
            }
        }

        return $result;
    }
}
