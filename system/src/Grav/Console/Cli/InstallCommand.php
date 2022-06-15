<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Console\GravCommand;
use Grav\Framework\File\Formatter\JsonFormatter;
use Grav\Framework\File\JsonFile;
use RocketTheme\Toolbox\File\YamlFile;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use function is_array;

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
            ->addOption(
                'plugin',
                'p',
                InputOption::VALUE_REQUIRED,
                'Install plugin (symlink)'
            )
            ->addOption(
                'theme',
                't',
                InputOption::VALUE_REQUIRED,
                'Install theme (symlink)'
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
        $input = $this->getInput();
        $io = $this->getIO();

        $dependencies_file = '.dependencies';
        $this->destination = $input->getArgument('destination') ?: GRAV_WEBROOT;

        // fix trailing slash
        $this->destination = rtrim($this->destination, DS) . DS;
        $this->user_path = $this->destination . GRAV_USER_PATH . DS;
        if ($local_config_file = $this->loadLocalConfig()) {
            $io->writeln('Read local config from <cyan>' . $local_config_file . '</cyan>');
        }

        // Look for dependencies file in ROOT and USER dir
        if (file_exists($this->user_path . $dependencies_file)) {
            $file = YamlFile::instance($this->user_path . $dependencies_file);
        } elseif (file_exists($this->destination . $dependencies_file)) {
            $file = YamlFile::instance($this->destination . $dependencies_file);
        } else {
            $io->writeln('<red>ERROR</red> Missing .dependencies file in <cyan>user/</cyan> folder');
            if ($input->getArgument('destination')) {
                $io->writeln('<yellow>HINT</yellow> <info>Are you trying to install a plugin or a theme? Make sure you use <cyan>bin/gpm install <something></cyan>, not <cyan>bin/grav install</cyan>. This command is only used to install Grav skeletons.');
            } else {
                $io->writeln('<yellow>HINT</yellow> <info>Are you trying to install Grav? Grav is already installed. You need to run this command only if you download a skeleton from GitHub directly.');
            }

            return 1;
        }

        $this->config = $file->content();
        $file->free();

        // If no config, fail.
        if (!$this->config) {
            $io->writeln('<red>ERROR</red> invalid YAML in ' . $dependencies_file);

            return 1;
        }

        $plugin = $input->getOption('plugin');
        $theme = $input->getOption('theme');
        $name = $plugin ?? $theme;
        $symlink = $name || $input->getOption('symlink');

        if (!$symlink) {
            // Updates composer first
            $io->writeln("\nInstalling vendor dependencies");
            $io->writeln($this->composerUpdate(GRAV_ROOT, 'install'));

            $error = $this->gitclone();
        } else {
            $type = $name ? ($plugin ? 'plugin' : 'theme') : null;

            $error = $this->symlink($name, $type);
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
        $io = $this->getIO();

        $io->newLine();
        $io->writeln('<green>Cloning Bits</green>');
        $io->writeln('============');
        $io->newLine();

        $error = 0;
        $this->destination = rtrim($this->destination, DS);
        foreach ($this->config['git'] as $repo => $data) {
            $path = $this->destination . DS . $data['path'];
            if (!file_exists($path)) {
                exec('cd ' . escapeshellarg($this->destination) . ' && git clone -b ' . $data['branch'] . ' --depth 1 ' . $data['url'] . ' ' . $data['path'], $output, $return);

                if (!$return) {
                    $io->writeln('<green>SUCCESS</green> cloned <magenta>' . $data['url'] . '</magenta> -> <cyan>' . $path . '</cyan>');
                } else {
                    $io->writeln('<red>ERROR</red> cloning <magenta>' . $data['url']);
                    $error = 1;
                }

                $io->newLine();
            } else {
                $io->writeln('<yellow>' . $path . ' already exists, skipping...</yellow>');
                $io->newLine();
            }
        }

        return $error;
    }

    /**
     * Symlinks
     *
     * @param string|null $name
     * @param string|null $type
     * @return int
     */
    private function symlink(string $name = null, string $type = null): int
    {
        $io = $this->getIO();

        $io->newLine();
        $io->writeln('<green>Symlinking Bits</green>');
        $io->writeln('===============');
        $io->newLine();

        if (!$this->local_config) {
            $io->writeln('<red>No local configuration available, aborting...</red>');
            $io->newLine();

            return 1;
        }

        $error = 0;
        $this->destination = rtrim($this->destination, DS);

        if ($name) {
            $src = "grav-{$type}-{$name}";
            $links = [
                $name => [
                    'scm' => 'github', // TODO: make configurable
                    'src' => $src,
                    'path' => "user/{$type}s/{$name}"
                ]
            ];
        } else {
            $links = $this->config['links'];
        }

        foreach ($links as $name => $data) {
            $scm = $data['scm'] ?? null;
            $src = $data['src'] ?? null;
            $path = $data['path'] ?? null;
            if (!isset($scm, $src, $path)) {
                $io->writeln("<red>Dependency '$name' has broken configuration, skipping...</red>");
                $io->newLine();
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

            if (is_link($to) && !realpath($to)) {
                $io->writeln('<yellow>Removed broken symlink '. $path .'</yellow>');
                unlink($to);
            }
            if (null === $from) {
                $io->writeln('<red>source for ' . $src . ' does not exists, skipping...</red>');
                $io->newLine();
                $error = 1;
            } elseif (!file_exists($to)) {
                $error = $this->addSymlinks($from, $to, ['name' => $name, 'src' => $src, 'path' => $path]);
                $io->newLine();
            } else {
                $io->writeln('<yellow>destination: ' . $path . ' already exists, skipping...</yellow>');
                $io->newLine();
            }
        }

        return $error;
    }

    private function addSymlinks(string $from, string $to, array $options): int
    {
        $io = $this->getIO();

        $hebe = $this->readHebe($from);
        if (null === $hebe) {
            symlink($from, $to);

            $io->writeln('<green>SUCCESS</green> symlinked <magenta>' . $options['src'] . '</magenta> -> <cyan>' . $options['path'] . '</cyan>');
        } else {
            $to = GRAV_ROOT;
            $name = $options['name'];
            $io->writeln("Processing <magenta>{$name}</magenta>");
            foreach ($hebe as $section => $symlinks) {
                foreach ($symlinks as $symlink) {
                    $src = trim($symlink['source'], '/');
                    $dst = trim($symlink['destination'], '/');
                    $s = "{$from}/{$src}";
                    $d = "{$to}/{$dst}";

                    if (is_link($d) && !realpath($d)) {
                        unlink($d);
                        $io->writeln('    <yellow>Removed broken symlink '. $dst .'</yellow>');
                    }
                    if (!file_exists($d)) {
                        symlink($s, $d);
                        $io->writeln('    symlinked <magenta>' . $src . '</magenta> -> <cyan>' . $dst . '</cyan>');
                    }
                }
            }
            $io->writeln('<green>SUCCESS</green>');
        }

        return 0;
    }

    private function readHebe(string $folder): ?array
    {
        $filename = "{$folder}/hebe.json";
        if (!is_file($filename)) {
            return null;
        }

        $formatter = new JsonFormatter();
        $file = new JsonFile($filename, $formatter);
        $hebe = $file->load();
        $paths = $hebe['platforms']['grav']['nodes'] ?? null;

        return is_array($paths) ? $paths : null;
    }
}
