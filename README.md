To run PHPUnit tests.

We assume that the module lives under modules/content_hub.
We assume Xdebug is enabled, if it is not, please remove the coverage-html instruction.

cd modules/content_hub
../../vendor/bin/phpunit .  --debug --verbose --coverage-html ~/contenthub_coverage

