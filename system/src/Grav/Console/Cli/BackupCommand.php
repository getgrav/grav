<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Backup\Backups;
use Grav\Common\Grav;
use Grav\Console\GravCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\ChoiceQuestion;
use ZipArchive;
use function count;

/**
 * Class BackupCommand
 * @package Grav\Console\Cli
 */
class BackupCommand extends GravCommand
{
    /** @var string $source */
    protected $source;
    /** @var ProgressBar $progress */
    protected $progress;

    /**
     * @return void
     */
    protected function configure(): void
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

    /**
     * @return int
     */
    protected function serve(): int
    {
        $this->initializeGrav();

        $input = $this->getInput();
        $io = $this->getIO();

        $io->title('Grav Backup');

        if (!class_exists(ZipArchive::class)) {
            $io->error('php-zip extension needs to be enabled!');
            return 1;
        }

        ProgressBar::setFormatDefinition('zip', 'Archiving <cyan>%current%</cyan> files [<green>%bar%</green>] <white>%percent:3s%%</white> %elapsed:6s% <yellow>%message%</yellow>');

        $this->progress = new ProgressBar($this->output, 100);
        $this->progress->setFormat('zip');


        /** @var Backups $backups */
        $backups = Grav::instance()['backups'];
        $backups_list = $backups::getBackupProfiles();
        $backups_names = $backups->getBackupNames();

        $id = null;

        $inline_id = $input->getArgument('id');
        if (null !== $inline_id && is_numeric($inline_id)) {
            $id = $inline_id;
        }

        if (null === $id) {
            if (count($backups_list) > 1) {
                $question = new ChoiceQuestion(
                    'Choose a backup?',
                    $backups_names,
                    0
                );
                $question->setErrorMessage('Option %s is invalid.');
                $backup_name = $io->askQuestion($question);
                $id = array_search($backup_name, $backups_names, true);

                $io->newLine();
                $io->note('Selected backup: ' . $backup_name);
            } else {
                $id = 0;
            }
        }

        $backup = $backups::backup($id, function($args) { $this->outputProgress($args); });

        $io->newline(2);
        $io->success('Backup Successfully Created: ' . $backup);

        return 0;
    }

    /**
     * @param array $args
     * @return void
     */
    public function outputProgress(array $args): void
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
