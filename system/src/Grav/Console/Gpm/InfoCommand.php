<?php
namespace Grav\Console\Gpm;

use Grav\Common\Grav;
use Grav\Common\Cache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class InfoCommand extends Command {

    protected $input;
    protected $ouput;
    protected $data;
    protected $argv;

    public function __construct(Grav $grav){
        $this->grav = $grav;

        // just for the gpm cli we force the filesystem driver cache
        $this->grav['config']->set('system.cache.driver', 'default');
        $this->argv = $_SERVER['argv'][0];

        parent::__construct();
    }

    protected function configure() {
        $this
        ->setName("info")
        ->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force fetching the new data remotely'
        )
        ->addArgument(
            'package',
            InputArgument::REQUIRED,
            'The package of which more informations are desired. Use the "index" command for a list of packages'

        )
        ->setDescription("Shows more informations about a package")
        ->setHelp('The <info>info</info> shows more informations about a package');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;
        $this->output = $output;

        $userPackage  = false;

        $this->setColors();

        $fetchCommand = $this->getApplication()->find('fetch');
        $args         = new ArrayInput(array('command' => 'fetch', '-f' => $input->getOption('force')));
        $commandExec = $fetchCommand->run($args, $output);

        if ($commandExec != 0){
            $output->writeln("<red>Error:</red> An error occured while trying to fetch data from <cyan>getgrav.org</cyan>");
            exit;
        }

        $this->data = $this->grav['cache']->fetch(md5('cli:gpm'));

        $this->output->writeln('');

        $findPackage = strtolower($input->getArgument('package'));
        foreach ($this->data as $type => $result) {
            $result = json_decode($result)->results;
            $name = $result->name;

            foreach ($result->data as $index => $package) {
                if (strtolower($package->name) == $findPackage || strtolower($package->slug) == $findPackage){
                    $userPackage = array();
                    $userPackage[$name] = $package;
                }
            }
        }

        if (!$userPackage){
            $this->output->writeln("The package <cyan>'".$input->getArgument('package')."'</cyan> was not found in the Grav repository.");
            $this->output->writeln('');
            $this->output->writeln("You can list all the available packages by typing:");
            $this->output->writeln("    <green>".$this->argv." index</green>");
            $this->output->writeln('');
            exit;
        }

        $key = key($userPackage);
        $this->output->writeln("Found package <cyan>'".$input->getArgument('package')."'</cyan> under the '<green>".$key."</green>' section");
        $this->output->writeln('');
        $this->output->writeln("<cyan>".$userPackage[$key]->name."</cyan>");
        $this->output->writeln('');
        $this->output->writeln("<white>".strip_tags($userPackage[$key]->description)."</white>");
        $this->output->writeln('');
        $this->output->writeln("<magenta>Author:</magenta>     ".$userPackage[$key]->author);
        $this->output->writeln("<magenta>Version:</magenta>    ".$userPackage[$key]->version);
        $this->output->writeln("<magenta>Path:</magenta>       ".$userPackage[$key]->install_path);
        $this->output->writeln("<magenta>URL:</magenta>        ".$userPackage[$key]->url);
        $this->output->writeln("<magenta>Download:</magenta>   ".$userPackage[$key]->download);
        $this->output->writeln('');
        $this->output->writeln("You can install this package by typing:");
        $this->output->writeln("    <green>".$this->argv." install</green> <cyan>".$userPackage[$key]->slug."</cyan>");
        $this->output->writeln('');

    }

       private function setColors()
    {
        $this->output->getFormatter()->setStyle('normal', new OutputFormatterStyle('white'));
        $this->output->getFormatter()->setStyle('red', new OutputFormatterStyle('red', null, array('bold')));
        $this->output->getFormatter()->setStyle('cyan', new OutputFormatterStyle('cyan', null, array('bold')));
        $this->output->getFormatter()->setStyle('green', new OutputFormatterStyle('green', null, array('bold')));
        $this->output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta', null, array('bold')));
        $this->output->getFormatter()->setStyle('white', new OutputFormatterStyle('white', null, array('bold')));
    }
}
