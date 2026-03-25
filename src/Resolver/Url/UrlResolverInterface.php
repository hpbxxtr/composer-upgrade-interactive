<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver\Url;

use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
interface UrlResolverInterface
{
    public function isMatch(): bool;

    public function resolve(BumpType $bumpType): UrlResult;
}
