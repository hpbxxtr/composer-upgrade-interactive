<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\UI;

use Hpbxxtr\UpgradeInteractive\Resolver\Age\ReleaseAgePolicy;
use Hpbxxtr\UpgradeInteractive\Resolver\AvailableVersionsResolverInterface;
use Hpbxxtr\UpgradeInteractive\Resolver\Compatibility\CompatibilityCheckerInterface;
use Hpbxxtr\UpgradeInteractive\Resolver\Compatibility\ConflictMap;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Override;

use function count;
use function Laravel\Prompts\confirm;
use function sprintf;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final readonly class InteractiveUI implements InteractiveUIInterface
{
    public function __construct(
        private ?AvailableVersionsResolverInterface $availableVersionsResolver = null,
        private ?CompatibilityCheckerInterface $compatibilityChecker = null,
        private ?ConflictMap $initialConflictMap = null,
        private ?ReleaseAgePolicy $releaseAgePolicy = null,
    ) {}

    /**
     * @param list<OutdatedPackage> $entries
     *
     * @return array<string, string> package name => raw version tag
     */
    #[Override]
    public function ask(array $entries): array
    {
        $upgradePrompt = new UpgradePrompt(
            $entries,
            availableVersionsResolver: $this->availableVersionsResolver,
            compatibilityChecker: $this->compatibilityChecker,
            initialConflictMap: $this->initialConflictMap,
            releaseAgePolicy: $this->releaseAgePolicy,
        );
        $upgradePrompt->prompt();

        $result = $upgradePrompt->value();

        if ($result === []) {
            return [];
        }

        $count = count($result);

        $isConfirmed = confirm(
            label: sprintf('Upgrade %d package%s?', $count, $count !== 1 ? 's' : ''),
            default: true,
        );

        return $isConfirmed ? $result : [];
    }
}
