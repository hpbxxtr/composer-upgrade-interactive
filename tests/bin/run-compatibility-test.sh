#!/bin/sh
set -e

QUIET="--quiet"
for arg in "$@"; do
  case "$arg" in
    -v|--verbose) QUIET="" ;;
  esac
done

php -v

# Static analysis and code style tooling carries its own, narrower PHP constraints and is
# irrelevant to a runtime compatibility check. Removing it first (batched via --no-update)
# keeps the dependency resolution below focused on what the plugin actually needs at runtime.
echo "Remove unnecessary dev dependencies..."
composer remove --dev --no-update --no-interaction $QUIET \
  brnshkr/config \
  friendsofphp/php-cs-fixer \
  kubawerlos/php-cs-fixer-custom-fixers \
  phpstan/extension-installer \
  phpstan/phpstan \
  phpstan/phpstan-deprecation-rules \
  phpstan/phpstan-doctrine \
  phpstan/phpstan-phpunit \
  phpstan/phpstan-strict-rules \
  phpstan/phpstan-webmozart-assert \
  rector/rector \
  rector/type-perfect \
  symplify/phpstan-rules \
  ticketswap/phpstan-error-formatter

echo "Install dependencies..."
composer update --no-interaction --prefer-dist $QUIET -W

echo "Run tests..."
vendor/bin/pest --compact --colors=never --no-coverage
