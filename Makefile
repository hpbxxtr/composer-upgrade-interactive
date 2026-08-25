#-- h:ui

#--- core

PWD := .
PHP_COMPOSER := docker compose exec php composer
PHP_STAN := docker compose exec php vendor/bin/phpstan
PHP_CS_FIXER := docker compose exec php vendor/bin/php-cs-fixer
RECTOR := docker compose exec php vendor/bin/rector
PHP_PEST := docker compose exec php vendor/bin/pest
PHP_PEST_COVERAGE := docker compose exec -e XDEBUG_MODE=coverage php php -d xdebug.log= -d xdebug.control_socket=off vendor/bin/pest

define LOGO

__/\\\\\\___________________________________
 _\\/\\\\\\___________________________________
  _\\/\\\\\\______________________________/\\\\\\_
   _\\/\\\\\\________________/\\\\\\____/\\\\\\_\\///__
    _\\/\\\\\\\\\\\\\\\\\\\\___/\\\\\\_\\/\\\\\\___\\/\\\\\\__/\\\\\\_
     _\\/\\\\\\/////\\\\\\_\\///__\\/\\\\\\___\\/\\\\\\_\\/\\\\\\_
      _\\/\\\\\\___\\/\\\\\\_______\\/\\\\\\___\\/\\\\\\_\\/\\\\\\_
       _\\/\\\\\\___\\/\\\\\\__/\\\\\\_\\//\\\\\\\\\\\\\\\\\\__\\/\\\\\\_
        _\\///____\\///__\\///___\\/////////___\\///__


endef

# The brnshkr/config Makefile lives in vendor/, which does not exist before the first
# `composer install`. Fall back to conf/Makefile so the Docker lifecycle targets
# (up, vendors, bash) are available on a fresh clone.
ifneq ($(wildcard $(PWD)/vendor/brnshkr/config/conf/Makefile),)
include ./vendor/brnshkr/config/conf/Makefile
else
include ./conf/Makefile
endif

include .env

# Guarded rather than `-include`: `.env.local` is gitignored, and a plain `-include` of a
# missing file makes make try to remake it, which trips the brnshkr/config .DEFAULT handler.
ifneq ($(wildcard .env.local),)
include .env.local
endif

export
