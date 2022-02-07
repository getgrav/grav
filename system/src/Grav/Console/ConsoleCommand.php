<?php

/**
 * @package    Grav\Console
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ConsoleCommand
 * @package Grav\Console
 */
class ConsoleCommand extends Command
{
    use ConsoleTrait;

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setupConsole($input, $output);

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
}
