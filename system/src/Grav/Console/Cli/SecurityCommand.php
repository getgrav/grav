<?php
/**
 * @package    Grav.Console
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Grav;
use Grav\Common\Security;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;

class SecurityCommand extends ConsoleCommand
{
    /** @var string $source */
    protected $source;

    /** @var ProgressBar $progress */
    protected $progress;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("security")
            ->addArgument(
                'xss',
                InputArgument::OPTIONAL,
                'Perform XSS security checks on all pages'

            )
            ->setDescription("Capable of running XSS Security checks")
            ->setHelp('The <info>security</info> runs various security checks on your Grav site');

        $this->source = getcwd();
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        $this->progress = new ProgressBar($this->output);
        $this->progress->setFormat('Archiving <cyan>%current%</cyan> files [<green>%bar%</green>] %elapsed:6s% %memory:6s%');

        /** @var Grav $grav */
        $grav = Grav::instance();

        $grav['config']->init();
        $grav['debugger']->enabled(false);
        $grav['twig']->init();
        $grav['pages']->init();

        $results = Security::detectXssFromPages($grav['pages'], [$this, 'output']);

        print_r($results);

    }

    /**
     * @param $args
     */
    public function output($args)
    {
        switch ($args['type']) {
            case 'message':
                $this->output->writeln($args['message']);
                break;
            case 'progress':
                if ($args['complete']) {
                    $this->progress->finish();
                } else {
                    $this->progress->advance();
                }
                break;
        }
    }

}

