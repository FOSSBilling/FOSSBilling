.SILENT:

DOCKER_COMPOSE = docker-compose
DOCKER_PHP_CONTAINER_EXEC = $(DOCKER_COMPOSE) exec php
DOCKER_DB_CONTAINER_EXEC = $(DOCKER_COMPOSE) exec database
DOCKER_PHP_EXECUTABLE_CMD = php -dmemory_limit=1G

help:           ## Show this help
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//'

all: start-recreate reinstall

start:          ## Start app
	$(DOCKER_COMPOSE) up -d

start-recreate: ## Start app with full rebuild
	$(DOCKER_COMPOSE) up -d  --build --force-recreate --remove-orphans

stop:           ## Stop app
	$(DOCKER_COMPOSE) stop

remove: stop    ## Stop and remove app
	$(DOCKER_COMPOSE) rm -f

logs:           ## Show app logs
	$(DOCKER_COMPOSE) logs -ft --tail=50

exec-php:       ## Enter PHP container shell
	$(DOCKER_PHP_CONTAINER_EXEC) bash

exec-db:        ## Enter DB container shell
	$(DOCKER_DB_CONTAINER_EXEC) bash

install: start  ## Install app after start
	$(DOCKER_PHP_CONTAINER_EXEC) composer install --working-dir=src
ifeq (,$(wildcard ./src/bb-config.php))
	cp ./src/bb-config-sample.php ./src/bb-config.php
	$(DOCKER_PHP_CONTAINER_EXEC) $(DOCKER_PHP_EXECUTABLE_CMD) ./bin/prepare.php
endif


reinstall:      ## Reinstall app
	rm -rf ./src/bb-config.php
	make install