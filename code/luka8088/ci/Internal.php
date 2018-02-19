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
      if (isset($GLOBALS["__xdebug_disable_attempt_made"]))
        return;

      if (!in_array('xdebug', array_map(function ($name) { return strtolower($name); }, get_loaded_extensions(true))))
        return;

      $process = new \Symfony\Component\Process\PhpProcess('<?php
        $GLOBALS["__xdebug_disable_attempt_made"] = true;
        ' . (isset($_SERVER['argc']) ? '$_SERVER["argc"] = ' . var_export($_SERVER['argc'], true) . ';' : '') . '
        ' . (isset($_SERVER['argv']) ? '$_SERVER["argv"] = ' . var_export($_SERVER['argv'], true) . ';' : '') . '
        ' . (isset($GLOBALS['argc']) ? '$GLOBALS["argc"] = ' . var_export($GLOBALS['argc'], true) . ';' : '') . '
        ' . (isset($GLOBALS['argv']) ? '$GLOBALS["argv"] = ' . var_export($GLOBALS['argv'], true) . ';' : '') . '
        require ' . var_export(debug_backtrace()[0]['file'], true) . ';
      ');

      $process->setTimeout(null);

      /**
       * Don't load the default ini file. This is the main reason why we are running
       * a sub-process in the first place - to disable XDebug.
       * It seems that XDebug can't be disabled during runtime, nor can an extension
       * defined in the php.ini be excluded from loading with parameters.
       * So far this seems to be the only way not to load XDebug.
       */
      $process->setCommandLine($process->getCommandLine() . ' -n');

      /**
       * For some reason these two extensions are not statically linked
       * on *nix systems so we need to load the explicitly.
       */
      if (PHP_SHLIB_SUFFIX == 'so')
        $process->setCommandLine($process->getCommandLine() . ' -dextension=tokenizer.so -dextension=json.so -dextension=simplexml.so -dextension=xml.so -dextension=xmlwriter.so -dextension=iconv.so');

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

}
