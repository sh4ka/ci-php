<?php

namespace luka8088\ci\test;

use \ArrayObject;
use \luka8088\ExtensionInterface;
use \luka8088\phops as op;

class Result {

  public $issues = [];

  function addIssue ($name, $message, $description = '') {
    $issue = new ArrayObject([
      'name' => $name,
      'message' => $message,
      'description' => $description,
    ]);
    $keep = true;
    op\metaContext(ExtensionInterface::class)["luka8088.ci.test.issueFound"]->__invoke($issue, $keep);
    if ($keep) {
      $this->issues[] = $issue;
      op\metaContext(ExtensionInterface::class)["luka8088.ci.test.issueReport"]->__invoke($issue);
    }
  }

}
