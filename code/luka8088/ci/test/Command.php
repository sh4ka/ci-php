<?php

namespace luka8088\ci\test;

use \Exception;
use \luka8088\ci\Application;
use \luka8088\ci\test\Result;
use \luka8088\ExtensionInterface;
use \luka8088\phops as op;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;

class Command extends \Symfony\Component\Console\Command\Command {

    protected function configure () {
      $this
        ->setName('test')
        ->setDescription('Run code analysis and testing tools.')
        ->setHelp('Run code analysis and testing tools.')
        ->addOption('tool', null, InputOption::VALUE_OPTIONAL, 'Run only a specific tool.')
      ;
    }

    protected function execute (InputInterface $input, OutputInterface $output) {

      $outputMetaContext = op\metaContextCreateScoped(OutputInterface::class, $output);
      $resultMetaContext = op\metaContextCreateScoped(Result::class, new Result());

      // @todo: Rething.
      op\metaContext(Application::class)[] = new \luka8088\ci\test\report\Console();

      op\metaContext(ExtensionInterface::class)["luka8088.ci.test.begin"]->__invoke();
      op\metaContext(ExtensionInterface::class)["luka8088.ci.test.run"]->__invoke();

      $testers = [];
      foreach (op\metaContext(Application::class)->extensions as $extension)
        if (method_exists($extension, 'runTests'))
          $testers[$extension->getIdentifier()] = $extension;

      if ($input->getOption('tool', '') && !isset($testers[$input->getOption('tool', '')]))
        throw new Exception('Tool not registered.');

      foreach ($testers as $testerIdentifier => $tester) {
        if ($input->getOption('tool', '') && $input->getOption('tool', '') != $testerIdentifier)
          continue;
        $tester->runTests();
      }

      op\metaContext(ExtensionInterface::class)["luka8088.ci.test.end"]->__invoke();

    }

}
