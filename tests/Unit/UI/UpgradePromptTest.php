<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;
use Hpbxxtr\UpgradeInteractive\UI\UpgradePrompt;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

afterEach(function (): void {
    \Mockery::close();
});

// ---------------------------------------------------------------------------
// value() — pure state inspection, no I/O needed
// ---------------------------------------------------------------------------

\it('value() returns empty array when no selections have been made', function (): void {
    $outdatedPackage    = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');
    $prompt = new UpgradePrompt([$outdatedPackage]);

    \expect($prompt->value())->toBe([]);
});

\it('value() returns the selected package with its raw version', function (): void {
    $outdatedPackage    = \outdatedPackage('vendor/pkg', '1.2.3', minor: VersionTarget::fromRaw('v1.3.0'));
    $prompt = new UpgradePrompt([$outdatedPackage]);

    $prompt->selections['vendor/pkg'] = BumpType::Minor;

    \expect($prompt->value())->toBe(['vendor/pkg' => 'v1.3.0']);
});

\it('value() skips entries whose selection is null', function (): void {
    $outdatedPackage = \outdatedPackageWithMinor('vendor/a', '1.0.0', '1.1.0');
    $pkgB = \outdatedPackageWithMinor('vendor/b', '2.0.0', '2.1.0');

    $prompt                       = new UpgradePrompt([$outdatedPackage, $pkgB]);
    $prompt->selections['vendor/a'] = BumpType::Minor;
    // vendor/b stays null

    \expect($prompt->value())->toBe(['vendor/a' => '1.1.0']);
});

\it('value() handles multiple selections across patch, minor and major', function (): void {
    $outdatedPackage = \outdatedPackage('vendor/a', '1.2.3', patch: VersionTarget::fromRaw('1.2.4'));
    $pkgB = \outdatedPackage('vendor/b', '1.2.3', minor: VersionTarget::fromRaw('1.3.0'));
    $pkgC = \outdatedPackage('vendor/c', '1.2.3', major: VersionTarget::fromRaw('2.0.0'));

    $prompt = new UpgradePrompt([$outdatedPackage, $pkgB, $pkgC]);

    $prompt->selections['vendor/a'] = BumpType::Patch;
    $prompt->selections['vendor/b'] = BumpType::Minor;
    $prompt->selections['vendor/c'] = BumpType::Major;

    \expect($prompt->value())->toBe([
        'vendor/a' => '1.2.4',
        'vendor/b' => '1.3.0',
        'vendor/c' => '2.0.0',
    ]);
});

\it('value() returns empty array when entries list is empty', function (): void {
    $prompt = new UpgradePrompt([]);

    \expect($prompt->value())->toBe([]);
});

// ---------------------------------------------------------------------------
// Initial state
// ---------------------------------------------------------------------------

\it('initialises activeRow and activeCol to 0', function (): void {
    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()]);

    \expect($prompt->activeRow)->toBe(0)
        ->and($prompt->activeCol)->toBe(0)
    ;
});

\it('initialises all selections to null', function (): void {
    $outdatedPackage   = \outdatedPackageWithMinor('vendor/a');
    $pkgB   = \outdatedPackageWithMinor('vendor/b');
    $prompt = new UpgradePrompt([$outdatedPackage, $pkgB]);

    \expect($prompt->selections)->toBe(['vendor/a' => null, 'vendor/b' => null]);
});

// ---------------------------------------------------------------------------
// Key handling via Prompt::fake()
// ---------------------------------------------------------------------------

\it('pressing enter with no selection submits with empty result', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage    = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    \expect($prompt->value())->toBe([]);
});

\it('pressing space selects the focused bump and enter submits', function (): void {
    Prompt::fake([Key::SPACE, "\n"]);

    $outdatedPackage    = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    \expect($prompt->value())->toBe(['vendor/pkg' => '1.3.0']);
});

\it('pressing space twice deselects the bump', function (): void {
    Prompt::fake([Key::SPACE, Key::SPACE, "\n"]);

    $outdatedPackage    = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    \expect($prompt->value())->toBe([]);
});

\it('pressing Ctrl+C clears all selections and submits', function (): void {
    Prompt::fake([Key::SPACE, Key::CTRL_C]);

    $outdatedPackage    = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    \expect($prompt->value())->toBe([]);
});

\it('pressing DOWN moves the active row', function (): void {
    Prompt::fake([Key::DOWN_ARROW, "\n"]);

    $outdatedPackage   = \outdatedPackageWithMinor('vendor/a');
    $pkgB   = \outdatedPackageWithMinor('vendor/b');
    $prompt = new UpgradePrompt([$outdatedPackage, $pkgB]);
    $prompt->prompt();

    \expect($prompt->activeRow)->toBe(1);
});

\it('pressing UP at row 0 stays at row 0', function (): void {
    Prompt::fake([Key::UP_ARROW, "\n"]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()]);
    $prompt->prompt();

    \expect($prompt->activeRow)->toBe(0);
});

\it('pressing DOWN beyond last row stays at last row', function (): void {
    Prompt::fake([Key::DOWN_ARROW, Key::DOWN_ARROW, "\n"]);

    $outdatedPackage   = \outdatedPackageWithMinor('vendor/a');
    $pkgB   = \outdatedPackageWithMinor('vendor/b');
    $prompt = new UpgradePrompt([$outdatedPackage, $pkgB]);
    $prompt->prompt();

    \expect($prompt->activeRow)->toBe(1);
});

\it('pressing RIGHT moves the active column for a package with multiple bumps', function (): void {
    Prompt::fake([Key::RIGHT_ARROW, "\n"]);

    $pkg = new OutdatedPackage(
        name: 'vendor/pkg',
        current: '1.0.0',
        currentRaw: '1.0.0',
        patch: VersionTarget::fromRaw('1.0.1'),
        minor: VersionTarget::fromRaw('1.1.0'),
        major: null,
        isDev: false,
        repoUrl: '',
    );
    $prompt = new UpgradePrompt([$pkg]);
    $prompt->prompt();

    \expect($prompt->activeCol)->toBe(1);
});

\it('pressing RIGHT selects the correct bump type via column navigation', function (): void {
    // patch is col 0, minor is col 1 — RIGHT moves to minor, SPACE selects it
    Prompt::fake([Key::RIGHT_ARROW, Key::SPACE, "\n"]);

    $pkg = new OutdatedPackage(
        name: 'vendor/pkg',
        current: '1.0.0',
        currentRaw: '1.0.0',
        patch: VersionTarget::fromRaw('1.0.1'),
        minor: VersionTarget::fromRaw('1.1.0'),
        major: null,
        isDev: false,
        repoUrl: '',
    );
    $prompt = new UpgradePrompt([$pkg]);
    $prompt->prompt();

    \expect($prompt->value())->toBe(['vendor/pkg' => '1.1.0']);
});

\it('column navigation wraps around', function (): void {
    // A package with patch + minor (2 bumps). Col 0 → RIGHT → col 1 → RIGHT → col 0
    Prompt::fake([Key::RIGHT_ARROW, Key::RIGHT_ARROW, "\n"]);

    $pkg = new OutdatedPackage(
        name: 'vendor/pkg',
        current: '1.0.0',
        currentRaw: '1.0.0',
        patch: VersionTarget::fromRaw('1.0.1'),
        minor: VersionTarget::fromRaw('1.1.0'),
        major: null,
        isDev: false,
        repoUrl: '',
    );
    $prompt = new UpgradePrompt([$pkg]);
    $prompt->prompt();

    \expect($prompt->activeCol)->toBe(0);
});

// ---------------------------------------------------------------------------
// Edge cases — no-bump packages and unrecognised keys
// ---------------------------------------------------------------------------

\it('pressing an unrecognised key is silently ignored', function (): void {
    Prompt::fake(['z', "\n"]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()]);
    $prompt->prompt();

    // No exception; value unchanged
    \expect($prompt->value())->toBe([]);
});

\it('pressing RIGHT on a package with no bumps does not change the column', function (): void {
    // abandoned-only package: all bump targets are null
    Prompt::fake([Key::RIGHT_ARROW, "\n"]);

    $outdatedPackage    = \outdatedPackage(name: 'vendor/dead', current: '1.0.0', abandonedBy: 'vendor/new');
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    \expect($prompt->activeCol)->toBe(0);
});

\it('pressing LEFT on a package with no bumps does not change the column', function (): void {
    Prompt::fake([Key::LEFT_ARROW, "\n"]);

    $outdatedPackage    = \outdatedPackage(name: 'vendor/dead', current: '1.0.0', abandonedBy: 'vendor/new');
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    \expect($prompt->activeCol)->toBe(0);
});

\it('pressing SPACE on a package with no bumps makes no selection', function (): void {
    Prompt::fake([Key::SPACE, "\n"]);

    $outdatedPackage    = \outdatedPackage(name: 'vendor/dead', current: '1.0.0', abandonedBy: 'vendor/new');
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    \expect($prompt->value())->toBe([]);
});

\it('DOWN resets active column to 0', function (): void {
    Prompt::fake([Key::RIGHT_ARROW, Key::DOWN_ARROW, "\n"]);

    $pkg = new OutdatedPackage(
        name: 'vendor/pkg',
        current: '1.0.0',
        currentRaw: '1.0.0',
        patch: VersionTarget::fromRaw('1.0.1'),
        minor: VersionTarget::fromRaw('1.1.0'),
        major: null,
        isDev: false,
        repoUrl: '',
    );
    $outdatedPackage   = \outdatedPackageWithMinor('vendor/b');
    $prompt = new UpgradePrompt([$pkg, $outdatedPackage]);
    $prompt->prompt();

    \expect($prompt->activeCol)->toBe(0);
});
