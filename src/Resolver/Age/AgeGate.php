<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver\Age;

/**
 * Which release-age gate keeps a version from being selected.
 *
 * @internal Hpbxxtr\UpgradeInteractive
 */
enum AgeGate
{
    /** The plugin's own minimum release age (--min-age, extra, the `a` key). */
    case MinAge;

    /** Composer's config.policy.cooldown, which `composer update` would enforce anyway. */
    case Cooldown;
}
