<?php

/**
 * @package    Grav\Console\Gpm
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Gpm;

use Exception;
use Grav\Common\Filesystem\Folder;
use Grav\Common\HTTP\Response;
use Grav\Common\GPM\Installer;
use Grav\Common\GPM\Upgrader;
use Grav\Common\Grav;
use Grav\Console\GpmCommand;
use Grav\Installer\Install;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Grav\Common\Yaml;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
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
    /** @var string|null */
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
            $io->writeln("You are already running the latest version of <green>Grav v{$local}</green>");
            $io->writeln("which was released on {$release}");

            $config = Grav::instance()['config'];
            $schema = $config->get('versions.core.grav.schema');
            if ($schema !== GRAV_SCHEMA && version_compare($schema, GRAV_SCHEMA, '<')) {
                $io->newLine();
                $io->writeln('However post-install scripts have not been run.');
                if (!$this->all_yes) {
                    $question = new ConfirmationQuestion(
                        'Would you like to run the scripts? [Y|n] ',
                        true
                    );
                    $answer = $io->askQuestion($question);
                } else {
                    $answer = true;
                }

                if ($answer) {
                    // Finalize installation.
                    Install::instance()->finalize();

                    $io->write('  |- Running post-install scripts...  ');
                    $io->writeln("  '- <green>Success!</green>  ");
                    $io->newLine();
                }
            }

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

        if ($this->tmp && is_dir($this->tmp)) {
            Folder::delete($this->tmp);
        }

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
        $this->tmp = $tmp_dir . '/grav-update-' . uniqid('', false);
        $options = [
            'timeout' => $this->timeout,
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

        $this->upgradeGrav($this->file);

        $errorCode = Installer::lastErrorCode();
        if ($errorCode) {
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
     * @return void
     */
    private function upgradeGrav(string $zip): void
    {
        try {
            $folder = Installer::unZip($zip, $this->tmp . '/zip');
            if ($folder === false) {
                throw new RuntimeException(Installer::lastErrorMsg());
            }

            $script = $folder . '/system/install.php';
            if ((file_exists($script) && $install = include $script) && is_callable($install)) {
                // Run preflight from the NEW package's installer if available
                if (is_object($install) && method_exists($install, 'generatePreflightReport')) {
                    $report = $install->generatePreflightReport();
                    if (!$this->handlePreflightReport($report)) {
                        Installer::setError('Upgrade aborted due to preflight requirements.');
                        return;
                    }
                }
                $install($zip);
            } else {
                throw new RuntimeException('Uploaded archive file is not a valid Grav update package');
            }
        } catch (Exception $e) {
            Installer::setError($e->getMessage());
        }
    }

    /**
     * Process a preflight report from the target package's installer.
     *
     * @param array $preflight
     * @return bool True to proceed, false to abort
     */
    protected function handlePreflightReport(array $preflight): bool
    {
        $io = $this->getIO();
        $pending = $preflight['plugins_pending'] ?? [];
        $blocking = $preflight['blocking'] ?? [];
        $conflicts = $preflight['psr_log_conflicts'] ?? [];
        $monologConflicts = $preflight['monolog_conflicts'] ?? [];
        $warnings = $preflight['warnings'] ?? [];
        $incompatible = $preflight['incompatible_packages'] ?? [];
        $incompatibleBlocking = $incompatible['blocking'] ?? [];
        $incompatibleTarget = $incompatible['target'] ?? '';
        $isMajorMinorUpgrade = $preflight['is_major_minor_upgrade'] ?? false;

        if ($warnings) {
            $io->newLine();
            $io->writeln('<magenta>Preflight warnings detected:</magenta>');
            foreach ($warnings as $warning) {
                $io->writeln('  • ' . $warning);
            }
        }

        // Filter out the incompatible-packages blocker (handled separately below)
        $filteredBlocking = array_filter($blocking, static function ($reason) {
            return !stripos($reason, 'not been marked as compatible');
        });

        if ($filteredBlocking && empty($pending)) {
            $io->newLine();
            $io->writeln('<red>Upgrade blocked:</red>');
            foreach ($filteredBlocking as $reason) {
                $io->writeln('  - ' . $reason);
            }

            return false;
        }

        if (empty($pending) && empty($conflicts) && empty($monologConflicts) && empty($incompatibleBlocking)) {
            return true;
        }

        if ($pending && $isMajorMinorUpgrade) {
            $local = $this->upgrader ? $this->upgrader->getLocalVersion() : 'unknown';
            $remote = $this->upgrader ? $this->upgrader->getRemoteVersion() : 'unknown';

            $io->newLine();
            $io->writeln('<yellow>The following packages need updating before Grav upgrade:</yellow>');
            foreach ($pending as $slug => $info) {
                $type = $info['type'] ?? 'plugin';
                $current = $info['current'] ?? 'unknown';
                $available = $info['available'] ?? 'unknown';
                $io->writeln(sprintf('  - %s (%s) %s → %s', $slug, $type, $current, $available));
            }

            $io->writeln('    › For major version upgrades (v' . $local . ' → v' . $remote . '), plugins must be updated first.');
            $io->writeln('      Please run `bin/gpm update` to update these packages, then retry self-upgrade.');

            $proceed = false;
            if (!$this->all_yes) {
                $question = new ConfirmationQuestion('Proceed anyway? [y|N] ', false);
                $proceed = $io->askQuestion($question);
            }

            if (!$proceed) {
                $io->writeln('Aborting self-upgrade. Run `bin/gpm update` first.');

                return false;
            }

            if (method_exists(Install::class, 'allowPendingPackageOverride')) {
                Install::allowPendingPackageOverride(true);
            }
            $io->writeln('    › Proceeding despite pending plugin/theme updates.');
        }

        // Handle incompatible packages
        if ($incompatibleBlocking) {
            $io->newLine();
            $io->writeln('<yellow>The following enabled plugins/themes are not marked as compatible with Grav ' . $incompatibleTarget . ':</yellow>');
            foreach ($incompatibleBlocking as $slug => $info) {
                $type = $info['type'] ?? 'plugin';
                $ver = $info['version'] ?? 'unknown';
                $gravCompat = implode(', ', $info['compatibility']['grav'] ?? ['?']);
                $io->writeln(sprintf('  - %s (%s v%s) — compatible with: %s', $slug, $type, $ver, $gravCompat));
            }
            $io->writeln('    › Plugins/themes must be marked as compatible with Grav ' . $incompatibleTarget . ' before upgrading.');
            $io->writeln('      Either update the plugins, or disable them to proceed.');

            $choice = $this->all_yes ? 'abort' : $io->choice(
                'How would you like to proceed?',
                ['disable', 'continue', 'abort'],
                'abort'
            );

            if ($choice === 'abort') {
                $io->writeln('Aborting self-upgrade. Update or disable incompatible plugins first.');

                return false;
            }

            if ($choice === 'disable') {
                foreach (array_keys($incompatibleBlocking) as $slug) {
                    $this->disablePluginConfig($slug);
                    $io->writeln(sprintf('  - Disabled %s.', $slug));
                }
                $io->writeln('Continuing with incompatible plugins disabled.');
            } else {
                if (method_exists(Install::class, 'allowIncompatibleOverride')) {
                    Install::allowIncompatibleOverride(true);
                }
                $io->writeln('    › Proceeding despite incompatible plugins/themes.');
            }
        }

        // Show incompatible warnings (disabled packages) — informational only
        $incompatibleWarnings = $incompatible['warnings'] ?? [];
        if ($incompatibleWarnings) {
            $io->newLine();
            $io->writeln('<cyan>Disabled plugins/themes not yet compatible with Grav ' . $incompatibleTarget . ' (will not block upgrade):</cyan>');
            foreach ($incompatibleWarnings as $slug => $info) {
                $type = $info['type'] ?? 'plugin';
                $ver = $info['version'] ?? 'unknown';
                $io->writeln(sprintf('  - %s (%s v%s)', $slug, $type, $ver));
            }
        }

        $handled = $this->handleConflicts(
            $conflicts,
            static function (SymfonyStyle $io, array $conflicts): void {
                $io->newLine();
                $io->writeln('<yellow>Potential psr/log incompatibilities:</yellow>');
                foreach ($conflicts as $slug => $info) {
                    $requires = $info['requires'] ?? '*';
                    $io->writeln(sprintf('  - %s (requires psr/log %s)', $slug, $requires));
                }
            },
            'Update the plugin or add "replace": {"psr/log": "*"} to its composer.json.',
            'Aborting self-upgrade. Adjust composer requirements or update affected plugins.',
            'Proceeding with potential psr/log incompatibilities still active.'
        );

        if (!$handled) {
            return false;
        }

        $handledMonolog = $this->handleConflicts(
            $monologConflicts,
            static function (SymfonyStyle $io, array $conflicts): void {
                $io->newLine();
                $io->writeln('<yellow>Potential Monolog logger API incompatibilities:</yellow>');
                foreach ($conflicts as $slug => $entries) {
                    foreach ($entries as $entry) {
                        $file = $entry['file'] ?? 'unknown file';
                        $method = $entry['method'] ?? 'add*';
                        $io->writeln(sprintf('  - %s (%s in %s)', $slug, $method, $file));
                    }
                }
            },
            'Update the plugin to use PSR-3 style logger methods before upgrading.',
            'Aborting self-upgrade. Update plugins to remove deprecated Monolog add* calls.',
            'Proceeding with potential Monolog API incompatibilities still active.'
        );

        if (!$handledMonolog) {
            return false;
        }

        return true;
    }

    /**
     * Handle a set of conflicts with user choice (disable/continue/abort).
     *
     * @param array $conflicts
     * @param callable $printer
     * @param string $advice
     * @param string $abortMessage
     * @param string $continueMessage
     * @return bool
     */
    private function handleConflicts(array $conflicts, callable $printer, string $advice, string $abortMessage, string $continueMessage): bool
    {
        if (empty($conflicts)) {
            return true;
        }

        $io = $this->getIO();
        $printer($io, $conflicts);
        $io->writeln('    › ' . $advice);

        $choice = $this->all_yes ? 'abort' : $io->choice(
            'How would you like to proceed?',
            ['disable', 'continue', 'abort'],
            'abort'
        );

        if ($choice === 'abort') {
            $io->writeln($abortMessage);

            return false;
        }

        if ($choice === 'disable') {
            foreach (array_keys($conflicts) as $slug) {
                $this->disablePluginConfig($slug);
                $io->writeln(sprintf('  - Disabled plugin %s.', $slug));
            }
            $io->writeln('Continuing with conflicted plugins disabled.');

            return true;
        }

        $io->writeln($continueMessage);

        return true;
    }

    /**
     * Disable a plugin by writing enabled: false to its config file.
     * Used on 1.7 where RecoveryManager may not be available.
     *
     * @param string $slug
     */
    private function disablePluginConfig(string $slug): void
    {
        $configPath = GRAV_ROOT . '/user/config/plugins/' . $slug . '.yaml';

        try {
            if (is_file($configPath)) {
                $contents = @file_get_contents($configPath);
                $data = $contents !== false ? Yaml::parse($contents) : [];
                if (!is_array($data)) {
                    $data = [];
                }
            } else {
                $data = [];
            }

            $data['enabled'] = false;
            file_put_contents($configPath, Yaml::dump($data));
        } catch (Throwable $e) {
            // best effort — if config write fails, upgrade will be blocked by Install.php
        }
    }
}
