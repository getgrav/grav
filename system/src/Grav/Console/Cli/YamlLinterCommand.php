<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Helpers\YamlLinter;
use Grav\Console\GravCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class YamlLinterCommand
 * @package Grav\Console\Cli
 */
class YamlLinterCommand extends GravCommand
{
    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('yamllinter')
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Go through the whole Grav installation'
            )
            ->addOption(
                'folder',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Go through specific folder'
            )
            ->addOption(
                'strict',
                's',
                InputOption::VALUE_NONE,
                'Use the stricter Compat YAML parser that matches runtime behavior'
            )
            ->setDescription('Checks various files for YAML errors')
            ->setHelp('Checks various files for YAML errors');
    }

    /**
     * @return int
     */
    protected function serve(): int
    {
        $input = $this->getInput();
        $io = $this->getIO();

        $io->title('Yaml Linter');

        $verbose = $io->isVerbose();
        $strict = (bool) $input->getOption('strict');
        $checked = 0;
        $callback = $verbose ? function (string $file, bool $success, ?string $error) use ($io, &$checked) {
            $checked++;
            if ($success) {
                $io->writeln("<green>[OK]</green> {$file}");
            } else {
                $io->writeln("<red>[ERROR]</red> {$file}");
            }
        } : null;

        if ($strict) {
            $io->note('Using strict Compat YAML parser (matches runtime behavior)');
        }

        $error = 0;
        if ($input->getOption('all')) {
            $io->section('All');
            $errors = YamlLinter::lint('', $callback, $strict);

            $this->displayResult($errors, $io, 'No YAML Linting issues found', $verbose, $checked);
            if (!empty($errors)) {
                $error = 1;
            }
        } elseif ($folder = $input->getOption('folder')) {
            $io->section($folder);
            $errors = YamlLinter::lint($folder, $callback, $strict);

            $this->displayResult($errors, $io, 'No YAML Linting issues found', $verbose, $checked);
            if (!empty($errors)) {
                $error = 1;
            }
        } else {
            $io->section('User Configuration');
            $checked = 0;
            $errors = YamlLinter::lintConfig($callback, $strict);

            $this->displayResult($errors, $io, 'No YAML Linting issues with configuration', $verbose, $checked);
            if (!empty($errors)) {
                $error = 1;
            }

            $io->section('Pages Frontmatter');
            $checked = 0;
            $errors = YamlLinter::lintPages($callback, $strict);

            $this->displayResult($errors, $io, 'No YAML Linting issues with pages', $verbose, $checked);
            if (!empty($errors)) {
                $error = 1;
            }

            $io->section('Page Blueprints');
            $checked = 0;
            $errors = YamlLinter::lintBlueprints($callback, $strict);

            $this->displayResult($errors, $io, 'No YAML Linting issues with blueprints', $verbose, $checked);
            if (!empty($errors)) {
                $error = 1;
            }

            $io->section('Environment Configuration');
            $checked = 0;
            $errors = YamlLinter::lintEnvironments($callback, $strict);

            $this->displayResult($errors, $io, 'No YAML Linting issues with environment configs', $verbose, $checked);
            if (!empty($errors)) {
                $error = 1;
            }
        }

        return $error;
    }

    /**
     * @param array $errors
     * @param SymfonyStyle $io
     * @param string $successMessage
     * @param bool $verbose
     * @param int $checked
     * @return void
     */
    protected function displayResult(array $errors, SymfonyStyle $io, string $successMessage, bool $verbose, int $checked): void
    {
        if ($verbose) {
            $io->newLine();
            $io->writeln("Files checked: <info>{$checked}</info>");
        }

        if (empty($errors)) {
            $io->success($successMessage);
        } else {
            $this->displayErrors($errors, $io);
        }
    }

    /**
     * @param array $errors
     * @param SymfonyStyle $io
     * @return void
     */
    protected function displayErrors(array $errors, SymfonyStyle $io): void
    {
        $io->error('YAML Linting issues found...');
        foreach ($errors as $path => $error) {
            $io->writeln("<yellow>{$path}</yellow> - {$error}");
        }
    }
}
