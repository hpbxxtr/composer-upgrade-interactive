<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\UI\AnsiFormatter;

function stripAnsiCodes(string $s): string
{
    $s = (string) preg_replace('/\e\[[0-9;]*m/', '', $s);

    return (string) preg_replace('/\x1b\]8;;[^\x1b]*\x1b\\\\/', '', $s);
}

// -------------------------------------------------------------------------
// Generic primitives
// -------------------------------------------------------------------------

\it('dim wraps text in dim escape', function (): void {
    $fmt = new AnsiFormatter();

    \expect($fmt->dim('hello'))->toBe("\e[2mhello\e[0m");
});

\it('bold wraps text in bold escape', function (): void {
    $fmt = new AnsiFormatter();

    \expect($fmt->bold('hello'))->toBe("\e[1mhello\e[0m");
});

\it('hyperlink returns OSC 8 sequence', function (): void {
    $fmt = new AnsiFormatter();
    $result = $fmt->hyperlink('https://example.com', 'click');

    \expect($result)->toBe("\e]8;;https://example.com\e\\click\e]8;;\e\\");
});

\it('visLen ignores SGR escape codes', function (): void {
    $fmt = new AnsiFormatter();

    \expect($fmt->visLen("\e[1mhello\e[0m"))->toBe(5);
});

\it('visPad pads to visual width ignoring escapes', function (): void {
    $fmt    = new AnsiFormatter();
    $padded = $fmt->visPad("\e[1mhi\e[0m", 5);

    \expect($fmt->visLen($padded))->toBe(5);
});

// -------------------------------------------------------------------------
// Semantic methods
// -------------------------------------------------------------------------

\it('warning returns yellow-wrapped text', function (): void {
    $fmt = new AnsiFormatter();

    \expect($fmt->warning('danger'))->toBe("\e[33mdanger\e[0m");
});

\it('footerLabel returns dim text', function (): void {
    $fmt = new AnsiFormatter();

    \expect($fmt->footerLabel('compare'))->toBe("\e[2mcompare\e[0m");
});

\it('footerLink returns cyan OSC-8 hyperlink', function (): void {
    $fmt    = new AnsiFormatter();
    $result = $fmt->footerLink('https://example.com');

    \expect($result)->toStartWith("\e[36m")
        ->and(\stripAnsiCodes($result))->toBe('https://example.com');
});

\it('bumpColor wraps text in the correct bump-type color', function (): void {
    $fmt = new AnsiFormatter();

    \expect($fmt->bumpColor(BumpType::Minor, 'minor'))->toBe("\e[32mminor\e[0m");
    \expect($fmt->bumpColor(BumpType::Major, 'major'))->toBe("\e[31mmajor\e[0m");
    \expect($fmt->bumpColor(BumpType::Patch, 'patch'))->toBe("\e[34mpatch\e[0m");
});

\it('submitCheck returns green checkmark', function (): void {
    $fmt = new AnsiFormatter();

    \expect(\stripAnsiCodes($fmt->submitCheck()))->toBe('✔');
    \expect($fmt->submitCheck())->toStartWith("\e[32m");
});

\it('cursor returns bold arrow when active', function (): void {
    $fmt = new AnsiFormatter();

    \expect($fmt->cursor(true))->toBe("\e[1m❯\e[22m");
    \expect($fmt->cursor(false))->toBe(' ');
});

\it('selectedMark returns bump-colored circle', function (): void {
    $fmt = new AnsiFormatter();

    \expect(\stripAnsiCodes($fmt->selectedMark(BumpType::Minor)))->toBe('◉');
    \expect($fmt->selectedMark(BumpType::Minor))->toStartWith("\e[32m");
});

\it('unselectedMark returns dim empty circle', function (): void {
    $fmt = new AnsiFormatter();

    \expect(\stripAnsiCodes($fmt->unselectedMark()))->toBe('◯');
});

\it('columnHeader uses BOLD_OFF not RESET', function (): void {
    $fmt = new AnsiFormatter();

    \expect($fmt->columnHeader('minor'))->toBe("\e[1mminor\e[22m");
});

\it('separator returns dim vertical bar with spacing', function (): void {
    $fmt = new AnsiFormatter();

    \expect(\stripAnsiCodes($fmt->separator()))->toBe('  │  ');
});

\it('pickerExpandLabel includes bump value and down-arrow', function (): void {
    $fmt = new AnsiFormatter();

    \expect(\stripAnsiCodes($fmt->pickerExpandLabel(BumpType::Minor)))->toBe('minor ▾');
});

\it('loading returns spinner glyph and loading text', function (): void {
    $fmt  = new AnsiFormatter();
    $text = \stripAnsiCodes($fmt->loading());

    \expect($text)->toContain('⠋')
        ->and($text)->toContain('loading…');
});

// -------------------------------------------------------------------------
// versionCell states
// -------------------------------------------------------------------------

\it('versionCell focused+selected+incompatible uses bg-blue+bold with ◉!', function (): void {
    $fmt  = new AnsiFormatter();
    $text = \stripAnsiCodes($fmt->versionCell(true, true, false, '1.0.0', BumpType::Minor));

    \expect($text)->toBe('◉!1.0.0');
});

\it('versionCell focused+selected shows ◉ with space', function (): void {
    $fmt  = new AnsiFormatter();
    $text = \stripAnsiCodes($fmt->versionCell(true, true, true, '1.0.0', BumpType::Minor));

    \expect($text)->toBe('◉ 1.0.0');
});

\it('versionCell selected+incompatible shows ◉! and bump color', function (): void {
    $fmt  = new AnsiFormatter();
    $text = \stripAnsiCodes($fmt->versionCell(false, true, false, '1.0.0', BumpType::Patch));

    \expect($text)->toBe('◉!1.0.0');
});

\it('versionCell default (unfocused, unselected, compatible) shows dim circle + version', function (): void {
    $fmt  = new AnsiFormatter();
    $text = \stripAnsiCodes($fmt->versionCell(false, false, true, '1.0.0', BumpType::Major));

    \expect($text)->toBe('◯ 1.0.0');
});

// -------------------------------------------------------------------------
// pickerRow states
// -------------------------------------------------------------------------

\it('pickerRow cursor shows arrow prefix', function (): void {
    $fmt  = new AnsiFormatter();
    $text = \stripAnsiCodes($fmt->pickerRow(true, true, '1.0.0', ''));

    \expect($text)->toBe('▸ 1.0.0');
});

\it('pickerRow cursor+incompatible shows arrow and cross mark', function (): void {
    $fmt  = new AnsiFormatter();
    $text = \stripAnsiCodes($fmt->pickerRow(true, false, '1.0.0', ''));

    \expect($text)->toContain('▸ 1.0.0')
        ->and($text)->toContain('✗');
});

\it('pickerRow incompatible shows cross mark without cursor', function (): void {
    $fmt  = new AnsiFormatter();
    $text = \stripAnsiCodes($fmt->pickerRow(false, false, '1.0.0', ''));

    \expect($text)->toContain('1.0.0')
        ->and($text)->toContain('✗')
        ->and($text)->not->toContain('▸');
});

\it('pickerRow default appends suffix', function (): void {
    $fmt  = new AnsiFormatter();
    $text = \stripAnsiCodes($fmt->pickerRow(false, true, '1.0.0', '  (latest)'));

    \expect($text)->toContain('1.0.0')
        ->and($text)->toContain('(latest)');
});
