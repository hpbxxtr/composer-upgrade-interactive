<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\UI\InteractiveUI;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

afterEach(function (): void {
    \Mockery::close();
});

\it('returns empty array when user submits with no selection', function (): void {
    Prompt::fake(["\n"]);

    $ui = new InteractiveUI();

    \expect($ui->ask([\outdatedPackageWithMinor()]))->toBe([]);
});

\it('returns selected package after space + enter + confirm', function (): void {
    Prompt::fake([Key::SPACE, "\n", "\n"]); // SPACE selects, Enter submits, Enter confirms

    $ui              = new InteractiveUI();
    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');

    \expect($ui->ask([$outdatedPackage]))->toBe(['vendor/pkg' => '1.3.0']);
});

\it('returns empty array when user declines the confirmation', function (): void {
    Prompt::fake([Key::SPACE, "\n", 'n', "\n"]); // SPACE selects, Enter submits, n declines, Enter confirms

    $ui              = new InteractiveUI();
    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');

    \expect($ui->ask([$outdatedPackage]))->toBe([]);
});

\it('returns empty array after Ctrl+C cancels the prompt', function (): void {
    Prompt::fake([Key::SPACE, Key::CTRL_C]);

    $ui              = new InteractiveUI();
    $outdatedPackage = \outdatedPackageWithMinor('vendor/pkg', '1.2.3', '1.3.0');

    \expect($ui->ask([$outdatedPackage]))->toBe([]);
});
