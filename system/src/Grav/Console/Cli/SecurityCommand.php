<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Grav;
use Grav\Common\Security;
use Grav\Console\GravCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use function count;

/**
 * Class SecurityCommand
 * @package Grav\Console\Cli
 */
class SecurityCommand extends GravCommand
{
    /** @var ProgressBar $progress */
    protected $progress;

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('security')
            ->setDescription('Capable of running various Security checks')
            ->setHelp('The <info>security</info> runs various security checks on your Grav site');
    }

    /**
     * @return int
     */
    protected function serve(): int
    {
        $this->initializePages();

        $io = $this->getIO();

        /** @var Grav $grav */
        $grav = Grav::instance();
        $this->progress = new ProgressBar($this->output, count($grav['pages']->routes()) - 1);
        $this->progress->setFormat('Scanning <cyan>%current%</cyan> pages [<green>%bar%</green>] <white>%percent:3s%%</white> %elapsed:6s%');
        $this->progress->setBarWidth(100);

        $io->title('Grav Security Check');
        $io->newline(2);

        $output = Security::detectXssFromPages($grav['pages'], false, [$this, 'outputProgress']);

        $error = 0;
        if (!empty($output)) {
            $counter = 1;
            foreach ($output as $route => $results) {
                $results_parts = array_map(static function ($value, $key) {
                    return $key.': \''.$value . '\'';
                }, array_values($results), array_keys($results));

                $io->writeln($counter++ .' - <cyan>' . $route . '</cyan> → <red>' . implode(', ', $results_parts) . '</red>');
            }

            $error = 1;
            $io->error('Security Scan complete: ' . count($output) . ' potential XSS issues found...');
        } else {
            $io->success('Security Scan complete: No issues found...');
        }

        $io->newline(1);

        return $error;
    }

    /**
     * @param array $args
     * @return void
     */
    public function outputProgress(array $args): void
    {
        switch ($args['type']) {
            case 'count':
                $steps = $args['steps'];
                $freq = (int)($steps > 100 ? round($steps / 100) : $steps);
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
