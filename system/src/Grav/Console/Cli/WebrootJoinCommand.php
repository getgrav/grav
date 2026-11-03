<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Config\InstallLayout;
use Grav\Common\Config\WebrootPlanner;
use Grav\Console\GravCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * bin/grav webroot:join
 */
class WebrootJoinCommand extends GravCommand
{
    use WebrootOutputTrait;

    protected function configure(): void
    {
        $this
            ->setName('webroot:join')
            ->setDescription('Moves a public webroot site back to the single-folder layout')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the plan and change nothing')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Do not ask for confirmation')
            ->setHelp(<<<'HELP'
The <info>webroot:join</info> command undoes <info>webroot:split</info>. It moves everything in the webroot back into the Grav folder, removes the published system files and the then empty webroot folder, rewrites relative symlinks so they still point at the same place, and clears the cache.

It refuses to start when a name exists in both folders, or when the webroot's system/ folder holds files that <info>webroot:sync</info> did not put there.

Afterwards, point the web server's docroot back at the Grav folder.
HELP
            );
    }

    protected function serve(): int
    {
        $io = $this->getIO();
        $input = $this->getInput();

        $io->title('Join the webroot back into the Grav folder');

        if (!InstallLayout::isSplit()) {
            $io->error('This site already uses the single-folder layout.');

            return 1;
        }

        $plan = WebrootPlanner::join(GRAV_ROOT, GRAV_WEBROOT);
        if (InstallLayout::webrootSource() === 'env') {
            $plan->notes[] = 'GRAV_WEBROOT is set in the environment or .env. Remove it, or Grav keeps looking for the old webroot.';
        }

        if (!$plan->canApply()) {
            $io->error($plan->errors);

            return 1;
        }

        $this->printPlan($plan);
        foreach ($plan->notes as $note) {
            $io->note($note);
        }

        if ($input->getOption('dry-run')) {
            $io->note('Dry run: nothing was changed.');

            return 0;
        }

        if (!$input->getOption('yes') && !$io->confirm('Join this site now?', false)) {
            $io->writeln('Nothing was changed.');

            return 1;
        }

        if ($this->applyPlan($plan) !== 0) {
            return 1;
        }

        $io->success(sprintf('The site is back in the single-folder layout in %s.', $plan->root));
        $io->writeln('<white>What you need to do now:</white>');
        $io->writeln('1. Point the web server docroot back at ' . $plan->root . ':');
        $this->printDocrootSnippets($plan->root);
        $io->writeln('2. Run <info>bin/grav webroot:status</info> to check the result.');
        foreach ($plan->notes as $note) {
            $io->writeln('3. ' . $note);
        }
        $io->newLine();

        return 0;
    }
}
