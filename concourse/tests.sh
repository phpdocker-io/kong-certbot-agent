#!/usr/bin/env bash

# Ensure we exit with failure if anything here fails
set -e

# cd into the codebase, as per CI source
cd code

# Install xdebug & disable
apt-get update
apt-get install -y php-xdebug
phpdismod xdebug

# Static analysis
vendor/bin/phpstan -v analyse -l 7 src -c phpstan.neon  && printf "\n ${bold}PHPStan:${normal} static analysis good\n\n" || exit 1

# Store in here any test artifacts
mkdir /tmp/reports/

# Run unit tests
composer -o install
vendor/bin/phpunit --testdox

# Placeholder for extracting coverage metric
echo "fo" > /tmp/reports/phpunit

# Run mutation tests
vendor/bin/infection --initial-tests-php-options="-d zend_extension=xdebug.so" --threads=2 -s --min-msi=95 --min-covered-msi=95

# Placeholder for extracting coverage metric
echo "fa" > /tmp/reports/infection
