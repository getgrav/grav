<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Utils;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ServerCommand extends ConsoleCommand
{
    const SYMFONY_SERVER = 'Symfony Server';
    const PHP_SERVER = 'Built-in PHP Server';

    /** @var string */
    protected $ip;
    /** @var int */
    protected $port;
    /** @var SymfonyStyle */
    protected $io;

    protected function configure()
    {
        $this
            ->setName('server')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Preferred HTTP port rather than auto-find (default is 8000-9000')
            ->addOption('symfony', null, InputOption::VALUE_NONE, 'Force using Symfony server')
            ->addOption('php', null, InputOption::VALUE_NONE, 'Force using built-in PHP server')
            ->setDescription("Runs built-in web-server, Symfony first, then tries PHP's")
            ->setHelp("Runs built-in web-server, Symfony first, then tries PHP's");
    }

    protected function serve()
    {
        $io = $this->io = new SymfonyStyle($this->input, $this->output);

        $io->title('Grav Web Server');

        // Ensure CLI colors are on
        ini_set('cli_server.color', 'on');

        // Options
        $force_symfony = $this->input->getOption('symfony');
        $force_php = $this->input->getOption('php');

        // Find PHP
        $executableFinder = new PhpExecutableFinder();
        $php = $executableFinder->find(false);

        $this->ip = '127.0.0.1';
        $this->port = (int)($this->input->getOption('port') ?? 8000);


        // Get an open port
        while (!$this->portAvailable($this->ip, $this->port)) {
            $this->port++;
        }

        // Setup the commands
        $symfony_cmd = ['symfony', 'server:start', '--ansi', '--port=' . $this->port];
        $php_cmd = [$php, '-S', $this->ip.':'.$this->port, 'system/router.php'];

        $commands = [
            self::SYMFONY_SERVER => $symfony_cmd,
            self::PHP_SERVER => $php_cmd
        ];

        if ($force_symfony) {
            unset($commands[self::PHP_SERVER]);
        } elseif ($force_php) {
            unset($commands[self::SYMFONY_SERVER]);
        }

        foreach ($commands as $name => $command) {
            $process = $this->runProcess($name, $command);

            if (!$process) {
                $io->note('Starting ' . $name . '...');
            }

            // Should only get here if there's an error running
            if (!$process->isRunning() && (
                ($name === self::SYMFONY_SERVER && $force_symfony) ||
                ($name === self::PHP_SERVER)
                )) {
                $io->error('Could not start ' . $name);
            }
        }
    }

    protected function runProcess($name, $cmd)
    {
        $process = new Process($cmd);
        $process->setTimeout(0);
        $process->start();

        if ($name == self::SYMFONY_SERVER && Utils::contains($process->getErrorOutput(), 'symfony: not found')) {
            $this->io->error('The symfony binary could not be found, please install the CLI tools: https://symfony.com/download');
            $this->io->warning('Falling back to PHP web server...');
        }

        if ($name === self::PHP_SERVER) {
            $this->io->success('Built-in PHP web server listening on http://' . $this->ip . ':' . $this->port . ' (PHP v' . PHP_VERSION . ')');
        }

        $process->wait(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return $process;
    }

    /**
     * Simple function test the port
     *
     * @param string $ip
     * @param int $port
     * @return bool
     */
    protected function portAvailable($ip, $port)
    {
        $fp = @fsockopen($ip, $port, $errno, $errstr, 0.1);
        if (!$fp) {
            return true;
        }

        fclose($fp);
        return false;
    }
}
