#!/bin/sh

# Wrapper script combining a build followed by a Drupal site install.

./build-drupal-8-ch.sh $1 $2 $3

if [ ! -z $3 ]; then
  SITE_PATH=$3/$1
else
  SITE_PATH=$1
fi

echo "> installing in $SITE_PATH"
./install-drupal-8-ch.sh $SITE_PATH
