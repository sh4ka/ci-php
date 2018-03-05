<?php

use \luka8088\ci;

class PHPCodingStandardsFixerTest {

  /**
   * Test default behavior.
   *
   * @test @internal
   */
  static function default () {

    ci\Test::mockFilesystem(sys_get_temp_dir() . '/kibzsumvcs7sn33vodq9lnhtvsmu/', [
      '/.php_cs.dist' =>
        '<?php
          return PhpCsFixer\Config::create()
            ->setCacheFile(
              ' . var_export(sys_get_temp_dir() . '/kibzsumvcs7sn33vodq9lnhtvsmu/', true) . ' . "/.php_cs.cache"
            )
            ->setRules([
                "@PSR2" => true,
                "@Symfony" => true,
            ])
        ;
        ',
      '/code/source.php' => '<?php
        class A
        {
          public function foo ()
          {
            $bar = 1;
          }
        }
      ',
    ]);

    $ci = ci\Test::create(function ($ci) {
      $ci[] = new ci\test\tool\PHPCodingStandardsFixer(sys_get_temp_dir() . '/kibzsumvcs7sn33vodq9lnhtvsmu/.php_cs.dist');
      $ci[] = new ci\test\filter\Path(function ($path, $line) { return $path . ($line ? ':' . $line : ''); });
      $ci->setRootPath(sys_get_temp_dir() . '/kibzsumvcs7sn33vodq9lnhtvsmu/');
      $ci->addPath(sys_get_temp_dir() . '/kibzsumvcs7sn33vodq9lnhtvsmu/code');
    });

    ci\Test::assertOutput($ci, 'test', '

      Running tests ...

      ✖ PHP Coding Standards Fixer: Coding Standards in code/source.php
        Fixes that need to be applied: function_declaration, no_whitespace_in_blank_line, braces

      ✖ Done with 1 failure(s), 0 error(s) and 0 success(es).

    ');

  }

  /**
   * Test default behavior with verbose output.
   *
   * @test @internal
   */
  static function defaultVerbose () {

    ci\Test::mockFilesystem(sys_get_temp_dir() . '/kibzsumvcs7sn33vodq9lnhtvsmu/', [
      '/.php_cs.dist' =>
        '<?php
          return PhpCsFixer\Config::create()
            ->setCacheFile(
              ' . var_export(sys_get_temp_dir() . '/kibzsumvcs7sn33vodq9lnhtvsmu/', true) . ' . "/.php_cs.cache"
            )
            ->setRules([
                "@PSR2" => true,
                "@Symfony" => true,
            ])
        ;
        ',
      '/code/source.php' => '<?php
        class A
        {
          public function foo ()
          {
            $bar = 1;
          }
        }
      ',
    ]);

    $ci = ci\Test::create(function ($ci) {
      $ci[] = new ci\test\tool\PHPCodingStandardsFixer(sys_get_temp_dir() . '/kibzsumvcs7sn33vodq9lnhtvsmu/.php_cs.dist');
      $ci[] = new ci\test\filter\Path(function ($path, $line) { return $path . ($line ? ':' . $line : ''); });
      $ci->setRootPath(sys_get_temp_dir() . '/kibzsumvcs7sn33vodq9lnhtvsmu/');
      $ci->addPath(sys_get_temp_dir() . '/kibzsumvcs7sn33vodq9lnhtvsmu/code');
    });

    ci\Test::assertOutput($ci, 'test -v', '

      Running tests ...

      ✖ PHP Coding Standards Fixer: Coding Standards in code/source.php
        Fixes that need to be applied: function_declaration, no_whitespace_in_blank_line, braces
        --- Original
        +++ New
        @@ @@
         <?php
                 class A
                 {
        -          public function foo ()
        -          {
        -            $bar = 1;
        -          }
        +            public function foo()
        +            {
        +                $bar = 1;
        +            }
                 }
        -
        +

      ✖ Done with 1 failure(s), 0 error(s) and 0 success(es).

    ');

  }

}
