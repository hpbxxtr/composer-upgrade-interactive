<?php

declare(strict_types=1);

namespace Hpbxxtr\UpgradeInteractive\Resolver\Url;

use Hpbxxtr\UpgradeInteractive\Resolver\BumpType;
use Hpbxxtr\UpgradeInteractive\Resolver\OutdatedPackage;
use Override;

/**
 * @internal Hpbxxtr\UpgradeInteractive
 */
final class ComposeUrlResolver extends AbstractUrlResolver
{
    /**
     * @var list<UrlResolverInterface>
     */
    private array $resolvers;

    public function __construct(OutdatedPackage $outdatedPackage)
    {
        parent::__construct($outdatedPackage);

        $this->resolvers = [
            new GithubUrlResolver($outdatedPackage),
            new GitlabUrlResolver($outdatedPackage),
            new PackagistUrlResolver($outdatedPackage),
        ];
    }

    /**
     * @param list<UrlResolverInterface> $resolvers
     */
    public function setResolvers(array $resolvers): void
    {
        $this->resolvers = $resolvers;
    }

    #[Override]
    public function isMatch(): bool
    {
        return $this->firstMatch() instanceof UrlResolverInterface;
    }

    #[Override]
    public function resolve(BumpType $bumpType): UrlResult
    {
        return $this->firstMatch()?->resolve($bumpType) ?? new UrlResult(null, null);
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
