<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
interface PackageResolverInterface
{
    /**
     * @return list<OutdatedPackage>
     *
     * @throws \RuntimeException
     * @throws \JsonException
     * @throws \ValueError
     */
    public function resolve(): array;
}
