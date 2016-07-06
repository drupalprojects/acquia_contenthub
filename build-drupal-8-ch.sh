#!/bin/sh

# Make sure we exit if one of the commands fails.
set -e

# Make sure we were passed a project name for the directory.
if [ -z "$1" ]; then
  echo "You need to specify a directory name to build Drupal 8."
  exit
fi

composer create-project drupal-composer/drupal-project:~8.0 $1 --stability dev --no-interaction
cd $1
composer config repositories.acquia-content-hub-d8 vcs https://github.com/acquia/content-hub-d8
composer require acquia/content-hub-d8:dev-CHMS-769
