#!/usr/bin/env bash
apt-get update
apt-get install -y php-xdebug

cd kong-certbot-agent

composer -o install
vendor/bin/phpunit
