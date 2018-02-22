<?php

namespace luka8088\ci;

use \luka8088\ci\Application;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;
use \Symfony\Component\Console\Input\StringInput;
use \Symfony\Component\Console\Output\BufferedOutput;

class Test {

  static function create ($configurator) {
    $ci = new Application();
    $ci->setAutoExit(false);
    $configurator($ci);
    return $ci;
  }

  static function assertOutput ($ci, $input, $expectedOutput) {
    $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
    $result = $ci->run(new StringInput($input), $output);
    $outputString = $output->fetch();
    $minimizeMessage = function ($message) { return trim(preg_replace('/(?is)[ \t\r\n]+/', ' ', preg_replace("/\x1b\\[[0-9\,]+m/", '', $message))); };
    assert(
      $minimizeMessage($outputString) == $minimizeMessage($expectedOutput),
      "Unexpected output found.\nExpected:\n----------------------------------------\n"
      . $minimizeMessage($expectedOutput)
      . "\n----------------------------------------\nActual:\n----------------------------------------\n"
      . $minimizeMessage($outputString)
      . "\n----------------------------------------\n"
    );
  }

  static function mockFilesystem ($path, $files) {

    if (file_exists($path)) {

      $existingFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
      );

      foreach ($existingFiles as $existingFile) {
        if ($existingFile->isDir())
          rmdir($existingFile->getRealPath());
        else
          unlink($existingFile->getRealPath());
      }

    }

    foreach ($files as $file => $contents) {
      if (!file_exists($path . '/' . dirname($file)))
        mkdir($path . '/' . dirname($file), 0777, true);
      file_put_contents($path . '/' . $file, $contents);
    }

  }

}
