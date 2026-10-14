.PHONY: build up down deploy

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

deploy:
	git pull
	$(MAKE) build
	$(MAKE) down
	docker compose run --rm --user www-data php-fpm php do migrate:migrate
	docker compose run --rm --user www-data php-fpm php do cube:dispatch --event PostDeployment
	docker compose run --rm --user www-data php-fpm php do configuration:cache
	$(MAKE) up
