<?php

namespace luka8088\ci;

use \ArrayAccess;
use \luka8088\ExtensionCall;
use \luka8088\ExtensionInterface;
use \luka8088\phops as op;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;

class Application extends \Symfony\Component\Console\Application implements ArrayAccess {

  /** @internal */
  protected $extensionInterface = null;

  public $rootPath = '';
  public $extensions = [];
  public $paths = [];

  function __construct () {

    parent::__construct();

    $this->extensionInterface = new ExtensionInterface();

    $this->extensionInterface[] = [
      /** @ExtensionCall('luka8088.ci.test.begin') */ function () {},
      /** @ExtensionCall('luka8088.ci.test.end') */ function () {},
      /** @ExtensionCall('luka8088.ci.test.run') */ function () {},
      /** @ExtensionCall('luka8088.ci.test.testFound') */ function ($test, &$keep = true) {},
      /** @ExtensionCall('luka8088.ci.test.testReport') */ function ($issue) {},
    ];

  }

  function doRun (InputInterface $input = null, OutputInterface $output = null) {

    $applicationMetaContext = op\metaContextCreateScoped(Application::class, $this);
    $extensionInterfaceMetaContext = op\metaContextCreateScoped(ExtensionInterface::class, $this->extensionInterface);

    $this->add(new \luka8088\ci\cli\Command());
    $this->add(new \luka8088\ci\test\Command());

    return parent::doRun($input, $output);

  }

  function setRootPath ($path) {
    $this->rootPath = $path;
  }

  /**
   * @param string $path Path to a file or folder with code files.
   */
  function addPath ($path) {
    $this->paths[] = strpos($path, '/') === 0 || strpos($path, ':\\') !== false ? $path : $path;
    return $this;
  }

  function registerExtension ($extension) {
    $this->extensions[] = $extension;
    $this->extensionInterface[] = $extension;
  }

  /** @internal */
  function offsetExists ($offset) {
    assert(false);
  }

  /** @internal */
  function offsetGet ($offset) {
    assert(false);
  }

  /** @internal */
  function offsetSet ($offset, $value) {
    assert($offset === null);
    $this->registerExtension($value);
  }

  /** @internal */
  function offsetUnset ($offset) {
    assert(false);
  }

}
