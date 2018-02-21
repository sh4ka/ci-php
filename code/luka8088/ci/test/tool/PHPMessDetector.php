<?php

namespace luka8088\ci\test\tool;

use \Exception;
use \luka8088\ci\Application;
use \luka8088\ci\Internal;
use \luka8088\ci\SymbolFinder;
use \luka8088\ci\test\Result;
use \luka8088\ExtensionCall;
use \luka8088\phops as op;
use \SimpleXMLElement;
use \Symfony\Component\Process\PhpExecutableFinder;
use \Symfony\Component\Process\Process;

class PHPMessDetector {

  public $executable = '';
  public $configuration = '';

  function __construct ($configuration, $executable = '') {
    $this->configuration = $configuration;
    $this->executable = $executable;
  }

  function getIdentifier () {
    return 'phpmd';
  }

  function runTests () {

    $executable = $this->executable;

    if (!$executable) {
      $basePath = __dir__;
      while ($basePath != dirname($basePath)) {
        if (is_file($basePath . '/vendor/phpmd/phpmd/src/bin/phpmd'))
          break;
        $basePath = dirname($basePath);
      }
      if ($basePath)
        $executable = $basePath . '/vendor/phpmd/phpmd/src/bin/phpmd';
    }

    if (!$executable)
      throw new Exception('PHP Mess Detector executable not found.');

    $phpExecutableFinder = new PhpExecutableFinder();

    $alteredINIFile = tmpfile();
    fwrite($alteredINIFile, Internal::disableINIXDebug(Internal::loadedINI()));
    $alteredINIFileInfo = stream_get_meta_data($alteredINIFile);

    $process = new Process(
      $phpExecutableFinder->find()
      . ' ' . '-c ' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
        ? '"' . addcslashes($alteredINIFileInfo['uri'], '\\"') . '"'
        : escapeshellarg($alteredINIFileInfo['uri']))
      . ' ' . '-dzend.enable_gc=0'
      . ' ' . escapeshellarg($executable)
      . ' ' . escapeshellarg(implode(',', op\metaContext(Application::class)->paths))
      . ' ' . 'xml'
      . ' ' . escapeshellarg($this->configuration)
      . ' ' . '--ignore-violations-on-exit'
    );

    $process->setEnv([
      'PHP_INI_SCAN_DIR' => '',
    ]);

    $process->inheritEnvironmentVariables(true);

    $process->setTimeout(null);

    $process->run();

    if (!$process->isSuccessful())
      throw new Exception('Error while running PHP Mess Detector: ' . $process->getErrorOutput() . $process->getOutput());

    libxml_use_internal_errors(true);
    $phpmdReport = new SimpleXMLElement($process->getOutput());

    $symbolFinder = new SymbolFinder();

    $testcaseMessageMap = [];

    foreach ($phpmdReport->file as $file) {
      foreach ($file->xpath(".//violation") as $violation) {
        $testcaseName = $violation->attributes()->rule->__toString() . " at " . $symbolFinder->findByLocation(
          $file->attributes()->name->__toString(),
          $violation->attributes()->beginline->__toString(),
          0
        );
        if (!isset($testcaseMessageMap[$testcaseName]))
          $testcaseMessageMap[$testcaseName] = [];
        $testcaseMessageMap[$testcaseName][] =
          $file->attributes()->name->__toString() . ":" . $violation->attributes()->beginline->__toString()
          . ": " . trim(html_entity_decode(strip_tags($violation->asXML()), ENT_QUOTES | ENT_HTML5, "UTF-8"))
        ;
      }
    }
    foreach ($testcaseMessageMap as $testcaseName => $message) {
      $rule = substr($testcaseName, 0, strpos($testcaseName, ' '));
      op\metaContext(Result::class)->addTest(
        'failure',
        'PHP Mess Detector: ' . $testcaseName,
        implode("\n", array_unique($message))
        . "\n" . 'Rule documentation: '
        . 'https://phpmd.org/rules/' . strtolower(self::$ruleClassMap[$rule]) . '.html#' . strtolower($rule)
      );
    }

    if (count($testcaseMessageMap) == 0)
      op\metaContext(Result::class)->addTest(
        count($testcaseMessageMap) == 0 ? 'success' : 'failure',
        'PHP Mess Detector: General',
        'No PHP Mess Detector issues found.'
      );

  }

  static $ruleClassMap = [
    'BooleanArgumentFlag' => 'CleanCode',
    'BooleanGetMethodName' => 'Naming',
    'CamelCaseClassName' => 'Controversial',
    'CamelCaseMethodName' => 'Controversial',
    'CamelCaseParameterName' => 'Controversial',
    'CamelCasePropertyName' => 'Controversial',
    'CamelCaseVariableName' => 'Controversial',
    'ConstantNamingConventions' => 'Naming',
    'ConstructorWithNameAsEnclosingClass' => 'Naming',
    'CouplingBetweenObjects' => 'Design',
    'CyclomaticComplexity' => 'CodeSize',
    'DepthOfInheritance' => 'Design',
    'DevelopmentCodeFragment' => 'Design',
    'ElseExpression' => 'CleanCode',
    'EvalExpression' => 'Design',
    'ExcessiveClassComplexity' => 'CodeSize',
    'ExcessiveClassLength' => 'CodeSize',
    'ExcessiveMethodLength' => 'CodeSize',
    'ExcessiveParameterList' => 'CodeSize',
    'ExcessivePublicCount' => 'CodeSize',
    'ExitExpression' => 'Design',
    'GotoStatement' => 'Design',
    'LongVariable' => 'Naming',
    'NPathComplexity' => 'CodeSize',
    'NumberOfChildren' => 'Design',
    'ShortMethodName' => 'Naming',
    'ShortVariable' => 'Naming',
    'StaticAccess' => 'CleanCode',
    'Superglobals' => 'Controversial',
    'TooManyFields' => 'CodeSize',
    'TooManyMethods' => 'CodeSize',
    'TooManyPublicMethods' => 'CodeSize',
    'UnusedFormalParameter' => 'UnusedCode',
    'UnusedLocalVariable' => 'UnusedCode',
    'UnusedPrivateField' => 'UnusedCode',
    'UnusedPrivateMethod' => 'UnusedCode',
  ];

}
