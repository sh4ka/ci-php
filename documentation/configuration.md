
Home: [Documentation](/documentation/index.md)


Configuration
=============

CI-PHP can be configured by placing a `ci.configuration.distributed.php` in the root path of the project.

Example `ci.configuration.distributed.php`:

```php
<?php

return function ($ci) {

    /**
     * Runs inline tests.
     * Scans the code for all functions/methods which have the attribute `@test`
     * in their PHPDoc blocks and runs each of them as a separate test.
     */
    $ci[] = new \luka8088\ci\test\tool\InlineTest();

    /**
     * Runs Phlint against the project.
     * @see https://gitlab.com/phlint/phlint
     */
    $ci[] = new \luka8088\ci\test\tool\Phlint(__dir__ . '/phlint.configuration.distributed.php');

    /**
     * Runs PHP Code Sniffer against the project.
     * @see https://github.com/squizlabs/PHP_CodeSniffer
     */
    $ci[] = new \luka8088\ci\test\tool\PHPCodeSniffer(__dir__ . '/phpcs.xml');

    /**
     * Runs PHP Coding Standards Fixer against the project.
     * @see https://github.com/FriendsOfPHP/PHP-CS-Fixer
     */
    $ci[] = new \luka8088\ci\test\tool\PHPCodingStandardsFixer(__dir__ . '/.php_cs.dist');

    /**
     * Runs PHP Mess Detector against the project.
     * @see https://phpmd.org/
     */
    $ci[] = new \luka8088\ci\test\tool\PHPMessDetector(__dir__ . '/phpmd.xml');

    /**
     * Runs PHPUnit against the project.
     * @see https://phpunit.de/
     */
    $ci[] = new \luka8088\ci\test\tool\PHPUnit(__dir__ . '/phpunit.xml');

    /**
     * Filters out known issues from the report.
     * The path provided is expected to be a file with a list of test names (one per line)
     * which are known to fail.
     * This method might also be used to get a clean start when introducing various
     * code analysis tools to a legacy project which would yield too many failures.
     */
    $ci[] = new \luka8088\ci\test\filter\KnownIssues(__dir__ . '/.knownIssues');

    /**
     * Sets the rootPath. This will be a used as a reference point when
     * resolving relative paths.
     */
    $ci->setParameter('rootPath', __dir__);

    /**
     * Whether to successes in the console - defaults to true.
     * Some might want to turn this off to reduce the amount of output.
     */
    $ci->setParameter('console.showSuccesses', false);

    /**
     * Path to scan for code. Multiple paths can be provided by
     * calling `addPath` multiple times.
     */
    $ci->addPath(__dir__ . '/src');

};
```
