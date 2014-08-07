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

    protected $config;
    protected $local_config;

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

        $dependencies_file = USER_DIR . '/.dependencies';
        $local_config_file = exec('eval echo ~/.grav/config');

        // Create a red output option
        $output->getFormatter()->setStyle('red', new OutputFormatterStyle('red'));
        $output->getFormatter()->setStyle('cyan', new OutputFormatterStyle('cyan'));
        $output->getFormatter()->setStyle('green', new OutputFormatterStyle('green'));
        $output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta'));

        if (file_exists($local_config_file)) {
            $this->local_config = Yaml::parse($local_config_file);
            $output->writeln('');
            $output->writeln('read local config from <cyan>' . $local_config_file . '</cyan>');
        }



        if (is_file($dependencies_file)) {
            $this->config = Yaml::parse($dependencies_file);

            if (!$input->getOption('symlink')) {
                $this->gitclone($output);
            } else {
                $this->symlink($output);
            }
        } else {
            $output->writeln('<red>ERROR</red> Missing .dependencies file in <cyan>user/</cyan> folder');
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
        foreach($this->config['git'] as $repo => $data) {
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

        if (!$this->local_config) {
            $output->writeln('<red>No local configuration available, aborting...</red>');
            $output->writeln('');
            exit;
        }

        exec('cd ' . ROOT_DIR);
        foreach($this->config['links'] as $repo => $data) {
            $from = $this->local_config[$data['scm'].'_repos'] . $data['src'];
            $to = ROOT_DIR . $data['path'];

            if (file_exists($from)) {
                if (!file_exists($to)) {
                    symlink ($from, $to);
                    $output->writeln('<green>SUCCESS</green> symlinked <magenta>' . $data['src'] . '</magenta> -> <cyan>' . $data['path'] . '</cyan>');
                    $output->writeln('');
                } else {
                    $output->writeln('<red>destination: ' . $to . ' already exists, skipping...</red>');
                    $output->writeln('');
                }
            } else {
                $output->writeln('<red>source: ' . $from . ' does not exists, skipping...</red>');
                $output->writeln('');
            }

        }
    }
}
