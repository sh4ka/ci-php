<?php

namespace luka8088\ci\test\tool;

use \Exception;
use \luka8088\ci\SymbolFinder;
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

    $coverageReportFile = tmpfile();
    $coverageReportFileInfo = stream_get_meta_data($coverageReportFile);

    $phpExecutableFinder = new PhpExecutableFinder();

    $process = new Process(
      $phpExecutableFinder->find()
      #. ' ' . '-dzend.enable_gc=0'
      . ' ' . escapeshellarg($executable)
      . ' ' . '--configuration ' . escapeshellarg($this->configuration)
      . ' ' . '--log-junit ' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
        ? '"' . addcslashes($testReportFileInfo['uri'], '\\"') . '"'
        : escapeshellarg($testReportFileInfo['uri']))
      . ' ' . '--coverage-clover ' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
        ? '"' . addcslashes($coverageReportFileInfo['uri'], '\\"') . '"'
        : escapeshellarg($coverageReportFileInfo['uri']))
    );

    $process->setTimeout(null);

    $process->run();

    $testMessageMap = [];
    $testStatusMap = [];
    libxml_use_internal_errors(true);
    rewind($testReportFile);
    $testReport = stream_get_contents($testReportFile);

    if (!$testReport)
      throw new Exception('Error while running PHPUnit: ' . $process->getErrorOutput() . $process->getOutput());

    $phpunitReport = new SimpleXMLElement($testReport);

    foreach ($phpunitReport->xpath('.//testsuite') as $testsuite)
      foreach ($testsuite->xpath('./testcase') as $testcase) {
        $testName = $testsuite->attributes()->name->__toString() . ': '
          . $testcase->attributes()->name->__toString();
        foreach ($testcase->xpath('./failure') as $failure) {
          $testStatusMap[$testName] = 'failure';
          if (!isset($testMessageMap[$testName]))
            $testMessageMap[$testName] = [];
          $testMessageMap[$testName][]
            = trim(html_entity_decode(strip_tags($failure->asXML()), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        foreach ($testcase->xpath('./error') as $error) {
          $testStatusMap[$testName] = 'error';
          if (!isset($testMessageMap[$testName]))
            $testMessageMap[$testName] = [];
          $testMessageMap[$testName][]
            = trim(html_entity_decode(strip_tags($error->asXML()), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (!isset($testStatusMap[$testName])) {
          $testStatusMap[$testName] = 'success';
          $testMessageMap[$testName] = [];
        }
      }

    foreach ($testMessageMap as $testName => $message)
      op\metaContext(Result::class)->addTest(
        $testStatusMap[$testName],
        $testName,
        implode("\n", array_unique($message))
      );

    $symbolFinder = new SymbolFinder();

    libxml_use_internal_errors(true);
    rewind($coverageReportFile);
    $coverageReportSource = stream_get_contents($coverageReportFile);

    if (!$coverageReportSource)
      op\metaContext(Result::class)->addTest(
        'error',
        'PHPUnit Code Coverage: Report',
        'PHPUnit Code Coverage: Unable to generate report.'
      );

    if ($coverageReportSource) {
      $coverageReport = new SimpleXMLElement($coverageReportSource);
      $symbolCoverageMap = [];
      foreach ($coverageReport->xpath(".//file") as $file)
        foreach ($file->xpath(".//line[@type=\"stmt\"]") as $lineCoverage) {
          $symbolLocation = $symbolFinder->findByLocation(
            $file->attributes()->name->__toString(),
            $lineCoverage->attributes()->num->__toString(),
            0
          );
          if (!isset($symbolCoverageMap[$symbolLocation]))
            $symbolCoverageMap[$symbolLocation] = [];
          $symbolCoverageMap[$symbolLocation][] = $lineCoverage->attributes()->count->__toString();
        }

      foreach ($symbolCoverageMap as $symbol => $symbolCoverage) {
        $coverage = count(array_filter($symbolCoverage)) / count($symbolCoverage);
        op\metaContext(Result::class)->addTest(
          $coverage < 1 ? 'failure' : 'success',
          'PHPUnit Code Coverage: Code coverage for ' . $symbol,
          'Code coverage is ' . number_format($coverage * 100, 2) . '%.'
        );
      }
    }

  }

}
