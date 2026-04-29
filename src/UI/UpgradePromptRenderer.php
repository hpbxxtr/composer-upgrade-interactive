<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\UI;

use Hpbxxtr\UpgradeInteractive\Core\Str;
use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\Compatibility\ConflictReason;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\Url\ComposeUrlResolver;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;
use Laravel\Prompts\Prompt;
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

    private AnsiFormatter $formatter;

    public function __construct(Prompt $prompt)
    {
        parent::__construct($prompt);
        $this->formatter = new AnsiFormatter();
    }

    public function __invoke(UpgradePrompt $upgradePrompt): string
    {
        if ($upgradePrompt->state === 'submit') {
            $this->line("  " . $this->formatter->submitCheck() . " Done");

            return (string) $this;
        }

        $entries = $upgradePrompt->entries;

        ['maxName' => $maxName, 'maxFrom' => $maxFrom, 'colW' => $colW] = $this->computeWidths($entries);

        $firstDevIdx = $this->findFirstDevIndex($entries);
        $hasProd     = $firstDevIdx !== 0;
        $hasDev      = $firstDevIdx !== -1;

        $sep    = $this->formatter->separator();
        $indent = '  ';

        $namePad = Str::repeat(' ', 4 + $maxName);
        $fromPad = Str::repeat(' ', $maxFrom);
        $hdrCols = implode($sep, array_map(
            fn (BumpType $bumpType): string => $this->formatter->visPad($this->formatter->columnHeader($bumpType->value), $colW + 2),
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
        $this->line($indent . $this->formatter->promptLabel($upgradePrompt->label));
        $this->line($namePad . $indent . $fromPad . $sep . $hdrCols);

        $totalWidth = $this->formatter->visLen($namePad . $indent . $fromPad . $sep . $hdrCols);
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
        $this->line($indent . $this->formatter->dim(
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

        $cursor = $this->formatter->cursor($isActive);
        $check  = $selectedK !== null
            ? $this->formatter->selectedMark($selectedK->column)
            : $this->formatter->unselectedMark();

        $label   = $this->nameLabel($outdatedPackage);
        $nameStr = $this->formatter->bold($outdatedPackage->name)
            . ($outdatedPackage->abandonedBy !== null ? '  ' . $this->formatter->abandonedIcon() : '')
            . Str::repeat(' ', max(0, $maxName - Str::length($label)));
        $fromStr = $this->formatter->dim(Str::padRight($outdatedPackage->current, $maxFrom));

        $sep = $this->formatter->separator();

        if ($isActive && $upgradePrompt->isPickerActive && $upgradePrompt->pickerBumpType instanceof BumpType) {
            $pickerLabel = $this->formatter->pickerExpandLabel($upgradePrompt->pickerBumpType);

            return sprintf('%s %s %s  %s%s%s', $cursor, $check, $nameStr, $fromStr, $sep, $pickerLabel);
        }

        $cols = implode($sep, array_map(
            function (BumpType $bumpType) use ($upgradePrompt, $outdatedPackage, $focusedBump, $selectedK, $colW): string {
                $target = $outdatedPackage->target($bumpType);

                if (!$target instanceof VersionTarget) {
                    return $this->formatter->visPad('  ' . $this->formatter->dim('–'), $colW + 2);
                }

                $isFocused  = $bumpType === $focusedBump;
                $isSelected = $bumpType === $selectedK?->column;
                $ver        = $selectedK !== null && $selectedK->column === $bumpType
                    ? $selectedK->target->version
                    : $target->version;
                $versionRaw = $selectedK !== null && $selectedK->column === $bumpType
                    ? $selectedK->target->versionRaw
                    : $target->versionRaw;
                $isCompatible = $upgradePrompt->conflictMap->isCompatible($outdatedPackage->name, $versionRaw);

                return $this->formatter->visPad(
                    $this->formatter->versionCell($isFocused, $isSelected, $isCompatible, $ver, $bumpType),
                    $colW + 2,
                );
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
        $sep    = ' ' . $this->formatter->dim('|') . ' ';

        $lastColIdx   = count($columns) - 1;
        $latestSuffix = $this->formatter->dim('  (latest)');
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
            $headers[] = $this->formatter->visPad($this->formatter->dim('── ' . $col->label . ' ──'), $colWidths[$cIdx] ?? 0);
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

                $cells[] = $this->formatter->visPad(
                    $this->formatter->pickerRow($isCursor, $isCompatible, $version->version, $suffix),
                    $colWidth,
                );
            }

            $lines[] = $indent . implode($sep, $cells);
        }

        return $lines;
    }

    private function sectionLabel(string $label, int $totalWidth): string
    {
        $dashes = Str::repeat('─', max(0, $totalWidth - Str::length($label) - 7));

        return $this->formatter->dim(sprintf('  ─── %s %s', $label, $dashes));
    }

    private function nameLabel(OutdatedPackage $outdatedPackage): string
    {
        return $outdatedPackage->abandonedBy !== null
            ? $outdatedPackage->name . '  ⚠'
            : $outdatedPackage->name;
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
            $urls = (new ComposeUrlResolver($outdatedPackage))->resolve($bumpType, $versionTarget);

            if ($urls->compareUrl !== null) {
                $lines[] = $indent . $this->formatter->bumpColor($bumpType, $this->formatter->visPad($bumpType->value, 5))
                    . $indent . $this->formatter->footerLabel('compare')
                    . $indent . $this->formatter->footerLink($urls->compareUrl);
            }

            if ($urls->releaseUrl !== null) {
                $lines[] = $indent . $this->formatter->bumpColor($bumpType, $this->formatter->visPad('', 5))
                    . $indent . $this->formatter->footerLabel('release')
                    . $indent . $this->formatter->footerLink($urls->releaseUrl);
            }
        }

        if ($outdatedPackage->abandonedBy !== null) {
            $notice  = $outdatedPackage->abandonedBy !== ''
                ? '⚠ abandoned · use ' . $outdatedPackage->abandonedBy . ' instead'
                : '⚠ abandoned';
            $lines[] = $indent . $this->formatter->warning($notice);
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
            $lines[] = $indent . $this->formatter->dim(
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
        $base = '! ' . $conflictReason->format();

        if (!$conflictReason->isInstalled) {
            // Case 1: cross-selection conflict (both packages are user selections)
            return $indent . $this->formatter->warning($base . ' — selected: ' . $conflictReason->selectedVersion);
        }

        if ($conflictReason->dependentPackage === $forPackage) {
            // Case 2: forward — candidate requires a dep that is installed-only
            $isDepUpdatable = in_array($conflictReason->requiredPackage, $updatableNames, true);
            $suffix         = $isDepUpdatable
                ? ' — installed: ' . $conflictReason->selectedVersion . ' (update available)'
                : ' — installed: ' . $conflictReason->selectedVersion;

            return $indent . $this->formatter->warning($base . $suffix);
        }

        // Case 3: backward — an installed package requires the candidate at a conflicting constraint
        $isDependentUpdatable = in_array($conflictReason->dependentPackage, $updatableNames, true);
        $suffix               = $isDependentUpdatable
            ? ' — installed, update available'
            : ' — installed, no update available';

        return $indent . $this->formatter->warning($base . $suffix);
    }
}
