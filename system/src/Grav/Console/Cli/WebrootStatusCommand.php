<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Config\InstallLayout;
use Grav\Common\Config\WebrootStatus;
use Grav\Console\GravCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * bin/grav webroot:status
 */
class WebrootStatusCommand extends GravCommand
{
    use WebrootOutputTrait;

    protected function configure(): void
    {
        $this
            ->setName('webroot:status')
            ->setDescription('Shows the install layout (single folder or public webroot) and checks it')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the result as JSON')
            ->setHelp(<<<'HELP'
The <info>webroot:status</info> command shows whether this site uses the single-folder layout or the public webroot layout (a private Grav folder with only its <cyan>public/</cyan> subfolder served), where the private folder and the webroot are, and how the webroot was found.

It then checks the layout: private files inside the webroot, files missing from the webroot, a stray index.php or user/ in the private folder, the published system files, index.php, and broken relative symlinks in user/. The command exits with 1 when any check fails.
HELP
            );
    }

    protected function serve(): int
    {
        $io = $this->getIO();
        $status = new WebrootStatus(GRAV_ROOT, GRAV_WEBROOT, GRAV_USER_PATH, InstallLayout::resolve(GRAV_ROOT, GRAV_SYSTEM_PATH));
        $checks = $status->checks();
        $failed = WebrootStatus::hasFailures($checks);
        $source = InstallLayout::webrootSource();
        $foundBy = [
            'single' => 'single folder (GRAV_ROOT is the webroot)',
            'env' => 'GRAV_WEBROOT environment variable or .env',
            'candidate' => sprintf('the "%s" folder (one of %s)', basename(GRAV_WEBROOT), implode(', ', InstallLayout::WEBROOT_CANDIDATES)),
        ][$source];

        if ($this->getInput()->getOption('json')) {
            $io->writeln((string) json_encode([
                'layout' => $status->isSplit() ? 'split' : 'single',
                'root' => GRAV_ROOT,
                'webroot' => GRAV_WEBROOT,
                'found_by' => $source,
                'checks' => $checks,
                'ok' => !$failed,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $failed ? 1 : 0;
        }

        $io->title('Webroot status');
        $io->writeln(sprintf('Layout:         <white>%s</white>', $status->isSplit() ? 'public webroot (split)' : 'single folder'));
        $io->writeln(sprintf('Grav folder:    <white>%s</white>', GRAV_ROOT));
        $io->writeln(sprintf('Webroot:        <white>%s</white>', GRAV_WEBROOT));
        $io->writeln(sprintf('Found by:       %s', $foundBy));
        $io->newLine();

        $labels = [WebrootStatus::PASS => '<green>pass</green>', WebrootStatus::WARN => '<yellow>warn</yellow>', WebrootStatus::FAIL => '<red>FAIL</red>'];
        foreach ($checks as $check) {
            $io->writeln(sprintf('  [%s] %s', $labels[$check['status']], $check['message']));
            foreach (\array_slice($check['details'], 0, 20) as $detail) {
                $io->writeln('         ' . $detail);
            }
            if (\count($check['details']) > 20) {
                $io->writeln(sprintf('         ... and %d more', \count($check['details']) - 20));
            }
        }
        $io->newLine();

        if ($status->isSplit()) {
            $io->writeln('The web server docroot must be the webroot:');
            $this->printDocrootSnippets(GRAV_WEBROOT);
        }

        if ($failed) {
            $io->error('Some checks failed.');
        } else {
            $io->success('All checks passed.');
        }

        return $failed ? 1 : 0;
    }
}
