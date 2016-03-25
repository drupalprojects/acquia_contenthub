To run PHPUnit tests.

We assume that the module lives under modules/content_hub.
We assume Xdebug is enabled, if it is not, please remove the coverage-html instruction.

cd modules/content_hub
../../vendor/bin/phpunit .  --debug --verbose --coverage-html ~/contenthub_coverage

If you want automatic code standards checking, please run composer install in the content_hub_connector module or do:

```
if [ -x .git/hooks ]; then cp content_hub_connector/pre-commit.dist .git/hooks/pre-commit; fi
```