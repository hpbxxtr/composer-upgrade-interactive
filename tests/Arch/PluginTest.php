<?php

declare(strict_types=1);

use Hpbxxtr\UpgradeInteractive\Plugin;
use Hpbxxtr\UpgradeInteractive\Resolver\Compatibility\ConflictReason;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionSelection;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;

\arch('all classes use strict types')
    ->expect('Hpbxxtr\UpgradeInteractive')
    ->toUseStrictTypes()
;

\arch('value objects are final and readonly')
    ->expect(OutdatedPackage::class)
    ->toBeFinal()
    ->toBeReadonly()
;

\arch('value objects VersionTarget is final and readonly')
    ->expect(VersionTarget::class)
    ->toBeFinal()
    ->toBeReadonly()
;

\arch('plugin layer does not depend on UI layer')
    ->expect(Plugin::class)
    ->not->toUse('Hpbxxtr\UpgradeInteractive\UI')
;

\arch('resolver layer does not depend on UI or executor layers')
    ->expect('Hpbxxtr\UpgradeInteractive\Resolver')
    ->not->toUse('Hpbxxtr\UpgradeInteractive\UI')
    ->not->toUse('Hpbxxtr\UpgradeInteractive\Executor');

\arch('UI layer does not depend on Executor layer')
    ->expect('Hpbxxtr\UpgradeInteractive\UI')
    ->not->toUse('Hpbxxtr\UpgradeInteractive\Executor');

\arch('value objects VersionSelection is final and readonly')
    ->expect(VersionSelection::class)
    ->toBeFinal()
    ->toBeReadonly();

\arch('value objects ConflictReason is final and readonly')
    ->expect(ConflictReason::class)
    ->toBeFinal()
    ->toBeReadonly();
