<?php

/**
 * @package    Grav\Console
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Application\CommandLoader;

use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

/**
 * Class GpmApplication
 * @package Grav\Console\Application
 */
class PluginCommandLoader implements CommandLoaderInterface
{
    /** @var array */
    private $commands;

    /**
     * PluginCommandLoader constructor.
     *
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->commands = [];

        try {
            $path = "plugins://{$name}/cli";
            $pattern = '([A-Z]\w+Command\.php)';

            $commands = is_dir($path) ? Folder::all($path, ['compare' => 'Filename', 'pattern' => '/' . $pattern . '$/usm', 'levels' => 1]) : [];
        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to load console commands for plugin {$name}");
        }

        $grav = Grav::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        foreach ($commands as $command_path) {
            $full_path = $locator->findResource("plugins://{$name}/cli/{$command_path}");
            require_once $full_path;

            $command_class = 'Grav\Plugin\Console\\' . preg_replace('/.php$/', '', $command_path);
            if (class_exists($command_class)) {
                $command = new $command_class();
                if ($command instanceof Command) {
                    $this->commands[$command->getName()] = $command;
                }
            }
        }
    }

    /**
     * @param string $name
     * @return Command
     */
    public function get($name): Command
    {
        $command = $this->commands[$name] ?? null;
        if (null === $command) {
            throw new CommandNotFoundException(sprintf('The command "%s" does not exist.', $name));
        }

        return $command;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function has($name): bool
    {
        return isset($this->commands[$name]);
    }

    /**
     * @return string[]
     */
    public function getNames(): array
    {
        return array_keys($this->commands);
    }
}
