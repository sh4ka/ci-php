<?php

namespace luka8088\ci\test\tool;

use \ErrorException;
use \Exception;
use \luka8088\ci\Application;
use \luka8088\ci\test\Result;
use \luka8088\phops as op;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;
use \ReflectionFunction;
use \ReflectionMethod;
use \Throwable;

class InlineTest {

  function getIdentifier () {
    return 'inlinetest';
  }

  function runTests () {

    $files = [];

    foreach (op\metaContext(Application::class)->paths as $scanPath)
      foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanPath)) as $file)
        if (in_array($file->getExtension(), ['php'])) {
          $fileContent = file_get_contents($file);
          if (strpos($fileContent, '@test') === false)
            continue;
          $files[] = realpath($file);
        }

    usort($files, function ($lhs, $rhs) {
      return filemtime($rhs) - filemtime($lhs);
    });

    $includeFile = function ($file) {
      ob_start();
      include_once (string) $file;
      ob_end_clean();
    };

    foreach ($files as $file) {
      if (in_array(realpath($file), get_included_files()))
        continue;
      $includeFile($file);
    }

    $tests = [];

    $functions = get_defined_functions();
    foreach (array_merge($functions['internal'], $functions['user']) as $function) {
      $reflection = new ReflectionFunction($function);
      if (!$reflection->getFileName())
        continue;
      if (!in_array(realpath($reflection->getFileName()), $files))
        continue;
      if (!preg_match('/(?s)(?<=[ \t\r\n\*\\/]|\A)\@test(?=[ \t\r\n\*\\/]|\z)/', $reflection->getDocComment()))
        continue;
      if (substr(strtolower($function), 0, 8) == 'unittest')
        $tests[] = $function;
    }

    foreach (get_declared_classes() as $class)
      foreach (get_class_methods($class) as $method) {
        $reflection = new ReflectionMethod($class, $method);
        if ($reflection->getDeclaringClass()->getName() != $class)
          continue;
        if (!$reflection->getFileName())
          continue;
        if (!in_array(realpath($reflection->getFileName()), $files))
          continue;
        if (!preg_match('/(?s)(?<=[ \t\r\n\*\\/]|\A)\@test(?=[ \t\r\n\*\\/]|\z)/', $reflection->getDocComment()))
          continue;
        $tests[] = [$class, $method];
      }

    usort($tests, function ($lhs, $rhs) {
      $lhsReflection = is_string($lhs) ? new ReflectionFunction($lhs) : new ReflectionMethod($lhs[0], $lhs[1]);
      $rhsReflection = is_string($rhs) ? new ReflectionFunction($rhs) : new ReflectionMethod($rhs[0], $rhs[1]);
      return filemtime($rhsReflection->getFileName()) - filemtime($lhsReflection->getFileName());
    });

    $errorHandlerScoped = op\scopeExit(function () { restore_error_handler(); });
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
      throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });

    foreach ($tests as $key => $test) {
      $e = null;
      try {
        call_user_func($test);
        $status = 'success';
      } catch (Exception $e) {
        $status = 'failure';
      } catch (Throwable $e) {
        $status = 'failure';
      }
      op\metaContext(Result::class)->addTest(
        $status,
        'Inline test: ' . (is_string($test) ? $test : $test[0] . '::' . $test[1]),
        $e ? (string) $e : ''
      );
    }

  }

}
