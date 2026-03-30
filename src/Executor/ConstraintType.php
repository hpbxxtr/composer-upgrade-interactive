<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Executor;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
enum ConstraintType: string
{
    case Exact = 'exact';
    case Caret = 'caret';
}
