#!/usr/bin/env bash

# Ensure we exit with failure if anything here fails
set -e

INITIAL_FOLDER=`pwd`

# cd into the codebase, as per CI source
cd code
mkdir reports

PHP_VERSION=$(php -r "echo preg_replace('/.[0-9]+(-.*)?$/', '', phpversion());")
echo "Detected PHP Version: ${PHP_VERSION}"

# Install xdebug & disable
apt-get update
apt-get install -y php${PHP_VERSION}-xdebug
phpdismod xdebug

composer -o install

# Static analysis
vendor/bin/phpstan -v analyse -l 7 src && printf "\n ${bold}PHPStan:${normal} static analysis good\n\n" || exit 1

# Run unit tests
XDEBUG_MODE=coverage php -d zend_extension=xdebug.so vendor/bin/phpunit --testdox

# Run mutation tests
vendor/bin/infection --coverage=reports/infection --threads=2 -s --min-msi=100 --min-covered-msi=100

# Go back to initial working dir to allow outputs to function
cd ${INITIAL_FOLDER}

# Copy reports to output
cp code/reports/* coverage-reports/ -Rf
