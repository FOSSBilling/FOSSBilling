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
ifeq (,$(wildcard ./src/bb-config.php))
	cp ./src/bb-config-sample.php ./src/bb-config.php
	$(DOCKER_PHP_CONTAINER_EXEC) $(DOCKER_PHP_EXECUTABLE_CMD) ./bin/prepare.php
endif

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

cache-flush:       ## Flush Twig cache
	$(DOCKER_PHP_CONTAINER_EXEC) rm -fr src/bb-data/cache/*

exec-db:        ## Enter DB container shell
	$(DOCKER_DB_CONTAINER_EXEC) bash

install: start  ## Install app after start
	$(DOCKER_PHP_CONTAINER_EXEC) composer install --working-dir=src --no-progress --no-suggest --prefer-dist --no-dev

reinstall:      ## Reinstall app
	rm -rf ./src/bb-config.php
	make install

test: start	## Run app tests
	echo "Running unit tests"
	echo > ./src/bb-data/log/application.log
	echo > ./src/bb-data/log/php_error.log
	rm -rf src/install
	$(DOCKER_PHP_CONTAINER_EXEC) composer install --working-dir=src --no-progress --no-suggest --prefer-dist
	$(DOCKER_PHP_CONTAINER_EXEC) ./src/bb-vendor/bin/phpunit --dont-report-useless-tests ./tests/bb-modules/

build:          ## Build App with Docker CI image
	docker run --rm \
		--mount type=bind,source=$(PWD),target=/opt -w /opt \
		-e TRAVIS_TAG \
		fordnox/docker-builder-ci \
		make release

release:        ## App release
	echo "TRAVIS_BUILD_NUMBER:" $(TRAVIS_BUILD_NUMBER)
	echo "TRAVIS_TAG:" $(TRAVIS_TAG)
	npm install -g grunt-cli
	npm install
	grunt
	ant release

revbump:
	set -e ;\
		git tag --sort=v:refname | tail -1
		NEW_VERSION=$$( git tag --sort=v:refname | tail -1 | awk 'BEGIN{FS=OFS="."}{print $$1,$$2,$$3+1}' ) ;\
		echo "unstable release $$NEW_VERSION" ;\
		git tag $$NEW_VERSION
		git push --tags

minorbump:
	set -e ;\
		git tag --sort=v:refname | tail -1
		NEW_VERSION=$$( git tag --sort=v:refname | tail -1 | awk 'BEGIN{FS=OFS="."}{print $$1,$$2+1,0}' ) ;\
		echo "unstable release $$NEW_VERSION" ;\
		git tag $$NEW_VERSION
		git push --tags

majorbump:
	set -e ;\
		git tag --sort=v:refname | tail -1
		NEW_VERSION=$$( git tag --sort=v:refname | tail -1 | awk 'BEGIN{FS=OFS="."}{print $$1+1,0,0}' ) ;\
		echo "release $$NEW_VERSION" ;\
		git tag $$NEW_VERSION
		git push --tags

build-run: 		## Run app in LAMP container after build
	# used to test app after build
	# run `make build` before running this target
	# mysql user: admin pass: admin
	# access phpmyadmin http://localhost/phpmyadmin/
	docker container stop boxbilling &>/dev/null || true
	docker container rm -f boxbilling &>/dev/null || true
	docker run -i -t --name boxbilling -p "80:80" \
		-v ${PWD}/build/source:/app \
		-e MYSQL_ADMIN_PASS=admin \
		mattrayner/lamp:latest
