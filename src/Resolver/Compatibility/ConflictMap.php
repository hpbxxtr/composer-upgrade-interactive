<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver\Compatibility;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final readonly class ConflictMap
{
    /**
     * @param array<string, array<string, list<ConflictReason>>> $data  name → versionRaw → reasons
     */
    public function __construct(private array $data = []) {}

    public static function empty(): self
    {
        return new self();
    }

    public function isEmpty(): bool
    {
        return $this->data === [];
    }

    /**
     * True when (packageName, versionRaw) has no recorded conflicts.
     * Unknown entries default to compatible (safe).
     */
    public function isCompatible(string $packageName, string $versionRaw): bool
    {
        return ($this->data[$packageName][$versionRaw] ?? []) === [];
    }

    /**
     * @return list<ConflictReason>
     */
    public function conflictsFor(string $packageName, string $versionRaw): array
    {
        return $this->data[$packageName][$versionRaw] ?? [];
    }

    /**
     * Returns a new ConflictMap with additional (package, version) entries merged in.
     * Existing entries for the same pair are overwritten by the incoming data.
     *
     * @param array<string, array<string, list<ConflictReason>>> $extra
     */
    public function withMerged(array $extra): self
    {
        $merged = $this->data;

        foreach ($extra as $name => $versions) {
            foreach ($versions as $versionRaw => $reasons) {
                $merged[$name][$versionRaw] = $reasons;
            }
        }

        return new self($merged);
    }
}
