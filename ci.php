<?php

$cwd = getcwd();

foreach ([__dir__ . '/../../autoload.php', __dir__ . '/vendor/autoload.php'] as $autoloadFile)
  if (is_file($autoloadFile))
    require $autoloadFile;

chdir($cwd);

\luka8088\ci\Internal::disableXDebug();

if (in_array('xdebug', array_map(function ($name) { return strtolower($name); }, get_loaded_extensions(true))))
  echo "\x1b[33m" .
    "Warning: XDebug is currently enabled. Running ci with XDebug has significant performance implications." .
    "\x1b[0m\n";

ini_set('memory_limit', -1);

\luka8088\phops\initializeStrictMode();

call_user_func(function () {

  $ci = new \luka8088\ci\Application();

  $codePath = getcwd();
  while ($codePath && $codePath != dirname($codePath)) {
    if (is_file($codePath . '/ci.configuration.php')) {
      break;
    }
    if (is_file($codePath . '/ci.configuration.distributed.php')) {
      break;
    }
    if (is_file($codePath . '/composer.json')) {
      break;
    }
    $codePath = dirname($codePath);
  }

  if (strlen($codePath) > 2)
    $ci->setParameter('rootPath', $codePath);

  if (is_file($codePath . '/ci.configuration.distributed.php'))
    $ci->configurations[] = $codePath . '/ci.configuration.distributed.php';

  if (is_file($codePath . '/ci.configuration.php'))
    $ci->configurations[] = $codePath . '/ci.configuration.php';

  $ci->run();

});
