<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Backup\Backups;
use Grav\Common\Grav;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class BackupCommand extends ConsoleCommand
{
    /** @var string $source */
    protected $source;

    /** @var ProgressBar $progress */
    protected $progress;

    protected function configure()
    {
        $this
            ->setName('backup')
            ->addArgument(
                'id',
                InputArgument::OPTIONAL,
                'The ID of the backup profile to perform without prompting'

            )
            ->setDescription('Creates a backup of the Grav instance')
            ->setHelp('The <info>backup</info> creates a zipped backup.');

        $this->source = getcwd();
    }

    protected function serve()
    {
        $this->progress = new ProgressBar($this->output);
        $this->progress->setFormat('Archiving <cyan>%current%</cyan> files [<green>%bar%</green>] <white>%percent:3s%%</white> %elapsed:6s% <yellow>%message%</yellow>');
        $this->progress->setBarWidth(100);

        Grav::instance()['config']->init();

        $io = new SymfonyStyle($this->input, $this->output);
        $io->title('Grav Backup');

        /** @var Backups $backups */
        $backups = Grav::instance()['backups'];
        $backups_list = $backups->getBackupProfiles();
        $backups_names = $backups->getBackupNames();

        $id = null;

        $inline_id = $this->input->getArgument('id');
        if (null !== $inline_id && is_numeric($inline_id)) {
            $id = $inline_id;
        }

        if (null === $id) {
            if (\count($backups_list) > 1) {
                $helper = $this->getHelper('question');
                $question = new ChoiceQuestion(
                    'Choose a backup?',
                    $backups_names,
                    0
                );
                $question->setErrorMessage('Option %s is invalid.');
                $backup_name = $helper->ask($this->input, $this->output, $question);
                $id = array_search($backup_name, $backups_names, true);

                $io->newLine();
                $io->note('Selected backup: ' . $backup_name);
            } else {
                $id = 0;
            }
        }

        $backup = $backups->backup($id, [$this, 'outputProgress']);

        $io->newline(2);
        $io->success('Backup Successfully Created: ' . $backup);
    }

    /**
     * @param array $args
     */
    public function outputProgress($args)
    {
        switch ($args['type']) {
            case 'count':
                $steps = $args['steps'];
                $freq = (int)($steps > 100 ? round($steps / 100) : $steps);
                $this->progress->setMaxSteps($steps);
                $this->progress->setRedrawFrequency($freq);
                $this->progress->setMessage('Adding files...');
                break;
            case 'message':
                $this->progress->setMessage($args['message']);
                $this->progress->display();
                break;
            case 'progress':
                if (isset($args['complete']) && $args['complete']) {
                    $this->progress->finish();
                } else {
                    $this->progress->advance();
                }
                break;
        }
    }

}

