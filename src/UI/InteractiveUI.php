<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\UI;

use Hpbxxtr\UpgradeInteractive\Resolver\AvailableVersionsResolverInterface;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Override;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final readonly class InteractiveUI implements InteractiveUIInterface
{
    public function __construct(
        private ?AvailableVersionsResolverInterface $availableVersionsResolver = null,
    ) {}

    /**
     * @param list<OutdatedPackage> $entries
     *
     * @return array<string, string> package name => raw version tag
     */
    #[Override]
    public function ask(array $entries): array
    {
        $upgradePrompt = new UpgradePrompt($entries, availableVersionsResolver: $this->availableVersionsResolver);
        $upgradePrompt->prompt();

        return $upgradePrompt->value();
    }
}
