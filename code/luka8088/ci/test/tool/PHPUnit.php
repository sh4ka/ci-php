<?php

namespace luka8088\ci\test\tool;

use \Exception;
use \luka8088\ci\test\Result;
use \luka8088\phops as op;
use \SimpleXMLElement;
use \Symfony\Component\Process\PhpExecutableFinder;
use \Symfony\Component\Process\Process;

class PHPUnit {

  public $executable = '';
  public $configuration = '';

  function __construct ($configuration, $executable = '') {
    $this->configuration = $configuration;
    $this->executable = $executable;
  }

  function getIdentifier () {
    return 'phpunit';
  }

  function runTests () {

    $executable = $this->executable;

    if (!$executable) {
      $basePath = __dir__;
      while ($basePath != dirname($basePath)) {
        if (is_file($basePath . '/vendor/phpunit/phpunit/phpunit'))
          break;
        $basePath = dirname($basePath);
      }
      if ($basePath)
        $executable = $basePath . '/vendor/phpunit/phpunit/phpunit';
    }

    if (!$executable)
      throw new Exception('PHPUnit executable not found.');

    $testReportFile = tmpfile();
    $testReportFileInfo = stream_get_meta_data($testReportFile);

    $phpExecutableFinder = new PhpExecutableFinder();

    $process = new Process(
      $phpExecutableFinder->find()
      . ' ' . escapeshellarg($executable)
      . ' ' . '--configuration ' . escapeshellarg($this->configuration)
      . ' ' . '--log-junit ' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
        ? '"' . addcslashes($testReportFileInfo['uri'], '\\"') . '"'
        : escapeshellarg($testReportFileInfo['uri']))
    );

    $process->setTimeout(null);

    $process->run();

    $testMessageMap = [];
    libxml_use_internal_errors(true);
    rewind($testReportFile);
    $phpunitReport = new SimpleXMLElement(stream_get_contents($testReportFile));

    foreach ($phpunitReport->xpath('.//testsuite') as $testsuite)
      foreach ($testsuite->xpath('./testcase') as $testcase) {
        $testName = $testsuite->attributes()->name->__toString() . ': '
          . $testcase->attributes()->name->__toString();
        foreach ($testcase->xpath('./failure') as $failure) {
          if (!isset($testMessageMap[$testName]))
            $testMessageMap[$testName] = [];
          $testMessageMap[$testName][]
            = trim(html_entity_decode(strip_tags($failure->asXML()), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
      }

    foreach ($testMessageMap as $testName => $message)
      op\metaContext(Result::class)->addIssue(
        $testName,
        implode("\n", array_unique($message))
      );

  }

}
