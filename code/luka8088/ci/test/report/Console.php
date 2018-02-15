<?php

namespace luka8088\ci\test\report;

use \luka8088\ExtensionCall;
use \luka8088\phops as op;
use \Symfony\Component\Console\Output\OutputInterface;

class Console {

  /** @ExtensionCall("luka8088.ci.test.begin") */
  function begin () {
    op\metaContext(OutputInterface::class)->write("\n");
  }

  /** @ExtensionCall("luka8088.ci.test.testReport") */
  function testReport ($test) {
    $message = trim(preg_replace_callback('/(?i)((http|https)\:\/\/[^ \t\r\n\(\)\<\>\*\;]+)/', function ($match) {
        return "\x1b[93m" . $match[1] . "\x1b[0m";
    }, $test['message']));
    op\metaContext(OutputInterface::class)->write(
      (in_array($test['status'], ['error', 'failure']) ? "  \x1b[91m✖ " : "  \x1b[92m✔ ")
      . "\x1b[0m"
      . $test['name']
      . "\x1b[0m"
      . ($message ? "\n  " . str_replace("\n", "\n  ", $message) : '')
      . "\n"
      . "\n"
    , false, OutputInterface::OUTPUT_RAW);
  }

}
