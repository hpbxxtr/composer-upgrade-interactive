<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final readonly class VersionSelection
{
    public function __construct(
        public BumpType $column,
        public VersionTarget $target,
    ) {}
}
