#!/usr/bin/make -f

.DEFAULT_GOAL := help
SHELL = /bin/bash -o pipefail

cwd := $(shell pwd)

COMPOSER_CMD=docker run --rm -i --tty -v $(cwd):/app composer:lts
SYMFONY_CMD=docker compose exec php bin/console
PHP_CS_FIXER_CMD=vendor/bin/php-cs-fixer
PHPUNIT_CMD=docker compose exec php bin/phpunit

help:
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_\-\.]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

init:                                                                          ## setup application
	$(MAKE) docker-start
	$(MAKE) composer
	$(SYMFONY_CMD) assets:install --relative
	$(MAKE) fix-database-permissions

fix-database-permissions:
	docker compose exec \
		-e DATABASE_URL="postgresql://postgres:admin@psql.test:5432/foo_intro?serverVersion=14&charset=utf8" \
		php bin/console doctrine:query:sql 'GRANT USAGE ON SEQUENCE sec.user_id_seq TO web'
	docker compose exec \
		-e DATABASE_URL="postgresql://postgres:admin@psql.test:5432/foo_intro?serverVersion=14&charset=utf8" \
		php bin/console doctrine:query:sql 'GRANT USAGE ON SEQUENCE std.adresse_adresse_id_seq TO web'

shell:                                                                         ## shell into php container
	docker compose exec php bash

docker-start:                                                                  ## start docker stack
	docker compose up --build -d

docker-stop:
	docker compose down -t0

composer:                                                                      ## install composer deps
	$(COMPOSER_CMD) install --ignore-platform-reqs

composer-shell:                                                                ## install composer deps
	docker run --rm -i --tty -v $(cwd):/app composer:lts /bin/sh

fixtures:                                                                       ## load fixtures
	$(SYMFONY_CMD) doctrine:fixtures:load -n -vv

cache-clear:                                                                   ## clear symfony + doctrine cache
	$(SYMFONY_CMD) cache:clear
	$(SYMFONY_CMD) doctrine:cache:clear-metadata

lint-config:                                                                    ## lint configuration files
	$(SYMFONY_CMD) lint:yaml config

lint-container:                                                                ## lint container configuration
	$(SYMFONY_CMD) lint:container

lint: lint-config lint-container                                                ## lint config/templates/container

phpunit:                                                                        ## run phpunit
	$(PHPUNIT_CMD) --testdox

test: cache-clear                                                                       	## run phpunit (without testdox)
	$(PHPUNIT_CMD)

.PHONY: help init shell docker-start docker-stop composer fixtures cache-clear lint lint-config lint-container phpunit
