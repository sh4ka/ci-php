<?php

use \luka8088\ci;

class Phlint {

  /**
   * Test default behavior.
   *
   * @test @internal
   */
  static function defaultBehavior () {

    ci\Test::mockFilesystem(sys_get_temp_dir() . '/okaagpi9zgzhv6e5jlqyujvoht76/', [
      '/phlint.configuration.php' => '<?php
        return function ($ci) {
          $ci->enableRule("all");
        };
      ',
      '/code/source.php' => '<?php
        class A {
          function foo () {
            $bar = $baz;
          }
        }
      ',
    ]);

    $ci = ci\Test::create(function ($ci) {
      $ci[] = new ci\test\tool\Phlint(sys_get_temp_dir() . '/okaagpi9zgzhv6e5jlqyujvoht76/phlint.configuration.php');
      $ci[] = new ci\test\filter\Path(function ($path, $line) { return $path . ($line ? ':' . $line : ''); });
      $ci->setParameter('rootPath', sys_get_temp_dir() . '/okaagpi9zgzhv6e5jlqyujvoht76/');
      $ci->addPath(sys_get_temp_dir() . '/okaagpi9zgzhv6e5jlqyujvoht76/code');
    });

    ci\Test::assertOutput($ci, 'test', '

      Running tests ...

      ✖ Phlint: Variable Initialization: $baz in method A::foo
        Variable `$baz` is used but it is not always initialized.
        Rule documentation: https://gitlab.com/phlint/phlint/blob/master/documentation/rule/variableInitialization.md

      ✔ Phlint: General

      ✖ Done with 1 failure(s), 0 error(s), 0 skipped and 1 success(es).

    ');

  }

}
