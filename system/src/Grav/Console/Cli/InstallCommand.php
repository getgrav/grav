<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Console\GravCommand;
use RocketTheme\Toolbox\File\YamlFile;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class InstallCommand
 * @package Grav\Console\Cli
 */
class InstallCommand extends GravCommand
{
    /** @var array */
    protected $config;

    /** @var string */
    protected $destination;

    /** @var string */
    protected $user_path;

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('install')
            ->addOption(
                'symlink',
                's',
                InputOption::VALUE_NONE,
                'Symlink the required bits'
            )
            ->addArgument(
                'destination',
                InputArgument::OPTIONAL,
                'Where to install the required bits (default to current project)'
            )
            ->setDescription('Installs the dependencies needed by Grav. Optionally can create symbolic links')
            ->setHelp('The <info>install</info> command installs the dependencies needed by Grav. Optionally can create symbolic links');
    }

    /**
     * @return int
     */
    protected function serve(): int
    {
        $dependencies_file = '.dependencies';
        $this->destination = $this->input->getArgument('destination') ?: ROOT_DIR;

        // fix trailing slash
        $this->destination = rtrim($this->destination, DS) . DS;
        $this->user_path = $this->destination . USER_PATH;
        if ($local_config_file = $this->loadLocalConfig()) {
            $this->output->writeln('Read local config from <cyan>' . $local_config_file . '</cyan>');
        }

        // Look for dependencies file in ROOT and USER dir
        if (file_exists($this->user_path . $dependencies_file)) {
            $file = YamlFile::instance($this->user_path . $dependencies_file);
        } elseif (file_exists($this->destination . $dependencies_file)) {
            $file = YamlFile::instance($this->destination . $dependencies_file);
        } else {
            $this->output->writeln('<red>ERROR</red> Missing .dependencies file in <cyan>user/</cyan> folder');
            if ($this->input->getArgument('destination')) {
                $this->output->writeln('<yellow>HINT</yellow> <info>Are you trying to install a plugin or a theme? Make sure you use <cyan>bin/gpm install <something></cyan>, not <cyan>bin/grav install</cyan>. This command is only used to install Grav skeletons.');
            } else {
                $this->output->writeln('<yellow>HINT</yellow> <info>Are you trying to install Grav? Grav is already installed. You need to run this command only if you download a skeleton from GitHub directly.');
            }

            return 1;
        }

        $this->config = $file->content();
        $file->free();

        // If no config, fail.
        if (!$this->config) {
            $this->output->writeln('<red>ERROR</red> invalid YAML in ' . $dependencies_file);

            return 1;
        }

        $error = 0;
        if (!$this->input->getOption('symlink')) {
            // Updates composer first
            $this->output->writeln("\nInstalling vendor dependencies");
            $this->output->writeln($this->composerUpdate(GRAV_ROOT, 'install'));

            $error = $this->gitclone();
        } else {
            $error = $this->symlink();
        }

        return $error;
    }

    /**
     * Clones from Git
     *
     * @return int
     */
    private function gitclone(): int
    {
        $this->output->writeln('');
        $this->output->writeln('<green>Cloning Bits</green>');
        $this->output->writeln('============');
        $this->output->writeln('');

        $error = 0;
        $this->destination = rtrim($this->destination, DS);
        foreach ($this->config['git'] as $repo => $data) {
            $path = $this->destination . DS . $data['path'];
            if (!file_exists($path)) {
                exec('cd "' . $this->destination . '" && git clone -b ' . $data['branch'] . ' --depth 1 ' . $data['url'] . ' ' . $data['path'], $output, $return);

                if (!$return) {
                    $this->output->writeln('<green>SUCCESS</green> cloned <magenta>' . $data['url'] . '</magenta> -> <cyan>' . $path . '</cyan>');
                } else {
                    $this->output->writeln('<red>ERROR</red> cloning <magenta>' . $data['url']);
                    $error = 1;
                }

                $this->output->writeln('');
            } else {
                $this->output->writeln('<yellow>' . $path . ' already exists, skipping...</yellow>');
                $this->output->writeln('');
            }
        }

        return $error;
    }

    /**
     * Symlinks
     *
     * @return int
     */
    private function symlink(): int
    {
        $this->output->writeln('');
        $this->output->writeln('<green>Symlinking Bits</green>');
        $this->output->writeln('===============');
        $this->output->writeln('');

        if (!$this->local_config) {
            $this->output->writeln('<red>No local configuration available, aborting...</red>');
            $this->output->writeln('');

            return 1;
        }

        $error = 0;
        $this->destination = rtrim($this->destination, DS);
        foreach ($this->config['links'] as $name => $data) {
            $scm = $data['scm'] ?? null;
            $src = $data['src'] ?? null;
            $path = $data['path'] ?? null;
            if (!isset($scm, $src, $path)) {
                $this->output->writeln("<red>Dependency '$name' has broken configuration, skipping...</red>");
                $this->output->writeln('');
                $error = 1;

                continue;
            }

            $locations = (array) $this->local_config["{$scm}_repos"];
            $to = $this->destination . DS . $path;

            $from = null;
            foreach ($locations as $location) {
                $test = rtrim($location, '\\/') . DS . $src;
                if (file_exists($test)) {
                    $from = $test;
                    continue;
                }
            }

            if (is_link($to) && !is_file("{$to}/{$name}.yaml")) {
                $this->output->writeln('<yellow>Removed broken symlink '. $path .'</yellow>');
                unlink($to);
            }
            if (null === $from) {
                $this->output->writeln('<red>source for ' . $src . ' does not exists, skipping...</red>');
                $this->output->writeln('');
                $error = 1;
            } elseif (!file_exists($to)) {
                symlink($from, $to);
                $this->output->writeln('<green>SUCCESS</green> symlinked <magenta>' . $src . '</magenta> -> <cyan>' . $path . '</cyan>');
                $this->output->writeln('');
            } else {
                $this->output->writeln('<yellow>destination: ' . $path . ' already exists, skipping...</yellow>');
                $this->output->writeln('');
            }
        }

        return $error;
    }
}
