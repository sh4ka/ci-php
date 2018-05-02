<?php

namespace luka8088\ci\test;

use \Exception;
use \luka8088\ci\Application;
use \luka8088\ci\test\report\Issues as IssuesReport;
use \luka8088\ci\test\report\JUnit as JUnitReport;
use \luka8088\ci\test\Result;
use \luka8088\ExtensionCall;
use \luka8088\ExtensionInterface;
use \luka8088\phops\MetaContext;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;

class Command extends \Symfony\Component\Console\Command\Command {

  function configure () {
    $this
      ->setName('test')
      ->setDescription('Run code analysis and testing tools.')
      ->setHelp('Run code analysis and testing tools.')
      ->addOption('exit-code', null, InputOption::VALUE_NONE, 'Set exit code to non-zero if there are any issues.')
      ->addOption('report-issues', null, InputOption::VALUE_REQUIRED, 'Path to output an issue report to.')
      ->addOption('report-junit', null, InputOption::VALUE_REQUIRED, 'Path to output a JUnit report to.')
      ->addOption('tool', null, InputOption::VALUE_OPTIONAL, 'Run only a specific tool.')
    ;

    // @todo: Rething.
    $hasConsole = false;
    foreach (MetaContext::get(Application::class)->extensions as $extension)
      if ($extension instanceof \luka8088\ci\test\report\Console)
        $hasConsole = true;
    if (!$hasConsole)
      MetaContext::get(Application::class)[] = new \luka8088\ci\test\report\Console();

    MetaContext::get(ExtensionInterface::class)[] = [
      /** @ExtensionCall('luka8088.ci.test.configureCommand') */ function ($command) {},
    ];
    MetaContext::get(ExtensionInterface::class)['luka8088.ci.test.configureCommand']->__invoke($this);
  }

  function execute (InputInterface $input, OutputInterface $output) {

    $inputMetaContext = MetaContext::enterDestructible(InputInterface::class, $input);
    $outputMetaContext = MetaContext::enterDestructible(OutputInterface::class, $output);
    $resultMetaContext = MetaContext::enterDestructible(Result::class, new Result());

    $beginTimestamp = microtime(true);

    $issuesReport = $input->getOption('report-issues');
    if ($issuesReport)
      MetaContext::get(Application::class)[] = new IssuesReport(fopen($issuesReport, 'w'));

    $junitReport = $input->getOption('report-junit');
    if ($junitReport)
      MetaContext::get(Application::class)[] = new JUnitReport(fopen($junitReport, 'w'));

    MetaContext::get(ExtensionInterface::class)["luka8088.ci.test.begin"]->__invoke();
    MetaContext::get(ExtensionInterface::class)["luka8088.ci.test.run"]->__invoke();

    $testers = [];
    foreach (MetaContext::get(Application::class)->extensions as $extension)
      if (method_exists($extension, 'runTests'))
        $testers[$extension->getIdentifier()] = $extension;

    if ($input->getOption('tool', '') && !isset($testers[$input->getOption('tool', '')]))
      throw new Exception('Tool not registered.');

    foreach ($testers as $testerIdentifier => $tester) {
      if ($input->getOption('tool', '') && $input->getOption('tool', '') != $testerIdentifier)
        continue;
      $tester->runTests();
    }

    MetaContext::get(Result::class)->runningTime = microtime(true) - $beginTimestamp;

    MetaContext::get(ExtensionInterface::class)["luka8088.ci.test.end"]->__invoke();

    if ($input->getOption('exit-code')) {
      $issuesCount = count(array_filter(MetaContext::get(Result::class)->tests, function ($test) {
        return in_array($test['status'], ['error', 'failure']);
      }));
      if ($issuesCount > 0)
        return 1;
    }

  }

}
