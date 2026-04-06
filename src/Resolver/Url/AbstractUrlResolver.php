<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver\Url;

use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
abstract class AbstractUrlResolver implements UrlResolverInterface
{
    public function __construct(
        protected readonly OutdatedPackage $package,
    ) {}

    protected function targetVersion(BumpType $bumpType, ?VersionTarget $versionTarget = null): ?VersionTarget
    {
        return $versionTarget ?? $this->package->target($bumpType);
    }
}
