Install Drupal 8

The scripts directory includes scripts to build and install Content Hub and Drupal 8.

To build and install a Drupal 8 site with a git clone of the Drupal 8 Content Hub module, make sure
you run the following script in the location where your site is going to live (there is an
issue drush otherwise, see https://docs.google.com/document/d/1YpxekJkk0M4LXMH8BBhiOsUlVZ99DMAG3D-vIpoWXxY/edit).

In order for the script to install Drupal, you need to setup some local settings.
Copy the example.env file to .env and update it.

The main script will do everything for you (build and install). Just provide the
name of the directory the build should go into:
```
create-drupal-8-ch.sh MYPROJECT
```
If you just want to build a codebase, or install Drupal on an existing codebase,
you can run any of these scripts:
```
build-drupal-8-ch.sh MYPROJECT
```
```
install-drupal-8-ch.sh MYPROJECT
```

To run PHPUnit tests.

We assume that the module lives under modules/content_hub_drupal/.
We assume Xdebug is enabled, if it is not, please remove the coverage-html instruction.

```
cd modules/content_hub
../../vendor/bin/phpunit .  --debug --verbose --coverage-html ~/contenthub_coverage
```

If you want automatic code standards checking, please run composer install in the acquia_contenthub module or do:

```
if [ -x .git/hooks ]; then cp acquia_contenthub/pre-commit.dist .git/hooks/pre-commit; fi
```
