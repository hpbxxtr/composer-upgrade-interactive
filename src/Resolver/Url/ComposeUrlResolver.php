<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver\Url;

use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Hpbxxtr\UpgradeInteractive\Resolver\VersionTarget;
use Override;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final class ComposeUrlResolver extends AbstractUrlResolver
{
    /**
     * @var list<UrlResolverInterface>
     */
    private readonly array $resolvers;

    /**
     * @param list<UrlResolverInterface>|null $resolvers  Null uses the default GitHub/GitLab/Packagist chain.
     */
    public function __construct(OutdatedPackage $outdatedPackage, ?array $resolvers = null)
    {
        parent::__construct($outdatedPackage);

        $this->resolvers = $resolvers ?? [
            new GithubUrlResolver($outdatedPackage),
            new GitlabUrlResolver($outdatedPackage),
            new PackagistUrlResolver($outdatedPackage),
        ];
    }

    #[Override]
    public function isMatch(): bool
    {
        return $this->firstMatch() instanceof UrlResolverInterface;
    }

    #[Override]
    public function resolve(BumpType $bumpType, ?VersionTarget $versionTarget = null): UrlResult
    {
        return $this->firstMatch()?->resolve($bumpType, $versionTarget) ?? new UrlResult(null, null);
    }

    private function firstMatch(): ?UrlResolverInterface
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->isMatch()) {
                return $resolver;
            }
        }

        return null;
    }
}
