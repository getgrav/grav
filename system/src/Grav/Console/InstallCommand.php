<?php
namespace Grav\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use \Symfony\Component\Yaml\Yaml;

class InstallCommand extends Command {

    protected $configuration;

    protected function configure() {
        $this
        ->setName("install")
        ->addOption(
            'symlink',
            's',
            InputOption::VALUE_NONE,
            'Symlink the required bits'
        )
        ->setDescription("Handles cloning and symlinking for Grav")
        ->setHelp('The <info>install</info> provides clone and symlink installation chores');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $dependencies_file = ROOT_DIR . '/.dependencies';


        // Create a red output option
        $output->getFormatter()->setStyle('red', new OutputFormatterStyle('red'));
        $output->getFormatter()->setStyle('cyan', new OutputFormatterStyle('cyan'));
        $output->getFormatter()->setStyle('green', new OutputFormatterStyle('green'));
        $output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta'));

        if (is_file($dependencies_file)) {
            $this->configuration = Yaml::parse($dependencies_file);

            if (!$input->getOption('symlink')) {
                $this->gitclone($output);
            } else {
                $this->symlink($output);
            }
        } else {
            $output->writeln('<red>ERROR</red> Missing .dependencies file');
        }




    }

    // loops over the array of paths and deletes the files/folders
    private function gitclone($output)
    {
        $output->writeln('');
        $output->writeln('<green>Cloning Bits</green>');
        $output->writeln('============');
        $output->writeln('');

        exec('cd ' . ROOT_DIR);
        foreach($this->configuration['git'] as $repo => $data) {
            if (!file_exists($data['path'])) {
                exec('git clone -b ' . $data['branch'] . ' ' . $data['url'] . ' ' . $data['path']);
                $output->writeln('<green>SUCCESS</green> cloned <magenta>' . $data['url'] . '</magenta> -> <cyan>' . $data['path'] . '</cyan>');
                $output->writeln('');
            } else {
                $output->writeln('<red>' . $data['path'] . ' already exists, skipping...</red>');
                $output->writeln('');
            }

        }
    }

    // loops over the array of paths and deletes the files/folders
    private function symlink($output)
    {
        $output->writeln('');
        $output->writeln('<green>Symlinking Bits</green>');
        $output->writeln('===============');
        $output->writeln('');

        exec('cd ' . ROOT_DIR);
        foreach($this->configuration['links'] as $repo => $data) {
            if (!file_exists($data['path'])) {
                $from = ROOT_DIR . $data['src'];
                $to = ROOT_DIR . $data['path'];
                symlink ($from, $to);
                $output->writeln('<green>SUCCESS</green> symlinked <magenta>' . $data['src'] . '</magenta> -> <cyan>' . $data['path'] . '</cyan>');
                $output->writeln('');
            } else {
                $output->writeln('<red>' . $data['path'] . ' already exists, skipping...</red>');
                $output->writeln('');
            }

        }
    }
}
