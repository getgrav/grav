<?php

namespace Grav\Console\Gpm;

use Grav\Common\Grav;
use Grav\Common\Upgrade\SafeUpgradeService;
use Grav\Console\GpmCommand;
use Symfony\Component\Console\Input\InputOption;
use function json_encode;
use const JSON_PRETTY_PRINT;

class PreflightCommand extends GpmCommand
{
    protected function configure(): void
    {
        $this
            ->setName('preflight')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output report as JSON')
            ->setDescription('Run Grav upgrade preflight checks without modifying the installation.');
    }

    protected function serve(): int
    {
        $io = $this->getIO();
        $service = $this->createSafeUpgradeService();
        $report = $service->preflight();

        $hasIssues = !empty($report['plugins_pending']) || !empty($report['psr_log_conflicts']) || !empty($report['monolog_conflicts']) || !empty($report['warnings']);

        if ($this->getInput()->getOption('json')) {
            $io->writeln(json_encode($report, JSON_PRETTY_PRINT));

            return $hasIssues ? 2 : 0;
        }

        $io->title('Grav Upgrade Preflight');

        if (!empty($report['warnings'])) {
            $io->writeln('<comment>Warnings</comment>');
            foreach ($report['warnings'] as $warning) {
                $io->writeln('  - ' . $warning);
            }
            $io->newLine();
        }

        if (!empty($report['plugins_pending'])) {
            $io->writeln('<comment>Packages pending update</comment>');
            foreach ($report['plugins_pending'] as $slug => $info) {
                $io->writeln(sprintf('  - %s (%s) %s → %s', $slug, $info['type'] ?? 'plugin', $info['current'] ?? 'unknown', $info['available'] ?? 'unknown'));
            }
            $io->newLine();
        }

        if (!empty($report['psr_log_conflicts'])) {
            $io->writeln('<comment>Potential psr/log conflicts</comment>');
            foreach ($report['psr_log_conflicts'] as $slug => $info) {
                $io->writeln(sprintf('  - %s (requires psr/log %s)', $slug, $info['requires'] ?? '*'));
            }
            $io->writeln('    › Update the plugin or add "replace": {"psr/log": "*"} to its composer.json and reinstall dependencies.');
            $io->newLine();
        }

        if (!empty($report['monolog_conflicts'])) {
            $io->writeln('<comment>Potential Monolog logger conflicts</comment>');
            foreach ($report['monolog_conflicts'] as $slug => $entries) {
                foreach ($entries as $entry) {
                    $file = $entry['file'] ?? 'unknown file';
                    $method = $entry['method'] ?? 'add*';
                    $io->writeln(sprintf('  - %s (%s in %s)', $slug, $method, $file));
                }
            }
            $io->writeln('    › Update the plugin to use PSR-3 style logger calls (e.g. $logger->error()).');
            $io->newLine();
        }

        if (!$hasIssues) {
            $io->success('No blocking issues detected.');
        } else {
            $io->warning('Resolve the findings above before upgrading Grav.');
        }

        return $hasIssues ? 2 : 0;
    }

    /**
     * @return SafeUpgradeService
     */
    protected function createSafeUpgradeService(): SafeUpgradeService
    {
        $config = null;
        try {
            $config = Grav::instance()['config'] ?? null;
        } catch (\Throwable $e) {
            $config = null;
        }

        return new SafeUpgradeService([
            'config' => $config,
        ]);
    }
}
