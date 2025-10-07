<?php

/**
 * @package    Grav\Console
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Application;

use Grav\Common\Grav;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Class GpmApplication
 * @package Grav\Console\Application
 */
class Application extends \Symfony\Component\Console\Application
{
    /** @var string|null */
    protected $environment;
    /** @var string|null */
    protected $language;
    /** @var bool */
    protected $initialized = false;

    /**
     * PluginApplication constructor.
     * @param string $name
     * @param string $version
     */
    public function __construct(string $name = 'UNKNOWN', string $version = 'UNKNOWN')
    {
        parent::__construct($name, $version);

        // Add listener to prepare environment.
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ConsoleEvents::COMMAND, $this->prepareEnvironment(...));

        $this->setDispatcher($dispatcher);
    }

    /**
     * @param InputInterface $input
     * @return string|null
     */
    public function getCommandName(InputInterface $input): ?string
    {
        if ($input->hasParameterOption('--env', true)) {
            $this->environment = $input->getParameterOption('--env');
        }
        if ($input->hasParameterOption('--lang', true)) {
            $this->language = $input->getParameterOption('--lang');
        }

        $this->init();

        return parent::getCommandName($input);
    }

    /**
     * @param ConsoleCommandEvent $event
     * @return void
     */
    public function prepareEnvironment(ConsoleCommandEvent $event): void
    {
    }

    /**
     * @return void
     */
    protected function init(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        $grav = Grav::instance();
        $grav->setup($this->environment);
    }

    /**
     * Add global --env and --lang options.
     *
     * @return InputDefinition
     */
    protected function getDefaultInputDefinition(): InputDefinition
    {
        $inputDefinition = parent::getDefaultInputDefinition();
        $inputDefinition->addOption(
            new InputOption(
                '--env',
                '',
                InputOption::VALUE_OPTIONAL,
                'Use environment configuration (defaults to localhost)'
            )
        );
        $inputDefinition->addOption(
            new InputOption(
                '--lang',
                '',
                InputOption::VALUE_OPTIONAL,
                'Language to be used (defaults to en)'
            )
        );

        return $inputDefinition;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function configureIO(InputInterface $input, OutputInterface $output): void
    {
        $formatter = $output->getFormatter();
        $formatter->setStyle('normal', new OutputFormatterStyle('white'));
        $formatter->setStyle('yellow', new OutputFormatterStyle('yellow', null, ['bold']));
        $formatter->setStyle('red', new OutputFormatterStyle('red', null, ['bold']));
        $formatter->setStyle('cyan', new OutputFormatterStyle('cyan', null, ['bold']));
        $formatter->setStyle('green', new OutputFormatterStyle('green', null, ['bold']));
        $formatter->setStyle('magenta', new OutputFormatterStyle('magenta', null, ['bold']));
        $formatter->setStyle('white', new OutputFormatterStyle('white', null, ['bold']));

        parent::configureIO($input, $output);
    }
}
