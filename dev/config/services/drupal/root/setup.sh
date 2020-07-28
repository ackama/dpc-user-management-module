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
  standard install_configure_form.enable_update_status_module=NULL install_configure_form.enable_update_status_emails=NULL

# We have performed the install as root so change ownership of
# sites/default/files to allow Apache+PHP to write them
chown -R www-data.www-data /var/www/html/sites/default/files/

# Enable modules that make local development more pleasant
drush --yes pm:enable devel,devel_generate,kint

# webprofiler has to be enabled **after** devel - it fails when they are in the
# same command for some reason.
#
# As of 2020-05-04 having Webprofiler enabled prevents us from activating
# modules which depend on 'Group' i.e. our module. For this reason we do not
# activate Webprofiler. If you are reading this then this issue may have been
# fixed - if so, you can re-enabled Webprofiler. Details are in:
#
# * https://www.drupal.org/project/group/issues/3103716
# * https://www.drupal.org/project/group/issues/3103884
#
# drush --yes pm:enable webprofiler

# Enable Admin Toolbar goodies
drush --yes pm:enable admin_toolbar
drush --yes pm:enable admin_toolbar_tools
drush --yes pm:enable admin_toolbar_search

# Enable our module's Drupal dependencies (they are installed via composer)
drush --yes pm:enable group

# Enable phpmailer smtp and mailsystem
# Configures both to route emails to mailhog docker service
# drush --yes pm:enable phpmailer_smtp mailsystem
# drush config:set --yes mailsystem.settings defaults.sender phpmailer_smtp
# drush config:set --yes mailsystem.settings defaults.formatter phpmailer_smtp
# drush config:set --yes phpmailer_smtp.settings smtp_host mailhog
# drush config:set --yes phpmailer_smtp.settings smtp_port 1025

# Rebuild drupal cache after installing Drupal and enabling plugins. Cache
# rebuilds sometimes fail the first time for no apparent reason so we run with
# `|| true` to swallow that failure
drush --yes cache:rebuild || true
drush --yes cache:rebuild || true

npm ci --prefix /var/www/html/modules/custom/dpc_user_management

# Tell Drupal to load /var/www/html/sites/default/settings.local.php if it
# exists by appending the appropriate snippet to the main settings.php file
cat >> /var/www/html/sites/default/settings.php << 'END'

if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
END

# Create a helpful ~/.my.cnf to allow us to run the `mysql` client without any args
printf "[mysql]\nuser=$MYSQL_CLIENT_USER\npassword=$MYSQL_CLIENT_PASSWORD\nhost=$MYSQL_CLIENT_HOST\ndatabase=$MYSQL_CLIENT_DATABASE\n" > ~/.my.cnf
