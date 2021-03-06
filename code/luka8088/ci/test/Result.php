<?php

namespace luka8088\ci\test;

use \ArrayObject;
use \luka8088\ExtensionInterface;
use \luka8088\phops\MetaContext;

class Result {

  public $runningTime = 0;
  public $memoryUsage = 0;
  public $tests = [];

  function addTest ($status, $name, $message, $description = '') {
    $this->memoryUsage = max($this->memoryUsage, memory_get_usage());
    $test = new ArrayObject([
      'status' => $status,
      'name' => $name,
      'message' => $message,
      'description' => $description,
    ]);
    MetaContext::get(ExtensionInterface::class)["luka8088.ci.test.testFound"]->__invoke($test);
    $this->tests[] = $test;
    MetaContext::get(ExtensionInterface::class)["luka8088.ci.test.testReport"]->__invoke($test);
  }

}
