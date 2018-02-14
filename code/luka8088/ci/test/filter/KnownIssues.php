<?php

namespace luka8088\ci\test\filter;

use \Exception;
use \luka8088\ExtensionCall;

class KnownIssues {

  public $knownIssues = [];

  function __construct ($knownIssues) {
    if (!is_file($knownIssues))
      throw new Exception('Known issues file *' . $knownIssues . '* not found.');
    foreach (preg_split('/\r?\n/s', file_get_contents($knownIssues)) as $knownIssue)
      if (trim($knownIssue))
        $this->knownIssues[] = $knownIssue;
  }

  /** @ExtensionCall("luka8088.ci.test.issueFound") */
  function issueFound ($issue, &$keep) {
    if (in_array($issue['name'], $this->knownIssues))
      $keep = false;
  }

}
