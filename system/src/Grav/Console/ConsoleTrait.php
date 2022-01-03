<?php

/**
 * @package    Grav\Console
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console;

use Exception;
use Grav\Common\Cache;
use Grav\Common\Grav;
use Grav\Common\Composer;
use Grav\Common\Language\Language;
use Grav\Common\Processors\InitializeProcessor;
use Grav\Console\Cli\ClearCacheCommand;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\YamlFile;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Trait ConsoleTrait
 * @package Grav\Console
 */
trait ConsoleTrait
{
    /** @var string */
    protected $argv;
    /** @var InputInterface */
    protected $input;
    /** @var SymfonyStyle */
    protected $output;
    /** @var array */
    protected $local_config;

    /** @var bool */
    private $plugins_initialized = false;
    /** @var bool */
    private $themes_initialized = false;
    /** @var bool */
    private $pages_initialized = false;

    /**
     * Set colors style definition for the formatter.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return void
     */
    public function setupConsole(InputInterface $input, OutputInterface $output)
    {
        $this->argv = $_SERVER['argv'][0];
        $this->input = $input;
        $this->output = new SymfonyStyle($input, $output);

        $this->setupGrav();
    }

    public function getInput(): InputInterface
    {
        return $this->input;
    }

    /**
     * @return SymfonyStyle
     */
    public function getIO(): SymfonyStyle
    {
        return $this->output;
    }

    /**
     * Adds an option.
     *
     * @param string                        $name        The option name
     * @param string|array|null             $shortcut    The shortcuts, can be null, a string of shortcuts delimited by | or an array of shortcuts
     * @param int|null                      $mode        The option mode: One of the InputOption::VALUE_* constants
     * @param string                        $description A description text
     * @param string|string[]|int|bool|null $default     The default value (must be null for InputOption::VALUE_NONE)
     * @return $this
     * @throws InvalidArgumentException If option mode is invalid or incompatible
     */
    public function addOption($name, $shortcut = null, $mode = null, $description = '', $default = null)
    {
        if ($name !== 'env' && $name !== 'lang') {
            parent::addOption($name, $shortcut, $mode, $description, $default);
        }

        return $this;
    }

    /**
     * @return void
     */
    final protected function setupGrav(): void
    {
        try {
            $language = $this->input->getOption('lang');
            if ($language) {
                // Set used language.
                $this->setLanguage($language);
            }
        } catch (InvalidArgumentException $e) {}

        // Initialize cache with CLI compatibility
        $grav = Grav::instance();
        $grav['config']->set('system.cache.cli_compatibility', true);
    }

    /**
     * Initialize Grav.
     *
     * - Load configuration
     * - Initialize logger
     * - Disable debugger
     * - Set timezone, locale
     * - Load plugins (call PluginsLoadedEvent)
     * - Set Pages and Users type to be used in the site
     *
     * Safe to be called multiple times.
     *
     * @return $this
     */
    final protected function initializeGrav()
    {
        InitializeProcessor::initializeCli(Grav::instance());

        return $this;
    }

    /**
     * Set language to be used in CLI.
     *
     * @param string|null $code
     * @return $this
     */
    final protected function setLanguage(string $code = null)
    {
        $this->initializeGrav();

        $grav = Grav::instance();
        /** @var Language $language */
        $language = $grav['language'];
        if ($language->enabled()) {
            if ($code && $language->validate($code)) {
                $language->setActive($code);
            } else {
                $language->setActive($language->getDefault());
            }
        }

        return $this;
    }

    /**
     * Properly initialize plugins.
     *
     * - call $this->initializeGrav()
     * - call onPluginsInitialized event
     *
     * Safe to be called multiple times.
     *
     * @return $this
     */
    final protected function initializePlugins()
    {
        if (!$this->plugins_initialized) {
            $this->plugins_initialized = true;

            $this->initializeGrav();

            // Initialize plugins.
            $grav = Grav::instance();
            $grav['plugins']->init();
            $grav->fireEvent('onPluginsInitialized');
        }

        return $this;
    }

    /**
     * Properly initialize themes.
     *
     * - call $this->initializePlugins()
     * - initialize theme (call onThemeInitialized event)
     *
     * Safe to be called multiple times.
     *
     * @return $this
     */
    final protected function initializeThemes()
    {
        if (!$this->themes_initialized) {
            $this->themes_initialized = true;

            $this->initializePlugins();

            // Initialize themes.
            $grav = Grav::instance();
            $grav['themes']->init();
        }

        return $this;
    }

    /**
     * Properly initialize pages.
     *
     * - call $this->initializeThemes()
     * - initialize assets (call onAssetsInitialized event)
     * - initialize twig (calls the twig events)
     * - initialize pages (calls onPagesInitialized event)
     *
     * Safe to be called multiple times.
     *
     * @return $this
     */
    final protected function initializePages()
    {
        if (!$this->pages_initialized) {
            $this->pages_initialized = true;

            $this->initializeThemes();

            $grav = Grav::instance();

            // Initialize assets.
            $grav['assets']->init();
            $grav->fireEvent('onAssetsInitialized');

            // Initialize twig.
            $grav['twig']->init();

            // Initialize pages.
            $pages = $grav['pages'];
            $pages->init();
            $grav->fireEvent('onPagesInitialized', new Event(['pages' => $pages]));
        }

        return $this;
    }

    /**
     * @param string $path
     * @return void
     */
    public function isGravInstance($path)
    {
        $io = $this->getIO();

        if (!file_exists($path)) {
            $io->writeln('');
            $io->writeln("<red>ERROR</red>: Destination doesn't exist:");
            $io->writeln("       <white>$path</white>");
            $io->writeln('');
            exit;
        }

        if (!is_dir($path)) {
            $io->writeln('');
            $io->writeln("<red>ERROR</red>: Destination chosen to install is not a directory:");
            $io->writeln("       <white>$path</white>");
            $io->writeln('');
            exit;
        }

        if (!file_exists($path . DS . 'index.php') || !file_exists($path . DS . '.dependencies') || !file_exists($path . DS . 'system' . DS . 'config' . DS . 'system.yaml')) {
            $io->writeln('');
            $io->writeln('<red>ERROR</red>: Destination chosen to install does not appear to be a Grav instance:');
            $io->writeln("       <white>$path</white>");
            $io->writeln('');
            exit;
        }
    }

    /**
     * @param string $path
     * @param string $action
     * @return string|false
     */
    public function composerUpdate($path, $action = 'install')
    {
        $composer = Composer::getComposerExecutor();

        return system($composer . ' --working-dir="'.$path.'" --no-interaction --no-dev --prefer-dist -o '. $action);
    }

    /**
     * @param array $all
     * @return int
     * @throws Exception
     */
    public function clearCache($all = [])
    {
        if ($all) {
            $all = ['--all' => true];
        }

        $command = new ClearCacheCommand();
        $input = new ArrayInput($all);
        return $command->run($input, $this->output);
    }

    /**
     * @return void
     */
    public function invalidateCache()
    {
        Cache::invalidateCache();
    }

    /**
     * Load the local config file
     *
     * @return string|false The local config file name. false if local config does not exist
     */
    public function loadLocalConfig()
    {
        $home_folder = getenv('HOME') ?: getenv('HOMEDRIVE') . getenv('HOMEPATH');
        $local_config_file = $home_folder . '/.grav/config';

        if (file_exists($local_config_file)) {
            $file = YamlFile::instance($local_config_file);
            $this->local_config = $file->content();
            $file->free();

            return $local_config_file;
        }

        return false;
    }
}
