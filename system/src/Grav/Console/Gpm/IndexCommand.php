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

class IndexCommand extends Command {

    protected $input;
    protected $ouput;
    protected $data;
    protected $argv;
    protected $destination;

    public function __construct(Grav $grav){
        $this->grav = $grav;

        // just for the gpm cli we force the filesystem driver cache
        $this->grav['config']->set('system.cache.driver', 'default');
        $this->argv = $_SERVER['argv'][0];

        parent::__construct();
    }

    protected function configure() {
        $this
        ->setName("index")
        ->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force re-fetching the data from remote'
        )
        ->addOption(
            'destination',
            'd',
            InputOption::VALUE_OPTIONAL,
            'The destination where the packages check should be looked at. By default this would be where the grav instance has been launched from',
            GRAV_ROOT
        )
        ->setDescription("Lists the plugins and themes available for installation")
        ->setHelp('The <info>index</info> command lists the plugins and themes available for installation');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;
        $this->output = $output;
        $this->destination = realpath($this->input->getOption('destination'));

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

        foreach ($this->data as $type => $result) {
            $result = json_decode($result)->results;
            $name = $result->name;

            $this->output->writeln("<green>$name</green> [ ".count($result->data)." ]");

            foreach ($result->data as $index => $package) {
                $this->output->writeln(str_pad($index + 1, 2, '0', STR_PAD_LEFT).". <cyan>".str_pad($package->name, 15)."</cyan> [".str_pad($package->slug, 15, ' ', STR_PAD_BOTH)."] ". $this->versionDetails($package));
           }

           $this->output->writeln('');
        }

        $this->output->writeln('You can either get more informations about a package by typing:');
        $this->output->writeln('    <green>'.$this->argv.' info <cyan><package></cyan></green>');
        $this->output->writeln('');
        $this->output->writeln('Or you can install a package by typing:');
        $this->output->writeln('    <green>'.$this->argv.' install <cyan><package></cyan></green>');
        $this->output->writeln('');
    }

    private function setColors()
    {
        $this->output->getFormatter()->setStyle('normal', new OutputFormatterStyle('white'));
        $this->output->getFormatter()->setStyle('yellow', new OutputFormatterStyle('yellow', null, array('bold')));
        $this->output->getFormatter()->setStyle('red', new OutputFormatterStyle('red', null, array('bold')));
        $this->output->getFormatter()->setStyle('cyan', new OutputFormatterStyle('cyan', null, array('bold')));
        $this->output->getFormatter()->setStyle('green', new OutputFormatterStyle('green', null, array('bold')));
        $this->output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta', null, array('bold')));
        $this->output->getFormatter()->setStyle('white', new OutputFormatterStyle('white', null, array('bold')));
    }

    private function versionDetails($package)
    {
        $localVersion  = @file_get_contents($this->destination.DS.$package->install_path.DS."VERSION");
        $remoteVersion = $package->version;
        if ($localVersion) $localVersion = str_replace(array("\r", "\n"), '', $localVersion);
        $compare       = version_compare($localVersion, $remoteVersion);

        if (!$localVersion || !$compare){
            $installed = !$localVersion ? ' (<magenta>not installed</magenta>)' : ' (<cyan>installed</cyan>)';
            return " [v<green>$remoteVersion</green>]$installed";
        }


        if ($compare < 0){
            return " [v<red>$localVersion</red> <cyan>-></cyan> v<green>$remoteVersion</green>]";
        }

        if ($compare > 0){
            return " [Great Scott! -> local: v<yellow>$localVersion</yellow> | remote: v<yellow>$remoteVersion</yellow>]";
        }
        var_dump($localVersion, $remoteVersion, version_compare($localVersion, $remoteVersion));
    }
}
