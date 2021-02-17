<?php

/**
 * @package    Grav\Console
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Application;

use Grav\Common\Grav;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
     * @param InputInterface $input
     * @return string|null
     */
    public function getCommandName(InputInterface $input): ?string
    {
        $this->environment = $input->getOption('env');
        $this->language = $input->getOption('lang') ?? $this->language;
        $this->init();

        return parent::getCommandName($input);
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
     * Add global a --env option.
     *
     * @return InputDefinition
     */
    protected function getDefaultInputDefinition(): InputDefinition
    {
        $inputDefinition = parent::getDefaultInputDefinition();
        $inputDefinition->addOption(
            new InputOption(
                'env',
                null,
                InputOption::VALUE_OPTIONAL,
                'Use environment configuration (defaults to localhost)'
            )
        );
        $inputDefinition->addOption(
            new InputOption(
                'lang',
                null,
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
    protected function configureIO(InputInterface $input, OutputInterface $output)
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
