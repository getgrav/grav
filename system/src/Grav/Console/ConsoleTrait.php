<?php

/**
 * @package    Grav\Console
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console;

use Grav\Common\Cache;
use Grav\Common\Grav;
use Grav\Common\Composer;
use Grav\Common\GravTrait;
use Grav\Console\Cli\ClearCacheCommand;
use RocketTheme\Toolbox\File\YamlFile;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait ConsoleTrait
{
    protected $argv;

    /* @var InputInterface $output */
    protected $input;

    /* @var OutputInterface $output */
    protected $output;

    /** @var array */
    protected $local_config;

    /**
     * Set colors style definition for the formatter.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    public function setupConsole(InputInterface $input, OutputInterface $output)
    {
        // Initialize cache with CLI compatibility
        Grav::instance()['config']->set('system.cache.cli_compatibility', true);
        Grav::instance()['cache'];

        $this->argv = $_SERVER['argv'][0];
        $this->input  = $input;
        $this->output = $output;

        $this->output->getFormatter()->setStyle('normal', new OutputFormatterStyle('white'));
        $this->output->getFormatter()->setStyle('yellow', new OutputFormatterStyle('yellow', null, array('bold')));
        $this->output->getFormatter()->setStyle('red', new OutputFormatterStyle('red', null, array('bold')));
        $this->output->getFormatter()->setStyle('cyan', new OutputFormatterStyle('cyan', null, array('bold')));
        $this->output->getFormatter()->setStyle('green', new OutputFormatterStyle('green', null, array('bold')));
        $this->output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta', null, array('bold')));
        $this->output->getFormatter()->setStyle('white', new OutputFormatterStyle('white', null, array('bold')));
    }

    /**
     * @param string $path
     */
    public function isGravInstance($path)
    {
        if (!file_exists($path)) {
            $this->output->writeln('');
            $this->output->writeln("<red>ERROR</red>: Destination doesn't exist:");
            $this->output->writeln("       <white>$path</white>");
            $this->output->writeln('');
            exit;
        }

        if (!is_dir($path)) {
            $this->output->writeln('');
            $this->output->writeln("<red>ERROR</red>: Destination chosen to install is not a directory:");
            $this->output->writeln("       <white>$path</white>");
            $this->output->writeln('');
            exit;
        }

        if (!file_exists($path . DS . 'index.php') || !file_exists($path . DS . '.dependencies') || !file_exists($path . DS . 'system' . DS . 'config' . DS . 'system.yaml')) {
            $this->output->writeln('');
            $this->output->writeln('<red>ERROR</red>: Destination chosen to install does not appear to be a Grav instance:');
            $this->output->writeln("       <white>$path</white>");
            $this->output->writeln('');
            exit;
        }
    }

    public function composerUpdate($path, $action = 'install')
    {
        $composer = Composer::getComposerExecutor();

        return system($composer . ' --working-dir="'.$path.'" --no-interaction --no-dev --prefer-dist -o '. $action);
    }

    /**
     * @param array $all
     *
     * @return int
     * @throws \Exception
     */
    public function clearCache($all = [])
    {
        if ($all) {
            $all = ['--all' => true];
        }

        $command = new ClearCacheCommand();
        $input = new ArrayInput($all);
        return $command->run($input, $this->output);
    }

    public function invalidateCache()
    {
        Cache::invalidateCache();
    }

    /**
     * Load the local config file
     *
     * @return mixed string the local config file name. false if local config does not exist
     */
    public function loadLocalConfig()
    {
        $home_folder = getenv('HOME') ?: getenv('HOMEDRIVE') . getenv('HOMEPATH');
        $local_config_file = $home_folder . '/.grav/config';

        if (file_exists($local_config_file)) {
            $file = YamlFile::instance($local_config_file);
            $this->local_config = $file->content();
            $file->free();
            return $local_config_file;
        }

        return false;
    }
}
