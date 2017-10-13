<?php
/**
 * @package    Grav.Console
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

class InstallCommand extends ConsoleCommand
{
    /**
     * @var
     */
    protected $config;
    /**
     * @var
     */
    protected $local_config;
    /**
     * @var
     */
    protected $destination;
    /**
     * @var
     */
    protected $user_path;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("install")
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
            ->setDescription("Installs the dependencies needed by Grav. Optionally can create symbolic links")
            ->setHelp('The <info>install</info> command installs the dependencies needed by Grav. Optionally can create symbolic links');
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        $dependencies_file = '.dependencies';
        $this->destination = ($this->input->getArgument('destination')) ? $this->input->getArgument('destination') : ROOT_DIR;

        // fix trailing slash
        $this->destination = rtrim($this->destination, DS) . DS;
        $this->user_path = $this->destination . USER_PATH;
        if ($local_config_file = $this->loadLocalConfig()) {
            $this->output->writeln('Read local config from <cyan>' . $local_config_file . '</cyan>');
        }

        // Look for dependencies file in ROOT and USER dir
        if (file_exists($this->user_path . $dependencies_file)) {
            $this->config = Yaml::parse(file_get_contents($this->user_path . $dependencies_file));
        } elseif (file_exists($this->destination . $dependencies_file)) {
            $this->config = Yaml::parse(file_get_contents($this->destination . $dependencies_file));
        } else {
            $this->output->writeln('<red>ERROR</red> Missing .dependencies file in <cyan>user/</cyan> folder');
            if ($this->input->getArgument('destination')) {
                $this->output->writeln('<yellow>HINT</yellow> <info>Are you trying to install a plugin or a theme? Make sure you use <cyan>bin/gpm install <something></cyan>, not <cyan>bin/grav install</cyan>. This command is only used to install Grav skeletons.');    
            } else {
                $this->output->writeln('<yellow>HINT</yellow> <info>Are you trying to install Grav? Grav is already installed. You need to run this command only if you download a skeleton from GitHub directly.');    
            }
            
            return;
        }

        // If yaml config, process
        if ($this->config) {
            if (!$this->input->getOption('symlink')) {
                // Updates composer first
                $this->output->writeln("\nInstalling vendor dependencies");
                $this->output->writeln($this->composerUpdate(GRAV_ROOT, 'install'));

                $this->gitclone();
            } else {
                $this->symlink();
            }
        } else {
            $this->output->writeln('<red>ERROR</red> invalid YAML in ' . $dependencies_file);
        }


    }

    /**
     * Clones from Git
     */
    private function gitclone()
    {
        $this->output->writeln('');
        $this->output->writeln('<green>Cloning Bits</green>');
        $this->output->writeln('============');
        $this->output->writeln('');

        foreach ($this->config['git'] as $repo => $data) {
            $this->destination = rtrim($this->destination, DS);
            $path = $this->destination . DS . $data['path'];
            if (!file_exists($path)) {
                exec('cd "' . $this->destination . '" && git clone -b ' . $data['branch'] . ' --depth 1 ' . $data['url'] . ' ' . $data['path'], $output, $return);

                if (!$return) {
                    $this->output->writeln('<green>SUCCESS</green> cloned <magenta>' . $data['url'] . '</magenta> -> <cyan>' . $path . '</cyan>');
                } else {
                    $this->output->writeln('<red>ERROR</red> cloning <magenta>' . $data['url']);

                }

                $this->output->writeln('');
            } else {
                $this->output->writeln('<red>' . $path . ' already exists, skipping...</red>');
                $this->output->writeln('');
            }

        }
    }

    /**
     * Symlinks
     */
    private function symlink()
    {
        $this->output->writeln('');
        $this->output->writeln('<green>Symlinking Bits</green>');
        $this->output->writeln('===============');
        $this->output->writeln('');

        if (!$this->local_config) {
            $this->output->writeln('<red>No local configuration available, aborting...</red>');
            $this->output->writeln('');
            return;
        }

        exec('cd ' . $this->destination);
        foreach ($this->config['links'] as $repo => $data) {
            $from = $this->local_config[$data['scm'] . '_repos'] . $data['src'];
            $to = $this->destination . $data['path'];

            if (file_exists($from)) {
                if (!file_exists($to)) {
                    symlink($from, $to);
                    $this->output->writeln('<green>SUCCESS</green> symlinked <magenta>' . $data['src'] . '</magenta> -> <cyan>' . $data['path'] . '</cyan>');
                    $this->output->writeln('');
                } else {
                    $this->output->writeln('<red>destination: ' . $to . ' already exists, skipping...</red>');
                    $this->output->writeln('');
                }
            } else {
                $this->output->writeln('<red>source: ' . $from . ' does not exists, skipping...</red>');
                $this->output->writeln('');
            }

        }
    }
}
