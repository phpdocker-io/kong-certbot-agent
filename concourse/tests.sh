#!/usr/bin/env bash
apt-get update
apt-get install -y php-xdebug

mkdir /tmp/reports/
cd code

composer -o install
vendor/bin/phpunit

# Placeholder for extracting coverage metric
echo "fo" > /tmp/reports/phpunit

ls -la vendor/bin
vendor/bin/infection --threads=2 -s --configuration=infection.json.dist --min-msi=60 --min-covered-msi=95

# Placeholder for extracting coverage metric
echo "fa" > /tmp/reports/infection
