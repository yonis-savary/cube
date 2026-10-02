.PHONY: build up down deploy

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

deploy:
	git pull
	$(MAKE) down
	$(MAKE) build
	$(MAKE) up
	docker compose exec --user www-data php-fpm php do cube:dispatch --event PostDeployment
