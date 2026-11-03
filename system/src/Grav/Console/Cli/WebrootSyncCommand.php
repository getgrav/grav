<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Config\WebrootPublisher;
use Grav\Console\GravCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * bin/grav webroot:sync
 */
class WebrootSyncCommand extends GravCommand
{
    protected function configure(): void
    {
        $this
            ->setName('webroot:sync')
            ->setDescription('Publishes the system files linked by URL into the public webroot')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Change nothing; exit with 1 when the published files are out of sync')
            ->setHelp(<<<'HELP'
In the public webroot layout system/ is private, but a few of its files are linked by URL: <cyan>system/assets/</cyan> (jQuery, the debugger's CSS and JS) and <cyan>system/images/</cyan> (the media type icons). The <info>webroot:sync</info> command copies the static files of those two folders into <cyan>system/</cyan> in the webroot, removes the ones that are no longer in system/, and writes a manifest so <info>webroot:status</info> can tell when the copy is stale. PHP, YAML, Twig and config files are never copied.

Core upgrades run it on their own. In the single-folder layout there is nothing to do.
HELP
            );
    }

    protected function serve(): int
    {
        $io = $this->getIO();
        $check = (bool) $this->getInput()->getOption('check');

        $publisher = WebrootPublisher::forSite();
        if ($publisher === null) {
            $io->writeln('This site uses the single-folder layout, so system/ is served as it is and there is nothing to publish.');

            return 0;
        }

        $plan = $publisher->plan();
        $changes = [
            'new' => $plan['copy'],
            'changed' => $plan['update'],
            'removed' => $plan['delete'],
        ];

        // One line per file only when there are few of them, or with -v.
        $list = $io->isVerbose() || \count($plan['copy']) + \count($plan['update']) + \count($plan['delete']) <= 20;
        foreach ($list ? $changes : [] as $label => $files) {
            foreach ($files as $file) {
                $io->writeln(sprintf('  %-8s system/%s', $label, $file));
            }
        }
        foreach ($plan['extra'] as $file) {
            $io->writeln(sprintf('  <yellow>%-8s</yellow> system/%s (not published by webroot:sync, left alone)', 'extra', $file));
        }

        if ($publisher->isInSync($plan)) {
            $io->writeln(sprintf('<green>%d published system files in %s are in sync.</green>', $plan['total'], $publisher->target()));

            return 0;
        }

        if ($check) {
            $io->writeln(sprintf('<red>Published system files in %s are out of sync.</red> Run bin/grav webroot:sync.', $publisher->target()));

            return 1;
        }

        try {
            $publisher->sync();
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return 1;
        }

        $io->writeln(sprintf(
            '<green>Published system files to %s:</green> %d new, %d changed, %d removed, %d in total.',
            $publisher->target(),
            \count($plan['copy']),
            \count($plan['update']),
            \count($plan['delete']),
            $plan['total']
        ));

        return 0;
    }
}
