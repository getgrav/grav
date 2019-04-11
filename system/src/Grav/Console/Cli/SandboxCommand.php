<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Console\ConsoleCommand;
use Grav\Common\Filesystem\Folder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class SandboxCommand extends ConsoleCommand
{
    /** @var array */
    protected $directories = [
        '/assets',
        '/backup',
        '/cache',
        '/images',
        '/logs',
        '/tmp',
        '/user/accounts',
        '/user/config',
        '/user/data',
        '/user/pages',
        '/user/plugins',
        '/user/themes',
    ];

    /** @var array */
    protected $files = [
        '/.dependencies',
        '/.htaccess',
        '/user/config/site.yaml',
        '/user/config/system.yaml',
    ];

    /** @var array */
    protected $mappings = [
        '/.gitignore'           => '/.gitignore',
        '/.editorconfig'        => '/.editorconfig',
        '/CHANGELOG.md'         => '/CHANGELOG.md',
        '/LICENSE.txt'          => '/LICENSE.txt',
        '/README.md'            => '/README.md',
        '/CONTRIBUTING.md'      => '/CONTRIBUTING.md',
        '/index.php'            => '/index.php',
        '/composer.json'        => '/composer.json',
        '/bin'                  => '/bin',
        '/system'               => '/system',
        '/vendor'               => '/vendor',
        '/webserver-configs'    => '/webserver-configs',
    ];

    /** @var string */
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

    protected function serve()
    {
        $this->destination = $this->input->getArgument('destination');

        // Symlink the Core Stuff
        if ($this->input->getOption('symlink')) {
            // Create Some core stuff if it doesn't exist
            $this->createDirectories();

            // Loop through the symlink mappings and create the symlinks
            $this->symlink();

            // Copy the Core STuff
        } else {
            // Create Some core stuff if it doesn't exist
            $this->createDirectories();

            // Loop through the symlink mappings and copy what otherwise would be symlinks
            $this->copy();
        }

        $this->pages();
        $this->initFiles();
        $this->perms();
    }

    private function createDirectories()
    {
        $this->output->writeln('');
        $this->output->writeln('<comment>Creating Directories</comment>');
        $dirs_created = false;

        if (!file_exists($this->destination)) {
            Folder::create($this->destination);
        }

        foreach ($this->directories as $dir) {
            if (!file_exists($this->destination . $dir)) {
                $dirs_created = true;
                $this->output->writeln('    <cyan>' . $dir . '</cyan>');
                Folder::create($this->destination . $dir);
            }
        }

        if (!$dirs_created) {
            $this->output->writeln('    <red>Directories already exist</red>');
        }
    }

    private function copy()
    {
        $this->output->writeln('');
        $this->output->writeln('<comment>Copying Files</comment>');


        foreach ($this->mappings as $source => $target) {
            if ((string)(int)$source === (string)$source) {
                $source = $target;
            }

            $from = $this->source . $source;
            $to = $this->destination . $target;

            $this->output->writeln('    <cyan>' . $source . '</cyan> <comment>-></comment> ' . $to);
            @Folder::rcopy($from, $to);
        }
    }

    private function symlink()
    {
        $this->output->writeln('');
        $this->output->writeln('<comment>Resetting Symbolic Links</comment>');


        foreach ($this->mappings as $source => $target) {
            if ((string)(int)$source === (string)$source) {
                $source = $target;
            }

            $from = $this->source . $source;
            $to = $this->destination . $target;

            $this->output->writeln('    <cyan>' . $source . '</cyan> <comment>-></comment> ' . $to);

            if (is_dir($to)) {
                @Folder::delete($to);
            } else {
                @unlink($to);
            }
            symlink($from, $to);
        }
    }

    private function initFiles()
    {
        $this->check();

        $this->output->writeln('');
        $this->output->writeln('<comment>File Initializing</comment>');
        $files_init = false;

        // Copy files if they do not exist
        foreach ($this->files as $source => $target) {
            if ((string)(int)$source === (string)$source) {
                $source = $target;
            }

            $from = $this->source . $source;
            $to = $this->destination . $target;

            if (!file_exists($to)) {
                $files_init = true;
                copy($from, $to);
                $this->output->writeln('    <cyan>' . $target . '</cyan> <comment>-></comment> Created');
            }
        }

        if (!$files_init) {
            $this->output->writeln('    <red>Files already exist</red>');
        }
    }

    private function pages()
    {
        $this->output->writeln('');
        $this->output->writeln('<comment>Pages Initializing</comment>');

        // get pages files and initialize if no pages exist
        $pages_dir = $this->destination . '/user/pages';
        $pages_files = array_diff(scandir($pages_dir), ['..', '.']);

        if (\count($pages_files) === 0) {
            $destination = $this->source . '/user/pages';
            Folder::rcopy($destination, $pages_dir);
            $this->output->writeln('    <cyan>' . $destination . '</cyan> <comment>-></comment> Created');

        }
    }

    private function perms()
    {
        $this->output->writeln('');
        $this->output->writeln('<comment>Permissions Initializing</comment>');

        $dir_perms = 0755;

        $binaries = glob($this->destination . DS . 'bin' . DS . '*');

        foreach ($binaries as $bin) {
            chmod($bin, $dir_perms);
            $this->output->writeln('    <cyan>bin/' . basename($bin) . '</cyan> permissions reset to ' . decoct($dir_perms));
        }

        $this->output->writeln("");
    }

    private function check()
    {
        $success = true;

        if (!file_exists($this->destination)) {
            $this->output->writeln('    file: <red>$this->destination</red> does not exist!');
            $success = false;
        }

        foreach ($this->directories as $dir) {
            if (!file_exists($this->destination . $dir)) {
                $this->output->writeln('    directory: <red>' . $dir . '</red> does not exist!');
                $success = false;
            }
        }

        foreach ($this->mappings as $target => $link) {
            if (!file_exists($this->destination . $target)) {
                $this->output->writeln('    mappings: <red>' . $target . '</red> does not exist!');
                $success = false;
            }
        }

        if (!$success) {
            $this->output->writeln('');
            $this->output->writeln('<comment>install should be run with --symlink|--s to symlink first</comment>');
            exit;
        }
    }
}
