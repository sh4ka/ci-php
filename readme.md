
CI-PHP - PHP Continuous Integration assistant
=============================================


Introduction
------------

CI-PHP is a tool made to assist with continuous integration workflows.


Getting Started
---------------

CI-PHP can be included in the project through composer:

To install CI-PHP run:
```bash
composer require luka8088/ci
```

CI-PHP can be configured by placing a `ci.configuration.distributed.php` in the root path of the project.

Example `ci.configuration.distributed.php`:

```php
<?php

return function ($ci) {

    // Runs inline tests.
    $ci[] = new \luka8088\ci\test\tool\InlineTest();

    // Filters out known issues from the report.
    $ci[] = new \luka8088\ci\test\filter\KnownIssues(__dir__ . '/.knownIssues');

    // Paths to scan for code.
    $ci->addPath(__dir__ . '/src');

};
```

For a more verbose configuration example check the [configuration](/documentation/configuration.md) page.

To invoke CI-PHP run:
```bash
./vendor/bin/ci
```


Documentation
-------------

For a full documentation visit [CI-PHP documentation page](/documentation/index.md).
For a contribution guidelines visit [Contributing guidelines page](/contributing.md).


License
-------

Ci-PHP is licensed under the [MIT license](/license.txt).
