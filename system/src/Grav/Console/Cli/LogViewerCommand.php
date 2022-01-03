<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use DateTime;
use Grav\Common\Grav;
use Grav\Common\Helpers\LogViewer;
use Grav\Console\GravCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class LogViewerCommand
 * @package Grav\Console\Cli
 */
class LogViewerCommand extends GravCommand
{
    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('logviewer')
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_OPTIONAL,
                'custom log file location (default = grav.log)'
            )
            ->addOption(
                'lines',
                'l',
                InputOption::VALUE_OPTIONAL,
                'number of lines (default = 10)'
            )
            ->setDescription('Display the last few entries of Grav log')
            ->setHelp('Display the last few entries of Grav log');
    }

    /**
     * @return int
     */
    protected function serve(): int
    {
        $input = $this->getInput();
        $io = $this->getIO();

        $file = $input->getOption('file') ?? 'grav.log';
        $lines = $input->getOption('lines') ?? 20;
        $verbose = $input->getOption('verbose') ?? false;

        $io->title('Log Viewer');

        $io->writeln(sprintf('viewing last %s entries in <white>%s</white>', $lines, $file));
        $io->newLine();

        $viewer = new LogViewer();

        $grav = Grav::instance();

        $logfile = $grav['locator']->findResource('log://' . $file);
        if (!$logfile) {
            $io->error('cannot find the log file: logs/' . $file);

            return 1;
        }

        $rows = $viewer->objectTail($logfile, $lines, true);
        foreach ($rows as $log) {
            $date = $log['date'];
            $level_color = LogViewer::levelColor($log['level']);

            if ($date instanceof DateTime) {
                $output = "<yellow>{$log['date']->format('Y-m-d h:i:s')}</yellow> [<{$level_color}>{$log['level']}</{$level_color}>]";
                if ($log['trace'] && $verbose) {
                    $output .= " <white>{$log['message']}</white>\n";
                    foreach ((array) $log['trace'] as $index => $tracerow) {
                        $output .= "<white>{$index}</white>${tracerow}\n";
                    }
                } else {
                    $output .= " {$log['message']}";
                }
                $io->writeln($output);
            }
        }

        return 0;
    }
}
