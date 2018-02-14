<?php

namespace luka8088\ci\test\tool;

use \luka8088\ci\Application;
use \luka8088\ci\test\Result;
use \luka8088\ExtensionCall;
use \luka8088\phops as op;
use \Symfony\Component\Process\PhpExecutableFinder;
use \Symfony\Component\Process\Process;

class PHPCSFixer {

  public $executable = '';
  public $configuration = '';

  function __construct ($configuration, $executable = '') {
    $this->configuration = $configuration;
    $this->executable = $executable;
  }

  function getIdentifier () {
    return 'phpcsfixer';
  }

  /** @ExtensionCall("luka8088.ci.test.run") */
  function run () {

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
      $finder = PhpCsFixer\Finder::create()
        ->in(' . implode(')->in(', array_map(function ($path) {
          return var_export($path, true);
        }, op\metaContext(Application::class)->paths)) . ')
      ;
      $configuration->setFinder($finder);
      return $configuration;
    ');
    $configurationFileInfo = stream_get_meta_data($configurationFile);

    $phpExecutableFinder = new PhpExecutableFinder();

    $process = new Process(
      $phpExecutableFinder->find()
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

    $process->setTimeout(null);

    $process->run();

    if (!in_array($process->getExitCode(), [0, 4, 8]))
      throw new Exception('Error while running PHP Coding Standards Fixer: ' . $process->getErrorOutput());

    $phpcsfixerReport = json_decode($process->getOutput(), true);

    if (!is_array($phpcsfixerReport))
      throw new \Exception('Error while reading PHP CS Fixer report.');

    foreach ($phpcsfixerReport["files"] as $file)
      op\metaContext(Result::class)->addIssue(
        'PHP Coding Standards Fixer: Coding Standards in ' . str_replace('\\', '/', $file["name"]),
        'Fixes that need to be applied: ' . implode(", ", $file["appliedFixers"]),
        $file["diff"]
      );

  }

}
