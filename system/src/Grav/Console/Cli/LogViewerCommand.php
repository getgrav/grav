<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Grav;
use Grav\Common\Helpers\LogViewer;
use Grav\Common\Utils;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

class LogViewerCommand extends ConsoleCommand
{
    protected function configure()
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
            ->setHelp("Display the last few entries of Grav log");
    }

    protected function serve()
    {
        $grav = Grav::instance();
        $grav->setup();

        $file = $this->input->getOption('file') ?? 'grav.log';
        $lines = $this->input->getOption('lines') ?? 20;
        $verbose = $this->input->getOption('verbose', false);

        $io = new SymfonyStyle($this->input, $this->output);

        $io->title('Log Viewer');

        $io->writeln(sprintf('viewing last %s entries in <white>%s</white>', $lines, $file));
        $io->newLine();

        $viewer = new LogViewer();

        $logfile = $grav['locator']->findResource("log://" . $file);

        if ($logfile) {
            $rows = $viewer->objectTail($logfile, $lines, true);
            foreach ($rows as $log) {
                $date = $log['date'];
                $level_color = LogViewer::levelColor($log['level']);

                if ($date instanceof \DateTime) {
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
        } else {
            $io->error('cannot find the log file: logs/' . $file);
        }

    }
}
