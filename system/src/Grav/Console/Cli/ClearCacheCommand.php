<?php
namespace Grav\Console\Cli;

use Grav\Common\Filesystem\Folder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ClearCacheCommand
 * @package Grav\Console\Cli
 */
class ClearCacheCommand extends Command
{

    /**
     * @var array
     */
    protected $standard_remove = [
        'cache/twig/',
        'cache/doctrine/',
        'cache/compiled/',
        'cache/validated-',
        'images/',
        'assets/',
    ];

    /**
     * @var array
     */
    protected $all_remove = [
        'cache/',
        'images/',
        'assets/'
    ];

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("clear-cache")
            ->setDescription("Clears Grav cache")
            ->addOption('all', null, InputOption::VALUE_NONE, 'If set will remove all')
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

        $user_config = USER_DIR . 'config/system.yaml';

        $anything = false;

        if ($input->getOption('all')) {
            $remove_paths = $this->all_remove;
        } else {
            $remove_paths = $this->standard_remove;
        }

        foreach ($remove_paths as $path) {

            $files = glob(ROOT_DIR . $path . '*');

            foreach ($files as $file) {
                if (is_file($file)) {
                    if (@unlink($file)) {
                        $anything = true;
                    }
                } elseif (is_dir($file)) {
                    if (@Folder::delete($file)) {
                        $anything = true;
                    }
                }
            }

            if ($anything) {
                $output->writeln('<red>Cleared:  </red>' . $path . '*');
            }
        }

        if (file_exists($user_config)) {
            touch($user_config);
            $output->writeln('');
            $output->writeln('<red>Touched: </red>' . $user_config);
            $output->writeln('');
        }

        if (!$anything) {
            $output->writeln('<green>Nothing to clear...</green>');
            $output->writeln('');
        }

    }
}

