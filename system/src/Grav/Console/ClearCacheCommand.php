<?php
namespace Grav\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use \Symfony\Component\Yaml\Yaml;

class ClearCacheCommand extends Command {

    protected $paths_to_remove = [
        'cache/'
    ];

    protected function configure() {
        $this
        ->setName("clear-cache")
        ->setDescription("Clears Grav cache")
        ->setHelp('The <info>clear-cache</info> deletes all cache files');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {


        // Create a red output option
        $output->getFormatter()->setStyle('red', new OutputFormatterStyle('red'));
        $output->getFormatter()->setStyle('cyan', new OutputFormatterStyle('cyan'));
        $output->getFormatter()->setStyle('green', new OutputFormatterStyle('green'));
        $output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta'));

        $this->cleanPaths($output);


    }

    // loops over the array of paths and deletes the files/folders
    private function cleanPaths($output)
    {
        $output->writeln('');
        $output->writeln('<magenta>Clearing cache</magenta>');
        $output->writeln('');

        $anything = false;

        foreach($this->paths_to_remove as $path) {
            $files = glob(ROOT_DIR . rtrim($path, '/') . '/*');

            foreach ($files as $file) {
                if     (is_file($file) && @unlink($file))       $anything = true;
                elseif (is_dir($file) && @$this->rrmdir($file)) $anything = true;
            }

            if ($anything) $output->writeln('<red>Cleared:  </red>' . $path . '*');
        }

        if (!$anything) {
            $output->writeln('<green>Nothing to clear...</green>');
            $output->writeln('');
        }

    }

    // Recursively Delete folder - DANGEROUS! USE WITH CARE!!!!
    private function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir."/".$object) == "dir") $this->rrmdir($dir."/".$object); else unlink($dir."/".$object);
                }
            }
            reset($objects);
            rmdir($dir);
            return true;
        }
    }
}

