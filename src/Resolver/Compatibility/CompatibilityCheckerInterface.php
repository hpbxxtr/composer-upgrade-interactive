<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver\Compatibility;

use Hpbxxtr\UpgradeInteractive\Resolver\VersionSelection;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
interface CompatibilityCheckerInterface
{
    /**
     * Check whether one candidate version is compatible with a set of existing selections.
     *
     * @param array<string, VersionSelection> $selections  other selections, excluding $packageName
     * @return list<ConflictReason>  empty = compatible
     * @throws \UnexpectedValueException if a Link was constructed without a prettyConstraint
     */
    public function checkCandidate(
        string $packageName,
        VersionTarget $versionTarget,
        array $selections,
    ): array;
}
