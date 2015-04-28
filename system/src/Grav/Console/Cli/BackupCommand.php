<?php
namespace Grav\Console\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Grav\Common\Backup\ZipBackup;

/**
 * Class BackupCommand
 * @package Grav\Console\Cli
 */
class BackupCommand extends Command
{

    /**
     * @var
     */
    protected $source;
    /**
     * @var
     */
    protected $progress;
    /**
     * @var
     */
    protected $output;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("backup")
            ->addArgument(
                'destination',
                InputArgument::OPTIONAL,
                'Where to store the backup'

            )
            ->setDescription("Creates a backup of the Grav instance")
            ->setHelp('The <info>backup</info> creates a zipped backup. Optionally can be saved in a different destination.');


        $this->source = getcwd();
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $output->getFormatter()->setStyle('red', new OutputFormatterStyle('red'));
        $output->getFormatter()->setStyle('cyan', new OutputFormatterStyle('cyan'));
        $output->getFormatter()->setStyle('green', new OutputFormatterStyle('green'));
        $output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta'));

        $this->progress = new ProgressBar($output);
        $this->progress->setFormat('Archiving <cyan>%current%</cyan> files [<green>%bar%</green>] %elapsed:6s% %memory:6s%');

        $destination = ($input->getArgument('destination')) ? $input->getArgument('destination') : ROOT_DIR;

        ZipBackup::backup($destination, [$this, 'output']);

        $output->writeln('');
        $output->writeln('');

    }

    /**
     * @param $folder
     * @param $zipFile
     * @param $exclusiveLength
     * @param $progress
     */
    public function output($args)
    {
        switch ($args['type']) {
            case 'message':
                $this->output->writeln($args['message']);
                break;
            case 'progress':
                if ($args['complete']) {
                    $this->progress->finish();
                } else {
                    $this->progress->advance();
                }
                break;
        }
    }
}

