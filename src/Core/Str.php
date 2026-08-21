<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Core;

use function max;
use function mb_str_pad;
use function mb_strlen;
use function mb_strtolower;
use function mb_substr;
use function preg_replace;
use function str_repeat;
use function str_starts_with;

use const STR_PAD_LEFT;
use const STR_PAD_RIGHT;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final class Str
{
    public static function length(string $string): int
    {
        // @phpstan-ignore symplify.forbiddenFuncCall (Avoid using symfony/string)
        return mb_strlen($string);
    }

    public static function repeat(string $string, int $times): string
    {
        // @phpstan-ignore symplify.forbiddenFuncCall (Avoid using symfony/string)
        return str_repeat($string, $times);
    }

    public static function pad(string $string, int $length, string $padString = ' ', bool $isPadLeft = false): string
    {
        // @phpstan-ignore symplify.forbiddenFuncCall (Avoid using symfony/string)
        return mb_str_pad($string, $length, $padString, $isPadLeft ? STR_PAD_LEFT : STR_PAD_RIGHT);
    }

    public static function padLeft(string $string, int $length, string $padString = ' '): string
    {
        return self::pad($string, $length, $padString, true);
    }

    public static function padRight(string $string, int $length, string $padString = ' '): string
    {
        return self::pad($string, $length, $padString, false);
    }

    /**
     * Removes the last $count characters.
     */
    public static function dropLast(string $string, int $count = 1): string
    {
        // @phpstan-ignore symplify.forbiddenFuncCall (Avoid using symfony/string)
        return mb_substr($string, 0, max(0, self::length($string) - $count));
    }

    public static function lower(string $string): string
    {
        // @phpstan-ignore symplify.forbiddenFuncCall (Avoid using symfony/string)
        return mb_strtolower($string);
    }

    public static function trim(string $string, string $characters = " \t\n\r\0\x0B"): string
    {
        // @phpstan-ignore symplify.forbiddenFuncCall (Avoid using symfony/string)
        return trim($string, $characters);
    }

    public static function trimStart(string $string, string $characters = " \t\n\r\0\x0B"): string
    {
        // @phpstan-ignore symplify.forbiddenFuncCall (Avoid using symfony/string)
        return ltrim($string, $characters);
    }

    public static function trimEnd(string $string, string $characters = " \t\n\r\0\x0B"): string
    {
        // @phpstan-ignore symplify.forbiddenFuncCall (Avoid using symfony/string)
        return rtrim($string, $characters);
    }

    public static function replace(string $pattern, string $string, string $replacement): string
    {
        // @phpstan-ignore symplify.forbiddenFuncCall (Avoid using symfony/string)
        return preg_replace($pattern, $replacement, $string) ?? $string;
    }

    // @phpstan-ignore brnshkr.boolishPrefix (conventional name for this utility — prefix rules don't apply to generic string helpers)
    public static function startsWith(string $string, string $needle): bool
    {
        // @phpstan-ignore symplify.forbiddenFuncCall (Avoid using symfony/string)
        return str_starts_with($string, $needle);
    }

    /**
     * Returns the matches array on success, null if no match.
     *
     * @return array<int|string, string>|null
     */
    public static function match(string $pattern, string $string): ?array
    {
        // @phpstan-ignore symplify.forbiddenFuncCall (Avoid using symfony/string)
        return preg_match($pattern, $string, $m) === 1 ? $m : null;
    }
}
