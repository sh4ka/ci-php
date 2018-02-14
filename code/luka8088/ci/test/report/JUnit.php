<?php

namespace luka8088\ci\test\report;

use \Exception;
use \luka8088\ExtensionCall;
use \luka8088\phops as op;

class JUnit {

  protected $output = null;

  function __construct ($output) {
    $this->output = $output;
  }

  /** @ExtensionCall("luka8088.ci.test.begin") */
  function begin () {
    rewind($this->output);
    fwrite(
      $this->output,
      '<?xml version="1.0" encoding="UTF-8" ?>' . "\n" .
      '  <testsuites>' . "\n" .
      '    <testsuite name="Phlint">' . "\n" .
      '      <testcase name="OK"></testcase>' . "\n"
    );
  }

  /** @ExtensionCall("luka8088.ci.test.issueReport") */
  function issueReport ($issue) {
    fwrite(
      $this->output,
      '      <testcase name="' . self::xmlEncode($issue['name']) . '">' . "\n" .
      '        <failure message="' . self::xmlEncode($issue['message']) . '">' .
                self::xmlEncode($issue['description']) .
              '</failure>' . "\n" .
      '      </testcase>' . "\n"
    );
  }

  /** @ExtensionCall("luka8088.ci.test.end") */
  function end () {
    fwrite(
      $this->output,
      '  </testsuite>' . "\n" .
      '</testsuites>' . "\n"
    );
  }

  static function xmlEncode ($string) {
    return htmlspecialchars($string, ENT_QUOTES | ENT_DISALLOWED);
  }

}
