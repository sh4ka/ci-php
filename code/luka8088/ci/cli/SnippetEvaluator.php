<?php

namespace luka8088\ci\cli;

use \ErrorException;
use \luka8088\phops\DestructCallback;
use \luka8088\phops\MetaContext;

class SnippetEvaluator {

  public $variables = [];
  public $interactiveSnippet = '';

  function evaluate ($snippet) {
    $errorHandlerScoped = DestructCallback::create(function () { restore_error_handler(); });
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
      throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });
    MetaContext::get(SnippetEvaluator::class)->interactiveSnippet = $snippet;
    call_user_func(function () {
      extract(MetaContext::get(SnippetEvaluator::class)->variables);
      eval(call_user_func(function () {
        return MetaContext::get(SnippetEvaluator::class)->interactiveSnippet . ';';
      }));
      MetaContext::get(SnippetEvaluator::class)->variables += get_defined_vars();
    });
  }

}
