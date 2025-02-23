test:
	@docker compose up -d
	@rm -r tests/integration-apps || true
	@rm -r tests/Storage/Database || true
	@rm -r tests/Storage/Cache || true
	@vendor/bin/phpunit