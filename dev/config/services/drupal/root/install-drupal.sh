#!/usr/bin/env bash

# Echo all commands just before they are executed
set -x

# Stop processing this script if any of the commands we run return an error
# (i.e. exit with a non-zero status)
set -e

# The enironment variables in these commands are set in docker-compose.yml
drush site:install -vvv \
  --yes \
  --db-url=mysql://${MYSQL_CLIENT_USER}:${MYSQL_CLIENT_PASSWORD}@${MYSQL_CLIENT_HOST}:3306/${MYSQL_CLIENT_DATABASE} \
  -r /var/www/html \
  --account-name=$DRUPAL_ADMIN_USERNAME \
  --account-pass=$DRUPAL_ADMIN_PASSWORD \
  standard

# We have performed the install as root so change ownership of
# sites/default/files to allow Apache+PHP to write them
chown -R www-data.www-data /var/www/html/sites/default/files/

# 2020-04-18: This is a workaround for a bug in current Drupal 8 that causes it
# to fail after a drush `site:install`. If you are reading this and have a few
# minutes, please try standing these containers up without this hack so we can
# remove it.
drush -r /var/www/html cache:rebuild || echo "First cache:rebuild failed as it sometimes does for unknown reasons"
drush -r /var/www/html cache:rebuild

# Enable modules that make local development more pleasant
drush pm:enable devel,devel_generate,kint
# webprofiler has to be enabled **after** devel - it fails when they are in the
# same command for some reason.
drush pm:enable webprofiler

# Tell Drupal to load /var/www/html/sites/default/settings.local.php if it
# exists by appending the appropriate snippet to the main settings.php file
cat >> /var/www/html/sites/default/settings.php << 'END'

if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
END

# Create a helpful ~/.my.cnf to allow us to run the `mysql` client without any args
printf "[mysql]\nuser=$MYSQL_CLIENT_USER\npassword=$MYSQL_CLIENT_PASSWORD\nhost=$MYSQL_CLIENT_HOST\ndatabase=$MYSQL_CLIENT_DATABASE\n" > ~/.my.cnf
