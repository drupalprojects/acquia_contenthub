#!/bin/sh

# Make sure we exit if one of the commands fails.
set -e

# Make sure we were passed a project name for the directory.
if [ -z "$1" ]; then
  echo "You need to specify a directory name to build Drupal 8."
  echo "You can also specify an optional branch."
  echo "ie. ./build-drupal-8-ch.sh d8 CHMS-779"
  exit
fi

# project creation
if [ ! -d $1 ]; then
  composer create-project drupal-composer/drupal-project:~8.0 $1 --stability dev --no-interaction --profile
  cd $1
  composer config repositories.acquia-content-hub-d8 vcs https://github.com/acquia/content-hub-d8
else
  echo " using existing project $1..."
  cd $1
fi

# develop branch by default, or take the passed branch
branch="$2"
if [ "$2" == "" ]; then
  branch="develop"
fi

# setup the content-hub-d8 module and change branch if needed
if [ ! -d web/modules/contrib/content-hub-d8 ]; then
  echo " performing initial content-hub-d8 require"
  composer require acquia/content-hub-d8:dev-develop --profile
  cd web/modules/contrib/content-hub-d8
  git remote rm composer
else
  echo " using existing content-hub-d8 require"
  cd web/modules/contrib/content-hub-d8
  git fetch
  echo " checking out $branch"
  git checkout $branch
fi

# could include additional modules here ??

# back to the root
cd ../../../..