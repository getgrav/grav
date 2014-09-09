<?php
namespace Grav\Console\Gpm;

use Grav\Common\GPM\GPM;
use Grav\Console\ConsoleTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InfoCommand extends Command {
    use ConsoleTrait;

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

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->setupConsole($input, $output);
        $this->gpm = new GPM($this->input->getOption('force'));

        $this->output->writeln('');

        $foundPackage = $this->gpm->findPackage($input->getArgument('package'));

        if (!$foundPackage) {
            $this->output->writeln("The package <cyan>'" . $input->getArgument('package') . "'</cyan> was not found in the Grav repository.");
            $this->output->writeln('');
            $this->output->writeln("You can list all the available packages by typing:");
            $this->output->writeln("    <green>" . $this->argv . " index</green>");
            $this->output->writeln('');
            exit;
        }

        $this->output->writeln("Found package <cyan>'" . $input->getArgument('package') . "'</cyan> under the '<green>" . ucfirst($foundPackage->package_type) . "</green>' section");
        $this->output->writeln('');
        $this->output->writeln("<cyan>" . $foundPackage->name . "</cyan>");
        $this->output->writeln('');
        $this->output->writeln("<white>" . strip_tags($foundPackage->description) . "</white>");
        $this->output->writeln('');
        $this->output->writeln("<magenta>Author:</magenta>     " . $foundPackage->author);
        $this->output->writeln("<magenta>Version:</magenta>    " . $foundPackage->version);
        $this->output->writeln("<magenta>Path:</magenta>       " . $foundPackage->install_path);
        $this->output->writeln("<magenta>URL:</magenta>        " . $foundPackage->url);
        $this->output->writeln("<magenta>Download:</magenta>   " . $foundPackage->download);
        $this->output->writeln('');
        $this->output->writeln("You can install this package by typing:");
        $this->output->writeln("    <green>" . $this->argv . " install</green> <cyan>" . $foundPackage->slug . "</cyan>");
        $this->output->writeln('');

    }
}
