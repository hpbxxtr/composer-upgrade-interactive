<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver;

use function array_filter;
use function array_values;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final readonly class OutdatedPackage
{
    public function __construct(
        public string $name,
        /**
         * Installed version without leading "v" (display).
         */
        public string $current,
        /**
         * Installed version as reported by Composer.
         */
        public string $currentRaw,
        public ?VersionTarget $patch,
        public ?VersionTarget $minor,
        public ?VersionTarget $major,
        public bool $isDev,
        public string $repoUrl,
        /**
         * null = not abandoned; empty string = abandoned with no stated replacement; non-empty = replacement package.
         */
        public ?string $abandonedBy = null,
    ) {}

    /**
     * @return list<BumpType>
     */
    public function availableBumps(): array
    {
        return array_values(array_filter([
            $this->patch instanceof VersionTarget ? BumpType::Patch : null,
            $this->minor instanceof VersionTarget ? BumpType::Minor : null,
            $this->major instanceof VersionTarget ? BumpType::Major : null,
        ]));
    }

    public function target(BumpType $bumpType): ?VersionTarget
    {
        return match ($bumpType) {
            BumpType::Patch => $this->patch,
            BumpType::Minor => $this->minor,
            BumpType::Major => $this->major,
        };
    }
}
