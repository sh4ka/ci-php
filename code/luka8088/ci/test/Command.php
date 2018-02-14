<?php

namespace luka8088\ci\test;

use \luka8088\ci\test\Result;
use \luka8088\ExtensionInterface;
use \luka8088\phops as op;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;

class Command extends \Symfony\Component\Console\Command\Command {

    protected function configure () {
      $this
        ->setName('test')
        ->setDescription('Run code analysis and testing tools.')
        ->setHelp('Run code analysis and testing tools.')
      ;
    }

    protected function execute (InputInterface $input, OutputInterface $output) {

      $outputMetaContext = op\metaContextCreateScoped(OutputInterface::class, $output);
      $resultMetaContext = op\metaContextCreateScoped(Result::class, new Result());

      op\metaContext(ExtensionInterface::class)["luka8088.ci.test.begin"]->__invoke();
      op\metaContext(ExtensionInterface::class)["luka8088.ci.test.run"]->__invoke();
      op\metaContext(ExtensionInterface::class)["luka8088.ci.test.end"]->__invoke();

    }

}
