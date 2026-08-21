<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver\Age;

use Hpbxxtr\UpgradeInteractive\Core\Duration;
use InvalidArgumentException;

use function is_array;
use function is_int;
use function is_string;
use function sprintf;

/**
 * Resolves the minimum-release-age threshold from the CLI option, falling back
 * to composer.json's extra section.
 *
 * @internal Hpbxxtr\UpgradeInteractive
 */
final readonly class AgeThresholdResolver
{
    public const string EXTRA_KEY = 'hpbxxtr-upgrade-interactive';

    public const string EXTRA_OPTION = 'minimum-release-age';

    /**
     * @param array<array-key, mixed> $extra root package "extra" section
     *
     * @throws InvalidArgumentException when either source holds a malformed value
     */
    public static function resolve(?string $optionValue, array $extra = []): ?Duration
    {
        if ($optionValue !== null && $optionValue !== '') {
            return Duration::parse($optionValue);
        }

        $configured = self::fromExtra($extra);

        return $configured !== null ? Duration::parse($configured) : null;
    }

    /**
     * @param array<array-key, mixed> $extra
     *
     * @throws InvalidArgumentException when the configured value is neither string nor int
     */
    private static function fromExtra(array $extra): ?string
    {
        $section = $extra[self::EXTRA_KEY] ?? null;

        if (!is_array($section)) {
            return null;
        }

        $value = $section[self::EXTRA_OPTION] ?? null;

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        throw new InvalidArgumentException(sprintf(
            'extra.%s.%s must be a string or an integer number of days.',
            self::EXTRA_KEY,
            self::EXTRA_OPTION,
        ));
    }
}
