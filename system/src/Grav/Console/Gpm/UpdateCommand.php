<?php
namespace Grav\Console\Gpm;

use Grav\Console\ConsoleTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class UpdateCommand extends Command {
    use ConsoleTrait;

    protected $data;
    protected $extensions;
    protected $updatable;
    protected $destination;
    protected $file;
    protected $types = array('plugins', 'themes');

    protected function configure() {
        $this
        ->setName("update")
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
            'The grav instance location where the updates should be applied to. By default this would be where the grav cli has been launched from',
            GRAV_ROOT
        )
        ->addArgument(
            'package',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'The package or packages that is desired to update. By default all available updates will be applied.'
        )
        ->setDescription("Detects and performs an update of plugins and themes when available")
        ->setHelp('The <info>update</info> command updates plugins and themes when a new version is available');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setupConsole($input, $output);

        $this->destination = realpath($this->input->getOption('destination'));

        $this->isGravRoot($this->destination);

        // fetch remote data and scan for local extensions
        $this->data       = $this->fetchData();
        $this->extensions = $this->scanForExtensions();

        if (!$this->extensions['total']){
            $packages = array_map('strtolower', $this->input->getArgument('package'));
            $this->output->writeln("Nothing to update.");
            if (count($packages)){
                $this->output->writeln("Packages not found: <red>".implode('</red>, <red>', $packages)."</red>");
            }
            exit;
        }

        // compare fetched data and local extensions and see what's updatable
        $this->updatable = $this->scanForUpdates();

        $this->output->writeln("Found <green>".$this->extensions['total']."</green> extensions of which <magenta>".count($this->updatable)."</magenta> need updating\n");

        if (!count($this->updatable)){
            $this->output->writeln("Good job on keeping everything <cyan>up to date</cyan>.");
            $this->output->writeln("Nothing else to do here!");
            exit;
        }

        // updates review
        foreach ($this->updatable as $extension) {
            $this->output->writeln("<cyan>".str_pad($extension->name, 15)."</cyan> [v<magenta>".$extension->current_version."</magenta> ➜ v<green>".$extension->version."</green>]");
        }

        // prompt to continue
        $this->output->writeln("");
        $questionHelper = $this->getHelper('question');
        $question       = new ConfirmationQuestion("Continue with the update process? [Y|n] ", true);
        $answer         = $questionHelper->ask($this->input, $this->output, $question);

        if (!$answer){
            $this->output->writeln("Update aborted. Exiting...");
            exit;
        }

        // finally update
        $packages = array_map(function($e){ return $e->slug; }, $this->updatable);
        $installCommand = $this->getApplication()->find('install');
        $args           = new ArrayInput(array(
                                         'command'  => 'install',
                                         'package'  => $packages,
                                         '-f'       => $this->input->getOption('force'),
                                         '-d'       => $this->destination,
                                         '-y'       => true
                                         ));
        $commandExec    = $installCommand->run($args, $this->output);

        if ($commandExec != 0){
            $this->output->writeln("<red>Error:</red> An error occured while trying to install the extensions");
            exit;
        }
    }

    private function isGravRoot($path)
    {
        if (!file_exists($path)){
            $this->output->writeln('');
            $this->output->writeln("<red>ERROR</red>: Destination doesn't exist:");
            $this->output->writeln("       <white>$path</white>");
            $this->output->writeln('');
            exit;
        }

        if (!is_dir($path)){
            $this->output->writeln('');
            $this->output->writeln("<red>ERROR</red>: Destination chosen to install is not a directory:");
            $this->output->writeln("       <white>$path</white>");
            $this->output->writeln('');
            exit;
        }

        if (!file_exists($path.DS.'index.php') || !file_exists($path.DS.'.dependencies') || !file_exists($path.DS.'system'.DS.'config'.DS.'system.yaml')){
            $this->output->writeln('');
            $this->output->writeln("<red>ERROR</red>: Destination chosen to install does not appear to be a Grav instance:");
            $this->output->writeln("       <white>$path</white>");
            $this->output->writeln('');
            exit;
        }
    }

    private function scanForExtensions()
    {
        $types    = $this->types;
        $found    = array('total' => 0);
        $packages = array_map('strtolower', $this->input->getArgument('package'));

        foreach ($types as $type) {
            $found[$type] = array();
            foreach (new \DirectoryIterator($this->destination.DS.'user'.DS.$type) as $node) {
                $name = $node->getFileName();
                $path = $node->getPathName();

                // ignore dot folders, everything that starts with dot, symlinks and files
                if ($node->isDot() || $node->isLink() || !$node->isDir() || substr($name, 0, 1) == '.') continue;
                if (!file_exists($version = $path.DS.'VERSION')) continue;
                if (count($packages) && !in_array($name, $packages)) continue;

                $version = str_replace(array("\r", "\n"), '', file_get_contents($version));

                $found[$type][$name] = array(
                                      "name"    => $name,
                                      "path"    => $path,
                                      "version" => $version
                                      );
                $found['total']++;
            }
        }

        return $found;
    }

    private function scanForUpdates()
    {
        $updatable = array();

        foreach ($this->types as $type) {
            if (!isset($this->data[$type])) continue;
            $json = json_decode($this->data[$type])->results;

            $dataType = $json->type;
            $dataName = $json->name;
            $filter = array_filter($json->data, function($o) use ($type) {
                $can_update = array_key_exists($o->slug, $this->extensions[$type]);
                if (!$can_update) return false;

                $current_version = $this->extensions[$type][$o->slug]['version'];
                $can_update = version_compare($current_version, $o->version);
                if ($can_update >= 0) return false;

                $o->current_version = $current_version;
                return $can_update;
            });

            if (count($filter)) $updatable[] = array_values($filter)[0];
        }

        return $updatable;
    }
}
