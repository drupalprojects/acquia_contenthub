#!/bin/sh

# Wrapper script combining a build followed by a Drupal site install.

./build-drupal-8-ch.sh $1 $2 && ./install-drupal-8-ch.sh $1
