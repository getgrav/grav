<?php

namespace Grav\Console\Gpm;

use Grav\Common\Upgrade\SafeUpgradeService;
use Grav\Console\GpmCommand;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use function basename;
use function file_get_contents;
use function glob;
use function is_array;
use function json_decode;
use function pathinfo;
use const PATHINFO_FILENAME;
use const GRAV_ROOT;

class RollbackCommand extends GpmCommand
{
    /** @var bool */
    private $allYes = false;

    protected function configure(): void
    {
        $this
            ->setName('rollback')
            ->addArgument('manifest', InputArgument::OPTIONAL, 'Manifest identifier to roll back to. Defaults to the latest snapshot.')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List available snapshots')
            ->addOption('all-yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompts')
            ->setDescription('Rollback Grav to a previously staged snapshot.');
    }

    protected function serve(): int
    {
        $input = $this->getInput();
        $io = $this->getIO();
        $this->allYes = (bool)$input->getOption('all-yes');

        $snapshots = $this->collectSnapshots();
        if ($input->getOption('list')) {
            if (!$snapshots) {
                $io->writeln('No snapshots found.');
                return 0;
            }

            $io->writeln('<info>Available snapshots:</info>');
            foreach ($snapshots as $snapshot) {
                $io->writeln(sprintf('  - %s (Grav %s)', $snapshot['id'], $snapshot['target_version'] ?? 'unknown'));
            }

            return 0;
        }

        if (!$snapshots) {
            $io->error('No snapshots available to roll back to.');

            return 1;
        }

        $targetId = $input->getArgument('manifest') ?: $snapshots[0]['id'];
        $target = null;
        foreach ($snapshots as $snapshot) {
            if ($snapshot['id'] === $targetId) {
                $target = $snapshot;
                break;
            }
        }

        if (!$target) {
            $io->error(sprintf('Snapshot %s not found.', $targetId));

            return 1;
        }

        if (!$this->allYes) {
            $question = new ConfirmationQuestion(sprintf('Rollback to snapshot %s (Grav %s)? [y|N] ', $target['id'], $target['target_version'] ?? 'unknown'), false);
            if (!$io->askQuestion($question)) {
                $io->writeln('Rollback aborted.');

                return 1;
            }
        }

        $service = $this->createSafeUpgradeService();

        try {
            $service->rollback($target['id']);
            $service->clearRecoveryFlag();
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return 1;
        }

        $io->success(sprintf('Rolled back to snapshot %s.', $target['id']));

        return 0;
    }

    /**
     * @return array<int, array>
     */
    protected function collectSnapshots(): array
    {
        $manifestDir = GRAV_ROOT . '/user/data/upgrades';
        $files = glob($manifestDir . '/*.json');
        if (!$files) {
            return [];
        }

        rsort($files);
        $snapshots = [];
        foreach ($files as $file) {
            $decoded = json_decode(file_get_contents($file), true);
            if (!is_array($decoded)) {
                continue;
            }

            $decoded['id'] = $decoded['id'] ?? pathinfo($file, PATHINFO_FILENAME);
            $decoded['file'] = basename($file);
            $snapshots[] = $decoded;
        }

        return $snapshots;
    }

    /**
     * @return SafeUpgradeService
     */
    protected function createSafeUpgradeService(): SafeUpgradeService
    {
        return new SafeUpgradeService();
    }
}
