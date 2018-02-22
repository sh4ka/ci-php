<?php

namespace luka8088\ci\cli;

use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Console\Question\Question;

class Command extends \Symfony\Component\Console\Command\Command {

  function configure () {
    $this
      ->setName('cli')
      ->setDescription('Interact with the project.')
      ->setHelp('Interact directly with the project using CLI.')
    ;
  }

  function execute (InputInterface $input, OutputInterface $output) {

    $output->write("Interactive console:\n");

    while (true) {
      $snippet = $this->getHelper('question')->ask($input, $output, new Question('λ '));
      var_dump($snippet);
    }


  }

}
