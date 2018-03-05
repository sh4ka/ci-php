<?php

namespace luka8088\ci;

use \ArrayAccess;
use \Exception;
use \luka8088\ExtensionCall;
use \luka8088\ExtensionInterface;
use \luka8088\phops as op;
use \Symfony\Component\Console\Command\Command;
use \Symfony\Component\Console\Input\InputDefinition;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;

class Application extends \Symfony\Component\Console\Application implements ArrayAccess {

  /** @internal */
  protected $extensionInterface = null;

  public $configurations = [];
  public $parameters = [];
  public $extensions = [];
  public $paths = [];

  function __construct () {

    parent::__construct();

    $this->extensionInterface = new ExtensionInterface();

    $this->extensionInterface[] = [
      /** @ExtensionCall('luka8088.ci.test.begin') */ function () {},
      /** @ExtensionCall('luka8088.ci.test.end') */ function () {},
      /** @ExtensionCall('luka8088.ci.test.run') */ function () {},
      /** @ExtensionCall('luka8088.ci.test.testFound') */ function ($test) {},
      /** @ExtensionCall('luka8088.ci.test.testReport') */ function ($issue) {},
    ];

    $this->getDefinition()->addOption(new InputOption(
      '--configuration',
      '-c',
      InputOption::VALUE_OPTIONAL,
      'Path to a configuration file.'
    ));

    $applicationMetaContext = op\metaContextCreateScoped(Application::class, $this);
    $extensionInterfaceMetaContext = op\metaContextCreateScoped(ExtensionInterface::class, $this->extensionInterface);

    $this->createParameter('rootPath', '');

    $this->add(new \luka8088\ci\cli\Command());
    $this->add(new \luka8088\ci\test\Command());

  }

  function doRun (InputInterface $input = null, OutputInterface $output = null) {

    $applicationMetaContext = op\metaContextCreateScoped(Application::class, $this);
    $extensionInterfaceMetaContext = op\metaContextCreateScoped(ExtensionInterface::class, $this->extensionInterface);

    $configurationPath = $input->getParameterOption('-c')
      ? $input->getParameterOption('-c')
      : $input->getParameterOption('--configuration');
    if ($configurationPath) {
      if (!is_file($configurationPath))
        throw new Exception('Configuration file *' . $configurationPath . '* not found.');
      $this->configurations[] = $configurationPath;
    }

    foreach ($this->configurations as $configurationPath) {
      $configurator = require($configurationPath);
      $configurator($this);
    }

    return parent::doRun($input, $output);

  }

  function createParameter ($name, $value) {
    if (isset($this->parameters[$name]))
      throw new Exception('Parameter *' . $name . '* already created.');
    $this->parameters[$name] = $value;
  }

  function setParameter ($name, $value) {
    if (!isset($this->parameters[$name]))
      throw new Exception('Parameter *' . $name . '* does not exist.');
    $this->parameters[$name] = $value;
  }

  function getParameter ($name) {
    return $this->parameters[$name];
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
