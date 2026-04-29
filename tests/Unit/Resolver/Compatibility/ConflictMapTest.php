<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\Resolver\Compatibility\ConflictMap;
use Hpbxxtr\UpgradeInteractive\Resolver\Compatibility\ConflictReason;

describe('ConflictMap', function (): void {
    describe('empty()', function (): void {
        it('creates an empty map', function (): void {
            $conflictMap = ConflictMap::empty();

            expect($conflictMap->isEmpty())->toBeTrue();
        });
    });

    describe('isEmpty()', function (): void {
        it('returns false when map has entries', function (): void {
            $reason = new ConflictReason(
                dependentPackage: 'vendor/a',
                dependentVersion: '1.0.0',
                requiredPackage: 'vendor/b',
                requiredConstraint: '^2.0',
                selectedVersion: '3.0.0',
            );

            $map = new ConflictMap(['vendor/a' => ['1.0.0' => [$reason]]]);

            expect($map->isEmpty())->toBeFalse();
        });

        it('returns false when map has package entries with empty conflict lists', function (): void {
            $map = new ConflictMap(['vendor/a' => ['1.0.0' => []]]);

            expect($map->isEmpty())->toBeFalse();
        });
    });

    describe('isCompatible()', function (): void {
        it('returns false for a known conflict', function (): void {
            $reason = new ConflictReason(
                dependentPackage: 'vendor/a',
                dependentVersion: '1.0.0',
                requiredPackage: 'vendor/b',
                requiredConstraint: '^2.0',
                selectedVersion: '3.0.0',
            );

            $map = new ConflictMap(['vendor/a' => ['1.0.0' => [$reason]]]);

            expect($map->isCompatible('vendor/a', '1.0.0'))->toBeFalse();
        });

        it('returns true for a known-clean version', function (): void {
            $map = new ConflictMap(['vendor/a' => ['1.0.0' => []]]);

            expect($map->isCompatible('vendor/a', '1.0.0'))->toBeTrue();
        });

        it('returns true for an unknown package (safe default)', function (): void {
            $conflictMap = ConflictMap::empty();

            expect($conflictMap->isCompatible('vendor/unknown', '1.0.0'))->toBeTrue();
        });

        it('returns true for a known package but unknown version', function (): void {
            $reason = new ConflictReason(
                dependentPackage: 'vendor/a',
                dependentVersion: '1.0.0',
                requiredPackage: 'vendor/b',
                requiredConstraint: '^2.0',
                selectedVersion: '3.0.0',
            );

            $map = new ConflictMap(['vendor/a' => ['1.0.0' => [$reason]]]);

            expect($map->isCompatible('vendor/a', '2.0.0'))->toBeTrue();
        });
    });

    describe('conflictsFor()', function (): void {
        it('returns conflicts for a known conflicting entry', function (): void {
            $reason = new ConflictReason(
                dependentPackage: 'vendor/a',
                dependentVersion: '1.0.0',
                requiredPackage: 'vendor/b',
                requiredConstraint: '^2.0',
                selectedVersion: '3.0.0',
            );

            $map = new ConflictMap(['vendor/a' => ['1.0.0' => [$reason]]]);

            expect($map->conflictsFor('vendor/a', '1.0.0'))
                ->toHaveCount(1)
                ->and($map->conflictsFor('vendor/a', '1.0.0')[0])
                ->toBe($reason);
        });

        it('returns empty list for unknown package', function (): void {
            $conflictMap = ConflictMap::empty();

            expect($conflictMap->conflictsFor('vendor/unknown', '1.0.0'))->toBe([]);
        });

        it('returns empty list for unknown version of known package', function (): void {
            $reason = new ConflictReason(
                dependentPackage: 'vendor/a',
                dependentVersion: '1.0.0',
                requiredPackage: 'vendor/b',
                requiredConstraint: '^2.0',
                selectedVersion: '3.0.0',
            );

            $map = new ConflictMap(['vendor/a' => ['1.0.0' => [$reason]]]);

            expect($map->conflictsFor('vendor/a', '2.0.0'))->toBe([]);
        });

        it('returns multiple conflicts when present', function (): void {
            $reason1 = new ConflictReason('vendor/a', '1.0.0', 'vendor/b', '^2.0', '3.0.0');
            $reason2 = new ConflictReason('vendor/a', '1.0.0', 'vendor/c', '^1.0', '2.0.0');

            $map = new ConflictMap(['vendor/a' => ['1.0.0' => [$reason1, $reason2]]]);

            expect($map->conflictsFor('vendor/a', '1.0.0'))->toHaveCount(2);
        });
    });

    describe('withMerged()', function (): void {
        it('returns a new map containing existing and extra entries', function (): void {
            $existing = new ConflictReason('vendor/a', '1.0.0', 'vendor/b', '^1.0', '2.0.0');
            $extra    = new ConflictReason('vendor/a', '2.0.0', 'vendor/c', '^2.0', '3.0.0');

            $map    = new ConflictMap(['vendor/a' => ['1.0.0' => [$existing]]]);
            $conflictMap = $map->withMerged(['vendor/a' => ['2.0.0' => [$extra]]]);

            expect($conflictMap->conflictsFor('vendor/a', '1.0.0'))->toHaveCount(1)
                ->and($conflictMap->conflictsFor('vendor/a', '2.0.0'))->toHaveCount(1);
        });

        it('overwrites an existing entry when the same package+version appears in extra', function (): void {
            $old = new ConflictReason('vendor/a', '1.0.0', 'vendor/b', '^1.0', '2.0.0');
            $new = new ConflictReason('vendor/a', '1.0.0', 'vendor/c', '^3.0', '4.0.0');

            $map    = new ConflictMap(['vendor/a' => ['1.0.0' => [$old]]]);
            $conflictMap = $map->withMerged(['vendor/a' => ['1.0.0' => [$new]]]);

            expect($conflictMap->conflictsFor('vendor/a', '1.0.0'))
                ->toHaveCount(1)
                ->and($conflictMap->conflictsFor('vendor/a', '1.0.0')[0])->toBe($new);
        });

        it('does not mutate the original map', function (): void {
            $conflictMap    = ConflictMap::empty();
            $reason = new ConflictReason('vendor/a', '1.0.0', 'vendor/b', '^1.0', '2.0.0');
            $conflictMap->withMerged(['vendor/a' => ['1.0.0' => [$reason]]]);

            expect($conflictMap->isEmpty())->toBeTrue();
        });

        it('adds a new package when the package did not exist before', function (): void {
            $conflictMap    = ConflictMap::empty();
            $reason = new ConflictReason('vendor/new', '3.0.0', 'vendor/dep', '^1.0', '2.0.0');
            $merged = $conflictMap->withMerged(['vendor/new' => ['3.0.0' => [$reason]]]);

            expect($merged->conflictsFor('vendor/new', '3.0.0'))->toHaveCount(1);
        });
    });
});
