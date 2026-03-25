<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver\Url;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final readonly class UrlResult
{
    public function __construct(
        public ?string $compareUrl,
        public ?string $releaseUrl,
    ) {}
}
