<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Executor;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
interface UpgradeExecutorInterface
{
    /**
     * @param array<string, string> $selections package name => raw version tag
     *
     * @throws \Exception
     */
    public function execute(array $selections, ConstraintType $constraintType = ConstraintType::Exact): void;
}
