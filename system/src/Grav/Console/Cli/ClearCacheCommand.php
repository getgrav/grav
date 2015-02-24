<?php
namespace Grav\Console\Cli;

use Grav\Common\Cache;
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
        // Create a red output option
        $output->getFormatter()->setStyle('red', new OutputFormatterStyle('red'));
        $output->getFormatter()->setStyle('cyan', new OutputFormatterStyle('cyan'));
        $output->getFormatter()->setStyle('green', new OutputFormatterStyle('green'));
        $output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta'));

        $this->cleanPaths($input, $output);
    }

    // loops over the array of paths and deletes the files/folders
    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    private function cleanPaths(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<magenta>Clearing cache</magenta>');
        $output->writeln('');

        if ($input->getOption('all')) {
            $remove = 'all';
        } elseif ($input->getOption('assets-only')) {
            $remove = 'assets-only';
        } elseif ($input->getOption('images-only')) {
            $remove = 'images-only';
        } elseif ($input->getOption('cache-only')) {
            $remove = 'cache-only';
        } else {
            $remove = 'standard';
        }

        foreach (Cache::clearCache($remove) as $result) {
            $output->writeln($result);
        }
    }
}

