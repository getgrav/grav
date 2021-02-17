<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Filesystem\Folder;
use Grav\Console\GravCommand;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use function count;

/**
 * Class SandboxCommand
 * @package Grav\Console\Cli
 */
class SandboxCommand extends GravCommand
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
    protected $source;
    /** @var string */
    protected $destination;

    /**
     * @return void
     */
    protected function configure(): void
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

        $source = getcwd();
        if ($source === false) {
            throw new RuntimeException('Internal Error');
        }
        $this->source = $source;
    }

    /**
     * @return int
     */
    protected function serve(): int
    {
        $input = $this->getInput();

        $this->destination = $input->getArgument('destination');

        // Create Some core stuff if it doesn't exist
        $error = $this->createDirectories();
        if ($error) {
            return $error;
        }

        // Copy files or create symlinks
        $error = $input->getOption('symlink') ? $this->symlink() : $this->copy();
        if ($error) {
            return $error;
        }

        $error = $this->pages();
        if ($error) {
            return $error;
        }

        $error = $this->initFiles();
        if ($error) {
            return $error;
        }

        $error = $this->perms();
        if ($error) {
            return $error;
        }

        return 0;
    }

    /**
     * @return int
     */
    private function createDirectories(): int
    {
        $io = $this->getIO();

        $io->newLine();
        $io->writeln('<comment>Creating Directories</comment>');
        $dirs_created = false;

        if (!file_exists($this->destination)) {
            Folder::create($this->destination);
        }

        foreach ($this->directories as $dir) {
            if (!file_exists($this->destination . $dir)) {
                $dirs_created = true;
                $io->writeln('    <cyan>' . $dir . '</cyan>');
                Folder::create($this->destination . $dir);
            }
        }

        if (!$dirs_created) {
            $io->writeln('    <red>Directories already exist</red>');
        }

        return 0;
    }

    /**
     * @return int
     */
    private function copy(): int
    {
        $io = $this->getIO();

        $io->newLine();
        $io->writeln('<comment>Copying Files</comment>');


        foreach ($this->mappings as $source => $target) {
            if ((string)(int)$source === (string)$source) {
                $source = $target;
            }

            $from = $this->source . $source;
            $to = $this->destination . $target;

            $io->writeln('    <cyan>' . $source . '</cyan> <comment>-></comment> ' . $to);
            @Folder::rcopy($from, $to);
        }

        return 0;
    }

    /**
     * @return int
     */
    private function symlink(): int
    {
        $io = $this->getIO();

        $io->newLine();
        $io->writeln('<comment>Resetting Symbolic Links</comment>');


        foreach ($this->mappings as $source => $target) {
            if ((string)(int)$source === (string)$source) {
                $source = $target;
            }

            $from = $this->source . $source;
            $to = $this->destination . $target;

            $io->writeln('    <cyan>' . $source . '</cyan> <comment>-></comment> ' . $to);

            if (is_dir($to)) {
                @Folder::delete($to);
            } else {
                @unlink($to);
            }
            symlink($from, $to);
        }

        return 0;
    }

    /**
     * @return int
     */
    private function pages(): int
    {
        $io = $this->getIO();

        $io->newLine();
        $io->writeln('<comment>Pages Initializing</comment>');

        // get pages files and initialize if no pages exist
        $pages_dir = $this->destination . '/user/pages';
        $pages_files = array_diff(scandir($pages_dir), ['..', '.']);

        if (count($pages_files) === 0) {
            $destination = $this->source . '/user/pages';
            Folder::rcopy($destination, $pages_dir);
            $io->writeln('    <cyan>' . $destination . '</cyan> <comment>-></comment> Created');
        }

        return 0;
    }

    /**
     * @return int
     */
    private function initFiles(): int
    {
        if (!$this->check()) {
            return 1;
        }

        $io = $this->getIO();
        $io->newLine();
        $io->writeln('<comment>File Initializing</comment>');
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
                $io->writeln('    <cyan>' . $target . '</cyan> <comment>-></comment> Created');
            }
        }

        if (!$files_init) {
            $io->writeln('    <red>Files already exist</red>');
        }

        return 0;
    }

    /**
     * @return int
     */
    private function perms(): int
    {
        $io = $this->getIO();
        $io->newLine();
        $io->writeln('<comment>Permissions Initializing</comment>');

        $dir_perms = 0755;

        $binaries = glob($this->destination . DS . 'bin' . DS . '*');

        foreach ($binaries as $bin) {
            chmod($bin, $dir_perms);
            $io->writeln('    <cyan>bin/' . basename($bin) . '</cyan> permissions reset to ' . decoct($dir_perms));
        }

        $io->newLine();

        return 0;
    }

    /**
     * @return bool
     */
    private function check(): bool
    {
        $success = true;
        $io = $this->getIO();

        if (!file_exists($this->destination)) {
            $io->writeln('    file: <red>' . $this->destination . '</red> does not exist!');
            $success = false;
        }

        foreach ($this->directories as $dir) {
            if (!file_exists($this->destination . $dir)) {
                $io->writeln('    directory: <red>' . $dir . '</red> does not exist!');
                $success = false;
            }
        }

        foreach ($this->mappings as $target => $link) {
            if (!file_exists($this->destination . $target)) {
                $io->writeln('    mappings: <red>' . $target . '</red> does not exist!');
                $success = false;
            }
        }

        if (!$success) {
            $io->newLine();
            $io->writeln('<comment>install should be run with --symlink|--s to symlink first</comment>');
        }

        return $success;
    }
}
