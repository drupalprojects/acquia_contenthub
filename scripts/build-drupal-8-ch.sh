#!/bin/sh

# build and rebuild a d8 sites w/ our module(s)
# ./build-drupal-8-ch.sh dd
# ./build-drupal-8-ch.sh dd CHMS-814

# Make sure we exit if one of the commands fails.
set -e

# Make sure we were passed a project name for the directory.
if [ -z "$1" ]; then
  echo "You need to specify a directory name to build Drupal 8."
  echo "You can also specify an optional branch."
  echo "ie. ./build-drupal-8-ch.sh d8 CHMS-814"
  exit
fi

# use develop branch by default, or take the passed branch
branch="$2"
if [ "$2" == "" ]; then
  branch="develop"
fi

# project creation
if [ ! -d $1 ]; then

  # dont install yet, just create
  composer create-project drupal-composer/drupal-project:~8.0 $1 --stability dev --no-interaction --no-install --prefer-dist --profile

  cd $1

  echo "> setting the vendor path to be inside the web path"
  cp composer.json composer_orig.json
  php ../revendor.php

  echo "> adjusting config settings"
  composer config --global repo.packagist composer https://packagist.org
  # this can be undone down the road
  # ugh, see https://github.com/drupal-composer/drupal-project/issues/175
  composer config --global repositories.0 composer https://packages.drupal.org/8
  composer config repositories.acquia-content-hub-d8 vcs https://github.com/acquia/content-hub-d8

  echo "> composer update"
  composer update --profile
else
  echo " using existing project $1..."
  cd $1
fi

# setup the content-hub-d8 module and change branch if needed
if [ ! -d web/modules/contrib/content-hub-d8 ]; then
  echo "> composer update for content-hub-d8"
  composer require acquia/content-hub-d8:dev-develop --profile
  echo "> removing composer remote"
  cd web/modules/contrib/content-hub-d8
  git remote rm composer
else
  echo "> using existing content-hub-d8"
  cd web/modules/contrib/content-hub-d8
  git fetch
  echo "> checking out $branch"
  git checkout $branch
  git pull
fi

# could include additional modules here
# todo: add modules here

# back to the root
cd ../../../../..

# now go into $1/web and create a tarball
if [ -a ./$1.tar ]; then
 rm $1.tar
fi
cd $1/web
tar -cf ../$1.tar .
cd ../..

# todo: in the future trigger automatically
# for now just run ./deploy.sh after wards, but configure it for yourself
# # also dereploy locally, and run site standup is there is a sites.$1 match
# if [ -s ./deploy.$1.sh ]; then
#   echo "> found ./deploy.$1 ..."
# #  sh deploy.$1.sh
# else
#   echo "> no ./deploy.$1.sh found. all done."
# fi
