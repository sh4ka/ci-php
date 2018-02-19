<?php

namespace luka8088\ci\test\tool;

use \Exception;
use \luka8088\ci\Application;
use \luka8088\ci\Internal;
use \luka8088\ci\test\Result;
use \luka8088\ExtensionCall;
use \luka8088\phops as op;
use \Symfony\Component\Process\PhpExecutableFinder;
use \Symfony\Component\Process\Process;

class PHPCodingStandardsFixer {

  public $executable = '';
  public $configuration = '';

  function __construct ($configuration, $executable = '') {
    $this->configuration = $configuration;
    $this->executable = $executable;
  }

  function getIdentifier () {
    return 'phpcsfixer';
  }

  function runTests () {

    $executable = $this->executable;

    if (!$executable) {
      $basePath = __dir__;
      while ($basePath != dirname($basePath)) {
        if (is_file($basePath . '/vendor/friendsofphp/php-cs-fixer/php-cs-fixer'))
          break;
        $basePath = dirname($basePath);
      }
      if ($basePath)
        $executable = $basePath . '/vendor/friendsofphp/php-cs-fixer/php-cs-fixer';
    }

    if (!$executable)
      throw new Exception('PHP Coding Standards Fixer executable not found.');

    $configurationFile = tmpfile();
    fwrite($configurationFile, '<?php
      $configuration = require(' . var_export($this->configuration, true) . ');
      $finder = \PhpCsFixer\Finder::create()
        ->in(' . implode(')->in(', array_map(function ($path) {
          return var_export($path, true);
        }, op\metaContext(Application::class)->paths)) . ')
      ;
      $configuration->setFinder($finder);
      return $configuration;
    ');
    $configurationFileInfo = stream_get_meta_data($configurationFile);

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
      . ' ' . 'fix'
      . ' ' . '--config ' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
        ? '"' . addcslashes($configurationFileInfo['uri'], '\\"') . '"'
        : escapeshellarg($configurationFileInfo['uri']))
      . ' ' . '--dry-run'
      . ' ' . '--format json'
      . ' ' . '--verbose'
      . ' ' . '--diff'
    );

    $process->setEnv([
      'PHP_INI_SCAN_DIR' => '',
    ]);

    $process->inheritEnvironmentVariables(true);

    $process->setTimeout(null);

    $process->run();

    if (!in_array($process->getExitCode(), [0, 4, 8]))
      throw new Exception('Error while running PHP Coding Standards Fixer: ' . $process->getErrorOutput() . $process->getOutput());

    $phpcsfixerReport = json_decode($process->getOutput(), true);

    if (!is_array($phpcsfixerReport))
      throw new \Exception('Error while reading PHP CS Fixer report.');

    foreach ($phpcsfixerReport["files"] as $file)
      op\metaContext(Result::class)->addTest(
        'failure',
        'PHP Coding Standards Fixer: Coding Standards in ' . str_replace('\\', '/', $file["name"]),
        'Fixes that need to be applied: ' . implode(", ", $file["appliedFixers"])
        . "\n" . $file["diff"]
      );

    if (count($phpcsfixerReport["files"]) == 0)
      op\metaContext(Result::class)->addTest(
        count($phpcsfixerReport["files"]) == 0 ? 'success' : 'failure',
        'PHP Coding Standards Fixer: General',
        'No PHP Coding Standards Fixer issues found.'
      );

  }

}
