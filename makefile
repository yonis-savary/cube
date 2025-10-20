test:
	@composer install
	@docker compose build
	@docker compose up -d
	@sleep 5
	@rm -r tests/integration-apps || true
	@rm -r tests/Storage/Database || true
	@rm -r tests/Storage/Cache || true
	@rm -r Storage/Cache || true
	@vendor/bin/phpunit

workflow-test:
	@vendor/bin/phpunit

fix:
	@./vendor/bin/php-cs-fixer fix --allow-risky=yes