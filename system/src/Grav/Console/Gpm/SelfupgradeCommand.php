<?php

/**
 * @package    Grav\Console\Gpm
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Gpm;

use Exception;
use Grav\Common\Cache;
use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\Installer;
use Grav\Common\GPM\Response;
use Grav\Common\GPM\Upgrader;
use Grav\Common\Grav;
use Grav\Console\GpmCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use ZipArchive;
use function is_callable;
use function strlen;

/**
 * Class SelfupgradeCommand
 * @package Grav\Console\Gpm
 */
class SelfupgradeCommand extends GpmCommand
{
    /** @var array */
    protected $data;
    /** @var string */
    protected $file;
    /** @var array */
    protected $types = ['plugins', 'themes'];
    /** @var string */
    private $tmp;
    /** @var Upgrader */
    private $upgrader;

    /** @var string */
    protected $all_yes;
    /** @var string */
    protected $overwrite;
    /** @var int */
    protected $timeout;

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('self-upgrade')
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
            ->addOption(
                'timeout',
                't',
                InputOption::VALUE_OPTIONAL,
                'Option to set the timeout in seconds when downloading the update (0 for no timeout)',
                30
            )
            ->setDescription('Detects and performs an update of Grav itself when available')
            ->setHelp('The <info>update</info> command updates Grav itself when a new version is available');
    }

    /**
     * @return int
     */
    protected function serve(): int
    {
        $input = $this->getInput();
        $io = $this->getIO();

        if (!class_exists(ZipArchive::class)) {
            $io->title('GPM Self Upgrade');
            $io->error('php-zip extension needs to be enabled!');

            return 1;
        }

        $this->upgrader = new Upgrader($input->getOption('force'));
        $this->all_yes = $input->getOption('all-yes');
        $this->overwrite = $input->getOption('overwrite');
        $this->timeout = (int) $input->getOption('timeout');

        $this->displayGPMRelease();

        $update = $this->upgrader->getAssets()['grav-update'];

        $local = $this->upgrader->getLocalVersion();
        $remote = $this->upgrader->getRemoteVersion();
        $release = strftime('%c', strtotime($this->upgrader->getReleaseDate()));

        if (!$this->upgrader->meetsRequirements()) {
            $io->writeln('<red>ATTENTION:</red>');
            $io->writeln('   Grav has increased the minimum PHP requirement.');
            $io->writeln('   You are currently running PHP <red>' . phpversion() . '</red>, but PHP <green>' . $this->upgrader->minPHPVersion() . '</green> is required.');
            $io->writeln('   Additional information: <white>http://getgrav.org/blog/changing-php-requirements</white>');
            $io->newLine();
            $io->writeln('Selfupgrade aborted.');
            $io->newLine();

            return 1;
        }

        if (!$this->overwrite && !$this->upgrader->isUpgradable()) {
            $io->writeln("You are already running the latest version of Grav (v{$local}) released on {$release}");

            return 0;
        }

        Installer::isValidDestination(GRAV_ROOT . '/system');
        if (Installer::IS_LINK === Installer::lastErrorCode()) {
            $io->writeln('<red>ATTENTION:</red> Grav is symlinked, cannot upgrade, aborting...');
            $io->newLine();
            $io->writeln("You are currently running a symbolically linked Grav v{$local}. Latest available is v{$remote}.");

            return 1;
        }

        // not used but preloaded just in case!
        new ArrayInput([]);

        $io->writeln("Grav v<cyan>{$remote}</cyan> is now available [release date: {$release}].");
        $io->writeln('You are currently using v<cyan>' . GRAV_VERSION . '</cyan>.');

        if (!$this->all_yes) {
            $question = new ConfirmationQuestion(
                'Would you like to read the changelog before proceeding? [y|N] ',
                false
            );
            $answer = $io->askQuestion($question);

            if ($answer) {
                $changelog = $this->upgrader->getChangelog(GRAV_VERSION);

                $io->newLine();
                foreach ($changelog as $version => $log) {
                    $title = $version . ' [' . $log['date'] . ']';
                    $content = preg_replace_callback('/\d\.\s\[\]\(#(.*)\)/', static function ($match) {
                        return "\n" . ucfirst($match[1]) . ':';
                    }, $log['content']);

                    $io->writeln($title);
                    $io->writeln(str_repeat('-', strlen($title)));
                    $io->writeln($content);
                    $io->newLine();
                }

                $question = new ConfirmationQuestion('Press [ENTER] to continue.', true);
                $io->askQuestion($question);
            }

            $question = new ConfirmationQuestion('Would you like to upgrade now? [y|N] ', false);
            $answer = $io->askQuestion($question);

            if (!$answer) {
                $io->writeln('Aborting...');

                return 1;
            }
        }

        $io->newLine();
        $io->writeln("Preparing to upgrade to v<cyan>{$remote}</cyan>..");

        $io->write("  |- Downloading upgrade [{$this->formatBytes($update['size'])}]...     0%");
        $this->file = $this->download($update);

        $io->write('  |- Installing upgrade...  ');
        $installation = $this->upgrade();

        $error = 0;
        if (!$installation) {
            $io->writeln("  '- <red>Installation failed or aborted.</red>");
            $io->newLine();
            $error = 1;
        } else {
            $io->writeln("  '- <green>Success!</green>  ");
            $io->newLine();
        }

        // clear cache after successful upgrade
        $this->clearCache(['all']);

        return $error;
    }

    /**
     * @param array $package
     * @return string
     */
    private function download(array $package): string
    {
        $io = $this->getIO();

        $tmp_dir = Grav::instance()['locator']->findResource('tmp://', true, true);
        $this->tmp = $tmp_dir . '/Grav-' . uniqid('', false);
        $options = [
            'curl' => [
                CURLOPT_TIMEOUT => $this->timeout,
            ],
            'fopen' => [
                'timeout' => $this->timeout,
            ],
        ];

        $output = Response::get($package['download'], $options, [$this, 'progress']);

        Folder::create($this->tmp);

        $io->write("\x0D");
        $io->write("  |- Downloading upgrade [{$this->formatBytes($package['size'])}]...   100%");
        $io->newLine();

        file_put_contents($this->tmp . DS . $package['name'], $output);

        return $this->tmp . DS . $package['name'];
    }

    /**
     * @return bool
     */
    private function upgrade(): bool
    {
        $io = $this->getIO();

        if ($this->file) {
            $folder = Installer::unZip($this->file, $this->tmp . '/zip');
        } else {
            $folder = false;
        }

        $this->upgradeGrav($this->file, $folder);

        $errorCode = Installer::lastErrorCode();

        if ($this->tmp) {
            Folder::delete($this->tmp);
        }

        if ($errorCode & (Installer::ZIP_OPEN_ERROR | Installer::ZIP_EXTRACT_ERROR)) {
            $io->write("\x0D");
            // extra white spaces to clear out the buffer properly
            $io->writeln('  |- Installing upgrade...    <red>error</red>                             ');
            $io->writeln("  |  '- " . Installer::lastErrorMsg());

            return false;
        }

        $io->write("\x0D");
        // extra white spaces to clear out the buffer properly
        $io->writeln('  |- Installing upgrade...    <green>ok</green>                             ');

        return true;
    }

    /**
     * @param array $progress
     * @return void
     */
    public function progress(array $progress): void
    {
        $io = $this->getIO();

        $io->write("\x0D");
        $io->write("  |- Downloading upgrade [{$this->formatBytes($progress['filesize']) }]... " . str_pad(
            $progress['percent'],
            5,
            ' ',
            STR_PAD_LEFT
        ) . '%');
    }

    /**
     * @param int|float $size
     * @param int $precision
     * @return string
     */
    public function formatBytes($size, int $precision = 2): string
    {
        $base = log($size) / log(1024);
        $suffixes = array('', 'k', 'M', 'G', 'T');

        return round(1024 ** ($base - floor($base)), $precision) . $suffixes[(int)floor($base)];
    }

    /**
     * @param string $zip
     * @param string $folder
     * @param bool $keepFolder
     * @return void
     */
    private function upgradeGrav(string $zip, string $folder, bool $keepFolder = false): void
    {
        static $ignores = [
            'backup',
            'cache',
            'images',
            'logs',
            'tmp',
            'user',
            '.htaccess',
            'robots.txt'
        ];

        if (!is_dir($folder)) {
            Installer::setError('Invalid source folder');
        }

        try {
            $script = $folder . '/system/install.php';
            /** Install $installer */
            if ((file_exists($script) && $install = include $script) && is_callable($install)) {
                $install($zip);
            } else {
                Installer::install(
                    $zip,
                    GRAV_ROOT,
                    ['sophisticated' => true, 'overwrite' => true, 'ignore_symlinks' => true, 'ignores' => $ignores],
                    $folder,
                    $keepFolder
                );

                Cache::clearCache();
            }
        } catch (Exception $e) {
            Installer::setError($e->getMessage());
        }
    }
}
