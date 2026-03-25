<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver\Url;

use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Override;

use function sprintf;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final class PackagistUrlResolver extends AbstractUrlResolver
{
    #[Override]
    public function isMatch(): bool
    {
        return true;
    }

    #[Override]
    public function resolve(BumpType $bumpType): UrlResult
    {
        $url = sprintf('https://packagist.org/packages/%s#releases', $this->package->name);

        return new UrlResult(compareUrl: $url, releaseUrl: $url);
    }
}
