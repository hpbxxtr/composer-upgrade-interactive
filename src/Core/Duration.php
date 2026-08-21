<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Core;

use InvalidArgumentException;

use function sprintf;

/**
 * A whole-unit time span such as "7d", "2w", "3m" or "1y".
 *
 * @internal Hpbxxtr\UpgradeInteractive
 */
final readonly class Duration
{
    private const int SECONDS_PER_DAY = 86400;

    private const array UNIT_ALIASES = [
        'd'      => DurationUnit::Day,
        'day'    => DurationUnit::Day,
        'days'   => DurationUnit::Day,
        'w'      => DurationUnit::Week,
        'week'   => DurationUnit::Week,
        'weeks'  => DurationUnit::Week,
        'm'      => DurationUnit::Month,
        'month'  => DurationUnit::Month,
        'months' => DurationUnit::Month,
        'y'      => DurationUnit::Year,
        'year'   => DurationUnit::Year,
        'years'  => DurationUnit::Year,
    ];

    public function __construct(
        public int $amount,
        public DurationUnit $unit = DurationUnit::Day,
    ) {}

    /**
     * Accepts "7d", "2 w", "3months", "1y" and a bare integer (days).
     *
     * @throws InvalidArgumentException on malformed input or an unknown unit
     */
    public static function parse(string $value): self
    {
        $match  = Str::match('/^(\d+)\s*([a-z]*)$/', Str::lower(Str::trim($value)));
        $amount = $match[1] ?? null;
        $unitKey = $match[2] ?? null;

        if ($amount === null || $unitKey === null) {
            throw new InvalidArgumentException(self::syntaxError($value));
        }

        if ($unitKey === '') {
            return new self((int) $amount);
        }

        $unit = self::UNIT_ALIASES[$unitKey] ?? throw new InvalidArgumentException(self::syntaxError($value));

        return new self((int) $amount, $unit);
    }

    public function toSeconds(): int
    {
        return $this->toDays() * self::SECONDS_PER_DAY;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function toDays(): int
    {
        return $this->amount * $this->unit->days();
    }

    public function format(): string
    {
        return $this->amount . $this->unit->value;
    }

    private static function syntaxError(string $value): string
    {
        return sprintf(
            'Invalid duration "%s". Expected a number of days (e.g. "14") or a value with a unit: "7d", "2w", "3m", "1y".',
            $value,
        );
    }
}
