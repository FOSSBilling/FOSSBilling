ifeq ($(wildcard docker-compose),) 
    DOCKER_COMPOSE := docker-compose
else 
    DOCKER_COMPOSE := docker compose
endif
DOCKER_WEB_CONTAINER_EXEC := $(DOCKER_COMPOSE) exec web
DOCKER_PHP_CONTAINER_EXEC := $(DOCKER_COMPOSE) exec php
DOCKER_DB_CONTAINER_EXEC := $(DOCKER_COMPOSE) exec mariadb
DOCKER_PHP_EXECUTABLE_CMD := php -dmemory_limit=1G

help: ## Show this help
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//'

addx:
	chmod +x ./bin/install.sh
	
all: docker

docker: addx ## Will migrate other tasks to the bash script too
	./bin/install.sh

start:	## Start app
	$(DOCKER_COMPOSE) up -d

refresh: ## Start app with full rebuild
	$(DOCKER_COMPOSE) up -d --build --force-recreate --remove-orphans

stop:	## Stop app
	$(DOCKER_COMPOSE) stop

remove:	stop ## Stop and remove app
	$(DOCKER_COMPOSE) rm -f

logs: ## Show app logs
	$(DOCKER_COMPOSE) logs -ft --tail=50

exec-php: ## Enter PHP container shell
	$(DOCKER_PHP_CONTAINER_EXEC) bash

cache-flush: ## Flush Twig cache
	$(DOCKER_PHP_CONTAINER_EXEC) rm -fr src/data/cache/*

exec-db: ## Enter DB container shell
	$(DOCKER_DB_CONTAINER_EXEC) bash

install: start ## Install app after start
	docker run --rm --interactive --tty --volume $(PWD):/app composer install --working-dir=src --no-progress --prefer-dist --no-dev
	rm -rf ./src/install

reinstall: ## Reinstall app
	rm -rf ./src/bb-config.php
	make install

test: start ## Run app tests
	echo "Running unit tests"
	echo > ./src/data/log/application.log
	echo > ./src/data/log/php_error.log
	rm -rf src/install
	docker run --rm --interactive --tty --volume $(PWD):/app composer install --working-dir=src --no-progress --no-suggest --prefer-dist
	$(DOCKER_PHP_CONTAINER_EXEC) ./src/vendor/bin/phpunit --dont-report-useless-tests ./tests/modules/

build: ## Build App with Docker CI image
	docker run --rm \
	--mount type=bind,source=$(PWD),target=/opt -w /opt \
	-e TRAVIS_TAG \
	fordnox/docker-builder-ci \
	make release

release: ## App release
	echo "TRAVIS_BUILD_NUMBER:" $(TRAVIS_BUILD_NUMBER)
	echo "TRAVIS_TAG:" $(TRAVIS_TAG)
	npm install
	gulp build
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

build-run: ## Run app in LAMP container after build
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
