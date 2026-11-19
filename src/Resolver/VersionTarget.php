<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver;

use Composer\Package\PackageInterface;
use DateTimeImmutable;
use Hpbxxtr\UpgradeInteractive\Core\Str;
use Hpbxxtr\UpgradeInteractive\Resolver\Age\CooldownPolicy;

use function method_exists;

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
         * Publication date as reported by the repository metadata ("published-time",
         * falling back to "time"); null when the repository provides neither
         * (path repositories, some VCS repos).
         */
        public ?DateTimeImmutable $releaseDate = null,
        /**
         * Whether Composer's policy.cooldown skips this version (dev version or a
         * matching policy.cooldown.ignore rule); only the min-age gate applies then.
         */
        public bool $isCooldownExempt = false,
    ) {}

    public static function fromRaw(string $raw, ?DateTimeImmutable $releaseDate = null, bool $isCooldownExempt = false): self
    {
        return new self(
            version: Str::trimStart($raw, 'v'),
            versionRaw: $raw,
            releaseDate: $releaseDate,
            isCooldownExempt: $isCooldownExempt,
        );
    }

    public static function fromPackage(PackageInterface $package, ?CooldownPolicy $cooldownPolicy = null): self
    {
        return self::fromRaw(
            $package->getPrettyVersion(),
            self::releaseDateOf($package),
            $cooldownPolicy?->isExempt($package) ?? false,
        );
    }

    /**
     * The date Composer's cooldown measures against: the server-set publication time,
     * falling back to the package-supplied release date.
     */
    public static function releaseDateOf(PackageInterface $package): ?DateTimeImmutable
    {
        // getPublishedDate() only exists since Composer 2.11; the plugin also runs on older 2.x.
        // @phpstan-ignore function.alreadyNarrowedType (always true against the dev dependency, not at runtime), symplify.forbiddenFuncCall (only way to feature-detect the Composer version)
        $publishedDate = method_exists($package, 'getPublishedDate') ? $package->getPublishedDate() : null;

        if ($publishedDate instanceof DateTimeImmutable) {
            return $publishedDate;
        }

        $releaseDate = $package->getReleaseDate();

        if (!$releaseDate instanceof \DateTimeInterface) {
            return null;
        }

        return DateTimeImmutable::createFromInterface($releaseDate);
    }
}
