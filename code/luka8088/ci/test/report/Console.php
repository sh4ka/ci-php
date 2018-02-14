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
    op\metaContext(OutputInterface::class)->write(
      "  \x1b[91m" . $issue['name']
      . "\x1b[0m\n  " . $issue['message'] . "\n"
      . ($issue['description'] ? '  ' . str_replace("\n", "\n  ", $issue['description']) . "\n" : '')
      . "\n"
    );
  }

}
