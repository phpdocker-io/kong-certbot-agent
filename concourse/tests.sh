#!/usr/bin/env bash

# Ensure we exit with failure if anything here fails
set -e

# cd into the codebase, as per CI source
cd code

# Install xdebug & disable
apt-get update
apt-get install -y php-xdebug
phpdismod xdebug

# Store in here any test artifacts
mkdir /tmp/reports/

# Run unit tests
composer -o install
vendor/bin/phpunit

# Placeholder for extracting coverage metric
echo "fo" > /tmp/reports/phpunit

# Run mutation tests
vendor/bin/infection --initial-tests-php-options="-d zend_extension=xdebug.so" --threads=2 -s --min-msi=60 --min-covered-msi=95

# Placeholder for extracting coverage metric
echo "fa" > /tmp/reports/infection
