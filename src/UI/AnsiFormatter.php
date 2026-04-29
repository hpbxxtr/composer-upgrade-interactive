<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\UI;

use Hpbxxtr\UpgradeInteractive\Core\Str;
use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;

use function max;

/**
 * @internal
 */
final readonly class AnsiFormatter
{
    private const string RESET    = "\e[0m";
    private const string BOLD     = "\e[1m";
    private const string BOLD_OFF = "\e[22m";
    private const string DIM      = "\e[2m";
    private const string CYAN     = "\e[36m";
    private const string GREEN    = "\e[32m";
    private const string RED      = "\e[31m";
    private const string BLUE     = "\e[34m";
    private const string BG_BLUE  = "\e[44m";
    private const string WHITE    = "\e[37m";
    private const string YELLOW   = "\e[33m";

    private const array BUMP_COLORS = [
        'patch' => self::BLUE,
        'minor' => self::GREEN,
        'major' => self::RED,
    ];

    // -------------------------------------------------------------------------
    // Generic primitives
    // -------------------------------------------------------------------------

    public function dim(string $s): string
    {
        return self::DIM . $s . self::RESET;
    }

    public function bold(string $s): string
    {
        return self::BOLD . $s . self::RESET;
    }

    public function hyperlink(string $url, string $text): string
    {
        return "\e]8;;" . $url . "\e\\" . $text . "\e]8;;\e\\";
    }

    public function visLen(string $s): int
    {
        return Str::length(Str::replace('/\e\[[0-9;]*m/', $s, ''));
    }

    public function visPad(string $s, int $width): string
    {
        return $s . Str::repeat(' ', max(0, $width - $this->visLen($s)));
    }

    // -------------------------------------------------------------------------
    // Semantic methods
    // -------------------------------------------------------------------------

    public function warning(string $s): string
    {
        return self::YELLOW . $s . self::RESET;
    }

    public function footerLabel(string $label): string
    {
        return $this->dim($label);
    }

    public function footerLink(string $url): string
    {
        return self::CYAN . $this->hyperlink($url, $url) . self::RESET;
    }

    public function bumpColor(BumpType $bumpType, string $text): string
    {
        return self::BUMP_COLORS[$bumpType->value] . $text . self::RESET;
    }

    public function abandonedIcon(): string
    {
        return self::YELLOW . '⚠' . self::RESET;
    }

    public function submitCheck(): string
    {
        return self::GREEN . '✔' . self::RESET;
    }

    public function cursor(bool $isActive): string
    {
        return $isActive
            ? self::BOLD . '❯' . self::BOLD_OFF
            : ' ';
    }

    public function selectedMark(BumpType $bumpType): string
    {
        return self::BUMP_COLORS[$bumpType->value] . '◉' . self::RESET;
    }

    public function unselectedMark(): string
    {
        return $this->dim('◯');
    }

    public function columnHeader(string $label): string
    {
        return self::BOLD . $label . self::BOLD_OFF;
    }

    public function promptLabel(string $label): string
    {
        return self::BOLD . $label . self::RESET;
    }

    public function separator(): string
    {
        return $this->dim('  │  ');
    }

    public function pickerExpandLabel(BumpType $bumpType): string
    {
        return self::BUMP_COLORS[$bumpType->value] . $bumpType->value . ' ▾' . self::RESET;
    }

    public function loading(): string
    {
        return self::CYAN . '⠋' . self::RESET . '  ' . self::DIM . 'loading…' . self::RESET;
    }

    public function pickerVersion(string $version): string
    {
        return $this->dim($version);
    }

    public function versionCell(
        bool $isFocused,
        bool $isSelected,
        bool $isCompatible,
        string $version,
        BumpType $bumpType,
    ): string {
        $color = self::BUMP_COLORS[$bumpType->value];

        return match (true) {
            $isFocused && $isSelected && !$isCompatible
                => self::BG_BLUE . self::WHITE . self::BOLD . '◉!' . $version . self::RESET,
            $isFocused && $isSelected
                => self::BG_BLUE . self::WHITE . self::BOLD . '◉ ' . $version . self::RESET,
            $isFocused && !$isCompatible
                => self::BG_BLUE . self::WHITE . '◯!' . $version . self::RESET,
            $isFocused
                => self::BG_BLUE . self::WHITE . '◯ ' . $version . self::RESET,
            $isSelected && !$isCompatible
                => self::GREEN . self::BOLD . '◉' . self::RESET . self::YELLOW . '!' . self::RESET . $color . $version . self::RESET,
            $isSelected
                => self::GREEN . self::BOLD . '◉' . self::RESET . ' ' . $color . $version . self::RESET,
            !$isCompatible
                => $this->dim('◯') . self::YELLOW . '!' . self::RESET . $color . $version . self::RESET,
            default
                => $this->dim('◯') . ' ' . $color . $version . self::RESET,
        };
    }

    public function pickerRow(
        bool $isCursor,
        bool $isCompatible,
        string $version,
        string $suffix,
    ): string {
        return match (true) {
            $isCursor && !$isCompatible
                => self::BG_BLUE . self::WHITE . '▸ ' . $version . self::RESET . ' ' . self::DIM . '✗' . self::RESET . $suffix,
            $isCursor
                => self::BG_BLUE . self::WHITE . '▸ ' . $version . self::RESET . $suffix,
            !$isCompatible
                => '  ' . self::DIM . $version . ' ✗' . self::RESET,
            default
                => '  ' . $this->dim($version) . $suffix,
        };
    }
}
