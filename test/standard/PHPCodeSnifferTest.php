<?php

use \luka8088\ci;

class PHPCodeSnifferTest {

  /**
   * Test default behavior.
   *
   * @test @internal
   */
  static function defaultBehavior () {

    ci\Test::mockFilesystem(sys_get_temp_dir() . '/jvspttke0femf1b9uclankckhxdt/', [
      '/phpcs.xml' =>
        '<?xml version="1.0" encoding="UTF-8" ?>
          <ruleset>
            <rule ref="PSR2" />
          </ruleset>
        ',
      '/code/source.php' => '<?php
        function foo {}
      ',
    ]);

    $ci = ci\Test::create(function ($ci) {
      $ci[] = new ci\test\tool\PHPCodeSniffer(sys_get_temp_dir() . '/jvspttke0femf1b9uclankckhxdt/phpcs.xml');
      $ci[] = new ci\test\filter\Path(function ($path, $line) { return $path . ($line ? ':' . $line : ''); });
      $ci->setParameter('rootPath', sys_get_temp_dir() . '/jvspttke0femf1b9uclankckhxdt/');
      $ci->addPath(sys_get_temp_dir() . '/jvspttke0femf1b9uclankckhxdt/code');
    });

    ci\Test::assertOutput($ci, 'test', '

      Running tests ...

      ✖ PHP Code Sniffer: Generic.WhiteSpace.ScopeIndent.IncorrectExact at code/source.php
        Generic.WhiteSpace.ScopeIndent.IncorrectExact at code/source.php (2:9):
          Line indented incorrectly; expected 0 spaces, found 8

      ✖ PHP Code Sniffer: Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore at code/source.php
        Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore at code/source.php (2:23):
          Closing brace must be on a line by itself

      ✖ PHP Code Sniffer: PSR2.Files.EndFileNewline.NoneFound at code/source.php
        PSR2.Files.EndFileNewline.NoneFound at code/source.php (3:1):
          Expected 1 newline at end of file; 0 found

      ✔ PHP Code Sniffer: General

      ✖ Done with 3 failure(s), 0 error(s), 0 skipped and 1 success(es).

    ');

  }

}
