<?php

namespace luka8088\ci\test\filter;

use \Exception;
use \luka8088\ci\Application;
use \luka8088\ExtensionCall;
use \luka8088\phops as op;

class Path {

  public $builder = [];

  function __construct ($builder) {
    $this->builder = $builder;
  }

  /** @ExtensionCall("luka8088.ci.test.testFound") */
  function testFound ($test, &$keep) {
    $test['message'] = preg_replace_callback(
      '/(?s)' . preg_quote(realpath(op\metaContext(Application::class)->rootPath), '/')
        . '[\\\\|\/]*([^ \t\r\n\*\?\#\,\;\:\(\)\[\]\{\}\<\>]+)(\:([0-9]+)?)?/',
      function ($match) {
        return call_user_func($this->builder, str_replace('\\', '/', $match[1]), isset($match[3]) ? $match[3] : 0);
      },
      $test['message']
    );
  }

}
