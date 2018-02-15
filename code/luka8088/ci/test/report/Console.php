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

  /** @ExtensionCall("luka8088.ci.test.issueReport") */
  function issueReport ($issue) {
    $message = preg_replace_callback('/(?i)((http|https)\:\/\/[^ \t\r\n\(\)\<\>\*\;]+)/', function ($match) {
        return "\x1b[93m" . $match[1] . "\x1b[0m";
    }, $issue['message']);
    op\metaContext(OutputInterface::class)->write(
      "  \x1b[91m" . $issue['name']
      . "\x1b[0m\n  " . str_replace("\n", "\n  ", $message) . "\n"
      . "\n"
    , false, OutputInterface::OUTPUT_RAW);
  }

}
