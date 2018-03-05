<?php

namespace luka8088\ci\test\report;

use \luka8088\ci\Application;
use \luka8088\ExtensionCall;
use \luka8088\phops as op;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;

class Console {

  protected $showSuccesses = true;

  protected $previousTest = null;
  protected $successCount = 0;
  protected $skippedCount = 0;
  protected $failureCount = 0;
  protected $errorCount = 0;

  /** @ExtensionCall('luka8088.ci.test.configureCommand') */
  function configureCommand ($command) {
    op\metaContext(Application::class)->createParameter('console.showSuccesses', true);
    $command
      ->addOption('show-successes', null, InputOption::VALUE_OPTIONAL, 'Show successful tests in the console output.')
    ;
  }

  /** @ExtensionCall("luka8088.ci.test.begin") */
  function begin () {
    $this->showSuccesses = op\metaContext(Application::class)->getParameter('console.showSuccesses');
    $showSuccesses = filter_var(
      op\metaContext(InputInterface::class)->getOption('show-successes') !== null
        ? op\metaContext(InputInterface::class)->getOption('show-successes')
        : 'x',
      FILTER_VALIDATE_BOOLEAN,
      FILTER_NULL_ON_FAILURE
    );
    if (is_bool($showSuccesses))
      $this->showSuccesses = $showSuccesses;
    op\metaContext(OutputInterface::class)->write("\n  Running tests ...\n");
    $this->previousTest = null;
    $this->successCount = 0;
    $this->skippedCount = 0;
    $this->failureCount = 0;
    $this->errorCount = 0;
  }

  /** @ExtensionCall("luka8088.ci.test.testReport") */
  function testReport ($test) {
    switch ($test['status']) {
      case 'error': $this->errorCount += 1; break;
      case 'failure': $this->failureCount += 1; break;
      case 'skipped': $this->skippedCount += 1; break;
      case 'success': $this->successCount += 1; break;
    }
    if ($test['status'] == 'success' && !$this->showSuccesses)
      return;
    if ($test['status'] == 'skipped')
      return;
    $message = trim(preg_replace_callback('/(?i)((http|https)\:\/\/[^ \t\r\n\(\)\<\>\*\;]+)/', function ($match) {
        return "\x1b[93m" . $match[1] . "\x1b[0m";
    }, $test['message'] . (op\metaContext(OutputInterface::class)->isVerbose() && $test['description']
      ? "\n" . $test['description']
      : '')));
    $hasSpacer
      = !$this->previousTest
      || in_array($this->previousTest['status'], ['error', 'failure'])
      || in_array($test['status'], ['error', 'failure'])
    ;
    op\metaContext(OutputInterface::class)->write(
      ($hasSpacer ? "\n" : '')
      . ($test['status'] == 'error' ? "  \x1b[91m❗ "
        : ($test['status'] == 'failure' ? "  \x1b[91m✖ " : "  \x1b[92m✔ "))
      . "\x1b[37m"
      . $test['name']
      . "\x1b[0m"
      . ($message && $test['status'] != 'success' ? "\n    " . str_replace("\n", "\n    ", $message) : '')
      . "\n"
    , false, OutputInterface::OUTPUT_RAW);
    $this->previousTest = $test;
  }

  /** @ExtensionCall("luka8088.ci.test.end") */
  function end () {
    op\metaContext(OutputInterface::class)->write(
      "\n  "
      . ($this->errorCount > 0 ? "\x1b[91m❗" : ($this->failureCount > 0 ? "\x1b[91m✖" : ($this->successCount > 0 ? "\x1b[92m✔" : "\x1b[93m❓")))
      . " Done with "
      . $this->failureCount . " failure(s), "
      . $this->errorCount . " error(s), "
      . $this->skippedCount . " skipped and "
      . $this->successCount . " success(es)."
      . "\n\n"
      . "\x1b[0m"
    );
  }

}
