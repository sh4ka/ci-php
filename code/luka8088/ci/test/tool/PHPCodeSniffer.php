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

class PHPCodeSniffer {

  public $executable = '';
  public $configuration = '';

  function __construct ($configuration, $executable = '') {
    $this->configuration = $configuration;
    $this->executable = $executable;
  }

  function getIdentifier () {
    return 'phpcs';
  }

  function runTests () {

    $executable = $this->executable;

    if (!$executable) {
      $basePath = __dir__;
      while ($basePath != dirname($basePath)) {
        if (is_file($basePath . '/vendor/squizlabs/php_codesniffer/bin/phpcs'))
          break;
        $basePath = dirname($basePath);
      }
      if ($basePath)
        $executable = $basePath . '/vendor/squizlabs/php_codesniffer/bin/phpcs';
    }

    if (!$executable)
      throw new Exception('PHP Code Sniffer executable not found.');

    $phpExecutableFinder = new PhpExecutableFinder();

    $loadedINI = Internal::loadedINI();

    $alteredINI = $loadedINI;

    /**
     * Remove xdebug from ini file. This is the main reason why we are running
     * a sub-process in the first place - to disable XDebug.
     * It seems that XDebug can't be disabled during runtime, nor can an extension
     * defined in the php.ini be excluded from loading with parameters.
     * So far this seems to be the only way not to load XDebug.
     *
     * Examples:
     *   zend_extension=/usr/lib64/php/modules/xdebug.so
     *   zend_extension=php_xdebug.dll
     */
    $alteredINI = preg_replace(
      '/(?is)(\A|(?<=\n))zend_extension[ \t]*\=[ \t]*[a-z0-9\\\\\/\_\.]*(php_)xdebug\.(so|dll)/',
      '',
      $alteredINI
    );

    $alteredINIFile = tmpfile();
    fwrite($alteredINIFile, $alteredINI);
    $alteredINIFileInfo = stream_get_meta_data($alteredINIFile);

    $process = new Process(
      $phpExecutableFinder->find()
      . ' ' . '-c ' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
        ? '"' . addcslashes($alteredINIFileInfo['uri'], '\\"') . '"'
        : escapeshellarg($alteredINIFileInfo['uri']))
      . ' ' . escapeshellarg($executable)
      . ' ' . implode(' ', array_map('escapeshellarg', op\metaContext(Application::class)->paths))
      . ' ' . '--standard=' . escapeshellarg($this->configuration)
      . ' ' . '--report=junit'
      . ' ' . '--runtime-set ignore_errors_on_exit 1'
      . ' ' . '--runtime-set ignore_warnings_on_exit 1'
    );

    $process->setEnv([
      'PHP_INI_SCAN_DIR' => '',
    ]);

    $process->inheritEnvironmentVariables(true);

    $process->setTimeout(null);

    $process->run();

    if (!$process->isSuccessful())
      throw new Exception('Error while running PHP Code Sniffer: ' . $process->getErrorOutput() . $process->getOutput());

    $testcaseMessageMap = [];
    libxml_use_internal_errors(true);
    $phpcsReport = new SimpleXMLElement($process->getOutput());

    $symbolFinder = new SymbolFinder();

    foreach ($phpcsReport->xpath(".//testcase") as $testcase) {
      static $regex = "/
        ((?:in|at)[ \t]+)
        (...[^ \t\(\)\:]+)
        ([ \t]*[\(\:]?)
        ([0-9]*)
        ((?:[ \t]*[\:])?)
        ([0-9]*)
        ((?:[ \t]*[\:\)])?)
      /x";
      $testcaseName = preg_replace_callback($regex, function ($match) use ($symbolFinder) {
        return $match[1] . $symbolFinder->findByLocation($match[2], $match[4], $match[6]);
      }, $testcase->attributes()->name->__toString());
      foreach ($testcase->xpath(".//failure") as $failure) {
        if (!isset($testcaseMessageMap[$testcaseName]))
          $testcaseMessageMap[$testcaseName] = [];
        $testcaseMessageMap[$testcaseName][] = trim(
          $testcase->attributes()->name->__toString()
          . ": " . $failure->attributes()->message->__toString()
        );
      }
    }
    foreach ($testcaseMessageMap as $testcaseName => $message)
      op\metaContext(Result::class)->addTest(
        'failure',
        'PHP Code Sniffer: ' . $testcaseName,
        implode("\n", array_unique($message))#,
        #'https://github.com/squizlabs/PHP_CodeSniffer/wiki/Customisable-Sniff-Properties#'
        #  . strtolower(implode('', array_slice(explode('.', $testcaseName), 0, 3)))
      );

    if (count($testcaseMessageMap) == 0)
      op\metaContext(Result::class)->addTest(
        'success',
        'PHP Code Sniffer: General',
        'No PHP Code Sniffer issues found.'
      );

  }

}
