<?php

/**
 * @package    Grav\Console
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console;

use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Page\Page;
use Grav\Common\Processors\InitializeProcessor;
use RocketTheme\Toolbox\Event\Event;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleCommand extends Command
{
    use ConsoleTrait;

    /** @var bool */
    private $plugins_initialized = false;
    /** @var bool */
    private $themes_initialized = false;
    /** @var bool */
    private $pages_initialized = false;

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setupConsole($input, $output);
        $this->serve();
    }

    /**
     * Override with your implementation.
     */
    protected function serve()
    {
    }

    /**
     * Initialize Grav.
     *
     * - Load configuration
     * - Disable debugger
     * - Set timezone, locale
     * - Load plugins
     * - Set Users type to be used in the site
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

    protected function displayGPMRelease()
    {
        $this->output->writeln('');
        $this->output->writeln('GPM Releases Configuration: <yellow>' . ucfirst(Grav::instance()['config']->get('system.gpm.releases')) . '</yellow>');
        $this->output->writeln('');
    }

}
