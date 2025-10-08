SHELL := /bin/sh

COMPOSER_IMAGE := composer:2
API_DIR := $(CURDIR)/apps/api
WEB_DIR := $(CURDIR)/apps/web
COMPOSER_RUN := docker run --rm --user "$$(id -u):$$(id -g)" -e COMPOSER_CACHE_DIR=/tmp/composer-cache -v "$(API_DIR):/app" -w /app $(COMPOSER_IMAGE)

.PHONY: install ci test test-e2e audit build lint compose-config up down api-install api-test api-audit web-install web-lint web-test web-e2e web-build web-audit

install: api-install web-install

ci: lint test audit build compose-config

test: api-test web-test

test-e2e: web-e2e

audit: api-audit web-audit

build: web-build

lint: web-lint

compose-config:
	docker compose -f infra/docker/docker-compose.yml config --quiet
	docker compose -f infra/docker/docker-compose.prod.yml config --quiet
	docker compose --env-file infra/docker/.env.https.example -f infra/docker/docker-compose.https.yml config --quiet

up:
	docker compose -f infra/docker/docker-compose.yml up --build

down:
	docker compose -f infra/docker/docker-compose.yml down

api-install:
	$(COMPOSER_RUN) composer install --no-interaction --prefer-dist

api-test:
	$(COMPOSER_RUN) sh -lc 'composer install --no-interaction --prefer-dist && composer test'

api-audit:
	$(COMPOSER_RUN) sh -lc 'composer install --no-interaction --prefer-dist && composer audit'

web-install:
	cd "$(WEB_DIR)" && npm ci

web-lint:
	cd "$(WEB_DIR)" && npm run lint

web-test:
	cd "$(WEB_DIR)" && npm run test:run

web-e2e:
	cd "$(WEB_DIR)" && npm run test:e2e

web-build:
	cd "$(WEB_DIR)" && npm run build

web-audit:
	cd "$(WEB_DIR)" && npm audit
