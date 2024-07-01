# Executables (local)
DOCKER_COMP = docker compose
DOCKER_RUN  = docker run

# Docker containers
PHP_CONT = $(DOCKER_COMP) exec php
PHP_STAN_CONT = $(DOCKER_COMP) exec -e PHP_MEMORY_LIMIT=256M php

# Executables
PHP      = $(PHP_CONT) php
COMPOSER = $(PHP_CONT) composer
SYMFONY  = $(PHP_CONT) php bin/console

# Misc
.DEFAULT_GOAL := help
.PHONY        : help build up start down logs bash composer vendor sf cc lint stan cs checkstyle security cbf

## —— 🎵 🐳 The Symfony Docker Makefile 🐳 🎵 ——————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Docker 🐳 ————————————————————————————————————————————————————————————————
build: ## Builds the Docker images
	@$(DOCKER_COMP) build --pull --no-cache

up: ## Start the docker hub in detached mode (no logs)
	@$(DOCKER_COMP) up --detach
	@$(PHP_CONT) bash -c "symfony-installer"

start: build up ## Build and start the containers

down: ## Stop the docker hub
	@$(DOCKER_COMP) down --remove-orphans

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=0 --follow

bash: ## Connect to the PHP FPM container
	@$(PHP_CONT) bash

## —— Composer 🧙 ——————————————————————————————————————————————————————————————
composer: ## Run composer, pass the parameter "c=" to run a given command, example: make composer c='req symfony/orm-pack'
	@$(eval c ?=)
	@$(COMPOSER) $(c)

vendor: ## Install vendors according to the current composer.lock file
vendor: c=install --prefer-dist --no-progress --no-scripts --no-interaction
vendor: composer

## —— Symfony 🎵 ———————————————————————————————————————————————————————————————
sf: ## List all Symfony commands or pass the parameter "c=" to run a given command, example: make sf c=about
	@$(eval c ?=)
	@$(SYMFONY) $(c)

cc: c=c:c ## Clear the cache
cc: sf

## —— CI/CD ⚙️ —————————————————————————————————————————————————————————————————
lint: ## Lint PHP files
	@$(PHP_CONT) vendor/bin/parallel-lint . --exclude vendor

stan: ## Stan check PHP files
	@$(PHP_STAN_CONT) vendor/bin/phpstan analyse

cs:checkstyle ## Alias for checkstyle
checkstyle: ## Checkstyle source and tests PHP files using PSR12 standard
	@$(PHP_CONT) vendor/bin/phpcs

security: ## Security check for CVE
security: c=audit --format=summary
security: composer

## —— Misc ⛏️ ——————————————————————————————————————————————————————————————————
cbf: ## Fix checkstyle issues
	@$(PHP_CONT) vendor/bin/phpcbf