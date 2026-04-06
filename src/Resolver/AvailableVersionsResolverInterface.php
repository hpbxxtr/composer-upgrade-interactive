<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
interface AvailableVersionsResolverInterface
{
    /**
     * Returns stable versions for $packageName that belong to the given $bumpType
     * relative to $currentVersion, sorted newest-first.
     *
     * @return list<VersionTarget>
     */
    public function resolve(string $packageName, string $currentVersion, BumpType $bumpType): array;
}
