<?php

/**
 * @package    Grav\Console\Gpm
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Gpm;

use Grav\Common\GPM\GPM;
use Grav\Console\GpmCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use function strlen;

/**
 * Class InfoCommand
 * @package Grav\Console\Gpm
 */
class InfoCommand extends GpmCommand
{
    /** @var array */
    protected $data;
    /** @var GPM */
    protected $gpm;
    /** @var string */
    protected $all_yes;

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('info')
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
            ->setDescription('Shows more informations about a package')
            ->setHelp('The <info>info</info> shows more information about a package');
    }

    /**
     * @return int
     */
    protected function serve(): int
    {
        $input = $this->getInput();
        $io = $this->getIO();

        $this->gpm = new GPM($input->getOption('force'));

        $this->all_yes = $input->getOption('all-yes');

        $this->displayGPMRelease();

        $foundPackage = $this->gpm->findPackage($input->getArgument('package'));

        if (!$foundPackage) {
            $io->writeln("The package <cyan>'{$input->getArgument('package')}'</cyan> was not found in the Grav repository.");
            $io->newLine();
            $io->writeln('You can list all the available packages by typing:');
            $io->writeln("    <green>{$this->argv} index</green>");
            $io->newLine();

            return 1;
        }

        $io->writeln("Found package <cyan>'{$input->getArgument('package')}'</cyan> under the '<green>" . ucfirst($foundPackage->package_type) . "</green>' section");
        $io->newLine();
        $io->writeln("<cyan>{$foundPackage->name}</cyan> [{$foundPackage->slug}]");
        $io->writeln(str_repeat('-', strlen($foundPackage->name) + strlen($foundPackage->slug) + 3));
        $io->writeln('<white>' . strip_tags($foundPackage->description_plain) . '</white>');
        $io->newLine();

        $packageURL = '';
        if (isset($foundPackage->author['url'])) {
            $packageURL = '<' . $foundPackage->author['url'] . '>';
        }

        $io->writeln('<green>' . str_pad(
            'Author',
            12
        ) . ':</green> ' . $foundPackage->author['name'] . ' <' . $foundPackage->author['email'] . '> ' . $packageURL);

        foreach ([
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
                 ] as $info) {
            if (isset($foundPackage->{$info})) {
                $name = ucfirst($info);
                $data = $foundPackage->{$info};

                if ($info === 'zipball_url') {
                    $name = 'Download';
                }

                if ($info === 'date') {
                    $name = 'Last Update';
                    $data = date('D, j M Y, H:i:s, P ', strtotime($data));
                }

                $name = str_pad($name, 12);
                $io->writeln("<green>{$name}:</green> {$data}");
            }
        }

        $type = rtrim($foundPackage->package_type, 's');
        $updatable = $this->gpm->{'is' . $type . 'Updatable'}($foundPackage->slug);
        $installed = $this->gpm->{'is' . $type . 'Installed'}($foundPackage->slug);

        // display current version if installed and different
        if ($installed && $updatable) {
            $local = $this->gpm->{'getInstalled'. $type}($foundPackage->slug);
            $io->newLine();
            $io->writeln("Currently installed version: <magenta>{$local->version}</magenta>");
            $io->newLine();
        }

        // display changelog information
        $question = new ConfirmationQuestion(
            'Would you like to read the changelog? [y|N] ',
            false
        );
        $answer = $this->all_yes ? true : $io->askQuestion($question);

        if ($answer) {
            $changelog = $foundPackage->changelog;

            $io->newLine();
            foreach ($changelog as $version => $log) {
                $title = $version . ' [' . $log['date'] . ']';
                $content = preg_replace_callback('/\d\.\s\[\]\(#(.*)\)/', static function ($match) {
                    return "\n" . ucfirst($match[1]) . ':';
                }, $log['content']);

                $io->writeln("<cyan>{$title}</cyan>");
                $io->writeln(str_repeat('-', strlen($title)));
                $io->writeln($content);
                $io->newLine();

                $question = new ConfirmationQuestion('Press [ENTER] to continue or [q] to quit ', true);
                $answer = $this->all_yes ? false : $io->askQuestion($question);
                if (!$answer) {
                    break;
                }
                $io->newLine();
            }
        }

        $io->newLine();

        if ($installed && $updatable) {
            $io->writeln('You can update this package by typing:');
            $io->writeln("    <green>{$this->argv} update</green> <cyan>{$foundPackage->slug}</cyan>");
        } else {
            $io->writeln('You can install this package by typing:');
            $io->writeln("    <green>{$this->argv} install</green> <cyan>{$foundPackage->slug}</cyan>");
        }

        $io->newLine();

        return 0;
    }
}
