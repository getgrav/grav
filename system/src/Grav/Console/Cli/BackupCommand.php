<?php
namespace Grav\Console\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Yaml\Yaml;

class BackupCommand extends Command {

    protected $source;
    protected $progress;

    protected function configure() {
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
            $output->getFormatter()->setStyle('red', new OutputFormatterStyle('red'));
            $output->getFormatter()->setStyle('cyan', new OutputFormatterStyle('cyan'));
            $output->getFormatter()->setStyle('green', new OutputFormatterStyle('green'));
            $output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta'));

            $this->progress = new ProgressBar($output);
            $this->progress->setFormat('Archiving <cyan>%current%</cyan> files [<green>%bar%</green>] %elapsed:6s% %memory:6s%');

            $name        = basename($this->source);
            $dir         = dirname($this->source);
            $date        = date('YmdHis', time());
            $filename    = $name . '-' . $date . '.zip';

            $destination = ($input->getArgument('destination')) ? $input->getArgument('destination') : ROOT_DIR;
            $destination = rtrim($destination, DS) . DS . $filename;

            $output->writeln('');
            $output->writeln('Creating new Backup "'.$destination.'"');
            $this->progress->start();

            $zip = new \ZipArchive();
            $zip->open($destination, \ZipArchive::CREATE);
            $zip->addEmptyDir($name);

            $this->folderToZip($this->source, $zip, strlen($dir.DS), $this->progress);
            $zip->close();
            $this->progress->finish();
            $output->writeln('');
            $output->writeln('');

    }

    private static function folderToZip($folder, &$zipFile, $exclusiveLength, $progress) {
        $handle = opendir($folder);
        while (false !== $f = readdir($handle)) {
            if ($f != '.' && $f != '..') {
                $filePath = "$folder/$f";
                // Remove prefix from file path before add to zip.
                $localPath = substr($filePath, $exclusiveLength);
                if (is_file($filePath)) {
                    $zipFile->addFile($filePath, $localPath);
                    $progress->advance();
                } elseif (is_dir($filePath)) {
                    // Add sub-directory.
                    $zipFile->addEmptyDir($localPath);
                    self::folderToZip($filePath, $zipFile, $exclusiveLength, $progress);
                }
            }
        }
        closedir($handle);
    }
}

