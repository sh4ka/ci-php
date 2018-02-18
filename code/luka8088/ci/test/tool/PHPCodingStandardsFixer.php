<?php

namespace luka8088\ci\test\tool;

use \Exception;
use \luka8088\ci\Application;
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
      $phpCommand .= ' -dextension=tokenizer.so -dextension=json.so';

    $process = new Process(
      $phpCommand
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
      op\metaContext(Result::class)->addTest(
        'failure',
        'PHP Coding Standards Fixer: Coding Standards in ' . str_replace('\\', '/', $file["name"]),
        'Fixes that need to be applied: ' . implode(", ", $file["appliedFixers"])
        . "\n" . $file["diff"]
      );

  }

}
