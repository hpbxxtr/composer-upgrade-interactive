<?php

declare(strict_types=1);

use Composer\Policy\CooldownPolicyConfig;
use Composer\Policy\IgnorePackageRule;
use Composer\Policy\ListPolicyConfig;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;

/**
 * Build an OutdatedPackage test fixture with sensible defaults.
 * Providing no bump targets results in an abandoned-only entry.
 */
function outdatedPackage(
    string $name = 'vendor/pkg',
    string $current = '1.2.3',
    ?VersionTarget $patch = null,
    ?VersionTarget $minor = null,
    ?VersionTarget $major = null,
    bool $isDev = false,
    string $repoUrl = 'https://github.com/vendor/pkg',
    ?string $abandonedBy = null,
): OutdatedPackage {
    return new OutdatedPackage(
        name: $name,
        current: $current,
        currentRaw: $current,
        patch: $patch,
        minor: $minor,
        major: $major,
        isDev: $isDev,
        repoUrl: $repoUrl,
        abandonedBy: $abandonedBy,
    );
}

/**
 * Build a package with a minor update available (a common fixture).
 */
function outdatedPackageWithMinor(
    string $name = 'vendor/pkg',
    string $current = '1.2.3',
    string $minorTarget = '1.3.0',
    bool $isDev = false,
): OutdatedPackage {
    return \outdatedPackage(
        name: $name,
        current: $current,
        minor: VersionTarget::fromRaw($minorTarget),
        isDev: $isDev,
    );
}

/**
 * Composer's blocking cooldown policy config, as BaseCommand::getBlockingCooldownPolicy() returns it.
 *
 * @param array<mixed> $ignore policy.cooldown.ignore map
 */
function cooldownConfig(?int $period, array $ignore = []): CooldownPolicyConfig
{
    return new CooldownPolicyConfig(true, ListPolicyConfig::AUDIT_IGNORE, IgnorePackageRule::parseIgnoreMap($ignore), $period);
}
