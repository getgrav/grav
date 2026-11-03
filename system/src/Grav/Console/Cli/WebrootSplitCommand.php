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
 * bin/grav webroot:split
 */
class WebrootSplitCommand extends GravCommand
{
    use WebrootOutputTrait;

    protected function configure(): void
    {
        $this
            ->setName('webroot:split')
            ->setDescription('Moves a single-folder site to the public webroot layout')
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Name of the webroot folder to create (' . implode(', ', InstallLayout::WEBROOT_CANDIDATES) . ')', 'public')
            ->addOption('move', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Another top-level file or folder to move into the webroot, such as favicon.ico or .well-known')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the plan and change nothing')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Do not ask for confirmation')
            ->setHelp(<<<'HELP'
The <info>webroot:split</info> command moves a single-folder site to the public webroot layout. It creates the webroot folder (<cyan>public/</cyan> by default) and moves index.php, .htaccess, robots.txt, user/, assets/ and images/ into it. system/, vendor/, bin/, cache/, logs/, tmp/, backup/, .env and the Composer files stay in the private folder.

Relative symlinks in the moved folders that point outside them (such as <cyan>user/plugins/foo -> ../../../grav-plugin-foo</cyan>) are rewritten so they still point at the same place. The static files of system/ that are linked by URL are published into the webroot, and the cache is cleared.

Top-level files the command doesn't know (favicon.ico, .well-known/, verification files) stay private and are listed; add <cyan>--move=NAME</cyan> for each one that must be public.

Afterwards, point the web server's docroot at the new folder and run <info>bin/grav webroot:status</info>. Until the docroot changes, the web server serves the private folder without Grav's .htaccess rules. To avoid that window, change the docroot to the new folder first (the site is offline, not exposed, until the split runs). <info>bin/grav webroot:join</info> undoes the split.
HELP
            );
    }

    protected function serve(): int
    {
        $io = $this->getIO();
        $input = $this->getInput();

        $io->title('Split into a public webroot');

        if (InstallLayout::isSplit()) {
            $io->error(sprintf('This site already uses the public webroot layout (webroot %s). Run bin/grav webroot:status.', GRAV_WEBROOT));

            return 1;
        }

        $plan = WebrootPlanner::split(GRAV_ROOT, (string) $input->getOption('dir'), (array) $input->getOption('move'), GRAV_USER_PATH);

        if (!$plan->canApply()) {
            $io->error($plan->errors);

            return 1;
        }

        $this->printPlan($plan);

        if ($input->getOption('dry-run')) {
            $io->note('Dry run: nothing was changed.');

            return 0;
        }

        if (!$input->getOption('yes') && !$io->confirm('Split this site now?', false)) {
            $io->writeln('Nothing was changed.');

            return 1;
        }

        if ($this->applyPlan($plan) !== 0) {
            return 1;
        }

        $io->success(sprintf('The site now lives in %s, with %s as its webroot.', $plan->root, $plan->webroot));
        $io->writeln('<white>What you need to do now:</white>');
        $io->writeln('1. Point the web server docroot at ' . $plan->webroot . ':');
        $this->printDocrootSnippets($plan->webroot);
        $io->writeln('2. Run <info>bin/grav webroot:status</info> to check the result.');
        $io->newLine();
        $io->warning('Until the docroot changes, the web server serves the private folder without Grav\'s .htaccess rules, so change it now.');

        return 0;
    }
}
