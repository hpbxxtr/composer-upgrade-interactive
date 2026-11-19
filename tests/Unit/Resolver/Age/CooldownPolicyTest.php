<?php

declare(strict_types=1);

use Composer\Config;
use Composer\Package\CompletePackage;
use Composer\Policy\PolicyConfig;
use Hpbxxtr\UpgradeInteractive\Resolver\Age\CooldownPolicy;

\it('returns null without a config', function (): void {
    \expect(CooldownPolicy::fromComposerConfig(null))->toBeNull();
});

\it('returns null for a null or zero period', function (): void {
    \expect(CooldownPolicy::fromComposerConfig(\cooldownConfig(null)))->toBeNull()
        ->and(CooldownPolicy::fromComposerConfig(\cooldownConfig(0)))->toBeNull()
    ;
});

\it('is inactive when composer.json sets no cooldown policy', function (): void {
    $policyConfig = PolicyConfig::fromConfig(new Config(false));

    \expect(CooldownPolicy::fromComposerConfig($policyConfig->cooldown))->toBeNull();
});

\it('exposes the period in seconds', function (): void {
    \expect(CooldownPolicy::fromComposerConfig(\cooldownConfig(604800))?->periodSeconds())->toBe(604800);
});

\it('formats the label', function (int $seconds, string $label): void {
    \expect(CooldownPolicy::fromComposerConfig(\cooldownConfig($seconds))?->label())->toBe($label);
})->with([
    'one week'           => [604800, '7d'],
    'a day and a half'   => [129600, '1d 12h'],
    'one day'            => [86400, '1d'],
    'ninety minutes'     => [5400, '1h 30min'],
    'thirty minutes'     => [1800, '30min'],
    'seconds'            => [45, '45s'],
    'only two units'     => [90061, '1d 1h'],
]);

\it('exempts dev versions', function (): void {
    $policy = CooldownPolicy::fromComposerConfig(\cooldownConfig(86400));

    \expect($policy?->isExempt(new CompletePackage('vendor/pkg', 'dev-main', 'dev-main')))->toBeTrue();
});

\it('does not exempt a stable version without an ignore rule', function (): void {
    $policy = CooldownPolicy::fromComposerConfig(\cooldownConfig(86400));

    \expect($policy?->isExempt(new CompletePackage('vendor/pkg', '1.2.3.0', '1.2.3')))->toBeFalse();
});

\it('exempts a package matching an ignore rule', function (): void {
    $policy = CooldownPolicy::fromComposerConfig(\cooldownConfig(86400, ['vendor/pkg' => 'trusted']));

    \expect($policy?->isExempt(new CompletePackage('vendor/pkg', '1.2.3.0', '1.2.3')))->toBeTrue()
        ->and($policy?->isExempt(new CompletePackage('vendor/other', '1.2.3.0', '1.2.3')))->toBeFalse()
    ;
});

\it('exempts packages matching a wildcard ignore rule', function (): void {
    $policy = CooldownPolicy::fromComposerConfig(\cooldownConfig(86400, ['vendor/*' => null]));

    \expect($policy?->isExempt(new CompletePackage('vendor/other', '1.2.3.0', '1.2.3')))->toBeTrue();
});

\it('honours the version constraint of an ignore rule', function (): void {
    $policy = CooldownPolicy::fromComposerConfig(\cooldownConfig(86400, ['vendor/pkg' => ['constraint' => '^1.0']]));

    \expect($policy?->isExempt(new CompletePackage('vendor/pkg', '1.5.0.0', '1.5.0')))->toBeTrue()
        ->and($policy?->isExempt(new CompletePackage('vendor/pkg', '2.0.0.0', '2.0.0')))->toBeFalse()
    ;
});
