<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\UI;

use Hpbxxtr\UpgradeInteractive\Core\Str;
use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\Compatibility\ConflictReason;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\Url\ComposeUrlResolver;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;
use Laravel\Prompts\Themes\Default\Renderer;

use function array_map;
use function array_slice;
use function count;
use function implode;
use function in_array;
use function max;
use function min;
use function range;
use function sprintf;

/**
 * @internal
 */
final class UpgradePromptRenderer extends Renderer
{
    private const int CONFLICT_FOOTER_MAX = 5;

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

        ['maxName' => $maxName, 'maxFrom' => $maxFrom, 'colW' => $colW] = $this->computeWidths($entries);

        $firstDevIdx = $this->findFirstDevIndex($entries);
        $hasProd     = $firstDevIdx !== 0;
        $hasDev      = $firstDevIdx !== -1;

        $sep    = $this->separator();
        $indent = '  ';

        $namePad = Str::repeat(' ', 4 + $maxName);
        $fromPad = Str::repeat(' ', $maxFrom);
        $hdrCols = implode($sep, array_map(
            fn (BumpType $bumpType): string => $this->visPad(self::ANSI_BOLD . $bumpType->value . self::ANSI_BOLD_OFF, $colW + 2),
            BumpType::cases(),
        ));

        $activeEntry = $entries[$upgradePrompt->activeRow] ?? null;

        if ($activeEntry === null) {
            return (string) $this;
        }

        $activeBumps = $activeEntry->availableBumps();
        $focusedBump = $activeBumps !== []
            ? ($activeBumps[min($upgradePrompt->activeCol, count($activeBumps) - 1)] ?? null)
            : null;

        // Label + column headers
        $this->line($indent . self::ANSI_BOLD . $upgradePrompt->label . self::ANSI_RESET);
        $this->line($namePad . $indent . $fromPad . $sep . $hdrCols);

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

            $this->line($this->renderRow($upgradePrompt, $entry, $i, $maxName, $maxFrom, $colW));

            if ($upgradePrompt->isPickerActive && $i === $upgradePrompt->activeRow) {
                foreach ($this->renderPickerTable($upgradePrompt) as $pickerLine) {
                    $this->line($pickerLine);
                }
            }
        }

        // Footer: compare/release URLs, abandonment notice, conflict lines
        $footerBump   = $upgradePrompt->isPickerActive ? $upgradePrompt->pickerBumpType : $focusedBump;
        $footerTarget = $this->resolveFooterTarget($upgradePrompt, $activeEntry, $focusedBump);
        $footerLines  = $this->buildFooterLines($activeEntry, $footerBump, $footerTarget, $indent);

        $hoveredVersionRaw = $footerTarget instanceof VersionTarget ? $footerTarget->versionRaw : '';

        if ($hoveredVersionRaw === '' && !$upgradePrompt->isPickerActive && $focusedBump !== null) {
            $hoveredTarget     = $activeEntry->target($focusedBump);
            $hoveredVersionRaw = $hoveredTarget instanceof VersionTarget ? $hoveredTarget->versionRaw : '';
        }

        foreach ($this->collectConflictLines($upgradePrompt, $activeEntry, $hoveredVersionRaw, $indent) as $conflictLine) {
            $footerLines[] = $conflictLine;
        }

        if ($footerLines !== []) {
            $this->line('');

            foreach ($footerLines as $footerLine) {
                $this->line($footerLine);
            }
        }

        // Help
        $this->line('');
        $this->line($indent . $this->dimStr(
            $upgradePrompt->isPickerActive
                ? '↑↓ navigate · ←→ column · space select · esc close'
                : '↑↓ navigate · ←→ column · space select · v versions · enter confirm',
        ));

        return (string) $this;
    }

    private function renderRow(
        UpgradePrompt $upgradePrompt,
        OutdatedPackage $outdatedPackage,
        int $rowIdx,
        int $maxName,
        int $maxFrom,
        int $colW,
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

        $label   = $this->nameLabel($outdatedPackage);
        $nameStr = self::ANSI_BOLD . $outdatedPackage->name . self::ANSI_RESET
            . ($outdatedPackage->abandonedBy !== null ? '  ' . self::ANSI_YELLOW . '⚠' . self::ANSI_RESET : '')
            . Str::repeat(' ', max(0, $maxName - Str::length($label)));
        $fromStr = $this->dimStr(Str::padRight($outdatedPackage->current, $maxFrom));

        $sep = $this->separator();

        if ($isActive && $upgradePrompt->isPickerActive && $upgradePrompt->pickerBumpType instanceof BumpType) {
            $bumpType    = $upgradePrompt->pickerBumpType;
            $pickerLabel = self::BUMP_COLOR[$bumpType->value] . $bumpType->value . ' ▾' . self::ANSI_RESET;

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
                $ver        = $selectedK !== null && $selectedK->column === $bumpType
                    ? $selectedK->target->version
                    : $target->version;
                $versionRaw = $selectedK !== null && $selectedK->column === $bumpType
                    ? $selectedK->target->versionRaw
                    : $target->versionRaw;
                $color      = self::BUMP_COLOR[$bumpType->value];
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

        $headers = [];

        foreach ($columns as $cIdx => $col) {
            $headers[] = $this->visPad($this->dimStr('── ' . $col->label . ' ──'), $colWidths[$cIdx] ?? 0);
        }

        $lines   = [$indent . implode($sep, $headers)];
        $maxRows = max(array_map(static fn (PickerColumn $pickerColumn): int => count($pickerColumn->versions), $columns));

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

                $text = match (true) {
                    $isCursor && !$isCompatible
                        => self::ANSI_BG_BLUE . self::ANSI_WHITE . '▸ ' . $version->version
                            . self::ANSI_RESET . ' ' . self::ANSI_DIM . '✗' . self::ANSI_RESET . $suffix,
                    $isCursor
                        => self::ANSI_BG_BLUE . self::ANSI_WHITE . '▸ ' . $version->version . self::ANSI_RESET . $suffix,
                    !$isCompatible
                        => '  ' . self::ANSI_DIM . $version->version . ' ✗' . self::ANSI_RESET,
                    default
                        => '  ' . $this->dimStr($version->version) . $suffix,
                };

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

    private function nameLabel(OutdatedPackage $outdatedPackage): string
    {
        return $outdatedPackage->abandonedBy !== null
            ? $outdatedPackage->name . '  ⚠'
            : $outdatedPackage->name;
    }

    private function separator(): string
    {
        return $this->dimStr('  │  ');
    }

    private function dimStr(string $s): string
    {
        return self::ANSI_DIM . $s . self::ANSI_RESET;
    }

    /** Wraps $text in an OSC 8 hyperlink so supporting terminals make it clickable. */
    private function hyperlink(string $url, string $text): string
    {
        return "\e]8;;" . $url . "\e\\" . $text . "\e]8;;\e\\";
    }

    private function visLen(string $s): int
    {
        return Str::length(Str::replace('/\e\[[0-9;]*m/', $s, ''));
    }

    private function visPad(string $s, int $width): string
    {
        return $s . Str::repeat(' ', max(0, $width - $this->visLen($s)));
    }

    /**
     * @param list<OutdatedPackage> $entries
     * @return array{maxName: int, maxFrom: int, colW: int}
     */
    private function computeWidths(array $entries): array
    {
        $nameLengths = array_map(fn (OutdatedPackage $outdatedPackage): int => Str::length($this->nameLabel($outdatedPackage)), $entries);
        $fromLengths = array_map(static fn (OutdatedPackage $outdatedPackage): int => Str::length($outdatedPackage->current), $entries);
        $colWidths   = array_map(
            static fn (OutdatedPackage $outdatedPackage): int => max(
                0,
                $outdatedPackage->patch instanceof VersionTarget ? Str::length($outdatedPackage->patch->version) : 0,
                $outdatedPackage->minor instanceof VersionTarget ? Str::length($outdatedPackage->minor->version) : 0,
                $outdatedPackage->major instanceof VersionTarget ? Str::length($outdatedPackage->major->version) : 0,
            ),
            $entries,
        );

        return [
            'maxName' => max(0, ...$nameLengths),
            'maxFrom' => max(0, ...$fromLengths),
            'colW'    => max(5, ...$colWidths),
        ];
    }

    /**
     * @param list<OutdatedPackage> $entries
     */
    private function findFirstDevIndex(array $entries): int
    {
        foreach ($entries as $i => $entry) {
            if ($entry->isDev) {
                return $i;
            }
        }

        return -1;
    }

    private function resolveFooterTarget(
        UpgradePrompt $upgradePrompt,
        OutdatedPackage $outdatedPackage,
        ?BumpType $bumpType,
    ): ?VersionTarget {
        if ($upgradePrompt->isPickerActive) {
            $cursorCol = $upgradePrompt->pickerColumns[$upgradePrompt->pickerCol] ?? null;

            return $cursorCol instanceof PickerColumn
                ? ($cursorCol->versions[$upgradePrompt->pickerRow] ?? null)
                : null;
        }

        $selection = $upgradePrompt->selections[$outdatedPackage->name] ?? null;

        return ($selection !== null && $selection->column === $bumpType)
            ? $selection->target
            : null;
    }

    /**
     * Builds the URL lines (compare/release) and abandonment notice for the footer.
     *
     * @return list<string>
     */
    private function buildFooterLines(
        OutdatedPackage $outdatedPackage,
        ?BumpType $bumpType,
        ?VersionTarget $versionTarget,
        string $indent,
    ): array {
        $lines = [];

        if ($bumpType instanceof BumpType) {
            $color = self::BUMP_COLOR[$bumpType->value];
            $urls  = (new ComposeUrlResolver($outdatedPackage))->resolve($bumpType, $versionTarget);

            if ($urls->compareUrl !== null) {
                $lines[] = $indent . $color . $this->visPad($bumpType->value, 5) . self::ANSI_RESET
                    . $indent . $this->dimStr('compare') . $indent . self::ANSI_CYAN . $this->hyperlink($urls->compareUrl, $urls->compareUrl) . self::ANSI_RESET;
            }

            if ($urls->releaseUrl !== null) {
                $lines[] = $indent . $color . $this->visPad('', 5) . self::ANSI_RESET
                    . $indent . $this->dimStr('release') . $indent . self::ANSI_CYAN . $this->hyperlink($urls->releaseUrl, $urls->releaseUrl) . self::ANSI_RESET;
            }
        }

        if ($outdatedPackage->abandonedBy !== null) {
            $notice  = $outdatedPackage->abandonedBy !== ''
                ? '⚠ abandoned · use ' . $outdatedPackage->abandonedBy . ' instead'
                : '⚠ abandoned';
            $lines[] = $indent . self::ANSI_YELLOW . $notice . self::ANSI_RESET;
        }

        return $lines;
    }

    /**
     * Collects all conflict footer lines via two phases:
     *   Phase 1 — static conflicts for other selected packages (active entry excluded).
     *   Phase 2 — hover conflicts for the hovered version of the active entry.
     * Deduplicates across phases and caps output at CONFLICT_FOOTER_MAX lines.
     *
     * @return list<string>
     */
    private function collectConflictLines(
        UpgradePrompt $upgradePrompt,
        OutdatedPackage $outdatedPackage,
        string $hoveredVersionRaw,
        string $indent,
    ): array {
        /** @var list<string> $lines */
        $lines = [];
        /** @var array<string, true> $seen */
        $seen = [];

        $updatableNames = array_map(
            static fn (OutdatedPackage $outdatedPackage): string => $outdatedPackage->name,
            $upgradePrompt->entries,
        );

        // Phase 1: conflicts for selected packages other than the active entry,
        // filtered to exclude any conflict that mentions the active entry.
        foreach ($upgradePrompt->selections as $selPkgName => $selection) {
            if ($selection === null) {
                continue;
            }
            if ($selPkgName === $outdatedPackage->name) {
                continue;
            }
            foreach ($upgradePrompt->conflictMap->conflictsFor($selPkgName, $selection->target->versionRaw) as $reason) {
                if ($reason->dependentPackage === $outdatedPackage->name) {
                    continue;
                }
                if ($reason->requiredPackage === $outdatedPackage->name) {
                    continue;
                }
                $line = $this->formatConflictLine($reason, $selPkgName, $updatableNames, $indent);

                if (!isset($seen[$line])) {
                    $seen[$line] = true;
                    $lines[]     = $line;
                }
            }
        }

        // Phase 2: conflicts for the hovered version of the active entry.
        if ($hoveredVersionRaw !== '') {
            foreach ($upgradePrompt->conflictMap->conflictsFor($outdatedPackage->name, $hoveredVersionRaw) as $reason) {
                $line = $this->formatConflictLine($reason, $outdatedPackage->name, $updatableNames, $indent);

                if (!isset($seen[$line])) {
                    $seen[$line] = true;
                    $lines[]     = $line;
                }
            }
        }

        $overflowCount = count($lines) - self::CONFLICT_FOOTER_MAX;

        if ($overflowCount > 0) {
            $lines   = array_slice($lines, 0, self::CONFLICT_FOOTER_MAX);
            $lines[] = $indent . $this->dimStr(
                '… and ' . $overflowCount . ' more conflict' . ($overflowCount > 1 ? 's' : ''),
            );
        }

        return $lines;
    }

    /**
     * Format a single conflict footer line, adapting the suffix to whether the conflict involves
     * an installed-only package (as opposed to a cross-selection conflict).
     *
     * @param list<string> $updatableNames package names that appear in the outdated-packages list
     */
    private function formatConflictLine(
        ConflictReason $conflictReason,
        string $forPackage,
        array $updatableNames,
        string $indent,
    ): string {
        $prefix = $indent . self::ANSI_YELLOW . '! ' . $conflictReason->format();

        if (!$conflictReason->isInstalled) {
            // Case 1: cross-selection conflict (both packages are user selections)
            return $prefix . ' — selected: ' . $conflictReason->selectedVersion . self::ANSI_RESET;
        }

        if ($conflictReason->dependentPackage === $forPackage) {
            // Case 2: forward — candidate requires a dep that is installed-only
            $isDepUpdatable = in_array($conflictReason->requiredPackage, $updatableNames, true);
            $suffix         = $isDepUpdatable
                ? ' — installed: ' . $conflictReason->selectedVersion . ' (update available)'
                : ' — installed: ' . $conflictReason->selectedVersion;

            return $prefix . $suffix . self::ANSI_RESET;
        }

        // Case 3: backward — an installed package requires the candidate at a conflicting constraint
        $isDependentUpdatable = in_array($conflictReason->dependentPackage, $updatableNames, true);
        $suffix               = $isDependentUpdatable
            ? ' — installed, update available'
            : ' — installed, no update available';

        return $prefix . $suffix . self::ANSI_RESET;
    }
}
