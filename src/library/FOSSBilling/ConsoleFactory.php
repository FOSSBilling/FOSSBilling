<?php

namespace FOSSBilling;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;

class ConsoleFactory implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function createConsoleApplication(): Application
    {
        $container = [
            'console.name' => 'FOSSBilling Console Commands',
            'console.version' => $this->di['mod_service']('system')->getVersion(),
            'console.allow_namespace' => Config::getProperty('console.allow_namespace', true),
        ];
        $console = new Application($container['console.name'], $container['console.version']);

        if ($container['console.allow_namespace']) {
            $console->getDefinition()->addOption(
                new InputOption(
                    'namespace',
                    null,
                    InputOption::VALUE_OPTIONAL,
                    'Specify namespace for the console'
                )
            );
            $console->setDefaultCommand('list');
            $namespace = array_reduce($_SERVER['argv'], function ($carry, $arg) {
                return str_starts_with($arg, '--namespace=') ? substr($arg, strlen('--namespace=')) : $carry;
            }, '');

            if (!empty($namespace)) {
                if (!class_exists($namespace)) {
                    return $console;
                }
                $class = new $namespace();
                $class->setDi($this->di);
                $console->add($class);

                return $console;
            }
        }

        try {
            $modules = $this->di['mod_service']('extension')->getCoreAndActiveModules();
        } catch (Exception $e) {
            $modules = $this->di['mod']('extension')->getCoreModules();
        }

        // Dynamically load the commands from the modules
        foreach ($modules as $module) {
            $cap = ucfirst($module);
            $commands = glob(PATH_ROOT . '/modules/' . $cap . '/Console/*.php');
            foreach ($commands as $command) {
                $commandClass = basename($command, '.php');
                $class = "\\Box\\Mod\\$cap\\Console\\$commandClass";
                if (class_exists($class)) {
                    $instance = new $class();
                    $instance->setDi($this->di);
                    $console->add($instance);
                }
            }
        }

        return $console;
    }
}
