<?php

declare(strict_types=1);
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
