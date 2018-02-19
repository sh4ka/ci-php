<?php

namespace luka8088\ci;

use \Symfony\Component\Process\PhpExecutableFinder;
use \Symfony\Component\Process\Process;

class Internal {

  /**
   * Disable XDebug due to performance issues with it.
   * It seems that for data processing heavy code XDebug has
   * massive performance implications.
   */
  static function disableXDebug()
  {

      if (getenv('xDebugDisableAttemptMade')) {
        putenv('PHP_INI_SCAN_DIR' . (getenv('PHP_INI_SCAN_DIR_BACKUP') ? '=' . getenv('PHP_INI_SCAN_DIR_BACKUP') : ''));
        putenv('PHP_INI_SCAN_DIR_BACKUP');
        putenv('xDebugDisableAttemptMade');
        return;
      }

      if (!in_array('xdebug', array_map(function ($name) { return strtolower($name); }, get_loaded_extensions(true))))
        return;

      $phpExecutableFinder = new PhpExecutableFinder();

      $loadedINI = Internal::loadedINI();

      $alteredINI = $loadedINI;

      /**
       * Remove xdebug from ini file. This is the main reason why we are running
       * a sub-process in the first place - to disable XDebug.
       * It seems that XDebug can't be disabled during runtime, nor can an extension
       * defined in the php.ini be excluded from loading with parameters.
       * So far this seems to be the only way not to load XDebug.
       *
       * Examples:
       *   zend_extension=/usr/lib64/php/modules/xdebug.so
       *   zend_extension=php_xdebug.dll
       */
      $alteredINI = preg_replace(
        '/(?s)(\A|(?<=\n))zend_extension[ \t]*\=[ \t]*[A-Za-z0-9\\\\\/\_\.]*(php_)?xdebug\.(so|dll)/',
        '',
        $alteredINI
      );

      $alteredINIFile = tmpfile();
      fwrite($alteredINIFile, $alteredINI);
      $alteredINIFileInfo = stream_get_meta_data($alteredINIFile);

      $process = new Process(
        $phpExecutableFinder->find()
        . ' ' . '-c ' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
          ? '"' . addcslashes($alteredINIFileInfo['uri'], '\\"') . '"'
          : escapeshellarg($alteredINIFileInfo['uri']))
        . ' ' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
          ? '"' . addcslashes(debug_backtrace()[0]['file'], '\\"') . '"'
          : escapeshellarg(debug_backtrace()[0]['file']))
        . ' ' . implode(' ', array_slice($GLOBALS['argv'], 1))
      );

      $process->setEnv([
        'PHP_INI_SCAN_DIR' => '',
        'PHP_INI_SCAN_DIR_BACKUP' => getenv('PHP_INI_SCAN_DIR'),
        'xDebugDisableAttemptMade' => '1',
      ]);

      $process->inheritEnvironmentVariables(true);

      $process->setTimeout(null);

      $output = fopen('php://stdout', 'w');
      $error = fopen('php://stderr', 'w');

      $hasSuccessfullyRun = false;

      $process->run(function ($type, $buffer) use ($output, $error, &$hasSuccessfullyRun) {
        if ($type == \Symfony\Component\Process\Process::OUT)
          fwrite($output, $buffer);
        if ($type == \Symfony\Component\Process\Process::ERR)
          fwrite($error, $buffer);
        $hasSuccessfullyRun = true;
      });

      /**
       * In case the sub process is successful it runs the original
       * code - without XDebug.
       * In that case continuing to execute would produce a duplicate.
       * If it's not successful the parent process does not exit and
       * continues to fallback with XDebug.
       */
      if ($process->isSuccessful() || $hasSuccessfullyRun)
        exit;

  }

  static function loadedINI () {

    $loadedINIFiles = [];

    if (is_file(php_ini_loaded_file()))
      $loadedINIFiles[] = php_ini_loaded_file();

    if (php_ini_scanned_files())
      foreach (preg_split('/(?s)[ \t\r\n]*,[ \t\r\n]*/', php_ini_scanned_files()) as $loadedINIFile)
        if (is_file(trim($loadedINIFile)))
          $loadedINIFiles[] = trim($loadedINIFile);

    return implode("\n", array_map('file_get_contents', $loadedINIFiles));

  }

}
