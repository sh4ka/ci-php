<?php

namespace luka8088\ci\test\tool;

use \luka8088\ci\Application;
use \luka8088\ci\SymbolFinder;
use \luka8088\ci\test\Result;
use \luka8088\ExtensionCall;
use \luka8088\phops as op;
use \SimpleXMLElement;

class PHPMD {

  public $configuration = '';

  function __construct ($configuration) {
    $this->configuration = $configuration;
  }

  function getIdentifier () {
    return 'phpmd';
  }

  /** @ExtensionCall("luka8088.ci.test.run") */
  function run () {

    $reportStream = fopen('php://memory', 'rw');
    $reportRenderer = new \PHPMD\Renderer\XMLRenderer();
    $reportRenderer->setWriter(new \PHPMD\Writer\StreamWriter($reportStream));

    $phpmd = new \PHPMD\PHPMD();
    $phpmd->processFiles(
      implode(',', op\metaContext(Application::class)->paths),
      $this->configuration,
      [$reportRenderer],
      new \PHPMD\RuleSetFactory()
    );

    libxml_use_internal_errors(true);
    rewind($reportStream);
    $phpmdReport = new SimpleXMLElement(stream_get_contents($reportStream));

    $symbolFinder = new SymbolFinder();

    $testcaseMessageMap = [];

    foreach ($phpmdReport->file as $file) {
      foreach ($file->xpath(".//violation") as $violation) {
        $testcaseName = $violation->attributes()->rule->__toString() . " at " . $symbolFinder->findByLocation(
          $file->attributes()->name->__toString(),
          $violation->attributes()->beginline->__toString(),
          0
        );
        if (!isset($testcaseMessageMap[$testcaseName]))
          $testcaseMessageMap[$testcaseName] = [];
        $testcaseMessageMap[$testcaseName][] =
          $file->attributes()->name->__toString() . ":" . $violation->attributes()->beginline->__toString()
          . ": " . trim(html_entity_decode(strip_tags($violation->asXML()), ENT_QUOTES | ENT_HTML5, "UTF-8"))
        ;
      }
    }
    foreach ($testcaseMessageMap as $testcaseName => $message)
      op\metaContext(Result::class)->addIssue(
        'PHP Mess Detector: ' . $testcaseName,
        implode("\n", array_unique($message))
      );

  }

}
