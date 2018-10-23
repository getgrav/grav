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
use Symfony\Component\Console\Style\SymfonyStyle;

class SecurityCommand extends ConsoleCommand
{
    /** @var ProgressBar $progress */
    protected $progress;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("security")
            ->setDescription("Capable of running various Security checks")
            ->setHelp('The <info>security</info> runs various security checks on your Grav site');

        $this->source = getcwd();
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {


        /** @var Grav $grav */
        $grav = Grav::instance();

        $grav['uri']->init();
        $grav['config']->init();
        $grav['debugger']->enabled(false);
        $grav['streams'];
        $grav['plugins']->init();
        $grav['themes']->init();


        $grav['twig']->init();
        $grav['pages']->init();

        $this->progress = new ProgressBar($this->output, (count($grav['pages']->routes()) - 1));
        $this->progress->setFormat('Scanning <cyan>%current%</cyan> pages [<green>%bar%</green>] <white>%percent:3s%%</white> %elapsed:6s%');
        $this->progress->setBarWidth(100);

        $io = new SymfonyStyle($this->input, $this->output);
        $io->title('Grav Security Check');

        $output = Security::detectXssFromPages($grav['pages'], [$this, 'outputProgress']);

        $io->newline(2);

        if (!empty($output)) {

            $counter = 1;
            foreach ($output as $route => $results) {

                $results_parts = array_map(function($value, $key) {
                    return $key.': \''.$value . '\'';
                }, array_values($results), array_keys($results));

                $io->writeln($counter++ .' - <cyan>' . $route . '</cyan> → <red>' . implode(', ', $results_parts) . '</red>');
            }

            $io->error('Security Scan complete: ' . count($output) . ' potential XSS issues found...');

        } else {
            $io->success('Security Scan complete: No issues found...');
        }

        $io->newline(1);

    }

    /**
     * @param $args
     */
    public function outputProgress($args)
    {
        switch ($args['type']) {
            case 'count':
                $steps = $args['steps'];
                $freq = intval($steps > 100 ? round($steps / 100) : $steps);
                $this->progress->setMaxSteps($steps);
                $this->progress->setRedrawFrequency($freq);
                break;
            case 'progress':
                if (isset($args['complete']) && $args['complete']) {
                    $this->progress->finish();
                } else {
                    $this->progress->advance();
                }
                break;
        }
    }

}

