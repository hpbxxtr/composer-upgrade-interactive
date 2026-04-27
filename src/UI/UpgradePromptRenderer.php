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
use function range;
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

            // Render inline picker rows immediately after the active row
            if ($upgradePrompt->isPickerActive && $i === $upgradePrompt->activeRow) {
                foreach ($this->renderPickerTable($upgradePrompt) as $line) {
                    $this->line($line);
                }
            }
        }

        // Footer: compare URL + abandonment notice
        $footerLines = [];

        $footerBump = $upgradePrompt->isPickerActive ? $upgradePrompt->pickerBumpType : $focusedBump;

        if ($upgradePrompt->isPickerActive) {
            $cursorCol    = $upgradePrompt->pickerColumns[$upgradePrompt->pickerCol] ?? null;
            $footerTarget = $cursorCol instanceof PickerColumn
                ? ($cursorCol->versions[$upgradePrompt->pickerRow] ?? null)
                : null;
        } else {
            $selection    = $upgradePrompt->selections[$activeEntry->name] ?? null;
            $footerTarget = ($selection !== null && $selection->column === $focusedBump)
                ? $selection->target
                : null;
        }

        if ($footerBump instanceof BumpType) {
            $color = self::BUMP_COLOR[$footerBump->value];
            $urls  = (new ComposeUrlResolver($activeEntry))->resolve($footerBump, $footerTarget);

            if ($urls->compareUrl !== null) {
                $footerLines[]
                    = $indent . $color . $this->visPad($footerBump->value, 5) . self::ANSI_RESET
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

        $activeSelection        = $upgradePrompt->selections[$activeEntry->name] ?? null;
        $activeSelectionVersion = $activeSelection?->target->versionRaw ?? '';

        foreach ($upgradePrompt->conflictMap->conflictsFor($activeEntry->name, $activeSelectionVersion) as $reason) {
            $footerLines[] = $indent . self::ANSI_YELLOW
                . '! ' . $reason->dependentPackage . ' ' . $reason->dependentVersion
                . ' requires ' . $reason->requiredPackage . ' ' . $reason->requiredConstraint
                . ' — selected: ' . $reason->selectedVersion
                . self::ANSI_RESET;
        }

        if ($footerLines !== []) {
            $this->line('');

            foreach ($footerLines as $footerLine) {
                $this->line($footerLine);
            }
        }

        // Help
        $this->line('');

        if ($upgradePrompt->isPickerActive) {
            $this->line($indent . $this->dimStr('↑↓ navigate · ←→ column · space select · esc close'));
        } else {
            $this->line($indent . $this->dimStr('↑↓ navigate · ←→ column · space select · v versions · enter confirm'));
        }

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
            ? self::BUMP_COLOR[$selectedK->column->value] . '◉' . self::ANSI_RESET
            : $this->dimStr('◯');

        $label   = $nameLabel($outdatedPackage);
        $nameStr = self::ANSI_BOLD . $outdatedPackage->name . self::ANSI_RESET
            . ($outdatedPackage->abandonedBy !== null
                ? '  ' . self::ANSI_YELLOW . '⚠' . self::ANSI_RESET
                : '')
            . Str::repeat(' ', max(0, $maxName - Str::length($label)));
        $fromStr = $this->dimStr(Str::padRight($outdatedPackage->current, $maxFrom));

        $sep = $this->dimStr('  │  ');

        // If the picker is open for this row, render a compact header instead of the column grid
        if ($isActive && $upgradePrompt->isPickerActive && $upgradePrompt->pickerBumpType instanceof \Hpbxxtr\UpgradeInteractive\Resolver\BumpType) {
            $bumpType    = $upgradePrompt->pickerBumpType;
            $color       = self::BUMP_COLOR[$bumpType->value];
            $pickerLabel = $color . $bumpType->value . ' ▾' . self::ANSI_RESET;

            return sprintf('%s %s %s  %s%s%s', $cursor, $check, $nameStr, $fromStr, $sep, $pickerLabel);
        }

        $cols = implode($sep, array_map(
            function (BumpType $bumpType) use ($upgradePrompt, $outdatedPackage, $focusedBump, $selectedK, $colW): string {
                $target = $outdatedPackage->target($bumpType);

                if (!$target instanceof VersionTarget) {
                    return $this->visPad('  ' . $this->dimStr('–'), $colW + 2);
                }

                $isFocused  = $bumpType === $focusedBump;
                $isSelected = $bumpType === $selectedK?->column;
                // Show the picker-selected version if it differs from the package's default target
                $ver        = $selectedK !== null && $selectedK->column === $bumpType
                    ? $selectedK->target->version
                    : $target->version;
                $color      = self::BUMP_COLOR[$bumpType->value];

                $versionRaw   = $selectedK !== null && $selectedK->column === $bumpType
                    ? $selectedK->target->versionRaw
                    : $target->versionRaw;
                $isCompatible = $upgradePrompt->conflictMap->isCompatible($outdatedPackage->name, $versionRaw);

                $text = match (true) {
                    $isFocused && $isSelected && !$isCompatible
                        => self::ANSI_BG_BLUE . self::ANSI_WHITE . self::ANSI_BOLD . '◉!' . $ver . self::ANSI_RESET,
                    $isFocused && $isSelected
                        => self::ANSI_BG_BLUE . self::ANSI_WHITE . self::ANSI_BOLD . '◉ ' . $ver . self::ANSI_RESET,
                    $isFocused && !$isCompatible
                        => self::ANSI_BG_BLUE . self::ANSI_WHITE . '◯!' . $ver . self::ANSI_RESET,
                    $isFocused
                        => self::ANSI_BG_BLUE . self::ANSI_WHITE . '◯ ' . $ver . self::ANSI_RESET,
                    $isSelected && !$isCompatible
                        => self::ANSI_GREEN . self::ANSI_BOLD . '◉' . self::ANSI_RESET . self::ANSI_YELLOW . '!' . self::ANSI_RESET . $color . $ver . self::ANSI_RESET,
                    $isSelected
                        => self::ANSI_GREEN . self::ANSI_BOLD . '◉' . self::ANSI_RESET . ' ' . $color . $ver . self::ANSI_RESET,
                    !$isCompatible
                        => $this->dimStr('◯') . self::ANSI_YELLOW . '!' . self::ANSI_RESET . $color . $ver . self::ANSI_RESET,
                    default
                        => $this->dimStr('◯') . ' ' . $color . $ver . self::ANSI_RESET,
                };

                return $this->visPad($text, $colW + 2);
            },
            BumpType::cases(),
        ));

        return sprintf('%s %s %s  %s%s%s', $cursor, $check, $nameStr, $fromStr, $sep, $cols);
    }

    /** @return list<string> */
    private function renderPickerTable(UpgradePrompt $upgradePrompt): array
    {
        $columns = $upgradePrompt->pickerColumns;

        if ($columns === []) {
            return [];
        }

        $indent = '        ';
        $sep    = ' ' . $this->dimStr('|') . ' ';

        $lastColIdx   = count($columns) - 1;
        $latestSuffix = $this->dimStr('  (latest)');
        $latestLen    = Str::length('  (latest)');

        /** @var list<int> $colWidths */
        $colWidths = [];

        foreach ($columns as $cIdx => $col) {
            $headerLen = Str::length('── ' . $col->label . ' ──');
            $maxVerLen = 0;

            foreach ($col->versions as $v) {
                $len = Str::length($v->version);

                if ($len > $maxVerLen) {
                    $maxVerLen = $len;
                }
            }

            $cellWidth = 2 + $maxVerLen;

            if ($cIdx === $lastColIdx && isset($col->versions[0])) {
                $cellWidth = max($cellWidth, 2 + Str::length($col->versions[0]->version) + $latestLen);
            }

            $colWidths[] = max($headerLen, $cellWidth);
        }

        $lines = [];

        $headers = [];

        foreach ($columns as $cIdx => $col) {
            $colWidth  = $colWidths[$cIdx] ?? 0;
            $headers[] = $this->visPad($this->dimStr('── ' . $col->label . ' ──'), $colWidth);
        }

        $lines[] = $indent . implode($sep, $headers);

        $maxRows = 0;

        foreach ($columns as $col) {
            $count = count($col->versions);

            if ($count > $maxRows) {
                $maxRows = $count;
            }
        }

        foreach (range(0, $maxRows - 1) as $rowIdx) {
            $cells = [];

            foreach ($columns as $cIdx => $col) {
                $colWidth = $colWidths[$cIdx] ?? 0;
                $version  = $col->versions[$rowIdx] ?? null;

                if (!$version instanceof VersionTarget) {
                    $cells[] = Str::repeat(' ', $colWidth);

                    continue;
                }

                $isCursor     = $cIdx === $upgradePrompt->pickerCol && $rowIdx === $upgradePrompt->pickerRow;
                $suffix       = ($cIdx === $lastColIdx && $rowIdx === 0) ? $latestSuffix : '';
                $isCompatible = $upgradePrompt->pickerVersionCompatibility[$version->versionRaw] ?? true;

                if ($isCursor && !$isCompatible) {
                    $text = self::ANSI_BG_BLUE . self::ANSI_WHITE . '▸ ' . $version->version
                        . self::ANSI_RESET . ' ' . self::ANSI_DIM . '✗' . self::ANSI_RESET . $suffix;
                } elseif ($isCursor) {
                    $text = self::ANSI_BG_BLUE . self::ANSI_WHITE . '▸ ' . $version->version . self::ANSI_RESET . $suffix;
                } elseif (!$isCompatible) {
                    $text = '  ' . self::ANSI_DIM . $version->version . ' ✗' . self::ANSI_RESET;
                } else {
                    $text = '  ' . $this->dimStr($version->version) . $suffix;
                }

                $cells[] = $this->visPad($text, $colWidth);
            }

            $lines[] = $indent . implode($sep, $cells);
        }

        return $lines;
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
