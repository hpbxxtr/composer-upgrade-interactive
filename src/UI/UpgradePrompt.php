<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\UI;

use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Override;

use function array_fill_keys;
use function array_map;
use function count;
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
     * @var array<string, BumpType|null>
     */
    public array $selections;

    /**
     * @param list<OutdatedPackage> $entries
     */
    public function __construct(
        public readonly array $entries,
        public readonly string $label = 'Select versions to update',
    ) {
        self::$themes['default'][self::class] = UpgradePromptRenderer::class;

        $this->required = false;

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
            $bump = $this->selections[$entry->name] ?? null;

            if ($bump === null) {
                continue;
            }

            $target = $entry->target($bump);

            if ($target !== null) {
                $result[$entry->name] = $target->versionRaw;
            }
        }

        return $result;
    }

    private function handleKey(string $key): void
    {
        match (true) {
            in_array($key, [Key::UP, Key::UP_ARROW, Key::CTRL_P], true)       => $this->moveRow(-1),
            in_array($key, [Key::DOWN, Key::DOWN_ARROW, Key::CTRL_N], true)   => $this->moveRow(1),
            in_array($key, [Key::LEFT, Key::LEFT_ARROW, Key::CTRL_B], true)   => $this->moveCol(-1),
            in_array($key, [Key::RIGHT, Key::RIGHT_ARROW, Key::CTRL_F], true) => $this->moveCol(1),
            $key === Key::SPACE                                               => $this->toggleSelection(),
            $key === "\n" || $key === "\r"                                    => $this->submit(),
            $key === Key::CTRL_C                                              => $this->cancelPrompt(),
            default                                                           => false, // ignore unrecognised keys
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

        $this->selections[$entry->name] = $current === $col ? null : $col;
    }

    private function cancelPrompt(): void
    {
        $this->selections = array_fill_keys(
            array_map(static fn (OutdatedPackage $outdatedPackage): string => $outdatedPackage->name, $this->entries),
            null,
        );

        $this->submit();
    }
}
