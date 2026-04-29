<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver\Compatibility;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final readonly class ConflictReason
{
    public function __construct(
        public string $dependentPackage,   // the package whose requires clause is violated
        public string $dependentVersion,
        public string $requiredPackage,    // the package it requires
        public string $requiredConstraint, // e.g. "^6.4"
        public string $selectedVersion,    // what is actually selected or installed — conflicts with constraint
        public bool $isInstalled = false,  // true when the version comes from the installed repo, not a user selection
    ) {}

    public function format(): string
    {
        return $this->dependentPackage . ' ' . $this->dependentVersion
            . ' requires ' . $this->requiredPackage . ' ' . $this->requiredConstraint;
    }
}
