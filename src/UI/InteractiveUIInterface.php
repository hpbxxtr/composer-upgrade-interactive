<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\UI;

use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
interface InteractiveUIInterface
{
    /**
     * @param list<OutdatedPackage> $entries
     *
     * @return array<string, string> package name => raw version tag
     */
    public function ask(array $entries): array;
}
