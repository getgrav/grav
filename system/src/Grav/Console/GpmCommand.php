<?php

/**
 * @package    Grav\Console
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ConsoleCommand
 * @package Grav\Console
 */
class GpmCommand extends Command
{
    use ConsoleTrait;

    /**
     * @param string|null $name The name of the command; passing null means it must be set in configure()
     *
     * @throws LogicException When the command name is empty
     */
    public function __construct(string $name = null)
    {
        parent::__construct($name);

        // Add --env option.
        $this->addEnvOption();
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setupConsole($input, $output);

        $grav = Grav::instance();
        $grav['config']->init();
        $grav['uri']->init();
        $grav['accounts'];

        return $this->serve();
    }

    /**
     * Override with your implementation.
     *
     * @return int
     */
    protected function serve()
    {
        // Return error.
        return 1;
    }

    /**
     * @return void
     */
    protected function displayGPMRelease()
    {
        /** @var Config $config */
        $config = Grav::instance()['config'];

        $this->output->writeln('');
        $this->output->writeln('GPM Releases Configuration: <yellow>' . ucfirst($config->get('system.gpm.releases')) . '</yellow>');
        $this->output->writeln('');
    }
}
