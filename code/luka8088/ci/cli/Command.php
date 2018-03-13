<?php

namespace luka8088\ci\cli;

use \Exception;
use \luka8088\ci\cli\SnippetEvaluator;
use \luka8088\phops\MetaContext;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Console\Question\Question;
use \Throwable;

class Command extends \Symfony\Component\Console\Command\Command {

  function configure () {
    $this
      ->setName('cli')
      ->setDescription('Interact with the project.')
      ->setHelp('Interact directly with the project using CLI.')
    ;
  }

  function execute (InputInterface $input, OutputInterface $output) {

    $output->write("Running interactive console mode.\n");

    $snippetEvaluatorMetaContext = MetaContext::enterDestructible(SnippetEvaluator::class, new SnippetEvaluator());

    $nlEnd = true;

    $obStartSuccess = ob_start(function ($buffer) use ($output, &$nlEnd) {
      if (strlen($buffer) > 0)
        $nlEnd = substr($buffer, -1) == "\n" || substr($buffer, -1) == "\r";
      $output->write($buffer);
      return '';
    }, 1);

    if (!$obStartSuccess)
      throw new \Exception('ob_start failed.');

    while (true) {

      $snippet = $this->getHelper('question')->ask($input, $output, new Question("\x1b[96mλ\x1b[0m "));

      $exception = null;
      try {
        $nlEnd = true;
        MetaContext::get(SnippetEvaluator::class)->evaluate($snippet);
      } catch (Exception $exception) {
      } catch (Throwable $exception) {
      }

      if ($exception)
        echo "\x1b[91m" . $exception . "\x1b[0m";

      if (!$nlEnd)
        echo "\n";

    }

  }

}
