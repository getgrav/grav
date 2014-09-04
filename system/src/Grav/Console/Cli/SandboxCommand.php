<?php
namespace Grav\Console\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class SandboxCommand extends Command
{
    protected $directories  = array('/cache',
                                    '/logs',
                                    '/images',
                                    '/assets',
                                    '/user/accounts',
                                    '/user/config',
                                    '/user/pages',
                                    '/user/data',
                                    '/user/plugins',
                                    '/user/themes',
                                    );

    protected $files        = array('/.dependencies',
                                    '/.htaccess',
                                    '/user/config/site.yaml',
                                    '/user/config/system.yaml',
                                   );

    protected $mappings     = array('/index.php' => '/index.php',
                                    '/composer.json' => '/composer.json',
                                    '/bin' => '/bin',
                                    '/system' => '/system'
                                    );

    protected $default_file = "---\ntitle: HomePage\n---\n# HomePage\n\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque porttitor eu felis sed ornare. Sed a mauris venenatis, pulvinar velit vel, dictum enim. Phasellus ac rutrum velit. Nunc lorem purus, hendrerit sit amet augue aliquet, iaculis ultricies nisl. Suspendisse tincidunt euismod risus, quis feugiat arcu tincidunt eget. Nulla eros mi, commodo vel ipsum vel, aliquet congue odio. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Pellentesque velit orci, laoreet at adipiscing eu, interdum quis nibh. Nunc a accumsan purus.";

    protected $source;
    protected $destination;

    protected function configure()
    {
        $this
        ->setName('sandbox')
        ->setDescription('Setup of a base Grav system in your webroot, good for development, playing around or starting fresh')
        ->addArgument(
            'destination',
            InputArgument::REQUIRED,
            'The destination directory to symlink into'
        )
        ->addOption(
            'symlink',
            's',
            InputOption::VALUE_NONE,
            'Symlink the base grav system'
        )
        ->setHelp("The <info>sandbox</info> command help create a development environment that can optionally use symbolic links to link the core of grav to the git cloned repository.\nGood for development, playing around or starting fresh");
        $this->source = getcwd();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->destination = $input->getArgument('destination');

        // Create a red output option
        $output->getFormatter()->setStyle('red', new OutputFormatterStyle('red'));
        $output->getFormatter()->setStyle('cyan', new OutputFormatterStyle('cyan'));
        $output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta'));

        // Symlink the Core Stuff
        if ($input->getOption('symlink')) {
            // Create Some core stuff if it doesn't exist
            $this->createDirectories($output);

            // Loop through the symlink mappings and create the symlinks
            $this->symlink($output);

        // Copy the Core STuff
        } else {
            // Create Some core stuff if it doesn't exist
            $this->createDirectories($output);

            // Loop through the symlink mappings and copy what otherwise would be symlinks
            $this->copy($output);
        }

        $this->pages($output);
        $this->initFiles($output);
        $this->perms($output);
    }

    private function createDirectories($output)
    {
        $output->writeln('');
        $output->writeln('<comment>Creating Directories</comment>');
        $dirs_created = false;

        if (!file_exists($this->destination)) {
            mkdir($this->destination, 0777, true);
        }

        foreach ($this->directories as $dir) {
            if (!file_exists($this->destination . $dir)) {
                $dirs_created = true;
                $output->writeln('    <cyan>' . $dir . '</cyan>');
                mkdir($this->destination . $dir, 0777, true);
            }
        }

        if (!$dirs_created) {
            $output->writeln('    <red>Directories already exist</red>');
        }
    }

    private function copy($output)
    {
        $output->writeln('');
        $output->writeln('<comment>Copying Files</comment>');


        foreach ($this->mappings as $source => $target) {
            if ((int) $source == $source) {
                $source = $target;
            }

            $from = $this->source . $source;
            $to = $this->destination . $target;

            $output->writeln('    <cyan>' . $source . '</cyan> <comment>-></comment> ' . $to);
            $this->rcopy($from, $to);
        }
    }

    private function symlink($output)
    {
        $output->writeln('');
        $output->writeln('<comment>Resetting Symbolic Links</comment>');


        foreach ($this->mappings as $source => $target) {
            if ((int) $source == $source) {
                $source = $target;
            }

            $from = $this->source . $source;
            $to = $this->destination . $target;

            $output->writeln('    <cyan>' . $source . '</cyan> <comment>-></comment> ' . $to);

            if (is_dir($to)) {
                $this->rmdir($to);
            } else {
                @unlink($to);
            }
            symlink($from, $to);
        }
    }

    private function initFiles($output)
    {
        $this->check($output);

        $output->writeln('');
        $output->writeln('<comment>File Initializing</comment>');
        $files_init = false;

        // Copy files if they do not exist
         foreach ($this->files as $source => $target) {
            if ((int) $source == $source) {
                $source = $target;
            }

            $from = $this->source . $source;
            $to = $this->destination . $target;

            if (!file_exists($to)) {
                $files_init = true;
                copy($from, $to);
                $output->writeln('    <cyan>'.$target.'</cyan> <comment>-></comment> Created');
            }
        }

        if (!$files_init) {
            $output->writeln('    <red>Files already exist</red>');
        }


    }

    private function pages($output)
    {
        $output->writeln('');
        $output->writeln('<comment>Pages Initializing</comment>');

        // get pages files and initialize if no pages exist
        $pages_dir = $this->destination . '/user/pages';
        $pages_files = array_diff(scandir($pages_dir), array('..', '.'));

        if (count($pages_files) == 0) {
            $destination = $this->source . '/user/pages';
            $this->rcopy($destination, $pages_dir);
            $output->writeln('    <cyan>'.$destination.'</cyan> <comment>-></comment> Created');

        }
    }

    private function perms($output)
    {
        $output->writeln('');
        $output->writeln('<comment>Permisions Initializing</comment>');

        $dir_perms = 0755;

        // get pages files and initialize if no pages exist
        chmod($this->destination.'/bin/grav', $dir_perms);
        $output->writeln('    <cyan>bin/grav</cyan> permissions reset to '. decoct($dir_perms));
    }


    private function check($output)
    {
        $success = true;

        if (!file_exists($this->destination)) {
            $output->writeln('    file: <red>$this->destination</red> does not exist!');
            $success = false;
        }

        foreach ($this->directories as $dir) {
            if (!file_exists($this->destination . $dir)) {
                $output->writeln('    directory: <red>' . $dir . '</red> does not exist!');
                $success = false;
            }
        }

        foreach ($this->mappings as $target => $link) {
            if (!file_exists($this->destination . $target)) {
                $output->writeln('    mappings: <red>' . $target . '</red> does not exist!');
                $success = false;
            }
        }
        if (!$success) {
            $output->writeln('');
            $output->writeln('<comment>install should be run with --symlink|--s to symlink first</comment>');
            exit;
        }
    }

    private function rcopy($src, $dest){

        // If the src is not a directory do a simple file copy
        if(!is_dir($src)) {
            copy($src, $dest);
            return true;
        }

        // If the destination directory does not exist create it
        if(!is_dir($dest)) {
            if(!mkdir($dest)) {
        // If the destination directory could not be created stop processing
                return false;
            }
        }

        // Open the source directory to read in files
        $i = new \DirectoryIterator($src);
        foreach($i as $f) {
            if($f->isFile()) {
                copy($f->getRealPath(), "$dest/" . $f->getFilename());
            } else if(!$f->isDot() && $f->isDir()) {
                $this->rcopy($f->getRealPath(), "$dest/$f");
            }
        }
    }

    private function rmdir($dir) {
        $files = new \RecursiveIteratorIterator(
                       new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                       \RecursiveIteratorIterator::CHILD_FIRST
                    );

        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                if (false === rmdir($fileinfo->getRealPath())) return false;
            } else {
                if (false === unlink($fileinfo->getRealPath())) return false;
            }
        }

        return rmdir($dir);
    }
}
