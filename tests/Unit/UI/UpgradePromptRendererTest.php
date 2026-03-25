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
