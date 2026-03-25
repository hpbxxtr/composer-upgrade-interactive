<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
enum BumpType: string
{
    case Patch = 'patch';
    case Minor = 'minor';
    case Major = 'major';
}
