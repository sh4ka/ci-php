<?php

namespace luka8088\ci\test\report;

use \Exception;
use \luka8088\ExtensionCall;
use \luka8088\phops as op;

class Issues {

  protected $output = null;
  protected $issues = [];

  function __construct ($output) {
    $this->output = $output;
  }

  /** @ExtensionCall("luka8088.ci.test.begin") */
  function begin () {
    $this->issues = [];
  }

  /** @ExtensionCall("luka8088.ci.test.testReport") */
  function testReport ($test) {
    if ($test['status'] == 'success')
      return;
    $this->issues[] = $test['name'];
  }

  /** @ExtensionCall("luka8088.ci.test.end") */
  function end () {
    rewind($this->output);
    sort($this->issues);
    foreach (array_unique($this->issues) as $issue)
      fwrite($this->output, $issue . "\n");
  }

}
