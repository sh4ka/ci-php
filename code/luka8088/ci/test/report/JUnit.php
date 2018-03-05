<?php

namespace luka8088\ci\test\report;

use \Exception;
use \luka8088\ExtensionCall;
use \luka8088\phops as op;

class JUnit {

  protected $output = null;
  protected $testToolMap = [];

  function __construct ($output) {
    $this->output = $output;
  }

  /** @ExtensionCall("luka8088.ci.test.begin") */
  function begin () {
    rewind($this->output);
    fwrite(
      $this->output,
      '<?xml version="1.0" encoding="UTF-8" ?>' . "\n" .
      '<testsuites>' . "\n"
    );
    $this->testToolMap = [];
  }

  /** @ExtensionCall("luka8088.ci.test.testReport") */
  function testReport ($test) {
    $this->testToolMap[trim(substr($test['name'], 0, strpos($test['name'], ': ')))][] = $test;
  }

  /** @ExtensionCall("luka8088.ci.test.end") */
  function end () {

    foreach ($this->testToolMap as $tool => $tests) {
      fwrite(
        $this->output,
        '  <testsuite name="' . self::xmlEncode($tool) . '">' . "\n"
      );
      foreach ($tests as $test)
        fwrite(
          $this->output,
          '      <testcase name="' .
                      self::xmlEncode(trim(substr($test['name'], strpos($test['name'], ': ') + 1))) . '">' . "\n" .
          ($test['status'] == 'failure'
            ? '        <failure message="' . self::xmlEncode($test['message']) . '">' .
                        self::xmlEncode($test['message'] . ($test['description'] ? "\n" . $test['description'] : '')) .
                      '</failure>' . "\n"
            : ($test['status'] == 'error'
            ? '        <error message="' . self::xmlEncode($test['message']) . '">' .
                        self::xmlEncode($test['message'] . ($test['description'] ? "\n" . $test['description'] : '')) .
                      '</error>' . "\n"
            : ''
          )) .
          '      </testcase>' . "\n"
        );
      if (count($tests) == 0)
        fwrite(
          $this->output,
          '    <testcase name="OK"></testcase>' . "\n"
        );
      fwrite(
        $this->output,
        '  </testsuite>' . "\n"
      );
    }

    if (count($this->testToolMap) == 0)
      fwrite(
        $this->output,
        '  <testsuite name="Sanity">' . "\n" .
        '    <testcase name="OK"></testcase>' . "\n" .
        '  </testsuite>' . "\n"
      );

    fwrite($this->output, '</testsuites>' . "\n");
  }

  static function xmlEncode ($string) {
    $encoded = htmlspecialchars($string, ENT_QUOTES | ENT_DISALLOWED);
    $encoded = str_replace("\n", "&#10;", $encoded);
    $encoded = str_replace("\r", "&#13;", $encoded);
    return $encoded;
  }

}
