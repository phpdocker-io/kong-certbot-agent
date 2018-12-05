#!/usr/bin/env bash
apt-get update
apt-get install -y php-xdebug

cd code

composer -o install
vendor/bin/phpunit
vendor/bin/infection vendor/bin/infection --threads=2 -s --configuration=infection.json.dist --min-msi=88 --min-covered-msi=90
