<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\Resolver\Age\AgeThresholdResolver;

\it('returns null without option and without extra config', function (): void {
    \expect(AgeThresholdResolver::resolve(null))->toBeNull();
});

\it('parses the CLI option', function (): void {
    \expect(AgeThresholdResolver::resolve('2w')?->format())->toBe('2w');
});

\it('falls back to the extra section', function (): void {
    $extra = ['hpbxxtr-upgrade-interactive' => ['minimum-release-age' => '10d']];

    \expect(AgeThresholdResolver::resolve(null, $extra)?->format())->toBe('10d');
});

\it('accepts an integer number of days in the extra section', function (): void {
    $extra = ['hpbxxtr-upgrade-interactive' => ['minimum-release-age' => 21]];

    \expect(AgeThresholdResolver::resolve(null, $extra)?->format())->toBe('21d');
});

\it('lets the CLI option win over the extra section', function (): void {
    $extra = ['hpbxxtr-upgrade-interactive' => ['minimum-release-age' => '30d']];

    \expect(AgeThresholdResolver::resolve('3d', $extra)?->format())->toBe('3d');
});

\it('ignores unrelated or malformed extra sections', function (array $extra): void {
    \expect(AgeThresholdResolver::resolve(null, $extra))->toBeNull();
})->with([
    [['other-plugin' => ['minimum-release-age' => '5d']]],
    [['hpbxxtr-upgrade-interactive' => 'not-an-array']],
    [['hpbxxtr-upgrade-interactive' => []]],
]);

\it('rejects a non-scalar extra value', function (): void {
    AgeThresholdResolver::resolve(null, ['hpbxxtr-upgrade-interactive' => ['minimum-release-age' => ['7d']]]);
})->throws(InvalidArgumentException::class);

\it('propagates parse errors from the extra section', function (): void {
    AgeThresholdResolver::resolve(null, ['hpbxxtr-upgrade-interactive' => ['minimum-release-age' => 'soon']]);
})->throws(InvalidArgumentException::class);
