filter=.

cleanup:
	@[ -d 'tests/integration-apps' ] && rm -r tests/integration-apps || true
	@[ -d 'tests/Storage/Database' ] && rm -r tests/Storage/Database || true
	@[ -d 'tests/Storage/Cache' ] && rm -r tests/Storage/Cache || true
	@[ -d 'Storage/Cache' ] && rm -r Storage/Cache || true

test:
	@make --no-print-directory test-dirty
	@make --no-print-directory cleanup

test-dirty:
	@composer install
	@docker compose up -d --build
	@make --no-print-directory cleanup
	@vendor/bin/phpunit --filter=$(filter)

workflow-test:
	@vendor/bin/phpunit --filter=$(filter)

fix:
	@./vendor/bin/php-cs-fixer fix --allow-risky=yes

rm:
	@docker compose down --rmi local