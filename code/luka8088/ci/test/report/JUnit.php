<?php

namespace luka8088\ci\test\report;

use \Exception;
use \luka8088\ExtensionCall;
use \luka8088\phops as op;

class JUnit {

  protected $output = null;
  protected $issueToolMap = [];

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
    $this->issueToolMap = [];
  }

  /** @ExtensionCall("luka8088.ci.test.issueReport") */
  function issueReport ($issue) {
    $this->issueToolMap[trim(substr($issue['name'], 0, strpos($issue['name'], ':')))][] = $issue;
  }

  /** @ExtensionCall("luka8088.ci.test.end") */
  function end () {

    foreach ($this->issueToolMap as $tool => $issues) {
      fwrite(
        $this->output,
        '  <testsuite name="' . self::xmlEncode($tool) . '">' . "\n"
      );
      foreach ($issues as $issue)
        fwrite(
          $this->output,
          '      <testcase name="' .
                      self::xmlEncode(trim(substr($issue['name'], strpos($issue['name'], ':') + 1))) . '">' . "\n" .
          '        <failure message="' . self::xmlEncode($issue['message']) . '">' .
                    self::xmlEncode($issue['description']) .
                  '</failure>' . "\n" .
          '      </testcase>' . "\n"
        );
      if (count($issues) == 0)
        fwrite(
          $this->output,
          '    <testcase name="OK"></testcase>' . "\n"
        );
      fwrite(
        $this->output,
        '  </testsuite>' . "\n"
      );
    }

    if (count($this->issueToolMap) == 0)
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
