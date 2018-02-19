<?php

namespace luka8088\ci\test\tool;

use \Exception;
use \luka8088\ci\Application;
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

    $phpCommand = $phpExecutableFinder->find();

    /**
     * Don't load the default ini file to disable XDebug.
     * It seems that XDebug can't be disabled during runtime, nor can an extension
     * defined in the php.ini be excluded from loading with parameters.
     * So far this seems to be the only way not to load XDebug.
     */
    $phpCommand .= ' -n';

    /**
     * For some reason these two extensions are not statically linked
     * on *nix systems so we need to load the explicitly since
     * the default ini file is not loaded.
     */
    if (PHP_SHLIB_SUFFIX == 'so')
      $phpCommand .= ' -dextension=tokenizer.so -dextension=json.so -dextension=simplexml.so -dextension=xml.so -dextension=xmlwriter.so -dextension=ctype.so -dextension=dom.so -dextension=iconv.so';

    $process = new Process(
      $phpCommand
      . ' ' . escapeshellarg($executable)
      . ' ' . escapeshellarg(implode(',', op\metaContext(Application::class)->paths))
      . ' ' . 'xml'
      . ' ' . escapeshellarg($this->configuration)
      . ' ' . '--ignore-violations-on-exit'
    );

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
