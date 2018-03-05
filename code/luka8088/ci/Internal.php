<?php

namespace luka8088\ci;

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

      $alteredINIFile = tmpfile();
      fwrite($alteredINIFile, Internal::disableINIXDebug(Internal::loadedINI()));
      $alteredINIFileInfo = stream_get_meta_data($alteredINIFile);

      /**
       * For some unknown reason the combination of OPCache and XDebug causes
       * an error in the sub-process making it exit immediately.
       * Reseting OPCache at this point addresses that.
       */
      if (function_exists('opcache_reset'))
        opcache_reset();

      $process = proc_open(
        (PHP_BINARY ? PHP_BINARY : PHP_BINDIR . '/php')
        . (PHP_SAPI == 'phpdbg' ? ' -qrr' : '')
        . ' ' . '-c ' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
          ? '"' . addcslashes($alteredINIFileInfo['uri'], '\\"') . '"'
          : escapeshellarg($alteredINIFileInfo['uri']))
        . ' ' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
          ? '"' . addcslashes(debug_backtrace()[0]['file'], '\\"') . '"'
          : escapeshellarg(debug_backtrace()[0]['file']))
        . ' ' . implode(' ', array_slice($GLOBALS['argv'], 1)),
        [
          0 => fopen('php://stdin', 'r'),
          1 => fopen('php://stdout', 'w'),
          2 => fopen('php://stderr', 'w'),
        ],
        $pipes,
        null,
        array_filter($_SERVER + [
          'PHP_INI_SCAN_DIR' => '/dev/null',
          'PHP_INI_SCAN_DIR_BACKUP' => getenv('PHP_INI_SCAN_DIR'),
          'xDebugDisableAttemptMade' => '1',
        ], function ($value) { return !is_array($value); })
      );

      $exitCode = proc_close($process);

      exit($exitCode);

  }

  static function disableINIXDebug ($iniContents) {
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
    return preg_replace(
      '/(?s)(\A|(?<=\n))zend_extension[ \t]*\=[ \t]*[A-Za-z0-9\\\\\/\_\.]*(php_)?xdebug\.(so|dll)/',
      '',
      $iniContents
    );
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
