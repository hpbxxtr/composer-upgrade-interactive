<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver\Age;

use DateTimeImmutable;
use Hpbxxtr\UpgradeInteractive\Core\Duration;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;

/**
 * Decides whether a candidate release is old enough to be selectable.
 *
 * Two gates apply: the plugin's minimum release age and, when Composer would block
 * an update with it, Composer's policy.cooldown. The cooldown is a floor: lowering
 * or disabling the min age never makes a version inside the cooldown selectable.
 *
 * Permissive by design: a release with no known date (path repositories, some
 * private VCS repositories, dev branches) is always eligible.
 *
 * @internal Hpbxxtr\UpgradeInteractive
 */
final readonly class ReleaseAgePolicy
{
    private DateTimeImmutable $now;

    public function __construct(
        private ?Duration $duration = null,
        ?DateTimeImmutable $now = null,
        private ?CooldownPolicy $cooldownPolicy = null,
    ) {
        $this->now = $now ?? new DateTimeImmutable('now');
    }

    public function threshold(): ?Duration
    {
        return $this->duration;
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    /**
     * Whether any gate is active: a non-zero min age or a Composer cooldown.
     */
    public function isEnabled(): bool
    {
        return $this->isMinAgeEnabled() || $this->cooldownPolicy instanceof CooldownPolicy;
    }

    public function isEligible(VersionTarget $versionTarget): bool
    {
        return !$this->blockingGate($versionTarget) instanceof AgeGate;
    }

    /**
     * The gate keeping the target from being selected, or null when it is eligible.
     * When both block, the longer one wins (it releases the version later); on a tie
     * the cooldown, since it cannot be lowered from the TUI.
     */
    public function blockingGate(VersionTarget $versionTarget): ?AgeGate
    {
        $releaseDate = $versionTarget->releaseDate;

        if (!$releaseDate instanceof DateTimeImmutable) {
            return null;
        }

        $minAgeSeconds   = $this->isMinAgeEnabled() && $this->duration instanceof Duration ? $this->duration->toSeconds() : null;
        $cooldownSeconds = $versionTarget->isCooldownExempt ? null : $this->cooldownPolicy?->periodSeconds();

        $isMinAgeBlocking   = $minAgeSeconds !== null && $this->isWithin($releaseDate, $minAgeSeconds);
        $isCooldownBlocking = $cooldownSeconds !== null && $this->isWithin($releaseDate, $cooldownSeconds);

        return match (true) {
            $isCooldownBlocking && (!$isMinAgeBlocking || $cooldownSeconds >= $minAgeSeconds) => AgeGate::Cooldown,
            $isMinAgeBlocking                                                                 => AgeGate::MinAge,
            default                                                                           => null,
        };
    }

    /**
     * Newest release date that still counts as old enough; null when no
     * threshold is configured.
     */
    public function cutoff(): ?DateTimeImmutable
    {
        if (!$this->duration instanceof Duration) {
            return null;
        }

        return $this->now->setTimestamp($this->now->getTimestamp() - $this->duration->toSeconds());
    }

    public function withThreshold(?Duration $duration): self
    {
        return new self($duration, $this->now, $this->cooldownPolicy);
    }

    /**
     * 'off' when disabled, otherwise the formatted threshold ('7d').
     */
    public function label(): string
    {
        return $this->isMinAgeEnabled() && $this->duration instanceof Duration
            ? $this->duration->format()
            : 'off';
    }

    /**
     * The Composer cooldown's label ('7d', '1d 12h'), or null when no cooldown applies.
     */
    public function cooldownLabel(): ?string
    {
        return $this->cooldownPolicy?->label();
    }

    private function isMinAgeEnabled(): bool
    {
        return $this->duration instanceof Duration && !$this->duration->isZero();
    }

    /**
     * Whether the release is younger than the given span, i.e. still inside the gate.
     */
    private function isWithin(DateTimeImmutable $releaseDate, int $seconds): bool
    {
        return $releaseDate > $this->now->setTimestamp($this->now->getTimestamp() - $seconds);
    }
}
