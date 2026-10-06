filter=.
jobs=

processes=$(if $(jobs),--processes $(jobs),)

cleanup:
	@php tests/drop-leftover-databases.php || echo 'Could not drop leftover test databases, are the services up ?'
	@[ -d 'tests/integration-apps' ] && rm -r tests/integration-apps || true
	@[ -d 'tests/Storage/Database' ] && rm -r tests/Storage/Database || true
	@[ -d 'tests/Storage/Cache' ] && rm -r tests/Storage/Cache || true
	@[ -d 'Storage/' ] && rm -r Storage/ || true

test:
	@make --no-print-directory test-dirty; \
	status=$$?; \
	make --no-print-directory cleanup; \
	exit $$status

test-dirty:
	@composer install
	@docker compose up -d --build
	@make --no-print-directory cleanup
	@make --no-print-directory workflow-test

# ParaTest runs the three suites together, one process per test class.
workflow-test:
	@vendor/bin/paratest --filter=$(filter) $(processes)

test-serial:
	@make --no-print-directory test-serial-dirty; \
	status=$$?; \
	make --no-print-directory cleanup; \
	exit $$status

test-serial-dirty:
	@composer install
	@docker compose up -d --build
	@make --no-print-directory cleanup
	@vendor/bin/phpunit --filter=$(filter)

fix:
	@./vendor/bin/php-cs-fixer fix --allow-risky=yes

rm:
	@docker compose down --rmi local
