#!/bin/bash

# This script was adpated from https://docs.docker.com/config/containers/multi-service_container/

# Start PHP-FPM
echo "Attempting to start php-fpm"
php-fpm -D
status=$?
if [ $status -ne 0 ]; then
  echo "Failed to start php-fpm: $status"
  exit $status
fi

# Start nginx
echo "Attempting to start nginx (nginx is the strong, silent type. There will be no output if it succeeds)"
nginx
status=$?
if [ $status -ne 0 ]; then
  echo "Failed to start nginx: $status"
  exit $status
fi

# Naive check runs checks once a minute to see if either of the processes
# exited. The container exits with an error if it detects that either of the
# processes has exited. Otherwise it loops forever, waking up every 60 seconds

while sleep 60; do
  ps aux |grep "php-fpm: master" |grep -q -v grep
  PROCESS_1_STATUS=$?
  ps aux |grep "nginx: master" |grep -q -v grep
  PROCESS_2_STATUS=$?

  # If the greps above find anything, they exit with 0 status
  # If they are not both 0, then something is wrong
  if [ $PROCESS_1_STATUS -ne 0 -o $PROCESS_2_STATUS -ne 0 ]; then
    echo "One of the processes has already exited."
    exit 1
  fi
done
