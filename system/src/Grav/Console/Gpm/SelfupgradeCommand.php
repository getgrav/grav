<?php
/**
 * @package    Grav.Console
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Gpm;

use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\Installer;
use Grav\Common\GPM\Response;
use Grav\Common\GPM\Upgrader;
use Grav\Common\Grav;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class SelfupgradeCommand extends ConsoleCommand
{
    /**
     * @var
     */
    protected $data;
    /**
     * @var
     */
    protected $extensions;
    /**
     * @var
     */
    protected $updatable;
    /**
     * @var
     */
    protected $file;
    /**
     * @var array
     */
    protected $types = ['plugins', 'themes'];
    /**
     * @var
     */
    private $tmp;
    /**
     * @var
     */
    private $upgrader;

    protected $all_yes;
    protected $overwrite;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("self-upgrade")
            ->setAliases(['selfupgrade', 'selfupdate'])
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force re-fetching the data from remote'
            )
            ->addOption(
                'all-yes',
                'y',
                InputOption::VALUE_NONE,
                'Assumes yes (or best approach) instead of prompting'
            )
            ->addOption(
                'overwrite',
                'o',
                InputOption::VALUE_NONE,
                'Option to overwrite packages if they already exist'
            )
            ->setDescription("Detects and performs an update of Grav itself when available")
            ->setHelp('The <info>update</info> command updates Grav itself when a new version is available');
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        $this->upgrader = new Upgrader($this->input->getOption('force'));
        $this->all_yes = $this->input->getOption('all-yes');
        $this->overwrite = $this->input->getOption('overwrite');

        $this->displayGPMRelease();

        $update = $this->upgrader->getAssets()['grav-update'];

        $local = $this->upgrader->getLocalVersion();
        $remote = $this->upgrader->getRemoteVersion();
        $release = strftime('%c', strtotime($this->upgrader->getReleaseDate()));

        if (!$this->upgrader->meetsRequirements()) {
            $this->output->writeln("<red>ATTENTION:</red>");
            $this->output->writeln("   Grav has increased the minimum PHP requirement.");
            $this->output->writeln("   You are currently running PHP <red>" . PHP_VERSION . "</red>, but PHP <green>" . GRAV_PHP_MIN . "</green> is required.");
            $this->output->writeln("   Additional information: <white>http://getgrav.org/blog/changing-php-requirements-to-5.5</white>");
            $this->output->writeln("");
            $this->output->writeln("Selfupgrade aborted.");
            $this->output->writeln("");
            exit;
        }

        if (!$this->overwrite && !$this->upgrader->isUpgradable()) {
            $this->output->writeln("You are already running the latest version of Grav (v" . $local . ") released on " . $release);
            exit;
        }

        Installer::isValidDestination(GRAV_ROOT . '/system');
        if (Installer::IS_LINK === Installer::lastErrorCode()) {
            $this->output->writeln("<red>ATTENTION:</red> Grav is symlinked, cannot upgrade, aborting...");
            $this->output->writeln('');
            $this->output->writeln("You are currently running a symbolically linked Grav v" . $local . ". Latest available is v". $remote . ".");
            exit;
        }

        // not used but preloaded just in case!
        new ArrayInput([]);

        $questionHelper = $this->getHelper('question');


        $this->output->writeln("Grav v<cyan>$remote</cyan> is now available [release date: $release].");
        $this->output->writeln("You are currently using v<cyan>" . GRAV_VERSION . "</cyan>.");

        if (!$this->all_yes) {
            $question = new ConfirmationQuestion("Would you like to read the changelog before proceeding? [y|N] ",
                false);
            $answer = $questionHelper->ask($this->input, $this->output, $question);

            if ($answer) {
                $changelog = $this->upgrader->getChangelog(GRAV_VERSION);

                $this->output->writeln("");
                foreach ($changelog as $version => $log) {
                    $title = $version . ' [' . $log['date'] . ']';
                    $content = preg_replace_callback("/\d\.\s\[\]\(#(.*)\)/", function ($match) {
                        return "\n" . ucfirst($match[1]) . ":";
                    }, $log['content']);

                    $this->output->writeln($title);
                    $this->output->writeln(str_repeat('-', strlen($title)));
                    $this->output->writeln($content);
                    $this->output->writeln("");
                }

                $question = new ConfirmationQuestion("Press [ENTER] to continue.", true);
                $questionHelper->ask($this->input, $this->output, $question);
            }

            $question = new ConfirmationQuestion("Would you like to upgrade now? [y|N] ", false);
            $answer = $questionHelper->ask($this->input, $this->output, $question);

            if (!$answer) {
                $this->output->writeln("Aborting...");

                exit;
            }
        }

        $this->output->writeln("");
        $this->output->writeln("Preparing to upgrade to v<cyan>$remote</cyan>..");

        $this->output->write("  |- Downloading upgrade [" . $this->formatBytes($update['size']) . "]...     0%");
        $this->file = $this->download($update);

        $this->output->write("  |- Installing upgrade...  ");
        $installation = $this->upgrade();

        if (!$installation) {
            $this->output->writeln("  '- <red>Installation failed or aborted.</red>");
            $this->output->writeln('');
        } else {
            $this->output->writeln("  '- <green>Success!</green>  ");
            $this->output->writeln('');
        }

        // clear cache after successful upgrade
        $this->clearCache('all');
    }

    /**
     * @param $package
     *
     * @return string
     */
    private function download($package)
    {
        $tmp_dir = Grav::instance()['locator']->findResource('tmp://', true, true);
        $this->tmp = $tmp_dir . '/Grav-' . uniqid();
        $output = Response::get($package['download'], [], [$this, 'progress']);

        Folder::mkdir($this->tmp);

        $this->output->write("\x0D");
        $this->output->write("  |- Downloading upgrade [" . $this->formatBytes($package['size']) . "]...   100%");
        $this->output->writeln('');

        file_put_contents($this->tmp . DS . $package['name'], $output);

        return $this->tmp . DS . $package['name'];
    }

    /**
     * @return bool
     */
    private function upgrade()
    {
        Installer::install($this->file, GRAV_ROOT,
            ['sophisticated' => true, 'overwrite' => true, 'ignore_symlinks' => true]);
        $errorCode = Installer::lastErrorCode();
        Folder::delete($this->tmp);

        if ($errorCode & (Installer::ZIP_OPEN_ERROR | Installer::ZIP_EXTRACT_ERROR)) {
            $this->output->write("\x0D");
            // extra white spaces to clear out the buffer properly
            $this->output->writeln("  |- Installing upgrade...    <red>error</red>                             ");
            $this->output->writeln("  |  '- " . Installer::lastErrorMsg());

            return false;
        }

        $this->output->write("\x0D");
        // extra white spaces to clear out the buffer properly
        $this->output->writeln("  |- Installing upgrade...    <green>ok</green>                             ");

        return true;
    }

    /**
     * @param $progress
     */
    public function progress($progress)
    {
        $this->output->write("\x0D");
        $this->output->write("  |- Downloading upgrade [" . $this->formatBytes($progress["filesize"]) . "]... " . str_pad($progress['percent'],
                5, " ", STR_PAD_LEFT) . '%');
    }

    /**
     * @param     $size
     * @param int $precision
     *
     * @return string
     */
    public function formatBytes($size, $precision = 2)
    {
        $base = log($size) / log(1024);
        $suffixes = array('', 'k', 'M', 'G', 'T');

        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[(int)floor($base)];
    }
}
