
Home: [Documentation](/documentation/index.md)


Configuration
=============

CI-PHP can be configured by placing a `ci.configuration.distributed.php` in the root path of the project.

Example `ci.configuration.distributed.php`:

```php
<?php

return function ($ci) {

    // Run inline tests.
    $ci[] = new \luka8088\ci\test\tool\InlineTest();

    // Paths to scan for code.
    $ci->addPath(__dir__ . '/src');

};
```
