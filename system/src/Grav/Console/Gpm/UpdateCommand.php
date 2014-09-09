<?php
namespace Grav\Console\Gpm;

use Grav\Common\GPM\GPM;
use Grav\Console\ConsoleTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

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
            InputArgument::IS_ARRAY|InputArgument::OPTIONAL,
            'The package or packages that is desired to update. By default all available updates will be applied.'
        )
            ->setDescription("Detects and performs an update of plugins and themes when available")
            ->setHelp('The <info>update</info> command updates plugins and themes when a new version is available');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->setupConsole($input, $output);

        $this->gpm         = new GPM($this->input->getOption('force'));
        $this->destination = realpath($this->input->getOption('destination'));

        $this->isGravInstance($this->destination);

        // fetch remote data and scan for local extensions
        $this->data = $this->gpm->getUpdatable();

        if (!$this->data['total']) {
            $packages = array_map('strtolower', $this->input->getArgument('package'));
            $this->output->writeln("Nothing to update.");
            if (count($packages)) {
                $this->output->writeln("Packages not found: <red>" . implode('</red>, <red>', $packages) . "</red>");
            }
            exit;
        }

        $this->output->writeln("Found <green>" . $this->gpm->countInstalled() . "</green> extensions of which <magenta>" . $this->data['total'] . "</magenta> need updating\n");

        if (!$this->data['total']) {
            $this->output->writeln("Good job on keeping everything <cyan>up to date</cyan>.");
            $this->output->writeln("Nothing else to do here!");
            exit;
        }

        unset($this->data['total']);

        // updates review
        $slugs = [];

        foreach ($this->data as $type => $packages) {
            $index = 0;
            foreach ($packages as $slug => $package) {
                $this->output->writeln(
                    // index
                    str_pad($index+++1, 2, '0', STR_PAD_LEFT) . ". " .
                    // name
                    "<cyan>" . str_pad($package->name, 15) . "</cyan> " .
                    // version
                    "[v<magenta>" . $package->version . "</magenta> ➜ v<green>" . $package->available . "</green>]"
                );
                $slugs[] = $slug;
            }
        }

        // prompt to continue
        $this->output->writeln("");
        $questionHelper = $this->getHelper('question');
        $question       = new ConfirmationQuestion("Continue with the update process? [Y|n] ", true);
        $answer         = $questionHelper->ask($this->input, $this->output, $question);

        if (!$answer) {
            $this->output->writeln("Update aborted. Exiting...");
            exit;
        }

        // finally update
        $installCommand = $this->getApplication()->find('install');
        $args           = new ArrayInput(array(
                'command' => 'install',
                'package' => $slugs,
                '-f'      => $this->input->getOption('force'),
                '-d'      => $this->destination,
                '-y'      => true
            ));
        $commandExec = $installCommand->run($args, $this->output);

        if ($commandExec != 0) {
            $this->output->writeln("<red>Error:</red> An error occured while trying to install the extensions");
            exit;
        }
    }

}
