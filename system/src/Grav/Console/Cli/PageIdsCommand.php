<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Grav;
use Grav\Common\Page\PageIdentity;
use Grav\Common\Page\Pages;
use Grav\Console\GravCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Gives every page folder a permanent id (`.page-id`), see PageIdentity.
 */
class PageIdsCommand extends GravCommand
{
    protected function configure(): void
    {
        $this
            ->setName('page-ids')
            ->setDescription('Gives every page folder a permanent id (.page-id)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change without writing anything')
            ->setHelp(
                'The <info>page-ids</info> command writes a hidden <cyan>.page-id</cyan> file with a new id into every page folder that has none, '
                . 'and gives a new id to folders that share one with another folder (a folder copied outside Grav). '
                . 'Of folders sharing an id, the one whose .page-id is oldest keeps it. Running it again changes nothing.'
            );
    }

    protected function serve(): int
    {
        $this->initializeGrav();

        $io = $this->getIO();
        $dryRun = (bool) $this->getInput()->getOption('dry-run');

        $io->newLine();
        $io->writeln('<magenta>Page ids</magenta>' . ($dryRun ? ' <yellow>(dry run, nothing is written)</yellow>' : ''));
        $io->newLine();

        $report = PageIdentity::assignAll($dryRun);

        // Ids written outside a page save: tell the pages cache, so the next
        // request indexes them instead of answering from the old index.
        if (!$dryRun && ($report['assigned'] || $report['duplicates'])) {
            $pages = Grav::instance()['pages'] ?? null;
            if ($pages instanceof Pages) {
                $pages->markChanged();
            }
        }

        foreach ($report['duplicates'] as $uuid => $duplicate) {
            $io->writeln(sprintf('<yellow>Duplicate id</yellow> %s', $uuid));
            $io->writeln(sprintf('  kept by    %s', PageIdentity::relative($duplicate['keeper'])));
            foreach ($duplicate['others'] as $other) {
                $io->writeln(sprintf('  %s %s', $dryRun ? 'would renew' : 'renewed   ', PageIdentity::relative($other)));
            }
        }
        if ($io->isVerbose()) {
            foreach ($report['assigned'] as $folder) {
                $io->writeln(sprintf('  %s %s', $dryRun ? 'would assign' : 'assigned', PageIdentity::relative($folder)));
            }
        }

        $renewed = 0;
        foreach ($report['duplicates'] as $duplicate) {
            $renewed += count($duplicate['others']);
        }

        $io->newLine();
        $io->writeln(sprintf(
            '%d page folders: %d already had an id, %d %s a new id, %d duplicate%s %s.',
            $report['folders'],
            $report['existing'],
            count($report['assigned']),
            $dryRun ? 'would get' : 'got',
            $renewed,
            $renewed === 1 ? '' : 's',
            $dryRun ? 'would be renewed' : 'renewed'
        ));

        if (!PageIdentity::enabled()) {
            $io->newLine();
            $io->writeln('<comment>system.pages.uuid is off, so Grav itself won\'t give new pages an id; plugins that need ids turn it on.</comment>');
        }

        return 0;
    }
}
