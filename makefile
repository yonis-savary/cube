test:
	@composer install
	@docker compose up -d --build
	@[ -d 'tests/integration-apps' ] && rm -r tests/integration-apps || true
	@[ -d 'tests/Storage/Database' ] && rm -r tests/Storage/Database || true
	@[ -d 'tests/Storage/Cache' ] && rm -r tests/Storage/Cache || true
	@[ -d 'Storage/Cache' ] && rm -r Storage/Cache || true
	@vendor/bin/phpunit

workflow-test:
	@vendor/bin/phpunit

fix:
	@./vendor/bin/php-cs-fixer fix --allow-risky=yes

rm:
	@docker compose down --rmi local