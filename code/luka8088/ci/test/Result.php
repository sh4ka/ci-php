<?php

namespace luka8088\ci\test;

use \ArrayObject;
use \luka8088\ExtensionInterface;
use \luka8088\phops as op;

class Result {

  public $tests = [];

  function addTest ($status, $name, $message) {
    $test = new ArrayObject([
      'status' => $status,
      'name' => $name,
      'message' => $message,
    ]);
    $keep = true;
    op\metaContext(ExtensionInterface::class)["luka8088.ci.test.testFound"]->__invoke($test, $keep);
    if ($keep) {
      $this->tests[] = $test;
      op\metaContext(ExtensionInterface::class)["luka8088.ci.test.testReport"]->__invoke($test);
    }
  }

}
