<?php

/**
 * @package    Grav\Console\Cli
 *
 * Background worker for Safe Upgrade jobs.
 */

namespace Grav\Console\Cli;

use Grav\Console\GravCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class SafeUpgradeRunCommand extends GravCommand
{
    protected function configure(): void
    {
        $this
            ->setName('safe-upgrade:run')
            ->setDescription('Execute a queued Grav safe-upgrade job')
            ->addOption(
                'job',
                null,
                InputOption::VALUE_REQUIRED,
                'Job identifier to execute'
            );
    }

    protected function serve(): int
    {
        $input = $this->getInput();
        /** @var SymfonyStyle $io */
        $io = $this->getIO();

        $jobId = $input->getOption('job');
        if (!$jobId) {
            $io->error('Missing required --job option.');

            return 1;
        }

        if (method_exists($this, 'initializePlugins')) {
            $this->initializePlugins();
        }

        if (!class_exists(\Grav\Plugin\Admin\SafeUpgradeManager::class)) {
            $path = GRAV_ROOT . '/user/plugins/admin/classes/plugin/SafeUpgradeManager.php';
            if (is_file($path)) {
                require_once $path;
            }
        }

        if (!class_exists(\Grav\Plugin\Admin\SafeUpgradeManager::class)) {
            $io->error('SafeUpgradeManager is not available. Ensure the Admin plugin is installed.');

            return 1;
        }

        $manager = new \Grav\Plugin\Admin\SafeUpgradeManager();
        $manifest = $manager->loadJob($jobId);

        if (!$manifest) {
            $io->error(sprintf('Safe upgrade job "%s" could not be found.', $jobId));

            return 1;
        }

        $options = $manifest['options'] ?? [];
        $manager->updateJob([
            'status' => 'running',
            'started_at' => $manifest['started_at'] ?? time(),
        ]);

        try {
            $operation = $options['operation'] ?? 'upgrade';
            if ($operation === 'restore') {
                $result = $manager->runRestore($options);
            } else {
                $result = $manager->run($options);
            }
            $manager->ensureJobResult($result);

            return ($result['status'] ?? null) === 'success' ? 0 : 1;
        } catch (Throwable $e) {
            $manager->ensureJobResult([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
            $io->error($e->getMessage());

            return 1;
        }
    }
}
