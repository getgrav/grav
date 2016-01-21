<?php
namespace Grav\Console\Gpm;

use Grav\Common\GPM\GPM;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class InfoCommand
 * @package Grav\Console\Gpm
 */
class InfoCommand extends ConsoleCommand
{
    /**
     * @var
     */
    protected $data;
    /**
     * @var
     */
    protected $gpm;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("info")
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force fetching the new data remotely'
            )
            ->addOption(
                'all-yes',
                'y',
                InputOption::VALUE_NONE,
                'Assumes yes (or best approach) instead of prompting'
            )
            ->addArgument(
                'package',
                InputArgument::REQUIRED,
                'The package of which more informations are desired. Use the "index" command for a list of packages'
            )
            ->setDescription("Shows more informations about a package")
            ->setHelp('The <info>info</info> shows more informations about a package');
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        $this->gpm = new GPM($this->input->getOption('force'));

        $foundPackage = $this->gpm->findPackage($this->input->getArgument('package'));

        if (!$foundPackage) {
            $this->output->writeln("The package <cyan>'" . $this->input->getArgument('package') . "'</cyan> was not found in the Grav repository.");
            $this->output->writeln('');
            $this->output->writeln("You can list all the available packages by typing:");
            $this->output->writeln("    <green>" . $this->argv . " index</green>");
            $this->output->writeln('');
            exit;
        }

        $this->output->writeln("Found package <cyan>'" . $this->input->getArgument('package') . "'</cyan> under the '<green>" . ucfirst($foundPackage->package_type) . "</green>' section");
        $this->output->writeln('');
        $this->output->writeln("<cyan>" . $foundPackage->name . "</cyan> [" . $foundPackage->slug . "]");
        $this->output->writeln(str_repeat('-', strlen($foundPackage->name) + strlen($foundPackage->slug) + 3));
        $this->output->writeln("<white>" . strip_tags($foundPackage->description_plain) . "</white>");
        $this->output->writeln('');

        $packageURL = '';
        if (isset($foundPackage->author['url'])) {
            $packageURL = '<' . $foundPackage->author['url'] . '>';
        }

        $this->output->writeln("<green>" . str_pad("Author",
                12) . ":</green> " . $foundPackage->author['name'] . ' <' . $foundPackage->author['email'] . '> ' . $packageURL);

        foreach (array(
                     'version',
                     'keywords',
                     'date',
                     'homepage',
                     'demo',
                     'docs',
                     'guide',
                     'repository',
                     'bugs',
                     'zipball_url',
                     'license'
                 ) as $info) {
            if (isset($foundPackage->$info)) {
                $name = ucfirst($info);
                $data = $foundPackage->$info;

                if ($info == 'zipball_url') {
                    $name = "Download";
                }

                if ($info == 'date') {
                    $name = "Last Update";
                    $data = date('D, j M Y, H:i:s, P ', strtotime('2014-09-16T00:07:16Z'));
                }

                $name = str_pad($name, 12);
                $this->output->writeln("<green>" . $name . ":</green> " . $data);
            }
        }

        $type = rtrim($foundPackage->package_type, 's');
        $updatable = $this->gpm->{'is' . $type . 'Updatable'}($foundPackage->slug);
        $installed = $this->gpm->{'is' . $type . 'Installed'}($foundPackage->slug);

        // display current version if installed and different
        if ($installed && $updatable) {
            $local = $this->gpm->{'getInstalled'. $type}($foundPackage->slug);
            $this->output->writeln('');
            $this->output->writeln("Currently installed version: <magenta>" . $local->version . "</magenta>");
            $this->output->writeln('');
        }

        // display changelog information
        $questionHelper = $this->getHelper('question');
        $skipPrompt = $this->input->getOption('all-yes');

        if (!$skipPrompt) {
            $question = new ConfirmationQuestion("Would you like to read the changelog? [y|N] ",
                false);
            $answer = $questionHelper->ask($this->input, $this->output, $question);

            if ($answer) {
                $changelog = $foundPackage->changelog;

                $this->output->writeln("");
                foreach ($changelog as $version => $log) {
                    $title = $version . ' [' . $log['date'] . ']';
                    $content = preg_replace_callback("/\d\.\s\[\]\(#(.*)\)/", function ($match) {
                        return "\n" . ucfirst($match[1]) . ":";
                    }, $log['content']);

                    $this->output->writeln('<cyan>'.$title.'</cyan>');
                    $this->output->writeln(str_repeat('-', strlen($title)));
                    $this->output->writeln($content);
                    $this->output->writeln("");

                    $question = new ConfirmationQuestion("Press [ENTER] to continue or [q] to quit ", true);
                    if (!$questionHelper->ask($this->input, $this->output, $question)) {
                        break;
                    }
                    $this->output->writeln("");
                }
            }
        }


        $this->output->writeln('');

        if ($installed && $updatable) {
            $this->output->writeln("You can update this package by typing:");
            $this->output->writeln("    <green>" . $this->argv . " update</green> <cyan>" . $foundPackage->slug . "</cyan>");
        } else {
            $this->output->writeln("You can install this package by typing:");
            $this->output->writeln("    <green>" . $this->argv . " install</green> <cyan>" . $foundPackage->slug . "</cyan>");
        }

        $this->output->writeln('');

    }
}
