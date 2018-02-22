<?php

use \luka8088\ci;

class PHPMessDetectorTest {

  /**
   * Test default behavior.
   *
   * @test @internal
   */
  static function default () {

    ci\Test::mockFilesystem(sys_get_temp_dir() . '/kibzsumvcs7sn33vodq9lnhtvsmu/', [
      '/phpmd.xml' =>
        '<?xml version="1.0" encoding="UTF-8" ?>
          <ruleset>
            <rule ref="rulesets/unusedcode.xml"/>
          </ruleset>
        ',
      '/code/source.php' => '<?php
        class A {
          function foo () {
            $bar = 1;
          }
        }
      ',
    ]);

    $ci = ci\Test::create(function ($ci) {
      $ci[] = new ci\test\tool\PHPMessDetector(sys_get_temp_dir() . '/kibzsumvcs7sn33vodq9lnhtvsmu/phpmd.xml');
      $ci[] = new ci\test\filter\Path(function ($path, $line) { return $path . ($line ? ':' . $line : ''); });
      $ci->setRootPath(sys_get_temp_dir() . '/kibzsumvcs7sn33vodq9lnhtvsmu/');
      $ci->addPath(sys_get_temp_dir() . '/kibzsumvcs7sn33vodq9lnhtvsmu/code');
    });

    ci\Test::assertOutput($ci, 'test', '

      Running tests ...

      ✖ PHP Mess Detector: UnusedLocalVariable at A::foo
        code/source.php:4: Avoid unused local variables such as \'$bar\'.
        Rule documentation: https://phpmd.org/rules/unusedcode.html#unusedlocalvariable

      ✖ Done with 1 failure(s), 0 error(s) and 0 success(es).

    ');

  }

}
