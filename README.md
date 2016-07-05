Install Drupal 8

To build a Drupal 8 site with a git clone of the Drupal 8 Content Hub module, make sure
you run the following script in the location where your site is going to live (there is an
issue drush otherwise, see https://docs.google.com/document/d/1YpxekJkk0M4LXMH8BBhiOsUlVZ99DMAG3D-vIpoWXxY/edit).
When executing the script, provide the name of your project:

```
sh build-drupal-8-ch.sh myproject
```

Once the script is complete, go into the myproject directory. Drupal was built into the
directory called 'web'. A git clone of the content-hub-d8 repository will have been
created in the modules directory. Install Drupal using Drush or by browsing to your Drupal site URL.
```
cd web
drush site-install --db-url=mysql://{username}:{password}@localhost/{database}
```

If using DevDesktop, the last command can look like this:
```
drush si -y --db-url=mysql://root:@127.0.0.1:33067/newdb
```


To run PHPUnit tests.

We assume that the module lives under modules/content_hub_drupal/.
We assume Xdebug is enabled, if it is not, please remove the coverage-html instruction.

```
cd modules/content_hub
../../vendor/bin/phpunit .  --debug --verbose --coverage-html ~/contenthub_coverage
```

If you want automatic code standards checking, please run composer install in the content_hub_connector module or do:

```
if [ -x .git/hooks ]; then cp content_hub_connector/pre-commit.dist .git/hooks/pre-commit; fi
```
