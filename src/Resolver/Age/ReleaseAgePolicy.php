<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver\Age;

use DateTimeImmutable;
use Hpbxxtr\UpgradeInteractive\Core\Duration;

/**
 * Decides whether a candidate release is old enough to be selectable.
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

    public function isEnabled(): bool
    {
        return $this->duration instanceof Duration && !$this->duration->isZero();
    }

    public function isEligible(?DateTimeImmutable $releaseDate): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        if (!$releaseDate instanceof DateTimeImmutable) {
            return true;
        }

        return $releaseDate <= $this->cutoff();
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
        return new self($duration, $this->now);
    }

    /**
     * 'off' when disabled, otherwise the formatted threshold ('7d').
     */
    public function label(): string
    {
        return $this->isEnabled() && $this->duration instanceof Duration
            ? $this->duration->format()
            : 'off';
    }
}
