<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Grav;
use Grav\Common\Log\LogRotator;
use Grav\Common\Utils;
use Grav\Console\GravCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class LogRotateCommand
 * @package Grav\Console\Cli
 */
class LogRotateCommand extends GravCommand
{
    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('logrotate')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Rotate every non-empty log now, whatever its size')
            ->setDescription('Rotates the log files in logs/ that have reached system.log.rotate.max_size')
            ->setHelp(<<<'HELP'
The <info>logrotate</info> command rotates every <comment>*.log</comment> in the log folder that has reached
<comment>system.log.rotate.max_size</comment>: grav.log becomes grav.log.1, grav.log.1 becomes grav.log.2.gz,
and so on, keeping <comment>system.log.rotate.max_files</comment> of them.

The scheduler runs this every hour while rotation is on, and grav.log, security.log and
scheduler.log also rotate on their own as they are written.

<comment>Rotate logs over the size limit:</comment>
  <info>bin/grav logrotate</info>

<comment>Rotate every log now:</comment>
  <info>bin/grav logrotate --force</info>
HELP
            );
    }

    /**
     * @return int
     */
    protected function serve(): int
    {
        $this->initializeGrav();

        $io = $this->getIO();
        $force = (bool) $this->getInput()->getOption('force');

        $settings = LogRotator::settings() ?? LogRotator::normalize([]);
        if (!$settings['enabled'] && !$force) {
            $io->writeln('Log rotation is turned off (<white>system.log.rotate.enabled</white>). Use <white>--force</white> to rotate anyway.');

            return 0;
        }

        $dir = Grav::instance()['locator']->findResource('log://', true, true);
        if (!$dir || !is_dir($dir)) {
            $io->error('Cannot find the log folder');

            return 1;
        }

        $result = LogRotator::rotateDirectory($dir, $settings, $force);

        if ($result['rotated']) {
            $io->writeln('Rotated <green>' . implode('</green>, <green>', $result['rotated']) . '</green>');
        } else {
            $io->writeln($force ? 'No logs to rotate' : 'No logs over ' . Utils::prettySize($settings['max_size']));
        }

        if ($result['skipped']) {
            $io->warning('Could not rotate ' . implode(', ', $result['skipped']) . ': another process is rotating, or the file is in use');

            return 1;
        }

        return 0;
    }
}
