<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\UI;

use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;

/** @internal */
final readonly class PickerColumn
{
    /** @param list<VersionTarget> $versions newest-first */
    public function __construct(
        public string $label,
        public array $versions,
    ) {}
}
