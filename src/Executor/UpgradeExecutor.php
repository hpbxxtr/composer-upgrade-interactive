<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Executor;

use Composer\Composer;
use Composer\Console\Application;
use Composer\IO\IOInterface;
use Hpbxxtr\UpgradeInteractive\Core\Str;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

use function array_flip;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function sprintf;

use const PHP_EOL;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final readonly class UpgradeExecutor implements UpgradeExecutorInterface
{
    public function __construct(
        private Composer $composer,
        private IOInterface $io,
        private ?Application $application = null,
    ) {}

    /**
     * @param array<string, string> $selections package name => raw version tag
     *
     * @throws \Exception
     */
    #[\Override]
    public function execute(array $selections, ConstraintType $constraintType = ConstraintType::Exact): void
    {
        $count = count($selections);
        $this->io->write(PHP_EOL . sprintf('<info>Updating %d package(s)…</info>', $count) . PHP_EOL);

        // Split into prod / dev by checking root package requires
        $devSet = array_flip(array_keys($this->composer->getPackage()->getDevRequires()));

        $prod = [];
        $dev  = [];

        foreach ($selections as $name => $version) {
            if (isset($devSet[$name])) {
                $dev[$name] = $version;
            } else {
                $prod[$name] = $version;
            }
        }

        $application = $this->application ?? new Application();
        $application->setAutoExit(false);
        $consoleOutput = new ConsoleOutput();

        foreach ([0 => $prod, 1 => $dev] as $isDev => $group) {
            /** @var array<string, string> $group */
            if ($group === []) {
                continue;
            }

            foreach (array_keys($group) as $name) {
                $this->io->write(sprintf('  <comment>→</comment> <info>%s</info>', $name));
            }

            $prefix   = $constraintType === ConstraintType::Caret ? '^' : '';
            $packages = array_map(
                static fn (string $name, string $ver): string => $name . ':' . $prefix . Str::trimStart($ver, 'v'),
                array_keys($group),
                array_values($group),
            );

            $args = [
                'command'     => 'require',
                'packages'    => $packages,
                '--no-update' => true,
            ];

            if ($isDev !== 0) {
                $args['--dev'] = true;
            }

            $input = new ArrayInput($args);
            $input->setInteractive(false);

            if ($application->run($input, $consoleOutput) !== 0) {
                $this->io->writeError('<error>composer require failed.</error>');

                return;
            }
        }

        // Force a fresh composer.json read so the update step sees the
        // constraints that require --no-update just wrote to disk.
        $application->resetComposer();

        // Run composer update for all selected packages
        $input = new ArrayInput([
            'command'                 => 'update',
            'packages'                => array_keys($selections),
            '--with-all-dependencies' => true,
        ]);
        $input->setInteractive(false);

        if ($application->run($input, $consoleOutput) !== 0) {
            $this->io->writeError('<error>composer update failed.</error>');
        }
    }
}
