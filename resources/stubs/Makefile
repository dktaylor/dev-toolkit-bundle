# Thin shortcuts that delegate to the project's console/composer commands.
# Run `make` or `make help` for the list.

.DEFAULT_GOAL := help
CONSOLE := php bin/console

.PHONY: help install up down stop status logs test cs cs-fix stan quality hooks

help: ## List available targets
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

install: ## Install PHP dependencies (and the isolated dev tools)
	composer install

up: ## Start the local dev environment (Docker, Symfony server, assets)
	$(CONSOLE) dev:up

down: ## Stop the local dev environment
	$(CONSOLE) dev:down

stop: down ## Alias for `down`

status: ## Show the dev environment status
	$(CONSOLE) dev:status

logs: ## Tail the Symfony web server logs
	$(CONSOLE) dev:logs

test: ## Run the unit test suite
	composer test

cs: ## Check coding standards
	composer cs-check

cs-fix: ## Fix coding standards
	composer cs-fix

stan: ## Run static analysis
	composer phpstan

quality: ## Run the full quality suite (phpstan, cs, audit, tests)
	composer quality

hooks: ## Install the project git hooks (.githooks)
	git config core.hooksPath .githooks
	@echo "Git hooks installed (core.hooksPath -> .githooks)."