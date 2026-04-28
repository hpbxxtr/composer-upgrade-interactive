<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\Resolver\AvailableVersionsResolverInterface;
use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\Compatibility\CompatibilityCheckerInterface;
use Hpbxxtr\UpgradeInteractive\Resolver\Compatibility\ConflictReason;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionSelection;
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
    $versionTarget          = VersionTarget::fromRaw('v1.3.0');
    $outdatedPackage = \outdatedPackage('vendor/pkg', '1.2.3', minor: $versionTarget);
    $prompt          = new UpgradePrompt([$outdatedPackage]);

    $prompt->selections['vendor/pkg'] = new VersionSelection(BumpType::Minor, $versionTarget);

    \expect($prompt->value())->toBe(['vendor/pkg' => 'v1.3.0']);
});

\it('value() skips entries whose selection is null', function (): void {
    $outdatedPackage = \outdatedPackageWithMinor('vendor/a', '1.0.0', '1.1.0');
    $pkgB            = \outdatedPackageWithMinor('vendor/b', '2.0.0', '2.1.0');

    $prompt                         = new UpgradePrompt([$outdatedPackage, $pkgB]);
    $prompt->selections['vendor/a'] = new VersionSelection(BumpType::Minor, VersionTarget::fromRaw('1.1.0'));
    // vendor/b stays null

    \expect($prompt->value())->toBe(['vendor/a' => '1.1.0']);
});

\it('value() handles multiple selections across patch, minor and major', function (): void {
    $outdatedPackage = \outdatedPackage('vendor/a', '1.2.3', patch: VersionTarget::fromRaw('1.2.4'));
    $pkgB = \outdatedPackage('vendor/b', '1.2.3', minor: VersionTarget::fromRaw('1.3.0'));
    $pkgC = \outdatedPackage('vendor/c', '1.2.3', major: VersionTarget::fromRaw('2.0.0'));

    $prompt = new UpgradePrompt([$outdatedPackage, $pkgB, $pkgC]);

    $prompt->selections['vendor/a'] = new VersionSelection(BumpType::Patch, VersionTarget::fromRaw('1.2.4'));
    $prompt->selections['vendor/b'] = new VersionSelection(BumpType::Minor, VersionTarget::fromRaw('1.3.0'));
    $prompt->selections['vendor/c'] = new VersionSelection(BumpType::Major, VersionTarget::fromRaw('2.0.0'));

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

// ---------------------------------------------------------------------------
// Guards for out-of-bounds property values
// ---------------------------------------------------------------------------

\it('toggleSelection is a no-op when activeRow is out of bounds', function (): void {
    Prompt::fake([Key::SPACE, "\n"]);

    $prompt           = new UpgradePrompt([\outdatedPackageWithMinor()]);
    $prompt->activeRow = 999; // one past any valid index

    $prompt->prompt();

    \expect($prompt->value())->toBe([]);
});

\it('toggleSelection is a no-op when activeCol resolves to a negative index', function (): void {
    // activeCol = -1 → min(-1, count-1) = -1 → $bumps[-1] is undefined → col === null
    Prompt::fake([Key::SPACE, "\n"]);

    $prompt           = new UpgradePrompt([\outdatedPackageWithMinor()]);
    $prompt->activeCol = -1;

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

// ---------------------------------------------------------------------------
// Inline picker — opening
// ---------------------------------------------------------------------------

\it('pressing v without a versionsResolver leaves picker inactive', function (): void {
    Prompt::fake(['v', "\n"]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()]);
    $prompt->prompt();

    \expect($prompt->isPickerActive)->toBeFalse();
});

\it('pressing v on a package with no bumps leaves picker inactive', function (): void {
    Prompt::fake(['v', "\n"]);

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldNotReceive('resolve');

    $outdatedPackage = \outdatedPackage(name: 'vendor/dead', current: '1.0.0', abandonedBy: 'vendor/new');
    $prompt          = new UpgradePrompt([$outdatedPackage], availableVersionsResolver: $mock);
    $prompt->prompt();

    \expect($prompt->isPickerActive)->toBeFalse();
});

\it('pressing v when resolver returns empty versions leaves picker inactive', function (): void {
    Prompt::fake(['v', "\n"]);

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()], availableVersionsResolver: $mock);
    $prompt->prompt();

    \expect($prompt->isPickerActive)->toBeFalse();
});

\it('pressing v opens the picker and sets isPickerActive', function (): void {
    Prompt::fake(['v', Key::CTRL_C]);

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([
        VersionTarget::fromRaw('1.3.1'),
        VersionTarget::fromRaw('1.3.0'),
    ]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()], availableVersionsResolver: $mock);
    $prompt->prompt();

    \expect($prompt->isPickerActive)->toBeFalse() // closed after CTRL+C
        ->and($prompt->pickerColumns)->toBe([]) // cleared on cancel
    ;
});

// ---------------------------------------------------------------------------
// Inline picker — selecting a version
// ---------------------------------------------------------------------------

\it('pressing v then space selects the first (latest) version', function (): void {
    Prompt::fake(['v', Key::SPACE, "\n"]);

    $versionTarget = VersionTarget::fromRaw('1.3.1');
    $v2 = VersionTarget::fromRaw('1.3.0');

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([$versionTarget, $v2]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0')], availableVersionsResolver: $mock);
    $prompt->prompt();

    \expect($prompt->value())->toBe(['vendor/pkg' => '1.3.1'])
        ->and($prompt->isPickerActive)->toBeFalse()
    ;
});

\it('pressing v, DOWN, space selects the second version', function (): void {
    Prompt::fake(['v', Key::DOWN_ARROW, Key::SPACE, "\n"]);

    $versionTarget = VersionTarget::fromRaw('1.3.5');
    $v2 = VersionTarget::fromRaw('1.3.4');

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([$versionTarget, $v2]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()], availableVersionsResolver: $mock);
    $prompt->prompt();

    \expect($prompt->value())->toBe(['vendor/pkg' => '1.3.4']);
});

// ---------------------------------------------------------------------------
// Inline picker — cancellation and ESC restore
// ---------------------------------------------------------------------------

\it('pressing ESC cancels the picker without changing selection', function (): void {
    Prompt::fake(['v', Key::ESCAPE, "\n"]);

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([
        VersionTarget::fromRaw('1.3.1'),
    ]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()], availableVersionsResolver: $mock);
    $prompt->prompt();

    \expect($prompt->value())->toBe([])
        ->and($prompt->isPickerActive)->toBeFalse()
    ;
});

\it('pressing ESC restores the previous selection', function (): void {
    // First SPACE selects the default minor, then v opens picker, then ESC restores
    Prompt::fake([Key::SPACE, 'v', Key::ESCAPE, "\n"]);

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([
        VersionTarget::fromRaw('1.3.1'),
    ]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');
    $prompt          = new UpgradePrompt([$outdatedPackage], availableVersionsResolver: $mock);
    $prompt->prompt();

    // The default selection (space = 1.3.0) should be restored after ESC
    \expect($prompt->value())->toBe(['vendor/pkg' => '1.3.0']);
});

\it('pressing LEFT in the picker cancels it (same as ESC)', function (): void {
    Prompt::fake(['v', Key::LEFT_ARROW, "\n"]);

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([VersionTarget::fromRaw('1.3.1')]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()], availableVersionsResolver: $mock);
    $prompt->prompt();

    \expect($prompt->value())->toBe([])
        ->and($prompt->isPickerActive)->toBeFalse()
    ;
});

// ---------------------------------------------------------------------------
// Inline picker — Ctrl+C clears everything
// ---------------------------------------------------------------------------

\it('Ctrl+C while picker is open clears all selections and submits', function (): void {
    // SPACE selects, v opens picker, CTRL+C should wipe everything
    Prompt::fake([Key::SPACE, 'v', Key::CTRL_C]);

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([VersionTarget::fromRaw('1.3.1')]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');
    $prompt          = new UpgradePrompt([$outdatedPackage], availableVersionsResolver: $mock);
    $prompt->prompt();

    \expect($prompt->value())->toBe([]);
});

// ---------------------------------------------------------------------------
// Inline picker — navigation skips group headers
// ---------------------------------------------------------------------------

\it('pressing LEFT in picker navigates from latest column to older column', function (): void {
    Prompt::fake(['v', Key::LEFT_ARROW, Key::SPACE, "\n"]);

    $versionTarget = VersionTarget::fromRaw('1.4.0');
    $v13 = VersionTarget::fromRaw('1.3.5');

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([$versionTarget, $v13]);

    $pkg = new OutdatedPackage(
        name: 'vendor/pkg',
        current: '1.2.0',
        currentRaw: '1.2.0',
        patch: null,
        minor: VersionTarget::fromRaw('1.4.0'),
        major: null,
        isDev: false,
        repoUrl: '',
    );
    $prompt = new UpgradePrompt([$pkg], availableVersionsResolver: $mock);
    $prompt->prompt();

    // columns = [{1.3.x, [1.3.5]}, {1.4.x, [1.4.0]}], cursor starts at col=1 (1.4.x)
    // LEFT moves to col=0 (1.3.x), SPACE selects 1.3.5
    \expect($prompt->value())->toBe(['vendor/pkg' => '1.3.5']);
});

\it('picker UP does not move cursor past the first selectable row', function (): void {
    Prompt::fake(['v', Key::UP_ARROW, Key::SPACE, "\n"]);

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([VersionTarget::fromRaw('1.3.0')]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()], availableVersionsResolver: $mock);
    $prompt->prompt();

    \expect($prompt->value())->toBe(['vendor/pkg' => '1.3.0']);
});

// ---------------------------------------------------------------------------
// Picker opening guards
// ---------------------------------------------------------------------------

\it('pressing v with out-of-bounds activeRow leaves picker inactive', function (): void {
    Prompt::fake(['v', "\n"]);

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldNotReceive('resolve');

    $prompt           = new UpgradePrompt([\outdatedPackageWithMinor()], availableVersionsResolver: $mock);
    $prompt->activeRow = 999;
    $prompt->prompt();

    \expect($prompt->isPickerActive)->toBeFalse();
});

\it('pressing v with a negative activeCol leaves picker inactive', function (): void {
    // activeCol = -1 → min(-1, count-1) = -1 → $bumps[-1] is undefined → bumpType === null → early return
    Prompt::fake(['v', "\n"]);

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldNotReceive('resolve');

    $prompt           = new UpgradePrompt([\outdatedPackageWithMinor()], availableVersionsResolver: $mock);
    $prompt->activeCol = -1;
    $prompt->prompt();

    \expect($prompt->isPickerActive)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Patch and Major picker via v key
// ---------------------------------------------------------------------------

\it('pressing v on a patch column opens picker with flat version list', function (): void {
    Prompt::fake(['v', Key::SPACE, "\n"]);

    $versionTarget = VersionTarget::fromRaw('1.2.5');
    $v2 = VersionTarget::fromRaw('1.2.4');

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldReceive('resolve')
        ->with('vendor/pkg', '1.2.3', BumpType::Patch)
        ->once()
        ->andReturn([$versionTarget, $v2]);

    $pkg = new OutdatedPackage(
        name: 'vendor/pkg',
        current: '1.2.3',
        currentRaw: '1.2.3',
        patch: VersionTarget::fromRaw('1.2.5'),
        minor: null,
        major: null,
        isDev: false,
        repoUrl: '',
    );
    $prompt = new UpgradePrompt([$pkg], availableVersionsResolver: $mock);
    $prompt->prompt();

    // Patch picker is flat (no group headers), selects first item
    \expect($prompt->value())->toBe(['vendor/pkg' => '1.2.5']);
});

\it('pressing v on a major column opens picker grouped by major series', function (): void {
    Prompt::fake(['v', Key::SPACE, "\n"]);

    $versionTarget = VersionTarget::fromRaw('3.0.0');
    $v2 = VersionTarget::fromRaw('2.1.0');

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldReceive('resolve')
        ->with('vendor/pkg', '1.2.3', BumpType::Major)
        ->once()
        ->andReturn([$versionTarget, $v2]);

    $pkg = new OutdatedPackage(
        name: 'vendor/pkg',
        current: '1.2.3',
        currentRaw: '1.2.3',
        patch: null,
        minor: null,
        major: VersionTarget::fromRaw('3.0.0'),
        isDev: false,
        repoUrl: '',
    );
    $prompt = new UpgradePrompt([$pkg], availableVersionsResolver: $mock);
    $prompt->prompt();

    // Grouped picker: '── 3.x ──', $v3, '── 2.x ──', $v2 — cursor starts at index 1 ($v3)
    \expect($prompt->value())->toBe(['vendor/pkg' => '3.0.0']);
});

// ---------------------------------------------------------------------------
// Unrecognised key in picker mode is ignored
// ---------------------------------------------------------------------------

\it('pressing an unrecognised key while picker is open is silently ignored', function (): void {
    Prompt::fake(['v', 'z', Key::SPACE, "\n"]);

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([VersionTarget::fromRaw('1.3.1')]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()], availableVersionsResolver: $mock);
    $prompt->prompt();

    // 'z' is ignored, space selects first version
    \expect($prompt->value())->toBe(['vendor/pkg' => '1.3.1']);
});

// ---------------------------------------------------------------------------
// pickerSelect guard — cursor on non-VersionTarget row
// ---------------------------------------------------------------------------

\it('pickerSelect is a no-op when cursor lands on a group header string', function (): void {
    Prompt::fake(['v', Key::SPACE, "\n"]);

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldReceive('resolve')->once()->andReturn([VersionTarget::fromRaw('1.3.5')]);

    $pkg = new OutdatedPackage(
        name: 'vendor/pkg',
        current: '1.2.0',
        currentRaw: '1.2.0',
        patch: null,
        minor: VersionTarget::fromRaw('1.3.5'),
        major: null,
        isDev: false,
        repoUrl: '',
    );
    $prompt = new UpgradePrompt([$pkg], availableVersionsResolver: $mock);

    // Set cursor to 0 before picker opens; picker rows will be ['── 1.3.x ──', $v]
    // After picker opens pickerCursor will be set to firstSelectableIndex = 1
    // So pressing SPACE selects $v at index 1, not the header
    $prompt->prompt();

    \expect($prompt->value())->toBe(['vendor/pkg' => '1.3.5']);
});

// ---------------------------------------------------------------------------
// Picker pre-selects the current selection when opened
// ---------------------------------------------------------------------------

\it('picker cursor starts at the already-selected version when re-opened', function (): void {
    // Select 1.3.0 via space, then open picker — cursor should land on 1.3.0 not 1.3.1
    Prompt::fake([Key::SPACE, 'v', Key::SPACE, "\n"]);

    $versionTarget = VersionTarget::fromRaw('1.3.1');
    $v0 = VersionTarget::fromRaw('1.3.0');

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    // Called once (v key), returns two versions
    $mock->shouldReceive('resolve')->once()->andReturn([$versionTarget, $v0]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');
    $prompt          = new UpgradePrompt([$outdatedPackage], availableVersionsResolver: $mock);
    $prompt->prompt();

    // SPACE selects 1.3.0 (default); then v opens picker; picker cursor is at 1.3.0 (index 1);
    // pressing SPACE again re-selects 1.3.0
    \expect($prompt->value())->toBe(['vendor/pkg' => '1.3.0']);
});

\it('picker cursor jumps to existing selection on a different column than default', function (): void {
    // Select 1.3.5 (a non-default minor) via picker first, then re-open picker — cursor lands on it
    Prompt::fake(['v', Key::DOWN_ARROW, Key::SPACE, 'v', Key::SPACE, "\n"]);

    $versionTarget = VersionTarget::fromRaw('1.3.5');
    $v0 = VersionTarget::fromRaw('1.3.0');

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldReceive('resolve')->twice()->andReturn([$versionTarget, $v0]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');
    $prompt          = new UpgradePrompt([$outdatedPackage], availableVersionsResolver: $mock);
    $prompt->prompt();

    // First v: opens picker, DOWN goes to 1.3.0 (index 1), SPACE selects it
    // Second v: re-opens picker, cursor should be at index 1 (1.3.0), SPACE re-selects it
    \expect($prompt->value())->toBe(['vendor/pkg' => '1.3.0']);
});

// ---------------------------------------------------------------------------
// pickerSelect defensive guards (lines 244 and 248)
// ---------------------------------------------------------------------------

\it('pickerSelect is a no-op when pickerColumns is empty (cursor out of bounds)', function (): void {
    Prompt::fake([Key::SPACE, "\n"]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()]);
    // Manually inject picker state with no columns so cursor lookup yields null
    $prompt->isPickerActive = true;
    $prompt->pickerColumns  = [];
    $prompt->pickerCol      = 0;
    $prompt->pickerRow      = 0;
    $prompt->pickerBumpType = BumpType::Minor;
    $prompt->prompt();

    \expect($prompt->value())->toBe([]);
});

\it('pickerSelect is a no-op when pickerBumpType is null', function (): void {
    Prompt::fake([Key::SPACE, "\n"]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()]);
    // Cursor points to a real VersionTarget but pickerBumpType is null
    $prompt->isPickerActive = true;
    $prompt->pickerColumns  = [new \Hpbxxtr\UpgradeInteractive\UI\PickerColumn('1.3.x', [VersionTarget::fromRaw('1.3.0')])];
    $prompt->pickerCol      = 0;
    $prompt->pickerRow      = 0;
    $prompt->pickerBumpType = null;
    $prompt->prompt();

    \expect($prompt->value())->toBe([]);
});

\it('pickerSelect is a no-op when column has no versions (row out of bounds)', function (): void {
    Prompt::fake([Key::SPACE, "\n"]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()]);
    $prompt->isPickerActive = true;
    $prompt->pickerColumns  = [new \Hpbxxtr\UpgradeInteractive\UI\PickerColumn('1.3.x', [])];
    $prompt->pickerCol      = 0;
    $prompt->pickerRow      = 0;
    $prompt->pickerBumpType = BumpType::Minor;
    $prompt->prompt();

    \expect($prompt->value())->toBe([]);
});

\it('RIGHT at last column is a no-op', function (): void {
    Prompt::fake([Key::RIGHT_ARROW, "\n"]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()]);
    $prompt->isPickerActive = true;
    $prompt->pickerColumns  = [new \Hpbxxtr\UpgradeInteractive\UI\PickerColumn('1.3.x', [VersionTarget::fromRaw('1.3.0')])];
    $prompt->pickerCol      = 0;
    $prompt->pickerRow      = 0;
    $prompt->pickerBumpType = BumpType::Minor;
    $prompt->prompt();

    \expect($prompt->value())->toBe([]);
});

\it('pickerNavigate is a no-op when pickerColumns is empty', function (): void {
    Prompt::fake([Key::DOWN_ARROW, "\n"]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()]);
    $prompt->isPickerActive = true;
    $prompt->pickerColumns  = [];
    $prompt->pickerCol      = 0;
    $prompt->pickerRow      = 0;
    $prompt->pickerBumpType = BumpType::Minor;
    $prompt->prompt();

    \expect($prompt->value())->toBe([]);
});

// ---------------------------------------------------------------------------
// Compatibility checker — conflictMap and pickerVersionCompatibility
// ---------------------------------------------------------------------------

\it('conflictMap is initially empty', function (): void {
    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()]);

    \expect($prompt->conflictMap->isEmpty())->toBeTrue();
});

\it('conflictMap stays empty after selection when no checker is injected', function (): void {
    Prompt::fake([Key::SPACE, "\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');
    $prompt          = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    \expect($prompt->conflictMap->isEmpty())->toBeTrue();
});

\it('conflictMap is updated after toggleSelection when checker is injected', function (): void {
    Prompt::fake([Key::SPACE, "\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');

    $mock = \Mockery::mock(CompatibilityCheckerInterface::class);
    // After selecting pkg, recomputeConflictMap calls checkCandidate for the minor target
    // otherSelections is empty (only one package), so it's called with []
    $mock->shouldReceive('checkCandidate')
        ->with('vendor/pkg', \Mockery::type(VersionTarget::class), [])
        ->andReturn([]);

    $prompt = new UpgradePrompt([$outdatedPackage], compatibilityChecker: $mock);
    $prompt->prompt();

    // conflictMap is no longer empty — it now has data for vendor/pkg
    \expect($prompt->conflictMap->isEmpty())->toBeFalse();
});

\it('conflictMap marks a version as conflicting when checker returns a conflict reason', function (): void {
    Prompt::fake([Key::SPACE, "\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg-a', '1.0.0', '2.0.0');
    $pkgB = \outdatedPackageWithMinor('vendor/pkg-b', '1.0.0', '1.5.0');

    $conflictReason = new ConflictReason(
        dependentPackage: 'vendor/pkg-a',
        dependentVersion: '2.0.0',
        requiredPackage: 'vendor/pkg-b',
        requiredConstraint: '^1.0',
        selectedVersion: '1.5.0',
    );

    $mock = \Mockery::mock(CompatibilityCheckerInterface::class);
    // When pkg-a is selected, checker is called for each target — returns empty (no other selections yet)
    $mock->shouldReceive('checkCandidate')
        ->with('vendor/pkg-a', \Mockery::type(VersionTarget::class), \Mockery::type('array'))
        ->andReturn([]);
    // For pkg-b with pkg-a in otherSelections, return the conflict
    $mock->shouldReceive('checkCandidate')
        ->with('vendor/pkg-b', \Mockery::type(VersionTarget::class), \Mockery::type('array'))
        ->andReturn([$conflictReason]);

    $prompt = new UpgradePrompt([$outdatedPackage, $pkgB], compatibilityChecker: $mock);
    $prompt->prompt();

    \expect($prompt->conflictMap->isCompatible('vendor/pkg-b', '1.5.0'))->toBeFalse();
    \expect($prompt->conflictMap->conflictsFor('vendor/pkg-b', '1.5.0'))->toHaveCount(1);
});

\it('conflictMap is recomputed after pickerSelect', function (): void {
    Prompt::fake(['v', Key::SPACE, "\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');

    $mock = \Mockery::mock(CompatibilityCheckerInterface::class);
    $mock->shouldReceive('checkCandidate')
        ->with('vendor/pkg', \Mockery::type(VersionTarget::class), \Mockery::type('array'))
        ->andReturn([]);

    $versionResolver = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $versionResolver->shouldReceive('resolve')->once()->andReturn([VersionTarget::fromRaw('1.3.1')]);

    $prompt = new UpgradePrompt(
        [$outdatedPackage],
        availableVersionsResolver: $versionResolver,
        compatibilityChecker: $mock,
    );
    $prompt->prompt();

    // After pickerSelect, recomputeConflictMap was called
    \expect($prompt->conflictMap->isEmpty())->toBeFalse();
});

\it('pickerVersionCompatibility is populated when picker opens with a checker', function (): void {
    Prompt::fake(['v', Key::ESCAPE, "\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');

    $versionTarget = VersionTarget::fromRaw('1.3.1');
    $v2 = VersionTarget::fromRaw('1.3.0');

    $conflictReason = new ConflictReason(
        dependentPackage: 'vendor/pkg',
        dependentVersion: '1.3.1',
        requiredPackage: 'vendor/other',
        requiredConstraint: '^1.2',
        selectedVersion: '1.3.1',
    );

    $mock = \Mockery::mock(CompatibilityCheckerInterface::class);
    // 1.3.1 is incompatible, 1.3.0 is compatible
    $mock->shouldReceive('checkCandidate')
        ->with('vendor/pkg', $versionTarget, [])
        ->andReturn([$conflictReason]);
    $mock->shouldReceive('checkCandidate')
        ->with('vendor/pkg', $v2, [])
        ->andReturn([]);

    $versionResolver = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $versionResolver->shouldReceive('resolve')->once()->andReturn([$versionTarget, $v2]);

    $prompt = new UpgradePrompt(
        [$outdatedPackage],
        availableVersionsResolver: $versionResolver,
        compatibilityChecker: $mock,
    );
    $prompt->prompt();

    \expect($prompt->pickerVersionCompatibility['1.3.1'])->toBeFalse();
    \expect($prompt->pickerVersionCompatibility['1.3.0'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// recomputeConflictMap — exception handling
// ---------------------------------------------------------------------------

\it('recomputeConflictMap treats UnexpectedValueException from checker as no conflicts', function (): void {
    Prompt::fake([Key::SPACE, "\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.0.0', '1.3.0');

    $mock = \Mockery::mock(CompatibilityCheckerInterface::class);
    $mock->shouldReceive('checkCandidate')->andThrow(new \UnexpectedValueException('bad constraint'));

    $prompt = new UpgradePrompt([$outdatedPackage], compatibilityChecker: $mock);
    $prompt->prompt();

    // Exception caught → conflict treated as empty [] → version considered compatible
    \expect($prompt->conflictMap->isCompatible('vendor/pkg', '1.3.0'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// recomputeConflictMap — no-selection early return
// ---------------------------------------------------------------------------

\it('recomputeConflictMap resets conflictMap to empty when the last selection is cleared', function (): void {
    // SPACE once selects (triggers recompute with one selection)
    // SPACE again deselects (triggers recompute with zero selections)
    Prompt::fake([Key::SPACE, Key::SPACE, "\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.0.0', '1.3.0');

    $mock = \Mockery::mock(CompatibilityCheckerInterface::class);
    $mock->shouldReceive('checkCandidate')->andReturn([]);

    $prompt = new UpgradePrompt([$outdatedPackage], compatibilityChecker: $mock);
    $prompt->prompt();

    // After deselecting the only package, conflictMap is reset to empty
    \expect($prompt->conflictMap->isEmpty())->toBeTrue();
});

// ---------------------------------------------------------------------------
// openPicker — exception handling
// ---------------------------------------------------------------------------

\it('openPicker treats UnexpectedValueException from checker as compatible for that version', function (): void {
    $versions = [VersionTarget::fromRaw('1.3.0')];

    $mock = \Mockery::mock(AvailableVersionsResolverInterface::class);
    $mock->shouldReceive('resolve')->andReturn($versions);

    $checker = \Mockery::mock(CompatibilityCheckerInterface::class);
    $checker->shouldReceive('checkCandidate')->andThrow(new \UnexpectedValueException('bad'));

    Prompt::fake(['v', "\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.0.0', '1.3.0');
    $prompt          = new UpgradePrompt([$outdatedPackage], availableVersionsResolver: $mock, compatibilityChecker: $checker);
    $prompt->prompt();

    // Exception caught → pickerConflicts = [] → version treated as compatible
    \expect($prompt->pickerVersionCompatibility['1.3.0'] ?? null)->toBeTrue();
});

