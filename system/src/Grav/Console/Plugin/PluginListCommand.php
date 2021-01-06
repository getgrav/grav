<?php

/**
 * @package    Grav\Console\Plugin
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Plugin;

use Grav\Common\Filesystem\Folder;
use Grav\Common\Plugins;
use Grav\Console\ConsoleCommand;

/**
 * Class InfoCommand
 * @package Grav\Console\Gpm
 */
class PluginListCommand extends ConsoleCommand
{
    protected static $defaultName = 'plugins:list';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setHidden(true);
    }

    /**
     * @return int
     */
    protected function serve(): int
    {
        $bin = 'bin/plugin';
        $pattern = '([A-Z]\w+Command\.php)';

        $output = $this->output;
        $output->writeln('');
        $output->writeln('<red>Usage:</red>');
        $output->writeln("  {$bin} [slug] [command] [arguments]");
        $output->writeln('');
        $output->writeln('<red>Example:</red>');
        $output->writeln("  {$bin} error log -l 1 --trace");

        $output->writeln('');
        $output->writeln('<red>Plugins with CLI available:</red>');

        $plugins = Plugins::all();
        $total = 0;
        foreach ($plugins as $name => $plugin) {
            if (!$plugin->enabled) {
                continue;
            }

            $list = Folder::all("plugins://{$name}", ['compare' => 'Pathname', 'pattern' => '/\/cli\/' . $pattern . '$/usm', 'levels' => 1]);
            if (!$list) {
                continue;
            }

            $total++;
            $index = str_pad($total, 2, '0', STR_PAD_LEFT);
            $output->writeln('  ' . $index . '. <red>' . str_pad($name, 15) . "</red> <white>{$bin} {$name} list</white>");
        }

        return 0;
    }
}
