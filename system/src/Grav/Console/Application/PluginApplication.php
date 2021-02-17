<?php

/**
 * @package    Grav\Console
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Application;

use Grav\Common\Grav;
use Grav\Common\Plugins;
use Grav\Console\Application\CommandLoader\PluginCommandLoader;
use Grav\Console\Plugin\PluginListCommand;
use Symfony\Component\Console\Exception\NamespaceNotFoundException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class PluginApplication
 * @package Grav\Console\Application
 */
class PluginApplication extends Application
{
    /** @var string|null */
    protected $pluginName;

    /**
     * PluginApplication constructor.
     * @param string $name
     * @param string $version
     */
    public function __construct(string $name = 'UNKNOWN', string $version = 'UNKNOWN')
    {
        parent::__construct($name, $version);

        $this->addCommands([
            new PluginListCommand(),
        ]);
    }

    /**
     * @param string $pluginName
     * @return void
     */
    public function setPluginName(string $pluginName): void
    {
        $this->pluginName = $pluginName;
    }

    /**
     * @return string
     */
    public function getPluginName(): string
    {
        return $this->pluginName;
    }

    /**
     * @param InputInterface|null $input
     * @param OutputInterface|null $output
     * @return int
     * @throws Throwable
     */
    public function run(InputInterface $input = null, OutputInterface $output = null): int
    {
        if (null === $input) {
            $argv = $_SERVER['argv'] ?? [];

            $bin = array_shift($argv);
            $this->pluginName = array_shift($argv);
            $argv = array_merge([$bin], $argv);

            $input = new ArgvInput($argv);
        }

        return parent::run($input, $output);
    }

    /**
     * @return void
     */
    protected function init(): void
    {
        if ($this->initialized) {
            return;
        }

        parent::init();

        if (null === $this->pluginName) {
            $this->setDefaultCommand('plugins:list');

            return;
        }

        $grav = Grav::instance();
        $grav->initializeCli();

        /** @var Plugins $plugins */
        $plugins = $grav['plugins'];

        $plugin = $this->pluginName ? $plugins::get($this->pluginName) : null;
        if (null === $plugin) {
            throw new NamespaceNotFoundException("Plugin \"{$this->pluginName}\" is not installed.");
        }
        if (!$plugin->enabled) {
            throw new NamespaceNotFoundException("Plugin \"{$this->pluginName}\" is not enabled.");
        }

        $this->setCommandLoader(new PluginCommandLoader($this->pluginName));
    }
}
