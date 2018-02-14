<?php

namespace luka8088\ci\test\tool;

use \luka8088\ci\Application;
use \luka8088\ci\test\Result;
use \luka8088\ExtensionCall;
use \luka8088\phops as op;
use \Symfony\Component\Console\Input\StringInput;
use \Symfony\Component\Console\Output\BufferedOutput;

class PHPCSFixer {

  public $configuration = '';

  function __construct ($configuration) {
    $this->configuration = $configuration;
  }

  function getIdentifier () {
    return 'phpcsfixer';
  }

  /** @ExtensionCall("luka8088.ci.test.run") */
  function run () {

    $phpcsfixer = new \PhpCsFixer\Console\Application();

    $configurationStream = tmpfile();
    fwrite($configurationStream, '<?php
      $configuration = require(' . var_export($this->configuration, true) . ');
      $finder = PhpCsFixer\Finder::create()
        ->in(' . implode(')->in(', array_map(function ($path) {
          return var_export($path, true);
        }, op\metaContext(Application::class)->paths)) . ')
      ;
      $configuration->setFinder($finder);
      return $configuration;
    ');
    $configurationStreamInfo = stream_get_meta_data($configurationStream);

    $phpcsfixer->setAutoExit(false);
    $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
    $exitCode = $phpcsfixer->run(new StringInput('fix --config ' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '"' . addcslashes($configurationStreamInfo['uri'], '\\"') . '"' : escapeshellarg($configurationStreamInfo['uri'])) . ' --dry-run --format json --verbose --diff'), $output);

    /** @see https://github.com/FriendsOfPHP/PHP-CS-Fixer#exit-codes */
    if (!in_array($exitCode, [0, 4, 8]))
      throw new \Exception('Error running PHP CS Fixer: ' . $output->fetch());

    $phpcsfixerReport = json_decode($output->fetch(), true);

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
