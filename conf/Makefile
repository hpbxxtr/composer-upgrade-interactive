#-- h:ui

#--- core
CACHE_DIR := ./.cache

#-- h:ui
#--- core
build: #~~ builds docker containers
	docker compose build

bash: #~~ open bash of php container
	docker compose exec php bash

bash-root: #~~ open bash of php container as root
	docker compose exec -u root php bash

up-only: #~~ bring up docker containers
	@mkdir -p ${DOCKER_COMPOSER_CACHE}
	docker compose up -d

up: up-only vendors #~~ bring up docker containers and prepare environment

down: #~~ bring down docker containers
	docker compose down

restart: down up #~~ bring down docker containers and up again

vendors: #~~ composer install
	${PHP_COMPOSER} install

cache-clear: #~~ removes all tool caches (.cache dir)
	@rm -rf ${CACHE_DIR}
	@mkdir -p ${CACHE_DIR}
	@echo "Cache cleared: ${CACHE_DIR}"
