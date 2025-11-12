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
// NOTE: SafeUpgradeService removed - no longer used in this file
// Preflight is now handled in Install.php after downloading the package
use Grav\Installer\Install;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use ZipArchive;
use function date;
use function count;
use function is_callable;
use function strlen;
use function stripos;

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
    /** @var string|null */
    private $lastProgressMessage = null;
    /** @var float|null */
    private $operationTimerStart = null;
    /** @var string|null */
    private $currentProgressStage = null;
    /** @var float|null */
    private $currentStageStartedAt = null;
    /** @var array */
    private $currentStageExtras = [];

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
            ->addOption(
                'safe',
                null,
                InputOption::VALUE_NONE,
                'Force safe upgrade staging even if disabled in configuration'
            )
            ->addOption(
                'legacy',
                null,
                InputOption::VALUE_NONE,
                'Force legacy in-place upgrade even if safe upgrade is enabled'
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
        $forceSafe = (bool) $input->getOption('safe');
        $forceLegacy = (bool) $input->getOption('legacy');
        $forcedMode = null;

        if ($forceSafe && $forceLegacy) {
            $io->error('Cannot force safe and legacy upgrade modes simultaneously.');

            return 1;
        }

        if ($forceSafe || $forceLegacy) {
            $forcedMode = $forceSafe ? true : false;
            // NOTE: Do not call Install::forceSafeUpgrade() here as it would load the old Install class
            // before the upgrade package is extracted, causing a class redeclaration error.
            // Instead, we set the config and also use an environment variable as a fallback.
            putenv('GRAV_FORCE_SAFE_UPGRADE=' . ($forcedMode ? '1' : '0'));
            try {
                $grav = Grav::instance();
                if ($grav && isset($grav['config'])) {
                    $grav['config']->set('system.updates.safe_upgrade', $forcedMode);
                }
            } catch (\Throwable $e) {
                // Ignore container bootstrap failures; mode override still applies via env var.
            }
            if ($forceSafe) {
                $io->note('Safe upgrade staging forced for this run.');
            } else {
                $io->warning('Legacy in-place upgrade forced for this run.');
            }
        }

        try {
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

            // NOTE: Preflight checks are now run in Install.php AFTER downloading the package.
            // This ensures we use the NEW SafeUpgradeService from the package, not the old one.
            // Running preflight here would load the OLD class into memory and prevent the new one from loading.

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
                        $io->writeln("  |- <green>Success!</green>  ");
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
                            return "\n" . ucfirst((string) $match[1]) . ':';
                        }, (string) $log['content']);

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

            /** @var \Grav\Common\Recovery\RecoveryManager $recovery */
            $recovery = Grav::instance()['recovery'];
            $recovery->markUpgradeWindow('core-upgrade', [
                'scope' => 'core',
                'target_version' => $remote,
            ]);

            $io->write("  |- Downloading upgrade [{$this->formatBytes($update['size'])}]...     0%");
            $this->file = $this->download($update);

            $io->write('  |- Installing upgrade...  ');
            $this->operationTimerStart = microtime(true);
            $installation = $this->upgrade();

            $error = 0;
            if (!$installation) {
                $io->writeln("  |- <red>Installation failed or aborted.</red>");
                $io->newLine();
                $error = 1;
            } else {
                $io->writeln("  |- <green>Success!</green>  ");

                $manifest = Install::instance()->getLastManifest();
                if (is_array($manifest) && ($manifest['id'] ?? null)) {
                    $snapshotId = (string) $manifest['id'];
                    $snapshotTimestamp = isset($manifest['created_at']) ? (int) $manifest['created_at'] : null;
                    $manifestPath = null;
                    if (isset($manifest['id'])) {
                        $manifestPath = 'user/data/upgrades/' . $manifest['id'] . '.json';
                    }
                    $metadata = [
                        'scope' => 'core',
                        'target_version' => $remote,
                        'snapshot' => $snapshotId,
                    ];
                    if (null !== $snapshotTimestamp) {
                        $metadata['snapshot_created_at'] = $snapshotTimestamp;
                    }
                    if ($manifestPath) {
                        $metadata['snapshot_manifest'] = $manifestPath;
                    }

                    $recovery->markUpgradeWindow('core-upgrade', $metadata);

                    $io->writeln(sprintf("  |- Recovery snapshot: <cyan>%s</cyan>", $snapshotId));
                    if (null !== $snapshotTimestamp) {
                        $io->writeln(sprintf("  |- Snapshot captured: <white>%s</white>", date('c', $snapshotTimestamp)));
                    }
                    if ($manifestPath) {
                        $io->writeln(sprintf("  |- Manifest stored at: <white>%s</white>", $manifestPath));
                    }
                } else {
                    // Ensure recovery window remains active even if manifest could not be resolved.
                    $recovery->markUpgradeWindow('core-upgrade', [
                        'scope' => 'core',
                        'target_version' => $remote,
                    ]);
                }

                $io->newLine();
                // Clear recovery flag - upgrade completed successfully
                $recovery->closeUpgradeWindow();
            }

            if ($this->tmp && is_dir($this->tmp)) {
                Folder::delete($this->tmp);
            }

            return $error;
        } finally {
            if (null !== $forcedMode) {
                // Clean up environment variable
                putenv('GRAV_FORCE_SAFE_UPGRADE');
                // Only call Install::forceSafeUpgrade if Install class has been loaded
                if (class_exists(\Grav\Installer\Install::class, false)) {
                    Install::forceSafeUpgrade(null);
                }
            }
        }
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

        $output = Response::get($package['download'], $options, $this->progress(...));

        Folder::create($this->tmp);

        $io->write("\x0D");
        $io->write("  |- Downloading upgrade [{$this->formatBytes($package['size'])}]...   100%");
        $io->newLine();

        file_put_contents($this->tmp . DS . $package['name'], $output);

        return $this->tmp . DS . $package['name'];
    }

    /**
     * @param array $preflight
     * @return bool
     */
    protected function handlePreflightReport(array $preflight): bool
    {
        $io = $this->getIO();
        $pending = $preflight['plugins_pending'] ?? [];
        $blocking = $preflight['blocking'] ?? [];
        $conflicts = $preflight['psr_log_conflicts'] ?? [];
        $monologConflicts = $preflight['monolog_conflicts'] ?? [];
        $warnings = $preflight['warnings'] ?? [];
        $isMajorMinorUpgrade = $preflight['is_major_minor_upgrade'] ?? null;
        if ($isMajorMinorUpgrade === null && $this->upgrader) {
            $local = $this->upgrader->getLocalVersion();
            $remote = $this->upgrader->getRemoteVersion();
            $localParts = explode('.', $local);
            $remoteParts = explode('.', $remote);

            $localMajor = (int)($localParts[0] ?? 0);
            $localMinor = (int)($localParts[1] ?? 0);
            $remoteMajor = (int)($remoteParts[0] ?? 0);
            $remoteMinor = (int)($remoteParts[1] ?? 0);

            $isMajorMinorUpgrade = ($localMajor !== $remoteMajor) || ($localMinor !== $remoteMinor);
        }
        $isMajorMinorUpgrade = (bool)$isMajorMinorUpgrade;

        if ($warnings) {
            $io->newLine();
            $io->writeln('<magenta>Preflight warnings detected:</magenta>');
            foreach ($warnings as $warning) {
                $io->writeln('  • ' . $warning);
            }
        }

        if ($blocking && empty($pending)) {
            $io->newLine();
            $io->writeln('<red>Upgrade blocked:</red>');
            foreach ($blocking as $reason) {
                $io->writeln('  - ' . $reason);
            }

            return false;
        }

        if (empty($pending) && empty($conflicts) && empty($monologConflicts)) {
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

            $io->writeln('    › For major version upgrades (v' . $local . ' → v' . $remote . '), plugins must be updated to their latest');
            $io->writeln('      compatible versions BEFORE upgrading Grav core to ensure compatibility.');
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

            Install::allowPendingPackageOverride(true);
            $io->writeln('    › Proceeding despite pending plugin/theme updates.');
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
            'Update the plugin or add "replace": {"psr/log": "*"} to its composer.json and reinstall dependencies.',
            'Aborting self-upgrade. Adjust composer requirements or update affected plugins.',
            'Proceeding with potential psr/log incompatibilities still active.',
            'Disabled before upgrade because of psr/log conflict'
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
            'Update the plugin to use PSR-3 style logger methods (e.g. $logger->error()) before upgrading.',
            'Aborting self-upgrade. Update plugins to remove deprecated Monolog add* calls.',
            'Proceeding with potential Monolog API incompatibilities still active.',
            'Disabled before upgrade because of Monolog API conflict'
        );

        if (!$handledMonolog) {
            return false;
        }

        return true;
    }

    /**
     * @param array $conflicts
     * @param callable $printer
     * @param string $advice
     * @param string $abortMessage
     * @param string $continueMessage
     * @param string $disableNote
     * @return bool
     */
    private function handleConflicts(array $conflicts, callable $printer, string $advice, string $abortMessage, string $continueMessage, string $disableNote): bool
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

        /** @var \Grav\Common\Recovery\RecoveryManager $recovery */
        $recovery = Grav::instance()['recovery'];

        if ($choice === 'disable') {
            foreach (array_keys($conflicts) as $slug) {
                $recovery->disablePlugin($slug, ['message' => $disableNote]);
                $io->writeln(sprintf('  - Disabled plugin %s.', $slug));
            }
            $io->writeln('Continuing with conflicted plugins disabled.');

            return true;
        }

        $io->writeln($continueMessage);

        return true;
    }

    /**
     * @return bool
     */
    private function upgrade(): bool
    {
        $io = $this->getIO();
        $this->lastProgressMessage = null;

        $this->upgradeGrav($this->file);
        $this->finalizeStageTracking();

        $elapsed = null;
        if (null !== $this->operationTimerStart) {
            $elapsed = microtime(true) - $this->operationTimerStart;
            $this->operationTimerStart = null;
        }

        $errorCode = Installer::lastErrorCode();
        if ($errorCode) {
            $io->write("\x0D");
            // extra white spaces to clear out the buffer properly
            $io->writeln('  |- Installing upgrade...    <red>error</red>                             ');
            $io->writeln("  |  '- " . Installer::lastErrorMsg());

            return false;
        }

        if (null !== $elapsed) {
            $io->writeln(sprintf('  |- Safe upgrade staging completed in %s', $this->formatDuration($elapsed)));
        }

        $io->write("\x0D");
        // extra white spaces to clear out the buffer properly
        $io->writeln('  |- Installing upgrade...    <green>ok</green>                             ');

        $this->ensureExecutablePermissions();

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
            (string) $progress['percent'],
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
        $suffixes = ['', 'k', 'M', 'G', 'T'];

        return round(1024 ** ($base - floor($base)), $precision) . $suffixes[(int)floor($base)];
    }

    /**
     * @param string $zip
     * @return void
     */
    private function upgradeGrav(string $zip): void
    {
        $io = $this->getIO();

        try {
            $io->write("\x0D  |- Extracting update...                    ");
            $folder = Installer::unZip($zip, $this->tmp . '/zip');
            if ($folder === false) {
                throw new RuntimeException(Installer::lastErrorMsg());
            }
            $io->write("\x0D");
            $io->writeln('  |- Extracting update...    <green>ok</green>                ');

            $script = $folder . '/system/install.php';
            if ((file_exists($script) && $install = include $script) && is_callable($install)) {
                if (is_object($install) && method_exists($install, 'setProgressCallback')) {
                    $install->setProgressCallback(function (string $stage, string $message, ?int $percent = null, array $extra = []) {
                        $this->handleServiceProgress($stage, $message, $percent);
                    });
                }
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

    private function handleServiceProgress(string $stage, string $message, ?int $percent = null, array $extra = []): void
    {
        $this->trackStageProgress($stage, $message, $extra);

        if ($this->lastProgressMessage === $message) {
            return;
        }

        $this->lastProgressMessage = $message;
        $io = $this->getIO();
        $suffix = '';
        if (null !== $percent) {
            $suffix = sprintf(' (%d%%)', $percent);
        }
        $io->writeln(sprintf('  |- %s%s', $message, $suffix));
    }

    private function ensureExecutablePermissions(): void
    {
        $executables = [
            'bin/grav',
            'bin/plugin',
            'bin/gpm',
            'bin/restore',
            'bin/composer.phar'
        ];

        foreach ($executables as $relative) {
            $path = GRAV_ROOT . '/' . $relative;
            if (!is_file($path) || is_link($path)) {
                continue;
            }

            $mode = @fileperms($path);
            $desired = ($mode & 0777) | 0111;
            if (($mode & 0111) !== 0111) {
                @chmod($path, $desired);
            }
        }
    }

    private function trackStageProgress(string $stage, string $message, array $extra = []): void
    {
        $now = microtime(true);

        if (null !== $this->currentProgressStage && $stage !== $this->currentProgressStage && null !== $this->currentStageStartedAt) {
            $elapsed = $now - $this->currentStageStartedAt;
            $this->emitStageSummary($this->currentProgressStage, $elapsed, $this->currentStageExtras);
            $this->currentStageExtras = [];
        }

        if ($stage !== $this->currentProgressStage) {
            $this->currentProgressStage = $stage;
            $this->currentStageStartedAt = $now;
            $this->currentStageExtras = [];
        }

        if (!isset($this->currentStageExtras['label'])) {
            $this->currentStageExtras['label'] = $message;
        }

        if ($extra) {
            $this->currentStageExtras = array_merge($this->currentStageExtras, $extra);
        }
    }

    private function finalizeStageTracking(): void
    {
        if (null !== $this->currentProgressStage && null !== $this->currentStageStartedAt) {
            $elapsed = microtime(true) - $this->currentStageStartedAt;
            $this->emitStageSummary($this->currentProgressStage, $elapsed, $this->currentStageExtras);
        }

        $this->currentProgressStage = null;
        $this->currentStageStartedAt = null;
        $this->currentStageExtras = [];
    }

    private function emitStageSummary(string $stage, float $seconds, array $extra = []): void
    {
        $io = $this->getIO();
        $label = $extra['label'] ?? ucfirst($stage);
        $modeText = '';
        if (isset($extra['mode'])) {
            $modeText = sprintf(' [%s]', $extra['mode']);
        }

        $io->writeln(sprintf('  |- %s completed in %s%s', $label, $this->formatDuration($seconds), $modeText));
    }

    private function formatDuration(float $seconds): string
    {
        if ($seconds < 1) {
            return sprintf('%0.3fs', $seconds);
        }

        $minutes = (int)floor($seconds / 60);
        $remaining = $seconds - ($minutes * 60);

        if ($minutes === 0) {
            return sprintf('%0.1fs', $remaining);
        }

        return sprintf('%dm %0.1fs', $minutes, $remaining);
    }
}
