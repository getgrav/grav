<?php
namespace Grav\Console\Cli;

use Grav\Common\Cache;
use Grav\Console\ConsoleTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ClearCacheCommand
 * @package Grav\Console\Cli
 */
class ClearCacheCommand extends Command
{
    use ConsoleTrait;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("clear-cache")
            ->setDescription("Clears Grav cache")
            ->addOption('all', null, InputOption::VALUE_NONE, 'If set will remove all including compiled, twig, doctrine caches')
            ->addOption('assets-only', null, InputOption::VALUE_NONE, 'If set will remove only assets/*')
            ->addOption('images-only', null, InputOption::VALUE_NONE, 'If set will remove only images/*')
            ->addOption('cache-only', null, InputOption::VALUE_NONE, 'If set will remove only cache/*')
            ->setHelp('The <info>clear-cache</info> deletes all cache files');
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
        $this->cleanPaths();
    }

    /**
     * loops over the array of paths and deletes the files/folders
     */
    private function cleanPaths()
    {
        $this->output->writeln('');
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
        } else {
            $remove = 'standard';
        }

        foreach (Cache::clearCache($remove) as $result) {
            $this->output->writeln($result);
        }
    }
}

