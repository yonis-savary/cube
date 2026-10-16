.PHONY: dev dev-down build up down deploy exec launch-scheduler

# Production reads docker-compose.yml only, never the development override
PROD = docker compose -f docker-compose.yml
DO = $(PROD) run --rm php-fpm runuser -u www-data -- php do
EXEC = docker compose exec --user www-data php-fpm

dev:
	docker compose up -d --build

dev-down:
	docker compose down

build:
	$(PROD) build

up:
	$(PROD) up -d

down:
	$(PROD) down

deploy:
	git pull
	$(MAKE) build
	$(MAKE) down
	$(DO) migrate:migrate
	$(DO) cube:dispatch --event PostDeployment
	$(DO) configuration:cache
	$(MAKE) up

exec:
	$(EXEC) bash

launch-scheduler:
	$(EXEC) php do routine:launch
