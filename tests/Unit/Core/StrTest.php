<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\Core\Str;

// ---------------------------------------------------------------------------
// length
// ---------------------------------------------------------------------------

\it('returns character count for an ASCII string', function (): void {
    \expect(Str::length('hello'))->toBe(5);
});

\it('returns 0 for an empty string', function (): void {
    \expect(Str::length(''))->toBe(0);
});

\it('counts multibyte characters as single characters', function (): void {
    \expect(Str::length('héllo'))->toBe(5);
});

// ---------------------------------------------------------------------------
// repeat
// ---------------------------------------------------------------------------

\it('repeats a string the given number of times', function (): void {
    \expect(Str::repeat('ab', 3))->toBe('ababab');
});

\it('returns empty string when repeat count is 0', function (): void {
    \expect(Str::repeat('x', 0))->toBe('');
});

// ---------------------------------------------------------------------------
// pad / padLeft / padRight
// ---------------------------------------------------------------------------

\it('pads a string on the right by default', function (): void {
    \expect(Str::pad('hi', 5))->toBe('hi   ');
});

\it('pads a string on the left when isPadLeft is true', function (): void {
    \expect(Str::pad('hi', 5, ' ', true))->toBe('   hi');
});

\it('padRight pads on the right', function (): void {
    \expect(Str::padRight('hi', 5))->toBe('hi   ');
});

\it('padLeft pads on the left', function (): void {
    \expect(Str::padLeft('hi', 5))->toBe('   hi');
});

\it('does not truncate when string is already at target length', function (): void {
    \expect(Str::pad('hello', 5))->toBe('hello');
});

\it('uses custom pad character', function (): void {
    \expect(Str::pad('hi', 5, '-'))->toBe('hi---');
});

// ---------------------------------------------------------------------------
// trim / trimStart / trimEnd
// ---------------------------------------------------------------------------

\it('trims whitespace from both ends', function (): void {
    \expect(Str::trim('  hello  '))->toBe('hello');
});

\it('trims custom characters from both ends', function (): void {
    \expect(Str::trim('/hello/', '/'))->toBe('hello');
});

\it('trimStart removes only leading whitespace', function (): void {
    \expect(Str::trimStart('  hello  '))->toBe('hello  ');
});

\it('trimEnd removes only trailing whitespace', function (): void {
    \expect(Str::trimEnd('  hello  '))->toBe('  hello');
});

\it('trimStart removes custom leading characters', function (): void {
    \expect(Str::trimStart('vvv1.2.3', 'v'))->toBe('1.2.3');
});

\it('trimEnd removes custom trailing characters', function (): void {
    \expect(Str::trimEnd('1.2.3---', '-'))->toBe('1.2.3');
});

// ---------------------------------------------------------------------------
// replace
// ---------------------------------------------------------------------------

\it('replaces pattern matches with the replacement string', function (): void {
    \expect(Str::replace('/\d+/', '2025-01-15', 'YEAR'))->toBe('YEAR-YEAR-YEAR');
});

\it('returns original string when pattern has no match', function (): void {
    \expect(Str::replace('/\d+/', 'hello', 'X'))->toBe('hello');
});

\it('strips leading v from version via replace', function (): void {
    \expect(Str::replace('/^v/', 'v1.2.3', ''))->toBe('1.2.3');
});

// ---------------------------------------------------------------------------
// match
// ---------------------------------------------------------------------------

\it('returns matches array when pattern matches', function (): void {
    $result = Str::match('/(\d+)\.(\d+)/', '1.2.3');

    \expect($result)->not->toBeNull()
        ->and($result[1])->toBe('1')
        ->and($result[2])->toBe('2')
    ;
});

\it('returns null when pattern does not match', function (): void {
    \expect(Str::match('/\d+/', 'hello'))->toBeNull();
});

\it('extracts github slug from HTTPS URL', function (): void {
    $result = Str::match('#github\.com[/:]([^/]+/[^/]+?)(?:\.git)?(?:/.*)?$#', 'https://github.com/vendor/pkg.git');

    \expect($result)->not->toBeNull()
        ->and($result[1])->toBe('vendor/pkg')
    ;
});

\it('extracts github slug from SSH URL', function (): void {
    $result = Str::match('#github\.com[/:]([^/]+/[^/]+?)(?:\.git)?(?:/.*)?$#', 'git@github.com:vendor/pkg.git');

    \expect($result)->not->toBeNull()
        ->and($result[1])->toBe('vendor/pkg')
    ;
});
