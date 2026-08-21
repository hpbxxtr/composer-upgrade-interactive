<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver;

use Composer\Package\PackageInterface;
use DateTimeImmutable;
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
        /**
         * Publication date as reported by the repository metadata; null when the
         * repository provides no "time" field (path repositories, some VCS repos).
         */
        public ?DateTimeImmutable $releaseDate = null,
    ) {}

    public static function fromRaw(string $raw, ?DateTimeImmutable $releaseDate = null): self
    {
        return new self(
            version: Str::trimStart($raw, 'v'),
            versionRaw: $raw,
            releaseDate: $releaseDate,
        );
    }

    public static function fromPackage(PackageInterface $package): self
    {
        return self::fromRaw($package->getPrettyVersion(), self::releaseDateOf($package));
    }

    public static function releaseDateOf(PackageInterface $package): ?DateTimeImmutable
    {
        $releaseDate = $package->getReleaseDate();

        if (!$releaseDate instanceof \DateTimeInterface) {
            return null;
        }

        return DateTimeImmutable::createFromInterface($releaseDate);
    }
}
