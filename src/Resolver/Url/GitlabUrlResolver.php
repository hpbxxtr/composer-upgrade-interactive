<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver\Url;

use Hpbxxtr\UpgradeInteractive\Core\Str;
use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;
use Override;

use function sprintf;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final class GitlabUrlResolver extends AbstractUrlResolver
{
    private readonly ?string $slug;

    public function __construct(OutdatedPackage $outdatedPackage)
    {
        parent::__construct($outdatedPackage);

        $m          = Str::match('#gitlab\.com[/:]([^/]+/[^/]+?)(?:\.git)?(?:/.*)?$#', $outdatedPackage->repoUrl);
        $this->slug = $m !== null ? ($m[1] ?? null) : null;
    }

    #[Override]
    public function isMatch(): bool
    {
        return $this->slug !== null;
    }

    #[Override]
    public function resolve(BumpType $bumpType): UrlResult
    {
        $target = $this->targetVersion($bumpType);

        if (!$target instanceof VersionTarget || $this->slug === null) {
            return new UrlResult(null, null);
        }

        return new UrlResult(
            compareUrl: sprintf('https://gitlab.com/%s/-/compare/%s...%s', $this->slug, $this->package->currentRaw, $target->versionRaw),
            releaseUrl: sprintf('https://gitlab.com/%s/-/releases/%s', $this->slug, $target->versionRaw),
        );
    }
}
