#!/bin/sh

# Wrapper script combining a build followed by a Drupal site install.

# Make sure we were passed at least a project name.
if [ -z "$1" ]; then
  echo "You need to specify a project name to build Drupal 8."
  echo "You can also specify an optional branch, a destination and a Drupal core"
  echo "version."
  echo "ie. ./create-drupal-8-ch.sh d8 CHMS-814 /Users/you/sites/ 8.1.*"
  exit
fi

./build-drupal-8-ch.sh $1 $2 $3 $4 && ./install-drupal-8-ch.sh $1 $3
