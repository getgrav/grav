<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Helpers\YamlLinter;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

class YamlLinterCommand extends ConsoleCommand
{
    protected function configure()
    {
        $this
            ->setName('yamllinter')
            ->addOption(
                'env',
                'e',
                InputOption::VALUE_OPTIONAL,
                'The environment to trigger a specific configuration. For example: localhost, mysite.dev, www.mysite.com'
            )
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
            ->setDescription('Checks various files for YAML errors')
            ->setHelp("Checks various files for YAML errors");
    }

    protected function serve()
    {
        $io = new SymfonyStyle($this->input, $this->output);

        $io->title('Yaml Linter');

        if ($this->input->getOption('all')) {
            $io->section('All');
            $errors = YamlLinter::lint('');

            if (empty($errors)) {
                $io->success('No YAML Linting issues found');
            } else {
                $this->displayErrors($errors, $io);
            }
        } elseif ($folder = $this->input->getOption('folder')) {
            $io->section($folder);
            $errors = YamlLinter::lint($folder);

            if (empty($errors)) {
                $io->success('No YAML Linting issues found');
            } else {
                $this->displayErrors($errors, $io);
            }
        } else {
            $io->section('User Configuration');
            $errors = YamlLinter::lintConfig();

            if (empty($errors)) {
                $io->success('No YAML Linting issues with configuration');
            } else {
                $this->displayErrors($errors, $io);
            }

            $io->section('Pages Frontmatter');
            $errors = YamlLinter::lintPages();

            if (empty($errors)) {
                $io->success('No YAML Linting issues with pages');
            } else {
                $this->displayErrors($errors, $io);
            }

            $io->section('Page Blueprints');
            $errors = YamlLinter::lintBlueprints();

            if (empty($errors)) {
                $io->success('No YAML Linting issues with blueprints');
            } else {
                $this->displayErrors($errors, $io);
            }
        }
    }

    protected function displayErrors($errors, SymfonyStyle $io)
    {
        $io->error('YAML Linting issues found...');
        foreach ($errors as $path => $error) {
            $io->writeln("<yellow>{$path}</yellow> - {$error}");
        }
    }
}
