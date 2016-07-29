#!/bin/sh

# Make sure we exit if one of the commands fails.
set -e

# declare each of the sites to update
sites="/path/to_site_a /path/to_site_b /some/other/path"


sites="/Users/matthew.morley/Sites/devdesktop/drupal-8-a /Users/matthew.morley/Sites/devdesktop/drupal-8-b /Users/matthew.morley/Sites/devdesktop/zw/docroot"

# whatever drupal d8 thing was built via build-drupal-8-ch.sh
project="dd"

# remember where we, so we can returns
base=`pwd`


# for each site listed about
for docroot in $sites; do
  echo ""
  echo "----------------------------------------------------"
  echo " working on $docroot"

  # backup the site as it is already
  now=`date "+%Y%m%d-%H%M%S"`
  tar -cf $now.tar $docroot
  gzip $now.tar

  cd $docroot

  if [ -d sites ]; then
    echo " working in $docroot"
    mv ./sites ./.sites
    rm -rf *

    echo " copying recent build into place"
    tar xBf $base/$project/$project.tar

    echo " restoring the original sites directory"
    mv ./.sites ./sites

    echo " doing a site install"
    drush status

    # todo
    # echo " do a site install"
    # drush site-install standard --account-mail="matthew.morley@acquia.com" --account-name=admin443 --account-pass=admin883 --site-name="$project" -y

  else
    echo " $docroot has no sites directory"
    if [ ! -d .sites ]; then
        echo "   rename .sites to sites"
    fi
  fi
  echo ""
  cd $base
done