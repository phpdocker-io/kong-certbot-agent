#SHELL=/bin/bash
#MKCERT_VERSION=v1.4.3
#MKCERT_LOCATION=bin/mkcert
#HOSTS_VERSION=3.6.4
#HOSTS_LOCATION=bin/hosts
#SITE_HOST=phpdocker.local
PHP_RUN=docker run --rm -e XDEBUG_MODE=coverage --rm phpdockerio/php80-cli
#
#BUILD_TAG?:=$(shell date +'%Y-%m-%d-%H-%M-%S')-$(shell git rev-parse --short HEAD)
#
## linux-amd64, darwin-amd64, linux-arm
## On windows, override with windows-amd64.exe
#ifndef BINARY_SUFFIX
#	BINARY_SUFFIX:=$(shell [[ "`uname -s`" == "Linux" ]] && echo linux || echo darwin)-amd64
#endif
#
#ifndef BUILD_TAG
#	BUILD_TAG:=$(shell date +'%Y-%m-%d-%H-%M-%S')-$(shell git rev-parse --short HEAD)
#endif
#
#echo-build-tag:
#	echo $(BUILD_TAG)
#	sleep 3
#
#echo-build-tag-2:
#	echo $(BUILD_TAG)
#
#start:
#	docker-compose up -d --scale php-fpm=2
#
#stop:
#	docker-compose stop
#
#shell:
#	$(PHP_RUN) bash
#
#init: clean install-mkcert create-certs install-hosts clean-hosts init-hosts install-dependencies install-assets-dev fix-permissions fix-cache-permissions-dev start
#
#clean: clear-cache
#	docker-compose down
#	sudo rm -rf vendor
#	make clear-cache
#
#fix-permissions:
#	sudo chown -Rf $(shell id -u):$(shell id -g) .
#	sudo chown -Rf $(shell id -u):$(shell id -g) ~/.cache/composer
#
#fix-cache-permissions-dev:
#	sudo chmod -Rf 777 var/*
#
#clear-cache:
#	$(PHP_RUN) rm var/* -rf
#
#install-assets-dev:
#	mkdir -p web/bundles web/css web/js
#	$(PHP_RUN) bin/console assets:install --symlink --relative
#
composer-install:
	$(PHP_RUN) composer -o install
#
#bower-install:
#	docker run  \
#	    --rm \
#	    -t \
#	    -v "`pwd`:/workdir" \
#	    -w /workdir \
#	    node:alpine \
#	    sh -c "apk update; apk add git; npm i -g bower; bower install --allow-root"
#
#install-dependencies: composer-install bower-install
#
#composer-update:
#	$(PHP_RUN) composer update --no-scripts
#	make composer-install
#
#install-mkcert:
#	@echo "Installing mkcert for OS type ${BINARY_SUFFIX}"
#	@if [[ ! -f '$(MKCERT_LOCATION)' ]]; then curl -sL 'https://github.com/FiloSottile/mkcert/releases/download/$(MKCERT_VERSION)/mkcert-$(MKCERT_VERSION)-$(BINARY_SUFFIX)' -o $(MKCERT_LOCATION); chmod +x $(MKCERT_LOCATION);	fi;
#	bin/mkcert -install
#
#create-certs:
#	bin/mkcert -cert-file=infrastructure/local/localhost.pem -key-file=infrastructure/local/localhost-key.pem $(SITE_HOST)
#
#install-hosts:
#	@echo "Installing hosts script"
#	@if [[ ! -f '$(HOSTS_LOCATION)' ]]; then curl -sL 'https://raw.githubusercontent.com/xwmx/hosts/$(HOSTS_VERSION)/hosts' -o $(HOSTS_LOCATION); chmod +x $(HOSTS_LOCATION);	fi;
#
#clean-hosts:
#	sudo bin/hosts remove --force *$(SITE_HOST) > /dev/null 2>&1 || exit 0
#
#init-hosts: clean-hosts
#	sudo bin/hosts add 127.0.0.1 $(SITE_HOST)
#
#open-frontend:
#	xdg-open https://$(SITE_HOST):10000
#
#### Tests & ci
prep-ci: composer-install

#behaviour:
#	$(PHP_RUN) vendor/bin/behat --colors
#
static-analysis:
	$(PHP_RUN) vendor/bin/phpstan --ansi -v analyse -l 8 src

unit-tests:
	$(PHP_RUN) vendor/bin/phpunit --testdox --colors=always

coverage-tests:
	$(PHP_RUN) php -d zend_extension=xdebug.so vendor/bin/phpunit --testdox --colors=always

mutation-tests:
	$(PHP_RUN) vendor/bin/infection --coverage=reports/infection --threads=2 -s --min-msi=0 --min-covered-msi=0

#open-coverage-report:
#	xdg-open reports/phpunit/index.html
#
#### Deployment targets
#build-and-push:
#	docker buildx build --target=backend-deployment  --tag eu.gcr.io/auron-infrastructure/phpdocker-php-fpm:$(BUILD_TAG) --platform linux/arm/v7 --pull --push .
#	docker buildx build --target=frontend-deployment --tag eu.gcr.io/auron-infrastructure/phpdocker-nginx:$(BUILD_TAG)   --platform linux/arm/v7 --pull --push .
#
#deploy:
#	cp infrastructure/kubernetes/deployment.yaml /tmp/phpdocker-deployment-$(BUILD_TAG).yaml
#	sed -i "s/latest/$(BUILD_TAG)/g" /tmp/phpdocker-deployment-$(BUILD_TAG).yaml
#
#	kubectl apply -f /tmp/phpdocker-deployment-$(BUILD_TAG).yaml
#	rm /tmp/phpdocker-deployment-$(BUILD_TAG).yaml
#
#rollback:
#	kubectl rollout undo deployment.v1.apps/phpdocker-io-old
