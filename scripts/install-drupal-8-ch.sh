#!/bin/sh

# build and rebuild a d8 sites w/ our module(s)
# ./build-drupal-8-ch.sh dd
# ./build-drupal-8-ch.sh dd CHMS-814

# Make sure we exit if one of the commands fails.
set -e

# Import local settings.
source .env

PROJECT_NAME=$1

if [ ! -z $2 ]; then
  SITE_PATH=$2/$PROJECT_NAME
else
  SITE_PATH=$PROJECT_NAME
fi



# Make sure we were passed a project name for the directory.
if [ -z "$PROJECT_NAME" ]; then
  echo "You need to specify a directory name where the build is."
  exit
fi

if [ ! -d $SITE_PATH ]; then
  echo "The directory $SITE_PATH does not exist."
  exit
fi

cd $SITE_PATH/web

# MySQL has trouble with dashes in database names.
DB_URL="$DRUPAL_DB_URL${PROJECT_NAME//-/_}"
drush si -y --account-name=$DRUPAL_ADMIN_NAME --account-pass=$DRUPAL_ADMIN_PASSWORD --db-url=$DB_URL
drush en -y acquia_contenthub

exit
