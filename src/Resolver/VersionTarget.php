<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver;

use Hpbxxtr\UpgradeInteractive\Core\Str;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final readonly class VersionTarget
{
    public function __construct(
        /**
         * Version without leading "v" (display).
         */
        public string $version,
        /**
         * Original version tag as returned by Composer (may have "v" prefix).
         */
        public string $versionRaw,
    ) {}

    public static function fromRaw(string $raw): self
    {
        return new self(
            version: Str::trimStart($raw, 'v'),
            versionRaw: $raw,
        );
    }
}
