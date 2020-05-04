#!/usr/bin/env bash

# Echo all commands just before they are executed
set -x

# Stop processing this script if any of the commands we run return an error
# (i.e. exit with a non-zero status)
set -e

cd /var/www/html

# If we try to run phpunit as root it will drop back to the 'nobody' user so
# our test run will fail due to permissions errors
sudo -u www-data SYMFONY_DEPRECATIONS_HELPER=weak vendor/bin/phpunit --verbose modules/custom/
