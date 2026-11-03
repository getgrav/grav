<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Cache;
use Grav\Common\Config\WebrootPlan;
use Grav\Common\Config\WebrootStatus;

/**
 * Output shared by the webroot:* commands.
 */
trait WebrootOutputTrait
{
    protected function printDocrootSnippets(string $webroot): void
    {
        $io = $this->getIO();
        foreach (WebrootStatus::docrootSnippets($webroot) as $server => $snippet) {
            $io->writeln("<cyan>{$server}</cyan>");
            foreach (explode("\n", $snippet) as $line) {
                $io->writeln('    ' . $line);
            }
        }
        $io->newLine();
    }

    protected function printPlan(WebrootPlan $plan): void
    {
        $io = $this->getIO();
        $io->writeln(sprintf('Private folder: <white>%s</white>', $plan->root));
        $io->writeln(sprintf('Webroot:        <white>%s</white>', $plan->webroot));
        $io->newLine();

        if ($plan->action === WebrootPlan::SPLIT) {
            $io->writeln(is_dir($plan->webroot) ? 'Use the empty folder ' . $plan->webroot : 'Create ' . $plan->webroot);
        } elseif ($plan->publisher) {
            $io->writeln('Remove the published system files in ' . $plan->publisher->target());
        }

        $io->writeln('Move:');
        foreach ($plan->moves as $from => $to) {
            $io->writeln(sprintf('    %s  ->  %s', $this->shortPath($from, $plan), $this->shortPath($to, $plan)));
        }

        if ($plan->links) {
            $io->writeln('Rewrite symlinks so they point at the same place:');
            foreach ($plan->links as $link => $change) {
                $io->writeln(sprintf('    %s: %s  ->  %s', $this->shortPath($link, $plan), $change['from'], $change['to']));
            }
        }

        if ($plan->action === WebrootPlan::SPLIT) {
            $io->writeln(sprintf('Publish system/%s into %s', implode(', system/', \Grav\Common\Config\WebrootPublisher::FOLDERS), $this->shortPath($plan->webroot . '/system', $plan)));
        } else {
            $io->writeln('Remove the empty folder ' . $plan->webroot);
        }
        $io->writeln('Clear the cache');
        $io->newLine();

        if ($plan->unknown) {
            $io->writeln('<yellow>Left in the private folder</yellow> (not part of Grav, so it stays private):');
            foreach ($plan->unknown as $name) {
                $io->writeln('    ' . $name);
            }
            $io->writeln('Re-run with <cyan>--move=NAME</cyan> for any of these that must be public (favicon.ico, .well-known/, site verification files...).');
            $io->newLine();
        }
    }

    protected function shortPath(string $path, WebrootPlan $plan): string
    {
        return str_starts_with($path, $plan->root . '/') ? substr($path, \strlen($plan->root) + 1) : $path;
    }

    protected function applyPlan(WebrootPlan $plan): int
    {
        $io = $this->getIO();
        try {
            $problems = $plan->apply(function (string $line) use ($io) {
                $io->writeln('  ' . $line);
            });
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $io->writeln('Stopped part way. Run bin/grav webroot:status to see where things are.');

            return 1;
        }

        // Compiled config and the other caches hold paths that just moved.
        Cache::clearCache('standard');
        $io->writeln('  Cleared the cache');
        $io->newLine();

        foreach ($problems as $problem) {
            $io->warning($problem);
        }

        return 0;
    }
}
