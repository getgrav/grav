<?php

/**
 * @package    Grav\Console
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
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
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Trait ConsoleTrait
 * @package Grav\Console
 */
trait ConsoleTrait
{
    /** @var string */
    protected $argv;
    /* @var InputInterface $output */
    protected $input;
    /* @var OutputInterface $output */
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
        $this->output = $output;

        $this->output->getFormatter()->setStyle('normal', new OutputFormatterStyle('white'));
        $this->output->getFormatter()->setStyle('yellow', new OutputFormatterStyle('yellow', null, ['bold']));
        $this->output->getFormatter()->setStyle('red', new OutputFormatterStyle('red', null, ['bold']));
        $this->output->getFormatter()->setStyle('cyan', new OutputFormatterStyle('cyan', null, ['bold']));
        $this->output->getFormatter()->setStyle('green', new OutputFormatterStyle('green', null, ['bold']));
        $this->output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta', null, ['bold']));
        $this->output->getFormatter()->setStyle('white', new OutputFormatterStyle('white', null, ['bold']));

        $this->setupGrav();
    }

    /**
     * @return $this
     */
    final protected function addEnvOption()
    {
        try {
            return $this->addOption(
                'env',
                'e',
                InputOption::VALUE_OPTIONAL,
                'Optional environment to trigger a specific configuration.'
            );
        } catch (LogicException $e) {
            return $this;
        }
    }

    /**
     * @return $this
     */
    final protected function addLanguageOption()
    {
        try {
            return $this->addOption(
                'language',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Optional language to be used (multi-language sites only).'
            );
        } catch (LogicException $e) {
            return $this;
        }
    }

    final protected function setupGrav(): void
    {
        try {
            $environment = $this->input->getOption('env');
        } catch (InvalidArgumentException $e) {
            $environment = null;
        }
        try {
            $language = $this->input->getOption('language');
        } catch (InvalidArgumentException $e) {
            $language = null;
        }

        $grav = Grav::instance();
        if (!$grav->isSetup()) {
            // Set environment.
            $grav->setup($environment);
        } else {
            $this->output->writeln('');
            $this->output->writeln('<red>WARNING</red>: Grav environment already set, please update logic in your CLI command');
        }

        if ($language) {
            // Set used language.
            $this->setLanguage($language);
        }

        // Initialize cache with CLI compatibility
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
        if (!file_exists($path)) {
            $this->output->writeln('');
            $this->output->writeln("<red>ERROR</red>: Destination doesn't exist:");
            $this->output->writeln("       <white>$path</white>");
            $this->output->writeln('');
            exit;
        }

        if (!is_dir($path)) {
            $this->output->writeln('');
            $this->output->writeln("<red>ERROR</red>: Destination chosen to install is not a directory:");
            $this->output->writeln("       <white>$path</white>");
            $this->output->writeln('');
            exit;
        }

        if (!file_exists($path . DS . 'index.php') || !file_exists($path . DS . '.dependencies') || !file_exists($path . DS . 'system' . DS . 'config' . DS . 'system.yaml')) {
            $this->output->writeln('');
            $this->output->writeln('<red>ERROR</red>: Destination chosen to install does not appear to be a Grav instance:');
            $this->output->writeln("       <white>$path</white>");
            $this->output->writeln('');
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
