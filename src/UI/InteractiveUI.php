<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\UI;

use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Override;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final class InteractiveUI implements InteractiveUIInterface
{
    /**
     * @param list<OutdatedPackage> $entries
     *
     * @return array<string, string> package name => raw version tag
     */
    #[Override]
    public function ask(array $entries): array
    {
        $upgradePrompt = new UpgradePrompt($entries);
        $upgradePrompt->prompt();

        return $upgradePrompt->value();
    }
}
