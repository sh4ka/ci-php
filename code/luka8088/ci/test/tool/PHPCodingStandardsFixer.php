<?php

namespace luka8088\ci\test\tool;

use \Exception;
use \luka8088\ci\Application;
use \luka8088\ci\test\Result;
use \luka8088\ExtensionCall;
use \luka8088\phops\MetaContext;
use \luka8088\XdebugHelper;
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
        }, MetaContext::get(Application::class)->paths)) . ')
      ;
      $configuration->setFinder($finder);
      return $configuration;
    ');
    $configurationFileInfo = stream_get_meta_data($configurationFile);

    $phpExecutableFinder = new PhpExecutableFinder();

    $process = new Process(
      $phpExecutableFinder->find()
      . ' ' . '-c ' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
        ? '"' . addcslashes(XdebugHelper::iniFileWithoutXdebug(), '\\"') . '"'
        : escapeshellarg(XdebugHelper::iniFileWithoutXdebug()))
      #. ' ' . '-dzend.enable_gc=0'
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

    foreach ($phpcsfixerReport["files"] as $file) {
      $filePath = $file["name"];
      if (strpos(realpath($file["name"]), realpath(MetaContext::get(Application::class)->getParameter('rootPath'))) === 0)
        $filePath = ltrim(str_replace('\\', '/', substr(realpath($file["name"]),
          strlen(realpath(MetaContext::get(Application::class)->getParameter('rootPath'))))), '/');
      MetaContext::get(Result::class)->addTest(
        'failure',
        'PHP Coding Standards Fixer: Coding Standards in ' . $filePath,
        'Fixes that need to be applied: ' . implode(", ", $file["appliedFixers"]),
        $file["diff"]
      );
    }

    MetaContext::get(Result::class)->addTest(
      'success',
      'PHP Coding Standards Fixer: General',
      'No PHP Coding Standards Fixer analysis complete.'
    );

  }

}
