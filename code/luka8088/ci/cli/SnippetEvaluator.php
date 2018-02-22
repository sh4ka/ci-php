<?php

namespace luka8088\ci\cli;

use \ErrorException;
use \luka8088\phops as op;

class SnippetEvaluator {

  public $variables = [];
  public $interactiveSnippet = '';

  function evaluate ($snippet) {
    $errorHandlerScoped = op\scopeExit(function () { restore_error_handler(); });
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
      throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });
    op\metaContext(SnippetEvaluator::class)->interactiveSnippet = $snippet;
    call_user_func(function () {
      extract(op\metaContext(SnippetEvaluator::class)->variables);
      eval(call_user_func(function () {
        return op\metaContext(SnippetEvaluator::class)->interactiveSnippet . ';';
      }));
      op\metaContext(SnippetEvaluator::class)->variables += get_defined_vars();
    });
  }

}
