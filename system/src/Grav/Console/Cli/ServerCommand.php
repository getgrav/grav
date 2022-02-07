<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Utils;
use Grav\Console\GravCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Class ServerCommand
 * @package Grav\Console\Cli
 */
class ServerCommand extends GravCommand
{
    const SYMFONY_SERVER = 'Symfony Server';
    const PHP_SERVER = 'Built-in PHP Server';

    /** @var string */
    protected $ip;
    /** @var int */
    protected $port;

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('server')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Preferred HTTP port rather than auto-find (default is 8000-9000')
            ->addOption('symfony', null, InputOption::VALUE_NONE, 'Force using Symfony server')
            ->addOption('php', null, InputOption::VALUE_NONE, 'Force using built-in PHP server')
            ->setDescription("Runs built-in web-server, Symfony first, then tries PHP's")
            ->setHelp("Runs built-in web-server, Symfony first, then tries PHP's");
    }

    /**
     * @return int
     */
    protected function serve(): int
    {
        $input = $this->getInput();
        $io = $this->getIO();

        $io->title('Grav Web Server');

        // Ensure CLI colors are on
        ini_set('cli_server.color', 'on');

        // Options
        $force_symfony = $input->getOption('symfony');
        $force_php = $input->getOption('php');

        // Find PHP
        $executableFinder = new PhpExecutableFinder();
        $php = $executableFinder->find(false);

        $this->ip = '127.0.0.1';
        $this->port = (int)($input->getOption('port') ?? 8000);

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

        $error = 0;
        foreach ($commands as $name => $command) {
            $process = $this->runProcess($name, $command);
            if (!$process) {
                $io->note('Starting ' . $name . '...');
            }

            // Should only get here if there's an error running
            if (!$process->isRunning() && (($name === self::SYMFONY_SERVER && $force_symfony) || ($name === self::PHP_SERVER))) {
                $error = 1;
                $io->error('Could not start ' . $name);
            }
        }

        return $error;
    }

    /**
     * @param string $name
     * @param array $cmd
     * @return Process
     */
    protected function runProcess(string $name, array $cmd): Process
    {
        $io = $this->getIO();

        $process = new Process($cmd);
        $process->setTimeout(0);
        $process->start();

        if ($name === self::SYMFONY_SERVER && Utils::contains($process->getErrorOutput(), 'symfony: not found')) {
            $io->error('The symfony binary could not be found, please install the CLI tools: https://symfony.com/download');
            $io->warning('Falling back to PHP web server...');
        }

        if ($name === self::PHP_SERVER) {
            $io->success('Built-in PHP web server listening on http://' . $this->ip . ':' . $this->port . ' (PHP v' . PHP_VERSION . ')');
        }

        $process->wait(function ($type, $buffer) {
            $this->getIO()->write($buffer);
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
    protected function portAvailable(string $ip, int $port): bool
    {
        $fp = @fsockopen($ip, $port, $errno, $errstr, 0.1);
        if (!$fp) {
            return true;
        }

        fclose($fp);

        return false;
    }
}
