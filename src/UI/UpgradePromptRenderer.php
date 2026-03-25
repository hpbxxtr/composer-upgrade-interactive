<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\UI;

use Hpbxxtr\UpgradeInteractive\Core\Str;
use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\Url\ComposeUrlResolver;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;
use Laravel\Prompts\Themes\Default\Renderer;

use function array_map;
use function count;
use function implode;
use function max;
use function min;
use function sprintf;

/**
 * @internal
 */
final class UpgradePromptRenderer extends Renderer
{
    private const string ANSI_RESET    = "\e[0m";
    private const string ANSI_BOLD     = "\e[1m";
    private const string ANSI_BOLD_OFF = "\e[22m";
    private const string ANSI_DIM      = "\e[2m";
    private const string ANSI_CYAN     = "\e[36m";
    private const string ANSI_GREEN    = "\e[32m";
    private const string ANSI_RED      = "\e[31m";
    private const string ANSI_BLUE     = "\e[34m";
    private const string ANSI_BG_BLUE  = "\e[44m";
    private const string ANSI_WHITE    = "\e[37m";
    private const string ANSI_YELLOW   = "\e[33m";

    private const array BUMP_COLOR = [
        'patch' => self::ANSI_BLUE,
        'minor' => self::ANSI_GREEN,
        'major' => self::ANSI_RED,
    ];

    public function __invoke(UpgradePrompt $upgradePrompt): string
    {
        if ($upgradePrompt->state === 'submit') {
            $this->line("  \e[32m✔\e[0m Done");

            return (string) $this;
        }

        $entries = $upgradePrompt->entries;

        // Precompute column widths
        $nameLabel = static fn (OutdatedPackage $outdatedPackage): string => $outdatedPackage->abandonedBy !== null ? $outdatedPackage->name . '  ⚠' : $outdatedPackage->name;

        /** @var non-empty-list<int<0, max>> $nameLengths */
        $nameLengths = array_map(static fn (OutdatedPackage $outdatedPackage): int => Str::length($nameLabel($outdatedPackage)), $entries);
        $maxName     = max($nameLengths);

        /** @var non-empty-list<int<0, max>> $fromLengths */
        $fromLengths = array_map(static fn (OutdatedPackage $outdatedPackage): int => Str::length($outdatedPackage->current), $entries);
        $maxFrom     = max($fromLengths);

        /** @var non-empty-list<int<0, max>> $colWidths */
        $colWidths = array_map(
            static fn (OutdatedPackage $outdatedPackage): int => max(
                0,
                $outdatedPackage->patch instanceof VersionTarget ? Str::length($outdatedPackage->patch->version) : 0,
                $outdatedPackage->minor instanceof VersionTarget ? Str::length($outdatedPackage->minor->version) : 0,
                $outdatedPackage->major instanceof VersionTarget ? Str::length($outdatedPackage->major->version) : 0,
            ),
            $entries,
        );
        $colW = max(5, ...$colWidths);

        // Determine first dev row index
        $firstDevIdx = -1;

        foreach ($entries as $i => $entry) {
            if ($entry->isDev) {
                $firstDevIdx = $i;

                break;
            }
        }

        $hasProd = $firstDevIdx !== 0;
        $hasDev  = $firstDevIdx !== -1;

        $sep = $this->dimStr('  │  ');

        // Header line
        $namePad = Str::repeat(' ', 4 + $maxName);
        $fromPad = Str::repeat(' ', $maxFrom);
        $hdrCols = implode($sep, array_map(
            fn (BumpType $bumpType): string => $this->visPad(self::ANSI_BOLD . $bumpType->value . self::ANSI_BOLD_OFF, $colW + 2),
            BumpType::cases(),
        ));

        // Focused bump for footer
        $activeEntry = $entries[$upgradePrompt->activeRow] ?? null;

        if ($activeEntry === null) {
            return (string) $this;
        }

        $activeBumps = $activeEntry->availableBumps();
        $focusedBump = $activeBumps !== [] ? ($activeBumps[min($upgradePrompt->activeCol, count($activeBumps) - 1)] ?? null) : null;

        $indent = '  ';

        // Label
        $this->line($indent . self::ANSI_BOLD . $upgradePrompt->label . self::ANSI_RESET);
        $this->line($namePad . $indent . $fromPad . $sep . $hdrCols);

        // Section labels
        $totalWidth = $this->visLen($namePad . $indent . $fromPad . $sep . $hdrCols);
        $prodLabel  = $hasProd ? $this->sectionLabel('require', $totalWidth) : null;
        $devLabel   = $hasDev ? $this->sectionLabel('require-dev', $totalWidth) : null;

        foreach ($entries as $i => $entry) {
            if ($i === 0 && $prodLabel !== null) {
                $this->line($prodLabel);
            }

            if ($i === $firstDevIdx && $devLabel !== null) {
                if ($i > 0) {
                    $this->line('');
                }

                $this->line($devLabel);
            }

            $this->line($this->renderRow($upgradePrompt, $entry, $i, $maxName, $maxFrom, $colW, $nameLabel));
        }

        // Footer: compare URL + abandonment notice
        $footerLines = [];

        if ($focusedBump !== null) {
            $color  = self::BUMP_COLOR[$focusedBump->value];
            $urls   = (new ComposeUrlResolver($activeEntry))->resolve($focusedBump);

            if ($urls->compareUrl !== null) {
                $footerLines[]
                    = $indent . $color . $this->visPad($focusedBump->value, 5) . self::ANSI_RESET
                    . $indent . $this->dimStr('compare') . $indent . self::ANSI_CYAN . $urls->compareUrl . self::ANSI_RESET;
            }

            if ($urls->releaseUrl !== null) {
                $footerLines[]
                    = $indent . $color . $this->visPad('', 5) . self::ANSI_RESET
                    . $indent . $this->dimStr('release') . $indent . self::ANSI_CYAN . $urls->releaseUrl . self::ANSI_RESET;
            }
        }

        if ($activeEntry->abandonedBy !== null) {
            $notice = $activeEntry->abandonedBy !== ''
                ? '⚠ abandoned · use ' . $activeEntry->abandonedBy . ' instead'
                : '⚠ abandoned';
            $footerLines[] = $indent . self::ANSI_YELLOW . $notice . self::ANSI_RESET;
        }

        if ($footerLines !== []) {
            $this->line('');

            foreach ($footerLines as $footerLine) {
                $this->line($footerLine);
            }
        }

        // Help
        $this->line('');
        $this->line($indent . $this->dimStr('↑↓ navigate · ←→ column · space select · enter confirm'));

        return (string) $this;
    }

    /**
     * @param callable(OutdatedPackage):string $nameLabel
     */
    private function renderRow(
        UpgradePrompt $upgradePrompt,
        OutdatedPackage $outdatedPackage,
        int $rowIdx,
        int $maxName,
        int $maxFrom,
        int $colW,
        callable $nameLabel,
    ): string {
        $isActive    = $rowIdx === $upgradePrompt->activeRow;
        $selectedK   = $upgradePrompt->selections[$outdatedPackage->name] ?? null;
        $activeBumps = $isActive ? $outdatedPackage->availableBumps() : [];
        $focusedBump = ($isActive && $activeBumps !== [])
            ? ($activeBumps[min($upgradePrompt->activeCol, count($activeBumps) - 1)] ?? null)
            : null;

        $cursor = $isActive
            ? self::ANSI_BOLD . '❯' . self::ANSI_BOLD_OFF
            : ' ';

        $check = $selectedK !== null
            ? self::BUMP_COLOR[$selectedK->value] . '◉' . self::ANSI_RESET
            : $this->dimStr('◯');

        $label   = $nameLabel($outdatedPackage);
        $nameStr = self::ANSI_BOLD . $outdatedPackage->name . self::ANSI_RESET
            . ($outdatedPackage->abandonedBy !== null
                ? '  ' . self::ANSI_YELLOW . '⚠' . self::ANSI_RESET
                : '')
            . Str::repeat(' ', max(0, $maxName - Str::length($label)));
        $fromStr = $this->dimStr(Str::padRight($outdatedPackage->current, $maxFrom));

        $sep  = $this->dimStr('  │  ');
        $cols = implode($sep, array_map(
            function (BumpType $bumpType) use ($outdatedPackage, $focusedBump, $selectedK, $colW): string {
                $target = $outdatedPackage->target($bumpType);

                if (!$target instanceof VersionTarget) {
                    return $this->visPad('  ' . $this->dimStr('–'), $colW + 2);
                }

                $ver        = $target->version;
                $isFocused  = $bumpType === $focusedBump;
                $isSelected = $bumpType === $selectedK;
                $color      = self::BUMP_COLOR[$bumpType->value];

                $text = match (true) {
                    $isFocused && $isSelected => self::ANSI_BG_BLUE . self::ANSI_WHITE . self::ANSI_BOLD . '◉ ' . $ver . self::ANSI_RESET,
                    $isFocused                => self::ANSI_BG_BLUE . self::ANSI_WHITE . '◯ ' . $ver . self::ANSI_RESET,
                    $isSelected               => self::ANSI_GREEN . self::ANSI_BOLD . '◉' . self::ANSI_RESET . ' ' . $color . $ver . self::ANSI_RESET,
                    default                   => $this->dimStr('◯') . ' ' . $color . $ver . self::ANSI_RESET,
                };

                return $this->visPad($text, $colW + 2);
            },
            BumpType::cases(),
        ));

        return sprintf('%s %s %s  %s%s%s', $cursor, $check, $nameStr, $fromStr, $sep, $cols);
    }

    private function sectionLabel(string $label, int $totalWidth): string
    {
        $dashes = Str::repeat('─', max(0, $totalWidth - Str::length($label) - 7));

        return $this->dimStr(sprintf('  ─── %s %s', $label, $dashes));
    }

    private function dimStr(string $s): string
    {
        return self::ANSI_DIM . $s . self::ANSI_RESET;
    }

    private function visLen(string $s): int
    {
        return Str::length(Str::replace('/\e\[[0-9;]*m/', $s, ''));
    }

    private function visPad(string $s, int $width): string
    {
        return $s . Str::repeat(' ', max(0, $width - $this->visLen($s)));
    }
}
