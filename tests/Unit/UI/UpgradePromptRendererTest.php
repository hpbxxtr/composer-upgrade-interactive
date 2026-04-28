<?php

declare(strict_types=1);
use Hpbxxtr\UpgradeInteractive\Resolver\Compatibility\ConflictMap;
use Hpbxxtr\UpgradeInteractive\Resolver\Compatibility\ConflictReason;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;
use Hpbxxtr\UpgradeInteractive\UI\UpgradePrompt;
use Hpbxxtr\UpgradeInteractive\UI\UpgradePromptRenderer;
use Laravel\Prompts\Prompt;

afterEach(function (): void {
    \Mockery::close();
});

function stripAnsi(string $s): string
{
    return (string) preg_replace('/\e\[[0-9;]*m/', '', $s);
}

function renderPrompt(UpgradePrompt $upgradePrompt): string
{
    $renderer = new UpgradePromptRenderer($upgradePrompt);

    return $renderer($upgradePrompt);
}

// ---------------------------------------------------------------------------
// Submit state
// ---------------------------------------------------------------------------

\it('renders "Done" checkmark in submit state', function (): void {
    Prompt::fake(["\n"]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()]);
    $prompt->prompt();

    $output = \renderPrompt($prompt);

    \expect(\stripAnsi($output))->toContain('Done');
});

// ---------------------------------------------------------------------------
// Normal rendering
// ---------------------------------------------------------------------------

\it('renders the package name', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage    = \outdatedPackageWithMinor('vendor/mypkg', '1.2.3', '1.3.0');
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    // After submit the renderer only shows "Done". Test pre-submit state.
    $prompt->state  = 'initial';
    $prompt->activeRow = 0;
    $output = \renderPrompt($prompt);

    \expect(\stripAnsi($output))->toContain('vendor/mypkg');
});

\it('renders the current version', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage    = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state = 'initial';
    $output = \renderPrompt($prompt);

    \expect(\stripAnsi($output))->toContain('1.2.3');
});

\it('renders the target version', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage    = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state = 'initial';
    $output = \renderPrompt($prompt);

    \expect(\stripAnsi($output))->toContain('1.3.0');
});

\it('renders bump type column headers', function (): void {
    Prompt::fake(["\n"]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()]);
    $prompt->prompt();

    $prompt->state = 'initial';
    $output = \renderPrompt($prompt);
    $stripped = \stripAnsi($output);

    \expect($stripped)->toContain('patch')
        ->and($stripped)->toContain('minor')
        ->and($stripped)->toContain('major')
    ;
});

\it('renders the default label', function (): void {
    Prompt::fake(["\n"]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()]);
    $prompt->prompt();

    $prompt->state = 'initial';
    $output = \renderPrompt($prompt);

    \expect(\stripAnsi($output))->toContain('Select versions to update');
});

\it('renders a custom label', function (): void {
    Prompt::fake(["\n"]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()], label: 'Choose updates');
    $prompt->prompt();

    $prompt->state = 'initial';
    $output = \renderPrompt($prompt);

    \expect(\stripAnsi($output))->toContain('Choose updates');
});

// ---------------------------------------------------------------------------
// Abandonment notice
// ---------------------------------------------------------------------------

\it('includes the abandonment warning for abandoned packages', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackage(
        name: 'vendor/dead',
        current: '1.0.0',
        minor: VersionTarget::fromRaw('1.1.0'),
        abandonedBy: 'vendor/replacement',
    );
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state = 'initial';
    $output = \renderPrompt($prompt);

    \expect(\stripAnsi($output))->toContain('abandoned');
});

\it('includes the replacement package name in the notice', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackage(
        name: 'vendor/dead',
        current: '1.0.0',
        minor: VersionTarget::fromRaw('1.1.0'),
        abandonedBy: 'vendor/replacement',
    );
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state = 'initial';
    $output = \renderPrompt($prompt);

    \expect(\stripAnsi($output))->toContain('vendor/replacement');
});

\it('renders abandonment notice without a replacement when abandonedBy is empty string', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackage(name: 'vendor/dead', current: '1.0.0', minor: VersionTarget::fromRaw('1.1.0'), abandonedBy: '');
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state = 'initial';
    $output = \renderPrompt($prompt);

    \expect(\stripAnsi($output))->toContain('abandoned');
});

// ---------------------------------------------------------------------------
// Section labels (require / require-dev)
// ---------------------------------------------------------------------------

\it('shows require section label for prod packages', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage   = \outdatedPackageWithMinor('vendor/prod', isDev: false);
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state = 'initial';
    $output = \renderPrompt($prompt);

    \expect(\stripAnsi($output))->toContain('require');
});

\it('shows require-dev section label when dev packages are present', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/prod', isDev: false);
    $dev  = \outdatedPackageWithMinor('vendor/dev', isDev: true);

    $prompt = new UpgradePrompt([$outdatedPackage, $dev]);
    $prompt->prompt();

    $prompt->state = 'initial';
    $output = \renderPrompt($prompt);

    \expect(\stripAnsi($output))->toContain('require-dev');
});

// ---------------------------------------------------------------------------
// Footer URLs (compare / release links)
// ---------------------------------------------------------------------------

\it('shows compare URL in footer for the focused bump', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackage(
        name: 'vendor/pkg',
        current: '1.0.0',
        minor: VersionTarget::fromRaw('1.1.0'),
        repoUrl: 'https://github.com/vendor/pkg',
    );
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state = 'initial';
    $output = \renderPrompt($prompt);

    \expect(\stripAnsi($output))->toContain('compare');
});

// ---------------------------------------------------------------------------
// Early return when activeRow is out of bounds (activeEntry === null)
// ---------------------------------------------------------------------------

\it('returns empty output when activeRow points beyond the entries list', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage    = \outdatedPackageWithMinor();
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    // Force activeRow past the end, then render in non-submit state
    $prompt->state     = 'initial';
    $prompt->activeRow = 999;

    $output = \renderPrompt($prompt);

    // Renderer returns early — output contains no package info
    \expect(\stripAnsi($output))->not->toContain('vendor/pkg');
});

// ---------------------------------------------------------------------------
// Output is a string
// ---------------------------------------------------------------------------

\it('__invoke returns a string', function (): void {
    Prompt::fake(["\n"]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()]);
    $prompt->prompt();

    \expect(\renderPrompt($prompt))->toBeString();
});

// ---------------------------------------------------------------------------
// Inline picker rendering
// ---------------------------------------------------------------------------

\it('renders picker header row (bump label + triangle) when picker is active', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');
    $prompt          = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state          = 'initial';
    $prompt->isPickerActive = true;
    $prompt->pickerBumpType = \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor;
    $prompt->pickerColumns  = [new \Hpbxxtr\UpgradeInteractive\UI\PickerColumn('1.3.x', [VersionTarget::fromRaw('1.3.1'), VersionTarget::fromRaw('1.3.0')])];
    $prompt->pickerCol      = 0;
    $prompt->pickerRow      = 0;

    $output   = \renderPrompt($prompt);
    $stripped = \stripAnsi($output);

    \expect($stripped)->toContain('minor ▾');
});

\it('renders version list rows when picker is active', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');
    $prompt          = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state          = 'initial';
    $prompt->isPickerActive = true;
    $prompt->pickerBumpType = \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor;
    $prompt->pickerColumns  = [new \Hpbxxtr\UpgradeInteractive\UI\PickerColumn('1.3.x', [VersionTarget::fromRaw('1.3.1'), VersionTarget::fromRaw('1.3.0')])];
    $prompt->pickerCol      = 0;
    $prompt->pickerRow      = 0;

    $output   = \renderPrompt($prompt);
    $stripped = \stripAnsi($output);

    \expect($stripped)->toContain('1.3.1')
        ->and($stripped)->toContain('1.3.0')
        ->and($stripped)->toContain('(latest)')
    ;
});

\it('renders column headers when picker is active', function (): void {
    Prompt::fake(["\n"]);

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
    $prompt = new UpgradePrompt([$pkg]);
    $prompt->prompt();

    $prompt->state          = 'initial';
    $prompt->isPickerActive = true;
    $prompt->pickerBumpType = \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor;
    $prompt->pickerColumns  = [
        new \Hpbxxtr\UpgradeInteractive\UI\PickerColumn('1.3.x', [VersionTarget::fromRaw('1.3.5')]),
        new \Hpbxxtr\UpgradeInteractive\UI\PickerColumn('1.4.x', [VersionTarget::fromRaw('1.4.0')]),
    ];
    $prompt->pickerCol      = 1;
    $prompt->pickerRow      = 0;

    $output   = \renderPrompt($prompt);
    $stripped = \stripAnsi($output);

    \expect($stripped)->toContain('1.4.x')
        ->and($stripped)->toContain('1.3.x')
    ;
});

\it('renders picker help line instead of normal help when picker is active', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackageWithMinor();
    $prompt          = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state          = 'initial';
    $prompt->isPickerActive = true;
    $prompt->pickerBumpType = \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor;
    $prompt->pickerColumns  = [new \Hpbxxtr\UpgradeInteractive\UI\PickerColumn('1.3.x', [VersionTarget::fromRaw('1.3.0')])];
    $prompt->pickerCol      = 0;
    $prompt->pickerRow      = 0;

    $output   = \renderPrompt($prompt);
    $stripped = \stripAnsi($output);

    \expect($stripped)->toContain('navigate')
        ->and($stripped)->toContain('←→ column')
        ->and($stripped)->toContain('esc close')
        ->and($stripped)->not->toContain('v versions')
    ;
});

\it('renders normal help line with v versions hint when picker is not active', function (): void {
    Prompt::fake(["\n"]);

    $prompt = new UpgradePrompt([\outdatedPackageWithMinor()]);
    $prompt->prompt();

    $prompt->state = 'initial';
    $output        = \renderPrompt($prompt);
    $stripped      = \stripAnsi($output);

    \expect($stripped)->toContain('v versions');
});

// ---------------------------------------------------------------------------
// Picker-selected version shows in the main row cell
// ---------------------------------------------------------------------------

\it('cell shows the picker-selected version instead of the default target', function (): void {
    Prompt::fake(["\n"]);

    // Package has minor target 1.3.2, but user picked 1.3.1 via the picker
    $versionTarget = VersionTarget::fromRaw('1.3.1');
    $outdatedPackage      = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.2');
    $prompt   = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state                    = 'initial';
    $prompt->selections['vendor/pkg'] = new \Hpbxxtr\UpgradeInteractive\Resolver\VersionSelection(
        \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor,
        $versionTarget,
    );

    $output   = \renderPrompt($prompt);
    $stripped = \stripAnsi($output);

    \expect($stripped)->toContain('1.3.1') // selected version in cell
        ->and($stripped)->toContain('1.2.3...1.3.1') // footer compare URL uses selected version
        ->and($stripped)->not->toContain('1.2.3...1.3.2') // not the default target
    ;
});

// ---------------------------------------------------------------------------
// Footer updates while navigating the picker
// ---------------------------------------------------------------------------

\it('footer compare URL reflects the picker cursor version while picker is active', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage    = \outdatedPackage(name: 'vendor/pkg', current: '1.2.3', minor: VersionTarget::fromRaw('1.3.5'));
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state          = 'initial';
    $prompt->isPickerActive = true;
    $prompt->pickerBumpType = \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor;
    $prompt->pickerColumns  = [new \Hpbxxtr\UpgradeInteractive\UI\PickerColumn('1.3.x', [VersionTarget::fromRaw('1.3.5'), VersionTarget::fromRaw('1.3.2')])];
    $prompt->pickerCol      = 0;
    $prompt->pickerRow      = 1; // cursor on 1.3.2

    $output   = \renderPrompt($prompt);
    $stripped = \stripAnsi($output);

    \expect($stripped)->toContain('1.2.3...1.3.2') // compare URL uses cursor version
        ->and($stripped)->not->toContain('1.2.3...1.3.5') // not the default target
    ;
});

\it('pads shorter columns with empty cells when column heights differ', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage    = \outdatedPackageWithMinor();
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state          = 'initial';
    $prompt->isPickerActive = true;
    $prompt->pickerBumpType = \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor;
    // col 0 has 1 version, col 1 has 2 — row 1 of col 0 is empty (padding branch)
    $prompt->pickerColumns  = [
        new \Hpbxxtr\UpgradeInteractive\UI\PickerColumn('1.3.x', [VersionTarget::fromRaw('1.3.5')]),
        new \Hpbxxtr\UpgradeInteractive\UI\PickerColumn('1.4.x', [VersionTarget::fromRaw('1.4.1'), VersionTarget::fromRaw('1.4.0')]),
    ];
    $prompt->pickerCol = 1;
    $prompt->pickerRow = 0;

    $output   = \renderPrompt($prompt);
    $stripped = \stripAnsi($output);

    \expect($stripped)->toContain('1.3.x')
        ->and($stripped)->toContain('1.4.x')
        ->and($stripped)->toContain('1.4.1')
        ->and($stripped)->toContain('1.4.0')
    ;
});

// ---------------------------------------------------------------------------
// Compatibility: ! markers in main grid
// ---------------------------------------------------------------------------

\it('renders ! marker on an incompatible (unselected, unfocused) bump column', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.0.0', '1.3.0');
    $prompt          = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state = 'initial';
    // Mark 1.3.0 as conflicting
    $prompt->conflictMap = new ConflictMap([
        'vendor/pkg' => ['1.3.0' => [new ConflictReason('vendor/pkg', '1.3.0', 'vendor/dep', '^1.0', '2.0.0')]],
    ]);

    $output = \stripAnsi(\renderPrompt($prompt));

    \expect($output)->toContain('◯!')
        ->and($output)->not->toContain('◯ 1.3.0')
    ;
});

\it('renders normal circle on a compatible (unselected, unfocused) bump column', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.0.0', '1.3.0');
    $prompt          = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state       = 'initial';
    $prompt->conflictMap = ConflictMap::empty();

    $output = \stripAnsi(\renderPrompt($prompt));

    \expect($output)->toContain('◯ 1.3.0')
        ->and($output)->not->toContain('! 1.3.0')
    ;
});

\it('renders ! marker on a selected but conflicting bump column', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.0.0', '1.3.0');
    $prompt          = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state      = 'initial';
    $prompt->activeRow  = 0;
    $prompt->activeCol  = 99; // not focused on minor

    $prompt->selections['vendor/pkg'] = new \Hpbxxtr\UpgradeInteractive\Resolver\VersionSelection(
        \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor,
        VersionTarget::fromRaw('1.3.0'),
    );

    $prompt->conflictMap = new ConflictMap([
        'vendor/pkg' => ['1.3.0' => [new ConflictReason('vendor/pkg', '1.3.0', 'vendor/dep', '^1.0', '2.0.0')]],
    ]);

    $output = \stripAnsi(\renderPrompt($prompt));

    // Selected + conflicting → "◉!1.3.0" (not "◉ 1.3.0")
    \expect($output)->toContain('◉!')
        ->and($output)->not->toContain('◉ 1.3.0')
    ;
});

// ---------------------------------------------------------------------------
// Compatibility: ✗ markers in picker
// ---------------------------------------------------------------------------

\it('renders ✗ on an incompatible version in the picker (not cursor)', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackageWithMinor();
    $prompt          = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state          = 'initial';
    $prompt->isPickerActive = true;
    $prompt->pickerBumpType = \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor;
    $prompt->pickerColumns  = [
        new \Hpbxxtr\UpgradeInteractive\UI\PickerColumn('1.3.x', [
            VersionTarget::fromRaw('1.3.1'),
            VersionTarget::fromRaw('1.3.0'),
        ]),
    ];
    $prompt->pickerCol = 0;
    $prompt->pickerRow = 0; // cursor on 1.3.1

    // 1.3.0 is incompatible
    $prompt->pickerVersionCompatibility = ['1.3.1' => true, '1.3.0' => false];

    $output = \stripAnsi(\renderPrompt($prompt));

    \expect($output)->toContain('1.3.0 ✗');
});

\it('renders ✗ on an incompatible version in the picker when it is the cursor', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackageWithMinor();
    $prompt          = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state          = 'initial';
    $prompt->isPickerActive = true;
    $prompt->pickerBumpType = \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor;
    $prompt->pickerColumns  = [
        new \Hpbxxtr\UpgradeInteractive\UI\PickerColumn('1.3.x', [VersionTarget::fromRaw('1.3.0')]),
    ];
    $prompt->pickerCol = 0;
    $prompt->pickerRow = 0; // cursor on 1.3.0

    // cursor version is incompatible
    $prompt->pickerVersionCompatibility = ['1.3.0' => false];

    $output = \stripAnsi(\renderPrompt($prompt));

    \expect($output)->toContain('▸ 1.3.0')
        ->and($output)->toContain('✗')
    ;
});

\it('renders picker without ✗ when all versions are compatible', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackageWithMinor();
    $prompt          = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state          = 'initial';
    $prompt->isPickerActive = true;
    $prompt->pickerBumpType = \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor;
    $prompt->pickerColumns  = [
        new \Hpbxxtr\UpgradeInteractive\UI\PickerColumn('1.3.x', [VersionTarget::fromRaw('1.3.0')]),
    ];
    $prompt->pickerCol = 0;
    $prompt->pickerRow = 0;

    $prompt->pickerVersionCompatibility = ['1.3.0' => true];

    $output = \stripAnsi(\renderPrompt($prompt));

    \expect($output)->not->toContain('✗');
});

// ---------------------------------------------------------------------------
// Compatibility: conflict footer lines
// ---------------------------------------------------------------------------

\it('renders conflict footer line when active selection has conflicts', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.0.0', '1.3.0');
    $prompt          = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state     = 'initial';
    $prompt->activeRow = 0;

    $prompt->selections['vendor/pkg'] = new \Hpbxxtr\UpgradeInteractive\Resolver\VersionSelection(
        \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor,
        VersionTarget::fromRaw('1.3.0'),
    );

    $prompt->conflictMap = new ConflictMap([
        'vendor/pkg' => ['1.3.0' => [new ConflictReason('vendor/pkg', '1.3.0', 'vendor/dep', '^1.0', '2.0.0')]],
    ]);

    $output = \stripAnsi(\renderPrompt($prompt));

    \expect($output)->toContain('! vendor/pkg 1.3.0 requires vendor/dep ^1.0 — selected: 2.0.0');
});

\it('renders conflict footer line when hovering an incompatible version without a selection', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.0.0', '1.3.0');
    $prompt          = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state     = 'initial';
    $prompt->activeRow = 0;
    // No selection — just hovering the minor column (only available bump, so activeCol 0 → minor)

    $prompt->conflictMap = new ConflictMap([
        'vendor/pkg' => ['1.3.0' => [new ConflictReason('vendor/pkg', '1.3.0', 'vendor/dep', '^1.0', '2.0.0')]],
    ]);

    $output = \stripAnsi(\renderPrompt($prompt));

    \expect($output)->toContain('! vendor/pkg 1.3.0 requires vendor/dep ^1.0 — selected: 2.0.0');
});

// ---------------------------------------------------------------------------
// Compatibility: selected-but-not-focused column rendering (lines 299, 301)
// ---------------------------------------------------------------------------

\it('renders ◉! on a selected-but-not-focused incompatible bump column', function (): void {
    Prompt::fake(["\n"]);

    // Package has both minor and major bumps so the two columns are different
    $outdatedPackage = \outdatedPackage(
        name: 'vendor/pkg',
        current: '1.0.0',
        minor: VersionTarget::fromRaw('1.3.0'),
        major: VersionTarget::fromRaw('2.0.0'),
    );
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state     = 'initial';
    $prompt->activeRow = 0;
    $prompt->activeCol = 1; // focused on major (index 1 of [minor, major])

    // Selection is on minor
    $prompt->selections['vendor/pkg'] = new \Hpbxxtr\UpgradeInteractive\Resolver\VersionSelection(
        \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor,
        VersionTarget::fromRaw('1.3.0'),
    );

    // Minor version 1.3.0 is conflicting
    $prompt->conflictMap = new ConflictMap([
        'vendor/pkg' => ['1.3.0' => [new ConflictReason('vendor/other', '1.5.0', 'vendor/dep', '^1.0', '2.0.0')]],
    ]);

    $output = \stripAnsi(\renderPrompt($prompt));

    // Minor column: selected + not focused + incompatible → ◉! (line 299)
    \expect($output)->toContain('◉!')
        ->and($output)->not->toContain('◉ 1.3.0')
    ;
});

\it('renders ◉ (space) on a selected-but-not-focused compatible bump column', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackage(
        name: 'vendor/pkg',
        current: '1.0.0',
        minor: VersionTarget::fromRaw('1.3.0'),
        major: VersionTarget::fromRaw('2.0.0'),
    );
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state     = 'initial';
    $prompt->activeRow = 0;
    $prompt->activeCol = 1; // focused on major

    $prompt->selections['vendor/pkg'] = new \Hpbxxtr\UpgradeInteractive\Resolver\VersionSelection(
        \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor,
        VersionTarget::fromRaw('1.3.0'),
    );

    $prompt->conflictMap = ConflictMap::empty(); // no conflicts

    $output = \stripAnsi(\renderPrompt($prompt));

    // Minor column: selected + not focused + compatible → ◉ 1.3.0 (line 301)
    \expect($output)->toContain('◉ 1.3.0')
        ->and($output)->not->toContain('◉!')
    ;
});

\it('does not render conflict footer when conflictMap is empty', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.0.0', '1.3.0');
    $prompt          = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state     = 'initial';
    $prompt->activeRow = 0;

    $prompt->selections['vendor/pkg'] = new \Hpbxxtr\UpgradeInteractive\Resolver\VersionSelection(
        \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor,
        VersionTarget::fromRaw('1.3.0'),
    );

    $prompt->conflictMap = ConflictMap::empty();

    $output = \stripAnsi(\renderPrompt($prompt));

    // No conflict lines starting with "! vendor/"
    \expect($output)->not->toContain('! vendor/pkg 1.3.0 requires');
});

// ---------------------------------------------------------------------------
// Compatibility: static footer (phase 1) — selection conflicts always visible
// ---------------------------------------------------------------------------

\it('renders selection conflict in footer even when cursor has moved to a different column', function (): void {
    Prompt::fake(["\n"]);

    // Package has both minor and major — selection on minor (conflicting), cursor on major
    $outdatedPackage = \outdatedPackage(
        name: 'vendor/pkg',
        current: '1.0.0',
        minor: VersionTarget::fromRaw('1.3.0'),
        major: VersionTarget::fromRaw('2.0.0'),
    );
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state     = 'initial';
    $prompt->activeRow = 0;
    $prompt->activeCol = 1; // cursor on major column

    // Selection is on minor with a conflict
    $prompt->selections['vendor/pkg'] = new \Hpbxxtr\UpgradeInteractive\Resolver\VersionSelection(
        \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor,
        VersionTarget::fromRaw('1.3.0'),
    );

    $prompt->conflictMap = new ConflictMap([
        'vendor/pkg' => ['1.3.0' => [new ConflictReason('vendor/pkg', '1.3.0', 'vendor/dep', '^1.0', '2.0.0')]],
    ]);

    $output = \stripAnsi(\renderPrompt($prompt));

    // Phase 1: selection conflict must appear even though cursor is on major
    \expect($output)->toContain('! vendor/pkg 1.3.0 requires vendor/dep ^1.0 — selected: 2.0.0');
});

\it('renders conflict footer lines for all selected packages regardless of focused row', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/a', '1.0.0', '1.3.0');
    $pkgB = \outdatedPackageWithMinor('vendor/b', '2.0.0', '2.1.0');
    $prompt = new UpgradePrompt([$outdatedPackage, $pkgB]);
    $prompt->prompt();

    $prompt->state     = 'initial';
    $prompt->activeRow = 0; // focused on vendor/a

    $prompt->selections['vendor/a'] = new \Hpbxxtr\UpgradeInteractive\Resolver\VersionSelection(
        \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor,
        VersionTarget::fromRaw('1.3.0'),
    );
    $prompt->selections['vendor/b'] = new \Hpbxxtr\UpgradeInteractive\Resolver\VersionSelection(
        \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor,
        VersionTarget::fromRaw('2.1.0'),
    );

    $prompt->conflictMap = new ConflictMap([
        'vendor/a' => ['1.3.0' => [new ConflictReason('vendor/a', '1.3.0', 'vendor/dep', '^1.0', '2.0.0')]],
        'vendor/b' => ['2.1.0' => [new ConflictReason('vendor/b', '2.1.0', 'vendor/dep', '^2.0', '3.0.0')]],
    ]);

    $output = \stripAnsi(\renderPrompt($prompt));

    // Both conflict lines must appear even though vendor/b is not the focused row
    \expect($output)->toContain('! vendor/a 1.3.0 requires vendor/dep ^1.0 — selected: 2.0.0')
        ->and($output)->toContain('! vendor/b 2.1.0 requires vendor/dep ^2.0 — selected: 3.0.0');
});

\it('renders each conflict exactly once when hovering the selected conflicting version', function (): void {
    Prompt::fake(["\n"]);

    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.0.0', '1.3.0');
    $prompt          = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state     = 'initial';
    $prompt->activeRow = 0;
    $prompt->activeCol = 0; // cursor on the minor column (same as selection)

    $prompt->selections['vendor/pkg'] = new \Hpbxxtr\UpgradeInteractive\Resolver\VersionSelection(
        \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Minor,
        VersionTarget::fromRaw('1.3.0'),
    );

    $prompt->conflictMap = new ConflictMap([
        'vendor/pkg' => ['1.3.0' => [new ConflictReason('vendor/pkg', '1.3.0', 'vendor/dep', '^1.0', '2.0.0')]],
    ]);

    $output = \stripAnsi(\renderPrompt($prompt));

    // Phase 1 covers it; phase 2 must be skipped — conflict line appears exactly once
    $conflictLine  = '! vendor/pkg 1.3.0 requires vendor/dep ^1.0 — selected: 2.0.0';
    $occurrences   = substr_count($output, $conflictLine);
    \expect($occurrences)->toBe(1);
});

\it('renders both selection conflict and hover conflict when they are for different versions', function (): void {
    Prompt::fake(["\n"]);

    // Package has patch (1.0.1, selected + conflicting) and minor (1.3.0, hover + conflicting)
    $outdatedPackage = \outdatedPackage(
        name: 'vendor/pkg',
        current: '1.0.0',
        patch: VersionTarget::fromRaw('1.0.1'),
        minor: VersionTarget::fromRaw('1.3.0'),
    );
    $prompt = new UpgradePrompt([$outdatedPackage]);
    $prompt->prompt();

    $prompt->state     = 'initial';
    $prompt->activeRow = 0;
    $prompt->activeCol = 1; // cursor on minor column (index 1 of [patch, minor])

    // Selection is on patch (conflicting)
    $prompt->selections['vendor/pkg'] = new \Hpbxxtr\UpgradeInteractive\Resolver\VersionSelection(
        \Hpbxxtr\UpgradeInteractive\Resolver\BumpType::Patch,
        VersionTarget::fromRaw('1.0.1'),
    );

    $prompt->conflictMap = new ConflictMap([
        'vendor/pkg' => [
            '1.0.1' => [new ConflictReason('vendor/pkg', '1.0.1', 'vendor/dep', '^1.0', '2.0.0')],
            '1.3.0' => [new ConflictReason('vendor/pkg', '1.3.0', 'vendor/dep', '^1.0', '2.0.0')],
        ],
    ]);

    $output = \stripAnsi(\renderPrompt($prompt));

    // Phase 1 shows selected (patch) conflict; phase 2 shows hover (minor) conflict
    \expect($output)->toContain('! vendor/pkg 1.0.1 requires vendor/dep ^1.0 — selected: 2.0.0')
        ->and($output)->toContain('! vendor/pkg 1.3.0 requires vendor/dep ^1.0 — selected: 2.0.0');
});
