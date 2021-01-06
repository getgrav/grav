<?php

/**
 * @package    Grav\Console
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Application;

use Grav\Common\Grav;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

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
}
