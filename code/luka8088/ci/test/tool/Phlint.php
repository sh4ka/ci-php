<?php

namespace luka8088\ci\test\tool;

use \Exception;
use \luka8088\ci\Application;
use \luka8088\ci\test\Result;
use \luka8088\ExtensionCall;
use \luka8088\phops\MetaContext;
use \luka8088\XdebugHelper;
use \SimpleXMLElement;
use \Symfony\Component\Process\PhpExecutableFinder;
use \Symfony\Component\Process\Process;

class Phlint {

  public $executable = '';
  public $configuration = '';

  function __construct ($configuration, $executable = '') {
    $this->configuration = $configuration;
    $this->executable = $executable;
  }

  function getIdentifier () {
    return 'phlint';
  }

  function runTests () {

    $executable = $this->executable;

    if (!$executable) {
      $basePath = __dir__;
      while ($basePath != dirname($basePath)) {
        if (is_file($basePath . '/vendor/phlint/phlint/phlint'))
          break;
        $basePath = dirname($basePath);
      }
      if ($basePath)
        $executable = $basePath . '/vendor/phlint/phlint/phlint';
    }

    if (!$executable)
      throw new Exception('Phlint executable not found.');

    $phpExecutableFinder = new PhpExecutableFinder();

    $testReportFile = tmpfile();
    $testReportFileInfo = stream_get_meta_data($testReportFile);

    $process = new Process(
      $phpExecutableFinder->find()
      . ' ' . '-c ' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
        ? '"' . addcslashes(XdebugHelper::iniFileWithoutXdebug(), '\\"') . '"'
        : escapeshellarg(XdebugHelper::iniFileWithoutXdebug()))
      #. ' ' . '-dzend.enable_gc=0'
      . ' ' . escapeshellarg($executable)
      . ' ' . 'analyze'
      . ' ' . implode(' ', array_map('escapeshellarg', MetaContext::get(Application::class)->paths))
      . ' ' . '--configuration=' . escapeshellarg($this->configuration)
      . ' ' . '--report-junit=' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
        ? '"' . addcslashes($testReportFileInfo['uri'], '\\"') . '"'
        : escapeshellarg($testReportFileInfo['uri']))
    );

    $process->setEnv([
      'PHP_INI_SCAN_DIR' => '',
    ]);

    $process->inheritEnvironmentVariables(true);

    $process->setTimeout(null);

    $process->run();

    if ($process->getExitCode() != 0)
      throw new Exception('Error while running Phlint: ' . $process->getErrorOutput() . $process->getOutput());

    $testMessageMap = [];
    $testStatusMap = [];
    libxml_use_internal_errors(true);
    rewind($testReportFile);
    $testReport = stream_get_contents($testReportFile);

    if (!$testReport)
      throw new Exception('Error while running Phlint: ' . $process->getErrorOutput() . $process->getOutput());

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
        #if (!isset($testStatusMap[$testName])) {
        #  $testStatusMap[$testName] = 'success';
        #  $testMessageMap[$testName] = [];
        #}
      }

    foreach ($testMessageMap as $testName => $message)
      MetaContext::get(Result::class)->addTest(
        $testStatusMap[$testName],
        $testName,
        implode("\n", array_unique($message))
      );

    MetaContext::get(Result::class)->addTest(
      'success',
      'Phlint: General',
      'Phlint analysis complete.'
    );

  }

}
