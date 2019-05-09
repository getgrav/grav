<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Cache;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputOption;

class ClearCacheCommand extends ConsoleCommand
{
    protected function configure()
    {
        $this
            ->setName('cache')
            ->setAliases(['clearcache', 'cache-clear'])
            ->setDescription('Clears Grav cache')
            ->addOption('invalidate', null, InputOption::VALUE_NONE, 'Invalidate cache, but do not remove any files')
            ->addOption('purge', null, InputOption::VALUE_NONE, 'If set purge old caches')
            ->addOption('all', null, InputOption::VALUE_NONE, 'If set will remove all including compiled, twig, doctrine caches')
            ->addOption('assets-only', null, InputOption::VALUE_NONE, 'If set will remove only assets/*')
            ->addOption('images-only', null, InputOption::VALUE_NONE, 'If set will remove only images/*')
            ->addOption('cache-only', null, InputOption::VALUE_NONE, 'If set will remove only cache/*')
            ->addOption('tmp-only', null, InputOption::VALUE_NONE, 'If set will remove only tmp/*')

            ->setHelp('The <info>cache</info> command allows you to interact with Grav cache');
    }

    protected function serve()
    {
        $this->cleanPaths();
    }

    /**
     * loops over the array of paths and deletes the files/folders
     */
    private function cleanPaths()
    {
        $this->output->writeln('');


        if ($this->input->getOption('purge')) {
            $this->output->writeln('<magenta>Purging old cache</magenta>');
            $this->output->writeln('');

            $msg = Cache::purgeJob();
            $this->output->writeln($msg);
        } else {
            $this->output->writeln('<magenta>Clearing cache</magenta>');
            $this->output->writeln('');

            if ($this->input->getOption('all')) {
                $remove = 'all';
            } elseif ($this->input->getOption('assets-only')) {
                $remove = 'assets-only';
            } elseif ($this->input->getOption('images-only')) {
                $remove = 'images-only';
            } elseif ($this->input->getOption('cache-only')) {
                $remove = 'cache-only';
            } elseif ($this->input->getOption('tmp-only')) {
                $remove = 'tmp-only';
            } elseif ($this->input->getOption('invalidate')) {
                $remove = 'invalidate';
            } else {
                $remove = 'standard';
            }

            foreach (Cache::clearCache($remove) as $result) {
                $this->output->writeln($result);
            }
        }
    }
}

