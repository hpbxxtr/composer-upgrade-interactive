<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\UI;

use Hpbxxtr\UpgradeInteractive\Resolver\AvailableVersionsResolverInterface;
use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\Compatibility\CompatibilityCheckerInterface;
use Hpbxxtr\UpgradeInteractive\Resolver\Compatibility\ConflictMap;
use Hpbxxtr\UpgradeInteractive\Resolver\Compatibility\ConflictReason;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionSelection;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Override;

use UnexpectedValueException;
use function array_fill_keys;
use function array_filter;
use function array_map;
use function array_reverse;
use function array_values;
use function count;
use function explode;
use function in_array;
use function max;
use function min;

/**
 * @internal
 */
final class UpgradePrompt extends Prompt
{
    public int $activeRow = 0;

    public int $activeCol = 0;

    /**
     * @var array<string, VersionSelection|null>
     */
    public array $selections;

    public bool $isPickerActive = false;

    public bool $isLoading = false;

    /** @var list<PickerColumn> */
    public array $pickerColumns = [];

    public int $pickerCol = 0;

    public int $pickerRow = 0;

    public ?BumpType $pickerBumpType = null;

    public ConflictMap $conflictMap;

    /** @var array<string, bool> versionRaw → isCompatible */
    public array $pickerVersionCompatibility = [];

    private ?string $pickerPackageName = null;

    private ?VersionSelection $versionSelection = null;

    /** @var array<string, list<VersionTarget>> key: "packageName|bumpType" */
    private array $versionCache = [];

    /**
     * @param list<OutdatedPackage> $entries
     */
    public function __construct(
        public readonly array                                $entries,
        public readonly string                               $label = 'Select versions to update',
        private readonly ?AvailableVersionsResolverInterface $availableVersionsResolver = null,
        private readonly ?CompatibilityCheckerInterface      $compatibilityChecker = null,
        ?ConflictMap                        $initialConflictMap = null,
    ) {
        self::$themes['default'][self::class] = UpgradePromptRenderer::class;

        $this->required = false;
        $this->conflictMap = $initialConflictMap ?? ConflictMap::empty();

        $this->selections = array_fill_keys(
            array_map(static fn (OutdatedPackage $outdatedPackage): string => $outdatedPackage->name, $entries),
            null,
        );

        $this->on('key', fn (string $key) => $this->handleKey($key));
    }

    /**
     * @return array<string, string> package name => raw version tag
     */
    #[Override]
    public function value(): array
    {
        $result = [];

        foreach ($this->entries as $entry) {
            $sel = $this->selections[$entry->name] ?? null;

            if ($sel === null) {
                continue;
            }

            $result[$entry->name] = $sel->target->versionRaw;
        }

        return $result;
    }

    private function handleKey(string $key): void
    {
        if ($this->isPickerActive) {
            $this->handlePickerKey($key);

            return;
        }

        match (true) {
            in_array($key, [Key::UP, Key::UP_ARROW, Key::CTRL_P], true)       => $this->moveRow(-1),
            in_array($key, [Key::DOWN, Key::DOWN_ARROW, Key::CTRL_N], true)   => $this->moveRow(1),
            in_array($key, [Key::LEFT, Key::LEFT_ARROW, Key::CTRL_B], true)   => $this->moveCol(-1),
            in_array($key, [Key::RIGHT, Key::RIGHT_ARROW, Key::CTRL_F], true) => $this->moveCol(1),
            $key === Key::SPACE                                                => $this->toggleSelection(),
            $key === 'v'                                                       => $this->openPicker(),
            $key === "\n" || $key === "\r"                                     => $this->submit(),
            $key === Key::CTRL_C                                               => $this->cancelPrompt(),
            default                                                            => false, // ignore unrecognised keys
        };
    }

    private function handlePickerKey(string $key): void
    {
        match (true) {
            in_array($key, [Key::UP, Key::UP_ARROW, Key::CTRL_P], true)       => $this->pickerNavigate(-1),
            in_array($key, [Key::DOWN, Key::DOWN_ARROW, Key::CTRL_N], true)   => $this->pickerNavigate(1),
            in_array($key, [Key::RIGHT, Key::RIGHT_ARROW, Key::CTRL_F], true) => $this->pickerMoveCol(1),
            in_array($key, [Key::LEFT, Key::LEFT_ARROW, Key::CTRL_B], true)   => $this->pickerMoveCol(-1),
            $key === Key::SPACE                                                => $this->pickerSelect(),
            $key === Key::ESCAPE                                               => $this->pickerCancel(),
            $key === "\n" || $key === "\r"                                     => $this->submit(),
            $key === Key::CTRL_C                                               => $this->cancelPrompt(),
            default                                                            => false,
        };
    }

    private function moveRow(int $direction): void
    {
        $this->activeRow = max(0, min(count($this->entries) - 1, $this->activeRow + $direction));
        $this->activeCol = 0;
    }

    private function moveCol(int $direction): void
    {
        $bumps = ($this->entries[$this->activeRow] ?? null)?->availableBumps() ?? [];

        if ($bumps === []) {
            return;
        }

        $count           = count($bumps);
        $this->activeCol = ($this->activeCol + $direction + $count) % $count;
    }

    private function toggleSelection(): void
    {
        $entry = $this->entries[$this->activeRow] ?? null;

        if ($entry === null) {
            return;
        }

        $bumps = $entry->availableBumps();

        if ($bumps === []) {
            return;
        }

        $col     = $bumps[min($this->activeCol, count($bumps) - 1)] ?? null;
        $current = $this->selections[$entry->name] ?? null;

        if ($col === null) {
            return;
        }

        if ($current !== null && $current->column === $col) {
            $this->selections[$entry->name] = null;
        } else {
            $target = $entry->target($col);

            if ($target === null) {
                return;
            }

            $this->selections[$entry->name] = new VersionSelection($col, $target);
        }

        $this->recomputeConflictMap();
    }

    private function openPicker(): void
    {
        if (!$this->availableVersionsResolver instanceof AvailableVersionsResolverInterface) {
            return;
        }

        $entry = $this->entries[$this->activeRow] ?? null;

        if ($entry === null) {
            return;
        }

        $bumps = $entry->availableBumps();

        if ($bumps === []) {
            return;
        }

        $bumpType = $bumps[min($this->activeCol, count($bumps) - 1)] ?? null;

        if ($bumpType === null) {
            return;
        }

        $cacheKey = $entry->name . '|' . $bumpType->value;

        if (!isset($this->versionCache[$cacheKey])) {
            $this->isLoading = true;
            $this->render();

            try {
                $this->versionCache[$cacheKey] = $this->availableVersionsResolver->resolve($entry->name, $entry->currentRaw, $bumpType);
            } finally {
                $this->isLoading = false;
            }
        }

        $versions = $this->versionCache[$cacheKey];

        if ($versions === []) {
            return;
        }

        $existing    = $this->selections[$entry->name] ?? null;
        $pickerCols  = $this->buildPickerColumns($bumpType, $versions);

        $this->pickerColumns     = $pickerCols;
        $this->pickerBumpType    = $bumpType;
        $this->pickerPackageName = $entry->name;
        $this->versionSelection  = $existing;

        [$this->pickerCol, $this->pickerRow] = ($existing !== null && $existing->column === $bumpType)
            ? $this->findVersionPosition($pickerCols, $existing->target->versionRaw)
            : $this->defaultPosition($pickerCols);

        if ($this->compatibilityChecker instanceof CompatibilityCheckerInterface) {
            /** @var array<string, VersionSelection> $otherSelections */
            $otherSelections = array_filter(
                $this->selections,
                static fn (?VersionSelection $versionSelection, string $k): bool => $versionSelection instanceof VersionSelection && $k !== $entry->name,
                ARRAY_FILTER_USE_BOTH,
            );

            $this->pickerVersionCompatibility = [];

            /** @var array<string, list<ConflictReason>> $pickerConflictData */
            $pickerConflictData = [];

            foreach ($versions as $version) {
                try {
                    $pickerConflicts = $this->compatibilityChecker->checkCandidate($entry->name, $version, $otherSelections);
                } catch (UnexpectedValueException) {
                    $pickerConflicts = [];
                }

                $this->pickerVersionCompatibility[$version->versionRaw] = ($pickerConflicts === []);
                $pickerConflictData[$version->versionRaw]                = $pickerConflicts;
            }

            $this->conflictMap = $this->conflictMap->withMerged([$entry->name => $pickerConflictData]);
        }

        $this->isPickerActive = true;
    }

    private function pickerMoveCol(int $direction): void
    {
        $newCol = $this->pickerCol + $direction;

        if ($newCol < 0) {
            $this->pickerCancel();

            return;
        }

        if (!isset($this->pickerColumns[$newCol])) {
            return;
        }

        $this->pickerCol = $newCol;
        $this->pickerRow = min($this->pickerRow, count($this->pickerColumns[$newCol]->versions) - 1);
    }

    private function pickerNavigate(int $direction): void
    {
        $col = $this->pickerColumns[$this->pickerCol] ?? null;

        if ($col === null) {
            return;
        }

        $newRow = $this->pickerRow + $direction;

        if (isset($col->versions[$newRow])) {
            $this->pickerRow = $newRow;
        }
    }

    private function pickerSelect(): void
    {
        $col = $this->pickerColumns[$this->pickerCol] ?? null;

        if (!$col instanceof PickerColumn) {
            return;
        }

        $row = $col->versions[$this->pickerRow] ?? null;

        if (!$row instanceof VersionTarget) {
            return;
        }

        if (!$this->pickerBumpType instanceof BumpType || $this->pickerPackageName === null) {
            return;
        }

        $this->selections[$this->pickerPackageName] = new VersionSelection($this->pickerBumpType, $row);
        $this->closePicker();
        $this->recomputeConflictMap();
    }

    private function pickerCancel(): void
    {
        if ($this->pickerPackageName !== null) {
            $this->selections[$this->pickerPackageName] = $this->versionSelection;
        }

        $this->closePicker();
    }

    private function closePicker(): void
    {
        $this->isPickerActive    = false;
        $this->pickerColumns     = [];
        $this->pickerCol         = 0;
        $this->pickerRow         = 0;
        $this->pickerBumpType    = null;
        $this->pickerPackageName = null;
        $this->versionSelection  = null;
    }

    private function cancelPrompt(): void
    {
        $this->closePicker();

        $this->selections = array_fill_keys(
            array_map(static fn (OutdatedPackage $outdatedPackage): string => $outdatedPackage->name, $this->entries),
            null,
        );

        $this->submit();
    }

    private function recomputeConflictMap(): void
    {
        if (!$this->compatibilityChecker instanceof CompatibilityCheckerInterface) {
            return;
        }

        /** @var array<string, array<string, list<ConflictReason>>> $data */
        $data = [];

        foreach ($this->entries as $entry) {
            $name = $entry->name;

            /** @var array<string, VersionSelection> $otherSelections */
            $otherSelections = array_filter(
                $this->selections,
                static fn (?VersionSelection $versionSelection, string $k): bool => $versionSelection instanceof VersionSelection && $k !== $name,
                ARRAY_FILTER_USE_BOTH,
            );

            $targets = array_values(array_filter([$entry->patch, $entry->minor, $entry->major]));

            // Include the currently selected version if it came from the picker and differs from all defaults
            $selected = $this->selections[$name] ?? null;

            if ($selected !== null) {
                $isAlreadyIncluded = false;

                foreach ($targets as $target) {
                    if ($target->versionRaw === $selected->target->versionRaw) {
                        $isAlreadyIncluded = true;

                        break;
                    }
                }

                if (!$isAlreadyIncluded) {
                    $targets[] = $selected->target;
                }
            }

            foreach ($targets as $target) {
                try {
                    $conflicts = $this->compatibilityChecker->checkCandidate($name, $target, $otherSelections);
                } catch (UnexpectedValueException) {
                    $conflicts = [];
                }

                $data[$name][$target->versionRaw] = $conflicts;
            }
        }

        $this->conflictMap = new ConflictMap($data);
    }

    /**
     * @param list<VersionTarget> $versions newest-first
     * @return list<PickerColumn>
     */
    private function buildPickerColumns(BumpType $bumpType, array $versions): array
    {
        $columns      = [];
        $currentLabel = null;
        $currentVers  = [];

        foreach ($versions as $version) {
            $parts = explode('.', $version->version);
            $label = $bumpType === BumpType::Major
                ? $parts[0] . '.x'
                : $parts[0] . '.' . ($parts[1] ?? '?') . '.x';

            if ($label !== $currentLabel) {
                if ($currentLabel !== null) {
                    $columns[] = new PickerColumn($currentLabel, $currentVers);
                }

                $currentLabel = $label;
                $currentVers  = [];
            }

            $currentVers[] = $version;
        }

        if ($currentLabel !== null) {
            $columns[] = new PickerColumn($currentLabel, $currentVers);
        }

        return array_reverse($columns);
    }

    /**
     * @param list<PickerColumn> $columns
     * @return array{int, int} [col, row]
     */
    private function findVersionPosition(array $columns, string $versionRaw): array
    {
        foreach ($columns as $colIdx => $col) {
            foreach ($col->versions as $rowIdx => $version) {
                if ($version->versionRaw === $versionRaw) {
                    return [$colIdx, $rowIdx];
                }
            }
        }

        return $this->defaultPosition($columns);
    }

    /**
     * @param list<PickerColumn> $columns
     * @return array{int, int} last column, first row (newest series, latest version)
     */
    private function defaultPosition(array $columns): array
    {
        return [max(0, count($columns) - 1), 0];
    }
}
