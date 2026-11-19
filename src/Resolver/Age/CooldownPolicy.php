<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver\Age;

use Composer\Package\PackageInterface;
use Composer\Policy\CooldownPolicyConfig;

use function array_slice;
use function implode;
use function intdiv;

/**
 * Composer's blocking `config.policy.cooldown` (Composer 2.11+), reduced to what the
 * release-age gate needs. The only class that touches Composer's policy internals.
 *
 * @internal Hpbxxtr\UpgradeInteractive
 */
final readonly class CooldownPolicy
{
    /**
     * Label units, largest first. "min" rather than "m": "m" means months in Duration.
     */
    private const array LABEL_UNITS = [
        'd'   => 86400,
        'h'   => 3600,
        'min' => 60,
        's'   => 1,
    ];

    private const int LABEL_MAX_PARTS = 2;

    private function __construct(
        // @phpstan-ignore brnshkr.internalUsage (Composer exposes the cooldown policy only through @internal API; isolated to this class)
        private CooldownPolicyConfig $cooldownPolicyConfig,
        private int $periodSeconds,
    ) {}

    /**
     * @param CooldownPolicyConfig|null $cooldownPolicyConfig the policy Composer would block an update with, or null when none applies
     */
    public static function fromComposerConfig(?CooldownPolicyConfig $cooldownPolicyConfig): ?self
    {
        if (!$cooldownPolicyConfig instanceof CooldownPolicyConfig) {
            return null;
        }

        // @phpstan-ignore brnshkr.internalUsage (Composer exposes the cooldown policy only through @internal API; isolated to this class)
        $period = $cooldownPolicyConfig->period;

        // Mirrors CooldownPolicyConfig::hasCooldown(): no period or 0 means no cooldown.
        if ($period === null || $period <= 0) {
            return null;
        }

        return new self($cooldownPolicyConfig, $period);
    }

    public function periodSeconds(): int
    {
        return $this->periodSeconds;
    }

    /**
     * Up to two whole units, largest first: "7d", "1d 12h", "1h 30min", "45s".
     */
    public function label(): string
    {
        $remaining = $this->periodSeconds;
        $parts     = [];

        foreach (self::LABEL_UNITS as $unit => $seconds) {
            $amount    = intdiv($remaining, $seconds);
            $remaining -= $amount * $seconds;

            if ($amount > 0) {
                $parts[] = $amount . $unit;
            }
        }

        return implode(' ', array_slice($parts, 0, self::LABEL_MAX_PARTS));
    }

    /**
     * Dev versions and versions matching a policy.cooldown.ignore rule bypass the cooldown,
     * exactly as Composer's own VersionSelector treats them.
     */
    public function isExempt(PackageInterface $package): bool
    {
        // @phpstan-ignore brnshkr.internalUsage (Composer exposes the cooldown policy only through @internal API; isolated to this class)
        return $package->isDev() || $this->cooldownPolicyConfig->isIgnored($package, 'block');
    }
}
