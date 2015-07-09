<?php
namespace Grav\Console\Cli;

use Grav\Common\Backup\ZipBackup;
use Grav\Console\ConsoleTrait;
use RocketTheme\Toolbox\File\JsonFile;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class BackupCommand
 * @package Grav\Console\Cli
 */
class BackupCommand extends Command
{
    use ConsoleTrait;

    protected $source;
    protected $progress;

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
                'Where to store the backup (/backup is default)'

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
        $this->setupConsole($input, $output);

        $this->progress = new ProgressBar($output);
        $this->progress->setFormat('Archiving <cyan>%current%</cyan> files [<green>%bar%</green>] %elapsed:6s% %memory:6s%');

        self::getGrav()['config']->init();

        $destination = ($input->getArgument('destination')) ? $input->getArgument('destination') : null;
        $log = JsonFile::instance(self::getGrav()['locator']->findResource("log://backup.log", true, true));
        $backup = ZipBackup::backup($destination, [$this, 'output']);

        $log->content([
            'time' => time(),
            'location' => $backup
        ]);
        $log->save();

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

